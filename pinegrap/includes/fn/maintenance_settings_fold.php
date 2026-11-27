<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Site Settings > Updates and maintenance as folded rows
 * (includes/settings/fold.php): the rows' order, names and icons, and each
 * part's one line of state with its pill. The pg_maintenance_fold_<part>()
 * lines are pure but for lang() and date(); includes/settings/maintenance.php
 * reads the facts they take.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/**
 * The rows' keys in reading order. A key is its card's id without pgset-.
 *
 * @return string[]
 */
function pg_maintenance_fold_order()
{
    return array('channel', 'maintenance', 'cron', 'backup', 'storage', 'alerts', 'sales-watch', 'chat-notify');
}

/**
 * A row's name, icon and tone.
 *
 * @param string $key one of pg_maintenance_fold_order()
 * @return array name, icon, tone
 */
function pg_maintenance_fold_face($key)
{
    switch ($key) {
        case 'channel':
            return array('name' => lang('Update channel'), 'icon' => 'bi-arrow-repeat', 'tone' => 'pg-tone-blue');
        case 'maintenance':
            return array('name' => lang('Maintenance mode'), 'icon' => 'bi-cone-striped', 'tone' => 'pg-tone-amber');
        case 'cron':
            return array('name' => lang('Scheduled jobs'), 'icon' => 'bi-clock-history', 'tone' => 'pg-tone-violet');
        case 'backup':
            return array('name' => lang('Backups'), 'icon' => 'bi-archive', 'tone' => 'pg-tone-teal');
        case 'storage':
            return array('name' => lang('Storage'), 'icon' => 'bi-hdd-stack', 'tone' => 'pg-tone-system');
        case 'alerts':
            return array('name' => lang('Operations alerts'), 'icon' => 'bi-bell', 'tone' => 'pg-tone-rose');
        case 'sales-watch':
            return array('name' => lang('Sales watch'), 'icon' => 'bi-graph-up-arrow', 'tone' => 'pg-tone-shop');
        default:
            return array('name' => lang('Chat notifications'), 'icon' => 'bi-chat-dots', 'tone' => '');
    }
}

/**
 * A time as a row says it: the clock alone today, the day too otherwise.
 *
 * @param int $at
 * @param int $now
 * @return string
 */
function pg_maintenance_fold_time($at, $now)
{
    return (date('Y-m-d', (int) $at) === date('Y-m-d', (int) $now)) ? date('H:i', (int) $at) : date('d.m.Y H:i', (int) $at);
}

/**
 * The line of a part whose columns the upgrade has not added yet.
 *
 * @return array line, pill, attention
 */
function pg_maintenance_fold_not_ready()
{
    return array('line' => lang('Ready once the upgrade has run'), 'pill' => null, 'attention' => false);
}

/**
 * The Update channel row: Stable or Beta, then whether an update backs up
 * first.
 *
 * @param string $channel      stable or beta
 * @param bool   $backup_ready the update_backup column exists
 * @param bool   $backup       Back up before updating
 * @return array line, pill, attention
 */
function pg_maintenance_fold_channel($channel, $backup_ready, $backup)
{
    $beta = ($channel === 'beta');
    $line = $beta ? lang('Beta') : lang('Stable');

    if ($backup_ready && $backup) {
        $line .= ' · ' . lang('backs up before updating');
    }

    return array('line' => $line, 'pill' => $beta ? array(lang('Beta'), 'pg-tone-amber') : null, 'attention' => false);
}

/**
 * The Maintenance mode row: Off, or On (until its end time) with an amber
 * pill. A switch left on past its end time no longer holds the site.
 *
 * @param bool $ready the maintenance columns exist
 * @param bool $on    the switch
 * @param int  $until the end time, 0 for none
 * @param int  $now
 * @return array line, pill, attention
 */
