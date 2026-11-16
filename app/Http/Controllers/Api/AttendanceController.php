<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ClockInRequest;
use App\Models\Attendance;
use App\Models\Employee;
use App\Services\AttendanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AttendanceController extends Controller
{
    public function __construct(private AttendanceService $service) {}

    /** Riwayat absensi: karyawan melihat miliknya, manajer melihat tim, HR melihat semua. */
    public function index(Request $request): JsonResponse
    {
        $request->validate(['month' => ['sometimes', 'date_format:Y-m'], 'mode' => ['sometimes', 'in:'.implode(',', Attendance::MODES)]]);

        $month = \Carbon\Carbon::createFromFormat('Y-m', $request->input('month', now()->format('Y-m')))->startOfMonth();

        $query = Attendance::with('employee:id,full_name,position')
            ->whereBetween('date', [$month->toDateString(), $month->copy()->endOfMonth()->toDateString()])
            ->when($request->mode, fn ($q, $mode) => $q->where('mode', $mode))
            ->when($request->employee_id, fn ($q, $id) => $q->where('employee_id', $id))
            ->latest('date')->orderBy('employee_id');

        $query = $this->scopeToViewer($query, $request->user(), 'employee_id');

        return response()->json($query->paginate($request->integer('per_page', 31)));
    }

    public function today(Request $request): JsonResponse
    {
        $attendance = Attendance::where('employee_id', $this->employee($request)->id)->whereDate('date', today())->first();

        return response()->json(['data' => $attendance]);
    }

    public function clockIn(ClockInRequest $request): JsonResponse
    {
        $attendance = $this->service->clockIn($this->employee($request), $request->mode, $request->client_name);

        return response()->json(['data' => $attendance], 201);
    }

    public function clockOut(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->service->clockOut($this->employee($request))]);
    }

    private function employee(Request $request): Employee
    {
        return $request->user()->employee ?? abort(403, 'Akun ini tidak terhubung ke data karyawan.');
    }
}
