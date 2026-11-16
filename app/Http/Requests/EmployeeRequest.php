<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Dipakai untuk create dan update karyawan (+ akun login terkait). */
class EmployeeRequest extends FormRequest
{
    public function rules(): array
    {
        $employee = $this->route('employee');
        $required = $employee ? 'sometimes' : 'required';

        return [
            'email' => [$required, 'email', Rule::unique('users', 'email')->ignore($employee?->user_id)],
            'password' => [$employee ? 'sometimes' : 'required', 'string', 'min:8'],
            'role' => [$required, Rule::in(['hr_admin', 'manager', 'employee'])],
            'employee_number' => [$employee ? 'sometimes' : 'nullable', 'string', Rule::unique('employees', 'employee_number')->ignore($employee?->id)],
            'full_name' => [$required, 'string', 'max:150'],
            'position' => [$required, 'string', 'max:100'],
            'department' => [$required, 'string', 'max:100'],
            'manager_id' => ['nullable', 'exists:employees,id'],
            'join_date' => [$required, 'date'],
            'status' => ['sometimes', Rule::in(['active', 'inactive'])],
            'ptkp_status' => [$required, Rule::in(array_keys(config('payroll.ter_category')))],
            'npwp' => ['nullable', 'string', 'max:30'],
            'phone' => ['nullable', 'string', 'max:20'],
            'bank_name' => ['nullable', 'string', 'max:50'],
            'bank_account' => ['nullable', 'string', 'max:30'],
            'basic_salary' => [$required, 'integer', 'min:0'],
            'fixed_allowance' => ['sometimes', 'integer', 'min:0'],
            'meal_allowance_daily' => ['sometimes', 'integer', 'min:0'],
            'transport_allowance_daily' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
