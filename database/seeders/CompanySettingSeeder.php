<?php

namespace Database\Seeders;

use App\Models\CompanySetting;
use Illuminate\Database\Seeder;

class CompanySettingSeeder extends Seeder
{
    public function run(): void
    {
        CompanySetting::updateOrCreate(['id' => 1], [
            'company_name' => 'PT Contoh Sejahtera',
            'address' => 'Jl. Contoh Raya No. 123',
            'city' => 'Jakarta',
            'npwp' => '91.234.567.8-612.000',
            'work_start' => '08:00:00',
            'work_end' => '16:00:00',
            'grace_minutes' => 15,
            'work_days' => [1, 2, 3, 4, 5],
            'signer_name' => 'Admin HR',
            'signer_title' => 'HR Manager',
        ]);
    }
}
