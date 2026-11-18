<?php

namespace App\Models\Concerns;

trait HasMonthlyAdCreatives
{
    public const CREATIVE_TYPES = [
        'reel'  => 'Reel',
        'story' => 'Story',
        'post'  => 'Post',
    ];

    public const MEDIA_TYPES = [
        'video' => 'Video',
        'image' => 'Image',
    ];

    public const CREATIVE_SIZES = [
        '9:16' => '9:16 (1080×1920)',
        '4:5'  => '4:5 (1080×1350)',
        '1:1'  => '1:1 (1080×1080)',
        '16:9' => '16:9 (1920×1080)',
    ];

    public const BOARD_PHASE_LABELS = [
        'Planning' => 'Planning & Production',
        'Testing'  => 'Testing',
        'Scaling'  => 'Scaling',
    ];

    protected static function bootHasMonthlyAdCreatives(): void
    {
        static::saving(function ($model) {
            $model->phase = static::normalizePhase($model->phase);
        });
    }

    public static function normalizePhase(?string $phase): string
    {
        $clean = strtolower(trim((string) $phase));

        return match ($clean) {
            '', 'planning', 'production', 'planning_production', 'planning & production' => 'Planning',
            'testing' => 'Testing',
            'scaling' => 'Scaling',
            'killed'  => 'Killed',
            default   => 'Planning',
        };
    }

    public function normalizedPhase(): string
    {
        return static::normalizePhase($this->phase);
    }

    public function phaseLabel(): string
    {
        $norm = $this->normalizedPhase();

        return self::BOARD_PHASE_LABELS[$norm] ?? $norm;
    }

    public static function defaultSizeForType(string $creativeType): string
    {
        return strtolower(trim($creativeType)) === 'post' ? '4:5' : '9:16';
    }

    /**
     * Normalize raw creative item array into canonical structure.
     *
     * @param  array<string, mixed>  $raw
     * @return array<string, mixed>
     */
    public static function normalizeCreativeItem(array $raw, int $position): array
    {
        $type = strtolower(trim((string) ($raw['creative_type'] ?? 'reel')));
        if (!array_key_exists($type, self::CREATIVE_TYPES)) {
            $type = 'reel';
        }

        $media = strtolower(trim((string) ($raw['media_type'] ?? 'video')));
        if (!array_key_exists($media, self::MEDIA_TYPES)) {
            $media = 'video';
        }

        $defaultSize = static::defaultSizeForType($type);
        $size = trim((string) ($raw['size'] ?? $defaultSize));
        if (!array_key_exists($size, self::CREATIVE_SIZES)) {
            $size = $defaultSize;
        }

        $refUrl = trim((string) ($raw['reference_url'] ?? $raw['asset_link'] ?? ''));
        $name = trim((string) ($raw['name'] ?? ''));

        return [
            'index'         => $position,
            'label'         => "Creative {$position}",
            'short_label'   => "C{$position}",
            'name'          => $name,
            'creative_type' => $type,
            'type_label'    => self::CREATIVE_TYPES[$type],
            'media_type'    => $media,
            'media_label'   => self::MEDIA_TYPES[$media],
            'size'          => $size,
            'size_label'    => self::CREATIVE_SIZES[$size],
            'reference_url' => $refUrl !== '' ? $refUrl : null,
        ];
    }

    /**
     * Return 1..N normalized creatives from JSON `creatives`, falling back seamlessly
     * to legacy `creative_1_name..creative_3_name` columns when JSON is empty.
     *
     * @return array<int, array<string, mixed>>
     */
    public function resolvedCreatives(): array
    {
        $stored = $this->creatives;
        if (is_string($stored) && $stored !== '') {
            $decoded = json_decode($stored, true);
            $stored = is_array($decoded) ? $decoded : null;
        }

        if (is_array($stored) && !empty($stored)) {
            $items = [];
            $pos = 1;
            foreach ($stored as $raw) {
                if (!is_array($raw)) {
                    continue;
                }
                $norm = static::normalizeCreativeItem($raw, $pos);
                if ($norm['name'] !== '' || $norm['reference_url'] !== null) {
                    $items[] = $norm;
                    $pos++;
                }
            }
            if (!empty($items)) {
                return $items;
            }
        }

        // Backward-compatible fallback for legacy 3-column creative records
        $legacyPairs = [
            ['name' => $this->creative_1_name ?? null, 'reference_url' => $this->creative_asset_link ?? null],
            ['name' => $this->creative_2_name ?? null, 'reference_url' => $this->creative_asset_link_2 ?? null],
            ['name' => $this->creative_3_name ?? null, 'reference_url' => $this->creative_asset_link_3 ?? null],
        ];

        $fallback = [];
        $pos = 1;
        foreach ($legacyPairs as $pair) {
            $hasName = trim((string) ($pair['name'] ?? '')) !== '';
            $hasUrl = trim((string) ($pair['reference_url'] ?? '')) !== '';
            if ($hasName || $hasUrl) {
                if (!$hasName) {
                    $pair['name'] = "Creative {$pos}";
                }
                $fallback[] = static::normalizeCreativeItem($pair, $pos);
                $pos++;
            }
        }

        return $fallback;
    }

    /**
     * Resolve details for the selected winning creative (`Creative 1`..`Creative N`).
     *
     * @return array{label: string|null, name: string|null, reference_url: string|null}
     */
    public function winningCreativeDetails(): array
    {
        $win = trim((string) ($this->winning_creative ?? ''));
        if ($win === '' || strcasecmp($win, 'None') === 0) {
            return ['label' => $win !== '' ? $win : null, 'name' => null, 'reference_url' => null];
        }

        foreach ($this->resolvedCreatives() as $item) {
            if (strcasecmp($item['label'], $win) === 0 || strcasecmp($item['short_label'], $win) === 0) {
                return [
                    'label'         => $item['label'],
                    'name'          => $item['name'] ?: null,
                    'reference_url' => $item['reference_url'] ?? null,
                ];
            }
        }

        return ['label' => $win, 'name' => null, 'reference_url' => null];
    }

    /**
     * Sync legacy `creative_1..3` columns from normalized `creatives` array for backward compatibility.
     *
     * @param  array<int, array<string, mixed>>  $creatives
     * @return array<string, mixed>
     */
    public static function legacyCreativeColumnsFromList(array $creatives): array
    {
        return [
            'creative_1_name'       => $creatives[0]['name'] ?? null,
            'creative_asset_link'   => $creatives[0]['reference_url'] ?? null,
            'creative_2_name'       => $creatives[1]['name'] ?? null,
            'creative_asset_link_2' => $creatives[1]['reference_url'] ?? null,
            'creative_3_name'       => $creatives[2]['name'] ?? null,
            'creative_asset_link_3' => $creatives[2]['reference_url'] ?? null,
        ];
    }
}
