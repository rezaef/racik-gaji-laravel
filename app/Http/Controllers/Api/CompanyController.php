<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CompanySettingRequest;
use App\Models\CompanySetting;
use Illuminate\Http\JsonResponse;

class CompanyController extends Controller
{
    public function show(): JsonResponse
    {
        return response()->json(['data' => CompanySetting::current()]);
    }

    public function update(CompanySettingRequest $request): JsonResponse
    {
        $settings = CompanySetting::current();
        $settings->update($request->validated());

        return response()->json(['data' => $settings->fresh()]);
    }
}
