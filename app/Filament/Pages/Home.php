<?php

namespace App\Filament\Pages;

use Filament\Pages\Dashboard as BaseDashboard;

class Home extends BaseDashboard
{
    protected static ?string $title = 'الرئيسية';

    protected static ?string $navigationLabel = 'الرئيسية';

    public function getWidgets(): array
    {
        return [
            \App\Filament\Widgets\AttendanceStatusWidget::class,
        ];
    }
}
