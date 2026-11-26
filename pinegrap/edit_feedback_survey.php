<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Add and edit a satisfaction survey (Communication › Surveys) on the edit
 * layout. Cards: Question (name, kind, the texts per site language, the
 * extra questions folded), Sent after (what it follows, its filter, the
 * delay), Sent by (channel, service message or commercial, the WhatsApp
 * template and what fills its variables), Low scores (who
 * follows up, who joins after 48 hours, the Workspace channel) and After the
 * answer (the review invitation). The status column holds the On switch,
 * the e-mail's preview, "Send me a test" and Delete or Archive. A new
 * survey comes from a starting point (?start=) and is off until switched
 * on. The list's row buttons post here too: a test with the stored survey,
 * and Delete or Archive. Role <= 2. Helpers: includes/fn/feedback_surveys.php.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/feedback_surveys.php');

$user = validate_user();
validate_area_access($user, 'manager');

if (!pg_feedback_surveys_ready()) {
    output_error(lang('The database has not been upgraded yet. Run the software update first.'));
}

$liveform = new liveform('edit_feedback_survey');
$fs_self = OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/edit_feedback_survey.php';
$fs_list = OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/messages_surveys.php';

// The list's buttons post the survey's id as their value.
$fs_id = (int) ($_POST['send_test'] ?? $_POST['submit_remove'] ?? $_POST['id'] ?? $_GET['id'] ?? 0);
$fs_survey = ($fs_id > 0) ? pg_feedback_survey_get($fs_id) : null;

if (($fs_id > 0) && (($fs_survey === null) || ($fs_survey['archived_at'] > 0))) {
    output_error(lang('The survey was not found.'), 404);
}

$fs_languages = pg_feedback_survey_languages();
$fs_codes = array_keys($fs_languages);
$fs_choices = pg_feedback_survey_choices();
// The approved WhatsApp templates a survey's message can be sent with.
require_once(PG_FUNCTIONS_DIR . '/includes/fn/whatsapp.php');
$fs_wa_offered = pg_whatsapp_message_templates();
$fs_known = array('users' => array_keys($fs_choices['users']), 'channels' => array_keys($fs_choices['channels']), 'languages' => $fs_codes,
    'wa_offered' => $fs_wa_offered, 'wa_before' => ($fs_survey !== null) ? $fs_survey['whatsapp'] : null,
    'kind' => (($fs_survey !== null) && pg_feedback_survey_used($fs_id)) ? (int) $fs_survey['kind'] : 0);

