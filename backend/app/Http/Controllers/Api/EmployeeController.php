<?php

namespace App\Http\Controllers\Api;

use Illuminate\Support\Facades\Gate;
use App\Http\Requests\UpdateEmployeeRequest;
use App\Http\Requests\StoreEmployeeRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use App\Http\Controllers\Controller;
use App\Models\Employee;
use Illuminate\Http\Request;
use App\Http\Resources\EmployeeResource;
use Illuminate\Support\Arr;

class EmployeeController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        Gate::authorize('viewAny', Employee::class);
        $user = $request->user();

        $search = $request->query('search');

        $employees = Employee::with(['user', 'department', 'position'])
            ->when($search, function ($query, $search) {
                $query->whereHas('user', function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->when($user->role === 'manager', fn ($q) => $q->where('department_id', $user->employee?->department_id))
            ->orderBy('id')
            ->paginate(15)
            ->withQueryString();

        return EmployeeResource::collection($employees);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreEmployeeRequest $request)
    {
        $data = $request->validated();

        $employee = DB::transaction(function () use ($data) {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make(Str::password(16)),
            ]);

            return Employee::create([
                'user_id' => $user->id,
                'department_id' => $data['department_id'],
                'position_id' => $data['position_id'],
                'hire_date' => $data['hire_date'],
            ]);
        });

        $employee = $employee->fresh(['user', 'department', 'position']);

        return (new EmployeeResource($employee))->response()->setStatusCode(201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Employee $employee)
    {
        Gate::authorize('view', $employee);
        $employee->load(['user', 'department', 'position']);

        return new EmployeeResource($employee);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateEmployeeRequest $request, Employee $employee)
    {
        $data = $request->validated();

        DB::transaction(function () use ($data, $employee) {
            $employee->user->update(Arr::only($data, ['name', 'email']));
            $employee->update(Arr::only($data, ['department_id', 'position_id', 'hire_date', 'status']));
        });

        return new EmployeeResource($employee->fresh(['user', 'department', 'position']));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Employee $employee)
    {
        Gate::authorize('delete', $employee);
        $employee->update(['status' => 'inactive']);

        return response()->noContent();
    }
}
