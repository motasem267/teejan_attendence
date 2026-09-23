<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DailyStudentAttendance extends Model
{
    protected $table = 'daily_student_attendance';

    protected $fillable = [
        'student_id',
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
