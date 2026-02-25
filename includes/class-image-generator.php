<?php

namespace WPAutopilot\Includes;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ImageGenerator {

    const MAX_POLLS    = 10;
    const POLL_INTERVAL = 6;

    /**
     * Generate an image and upload it to WordPress media library.
     *
     * @param string $prompt        Image generation prompt.
     * @param string $post_title    Post title for alt text / filename.
     * @param string $image_alt     SEO alt text for the image.
     * @param string $image_caption Caption / bildetekst for the image.
     * @return int|null Attachment ID or null on failure.
     */
    public function generate( $prompt, $post_title, $image_alt = '', $image_caption = '' ) {
        $api_key = Settings::get( 'fal_api_key' );
        if ( empty( $api_key ) ) {
            Logger::error( __( 'fal.ai API key is missing.', 'wp-autopilot' ) );
            return null;
        }

        if ( ! Settings::get( 'generate_images' ) ) {
            return null;
        }

        $model = Settings::get( 'image_custom_model' );
        if ( empty( $model ) ) {
            $model = Settings::get( 'image_model', 'fal-ai/flux-2-pro' );
        }
        $image_style = Settings::get( 'image_style', 'photorealistic editorial style' );
        $full_prompt = $image_style . ': ' . $prompt;

        // Step 1: Submit to queue.
        $request_id = $this->submit_to_queue( $api_key, $model, $full_prompt );
        if ( ! $request_id ) {
            return null;
        }

        // Step 2: Poll for result.
        $image_url = $this->poll_for_result( $api_key, $model, $request_id );
        if ( ! $image_url ) {
            return null;
        }

        // Step 3: Download and upload to WP media library.
        $attachment_id = $this->upload_to_media( $image_url, $post_title, $image_alt, $image_caption );

        if ( $attachment_id ) {
            /* translators: 1: attachment ID, 2: post title */
            Logger::info( sprintf( __( 'Image generated and uploaded (ID: %1$d) for "%2$s".', 'wp-autopilot' ), $attachment_id, $post_title ) );
        }

        return $attachment_id;
    }

    /**
     * Generate an inline image using the inline image model.
     *
     * @param string $prompt        Image generation prompt.
     * @param string $alt_text      Alt text for the image.
     * @param string $caption       Caption text.
     * @param string $post_title    Post title for filename.
     * @param int    $index         Image index (for unique filenames).
     * @return array {attachment_id, model} or {attachment_id: null, model}.
     */
    public function generate_inline( $prompt, $alt_text, $caption, $post_title, $index = 1 ) {
        $api_key = Settings::get( 'fal_api_key' );
        if ( empty( $api_key ) ) {
            Logger::error( __( 'fal.ai API key is missing (inline image).', 'wp-autopilot' ) );
            return array( 'attachment_id' => null, 'model' => '' );
        }

        $model = Settings::get( 'inline_image_custom_model' );
        if ( empty( $model ) ) {
            $model = Settings::get( 'inline_image_model', 'fal-ai/flux-2-pro' );
        }

        $image_style = Settings::get( 'image_style', 'photorealistic editorial style' );
        $full_prompt = $image_style . ': ' . $prompt;

        $request_id = $this->submit_to_queue( $api_key, $model, $full_prompt );
        if ( ! $request_id ) {
            return array( 'attachment_id' => null, 'model' => $model );
        }

        $image_url = $this->poll_for_result( $api_key, $model, $request_id );
        if ( ! $image_url ) {
            return array( 'attachment_id' => null, 'model' => $model );
        }

        $filename_title = $post_title . '-inline-' . $index;
        $attachment_id = $this->upload_to_media( $image_url, $filename_title, $alt_text, $caption );

        if ( $attachment_id ) {
            /* translators: 1: image index, 2: attachment ID, 3: post title */
            Logger::info( sprintf( __( 'Inline image %1$d generated (ID: %2$d) for "%3$s".', 'wp-autopilot' ), $index, $attachment_id, $post_title ) );
        }

        return array( 'attachment_id' => $attachment_id, 'model' => $model );
    }

