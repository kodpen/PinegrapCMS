<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * "My reviews": a reviewer's application programmes, each with its progress,
 * the days left in the round and the queue of entries; and, with ?review=,
 * the scoring screen of one entry: the answers at the left (blind review
 * hides what the programme hides), the criteria at the right, scores saved
 * as typed, Send the review, Previous and Next, and "I know this applicant".
 * Opened by programme membership alone, with no Forms right: every read is
 * of the signed-in user's own reviews, checked on the server.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/application_my_reviews.php');

$mr_autosave = ($_SERVER['REQUEST_METHOD'] === 'POST') && isset($_POST['autosave']);
$mr_json = function ($status, array $body) {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($body);
    exit;
};

// The scripted save answers in JSON, so it refuses in JSON before the
// screen's own gates, which answer with a page.
if ($mr_autosave) {
    if (empty($_SESSION['sessionuserid'])) {
        $mr_json(401, array('ok' => false, 'message' => lang('Your session has expired. Reload the page and sign in again.')));
    }

    $mr_token = (string) ($_SESSION['software']['token'] ?? '');

    if (($mr_token === '') || !is_string($_POST['token'] ?? null) || !hash_equals($mr_token, $_POST['token'])) {
        $mr_json(403, array('ok' => false, 'message' => lang('Your session expired. Reload the page and try again.')));
    }
}

$user = validate_user();

if (!pg_application_program_ready()) {
    output_error(lang('The database has not been upgraded yet. Run the software update first.'));
}

$liveform = new liveform('my_reviews');
$mr_self = OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/my_reviews.php';
$mr_id = (int) ($_POST['review'] ?? $_GET['review'] ?? 0);
$mr = ($mr_id > 0) ? pg_application_my_review($mr_id, (int) $user['id']) : null;

if (($mr_id > 0) && ($mr === null)) {
    if ($mr_autosave) {
        $mr_json(404, array('ok' => false, 'message' => lang('This review is not yours, or it was taken back.')));
    }

    log_activity('access denied to application review ' . $mr_id, $_SESSION['sessionusername']);
    output_error(lang('This review is not yours, or it was taken back.'), 404);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$mr_autosave) {
        validate_token_field();
    }

    if (($mr === null) || !$mr['open']) {
        if ($mr_autosave) {
            $mr_json(409, array('ok' => false, 'message' => lang('This review was sent and can no longer be changed.')));
        }

        go($mr_self . (($mr !== null) ? '?review=' . $mr_id : ''));
    }

    $mr_scores = pg_application_review_scores_input($mr['criteria'], $_POST['score'] ?? null, $_POST['note'] ?? null);

    if (isset($_POST['submit_conflict'])) {
        $mr_conflict = pg_application_review_conflict($mr, is_string($_POST['conflict_reason'] ?? null) ? $_POST['conflict_reason'] : '');

        if (!$mr_conflict['ok']) {
            $liveform->add_error(h(lang('Say how you know the applicant: the programme\'s managers read it.')));
            go($mr_self . '?review=' . $mr_id);
        }

        $liveform->add_notice(h($mr_conflict['moved']
            ? lang('The entry went to another reviewer. Your reason is kept for the programme\'s managers.')
            : lang('The entry was taken off your list. No other reviewer is free for it yet: the programme\'s managers see it.')));
        go($mr_self);
    }

    $mr_send = isset($_POST['submit_send']);

    if (!pg_application_review_save($mr, $mr_scores, $mr_send)) {
        if ($mr_autosave) {
            $mr_json(500, array('ok' => false, 'message' => lang('The scores could not be saved.')));
        }

        $liveform->add_error(h($mr_send ? lang('Score every criterion before you send the review.') : lang('The scores could not be saved.')));
        go($mr_self . '?review=' . $mr_id);
    }

    if ($mr_autosave) {
        $mr_json(200, array('ok' => true, 'message' => lang('Saved')));
    }

    $mr_go = (int) ($_POST['go'] ?? 0);

    if ($mr_send) {
        $liveform->add_notice(h(lang('The review was sent.')));

        // On to the next entry still to do, if there is one.
        foreach (pg_application_my_queue($mr['review']['reviewer_id'], $mr['review']['round']) as $mr_row) {
            if ((int) $mr_row['status'] < 2) {
                go($mr_self . '?review=' . (int) $mr_row['id']);
            }
        }

        go($mr_self);
    }

    go($mr_self . (($mr_go > 0) ? '?review=' . $mr_go : ''));
}

