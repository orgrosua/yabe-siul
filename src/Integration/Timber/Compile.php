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

namespace Yabe\Siul\Integration\Timber;

use Symfony\Component\Finder\Finder;
use Timber\LocationManager;
use Timber\Timber;

/**
 * @author Joshua Gugun Siagian <suabahasa@gmail.com>
 */
class Compile
{
    public function __invoke(): array
    {
        if (! class_exists(Timber::class)) {
            return [];
        }

        return $this->get_contents();
    }

    public function get_contents(): array
    {
        $contents = [];

        $paths = LocationManager::get_locations();

        $finder = new Finder();

        $finder->in($paths);

        $finder->files()->name('*.twig');

        foreach ($finder as $file) {
            $template_file = $file->getPathname();

            if (! is_readable($template_file)) {
                continue;
            }

            $contents[] = [
                'name' => $file->getRelativePathname(),
                'content' => file_get_contents($template_file),
            ];
        }

        return $contents;
    }
}