function pg_maintenance_fold_mode($ready, $on, $until, $now)
{
    if (!$ready) {
        return pg_maintenance_fold_not_ready();
    }

    $until = (int) $until;

    if (!$on) {
        return array('line' => lang('Off'), 'pill' => null, 'attention' => false);
    }

    // As pg_maintenance_holds(): no end time, or one still ahead.
    if (($until > 0) && ((int) $now >= $until)) {
        return array(
            'line'      => lang(array('string' => 'Ended at {var:1}: visitors see the site', 'vars' => pg_maintenance_fold_time($until, $now))),
            'pill'      => null,
            'attention' => false,
        );
    }

    return array(
        'line'      => ($until > 0) ? lang(array('string' => 'On until {var:1}', 'vars' => pg_maintenance_fold_time($until, $now))) : lang('On'),
        'pill'      => array(lang('On'), 'pg-tone-amber'),
        'attention' => false,
    );
}

/**
 * How many of the jobs chosen to run with the general job can run with it.
 * Pure.
 *
 * @param string[] $selection the job names saved in job_dispatch
 * @param array    $jobs      pg_cron_jobs()
 * @param string[] $inactive  names config.php has switched off, which the
 *                            dispatcher passes over (pg_cron_job_active())
 * @return int
 */
function pg_maintenance_fold_cron_count($selection, $jobs, $inactive = array())
{
    $count = 0;

    foreach (array_unique(array_map('strval', (array) $selection)) as $name) {
        if (isset($jobs[$name]) && !empty($jobs[$name]['dispatch']) && !in_array($name, (array) $inactive, true)) {
            $count++;
        }
    }

    return $count;
}

/**
 * The Scheduled jobs row: whether the general job runs, and how many jobs
 * run with it. A general job that never ran, or stopped, asks for attention.
 *
 * @param int  $ran_at      when the general job last finished, 0 for never
 * @param int  $now
 * @param int  $stale_after seconds after which it counts as stopped
 * @param bool $master      Run the jobs with the general job
 * @param int  $count       pg_maintenance_fold_cron_count()
 * @return array line, pill, attention
 */
function pg_maintenance_fold_cron($ran_at, $now, $stale_after, $master, $count)
{
    $ran_at = (int) $ran_at;

    if ($ran_at < 1) {
        return array(
            'line'      => lang('Not scheduled: nothing runs on its own'),
            'pill'      => array(lang('Not scheduled'), 'pg-tone-warning'),
            'attention' => true,
        );
    }

    if (((int) $now - $ran_at) > (int) $stale_after) {
        return array(
            'line'      => lang(array('string' => 'Stopped: the general job last ran {var:1}', 'vars' => pg_maintenance_fold_time($ran_at, $now))),
            'pill'      => array(lang('Stopped'), 'pg-tone-rose'),
            'attention' => true,
        );
    }

    $count = $master ? (int) $count : 0;

    if ($count === 0) {
        $with = lang('no job with it');
    } else {
        $with = lang(array('string' => '{var:1} job{suffix:1} with it', 'vars' => $count, 'suffix' => array(($count === 1) ? '' : 's')));
    }

    return array('line' => lang('Runs every minute') . ' · ' . $with, 'pill' => array(lang('Running'), 'pg-tone-teal'), 'attention' => false);
}

/**
 * The name of a remote copy's destination, or of the server itself.
 *
 * @param string $type '' or a backup_remote_type
 * @return string
 */
function pg_maintenance_fold_backup_place($type)
{
    $places = array(
        'ftp'      => 'FTP',
        's3'       => lang('S3-compatible storage'),
        'gdrive'   => 'Google Drive',
        'onedrive' => 'OneDrive',
        'dropbox'  => 'Dropbox',
    );

    return isset($places[$type]) ? $places[$type] : lang('On this server');
}

/**
 * The Backups row: where the copy goes and when the last backup was taken.
 * No backup yet, or a failed remote copy, asks for attention.
 *
 * @param string $type   the saved remote copy, '' for none
 * @param string $error  the last remote copy's error, '' for none
 * @param int    $newest the newest backup's time, 0 for none (pg_backup_newest())
 * @param int    $now
 * @return array line, pill, attention
 */
