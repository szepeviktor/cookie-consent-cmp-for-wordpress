const {defineConfig} = require('@playwright/test');

module.exports = defineConfig({
    testDir: './tests/playwright',
    fullyParallel: false,
    workers: 1,
    reporter: 'line',
    webServer: {
        command: 'rtk python3 -m http.server 8765 --bind 127.0.0.1',
        url: 'http://127.0.0.1:8765',
        reuseExistingServer: true
    },
    use: {
        channel: 'chrome',
        headless: true
    }
});
