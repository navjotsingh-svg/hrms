<?php



namespace App\Http\Requests;



use Illuminate\Foundation\Http\FormRequest;



class StoreEmployeeFamilyMemberRequest extends FormRequest

{

    public function authorize(): bool

    {

        return true;

    }



    public function rules(): array

    {

        return [

            'members' => ['required', 'array', 'min:1'],

            'members.*.id' => ['nullable', 'integer'],

            'members.*.name' => ['required', 'string', 'max:100'],

            'members.*.relation' => ['required', 'string', 'max:50'],

            'members.*.phone' => ['nullable', 'digits:10'],

            'members.*.date_of_birth' => [

                'nullable',

                'date_format:Y-m-d',

                'before_or_equal:today',

                'after:1900-01-01',

            ],

        ];

    }



    public function messages(): array

    {

        return [

            'members.*.phone.digits' => 'Mobile number must be exactly 10 digits.',

            'members.*.date_of_birth.date_format' => 'Date of birth must use a 4-digit year (YYYY-MM-DD).',

            'members.*.date_of_birth.before_or_equal' => 'Date of birth cannot be in the future.',

            'members.*.date_of_birth.after' => 'Date of birth year must be 1900 or later.',

        ];

    }



    public function validated($key = null, $default = null): array

    {

        $validated = parent::validated($key, $default);



        return [

            'members' => collect($validated['members'] ?? [])

                ->map(fn (array $member) => [

                    'id' => isset($member['id']) ? (int) $member['id'] : null,

                    'name' => trim($member['name']),

                    'relation' => trim($member['relation']),

                    'phone' => isset($member['phone']) && trim((string) $member['phone']) !== ''

                        ? trim((string) $member['phone'])

                        : null,

                    'date_of_birth' => $member['date_of_birth'] ?? null,

                ])

                ->values()

                ->all(),

        ];

    }

}

