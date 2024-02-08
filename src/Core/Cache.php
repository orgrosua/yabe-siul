<?php

/*
 * This file is part of the Yabe package.
 *
 * (c) Joshua Gugun Siagian <suabahasa@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Yabe\Siul\Core;

use Yabe\Siul\Utils\Cache as UtilsCache;
use Yabe\Siul\Utils\Common;

/**
 * @since 1.0.0
 */
class Cache
{
    /**
     * @var string
     */
    public const CSS_CACHE_FILE = 'tailwind.css';

    /**
     * @var string
     */
    public const CACHE_DIR = '/yabe-siul/cache/';

    public static function get_providers(): array
    {
        /**
         * Register cache providers.
         * @param array $providers The list of cache providers. Each provider should have `id`, `name`, `description`, and `callback` keys.
         */
        return apply_filters('f!yabe/siul/core/cache:compile.providers', []);
    }

    public static function get_cache_path(string $file_path = ''): string
    {
        return wp_upload_dir()['basedir'] . self::CACHE_DIR . $file_path;
    }

    public static function get_cache_url(string $file_path = ''): string
    {
        return wp_upload_dir()['baseurl'] . self::CACHE_DIR . $file_path;
    }

    public static function save_cache(string $payload)
    {
        try {
            Common::save_file($payload, self::get_cache_path(self::CSS_CACHE_FILE));
        } catch (\Throwable $throwable) {
            throw $throwable;
        }

        UtilsCache::purge_cache_plugin();
    }
}
