<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Site Settings - what the SEO screen writes: how the site reads from outside, and what the traffic to it looks like.
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
    
    // If old key exists and changed, delete old file and its DB record
    if ($current_indexnow_key !== '' && $current_indexnow_key !== $new_indexnow_key) {
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

    // Structured data settings (2026.4.4). Only written where the columns
    // exist, so this code also runs safely before the database upgrade.
    $sql_seo_jsonld_fields = "";
    $sql_app_icon_field = "";

    if (waf_table_has_column('config', 'app_icon')) {
        $sql_app_icon_field = "app_icon = '" . escape(trim(post_value('app_icon'))) . "',";
    }

    if (waf_table_has_column('config', 'og_default_image')) {
        $site_custom_jsonld_input = trim(post_value('custom_jsonld'));

        // Refuse invalid JSON outright. An invalid block is silently dropped
        // at render time, so saving it would hide the mistake exactly where
        // the operator cannot see it.
        if (($site_custom_jsonld_input != '') && (json_decode($site_custom_jsonld_input) === null)) {
            output_error(lang('The site-wide JSON-LD is not valid JSON, so the settings were not saved. Please correct it and try again.') . ' <a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.');
        }

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
            : max(0, (int) round(((float) str_replace(',', '.', $merchant_shipping_rate_input)) * 100));

        $merchant_return_days_input = trim(post_value('merchant_return_days'));
        $merchant_return_days_value = ($merchant_return_days_input === '')
            ? -1
            : max(0, (int) $merchant_return_days_input);

        $merchant_transit_days_min_value = max(0, min(60, (int) post_value('merchant_transit_days_min')));
        $merchant_transit_days_max_value = max($merchant_transit_days_min_value, min(60, (int) post_value('merchant_transit_days_max')));

        $sql_seo_jsonld_fields =
            "og_default_image = '" . escape(trim(post_value('og_default_image'))) . "',
            organization_logo = '" . escape(trim(post_value('organization_logo'))) . "',
            merchant_country = '" . escape($merchant_country_input) . "',
            merchant_shipping_rate = '" . $merchant_shipping_rate_value . "',
            merchant_transit_days_min = '" . $merchant_transit_days_min_value . "',
            merchant_transit_days_max = '" . $merchant_transit_days_max_value . "',
            merchant_return_days = '" . $merchant_return_days_value . "',
            merchant_return_fees = '" . ((post_value('merchant_return_fees') == '1') ? 1 : 0) . "',
            custom_jsonld = '" . escape($site_custom_jsonld_input) . "',";
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
            strutured_data = '" . escape(post_value('strutured_data')) . "',
            indexnow_key = '" . escape(post_value('indexnow_key')) . "',
            visitor_tracking = '" . escape(post_value('visitor_tracking')) . "',
            tracking_code_duration = '" . e(post_value('tracking_code_duration')) . "',
            pay_per_click_flag = '" . escape(post_value('pay_per_click_flag')) . "',
            stats_url = '" . escape(post_value('stats_url')) . "',
            google_analytics = '" . escape(post_value('google_analytics')) . "',
            google_analytics_web_property_id = '" . escape(post_value('google_analytics_web_property_id')) . "',
            last_modified_user_id = '" . USER_ID . "',
            last_modified_timestamp = UNIX_TIMESTAMP()");
