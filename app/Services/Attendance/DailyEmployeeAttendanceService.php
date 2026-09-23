<?php

namespace App\Services\Attendance;

use App\Models\DailyEmployeeAttendance;
use App\Models\Resultsys\Employee;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;

/**
 * حضور الموظفين غير المعلمين: أول بصمة باليوم = دخول، آخر بصمة = خروج، بلا
 * مقارنة بجدول (ما فماش وقت متوقع لهذي الفئة). يكتب محليا ويعكس في resultsys.
 */
class DailyEmployeeAttendanceService
{
    public function processForDate(null|string|DateTimeInterface $date = null): array
    {
        $date = $this->normalizeDate($date);
        $attlog = config('attendance.attlog');

        $employeeIds = Employee::query()
            ->whereDoesntHave('teacherClasses')
            ->pluck('id');

        if ($employeeIds->isEmpty()) {
            return [
                'date' => $date->toDateString(),
                'employees_recorded' => 0,
                'processed_logs' => 0,
            ];
        }

        $employeesRecorded = 0;
        $processedLogs = 0;

        foreach ($employeeIds as $employeeId) {
            $dayLogs = DB::connection('resultsys')->table($attlog['table'])
                ->where($attlog['employee_column'], $employeeId)
                ->whereDate($attlog['timestamp_column'], $date->toDateString())
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

            DailyEmployeeAttendance::updateOrCreate(
                ['employee_id' => $employeeId, 'date' => $date->toDateString()],
                [
                    'first_check_in' => $firstCheckIn,
                    'last_check_out' => $lastCheckOut,
                    'status' => 'present',
                ],
            );

            DB::connection('resultsys')->table('daily_employee_attendance')->updateOrInsert(
                ['employee_id' => $employeeId, 'date' => $date->toDateString()],
                [
                    'first_check_in' => $firstCheckIn,
                    'last_check_out' => $lastCheckOut,
                    'status' => 'present',
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            );

            $employeesRecorded++;

            $unprocessedIds = $dayLogs
                ->reject(fn ($log) => (int) ($log->{$attlog['processed_column']} ?? 0) === 1)
                ->pluck('id');

            if ($unprocessedIds->isNotEmpty()) {
                DB::connection('resultsys')->table($attlog['table'])
                    ->whereIn('id', $unprocessedIds)
                    ->update([$attlog['processed_column'] => 1]);

                $processedLogs += $unprocessedIds->count();
            }
        }

        return [
            'date' => $date->toDateString(),
            'employees_recorded' => $employeesRecorded,
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
