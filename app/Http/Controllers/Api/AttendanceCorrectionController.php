<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\StoreCorrectionRequest;
use App\Models\Attendance;
use App\Models\AttendanceCorrection;
use App\Models\CompanySetting;
use App\Services\AttendanceService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;

class AttendanceCorrectionController extends ApprovalController
{
    public function __construct(private AttendanceService $attendance) {}

    protected function model(): string
    {
        return AttendanceCorrection::class;
    }

    public function store(StoreCorrectionRequest $request): JsonResponse
    {
        $employee = $request->user()->employee ?? abort(403);

        return response()->json(['data' => AttendanceCorrection::create(['employee_id' => $employee->id] + $request->validated())], 201);
    }

    /** Koreksi disetujui -> catatan absensi dibuat/diperbarui dan status keterlambatan dihitung ulang. */
    protected function onApproved(Model $item): void
    {
        $late = $this->attendance->lateMinutes($item->clock_in, CompanySetting::current());

        Attendance::updateOrCreate(
            ['employee_id' => $item->employee_id, 'date' => $item->date->toDateString()],
            [
                'mode' => $item->mode,
                'clock_in' => $item->clock_in,
                'clock_out' => $item->clock_out,
                'status' => $late > 0 ? 'late' : 'present',
                'late_minutes' => $late,
                'work_minutes' => $this->attendance->workMinutes($item->clock_in, $item->clock_out),
                'notes' => 'Dikoreksi #'.$item->id,
            ],
        );
    }
}
