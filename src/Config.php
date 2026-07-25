<?php

/**
 * Config.php
 *
 * @author Viktor Szépe <viktor@szepe.net>
 * @license GNU General Public License v2 or later
 * @link https://github.com/szepeviktor/cookie-consent-cmp-for-wordpress
 */

declare(strict_types=1);

namespace SzepeViktor\CookieConsentCmp;

use LogicException;

/**
 * Immutable configuration.
 *
 * @phpstan-type ConfigShape array{
 *     filePath: string,
 *     baseName: string,
 *     slug: string,
 *     version: string
 * }
 */
final class Config
{
    /** @var ConfigShape|null */
    private static ?array $container = null;

    /**
     * @param ConfigShape $container
     */
    public static function init(array $container): void
    {
        if (isset(self::$container)) {
            return;
        }

        self::$container = $container;
    }

    /**
     * @template TKey of key-of<ConfigShape>
     *
     * @param TKey $name
     * @return ConfigShape[TKey]
     */
    public static function get(string $name)
    {
        if (! isset(self::$container) || ! array_key_exists($name, self::$container)) {
            throw new LogicException('Config is not initialized or the requested key does not exist.');
        }

        return self::$container[$name];
    }
}
