<?php

namespace App\Models;

use Database\Factories\CommentAttachmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommentAttachment extends Model
{
    /** @use HasFactory<CommentAttachmentFactory> */
    use HasFactory;

    protected $fillable = [
        'comment_id',
        'disk',
        'path',
        'original_name',
        'mime_type',
        'size',
    ];

    public function comment(): BelongsTo
    {
        return $this->belongsTo(Comment::class);
    }
}
