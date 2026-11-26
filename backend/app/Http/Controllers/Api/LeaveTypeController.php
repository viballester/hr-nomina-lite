<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LeaveType;

class LeaveTypeController extends Controller
{
    // Catálogo de solo lectura que necesita cualquier usuario para solicitar una ausencia:
    // basta con estar autenticado, no hace falta Policy.
    public function index()
    {
        return response()->json([
            'data' => LeaveType::orderBy('name')->get(['id', 'name', 'requires_approval']),
        ]);
    }
}
