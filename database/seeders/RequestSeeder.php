<?php

namespace Database\Seeders;

use App\Models\Attendance;
use App\Models\AttendanceCorrection;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\OvertimeRequest;
use Illuminate\Database\Seeder;

class RequestSeeder extends Seeder
{
    public function run(): void
    {
        $manager = Employee::where('full_name', 'Syaiful Akbar')->firstOrFail();
        $prev = now()->subMonthNoOverflow()->startOfMonth();
        $by = fn (string $name) => Employee::where('full_name', 'like', "$name%")->firstOrFail();

        // Cuti disetujui: absensi pada tanggal tersebut ditandai "leave".
        $leave = LeaveRequest::create([
            'employee_id' => $by('Siti')->id, 'type' => 'annual',
            'start_date' => $prev->copy()->addDays(14)->toDateString(), 'end_date' => $prev->copy()->addDays(16)->toDateString(),
            'days' => 3, 'reason' => 'Acara keluarga di Surabaya', 'status' => 'approved',
            'reviewed_by' => $manager->id, 'reviewed_at' => $prev->copy()->addDays(10),
        ]);
        Attendance::where('employee_id', $leave->employee_id)
            ->whereBetween('date', [$leave->start_date, $leave->end_date])
            ->update(['status' => 'leave', 'clock_in' => null, 'clock_out' => null, 'late_minutes' => 0, 'work_minutes' => 0]);

        LeaveRequest::create([
            'employee_id' => $by('Rizky')->id, 'type' => 'sick',
            'start_date' => now()->addDays(1)->toDateString(), 'end_date' => now()->addDays(2)->toDateString(),
            'days' => 2, 'reason' => 'Demam, surat dokter menyusul', 'status' => 'pending',
        ]);
        LeaveRequest::create([
            'employee_id' => $by('Nur')->id, 'type' => 'annual',
            'start_date' => now()->addDays(10)->toDateString(), 'end_date' => now()->addDays(12)->toDateString(),
            'days' => 3, 'reason' => 'Liburan keluarga', 'status' => 'pending',
        ]);

        // Lembur bulan lalu (disetujui, masuk payroll) + beberapa pending.
        foreach ([['Bagus', 3, 1], ['Agus', 4, 4], ['Bagus', 10, 2], ['Dimas', 11, 3]] as [$name, $offset, $hours]) {
            $date = $prev->copy()->addDays($offset);
            $date = $date->isWeekend() ? $date->next('Monday') : $date;
            OvertimeRequest::create([
                'employee_id' => $by($name)->id, 'date' => $date->toDateString(),
                'start_time' => '16:00:00', 'end_time' => sprintf('%02d:00:00', 16 + $hours), 'hours' => $hours,
                'reason' => 'Deploy dan hotfix produksi', 'status' => 'approved',
                'reviewed_by' => $manager->id, 'reviewed_at' => $date->copy()->addDay(),
            ]);
        }
        OvertimeRequest::create([
            'employee_id' => $by('Yoga')->id, 'date' => now()->subDay()->toDateString(),
            'start_time' => '16:00:00', 'end_time' => '19:00:00', 'hours' => 3,
            'reason' => 'Penanganan insiden jaringan klien', 'status' => 'pending',
        ]);

        AttendanceCorrection::create([
            'employee_id' => $by('Putri')->id, 'date' => now()->subDays(3)->toDateString(),
            'mode' => 'WFH', 'clock_in' => '08:05:00', 'clock_out' => '16:10:00',
            'reason' => 'Lupa clock-in karena internet bermasalah',
        ]);
    }
}