// "Send me a test": the survey's e-mail to the signed-in person, ten an hour.
$fs_test = function (array $survey, liveform $form) use ($user) {
    if (function_exists('waf_rate_exceeded') && waf_rate_exceeded('feedback_test|' . (int) $user['id'], 'feedback_test', PG_FEEDBACK_TEST_HOURLY, 3600)) {
        $form->add_warning(h(lang(array('string' => 'The test was not sent: a user may send {var:1} tests an hour.', 'vars' => array(PG_FEEDBACK_TEST_HOURLY)))));
    } elseif (trim((string) $user['email_address']) === '') {
        $form->add_warning(h(lang('Your user has no e-mail address, so the test has nowhere to go.')));
    } elseif (pg_feedback_survey_test_send($survey, $user['email_address'])) {
        $form->add_notice(h(lang(array('string' => 'A test was sent to {var:1}.', 'vars' => array($user['email_address'])))));
        log_activity('a test of survey "' . $survey['name'] . '" was sent');
    } else {
        $form->add_warning(h(lang(array('string' => 'The test could not be sent to {var:1}: check the mail server in E-mail sending.', 'vars' => array($user['email_address'])))));
    }
};
$fs_input = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_token_field();
    $list_form = new liveform('messages_surveys');

    if (($fs_survey !== null) && isset($_POST['submit_remove'])) {
        $removed = pg_feedback_survey_remove($fs_id);
        log_activity('survey "' . $fs_survey['name'] . '" was ' . $removed);
        $list_form->add_notice(h(lang(array('string' => ($removed === 'deleted') ? 'The survey "{var:1}" was deleted.' : 'The survey "{var:1}" was archived; its answers stay in the results.', 'vars' => array($fs_survey['name'])))));
        go($fs_list);
    }

    // A row's test sends the stored survey and goes back to the list.
    if (($fs_survey !== null) && isset($_POST['send_test']) && !isset($_POST['name'])) {
        $fs_test($fs_survey, $list_form);
        go($fs_list);
    }

    $fs_touchpoint = ($fs_survey !== null) ? $fs_survey['touchpoint'] : (string) ($_POST['touchpoint'] ?? '');

    if (!isset(pg_feedback_touchpoints()[$fs_touchpoint])) {
        go($fs_list . '?add=1');
    }

    $fs_input = pg_feedback_survey_input($_POST, $fs_known, $fs_touchpoint);

    if (!$fs_input['errors']) {
        $saved = pg_feedback_survey_save($fs_id, $fs_input['survey'], (int) $user['id']);

        if ($saved > 0) {
            log_activity('survey "' . $fs_input['survey']['name'] . '" was ' . (($fs_id > 0) ? 'modified' : 'created'));
            $liveform->add_notice(h(lang('The survey was saved.')));

            if (isset($_POST['send_test'])) {
                $fs_test($fs_input['survey'], $liveform);
            }

            go($fs_self . '?id=' . $saved);
        }

        $fs_input['errors'][] = lang('The survey could not be saved.');
    }

    foreach ($fs_input['errors'] as $error) {
        $liveform->add_error(h($error));
    }
}

// What the form shows: the posted values after a refused save, the stored
// survey, or a starting point's defaults.
if ($fs_input !== null) {
    $fs = $fs_input['survey'];
} elseif ($fs_survey !== null) {
    $fs = $fs_survey;
} else {
    $fs = pg_feedback_survey_start(is_string($_GET['start'] ?? null) ? $_GET['start'] : '');

    if ($fs === null) {
        go($fs_list . '?add=1');
    }
}

$fs_point = pg_feedback_touchpoints()[$fs['touchpoint']];
$fs_first = $fs_codes[0];
$fs_many = (count($fs_codes) > 1);
$fs_defaults = array();

foreach (array_keys(pg_feedback_kind_names()) as $kind) {
    $fs_defaults[$kind] = pg_feedback_default_texts($kind);
}

// A segmented choice that writes a hidden field (look A's segmented control).
$fs_seg = function ($name, $options, $chosen, $label, $attributes = array()) {
    $items = '';

    foreach ($options as $value => $text) {
        $items .= '<button type="button" class="pg-seg-item" aria-pressed="' . (((string) $value === (string) $chosen) ? 'true' : 'false') . '" data-fs-value="' . h((string) $value) . '"' . ($attributes[$value] ?? '') . '>' . h($text) . '</button>';
    }

    return '<span class="pg-seg" role="group" aria-label="' . h($label) . '" data-fs-seg="' . h($name) . '">' . $items . '</span><input type="hidden" name="' . h($name) . '" value="' . h((string) $chosen) . '">';
};
$fs_select = function ($name, $id, $options, $chosen, $none) {
    $html = '<select class="pg-input" id="' . $id . '" name="' . $name . '"><option value="0">' . h($none) . '</option>';

    foreach ($options as $value => $text) {
        $html .= '<option value="' . (int) $value . '"' . (((int) $value === (int) $chosen) ? ' selected' : '') . '>' . h($text) . '</option>';
    }

    return $html . '</select>';
};
$fs_card = function ($key, $title, $line, $body) {
    return '<section class="pg-card p-5 mb-5" aria-labelledby="fs_' . $key . '_title"><h2 class="pg-title-section mb-0" id="fs_' . $key . '_title">' . h($title) . '</h2>'
        . '<p class="pg-card-sub mb-5">' . h($line) . '</p>' . $body . '</section>';
};
$fs_lang = function ($code) use ($fs_first, $fs_many) {
    return $fs_many ? ' data-fs-lang="' . h($code) . '"' . (($code === $fs_first) ? '' : ' hidden') : '';
};

