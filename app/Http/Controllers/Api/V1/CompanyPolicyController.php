<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Resources\CompanyPolicyResource;
use App\Models\CompanyPolicy;
use App\Services\CompanyPolicyService;
use App\Services\EmployeeAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CompanyPolicyController extends Controller
{
    use ApiResponse;

    public function __construct(
        private CompanyPolicyService $companyPolicyService,
        private EmployeeAccessService $employeeAccessService,
    ) {}

    public function meta(Request $request): JsonResponse
    {
        $employee = $this->employeeAccessService->linkedEmployee($request->user());

        return $this->success([
            'categories' => collect(config('company_policies.categories', []))->map(fn ($label, $value) => [
                'value' => $value,
                'label' => $label,
            ])->values(),
            'statuses' => collect(config('company_policies.statuses', []))->map(fn ($label, $value) => [
                'value' => $value,
                'label' => $label,
            ])->values(),
            'can_manage' => $request->user()->canManageDocuments(),
            'personal_email' => $employee?->personal_email,
            'employee_name' => $employee?->full_name,
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'category' => ['nullable', 'string', Rule::in(array_keys(config('company_policies.categories', [])))],
            'status' => ['nullable', 'string', Rule::in(array_keys(config('company_policies.statuses', [])))],
            'per_page' => ['nullable', 'integer', Rule::in([10, 25, 50])],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $policies = $this->companyPolicyService->listForCompany($request->user(), $validated);

        return $this->success([
            'policies' => CompanyPolicyResource::collection($policies->items()),
            'pagination' => [
                'current_page' => $policies->currentPage(),
                'last_page' => $policies->lastPage(),
                'per_page' => $policies->perPage(),
                'total' => $policies->total(),
                'from' => $policies->firstItem(),
                'to' => $policies->lastItem(),
            ],
            'can_manage' => $request->user()->canManageDocuments(),
        ]);
    }

    public function show(Request $request, CompanyPolicy $company_policy): JsonResponse
    {
        $policy = $this->companyPolicyService->resolveForUser($request->user(), $company_policy);

        return $this->success([
            'policy' => new CompanyPolicyResource($policy),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validatedPayload($request, true);
        $policy = $this->companyPolicyService->create($request->user(), $validated);

        return $this->success(
            ['policy' => new CompanyPolicyResource($policy)],
            'Policy created successfully.',
            201,
        );
    }

    public function update(Request $request, CompanyPolicy $company_policy): JsonResponse
    {
        $validated = $this->validatedPayload($request, false);
        $policy = $this->companyPolicyService->update($request->user(), $company_policy, $validated);

        return $this->success(
            ['policy' => new CompanyPolicyResource($policy)],
            'Policy updated successfully.',
        );
    }

    public function destroy(Request $request, CompanyPolicy $company_policy): JsonResponse
    {
        $this->companyPolicyService->delete($request->user(), $company_policy);

        return $this->success(null, 'Policy deleted successfully.');
    }

    public function sign(Request $request, CompanyPolicy $company_policy): JsonResponse
    {
        $validated = $request->validate([
            'consent_email' => ['required', 'email', 'max:255'],
            'signature_name' => ['required', 'string', 'max:255'],
            'signature_data_url' => ['nullable', 'string'],
            'signature_file' => ['nullable', 'file', 'mimes:jpg,jpeg,png', 'max:2048'],
        ]);

        if (empty($validated['signature_data_url']) && ! $request->hasFile('signature_file')) {
            return $this->error('Please draw your signature to give consent.', [
                'signature' => ['Please draw your signature to give consent.'],
            ], 422);
        }

        $consent = $this->companyPolicyService->signConsent(
            $request->user(),
            $company_policy,
            $validated,
            $request->file('signature_file'),
        );

        $policy = $this->companyPolicyService->resolveForUser($request->user(), $company_policy);

        return $this->success([
            'policy' => new CompanyPolicyResource($policy),
            'consent' => [
                'consent_email' => $consent->consent_email,
                'signature_name' => $consent->signature_name,
                'signed_at_label' => $consent->signed_at?->format('d M Y, h:i A'),
            ],
        ], 'Consent recorded successfully.');
    }

    /** @return array<string, mixed> */
    private function validatedPayload(Request $request, bool $creating): array
    {
        return $request->validate([
            'title' => [$creating ? 'required' : 'sometimes', 'string', 'max:255'],
            'category' => [$creating ? 'required' : 'sometimes', 'string', Rule::in(array_keys(config('company_policies.categories', [])))],
            'description' => ['nullable', 'string', 'max:2000'],
            'body_html' => ['nullable', 'string'],
            'status' => ['nullable', 'string', Rule::in(array_keys(config('company_policies.statuses', [])))],
            'requires_consent' => ['nullable', 'boolean'],
        ]);
    }
}
