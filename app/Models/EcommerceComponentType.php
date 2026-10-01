<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EcommerceComponentType extends Model
{
    protected $fillable = [
        'name',
    ];

    public function rawMaterials()
    {
        return $this->hasMany(EcommerceRawMaterial::class, 'component_type_id');
    }
}
