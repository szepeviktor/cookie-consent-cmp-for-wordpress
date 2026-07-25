// Requires ECMAScript 5 (ES5) syntax support.
(function () {
    'use strict';

    var MAX_ATTEMPTS = 40;
    var RETRY_DELAY_MS = 250;
    var lastServiceConsents = Object.create(null);

    function createEvent(name, detail) {
        var event;

        if (typeof window.CustomEvent === 'function') {
            return new CustomEvent(name, {detail: detail});
        }

        event = document.createEvent('Event');
        event.initEvent(name, false, false);
        event.detail = detail;

        return event;
    }

    function reportError(message) {
        if (window.console && typeof window.console.error === 'function') {
            window.console.error('Cookie Consent CMP: ' + message);
        }

        document.dispatchEvent(createEvent('cookie_consent_cmp_sync_error', {
            message: message
        }));
    }

    function defineConsentType() {
        if (typeof window.wp_consent_type === 'undefined' || window.wp_consent_type === '') {
            window.wp_consent_type = 'optin';
        }

        document.dispatchEvent(createEvent('wp_consent_type_defined'));
    }

    function setCategoryConsent(category, consented) {
        var consentValue = consented ? 'allow' : 'deny';
        var currentValue;

        if (typeof window.wp_set_consent !== 'function') {
            return false;
        }

        if (window.consent_api
            && typeof window.consent_api.cookie_prefix === 'string'
            && typeof window.consent_api_get_cookie === 'function'
        ) {
            currentValue = window.consent_api_get_cookie(
                window.consent_api.cookie_prefix + '_' + category
            );

            if (currentValue === consentValue) {
                return true;
            }
        }

        window.wp_set_consent(category, consentValue);

        return true;
    }

    function setServiceConsent(service, consented) {
        var currentConsent;

        if (typeof window.wp_set_service_consent !== 'function') {
            return;
        }

        if (Object.prototype.hasOwnProperty.call(lastServiceConsents, service)
            && lastServiceConsents[service] === consented
        ) {
            return;
        }

        if (typeof window.wp_has_service_consent === 'function') {
            currentConsent = !!window.wp_has_service_consent(service);

            if (currentConsent === consented) {
                lastServiceConsents[service] = consented;
                return;
            }
        }

        window.wp_set_service_consent(service, consented);
        lastServiceConsents[service] = consented;
    }

    function configuredServices(manager) {
        if (!manager || !manager.config || !manager.config.services) {
            return [];
        }

        return manager.config.services;
    }

    function hasCategoryConsent(manager, category) {
        var services = configuredServices(manager);
        var index;
        var service;

        if (category === 'functional') {
            return true;
        }

        if (!manager.confirmed) {
            return false;
        }

        for (index = 0; index < services.length; index++) {
            service = services[index];

            if (service.wpConsentCategory === category) {
                return !!(manager.consents && manager.consents[service.name]);
            }
        }

        return false;
    }

    function syncServices(manager) {
        var services = configuredServices(manager);
        var index;
        var service;
        var consented;

        for (index = 0; index < services.length; index++) {
            service = services[index];

            if (!service.name || service.required || service.wpConsentCategory) {
                continue;
            }

            consented = !!(manager.confirmed
                && manager.consents
                && manager.consents[service.name]);
            setServiceConsent(service.name, consented);
        }
    }

    function syncManager(manager) {
        var success = true;

        if (!manager) {
            return false;
        }

        success = setCategoryConsent('functional', hasCategoryConsent(manager, 'functional')) && success;
        success = setCategoryConsent('preferences', hasCategoryConsent(manager, 'preferences')) && success;
        success = setCategoryConsent(
            'statistics-anonymous',
            hasCategoryConsent(manager, 'statistics-anonymous')
        ) && success;
        success = setCategoryConsent('statistics', hasCategoryConsent(manager, 'statistics')) && success;
        success = setCategoryConsent('marketing', hasCategoryConsent(manager, 'marketing')) && success;
        syncServices(manager);

        if (!success) {
            reportError('WP Consent API JavaScript did not become available.');
        }

        return success;
    }

    function bindManager(attempt) {
        var manager;
        var watcher;

        if (!window.klaro || typeof window.klaro.getManager !== 'function') {
            if (attempt < MAX_ATTEMPTS) {
                window.setTimeout(function () {
                    bindManager(attempt + 1);
                }, RETRY_DELAY_MS);
            } else {
                reportError('Klaro did not become available in time.');
            }

            return;
        }

        try {
            manager = window.klaro.getManager();
        } catch (error) {
            reportError('Klaro manager could not be accessed.');
            return;
        }

        if (!manager) {
            if (attempt < MAX_ATTEMPTS) {
                window.setTimeout(function () {
                    bindManager(attempt + 1);
                }, RETRY_DELAY_MS);
            } else {
                reportError('Klaro manager did not become available in time.');
            }

            return;
        }

        watcher = {
            update: function (currentManager, type) {
                if (type === 'saveConsents') {
                    syncManager(currentManager);
                }
            }
        };

        manager.watch(watcher);
        syncManager(manager);
    }

    defineConsentType();
    bindManager(0);
})();
