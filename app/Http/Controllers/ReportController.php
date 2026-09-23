<?php

namespace App\Http\Controllers;

use App\Models\DailyClassAttendance;
use App\Models\Resultsys\Employee;
use Illuminate\Http\RedirectResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use TCPDF;

class ReportController extends Controller
{
    public function downloadAttendanceOverallPdf(): StreamedResponse|RedirectResponse
    {
        $startDate = request('startDate');
        $endDate = request('endDate');

        $teacherIds = Employee::query()->whereHas('teacherClasses')->pluck('id');
        $names = Employee::query()->whereIn('id', $teacherIds)->pluck('name', 'id');

        $records = $teacherIds->map(function ($employeeId) use ($startDate, $endDate, $names) {
            $attendanceRecords = DailyClassAttendance::query()
                ->where('employee_id', $employeeId)
                ->when($startDate, fn ($q) => $q->whereDate('date', '>=', $startDate))
                ->when($endDate, fn ($q) => $q->whereDate('date', '<=', $endDate))
                ->get();

            $totalSessions = $attendanceRecords->count();
            $attendedSessions = $attendanceRecords->where('status', 'completed')->count();
            $absentSessions = $totalSessions - $attendedSessions;
            $percentage = $totalSessions > 0 ? ($attendedSessions / $totalSessions) * 100 : 0;

            return [
                'name' => $names[$employeeId] ?? '-',
                'total' => $totalSessions,
                'attended' => $attendedSessions,
                'absent' => $absentSessions,
                'percentage' => number_format($percentage, 1),
            ];
        })->filter(fn ($r) => $r['total'] > 0)->values();

        $totalSessions = $records->sum('total');
        $totalAttended = $records->sum('attended');
        $totalAbsent = $records->sum('absent');

        try {
            $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
            $pdf->SetCreator('نظام تيجان');
            $pdf->SetAuthor('نظام تيجان');
            $pdf->SetTitle('تقرير الحضور الإجمالي');
            $pdf->SetSubject('تقرير الحضور الإجمالي');
            $pdf->setPrintHeader(false);
            $pdf->setPrintFooter(false);
            $pdf->SetMargins(15, 15, 15);
            $pdf->SetAutoPageBreak(true, 15);
            $pdf->AddPage();
            $pdf->SetFont('dejavusans', '', 11);
            $pdf->setRTL(true);

            $pdf->SetFont('dejavusans', 'B', 16);
            $pdf->SetTextColor(30, 58, 95);
            $pdf->Cell(0, 8, 'تقرير الحضور الإجمالي', 0, 1, 'C');
            $pdf->SetFont('dejavusans', '', 10);
            $pdf->SetTextColor(100, 100, 100);
            $pdf->Cell(0, 5, 'من: ' . ($startDate ?? 'البداية') . ' إلى: ' . ($endDate ?? 'النهاية'), 0, 1, 'C');
            $pdf->Ln(8);

            $pdf->SetFont('dejavusans', 'B', 10);
            $pdf->SetFillColor(30, 58, 95);
            $pdf->SetTextColor(255, 255, 255);
            $pdf->Cell(70, 7, 'اسم المعلم/ة', 1, 0, 'C', true);
            $pdf->Cell(30, 7, 'إجمالي', 1, 0, 'C', true);
            $pdf->Cell(30, 7, 'حاضر', 1, 0, 'C', true);
            $pdf->Cell(30, 7, 'غايب', 1, 0, 'C', true);
            $pdf->Cell(30, 7, 'النسبة %', 1, 1, 'C', true);

            $pdf->SetFont('dejavusans', '', 9);
            $pdf->SetTextColor(0, 0, 0);
            foreach ($records as $index => $record) {
                $bgColor = ($index % 2) ? [245, 245, 245] : [255, 255, 255];
                $pdf->SetFillColor($bgColor[0], $bgColor[1], $bgColor[2]);
                $pdf->Cell(70, 6, $record['name'], 1, 0, 'R', true);
                $pdf->Cell(30, 6, $record['total'], 1, 0, 'C', true);
                $pdf->Cell(30, 6, $record['attended'], 1, 0, 'C', true);
                $pdf->Cell(30, 6, $record['absent'], 1, 0, 'C', true);
                $pdf->Cell(30, 6, $record['percentage'] . '%', 1, 1, 'C', true);
            }

            $pdf->SetFont('dejavusans', 'B', 10);
            $pdf->SetFillColor(44, 82, 130);
            $pdf->SetTextColor(255, 255, 255);
            $pdf->Cell(70, 7, 'الإجمالي', 1, 0, 'C', true);
            $pdf->Cell(30, 7, $totalSessions, 1, 0, 'C', true);
            $pdf->Cell(30, 7, $totalAttended, 1, 0, 'C', true);
            $pdf->Cell(30, 7, $totalAbsent, 1, 0, 'C', true);
            $pdf->Cell(30, 7, number_format(($totalSessions > 0 ? ($totalAttended / $totalSessions) * 100 : 0), 1) . '%', 1, 1, 'C', true);

            $filename = 'تقرير_الحضور_الإجمالي_' . now()->format('Y_m_d_H_i_s') . '.pdf';
            $content = $pdf->Output($filename, 'S');

            return response()->streamDownload(
                fn () => print($content),
                $filename,
                ['Content-Type' => 'application/pdf'],
            );
        } catch (\Exception $e) {
            return back()->with('error', 'خطأ في توليد PDF: ' . $e->getMessage());
        }
    }

