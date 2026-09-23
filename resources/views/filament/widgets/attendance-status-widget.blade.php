<x-filament-widgets::widget>
    <div style="background: #1f2937; padding: 20px; border-radius: 8px; margin-bottom: 20px; border: 1px solid #374151;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; flex-wrap: wrap; gap: 10px;">
            <h2 style="font-size: 20px; font-weight: bold; margin: 0; color: #f9fafb;">حالة الحضور اليوم</h2>
            <div style="display: flex; gap: 10px; align-items: center;">
                <x-filament::button wire:click="toggleMode" color="gray" icon="heroicon-o-arrow-path-rounded-square">
                    نمط المطابقة: {{ $isDurationMode ? 'الجلسات الزمنية' : 'الجدول الدراسي' }}
                </x-filament::button>
                <x-filament::button wire:click="syncNow" icon="heroicon-o-arrow-path">
                    مزامنة الحضور الآن
                </x-filament::button>
            </div>
        </div>

        <p style="color: #9ca3af; font-size: 13px; margin: 0 0 20px 0;">
            نمط "الجدول الدراسي" يقارن البصمة بوقت الحصة المتوقع. نمط "الجلسات الزمنية" يجمع بصمتين متتاليات على نفس الجهاز (15-80 دقيقة) بلا حاجة للجدول.
        </p>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 15px;">
            <div style="background: #111827; padding: 15px; border-radius: 6px; border: 1px solid #374151;">
                <p style="color: #9ca3af; font-size: 13px; margin: 0 0 5px 0;">✓ حصص مكتملة</p>
                <p style="font-weight: 600; font-size: 24px; margin: 0; color: #22c55e;">{{ $completedCount }}</p>
            </div>

            <div style="background: #111827; padding: 15px; border-radius: 6px; border: 1px solid #374151;">
                <p style="color: #9ca3af; font-size: 13px; margin: 0 0 5px 0;">⏳ دخول بلا خروج</p>
                <p style="font-weight: 600; font-size: 24px; margin: 0; color: #eab308;">{{ $checkedInCount }}</p>
            </div>

            <div style="background: #111827; padding: 15px; border-radius: 6px; border: 1px solid #374151;">
                <p style="color: #9ca3af; font-size: 13px; margin: 0 0 5px 0;">✗ لسه ما جاش</p>
                <p style="font-weight: 600; font-size: 24px; margin: 0; color: #ef4444;">{{ $pendingCount }}</p>
            </div>

            <div style="background: #111827; padding: 15px; border-radius: 6px; border: 1px solid #374151;">
                <p style="color: #9ca3af; font-size: 13px; margin: 0 0 5px 0;">👤 موظفين حاضرين (غير معلمين)</p>
                <p style="font-weight: 600; font-size: 24px; margin: 0; color: #3b82f6;">{{ $presentEmployeesCount }}</p>
            </div>

            <div style="background: #111827; padding: 15px; border-radius: 6px; border: 1px solid #374151;">
                <p style="color: #9ca3af; font-size: 13px; margin: 0 0 5px 0;">🎓 طلبة حاضرين</p>
                <p style="font-weight: 600; font-size: 24px; margin: 0; color: #a855f7;">{{ $presentStudentsCount }}</p>
            </div>
        </div>
    </div>
</x-filament-widgets::widget>
