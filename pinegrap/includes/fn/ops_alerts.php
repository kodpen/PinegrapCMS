<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Operations alerts: the failures that otherwise surface only when somebody
 * opens System Status or the logs - a failed or missing backup, a scheduled
 * task that stopped, software files that no longer match the release, a burst
 * of PHP errors, a disk running full, mail or webhooks that stopped going out,
 * a certificate about to run out - told to the people who run the site on the
 * panel bell, by e-mail and on the devices they subscribed.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// A module of functions.php: operations alerts - the catalogue, the one
// sender, the once-a-day rule, and the checks the general job runs.
//
// One alert is one row in notifications (action 'ops'): order_total carries
// the code and title the detail, the way the ERP notices carry their counts.
// That row is the bell entry, the source of the device banner, the record the
// once-a-day rule reads and what System Status lists as the last alert per
// code - so there is no table of its own. A small state file next to it
// (data/temp/ops_alerts_state.json) remembers the same send times and when the
// checks last ran; it is a cache and may be swept by Cleanup at any time.
//
// Nothing here runs before the 2026.11.0 columns exist: OPS_ALERTS reads 0
// until then (init.php), and every entry point asks pg_ops_alerts_enabled().
if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/**
 * Every alert this software raises, keyed by the code stored with it.
 *
 * - label  What the alert is, as the bell, the e-mail subject and the System
 *          Status list say it.
 * - type   The notification type: 'error' for something that has stopped,
 *          'warning' for something that will.
 * - url    The panel screen that deals with it, relative to the software
 *          directory.
 * - direct Optional. The e-mail is sent at once instead of being queued.
 *
 * @return array<string,array>
 */
function pg_ops_alert_codes()
{
    return array(
        'backup_failed' => array(
            'label' => lang('The automatic backup failed'),
            'type'  => 'error',
            'url'   => 'backups.php',
        ),
        'backup_remote_failed' => array(
            'label' => lang('The remote backup copy failed'),
            'type'  => 'warning',
            'url'   => 'settings_general.php#pgset-backup',
        ),
        'backup_stale' => array(
            'label' => lang('No recent backup'),
            'type'  => 'warning',
            'url'   => 'backups.php',
        ),
        'cron_stalled' => array(
            'label' => lang('A scheduled task stopped running'),
            'type'  => 'error',
            'url'   => 'settings_general.php#pgset-cron',
        ),
        'integrity' => array(
            'label' => lang('Software files were changed'),
            'type'  => 'error',
            'url'   => 'software_repair.php',
        ),
        'error_spike' => array(
            'label' => lang('Many PHP errors'),
            'type'  => 'warning',
            'url'   => 'view_log.php',
        ),
        'disk_low' => array(
            'label' => lang('Disk space is running out'),
            'type'  => 'error',
            'url'   => 'welcome.php',
        ),
        'mail_failures' => array(
            'label' => lang('E-mails are not being sent'),
            'type'  => 'error',
            'url'   => 'mail_queue.php',
            'direct' => true,
        ),
        'webhook_failing' => array(
            'label' => lang('An event address stopped answering'),
            'type'  => 'warning',
            'url'   => 'api_settings.php#events',
        ),
        'payment_notice' => array(
            'label' => lang('A payment service reported a payment to check'),
            'type'  => 'warning',
            'url'   => 'view_log.php',
        ),
        'cert_expiring' => array(
            'label' => lang('The SSL certificate is about to expire'),
            'type'  => 'error',
            'url'   => 'welcome.php',
        ),
    );
}

/**
 * The thresholds of the checks, from the settings or their defaults.
 *
 * 0 switches that one check off. The defaults are those of the 2026.11.0
 * step, so a site whose columns are older than this code still checks.
 *
 * @return array error_rate (errors an hour), disk_percent (free %),
 *               mail_failures (a day), cert_days (days before the end)
 */
function pg_ops_alert_thresholds()
{
    return array(
        'error_rate'    => defined('OPS_ALERT_ERROR_RATE') ? max(0, (int) OPS_ALERT_ERROR_RATE) : 50,
        'disk_percent'  => defined('OPS_ALERT_DISK_PERCENT') ? max(0, min(50, (int) OPS_ALERT_DISK_PERCENT)) : 10,
        'mail_failures' => defined('OPS_ALERT_MAIL_FAILURES') ? max(0, (int) OPS_ALERT_MAIL_FAILURES) : 10,
        'cert_days'     => defined('OPS_ALERT_CERT_DAYS') ? max(0, min(60, (int) OPS_ALERT_CERT_DAYS)) : 14,
    );
}

/**
 * Whether alerts are switched on, and the schema is there to switch them on.
 *
 * @return bool
 */
function pg_ops_alerts_enabled()
{
    return (defined('OPS_ALERTS') && OPS_ALERTS);
}

/**
 * The addresses an alert e-mail goes to.
 *
 * The list the operator typed, commas, semicolons or spaces between the
 * addresses; with nothing typed, the first valid fallback (the site's own
 * address), so switching alerts on is the only step. Invalid entries and
 * repeats are dropped; the settings screen refuses to save them in the first
 * place (pg_ops_alert_recipients_invalid()).
 *
 * @param string $configured
 * @param array  $fallbacks  addresses tried in order when nothing is typed
 * @return array valid, unique addresses; may be empty
 */
