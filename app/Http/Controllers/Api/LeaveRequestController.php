<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\StoreLeaveRequest;
use App\Models\Attendance;
use App\Models\CompanySetting;
use App\Models\LeaveRequest;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;

class LeaveRequestController extends ApprovalController
{
    protected function model(): string
    {
        return LeaveRequest::class;
    }

    public function store(StoreLeaveRequest $request): JsonResponse
    {
        $employee = $request->user()->employee ?? abort(403);
        $days = $this->workDates($request->start_date, $request->end_date);

        if (count($days) === 0) {
            abort(422, 'Rentang tanggal tidak mengandung hari kerja.');
        }

        $leave = $employee->leaveRequests()->create($request->validated() + ['days' => count($days)]);

        return response()->json(['data' => $leave], 201);
    }

    /** Cuti disetujui -> hari kerja terkait ditandai "leave" pada absensi. */
    protected function onApproved(Model $item): void
    {
        foreach ($this->workDates($item->start_date, $item->end_date) as $date) {
            Attendance::updateOrCreate(
                ['employee_id' => $item->employee_id, 'date' => $date],
                ['mode' => 'WFO', 'status' => 'leave', 'clock_in' => null, 'clock_out' => null, 'late_minutes' => 0, 'work_minutes' => 0, 'notes' => 'Cuti #'.$item->id],
            );
        }
    }

    /** @return list<string> */
    private function workDates(string|Carbon $start, string|Carbon $end): array
    {
        $workDays = CompanySetting::current()->work_days;
        $dates = [];

        for ($d = Carbon::parse($start); $d->lte(Carbon::parse($end)); $d->addDay()) {
            if (in_array($d->isoWeekday(), $workDays, true)) {
                $dates[] = $d->toDateString();
            }
        }

        return $dates;
    }
}
