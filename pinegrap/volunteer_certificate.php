<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * A volunteer's hours certificate (includes/fn/volunteer_certificate.php),
 * in certificate.php's look.
 *
 *   GET volunteer_certificate.php?p=<volunteer>&from=&to=
 *       the volunteer's certificate of the approved hours of a period they
 *       choose (this year by default), printed or saved as a PDF by the
 *       browser; only for a profile this browser holds: a signed link
 *       opened (volunteer.php) or the signed-in member's own
 *   GET volunteer_certificate.php?code=<code>
 *       the public check of a code, typed in the form or scanned from the QR
 *
 * Both are limited per address by the volunteer page's rate.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');

require_once(PG_FUNCTIONS_DIR . '/includes/fn/volunteer_signup.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/volunteer_certificate.php');

// A code in the address is not passed on as the referrer, kept by a proxy
// or indexed.
header('X-Robots-Tag: noindex, nofollow');
header('Referrer-Policy: no-referrer');
header('Cache-Control: no-store, private');
header('X-Frame-Options: DENY');

$vf_on = pg_volunteer_ready();
$vf_throttled = $vf_on ? pg_volunteer_page_throttled() : 0;
$vf_self = pg_volunteer_certificate_url();
$vf_site = defined('ORGANIZATION_NAME') ? (string) ORGANIZATION_NAME : '';
$vf_status = 200;
$vf_box = function ($title, $body) {
    return '<div class="pg-certificate-box"><h1 class="pg-certificate-heading">' . h($title) . '</h1>' . $body . '</div>';
};

