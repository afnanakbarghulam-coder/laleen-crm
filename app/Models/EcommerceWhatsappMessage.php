<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\QueryException;

class EcommerceWhatsappMessage extends Model
{
    use HasFactory;

    protected $table = 'ecommerce_whatsapp_messages';

    public const DIRECTIONS = [
        'outbound',
        'inbound',
    ];

    public const SENDER_TYPES = [
        'ai',
        'staff',
        'customer',
        'system_event',
    ];

    public const DELIVERY_STATUSES = [
        'pending_approval',
        'sent',
        'delivered',
        'failed',
    ];

    protected $fillable = [
        'thread_id',
        'direction',
        'sender_type',
        'delivery_status',
        'message_body',
        'ai_metadata',
        'external_message_id',
    ];

    protected $casts = [
        'ai_metadata' => 'array',
    ];

    public function thread(): BelongsTo
    {
        return $this->belongsTo(EcommerceWhatsappThread::class, 'thread_id');
    }

    /**
     * Render clean HTML for the Blade chat timeline:
     * - Replaces raw 36-char UUIDs with short `#D2871B` references
     * - Escapes HTML safely
     * - Converts `**bold**` and `*bold*` into `<strong>bold</strong>`
     */
    public function formattedHtmlBody(): string
    {
        $text = (string) $this->message_body;

        // Replace any raw 36-char UUIDs in the text with #XXXXXX
        $text = (string) preg_replace_callback(
            '/\b([0-9a-f]{8})-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\b/i',
            fn (array $m) => '#' . strtoupper(substr($m[1], 0, 6)),
            $text
        );

        $escaped = e($text);

        // Convert **bold** first, then single *bold*
        $escaped = (string) preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $escaped);
        $escaped = (string) preg_replace('/\*([^\*\n]+?)\*/', '<strong>$1</strong>', $escaped);

        // Convert https:// URLs into clickable links
        $escaped = (string) preg_replace(
            '#(https?://[^\s<]+)#i',
            '<a href="$1" target="_blank" rel="noopener" style="color: #deb97c; text-decoration: underline;">$1</a>',
            $escaped
        );

        return nl2br($escaped);
    }

    /**
     * Convert any double-asterisks `**bold**` into WhatsApp native single-asterisk `*bold*`
     * when dispatching outbound messages.
     */
    public static function toWhatsappNativeFormatting(string $text): string
    {
        return (string) preg_replace('/\*\*(.+?)\*\*/s', '*$1*', $text);
    }

    /**
     * Convert 4-byte UTF-8 emojis (U+10000..U+10FFFF) into safe 3-byte BMP symbols
     * (`✨`, `⚡`, `✓`, `•`, `★`, `↩`, `⚠`) so messages always save cleanly even if a
     * legacy MySQL connection or table is still using 3-byte `utf8mb3`.
     */
    public static function sanitizeForLegacyMysql(string $text): string
    {
        if ($text === '') {
            return '';
        }

        $replacements = [
            '💬' => '✨',
            '🎉' => '✨',
            '🥳' => '✨',
            '🎊' => '✨',
            '🛍️' => '★',
            '🛍' => '★',
            '🛒' => '★',
            '📦' => '★',
            '🔗' => '•',
            '📍' => '•',
            '🔄' => '↩',
            '↩️' => '↩',
            '🚨' => '⚠',
            '🛑' => '⚠',
            '🔕' => '⚠',
            '🧹' => '✓',
            '🛡️' => '★',
            '🛡' => '★',
            '👤' => '•',
            '👩‍💼' => '✨',
            '🤖' => '✨',
            '👋' => '✨',
            '🙏' => '✨',
        ];

        $sanitized = strtr($text, $replacements);

        // Strip any remaining 4-byte UTF-8 astral plane codepoints ([\x{10000}-\x{10FFFF}])
        $sanitized = (string) preg_replace('/[\x{10000}-\x{10FFFF}]/u', '', $sanitized);

        return $sanitized;
    }

    /**
     * Recursively sanitize a string or array for legacy 3-byte MySQL tables.
     */
    public static function sanitizeValueForLegacyMysql(mixed $value): mixed
    {
        if (is_string($value)) {
            return self::sanitizeForLegacyMysql($value);
        }

        if (is_array($value)) {
            $cleaned = [];
            foreach ($value as $k => $v) {
                $cleanKey = is_string($k) ? self::sanitizeForLegacyMysql($k) : $k;
                $cleaned[$cleanKey] = self::sanitizeValueForLegacyMysql($v);
            }

            return $cleaned;
        }

        return $value;
    }

    /**
     * Detect MySQL 1366 / HY000 "Incorrect string value" errors caused by 4-byte UTF-8 characters on utf8mb3 columns.
     */
    public static function isLegacyCharsetError(\Throwable $e): bool
    {
        $msg = $e->getMessage();

        return (string) $e->getCode() === 'HY000'
            || str_contains($msg, '1366')
            || stripos($msg, 'Incorrect string value') !== false;
    }

    /**
     * Sanitize all string/JSON attributes on an Eloquent model in-place when retrying after a 1366 error.
     */
    public static function sanitizeModelAttributesForLegacyMysql(Model $model): void
    {
        foreach ($model->getAttributes() as $key => $val) {
            if (is_string($val)) {
                $model->setRawAttributes(array_merge(
                    $model->getAttributes(),
                    [$key => self::sanitizeForLegacyMysql($val)]
                ));
            }
        }
    }

    /**
     * Try saving with full native emojis first; if legacy MySQL utf8mb3 throws 1366,
     * automatically sanitize 4-byte characters into BMP symbols and retry immediately.
     */
    public function save(array $options = []): bool
    {
        try {
            return parent::save($options);
        } catch (QueryException $e) {
            if (self::isLegacyCharsetError($e)) {
                self::sanitizeModelAttributesForLegacyMysql($this);

                return parent::save($options);
            }

            throw $e;
        }
    }
}
