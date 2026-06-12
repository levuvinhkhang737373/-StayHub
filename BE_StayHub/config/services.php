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

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'ai_service' => [
        'url' => rtrim(env('AI_SERVICE_URL', 'http://ai-service:8000'), '/'),
    ],

    'qdrant' => [
        'url' => rtrim(env('QDRANT_URL', 'http://qdrant:6333'), '/'),
        'face_collection' => env('QDRANT_FACE_COLLECTION', 'face_embeddings'),
        'face_threshold' => (float) env('FACEID_MATCH_THRESHOLD', 0.9),
    ],

    'vietqr' => [
        'bank_bin' => env('VIETQR_BANK_BIN', '970423'),
        'account_number' => env('SEPAY_ACCOUNT_NUMBER', '99928876789'),
        'account_name' => env('VIETQR_ACCOUNT_NAME', 'LE VU VINH KHANG'),
        'template' => env('VIETQR_TEMPLATE', 'VNnocPH'),
    ],

    'sepay' => [
        'webhook_token' => env('SEPAY_WEBHOOK_TOKEN'),
    ],

];


