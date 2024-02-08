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

namespace Yabe\Siul\Admin;

use SIUL;
use Yabe\Siul\Utils\Asset;

class AdminPage
{
    public function __construct()
    {
        add_action('admin_menu', fn () => $this->add_admin_menu(), 1_000_001);
    }

    public static function get_page_url(): string
    {
        return add_query_arg([
            'page' => SIUL::WP_OPTION,
        ], admin_url('admin.php'));
    }

    public function add_admin_menu()
    {
        $hook = add_menu_page(
            __('Yabe Siul', 'yabe-siul'),
            __('Yabe Siul', 'yabe-siul'),
            'manage_options',
            SIUL::WP_OPTION,
            fn () => $this->render(),
            'data:image/svg+xml;base64,PHN2ZyB2ZXJzaW9uPSIxLjEiIGlkPSJDYXBhXzEiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyIgeG1sbnM6eGxpbms9Imh0dHA6Ly93d3cudzMub3JnLzE5OTkveGxpbmsiIHg9IjBweCIgeT0iMHB4IiB2aWV3Qm94PSIwIDAgNTEyIDUxMiIgc3R5bGU9ImVuYWJsZS1iYWNrZ3JvdW5kOm5ldyAwIDAgNTEyIDUxMjsiIHhtbDpzcGFjZT0icHJlc2VydmUiPgoJPGc+CgkJPGc+CgkJCTxwYXRoIGQ9Ik0xNzYsMzg0SDE2Yy04LjgzMiwwLTE2LDcuMTY4LTE2LDE2YzAsOC44MzIsNy4xNjgsMTYsMTYsMTZoMTYwYzguODMyLDAsMTYsNy4yLDE2LDE2cy03LjE2OCwxNi0xNiwxNgoJCQljLTguODMyLDAtMTYsNy4xNjgtMTYsMTZjMCw4LjgzMiw3LjE2OCwxNiwxNiwxNmMyNi40NjQsMCw0OC0yMS41MzYsNDgtNDhTMjAyLjQ2NCwzODQsMTc2LDM4NHoiIC8+CgkJPC9nPgoJPC9nPgoJPGc+CgkJPGc+CgkJCTxwYXRoIGQ9Ik0yNDAsMjU2Yy04LjgzMiwwLTE2LDcuMTY4LTE2LDE2YzAsOC44MzIsNy4xNjgsMTYsMTYsMTZjOC44MzIsMCwxNiw3LjIsMTYsMTZzLTcuMTY4LDE2LTE2LDE2SDE2CgkJCWMtOC44MzIsMC0xNiw3LjE2OC0xNiwxNmMwLDguODMyLDcuMTY4LDE2LDE2LDE2aDIyNGMyNi40NjQsMCw0OC0yMS41MzYsNDgtNDhTMjY2LjQ2NCwyNTYsMjQwLDI1NnoiIC8+CgkJPC9nPgoJPC9nPgoJPGc+CgkJPGc+CgkJCTxwYXRoIGQ9Ik0yODgsMzJDMTY0LjI4OCwzMiw2NCwxMzIuMjg4LDY0LDI1NmMwLDEwLjg4LDEuMDU2LDIxLjUzNiwyLjU2LDMyaDEyOC4xOTJjLTEuNzkyLTQuOTkyLTIuNzUyLTEwLjQtMi43NTItMTYKCQkJYzAtMjYuNDY0LDIxLjUzNi00OCw0OC00OGM0NC4wOTYsMCw4MCwzNS45MDQsODAsODBjMCw0NC4xMjgtMzUuOTA0LDgwLTgwLDgwaC0wLjQxNkMyNDkuNzYsMzk3LjQwOCwyNTYsNDEzLjkyLDI1Niw0MzIKCQkJYzAsMTYuMDMyLTQuODY0LDMwLjk0NC0xMy4wMjQsNDMuNDU2YzE0LjU2LDIuOTc2LDI5LjYsNC41NDQsNDUuMDI0LDQuNTQ0YzEyMy43MTIsMCwyMjQtMTAwLjI4OCwyMjQtMjI0UzQxMS43MTIsMzIsMjg4LDMyeiIgLz4KCQk8L2c+Cgk8L2c+Cjwvc3ZnPg==',
            1_000_001
        );

        add_action('load-' . $hook, fn () => $this->init_hooks());
    }

    private function render()
    {
        add_filter('admin_footer_text', static fn ($text) => 'Thank you for using <b>Siul</b>!', 1_000_001);
        add_filter('update_footer', static fn ($text) => $text . ' | Siul ' . SIUL::VERSION, 1_000_001);
        echo '<div id="siul-app" class=""></div>';
    }

    private function init_hooks()
    {
        add_action('admin_enqueue_scripts', fn () => $this->enqueue_scripts(), 1_000_001);
    }

    private function enqueue_scripts()
    {
        Asset::enqueue_entry('admin', [], true);

        wp_set_script_translations(SIUL::WP_OPTION . ':admin.js', 'yabe-siul');

        wp_localize_script(SIUL::WP_OPTION . ':admin.js', 'siul', [
            '_version' => SIUL::VERSION,
            '_wpnonce' => wp_create_nonce(SIUL::WP_OPTION),
            'web_history' => self::get_page_url(),
            'rest_api' => [
                'nonce' => wp_create_nonce('wp_rest'),
                'root' => esc_url_raw(rest_url()),
                'namespace' => SIUL::REST_NAMESPACE,
                'url' => esc_url_raw(rest_url(SIUL::REST_NAMESPACE)),
            ],
            'assets' => [
                'url' => Asset::asset_base_url(),
            ],
            'site_meta' => [
                'name' => get_bloginfo('name'),
                'site_url' => get_site_url(),
            ],
        ]);
    }
}
