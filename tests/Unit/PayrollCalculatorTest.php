<?php

namespace Tests\Unit;

use App\Models\Employee;
use App\Services\PayrollCalculator;
use Tests\TestCase;

class PayrollCalculatorTest extends TestCase
{
    private function employee(array $attrs = []): Employee
    {
        return new Employee($attrs + [
            'basic_salary' => 8_650_000, 'fixed_allowance' => 0,
            'meal_allowance_daily' => 30_000, 'transport_allowance_daily' => 20_000, 'ptkp_status' => 'TK/0',
        ]);
    }

    public function test_overtime_uses_kemenaker_173_formula(): void
    {
        $calc = new PayrollCalculator;

        // upah/jam = 8.650.000 / 173 = 50.000 ; 3 jam = 1,5x + 2x + 2x = 5,5 x 50.000
        $this->assertSame(275_000, $calc->overtimePay(8_650_000, 3));
        $this->assertSame(0, $calc->overtimePay(8_650_000, 0));
    }

    public function test_ter_category_and_rate_follow_ptkp_status(): void
    {
        $calc = new PayrollCalculator;

        $this->assertSame(['A', 0.0], $calc->terRate('TK/0', 5_400_000));
        $this->assertSame(['A', 1.5], $calc->terRate('TK/0', 8_000_000));
        $this->assertSame(['B', 1.0], $calc->terRate('K/1', 8_000_000));
        $this->assertSame(['C', 3.0], $calc->terRate('K/2', 12_500_000));
        $this->assertSame(['A', 34.0], $calc->terRate('TK/0', 2_000_000_000));
    }

    public function test_full_payslip_calculation(): void
    {
        $result = (new PayrollCalculator)->calculate($this->employee(), presentDays: 20, overtimeHours: 2);

        // 8.650.000 + 20x30.000 + 20x20.000 + lembur(1,5+2=3,5 x 50.000 = 175.000)
        $this->assertSame(600_000, $result['meal_allowance']);
        $this->assertSame(400_000, $result['transport_allowance']);
        $this->assertSame(175_000, $result['overtime_pay']);
        $this->assertSame(9_825_000, $result['gross_salary']);
        $this->assertSame(86_500, $result['bpjs_kesehatan']);
        $this->assertSame(173_000, $result['bpjs_jht']);
        $this->assertSame(86_500, $result['bpjs_jp']);
        $this->assertSame('A', $result['ter_category']);
        $this->assertSame(2.0, $result['ter_rate']);
        $this->assertSame(196_500, $result['pph21']); // 9.825.000 x 2%
        $this->assertSame($result['gross_salary'] - $result['total_deductions'], $result['net_salary']);
    }

    public function test_bpjs_salary_caps_apply(): void
    {
        $result = (new PayrollCalculator)->calculate($this->employee(['basic_salary' => 20_000_000]), 0, 0);

        $this->assertSame(120_000, $result['bpjs_kesehatan']); // cap 12 jt
        $this->assertSame(105_474, $result['bpjs_jp']);        // cap 10.547.400
    }
}
