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

    // The key becomes a file name under the public files directory
    // (<key>.txt) and, when it changes, the name of the file that is deleted,
    // so it has to be a plain token before it reaches the file system. This
    // is IndexNow's own format: 8 to 128 letters, digits and dashes.
    $indexnow_key_pattern = '/^[A-Za-z0-9-]{8,128}$/';

    if ($new_indexnow_key !== '' && !preg_match($indexnow_key_pattern, $new_indexnow_key)) {
        $liveform->mark_error('indexnow_key', lang('The IndexNow key must be 8 to 128 characters long and may only contain letters, digits and dashes.'));
        return;
    }
    
    // The notice prints the cookie policy address as a link, so only a site
    // path or a web address is taken (escape_url()); anything else is refused
    // before a column of this screen is written.
    $cookie_consent_policy_url_input = trim((string) post_value('cookie_consent_policy_url'));

    if (($cookie_consent_policy_url_input !== '') && (escape_url($cookie_consent_policy_url_input) === false)) {
        $liveform->mark_error('cookie_consent_policy_url', lang('The cookie policy address must start with / or https://.'));
        return;
    }

    // Product feeds (2026.11.0, 11.0.4): the addresses the feeds pass on must
    // be absolute, so a wrong one is refused here, before anything on this
    // screen is written. Only when the card's fields were posted and the
    // column is there.
    $product_feeds_save_ready = function_exists('pg_product_feed_schema_ready') && pg_product_feed_schema_ready()
        && array_key_exists('product_feed_seller_name', $_POST);

    if ($product_feeds_save_ready) {
        foreach (array('product_feed_seller_url', 'product_feed_privacy_policy_url', 'product_feed_terms_url') as $product_feeds_field) {
            if (!pg_product_feed_valid_url(post_value($product_feeds_field))) {
                $liveform->mark_error($product_feeds_field, lang('Enter a full address starting with https://, or leave the field empty.'));
                return;
            }
        }
    }

    // Visitor record retention (2026.4.10, 10.3): 0, or a whole number of
    // days within the bounds the module sets. Checked here, before anything
    // on this screen is written. Only when the form carried the field: a
    // missing one would read as 0 and quietly lift the limit.
    $visitor_retention_ready = pg_visitor_retention_ready() && array_key_exists('visitor_retention_days', $_POST);
    $visitor_retention_days_input = $visitor_retention_ready ? pg_visitor_retention_days_from_input(post_value('visitor_retention_days')) : 0;

    if ($visitor_retention_ready && ($visitor_retention_days_input === null)) {
        $liveform->mark_error('visitor_retention_days', lang(array('string' => 'Keep visitor records for 0 days (no limit), or for {var:1} to {var:2} days.', 'vars' => array(PG_VISITOR_RETENTION_MIN_DAYS, PG_VISITOR_RETENTION_MAX_DAYS))));
        return;
    }

    // The days after which a visit's IP address goes (2026.4.10, 10.8), the
    // same way: only when the form carried the field.
    $visitor_ip_ready = $visitor_retention_ready && pg_visitor_ip_ready() && array_key_exists('visitor_ip_days', $_POST);
    $visitor_ip_days_input = $visitor_ip_ready ? pg_visitor_ip_days_from_input(post_value('visitor_ip_days')) : 0;

    if ($visitor_ip_ready && ($visitor_ip_days_input === null)) {
        $liveform->mark_error('visitor_ip_days', lang(array('string' => 'Remove IP addresses after 0 days (keep them with the record), or after {var:1} to {var:2} days.', 'vars' => array(PG_VISITOR_IP_MIN_DAYS, PG_VISITOR_IP_MAX_DAYS))));
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

    if (waf_table_has_column('config', 'app_icon')) {
        $sql_app_icon_field = "app_icon = '" . escape(trim(post_value('app_icon'))) . "',";
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
    // A changed retention period or summary switch clears the general job's
    // last look, so the change applies from its next tick.
    $sql_visitor_retention_fields = '';

    if ($visitor_retention_ready) {

        $visitor_summary_input = (post_value('visitor_summary') == '1') ? 1 : 0;

        $visitor_retention_current = pg_visitor_retention_settings();

        if (!is_array($visitor_retention_current)
            || ($visitor_retention_current['days'] !== $visitor_retention_days_input)
            || ($visitor_retention_current['summary'] !== ($visitor_summary_input === 1))
            || ($visitor_ip_ready && ($visitor_retention_current['ip_days'] !== $visitor_ip_days_input))
        ) {
            pg_visitor_cleanup_reset();
        }

        $sql_visitor_retention_fields =
            "visitor_retention_days = '" . (int) $visitor_retention_days_input . "',
            visitor_summary = '" . $visitor_summary_input . "',"
            . ($visitor_ip_ready ? "
            visitor_ip_days = '" . (int) $visitor_ip_days_input . "'," : "");
    }

    $sql_cookie_consent_fields = '';

    if (waf_table_has_column('config', 'cookie_consent')) {
        $sql_cookie_consent_fields =
            "cookie_consent = '" . ((post_value('cookie_consent') == '1') ? 1 : 0) . "',
            cookie_consent_policy_url = '" . escape($cookie_consent_policy_url_input) . "',";
    }

    // AI crawlers and llms.txt (2026.11.0, 11.0.21; includes/fn/ai_crawlers.php):
    // only once the columns are there and the card's fields were posted. The
    // firewall switches the card shows are written by the firewall screen.
    $sql_ai_crawler_fields = '';

    if (pg_schema_has('config', 'robots_ai_rules') && array_key_exists('additional_llms_content', $_POST)) {
        $sql_ai_crawler_fields =
            "robots_ai_rules = '" . ((post_value('robots_ai_rules') == '1') ? 1 : 0) . "',
            robots_ai_training = '" . ((post_value('robots_ai_training') == '1') ? 1 : 0) . "',
            llms_txt = '" . ((post_value('llms_txt') == '1') ? 1 : 0) . "',
            additional_llms_content = '" . escape(trim((string) post_value('additional_llms_content'))) . "',";
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
            $sql_cookie_consent_fields
            $sql_ai_crawler_fields
            $sql_visitor_retention_fields
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

    // Product feeds: their own JSON column, written after the screen's
    // columns. A new address that cannot be shown again (no site key) is shown
    // once here; a build asked for, or caused by a feed just switched on, is
    // reported the same way.
    if ($product_feeds_save_ready) {
        $product_feeds_offered = pg_product_feed_screen_groups();
        $product_feeds_current = pg_product_feed_settings();
        $product_feeds_enabled = array();

        foreach (array_keys(pg_product_feed_formats()) as $product_feeds_format) {
            $product_feeds_enabled[$product_feeds_format] = (post_value('product_feed_' . $product_feeds_format) == '1');
        }

        $product_feeds_new_address = (string) post_value('product_feed_new_address');

        $product_feeds_saved = pg_product_feed_save_from_screen(array(
            'seller_name'        => (string) post_value('product_feed_seller_name'),
            'seller_url'         => (string) post_value('product_feed_seller_url'),
            'privacy_policy_url' => (string) post_value('product_feed_privacy_policy_url'),
            'terms_url'          => (string) post_value('product_feed_terms_url'),
            'default_brand'      => (string) post_value('product_feed_default_brand'),
            // The card offers no list while no catalog shows a group (or
            // e-commerce is off); the stored choice is kept until it does.
            'excluded_group_ids' => $product_feeds_offered
                ? pg_product_feed_posted_groups(post_value('product_feed_excluded_groups'), array_keys($product_feeds_offered))
                : $product_feeds_current['excluded_group_ids'],
            'enabled'            => $product_feeds_enabled,
            'new_address'        => isset($product_feeds_enabled[$product_feeds_new_address]) ? $product_feeds_new_address : '',
            'build'              => (bool) post_value('product_feed_build'),
        ));

        $product_feeds_texts = pg_product_feed_format_texts();

        foreach ($product_feeds_saved['issued'] as $product_feeds_format => $product_feeds_token) {
            $liveform->add_notice(lang(array('string' => 'The address of {var:1} is {var:2} - note it now: this site has no key to show it again.', 'vars' => array($product_feeds_texts[$product_feeds_format]['label'], h(pg_product_feed_url($product_feeds_token))))));
        }

        if ($product_feeds_saved['renewed'] !== '') {
            $liveform->add_notice(lang(array('string' => '{var:1} has a new address; the old one no longer works.', 'vars' => array($product_feeds_texts[$product_feeds_saved['renewed']]['label']))));
        }

        if (is_array($product_feeds_saved['built'])) {
            if ($product_feeds_saved['built']['error'] !== '') {
                $liveform->add_notice(lang(array('string' => 'The product feeds were not built: {var:1}', 'vars' => array(pg_product_feed_error_text($product_feeds_saved['built']['error'])))));
            }

            foreach ($product_feeds_saved['built']['formats'] as $product_feeds_format => $product_feeds_result) {
                $liveform->add_notice($product_feeds_result['ok']
                    ? lang(array('string' => '{var:1} was built with {var:2} product(s).', 'vars' => array($product_feeds_texts[$product_feeds_format]['label'], (int) $product_feeds_result['count'])))
                    : lang(array('string' => '{var:1} was not built: {var:2}', 'vars' => array($product_feeds_texts[$product_feeds_format]['label'], pg_product_feed_error_text($product_feeds_result['error'])))));
            }
        }
    }

