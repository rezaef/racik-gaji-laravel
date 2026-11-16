<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_settings', function (Blueprint $table) {
            $table->id();
            $table->string('company_name');
            $table->string('address');
            $table->string('city');
            $table->string('npwp')->nullable();
            $table->time('work_start')->default('08:00:00');
            $table->time('work_end')->default('16:00:00');
            $table->unsignedSmallInteger('grace_minutes')->default(15);
            $table->json('work_days'); // ISO weekday numbers, 1 = Senin
            $table->string('signer_name')->nullable();
            $table->string('signer_title')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_settings');
    }
};
