<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The answer page of a satisfaction survey, opened from the signed links of
 * its e-mail or SMS without an account (includes/fn/feedback_answer.php).
 *
 * ?t=<answer token>[&s=<score>] shows the question with the score chosen.
 * Opening the link writes nothing, so a mail scanner's visit never counts:
 * the page's own script posts the chosen score (action=score), and "Send"
 * posts the score, the comment and the extra answers (action=send). After
 * the answer: a thank-you line, the review invitation (the same for every
 * score) and "Change my answer" for 7 days. ?x=<stop token> asks "Don't ask
 * me again", written only by its POST (action=stop). Every POST carries the
 * form token. Counted on the firewall's sensitive allowance (waf.php).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');

require_once(PG_FUNCTIONS_DIR . '/includes/fn/feedback_answer.php');

// A personal answer: never kept by a cache, never indexed, and the signed
// address is not handed to another site as a referrer.
header('Cache-Control: no-store, private');
header('X-Robots-Tag: noindex, nofollow');
header('Referrer-Policy: no-referrer');
header('X-Frame-Options: DENY');

$fb_post = (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? '')) === 'POST');
$fb_action = ($fb_post && is_string($_POST['action'] ?? null)) ? $_POST['action'] : '';
$fb_input = $fb_post ? $_POST : $_GET;
$fb_now = time();
$fb_page = OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/feedback.php';

// The page's own write of the chosen score answers in JSON, so it checks
// the form token itself.
if ($fb_action === 'score') {
    header('Content-Type: application/json; charset=utf-8');
    $fb_session = (string) ($_SESSION['software']['token'] ?? '');
    $fb_row = (($fb_session !== '') && is_string($_POST['token'] ?? null) && hash_equals($fb_session, $_POST['token']))
        ? pg_feedback_answer_find($_POST['t'] ?? '', $fb_now) : null;
    $fb_survey = $fb_row ? pg_feedback_answer_survey($fb_row['survey_id']) : null;

    if (!$fb_row || !$fb_survey || !pg_feedback_score_valid($fb_survey['kind'], $_POST['score'] ?? null)) {
        http_response_code(403);
        print json_encode(array('ok' => false));
        exit;
    }

    if (pg_feedback_answer_write($fb_row['id'], $fb_survey['kind'], array('score' => (int) $_POST['score']), true, $fb_now)) {
        pg_feedback_answered($fb_row, $fb_survey, $fb_now);
    }

    print json_encode(array('ok' => true));
    exit;
}

if ($fb_post) {
    validate_token_field();
}

$fb_stop_token = is_string($fb_input['x'] ?? null) ? $fb_input['x'] : '';
$fb_token = is_string($fb_input['t'] ?? null) ? $fb_input['t'] : '';
$fb_row = ($fb_stop_token !== '') ? pg_feedback_stop_find($fb_stop_token, $fb_now) : pg_feedback_answer_find($fb_token, $fb_now);
$fb_survey = $fb_row ? pg_feedback_answer_survey($fb_row['survey_id']) : null;
$fb_state = ($fb_row && $fb_survey) ? pg_feedback_answer_state($fb_row, $fb_now) : 'closed';
$fb_title = lang('Your opinion');

// "Don't ask me again" is open while the link is valid, whatever the answer.
if (!$fb_row || !$fb_survey || (($fb_stop_token === '') && ($fb_state === 'closed'))) {
    set_response_code(404);
    print output_header_secure(array('title' => $fb_title))
        . pg_secure_card('bi-chat-square-heart', $fb_title, '<p>' . h(lang('This link cannot be used. It may have expired, or the survey is closed.')) . '</p>')
        . output_footer_secure();
    exit;
}

$fb_languages = array_keys(pg_feedback_survey_languages());
$fb_first = $fb_languages[0];
$fb_lang = (defined('FRONTEND_LANGUAGE') && in_array((string) FRONTEND_LANGUAGE, $fb_languages, true)) ? (string) FRONTEND_LANGUAGE : $fb_first;
$fb_texts = pg_feedback_texts_in($fb_survey, $fb_lang, $fb_first);

