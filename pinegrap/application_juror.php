<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * An outside juror's page, opened from the signed link of their e-mail with
 * no account (includes/fn/application_jurors.php): the queue of "My
 * reviews" and its scoring screen in the panel's look, with no menu and no
 * top bar, the programme's name as its head.
 *
 *   GET  application_juror.php?t=<token>              the queue
 *   GET  application_juror.php?t=<token>&review=<id>  the scoring screen
 *   POST application_juror.php                        t, review, the scores
 *
 * The token is the credential and opens this juror's reviews only, checked
 * on every read; the session carries only the form token. A removed juror's
 * link says it was withdrawn; once the programme is closed and its round
 * has ended, it says it has expired. Counted on the firewall's sensitive
 * allowance (waf.php).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/application_my_reviews.php');

// A personal page: never kept by a cache, never indexed, never framed, and
// the signed address is not handed to another site as a referrer.
header('Cache-Control: no-store, private');
header('X-Robots-Tag: noindex, nofollow');
header('Referrer-Policy: no-referrer');
header('X-Frame-Options: DENY');

$aj_post = (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? '')) === 'POST');
$aj_autosave = $aj_post && isset($_POST['autosave']);
$aj_input = $aj_post ? $_POST : $_GET;
$aj_token = is_string($aj_input['t'] ?? null) ? $aj_input['t'] : '';
$aj_reviewer = pg_application_juror_find($aj_token);
$aj_program = $aj_reviewer ? pg_application_program_get($aj_reviewer['program_id']) : null;
$aj_ends_at = $aj_program ? (int) ($aj_program['rounds'][max(1, $aj_program['round']) - 1]['ends_at'] ?? 0) : 0;
$aj_state = pg_application_juror_link_state($aj_program ? $aj_reviewer : null, $aj_program['status'] ?? 0, $aj_ends_at, time());

if ($aj_autosave) {
    $aj_session = (string) ($_SESSION['software']['token'] ?? '');

    if (($aj_session === '') || !is_string($_POST['token'] ?? null) || !hash_equals($aj_session, $_POST['token'])) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(array('ok' => false, 'message' => lang('Your session expired. Reload the page and try again.')));
        exit;
    }

    if ($aj_state !== 'open') {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(array('ok' => false, 'message' => lang('This link can no longer be used.')));
        exit;
    }
}

if ($aj_state !== 'open') {
    $aj_words = array(
        'invalid'   => array(404, lang('This link is not valid.'), lang('Check that the whole address from the e-mail was opened, or ask the programme\'s managers for a new link.')),
        'withdrawn' => array(403, lang('This link was withdrawn.'), lang('You are no longer a reviewer of this programme. The reviews you sent are kept.')),
        'expired'   => array(410, lang('This link has expired.'), lang('The programme is closed and its round has ended, so there is nothing more to score.')),
    );
    $aj_word = $aj_words[$aj_state];
    set_response_code($aj_word[0]);
    print output_header_secure(array('title' => $aj_word[1]))
        . pg_secure_card('bi-clipboard-check', ($aj_state === 'invalid') ? $aj_word[1] : $aj_program['name'], (($aj_state !== 'invalid') ? '<p class="fw-semibold">' . h($aj_word[1]) . '</p>' : '') . '<p class="mb-0">' . h($aj_word[2]) . '</p>')
        . output_footer_secure();
    exit;
}

$liveform = new liveform('application_juror');
$aj_page = OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/application_juror.php';
$aj_url = function ($review_id) use ($aj_page, $aj_token) {
    return $aj_page . '?t=' . $aj_token . (($review_id > 0) ? '&review=' . (int) $review_id : '');
};
$aj_id = (int) ($aj_input['review'] ?? 0);
$aj_review = ($aj_id > 0) ? pg_application_juror_review($aj_id, (int) $aj_reviewer['id']) : null;

if (($aj_id > 0) && ($aj_review === null)) {
    if ($aj_autosave) {
        http_response_code(404);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(array('ok' => false, 'message' => lang('This review is not yours, or it was taken back.')));
        exit;
    }

    $liveform->add_error(h(lang('This review is not yours, or it was taken back.')));
    go($aj_url(0));
}

if ($aj_post) {
    if (!$aj_autosave) {
        validate_token_field();
    }

    pg_application_review_post($aj_review, $liveform, $aj_url, $aj_autosave);
}

db_q("UPDATE application_reviewers SET last_seen_at = UNIX_TIMESTAMP() WHERE id = ?", array((int) $aj_reviewer['id']));

$aj_head = '<header class="mb-5"><h1 class="pg-title-screen mb-1">' . h($aj_program['name']) . '</h1>'
    . '<p class="pg-text-2 mb-0">' . h(lang(array('string' => 'Reviewing as {var:1}. Others\' scores stay hidden.', 'vars' => array((string) $aj_reviewer['name'])))) . '</p></header>';

if ($aj_review === null) {
    $aj_programs = pg_application_juror_programs((int) $aj_reviewer['id']);
    $aj_body = $aj_programs
        ? pg_application_review_cards_html($aj_programs, $aj_url)
        : '<section class="pg-card p-5 mb-5"><p class="pg-text-2 mb-0">' . h(lang('No entries were given to you in this round yet.')) . '</p></section>';
    $aj_title = $aj_program['name'];
} else {
    $aj_title = pg_application_review_entry_name($aj_program, $aj_review['entry'] + array('entry_id' => $aj_review['entry']['id']));
    $aj_choices = pg_application_category_choices($aj_program);
    $aj_category = (string) $aj_review['entry']['category'];
    $aj_body = '<nav class="mb-4"><a class="pg-button pg-button-quiet" href="' . h($aj_url(0)) . '"><i class="bi bi-chevron-left" aria-hidden="true"></i>' . h(lang('All entries')) . '</a></nav>'
        . '<div class="d-flex flex-wrap align-items-center gap-2 mb-5"><h2 class="pg-title-section mb-0">' . h($aj_title) . '</h2>'
        . (pg_application_review_pills()[(int) $aj_review['review']['status']] ?? '')
        . (($aj_category !== '') ? '<span class="pg-text-2">' . h($aj_choices[$aj_category] ?? $aj_category) . '</span>' : '') . '</div>'
        . pg_application_review_screen_html($aj_review, $aj_page, '<input type="hidden" name="t" value="' . h($aj_token) . '">', $aj_url);
}

print output_header_secure(array('title' => $aj_title, 'extra classes' => 'form pg-measure-edit'))
    . '<main id="content" class="container-fluid py-6">' . $aj_head . $liveform->get_messages() . $aj_body . '</main>'
    . output_footer_secure();

$liveform->remove_form();
