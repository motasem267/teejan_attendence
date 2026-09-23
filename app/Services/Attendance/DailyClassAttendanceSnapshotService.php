<?php

namespace App\Services\Attendance;

use App\Models\Resultsys\SchoolSchedule;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * منفذة من نفس منطق teejan_laravel_project — تبني snapshot يومي من الجدول
 * الدراسي (المقروء من resultsys) قبل وصول أي بصمة. تكتب في قاعدتين:
 * المحلية (الأساسية) ومرآة في resultsys (باش تقارير/مرتبات البرودكشن تبقى شغالة).
 */
class DailyClassAttendanceSnapshotService
{
    public function generateForDate(null|string|DateTimeInterface $date = null): int
    {
        $date = $this->normalizeDate($date);
        $dayOrder = $this->schoolDayOrder($date);

        $scheduleRows = SchoolSchedule::query()
            ->with(['teacherClass.teacher', 'lessonTime', 'day'])
            ->whereHas('day', function ($query) use ($dayOrder): void {
                $query->where('day_order', $dayOrder);
            })
            ->get()
            ->filter(function (SchoolSchedule $schedule): bool {
                return $schedule->teacherClass?->teacher !== null && $schedule->lessonTime !== null;
            });

        if ($scheduleRows->isEmpty()) {
            return 0;
        }

        $rows = $this->buildRows($scheduleRows, $date);

        $inserted = DB::table('daily_class_attendance')->insertOrIgnore($rows);
        DB::connection('resultsys')->table('daily_class_attendance')->insertOrIgnore($rows);

        return $inserted;
    }

    /**
     * @param  Collection<int, SchoolSchedule>  $scheduleRows
     * @return array<int, array<string, mixed>>
     */
    protected function buildRows(Collection $scheduleRows, CarbonImmutable $date): array
    {
        $status = config('attendance.statuses.pending', 'pending');
        $rows = [];

        foreach ($scheduleRows as $schedule) {
            $teacher = $schedule->teacherClass?->teacher;
            $lessonTime = $schedule->lessonTime;

            if (! $teacher || ! $lessonTime) {
                continue;
            }

            $rows[] = [
                'employee_id' => $teacher->id,
                'start_time' => $lessonTime->start_time,
                'end_time' => $lessonTime->end_time,
                'status' => $status,
                'check_in_at' => null,
                'check_out_at' => null,
                'date' => $date->toDateString(),
            ];
        }

        return array_values(array_reduce($rows, function (array $carry, array $row): array {
            $key = $row['employee_id'] . '|' . $row['date'] . '|' . $row['start_time'] . '|' . $row['end_time'];
            $carry[$key] = $row;

            return $carry;
        }, []));
    }

    protected function normalizeDate(null|string|DateTimeInterface $date): CarbonImmutable
    {
        return $date instanceof DateTimeInterface
            ? CarbonImmutable::instance($date)
            : CarbonImmutable::parse($date ?? now());
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
