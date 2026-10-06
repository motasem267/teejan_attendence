<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * سجل الأيام اللي تمت مزامنتها فعليا — يمنع تكرار مزامنة نفس اليوم مرتين
 * من صفحة "مزامنة الحضور" (نطاق تاريخ من/إلى).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_sync_logs', function (Blueprint $table): void {
            $table->id();
            $table->date('date')->unique();
            $table->string('mode', 20);
            $table->integer('employees_recorded')->default(0);
            $table->integer('students_recorded')->default(0);
            $table->string('class_summary')->nullable();
            $table->integer('processed_logs')->default(0);
            $table->timestamp('synced_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_sync_logs');
    }
};
