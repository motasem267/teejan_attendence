<?php

namespace App\Models\Resultsys;

use Illuminate\Database\Eloquent\Model;

class LessonTime extends Model
{
    protected $connection = 'resultsys';

    protected $table = 'lesson_times';

    public $timestamps = false;

    protected $fillable = [
        'period_number',
        'start_time',
        'end_time',
        'is_break',
    ];
}
