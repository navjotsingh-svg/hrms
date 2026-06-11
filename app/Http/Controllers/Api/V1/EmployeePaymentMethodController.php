<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Concerns\ApiResponse;
use App\Http\Requests\RejectEmployeeDocumentRequest;
use App\Http\Resources\EmployeePaymentMethodResource;
use App\Models\EmployeePaymentMethod;
use App\Services\EmployeePaymentMethodService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmployeePaymentMethodController extends Controller
{
    use ApiResponse;

    public function __construct(private EmployeePaymentMethodService $paymentMethodService) {}

    public function pending(Request $request): JsonResponse
    {
        if (! $request->user()->canReviewEmployeeDocuments()) {
            return $this->error('You are not allowed to review payment options.', null, 403);
        }

        $methods = $this->paymentMethodService->pendingForReviewer($request->user());

        return $this->success([
            'payment_methods' => EmployeePaymentMethodResource::collection($methods),
        ]);
    }

    public function approve(Request $request, EmployeePaymentMethod $employeePaymentMethod): JsonResponse
    {
        $this->paymentMethodService->assertBelongsToCompany($request->user(), $employeePaymentMethod);
        $method = $this->paymentMethodService->approve($request->user(), $employeePaymentMethod);

        return $this->success(
            ['payment_method' => new EmployeePaymentMethodResource($method)],
            'Payment option approved successfully.'
        );
    }

    public function reject(RejectEmployeeDocumentRequest $request, EmployeePaymentMethod $employeePaymentMethod): JsonResponse
    {
        $this->paymentMethodService->assertBelongsToCompany($request->user(), $employeePaymentMethod);
        $method = $this->paymentMethodService->reject(
            $request->user(),
            $employeePaymentMethod,
            $request->validated('notes')
        );

        return $this->success(
            ['payment_method' => new EmployeePaymentMethodResource($method)],
            'Payment option rejected. The employee can re-submit after reviewing your feedback.'
        );
    }
}