// 1. Question: name, kind, the texts per language, the extra questions.
$fs_kind_attributes = array();

foreach ($fs_defaults as $kind => $texts) {
    $fs_kind_attributes[$kind] = ' data-fs-question="' . h($texts['question']) . '" data-fs-comment="' . h($texts['comment']) . '"';
}

$fs_texts = '';

foreach ($fs_codes as $code) {
    $fs_texts .= '<div class="row gy-5 mb-5"' . $fs_lang($code) . '>'
        . '<div class="col-12"><label class="pg-field-label" for="fs_question_' . h($code) . '">' . h(lang('Question')) . '</label><input type="text" class="pg-input" id="fs_question_' . h($code) . '" name="question[' . h($code) . ']" maxlength="300" data-fs-text="question" value="' . h($fs['texts']['question'][$code] ?? '') . '" placeholder="' . h($fs_defaults[$fs['kind']]['question']) . '"></div>'
        . '<div class="col-12"><label class="pg-field-label" for="fs_comment_' . h($code) . '">' . h(lang('Comment label')) . '</label><input type="text" class="pg-input" id="fs_comment_' . h($code) . '" name="comment[' . h($code) . ']" maxlength="150" data-fs-text="comment" value="' . h($fs['texts']['comment'][$code] ?? '') . '" placeholder="' . h($fs_defaults[$fs['kind']]['comment']) . '"></div></div>';
}

$fs_extra_rows = '';
$fs_extra_count = count($fs['texts']['extra']);

for ($i = 0; $i < PG_FEEDBACK_EXTRA_MAX; $i++) {
    $extra = $fs['texts']['extra'][$i] ?? array('type' => 'text', 'label' => array(), 'choices' => array());
    $inputs = '';

    foreach ($fs_codes as $code) {
        $shown = !$fs_many || ($code === $fs_first);
        $lang_attribute = $fs_many ? ' data-fs-lang="' . h($code) . '"' : '';
        $inputs .= '<input type="text" class="pg-input flex-grow-1" name="extra[' . $i . '][label][' . h($code) . ']" maxlength="200" value="' . h($extra['label'][$code] ?? '') . '" aria-label="' . h(lang('Question')) . '" placeholder="' . h(lang('Question')) . '"' . $lang_attribute . ($shown ? '' : ' hidden') . '>'
            . '<input type="text" class="pg-input flex-grow-1" name="extra[' . $i . '][choices][' . h($code) . ']" value="' . h(implode(', ', $extra['choices'][$code] ?? array())) . '" aria-label="' . h(lang('Choices')) . '" placeholder="' . h(lang('Choices, split by commas')) . '" data-fs-choices' . $lang_attribute . (($shown && ($extra['type'] === 'choice')) ? '' : ' hidden') . '>';
    }

    $fs_extra_rows .= '<div class="pg-card-inner-row flex-wrap" data-fs-extra' . (($i < $fs_extra_count) ? '' : ' hidden') . '>'
        . '<select class="pg-input w-auto" name="extra[' . $i . '][type]" aria-label="' . h(lang('Answer')) . '" data-fs-extra-type><option value="text">' . h(lang('Short text')) . '</option><option value="choice"' . (($extra['type'] === 'choice') ? ' selected' : '') . '>' . h(lang('Single choice')) . '</option></select>'
        . $inputs . '<button type="button" class="pg-icon-button pg-icon-button-sm" data-fs-extra-remove aria-label="' . h(lang('Remove')) . '"><span class="bi bi-x-lg" aria-hidden="true"></span></button></div>';
}

$fs_lang_tabs = '';

