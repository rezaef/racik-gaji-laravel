<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CompanySetting;
use App\Models\Payslip;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PayslipController extends Controller
{
    /** Slip gaji milik sendiri (hanya dari periode yang sudah difinalisasi). */
    public function mine(Request $request): JsonResponse
    {
        $employee = $request->user()->employee ?? abort(403);

        $payslips = $employee->payslips()->with('period')
            ->whereHas('period', fn ($q) => $q->where('status', '!=', 'draft'))
            ->get()->sortByDesc(fn ($p) => $p->period->year * 100 + $p->period->month)->values();

        return response()->json(['data' => $payslips]);
    }

    public function show(Request $request, Payslip $payslip): JsonResponse
    {
        $this->authorizeView($request, $payslip);

        return response()->json(['data' => $payslip->load(['period', 'employee'])]);
    }

    public function pdf(Request $request, Payslip $payslip)
    {
        $this->authorizeView($request, $payslip);

        $payslip->load(['period', 'employee']);
        $filename = sprintf('slip-gaji-%s-%d-%02d.pdf', $payslip->employee->employee_number, $payslip->period->year, $payslip->period->month);

        return Pdf::loadView('pdf.payslip', ['payslip' => $payslip, 'company' => CompanySetting::current()])
            ->setPaper('a4')
            ->download($filename);
    }

    private function authorizeView(Request $request, Payslip $payslip): void
    {
        $user = $request->user();

        if ($user->isHr()) {
            return;
        }

        abort_unless($payslip->employee_id === $user->employee?->id && $payslip->period->status !== 'draft', 403, 'Anda tidak berhak melihat slip gaji ini.');
    }
}
