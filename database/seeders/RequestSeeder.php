<?php

namespace Database\Seeders;

use App\Models\Attendance;
use App\Models\AttendanceCorrection;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\OvertimeRequest;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class RequestSeeder extends Seeder
{
    public function run(): void
    {
        $manager = Employee::where('full_name', 'Manajer Demo')->firstOrFail();
        $by = fn (string $name) => Employee::where('full_name', $name)->firstOrFail();

        // Cuti/sakit disetujui di dua bulan penuh: [nama, jenis, bulan (1 = bulan lalu, 2 = dua bulan lalu), hari ke-, lama hari kerja, alasan]
        $leaves = [
            ['Karyawan 6', 'annual', 1, 14, 3, 'Acara keluarga di Surabaya'], ['Karyawan 7', 'sick', 1, 5, 2, 'Demam dan flu, surat dokter terlampir'],
            ['Karyawan 10', 'annual', 1, 21, 2, 'Mengurus dokumen keluarga'], ['Karyawan 5', 'annual', 1, 9, 1, 'Keperluan pribadi'],
            ['Karyawan 12', 'sick', 1, 17, 1, 'Kontrol dokter'],
            ['Karyawan 6', 'annual', 2, 7, 2, 'Liburan akhir pekan panjang'], ['Karyawan 4', 'sick', 2, 14, 1, 'Sakit kepala, istirahat'],
            ['Karyawan 8', 'annual', 2, 20, 3, 'Pernikahan saudara'], ['Karyawan 9', 'annual', 2, 3, 2, 'Mengantar keluarga berobat'],
        ];

        foreach ($leaves as [$name, $type, $monthsAgo, $offset, $length, $reason]) {
            $start = now()->subMonthsNoOverflow($monthsAgo)->startOfMonth()->addDays($offset);
            $start = $start->isWeekend() ? $start->next(Carbon::MONDAY) : $start;
            $dates = [];
            for ($d = $start->copy(); count($dates) < $length; $d->addDay()) {
                if (! $d->isWeekend()) {
                    $dates[] = $d->copy();
                }
            }

            $leave = LeaveRequest::create([
                'employee_id' => $by($name)->id, 'type' => $type,
                'start_date' => $dates[0]->toDateString(), 'end_date' => end($dates)->toDateString(),
                'days' => $length, 'reason' => $reason, 'status' => 'approved',
                'reviewed_by' => $manager->id, 'reviewed_at' => $dates[0]->copy()->subDays(3),
            ]);

            foreach ($dates as $d) {
                $row = Attendance::firstOrNew(['employee_id' => $leave->employee_id, 'date' => $d->toDateString()]);
                $row->mode ??= 'WFO';
                $row->fill(['status' => 'leave', 'client_name' => null, 'clock_in' => null, 'clock_out' => null, 'late_minutes' => 0, 'work_minutes' => 0])->save();
            }
        }

        LeaveRequest::create([
            'employee_id' => $by('Karyawan 5')->id, 'type' => 'sick',
            'start_date' => now()->addDays(1)->toDateString(), 'end_date' => now()->addDays(2)->toDateString(),
            'days' => 2, 'reason' => 'Demam, surat dokter menyusul', 'status' => 'pending',
        ]);
        LeaveRequest::create([
            'employee_id' => $by('Karyawan 4')->id, 'type' => 'annual',
            'start_date' => now()->addDays(10)->toDateString(), 'end_date' => now()->addDays(12)->toDateString(),
            'days' => 3, 'reason' => 'Liburan keluarga', 'status' => 'pending',
        ]);

        // Lembur disetujui (masuk payroll) di dua bulan: [nama, bulan lalu ke-, hari ke-, jam]. Tidak dibuat pada hari cuti.
        $overtimes = [
            ['Karyawan 3', 1, 3, 1], ['Karyawan 7', 1, 4, 4], ['Karyawan 3', 1, 10, 2], ['Karyawan 13', 1, 11, 3], ['Karyawan 5', 1, 17, 2], ['Karyawan 8', 1, 22, 1],
            ['Karyawan 3', 2, 4, 2], ['Karyawan 7', 2, 9, 3], ['Karyawan 13', 2, 15, 2], ['Karyawan 5', 2, 18, 1], ['Karyawan 4', 2, 22, 4], ['Karyawan 6', 2, 24, 2],
        ];

        foreach ($overtimes as [$name, $monthsAgo, $offset, $hours]) {
            $date = now()->subMonthsNoOverflow($monthsAgo)->startOfMonth()->addDays($offset);
            $date = $date->isWeekend() ? $date->next(Carbon::MONDAY) : $date;
            $employee = $by($name);

            if (Attendance::where('employee_id', $employee->id)->whereDate('date', $date)->where('status', 'leave')->exists()) {
                continue;
            }

            OvertimeRequest::create([
                'employee_id' => $employee->id, 'date' => $date->toDateString(),
                'start_time' => '16:00:00', 'end_time' => sprintf('%02d:00:00', 16 + $hours), 'hours' => $hours,
                'reason' => 'Deploy dan hotfix produksi', 'status' => 'approved',
                'reviewed_by' => $manager->id, 'reviewed_at' => $date->copy()->addDay(),
            ]);
        }
        OvertimeRequest::create([
            'employee_id' => $by('Karyawan 11')->id, 'date' => now()->subDay()->toDateString(),
            'start_time' => '16:00:00', 'end_time' => '19:00:00', 'hours' => 3,
            'reason' => 'Penanganan insiden jaringan klien', 'status' => 'pending',
        ]);

        AttendanceCorrection::create([
            'employee_id' => $by('Karyawan 8')->id, 'date' => now()->subDays(3)->toDateString(),
            'mode' => 'WFH', 'clock_in' => '08:05:00', 'clock_out' => '16:10:00',
            'reason' => 'Lupa clock-in karena internet bermasalah',
        ]);
    }
}
