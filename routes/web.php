<?php

use App\Http\Controllers\ReportController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::get('/reports/attendance-overall-pdf', [ReportController::class, 'downloadAttendanceOverallPdf'])
        ->name('reports.attendance-overall.pdf');

    Route::get('/reports/attendance-detailed-pdf', [ReportController::class, 'downloadAttendanceDetailedPdf'])
        ->name('reports.attendance-detailed.pdf');
});
