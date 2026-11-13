<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;


class EmployeeResource extends JsonResource
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
            'name' => $this->user->name,
            'email' => $this->user->email,
            'hire_date' => $this->hire_date->toDateString(),
            'status' => $this->status,
            'department' => ['id' => $this->department->id, 'name' => $this->department->name],
            'position' => ['id' => $this->position->id, 'name' => $this->position->name],
        ];
    }
}
