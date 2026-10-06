<?php

namespace App\Filament\Widgets;

use App\Models\AttendanceSetting;
use App\Services\Attendance\DailyClassAttendanceDurationMatcherService;
use App\Services\Attendance\DailyClassAttendanceMatcherService;
use App\Services\Attendance\DailyClassAttendanceSnapshotService;
use App\Services\Attendance\DailyEmployeeAttendanceService;
use App\Services\Attendance\DailyStudentAttendanceService;
use Filament\Notifications\Notification;
use Filament\Widgets\Widget;
use Livewire\Attributes\On;

class AttendanceStatusWidget extends Widget
{
    protected string $view = 'filament.widgets.attendance-status-widget';

    protected int|string|array $columnSpan = 'full';

    public bool $isDurationMode = false;

    public string $date;

    public function mount(?string $date = null): void
    {
        $this->isDurationMode = AttendanceSetting::current()->isDurationMode();
        $this->date = $date ?? now()->toDateString();
    }

    #[On('date-changed')]
    public function onDateChanged(string $date): void
    {
        $this->date = $date;
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
            $classResult = $durationMatcherService->processForDate($this->date);
            $classSummary = sprintf(
                'حصص متطابقة (نمط الجلسات): %d، بصمات بلا تطابق: %d',
                $classResult['matched_sessions'],
                $classResult['unmatched_logs'],
            );
            $classProcessedLogs = $classResult['processed_logs'];
        } else {
            $snapshotService->generateForDate($this->date);
            $classResult = $matcherService->processForDate($this->date);
            $classSummary = sprintf(
                'دخول: %d، خروج: %d (نمط الجدول الدراسي)',
                $classResult['matched_check_ins'],
                $classResult['matched_check_outs'],
            );
            $classProcessedLogs = $classResult['processed_logs'];
        }

        $employeeResult = $employeeService->processForDate($this->date);
        $studentResult = $studentService->processForDate($this->date);

        $this->dispatch('attendance-synced');

        Notification::make()
            ->title('تمت المزامنة ليوم '.$this->date)
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
