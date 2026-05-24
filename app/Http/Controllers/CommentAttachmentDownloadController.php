<?php

namespace App\Http\Controllers;

use App\Models\CommentAttachment;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CommentAttachmentDownloadController extends Controller
{
    public function __invoke(CommentAttachment $commentAttachment): StreamedResponse|Response
    {
        $commentAttachment->loadMissing('comment');

        Gate::authorize('view', $commentAttachment->comment);

        if (! Storage::disk($commentAttachment->disk)->exists($commentAttachment->path)) {
            abort(404);
        }

        return Storage::disk($commentAttachment->disk)->download(
            $commentAttachment->path,
            $commentAttachment->original_name,
        );
    }
}
