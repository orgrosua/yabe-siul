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

namespace Yabe\Siul\Integration\Gutenberg;

use WP_Query;

/**
 * @author Joshua Gugun Siagian <suabahasa@gmail.com>
 */
class Compile
{
    public function __invoke(): array
    {
        return $this->get_contents();
    }

    public function get_contents(): array
    {
        $contents = [];

        $wpQuery = new WP_Query([
            'posts_per_page' => -1,
            'post_type' => [
                'post',
                'page',
                'wp_template',
            ],
        ]);

        foreach ($wpQuery->posts as $post) {
            if (trim($post->post_content) === '' || trim($post->post_content) === '0') {
                continue;
            }

            $post_content = $post->post_content;
            $post_content = \do_blocks($post_content);
            $post_content = \wptexturize($post_content);
            $post_content = \convert_smilies($post_content);
            $post_content = \shortcode_unautop($post_content);
            $post_content = \wp_filter_content_tags($post_content);
            $post_content = \do_shortcode($post_content);

            $contents[] = [
                'id' => $post->ID,
                'title' => sprintf('#%s: %s', $post->ID, $post->post_title),
                'content' => $post_content,
            ];
        }

        return $contents;
    }
}
