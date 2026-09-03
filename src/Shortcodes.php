<?php

/**
 * Shortcodes.php
 *
 * @author Viktor Szépe <viktor@szepe.net>
 * @license GNU General Public License v2 or later
 * @link https://github.com/szepeviktor/cookie-consent-cmp-for-wordpress
 */

declare(strict_types=1);

namespace SzepeViktor\CookieConsentCmp;

use function add_shortcode;
use function esc_url;
use function get_privacy_policy_url;

/**
 * Registers frontend shortcodes.
 */
final class Shortcodes
{
    public function register(): void
    {
        add_shortcode('privacy-policy', [$this, 'privacyPolicyUrl']);
    }

    public function privacyPolicyUrl(): string
    {
        return esc_url(get_privacy_policy_url());
    }
}
