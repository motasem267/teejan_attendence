<?php

namespace App\Filament\Pages;

use App\Models\Resultsys\Employee;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use UnitEnum;

class EmployeesList extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = \Filament\Support\Icons\Heroicon::OutlinedIdentification;
    protected static ?string $navigationLabel = 'الموظفون';
    protected static string|UnitEnum|null $navigationGroup = null;

    public function table(Table $table): Table
    {
        return $table
            ->query(Employee::query()->with(['employeeType', 'status']))
            ->columns([
                TextColumn::make('id')
                    ->label('المعرّف')
                    ->sortable(),

                TextColumn::make('name')
                    ->label('الاسم')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('employeeType.type_name')
                    ->label('الوظيفة')
                    ->badge(),

                TextColumn::make('status.status_name')
                    ->label('الحالة')
                    ->badge()
                    ->placeholder('-'),

                TextColumn::make('phone_number')
                    ->label('الهاتف')
                    ->placeholder('-'),
            ])
            ->defaultSort('name')
            ->searchable()
            ->striped();
    }

    public function getView(): string
    {
        return 'filament.pages.employees-list';
    }

    public function getTitle(): string
    {
        return 'الموظفون';
    }
}
