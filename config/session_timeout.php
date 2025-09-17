<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Session Timeout Configuration
    |--------------------------------------------------------------------------
    |
    | This file centralizes all session timeout configuration for the
    | application. It handles JWT tokens, session management, and provides
    | a unified fallback strategy.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | JWT Token TTL (Time To Live)
    |--------------------------------------------------------------------------
    |
    | Specifies the length of time (in minutes) that JWT tokens will be valid.
    | This is the centralized configuration that replaces all other timeout sources.
    |
    | Configuration:
    | - Environment (.env SESSION_LIFETIME) - Primary source (Laravel standard)
    | - Hardcoded default (120 minutes) - Fallback if .env not set
    |
    */

    'ttl' => (int) env('SESSION_LIFETIME', 120),

    /*
    |--------------------------------------------------------------------------
    | Session Timeout in Seconds
    |--------------------------------------------------------------------------
    |
    | Same as TTL but converted to seconds for components that need it.
    |
    */

    'ttl_seconds' => (int) env('SESSION_LIFETIME', 120) * 60,

    /*
    |--------------------------------------------------------------------------
    | Token Expires In (Seconds)
    |--------------------------------------------------------------------------
    |
    | Specifies how long (in seconds) the expires_in value should be returned
    | in API responses. This is independent from SESSION_LIFETIME and allows
    | for different timeout behaviors if needed.
    |
    | Configuration:
    | - Environment (.env EXPIRES_IN) - Primary source
    | - Hardcoded default (900 seconds = 15 minutes) - Fallback
    |
    */

    'expires_in' => (int) env('EXPIRES_IN', 900),

];
