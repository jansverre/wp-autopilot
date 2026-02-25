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
     * @param int   $post_id   WordPress post ID.
     * @param array $article   Article data from ArticleWriter.
     * @param int   $author_id Author user ID.
     */
    public function share( $post_id, $article, $author_id ) {
        if ( ! Settings::get( 'fb_enabled' ) ) {
            return;
        }

        $page_id      = Settings::get( 'fb_page_id' );
        $access_token = Settings::get( 'fb_access_token' );

        if ( empty( $page_id ) || empty( $access_token ) ) {
            Logger::warning( 'Facebook-deling: Mangler side-ID eller tilgangstoken.' );
            return;
        }

        // Hindre dobbeltdeling.
        if ( get_post_meta( $post_id, '_wpa_fb_shared', true ) ) {
            Logger::info( sprintf( 'Facebook-deling: Artikkel %d allerede delt.', $post_id ) );
            return;
        }

        // Generer FB-tekst.
        $fb_text = $this->generate_fb_text( $post_id, $article );

        // Generer poster om valgt.
        $poster_id = null;
        if ( Settings::get( 'fb_image_mode' ) === 'generated_poster' ) {
            $poster_id = $this->generate_poster(
                $post_id,
                $article['title'] ?? get_the_title( $post_id ),
                $article['excerpt'] ?? '',
                $author_id
            );

            if ( ! $poster_id ) {
                Logger::warning( 'Facebook-deling: Poster-generering feilet, faller tilbake til link-post.' );
            }
        }

        // Post til Facebook.
        $link      = get_permalink( $post_id );
        $fb_result = $this->post_to_facebook( $fb_text, $link, $poster_id );

        if ( $fb_result ) {
            update_post_meta( $post_id, '_wpa_fb_shared', true );
            update_post_meta( $post_id, '_wpa_fb_post_id', $fb_result );
            Logger::info( sprintf( 'Facebook-deling: Artikkel %d delt (FB post ID: %s).', $post_id, $fb_result ) );
        }
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
            Logger::warning( 'Facebook-deling: OpenRouter API-nøkkel mangler, bruker excerpt.' );
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
            Logger::warning( 'Facebook-deling: AI-feil, bruker excerpt. ' . $response->get_error_message() );
            return $this->fallback_text( $post_id, $article );
        }

        $status_code = wp_remote_retrieve_response_code( $response );
        $data        = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $status_code !== 200 ) {
            Logger::warning( sprintf( 'Facebook-deling: AI returnerte %d, bruker excerpt.', $status_code ) );
            return $this->fallback_text( $post_id, $article );
        }

        $text = trim( $data['choices'][0]['message']['content'] ?? '' );

        if ( empty( $text ) ) {
            return $this->fallback_text( $post_id, $article );
        }

        // Logg kostnad.
        CostTracker::log_text( $post_id, $model, $data );

        Logger::info( sprintf( 'Facebook-deling: AI-tekst generert for artikkel %d.', $post_id ) );

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
            Logger::error( 'Facebook-poster: fal.ai API-nøkkel mangler.' );
            return null;
        }

        $language = $this->locale_to_language( get_locale() );

        // Samle referansebilder.
        $image_urls     = array();
        $has_author_photo = false;
        $has_logo       = false;

        // Forfatter-referansebilde.
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

        // Velg modell og bygg prompt basert på tilgjengelige referansebilder.
        if ( $has_author_photo && $has_logo ) {
            // Forfatter + logo → edit-modell.
            $model  = self::POSTER_EDIT_MODEL;
            $prompt = $this->build_poster_prompt_author_logo( $title, $excerpt, $language );
        } elseif ( $has_logo ) {
            // Kun logo → edit-modell.
            $model  = self::POSTER_EDIT_MODEL;
            $prompt = $this->build_poster_prompt_logo_only( $title, $excerpt, $language );
        } else {
            // Ingen referansebilder → vanlig generering.
            $model  = self::POSTER_MODEL;
            $prompt = $this->build_poster_prompt_plain( $title, $excerpt, $language );
        }

        // Bygg request body.
        $request_body = array(
            'prompt'            => $prompt,
            'aspect_ratio'      => '16:9',
            'enable_web_search' => true,
        );

        if ( ! empty( $image_urls ) ) {
            $request_body['image_urls'] = $image_urls;
        }

        // Submit til fal.ai queue.
        $request_id = $this->submit_to_fal( $api_key, $model, $request_body );
        if ( ! $request_id ) {
            return null;
        }

        // Poll for resultat.
        $image_url = $this->poll_fal_result( $api_key, $model, $request_id );
        if ( ! $image_url ) {
            return null;
        }

        // Last opp til WP Media Library.
        $attachment_id = $this->upload_poster( $image_url, $title );
        if ( $attachment_id ) {
            CostTracker::log_image( $post_id, 'fb_poster', $model );
            Logger::info( sprintf( 'Facebook-poster generert (ID: %d) for artikkel %d.', $attachment_id, $post_id ) );
        }

        return $attachment_id;
    }

    /**
     * Poster-prompt med forfatter-referansebilde + logo.
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
     * Poster-prompt med kun logo (uten forfatter).
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
     * Poster-prompt uten referansebilder.
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
            Logger::error( 'Facebook-poster: fal.ai queue-feil: ' . $response->get_error_message() );
            return null;
        }

        $data       = json_decode( wp_remote_retrieve_body( $response ), true );
        $request_id = $data['request_id'] ?? null;

        if ( ! $request_id ) {
            Logger::error( 'Facebook-poster: Fikk ikke request_id fra fal.ai.', $data );
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
                    Logger::error( 'Facebook-poster: Kunne ikke hente fal.ai-resultat: ' . $result_response->get_error_message() );
                    return null;
                }

                $result = json_decode( wp_remote_retrieve_body( $result_response ), true );

                // Håndter begge responsformat.
                return $result['images'][0]['url'] ?? $result['image']['url'] ?? null;
            }

            if ( $status === 'FAILED' ) {
                Logger::error( 'Facebook-poster: fal.ai bildegenerering feilet.', $data );
                return null;
            }
        }

        Logger::error( 'Facebook-poster: fal.ai timeout etter ' . self::MAX_POLLS . ' polls.' );
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
            Logger::error( 'Facebook-poster: Kunne ikke laste ned bilde: ' . $tmp->get_error_message() );
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
            Logger::error( 'Facebook-poster: Kunne ikke laste opp til mediebiblioteket: ' . $attachment_id->get_error_message() );
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
            // Post med bilde via /photos endpoint.
            $poster_url = wp_get_attachment_url( $poster_id );
            if ( ! $poster_url ) {
                Logger::warning( 'Facebook-deling: Kunne ikke hente poster-URL, faller tilbake til link-post.' );
                return $this->post_link_to_facebook( $message, $link );
            }

            // Inkluder lenken i meldingen for poster-posts.
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
            // Link-post via /feed endpoint (FB scraper henter OG-bilde).
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
            Logger::error( 'Facebook API-feil: ' . $response->get_error_message() );
            return null;
        }

        $status_code = wp_remote_retrieve_response_code( $response );
        $data        = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $status_code !== 200 ) {
            $error_msg = $data['error']['message'] ?? 'Ukjent feil';
            Logger::error( sprintf( 'Facebook API returnerte %d: %s', $status_code, $error_msg ) );
            return null;
        }

        // /feed returnerer {id}, /photos returnerer {id, post_id}.
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
