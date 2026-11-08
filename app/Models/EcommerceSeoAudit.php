<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EcommerceSeoAudit extends Model
{
    protected $table = 'ecommerce_seo_audits';

    protected $fillable = [
        'region',
        'business_line',
        'target_type',
        'ecommerce_product_id',
        'target_name',
        'target_url_slug',
        'primary_keyword',
        'secondary_entities',
        'seo_score',
        'meta_title',
        'meta_description',
        'h1_heading',
        'image_alt_text',
        'image_filename',
        'optimized_body_html',
        'faq_json',
        'json_ld_schema',
        'audit_checklist_json',
        'shopify_sync_status',
        'shopify_published_at',
    ];

    protected $casts = [
        'secondary_entities' => 'array',
        'seo_score' => 'integer',
        'faq_json' => 'array',
        'audit_checklist_json' => 'array',
        'shopify_published_at' => 'datetime',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(EcommerceProduct::class, 'ecommerce_product_id');
    }
}
