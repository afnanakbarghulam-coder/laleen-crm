<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ServiceFreebiePackage extends Model
{
    protected $fillable = [
        'name',
        'anchor_service_id',
        'price',
        'free_choices_count',
        'validity_days',
        'is_active',
    ];

    protected $casts = [
        'anchor_service_id' => 'integer',
        'price' => 'float',
        'free_choices_count' => 'integer',
        'validity_days' => 'integer',
        'is_active' => 'boolean',
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function anchorService()
    {
        return $this->belongsTo(Service::class, 'anchor_service_id');
    }

    public function eligibleServices()
    {
        return $this->belongsToMany(Service::class, 'service_freebie_package_items', 'package_id', 'service_id')
            ->withTimestamps()
            ->orderBy('services.name');
    }

    public function credits()
    {
        return $this->hasMany(ClientFreebieCredit::class, 'package_id');
    }

    public function appointments()
    {
        return $this->hasMany(Appointment::class, 'freebie_package_id');
    }

    public function getEffectiveValidityDaysAttribute(): int
    {
        $days = (int) ($this->validity_days ?: 7);

        return $days > 0 ? $days : 7;
    }

    public function getEffectiveChoicesCountAttribute(): int
    {
        $count = (int) ($this->free_choices_count ?: 2);

        return $count > 0 ? $count : 2;
    }
}
