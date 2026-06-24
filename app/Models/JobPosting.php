<?php



namespace App\Models;



use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

use Illuminate\Database\Eloquent\Relations\HasMany;



class JobPosting extends Model

{

    public const STATUS_DRAFT = 'draft';



    public const STATUS_OPEN = 'open';



    public const STATUS_CLOSED = 'closed';



    protected $table = 'job_postings';



    protected $fillable = [

        'company_id',

        'requisition_id',

        'department_id',

        'hiring_manager_employee_id',

        'title',

        'slug',

        'description_html',

        'location',

        'employment_type',

        'experience_min',

        'salary_min',

        'salary_max',

        'status',

        'published_at',

        'created_by_user_id',

    ];



    protected function casts(): array

    {

        return [

            'experience_min' => 'integer',

            'salary_min' => 'decimal:2',

            'salary_max' => 'decimal:2',

            'published_at' => 'datetime',

        ];

    }



    public function company(): BelongsTo

    {

        return $this->belongsTo(Company::class);

    }



    public function requisition(): BelongsTo

    {

        return $this->belongsTo(JobRequisition::class, 'requisition_id');

    }



    public function department(): BelongsTo

    {

        return $this->belongsTo(Department::class);

    }



    public function hiringManager(): BelongsTo

    {

        return $this->belongsTo(Employee::class, 'hiring_manager_employee_id');

    }



    public function createdBy(): BelongsTo

    {

        return $this->belongsTo(User::class, 'created_by_user_id');

    }



    public function candidates(): HasMany

    {

        return $this->hasMany(Candidate::class, 'job_id');

    }



    public function interviews(): HasMany

    {

        return $this->hasMany(CandidateInterview::class, 'job_id');

    }



    public function offers(): HasMany

    {

        return $this->hasMany(HiringOffer::class, 'job_id');

    }

}

