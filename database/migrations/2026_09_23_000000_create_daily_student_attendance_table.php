<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_student_attendance', function (Blueprint $table): void {
            $table->id();
            // بلا foreign key: الطالب موجود في قاعدة بيانات أخرى (resultsys).
            // students.id رقم تلقائي عادي (bigint)، خلاف employees.id (varchar).
            $table->unsignedBigInteger('student_id');
            $table->date('date');
            $table->dateTime('first_check_in')->nullable();
            $table->dateTime('last_check_out')->nullable();
            $table->string('status', 30)->default('present');
            $table->timestamps();

            $table->unique(['student_id', 'date'], 'daily_student_attendance_unique_day');
            $table->index(['date', 'student_id'], 'daily_student_attendance_date_student_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_student_attendance');
    }
};
