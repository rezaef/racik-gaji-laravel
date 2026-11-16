<?php

namespace Database\Seeders;

use App\Models\Attendance;
use App\Models\Employee;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class AttendanceSeeder extends Seeder
{
    /** Absensi hybrid hari kerja (Senin-Jumat): dua bulan penuh sebelumnya + bulan berjalan sampai kemarin. */
    public function run(): void
    {
        mt_srand(20261116); // deterministik agar seed bisa diulang

        $from = now()->subMonthsNoOverflow(2)->startOfMonth();
        $to = now()->subDay();

        foreach (Employee::all() as $employee) {
            for ($date = $from->copy(); $date->lte($to); $date->addDay()) {
                if ($date->isWeekend() || $date->lt($employee->join_date)) {
                    continue; // tanpa Sabtu/Minggu dan sebelum tanggal masuk
                }

                Attendance::updateOrCreate(
                    ['employee_id' => $employee->id, 'date' => $date->toDateString()],
                    $this->row($date),
                );
            }
        }
    }

    private function row(Carbon $date): array
    {
        $roll = mt_rand(1, 100);
        $mode = $roll <= 55 ? 'WFO' : ($roll <= 85 ? 'WFH' : 'CLIENT_SITE');

        $lateRoll = mt_rand(1, 100);
        $in = Carbon::parse('08:00')->addMinutes(match (true) {
            $lateRoll <= 12 => mt_rand(16, 55),
            default => mt_rand(-25, 12),
        });
        $out = Carbon::parse('16:00')->addMinutes(mt_rand(0, 25));
        $late = max(0, $in->diffInMinutes(Carbon::parse('08:00'), false) * -1);

        return [
            'mode' => $mode,
            'client_name' => $mode === 'CLIENT_SITE' ? collect(['PT Petrokimia Gresik', 'PT Semen Indonesia', 'PT Smelting', 'PT Maspion'])->random() : null,
            'clock_in' => $in->format('H:i:s'),
            'clock_out' => $out->format('H:i:s'),
            'status' => $late > 15 ? 'late' : 'present',
            'late_minutes' => $late > 15 ? $late : 0,
            'work_minutes' => (int) $in->diffInMinutes($out),
        ];
    }
}
