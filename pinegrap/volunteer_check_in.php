<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * A volunteer's check-in on the spot, built for a phone. A place's QR
 * poster opens it (?pr=<programme>&pl=<place>&c=<signed code>): it shows the
 * volunteer's shift there and one large button, "Check in" or, after a
 * check-in, "Check out", then the time. Without a poster in the address it
 * shows the scan button and the camera box (assets/js/volunteer_check_in.js)
 * that read the poster. The volunteer is known by the signed link opened in
 * this browser (volunteer.php) or by their signed-in account; nobody else's
 * shifts are ever shown (includes/fn/volunteer_hours.php).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');

require_once(PG_FUNCTIONS_DIR . '/includes/fn/volunteer_signup.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/volunteer_board_view.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/volunteer_hours.php');

header('Cache-Control: no-store, private');
header('X-Robots-Tag: noindex, nofollow');
header('Referrer-Policy: no-referrer');
header('X-Frame-Options: DENY');

$vc_throttled = pg_volunteer_page_throttled();

if ($vc_throttled) {
    header('Retry-After: ' . $vc_throttled);
    output_error(h(lang('Too many requests from your address. Please wait a moment and try again.')), 429);
}

$vc_post = (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? '')) === 'POST');

if ($vc_post) {
    validate_token_field();
    pg_request_single_values(array('pr', 'pl', 'c', 'signup', 'step'));
}

$vc_input = $vc_post ? $_POST : $_GET;
$vc_title = lang('Check in');
$vc_page = OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/volunteer_check_in.php';
$vc_programme_id = (int) (is_string($vc_input['pr'] ?? null) ? $vc_input['pr'] : 0);
$vc_place = is_string($vc_input['pl'] ?? null) ? mb_substr($vc_input['pl'], 0, 100) : '';
$vc_card = function ($body) use ($vc_title) {
    return output_header_secure(array('title' => $vc_title)) . pg_secure_card('bi-qr-code-scan', $vc_title, $body) . output_footer_secure();
};

// No poster in the address: the camera reads one.
if (!isset($vc_input['c'])) {
    $vc_texts = array(
        'scan'      => lang('Scan the poster'),
        'stop'      => lang('Stop camera'),
        'aim'       => lang('Hold the QR poster of your place in front of the camera.'),
        'no_reader' => lang('This browser cannot read QR codes. Open your phone\'s camera and point it at the poster.'),
        'no_camera' => lang('The camera could not be opened. Allow it for this site, or open your phone\'s camera and point it at the poster.'),
        'other'     => lang('This is not a volunteer poster of this site.'),
    );

    print $vc_card('<div id="vc_scan" data-texts="' . h(json_encode($vc_texts, JSON_UNESCAPED_UNICODE)) . '" data-page="' . h($vc_page) . '">'
        . '<p>' . h(lang('Scan the QR poster at your place to check in or out of your shift.')) . '</p>'
        . '<video id="vc_video" class="w-100 d-none mb-3" style="aspect-ratio:1;object-fit:cover;border-radius:var(--pg-radius-card);" muted playsinline aria-label="' . h(lang('Camera')) . '"></video>'
        . '<button type="button" class="pg-button pg-button-fill w-100" id="vc_camera"><span class="bi bi-qr-code-scan" aria-hidden="true"></span> <span class="pg-btn-label">' . h($vc_texts['scan']) . '</span></button>'
        . '<p class="small fg-secondary mt-3 mb-0" id="vc_hint" aria-live="polite"></p></div>'
        . '<script src="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/assets/js/volunteer_check_in.js?v=' . filemtime(PG_FUNCTIONS_DIR . '/assets/js/volunteer_check_in.js') . '"></script>');
    exit;
}

$vc_programme = pg_volunteer_poster_code_valid($vc_programme_id, $vc_place, $vc_input['c'], (string) ENCRYPTION_KEY) ? pg_volunteer_programme_get($vc_programme_id) : null;

if ($vc_programme === null) {
    set_response_code(404);
    print $vc_card('<p class="mb-0">' . h(lang('This poster cannot be used. Ask the coordinator for a new one.')) . '</p>');
    exit;
}

// Whose shifts: the signed links opened in this browser, and the profiles
// of the member signed in.
$vc_ids = array_map('intval', (array) ($_SESSION['volunteer']['ids'] ?? array()));

