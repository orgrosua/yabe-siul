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

namespace Yabe\Siul\Api\Admin;

use SIUL;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use Yabe\Siul\Api\AbstractApi;
use Yabe\Siul\Api\ApiInterface;

class Tailwind extends AbstractApi implements ApiInterface
{
    public function __construct()
    {
    }

    public function get_prefix(): string
    {
        return 'admin/tailwind';
    }

    public function register_custom_endpoints(): void
    {
        register_rest_route(
            self::API_NAMESPACE,
            $this->get_prefix() . '/index',
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => fn (\WP_REST_Request $wprestRequest): \WP_REST_Response => $this->index($wprestRequest),
                'permission_callback' => fn (\WP_REST_Request $wprestRequest): bool => $this->permission_callback($wprestRequest),
            ]
        );

        register_rest_route(
            self::API_NAMESPACE,
            $this->get_prefix() . '/store',
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => fn (\WP_REST_Request $wprestRequest): \WP_REST_Response => $this->store($wprestRequest),
                'permission_callback' => fn (\WP_REST_Request $wprestRequest): bool => $this->permission_callback($wprestRequest),
            ]
        );
    }

    public function index(WP_REST_Request $wprestRequest): WP_REST_Response
    {
        $tailwind = get_option(SIUL::WP_OPTION . '_tailwind', base64_encode(json_encode(SIUL::default_tailwind())));

        $tailwind = apply_filters('f!yabe/siul/api/admin/tailwind:index', json_decode(base64_decode($tailwind)));

        return new WP_REST_Response([
            'tailwind' => $tailwind,
            '_default' => SIUL::default_tailwind(),
            '_custom' => [
                'css' => [
                    'prepend' => apply_filters('f!yabe/siul/core/runtime:enqueue_play_cdn.css.prepend', ''),
                    'append' => apply_filters('f!yabe/siul/core/runtime:enqueue_play_cdn.css.append', ''),
                ],
                'config' => [
                    'prepend' => apply_filters('f!yabe/siul/core/runtime:enqueue_play_cdn.config.prepend', ''),
                    'append' => apply_filters('f!yabe/siul/core/runtime:enqueue_play_cdn.config.append', ''),
                ],
            ]
        ]);
    }

    public function store(WP_REST_Request $wprestRequest): WP_REST_Response
    {
        $payload = $wprestRequest->get_json_params();

        $tailwind = $payload['tailwind'];

        $tailwind = apply_filters('f!yabe/siul/api/admin/tailwind:store', $tailwind);

        update_option(SIUL::WP_OPTION . '_tailwind', base64_encode(json_encode($tailwind)));

        return new WP_REST_Response([
            'message' => 'TailwindCSS config updated',
        ]);
    }
}
