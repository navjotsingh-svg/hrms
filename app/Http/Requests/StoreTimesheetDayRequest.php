<?php



namespace App\Http\Requests;



use Illuminate\Foundation\Http\FormRequest;



class StoreTimesheetDayRequest extends FormRequest

{

    public function authorize(): bool

    {

        return $this->user()?->canSubmitTimesheets() ?? false;

    }



    protected function prepareForValidation(): void

    {

        $entries = collect($this->input('entries', []))->map(function ($entry) {

            if (! is_array($entry)) {

                return $entry;

            }



            foreach (['done_today', 'blockers', 'plan_tomorrow', 'notes'] as $field) {

                if (($entry[$field] ?? '') === '') {

                    $entry[$field] = null;

                } elseif (is_string($entry[$field])) {

                    $entry[$field] = trim($entry[$field]) ?: null;

                }

            }



            return $entry;

        })->all();



        $this->merge(['entries' => $entries]);

    }



    public function rules(): array

    {

        return [

            'work_date' => ['required', 'date', 'before_or_equal:today'],

            'entries' => ['required', 'array', 'min:1'],

            'entries.*.project_id' => ['required', 'integer', 'distinct'],

            'entries.*.start_time' => ['required', 'date_format:H:i'],

            'entries.*.end_time' => ['required', 'date_format:H:i'],

            'entries.*.done_today' => ['required', 'string', 'max:5000'],

            'entries.*.blockers' => ['nullable', 'string', 'max:5000'],

            'entries.*.plan_tomorrow' => ['nullable', 'string', 'max:5000'],

            'entries.*.notes' => ['nullable', 'string', 'max:2000'],

        ];

    }



    public function withValidator($validator): void

    {

        $validator->after(function ($validator) {

            foreach ($this->input('entries', []) as $index => $entry) {

                if (! is_array($entry)) {

                    continue;

                }



                $start = $entry['start_time'] ?? null;

                $end = $entry['end_time'] ?? null;



                if ($start && $end && $end <= $start) {

                    $validator->errors()->add(

                        "entries.{$index}.end_time",

                        'End time must be after start time.',

                    );

                }

            }

        });

    }



    public function messages(): array

    {

        return [

            'work_date.date_equals' => 'You cannot submit a report for a future date.',
            'work_date.before_or_equal' => 'You cannot submit a report for a future date.',

            'entries.*.project_id.distinct' => 'Each project can only be logged once per day.',

            'entries.*.done_today.required' => 'Describe what you completed for each project.',

        ];

    }

}

