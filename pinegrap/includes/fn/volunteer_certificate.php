<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * A volunteer's hours certificate (volunteer_certificate.php) and the
 * thank-you e-mails at 50, 100 and 250 approved hours. A certificate states
 * the approved hours of a period the volunteer chooses; its code carries
 * the volunteer, the period and the minutes, signed with ENCRYPTION_KEY,
 * and nothing is stored for it. A code is valid while the volunteer's
 * profile exists and the period still holds at least those approved
 * minutes, so erasing the person takes every code back.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/volunteer_shifts.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/volunteer_board.php');

/** The approved hours whose reaching sends the thank-you e-mail, once each. */
const PG_VOLUNTEER_THANKS_HOURS = array(50, 100, 250);

/** The longest period a certificate covers, in days. */
if (!defined('PG_VOLUNTEER_CERTIFICATE_DAYS')) {
    define('PG_VOLUNTEER_CERTIFICATE_DAYS', 3660);
}

/**
 * The signature of a certificate code's body. Pure.
 *
 * @param string $body
 * @param string $key
 * @return string 12 upper-case hex letters
 */
function _pg_volunteer_certificate_signature($body, $key)
{
    return strtoupper(substr(hash_hmac('sha256', 'volunteer-certificate:' . $body, (string) $key), 0, 12));
}

/**
 * A certificate's code: the volunteer, the period's first and last day
 * (YYYYMMDD) and the approved minutes in base 36, then a signature made
 * with the site's key. '' when a part is missing. Pure.
 *
 * @param int    $volunteer_id
 * @param string $from    'YYYY-MM-DD'
 * @param string $to      'YYYY-MM-DD'
 * @param int    $minutes
 * @param string $key     the site's ENCRYPTION_KEY
 * @return string
 */
function pg_volunteer_certificate_code($volunteer_id, $from, $to, $minutes, $key)
{
    $days = array((int) str_replace('-', '', (string) $from), (int) str_replace('-', '', (string) $to));

    if (((string) $key === '') || ((int) $volunteer_id <= 0) || ((int) $minutes <= 0) || ($days[0] < 19000101) || ($days[1] < $days[0])) {
        return '';
    }

    $body = strtoupper(base_convert((string) (int) $volunteer_id, 10, 36) . '-' . base_convert((string) $days[0], 10, 36) . '-'
        . base_convert((string) $days[1], 10, 36) . '-' . base_convert((string) (int) $minutes, 10, 36));

    return $body . '-' . _pg_volunteer_certificate_signature($body, $key);
}

/**
 * Reads a code back, typed in any letter case; null when it is no code,
 * any part was changed or a day is no real date. The caller still checks
 * the volunteer and the hours. Pure.
 *
 * @param mixed  $code
 * @param string $key
 * @return array|null volunteer_id, from, to ('YYYY-MM-DD'), minutes
 */
function pg_volunteer_certificate_parse($code, $key)
{
    $code = is_string($code) ? strtoupper(trim($code)) : '';

    if (((string) $key === '') || !preg_match('/^([0-9A-Z]{1,8})-([0-9A-Z]{1,8})-([0-9A-Z]{1,8})-([0-9A-Z]{1,8})-([0-9A-F]{12})$/', $code, $parts)) {
        return null;
    }

    $body = $parts[1] . '-' . $parts[2] . '-' . $parts[3] . '-' . $parts[4];

    if (!hash_equals(_pg_volunteer_certificate_signature($body, $key), $parts[5])) {
        return null;
    }

    $day = function ($part) {
        $text = str_pad(base_convert(strtolower($part), 36, 10), 8, '0', STR_PAD_LEFT);
        $date = substr($text, 0, 4) . '-' . substr($text, 4, 2) . '-' . substr($text, 6, 2);

        return ((strlen($text) === 8) && checkdate((int) substr($text, 4, 2), (int) substr($text, 6, 2), (int) substr($text, 0, 4))) ? $date : null;
    };
    $out = array(
        'volunteer_id' => (int) base_convert(strtolower($parts[1]), 36, 10),
        'from'         => $day($parts[2]),
        'to'           => $day($parts[3]),
        'minutes'      => (int) base_convert(strtolower($parts[4]), 36, 10),
    );

    return (($out['volunteer_id'] > 0) && ($out['from'] !== null) && ($out['to'] !== null) && ($out['from'] <= $out['to']) && ($out['minutes'] > 0)) ? $out : null;
}

