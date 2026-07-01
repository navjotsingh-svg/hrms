<?php



namespace App\Http\Controllers\Api\V1;



use App\Http\Concerns\ApiResponse;

use App\Http\Controllers\Controller;

use App\Http\Requests\StoreHelpdeskTicketRequest;

use App\Http\Resources\HelpdeskTicketResource;

use App\Models\HelpdeskTicket;

use App\Services\HelpdeskCategoryService;

use App\Services\HelpdeskService;

use Illuminate\Http\JsonResponse;

use Illuminate\Http\Request;

use Illuminate\Validation\Rule;



class HelpdeskTicketController extends Controller

{

    use ApiResponse;



    public function __construct(

        private HelpdeskService $helpdeskService,

        private HelpdeskCategoryService $categoryService,

    ) {}



    public function meta(Request $request): JsonResponse

    {

        $user = $request->user();

        $categories = $this->categoryService->activeCategoriesForCompany($user->company_id);



        return $this->success([

            'categories' => $categories->map(fn ($category) => [

                'value' => $category->id,

                'label' => $category->name,

            ])->values(),

            'priorities' => collect(config('helpdesk.priorities', []))->map(fn ($label, $value) => [

                'value' => $value,

                'label' => $label,

            ])->values(),

            'statuses' => collect(config('helpdesk.statuses', []))->map(fn ($label, $value) => [

                'value' => $value,

                'label' => $label,

            ])->values(),

            'can_manage_categories' => $user->canManageHelpdesk(),

        ]);

    }



    public function summary(Request $request): JsonResponse

    {

        return $this->success([

            'open_count' => $this->helpdeskService->openCountForManagers($request->user()),

        ]);

    }



    public function index(Request $request): JsonResponse

    {

        $validated = $request->validate([

            'employee_id' => ['nullable', 'integer', 'exists:employees,id'],

            'status' => ['nullable', Rule::in(array_keys(config('helpdesk.statuses', [])))],

            'helpdesk_category_id' => ['nullable', 'integer', 'exists:helpdesk_categories,id'],

            'priority' => ['nullable', Rule::in(array_keys(config('helpdesk.priorities', [])))],

            'search' => ['nullable', 'string', 'max:255'],

            'per_page' => ['nullable', 'integer', Rule::in([10, 25, 50])],

            'page' => ['nullable', 'integer', 'min:1'],

        ]);



        $tickets = $this->helpdeskService->listForUser($request->user(), $validated);



        return $this->success([

            'tickets' => HelpdeskTicketResource::collection($tickets->items()),

            'pagination' => [

                'current_page' => $tickets->currentPage(),

                'last_page' => $tickets->lastPage(),

                'per_page' => $tickets->perPage(),

                'total' => $tickets->total(),

                'from' => $tickets->firstItem(),

                'to' => $tickets->lastItem(),

            ],

        ]);

    }



    public function store(StoreHelpdeskTicketRequest $request): JsonResponse

    {

        $ticket = $this->helpdeskService->create(

            $request->user(),

            $request->validated(),

            $request->file('attachments', []) ?? [],

        );



        return $this->success([

            'ticket' => new HelpdeskTicketResource($ticket),

        ], 'Helpdesk ticket created.', 201);

    }



    public function show(Request $request, HelpdeskTicket $helpdesk_ticket): JsonResponse

    {

        $ticket = $this->helpdeskService->showForUser($request->user(), $helpdesk_ticket);



        return $this->success([

            'ticket' => new HelpdeskTicketResource($ticket),

        ]);

    }



    public function addComment(Request $request, HelpdeskTicket $helpdesk_ticket): JsonResponse

    {

        $validated = $request->validate([

            'body' => ['required', 'string', 'max:5000'],

            'is_internal' => ['nullable', 'boolean'],

        ]);



        $comment = $this->helpdeskService->addComment($request->user(), $helpdesk_ticket, $validated);

        $ticket = $this->helpdeskService->showForUser($request->user(), $helpdesk_ticket->fresh());



        return $this->success([

            'comment' => [

                'id' => $comment->id,

                'body' => $comment->body,

                'is_internal' => $comment->is_internal,

                'created_at_label' => $comment->created_at?->format('d M Y, h:i A'),

                'user' => $comment->user ? [

                    'id' => $comment->user->id,

                    'name' => $comment->user->name,

                ] : null,

            ],

            'ticket' => new HelpdeskTicketResource($ticket),

        ], 'Comment added.');

    }



    public function updateStatus(Request $request, HelpdeskTicket $helpdesk_ticket): JsonResponse

    {

        $validated = $request->validate([

            'status' => ['required', Rule::in(array_keys(config('helpdesk.statuses', [])))],

        ]);



        $ticket = $this->helpdeskService->updateStatus(

            $request->user(),

            $helpdesk_ticket,

            $validated['status'],

        );



        return $this->success([

            'ticket' => new HelpdeskTicketResource($ticket),

        ], 'Ticket status updated.');

    }

}

