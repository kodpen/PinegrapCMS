<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Maintenance mode and the liveness answer for uptime monitors.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// A module of functions.php: maintenance mode (Site Settings, General,
// Maintenance Mode) and health.php's answer.
//
// While maintenance mode is on, a visitor who asks for a page of the site gets
// a 503 with Retry-After, drawn by the designed error page (the error_page
// widget) or the legacy error page - the same screen every other site error
// uses - so search engines keep the pages and come back. Staff (manager and
// above) and the addresses on the list see the site as usual.
//
// Only page renders are held (get_page.php). Files, robots.txt and the
// sitemap are served, the control panel and its sign-in work, and so do the
// endpoints a page already open posts to: a payment provider calling the
// shop back must reach it whatever the mode, or an order that was paid would
// be lost. Nothing here changes a site that never switches the mode on.
if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/**
 * The maintenance settings as one array.
 *
 * @return array on (bool, the switch), until (unix time, 0 for no end),
 *               allow (array of address patterns)
 */
function pg_maintenance_settings()
{
    return array(
        'on'    => (defined('MAINTENANCE_MODE') && MAINTENANCE_MODE),
        'until' => defined('MAINTENANCE_UNTIL') ? max(0, (int) MAINTENANCE_UNTIL) : 0,
        'allow' => function_exists('waf_parse_list') ? waf_parse_list(defined('MAINTENANCE_ALLOW_IPS') ? (string) MAINTENANCE_ALLOW_IPS : '') : array(),
    );
}

/**
 * Whether the mode holds at $now: switched on, and before its end time when
 * it has one. A mode whose end has passed is over without anyone having to
 * switch it off.
 *
 * @param bool $on
 * @param int  $until unix time, 0 for no end
 * @param int  $now
 * @return bool
 */
function pg_maintenance_holds($on, $until, $now)
{
    return (bool) $on && (((int) $until < 1) || ((int) $now < (int) $until));
}

/**
 * Whether maintenance mode holds now.
 *
 * @return bool
 */
function pg_maintenance_active()
{
    $settings = pg_maintenance_settings();

    return pg_maintenance_holds($settings['on'], $settings['until'], time());
}

/**
 * The Retry-After a 503 carries: the seconds to the end time, or an hour
 * when there is none. Never under a minute, so a crawler is not asked to come
 * back at once; never over a day, so an end time typed a year ahead does not
 * keep the crawlers away for a year after the mode is switched off early.
 *
 * @param int $until unix time, 0 for no end
 * @param int $now
 * @return int seconds
 */
function pg_maintenance_retry_after($until, $now)
{
    if ((int) $until < 1) {
        return 3600;
    }

    return min(86400, max(60, (int) $until - (int) $now));
}

/**
 * Whether this request is let through while the mode holds: staff signed in
 * (manager and above), or an address on the list. The address is the one the
 * firewall resolves (waf_client_ip()), so a trusted proxy's header counts and
 * anyone else's does not.
 *
 * @param array $allow address patterns (waf_ip_matches())
 * @return bool
 */
function pg_maintenance_passes(array $allow)
{
    if (defined('USER_LOGGED_IN') && USER_LOGGED_IN && defined('USER_ROLE') && ((int) USER_ROLE < 3)) {
        return true;
    }

    if ($allow && function_exists('waf_client_ip') && function_exists('waf_ip_in_any')) {
        $ip = waf_client_ip();

        return ($ip !== '') && waf_ip_in_any($ip, $allow);
    }

    return false;
}

/**
 * Whether an address the firewall resolved is one every visitor arrives
 * with, not a person's own: the server itself (a tunnel or a reverse proxy on
 * the same machine) or a trusted proxy whose header named nobody
 * (waf_ip_is_infrastructure()). Shown on the card, so the operator does not
 * take it for theirs.
 *
 * @param string $ip as waf_client_ip() returns it
 * @return bool
 */
function pg_maintenance_ip_is_shared($ip)
{
    $ip = (string) $ip;

    return ($ip !== '') && function_exists('waf_ip_is_infrastructure') && waf_ip_is_infrastructure($ip);
}

/**
 * Whether a typed address list lets every visitor through: it holds the
 * address this request arrives from, and every visitor shares that address.
 *
 * @param string $list   the list as typed
 * @param string $ip     the address the firewall resolved for this request
 * @param bool   $shared pg_maintenance_ip_is_shared($ip)
 * @return bool
 */
function pg_maintenance_list_opens_to_all($list, $ip, $shared)
{
    $ip = (string) $ip;

    if (!$shared || ($ip === '') || !function_exists('waf_parse_list') || !function_exists('waf_ip_in_any')) {
        return false;
    }

    $patterns = waf_parse_list((string) $list);

    return $patterns ? waf_ip_in_any($ip, $patterns) : false;
}

/**
 * The entries of a typed address list that are not an address, a range
 * (CIDR) or an IPv4 wildcard - the patterns the firewall's lists take.
 *
 * @param string $list
 * @return array
 */
