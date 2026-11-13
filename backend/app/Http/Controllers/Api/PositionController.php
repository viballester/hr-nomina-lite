<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Position;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use App\Http\Requests\PositionRequest;
use App\Http\Resources\PositionResource;

class PositionController extends Controller
{
    public function index()
    {
        Gate::authorize('viewAny', Position::class);

        return PositionResource::collection(Position::orderBy('name')->get());
    }

    public function store(PositionRequest $request)
    {
        $position = Position::create($request->validated());

        return (new PositionResource($position))->response()->setStatusCode(201);
    }

    public function show(Position $position)
    {
        Gate::authorize('view', $position);

        return new PositionResource($position);
    }

    public function update(PositionRequest $request, Position $position)
    {
        $position->update($request->validated());

        return new PositionResource($position);
    }

    public function destroy(Position $position)
    {
        Gate::authorize('delete', $position);

        if ($position->employees()->exists()) {
            return response()->json(['message' => 'No se puede borrar: el puesto tiene empleados.'], 409);
        }

        $position->delete();

        return response()->noContent();
    }
}
