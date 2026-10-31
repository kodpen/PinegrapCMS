<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Site Settings - what the General screen writes: who runs the site, where it is, what the software is, the time it keeps and how it is backed up.
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

    // ── Backups ──────────────────────────────────────────────────────────
    // The 2026.4.8 columns (8.33); before the upgrade nothing is written here.
    // The password and the secret key are never drawn back into the form, so
    // an empty box keeps what is stored. Choosing no remote destination
    // forgets the stored connection details altogether.
    $sql_backup_settings = '';

    if (function_exists('pg_backup_settings_ready') && pg_backup_settings_ready()) {

        $backup_post = function ($key) {
            $value = post_value($key);
            return is_scalar($value) ? trim((string) $value) : '';
        };

        $backup_keep_value = ($backup_post('backup_keep') === '')
            ? (int) $row['backup_keep']
            : min(9999, max(0, (int) $backup_post('backup_keep')));

        $backup_type_value = pg_backup_remote_type($backup_post('backup_remote_type'));

        $backup_stored = pg_backup_remote_decode($row['backup_remote_settings']);
        $backup_values = array();
        $backup_blob_value = '';

        if ($backup_type_value !== '') {

            $backup_ftp = pg_backup_remote_normalize('ftp', array(
                'host'     => $backup_post('backup_ftp_host'),
                'port'     => $backup_post('backup_ftp_port'),
                'user'     => $backup_post('backup_ftp_user'),
                'password' => $backup_post('backup_ftp_password'),
                'path'     => $backup_post('backup_ftp_path'),
                'tls'      => ($backup_post('backup_ftp_tls') !== ''),
            ));

            if ($backup_ftp['password'] === '') {
                $backup_ftp['password'] = $backup_stored['ftp']['password'];
            }

            $backup_s3 = pg_backup_remote_normalize('s3', array(
                'endpoint'   => $backup_post('backup_s3_endpoint'),
                'region'     => $backup_post('backup_s3_region'),
                'bucket'     => $backup_post('backup_s3_bucket'),
                'prefix'     => $backup_post('backup_s3_prefix'),
                'access_key' => $backup_post('backup_s3_access_key'),
                'secret_key' => $backup_post('backup_s3_secret_key'),
                'path_style' => ($backup_post('backup_s3_path_style') !== ''),
            ));

            if ($backup_s3['secret_key'] === '') {
                $backup_s3['secret_key'] = $backup_stored['s3']['secret_key'];
            }

            // Only the destination in use has to be complete.
            if (($backup_type_value === 'ftp') && ($backup_ftp['host'] === '')) {
                $liveform->mark_error('backup_ftp_host', lang(array('string' => '{var:1} is required.', 'vars' => lang('FTP server'))));
            }

            if ($backup_type_value === 's3') {
                if ($backup_s3['bucket'] === '') {
                    $liveform->mark_error('backup_s3_bucket', lang(array('string' => '{var:1} is required.', 'vars' => lang('Bucket name'))));
                }
                if ($backup_s3['access_key'] === '') {
                    $liveform->mark_error('backup_s3_access_key', lang(array('string' => '{var:1} is required.', 'vars' => lang('Access Key'))));
                }
                if ($backup_s3['secret_key'] === '') {
                    $liveform->mark_error('backup_s3_secret_key', lang(array('string' => '{var:1} is required.', 'vars' => lang('Secret Key'))));
                }
            }

            // Google Drive, OneDrive, Dropbox: an account connected for
            // backups, and a folder. Every destination's choice is kept, as
            // for the two above.
            $backup_cloud = array();

            foreach (array_keys(pg_backup_cloud_types()) as $backup_cloud_type) {

                $backup_cloud_folder = pg_backup_cloud_folder($backup_post('backup_' . $backup_cloud_type . '_folder'));

                $backup_cloud[$backup_cloud_type] = pg_backup_remote_normalize($backup_cloud_type, array(
                    'connection_id' => $backup_post('backup_' . $backup_cloud_type . '_connection'),
                    'folder'        => ($backup_cloud_folder === null) ? '' : $backup_cloud_folder,
                ));

                if ($backup_type_value !== $backup_cloud_type) {
                    continue;
                }

                if ($backup_cloud_folder === null) {
                    $liveform->mark_error('backup_' . $backup_cloud_type . '_folder', lang('A folder name may hold letters, digits, spaces, dots, dashes and underscores, up to three levels separated by /.'));
                }

                $backup_cloud_account = pg_backup_cloud_account($backup_cloud_type, $backup_cloud[$backup_cloud_type]);

                if (!$backup_cloud_account['ok']) {
                    $liveform->mark_error('backup_' . $backup_cloud_type . '_connection', $backup_cloud_account['error']);
                }
            }

            // Nothing on this screen is written when the backup card is
            // refused: a half-saved category is worse than none.
            if ($liveform->check_form_errors()) {
                return;
            }

            $backup_values = array_merge(array('ftp' => $backup_ftp, 's3' => $backup_s3), $backup_cloud);
            $backup_blob_value = pg_backup_remote_encode($backup_values);
        }

        // A destination that changed starts without the previous one's error.
        // Compared decoded: the blob gets a fresh IV on every save.
        $backup_changed = ($backup_type_value !== pg_backup_remote_type($row['backup_remote_type']))
            || (($backup_type_value !== '') && ($backup_values !== $backup_stored));

        $sql_backup_settings =
            "backup_keep = '" . (int) $backup_keep_value . "',
             backup_remote_type = '" . escape($backup_type_value) . "',
             backup_remote_settings = '" . escape($backup_blob_value) . "',"
            . ($backup_changed ? " backup_remote_error = ''," : '');
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
    // once the structured data columns exist.
    $sql_organization_logo = waf_table_has_column('config', 'og_default_image')
        ? "organization_logo = '" . escape(trim(post_value('organization_logo'))) . "',"
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
            " . $sql_backup_settings . "
            last_modified_user_id = '" . USER_ID . "',
            last_modified_timestamp = UNIX_TIMESTAMP()");

    // Pinegrap AI sends this key to its gateway (includes/workspace/ai.php).
    // What the gateway said about the old key is forgotten with it: a key it
    // turned down is otherwise never asked about again.
    if (($subscription_key !== str_replace('-', '', (string) SUBSCRIPTION_KEY))
        && function_exists('waf_table_has_column') && waf_table_has_column('config', 'ws_ai_license_state')) {
        db("UPDATE config SET ws_ai_license_state = '', ws_ai_license_checked = 0, ws_ai_license_expires = 0, ws_ai_error = ''");
    }


    // "Save and test the connection": the settings above are stored first, so
    // the test reads exactly what the next automatic backup will use. A small
    // file is sent under a fixed name, so repeated tests replace one another
    // on the remote side instead of piling up. The outcome is a notice either
    // way: the settings dialog carries notices and errors, not warnings, and
    // an error would mark the save itself as refused.
    if (post_value('backup_remote_test') && function_exists('pg_backup_settings_ready') && pg_backup_settings_ready()) {

        pg_backup_settings(true);

        list($backup_test_type, $backup_test_config) = pg_backup_remote_config();

        if ($backup_test_type === '') {

            $liveform->add_notice(lang('No remote backup destination is set.'));

        } else {

            $backup_test_directory = PG_FUNCTIONS_DIR . '/data/temp/backup_test_' . substr(md5(uniqid('', true)), 0, 8);
            $backup_test_file = $backup_test_directory . '/pinegrap_connection_test.txt';

            $backup_test_result = array('ok' => false, 'error' => lang(array('string' => 'The file {var:1} cannot be read.', 'vars' => array(basename($backup_test_file)))));

            if (@mkdir($backup_test_directory, 0755, true)
                && (file_put_contents($backup_test_file, 'Pinegrap connection test ' . gmdate('Y-m-d H:i:s') . " UTC\n") !== false)
            ) {
                $backup_test_result = pg_backup_remote_send($backup_test_file, $backup_test_type, $backup_test_config);
            }

            if (is_file($backup_test_file)) {
                @unlink($backup_test_file);
            }

            if (is_dir($backup_test_directory)) {
                @rmdir($backup_test_directory);
            }

            if ($backup_test_result['ok']) {
                $liveform->add_notice(lang('The remote backup destination accepted the test file.'));
            } else {
                $liveform->add_notice(lang(array('string' => 'The remote backup destination refused the test file: {var:1}', 'vars' => array(h($backup_test_result['error'])))));
            }
        }
    }
