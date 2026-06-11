<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Concerns\ApiResponse;
use App\Http\Requests\StoreCompanyRequest;
use App\Http\Requests\UpdateCompanyRequest;
use App\Http\Resources\CompanyResource;
use App\Models\Company;
use App\Services\CompanyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CompanyController extends Controller
{
    use ApiResponse;

    public function __construct(private CompanyService $companyService) {}

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
            'per_page' => ['nullable', 'integer', Rule::in([10, 25, 50])],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $query = Company::query()->with('adminUser');

        if ($name = trim((string) $request->input('name'))) {
            $query->where('name', 'like', "%{$name}%");
        }

        if ($city = trim((string) $request->input('city'))) {
            $query->where('city', 'like', "%{$city}%");
        }

        $summaryQuery = clone $query;

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $companies = $query
            ->latest()
            ->paginate($request->integer('per_page', 10))
            ->withQueryString();

        return $this->success([
            'companies' => CompanyResource::collection($companies->items()),
            'pagination' => [
                'current_page' => $companies->currentPage(),
                'last_page' => $companies->lastPage(),
                'per_page' => $companies->perPage(),
                'total' => $companies->total(),
                'from' => $companies->firstItem(),
                'to' => $companies->lastItem(),
            ],
            'summary' => [
                'total' => (clone $summaryQuery)->count(),
                'active' => (clone $summaryQuery)->where('status', 'active')->count(),
                'inactive' => (clone $summaryQuery)->where('status', 'inactive')->count(),
            ],
        ]);
    }

    public function store(StoreCompanyRequest $request): JsonResponse
    {
        $result = $this->companyService->create(
            $request->validated(),
            $request->file('logo')
        );

        return $this->success(
            ['company' => new CompanyResource($result['company'])],
            $result['message'],
            201
        );
    }

    public function show(Company $company): JsonResponse
    {
        $company->load('adminUser');

        return $this->success([
            'company' => new CompanyResource($company),
        ]);
    }

    public function update(UpdateCompanyRequest $request, Company $company): JsonResponse
    {
        $company = $this->companyService->update(
            $company,
            $request->validated(),
            $request->file('logo')
        );

        return $this->success(
            ['company' => new CompanyResource($company)],
            'Company updated successfully.'
        );
    }

    public function destroy(Company $company): JsonResponse
    {
        $this->companyService->delete($company);

        return $this->success(null, 'Company deleted successfully.');
    }

    public function updateStatus(Request $request, Company $company): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ]);

        $company->update(['status' => $validated['status']]);

        return $this->success(
            ['company' => new CompanyResource($company->fresh())],
            'Company status updated successfully.'
        );
    }

    public function suggestions(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'field' => ['required', Rule::in(['name', 'city'])],
            'q' => ['nullable', 'string', 'max:255'],
        ]);

        $term = trim((string) ($validated['q'] ?? ''));

        if ($term === '') {
            return $this->success(['suggestions' => []]);
        }

        $column = $validated['field'];

        $suggestions = Company::query()
            ->whereNotNull($column)
            ->where($column, '!=', '')
            ->where($column, 'like', "%{$term}%")
            ->distinct()
            ->orderBy($column)
            ->limit(10)
            ->pluck($column)
            ->values();

        return $this->success([
            'suggestions' => $suggestions,
        ]);
    }

    public function checkField(Request $request): JsonResponse
    {
        $validated = $this->companyService->validateCheckFieldRequest($request->all());

        $result = $this->companyService->validateField(
            $validated['field'],
            trim((string) ($validated['value'] ?? '')),
            $validated['company_id'] ?? null
        );

        return $this->success($result);
    }
}
