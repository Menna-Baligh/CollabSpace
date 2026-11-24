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

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI', env('APP_URL').'/api/v1/auth/google/callback'),
    ],

    'frontend' => [
        'url' => env('FRONTEND_URL', 'http://localhost:3000'),
        'auth_callback_url' => env('FRONTEND_AUTH_CALLBACK_URL', env('FRONTEND_URL', 'http://localhost:3000').'/auth/callback'),
        'reset_password_url' => env('FRONTEND_RESET_PASSWORD_URL', env('FRONTEND_URL', 'http://localhost:3000').'/reset-password'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Jitsi Meet
    |--------------------------------------------------------------------------
    | JITSI_DOMAIN: The Jitsi server domain (default: public meet.jit.si).
    | JITSI_APP_ID / JITSI_SECRET: Required only for JWT-secured self-hosted
    | or JaaS (8x8.vc) deployments.
    */
    'jitsi' => [
        'domain' => env('JITSI_DOMAIN', 'meet.jit.si'),
        'app_id' => env('JITSI_APP_ID'),
        'secret' => env('JITSI_SECRET'),
    ],

    /*
    |--------------------------------------------------------------------------
    | OpenAI (Whisper Speech-to-Text)
    |--------------------------------------------------------------------------
    */
    'openai' => [
        'key' => env('OPENAI_API_KEY'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Groq (Free-Tier Whisper Speech-to-Text)
    |--------------------------------------------------------------------------
    */
    'groq' => [
        'api_key' => env('GROQ_API_KEY'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Google Gemini (AI Summarization)
    |--------------------------------------------------------------------------
    */
    'gemini' => [
        'api_key' => env('GEMINI_API_KEY'),
    ],

];
