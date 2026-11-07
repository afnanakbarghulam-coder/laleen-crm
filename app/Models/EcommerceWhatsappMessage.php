<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
}
