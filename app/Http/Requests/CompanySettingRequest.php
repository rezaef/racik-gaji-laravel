<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CompanySettingRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'company_name' => ['sometimes', 'string', 'max:150'],
            'address' => ['sometimes', 'string', 'max:255'],
            'city' => ['sometimes', 'string', 'max:100'],
            'npwp' => ['nullable', 'string', 'max:30'],
            'work_start' => ['sometimes', 'date_format:H:i'],
            'work_end' => ['sometimes', 'date_format:H:i', 'after:work_start'],
            'grace_minutes' => ['sometimes', 'integer', 'between:0,120'],
            'work_days' => ['sometimes', 'array', 'min:1'],
            'work_days.*' => ['integer', 'between:1,7'],
            'signer_name' => ['nullable', 'string', 'max:100'],
            'signer_title' => ['nullable', 'string', 'max:100'],
        ];
    }
}
