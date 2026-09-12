<?php

namespace App\NovaAI\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Lives on the isolated 'nova_memory' connection - never the CRM's own
 * connection. See app/NovaAI/README.md's NOVA READ-ONLY INTEGRATION RULE.
 * Populated only via App\NovaAI\Services\NovaDecisionExtractor + recorded
 * through App\NovaAI\Services\NovaDecisionService - never written
 * directly from a controller, and never from a Nova recommendation alone.
 */
class NovaExperiment extends Model
{
    protected $connection = 'nova_memory';

    protected $table = 'nova_experiments';

    protected $fillable = [
        'title',
        'description',
        'category',
        'status',
        'started_at',
        'ends_at',
        'target_metric',
        'success_criteria',
        'review_date',
        'result_summary',
        'decided_by_user_id',
        'source_message',
    ];

    protected $casts = [
        'started_at' => 'date',
        'ends_at' => 'date',
        'review_date' => 'date',
    ];
}
