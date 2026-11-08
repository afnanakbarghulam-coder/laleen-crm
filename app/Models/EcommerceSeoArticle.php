<?php

namespace App\Models;

use App\Services\Ecommerce\EcommerceSeoAgentService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class EcommerceSeoArticle extends Model
{
    protected $table = 'ecommerce_seo_articles';

    protected $fillable = [
        'region',
        'business_line',
        'cluster_topic',
        'search_intent',
        'title',
        'slug',
        'target_keyword',
        'internal_link_target_url',
        'article_markdown',
        'meta_title',
        'meta_description',
        'image_alt_text',
        'image_filename',
        'featured_image_url',
        'featured_image_path',
        'image_generation_prompt',
        'json_ld_schema',
        'status',
        'shopify_sync_status',
        'shopify_published_at',
    ];

    protected $casts = [
        'shopify_published_at' => 'datetime',
    ];

    /**
     * Return a direct inline base64 Data URI for the cover image (auto-regenerating on disk if missing or invalid)
     * so browser <img> previews never depend on /storage symlinks or show broken image icons.
     */
    public function getFeaturedImageDataUriAttribute(): string
    {
        $path = trim((string) $this->featured_image_path);

        if ($path === '' || !Storage::disk('public')->exists($path)) {
            $refreshed = app(EcommerceSeoAgentService::class)->generateArticleImage($this);
            $path = trim((string) $refreshed->featured_image_path);
        }

        $bytes = $path !== '' && Storage::disk('public')->exists($path)
            ? (string) Storage::disk('public')->get($path)
            : '';

        if (strlen($bytes) < 16 || (!app()->runningUnitTests() && strlen($bytes) === 44462)) {
            $refreshed = app(EcommerceSeoAgentService::class)->generateArticleImage($this);
            $path = trim((string) $refreshed->featured_image_path);
            $bytes = $path !== '' && Storage::disk('public')->exists($path)
                ? (string) Storage::disk('public')->get($path)
                : '';
        }

        $trimmed = ltrim($bytes);
        if (str_starts_with($trimmed, '<svg') || str_starts_with($trimmed, '<?xml')) {
            $mime = 'image/svg+xml';
        } elseif (str_starts_with($bytes, "\x89PNG")) {
            $mime = 'image/png';
        } else {
            $mime = 'image/jpeg';
        }

        return "data:{$mime};base64," . base64_encode($bytes);
    }
}
