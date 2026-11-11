<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

class Lead extends Model
{
    const CATEGORIES = [
        'inquiry' => 'Inquiry',
        'follow_up' => 'Follow up',
        'no_show' => 'No Show',
        'cancel' => 'Cancelled',
        'combos_packages' => 'Combos / Packages',
    ];

    /**
     * Strict urgency priority order for determining the primary `category`
     * column when a lead holds multiple simultaneous categories.
     */
    const CATEGORY_PRIORITY = [
        'no_show',
        'cancel',
        'follow_up',
        'combos_packages',
        'inquiry',
    ];

    /**
     * Categories staff can pick when adding/editing a lead.
     */
    const MANUAL_CATEGORIES = [
        'inquiry' => 'Inquiry',
        'follow_up' => 'Follow up',
        'no_show' => 'No Show',
        'cancel' => 'Cancelled',
        'combos_packages' => 'Combos / Packages',
    ];

    const NEEDFUL_STATUSES = [
        'booked' => '✅ Booked Appointment',
        'yes' => '💬 Contacted / In Progress',
        'no' => '❌ Not Interested / Lost',
    ];

    /**
     * Statuses agents can manually select in the Needful Done dropdown.
     * 'booked' is reserved for automatic Calendar booking verification.
     */
    const MANUAL_NEEDFUL_STATUSES = [
        'yes' => '💬 Contacted / In Progress',
        'no' => '❌ Not Interested / Lost',
    ];

    const CLOSED_STATUSES = [
        'booked',
        'no',
    ];

    const COUNTRY_CODES = [
        '974' => 'Qatar',
        '1' => 'USA / Canada',
        '91' => 'India',
        '971' => 'UAE',
        '966' => 'Saudi Arabia',
        '968' => 'Oman',
        '973' => 'Bahrain',
        '965' => 'Kuwait',
        '20' => 'Egypt',
        '63' => 'Philippines',
        '94' => 'Sri Lanka',
        '92' => 'Pakistan',
        '44' => 'UK',
    ];

    protected $fillable = [
        'phone',
        'customer_id',
        'appointment_id',
        'assigned_agent_id',
        'category',
        'source',
        'categories',
        'customer_remarks',
        'service_interest',
        'needful_done',
        'next_followup_date',
    ];

    public const PAID_AD_SOURCES = [
        'instagram',
        'tiktok',
        'snapchat',
        'meta ads',
        'meta',
        'paid ads',
        'paid ad',
        'facebook',
        'facebook ads',
        'whatsapp ads',
    ];

    public function isPaidAdLead(): bool
    {
        $source = strtolower(trim((string) ($this->source ?? '')));
        if ($source !== '') {
            foreach (self::PAID_AD_SOURCES as $adKeyword) {
                if ($source === $adKeyword || str_contains($source, $adKeyword)) {
                    return true;
                }
            }
        }

        $remarks = strtolower(trim((string) ($this->customer_remarks ?? '')));
        if ($remarks !== '') {
            foreach (['instagram ad', 'tiktok ad', 'snapchat ad', 'meta ad', 'paid ad', 'facebook ad', 'whatsapp ad', 'ig ad', 'ig promo', 'reel ad', '[paid ad]'] as $hint) {
                if (str_contains($remarks, $hint)) {
                    return true;
                }
            }
        }

        return false;
    }

    protected $casts = [
        'next_followup_date' => 'date',
        'service_interest' => 'array',
        'categories' => 'array',
    ];

    protected static function booted(): void
    {
        static::saving(function (Lead $lead) {
            $rawCategories = is_array($lead->categories) ? $lead->categories : [];

            if ($lead->isDirty('categories') && !empty($rawCategories)) {
                $normalized = self::normalizeCategories($rawCategories);
                $lead->categories = $normalized;
                $lead->category = self::resolvePrimaryCategory($normalized);
            } elseif ($lead->category || !empty($rawCategories)) {
                $combined = array_merge($rawCategories, $lead->category ? [$lead->category] : []);
                $normalized = self::normalizeCategories($combined);
                $lead->categories = !empty($normalized) ? $normalized : null;
                $lead->category = self::resolvePrimaryCategory($normalized) ?? $lead->category;
            }
        });

        static::deleting(function (Lead $lead) {
            if (Schema::hasTable('ad_lead_entries') && Schema::hasColumn('ad_lead_entries', 'lead_id')) {
                AdLeadEntry::where('lead_id', $lead->id)->update(['lead_id' => null]);
            }
        });
    }

