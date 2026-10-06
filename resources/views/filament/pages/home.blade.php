<x-filament-panels::page>
    <div style="display: flex; flex-direction: column; gap: 20px;">
        {{-- فلتر التاريخ --}}
        <div style="background: #ffffff; border: 1px solid #e5e7eb; border-radius: 12px; padding: 16px 20px; display: flex; align-items: center; gap: 12px; box-shadow: 0 1px 2px rgba(0,0,0,.04);">
            <span style="color: #6b7280; font-size: 14px; font-weight: 500;">📅 عرض بيانات يوم:</span>
            <input
                type="date"
                wire:model.live="selectedDate"
                style="background: #f9fafb; color: #111827; border: 1px solid #d1d5db; border-radius: 8px; padding: 8px 14px; font-size: 14px;"
            >
        </div>

        {{-- اختصار لصفحة المزامنة --}}
        <a href="{{ \App\Filament\Pages\SyncAttendance::getUrl() }}"
           style="display: flex; align-items: center; justify-content: space-between; background: #ffffff; border: 1px solid #e5e7eb; border-radius: 12px; padding: 16px 20px; text-decoration: none; box-shadow: 0 1px 2px rgba(0,0,0,.04);">
            <span style="color: #111827; font-weight: 600; font-size: 15px;">🔄 مزامنة بيانات الحضور</span>
            <span style="color: #6b7280; font-size: 13px;">اذهب لصفحة المزامنة ←</span>
        </a>

        {{-- المعلمون --}}
        <div style="background: #ffffff; border: 1px solid #e5e7eb; border-radius: 12px; padding: 20px; box-shadow: 0 1px 2px rgba(0,0,0,.04);">
            <h2 style="font-size: 17px; font-weight: 700; color: #111827; margin: 0 0 16px 0; display: flex; align-items: center; gap: 8px;">
                👨‍🏫 المعلمون
            </h2>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 14px;">
                <a href="{{ \App\Filament\Pages\TeacherSessionsDrilldown::getUrl(['status' => 'completed', 'date' => $selectedDate]) }}"
                   style="display: block; background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 10px; padding: 16px; text-decoration: none; transition: box-shadow .15s, transform .15s;"
                   onmouseover="this.style.boxShadow='0 4px 10px rgba(0,0,0,.08)';this.style.transform='translateY(-1px)'"
                   onmouseout="this.style.boxShadow='none';this.style.transform='none'">
                    <p style="color: #6b7280; font-size: 13px; margin: 0 0 6px 0;">✓ حصص مكتملة</p>
                    <p style="font-weight: 700; font-size: 26px; margin: 0; color: #16a34a;">{{ $completedSessionsCount }}</p>
                </a>

                <a href="{{ \App\Filament\Pages\TeacherSessionsDrilldown::getUrl(['status' => 'pending', 'date' => $selectedDate]) }}"
                   style="display: block; background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 10px; padding: 16px; text-decoration: none; transition: box-shadow .15s, transform .15s;"
                   onmouseover="this.style.boxShadow='0 4px 10px rgba(0,0,0,.08)';this.style.transform='translateY(-1px)'"
                   onmouseout="this.style.boxShadow='none';this.style.transform='none'">
                    <p style="color: #6b7280; font-size: 13px; margin: 0 0 6px 0;">✗ لم يحضر</p>
                    <p style="font-weight: 700; font-size: 26px; margin: 0; color: #dc2626;">{{ $pendingSessionsCount }}</p>
                </a>

                <a href="{{ \App\Filament\Pages\TeacherSessionsDrilldown::getUrl(['status' => 'checked_in', 'date' => $selectedDate]) }}"
                   style="display: block; background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 10px; padding: 16px; text-decoration: none; transition: box-shadow .15s, transform .15s;"
                   onmouseover="this.style.boxShadow='0 4px 10px rgba(0,0,0,.08)';this.style.transform='translateY(-1px)'"
                   onmouseout="this.style.boxShadow='none';this.style.transform='none'">
                    <p style="color: #6b7280; font-size: 13px; margin: 0 0 6px 0;">⏳ دخول بلا خروج</p>
                    <p style="font-weight: 700; font-size: 26px; margin: 0; color: #ca8a04;">{{ $checkedInSessionsCount }}</p>
                </a>
            </div>
        </div>

        {{-- الموظفون --}}
        <div style="background: #ffffff; border: 1px solid #e5e7eb; border-radius: 12px; padding: 20px; box-shadow: 0 1px 2px rgba(0,0,0,.04);">
            <h2 style="font-size: 17px; font-weight: 700; color: #111827; margin: 0 0 16px 0; display: flex; align-items: center; gap: 8px;">
                👤 الموظفون
            </h2>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 14px;">
                <a href="{{ \App\Filament\Pages\EmployeesAttendanceDrilldown::getUrl(['status' => 'present', 'date' => $selectedDate]) }}"
                   style="display: block; background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 10px; padding: 16px; text-decoration: none; transition: box-shadow .15s, transform .15s;"
                   onmouseover="this.style.boxShadow='0 4px 10px rgba(0,0,0,.08)';this.style.transform='translateY(-1px)'"
                   onmouseout="this.style.boxShadow='none';this.style.transform='none'">
                    <p style="color: #6b7280; font-size: 13px; margin: 0 0 6px 0;">✓ موظفون حاضرون</p>
                    <p style="font-weight: 700; font-size: 26px; margin: 0; color: #2563eb;">{{ $presentEmployeesCount }}</p>
                </a>

                <a href="{{ \App\Filament\Pages\EmployeesAttendanceDrilldown::getUrl(['status' => 'absent', 'date' => $selectedDate]) }}"
                   style="display: block; background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 10px; padding: 16px; text-decoration: none; transition: box-shadow .15s, transform .15s;"
                   onmouseover="this.style.boxShadow='0 4px 10px rgba(0,0,0,.08)';this.style.transform='translateY(-1px)'"
                   onmouseout="this.style.boxShadow='none';this.style.transform='none'">
                    <p style="color: #6b7280; font-size: 13px; margin: 0 0 6px 0;">✗ موظفون غير حاضرين</p>
                    <p style="font-weight: 700; font-size: 26px; margin: 0; color: #dc2626;">{{ $absentEmployeesCount }}</p>
                </a>
            </div>
        </div>

        {{-- الطلبة --}}
        <div style="background: #ffffff; border: 1px solid #e5e7eb; border-radius: 12px; padding: 20px; box-shadow: 0 1px 2px rgba(0,0,0,.04);">
            <h2 style="font-size: 17px; font-weight: 700; color: #111827; margin: 0 0 16px 0; display: flex; align-items: center; gap: 8px;">
                🎓 الطلبة
            </h2>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 14px;">
                <a href="{{ \App\Filament\Pages\StudentsAttendanceDrilldown::getUrl(['status' => 'present', 'date' => $selectedDate]) }}"
                   style="display: block; background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 10px; padding: 16px; text-decoration: none; transition: box-shadow .15s, transform .15s;"
                   onmouseover="this.style.boxShadow='0 4px 10px rgba(0,0,0,.08)';this.style.transform='translateY(-1px)'"
                   onmouseout="this.style.boxShadow='none';this.style.transform='none'">
                    <p style="color: #6b7280; font-size: 13px; margin: 0 0 6px 0;">✓ طلبة حاضرون</p>
                    <p style="font-weight: 700; font-size: 26px; margin: 0; color: #9333ea;">{{ $presentStudentsCount }}</p>
                </a>

                <a href="{{ \App\Filament\Pages\StudentsAttendanceDrilldown::getUrl(['status' => 'absent', 'date' => $selectedDate]) }}"
                   style="display: block; background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 10px; padding: 16px; text-decoration: none; transition: box-shadow .15s, transform .15s;"
                   onmouseover="this.style.boxShadow='0 4px 10px rgba(0,0,0,.08)';this.style.transform='translateY(-1px)'"
                   onmouseout="this.style.boxShadow='none';this.style.transform='none'">
                    <p style="color: #6b7280; font-size: 13px; margin: 0 0 6px 0;">✗ طلبة غير حاضرين</p>
                    <p style="font-weight: 700; font-size: 26px; margin: 0; color: #dc2626;">{{ $absentStudentsCount }}</p>
                </a>
            </div>
        </div>
    </div>
</x-filament-panels::page>