    public function downloadAttendanceDetailedPdf(): StreamedResponse|RedirectResponse
    {
        $employeeId = request('employeeId');
        $startDate = request('startDate');
        $endDate = request('endDate');

        if (!$employeeId) {
            return back()->with('error', 'يجب اختيار معلم/ة');
        }

        $employee = Employee::find($employeeId);
        if (!$employee) {
            return back()->with('error', 'المعلم/ة غير موجود');
        }

        $records = DailyClassAttendance::query()
            ->where('employee_id', $employeeId)
            ->when($startDate, fn ($q) => $q->whereDate('date', '>=', $startDate))
            ->when($endDate, fn ($q) => $q->whereDate('date', '<=', $endDate))
            ->orderBy('date', 'desc')
            ->orderBy('start_time', 'desc')
            ->get();

        $totalSessions = $records->count();
        $attendedSessions = $records->where('status', 'completed')->count();
        $absentSessions = $totalSessions - $attendedSessions;

        try {
            $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
            $pdf->SetCreator('نظام تيجان');
            $pdf->SetAuthor('نظام تيجان');
            $pdf->SetTitle('تقرير الحضور التفصيلي');
            $pdf->SetSubject('تقرير الحضور التفصيلي');
            $pdf->setPrintHeader(false);
            $pdf->setPrintFooter(false);
            $pdf->SetMargins(15, 15, 15);
            $pdf->SetAutoPageBreak(true, 15);
            $pdf->AddPage();
            $pdf->SetFont('dejavusans', '', 11);
            $pdf->setRTL(true);

            $pdf->SetFont('dejavusans', 'B', 16);
            $pdf->SetTextColor(30, 58, 95);
            $pdf->Cell(0, 8, 'تقرير الحضور التفصيلي', 0, 1, 'C');

            $pdf->SetFont('dejavusans', 'B', 12);
            $pdf->SetTextColor(50, 50, 50);
            $pdf->Cell(0, 6, 'المعلم/ة: ' . $employee->name, 0, 1, 'R');

            $pdf->SetFont('dejavusans', '', 10);
            $pdf->SetTextColor(100, 100, 100);
            $pdf->Cell(0, 5, 'من: ' . ($startDate ?? 'البداية') . ' إلى: ' . ($endDate ?? 'النهاية'), 0, 1, 'R');

            $pdf->SetFont('dejavusans', '', 9);
            $pdf->Cell(0, 4, 'إجمالي: ' . $totalSessions . ' | حاضر: ' . $attendedSessions . ' | غايب: ' . $absentSessions, 0, 1, 'R');
            $pdf->Ln(6);

            $pdf->SetFont('dejavusans', 'B', 9);
            $pdf->SetFillColor(30, 58, 95);
            $pdf->SetTextColor(255, 255, 255);
            $pdf->Cell(30, 6, 'التاريخ', 1, 0, 'C', true);
            $pdf->Cell(20, 6, 'البداية', 1, 0, 'C', true);
            $pdf->Cell(20, 6, 'النهاية', 1, 0, 'C', true);
            $pdf->Cell(30, 6, 'الحالة', 1, 0, 'C', true);
            $pdf->Cell(25, 6, 'الدخول', 1, 0, 'C', true);
            $pdf->Cell(25, 6, 'الخروج', 1, 0, 'C', true);
            $pdf->Cell(30, 6, 'المدة', 1, 1, 'C', true);

            $pdf->SetFont('dejavusans', '', 8);
            $pdf->SetTextColor(0, 0, 0);
            foreach ($records as $index => $record) {
                $bgColor = ($index % 2) ? [245, 245, 245] : [255, 255, 255];
                $pdf->SetFillColor($bgColor[0], $bgColor[1], $bgColor[2]);

                $status = match ($record->status) {
                    'completed' => 'حاضر',
                    'checked_in' => 'جزئي',
                    'pending' => 'غايب',
                    default => $record->status,
                };

                $duration = '-';
                if ($record->check_in_at && $record->check_out_at) {
                    $start = \Carbon\Carbon::parse($record->check_in_at);
                    $end = \Carbon\Carbon::parse($record->check_out_at);
                    $minutes = $end->diffInMinutes($start);
                    $duration = floor($minutes / 60) . 'h' . ($minutes % 60) . 'm';
                }

                $pdf->Cell(30, 5, $record->date->format('Y-m-d'), 1, 0, 'C', true);
                $pdf->Cell(20, 5, \Carbon\Carbon::parse($record->start_time)->format('H:i'), 1, 0, 'C', true);
                $pdf->Cell(20, 5, \Carbon\Carbon::parse($record->end_time)->format('H:i'), 1, 0, 'C', true);
                $pdf->Cell(30, 5, $status, 1, 0, 'C', true);
                $pdf->Cell(25, 5, $record->check_in_at ? $record->check_in_at->format('H:i') : '-', 1, 0, 'C', true);
                $pdf->Cell(25, 5, $record->check_out_at ? $record->check_out_at->format('H:i') : '-', 1, 0, 'C', true);
                $pdf->Cell(30, 5, $duration, 1, 1, 'C', true);
            }

            $filename = 'تقرير_حضور_' . str_replace(' ', '_', $employee->name) . '_' . now()->format('Y_m_d_H_i_s') . '.pdf';
            $content = $pdf->Output($filename, 'S');

            return response()->streamDownload(
                fn () => print($content),
                $filename,
                ['Content-Type' => 'application/pdf'],
            );
        } catch (\Exception $e) {
            return back()->with('error', 'خطأ في توليد PDF: ' . $e->getMessage());
        }
    }
}
