<?php

namespace App\Filament\Pages;

use App\Models\DailyEmployeeAttendance;
use App\Models\Resultsys\Employee;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use UnitEnum;

class EmployeeAttendanceReport extends Page implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = \Filament\Support\Icons\Heroicon::OutlinedUsers;
    protected static ?string $navigationLabel = 'تقرير حضور الموظفين';
    protected static string|UnitEnum|null $navigationGroup = null;

    public ?array $data = [];

    public ?string $selectedEmployeeId = null;
    public ?string $startDate = null;
    public ?string $endDate = null;

    protected ?Collection $employeeNamesCache = null;

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Grid::make(3)
                    ->schema([
                        Select::make('employee_id')
                            ->label('الموظف')
                            ->options(fn () => $this->employeeNames())
                            ->searchable()
                            ->placeholder('كل الموظفين'),

                        DatePicker::make('start_date')
                            ->label('من تاريخ'),

                        DatePicker::make('end_date')
                            ->label('إلى تاريخ'),
                    ]),
            ])
            ->statePath('data');
    }

    public function search(): void
    {
        $state = $this->form->getState();

        $this->selectedEmployeeId = $state['employee_id'] ?? null;
        $this->startDate = $state['start_date'] ?? null;
        $this->endDate = $state['end_date'] ?? null;

        $this->resetTable();
    }

    public function resetSearch(): void
    {
        $this->form->fill();
        $this->selectedEmployeeId = null;
        $this->startDate = null;
        $this->endDate = null;

        $this->resetTable();
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
            ->defaultSort('date', 'desc')
            ->striped();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('addManualRecord')
                ->label('إضافة سجل حضور يدوي')
                ->icon('heroicon-o-plus')
                ->form([
                    Select::make('employee_id')
                        ->label('الموظف')
                        ->options(fn () => $this->employeeNames())
                        ->searchable()
                        ->required(),

                    DatePicker::make('date')
                        ->label('التاريخ')
                        ->default(now())
                        ->required(),

                    DateTimePicker::make('first_check_in')
                        ->label('أول دخول')
                        ->seconds(false)
                        ->required(),

                    DateTimePicker::make('last_check_out')
                        ->label('آخر خروج')
                        ->seconds(false),
                ])
                ->action(function (array $data): void {
                    DailyEmployeeAttendance::updateOrCreate(
                        ['employee_id' => $data['employee_id'], 'date' => $data['date']],
                        [
                            'first_check_in' => $data['first_check_in'],
                            'last_check_out' => $data['last_check_out'] ?? null,
                            'status' => 'present',
                        ],
                    );

                    DB::connection('resultsys')->table('daily_employee_attendance')->updateOrInsert(
                        ['employee_id' => $data['employee_id'], 'date' => $data['date']],
                        [
                            'first_check_in' => $data['first_check_in'],
                            'last_check_out' => $data['last_check_out'] ?? null,
                            'status' => 'present',
                            'created_at' => now(),
                            'updated_at' => now(),
                        ],
                    );

                    Notification::make()
                        ->title('تمت إضافة السجل')
                        ->success()
                        ->send();

                    $this->resetTable();
                }),
        ];
    }

    protected function employeeNames(): Collection
    {
        return $this->employeeNamesCache ??= Employee::query()
            ->nonTeachingStaff()
            ->orderBy('name')
            ->pluck('name', 'id');
    }

    public function getFilteredTableQuery(): Builder
    {
        return DailyEmployeeAttendance::query()
            ->when($this->selectedEmployeeId, fn ($q) => $q->where('employee_id', $this->selectedEmployeeId))
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
