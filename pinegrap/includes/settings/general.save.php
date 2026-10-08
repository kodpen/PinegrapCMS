<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Site Settings - what the General screen writes: where the site is, what the software is, which releases it takes, the time it keeps, what runs on a schedule and how it is backed up.
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

    // ── Live chat settings ───────────────────────────────────────────────
    // Same pattern as the WAF fragment: on installs that have not run the
    // upgrade the fragment stays empty and saving does not crash on an
    // unknown column. The appearance columns (2026.4.3) have their own
    // guard.
    // ── Scheduled job dispatch ───────────────────────────────────────────
    // Same fragment pattern: an install that has not run the 2026.4.2 upgrade
    // saves nothing here instead of failing the whole screen on an unknown
    // column.
    $sql_job_dispatch_settings = '';

    if (function_exists('waf_table_has_column')
        && waf_table_has_column('config', 'job_dispatch_enabled')
    ) {
        $job_dispatch_posted = post_value('job_dispatch_job');

        if (!is_array($job_dispatch_posted)) {
            $job_dispatch_posted = array();
        }

        // Filtered against the catalogue rather than stored as posted. The
        // dispatcher turns these names into file paths, so nothing that is not
        // a job this software ships may reach the column.
        $job_dispatch_selected = array();

        foreach (pg_cron_jobs() as $job_dispatch_name => $job_dispatch_job) {

            if (!$job_dispatch_job['dispatch']) {
                continue;
            }

            if (isset($job_dispatch_posted[$job_dispatch_name])) {
                $job_dispatch_selected[] = $job_dispatch_name;
            }
        }

        $sql_job_dispatch_settings =
            "job_dispatch_enabled = '" . escape(post_value('job_dispatch_enabled') ? 1 : 0) . "',
             job_dispatch = '" . escape(implode(',', $job_dispatch_selected)) . "',";
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

            // Nothing on this screen is written when the backup card is
            // refused: a half-saved category is worse than none.
            if ($liveform->check_form_errors()) {
                return;
            }

            $backup_values = array('ftp' => $backup_ftp, 's3' => $backup_s3);
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
    // Only what the cards on this screen edit.
    db("UPDATE config
        SET
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
            " . $sql_job_dispatch_settings . "
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


    // Update channel: its column arrives with migration 4.21, so a database that
    // has not been upgraded yet must not break the rest of this save. Changing
    // the channel also clears the last answer and the check timestamp — that
    // answer came from the other channel, and leaving it there would show the
    // wrong "an update is available" (or hide a real one) until the next daily
    // check happened to run.
    $channel_column = @mysqli_query(db::$con, "SHOW COLUMNS FROM config WHERE Field = 'software_update_channel'");

    if ($channel_column && (mysqli_num_rows($channel_column) > 0)) {

        $channel_value = ((post_value('software_update_channel') === 'beta') ? 'beta' : 'stable');

        if ($channel_value !== (defined('SOFTWARE_UPDATE_CHANNEL') ? SOFTWARE_UPDATE_CHANNEL : 'stable')) {

            db("UPDATE config SET software_update_channel = '" . escape($channel_value) . "', software_update_available = '0', last_software_update_check_timestamp = '0'");

            db("DELETE FROM notifications WHERE action = 'software_update'");

            log_activity(lang(array('string' => 'The update channel was changed to {var:1}.', 'vars' => $channel_value)), $_SESSION['sessionusername']);
        }
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
