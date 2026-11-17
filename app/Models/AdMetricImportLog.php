<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdMetricImportLog extends Model
{
    protected $fillable = [
        'workspace',
        'region',
        'branch',
        'date_from',
        'date_to',
        'account_currency',
        'fx_rate_to_local',
        'local_currency',
        'screenshot_paths',
        'raw_extracted_json',
        'total_results_json',
        'verification_json',
        'ai_provider',
        'campaigns_count',
        'status',
        'created_by',
    ];

    protected $casts = [
        'date_from' => 'date:Y-m-d',
        'date_to' => 'date:Y-m-d',
        'fx_rate_to_local' => 'float',
        'screenshot_paths' => 'array',
        'raw_extracted_json' => 'array',
        'total_results_json' => 'array',
        'verification_json' => 'array',
        'campaigns_count' => 'integer',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function campaignMetrics()
    {
        return $this->hasMany(AdCampaignMetric::class, 'last_import_log_id');
    }
}
