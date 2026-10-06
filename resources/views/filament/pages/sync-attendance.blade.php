<x-filament-panels::page>
    <div style="background: #ffffff; border: 1px solid #e5e7eb; border-radius: 12px; padding: 20px; box-shadow: 0 1px 2px rgba(0,0,0,.04); margin-bottom: 20px;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; flex-wrap: wrap; gap: 10px;">
            <h2 style="font-size: 17px; font-weight: 700; margin: 0; color: #111827;">🔄 مزامنة بيانات الحضور</h2>
            <x-filament::button wire:click="toggleMode" color="gray" icon="heroicon-o-arrow-path-rounded-square">
                نمط المطابقة: {{ $isDurationMode ? 'الجلسات الزمنية' : 'الجدول الدراسي' }}
            </x-filament::button>
        </div>

        <p style="color: #6b7280; font-size: 13px; margin: 0 0 16px 0;">
            نمط "الجدول الدراسي" يقارن البصمة بوقت الحصة المتوقع. نمط "الجلسات الزمنية" يجمع بصمتين متتاليتين على نفس الجهاز (15-80 دقيقة) بلا حاجة للجدول.
            أي يوم اتزامن من قبل يتخطى تلقائيًا (بلا تكرار)، إلا لو ضغطت "إعادة مزامنة" عليه من الجدول تحت.
        </p>

        <form wire:submit.prevent="syncRange">
            <div style="display: flex; align-items: flex-end; gap: 16px; flex-wrap: wrap;">
                <div style="flex: 1; min-width: 200px;">
                    {{ $this->form }}
                </div>
                <x-filament::button type="submit" icon="heroicon-o-play">
                    مزامنة النطاق
                </x-filament::button>
            </div>
        </form>
    </div>

    <div class="space-y-6">
        {{ $this->table }}
    </div>
</x-filament-panels::page>