/**
 * The period a volunteer asked for: from the first of this year to today
 * when nothing is given; a day after today becomes today; a start after
 * the end, an unreadable day or a period longer than
 * PG_VOLUNTEER_CERTIFICATE_DAYS is refused. Pure.
 *
 * @param array  $input from, to
 * @param string $today 'YYYY-MM-DD'
 * @return array from, to, error ('' or 'period')
 */
function pg_volunteer_certificate_period(array $input, $today)
{
    $read = function ($value, $default) {
        if (($value !== null) && !is_string($value)) {
            return null;
        }

        $value = trim((string) $value);

        if ($value === '') {
            return $default;
        }

        return (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m) && checkdate((int) $m[2], (int) $m[3], (int) $m[1])) ? $value : null;
    };
    $from = $read($input['from'] ?? null, substr((string) $today, 0, 4) . '-01-01');
    $to = $read($input['to'] ?? null, (string) $today);

    if (($to !== null) && ($to > $today)) {
        $to = (string) $today;
    }

    if (($from === null) || ($to === null) || ($from > $to) || (pg_volunteer_days_between($from, $to) > PG_VOLUNTEER_CERTIFICATE_DAYS)) {
        return array('from' => is_string($input['from'] ?? null) ? mb_substr($input['from'], 0, 10) : '', 'to' => is_string($input['to'] ?? null) ? mb_substr($input['to'], 0, 10) : '', 'error' => 'period');
    }

    return array('from' => $from, 'to' => $to, 'error' => '');
}

/**
 * Whether a parsed code still holds: the volunteer's profile exists and the
 * period's approved minutes are at least the code's (hours approved later
 * inside the period keep it valid). Pure.
 *
 * @param array|null $parsed  pg_volunteer_certificate_parse()
 * @param bool       $exists  the volunteer's profile exists
 * @param int        $minutes the period's approved minutes now
 * @return bool
 */
function pg_volunteer_certificate_holds($parsed, $exists, $minutes)
{
    return is_array($parsed) && $exists && ((int) $minutes >= (int) $parsed['minutes']);
}

/**
 * The thank-you due once a volunteer has these approved minutes: the
 * highest of PG_VOLUNTEER_THANKS_HOURS reached and not yet thanked, so
 * passing two at once sends one e-mail. 0 for none. Pure.
 *
 * @param int $minutes approved minutes in the programme
 * @param int $thanked the hours already thanked for (volunteers.thanked_hours)
 * @return int hours
 */
function pg_volunteer_thanks_due($minutes, $thanked)
{
    $due = 0;

    foreach (PG_VOLUNTEER_THANKS_HOURS as $hours) {
        if (((int) $minutes >= $hours * 60) && ($hours > (int) $thanked)) {
            $due = $hours;
        }
    }

    return $due;
}

/**
 * Approved hours as a number for a certificate or an e-mail: whole hours,
 * or one decimal. Pure.
 *
 * @param int $minutes
 * @return string
 */
function pg_volunteer_hours_number($minutes)
{
    $hours = max(0, (int) $minutes) / 60;

    return (floor($hours) == $hours) ? (string) (int) $hours : pg_format_number(floor($hours * 10) / 10, 1);
}

/**
 * A volunteer's approved minutes, in a period when one is given: a shift's
 * row counts on its start day, a row outside a shift on its day.
 *
 * @param int    $volunteer_id
 * @param string $from '' for no start
 * @param string $to   '' for no end
 * @return int
 */
