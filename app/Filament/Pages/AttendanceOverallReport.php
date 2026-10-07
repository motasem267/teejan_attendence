<?php

namespace App\Filament\Pages;

use App\Models\DailyClassAttendance;
use App\Models\Resultsys\Employee;
use App\Services\Attendance\TeacherScheduledSessionsService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\Summarizers\Summarizer;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use UnitEnum;

class AttendanceOverallReport extends Page implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = \Filament\Support\Icons\Heroicon::OutlinedCalendar;
    protected static ?string $navigationLabel = 'تقرير حضور المعلمين الإجمالي';
    protected static string|UnitEnum|null $navigationGroup = null;

    public ?array $data = [];

    public ?string $startDate = null;
    public ?string $endDate = null;
    public ?string $selectedEmployeeId = null;

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
        // محلول مرة وحدة هنا ومستعمل عبر كل الـ closures تحت — عنده cache
        // داخلي خاص بيه لمدة بناء الجدول هذا بس (مش محفوظ عبر الطلبات).
        $scheduledSessions = app(TeacherScheduledSessionsService::class);
        $pool = fn (string $teacherId): int => $scheduledSessions->calculate($teacherId, $this->startDate, $this->endDate);

        return $table
            ->query($this->getFilteredTableQuery())
            ->columns([
                TextColumn::make('employee_id')
                    ->label('اسم المعلم/ة')
                    ->formatStateUsing(fn ($state) => $this->employeeNames()->get($state) ?? '-')
                    ->sortable(),

                TextColumn::make('total_schedule_pool')
                    ->label('إجمالي وعاء الحصص (الجدول الدراسي)')
                    ->state(fn ($record) => $pool($record->employee_id))
                    ->numeric(locale: 'en')
                    ->color('gray')
                    ->summarize([
                        Summarizer::make()
                            ->label('المجموع')
                            ->using(fn ($query) => $query->get()->sum(fn ($row) => $pool($row->employee_id)))
                            ->numeric(locale: 'en'),
                    ]),

                TextColumn::make('total_sessions')
                    ->label('إجمالي الحصص')
                    ->numeric(locale: 'en')
                    ->sortable()
                    ->summarize([Sum::make()->label('المجموع')->numeric(locale: 'en')]),

                TextColumn::make('attended_sessions')
                    ->label('حصص الحضور')
                    ->state(fn ($record) => $record->total_sessions)
                    ->numeric(locale: 'en')
                    ->sortable()
                    ->formatStateUsing(fn ($state) => "✓ {$state}")
                    ->color('success')
                    ->summarize([
                        Summarizer::make()
                            ->label('المجموع')
                            ->using(fn ($query) => $query->get()->sum('total_sessions'))
                            ->numeric(locale: 'en'),
                    ]),

                TextColumn::make('absent_sessions')
                    ->label('حصص الغياب')
                    ->state(fn ($record) => max(0, $pool($record->employee_id) - $record->total_sessions))
                    ->numeric(locale: 'en')
                    ->sortable()
                    ->formatStateUsing(fn ($state) => "✗ {$state}")
                    ->color('danger')
                    ->summarize([
                        Summarizer::make()
                            ->label('المجموع')
                            ->using(fn ($query) => $query->get()->sum(fn ($row) => max(0, $pool($row->employee_id) - $row->total_sessions)))
                            ->numeric(locale: 'en'),
                    ]),

                TextColumn::make('attendance_percentage')
                    ->label('نسبة الحضور %')
                    ->state(function ($record) use ($pool): string {
                        $teacherPool = $pool($record->employee_id);
                        $percentage = $teacherPool > 0
                            ? round(($record->total_sessions / $teacherPool) * 100, 1)
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
            ->defaultSort('employee_id')
            ->defaultKeySort(false)
            ->modelLabel('حصة')
            ->pluralModelLabel('الحصص')
            ->striped();
    }

    protected function employeeNames(): Collection
    {
        return $this->employeeNamesCache ??= Employee::query()
            ->teachers()
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
            'employeeId' => $this->selectedEmployeeId,
            'startDate' => $this->startDate,
            'endDate' => $this->endDate,
        ]);
    }

    public function getFilteredTableQuery(): Builder
    {
        $teacherIds = Employee::query()
            ->teachers()
            ->when($this->selectedEmployeeId, fn ($q) => $q->where('id', $this->selectedEmployeeId))
            ->pluck('id');

        // كل الأعمدة تأتي من daily_class_attendance المحلية فقط — أسماء المعلمين
        // تُحل عبر employeeNames() لأنها في اتصال قاعدة بيانات مختلف (resultsys)،
        // ولا يمكن عمل JOIN حقيقي بين قاعدتين منفصلتين.
        // attended/absent/النسبة كلهم تُحسب من وعاء الجدول الدراسي
        // (TeacherScheduledSessionsService) مقابل total_sessions، مش من عمود
        // status — خلاها الكويري تجيب بس العدد الخام اللي فعلا احتُسب.
        return DailyClassAttendance::query()
            ->whereIn('employee_id', $teacherIds)
            ->when($this->startDate, fn ($q) => $q->whereDate('date', '>=', $this->startDate))
            ->when($this->endDate, fn ($q) => $q->whereDate('date', '<=', $this->endDate))
            ->selectRaw(
                'employee_id as id,
                employee_id,
                COUNT(*) as total_sessions'
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
