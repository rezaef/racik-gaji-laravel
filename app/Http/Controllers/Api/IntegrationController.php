<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Employee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Endpoint untuk aplikasi absensi eksternal (absensi-laravel). */
class IntegrationController extends Controller
{
    public function employees(): JsonResponse
    {
        return response()->json(['data' => Employee::with('user:id,email')->get()->map(fn (Employee $e) => [
            'employee_number' => $e->employee_number,
            'full_name' => $e->full_name,
            'position' => $e->position,
            'department' => $e->department,
            'status' => $e->status,
            'email' => $e->user?->email,
        ])]);
    }

    /** Upsert absensi per (karyawan, tanggal). Data dari aplikasi absensi menjadi sumber kebenaran. */
    public function attendances(Request $request): JsonResponse
    {
        $records = $request->validate([
            'records' => ['required', 'array', 'max:1000'],
            'records.*.employee_number' => ['required', 'string'],
            'records.*.date' => ['required', 'date_format:Y-m-d'],
            'records.*.mode' => ['required', 'in:'.implode(',', Attendance::MODES)],
            'records.*.client_name' => ['nullable', 'string', 'max:255'],
            'records.*.clock_in' => ['nullable', 'date_format:H:i:s'],
            'records.*.clock_out' => ['nullable', 'date_format:H:i:s'],
            'records.*.status' => ['required', 'in:present,late,absent,leave'],
            'records.*.late_minutes' => ['nullable', 'integer', 'min:0'],
            'records.*.work_minutes' => ['nullable', 'integer', 'min:0'],
            'records.*.notes' => ['nullable', 'string', 'max:255'],
        ])['records'];

        $employees = Employee::whereIn('employee_number', collect($records)->pluck('employee_number')->unique())
            ->pluck('id', 'employee_number');

        $created = $updated = $skipped = 0;
        $errors = [];

        foreach ($records as $r) {
            $employeeId = $employees[$r['employee_number']] ?? null;

            if (! $employeeId) {
                $skipped++;
                $errors[] = "Karyawan {$r['employee_number']} tidak ditemukan di payroll.";

                continue;
            }

            $attendance = Attendance::updateOrCreate(
                ['employee_id' => $employeeId, 'date' => $r['date']],
                [
                    'mode' => $r['mode'],
                    'client_name' => $r['client_name'] ?? null,
                    'clock_in' => $r['clock_in'] ?? null,
                    'clock_out' => $r['clock_out'] ?? null,
                    'status' => $r['status'],
                    'late_minutes' => $r['late_minutes'] ?? 0,
                    'work_minutes' => $r['work_minutes'] ?? 0,
                    'notes' => $r['notes'] ?? null,
                ],
            );

            $attendance->wasRecentlyCreated ? $created++ : $updated++;
        }

        return response()->json(['data' => compact('created', 'updated', 'skipped', 'errors')]);
    }
}
