<?php



namespace App\Http\Resources;



use Illuminate\Http\Request;

use Illuminate\Http\Resources\Json\JsonResource;



class HelpdeskTicketResource extends JsonResource

{

    public function toArray(Request $request): array

    {

        $priorities = config('helpdesk.priorities', []);

        $statuses = config('helpdesk.statuses', []);



        return [

            'id' => $this->id,

            'ticket_number' => $this->ticket_number,

            'subject' => $this->subject,

            'description' => $this->description,

            'helpdesk_category_id' => $this->helpdesk_category_id,

            'category_label' => $this->when(

                $this->relationLoaded('category') && $this->category,

                fn () => $this->category->name,

                '—',

            ),

            'priority' => $this->priority,

            'priority_label' => $priorities[$this->priority] ?? ucfirst($this->priority),

            'status' => $this->status,

            'status_label' => $statuses[$this->status] ?? ucfirst(str_replace('_', ' ', $this->status)),

            'created_at_label' => $this->created_at?->labelStack(),

            'updated_at_label' => $this->updated_at?->labelStack(),

            'resolved_at_label' => $this->resolved_at?->labelStack(),

            'closed_at_label' => $this->closed_at?->labelStack(),

            'employee' => $this->when($this->relationLoaded('employee'), fn () => [

                'id' => $this->employee->id,

                'full_name' => $this->employee->full_name,

                'employee_code' => $this->employee->employee_code,

            ]),

            'created_by' => $this->when($this->relationLoaded('createdBy') && $this->createdBy, fn () => [

                'id' => $this->createdBy->id,

                'name' => $this->createdBy->name,

            ]),

            'assigned_to' => $this->when($this->relationLoaded('assignedTo') && $this->assignedTo, fn () => [

                'id' => $this->assignedTo->id,

                'name' => $this->assignedTo->name,

            ]),

            'resolved_by' => $this->when($this->relationLoaded('resolvedBy') && $this->resolvedBy, fn () => [

                'id' => $this->resolvedBy->id,

                'name' => $this->resolvedBy->name,

            ]),

            'attachments' => $this->when($this->relationLoaded('attachments'), fn () => $this->attachments->map(fn ($file) => [

                'id' => $file->id,

                'original_name' => $file->original_name,

                'file_url' => $file->fileUrl(),

                'mime_type' => $file->mime_type,

                'file_size' => $file->file_size,

            ])),

            'comments' => $this->when($this->relationLoaded('comments'), fn () => $this->comments->map(fn ($comment) => [

                'id' => $comment->id,

                'body' => $comment->body,

                'is_internal' => $comment->is_internal,

                'created_at_label' => $comment->created_at?->labelStack(),

                'user' => $comment->user ? [

                    'id' => $comment->user->id,

                    'name' => $comment->user->name,

                ] : null,

            ])->values()->all()),

            'can_manage' => $request->user()?->canManageHelpdeskTicket($this->resource) ?? false,

            'can_comment' => $request->user()?->canCommentOnHelpdeskTicket($this->resource) ?? false,

        ];

    }

}

