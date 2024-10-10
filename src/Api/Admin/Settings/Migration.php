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

namespace Yabe\Siul\Api\Admin\Settings;

use ArrayAccess;
use SIUL;
use WindPress\WindPress\Core\Volume;
use WindPress\WindPress\Utils\Common;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use Yabe\Siul\Api\AbstractApi;
use Yabe\Siul\Api\ApiInterface;
use Symfony\Component\PropertyAccess\PropertyAccess;

class Migration extends AbstractApi implements ApiInterface
{
    public function __construct() {}

    public function get_prefix(): string
    {
        return 'admin/settings/migration';
    }

    public function register_custom_endpoints(): void
    {
        register_rest_route(
            self::API_NAMESPACE,
            $this->get_prefix() . '/do_migrate',
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => fn(\WP_REST_Request $wprestRequest): \WP_REST_Response => $this->do_migrate($wprestRequest),
                'permission_callback' => fn(\WP_REST_Request $wprestRequest): bool => $this->permission_callback($wprestRequest),
            ]
        );
    }

    public function do_migrate(WP_REST_Request $wprestRequest): WP_REST_Response
    {
        sleep(5);

        try {

            // Data dir
            $data_dir = Volume::data_dir_path();

            // Get the current tailwind settings
            $tailwind = get_option(SIUL::WP_OPTION . '_tailwind', base64_encode(json_encode(SIUL::default_tailwind())));
            $tailwind = apply_filters('f!yabe/siul/api/admin/tailwind:index', json_decode(base64_decode($tailwind)));

            // Copy main.css
            Common::save_file($tailwind->css, $data_dir . '/main.css');

            // Copy tailwind.config.js
            Common::save_file(
                $tailwind->config . PHP_EOL  . 'export default siul;',
                $data_dir . '/tailwind.config.js'
            );

            // Switch the tw version
            $options = json_decode(get_option('windpress_options', '{}'), null, 512, JSON_THROW_ON_ERROR);
            $path = 'general.tailwindcss.version';
            $value = 3;

            if (self::propertyAccessor()->isWritable($options, $path)) {
                self::propertyAccessor()->setValue($options, $path, $value);
            } else {
                self::data_set($options, $path, $value);
            }

            update_option('windpress_options', wp_json_encode($options, JSON_THROW_ON_ERROR));

            deactivate_plugins(SIUL::FILE);

            return new WP_REST_Response([
                'message' => 'Settings updated',
            ]);


        } catch (\Throwable $th) {
            error_log(print_r($th->getMessage(), true));

            return new WP_REST_Response([
                'message' => $th->getMessage(),
            ], 500);
        }
    }

    /**
     * Stores the instance of PropertyAccessor, implementing a Singleton pattern.
     */
    private static ?\Symfony\Component\PropertyAccess\PropertyAccessorInterface $propertyAccessor = null;

    /**
     * Set an item on an array or object using dot notation.
     *
     * @param  mixed  $target
     * @param  string|array  $key
     * @param  mixed  $value
     * @param  bool  $overwrite
     * @return mixed
     * 
     * @see https://github.com/laravel/framework/blob/a84c4f41d3fb1c57684bb417b1f0858300e769d0/src/Illuminate/Collections/helpers.php#L109
     */
    public static function data_set(&$target, $key, $value, $overwrite = true)
    {
        $segments = is_array($key) ? $key : explode('.', $key);

        if (($segment = array_shift($segments)) === '*') {
            if (!self::array_accessible($target)) {
                $target = [];
            }

            if ($segments) {
                foreach ($target as &$inner) {
                    self::data_set($inner, $segments, $value, $overwrite);
                }
            } elseif ($overwrite) {
                foreach ($target as &$inner) {
                    $inner = $value;
                }
            }
        } elseif (self::array_accessible($target)) {
            if ($segments) {
                if (!self::array_exists($target, $segment)) {
                    $target[$segment] = [];
                }

                self::data_set($target[$segment], $segments, $value, $overwrite);
            } elseif ($overwrite || !self::array_exists($target, $segment)) {
                $target[$segment] = $value;
            }
        } elseif (is_object($target)) {
            if ($segments) {
                if (!isset($target->{$segment})) {
                    $target->{$segment} = [];
                }

                self::data_set($target->{$segment}, $segments, $value, $overwrite);
            } elseif ($overwrite || !isset($target->{$segment})) {
                $target->{$segment} = $value;
            }
        } else {
            $target = [];

            if ($segments) {
                self::data_set($target[$segment], $segments, $value, $overwrite);
            } elseif ($overwrite) {
                $target[$segment] = $value;
            }
        }

        return $target;
    }

    /**
     * Determine if the given key exists in the provided array.
     *
     * @param  \ArrayAccess|array  $array
     * @param  string|int  $key
     * @return bool
     * 
     * @see https://github.com/laravel/framework/blob/a84c4f41d3fb1c57684bb417b1f0858300e769d0/src/Illuminate/Collections/Arr.php#L164
     */
    public static function array_exists($array, $key)
    {
        // if ($array instanceof Enumerable) {
        //     return $array->has($key);
        // }

        if ($array instanceof ArrayAccess) {
            return $array->offsetExists($key);
        }

        if (is_float($key)) {
            $key = (string) $key;
        }

        return array_key_exists($key, $array);
    }

    /**
     * Determine whether the given value is array accessible.
     *
     * @param  mixed  $value
     * @return bool
     * 
     * @see https://github.com/laravel/framework/blob/a84c4f41d3fb1c57684bb417b1f0858300e769d0/src/Illuminate/Collections/Arr.php#L21
     */
    public static function array_accessible($value)
    {
        return is_array($value) || $value instanceof ArrayAccess;
    }

    public static function propertyAccessor()
    {
        if (! isset(self::$propertyAccessor)) {
            self::$propertyAccessor = PropertyAccess::createPropertyAccessorBuilder()
                ->enableExceptionOnInvalidIndex()
                ->getPropertyAccessor();
        }

        return self::$propertyAccessor;
    }
}
