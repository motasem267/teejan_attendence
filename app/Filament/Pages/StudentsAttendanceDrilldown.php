<?php

namespace App\Filament\Pages;

use App\Models\DailyStudentAttendance;
use App\Models\Resultsys\Student;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use UnitEnum;

class StudentsAttendanceDrilldown extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = \Filament\Support\Icons\Heroicon::OutlinedAcademicCap;
    protected static string|UnitEnum|null $navigationGroup = null;
    protected static bool $shouldRegisterNavigation = false;

    public string $status = 'present';
    public string $date;

    /** @var array<int, array{first_check_in: ?string, last_check_out: ?string}> */
    public array $timesMap = [];

    /** @var array<int, int> */
    public array $presentIds = [];

    public function mount(): void
    {
        $this->status = request()->query('status', 'present');
        $this->date = request()->query('date', now()->toDateString());

        $attendance = DailyStudentAttendance::query()
            ->whereDate('date', $this->date)
            ->get()
            ->keyBy('student_id');

        $this->presentIds = $attendance->keys()->all();
        $this->timesMap = $attendance->map(fn ($record) => [
            'first_check_in' => optional($record->first_check_in)->format('H:i'),
            'last_check_out' => optional($record->last_check_out)->format('H:i'),
        ])->toArray();
    }

    public function table(Table $table): Table
    {
        $query = $this->status === 'present'
            ? Student::query()->whereIn('id', $this->presentIds)
            : Student::query()->whereNotIn('id', $this->presentIds);

        return $table
            ->query($query)
            ->columns([
                TextColumn::make('full_name')
                    ->label('الاسم')
                    ->searchable(),

                TextColumn::make('national_id')
                    ->label('الرقم الوطني')
                    ->placeholder('-'),

                TextColumn::make('first_check_in_display')
                    ->label('أول دخول')
                    ->state(fn ($record) => $this->timesMap[$record->id]['first_check_in'] ?? '-')
                    ->visible($this->status === 'present'),

                TextColumn::make('last_check_out_display')
                    ->label('آخر خروج')
                    ->state(fn ($record) => $this->timesMap[$record->id]['last_check_out'] ?? '-')
                    ->visible($this->status === 'present'),
            ])
            ->defaultSort('full_name')
            ->striped();
    }

    public function getView(): string
    {
        return 'filament.pages.students-attendance-drilldown';
    }

    public function getTitle(): string
    {
        return ($this->status === 'present' ? 'الطلبة الحاضرون' : 'الطلبة غير الحاضرين').' - '.$this->date;
    }
}
