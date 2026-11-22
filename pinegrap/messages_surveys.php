<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Communication › Surveys: the satisfaction surveys (NPS, CSAT, effort) on
 * the list table driver: name, kind, when it is sent, channel and whether
 * it is on. A row opens its survey (edit_feedback_survey.php), sends its
 * e-mail to oneself, or from its "more" menu deletes it while nothing was
 * sent, else archives it (posted to the edit screen). "Add survey" opens the
 * starting points in a drawer; a card opens the edit screen filled from it,
 * the survey off. Role <= 2; a user with the contacts right is sent to the
 * results. Helpers: includes/fn/feedback_surveys.php.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/list_table.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/messages_area.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/feedback_report.php');

$user = validate_user();

// A user with the contacts right sees the results only.
if (((int) $user['role'] > 2) && pg_feedback_results_may($user)) {
    go('messages_survey_results.php');
}

validate_area_access($user, 'manager');

$description = lang('Ask your customers how it went after a booking, an event, a course, a form, a delivery or a mail conversation.');

if (!pg_feedback_surveys_ready()) {
    echo pg_messages_tab_page('surveys', $user, $description, pg_messages_note(lang('Surveys are not available yet; run the upgrade.'), '', '', 'amber'));
    exit();
}

$liveform = new liveform('messages_surveys');
$surveys = pg_feedback_survey_list();
$kinds = pg_feedback_kind_names();
$channels = pg_feedback_channel_names();
$surveys_rows = array();

foreach ($surveys as $survey) {
    $id = (int) $survey['id'];
    $remove = $survey['used']
        ? array('label' => lang('Archive'), 'icon' => 'bi-archive', 'confirm' => lang('The survey is switched off and leaves the list. Its answers stay in the results.'))
        : array('label' => lang('Delete'), 'icon' => 'bi-trash', 'confirm' => lang('The survey is deleted. Nothing was sent with it.'));

    $surveys_rows[] = array(
        'id' => $id,
        'cells' => array(
            'name' => pg_list_cell('<a href="edit_feedback_survey.php?id=' . $id . '" data-pg-open>' . h($survey['name']) . '</a>'),
            'kind' => pg_list_cell(h(lang($kinds[$survey['kind']] ?? 'NPS')), 'align-middle', array('data-pg-sort-value' => $survey['kind'])),
            'after' => pg_list_cell(h(pg_feedback_sent_after_words($survey))),
            'channel' => pg_list_cell(h(lang($channels[$survey['channel']] ?? 'E-mail'))),
            'status' => pg_list_cell('<span class="pg-pill pg-tone-' . ($survey['enabled'] ? 'teal">' . h(lang('On')) : 'gray">' . h(lang('Off'))) . '</span>', 'align-middle', array('data-pg-sort-value' => $survey['enabled'])),
            'actions' => pg_list_cell('<a class="pg-button pg-button-quiet pg-button-sm" href="edit_feedback_survey.php?id=' . $id . '">' . h(lang('Edit')) . '</a>'
                . ' <button type="submit" name="send_test" value="' . $id . '" class="pg-button pg-button-quiet pg-button-sm">' . h(lang('Send me a test')) . '</button>'
                . ' <span class="pg-menu-wrap"><button type="button" class="pg-icon-button pg-icon-button-sm" data-bs-toggle="menu" aria-expanded="false" aria-label="' . h(lang('More actions')) . '"><i class="bi bi-three-dots" aria-hidden="true"></i></button>'
                . '<ul class="menu pg-menu-end"><li><button type="submit" name="submit_remove" value="' . $id . '" class="menu-item fg-emphasis-danger" data-confirm-content="' . h($remove['confirm']) . '"><i class="bi ' . $remove['icon'] . ' me-3" aria-hidden="true"></i>' . h($remove['label']) . '</button></li></ul></span>',
                'align-middle text-nowrap'),
        ),
    );
}

$surveys_sort_columns = array('name' => lang('Name'), 'kind' => lang('Kind'), 'after' => lang('Sent after'), 'channel' => lang('Channel'), 'status' => lang('Status'));
$surveys_sort_default = array('sort' => 'name', 'order' => 'asc');
$surveys_levels = pg_list_state('messages_surveys', $surveys_sort_columns, $surveys_sort_default)['sorts'];
$surveys_list = pg_list_table_printed(array(
    'list' => 'messages_surveys',
    'columns' => array(
        'name' => array('heading' => lang('Name')),
        'kind' => array('heading' => lang('Kind')),
        'after' => array('heading' => lang('Sent after')),
        'channel' => array('heading' => lang('Channel')),
        'status' => array('heading' => lang('Status'), 'kind' => 'badge'),
        'actions' => array('heading' => lang('Actions'), 'kind' => 'actions'),
    ),
    'levels' => $surveys_levels,
    'rows' => $surveys_rows,
));

// The starting points: a card each, as the starting-point sheet of the
// Design examples draws them; Continue opens the edit screen filled from
// the chosen one. Opened by "Add survey", and with the screen on ?add=1.
$start_cards = '';
$first = true;