// "Don't ask me again": asked, then written by its POST.
if ($fb_stop_token !== '') {
    if ($fb_action === 'stop') {
        pg_feedback_stop($fb_row);
        go($fb_page . '?x=' . $fb_stop_token);
    }

    $fb_content = empty($fb_row['stopped'])
        ? '<p>' . h(lang('Press the button below and you will not be asked for your opinion again, in any survey.')) . '</p>'
            . '<form method="post" action="' . h($fb_page) . '">' . get_token_field()
            . '<input type="hidden" name="x" value="' . h($fb_stop_token) . '">'
            . '<button type="submit" name="action" value="stop" class="pg-button pg-button-fill">' . h(lang('Don\'t ask me again')) . '</button></form>'
        : '<p role="status">' . h(lang('Done. You will not be asked for your opinion again.')) . '</p>';

    print output_header_secure(array('title' => $fb_title))
        . pg_secure_card('bi-bell-slash', lang('Don\'t ask me again'), $fb_content)
        . output_footer_secure();
    exit;
}

$fb_link = $fb_page . '?t=' . $fb_token;
$fb_error = '';

if ($fb_action === 'send') {
    $fb_answer = pg_feedback_answer_input($_POST, $fb_survey, $fb_lang, $fb_first);

    if ($fb_answer === null) {
        $fb_error = lang('Please choose a score.');
    } elseif (in_array($fb_state, array('open', 'changeable'), true)) {
        if (pg_feedback_answer_write($fb_row['id'], $fb_survey['kind'], $fb_answer, false, $fb_now)) {
            pg_feedback_answered($fb_row, $fb_survey, $fb_now);
        }

        go($fb_link);
    } else {
        $fb_error = lang('This answer can no longer be changed.');
    }
}

$fb_form = ($fb_state === 'open') || (($fb_state === 'changeable') && ($fb_post || !empty($_GET['change'])));
$fb_stop_link = pg_feedback_links($fb_page, $fb_row['id'], $fb_row['due_at'], $fb_survey['kind'], pg_feedback_key())['stop'] ?? '';
$fb_content = ($fb_error !== '') ? '<div class="alert theme-danger" role="alert">' . h($fb_error) . '</div>' : '';

