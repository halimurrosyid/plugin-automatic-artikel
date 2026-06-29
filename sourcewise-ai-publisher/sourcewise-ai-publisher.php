<?php
/*
Plugin Name: SourceWise AI Publisher
Plugin URI: https://ajidmujaddid.staff.telkomuniversity.ac.id/
Description: Create draft posts from approved source URLs or RSS feeds with optional Anthropic-powered editorial assistance.
Version: 1.2.1
Author: Ajid Digital Tools
Author URI: https://ajidmujaddid.staff.telkomuniversity.ac.id/
Requires PHP: 5.6
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Text Domain: sourcewise-ai-publisher
*/

if (!defined('ABSPATH')) {
    exit;
}

define('SCR_VERSION', '1.2.1');
define('SCR_OPTION', 'ajid_scr_options');
define('SCR_LOG_OPTION', 'ajid_scr_logs');
define('SCR_HISTORY_OPTION', 'ajid_scr_history');
define('SCR_QUEUE_OPTION', 'ajid_scr_queue');
define('SCR_IMPORTED_META', '_ajid_scr_source_url');
define('SCR_CONTENT_HASH_META', '_ajid_scr_content_hash');
define('SCR_CRON_HOOK', 'ajid_scr_run_import_event');
define('SCR_QUEUE_CRON_HOOK', 'ajid_scr_process_queue_event');
define('SCR_PREVIEW_TRANSIENT', 'ajid_scr_preview_result');

function ajid_scr_defaults()
{
    return array(
        'anthropic_api_key' => '',
        'anthropic_model' => 'claude-haiku-4-5-20251001',
        'sources' => '',
        'source_rules' => '',
        'keyword_categories' => '',
        'prompt' => 'Create an original editorial draft in natural Indonesian from this approved source material. Keep the facts, improve readability, create an original title, and do not add unsupported claims.',
        'language' => 'Indonesian',
        'writing_style' => 'natural editorial',
        'target_length' => 'medium',
        'add_seo_title' => 1,
        'add_meta_description' => 1,
        'add_headings' => 1,
        'add_faq' => 0,
        'enable_featured_image' => 1,
        'featured_image_source' => 'auto',
        'min_image_width' => 300,
        'min_image_height' => 180,
        'require_featured_image' => 0,
        'cleaner_enabled' => 1,
        'cleaner_blocklist' => 'advertisement, sponsored, related posts, baca juga, share this, subscribe, komentar, iklan',
        'queue_enabled' => 0,
        'items_per_queue_run' => 1,
        'enable_seo_plugin_meta' => 1,
        'enable_auto_tags' => 1,
        'min_rewrite_words' => 120,
        'require_h2' => 0,
        'banned_phrases' => '',
        'safety_mode' => 1,
        'include_source_link' => 0,
        'duplicate_title_check' => 1,
        'duplicate_hash_check' => 1,
        'post_status' => 'draft',
        'post_author' => 1,
        'category_id' => 0,
        'max_items' => 3,
        'cron_enabled' => 0,
        'cron_recurrence' => 'hourly',
    );
}

function ajid_scr_get_options()
{
    $options = get_option(SCR_OPTION);
    if (!is_array($options)) {
        $options = array();
    }

    return wp_parse_args($options, ajid_scr_defaults());
}

function ajid_scr_save_options($input)
{
    $current = ajid_scr_get_options();
    $defaults = ajid_scr_defaults();

    if (!is_array($input)) {
        $input = array();
    }

    $sources_raw = isset($input['sources']) ? trim(wp_unslash($input['sources'])) : '';
    $source_lines = preg_split('/\r\n|\r|\n/', $sources_raw);
    $sources = array();

    foreach ($source_lines as $line) {
        $line = trim($line);
        if ($line !== '') {
            $sources[] = esc_url_raw($line);
        }
    }

    $api_key = isset($input['anthropic_api_key']) ? sanitize_text_field(wp_unslash($input['anthropic_api_key'])) : '';
    $post_status = isset($input['post_status']) ? sanitize_key(wp_unslash($input['post_status'])) : 'draft';
    $cron_recurrence = isset($input['cron_recurrence']) ? sanitize_key(wp_unslash($input['cron_recurrence'])) : 'hourly';
    $target_length = isset($input['target_length']) ? sanitize_key(wp_unslash($input['target_length'])) : 'medium';
    $featured_image_source = isset($input['featured_image_source']) ? sanitize_key(wp_unslash($input['featured_image_source'])) : 'auto';

    $clean = array(
        'anthropic_api_key' => $api_key !== '' ? $api_key : $current['anthropic_api_key'],
        'anthropic_model' => isset($input['anthropic_model']) ? sanitize_text_field(wp_unslash($input['anthropic_model'])) : $defaults['anthropic_model'],
        'sources' => implode("\n", $sources),
        'source_rules' => isset($input['source_rules']) ? ajid_scr_sanitize_multiline(wp_unslash($input['source_rules'])) : '',
        'keyword_categories' => isset($input['keyword_categories']) ? ajid_scr_sanitize_multiline(wp_unslash($input['keyword_categories'])) : '',
        'prompt' => isset($input['prompt']) ? sanitize_textarea_field(wp_unslash($input['prompt'])) : $defaults['prompt'],
        'language' => isset($input['language']) ? sanitize_text_field(wp_unslash($input['language'])) : $defaults['language'],
        'writing_style' => isset($input['writing_style']) ? sanitize_text_field(wp_unslash($input['writing_style'])) : $defaults['writing_style'],
        'target_length' => in_array($target_length, array('short', 'medium', 'long'), true) ? $target_length : 'medium',
        'add_seo_title' => empty($input['add_seo_title']) ? 0 : 1,
        'add_meta_description' => empty($input['add_meta_description']) ? 0 : 1,
        'add_headings' => empty($input['add_headings']) ? 0 : 1,
        'add_faq' => empty($input['add_faq']) ? 0 : 1,
        'enable_featured_image' => empty($input['enable_featured_image']) ? 0 : 1,
        'featured_image_source' => in_array($featured_image_source, array('auto', 'og', 'first_image', 'rss'), true) ? $featured_image_source : 'auto',
        'min_image_width' => max(0, absint(isset($input['min_image_width']) ? $input['min_image_width'] : 300)),
        'min_image_height' => max(0, absint(isset($input['min_image_height']) ? $input['min_image_height'] : 180)),
        'require_featured_image' => empty($input['require_featured_image']) ? 0 : 1,
        'cleaner_enabled' => empty($input['cleaner_enabled']) ? 0 : 1,
        'cleaner_blocklist' => isset($input['cleaner_blocklist']) ? sanitize_text_field(wp_unslash($input['cleaner_blocklist'])) : $defaults['cleaner_blocklist'],
        'queue_enabled' => empty($input['queue_enabled']) ? 0 : 1,
        'items_per_queue_run' => max(1, min(5, absint(isset($input['items_per_queue_run']) ? $input['items_per_queue_run'] : 1))),
        'enable_seo_plugin_meta' => empty($input['enable_seo_plugin_meta']) ? 0 : 1,
        'enable_auto_tags' => empty($input['enable_auto_tags']) ? 0 : 1,
        'min_rewrite_words' => max(0, absint(isset($input['min_rewrite_words']) ? $input['min_rewrite_words'] : 120)),
        'require_h2' => empty($input['require_h2']) ? 0 : 1,
        'banned_phrases' => isset($input['banned_phrases']) ? sanitize_text_field(wp_unslash($input['banned_phrases'])) : '',
        'safety_mode' => empty($input['safety_mode']) ? 0 : 1,
        'include_source_link' => empty($input['include_source_link']) ? 0 : 1,
        'duplicate_title_check' => empty($input['duplicate_title_check']) ? 0 : 1,
        'duplicate_hash_check' => empty($input['duplicate_hash_check']) ? 0 : 1,
        'post_status' => in_array($post_status, array('draft', 'pending', 'publish'), true) ? $post_status : 'draft',
        'post_author' => absint(isset($input['post_author']) ? $input['post_author'] : 1),
        'category_id' => absint(isset($input['category_id']) ? $input['category_id'] : 0),
        'max_items' => max(1, min(20, absint(isset($input['max_items']) ? $input['max_items'] : 3))),
        'cron_enabled' => empty($input['cron_enabled']) ? 0 : 1,
        'cron_recurrence' => in_array($cron_recurrence, array('hourly', 'twicedaily', 'daily'), true) ? $cron_recurrence : 'hourly',
    );

    update_option(SCR_OPTION, $clean, false);
    ajid_scr_refresh_cron($clean);
}

function ajid_scr_sanitize_multiline($text)
{
    $lines = preg_split('/\r\n|\r|\n/', trim($text));
    $clean = array();

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line !== '') {
            $clean[] = sanitize_text_field($line);
        }
    }

    return implode("\n", $clean);
}

