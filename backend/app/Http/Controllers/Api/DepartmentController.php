<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\DepartmentRequest;
use App\Http\Resources\DepartmentResource;
use App\Models\Department;
use Illuminate\Support\Facades\Gate;

class DepartmentController extends Controller
{
    public function index()
    {
        Gate::authorize('viewAny', Department::class);

        return DepartmentResource::collection(Department::orderBy('name')->get());
    }

    public function store(DepartmentRequest $request)
    {
        $department = Department::create($request->validated());

        return (new DepartmentResource($department))->response()->setStatusCode(201);
    }

    public function show(Department $department)
    {
        Gate::authorize('view', $department);

        return new DepartmentResource($department);
    }

    public function update(DepartmentRequest $request, Department $department)
    {
        $department->update($request->validated());

        return new DepartmentResource($department);
    }

    public function destroy(Department $department)
    {
        Gate::authorize('delete', $department);

        if ($department->employees()->exists()) {
            return response()->json(['message' => 'No se puede borrar: el departamento tiene empleados.'], 409);
        }

        $department->delete();

        return response()->noContent();
    }
}