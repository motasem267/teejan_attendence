<?php

namespace App\Filament\Widgets;

use App\Models\AttendanceSetting;
use App\Models\DailyClassAttendance;
use App\Models\DailyEmployeeAttendance;
use App\Models\DailyStudentAttendance;
use App\Services\Attendance\DailyClassAttendanceDurationMatcherService;
use App\Services\Attendance\DailyClassAttendanceMatcherService;
use App\Services\Attendance\DailyClassAttendanceSnapshotService;
use App\Services\Attendance\DailyEmployeeAttendanceService;
use App\Services\Attendance\DailyStudentAttendanceService;
use Filament\Notifications\Notification;
use Filament\Widgets\Widget;

class AttendanceStatusWidget extends Widget
{
    protected string $view = 'filament.widgets.attendance-status-widget';

    protected int|string|array $columnSpan = 'full';

    public int $completedCount = 0;
    public int $checkedInCount = 0;
    public int $pendingCount = 0;
    public int $presentEmployeesCount = 0;
    public int $presentStudentsCount = 0;
    public bool $isDurationMode = false;

    public function mount(): void
    {
        $this->loadCounts();
        $this->isDurationMode = AttendanceSetting::current()->isDurationMode();
    }

    protected function loadCounts(): void
    {
        $today = now()->toDateString();

        $classCounts = DailyClassAttendance::query()
            ->whereDate('date', $today)
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $this->completedCount = (int) ($classCounts['completed'] ?? 0);
        $this->checkedInCount = (int) ($classCounts['checked_in'] ?? 0);
        $this->pendingCount = (int) ($classCounts['pending'] ?? 0);

        $this->presentEmployeesCount = DailyEmployeeAttendance::query()
            ->whereDate('date', $today)
            ->count();

        $this->presentStudentsCount = DailyStudentAttendance::query()
            ->whereDate('date', $today)
            ->count();
    }

    public function toggleMode(): void
    {
        $setting = AttendanceSetting::current();
        $setting->toggle();
        $this->isDurationMode = $setting->isDurationMode();

        Notification::make()
            ->title('تم تبديل نمط مطابقة حصص المعلمين')
            ->body($this->isDurationMode
                ? 'النمط الحالي: الجلسات الزمنية (بلا جدول دراسي)'
                : 'النمط الحالي: الجدول الدراسي (snapshot)')
            ->success()
            ->send();
    }

    public function syncNow(
        DailyClassAttendanceSnapshotService $snapshotService,
        DailyClassAttendanceMatcherService $matcherService,
        DailyClassAttendanceDurationMatcherService $durationMatcherService,
        DailyEmployeeAttendanceService $employeeService,
        DailyStudentAttendanceService $studentService,
    ): void {
        $mode = AttendanceSetting::current();

        if ($mode->isDurationMode()) {
            $classResult = $durationMatcherService->processForDate();
            $classSummary = sprintf(
                'حصص متطابقة (نمط الجلسات): %d، بصمات بلا تطابق: %d',
                $classResult['matched_sessions'],
                $classResult['unmatched_logs'],
            );
            $classProcessedLogs = $classResult['processed_logs'];
        } else {
            $snapshotService->generateForDate();
            $classResult = $matcherService->processForDate();
            $classSummary = sprintf(
                'دخول: %d، خروج: %d (نمط الجدول الدراسي)',
                $classResult['matched_check_ins'],
                $classResult['matched_check_outs'],
            );
            $classProcessedLogs = $classResult['processed_logs'];
        }

        $employeeResult = $employeeService->processForDate();
        $studentResult = $studentService->processForDate();

        $this->loadCounts();

        Notification::make()
            ->title('تمت المزامنة')
            ->body(sprintf(
                '%s. موظفين مسجلين: %d، طلبة مسجلين: %d، إجمالي بصمات معالجة: %d',
                $classSummary,
                $employeeResult['employees_recorded'],
                $studentResult['students_recorded'],
                $classProcessedLogs + $employeeResult['processed_logs'] + $studentResult['processed_logs'],
            ))
            ->success()
            ->send();
    }
}
