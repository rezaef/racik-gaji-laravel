<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class HrisFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    private function actAs(string $email): User
    {
        $user = User::where('email', $email)->firstOrFail();
        Sanctum::actingAs($user);

        return $user;
    }

    public function test_seed_has_expected_people(): void
    {
        $this->assertSame(15, Employee::count());
        $this->assertDatabaseHas('employees', ['full_name' => 'Syaiful Akbar', 'position' => 'Technical Lead']);
    }

    public function test_demo_login_issues_token_per_role(): void
    {
        $this->postJson('/api/auth/demo-login', ['role' => 'manager'])
            ->assertOk()->assertJsonPath('user.name', 'Syaiful Akbar')->assertJsonStructure(['token']);
    }

    public function test_clock_in_flags_lateness_after_grace_period(): void
    {
        $this->actAs('yoga.pratama@racik.test');
        Carbon::setTestNow(Carbon::parse('next monday 08:40'));

        $this->postJson('/api/attendances/clock-in', ['mode' => 'WFO'])
            ->assertCreated()->assertJsonPath('data.status', 'late')->assertJsonPath('data.late_minutes', 40);

        $this->postJson('/api/attendances/clock-in', ['mode' => 'WFO'])->assertUnprocessable();
        Carbon::setTestNow();
    }

    public function test_client_site_requires_client_name(): void
    {
        $this->actAs('yoga.pratama@racik.test');

        $this->postJson('/api/attendances/clock-in', ['mode' => 'CLIENT_SITE'])->assertUnprocessable();
    }

    public function test_manager_approves_leave_but_not_own_and_employee_cannot(): void
    {
        $pending = LeaveRequest::where('status', 'pending')->first();

        $this->actAs('reza.eka@racik.test');
        $this->postJson("/api/leave-requests/{$pending->id}/approve")->assertForbidden();

        $this->actAs('syaiful.akbar@racik.test');
        $this->postJson("/api/leave-requests/{$pending->id}/approve", ['review_note' => 'OK'])
            ->assertOk()->assertJsonPath('data.status', 'approved');
        $this->postJson("/api/leave-requests/{$pending->id}/approve")->assertUnprocessable();
    }

    public function test_payroll_generate_finalize_export_and_payslip_access(): void
    {
        $prev = now()->subMonthNoOverflow();

        $this->actAs('dewi.lestari@racik.test');
        $period = $this->postJson('/api/payroll/generate', ['year' => $prev->year, 'month' => $prev->month])
            ->assertCreated()->assertJsonPath('data.totals.employees', 15)->json('data');

        $this->assertGreaterThan(0, $period['totals']['net']);
        $payslip = Employee::where('full_name', 'like', 'Bagus%')->first()->payslips()->first();
        $this->assertGreaterThan(0, $payslip->overtime_pay); // lembur disetujui masuk payroll

        // Draft: karyawan belum boleh melihat slip.
        $this->actAs('bagus.prasetyo@racik.test');
        $this->getJson("/api/payslips/{$payslip->id}")->assertForbidden();

        $this->actAs('dewi.lestari@racik.test');
        $this->postJson("/api/payroll/periods/{$period['id']}/finalize")->assertOk();
        $this->postJson('/api/payroll/generate', ['year' => $prev->year, 'month' => $prev->month])->assertUnprocessable();
        $this->get("/api/payroll/periods/{$period['id']}/export")->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $this->actAs('bagus.prasetyo@racik.test');
        $this->getJson("/api/payslips/{$payslip->id}")->assertOk();
        $this->get("/api/payslips/{$payslip->id}/pdf")->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->getJson('/api/payroll/periods')->assertForbidden();
    }
}
