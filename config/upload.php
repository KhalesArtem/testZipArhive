<?php

return [
    'chunk_size' => 5 * 1024 * 1024, // 5MB per chunk
    'max_file_size' => 2 * 1024 * 1024 * 1024, // 2GB max file size
    'temp_path' => storage_path('app/chunks'),
    'cleanup_after_hours' => 24, // Clean incomplete uploads after 24 hours
    'simultaneous_uploads' => 3,
    'max_chunk_retries' => 3,
    'allowed_extensions' => ['zip'],
    'queue_connection' => env('QUEUE_CONNECTION', 'database'),
];