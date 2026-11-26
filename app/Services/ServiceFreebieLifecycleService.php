<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\AppointmentPriceOverride;
use App\Models\AppointmentService;
use App\Models\ClientFreebieCredit;
use App\Models\Customer;
use App\Models\Service;
use App\Models\ServiceFreebiePackage;
use App\Models\Staff;
use App\Support\BookingConflictValidator;
use App\Support\LineDiscountCalculator;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

class ServiceFreebieLifecycleService
{
    public function __construct(
        private ?BookingConflictValidator $conflictValidator = null
    ) {
        $this->conflictValidator ??= new BookingConflictValidator();
    }

    /**
     * Extract normalized anchor package purchase requests from a booking Request.
     * Supports both:
     *   - freebie_packages: [ { package_id, anchor_staff_id, slots: [ { mode, service_id, staff_id, ... } ] } ]
     *   - freebie_package_id + freebie_slots (shorthand single package)
     */
    public function extractAnchorPackageInputs(Request $request): array
    {
        $items = [];

        if ($request->filled('freebie_packages') && is_array($request->input('freebie_packages'))) {
            foreach ($request->input('freebie_packages') as $row) {
                if (is_array($row) && !empty($row['package_id'])) {
                    $items[] = $row;
                }
            }
        }

        if (empty($items) && $request->filled('freebie_package_id')) {
            $items[] = [
                'package_id' => (int) $request->input('freebie_package_id'),
                'anchor_staff_id' => $request->input('anchor_staff_id') ?: $request->input('staff_id'),
                'discount_type' => $request->input('freebie_discount_type'),
                'discount_value' => $request->input('freebie_discount_value'),
                'price' => $request->input('freebie_price'),
                'discount_reason' => $request->input('freebie_discount_reason'),
                'slots' => is_array($request->input('freebie_slots')) ? $request->input('freebie_slots') : [],
            ];
        }

        return $items;
    }

    /**
     * Extract normalized freebie credit redemption requests from a booking/checkout Request.
     * Supports:
     *   - redeem_freebies: [ { credit_id, service_id, staff_id, sort_order, is_parallel } ]
     *   - freebie_credit_id + (freebie_service_id | service_id)
     *   - redeem_freebie_tokens: [ "creditId:serviceId" ] + redeem_freebie_staff: { "creditId:serviceId": staffId }
     */
    public function extractRedemptionInputs(Request $request): array
    {
        $items = [];

        if ($request->filled('redeem_freebies') && is_array($request->input('redeem_freebies'))) {
            foreach ($request->input('redeem_freebies') as $row) {
                if (is_array($row) && !empty($row['credit_id'])) {
                    $items[] = [
                        'credit_id' => (int) $row['credit_id'],
                        'service_id' => isset($row['service_id']) && $row['service_id'] !== '' ? (int) $row['service_id'] : 0,
                        'staff_id' => !empty($row['staff_id']) ? (int) $row['staff_id'] : null,
                        'sort_order' => isset($row['sort_order']) && $row['sort_order'] !== '' ? (int) $row['sort_order'] : null,
                        'is_parallel' => filter_var($row['is_parallel'] ?? false, FILTER_VALIDATE_BOOLEAN),
                    ];
                }
            }
        }

        if (empty($items) && $request->filled('freebie_credit_id')) {
            $resolvedSvcId = $request->input('freebie_service_id') ?: $request->input('service_id');
            if (!$resolvedSvcId && $request->filled('service_name')) {
                $firstSvcName = is_array($request->input('service_name'))
                    ? trim((string) ($request->input('service_name')[0] ?? ''))
                    : trim((string) $request->input('service_name'));
                if ($firstSvcName !== '') {
                    $resolvedSvcId = Service::where('name', $firstSvcName)->value('id');
                }
            }

            $items[] = [
                'credit_id' => (int) $request->input('freebie_credit_id'),
                'service_id' => $resolvedSvcId ? (int) $resolvedSvcId : 0,
                'staff_id' => $request->filled('freebie_staff_id')
                    ? (int) $request->input('freebie_staff_id')
                    : ($request->filled('staff_id') ? (int) $request->input('staff_id') : null),
                'sort_order' => null,
                'is_parallel' => false,
            ];
        }

        if (empty($items) && $request->filled('redeem_freebie_tokens') && is_array($request->input('redeem_freebie_tokens'))) {
            $staffMap = is_array($request->input('redeem_freebie_staff')) ? $request->input('redeem_freebie_staff') : [];
            $sortMap = is_array($request->input('redeem_freebie_sort_order')) ? $request->input('redeem_freebie_sort_order') : [];
            $parMap = is_array($request->input('redeem_freebie_parallel')) ? $request->input('redeem_freebie_parallel') : [];

            foreach ($request->input('redeem_freebie_tokens') as $token) {
                $parts = explode(':', (string) $token, 2);
                $cid = (int) ($parts[0] ?? 0);
                $sid = (int) ($parts[1] ?? 0);
                if ($cid <= 0) {
                    continue;
                }
                $canonical = "{$cid}:{$sid}";
                $rawStaff = $staffMap[(string) $token] ?? ($staffMap[$canonical] ?? null);
                $rawSort = $sortMap[(string) $token] ?? ($sortMap[$canonical] ?? null);
                $rawPar = $parMap[(string) $token] ?? ($parMap[$canonical] ?? false);

                $items[] = [
                    'credit_id' => $cid,
                    'service_id' => $sid,
                    'staff_id' => ($rawStaff !== null && $rawStaff !== '') ? (int) $rawStaff : null,
                    'sort_order' => ($rawSort !== null && $rawSort !== '') ? (int) $rawSort : null,
                    'is_parallel' => filter_var($rawPar, FILTER_VALIDATE_BOOLEAN),
                ];
            }
        }

        return $items;
    }

