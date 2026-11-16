<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

abstract class Controller
{
    /** Batasi query ke data yang boleh dilihat: HR semua, manajer tim sendiri, karyawan dirinya. */
    protected function scopeToViewer(Builder $query, User $user, string $column): Builder
    {
        if ($user->isHr()) {
            return $query;
        }

        $employee = $user->employee ?? abort(403, 'Akun ini tidak terhubung ke data karyawan.');

        if ($user->isManager()) {
            return $query->whereIn($column, Employee::where('manager_id', $employee->id)->pluck('id')->push($employee->id));
        }

        return $query->where($column, $employee->id);
    }
}
