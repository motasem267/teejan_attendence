<?php

namespace App\Models\Resultsys;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TeacherClass extends Model
{
    protected $connection = 'resultsys';

    protected $table = 'teacher_classes';

    public $timestamps = false;

    protected $fillable = [
        'teacher_id',
        'subject_id',
        'class_id',
        'section_id',
        'academic_year_id',
    ];

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'teacher_id');
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(subject::class);
    }

    public function classModel(): BelongsTo
    {
        return $this->belongsTo(ClassModel::class, 'class_id');
    }

    public function schoolSchedules(): HasMany
    {
        return $this->hasMany(SchoolSchedule::class, 'teacher_class_id');
    }
}
