<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The agency summary's page for the customer, opened from the signed link
 * the agency copies from the dashboard's set-up card
 * (includes/fn/launch_summary.php): the site's name, the set-up steps (the
 * done ones, then the ones still waiting), and each "What we need from you" line with the customer's text
 * answer and its Send button.
 *
 *   GET  launch_summary.php?t=<token>   the summary
 *   POST launch_summary.php             t, line, answer, token
 *
 * The token is the credential, checked on every read; the session carries
 * only the form token. The page shows only what the agency wrote and the
 * steps' states, never a setting's value. A withdrawn link says so; an
 * ended one says it has expired. Drawn in the panel's look with no menu and
 * no top bar, not in the site's theme, since the site may not be live yet.
 * Counted on the firewall's sensitive allowance (waf.php).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');

// A page for one customer: never kept by a cache, never indexed, never
// framed, and the signed address is not handed to another site.
header('Cache-Control: no-store, private');
header('X-Robots-Tag: noindex, nofollow');
header('Referrer-Policy: no-referrer');
header('X-Frame-Options: DENY');

$ls_post = (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? '')) === 'POST');
$ls_input = $ls_post ? $_POST : $_GET;
$ls_token = is_string($ls_input['t'] ?? null) ? $ls_input['t'] : '';
$ls_state = pg_launch_summary_ready() ? pg_launch_summary_link_state(pg_launch_summary_link(), $ls_token, time()) : 'invalid';

if ($ls_state !== 'open') {
    $ls_words = array(
        'invalid'   => array(404, lang('This link is not valid.'), lang('Check that the whole address was opened, or ask the agency for a new link.')),
        'withdrawn' => array(403, lang('This link was withdrawn.'), lang('Ask the agency for a new link. The answers you sent are kept.')),
        'expired'   => array(410, lang('This link has expired.'), lang('Ask the agency for a new link. The answers you sent are kept.')),
    );
    $ls_word = $ls_words[$ls_state];
    set_response_code($ls_word[0]);
    print output_header_secure(array('title' => $ls_word[1]))
        . pg_secure_card('bi-clipboard-check', $ls_word[1], '<p class="mb-0">' . h($ls_word[2]) . '</p>')
        . output_footer_secure();
    exit;
}

$liveform = new liveform('launch_summary');
$ls_page = OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/launch_summary.php?t=' . $ls_token;

if ($ls_post) {
    validate_token_field();

    $ls_line = (int) ($_POST['line'] ?? 0);
    $ls_saved = pg_launch_summary_answer_save($ls_line, $_POST['answer'] ?? '');
    $ls_notes = array(
        'saved'   => lang('Thank you. Your answer was sent to the agency.'),
        'same'    => lang('This answer was already sent.'),
        'empty'   => lang('Write an answer before sending it.'),
        'missing' => lang('This line was taken off the summary.'),
    );

    if ($ls_saved === 'saved' || $ls_saved === 'same') {
        $liveform->add_notice(h($ls_notes[$ls_saved]));
    } else {
        $liveform->add_error(h($ls_notes[$ls_saved]));
    }

    go($ls_page . '#line-' . $ls_line);
}

// The steps' states only: the card's rows judged now, of which only the
// names and whether each is done are shown.
$ls_stored = pg_launch_journey_stored();
$ls_purpose = ($ls_stored['purpose'] !== '') ? $ls_stored['purpose'] : 'other';
$ls_card = pg_launch_journey_card($ls_purpose, pg_launch_journey_facts(array('id' => 0), $ls_purpose), $ls_stored['hidden']);
$ls_all = pg_launch_summary_steps($ls_card['rows']);
$ls_done = count(pg_launch_summary_done_steps($ls_card['rows']));
$ls_site = (defined('ORGANIZATION_NAME') && ORGANIZATION_NAME !== '') ? (string) ORGANIZATION_NAME : (string) HOSTNAME_SETTING;

$ls_steps = '';
foreach ($ls_all as $ls_step) {
    $ls_steps .= '<li class="pg-list-row">'
        . ($ls_step['done']
            ? '<span class="pg-icon-tile pg-tone-teal" aria-hidden="true"><i class="bi bi-check2"></i></span>'
            : '<span class="pg-icon-tile pg-tone-gray" aria-hidden="true"><i class="bi bi-hourglass-split"></i></span>')
        . '<div class="pg-list-row-main"><div class="pg-list-row-head"><span class="pg-list-row-title">' . h($ls_step['name']) . '</span>'
        . ($ls_step['done']
            ? '<span class="pg-pill pg-tone-teal">' . h(lang('Done')) . '</span>'
            : '<span class="pg-pill pg-tone-gray">' . h(lang('Waiting')) . '</span>')
        . '</div></div></li>';
}

$ls_lines = '';
foreach (pg_launch_summary_lines() as $ls_row) {
    $ls_id = (int) $ls_row['id'];
    $ls_field = 'ls_answer_' . $ls_id;
    $ls_answered = ((int) $ls_row['answered_at'] > 0);

    $ls_lines .= '<li class="pg-list-row" id="line-' . $ls_id . '"><div class="pg-list-row-main">'
        . '<form method="post" action="' . h(OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/launch_summary.php') . '">'
        . get_token_field()
        . '<input type="hidden" name="t" value="' . h($ls_token) . '"><input type="hidden" name="line" value="' . $ls_id . '">'
        . '<div class="pg-list-row-head"><label class="pg-list-row-title" for="' . $ls_field . '">' . h($ls_row['line_text']) . '</label>'
        . ($ls_answered ? '<span class="pg-pill pg-tone-teal">' . h(lang('Answered')) . '</span>' : '') . '</div>'
        . '<textarea class="pg-input mt-2" id="' . $ls_field . '" name="answer" rows="3" maxlength="' . PG_LAUNCH_SUMMARY_ANSWER_MAX . '" required>' . h((string) $ls_row['answer']) . '</textarea>'
        . '<div class="d-flex justify-content-end mt-2"><button type="submit" class="pg-button pg-button-fill pg-button-sm">' . h(lang('Send')) . '</button></div>'
        . '</form></div></li>';
}

$ls_body = '<section class="pg-card p-5 mb-5">'
    . '<h2 class="pg-title-section mb-1">' . h(lang('Set-up steps')) . '</h2>'
    . '<p class="pg-text-2 mb-3">' . h(lang(array('string' => '{var:1} of {var:2} steps done', 'vars' => array($ls_done, count($ls_all))))) . '</p>'
    . ($ls_steps !== '' ? '<ul class="list-unstyled mb-0">' . $ls_steps . '</ul>' : '')
    . '<h2 class="pg-title-section mt-6 mb-1">' . h(lang('What we need from you')) . '</h2>'
    . '<p class="pg-text-2 mb-3">' . h(lang('Write your answer under each line and send it; you can change it later.')) . '</p>'
    . ($ls_lines !== '' ? '<ul class="list-unstyled mb-0">' . $ls_lines . '</ul>' : '<p class="pg-text-2 mb-0">' . h(lang('Nothing is needed from you right now.')) . '</p>')
    . '</section>';

print output_header_secure(array('title' => $ls_site, 'extra classes' => 'form pg-measure-edit'))
    . '<main id="content" class="container-fluid py-6">'
    . '<header class="mb-5"><h1 class="pg-title-screen mb-1">' . h($ls_site) . '</h1>'
    . '<p class="pg-text-2 mb-0">' . h(lang('Your website\'s set-up, shared by the agency building it.')) . '</p></header>'
    . $liveform->get_messages() . $ls_body . '</main>'
    . output_footer_secure();

$liveform->remove_form();
