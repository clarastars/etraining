<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Training disclosure — Google Drive catalog
    |--------------------------------------------------------------------------
    |
    | Parent Drive folder whose direct child folders each become a RecordedCourse.
    | Share that folder with the Google service account (services.google.client_email),
    | then run:
    |
    |   php artisan recorded-courses:import-from-drive --team=<team-uuid>
    |
    | Videos are downloaded to a temp file and attached to Spatie media on the s3 disk.
    |
    */
    'parent_drive_folder_id' => env(
        'TRAINING_DISCLOSURE_DRIVE_PARENT_FOLDER_ID',
        '1CUt_3-kIYe7g73nGD5TDQ0BtSBGCGji-'
    ),

    'default_team_id' => env('TRAINING_DISCLOSURE_DEFAULT_TEAM_ID'),

    'unlock_delay_hours' => (int) env('TRAINING_DISCLOSURE_UNLOCK_DELAY_HOURS', 24),

    // ISO weekdays: 0=Sunday … 6=Saturday (matches recorded-course UI)
    'allowed_weekdays' => [0, 1, 2, 3, 4],
];