if (defined('USER_LOGGED_IN') && USER_LOGGED_IN && defined('USER_CONTACT_ID')) {
    $vc_ids = array_merge($vc_ids, pg_volunteer_account_ids((int) USER_CONTACT_ID));
}

if (!$vc_ids) {
    print $vc_card('<p class="mb-0">' . h(lang('We do not know who you are yet. Open the link in your latest e-mail from the coordinator, or sign in, then scan the poster again.')) . '</p>');
    exit;
}

$vc_now = date('Y-m-d H:i:s');
$vc_pick = pg_volunteer_scan_pick(pg_volunteer_scan_rows($vc_ids, $vc_programme_id, $vc_place, $vc_now), $vc_now);
$vc_link = $vc_page . '?pr=' . $vc_programme_id . '&pl=' . rawurlencode($vc_place) . '&c=' . rawurlencode((string) $vc_input['c']);

if ($vc_post) {
    $vc_step = (string) ($_POST['step'] ?? '');
    $vc_done = ($vc_pick !== null) && ((int) $vc_pick['id'] === (int) ($_POST['signup'] ?? 0)) && ($vc_pick['step'] === $vc_step)
        && pg_volunteer_check_write((int) $vc_pick['id'], $vc_step, 0, $vc_now);
    go($vc_link . '&done=' . ($vc_done ? $vc_step : 'changed'));
}

$vc_time = function ($time) {
    return get_absolute_time(array('timestamp' => strtotime((string) $time), 'type' => 'time', 'format' => 'plain_text'));
};
$vc_body = '';
$vc_done = is_string($_GET['done'] ?? null) ? $_GET['done'] : '';

if ($vc_done === 'changed') {
    $vc_body .= '<div class="alert theme-danger" role="alert">' . h(lang('This could not be done: the shift changed meanwhile. Please check it and try again.')) . '</div>';
}

if ($vc_pick === null) {
    $vc_body .= '<p class="mb-0">' . h(lang(array('string' => 'You have no shift at {var:1} now.', 'vars' => array($vc_place)))) . '</p>';
} else {
    $vc_body .= '<p class="fw-semibold mb-1">' . h($vc_programme['name'] . ' · ' . pg_volunteer_role_name($vc_programme, (string) $vc_pick['role'])) . '</p>'
        . '<p class="small fg-secondary mb-5">' . h(pg_volunteer_shift_when($vc_pick['shift'])) . '</p>';
    $vc_button = function ($step, $label) use ($vc_page, $vc_programme_id, $vc_place, $vc_input, $vc_pick) {
        return '<form method="post" action="' . h($vc_page) . '">' . get_token_field()
            . '<input type="hidden" name="pr" value="' . $vc_programme_id . '"><input type="hidden" name="pl" value="' . h($vc_place) . '">'
            . '<input type="hidden" name="c" value="' . h((string) $vc_input['c']) . '"><input type="hidden" name="signup" value="' . (int) $vc_pick['id'] . '">'
            . '<input type="hidden" name="step" value="' . $step . '">'
            . '<button type="submit" class="pg-button pg-button-fill w-100 py-5">' . h($label) . '</button></form>';
    };

    if ($vc_pick['step'] === 'check_in') {
        $vc_body .= $vc_button('check_in', lang('Check in'));
    } elseif ($vc_pick['step'] === 'check_out') {
        $vc_body .= '<p class="mb-3">' . h(lang(array('string' => 'Checked in at {var:1}.', 'vars' => array($vc_time($vc_pick['check_in']))))) . '</p>'
            . $vc_button('check_out', lang('Check out'));
    } elseif ($vc_pick['step'] === 'early') {
        $vc_body .= '<p class="mb-0">' . h(lang(array('string' => 'You can check in from {var:1}.', 'vars' => array($vc_time(pg_volunteer_time_add($vc_pick['shift']['start'], -PG_VOLUNTEER_CHECK_IN_EARLY)))))) . '</p>';
    } else {
        $vc_body .= '<p class="mb-0">' . h(((string) $vc_pick['check_out'] !== '')
            ? lang(array('string' => 'Checked out at {var:1}. Thank you!', 'vars' => array($vc_time($vc_pick['check_out']))))
            : lang('This shift is over. The coordinator will record your hours.')) . '</p>';
    }
}

print $vc_card($vc_body);
