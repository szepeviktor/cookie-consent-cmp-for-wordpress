<?php

declare(strict_types=1);

namespace SzepeViktor\CookieConsentCmp\Frontend;

final class ConsentApiBridge
{
    private const CONSENT_COOKIE_EXPIRATION_DAYS = 365;

    private string $plugin_basename;

    private bool $consent_type_conflict = false;

    public function __construct(string $plugin_basename)
    {
        $this->plugin_basename = $plugin_basename;
    }

    public function register(): void
    {
        add_filter('wp_consent_api_registered_' . $this->plugin_basename, '__return_true');
        add_filter('wp_get_consent_type', [$this, 'filter_consent_type'], PHP_INT_MAX);
        add_filter(
            'wp_consent_api_cookie_expiration',
            [$this, 'filter_cookie_expiration']
        );
    }

    public function filter_consent_type(string $consent_type = ''): string
    {
        if ($consent_type !== '') {
            $this->consent_type_conflict = true;

            return $consent_type;
        }

        return 'optin';
    }

    public function has_consent_type_conflict(): bool
    {
        if (function_exists('wp_get_consent_type')) {
            $consent_type = wp_get_consent_type();

            if ($consent_type !== 'optin') {
                $this->consent_type_conflict = true;
            }
        }

        return $this->consent_type_conflict;
    }

    public function is_api_available(): bool
    {
        return function_exists('wp_has_consent');
    }

    public function filter_cookie_expiration(): int
    {
        return self::CONSENT_COOKIE_EXPIRATION_DAYS;
    }

    /**
     * Register configured services before WP Consent API localizes its frontend data.
     *
     * @param array<int, array<string, mixed>> $services
     */
    public function register_services(array $services): void
    {
        if (! function_exists('wp_add_cookie_info')) {
            return;
        }

        $registered_cookies = function_exists('wp_get_cookie_info')
            ? wp_get_cookie_info()
            : [];

        foreach ($services as $service) {
            $name = isset($service['name']) && is_string($service['name'])
                ? $service['name']
                : '';
            $purposes = isset($service['purposes']) && is_array($service['purposes'])
                ? $service['purposes']
                : [];
            $category = isset($purposes[0]) && is_string($purposes[0])
                ? $purposes[0]
                : 'functional';
            $cookies = isset($service['wpConsentCookies']) && is_array($service['wpConsentCookies'])
                ? $service['wpConsentCookies']
                : [];
            $description = $this->service_description($service);

            if ($name === '') {
                continue;
            }

            foreach ($cookies as $cookie) {
                if (! is_array($cookie)
                    || ! isset($cookie['name'])
                    || ! is_string($cookie['name'])
                    || $cookie['name'] === ''
                    || isset($registered_cookies[$cookie['name']])
                ) {
                    continue;
                }

                wp_add_cookie_info(
                    $cookie['name'],
                    $name,
                    $category,
                    isset($cookie['expires']) && is_string($cookie['expires'])
                        ? $cookie['expires']
                        : __('Varies', 'cookie-consent-cmp'),
                    isset($cookie['function']) && is_string($cookie['function'])
                        ? $cookie['function']
                        : $description,
                    '',
                    false,
                    false,
                    isset($cookie['type']) && is_string($cookie['type'])
                        ? $cookie['type']
                        : 'HTTP',
                    isset($cookie['domain']) && is_string($cookie['domain'])
                        ? $cookie['domain']
                        : ''
                );
                $registered_cookies[$cookie['name']] = true;
            }
        }
    }

    /**
     * @param array<string, mixed> $service
     */
    private function service_description(array $service): string
    {
        if (isset($service['translations']) && is_array($service['translations'])) {
            foreach ($service['translations'] as $translation) {
                if (is_array($translation)
                    && isset($translation['description'])
                    && is_string($translation['description'])
                ) {
                    return $translation['description'];
                }
            }
        }

        return isset($service['title']) && is_string($service['title'])
            ? $service['title']
            : __('Configured consent service.', 'cookie-consent-cmp');
    }
}
