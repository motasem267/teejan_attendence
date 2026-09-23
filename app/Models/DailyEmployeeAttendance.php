<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DailyEmployeeAttendance extends Model
{
    protected $table = 'daily_employee_attendance';

    protected $fillable = [
        'employee_id',
        'date',
        'first_check_in',
        'last_check_out',
        'status',
    ];

    protected $casts = [
        'date' => 'date',
        'first_check_in' => 'datetime',
        'last_check_out' => 'datetime',
    ];
}
