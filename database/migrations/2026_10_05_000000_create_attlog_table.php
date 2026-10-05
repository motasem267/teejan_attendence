<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * جدول البصمات الخام اللي iVMS-4200 يكتب فيه مباشرة (Database Linkage).
 * أسماء الأعمدة مطابقة بالضبط لـ "Third-Party Database Table Field" اللي
 * ظاهرة في شاشة إعداد iVMS-4200 نفسها (Table Field mapping) — iVMS هو اللي
 * يفرض هاذي الأسماء، مش نحن.
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
            $table->dateTime('authDateTime');
            $table->date('authDate')->nullable();
            $table->time('authTime')->nullable();
            $table->string('direction', 20)->nullable();
            $table->string('deviceName', 100)->nullable();
            $table->string('deviceSN', 50)->nullable();
            $table->string('personName', 150)->nullable();
            $table->string('cardNo', 50)->nullable();
            $table->boolean('is_processed')->default(false);

            $table->index(['employeeID', 'authDateTime'], 'attlog_employee_authdatetime_index');
            $table->index('is_processed', 'attlog_is_processed_index');
            $table->index('deviceName', 'attlog_device_name_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attlog');
    }
};
