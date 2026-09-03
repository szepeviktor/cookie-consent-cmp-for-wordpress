<?php

declare(strict_types=1);

namespace SzepeViktor\CookieConsentCmp;

use SzepeViktor\CookieConsentCmp\Frontend\Assets;
use SzepeViktor\CookieConsentCmp\Frontend\ConsentApiBridge;

use function is_admin;
use function load_plugin_textdomain;
use function wp_doing_ajax;

final class Plugin
{
    private function __construct()
    {
    }

    public static function boot(): void
    {
        self::loadTextDomain();

        $options = new Options();
        $consentApiBridge = new ConsentApiBridge(Config::get('baseName'));
        $consentApiBridge->register();
        (new Shortcodes())->register();
        (new Assets($options, $consentApiBridge))->register();

        if (is_admin() && ! wp_doing_ajax()) {
            (new AdminPage($options, $consentApiBridge))->boot();
        }
    }

    public static function loadTextDomain(): void
    {
        load_plugin_textdomain(
            'cookie-consent-cmp',
            false,
            sprintf('%s/%s', dirname(Config::get('baseName')), 'languages')
        );
    }
}
