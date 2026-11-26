<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePayrollPeriodRequest;
use App\Http\Resources\PayrollPeriodResource;
use App\Models\PayrollPeriod;
use App\Services\Payroll\PayrollService;
use Illuminate\Support\Facades\Gate;

class PayrollPeriodController extends Controller
{
    public function index()
    {
        Gate::authorize('manage', PayrollPeriod::class);

        return PayrollPeriodResource::collection(
            PayrollPeriod::withCount('payslips')->latest('year')->latest('month')->paginate(15)
        );
    }

    public function store(StorePayrollPeriodRequest $request)
    {
        // refresh() trae el status por defecto que pone la base de datos
        $period = PayrollPeriod::create($request->validated())->refresh()->loadCount('payslips');

        return (new PayrollPeriodResource($period))->response()->setStatusCode(201);
    }

    public function generate(PayrollPeriod $payrollPeriod, PayrollService $service)
    {
        Gate::authorize('manage', PayrollPeriod::class);
        $service->generate($payrollPeriod);

        return new PayrollPeriodResource($payrollPeriod->loadCount('payslips'));
    }

    public function close(PayrollPeriod $payrollPeriod, PayrollService $service)
    {
        Gate::authorize('manage', PayrollPeriod::class);

        return new PayrollPeriodResource($service->close($payrollPeriod)->loadCount('payslips'));
    }
}
