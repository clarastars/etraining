<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */



    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'masdr' => [
        'endpoint' => env('MASDR_ENDPOINT'),
        'client_id' => env('MASDR_CLIENT_ID'),
        'client_secret' => env('MASDR_CLIENT_SECRET'),
    ],

    'google' => [
        'project_id' => env('GOOGLE_DRIVE_PROJECT_ID'),
        'private_key_id' => env('GOOGLE_DRIVE_PRIVATE_KEY_ID'),
        'private_key' => env('GOOGLE_DRIVE_PRIVATE_KEY'),
        'client_email' => env('GOOGLE_DRIVE_CLIENT_EMAIL'),
        'client_id' => env('GOOGLE_DRIVE_CLIENT_ID'),
        'client_x509_cert_url' => env('GOOGLE_DRIVE_CLIENT_X509_CERT_URL'),
        'joining_dates_file_id' => env('GOOGLE_DRIVE_JOINING_DATES_FILE_ID', '1u0AzPSmpPI7MiKgZVIh7VX2_NyEYHMrs'),
        // Cloud Vision OCR for trainee identity cards (separate service account).
        'vision_identity_ocr' => env('GOOGLE_VISION_IDENTITY_OCR', false),
        'vision' => [
            'project_id' => env('GOOGLE_VISION_PROJECT_ID'),
            'private_key_id' => env('GOOGLE_VISION_PRIVATE_KEY_ID'),
            'private_key' => env('GOOGLE_VISION_PRIVATE_KEY'),
            'client_email' => env('GOOGLE_VISION_CLIENT_EMAIL'),
            'client_id' => env('GOOGLE_VISION_CLIENT_ID'),
            'client_x509_cert_url' => env('GOOGLE_VISION_CLIENT_X509_CERT_URL'),
        ],
    ],

    'http' => [
        'verify' => 'C:\xampp\php\extras\ssl\cacert.pem',
    ],

];
