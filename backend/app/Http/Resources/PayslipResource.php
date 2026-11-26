<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PayslipResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            // Nombre del snapshot: el que tenía al generarse la nómina
            'employee' => $this->snapshot['employee_name'],
            'period' => new PayrollPeriodResource($this->payrollPeriod),
            'gross' => $this->gross,
            'deductions' => $this->deductions,
            'net' => $this->net,
            // Detalle solo cuando se cargan las líneas (ficha de una nómina)
            'lines' => $this->whenLoaded('lines', fn () => $this->lines->map->only(['concept', 'type', 'amount'])),
            'snapshot' => $this->whenLoaded('lines', fn () => $this->snapshot),
        ];
    }
}
