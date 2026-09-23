<?php

namespace App\Filament\Pages;

use App\Models\DailyClassAttendance;
use App\Models\Resultsys\Employee;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Pages\Page;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use UnitEnum;

class AttendanceOverallReport extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = \Filament\Support\Icons\Heroicon::OutlinedCalendar;
    protected static ?string $navigationLabel = 'تقرير حضور المعلمين الإجمالي';
    protected static string|UnitEnum|null $navigationGroup = null;

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
                TextColumn::make('employee_id')
                    ->label('اسم المعلم/ة')
                    ->formatStateUsing(fn ($state) => $this->employeeNames()->get($state) ?? '-')
                    ->sortable(),

                TextColumn::make('total_sessions')
                    ->label('إجمالي الحصص')
                    ->numeric()
                    ->sortable()
                    ->summarize([Sum::make()->label('المجموع')]),

                TextColumn::make('attended_sessions')
                    ->label('حصص حاضر')
                    ->numeric()
                    ->sortable()
                    ->formatStateUsing(fn ($state) => "✓ {$state}")
                    ->color('success')
                    ->summarize([Sum::make()->label('المجموع')]),

                TextColumn::make('absent_sessions')
                    ->label('حصص غايب')
                    ->numeric()
                    ->sortable()
                    ->formatStateUsing(fn ($state) => "✗ {$state}")
                    ->color('danger')
                    ->summarize([Sum::make()->label('المجموع')]),

                TextColumn::make('attendance_percentage')
                    ->label('نسبة الحضور %')
                    ->state(function ($record): string {
                        $percentage = $record->total_sessions > 0
                            ? round(($record->attended_sessions / $record->total_sessions) * 100, 1)
                            : 0;

                        return $percentage . '%';
                    })
                    ->color(function ($state) {
                        $percentage = (float) str_replace('%', '', $state);
                        if ($percentage >= 85) return 'success';
                        if ($percentage >= 70) return 'warning';
                        return 'danger';
                    }),
            ])
            ->filters([
                Filter::make('date_range')
                    ->label('نطاق التاريخ')
                    ->form([
                        DatePicker::make('start_date')->label('من التاريخ'),
                        DatePicker::make('end_date')->label('إلى التاريخ'),
                    ])
                    ->query(function ($query, array $data) {
                        $this->startDate = $data['start_date'] ?? null;
                        $this->endDate = $data['end_date'] ?? null;
                        return $query;
                    }),
            ])
            ->defaultSort('employee_id')
            ->striped();
    }

    protected function employeeNames(): Collection
    {
        return $this->employeeNamesCache ??= Employee::query()
            ->whereHas('teacherClasses')
            ->pluck('name', 'id');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('pdf')
                ->label('تحميل PDF')
                ->icon('heroicon-o-document')
                ->action('downloadPdf'),
        ];
    }

    public function downloadPdf()
    {
        return redirect()->route('reports.attendance-overall.pdf', [
            'startDate' => $this->startDate,
            'endDate' => $this->endDate,
        ]);
    }

    public function getFilteredTableQuery(): Builder
    {
        $teacherIds = Employee::query()
            ->whereHas('teacherClasses')
            ->pluck('id');

        // كل الأعمدة تأتي من daily_class_attendance المحلية فقط — أسماء المعلمين
        // تُحل عبر employeeNames() لأنها في اتصال قاعدة بيانات مختلف (resultsys)،
        // ولا يمكن عمل JOIN حقيقي بين قاعدتين منفصلتين.
        return DailyClassAttendance::query()
            ->whereIn('employee_id', $teacherIds)
            ->when($this->startDate, fn ($q) => $q->whereDate('date', '>=', $this->startDate))
            ->when($this->endDate, fn ($q) => $q->whereDate('date', '<=', $this->endDate))
            ->selectRaw(
                'employee_id as id,
                employee_id,
                COUNT(*) as total_sessions,
                SUM(CASE WHEN status = "completed" THEN 1 ELSE 0 END) as attended_sessions,
                SUM(CASE WHEN status = "completed" THEN 0 ELSE 1 END) as absent_sessions'
            )
            ->groupBy('employee_id')
            ->havingRaw('COUNT(*) > 0');
    }

    public function getView(): string
    {
        return 'filament.pages.attendance-overall-report';
    }

    public function getTitle(): string
    {
        return 'تقرير الحضور والغياب الإجمالي للمعلمين';
    }
}
