<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompanyMomentReaction extends Model
{
    public const REACTION_LIKE = 'like';

    public const REACTION_LOVE = 'love';

    public const REACTION_INSIGHTFUL = 'insightful';

    public const REACTION_CLAP = 'clap';

    public const REACTION_NOTE = 'note';

    public const REACTIONS = [
        self::REACTION_LIKE,
        self::REACTION_LOVE,
        self::REACTION_INSIGHTFUL,
        self::REACTION_CLAP,
        self::REACTION_NOTE,
    ];

    protected $fillable = [
        'company_moment_id',
        'user_id',
        'reaction',
    ];

    public function moment(): BelongsTo
    {
        return $this->belongsTo(CompanyMoment::class, 'company_moment_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
