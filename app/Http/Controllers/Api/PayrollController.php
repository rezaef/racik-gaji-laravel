<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\GeneratePayrollRequest;
use App\Models\PayrollPeriod;
use App\Services\PayrollService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PayrollController extends Controller
{
    public function __construct(private PayrollService $service) {}

    public function index(): JsonResponse
    {
        $periods = PayrollPeriod::withCount('payslips')
            ->withSum('payslips as total_gross', 'gross_salary')
            ->withSum('payslips as total_net', 'net_salary')
            ->orderByDesc('year')->orderByDesc('month')->get();

        return response()->json(['data' => $periods]);
    }

    public function generate(GeneratePayrollRequest $request): JsonResponse
    {
        $period = $this->service->generate($request->year, $request->month, $request->user()->id);

        return response()->json(['data' => $this->summary($period)], 201);
    }

    public function show(PayrollPeriod $period): JsonResponse
    {
        return response()->json(['data' => $this->summary($period->load('payslips.employee'))]);
    }

    /** Finalisasi mengunci periode dan membuat payslip terlihat oleh karyawan. */
    public function finalize(PayrollPeriod $period): JsonResponse
    {
        if ($period->status !== 'draft') {
            throw ValidationException::withMessages(['period' => 'Periode ini sudah difinalisasi.']);
        }

        $period->update(['status' => 'finalized', 'finalized_at' => now()]);

        return response()->json(['data' => $period]);
    }

    public function markPaid(PayrollPeriod $period): JsonResponse
    {
        if ($period->status !== 'finalized') {
            throw ValidationException::withMessages(['period' => 'Hanya periode yang sudah difinalisasi yang dapat ditandai dibayar.']);
        }

        $period->update(['status' => 'paid']);

        return response()->json(['data' => $period]);
    }

    /** Rekap CSV untuk pencairan bank oleh Finance. */
    public function exportCsv(PayrollPeriod $period): StreamedResponse
    {
        $filename = sprintf('rekap-gaji-%d-%02d.csv', $period->year, $period->month);

        return response()->streamDownload(function () use ($period) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // BOM agar Excel membaca UTF-8
            fputcsv($out, ['NIK', 'Nama', 'Bank', 'No Rekening', 'Hari Hadir', 'Gaji Pokok', 'Tunj. Tetap', 'Tunj. Makan', 'Tunj. Transport', 'Lembur', 'Gaji Kotor', 'BPJS Kes', 'JHT', 'JP', 'PPh 21', 'Total Potongan', 'Gaji Bersih']);

            $period->payslips()->with('employee')->get()->sortBy('employee.full_name')->each(fn ($p) => fputcsv($out, [
                $p->employee->employee_number, $p->employee->full_name, $p->employee->bank_name, $p->employee->bank_account.' ',
                $p->present_days, $p->basic_salary, $p->fixed_allowance, $p->meal_allowance, $p->transport_allowance, $p->overtime_pay,
                $p->gross_salary, $p->bpjs_kesehatan, $p->bpjs_jht, $p->bpjs_jp, $p->pph21, $p->total_deductions, $p->net_salary,
            ]));

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function summary(PayrollPeriod $period): array
    {
        $payslips = $period->payslips;

        return $period->toArray() + [
            'label' => $period->label(),
            'totals' => [
                'employees' => $payslips->count(),
                'gross' => $payslips->sum('gross_salary'),
                'deductions' => $payslips->sum('total_deductions'),
                'net' => $payslips->sum('net_salary'),
            ],
        ];
    }
}
