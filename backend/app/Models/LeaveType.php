<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'requires_approval', 'deducts_balance'])]
class LeaveType extends Model
{
    public function leaveRequests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class);
    }

    protected function casts(): array 
    {
        return [
            'requires_approval' => 'boolean',
            'deducts_balance' => 'boolean'
        ];
    }
}