foreach (pg_feedback_touchpoints() as $key => $point) {
    $card_id = 'survey_start_' . $key;
    $start_cards .= '<div class="form-field form-field-card">'
        . '<input class="radio" type="radio" name="start" value="' . h($key) . '" id="' . $card_id . '" aria-describedby="' . $card_id . '_help"' . ($first ? ' checked' : '') . '>'
        . '<label class="pg-start-head" for="' . $card_id . '"><span class="pg-icon-tile pg-icon-tile-lg pg-tone-' . h($point['tone']) . '" aria-hidden="true"><i class="bi ' . h($point['icon']) . '"></i></span>'
        . '<span class="fw-bold">' . h(lang($point['label'])) . '</span><span class="pg-pill pg-tone-gray">' . h(lang($kinds[$point['kind']])) . '</span></label>'
        . '<div class="form-text mt-1" id="' . $card_id . '_help">' . h(lang($point['line'])) . '</div></div>';
    $first = false;
}

$add_button = '<button type="button" class="pg-button pg-button-fill" data-survey-drawer="survey_add"><i class="bi bi-plus-lg" aria-hidden="true"></i>' . h(lang('Add survey')) . '</button>';
$start_drawer = '
    <dialog class="drawer drawer-end" id="survey_add" aria-labelledby="survey_add_title" style="width:min(760px,100%)"' . (isset($_GET['add']) ? ' data-open="1"' : '') . '>
        <form method="get" action="edit_feedback_survey.php" class="d-flex flex-column h-100">
            <div class="drawer-header p-5 pb-1 d-block"><div class="d-flex align-items-start gap-3">
                <span class="pg-icon-tile pg-icon-tile-lg pg-tone-blue" aria-hidden="true"><i class="bi bi-emoji-smile"></i></span>
                <div class="flex-grow-1 min-width-0"><h5 class="drawer-title mb-0" id="survey_add_title">' . h(lang('Add survey')) . '</h5>
                <p class="small fg-2 mb-0 mt-1">' . h(lang('What should it follow? Pick one and the rest is prepared for it.')) . '</p></div>
                <button type="button" class="btn-close ms-auto" data-bs-dismiss="drawer" aria-label="' . h(lang('Close')) . '"></button></div></div>
            <div class="drawer-body p-5 flex-grow-1 overflow-auto"><fieldset><legend class="visually-hidden">' . h(lang('What should it follow?')) . '</legend><div class="pg-start">' . $start_cards . '</div></fieldset></div>
            <div class="drawer-footer border-top p-5 d-flex align-items-center gap-3">
                <span class="small fg-2">' . h(lang('It starts switched off; nothing is sent before you switch it on.')) . '</span><span class="flex-grow-1"></span>
                <button type="button" class="pg-button pg-button-ghost" data-bs-dismiss="drawer">' . h(lang('Cancel')) . '</button>
                <button type="submit" class="pg-button pg-button-fill">' . h(lang('Continue')) . '<i class="bi bi-arrow-right" aria-hidden="true"></i></button>
            </div>
        </form>
    </dialog>';

// No survey yet: one line on what a survey does and the Add button, in
// place of an empty table and a toolbar.
if ($surveys === array()) {
    $body = '<div class="pg-card mb-5"><p class="pg-text-2">' . h(lang('A survey asks one question after a service, with a score and a comment, and tells you when someone was unhappy.')) . '</p>' . $add_button . '</div>';
} else {
    $body = pg_screen_toolbar(array(
            'primary' => $add_button,
            'search'  => array(pg_list_table_search_box('messages_surveys'), pg_list_sort_menu('messages_surveys.php', $surveys_sort_columns, $surveys_levels, $surveys_sort_default)),
        )) . '
        <form name="form" action="edit_feedback_survey.php" method="post">
            ' . get_token_field() . '
            ' . pg_list_table_html($surveys_list['source'], $surveys_list['page'], lang('Surveys')) . '
        </form>';
}

$body = pg_feedback_segments_html('surveys', $user) . $liveform->get_messages() . $body . $start_drawer . pg_js_bundle_tags('assets/js/pg_list_table.min.js') . '
<script>
(function () {
    var phone = window.matchMedia("(max-width: 575.98px)");

    // The drawer opens through pgUI, from the right or, on a phone, as a
    // sheet from the bottom; one carrying data-open opens with the screen.
    var open = function (drawer) {
        if (!drawer || !window.pgUI) { return; }
        drawer.classList.toggle("drawer-end", !phone.matches);
        drawer.classList.toggle("drawer-bottom", phone.matches);
        pgUI.drawer.show(drawer);
    };

    document.addEventListener("click", function (event) {
        var opener = event.target.closest("[data-survey-drawer]");
        if (opener) { open(document.getElementById(opener.getAttribute("data-survey-drawer"))); }
    });

    // pgUI comes with the footer, after this script.
    window.addEventListener("load", function () {
        document.querySelectorAll("dialog[data-open]").forEach(open);
    });
})();
</script>';

echo pg_messages_tab_page('surveys', $user, $description, $body);

$liveform->remove_form();