function pg_ops_alert_recipients($configured, array $fallbacks)
{
    $configured = trim((string) $configured);
    $typed = ($configured !== '');

    $candidates = $typed ? preg_split('/[\s,;]+/', $configured) : $fallbacks;

    $recipients = array();

    foreach ((array) $candidates as $address) {

        $address = trim((string) $address);

        if (($address === '') || !filter_var($address, FILTER_VALIDATE_EMAIL)) {
            continue;
        }

        if (!in_array(strtolower($address), array_map('strtolower', $recipients), true)) {
            $recipients[] = $address;
        }

        // With nothing typed the fallbacks are alternatives, not a list.
        if (!$typed) {
            break;
        }
    }

    return $recipients;
}

/**
 * The entries of a typed recipient list that are not e-mail addresses.
 *
 * @param string $configured
 * @return array
 */
function pg_ops_alert_recipients_invalid($configured)
{
    $invalid = array();

    foreach ((array) preg_split('/[\s,;]+/', trim((string) $configured)) as $address) {

        $address = trim((string) $address);

        if (($address !== '') && !filter_var($address, FILTER_VALIDATE_EMAIL)) {
            $invalid[] = $address;
        }
    }

    return $invalid;
}

/**
 * Whether an alert last sent at $last_sent may be sent again at $now.
 *
 * Once per code per window: a problem that lasts a week is one alert a day,
 * not one every ten minutes. A send time in the future (the clock was set
 * back) holds the alert only as long as the window would have.
 *
 * @param int $last_sent unix time, 0 for never
 * @param int $now
 * @param int $window    seconds
 * @return bool
 */
function pg_ops_alert_due($last_sent, $now, $window = 86400)
{
    $last_sent = (int) $last_sent;

    if ($last_sent < 1) {
        return true;
    }

    return (abs((int) $now - $last_sent) >= (int) $window);
}

/**
 * An alert's detail as it is stored and shown: plain text on one line, short
 * enough for the notification row (title is VARCHAR(256)).
 *
 * @param string $detail
 * @return string
 */
function pg_ops_alert_clean_detail($detail)
{
    $detail = html_entity_decode(strip_tags((string) $detail), ENT_QUOTES, 'UTF-8');
    $detail = trim((string) preg_replace('/\s+/u', ' ', $detail));

    if (mb_strlen($detail, 'UTF-8') > 250) {
        $detail = rtrim(mb_substr($detail, 0, 249, 'UTF-8')) . '…';
    }

    return $detail;
}

/**
 * The panel address an alert points at.
 *
 * @param string $code
 * @param bool   $absolute for the e-mail; the bell uses the relative form
 * @return string
 */
function pg_ops_alert_url($code, $absolute = false)
{
    $codes = pg_ops_alert_codes();

    $url = isset($codes[$code]) ? $codes[$code]['url'] : 'welcome.php';

    if (!$absolute) {
        return $url;
    }

    return URL_SCHEME . HOSTNAME_SETTING . PATH . SOFTWARE_DIRECTORY . '/' . $url;
}

// ----------------------------------------------------------------------------
// The state file
// ----------------------------------------------------------------------------

/**
 * Where the state file is.
 *
 * @return string
 */
function pg_ops_alert_state_path()
{
    return PG_FUNCTIONS_DIR . '/data/temp/ops_alerts_state.json';
}

/**
 * A state as read from the file, with every key present and typed.
 *
 * - sent  code => unix time the alert was last sent
 * - pass  unix time the checks last ran
 *
 * @param mixed $state
 * @return array
 */
function pg_ops_alert_state_normalize($state)
{
    $state = is_array($state) ? $state : array();

    $sent = array();

    if (isset($state['sent']) && is_array($state['sent'])) {
        foreach ($state['sent'] as $code => $stamp) {
            if (is_string($code) && (preg_match('/^[a-z0-9_]{1,64}$/', $code) === 1)) {
                $sent[$code] = (int) $stamp;
            }
        }
    }

    $normalized = $state;
    $normalized['sent'] = $sent;
    $normalized['pass'] = isset($state['pass']) ? (int) $state['pass'] : 0;

    return $normalized;
}

/**
 * Read the state file, or change it under an exclusive lock.
 *
 * Two processes can raise the same alert at once - the general job and a
 * backup running on its own schedule - so a change is a read-modify-write
 * under flock() on the file itself.
 *
 * @param callable|null $change receives the normalized state, returns the new one
 * @return array|null the state (after the change), null when the file could not
 *                    be opened or locked
 */
function pg_ops_alert_state($change = null)
{
    $path = pg_ops_alert_state_path();

    if (!is_dir(dirname($path))) {
        @mkdir(dirname($path), 0755, true);
    }

    $handle = @fopen($path, 'c+');

    if ($handle === false) {
        return null;
    }

    if (!@flock($handle, ($change === null) ? LOCK_SH : LOCK_EX)) {
        fclose($handle);
        return null;
    }

    $state = pg_ops_alert_state_normalize(json_decode((string) stream_get_contents($handle), true));

    if ($change !== null) {

        $state = pg_ops_alert_state_normalize(call_user_func($change, $state));

        ftruncate($handle, 0);
        rewind($handle);
        fwrite($handle, (string) json_encode($state));
        fflush($handle);
    }

    flock($handle, LOCK_UN);
    fclose($handle);

    return $state;
}

