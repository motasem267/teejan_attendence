<?php

namespace App\Models\Resultsys;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SchoolSchedule extends Model
{
    protected $connection = 'resultsys';

    protected $table = 'school_schedules';

    public $timestamps = false;

    protected $fillable = [
        'teacher_class_id',
        'day_id',
        'lesson_time_id',
    ];

    public function teacherClass(): BelongsTo
    {
        return $this->belongsTo(TeacherClass::class, 'teacher_class_id');
    }

    public function day(): BelongsTo
    {
        return $this->belongsTo(Day::class, 'day_id');
    }

    public function lessonTime(): BelongsTo
    {
        return $this->belongsTo(LessonTime::class, 'lesson_time_id');
    }
}
