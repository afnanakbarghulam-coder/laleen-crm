<?php

namespace App\NovaAI\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Lives on the isolated 'nova_memory' connection - never the CRM's own
 * connection. See app/NovaAI/README.md's NOVA READ-ONLY INTEGRATION RULE.
 */
class NovaMessage extends Model
{
    protected $connection = 'nova_memory';

    protected $table = 'nova_messages';

    protected $fillable = [
        'conversation_id',
        'role',
        'content',
    ];

    public function conversation()
    {
        return $this->belongsTo(NovaConversation::class, 'conversation_id');
    }
}