// ----------------------------------------------------------------------------
// Sending
// ----------------------------------------------------------------------------

/**
 * When an alert was last sent: the later of its newest bell row and the state
 * file. The bell row survives a Cleanup of data/temp; the file survives a
 * notification deleted from the bell, and covers a send whose row could not be
 * written.
 *
 * @param string $code
 * @return int unix time, 0 for never
 */
function pg_ops_alert_last_sent($code)
{
    $state = pg_ops_alert_state();

    $from_file = (is_array($state) && isset($state['sent'][$code])) ? (int) $state['sent'][$code] : 0;

    $from_bell = (int) db_value(
        "SELECT MAX(timestamp)
        FROM notifications
        WHERE
            (action = 'ops')
            AND (order_total = '" . escape($code) . "')");

    return max($from_file, $from_bell);
}

/**
 * Raise an alert: the bell row, the device banners and the e-mail, at most
 * once per code per day.
 *
 * The send is claimed in the state file before anything goes out, so two
 * processes that find the same problem in the same minute send it once. When
 * the file cannot be written at all the bell row is the only record, which is
 * still once a day for every run that comes after it.
 *
 * The e-mail is queued like the rest of the site's mail (the general job sends
 * it within a minute), except for a code marked 'direct': an alert about the
 * mail itself must not wait in the queue that is failing.
 *
 * @param string $code   a key of pg_ops_alert_codes()
 * @param string $detail what happened, in a sentence; plain text
 * @return array array('sent' => bool, 'notification_id' => int, 'email' => bool)
 */
function pg_ops_alert($code, $detail = '')
{
    $result = array('sent' => false, 'notification_id' => 0, 'email' => false);

    if (!pg_ops_alerts_enabled()) {
        return $result;
    }

    $codes = pg_ops_alert_codes();

    if (!isset($codes[$code])) {
        return $result;
    }

    $now = time();

    if (!pg_ops_alert_due(pg_ops_alert_last_sent($code), $now)) {
        return $result;
    }

    $claimed = true;

    $state = pg_ops_alert_state(function ($state) use ($code, $now, &$claimed) {

        if (!pg_ops_alert_due(isset($state['sent'][$code]) ? $state['sent'][$code] : 0, $now)) {
            $claimed = false;
            return $state;
        }

        $state['sent'][$code] = $now;

        return $state;
    });

    // Another process claimed it between the two reads.
    if (($state !== null) && !$claimed) {
        return $result;
    }

    $detail = pg_ops_alert_clean_detail($detail);
    $label = $codes[$code]['label'];

    $result['sent'] = true;

    $result['notification_id'] = (int) create_notification(array(
        'action'      => 'ops',
        'type'        => $codes[$code]['type'],
        // The detail is the row's text; a row needs a title, so an alert with
        // nothing to add carries its own code there, which the bell reads as
        // "no detail" (pg_notification_display()).
        'title'       => ($detail !== '') ? $detail : $code,
        'order_total' => $code,
        'user'        => 'system',
    ));

    // create_notification() queues device banners for three kinds only; this
    // one asks for its own, and only when the operator wants them.
    if (($result['notification_id'] > 0) && defined('OPS_ALERT_PUSH') && OPS_ALERT_PUSH) {

        include_once(PG_FUNCTIONS_DIR . '/includes/push.php');

        if (function_exists('pg_push_enqueue_notification')) {
            pg_push_enqueue_notification(array(
                'id'     => $result['notification_id'],
                'action' => 'ops',
            ));
        }
    }

    if (defined('OPS_ALERT_EMAIL') && OPS_ALERT_EMAIL) {

        $recipients = pg_ops_alert_recipients(
            defined('OPS_ALERT_RECIPIENTS') ? OPS_ALERT_RECIPIENTS : '',
            array(defined('EMAIL_ADDRESS') ? EMAIL_ADDRESS : '')
        );

        if ($recipients) {

            $site = (defined('ORGANIZATION_NAME') && (trim((string) ORGANIZATION_NAME) !== ''))
                ? (string) ORGANIZATION_NAME
                : (string) HOSTNAME_SETTING;

            try {
                $result['email'] = (bool) email(array(
                    'to'                 => $recipients,
                    'from_name'          => $site,
                    'from_email_address' => EMAIL_ADDRESS,
                    'subject'            => lang(array('string' => '{var:1}: {var:2}', 'vars' => array($site, $label))),
                    'format'             => 'html',
                    'body'               => pg_ops_alert_email_html($label, $detail, pg_ops_alert_url($code, true)),
                    'type'               => 'system',
                    'queue'              => empty($codes[$code]['direct']),
                ));
            } catch (\Throwable $e) {
                $result['email'] = false;
            }
        }
    }

    log_activity(lang(array(
        'string' => 'Operations alert: {var:1}. {var:2}',
        'vars'   => array($label, $detail),
    )), 'SYSTEM');

    return $result;
}

/**
 * The HTML body of an alert e-mail. Inline styles only: mail clients drop
 * stylesheets. email() adds the text alternative itself.
 *
 * @param string $label
 * @param string $detail plain text
 * @param string $url    absolute panel address
 * @return string
 */
function pg_ops_alert_email_html($label, $detail, $url)
{
    $paragraph = 'font-size: 14px; margin: 0 0 12px 0;';

    return '
        <p style="font-size: 16px; font-weight: bold; margin: 0 0 12px 0;">' . h($label) . '</p>' . (($detail !== '') ? '
        <p style="' . $paragraph . '">' . h($detail) . '</p>' : '') . '
        <p style="' . $paragraph . '"><a href="' . h($url) . '">' . h(lang('Open it in the panel')) . '</a></p>
        <p style="font-size: 12px; color: #666666; margin: 18px 0 0 0;">' . h(lang('The same alert is sent at most once a day while the problem lasts. Alerts are set under Site Settings, General, Alerts.')) . '</p>';
}

/**
 * The last alert of every code within the last $days days, newest first.
 *
 * @param int $days
 * @return array code => array('timestamp' => int, 'detail' => string, 'label' => string, 'url' => string)
 */
function pg_ops_alert_recent($days = 7)
{
    $codes = pg_ops_alert_codes();
    $since = time() - (max(1, (int) $days) * 86400);

    $rows = db_items(
        "SELECT order_total AS code, title, timestamp
        FROM notifications
        WHERE
            (action = 'ops')
            AND (timestamp >= '" . (int) $since . "')
        ORDER BY timestamp DESC
        LIMIT 200");

    $recent = array();

    foreach ((array) $rows as $row) {

        $code = (string) $row['code'];

        if (isset($recent[$code]) || !isset($codes[$code])) {
            continue;
        }

        $recent[$code] = array(
            'timestamp' => (int) $row['timestamp'],
            'detail'    => ((string) $row['title'] !== $code) ? (string) $row['title'] : '',
            'label'     => $codes[$code]['label'],
            'url'       => $codes[$code]['url'],
        );
    }

    return $recent;
}

// ----------------------------------------------------------------------------
// The checks
// ----------------------------------------------------------------------------

/**
 * Whether the newest backup is too old to be the weekly backup.
 *
 * Eight days: the automatic backup is weekly, so one missed run is a day late
 * and a stopped schedule is a week late. A site that has never been backed up
 * is not told so every day - System Status says it - because that is a
 * setting nobody made, not something that broke.
 *
 * @param int $newest  unix time of the newest backup, 0 for none
 * @param int $now
 * @param int $max_age seconds
 * @return bool
 */
function pg_ops_backup_stale($newest, $now, $max_age = 691200)
{
    return ((int) $newest > 0) && (((int) $now - (int) $newest) > (int) $max_age);
}

/**
 * The scheduled jobs that stopped running, by key.
 *
 * Reads the catalogue's stale_after exactly as System Status does, with three
 * differences that keep an e-mail from repeating something nobody can act on:
 *
 * - The general job comes first. When it has stopped it is the only answer:
 *   the jobs it runs stopped because it did.
 * - A job counts only while somebody still expects it to run: an inline job
 *   when it is switched on (the mail pass always is), a dispatched job when
 *   it is selected for the general job - or, while dispatching is off, when it
 *   must have a schedule of its own.
 * - A job silent for more than a week past its limit has been retired, not
 *   broken; the tile keeps saying so, the alert stops after that week.
 *
 * A job that has never recorded a run is not a failure, as on the tile.
 *
 * @param array $jobs             pg_cron_jobs()
 * @param array $runs             pg_cron_last_runs(): key => unix time
 * @param int   $now
 * @param array $dispatched       pg_cron_dispatch_list()
 * @param bool  $dispatch_enabled JOB_DISPATCH_ENABLED
 * @return array keys of the stopped jobs, in catalogue order
 */
function pg_ops_cron_stalled(array $jobs, array $runs, $now, array $dispatched, $dispatch_enabled)
{
    $retired_after = 604800;

    $silent = function ($key) use ($jobs, $runs, $now, $retired_after) {

        $last = isset($runs[$key]) ? (int) $runs[$key] : 0;
        $limit = isset($jobs[$key]['stale_after']) ? (int) $jobs[$key]['stale_after'] : 0;

        if (($last < 1) || ($limit < 1)) {
            return false;
        }

        $age = (int) $now - $last;

        return ($age > $limit) && ($age <= ($limit + $retired_after));
    };

    if (isset($jobs['job']) && $silent('job')) {
        return array('job');
    }

    $stalled = array();

    foreach ($jobs as $key => $job) {

        if (($key === 'job') || !$silent($key)) {
            continue;
        }

        if (!empty($job['inline'])) {

            // The mail queue and mailbox passes have no switch: job.php runs
            // them on every tick.
            if (!empty($job['dispatch']) && !in_array($key, $dispatched, true)) {
                continue;
            }

        } elseif (!empty($job['dispatch'])) {

            if ($dispatch_enabled && !in_array($key, $dispatched, true)) {
                continue;
            }
        }

        $stalled[] = $key;
    }

    return $stalled;
}

/**
 * Backups: a newest backup more than eight days old.
 *
 * A failed run and a failed remote copy are told by the backup itself, at the
 * moment they happen (auto_backup.php, pg_backup_remote_record()).
 *
 * @param int $now
 * @return string the code of the problem found, '' for none; pg_ops_alert()
 *                decides whether it is told again
 */
function pg_ops_alerts_check_backup($now)
{
    // Switched off in config.php: a decision, not a fault.
    if (defined('SOFTWARE_AUTO_BACKUP') && (SOFTWARE_AUTO_BACKUP == false)) {
        return '';
    }

    $found = pg_backup_newest();

    if (!pg_ops_backup_stale($found['newest'], $now)) {
        return '';
    }

    pg_ops_alert('backup_stale', lang(array(
        'string' => 'The most recent backup was written {var:1}.',
        'vars'   => array(get_relative_time(array('timestamp' => $found['newest'], 'format' => 'plain_text'))),
    )));

    return 'backup_stale';
}

/**
 * Scheduled tasks: the jobs that used to finish and no longer do.
 *
 * Cheap enough for any request - one read of cron_runs - which is why System
 * Status calls it too: the general job cannot report its own death.
 *
 * @param int $now
 * @return string the code of the problem found, '' for none; pg_ops_alert()
 *                decides whether it is told again
 */
function pg_ops_alerts_check_cron($now)
{
    $runs = pg_cron_last_runs();

    if ($runs === null) {
        return '';
    }

    $jobs = pg_cron_jobs();

    $stalled = pg_ops_cron_stalled(
        $jobs,
        $runs,
        $now,
        pg_cron_dispatch_list(),
        (defined('JOB_DISPATCH_ENABLED') && JOB_DISPATCH_ENABLED)
    );

    if (!$stalled) {
        return '';
    }

    $names = array();

    foreach ($stalled as $key) {
        $names[] = lang(array(
            'string' => '{var:1} (last finished {var:2})',
            'vars'   => array(
                $jobs[$key]['label'],
                get_relative_time(array('timestamp' => (int) $runs[$key], 'format' => 'plain_text')),
            ),
        ));
    }

    pg_ops_alert('cron_stalled', lang(array(
        'string' => 'Stopped: {var:1}.',
        'vars'   => array(implode(', ', $names)),
    )));

    return 'cron_stalled';
}

/**
 * Software files: the integrity check finds files that differ from the release.
 *
 * check_directory_file_integrity() reads its paths relative to the working
 * directory, which is the software directory for a panel request and anything
 * at all for a crontab entry, so the check runs from there and the directory
 * is put back. It keeps its own answer for an hour, so asking from every pass
 * hashes the files at most once an hour, as the dashboard already does.
 *
 * @param int $now
 * @return string the code of the problem found, '' for none; pg_ops_alert()
 *                decides whether it is told again
 */
function pg_ops_alerts_check_integrity($now)
{
    // The working tree of a development copy differs from every release.
    if ((defined('ENVIRONMENT') && (ENVIRONMENT === 'development')) || !function_exists('check_directory_file_integrity')) {
        return '';
    }

    // Between an update's file replacement and its database upgrade the new
    // files sit under the old version's number, and the check would compare
    // them with the old release: a false alert that would also use up the
    // day's slot a real one needs.
    if (function_exists('pg_schema_upgrade_pending') && pg_schema_upgrade_pending()) {
        return '';
    }

    $previous = getcwd();

    if (!@chdir(PG_FUNCTIONS_DIR)) {
        return '';
    }

    try {
        $answer = check_directory_file_integrity();
    } finally {
        if ($previous !== false) {
            @chdir($previous);
        }
    }

    if ($answer !== 'missing_or_tampered_files') {
        return '';
    }

    pg_ops_alert('integrity', lang('Files that do not match the released version of the software were found. The list is in the activity log; Repair Software puts the released files back.'));

    return 'integrity';
}

/**
 * The last $bytes of a file, from the first whole line on.
 *
 * @param string $path
 * @param int    $bytes
 * @return string '' when the file cannot be read
 */
function pg_ops_read_tail($path, $bytes)
{
    $size = @filesize($path);

    if (($size === false) || ($size < 1)) {
        return '';
    }

    $handle = @fopen($path, 'rb');

    if ($handle === false) {
        return '';
    }

    $offset = max(0, (int) $size - (int) $bytes);

    fseek($handle, $offset);

    $text = (string) stream_get_contents($handle);

    fclose($handle);

    // The first line of a tail is cut somewhere in its middle.
    if ($offset > 0) {
        $break = strpos($text, "\n");
        $text = ($break === false) ? '' : (string) substr($text, $break + 1);
    }

    return $text;
}

/**
 * How many entries of the PHP error log were written since $since, and the
 * message written most often among them.
 *
 * One entry a line, "[Y-m-d H:i:s] {json}" (pg_error_format_line()), the time
 * in the site's zone - the zone this request runs in as well. A line that does
 * not start that way is not an entry and is skipped.
 *
 * @param string $text
 * @param int    $since unix time
 * @return array array('count' => int, 'top' => string, 'top_count' => int)
 */
function pg_ops_error_lines_since($text, $since)
{
    $found = array('count' => 0, 'top' => '', 'top_count' => 0);
    $messages = array();

    foreach (preg_split('/\r?\n/', (string) $text) as $line) {

        if (preg_match('/^\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\] (.*)$/', $line, $parts) !== 1) {
            continue;
        }

        $stamp = strtotime($parts[1]);

        if (($stamp === false) || ($stamp < (int) $since)) {
            continue;
        }

        $found['count']++;

        $entry = json_decode($parts[2], true);
        $message = (is_array($entry) && isset($entry['message'])) ? trim((string) $entry['message']) : '';

        if ($message === '') {
            continue;
        }

        $key = mb_substr($message, 0, 200, 'UTF-8');
        $messages[$key] = isset($messages[$key]) ? ($messages[$key] + 1) : 1;

        // The first message to reach the highest count wins a tie.
        if ($messages[$key] > $found['top_count']) {
            $found['top'] = $key;
            $found['top_count'] = $messages[$key];
        }
    }

    return $found;
}

/**
 * Errors: more PHP errors in the last hour than the setting allows.
 *
 * The last megabyte of the log, and of its previous file when that was
 * written to within the hour (the log rotates at 5 MB). An hour that wrote
 * more than a megabyte of errors is past any threshold anyway.
 *
 * @param int $now
 * @return string the code of the problem found, '' for none; pg_ops_alert()
 *                decides whether it is told again
 */
function pg_ops_alerts_check_errors($now)
{
    $limit = pg_ops_alert_thresholds()['error_rate'];

    if (($limit < 1) || !function_exists('pg_error_log_path')) {
        return '';
    }

    $since = (int) $now - 3600;
    $path = pg_error_log_path();
    $text = '';

    foreach (array($path . '.1', $path) as $file) {
        if (is_file($file) && ((int) @filemtime($file) >= $since)) {
            $text .= pg_ops_read_tail($file, 1048576) . "\n";
        }
    }

    $found = pg_ops_error_lines_since($text, $since);

    if ($found['count'] < $limit) {
        return '';
    }

    if ($found['top'] !== '') {
        $detail = lang(array(
            'string' => '{var:1} PHP errors were recorded in the last hour. The most frequent ({var:2} times): {var:3}',
            'vars'   => array($found['count'], $found['top_count'], $found['top']),
        ));
    } else {
        $detail = lang(array(
            'string' => '{var:1} PHP errors were recorded in the last hour.',
            'vars'   => array($found['count']),
        ));
    }

    pg_ops_alert('error_spike', $detail);

    return 'error_spike';
}

/**
 * Whether the free space on a disk is too low: under the floor (1 GB) or
 * under the given share of the disk.
 *
 * @param float|int|false $free    bytes, false when unknown
 * @param float|int|false $total   bytes, false when unknown
 * @param int             $percent 0 switches the check off
 * @param float|int       $floor   bytes
 * @return bool
 */
function pg_ops_disk_low($free, $total, $percent, $floor = 1073741824)
{
    if (((int) $percent < 1) || !is_numeric($free) || ((float) $free < 0)) {
        return false;
    }

    if ((float) $free < (float) $floor) {
        return true;
    }

    return is_numeric($total) && ((float) $total > 0) && (((float) $free * 100) < ((float) $total * (int) $percent));
}

/**
 * Disk: the disk that holds data/ (backups, uploads, the database dump of a
 * backup run, the error log) is running out.
 *
 * disk_free_space() is among the functions shared hosts switch off; then
 * there is nothing to read and nothing is said.
 *
 * @param int $now
 * @return string the code of the problem found, '' for none; pg_ops_alert()
 *                decides whether it is told again
 */
function pg_ops_alerts_check_disk($now)
{
    $percent = pg_ops_alert_thresholds()['disk_percent'];

    if (($percent < 1) || !function_exists('disk_free_space') || !function_exists('disk_total_space')) {
        return '';
    }

    $free = @disk_free_space(PG_FUNCTIONS_DIR . '/data');
    $total = @disk_total_space(PG_FUNCTIONS_DIR . '/data');

    if (!pg_ops_disk_low($free, $total, $percent)) {
        return '';
    }

    pg_ops_alert('disk_low', lang(array(
        'string' => '{var:1} free of {var:2} on the disk that holds the site\'s data.',
        'vars'   => array(convert_bytes_to_string((float) $free, 1), is_numeric($total) ? convert_bytes_to_string((float) $total, 1) : '?'),
    )));

    return 'disk_low';
}

/**
 * Mail: messages of the last day that the queue could not send - given up,
 * or failed at least once and waiting to be tried again.
 *
 * Only the queue keeps a record of a failure: a message email() sent at once
 * (the general job not running) failed in the request that sent it.
 *
 * @param int $now
 * @return string the code of the problem found, '' for none; pg_ops_alert()
 *                decides whether it is told again
 */
function pg_ops_alerts_check_mail($now)
{
    $limit = pg_ops_alert_thresholds()['mail_failures'];

    if (($limit < 1) || !function_exists('pg_mail_queue_ready') || !pg_mail_queue_ready()) {
        return '';
    }

    $row = db_item(
        "SELECT COUNT(*) AS total, MAX(id) AS last_id
        FROM mail_outbox
        WHERE
            (attempts > 0)
            AND (status IN ('queued', 'failed'))
            AND (created_at >= '" . ((int) $now - 86400) . "')");

    $total = is_array($row) ? (int) $row['total'] : 0;

    if ($total < $limit) {
        return '';
    }

    $error = (string) db_value("SELECT last_error FROM mail_outbox WHERE id = '" . (int) $row['last_id'] . "'");

    pg_ops_alert('mail_failures', lang(array(
        'string' => '{var:1} e-mail(s) of the last day could not be sent. The last error: {var:2}',
        'vars'   => array($total, ($error !== '') ? $error : '-'),
    )));

    return 'mail_failures';
}

/**
 * Webhooks: an event address that stopped answering and is no longer tried.
 *
 * The dispatcher marks a subscription failing after six failures in a row and
 * stops sending to it; from outside that looks like a quiet week.
 *
 * @param int $now
 * @return string the code of the problem found, '' for none; pg_ops_alert()
 *                decides whether it is told again
 */
function pg_ops_alerts_check_webhooks($now)
{
    if (!pg_schema_has('api_webhooks')) {
        return '';
    }

    $total = (int) db_value("SELECT COUNT(*) FROM api_webhooks WHERE status = 'failing'");

    if ($total < 1) {
        return '';
    }

    $urls = db_values("SELECT url FROM api_webhooks WHERE status = 'failing' ORDER BY id ASC LIMIT 3");

    pg_ops_alert('webhook_failing', lang(array(
        'string' => '{var:1} event address(es) stopped answering and are no longer sent to: {var:2}',
        'vars'   => array($total, implode(', ', (array) $urls)),
    )));

    return 'webhook_failing';
}

/**
 * The host whose certificate is checked: the site's host name without its
 * port, lower case. '' for a name that has no public certificate to check -
 * an IP address, localhost, a name without a dot.
 *
 * @param string $hostname HOSTNAME_SETTING
 * @return string
 */
function pg_ops_certificate_host($hostname)
{
    $host = strtolower(trim((string) $hostname));

    if (($host === '') || (strpos($host, '[') === 0)) {
        return '';
    }

    $colon = strrpos($host, ':');

    if ($colon !== false) {
        $host = substr($host, 0, $colon);
    }

    if ((filter_var($host, FILTER_VALIDATE_IP) !== false) || (strpos($host, '.') === false) || (substr($host, -10) === '.localhost')) {
        return '';
    }

    return (preg_match('/^[a-z0-9.-]+$/', $host) === 1) ? $host : '';
}

/**
 * What a certificate's end date means at $now: 'ok', 'expiring' (within
 * $days), 'expired', or 'unknown' when it could not be read.
 *
 * @param int $ends unix time, 0 for unknown
 * @param int $now
 * @param int $days
 * @return string
 */
function pg_ops_certificate_state($ends, $now, $days)
{
    $ends = (int) $ends;

    if ($ends < 1) {
        return 'unknown';
    }

    if ($ends <= (int) $now) {
        return 'expired';
    }

    return (($ends - (int) $now) < ((int) $days * 86400)) ? 'expiring' : 'ok';
}

/**
 * When the certificate the site's host name answers with runs out.
 *
 * The host is checked the way every outgoing call is (no private or reserved
 * address) and the connection goes to the address that was checked. The
 * certificate is read, not trusted: one that ran out is reported as such
 * rather than as a failed connection. The same reading as the Workspace's
 * web check (ws_scheduled_ssl_check()), which needs the Workspace loaded.
 *
 * @param string $host
 * @return array array('ok' => bool, 'ends' => int, 'error' => string)
 */
function pg_ops_certificate_end($host)
{
    $result = array('ok' => false, 'ends' => 0, 'error' => '');

    if (!function_exists('stream_socket_client') || !function_exists('openssl_x509_parse')) {
        $result['error'] = 'openssl';
        return $result;
    }

    if (!function_exists('api_http_check_url')) {
        require_once(PG_FUNCTIONS_DIR . '/includes/api/outbound/http.php');
    }

    $check = api_http_check_url('https://' . $host . '/');

    if (!$check['ok']) {
        $result['error'] = (string) $check['error'];
        return $result;
    }

    $context = stream_context_create(array('ssl' => array(
        'capture_peer_cert' => true,
        'verify_peer'       => false,
        'verify_peer_name'  => false,
        'SNI_enabled'       => true,
        'peer_name'         => $host,
    )));

    $errno = 0;
    $errstr = '';
    $address = (filter_var($check['ip'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false) ? '[' . $check['ip'] . ']' : $check['ip'];

    $socket = @stream_socket_client('ssl://' . $address . ':443', $errno, $errstr, 10, STREAM_CLIENT_CONNECT, $context);

    if (!$socket) {
        $result['error'] = ($errstr !== '') ? $errstr : (string) $errno;
        return $result;
    }

    $params = stream_context_get_params($socket);
    fclose($socket);

    $certificate = isset($params['options']['ssl']['peer_certificate']) ? $params['options']['ssl']['peer_certificate'] : null;
    $parsed = $certificate ? @openssl_x509_parse($certificate) : false;

    if (!is_array($parsed) || empty($parsed['validTo_time_t'])) {
        $result['error'] = 'no certificate';
        return $result;
    }

    $result['ok'] = true;
    $result['ends'] = (int) $parsed['validTo_time_t'];

    return $result;
}

/**
 * Certificate: the site is served over https and its certificate runs out
 * within the days the setting gives, or already has.
 *
 * The certificate is read once a day (and again when the host name changes);
 * the passes in between judge the end date kept in the state file. A reading
 * that fails says nothing: a firewall that blocks the server from calling its
 * own address is not an expiring certificate.
 *
 * @param int $now
 * @return string the code of the problem found, '' for none; pg_ops_alert()
 *                decides whether it is told again
 */
function pg_ops_alerts_check_certificate($now)
{
    $days = pg_ops_alert_thresholds()['cert_days'];

    if (($days < 1) || !defined('URL_SCHEME') || (URL_SCHEME !== 'https://')) {
        return '';
    }

    $host = pg_ops_certificate_host(defined('HOSTNAME_SETTING') ? HOSTNAME_SETTING : '');

    if ($host === '') {
        return '';
    }

    $state = pg_ops_alert_state();
    $known = (is_array($state) && isset($state['cert']) && is_array($state['cert'])) ? $state['cert'] : array();

    $fresh = isset($known['host'], $known['checked_at'], $known['ends'])
        && ((string) $known['host'] === $host)
        && !pg_ops_alert_due((int) $known['checked_at'], $now);

    if (!$fresh) {

        $read = pg_ops_certificate_end($host);

        $known = array(
            'host'       => $host,
            'checked_at' => (int) $now,
            'ends'       => $read['ok'] ? $read['ends'] : 0,
        );

        pg_ops_alert_state(function ($state) use ($known) {
            $state['cert'] = $known;
            return $state;
        });
    }

    $verdict = pg_ops_certificate_state($known['ends'], $now, $days);

    if (($verdict !== 'expiring') && ($verdict !== 'expired')) {
        return '';
    }

    $date = date('d.m.Y', (int) $known['ends']);

    pg_ops_alert('cert_expiring', ($verdict === 'expired')
        ? lang(array('string' => 'The certificate of {var:1} ran out on {var:2}', 'vars' => array($host, $date)))
        : lang(array('string' => 'The certificate of {var:1} ends in {var:2} days ({var:3})', 'vars' => array($host, (int) floor(((int) $known['ends'] - (int) $now) / 86400), $date))));

    return 'cert_expiring';
}

/**
 * The checks the general job runs, in order.
 *
 * @return array name => callable taking the current time, returning the code found
 */
function pg_ops_alert_sources()
{
    return array(
        'backup'      => 'pg_ops_alerts_check_backup',
        'cron'        => 'pg_ops_alerts_check_cron',
        'integrity'   => 'pg_ops_alerts_check_integrity',
        'errors'      => 'pg_ops_alerts_check_errors',
        'disk'        => 'pg_ops_alerts_check_disk',
        'mail'        => 'pg_ops_alerts_check_mail',
        'webhooks'    => 'pg_ops_alerts_check_webhooks',
        // Last: the only one that may wait on the network, for up to ten
        // seconds once a day.
        'certificate' => 'pg_ops_alerts_check_certificate',
    );
}

/**
 * Run every check, at most once every ten minutes and never twice at once.
 *
 * Called by the general job on every tick; with alerts off, or within the ten
 * minutes, it is one constant read and one small file read. A check that
 * throws is logged and skipped, so one broken source cannot silence the
 * others or end the general job's tick.
 *
 * @param bool $force run now, whatever the last pass
 * @return array array('ran' => bool, 'found' => array of codes, told or not)
 */
function pg_ops_alerts_run($force = false)
{
    $result = array('ran' => false, 'found' => array());

    if (!pg_ops_alerts_enabled()) {
        return $result;
    }

    $now = time();

    $state = pg_ops_alert_state();

    if (!$force && is_array($state) && ($state['pass'] > 0) && ($state['pass'] <= $now) && (($now - $state['pass']) < 600)) {
        return $result;
    }

    // A tick that overlaps a slow one (the integrity check hashing every file)
    // skips the pass rather than queueing behind it.
    $lock_path = PG_FUNCTIONS_DIR . '/data/temp/ops_alerts.lock';
    $lock = @fopen($lock_path, 'c');

    if (($lock === false) || !@flock($lock, LOCK_EX | LOCK_NB)) {
        if ($lock !== false) {
            fclose($lock);
        }
        return $result;
    }

    pg_ops_alert_state(function ($state) use ($now) {
        $state['pass'] = $now;
        return $state;
    });

    $result['ran'] = true;

    foreach (pg_ops_alert_sources() as $name => $check) {

        try {
            $found = (string) call_user_func($check, $now);
        } catch (\Throwable $e) {
            log_activity(lang(array(
                'string' => 'The operations alert check {var:1} failed: {var:2}',
                'vars'   => array($name, $e->getMessage()),
            )), 'SYSTEM');
            continue;
        }

        if ($found !== '') {
            $result['found'][] = $found;
        }
    }

    flock($lock, LOCK_UN);
    fclose($lock);

    return $result;
}
