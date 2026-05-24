<section class="fi-commentable space-y-8" wire:poll.{{ $pollInterval }}>
    <form class="fi-commentable-form space-y-3" wire:submit="addComment">
        <div class="fi-commentable-editor-shell">
            {{ $this->form }}
        </div>

        @error('data.body')
            <p class="text-sm text-danger-600 dark:text-danger-400">{{ $message }}</p>
        @enderror

        <div class="flex justify-end">
            <button type="submit"
                class="inline-flex items-center justify-center rounded-lg bg-primary-600 px-3 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 dark:focus:ring-offset-gray-900">
                Create
            </button>
        </div>
    </form>

    <div class="space-y-5">
        @if ($comments->isNotEmpty())
            <h3 class="text-sm font-semibold text-gray-950 dark:text-white">
                {{ $comments->count() }} {{ \Illuminate\Support\Str::plural('comment', $comments->count()) }}
            </h3>
        @endif

        <div class="space-y-4">
            @foreach ($comments as $comment)
                @include('livewire.partials.comment', [
                    'comment' => $comment,
                    'depth' => 0,
                    'renderMentionProviders' => $renderMentionProviders,
                ])
            @endforeach
        </div>
    </div>
</section>
