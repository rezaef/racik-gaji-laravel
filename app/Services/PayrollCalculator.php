<?php

namespace App\Services;

use App\Models\Employee;

/** Perhitungan komponen gaji; murni (tanpa akses DB) agar mudah diuji. */
class PayrollCalculator
{
    /**
     * @return array<string, int|float|string|null>
     */
    public function calculate(Employee $employee, int $presentDays, float $overtimeHours): array
    {
        $meal = $employee->meal_allowance_daily * $presentDays;
        $transport = $employee->transport_allowance_daily * $presentDays;
        $overtime = $this->overtimePay($employee->basic_salary, $overtimeHours);
        $gross = $employee->basic_salary + $employee->fixed_allowance + $meal + $transport + $overtime;

        $bpjs = config('payroll.bpjs');
        $kes = $this->percent(min($employee->basic_salary + $employee->fixed_allowance, $bpjs['kesehatan_salary_cap']), $bpjs['kesehatan_employee_rate']);
        $jht = $this->percent($employee->basic_salary + $employee->fixed_allowance, $bpjs['jht_employee_rate']);
        $jp = $this->percent(min($employee->basic_salary + $employee->fixed_allowance, $bpjs['jp_salary_cap']), $bpjs['jp_employee_rate']);

        [$category, $rate] = $this->terRate($employee->ptkp_status, $gross);
        $pph21 = $this->percent($gross, $rate);

        $deductions = $kes + $jht + $jp + $pph21;

        return [
            'present_days' => $presentDays,
            'overtime_hours' => $overtimeHours,
            'basic_salary' => $employee->basic_salary,
            'fixed_allowance' => $employee->fixed_allowance,
            'meal_allowance' => $meal,
            'transport_allowance' => $transport,
            'overtime_pay' => $overtime,
            'gross_salary' => $gross,
            'bpjs_kesehatan' => $kes,
            'bpjs_jht' => $jht,
            'bpjs_jp' => $jp,
            'ter_category' => $category,
            'ter_rate' => $rate,
            'pph21' => $pph21,
            'total_deductions' => $deductions,
            'net_salary' => $gross - $deductions,
        ];
    }

    /** Lembur hari kerja: jam pertama 1,5x, jam berikutnya 2x, upah per jam = gaji pokok / 173. */
    public function overtimePay(int $basicSalary, float $hours): int
    {
        if ($hours <= 0) {
            return 0;
        }

        $hourly = $basicSalary / config('payroll.overtime_divisor');
        $first = min($hours, 1) * config('payroll.overtime_first_hour_multiplier');
        $rest = max($hours - 1, 0) * config('payroll.overtime_next_hour_multiplier');

        return (int) round($hourly * ($first + $rest));
    }

    /** @return array{0: string, 1: float} kategori TER dan tarif (%) */
    public function terRate(string $ptkpStatus, int $gross): array
    {
        $category = config('payroll.ter_category')[$ptkpStatus] ?? 'A';

        foreach (config("payroll.ter.$category") as [$limit, $rate]) {
            if ($limit === null || $gross <= $limit) {
                return [$category, (float) $rate];
            }
        }

        return [$category, 0.0];
    }

    private function percent(int|float $base, int|float $rate): int
    {
        return (int) round($base * $rate / 100);
    }
}