    /**
     * Normalize, deduplicate, and sort category keys by strict urgency priority:
     * no_show > cancel > follow_up > inquiry.
     */
    public static function normalizeCategories(array|string|null $categories): array
    {
        $items = is_array($categories) ? $categories : ($categories ? [$categories] : []);
        $cleaned = array_values(array_unique(array_filter(array_map(
            function ($c) {
                if (!is_string($c)) {
                    return '';
                }
                $trimmed = trim($c);
                $lower = strtolower($trimmed);
                if ($lower === 'followup') {
                    return 'follow_up';
                }
                if (in_array($lower, ['combos / packages', 'combos_packages', 'combos', 'packages', 'combo'], true)) {
                    return 'combos_packages';
                }
                return $trimmed;
            },
            $items
        ))));

        usort($cleaned, function (string $a, string $b) {
            $posA = array_search($a, self::CATEGORY_PRIORITY, true);
            $posB = array_search($b, self::CATEGORY_PRIORITY, true);
            $posA = $posA === false ? 999 : $posA;
            $posB = $posB === false ? 999 : $posB;

            return $posA <=> $posB;
        });

        return $cleaned;
    }

    /**
     * Return the highest-urgency category from a list of categories.
     */
    public static function resolvePrimaryCategory(array $categories): ?string
    {
        foreach (self::CATEGORY_PRIORITY as $priorityCat) {
            if (in_array($priorityCat, $categories, true)) {
                return $priorityCat;
            }
        }

        return $categories[0] ?? null;
    }

    /**
     * Merge new category status(es) into this lead's `categories` array and
     * update the primary `category` column to the highest-priority status.
     */
    public function mergeCategories(array|string|null $newCategories): array
    {
        $existing = is_array($this->categories) && !empty($this->categories)
            ? $this->categories
            : ($this->category ? [$this->category] : []);
        $incoming = is_array($newCategories) ? $newCategories : ($newCategories ? [$newCategories] : []);

        $merged = self::normalizeCategories(array_merge($existing, $incoming));
        $this->categories = !empty($merged) ? $merged : null;
        $this->category = self::resolvePrimaryCategory($merged);

        return $merged;
    }

    public function getCategoriesListAttribute(): array
    {
        return self::normalizeCategories(
            (is_array($this->categories) && !empty($this->categories))
                ? $this->categories
                : ($this->category ? [$this->category] : [])
        );
    }

