const {expect, test} = require('@playwright/test');
const {pathToFileURL} = require('node:url');
const path = require('node:path');

function fixtureUrl(query = '') {
    const fixture = path.resolve(__dirname, '../fixtures/wp-consent-api-bridge.html');

    return pathToFileURL(fixture).href + query;
}

async function openFixture(page, query = '') {
    await page.goto(fixtureUrl(query));
    await page.waitForFunction(() => window.bridgeFixture.categoryCalls.length >= 5);
}

test('synchronizes explicit category choices independently from service choices', async ({page}) => {
    await openFixture(page);

    const state = await page.evaluate(() => ({
        categories: Object.fromEntries(
            window.bridgeFixture.categoryCalls.map((call) => [call.category, call.value])
        ),
        services: Object.fromEntries(
            window.bridgeFixture.serviceCalls.map((call) => [call.service, call.consented])
        )
    }));

    expect(state.categories).toEqual({
        functional: 'allow',
        preferences: 'allow',
        'statistics-anonymous': 'allow',
        statistics: 'allow',
        marketing: 'allow'
    });
    expect(state.services).toEqual({
        analytics: true,
        clarity: false,
        youtube: true,
        meta: false
    });
});

test('ignores unsaved toggles and synchronizes after save', async ({page}) => {
    await openFixture(page);

    const initialCounts = await page.evaluate(() => ({
        categories: window.bridgeFixture.categoryCalls.length,
        services: window.bridgeFixture.serviceCalls.length
    }));

    await page.evaluate(() => {
        window.bridgeFixture.manager.consents.clarity = true;
        window.bridgeFixture.manager.consents['wp-consent-category-statistics'] = false;
        window.bridgeFixture.manager.trigger('consents');
    });
    expect(await page.evaluate(() => window.bridgeFixture.categoryCalls.length))
        .toBe(initialCounts.categories);
    expect(await page.evaluate(() => window.bridgeFixture.serviceCalls.length))
        .toBe(initialCounts.services);

    await page.evaluate(() => window.bridgeFixture.manager.trigger('saveConsents'));

    expect(await page.evaluate(() => window.bridgeFixture.categoryCalls.length))
        .toBe(initialCounts.categories + 5);
    expect(await page.evaluate(() => Object.fromEntries(
        window.bridgeFixture.categoryCalls.slice(-5).map((call) => [call.category, call.value])
    ))).toEqual({
        functional: 'allow',
        preferences: 'allow',
        'statistics-anonymous': 'allow',
        statistics: 'deny',
        marketing: 'allow'
    });
    expect(await page.evaluate(() => window.bridgeFixture.serviceCalls.slice(-1)[0]))
        .toEqual({service: 'clarity', consented: true});
});

test('dispatches on document and preserves an existing consent type', async ({page}) => {
    await openFixture(page, '?existing=optout');

    const result = await page.evaluate(() => ({
        consentType: window.wp_consent_type,
        documentEvents: window.bridgeFixture.documentConsentTypeEvents,
        windowEvents: window.bridgeFixture.windowConsentTypeEvents
    }));

    expect(result).toEqual({
        consentType: 'optout',
        documentEvents: 1,
        windowEvents: 0
    });
});

test('falls back to category synchronization with a pre-2.0 API', async ({page}) => {
    await openFixture(page, '?legacy=1');

    expect(await page.evaluate(() => window.bridgeFixture.categoryCalls.length)).toBe(5);
    expect(await page.evaluate(() => window.bridgeFixture.serviceCalls)).toEqual([]);
    expect(await page.evaluate(() => window.bridgeFixture.errors)).toEqual([]);
});

test('does not publish redundant service changes when effective state already matches', async ({page}) => {
    await openFixture(page, '?serviceState=1');

    expect(await page.evaluate(() => window.bridgeFixture.serviceCalls)).toEqual([]);
});

test('integrates with the real WP Consent API 2.0.1 browser implementation', async ({page}) => {
    await page.goto('http://127.0.0.1:8765/tests/fixtures/wp-consent-api-integration.html');
    await page.waitForFunction(() => document.cookie.includes('wp_consent_marketing=allow'));

    const initial = await page.evaluate(() => ({
        categories: {
            functional: wp_has_consent('functional'),
            preferences: wp_has_consent('preferences'),
            anonymous: wp_has_consent('statistics-anonymous'),
            statistics: wp_has_consent('statistics'),
            marketing: wp_has_consent('marketing')
        },
        services: {
            analytics: wp_has_service_consent('analytics'),
            clarity: wp_has_service_consent('clarity'),
            youtube: wp_has_service_consent('youtube'),
            meta: wp_has_service_consent('meta')
        },
        events: window.integrationFixture.serviceEvents,
        categoryWrites: window.integrationFixture.categoryWrites
    }));

    expect(initial.categories).toEqual({
        functional: true,
        preferences: true,
        anonymous: true,
        statistics: true,
        marketing: true
    });
    expect(initial.services).toEqual({
        analytics: true,
        clarity: false,
        youtube: true,
        meta: false
    });
    expect(initial.events).toEqual([
        {service: 'clarity', value: false},
        {service: 'meta', value: false}
    ]);
    expect(initial.categoryWrites).toHaveLength(5);

    await page.reload();
    await page.waitForFunction(() => window.integrationFixture);

    expect(await page.evaluate(() => window.integrationFixture.serviceEvents)).toEqual([]);
    expect(await page.evaluate(() => window.integrationFixture.categoryWrites)).toEqual([]);
});

test('reports a missing category API instead of silently dropping consent', async ({page}) => {
    await page.goto(fixtureUrl('?missingCategoryApi=1'));
    await page.waitForFunction(() => window.bridgeFixture.errors.length > 0);

    expect(await page.evaluate(() => window.bridgeFixture.errors))
        .toEqual(['WP Consent API JavaScript did not become available.']);
});
