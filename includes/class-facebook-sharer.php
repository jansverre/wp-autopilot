<?php

namespace WPAutopilot\Includes;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class FacebookSharer {

    const FB_API_VERSION    = 'v22.0';
    const POSTER_MODEL      = 'fal-ai/nano-banana-pro';
    const POSTER_EDIT_MODEL = 'fal-ai/nano-banana-pro/edit';
    const MAX_POLLS         = 10;
    const POLL_INTERVAL     = 6;

    /**
     * Share an article to Facebook.
     *
     * @param int   $post_id          WordPress post ID.
     * @param array $article          Article data from ArticleWriter.
     * @param int   $author_id        Author user ID.
     * @param int   $posters_this_run Number of posters already generated this run.
     * @return array|null Result with 'had_poster' key, or null on early exit.
     */
    public function share( $post_id, $article, $author_id, $posters_this_run = 0 ) {
        if ( ! Settings::get( 'fb_enabled' ) ) {
            return null;
        }

        $page_id      = Settings::get( 'fb_page_id' );
        $access_token = Settings::get( 'fb_access_token' );

        if ( empty( $page_id ) || empty( $access_token ) ) {
            Logger::warning( __( 'Facebook sharing: Missing page ID or access token.', 'wp-autopilot' ) );
            return null;
        }

        // Prevent double sharing.
        if ( get_post_meta( $post_id, '_wpa_fb_shared', true ) ) {
            /* translators: %d: post ID */
            Logger::info( sprintf( __( 'Facebook sharing: Article %d already shared.', 'wp-autopilot' ), $post_id ) );
            return null;
        }

        $link       = get_permalink( $post_id );
        $had_poster = false;

        // Determine if this article should get a poster.
        if ( Settings::get( 'fb_image_mode' ) === 'generated_poster' && $this->is_poster_eligible( $author_id, $posters_this_run ) ) {
            // Full poster path: AI text + generated poster.
            $fb_text   = $this->generate_fb_text( $post_id, $article );
            $poster_id = $this->generate_poster(
                $post_id,
                $article['title'] ?? get_the_title( $post_id ),
                $article['excerpt'] ?? '',
                $author_id
            );

            if ( $poster_id ) {
                $fb_result  = $this->post_to_facebook( $fb_text, $link, $poster_id );
                $had_poster = true;
            } else {
                // Poster generation failed — fallback to link post.
                Logger::warning( __( 'Facebook sharing: Poster generation failed, falling back to link post.', 'wp-autopilot' ) );
                $fb_result = $this->post_to_facebook( $fb_text, $link );
            }
        } elseif ( Settings::get( 'fb_image_mode' ) === 'generated_poster' ) {
            // Not poster-eligible — use fallback mode.
            $fb_result = $this->share_without_poster( $post_id, $article, $link );
            if ( $fb_result === false ) {
                // 'skip' mode — no sharing.
                return array( 'had_poster' => false );
            }
        } else {
            // featured_image mode: AI text + link post.
            $fb_text   = $this->generate_fb_text( $post_id, $article );
            $fb_result = $this->post_to_facebook( $fb_text, $link );
        }

        if ( $fb_result ) {
            update_post_meta( $post_id, '_wpa_fb_shared', true );
            update_post_meta( $post_id, '_wpa_fb_post_id', $fb_result );
            /* translators: 1: post ID, 2: Facebook post ID */
            Logger::info( sprintf( __( 'Facebook sharing: Article %1$d shared (FB post ID: %2$s).', 'wp-autopilot' ), $post_id, $fb_result ) );
        }

        return array( 'had_poster' => $had_poster );
    }

