<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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
        'json_ld_schema',
        'status',
        'shopify_sync_status',
        'shopify_published_at',
    ];

    protected $casts = [
        'shopify_published_at' => 'datetime',
    ];
}