function ajid_scr_refresh_cron($options)
{
    wp_clear_scheduled_hook(SCR_CRON_HOOK);
    wp_clear_scheduled_hook(SCR_QUEUE_CRON_HOOK);

    if (!empty($options['cron_enabled'])) {
        wp_schedule_event(time() + 300, $options['cron_recurrence'], SCR_CRON_HOOK);
    }

    if (!empty($options['queue_enabled'])) {
        wp_schedule_event(time() + 180, 'hourly', SCR_QUEUE_CRON_HOOK);
    }
}

function ajid_scr_deactivate()
{
    wp_clear_scheduled_hook(SCR_CRON_HOOK);
    wp_clear_scheduled_hook(SCR_QUEUE_CRON_HOOK);
}
register_deactivation_hook(__FILE__, 'ajid_scr_deactivate');

function ajid_scr_log($message, $context)
{
    $logs = get_option(SCR_LOG_OPTION);
    if (!is_array($logs)) {
        $logs = array();
    }

    array_unshift($logs, array(
        'time' => current_time('mysql'),
        'message' => sanitize_text_field($message),
        'context' => is_array($context) ? array_map('sanitize_text_field', $context) : array(),
    ));

    update_option(SCR_LOG_OPTION, array_slice($logs, 0, 30), false);
}

function ajid_scr_get_logs()
{
    $logs = get_option(SCR_LOG_OPTION);
    return is_array($logs) ? $logs : array();
}

function ajid_scr_add_history($entry)
{
    $history = get_option(SCR_HISTORY_OPTION);
    if (!is_array($history)) {
        $history = array();
    }

    $entry = wp_parse_args($entry, array(
        'time' => current_time('mysql'),
        'url' => '',
        'title' => '',
        'status' => '',
        'post_id' => '',
        'error' => '',
    ));

    $entry['url'] = esc_url_raw($entry['url']);
    $entry['title'] = sanitize_text_field($entry['title']);
    $entry['status'] = sanitize_text_field($entry['status']);
    $entry['post_id'] = absint($entry['post_id']);
    $entry['error'] = sanitize_text_field($entry['error']);

    array_unshift($history, $entry);
    update_option(SCR_HISTORY_OPTION, array_slice($history, 0, 100), false);
}

function ajid_scr_get_history()
{
    $history = get_option(SCR_HISTORY_OPTION);
    return is_array($history) ? $history : array();
}

function ajid_scr_admin_menu()
{
    add_menu_page(
        'SourceWise AI Publisher',
        'SourceWise',
        'manage_options',
        'sourcewise-ai-publisher',
        'ajid_scr_render_admin',
        'dashicons-edit-page',
        58
    );
}
add_action('admin_menu', 'ajid_scr_admin_menu');

