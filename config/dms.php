<?php

return [
    'clamav' => [
        'socket' => env('CLAMAV_SOCKET', '/var/run/clamav/clamd.ctl'),
        'cli' => env('CLAMAV_CLI', null),
        'timeout' => (int) env('CLAMAV_TIMEOUT', 10),
    ],
    'quarantine_path' => env('DMS_QUARANTINE_PATH', storage_path('app/quarantine')),
    'max_zip_uncompressed_bytes' => (int) env('DMS_MAX_ZIP_BYTES', 100 * 1024 * 1024), // 100 MB
    'max_zip_ratio' => (float) env('DMS_MAX_ZIP_RATIO', 100.0), // 100:1
    'max_zip_entries' => (int) env('DMS_MAX_ZIP_ENTRIES', 1000),
];
