<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * جدول البصمات الخام اللي iVMS-4200 يكتب فيه مباشرة (device database
 * linkage). الأسماء والنوع مطابقين لمعيار ZKTeco/Hikvision ATTLOG
 * التقليدي، ونفس أسماء الأعمدة المستعملة في config/attendance.php
 * (employeeID, checktime, deviceName, is_processed) زي ما كانت فالنظام
 * القديم المبني على SQL Server.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('attlog')) {
            return;
        }

        Schema::create('attlog', function (Blueprint $table): void {
            $table->id();
            $table->string('employeeID', 50);
            $table->dateTime('checktime');
            $table->string('checktype', 10)->nullable();
            $table->integer('verifycode')->nullable();
            $table->string('sensorid', 20)->nullable();
            $table->string('Memoinfo', 50)->nullable();
            $table->integer('workcode')->nullable()->default(0);
            $table->string('sn', 20)->nullable();
            $table->integer('UserExtFmt')->nullable();
            $table->string('deviceName', 100)->nullable();
            $table->boolean('is_processed')->default(false);

            $table->index(['employeeID', 'checktime'], 'attlog_employee_checktime_index');
            $table->index('is_processed', 'attlog_is_processed_index');
            $table->index('deviceName', 'attlog_device_name_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attlog');
    }
};
