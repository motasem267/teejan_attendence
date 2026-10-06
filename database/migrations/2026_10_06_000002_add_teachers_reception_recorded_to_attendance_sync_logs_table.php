<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('attendance_sync_logs', 'teachers_reception_recorded')) {
            return;
        }

        Schema::table('attendance_sync_logs', function (Blueprint $table): void {
            $table->integer('teachers_reception_recorded')->default(0)->after('employees_recorded');
        });
    }

    public function down(): void
    {
        Schema::table('attendance_sync_logs', function (Blueprint $table): void {
            $table->dropColumn('teachers_reception_recorded');
        });
    }
};
