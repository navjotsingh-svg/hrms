<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class EmployeeAssistantService
{
    public function __construct(
        private EmployeeAssistantContextService $contextService,
        private AiCompletionService $aiCompletionService,
    ) {}

    /** @return array<string, mixed> */
    public function meta(User $user): array
    {
        $this->assertEnabled();
        $persona = $this->contextService->personaFor($user);

        return [
            'enabled' => true,
            'ai_enabled' => $this->aiEnabled(),
            'persona' => $persona,
            'employee_name' => $user->employee?->full_name ?? $user->name,
            'suggested_questions' => $this->contextService->suggestedQuestionsFor($user),
        ];
    }

    /** @param  array<int, array{role: string, content: string}>  $history */
    public function chat(User $user, string $message, array $history = []): array
    {
        $this->assertEnabled();

        $message = trim($message);

        if ($message === '') {
            return [
                'reply' => 'Please type a question about HR information available to you.',
                'source' => 'system',
            ];
        }

        $context = $this->contextService->buildForUser($user);
        $persona = $context['persona'] ?? 'employee';

        if ($persona === 'employee' && ! ($context['has_employee_profile'] ?? false)) {
            return [
                'reply' => 'Your user account is not linked to an employee profile. Please contact HR if you believe this is incorrect.',
                'source' => 'system',
            ];
        }

        if ($this->aiEnabled()) {
            try {
                return [
                    ...$this->chatWithOpenAi($message, $context, $history, $persona),
                    'source' => 'ai',
                ];
            } catch (\Throwable $exception) {
                report($exception);
            }
        }

        return [
            'reply' => $this->ruleBasedReply($message, $context),
            'source' => 'rules',
        ];
    }

    public function aiEnabled(): bool
    {
        return $this->isEnabled()
            && $this->aiCompletionService->enabled();
    }

    private function isEnabled(): bool
    {
        return (bool) config('hrms.assistant.enabled', true);
    }

    private function assertEnabled(): void
    {
        if (! $this->isEnabled()) {
            throw new AccessDeniedHttpException('HR Assistant is not enabled.');
        }
    }

    /** @param  array<string, mixed>  $context */
    private function ruleBasedReply(string $message, array $context): string
    {
        $normalized = Str::lower($message);
        $profile = $context['profile'] ?? [];
        $persona = $context['persona'] ?? 'employee';

        if ($this->matchesAny($normalized, ['help', 'what can you', 'what do you', 'how can you'])) {
            return $this->capabilitiesReply($context);
        }

        if ($this->matchesAny($normalized, ['policy', 'policies', 'handbook', 'company rule'])) {
            return $this->policiesReply($context);
        }

        if ($persona !== 'employee') {
            if ($this->matchesAny($normalized, ['on leave today', 'leave today', 'who is on leave'])) {
                return $this->employeesOnLeaveReply($context);
            }

            if ($this->matchesAny($normalized, ['pending leave', 'leave approval', 'approve leave'])) {
                return $this->pendingLeaveReply($context);
            }

            if ($this->matchesAny($normalized, ['attendance today', 'attendance overview', 'not punched', 'absent today'])) {
                return $this->attendanceOverviewReply($context);
            }

            if ($this->matchesAny($normalized, ['helpdesk', 'open ticket', 'support ticket'])) {
                return $this->helpdeskQueueReply($context);
            }

            if ($this->matchesAny($normalized, ['data quality', 'incomplete profile', 'missing data', 'setup issue'])) {
                return $this->dataQualityReply($context);
            }
        }

        if ($persona === 'admin') {
            if ($this->matchesAny($normalized, ['headcount', 'company setup', 'setup status', 'how many employee'])) {
                return $this->companySetupReply($context);
            }

            if ($this->matchesAny($normalized, ['role', 'roles', 'permission'])) {
                return $this->rolesReply($context);
            }

            if ($this->matchesAny($normalized, ['activity log', 'audit', 'recent activity', 'who changed'])) {
                return $this->activityReply($context);
            }

            if ($this->matchesAny($normalized, ['analytics', 'summary', 'overview', 'insight'])) {
                return $this->analyticsReply($context);
            }
        }

        if ($this->matchesAny($normalized, ['leave balance', 'leave left', 'remaining leave', 'how many leave', 'leaves do i have', 'leave available'])) {
            return $this->leaveBalanceReply($context);
        }

        if ($this->matchesAny($normalized, ['attendance', 'punch', 'punched', 'present today', 'absent today', 'clock in', 'clock out', 'mark attendance'])) {
            return $this->attendanceReply($context);
        }

        if ($this->matchesAny($normalized, ['manager', 'reporting', 'supervisor', 'line manager', 'boss'])) {
            $manager = $profile['manager_name'] ?? null;

            return $manager
                ? "Your reporting manager is **{$manager}**."
                : 'No reporting manager is assigned to your profile yet.';
        }

        if ($this->matchesAny($normalized, ['department', 'dept'])) {
            $departments = $profile['departments'] ?? [];

            return $departments !== []
                ? 'You belong to: **'.implode(', ', $departments).'**.'
                : 'No department is assigned to your profile yet.';
        }

        if ($this->matchesAny($normalized, ['employee code', 'emp code', 'employee id', 'emp id'])) {
            return 'Your employee code is **'.($profile['employee_code'] ?? '—').'**.';
        }

        if ($this->matchesAny($normalized, ['joining date', 'date of joining', 'when did i join', 'doj'])) {
            $joiningDate = $profile['joining_date'] ?? null;

            return $joiningDate
                ? "Your joining date is **{$joiningDate}**."
                : 'Your joining date is not recorded in your profile yet.';
        }

        if ($this->matchesAny($normalized, ['designation', 'job title', 'role name', 'my role'])) {
            $parts = array_filter([
                filled($profile['designation'] ?? null) ? 'Designation: '.$profile['designation'] : null,
                filled($profile['role'] ?? null) ? 'System role: '.$profile['role'] : null,
            ]);

            return $parts !== []
                ? implode('. ', $parts).'.'
                : 'Your designation is not recorded in your profile yet.';
        }

        if ($this->matchesAny($normalized, ['shift', 'work timing', 'office timing'])) {
            $shift = $profile['shift'] ?? null;
            $range = $profile['shift_time_range'] ?? null;

            if (! $shift) {
                return 'No shift is assigned to your profile yet.';
            }

            return $range
                ? "Your shift is **{$shift}** ({$range})."
                : "Your shift is **{$shift}**.";
        }

        if ($this->matchesAny($normalized, ['leave request', 'pending leave', 'applied leave', 'leave status', 'my leave'])) {
            return $this->leaveRequestsReply($context);
        }

        if ($this->matchesAny($normalized, ['holiday', 'holidays', 'next holiday'])) {
            return $this->holidaysReply($context);
        }

        if ($this->matchesAny($normalized, ['payslip', 'salary slip', 'pay slip', 'my salary', 'net pay', 'deduction', 'earning'])) {
            return $this->payslipsReply($context);
        }

        if ($this->matchesAny($normalized, ['profile', 'my details', 'about me', 'who am i', 'my information'])) {
            return $this->profileReply($context);
        }

        if ($this->matchesAny($normalized, ['ticket', 'my ticket', 'helpdesk status'])) {
            return $this->myHelpdeskReply($context);
        }

        return 'I can help with HR information available to your role. Try one of the suggested questions, or ask about leave, attendance, holidays, payslips, policies, team insights, or company setup.';
    }

    /** @param  array<string, mixed>  $context */
    private function capabilitiesReply(array $context): string
    {
        $topics = $context['capabilities'] ?? ['HR self-service'];

        return 'I am your HR Assistant ('.($context['persona'] ?? 'employee')." mode). I can help with: **".implode(', ', $topics).'**. I only use data you are permitted to see.';
    }

    /** @param  array<string, mixed>  $context */
    private function profileReply(array $context): string
    {
        $profile = $context['profile'] ?? [];

        if ($profile === []) {
            return 'No employee profile is linked to your account.';
        }

        $lines = [
            'Name: '.($profile['full_name'] ?? '—'),
            'Employee code: '.($profile['employee_code'] ?? '—'),
            'Designation: '.($profile['designation'] ?? '—'),
            'Department: '.implode(', ', $profile['departments'] ?? []) ?: '—',
            'Manager: '.($profile['manager_name'] ?? '—'),
            'Joining date: '.($profile['joining_date'] ?? '—'),
            'Shift: '.($profile['shift'] ?? '—'),
        ];

        return implode("\n", $lines);
    }

    /** @param  array<string, mixed>  $context */
    private function leaveBalanceReply(array $context): string
    {
        if (! isset($context['leave_balances'])) {
            return 'Leave information is not available for your account.';
        }

        $balances = $context['leave_balances'];

        if ($balances === []) {
            return 'No leave types are assigned to you for this year.';
        }

        $lines = collect($balances)->map(function (array $balance) {
            $unit = $balance['unit'] === 'hours' ? 'hours' : 'days';

            return '- **'.$balance['leave_type'].'**: '.$balance['available'].' '.$unit.' available (used '.$balance['used'].', pending '.$balance['pending'].')';
        });

        return "Your leave balances for {$balances[0]['year']}:\n".$lines->implode("\n");
    }

    /** @param  array<string, mixed>  $context */
    private function attendanceReply(array $context): string
    {
        if (! isset($context['attendance_today'])) {
            return 'Attendance information is not available for your account.';
        }

        $attendance = $context['attendance_today'];
        $lines = ['Status: **'.($attendance['status_label'] ?? $attendance['status'] ?? '—').'**'];

        if ($attendance['punch_in']) {
            $lines[] = 'Punch in: '.$attendance['punch_in'];
        }

        if ($attendance['punch_out']) {
            $lines[] = 'Punch out: '.$attendance['punch_out'];
        }

        if (($attendance['worked_minutes'] ?? 0) > 0) {
            $hours = intdiv((int) $attendance['worked_minutes'], 60);
            $minutes = (int) $attendance['worked_minutes'] % 60;
            $lines[] = 'Worked: '.$hours.'h '.$minutes.'m';
        }

        if ($attendance['day_message'] ?? null) {
            $lines[] = $attendance['day_message'];
        }

        return implode("\n", $lines);
    }

    /** @param  array<string, mixed>  $context */
    private function leaveRequestsReply(array $context): string
    {
        if (! isset($context['leave_requests'])) {
            return 'Leave request information is not available for your account.';
        }

        $requests = $context['leave_requests'];

        if ($requests === []) {
            return 'You have no recent leave requests on record.';
        }

        $lines = collect($requests)->map(fn (array $request) => '- **'.$request['leave_type'].'** ('.$request['from_date'].' to '.$request['to_date'].'): '.$request['status']);

        return "Your recent leave requests:\n".$lines->implode("\n");
    }

    /** @param  array<string, mixed>  $context */
    private function holidaysReply(array $context): string
    {
        $holidays = $context['upcoming_holidays'] ?? [];

        if ($holidays === []) {
            return 'There are no upcoming active company holidays in the next twelve months.';
        }

        $lines = collect($holidays)->map(fn (array $holiday) => '- **'.$holiday['name'].'** on '.$holiday['date_label']);

        return "Upcoming holidays (next 12 months):\n".$lines->implode("\n");
    }

    /** @param  array<string, mixed>  $context */
    private function payslipsReply(array $context): string
    {
        if (! isset($context['payslips'])) {
            return 'Payslip information is not available for your account.';
        }

        $payslips = $context['payslips'];
        $count = (int) ($payslips['total_count'] ?? 0);

        if ($count === 0) {
            return 'No payslips are available for you yet.';
        }

        $detail = $context['latest_payslip_detail'] ?? null;
        $lines = [
            'You have **'.$count.'** payslip(s). Latest period: **'.($payslips['latest_period_label'] ?? '—').'**.',
        ];

        if ($detail) {
            $lines[] = 'Net pay: **'.number_format((float) $detail['net_pay'], 2).'**';
            $lines[] = 'Total earnings: '.number_format((float) $detail['total_earnings'], 2);
            $lines[] = 'Total deductions: '.number_format((float) $detail['total_deductions'], 2);

            if (! empty($detail['earnings'])) {
                $lines[] = 'Earnings: '.collect($detail['earnings'])->map(fn ($amount, $label) => "{$label}: {$amount}")->implode(', ');
            }

            if (! empty($detail['deductions'])) {
                $lines[] = 'Deductions: '.collect($detail['deductions'])->map(fn ($amount, $label) => "{$label}: {$amount}")->implode(', ');
            }
        }

        return implode("\n", $lines);
    }

    /** @param  array<string, mixed>  $context */
    private function policiesReply(array $context): string
    {
        $policies = $context['company_policies'] ?? [];

        if ($policies === []) {
            return 'No signed policy documents are available yet. Contact HR for policy questions.';
        }

        $lines = collect($policies)->map(fn (array $policy) => '- **'.$policy['title'].'** ('.$policy['status'].')');

        return "Company policy documents on file:\n".$lines->implode("\n");
    }

    /** @param  array<string, mixed>  $context */
    private function myHelpdeskReply(array $context): string
    {
        $tickets = $context['my_helpdesk_tickets'] ?? [];

        if ($tickets === []) {
            return 'You have no recent helpdesk tickets.';
        }

        $lines = collect($tickets)->map(fn (array $ticket) => '- **'.$ticket['subject'].'** ('.$ticket['status'].', '.$ticket['priority'].' priority)');

        return "Your recent helpdesk tickets:\n".$lines->implode("\n");
    }

    /** @param  array<string, mixed>  $context */
    private function employeesOnLeaveReply(array $context): string
    {
        $items = $context['employees_on_leave_today'] ?? [];

        if ($items === []) {
            return 'No employees are on approved leave today.';
        }

        $lines = collect($items)->map(fn (array $item) => '- **'.$item['employee_name'].'** ('.$item['leave_type'].', until '.$item['until'].')');

        return "Employees on leave today:\n".$lines->implode("\n");
    }

    /** @param  array<string, mixed>  $context */
    private function pendingLeaveReply(array $context): string
    {
        $pending = $context['pending_leave_requests'] ?? null;

        if (! $pending) {
            return 'Pending leave approval data is not available for your account.';
        }

        if (($pending['count'] ?? 0) === 0) {
            return 'There are no pending leave requests awaiting approval.';
        }

        $lines = collect($pending['items'] ?? [])->map(fn (array $item) => '- **'.$item['employee_name'].'**: '.$item['leave_type'].' ('.$item['from_date'].' to '.$item['to_date'].')');

        return 'Pending leave requests: **'.$pending['count']."**\n".$lines->implode("\n");
    }

    /** @param  array<string, mixed>  $context */
    private function attendanceOverviewReply(array $context): string
    {
        $overview = $context['attendance_overview_today'] ?? null;

        if (! $overview) {
            return 'Attendance overview is not available for your account.';
        }

        $summary = collect($overview['summary'] ?? [])->map(fn ($value, $key) => ucfirst(str_replace('_', ' ', (string) $key)).': **'.$value.'**')->implode("\n");
        $issues = collect($overview['not_punched_in'] ?? [])->map(fn (array $row) => '- '.$row['employee_name'].' ('.$row['status'].')')->implode("\n");

        return "Today's attendance ({$overview['date']}):\n{$summary}".($issues ? "\n\nNeeds attention:\n{$issues}" : '');
    }

    /** @param  array<string, mixed>  $context */
    private function helpdeskQueueReply(array $context): string
    {
        $queue = $context['open_helpdesk_tickets'] ?? null;

        if (! $queue) {
            return 'Helpdesk queue data is not available for your account.';
        }

        if (($queue['count'] ?? 0) === 0) {
            return 'There are no open helpdesk tickets.';
        }

        $lines = collect($queue['items'] ?? [])->map(fn (array $item) => '- **'.$item['subject'].'** ('.$item['priority'].', '.$item['status'].')');

        return 'Open helpdesk tickets: **'.$queue['count']."**\n".$lines->implode("\n");
    }

    /** @param  array<string, mixed>  $context */
    private function dataQualityReply(array $context): string
    {
        $report = $context['data_quality'] ?? null;

        if (! $report) {
            return 'Data quality scan is not available for your account.';
        }

        if (($report['incomplete_profiles'] ?? 0) === 0) {
            return 'All **'.$report['active_employees'].'** active employee profiles look complete.';
        }

        $lines = collect($report['issues'] ?? [])->map(fn (array $issue) => '- **'.$issue['employee_name'].'** missing: '.implode(', ', $issue['missing_fields']));

        return 'Incomplete profiles: **'.$report['incomplete_profiles'].'** / '.$report['active_employees']."\n".$lines->implode("\n");
    }

    /** @param  array<string, mixed>  $context */
    private function companySetupReply(array $context): string
    {
        $setup = $context['company_setup'] ?? null;

        if (! $setup) {
            return 'Company setup summary is not available.';
        }

        return collect($setup)->map(fn ($value, $key) => ucfirst(str_replace('_', ' ', (string) $key)).': **'.$value.'**')->implode("\n");
    }

    /** @param  array<string, mixed>  $context */
    private function rolesReply(array $context): string
    {
        $roles = $context['roles_overview'] ?? [];

        if ($roles === []) {
            return 'No roles found for this company.';
        }

        $lines = collect($roles)->map(fn (array $role) => '- **'.$role['name'].'**: '.$role['users_count'].' user(s)');

        return "Roles overview:\n".$lines->implode("\n");
    }

    /** @param  array<string, mixed>  $context */
    private function activityReply(array $context): string
    {
        $logs = $context['recent_activity'] ?? [];

        if ($logs === []) {
            return 'No recent activity logs found.';
        }

        $lines = collect($logs)->map(fn (array $log) => '- '.$log['created_at'].': '.$log['summary']);

        return "Recent activity:\n".$lines->implode("\n");
    }

    /** @param  array<string, mixed>  $context */
    private function analyticsReply(array $context): string
    {
        $snapshot = $context['analytics_snapshot'] ?? null;

        if (! $snapshot) {
            return 'Analytics snapshot is not available.';
        }

        $lines = [
            'Employees on leave today: **'.($snapshot['employees_on_leave_today'] ?? 0).'**',
            'Pending leave requests: **'.($snapshot['pending_leave_requests'] ?? 0).'**',
            'Open helpdesk tickets: **'.($snapshot['open_helpdesk_tickets'] ?? 0).'**',
        ];

        foreach ($snapshot['attendance_summary'] ?? [] as $key => $value) {
            $lines[] = ucfirst(str_replace('_', ' ', (string) $key)).': **'.$value.'**';
        }

        return implode("\n", $lines);
    }

    /** @param  array<string, mixed>  $context */
    /** @param  array<int, array{role: string, content: string}>  $history */
    private function chatWithOpenAi(string $message, array $context, array $history, string $persona): array
    {
        $messages = [
            [
                'role' => 'system',
                'content' => implode("\n", [
                    'You are a helpful HR assistant inside an HRMS product.',
                    'Persona: '.$persona.' (employee, hr, or admin).',
                    'Answer ONLY using the JSON data provided below and respect permission boundaries.',
                    'Never invent numbers, dates, or names.',
                    'Never reveal information about employees the user is not allowed to see.',
                    'If the data is missing, say you do not have that information.',
                    'Keep answers concise, friendly, and plain text (markdown bold is allowed).',
                    'HR data JSON:',
                    json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ]),
            ],
        ];

        foreach (array_slice($history, -6) as $entry) {
            if (! in_array($entry['role'] ?? '', ['user', 'assistant'], true)) {
                continue;
            }

            $content = trim((string) ($entry['content'] ?? ''));

            if ($content !== '') {
                $messages[] = ['role' => $entry['role'], 'content' => $content];
            }
        }

        $messages[] = ['role' => 'user', 'content' => $message];

        return ['reply' => $this->aiCompletionService->chat($messages)['content']];
    }

    /** @param  array<int, string>  $needles */
    private function matchesAny(string $haystack, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (str_contains($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }
}
