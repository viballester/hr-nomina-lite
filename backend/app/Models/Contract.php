<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

#[Fillable(['employee_id', 'type', 'base_salary', 'start_date', 'end_date'])]
class Contract extends Model
{
    use HasFactory;
    public function employee(): BelongsTo
    {
       return $this->belongsTo(Employee::class);
    }

    protected function casts(): array
    {
        return [
            'base_salary' => 'decimal:2',
            'start_date' => 'date',
            'end_date' => 'date'
        ];
    }
}
