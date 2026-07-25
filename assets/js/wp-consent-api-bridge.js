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
        if (typeof window.wp_set_consent !== 'function') {
            return false;
        }

        window.wp_set_consent(category, consented ? 'allow' : 'deny');

        return true;
    }

    function setServiceConsent(service, consented) {
        if (typeof window.wp_set_service_consent !== 'function') {
            return;
        }

        if (Object.prototype.hasOwnProperty.call(lastServiceConsents, service)
            && lastServiceConsents[service] === consented
        ) {
            return;
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

    function hasPurposeConsent(manager, purpose) {
        var services = configuredServices(manager);
        var matched = false;
        var index;
        var service;

        if (!manager.confirmed) {
            return false;
        }

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

    function syncServices(manager) {
        var services = configuredServices(manager);
        var index;
        var service;
        var consented;

        for (index = 0; index < services.length; index++) {
            service = services[index];

            if (!service.name || service.required) {
                continue;
            }

            consented = !!(manager.confirmed
                && manager.consents
                && manager.consents[service.name]);
            setServiceConsent(service.name, consented);
        }
    }

    function syncManager(manager) {
        var statisticsConsent;
        var success = true;

        if (!manager) {
            return false;
        }

        statisticsConsent = hasPurposeConsent(manager, 'statistics');
        success = setCategoryConsent('functional', true) && success;
        success = setCategoryConsent('preferences', false) && success;
        success = setCategoryConsent('statistics-anonymous', statisticsConsent) && success;
        success = setCategoryConsent('statistics', statisticsConsent) && success;
        success = setCategoryConsent('marketing', hasPurposeConsent(manager, 'marketing')) && success;
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
