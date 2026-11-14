<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CrmIdea extends Model
{
    use HasFactory;

    protected $table = 'crm_ideas';

    public const CATEGORIES = [
        'Giveaway',
        'Reels Hook',
        'Branch Upsell',
        'Laleen Rituals',
        'CRM Improvement',
    ];

    public const STATUS_NEW = 'New Idea';
    public const STATUS_APPROVED = 'Approved';
    public const STATUS_CONVERTED = 'Converted to Ops';
    public const STATUS_ARCHIVED = 'Archived';

    public const STATUSES = [
        self::STATUS_NEW,
        self::STATUS_APPROVED,
        self::STATUS_CONVERTED,
        self::STATUS_ARCHIVED,
    ];

    protected $fillable = [
        'title',
        'category',
        'description',
        'status',
        'upvotes_count',
        'created_by',
        'ops_task_id',
    ];

    protected $casts = [
        'upvotes_count' => 'integer',
        'created_by' => 'integer',
        'ops_task_id' => 'integer',
    ];

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function opsTask(): BelongsTo
    {
        return $this->belongsTo(OpsActionItem::class, 'ops_task_id');
    }

    public function votes(): HasMany
    {
        return $this->hasMany(CrmIdeaVote::class, 'idea_id');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(CrmIdeaComment::class, 'idea_id')->orderBy('created_at', 'asc');
    }

    public function canBeModifiedBy(?User $user): bool
    {
        if (!$user || !$user->is_active) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        return (int) $this->created_by === (int) $user->id
            && $this->status === self::STATUS_NEW;
    }

    public static function opsScopeForCategory(string $category): string
    {
        return match ($category) {
            'Laleen Rituals' => 'Laleen Rituals',
            'Giveaway', 'Reels Hook' => 'Marketing & Ads',
            'Branch Upsell' => 'Old Airport',
            default => 'CRM & General',
        };
    }

    public static function opsImpactLeverForCategory(string $category): string
    {
        return match ($category) {
            'Giveaway', 'Reels Hook', 'Branch Upsell' => 'revenue_leads',
            'Laleen Rituals' => 'retention_delivery',
            default => 'admin_ops',
        };
    }
}
