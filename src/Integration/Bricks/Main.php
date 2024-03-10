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

namespace Yabe\Siul\Integration\Bricks;

use Yabe\Siul\Integration\IntegrationInterface;
use Yabe\Siul\Utils\Config;

/**
 * @author Joshua Gugun Siagian <suabahasa@gmail.com>
 */
class Main implements IntegrationInterface
{
    public function __construct()
    {
        add_filter('f!yabe/siul/core/cache:compile.providers', fn (array $providers): array => $this->register_provider($providers));

        if ($this->is_enabled()) {
            add_filter('f!yabe/siul/core/runtime:is_prevent_load', fn (bool $is_prevent_load): bool => $this->is_prevent_load($is_prevent_load));
            add_filter('f!yabe/siul/core/runtime:append_header.exclude_admin', fn (bool $is_exclude_admin): bool => $this->is_exclude_admin($is_exclude_admin));
            add_action('a!yabe/bricksbender/module/plainclasses:register_autocomplete', fn () => $this->register_bricksbender_autocomplete());
        }
    }

    public function get_name(): string
    {
        return 'bricks';
    }

    public function is_enabled(): bool
    {
        return (bool) apply_filters(
            'f!yabe/siul/integration/bricks:enabled',
            Config::get(sprintf(
                'integration.%s.enabled',
                $this->get_name()
            ), true)
        );
    }

    public function register_provider(array $providers): array
    {
        $providers[] = [
            'id' => $this->get_name(),
            'name' => 'Bricks Builder',
            'description' => 'Bricks Builder integration',
            'callback' => Compile::class,
            'enabled' => $this->is_enabled(),
        ];

        return $providers;
    }

    public function is_prevent_load(bool $is_prevent_load): bool
    {
        if ($is_prevent_load || !function_exists('bricks_is_builder_main')) {
            return $is_prevent_load;
        }

        return bricks_is_builder_main();
    }

    public function is_exclude_admin(bool $is_exclude_admin): bool
    {
        if ($is_exclude_admin || !function_exists('bricks_is_builder_iframe')) {
            return $is_exclude_admin;
        }

        return bricks_is_builder_iframe();
    }

    public function register_bricksbender_autocomplete()
    {
        if (!Config::get('general.autocomplete.engine.enabled', false)) {
            return;
        }

        wp_add_inline_script('bricksbender:editor', <<<JS
            document.addEventListener('DOMContentLoaded', function () {
                const iframeWindow = document.getElementById('bricks-builder-iframe');

                // Cached query for autocomplete items.
                const cached_query = new Map();
                async function searchQuery(query) {
                    // split query by `:` and search for each subquery
                    let prefix = query.split(':');
                    let q = prefix.pop();
                    for (let i = query.length; i > query.length - q.length; i--) {
                        const subquery = query.slice(0, i);
                        if (cached_query.has(subquery)) {
                            return cached_query.get(subquery);
                        }
                    }

                    const suggestions = await iframeWindow.contentWindow.wp.hooks.applyFilters('siul.module.autocomplete', query)
                        .then((suggestions) => {
                            return suggestions.map((s) => {
                                return {
                                    value: [...s.variants, s.name].join(':'),
                                    color: s.color,
                                };
                            });
                        });

                    cached_query.set(query, suggestions);

                    return suggestions;
                }

                wp.hooks.addFilter('bricksbender-autocomplete-items-query', 'bricksbender', async (autocompleteItems, text) => {
                    if (!iframeWindow.contentWindow.siul?.loaded?.module?.autocomplete) {
                        return autocompleteItems;
                    }

                    const siul_suggestions = await searchQuery(text);

                    return [...siul_suggestions, ...autocompleteItems];
                });
            });
        JS, 'after');
    }
}