function ajid_scr_admin_assets($hook)
{
    if ($hook !== 'toplevel_page_sourcewise-ai-publisher') {
        return;
    }

    wp_add_inline_style('wp-admin', '
        .scr-layout{display:grid;grid-template-columns:minmax(0,1fr)340px;gap:18px;max-width:1280px}
        .scr-panel{background:#fff;border:1px solid #dcdcde;border-radius:6px;margin:0 0 18px;padding:18px}
        .scr-panel h2{margin:0 0 12px}.scr-panel textarea,.scr-panel input[type=text],.scr-panel input[type=password]{max-width:760px}
        .scr-log{margin:0}.scr-log li{border-bottom:1px solid #f0f0f1;margin:0;padding:10px 0}.scr-log strong,.scr-log span,.scr-log small{display:block}.scr-log small{color:#8c1d18;margin-top:5px}
        .scr-result{border-left:4px solid #2271b1;margin-top:16px;padding:10px 16px;background:#f6f7f7}
        @media(max-width:960px){.scr-layout{grid-template-columns:1fr}}
    ');
}
add_action('admin_enqueue_scripts', 'ajid_scr_admin_assets');

function ajid_scr_check_admin($nonce_action)
{
    if (!current_user_can('manage_options')) {
        wp_die('Permission denied.');
    }

    check_admin_referer($nonce_action);
}

function ajid_scr_save_settings_action()
{
    ajid_scr_check_admin('ajid_scr_save_settings');
    ajid_scr_save_options(isset($_POST['scr']) ? $_POST['scr'] : array());
    wp_safe_redirect(add_query_arg('ajid_scr_notice', 'saved', wp_get_referer()));
    exit;
}
add_action('admin_post_ajid_scr_save_settings', 'ajid_scr_save_settings_action');

function ajid_scr_run_import_action()
{
    ajid_scr_check_admin('ajid_scr_run_import');
    $result = ajid_scr_run_import();
    $notice = empty($result['errors']) ? 'imported' : 'imported_with_errors';
    wp_safe_redirect(add_query_arg('ajid_scr_notice', $notice, wp_get_referer()));
    exit;
}
add_action('admin_post_ajid_scr_run_import', 'ajid_scr_run_import_action');

function ajid_scr_test_rewrite_action()
{
    ajid_scr_check_admin('ajid_scr_test_rewrite');
    $options = ajid_scr_get_options();
    $title = sanitize_text_field(wp_unslash(isset($_POST['test_title']) ? $_POST['test_title'] : 'Test Article'));
    $content = sanitize_textarea_field(wp_unslash(isset($_POST['test_content']) ? $_POST['test_content'] : ''));

    if ($content === '') {
        ajid_scr_log('Rewrite test failed.', array('error' => 'Test content is empty.'));
    } else {
        $result = ajid_scr_rewrite_with_anthropic($title, $content, $options);
        if (is_wp_error($result)) {
            ajid_scr_log('Rewrite test failed.', array('error' => $result->get_error_message()));
        } else {
            set_transient('ajid_scr_test_result', $result, 10 * MINUTE_IN_SECONDS);
            ajid_scr_log('Rewrite test succeeded.', array());
        }
    }

    wp_safe_redirect(add_query_arg('ajid_scr_notice', 'tested', wp_get_referer()));
    exit;
}
add_action('admin_post_ajid_scr_test_rewrite', 'ajid_scr_test_rewrite_action');

function ajid_scr_test_api_action()
{
    ajid_scr_check_admin('ajid_scr_test_api');
    $options = ajid_scr_get_options();
    $result = ajid_scr_test_anthropic_connection($options);

    if (is_wp_error($result)) {
        ajid_scr_log('API test failed.', array('error' => $result->get_error_message(), 'model' => $options['anthropic_model']));
        $notice = 'api_failed';
    } else {
        ajid_scr_log('API test succeeded.', array('model' => $options['anthropic_model']));
        $notice = 'api_ok';
    }

    wp_safe_redirect(add_query_arg('ajid_scr_notice', $notice, wp_get_referer()));
    exit;
}
add_action('admin_post_ajid_scr_test_api', 'ajid_scr_test_api_action');

function ajid_scr_preview_action()
{
    ajid_scr_check_admin('ajid_scr_preview');
    $result = ajid_scr_generate_preview();
    $notice = is_wp_error($result) ? 'preview_failed' : 'preview_ready';
    wp_safe_redirect(add_query_arg('ajid_scr_notice', $notice, wp_get_referer()));
    exit;
}
add_action('admin_post_ajid_scr_preview', 'ajid_scr_preview_action');

function ajid_scr_save_preview_action()
{
    ajid_scr_check_admin('ajid_scr_save_preview');
    $preview = get_transient(SCR_PREVIEW_TRANSIENT);

    if (!is_array($preview)) {
        ajid_scr_log('Preview save failed.', array('error' => 'Preview expired or missing.'));
        wp_safe_redirect(add_query_arg('ajid_scr_notice', 'preview_missing', wp_get_referer()));
        exit;
    }

    $options = ajid_scr_get_options();
    $result = ajid_scr_insert_rewritten_post($preview['item'], $preview['article'], $preview['rewritten'], $options);

    if (is_wp_error($result)) {
        ajid_scr_log('Preview save failed.', array('url' => $preview['item']['url'], 'error' => $result->get_error_message()));
        $notice = 'preview_save_failed';
    } else {
        delete_transient(SCR_PREVIEW_TRANSIENT);
        ajid_scr_log('Preview saved as post.', array('url' => $preview['item']['url'], 'post_id' => $result));
        $notice = 'preview_saved';
    }

    wp_safe_redirect(add_query_arg('ajid_scr_notice', $notice, wp_get_referer()));
    exit;
}
add_action('admin_post_ajid_scr_save_preview', 'ajid_scr_save_preview_action');

function ajid_scr_clear_preview_action()
{
    ajid_scr_check_admin('ajid_scr_clear_preview');
    delete_transient(SCR_PREVIEW_TRANSIENT);
    wp_safe_redirect(add_query_arg('ajid_scr_notice', 'preview_cleared', wp_get_referer()));
    exit;
}
add_action('admin_post_ajid_scr_clear_preview', 'ajid_scr_clear_preview_action');

function ajid_scr_test_sources_action()
{
    ajid_scr_check_admin('ajid_scr_test_sources');
    $result = ajid_scr_test_sources();
    $notice = empty($result['errors']) ? 'sources_ok' : 'sources_failed';
    wp_safe_redirect(add_query_arg('ajid_scr_notice', $notice, wp_get_referer()));
    exit;
}
add_action('admin_post_ajid_scr_test_sources', 'ajid_scr_test_sources_action');

function ajid_scr_process_queue_action()
{
    ajid_scr_check_admin('ajid_scr_process_queue');
    $result = ajid_scr_process_queue();
    $notice = empty($result['errors']) ? 'queue_processed' : 'queue_errors';
    wp_safe_redirect(add_query_arg('ajid_scr_notice', $notice, wp_get_referer()));
    exit;
}
add_action('admin_post_ajid_scr_process_queue', 'ajid_scr_process_queue_action');

function ajid_scr_render_admin()
{
    if (!current_user_can('manage_options')) {
        wp_die('Permission denied.');
    }

    $options = ajid_scr_get_options();
    $logs = ajid_scr_get_logs();
    $history = ajid_scr_get_history();
    $test_result = get_transient('ajid_scr_test_result');
    $preview = get_transient(SCR_PREVIEW_TRANSIENT);
    $notice = sanitize_key(isset($_GET['ajid_scr_notice']) ? $_GET['ajid_scr_notice'] : '');
    ?>
    <div class="wrap">
        <h1>SourceWise AI Publisher</h1>
        <?php ajid_scr_render_notice($notice); ?>

        <div class="scr-layout">
            <div>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <?php wp_nonce_field('ajid_scr_save_settings'); ?>
                    <input type="hidden" name="action" value="ajid_scr_save_settings">

                    <section class="scr-panel">
                        <h2>Anthropic AI</h2>
                        <table class="form-table" role="presentation">
                            <tr>
                                <th scope="row"><label for="ajid_scr_api_key">API Key</label></th>
                                <td>
                                    <input id="ajid_scr_api_key" type="password" class="regular-text" name="scr[anthropic_api_key]" value="" placeholder="<?php echo $options['anthropic_api_key'] ? esc_attr('API key saved') : ''; ?>" autocomplete="off">
                                    <p class="description">Leave empty to keep the saved key.</p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="ajid_scr_model">Model</label></th>
                                <td>
                                    <input id="ajid_scr_model" type="text" class="regular-text" name="scr[anthropic_model]" value="<?php echo esc_attr($options['anthropic_model']); ?>">
                                    <p class="description">Default model: claude-haiku-4-5-20251001.</p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="ajid_scr_prompt">Editorial Prompt</label></th>
                                <td><textarea id="ajid_scr_prompt" class="large-text" rows="5" name="scr[prompt]"><?php echo esc_textarea($options['prompt']); ?></textarea></td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="ajid_scr_language">Language</label></th>
                                <td><input id="ajid_scr_language" type="text" class="regular-text" name="scr[language]" value="<?php echo esc_attr($options['language']); ?>"></td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="ajid_scr_style">Writing Style</label></th>
                                <td><input id="ajid_scr_style" type="text" class="regular-text" name="scr[writing_style]" value="<?php echo esc_attr($options['writing_style']); ?>"></td>
                            </tr>
                            <tr>
                                <th scope="row">Output Options</th>
                                <td>
                                    <?php ajid_scr_length_select($options['target_length']); ?>
                                    <p>
                                        <label><input type="checkbox" name="scr[add_seo_title]" value="1" <?php checked($options['add_seo_title']); ?>> SEO title</label>
                                        <label><input type="checkbox" name="scr[add_meta_description]" value="1" <?php checked($options['add_meta_description']); ?>> Meta description</label>
                                        <label><input type="checkbox" name="scr[add_headings]" value="1" <?php checked($options['add_headings']); ?>> H2/H3 headings</label>
                                        <label><input type="checkbox" name="scr[add_faq]" value="1" <?php checked($options['add_faq']); ?>> FAQ</label>
                                    </p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">Quality Control</th>
                                <td>
                                    <label>Minimum words <input type="number" min="0" name="scr[min_rewrite_words]" value="<?php echo esc_attr($options['min_rewrite_words']); ?>" style="width:90px"></label>
                                    <label><input type="checkbox" name="scr[require_h2]" value="1" <?php checked($options['require_h2']); ?>> Require H2 heading</label>
                                    <p><input type="text" class="regular-text" name="scr[banned_phrases]" value="<?php echo esc_attr($options['banned_phrases']); ?>" placeholder="banned phrase 1, banned phrase 2"></p>
                                </td>
                            </tr>
                        </table>
                    </section>

                    <section class="scr-panel">
                        <h2>Featured Image and SEO</h2>
                        <table class="form-table" role="presentation">
                            <tr>
                                <th scope="row">Featured Image</th>
                                <td>
                                    <label><input type="checkbox" name="scr[enable_featured_image]" value="1" <?php checked($options['enable_featured_image']); ?>> Enable featured image</label>
                                    <?php ajid_scr_image_source_select($options['featured_image_source']); ?>
                                    <p>
                                        <label>Min width <input type="number" min="0" name="scr[min_image_width]" value="<?php echo esc_attr($options['min_image_width']); ?>" style="width:90px"></label>
                                        <label>Min height <input type="number" min="0" name="scr[min_image_height]" value="<?php echo esc_attr($options['min_image_height']); ?>" style="width:90px"></label>
                                        <label><input type="checkbox" name="scr[require_featured_image]" value="1" <?php checked($options['require_featured_image']); ?>> Require image before publishing</label>
                                    </p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">SEO and Tags</th>
                                <td>
                                    <label><input type="checkbox" name="scr[enable_seo_plugin_meta]" value="1" <?php checked($options['enable_seo_plugin_meta']); ?>> Save Yoast/RankMath meta description</label>
                                    <label><input type="checkbox" name="scr[enable_auto_tags]" value="1" <?php checked($options['enable_auto_tags']); ?>> Generate tags with AI</label>
                                    <label><input type="checkbox" name="scr[safety_mode]" value="1" <?php checked($options['safety_mode']); ?>> Safety mode</label>
                                    <label><input type="checkbox" name="scr[include_source_link]" value="1" <?php checked($options['include_source_link']); ?>> Include source link in public post content</label>
                                </td>
                            </tr>
                        </table>
                    </section>

                    <section class="scr-panel">
                        <h2>Cleaner and Queue</h2>
                        <table class="form-table" role="presentation">
                            <tr>
                                <th scope="row">Cleaner</th>
                                <td>
                                    <label><input type="checkbox" name="scr[cleaner_enabled]" value="1" <?php checked($options['cleaner_enabled']); ?>> Clean article before drafting</label>
                                    <p><input type="text" class="large-text" name="scr[cleaner_blocklist]" value="<?php echo esc_attr($options['cleaner_blocklist']); ?>"></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">Queue</th>
                                <td>
                                    <label><input type="checkbox" name="scr[queue_enabled]" value="1" <?php checked($options['queue_enabled']); ?>> Process imports through queue</label>
                                    <label>Items per queue run <input type="number" min="1" max="5" name="scr[items_per_queue_run]" value="<?php echo esc_attr($options['items_per_queue_run']); ?>" style="width:80px"></label>
                                </td>
                            </tr>
                        </table>
                    </section>

                    <section class="scr-panel">
                        <h2>Sources and Publishing</h2>
                        <table class="form-table" role="presentation">
                            <tr>
                                <th scope="row"><label for="ajid_scr_sources">Source URLs/RSS</label></th>
                                <td>
                                    <textarea id="ajid_scr_sources" class="large-text code" rows="7" name="scr[sources]"><?php echo esc_textarea($options['sources']); ?></textarea>
                                    <p class="description">One URL per line. Use sources you own or have permission to republish.</p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="ajid_scr_source_rules">Managed Sources</label></th>
                                <td>
                                    <textarea id="ajid_scr_source_rules" class="large-text code" rows="6" name="scr[source_rules]"><?php echo esc_textarea($options['source_rules']); ?></textarea>
                                    <p class="description">Format: URL | Label | Category ID | Status | keywords | custom prompt. Example: https://example.com/feed | Campus | 3 | draft | kampus, mahasiswa | Create a formal campus news draft.</p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="ajid_scr_keyword_categories">Keyword Category Mapping</label></th>
                                <td>
                                    <textarea id="ajid_scr_keyword_categories" class="large-text code" rows="4" name="scr[keyword_categories]"><?php echo esc_textarea($options['keyword_categories']); ?></textarea>
                                    <p class="description">Format: keyword | category_id. One rule per line.</p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">Post Status</th>
                                <td><?php ajid_scr_status_select($options['post_status']); ?></td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="ajid_scr_author">Author ID</label></th>
                                <td><input id="ajid_scr_author" type="number" min="1" name="scr[post_author]" value="<?php echo esc_attr($options['post_author']); ?>"></td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="ajid_scr_category">Category ID</label></th>
                                <td><input id="ajid_scr_category" type="number" min="0" name="scr[category_id]" value="<?php echo esc_attr($options['category_id']); ?>"></td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="ajid_scr_max_items">Max Items per Run</label></th>
                                <td><input id="ajid_scr_max_items" type="number" min="1" max="20" name="scr[max_items]" value="<?php echo esc_attr($options['max_items']); ?>"></td>
                            </tr>
                            <tr>
                                <th scope="row">Cron Import</th>
                                <td>
                                    <label><input type="checkbox" name="scr[cron_enabled]" value="1" <?php checked($options['cron_enabled']); ?>> Enable automatic import</label>
                                    <?php ajid_scr_cron_select($options['cron_recurrence']); ?>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">Duplicate Protection</th>
                                <td>
                                    <label><input type="checkbox" name="scr[duplicate_title_check]" value="1" <?php checked($options['duplicate_title_check']); ?>> Similar title check</label>
                                    <label><input type="checkbox" name="scr[duplicate_hash_check]" value="1" <?php checked($options['duplicate_hash_check']); ?>> Content hash check</label>
                                </td>
                            </tr>
                        </table>
                    </section>

                    <?php submit_button('Save Settings'); ?>
                </form>

                <section class="scr-panel">
                    <h2>Manual Run</h2>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <?php wp_nonce_field('ajid_scr_run_import'); ?>
                        <input type="hidden" name="action" value="ajid_scr_run_import">
                        <?php submit_button('Run Import Now', 'primary', 'submit', false); ?>
                    </form>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin-top:10px">
                        <?php wp_nonce_field('ajid_scr_preview'); ?>
                        <input type="hidden" name="action" value="ajid_scr_preview">
                        <?php submit_button('Preview Next Item', 'secondary', 'submit', false); ?>
                    </form>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin-top:10px">
                        <?php wp_nonce_field('ajid_scr_test_sources'); ?>
                        <input type="hidden" name="action" value="ajid_scr_test_sources">
                        <?php submit_button('Test Sources', 'secondary', 'submit', false); ?>
                    </form>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin-top:10px">
                        <?php wp_nonce_field('ajid_scr_process_queue'); ?>
                        <input type="hidden" name="action" value="ajid_scr_process_queue">
                        <?php submit_button('Process Queue Now', 'secondary', 'submit', false); ?>
                    </form>
                </section>

                <?php if (!empty($preview)) : ?>
                    <section class="scr-panel">
                        <h2>Preview</h2>
                        <p><strong>Source:</strong> <a href="<?php echo esc_url($preview['item']['url']); ?>" target="_blank" rel="noopener"><?php echo esc_html($preview['item']['url']); ?></a></p>
                        <p><strong>Original title:</strong> <?php echo esc_html($preview['article']['title']); ?></p>
                        <?php $preview_image = ajid_scr_select_featured_image_url($preview['article'], $preview['item'], $options); ?>
                        <?php if ($preview_image) : ?>
                            <p><strong>Featured image candidate:</strong> <a href="<?php echo esc_url($preview_image); ?>" target="_blank" rel="noopener"><?php echo esc_html($preview_image); ?></a></p>
                        <?php endif; ?>
                        <div class="scr-result">
                            <h3><?php echo esc_html($preview['rewritten']['title']); ?></h3>
                            <?php echo wp_kses_post($preview['rewritten']['content']); ?>
                            <?php if (!empty($preview['rewritten']['meta_description'])) : ?>
                                <p><strong>Meta description:</strong> <?php echo esc_html($preview['rewritten']['meta_description']); ?></p>
                            <?php endif; ?>
                            <?php if (!empty($preview['rewritten']['tags'])) : ?>
                                <p><strong>Tags:</strong> <?php echo esc_html(implode(', ', $preview['rewritten']['tags'])); ?></p>
                            <?php endif; ?>
                        </div>
                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline-block;margin-right:8px">
                            <?php wp_nonce_field('ajid_scr_save_preview'); ?>
                            <input type="hidden" name="action" value="ajid_scr_save_preview">
                            <?php submit_button('Save Preview as Post', 'primary', 'submit', false); ?>
                        </form>
                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline-block">
                            <?php wp_nonce_field('ajid_scr_clear_preview'); ?>
                            <input type="hidden" name="action" value="ajid_scr_clear_preview">
                            <?php submit_button('Clear Preview', 'secondary', 'submit', false); ?>
                        </form>
                    </section>
                <?php endif; ?>

                <section class="scr-panel">
                    <h2>Test Editorial Draft</h2>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin-bottom:10px">
                        <?php wp_nonce_field('ajid_scr_test_api'); ?>
                        <input type="hidden" name="action" value="ajid_scr_test_api">
                        <?php submit_button('Test API Connection', 'secondary', 'submit', false); ?>
                    </form>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <?php wp_nonce_field('ajid_scr_test_rewrite'); ?>
                        <input type="hidden" name="action" value="ajid_scr_test_rewrite">
                        <input type="text" class="large-text" name="test_title" placeholder="Original title">
                        <textarea class="large-text" rows="7" name="test_content" placeholder="Paste article text here"></textarea>
                        <?php submit_button('Draft Test', 'secondary', 'submit', false); ?>
                    </form>
                    <?php if (!empty($test_result)) : ?>
                        <div class="scr-result">
                            <h3><?php echo esc_html($test_result['title']); ?></h3>
                            <?php echo wp_kses_post($test_result['content']); ?>
                        </div>
                    <?php endif; ?>
                </section>
            </div>

            <aside>
                <section class="scr-panel">
                    <h2>History</h2>
                    <?php if (!$history) : ?>
                        <p>No history yet.</p>
                    <?php else : ?>
                        <table class="widefat striped">
                            <thead><tr><th>Time</th><th>Status</th><th>Title</th><th>Post</th></tr></thead>
                            <tbody>
                                <?php foreach (array_slice($history, 0, 20) as $item) : ?>
                                    <tr>
                                        <td><?php echo esc_html($item['time']); ?></td>
                                        <td><?php echo esc_html($item['status']); ?></td>
                                        <td>
                                            <?php echo esc_html($item['title']); ?><br>
                                            <small><?php echo esc_html($item['url']); ?></small>
                                            <?php if (!empty($item['error'])) : ?><br><small><?php echo esc_html($item['error']); ?></small><?php endif; ?>
                                        </td>
                                        <td><?php echo !empty($item['post_id']) ? esc_html($item['post_id']) : '-'; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </section>
                <section class="scr-panel">
                    <h2>Logs</h2>
                    <?php if (!$logs) : ?>
                        <p>No logs yet.</p>
                    <?php else : ?>
                        <ul class="scr-log">
                            <?php foreach ($logs as $log) : ?>
                                <li>
                                    <strong><?php echo esc_html($log['time']); ?></strong>
                                    <span><?php echo esc_html($log['message']); ?></span>
                                    <?php if (!empty($log['context']) && is_array($log['context'])) : ?>
                                        <?php foreach ($log['context'] as $key => $value) : ?>
                                            <?php if ($value !== '') : ?>
                                                <small><?php echo esc_html($key); ?>: <?php echo esc_html($value); ?></small>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </section>
            </aside>
        </div>
    </div>
    <?php
}

function ajid_scr_render_notice($notice)
{
    $messages = array(
        'saved' => 'Settings saved.',
        'imported' => 'Import completed.',
        'imported_with_errors' => 'Import completed with errors. Check logs.',
        'tested' => 'Draft test completed. Check result or logs.',
        'api_ok' => 'API connection succeeded.',
        'api_failed' => 'API connection failed. Check logs.',
        'preview_ready' => 'Preview generated.',
        'preview_failed' => 'Preview failed. Check logs.',
        'preview_missing' => 'Preview is missing or expired.',
        'preview_saved' => 'Preview saved as post.',
        'preview_save_failed' => 'Preview could not be saved. Check logs.',
        'preview_cleared' => 'Preview cleared.',
        'sources_ok' => 'Source test completed successfully.',
        'sources_failed' => 'Source test completed with errors. Check logs.',
        'queue_processed' => 'Queue processed.',
        'queue_errors' => 'Queue processed with errors. Check logs.',
    );

    if (isset($messages[$notice])) {
        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($messages[$notice]) . '</p></div>';
    }
}

function ajid_scr_length_select($value)
{
    ?>
    <select name="scr[target_length]">
        <option value="short" <?php selected($value, 'short'); ?>>Short</option>
        <option value="medium" <?php selected($value, 'medium'); ?>>Medium</option>
        <option value="long" <?php selected($value, 'long'); ?>>Long</option>
    </select>
    <?php
}

function ajid_scr_image_source_select($value)
{
    ?>
    <select name="scr[featured_image_source]">
        <option value="auto" <?php selected($value, 'auto'); ?>>Auto</option>
        <option value="og" <?php selected($value, 'og'); ?>>OG image</option>
        <option value="first_image" <?php selected($value, 'first_image'); ?>>First article image</option>
        <option value="rss" <?php selected($value, 'rss'); ?>>RSS media image</option>
    </select>
    <?php
}

function ajid_scr_status_select($value)
{
    ?>
    <select name="scr[post_status]">
        <option value="draft" <?php selected($value, 'draft'); ?>>Draft</option>
        <option value="pending" <?php selected($value, 'pending'); ?>>Pending Review</option>
        <option value="publish" <?php selected($value, 'publish'); ?>>Publish</option>
    </select>
    <?php
}

function ajid_scr_cron_select($value)
{
    ?>
    <select name="scr[cron_recurrence]">
        <option value="hourly" <?php selected($value, 'hourly'); ?>>Hourly</option>
        <option value="twicedaily" <?php selected($value, 'twicedaily'); ?>>Twice Daily</option>
        <option value="daily" <?php selected($value, 'daily'); ?>>Daily</option>
    </select>
    <?php
}

function ajid_scr_run_import()
{
    $options = ajid_scr_get_options();
    $sources = ajid_scr_get_managed_sources($options);
    $created = 0;
    $queued = 0;
    $errors = array();

    foreach ($sources as $source_rule) {
        if (($created + $queued) >= $options['max_items']) {
            break;
        }

        $items = ajid_scr_source_to_items($source_rule['url']);
        if (is_wp_error($items)) {
            $errors[] = $source_rule['url'] . ': ' . $items->get_error_message();
            ajid_scr_add_history(array('url' => $source_rule['url'], 'title' => $source_rule['label'], 'status' => 'source_error', 'error' => $items->get_error_message()));
            continue;
        }

        foreach ($items as $item) {
            if (($created + $queued) >= $options['max_items']) {
                break;
            }

            $item['source_rule'] = $source_rule;
            $article = ajid_scr_fetch_article($item['url']);
            if (is_wp_error($article)) {
                $errors[] = $item['url'] . ': ' . $article->get_error_message();
                ajid_scr_add_history(array('url' => $item['url'], 'title' => $item['title'], 'status' => 'fetch_error', 'error' => $article->get_error_message()));
                continue;
            }

            $duplicate = ajid_scr_find_duplicate($item['url'], $article['title'] !== '' ? $article['title'] : $item['title'], $article['hash'], $options);
            if ($duplicate) {
                ajid_scr_add_history(array('url' => $item['url'], 'title' => $item['title'], 'status' => 'duplicate', 'error' => $duplicate));
                continue;
            }

            if (!empty($options['queue_enabled'])) {
                ajid_scr_enqueue_item($item, $article);
                ajid_scr_add_history(array('url' => $item['url'], 'title' => $item['title'], 'status' => 'queued'));
                $queued++;
                continue;
            }

            $result = ajid_scr_import_item($item, $options, $article);
            if (is_wp_error($result)) {
                $errors[] = $item['url'] . ': ' . $result->get_error_message();
                ajid_scr_add_history(array('url' => $item['url'], 'title' => $item['title'], 'status' => 'import_error', 'error' => $result->get_error_message()));
                continue;
            }

            $created++;
        }
    }

    ajid_scr_log('Import finished. Created ' . $created . ' post(s), queued ' . $queued . ' item(s).', array('created' => $created, 'queued' => $queued, 'errors' => implode(' | ', $errors)));
    return array('created' => $created, 'queued' => $queued, 'errors' => $errors);
}
add_action(SCR_CRON_HOOK, 'ajid_scr_run_import');
add_action(SCR_QUEUE_CRON_HOOK, 'ajid_scr_process_queue');

function ajid_scr_import_item($item, $options, $article = null)
{
    if ($article === null) {
        $article = ajid_scr_fetch_article($item['url']);
        if (is_wp_error($article)) {
            return $article;
        }
    }

    $title = $article['title'] !== '' ? $article['title'] : $item['title'];
    $rewrite_options = $options;
    if (!empty($item['source_rule']['prompt'])) {
        $rewrite_options['prompt'] = $item['source_rule']['prompt'];
    }

    $rewritten = ajid_scr_rewrite_with_anthropic($title, $article['content'], $rewrite_options);
    if (is_wp_error($rewritten)) {
        return $rewritten;
    }

    return ajid_scr_insert_rewritten_post($item, $article, $rewritten, $options);
}

function ajid_scr_generate_preview()
{
    $options = ajid_scr_get_options();
    $sources = ajid_scr_get_managed_sources($options);

    foreach ($sources as $source_rule) {
        $items = ajid_scr_source_to_items($source_rule['url']);
        if (is_wp_error($items)) {
            ajid_scr_log('Preview source failed.', array('url' => $source_rule['url'], 'error' => $items->get_error_message()));
            continue;
        }

        foreach ($items as $item) {
            $item['source_rule'] = $source_rule;
            $article = ajid_scr_fetch_article($item['url']);
            if (is_wp_error($article)) {
                ajid_scr_log('Preview fetch failed.', array('url' => $item['url'], 'error' => $article->get_error_message()));
                continue;
            }

            $title = $article['title'] !== '' ? $article['title'] : $item['title'];
            $duplicate = ajid_scr_find_duplicate($item['url'], $title, $article['hash'], $options);
            if ($duplicate) {
                ajid_scr_log('Preview skipped duplicate.', array('url' => $item['url'], 'reason' => $duplicate));
                continue;
            }

            $rewrite_options = $options;
            if (!empty($item['source_rule']['prompt'])) {
                $rewrite_options['prompt'] = $item['source_rule']['prompt'];
            }
            $rewritten = ajid_scr_rewrite_with_anthropic($title, $article['content'], $rewrite_options);
            if (is_wp_error($rewritten)) {
                ajid_scr_log('Preview rewrite failed.', array('url' => $item['url'], 'error' => $rewritten->get_error_message()));
                return $rewritten;
            }

            $preview = array('item' => $item, 'article' => $article, 'rewritten' => $rewritten);
            set_transient(SCR_PREVIEW_TRANSIENT, $preview, 30 * MINUTE_IN_SECONDS);
            ajid_scr_log('Preview generated.', array('url' => $item['url'], 'title' => $rewritten['title']));
            return $preview;
        }
    }

    $error = new WP_Error('ajid_scr_no_preview_item', 'No eligible item found for preview.');
    ajid_scr_log('Preview failed.', array('error' => $error->get_error_message()));
    return $error;
}

function ajid_scr_test_anthropic_connection($options)
{
    if (empty($options['anthropic_api_key'])) {
        return new WP_Error('ajid_scr_no_api_key', 'Anthropic API key is empty.');
    }

    $response = wp_remote_post('https://api.anthropic.com/v1/messages', array(
        'timeout' => 30,
        'headers' => array(
            'content-type' => 'application/json',
            'x-api-key' => $options['anthropic_api_key'],
            'anthropic-version' => '2023-06-01',
        ),
        'body' => wp_json_encode(array(
            'model' => $options['anthropic_model'],
            'max_tokens' => 32,
            'messages' => array(
                array('role' => 'user', 'content' => 'Reply with OK.'),
            ),
        )),
    ));

    if (is_wp_error($response)) {
        return $response;
    }

    $code = wp_remote_retrieve_response_code($response);
    if ($code < 200 || $code >= 300) {
        $body = json_decode(wp_remote_retrieve_body($response), true);
        $message = isset($body['error']['message']) ? $body['error']['message'] : 'HTTP ' . $code;
        return new WP_Error('ajid_scr_api_test_failed', $message);
    }

    return true;
}

function ajid_scr_test_sources()
{
    $options = ajid_scr_get_options();
    $sources = ajid_scr_get_managed_sources($options);
    $checked = 0;
    $errors = array();

    foreach ($sources as $source_rule) {
        $items = ajid_scr_source_to_items($source_rule['url']);
        if (is_wp_error($items)) {
            $errors[] = $source_rule['url'] . ': ' . $items->get_error_message();
            ajid_scr_log('Source test failed.', array('url' => $source_rule['url'], 'error' => $items->get_error_message()));
            continue;
        }

        $first = isset($items[0]) ? $items[0] : array('url' => $source_rule['url'], 'title' => $source_rule['label']);
        $article = ajid_scr_fetch_article($first['url']);
        if (is_wp_error($article)) {
            $errors[] = $first['url'] . ': ' . $article->get_error_message();
            ajid_scr_log('Source item test failed.', array('url' => $first['url'], 'error' => $article->get_error_message()));
            continue;
        }

        $image = ajid_scr_select_featured_image_url($article, $first, $options);
        ajid_scr_log('Source test succeeded.', array(
            'source' => $source_rule['url'],
            'first_item' => $first['url'],
            'words' => str_word_count(wp_strip_all_tags($article['content'])),
            'image' => $image ? 'found' : 'missing',
        ));
        $checked++;
    }

    return array('checked' => $checked, 'errors' => $errors);
}

function ajid_scr_insert_rewritten_post($item, $article, $rewritten, $options)
{
    $quality = ajid_scr_validate_rewrite_quality($rewritten, $options);
    if (is_wp_error($quality)) {
        return $quality;
    }

    $source_rule = isset($item['source_rule']) && is_array($item['source_rule']) ? $item['source_rule'] : array();
    $post_status = !empty($source_rule['status']) ? $source_rule['status'] : $options['post_status'];
    $category_id = ajid_scr_resolve_category($item, $article, $rewritten, $options);
    $source_link = '<p><small>Source: <a href="' . esc_url($item['url']) . '" rel="nofollow noopener" target="_blank">' . esc_html($item['url']) . '</a></small></p>';
    $content = !empty($options['include_source_link']) ? $rewritten['content'] . "\n\n" . $source_link : $rewritten['content'];
    $post_title = !empty($rewritten['seo_title']) ? $rewritten['seo_title'] : $rewritten['title'];

    $postarr = array(
        'post_title' => $post_title,
        'post_content' => $content,
        'post_status' => $post_status,
        'post_author' => $options['post_author'],
        'post_excerpt' => !empty($rewritten['meta_description']) ? $rewritten['meta_description'] : '',
    );

    if (!empty($category_id)) {
        $postarr['post_category'] = array($category_id);
    }

    if (!empty($options['safety_mode'])) {
        $postarr['post_status'] = 'draft';
    }

    $post_id = wp_insert_post(wp_slash($postarr), true);
    if (is_wp_error($post_id)) {
        return $post_id;
    }

    update_post_meta($post_id, SCR_IMPORTED_META, esc_url_raw($item['url']));
    update_post_meta($post_id, SCR_CONTENT_HASH_META, sanitize_text_field($article['hash']));
    if (!empty($rewritten['meta_description'])) {
        update_post_meta($post_id, '_scr_meta_description', sanitize_text_field($rewritten['meta_description']));
        if (!empty($options['enable_seo_plugin_meta'])) {
            update_post_meta($post_id, '_yoast_wpseo_metadesc', sanitize_text_field($rewritten['meta_description']));
            update_post_meta($post_id, 'rank_math_description', sanitize_text_field($rewritten['meta_description']));
        }
    }

    if (!empty($options['enable_auto_tags']) && !empty($rewritten['tags'])) {
        wp_set_post_tags($post_id, $rewritten['tags'], true);
    }

    if (!empty($options['enable_featured_image'])) {
        $attachment_id = ajid_scr_set_featured_image($post_id, $article, $item, $options);
        if (is_wp_error($attachment_id)) {
            ajid_scr_log('Featured image failed.', array('url' => $item['url'], 'error' => $attachment_id->get_error_message()));
            if (!empty($options['require_featured_image'])) {
                wp_update_post(array('ID' => $post_id, 'post_status' => 'draft'));
            }
        } elseif ($attachment_id) {
            ajid_scr_log('Featured image set.', array('post_id' => $post_id, 'attachment_id' => $attachment_id));
        }
    }

    ajid_scr_add_history(array('url' => $item['url'], 'title' => $post_title, 'status' => 'created', 'post_id' => $post_id));
    return $post_id;
}

function ajid_scr_enqueue_item($item, $article)
{
    $queue = get_option(SCR_QUEUE_OPTION);
    if (!is_array($queue)) {
        $queue = array();
    }

    foreach ($queue as $queued) {
        if (!empty($queued['item']['url']) && $queued['item']['url'] === $item['url']) {
            return;
        }
    }

    $queue[] = array(
        'time' => current_time('mysql'),
        'item' => $item,
        'article' => $article,
    );

    update_option(SCR_QUEUE_OPTION, array_slice($queue, -100), false);
}

function ajid_scr_process_queue()
{
    $options = ajid_scr_get_options();
    $queue = get_option(SCR_QUEUE_OPTION);
    if (!is_array($queue)) {
        $queue = array();
    }

    $processed = 0;
    $errors = array();
    $remaining = array();

    foreach ($queue as $entry) {
        if ($processed >= $options['items_per_queue_run']) {
            $remaining[] = $entry;
            continue;
        }

        if (empty($entry['item']) || empty($entry['article'])) {
            $processed++;
            continue;
        }

        $result = ajid_scr_import_item($entry['item'], $options, $entry['article']);
        if (is_wp_error($result)) {
            $errors[] = $entry['item']['url'] . ': ' . $result->get_error_message();
            ajid_scr_add_history(array('url' => $entry['item']['url'], 'title' => $entry['item']['title'], 'status' => 'queue_error', 'error' => $result->get_error_message()));
        }

        $processed++;
    }

    update_option(SCR_QUEUE_OPTION, $remaining, false);
    ajid_scr_log('Queue processed.', array('processed' => $processed, 'remaining' => count($remaining), 'errors' => implode(' | ', $errors)));

    return array('processed' => $processed, 'remaining' => count($remaining), 'errors' => $errors);
}

function ajid_scr_rewrite_with_anthropic($title, $content, $options)
{
    if (empty($options['anthropic_api_key'])) {
        return new WP_Error('ajid_scr_no_api_key', 'Anthropic API key is empty.');
    }

    $requirements = array();
    $requirements[] = 'Language: ' . $options['language'];
    $requirements[] = 'Writing style: ' . $options['writing_style'];
    $requirements[] = 'Target length: ' . $options['target_length'];
    $requirements[] = !empty($options['add_headings']) ? 'Use useful H2/H3 headings in the content.' : 'Do not force headings.';
    $requirements[] = !empty($options['add_faq']) ? 'Add a short FAQ section if it fits the topic.' : 'Do not add FAQ.';
    $requirements[] = !empty($options['add_seo_title']) ? 'Create an SEO-friendly title.' : 'Use a natural title.';
    $requirements[] = !empty($options['add_meta_description']) ? 'Create a concise meta description under 160 characters.' : 'Meta description may be empty.';
    $requirements[] = !empty($options['enable_auto_tags']) ? 'Suggest 3 to 8 relevant WordPress tags.' : 'Tags may be empty.';

    $user_prompt = $options['prompt'] . "\n\nAdditional requirements:\n- " . implode("\n- ", $requirements)
        . "\n\nReturn JSON only with keys title, content, seo_title, meta_description, tags. Tags must be an array of strings."
        . "\n\nOriginal title:\n" . $title
        . "\n\nOriginal article:\n" . wp_strip_all_tags($content);

    $response = wp_remote_post('https://api.anthropic.com/v1/messages', array(
        'timeout' => 90,
        'headers' => array(
            'content-type' => 'application/json',
            'x-api-key' => $options['anthropic_api_key'],
            'anthropic-version' => '2023-06-01',
        ),
        'body' => wp_json_encode(array(
            'model' => $options['anthropic_model'],
            'max_tokens' => 4096,
            'temperature' => 0.7,
            'messages' => array(
                array(
                    'role' => 'user',
                    'content' => $user_prompt,
                ),
            ),
        )),
    ));

    if (is_wp_error($response)) {
        return $response;
    }

    $code = wp_remote_retrieve_response_code($response);
    $body = json_decode(wp_remote_retrieve_body($response), true);
    if ($code < 200 || $code >= 300) {
        $message = isset($body['error']['message']) ? $body['error']['message'] : 'Anthropic request failed.';
        return new WP_Error('ajid_scr_anthropic_error', $message);
    }

    $text = isset($body['content'][0]['text']) ? $body['content'][0]['text'] : '';
    $parsed = ajid_scr_parse_json_response($text);
    if (empty($parsed['title']) || empty($parsed['content'])) {
        return new WP_Error('ajid_scr_bad_ai_response', 'AI response did not contain usable title and content.');
    }

    return array(
        'title' => sanitize_text_field($parsed['title']),
        'seo_title' => !empty($parsed['seo_title']) ? sanitize_text_field($parsed['seo_title']) : sanitize_text_field($parsed['title']),
        'meta_description' => !empty($parsed['meta_description']) ? sanitize_text_field($parsed['meta_description']) : '',
        'tags' => ajid_scr_sanitize_tags(isset($parsed['tags']) ? $parsed['tags'] : array()),
        'content' => wp_kses_post(wpautop($parsed['content'])),
    );
}

function ajid_scr_sanitize_tags($tags)
{
    $clean = array();
    if (is_string($tags)) {
        $tags = explode(',', $tags);
    }

    if (!is_array($tags)) {
        return $clean;
    }

    foreach ($tags as $tag) {
        $tag = sanitize_text_field($tag);
        if ($tag !== '') {
            $clean[] = $tag;
        }
    }

    return array_slice(array_unique($clean), 0, 12);
}

function ajid_scr_validate_rewrite_quality($rewritten, $options)
{
    $plain = wp_strip_all_tags($rewritten['content']);
    if (!empty($options['min_rewrite_words']) && str_word_count($plain) < absint($options['min_rewrite_words'])) {
        return new WP_Error('ajid_scr_quality_word_count', 'Draft is shorter than minimum word count.');
    }

    if (!empty($options['require_h2']) && stripos($rewritten['content'], '<h2') === false) {
        return new WP_Error('ajid_scr_quality_h2', 'Draft does not contain an H2 heading.');
    }

    $banned = array_filter(array_map('trim', explode(',', $options['banned_phrases'])));
    foreach ($banned as $phrase) {
        if ($phrase !== '' && stripos($plain, $phrase) !== false) {
            return new WP_Error('ajid_scr_quality_banned_phrase', 'Draft contains banned phrase: ' . $phrase);
        }
    }

    return true;
}

function ajid_scr_parse_json_response($text)
{
    $text = trim($text);
    $decoded = json_decode($text, true);
    if (is_array($decoded)) {
        return $decoded;
    }

    if (preg_match('/\{.*\}/s', $text, $matches)) {
        $decoded = json_decode($matches[0], true);
        if (is_array($decoded)) {
            return $decoded;
        }
    }

    return array();
}

function ajid_scr_fetch_article($url)
{
    $response = wp_remote_get($url, array('timeout' => 30, 'redirection' => 5));
    if (is_wp_error($response)) {
        return $response;
    }

    $code = wp_remote_retrieve_response_code($response);
    if ($code < 200 || $code >= 300) {
        return new WP_Error('ajid_scr_http_error', 'Article request returned HTTP ' . $code . ' for ' . $url);
    }

    $html = wp_remote_retrieve_body($response);
    if ($html === '') {
        return new WP_Error('ajid_scr_empty_article', 'The article page is empty.');
    }

    $title = '';
    if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $matches)) {
        $title = html_entity_decode(wp_strip_all_tags($matches[1]), ENT_QUOTES, get_bloginfo('charset'));
    }
    if (preg_match('/<meta[^>]+property=["\']og:title["\'][^>]+content=["\']([^"\']+)["\']/is', $html, $matches)) {
        $title = html_entity_decode(wp_strip_all_tags($matches[1]), ENT_QUOTES, get_bloginfo('charset'));
    } elseif (preg_match('/<h1[^>]*>(.*?)<\/h1>/is', $html, $matches)) {
        $title = html_entity_decode(wp_strip_all_tags($matches[1]), ENT_QUOTES, get_bloginfo('charset'));
    }

    $content = ajid_scr_extract_content($html);
    $options = ajid_scr_get_options();
    if (!empty($options['cleaner_enabled'])) {
        $content = ajid_scr_clean_content($content, $options);
    }
    if (str_word_count(wp_strip_all_tags($content)) < 80) {
        return new WP_Error('ajid_scr_short_article', 'Could not extract enough article text.');
    }

    return array(
        'title' => $title,
        'content' => $content,
        'hash' => ajid_scr_content_hash($content),
        'image_url' => ajid_scr_extract_image_url($html, $url, 'auto'),
        'og_image' => ajid_scr_extract_image_url($html, $url, 'og'),
        'first_image' => ajid_scr_extract_image_url($html, $url, 'first_image'),
    );
}

function ajid_scr_extract_content($html)
{
    $html = preg_replace('/<(script|style|noscript|iframe)[^>]*>.*?<\/\1>/is', '', $html);
    $html = preg_replace('/<(nav|header|footer|aside|form)[^>]*>.*?<\/\1>/is', '', $html);

    if (preg_match('/<article[^>]*>(.*?)<\/article>/is', $html, $matches)) {
        return wp_kses_post($matches[1]);
    }

    $best = '';
    $best_score = 0;
    if (preg_match_all('/<(main|section|div)[^>]*(class|id)=["\'][^"\']*(content|post|entry|article|story|main)[^"\']*["\'][^>]*>(.*?)<\/\1>/is', $html, $blocks)) {
        foreach ($blocks[4] as $block) {
            $text = wp_strip_all_tags($block);
            $links = preg_match_all('/<a\s/i', $block, $unused);
            $paragraphs = preg_match_all('/<p[\s>]/i', $block, $unused);
            $score = strlen($text) + ($paragraphs * 120) - ($links * 60);
            if ($score > $best_score) {
                $best_score = $score;
                $best = $block;
            }
        }
    }

    if ($best !== '' && str_word_count(wp_strip_all_tags($best)) >= 80) {
        return wp_kses_post($best);
    }

    preg_match_all('/<p[^>]*>.*?<\/p>/is', $html, $matches);
    $paragraphs = isset($matches[0]) ? $matches[0] : array();
    $clean = array();
    foreach ($paragraphs as $paragraph) {
        if (str_word_count(wp_strip_all_tags($paragraph)) >= 8) {
            $clean[] = $paragraph;
        }
    }

    return wp_kses_post(implode("\n", array_slice($clean, 0, 40)));
}

function ajid_scr_clean_content($content, $options)
{
    $content = preg_replace('/<(script|style|noscript|iframe|form|button)[^>]*>.*?<\/\1>/is', '', $content);
    $blocklist = array_filter(array_map('trim', explode(',', $options['cleaner_blocklist'])));

    if ($blocklist) {
        preg_match_all('/<(p|div|section|aside|ul|ol)[^>]*>.*?<\/\1>/is', $content, $blocks, PREG_SET_ORDER);
        foreach ($blocks as $block) {
            $text = strtolower(wp_strip_all_tags($block[0]));
            foreach ($blocklist as $term) {
                if ($term !== '' && strpos($text, strtolower($term)) !== false) {
                    $content = str_replace($block[0], '', $content);
                    break;
                }
            }
        }
    }

    $content = preg_replace('/\s+/', ' ', $content);
    return wp_kses_post(trim($content));
}

function ajid_scr_extract_image_url($html, $base_url, $mode)
{
    $candidates = array();

    if ($mode === 'auto' || $mode === 'og') {
        if (preg_match('/<meta[^>]+property=["\']og:image["\'][^>]+content=["\']([^"\']+)["\']/is', $html, $matches)) {
            $candidates[] = $matches[1];
        }
        if (preg_match('/<meta[^>]+name=["\']twitter:image["\'][^>]+content=["\']([^"\']+)["\']/is', $html, $matches)) {
            $candidates[] = $matches[1];
        }
    }

    if ($mode === 'auto' || $mode === 'first_image') {
        if (preg_match('/<img[^>]+src=["\']([^"\']+)["\']/is', $html, $matches)) {
            $candidates[] = $matches[1];
        }
    }

    foreach ($candidates as $candidate) {
        $url = ajid_scr_absolute_url(html_entity_decode($candidate, ENT_QUOTES, get_bloginfo('charset')), $base_url);
        if ($url) {
            return esc_url_raw($url);
        }
    }

    return '';
}

function ajid_scr_absolute_url($url, $base)
{
    $url = trim($url);
    if ($url === '' || strpos($url, 'data:') === 0) {
        return '';
    }

    if (strpos($url, '//') === 0) {
        return 'https:' . $url;
    }

    if (preg_match('/^https?:\/\//i', $url)) {
        return $url;
    }

    $parts = wp_parse_url($base);
    if (empty($parts['scheme']) || empty($parts['host'])) {
        return '';
    }

    $root = $parts['scheme'] . '://' . $parts['host'];
    if (strpos($url, '/') === 0) {
        return $root . $url;
    }

    $path = isset($parts['path']) ? dirname($parts['path']) : '';
    return $root . rtrim($path, '/') . '/' . $url;
}

function ajid_scr_feed_image_url($item)
{
    $namespaces = $item->getNamespaces(true);
    if (isset($namespaces['media'])) {
        $media = $item->children($namespaces['media']);
        if (isset($media->content)) {
            foreach ($media->content as $content) {
                $attrs = $content->attributes();
                if (!empty($attrs['url'])) {
                    return esc_url_raw((string) $attrs['url']);
                }
            }
        }
        if (isset($media->thumbnail)) {
            foreach ($media->thumbnail as $thumbnail) {
                $attrs = $thumbnail->attributes();
                if (!empty($attrs['url'])) {
                    return esc_url_raw((string) $attrs['url']);
                }
            }
        }
    }

    if (isset($item->enclosure)) {
        $attrs = $item->enclosure->attributes();
        if (!empty($attrs['url'])) {
            return esc_url_raw((string) $attrs['url']);
        }
    }

    return '';
}

function ajid_scr_set_featured_image($post_id, $article, $item, $options)
{
    $image_url = ajid_scr_select_featured_image_url($article, $item, $options);
    if ($image_url === '') {
        return new WP_Error('ajid_scr_no_image', 'No image candidate found.');
    }

    return ajid_scr_sideload_featured_image($post_id, $image_url, $options);
}

function ajid_scr_select_featured_image_url($article, $item, $options)
{
    if ($options['featured_image_source'] === 'rss' && !empty($item['image_url'])) {
        return $item['image_url'];
    }
    if ($options['featured_image_source'] === 'og' && !empty($article['og_image'])) {
        return $article['og_image'];
    }
    if ($options['featured_image_source'] === 'first_image' && !empty($article['first_image'])) {
        return $article['first_image'];
    }

    if (!empty($article['image_url'])) {
        return $article['image_url'];
    }
    if (!empty($item['image_url'])) {
        return $item['image_url'];
    }

    return '';
}

function ajid_scr_sideload_featured_image($post_id, $image_url, $options)
{
    if (!function_exists('download_url')) {
        require_once ABSPATH . 'wp-admin/includes/file.php';
    }
    if (!function_exists('media_handle_sideload')) {
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';
    }

    $tmp = download_url($image_url, 30);
    if (is_wp_error($tmp)) {
        return $tmp;
    }

    $name = basename(parse_url($image_url, PHP_URL_PATH));
    if ($name === '' || strpos($name, '.') === false) {
        $name = 'featured-image.jpg';
    }

    $file = array(
        'name' => sanitize_file_name($name),
        'tmp_name' => $tmp,
    );

    $attachment_id = media_handle_sideload($file, $post_id);
    if (is_wp_error($attachment_id)) {
        @unlink($tmp);
        return $attachment_id;
    }

    $meta = wp_get_attachment_metadata($attachment_id);
    $width = isset($meta['width']) ? absint($meta['width']) : 0;
    $height = isset($meta['height']) ? absint($meta['height']) : 0;
    if (($options['min_image_width'] && $width && $width < $options['min_image_width']) || ($options['min_image_height'] && $height && $height < $options['min_image_height'])) {
        wp_delete_attachment($attachment_id, true);
        return new WP_Error('ajid_scr_small_image', 'Featured image is smaller than the configured minimum size.');
    }

    set_post_thumbnail($post_id, $attachment_id);
    return $attachment_id;
}

function ajid_scr_source_to_items($source)
{
    $response = wp_remote_get($source, array('timeout' => 30));
    if (is_wp_error($response)) {
        return $response;
    }

    $body = wp_remote_retrieve_body($response);
    if ($body === '') {
        return new WP_Error('ajid_scr_empty_source', 'Source returned empty content.');
    }

    if ((stripos($body, '<rss') !== false || stripos($body, '<feed') !== false) && function_exists('simplexml_load_string')) {
        return ajid_scr_parse_feed($body, $source);
    }

    return array(array('title' => $source, 'url' => $source));
}

function ajid_scr_parse_feed($body, $source)
{
    $xml = simplexml_load_string($body);
    if (!$xml) {
        return new WP_Error('ajid_scr_bad_feed', 'Could not parse RSS/Atom feed.');
    }

    $items = array();
    if (isset($xml->channel->item)) {
        foreach ($xml->channel->item as $item) {
            $items[] = array('title' => sanitize_text_field((string) $item->title), 'url' => esc_url_raw((string) $item->link), 'image_url' => ajid_scr_feed_image_url($item));
        }
    } elseif (isset($xml->entry)) {
        foreach ($xml->entry as $entry) {
            $link = '';
            foreach ($entry->link as $entry_link) {
                $attrs = $entry_link->attributes();
                $link = isset($attrs['href']) ? (string) $attrs['href'] : (string) $entry_link;
                if ($link !== '') {
                    break;
                }
            }
            $items[] = array('title' => sanitize_text_field((string) $entry->title), 'url' => esc_url_raw($link), 'image_url' => ajid_scr_feed_image_url($entry));
        }
    }

    $clean = array();
    foreach ($items as $item) {
        if (!empty($item['url'])) {
            $clean[] = $item;
        }
    }

    if (!$clean) {
        return new WP_Error('ajid_scr_no_feed_items', 'No URLs found in feed: ' . $source);
    }

    return $clean;
}

function ajid_scr_get_sources($sources_raw)
{
    $lines = preg_split('/\r\n|\r|\n/', $sources_raw);
    $sources = array();
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line !== '') {
            $sources[] = $line;
        }
    }

    return $sources;
}

