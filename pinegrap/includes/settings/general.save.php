<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Site Settings - what the General screen writes: where the site is, what the software is, which releases it takes, the time it keeps and what runs on a schedule.
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
            last_modified_user_id = '" . USER_ID . "',
            last_modified_timestamp = UNIX_TIMESTAMP()");


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
