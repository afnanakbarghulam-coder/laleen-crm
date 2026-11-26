<?php

namespace App\Console\Commands;

use App\Models\Appointment;
use App\Models\User;
use App\Support\AgentBookingQuotaCalculator;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class BackfillAgentAttributionCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'appointments:backfill-agent-attribution
        {--dry-run : Preview candidate rows without writing changes (default unless --apply is passed)}
        {--apply : Apply attribution backfill inside a database transaction}
        {--from= : Optional YYYY-MM-DD lower bound (Asia/Qatar start of day) on appointments.created_at}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Safely backfill missing or salon_staff booking_agent_id on appointments created_by active Chat Agents.';

    public function handle(): int
    {
        if (! Schema::hasTable('appointments') || ! Schema::hasTable('users')) {
            $this->error('Required tables (appointments, users) are not present.');

            return self::FAILURE;
        }

        if (! Schema::hasColumn('appointments', 'created_by') || ! Schema::hasColumn('appointments', 'booking_agent_id')) {
            $this->error('Required columns (appointments.created_by, appointments.booking_agent_id) are missing.');

            return self::FAILURE;
        }

        $applyFlag = (bool) $this->option('apply');
        $dryRunFlag = (bool) $this->option('dry-run');

        if ($applyFlag && $dryRunFlag) {
            $this->error('Pass either --dry-run or --apply, not both.');

            return self::FAILURE;
        }

        $isApplyMode = $applyFlag;

        $rawFrom = trim((string) ($this->option('from') ?? ''));
        $fromDbString = null;
        if ($rawFrom !== '') {
            if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $rawFrom)) {
                $this->error("Invalid --from date [{$rawFrom}]. Expected YYYY-MM-DD (e.g., 2026-11-01).");

                return self::FAILURE;
            }

            $dbTz = config('app.timezone', AgentBookingQuotaCalculator::QATAR_TIMEZONE);
            $fromQatar = Carbon::createFromFormat('Y-m-d', $rawFrom, AgentBookingQuotaCalculator::QATAR_TIMEZONE)->startOfDay();
            $fromDbString = $fromQatar->copy()->timezone($dbTz)->format('Y-m-d H:i:s');
        }

        $activeAgents = User::query()
            ->where('role', 'agent')
            ->where(function ($q) {
                $q->where('is_active', true)->orWhereNull('is_active');
            })
            ->orderBy('id')
            ->get(['id', 'name', 'email'])
            ->keyBy('id');

        if ($activeAgents->isEmpty()) {
            $this->info('No active Chat Agent users (role = agent) found. Nothing to backfill.');

            return self::SUCCESS;
        }

        $hasTypeColumn = Schema::hasColumn('appointments', 'booking_agent_type');

        $query = Appointment::query()
            ->whereIn('created_by', $activeAgents->keys()->all())
            ->whereNull('booking_agent_id')
            ->orderBy('id');

        if ($hasTypeColumn) {
            $query->where(function ($q) {
                $q->whereNull('booking_agent_type')
                    ->orWhere('booking_agent_type', '')
                    ->orWhere('booking_agent_type', Appointment::BOOKING_AGENT_SALON_STAFF);
            });
        }

        if ($fromDbString !== null) {
            $query->where('created_at', '>=', $fromDbString);
        }

        $selectColumns = ['id', 'created_at', 'customer_name', 'phone', 'booking_date', 'start_time', 'created_by', 'booking_agent_id'];
        if ($hasTypeColumn) {
            $selectColumns[] = 'booking_agent_type';
        }

        $candidates = $query->get($selectColumns);

        $modeLabel = $isApplyMode ? 'APPLY' : 'DRY-RUN';
        $this->info("Mode: {$modeLabel}" . ($rawFrom !== '' ? " | From: {$rawFrom} (Asia/Qatar)" : ' | Scope: All dates'));
        $this->line('Active Chat Agents matched: ' . $activeAgents->map(fn (User $u) => "{$u->name} (#{$u->id})")->implode(', '));

        if ($candidates->isEmpty()) {
            $this->info('0 candidate appointments found. Everything is already properly attributed.');

            return self::SUCCESS;
        }

        $tableRows = [];
        $auditItems = [];

        foreach ($candidates as $appt) {
            $creatorId = (int) $appt->created_by;
            /** @var User|null $creator */
            $creator = $activeAgents->get($creatorId);
            $creatorLabel = $creator ? "{$creator->name} (#{$creatorId})" : "#{$creatorId}";
            $currentType = $hasTypeColumn ? ($appt->booking_agent_type ?? 'NULL') : 'N/A';
            $createdQatar = AgentBookingQuotaCalculator::createdAtQatar($appt)?->format('Y-m-d H:i:s')
                ?? (string) $appt->getRawOriginal('created_at');

            $tableRows[] = [
                $appt->id,
                $createdQatar,
                (string) ($appt->customer_name ?? '—'),
                $creatorLabel,
                'NULL',
                (string) $currentType,
                (string) $creatorId,
                Appointment::BOOKING_AGENT_TYPE_AGENT,
            ];

            $auditItems[] = [
                'appointment_id' => (int) $appt->id,
                'created_at' => $createdQatar,
                'customer_name' => (string) ($appt->customer_name ?? ''),
                'created_by' => $creatorId,
                'old_booking_agent_id' => null,
                'old_booking_agent_type' => $hasTypeColumn ? $appt->booking_agent_type : null,
                'new_booking_agent_id' => $creatorId,
                'new_booking_agent_type' => Appointment::BOOKING_AGENT_TYPE_AGENT,
            ];
        }

        $this->table(
            ['ID', 'Created At (Qatar)', 'Customer', 'Created By', 'Old Agent ID', 'Old Type', 'New Agent ID', 'New Type'],
            $tableRows
        );

        if (! $isApplyMode) {
            $this->warn("Dry-run complete: {$candidates->count()} candidate appointment(s) would be updated. Re-run with --apply to persist.");

            return self::SUCCESS;
        }

        $updatedIds = [];

        DB::transaction(function () use ($candidates, $hasTypeColumn, &$updatedIds): void {
            foreach ($candidates as $appt) {
                $updatePayload = [
                    'booking_agent_id' => (int) $appt->created_by,
                ];
                if ($hasTypeColumn) {
                    $updatePayload['booking_agent_type'] = Appointment::BOOKING_AGENT_TYPE_AGENT;
                }

                $affected = DB::table('appointments')
                    ->where('id', $appt->id)
                    ->whereNull('booking_agent_id')
                    ->update($updatePayload);

                if ($affected > 0) {
                    $updatedIds[] = (int) $appt->id;
                }
            }
        });

        Log::info('appointments:backfill-agent-attribution applied', [
            'from' => $rawFrom !== '' ? $rawFrom : null,
            'updated_count' => count($updatedIds),
            'updated_ids' => $updatedIds,
            'changes' => $auditItems,
        ]);

        $this->info(sprintf(
            'Backfill applied cleanly inside transaction: %d appointment(s) updated. IDs: [%s]',
            count($updatedIds),
            implode(', ', $updatedIds)
        ));

        return self::SUCCESS;
    }
}
