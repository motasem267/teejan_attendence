<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * حضور المعلمين من جهاز الاستقبال (Reception) — نفس منطق حضور الموظفين
 * بالضبط (أول بصمة = دخول، آخر بصمة = خروج)، بس مقتصر على معرّفات المعلمين
 * (Employee::teachers()). جدول منفصل عن daily_class_attendance (حصص الدرس)
 * لأن هذا تسجيل حضور عام باليوم، مش حصة محددة.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_teacher_reception_attendance', function (Blueprint $table): void {
            $table->id();
            // بلا foreign key: المعلم موجود في قاعدة بيانات أخرى (resultsys).
            $table->string('employee_id', 50);
            $table->date('date');
            $table->dateTime('first_check_in')->nullable();
            $table->dateTime('last_check_out')->nullable();
            $table->string('status', 30)->default('present');
            $table->timestamps();

            $table->unique(['employee_id', 'date'], 'daily_teacher_reception_attendance_unique_day');
            $table->index(['date', 'employee_id'], 'daily_teacher_reception_attendance_date_employee_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_teacher_reception_attendance');
    }
};
