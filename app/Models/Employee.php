<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Employee extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['join_date' => 'date'];
    }

    /** Nomor karyawan berikutnya: RG-TAHUN-URUT (urut per tahun masuk). */
    public static function nextNumber(string $joinDate): string
    {
        $year = substr($joinDate, 0, 4);
        $last = static::where('employee_number', 'like', "RG-$year-%")->max('employee_number');
        $seq = $last ? ((int) substr($last, -3)) + 1 : 1;

        return sprintf('RG-%s-%03d', $year, $seq);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(self::class, 'manager_id');
    }

    public function subordinates(): HasMany
    {
        return $this->hasMany(self::class, 'manager_id');
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    public function leaveRequests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class);
    }

    public function overtimeRequests(): HasMany
    {
        return $this->hasMany(OvertimeRequest::class);
    }

    public function payslips(): HasMany
    {
        return $this->hasMany(Payslip::class);
    }
}
