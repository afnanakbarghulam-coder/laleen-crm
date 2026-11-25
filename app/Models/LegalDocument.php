<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class LegalDocument extends Model
{
    use HasFactory;

    public const SCOPES = [
        'old_airport' => 'Old Airport Branch',
        'al_wakrah' => 'Al Wakrah Branch',
        'company' => 'Company / Head Office',
    ];

    public const CATEGORIES = [
        'commercial_registration' => 'Commercial Registration (CR)',
        'trade_license' => 'Trade License / Baladiya',
        'civil_defense' => 'Civil Defense Certificate',
        'ministry_approval' => 'Ministry Approval',
        'lease_agreement' => 'Lease / Tenancy Agreement',
        'other' => 'Other Legal Document',
    ];

    protected $table = 'legal_documents';

    protected $fillable = [
        'scope',
        'category',
        'title',
        'document_number',
        'issue_date',
        'expiry_date',
        'notes',
        'file_path',
        'original_filename',
        'mime_type',
        'file_size',
        'uploaded_by',
    ];

    protected $casts = [
        'issue_date' => 'date',
        'expiry_date' => 'date',
        'file_size' => 'integer',
    ];

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function scopeLabel(): string
    {
        return self::SCOPES[$this->scope] ?? ucwords(str_replace('_', ' ', (string) $this->scope));
    }

    public function categoryLabel(): string
    {
        return self::CATEGORIES[$this->category] ?? ucwords(str_replace('_', ' ', (string) $this->category));
    }

    /**
     * @return array{key: string, label: string, tone: string, days_remaining: ?int}
     */
    public function expiryStatus(?Carbon $todayQatar = null): array
    {
        if (!$this->expiry_date) {
            return [
                'key' => 'none',
                'label' => 'No Expiry',
                'tone' => 'muted',
                'days_remaining' => null,
            ];
        }

        $today = ($todayQatar ?: Carbon::now('Asia/Qatar'))->copy()->startOfDay();
        $expiry = $this->expiry_date->copy()->startOfDay();
        $days = (int) $today->diffInDays($expiry, false);

        if ($days < 0) {
            return [
                'key' => 'expired',
                'label' => 'Expired',
                'tone' => 'danger',
                'days_remaining' => $days,
            ];
        }

        if ($days <= 30) {
            return [
                'key' => 'expiring_soon',
                'label' => 'Expiring Soon',
                'tone' => 'amber',
                'days_remaining' => $days,
            ];
        }

        return [
            'key' => 'valid',
            'label' => 'Valid',
            'tone' => 'emerald',
            'days_remaining' => $days,
        ];
    }

    public function formattedFileSize(): string
    {
        $bytes = (int) $this->file_size;
        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 1) . ' MB';
        }
        if ($bytes >= 1024) {
            return round($bytes / 1024, 1) . ' KB';
        }

        return $bytes . ' B';
    }
}
