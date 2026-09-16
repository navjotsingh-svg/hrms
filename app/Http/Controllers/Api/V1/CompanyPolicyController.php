<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Resources\CompanyPolicyResource;
use App\Models\CompanyPolicy;
use App\Services\CompanyPolicyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class CompanyPolicyController extends Controller
{
    use ApiResponse;

    public function __construct(private CompanyPolicyService $companyPolicyService) {}

    public function meta(): JsonResponse
    {
        return $this->success([
            'categories' => collect(config('company_policies.categories', []))->map(fn ($label, $value) => [
                'value' => $value,
                'label' => $label,
            ])->values(),
            'statuses' => collect(config('company_policies.statuses', []))->map(fn ($label, $value) => [
                'value' => $value,
                'label' => $label,
            ])->values(),
            'allowed_mimes' => config('company_policies.allowed_mimes', []),
            'max_file_kb' => (int) config('company_policies.max_file_kb', 10240),
            'can_manage' => request()->user()?->canManageDocuments() ?? false,
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

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validatedPayload($request, true);
        $policy = $this->companyPolicyService->create(
            $request->user(),
            $validated,
            $request->file('file'),
        );

        return $this->success(
            ['policy' => new CompanyPolicyResource($policy)],
            'Policy uploaded successfully.',
            201,
        );
    }

    public function update(Request $request, CompanyPolicy $company_policy): JsonResponse
    {
        $validated = $this->validatedPayload($request, false);
        $policy = $this->companyPolicyService->update(
            $request->user(),
            $company_policy,
            $validated,
            $request->file('file'),
        );

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

    public function download(Request $request, CompanyPolicy $company_policy): BinaryFileResponse
    {
        $policy = $this->companyPolicyService->resolveForUser($request->user(), $company_policy);
        $path = $policy->absoluteFilePath();

        if (! $path || ! is_file($path)) {
            abort(404, 'Policy file not found.');
        }

        return response()->download($path, $policy->original_name);
    }

    /** @return array<string, mixed> */
    private function validatedPayload(Request $request, bool $requireFile): array
    {
        $maxKb = (int) config('company_policies.max_file_kb', 10240);
        $mimes = implode(',', config('company_policies.allowed_mimes', []));

        return $request->validate([
            'title' => [$requireFile ? 'required' : 'sometimes', 'string', 'max:255'],
            'category' => [$requireFile ? 'required' : 'sometimes', 'string', Rule::in(array_keys(config('company_policies.categories', [])))],
            'description' => ['nullable', 'string', 'max:2000'],
            'status' => ['nullable', 'string', Rule::in(array_keys(config('company_policies.statuses', [])))],
            'file' => [
                $requireFile ? 'required' : 'nullable',
                'file',
                'max:'.$maxKb,
                $mimes !== '' ? 'mimes:'.$mimes : 'file',
            ],
        ]);
    }
}
