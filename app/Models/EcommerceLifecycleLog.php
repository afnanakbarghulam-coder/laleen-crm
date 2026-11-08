<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EcommerceLifecycleLog extends Model
{
    use HasFactory;

    protected $table = 'ecommerce_lifecycle_logs';

    protected $fillable = [
        'region',
        'customer_id',
        'customer_name',
        'customer_email',
        'customer_phone',
        'flow_key',
        'channel',
        'subject',
        'message_body',
        'status',
        'sent_at',
    ];

    protected $casts = [
        'customer_id' => 'integer',
        'sent_at' => 'datetime',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(EcommerceCustomer::class, 'customer_id');
    }
}
