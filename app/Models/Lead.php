<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Lead extends Model
{
    const CATEGORIES = [
        'follow_up' => 'Follow up',
        'inquiry' => 'Inquiry',
        'no_show' => 'No-show',
        'cancel' => 'Cancel',
    ];

    const NEEDFUL_STATUSES = [
        'yes' => 'Yes',
        'no' => 'No',
    ];

    const COUNTRY_CODES = [
        '974' => 'Qatar',
        '91' => 'India',
        '971' => 'UAE',
        '966' => 'Saudi Arabia',
        '968' => 'Oman',
        '973' => 'Bahrain',
        '965' => 'Kuwait',
        '20' => 'Egypt',
        '63' => 'Philippines',
        '94' => 'Sri Lanka',
        '92' => 'Pakistan',
        '44' => 'UK',
    ];

    protected $fillable = [
        'phone',
        'customer_id',
        'assigned_agent_id',
        'category',
        'customer_remarks',
        'service_interest',
        'needful_done',
        'next_followup_date',
    ];

    protected $casts = [
        'next_followup_date' => 'date',
    ];

    public function agent()
    {
        return $this->belongsTo(User::class, 'assigned_agent_id');
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Split a stored digits-only phone number into [country_code, local_number]
     * for pre-filling the country code select + number input on edit. Falls
     * back to Qatar with the full number if no known code matches.
     */
    public static function splitPhone(?string $phone): array
    {
        $phone = (string) $phone;
        $codes = array_keys(self::COUNTRY_CODES);
        usort($codes, fn($a, $b) => strlen($b) - strlen($a));

        foreach ($codes as $code) {
            if (str_starts_with($phone, $code)) {
                return [$code, substr($phone, strlen($code))];
            }
        }

        return ['974', $phone];
    }
}
