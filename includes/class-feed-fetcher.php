<?php

namespace WPAutopilot\Includes;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class FeedFetcher {

    /**
     * Fetch new items from all active feeds.
     *
     * @return array Array of news items.
     */
    public function fetch_new_items() {
        $feeds_json = Settings::get( 'feeds', '[]' );
        $feeds      = json_decode( $feeds_json, true );

        if ( empty( $feeds ) || ! is_array( $feeds ) ) {
            Logger::warning( 'Ingen feeds konfigurert.' );
            return array();
        }

        $max_per_run      = (int) Settings::get( 'max_per_run', 3 );
        $keyword_include  = Settings::get( 'keyword_include', '' );
        $keyword_exclude  = Settings::get( 'keyword_exclude', '' );

        $include_words = self::parse_keywords( $keyword_include );
        $exclude_words = self::parse_keywords( $keyword_exclude );

        $all_items = array();

        foreach ( $feeds as $feed ) {
            if ( empty( $feed['url'] ) || empty( $feed['active'] ) ) {
                continue;
            }

            $items = $this->fetch_feed( $feed['url'], $feed['name'] ?? '' );
            $all_items = array_merge( $all_items, $items );
        }

        // Filter duplicates against seen articles.
        $all_items = $this->filter_seen( $all_items );

        // Filter by age (max 48 hours).
        $all_items = $this->filter_by_age( $all_items );

        // Keyword filtering.
        $all_items = $this->filter_by_keywords( $all_items, $include_words, $exclude_words );

        // Shuffle and limit.
        shuffle( $all_items );
        $all_items = array_slice( $all_items, 0, $max_per_run );

        Logger::info( sprintf( 'Feed-henting fullført: %d nye artikler funnet.', count( $all_items ) ) );

        return $all_items;
    }

    /**
     * Fetch and parse a single RSS feed.
     *
     * @param string $url       Feed URL.
     * @param string $feed_name Feed name for logging.
     * @return array
     */
    private function fetch_feed( $url, $feed_name = '' ) {
        $response = wp_remote_get( $url, array(
            'timeout' => 30,
            'headers' => array(
                'User-Agent' => 'WP-Autopilot/' . WPA_VERSION,
            ),
        ) );

        if ( is_wp_error( $response ) ) {
            Logger::error( sprintf( 'Feil ved henting av feed "%s": %s', $feed_name, $response->get_error_message() ) );
            return array();
        }

        $body = wp_remote_retrieve_body( $response );
        if ( empty( $body ) ) {
            Logger::warning( sprintf( 'Tom respons fra feed "%s".', $feed_name ) );
            return array();
        }

        // Suppress XML errors.
        $use_errors = libxml_use_internal_errors( true );
        $xml = simplexml_load_string( $body );
        libxml_use_internal_errors( $use_errors );

        if ( ! $xml ) {
            Logger::error( sprintf( 'Kunne ikke parse XML fra feed "%s".', $feed_name ) );
            return array();
        }

        $items = array();

        // Handle both RSS 2.0 and Atom feeds.
        if ( isset( $xml->channel->item ) ) {
            // RSS 2.0
            foreach ( $xml->channel->item as $item ) {
                $items[] = $this->parse_rss_item( $item, $feed_name );
            }
        } elseif ( isset( $xml->entry ) ) {
            // Atom
            foreach ( $xml->entry as $entry ) {
                $items[] = $this->parse_atom_entry( $entry, $feed_name );
            }
        }

        return $items;
    }

    /**
     * Parse an RSS 2.0 item.
     */
    private function parse_rss_item( $item, $feed_name ) {
        $title       = (string) $item->title;
        $link        = (string) $item->link;
        $description = (string) $item->description;
        $pub_date    = (string) $item->pubDate;

        return array(
            'title'       => wp_strip_all_tags( $title ),
            'url'         => esc_url_raw( $link ),
            'description' => wp_strip_all_tags( $description ),
            'pub_date'    => $pub_date ? strtotime( $pub_date ) : time(),
            'feed_name'   => $feed_name,
            'hash'        => md5( $title . $link ),
        );
    }

    /**
     * Parse an Atom entry.
     */
    private function parse_atom_entry( $entry, $feed_name ) {
        $title       = (string) $entry->title;
        $link        = '';
        $description = (string) $entry->summary;
        $pub_date    = (string) $entry->published ?: (string) $entry->updated;

        // Atom links can have href attribute.
        if ( isset( $entry->link ) ) {
            foreach ( $entry->link as $l ) {
                $attrs = $l->attributes();
                if ( ! $attrs || (string) $attrs->rel === 'alternate' || (string) $attrs->rel === '' ) {
                    $link = (string) $attrs->href;
                    break;
                }
            }
        }

        return array(
            'title'       => wp_strip_all_tags( $title ),
            'url'         => esc_url_raw( $link ),
            'description' => wp_strip_all_tags( $description ),
            'pub_date'    => $pub_date ? strtotime( $pub_date ) : time(),
            'feed_name'   => $feed_name,
            'hash'        => md5( $title . $link ),
        );
    }

    /**
     * Filter out items already seen (by MD5 hash).
     */
    private function filter_seen( $items ) {
        global $wpdb;
        $table = $wpdb->prefix . 'wpa_seen_articles';

        return array_filter( $items, function ( $item ) use ( $wpdb, $table ) {
            $exists = $wpdb->get_var(
                $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE hash = %s", $item['hash'] )
            );
            return (int) $exists === 0;
        } );
    }

    /**
     * Filter out items older than 48 hours.
     */
    private function filter_by_age( $items ) {
        $cutoff = time() - ( 48 * HOUR_IN_SECONDS );

        return array_filter( $items, function ( $item ) use ( $cutoff ) {
            return $item['pub_date'] >= $cutoff;
        } );
    }

    /**
     * Filter items by include/exclude keywords.
     */
    private function filter_by_keywords( $items, $include_words, $exclude_words ) {
        if ( empty( $include_words ) && empty( $exclude_words ) ) {
            return $items;
        }

        return array_filter( $items, function ( $item ) use ( $include_words, $exclude_words ) {
            $text = mb_strtolower( $item['title'] . ' ' . $item['description'] );

            // If include keywords are set, at least one must match.
            if ( ! empty( $include_words ) ) {
                $found = false;
                foreach ( $include_words as $word ) {
                    if ( mb_strpos( $text, $word ) !== false ) {
                        $found = true;
                        break;
                    }
                }
                if ( ! $found ) {
                    return false;
                }
            }

            // If exclude keywords are set, none must match.
            if ( ! empty( $exclude_words ) ) {
                foreach ( $exclude_words as $word ) {
                    if ( mb_strpos( $text, $word ) !== false ) {
                        return false;
                    }
                }
            }

            return true;
        } );
    }

    /**
     * Parse comma-separated keyword string into array of lowercase trimmed words.
     */
    private static function parse_keywords( $string ) {
        if ( empty( $string ) ) {
            return array();
        }

        $words = explode( ',', $string );
        $words = array_map( 'trim', $words );
        $words = array_map( 'mb_strtolower', $words );
        $words = array_filter( $words );

        return array_values( $words );
    }
}