function ajid_scr_get_managed_sources($options)
{
    $rules = array();
    $lines = preg_split('/\r\n|\r|\n/', $options['source_rules']);

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '') {
            continue;
        }

        $parts = array_map('trim', explode('|', $line));
        $url = isset($parts[0]) ? esc_url_raw($parts[0]) : '';
        if ($url === '') {
            continue;
        }

        $status = isset($parts[3]) ? sanitize_key($parts[3]) : '';
        if (!in_array($status, array('draft', 'pending', 'publish'), true)) {
            $status = '';
        }

        $rules[] = array(
            'url' => $url,
            'label' => isset($parts[1]) ? sanitize_text_field($parts[1]) : $url,
            'category_id' => isset($parts[2]) ? absint($parts[2]) : 0,
            'status' => $status,
            'keywords' => isset($parts[4]) ? sanitize_text_field($parts[4]) : '',
            'prompt' => isset($parts[5]) ? sanitize_textarea_field($parts[5]) : '',
        );
    }

    if ($rules) {
        return $rules;
    }

    $sources = ajid_scr_get_sources($options['sources']);
    foreach ($sources as $source) {
        $rules[] = array('url' => $source, 'label' => $source, 'category_id' => 0, 'status' => '', 'keywords' => '');
    }

    return $rules;
}

