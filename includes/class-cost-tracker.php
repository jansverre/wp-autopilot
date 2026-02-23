<?php

namespace WPAutopilot\Includes;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CostTracker {

    /**
     * Approximate prices per image for known fal.ai models.
     * Default $0.05 for unknown models.
     */
    private static $image_prices = array(
        'fal-ai/flux-2-pro'              => 0.05,
        'fal-ai/flux-2/klein/realtime'   => 0.01,
        'fal-ai/nano-banana-pro'         => 0.02,
        'xai/grok-imagine-image'         => 0.07,
    );

    /**
     * Log a cost entry.
     *
     * @param int|null $post_id   Associated post ID (null for style analysis etc).
     * @param string   $type      'text', 'featured_image', 'inline_image', 'style_analysis'.
     * @param string   $model     Model identifier.
     * @param int      $tokens_in Input tokens.
     * @param int      $tokens_out Output tokens.
     * @param float|null $cost_usd Estimated cost in USD.
     */
    public static function log( $post_id, $type, $model, $tokens_in = 0, $tokens_out = 0, $cost_usd = null ) {
        global $wpdb;
        $table = $wpdb->prefix . 'wpa_costs';

        $wpdb->insert(
            $table,
            array(
                'post_id'    => $post_id,
                'type'       => $type,
                'model'      => $model,
                'tokens_in'  => $tokens_in,
                'tokens_out' => $tokens_out,
                'cost_usd'   => $cost_usd,
                'created_at' => current_time( 'mysql' ),
            ),
            array( '%d', '%s', '%s', '%d', '%d', '%f', '%s' )
        );
    }

    /**
     * Log text generation cost from OpenRouter response data.
     *
     * @param int|null $post_id       Associated post ID.
     * @param string   $model         Model identifier.
     * @param array    $response_data Decoded OpenRouter API response.
     */
    public static function log_text( $post_id, $model, $response_data ) {
        $usage      = $response_data['usage'] ?? array();
        $tokens_in  = (int) ( $usage['prompt_tokens'] ?? 0 );
        $tokens_out = (int) ( $usage['completion_tokens'] ?? 0 );

        // OpenRouter may include total_cost in the response.
        $cost_usd = isset( $response_data['usage']['total_cost'] )
            ? (float) $response_data['usage']['total_cost']
            : null;

        self::log( $post_id, 'text', $model, $tokens_in, $tokens_out, $cost_usd );
    }

    /**
     * Log image generation cost.
     *
     * @param int|null $post_id Associated post ID.
     * @param string   $type    'featured_image' or 'inline_image'.
     * @param string   $model   Model identifier.
     */
    public static function log_image( $post_id, $type, $model ) {
        $cost = self::$image_prices[ $model ] ?? 0.05;
        self::log( $post_id, $type, $model, 0, 0, $cost );
    }

    /**
     * Get cost summary statistics.
     *
     * @return array Summary with totals and averages.
     */
    public static function get_summary() {
        global $wpdb;
        $table = $wpdb->prefix . 'wpa_costs';

        $today  = current_time( 'Y-m-d' ) . ' 00:00:00';
        $week   = gmdate( 'Y-m-d H:i:s', strtotime( '-7 days' ) );
        $month  = gmdate( 'Y-m-d H:i:s', strtotime( '-30 days' ) );

        $cost_today = (float) $wpdb->get_var( $wpdb->prepare(
            "SELECT COALESCE(SUM(cost_usd), 0) FROM {$table} WHERE created_at >= %s",
            $today
        ) );

        $cost_7d = (float) $wpdb->get_var( $wpdb->prepare(
            "SELECT COALESCE(SUM(cost_usd), 0) FROM {$table} WHERE created_at >= %s",
            $week
        ) );

        $cost_30d = (float) $wpdb->get_var( $wpdb->prepare(
            "SELECT COALESCE(SUM(cost_usd), 0) FROM {$table} WHERE created_at >= %s",
            $month
        ) );

        $total_cost = (float) $wpdb->get_var(
            "SELECT COALESCE(SUM(cost_usd), 0) FROM {$table}"
        );

        $article_count = (int) $wpdb->get_var(
            "SELECT COUNT(DISTINCT post_id) FROM {$table} WHERE post_id IS NOT NULL"
        );

        $avg_per_article = $article_count > 0 ? $total_cost / $article_count : 0;

        $tokens_total = $wpdb->get_row(
            "SELECT COALESCE(SUM(tokens_in), 0) AS tokens_in, COALESCE(SUM(tokens_out), 0) AS tokens_out FROM {$table}",
            ARRAY_A
        );

        return array(
            'cost_today'       => $cost_today,
            'cost_7d'          => $cost_7d,
            'cost_30d'         => $cost_30d,
            'cost_total'       => $total_cost,
            'avg_per_article'  => $avg_per_article,
            'article_count'    => $article_count,
            'tokens_in_total'  => (int) $tokens_total['tokens_in'],
            'tokens_out_total' => (int) $tokens_total['tokens_out'],
        );
    }

    /**
     * Get per-article cost breakdown.
     *
     * @param int $limit Number of articles to return.
     * @return array
     */
    public static function get_article_costs( $limit = 20 ) {
        global $wpdb;
        $table = $wpdb->prefix . 'wpa_costs';

        return $wpdb->get_results( $wpdb->prepare(
            "SELECT post_id,
                    SUM(tokens_in) AS tokens_in,
                    SUM(tokens_out) AS tokens_out,
                    SUM(cost_usd) AS cost_usd,
                    MIN(created_at) AS created_at,
                    GROUP_CONCAT(DISTINCT type) AS types
             FROM {$table}
             WHERE post_id IS NOT NULL
             GROUP BY post_id
             ORDER BY created_at DESC
             LIMIT %d",
            $limit
        ), ARRAY_A );
    }
}