$mr_pills = array(
    0 => '<span class="pg-pill pg-tone-gray">' . h(lang('To do')) . '</span>',
    1 => '<span class="pg-pill pg-tone-amber">' . h(lang('Started')) . '</span>',
    2 => '<span class="pg-pill pg-tone-teal">' . h(lang('Sent')) . '</span>',
);

// An entry's name for its reviewer: blind review shows only its number.
$mr_entry_name = function (array $program, array $row) {
    if ($program['blind'] || ((string) $row['reference_code'] === '')) {
        return lang(array('string' => 'Entry {var:1}', 'vars' => array((int) ($row['entry_id'] ?? $row['id']))));
    }

    return (string) $row['reference_code'];
};
$mr_days = function ($ends_at) {
    $left = pg_application_days_left($ends_at, time());

    if ($left === null) {
        return '';
    }

    return ($left === 0) ? lang('The round has ended') : lang(array('string' => '{var:1} days left', 'vars' => array($left)));
};

// The list: a programme a card, then its queue as rows.
if ($mr === null) {
    $mr_programs = pg_application_my_programs((int) $user['id']);

    if (!$mr_programs) {
        log_activity('access denied to my reviews', $_SESSION['sessionusername']);
        output_error(lang('You are not a reviewer of any application programme.'), 403);
    }

    $mr_cards = '';

    foreach ($mr_programs as $program) {
        $choices = pg_application_category_choices($program);
        $rows = '';

        foreach (pg_application_my_queue($program['reviewer_id'], $program['round']) as $row) {
            $rows .= '<div class="pg-card-inner-row">'
                . '<div class="flex-grow-1 min-width-0"><a class="pg-list-row-title" href="my_reviews.php?review=' . (int) $row['id'] . '">' . h($mr_entry_name($program, $row)) . '</a>'
                . (((string) $row['category'] !== '') ? '<div class="pg-text-meta">' . h($choices[$row['category']] ?? $row['category']) . '</div>' : '') . '</div>'
                . ($mr_pills[(int) $row['status']] ?? '') . '</div>';
        }

        $percent = ($program['assigned'] > 0) ? (int) floor($program['done'] * 100 / $program['assigned']) : 0;
        $progress = lang(array('string' => '{var:1} of {var:2} sent', 'vars' => array($program['done'], $program['assigned'])));
        $days = $mr_days($program['ends_at']);
        $mr_cards .= '<section class="pg-card p-5 mb-5" aria-labelledby="mr_program_' . $program['id'] . '">'
            . '<div class="d-flex flex-wrap align-items-center gap-2"><h2 class="pg-title-section mb-0 flex-grow-1" id="mr_program_' . $program['id'] . '">' . h($program['name']) . '</h2>'
            . (($days !== '') ? '<span class="pg-text-2">' . h($days) . '</span>' : '') . '</div>'
            . '<p class="pg-card-sub mb-3">' . h(lang(array('string' => 'Round {var:1}', 'vars' => array(max(1, $program['round']))))) . ' · ' . h($progress) . '</p>'
            . '<div class="progress mb-5" role="progressbar" aria-label="' . h($progress) . '" aria-valuenow="' . $percent . '" aria-valuemin="0" aria-valuemax="100" style="height:0.5rem"><div class="progress-bar" style="width:' . $percent . '%"></div></div>'
            . (($rows !== '') ? '<div class="pg-card-inner">' . $rows . '</div>' : '<p class="pg-text-2 mb-0">' . h(lang('No entries were given to you in this round yet.')) . '</p>')
            . '</section>';
    }

    echo pg_page_shell(array(
        'title' => lang('My reviews'),
        'extra classes' => 'form pg-measure-edit',
        'icon' => 'form',
        'help' => 'forms#submitted-forms',
        'heading' => h(lang('My reviews')),
        'heading_description' => lang('The entries given to you to score. Others\' scores stay hidden.'),
    )) . '
<main id="content" class="container-fluid">
    ' . $liveform->get_messages() . '
    ' . $mr_cards . '
</main>
' . output_footer();

    $liveform->remove_form();
    exit;
}

