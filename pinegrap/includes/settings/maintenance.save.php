<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Site Settings - what the Updates and maintenance screen writes: which releases the site takes, what runs on a schedule, who is told when something stops working and the maintenance screen.
 *
 * Runs in the scope of includes/settings/screen.php, after the token check and
 * after post_value() is defined. Only the columns edited by the cards on this
 * screen are written, so a screen that was not submitted cannot have its
 * settings overwritten.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_SETTINGS_ENTRY')) {
    exit;
}

    // The stored values: the thresholds an empty box keeps,
    // the maintenance state a save compares with, and the site's time zone.
    $result = mysqli_query(db::$con, "SELECT * FROM config") or output_error('Query failed.');
    $row = mysqli_fetch_assoc($result);

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

    // ── Alerts ───────────────────────────────────────────────────────────
    // The 2026.11.0 columns (11.0.5); before the upgrade nothing is written
    // here. A recipient that is not an address refuses the whole save: a
    // half-saved category is worse than none. The list is stored comma-separated, whatever the operator typed
    // between the addresses.
    $sql_ops_alert_settings = '';

    if (array_key_exists('ops_alerts', $row) && function_exists('pg_ops_alert_recipients')) {

        $ops_alert_recipients_posted = trim((string) post_value('ops_alert_recipients'));
        $ops_alert_recipients_invalid = pg_ops_alert_recipients_invalid($ops_alert_recipients_posted);

        if ($ops_alert_recipients_invalid) {
            $liveform->mark_error('ops_alert_recipients', lang(array(
                'string' => 'Not an e-mail address: {var:1}',
                'vars'   => array(h(implode(', ', $ops_alert_recipients_invalid))),
            )));
        }

        if ($liveform->check_form_errors()) {
            return;
        }

        $sql_ops_alert_settings =
            "ops_alerts = '" . (post_value('ops_alerts') ? 1 : 0) . "',
             ops_alert_email = '" . (post_value('ops_alert_email') ? 1 : 0) . "',
             ops_alert_push = '" . (post_value('ops_alert_push') ? 1 : 0) . "',
             ops_alert_recipients = '" . escape(implode(', ', pg_ops_alert_recipients($ops_alert_recipients_posted, array()))) . "',";

        // The thresholds (0 = that check off), held to what each column and
        // check can take, the way the backup retention is. An empty box
        // keeps what is stored.
        if (array_key_exists('ops_alert_error_rate', $row)) {

            $ops_alert_limits = array(
                'ops_alert_error_rate'    => 100000,
                'ops_alert_disk_percent'  => 50,
                'ops_alert_mail_failures' => 100000,
                'ops_alert_cert_days'     => 60,
            );

            foreach ($ops_alert_limits as $ops_alert_column => $ops_alert_max) {

                $ops_alert_posted = trim((string) post_value($ops_alert_column));

                $ops_alert_value = ($ops_alert_posted === '')
                    ? (int) $row[$ops_alert_column]
                    : min($ops_alert_max, max(0, (int) $ops_alert_posted));

                $sql_ops_alert_settings .= "
             " . $ops_alert_column . " = '" . $ops_alert_value . "',";
            }
        }
    }

    // ── Maintenance mode ─────────────────────────────────────────────────
    // The 2026.11.0 columns (11.0.7); before the upgrade nothing is written.
    // The end time arrives from a datetime-local box in the site's own time
    // zone. An address the firewall's lists would not take, a time that is
    // not one, or an end that has already passed while the mode is being
    // switched on, refuses the save.
    $sql_maintenance_settings = '';
    $maintenance_switched = null;

    if (array_key_exists('maintenance_mode', $row) && function_exists('pg_maintenance_allow_invalid')) {

        $maintenance_on = post_value('maintenance_mode') ? 1 : 0;
        $maintenance_allow_posted = trim((string) post_value('maintenance_allow_ips'));
        $maintenance_allow_invalid = pg_maintenance_allow_invalid($maintenance_allow_posted);

        if ($maintenance_allow_invalid) {
            $liveform->mark_error('maintenance_allow_ips', lang(array(
                'string' => 'Not an address or a range: {var:1}',
                'vars'   => array(h(implode(', ', $maintenance_allow_invalid))),
            )));
        }

        // Read in the site's time zone, saved by the General card (an empty
        // one is the server's).
        $maintenance_timezone = (string) $row['timezone'];

        if (($maintenance_timezone === '') && defined('SERVER_TIMEZONE')) {
            $maintenance_timezone = (string) SERVER_TIMEZONE;
        }

        $maintenance_until_saved = pg_maintenance_until_parse(post_value('maintenance_until'), $maintenance_timezone);

        if ($maintenance_until_saved === false) {
            $maintenance_until_saved = 0;
            $liveform->mark_error('maintenance_until', lang('The end time is not a date and time.'));
        } elseif (pg_maintenance_until_refused((bool) $maintenance_on, $maintenance_until_saved, time(), (int) $row['maintenance_mode'] === 1, (int) $row['maintenance_until'])) {
            $liveform->mark_error('maintenance_until', lang('The end time has already passed.'));
        }

        if ($liveform->check_form_errors()) {
            return;
        }

        $sql_maintenance_settings =
            "maintenance_mode = '" . $maintenance_on . "',
             maintenance_until = '" . (int) $maintenance_until_saved . "',
             maintenance_allow_ips = '" . escape(implode(', ', waf_parse_list($maintenance_allow_posted))) . "',";

        if ($maintenance_on !== (int) $row['maintenance_mode']) {
            $maintenance_switched = $maintenance_on;
        }
    }

    // ── Backup before updating ───────────────────────────────────────────
    // The 2026.11.0 column (11.0.6); before the upgrade nothing is written.
    $sql_update_backup = array_key_exists('update_backup', $row)
        ? "update_backup = '" . (post_value('update_backup') ? 1 : 0) . "',"
        : '';

    // Only what the cards on this screen edit. Every part is empty on a
    // database that has not been upgraded yet; the user stamp keeps the
    // statement whole.
    db("UPDATE config
        SET
            " . $sql_job_dispatch_settings . "
            " . $sql_ops_alert_settings . "
            " . $sql_update_backup . "
            " . $sql_maintenance_settings . "
            last_modified_user_id = '" . USER_ID . "',
            last_modified_timestamp = UNIX_TIMESTAMP()");

    // The mode changed: in the log, and System Status is told at once rather
    // than after its ten-minute cache.
    if ($maintenance_switched !== null) {
        log_activity($maintenance_switched ? lang('Maintenance mode was switched on.') : lang('Maintenance mode was switched off.'), $_SESSION['sessionusername']);

        pg_system_status_forget();
    }

    // An address every visitor shares on the list (the server behind a
    // tunnel, a proxy that is not declared) lets everyone through. The list
    // is saved as typed - on a machine without such a proxy it is the
    // operator's own - and the warning says what it does.
    if (($sql_maintenance_settings !== '') && function_exists('waf_client_ip')) {
        $maintenance_my_ip = (string) waf_client_ip();

        if (pg_maintenance_list_opens_to_all($maintenance_allow_posted, $maintenance_my_ip, pg_maintenance_ip_is_shared($maintenance_my_ip))) {
            $liveform->add_warning(lang(array(
                'string' => '{var:1} is on the list, but it is the server or a proxy every visitor arrives through, so everyone sees the site during maintenance. Add it to Trusted Proxies on the Firewall card and list your own address instead.',
                'vars'   => array(h($maintenance_my_ip)),
            )));
        }
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
