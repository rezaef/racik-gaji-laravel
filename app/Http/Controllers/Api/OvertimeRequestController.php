<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\StoreOvertimeRequest;
use App\Models\OvertimeRequest;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;

class OvertimeRequestController extends ApprovalController
{
    protected function model(): string
    {
        return OvertimeRequest::class;
    }

    public function store(StoreOvertimeRequest $request): JsonResponse
    {
        $employee = $request->user()->employee ?? abort(403);

        $hours = round(Carbon::createFromFormat('H:i', $request->start_time)->diffInMinutes(Carbon::createFromFormat('H:i', $request->end_time)) / 60, 2);

        $overtime = $employee->overtimeRequests()->create($request->validated() + ['hours' => $hours]);

        return response()->json(['data' => $overtime], 201);
    }
}