    public function hasCategory(string|array $category): bool
    {
        $targets = (array) $category;
        $current = $this->categories_list;

        foreach ($targets as $target) {
            $normalizedTarget = strtolower(trim((string) $target)) === 'followup' ? 'follow_up' : $target;
            if (in_array($normalizedTarget, $current, true) || in_array($target, $current, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Find the single active (open) Lead row for a given phone number (or customer ID).
     */
    public static function findOpenLeadByPhone(?string $phone, ?int $customerId = null, ?int $excludeLeadId = null): ?self
    {
        $rawDigits = preg_replace('/\D+/', '', (string) $phone);
        [$countryCode, $localNumber] = self::splitPhone($rawDigits);
        $normalizedPhone = self::normalizePhone($countryCode, $localNumber);
        $candidates = array_values(array_unique(array_filter([$normalizedPhone, $rawDigits])));

        if (empty($candidates) && !$customerId) {
            return null;
        }

        return static::query()
            ->open()
            ->where(function ($q) use ($candidates, $customerId) {
                if (!empty($candidates)) {
                    $q->whereIn('phone', $candidates);
                    foreach ($candidates as $cand) {
                        $q->orWhereRaw(
                            "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(phone,' ',''),'-',''),'(',''),')',''),'+','') = ?",
                            [$cand]
                        );
                    }
                }
                if ($customerId) {
                    $q->orWhere('customer_id', $customerId);
                }
            })
            ->when($excludeLeadId, fn ($q) => $q->where('id', '!=', $excludeLeadId))
            ->orderByDesc('id')
            ->first();
    }

    public function scopeWhereHasCategory($query, string|array $categories)
    {
        $cats = array_values(array_filter((array) $categories));

        return $query->where(function ($q) use ($cats) {
            $q->whereIn('category', $cats);
            foreach ($cats as $cat) {
                $q->orWhereJsonContains('categories', $cat);
            }
        });
    }

    public function scopeOpen($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('needful_done')
                ->orWhereNotIn('needful_done', self::CLOSED_STATUSES);
        });
    }

    public function agent()
    {
        return $this->belongsTo(User::class, 'assigned_agent_id');
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function getCustomerNameAttribute($value): ?string
    {
        if (is_string($value) && trim($value) !== '') {
            return $value;
        }

        if ($this->customer_id) {
            return $this->customer?->name;
        }

        if ($this->phone) {
            return Customer::where('phone', $this->phone)->value('name');
        }

        return null;
    }

    public function getNameAttribute($value): ?string
    {
        if (is_string($value) && trim($value) !== '') {
            return $value;
        }

        return $this->customer_name;
    }

    /**
     * The specific appointment that auto-generated this lead (No Show/Cancel
     * only - manually created leads never have this set). Null for those.
     */
    public function appointment()
    {
        return $this->belongsTo(Appointment::class);
    }

    /**
     * Determine whether this Lead counts as a valid "Booked" conversion for KPIs/Analytics.
     * Excludes leads whose linked appointment was cancelled or marked no_show.
     */
    public function isBookedConversion(): bool
    {
        if ($this->appointment_id !== null) {
            $appt = $this->relationLoaded('appointment')
                ? $this->appointment
                : $this->appointment()->select(['id', 'status'])->first();

            if ($appt) {
                return !in_array(strtolower((string) ($appt->status ?? 'pending')), ['cancelled', 'no_show'], true);
            }
        }

        return $this->needful_done === 'booked';
    }

    /**
     * Determine whether this Lead is backed by a real active/completed calendar Appointment
     * or a verified paid Sale record (used to guard Needful Done lock vs phantom-booked leads).
     */
    public function hasVerifiedConversionActivity(): bool
    {
        if ($this->appointment_id !== null && Schema::hasTable('appointments')) {
            $hasActiveLinked = Appointment::where('id', $this->appointment_id)
                ->whereNotIn('status', ['cancelled', 'no_show'])
                ->exists();

            if ($hasActiveLinked) {
                return true;
            }
        }

        $phoneCandidates = array_values(array_unique(array_filter([
            $this->phone,
            Customer::normalizePhone((string) $this->phone),
        ])));

        $customerIds = array_values(array_unique(array_filter([
            $this->customer_id,
        ])));

        if (!empty($phoneCandidates) && Schema::hasTable('customers')) {
            $matchedCustomerIds = Customer::whereIn('phone', $phoneCandidates)->pluck('id')->all();
            $customerIds = array_values(array_unique(array_merge($customerIds, $matchedCustomerIds)));
        }

        if (empty($customerIds) && empty($phoneCandidates)) {
            return false;
        }

        $minDate = $this->created_at ? Carbon::parse($this->created_at)->startOfDay() : today()->startOfDay();
        $minDateStr = $minDate->toDateString();
        $todayStr = today()->toDateString();

        if (Schema::hasTable('appointments')) {
            $activeAppointments = Appointment::query()
                ->whereNotIn('status', ['cancelled', 'no_show'])
                ->where(function ($q) use ($customerIds, $phoneCandidates) {
                    if (!empty($customerIds)) {
                        $q->orWhereIn('customer_id', $customerIds);
                    }
                    if (!empty($phoneCandidates)) {
                        $q->orWhereIn('phone', $phoneCandidates);
                    }
                })
                ->get();

            $hasRelevantActiveAppt = $activeAppointments->contains(function (Appointment $a) use ($minDateStr, $todayStr) {
                $apptDateStr = $a->appointment_date->toDateString();

                return $apptDateStr >= $minDateStr || $apptDateStr >= $todayStr;
            });

            if ($hasRelevantActiveAppt) {
                return true;
            }
        }

        if (Schema::hasTable('sales') && !empty($customerIds)) {
            $sales = Sale::with('appointment')
                ->whereIn('customer_id', $customerIds)
                ->get();

            foreach ($sales as $sale) {
                if ($sale->appointment && in_array(strtolower((string) $sale->appointment->status), ['cancelled', 'no_show'], true)) {
                    continue;
                }
                if ($minDate && AdLeadEntry::resolveSaleDate($sale)->lt($minDate)) {
                    continue;
                }
                if (AdLeadEntry::netSaleAmount($sale) > 0) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Combine a country code + local number into the digits-only phone format
     * used everywhere else in the app (Customers, Appointments, Calendar all
     * store bare local numbers with no country code - e.g. "55532347", not
     * "97455532347"). Qatar is the default/legacy convention, so it's left
     * unprefixed to match every existing client record; only a non-Qatar code
     * is actually prepended, since there's no existing data to conflict with
     * there.
     */
    public static function normalizePhone(?string $countryCode, ?string $number): string
    {
        $cleanCountryCode = preg_replace('/\D+/', '', (string) $countryCode);
        $cleanCountryCode = preg_replace('/^00+/', '', $cleanCountryCode);

        $digits = preg_replace('/\D+/', '', (string) $number);
        if (preg_match('/^009740*(\d{8})$/', $digits, $m)) {
            $digits = $m[1];
        } elseif (preg_match('/^9740*(\d{8})$/', $digits, $m)) {
            $digits = $m[1];
        } elseif (str_starts_with($digits, '00')) {
            $digits = preg_replace('/^00+/', '', $digits);
        }

        if ($cleanCountryCode === '974') {
            if (str_starts_with($digits, '974') && strlen($digits) > 8) {
                $digits = substr($digits, 3);
            }
            $stripped = ltrim($digits, '0');
            if ($stripped !== '') {
                $digits = $stripped;
            }
        } elseif ($cleanCountryCode !== '') {
            if (str_starts_with($digits, $cleanCountryCode) && strlen($digits) >= strlen($cleanCountryCode) + 7) {
                $localPart = ltrim(substr($digits, strlen($cleanCountryCode)), '0');
                $digits = $cleanCountryCode . $localPart;
            } else {
                $localPart = ltrim($digits, '0');
                $digits = $cleanCountryCode . ($localPart !== '' ? $localPart : $digits);
            }
        } elseif (preg_match('/^0+(\d{8})$/', $digits, $m)) {
            $digits = $m[1];
        }

        return $digits;
    }

    /**
     * Split a stored digits-only phone number into [country_code, local_number]
     * for pre-filling the country code select + number input on edit. Falls
     * back to Qatar with the full number if no known code matches.
     */
    public static function splitPhone(?string $phone): array
    {
        $digits = preg_replace('/\D+/', '', (string) $phone);
        if (str_starts_with($digits, '00')) {
            $digits = preg_replace('/^00+/', '', $digits);
        }

        if (preg_match('/^9740*(\d{8})$/', $digits, $m)) {
            return ['974', $m[1]];
        }

        // Bare 8-digit numbers in Qatar CRM (including Doha landlines 44xxxxxx) are local Qatar numbers
        if (strlen($digits) <= 9) {
            $local = preg_match('/^0+(\d{8})$/', $digits, $m) ? $m[1] : $digits;

            return ['974', $local];
        }

        $codes = array_keys(self::COUNTRY_CODES);
        usort($codes, fn($a, $b) => strlen($b) - strlen($a));

        foreach ($codes as $code) {
            $codeStr = (string) $code;
            if (!str_starts_with($digits, $codeStr)) {
                continue;
            }

            $rest = substr($digits, strlen($codeStr));
            $restStripped = ltrim($rest, '0');

            if ($codeStr === '1') {
                // NANP (+1 USA/Canada) requires exactly 10 digits after country code 1
                if (strlen($rest) === 10) {
                    return ['1', $rest];
                }
                continue;
            }

            if (strlen($restStripped) >= 7) {
                return [$codeStr, $restStripped];
            }
        }

        return ['974', $digits];
    }
}
