<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('manager_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->string('employee_number')->unique();
            $table->string('full_name');
            $table->string('position');
            $table->string('department');
            $table->date('join_date');
            $table->string('status')->default('active'); // active | inactive
            $table->string('ptkp_status')->default('TK/0'); // TK/0..TK/3, K/0..K/3
            $table->string('npwp')->nullable();
            $table->string('phone')->nullable();
            $table->string('bank_name')->nullable();
            $table->string('bank_account')->nullable();
            $table->unsignedBigInteger('basic_salary');
            $table->unsignedBigInteger('fixed_allowance')->default(0);
            $table->unsignedBigInteger('meal_allowance_daily')->default(0);
            $table->unsignedBigInteger('transport_allowance_daily')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
