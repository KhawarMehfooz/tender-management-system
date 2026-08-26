@php
    $changes = $getRecord()->statusChanges;
@endphp

<ul class="space-y-0">
    @foreach ($changes as $change)
        <li class="relative flex gap-x-4 pb-8 last:pb-0">
            @unless ($loop->last)
                <span class="absolute left-4 top-8 -bottom-0 w-px bg-gray-200 dark:bg-white/10"></span>
            @endunless

            <span @class([
                'relative z-10 flex h-8 w-8 flex-none items-center justify-center rounded-full ring-4 ring-white dark:ring-gray-900',
                'bg-primary-500' => $loop->first,
                'bg-gray-300 dark:bg-gray-700' => ! $loop->first,
            ])>
                <x-filament::icon icon="heroicon-o-flag" class="h-4 w-4 text-white" />
            </span>

            <div class="min-w-0 flex-1 pt-0.5">
                <x-filament::badge :color="$change->to_status->color()" size="sm">
                    {{ $change->to_status->getLabel() }}
                </x-filament::badge>

                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    {{ __('tasks.infolist.status_change_from') }} {{ $change->from_status->getLabel() }}
                </p>

                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    {{ $change->changedBy?->name ?? __('tasks.infolist.unknown_actor') }}
                    &middot;
                    {{ $change->changed_at->translatedFormat('d M Y, H:i') }}
                </p>

                @if ($change->reason)
                    <p class="mt-2 text-sm italic text-gray-700 dark:text-gray-300">
                        &ldquo;{{ $change->reason }}&rdquo;
                    </p>
                @endif
            </div>
        </li>
    @endforeach
</ul>
