<?php

use App\Models\Lead;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('leads')) {
            return;
        }

        // 1. Clear stale next_followup_date on already closed leads ('booked' or 'no')
        Lead::whereIn('needful_done', Lead::CLOSED_STATUSES)
            ->whereNotNull('next_followup_date')
            ->update(['next_followup_date' => null]);

        // 2. Consolidate all open leads sharing the same normalized phone into the single most recent row
        $openLeads = Lead::where(function ($q) {
            $q->whereNull('needful_done')->orWhereNotIn('needful_done', Lead::CLOSED_STATUSES);
        })
            ->orderByDesc('id')
            ->get();

        $groups = $openLeads->groupBy(function (Lead $lead) {
            [$countryCode, $localNumber] = Lead::splitPhone($lead->phone);
            $normalized = Lead::normalizePhone($countryCode, $localNumber);

            return $normalized !== '' ? $normalized : (string) $lead->phone;
        });

        foreach ($groups as $normalizedPhone => $group) {
            if ($normalizedPhone === '' || $group->count() <= 1) {
                continue;
            }

            /** @var Lead $primary */
            $primary = $group->first();

            $mergedServices = [];
            $remarks = [];
            $hasContactedYes = false;
            $customerId = $primary->customer_id;
            $appointmentId = $primary->appointment_id;
            $assignedAgentId = $primary->assigned_agent_id;
            $nextFollowupDate = $primary->next_followup_date;

            foreach ($group as $row) {
                if (is_array($row->service_interest)) {
                    $mergedServices = array_merge($mergedServices, $row->service_interest);
                }

                $remark = trim((string) $row->customer_remarks);
                if ($remark !== '' && !in_array($remark, $remarks, true)) {
                    $remarks[] = $remark;
                }

                if ($row->needful_done === 'yes') {
                    $hasContactedYes = true;
                }

                if (!$customerId && $row->customer_id) {
                    $customerId = $row->customer_id;
                }

                if (!$appointmentId && $row->appointment_id) {
                    $appointmentId = $row->appointment_id;
                }

                if (!$assignedAgentId && $row->assigned_agent_id) {
                    $assignedAgentId = $row->assigned_agent_id;
                }

                if (!$nextFollowupDate && $row->next_followup_date) {
                    $nextFollowupDate = $row->next_followup_date;
                }
            }

            $mergedServices = array_values(array_unique(array_filter(array_map('trim', $mergedServices))));

            $primary->update([
                'phone' => $normalizedPhone,
                'service_interest' => !empty($mergedServices) ? $mergedServices : $primary->service_interest,
                'customer_remarks' => !empty($remarks) ? implode(' | ', $remarks) : $primary->customer_remarks,
                'needful_done' => $hasContactedYes ? 'yes' : $primary->needful_done,
                'customer_id' => $customerId,
                'appointment_id' => $appointmentId,
                'assigned_agent_id' => $assignedAgentId,
                'next_followup_date' => $nextFollowupDate,
            ]);

            foreach ($group->slice(1) as $duplicate) {
                $duplicate->delete();
            }
        }
    }

    public function down(): void
    {
        // One-way data consolidation
    }
};
