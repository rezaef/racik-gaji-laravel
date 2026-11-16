<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\CompanySetting;
use App\Models\Employee;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

class AttendanceService
{
    public function clockIn(Employee $employee, string $mode, ?string $clientName, ?Carbon $now = null): Attendance
    {
        $now ??= now();
        $settings = CompanySetting::current();

        if (! in_array($now->isoWeekday(), $settings->work_days, true)) {
            throw ValidationException::withMessages(['date' => 'Hari ini bukan hari kerja.']);
        }

        if (Attendance::where('employee_id', $employee->id)->whereDate('date', $now->toDateString())->exists()) {
            throw ValidationException::withMessages(['date' => 'Anda sudah clock-in hari ini.']);
        }

        $late = $this->lateMinutes($now->format('H:i:s'), $settings);

        return Attendance::create([
            'employee_id' => $employee->id,
            'date' => $now->toDateString(),
            'mode' => $mode,
            'client_name' => $mode === 'CLIENT_SITE' ? $clientName : null,
            'clock_in' => $now->format('H:i:s'),
            'status' => $late > 0 ? 'late' : 'present',
            'late_minutes' => $late,
        ]);
    }

    public function clockOut(Employee $employee, ?Carbon $now = null): Attendance
    {
        $now ??= now();

        $attendance = Attendance::where('employee_id', $employee->id)->whereDate('date', $now->toDateString())->first();

        if (! $attendance || ! $attendance->clock_in) {
            throw ValidationException::withMessages(['date' => 'Anda belum clock-in hari ini.']);
        }

        if ($attendance->clock_out) {
            throw ValidationException::withMessages(['date' => 'Anda sudah clock-out hari ini.']);
        }

        $attendance->update([
            'clock_out' => $now->format('H:i:s'),
            'work_minutes' => $this->workMinutes($attendance->clock_in, $now->format('H:i:s')),
        ]);

        return $attendance;
    }

    /** Menit keterlambatan setelah melewati masa toleransi; 0 bila masih dalam toleransi. */
    public function lateMinutes(string $clockIn, CompanySetting $settings): int
    {
        $start = Carbon::createFromTimeString($settings->work_start);
        $arrived = Carbon::createFromTimeString($clockIn);
        $minutes = (int) $start->diffInMinutes($arrived, false);

        return $minutes > $settings->grace_minutes ? $minutes : 0;
    }

    public function workMinutes(string $clockIn, string $clockOut): int
    {
        return max(0, (int) Carbon::createFromTimeString($clockIn)->diffInMinutes(Carbon::createFromTimeString($clockOut), false));
    }
}
