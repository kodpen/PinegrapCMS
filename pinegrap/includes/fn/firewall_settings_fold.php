<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Site Settings > Firewall and HTTPS as folded rows
 * (includes/settings/fold.php): the rows' order, names and icons, and each
 * part's one line of state with its pill. The pg_firewall_fold_<part>()
 * lines are pure but for lang(); includes/settings/firewall.php reads the
 * facts they take.
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
function pg_firewall_fold_order()
{
    return array('headers', 'waf', 'scan', 'limits', 'bots', 'iplists', 'countries', 'proxies', 'waf-logs');
}

/**
 * A row's name, icon and tone.
 *
 * @param string $key one of pg_firewall_fold_order()
 * @return array name, icon, tone
 */
function pg_firewall_fold_face($key)
{
    switch ($key) {
        case 'headers':
            return array('name' => lang('HTTPS'), 'icon' => 'bi-lock', 'tone' => 'pg-tone-violet');
        case 'waf':
            return array('name' => lang('Firewall'), 'icon' => 'bi-shield-check', 'tone' => 'pg-tone-system');
        case 'scan':
            return array('name' => lang('Security Scan'), 'icon' => 'bi-shield-exclamation', 'tone' => 'pg-tone-rose');
        case 'limits':
            return array('name' => lang('Limits'), 'icon' => 'bi-speedometer2', 'tone' => 'pg-tone-amber');
        case 'bots':
            return array('name' => lang('Bots'), 'icon' => 'bi-robot', 'tone' => 'pg-tone-blue');
        case 'iplists':
            return array('name' => lang('Addresses'), 'icon' => 'bi-list-check', 'tone' => 'pg-tone-rose');
        case 'countries':
            return array('name' => lang('Countries'), 'icon' => 'bi-globe2', 'tone' => 'pg-tone-teal');
        case 'waf-logs':
            return array('name' => lang('Logs'), 'icon' => 'bi-journal-text', 'tone' => '');
        default:
            return array('name' => lang('Trusted proxies'), 'icon' => 'bi-diagram-3', 'tone' => '');
    }
}

/**
 * The On or Off pill of a part one switch turns on.
 *
 * @param bool $on
 * @return array words, tone class
 */
function pg_firewall_fold_switch_pill($on)
{
    return $on ? array(lang('On'), 'pg-tone-teal') : array(lang('Off'), 'pg-tone-gray');
}

/**
 * The HTTPS row's line: Secure Mode, then the headers and the policy once
 * their columns exist.
 *
 * @param bool   $secure   Secure Mode
 * @param bool   $ready    the header columns exist
 * @param bool   $headers  Send security headers
 * @param bool   $hsts     Always HTTPS (HSTS)
 * @param string $csp_mode off, report or enforce
 * @return array line, pill, attention
 */
function pg_firewall_fold_https($secure, $ready, $headers, $hsts, $csp_mode)
{
    $line = $secure ? lang('Secure Mode on') : lang('Secure Mode off');

    if ($ready) {
        if (!$headers) {
            $line .= ' · ' . lang('no security headers');
        } else {
            $line .= ' · ' . lang('security headers sent');

            // HSTS is sent only over Secure Mode (waf_send_security_headers()).
            if ($hsts && $secure) {
                $line .= ' · ' . lang('HSTS');
            }

            if ($csp_mode === 'enforce') {
                $line .= ' · ' . lang('policy enforced');
            } elseif ($csp_mode !== 'off') {
                $line .= ' · ' . lang('policy reported only');
            }
        }
    }

    return array('line' => $line, 'pill' => pg_firewall_fold_switch_pill($secure), 'attention' => false);
}

/**
 * The Security Scan row's line: how many findings are open and when the last
 * scan ended, the worst open severity as its pill. A high or critical open
 * finding opens the row.
 *
 * @param bool   $ready         the findings table exists
 * @param int    $last_finished when the last full scan ended, 0 for never
 * @param bool   $running       a scan is under way
 * @param array  $open          open findings by severity (pg_security_section_open_counts())
 * @param string $when          the last scan's time in words
 * @return array line, pill, attention
 */
function pg_firewall_fold_scan($ready, $last_finished, $running, array $open, $when)
{
    if (!$ready) {
        return array('line' => lang('Ready once the upgrade has run'), 'pill' => null, 'attention' => false);
    }

    $total = (int) array_sum($open);
    $worst = 0;

    foreach ($open as $severity => $count) {
        if (((int) $count > 0) && ((int) $severity > $worst)) {
            $worst = (int) $severity;
        }
    }

    if ($running) {
        $line = lang('Scanning now');
    } elseif ((int) $last_finished === 0) {
        $line = lang('Not scanned yet');
    } else {
        $line = (($total === 0) ? lang('Nothing found') : lang(array('string' => '{var:1} open finding{suffix:1}', 'vars' => $total, 'suffix' => array(($total === 1) ? '' : 's'))))
            . ' · ' . lang('last scan') . ' ' . $when;
    }

    if ($worst > 0) {
        $pill = pg_security_severity_face($worst);
    } else {
        $pill = ((int) $last_finished > 0) ? array(lang('Nothing open'), 'pg-tone-teal') : null;
    }

    return array('line' => $line, 'pill' => $pill, 'attention' => $worst >= PG_SECURITY_SEVERITY_HIGH);
}

