<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_periods', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->string('status')->default('draft'); // draft | finalized | paid
            $table->unsignedSmallInteger('working_days');
            $table->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('generated_at')->nullable();
            $table->timestamp('finalized_at')->nullable();
            $table->timestamps();

            $table->unique(['year', 'month']);
        });

        Schema::create('payslips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_period_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('present_days')->default(0);
            $table->decimal('overtime_hours', 6, 2)->default(0);

            $table->unsignedBigInteger('basic_salary');
            $table->unsignedBigInteger('fixed_allowance')->default(0);
            $table->unsignedBigInteger('meal_allowance')->default(0);
            $table->unsignedBigInteger('transport_allowance')->default(0);
            $table->unsignedBigInteger('overtime_pay')->default(0);
            $table->unsignedBigInteger('gross_salary');

            $table->unsignedBigInteger('bpjs_kesehatan')->default(0);
            $table->unsignedBigInteger('bpjs_jht')->default(0);
            $table->unsignedBigInteger('bpjs_jp')->default(0);
            $table->string('ter_category', 1)->nullable();
            $table->decimal('ter_rate', 5, 2)->default(0);
            $table->unsignedBigInteger('pph21')->default(0);
            $table->unsignedBigInteger('total_deductions');
            $table->unsignedBigInteger('net_salary');
            $table->timestamps();

            $table->unique(['payroll_period_id', 'employee_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payslips');
        Schema::dropIfExists('payroll_periods');
    }
};
