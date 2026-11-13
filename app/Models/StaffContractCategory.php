<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class StaffContractCategory extends Model
{
    protected $fillable = [
        'slug',
        'label',
        'default_currency',
        'default_branch',
        'clauses',
        'created_by',
    ];

    public static function generateUniqueSlug(string $label): string
    {
        $base = Str::slug($label, '_');
        if ($base === '' || in_array($base, array_keys(StaffContract::TEMPLATES), true) || $base === '__new_category__') {
            $base = 'custom_' . ($base ?: 'category');
        }
        $base = substr($base, 0, 44);

        $candidate = $base;
        $seq = 2;
        while (
            isset(StaffContract::TEMPLATES[$candidate])
            || self::where('slug', $candidate)->exists()
        ) {
            $candidate = $base . '_' . $seq;
            $seq++;
        }

        return $candidate;
    }

    public function toTemplateArray(): array
    {
        $currency = strtoupper((string) ($this->default_currency ?: 'QAR'));

        return [
            'label' => $this->label,
            'default_role' => $this->label,
            'default_currency' => in_array($currency, StaffContract::CURRENCIES, true) ? $currency : 'QAR',
            'allowed_currencies' => StaffContract::CURRENCIES,
            'default_branch' => $this->default_branch ?: ($currency === 'PKR' ? 'remote' : 'old_airport'),
            'summary' => "{$currency} monthly compensation + customized operational clauses",
            'clauses' => $this->clauses,
            'is_custom' => true,
        ];
    }
}
