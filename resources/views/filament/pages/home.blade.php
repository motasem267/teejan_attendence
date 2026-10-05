<x-filament-panels::page>
    <div style="margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
        <label style="color: #9ca3af; font-size: 14px;">التاريخ:</label>
        <input
            type="date"
            wire:model.live="selectedDate"
            style="background: #111827; color: #f9fafb; border: 1px solid #374151; border-radius: 6px; padding: 8px 12px;"
        >
    </div>

    <div style="display: flex; flex-direction: column; gap: 20px;">
        {{-- المعلمون --}}
        <div style="background: #1f2937; border: 1px solid #374151; border-radius: 8px; padding: 20px;">
            <h2 style="font-size: 18px; font-weight: bold; color: #f9fafb; margin: 0 0 15px 0;">المعلمون</h2>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 15px;">
                <a href="{{ \App\Filament\Pages\TeacherSessionsDrilldown::getUrl(['status' => 'completed', 'date' => $selectedDate]) }}"
                   style="display: block; background: #111827; border: 1px solid #374151; border-radius: 6px; padding: 15px; text-decoration: none; transition: border-color .15s;">
                    <p style="color: #9ca3af; font-size: 13px; margin: 0 0 5px 0;">✓ حصص مكتملة</p>
                    <p style="font-weight: 600; font-size: 24px; margin: 0; color: #22c55e;">{{ $completedSessionsCount }}</p>
                </a>

                <a href="{{ \App\Filament\Pages\TeacherSessionsDrilldown::getUrl(['status' => 'pending', 'date' => $selectedDate]) }}"
                   style="display: block; background: #111827; border: 1px solid #374151; border-radius: 6px; padding: 15px; text-decoration: none;">
                    <p style="color: #9ca3af; font-size: 13px; margin: 0 0 5px 0;">✗ لم يحضر</p>
                    <p style="font-weight: 600; font-size: 24px; margin: 0; color: #ef4444;">{{ $pendingSessionsCount }}</p>
                </a>

                <a href="{{ \App\Filament\Pages\TeacherSessionsDrilldown::getUrl(['status' => 'checked_in', 'date' => $selectedDate]) }}"
                   style="display: block; background: #111827; border: 1px solid #374151; border-radius: 6px; padding: 15px; text-decoration: none;">
                    <p style="color: #9ca3af; font-size: 13px; margin: 0 0 5px 0;">⏳ دخول بلا خروج</p>
                    <p style="font-weight: 600; font-size: 24px; margin: 0; color: #eab308;">{{ $checkedInSessionsCount }}</p>
                </a>
            </div>
        </div>

        {{-- الطلبة --}}
        <div style="background: #1f2937; border: 1px solid #374151; border-radius: 8px; padding: 20px;">
            <h2 style="font-size: 18px; font-weight: bold; color: #f9fafb; margin: 0 0 15px 0;">الطلبة</h2>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 15px;">
                <a href="{{ \App\Filament\Pages\StudentsAttendanceDrilldown::getUrl(['status' => 'present', 'date' => $selectedDate]) }}"
                   style="display: block; background: #111827; border: 1px solid #374151; border-radius: 6px; padding: 15px; text-decoration: none;">
                    <p style="color: #9ca3af; font-size: 13px; margin: 0 0 5px 0;">🎓 طلبة حاضرون</p>
                    <p style="font-weight: 600; font-size: 24px; margin: 0; color: #a855f7;">{{ $presentStudentsCount }}</p>
                </a>

                <a href="{{ \App\Filament\Pages\StudentsAttendanceDrilldown::getUrl(['status' => 'absent', 'date' => $selectedDate]) }}"
                   style="display: block; background: #111827; border: 1px solid #374151; border-radius: 6px; padding: 15px; text-decoration: none;">
                    <p style="color: #9ca3af; font-size: 13px; margin: 0 0 5px 0;">✗ طلبة غير حاضرين</p>
                    <p style="font-weight: 600; font-size: 24px; margin: 0; color: #ef4444;">{{ $absentStudentsCount }}</p>
                </a>
            </div>
        </div>

        {{-- الموظفون --}}
        <div style="background: #1f2937; border: 1px solid #374151; border-radius: 8px; padding: 20px;">
            <h2 style="font-size: 18px; font-weight: bold; color: #f9fafb; margin: 0 0 15px 0;">الموظفون</h2>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 15px;">
                <a href="{{ \App\Filament\Pages\EmployeesAttendanceDrilldown::getUrl(['status' => 'present', 'date' => $selectedDate]) }}"
                   style="display: block; background: #111827; border: 1px solid #374151; border-radius: 6px; padding: 15px; text-decoration: none;">
                    <p style="color: #9ca3af; font-size: 13px; margin: 0 0 5px 0;">👤 موظفون حاضرون</p>
                    <p style="font-weight: 600; font-size: 24px; margin: 0; color: #3b82f6;">{{ $presentEmployeesCount }}</p>
                </a>

                <a href="{{ \App\Filament\Pages\EmployeesAttendanceDrilldown::getUrl(['status' => 'absent', 'date' => $selectedDate]) }}"
                   style="display: block; background: #111827; border: 1px solid #374151; border-radius: 6px; padding: 15px; text-decoration: none;">
                    <p style="color: #9ca3af; font-size: 13px; margin: 0 0 5px 0;">✗ موظفون غير حاضرين</p>
                    <p style="font-weight: 600; font-size: 24px; margin: 0; color: #ef4444;">{{ $absentEmployeesCount }}</p>
                </a>
            </div>
        </div>
    </div>

    <div style="margin-top: 20px;">
        @livewire(\App\Filament\Widgets\AttendanceStatusWidget::class)
    </div>
</x-filament-panels::page>
