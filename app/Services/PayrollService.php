<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\CompanySetting;
use App\Models\Employee;
use App\Models\OvertimeRequest;
use App\Models\PayrollPeriod;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PayrollService
{
    public function __construct(private PayrollCalculator $calculator) {}

    /** Generate (atau generate ulang bila masih draft) payslip semua karyawan aktif satu bulan. */
    public function generate(int $year, int $month, ?int $userId = null): PayrollPeriod
    {
        $start = Carbon::create($year, $month, 1)->startOfMonth();
        $end = $start->copy()->endOfMonth();

        return DB::transaction(function () use ($year, $month, $start, $end, $userId) {
            $period = PayrollPeriod::where(compact('year', 'month'))->lockForUpdate()->first();

            if ($period && $period->status !== 'draft') {
                throw ValidationException::withMessages(['period' => 'Periode ini sudah difinalisasi dan tidak dapat digenerate ulang.']);
            }

            $period ??= new PayrollPeriod(compact('year', 'month'));
            $period->fill([
                'working_days' => $this->workingDays($start, $end),
                'generated_by' => $userId,
                'generated_at' => now(),
            ])->save();

            $period->payslips()->delete();

            Employee::where('status', 'active')->get()->each(function (Employee $employee) use ($period, $start, $end) {
                $presentDays = Attendance::where('employee_id', $employee->id)
                    ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
                    ->whereIn('status', ['present', 'late'])
                    ->count();

                $overtimeHours = (float) OvertimeRequest::where('employee_id', $employee->id)
                    ->where('status', 'approved')
                    ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
                    ->sum('hours');

                $period->payslips()->create(
                    ['employee_id' => $employee->id] + $this->calculator->calculate($employee, $presentDays, $overtimeHours)
                );
            });

            return $period->load('payslips.employee');
        });
    }

    public function workingDays(Carbon $start, Carbon $end): int
    {
        $days = CompanySetting::current()->work_days;
        $count = 0;

        for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
            $count += in_array($date->isoWeekday(), $days, true) ? 1 : 0;
        }

        return $count;
    }
}
