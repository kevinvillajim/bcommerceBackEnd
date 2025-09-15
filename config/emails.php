<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Email Templates Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains configuration for email templates, themes, and
    | customization options that can be modified without touching code.
    |
    */

    'themes' => [
        'default' => [
            'primary_color' => '#007bff',
            'secondary_color' => '#0056b3',
            'success_color' => '#28a745',
            'warning_color' => '#ffc107',
            'danger_color' => '#dc3545',
            'info_color' => '#17a2b8',
        ],
        'green' => [
            'primary_color' => '#28a745',
            'secondary_color' => '#198754',
            'success_color' => '#20c997',
            'warning_color' => '#ffc107',
            'danger_color' => '#dc3545',
            'info_color' => '#17a2b8',
        ],
        'purple' => [
            'primary_color' => '#6f42c1',
            'secondary_color' => '#5a2d91',
            'success_color' => '#28a745',
            'warning_color' => '#ffc107',
            'danger_color' => '#dc3545',
            'info_color' => '#17a2b8',
        ],
    ],

    'templates' => [
        'verification' => [
            'enabled' => true,
            'theme' => 'green',
            'expires_hours' => 24,
            'features' => [
                'show_security_tips' => true,
                'show_benefits' => true,
                'show_troubleshooting' => true,
            ],
        ],
        'password_reset' => [
            'enabled' => true,
            'theme' => 'danger',
            'expires_hours' => 1,
            'features' => [
                'show_security_warnings' => true,
                'show_help_links' => true,
            ],
        ],
        'welcome' => [
            'enabled' => true,
            'theme' => 'default',
            'features' => [
                'show_getting_started' => true,
                'show_features_overview' => true,
                'show_support_info' => true,
            ],
        ],
        'notification' => [
            'enabled' => true,
            'theme' => 'default',
            'types' => [
                'notification' => '#007bff',
                'announcement' => '#28a745',
                'warning' => '#ffc107',
                'urgent' => '#dc3545',
                'info' => '#17a2b8',
            ],
        ],
        'order_confirmation' => [
            'enabled' => true,
            'theme' => 'success',
            'features' => [
                'show_order_details' => true,
                'show_tracking_info' => true,
                'show_support_info' => true,
            ],
        ],
    ],

    'content' => [
        'footer' => [
            'show_social_links' => true,
            'show_unsubscribe' => false, // Will be implemented later
            'support_email' => env('MAIL_SUPPORT_EMAIL', 'soporte@bcommerce.app'),
            'website_url' => env('APP_URL', 'https://bcommerce.app'),
        ],
        'branding' => [
            'logo_url' => null, // Path to logo image
            'company_address' => null, // Physical address
            'show_powered_by' => false,
        ],
    ],

    'features' => [
        'queue_emails' => env('QUEUE_EMAILS', true),
        'track_opens' => false, // Email open tracking (not implemented)
        'track_clicks' => false, // Click tracking (not implemented)
        'auto_retry' => true,
        'retry_attempts' => 3,
    ],

    'localization' => [
        'default_locale' => 'es',
        'available_locales' => ['es', 'en'],
        'fallback_locale' => 'es',
    ],
];
