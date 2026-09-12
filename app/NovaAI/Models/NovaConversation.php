<?php

namespace App\NovaAI\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Lives on the isolated 'nova_memory' connection - never the CRM's own
 * connection. See app/NovaAI/README.md's NOVA READ-ONLY INTEGRATION RULE.
 */
class NovaConversation extends Model
{
    use HasUuids;

    protected $connection = 'nova_memory';

    protected $table = 'nova_conversations';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'owner_user_id',
        'started_at',
        'last_active_at',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'last_active_at' => 'datetime',
    ];

    public function messages()
    {
        return $this->hasMany(NovaMessage::class, 'conversation_id');
    }
}