function pg_maintenance_fold_backup($type, $error, $newest, $now)
{
    $place = pg_maintenance_fold_backup_place((string) $type);

    if ((int) $newest < 1) {
        return array(
            'line'      => lang('No backup yet') . ' · ' . $place,
            'pill'      => array(lang('No backup'), 'pg-tone-warning'),
            'attention' => true,
        );
    }

    $line = $place . ' · ' . lang(array('string' => 'last backup {var:1}', 'vars' => pg_maintenance_fold_time($newest, $now)));

    if (((string) $type !== '') && ((string) $error !== '')) {
        return array('line' => $line, 'pill' => array(lang('Copy failed'), 'pg-tone-rose'), 'attention' => true);
    }

    return array('line' => $line, 'pill' => null, 'attention' => false);
}

/**
 * The Storage row: where uploaded files are, or how many a move still has
 * to carry. A failed last "Save and test" asks for attention.
 *
 * @param bool       $used      a saved store that is switched on
 * @param string     $bucket    its bucket
 * @param string     $direction to, back or '' (pg_file_storage_mode())
 * @param bool       $paused    the move is paused
 * @param int        $left      files still to carry the way the move goes
 * @param bool|null  $test_ok   the last test's result, null for none
 * @return array line, pill, attention
 */
function pg_maintenance_fold_storage($used, $bucket, $direction, $paused, $left, $test_ok)
{
    $left = max(0, (int) $left);
    $count = function_exists('pg_format_number') ? pg_format_number($left, 0) : number_format($left);
    $line = lang('Files on the disk');

    if ($used && ($direction === 'to')) {
        if ($left > 0) {
            $line = $paused
                ? lang(array('string' => 'Moving files paused: {var:1} left', 'vars' => $count))
                : lang(array('string' => 'Moving files: {var:1} left', 'vars' => $count));
        } else {
            $line = lang(array('string' => 'In the bucket {var:1}', 'vars' => (string) $bucket));
        }
    } elseif ($used && ($direction === 'back') && ($left > 0)) {
        $line = $paused
            ? lang(array('string' => 'Bringing files back paused: {var:1} left', 'vars' => $count))
            : lang(array('string' => 'Bringing files back: {var:1} left', 'vars' => $count));
    }

    if ($test_ok === false) {
        return array('line' => $line, 'pill' => array(lang('Test failed'), 'pg-tone-rose'), 'attention' => true);
    }

    return array('line' => $line, 'pill' => null, 'attention' => false);
}

/**
 * How many addresses the alert recipients box holds; empty means the site
 * e-mail address alone. Pure.
 *
 * @param string $recipients comma-separated
 * @return int
 */
function pg_maintenance_fold_recipient_count($recipients)
{
    $count = 0;

    foreach (explode(',', (string) $recipients) as $address) {
        if (trim($address) !== '') {
            $count++;
        }
    }

    return max(1, $count);
}

/**
 * The Operations alerts row: Off, or the ways an alert goes out. The bell
 * is no choice, so an alert with neither e-mail nor devices still rings it.
 *
 * @param bool   $ready      the alert columns exist
 * @param bool   $on         Operations alerts
 * @param bool   $email      Send alerts by e-mail
 * @param string $recipients Alert recipients
 * @param bool   $push       Send alerts to subscribed devices
 * @return array line, pill, attention
 */
function pg_maintenance_fold_alerts($ready, $on, $email, $recipients, $push)
{
    if (!$ready) {
        return pg_maintenance_fold_not_ready();
    }

    if (!$on) {
        return array('line' => lang('Off'), 'pill' => array(lang('Off'), 'pg-tone-gray'), 'attention' => false);
    }

    $count = pg_maintenance_fold_recipient_count($recipients);
    $mail = lang(array('string' => 'By e-mail to {var:1} address{suffix:1}', 'vars' => $count, 'suffix' => array(($count === 1) ? '' : 'es')));

    if ($email && $push) {
        $line = $mail . ' ' . lang('and to devices');
    } elseif ($email) {
        $line = $mail;
    } elseif ($push) {
        $line = lang('To devices');
    } else {
        $line = lang('On the bell only');
    }

    return array('line' => $line, 'pill' => array(lang('On'), 'pg-tone-teal'), 'attention' => false);
}