    /**
     * Submit image generation request to fal.ai queue.
     *
     * @return string|null Request ID or null on failure.
     */
    private function submit_to_queue( $api_key, $model, $prompt ) {
        $response = wp_remote_post( "https://queue.fal.run/{$model}", array(
            'timeout' => 30,
            'headers' => array(
                'Content-Type'  => 'application/json',
                'Authorization' => 'Key ' . $api_key,
            ),
            'body' => wp_json_encode( array(
                'prompt'     => $prompt,
                'image_size' => 'landscape_16_9',
                'width'      => 1280,
                'height'     => 720,
                'aspect_ratio' => '16:9',
                'num_images' => 1,
            ) ),
        ) );

        if ( is_wp_error( $response ) ) {
            Logger::error( __( 'fal.ai queue error: ', 'wp-autopilot' ) . $response->get_error_message() );
            return null;
        }

        $data = json_decode( wp_remote_retrieve_body( $response ), true );
        $request_id = $data['request_id'] ?? null;

        if ( ! $request_id ) {
            Logger::error( __( 'Did not receive request_id from fal.ai.', 'wp-autopilot' ), $data );
            return null;
        }

        return $request_id;
    }

    /**
     * Poll fal.ai queue for completion.
     *
     * @return string|null Image URL or null on failure/timeout.
     */
    private function poll_for_result( $api_key, $model, $request_id ) {
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
                // Fetch the result.
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
                    Logger::error( __( 'Could not fetch fal.ai result: ', 'wp-autopilot' ) . $result_response->get_error_message() );
                    return null;
                }

                $result = json_decode( wp_remote_retrieve_body( $result_response ), true );
                return $result['images'][0]['url'] ?? null;
            }

            if ( $status === 'FAILED' ) {
                Logger::error( __( 'fal.ai image generation failed.', 'wp-autopilot' ), $data );
                return null;
            }
        }

        /* translators: %d: number of poll attempts */
        Logger::error( sprintf( __( 'fal.ai timeout after %d polls.', 'wp-autopilot' ), self::MAX_POLLS ) );
        return null;
    }

    /**
     * Download an image from URL and upload to WordPress media library.
     *
     * @param string $url           Image URL.
     * @param string $post_title    Post title for naming.
     * @param string $image_alt     SEO alt text.
     * @param string $image_caption Caption text.
     * @return int|null Attachment ID.
     */
    private function upload_to_media( $url, $post_title, $image_alt = '', $image_caption = '' ) {
        if ( ! function_exists( 'media_sideload_image' ) ) {
            require_once ABSPATH . 'wp-admin/includes/media.php';
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/image.php';
        }

        // Download to temp file.
        $tmp = download_url( $url, 60 );
        if ( is_wp_error( $tmp ) ) {
            Logger::error( __( 'Could not download image: ', 'wp-autopilot' ) . $tmp->get_error_message() );
            return null;
        }

        $filename = sanitize_file_name( sanitize_title( $post_title ) ) . '.png';

        $file_array = array(
            'name'     => $filename,
            'tmp_name' => $tmp,
        );

        $attachment_id = media_handle_sideload( $file_array, 0, $post_title );

        if ( is_wp_error( $attachment_id ) ) {
            @unlink( $tmp );
            Logger::error( __( 'Could not upload image to media library: ', 'wp-autopilot' ) . $attachment_id->get_error_message() );
            return null;
        }

        // SEO: Set alt text (uses AI-generated alt, falls back to post title).
        $alt = ! empty( $image_alt ) ? sanitize_text_field( $image_alt ) : sanitize_text_field( $post_title );
        update_post_meta( $attachment_id, '_wp_attachment_image_alt', $alt );

        // SEO: Set caption and description on the attachment post.
        $caption_text = ! empty( $image_caption ) ? sanitize_text_field( $image_caption ) : '';
        wp_update_post( array(
            'ID'           => $attachment_id,
            'post_excerpt' => $caption_text,                    // Caption
            'post_content' => sanitize_text_field( $post_title ), // Description
            'post_title'   => $alt,                              // Title
        ) );

        return $attachment_id;
    }
}
