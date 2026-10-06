<?php

namespace App\Filament\Pages;

use App\Models\AttendanceSetting;
use App\Models\AttendanceSyncLog;
use App\Services\Attendance\DailyClassAttendanceDurationMatcherService;
use App\Services\Attendance\DailyClassAttendanceMatcherService;
use App\Services\Attendance\DailyClassAttendanceSnapshotService;
use App\Services\Attendance\DailyEmployeeAttendanceService;
use App\Services\Attendance\DailyStudentAttendanceService;
use BackedEnum;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Tables\Actions\Action as TableAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use UnitEnum;

class SyncAttendance extends Page implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = \Filament\Support\Icons\Heroicon::OutlinedArrowPath;
    protected static ?string $navigationLabel = 'مزامنة الحضور';
    protected static string|UnitEnum|null $navigationGroup = null;
    protected static ?int $navigationSort = -90;

    public ?array $data = [];

    public bool $isDurationMode = false;

    public function mount(): void
    {
        $this->isDurationMode = AttendanceSetting::current()->isDurationMode();
        $this->form->fill([
            'from_date' => now()->toDateString(),
            'to_date' => now()->toDateString(),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                DatePicker::make('from_date')
                    ->label('من تاريخ')
                    ->required(),

                DatePicker::make('to_date')
                    ->label('إلى تاريخ')
                    ->required(),
            ])
            ->statePath('data');
    }

    public function toggleMode(): void
    {
        $setting = AttendanceSetting::current();
        $setting->toggle();
        $this->isDurationMode = $setting->isDurationMode();

        Notification::make()
            ->title('تم تبديل نمط مطابقة حصص المعلمين')
            ->body($this->isDurationMode
                ? 'النمط الحالي: الجلسات الزمنية (بلا جدول دراسي)'
                : 'النمط الحالي: الجدول الدراسي (snapshot)')
            ->success()
            ->send();
    }

    public function syncRange(
        DailyClassAttendanceSnapshotService $snapshotService,
        DailyClassAttendanceMatcherService $matcherService,
        DailyClassAttendanceDurationMatcherService $durationMatcherService,
        DailyEmployeeAttendanceService $employeeService,
        DailyStudentAttendanceService $studentService,
    ): void {
        $state = $this->form->getState();
        $from = CarbonImmutable::parse($state['from_date']);
        $to = CarbonImmutable::parse($state['to_date']);

        if ($from->gt($to)) {
            Notification::make()
                ->title('خطأ')
                ->body('تاريخ "من" لازم يكون قبل أو يساوي تاريخ "إلى"')
                ->danger()
                ->send();

            return;
        }

        $alreadySynced = AttendanceSyncLog::alreadySyncedDates($from->toDateString(), $to->toDateString());
        $mode = AttendanceSetting::current();
        $syncedDates = [];
        $skippedDates = [];

        foreach (CarbonPeriod::create($from, $to) as $date) {
            $dateString = $date->toDateString();

            if (in_array($dateString, $alreadySynced, true)) {
                $skippedDates[] = $dateString;
                continue;
            }

            if ($mode->isDurationMode()) {
                $classResult = $durationMatcherService->processForDate($dateString);
                $classSummary = sprintf(
                    'حصص متطابقة (جلسات): %d، بلا تطابق: %d',
                    $classResult['matched_sessions'],
                    $classResult['unmatched_logs'],
                );
                $classProcessedLogs = $classResult['processed_logs'];
            } else {
                $snapshotService->generateForDate($dateString);
                $classResult = $matcherService->processForDate($dateString);
                $classSummary = sprintf(
                    'دخول: %d، خروج: %d (جدول)',
                    $classResult['matched_check_ins'],
                    $classResult['matched_check_outs'],
                );
                $classProcessedLogs = $classResult['processed_logs'];
            }

            $employeeResult = $employeeService->processForDate($dateString);
            $studentResult = $studentService->processForDate($dateString);

            AttendanceSyncLog::create([
                'date' => $dateString,
                'mode' => $mode->teacher_matching_mode,
                'employees_recorded' => $employeeResult['employees_recorded'],
                'students_recorded' => $studentResult['students_recorded'],
                'class_summary' => $classSummary,
                'processed_logs' => $classProcessedLogs + $employeeResult['processed_logs'] + $studentResult['processed_logs'],
                'synced_at' => now(),
            ]);

            $syncedDates[] = $dateString;
        }

        $this->resetTable();

        $body = sprintf('تمت مزامنة %d يوم.', count($syncedDates));
        if (!empty($skippedDates)) {
            $body .= sprintf(' تم تخطي %d يوم (مزامنين من قبل): %s', count($skippedDates), implode('، ', $skippedDates));
        }

        Notification::make()
            ->title('انتهت المزامنة')
            ->body($body)
            ->success()
            ->send();
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(AttendanceSyncLog::query())
            ->columns([
                TextColumn::make('date')
                    ->label('التاريخ')
                    ->date('Y-m-d')
                    ->sortable(),

                TextColumn::make('mode')
                    ->label('النمط')
                    ->formatStateUsing(fn (string $state) => $state === 'duration' ? 'الجلسات الزمنية' : 'الجدول الدراسي')
                    ->badge(),

                TextColumn::make('class_summary')
                    ->label('حصص المعلمين'),

                TextColumn::make('employees_recorded')
                    ->label('موظفين مسجلين'),

                TextColumn::make('students_recorded')
                    ->label('طلبة مسجلين'),

                TextColumn::make('synced_at')
                    ->label('وقت المزامنة')
                    ->dateTime('Y-m-d H:i'),
            ])
            ->defaultSort('date', 'desc')
            ->actions([
                TableAction::make('resync')
                    ->label('إعادة مزامنة')
                    ->icon('heroicon-o-arrow-path')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalDescription('هذا يحذف سجل المزامنة الحالي لهذا اليوم ويسمح بإعادة مزامنته من جديد. استعملها بس لو متأكد.')
                    ->action(function (AttendanceSyncLog $record): void {
                        $record->delete();

                        Notification::make()
                            ->title('تم حذف سجل المزامنة')
                            ->body('اليوم ' . $record->date->toDateString() . ' توا يقدر يتزامن من جديد.')
                            ->success()
                            ->send();
                    }),
            ])
            ->striped();
    }

    public function getView(): string
    {
        return 'filament.pages.sync-attendance';
    }

    public function getTitle(): string
    {
        return 'مزامنة الحضور';
    }
}
