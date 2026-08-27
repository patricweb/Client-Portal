<?php

return [

    'telegram' => [
        'bot_token' => env('TELEGRAM_BOT_TOKEN'),
        'owner_chat_id' => env('TELEGRAM_OWNER_CHAT_ID'),
        'work_chat_id' => env('TELEGRAM_WORK_CHAT_ID'),
        'work_topic_id' => env('TELEGRAM_WORK_TOPIC_ID'),
        'webhook_secret' => env('TELEGRAM_WEBHOOK_SECRET'),
        'allowed_user_ids' => array_values(array_filter(array_map('trim', explode(',', (string) env('TELEGRAM_ALLOWED_USER_IDS', ''))))),
    ],

    'discord' => [
        'bot_token' => env('DISCORD_BOT_TOKEN'),
        'work_forums' => [
            'web' => env('DISCORD_FORUM_WEB'),
            'telegram_bot' => env('DISCORD_FORUM_TELEGRAM_BOT'),
            'python' => env('DISCORD_FORUM_PYTHON'),
            'design' => env('DISCORD_FORUM_DESIGN'),
            'development' => env('DISCORD_FORUM_DEVELOPMENT'),
            'three_d' => env('DISCORD_FORUM_3D'),
            'other' => env('DISCORD_FORUM_OTHER'),
        ],
    ],

    'work_item_channels' => [
        'include_price' => env('WORK_ITEM_CHANNELS_INCLUDE_PRICE', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
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

];
