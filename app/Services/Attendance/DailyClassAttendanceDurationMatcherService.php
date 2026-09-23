<?php

namespace App\Services\Attendance;

use App\Models\DailyClassAttendance;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;

/**
 * منفذة من نفس منطق sp_GetTeacherSessions (T-SQL) اللي كان يشتغل قبل على
 * SQL Server — تجمع أي بصمتين متتاليات على نفس الجهاز بينهم مدة معقولة
 * (15-80 دقيقة افتراضيا) كحصة وحدة، بلا أي مقارنة بالجدول الدراسي خالص.
 * تُستعمل بس لما يكون نمط "الجلسات الزمنية" مفعّل في AttendanceSetting.
 */
class DailyClassAttendanceDurationMatcherService
{
    public function processForDate(null|string|DateTimeInterface $date = null): array
    {
        $date = $this->normalizeDate($date);
        $attlog = config('attendance.attlog');
        $excludedDevices = array_filter([
            config('attendance.reception_device'),
            config('attendance.student_device'),
        ]);
        $minMinutes = (int) config('attendance.duration_matching.min_minutes', 15);
        $maxMinutes = (int) config('attendance.duration_matching.max_minutes', 80);
        $graceMinutes = (int) config('attendance.duration_matching.grace_minutes', 2);

        $logs = DB::table($attlog['table'])
            ->whereDate($attlog['timestamp_column'], $date->toDateString())
            ->where(function ($query) use ($attlog): void {
                $query->whereNull($attlog['processed_column'])
                    ->orWhere($attlog['processed_column'], 0);
            })
            ->when(!empty($excludedDevices), fn ($query) => $query->whereNotIn($attlog['device_column'], $excludedDevices))
            ->orderBy($attlog['employee_column'])
            ->orderBy($attlog['timestamp_column'])
            ->orderBy('id')
            ->get();

        if ($logs->isEmpty()) {
            return [
                'date' => $date->toDateString(),
                'matched_sessions' => 0,
                'processed_logs' => 0,
                'unmatched_logs' => 0,
            ];
        }

        // إزالة تكرار البصمات بنفس التوقيت بالضبط لنفس الموظف (نفس منطق NOT EXISTS في T-SQL)
        $deduped = $logs->unique(fn ($log) => $log->{$attlog['employee_column']} . '|' . $log->{$attlog['timestamp_column']});

        $matchedSessions = 0;
        $processedLogs = 0;
        $unmatchedLogs = 0;

        foreach ($deduped->groupBy($attlog['employee_column']) as $employeeId => $employeeLogs) {
            $employeeLogs = $employeeLogs->values();
            $used = array_fill(0, $employeeLogs->count(), false);
            $lastExit = CarbonImmutable::parse('1900-01-01');

            for ($i = 0; $i < $employeeLogs->count(); $i++) {
                if ($used[$i]) {
                    continue;
                }

                $current = $employeeLogs[$i];
                $currentTime = CarbonImmutable::parse($current->{$attlog['timestamp_column']});

                if ($currentTime->lt($lastExit->subMinutes($graceMinutes))) {
                    continue;
                }

                $foundIndex = null;

                for ($j = $i + 1; $j < $employeeLogs->count(); $j++) {
                    if ($used[$j]) {
                        continue;
                    }

                    $candidate = $employeeLogs[$j];

                    if ($candidate->{$attlog['device_column']} !== $current->{$attlog['device_column']}) {
                        continue;
                    }

                    $diffMinutes = $currentTime->diffInMinutes(CarbonImmutable::parse($candidate->{$attlog['timestamp_column']}));

                    if ($diffMinutes < $minMinutes) {
                        continue;
                    }

                    if ($diffMinutes > $maxMinutes) {
                        break;
                    }

                    $foundIndex = $j;
                    break;
                }

                if ($foundIndex === null) {
                    continue;
                }

                $exitLog = $employeeLogs[$foundIndex];
                $exitTime = CarbonImmutable::parse($exitLog->{$attlog['timestamp_column']});

                DailyClassAttendance::updateOrCreate(
                    [
                        'employee_id' => $employeeId,
                        'date' => $date->toDateString(),
                        'start_time' => $currentTime->format('H:i:s'),
                        'end_time' => $exitTime->format('H:i:s'),
                    ],
                    [
                        'check_in_at' => $currentTime->toDateTimeString(),
                        'check_out_at' => $exitTime->toDateTimeString(),
                        'status' => config('attendance.statuses.completed', 'completed'),
                    ],
                );

                $this->mirrorToResultsys((string) $employeeId, $date->toDateString(), $currentTime, $exitTime);

                DB::table($attlog['table'])
                    ->whereIn('id', [$current->id, $exitLog->id])
                    ->update([$attlog['processed_column'] => 1]);

                $used[$i] = true;
                $used[$foundIndex] = true;
                $lastExit = $exitTime;
                $matchedSessions++;
                $processedLogs += 2;
            }

            $unmatchedLogs += count(array_filter($used, fn ($wasUsed) => !$wasUsed));
        }

        return [
            'date' => $date->toDateString(),
            'matched_sessions' => $matchedSessions,
            'processed_logs' => $processedLogs,
            'unmatched_logs' => $unmatchedLogs,
        ];
    }

    protected function mirrorToResultsys(string $employeeId, string $date, CarbonImmutable $checkIn, CarbonImmutable $checkOut): void
    {
        DB::connection('resultsys')->table('daily_class_attendance')->updateOrInsert(
            [
                'employee_id' => $employeeId,
                'date' => $date,
                'start_time' => $checkIn->format('H:i:s'),
                'end_time' => $checkOut->format('H:i:s'),
            ],
            [
                'check_in_at' => $checkIn->toDateTimeString(),
                'check_out_at' => $checkOut->toDateTimeString(),
                'status' => config('attendance.statuses.completed', 'completed'),
            ],
        );
    }

    protected function normalizeDate(null|string|DateTimeInterface $date): CarbonImmutable
    {
        return $date instanceof DateTimeInterface
            ? CarbonImmutable::instance($date)
            : CarbonImmutable::parse($date ?? now());
    }
}
