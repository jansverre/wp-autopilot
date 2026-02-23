<?php

namespace WPAutopilot\Includes;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Cron {

    const HOOK = 'wpa_run_autopilot';

    /**
     * Register cron hooks and custom intervals.
     */
    public function register() {
        add_filter( 'cron_schedules', array( $this, 'add_schedules' ) );
        add_action( self::HOOK, array( $this, 'run' ) );

        // Ensure event is scheduled if enabled.
        if ( Settings::get( 'enabled' ) && ! wp_next_scheduled( self::HOOK ) ) {
            $interval = Settings::get( 'cron_interval', 'every_6_hours' );
            wp_schedule_event( time(), $interval, self::HOOK );
        }
    }

    /**
     * Add custom cron intervals.
     */
    public function add_schedules( $schedules ) {
        $schedules['every_2_hours'] = array(
            'interval' => 2 * HOUR_IN_SECONDS,
            'display'  => 'Hver 2. time',
        );
        $schedules['every_6_hours'] = array(
            'interval' => 6 * HOUR_IN_SECONDS,
            'display'  => 'Hver 6. time',
        );
        $schedules['every_12_hours'] = array(
            'interval' => 12 * HOUR_IN_SECONDS,
            'display'  => 'Hver 12. time',
        );
        return $schedules;
    }

    /**
     * Main autopilot run. Called by WP-Cron or manually.
     */
    public function run() {
        // Check if enabled (skip check if called manually via AJAX).
        if ( ! defined( 'WPA_MANUAL_RUN' ) && ! Settings::get( 'enabled' ) ) {
            return;
        }

        // Check work hours (skip for manual runs).
        if ( ! defined( 'WPA_MANUAL_RUN' ) && ! $this->is_within_work_hours() ) {
            Logger::info( 'Utenfor arbeidstid. Hopper over kjøring.' );
            return;
        }

        // Check daily limit.
        if ( $this->daily_count() >= (int) Settings::get( 'max_per_day', 10 ) ) {
            Logger::info( 'Daglig grense nådd. Hopper over kjøring.' );
            return;
        }

        Logger::info( 'Autopilot-kjøring startet.' );

        $fetcher   = new FeedFetcher();
        $writer    = new ArticleWriter();
        $imager    = new ImageGenerator();
        $linker    = new InternalLinks();
        $publisher = new Publisher();

        $items = $fetcher->fetch_new_items();

        if ( empty( $items ) ) {
            Logger::info( 'Ingen nye artikler å behandle.' );
            return;
        }

        $published   = 0;
        $max_per_day = (int) Settings::get( 'max_per_day', 10 );
        $item_count  = count( $items );

        // Calculate spread-out schedule times for the articles.
        $schedule_times = $this->calculate_schedule_times( $item_count );

        $inline_images_enabled = (bool) Settings::get( 'inline_images_enabled' );

        $index = 0;
        foreach ( $items as $item ) {
            // Re-check daily limit for each item.
            if ( $this->daily_count() >= $max_per_day ) {
                Logger::info( 'Daglig grense nådd under kjøring.' );
                break;
            }

            // Resolve author for this article.
            $author_id = $publisher->resolve_author();

            // Find related articles for internal linking.
            $related = $linker->find_related( $item['title'], $item['description'] );

            // Generate article with AI (passing author_id for per-author style).
            $article = $writer->write( $item, $related, $author_id );
            if ( ! $article ) {
                Logger::warning( sprintf( 'Kunne ikke generere artikkel for: "%s"', $item['title'] ) );
                $index++;
                continue;
            }

            // Log text generation cost.
            CostTracker::log_text( null, $article['_model'], $article['_response_data'] );

            // Generate featured image.
            $image_id = null;
            if ( ! empty( $article['image_prompt'] ) ) {
                $image_id = $imager->generate(
                    $article['image_prompt'],
                    $article['title'],
                    $article['image_alt'] ?? '',
                    $article['image_caption'] ?? ''
                );
            }

            // Generate inline images if enabled.
            $inline_images = array();
            if ( $inline_images_enabled && ! empty( $article['inline_images'] ) ) {
                $img_index = 1;
                foreach ( $article['inline_images'] as $inline ) {
                    $result = $imager->generate_inline(
                        $inline['prompt'] ?? '',
                        $inline['alt'] ?? '',
                        $inline['caption'] ?? '',
                        $article['title'],
                        $img_index
                    );

                    $inline['attachment_id'] = $result['attachment_id'];
                    $inline_images[] = $inline;

                    // Log inline image cost.
                    if ( $result['model'] ) {
                        CostTracker::log_image( null, 'inline_image', $result['model'] );
                    }

                    $img_index++;
                }
            }

            // Insert inline images into content before publishing.
            if ( ! empty( $inline_images ) ) {
                $article['content'] = $publisher->insert_inline_images( $article['content'], $inline_images );
            }

            // Get scheduled time for this article (null for first article = publish now).
            $scheduled_date = isset( $schedule_times[ $index ] ) ? $schedule_times[ $index ] : null;

            // Publish (or schedule).
            $post_id = $publisher->publish( $article, $image_id, $item, $scheduled_date, $author_id );
            if ( $post_id ) {
                $published++;

                // Update cost records with the actual post_id.
                $this->update_cost_post_id( $post_id, $article, $image_id, $inline_images );
            }

            $index++;
        }

        Logger::info( sprintf( 'Autopilot-kjøring fullført. %d artikler opprettet.', $published ) );
    }

