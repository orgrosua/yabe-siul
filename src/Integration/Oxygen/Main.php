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

namespace Yabe\Siul\Integration\Oxygen;

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
            add_action('a!yabe/oxybender/module/plainclasses:register_autocomplete', fn () => $this->register_oxybender_autocomplete());
        }
    }

    public function get_name(): string
    {
        return 'oxygen';
    }

    public function is_enabled(): bool
    {
        return (bool) apply_filters(
            'f!yabe/siul/integration/oxygen:enabled',
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
            'name' => 'Oxygen Builder',
            'description' => 'Oxygen Builder integration',
            'callback' => Compile::class,
            'enabled' => $this->is_enabled(),
        ];

        return $providers;
    }

    public function is_prevent_load(bool $is_prevent_load): bool
    {
        if ($is_prevent_load || ! $this->is_editor()) {
            return $is_prevent_load;
        }

        return $this->is_editor();
    }

    public function is_exclude_admin(bool $is_exclude_admin): bool
    {
        if ($is_exclude_admin || ! $this->is_preview()) {
            return $is_exclude_admin;
        }

        return $this->is_preview();
    }

    public function is_inside_builder(): bool
    {
        return isset($_GET['ct_builder']) && $_GET['ct_builder'];
    }

    public function is_preview(): bool
    {
        return $this->is_inside_builder() && isset($_GET['oxygen_iframe']) && $_GET['oxygen_iframe'];
    }

    public function is_editor(): bool
    {
        return $this->is_inside_builder() && ! isset($_GET['oxygen_iframe']);
    }

    public function register_oxybender_autocomplete()
    {
        wp_add_inline_script('oxybender:editor', <<<JS
            document.addEventListener('DOMContentLoaded', function () {
                const iframeWindow = document.getElementById('ct-artificial-viewport');

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

                wp.hooks.addFilter('oxybender-autocomplete-items-query', 'oxybender', async (autocompleteItems, text) => {
                    if (!iframeWindow.contentWindow.siul?.loaded?.module?.autocomplete) {
                        return autocompleteItems;
                    }

                    const siul_suggestions = await searchQuery(text);

                    return [...siul_suggestions, ...autocompleteItems];
                });

                // clear cache each 1 minute to avoid memory leak
                setInterval(() => {
                    cached_query.clear();
                }, 60000);
            });
        JS, 'after');
    }
}
