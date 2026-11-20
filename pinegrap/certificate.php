<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The course certificate (includes/fn/course_certificate.php).
 *
 *   GET certificate.php?course=<id>   the signed-in member's certificate of
 *                                     a course they completed, printed or
 *                                     saved as a PDF by the browser
 *   GET certificate.php?code=<code>   the public check of a code, typed in
 *                                     the form or scanned from the QR
 *
 * A visitor asking for a certificate is sent to the sign-in page and back.
 * A certificate is refused, in the order pg_course_certificate_refusal()
 * checks, while the "Courses" switch is off, for a course that is missing
 * or has "Certificate" off, and to a member who has not completed it. A
 * check is rate limited per address (pg_course_throttled('check')) and
 * shows the name, the course and the day only for a code that matches a
 * completion (pg_course_certificate_matches()).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');

require_once(PG_FUNCTIONS_DIR . '/includes/fn/course_blocks.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/course_certificate.php');

// A code in the address is not passed on as the referrer, kept by a proxy
// or indexed.
header('X-Robots-Tag: noindex, nofollow');
header('Referrer-Policy: no-referrer');
header('Cache-Control: no-store, private');
header('X-Frame-Options: DENY');

$on = pg_course_ready() && pg_course_switch_on();
$self = PATH . SOFTWARE_DIRECTORY . '/certificate.php';
$site = defined('ORGANIZATION_NAME') ? (string) ORGANIZATION_NAME : '';
$status = 200;
$body = '';

if (isset($_GET['course'])) {
    $course = (is_scalar($_GET['course']) && $on) ? pg_course_get((int) $_GET['course']) : null;
    $progress = ($course !== null && USER_LOGGED_IN) ? pg_course_member_progress((int) USER_ID, $course['id']) : array('completed_at' => 0);

    $refusal = pg_course_certificate_refusal(array(
        'on' => $on, 'signed_in' => USER_LOGGED_IN, 'course' => $course !== null,
        'certificate' => ($course !== null) && !empty($course['certificate']), 'completed' => $progress['completed_at'] > 0,
    ));

    if ($refusal && ($refusal[1] === 'sign_in')) {
        header('Location: ' . URL_SCHEME . HOSTNAME . pg_tr_script_url(PATH . SOFTWARE_DIRECTORY . '/registration_entrance.php?send_to=' . urlencode(REQUEST_URL)));
        exit;
    }

    if ($refusal) {
        $status = $refusal[0];
        $messages = array(
            'off'          => lang('This page is not available.'),
            'course'       => lang('This course gives no certificate.'),
            'certificate'  => lang('This course gives no certificate.'),
            'not_complete' => lang('The certificate is given once every lesson of the course is done.'),
        );
        $body = '<div class="pg-certificate-box"><h1 class="pg-certificate-heading">' . h(lang('Certificate')) . '</h1>'
            . '<p>' . h($messages[$refusal[1]]) . '</p></div>';
    } else {
        $key = defined('ENCRYPTION_KEY') ? (string) ENCRYPTION_KEY : '';
        $logo = (defined('ORGANIZATION_LOGO') && function_exists('pg_resolve_config_image')) ? pg_resolve_config_image(ORGANIZATION_LOGO) : null;

        $body = '<div class="pg-certificate-tools"><button type="button" class="pg-certificate-button" onclick="window.print()">'
            . '<i class="bi bi-printer me-1" aria-hidden="true"></i>' . h(lang('Print or save as PDF')) . '</button></div>'
            . pg_course_certificate_html(array(
                'name'         => pg_course_member_name((int) USER_ID),
                'course'       => (string) $course['name'],
                'completed_at' => (int) $progress['completed_at'],
                'code'         => pg_course_certificate_code((int) USER_ID, $course['id'], $progress['completed_at'], $key),
            ), $site, is_array($logo) ? (string) $logo['url'] : '');
    }
} else {
    $code = (isset($_GET['code']) && is_string($_GET['code'])) ? trim(substr($_GET['code'], 0, 64)) : '';
    $result = '';

    if (!$on) {
        $status = 404;
        $result = '<p>' . h(lang('This page is not available.')) . '</p>';
    } elseif ($code !== '') {
        $throttled = pg_course_throttled('check');
        $found = $throttled ? null : pg_course_certificate_lookup($code);
        $refusal = pg_course_check_refusal(array('on' => $on, 'throttled' => $throttled, 'valid' => $found !== null));

        if ($refusal) {
            $status = $refusal[0];
            $result = '<div class="alert theme-warning" role="alert">' . h(($refusal[1] === 'rate')
                ? lang('Too many checks from your address. Try again in a few minutes.')
                : lang('No certificate has this code.')) . '</div>';
        } else {
            $result = '<div class="alert theme-success" role="status">' . h(lang('This certificate is valid.')) . '</div>'
                . '<dl class="pg-certificate-found">'
                . '<dt>' . h(lang('Name')) . '</dt><dd>' . h($found['name']) . '</dd>'
                . '<dt>' . h(lang('Course')) . '</dt><dd>' . h($found['course']) . '</dd>'
                . '<dt>' . h(lang('Completed on')) . '</dt><dd>' . h(pg_course_certificate_day($found['completed_at'])) . '</dd>'
                . '<dt>' . h(lang('Certificate code')) . '</dt><dd>' . h($found['code']) . '</dd>'
                . '</dl>';
        }
    }

    $body = '<div class="pg-certificate-box"><h1 class="pg-certificate-heading">' . h(lang('Check a certificate')) . '</h1>'
        . ($on ? '<form method="get" action="' . h($self) . '" class="pg-certificate-form">'
            . '<label class="form-label" for="pg-certificate-code">' . h(lang('Certificate code')) . '</label>'
            . '<div class="d-flex gap-2"><input type="text" class="form-control" id="pg-certificate-code" name="code" value="' . h($code) . '" maxlength="64" autocomplete="off" spellcheck="false" required>'
            . '<button type="submit" class="pg-certificate-button">' . h(lang('Check')) . '</button></div></form>' : '')
        . $result . '</div>';
}

http_response_code($status);

$software = OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY;
$version = function ($path) {
    return (string) @filemtime(PG_FUNCTIONS_DIR . $path);
};

echo '<!DOCTYPE html>
<html lang="' . h(lang(array('info' => ''))) . '">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="robots" content="noindex, nofollow">
<meta name="referrer" content="no-referrer">
<title>' . h(lang('Certificate') . (($site !== '') ? ' · ' . $site : '')) . '</title>
<link rel="stylesheet" href="' . $software . '/assets/lib/bootstrap-6/css/bootstrap.min.css?v=' . $version('/assets/lib/bootstrap-6/css/bootstrap.min.css') . '">
<link rel="stylesheet" href="' . $software . '/assets/fonts/bootstrap-icons/bootstrap-icons.min.css?v=' . $version('/assets/fonts/bootstrap-icons/bootstrap-icons.min.css') . '">
<link rel="stylesheet" href="' . $software . '/assets/css/certificate.css?v=' . $version('/assets/css/certificate.css') . '">
</head>
<body class="pg-certificate-page">
<main>' . $body . '</main>
</body>
</html>';
