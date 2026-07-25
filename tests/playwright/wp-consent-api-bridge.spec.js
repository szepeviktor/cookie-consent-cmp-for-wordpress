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

test('synchronizes every standard category and each configured service', async ({page}) => {
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
        preferences: 'deny',
        'statistics-anonymous': 'deny',
        statistics: 'deny',
        marketing: 'deny'
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
        preferences: 'deny',
        'statistics-anonymous': 'allow',
        statistics: 'allow',
        marketing: 'deny'
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

test('reports a missing category API instead of silently dropping consent', async ({page}) => {
    await page.goto(fixtureUrl('?missingCategoryApi=1'));
    await page.waitForFunction(() => window.bridgeFixture.errors.length > 0);

    expect(await page.evaluate(() => window.bridgeFixture.errors))
        .toEqual(['WP Consent API JavaScript did not become available.']);
});