    /**
     * Handle sharing without a poster based on fb_no_poster_mode setting.
     *
     * @param int    $post_id WordPress post ID.
     * @param array  $article Article data.
     * @param string $link    Article permalink.
     * @return string|null|false FB post ID, null on failure, false if skipped.
     */
    private function share_without_poster( $post_id, $article, $link ) {
        $mode = Settings::get( 'fb_no_poster_mode', 'ai_text' );

        switch ( $mode ) {
            case 'ai_text':
                $fb_text = $this->generate_fb_text( $post_id, $article );
                return $this->post_to_facebook( $fb_text, $link );

            case 'excerpt':
                $fb_text = $this->fallback_text( $post_id, $article );
                return $this->post_to_facebook( $fb_text, $link );

            case 'skip':
                /* translators: %d: post ID */
                Logger::info( sprintf( __( 'Facebook sharing: Skipping article %d (poster not eligible, mode: skip).', 'wp-autopilot' ), $post_id ) );
                return false;

            default:
                $fb_text = $this->generate_fb_text( $post_id, $article );
                return $this->post_to_facebook( $fb_text, $link );
        }
    }

    /**
     * Check if this article is eligible for a poster image.
     *
     * @param int $author_id        Author user ID.
     * @param int $posters_this_run Number of posters already generated this run.
     * @return bool
     */
    public function is_poster_eligible( $author_id, $posters_this_run = 0 ) {
        // Check author whitelist.
        $poster_authors = json_decode( Settings::get( 'fb_poster_authors', '[]' ), true );
        if ( ! empty( $poster_authors ) && ! in_array( (int) $author_id, array_map( 'intval', $poster_authors ), true ) ) {
            /* translators: %d: author user ID */
            Logger::info( sprintf( __( 'Facebook poster: Author %d not in poster author list.', 'wp-autopilot' ), $author_id ) );
            return false;
        }

        // Check per-run limit.
        $per_run = (int) Settings::get( 'fb_poster_per_run', 0 );
        if ( $per_run > 0 && $posters_this_run >= $per_run ) {
            /* translators: %d: per-run poster limit */
            Logger::info( sprintf( __( 'Facebook poster: Per-run limit reached (%d).', 'wp-autopilot' ), $per_run ) );
            return false;
        }

        // Check daily limit.
        $daily_limit = (int) Settings::get( 'fb_poster_daily_limit', 0 );
        if ( $daily_limit > 0 && $this->get_today_poster_count() >= $daily_limit ) {
            /* translators: %d: daily poster limit */
            Logger::info( sprintf( __( 'Facebook poster: Daily limit reached (%d).', 'wp-autopilot' ), $daily_limit ) );
            return false;
        }

        return true;
    }

