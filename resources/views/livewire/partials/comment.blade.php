@php
    use Filament\Forms\Components\RichEditor\RichContentRenderer;

    $author = $comment->author;
    $authorName = $author?->name ?? 'Unknown user';
    $initials = str($authorName)
        ->explode(' ')
        ->filter()
        ->map(fn(string $part): string => str($part)->substr(0, 1)->upper()->toString())
        ->take(2)
        ->implode('');

    $content = RichContentRenderer::make($comment->body)->mentions($renderMentionProviders)->toHtml();
@endphp

<article class="fi-commentable-comment {{ $depth > 0 ? 'mt-4' : '' }}">
    <div class="flex items-start gap-3">
        <div
            class="flex size-8 shrink-0 items-center justify-center rounded-full bg-gray-700 text-xs font-semibold text-white dark:bg-gray-600">
            {{ $initials ?: '?' }}
        </div>

        <div class="min-w-0 flex-1">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <div class="flex flex-wrap items-baseline gap-x-2 gap-y-1">
                        <p class="truncate text-sm font-semibold text-gray-950 dark:text-white">{{ $authorName }}</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ $comment->created_at?->diffForHumans() }}
                        </p>
                    </div>

                    @if ($editingCommentId !== $comment->id)
                        <div class="fi-commentable-content mt-1 text-sm leading-6 text-gray-700 dark:text-gray-200">
                            {!! $content !!}
                        </div>
                    @endif
                </div>

                <div class="flex shrink-0 items-center gap-3 text-gray-400">
                    @can('reply', $comment)
                        <button type="button" class="hover:text-primary-600 dark:hover:text-primary-400"
                            wire:click="startReply({{ $comment->id }})" title="Reply">
                            <span class="sr-only">Reply</span>
                            <span aria-hidden="true">reply</span>
                        </button>
                    @endcan

                    <div class="relative text-xs font-medium">
                        @can('update', $comment)
                            <button type="button" class="text-primary-600 hover:text-primary-500 dark:text-primary-400"
                                wire:click="startEditing({{ $comment->id }})">Edit</button>
                        @endcan
                        @can('delete', $comment)
                            <button type="button" class="ms-2 text-danger-600 hover:text-danger-500 dark:text-danger-400"
                                wire:click="deleteComment({{ $comment->id }})"
                                wire:confirm="Delete this comment?">Delete</button>
                        @endcan
                    </div>
                </div>
            </div>

            @if ($editingCommentId === $comment->id)
                <form class="mt-3 space-y-3 rounded-lg border border-gray-200 bg-gray-50 p-3 dark:border-white/10 dark:bg-white/5"
                    wire:submit="updateComment">
                    <div class="fi-commentable-editor-shell">
                        {{ $this->editForm }}
                    </div>
                    <div class="flex justify-end gap-2">
                        <button type="button"
                            class="rounded-lg border border-gray-200 px-3 py-2 text-sm font-semibold text-gray-700 hover:bg-white dark:border-white/10 dark:text-gray-200 dark:hover:bg-white/10"
                            wire:click="$set('editingCommentId', null)">Cancel</button>
                        <button type="submit"
                            class="rounded-lg bg-primary-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-primary-500">Save</button>
                    </div>
                </form>
            @endif

            @if ($comment->attachments->isNotEmpty())
                <div class="mt-2 flex flex-wrap gap-2">
                    @foreach ($comment->attachments as $attachment)
                        <a href="{{ route('comments.attachments.download', $attachment) }}"
                            class="inline-flex items-center rounded-md bg-gray-100 px-2 py-1 text-xs font-medium text-primary-600 hover:bg-gray-200 dark:bg-white/10 dark:text-primary-400 dark:hover:bg-white/15">
                            {{ $attachment->original_name }}
                        </a>
                    @endforeach
                </div>
            @endif

            <div class="mt-2 flex flex-wrap gap-1.5">
                @foreach ($allowedReactions as $reaction)
                    @php
                        $reactionCount = $comment->reactions->where('reaction', $reaction)->count();
                        $hasReacted = $comment->reactions
                            ->where('reaction', $reaction)
                            ->where('user_id', auth()->id())
                            ->isNotEmpty();
                    @endphp

                    @can('react', $comment)
                        <button type="button" @class([
                            'inline-flex min-h-6 items-center gap-1 rounded-full px-2 text-xs font-medium transition',
                            'bg-primary-50 text-primary-700 dark:bg-primary-400/10 dark:text-primary-300' => $hasReacted,
                            'bg-gray-50 text-gray-600 hover:bg-gray-100 dark:bg-white/5 dark:text-gray-300 dark:hover:bg-white/10' => !$hasReacted,
                        ])
                            wire:click="toggleReaction({{ $comment->id }}, @js($reaction))">
                            <span>{{ $reaction }}</span>
                            @if ($reactionCount > 0)
                                <span>{{ $reactionCount }}</span>
                            @endif
                        </button>
                    @endcan
                @endforeach
            </div>

            @if ($replyingTo === $comment->id)
                <form
                    class="mt-3 space-y-3 rounded-lg border border-gray-200 bg-gray-50 p-3 dark:border-white/10 dark:bg-white/5"
                    wire:submit="addReply">
                    <div class="fi-commentable-editor-shell">
                        {{ $this->replyForm }}
                    </div>

                    <div class="flex justify-end gap-2">
                        <button type="button"
                            class="rounded-lg border border-gray-200 px-3 py-2 text-sm font-semibold text-gray-700 hover:bg-white dark:border-white/10 dark:text-gray-200 dark:hover:bg-white/10"
                            wire:click="$set('replyingTo', null)">Cancel</button>
                        <button type="submit"
                            class="rounded-lg bg-primary-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-primary-500">Reply</button>
                    </div>

                    @error('replyData.body')
                        <p class="text-sm text-danger-600 dark:text-danger-400">{{ $message }}</p>
                    @enderror
                </form>
            @endif

            @foreach ($comment->replies as $reply)
                @include('livewire.partials.comment', [
                    'comment' => $reply,
                    'depth' => $depth + 1,
                    'renderMentionProviders' => $renderMentionProviders,
                ])
            @endforeach
        </div>
    </div>
</article>
