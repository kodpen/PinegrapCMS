<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The operator's notice of an automatic firewall ban: an e-mail for each ban
 * with the rule, the address, a few of the requests that brought it and a
 * link to the firewall log filtered by the address, and a single notice
 * instead of one e-mail per address when one rule bans several addresses
 * within an hour.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// A module of functions.php. waf.php runs before the database and this file,
// so waf_register_offence() only queues a line (waf_ban_notice_queue());
// pg_waf_ban_notice_send() sends what is queued, from init.php on the next
// request and on every tick of the general job, through the site's mail
// queue to the recipients of the operations alerts.
if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/**
 * Where waf_ban_notice_queue() appends.
 *
 * @return string
 */
function pg_waf_ban_notice_pending_path()
{
    return PG_FUNCTIONS_DIR . '/data/temp/waf_ban_notice.log';
}

/**
 * Where the bans of the last hour are remembered, per rule, for the burst
 * notice. A cache: Cleanup may delete it, which at worst sends one more
 * e-mail.
 *
 * @return string
 */
function pg_waf_ban_notice_state_path()
{
    return PG_FUNCTIONS_DIR . '/data/temp/waf_ban_notice_state.json';
}

/**
 * Whether ban notices are mailed: the operations alerts' e-mail switch, so
 * the operator turns them on, and chooses who receives them, in one place
 * (Site Settings, General, Alerts).
 *
 * @return bool
 */
function pg_waf_ban_notice_enabled()
{
    return function_exists('pg_ops_alerts_enabled') && pg_ops_alerts_enabled()
        && defined('OPS_ALERT_EMAIL') && OPS_ALERT_EMAIL;
}

/**
 * Decide which queued bans are mailed.
 *
 * The first ban of a rule within an hour is mailed on its own. A second
 * address banned by the same rule within the hour is a burst: one notice names
 * every address the rule banned in that hour, and no further e-mail is sent
 * for the rule until an hour after that notice. Bans drained together are
 * judged in the order they happened, and the burst notice of a run collects
 * the rest of the run's bans of its rule.
 *
 * @param array $entries waf_ban_notice_parse() results
 * @param array $state   the previous run's state ('rules' => rule =>
 *                       array('bans' => array(array(time, subject)), 'burst' => time))
 * @param int   $now
 * @return array array('notices' => list of array('kind' => 'ban', 'entry')
 *               or array('kind' => 'burst', 'rule', 'subjects'), 'state')
 */
function pg_waf_ban_notice_plan(array $entries, array $state, $now)
{
    $window = 3600;
    $keep = 100;

    $rules = array();

    foreach ((isset($state['rules']) && is_array($state['rules'])) ? $state['rules'] : array() as $rule => $data) {

        $bans = array();

        foreach ((isset($data['bans']) && is_array($data['bans'])) ? $data['bans'] : array() as $ban) {
            if (is_array($ban) && isset($ban[0], $ban[1]) && (int) $ban[0] > $now - $window) {
                $bans[] = array((int) $ban[0], (string) $ban[1]);
            }
        }

        $burst = isset($data['burst']) ? (int) $data['burst'] : 0;

        if ($bans || $burst > $now - $window) {
            $rules[(string) $rule] = array('bans' => $bans, 'burst' => ($burst > $now - $window) ? $burst : 0);
        }
    }

    usort($entries, function ($a, $b) {
        return $a['when'] - $b['when'];
    });

    $notices = array();
    $burst_notice = array();

    foreach ($entries as $entry) {

        $rule = $entry['rule'];
        $data = isset($rules[$rule]) ? $rules[$rule] : array('bans' => array(), 'burst' => 0);

        $recent = array();

        foreach ($data['bans'] as $ban) {
            if ($ban[0] > $entry['when'] - $window && !in_array($ban[1], $recent, true)) {
                $recent[] = $ban[1];
            }
        }

        if (isset($burst_notice[$rule])) {

            $subjects = &$notices[$burst_notice[$rule]]['subjects'];

            if (count($subjects) < $keep && !in_array($entry['subject'], $subjects, true)) {
                $subjects[] = $entry['subject'];
            }

            unset($subjects);

        } elseif (!$recent) {
            $notices[] = array('kind' => 'ban', 'entry' => $entry);

        } elseif ($data['burst'] <= $entry['when'] - $window) {

            if (!in_array($entry['subject'], $recent, true)) {
                $recent[] = $entry['subject'];
            }

            $notices[] = array('kind' => 'burst', 'rule' => $rule, 'subjects' => array_slice($recent, 0, $keep));
            $burst_notice[$rule] = count($notices) - 1;
            $data['burst'] = $entry['when'];
        }

        $data['bans'][] = array($entry['when'], $entry['subject']);
        $data['bans'] = array_slice($data['bans'], -$keep);

        $rules[$rule] = $data;
    }

    return array('notices' => $notices, 'state' => array('rules' => $rules));
}

