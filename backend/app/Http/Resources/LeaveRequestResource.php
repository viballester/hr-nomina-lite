<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LeaveRequestResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'employee' => $this->whenLoaded('employee', fn () => $this->employee->user->name),
            'leave_type' => $this->leaveType->name,
            'start_date' => $this->start_date->toDateString(),
            'end_date' => $this->end_date->toDateString(),
            'days' => $this->days,
            'status' => $this->status,
            'review_comment' => $this->review_comment,
            // Qué puede hacer el usuario actual, según la misma Policy que protege los endpoints.
            // Así el frontend no duplica reglas como "nadie se aprueba a sí mismo".
            'can' => [
                'review' => $request->user()?->can('review', $this->resource) ?? false,
                'cancel' => $request->user()?->can('cancel', $this->resource) ?? false,
            ],
        ];
    }
}
