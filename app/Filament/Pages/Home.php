<?php

namespace App\Filament\Pages;

use App\Models\DailyClassAttendance;
use App\Models\DailyEmployeeAttendance;
use App\Models\DailyStudentAttendance;
use App\Models\Resultsys\Employee;
use App\Models\Resultsys\Student;
use BackedEnum;
use Filament\Pages\Page;
use Livewire\Attributes\On;
use UnitEnum;

class Home extends Page
{
    protected static string|BackedEnum|null $navigationIcon = \Filament\Support\Icons\Heroicon::OutlinedHome;
    protected static ?string $navigationLabel = 'الرئيسية';
    protected static string|UnitEnum|null $navigationGroup = null;
    protected static ?int $navigationSort = -100;

    public string $selectedDate;

    public int $completedSessionsCount = 0;
    public int $pendingSessionsCount = 0;
    public int $checkedInSessionsCount = 0;

    public int $presentStudentsCount = 0;
    public int $absentStudentsCount = 0;

    public int $presentEmployeesCount = 0;
    public int $absentEmployeesCount = 0;

    public function mount(): void
    {
        $this->selectedDate = now()->toDateString();
        $this->loadCounts();
    }

    public function updatedSelectedDate(): void
    {
        $this->loadCounts();
    }

    #[On('attendance-synced')]
    public function refreshCounts(): void
    {
        $this->loadCounts();
    }

    protected function loadCounts(): void
    {
        $date = $this->selectedDate;

        $sessionCounts = DailyClassAttendance::query()
            ->whereDate('date', $date)
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $this->completedSessionsCount = (int) ($sessionCounts['completed'] ?? 0);
        $this->pendingSessionsCount = (int) ($sessionCounts['pending'] ?? 0);
        $this->checkedInSessionsCount = (int) ($sessionCounts['checked_in'] ?? 0);

        $this->presentStudentsCount = DailyStudentAttendance::query()
            ->whereDate('date', $date)
            ->count();
        $totalStudents = Student::query()->count();
        $this->absentStudentsCount = max(0, $totalStudents - $this->presentStudentsCount);

        $this->presentEmployeesCount = DailyEmployeeAttendance::query()
            ->whereDate('date', $date)
            ->count();
        $totalEmployees = Employee::query()->nonTeachingStaff()->count();
        $this->absentEmployeesCount = max(0, $totalEmployees - $this->presentEmployeesCount);
    }

    public function getView(): string
    {
        return 'filament.pages.home';
    }

    public function getTitle(): string
    {
        return 'الرئيسية';
    }
}
