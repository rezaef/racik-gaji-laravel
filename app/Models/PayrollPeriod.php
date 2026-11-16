<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PayrollPeriod extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['generated_at' => 'datetime', 'finalized_at' => 'datetime'];
    }

    public function payslips(): HasMany
    {
        return $this->hasMany(Payslip::class);
    }

    public function label(): string
    {
        return \Carbon\Carbon::create($this->year, $this->month, 1)->locale('id')->translatedFormat('F Y');
    }
}
