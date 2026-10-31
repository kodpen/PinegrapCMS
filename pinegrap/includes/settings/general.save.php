<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Site Settings - what the General screen writes: who runs the site, where it is, what the software is and the time it keeps.
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


    
    // Remove a leading http:// or https:// from the hostname; the scheme is
    // kept in url_scheme and a pasted URL must not end up in the column.
    $hostname = trim((string) post_value('hostname'));
    $hostname = preg_replace('#^https?://#i', '', $hostname);

    // The Support E-mail Address is the From of system mail: a broken one
    // refuses the whole save before anything is written. Blank is allowed.
    foreach (pg_email_field_errors(array('email_address' => post_value('email_address'))) as $email_field => $email_error) {
        $liveform->mark_error($email_field, $email_error);
    }

    if ($liveform->check_form_errors()) {
        return;
    }
    
    // Secure Mode - the url_scheme column - is saved by the Firewall screen,
    // next to HSTS and the trusted proxies it depends on. It must not be
    // written here: a save of this screen carries no secure_mode field, and
    // deriving the scheme from its absence would switch Secure Mode off on
    // every save of the General screen.





    // if null mean no software update yet so software language and theme not gonna update
    $sql_software_language ='';
    //check if not null, than update.
    $query = "SELECT * FROM config";
    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
    $row = mysqli_fetch_assoc($result);

    $software_language = $row['software_language'];
    if($software_language != NULL){
        $sql_software_language ="software_language = '" . escape($_POST['software_language'] ?? '') . "',";
    }
   
    // prepare subscription_key for write to db
    $subscription_key_posted = post_value('subscription_key');
    $subscription_key = str_replace('-', '', (string) $subscription_key_posted);
    // check if subscription_key was posted and differs from the stored key
    if (($subscription_key_posted !== null) && ($subscription_key_posted != SUBSCRIPTION_KEY)) {
        //remove all session about license, we will set it from license_check() again.
        unset($_SESSION['software']['settings']['license']['last_check']);
        unset($_SESSION['software']['settings']['license']['countdown']);
        unset($_SESSION['software']['settings']['license']['expiration_date_formatted']);
        unset($_SESSION['software']['settings']['license']['status']);
        unset($_SESSION['software']['settings']['license']['error_code']);
    }

    // The seller's tax number, kept the way the store's country writes it:
    // digits in Turkey (VKN/TCKN), as typed elsewhere - an EU VAT id carries
    // its country prefix and letters. Never longer than the column holds
    // (32 from 2026.4.4/4.70, 11 before it), so the save never fails on it.
    $erp_seller_vkn_width = (int) db_value("SELECT CHARACTER_MAXIMUM_LENGTH FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'config' AND COLUMN_NAME = 'erp_seller_vkn' LIMIT 1");
    $erp_seller_vkn_width = ($erp_seller_vkn_width > 0) ? $erp_seller_vkn_width : 11;
    $erp_seller_vkn_value = trim((string) post_value('erp_seller_vkn'));
    $erp_seller_vkn_value = (pg_erp_store_country() === 'TR')
        ? substr(preg_replace('/\D/', '', $erp_seller_vkn_value), 0, 11)
        : mb_substr($erp_seller_vkn_value, 0, $erp_seller_vkn_width);

    // The Organization card's logo, where the SEO save wrote it before: only
    // once the structured data columns exist. It is shown to everyone, so no
    // attachment or ERP document (pg_public_image_choice_ok()).
    $organization_logo_ready = waf_table_has_column('config', 'og_default_image');
    $organization_logo_input = $organization_logo_ready ? trim((string) post_value('organization_logo')) : '';

    if ($organization_logo_ready
        && !pg_public_image_choice_ok($organization_logo_input, (string) db_value("SELECT organization_logo FROM config"), pg_public_image_file_types())
    ) {
        $liveform->mark_error('organization_logo', lang('Choose a picture from the Files screen that everyone may see.'));
        return;
    }

    $sql_organization_logo = $organization_logo_ready
        ? "organization_logo = '" . escape($organization_logo_input) . "',"
        : '';

    // Only what the cards on this screen edit.
    db("UPDATE config
        SET
            organization_name = '" . escape(post_value('organization_name')) . "',
            organization_address_1 = '" . escape(post_value('organization_address_1')) . "',
            organization_address_2 = '" . escape(post_value('organization_address_2')) . "',
            organization_city = '" . escape(post_value('organization_city')) . "',
            organization_state = '" . escape(post_value('organization_state')) . "',
            organization_zip_code = '" . escape(post_value('organization_zip_code')) . "',
            organization_country = '" . escape(post_value('organization_country')) . "',
            " . $sql_organization_logo . "
            erp_seller_vkn = '" . escape($erp_seller_vkn_value) . "',
            erp_seller_tax_office = '" . escape(trim(post_value('erp_seller_tax_office'))) . "',
            hostname = '" . escape($hostname) . "',
            email_address = '" . escape(post_value('email_address')) . "',
            proxy_address = '" . escape(post_value('proxy_address')) . "',
            debug = '" . escape(post_value('debug')) . "',
            subscription_id = '" . escape(post_value('subscription_id')) . "',
            subscription_key = '" . escape($subscription_key) . "',
            $sql_software_language 
            timezone = '" . escape(post_value('timezone')) . "',
            date_format = '" . escape(post_value('date_format')) . "',
            time_format = '" . escape(post_value('time_format')) . "',
            last_modified_user_id = '" . USER_ID . "',
            last_modified_timestamp = UNIX_TIMESTAMP()");

    // Pinegrap AI sends this key to its gateway (includes/workspace/ai.php).
    // What the gateway said about the old key is forgotten with it: a key it
    // turned down is otherwise never asked about again.
    if (($subscription_key !== str_replace('-', '', (string) SUBSCRIPTION_KEY))
        && function_exists('waf_table_has_column') && waf_table_has_column('config', 'ws_ai_license_state')) {
        db("UPDATE config SET ws_ai_license_state = '', ws_ai_license_checked = 0, ws_ai_license_expires = 0, ws_ai_error = ''");
    }