/**
 * The Logs row's line: how long the firewall log keeps its rows and its
 * ceiling, then the plain-text block log once its column exists.
 *
 * @param int  $days     Log retention
 * @param int  $rows     Log size limit
 * @param bool $ready    the plain-text log's column exists
 * @param bool $text_log Plain-text block log
 * @return array line, pill, attention
 */
function pg_firewall_fold_logs($days, $rows, $ready, $text_log)
{
    $days = (int) $days;
    $rows = function_exists('pg_format_number') ? pg_format_number((int) $rows, 0) : number_format((int) $rows);
    $line = lang(array('string' => 'Kept {var:1} day{suffix:1}, at most {var:2} rows', 'vars' => array($days, $rows), 'suffix' => array(($days === 1) ? '' : 's')));

    if ($ready) {
        $line .= ' · ' . ($text_log ? lang('plain-text block log written') : lang('no plain-text block log'));
    }

    return array('line' => $line, 'pill' => null, 'attention' => false);
}

/**
 * The Firewall row's line: on or off, and its mode.
 *
 * @param bool   $ready       the firewall's columns exist
 * @param bool   $on          the firewall switch
 * @param string $mode        monitor or block
 * @param bool   $self_banned the operator's own address is on the banned list
 * @return array line, pill, attention
 */
function pg_firewall_fold_waf($ready, $on, $mode, $self_banned)
{
    if (!$ready) {
        return array('line' => lang('Waits for the database upgrade'), 'pill' => null, 'attention' => true);
    }

    if (!$on) {
        $line = lang('Off');
    } elseif ($mode === 'block') {
        $line = lang('On, Block');
    } else {
        $line = lang('On, Monitor');
    }

    if ($self_banned) {
        $line .= ' · ' . lang('your own address is banned');
    }

    return array('line' => $line, 'pill' => pg_firewall_fold_switch_pill($on), 'attention' => (bool) $self_banned);
}

/**
 * The Limits row's line: the per-minute limit and the automatic bans.
 *
 * @param bool $rate_on   Rate limiting
 * @param int  $requests  All Requests per minute
 * @param bool $bans_on   Automatic temporary bans
 * @param int  $ban_after events in ten minutes that ban an address
 * @return array line, pill, attention
 */
function pg_firewall_fold_limits($rate_on, $requests, $bans_on, $ban_after)
{
    $line = $rate_on
        ? lang(array('string' => '{var:1} requests a minute from one address', 'vars' => (int) $requests))
        : lang('No rate limit');
    $line .= ' · ' . ($bans_on
        ? lang(array('string' => 'banned after {var:1} events', 'vars' => (int) $ban_after))
        : lang('no automatic bans'));

    return array('line' => $line, 'pill' => null, 'attention' => false);
}

/**
 * The Bots row's line: whether unknown bots are refused, whether SEO's
 * "Allow AI training" lets the training crawlers past the built-in list, and
 * the operator's own lists.
 *
 * @param bool $block_unknown Block unknown bots
 * @param int  $allowed       entries in Allowed bots
 * @param int  $blocked       entries in Blocked bots
 * @param bool $training      "Allow AI training" (config.robots_ai_training)
 * @return array line, pill, attention
 */
function pg_firewall_fold_bots($block_unknown, $allowed, $blocked, $training = false)
{
    $line = $block_unknown ? lang('Unknown bots are refused') : lang('Unknown bots are let in');

    if ($training) {
        $line .= ' · ' . lang('AI training crawlers let in');
    }

    if ((int) $allowed > 0) {
        $line .= ' · ' . lang(array('string' => '{var:1} allowed by you', 'vars' => (int) $allowed));
    }

    if ((int) $blocked > 0) {
        $line .= ' · ' . lang(array('string' => '{var:1} blocked by you', 'vars' => (int) $blocked));
    }

    return array('line' => $line, 'pill' => pg_firewall_fold_switch_pill($block_unknown), 'attention' => false);
}

/**
 * The sentence for the addresses the firewall has banned on its own.
 *
 * @param int $bans active automatic bans
 * @return string
 */
function pg_firewall_fold_bans_sentence($bans)
{
    $bans = (int) $bans;

    if ($bans <= 0) {
        return lang('No address is banned by the firewall now');
    }

    if ($bans === 1) {
        return lang('1 address banned now');
    }

    return lang(array('string' => '{var:1} addresses banned now', 'vars' => $bans));
}

/**
 * The Addresses row's line: the automatic bans, then the hand-written lists.
 *
 * @param int $allowed entries in Allowed IP addresses
 * @param int $banned  entries in Banned IP addresses
 * @param int $bans    active automatic bans
 * @return array line, pill, attention
 */
