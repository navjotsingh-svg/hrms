<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Project extends Model
{
    public const STATUS_ACTIVE = 'active';

    public const STATUS_CLOSED = 'closed';

    protected $fillable = [
        'company_id',
        'name',
        'description',
        'start_date',
        'end_date',
        'status',
        'created_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function employees(): BelongsToMany
    {
        return $this->belongsToMany(Employee::class, 'project_employee')->withTimestamps();
    }
}
