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

        // 1. Build canonical Customer map by normalized 8-digit Qatar phone & normalize legacy Customer phone strings where safe
        $canonicalByPhone = [];
        $duplicateToCanonicalId = [];

        foreach (Customer::orderBy('id')->get() as $cust) {
            $norm = Customer::normalizePhone($cust->phone);
            if ($norm === '') {
                continue;
            }

            if (!isset($canonicalByPhone[$norm])) {
                if ($cust->phone !== $norm && !Customer::where('phone', $norm)->where('id', '!=', $cust->id)->exists()) {
                    $cust->update(['phone' => $norm]);
                }
                $canonicalByPhone[$norm] = $cust;
            } else {
                // If this duplicate holds the exact normalized string while the earlier one didn't, prefer the exact normalized one
                $currentCanonical = $canonicalByPhone[$norm];
                if ($cust->phone === $norm && $currentCanonical->phone !== $norm) {
                    $duplicateToCanonicalId[$currentCanonical->id] = $cust->id;
                    $canonicalByPhone[$norm] = $cust;
                } else {
                    $duplicateToCanonicalId[$cust->id] = $currentCanonical->id;
                }
            }
        }

        // 2. Repair ad_lead_entries customer_id & normalized phone
        $entriesQuery = AdLeadEntry::query()->orderBy('date')->orderBy('id');
        if ($monthStart && $monthEnd) {
            $entriesQuery->whereBetween('date', [$monthStart, $monthEnd]);
        }
        $entries = $entriesQuery->get();

        foreach ($entries as $entry) {
            $normPhone = Customer::normalizePhone($entry->phone ?: $entry->customer?->phone);
            $canonicalCustomer = null;

            if ($normPhone !== '' && isset($canonicalByPhone[$normPhone])) {
                $canonicalCustomer = $canonicalByPhone[$normPhone];
                $existingNameLower = strtolower(trim((string) $canonicalCustomer->name));
                $entryName = trim((string) $entry->customer_name);
                if (
                    $entryName !== ''
                    && strtolower($entryName) !== 'ad lead client'
                    && ($existingNameLower === '' || $existingNameLower === 'walk-in' || $existingNameLower === 'ad lead client')
                ) {
                    $canonicalCustomer->update(['name' => $entryName]);
                }
            } elseif ($normPhone !== '' || $entry->customer_id) {
                $canonicalCustomer = AdLeadEntry::resolveOrCreateCanonicalCustomer(
                    rawPhone: $entry->phone,
                    customerName: $entry->customer_name,
                    hintCustomer: $entry->customer_id ? Customer::find($entry->customer_id) : null
                );
                if ($canonicalCustomer && $normPhone !== '') {
                    $canonicalByPhone[$normPhone] = $canonicalCustomer;
                }
            }

            $entryUpdates = [];
            if ($canonicalCustomer && Schema::hasColumn('ad_lead_entries', 'customer_id') && (int) $entry->customer_id !== (int) $canonicalCustomer->id) {
                $entryUpdates['customer_id'] = $canonicalCustomer->id;
            }
            if ($normPhone !== '' && $entry->phone !== $normPhone) {
                $entryUpdates['phone'] = $normPhone;
            }

            if (!empty($entryUpdates)) {
                $entry->update($entryUpdates);
                $repairedAdLeads++;
            }
        }

        // 3. Repair appointments customer_id links (matching +974 / spaced / 8-digit phone variants & consolidating duplicate customers)
        if (Schema::hasTable('appointments') && Schema::hasColumn('appointments', 'customer_id')) {
            $appointmentsQuery = Appointment::query();
            if ($monthStart && $monthEnd) {
                $appointmentsQuery->where(function ($q) use ($monthStart, $monthEnd) {
                    $q->whereBetween('appointment_datetime', [$monthStart, $monthEnd])
                        ->orWhere(function ($sq) use ($monthStart, $monthEnd) {
                            $sq->whereNull('appointment_datetime')
                                ->whereBetween('booking_date', [$monthStart->toDateString(), $monthEnd->toDateString()]);
                        });
                });
            }

            foreach ($appointmentsQuery->get() as $appt) {
                $targetCustomerId = null;

                if ($appt->customer_id && isset($duplicateToCanonicalId[(int) $appt->customer_id])) {
                    $targetCustomerId = $duplicateToCanonicalId[(int) $appt->customer_id];
                } elseif (!$appt->customer_id) {
                    $normApptPhone = Customer::normalizePhone($appt->phone);
                    if ($normApptPhone !== '') {
                        if (!isset($canonicalByPhone[$normApptPhone])) {
                            $resolved = AdLeadEntry::resolveOrCreateCanonicalCustomer($appt->phone, $appt->customer_name);
                            if ($resolved) {
                                $canonicalByPhone[$normApptPhone] = $resolved;
                            }
                        }
                        $targetCustomerId = $canonicalByPhone[$normApptPhone]->id ?? null;
                    }
                }

                if ($targetCustomerId && (int) $appt->customer_id !== (int) $targetCustomerId) {
                    $appt->update(['customer_id' => $targetCustomerId]);
                    $repairedAppointments++;
                }
            }
        }

        // 4. Repair sales customer_id links (from linked Appointment or duplicate Customer mapping)
        if (Schema::hasTable('sales') && Schema::hasColumn('sales', 'customer_id')) {
            $salesQuery = Sale::with('appointment');
            if ($monthStart && $monthEnd) {
                $salesQuery->where(function ($q) use ($monthStart, $monthEnd) {
                    $q->whereBetween('created_at', [$monthStart, $monthEnd])
                        ->orWhereHas('appointment', function ($aq) use ($monthStart, $monthEnd) {
                            $aq->whereBetween('appointment_datetime', [$monthStart, $monthEnd])
                                ->orWhereBetween('booking_date', [$monthStart->toDateString(), $monthEnd->toDateString()]);
                        });
                });
            }

            foreach ($salesQuery->get() as $sale) {
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
                            $targetCustomerId = $canonicalByPhone[$normSalePhone]->id;
                        }
                    }
                }

                if ($targetCustomerId && (int) $sale->customer_id !== (int) $targetCustomerId) {
                    $sale->update(['customer_id' => $targetCustomerId]);
                    $repairedSales++;
                }
            }
        }

        // Also consolidate duplicate customer_id pointers on leads if present
        if (!empty($duplicateToCanonicalId) && Schema::hasTable('leads') && Schema::hasColumn('leads', 'customer_id')) {
            foreach ($duplicateToCanonicalId as $dupId => $canonId) {
                Lead::where('customer_id', $dupId)->update(['customer_id' => $canonId]);
            }
        }

        // 5. Re-sync same-month customer revenue across all target AdLeadEntry cohorts
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
