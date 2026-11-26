<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LegalTask extends Model
{
    use HasFactory;

    public const STATUS_UNDER_PROCESS = 'under_process';
    public const STATUS_HOLD = 'hold';
    public const STATUS_PENDING = 'pending';
    public const STATUS_COMPLETED = 'completed';

    public const STATUSES = [
        self::STATUS_UNDER_PROCESS => 'Under Process',
        self::STATUS_HOLD => 'Hold',
        self::STATUS_PENDING => 'Pending',
        self::STATUS_COMPLETED => 'Completed',
    ];

    protected $fillable = [
        'serial_number',
        'nature_of_work',
        'title',
        'description',
        'deadline',
        'status',
        'completed_at',
        'remarks',
        'why_pending',
        'person_responsible',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'serial_number' => 'integer',
            'deadline' => 'date',
            'completed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (LegalTask $task) {
            if ($task->serial_number === null) {
                $task->serial_number = ((int) static::max('serial_number')) + 1;
            }
            if (empty($task->status)) {
                $task->status = self::STATUS_PENDING;
            }
        });

        static::saving(function (LegalTask $task) {
            $work = trim((string) ($task->nature_of_work ?? ''));
            $title = trim((string) ($task->title ?? ''));

            if ($work === '' && $title !== '') {
                $task->nature_of_work = $title;
            } elseif ($title === '' && $work !== '') {
                $task->title = $work;
            }

            if ($task->status === self::STATUS_COMPLETED) {
                if ($task->completed_at === null) {
                    $task->completed_at = now();
                }
            } elseif ($task->completed_at !== null) {
                $task->completed_at = null;
            }
        });
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function displayWorkTitle(): string
    {
        return trim((string) ($this->nature_of_work ?: $this->title ?: 'Untitled Work Item'));
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? ucwords(str_replace('_', ' ', (string) $this->status));
    }

    /**
     * @return array{label: string, tone: string}
     */
    public function statusBadge(): array
    {
        return match ($this->status) {
            self::STATUS_UNDER_PROCESS => ['label' => 'Under Process', 'tone' => 'champagne'],
            self::STATUS_HOLD => ['label' => 'Hold', 'tone' => 'amber'],
            self::STATUS_COMPLETED => ['label' => 'Completed', 'tone' => 'emerald'],
            default => ['label' => 'Pending', 'tone' => 'danger'],
        };
    }
}