function ajid_scr_resolve_category($item, $article, $rewritten, $options)
{
    $source_rule = isset($item['source_rule']) && is_array($item['source_rule']) ? $item['source_rule'] : array();
    if (!empty($source_rule['category_id'])) {
        return absint($source_rule['category_id']);
    }

    $haystack = strtolower(wp_strip_all_tags(
        $item['url'] . ' ' .
        (isset($source_rule['label']) ? $source_rule['label'] : '') . ' ' .
        (isset($source_rule['keywords']) ? $source_rule['keywords'] : '') . ' ' .
        $article['title'] . ' ' .
        $rewritten['title'] . ' ' .
        $article['content']
    ));

    $lines = preg_split('/\r\n|\r|\n/', $options['keyword_categories']);
    foreach ($lines as $line) {
        $parts = array_map('trim', explode('|', $line));
        if (count($parts) < 2) {
            continue;
        }

        $keyword = strtolower($parts[0]);
        $category_id = absint($parts[1]);
        if ($keyword !== '' && $category_id && strpos($haystack, $keyword) !== false) {
            return $category_id;
        }
    }

    return absint($options['category_id']);
}

function ajid_scr_content_hash($content)
{
    $text = strtolower(wp_strip_all_tags($content));
    $text = preg_replace('/\s+/', ' ', $text);
    return md5(trim($text));
}