function pg_firewall_fold_addresses($allowed, $banned, $bans)
{
    $line = pg_firewall_fold_bans_sentence($bans);

    if ((int) $allowed > 0) {
        $line .= ' · ' . lang(array('string' => '{var:1} always allowed', 'vars' => (int) $allowed));
    }

    if ((int) $banned > 0) {
        $line .= ' · ' . lang(array('string' => '{var:1} banned by hand', 'vars' => (int) $banned));
    }

    return array('line' => $line, 'pill' => null, 'attention' => false);
}

/**
 * The Countries row's line: the restriction and how many countries it names.
 *
 * @param bool   $ready       the country columns exist
 * @param string $mode        off, block or allow
 * @param int    $count       countries listed
 * @param bool   $you_refused the operator's own request would be refused
 * @return array line, pill, attention
 */
function pg_firewall_fold_countries($ready, $mode, $count, $you_refused)
{
    if (!$ready) {
        return array('line' => lang('Arrives with the 2026.4.10 upgrade'), 'pill' => null, 'attention' => false);
    }

    $count = (int) $count;

    if ($mode === 'block') {
        $line = ($count === 1) ? lang('Refuses 1 country') : lang(array('string' => 'Refuses {var:1} countries', 'vars' => $count));
    } elseif ($mode === 'allow') {
        $line = ($count === 1) ? lang('Lets in only 1 country') : lang(array('string' => 'Lets in only {var:1} countries', 'vars' => $count));
    } else {
        $line = lang('Every country is let in');
    }

    if ($you_refused) {
        $line .= ' · ' . lang('yours is refused');
    }

    return array('line' => $line, 'pill' => pg_firewall_fold_switch_pill($mode === 'block' || $mode === 'allow'), 'attention' => (bool) $you_refused);
}

/**
 * The Trusted proxies row's line: how many are listed, and a proxy staff
 * came through that is not.
 *
 * @param int  $count    entries listed
 * @param bool $unlisted a proxy on a public address waits to be listed
 * @return array line, pill, attention
 */
function pg_firewall_fold_proxies($count, $unlisted)
{
    $count = (int) $count;

    if ($count <= 0) {
        $line = lang('None listed; Cloudflare is recognised on its own');
    } elseif ($count === 1) {
        $line = lang('1 proxy listed');
    } else {
        $line = lang(array('string' => '{var:1} proxies listed', 'vars' => $count));
    }

    if ($unlisted) {
        $line .= ' · ' . lang('a proxy is not listed');
    }

    return array('line' => $line, 'pill' => null, 'attention' => (bool) $unlisted);
}

/**
 * The Limits row's value rows for the limits on one address that Accounts and
 * sign-in keeps: Sign-in attempts and Form submissions, what each does today
 * and the row Change opens. Each is saved in its own pane; Limits only shows
 * it and leads there. Pure but for lang().
 *
 * @param array $throttle ready (the columns exist), on, attempts, minutes, lockout
 * @param array $forms    ready (the columns exist), ip_hour, recipient_day, domain_hour (0 is off)
 * @return array[] name, line, icon, tone, href ('' when the row is not drawn yet)
 */
function pg_settings_limits_overview(array $throttle, array $forms)
{
    $waiting = lang('Waits for the database upgrade.');

    if (empty($throttle['ready'])) {
        $throttle_line = $waiting;
        $throttle_on = false;
    } elseif (!empty($throttle['on'])) {
        $throttle_line = lang(array('string' => '{var:1} failed sign-ins in {var:2} minutes lock the address and the account out for {var:3} minutes.',
            'vars' => array((int) $throttle['attempts'], (int) $throttle['minutes'], (int) $throttle['lockout'])));
        $throttle_on = true;
    } else {
        $throttle_line = lang('Failed sign-ins are not limited.');
        $throttle_on = false;
    }

    $form_parts = array();

    if (!empty($forms['ready'])) {
        if ((int) $forms['ip_hour'] > 0) {
            $form_parts[] = lang(array('string' => '{var:1} an hour from one visitor address', 'vars' => (int) $forms['ip_hour']));
        }

        if ((int) $forms['recipient_day'] > 0) {
            $form_parts[] = lang(array('string' => '{var:1} a day for one e-mail address', 'vars' => (int) $forms['recipient_day']));
        }

        if ((int) $forms['domain_hour'] > 0) {
            $form_parts[] = lang(array('string' => '{var:1} an hour for one e-mail domain', 'vars' => (int) $forms['domain_hour']));
        }
    }

    if (empty($forms['ready'])) {
        $form_line = $waiting;
    } elseif ($form_parts) {
        $form_line = implode(' · ', $form_parts);
    } else {
        $form_line = lang('Form submissions are not limited.');
    }

    return array(
        array(
            'name' => lang('Sign-in attempts'),
            'line' => $throttle_line,
            'icon' => 'bi-person-lock',
            'tone' => $throttle_on ? 'blue' : 'amber',
            'href' => empty($throttle['ready']) ? '' : '#settings/security/pgset-throttle',
        ),
        array(
            'name' => lang('Form submissions'),
            'line' => $form_line,
            'icon' => 'bi-ui-checks',
            'tone' => $form_parts ? 'violet' : 'amber',
            'href' => empty($forms['ready']) ? '' : '#settings/security/pgset-captcha',
        ),
    );
}
