<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CrmIdeaComment extends Model
{
    use HasFactory;

    protected $table = 'crm_idea_comments';

    protected $fillable = [
        'idea_id',
        'user_id',
        'body',
    ];

    public function idea(): BelongsTo
    {
        return $this->belongsTo(CrmIdea::class, 'idea_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
