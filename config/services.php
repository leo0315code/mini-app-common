<?php

return [

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

    /*
    |--------------------------------------------------------------------------
    | 微信小程序（WeChat Mini Program）
    |--------------------------------------------------------------------------
    |
    | 通用后台通过 code2session 换取 openid / unionid。配置驱动：
    | 换一个小程序只需修改下方对应的 .env 变量，无需改代码。
    |
    */

    'mini_program' => [
        'app_id' => env('MINI_PROGRAM_APP_ID'),
        'secret' => env('MINI_PROGRAM_SECRET'),
        // 订阅消息模板 ID：优先 .env，未配置时可经后台「系统配置」页保存
        // （存 settings 表，由 SettingConfigLoader 在启动时覆盖本数组）
        'feedback_template_id' => env('MINI_PROGRAM_FEEDBACK_TEMPLATE_ID'),
        'announcement_template_id' => env('MINI_PROGRAM_ANNOUNCEMENT_TEMPLATE_ID'),
    ],

];
