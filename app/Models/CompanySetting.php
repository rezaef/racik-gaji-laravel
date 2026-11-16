<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CompanySetting extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['work_days' => 'array', 'grace_minutes' => 'integer'];
    }

    /** Pengaturan perusahaan tunggal; dibuat dengan default PT Racik bila belum ada. */
    public static function current(): self
    {
        return static::first() ?? static::create([
            'company_name' => 'PT Racik Solusi Teknologi',
            'address' => 'Jl. Sidowaras No.35',
            'city' => 'Gresik',
            'work_days' => [1, 2, 3, 4, 5],
        ]);
    }
}
