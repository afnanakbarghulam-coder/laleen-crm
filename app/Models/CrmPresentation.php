<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class CrmPresentation extends Model
{
    use HasFactory;

    protected $table = 'crm_presentations';

    public const CATEGORIES = [
        'Growth Playbook',
        'Marketing Strategy',
        'Branch SOP',
        'Laleen Rituals',
    ];

    public const LAYOUTS = [
        'cover' => 'Cover — Serif Headline & Subtitle',
        'big_metric' => 'Big Metric — Hero Figure & Takeaway',
        'timeline' => 'Timeline — Horizontal Step Cards (3–5)',
        'comparison' => 'Comparison — 2-Column Side-by-Side',
        'cards' => 'Cards — 3 or 4 Strategy Grid Tiles',
        'table' => 'Table — Executive KPI & Ownership Matrix',
    ];

    public const LEGACY_LAYOUT_ALIASES = [
        'hero' => 'cover',
        'closing' => 'cover',
        'metric' => 'big_metric',
        'funnel' => 'timeline',
        'checklist' => 'cards',
        'matrix' => 'table',
    ];

    protected $fillable = [
        'title',
        'slug',
        'category',
        'summary',
        'slides',
        'is_pinned',
        'created_by',
    ];

    protected $casts = [
        'slides' => 'array',
        'is_pinned' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (CrmPresentation $presentation) {
            if (empty($presentation->slug)) {
                $presentation->slug = static::generateUniqueSlug($presentation->title);
            }
            if (empty($presentation->category) || !in_array($presentation->category, self::CATEGORIES, true)) {
                $presentation->category = 'Growth Playbook';
            }
        });
    }

    public static function normalizeLayout(?string $raw): string
    {
        $key = strtolower(trim((string) $raw));
        if (array_key_exists($key, self::LAYOUTS)) {
            return $key;
        }
        if (isset(self::LEGACY_LAYOUT_ALIASES[$key])) {
            return self::LEGACY_LAYOUT_ALIASES[$key];
        }

        return 'cards';
    }

    public static function generateUniqueSlug(string $title, ?int $ignoreId = null): string
    {
        $base = Str::slug($title) ?: 'laleen-presentation';
        $candidate = $base;
        $counter = 2;

        while (
            static::where('slug', $candidate)
                ->when($ignoreId !== null, fn ($q) => $q->where('id', '!=', $ignoreId))
                ->exists()
        ) {
            $candidate = "{$base}-{$counter}";
            $counter++;
        }

        return $candidate;
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function slideCount(): int
    {
        return is_array($this->slides) ? count($this->slides) : 0;
    }

    /**
     * Sanitize and normalize raw slide input arrays into safe structured slide objects
     * across all 6 consulting layouts (`cover`, `big_metric`, `timeline`, `comparison`, `cards`, `table`).
     *
     * @param  array<int, mixed>  $rawSlides
     * @return list<array<string, mixed>>
     */
    public static function sanitizeSlides(array $rawSlides, array $existingSlides = []): array
    {
        $clean = [];

        foreach (array_values($rawSlides) as $index => $slide) {
            if (!is_array($slide)) {
                continue;
            }

            $title = trim(strip_tags((string) ($slide['title'] ?? '')));
            $eyebrow = trim(strip_tags((string) ($slide['eyebrow'] ?? '')));
            $subtitle = trim(strip_tags((string) ($slide['subtitle'] ?? '')));
            $highlight = trim(strip_tags((string) ($slide['highlight_metric'] ?? '')));
            $footer = trim(strip_tags((string) ($slide['footer_note'] ?? '')));
            $layout = static::normalizeLayout((string) ($slide['layout'] ?? 'cards'));

            $rawPoints = $slide['body_points'] ?? [];
            if (is_string($rawPoints)) {
                $rawPoints = preg_split("/\r\n|\n|\r/", $rawPoints) ?: [];
            }

            $bodyPoints = [];
            if (is_array($rawPoints)) {
                foreach ($rawPoints as $pt) {
                    $cleanPt = trim(strip_tags((string) $pt));
                    if ($cleanPt !== '') {
                        $bodyPoints[] = $cleanPt;
                    }
                }
            }

            if ($subtitle !== '' && empty($bodyPoints)) {
                $bodyPoints[] = $subtitle;
            } elseif ($subtitle === '' && !empty($bodyPoints)) {
                $subtitle = $bodyPoints[0];
            }

            // Parse table_rows from explicit array, headers+rows pair, or pipe-delimited table_rows_text
            $tableRows = [];
            if (array_key_exists('table_rows_text', $slide) && is_string($slide['table_rows_text'])) {
                $lines = preg_split("/\r\n|\n|\r/", $slide['table_rows_text']) ?: [];
                foreach ($lines as $line) {
                    $trimmedLine = trim($line);
                    if ($trimmedLine === '') {
                        continue;
                    }
                    $cells = array_map(
                        fn ($cell) => trim(strip_tags((string) $cell)),
                        explode('|', $trimmedLine)
                    );
                    if (count(array_filter($cells, fn ($c) => $c !== '')) > 0) {
                        $tableRows[] = $cells;
                    }
                }
            } else {
                $rawTable = [];
                if (!empty($slide['headers']) && is_array($slide['headers'])) {
                    $rawTable[] = $slide['headers'];
                    if (!empty($slide['rows']) && is_array($slide['rows'])) {
                        foreach ($slide['rows'] as $r) {
                            if (is_array($r)) {
                                $rawTable[] = $r;
                            }
                        }
                    }
                } elseif (array_key_exists('table_rows', $slide) && is_array($slide['table_rows'])) {
                    $rawTable = $slide['table_rows'];
                } elseif (isset($existingSlides[$index]['table_rows']) && is_array($existingSlides[$index]['table_rows'])) {
                    $rawTable = $existingSlides[$index]['table_rows'];
                }

                foreach ($rawTable as $row) {
                    if (is_array($row)) {
                        $cleanCells = array_map(fn ($cell) => trim(strip_tags((string) $cell)), array_values($row));
                        if (count(array_filter($cleanCells, fn ($c) => $c !== '')) > 0) {
                            $tableRows[] = $cleanCells;
                        }
                    }
                }
            }

            if ($title === '' && empty($bodyPoints) && empty($tableRows) && $highlight === '') {
                continue;
            }

            $clean[] = [
                'eyebrow' => $eyebrow !== '' ? $eyebrow : 'SLIDE ' . str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT),
                'title' => $title !== '' ? $title : 'Slide ' . ($index + 1),
                'subtitle' => $subtitle !== '' ? $subtitle : null,
                'layout' => $layout,
                'highlight_metric' => $highlight !== '' ? $highlight : null,
                'body_points' => $bodyPoints,
                'table_rows' => $tableRows,
                'headers' => !empty($tableRows) && $layout === 'table' ? $tableRows[0] : [],
                'rows' => count($tableRows) > 1 && $layout === 'table' ? array_slice($tableRows, 1) : [],
                'footer_note' => $footer !== '' ? $footer : null,
            ];
        }

        return $clean;
    }
}
