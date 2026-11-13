<?php

return [
    'clamav' => [
        'socket' => env('CLAMAV_SOCKET', '/var/run/clamav/clamd.ctl'),
        'cli' => env('CLAMAV_CLI', null),
        'timeout' => (int) env('CLAMAV_TIMEOUT', 10),
    ],
    'quarantine_path' => env('DMS_QUARANTINE_PATH', storage_path('app/quarantine')),
    'quarantine_staging_path' => env('DMS_QUARANTINE_STAGING_PATH', storage_path('app/dms_quarantine')),
    'quarantine_retention_days' => (int) env('DMS_QUARANTINE_RETENTION_DAYS', 30),
    'max_zip_uncompressed_bytes' => (int) env('DMS_MAX_ZIP_BYTES', 100 * 1024 * 1024), // 100 MB
    'max_zip_ratio' => (float) env('DMS_MAX_ZIP_RATIO', 100.0), // 100:1
    'max_zip_entries' => (int) env('DMS_MAX_ZIP_ENTRIES', 1000),
];
