<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    /**
     * Loyalty points earned per 1 QAR spent on a checkout (rounded down).
     */
    const POINTS_PER_QAR = 1;

    /**
     * Cooldown window (in days) after retention outreach so two agents never
     * message the same client twice.
     */
    const RETENTION_COOLDOWN_DAYS = 14;

    const BRANCH_OLD_AIRPORT = 'old_airport';
    const BRANCH_WAKRAH = 'wakrah';
    const BRANCH_UNASSIGNED = 'unassigned';

    const BRANCHES = [
        self::BRANCH_OLD_AIRPORT => 'Old Airport',
        self::BRANCH_WAKRAH => 'Al Wakrah',
    ];

    protected $fillable = [
        'name',
        'phone',
        'email',
        'branch',
        'notes',
        'allergies',
        'loyalty_points',
        'last_retention_contacted_at',
    ];

    protected $casts = [
        'last_retention_contacted_at' => 'datetime',
    ];

    public function setBranchAttribute($value): void
    {
        $this->attributes['branch'] = is_string($value) ? self::normalizeBranch($value) : null;
    }

    public function getBranchLabelAttribute(): ?string
    {
        return self::branchLabel($this->attributes['branch'] ?? null);
    }

    /**
     * Normalize any raw branch slug or display label into the canonical CRM
     * branch code ('old_airport' or 'wakrah'), or null when unrecognized/empty.
     */
    public static function normalizeBranch(?string $raw): ?string
    {
        if ($raw === null) {
            return null;
        }

        $slug = strtolower(trim((string) preg_replace('/[\s\-]+/', '_', $raw)));
        if ($slug === '' || $slug === 'all' || $slug === 'all_branches') {
            return null;
        }

        return match ($slug) {
            'old_airport', 'airport' => self::BRANCH_OLD_AIRPORT,
            'wakrah', 'al_wakrah', 'alwakrah' => self::BRANCH_WAKRAH,
            default => null,
        };
    }

    /**
     * Normalize a UI filter query parameter ('old_airport', 'wakrah', 'unassigned', or null for all).
     * Unlike normalizeBranch(), this permits 'unassigned' for filtering without ever persisting it on models.
     */
    public static function normalizeBranchFilter(?string $raw): ?string
    {
        if ($raw === null) {
            return null;
        }

        $slug = strtolower(trim((string) preg_replace('/[\s\-]+/', '_', $raw)));
        if ($slug === 'unassigned' || $slug === 'no_branch' || $slug === 'none') {
            return self::BRANCH_UNASSIGNED;
        }

        return self::normalizeBranch($raw);
    }

    public static function branchLabel(?string $raw): ?string
    {
        $canonical = self::normalizeBranch($raw);

        return $canonical ? (self::BRANCHES[$canonical] ?? null) : null;
    }

    /**
     * All stored/legacy string variants that represent a canonical branch in SQL WHERE IN clauses.
     *
     * @return array<int, string>
     */
    public static function branchMatchValues(?string $branch): array
    {
        $canonical = self::normalizeBranch($branch);

        return match ($canonical) {
            self::BRANCH_OLD_AIRPORT => ['old_airport', 'Old Airport', 'old airport', 'old-airport'],
            self::BRANCH_WAKRAH => ['wakrah', 'al_wakrah', 'Al Wakrah', 'Wakrah', 'al wakrah', 'al-wakrah'],
            default => [],
        };
    }

    /**
     * Extract a canonical branch from free-text lead remarks when present.
     */
    public static function extractBranchFromText(?string $text): ?string
    {
        $haystack = strtolower(trim((string) $text));
        if ($haystack === '') {
            return null;
        }

        if (str_contains($haystack, 'old airport') || str_contains($haystack, 'old_airport')) {
            return self::BRANCH_OLD_AIRPORT;
        }

        if (str_contains($haystack, 'wakrah')) {
            return self::BRANCH_WAKRAH;
        }

        return null;
    }

    /**
     * Scope clients who have neither a primary `customers.branch` nor any
     * non-cancelled booking history at Old Airport or Al Wakrah.
     */
    public function scopeUnassignedBranch($query)
    {
        $allKnownVariants = array_values(array_unique(array_merge(
            self::branchMatchValues(self::BRANCH_OLD_AIRPORT),
            self::branchMatchValues(self::BRANCH_WAKRAH)
        )));

        $hasBranchCol = \Illuminate\Support\Facades\Schema::hasColumn('customers', 'branch');

        if ($hasBranchCol) {
            $query->where(function ($q) use ($allKnownVariants) {
                $q->whereNull('customers.branch')
                    ->orWhere('customers.branch', '')
                    ->orWhereNotIn('customers.branch', $allKnownVariants);
            });
        }

        return $query->whereDoesntHave('appointments', function ($aq) use ($allKnownVariants) {
            $aq->whereIn('appointments.branch', $allKnownVariants)
                ->where(function ($sq) {
                    $sq->whereNull('appointments.status')
                        ->orWhere('appointments.status', '!=', 'cancelled');
                });
        });
    }

    /**
     * Scope clients to a branch: matches primary `customers.branch` OR any
     * non-cancelled booking history (`appointments.branch`) at that branch so
     * multi-branch guests remain visible to both branch teams. Also supports
     * filtering to 'unassigned' clients.
     */
    public function scopeForBranch($query, ?string $branch)
    {
        if (self::normalizeBranchFilter($branch) === self::BRANCH_UNASSIGNED) {
            return $this->scopeUnassignedBranch($query);
        }

        $variants = self::branchMatchValues($branch);
        if (empty($variants)) {
            return $query;
        }

        $hasBranchCol = \Illuminate\Support\Facades\Schema::hasColumn('customers', 'branch');

        return $query->where(function ($q) use ($variants, $hasBranchCol) {
            if ($hasBranchCol) {
                $q->whereIn('customers.branch', $variants);
                $q->orWhereHas('appointments', function ($aq) use ($variants) {
                    $aq->whereIn('appointments.branch', $variants)
                        ->where(function ($sq) {
                            $sq->whereNull('appointments.status')
                                ->orWhere('appointments.status', '!=', 'cancelled');
                        });
                });
            } else {
                $q->whereHas('appointments', function ($aq) use ($variants) {
                    $aq->whereIn('appointments.branch', $variants)
                        ->where(function ($sq) {
                            $sq->whereNull('appointments.status')
                                ->orWhere('appointments.status', '!=', 'cancelled');
                        });
                });
            }
        });
    }

    /**
     * Automatically set `customer->branch` when currently empty from a new/updated
     * booking, lead, ad lead entry, or walk-in sale.
     */
    public static function syncBranchFromActivity(?int $customerId, ?string $phone, ?string $rawBranch): void
    {
        $canonicalBranch = self::normalizeBranch($rawBranch);
        if (!$canonicalBranch || !\Illuminate\Support\Facades\Schema::hasTable('customers') || !\Illuminate\Support\Facades\Schema::hasColumn('customers', 'branch')) {
            return;
        }

        $customer = $customerId ? self::find($customerId) : null;

        if (!$customer && $phone !== null && trim($phone) !== '') {
            $normPhone = self::normalizePhone($phone);
            $digits = preg_replace('/\D+/', '', $phone);
            $candidates = array_values(array_unique(array_filter([$normPhone, $digits, trim($phone)])));
            if (!empty($candidates)) {
                $customer = self::whereIn('phone', $candidates)->first();
            }
        }

        if ($customer && self::normalizeBranch($customer->getRawOriginal('branch') ?? $customer->branch) === null) {
            $customer->branch = $canonicalBranch;
            $customer->saveQuietly();
        }
    }

    /**
     * Idempotently backfill `customers.branch` for existing records where branch is null/empty:
     * 1. Most recent appointment branch (non-cancelled preferred, matched by customer_id or normalized phone)
     * 2. Most recent paid sale branch
     * 3. Matching AdLeadEntry branch (matched by customer_id or normalized phone)
     * 4. Matching Lead branch / linked appointment branch / remarks (matched by customer_id or normalized phone)
     */
    public static function backfillBranches(): int
    {
        if (!\Illuminate\Support\Facades\Schema::hasTable('customers') || !\Illuminate\Support\Facades\Schema::hasColumn('customers', 'branch')) {
            return 0;
        }

        $missing = self::query()
            ->where(function ($q) {
                $q->whereNull('branch')->orWhere('branch', '');
            })
            ->get(['id', 'phone', 'branch']);

        if ($missing->isEmpty()) {
            return 0;
        }

        $hasAppointments = \Illuminate\Support\Facades\Schema::hasTable('appointments');
        $hasSales = \Illuminate\Support\Facades\Schema::hasTable('sales');
        $hasAdLeads = \Illuminate\Support\Facades\Schema::hasTable('ad_lead_entries');
        $hasLeads = \Illuminate\Support\Facades\Schema::hasTable('leads');
        $leadsHasBranchCol = $hasLeads && \Illuminate\Support\Facades\Schema::hasColumn('leads', 'branch');

        $apptsByCustomer = collect();
        $apptsByPhone = collect();
        if ($hasAppointments) {
            $allAppts = Appointment::query()
                ->whereNotNull('branch')
                ->where('branch', '!=', '')
                ->orderByRaw("CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END ASC")
                ->orderByDesc('appointment_datetime')
                ->orderByDesc('id')
                ->get(['id', 'customer_id', 'phone', 'branch', 'status']);

            foreach ($allAppts as $appt) {
                $normBranch = self::normalizeBranch($appt->branch);
                if (!$normBranch) {
                    continue;
                }
                if ($appt->customer_id && !$apptsByCustomer->has((int) $appt->customer_id)) {
                    $apptsByCustomer->put((int) $appt->customer_id, $normBranch);
                }
                $normPhone = self::normalizePhone($appt->phone);
                if ($normPhone !== '' && !$apptsByPhone->has($normPhone)) {
                    $apptsByPhone->put($normPhone, $normBranch);
                }
            }
        }

        $salesByCustomer = collect();
        if ($hasSales) {
            $allSales = Sale::query()
                ->whereNotNull('customer_id')
                ->whereNotNull('branch')
                ->where('branch', '!=', '')
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->get(['id', 'customer_id', 'branch']);

            foreach ($allSales as $sale) {
                $normBranch = self::normalizeBranch($sale->branch);
                if ($normBranch && !$salesByCustomer->has((int) $sale->customer_id)) {
                    $salesByCustomer->put((int) $sale->customer_id, $normBranch);
                }
            }
        }

        $adLeadsByCustomer = collect();
        $adLeadsByPhone = collect();
        if ($hasAdLeads) {
            $allAdLeads = AdLeadEntry::query()
                ->whereNotNull('branch')
                ->where('branch', '!=', '')
                ->orderByDesc('date')
                ->orderByDesc('id')
                ->get(['id', 'customer_id', 'phone', 'branch']);

            foreach ($allAdLeads as $entry) {
                $normBranch = self::normalizeBranch($entry->branch);
                if (!$normBranch) {
                    continue;
                }
                if ($entry->customer_id && !$adLeadsByCustomer->has((int) $entry->customer_id)) {
                    $adLeadsByCustomer->put((int) $entry->customer_id, $normBranch);
                }
                $normPhone = self::normalizePhone($entry->phone);
                if ($normPhone !== '' && !$adLeadsByPhone->has($normPhone)) {
                    $adLeadsByPhone->put($normPhone, $normBranch);
                }
            }
        }

        $leadsByCustomer = collect();
        $leadsByPhone = collect();
        if ($hasLeads) {
            $leadCols = ['id', 'customer_id', 'phone', 'appointment_id', 'customer_remarks'];
            if ($leadsHasBranchCol) {
                $leadCols[] = 'branch';
            }
            $allLeads = Lead::with('appointment:id,branch')
                ->orderByDesc('id')
                ->get($leadCols);

            foreach ($allLeads as $lead) {
                $resolved = ($leadsHasBranchCol ? self::normalizeBranch($lead->branch ?? null) : null)
                    ?: self::normalizeBranch($lead->appointment?->branch)
                    ?: self::extractBranchFromText($lead->customer_remarks);

                if (!$resolved) {
                    continue;
                }

                if ($lead->customer_id && !$leadsByCustomer->has((int) $lead->customer_id)) {
                    $leadsByCustomer->put((int) $lead->customer_id, $resolved);
                }
                $normPhone = self::normalizePhone($lead->phone);
                if ($normPhone !== '' && !$leadsByPhone->has($normPhone)) {
                    $leadsByPhone->put($normPhone, $resolved);
                }
            }
        }

        $updated = 0;
        foreach ($missing as $customer) {
            $cid = (int) $customer->id;
            $normPhone = self::normalizePhone($customer->phone);

            $resolvedBranch = $apptsByCustomer->get($cid)
                ?: ($normPhone !== '' ? $apptsByPhone->get($normPhone) : null)
                ?: $salesByCustomer->get($cid)
                ?: $adLeadsByCustomer->get($cid)
                ?: ($normPhone !== '' ? $adLeadsByPhone->get($normPhone) : null)
                ?: $leadsByCustomer->get($cid)
                ?: ($normPhone !== '' ? $leadsByPhone->get($normPhone) : null);

            if ($resolvedBranch) {
                \Illuminate\Support\Facades\DB::table('customers')
                    ->where('id', $cid)
                    ->update(['branch' => $resolvedBranch]);
                $updated++;
            }
        }

        return $updated;
    }

    /**
     * Scope to clients who have NOT been contacted for retention within the last $days days.
     */
    public function scopeNotRecentlyContacted($query, int $days = self::RETENTION_COOLDOWN_DAYS)
    {
        return $query->where(function ($q) use ($days) {
            $q->whereNull('last_retention_contacted_at')
                ->orWhere('last_retention_contacted_at', '<', now()->subDays($days));
        });
    }

    /**
     * Check whether this client is currently within the 14-day outreach cooldown window.
     */
    public function isOnRetentionCooldown(int $days = self::RETENTION_COOLDOWN_DAYS): bool
    {
        return $this->last_retention_contacted_at !== null
            && $this->last_retention_contacted_at->gte(now()->subDays($days));
    }

    public function appointments()
    {
        return $this->hasMany(Appointment::class, 'customer_id');
    }

    public function sales()
    {
        return $this->hasMany(Sale::class, 'customer_id');
    }

    public function loyaltyTransactions()
    {
        return $this->hasMany(LoyaltyTransaction::class)->latest();
    }

    public function clientPackages()
    {
        return $this->hasMany(ClientPackage::class)
            ->orderByRaw('COALESCE(purchased_at, created_at) DESC')
            ->orderByDesc('id');
    }

    /**
     * Normalize any phone input into the standard digits-only format via
     * Lead::splitPhone() and Lead::normalizePhone() so local 8-digit Qatar
     * numbers ('55123456') and international variants ('+974 5512 3456',
     * '97455123456') resolve to the exact same canonical phone string.
     */
    public static function normalizePhone(?string $phone): string
    {
        $raw = preg_replace('/\D+/', '', (string) $phone);
        if ($raw === '') {
            return '';
        }

        if (preg_match('/^009740*(\d{8})$/', $raw, $m)) {
            return $m[1];
        }
        if (preg_match('/^9740*(\d{8})$/', $raw, $m)) {
            return $m[1];
        }

        [$countryCode, $localNumber] = Lead::splitPhone($raw);

        return Lead::normalizePhone($countryCode, $localNumber);
    }

    /**
     * Packages that can still have services redeemed from them right now -
     * excludes anything already expired, even if a scheduled job hasn't
     * flipped its status column yet (real time is the source of truth).
     * Unpaid booking packages (sale_id is null) are only active for their
     * originating appointment ($forAppointmentId) and never across other
     * separate appointments until paid at checkout.
     */
    public function activeClientPackages(?int $forAppointmentId = null)
    {
        return $this->clientPackages()
            ->whereNotIn('status', ['expired', 'cancelled'])
            ->where(function ($q) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>=', now());
            })
            ->where(function ($q) use ($forAppointmentId) {
                $q->whereNotNull('sale_id')
                    ->orWhereNull('appointment_id');
                if ($forAppointmentId !== null) {
                    $q->orWhere('appointment_id', $forAppointmentId);
                }
            });
    }

    /**
     * Total still-unused slots across every active package this customer
     * owns - the number shown on the profile badge. Not tied to any
     * specific service, since which pool service fills each slot is only
     * decided at redemption time.
     */
    public function getPendingPackageServiceCountAttribute(): int
    {
        return (int) $this->activeClientPackages()->get()->sum('remaining_count');
    }

    /**
     * This customer's still-redeemable choices right now, formatted for
     * both the checkout page and the booking drawer's "Redeem Package
     * Service" pickers. Each active package with an unused slot offers
     * every pool service it hasn't already redeemed - excluding whatever's
     * already been used so the same service can never be picked twice out
     * of one package's lifecycle. Reconciles against real time first, so a
     * package whose validity window has quietly lapsed never shows up here
     * even if a scheduled sweep hasn't caught it yet.
     */
    public function redeemablePackageServices(?int $forAppointmentId = null)
    {
        ClientPackage::expireDue($this->id);

        return $this->activeClientPackages($forAppointmentId)
            ->with(['combo.services', 'redeemedServices'])
            ->get()
            ->filter(fn($pkg) => $pkg->canRedeem($forAppointmentId) && $pkg->combo)
            ->flatMap(function ($pkg) {
                $usedServiceIds = $pkg->redeemedServices->pluck('service_id')->all();

                return $pkg->combo->services
                    ->whereNotIn('id', $usedServiceIds)
                    ->map(fn($service) => [
                        'id' => "{$pkg->id}:{$service->id}",
                        'client_package_id' => $pkg->id,
                        'service_id' => $service->id,
                        'service_name' => $service->name,
                        'duration' => $service->duration,
                        'combo_name' => $pkg->combo_name,
                        'remaining_count' => $pkg->remaining_count,
                        'quantity_included' => $pkg->effective_quantity_included,
                        'expires_at' => $pkg->expires_at ? $pkg->expires_at->format('d M Y') : null,
                        'days_left' => $pkg->expires_at
                            ? (int) floor(now()->diffInDays($pkg->expires_at, false))
                            : $pkg->effective_validity_days,
                    ]);
            })
            ->values();
    }

    /**
     * Award points for a completed sale and log the transaction.
     */
    public function earnPointsForSale(Sale $sale): int
    {
        $points = (int) floor($sale->total_amount * self::POINTS_PER_QAR);
        if ($points <= 0) {
            return 0;
        }

        $this->loyaltyTransactions()->create([
            'sale_id' => $sale->id,
            'type' => 'earn',
            'points' => $points,
            'description' => 'Earned from checkout #' . $sale->id,
        ]);

        $this->increment('loyalty_points', $points);

        return $points;
    }

    /**
     * Redeem points for a reward. Throws if the balance is insufficient.
     */
    public function redeemPoints(int $points, string $description, ?int $userId = null): void
    {
        if ($points <= 0 || $points > $this->loyalty_points) {
            throw new \InvalidArgumentException('Not enough loyalty points to redeem.');
        }

        $this->loyaltyTransactions()->create([
            'type' => 'redeem',
            'points' => -$points,
            'description' => $description,
            'created_by' => $userId,
        ]);

        $this->decrement('loyalty_points', $points);
    }
}
