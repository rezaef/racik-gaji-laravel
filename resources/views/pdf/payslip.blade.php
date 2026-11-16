@php
    $rp = fn ($n) => 'Rp '.number_format($n, 0, ',', '.');
    $e = $payslip->employee;
    $earnings = [
        'Gaji Pokok' => $payslip->basic_salary,
        'Tunjangan Tetap' => $payslip->fixed_allowance,
        "Tunjangan Makan ({$payslip->present_days} hari)" => $payslip->meal_allowance,
        "Tunjangan Transport ({$payslip->present_days} hari)" => $payslip->transport_allowance,
        "Lembur ({$payslip->overtime_hours} jam)" => $payslip->overtime_pay,
    ];
    $deductions = [
        'BPJS Kesehatan (1%)' => $payslip->bpjs_kesehatan,
        'BPJS JHT (2%)' => $payslip->bpjs_jht,
        'BPJS JP (1%)' => $payslip->bpjs_jp,
        "PPh 21 TER {$payslip->ter_category} ({$payslip->ter_rate}%)" => $payslip->pph21,
    ];
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Slip Gaji {{ $e->full_name }} - {{ $payslip->period->label() }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1f2937; }
        .header { border-bottom: 3px solid #0f766e; padding-bottom: 8px; margin-bottom: 14px; }
        .header h1 { margin: 0; font-size: 18px; color: #0f766e; }
        .header p { margin: 2px 0 0; color: #4b5563; }
        h2 { font-size: 13px; margin: 0 0 8px; text-transform: uppercase; letter-spacing: 1px; }
        table { width: 100%; border-collapse: collapse; }
        .info td { padding: 2px 0; }
        .info td:first-child { width: 22%; color: #6b7280; }
        .items th, .items td { padding: 5px 8px; border-bottom: 1px solid #e5e7eb; text-align: left; }
        .items th { background: #f0fdfa; }
        .items td.num, .items th.num { text-align: right; }
        .total td { font-weight: bold; background: #f9fafb; }
        .net { margin-top: 14px; padding: 10px; background: #0f766e; color: #fff; font-size: 14px; }
        .sign { margin-top: 36px; width: 100%; }
        .sign td { width: 50%; text-align: center; vertical-align: top; }
        .stamp { margin: 8px auto; width: 150px; padding: 8px 0; border: 1px dashed #0f766e; color: #0f766e; font-size: 9px; }
        .foot { margin-top: 20px; font-size: 9px; color: #9ca3af; text-align: center; }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ $company->company_name }}</h1>
        <p>{{ $company->address }}, {{ $company->city }}@if($company->npwp) &middot; NPWP {{ $company->npwp }}@endif</p>
    </div>

    <h2>Slip Gaji &mdash; {{ $payslip->period->label() }}</h2>
    <table class="info">
        <tr><td>Nama</td><td>: {{ $e->full_name }}</td><td>No. Karyawan</td><td>: {{ $e->employee_number }}</td></tr>
        <tr><td>Jabatan</td><td>: {{ $e->position }}</td><td>Status PTKP</td><td>: {{ $e->ptkp_status }}</td></tr>
        <tr><td>Departemen</td><td>: {{ $e->department }}</td><td>Hari Hadir</td><td>: {{ $payslip->present_days }} / {{ $payslip->period->working_days }} hari kerja</td></tr>
    </table>

    <br>
    <table class="items">
        <tr><th>Pendapatan</th><th class="num">Jumlah</th></tr>
        @foreach ($earnings as $label => $amount)
            <tr><td>{{ $label }}</td><td class="num">{{ $rp($amount) }}</td></tr>
        @endforeach
        <tr class="total"><td>Total Pendapatan (Bruto)</td><td class="num">{{ $rp($payslip->gross_salary) }}</td></tr>
    </table>

    <br>
    <table class="items">
        <tr><th>Potongan</th><th class="num">Jumlah</th></tr>
        @foreach ($deductions as $label => $amount)
            <tr><td>{{ $label }}</td><td class="num">{{ $rp($amount) }}</td></tr>
        @endforeach
        <tr class="total"><td>Total Potongan</td><td class="num">{{ $rp($payslip->total_deductions) }}</td></tr>
    </table>

    <div class="net">Gaji Bersih (Take Home Pay): <strong>{{ $rp($payslip->net_salary) }}</strong></div>

    <table class="sign">
        <tr>
            <td>Diterima oleh,<br><br><br><br><br>{{ $e->full_name }}</td>
            <td>
                {{ $company->city }}, {{ now()->locale('id')->translatedFormat('d F Y') }}<br>
                Disahkan oleh,
                <div class="stamp">DITANDATANGANI SECARA DIGITAL<br>{{ $company->company_name }}<br>No. {{ sprintf('PS-%d%02d-%04d', $payslip->period->year, $payslip->period->month, $payslip->id) }}</div>
                <strong>{{ $company->signer_name }}</strong><br>{{ $company->signer_title }}
            </td>
        </tr>
    </table>

    <p class="foot">Dokumen ini dihasilkan otomatis oleh RacikHRIS dan bersifat rahasia.</p>
</body>
</html>