/**
 * What the firewall log is searched for to show a banned address or range:
 * the address itself, or the part of a range's address its prefix keeps,
 * which the log's search matches as a substring.
 *
 * @param string $entry an address or a CIDR range
 * @return string
 */
function pg_waf_ban_log_search($entry)
{
    $entry = trim((string) $entry);
    $slash = strpos($entry, '/');

    if ($slash === false) {
        return $entry;
    }

    $address = substr($entry, 0, $slash);
    $bits = (int) substr($entry, $slash + 1);

    if (strpos($address, ':') !== false) {
        $kept = rtrim($address, ':');

        return ($kept === '') ? $address : $kept . ':';
    }

    $octets = array_slice(explode('.', $address), 0, intdiv(max(0, min(32, $bits)), 8));

    return $octets ? implode('.', $octets) . '.' : $address;
}

/**
 * The firewall log filtered by a banned address or range, relative to the
 * software directory.
 *
 * @param string $entry an address or a CIDR range
 * @return string
 */
function pg_waf_ban_log_url($entry)
{
    return 'view_waf_log.php?range=7&search=' . rawurlencode(pg_waf_ban_log_search($entry));
}

/**
 * A panel address relative to the software directory, made absolute on the
 * site's own host name: the link leaves the request in an e-mail.
 *
 * @param string $url
 * @return string
 */
function pg_waf_ban_notice_absolute_url($url)
{
    return URL_SCHEME . HOSTNAME_SETTING . PATH . SOFTWARE_DIRECTORY . '/' . $url;
}

/**
 * The last firewall events of the banned address before its ban, newest
 * first: what the operator needs to tell an attack from a page full of
 * images.
 *
 * @param string $ip
 * @param int    $when the time of the ban
 * @return array list of array('when', 'rule', 'method', 'url', 'hits')
 */
