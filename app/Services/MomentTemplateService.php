<?php

namespace App\Services;

use App\Models\CompanyMoment;
use App\Models\CompanyMomentTemplate;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Support\Carbon;

class MomentTemplateService
{
    public function forCompany(int $companyId): CompanyMomentTemplate
    {
        $defaults = CompanyMomentTemplate::defaults();

        return CompanyMomentTemplate::query()->firstOrCreate(
            ['company_id' => $companyId],
            [
                'birthday_template' => $defaults['birthday'] ?? '',
                'work_anniversary_template' => $defaults['work_anniversary'] ?? '',
                'new_joinee_template' => $defaults['new_joinee'] ?? '',
            ],
        );
    }

    public function updateForCompany(User $user, array $data): CompanyMomentTemplate
    {
        if (! $user->isCompanyAdmin()) {
            abort(403, 'Only company admins can update moment templates.');
        }

        $template = $this->forCompany((int) $user->company_id);

        $template->update([
            'birthday_template' => trim($data['birthday_template']),
            'work_anniversary_template' => trim($data['work_anniversary_template']),
            'new_joinee_template' => trim($data['new_joinee_template']),
        ]);

        return $template->fresh();
    }

    public function render(string $template, Employee $employee, ?int $years = null): string
    {
        $replacements = [
            '{name}' => $employee->full_name,
            '{employee_code}' => $employee->employee_code ?? '',
            '{years}' => $years !== null ? (string) $years : '',
        ];

        return trim(str_replace(array_keys($replacements), array_values($replacements), $template));
    }

    public function preview(string $type, Employee $employee, ?CompanyMomentTemplate $templates = null): string
    {
        $templates ??= $this->forCompany((int) $employee->company_id);
        $template = $templates->templateForType($type);

        $years = null;

        if ($type === CompanyMoment::TYPE_WORK_ANNIVERSARY && $employee->joining_date) {
            $years = max(1, (int) Carbon::parse($employee->joining_date)->diffInYears(Carbon::today(config('app.timezone'))));
        }

        return $this->render($template, $employee, $years);
    }

    /** @return array<string, string> */
    public function toArray(CompanyMomentTemplate $template): array
    {
        return [
            'birthday_template' => $template->birthday_template,
            'work_anniversary_template' => $template->work_anniversary_template,
            'new_joinee_template' => $template->new_joinee_template,
            'placeholders' => config('moments.placeholders', []),
        ];
    }
}
