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
$mr_url = function ($review_id) use ($mr_self) {
    return $mr_self . (($review_id > 0) ? '?review=' . (int) $review_id : '');
};
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

    pg_application_review_post($mr, $liveform, $mr_url, $mr_autosave);
}

// The list: a programme a card, then its queue as rows.
if ($mr === null) {
    $mr_programs = pg_application_my_programs((int) $user['id']);

    if (!$mr_programs) {
        log_activity('access denied to my reviews', $_SESSION['sessionusername']);
        output_error(lang('You are not a reviewer of any application programme.'), 403);
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
    ' . pg_application_review_cards_html($mr_programs, $mr_url) . '
</main>
' . output_footer();

    $liveform->remove_form();
    exit;
}

// The scoring screen.
$mr_program = $mr['program'];
$mr_name = pg_application_review_entry_name($mr_program, $mr['entry'] + array('entry_id' => $mr['entry']['id']));
$mr_choices = pg_application_category_choices($mr_program);

echo pg_page_shell(array(
    'title' => $mr_name,
    'extra classes' => 'form',
    'icon' => 'form',
    'help' => 'forms#submitted-forms',
    'heading' => h($mr_name) . ' ' . (pg_application_review_pills()[(int) $mr['review']['status']] ?? ''),
    'heading_description' => $mr_program['name'] . ((string) $mr['entry']['category'] !== '' ? ' · ' . ($mr_choices[$mr['entry']['category']] ?? $mr['entry']['category']) : ''),
    'breadcrumb' => array(
        array('label' => lang('My reviews'), 'url' => $mr_self),
        array('label' => $mr_name),
    ),
)) . '
<main id="content" class="container-fluid">
    ' . $liveform->get_messages() . '
    ' . pg_application_review_screen_html($mr, 'my_reviews.php', '', $mr_url) . '
</main>
' . output_footer();

$liveform->remove_form();
