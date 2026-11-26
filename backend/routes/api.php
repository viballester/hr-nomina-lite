<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\EmployeeController;
use App\Http\Controllers\Api\DepartmentController;
use App\Http\Controllers\Api\PositionController;
use App\Http\Controllers\Api\LeaveRequestController;
use App\Http\Controllers\Api\LeaveTypeController;
use App\Http\Controllers\Api\PayrollPeriodController;
use App\Http\Controllers\Api\PayslipController;
use App\Http\Controllers\Api\DashboardController;

Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');

Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
    Route::get('/dashboard', DashboardController::class);
    Route::apiResource('employees', EmployeeController::class);
    Route::apiResource('departments', DepartmentController::class);
    Route::apiResource('positions', PositionController::class);
    Route::get('/leave-balance', [LeaveRequestController::class, 'balance']);
    Route::get('/leave-types', [LeaveTypeController::class, 'index']);
    Route::apiResource('leave-requests', LeaveRequestController::class)->only(['index', 'store']);
    Route::patch('leave-requests/{leaveRequest}/approve', [LeaveRequestController::class, 'approve']);
    Route::patch('leave-requests/{leaveRequest}/reject', [LeaveRequestController::class, 'reject']);
    Route::patch('leave-requests/{leaveRequest}/cancel', [LeaveRequestController::class, 'cancel']);
    Route::apiResource('payroll-periods', PayrollPeriodController::class)->only(['index', 'store']);
    Route::post('payroll-periods/{payrollPeriod}/generate', [PayrollPeriodController::class, 'generate']);
    Route::patch('payroll-periods/{payrollPeriod}/close', [PayrollPeriodController::class, 'close']);
    Route::apiResource('payslips', PayslipController::class)->only(['index', 'show']);
    Route::get('payslips/{payslip}/pdf', [PayslipController::class, 'pdf']);
});
