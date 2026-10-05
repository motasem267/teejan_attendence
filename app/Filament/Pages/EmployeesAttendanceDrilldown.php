<?php

namespace App\Filament\Pages;

use App\Models\DailyEmployeeAttendance;
use App\Models\Resultsys\Employee;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use UnitEnum;

class EmployeesAttendanceDrilldown extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = \Filament\Support\Icons\Heroicon::OutlinedIdentification;
    protected static string|UnitEnum|null $navigationGroup = null;
    protected static bool $shouldRegisterNavigation = false;

    public string $status = 'present';
    public string $date;

    /** @var array<string, array{first_check_in: ?string, last_check_out: ?string}> */
    public array $timesMap = [];

    /** @var array<int, string> */
    public array $presentIds = [];

    public function mount(): void
    {
        $this->status = request()->query('status', 'present');
        $this->date = request()->query('date', now()->toDateString());

        $attendance = DailyEmployeeAttendance::query()
            ->whereDate('date', $this->date)
            ->get()
            ->keyBy('employee_id');

        $this->presentIds = $attendance->keys()->all();
        $this->timesMap = $attendance->map(fn ($record) => [
            'first_check_in' => optional($record->first_check_in)->format('H:i'),
            'last_check_out' => optional($record->last_check_out)->format('H:i'),
        ])->toArray();
    }

    public function table(Table $table): Table
    {
        $base = Employee::query()->with('employeeType')->nonTeachingStaff();

        $query = $this->status === 'present'
            ? (clone $base)->whereIn('id', $this->presentIds)
            : (clone $base)->whereNotIn('id', $this->presentIds);

        return $table
            ->query($query)
            ->columns([
                TextColumn::make('name')
                    ->label('الاسم')
                    ->searchable(),

                TextColumn::make('employeeType.type_name')
                    ->label('الوظيفة')
                    ->badge(),

                TextColumn::make('first_check_in_display')
                    ->label('أول دخول')
                    ->state(fn ($record) => $this->timesMap[$record->id]['first_check_in'] ?? '-')
                    ->visible($this->status === 'present'),

                TextColumn::make('last_check_out_display')
                    ->label('آخر خروج')
                    ->state(fn ($record) => $this->timesMap[$record->id]['last_check_out'] ?? '-')
                    ->visible($this->status === 'present'),
            ])
            ->defaultSort('name')
            ->striped();
    }

    public function getView(): string
    {
        return 'filament.pages.employees-attendance-drilldown';
    }

    public function getTitle(): string
    {
        return ($this->status === 'present' ? 'الموظفون الحاضرون' : 'الموظفون غير الحاضرين').' - '.$this->date;
    }
}
