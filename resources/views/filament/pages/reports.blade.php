<x-filament-panels::page>
    <p class="text-sm text-gray-500 dark:text-gray-400">
        {{ __('reports.description') }}
    </p>

    <div class="grid grid-cols-1 gap-4">
        @foreach ($reports as $report)
            <x-filament::section>
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="font-medium">{{ $report['label'] }}</p>
                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ $report['description'] }}</p>
                    </div>
                    <div class="flex shrink-0 gap-2">
                        <x-filament::button
                            color="danger"
                            icon="heroicon-o-document-text"
                            wire:click="mountAction('exportPdf', { report: '{{ $report['key'] }}' })"
                        >
                            {{ __('reports.actions.export_pdf') }}
                        </x-filament::button>
                        <x-filament::button
                            color="success"
                            icon="heroicon-o-table-cells"
                            wire:click="mountAction('exportExcel', { report: '{{ $report['key'] }}' })"
                        >
                            {{ __('reports.actions.export_excel') }}
                        </x-filament::button>
                    </div>
                </div>
            </x-filament::section>
        @endforeach
    </div>

    {{ $this->table }}
</x-filament-panels::page>
