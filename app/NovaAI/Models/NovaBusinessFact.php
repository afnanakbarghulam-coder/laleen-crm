<?php

namespace App\NovaAI\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Lives on the isolated 'nova_memory' connection - never the CRM's own
 * connection. See app/NovaAI/README.md's NOVA READ-ONLY INTEGRATION RULE.
 * Populated only via App\NovaAI\Services\NovaFactExtractor + recorded
 * through App\NovaAI\Services\NovaBusinessFactService - never written
 * directly from a controller or from CRM data.
 */
class NovaBusinessFact extends Model
{
    protected $connection = 'nova_memory';

    protected $table = 'nova_business_facts';

    protected $fillable = [
        'category',
        'normalized_key',
        'value',
        'status',
        'source_message',
        'stated_by_user_id',
        'superseded_by_id',
    ];
}
