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

/**
 * Plugin constants.
 *
 * @since 1.0.0
 */
class SIUL
{
    /**
     * @var string
     */
    public const FILE = __DIR__ . '/yabe-siul.php';

    /**
     * @var string
     */
    public const VERSION = '2.0.2';

    /**
     * @var int
     */
    public const VERSION_ID = 20002;

    /**
     * @var int
     */
    public const MAJOR_VERSION = 2;

    /**
     * @var int
     */
    public const MINOR_VERSION = 0;

    /**
     * @var int
     */
    public const RELEASE_VERSION = 2;

    /**
     * @var string
     */
    public const EXTRA_VERSION = '';

    /**
     * @var string
     */
    public const WP_OPTION = 'siul';

    /**
     * @var string
     */
    public const DB_TABLE_PREFIX = 'siul';

    /**
     * The text domain should use the literal string 'yabe-siul' as the text domain.
     * This constant is used for reference only and should not be used as the actual text domain.
     *
     * @var string
     */
    public const TEXT_DOMAIN = 'yabe-siul';

    /**
     * @var string
     */
    public const REST_NAMESPACE = 'yabe-siul/v1';

    /**
     * @var array
     */
    public const EDD_STORE = [
        'store_url' => 'https://rosua.org',
        'item_id' => 2250,
        'author' => 'idrosua',
    ];

    public static function default_tailwind()
    {
        $default_preset = <<<JS
        const _merge = require('lodash.merge');
        /**
         * The Tailwind CSS configuration required by the SIUL plugin.
         *
         * @type {import('tailwindcss').Config} 
         */
        let siul = {};

        /* The autogenerated Tailwind CSS configuration from the Wizard goes below. */

        //-@-wizard

        /* Your custom Tailwind CSS configuration goes below. */

        /**
         * @type {import('tailwindcss').Config} 
         */
        let presetConfig = {
            theme: {
                extend: {},
            },
            plugins: [],
        };

        /* That's all, stop editing! Happy building. */

        _merge(siul, presetConfig);

        JS;

        $default_css = <<<CSS
        @tailwind base;
        @tailwind components;
        @tailwind utilities;

        @layer components {
            /* ... */
        }

        @layer components {
            /* ... */
        }
        CSS;

        $default_wizard = [
            [
                'name' => 'Default Profile',
                'id' => 'default',
                'preset' => (object) [],
            ],
        ];

        $default_config = $default_preset;

        return [
            'preset' => $default_preset,
            'css' => $default_css,
            'wizard' => $default_wizard,
            'config' => $default_config,
        ];
    }
}