    /**
     * Validate anchor Service + Freebies package purchase plans before DB writes.
     *
     * @return array{0: array, 1: ?string, 2: ?array}
     */
    public function buildAnchorPurchasePlans(array $rawPackages, string $branch, ?int $defaultStaffId = null): array
    {
        if (empty($rawPackages)) {
            return [[], null, null];
        }

        $canonicalBranch = Customer::normalizeBranch($branch) ?? $branch;
        $plans = [];

        foreach ($rawPackages as $rawPkg) {
            $package = ServiceFreebiePackage::with(['anchorService', 'eligibleServices'])->find((int) ($rawPkg['package_id'] ?? 0));
            if (!$package || !$package->is_active || !$package->anchorService) {
                return [[], 'Selected Service + Freebies package is unavailable or inactive.', [
                    'reason' => 'validation',
                    'field' => 'freebie_package_id',
                    'message' => 'Selected Service + Freebies package is unavailable or inactive.',
                ]];
            }

            $anchorService = $package->anchorService;
            $eligibleById = $package->eligibleServices->keyBy('id');
            $choicesCount = $package->effective_choices_count;

            $pricing = LineDiscountCalculator::resolve(
                (float) $package->price,
                isset($rawPkg['discount_type']) ? (string) $rawPkg['discount_type'] : null,
                $rawPkg['discount_value'] ?? null,
                $rawPkg['price'] ?? ($rawPkg['final_price'] ?? null),
                isset($rawPkg['discount_reason']) ? (string) $rawPkg['discount_reason'] : null,
                $package->name
            );
            if ($pricing['error'] !== null) {
                return [[], $pricing['error'], [
                    'reason' => 'validation',
                    'field' => 'freebie_packages',
                    'message' => $pricing['error'],
                ]];
            }

            $anchorStaffId = !empty($rawPkg['anchor_staff_id'])
                ? (int) $rawPkg['anchor_staff_id']
                : $defaultStaffId;

            if ($anchorStaffId) {
                [$staffErr, $staffConflict] = $this->validateStaffForService($anchorStaffId, $anchorService, $canonicalBranch);
                if ($staffErr !== null) {
                    return [[], $staffErr, $staffConflict];
                }
            }

            $rawSlots = is_array($rawPkg['slots'] ?? null) ? array_values($rawPkg['slots']) : [];
            if (count($rawSlots) > $choicesCount) {
                $msg = "\"{$package->name}\" only includes {$choicesCount} free mini-service slot(s).";

                return [[], $msg, [
                    'reason' => 'validation',
                    'field' => 'freebie_slots',
                    'message' => $msg,
                ]];
            }

            $normalizedSlots = [];
            for ($slotIdx = 0; $slotIdx < $choicesCount; $slotIdx++) {
                $slotInput = is_array($rawSlots[$slotIdx] ?? null) ? $rawSlots[$slotIdx] : [];
                $rawMode = strtolower(trim((string) ($slotInput['mode'] ?? '')));
                $rawServiceId = isset($slotInput['service_id']) && $slotInput['service_id'] !== '' && $slotInput['service_id'] !== 'save_for_later'
                    ? (int) $slotInput['service_id']
                    : null;

                $isSaveForLater = in_array($rawMode, ['save_for_later', 'later', 'pending'], true) || !$rawServiceId;

                if ($isSaveForLater) {
                    $normalizedSlots[] = [
                        'slot_number' => $slotIdx + 1,
                        'mode' => 'save_for_later',
                        'service' => null,
                        'staff_id' => null,
                        'sort_order' => null,
                        'is_parallel' => false,
                    ];
                    continue;
                }

                $miniService = $eligibleById->get($rawServiceId);
                if (!$miniService) {
                    $badSvc = Service::find($rawServiceId);
                    $badName = $badSvc ? "\"{$badSvc->name}\"" : 'Selected service';
                    $msg = "{$badName} is not in the eligible mini-services pool for \"{$package->name}\".";

                    return [[], $msg, [
                        'reason' => 'validation',
                        'field' => 'freebie_slots',
                        'message' => $msg,
                    ]];
                }

                $slotStaffId = !empty($slotInput['staff_id'])
                    ? (int) $slotInput['staff_id']
                    : $defaultStaffId;

                if ($slotStaffId) {
                    [$staffErr, $staffConflict] = $this->validateStaffForService($slotStaffId, $miniService, $canonicalBranch);
                    if ($staffErr !== null) {
                        return [[], $staffErr, $staffConflict];
                    }
                }

                $normalizedSlots[] = [
                    'slot_number' => $slotIdx + 1,
                    'mode' => 'same_day',
                    'service' => $miniService,
                    'staff_id' => $slotStaffId,
                    'sort_order' => isset($slotInput['sort_order']) && $slotInput['sort_order'] !== '' ? (int) $slotInput['sort_order'] : null,
                    'is_parallel' => filter_var($slotInput['is_parallel'] ?? false, FILTER_VALIDATE_BOOLEAN),
                ];
            }

            $plans[] = [
                'package' => $package,
                'anchor_service' => $anchorService,
                'anchor_staff_id' => $anchorStaffId,
                'anchor_sort_order' => isset($rawPkg['anchor_sort_order']) && $rawPkg['anchor_sort_order'] !== '' ? (int) $rawPkg['anchor_sort_order'] : null,
                'anchor_is_parallel' => filter_var($rawPkg['anchor_is_parallel'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'pricing' => $pricing,
                'slots' => $normalizedSlots,
            ];
        }

        return [$plans, null, null];
    }

    /**
     * Validate future freebie credit redemptions against client ownership, dynamic expiry,
     * eligible mini-service pool membership, and staff skill requirements.
     *
     * @return array{0: array, 1: ?string, 2: ?array}
     */
    public function buildFreebieRedemptionPlans(
        array $rawRedemptions,
        ?Customer $customer,
        string $branch,
        Carbon $targetAppointmentDate,
        ?int $forAppointmentId = null,
        ?int $defaultStaffId = null
    ): array {
        if (empty($rawRedemptions)) {
            return [[], null, null];
        }

        if (!$customer) {
            $msg = 'A client profile is required to redeem a freebie service.';

            return [[], $msg, [
                'reason' => 'validation',
                'field' => 'redeem_freebies',
                'message' => $msg,
            ]];
        }

        $canonicalBranch = Customer::normalizeBranch($branch) ?? $branch;
        $plans = [];
        $seenCreditIds = [];

        foreach ($rawRedemptions as $req) {
            $creditId = (int) ($req['credit_id'] ?? 0);
            $serviceId = (int) ($req['service_id'] ?? 0);

            if (isset($seenCreditIds[$creditId])) {
                $msg = 'Each freebie credit slot can only be redeemed once.';

                return [[], $msg, [
                    'reason' => 'validation',
                    'field' => 'redeem_freebies',
                    'message' => $msg,
                ]];
            }
            $seenCreditIds[$creditId] = true;

            $credit = ClientFreebieCredit::with('package.eligibleServices')->find($creditId);
            if (!$credit || !$credit->package) {
                $msg = 'Selected freebie credit was not found.';

                return [[], $msg, [
                    'reason' => 'validation',
                    'field' => 'redeem_freebies',
                    'message' => $msg,
                ]];
            }

            if ((int) $credit->customer_id !== (int) $customer->id) {
                $msg = 'This freebie credit does not belong to the selected client.';

                return [[], $msg, [
                    'reason' => 'validation',
                    'field' => 'redeem_freebies',
                    'message' => $msg,
                ]];
            }

            if ($credit->status === ClientFreebieCredit::STATUS_LOCKED_UNTIL_PAID) {
                $msg = "Freebie slot #{$credit->slot_number} for \"{$credit->package->name}\" is pending anchor payment and cannot be redeemed yet.";

                return [[], $msg, [
                    'reason' => 'validation',
                    'field' => 'redeem_freebies',
                    'message' => $msg,
                ]];
            }

            if ($credit->isDynamicallyExpired()) {
                $expLabel = $credit->expires_at?->copy()->timezone('Asia/Qatar')->format('d M Y') ?? 'expiry date';
                $msg = "This freebie credit from \"{$credit->package->name}\" expired on {$expLabel} and can no longer be redeemed.";

                return [[], $msg, [
                    'reason' => 'expired_freebie',
                    'field' => 'redeem_freebies',
                    'message' => $msg,
                ]];
            }

            if (!$credit->canRedeem($forAppointmentId, $targetAppointmentDate)) {
                $expLabel = $credit->expires_at?->copy()->timezone('Asia/Qatar')->format('d M Y');
                $targetQatar = $targetAppointmentDate->copy()->timezone('Asia/Qatar')->startOfDay();
                if ($credit->expires_at && $targetQatar->gt($credit->expires_at->copy()->timezone('Asia/Qatar'))) {
                    $msg = "This freebie credit from \"{$credit->package->name}\" expires on {$expLabel}. Please choose an appointment date on or before {$expLabel}.";
                } else {
                    $msg = "This freebie credit from \"{$credit->package->name}\" is no longer available ({$credit->effective_status_label}).";
                }

                return [[], $msg, [
                    'reason' => 'validation',
                    'field' => 'redeem_freebies',
                    'message' => $msg,
                ]];
            }

            $service = Service::find($serviceId);
            if (!$service || !$credit->package->eligibleServices->contains('id', $service->id)) {
                $badName = $service ? "\"{$service->name}\"" : 'Selected service';
                $msg = "{$badName} is not in the eligible mini-services pool for \"{$credit->package->name}\".";

                return [[], $msg, [
                    'reason' => 'ineligible_freebie_service',
                    'field' => 'redeem_freebies',
                    'message' => $msg,
                ]];
            }

            $staffId = !empty($req['staff_id']) ? (int) $req['staff_id'] : $defaultStaffId;
            if ($staffId) {
                [$staffErr, $staffConflict] = $this->validateStaffForService($staffId, $service, $canonicalBranch);
                if ($staffErr !== null) {
                    return [[], $staffErr, $staffConflict];
                }
            }

            $plans[] = [
                'credit' => $credit,
                'package' => $credit->package,
                'service' => $service,
                'staff_id' => $staffId,
                'sort_order' => $req['sort_order'] ?? null,
                'is_parallel' => (bool) ($req['is_parallel'] ?? false),
            ];
        }

        return [$plans, null, null];
    }

    /**
     * Persist anchor Service + Freebies slices + ClientFreebieCredit rows on a newly created Appointment.
     */
    public function persistAnchorPurchasePlans(
        Appointment $appointment,
        Customer $customer,
        array $anchorPlans,
        array $anchorScheduleByPlan,
        array $slotScheduleByPlanAndSlot,
        string $appointmentDateString
    ): void {
        if (empty($anchorPlans)) {
            return;
        }

        $firstPkg = $anchorPlans[0]['package'];
        if (!$appointment->freebie_package_id) {
            $appointment->update([
                'freebie_package_id' => $firstPkg->id,
                'is_freebie_redemption' => false,
            ]);
        }

        // Assign unique, strictly increasing slot_number across ALL Service + Freebies packages
        // on the same anchor_appointment_id (starting after any existing rows on edits/additions)
        // so stacking 2+ packages never collides on client_freebie_credits_anchor_slot_uniq.
        $nextCreditSlot = (int) ClientFreebieCredit::where('anchor_appointment_id', $appointment->id)
            ->lockForUpdate()
            ->max('slot_number');

        foreach ($anchorPlans as $pIdx => $plan) {
            /** @var ServiceFreebiePackage $pkg */
            $pkg = $plan['package'];
            /** @var Service $anchorSvc */
            $anchorSvc = $plan['anchor_service'];
            $anchorSliceMeta = $anchorScheduleByPlan[$pIdx] ?? null;

            $pricing = $plan['pricing'] ?? [
                'original_price' => (float) $pkg->price,
                'price' => (float) $pkg->price,
                'discount_type' => null,
                'discount_value' => 0.0,
                'discount_amount' => 0.0,
                'discount_reason' => null,
            ];

            $anchorLine = AppointmentService::create([
                'appointment_id' => $appointment->id,
                'service_id' => $anchorSvc->id,
                'freebie_package_id' => $pkg->id,
                'is_freebie_redemption' => false,
                'staff_id' => $anchorSliceMeta['staff_id'] ?? $plan['anchor_staff_id'] ?? $appointment->staff_id,
                'sort_order' => $anchorSliceMeta['sort_order'] ?? 0,
                'is_parallel' => (bool) ($anchorSliceMeta['is_parallel'] ?? false),
                'name' => $anchorSvc->name,
                'price' => $pricing['price'],
                'original_price' => $pricing['original_price'],
                'duration' => (int) ($anchorSvc->duration ?: 60),
                'start_time' => $anchorSliceMeta['start'] ?? null,
                'status' => 'scheduled',
                'is_pending' => false,
                'discount_type' => $pricing['discount_type'],
                'discount_value' => $pricing['discount_value'],
                'discount_amount' => $pricing['discount_amount'],
                'discount_reason' => $pricing['discount_reason'],
            ]);

            if ($anchorLine->discount_amount > 0 && Schema::hasTable('appointment_price_overrides')) {
                AppointmentPriceOverride::create([
                    'appointment_id' => $appointment->id,
                    'appointment_service_id' => $anchorLine->id,
                    'service_name' => $anchorLine->name,
                    'original_price' => $anchorLine->original_price,
                    'new_price' => $anchorLine->price,
                    'discount_amount' => $anchorLine->discount_amount,
                    'discount_reason' => $anchorLine->discount_reason,
                    'changed_by' => auth()->id(),
                ]);
            }

            $expiresAt = ClientFreebieCredit::computeExpiresAt($appointmentDateString, $pkg->effective_validity_days);

            foreach ($plan['slots'] as $slot) {
                $planSlotNum = (int) $slot['slot_number'];
                $creditSlotNum = ++$nextCreditSlot;

                if ($slot['mode'] === 'same_day' && $slot['service'] instanceof Service) {
                    /** @var Service $miniSvc */
                    $miniSvc = $slot['service'];
                    $slotMeta = $slotScheduleByPlanAndSlot[$pIdx][$planSlotNum] ?? null;

                    $freebieSlice = AppointmentService::create([
                        'appointment_id' => $appointment->id,
                        'service_id' => $miniSvc->id,
                        'freebie_package_id' => $pkg->id,
                        'is_freebie_redemption' => true,
                        'staff_id' => $slotMeta['staff_id'] ?? $slot['staff_id'] ?? $appointment->staff_id,
                        'sort_order' => $slotMeta['sort_order'] ?? $creditSlotNum,
                        'is_parallel' => (bool) ($slotMeta['is_parallel'] ?? false),
                        'name' => $miniSvc->name,
                        'price' => 0,
                        'original_price' => $miniSvc->price,
                        'duration' => (int) ($miniSvc->duration ?: 30),
                        'start_time' => $slotMeta['start'] ?? null,
                        'status' => 'scheduled',
                        'is_pending' => false,
                        'discount_type' => 'percent',
                        'discount_value' => 100,
                        'discount_reason' => "Freebie from {$pkg->name}",
                    ]);

                    $credit = ClientFreebieCredit::create([
                        'customer_id' => $customer->id,
                        'package_id' => $pkg->id,
                        'anchor_appointment_id' => $appointment->id,
                        'slot_number' => $creditSlotNum,
                        'status' => ClientFreebieCredit::STATUS_SCHEDULED,
                        'redeemed_service_id' => $miniSvc->id,
                        'redemption_appointment_id' => $appointment->id,
                        'redemption_appointment_service_id' => $freebieSlice->id,
                        'unlocked_at' => null,
                        'redeemed_at' => null,
                        'expires_at' => $expiresAt,
                    ]);

                    $freebieSlice->update(['freebie_credit_id' => $credit->id]);
                } else {
                    ClientFreebieCredit::create([
                        'customer_id' => $customer->id,
                        'package_id' => $pkg->id,
                        'anchor_appointment_id' => $appointment->id,
                        'slot_number' => $creditSlotNum,
                        'status' => ClientFreebieCredit::STATUS_LOCKED_UNTIL_PAID,
                        'redeemed_service_id' => null,
                        'redemption_appointment_id' => null,
                        'redemption_appointment_service_id' => null,
                        'unlocked_at' => null,
                        'redeemed_at' => null,
                        'expires_at' => $expiresAt,
                    ]);
                }
            }
        }
    }

    /**
     * Persist future 0.00 QAR freebie credit redemption slices on an Appointment.
     */
    public function persistFreebieRedemptionPlans(
        Appointment $appointment,
        array $freebieRedemptionPlans,
        array $scheduleByRedeemIdx,
        bool $isPureFreebieBooking = false
    ): void {
        if (empty($freebieRedemptionPlans)) {
            return;
        }

        if ($isPureFreebieBooking) {
            $appointment->update([
                'freebie_package_id' => $freebieRedemptionPlans[0]['package']->id,
                'is_freebie_redemption' => true,
            ]);
        }

        foreach ($freebieRedemptionPlans as $fIdx => $plan) {
            /** @var ClientFreebieCredit $credit */
            $credit = ClientFreebieCredit::whereKey($plan['credit']->id)->lockForUpdate()->first() ?? $plan['credit'];
            /** @var Service $svc */
            $svc = $plan['service'];
            /** @var ServiceFreebiePackage $pkg */
            $pkg = $plan['package'];
            $sliceMeta = $scheduleByRedeemIdx[$fIdx] ?? null;

            $slice = AppointmentService::create([
                'appointment_id' => $appointment->id,
                'service_id' => $svc->id,
                'freebie_package_id' => $pkg->id,
                'freebie_credit_id' => $credit->id,
                'is_freebie_redemption' => true,
                'staff_id' => $sliceMeta['staff_id'] ?? $plan['staff_id'] ?? $appointment->staff_id,
                'sort_order' => $sliceMeta['sort_order'] ?? $fIdx,
                'is_parallel' => (bool) ($sliceMeta['is_parallel'] ?? false),
                'name' => $svc->name,
                'price' => 0,
                'original_price' => $svc->price,
                'duration' => (int) ($svc->duration ?: 30),
                'start_time' => $sliceMeta['start'] ?? null,
                'status' => 'scheduled',
                'is_pending' => false,
                'discount_type' => 'percent',
                'discount_value' => 100,
                'discount_reason' => "Freebie from {$pkg->name}",
            ]);

            $credit->update([
                'status' => ClientFreebieCredit::STATUS_SCHEDULED,
                'redeemed_service_id' => $svc->id,
                'redemption_appointment_id' => $appointment->id,
                'redemption_appointment_service_id' => $slice->id,
            ]);
        }
    }

    /**
     * Unlock deferred Save-for-Later credits when the anchor booking is marked Paid/Completed,
     * and flip any scheduled freebie redemptions on this booking to 'redeemed'.
     */
    public function onAppointmentPaidOrCompleted(Appointment $appointment): void
    {
        if (!Schema::hasTable('client_freebie_credits')) {
            return;
        }

        $nowQatar = now('Asia/Qatar');
        $anchorDate = $appointment->booking_date ?: ($appointment->appointment_datetime ?: $nowQatar);

        $anchorCredits = ClientFreebieCredit::with('package')
            ->where('anchor_appointment_id', $appointment->id)
            ->lockForUpdate()
            ->get();

        foreach ($anchorCredits as $credit) {
            if ($credit->status === ClientFreebieCredit::STATUS_VOIDED) {
                continue;
            }

            $validityDays = $credit->package ? $credit->package->effective_validity_days : 7;
            $canonicalExpiry = ClientFreebieCredit::computeExpiresAt($anchorDate, $validityDays);

            if ($credit->status === ClientFreebieCredit::STATUS_LOCKED_UNTIL_PAID) {
                $credit->update([
                    'status' => ClientFreebieCredit::STATUS_AVAILABLE,
                    'unlocked_at' => $nowQatar,
                    'expires_at' => $canonicalExpiry,
                ]);
            } elseif (
                $credit->status === ClientFreebieCredit::STATUS_SCHEDULED
                && (int) $credit->redemption_appointment_id === (int) $appointment->id
            ) {
                $credit->update([
                    'status' => ClientFreebieCredit::STATUS_REDEEMED,
                    'unlocked_at' => $credit->unlocked_at ?? $nowQatar,
                    'redeemed_at' => $nowQatar,
                    'expires_at' => $canonicalExpiry,
                ]);
            } else {
                $credit->update([
                    'unlocked_at' => $credit->unlocked_at ?? $nowQatar,
                    'expires_at' => $canonicalExpiry,
                ]);
            }
        }

        $redemptionCredits = ClientFreebieCredit::where('redemption_appointment_id', $appointment->id)
            ->where('anchor_appointment_id', '!=', $appointment->id)
            ->where('status', ClientFreebieCredit::STATUS_SCHEDULED)
            ->lockForUpdate()
            ->get();

        foreach ($redemptionCredits as $rCredit) {
            $rCredit->update([
                'status' => ClientFreebieCredit::STATUS_REDEEMED,
                'redeemed_at' => $nowQatar,
            ]);
        }
    }

    /**
     * Sync expires_at on all non-voided credits when an anchor appointment's date is rescheduled.
     */
    public function syncAnchorExpiryOnReschedule(Appointment $appointment): void
    {
        if (!Schema::hasTable('client_freebie_credits')) {
            return;
        }

        $anchorCredits = ClientFreebieCredit::with('package')
            ->where('anchor_appointment_id', $appointment->id)
            ->where('status', '!=', ClientFreebieCredit::STATUS_VOIDED)
            ->get();

        if ($anchorCredits->isEmpty()) {
            return;
        }

        $anchorDate = $appointment->booking_date ?: ($appointment->appointment_datetime ?: now('Asia/Qatar'));

        foreach ($anchorCredits as $credit) {
            $validityDays = $credit->package ? $credit->package->effective_validity_days : 7;
            $credit->update([
                'expires_at' => ClientFreebieCredit::computeExpiresAt($anchorDate, $validityDays),
            ]);
        }
    }

    /**
     * Void all linked credits when an anchor booking is Cancelled/No-Show/Deleted,
     * OR release a scheduled slot back to 'available' when a future redemption booking is Cancelled/No-Show/Deleted.
     */
    public function onAppointmentCancelledOrDeleted(Appointment $appointment): void
    {
        if (!Schema::hasTable('client_freebie_credits')) {
            return;
        }

        // 1. Anchor appointment cancelled/no-show/deleted -> void ALL linked credits & cancel uncompleted sub-blocks
        $anchorCredits = ClientFreebieCredit::where('anchor_appointment_id', $appointment->id)
            ->lockForUpdate()
            ->get();

        if ($anchorCredits->isNotEmpty()) {
            $downstreamApptIds = $anchorCredits
                ->pluck('redemption_appointment_id')
                ->filter(fn ($id) => $id && (int) $id !== (int) $appointment->id)
                ->unique()
                ->values();

            foreach ($anchorCredits as $credit) {
                $credit->update([
                    'status' => ClientFreebieCredit::STATUS_VOIDED,
                ]);
            }

            AppointmentService::where('appointment_id', $appointment->id)
                ->where('is_freebie_redemption', true)
                ->update(['status' => 'cancelled']);

            foreach (Appointment::whereIn('id', $downstreamApptIds)->get() as $downstreamAppt) {
                if (in_array(strtolower((string) $downstreamAppt->status), ['completed', 'cancelled', 'no_show'], true)) {
                    continue;
                }

                $nonFreebieSlices = $downstreamAppt->appointmentServices()
                    ->where('is_pending', false)
                    ->where('is_freebie_redemption', false)
                    ->count();

                if ($nonFreebieSlices === 0) {
                    $downstreamAppt->appointmentServices()->update(['status' => 'cancelled']);
                    $downstreamAppt->update(['status' => 'cancelled']);
                } else {
                    $downstreamAppt->appointmentServices()
                        ->whereIn('freebie_credit_id', $anchorCredits->pluck('id'))
                        ->delete();
                    $downstreamAppt->syncFromServices();
                }
            }
        }

        // 2. Standalone future redemption appointment cancelled/no-show/deleted -> release slot back to 'available'
        $redemptionCredits = ClientFreebieCredit::with('anchorAppointment')
            ->where('redemption_appointment_id', $appointment->id)
            ->where('anchor_appointment_id', '!=', $appointment->id)
            ->lockForUpdate()
            ->get();

        foreach ($redemptionCredits as $rCredit) {
            if ($rCredit->status === ClientFreebieCredit::STATUS_VOIDED) {
                continue;
            }

            $anchor = $rCredit->anchorAppointment;
            $anchorCancelled = !$anchor || in_array(strtolower((string) $anchor->status), ['cancelled', 'no_show'], true);

            if ($anchorCancelled) {
                $rCredit->update(['status' => ClientFreebieCredit::STATUS_VOIDED]);
                continue;
            }

            $anchorPaid = $anchor->paid_at !== null
                || strtolower((string) $anchor->status) === 'completed'
                || $anchor->sale()->exists();

            $rCredit->update([
                'status' => $anchorPaid
                    ? ClientFreebieCredit::STATUS_AVAILABLE
                    : ClientFreebieCredit::STATUS_LOCKED_UNTIL_PAID,
                'redeemed_service_id' => null,
                'redemption_appointment_id' => null,
                'redemption_appointment_service_id' => null,
                'redeemed_at' => null,
            ]);
        }
    }

    /**
     * Restore voided anchor credits back to locked_until_paid (and scheduled for same-day slices)
     * if an unpaid cancelled/no-show anchor booking is revived/rescheduled onto the calendar.
     */
    public function restoreVoidedAnchorCreditsOnRevival(Appointment $appointment): void
    {
        if (!Schema::hasTable('client_freebie_credits')) {
            return;
        }

        $voidedCredits = ClientFreebieCredit::with('package')
            ->where('anchor_appointment_id', $appointment->id)
            ->where('status', ClientFreebieCredit::STATUS_VOIDED)
            ->get();

        if ($voidedCredits->isEmpty()) {
            return;
        }

        $anchorDate = $appointment->booking_date ?: ($appointment->appointment_datetime ?: now('Asia/Qatar'));

        AppointmentService::where('appointment_id', $appointment->id)
            ->where('is_freebie_redemption', true)
            ->update(['status' => 'scheduled']);

        foreach ($voidedCredits as $credit) {
            $validityDays = $credit->package ? $credit->package->effective_validity_days : 7;
            $expiresAt = ClientFreebieCredit::computeExpiresAt($anchorDate, $validityDays);
            $isSameDay = (int) ($credit->redemption_appointment_id ?? 0) === (int) $appointment->id && $credit->redeemed_service_id !== null;

            $credit->update([
                'status' => $isSameDay
                    ? ClientFreebieCredit::STATUS_SCHEDULED
                    : ClientFreebieCredit::STATUS_LOCKED_UNTIL_PAID,
                'expires_at' => $expiresAt,
            ]);
        }
    }

    private function validateStaffForService(int $staffId, Service $service, ?string $canonicalBranch): array
    {
        $staff = Staff::find($staffId);
        if (!$staff) {
            $msg = "Selected staff member for \"{$service->name}\" no longer exists.";

            return [$msg, [
                'reason' => 'validation',
                'field' => 'staff_id',
                'message' => $msg,
            ]];
        }

        $unskilled = $this->conflictValidator->unskilledServices($staffId, [$service->name], $canonicalBranch);
        if (!empty($unskilled)) {
            $eligibleNames = $canonicalBranch
                ? $this->conflictValidator->eligibleBranchStaffNames($canonicalBranch, $unskilled, $staffId)
                : [];
            $eligibleSuffix = !empty($eligibleNames) ? ' Eligible staff: ' . implode(', ', $eligibleNames) . '.' : '';
            $msg = "Cannot book: {$staff->name} does not have the required skill for {$service->name}.{$eligibleSuffix}";

            return [$msg, [
                'reason' => BookingConflictValidator::REASON_MISSING_SKILL,
                'field' => 'staff_id',
                'message' => $msg,
            ]];
        }

        return [null, null];
    }
}
