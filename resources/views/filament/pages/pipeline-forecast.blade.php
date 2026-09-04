<x-filament-panels::page>
    <p class="text-sm text-gray-500 dark:text-gray-400">
        {{ __('pipeline_forecast.description') }}
    </p>

    {{ $this->table }}

    @php
        $total = $this->totalWeightedPipelineValue();
    @endphp

    @if ($total !== null)
        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-900">
            <div class="flex items-center justify-between">
                <span class="text-sm font-medium text-gray-500 dark:text-gray-400">
                    {{ __('pipeline_forecast.total_weighted_value') }}
                </span>
                <span class="text-base font-semibold text-gray-950 dark:text-white">
                    {{ __('tenders.infolist.money_eur', ['amount' => number_format($total, 2)]) }}
                </span>
            </div>
        </div>
    @endif
</x-filament-panels::page>
