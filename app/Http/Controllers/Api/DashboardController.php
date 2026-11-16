<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\AttendanceCorrection;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\OvertimeRequest;
use App\Models\PayrollPeriod;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /** Ringkasan beranda sesuai role: personal untuk karyawan, tim/perusahaan untuk manager dan HR. */
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();
        $employee = $user->employee ?? abort(403, 'Akun ini tidak terhubung ke data karyawan.');
        $monthStart = now()->startOfMonth()->toDateString();

        $data = [
            'role' => $user->role,
            'today' => Attendance::where('employee_id', $employee->id)->whereDate('date', today())->first(),
            'month' => $this->monthSummary(Attendance::where('employee_id', $employee->id)->where('date', '>=', $monthStart)),
            'my_pending' => [
                'leave' => LeaveRequest::where('employee_id', $employee->id)->where('status', 'pending')->count(),
                'overtime' => OvertimeRequest::where('employee_id', $employee->id)->where('status', 'pending')->count(),
                'corrections' => AttendanceCorrection::where('employee_id', $employee->id)->where('status', 'pending')->count(),
            ],
            'latest_payslip' => $employee->payslips()->with('period')
                ->whereHas('period', fn ($q) => $q->where('status', '!=', 'draft'))
                ->latest('id')->first(),
        ];

        if ($user->isManager() || $user->isHr()) {
            $data += $this->teamOverview($user, $monthStart);
        }

        if ($user->isHr()) {
            $data['payroll'] = PayrollPeriod::withSum('payslips as total_net', 'net_salary')
                ->orderByDesc('year')->orderByDesc('month')->limit(6)->get();
        }

        return response()->json(['data' => $data]);
    }

    private function teamOverview($user, string $monthStart): array
    {
        $teamIds = $this->scopeToViewer(Employee::where('status', 'active'), $user, 'id')->pluck('id');
        $todayRows = Attendance::whereIn('employee_id', $teamIds)->whereDate('date', today())->get();

        $pending = fn (string $model) => $this->scopeToViewer($model::where('status', 'pending'), $user, 'employee_id');

        $trend = Attendance::whereIn('employee_id', $teamIds)
            ->whereIn('status', ['present', 'late'])
            ->where('date', '>=', now()->subDays(13)->toDateString())
            ->selectRaw('date, count(*) as present, sum(case when status = ? then 1 else 0 end) as late', ['late'])
            ->groupBy('date')->orderBy('date')->get()
            ->map(fn ($r) => ['date' => substr($r->date, 0, 10), 'present' => (int) $r->present, 'late' => (int) $r->late]);

        return [
            'team' => [
                'headcount' => $teamIds->count(),
                'present_today' => $todayRows->whereIn('status', ['present', 'late'])->count(),
                'late_today' => $todayRows->where('status', 'late')->count(),
                'on_leave_today' => $todayRows->where('status', 'leave')->count(),
                'by_mode_today' => collect(Attendance::MODES)->mapWithKeys(fn ($m) => [$m => $todayRows->where('mode', $m)->whereIn('status', ['present', 'late'])->count()]),
                'by_mode_month' => $this->monthSummary(Attendance::whereIn('employee_id', $teamIds)->where('date', '>=', $monthStart))['by_mode'],
                'trend' => $trend,
            ],
            'pending_approvals' => [
                'leave' => $pending(LeaveRequest::class)->count(),
                'overtime' => $pending(OvertimeRequest::class)->count(),
                'corrections' => $pending(AttendanceCorrection::class)->count(),
            ],
        ];
    }

    private function monthSummary($query): array
    {
        $rows = $query->get(['status', 'mode', 'late_minutes']);

        return [
            'present' => $rows->whereIn('status', ['present', 'late'])->count(),
            'late' => $rows->where('status', 'late')->count(),
            'leave' => $rows->where('status', 'leave')->count(),
            'by_mode' => collect(Attendance::MODES)->mapWithKeys(fn ($m) => [$m => $rows->where('mode', $m)->whereIn('status', ['present', 'late'])->count()]),
        ];
    }
}
