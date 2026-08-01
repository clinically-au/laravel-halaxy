<?php

declare(strict_types=1);
use Clinically\Halaxy\Webhooks\HalaxySignatureValidator;
use Clinically\Halaxy\Webhooks\ProcessHalaxyWebhookJob;
use Spatie\WebhookClient\Models\WebhookCall;
use Spatie\WebhookClient\WebhookProfile\ProcessEverythingWebhookProfile;

return [
    /*
    |--------------------------------------------------------------------------
    | Halaxy API Credentials
    |--------------------------------------------------------------------------
    |
    | Your Halaxy API client ID and secret. These are required to authenticate
    | with the Halaxy API. You can obtain these from your Halaxy developer
    | settings. See https://developers.halaxy.com/docs/authentication
    |
    */

    'client_id' => env('HALAXY_CLIENT_ID'),

    'client_secret' => env('HALAXY_CLIENT_SECRET'),

    /*
    |--------------------------------------------------------------------------
    | API Region
    |--------------------------------------------------------------------------
    |
    | The region determines which Halaxy API server to connect to.
    | - 'au': Australia and other countries (default)
    | - 'eu': European Union and United Kingdom
    |
    */

    'region' => env('HALAXY_REGION', 'au'),

    /*
    |--------------------------------------------------------------------------
    | Base URLs
    |--------------------------------------------------------------------------
    |
    | The base URLs for each Halaxy API region. These should not need to be
    | changed unless Halaxy updates their API endpoints.
    |
    */

    'base_urls' => [
        'au' => 'https://au-api.halaxy.com/main/',
        'eu' => 'https://eu-api.halaxy.com/main/',
    ],

    /*
    |--------------------------------------------------------------------------
    | User Agent
    |--------------------------------------------------------------------------
    |
    | Identifies your application to the Halaxy API. Halaxy recommends using
    | the format: "APP_VENDOR_NAME (APP_VENDOR_EMAIL)"
    |
    */

    'user_agent' => env('HALAXY_USER_AGENT', 'Clinically Halaxy SDK'),

    /*
    |--------------------------------------------------------------------------
    | HTTP Client Settings
    |--------------------------------------------------------------------------
    |
    | Configure the HTTP client behavior for API requests.
    |
    */

    'http' => [
        // Request timeout in seconds
        'timeout' => env('HALAXY_HTTP_TIMEOUT', 30),

        // Number of retry attempts for failed requests
        'retry_attempts' => env('HALAXY_HTTP_RETRY_ATTEMPTS', 3),

        // Delay between retry attempts in milliseconds
        'retry_delay' => env('HALAXY_HTTP_RETRY_DELAY', 100),
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Settings
    |--------------------------------------------------------------------------
    |
    | Configure caching for API responses and OAuth tokens.
    |
    */

    'cache' => [
        // Enable/disable response caching
        'enabled' => env('HALAXY_CACHE_ENABLED', true),

        // Cache store to use (null = default store)
        'store' => env('HALAXY_CACHE_STORE'),

        // Default cache TTL in seconds (5 minutes)
        'ttl' => env('HALAXY_CACHE_TTL', 300),

        // Cache key prefix
        'prefix' => 'halaxy',

        // Token cache TTL is calculated dynamically based on token expiry
        // This buffer (in seconds) is subtracted from the token expiry
        'token_buffer' => 60,
    ],

    /*
    |--------------------------------------------------------------------------
    | Webhook Settings
    |--------------------------------------------------------------------------
    |
    | Configure webhook handling using spatie/laravel-webhook-client.
    | Webhooks allow your application to receive real-time notifications
    | when events occur in Halaxy.
    |
    */

    'webhooks' => [
        // Webhooks are opt-in so installing the package never exposes an
        // unauthenticated endpoint. Enabling them also requires a secret.
        'enabled' => env('HALAXY_WEBHOOKS_ENABLED', false),

        // Auto-register webhook routes at the path below. Multi-tenant hosts
        // set this to false and call HalaxyWebhookRoutes::register() inside
        // their own tenant-prefixed route group.
        'register_routes' => env('HALAXY_WEBHOOK_ROUTES', true),

        // The secret/token used to verify incoming webhooks
        // This should match the "Authentication Header" configured in Halaxy
        'signing_secret' => env('HALAXY_WEBHOOK_SECRET'),

        // The header name containing the authentication token
        'signature_header_name' => 'Authorization',

        // Component classes for the spatie webhook-client entries. Override
        // these in a host app to substitute tenant-aware implementations
        // (e.g. a validator that reads the tenant's own secret).
        'signature_validator' => HalaxySignatureValidator::class,
        'webhook_profile' => ProcessEverythingWebhookProfile::class,
        'process_webhook_job' => ProcessHalaxyWebhookJob::class,
        'webhook_model' => WebhookCall::class,

        // The route path for receiving webhooks
        'path' => env('HALAXY_WEBHOOK_PATH', 'webhooks/halaxy'),

        // Delete webhook records after this many days
        'delete_after_days' => 30,

        // Queue connection for processing webhooks (null = default)
        'queue_connection' => env('HALAXY_WEBHOOK_QUEUE_CONNECTION'),

        // Queue name for processing webhooks (null = default)
        'queue' => env('HALAXY_WEBHOOK_QUEUE'),

        // Request headers to persist on the stored WebhookCall. Empty by
        // default: Halaxy's authentication header carries the shared secret,
        // so storing headers would persist that secret at rest. Opt in to
        // specific header names (e.g. ['Content-Type']) if needed — never '*'.
        'store_headers' => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | Pagination Settings
    |--------------------------------------------------------------------------
    |
    | Configure default pagination behavior for list operations.
    |
    */

    'pagination' => [
        // Default number of items per page
        'per_page' => env('HALAXY_PAGINATION_PER_PAGE', 50),

        // Maximum items per page
        'max_per_page' => 100,
    ],
];