    /**
     * Count posters generated today.
     *
     * @return int
     */
    public function get_today_poster_count() {
        global $wpdb;
        $table = $wpdb->prefix . 'wpa_costs';

        return (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$table} WHERE type = 'fb_poster' AND DATE(created_at) = CURDATE()"
        );
    }

    /**
     * Generate Facebook post text via OpenRouter AI.
     *
     * @param int   $post_id WordPress post ID.
     * @param array $article Article data.
     * @return string Facebook post text.
     */
    private function generate_fb_text( $post_id, $article ) {
        $api_key = Settings::get( 'openrouter_api_key' );
        if ( empty( $api_key ) ) {
            Logger::warning( __( 'Facebook sharing: OpenRouter API key missing, using excerpt.', 'wp-autopilot' ) );
            return $this->fallback_text( $post_id, $article );
        }

        $model = Settings::get( 'ai_custom_model' );
        if ( empty( $model ) ) {
            $model = Settings::get( 'ai_model', 'google/gemini-3-flash-preview' );
        }

        $language = $this->locale_to_language( get_locale() );
        $title    = $article['title'] ?? get_the_title( $post_id );
        $excerpt  = $article['excerpt'] ?? '';
        $link     = get_permalink( $post_id );

        $body = array(
            'model'       => $model,
            'temperature' => 0.8,
            'messages'    => array(
                array(
                    'role'    => 'system',
                    'content' => "Du er en sosiale medier-ekspert. Skriv en kort, engasjerende Facebook-post på {$language} som får folk til å klikke på lenken. Maks 2-3 setninger. Ikke bruk hashtags. Ikke inkluder lenken i teksten — den legges til automatisk.",
                ),
                array(
                    'role'    => 'user',
                    'content' => "Skriv en Facebook-post for denne artikkelen:\n\nTittel: {$title}\nSammendrag: {$excerpt}\nLenke: {$link}",
                ),
            ),
        );

        $response = wp_remote_post( 'https://openrouter.ai/api/v1/chat/completions', array(
            'timeout' => 60,
            'headers' => array(
                'Content-Type'  => 'application/json',
                'Authorization' => 'Bearer ' . $api_key,
                'HTTP-Referer'  => home_url(),
                'X-Title'       => 'WP Autopilot',
            ),
            'body' => wp_json_encode( $body ),
        ) );

        if ( is_wp_error( $response ) ) {
            Logger::warning( __( 'Facebook sharing: AI error, using excerpt. ', 'wp-autopilot' ) . $response->get_error_message() );
            return $this->fallback_text( $post_id, $article );
        }

        $status_code = wp_remote_retrieve_response_code( $response );
        $data        = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $status_code !== 200 ) {
            /* translators: %d: HTTP status code */
            Logger::warning( sprintf( __( 'Facebook sharing: AI returned %d, using excerpt.', 'wp-autopilot' ), $status_code ) );
            return $this->fallback_text( $post_id, $article );
        }

        $text = trim( $data['choices'][0]['message']['content'] ?? '' );

        if ( empty( $text ) ) {
            return $this->fallback_text( $post_id, $article );
        }

        // Log cost.
        CostTracker::log_text( $post_id, $model, $data );

        /* translators: %d: post ID */
        Logger::info( sprintf( __( 'Facebook sharing: AI text generated for article %d.', 'wp-autopilot' ), $post_id ) );

        return $text;
    }

    /**
     * Fallback text when AI generation fails.
     */
    private function fallback_text( $post_id, $article ) {
        $excerpt = $article['excerpt'] ?? '';
        if ( empty( $excerpt ) ) {
            $excerpt = get_the_excerpt( $post_id );
        }
        return $excerpt ?: ( $article['title'] ?? get_the_title( $post_id ) );
    }

    /**
     * Generate a poster image for the Facebook post.
     *
     * @param int    $post_id   WordPress post ID.
     * @param string $title     Article title.
     * @param string $excerpt   Article excerpt.
     * @param int    $author_id Author user ID.
     * @return int|null WP attachment ID or null on failure.
     */
    public function generate_poster( $post_id, $title, $excerpt, $author_id ) {
        $api_key = Settings::get( 'fal_api_key' );
        if ( empty( $api_key ) ) {
            Logger::error( __( 'Facebook poster: fal.ai API key is missing.', 'wp-autopilot' ) );
            return null;
        }

        $language = $this->locale_to_language( get_locale() );

        // Collect reference images.
        $image_urls     = array();
        $has_author_photo = false;
        $has_logo       = false;

        // Author reference photo.
        if ( Settings::get( 'fb_author_face' ) && $author_id ) {
            $author_photos = json_decode( Settings::get( 'fb_author_photos', '{}' ), true );
            $photo_id      = $author_photos[ $author_id ] ?? null;
            if ( $photo_id ) {
                $photo_url = wp_get_attachment_url( (int) $photo_id );
                if ( $photo_url ) {
                    $image_urls[]     = $photo_url;
                    $has_author_photo = true;
                }
            }
        }

        // Site-logo.
        $custom_logo_id = get_theme_mod( 'custom_logo' );
        if ( $custom_logo_id ) {
            $logo_url = wp_get_attachment_url( $custom_logo_id );
            if ( $logo_url ) {
                $image_urls[] = $logo_url;
                $has_logo     = true;
            }
        }

        // Select model and build prompt based on available reference images.
        if ( $has_author_photo && $has_logo ) {
            // Author + logo → edit model.
            $model  = self::POSTER_EDIT_MODEL;
            $prompt = $this->build_poster_prompt_author_logo( $title, $excerpt, $language );
        } elseif ( $has_logo ) {
            // Logo only → edit model.
            $model  = self::POSTER_EDIT_MODEL;
            $prompt = $this->build_poster_prompt_logo_only( $title, $excerpt, $language );
        } else {
            // No reference images → standard generation.
            $model  = self::POSTER_MODEL;
            $prompt = $this->build_poster_prompt_plain( $title, $excerpt, $language );
        }

        // Build request body.
        $request_body = array(
            'prompt'            => $prompt,
            'image_size'        => 'landscape_16_9',
            'width'             => 1280,
            'height'            => 720,
            'aspect_ratio'      => '16:9',
            'enable_web_search' => true,
        );

        if ( ! empty( $image_urls ) ) {
            $request_body['image_urls'] = $image_urls;
        }

        // Submit to fal.ai queue.
        $request_id = $this->submit_to_fal( $api_key, $model, $request_body );
        if ( ! $request_id ) {
            return null;
        }

        // Poll for result.
        $image_url = $this->poll_fal_result( $api_key, $model, $request_id );
        if ( ! $image_url ) {
            return null;
        }

        // Upload to WP Media Library.
        $attachment_id = $this->upload_poster( $image_url, $title );
        if ( $attachment_id ) {
            CostTracker::log_image( $post_id, 'fb_poster', $model );
            /* translators: 1: attachment ID, 2: post ID */
            Logger::info( sprintf( __( 'Facebook poster generated (ID: %1$d) for article %2$d.', 'wp-autopilot' ), $attachment_id, $post_id ) );
        }

        return $attachment_id;
    }

    /**
     * Poster prompt with author reference photo + logo.
     */
    private function build_poster_prompt_author_logo( $title, $excerpt, $language ) {
        return "Create a professional, scroll-stopping Facebook sharing poster for this news article.\n\n"
            . "The first reference image is a photo of the journalist/author — feature this person "
            . "prominently in a setting that relates to the article's core topic.\n\n"
            . "The second reference image is the site's logo — incorporate it creatively into the "
            . "poster design (e.g., corner placement, watermark style, or as part of the header).\n\n"
            . "Article title: \"{$title}\"\n"
            . "Summary: \"{$excerpt}\"\n\n"
            . "All text and headlines on the poster must be in {$language}.\n"
            . "Design should be bold, modern, and attention-grabbing for social media.\n"
            . "You have full creative freedom for layout, colors, and composition.";
    }

    /**
     * Poster prompt with logo only (without author).
     */
    private function build_poster_prompt_logo_only( $title, $excerpt, $language ) {
        return "Create a professional, scroll-stopping Facebook sharing poster for this news article.\n\n"
            . "The reference image is the site's logo — incorporate it creatively into the poster design.\n\n"
            . "Article title: \"{$title}\"\n"
            . "Summary: \"{$excerpt}\"\n\n"
            . "All text and headlines on the poster must be in {$language}.\n"
            . "Design should be bold, modern, and attention-grabbing for social media.\n"
            . "You have full creative freedom for layout, colors, and composition.";
    }

    /**
     * Poster prompt without reference images.
     */
    private function build_poster_prompt_plain( $title, $excerpt, $language ) {
        return "Create a professional, scroll-stopping Facebook sharing poster for this news article.\n\n"
            . "Article title: \"{$title}\"\n"
            . "Summary: \"{$excerpt}\"\n\n"
            . "All text and headlines on the poster must be in {$language}.\n"
            . "Design should be bold, modern, and attention-grabbing for social media.\n"
            . "You have full creative freedom for layout, colors, and composition.";
    }

    /**
     * Submit a request to fal.ai queue.
     *
     * @param string $api_key      fal.ai API key.
     * @param string $model        Model identifier.
     * @param array  $request_body Request body.
     * @return string|null Request ID or null on failure.
     */
    private function submit_to_fal( $api_key, $model, $request_body ) {
        $response = wp_remote_post( "https://queue.fal.run/{$model}", array(
            'timeout' => 30,
            'headers' => array(
                'Content-Type'  => 'application/json',
                'Authorization' => 'Key ' . $api_key,
            ),
            'body' => wp_json_encode( $request_body ),
        ) );

        if ( is_wp_error( $response ) ) {
            Logger::error( __( 'Facebook poster: fal.ai queue error: ', 'wp-autopilot' ) . $response->get_error_message() );
            return null;
        }

        $data       = json_decode( wp_remote_retrieve_body( $response ), true );
        $request_id = $data['request_id'] ?? null;

        if ( ! $request_id ) {
            Logger::error( __( 'Facebook poster: Did not receive request_id from fal.ai.', 'wp-autopilot' ), $data );
            return null;
        }

        return $request_id;
    }

    /**
     * Poll fal.ai for result.
     *
     * @param string $api_key    fal.ai API key.
     * @param string $model      Model identifier.
     * @param string $request_id Request ID.
     * @return string|null Image URL or null on failure/timeout.
     */
    private function poll_fal_result( $api_key, $model, $request_id ) {
        for ( $i = 0; $i < self::MAX_POLLS; $i++ ) {
            sleep( self::POLL_INTERVAL );

            $response = wp_remote_get(
                "https://queue.fal.run/{$model}/requests/{$request_id}/status",
                array(
                    'timeout' => 30,
                    'headers' => array(
                        'Authorization' => 'Key ' . $api_key,
                    ),
                )
            );

            if ( is_wp_error( $response ) ) {
                continue;
            }

            $data   = json_decode( wp_remote_retrieve_body( $response ), true );
            $status = $data['status'] ?? '';

            if ( $status === 'COMPLETED' ) {
                $result_response = wp_remote_get(
                    "https://queue.fal.run/{$model}/requests/{$request_id}",
                    array(
                        'timeout' => 30,
                        'headers' => array(
                            'Authorization' => 'Key ' . $api_key,
                        ),
                    )
                );

                if ( is_wp_error( $result_response ) ) {
                    Logger::error( __( 'Facebook poster: Could not fetch fal.ai result: ', 'wp-autopilot' ) . $result_response->get_error_message() );
                    return null;
                }

                $result = json_decode( wp_remote_retrieve_body( $result_response ), true );

                // Handle both response formats.
                return $result['images'][0]['url'] ?? $result['image']['url'] ?? null;
            }

            if ( $status === 'FAILED' ) {
                Logger::error( __( 'Facebook poster: fal.ai image generation failed.', 'wp-autopilot' ), $data );
                return null;
            }
        }

        /* translators: %d: number of poll attempts */
        Logger::error( sprintf( __( 'Facebook poster: fal.ai timeout after %d polls.', 'wp-autopilot' ), self::MAX_POLLS ) );
        return null;
    }

    /**
     * Upload a poster image to WordPress Media Library.
     *
     * @param string $url   Image URL.
     * @param string $title Post title for naming.
     * @return int|null Attachment ID.
     */
    private function upload_poster( $url, $title ) {
        if ( ! function_exists( 'media_handle_sideload' ) ) {
            require_once ABSPATH . 'wp-admin/includes/media.php';
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/image.php';
        }

        $tmp = download_url( $url, 60 );
        if ( is_wp_error( $tmp ) ) {
            Logger::error( __( 'Facebook poster: Could not download image: ', 'wp-autopilot' ) . $tmp->get_error_message() );
            return null;
        }

        $filename = sanitize_file_name( 'fb-poster-' . sanitize_title( $title ) ) . '.png';

        $file_array = array(
            'name'     => $filename,
            'tmp_name' => $tmp,
        );

        $attachment_id = media_handle_sideload( $file_array, 0, 'FB-poster: ' . $title );

        if ( is_wp_error( $attachment_id ) ) {
            @unlink( $tmp );
            Logger::error( __( 'Facebook poster: Could not upload to media library: ', 'wp-autopilot' ) . $attachment_id->get_error_message() );
            return null;
        }

        return $attachment_id;
    }

    /**
     * Post to Facebook Graph API.
     *
     * @param string   $message   Post text.
     * @param string   $link      Article permalink.
     * @param int|null $poster_id WP attachment ID for poster image.
     * @return string|null Facebook post ID or null on failure.
     */
    private function post_to_facebook( $message, $link, $poster_id = null ) {
        $page_id      = Settings::get( 'fb_page_id' );
        $access_token = Settings::get( 'fb_access_token' );
        $api_base     = 'https://graph.facebook.com/' . self::FB_API_VERSION;

        if ( $poster_id ) {
            // Post with image via /photos endpoint.
            $poster_url = wp_get_attachment_url( $poster_id );
            if ( ! $poster_url ) {
                Logger::warning( __( 'Facebook sharing: Could not get poster URL, falling back to link post.', 'wp-autopilot' ) );
                return $this->post_link_to_facebook( $message, $link );
            }

            // Include the link in the message for poster posts.
            $full_message = $message . "\n\n" . $link;

            $response = wp_remote_post( "{$api_base}/{$page_id}/photos", array(
                'timeout' => 30,
                'body'    => array(
                    'message'      => $full_message,
                    'url'          => $poster_url,
                    'access_token' => $access_token,
                ),
            ) );
        } else {
            // Link post via /feed endpoint (FB scraper fetches OG image).
            $response = $this->post_link_to_facebook_raw( $message, $link );
            return $this->handle_fb_response( $response );
        }

        return $this->handle_fb_response( $response );
    }

    /**
     * Post a link-only post to Facebook.
     */
    private function post_link_to_facebook( $message, $link ) {
        $response = $this->post_link_to_facebook_raw( $message, $link );
        return $this->handle_fb_response( $response );
    }

    /**
     * Raw link post to Facebook /feed endpoint.
     */
    private function post_link_to_facebook_raw( $message, $link ) {
        $page_id      = Settings::get( 'fb_page_id' );
        $access_token = Settings::get( 'fb_access_token' );
        $api_base     = 'https://graph.facebook.com/' . self::FB_API_VERSION;

        return wp_remote_post( "{$api_base}/{$page_id}/feed", array(
            'timeout' => 30,
            'body'    => array(
                'message'      => $message,
                'link'         => $link,
                'access_token' => $access_token,
            ),
        ) );
    }

    /**
     * Handle Facebook API response.
     *
     * @param array|\WP_Error $response wp_remote_post response.
     * @return string|null Facebook post/photo ID or null.
     */
    private function handle_fb_response( $response ) {
        if ( is_wp_error( $response ) ) {
            Logger::error( __( 'Facebook API error: ', 'wp-autopilot' ) . $response->get_error_message() );
            return null;
        }

        $status_code = wp_remote_retrieve_response_code( $response );
        $data        = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $status_code !== 200 ) {
            $error_msg = $data['error']['message'] ?? __( 'Unknown error', 'wp-autopilot' );
            /* translators: 1: HTTP status code, 2: error message */
            Logger::error( sprintf( __( 'Facebook API returned %1$d: %2$s', 'wp-autopilot' ), $status_code, $error_msg ) );
            return null;
        }

        // /feed returns {id}, /photos returns {id, post_id}.
        return $data['id'] ?? $data['post_id'] ?? null;
    }

    /**
     * Map WordPress locale to human-readable language name.
     *
     * @param string $locale WordPress locale string.
     * @return string Language name in English.
     */
    private function locale_to_language( $locale ) {
        $map = array(
            'nb_NO' => 'Norwegian',
            'nn_NO' => 'Norwegian Nynorsk',
            'en_US' => 'English',
            'en_GB' => 'English',
            'sv_SE' => 'Swedish',
            'da_DK' => 'Danish',
            'fi'    => 'Finnish',
            'de_DE' => 'German',
            'fr_FR' => 'French',
            'es_ES' => 'Spanish',
            'it_IT' => 'Italian',
            'pt_BR' => 'Portuguese',
            'nl_NL' => 'Dutch',
            'pl_PL' => 'Polish',
        );

        return $map[ $locale ] ?? 'English';
    }
}
