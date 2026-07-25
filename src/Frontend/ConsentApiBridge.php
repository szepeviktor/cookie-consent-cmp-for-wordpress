<?php

declare(strict_types=1);

namespace SzepeViktor\CookieConsentCmp\Frontend;

final class ConsentApiBridge
{
    private string $plugin_basename;

    public function __construct(string $plugin_basename)
    {
        $this->plugin_basename = $plugin_basename;
    }

    public function register(): void
    {
        add_filter('wp_consent_api_registered_' . $this->plugin_basename, '__return_true');
        add_filter('wp_get_consent_type', [$this, 'filter_consent_type']);
        add_filter('wp_consent_categories', [$this, 'filter_categories']);
    }

    public function filter_consent_type(): string
    {
        return 'optin';
    }

    /**
     * @param mixed $categories
     * @return mixed
     */
    public function filter_categories($categories)
    {
        $allowed = [
            'functional' => true,
            'statistics' => true,
            'marketing' => true,
        ];

        if (! is_array($categories)) {
            return $categories;
        }

        if (array_values($categories) === $categories) {
            return array_values(array_intersect($categories, array_keys($allowed)));
        }

        return array_intersect_key($categories, $allowed);
    }

    public function is_api_available(): bool
    {
        return function_exists('wp_has_consent');
    }

    public function inline_script(): string
    {
        return <<<'JS'
(function () {
    function dispatchConsentTypeDefined() {
        if (typeof window.dispatchEvent === 'function') {
            if (typeof window.Event === 'function') {
                window.dispatchEvent(new Event('wp_consent_type_defined'));
                return;
            }

            if (document.createEvent) {
                var event = document.createEvent('Event');
                event.initEvent('wp_consent_type_defined', true, true);
                window.dispatchEvent(event);
            }
        }
    }

    function setConsent(category, allowed) {
        if (typeof window.wp_set_consent === 'function') {
            window.wp_set_consent(category, allowed ? 'allow' : 'deny');
        }
    }

    function hasPurposeConsent(manager, purpose) {
        var services;
        var matched = false;
        var index;
        var service;

        if (!manager.confirmed || !manager.config || !manager.config.services) {
            return false;
        }

        services = manager.config.services;

        for (index = 0; index < services.length; index++) {
            service = services[index];

            if (!service.purposes || service.purposes.indexOf(purpose) === -1 || service.required) {
                continue;
            }

            matched = true;

            if (!manager.consents || !manager.consents[service.name]) {
                return false;
            }
        }

        return matched;
    }

    function applyFromManager(manager) {
        if (!manager) {
            return false;
        }

        setConsent('functional', true);
        setConsent('statistics', hasPurposeConsent(manager, 'statistics'));
        setConsent('marketing', hasPurposeConsent(manager, 'marketing'));

        return true;
    }

    function bindManager(attempt) {
        var manager;
        var watcher;

        if (!window.klaro || typeof window.klaro.getManager !== 'function') {
            if (attempt < 40) {
                window.setTimeout(function () {
                    bindManager(attempt + 1);
                }, 250);
            }
            return;
        }

        try {
            manager = window.klaro.getManager();
        } catch (error) {
            return;
        }

        if (!manager) {
            if (attempt < 40) {
                window.setTimeout(function () {
                    bindManager(attempt + 1);
                }, 250);
            }
            return;
        }

        watcher = {
            update: function (currentManager, type) {
                if (type === 'applyConsents' || type === 'saveConsents' || type === 'consents') {
                    applyFromManager(currentManager);
                }
            }
        };

        manager.watch(watcher);
        applyFromManager(manager);
    }

    window.wp_consent_type = 'optin';
    dispatchConsentTypeDefined();
    bindManager(0);
})();
JS;
    }
}
