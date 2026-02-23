<?php

namespace WPAutopilot\Includes;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ArticleWriter {

    /**
     * Write an article based on a news item.
     *
     * @param array    $news_item     News item data from feed.
     * @param array    $related_links Related internal articles for linking.
     * @param int|null $author_id     Author ID for per-author writing style.
     * @return array|null Article data or null on failure.
     */
    public function write( array $news_item, array $related_links = array(), $author_id = null ) {
        $api_key = Settings::get( 'openrouter_api_key' );
        if ( empty( $api_key ) ) {
            Logger::error( 'OpenRouter API-nøkkel mangler.' );
            return null;
        }

        $model = Settings::get( 'ai_custom_model' );
        if ( empty( $model ) ) {
            $model = Settings::get( 'ai_model', 'google/gemini-3-flash-preview' );
        }

        $prompt = $this->build_prompt( $news_item, $related_links );

        $body = array(
            'model'           => $model,
            'temperature'     => (float) Settings::get( 'ai_temperature', 0.7 ),
            'response_format' => array( 'type' => 'json_object' ),
            'messages'        => array(
                array(
                    'role'    => 'system',
                    'content' => $this->build_system_prompt( $author_id ),
                ),
                array(
                    'role'    => 'user',
                    'content' => $prompt,
                ),
            ),
        );

        $response = wp_remote_post( 'https://openrouter.ai/api/v1/chat/completions', array(
            'timeout' => 120,
            'headers' => array(
                'Content-Type'  => 'application/json',
                'Authorization' => 'Bearer ' . $api_key,
                'HTTP-Referer'  => home_url(),
                'X-Title'       => 'WP Autopilot',
            ),
            'body' => wp_json_encode( $body ),
        ) );

        if ( is_wp_error( $response ) ) {
            Logger::error( 'OpenRouter API-feil: ' . $response->get_error_message() );
            return null;
        }

        $status_code = wp_remote_retrieve_response_code( $response );
        $response_body = wp_remote_retrieve_body( $response );
        $data = json_decode( $response_body, true );

        if ( $status_code !== 200 ) {
            $error_msg = $data['error']['message'] ?? 'Ukjent feil';
            Logger::error( sprintf( 'OpenRouter API returnerte %d: %s', $status_code, $error_msg ) );
            return null;
        }

        $content = $data['choices'][0]['message']['content'] ?? null;
        if ( empty( $content ) ) {
            Logger::error( 'Tomt svar fra OpenRouter API.' );
            return null;
        }

        $article = json_decode( $content, true );
        if ( ! $article || empty( $article['title'] ) || empty( $article['content'] ) ) {
            Logger::error( 'Kunne ikke parse AI-respons som JSON.', $content );
            return null;
        }

        // Ensure all expected fields exist.
        $article = wp_parse_args( $article, array(
            'title'          => '',
            'content'        => '',
            'excerpt'        => '',
            'category_hint'  => '',
            'image_prompt'   => '',
            'image_alt'      => '',
            'image_caption'  => '',
            'inline_images'  => array(),
        ) );

        // Clean up content: remove literal \n and normalize whitespace between HTML tags.
        $article['content'] = self::clean_content( $article['content'] );

        // Attach usage data and model for cost tracking.
        $article['_model'] = $model;
        $article['_usage'] = $data['usage'] ?? array();
        $article['_response_data'] = $data;

        Logger::info( sprintf( 'Artikkel generert: "%s"', $article['title'] ) );

        return $article;
    }

