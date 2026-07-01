<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Services\HelpdeskCategoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class HelpdeskCategoryController extends Controller
{
    use ApiResponse;

    public function __construct(private HelpdeskCategoryService $categoryService) {}

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
        ]);

        $category = $this->categoryService->create($request->user(), $validated);

        return $this->success([
            'category' => $this->formatCategory($category),
        ], 'Helpdesk category created.', 201);
    }

    private function formatCategory($category): array
    {
        return [
            'id' => $category->id,
            'name' => $category->name,
            'sort_order' => $category->sort_order,
            'status' => $category->status,
        ];
    }
}
