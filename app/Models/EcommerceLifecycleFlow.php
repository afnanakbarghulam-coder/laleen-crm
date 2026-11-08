<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EcommerceLifecycleFlow extends Model
{
    use HasFactory;

    protected $table = 'ecommerce_lifecycle_flows';

    protected $fillable = [
        'region',
        'flow_key',
        'name',
        'channel',
        'trigger_day',
        'is_enabled',
        'sent_count',
        'converted_count',
        'last_ran_at',
    ];

    protected $casts = [
        'trigger_day' => 'integer',
        'is_enabled' => 'boolean',
        'sent_count' => 'integer',
        'converted_count' => 'integer',
        'last_ran_at' => 'datetime',
    ];
}