/**
 * The last day of the whole store's pause that holds at $now, 0 for none.
 * Pure.
 *
 * @param array $settings pg_sales_watch_settings_normalize()
 * @param int   $now
 * @return int a time on that day
 */
function pg_maintenance_fold_sales_pause_day($settings, $now)
{
    $until = isset($settings['paused']['store']) ? (int) $settings['paused']['store'] : 0;

    // A pause ends at the midnight after its last day.
    return ((int) $now < $until) ? $until - 1 : 0;
}

/**
 * The Sales watch row: off with Operations alerts, paused, or watching at
 * its sensitivity with how many channels are paused.
 *
 * @param bool  $alerts   Operations alerts
 * @param array $settings pg_sales_watch_settings_normalize()
 * @param int   $now
 * @return array line, pill, attention
 */
function pg_maintenance_fold_sales($alerts, $settings, $now)
{
    if (!$alerts) {
        return array('line' => lang('Off with Operations alerts'), 'pill' => array(lang('Off'), 'pg-tone-gray'), 'attention' => false);
    }

    $any = false;

    foreach (pg_sales_watch_alert_names() as $name) {
        $any = $any || !empty($settings[$name]);
    }

    if (!$any) {
        return array('line' => lang('Off'), 'pill' => array(lang('Off'), 'pg-tone-gray'), 'attention' => false);
    }

    $day = pg_maintenance_fold_sales_pause_day($settings, $now);

    if ($day > 0) {
        return array(
            'line'      => lang(array('string' => 'Paused until {var:1}', 'vars' => date('d.m.Y', $day))),
            'pill'      => array(lang('Paused'), 'pg-tone-amber'),
            'attention' => false,
        );
    }

    $levels = array('low' => lang('Watching, low sensitivity'), 'high' => lang('Watching, high sensitivity'));
    $line = isset($levels[$settings['level'] ?? '']) ? $levels[$settings['level']] : lang('Watching, normal sensitivity');
    $paused = 0;

    foreach ((array) ($settings['paused'] ?? array()) as $key => $until) {
        if (((string) $key !== 'store') && ((int) $now < (int) $until)) {
            $paused++;
        }
    }

    if ($paused > 0) {
        $line .= ' · ' . lang(array('string' => '{var:1} channel{suffix:1} paused', 'vars' => $paused, 'suffix' => array(($paused === 1) ? '' : 's')));
    }

    return array('line' => $line, 'pill' => array(lang('Watching'), 'pg-tone-teal'), 'attention' => false);
}

/**
 * The Chat notifications row: the targets by name, at most three, or None.
 *
 * @param array $targets pg_notify_targets()
 * @param array $kinds   pg_notify_channel_kinds(): kind => label
 * @return array line, pill, attention
 */
function pg_maintenance_fold_chat($targets, $kinds)
{
    $names = array();

    foreach ((array) $targets as $target) {
        if (!is_array($target)) {
            continue;
        }

        $name = trim((string) ($target['label'] ?? ''));
        $kind = (string) ($target['kind'] ?? '');

        if (($name === '') && isset($kinds[$kind]['label'])) {
            $name = (string) $kinds[$kind]['label'];
        }

        if ($name !== '') {
            $names[] = $name;
        }
    }

    if (!$names) {
        return array('line' => lang('None'), 'pill' => null, 'attention' => false);
    }

    $line = implode(', ', array_slice($names, 0, 3));

    if (count($names) > 3) {
        $line .= ' ' . lang(array('string' => 'and {var:1} more', 'vars' => count($names) - 3));
    }

    return array('line' => $line, 'pill' => null, 'attention' => false);
}
