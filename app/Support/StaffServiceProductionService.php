<?php

namespace App\Support;

use App\Models\Appointment;
use App\Models\AppointmentService;
use App\Models\ClientPackage;
use App\Models\Customer;
use App\Models\Sale;
use App\Models\Staff;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class StaffServiceProductionService
{
    /**
     * Compute Month-to-Date (Asia/Qatar) Service Production strictly for `$staff`:
     * - Completed / paid appointments ONLY (`Appointment::paidOrCompleted()`, excluding
     *   cancelled, no_show, soft-deleted, and uncompleted future bookings).
     * - Attributes each service line to its exact assigned specialist (`staff_id`).
     * - Standalone services: net paid line price after item & bill discounts.
     * - Service + Freebies: anchor gets net paid price; freebie redemption = 0.00 QAR (still counted).
     * - Combos & Custom Combos: prorates actual net paid combo total proportionally by catalog price
     *   (equal split fallback if catalog prices sum to 0) with penny-exact conservation — zero double-counting.
     *
     * @return array{
     *     month_label: string,
     *     total_services: int,
     *     standalone_count: int,
     *     combo_count: int,
     *     freebie_count: int,
     *     clients_served: int,
     *     total_revenue_qar: float,
     *     formatted_revenue: string,
     *     avg_per_service_qar: float,
     *     formatted_avg: string,
     *     services_subtitle: string,
     *     revenue_subtitle: string
     * }
     */
    public function buildForStaff(Staff $staff, ?Carbon $asOfQatar = null): array
    {
        $nowQatar = ($asOfQatar ? $asOfQatar->copy()->timezone('Asia/Qatar') : Carbon::now('Asia/Qatar'));
        $monthStart = $nowQatar->copy()->startOfMonth()->startOfDay();
        $monthEnd = $nowQatar->copy()->endOfDay();
        $monthStartStr = $monthStart->toDateString();
        $todayStr = $nowQatar->toDateString();
        $monthLabel = $nowQatar->format('M Y');

        if (!Schema::hasTable('appointments')) {
            return $this->emptyPayload($monthLabel);
        }

        $query = Appointment::query()
            ->paidOrCompleted()
            ->where(function ($q) use ($staff) {
                $q->where('staff_id', $staff->id)
                    ->orWhereHas('appointmentServices', fn ($sq) => $sq->where('staff_id', $staff->id));
            })
            ->where(function ($q) use ($monthStartStr, $todayStr, $monthStart, $monthEnd) {
                $q->whereBetween('booking_date', [$monthStartStr, $todayStr])
                    ->orWhere(function ($q2) use ($monthStart, $monthEnd) {
                        $q2->whereNull('booking_date')
                            ->whereBetween('appointment_datetime', [$monthStart, $monthEnd]);
                    })
                    ->orWhere(function ($q3) use ($monthStart, $monthEnd) {
                        $q3->whereNull('booking_date')
                            ->whereNull('appointment_datetime')
                            ->whereBetween('paid_at', [$monthStart, $monthEnd]);
                    });
            });

        if (Schema::hasColumn('appointments', 'deleted_at')) {
            $query->whereNull('deleted_at');
        }

        $appointments = $query
            ->with([
                'sale',
                'combo.services:id,name,price',
                'clientPackages.sale',
                'clientPackages.combo.services:id,name,price',
                'clientPackages.services.service:id,name,price',
                'clientPackages.services.appointmentService:id,appointment_id,service_id,staff_id,price,original_price,is_pending,status',
                'appointmentServices.service:id,name,price',
                'appointmentServices.clientPackage.sale',
                'appointmentServices.clientPackage.combo.services:id,name,price',
                'appointmentServices.clientPackage.services.service:id,name,price',
                'appointmentServices.clientPackage.services.appointmentService:id,appointment_id,service_id,staff_id,price,original_price,is_pending,status',
                'appointmentServices.clientPackageService.clientPackage.sale',
                'appointmentServices.clientPackageService.clientPackage.combo.services:id,name,price',
                'appointmentServices.clientPackageService.clientPackage.services.service:id,name,price',
                'appointmentServices.clientPackageService.clientPackage.services.appointmentService:id,appointment_id,service_id,staff_id,price,original_price,is_pending,status',
            ])
            ->get();

        $standaloneCount = 0;
        $comboCount = 0;
        $freebieCount = 0;
        $totalRevenue = 0.0;
        $clientKeys = [];

        foreach ($appointments as $appointment) {
            $saleFactor = $this->saleNetFactor($appointment->sale);

            $activeLines = $appointment->appointmentServices
                ->filter(function (AppointmentService $line) {
                    if ($line->is_pending) {
                        return false;
                    }
                    $st = strtolower(trim((string) ($line->status ?? '')));

                    return !in_array($st, ['cancelled', 'no_show'], true);
                })
                ->values();

            // Legacy bare appointment without AppointmentService line items
            if ($activeLines->isEmpty()) {
                if ((int) $appointment->staff_id !== (int) $staff->id) {
                    continue;
                }
                if ($appointment->decide_in_salon && (float) $appointment->price <= 0) {
                    continue;
                }

                if ($appointment->is_freebie_redemption) {
                    $standaloneCount++;
                    $freebieCount++;
                } else {
                    $lineNet = $appointment->sale
                        ? round(max(0.0, (float) $appointment->sale->services_total + (float) $appointment->sale->packages_total) * $saleFactor, 2)
                        : round(max(0.0, (float) $appointment->price), 2);
                    $standaloneCount++;
                    $totalRevenue += $lineNet;
                }

                $clientKeys[$this->clientIdentityKey($appointment)] = true;
                continue;
            }

            // Classify every active line on the appointment so combo splits see ALL sibling items accurately
            $standaloneLines = collect();
            $freebieLines = collect();
            $packageGroups = []; // keyed by 'pkg:{id}'
            $directComboGroups = []; // keyed by 'combo:{combo_id|name}'

            foreach ($activeLines as $line) {
                if ($this->isFreebieRedemptionLine($line)) {
                    $freebieLines->push($line);
                    continue;
                }

                $pkg = $this->resolveLineClientPackage($line, $appointment);
                if ($pkg !== null && !$line->is_combo_extra) {
                    $packageGroups['pkg:' . $pkg->id]['package'] = $pkg;
                    $packageGroups['pkg:' . $pkg->id]['lines'][] = $line;
                    continue;
                }

                if ($this->isDirectComboLine($line, $appointment)) {
                    $key = $line->combo_id ? ('combo_id:' . $line->combo_id) : ('combo_name:' . strtolower(trim((string) ($line->combo_name ?: $appointment->combo_name ?: 'combo'))));
                    $directComboGroups[$key][] = $line;
                    continue;
                }

                $standaloneLines->push($line);
            }

            $touchedByStaff = false;

            // 1. Freebie redemptions: +1 service performed, 0.00 QAR revenue
            foreach ($freebieLines as $line) {
                if ($this->lineStaffId($line, $appointment) === (int) $staff->id) {
                    $standaloneCount++;
                    $freebieCount++;
                    $touchedByStaff = true;
                }
            }

            // 2. Standalone services (incl. Service+Freebies anchor & paid combo extras): net paid line price
            $standaloneNetTotal = 0.0;
            foreach ($standaloneLines as $line) {
                $lineNet = round(max(0.0, (float) $line->final_price) * $saleFactor, 2);
                $standaloneNetTotal += $lineNet;

                if ($this->lineStaffId($line, $appointment) === (int) $staff->id) {
                    $standaloneCount++;
                    $totalRevenue += $lineNet;
                    $touchedByStaff = true;
                }
            }

            // 3. ClientPackage-backed Combos (regular & custom combos)
            foreach ($packageGroups as $group) {
                /** @var ClientPackage $pkg */
                $pkg = $group['package'];
                /** @var list<AppointmentService> $apptPkgLines */
                $apptPkgLines = $group['lines'];

                $sharesByLineId = $this->prorateClientPackageLines($pkg, $apptPkgLines, $appointment);

                foreach ($apptPkgLines as $line) {
                    if ($this->lineStaffId($line, $appointment) === (int) $staff->id) {
                        $comboCount++;
                        $totalRevenue += (float) ($sharesByLineId[$line->id] ?? 0.0);
                        $touchedByStaff = true;
                    }
                }
            }

            // 4. Direct Appointment Combos (without ClientPackage row)
            if (!empty($directComboGroups)) {
                $apptGrossNet = $appointment->sale
                    ? round(max(0.0, ((float) $appointment->sale->services_total + (float) $appointment->sale->packages_total) * $saleFactor), 2)
                    : round(max(0.0, (float) $appointment->price), 2);
                $pkgNetSum = collect($packageGroups)->sum(function ($g) {
                    /** @var ClientPackage $p */
                    $p = $g['package'];

                    return round(max(0.0, (float) $p->price_paid) * $this->saleNetFactor($p->sale), 2);
                });
                $remainingPoolForDirectCombos = max(0.0, round($apptGrossNet - $standaloneNetTotal - $pkgNetSum, 2));
                $directGroupCount = count($directComboGroups);

                foreach ($directComboGroups as $groupLines) {
                    /** @var list<AppointmentService> $groupLines */
                    $linesSum = round(collect($groupLines)->sum(fn (AppointmentService $l) => max(0.0, (float) $l->final_price) * $saleFactor), 2);
                    if ($linesSum > 0) {
                        $comboNetPaid = $linesSum;
                    } elseif ($remainingPoolForDirectCombos > 0) {
                        $comboNetPaid = round($remainingPoolForDirectCombos / max(1, $directGroupCount), 2);
                    } else {
                        $comboNetPaid = round(max(0.0, (float) ($appointment->combo?->price ?? 0.0)) * $saleFactor, 2);
                    }

                    $weights = array_map(fn (AppointmentService $l) => $this->catalogWeightForLine($l), $groupLines);
                    $shares = self::prorateAmount($comboNetPaid, $weights);

                    foreach ($groupLines as $idx => $line) {
                        if ($this->lineStaffId($line, $appointment) === (int) $staff->id) {
                            $comboCount++;
                            $totalRevenue += (float) ($shares[$idx] ?? 0.0);
                            $touchedByStaff = true;
                        }
                    }
                }
            }

            if ($touchedByStaff) {
                $clientKeys[$this->clientIdentityKey($appointment)] = true;
            }
        }

        $totalServices = $standaloneCount + $comboCount;
        $clientsServed = count($clientKeys);
        $totalRevenue = round($totalRevenue, 2);
        $avgPerService = $totalServices > 0 ? round($totalRevenue / $totalServices, 2) : 0.0;

        $formattedRevenue = $this->formatQar($totalRevenue);
        $formattedAvg = $this->formatQar($avgPerService);

        $servicesSubtitle = sprintf(
            '%d standalone · %d combo · %d %s served',
            $standaloneCount,
            $comboCount,
            $clientsServed,
            $clientsServed === 1 ? 'client' : 'clients'
        );

        $revenueSubtitle = $totalServices > 0
            ? "Avg {$formattedAvg} / service · Completed & paid only"
            : 'Completed & paid only';

        return [
            'month_label' => $monthLabel,
            'total_services' => $totalServices,
            'standalone_count' => $standaloneCount,
            'combo_count' => $comboCount,
            'freebie_count' => $freebieCount,
            'clients_served' => $clientsServed,
            'total_revenue_qar' => $totalRevenue,
            'formatted_revenue' => $formattedRevenue,
            'avg_per_service_qar' => $avgPerService,
            'formatted_avg' => $formattedAvg,
            'services_subtitle' => $servicesSubtitle,
            'revenue_subtitle' => $revenueSubtitle,
        ];
    }

    /**
     * Prorate a total QAR amount across weighted items with penny-exact conservation
     * (sum of returned shares === round($totalAmount, 2)). Falls back to equal split
     * when all weights sum to 0.
     *
     * @param  list<float>  $weights
     * @return list<float>
     */
    public static function prorateAmount(float $totalAmount, array $weights): array
    {
        $n = count($weights);
        if ($n === 0) {
            return [];
        }

        $cleanTotal = round(max(0.0, $totalAmount), 2);
        if ($cleanTotal <= 0.0) {
            return array_fill(0, $n, 0.0);
        }

        $positiveWeights = array_map(fn ($w) => max(0.0, (float) $w), $weights);
        $weightSum = array_sum($positiveWeights);

        if ($weightSum <= 0.0) {
            $positiveWeights = array_fill(0, $n, 1.0);
            $weightSum = (float) $n;
        }

        $shares = [];
        $allocated = 0.0;
        for ($i = 0; $i < $n; $i++) {
            if ($i === $n - 1) {
                $shares[$i] = round(max(0.0, $cleanTotal - $allocated), 2);
            } else {
                $share = round($cleanTotal * ($positiveWeights[$i] / $weightSum), 2);
                $shares[$i] = $share;
                $allocated = round($allocated + $share, 2);
            }
        }

        return $shares;
    }

    /**
     * Prorate a ClientPackage's net paid total across all its slots / performed lines without
     * over-allocating unperformed future visits or double-counting across specialists.
     *
     * @param  list<AppointmentService>  $apptPkgLines
     * @return array<int, float>  Map of appointment_service.id => QAR share
     */
    private function prorateClientPackageLines(ClientPackage $pkg, array $apptPkgLines, Appointment $appointment): array
    {
        $saleForPkg = $pkg->sale ?? ($pkg->appointment_id && (int) $pkg->appointment_id === (int) $appointment->id ? $appointment->sale : null);
        $netPaid = round(max(0.0, (float) $pkg->price_paid) * $this->saleNetFactor($saleForPkg), 2);
        if ($netPaid <= 0.0) {
            return [];
        }

        $qtyIncluded = max(1, (int) ($pkg->quantity_included ?: count($apptPkgLines)));

        // Build unified list of slots on this ClientPackage (prefer materialized AppointmentService lines + any other package slots)
        $slots = [];
        $seenApptLineIds = [];

        foreach ($apptPkgLines as $line) {
            $seenApptLineIds[$line->id] = true;
            $slots[] = [
                'appt_line_id' => $line->id,
                'weight' => $this->catalogWeightForLine($line),
            ];
        }

        // Include sibling slots from ClientPackageService (e.g., redeemed on another visit or still pending)
        $pkgServices = $pkg->relationLoaded('services') ? $pkg->services : collect();
        foreach ($pkgServices as $cps) {
            if ($cps->appointment_service_id && isset($seenApptLineIds[$cps->appointment_service_id])) {
                continue;
            }
            if ($cps->status === 'expired' || $cps->status === 'cancelled') {
                continue;
            }
            $weight = (float) ($cps->service?->price ?? $cps->appointmentService?->original_price ?? 0.0);
            $slots[] = [
                'appt_line_id' => null,
                'weight' => max(0.0, $weight),
            ];
        }

        $materializedCount = count($slots);
        if ($materializedCount === 0) {
            return [];
        }

        // If the package still has open-pool unmaterialized future slots, scale the pool proportionally
        // so today's performed visit never claims revenue belonging to future unperformed visits.
        $effectivePool = $materializedCount < $qtyIncluded
            ? round($netPaid * ($materializedCount / $qtyIncluded), 2)
            : $netPaid;

        $weights = array_column($slots, 'weight');
        $shares = self::prorateAmount($effectivePool, $weights);

        $map = [];
        foreach ($slots as $idx => $slot) {
            if ($slot['appt_line_id'] !== null) {
                $map[$slot['appt_line_id']] = (float) ($shares[$idx] ?? 0.0);
            }
        }

        return $map;
    }

    private function isFreebieRedemptionLine(AppointmentService $line): bool
    {
        if ($line->is_freebie_redemption || $line->freebie_credit_id !== null) {
            return true;
        }

        $reason = trim((string) ($line->discount_reason ?? ''));

        return $reason !== '' && stripos($reason, 'Freebie from ') === 0;
    }

    private function resolveLineClientPackage(AppointmentService $line, Appointment $appointment): ?ClientPackage
    {
        if ($line->client_package_id) {
            if ($line->relationLoaded('clientPackage') && $line->clientPackage) {
                return $line->clientPackage;
            }
            $fromAppt = $appointment->clientPackages->firstWhere('id', $line->client_package_id);
            if ($fromAppt) {
                return $fromAppt;
            }
        }

        if ($line->relationLoaded('clientPackageService') && $line->clientPackageService?->clientPackage) {
            return $line->clientPackageService->clientPackage;
        }

        // If the appointment has a single attached ClientPackage and this line carries a combo reference
        if (($line->combo_id || !empty($line->combo_name)) && !$line->is_combo_extra) {
            $matched = $appointment->clientPackages->first(function (ClientPackage $p) use ($line) {
                return ($line->combo_id && (int) $p->combo_id === (int) $line->combo_id)
                    || (!empty($line->combo_name) && strcasecmp(trim((string) $p->combo_name), trim((string) $line->combo_name)) === 0);
            });
            if ($matched) {
                return $matched;
            }
        }

        return null;
    }

    private function isDirectComboLine(AppointmentService $line, Appointment $appointment): bool
    {
        if ($line->is_combo_extra) {
            return false;
        }

        if ($line->combo_id !== null || !empty($line->combo_name)) {
            return true;
        }

        $reason = trim((string) ($line->discount_reason ?? ''));
        if ($reason !== '' && stripos($reason, 'Redeemed from ') === 0) {
            return true;
        }

        // If appointment itself is booked as a direct combo and line has price 0
        if (($appointment->combo_id !== null || !empty($appointment->combo_name)) && (float) $line->price <= 0.0) {
            return true;
        }

        return false;
    }

    private function catalogWeightForLine(AppointmentService $line): float
    {
        if ($line->relationLoaded('service') && $line->service && (float) $line->service->price > 0) {
            return (float) $line->service->price;
        }

        if ($line->original_price !== null && (float) $line->original_price > 0) {
            return (float) $line->original_price;
        }

        if ((float) $line->price > 0) {
            return (float) $line->price;
        }

        return 0.0;
    }

    private function saleNetFactor(?Sale $sale): float
    {
        if (!$sale) {
            return 1.0;
        }

        $discount = max(0.0, (float) ($sale->discount_amount ?? 0.0));
        if ($discount <= 0.0) {
            return 1.0;
        }

        $subtotal = max(
            0.0,
            (float) ($sale->services_total ?? 0.0)
            + (float) ($sale->products_total ?? 0.0)
            + (float) ($sale->packages_total ?? 0.0)
        );

        if ($subtotal <= 0.0) {
            return 1.0;
        }

        return max(0.0, min(1.0, ($subtotal - $discount) / $subtotal));
    }

    private function lineStaffId(AppointmentService $line, Appointment $appointment): int
    {
        return (int) ($line->staff_id ?: $appointment->staff_id ?: 0);
    }

    private function clientIdentityKey(Appointment $appointment): string
    {
        if (!empty($appointment->customer_id)) {
            return 'cid:' . (int) $appointment->customer_id;
        }
        $normPhone = !empty($appointment->phone) ? Customer::normalizePhone((string) $appointment->phone) : '';
        if ($normPhone !== '') {
            return 'ph:' . $normPhone;
        }
        $name = mb_strtolower(trim((string) ($appointment->customer_name ?? '')));
        if ($name !== '') {
            return 'nm:' . $name;
        }

        return 'appt:' . (int) $appointment->id;
    }

    private function formatQar(float $amount): string
    {
        $decimals = abs($amount - round($amount)) < 0.005 ? 0 : 2;

        return number_format($amount, $decimals) . ' QAR';
    }

    private function emptyPayload(string $monthLabel): array
    {
        return [
            'month_label' => $monthLabel,
            'total_services' => 0,
            'standalone_count' => 0,
            'combo_count' => 0,
            'freebie_count' => 0,
            'clients_served' => 0,
            'total_revenue_qar' => 0.0,
            'formatted_revenue' => '0 QAR',
            'avg_per_service_qar' => 0.0,
            'formatted_avg' => '0 QAR',
            'services_subtitle' => '0 standalone · 0 combo · 0 clients served',
            'revenue_subtitle' => 'Completed & paid only',
        ];
    }
}
