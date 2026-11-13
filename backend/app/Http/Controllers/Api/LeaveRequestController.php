<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreLeaveRequestRequest;
use App\Http\Resources\LeaveRequestResource;
use App\Models\LeaveType;
use App\Services\LeaveRequestService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class LeaveRequestController extends Controller
{
    public function index(Request $request)
    {
        // shortcut: solo las propias; el filtro por rol llega con la Policy en la tarea 5
        $requests = $request->user()->employee?->leaveRequests()
            ->with('leaveType')
            ->latest('start_date')
            ->paginate(15);

        abort_if($requests === null, 403, 'Tu usuario no tiene ficha de empleado.');

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
}