    /**
     * Update cost tracker entries with the real post ID after publishing.
     */
    private function update_cost_post_id( $post_id, $article, $image_id, $inline_images ) {
        global $wpdb;
        $table = $wpdb->prefix . 'wpa_costs';

        // Update the most recent text cost entry (the one we just logged with null post_id).
        $wpdb->query( $wpdb->prepare(
            "UPDATE {$table} SET post_id = %d WHERE post_id IS NULL AND type = 'text' ORDER BY id DESC LIMIT 1",
            $post_id
        ) );

        // Log featured image cost with post_id.
        if ( $image_id ) {
            $featured_model = Settings::get( 'image_custom_model' );
            if ( empty( $featured_model ) ) {
                $featured_model = Settings::get( 'image_model', 'fal-ai/flux-2-pro' );
            }
            CostTracker::log_image( $post_id, 'featured_image', $featured_model );
        }

        // Update inline image costs with post_id.
        if ( ! empty( $inline_images ) ) {
            $count = count( $inline_images );
            $wpdb->query( $wpdb->prepare(
                "UPDATE {$table} SET post_id = %d WHERE post_id IS NULL AND type = 'inline_image' ORDER BY id DESC LIMIT %d",
                $post_id,
                $count
            ) );
        }
    }

    /**
     * Reschedule cron event (called when settings change).
     */
    public function reschedule() {
        // Clear existing.
        wp_clear_scheduled_hook( self::HOOK );

        // Re-schedule if enabled.
        if ( Settings::get( 'enabled' ) ) {
            $interval = Settings::get( 'cron_interval', 'every_6_hours' );
            wp_schedule_event( time(), $interval, self::HOOK );
        }
    }

    /**
     * Calculate evenly spread schedule times for articles.
     *
     * First article publishes immediately (null), the rest are spread
     * evenly from now until the next scheduled cron run.
     *
     * @param int $count Number of articles to schedule.
     * @return array Array of GMT datetime strings (null for first = publish now).
     */
    private function calculate_schedule_times( $count ) {
        if ( $count <= 1 ) {
            return array( null );
        }

        // Find the interval until next run.
        $interval_key = Settings::get( 'cron_interval', 'every_6_hours' );
        $intervals    = array(
            'hourly'         => HOUR_IN_SECONDS,
            'every_2_hours'  => 2 * HOUR_IN_SECONDS,
            'every_6_hours'  => 6 * HOUR_IN_SECONDS,
            'every_12_hours' => 12 * HOUR_IN_SECONDS,
            'daily'          => DAY_IN_SECONDS,
        );

        $total_seconds = $intervals[ $interval_key ] ?? ( 6 * HOUR_IN_SECONDS );

        // If work hours are enabled, cap the spread window to remaining work hours today.
        if ( Settings::get( 'work_hours_enabled' ) ) {
            $remaining = $this->remaining_work_seconds();
            if ( $remaining > 0 && $remaining < $total_seconds ) {
                $total_seconds = $remaining;
            }
        }

        // Leave a small buffer at the end (don't schedule right at the boundary).
        $total_seconds = (int) ( $total_seconds * 0.9 );

        // Spread: first one now, rest evenly across the window.
        $gap   = (int) floor( $total_seconds / $count );
        $times = array();
        $now   = time();

        for ( $i = 0; $i < $count; $i++ ) {
            if ( $i === 0 ) {
                $times[] = null; // First article publishes immediately.
            } else {
                $future  = $now + ( $gap * $i );
                $times[] = gmdate( 'Y-m-d H:i:s', $future );
            }
        }

        return $times;
    }

    /**
     * Calculate remaining seconds in today's work hours window.
     *
     * @return int Remaining seconds, or 0 if outside work hours.
     */
    private function remaining_work_seconds() {
        $current_hour = (int) current_time( 'G' );
        $current_min  = (int) current_time( 'i' );
        $end          = (int) Settings::get( 'work_hours_end', 22 );
        $start        = (int) Settings::get( 'work_hours_start', 8 );

        if ( $start <= $end ) {
            // Normal window (e.g. 8-22).
            $remaining_hours = $end - $current_hour;
        } else {
            // Overnight window (e.g. 22-06).
            if ( $current_hour >= $start ) {
                $remaining_hours = ( 24 - $current_hour ) + $end;
            } else {
                $remaining_hours = $end - $current_hour;
            }
        }

        return max( 0, ( $remaining_hours * 3600 ) - ( $current_min * 60 ) );
    }

    /**
     * Check if current time is within configured work hours.
     *
     * @return bool
     */
    private function is_within_work_hours() {
        if ( ! Settings::get( 'work_hours_enabled' ) ) {
            return true;
        }

        $current_hour = (int) current_time( 'G' ); // 0-23 in WP timezone.
        $start        = (int) Settings::get( 'work_hours_start', 8 );
        $end          = (int) Settings::get( 'work_hours_end', 22 );

        // Handle overnight windows (e.g. 22 - 06).
        if ( $start <= $end ) {
            return $current_hour >= $start && $current_hour < $end;
        } else {
            return $current_hour >= $start || $current_hour < $end;
        }
    }

    /**
     * Count articles published today.
     */
    private function daily_count() {
        global $wpdb;
        $table = $wpdb->prefix . 'wpa_seen_articles';

        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE post_id IS NOT NULL AND created_at >= %s",
                current_time( 'Y-m-d' ) . ' 00:00:00'
            )
        );
    }
}