if ($fs_many) {
    foreach ($fs_languages as $code => $name) {
        $fs_lang_tabs .= '<button type="button" class="pg-seg-item" role="tab" aria-selected="' . (($code === $fs_first) ? 'true' : 'false') . '" data-fs-tab="' . h($code) . '">' . h($name) . '</button>';
    }

    $fs_lang_tabs = '<div class="mb-4"><span class="pg-seg" role="tablist" aria-label="' . h(lang('Language')) . '">' . $fs_lang_tabs . '</span></div>';
}

$fs_question = $fs_card('question', lang('Question'), lang('One question with a score, then a comment. Left empty, a text uses the default shown.'),
    '<div class="row gy-5 mb-5"><div class="col-12 md:col-6"><label class="pg-field-label" for="fs_name">' . h(lang('Name')) . '</label><input type="text" class="pg-input" id="fs_name" name="name" maxlength="150" required value="' . h($fs['name']) . '"></div>'
    . '<div class="col-12 md:col-6"><div class="pg-field-label">' . h(lang('Score')) . '</div>' . $fs_seg('kind', array(PG_FEEDBACK_NPS => lang('NPS 0 to 10'), PG_FEEDBACK_CSAT => lang('CSAT 1 to 5'), PG_FEEDBACK_EFFORT => lang('Effort 1 to 7')), $fs['kind'], lang('Score'), $fs_kind_attributes) . '</div></div>'
    . $fs_lang_tabs . $fs_texts
    . '<details class="pg-start-advanced"' . ($fs_extra_count ? ' open' : '') . '><summary class="small"><b>' . h(lang('Extra questions')) . '</b> <span class="small fg-2">' . h(lang(array('string' => 'At most {var:1}, each a short text or a single choice.', 'vars' => array(PG_FEEDBACK_EXTRA_MAX)))) . '</span></summary>'
    . '<div class="pg-card-inner my-3">' . $fs_extra_rows . '</div>'
    . '<button type="button" class="pg-button pg-button-quiet pg-button-sm" data-fs-extra-add' . (($fs_extra_count >= PG_FEEDBACK_EXTRA_MAX) ? ' hidden' : '') . '><span class="bi bi-plus-lg" aria-hidden="true"></span>' . h(lang('Add a question')) . '</button></details>');

// 2. Sent after: what it follows (fixed once made), its filter, the delay.
$fs_filter = '';
$fs_any = array('service' => array('services', lang('Service'), lang('Any service')), 'event' => array('events', lang('Event'), lang('Any event')), 'course' => array('courses', lang('Course'), lang('Any course')), 'box' => array('boxes', lang('Mailbox'), lang('Any mailbox')), 'place' => array('places', lang('Place'), lang('Any place')));

foreach ($fs_any as $key => $spec) {
    if (isset($fs['filter'][$key])) {
        $fs_filter .= '<div class="col-12 md:col-6"><label class="pg-field-label" for="fs_filter_' . $key . '">' . h($spec[1]) . '</label>' . $fs_select('filter[' . $key . ']', 'fs_filter_' . $key, $fs_choices[$spec[0]], $fs['filter'][$key], $spec[2]) . '</div>';
    }
}

if ($fs['touchpoint'] === 'form') {
    $field_options = '<option value="0">' . h(lang('When it is sent')) . '</option>';

    foreach ($fs_choices['fields'] as $field_id => $field) {
        $field_options .= '<option value="' . $field_id . '" data-fs-form="' . $field['form'] . '"' . (($field_id === (int) $fs['filter']['field']) ? ' selected' : '') . '>' . h(lang(array('string' => 'When "{var:1}" is chosen as', 'vars' => array($field['label'])))) . '</option>';
    }

    $fs_filter .= '<div class="col-12 md:col-6"><label class="pg-field-label" for="fs_filter_form">' . h(lang('Form')) . '</label>' . $fs_select('filter[form]', 'fs_filter_form', $fs_choices['forms'], $fs['filter']['form'], lang('Any form')) . '</div>'
        . '<div class="col-12 md:col-6"><label class="pg-field-label" for="fs_filter_field">' . h(lang('Sent')) . '</label><select class="pg-input" id="fs_filter_field" name="filter[field]">' . $field_options . '</select></div>'
        . '<div class="col-12 md:col-6" data-fs-value-box' . (((int) $fs['filter']['field'] > 0) ? '' : ' hidden') . '><label class="pg-field-label" for="fs_filter_value">' . h(lang('Choice')) . '</label><input type="text" class="pg-input" id="fs_filter_value" name="filter[value]" maxlength="100" value="' . h($fs['filter']['value']) . '"></div>';
}

