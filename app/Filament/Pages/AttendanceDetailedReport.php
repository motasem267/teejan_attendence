<?php

namespace App\Filament\Pages;

use App\Models\DailyClassAttendance;
use App\Models\Resultsys\Employee;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class AttendanceDetailedReport extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = \Filament\Support\Icons\Heroicon::OutlinedDocumentText;
    protected static ?string $navigationLabel = 'تقرير حضور المعلمين التفصيلي';
    protected static string|UnitEnum|null $navigationGroup = null;

    public ?int $selectedEmployeeId = null;
    public ?string $startDate = null;
    public ?string $endDate = null;

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

                TextColumn::make('start_time')
                    ->label('وقت البداية')
                    ->formatStateUsing(fn ($state) => \Carbon\Carbon::parse($state)->format('H:i'))
                    ->sortable(),

                TextColumn::make('end_time')
                    ->label('وقت النهاية')
                    ->formatStateUsing(fn ($state) => \Carbon\Carbon::parse($state)->format('H:i'))
                    ->sortable(),

                TextColumn::make('status')
                    ->label('الحالة')
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'completed' => 'حاضر ✓',
                        'checked_in' => 'حاضر جزئي',
                        'pending' => 'غايب ✗',
                        default => $state,
                    })
                    ->color(fn ($state) => match ($state) {
                        'completed' => 'success',
                        'checked_in' => 'warning',
                        'pending' => 'danger',
                        default => 'gray',
                    })
                    ->sortable(),

                TextColumn::make('check_in_at')
                    ->label('وقت الدخول')
                    ->formatStateUsing(fn ($state) => $state ? \Carbon\Carbon::parse($state)->format('H:i') : '-')
                    ->sortable(),

                TextColumn::make('check_out_at')
                    ->label('وقت الخروج')
                    ->formatStateUsing(fn ($state) => $state ? \Carbon\Carbon::parse($state)->format('H:i') : '-')
                    ->sortable(),
            ])
            ->filters([
                Filter::make('employee_filter')
                    ->label('اختر المعلم/ة')
                    ->form([
                        Select::make('employee_id')
                            ->label('المعلم/ة')
                            ->options(fn () => Employee::query()
                                ->whereHas('teacherClasses')
                                ->orderBy('name')
                                ->pluck('name', 'id'))
                            ->searchable(),
                    ])
                    ->query(function ($query, array $data) {
                        $this->selectedEmployeeId = $data['employee_id'] ?? null;
                        return $query;
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

    protected function getHeaderActions(): array
    {
        return [
            Action::make('pdf')
                ->label('تحميل PDF')
                ->icon('heroicon-o-document')
                ->action('downloadPdf')
                ->disabled(fn () => !$this->selectedEmployeeId),
        ];
    }

    public function downloadPdf()
    {
        if (!$this->selectedEmployeeId) {
            return;
        }

        return redirect()->route('reports.attendance-detailed.pdf', [
            'employeeId' => $this->selectedEmployeeId,
            'startDate' => $this->startDate,
            'endDate' => $this->endDate,
        ]);
    }

    public function getFilteredTableQuery(): Builder
    {
        $query = DailyClassAttendance::query();

        if ($this->selectedEmployeeId) {
            $query->where('employee_id', $this->selectedEmployeeId);
        }

        return $query
            ->when($this->startDate, fn ($q) => $q->whereDate('date', '>=', $this->startDate))
            ->when($this->endDate, fn ($q) => $q->whereDate('date', '<=', $this->endDate))
            ->orderBy('date', 'desc')
            ->orderBy('start_time', 'desc');
    }

    public function getView(): string
    {
        return 'filament.pages.attendance-detailed-report';
    }

    public function getTitle(): string
    {
        return 'تقرير حضور المعلمين بالتفصيل';
    }
}
