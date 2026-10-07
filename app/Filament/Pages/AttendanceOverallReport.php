<?php

namespace App\Filament\Pages;

use App\Models\DailyClassAttendance;
use App\Models\Resultsys\Employee;
use App\Models\Resultsys\SchoolSchedule;
use BackedEnum;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Pages\Page;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\Summarizers\Summarizer;
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
    public ?string $selectedEmployeeId = null;

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

                TextColumn::make('total_schedule_pool')
                    ->label('إجمالي وعاء الحصص (الجدول الدراسي)')
                    ->state(fn ($record) => $this->totalScheduledSessions($record->employee_id))
                    ->numeric(locale: 'en')
                    ->color('gray')
                    ->summarize([
                        Summarizer::make()
                            ->label('المجموع')
                            ->using(fn ($query) => $query->get()->sum(fn ($row) => $this->totalScheduledSessions($row->employee_id)))
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
                    ->state(fn ($record) => max(0, $this->totalScheduledSessions($record->employee_id) - $record->total_sessions))
                    ->numeric(locale: 'en')
                    ->sortable()
                    ->formatStateUsing(fn ($state) => "✗ {$state}")
                    ->color('danger')
                    ->summarize([
                        Summarizer::make()
                            ->label('المجموع')
                            ->using(fn ($query) => $query->get()->sum(fn ($row) => max(0, $this->totalScheduledSessions($row->employee_id) - $row->total_sessions)))
                            ->numeric(locale: 'en'),
                    ]),

                TextColumn::make('attendance_percentage')
                    ->label('نسبة الحضور %')
                    ->state(function ($record): string {
                        $pool = $this->totalScheduledSessions($record->employee_id);
                        $percentage = $pool > 0
                            ? round(($record->total_sessions / $pool) * 100, 1)
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
                Filter::make('employee_filter')
                    ->label('اختر المعلم/ة')
                    ->form([
                        Select::make('employee_id')
                            ->label('المعلم/ة')
                            ->options(fn () => $this->employeeNames())
                            ->searchable(),
                    ])
                    ->query(function ($query, array $data) {
                        // استعمل القيمة السابقة لو هذا الفلتر بالذات ماشي هو
                        // اللي اتفعّل توا (Filament يعاود ينفذ كل الفلاتر مع
                        // كل تحديث، حتى اللي ماتلمستهاش، و$data يطلع فاضي
                        // لهم) — ?? null هنا كان يصفّر القيمة غلط فأي تفاعل.
                        $this->selectedEmployeeId = array_key_exists('employee_id', $data) ? $data['employee_id'] : $this->selectedEmployeeId;
                        return $query->when($this->selectedEmployeeId, fn ($q) => $q->where('employee_id', $this->selectedEmployeeId));
                    }),

                Filter::make('date_range')
                    ->label('نطاق التاريخ')
                    ->form([
                        DatePicker::make('start_date')->label('من التاريخ')->default(fn () => $this->startDate),
                        DatePicker::make('end_date')->label('إلى التاريخ')->default(fn () => $this->endDate),
                    ])
                    ->query(function ($query, array $data) {
                        $this->startDate = array_key_exists('start_date', $data) ? $data['start_date'] : $this->startDate;
                        $this->endDate = array_key_exists('end_date', $data) ? $data['end_date'] : $this->endDate;
                        return $query
                            ->when($this->startDate, fn ($q, $date) => $q->whereDate('date', '>=', $date))
                            ->when($this->endDate, fn ($q, $date) => $q->whereDate('date', '<=', $date));
                    }),
            ])
            ->defaultSort('employee_id')
            ->defaultKeySort(false)
            ->modelLabel('حصة')
            ->pluralModelLabel('الحصص')
            ->striped();
    }

    /**
     * إجمالي الحصص المجدولة فعليا للمعلم (من الجدول الدراسي school_schedules)
     * خلال نطاق التاريخ المختار — يحسب تكرار كل يوم أسبوع داخل النطاق
     * ويضربه في عدد الحصص المجدولة لهذا اليوم، للمقارنة الحقيقية مع
     * إجمالي الحصص اللي فعلا احتُسبت (total_sessions).
     */
    /** @var array<string, int> */
    protected array $scheduledSessionsCache = [];

    protected function totalScheduledSessions(string $teacherId): int
    {
        return $this->scheduledSessionsCache[$teacherId] ??= $this->computeTotalScheduledSessions($teacherId);
    }

    protected function computeTotalScheduledSessions(string $teacherId): int
    {
        $weeklySchedule = SchoolSchedule::query()
            ->whereHas('teacherClass', fn ($q) => $q->where('teacher_id', $teacherId))
            ->with('day')
            ->get()
            ->filter(fn (SchoolSchedule $schedule) => $schedule->day !== null)
            ->groupBy(fn (SchoolSchedule $schedule) => $schedule->day->day_order);

        if ($weeklySchedule->isEmpty() || !$this->startDate || !$this->endDate) {
            return 0;
        }

        $dayOccurrences = [];
        foreach (CarbonPeriod::create($this->startDate, $this->endDate) as $date) {
            $dayOrder = $this->schoolDayOrder(CarbonImmutable::instance($date));
            $dayOccurrences[$dayOrder] = ($dayOccurrences[$dayOrder] ?? 0) + 1;
        }

        $total = 0;
        foreach ($weeklySchedule as $dayOrder => $schedulesForDay) {
            $total += $schedulesForDay->count() * ($dayOccurrences[$dayOrder] ?? 0);
        }

        return $total;
    }

    protected function schoolDayOrder(CarbonImmutable $date): int
    {
        return match ((int) $date->format('N')) {
            6 => 1,
            7 => 2,
            1 => 3,
            2 => 4,
            3 => 5,
            4 => 6,
            5 => 7,
        };
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
            'startDate' => $this->startDate,
            'endDate' => $this->endDate,
        ]);
    }

    public function getFilteredTableQuery(): Builder
    {
        $teacherIds = Employee::query()
            ->teachers()
            ->pluck('id');

        // كل الأعمدة تأتي من daily_class_attendance المحلية فقط — أسماء المعلمين
        // تُحل عبر employeeNames() لأنها في اتصال قاعدة بيانات مختلف (resultsys)،
        // ولا يمكن عمل JOIN حقيقي بين قاعدتين منفصلتين.
        // الفلترة الفعلية (المعلم + نطاق التاريخ) تتعمل من جوا ->query() متاع
        // كل Filter نفسه (يشوف أحدث $data مباشرة)، مش هنا — تكرارها هنا يخلق
        // فلترة مزدوجة بقيمة قديمة لـ $this->startDate/selectedEmployeeId.
        // attended/absent/النسبة كلهم تُحسب توا من وعاء الجدول الدراسي
        // (totalScheduledSessions) مقابل total_sessions، مش من عمود status —
        // خلاها الكويري تجيب بس العدد الخام اللي فعلا احتُسب.
        return DailyClassAttendance::query()
            ->whereIn('employee_id', $teacherIds)
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
