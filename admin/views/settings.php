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
        <h2>API-nøkler</h2>
        <table class="form-table">
            <tr>
                <th><label for="wpa_openrouter_api_key">OpenRouter API-nøkkel</label></th>
                <td>
                    <input type="password" id="wpa_openrouter_api_key" name="wpa_openrouter_api_key"
                           value="<?php echo esc_attr( $settings['openrouter_api_key'] ); ?>"
                           class="regular-text" autocomplete="off">
                    <p class="description">Hentes fra <a href="https://openrouter.ai/keys" target="_blank" rel="noopener">openrouter.ai/keys</a></p>
                </td>
            </tr>
            <tr>
                <th><label for="wpa_fal_api_key">fal.ai API-nøkkel</label></th>
                <td>
                    <input type="password" id="wpa_fal_api_key" name="wpa_fal_api_key"
                           value="<?php echo esc_attr( $settings['fal_api_key'] ); ?>"
                           class="regular-text" autocomplete="off">
                    <p class="description">Hentes fra <a href="https://fal.ai/dashboard/keys" target="_blank" rel="noopener">fal.ai/dashboard/keys</a></p>
                </td>
            </tr>
        </table>
    </div>

    <!-- AI Settings -->
    <div class="wpa-section">
        <h2>AI-innstillinger</h2>
        <table class="form-table">
            <tr>
                <th><label for="wpa_ai_model">AI-modell</label></th>
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
                <th><label for="wpa_ai_custom_model">Egendefinert modell-ID</label></th>
                <td>
                    <input type="text" id="wpa_ai_custom_model" name="wpa_ai_custom_model"
                           value="<?php echo esc_attr( $settings['ai_custom_model'] ); ?>"
                           class="regular-text" placeholder="f.eks. meta-llama/llama-3-70b-instruct">
                    <p class="description">Overstyrer valget ovenfor. La stå tom for å bruke dropdown-valget.</p>
                </td>
            </tr>
            <tr>
                <th><label for="wpa_ai_language">Språk</label></th>
                <td>
                    <input type="text" id="wpa_ai_language" name="wpa_ai_language"
                           value="<?php echo esc_attr( $settings['ai_language'] ); ?>"
                           class="regular-text">
                </td>
            </tr>
            <tr>
                <th><label for="wpa_ai_niche">Nisje / tema</label></th>
                <td>
                    <input type="text" id="wpa_ai_niche" name="wpa_ai_niche"
                           value="<?php echo esc_attr( $settings['ai_niche'] ); ?>"
                           class="regular-text" placeholder="f.eks. teknologi, helse, finans">
                </td>
            </tr>
            <tr>
                <th><label for="wpa_ai_style">Skrivestil (global fallback)</label></th>
                <td>
                    <input type="text" id="wpa_ai_style" name="wpa_ai_style"
                           value="<?php echo esc_attr( $settings['ai_style'] ); ?>"
                           class="regular-text">
                    <p class="description">Brukes for forfattere uten egen skrivestil-analyse.</p>
                </td>
            </tr>
            <tr>
                <th><label for="wpa_ai_temperature">Temperatur</label></th>
                <td>
                    <input type="number" id="wpa_ai_temperature" name="wpa_ai_temperature"
                           value="<?php echo esc_attr( $settings['ai_temperature'] ); ?>"
                           min="0" max="2" step="0.1" class="small-text">
                    <p class="description">0 = deterministisk, 2 = svært kreativ. Standard: 0.7</p>
                </td>
            </tr>
            <tr>
                <th><label for="wpa_site_identity">Nettstedidentitet</label></th>
                <td>
                    <textarea id="wpa_site_identity" name="wpa_site_identity"
                              rows="5" class="large-text"><?php echo esc_textarea( $settings['site_identity'] ?? '' ); ?></textarea>
                    <p class="description">Beskriv nettstedets formål, verdier og målgruppe. Injiseres i AI-prompten for å gi artiklene riktig tone og kontekst.</p>
                </td>
            </tr>
        </table>
    </div>

    <!-- Writing Style Analysis -->
    <div class="wpa-section">
        <h2>Skrivestil per forfatter</h2>
        <p class="description" style="margin-bottom: 15px;">Analyser skrivestilen til en forfatter basert på deres publiserte artikler. Stilen brukes automatisk når autopiloten skriver artikler for denne forfatteren.</p>
        <table class="form-table">
            <tr>
                <th><label for="wpa_style_author">Velg forfatter</label></th>
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
                <th><label for="wpa_writing_style_text">Skrivestil</label></th>
                <td>
                    <textarea id="wpa_writing_style_text" rows="6" class="large-text" placeholder="Velg en forfatter og klikk &laquo;Analyser&raquo; for å generere en skrivestil-beskrivelse automatisk, eller skriv inn manuelt."></textarea>
                </td>
            </tr>
            <tr>
                <th>Antall artikler å analysere</th>
                <td>
                    <input type="number" id="wpa_style_num_posts" value="5" min="1" max="20" class="small-text">
                </td>
            </tr>
            <tr>
                <th></th>
                <td>
                    <button type="button" id="wpa-analyze-style" class="button">Analyser skrivestil</button>
                    <button type="button" id="wpa-save-style" class="button button-primary">Lagre skrivestil</button>
                    <span id="wpa-style-spinner" class="spinner"></span>
                    <p id="wpa-style-message" class="wpa-message"></p>
                </td>
            </tr>
        </table>
    </div>

    <!-- Content Settings -->
    <div class="wpa-section">
        <h2>Innholdsinnstillinger</h2>
        <table class="form-table">
            <tr>
                <th><label for="wpa_min_words">Minimum antall ord</label></th>
                <td>
                    <input type="number" id="wpa_min_words" name="wpa_min_words"
                           value="<?php echo esc_attr( $settings['min_words'] ); ?>"
                           min="100" step="50" class="small-text">
                </td>
            </tr>
            <tr>
                <th><label for="wpa_max_words">Maksimum antall ord</label></th>
                <td>
                    <input type="number" id="wpa_max_words" name="wpa_max_words"
                           value="<?php echo esc_attr( $settings['max_words'] ); ?>"
                           min="100" step="50" class="small-text">
                </td>
            </tr>
            <tr>
                <th><label for="wpa_include_source_link">Inkluder kildelenke</label></th>
                <td>
                    <label>
                        <input type="checkbox" id="wpa_include_source_link" name="wpa_include_source_link"
                               value="1" <?php checked( $settings['include_source_link'] ); ?>>
                        Legg til lenke til originalartikkelen
                    </label>
                </td>
            </tr>
        </table>
    </div>

    <!-- Publishing Settings -->
    <div class="wpa-section">
        <h2>Publiseringsinnstillinger</h2>
        <table class="form-table">
            <tr>
                <th><label for="wpa_post_status">Innleggsstatus</label></th>
                <td>
                    <select id="wpa_post_status" name="wpa_post_status">
                        <option value="draft" <?php selected( $settings['post_status'], 'draft' ); ?>>Kladd</option>
                        <option value="publish" <?php selected( $settings['post_status'], 'publish' ); ?>>Publisert</option>
                        <option value="pending" <?php selected( $settings['post_status'], 'pending' ); ?>>Venter på godkjenning</option>
                    </select>
                </td>
            </tr>
            <tr>
                <th><label for="wpa_post_author">Standard forfatter</label></th>
                <td>
                    <?php
                    wp_dropdown_users( array(
                        'name'     => 'wpa_post_author',
                        'id'       => 'wpa_post_author',
                        'selected' => $settings['post_author'],
                        'role__in' => array( 'administrator', 'editor', 'author' ),
                    ) );
                    ?>
                    <p class="description">Brukes når forfattermetode er satt til &laquo;Enkelt&raquo;.</p>
                </td>
            </tr>
            <tr>
                <th><label for="wpa_author_method">Forfattermetode</label></th>
                <td>
                    <select id="wpa_author_method" name="wpa_author_method">
                        <option value="single" <?php selected( $settings['author_method'] ?? 'single', 'single' ); ?>>Enkelt (standard forfatter)</option>
                        <option value="random" <?php selected( $settings['author_method'] ?? 'single', 'random' ); ?>>Tilfeldig</option>
                        <option value="round_robin" <?php selected( $settings['author_method'] ?? 'single', 'round_robin' ); ?>>Round-robin (roterer)</option>
                        <option value="percentage" <?php selected( $settings['author_method'] ?? 'single', 'percentage' ); ?>>Prosentfordeling (vektet)</option>
                    </select>
                </td>
            </tr>
            <tr id="wpa-authors-row" style="<?php echo ( $settings['author_method'] ?? 'single' ) === 'single' ? 'display:none;' : ''; ?>">
                <th>Forfattere</th>
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
                                    — vekt: <input type="number" class="wpa-weight-input small-text" min="1" max="100" value="<?php echo esc_attr( $weight ); ?>" style="width: 50px;">
                                </span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <input type="hidden" id="wpa_post_authors" name="wpa_post_authors"
                           value="<?php echo esc_attr( $settings['post_authors'] ?? '[]' ); ?>">
                    <p class="description">Velg hvilke forfattere som skal brukes. Ved prosentfordeling angir vekt-tallene relativ andel.</p>
                </td>
            </tr>
            <tr>
                <th><label for="wpa_default_category">Standardkategori</label></th>
                <td>
                    <?php
                    wp_dropdown_categories( array(
                        'name'             => 'wpa_default_category',
                        'id'               => 'wpa_default_category',
                        'selected'         => $settings['default_category'],
                        'show_option_none' => '— Bruk AI-forslag —',
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
        <h2>Bildeinnstillinger</h2>
        <table class="form-table">
            <tr>
                <th><label for="wpa_generate_images">Generer bilder</label></th>
                <td>
                    <label>
                        <input type="checkbox" id="wpa_generate_images" name="wpa_generate_images"
                               value="1" <?php checked( $settings['generate_images'] ); ?>>
                        Generer featured image med AI
                    </label>
                </td>
            </tr>
            <tr>
                <th><label for="wpa_image_model">Bildemodell (fal.ai)</label></th>
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
                <th><label for="wpa_image_custom_model">Egendefinert bildemodell</label></th>
                <td>
                    <input type="text" id="wpa_image_custom_model" name="wpa_image_custom_model"
                           value="<?php echo esc_attr( $settings['image_custom_model'] ?? '' ); ?>"
                           class="regular-text" placeholder="f.eks. fal-ai/stable-diffusion-v35-large">
                    <p class="description">Overstyrer valget ovenfor. La stå tom for å bruke dropdown-valget.</p>
                </td>
            </tr>
            <tr>
                <th><label for="wpa_image_style">Bildestil-prefiks</label></th>
                <td>
                    <input type="text" id="wpa_image_style" name="wpa_image_style"
                           value="<?php echo esc_attr( $settings['image_style'] ); ?>"
                           class="regular-text">
                    <p class="description">Legges foran AI-generert bilde-prompt.</p>
                </td>
            </tr>
        </table>
    </div>

    <!-- Inline Images -->
    <div class="wpa-section">
        <h2>Inline-bilder</h2>
        <table class="form-table">
            <tr>
                <th><label for="wpa_inline_images_enabled">Aktiver inline-bilder</label></th>
                <td>
                    <label>
                        <input type="checkbox" id="wpa_inline_images_enabled" name="wpa_inline_images_enabled"
                               value="1" <?php checked( $settings['inline_images_enabled'] ?? false ); ?>>
                        Generer bilder inne i artikkelteksten ved H2-seksjoner
                    </label>
                    <p class="description">OBS: Hvert inline-bilde tar 6-60 sekunder. Med mange bilder per artikkel kan kjøringen ta lang tid.</p>
                </td>
            </tr>
            <tr id="wpa-inline-freq-row" style="<?php echo empty( $settings['inline_images_enabled'] ) ? 'display:none;' : ''; ?>">
                <th><label for="wpa_inline_images_frequency">Frekvens</label></th>
                <td>
                    <select id="wpa_inline_images_frequency" name="wpa_inline_images_frequency">
                        <option value="every_h2" <?php selected( $settings['inline_images_frequency'] ?? 'every_other_h2', 'every_h2' ); ?>>Etter hver H2</option>
                        <option value="every_other_h2" <?php selected( $settings['inline_images_frequency'] ?? 'every_other_h2', 'every_other_h2' ); ?>>Etter annenhver H2</option>
                        <option value="every_third_h2" <?php selected( $settings['inline_images_frequency'] ?? 'every_other_h2', 'every_third_h2' ); ?>>Etter hver tredje H2</option>
                    </select>
                </td>
            </tr>
            <tr id="wpa-inline-model-row" style="<?php echo empty( $settings['inline_images_enabled'] ) ? 'display:none;' : ''; ?>">
                <th><label for="wpa_inline_image_model">Inline-bildemodell</label></th>
                <td>
                    <select id="wpa_inline_image_model" name="wpa_inline_image_model">
                        <option value="fal-ai/flux-2-pro" <?php selected( $settings['inline_image_model'] ?? 'fal-ai/flux-2-pro', 'fal-ai/flux-2-pro' ); ?>>FLUX 2 Pro</option>
                        <option value="fal-ai/flux-2/klein/realtime" <?php selected( $settings['inline_image_model'] ?? 'fal-ai/flux-2-pro', 'fal-ai/flux-2/klein/realtime' ); ?>>FLUX 2 Klein Realtime</option>
                        <option value="fal-ai/nano-banana-pro" <?php selected( $settings['inline_image_model'] ?? 'fal-ai/flux-2-pro', 'fal-ai/nano-banana-pro' ); ?>>Nano Banana Pro</option>
                        <option value="xai/grok-imagine-image" <?php selected( $settings['inline_image_model'] ?? 'fal-ai/flux-2-pro', 'xai/grok-imagine-image' ); ?>>Grok Imagine</option>
                    </select>
                    <p class="description">Velg en billigere modell for inline-bilder for å spare kostnader.</p>
                </td>
            </tr>
            <tr id="wpa-inline-custom-row" style="<?php echo empty( $settings['inline_images_enabled'] ) ? 'display:none;' : ''; ?>">
                <th><label for="wpa_inline_image_custom_model">Egendefinert inline-modell</label></th>
                <td>
                    <input type="text" id="wpa_inline_image_custom_model" name="wpa_inline_image_custom_model"
                           value="<?php echo esc_attr( $settings['inline_image_custom_model'] ?? '' ); ?>"
                           class="regular-text" placeholder="Overstyrer dropdown-valget">
                </td>
            </tr>
        </table>
    </div>

    <!-- Cron Settings -->
    <div class="wpa-section">
        <h2>Automatisk kjøring</h2>
        <table class="form-table">
            <tr>
                <th><label for="wpa_enabled">Aktivert</label></th>
                <td>
                    <label>
                        <input type="checkbox" id="wpa_enabled" name="wpa_enabled"
                               value="1" <?php checked( $settings['enabled'] ); ?>>
                        Aktiver automatisk publisering
                    </label>
                </td>
            </tr>
            <tr>
                <th><label for="wpa_cron_interval">Kjøringsintervall</label></th>
                <td>
                    <select id="wpa_cron_interval" name="wpa_cron_interval">
                        <option value="hourly" <?php selected( $settings['cron_interval'], 'hourly' ); ?>>Hver time</option>
                        <option value="every_2_hours" <?php selected( $settings['cron_interval'], 'every_2_hours' ); ?>>Hver 2. time</option>
                        <option value="every_6_hours" <?php selected( $settings['cron_interval'], 'every_6_hours' ); ?>>Hver 6. time</option>
                        <option value="every_12_hours" <?php selected( $settings['cron_interval'], 'every_12_hours' ); ?>>Hver 12. time</option>
                        <option value="daily" <?php selected( $settings['cron_interval'], 'daily' ); ?>>Daglig</option>
                    </select>
                </td>
            </tr>
            <tr>
                <th><label for="wpa_max_per_run">Maks artikler per kjøring</label></th>
                <td>
                    <input type="number" id="wpa_max_per_run" name="wpa_max_per_run"
                           value="<?php echo esc_attr( $settings['max_per_run'] ); ?>"
                           min="1" max="20" class="small-text">
                </td>
            </tr>
            <tr>
                <th><label for="wpa_max_per_day">Maks artikler per dag</label></th>
                <td>
                    <input type="number" id="wpa_max_per_day" name="wpa_max_per_day"
                           value="<?php echo esc_attr( $settings['max_per_day'] ); ?>"
                           min="1" max="100" class="small-text">
                </td>
            </tr>
            <tr>
                <th>Arbeidstid</th>
                <td>
                    <label>
                        <input type="checkbox" id="wpa_work_hours_enabled" name="wpa_work_hours_enabled"
                               value="1" <?php checked( $settings['work_hours_enabled'] ?? false ); ?>>
                        Begrens publisering til arbeidstid
                    </label>
                    <div style="margin-top: 8px;">
                        <label for="wpa_work_hours_start">Fra kl.</label>
                        <select id="wpa_work_hours_start" name="wpa_work_hours_start">
                            <?php for ( $h = 0; $h < 24; $h++ ) : ?>
                                <option value="<?php echo $h; ?>" <?php selected( (int) ( $settings['work_hours_start'] ?? 8 ), $h ); ?>>
                                    <?php echo str_pad( $h, 2, '0', STR_PAD_LEFT ) . ':00'; ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                        <label for="wpa_work_hours_end" style="margin-left: 10px;">til kl.</label>
                        <select id="wpa_work_hours_end" name="wpa_work_hours_end">
                            <?php for ( $h = 0; $h < 24; $h++ ) : ?>
                                <option value="<?php echo $h; ?>" <?php selected( (int) ( $settings['work_hours_end'] ?? 22 ), $h ); ?>>
                                    <?php echo str_pad( $h, 2, '0', STR_PAD_LEFT ) . ':00'; ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <p class="description">Autopiloten publiserer kun innenfor dette tidsvinduet (WordPress-tidssone).</p>
                </td>
            </tr>
        </table>
    </div>

    <!-- Keyword Filtering -->
    <div class="wpa-section">
        <h2>Nøkkelordfiltrering</h2>
        <table class="form-table">
            <tr>
                <th><label for="wpa_keyword_include">Inkluder nøkkelord</label></th>
                <td>
                    <input type="text" id="wpa_keyword_include" name="wpa_keyword_include"
                           value="<?php echo esc_attr( $settings['keyword_include'] ); ?>"
                           class="large-text" placeholder="f.eks. teknologi, AI, klima">
                    <p class="description">Kommaseparert. Minst ett ord må finnes i tittel/beskrivelse. La tom for å inkludere alt.</p>
                </td>
            </tr>
            <tr>
                <th><label for="wpa_keyword_exclude">Ekskluder nøkkelord</label></th>
                <td>
                    <input type="text" id="wpa_keyword_exclude" name="wpa_keyword_exclude"
                           value="<?php echo esc_attr( $settings['keyword_exclude'] ); ?>"
                           class="large-text" placeholder="f.eks. sport, underholdning">
                    <p class="description">Kommaseparert. Artikler med disse ordene filtreres bort.</p>
                </td>
            </tr>
        </table>
    </div>

    <!-- Data Management -->
    <div class="wpa-section">
        <h2>Databehandling</h2>
        <table class="form-table">
            <tr>
                <th><label for="wpa_delete_data_on_uninstall">Slett data ved avinstallering</label></th>
                <td>
                    <label>
                        <input type="checkbox" id="wpa_delete_data_on_uninstall" name="wpa_delete_data_on_uninstall"
                               value="1" <?php checked( $settings['delete_data_on_uninstall'] ?? false ); ?>>
                        Slett alle innstillinger, feeds, logg og databasetabeller når pluginen avinstalleres
                    </label>
                    <p class="description">La denne stå av hvis du planlegger å reinstallere eller oppdatere manuelt. Data beholdes da mellom installasjoner.</p>
                </td>
            </tr>
        </table>
    </div>

    <?php submit_button( 'Lagre innstillinger' ); ?>
</form>

<?php include WPA_PLUGIN_DIR . 'admin/partials/footer.php'; ?>
