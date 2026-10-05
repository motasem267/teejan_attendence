<?php

namespace App\Models\Resultsys;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Employee extends Model
{
    protected $connection = 'resultsys';

    protected $table = 'employees';

    public $incrementing = false;

    public $timestamps = true;

    protected $fillable = [
        'id',
        'name',
        'emp_type_id',
        'status_id',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    public function teacherClasses(): HasMany
    {
        return $this->hasMany(TeacherClass::class, 'teacher_id');
    }

    public function employeeType(): BelongsTo
    {
        return $this->belongsTo(EmployeeType::class, 'emp_type_id');
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(EmployeeStatus::class, 'status_id');
    }

    /**
     * المعلمون: يتحددون بالمسمى الوظيفي (معلم/معلمة)، مش بوجود حصص مسندة
     * فعليا — موظف نوعه "معلم" وبلا حصص لسه يبقى معلم، مش موظف إداري.
     */
    public function scopeTeachers(Builder $query): Builder
    {
        return $query->whereHas(
            'employeeType',
            fn (Builder $q) => $q->where('type_name', 'like', '%معلم%'),
        );
    }

    /**
     * باقي الموظفين (غير المعلمين)، باستثناء حساب "ادمن" (حساب نظام
     * وليس موظف حقيقي).
     */
    public function scopeNonTeachingStaff(Builder $query): Builder
    {
        return $query
            ->whereDoesntHave(
                'employeeType',
                fn (Builder $q) => $q->where('type_name', 'like', '%معلم%'),
            )
            ->where('name', 'not like', '%ادمن%')
            ->where('name', 'not like', '%admin%');
    }
}