function pg_volunteer_approved_minutes($volunteer_id, $from = '', $to = '')
{
    $day = "COALESCE(DATE(volunteer_shifts.start_time), volunteer_signups.worked_on)";

    return (int) db_value("SELECT COALESCE(SUM(volunteer_signups.minutes), 0) FROM volunteer_signups LEFT JOIN volunteer_shifts ON volunteer_shifts.id = volunteer_signups.shift_id
        WHERE volunteer_signups.volunteer_id = '" . (int) $volunteer_id . "' AND volunteer_signups.state = 'signed' AND volunteer_signups.approved_at > 0"
        . (((string) $from !== '') ? " AND " . $day . " >= '" . escape($from) . "'" : '')
        . (((string) $to !== '') ? " AND " . $day . " <= '" . escape($to) . "'" : ''));
}

/**
 * The volunteer a certificate names: their name (else the e-mail address)
 * and the programme's name; any state, a left volunteer included.
 *
 * @param int $volunteer_id
 * @return array|null id, name, email, programme_id, programme, thanked_hours, state
 */
function pg_volunteer_certificate_person($volunteer_id)
{
    $row = db_item("SELECT volunteers.id, volunteers.programme_id, volunteers.thanked_hours, volunteers.state, contacts.first_name, contacts.last_name, contacts.email_address, volunteer_programmes.name AS programme
        FROM volunteers INNER JOIN volunteer_programmes ON volunteer_programmes.id = volunteers.programme_id LEFT JOIN contacts ON contacts.id = volunteers.contact_id
        WHERE volunteers.id = '" . (int) $volunteer_id . "'");

    if (!$row) {
        return null;
    }

    $name = trim((string) $row['first_name'] . ' ' . (string) $row['last_name']);

    return array(
        'id'            => (int) $row['id'],
        'name'          => ($name !== '') ? $name : (string) $row['email_address'],
        'email'         => (string) $row['email_address'],
        'programme_id'  => (int) $row['programme_id'],
        'programme'     => (string) $row['programme'],
        'thanked_hours' => (int) $row['thanked_hours'],
        'state'         => (string) $row['state'],
    );
}

/**
 * Reads a typed or scanned code and the volunteer it names.
 *
 * @param string $code
 * @return array|null name, programme, from, to, minutes, code
 */
function pg_volunteer_certificate_lookup($code)
{
    $key = defined('ENCRYPTION_KEY') ? (string) ENCRYPTION_KEY : '';
    $parsed = pg_volunteer_certificate_parse($code, $key);
    $person = ($parsed !== null) ? pg_volunteer_certificate_person($parsed['volunteer_id']) : null;
    $minutes = ($person !== null) ? pg_volunteer_approved_minutes($person['id'], $parsed['from'], $parsed['to']) : 0;

    if (!pg_volunteer_certificate_holds($parsed, $person !== null, $minutes)) {
        return null;
    }

    return array(
        'name'      => $person['name'],
        'programme' => $person['programme'],
        'from'      => $parsed['from'],
        'to'        => $parsed['to'],
        'minutes'   => $parsed['minutes'],
        'code'      => pg_volunteer_certificate_code($person['id'], $parsed['from'], $parsed['to'], $parsed['minutes'], $key),
    );
}

/**
 * The certificate page's address: a volunteer's own with their profile, or
 * the public check with a code.
 *
 * @param array $query p, from, to or code
 * @param bool  $full  with the scheme and host (a QR code)
 * @return string
 */
function pg_volunteer_certificate_url(array $query = array(), $full = false)
{
    return ($full ? URL_SCHEME . HOSTNAME_SETTING : '') . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/volunteer_certificate.php'
        . ($query ? '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986) : '');
}

/**
 * A day as the site writes a long date.
 *
 * @param string $day 'YYYY-MM-DD'
 * @return string
 */
function pg_volunteer_certificate_day($day)
{
    return (string) get_absolute_time(array('timestamp' => strtotime($day . ' 12:00:00'), 'type' => 'date', 'size' => 'long', 'format' => 'plain_text', 'timezone_type' => 'site'));
}

/**
 * The certificate's body, in certificate.php's look: the site's logo and
 * name, the volunteer, the hours, the programme, the period, the code and
 * the QR of the check's address. Every value is escaped here.
 *
 * @param array  $view name, programme, from, to, minutes, code
 * @param string $site the site's name
 * @param string $logo the logo's address ('' for none)
 * @return string HTML
 */
function pg_volunteer_certificate_html(array $view, $site, $logo)
{
    $check = pg_volunteer_certificate_url(array('code' => $view['code']), true);
    $qr = function_exists('pg_qr_svg_data_uri') ? pg_qr_svg_data_uri($check, 0) : '';

    return '<article class="pg-certificate">'
        . (($logo !== '') ? '<img class="pg-certificate-logo" src="' . h($logo) . '" alt="' . h($site) . '">' : '')
        . (($site !== '') ? '<div class="pg-certificate-site">' . h($site) . '</div>' : '')
        . '<h1 class="pg-certificate-title">' . h(lang('Certificate of Volunteering')) . '</h1>'
        . '<p class="pg-certificate-lead">' . h(lang('This certifies that')) . '</p>'
        . '<p class="pg-certificate-name">' . h($view['name']) . '</p>'
        . '<p class="pg-certificate-lead">' . h(lang(array('string' => 'gave {var:1} approved hours as a volunteer of', 'vars' => array(pg_volunteer_hours_number($view['minutes']))))) . '</p>'
        . '<p class="pg-certificate-course">' . h($view['programme']) . '</p>'
        . '<p class="pg-certificate-day">' . h(lang(array('string' => 'From {var:1} to {var:2}', 'vars' => array(pg_volunteer_certificate_day($view['from']), pg_volunteer_certificate_day($view['to']))))) . '</p>'
        . '<footer class="pg-certificate-foot">'
        . (($qr !== '') ? '<img class="pg-certificate-qr" src="' . h($qr) . '" alt="' . h(lang('Check this certificate')) . '">' : '')
        . '<div><div class="pg-certificate-code">' . h(lang('Certificate code')) . ': <strong>' . h($view['code']) . '</strong></div>'
        . '<div class="pg-certificate-check">' . h(lang('Check it at')) . ' ' . h(pg_volunteer_certificate_url(array(), true)) . '</div></div>'
        . '</footer>'
        . '</article>';
}

/**
 * Sends the thank-you e-mail a volunteer's approved hours call for: the
 * milestone is claimed on the profile first (thanked_hours, a write
 * conditional on the old value), so two approvals at once never send it
 * twice. The programme's "Thank you" message, with the hours added.
 *
 * @param array $programme
 * @param int   $volunteer_id
 * @return int the hours thanked for, 0 for none
 */
function pg_volunteer_thanks_send(array $programme, $volunteer_id)
{
    $person = pg_volunteer_certificate_person($volunteer_id);

    if (($person === null) || ($person['programme_id'] !== (int) $programme['id'])) {
        return 0;
    }

    $due = pg_volunteer_thanks_due(pg_volunteer_approved_minutes($person['id']), $person['thanked_hours']);

    if ($due === 0) {
        return 0;
    }

    $ok = db_q("UPDATE volunteers SET thanked_hours = ?, updated_at = UNIX_TIMESTAMP() WHERE id = ? AND thanked_hours < ?", array($due, $person['id'], $due));

    if (!$ok || (mysqli_affected_rows(db::$con) !== 1)) {
        return 0;
    }

    if (($person['email'] !== '') && ($person['state'] !== 'left')) {
        $message = pg_volunteer_message($programme['texts'] ?? array(), 'thanks', array('name' => $person['name'], 'programme' => (string) $programme['name']));
        $message['body'] .= "\n\n" . lang(array('string' => 'You have given {var:1} approved hours so far. You can print your hours certificate on your volunteering page.', 'vars' => array($due)));
        _pg_volunteer_mail($person['email'], $message);
    }

    return $due;
}
