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
use Yabe\Siul\Utils\AssetVite;
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
        if (!isset(self::$instance)) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function init()
    {
        if (!is_admin()) {
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

        if ($is_cache_enabled && $this->is_cache_exists() && !$is_exclude_admin) {
            add_action('wp_head', fn () => $this->enqueue_css_cache(), 1_000_001);
        } else {
            /**
             * Temporary workaround for WordPress 6.5 and above.
             * @see https://core.trac.wordpress.org/ticket/61771
             * @see https://make.wordpress.org/core/2024/03/04/script-modules-in-6-5/
             */
            if (version_compare(get_bloginfo('version'), '6.5.0', '>=') && wp_is_block_theme()) {
                // unregister 'print_import_map' from wp_head
                remove_action('wp_head', array(wp_script_modules(), 'print_import_map'));

                add_action('wp_head', fn () => $this->enqueue_importmap_wp65(), 1);
                add_action('wp_head', fn () => $this->print_import_map(), 9);
            } else {
                add_action('wp_head', fn () => $this->enqueue_importmap(), 1);
            }

            add_action('wp_head', fn () => $this->enqueue_play_cdn(), 1_000_001);

            if (Config::get('general.compiler.embedded.enabled', false) && current_user_can('manage_options')) {
                add_action('wp_head', fn () => $this->enqueue_compiler(), 1_000_001);
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

        if (!$this->is_cache_exists()) {
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

    /**
     * @see https://make.wordpress.org/core/2024/03/04/script-modules-in-6-5/
     */
    public function enqueue_importmap_wp65($display = true)
    {
        if ($display && defined('SIUL_IMPORTMAP_WAS_LOADED')) {
            return;
        }

        // Registers a module, it can now appear as a dependency or be enqueued.
        wp_register_script_module('fs', 'https://ga.jspm.io/npm:@jspm/core@2.0.1/nodelibs/browser/fs.js', [], null);
        wp_register_script_module('postcss', 'https://ga.jspm.io/npm:postcss@8.4.38/lib/postcss.mjs', [], null);
        wp_register_script_module('tailwindcss', 'https://ga.jspm.io/npm:tailwindcss@3.4.3/lib/index.js', [], null);
        wp_register_script_module('tailwindcss/resolveConfig', 'https://ga.jspm.io/npm:tailwindcss@3.4.3/resolveConfig.js', [], null);
        wp_register_script_module('tailwindcss/lib/lib/evaluateTailwindFunctions', 'https://ga.jspm.io/npm:tailwindcss@3.4.3/lib/lib/evaluateTailwindFunctions.js', [], null);
        wp_register_script_module('tailwindcss/lib/lib/generateRules', 'https://ga.jspm.io/npm:tailwindcss@3.4.3/lib/lib/generateRules.js', [], null);
        wp_register_script_module('tailwindcss/lib/lib/setupContextUtils', 'https://ga.jspm.io/npm:tailwindcss@3.4.3/lib/lib/setupContextUtils.js', [], null);

        // Registers and enqueues a module.
        wp_enqueue_script_module(
            'yabe-siul-importmap',
            plugin_dir_url(SIUL::FILE) . 'build/public/importmap.js',
            [
                ['id' => 'fs', 'import' => 'dynamic'],
                ['id' => 'postcss', 'import' => 'dynamic'],
                ['id' => 'tailwindcss', 'import' => 'dynamic'],
                ['id' => 'tailwindcss/resolveConfig', 'import' => 'dynamic'],
                ['id' => 'tailwindcss/lib/lib/evaluateTailwindFunctions', 'import' => 'dynamic'],
                ['id' => 'tailwindcss/lib/lib/generateRules', 'import' => 'dynamic'],
                ['id' => 'tailwindcss/lib/lib/setupContextUtils', 'import' => 'dynamic'],
            ]
        );
    }

    public function enqueue_importmap($display = true)
    {
        if ($display && defined('SIUL_IMPORTMAP_WAS_LOADED')) {
            return;
        }

        $template_path = plugin_dir_path(SIUL::FILE) . 'build/public/importmap.html';

        if (file_exists($template_path) === false) {
            return;
        }

        $template = file_get_contents($template_path);

        if ($template === false) {
            return;
        }

        if ($display) {
            echo $template;
            define('SIUL_IMPORTMAP_WAS_LOADED', true);
        } else {
            return $template;
        }
    }

    public function enqueue_play_cdn($display = true)
    {
        if ($display && defined('SIUL_PLAY_CDN_WAS_LOADED')) {
            return;
        }

        $template_path = plugin_dir_path(SIUL::FILE) . 'build/public/play-cdn.html';

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
            sprintf(
                "%s\n%s\n%s",
                apply_filters('f!yabe/siul/core/runtime:enqueue_play_cdn.config.prepend', ''),
                preg_replace('/(?<!await )require\(/', 'await require(', $tailwind->config),
                apply_filters('f!yabe/siul/core/runtime:enqueue_play_cdn.config.append', '')
            ),
            $template
        );

        $template = str_replace(
            '<style type="text/siul-tailwindcss-main-css" id="siul-tailwindcss-main-css"></style>',
            sprintf(
                '<style type="text/siul-tailwindcss-main-css" id="siul-tailwindcss-main-css">' . "%s\n%s\n%s" . '</style>',
                apply_filters('f!yabe/siul/core/runtime:enqueue_play_cdn.css.prepend', ''),
                $tailwind->css,
                apply_filters('f!yabe/siul/core/runtime:enqueue_play_cdn.css.append', '')
            ),
            $template
        );

        $template = str_replace(
            'https://esm.sh/tailwindcss/src/css/preflight.css?raw',
            sprintf('https://esm.sh/tailwindcss@%s/src/css/preflight.css?raw', $tw_version),
            $template
        );

        if ($display) {
            echo $template;
            define('SIUL_PLAY_CDN_WAS_LOADED', true);
        } else {
            return $template;
        }
    }

    public function enqueue_compiler()
    {
        $handle = SIUL::WP_OPTION . ':lib-compiler';

        AssetVite::get_instance()->enqueue_asset('assets/integration/common/compiler-bootstrap.js', [
            'handle' => $handle,
            'in_footer' => true,
        ]);

        wp_localize_script($handle, 'siul', [
            '_version' => SIUL::VERSION,
            'rest_api' => [
                'nonce' => wp_create_nonce('wp_rest'),
                'root' => esc_url_raw(rest_url()),
                'namespace' => SIUL::REST_NAMESPACE,
                'url' => esc_url_raw(rest_url(SIUL::REST_NAMESPACE)),
            ],
        ]);
    }

    public function print_import_map()
    {
        $script_modules = wp_script_modules();

        $script_modules = leak($script_modules);

        $import_map = $script_modules->get_import_map();

        $import_map['scopes'] = $this->get_import_scopes();

        if (!empty($import_map['imports'])) {
            global $wp_scripts;
            if (isset($wp_scripts)) {
                wp_print_inline_script_tag(
                    wp_get_script_polyfill(
                        $wp_scripts,
                        array(
                            'HTMLScriptElement.supports && HTMLScriptElement.supports("importmap")' => 'wp-polyfill-importmap',
                        )
                    ),
                    array(
                        'id' => 'wp-load-polyfill-importmap',
                    )
                );
            }
            wp_print_inline_script_tag(
                wp_json_encode($import_map, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES),
                array(
                    'type' => 'importmap',
                    'id'   => 'wp-importmap',
                )
            );
        }
    }

    public function get_import_scopes()
    {
        return [
            'https://esm.sh/' => [
                'https://esm.sh/v135/node_fs.js' => 'https://ga.jspm.io/npm:@jspm/core@2.0.1/nodelibs/browser/fs.js',
                'fs' => 'https://ga.jspm.io/npm:@jspm/core@2.0.1/nodelibs/browser/fs.js',
            ],
            'https://ga.jspm.io/' => [
                "@alloc/quick-lru" => "https://ga.jspm.io/npm:@alloc/quick-lru@5.2.0/index.js",
                "@jridgewell/gen-mapping" => "https://ga.jspm.io/npm:@jridgewell/gen-mapping@0.3.5/dist/gen-mapping.umd.js",
                "@jridgewell/resolve-uri" => "https://ga.jspm.io/npm:@jridgewell/resolve-uri@3.1.2/dist/resolve-uri.umd.js",
                "@jridgewell/set-array" => "https://ga.jspm.io/npm:@jridgewell/set-array@1.2.1/dist/set-array.umd.js",
                "@jridgewell/sourcemap-codec" => "https://ga.jspm.io/npm:@jridgewell/sourcemap-codec@1.4.15/dist/sourcemap-codec.umd.js",
                "@jridgewell/trace-mapping" => "https://ga.jspm.io/npm:@jridgewell/trace-mapping@0.3.25/dist/trace-mapping.umd.js",
                "@nodelib/fs.scandir" => "https://ga.jspm.io/npm:@nodelib/fs.scandir@2.1.5/out/index.js",
                "@nodelib/fs.stat" => "https://ga.jspm.io/npm:@nodelib/fs.stat@2.0.5/out/index.js",
                "@nodelib/fs.walk" => "https://ga.jspm.io/npm:@nodelib/fs.walk@1.2.8/out/index.js",
                "@tailwindcss/line-clamp" => "https://ga.jspm.io/npm:@tailwindcss/line-clamp@0.4.4/src/index.js",
                "assert" => "https://ga.jspm.io/npm:@jspm/core@2.0.1/nodelibs/browser/assert.js",
                "braces" => "https://ga.jspm.io/npm:braces@3.0.2/index.js",
                "buffer" => "https://ga.jspm.io/npm:@jspm/core@2.0.1/nodelibs/browser/buffer.js",
                "camelcase-css" => "https://ga.jspm.io/npm:camelcase-css@2.0.1/index-es5.js",
                "crypto" => "https://ga.jspm.io/npm:@jspm/core@2.0.1/nodelibs/browser/crypto.js",
                "cssesc" => "https://ga.jspm.io/npm:cssesc@3.0.0/cssesc.js",
                "didyoumean" => "https://ga.jspm.io/npm:didyoumean@1.2.2/didYouMean-1.2.1.js",
                "dlv" => "https://ga.jspm.io/npm:dlv@1.1.3/dist/dlv.umd.js",
                "events" => "https://ga.jspm.io/npm:@jspm/core@2.0.1/nodelibs/browser/events.js",
                "fast-glob" => "https://ga.jspm.io/npm:fast-glob@3.3.2/out/index.js",
                "fastq" => "https://ga.jspm.io/npm:fastq@1.17.1/queue.js",
                "fill-range" => "https://ga.jspm.io/npm:fill-range@7.0.1/index.js",
                "fs" => "https://ga.jspm.io/npm:@jspm/core@2.0.1/nodelibs/browser/fs.js",
                "glob-parent" => "https://ga.jspm.io/npm:glob-parent@6.0.2/index.js",
                "is-extglob" => "https://ga.jspm.io/npm:is-extglob@2.1.1/index.js",
                "is-glob" => "https://ga.jspm.io/npm:is-glob@4.0.3/index.js",
                "is-number" => "https://ga.jspm.io/npm:is-number@7.0.0/index.js",
                "jiti" => "https://ga.jspm.io/npm:jiti@1.21.0/lib/index.js",
                "jiti/dist/babel.js" => "https://ga.jspm.io/npm:jiti@1.21.0/dist/babel.js",
                "lines-and-columns" => "https://ga.jspm.io/npm:lines-and-columns@1.2.4/build/index.js",
                "merge2" => "https://ga.jspm.io/npm:merge2@1.4.1/index.js",
                "micromatch" => "https://ga.jspm.io/npm:micromatch@4.0.5/index.js",
                "module" => "https://ga.jspm.io/npm:@jspm/core@2.0.1/nodelibs/browser/module.js",
                "nanoid/non-secure" => "https://ga.jspm.io/npm:nanoid@3.3.7/non-secure/index.js",
                "normalize-path" => "https://ga.jspm.io/npm:normalize-path@3.0.0/index.js",
                "object-hash" => "https://ga.jspm.io/npm:object-hash@3.0.0/dist/object_hash.js",
                "os" => "https://ga.jspm.io/npm:@jspm/core@2.0.1/nodelibs/browser/os.js",
                "path" => "https://ga.jspm.io/npm:@jspm/core@2.0.1/nodelibs/browser/path.js",
                "perf_hooks" => "https://ga.jspm.io/npm:@jspm/core@2.0.1/nodelibs/browser/perf_hooks.js",
                "picocolors" => "https://ga.jspm.io/npm:picocolors@1.0.0/picocolors.browser.js",
                "picomatch" => "https://ga.jspm.io/npm:picomatch@2.3.1/index.js",
                "picomatch/lib/utils" => "https://ga.jspm.io/npm:picomatch@2.3.1/lib/utils.js",
                "postcss" => "https://ga.jspm.io/npm:postcss@8.4.38/lib/postcss.js",
                "postcss-js" => "https://ga.jspm.io/npm:postcss-js@4.0.1/index.js",
                "postcss-nested" => "https://ga.jspm.io/npm:postcss-nested@6.0.1/index.js",
                "postcss-selector-parser" => "https://ga.jspm.io/npm:postcss-selector-parser@6.0.16/dist/index.js",
                "postcss-selector-parser/dist/util/unesc" => "https://ga.jspm.io/npm:postcss-selector-parser@6.0.16/dist/util/unesc.js",
                "process" => "https://ga.jspm.io/npm:@jspm/core@2.0.1/nodelibs/browser/process-production.js",
                "queue-microtask" => "https://ga.jspm.io/npm:queue-microtask@1.2.3/index.js",
                "reusify" => "https://ga.jspm.io/npm:reusify@1.0.4/reusify.js",
                "run-parallel" => "https://ga.jspm.io/npm:run-parallel@1.2.0/index.js",
                "source-map-js" => "https://ga.jspm.io/npm:source-map-js@1.2.0/source-map.js",
                "stream" => "https://ga.jspm.io/npm:@jspm/core@2.0.1/nodelibs/browser/stream.js",
                "sucrase" => "https://ga.jspm.io/npm:sucrase@3.35.0/dist/esm/index.js",
                "tailwindcss/plugin" => "https://ga.jspm.io/npm:tailwindcss@3.4.3/plugin.js",
                "to-regex-range" => "https://ga.jspm.io/npm:to-regex-range@5.0.1/index.js",
                "ts-interface-checker" => "https://ga.jspm.io/npm:ts-interface-checker@0.1.13/dist/index.js",
                "tty" => "https://ga.jspm.io/npm:@jspm/core@2.0.1/nodelibs/browser/tty.js",
                "url" => "https://ga.jspm.io/npm:@jspm/core@2.0.1/nodelibs/browser/url.js",
                "util" => "https://ga.jspm.io/npm:@jspm/core@2.0.1/nodelibs/browser/util.js",
                "util-deprecate" => "https://ga.jspm.io/npm:util-deprecate@1.0.2/browser.js",
                "v8" => "https://ga.jspm.io/npm:@jspm/core@2.0.1/nodelibs/browser/v8.js",
                "vm" => "https://ga.jspm.io/npm:@jspm/core@2.0.1/nodelibs/browser/vm.js"
            ],
            'https://ga.jspm.io/npm:fast-glob@3.3.2/' => [
                "glob-parent" => "https://ga.jspm.io/npm:glob-parent@5.1.2/index.js"
            ],
            'https://ga.jspm.io/npm:postcss@8.4.38/lib/fromJSON.js' => [
                "nanoid/non-secure" => "https://ga.jspm.io/npm:nanoid@3.3.7/non-secure/index.cjs"
            ],
            'https://ga.jspm.io/npm:postcss@8.4.38/lib/input.js' => [
                "nanoid/non-secure" => "https://ga.jspm.io/npm:nanoid@3.3.7/non-secure/index.cjs"
            ],
            'https://ga.jspm.io/npm:postcss@8.4.38/lib/map-generator.js' => [
                "nanoid/non-secure" => "https://ga.jspm.io/npm:nanoid@3.3.7/non-secure/index.cjs"
            ],
            'https://ga.jspm.io/npm:postcss@8.4.38/lib/no-work-result.js' => [
                "nanoid/non-secure" => "https://ga.jspm.io/npm:nanoid@3.3.7/non-secure/index.cjs"
            ],
            'https://ga.jspm.io/npm:postcss@8.4.38/lib/parse.js' => [
                "nanoid/non-secure" => "https://ga.jspm.io/npm:nanoid@3.3.7/non-secure/index.cjs"
            ],
            'https://ga.jspm.io/npm:postcss@8.4.38/lib/postcss.js' => [
                "nanoid/non-secure" => "https://ga.jspm.io/npm:nanoid@3.3.7/non-secure/index.cjs"
            ],
            'https://ga.jspm.io/npm:postcss@8.4.38/lib/processor.js' => [
                "nanoid/non-secure" => "https://ga.jspm.io/npm:nanoid@3.3.7/non-secure/index.cjs"
            ],
            'https://ga.jspm.io/npm:tailwindcss@3.4.3/_/AI7PbZ8Q.js' => [
                "postcss-js" => "https://ga.jspm.io/npm:postcss-js@4.0.1/index.mjs"
            ]
        ];
    }
}
