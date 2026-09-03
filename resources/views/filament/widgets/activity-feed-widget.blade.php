<x-filament-widgets::widget>
    <x-filament::section :heading="__('dashboard.activity_feed.heading')">
        @if ($entries->isEmpty())
            <p class="text-sm text-gray-500 dark:text-gray-400">
                {{ __('dashboard.activity_feed.no_data') }}
            </p>
        @else
            <ul class="divide-y divide-gray-200 dark:divide-white/10">
                @foreach ($entries as $entry)
                    <li class="flex items-center justify-between gap-4 py-2 text-sm">
                        <div>
                            @if ($entry['url'])
                                <a href="{{ $entry['url'] }}" class="font-medium text-primary-600 hover:underline dark:text-primary-400">
                                    {{ $entry['summary'] }}
                                </a>
                            @else
                                <span class="font-medium">{{ $entry['summary'] }}</span>
                            @endif
                            @if ($entry['actor'])
                                <span class="text-gray-500 dark:text-gray-400">
                                    &mdash; {{ $entry['actor'] }}
                                </span>
                            @endif
                        </div>
                        <span class="shrink-0 text-xs text-gray-400 dark:text-gray-500">
                            {{ $entry['changedAt']?->diffForHumans() }}
                        </span>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
