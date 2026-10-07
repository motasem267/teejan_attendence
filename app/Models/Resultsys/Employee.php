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
     * قيود الموظف في السنوات الدراسية (resultsys.employee_enrollments) —
     * المسمى الوظيفي ممكن يتغير من سنة لسنة، فهذا هو مصدر الحقيقة لسنة
     * بعينها، مش عمود emp_type_id الأساسي في employees.
     */
    public function enrollments(): HasMany
    {
        return $this->hasMany(EmployeeEnrollment::class, 'employee_id');
    }

    /**
     * المعلمون: يتحددون بالمسمى الوظيفي لسنة التسجيل الفعالة إن وُجدت، وإلا
     * رجوع للمسمى الأساسي في employees (مثلا سنة جديدة لسه ما تم ترحيل
     * القيود لها) — باش التقارير ما تفضاش فجأة لمجرد تأخر الترحيل.
     */
    public function scopeTeachers(Builder $query): Builder
    {
        return self::scopeOfType($query, true);
    }

    /**
     * باقي الموظفين (غير المعلمين)، باستثناء حساب "ادمن" (حساب نظام
     * وليس موظف حقيقي).
     */
    public function scopeNonTeachingStaff(Builder $query): Builder
    {
        return self::scopeOfType($query, false)
            ->where('name', 'not like', '%ادمن%')
            ->where('name', 'not like', '%admin%');
    }

    protected static function scopeOfType(Builder $query, bool $teacher): Builder
    {
        $isTeacherType = fn (Builder $q) => $q->where('type_name', 'like', '%معلم%');
        $matchesType = fn (Builder $q) => $teacher ? $isTeacherType($q) : $q->whereNot($isTeacherType);

        $activeYearId = AcademicYear::getActiveId();
        $activeYearHasEnrollments = $activeYearId
            && EmployeeEnrollment::where('academic_year_id', $activeYearId)->exists();

        if (! $activeYearHasEnrollments) {
            return $query->whereHas('employeeType', $matchesType);
        }

        return $query->where(fn (Builder $q) => $q
            ->whereHas('enrollments', fn (Builder $e) => $e
                ->where('academic_year_id', $activeYearId)
                ->whereHas('employeeType', $matchesType))
            ->orWhere(fn (Builder $q2) => $q2
                ->whereDoesntHave('enrollments', fn (Builder $e) => $e->where('academic_year_id', $activeYearId))
                ->whereHas('employeeType', $matchesType)));
    }
}