function pg_waf_ban_notice_samples($ip, $when)
{
    $rows = db_items(
        "SELECT rule_id, method, request_url, hit_count, last_seen
        FROM waf_log
        WHERE
            (ip_address = '" . escape($ip) . "')
            AND (last_seen >= " . ((int) $when - 900) . ")
            AND (last_seen <= " . ((int) $when + 60) . ")
            AND (action <> 'ban')
        ORDER BY last_seen DESC
        LIMIT 5");

    $samples = array();

    foreach ((array) $rows as $row) {
        $samples[] = array(
            'when'   => (int) $row['last_seen'],
            'rule'   => (string) $row['rule_id'],
            'method' => (string) $row['method'],
            'url'    => (string) $row['request_url'],
            'hits'   => (int) $row['hit_count'],
        );
    }

    return $samples;
}

/**
 * The HTML body of the e-mail for one ban. Inline styles only: mail clients
 * drop stylesheets. email() adds the text alternative itself.
 *
 * @param array  $entry   waf_ban_notice_parse() result
 * @param array  $samples pg_waf_ban_notice_samples() result
 * @param string $log_url absolute address of the filtered firewall log
 * @return string
 */
function pg_waf_ban_notice_email_html(array $entry, array $samples, $log_url)
{
    $paragraph = 'font-size: 14px; margin: 0 0 12px 0;';

    $html = '
        <p style="font-size: 16px; font-weight: bold; margin: 0 0 12px 0;">' . h(lang('The firewall banned an address')) . '</p>
        <p style="' . $paragraph . '">' . lang(array(
            'string' => '{var:1} was banned for {var:2} minute(s) by the rule {var:3}.',
            'vars'   => array(h($entry['subject']), (int) $entry['minutes'], h($entry['rule'])),
        )) . '</p>';

    if ($entry['subject'] !== $entry['ip']) {
        $html .= '
        <p style="' . $paragraph . '">' . lang(array(
            'string' => 'The address that was refused: {var:1}',
            'vars'   => array(h($entry['ip'])),
        )) . '</p>';
    }

    if ($samples) {

        $html .= '
        <p style="' . $paragraph . '">' . h(lang('The last requests before the ban:')) . '</p>
        <ul style="font-size: 13px; margin: 0 0 12px 0; padding-left: 18px;">';

        foreach ($samples as $sample) {
            $html .= '
            <li style="margin: 0 0 4px 0;">' . h(date('Y-m-d H:i', $sample['when'])) . ' &middot; '
                . h($sample['method']) . ' <span style="font-family: monospace;">' . h($sample['url']) . '</span>'
                . (($sample['hits'] > 1) ? ' &times;' . (int) $sample['hits'] : '')
                . ' (' . h($sample['rule']) . ')</li>';
        }

        $html .= '
        </ul>';
    }

    return $html . '
        <p style="' . $paragraph . '"><a href="' . h($log_url) . '">' . h(lang('Open the firewall log for this address')) . '</a></p>
        <p style="' . $paragraph . '">' . lang(array(
            'string' => 'If this is your own address, the control panel is closed to you until the ban ends. To lift it now, run {var:1} in the software directory on the server.',
            'vars'   => array('<span style="font-family: monospace;">' . h(pg_waf_unban_command($entry['subject'])) . '</span>'),
        )) . '</p>
        <p style="font-size: 12px; color: #666666; margin: 18px 0 0 0;">' . h(lang('An e-mail is sent for each automatic ban; when one rule bans several addresses within an hour, a single notice is sent instead. Alerts are set under Site Settings, General, Alerts.')) . '</p>';
}

/**
 * The HTML body of the single notice for several addresses banned by one rule
 * within an hour.
 *
 * @param string $rule
 * @param array  $subjects the banned addresses and ranges
 * @param string $log_url  absolute address of the firewall log filtered by
 *                         the rule
 * @return string
 */
function pg_waf_ban_notice_burst_html($rule, array $subjects, $log_url)
{
    $paragraph = 'font-size: 14px; margin: 0 0 12px 0;';

    $html = '
        <p style="font-size: 16px; font-weight: bold; margin: 0 0 12px 0;">' . h(lang('The firewall banned several addresses by one rule')) . '</p>
        <p style="' . $paragraph . '">' . lang(array(
            'string' => 'The rule {var:1} banned {var:2} addresses within an hour. This is either an attack or a rule that catches legitimate visitors; the log shows which.',
            'vars'   => array(h($rule), count($subjects)),
        )) . '</p>
        <ul style="font-size: 13px; font-family: monospace; margin: 0 0 12px 0; padding-left: 18px;">';

    foreach ($subjects as $subject) {
        $html .= '
            <li>' . h($subject) . '</li>';
    }

    return $html . '
        </ul>
        <p style="' . $paragraph . '"><a href="' . h($log_url) . '">' . h(lang('Open the firewall log for this rule')) . '</a></p>
        <p style="font-size: 12px; color: #666666; margin: 18px 0 0 0;">' . h(lang('No further e-mail is sent for this rule within the next hour.')) . '</p>';
}

/**
 * Send the queued ban notices.
 *
 * The pending file is claimed by renaming it, so two requests never send the
 * same line; it is dropped unread while the e-mail alerts are off, so it does
 * not pile up. Cheap when nothing is queued: one file check.
 *
 * @return int the e-mails queued
 */
