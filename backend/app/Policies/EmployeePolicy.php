<?php

namespace App\Policies;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class EmployeePolicy
{
    public function before(User $user): ?bool
    {
        return $user->role === 'admin' ? true : null;
    }
    
    public function viewAny(User $user): bool
    {
        return $user->role === 'manager';
    }
    
    public function view(User $user, Employee $employee): bool
    {
        if ($user->role === 'manager') {
            return $employee->department_id === $user->employee?->department_id;
        }
    
        return $employee->user_id === $user->id;
    }
    
    public function create(User $user): bool
    {
        return false;
    }
    
    public function update(User $user, Employee $employee): bool
    {
        return false;
    }
    
    public function delete(User $user, Employee $employee): bool
    {
        return false;
    }
}
