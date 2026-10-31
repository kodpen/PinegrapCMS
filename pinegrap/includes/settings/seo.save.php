<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Site Settings - what the SEO screen writes: how the site reads from outside.
 *
 * Runs in the scope of includes/settings/screen.php, after the token check and
 * after post_value() is defined. Only the columns edited by the cards on this
 * screen are written, so a screen that was not submitted cannot have its
 * settings overwritten -- which is what the single screen used to do.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_SETTINGS_ENTRY')) {
    exit;
}

 

  
    $current_indexnow_key = db("SELECT indexnow_key FROM config");
    $current_indexnow_key = ($current_indexnow_key !== null) ? $current_indexnow_key : '';
    $new_indexnow_key     = isset($_POST['indexnow_key']) ? trim($_POST['indexnow_key']) : '';

    // The key becomes a file name under the public files directory
    // (<key>.txt) and, when it changes, the name of the file that is deleted,
    // so it has to be a plain token before it reaches the file system. This
    // is IndexNow's own format: 8 to 128 letters, digits and dashes.
    $indexnow_key_pattern = '/^[A-Za-z0-9-]{8,128}$/';

    if ($new_indexnow_key !== '' && !preg_match($indexnow_key_pattern, $new_indexnow_key)) {
        $liveform->mark_error('indexnow_key', lang('The IndexNow key must be 8 to 128 characters long and may only contain letters, digits and dashes.'));
        return;
    }
    
    // The site icon names a PNG or SVG of the Files screen; anything else is
    // refused before a column of this screen is written.
    $site_icon_save_ready = pg_schema_has('config_text') && array_key_exists('site_icon', $_POST);
    $site_icon_input = $site_icon_save_ready ? trim((string) post_value('site_icon')) : '';

    if (($site_icon_input !== '') && ($site_icon_input !== pg_site_icon_name()) && (pg_site_icon_kind($site_icon_input) === '')) {
        $liveform->mark_error('site_icon', lang('The site icon must be a PNG or SVG picture.'));
        return;
    }

    // The site icon, the app icon and the default share image are shown to
    // everyone: none may name an attachment or an ERP document
    // (pg_public_image_choice_ok()), nor a name no file carries unless it was
    // stored before.
    if ($site_icon_save_ready && !pg_public_image_choice_ok($site_icon_input, pg_site_icon_name(), array('png', 'svg'))) {
        $liveform->mark_error('site_icon', lang('Choose a picture from the Files screen that everyone may see.'));
        return;
    }

    $app_icon_save_ready = waf_table_has_column('config', 'app_icon');
    $app_icon_input = $app_icon_save_ready ? trim((string) post_value('app_icon')) : '';

    if ($app_icon_save_ready
        && !pg_public_image_choice_ok($app_icon_input, (string) db_value("SELECT app_icon FROM config"), pg_public_image_file_types())
    ) {
        $liveform->mark_error('app_icon', lang('Choose a picture from the Files screen that everyone may see.'));
        return;
    }

    $og_default_image_ready = waf_table_has_column('config', 'og_default_image');
    $og_default_image_input = $og_default_image_ready ? trim((string) post_value('og_default_image')) : '';

    if ($og_default_image_ready
        && !pg_public_image_choice_ok($og_default_image_input, (string) db_value("SELECT og_default_image FROM config"), pg_public_image_file_types())
    ) {
        $liveform->mark_error('og_default_image', lang('Choose a picture from the Files screen that everyone may see.'));
        return;
    }

    // Structured data settings (2026.4.4). Only written where the columns
    // exist, so this code also runs safely before the database upgrade.
    $seo_jsonld_ready = waf_table_has_column('config', 'og_default_image');
    $site_custom_jsonld_input = $seo_jsonld_ready ? trim((string) post_value('custom_jsonld')) : '';

    // Invalid JSON is refused like any other field the operator has to
    // correct: marked, before the IndexNow file or a column is written. An
    // invalid block is silently dropped at render time, so saving it would
    // hide the mistake exactly where the operator cannot see it. It used to
    // stop the save with output_error, which the dialog got as a 500 after
    // the IndexNow file had already been written.
    if (($site_custom_jsonld_input !== '') && (json_decode($site_custom_jsonld_input) === null)) {
        $liveform->mark_error('custom_jsonld', lang('The site-wide JSON-LD is not valid JSON, so the settings were not saved. Please correct it and try again.'));
        return;
    }

    // If old key exists and changed, delete old file and its DB record. A
    // stored key that does not fit the format is left alone rather than used
    // as a path.
    if ($current_indexnow_key !== '' && $current_indexnow_key !== $new_indexnow_key && preg_match($indexnow_key_pattern, $current_indexnow_key)) {
        $old_file_name = $current_indexnow_key . '.txt';
        $old_file_path = FILE_DIRECTORY_PATH . '/' . $old_file_name;
    
        // Delete physical file if present
        if (file_exists($old_file_path)) {
            // Suppress warnings to avoid noisy outputs in production
            @unlink($old_file_path);
        }
    
        // Delete DB record for old file name
        db("DELETE FROM files WHERE name = '" . escape($old_file_name) . "'");
    }
    
    // If new key is provided, ensure file and DB record are in sync
    if ($new_indexnow_key !== '') {
        $new_file_name = $new_indexnow_key . '.txt';
        $new_file_path = FILE_DIRECTORY_PATH . '/' . $new_file_name;
    
        // Write the key into the file (overwrite if exists)
        $handle = @fopen($new_file_path, 'w');
        if ($handle) {
            fwrite($handle, $new_indexnow_key);
            fclose($handle);
        }
    
        // Determine file size safely
        $file_size = file_exists($new_file_path) ? (int)filesize($new_file_path) : 0;
    
        // Resolve target folder ID
        $folder_id = getPublicRootFolderId();
    
        // Fetch existing DB record by name (normalize different db() return shapes)
        $existing = db("SELECT name, folder, description, type, size, design, user
                        FROM files
                        WHERE name = '" . escape($new_file_name) . "'
                        LIMIT 1");
    
        // Normalize result to an associative array or null
        if (is_array($existing) && isset($existing[0]) && is_array($existing[0])) {
            $existing = $existing[0];
        } elseif (!is_array($existing) || (is_array($existing) && !isset($existing['name']))) {
            $existing = null;
        }
    
        // Prepare target values
        $target = [
            'folder'      => (int)$folder_id,
            'description' => 'IndexNow verification key',
            'type'        => 'txt',
            'size'        => (int)$file_size,
            'design'      => 1,
            'user'        => (int)USER_ID
        ];
    
        if ($existing) {
            // Compare field-by-field; update only if any value differs
            $needsUpdate =
                ((int)$existing['folder']      !== $target['folder'])      ||
                ((string)$existing['description'] !== $target['description']) ||
                ((string)$existing['type']        !== $target['type'])        ||
                ((int)$existing['size']        !== $target['size'])        ||
                ((int)$existing['design']      !== $target['design'])      ||
                ((int)$existing['user']        !== $target['user']);
        
            if ($needsUpdate) {
                db("UPDATE files SET
                        folder = '" . escape($target['folder']) . "',
                        description = '" . escape($target['description']) . "',
                        type = '" . escape($target['type']) . "',
                        size = '" . escape($target['size']) . "',
                        design = '" . escape($target['design']) . "',
                        user = '" . escape($target['user']) . "',
                        timestamp = UNIX_TIMESTAMP()
                    WHERE name = '" . escape($new_file_name) . "'");
            }
        } else {
            // Insert new record only if it does not exist
            db("INSERT INTO files (
                    name, folder, description, type, size, design, user, timestamp
                ) VALUES (
                    '" . escape($new_file_name) . "',
                    '" . escape($target['folder']) . "',
                    '" . escape($target['description']) . "',
                    '" . escape($target['type']) . "',
                    '" . escape($target['size']) . "',
                    '" . escape($target['design']) . "',
                    '" . escape($target['user']) . "',
                    UNIX_TIMESTAMP()
                )");
        }
    
        // Log activity once per operation (create or update); message reflects the file name
        log_activity(
            lang(['string' => 'IndexNow file ({var:1}) was synchronized', 'vars' => $new_file_name]),
            $_SESSION['sessionusername']
        );
    }

    $sql_seo_jsonld_fields = "";
    $sql_app_icon_field = "";

    if ($app_icon_save_ready) {
        $sql_app_icon_field = "app_icon = '" . escape($app_icon_input) . "',";
    }

    if ($seo_jsonld_ready) {
        // The country travels into markup as an ISO 3166-1 alpha-2 code;
        // anything else would be noise every reader ignores.
        $merchant_country_input = mb_strtoupper(trim(post_value('merchant_country')));

        if (!preg_match('/^[A-Z]{2}$/', $merchant_country_input)) {
            $merchant_country_input = '';
        }

        // Blank means "not configured, emit nothing" and is stored as -1;
        // zero is a real answer (free shipping / no returns).
        $merchant_shipping_rate_input = trim(post_value('merchant_shipping_rate'));
        $merchant_shipping_rate_value = ($merchant_shipping_rate_input === '')
            ? -1
            : max(0, (int) round(pg_parse_amount($merchant_shipping_rate_input) * 100));

        $merchant_return_days_input = trim(post_value('merchant_return_days'));
        $merchant_return_days_value = ($merchant_return_days_input === '')
            ? -1
            : max(0, (int) $merchant_return_days_input);

        $merchant_transit_days_min_value = max(0, min(60, (int) post_value('merchant_transit_days_min')));
        $merchant_transit_days_max_value = max($merchant_transit_days_min_value, min(60, (int) post_value('merchant_transit_days_max')));

        $sql_seo_jsonld_fields =
            "og_default_image = '" . escape($og_default_image_input) . "',
            merchant_country = '" . escape($merchant_country_input) . "',
            merchant_shipping_rate = '" . $merchant_shipping_rate_value . "',
            merchant_transit_days_min = '" . $merchant_transit_days_min_value . "',
            merchant_transit_days_max = '" . $merchant_transit_days_max_value . "',
            merchant_return_days = '" . $merchant_return_days_value . "',
            merchant_return_fees = '" . ((post_value('merchant_return_fees') == '1') ? 1 : 0) . "',
            custom_jsonld = '" . escape($site_custom_jsonld_input) . "',";
    }
    // AI crawlers and llms.txt (2026.11.0, 11.0.21; includes/fn/ai_crawlers.php):
    // only once the columns are there and the card's fields were posted.
    $sql_ai_crawler_fields = '';

    // The firewall's AI bot switches sit on the same card. They are newer
    // than the firewall base schema (2026.4.4 vs 2026.2.4), so they carry
    // their own column guard.
    if (waf_table_has_column('config', 'waf_allow_ai_fetchers') && array_key_exists('additional_llms_content', $_POST)) {
        $sql_ai_crawler_fields =
            "waf_allow_ai_fetchers = '" . (post_value('waf_allow_ai_fetchers') ? 1 : 0) . "',
            waf_allow_ai_search = '" . (post_value('waf_allow_ai_search') ? 1 : 0) . "',";
    }

    if (pg_schema_has('config', 'robots_ai_rules') && array_key_exists('additional_llms_content', $_POST)) {
        $sql_ai_crawler_fields .=
            "robots_ai_rules = '" . ((post_value('robots_ai_rules') == '1') ? 1 : 0) . "',
            robots_ai_training = '" . ((post_value('robots_ai_training') == '1') ? 1 : 0) . "',
            llms_txt = '" . ((post_value('llms_txt') == '1') ? 1 : 0) . "',
            additional_llms_content = '" . escape(trim((string) post_value('additional_llms_content'))) . "',";
    }

    // The SEO card's two switches, large image previews (2026.11.0, 11.0.51)
    // and the main host redirect (11.0.52): each only once its column is
    // there and the SEO card was posted (its title field always is).
    $sql_seo_switch_fields = '';

    if (pg_schema_has('config', 'max_image_preview_large') && array_key_exists('title', $_POST)) {
        $sql_seo_switch_fields =
            "max_image_preview_large = '" . ((post_value('max_image_preview_large') == '1') ? 1 : 0) . "',";
    }

    // Redirect to the main host (2026.11.0, 11.0.52), under the same rule.
    if (pg_schema_has('config', 'main_host_redirect') && array_key_exists('title', $_POST)) {
        $sql_seo_switch_fields .=
            "main_host_redirect = '" . ((post_value('main_host_redirect') == '1') ? 1 : 0) . "',";
    }

    // Only what the cards on this screen edit.
    db("UPDATE config
        SET
            title = '" . escape(post_value('title')) . "',
            meta_description = '" . escape(post_value('meta_description')) . "',
            additional_sitemap_content = '" . escape(trim(post_value('additional_sitemap_content'))) . "',
            additional_robots_content = '" . escape(trim(post_value('additional_robots_content'))) . "',
            $sql_app_icon_field
            $sql_seo_jsonld_fields
            $sql_ai_crawler_fields
            $sql_seo_switch_fields
            strutured_data = '" . escape(post_value('strutured_data')) . "',
            indexnow_key = '" . escape(post_value('indexnow_key')) . "',
            last_modified_user_id = '" . USER_ID . "',
            last_modified_timestamp = UNIX_TIMESTAMP()");

    // The site icon: its sizes are drawn now, so a picture that cannot be
    // drawn is said here rather than found missing on the site. A save draws
    // again even a source marked as failed (the server may have changed).
    if ($site_icon_save_ready) {
        pg_config_text_save('site_icon', $site_icon_input);

        if (pg_site_icon_kind($site_icon_input) === 'png') {
            $site_icon_drawn = pg_site_icon_generate($site_icon_input, true);

            if (!$site_icon_drawn['ok']) {
                $liveform->add_notice(pg_site_icon_error_text($site_icon_drawn['reason']));
            }
        }
    }

    // The "Update Bot IP Lists" button submits the settings form with its own
    // name, so the toggles the operator just set are saved first (above) and
    // the fetch runs against the saved state. Forced: pressing the button is
    // the explicit "now" the throttle exists to protect against.
    if (post_value('waf_refresh_ranges') && function_exists('pg_waf_refresh_ai_ranges')) {

        $waf_ranges_results = pg_waf_refresh_ai_ranges(true);

        if (is_array($waf_ranges_results)) {

            $waf_ranges_updated = 0;
            $waf_ranges_failed  = 0;

            foreach ($waf_ranges_results as $waf_ranges_result) {
                if ($waf_ranges_result['status'] === 'updated') {
                    $waf_ranges_updated++;
                } elseif ($waf_ranges_result['status'] === 'failed') {
                    $waf_ranges_failed++;
                }
            }

            if ($waf_ranges_failed == 0) {
                $liveform->add_notice(lang(array(
                    'string' => 'Bot IP lists refreshed: {var:1} list{suffix:1} updated.',
                    'vars'   => $waf_ranges_updated,
                    'suffix' => ($waf_ranges_updated == 1 ? '' : 's'),
                )));
            } else {
                $liveform->add_notice(lang(array(
                    'string' => 'Bot IP lists: {var:1} updated, {var:2} could not be fetched. The stored copies were kept.',
                    'vars'   => array($waf_ranges_updated, $waf_ranges_failed),
                )));

                // Name the first failure's cause. Four sources failing at once
                // is nearly always one local condition (no CA bundle, blocked
                // outbound), so one reason diagnoses all of them and four
                // repeated hints would only bury it.
                foreach ($waf_ranges_results as $waf_ranges_provider => $waf_ranges_result) {
                    if ($waf_ranges_result['status'] === 'failed' && !empty($waf_ranges_result['reason'])) {
                        $liveform->add_notice(lang(array(
                            'string' => 'Bot IP list fetch error: {var:1}',
                            'vars'   => h($waf_ranges_provider . ' — ' . $waf_ranges_result['reason']),
                        )));
                        break;
                    }
                }
            }
        }
    }
