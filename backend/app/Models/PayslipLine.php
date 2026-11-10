<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['payslip_id', 'concept', 'type', 'amount'])]
class PayslipLine extends Model
{
    public function payslip(): BelongsTo
    {
        return $this->belongsTo(Payslip::class);
    }

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2'
        ];
    }
}
