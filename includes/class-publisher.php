<?php

namespace WPAutopilot\Includes;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Publisher {

    /**
     * Publish an article to WordPress.
     *
     * @param array       $article       Article data from ArticleWriter.
     * @param int|null    $image_id      Attachment ID for featured image.
     * @param array       $news_item     Original news item data.
     * @param string|null $scheduled_date Optional date to schedule the post (Y-m-d H:i:s in GMT).
     * @return int|null Post ID or null on failure.
     */
    public function publish( array $article, $image_id, array $news_item, $scheduled_date = null ) {
        $post_status  = Settings::get( 'post_status', 'draft' );
        $post_author  = (int) Settings::get( 'post_author', 1 );
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
