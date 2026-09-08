<?php

namespace App\Services;

use App\Models\Holiday;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;

class HolidayService
{
    public function listForCompany(int $companyId, array $filters = []): LengthAwarePaginator
    {
        $query = Holiday::query()
            ->where('company_id', $companyId)
            ->orderByDesc('from_date');

        if ($search = trim((string) ($filters['search'] ?? ''))) {
            $query->where(function ($builder) use ($search) {
                $builder
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($year = $filters['year'] ?? null) {
            $query->where(function ($builder) use ($year) {
                $builder
                    ->where(function ($fixed) {
                        $fixed->where('frequency', Holiday::FREQUENCY_FIXED);
                    })
                    ->orWhere(function ($variable) use ($year) {
                        $variable
                            ->where('frequency', Holiday::FREQUENCY_VARIABLE)
                            ->where(function ($dates) use ($year) {
                                $dates->whereYear('from_date', $year)
                                    ->orWhereYear('to_date', $year);
                            });
                    });
            });
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->paginate($filters['per_page'] ?? 10);
    }

    public function create(int $companyId, array $input): Holiday
    {
        $data = $this->preparePayload($companyId, $input);

        return Holiday::create([
            ...$data,
            'company_id' => $companyId,
        ]);
    }

    public function update(Holiday $holiday, array $input): Holiday
    {
        $data = $this->preparePayload($holiday->company_id, $input, $holiday->id);
        $holiday->update($data);

        return $holiday->fresh();
    }

    public function delete(Holiday $holiday): void
    {
        $holiday->delete();
    }

    public function belongsToCompany(Holiday $holiday, int $companyId): bool
    {
        return (int) $holiday->company_id === $companyId;
    }

    /** @return array<string, mixed> */
    public function preparePayload(int $companyId, array $input, ?int $ignoreHolidayId = null): array
    {
        $data = $this->normalizeInput($input);
        $this->assertNoOverlap($companyId, $data, $ignoreHolidayId);

        return $data;
    }

    /** @return array<string, mixed> */
    private function normalizeInput(array $input): array
    {
        $frequency = $input['frequency'];
        $duration = $input['duration'];

        if ($frequency === Holiday::FREQUENCY_FIXED) {
            $startMonth = (int) $input['start_month'];
            $startDay = (int) $input['start_day'];
            $this->assertValidDayForMonth($startMonth, $startDay, 'start_day');

            $fromDate = sprintf('%04d-%02d-%02d', Holiday::FIXED_YEAR, $startMonth, $startDay);

            if ($duration === Holiday::DURATION_SINGLE) {
                $toDate = $fromDate;
            } else {
                $endMonth = (int) $input['end_month'];
                $endDay = (int) $input['end_day'];
                $this->assertValidDayForMonth($endMonth, $endDay, 'end_day');
                $toDate = sprintf('%04d-%02d-%02d', Holiday::FIXED_YEAR, $endMonth, $endDay);

                if (! $this->fixedRangeIsValid($startMonth, $startDay, $endMonth, $endDay)) {
                    throw ValidationException::withMessages([
                        'end_day' => ['End date must be after start date within the holiday period.'],
                    ]);
                }
            }
        } elseif ($duration === Holiday::DURATION_SINGLE) {
            $holidayDate = $this->requireFourDigitDate($input['holiday_date'] ?? null, 'holiday_date');
            $fromDate = $holidayDate;
            $toDate = $holidayDate;
        } else {
            $fromDate = $this->requireFourDigitDate($input['from_date'] ?? null, 'from_date');
            $toDate = $this->requireFourDigitDate($input['to_date'] ?? null, 'to_date');

            if ($toDate < $fromDate) {
                throw ValidationException::withMessages([
                    'to_date' => ['To date must be on or after from date.'],
                ]);
            }
        }

        return [
            'name' => trim((string) $input['name']),
            'frequency' => $frequency,
            'duration' => $duration,
            'from_date' => $fromDate,
            'to_date' => $toDate,
            'type' => $input['type'],
            'status' => $input['status'],
            'description' => $input['description'] ?? null,
        ];
    }

    private function requireFourDigitDate(?string $value, string $field): string
    {
        if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            throw ValidationException::withMessages([
                $field => ['Please select a valid date with a 4-digit year.'],
            ]);
        }

        return $value;
    }

    private function assertValidDayForMonth(int $month, int $day, string $field): void
    {
        if ($month < 1 || $month > 12 || $day < 1) {
            throw ValidationException::withMessages([
                $field => ['Enter a valid day and month.'],
            ]);
        }

        $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, Holiday::FIXED_YEAR);

        if ($day > $daysInMonth) {
            throw ValidationException::withMessages([
                $field => ["Invalid day for the selected month. Maximum is {$daysInMonth}."],
            ]);
        }
    }

    private function fixedRangeIsValid(int $startMonth, int $startDay, int $endMonth, int $endDay): bool
    {
        if ($endMonth > $startMonth) {
            return true;
        }

        if ($endMonth < $startMonth) {
            return true;
        }

        return $endDay >= $startDay;
    }

    /** @param  array<string, mixed>  $data */
    private function assertNoOverlap(int $companyId, array $data, ?int $ignoreHolidayId = null): void
    {
        $existing = Holiday::query()
            ->where('company_id', $companyId)
            ->when($ignoreHolidayId, fn ($query) => $query->where('id', '!=', $ignoreHolidayId))
            ->get();

        foreach ($existing as $holiday) {
            if ($this->rangesOverlap($data, $holiday)) {
                throw ValidationException::withMessages([
                    'start_day' => ['A holiday already exists for the selected date or period.'],
                    'end_day' => ['A holiday already exists for the selected date or period.'],
                    'holiday_date' => ['A holiday already exists for the selected date or period.'],
                    'from_date' => ['A holiday already exists for the selected date or period.'],
                    'to_date' => ['A holiday already exists for the selected date or period.'],
                ]);
            }
        }
    }

    /** @param  array<string, mixed>  $incoming */
    private function rangesOverlap(array $incoming, Holiday $existing): bool
    {
        if ($incoming['frequency'] === Holiday::FREQUENCY_FIXED && $existing->isFixed()) {
            return $this->fixedPatternsOverlap($incoming, $existing);
        }

        if ($incoming['frequency'] === Holiday::FREQUENCY_VARIABLE && ! $existing->isFixed()) {
            return $incoming['from_date'] <= $existing->to_date->toDateString()
                && $incoming['to_date'] >= $existing->from_date->toDateString();
        }

        if ($incoming['frequency'] === Holiday::FREQUENCY_FIXED && ! $existing->isFixed()) {
            return $this->fixedPatternOverlapsVariable($incoming, $existing);
        }

        if ($incoming['frequency'] === Holiday::FREQUENCY_VARIABLE && $existing->isFixed()) {
            return $this->variableOverlapsFixedPattern($incoming, $existing);
        }

        return false;
    }

    /** @param  array<string, mixed>  $incoming */
    private function fixedPatternOverlapsVariable(array $incoming, Holiday $existing): bool
    {
        $years = [
            (int) Carbon::parse($existing->from_date)->format('Y'),
            (int) Carbon::parse($existing->to_date)->format('Y'),
        ];

        $incomingHoliday = $this->temporaryHoliday($incoming);

        foreach (array_unique($years) as $year) {
            [$from, $to] = $incomingHoliday->resolvedBoundsForYear($year);

            if ($from->toDateString() <= $existing->to_date->toDateString()
                && $to->toDateString() >= $existing->from_date->toDateString()) {
                return true;
            }
        }

        return false;
    }

    /** @param  array<string, mixed>  $incoming */
    private function variableOverlapsFixedPattern(array $incoming, Holiday $existing): bool
    {
        $years = [
            (int) Carbon::parse($incoming['from_date'])->format('Y'),
            (int) Carbon::parse($incoming['to_date'])->format('Y'),
        ];

        foreach (array_unique($years) as $year) {
            [$from, $to] = $existing->resolvedBoundsForYear($year);

            if ($incoming['from_date'] <= $to->toDateString()
                && $incoming['to_date'] >= $from->toDateString()) {
                return true;
            }
        }

        return false;
    }

    /** @param  array<string, mixed>  $incoming */
    private function temporaryHoliday(array $incoming): Holiday
    {
        return new Holiday([
            'frequency' => $incoming['frequency'],
            'duration' => $incoming['duration'],
            'from_date' => $incoming['from_date'],
            'to_date' => $incoming['to_date'],
        ]);
    }

    /** @param  array<string, mixed>  $incoming */
    private function fixedPatternsOverlap(array $incoming, Holiday $existing): bool
    {
        $incomingHoliday = $this->temporaryHoliday($incoming);
        [$from, $to] = $incomingHoliday->resolvedBoundsForYear(2024);

        foreach (CarbonPeriod::create($from, $to) as $date) {
            if ($existing->coversDateResolved($date->toDateString())) {
                return true;
            }
        }

        return false;
    }
}
