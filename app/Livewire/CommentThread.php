<?php

namespace App\Livewire;

use App\Contracts\Commentable;
use App\Filament\App\Resources\Tasks\Pages\EditTask;
use App\Models\Comment;
use App\Models\User;
use App\Services\MentionParser;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\RichEditor\MentionProvider;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Schemas\Schema;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\WithFileUploads;

class CommentThread extends Component implements HasForms
{
    use InteractsWithForms;
    use WithFileUploads;

    public ?string $commentableType = null;

    public ?int $commentableId = null;

    public string $body = '';

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    /**
     * @var array<string, mixed>|null
     */
    public ?array $replyData = [];

    /**
     * @var array<string, mixed>|null
     */
    public ?array $editingData = [];

    public string $bodyFormat = 'rich';

    public ?int $replyingTo = null;

    public string $replyBody = '';

    public ?int $editingCommentId = null;

    public string $editingBody = '';

    /**
     * @var array<int, mixed>
     */
    public array $attachments = [];

    /**
     * @var array<int, mixed>
     */
    public array $replyAttachments = [];

    public function mount(mixed $record = null, bool $markdown = false): void
    {
        if ($record instanceof Commentable) {
            $this->commentableType = $record::class;
            $this->commentableId = $record->getKey();
        }

        $this->bodyFormat = $markdown ? 'markdown' : 'rich';
        $this->form->fill([
            'body' => $this->body,
        ]);
        $this->replyForm->fill([
            'body' => $this->replyBody,
        ]);
        $this->editForm->fill([
            'body' => $this->editingBody,
        ]);
    }

    /**
     * @return array<int, string>
     */
    protected function getForms(): array
    {
        return [
            'form',
            'replyForm',
            'editForm',
        ];
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([$this->makeRichEditorField('Write your comment...')])
            ->statePath('data');
    }

    public function replyForm(Schema $schema): Schema
    {
        return $schema
            ->components([$this->makeRichEditorField('Write a reply...')])
            ->statePath('replyData');
    }

    public function editForm(Schema $schema): Schema
    {
        return $schema
            ->components([$this->makeRichEditorField('Edit your comment...')])
            ->statePath('editingData');
    }

    private function makeRichEditorField(string $placeholder): RichEditor
    {
        return RichEditor::make('body')
            ->hiddenLabel()
            ->placeholder($placeholder)
            ->toolbarButtons([
                ['bold', 'italic', 'strike', 'link'],
            ])
            ->mentions($this->getCommentMentionProviders())
            ->extraInputAttributes([
                'class' => 'fi-commentable-rich-editor',
            ]);
    }

    public function addComment(): void
    {
        $commentable = $this->getCommentable();

        if (! $commentable instanceof Commentable) {
            return;
        }

        Gate::authorize('create', [Comment::class, $commentable]);

        $data = $this->form->getState();
        $body = $data['body'] ?? $this->body;

        $this->validateCommentInput($body);

        $comment = $this->storeComment($commentable, $this->serializeRichContent($body));

        $this->storeAttachments($comment, $this->attachments);

        $this->reset(['body', 'attachments']);
        $this->form->fill([
            'body' => '',
        ]);

        $this->sendMentionNotifications(
            $body,
            'You are being mentioned in a comment on task by '.Auth::user()->name,
            $commentable,
        );

        $this->dispatch('comment-created');
    }

    public function startReply(int $commentId): void
    {
        $comment = $this->findComment($commentId);

        Gate::authorize('reply', $comment);

        $this->replyingTo = $comment->id;
        $this->replyBody = '';
        $this->replyAttachments = [];
        $this->replyForm->fill([
            'body' => '',
        ]);
    }

    public function addReply(): void
    {
        $commentable = $this->getCommentable();
        $parent = $this->findComment((int) $this->replyingTo);

        if (! $commentable instanceof Commentable) {
            return;
        }

        Gate::authorize('reply', $parent);

        $data = $this->replyForm->getState();
        $body = $data['body'] ?? $this->replyBody;

        $this->validateReplyInput($body);

        $comment = $this->storeComment($commentable, $this->serializeRichContent($body), $parent);

        $this->storeAttachments($comment, $this->replyAttachments);

        $this->sendMentionNotifications(
            $body,
            Auth::user()->name.' reply on a comment and mentioned you in it.',
            $commentable,
        );

        $this->reset(['replyingTo', 'replyBody', 'replyAttachments']);
        $this->replyForm->fill([
            'body' => '',
        ]);
    }

