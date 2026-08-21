<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    /*
     | 微信小程序说明：
     | 小程序 wx.request 的 origin 为 `https://servicewechat.com`，且每台真机遇到的
     | origin 不同，无法用固定域名白名单。因此默认 `*`。若改用 H5 嵌小程序或自有
     | Web 管理端调用，建议通过 MINI_PROGRAM_ALLOWED_ORIGINS 收紧白名单（逗号分隔）。
     */
    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => env('CORS_ALLOWED_ORIGINS')
        ? explode(',', env('CORS_ALLOWED_ORIGINS'))
        : ['*'],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => env('CORS_MAX_AGE', 0),

    'supports_credentials' => false,

];
