<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The course certificate (certificate.php): the page a member who completed
 * a course with "Certificate" on prints or saves as a PDF from the browser,
 * and the public page that checks a certificate's code. The code is made
 * and read by pg_course_certificate_code() / pg_course_certificate_parse()
 * (includes/fn/courses.php); a code is valid only while its completion row
 * exists with the same moment, so "Reset progress" takes it back. Which
 * request is refused, and why, is decided by the pure functions here.
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
 * Why the member's certificate is refused, checked in this order: the
 * switch and tables, a signed-in member, the course, its "Certificate"
 * setting, the member's completion. Pure.
 *
 * @param array $c on, signed_in, course, certificate, completed (booleans)
 * @return array|null array(status, error)
 */
function pg_course_certificate_refusal(array $c)
{
    $checks = array(
        array(!empty($c['on']), 404, 'off'),
        array(!empty($c['signed_in']), 401, 'sign_in'),
        array(!empty($c['course']), 404, 'course'),
        array(!empty($c['certificate']), 404, 'certificate'),
        array(!empty($c['completed']), 403, 'not_complete'),
    );

    foreach ($checks as $check) {
        if (!$check[0]) {
            return array($check[1], $check[2]);
        }
    }

    return null;
}

/**
 * Whether a read code names a completion that exists: the course of the
 * code, with "Certificate" on, and the member's completion of that very
 * course at the code's moment. A code made for another course, or a
 * completion reset and written again, does not match. Pure.
 *
 * @param array|null $parsed       pg_course_certificate_parse()
 * @param array|null $course       the course row of $parsed['course_id']
 * @param int        $completed_at the member's completion of that course (0 for none)
 * @return bool
 */
function pg_course_certificate_matches($parsed, $course, $completed_at)
{
    if (!is_array($parsed) || !is_array($course) || empty($course['certificate'])) {
        return false;
    }

    return ((int) $course['id'] === (int) $parsed['course_id'])
        && ((int) $completed_at > 0)
        && ((int) $completed_at === (int) $parsed['completed_at']);
}

/**
 * Why a check of a code is refused, checked in this order: the switch and
 * tables, the address's rate, a code that matches a completion. Pure.
 *
 * @param array $c on, throttled, valid (booleans)
 * @return array|null array(status, error)
 */
function pg_course_check_refusal(array $c)
{
    $checks = array(
        array(!empty($c['on']), 404, 'off'),
        array(empty($c['throttled']), 429, 'rate'),
        array(!empty($c['valid']), 404, 'invalid'),
    );

    foreach ($checks as $check) {
        if (!$check[0]) {
            return array($check[1], $check[2]);
        }
    }

    return null;
}

/**
 * The address of the public check, with the code when one is given.
 *
 * @param string $code
 * @return string
 */
function pg_course_certificate_check_url($code = '')
{
    return URL_SCHEME . HOSTNAME_SETTING . PATH . SOFTWARE_DIRECTORY . '/certificate.php'
        . (((string) $code !== '') ? '?code=' . rawurlencode((string) $code) : '');
}

/**
 * The name a certificate shows: the member's contact name, else the
 * username.
 *
 * @param int $user_id
 * @return string
 */
function pg_course_member_name($user_id)
{
    $row = db_item(
        "SELECT user.user_username AS username, contacts.first_name, contacts.last_name
        FROM user LEFT JOIN contacts ON contacts.id = user.user_contact
        WHERE user.user_id = '" . (int) $user_id . "'");

    if (!$row) {
        return '';
    }

    $name = trim(trim((string) $row['first_name']) . ' ' . trim((string) $row['last_name']));

    return ($name !== '') ? $name : (string) $row['username'];
}

/**
 * Reads a typed or scanned code and the completion it names.
 *
 * @param string $code
 * @return array|null name, course, completed_at, code (written as the
 *                    certificate prints it); null when it matches nothing
 */
function pg_course_certificate_lookup($code)
{
    $key = defined('ENCRYPTION_KEY') ? (string) ENCRYPTION_KEY : '';
    $parsed = pg_course_certificate_parse($code, $key);

    if ($parsed === null) {
        return null;
    }

    $course = pg_course_get($parsed['course_id']);
    $progress = ($course !== null) ? pg_course_member_progress($parsed['user_id'], $course['id']) : array('completed_at' => 0);

    if (!pg_course_certificate_matches($parsed, $course, $progress['completed_at'])) {
        return null;
    }

    return array(
        'name'         => pg_course_member_name($parsed['user_id']),
        'course'       => (string) $course['name'],
        'completed_at' => (int) $parsed['completed_at'],
        'code'         => pg_course_certificate_code($parsed['user_id'], $course['id'], $parsed['completed_at'], $key),
    );
}

/**
 * The day of a completion as the site writes a long date.
 *
 * @param int $timestamp
 * @return string
 */
function pg_course_certificate_day($timestamp)
{
    return (string) get_absolute_time(array('timestamp' => (int) $timestamp, 'type' => 'date', 'size' => 'long', 'format' => 'plain_text', 'timezone_type' => 'site'));
}

/**
 * The certificate's body: the site's logo and name, the member, the
 * course, the day, the code and the QR of the check's address. Every value
 * is escaped here.
 *
 * @param array  $view name, course, completed_at, code
 * @param string $site the site's name
 * @param string $logo the logo's address ('' for none)
 * @return string HTML
 */
function pg_course_certificate_html(array $view, $site, $logo)
{
    $check = pg_course_certificate_check_url($view['code']);
    $qr = function_exists('pg_qr_svg_data_uri') ? pg_qr_svg_data_uri($check, 0) : '';

    return '<article class="pg-certificate">'
        . (($logo !== '') ? '<img class="pg-certificate-logo" src="' . h($logo) . '" alt="' . h($site) . '">' : '')
        . (($site !== '') ? '<div class="pg-certificate-site">' . h($site) . '</div>' : '')
        . '<h1 class="pg-certificate-title">' . h(lang('Certificate of Completion')) . '</h1>'
        . '<p class="pg-certificate-lead">' . h(lang('This certifies that')) . '</p>'
        . '<p class="pg-certificate-name">' . h($view['name']) . '</p>'
        . '<p class="pg-certificate-lead">' . h(lang('has completed the course')) . '</p>'
        . '<p class="pg-certificate-course">' . h($view['course']) . '</p>'
        . '<p class="pg-certificate-day">' . h(lang(array('string' => 'Completed on {var:1}', 'vars' => pg_course_certificate_day($view['completed_at'])))) . '</p>'
        . '<footer class="pg-certificate-foot">'
        . (($qr !== '') ? '<img class="pg-certificate-qr" src="' . h($qr) . '" alt="' . h(lang('Check this certificate')) . '">' : '')
        . '<div><div class="pg-certificate-code">' . h(lang('Certificate code')) . ': <strong>' . h($view['code']) . '</strong></div>'
        . '<div class="pg-certificate-check">' . h(lang('Check it at')) . ' ' . h(pg_course_certificate_check_url()) . '</div></div>'
        . '</footer>'
        . '</article>';
}
