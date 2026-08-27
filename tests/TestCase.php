<?php

namespace Tests;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    public function createApplication(): Application
    {
        $environment = [
            'APP_ENV' => 'testing',
            'CACHE_STORE' => 'array',
            'DB_CONNECTION' => 'sqlite',
            'DB_DATABASE' => ':memory:',
            'MAIL_MAILER' => 'array',
            'QUEUE_CONNECTION' => 'sync',
            'SESSION_DRIVER' => 'array',
            'TELEGRAM_BOT_TOKEN' => '',
            'TELEGRAM_OWNER_CHAT_ID' => '',
            'TELEGRAM_WORK_CHAT_ID' => '',
            'TELEGRAM_WORK_TOPIC_ID' => '',
            'TELEGRAM_WEBHOOK_SECRET' => '',
            'TELEGRAM_ALLOWED_USER_IDS' => '',
            'DISCORD_BOT_TOKEN' => '',
            'DISCORD_FORUM_WEB' => '',
            'DISCORD_FORUM_TELEGRAM_BOT' => '',
            'DISCORD_FORUM_PYTHON' => '',
            'DISCORD_FORUM_DESIGN' => '',
            'DISCORD_FORUM_DEVELOPMENT' => '',
            'DISCORD_FORUM_3D' => '',
            'DISCORD_FORUM_OTHER' => '',
            'WORK_ITEM_CHANNELS_INCLUDE_PRICE' => 'false',
        ];

        foreach ($environment as $key => $value) {
            putenv("{$key}={$value}");
            $_ENV[$key] = $_SERVER[$key] = $value;
        }

        return parent::createApplication();
    }
}
