<?php

namespace App\Http\Requests;

use App\Models\EmployeePaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEmployeePaymentMethodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->input('payment_mode') !== 'bank_transfer') {
            $this->merge([
                'bank_name' => null,
                'bank_branch' => null,
                'bank_address' => null,
                'account_holder_name' => null,
                'account_number' => null,
                'ifsc_code' => null,
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'payment_mode' => ['required', Rule::in(EmployeePaymentMethod::PAYMENT_MODES)],
            'bank_name' => ['nullable', 'required_if:payment_mode,bank_transfer', 'string', 'max:100'],
            'bank_branch' => ['nullable', 'required_if:payment_mode,bank_transfer', 'string', 'max:100'],
            'bank_address' => ['nullable', 'required_if:payment_mode,bank_transfer', 'string', 'max:255'],
            'account_holder_name' => ['nullable', 'required_if:payment_mode,bank_transfer', 'string', 'max:100'],
            'account_number' => ['nullable', 'required_if:payment_mode,bank_transfer', 'string', 'max:30'],
            'ifsc_code' => ['nullable', 'required_if:payment_mode,bank_transfer', 'string', 'max:20', 'regex:/^[A-Z]{4}0[A-Z0-9]{6}$/i'],
            'proofs' => ['nullable', 'array', 'min:1'],
            'proofs.*' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            if ($this->input('payment_mode') !== 'bank_transfer') {
                return;
            }

            if (! $this->hasFile('proofs')) {
                $validator->errors()->add('proofs', 'Please attach at least one bank proof document.');
            }
        });
    }

    public function proofFiles(): array
    {
        return array_values($this->file('proofs', []));
    }
}
