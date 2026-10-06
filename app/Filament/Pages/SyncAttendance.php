<?php

namespace App\Filament\Pages;

use App\Models\AttendanceSetting;
use App\Models\AttendanceSyncLog;
use App\Services\Attendance\DailyClassAttendanceDurationMatcherService;
use App\Services\Attendance\DailyClassAttendanceMatcherService;
use App\Services\Attendance\DailyClassAttendanceSnapshotService;
use App\Services\Attendance\DailyEmployeeAttendanceService;
use App\Services\Attendance\DailyStudentAttendanceService;
use App\Services\Attendance\DailyTeacherReceptionAttendanceService;
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
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Log;
use Throwable;
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
        DailyTeacherReceptionAttendanceService $teacherReceptionService,
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
        $syncedDates = [];
        $skippedDates = [];
        $failedDates = [];

        foreach (CarbonPeriod::create($from, $to) as $date) {
            $dateString = $date->toDateString();

            if (in_array($dateString, $alreadySynced, true)) {
                $skippedDates[] = $dateString;
                continue;
            }

            try {
                $this->syncDate($dateString, $snapshotService, $matcherService, $durationMatcherService, $employeeService, $studentService, $teacherReceptionService);
                $syncedDates[] = $dateString;
            } catch (Throwable $e) {
                // يوم وحيد فيه مشكلة ماخاصوش يوقف باقي النطاق — نسجل ونكمل.
                Log::error('فشلت مزامنة يوم كامل', [
                    'date' => $dateString,
                    'error' => $e->getMessage(),
                ]);
                $failedDates[] = $dateString;
            }
        }

        $this->resetTable();

        $body = sprintf('تمت مزامنة %d يوم.', count($syncedDates));
        if (!empty($skippedDates)) {
            $body .= sprintf(' تم تخطي %d يوم (مزامنين من قبل): %s', count($skippedDates), implode('، ', $skippedDates));
        }
        if (!empty($failedDates)) {
            $body .= sprintf(' فشلت مزامنة %d يوم (شوف اللوق): %s', count($failedDates), implode('، ', $failedDates));
        }

        $notification = Notification::make()
            ->title('انتهت المزامنة')
            ->body($body);

        empty($failedDates) ? $notification->success() : $notification->warning();

        $notification->send();
    }

    /**
     * يزامن يوم وحيد فعليا (يشغل كل الخدمات) ويسجل/يحدّث صف AttendanceSyncLog
     * المطابق. مستعملة من syncRange() وزر "إعادة مزامنة" في الجدول.
     */
    protected function syncDate(
        string $dateString,
        DailyClassAttendanceSnapshotService $snapshotService,
        DailyClassAttendanceMatcherService $matcherService,
        DailyClassAttendanceDurationMatcherService $durationMatcherService,
        DailyEmployeeAttendanceService $employeeService,
        DailyStudentAttendanceService $studentService,
        DailyTeacherReceptionAttendanceService $teacherReceptionService,
    ): void {
        $mode = AttendanceSetting::current();

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
        $teacherReceptionResult = $teacherReceptionService->processForDate($dateString);

        AttendanceSyncLog::updateOrCreate(
            ['date' => $dateString],
            [
                'mode' => $mode->teacher_matching_mode,
                'employees_recorded' => $employeeResult['employees_recorded'],
                'teachers_reception_recorded' => $teacherReceptionResult['teachers_recorded'],
                'students_recorded' => $studentResult['students_recorded'],
                'class_summary' => $classSummary,
                'processed_logs' => $classProcessedLogs + $employeeResult['processed_logs'] + $studentResult['processed_logs'] + $teacherReceptionResult['processed_logs'],
                'synced_at' => now(),
            ],
        );
    }

    public function resyncDate(
        AttendanceSyncLog $record,
        DailyClassAttendanceSnapshotService $snapshotService,
        DailyClassAttendanceMatcherService $matcherService,
        DailyClassAttendanceDurationMatcherService $durationMatcherService,
        DailyEmployeeAttendanceService $employeeService,
        DailyStudentAttendanceService $studentService,
        DailyTeacherReceptionAttendanceService $teacherReceptionService,
    ): void {
        $dateString = $record->date->toDateString();

        try {
            $this->syncDate($dateString, $snapshotService, $matcherService, $durationMatcherService, $employeeService, $studentService, $teacherReceptionService);

            Notification::make()
                ->title('تمت إعادة المزامنة')
                ->body('اليوم '.$dateString.' اتزامن من جديد بنجاح.')
                ->success()
                ->send();
        } catch (Throwable $e) {
            Log::error('فشلت إعادة مزامنة يوم', [
                'date' => $dateString,
                'error' => $e->getMessage(),
            ]);

            Notification::make()
                ->title('فشلت إعادة المزامنة')
                ->body('شوف اللوق لتفاصيل الخطأ.')
                ->danger()
                ->send();
        }
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

                TextColumn::make('teachers_reception_recorded')
                    ->label('معلمين (استقبال)'),

                TextColumn::make('students_recorded')
                    ->label('طلبة مسجلين'),

                TextColumn::make('synced_at')
                    ->label('وقت المزامنة')
                    ->dateTime('Y-m-d H:i'),
            ])
            ->defaultSort('date', 'desc')
            ->actions([
                Action::make('resync')
                    ->label('إعادة مزامنة')
                    ->icon('heroicon-o-arrow-path')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalDescription('هذا يعيد تشغيل المزامنة فعليا لهذا اليوم (يقرا attlog من جديد ويحدث النتائج). استعملها بس لو متأكد.')
                    ->action('resyncDate'),
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