if ($fb_form) {
    $fb_range = pg_feedback_range($fb_survey['kind']);
    $fb_ends = pg_feedback_scale_ends($fb_survey['kind']);
    $fb_answers = array();

    $fb_decoded = json_decode((string) ($fb_row['answers'] ?? ''), true);

    foreach (is_array($fb_decoded) ? $fb_decoded : array() as $fb_given) {
        if (is_array($fb_given) && is_string($fb_given['question'] ?? null) && is_string($fb_given['answer'] ?? null)) {
            $fb_answers[$fb_given['question']] = $fb_given['answer'];
        }
    }

    // The score chosen: posted, in the link, or the answer given before.
    $fb_chosen = $fb_post ? ($_POST['score'] ?? null) : ($_GET['s'] ?? (empty($fb_row['answered_at']) ? null : (string) $fb_row['score']));
    $fb_chosen = pg_feedback_score_valid($fb_survey['kind'], $fb_chosen) ? (int) $fb_chosen : null;
    $fb_scale = '';

    for ($fb_score = $fb_range[0]; $fb_score <= $fb_range[1]; $fb_score++) {
        $fb_scale .= '<label class="pg-seg-item" for="fb_score_' . $fb_score . '">'
            . '<input type="radio" class="visually-hidden" name="score" value="' . $fb_score . '" id="fb_score_' . $fb_score . '" required'
            . (($fb_chosen === $fb_score) ? ' checked' : '') . '>' . $fb_score . '</label>';
    }

    $fb_content .= '<form method="post" action="' . h($fb_page) . '" id="fb_form"'
        . ((($fb_state === 'open') && ($fb_chosen !== null) && !$fb_post) ? ' data-pg-feedback-autosave="1"' : '') . '>'
        . get_token_field() . '<input type="hidden" name="t" value="' . h($fb_token) . '">'
        . '<fieldset class="mb-7"><legend class="pg-field-label">' . h($fb_texts['question']) . '</legend>'
        . '<span class="pg-seg flex-wrap">' . $fb_scale . '</span>'
        . '<div class="d-flex justify-content-between pg-text-meta mt-3"><span>' . h($fb_ends[0]) . '</span><span>' . h($fb_ends[1]) . '</span></div></fieldset>'
        . '<div class="mb-7"><label class="pg-field-label" for="fb_comment">' . h($fb_texts['comment']) . '</label>'
        . '<textarea class="pg-input" name="comment" id="fb_comment" rows="4" maxlength="' . PG_FEEDBACK_COMMENT_MAX . '">'
        . h($fb_post ? (string) ($_POST['comment'] ?? '') : (string) ($fb_row['comment'] ?? '')) . '</textarea></div>';

    foreach ($fb_survey['texts']['extra'] as $fb_index => $fb_extra) {
        $fb_label = pg_feedback_text_in($fb_extra['label'], $fb_lang, $fb_first);
        $fb_before = $fb_answers[pg_feedback_text_in($fb_extra['label'], $fb_first, $fb_first)] ?? '';

        if ($fb_label === '') {
            continue;
        }

        $fb_id = 'fb_extra_' . (int) $fb_index;
        $fb_content .= '<div class="mb-7"><label class="pg-field-label" for="' . $fb_id . '">' . h($fb_label) . '</label>';

        if ($fb_extra['type'] === 'choice') {
            $fb_shown = $fb_extra['choices'][$fb_lang] ?? ($fb_extra['choices'][$fb_first] ?? array());
            $fb_stored = $fb_extra['choices'][$fb_first] ?? array();
            $fb_content .= '<select class="pg-input" name="extra[' . (int) $fb_index . ']" id="' . $fb_id . '"><option value=""></option>';

            foreach ($fb_shown as $fb_at => $fb_choice) {
                $fb_content .= '<option value="' . h($fb_choice) . '"' . ((($fb_stored[$fb_at] ?? $fb_choice) === $fb_before) ? ' selected' : '') . '>' . h($fb_choice) . '</option>';
            }

            $fb_content .= '</select></div>';
        } else {
            $fb_content .= '<input type="text" class="pg-input" name="extra[' . (int) $fb_index . ']" id="' . $fb_id . '" maxlength="' . PG_FEEDBACK_SHORT_MAX . '" value="' . h($fb_before) . '"></div>';
        }
    }

    $fb_content .= '<button type="submit" name="action" value="send" class="pg-button pg-button-fill">' . h(lang('Send')) . '</button></form>'
        . '<script src="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/assets/js/feedback_answer.js?v=' . filemtime(PG_FUNCTIONS_DIR . '/assets/js/feedback_answer.js') . '"></script>';
} else {
    $fb_review = pg_feedback_review_url_of($fb_survey, $fb_row);
    $fb_content .= '<p role="status">' . h(lang('Thank you. Your answer has been saved.')) . '</p>';

    if ($fb_review !== '') {
        $fb_content .= '<p class="mb-7">' . h(lang('Would you share your experience with others too?')) . '</p>'
            . '<p class="mb-7"><a class="pg-button pg-button-fill" href="' . h($fb_review) . '" target="_blank" rel="noopener">'
            . '<i class="bi bi-star me-1" aria-hidden="true"></i>' . h(lang('Write a review')) . '</a></p>';
    }

    if ($fb_state === 'changeable') {
        $fb_content .= '<p><a class="pg-button pg-button-quiet" href="' . h($fb_link . '&change=1') . '">' . h(lang('Change my answer')) . '</a></p>';
    }
}

if (!empty($fb_row['stopped'])) {
    $fb_content .= '<p class="pg-text-meta mt-7 mb-0">' . h(lang('You will not be asked for your opinion again.')) . '</p>';
} elseif ($fb_stop_link !== '') {
    $fb_content .= '<p class="pg-text-meta mt-7 mb-0"><a class="fg-secondary" href="' . h($fb_stop_link) . '">' . h(lang('Don\'t ask me again')) . '</a></p>';
}

print output_header_secure(array('title' => $fb_title))
    . pg_secure_card('bi-chat-square-heart', $fb_form ? $fb_title : lang('Thank you'), $fb_content)
    . output_footer_secure();
