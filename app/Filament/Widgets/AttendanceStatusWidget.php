<?php

namespace App\Filament\Widgets;

use App\Models\DailyClassAttendance;
use App\Models\DailyEmployeeAttendance;
use App\Services\Attendance\DailyClassAttendanceMatcherService;
use App\Services\Attendance\DailyClassAttendanceSnapshotService;
use App\Services\Attendance\DailyEmployeeAttendanceService;
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

    public function mount(): void
    {
        $this->loadCounts();
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
    }

    public function syncNow(
        DailyClassAttendanceSnapshotService $snapshotService,
        DailyClassAttendanceMatcherService $matcherService,
        DailyEmployeeAttendanceService $employeeService,
    ): void {
        $inserted = $snapshotService->generateForDate();
        $classResult = $matcherService->processForDate();
        $employeeResult = $employeeService->processForDate();

        $this->loadCounts();

        Notification::make()
            ->title('تمت المزامنة')
            ->body(sprintf(
                'حصص جديدة: %d، دخول: %d، خروج: %d، موظفين مسجلين: %d، بصمات معالجة: %d',
                $inserted,
                $classResult['matched_check_ins'],
                $classResult['matched_check_outs'],
                $employeeResult['employees_recorded'],
                $classResult['processed_logs'] + $employeeResult['processed_logs'],
            ))
            ->success()
            ->send();
    }
}
