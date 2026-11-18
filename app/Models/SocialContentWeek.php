<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SocialContentWeek extends Model
{
    protected $fillable = [
        'week_start_date',
        'strategy_objective',
        'pro_tips',
        'hashtags',
        'source_pdf_name',
        'uploaded_at',
        'uploaded_by',
    ];

    protected $casts = [
        'pro_tips' => 'array',
        'uploaded_at' => 'datetime',
    ];

    // week_start_date is intentionally kept uncast as a bare 'Y-m-d' string
    // so SQLite and MySQL equality lookups match SocialContentPlan seamlessly.

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * Return clean [{ title, body }] pro-tip entries regardless of raw JSON shape.
     *
     * @return array<int, array{title: string, body: string}>
     */
    public function normalizedProTips(): array
    {
        $raw = $this->pro_tips;
        if (is_string($raw) && trim($raw) !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $raw = $decoded;
            } else {
                return [
                    ['title' => 'Pro-Tip', 'body' => trim($raw)],
                ];
            }
        }

        if (!is_array($raw)) {
            return [];
        }

        $items = [];
        foreach ($raw as $entry) {
            if (is_string($entry) && trim($entry) !== '') {
                $items[] = [
                    'title' => 'Pro-Tip',
                    'body' => trim($entry),
                ];
                continue;
            }

            if (is_array($entry)) {
                $title = trim((string) ($entry['title'] ?? $entry['heading'] ?? ''));
                $body = trim((string) ($entry['body'] ?? $entry['description'] ?? $entry['text'] ?? ''));
                if ($title !== '' || $body !== '') {
                    $items[] = [
                        'title' => $title !== '' ? $title : 'Pro-Tip',
                        'body' => $body,
                    ];
                }
            }
        }

        return $items;
    }

    public function hasAnyMetadata(): bool
    {
        return filled($this->strategy_objective)
            || !empty($this->normalizedProTips())
            || filled($this->hashtags);
    }
}
