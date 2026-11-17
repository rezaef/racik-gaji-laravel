<?php

use App\Http\Controllers\Api\AttendanceController;
use App\Http\Controllers\Api\AttendanceCorrectionController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CompanyController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\EmployeeController;
use App\Http\Controllers\Api\LeaveRequestController;
use App\Http\Controllers\Api\OvertimeRequestController;
use App\Http\Controllers\Api\PayrollController;
use App\Http\Controllers\Api\PayslipController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:10,1');
    Route::get('demo-accounts', [AuthController::class, 'demoAccounts']);
    Route::post('demo-login', [AuthController::class, 'demoLogin']);
});

// Integrasi server-ke-server dengan aplikasi absensi (absensi-laravel).
Route::middleware('integration')->prefix('integration')->group(function () {
    Route::get('employees', [\App\Http\Controllers\Api\IntegrationController::class, 'employees']);
    Route::post('attendances', [\App\Http\Controllers\Api\IntegrationController::class, 'attendances']);
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('auth/me', [AuthController::class, 'me']);
    Route::post('auth/logout', [AuthController::class, 'logout']);

    Route::get('company', [CompanyController::class, 'show']);
    Route::get('dashboard', DashboardController::class);

    // Absensi & pengajuan (semua role yang punya data karyawan)
    Route::get('attendances', [AttendanceController::class, 'index']);
    Route::get('attendances/today', [AttendanceController::class, 'today']);
    Route::post('attendances/clock-in', [AttendanceController::class, 'clockIn']);
    Route::post('attendances/clock-out', [AttendanceController::class, 'clockOut']);

    foreach ([
        'leave-requests' => LeaveRequestController::class,
        'overtime-requests' => OvertimeRequestController::class,
        'attendance-corrections' => AttendanceCorrectionController::class,
    ] as $uri => $controller) {
        Route::get($uri, [$controller, 'index']);
        Route::post($uri, [$controller, 'store']);
        Route::middleware('role:manager,hr_admin')->group(function () use ($uri, $controller) {
            Route::post("$uri/{id}/approve", [$controller, 'approve'])->whereNumber('id');
            Route::post("$uri/{id}/reject", [$controller, 'reject'])->whereNumber('id');
        });
    }

    // Slip gaji (ESS)
    Route::get('payslips/mine', [PayslipController::class, 'mine']);
    Route::get('payslips/{payslip}', [PayslipController::class, 'show']);
    Route::get('payslips/{payslip}/pdf', [PayslipController::class, 'pdf']);

    Route::get('employees', [EmployeeController::class, 'index'])->middleware('role:hr_admin,manager');

    Route::middleware('role:hr_admin')->group(function () {
        Route::put('company', [CompanyController::class, 'update']);

        Route::post('employees', [EmployeeController::class, 'store']);
        Route::get('employees/{employee}', [EmployeeController::class, 'show']);
        Route::put('employees/{employee}', [EmployeeController::class, 'update']);
        Route::delete('employees/{employee}', [EmployeeController::class, 'destroy']);

        Route::get('payroll/periods', [PayrollController::class, 'index']);
        Route::post('payroll/generate', [PayrollController::class, 'generate']);
        Route::get('payroll/periods/{period}', [PayrollController::class, 'show']);
        Route::post('payroll/periods/{period}/finalize', [PayrollController::class, 'finalize']);
        Route::post('payroll/periods/{period}/mark-paid', [PayrollController::class, 'markPaid']);
        Route::get('payroll/periods/{period}/export', [PayrollController::class, 'exportCsv']);
    });
});
