<x-filament-widgets::widget>
    <div style="background: #1f2937; padding: 20px; border-radius: 8px; border: 1px solid #374151;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; flex-wrap: wrap; gap: 10px;">
            <h2 style="font-size: 18px; font-weight: bold; margin: 0; color: #f9fafb;">مزامنة بيانات الحضور</h2>
            <div style="display: flex; gap: 10px; align-items: center;">
                <x-filament::button wire:click="toggleMode" color="gray" icon="heroicon-o-arrow-path-rounded-square">
                    نمط المطابقة: {{ $isDurationMode ? 'الجلسات الزمنية' : 'الجدول الدراسي' }}
                </x-filament::button>
                <x-filament::button wire:click="syncNow" icon="heroicon-o-arrow-path">
                    مزامنة الحضور الآن
                </x-filament::button>
            </div>
        </div>

        <p style="color: #9ca3af; font-size: 13px; margin: 0;">
            نمط "الجدول الدراسي" يقارن البصمة بوقت الحصة المتوقع. نمط "الجلسات الزمنية" يجمع بصمتين متتاليتين على نفس الجهاز (15-80 دقيقة) بلا حاجة للجدول.
        </p>
    </div>
</x-filament-widgets::widget>