    /**
     * Analyze writing style from an author's published posts.
     *
     * @param int $author_id Author user ID.
     * @param int $num_posts Number of posts to analyze.
     * @return array {style: string, usage: array} or {error: string}.
     */
    public function analyze_style( $author_id, $num_posts = 5 ) {
        $api_key = Settings::get( 'openrouter_api_key' );
        if ( empty( $api_key ) ) {
            return array( 'error' => 'OpenRouter API-nøkkel mangler.' );
        }

        $posts = get_posts( array(
            'author'      => $author_id,
            'post_type'   => 'post',
            'post_status' => 'publish',
            'numberposts' => $num_posts,
            'orderby'     => 'date',
            'order'       => 'DESC',
        ) );

        if ( empty( $posts ) ) {
            return array( 'error' => 'Ingen publiserte innlegg funnet for denne forfatteren.' );
        }

        // Build sample text from posts.
        $samples = array();
        foreach ( $posts as $post ) {
            $text = wp_strip_all_tags( $post->post_content );
            $text = mb_substr( $text, 0, 2000 );
            $samples[] = "--- Artikkel: \"{$post->post_title}\" ---\n{$text}";
        }

        $sample_text = implode( "\n\n", $samples );

        $model = Settings::get( 'ai_custom_model' );
        if ( empty( $model ) ) {
            $model = Settings::get( 'ai_model', 'google/gemini-3-flash-preview' );
        }

        $language = Settings::get( 'ai_language', 'norsk' );

        $body = array(
            'model'       => $model,
            'temperature' => 0.3,
            'messages'    => array(
                array(
                    'role'    => 'system',
                    'content' => "Du er en ekspert på skrivestil-analyse. Analyser tekstene og beskriv skrivestilen kort og presist på {$language}. Fokuser på: tone, setningslengde, ordvalg, bruk av virkemidler, perspektiv, og overordnet stemme. Svaret skal kunne brukes som instruksjon til en AI for å etterligne stilen.",
                ),
                array(
                    'role'    => 'user',
                    'content' => "Analyser skrivestilen i disse artiklene:\n\n{$sample_text}\n\nGi en kort og presis beskrivelse av skrivestilen.",
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
            return array( 'error' => 'API-feil: ' . $response->get_error_message() );
        }

        $status_code = wp_remote_retrieve_response_code( $response );
        $data = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $status_code !== 200 ) {
            return array( 'error' => $data['error']['message'] ?? 'Ukjent API-feil' );
        }

        $style = $data['choices'][0]['message']['content'] ?? '';
        $usage = $data['usage'] ?? array();

        return array(
            'style'          => trim( $style ),
            'usage'          => $usage,
            'model'          => $model,
            'response_data'  => $data,
        );
    }

    /**
     * Build system prompt for the AI.
     *
     * @param int|null $author_id Optional author ID for per-author writing style.
     */
    private function build_system_prompt( $author_id = null ) {
        $language       = Settings::get( 'ai_language', 'norsk' );
        $niche          = Settings::get( 'ai_niche', '' );
        $site_identity  = Settings::get( 'site_identity', '' );
        $min_words      = (int) Settings::get( 'min_words', 600 );
        $max_words      = (int) Settings::get( 'max_words', 1200 );

        // Resolve writing style: per-author > global fallback.
        $style = Settings::get( 'ai_style', 'informativ og engasjerende' );
        if ( $author_id ) {
            $writing_styles = json_decode( Settings::get( 'writing_styles', '{}' ), true );
            if ( ! empty( $writing_styles[ $author_id ] ) ) {
                $style = $writing_styles[ $author_id ];
            }
        }

        $prompt = "Du er en profesjonell journalist og innholdsskaper. ";
        $prompt .= "Skriv alltid på {$language}. ";

        if ( ! empty( $site_identity ) ) {
            $prompt .= "\n\nOm nettstedet du skriver for:\n{$site_identity}\n\n";
        }

        if ( ! empty( $niche ) ) {
            $prompt .= "Du spesialiserer deg på {$niche}. ";
        }

        $prompt .= "Skrivestilen din er {$style}. ";
        $prompt .= "Artikkelen skal være mellom {$min_words} og {$max_words} ord. ";
        $prompt .= "Bruk HTML-formatering (<h2>, <h3>, <p>, <strong>, <em>, <ul>, <li>) for innholdet. ";
        $prompt .= "Ikke bruk <h1> da det brukes som tittel av WordPress. ";
        $prompt .= "\n\nVIKTIG for artikkelstruktur:";
        $prompt .= "\n- Artikkelen SKAL starte rett på med et engasjerende intro-avsnitt i <p>-tags. IKKE start med en <h2>-overskrift — tittelen er allerede H1.";
        $prompt .= "\n- Bruk <h2> og <h3> lenger ned i artikkelen for å strukturere innholdet.";
        $prompt .= "\n- Returner REN HTML uten literal \\n mellom tags. Bruk kun HTML-tags for formatering, ingen newline-tegn.";

        // Inline image instructions.
        if ( Settings::get( 'inline_images_enabled' ) ) {
            $frequency = Settings::get( 'inline_images_frequency', 'every_other_h2' );
            $freq_text = array(
                'every_h2'       => 'etter HVER <h2>-seksjon',
                'every_other_h2' => 'etter ANNENHVER <h2>-seksjon (2., 4., 6. osv.)',
                'every_third_h2' => 'etter HVER TREDJE <h2>-seksjon (3., 6., 9. osv.)',
            );
            $freq_desc = $freq_text[ $frequency ] ?? $freq_text['every_other_h2'];

            $prompt .= "\n\nINLINE-BILDER:";
            $prompt .= "\n- Plasser markøren [INLINE_IMAGE_1], [INLINE_IMAGE_2] osv. i innholdet {$freq_desc}.";
            $prompt .= "\n- Markøren skal stå alene på en linje mellom avsnitt, ETTER den relevante seksjonen.";
            $prompt .= "\n- I JSON-svaret skal du inkludere et ekstra felt \"inline_images\" som er en array med objekter:";
            $prompt .= "\n  [{\"marker\": \"[INLINE_IMAGE_1]\", \"prompt\": \"image description in English\", \"alt\": \"SEO alt text\", \"caption\": \"bildetekst\"}]";
        }

        $prompt .= "\n\nDu MÅ svare med gyldig JSON med følgende felter:\n";
        $json_spec = '{"title": "artikkel-tittel", "content": "full artikkel i ren HTML uten \\n", "excerpt": "kort oppsummering på 1-2 setninger", "category_hint": "foreslått kategori", "image_prompt": "beskrivelse for bildegenerering på engelsk, landscape-format", "image_alt": "SEO-vennlig alt-tekst for bildet", "image_caption": "kort bildetekst som vises under bildet"';

        if ( Settings::get( 'inline_images_enabled' ) ) {
            $json_spec .= ', "inline_images": [{"marker": "[INLINE_IMAGE_1]", "prompt": "...", "alt": "...", "caption": "..."}]';
        }

        $json_spec .= '}';
        $prompt .= $json_spec;

        return $prompt;
    }

    /**
     * Clean up AI-generated content.
     * Removes literal \n, normalizes whitespace, and ensures proper HTML structure.
     */
    private static function clean_content( $content ) {
        // Remove literal \n that AI may include in JSON string.
        $content = str_replace( array( "\\n", "\r\n", "\r" ), "\n", $content );

        // Remove newlines between HTML tags — let HTML handle spacing.
        $content = preg_replace( '/>\s*\n\s*</', '><', $content );

        // Remove leading/trailing whitespace from each line, then collapse to single string.
        $content = preg_replace( '/^\s+|\s+$/m', '', $content );

        // Remove any standalone \n that leaked through (not inside tags).
        $content = str_replace( "\n", '', $content );

        // Ensure paragraphs that are just text (not wrapped in tags) get wrapped.
        // Split on block-level closing tags and check for bare text.
        $content = trim( $content );

        return $content;
    }

    /**
     * Build the user prompt with news item and related links.
     */
    private function build_prompt( array $news_item, array $related_links = array() ) {
        $prompt  = "Skriv en original artikkel basert på følgende nyhet:\n\n";
        $prompt .= "Tittel: {$news_item['title']}\n";

        if ( ! empty( $news_item['description'] ) ) {
            $prompt .= "Beskrivelse: {$news_item['description']}\n";
        }

        if ( ! empty( $news_item['url'] ) && Settings::get( 'include_source_link' ) ) {
            $prompt .= "Kilde-URL: {$news_item['url']}\n";
            $prompt .= "\nInkluder en lenke til kilden i artikkelen.\n";
        }

        if ( ! empty( $related_links ) ) {
            $prompt .= "\nRelaterte artikler som bør lenkes til i teksten der det er naturlig:\n";
            foreach ( $related_links as $link ) {
                $prompt .= "- \"{$link['title']}\" ({$link['url']})\n";
            }
        }

        $prompt .= "\nSkriv en unik, engasjerende artikkel. Ikke kopier direkte fra kilden.";
        $prompt .= "\nSvar KUN med JSON som spesifisert i system-prompten.";

        return $prompt;
    }
}
