<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EcommerceContentCalendar extends Model
{
    protected $table = 'ecommerce_content_calendar';

    public const DAYS_OF_WEEK = [
        'Monday',
        'Tuesday',
        'Wednesday',
        'Thursday',
        'Friday',
        'Saturday',
        'Sunday',
    ];

    /** The six content pillars the weekly factory rotates through. */
    public const CONTENT_TYPES = [
        'Transformation',
        'Mechanism',
        'Bundle',
        'Behind-the-Scenes',
        'Social Proof',
        'Direct Response',
    ];

    public const STATUSES = [
        'Scripted',
        'Filmed',
        'Live on Shopify/Meta',
    ];

    /** Suggested default pillar per day, used to seed an empty week. */
    public const DEFAULT_PILLAR_BY_DAY = [
        'Monday' => 'Transformation',
        'Tuesday' => 'Mechanism',
        'Wednesday' => 'Bundle',
        'Thursday' => 'Behind-the-Scenes',
        'Friday' => 'Social Proof',
        'Saturday' => 'Direct Response',
        'Sunday' => 'Transformation',
    ];

    protected $fillable = [
        'day_of_week',
        'content_type',
        'objective',
        'script_concept',
        'person_responsible',
        'status',
    ];

    /**
     * Payload for the edit modal's JS.
     *
     * Kept as a method rather than an inline @json([...]) in Blade — Blade's
     * directive parser truncates an array literal containing commas, which
     * silently produced malformed JSON.
     */
    public function toEditPayload(): array
    {
        return $this->only([
            'id',
            'day_of_week',
            'content_type',
            'objective',
            'script_concept',
            'person_responsible',
            'status',
        ]);
    }

    /** Sort key so Monday-first ordering survives a plain DB fetch. */
    public function dayIndex(): int
    {
        $index = array_search($this->day_of_week, self::DAYS_OF_WEEK, true);

        return $index === false ? count(self::DAYS_OF_WEEK) : $index;
    }

    public function isLive(): bool
    {
        return $this->status === 'Live on Shopify/Meta';
    }
}
