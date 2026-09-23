<?php

namespace App\Filament\Pages;

use App\Models\DailyStudentAttendance;
use App\Models\Resultsys\Student;
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

class StudentAttendanceReport extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = \Filament\Support\Icons\Heroicon::OutlinedAcademicCap;
    protected static ?string $navigationLabel = 'تقرير حضور الطلبة';
    protected static string|UnitEnum|null $navigationGroup = null;

    public ?int $selectedStudentId = null;
    public ?string $startDate = null;
    public ?string $endDate = null;

    protected ?Collection $studentNamesCache = null;

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
            ->filters([
                Filter::make('student_filter')
                    ->label('اختر الطالب')
                    ->form([
                        Select::make('student_id')
                            ->label('الطالب')
                            ->options(fn () => $this->studentNames())
                            ->searchable(),
                    ])
                    ->query(function ($query, array $data) {
                        $this->selectedStudentId = $data['student_id'] ?? null;
                        return $query->when($this->selectedStudentId, fn ($q) => $q->where('student_id', $this->selectedStudentId));
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

    protected function studentNames(): Collection
    {
        return $this->studentNamesCache ??= Student::query()
            ->orderBy('full_name')
            ->pluck('full_name', 'id');
    }

    public function getFilteredTableQuery(): Builder
    {
        return DailyStudentAttendance::query()
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
