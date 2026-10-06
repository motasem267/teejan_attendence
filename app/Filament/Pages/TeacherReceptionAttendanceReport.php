<?php

namespace App\Filament\Pages;

use App\Models\DailyTeacherReceptionAttendance;
use App\Models\Resultsys\Employee;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use UnitEnum;

/**
 * حضور المعلمين من جهاز الاستقبال (Reception) — نفس شكل تقرير حضور
 * الموظفين بالضبط، بس مصدر البيانات DailyTeacherReceptionAttendance
 * وقائمة الأسماء Employee::teachers().
 */
class TeacherReceptionAttendanceReport extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = \Filament\Support\Icons\Heroicon::OutlinedClock;
    protected static ?string $navigationLabel = 'تقرير حضور المعلمين (الاستقبال)';
    protected static string|UnitEnum|null $navigationGroup = null;

    public ?string $selectedEmployeeId = null;
    public ?string $startDate = null;
    public ?string $endDate = null;

    protected ?Collection $teacherNamesCache = null;

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
                    ->label('المعلم/ة')
                    ->formatStateUsing(fn ($state) => $this->teacherNames()->get($state) ?? '-'),

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
                    ->label('اختر المعلم/ة')
                    ->form([
                        Select::make('employee_id')
                            ->label('المعلم/ة')
                            ->options(fn () => $this->teacherNames())
                            ->searchable(),
                    ])
                    ->query(function ($query, array $data) {
                        $this->selectedEmployeeId = $data['employee_id'] ?? null;
                        return $query->when($this->selectedEmployeeId, fn ($q) => $q->where('employee_id', $this->selectedEmployeeId));
                    }),

                Filter::make('date_range')
                    ->label('نطاق التاريخ')
                    ->form([
                        DatePicker::make('start_date')->label('من التاريخ')->default(fn () => $this->startDate),
                        DatePicker::make('end_date')->label('إلى التاريخ')->default(fn () => $this->endDate),
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

    protected function getHeaderActions(): array
    {
        return [
            Action::make('addManualRecord')
                ->label('إضافة سجل حضور يدوي')
                ->icon('heroicon-o-plus')
                ->form([
                    Select::make('employee_id')
                        ->label('المعلم/ة')
                        ->options(fn () => $this->teacherNames())
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
                    DailyTeacherReceptionAttendance::updateOrCreate(
                        ['employee_id' => $data['employee_id'], 'date' => $data['date']],
                        [
                            'first_check_in' => $data['first_check_in'],
                            'last_check_out' => $data['last_check_out'] ?? null,
                            'status' => 'present',
                        ],
                    );

                    DB::connection('resultsys')->table('daily_teacher_reception_attendance')->updateOrInsert(
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
                }),
        ];
    }

    protected function teacherNames(): Collection
    {
        return $this->teacherNamesCache ??= Employee::query()
            ->teachers()
            ->orderBy('name')
            ->pluck('name', 'id');
    }

    public function getFilteredTableQuery(): Builder
    {
        // الفلترة (المعلم + نطاق التاريخ) تتعمل من جوا ->query() متاع كل Filter
        // نفسه — تكرارها هنا يخلق فلترة مزدوجة بقيمة قديمة لـ $this->startDate.
        return DailyTeacherReceptionAttendance::query()->orderBy('date', 'desc');
    }

    public function getView(): string
    {
        return 'filament.pages.teacher-reception-attendance-report';
    }

    public function getTitle(): string
    {
        return 'تقرير حضور المعلمين من جهاز الاستقبال';
    }
}
