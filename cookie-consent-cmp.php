<?php

/**
 * Cookie Consent CMP
 *
 * @author            Viktor Szépe <viktor@szepe.net>
 * @license           GNU General Public License v2 or later
 * @link              https://github.com/szepeviktor/cookie-consent-cmp-for-wordpress
 *
 * @wordpress-plugin
 * Plugin Name:       Cookie Consent CMP
 * Plugin URI:        https://github.com/szepeviktor/cookie-consent-cmp-for-wordpress
 * Description:       Klaro-based cookie consent banner with WP Consent API compatibility.
 * Version:           1.2.0
 * Requires at least: 6.4
 * Requires PHP:      7.4
 * Author:            Viktor Szépe
 * Author URI:        https://github.com/szepeviktor
 * Text Domain:       cookie-consent-cmp
 * License:           GPL v2 or later
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Update URI:        false
 */

declare(strict_types=1);

namespace SzepeViktor\CookieConsentCmp;

use function plugin_basename;

// Prevent direct execution.
if (! defined('ABSPATH')) {
    exit;
}

require sprintf('%s/vendor/autoload.php', __DIR__);

Config::init([
    'filePath' => __FILE__,
    'baseName' => plugin_basename(__FILE__),
    'slug' => 'cookie-consent-cmp',
    'version' => '1.2.0',
]);

add_action('plugins_loaded', [Plugin::class, 'boot'], 10, 0);
