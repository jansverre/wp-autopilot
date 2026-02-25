<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use WPAutopilot\Includes\Settings;

$settings = Settings::all();
$post_authors = json_decode( $settings['post_authors'] ?? '[]', true );
if ( ! is_array( $post_authors ) ) {
    $post_authors = array();
}
$author_ids = array_column( $post_authors, 'id' );
?>

<?php include WPA_PLUGIN_DIR . 'admin/partials/header.php'; ?>

<?php settings_errors( 'wpa_settings' ); ?>

<form method="post" action="">
    <?php wp_nonce_field( 'wpa_save_settings', 'wpa_settings_nonce' ); ?>

    <!-- API Keys -->
    <div class="wpa-section">
        <h2><?php esc_html_e( 'API Keys', 'wp-autopilot' ); ?></h2>
        <table class="form-table">
            <tr>
                <th><label for="wpa_openrouter_api_key"><?php esc_html_e( 'OpenRouter API Key', 'wp-autopilot' ); ?></label></th>
                <td>
                    <input type="password" id="wpa_openrouter_api_key" name="wpa_openrouter_api_key"
                           value="<?php echo esc_attr( $settings['openrouter_api_key'] ); ?>"
                           class="regular-text" autocomplete="off">
                    <p class="description"><?php _e( 'Get yours at <a href="https://openrouter.ai/keys" target="_blank" rel="noopener">openrouter.ai/keys</a>', 'wp-autopilot' ); ?></p>
                </td>
            </tr>
            <tr>
                <th><label for="wpa_fal_api_key"><?php esc_html_e( 'fal.ai API Key', 'wp-autopilot' ); ?></label></th>
                <td>
                    <input type="password" id="wpa_fal_api_key" name="wpa_fal_api_key"
                           value="<?php echo esc_attr( $settings['fal_api_key'] ); ?>"
                           class="regular-text" autocomplete="off">
                    <p class="description"><?php _e( 'Get yours at <a href="https://fal.ai/dashboard/keys" target="_blank" rel="noopener">fal.ai/dashboard/keys</a>', 'wp-autopilot' ); ?></p>
                </td>
            </tr>
            <tr>
                <th><label for="wpa_github_token"><?php esc_html_e( 'GitHub Token (updates)', 'wp-autopilot' ); ?></label></th>
                <td>
                    <input type="password" id="wpa_github_token" name="wpa_github_token"
                           value="<?php echo esc_attr( $settings['github_token'] ); ?>"
                           class="regular-text" autocomplete="off">
                    <p class="description"><?php _e( 'Optional. A GitHub personal access token with <strong>no permissions</strong> (public repo). Avoids API rate limiting for update checks. Create one at <a href="https://github.com/settings/tokens/new" target="_blank" rel="noopener">github.com/settings/tokens</a>.', 'wp-autopilot' ); ?></p>
                </td>
            </tr>
        </table>
    </div>

    <!-- AI Settings -->
    <div class="wpa-section">
        <h2><?php esc_html_e( 'AI Settings', 'wp-autopilot' ); ?></h2>
        <table class="form-table">
            <tr>
                <th><label for="wpa_ai_model"><?php esc_html_e( 'AI Model', 'wp-autopilot' ); ?></label></th>
                <td>
                    <select id="wpa_ai_model" name="wpa_ai_model">
                        <option value="google/gemini-3-flash-preview" <?php selected( $settings['ai_model'], 'google/gemini-3-flash-preview' ); ?>>Gemini 3 Flash</option>
                        <option value="google/gemini-3.1-pro-preview" <?php selected( $settings['ai_model'], 'google/gemini-3.1-pro-preview' ); ?>>Gemini 3.1 Pro</option>
                        <option value="anthropic/claude-sonnet-4" <?php selected( $settings['ai_model'], 'anthropic/claude-sonnet-4' ); ?>>Claude Sonnet 4</option>
                        <option value="openai/gpt-4o-mini" <?php selected( $settings['ai_model'], 'openai/gpt-4o-mini' ); ?>>GPT-4o Mini</option>
                        <option value="openai/gpt-5-nano" <?php selected( $settings['ai_model'], 'openai/gpt-5-nano' ); ?>>GPT-5 Nano</option>
                        <option value="openai/gpt-5-mini" <?php selected( $settings['ai_model'], 'openai/gpt-5-mini' ); ?>>GPT-5 Mini</option>
                        <option value="qwen/qwen3.5-397b-a17b" <?php selected( $settings['ai_model'], 'qwen/qwen3.5-397b-a17b' ); ?>>Qwen 3.5 397B</option>
                    </select>
                </td>
            </tr>
            <tr>
                <th><label for="wpa_ai_custom_model"><?php esc_html_e( 'Custom Model ID', 'wp-autopilot' ); ?></label></th>
                <td>
                    <input type="text" id="wpa_ai_custom_model" name="wpa_ai_custom_model"
                           value="<?php echo esc_attr( $settings['ai_custom_model'] ); ?>"
                           class="regular-text" placeholder="<?php esc_attr_e( 'e.g. meta-llama/llama-3-70b-instruct', 'wp-autopilot' ); ?>">
                    <p class="description"><?php _e( 'Overrides the selection above. Leave empty to use the dropdown.', 'wp-autopilot' ); ?></p>
                </td>
            </tr>
            <tr>
                <th><label for="wpa_ai_language"><?php esc_html_e( 'Language', 'wp-autopilot' ); ?></label></th>
                <td>
                    <input type="text" id="wpa_ai_language" name="wpa_ai_language"
                           value="<?php echo esc_attr( $settings['ai_language'] ); ?>"
                           class="regular-text">
                </td>
            </tr>
            <tr>
                <th><label for="wpa_ai_niche"><?php esc_html_e( 'Niche / Topic', 'wp-autopilot' ); ?></label></th>
                <td>
                    <input type="text" id="wpa_ai_niche" name="wpa_ai_niche"
                           value="<?php echo esc_attr( $settings['ai_niche'] ); ?>"
                           class="regular-text" placeholder="<?php esc_attr_e( 'e.g. technology, health, finance', 'wp-autopilot' ); ?>">
                </td>
            </tr>
            <tr>
                <th><label for="wpa_ai_style"><?php esc_html_e( 'Writing Style (global fallback)', 'wp-autopilot' ); ?></label></th>
                <td>
                    <input type="text" id="wpa_ai_style" name="wpa_ai_style"
                           value="<?php echo esc_attr( $settings['ai_style'] ); ?>"
                           class="regular-text">
                    <p class="description"><?php _e( 'Used for authors without a custom writing style analysis.', 'wp-autopilot' ); ?></p>
                </td>
            </tr>
            <tr>
                <th><label for="wpa_ai_temperature"><?php esc_html_e( 'Temperature', 'wp-autopilot' ); ?></label></th>
                <td>
                    <input type="number" id="wpa_ai_temperature" name="wpa_ai_temperature"
                           value="<?php echo esc_attr( $settings['ai_temperature'] ); ?>"
                           min="0" max="2" step="0.1" class="small-text">
                    <p class="description"><?php _e( '0 = deterministic, 2 = very creative. Default: 0.7', 'wp-autopilot' ); ?></p>
                </td>
            </tr>
            <tr>
                <th><label for="wpa_site_identity"><?php esc_html_e( 'Site Identity', 'wp-autopilot' ); ?></label></th>
                <td>
                    <textarea id="wpa_site_identity" name="wpa_site_identity"
                              rows="5" class="large-text"><?php echo esc_textarea( $settings['site_identity'] ?? '' ); ?></textarea>
                    <p class="description"><?php _e( 'Describe your site\'s purpose, values, and target audience. Injected into the AI prompt to give articles the right tone and context.', 'wp-autopilot' ); ?></p>
                </td>
            </tr>
        </table>
    </div>

    <!-- Writing Style Analysis -->
    <div class="wpa-section">
        <h2><?php esc_html_e( 'Writing Style per Author', 'wp-autopilot' ); ?></h2>
        <p class="description" style="margin-bottom: 15px;"><?php _e( 'Analyze an author\'s writing style based on their published articles. The style is automatically used when the autopilot writes articles for this author.', 'wp-autopilot' ); ?></p>
        <table class="form-table">
            <tr>
                <th><label for="wpa_style_author"><?php esc_html_e( 'Select Author', 'wp-autopilot' ); ?></label></th>
                <td>
                    <?php
                    wp_dropdown_users( array(
                        'name'     => 'wpa_style_author',
                        'id'       => 'wpa_style_author',
                        'role__in' => array( 'administrator', 'editor', 'author' ),
                    ) );
                    ?>
                </td>
            </tr>
            <tr>
                <th><label for="wpa_writing_style_text"><?php esc_html_e( 'Writing Style', 'wp-autopilot' ); ?></label></th>
                <td>
                    <textarea id="wpa_writing_style_text" rows="6" class="large-text" placeholder="<?php esc_attr_e( 'Select an author and click &laquo;Analyze&raquo; to generate a writing style description automatically, or enter one manually.', 'wp-autopilot' ); ?>"></textarea>
                </td>
            </tr>
            <tr>
                <th><?php esc_html_e( 'Number of Articles to Analyze', 'wp-autopilot' ); ?></th>
                <td>
                    <input type="number" id="wpa_style_num_posts" value="5" min="1" max="20" class="small-text">
                </td>
            </tr>
            <tr>
                <th></th>
                <td>
                    <button type="button" id="wpa-analyze-style" class="button"><?php esc_html_e( 'Analyze Writing Style', 'wp-autopilot' ); ?></button>
                    <button type="button" id="wpa-save-style" class="button button-primary"><?php esc_html_e( 'Save Writing Style', 'wp-autopilot' ); ?></button>
                    <span id="wpa-style-spinner" class="spinner"></span>
                    <p id="wpa-style-message" class="wpa-message"></p>
                </td>
            </tr>
        </table>
    </div>

    <!-- Content Settings -->
    <div class="wpa-section">
        <h2><?php esc_html_e( 'Content Settings', 'wp-autopilot' ); ?></h2>
        <table class="form-table">
            <tr>
                <th><label for="wpa_min_words"><?php esc_html_e( 'Minimum Word Count', 'wp-autopilot' ); ?></label></th>
                <td>
                    <input type="number" id="wpa_min_words" name="wpa_min_words"
                           value="<?php echo esc_attr( $settings['min_words'] ); ?>"
                           min="100" step="50" class="small-text">
                </td>
            </tr>
            <tr>
                <th><label for="wpa_max_words"><?php esc_html_e( 'Maximum Word Count', 'wp-autopilot' ); ?></label></th>
                <td>
                    <input type="number" id="wpa_max_words" name="wpa_max_words"
                           value="<?php echo esc_attr( $settings['max_words'] ); ?>"
                           min="100" step="50" class="small-text">
                </td>
            </tr>
            <tr>
                <th><label for="wpa_include_source_link"><?php esc_html_e( 'Include Source Link', 'wp-autopilot' ); ?></label></th>
                <td>
                    <label>
                        <input type="checkbox" id="wpa_include_source_link" name="wpa_include_source_link"
                               value="1" <?php checked( $settings['include_source_link'] ); ?>>
                        <?php esc_html_e( 'Add link to the original article', 'wp-autopilot' ); ?>
                    </label>
                </td>
            </tr>
        </table>
    </div>

    <!-- Publishing Settings -->
    <div class="wpa-section">
        <h2><?php esc_html_e( 'Publishing Settings', 'wp-autopilot' ); ?></h2>
        <table class="form-table">
            <tr>
                <th><label for="wpa_post_status"><?php esc_html_e( 'Post Status', 'wp-autopilot' ); ?></label></th>
                <td>
                    <select id="wpa_post_status" name="wpa_post_status">
                        <option value="draft" <?php selected( $settings['post_status'], 'draft' ); ?>><?php echo esc_html__( 'Draft', 'wp-autopilot' ); ?></option>
                        <option value="publish" <?php selected( $settings['post_status'], 'publish' ); ?>><?php echo esc_html__( 'Published', 'wp-autopilot' ); ?></option>
                        <option value="pending" <?php selected( $settings['post_status'], 'pending' ); ?>><?php echo esc_html__( 'Pending Review', 'wp-autopilot' ); ?></option>
                    </select>
                </td>
            </tr>
            <tr>
                <th><label for="wpa_post_author"><?php esc_html_e( 'Default Author', 'wp-autopilot' ); ?></label></th>
                <td>
                    <?php
                    wp_dropdown_users( array(
                        'name'     => 'wpa_post_author',
                        'id'       => 'wpa_post_author',
                        'selected' => $settings['post_author'],
                        'role__in' => array( 'administrator', 'editor', 'author' ),
                    ) );
                    ?>
                    <p class="description"><?php _e( 'Used when author method is set to &laquo;Single&raquo;.', 'wp-autopilot' ); ?></p>
                </td>
            </tr>
            <tr>
                <th><label for="wpa_author_method"><?php esc_html_e( 'Author Method', 'wp-autopilot' ); ?></label></th>
                <td>
                    <select id="wpa_author_method" name="wpa_author_method">
                        <option value="single" <?php selected( $settings['author_method'] ?? 'single', 'single' ); ?>><?php echo esc_html__( 'Single (default author)', 'wp-autopilot' ); ?></option>
                        <option value="random" <?php selected( $settings['author_method'] ?? 'single', 'random' ); ?>><?php echo esc_html__( 'Random', 'wp-autopilot' ); ?></option>
                        <option value="round_robin" <?php selected( $settings['author_method'] ?? 'single', 'round_robin' ); ?>><?php echo esc_html__( 'Round Robin (rotating)', 'wp-autopilot' ); ?></option>
                        <option value="percentage" <?php selected( $settings['author_method'] ?? 'single', 'percentage' ); ?>><?php echo esc_html__( 'Weighted Distribution', 'wp-autopilot' ); ?></option>
                    </select>
                </td>
            </tr>
            <tr id="wpa-authors-row" style="<?php echo ( $settings['author_method'] ?? 'single' ) === 'single' ? 'display:none;' : ''; ?>">
                <th><?php esc_html_e( 'Authors', 'wp-autopilot' ); ?></th>
                <td>
                    <div id="wpa-authors-list">
                        <?php
                        $users = get_users( array( 'role__in' => array( 'administrator', 'editor', 'author' ) ) );
                        foreach ( $users as $user ) :
                            $is_selected = in_array( $user->ID, $author_ids, true );
                            $weight = 1;
                            foreach ( $post_authors as $pa ) {
                                if ( (int) $pa['id'] === $user->ID ) {
                                    $weight = (int) $pa['weight'];
                                    break;
                                }
                            }
                        ?>
                            <div class="wpa-author-item" style="margin-bottom: 5px;">
                                <label>
                                    <input type="checkbox" class="wpa-author-check" value="<?php echo esc_attr( $user->ID ); ?>"
                                           <?php checked( $is_selected ); ?>>
                                    <?php echo esc_html( $user->display_name ); ?>
                                </label>
                                <span class="wpa-author-weight" style="<?php echo ( $settings['author_method'] ?? 'single' ) === 'percentage' ? '' : 'display:none;'; ?>">
                                    — <?php esc_html_e( 'weight:', 'wp-autopilot' ); ?> <input type="number" class="wpa-weight-input small-text" min="1" max="100" value="<?php echo esc_attr( $weight ); ?>" style="width: 50px;">
                                </span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <input type="hidden" id="wpa_post_authors" name="wpa_post_authors"
                           value="<?php echo esc_attr( $settings['post_authors'] ?? '[]' ); ?>">
                    <p class="description"><?php _e( 'Select which authors to use. For weighted distribution, the weight numbers indicate relative proportion.', 'wp-autopilot' ); ?></p>
                </td>
            </tr>
            <tr>
                <th><label for="wpa_default_category"><?php esc_html_e( 'Default Category', 'wp-autopilot' ); ?></label></th>
                <td>
                    <?php
                    wp_dropdown_categories( array(
                        'name'             => 'wpa_default_category',
                        'id'               => 'wpa_default_category',
                        'selected'         => $settings['default_category'],
                        'show_option_none' => __( '— Use AI suggestion —', 'wp-autopilot' ),
                        'option_none_value' => '0',
                        'hide_empty'       => false,
                    ) );
                    ?>
                </td>
            </tr>
        </table>
    </div>

    <!-- Image Settings -->
    <div class="wpa-section">
        <h2><?php esc_html_e( 'Image Settings', 'wp-autopilot' ); ?></h2>
        <table class="form-table">
            <tr>
                <th><label for="wpa_generate_images"><?php esc_html_e( 'Generate Images', 'wp-autopilot' ); ?></label></th>
                <td>
                    <label>
                        <input type="checkbox" id="wpa_generate_images" name="wpa_generate_images"
                               value="1" <?php checked( $settings['generate_images'] ); ?>>
                        <?php esc_html_e( 'Generate featured image with AI', 'wp-autopilot' ); ?>
                    </label>
                </td>
            </tr>
            <tr>
                <th><label for="wpa_image_model"><?php esc_html_e( 'Image Model (fal.ai)', 'wp-autopilot' ); ?></label></th>
                <td>
                    <select id="wpa_image_model" name="wpa_image_model">
                        <option value="fal-ai/flux-2-pro" <?php selected( $settings['image_model'], 'fal-ai/flux-2-pro' ); ?>>FLUX 2 Pro</option>
                        <option value="fal-ai/flux-2/klein/realtime" <?php selected( $settings['image_model'], 'fal-ai/flux-2/klein/realtime' ); ?>>FLUX 2 Klein Realtime</option>
                        <option value="fal-ai/nano-banana-pro" <?php selected( $settings['image_model'], 'fal-ai/nano-banana-pro' ); ?>>Nano Banana Pro</option>
                        <option value="xai/grok-imagine-image" <?php selected( $settings['image_model'], 'xai/grok-imagine-image' ); ?>>Grok Imagine</option>
                    </select>
                </td>
            </tr>
            <tr>
                <th><label for="wpa_image_custom_model"><?php esc_html_e( 'Custom Image Model', 'wp-autopilot' ); ?></label></th>
                <td>
                    <input type="text" id="wpa_image_custom_model" name="wpa_image_custom_model"
                           value="<?php echo esc_attr( $settings['image_custom_model'] ?? '' ); ?>"
                           class="regular-text" placeholder="<?php esc_attr_e( 'e.g. fal-ai/stable-diffusion-v35-large', 'wp-autopilot' ); ?>">
                    <p class="description"><?php _e( 'Overrides the selection above. Leave empty to use the dropdown.', 'wp-autopilot' ); ?></p>
                </td>
            </tr>
            <tr>
                <th><label for="wpa_image_style"><?php esc_html_e( 'Image Style Prefix', 'wp-autopilot' ); ?></label></th>
                <td>
                    <input type="text" id="wpa_image_style" name="wpa_image_style"
                           value="<?php echo esc_attr( $settings['image_style'] ); ?>"
                           class="regular-text">
                    <p class="description"><?php _e( 'Prepended to the AI-generated image prompt.', 'wp-autopilot' ); ?></p>
                </td>
            </tr>
        </table>
    </div>

    <!-- Inline Images -->
    <div class="wpa-section">
        <h2><?php esc_html_e( 'Inline Images', 'wp-autopilot' ); ?></h2>
        <table class="form-table">
            <tr>
                <th><label for="wpa_inline_images_enabled"><?php esc_html_e( 'Enable Inline Images', 'wp-autopilot' ); ?></label></th>
                <td>
                    <label>
                        <input type="checkbox" id="wpa_inline_images_enabled" name="wpa_inline_images_enabled"
                               value="1" <?php checked( $settings['inline_images_enabled'] ?? false ); ?>>
                        <?php esc_html_e( 'Generate images within the article text at H2 sections', 'wp-autopilot' ); ?>
                    </label>
                    <p class="description"><?php _e( 'Note: Each inline image takes 6&ndash;60 seconds. With many images per article, the run can take a long time.', 'wp-autopilot' ); ?></p>
                </td>
            </tr>
            <tr id="wpa-inline-freq-row" style="<?php echo empty( $settings['inline_images_enabled'] ) ? 'display:none;' : ''; ?>">
                <th><label for="wpa_inline_images_frequency"><?php esc_html_e( 'Frequency', 'wp-autopilot' ); ?></label></th>
                <td>
                    <select id="wpa_inline_images_frequency" name="wpa_inline_images_frequency">
                        <option value="every_h2" <?php selected( $settings['inline_images_frequency'] ?? 'every_other_h2', 'every_h2' ); ?>><?php echo esc_html__( 'After every H2', 'wp-autopilot' ); ?></option>
                        <option value="every_other_h2" <?php selected( $settings['inline_images_frequency'] ?? 'every_other_h2', 'every_other_h2' ); ?>><?php echo esc_html__( 'After every other H2', 'wp-autopilot' ); ?></option>
                        <option value="every_third_h2" <?php selected( $settings['inline_images_frequency'] ?? 'every_other_h2', 'every_third_h2' ); ?>><?php echo esc_html__( 'After every third H2', 'wp-autopilot' ); ?></option>
                    </select>
                </td>
            </tr>
            <tr id="wpa-inline-model-row" style="<?php echo empty( $settings['inline_images_enabled'] ) ? 'display:none;' : ''; ?>">
                <th><label for="wpa_inline_image_model"><?php esc_html_e( 'Inline Image Model', 'wp-autopilot' ); ?></label></th>
                <td>
                    <select id="wpa_inline_image_model" name="wpa_inline_image_model">
                        <option value="fal-ai/flux-2-pro" <?php selected( $settings['inline_image_model'] ?? 'fal-ai/flux-2-pro', 'fal-ai/flux-2-pro' ); ?>>FLUX 2 Pro</option>
                        <option value="fal-ai/flux-2/klein/realtime" <?php selected( $settings['inline_image_model'] ?? 'fal-ai/flux-2-pro', 'fal-ai/flux-2/klein/realtime' ); ?>>FLUX 2 Klein Realtime</option>
                        <option value="fal-ai/nano-banana-pro" <?php selected( $settings['inline_image_model'] ?? 'fal-ai/flux-2-pro', 'fal-ai/nano-banana-pro' ); ?>>Nano Banana Pro</option>
                        <option value="xai/grok-imagine-image" <?php selected( $settings['inline_image_model'] ?? 'fal-ai/flux-2-pro', 'xai/grok-imagine-image' ); ?>>Grok Imagine</option>
                    </select>
                    <p class="description"><?php _e( 'Choose a cheaper model for inline images to save costs.', 'wp-autopilot' ); ?></p>
                </td>
            </tr>
            <tr id="wpa-inline-custom-row" style="<?php echo empty( $settings['inline_images_enabled'] ) ? 'display:none;' : ''; ?>">
                <th><label for="wpa_inline_image_custom_model"><?php esc_html_e( 'Custom Inline Model', 'wp-autopilot' ); ?></label></th>
                <td>
                    <input type="text" id="wpa_inline_image_custom_model" name="wpa_inline_image_custom_model"
                           value="<?php echo esc_attr( $settings['inline_image_custom_model'] ?? '' ); ?>"
                           class="regular-text" placeholder="<?php esc_attr_e( 'Overrides the dropdown selection', 'wp-autopilot' ); ?>">
                </td>
            </tr>
        </table>
    </div>

    <!-- Cron Settings -->
    <div class="wpa-section">
        <h2><?php esc_html_e( 'Automatic Scheduling', 'wp-autopilot' ); ?></h2>
        <table class="form-table">
            <tr>
                <th><label for="wpa_enabled"><?php esc_html_e( 'Enabled', 'wp-autopilot' ); ?></label></th>
                <td>
                    <label>
                        <input type="checkbox" id="wpa_enabled" name="wpa_enabled"
                               value="1" <?php checked( $settings['enabled'] ); ?>>
                        <?php esc_html_e( 'Enable automatic publishing', 'wp-autopilot' ); ?>
                    </label>
                </td>
            </tr>
            <tr>
                <th><label for="wpa_cron_interval"><?php esc_html_e( 'Run Interval', 'wp-autopilot' ); ?></label></th>
                <td>
                    <select id="wpa_cron_interval" name="wpa_cron_interval">
                        <option value="hourly" <?php selected( $settings['cron_interval'], 'hourly' ); ?>><?php echo esc_html__( 'Every Hour', 'wp-autopilot' ); ?></option>
                        <option value="every_2_hours" <?php selected( $settings['cron_interval'], 'every_2_hours' ); ?>><?php echo esc_html__( 'Every 2 Hours', 'wp-autopilot' ); ?></option>
                        <option value="every_6_hours" <?php selected( $settings['cron_interval'], 'every_6_hours' ); ?>><?php echo esc_html__( 'Every 6 Hours', 'wp-autopilot' ); ?></option>
                        <option value="every_12_hours" <?php selected( $settings['cron_interval'], 'every_12_hours' ); ?>><?php echo esc_html__( 'Every 12 Hours', 'wp-autopilot' ); ?></option>
                        <option value="daily" <?php selected( $settings['cron_interval'], 'daily' ); ?>><?php echo esc_html__( 'Daily', 'wp-autopilot' ); ?></option>
                    </select>
                </td>
            </tr>
            <tr>
                <th><label for="wpa_max_per_run"><?php esc_html_e( 'Max Articles per Run', 'wp-autopilot' ); ?></label></th>
                <td>
                    <input type="number" id="wpa_max_per_run" name="wpa_max_per_run"
                           value="<?php echo esc_attr( $settings['max_per_run'] ); ?>"
                           min="1" max="20" class="small-text">
                </td>
            </tr>
            <tr>
                <th><label for="wpa_max_per_day"><?php esc_html_e( 'Max Articles per Day', 'wp-autopilot' ); ?></label></th>
                <td>
                    <input type="number" id="wpa_max_per_day" name="wpa_max_per_day"
                           value="<?php echo esc_attr( $settings['max_per_day'] ); ?>"
                           min="1" max="100" class="small-text">
                </td>
            </tr>
            <tr>
                <th><?php esc_html_e( 'Work Hours', 'wp-autopilot' ); ?></th>
                <td>
                    <label>
                        <input type="checkbox" id="wpa_work_hours_enabled" name="wpa_work_hours_enabled"
                               value="1" <?php checked( $settings['work_hours_enabled'] ?? false ); ?>>
                        <?php esc_html_e( 'Limit publishing to work hours', 'wp-autopilot' ); ?>
                    </label>
                    <div style="margin-top: 8px;">
                        <label for="wpa_work_hours_start"><?php esc_html_e( 'From', 'wp-autopilot' ); ?></label>
                        <select id="wpa_work_hours_start" name="wpa_work_hours_start">
                            <?php for ( $h = 0; $h < 24; $h++ ) : ?>
                                <option value="<?php echo $h; ?>" <?php selected( (int) ( $settings['work_hours_start'] ?? 8 ), $h ); ?>>
                                    <?php echo str_pad( $h, 2, '0', STR_PAD_LEFT ) . ':00'; ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                        <label for="wpa_work_hours_end" style="margin-left: 10px;"><?php esc_html_e( 'to', 'wp-autopilot' ); ?></label>
                        <select id="wpa_work_hours_end" name="wpa_work_hours_end">
                            <?php for ( $h = 0; $h < 24; $h++ ) : ?>
                                <option value="<?php echo $h; ?>" <?php selected( (int) ( $settings['work_hours_end'] ?? 22 ), $h ); ?>>
                                    <?php echo str_pad( $h, 2, '0', STR_PAD_LEFT ) . ':00'; ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <p class="description"><?php _e( 'The autopilot only publishes within this time window (WordPress timezone).', 'wp-autopilot' ); ?></p>
                </td>
            </tr>
        </table>
    </div>

    <!-- Facebook Sharing -->
    <div class="wpa-section">
        <h2><?php esc_html_e( 'Facebook Sharing', 'wp-autopilot' ); ?></h2>
        <table class="form-table">
            <tr>
                <th><label for="wpa_fb_enabled"><?php esc_html_e( 'Enable Facebook Sharing', 'wp-autopilot' ); ?></label></th>
                <td>
                    <label>
                        <input type="checkbox" id="wpa_fb_enabled" name="wpa_fb_enabled"
                               value="1" <?php checked( $settings['fb_enabled'] ?? false ); ?>>
                        <?php esc_html_e( 'Automatically share autopilot articles to a Facebook Page', 'wp-autopilot' ); ?>
                    </label>
                </td>
            </tr>
        </table>
        <div id="wpa-fb-settings" style="<?php echo empty( $settings['fb_enabled'] ) ? 'display:none;' : ''; ?>">
            <table class="form-table">
                <tr>
                    <th><label for="wpa_fb_page_id"><?php esc_html_e( 'Facebook Page ID', 'wp-autopilot' ); ?></label></th>
                    <td>
                        <input type="text" id="wpa_fb_page_id" name="wpa_fb_page_id"
                               value="<?php echo esc_attr( $settings['fb_page_id'] ?? '' ); ?>"
                               class="regular-text">
                        <p class="description"><?php _e( 'Find your Page ID under Settings &rarr; About on your Facebook Page, or visit <a href="https://www.facebook.com/pages/?category=your_pages" target="_blank" rel="noopener">facebook.com/pages</a>.', 'wp-autopilot' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th><label for="wpa_fb_access_token"><?php esc_html_e( 'Page Access Token', 'wp-autopilot' ); ?></label></th>
                    <td>
                        <input type="password" id="wpa_fb_access_token" name="wpa_fb_access_token"
                               value="<?php echo esc_attr( $settings['fb_access_token'] ?? '' ); ?>"
                               class="large-text" autocomplete="off">
                        <p class="description"><?php _e( 'Page Access Token with <code>pages_manage_posts</code> and <code>pages_read_engagement</code> permissions. Generate via <a href="https://developers.facebook.com/tools/explorer/" target="_blank" rel="noopener">Graph API Explorer</a>.', 'wp-autopilot' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th></th>
                    <td>
                        <button type="button" id="wpa-test-fb" class="button"><?php esc_html_e( 'Test Connection', 'wp-autopilot' ); ?></button>
                        <span id="wpa-fb-test-spinner" class="spinner"></span>
                        <span id="wpa-fb-test-message" class="wpa-message"></span>
                    </td>
                </tr>
                <tr>
                    <th><label for="wpa_fb_image_mode"><?php esc_html_e( 'Image Type', 'wp-autopilot' ); ?></label></th>
                    <td>
                        <select id="wpa_fb_image_mode" name="wpa_fb_image_mode">
                            <option value="featured_image" <?php selected( $settings['fb_image_mode'] ?? 'featured_image', 'featured_image' ); ?>><?php echo esc_html__( 'Featured image (OG scraping)', 'wp-autopilot' ); ?></option>
                            <option value="generated_poster" <?php selected( $settings['fb_image_mode'] ?? 'featured_image', 'generated_poster' ); ?>><?php echo esc_html__( 'AI-generated poster', 'wp-autopilot' ); ?></option>
                        </select>
                        <p class="description"><?php _e( 'Featured image: Facebook fetches the image automatically from the article. AI poster: Generates a unique poster with fal.ai.', 'wp-autopilot' ); ?></p>
                    </td>
                </tr>
            </table>
            <div id="wpa-fb-poster-settings" style="<?php echo ( $settings['fb_image_mode'] ?? 'featured_image' ) !== 'generated_poster' ? 'display:none;' : ''; ?>">
                <table class="form-table">
                    <tr>
                        <th><label for="wpa_fb_author_face"><?php esc_html_e( 'Author in Poster', 'wp-autopilot' ); ?></label></th>
                        <td>
                            <label>
                                <input type="checkbox" id="wpa_fb_author_face" name="wpa_fb_author_face"
                                       value="1" <?php checked( $settings['fb_author_face'] ?? false ); ?>>
                                <?php esc_html_e( 'Include the author\'s face in the poster (requires reference photo)', 'wp-autopilot' ); ?>
                            </label>
                        </td>
                    </tr>
                </table>
                <div id="wpa-fb-author-photos" style="<?php echo empty( $settings['fb_author_face'] ) ? 'display:none;' : ''; ?>">
                    <h3 style="margin-top: 20px;"><?php esc_html_e( 'Author Reference Photos', 'wp-autopilot' ); ?></h3>
                    <p class="description" style="margin-bottom: 10px;"><?php _e( 'Upload a portrait photo for each author. The photo is used as a reference for the AI poster.', 'wp-autopilot' ); ?></p>
                    <table class="widefat" style="max-width: 700px;">
                        <thead>
                            <tr>
                                <th><?php esc_html_e( 'Author', 'wp-autopilot' ); ?></th>
                                <th><?php esc_html_e( 'Photo', 'wp-autopilot' ); ?></th>
                                <th><?php esc_html_e( 'Action', 'wp-autopilot' ); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $fb_author_photos = json_decode( $settings['fb_author_photos'] ?? '{}', true );
                            if ( ! is_array( $fb_author_photos ) ) {
                                $fb_author_photos = array();
                            }
                            $wp_users = get_users( array( 'role__in' => array( 'administrator', 'editor', 'author' ) ) );
                            foreach ( $wp_users as $user ) :
                                $photo_attachment_id = $fb_author_photos[ $user->ID ] ?? 0;
                                $photo_url = $photo_attachment_id ? wp_get_attachment_image_url( (int) $photo_attachment_id, 'thumbnail' ) : '';
                            ?>
                                <tr>
                                    <td><?php echo esc_html( $user->display_name ); ?></td>
                                    <td>
                                        <div class="wpa-fb-photo-preview" data-author-id="<?php echo esc_attr( $user->ID ); ?>">
                                            <?php if ( $photo_url ) : ?>
                                                <img src="<?php echo esc_url( $photo_url ); ?>" style="max-width: 60px; max-height: 60px; border-radius: 4px;">
                                            <?php else : ?>
                                                <span class="dashicons dashicons-format-image" style="font-size: 40px; color: #ccc;"></span>
                                            <?php endif; ?>
                                        </div>
                                        <input type="hidden" name="wpa_fb_author_photo_<?php echo esc_attr( $user->ID ); ?>"
                                               class="wpa-fb-photo-input"
                                               data-author-id="<?php echo esc_attr( $user->ID ); ?>"
                                               value="<?php echo esc_attr( $photo_attachment_id ); ?>">
                                    </td>
                                    <td>
                                        <button type="button" class="button wpa-fb-upload-photo" data-author-id="<?php echo esc_attr( $user->ID ); ?>"><?php esc_html_e( 'Upload', 'wp-autopilot' ); ?></button>
                                        <button type="button" class="button wpa-fb-remove-photo" data-author-id="<?php echo esc_attr( $user->ID ); ?>"
                                                style="<?php echo $photo_attachment_id ? '' : 'display:none;'; ?>"><?php esc_html_e( 'Remove', 'wp-autopilot' ); ?></button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Keyword Filtering -->
    <div class="wpa-section">
        <h2><?php esc_html_e( 'Keyword Filtering', 'wp-autopilot' ); ?></h2>
        <table class="form-table">
            <tr>
                <th><label for="wpa_keyword_include"><?php esc_html_e( 'Include Keywords', 'wp-autopilot' ); ?></label></th>
                <td>
                    <input type="text" id="wpa_keyword_include" name="wpa_keyword_include"
                           value="<?php echo esc_attr( $settings['keyword_include'] ); ?>"
                           class="large-text" placeholder="<?php esc_attr_e( 'e.g. technology, AI, climate', 'wp-autopilot' ); ?>">
                    <p class="description"><?php _e( 'Comma-separated. At least one word must appear in title/description. Leave empty to include all.', 'wp-autopilot' ); ?></p>
                </td>
            </tr>
            <tr>
                <th><label for="wpa_keyword_exclude"><?php esc_html_e( 'Exclude Keywords', 'wp-autopilot' ); ?></label></th>
                <td>
                    <input type="text" id="wpa_keyword_exclude" name="wpa_keyword_exclude"
                           value="<?php echo esc_attr( $settings['keyword_exclude'] ); ?>"
                           class="large-text" placeholder="<?php esc_attr_e( 'e.g. sports, entertainment', 'wp-autopilot' ); ?>">
                    <p class="description"><?php _e( 'Comma-separated. Articles containing these words will be filtered out.', 'wp-autopilot' ); ?></p>
                </td>
            </tr>
        </table>
    </div>

    <!-- Data Management -->
    <div class="wpa-section">
        <h2><?php esc_html_e( 'Data Management', 'wp-autopilot' ); ?></h2>
        <table class="form-table">
            <tr>
                <th><label for="wpa_delete_data_on_uninstall"><?php esc_html_e( 'Delete Data on Uninstall', 'wp-autopilot' ); ?></label></th>
                <td>
                    <label>
                        <input type="checkbox" id="wpa_delete_data_on_uninstall" name="wpa_delete_data_on_uninstall"
                               value="1" <?php checked( $settings['delete_data_on_uninstall'] ?? false ); ?>>
                        <?php esc_html_e( 'Delete all settings, feeds, logs, and database tables when the plugin is uninstalled', 'wp-autopilot' ); ?>
                    </label>
                    <p class="description"><?php _e( 'Leave this off if you plan to reinstall or update manually. Data is preserved between installations.', 'wp-autopilot' ); ?></p>
                </td>
            </tr>
        </table>
    </div>

    <?php submit_button( __( 'Save Settings', 'wp-autopilot' ) ); ?>
</form>

<?php include WPA_PLUGIN_DIR . 'admin/partials/footer.php'; ?>
