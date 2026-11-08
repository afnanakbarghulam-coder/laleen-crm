<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EcommerceSeoSearchQuery extends Model
{
    protected $table = 'ecommerce_seo_search_queries';

    protected $fillable = [
        'region',
        'business_unit',
        'search_query',
        'impressions',
        'clicks',
        'ctr_percent',
        'avg_position',
        'target_url',
        'opportunity_type',
        'linked_article_id',
        'last_synced_at',
    ];

    protected $casts = [
        'impressions' => 'integer',
        'clicks' => 'integer',
        'ctr_percent' => 'float',
        'avg_position' => 'float',
        'last_synced_at' => 'datetime',
    ];

    public function linkedArticle(): BelongsTo
    {
        return $this->belongsTo(EcommerceSeoArticle::class, 'linked_article_id');
    }
}
