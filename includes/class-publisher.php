<?php

namespace WPAutopilot\Includes;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Publisher {

    /**
     * Resolve which author to use based on author_method setting.
     *
     * @return int Author user ID.
     */
    public function resolve_author() {
        $method  = Settings::get( 'author_method', 'single' );
        $authors = json_decode( Settings::get( 'post_authors', '[]' ), true );

        // Fallback to single author if no multi-author config.
        if ( $method === 'single' || empty( $authors ) ) {
            return (int) Settings::get( 'post_author', 1 );
        }

        $author_ids = array_column( $authors, 'id' );

        if ( $method === 'random' ) {
            return (int) $author_ids[ array_rand( $author_ids ) ];
        }

        if ( $method === 'round_robin' ) {
            $index = (int) Settings::get( 'author_index', 0 );
            $author_id = (int) $author_ids[ $index % count( $author_ids ) ];
            Settings::set( 'author_index', $index + 1 );
            return $author_id;
        }

        if ( $method === 'percentage' ) {
            return $this->pick_weighted_author( $authors );
        }

        return (int) Settings::get( 'post_author', 1 );
    }

    /**
     * Pick an author using weighted random selection.
     *
     * @param array $authors Array of {id, weight} objects.
     * @return int Author user ID.
     */
    private function pick_weighted_author( array $authors ) {
        $total = array_sum( array_column( $authors, 'weight' ) );
        if ( $total <= 0 ) {
            return (int) $authors[0]['id'];
        }

        $rand = mt_rand( 1, $total );
        $cumulative = 0;

        foreach ( $authors as $author ) {
            $cumulative += (int) $author['weight'];
            if ( $rand <= $cumulative ) {
                return (int) $author['id'];
            }
        }

        return (int) $authors[0]['id'];
    }

    /**
     * Publish an article to WordPress.
     *
     * @param array       $article       Article data from ArticleWriter.
     * @param int|null    $image_id      Attachment ID for featured image.
     * @param array       $news_item     Original news item data.
     * @param string|null $scheduled_date Optional date to schedule the post (Y-m-d H:i:s in GMT).
     * @param int|null    $author_id     Override author ID (from resolve_author).
     * @return int|null Post ID or null on failure.
     */
    public function publish( array $article, $image_id, array $news_item, $scheduled_date = null, $author_id = null ) {
        $post_status  = Settings::get( 'post_status', 'draft' );
        $post_author  = $author_id ? (int) $author_id : (int) Settings::get( 'post_author', 1 );
        $default_cat  = (int) Settings::get( 'default_category', 0 );

        // Sanitize AI-generated content.
        $content = wp_kses_post( $article['content'] );
        $title   = sanitize_text_field( $article['title'] );
        $excerpt = sanitize_text_field( $article['excerpt'] );

        // Resolve category.
        $category_id = $this->resolve_category( $article['category_hint'], $default_cat );

        $post_data = array(
            'post_title'    => $title,
            'post_content'  => $content,
            'post_excerpt'  => $excerpt,
            'post_status'   => $post_status,
            'post_author'   => $post_author,
            'post_type'     => 'post',
            'post_category' => $category_id ? array( $category_id ) : array(),
        );

        // Schedule for future publishing if a date is provided and status is 'publish'.
        if ( $scheduled_date && $post_status === 'publish' ) {
            $post_data['post_status'] = 'future';
            $post_data['post_date']     = get_date_from_gmt( $scheduled_date );
            $post_data['post_date_gmt'] = $scheduled_date;
        }

        $post_id = wp_insert_post( $post_data, true );

        if ( is_wp_error( $post_id ) ) {
            Logger::error( 'Kunne ikke opprette innlegg: ' . $post_id->get_error_message() );
            return null;
        }

        // Set featured image.
        if ( $image_id ) {
            set_post_thumbnail( $post_id, $image_id );
        }

        // Mark as seen.
        $this->mark_as_seen( $news_item, $post_id );

        // Add to internal links index.
        $links = new InternalLinks();
        $links->add_article( $post_id, $title, get_permalink( $post_id ), $content );

        $actual_status = $scheduled_date && $post_status === 'publish' ? 'future' : $post_status;
        $schedule_info = $scheduled_date && $post_status === 'publish'
            ? sprintf( ', planlagt: %s', get_date_from_gmt( $scheduled_date, 'j. M H:i' ) )
            : '';

        Logger::info( sprintf(
            'Innlegg opprettet: "%s" (ID: %d, status: %s%s)',
            $title,
            $post_id,
            $actual_status,
            $schedule_info
        ) );

        return $post_id;
    }

    /**
     * Mark a news item as seen in the database.
     */
    private function mark_as_seen( array $news_item, $post_id ) {
        global $wpdb;
        $table = $wpdb->prefix . 'wpa_seen_articles';

        $wpdb->insert(
            $table,
            array(
                'hash'       => $news_item['hash'],
                'title'      => $news_item['title'],
                'url'        => $news_item['url'],
                'post_id'    => $post_id,
                'created_at' => current_time( 'mysql' ),
            ),
            array( '%s', '%s', '%s', '%d', '%s' )
        );
    }

    /**
     * Replace inline image markers with HTML figure elements.
     *
     * @param string $content      Article HTML content with [INLINE_IMAGE_N] markers.
     * @param array  $inline_images Array of inline image data with attachment_id set.
     * @return string Content with images inserted.
     */
    public function insert_inline_images( $content, array $inline_images ) {
        foreach ( $inline_images as $image ) {
            $marker = $image['marker'] ?? '';
            $att_id = $image['attachment_id'] ?? null;

            if ( empty( $marker ) || ! $att_id ) {
                // Remove the marker even if no image was generated.
                $content = str_replace( $marker, '', $content );
                continue;
            }

            $img_url = wp_get_attachment_url( $att_id );
            $alt     = esc_attr( $image['alt'] ?? '' );
            $caption = esc_html( $image['caption'] ?? '' );

            $figure = '<figure class="wpa-inline-image">';
            $figure .= '<img src="' . esc_url( $img_url ) . '" alt="' . $alt . '" loading="lazy">';
            if ( ! empty( $caption ) ) {
                $figure .= '<figcaption>' . $caption . '</figcaption>';
            }
            $figure .= '</figure>';

            $content = str_replace( $marker, $figure, $content );
        }

        return $content;
    }

    /**
     * Resolve category from AI hint or use default.
     *
     * @param string $hint       Category suggestion from AI.
     * @param int    $default_id Default category ID.
     * @return int Category ID.
     */
    private function resolve_category( $hint, $default_id ) {
        if ( empty( $hint ) ) {
            return $default_id;
        }

        $hint = sanitize_text_field( $hint );

        // Check if category already exists.
        $term = term_exists( $hint, 'category' );
        if ( $term ) {
            return (int) $term['term_id'];
        }

        // Create the category.
        $result = wp_insert_term( $hint, 'category' );
        if ( ! is_wp_error( $result ) ) {
            return (int) $result['term_id'];
        }

        return $default_id;
    }
}
