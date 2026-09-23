<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DailyClassAttendance extends Model
{
    protected $table = 'daily_class_attendance';

    protected $fillable = [
        'employee_id',
        'start_time',
        'end_time',
        'status',
        'check_in_at',
        'check_out_at',
        'date',
    ];

    protected $casts = [
        'date' => 'date',
        'check_in_at' => 'datetime',
        'check_out_at' => 'datetime',
    ];

    public $timestamps = false;
}
