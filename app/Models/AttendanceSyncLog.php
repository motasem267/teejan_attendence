<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AttendanceSyncLog extends Model
{
    protected $table = 'attendance_sync_logs';

    protected $fillable = [
        'date',
        'mode',
        'employees_recorded',
        'students_recorded',
        'class_summary',
        'processed_logs',
        'synced_at',
    ];

    protected $casts = [
        'date' => 'date',
        'synced_at' => 'datetime',
    ];

    public static function alreadySyncedDates(string $from, string $to): array
    {
        return static::query()
            ->whereBetween('date', [$from, $to])
            ->pluck('date')
            ->map(fn ($date) => $date->toDateString())
            ->all();
    }
}
