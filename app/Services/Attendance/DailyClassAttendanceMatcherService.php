<?php

namespace App\Services\Attendance;

use App\Models\DailyClassAttendance;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * منفذة من نفس منطق teejan_laravel_project — تقارن بصمات attlog (المقروءة من
 * resultsys) مع الـsnapshot المحلي. كل تحديث يتكتب محليا (المصدر الأساسي)
 * وفي resultsys كمرآة، وعلامة is_processed في attlog تبقى في resultsys فقط.
 */
class DailyClassAttendanceMatcherService
{
    public function processForDate(null|string|DateTimeInterface $date = null): array
    {
        $date = $this->normalizeDate($date);
        $attlog = config('attendance.attlog');

        $snapshots = DailyClassAttendance::query()
            ->whereDate('date', $date->toDateString())
            ->orderBy('employee_id')
            ->orderBy('start_time')
            ->get();

        if ($snapshots->isEmpty()) {
            return [
                'date' => $date->toDateString(),
                'matched_check_ins' => 0,
                'matched_check_outs' => 0,
                'processed_logs' => 0,
            ];
        }

        $employeeIds = $snapshots->pluck('employee_id')->unique()->values();

        $logs = DB::connection('resultsys')->table($attlog['table'])
            ->whereIn($attlog['employee_column'], $employeeIds)
            ->whereDate($attlog['timestamp_column'], $date->toDateString())
            ->where(function ($query) use ($attlog): void {
                $query->whereNull($attlog['processed_column'])
                    ->orWhere($attlog['processed_column'], 0);
            })
            ->orderBy($attlog['employee_column'])
            ->orderBy($attlog['timestamp_column'])
            ->orderBy('id')
            ->get();

        $groupedLogs = $logs->groupBy($attlog['employee_column']);
        $matchedCheckIns = 0;
        $matchedCheckOuts = 0;
        $processedLogs = 0;

        foreach ($snapshots->groupBy('employee_id') as $employeeId => $employeeSnapshots) {
            $employeeLogs = $groupedLogs->get($employeeId, collect())->values();
            $logIndex = 0;

            foreach ($employeeSnapshots->sortBy('start_time') as $snapshot) {
                if ($snapshot->status === config('attendance.statuses.completed', 'completed')) {
                    continue;
                }

                if (! $snapshot->check_in_at) {
                    $checkInMatch = $this->matchLog(
                        logs: $employeeLogs,
                        startIndex: $logIndex,
                        lowerBound: CarbonImmutable::parse($date->toDateString() . ' ' . $snapshot->start_time)->subMinutes((int) config('attendance.windows.check_in_before_minutes', 20)),
                        upperBound: CarbonImmutable::parse($date->toDateString() . ' ' . $snapshot->start_time)->addMinutes((int) config('attendance.windows.check_in_after_minutes', 15)),
                        timestampColumn: $attlog['timestamp_column'],
                    );

                    if ($checkInMatch !== null) {
                        $this->markSnapshotCheckIn($snapshot, $checkInMatch['timestamp']);
                        $this->markLogAsProcessed($attlog['table'], $attlog['processed_column'], $checkInMatch['id']);

                        $matchedCheckIns++;
                        $processedLogs++;
                        $logIndex = $checkInMatch['index'] + 1;
                    }
                }

                if ($snapshot->check_in_at && ! $snapshot->check_out_at) {
                    $checkOutMatch = $this->matchLog(
                        logs: $employeeLogs,
                        startIndex: $logIndex,
                        lowerBound: CarbonImmutable::parse($date->toDateString() . ' ' . $snapshot->end_time)->subMinutes((int) config('attendance.windows.check_out_before_minutes', 5)),
                        upperBound: CarbonImmutable::parse($date->toDateString() . ' ' . $snapshot->end_time)->addMinutes((int) config('attendance.windows.check_out_after_minutes', 20)),
                        timestampColumn: $attlog['timestamp_column'],
                    );

                    if ($checkOutMatch !== null) {
                        $this->markSnapshotCheckOut($snapshot, $checkOutMatch['timestamp']);
                        $this->markLogAsProcessed($attlog['table'], $attlog['processed_column'], $checkOutMatch['id']);

                        $matchedCheckOuts++;
                        $processedLogs++;
                        $logIndex = $checkOutMatch['index'] + 1;
                    }
                }
            }
        }

        return [
            'date' => $date->toDateString(),
            'matched_check_ins' => $matchedCheckIns,
            'matched_check_outs' => $matchedCheckOuts,
            'processed_logs' => $processedLogs,
        ];
    }

    /**
     * @param  Collection<int, object>  $logs
     * @return array{id:int,index:int,timestamp:string}|null
     */
    protected function matchLog(Collection $logs, int $startIndex, CarbonImmutable $lowerBound, CarbonImmutable $upperBound, string $timestampColumn): ?array
    {
        for ($index = $startIndex; $index < $logs->count(); $index++) {
            $log = $logs->get($index);
            $timestamp = CarbonImmutable::parse($log->{$timestampColumn});

            if ($timestamp->lt($lowerBound)) {
                continue;
            }

            if ($timestamp->gt($upperBound)) {
                return null;
            }

            return [
                'id' => $log->id,
                'index' => $index,
                'timestamp' => $timestamp->toDateTimeString(),
            ];
        }

        return null;
    }

    protected function markSnapshotCheckIn(DailyClassAttendance $snapshot, string $timestamp): void
    {
        $status = config('attendance.statuses.checked_in', 'checked_in');

        $snapshot->forceFill([
            'check_in_at' => $timestamp,
            'status' => $status,
        ])->save();

        $this->mirrorToResultsys($snapshot, ['check_in_at' => $timestamp, 'status' => $status]);
    }

    protected function markSnapshotCheckOut(DailyClassAttendance $snapshot, string $timestamp): void
    {
        $status = config('attendance.statuses.completed', 'completed');

        $snapshot->forceFill([
            'check_out_at' => $timestamp,
            'status' => $status,
        ])->save();

        $this->mirrorToResultsys($snapshot, ['check_out_at' => $timestamp, 'status' => $status]);
    }

    protected function mirrorToResultsys(DailyClassAttendance $snapshot, array $values): void
    {
        DB::connection('resultsys')->table('daily_class_attendance')->updateOrInsert(
            [
                'employee_id' => $snapshot->employee_id,
                'date' => $snapshot->date->toDateString(),
                'start_time' => $snapshot->start_time,
                'end_time' => $snapshot->end_time,
            ],
            $values,
        );
    }

    protected function markLogAsProcessed(string $table, string $processedColumn, int $logId): void
    {
        DB::connection('resultsys')->table($table)
            ->where('id', $logId)
            ->update([$processedColumn => 1]);
    }

    protected function normalizeDate(null|string|DateTimeInterface $date): CarbonImmutable
    {
        return $date instanceof DateTimeInterface
            ? CarbonImmutable::instance($date)
            : CarbonImmutable::parse($date ?? now());
    }
}
