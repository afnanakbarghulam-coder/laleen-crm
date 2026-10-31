<?php

use App\Models\Lead;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Merge existing open duplicate leads sharing the same normalized phone
     * and category by combining their service_interest arrays and keeping
     * the latest row.
     */
    public function up(): void
    {
        if (!Schema::hasTable('leads')) {
            return;
        }

        $openLeads = DB::table('leads')
            ->whereNotNull('phone')
            ->where('phone', '!=', '')
            ->where(function ($q) {
                $q->whereNull('needful_done')->orWhere('needful_done', '!=', 'yes');
            })
            ->orderBy('id')
            ->get();

        $grouped = $openLeads->groupBy(function ($row) {
            [$countryCode, $localNumber] = Lead::splitPhone($row->phone);
            $normalizedPhone = Lead::normalizePhone($countryCode, $localNumber);

            return $normalizedPhone . '|' . strtolower(trim((string) $row->category));
        });

        foreach ($grouped as $group) {
            if ($group->count() <= 1) {
                continue;
            }

            $keeper = $group->last();
            $duplicateIds = $group->slice(0, -1)->pluck('id')->all();

            $mergedServices = [];
            $remarksParts = [];
            $fallbackCustomerId = null;
            $fallbackAgentId = null;
            $fallbackAppointmentId = null;
            $fallbackFollowupDate = null;

            foreach ($group as $row) {
                $decoded = is_string($row->service_interest)
                    ? json_decode($row->service_interest, true)
                    : (is_array($row->service_interest) ? $row->service_interest : []);

                if (is_array($decoded)) {
                    foreach ($decoded as $service) {
                        $trimmed = trim((string) $service);
                        if ($trimmed !== '' && !in_array($trimmed, $mergedServices, true)) {
                            $mergedServices[] = $trimmed;
                        }
                    }
                }

                $remark = trim((string) ($row->customer_remarks ?? ''));
                if ($remark !== '' && !in_array($remark, $remarksParts, true)) {
                    $remarksParts[] = $remark;
                }

                if ($row->customer_id) {
                    $fallbackCustomerId = $row->customer_id;
                }
                if ($row->assigned_agent_id) {
                    $fallbackAgentId = $row->assigned_agent_id;
                }
                if ($row->appointment_id) {
                    $fallbackAppointmentId = $row->appointment_id;
                }
                if ($row->next_followup_date) {
                    $fallbackFollowupDate = $row->next_followup_date;
                }
            }

            [$countryCode, $localNumber] = Lead::splitPhone($keeper->phone);
            $normalizedPhone = Lead::normalizePhone($countryCode, $localNumber);

            DB::table('leads')
                ->where('id', $keeper->id)
                ->update([
                    'phone' => $normalizedPhone !== '' ? $normalizedPhone : $keeper->phone,
                    'service_interest' => !empty($mergedServices) ? json_encode($mergedServices) : $keeper->service_interest,
                    'customer_remarks' => !empty($remarksParts) ? implode(' | ', $remarksParts) : $keeper->customer_remarks,
                    'customer_id' => $keeper->customer_id ?: $fallbackCustomerId,
                    'assigned_agent_id' => $keeper->assigned_agent_id ?: $fallbackAgentId,
                    'appointment_id' => $keeper->appointment_id ?: $fallbackAppointmentId,
                    'next_followup_date' => $keeper->next_followup_date ?: $fallbackFollowupDate,
                    'updated_at' => now(),
                ]);

            DB::table('leads')->whereIn('id', $duplicateIds)->delete();
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // One-way data deduplication cleanup.
    }
};