function pg_maintenance_allow_invalid($list)
{
    $invalid = array();

    foreach ((function_exists('waf_parse_list') ? waf_parse_list((string) $list) : array()) as $entry) {
        if (!function_exists('waf_valid_ip_pattern') || !waf_valid_ip_pattern($entry)) {
            $invalid[] = $entry;
        }
    }

    return $invalid;
}

/**
 * An end time as the site writes dates and times, in digits: the month names
 * date() gives are English whatever the visitor reads.
 *
 * @param int $time
 * @return string
 */
function pg_maintenance_time_text($time)
{
    $date = (defined('DATE_FORMAT') && (DATE_FORMAT == 'month_day')) ? 'm/d/Y' : 'd.m.Y';
    $clock = (defined('TIME_FORMAT') && (TIME_FORMAT == 'twelve_hours')) ? 'g:i A' : 'H:i';

    return date($date . ' ' . $clock, (int) $time);
}

/**
 * The end time typed into the card (datetime-local, Y-m-d\TH:i), read in the
 * time zone the same save gives the site. Settings › General saves the time
 * zone and the end time in one UPDATE, and the request still runs in the old
 * zone: read in that one, the end time moved by the difference between the two
 * (15:00 typed for Istanbul stored as 15:00 UTC, shown as 18:00 next time).
 *
 * @param string $posted    the input's value; seconds, if a browser sends them, are dropped
 * @param string $timezone  the zone the site will have; an unknown one falls back to the request's
 * @return int|false  the timestamp, 0 for no end time, false for a value that is not a date and time
 */
function pg_maintenance_until_parse($posted, $timezone)
{
    $posted = trim((string) $posted);

    if ($posted === '') {
        return 0;
    }

    $value = substr($posted, 0, 16);
    $zone = null;

    if ((string) $timezone !== '') {
        try {
            $zone = new DateTimeZone((string) $timezone);
        } catch (Exception $exception) {
            $zone = null;
        }
    }

    if ($zone === null) {
        $zone = new DateTimeZone(date_default_timezone_get());
    }

    $parsed = DateTime::createFromFormat('!Y-m-d\TH:i', $value, $zone);

    if (($parsed === false) || ($parsed->format('Y-m-d\TH:i') !== $value)) {
        return false;
    }

    return $parsed->getTimestamp();
}

/**
 * Does an end time that has passed refuse the save? Only when the save would
 * switch the mode on or give it a new end: a mode over before it starts. A
 * mode that ended by itself keeps its switch and its passed end, and saving
 * any other general setting must not stop on it.
 *
 * @param bool $on         the switch as posted
 * @param int  $until      the end time as read (0 = none)
 * @param int  $now
 * @param bool $was_on     the stored switch
 * @param int  $was_until  the stored end time
 * @return bool
 */
function pg_maintenance_until_refused($on, $until, $now, $was_on, $was_until)
{
    if (!$on || ((int) $until <= 0) || ((int) $until > (int) $now)) {
        return false;
    }

    return !$was_on || ((int) $until !== (int) $was_until);
}

/**
 * The gate in front of a page render (get_page.php): while the mode holds,
 * a visitor gets the maintenance screen with a 503 and Retry-After, and the
 * request ends here.
 *
 * @return void
 */
function pg_maintenance_gate()
{
    $settings = pg_maintenance_settings();
    $now = time();

    if (!pg_maintenance_holds($settings['on'], $settings['until'], $now) || pg_maintenance_passes($settings['allow'])) {
        return;
    }

    if (!headers_sent()) {
        header('Retry-After: ' . pg_maintenance_retry_after($settings['until'], $now));
        header('Cache-Control: no-store');
    }

    $message = lang('This site is undergoing maintenance and will be back shortly.');

    if ($settings['until'] > 0) {
        $message .= ' ' . lang(array(
            'string' => 'Expected back at {var:1}.',
            'vars'   => array(pg_maintenance_time_text($settings['until'])),
        ));
    }

    output_error(h($message), 503);
}

/**
 * The answer health.php gives: whether the site is up for visitors and the
 * general job still runs.
 *
 * - maintenance  the mode holds (503)
 * - cron_stalled the general job used to finish and has not for longer than
 *                its limit (503): nothing else on the site says so from
 *                outside
 * - ok           otherwise (200); a site whose general job never ran is not
 *                a failure, as on System Status
 *
 * @param bool     $maintenance pg_maintenance_active()
 * @param int|null $cron_age    seconds since the general job last finished,
 *                              null when it never has
 * @param int      $cron_limit  the general job's stale_after
 * @return array array('code' => int, 'status' => string)
 */
function pg_health_verdict($maintenance, $cron_age, $cron_limit)
{
    if ($maintenance) {
        return array('code' => 503, 'status' => 'maintenance');
    }

    if (($cron_age !== null) && ((int) $cron_age > (int) $cron_limit)) {
        return array('code' => 503, 'status' => 'cron_stalled');
    }

    return array('code' => 200, 'status' => 'ok');
}
