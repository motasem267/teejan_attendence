<?php

namespace App\Filament\Pages;

use App\Models\Resultsys\Student;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use UnitEnum;

class StudentsList extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = \Filament\Support\Icons\Heroicon::OutlinedAcademicCap;
    protected static ?string $navigationLabel = 'الطلبة';
    protected static string|UnitEnum|null $navigationGroup = null;

    public function table(Table $table): Table
    {
        return $table
            ->query(Student::query()->enrolledActiveYear()->with('status'))
            ->columns([
                TextColumn::make('id')
                    ->label('المعرّف')
                    ->sortable(),

                TextColumn::make('full_name')
                    ->label('الاسم')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('national_id')
                    ->label('الرقم الوطني')
                    ->placeholder('-'),

                TextColumn::make('status.name')
                    ->label('الحالة')
                    ->badge()
                    ->placeholder('-'),

                TextColumn::make('mother_phone')
                    ->label('هاتف ولي الأمر')
                    ->placeholder('-'),
            ])
            ->defaultSort('full_name')
            ->searchable()
            ->striped();
    }

    public function getView(): string
    {
        return 'filament.pages.students-list';
    }

    public function getTitle(): string
    {
        return 'الطلبة';
    }
}
