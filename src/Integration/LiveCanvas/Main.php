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

namespace Yabe\Siul\Integration\LiveCanvas;

use SIUL;
use Yabe\Siul\Core\Runtime;
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
        }
    }

    public function get_name(): string
    {
        return 'livecanvas';
    }

    public function is_enabled(): bool
    {
        return (bool) apply_filters(
            'f!yabe/siul/integration/livecanvas:enabled',
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
            'name' => 'LiveCanvas',
            'description' => 'LiveCanvas integration',
            'callback' => Compile::class,
            'enabled' => $this->is_enabled(),
            'meta' => [
                'experimental' => true,
            ]
        ];

        return $providers;
    }
}
