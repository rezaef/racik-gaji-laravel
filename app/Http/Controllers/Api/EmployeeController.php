<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\EmployeeRequest;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EmployeeController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $employees = Employee::with(['user:id,email,role', 'manager:id,full_name'])
            ->when($request->status, fn ($q, $status) => $q->where('status', $status))
            ->when($request->department, fn ($q, $department) => $q->where('department', $department))
            ->when($request->search, fn ($q, $s) => $q->where(fn ($w) => $w->where('full_name', 'like', "%$s%")->orWhere('employee_number', 'like', "%$s%")))
            ->orderBy('full_name')
            ->paginate($request->integer('per_page', 25));

        return response()->json($employees);
    }

    public function show(Employee $employee): JsonResponse
    {
        return response()->json(['data' => $employee->load(['user:id,email,role', 'manager:id,full_name'])]);
    }

    public function store(EmployeeRequest $request): JsonResponse
    {
        $data = $request->validated();

        $employee = DB::transaction(function () use ($data) {
            $user = User::create([
                'name' => $data['full_name'], 'email' => $data['email'],
                'password' => $data['password'], 'role' => $data['role'],
            ]);

            return $user->employee()->create(collect($data)->except(['email', 'password', 'role'])->all());
        });

        return response()->json(['data' => $employee->load('user:id,email,role')], 201);
    }

    public function update(EmployeeRequest $request, Employee $employee): JsonResponse
    {
        $data = $request->validated();

        DB::transaction(function () use ($data, $employee) {
            $user = collect($data)->only(['email', 'password', 'role'])->all();
            if (isset($data['full_name'])) {
                $user['name'] = $data['full_name'];
            }
            $employee->user->update($user);
            $employee->update(collect($data)->except(['email', 'password', 'role'])->all());
        });

        return response()->json(['data' => $employee->fresh(['user:id,email,role'])]);
    }

    /** Karyawan dinonaktifkan (bukan dihapus) agar riwayat absensi dan payslip tetap utuh. */
    public function destroy(Employee $employee): JsonResponse
    {
        $employee->update(['status' => 'inactive']);
        $employee->user->tokens()->delete();

        return response()->json(['message' => 'Karyawan dinonaktifkan.']);
    }
}
