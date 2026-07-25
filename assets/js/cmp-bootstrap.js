// Requires ECMAScript 5 (ES5) syntax support.
(function () {
    'use strict';

    var SERVICE_NAMES = {
        gtm: 'google-tag-manager',
        clarity: 'microsoft-clarity',
        metaPixel: 'meta-pixel',
        linkedinInsightTag: 'linkedin-insight-tag',
        hotjar: 'hotjar',
        youtube: 'youtube'
    };

    function getFirstScriptParent() {
        var firstScript = document.getElementsByTagName('script')[0];

        if (firstScript && firstScript.parentNode) {
            return {
                parent: firstScript.parentNode,
                before: firstScript
            };
        }

        if (document.head) {
            return {
                parent: document.head,
                before: null
            };
        }

        if (document.body) {
            return {
                parent: document.body,
                before: null
            };
        }

        return null;
    }

    function insertScript(scriptElement) {
        var target = getFirstScriptParent();

        if (!target) {
            console.warn('CMP bootstrap: could not find a DOM node for script injection');
            return false;
        }

        if (target.before) {
            target.parent.insertBefore(scriptElement, target.before);
        } else {
            target.parent.appendChild(scriptElement);
        }

        return true;
    }

    function createVendorRegistry() {
        var vendors = Object.create(null);

        return {
            register: function (vendor) {
                vendors[vendor.serviceName] = vendors[vendor.serviceName] || [];
                vendors[vendor.serviceName].push(vendor);
                if (typeof vendor.init === 'function') {
                    vendor.init();
                }
            },
            applyManagerState: function (manager) {
                Object.keys(vendors).forEach(function (serviceName) {
                    var consent = isServiceActive(manager, serviceName);

                    vendors[serviceName].forEach(function (vendor) {
                        vendor.syncConsent(consent);
                    });
                });
            }
        };
    }

    function createConsentAwareVendor(serviceName, hooks) {
        var hasConsent = false;

        return {
            serviceName: serviceName,
            syncConsent: function (consent) {
                if (consent) {
                    if (!hasConsent) {
                        hooks.grant();
                        hasConsent = true;
                    }
                    return;
                }

                if (hasConsent) {
                    hooks.revoke();
                    hasConsent = false;
                }
            }
        };
    }

    function createKlaroBridge(registry) {
        var maxAttempts = 40;
        var delayMs = 250;
        var isBound = false;

        var watcher = {
            update: function (manager, type) {
                if (type === 'applyConsents') {
                    registry.applyManagerState(manager);
                }
            }
        };

        function bindManager(manager) {
            if (!manager || isBound) {
                return;
            }

            manager.watch(watcher);
            isBound = true;
            registry.applyManagerState(manager);
        }

        function tryBind(attempt) {
            var manager;

            if (attempt >= maxAttempts) {
                console.warn('CMP bootstrap: Klaro did not become available in time');
                return;
            }

            if (!window.klaro || typeof window.klaro.getManager !== 'function') {
                window.setTimeout(function () {
                    tryBind(attempt + 1);
                }, delayMs);
                return;
            }

            try {
                manager = window.klaro.getManager();
            } catch (error) {
                console.warn('CMP bootstrap: failed to access Klaro manager');
                return;
            }

            if (!manager) {
                window.setTimeout(function () {
                    tryBind(attempt + 1);
                }, delayMs);
                return;
            }

            bindManager(manager);
        }

        return {
            start: function () {
                tryBind(0);
            }
        };
    }

    function isServiceActive(manager, serviceName) {
        var service;
        var consent;
        var required;
        var optOut;
        var globalOptOut;
        var confirmed;

        if (!manager || !manager.consents) {
            return false;
        }

        consent = !!manager.consents[serviceName];
        service = typeof manager.getService === 'function'
            ? manager.getService(serviceName)
            : null;
        required = !!(service && service.required);
        optOut = !!(service && service.optOut);
        globalOptOut = !!(manager.config && manager.config.optOut);
        confirmed = !!manager.confirmed || optOut || globalOptOut;

        return required || (consent && confirmed);
    }

    function createGtmVendor(options) {
        var serviceName = options.serviceName || SERVICE_NAMES.gtm;
        var gtmId = options.gtmId;
        var dataLayerName = options.dataLayerName || 'dataLayer';
        var hasLoaded = false;

        function ensureRuntime() {
            window[dataLayerName] = window[dataLayerName] || [];

            if (typeof window.gtag !== 'function') {
                window.gtag = function () {
                    window[dataLayerName].push(arguments);
                };
            }
        }

        function buildConsentState(consent) {
            return {
                analytics_storage: consent ? 'granted' : 'denied',
                ad_storage: 'denied',
                ad_user_data: 'denied',
                ad_personalization: 'denied'
            };
        }

        function updateConsent(consent) {
            ensureRuntime();
            window.gtag('consent', 'update', buildConsentState(consent));
        }

        function load() {
            var dlParam;
            var scriptElement;

            if (hasLoaded) {
                return;
            }

            ensureRuntime();
            window.gtag('consent', 'default', buildConsentState(false));
            window[dataLayerName].push({
                'gtm.start': new Date().getTime(),
                event: 'gtm.js'
            });

            dlParam = dataLayerName !== 'dataLayer'
                ? '&l=' + encodeURIComponent(dataLayerName)
                : '';

            scriptElement = document.createElement('script');
            scriptElement.async = true;
            scriptElement.src = 'https://www.googletagmanager.com/gtm.js?id='
                + encodeURIComponent(gtmId)
                + dlParam;

            if (insertScript(scriptElement)) {
                hasLoaded = true;
            }
        }

        return createConsentAwareVendor(serviceName, {
            grant: function () {
                load();
                if (hasLoaded) {
                    updateConsent(true);
                }
            },
            revoke: function () {
                if (hasLoaded) {
                    updateConsent(false);
                }
            }
        });
    }

    function createMetaPixelVendor(options) {
        var serviceName = options.serviceName || SERVICE_NAMES.metaPixel;
        var pixelId = options.pixelId;
        var hasLoaded = false;
        var hasInitialized = false;

        function ensureRuntime() {
            var fbq;

            if (typeof window.fbq === 'function') {
                return;
            }

            fbq = function () {
                if (fbq.callMethod) {
                    fbq.callMethod.apply(fbq, arguments);
                } else {
                    fbq.queue.push(arguments);
                }
            };

            if (!window._fbq) {
                window._fbq = fbq;
            }

            fbq.push = fbq;
            fbq.loaded = true;
            fbq.version = '2.0';
            fbq.queue = [];
            window.fbq = fbq;
        }

        function load() {
            var scriptElement;

            if (hasLoaded) {
                return;
            }

            ensureRuntime();

            scriptElement = document.createElement('script');
            scriptElement.async = true;
            scriptElement.src = 'https://connect.facebook.net/en_US/fbevents.js';

            if (insertScript(scriptElement)) {
                hasLoaded = true;
            }
        }

        function initialize() {
            if (!hasInitialized && typeof window.fbq === 'function') {
                window.fbq('set', 'autoConfig', false, pixelId);
                window.fbq('init', pixelId);
                hasInitialized = true;
            }
        }

        return createConsentAwareVendor(serviceName, {
            grant: function () {
                load();
                initialize();

                if (typeof window.fbq === 'function') {
                    window.fbq('consent', 'grant');
                    window.fbq('track', 'PageView');
                }
            },
            revoke: function () {
                if (hasInitialized && typeof window.fbq === 'function') {
                    window.fbq('consent', 'revoke');
                }
            }
        });
    }

    function createClarityVendor(options) {
        var serviceName = options.serviceName || SERVICE_NAMES.clarity;
        var projectId = options.projectId;
        var hasLoaded = false;

        function ensureRuntime() {
            if (typeof window.clarity === 'function') {
                return;
            }

            window.clarity = function () {
                (window.clarity.q = window.clarity.q || []).push(arguments);
            };
        }

        function updateConsent(consent) {
            if (typeof window.clarity !== 'function') {
                return;
            }

            window.clarity('consentv2', {
                ad_Storage: 'denied',
                analytics_Storage: consent ? 'granted' : 'denied'
            });

            if (!consent) {
                window.clarity('consent', false);
            }
        }

        function load() {
            var scriptElement;

            if (hasLoaded) {
                return;
            }

            ensureRuntime();

            scriptElement = document.createElement('script');
            scriptElement.async = true;
            scriptElement.src = 'https://www.clarity.ms/tag/' + encodeURIComponent(projectId);

            if (insertScript(scriptElement)) {
                hasLoaded = true;
            }
        }

        return createConsentAwareVendor(serviceName, {
            grant: function () {
                load();
                updateConsent(true);
            },
            revoke: function () {
                if (hasLoaded) {
                    updateConsent(false);
                }
            }
        });
    }

    function createLinkedInInsightTagVendor(options) {
        var serviceName = options.serviceName || SERVICE_NAMES.linkedinInsightTag;
        var partnerId = options.partnerId;
        var hasLoaded = false;

        function clearAdsId(storage) {
            if (!storage || typeof storage.removeItem !== 'function') {
                return;
            }

            try {
                storage.removeItem('li_adsid');
            } catch (error) {
            }
        }

        function ensureRuntime() {
            if (!window._linkedin_data_partner_ids) {
                window._linkedin_data_partner_ids = [];
            }

            if (window._linkedin_data_partner_ids.indexOf(partnerId) === -1) {
                window._linkedin_data_partner_ids.push(partnerId);
            }

            if (typeof window.lintrk !== 'function') {
                window.lintrk = function (action, data) {
                    window.lintrk.q.push([action, data]);
                };
                window.lintrk.q = [];
            }
        }

        function load() {
            var scriptElement;

            if (hasLoaded) {
                return;
            }

            ensureRuntime();

            scriptElement = document.createElement('script');
            scriptElement.async = true;
            scriptElement.src = 'https://snap.licdn.com/li.lms-analytics/insight.min.js';

            if (insertScript(scriptElement)) {
                hasLoaded = true;
            }
        }

        return createConsentAwareVendor(serviceName, {
            grant: function () {
                load();
            },
            revoke: function () {
                clearAdsId(window.localStorage);
                clearAdsId(window.sessionStorage);
            }
        });
    }

    function createHotjarVendor(options) {
        var serviceName = options.serviceName || SERVICE_NAMES.hotjar;
        var siteId = options.siteId;
        var scriptVersion = options.scriptVersion || 6;
        var hasLoaded = false;

        function clearStorage(storage, key) {
            if (!storage || typeof storage.removeItem !== 'function') {
                return;
            }

            try {
                storage.removeItem(key);
            } catch (error) {
            }
        }

        function ensureRuntime() {
            if (typeof window.hj === 'function') {
                return;
            }

            window.hj = function () {
                window.hj.q.push(arguments);
            };
            window.hj.q = [];
        }

        function load() {
            var scriptElement;

            if (hasLoaded) {
                return;
            }

            ensureRuntime();
            window._hjSettings = {
                hjid: Number(siteId),
                hjsv: Number(scriptVersion)
            };

            scriptElement = document.createElement('script');
            scriptElement.async = true;
            scriptElement.src = 'https://static.hotjar.com/c/hotjar-'
                + encodeURIComponent(siteId)
                + '.js?sv='
                + encodeURIComponent(scriptVersion);

            if (insertScript(scriptElement)) {
                hasLoaded = true;
            }
        }

        return createConsentAwareVendor(serviceName, {
            grant: function () {
                load();
            },
            revoke: function () {
                clearStorage(window.localStorage, '_hjUserAttributes');
                clearStorage(window.localStorage, 'hjActiveViewportIds');
                clearStorage(window.sessionStorage, 'hjViewportId');
            }
        });
    }

    function createYouTubeVendor(options) {
        var serviceName = options.serviceName || SERVICE_NAMES.youtube;
        var consentGranted = false;
        var observer = null;
        var tokenCounter = 0;
        var entries = Object.create(null);
        var placeholderCopy = {
            title: 'YouTube video',
            description: 'This video loads from YouTube only after consent.',
            button: 'Load video'
        };

        function getManager() {
            if (!window.klaro || typeof window.klaro.getManager !== 'function') {
                return null;
            }

            try {
                return window.klaro.getManager();
            } catch (error) {
                return null;
            }
        }

        function grantContextualConsent() {
            var manager = getManager();

            if (!manager) {
                return false;
            }

            manager.updateConsent(serviceName, true);
            manager.saveAndApplyConsents('contextual');

            return true;
        }

        function appendCssUnit(value) {
            return /^\d+$/.test(value) ? value + 'px' : value;
        }

        function getIframeSource(iframe) {
            if (!iframe || iframe.tagName !== 'IFRAME') {
                return null;
            }

            return iframe.getAttribute('data-cmp-src')
                || iframe.getAttribute('data-src')
                || iframe.getAttribute('src');
        }

        function parseYouTubeUrl(src) {
            var url;
            var match;

            if (!src) {
                return null;
            }

            url = document.createElement('a');
            url.href = src.indexOf('//') === 0 ? window.location.protocol + src : src;

            if (!/(^|\.)(youtube\.com|youtube-nocookie\.com)$/.test(url.hostname)) {
                return null;
            }

            match = /^\/embed\/([^/?#]+)/.exec(url.pathname);

            if (!match) {
                return null;
            }

            return {
                videoId: decodeURIComponent(match[1]),
                query: url.search ? url.search.slice(1) : ''
            };
        }

        function buildYouTubeSrc(entry) {
            return 'https://www.youtube-nocookie.com/embed/'
                + encodeURIComponent(entry.videoId)
                + (entry.query ? '?' + entry.query : '');
        }

        function buildThumbnailUrl(entry) {
            return 'https://i.ytimg.com/vi/'
                + encodeURIComponent(entry.videoId)
                + '/hqdefault.jpg';
        }

        function applyPresentation(entry, element) {
            if (entry.className) {
                element.className += ' ' + entry.className;
            }

            if (entry.styleText) {
                element.setAttribute('style', entry.styleText);
            }

            if (entry.widthAttr) {
                element.style.width = appendCssUnit(entry.widthAttr);
            }

            if (entry.heightAttr) {
                element.style.height = appendCssUnit(entry.heightAttr);
            }

            if (entry.widthNumber && entry.heightNumber && !entry.heightAttr) {
                element.style.aspectRatio = entry.widthNumber + ' / ' + entry.heightNumber;
            }
        }

        function replaceCurrentElement(entry, newElement) {
            if (entry.currentElement && entry.currentElement.parentNode) {
                entry.currentElement.parentNode.replaceChild(newElement, entry.currentElement);
            }

            entry.currentElement = newElement;
        }

        function activateEntry(entry) {
            var iframe = document.createElement('iframe');

            iframe.className = 'cmp-youtube-embed';
            iframe.setAttribute('data-cmp-youtube-token', entry.token);
            iframe.setAttribute('src', buildYouTubeSrc(entry));
            iframe.setAttribute('title', entry.title || placeholderCopy.title);
            iframe.setAttribute('frameborder', entry.frameBorder || '0');
            iframe.setAttribute('allow', entry.allow || 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share');
            iframe.setAttribute('referrerpolicy', entry.referrerPolicy || 'strict-origin-when-cross-origin');
            iframe.setAttribute('loading', entry.loading || 'lazy');

            if (entry.allowFullscreen) {
                iframe.setAttribute('allowfullscreen', '');
            }

            applyPresentation(entry, iframe);
            replaceCurrentElement(entry, iframe);
        }

        function deactivateEntry(entry) {
            var placeholder;
            var content;
            var description;
            var title;
            var body;
            var button;

            placeholder = document.createElement('button');
            placeholder.type = 'button';
            placeholder.className = 'cmp-youtube-placeholder';
            placeholder.setAttribute('data-cmp-youtube-token', entry.token);
            placeholder.setAttribute('aria-label', (entry.title || placeholderCopy.title) + ': ' + placeholderCopy.button);
            placeholder.style.backgroundImage = 'linear-gradient(180deg, rgba(9, 12, 17, 0.18) 0%, rgba(9, 12, 17, 0.86) 100%), url("'
                + buildThumbnailUrl(entry)
                + '")';

            applyPresentation(entry, placeholder);

            content = document.createElement('span');
            content.className = 'cmp-youtube-placeholder-content';

            description = document.createElement('span');
            description.className = 'cmp-youtube-placeholder-copy';
            title = document.createElement('strong');
            title.appendChild(document.createTextNode(entry.title || placeholderCopy.title));
            body = document.createElement('span');
            body.appendChild(document.createTextNode(placeholderCopy.description));
            description.appendChild(title);
            description.appendChild(body);

            button = document.createElement('span');
            button.className = 'cmp-youtube-placeholder-button';
            button.appendChild(document.createTextNode(placeholderCopy.button));

            content.appendChild(description);
            content.appendChild(button);
            placeholder.appendChild(content);
            placeholder.addEventListener('click', function () {
                if (!grantContextualConsent() && typeof window.klaro !== 'undefined' && typeof window.klaro.show === 'function') {
                    window.klaro.show(window.klaroConfig, true);
                }
            });

            replaceCurrentElement(entry, placeholder);
        }

        function processIframe(iframe) {
            var parsed;
            var widthNumber;
            var heightNumber;
            var token;
            var entry;

            if (!iframe || iframe.tagName !== 'IFRAME' || iframe.getAttribute('data-cmp-youtube-token')) {
                return;
            }

            parsed = parseYouTubeUrl(getIframeSource(iframe));

            if (!parsed) {
                return;
            }

            widthNumber = parseInt(iframe.getAttribute('width'), 10);
            heightNumber = parseInt(iframe.getAttribute('height'), 10);
            token = String(tokenCounter++);
            entry = {
                token: token,
                videoId: parsed.videoId,
                query: parsed.query,
                title: iframe.getAttribute('title'),
                className: iframe.className || '',
                styleText: iframe.getAttribute('style') || '',
                widthAttr: iframe.getAttribute('width'),
                heightAttr: iframe.getAttribute('height'),
                widthNumber: isNaN(widthNumber) ? null : widthNumber,
                heightNumber: isNaN(heightNumber) ? null : heightNumber,
                allow: iframe.getAttribute('allow'),
                referrerPolicy: iframe.getAttribute('referrerpolicy'),
                loading: iframe.getAttribute('loading'),
                frameBorder: iframe.getAttribute('frameborder'),
                allowFullscreen: iframe.hasAttribute('allowfullscreen'),
                currentElement: iframe
            };

            entries[token] = entry;

            if (consentGranted) {
                activateEntry(entry);
            } else {
                deactivateEntry(entry);
            }
        }

        function scanNode(node) {
            var iframes;
            var index;

            if (!node || node.nodeType !== 1) {
                return;
            }

            if (node.tagName === 'IFRAME') {
                processIframe(node);
            }

            iframes = node.querySelectorAll ? node.querySelectorAll('iframe') : [];

            for (index = 0; index < iframes.length; index++) {
                processIframe(iframes[index]);
            }
        }

        function scanDocument() {
            scanNode(document.body || document.documentElement);
        }

        return {
            serviceName: serviceName,
            init: function () {
                if (typeof MutationObserver === 'function' && document.documentElement) {
                    observer = new MutationObserver(function (mutations) {
                        var mutationIndex;
                        var nodeIndex;

                        for (mutationIndex = 0; mutationIndex < mutations.length; mutationIndex++) {
                            for (nodeIndex = 0; nodeIndex < mutations[mutationIndex].addedNodes.length; nodeIndex++) {
                                scanNode(mutations[mutationIndex].addedNodes[nodeIndex]);
                            }
                        }
                    });

                    observer.observe(document.documentElement, {
                        childList: true,
                        subtree: true
                    });
                }

                if (document.readyState === 'loading') {
                    document.addEventListener('DOMContentLoaded', scanDocument);
                } else {
                    scanDocument();
                }
            },
            syncConsent: function (consent) {
                var token;

                consentGranted = consent;
                scanDocument();

                for (token in entries) {
                    if (consentGranted) {
                        activateEntry(entries[token]);
                    } else {
                        deactivateEntry(entries[token]);
                    }
                }
            }
        };
    }

    function buildRegistry(script) {
        var registry = createVendorRegistry();
        var dataLayerName = script ? script.getAttribute('data-layer-name') : null;
        var gtmId = script ? script.getAttribute('data-gtm-id') : null;
        var clarityProjectId = script ? script.getAttribute('data-clarity-project-id') : null;
        var metaPixelId = script ? script.getAttribute('data-meta-pixel-id') : null;
        var linkedinPartnerId = script ? script.getAttribute('data-linkedin-partner-id') : null;
        var hotjarId = script ? script.getAttribute('data-hotjar-id') : null;
        var hotjarVersion = script ? script.getAttribute('data-hotjar-version') : null;
        var youtubeServiceName = script ? script.getAttribute('data-youtube-service') : null;

        if (gtmId !== null) {
            registry.register(createGtmVendor({
                serviceName: SERVICE_NAMES.gtm,
                gtmId: gtmId,
                dataLayerName: dataLayerName || 'dataLayer'
            }));
        }

        if (clarityProjectId !== null) {
            registry.register(createClarityVendor({
                serviceName: SERVICE_NAMES.clarity,
                projectId: clarityProjectId
            }));
        }

        if (metaPixelId !== null) {
            registry.register(createMetaPixelVendor({
                serviceName: SERVICE_NAMES.metaPixel,
                pixelId: metaPixelId
            }));
        }

        if (linkedinPartnerId !== null) {
            registry.register(createLinkedInInsightTagVendor({
                serviceName: SERVICE_NAMES.linkedinInsightTag,
                partnerId: linkedinPartnerId
            }));
        }

        if (hotjarId !== null) {
            registry.register(createHotjarVendor({
                serviceName: SERVICE_NAMES.hotjar,
                siteId: hotjarId,
                scriptVersion: hotjarVersion || 6
            }));
        }

        if (youtubeServiceName !== null) {
            registry.register(createYouTubeVendor({
                serviceName: youtubeServiceName || SERVICE_NAMES.youtube
            }));
        }

        return registry;
    }

    function getFloatingButtonLabel() {
        var config = window.klaroConfig || {};
        var translations = config.translations || {};
        var translation = translations[config.lang] || translations.en || {};

        if (translation.consentModal && translation.consentModal.title) {
            return translation.consentModal.title;
        }

        return 'Privacy settings';
    }

    function addFloatingButton(script) {
        var floating = script ? script.getAttribute('data-floating') : null;
        var button;
        var label;

        if (floating === null || floating === 'false' || floating === '0' || document.getElementById('cmp-floating-settings')) {
            return;
        }

        label = getFloatingButtonLabel();
        button = document.createElement('button');
        button.id = 'cmp-floating-settings';
        button.className = 'cmp-floating-settings';
        button.type = 'button';
        button.setAttribute('aria-label', label);
        button.setAttribute('title', label);
        button.appendChild(document.createTextNode(label));
        button.addEventListener('click', function () {
            if (typeof window.klaro !== 'undefined' && typeof window.klaro.show === 'function') {
                window.klaro.show(window.klaroConfig, true);
            }
        });
        document.body.appendChild(button);
    }

    var script = document.currentScript;
    var registry = buildRegistry(script);
    var klaroBridge = createKlaroBridge(registry);

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            klaroBridge.start();
            addFloatingButton(script);
        });
    } else {
        klaroBridge.start();
        addFloatingButton(script);
    }
})();