function ajid_scr_find_duplicate($url, $title, $hash, $options)
{
    if (ajid_scr_already_imported($url)) {
        return 'Source URL already imported.';
    }

    if (!empty($options['duplicate_hash_check']) && ajid_scr_hash_exists($hash)) {
        return 'Content hash already exists.';
    }

    if (!empty($options['duplicate_title_check']) && ajid_scr_similar_title_exists($title)) {
        return 'Similar title already exists.';
    }

    return '';
}

function ajid_scr_hash_exists($hash)
{
    if ($hash === '') {
        return false;
    }

    $query = new WP_Query(array(
        'post_type' => 'post',
        'post_status' => 'any',
        'meta_key' => SCR_CONTENT_HASH_META,
        'meta_value' => sanitize_text_field($hash),
        'fields' => 'ids',
        'posts_per_page' => 1,
        'no_found_rows' => true,
    ));

    return $query->have_posts();
}

function ajid_scr_similar_title_exists($title)
{
    global $wpdb;

    $normalized = ajid_scr_normalize_title($title);
    if ($normalized === '') {
        return false;
    }

    $like = '%' . $wpdb->esc_like(substr($normalized, 0, 80)) . '%';
    $found = $wpdb->get_var($wpdb->prepare(
        "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'post' AND post_status <> 'trash' AND LOWER(post_title) LIKE %s LIMIT 1",
        $like
    ));

    if ($found) {
        return true;
    }

    return false;
}

function ajid_scr_normalize_title($title)
{
    $title = strtolower(wp_strip_all_tags($title));
    $title = preg_replace('/[^a-z0-9\s]/', '', $title);
    $title = preg_replace('/\s+/', ' ', $title);
    return trim($title);
}

function ajid_scr_already_imported($url)
{
    $query = new WP_Query(array(
        'post_type' => 'post',
        'post_status' => 'any',
        'meta_key' => SCR_IMPORTED_META,
        'meta_value' => esc_url_raw($url),
        'fields' => 'ids',
        'posts_per_page' => 1,
        'no_found_rows' => true,
    ));

    return $query->have_posts();
}