if ($fs['touchpoint'] === 'periodic') {
    $fs_filter .= '<div class="col-12 md:col-6"><label class="pg-field-label" for="fs_every">' . h(lang('Every (months)')) . '</label><input type="number" class="pg-input" id="fs_every" name="filter[every]" min="1" max="24" value="' . (int) $fs['filter']['every'] . '"></div>'
        . '<div class="col-12 md:col-6"><label class="pg-field-label" for="fs_active">' . h(lang('Active in the last (months)')) . '</label><input type="number" class="pg-input" id="fs_active" name="filter[active]" min="1" max="36" value="' . (int) $fs['filter']['active'] . '" aria-describedby="fs_active_help"><div class="pg-field-help" id="fs_active_help">' . h(lang('People with an order, booking, course or form in that time.')) . '</div></div>';
} else {
    $delay = pg_feedback_delay_split($fs['delay_minutes']);
    $fs_filter .= '<div class="col-12 md:col-6"><label class="pg-field-label" for="fs_delay">' . h(lang('Delay')) . '</label><div class="d-flex gap-2">'
        . '<input type="number" class="pg-input" id="fs_delay" name="delay_amount" min="0" max="2160" value="' . $delay['amount'] . '" aria-describedby="fs_delay_help">'
        . '<select class="pg-input w-auto" name="delay_unit" aria-label="' . h(lang('Unit')) . '"><option value="hours">' . h(lang('hours')) . '</option><option value="days"' . (($delay['unit'] === 'days') ? ' selected' : '') . '>' . h(lang('days')) . '</option></select></div>'
        . '<div class="pg-field-help" id="fs_delay_help">' . h(lang('Sent between 09:00 and 20:00, site time.')) . '</div></div>';
}

$fs_sent_after = $fs_card('after', lang('Sent after'), lang($fs_point['line']),
    '<div class="pg-card-inner-row mb-5"><span class="pg-icon-tile pg-tone-' . h($fs_point['tone']) . '" aria-hidden="true"><span class="bi ' . h($fs_point['icon']) . '"></span></span><strong class="pg-list-row-title">' . h(lang($fs_point['label'])) . '</strong></div>'
    . '<input type="hidden" name="touchpoint" value="' . h($fs['touchpoint']) . '"><div class="row gy-5">' . $fs_filter . '</div>');

// 3. Sent by: channel, the kind of message and the WhatsApp template.
$fs_purpose = ($fs['touchpoint'] === 'periodic')
    ? '<span class="pg-pill pg-tone-amber">' . h(lang('Commercial')) . '</span><div class="pg-field-help">' . h(lang('A survey every few months is commercial: it goes only to people who agreed to marketing messages.')) . '</div>'
    : $fs_seg('purpose', array(0 => lang('Service message'), 1 => lang('Commercial')), $fs['purpose'], lang('Kind of message'))
        . '<div class="pg-field-help">' . h(lang('Commercial goes only to people who agreed to marketing messages and İYS did not refuse.')) . '</div>';
$fs_sent_by = $fs_card('by', lang('Sent by'), lang('How the survey reaches the customer.'),
    '<div class="row gy-5"><div class="col-12 md:col-6"><div class="pg-field-label">' . h(lang('Channel')) . '</div>' . $fs_seg('channel', array_map('lang', pg_feedback_channel_names()), $fs['channel'], lang('Channel')) . '</div>'
    . '<div class="col-12 md:col-6"><div class="pg-field-label">' . h(lang('Kind of message')) . '</div>' . $fs_purpose . '</div></div>'
    . pg_feedback_whatsapp_card_html($fs['whatsapp'], $fs_wa_offered, pg_whatsapp_ready(), $fs['channel'] === 'whatsapp'));

