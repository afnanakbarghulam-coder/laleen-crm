<?php

namespace App\Console\Commands;

use App\Models\AdLeadEntry;
use App\Models\Appointment;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\Sale;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

class SyncAdLeadsAttributionCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'kpi:sync-ad-leads {--month= : Optional YYYY-MM month filter (defaults to all months)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Repair canonical Customer profile links across ad_lead_entries, appointments, and sales, and re-sync same-month customer revenue onto ad_lead_entries.';

    public function handle(): int
    {
        if (!Schema::hasTable('ad_lead_entries') || !Schema::hasTable('customers')) {
            $this->error('Required tables (ad_lead_entries, customers) are not present.');
            return self::FAILURE;
        }

        $rawMonth = trim((string) ($this->option('month') ?? ''));
        $monthStart = null;
        $monthEnd = null;

        if ($rawMonth !== '') {
            if (!preg_match('/^\d{4}-\d{2}$/', $rawMonth)) {
                $this->error("Invalid --month format [{$rawMonth}]. Expected YYYY-MM (e.g., 2026-11).");
                return self::FAILURE;
            }
            $monthStart = Carbon::createFromFormat('Y-m-d', $rawMonth . '-01')->startOfMonth()->startOfDay();
            $monthEnd = $monthStart->copy()->endOfMonth()->endOfDay();
        }

        $repairedAdLeads = 0;
        $repairedAppointments = 0;
        $repairedSales = 0;

        // 1. Build canonical Customer map by normalized phone using chunkById(500) to avoid hydrating 11k+ models in RAM.
        //    Prefer canonical E.164 phones (+974XXXXXXXX / +<cc><number>) over legacy 8-digit strings.
        /** @var array<string, array{id: int, phone: string, name: string, is_e164: bool}> $canonicalByPhone */
        $canonicalByPhone = [];
        $duplicateToCanonicalId = [];
        $seenE164Phones = [];

        Customer::query()
            ->select(['id', 'phone'])
            ->orderBy('id')
            ->chunkById(500, function ($customers) use (&$seenE164Phones) {
                foreach ($customers as $cust) {
                    $p = trim((string) $cust->phone);
                    if ($p !== '' && Customer::isCanonicalE164($p)) {
                        $seenE164Phones[$p] = (int) $cust->id;
                    }
                }
            });

        Customer::query()
            ->select(['id', 'phone', 'name'])
            ->orderBy('id')
            ->chunkById(500, function ($customers) use (&$canonicalByPhone, &$duplicateToCanonicalId, &$seenE164Phones) {
                foreach ($customers as $cust) {
                    $norm = Customer::normalizePhone($cust->phone);
                    if ($norm === '') {
                        continue;
                    }

                    $e164 = Customer::normalizePhoneE164($cust->phone);
                    $storedPhone = (string) $cust->phone;
                    $isE164 = Customer::isCanonicalE164($storedPhone);

                    if (!isset($canonicalByPhone[$norm])) {
                        if (
                            $e164 !== ''
                            && !$isE164
                            && !isset($seenE164Phones[$e164])
                        ) {
                            Customer::where('id', $cust->id)->update(['phone' => $e164]);
                            $storedPhone = $e164;
                            $isE164 = true;
                            $seenE164Phones[$e164] = (int) $cust->id;
                        }

                        $canonicalByPhone[$norm] = [
                            'id' => (int) $cust->id,
                            'phone' => $storedPhone,
                            'name' => (string) ($cust->name ?? ''),
                            'is_e164' => $isE164,
                        ];
                    } else {
                        $currentCanonical = $canonicalByPhone[$norm];
                        $preferNew = ($e164 !== '' && $storedPhone === $e164 && $currentCanonical['phone'] !== $e164)
                            || ($isE164 && !$currentCanonical['is_e164']);

                        if ($preferNew) {
                            $duplicateToCanonicalId[$currentCanonical['id']] = (int) $cust->id;
                            $canonicalByPhone[$norm] = [
                                'id' => (int) $cust->id,
                                'phone' => $storedPhone,
                                'name' => (string) ($cust->name ?? ''),
                                'is_e164' => $isE164,
                            ];
                        } else {
                            $duplicateToCanonicalId[(int) $cust->id] = $currentCanonical['id'];
                        }
                    }
                }
            });

        // 2. Repair ad_lead_entries customer_id & normalized phone
        //    When --month is specified, include prior-month leads within the 14-day spillover window.
        $leadWindowStart = $monthStart ? $monthStart->copy()->subDays(AdLeadEntry::SPILLOVER_DAYS)->startOfDay() : null;
        $apptWindowEnd = $monthEnd ? $monthEnd->copy()->addDays(AdLeadEntry::SPILLOVER_DAYS)->endOfDay() : null;

        $entriesQuery = AdLeadEntry::query()->orderBy('date')->orderBy('id');
        if ($leadWindowStart && $monthEnd) {
            $entriesQuery->whereBetween('date', [$leadWindowStart, $monthEnd]);
        }
        $entries = $entriesQuery->get();

        foreach ($entries as $entry) {
            $normPhone = Customer::normalizePhone($entry->phone ?: $entry->customer?->phone);
            $canonicalCustomerId = null;

            if ($normPhone !== '' && isset($canonicalByPhone[$normPhone])) {
                $canonicalCustomerId = $canonicalByPhone[$normPhone]['id'];
                $existingNameLower = strtolower(trim($canonicalByPhone[$normPhone]['name']));
                $entryName = trim((string) $entry->customer_name);
                if (
                    $entryName !== ''
                    && strtolower($entryName) !== 'ad lead client'
                    && ($existingNameLower === '' || $existingNameLower === 'walk-in' || $existingNameLower === 'ad lead client')
                ) {
                    Customer::where('id', $canonicalCustomerId)->update(['name' => $entryName]);
                    $canonicalByPhone[$normPhone]['name'] = $entryName;
                }
            } elseif ($normPhone !== '' || $entry->customer_id) {
                $resolved = AdLeadEntry::resolveOrCreateCanonicalCustomer(
                    rawPhone: $entry->phone,
                    customerName: $entry->customer_name,
                    hintCustomer: $entry->customer_id ? Customer::find($entry->customer_id) : null
                );
                if ($resolved) {
                    $canonicalCustomerId = (int) $resolved->id;
                    if ($normPhone !== '') {
                        $canonicalByPhone[$normPhone] = [
                            'id' => (int) $resolved->id,
                            'phone' => (string) $resolved->phone,
                            'name' => (string) ($resolved->name ?? ''),
                            'is_e164' => Customer::isCanonicalE164($resolved->phone),
                        ];
                    }
                }
            }

            $entryUpdates = [];
            if ($canonicalCustomerId && Schema::hasColumn('ad_lead_entries', 'customer_id') && (int) $entry->customer_id !== $canonicalCustomerId) {
                $entryUpdates['customer_id'] = $canonicalCustomerId;
            }
            if ($normPhone !== '' && $entry->phone !== $normPhone) {
                $entryUpdates['phone'] = $normPhone;
            }

            if (!empty($entryUpdates)) {
                $entry->update($entryUpdates);
                $repairedAdLeads++;
            }
        }

        // 3. Repair appointments customer_id links (including 14-day spillover range when --month is set)
        if (Schema::hasTable('appointments') && Schema::hasColumn('appointments', 'customer_id')) {
            $appointmentsQuery = Appointment::query()->orderBy('id');
            if ($leadWindowStart && $apptWindowEnd) {
                $appointmentsQuery->where(function ($q) use ($leadWindowStart, $apptWindowEnd) {
                    $q->whereBetween('appointment_datetime', [$leadWindowStart, $apptWindowEnd])
                        ->orWhere(function ($sq) use ($leadWindowStart, $apptWindowEnd) {
                            $sq->whereNull('appointment_datetime')
                                ->whereBetween('booking_date', [$leadWindowStart->toDateString(), $apptWindowEnd->toDateString()]);
                        });
                });
            }

            $appointmentsQuery->chunkById(500, function ($appointments) use (&$canonicalByPhone, $duplicateToCanonicalId, &$repairedAppointments) {
                foreach ($appointments as $appt) {
                    $targetCustomerId = null;

                    if ($appt->customer_id && isset($duplicateToCanonicalId[(int) $appt->customer_id])) {
                        $targetCustomerId = $duplicateToCanonicalId[(int) $appt->customer_id];
                    } elseif (!$appt->customer_id) {
                        $normApptPhone = Customer::normalizePhone($appt->phone);
                        if ($normApptPhone !== '') {
                            if (!isset($canonicalByPhone[$normApptPhone])) {
                                $resolved = AdLeadEntry::resolveOrCreateCanonicalCustomer($appt->phone, $appt->customer_name);
                                if ($resolved) {
                                    $canonicalByPhone[$normApptPhone] = [
                                        'id' => (int) $resolved->id,
                                        'phone' => (string) $resolved->phone,
                                        'name' => (string) ($resolved->name ?? ''),
                                        'is_e164' => Customer::isCanonicalE164($resolved->phone),
                                    ];
                                }
                            }
                            $targetCustomerId = $canonicalByPhone[$normApptPhone]['id'] ?? null;
                        }
                    }

                    if ($targetCustomerId && (int) $appt->customer_id !== (int) $targetCustomerId) {
                        $appt->update(['customer_id' => $targetCustomerId]);
                        $repairedAppointments++;
                    }
                }
            });
        }

        // 4. Repair sales customer_id links (from linked Appointment or duplicate Customer mapping)
        if (Schema::hasTable('sales') && Schema::hasColumn('sales', 'customer_id')) {
            $salesQuery = Sale::with('appointment')->orderBy('id');
            if ($leadWindowStart && $apptWindowEnd) {
                $salesQuery->where(function ($q) use ($leadWindowStart, $apptWindowEnd) {
                    $q->whereBetween('created_at', [$leadWindowStart, $apptWindowEnd])
                        ->orWhereHas('appointment', function ($aq) use ($leadWindowStart, $apptWindowEnd) {
                            $aq->whereBetween('appointment_datetime', [$leadWindowStart, $apptWindowEnd])
                                ->orWhereBetween('booking_date', [$leadWindowStart->toDateString(), $apptWindowEnd->toDateString()]);
                        });
                });
            }

            $salesQuery->chunkById(500, function ($sales) use ($canonicalByPhone, $duplicateToCanonicalId, &$repairedSales) {
                foreach ($sales as $sale) {
                    $targetCustomerId = null;

                    if ($sale->customer_id && isset($duplicateToCanonicalId[(int) $sale->customer_id])) {
                        $targetCustomerId = $duplicateToCanonicalId[(int) $sale->customer_id];
                    } elseif (!$sale->customer_id && $sale->appointment) {
                        if ($sale->appointment->customer_id) {
                            $apptCustId = (int) $sale->appointment->customer_id;
                            $targetCustomerId = $duplicateToCanonicalId[$apptCustId] ?? $apptCustId;
                        } else {
                            $normSalePhone = Customer::normalizePhone($sale->appointment->phone);
                            if ($normSalePhone !== '' && isset($canonicalByPhone[$normSalePhone])) {
                                $targetCustomerId = $canonicalByPhone[$normSalePhone]['id'];
                            }
                        }
                    }

                    if ($targetCustomerId && (int) $sale->customer_id !== (int) $targetCustomerId) {
                        $sale->update(['customer_id' => $targetCustomerId]);
                        $repairedSales++;
                    }
                }
            });
        }

        // Also consolidate duplicate customer_id pointers on leads if present
        if (!empty($duplicateToCanonicalId) && Schema::hasTable('leads') && Schema::hasColumn('leads', 'customer_id')) {
            foreach ($duplicateToCanonicalId as $dupId => $canonId) {
                Lead::where('customer_id', $dupId)->update(['customer_id' => $canonId]);
            }
        }

        // 5. Re-sync Enhanced Calendar booking attribution across all target AdLeadEntry cohorts
        $syncedCohortKeys = [];
        foreach ($entries->fresh() as $entry) {
            $entryMonth = Carbon::parse($entry->date)->format('Y-m');
            $identityKey = ($entry->customer_id ? 'c:' . $entry->customer_id : 'p:' . Customer::normalizePhone($entry->phone)) . '|' . $entryMonth;
            if (isset($syncedCohortKeys[$identityKey])) {
                continue;
            }
            $syncedCohortKeys[$identityKey] = true;

            $customer = $entry->customer_id ? Customer::find($entry->customer_id) : null;
            AdLeadEntry::syncFromCustomerActivity(
                customer: $customer,
                activityDate: Carbon::parse($entry->date),
                phone: $entry->phone,
                resetIfNoActivity: true
            );
        }

        // Refresh entries for summary report
        $finalEntries = AdLeadEntry::query()
            ->when($monthStart && $monthEnd, fn ($q) => $q->whereBetween('date', [$monthStart, $monthEnd]))
            ->get();

        $convertedCount = $finalEntries->filter(fn (AdLeadEntry $e) => $e->isBooked())->count();
        $oaRevenue = round((float) $finalEntries->sum(fn (AdLeadEntry $e) => $e->branchRevenue('old_airport')), 2);
        $wkRevenue = round((float) $finalEntries->sum(fn (AdLeadEntry $e) => $e->branchRevenue('wakrah')), 2);
        $totalRevenue = round($oaRevenue + $wkRevenue, 2);

        $this->info('Ad Leads Customer-Profile Attribution Sync Complete');
        $this->table(
            ['Metric', 'Value'],
            [
                ['Scope', $rawMonth !== '' ? $rawMonth : 'All Months'],
                ['Ad Lead Entries Processed', (string) $finalEntries->count()],
                ['Repaired Ad Lead Customer Links', (string) $repairedAdLeads],
                ['Repaired Appointment Customer Links', (string) $repairedAppointments],
                ['Repaired Sale Customer Links', (string) $repairedSales],
                ['Converted (Paid) Ad Leads', (string) $convertedCount],
                ['Old Airport Ad Revenue (QAR)', number_format($oaRevenue, 2)],
                ['Wakrah Ad Revenue (QAR)', number_format($wkRevenue, 2)],
                ['Total Attributed Ad Revenue (QAR)', number_format($totalRevenue, 2)],
            ]
        );

        return self::SUCCESS;
    }
}