function pg_waf_ban_notice_send()
{
    $path = pg_waf_ban_notice_pending_path();

    if (!is_file($path)) {
        return 0;
    }

    $claim = $path . '.' . getmypid() . '.sending';

    if (!@rename($path, $claim)) {
        return 0;
    }

    // A ban written while the file was renamed holds the lock until its line
    // is in.
    $raw = '';
    $handle = @fopen($claim, 'r');

    if ($handle) {
        @flock($handle, LOCK_SH);
        $raw = (string) stream_get_contents($handle);
        @flock($handle, LOCK_UN);
        fclose($handle);
    }

    @unlink($claim);

    $entries = array();

    foreach (explode("\n", $raw) as $line) {
        $entry = waf_ban_notice_parse($line);

        if ($entry !== null) {
            $entries[] = $entry;
        }
    }

    if (!$entries || !pg_waf_ban_notice_enabled()) {
        return 0;
    }

    $recipients = pg_ops_alert_recipients(
        defined('OPS_ALERT_RECIPIENTS') ? OPS_ALERT_RECIPIENTS : '',
        array(defined('EMAIL_ADDRESS') ? EMAIL_ADDRESS : '')
    );

    if (!$recipients) {
        return 0;
    }

    $plan = pg_waf_ban_notice_plan_with_state($entries, time());

    $site = (defined('ORGANIZATION_NAME') && (trim((string) ORGANIZATION_NAME) !== ''))
        ? (string) ORGANIZATION_NAME
        : (string) HOSTNAME_SETTING;

    $sent = 0;

    foreach ($plan as $notice) {

        if ($notice['kind'] === 'ban') {
            $entry = $notice['entry'];
            $subject = lang(array('string' => '{var:1}: the firewall banned {var:2}', 'vars' => array($site, $entry['subject'])));
            $body = pg_waf_ban_notice_email_html(
                $entry,
                pg_waf_ban_notice_samples($entry['ip'], $entry['when']),
                pg_waf_ban_notice_absolute_url(pg_waf_ban_log_url($entry['subject']))
            );
        } else {
            $subject = lang(array('string' => '{var:1}: the firewall banned {var:2} addresses by the rule {var:3}', 'vars' => array($site, count($notice['subjects']), $notice['rule'])));
            $body = pg_waf_ban_notice_burst_html(
                $notice['rule'],
                $notice['subjects'],
                pg_waf_ban_notice_absolute_url('view_waf_log.php?range=1&search=' . rawurlencode($notice['rule']))
            );
        }

        try {
            $sent += email(array(
                'to'                 => $recipients,
                'from_name'          => $site,
                'from_email_address' => EMAIL_ADDRESS,
                'subject'            => $subject,
                'format'             => 'html',
                'body'               => $body,
                'type'               => 'system',
                'queue'              => true,
            )) ? 1 : 0;
        } catch (\Throwable $e) {
            continue;
        }
    }

    return $sent;
}

/**
 * pg_waf_ban_notice_plan() against the state file, read and written under one
 * lock so two senders do not both start a burst.
 *
 * @param array $entries
 * @param int   $now
 * @return array the notices to send
 */
function pg_waf_ban_notice_plan_with_state(array $entries, $now)
{
    $handle = @fopen(pg_waf_ban_notice_state_path(), 'c+');

    if (!$handle) {
        $plan = pg_waf_ban_notice_plan($entries, array(), $now);

        return $plan['notices'];
    }

    @flock($handle, LOCK_EX);

    $state = json_decode((string) stream_get_contents($handle), true);
    $plan = pg_waf_ban_notice_plan($entries, is_array($state) ? $state : array(), $now);

    ftruncate($handle, 0);
    rewind($handle);
    fwrite($handle, (string) json_encode($plan['state']));
    fflush($handle);

    @flock($handle, LOCK_UN);
    fclose($handle);

    return $plan['notices'];
}
