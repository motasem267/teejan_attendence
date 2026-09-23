<?php

namespace App\Filament\Pages;

use App\Models\DailyEmployeeAttendance;
use App\Models\Resultsys\Employee;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use UnitEnum;

class EmployeeAttendanceReport extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = \Filament\Support\Icons\Heroicon::OutlinedUsers;
    protected static ?string $navigationLabel = 'تقرير حضور الموظفين';
    protected static string|UnitEnum|null $navigationGroup = null;

    public ?int $selectedEmployeeId = null;
    public ?string $startDate = null;
    public ?string $endDate = null;

    protected ?Collection $employeeNamesCache = null;

    public function mount(): void
    {
        $this->startDate = now()->startOfMonth()->format('Y-m-d');
        $this->endDate = now()->format('Y-m-d');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getFilteredTableQuery())
            ->columns([
                TextColumn::make('date')
                    ->label('التاريخ')
                    ->date('Y-m-d')
                    ->sortable(),

                TextColumn::make('employee_id')
                    ->label('الموظف')
                    ->formatStateUsing(fn ($state) => $this->employeeNames()->get($state) ?? '-'),

                TextColumn::make('first_check_in')
                    ->label('أول دخول')
                    ->dateTime('H:i'),

                TextColumn::make('last_check_out')
                    ->label('آخر خروج')
                    ->dateTime('H:i')
                    ->placeholder('-'),
            ])
            ->filters([
                Filter::make('employee_filter')
                    ->label('اختر الموظف')
                    ->form([
                        Select::make('employee_id')
                            ->label('الموظف')
                            ->options(fn () => $this->employeeNames())
                            ->searchable(),
                    ])
                    ->query(function ($query, array $data) {
                        $this->selectedEmployeeId = $data['employee_id'] ?? null;
                        return $query->when($this->selectedEmployeeId, fn ($q) => $q->where('employee_id', $this->selectedEmployeeId));
                    }),

                Filter::make('date_range')
                    ->label('نطاق التاريخ')
                    ->form([
                        DatePicker::make('start_date')->label('من التاريخ'),
                        DatePicker::make('end_date')->label('إلى التاريخ'),
                    ])
                    ->query(function ($query, array $data) {
                        $this->startDate = $data['start_date'] ?? null;
                        $this->endDate = $data['end_date'] ?? null;
                        return $query
                            ->when($data['start_date'] ?? null, fn ($q, $date) => $q->whereDate('date', '>=', $date))
                            ->when($data['end_date'] ?? null, fn ($q, $date) => $q->whereDate('date', '<=', $date));
                    }),
            ])
            ->defaultSort('date', 'desc')
            ->striped();
    }

    protected function employeeNames(): Collection
    {
        return $this->employeeNamesCache ??= Employee::query()
            ->whereDoesntHave('teacherClasses')
            ->orderBy('name')
            ->pluck('name', 'id');
    }

    public function getFilteredTableQuery(): Builder
    {
        return DailyEmployeeAttendance::query()
            ->when($this->startDate, fn ($q) => $q->whereDate('date', '>=', $this->startDate))
            ->when($this->endDate, fn ($q) => $q->whereDate('date', '<=', $this->endDate))
            ->orderBy('date', 'desc');
    }

    public function getView(): string
    {
        return 'filament.pages.employee-attendance-report';
    }

    public function getTitle(): string
    {
        return 'تقرير حضور الموظفين';
    }
}
