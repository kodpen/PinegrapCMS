<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * A customer's support request, opened from the signed link in the box's
 * automatic answer (?c=<conversation id>&t=<token>) without an account,
 * and from the requests section of the my_account widget: the request's
 * number, subject and state, the mails in and the answers out, and a Reply
 * box whose answer is taken in as the customer's mail
 * (includes/fn/support_requests.php). Opening the page changes nothing.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');

require_once(PG_FUNCTIONS_DIR . '/includes/fn/support_requests.php');

// A personal answer: never kept by a cache, never indexed, never framed,
// and the signed address is not handed to another site as a referrer.
header('Cache-Control: no-store, private');
header('X-Robots-Tag: noindex, nofollow');
header('Referrer-Policy: no-referrer');
header('X-Frame-Options: DENY');

$sr_post = (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? '')) === 'POST');
$sr_throttled = pg_support_request_throttled($sr_post);

if ($sr_throttled) {
    header('Retry-After: ' . $sr_throttled);
    output_error(h(lang('Too many requests from your address. Please wait a moment and try again.')), 429);
}

$sr_found = pg_support_request_find($_GET['c'] ?? '', $_GET['t'] ?? '');
$sr_title = lang('Your request');

if ($sr_found === null) {
    set_response_code(404);
    print output_header_secure(array('title' => $sr_title))
        . pg_secure_card('bi-life-preserver', $sr_title, '<p>' . h(lang('This link cannot be used. Please open the link in our e-mail again, or contact us.')) . '</p>')
        . output_footer_secure();
    exit;
}

$sr_conversation = $sr_found['conversation'];
$sr_link = pg_support_request_base(false) . '?c=' . (int) $sr_conversation['id'] . '&t=' . (string) $_GET['t'];
$sr_form = array('text' => '', 'error' => '', 'sent' => isset($_GET['sent']));

if ($sr_post) {
    validate_token_field();

    $sr_check = pg_support_request_reply_check($_POST['reply'] ?? '');
    $sr_form['text'] = $sr_check['text'];
    $sr_form['error'] = $sr_check['error'];

    if (($sr_form['error'] === '') && !pg_support_request_reply($sr_conversation, $sr_found['box'], $sr_check['text'])) {
        $sr_form['error'] = lang('Your answer could not be sent. Please try again later, or answer our e-mail.');
    }

    if ($sr_form['error'] === '') {
        // Back to the page by GET, so a reload does not send the answer twice.
        header('Location: ' . $sr_link . '&sent=1', true, 303);
        exit;
    }

    $sr_conversation = db_item("SELECT * FROM ws_mail_conversations WHERE id = '" . (int) $sr_conversation['id'] . "'") ?: $sr_conversation;
}

print output_header_secure(array('title' => $sr_title))
    . pg_support_request_page_style()
    . pg_support_request_page_html($sr_conversation, pg_support_request_items($sr_conversation), $sr_form + array(
        'action'      => $sr_link,
        'token_field' => get_token_field(),
        'date_format' => pg_sw_default_date_format('date and time'),
    ))
    . output_footer_secure();
