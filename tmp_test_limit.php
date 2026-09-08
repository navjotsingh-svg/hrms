<?php

require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$employee = App\Models\Employee::query()->where('company_id', 3)->first();
$cl = App\Models\LeaveType::query()->where('company_id', 3)->where('code', 'CL')->first();
$service = app(App\Services\LeaveRequestService::class);

$reflection = new ReflectionClass($service);
$build = $reflection->getMethod('buildDayRows');
$build->setAccessible(true);
$assert = $reflection->getMethod('assertApplicationLimits');
$assert->setAccessible(true);

$dayRows = $build->invoke($service, $employee, $cl, '2026-06-15', '2026-06-17', 'full_day', null);

echo 'Day rows: '.$dayRows->count().PHP_EOL;
foreach ($dayRows as $row) {
    echo "  {$row['date']} => {$row['day_value']}".PHP_EOL;
}

try {
    $assert->invoke($service, $employee, $cl, $dayRows);
    echo "UNEXPECTED: assertApplicationLimits passed\n";
} catch (Illuminate\Validation\ValidationException $e) {
    echo "EXPECTED failure: ".json_encode($e->errors()).PHP_EOL;
}
