<?php

namespace App\Models\Resultsys;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Student extends Model
{
    protected $connection = 'resultsys';

    protected $table = 'students';

    public $timestamps = true;

    protected $fillable = [
        'national_id',
        'full_name',
    ];

    public function status(): BelongsTo
    {
        return $this->belongsTo(StudentStatus::class, 'status_id');
    }

    /**
     * قيود الطالب في السنوات الدراسية (resultsys.student_enrollments).
     */
    public function enrollments(): HasMany
    {
        return $this->hasMany(StudentEnrollment::class, 'student_id');
    }

    /**
     * الطلبة المقيدين في السنة الدراسية الفعالة. لو السنة الفعالة ما فيها
     * حتى قيد طالب واحد (مثلا سنة جديدة لسه ما تم الترحيل لها)، نرجع كل
     * الطلبة بلا تقييد باش التقارير ما تفضاش فجأة لمجرد تأخر الترحيل.
     */
    public function scopeEnrolledActiveYear(Builder $query): Builder
    {
        $activeYearId = AcademicYear::getActiveId();

        if (! $activeYearId || StudentEnrollment::where('academic_year_id', $activeYearId)->doesntExist()) {
            return $query;
        }

        return $query->whereHas('enrollments', fn (Builder $e) => $e->where('academic_year_id', $activeYearId));
    }
}
