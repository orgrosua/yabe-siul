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

namespace Yabe\Siul\Integration\Blockstudio;

use Yabe\Siul\Core\Runtime;
use Yabe\Siul\Integration\IntegrationInterface;

/**
 * @author Joshua Gugun Siagian <suabahasa@gmail.com>
 */
class Main implements IntegrationInterface
{
    public function __construct()
    {
        add_filter('f!yabe/siul/core/cache:compile.providers', fn (array $providers): array => $this->register_provider($providers));
        add_action('admin_head', fn () => $this->admin_head());
    }

    public function get_name(): string
    {
        return 'blockstudio';
    }

    public function register_provider(array $providers): array
    {
        $providers[] = [
            'id' => $this->get_name(),
            'name' => 'Blockstudio',
            'description' => 'Blockstudio integration',
            'callback' => Compile::class,
        ];

        return $providers;
    }

    public function admin_head()
    {
        $screen = get_current_screen();

        if (is_admin() && $screen->id === 'toplevel_page_blockstudio') {
            $this->append_editor_markup();
        }
    }

    /**
     * @see https://blockstudio.dev/documentation/hooks/php/#editor-markup
     */
    public function append_editor_markup()
    {
        $markup = base64_encode(Runtime::get_instance()->enqueue_play_cdn(false));
        
        echo <<<HTML
            <script>
                (async () => {
                    while(!window.blockstudioEditorMarkup && typeof window.blockstudioEditorMarkup !== 'string') {
                        await new Promise(resolve => setTimeout(resolve, 100));
                    }
                    window.blockstudioEditorMarkup += atob('{$markup}');
                })();
            </script>
        HTML;
    }
}