// The scoring screen.
$mr_program = $mr['program'];
$mr_review = $mr['review'];
$mr_open = $mr['open'];
$mr_queue = pg_application_my_queue($mr_review['reviewer_id'], $mr_review['round']);
list($mr_prev, $mr_next) = pg_application_queue_neighbours(array_column($mr_queue, 'id'), $mr_id);
$mr_name = $mr_entry_name($mr_program, $mr['entry'] + array('entry_id' => $mr['entry']['id']));
$mr_choices = pg_application_category_choices($mr_program);

$mr_answers = '';

foreach (pg_application_entry_view($mr_program, $mr['entry']['form_id']) as $field) {
    $mr_answers .= '<div class="pg-card-inner-row d-block"><div class="pg-text-meta">' . h($field['label']) . '</div>'
        . (($field['html'] === null) ? '<div class="pg-text-2 fst-italic">' . h(lang('Hidden')) . '</div>' : '<div>' . (($field['html'] !== '') ? $field['html'] : '<span class="pg-text-2">—</span>') . '</div>')
        . '</div>';
}

$mr_criteria = '';

foreach ($mr['criteria'] as $criterion) {
    $cid = (string) $criterion['id'];
    $stored = $mr['scores'][$cid] ?? array('score' => null, 'note' => '');
    $choices = '';

    for ($score = 1; $score <= 10; $score++) {
        $choices .= '<label class="pg-seg-item"><input type="radio" class="visually-hidden" name="score[' . h($cid) . ']" value="' . $score . '"' . (($stored['score'] === $score) ? ' checked' : '') . ($mr_open ? '' : ' disabled') . '>' . $score . '</label>';
    }

    $mr_criteria .= '<div class="pg-card-inner-row d-block" data-pg-mr-criterion data-weight="' . (int) $criterion['weight'] . '">'
        . '<div class="d-flex align-items-baseline gap-2 mb-2"><strong class="pg-list-row-title flex-grow-1" id="mr_c_' . h($cid) . '">' . h($criterion['name']) . '</strong><span class="pg-text-meta pg-tabular">' . (int) $criterion['weight'] . '%</span></div>'
        . '<span class="pg-seg flex-wrap" role="radiogroup" aria-labelledby="mr_c_' . h($cid) . '">' . $choices . '</span>'
        . '<details class="mt-2"' . (($stored['note'] !== '') ? ' open' : '') . '><summary class="pg-text-2">' . h(lang('Note')) . '</summary>'
        . '<textarea class="pg-input mt-2" name="note[' . h($cid) . ']" rows="2" maxlength="1000" aria-label="' . h(lang('Note') . ': ' . $criterion['name']) . '"' . ($mr_open ? '' : ' disabled') . '>' . h($stored['note']) . '</textarea></details>'
        . '</div>';
}

$mr_total = pg_application_weighted_sum($mr['criteria'], pg_application_review_score_map($mr['scores']));
$mr_status = (int) $mr_review['status'];
$mr_nav = function ($target, $label, $icon) use ($mr_open) {
    if ($target <= 0) {
        return '<button type="button" class="pg-button pg-button-quiet" disabled><i class="bi ' . $icon . '" aria-hidden="true"></i>' . h($label) . '</button>';
    }

    // An open review saves what is typed on the way.
    return $mr_open
        ? '<button type="submit" name="go" value="' . (int) $target . '" class="pg-button pg-button-quiet"><i class="bi ' . $icon . '" aria-hidden="true"></i>' . h($label) . '</button>'
        : '<a class="pg-button pg-button-quiet" href="my_reviews.php?review=' . (int) $target . '"><i class="bi ' . $icon . '" aria-hidden="true"></i>' . h($label) . '</a>';
};

