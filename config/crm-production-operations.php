<?php

return [
    'health' => [
        'scheduler_stale_minutes' => (int) env('CRM_SCHEDULER_STALE_MINUTES', 3),
        'queue_stale_minutes' => (int) env('CRM_QUEUE_STALE_MINUTES', 5),
        'email_sync_stale_minutes' => (int) env('CRM_EMAIL_SYNC_STALE_MINUTES', 15),
        'backup_stale_hours' => (int) env('CRM_BACKUP_STALE_HOURS', 26),
    ],

    'backup' => [
        'daily_database_time' => env('CRM_DAILY_DATABASE_BACKUP_TIME', '02:00'),
        'weekly_full_time' => env('CRM_WEEKLY_FULL_BACKUP_TIME', '03:00'),
        'weekly_full_day' => (int) env('CRM_WEEKLY_FULL_BACKUP_DAY', 0),
        'keep_daily_days' => (int) env('CRM_BACKUP_KEEP_DAILY_DAYS', 14),
        'keep_weekly_weeks' => (int) env('CRM_BACKUP_KEEP_WEEKLY_WEEKS', 8),
        'keep_monthly_months' => (int) env('CRM_BACKUP_KEEP_MONTHLY_MONTHS', 12),
        'keep_yearly_years' => (int) env('CRM_BACKUP_KEEP_YEARLY_YEARS', 7),
        'object_disk' => env('CRM_BACKUP_OBJECT_DISK'),
        'object_prefix' => trim((string) env('CRM_BACKUP_OBJECT_PREFIX', 'crm-backups'), '/'),
        'object_store' => [
            'driver' => 's3',
            'key' => env('CRM_BACKUP_AWS_ACCESS_KEY_ID'),
            'secret' => env('CRM_BACKUP_AWS_SECRET_ACCESS_KEY'),
            'region' => env('CRM_BACKUP_AWS_DEFAULT_REGION'),
            'bucket' => env('CRM_BACKUP_AWS_BUCKET'),
            'url' => env('CRM_BACKUP_AWS_URL'),
            'endpoint' => env('CRM_BACKUP_AWS_ENDPOINT'),
            'use_path_style_endpoint' => (bool) env('CRM_BACKUP_AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => true,
        ],
    ],

    'attachments' => [
        'disk' => env('CRM_ATTACHMENT_DISK', 'local'),
        'max_kilobytes' => (int) env('CRM_UPLOAD_MAX_KB', 20480),
        'allowed_extensions' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env(
                'CRM_UPLOAD_ALLOWED_EXTENSIONS',
                'pdf,jpg,jpeg,png,webp,gif,txt,csv,doc,docx,xls,xlsx,zip,eml'
            ))
        ))),
    ],

    'antivirus' => [
        'enabled' => filter_var(env('CRM_CLAMAV_ENABLED', false), FILTER_VALIDATE_BOOL),
        'binary' => env('CRM_CLAMAV_BINARY', PHP_OS_FAMILY === 'Windows' ? 'clamscan.exe' : 'clamscan'),
        'timeout_seconds' => (int) env('CRM_CLAMAV_TIMEOUT_SECONDS', 60),
        'fail_closed' => filter_var(env('CRM_CLAMAV_FAIL_CLOSED', true), FILTER_VALIDATE_BOOL),
    ],

    'production' => [
        'force_https' => filter_var(env('CRM_FORCE_HTTPS', false), FILTER_VALIDATE_BOOL),
    ],
];
