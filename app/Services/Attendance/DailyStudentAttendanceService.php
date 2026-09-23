<?php

namespace App\Services\Attendance;

use App\Models\DailyStudentAttendance;
use App\Models\Resultsys\Student;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;

/**
 * حضور الطلبة: أول بصمة باليوم = دخول، آخر بصمة = خروج، من جهاز مخصص للطلبة
 * وحدهم. البصمات محلية (نفس جهاز teejan_attendence)، يكتب محليا ويعكس في resultsys.
 */
class DailyStudentAttendanceService
{
    public function processForDate(null|string|DateTimeInterface $date = null): array
    {
        $date = $this->normalizeDate($date);
        $attlog = config('attendance.attlog');
        $studentDevice = config('attendance.student_device');

        if (!$studentDevice) {
            return [
                'date' => $date->toDateString(),
                'students_recorded' => 0,
                'processed_logs' => 0,
            ];
        }

        $studentIds = Student::query()->pluck('id');

        if ($studentIds->isEmpty()) {
            return [
                'date' => $date->toDateString(),
                'students_recorded' => 0,
                'processed_logs' => 0,
            ];
        }

        $studentsRecorded = 0;
        $processedLogs = 0;

        foreach ($studentIds as $studentId) {
            $dayLogs = DB::table($attlog['table'])
                ->where($attlog['employee_column'], $studentId)
                ->whereDate($attlog['timestamp_column'], $date->toDateString())
                ->where($attlog['device_column'], $studentDevice)
                ->orderBy($attlog['timestamp_column'])
                ->orderBy('id')
                ->get();

            if ($dayLogs->isEmpty()) {
                continue;
            }

            $firstCheckIn = $dayLogs->first()->{$attlog['timestamp_column']};
            $lastCheckOut = $dayLogs->count() > 1
                ? $dayLogs->last()->{$attlog['timestamp_column']}
                : null;

            DailyStudentAttendance::updateOrCreate(
                ['student_id' => $studentId, 'date' => $date->toDateString()],
                [
                    'first_check_in' => $firstCheckIn,
                    'last_check_out' => $lastCheckOut,
                    'status' => 'present',
                ],
            );

            DB::connection('resultsys')->table('daily_student_attendance')->updateOrInsert(
                ['student_id' => $studentId, 'date' => $date->toDateString()],
                [
                    'first_check_in' => $firstCheckIn,
                    'last_check_out' => $lastCheckOut,
                    'status' => 'present',
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            );

            $studentsRecorded++;

            $unprocessedIds = $dayLogs
                ->reject(fn ($log) => (int) ($log->{$attlog['processed_column']} ?? 0) === 1)
                ->pluck('id');

            if ($unprocessedIds->isNotEmpty()) {
                DB::table($attlog['table'])
                    ->whereIn('id', $unprocessedIds)
                    ->update([$attlog['processed_column'] => 1]);

                $processedLogs += $unprocessedIds->count();
            }
        }

        return [
            'date' => $date->toDateString(),
            'students_recorded' => $studentsRecorded,
            'processed_logs' => $processedLogs,
        ];
    }

    protected function normalizeDate(null|string|DateTimeInterface $date): CarbonImmutable
    {
        return $date instanceof DateTimeInterface
            ? CarbonImmutable::instance($date)
            : CarbonImmutable::parse($date ?? now());
    }
}
