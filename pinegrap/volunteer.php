<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * A volunteer's own shifts. Opened from the signed link a coordinator sends
 * (?v=<volunteer id>&t=<token>) without an account: that volunteer's
 * programme, their next shifts with Cancel, Offer my place or Keep my place,
 * the shifts they fit with Sign up, Join the waitlist or Take this place,
 * their documents' dates and their "Shift calls" choice. It also takes the
 * buttons of the my_account widget's "volunteering" section for a signed-in
 * member (?p= one of the member's own profiles) and sends them back with a
 * notice (includes/fn/volunteer_signup.php).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');

require_once(PG_FUNCTIONS_DIR . '/includes/fn/volunteer_page_view.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/volunteer_messages.php');

// A personal answer: never kept by a cache, never indexed, never framed,
// and the signed address is not handed to another site as a referrer.
header('Cache-Control: no-store, private');
header('X-Robots-Tag: noindex, nofollow');
header('Referrer-Policy: no-referrer');
header('X-Frame-Options: DENY');

$vo_post = (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? '')) === 'POST');
$vo_throttled = pg_volunteer_page_throttled();

if ($vo_throttled) {
    header('Retry-After: ' . $vo_throttled);
    output_error(h(lang('Too many requests from your address. Please wait a moment and try again.')), 429);
}

if ($vo_post) {
    validate_token_field();
    pg_request_single_values(array('v', 't', 'p', 'shift', 'action', 'role', 'target', 'send_to', 'calls', 'channel'));
}

$vo_input = $vo_post ? $_POST : $_GET;

// One posted button of the page: the "Shift calls" choice, or an action on a
// shift.
$vo_act = function ($volunteer_id) {
    if ((string) ($_POST['action'] ?? '') === 'calls') {
        return pg_volunteer_calls_save($volunteer_id, !empty($_POST['calls']), (string) ($_POST['channel'] ?? ''));
    }

    return pg_volunteer_self_act($volunteer_id, (string) ($_POST['action'] ?? ''), (int) ($_POST['shift'] ?? 0), (string) ($_POST['role'] ?? ''), (int) ($_POST['target'] ?? 0));
};
$vo_title = lang('My volunteering');
$vo_page = OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/volunteer.php';
$vo_linked = isset($vo_input['t']) ? pg_volunteer_link_find($vo_input['v'] ?? '', $vo_input['t']) : null;

// The buttons of the my account section: the member's own profiles only.
if ($vo_linked === null) {
    $vo_user = (defined('USER_LOGGED_IN') && USER_LOGGED_IN && defined('USER_CONTACT_ID')) ? (int) USER_CONTACT_ID : 0;
    $vo_ids = pg_volunteer_account_ids($vo_user);
    $vo_profile = (int) ($vo_input['p'] ?? 0);

    if (!$vo_post || !in_array($vo_profile, $vo_ids, true)) {
        set_response_code(404);
        print output_header_secure(array('title' => $vo_title))
            . pg_secure_card('bi-person-heart', $vo_title, '<p>' . h(lang('This link cannot be used. Please open the link in your latest e-mail from the coordinator, or contact us.')) . '</p>')
            . output_footer_secure();
        exit;
    }

    $vo_answer = $vo_act($vo_profile);
    $vo_text = pg_volunteer_answer_text($vo_answer);
    include_once('liveform.class.php');
    $vo_notices = new liveform('my_account');
    $vo_text[1] ? $vo_notices->add_notice(h($vo_text[0])) : $vo_notices->add_error(h($vo_text[0]));
    $vo_account = (string) get_page_type_url('my account');
    go(pg_safe_redirect_path($_POST['send_to'] ?? '', $vo_account));
}

// The signed link lets this session open the volunteer's document files
// (get_file.php).
$_SESSION['volunteer']['ids'] = array_values(array_unique(array_merge((array) ($_SESSION['volunteer']['ids'] ?? array()), array($vo_linked))));
$vo_link = $vo_page . '?v=' . $vo_linked . '&t=' . (string) $vo_input['t'];
$vo_content = '';

if ($vo_post) {
    $vo_answer = $vo_act($vo_linked);
    go($vo_link . '&done=' . rawurlencode($vo_answer));
}

if (is_string($_GET['done'] ?? null)) {
    $vo_text = pg_volunteer_answer_text($_GET['done']);
    $vo_content .= '<div class="alert ' . ($vo_text[1] ? 'theme-success" role="status' : 'theme-danger" role="alert') . '">' . h($vo_text[0]) . '</div>';
}

$vo_view = pg_volunteer_self_view($vo_linked, date('Y-m-d H:i:s'));
$vo_content .= ($vo_view !== null)
    ? pg_volunteer_page_html(array($vo_view), array(
        'action' => $vo_page,
        'hidden' => get_token_field() . '<input type="hidden" name="v" value="' . $vo_linked . '"><input type="hidden" name="t" value="' . h((string) $vo_input['t']) . '">',
    ), pg_volunteer_page_secure_classes())
    : '<p>' . h(lang('This link cannot be used. Please open the link in your latest e-mail from the coordinator, or contact us.')) . '</p>';

print output_header_secure(array('title' => $vo_title))
    . '<main class="container py-7 md:py-12" style="max-width: 44rem;">'
    . '<h1 class="h4 mb-7"><i class="bi bi-person-heart me-3" aria-hidden="true"></i>' . h($vo_title) . '</h1>'
    . $vo_content
    . '</main>'
    . output_footer_secure();
