<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Candidate extends Model
{
    public const STAGE_APPLIED = 'applied';

    public const STAGE_SCREENING = 'screening';

    public const STAGE_INTERVIEW = 'interview';

    public const STAGE_OFFER = 'offer';

    public const STAGE_HIRED = 'hired';

    public const STAGE_REJECTED = 'rejected';

    protected $fillable = [
        'company_id',
        'job_id',
        'first_name',
        'last_name',
        'email',
        'phone',
        'resume_path',
        'source',
        'stage',
        'assigned_recruiter_user_id',
        'notes',
        'applied_at',
        'rejected_at',
        'rejection_reason',
        'hired_at',
        'employee_id',
    ];

    protected function casts(): array
    {
        return [
            'applied_at' => 'datetime',
            'rejected_at' => 'datetime',
            'hired_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function job(): BelongsTo
    {
        return $this->belongsTo(JobPosting::class, 'job_id');
    }

    public function assignedRecruiter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_recruiter_user_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function stageLogs(): HasMany
    {
        return $this->hasMany(CandidateStageLog::class)->orderBy('created_at');
    }

    public function interviews(): HasMany
    {
        return $this->hasMany(CandidateInterview::class);
    }

    public function offers(): HasMany
    {
        return $this->hasMany(HiringOffer::class);
    }
}
