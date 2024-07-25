<?php

/**
 * Yabe Siul - Tailwind CSS for WordPress
 *
 * @wordpress-plugin
 * Plugin Name:         Yabe Siul
 * Plugin URI:          https://siul.yabe.land
 * Description:         Tailwind CSS for WordPress
 * Version:             2.0.5
 * Requires at least:   6.0
 * Requires PHP:        7.4
 * Author:              Rosua
 * Author URI:          https://rosua.org
 * Donate link:         https://ko-fi.com/Q5Q75XSF7
 * Text Domain:         yabe-siul
 * Domain Path:         /languages
 *
 * @package             Yabe
 * @author              Joshua Gugun Siagian <suabahasa@gmail.com>
 */

declare(strict_types=1);

use Yabe\Siul\Plugin;
use Yabe\Siul\Utils\Requirement;

defined('ABSPATH') || exit;

if (file_exists(__DIR__ . '/vendor/scoper-autoload.php')) {
    require_once __DIR__ . '/vendor/scoper-autoload.php';
} else {
    require_once __DIR__ . '/vendor/autoload.php';
}

$requirement = new Requirement();
$requirement
    ->php('7.4')
    ->wp('6.0');

if ($requirement->met()) {
    Plugin::get_instance()->boot();
} else {
    add_action('admin_notices', fn () => $requirement->printNotice(), 0, 0);
}