// 4. Low scores.
$fs_channel_field = $fs_choices['channels']
    ? '<label class="pg-field-label" for="fs_ws_channel">' . h(lang('Workspace channel')) . '</label>' . $fs_select('ws_channel_id', 'fs_ws_channel', $fs_choices['channels'], $fs['ws_channel_id'], lang('No channel')) . '<div class="pg-field-help">' . h(lang('A line for each low score.')) . '</div>'
    : '<div class="pg-field-label">' . h(lang('Workspace channel')) . '</div><p class="pg-text-2 mb-0">' . h(lang('The Workspace is off, so no channel is told.')) . '</p>';
$fs_low = $fs_card('low', lang('Low scores'), lang('A low score becomes a follow-up for the staff member of the booking, form or conversation.'),
    '<div class="row gy-5"><div class="col-12 md:col-6"><label class="pg-field-label" for="fs_fallback">' . h(lang('Follow-up when the record names nobody')) . '</label>' . $fs_select('fallback_user_id', 'fs_fallback', $fs_choices['users'], $fs['fallback_user_id'], lang('Nobody')) . '</div>'
    . '<div class="col-12 md:col-6"><label class="pg-field-label" for="fs_escalation">' . h(lang('Joins after 48 hours')) . '</label>' . $fs_select('escalation_user_id', 'fs_escalation', $fs_choices['users'], $fs['escalation_user_id'], lang('Nobody')) . '<div class="pg-field-help">' . h(lang('When the follow-up is still open.')) . '</div></div>'
    . '<div class="col-12 md:col-6">' . $fs_channel_field . '</div></div>');

// 5. After the answer: the review invitation, the same for every score.
$fs_place = function_exists('pg_grev_state') ? (string) pg_grev_state()['place_id'] : '';
$fs_answer = $fs_card('answer', lang('After the answer'), lang('What the customer sees once they have answered.'),
    '<div class="d-flex align-items-center gap-3"><input type="checkbox" class="pg-switch" id="fs_review" name="review_invite" value="1"' . ($fs['review_invite'] ? ' checked' : '') . '><label class="pg-text-2" for="fs_review">' . h(lang('Invite them to write a review')) . '</label></div>'
    . '<div class="pg-field-help">' . h(lang('Shown to everyone who answers, whatever the score.')) . '</div>'
    . (($fs_place === '') ? '<p class="pg-text-2 mt-3 mb-0">' . h(lang('Set your Google place first; until then the invitation is not shown.')) . ' <a href="' . h(pg_grev_connections_url()) . '">' . h(lang('Google Reviews')) . '</a></p>' : ''));

// The status column: On, the e-mail's preview, the test, Delete or Archive.
$fs_previews = '';

foreach ($fs_defaults as $kind => $texts) {
    $question = pg_feedback_text_in($fs['texts']['question'], $fs_first, $fs_first);
    $comment = pg_feedback_text_in($fs['texts']['comment'], $fs_first, $fs_first);
    $fs_previews .= '<div data-fs-preview="' . $kind . '"' . (($kind === $fs['kind']) ? '' : ' hidden') . '>' . pg_feedback_mail_html($kind, ($question !== '') ? $question : $texts['question'], ($comment !== '') ? $comment : $texts['comment']) . '</div>';
}

