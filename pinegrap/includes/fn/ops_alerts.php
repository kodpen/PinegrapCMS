<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Operations alerts: the failures that otherwise surface only when somebody
 * opens System Status or the logs - a failed or missing backup, a scheduled
 * task that stopped, software files that no longer match the release - told
 * to the people who run the site on the panel bell, by e-mail and on the
 * devices they subscribed.
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

            // The mail pass has no switch: job.php runs it on every tick.
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
 * The checks the general job runs, in order.
 *
 * @return array name => callable taking the current time, returning the code found
 */
function pg_ops_alert_sources()
{
    return array(
        'backup'    => 'pg_ops_alerts_check_backup',
        'cron'      => 'pg_ops_alerts_check_cron',
        'integrity' => 'pg_ops_alerts_check_integrity',
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
