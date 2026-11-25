<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class StaffDocument extends Model
{
    use HasFactory;

    public const STATUS_PENDING_REVIEW = 'pending_review';
    public const STATUS_VERIFIED = 'verified';

    public const CATEGORIES = [
        'qid' => 'QID / Government ID',
        'passport' => 'Passport',
        'national_id' => 'National ID',
        'health_card' => 'Health Card',
        'medical_certificate' => 'Medical Certificate',
        'offer_letter' => 'Offer Letter',
        'signed_contract' => 'Signed Contract',
        'other' => 'Other',
    ];

    /**
     * Personal document categories permitted for employee self-upload from `/my-profile?tab=documents`.
     * HR-only contract & offer documents (`offer_letter`, `signed_contract`) are strictly blocked for staff.
     */
    public const SELF_UPLOAD_CATEGORIES = [
        'qid' => 'QID / Government ID',
        'passport' => 'Passport',
        'national_id' => 'National ID',
        'health_card' => 'Health Card',
        'medical_certificate' => 'Medical Certificate',
    ];

    protected $table = 'staff_documents';

    protected $fillable = [
        'staff_id',
        'category',
        'title',
        'document_number',
        'issue_date',
        'expiry_date',
        'file_path',
        'original_filename',
        'mime_type',
        'file_size',
        'uploaded_by',
        'verification_status',
        'verified_by',
        'verified_at',
    ];

    protected $casts = [
        'issue_date' => 'date',
        'expiry_date' => 'date',
        'file_size' => 'integer',
        'verified_at' => 'datetime',
    ];

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'staff_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function isPendingReview(): bool
    {
        return ($this->verification_status ?? self::STATUS_VERIFIED) === self::STATUS_PENDING_REVIEW;
    }

    public function isVerified(): bool
    {
        return !$this->isPendingReview();
    }

    /**
     * @return array{key: string, label: string, tone: string}
     */
    public function verificationStatusBadge(): array
    {
        if ($this->isPendingReview()) {
            return [
                'key' => self::STATUS_PENDING_REVIEW,
                'label' => 'Pending HR Review',
                'tone' => 'amber',
            ];
        }

        return [
            'key' => self::STATUS_VERIFIED,
            'label' => 'Verified',
            'tone' => 'emerald',
        ];
    }

    public function categoryLabel(): string
    {
        return self::CATEGORIES[$this->category] ?? ucwords(str_replace('_', ' ', (string) $this->category));
    }

    /**
     * Compute expiry badge status relative to today in Asia/Qatar:
     * - 'none': no expiry date set
     * - 'expired': expiry_date < today
     * - 'expiring_soon': expiry_date within 0..30 days
     * - 'valid': expiry_date > 30 days away
     *
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
