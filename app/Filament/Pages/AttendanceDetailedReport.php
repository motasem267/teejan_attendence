<?php

namespace App\Filament\Pages;

use App\Models\DailyClassAttendance;
use App\Models\Resultsys\Employee;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TimePicker;
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

class AttendanceDetailedReport extends Page implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = \Filament\Support\Icons\Heroicon::OutlinedDocumentText;
    protected static ?string $navigationLabel = 'تقرير حضور المعلمين التفصيلي';
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
                            ->label('المعلم/ة')
                            ->options(fn () => $this->employeeNames())
                            ->searchable()
                            ->placeholder('كل المعلمين'),

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
                TextColumn::make('employee_id')
                    ->label('المعلم/ة')
                    ->formatStateUsing(fn ($state) => $this->employeeNames()->get($state) ?? $state)
                    ->sortable(),

                TextColumn::make('date')
                    ->label('التاريخ')
                    ->date('Y-m-d')
                    ->sortable(),

                TextColumn::make('start_time')
                    ->label('وقت البداية')
                    ->formatStateUsing(fn ($state) => \Carbon\Carbon::parse($state)->format('H:i'))
                    ->sortable(),

                TextColumn::make('end_time')
                    ->label('وقت النهاية')
                    ->formatStateUsing(fn ($state) => \Carbon\Carbon::parse($state)->format('H:i'))
                    ->sortable(),

                TextColumn::make('status')
                    ->label('الحالة')
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'completed' => 'حاضر ✓',
                        'checked_in' => 'حاضر جزئي',
                        'pending' => 'غايب ✗',
                        default => $state,
                    })
                    ->color(fn ($state) => match ($state) {
                        'completed' => 'success',
                        'checked_in' => 'warning',
                        'pending' => 'danger',
                        default => 'gray',
                    })
                    ->sortable(),

                TextColumn::make('check_in_at')
                    ->label('وقت الدخول')
                    ->formatStateUsing(fn ($state) => $state ? \Carbon\Carbon::parse($state)->format('H:i') : '-')
                    ->sortable(),

                TextColumn::make('check_out_at')
                    ->label('وقت الخروج')
                    ->formatStateUsing(fn ($state) => $state ? \Carbon\Carbon::parse($state)->format('H:i') : '-')
                    ->sortable(),
            ])
            ->defaultSort('date', 'desc')
            ->striped();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('pdf')
                ->label('تحميل PDF')
                ->icon('heroicon-o-document')
                ->action('downloadPdf')
                ->disabled(fn () => !$this->selectedEmployeeId),

            Action::make('addManualRecord')
                ->label('إضافة سجل حضور يدوي')
                ->icon('heroicon-o-plus')
                ->form([
                    Select::make('employee_id')
                        ->label('المعلم/ة')
                        ->options(fn () => $this->employeeNames())
                        ->searchable()
                        ->required(),

                    DatePicker::make('date')
                        ->label('التاريخ')
                        ->default(now())
                        ->required(),

                    TimePicker::make('start_time')
                        ->label('بداية الحصة')
                        ->seconds(false)
                        ->required(),

                    TimePicker::make('end_time')
                        ->label('نهاية الحصة')
                        ->seconds(false)
                        ->required(),

                    Select::make('status')
                        ->label('الحالة')
                        ->options([
                            'completed' => 'حاضر',
                            'checked_in' => 'حاضر جزئي (دخول بلا خروج)',
                            'pending' => 'غايب',
                        ])
                        ->default('completed')
                        ->required()
                        ->reactive(),

                    DateTimePicker::make('check_in_at')
                        ->label('وقت الدخول')
                        ->seconds(false)
                        ->visible(fn ($get) => $get('status') !== 'pending'),

                    DateTimePicker::make('check_out_at')
                        ->label('وقت الخروج')
                        ->seconds(false)
                        ->visible(fn ($get) => $get('status') === 'completed'),
                ])
                ->action(function (array $data): void {
                    $values = [
                        'status' => $data['status'],
                        'check_in_at' => $data['status'] !== 'pending' ? ($data['check_in_at'] ?? null) : null,
                        'check_out_at' => $data['status'] === 'completed' ? ($data['check_out_at'] ?? null) : null,
                    ];

                    $keys = [
                        'employee_id' => $data['employee_id'],
                        'date' => $data['date'],
                        'start_time' => $data['start_time'],
                        'end_time' => $data['end_time'],
                    ];

                    DailyClassAttendance::updateOrCreate($keys, $values);

                    DB::connection('resultsys')->table('daily_class_attendance')->updateOrInsert(
                        $keys,
                        [...$values, 'created_at' => now(), 'updated_at' => now()],
                    );

                    Notification::make()
                        ->title('تمت إضافة السجل')
                        ->success()
                        ->send();

                    $this->resetTable();
                }),
        ];
    }

    public function downloadPdf()
    {
        if (!$this->selectedEmployeeId) {
            return;
        }

        return redirect()->route('reports.attendance-detailed.pdf', [
            'employeeId' => $this->selectedEmployeeId,
            'startDate' => $this->startDate,
            'endDate' => $this->endDate,
        ]);
    }

    protected function employeeNames(): Collection
    {
        return $this->employeeNamesCache ??= Employee::query()
            ->teachers()
            ->pluck('name', 'id');
    }

    public function getFilteredTableQuery(): Builder
    {
        return DailyClassAttendance::query()
            ->when($this->selectedEmployeeId, fn ($q) => $q->where('employee_id', $this->selectedEmployeeId))
            ->when($this->startDate, fn ($q) => $q->whereDate('date', '>=', $this->startDate))
            ->when($this->endDate, fn ($q) => $q->whereDate('date', '<=', $this->endDate))
            ->orderBy('date', 'desc')
            ->orderBy('start_time', 'desc');
    }

    public function getView(): string
    {
        return 'filament.pages.attendance-detailed-report';
    }

    public function getTitle(): string
    {
        return 'تقرير حضور المعلمين بالتفصيل';
    }
}
