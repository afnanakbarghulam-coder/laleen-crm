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

    /**
     * Categories staff can pick by hand when adding/editing a lead.
     * No-show and Cancel are excluded on purpose - those are set
     * automatically when an appointment is marked No Show or Cancelled
     * on the Enhanced Calendar (see AppointmentController::updateStatus()),
     * never chosen manually. They still appear in CATEGORIES above for
     * filtering, badges, and analytics.
     */
    const MANUAL_CATEGORIES = [
        'follow_up' => 'Follow up',
        'inquiry' => 'Inquiry',
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
        'appointment_id',
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
     * The specific appointment that auto-generated this lead (No Show/Cancel
     * only - manually created leads never have this set). Null for those.
     */
    public function appointment()
    {
        return $this->belongsTo(Appointment::class);
    }

    /**
     * Combine a country code + local number into the digits-only phone format
     * used everywhere else in the app (Customers, Appointments, Calendar all
     * store bare local numbers with no country code - e.g. "55532347", not
     * "97455532347"). Qatar is the default/legacy convention, so it's left
     * unprefixed to match every existing client record; only a non-Qatar code
     * is actually prepended, since there's no existing data to conflict with
     * there.
     */
    public static function normalizePhone(?string $countryCode, ?string $number): string
    {
        $digits = preg_replace('/\D+/', '', (string) $number);

        if ($countryCode && $countryCode !== '974') {
            $digits = preg_replace('/\D+/', '', $countryCode) . $digits;
        }

        return $digits;
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
