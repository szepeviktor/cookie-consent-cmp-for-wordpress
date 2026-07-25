/*
 * WP Consent API 2.0.1 frontend implementation.
 * Source: WordPress/wp-consent-level-api@723011d0b15753b489c971f25e29de8a2b9cc822
 * Upstream path: assets/js/wp-consent-api.js
 */
'use strict';

window.wp_fallback_consent_type = consent_api.consent_type;
window.waitfor_consent_hook = consent_api.waitfor_consent_hook;

function wp_has_service_consent(service) {
    let consented_services_json = consent_api_get_cookie(
        consent_api.cookie_prefix + '_consented_services'
    );
    let consented_services;

    try {
        consented_services = JSON.parse(consented_services_json);
    } catch (e) {
        consented_services = {};
    }

    if (!consented_services.hasOwnProperty(service)) {
        return wp_has_consent(wp_get_service_category(service));
    }

    return consented_services[service];
}

function wp_is_service_denied(service) {
    let consented_services_json = consent_api_get_cookie(
        consent_api.cookie_prefix + '_consented_services'
    );
    let consented_services;

    try {
        consented_services = JSON.parse(consented_services_json);
    } catch (e) {
        consented_services = {};
    }

    if (!consented_services.hasOwnProperty(service)) {
        return false;
    }

    return !consented_services[service];
}

function wp_set_service_consent(service, consented) {
    let consented_services_json = consent_api_get_cookie(
        consent_api.cookie_prefix + '_consented_services'
    );
    let consented_services;

    try {
        consented_services = JSON.parse(consented_services_json);
    } catch (e) {
        consented_services = {};
    }

    consented_services[service] = consented;
    consent_api_set_cookie(
        consent_api.cookie_prefix + '_consented_services',
        JSON.stringify(consented_services)
    );

    let details = {};
    details.service = service;
    details.value = consented;
    document.dispatchEvent(
        new CustomEvent('wp_consent_api_status_change_service', {detail: details})
    );
}

function wp_has_consent(category) {
    let has_consent = false;
    let consent_type;

    if (typeof window.wp_consent_type !== 'undefined') {
        consent_type = window.wp_consent_type;
    } else {
        consent_type = window.wp_fallback_consent_type;
    }

    let cookie_value = consent_api_get_cookie(consent_api.cookie_prefix + '_' + category);

    if (!consent_type) {
        has_consent = true;
    } else if (consent_type.indexOf('optout') !== -1 && cookie_value === '') {
        has_consent = true;
    } else {
        has_consent = cookie_value === 'allow';
    }

    return has_consent;
}

function wp_get_service_category(service) {
    if (!consent_api.services || !Array.isArray(consent_api.services)) {
        return 'marketing';
    }

    let services = consent_api.services;

    for (let i = 0; i < services.length; i++) {
        if (services[i] && services[i].name === service && services[i].category) {
            return services[i].category;
        }
    }

    return 'marketing';
}

function consent_api_set_cookie(name, value) {
    let secure = ';secure';
    let days = consent_api.cookie_expiration;
    let date = new Date();
    date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
    let expires = ';expires=' + date.toGMTString();

    if (window.location.protocol !== 'https:') {
        secure = '';
    }

    document.cookie = name + '=' + value + secure + expires + ';path=/';
}

function consent_api_get_cookie(name) {
    name = name + '=';
    var cookies = window.document.cookie.split(';');

    for (var i = 0; i < cookies.length; i++) {
        var cookie = cookies[i].trim();

        if (cookie.indexOf(name) === 0) {
            return cookie.substring(name.length, cookie.length);
        }
    }

    return '';
}

function wp_set_consent(category, value) {
    let event;

    if (value !== 'allow' && value !== 'deny') {
        return;
    }

    var previous_value = consent_api_get_cookie(consent_api.cookie_prefix + '_' + category);
    consent_api_set_cookie(consent_api.cookie_prefix + '_' + category, value);

    if (previous_value === value) {
        return;
    }

    var changedConsentCategory = [];
    changedConsentCategory[category] = value;

    try {
        event = new CustomEvent(
            'wp_listen_for_consent_change',
            {detail: changedConsentCategory}
        );
    } catch (err) {
        event = document.createEvent('Event');
        event.initEvent('wp_listen_for_consent_change', true, true);
        event.detail = changedConsentCategory;
    }

    document.dispatchEvent(event);
}
