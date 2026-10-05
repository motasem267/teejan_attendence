<?php

namespace App\Filament\Pages;

use App\Models\DailyClassAttendance;
use App\Models\Resultsys\Employee;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Collection;
use UnitEnum;

class TeacherSessionsDrilldown extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = \Filament\Support\Icons\Heroicon::OutlinedClipboardDocumentCheck;
    protected static string|UnitEnum|null $navigationGroup = null;
    protected static bool $shouldRegisterNavigation = false;

    public string $status = 'completed';
    public string $date;

    protected ?Collection $employeeNamesCache = null;

    public function mount(): void
    {
        $this->status = request()->query('status', 'completed');
        $this->date = request()->query('date', now()->toDateString());
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                DailyClassAttendance::query()
                    ->whereDate('date', $this->date)
                    ->where('status', $this->status)
            )
            ->columns([
                TextColumn::make('employee_id')
                    ->label('المعلم')
                    ->formatStateUsing(fn ($state) => $this->employeeNames()->get($state) ?? $state),

                TextColumn::make('start_time')
                    ->label('بداية الحصة'),

                TextColumn::make('end_time')
                    ->label('نهاية الحصة'),

                TextColumn::make('check_in_at')
                    ->label('وقت الحضور')
                    ->dateTime('H:i')
                    ->placeholder('-'),

                TextColumn::make('check_out_at')
                    ->label('وقت الانصراف')
                    ->dateTime('H:i')
                    ->placeholder('-'),
            ])
            ->defaultSort('start_time')
            ->striped();
    }

    protected function employeeNames(): Collection
    {
        return $this->employeeNamesCache ??= Employee::query()->pluck('name', 'id');
    }

    public function getView(): string
    {
        return 'filament.pages.teacher-sessions-drilldown';
    }

    public function getTitle(): string
    {
        $labels = [
            'completed' => 'الحصص المكتملة',
            'pending' => 'الحصص التي لم يحضر لها المعلم',
            'checked_in' => 'حصص دخول بلا خروج',
        ];

        return ($labels[$this->status] ?? 'حصص المعلمين').' - '.$this->date;
    }
}
