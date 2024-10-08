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
            new Editor();
            new Front();
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
}
