<?php

namespace App\Filament\Pages;

use App\Models\DailyStudentAttendance;
use App\Models\Resultsys\Student;
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

class StudentAttendanceReport extends Page implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = \Filament\Support\Icons\Heroicon::OutlinedAcademicCap;
    protected static ?string $navigationLabel = 'تقرير حضور الطلبة';
    protected static string|UnitEnum|null $navigationGroup = null;

    public ?array $data = [];

    public ?string $selectedStudentId = null;
    public ?string $startDate = null;
    public ?string $endDate = null;

    protected ?Collection $studentNamesCache = null;

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
                        Select::make('student_id')
                            ->label('الطالب')
                            ->options(fn () => $this->studentNames())
                            ->searchable()
                            ->placeholder('كل الطلبة'),

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

        $this->selectedStudentId = $state['student_id'] ?? null;
        $this->startDate = $state['start_date'] ?? null;
        $this->endDate = $state['end_date'] ?? null;

        $this->resetTable();
    }

    public function resetSearch(): void
    {
        $this->form->fill();
        $this->selectedStudentId = null;
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

                TextColumn::make('student_id')
                    ->label('الطالب')
                    ->formatStateUsing(fn ($state) => $this->studentNames()->get($state) ?? '-'),

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
                    Select::make('student_id')
                        ->label('الطالب')
                        ->options(fn () => $this->studentNames())
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
                    DailyStudentAttendance::updateOrCreate(
                        ['student_id' => $data['student_id'], 'date' => $data['date']],
                        [
                            'first_check_in' => $data['first_check_in'],
                            'last_check_out' => $data['last_check_out'] ?? null,
                            'status' => 'present',
                        ],
                    );

                    DB::connection('resultsys')->table('daily_student_attendance')->updateOrInsert(
                        ['student_id' => $data['student_id'], 'date' => $data['date']],
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

    protected function studentNames(): Collection
    {
        return $this->studentNamesCache ??= Student::query()
            ->orderBy('full_name')
            ->pluck('full_name', 'id');
    }

    public function getFilteredTableQuery(): Builder
    {
        return DailyStudentAttendance::query()
            ->when($this->selectedStudentId, fn ($q) => $q->where('student_id', $this->selectedStudentId))
            ->when($this->startDate, fn ($q) => $q->whereDate('date', '>=', $this->startDate))
            ->when($this->endDate, fn ($q) => $q->whereDate('date', '<=', $this->endDate))
            ->orderBy('date', 'desc');
    }

    public function getView(): string
    {
        return 'filament.pages.student-attendance-report';
    }

    public function getTitle(): string
    {
        return 'تقرير حضور الطلبة';
    }
}
