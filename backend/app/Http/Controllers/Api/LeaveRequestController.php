<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreLeaveRequestRequest;
use App\Http\Resources\LeaveRequestResource;
use App\Models\LeaveType;
use App\Services\LeaveRequestService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use App\Models\LeaveRequest;
use Illuminate\Support\Facades\Gate;

class LeaveRequestController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        abort_if($user->employee === null && $user->role !== 'admin', 403, 'Tu usuario no tiene ficha de empleado.');

        // Cerrado por defecto: un rol desconocido no ve nada
        $query = match ($user->role) {
            'admin' => LeaveRequest::query(),
            'manager' => LeaveRequest::whereHas('employee', fn ($e) => $e->where('department_id', $user->employee->department_id)),
            'employee' => LeaveRequest::where('employee_id', $user->employee->id),
            default => abort(403),
        };

        $requests = $query->with(['leaveType', 'employee.user'])
            ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status))
            ->latest('start_date')
            ->paginate(15)
            ->withQueryString();

        return LeaveRequestResource::collection($requests);
    }

    public function store(StoreLeaveRequestRequest $request, LeaveRequestService $service)
    {
        $data = $request->validated();

        $leaveRequest = $service->create(
            $request->user()->employee,
            LeaveType::findOrFail($data['leave_type_id']),
            Carbon::parse($data['start_date']),
            Carbon::parse($data['end_date']),
        );

        return (new LeaveRequestResource($leaveRequest->load('leaveType')))
            ->response()
            ->setStatusCode(201);
    }

    public function approve(Request $request, LeaveRequest $leaveRequest, LeaveRequestService $service)
    {
        Gate::authorize('review', $leaveRequest);

        return new LeaveRequestResource($service->approve($leaveRequest, $request->user()->employee)->load('leaveType'));
    }

    public function reject(Request $request, LeaveRequest $leaveRequest, LeaveRequestService $service)
    {
        Gate::authorize('review', $leaveRequest);
        $data = $request->validate(['comment' => ['required', 'string', 'max:500']]);

        return new LeaveRequestResource($service->reject($leaveRequest, $request->user()->employee, $data['comment'])->load('leaveType'));
    }

    public function cancel(LeaveRequest $leaveRequest, LeaveRequestService $service)
    {
        Gate::authorize('cancel', $leaveRequest);

        return new LeaveRequestResource($service->cancel($leaveRequest)->load('leaveType'));
    }


    public function balance(Request $request, LeaveRequestService $service)
    {
        $employee = $request->user()->employee;

        if ($employee === null) {
            abort(403, 'No tienes una ficha de empleado.');
        }

        $summary = $service->balanceSummary(
            $employee,
            now()->year
        );

        if ($summary === null) {
            abort(404, 'No hay saldo para este año.');
        }

        return response()->json([
            'data' => $summary,
        ]);
    }
}
