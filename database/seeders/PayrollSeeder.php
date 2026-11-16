<?php

namespace Database\Seeders;

use App\Models\User;
use App\Services\PayrollService;
use Illuminate\Database\Seeder;

class PayrollSeeder extends Seeder
{
    /** Dua bulan penuh terakhir: yang lebih lama sudah dibayar, bulan lalu sudah final dan menunggu ditandai dibayar. */
    public function run(PayrollService $payroll): void
    {
        $hr = User::where('role', User::ROLE_HR)->value('id');

        $older = now()->subMonthsNoOverflow(2);
        $payroll->generate($older->year, $older->month, $hr)->update(['status' => 'paid', 'finalized_at' => now()->subMonthNoOverflow()->startOfMonth()->addDays(2)]);

        $last = now()->subMonthNoOverflow();
        $payroll->generate($last->year, $last->month, $hr)->update(['status' => 'finalized', 'finalized_at' => now()->startOfMonth()->addDay()]);
    }
}
