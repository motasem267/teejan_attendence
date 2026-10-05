<x-filament-widgets::widget>
    <div style="background: #ffffff; padding: 20px; border-radius: 12px; border: 1px solid #e5e7eb; box-shadow: 0 1px 2px rgba(0,0,0,.04);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; flex-wrap: wrap; gap: 10px;">
            <h2 style="font-size: 17px; font-weight: 700; margin: 0; color: #111827; display: flex; align-items: center; gap: 8px;">
                🔄 مزامنة بيانات الحضور
            </h2>
            <div style="display: flex; gap: 10px; align-items: center;">
                <x-filament::button wire:click="toggleMode" color="gray" icon="heroicon-o-arrow-path-rounded-square">
                    نمط المطابقة: {{ $isDurationMode ? 'الجلسات الزمنية' : 'الجدول الدراسي' }}
                </x-filament::button>
                <x-filament::button wire:click="syncNow" icon="heroicon-o-arrow-path">
                    مزامنة الحضور الآن
                </x-filament::button>
            </div>
        </div>

        <p style="color: #6b7280; font-size: 13px; margin: 0;">
            نمط "الجدول الدراسي" يقارن البصمة بوقت الحصة المتوقع. نمط "الجلسات الزمنية" يجمع بصمتين متتاليتين على نفس الجهاز (15-80 دقيقة) بلا حاجة للجدول.
        </p>
    </div>
</x-filament-widgets::widget>
