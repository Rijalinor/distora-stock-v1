<x-filament-panels::page>
    @if (method_exists($this, 'getHeaderWidgets') && count($this->getHeaderWidgets()))
        <x-filament-widgets::widgets
            :widgets="$this->getVisibleHeaderWidgets()"
            :columns="$this->getHeaderWidgetsColumns()"
            :data="$this->getWidgetData()"
        />
    @endif

    {{ $this->table }}
</x-filament-panels::page>
