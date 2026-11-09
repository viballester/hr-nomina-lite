<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['year', 'month', 'status'])]
class PayrollPeriod extends Model
{
    public function payslips(): HasMany
    {
        return $this->hasMany(Payslip::class);
    }
}
