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
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'gemini' => [
        'key' => env('GEMINI_API_KEY', env('GOOGLE_AI_API_KEY')),
        'api_key' => env('GEMINI_API_KEY', env('GOOGLE_AI_API_KEY')),
        'model' => env('GEMINI_MODEL', 'gemini-3.5-flash-lite'),
        'command_model' => env('GEMINI_COMMAND_MODEL', 'gemini-2.5-flash'),
        'thinking_model' => env('GEMINI_THINKING_MODEL', 'gemini-3.5-flash'),
        'tts_model' => env('GEMINI_TTS_MODEL', 'gemini-3.8-flash-tts'),
        'verify_ssl' => env('GEMINI_VERIFY_SSL', true),
    ],

    'openai' => [
        'key' => env('OPENAI_API_KEY'),
        'model' => env('OPENAI_MODEL', 'gpt-4o-mini'),
    ],

    'shopify' => [
        'webhook_secret' => env('SHOPIFY_WEBHOOK_SECRET'),
        'domain' => env('SHOPIFY_DOMAIN'),
        'access_token' => env('SHOPIFY_ACCESS_TOKEN'),
    ],

    'whatsapp' => [
        'webhook_token' => env('ECOMMERCE_WHATSAPP_WEBHOOK_TOKEN'),
        'app_secret' => env('WHATSAPP_APP_SECRET'),
        'verify_token' => env('WHATSAPP_VERIFY_TOKEN', 'laleen_whatsapp_verify_2026'),
        'api_url' => env('WHATSAPP_API_URL'),
        'api_token' => env('WHATSAPP_API_TOKEN', env('WHATSAPP_CLOUD_API_TOKEN')),
        'token' => env('WHATSAPP_CLOUD_API_TOKEN', env('WHATSAPP_API_TOKEN')),
        'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),
        'template_name' => env('WHATSAPP_TEMPLATE_NAME'),
        'template_language' => env('WHATSAPP_TEMPLATE_LANGUAGE', 'en_US'),
    ],

    'ecommerce' => [
        'pkr_per_qar' => (float) env('ECOMMERCE_PKR_PER_QAR', 76.5),
    ],

];
