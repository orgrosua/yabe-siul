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

use Exception;
use SIUL;
use Yabe\Siul\Utils\Asset;
use Yabe\Siul\Utils\Config;

/**
 * @since 1.0.0
 */
class Runtime
{
    /**
     * Stores the instance, implementing a Singleton pattern.
     */
    private static self $instance;

    /**
     * The Singleton's constructor should always be private to prevent direct
     * construction calls with the `new` operator.
     */
    private function __construct()
    {
    }

    /**
     * Singletons should not be cloneable.
     */
    private function __clone()
    {
    }

    /**
     * Singletons should not be restorable from strings.
     *
     * @throws Exception Cannot unserialize a singleton.
     */
    public function __wakeup()
    {
        throw new Exception('Cannot unserialize a singleton.');
    }

    /**
     * This is the static method that controls the access to the singleton
     * instance. On the first run, it creates a singleton object and places it
     * into the static property. On subsequent runs, it returns the client existing
     * object stored in the static property.
     */
    public static function get_instance(): self
    {
        if (! isset(self::$instance)) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function init()
    {
        if (! is_admin()) {
            $is_prevent_load = apply_filters('f!yabe/siul/core/runtime:is_prevent_load', false);

            if ($is_prevent_load) {
                return;
            }

            $this->append_header();
        }
    }

    public function append_header()
    {
        $is_cache_enabled = Config::get('performance.cache.enabled', false);
        $is_cache_enabled = apply_filters('f!yabe/siul/core/runtime:append_header.cache_enabled', $is_cache_enabled);

        $is_exclude_admin = Config::get('performance.cache.exclude_admin', false) && current_user_can('manage_options');
        $is_exclude_admin = apply_filters('f!yabe/siul/core/runtime:append_header.exclude_admin', $is_exclude_admin);

        if ($is_cache_enabled && $this->is_cache_exists() && ! $is_exclude_admin) {
            add_action('wp_head', fn () => $this->enqueue_css_cache(), 1_000_001);
        } else {
            add_action('wp_head', fn () => $this->enqueue_play_cdn(), 1_000_001);

            if (Config::get('general.autocomplete.engine.enabled', false)) {
                $this->enqueue_module_autocomplete();
            }
        }
    }

    public function is_cache_exists()
    {
        return file_exists(Cache::get_cache_path(Cache::CSS_CACHE_FILE)) && is_readable(Cache::get_cache_path(Cache::CSS_CACHE_FILE));
    }

    public function enqueue_css_cache()
    {
        if (defined('SIUL_CSS_CACHE_WAS_LOADED')) {
            return;
        }

        if (! $this->is_cache_exists()) {
            return;
        }

        $handle = SIUL::WP_OPTION . '-cache';

        if (Config::get('performance.cache.inline_load', false)) {
            $css = file_get_contents(Cache::get_cache_path(Cache::CSS_CACHE_FILE));

            if ($css === false) {
                return;
            }

            echo sprintf("<style id=\"%s-css\">\n%s\n</style>", $handle, $css);
        } else {
            $version = (string) filemtime(Cache::get_cache_path(Cache::CSS_CACHE_FILE));
            wp_register_style($handle, Cache::get_cache_url(Cache::CSS_CACHE_FILE), [], $version);
            wp_print_styles($handle);
        }

        define('SIUL_CSS_CACHE_WAS_LOADED', true);
    }

    public function enqueue_play_cdn($display = true)
    {
        if ($display && defined('SIUL_PLAY_CDN_WAS_LOADED')) {
            return;
        }

        $template_path = plugin_dir_path(SIUL::FILE) . 'build/frontend/play-cdn.html';

        if (file_exists($template_path) === false) {
            return;
        }

        $template = file_get_contents($template_path);

        if ($template === false) {
            return;
        }

        $tailwind = get_option(SIUL::WP_OPTION . '_tailwind', base64_encode(json_encode(SIUL::default_tailwind())));
        $tailwind = apply_filters('f!yabe/siul/core/runtime:enqueue_play_cdn.tailwind', json_decode(base64_decode($tailwind)));

        $tw_version = Config::get('general.tailwindcss.version', 'latest');

        $template = str_replace(
            '<script src="https://cdn.tailwindcss.com"></script>',
            sprintf(
                '<script src="https://cdn.tailwindcss.com/%s"></script>',
                $tw_version && $tw_version !== 'latest' ? $tw_version : ''
            ),
            $template
        );

        $template = str_replace(
            '//-@-tailwindcss',
            preg_replace('/(?<!await )require\(/', 'await require(', $tailwind->config),
            $template
        );

        $template = str_replace(
            '<style type="text/siul-tailwindcss-main-css" id="siul-tailwindcss-main-css"></style>',
            sprintf('<style type="text/siul-tailwindcss-main-css" id="siul-tailwindcss-main-css">%s</style>', $tailwind->css),
            $template
        );

        if ($display) {
            echo $template;
            define('SIUL_PLAY_CDN_WAS_LOADED', true);
        } else {
            return $template;
        }
    }

    public function enqueue_module_autocomplete()
    {
        Asset::enqueue_entry('module-autocomplete', ['wp-hooks'], true);

        $handle = SIUL::WP_OPTION . ':module-autocomplete.js';

        wp_set_script_translations($handle, 'yabe-siul');

        wp_localize_script($handle, 'siul', [
            '_version' => SIUL::VERSION,
            'assets' => [
                'url' => Asset::asset_base_url(),
            ],
            'tailwind' => [
                'version' => Config::get('general.tailwindcss.version', 'latest'),
            ],
        ]);
    }
}
