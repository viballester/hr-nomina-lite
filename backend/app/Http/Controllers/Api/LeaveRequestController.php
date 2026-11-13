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

        $requests = LeaveRequest::with(['leaveType', 'employee.user'])
            ->when($user->role === 'employee', fn ($q) => $q->where('employee_id', $user->employee->id))
            ->when($user->role === 'manager', fn ($q) => $q->whereHas('employee', fn ($e) => $e->where('department_id', $user->employee->department_id)))
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
}
