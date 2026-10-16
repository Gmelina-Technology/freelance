<?php

return [
    'allowed_reactions' => ['👍', '❤️', '😂', '😮', '😢', '🤔'],

    'attachments' => [
        'disk' => env('COMMENTS_ATTACHMENTS_DISK', 'local'),
        'directory' => env('COMMENTS_ATTACHMENTS_DIRECTORY', 'comments'),
        'visibility' => env('COMMENTS_ATTACHMENTS_VISIBILITY', 'private'),
        'max_size' => 10240,
        'allowed_extensions' => [
            'jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf',
            'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx',
            'odt', 'ods', 'odp', 'txt', 'csv', 'zip',
        ],
        'allowed_mimetypes' => [
            'image/jpeg', 'image/png', 'image/gif', 'image/webp', 'application/pdf',
            'application/msword', 'application/vnd.ms-office', 'application/x-ole-storage', 'application/CDFV2',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.ms-powerpoint', 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'application/vnd.oasis.opendocument.text', 'application/vnd.oasis.opendocument.spreadsheet', 'application/vnd.oasis.opendocument.presentation',
            'text/plain', 'text/csv', 'application/csv', 'application/zip', 'application/x-zip-compressed',
        ],
    ],

    'poll_interval' => env('COMMENTS_POLL_INTERVAL', '30s'),

    'allow_self_reply' => true,
];
