<?php

namespace WPAutopilot\Includes;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class InternalLinks {

    /**
     * Find related articles based on word overlap scoring.
     *
     * @param string $title   Title of the new article.
     * @param string $content Content of the new article.
     * @param int    $limit   Maximum number of related articles.
     * @return array Array of related articles with title and url.
     */
    public function find_related( $title, $content, $limit = 5 ) {
        global $wpdb;
        $table = $wpdb->prefix . 'wpa_internal_links';

        $rows = $wpdb->get_results( "SELECT post_id, title, url, keywords FROM {$table}", ARRAY_A );

        if ( empty( $rows ) ) {
            return array();
        }

        $input_words = $this->extract_words( $title . ' ' . $content );

        $scored = array();
        foreach ( $rows as $row ) {
            $keywords = array_filter( explode( ',', $row['keywords'] ) );
            if ( empty( $keywords ) ) {
                continue;
            }

            $overlap = count( array_intersect( $input_words, $keywords ) );
            if ( $overlap > 0 ) {
                $scored[] = array(
                    'post_id' => (int) $row['post_id'],
                    'title'   => $row['title'],
                    'url'     => $row['url'],
                    'score'   => $overlap,
                );
            }
        }

        // Sort by score descending.
        usort( $scored, function ( $a, $b ) {
            return $b['score'] - $a['score'];
        } );

        return array_slice( $scored, 0, $limit );
    }

    /**
     * Remove an article from the internal links index.
     *
     * @param int $post_id Post ID to remove.
     */
    public function remove_article( $post_id ) {
        global $wpdb;
        $table = $wpdb->prefix . 'wpa_internal_links';

        $wpdb->delete( $table, array( 'post_id' => $post_id ), array( '%d' ) );
    }

    /**
     * Add an article to the internal links index.
     *
     * @param int    $post_id Post ID.
     * @param string $title   Post title.
     * @param string $url     Post URL.
     * @param string $content Post content.
     */
    public function add_article( $post_id, $title, $url, $content ) {
        global $wpdb;
        $table = $wpdb->prefix . 'wpa_internal_links';

        // Remove existing entry for this post.
        $wpdb->delete( $table, array( 'post_id' => $post_id ), array( '%d' ) );

        $keywords = $this->extract_words( $title . ' ' . $content );
        $keywords_str = implode( ',', $keywords );

        $wpdb->insert(
            $table,
            array(
                'post_id'    => $post_id,
                'title'      => $title,
                'url'        => $url,
                'keywords'   => $keywords_str,
                'created_at' => current_time( 'mysql' ),
            ),
            array( '%d', '%s', '%s', '%s', '%s' )
        );
    }

    /**
     * Sync existing published posts into the internal links index.
     * Called on plugin activation.
     */
    public function sync_existing_posts() {
        $posts = get_posts( array(
            'post_type'      => 'post',
            'post_status'    => array( 'publish', 'draft' ),
            'posts_per_page' => -1,
            'fields'         => 'ids',
        ) );

        $count = 0;
        foreach ( $posts as $post_id ) {
            $post = get_post( $post_id );
            if ( $post ) {
                $this->add_article(
                    $post->ID,
                    $post->post_title,
                    get_permalink( $post->ID ),
                    $post->post_content
                );
                $count++;
            }
        }

        Logger::info( sprintf( 'Intern lenke-indeks synkronisert: %d artikler indeksert.', $count ) );
    }

    /**
     * Extract significant words from text for keyword matching.
     *
     * @param string $text Input text.
     * @return array Unique lowercase words (3+ chars), stopwords removed.
     */
    private function extract_words( $text ) {
        // Strip HTML.
        $text = wp_strip_all_tags( $text );

        // Lowercase.
        $text = mb_strtolower( $text );

        // Remove non-alphanumeric except spaces.
        $text = preg_replace( '/[^\p{L}\p{N}\s]/u', ' ', $text );

        // Split into words.
        $words = preg_split( '/\s+/', $text, -1, PREG_SPLIT_NO_EMPTY );

        // Remove short words and stopwords.
        $stopwords = array(
            'og', 'er', 'det', 'en', 'ei', 'ett', 'den', 'dei', 'som', 'til', 'med',
            'for', 'har', 'var', 'kan', 'vil', 'skal', 'fra', 'ved', 'men', 'seg',
            'sin', 'bli', 'ble', 'alle', 'andre', 'noen', 'ikke', 'bare', 'denne',
            'dette', 'disse', 'også', 'etter', 'over', 'under', 'mellom', 'mot',
            'the', 'and', 'for', 'that', 'this', 'with', 'from', 'are', 'was',
            'have', 'has', 'been', 'will', 'not', 'but', 'they', 'their', 'what',
        );

        $words = array_filter( $words, function ( $word ) use ( $stopwords ) {
            return mb_strlen( $word ) >= 3 && ! in_array( $word, $stopwords, true );
        } );

        return array_values( array_unique( $words ) );
    }
}
