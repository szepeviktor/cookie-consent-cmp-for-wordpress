const {defineConfig} = require('@playwright/test');

module.exports = defineConfig({
    testDir: './tests/playwright',
    fullyParallel: false,
    workers: 1,
    reporter: 'line',
    use: {
        channel: 'chrome',
        headless: true
    }
});
