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
    /**
     * @param array $metadata
     */
    public function __invoke($metadata): array
    {
        return $this->get_contents($metadata);
    }

    public function get_contents($metadata): array
    {
        $contents = [];
        $post_types = apply_filters('f!yabe/siul/integration/gutenberg/compile:get_contents.post_types', [
            'post',
            'page',
            'wp_template',
        ]);

        $next_batch = $metadata['next_batch'] !== false ? $metadata['next_batch'] : 1;

        $wpQuery = new WP_Query([
            'posts_per_page' => apply_filters('f!yabe/siul/integration/gutenberg/compile:get_contents.post_per_page', (int) get_option('posts_per_page', 10)),
            'post_type' => $post_types,
            'paged' => $next_batch,
        ]);

        foreach ($wpQuery->posts as $post) {
            if (trim($post->post_content) === '' || trim($post->post_content) === '0') {
                continue;
            }

            $post_content = $post->post_content;

            if (apply_filters('f!yabe/siul/integration/gutenberg/compile:get_contents.render', true, $post)) {
                $post_content = \do_blocks($post_content);
                $post_content = \wptexturize($post_content);
                $post_content = \convert_smilies($post_content);
                $post_content = \shortcode_unautop($post_content);
                $post_content = \wp_filter_content_tags($post_content);
                $post_content = \do_shortcode($post_content);
            }

            $post_content = apply_filters('f!yabe/siul/integration/gutenberg/compile:get_contents.post_content', $post_content, $post);

            $contents[] = [
                'id' => $post->ID,
                'title' => sprintf('#%s: %s', $post->ID, $post->post_title),
                'content' => $post_content,
            ];
        }

        return [
            'metadata' => [
                'next_batch' => $wpQuery->max_num_pages > $next_batch ? $next_batch + 1 : false,
            ],
            'contents' => $contents,
        ];
    }
}