    public function startEditing(int $commentId): void
    {
        $comment = $this->findComment($commentId);

        Gate::authorize('update', $comment);

        $this->editingCommentId = $comment->id;
        $this->editingBody = $comment->body;
        $this->editForm->fill([
            'body' => $this->editingBody,
        ]);
    }

    public function updateComment(): void
    {
        $comment = $this->findComment((int) $this->editingCommentId);

        Gate::authorize('update', $comment);

        $this->validate([
            'editingBody' => ['required', 'string', 'max:10000'],
        ]);

        $data = $this->editForm->getState();
        $body = $data['body'] ?? $this->editingBody;

        $comment->update([
            'body' => $body,
        ]);

        $this->sendMentionNotifications(
            $body,
            Auth::user()->name.' updated a comment and mentioned you in it.',
            $comment->commentable,
        );

        $this->reset(['editingCommentId', 'editingBody']);
    }

    public function deleteComment(int $commentId): void
    {
        $comment = $this->findComment($commentId);

        Gate::authorize('delete', $comment);

        $comment->delete();
    }

    public function toggleReaction(int $commentId, string $reaction): void
    {
        $comment = $this->findComment($commentId);

        Gate::authorize('react', $comment);

        if (! in_array($reaction, config('comments.allowed_reactions'), true)) {
            return;
        }

        $existingReaction = $comment->reactions()
            ->where('user_id', Auth::id())
            ->where('reaction', $reaction)
            ->first();

        if ($existingReaction) {
            $existingReaction->delete();

            return;
        }

        $comment->reactions()->create([
            'user_id' => Auth::id(),
            'reaction' => $reaction,
        ]);
    }

    public function render(): View
    {
        return view('livewire.comment-thread', [
            'comments' => $this->getComments(),
            'allowedReactions' => config('comments.allowed_reactions'),
            'mentionableUsers' => $this->getMentionableUsers(),
            'pollInterval' => config('comments.poll_interval'),
            'renderMentionProviders' => $this->getRenderMentionProviders(),
        ]);
    }

    private function validateCommentInput(mixed $body): void
    {
        $this->validate([
            'attachments.*' => ['file', 'max:'.config('comments.attachments.max_size')],
        ]);

        if ($this->richContentIsBlank($body) && empty($this->attachments)) {
            throw ValidationException::withMessages([
                'data.body' => 'Add a comment or an attachment.',
            ]);
        }
    }

    private function validateReplyInput(mixed $body): void
    {
        $this->validate([
            'replyAttachments.*' => ['file', 'max:'.config('comments.attachments.max_size')],
        ]);

        if ($this->richContentIsBlank($body) && empty($this->replyAttachments)) {
            throw ValidationException::withMessages([
                'replyData.body' => 'Add a reply or an attachment.',
            ]);
        }
    }

    private function storeComment(Commentable $commentable, string $body, ?Comment $parent = null): Comment
    {
        return $commentable->comments()->create([
            'account_id' => $commentable->getCommentAccountId(),
            'user_id' => Auth::id(),
            'parent_id' => $parent?->id,
            'body' => $body,
            'body_format' => $this->bodyFormat,
        ]);
    }

    private function serializeRichContent(mixed $body): string
    {
        if (is_array($body)) {
            return json_encode($body, JSON_THROW_ON_ERROR);
        }

        return (string) $body;
    }

    private function richContentIsBlank(mixed $body): bool
    {
        if (is_array($body)) {
            return blank($this->richContentToPlainText($body));
        }

        return blank($body);
    }

    /**
     * @param  array<string, mixed>  $content
     */
    private function richContentToPlainText(array $content): string
    {
        $text = '';

        foreach ($content['content'] ?? [] as $node) {
            if (! is_array($node)) {
                continue;
            }

            $text .= $this->richContentToPlainText($node);
            $text .= ' ';
        }

        if (isset($content['text']) && is_string($content['text'])) {
            $text .= $content['text'].' ';
        }

        if (($content['type'] ?? null) === 'mention') {
            $text .= ($content['attrs']['label'] ?? $content['attrs']['id'] ?? '').' ';
        }

        return trim($text);
    }

