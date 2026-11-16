<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ReviewRequest;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Alur pengajuan -> persetujuan yang dipakai cuti, lembur, dan koreksi absensi. */
abstract class ApprovalController extends Controller
{
    /** @return class-string<Model> */
    abstract protected function model(): string;

    /** Dipanggil di dalam transaksi setelah pengajuan disetujui. */
    protected function onApproved(Model $item): void {}

    public function index(Request $request): JsonResponse
    {
        $query = ($this->model())::with('employee:id,full_name,position')
            ->when($request->status, fn ($q, $status) => $q->where('status', $status))
            ->latest();

        return response()->json($this->scopeToViewer($query, $request->user(), 'employee_id')->paginate($request->integer('per_page', 25)));
    }

    public function approve(ReviewRequest $request, int $id): JsonResponse
    {
        return $this->review($request, $id, 'approved');
    }

    public function reject(ReviewRequest $request, int $id): JsonResponse
    {
        return $this->review($request, $id, 'rejected');
    }

    private function review(ReviewRequest $request, int $id, string $status): JsonResponse
    {
        $reviewer = $request->user()->employee ?? abort(403);

        $item = DB::transaction(function () use ($id, $status, $reviewer, $request) {
            $item = ($this->model())::lockForUpdate()->findOrFail($id);

            abort_unless($this->scopeToViewer(($this->model())::whereKey($id), $request->user(), 'employee_id')->exists(), 403, 'Pengajuan ini bukan dari tim Anda.');
            abort_if($item->employee_id === $reviewer->id, 403, 'Tidak dapat menyetujui pengajuan sendiri.');

            if ($item->status !== 'pending') {
                throw ValidationException::withMessages(['status' => 'Pengajuan ini sudah diproses.']);
            }

            $item->update([
                'status' => $status,
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
                'review_note' => $request->review_note,
            ]);

            if ($status === 'approved') {
                $this->onApproved($item);
            }

            return $item;
        });

        return response()->json(['data' => $item->load('employee:id,full_name')]);
    }
}
