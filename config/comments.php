<?php

return [
    'allowed_reactions' => ['👍', '❤️', '😂', '😮', '😢', '🤔'],

    'attachments' => [
        'disk' => env('COMMENTS_ATTACHMENTS_DISK', 'local'),
        'directory' => env('COMMENTS_ATTACHMENTS_DIRECTORY', 'comments'),
        'visibility' => env('COMMENTS_ATTACHMENTS_VISIBILITY', 'private'),
        'max_size' => 10240,
    ],

    'poll_interval' => env('COMMENTS_POLL_INTERVAL', '5s'),

    'allow_self_reply' => true,
];
