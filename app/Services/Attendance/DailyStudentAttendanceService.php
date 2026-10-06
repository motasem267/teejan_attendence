<?php

namespace App\Services\Attendance;

use App\Models\DailyStudentAttendance;
use App\Models\Resultsys\Student;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * حضور الطلبة: أول بصمة باليوم = دخول، آخر بصمة = خروج. يتعرّف على بصمة
 * الطالب بمعرّفه (employeeID) فقط — بلا أي شرط على اسم الجهاز، لأن الهوية
 * الحقيقية هي رقم الطالب نفسه، مش الجهاز اللي بصم عليه. البصمات محلية
 * (نفس جهاز teejan_attendence)، يكتب محليا ويعكس في resultsys.
 */
class DailyStudentAttendanceService
{
    public function processForDate(null|string|DateTimeInterface $date = null): array
    {
        $date = $this->normalizeDate($date);
        $attlog = config('attendance.attlog');

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
            $dayLogs = DB::connection($attlog['connection'])->table($attlog['table'])
                ->where($attlog['employee_column'], $studentId)
                ->whereDate($attlog['timestamp_column'], $date->toDateString())
                ->orderBy($attlog['timestamp_column'])
                ->orderBy($attlog['id_column'])
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

            try {
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
            } catch (QueryException $e) {
                Log::warning('فشلت مزامنة حضور طالب لـ resultsys', [
                    'student_id' => $studentId,
                    'date' => $date->toDateString(),
                    'error' => $e->getMessage(),
                ]);
            }

            $studentsRecorded++;

            $unprocessedIds = $dayLogs
                ->reject(fn ($log) => (int) ($log->{$attlog['processed_column']} ?? 0) === 1)
                ->pluck($attlog['id_column']);

            if ($unprocessedIds->isNotEmpty()) {
                DB::connection($attlog['connection'])->table($attlog['table'])
                    ->whereIn($attlog['id_column'], $unprocessedIds)
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
