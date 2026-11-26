<?php

namespace App\Http\Requests;

use App\Models\PayrollPeriod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePayrollPeriodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manage', PayrollPeriod::class);
    }

    public function rules(): array
    {
        return [
            'year' => ['required', 'integer', 'between:2000,2100'],
            'month' => [
                'bail', 'required', 'integer', 'between:1,12',
                Rule::unique('payroll_periods', 'month')->where('year', (int) $this->input('year')),
            ],
        ];
    }

    public function messages(): array
    {
        return ['month.unique' => 'Ya existe un periodo para ese mes.'];
    }
}