$fs_used = ($fs_id > 0) && pg_feedback_survey_used($fs_id);
$fs_status = '
                        <div class="position-sticky" style="top:3.3rem;">
                            <div class="card mb-7">
                                <h2 class="card-header bg-reset border-0 pg-title-section">' . h(lang('Status')) . '</h2>
                                <div class="card-body pt-0">
                                    <div class="d-flex align-items-center gap-3 mb-2"><input type="checkbox" class="pg-switch" id="fs_enabled" name="enabled" value="1"' . ($fs['enabled'] ? ' checked' : '') . '><label class="pg-text-2" for="fs_enabled">' . h(lang('On')) . '</label>'
                                    . '<span class="pg-pill pg-tone-' . ($fs['enabled'] ? 'teal">' . h(lang('On')) : 'gray">' . h(lang('Off'))) . '</span></div>
                                    <p class="pg-text-meta">' . h($fs['enabled'] ? lang('Surveys go out as customers reach this point.') : lang('Nothing is sent while the survey is off.')) . '</p>
                                    <h3 class="pg-title-status mt-5">' . h(lang('E-mail preview')) . '</h3>
                                    <div class="pg-card-inner p-4" aria-hidden="true">' . $fs_previews . '</div>
                                    <div class="mt-5"><button type="submit" name="send_test" value="' . $fs_id . '" class="pg-button pg-button-quiet" aria-describedby="fs_test_help">' . h(lang('Send me a test')) . '</button>
                                    <div class="pg-field-help" id="fs_test_help">' . h((trim((string) $user['email_address']) !== '') ? lang(array('string' => 'Saves the survey, then sends its e-mail to {var:1}.', 'vars' => array($user['email_address']))) : lang('Your user has no e-mail address, so the test has nowhere to go.')) . '</div></div>
                                    ' . (($fs_id > 0) ? '<div class="mt-5 pt-3 border-top"><button type="submit" name="submit_remove" value="' . $fs_id . '" formnovalidate class="pg-button pg-button-danger" data-confirm-content="' . h($fs_used ? lang('The survey is switched off and leaves the list. Its answers stay in the results.') : lang('The survey is deleted. Nothing was sent with it.')) . '"><i class="bi ' . ($fs_used ? 'bi-archive' : 'bi-trash') . '" aria-hidden="true"></i>' . h($fs_used ? lang('Archive') : lang('Delete')) . '</button></div>' : '') . '
                                </div>
                            </div>
                        </div>';

$fs_title = ($fs_id > 0) ? $fs['name'] : lang('Add survey');

echo pg_page_shell(array(
    'title' => $fs_title,
    'extra classes' => 'campaign messages-area pg-measure-edit',
    'icon' => 'campaign',
    // output_header() prints the heading as markup; the name is staff text.
    'heading' => h($fs_title),
    'heading_description' => lang('A question after a service, its score and comment, and who follows up a low score.'),
    'breadcrumb' => array(
        array('label' => lang('Surveys'), 'url' => $fs_list),
        array('label' => $fs_title),
    ),
)) . '
<main id="content" class="container-fluid">
    <div class="row">
        <div class="col-12">
            ' . $liveform->get_messages() . '
            <form name="form" action="edit_feedback_survey.php" method="post" id="fs_form">
                ' . get_token_field() . '
                <input type="hidden" name="id" value="' . $fs_id . '">
                <div class="row">
                    <div class="col-12 lg:col-8 2xl:col-9 order-1">' . $fs_question . $fs_sent_after . $fs_sent_by . $fs_low . $fs_answer . '
                    </div>
                    <div class="col-12 lg:col-4 2xl:col-3 order-2">' . $fs_status . '
                    </div>
                    <div class="col-12 order-3 position-sticky" style="bottom:0; z-index:3;">
                        <nav class="buttons navigation text-center mb-7" aria-label="' . h(lang('data edit buttons')) . '">
                            <div class="container">
                                <div class="d-flex flex-wrap justify-content-center gap-3"><button type="submit" name="submit_save" value="1" class="pg-button pg-button-fill"><i class="bi bi-check2" aria-hidden="true"></i>' . h(lang('Save')) . '</button><a class="pg-button pg-button-quiet" href="' . h($fs_list) . '">' . h(lang('Cancel')) . '</a></div>
                            </div>
                        </nav>
                    </div>
                </div>
            </form>
        </div>
    </div>
</main>
<script src="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/assets/js/feedback_survey.js?v=' . filemtime(PG_FUNCTIONS_DIR . '/assets/js/feedback_survey.js') . '"></script>
' . output_footer();

$liveform->remove_form();
