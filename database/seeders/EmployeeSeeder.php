<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Database\Seeder;

class EmployeeSeeder extends Seeder
{
    /** Kata sandi semua akun demo: "password". */
    public function run(): void
    {
        // [email, nama, role, jabatan, departemen, join, PTKP, gaji pokok, tunj. tetap, makan/hari, transport/hari]
        $rows = [
            ['syaiful.akbar', 'Syaiful Akbar', User::ROLE_MANAGER, 'Technical Lead', 'Engineering', '2021-03-01', 'K/2', 15_000_000, 2_500_000, 40_000, 30_000],
            ['dewi.lestari', 'Dewi Lestari', User::ROLE_HR, 'HR Manager', 'Human Resources', '2021-06-14', 'K/1', 9_500_000, 1_500_000, 35_000, 25_000],
            ['reza.eka', 'Reza Eka Firmansyah', User::ROLE_EMPLOYEE, 'Backend Developer (Magang)', 'Engineering', '2026-09-01', 'TK/0', 3_500_000, 0, 25_000, 20_000],
            ['aldo.syahputra', 'Muhammad Febriano Aldo Syahputra', User::ROLE_EMPLOYEE, 'Frontend Developer (Magang)', 'Engineering', '2026-09-01', 'TK/0', 3_500_000, 0, 25_000, 20_000],
            ['bagus.prasetyo', 'Bagus Prasetyo', User::ROLE_EMPLOYEE, 'Senior Backend Engineer', 'Engineering', '2022-01-10', 'K/2', 11_000_000, 1_500_000, 35_000, 25_000],
            ['nur.aini', 'Nur Aini Rahmawati', User::ROLE_EMPLOYEE, 'Frontend Engineer', 'Engineering', '2022-08-01', 'TK/0', 8_000_000, 1_000_000, 35_000, 25_000],
            ['rizky.maulana', 'Rizky Maulana', User::ROLE_EMPLOYEE, 'Mobile Developer', 'Engineering', '2023-02-15', 'K/0', 8_500_000, 1_000_000, 35_000, 25_000],
            ['siti.fatimah', 'Siti Fatimah', User::ROLE_EMPLOYEE, 'QA Engineer', 'Engineering', '2023-05-02', 'K/1', 7_200_000, 800_000, 30_000, 25_000],
            ['agus.setiawan', 'Agus Setiawan', User::ROLE_EMPLOYEE, 'DevOps Engineer', 'Engineering', '2022-11-21', 'K/3', 10_000_000, 1_200_000, 35_000, 25_000],
            ['putri.wulandari', 'Putri Wulandari', User::ROLE_EMPLOYEE, 'UI/UX Designer', 'Product', '2023-07-17', 'TK/1', 7_500_000, 800_000, 30_000, 25_000],
            ['hendra.wijaya', 'Hendra Wijaya', User::ROLE_EMPLOYEE, 'Business Analyst', 'Product', '2022-04-04', 'K/2', 9_000_000, 1_000_000, 35_000, 25_000],
            ['lina.marlina', 'Lina Marlina', User::ROLE_EMPLOYEE, 'Finance Staff', 'Finance', '2023-01-09', 'TK/0', 6_500_000, 700_000, 30_000, 20_000],
            ['yoga.pratama', 'Yoga Pratama', User::ROLE_EMPLOYEE, 'Technical Support', 'Operations', '2024-02-05', 'TK/0', 5_300_000, 500_000, 25_000, 20_000],
            ['maya.anggraini', 'Maya Anggraini', User::ROLE_EMPLOYEE, 'Project Coordinator', 'Operations', '2023-09-11', 'K/0', 7_000_000, 800_000, 30_000, 25_000],
            ['dimas.saputra', 'Dimas Saputra', User::ROLE_EMPLOYEE, 'Data Engineer', 'Engineering', '2024-06-03', 'TK/2', 9_500_000, 1_000_000, 35_000, 25_000],
        ];

        $manager = null;

        foreach ($rows as $i => [$slug, $name, $role, $position, $department, $join, $ptkp, $basic, $fixed, $meal, $transport]) {
            $user = User::updateOrCreate(
                ['email' => "$slug@racik.test"],
                ['name' => $name, 'password' => 'password', 'role' => $role],
            );

            $employee = Employee::updateOrCreate(['user_id' => $user->id], [
                'manager_id' => $manager?->id,
                'employee_number' => sprintf('RST-%s-%03d', substr($join, 0, 4), $i + 1),
                'full_name' => $name,
                'position' => $position,
                'department' => $department,
                'join_date' => $join,
                'status' => 'active',
                'ptkp_status' => $ptkp,
                'npwp' => sprintf('%02d.%03d.%03d.%d-612.000', 90 + $i, 100 + $i * 7, 200 + $i * 3, $i % 10),
                'phone' => sprintf('0812%08d', 31000000 + $i * 137),
                'bank_name' => $i % 3 === 0 ? 'Bank Mandiri' : ($i % 3 === 1 ? 'BCA' : 'BNI'),
                'bank_account' => sprintf('%010d', 1200000000 + $i * 7919),
                'basic_salary' => $basic,
                'fixed_allowance' => $fixed,
                'meal_allowance_daily' => $meal,
                'transport_allowance_daily' => $transport,
            ]);

            // Syaiful (baris pertama) menjadi atasan seluruh karyawan lain.
            $manager ??= $employee;
        }
    }
}
