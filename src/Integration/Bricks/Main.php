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

/**
 * @author Joshua Gugun Siagian <suabahasa@gmail.com>
 */
class Main implements IntegrationInterface
{
    public function __construct()
    {
        add_filter('f!yabe/siul/core/cache:compile.providers', fn (array $providers): array => $this->register_provider($providers));
        add_filter('f!yabe/siul/core/runtime:is_prevent_load', fn (bool $is_prevent_load): bool => $this->is_prevent_load($is_prevent_load));
        add_filter('f!yabe/siul/core/runtime:append_header.exclude_admin', fn (bool $is_exclude_admin): bool => $this->is_exclude_admin($is_exclude_admin));
        add_action('a!yabe/bricksbender/module/plainclasses:register_autocomplete', fn () => $this->register_bricksbender_autocomplete());
    }

    public function get_name(): string
    {
        return 'bricks';
    }

    public function register_provider(array $providers): array
    {
        $providers[] = [
            'id' => $this->get_name(),
            'name' => 'Bricks Builder',
            'description' => 'Bricks Builder integration',
            'callback' => Compile::class,
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
        wp_add_inline_script('bricksbender:editor', <<<JS
            document.addEventListener('DOMContentLoaded', function () {
                iframeWindow = document.getElementById('bricks-builder-iframe');
    
                wp.hooks.addFilter('bricksbender-autocomplete-items-query', 'bricksbender', async (autocompleteItems, text) => {
                    if (!iframeWindow.contentWindow.siul?.loaded?.module?.autocomplete) {
                        return autocompleteItems;
                    }
                    
                    const siul_suggestions = await iframeWindow.contentWindow.wp.hooks.applyFilters('siul.module.autocomplete', text)
                        .then((suggestions) => suggestions.slice(0, 45))
                        .then((suggestions) => {
                            return suggestions.map((s) => {
                                return {
                                    value: [...s.variants, s.name].join(':'),
                                    color: s.color,
                                };
                            });
                        });
    
                    return [...siul_suggestions, ...autocompleteItems];
                });
            });
        JS, 'after');
    }
}