    /**
     * @param  array<int, mixed>  $attachments
     */
    private function storeAttachments(Comment $comment, array $attachments): void
    {
        $disk = config('comments.attachments.disk');
        $directory = trim(config('comments.attachments.directory'), '/').'/'.$comment->account_id;

        foreach ($attachments as $attachment) {
            $path = $attachment->store($directory, $disk);

            Storage::disk($disk)->setVisibility($path, config('comments.attachments.visibility'));

            $comment->attachments()->create([
                'disk' => $disk,
                'path' => $path,
                'original_name' => $attachment->getClientOriginalName(),
                'mime_type' => $attachment->getMimeType(),
                'size' => $attachment->getSize() ?: 0,
            ]);
        }
    }

    private function getCommentable(): ?Model
    {
        return once(function () {
            if (! $this->commentableType || ! $this->commentableId || ! is_a($this->commentableType, Model::class, true)) {
                return null;
            }

            $commentable = $this->commentableType::query()->find($this->commentableId);

            if (! $commentable instanceof Commentable) {
                return null;
            }

            return $commentable;
        });
    }

    private function findComment(int $commentId): Comment
    {
        $commentable = $this->getCommentable();

        abort_unless($commentable instanceof Commentable, 404);

        return $commentable->comments()->whereKey($commentId)->firstOrFail();
    }

    /**
     * @return Collection<int, Comment>
     */
    private function getComments(): Collection
    {
        $commentable = $this->getCommentable();

        if (! $commentable instanceof Commentable || Gate::denies('create', [Comment::class, $commentable])) {
            return new Collection;
        }

        return $commentable->rootComments()
            ->withNestedReplies()
            ->get();
    }

    /**
     * @return array<int, string>
     */
    private function getMentionableUsers(): array
    {
        $commentable = $this->getCommentable();

        if (! $commentable instanceof Commentable) {
            return [];
        }

        return $this->usersInAccountQuery($commentable)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    /**
     * @return array<int, MentionProvider>
     */
    private function getCommentMentionProviders(): array
    {
        return [
            MentionProvider::make('@')
                ->getSearchResultsUsing(fn (string $search): array => $this->searchMentionableUsers($search))
                ->getLabelsUsing(fn (array $ids): array => User::query()
                    ->whereIn('id', $ids)
                    ->pluck('name', 'id')
                    ->all()),
        ];
    }

    /**
     * @return array<int, MentionProvider>
     */
    private function getRenderMentionProviders(): array
    {
        return [
            MentionProvider::make('@')
                ->getLabelsUsing(fn (array $ids): array => User::query()
                    ->whereIn('id', $ids)
                    ->pluck('name', 'id')
                    ->all()),
        ];
    }

    private function sendMentionNotifications(string $body, string $notificationBody, Model $commentable): void
    {
        $mentionedUsers = app(MentionParser::class)->extract($body);

        if (empty($mentionedUsers)) {
            return;
        }

        Notification::make()
            ->success()
            ->title('You were mentioned in a comment')
            ->body($notificationBody)
            ->actions([
                Action::make('viewTask')
                    ->url(EditTask::getUrl(['tenant' => Filament::getTenant(), 'record' => $commentable])),
            ])
            ->sendToDatabase($mentionedUsers);
    }

    private function usersInAccountQuery(Commentable $commentable): Builder
    {
        return User::query()
            ->where(function ($query) use ($commentable) {
                $query
                    ->whereHas('accounts', fn ($q) => $q->whereKey($commentable->getCommentAccountId()))
                    ->orWhereHas('ownedAccounts', fn ($q) => $q->whereKey($commentable->getCommentAccountId()));
            });
    }

    /**
     * @return array<int, string>
     */
    private function searchMentionableUsers(string $search): array
    {
        $commentable = $this->getCommentable();

        if (! $commentable instanceof Commentable) {
            return [];
        }

        return $this->usersInAccountQuery($commentable)
            ->when($search !== '', fn ($query) => $query->where('name', 'like', "%{$search}%"))
            ->orderBy('name')
            ->limit(10)
            ->pluck('name', 'id')
            ->all();
    }
}
