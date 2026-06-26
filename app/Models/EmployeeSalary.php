<?php



namespace App\Models;



use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Relations\BelongsTo;



class EmployeeSalary extends Model

{

    protected $fillable = [

        'company_id',

        'employee_id',

        'annual_ctc',

        'basic_salary',

        'hra_percent',

        'special_allowance_percent',

        'hra',

        'special_allowance',

        'conveyance_allowance',

        'medical_allowance',

        'other_allowance',

        'pf_applicable',

        'esi_applicable',

        'professional_tax_applicable',

        'payment_mode',

        'bank_name',

        'account_holder_name',

        'account_number',

        'ifsc_code',

        'salary_effective_from',

        'salary_payout_from',

    ];



    protected function casts(): array

    {

        return [

            'annual_ctc' => 'decimal:2',

            'basic_salary' => 'decimal:2',

            'hra_percent' => 'decimal:2',

            'special_allowance_percent' => 'decimal:2',

            'hra' => 'decimal:2',

            'special_allowance' => 'decimal:2',

            'conveyance_allowance' => 'decimal:2',

            'medical_allowance' => 'decimal:2',

            'other_allowance' => 'decimal:2',

            'pf_applicable' => 'boolean',

            'esi_applicable' => 'boolean',

            'professional_tax_applicable' => 'boolean',

            'salary_effective_from' => 'date',

            'salary_payout_from' => 'date',

        ];

    }



    public function company(): BelongsTo

    {

        return $this->belongsTo(Company::class);

    }



    public function employee(): BelongsTo

    {

        return $this->belongsTo(Employee::class);

    }



    public function getMonthlyGrossAttribute(): float

    {

        return (float) $this->basic_salary

            + (float) $this->hra

            + (float) $this->special_allowance

            + (float) $this->conveyance_allowance

            + (float) $this->medical_allowance

            + (float) $this->other_allowance;

    }

}

