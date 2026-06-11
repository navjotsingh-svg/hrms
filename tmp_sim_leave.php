<?php

require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$employee = App\Models\Employee::query()->where('company_id', 3)->first();
$cl = App\Models\LeaveType::query()->where('company_id', 3)->where('code', 'CL')->first();

echo 'Employee: '.$employee?->id.PHP_EOL;
echo 'CL max_days_per_month: '.$cl?->max_days_per_month.PHP_EOL;

$requests = DB::table('leave_requests')
    ->join('leave_types', 'leave_types.id', '=', 'leave_requests.leave_type_id')
    ->where('leave_requests.employee_id', $employee?->id)
    ->where('leave_types.code', 'CL')
    ->orderByDesc('leave_requests.id')
    ->limit(5)
    ->get(['leave_requests.id', 'leave_requests.from_date', 'leave_requests.to_date', 'leave_requests.total_days', 'leave_requests.status']);

foreach ($requests as $r) {
    echo "Request {$r->id}: {$r->from_date} to {$r->to_date} = {$r->total_days} days ({$r->status})".PHP_EOL;
    $days = DB::table('leave_request_days')->where('leave_request_id', $r->id)->get();
    foreach ($days as $d) {
        echo "  - {$d->date} value={$d->day_value}".PHP_EOL;
    }
}

$policy = app(App\Services\AttendancePolicyService::class);
$weeklyOff = $policy->weeklyOffWeekdays(3);
echo 'Weekly off weekdays: '.json_encode($weeklyOff).PHP_EOL;

for ($d = 15; $d <= 17; $d++) {
    $date = "2026-06-{$d}";
    $isOff = $policy->isWeeklyOff($date, $weeklyOff);
    $holiday = $policy->holidayOnDate(3, $date);
    echo "{$date}: weekly_off=".($isOff?'yes':'no').' holiday='.($holiday?->name ?? 'no').PHP_EOL;
}
