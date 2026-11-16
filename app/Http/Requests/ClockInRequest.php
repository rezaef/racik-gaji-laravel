<?php

namespace App\Http\Requests;

use App\Models\Attendance;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ClockInRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'mode' => ['required', Rule::in(Attendance::MODES)],
            'client_name' => ['required_if:mode,CLIENT_SITE', 'nullable', 'string', 'max:150'],
        ];
    }
}
