<?php

declare(strict_types=1);

use SzepeViktor\CookieConsentCmp\Frontend\ConsentApiBridge;

$GLOBALS['consent_api_test_filters'] = [];
$GLOBALS['consent_api_test_added_cookies'] = [];
$GLOBALS['consent_api_test_registered_cookies'] = [
    '_ga' => [
        'plugin_or_service' => 'existing-analytics-plugin',
    ],
];

function add_filter(string $hook, $callback): void
{
    $GLOBALS['consent_api_test_filters'][$hook] = $callback;
}

function wp_has_consent(): bool
{
    return false;
}

function wp_get_consent_type(): string
{
    return '';
}

function wp_get_cookie_info(): array
{
    return $GLOBALS['consent_api_test_registered_cookies'];
}

function wp_add_cookie_info(...$arguments): void
{
    $GLOBALS['consent_api_test_added_cookies'][] = $arguments;
}

function __($text): string
{
    return $text;
}

require dirname(__DIR__, 2) . '/src/Frontend/ConsentApiBridge.php';

function assert_same($expected, $actual, string $message): void
{
    if ($expected !== $actual) {
        fwrite(
            STDERR,
            sprintf(
                "%s\nExpected: %s\nActual: %s\n",
                $message,
                var_export($expected, true),
                var_export($actual, true)
            )
        );
        exit(1);
    }
}

$bridge = new ConsentApiBridge('cookie-consent-cmp/cookie-consent-cmp.php');
$bridge->register();

assert_same(
    365,
    $bridge->filter_cookie_expiration(),
    'Consent cookie expiration must match Klaro retention.'
);

$bridge->register_services([
    [
        'name' => 'analytics',
        'purposes' => ['statistics'],
        'cookies' => ['^_ga_.*'],
        'wpConsentCookies' => [
            [
                'name' => '_ga',
                'expires' => '2 years',
                'function' => 'Existing registration must win.',
                'type' => 'HTTP',
            ],
            [
                'name' => '_gid',
                'expires' => '24 hours',
                'function' => 'Distinguishes visitors.',
                'type' => 'HTTP',
            ],
        ],
        'translations' => [
            'en' => [
                'description' => 'Analytics service.',
            ],
        ],
    ],
]);

assert_same(
    1,
    count($GLOBALS['consent_api_test_added_cookies']),
    'Only unregistered exact cookie names may be added.'
);
assert_same(
    '_gid',
    $GLOBALS['consent_api_test_added_cookies'][0][0],
    'Regex patterns and duplicate cookie names must not be registered.'
);
assert_same(
    '24 hours',
    $GLOBALS['consent_api_test_added_cookies'][0][3],
    'Per-cookie expiry metadata must be preserved.'
);

fwrite(STDOUT, "ConsentApiBridge PHP integration checks passed.\n");