$mr_foot = '<div class="pg-floatbar d-flex flex-wrap align-items-center gap-2 p-3 mb-5">'
    . ($mr_open ? '<button type="submit" name="submit_send" value="1" class="pg-button pg-button-fill" data-confirm-content="' . h(lang('A sent review can no longer be changed.')) . '">' . h(lang('Send the review')) . '</button>' : '')
    . $mr_nav($mr_prev, lang('Previous'), 'bi-chevron-left') . $mr_nav($mr_next, lang('Next'), 'bi-chevron-right')
    . '<span class="flex-grow-1 pg-text-meta" role="status" data-pg-mr-saved data-pg-mr-failed="' . h(lang('The scores could not be saved.')) . '"></span>'
    . ($mr_open ? '<button type="button" class="pg-button pg-button-quiet" aria-expanded="false" aria-controls="mr_conflict" data-pg-mr-conflict-toggle>' . h(lang('I know this applicant')) . '</button>' : '')
    . '</div>';

$mr_conflict = $mr_open ? '<section class="pg-card p-5 mb-5" id="mr_conflict" aria-labelledby="mr_conflict_title" hidden>'
    . '<h2 class="pg-title-section mb-0" id="mr_conflict_title">' . h(lang('I know this applicant')) . '</h2>'
    . '<p class="pg-card-sub mb-4">' . h(lang('The entry goes to another reviewer and you do not score it. Your reason is kept for the programme\'s managers.')) . '</p>'
    . '<label class="pg-field-label" for="mr_conflict_reason">' . h(lang('How you know them')) . '</label>'
    . '<textarea class="pg-input mb-4" id="mr_conflict_reason" name="conflict_reason" rows="2" maxlength="500"></textarea>'
    . '<button type="submit" name="submit_conflict" value="1" class="pg-button pg-button-quiet">' . h(lang('Pass the entry on')) . '</button>'
    . '</section>' : '';

echo pg_page_shell(array(
    'title' => $mr_name,
    'extra classes' => 'form',
    'icon' => 'form',
    'help' => 'forms#submitted-forms',
    'heading' => h($mr_name) . ' ' . ($mr_pills[$mr_status] ?? ''),
    'heading_description' => $mr_program['name'] . ((string) $mr['entry']['category'] !== '' ? ' · ' . ($mr_choices[$mr['entry']['category']] ?? $mr['entry']['category']) : ''),
    'breadcrumb' => array(
        array('label' => lang('My reviews'), 'url' => $mr_self),
        array('label' => $mr_name),
    ),
)) . '
<main id="content" class="container-fluid">
    ' . $liveform->get_messages() . '
    <form id="mr_form" action="my_reviews.php" method="post">
        ' . get_token_field() . '<input type="hidden" name="review" value="' . $mr_id . '">
        ' . ($mr_open ? '<button type="submit" name="go" value="' . $mr_id . '" class="visually-hidden" tabindex="-1" aria-hidden="true"></button>' : '') . '
        <div class="row gy-5 mb-5">
            <div class="col-12 lg:col-7"><section class="pg-card p-5 h-100" aria-labelledby="mr_answers_title">
                <h2 class="pg-title-section mb-0" id="mr_answers_title">' . h(lang('Answers')) . '</h2>
                <p class="pg-card-sub mb-4">' . h($mr_program['blind'] ? lang('Blind review: who applied is hidden. Uploaded files show as uploaded.') : lang('The entry as it was sent.')) . '</p>
                <div class="pg-card-inner">' . $mr_answers . '</div>
            </section></div>
            <div class="col-12 lg:col-5"><section class="pg-card p-5 h-100" aria-labelledby="mr_scores_title">
                <h2 class="pg-title-section mb-0" id="mr_scores_title">' . h(lang('Scores')) . '</h2>
                <p class="pg-card-sub mb-4">' . h($mr_open ? lang('Score each criterion from 1 to 10. Your scores are saved as you go; send the review once.') : lang('This review was sent.')) . '</p>
                <div class="pg-card-inner mb-4">' . $mr_criteria . '</div>
                <div class="d-flex align-items-baseline gap-2"><span class="pg-text-2 flex-grow-1">' . h(lang('Weighted total')) . '</span><strong class="pg-tabular" data-pg-mr-total>' . (($mr_total !== null) ? h(number_format($mr_total, 2)) : '—') . '</strong></div>
            </section></div>
        </div>
        ' . $mr_foot . $mr_conflict . '
    </form>
</main>
<script src="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/assets/js/my_reviews.js?v=' . filemtime(PG_FUNCTIONS_DIR . '/assets/js/my_reviews.js') . '"></script>
' . output_footer();

$liveform->remove_form();