if ($vf_throttled) {
    header('Retry-After: ' . $vf_throttled);
    $vf_status = 429;
    $vf_body = $vf_box(lang('Certificate'), '<p>' . h(lang('Too many requests from your address. Please wait a moment and try again.')) . '</p>');
} elseif (!$vf_on) {
    $vf_status = 404;
    $vf_body = $vf_box(lang('Certificate'), '<p>' . h(lang('This page is not available.')) . '</p>');
} elseif (isset($_GET['p'])) {
    // The profiles this browser holds: a signed link opened, or the
    // signed-in member's own.
    $vf_id = is_string($_GET['p']) ? (int) $_GET['p'] : 0;
    $vf_member = (defined('USER_LOGGED_IN') && USER_LOGGED_IN && defined('USER_CONTACT_ID')) ? pg_volunteer_account_ids((int) USER_CONTACT_ID) : array();
    $vf_holds = in_array($vf_id, array_map('intval', (array) ($_SESSION['volunteer']['ids'] ?? array())), true) || in_array($vf_id, $vf_member, true);
    $vf_person = $vf_holds ? pg_volunteer_certificate_person($vf_id) : null;

    if (($vf_person === null) || ($vf_person['state'] === 'left')) {
        $vf_status = 404;
        $vf_body = $vf_box(lang('Certificate'), '<p>' . h(lang('This link cannot be used. Please open the link in your latest e-mail from the coordinator, or contact us.')) . '</p>');
    } else {
        $vf_period = pg_volunteer_certificate_period($_GET, date('Y-m-d'));
        $vf_minutes = ($vf_period['error'] === '') ? pg_volunteer_approved_minutes($vf_person['id'], $vf_period['from'], $vf_period['to']) : 0;
        $vf_form = '<form method="get" action="' . h($vf_self) . '" class="d-flex flex-wrap align-items-end justify-content-end gap-2">'
            . '<input type="hidden" name="p" value="' . $vf_person['id'] . '">'
            . '<span><label class="form-label mb-1" for="vf_from">' . h(lang('From')) . '</label><input type="date" class="form-control" id="vf_from" name="from" value="' . h($vf_period['from']) . '" max="' . date('Y-m-d') . '" required></span>'
            . '<span><label class="form-label mb-1" for="vf_to">' . h(lang('To')) . '</label><input type="date" class="form-control" id="vf_to" name="to" value="' . h($vf_period['to']) . '" max="' . date('Y-m-d') . '" required></span>'
            . '<button type="submit" class="pg-certificate-button">' . h(lang('Show')) . '</button>'
            . (($vf_minutes > 0) ? '<button type="button" class="pg-certificate-button" onclick="window.print()"><i class="bi bi-printer me-1" aria-hidden="true"></i>' . h(lang('Print or save as PDF')) . '</button>' : '')
            . '</form>';

        if ($vf_period['error'] !== '') {
            $vf_status = 400;
            $vf_note = lang('Choose a period of at most ten years that ends today or earlier.');
        } elseif ($vf_minutes === 0) {
            $vf_note = lang('No approved hours in this period. Hours count once the coordinator approves them.');
        } else {
            $vf_note = '';
        }

        if ($vf_note !== '') {
            $vf_body = '<div class="pg-certificate-tools">' . $vf_form . '</div>' . $vf_box(lang('Certificate'), '<p class="mb-0">' . h($vf_note) . '</p>');
        } else {
            $vf_logo = (defined('ORGANIZATION_LOGO') && function_exists('pg_resolve_config_image')) ? pg_resolve_config_image(ORGANIZATION_LOGO) : null;
            $vf_body = '<div class="pg-certificate-tools">' . $vf_form . '</div>'
                . pg_volunteer_certificate_html(array(
                    'name'      => $vf_person['name'],
                    'programme' => $vf_person['programme'],
                    'from'      => $vf_period['from'],
                    'to'        => $vf_period['to'],
                    'minutes'   => $vf_minutes,
                    'code'      => pg_volunteer_certificate_code($vf_person['id'], $vf_period['from'], $vf_period['to'], $vf_minutes, defined('ENCRYPTION_KEY') ? (string) ENCRYPTION_KEY : ''),
                ), $vf_site, is_array($vf_logo) ? (string) $vf_logo['url'] : '');
        }
    }
} else {
    $vf_code = (isset($_GET['code']) && is_string($_GET['code'])) ? trim(substr($_GET['code'], 0, 64)) : '';
    $vf_result = '';

    if ($vf_code !== '') {
        $vf_found = pg_volunteer_certificate_lookup($vf_code);

        if ($vf_found === null) {
            $vf_status = 404;
            $vf_result = '<div class="alert theme-warning" role="alert">' . h(lang('No certificate has this code.')) . '</div>';
        } else {
            $vf_result = '<div class="alert theme-success" role="status">' . h(lang('This certificate is valid.')) . '</div>'
                . '<dl class="pg-certificate-found">'
                . '<dt>' . h(lang('Name')) . '</dt><dd>' . h($vf_found['name']) . '</dd>'
                . '<dt>' . h(lang('Programme')) . '</dt><dd>' . h($vf_found['programme']) . '</dd>'
                . '<dt>' . h(lang('Approved hours')) . '</dt><dd>' . h(pg_volunteer_hours_number($vf_found['minutes'])) . '</dd>'
                . '<dt>' . h(lang('Period')) . '</dt><dd>' . h(pg_volunteer_certificate_day($vf_found['from']) . ' – ' . pg_volunteer_certificate_day($vf_found['to'])) . '</dd>'
                . '<dt>' . h(lang('Certificate code')) . '</dt><dd>' . h($vf_found['code']) . '</dd>'
                . '</dl>';
        }
    }

    $vf_body = $vf_box(lang('Check a certificate'), '<form method="get" action="' . h($vf_self) . '" class="pg-certificate-form">'
        . '<label class="form-label" for="vf_code">' . h(lang('Certificate code')) . '</label>'
        . '<div class="d-flex gap-2"><input type="text" class="form-control" id="vf_code" name="code" value="' . h($vf_code) . '" maxlength="64" autocomplete="off" spellcheck="false" required>'
        . '<button type="submit" class="pg-certificate-button">' . h(lang('Check')) . '</button></div></form>'
        . $vf_result);
}

http_response_code($vf_status);

$vf_software = OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY;
$vf_version = function ($path) {
    return (string) @filemtime(PG_FUNCTIONS_DIR . $path);
};

echo '<!DOCTYPE html>
<html lang="' . h(lang(array('info' => ''))) . '">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="robots" content="noindex, nofollow">
<meta name="referrer" content="no-referrer">
<title>' . h(lang('Certificate') . (($vf_site !== '') ? ' · ' . $vf_site : '')) . '</title>
<link rel="stylesheet" href="' . $vf_software . '/assets/lib/bootstrap-6/css/bootstrap.min.css?v=' . $vf_version('/assets/lib/bootstrap-6/css/bootstrap.min.css') . '">
<link rel="stylesheet" href="' . $vf_software . '/assets/fonts/bootstrap-icons/bootstrap-icons.min.css?v=' . $vf_version('/assets/fonts/bootstrap-icons/bootstrap-icons.min.css') . '">
<link rel="stylesheet" href="' . $vf_software . '/assets/css/certificate.css?v=' . $vf_version('/assets/css/certificate.css') . '">
</head>
<body class="pg-certificate-page">
<main>' . $vf_body . '</main>
</body>
</html>';
