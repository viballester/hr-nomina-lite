<?php

namespace App\Http\Requests;

use App\Models\Position;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PositionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $position = $this->route('position');

        return $position
            ? $this->user()->can('update', $position)
            : $this->user()->can('create', Position::class);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('positions', 'name')->ignore($this->route('position'))],
        ];
    }
}
