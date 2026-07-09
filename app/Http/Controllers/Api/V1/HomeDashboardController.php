<?php



namespace App\Http\Controllers\Api\V1;



use App\Http\Controllers\Controller;

use App\Http\Concerns\ApiResponse;

use App\Services\DateRangePresetService;

use App\Services\HomeDashboardService;

use Illuminate\Http\JsonResponse;

use Illuminate\Http\Request;



class HomeDashboardController extends Controller

{

    use ApiResponse;



    public function __construct(private HomeDashboardService $homeDashboardService) {}



    public function index(Request $request): JsonResponse

    {

        $user = $request->user();

        $rangeInput = $this->rangeInputFromRequest($request);

        $range = app(DateRangePresetService::class)->resolve($rangeInput);



        return $this->success([

            'date_range_presets' => $this->homeDashboardService->dateRangePresets(),

            'date_range' => [

                'preset' => $range['preset'],

                'from_date' => $range['from_date'],

                'to_date' => $range['to_date'],

            ],

            'available_widgets' => $this->homeDashboardService->availableWidgets($user),

            'widgets' => $this->homeDashboardService->widgetsWithData($user, $rangeInput),

        ]);

    }



    public function syncWidgets(Request $request): JsonResponse

    {

        $validated = $request->validate([

            'widgets' => ['required', 'array', 'min:1'],

            'widgets.*' => ['required', 'string', 'max:100'],

            'range' => ['nullable', 'string', 'max:30'],

            'from_date' => ['nullable', 'date'],

            'to_date' => ['nullable', 'date', 'after_or_equal:from_date'],

        ]);



        $rangeInput = $this->rangeInputFromRequest($request);

        $widgets = $this->homeDashboardService->syncWidgets($request->user(), $validated['widgets']);



        return $this->success([

            'widgets' => collect($widgets)

                ->map(function (array $widget) use ($request, $rangeInput) {

                    $widget['data'] = $this->homeDashboardService->chartData($request->user(), $widget['key'], $rangeInput);



                    return $widget;

                })

                ->all(),

        ], 'Dashboard widgets updated.');

    }



    /** @return array<string, mixed> */

    private function rangeInputFromRequest(Request $request): array

    {

        return array_filter([

            'range' => $request->input('range'),

            'from_date' => $request->input('from_date'),

            'to_date' => $request->input('to_date'),

        ], fn ($value) => $value !== null && $value !== '');

    }

}


