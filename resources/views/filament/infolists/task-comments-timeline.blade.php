@php
    $comments = $getRecord()->comments;
@endphp

<ul class="space-y-4">
    @foreach ($comments as $comment)
        <li class="flex gap-x-3">
            <div class="flex-none">
                <span @class([
                    'relative z-10 flex h-8 w-8 flex-none items-center justify-center rounded-full ring-4 ring-white dark:ring-gray-900',
                    'bg-primary-500' => $loop->first,
                    'bg-gray-300 dark:bg-gray-700' => ! $loop->first,
                ])>
                    <x-filament::icon icon="heroicon-o-user" class="h-4 w-4 text-white" />
                </span>
            </div>

            <div class="min-w-0 flex-1">
                <div class="flex items-center gap-x-2">
                    <p class="text-sm font-medium text-gray-900 dark:text-white">
                        {{ $comment->author?->name ?? __('tasks.infolist.unknown_actor') }}
                    </p>
                    <span class="text-xs text-gray-500 dark:text-gray-400">
                        {{ $comment->created_at->translatedFormat('d M Y, H:i') }}
                    </span>
                </div>

                <p class="mt-1 text-sm text-gray-700 dark:text-gray-300 whitespace-pre-wrap">{{ $comment->body }}</p>
            </div>
        </li>
    @endforeach
</ul>
