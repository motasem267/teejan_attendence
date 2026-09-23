<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_employee_attendance', function (Blueprint $table): void {
            $table->id();
            // بلا foreign key: الموظف موجود في قاعدة بيانات أخرى (resultsys)
            $table->unsignedBigInteger('employee_id');
            $table->date('date');
            $table->dateTime('first_check_in')->nullable();
            $table->dateTime('last_check_out')->nullable();
            $table->string('status', 30)->default('present');
            $table->timestamps();

            $table->unique(['employee_id', 'date'], 'daily_employee_attendance_unique_day');
            $table->index(['date', 'employee_id'], 'daily_employee_attendance_date_employee_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_employee_attendance');
    }
};
