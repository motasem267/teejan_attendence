<?php

namespace App\Services\Attendance;

use App\Models\Resultsys\AcademicYear;
use App\Models\Resultsys\SchoolSchedule;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;

/**
 * إجمالي الحصص المجدولة فعليا لمعلم (من الجدول الدراسي school_schedules)
 * خلال نطاق تاريخ معين — يحسب تكرار كل يوم أسبوع داخل النطاق ويضربه في
 * عدد الحصص المجدولة لهذا اليوم. مستعملة في تقرير الحضور الإجمالي (الشاشة
 * وملف PDF) باش الاثنين يحسبوا بنفس الطريقة بالضبط.
 */
class TeacherScheduledSessionsService
{
    /** @var array<string, int> */
    protected array $cache = [];

    public function calculate(string $teacherId, ?string $startDate, ?string $endDate): int
    {
        $cacheKey = $teacherId.'|'.$startDate.'|'.$endDate;

        return $this->cache[$cacheKey] ??= $this->compute($teacherId, $startDate, $endDate);
    }

    protected function compute(string $teacherId, ?string $startDate, ?string $endDate): int
    {
        if (!$startDate || !$endDate) {
            return 0;
        }

        $activeYearId = AcademicYear::getActiveId();

        $weeklySchedule = SchoolSchedule::query()
            ->whereHas('teacherClass', fn ($q) => $q->where('teacher_id', $teacherId)
                ->when($activeYearId, fn ($q2) => $q2->where('academic_year_id', $activeYearId)))
            ->with('day')
            ->get()
            ->filter(fn (SchoolSchedule $schedule) => $schedule->day !== null)
            ->groupBy(fn (SchoolSchedule $schedule) => $schedule->day->day_order);

        if ($weeklySchedule->isEmpty()) {
            return 0;
        }

        $dayOccurrences = [];
        foreach (CarbonPeriod::create($startDate, $endDate) as $date) {
            $dayOrder = $this->schoolDayOrder(CarbonImmutable::instance($date));
            $dayOccurrences[$dayOrder] = ($dayOccurrences[$dayOrder] ?? 0) + 1;
        }

        $total = 0;
        foreach ($weeklySchedule as $dayOrder => $schedulesForDay) {
            $total += $schedulesForDay->count() * ($dayOccurrences[$dayOrder] ?? 0);
        }

        return $total;
    }

    protected function schoolDayOrder(CarbonImmutable $date): int
    {
        return match ((int) $date->format('N')) {
            6 => 1,
            7 => 2,
            1 => 3,
            2 => 4,
            3 => 5,
            4 => 6,
            5 => 7,
        };
    }
}
