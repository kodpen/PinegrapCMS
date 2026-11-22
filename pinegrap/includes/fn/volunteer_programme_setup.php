<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The Set-up tab of a volunteer programme (edit_volunteer_programme.php):
 * the name, then six folded rows, one open at a time, each with its line of
 * state: application form, roles, sign-up rules, calls and reminders,
 * messages and coordinators; Save as the fill. Loaded by that screen only.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/user_picker.php');

/**
 * The Set-up form of a programme.
 *
 * @param array  $values the programme, or the posted values after a refused save
 * @param array  $forms  pg_application_program_forms(): page id => name
 * @param string $cancel where Cancel goes
 * @return string HTML
 */
function pg_volunteer_setup_html(array $values, array $forms, $cancel)
{
    $missing = pg_volunteer_programme_missing($values);
    $not_set = array(lang('Not set'), 'pg-tone-amber');
    $remove = '<button type="button" class="pg-icon-button pg-icon-button-sm" data-pg-vp-remove aria-label="' . h(lang('Remove')) . '"><span class="bi bi-x-lg" aria-hidden="true"></span></button>';
    $number = function ($name, $value, $label, $min, $max, $end = '') {
        return '<div class="col-12 md:col-6"><label class="pg-field-label" for="vp_' . $name . '">' . h($label) . '</label>'
            . '<span class="pg-input"><input type="number" id="vp_' . $name . '" name="' . $name . '" min="' . $min . '" max="' . $max . '" step="1" value="' . (int) $value . '">' . (($end !== '') ? '<span class="pg-input-end">' . h($end) . '</span>' : '') . '</span></div>';
    };
    $check = function ($name, $value, $on, $label, $kind = 'pg-checkbox') {
        $id = 'vp_' . preg_replace('/[^a-z0-9_]/', '_', $name . '_' . $value);

        return '<div class="d-flex align-items-center gap-3 mb-3"><input type="checkbox" class="' . $kind . '" id="' . $id . '" name="' . $name . '" value="' . h($value) . '"' . ($on ? ' checked' : '') . '><label class="pg-text-2" for="' . $id . '">' . h($label) . '</label></div>';
    };

    // 1. Application form: the form and the fields that give the profile.
    $page_id = (int) $values['page_id'];
    $form_options = '<option value="0">' . h(lang('No form')) . '</option>';

    foreach ($forms as $id => $form) {
        $form_options .= '<option value="' . (int) $id . '"' . (($id === $page_id) ? ' selected' : '') . '>' . h($form['name']) . '</option>';
    }

    $fields = ($page_id > 0) ? pg_application_program_fields($page_id) : array();
    $field_select = function ($use, $label) use ($fields, $values) {
        $options = '<option value="">' . h(lang('None')) . '</option>';

        foreach ($fields as $name => $field) {
            $options .= '<option value="' . h($name) . '"' . (($values['form_fields'][$use] === $name) ? ' selected' : '') . '>' . h(($field['label'] !== '') ? $field['label'] : $name) . '</option>';
        }

        return '<div class="col-12 md:col-4"><label class="pg-field-label" for="vp_field_' . $use . '">' . h($label) . '</label><select class="pg-input" id="vp_field_' . $use . '" name="field_' . $use . '">' . $options . '</select></div>';
    };
    $form_html = '<div class="row gy-5 mb-3"><div class="col-12"><label class="pg-field-label" for="vp_page_id">' . h(lang('Application form')) . '</label><select class="pg-input" id="vp_page_id" name="page_id">' . $form_options . '</select>'
        . '<div class="pg-field-help">' . h(lang('A completed entry with an e-mail becomes an application. Save after changing the form to choose its fields.')) . '</div></div>'
        . $field_select('skills', lang('Skills from')) . $field_select('birth_date', lang('Birth date from')) . $field_select('free_days', lang('Free days from')) . '</div>';
    $form_line = ($page_id > 0) ? ($forms[$page_id]['name'] ?? lang('A form you cannot open')) : lang('No form yet: nobody can apply.');

    // 2. Roles: a row each, its skills, document kinds and trainings.
    $courses = pg_course_ready() ? (array) db_items("SELECT id, name FROM course ORDER BY name") : array();
    $role_row = function ($token, array $role) use ($remove, $courses) {
        $options = '';

        foreach ($courses as $course) {
            $options .= '<option value="' . (int) $course['id'] . '"' . (in_array((int) $course['id'], (array) ($role['trainings'] ?? array()), true) ? ' selected' : '') . '>' . h($course['name']) . '</option>';
        }

        return '<div class="pg-card-inner-row flex-wrap align-items-start" data-pg-vp-row>'
            . '<input type="hidden" name="role_row[]" value="' . h($token) . '">'
            . '<div class="row gy-3 flex-grow-1">'
            . '<div class="col-12 md:col-6"><input type="text" class="pg-input" name="role_name[]" maxlength="60" value="' . h($role['name'] ?? '') . '" aria-label="' . h(lang('Role')) . '" placeholder="' . h(lang('Role')) . '"></div>'
            . '<div class="col-12 md:col-6"><input type="text" class="form-control tagin" data-pg-chips name="role_skills[]" value="' . h(implode(',', (array) ($role['skills'] ?? array()))) . '" aria-label="' . h(lang('Skills needed')) . '" data-placeholder="' . h(lang('Skills needed')) . '"></div>'
            . '<div class="col-12 md:col-6"><input type="text" class="form-control tagin" data-pg-chips name="role_documents[]" value="' . h(implode(',', (array) ($role['documents'] ?? array()))) . '" aria-label="' . h(lang('Documents needed')) . '" data-placeholder="' . h(lang('Documents needed')) . '"></div>'
            . ($courses ? '<div class="col-12 md:col-6"><select class="pg-input" data-pg-combobox multiple name="role_trainings[' . h($token) . '][]" aria-label="' . h(lang('Trainings needed')) . '" data-placeholder="' . h(lang('Trainings needed')) . '">' . $options . '</select></div>' : '')
            . '</div>' . $remove . '</div>';
    };
    $role_rows = '';

    foreach ($values['roles'] as $key => $role) {
        $role_rows .= $role_row((string) $key, $role);
    }

    $roles_html = '<div class="pg-card-inner mb-3" data-pg-vp-list="role">' . $role_rows . '</div>'
        . '<template data-pg-vp-template="role">' . $role_row('__ROW__', array()) . '</template>'
        . '<button type="button" class="pg-button pg-button-quiet pg-button-sm" data-pg-vp-add="role"><span class="bi bi-plus-lg" aria-hidden="true"></span>' . h(lang('Add a role')) . '</button>'
        . '<div class="pg-field-help mt-3">' . h(lang('Do not ask for a health report.')) . '</div>';
    $roles_line = $values['roles'] ? implode(', ', array_column($values['roles'], 'name')) : lang('No role yet.');

    // 3. Sign-up rules.
    $rules_html = '<div class="row gy-5 mb-5">' . $number('cutoff_hours', $values['cutoff_hours'], lang('Cancel until'), 0, 720, lang('hours before'))
        . $number('min_age', $values['min_age'], lang('Minimum age'), 0, 99, lang('years')) . '</div>'
        . '<div class="pg-field-help mb-3">' . h(lang('The minimum age is checked on Approve when the form gives a birth date; 0 is no limit.')) . '</div>'
        . '<div class="d-flex align-items-center gap-3"><input type="checkbox" class="pg-switch" id="vp_swap_approval" name="swap_approval" value="1"' . ($values['swap_approval'] ? ' checked' : '') . '><label class="pg-text-2" for="vp_swap_approval">' . h(lang('A place offered to another volunteer needs a coordinator\'s approval')) . '</label></div>';
    $rules_line = lang(array('string' => 'Cancel until {var:1} hours before', 'vars' => array($values['cutoff_hours']))) . ($values['min_age'] ? ' · ' . lang(array('string' => 'from age {var:1}', 'vars' => array($values['min_age']))) : '');

    // 4. Calls and reminders.
    $channel_words = array('email' => lang('E-mail'), 'sms' => lang('SMS'), 'whatsapp' => lang('WhatsApp'));
    $calls_html = '<div class="row gy-5 mb-5">' . $number('call_days', $values['call_days'], lang('Call volunteers for a short shift'), 0, 14, lang('days before')) . '</div>'
        . '<div class="pg-field-label">' . h(lang('Channels a volunteer may choose')) . '</div>';

    foreach ($channel_words as $channel => $label) {
        $calls_html .= $check('call_channels[]', $channel, in_array($channel, $values['call_channels'], true), $label);
    }

    $calls_html .= '<div class="pg-field-help">' . h(lang('A call is a service message: a volunteer turns calls off on their page. Everyone signed up gets a reminder the day before.')) . '</div>';
    $calls_line = $values['call_days']
        ? lang(array('string' => '{var:1} days before · {var:2}', 'vars' => array($values['call_days'], implode(', ', array_intersect_key($channel_words, array_flip($values['call_channels']))) ?: lang('no channel'))))
        : lang('No calls for short shifts.');

    // 5. Messages: a tab each, its subject and text.
    $tabs = '';
    $panes = '';
    $message_words = array('welcome' => lang('Welcome'), 'call' => lang('Call'), 'reminder' => lang('Reminder'), 'thanks' => lang('Thank you'));

    foreach ($message_words as $kind => $label) {
        $text = $values['texts'][$kind];
        $tabs .= '<button type="button" class="pg-seg-item" aria-pressed="' . (($kind === 'welcome') ? 'true' : 'false') . '" data-pg-vp-tab="' . $kind . '">' . h($label) . '</button>';
        $panes .= '<div data-pg-vp-pane="' . $kind . '"' . (($kind === 'welcome') ? '' : ' hidden') . '>'
            . '<input type="text" class="pg-input mb-3" name="text[' . $kind . '][subject]" maxlength="150" value="' . h($text['subject']) . '" aria-label="' . h(lang('Subject')) . '" placeholder="' . h(pg_volunteer_message(array(), $kind, array('name' => '{name}', 'programme' => '{programme}'))['subject']) . '">'
            . '<textarea class="pg-input" name="text[' . $kind . '][body]" rows="4" maxlength="2000" aria-label="' . h($label) . '" placeholder="' . h(pg_volunteer_message(array(), $kind, array('name' => '{name}', 'programme' => '{programme}'))['body']) . '">' . h($text['body']) . '</textarea></div>';
    }

    $messages_html = '<span class="pg-seg mb-3" role="group" aria-label="' . h(lang('Message')) . '">' . $tabs . '</span>' . $panes
        . '<div class="pg-field-help mt-2">' . h(lang('Leave a text empty for the standard one. {name} and {programme} are filled in.')) . '</div>';
    $written = count(array_filter($values['texts'], function ($text) {
        return ($text['subject'] !== '') || ($text['body'] !== '');
    }));
    $messages_line = $written ? lang(array('string' => '{var:1} of 4 written; the rest are standard', 'vars' => array($written))) : lang('The standard texts');

    // 6. Coordinators: a person a row; Add from the user search.
    $person_row = function ($id, $name) use ($remove) {
        return '<div class="pg-card-inner-row" data-pg-vp-row><input type="hidden" name="coordinators[]" value="' . (int) $id . '">'
            . '<span class="pg-icon-tile" aria-hidden="true"><span class="bi bi-person"></span></span>'
            . '<span class="flex-grow-1 min-width-0 pg-list-row-title" data-pg-vp-person>' . h($name) . '</span>' . $remove . '</div>';
    };
    $people = '';

    foreach ($values['coordinators'] as $id) {
        $people .= $person_row($id, pg_user_picker_label($id));
    }

    $coordinators_html = '<p class="pg-field-help mb-3">' . h(lang('They see this programme, approve its applications and run its shifts.')) . '</p>'
        . '<div class="pg-card-inner mb-3" data-pg-vp-list="person">' . $people . '</div>'
        . '<template data-pg-vp-template="person">' . $person_row(0, '') . '</template>'
        . '<div class="d-flex flex-wrap align-items-center gap-2"><span class="flex-grow-1" style="min-width:14rem"><select class="pg-input" id="vp_person_pick" data-pg-combobox data-pg-source="user_search_action.php" data-pg-min-length="2" data-placeholder="' . h(lang('Search for a user')) . '" aria-label="' . h(lang('Search for a user')) . '"><option value=""></option></select></span>'
        . '<button type="button" class="pg-button pg-button-quiet pg-button-sm" data-pg-vp-add="person"><span class="bi bi-plus-lg" aria-hidden="true"></span>' . h(lang('Add')) . '</button></div>';
    $coordinators_line = $values['coordinators'] ? lang(array('string' => '{var:1} coordinators', 'vars' => array(count($values['coordinators'])))) : lang('No coordinator yet.');

    $row = function ($key, $name, $icon, $line, $html, $code = '') use ($missing, $not_set) {
        $attention = ($code !== '') && in_array($code, $missing, true);

        return array('key' => $key, 'name' => $name, 'icon' => $icon, 'tone' => '', 'line' => $line, 'pill' => $attention ? $not_set : null, 'attention' => $attention, 'html' => $html);
    };

    return '<form id="vp_form" action="edit_volunteer_programme.php" method="post">
        ' . get_token_field() . '
        <input type="hidden" name="id" value="' . (int) $values['id'] . '"><input type="hidden" name="view" value="setup">
        <div class="row gy-5 mb-5"><div class="col-12 md:col-6"><label class="pg-field-label" for="vp_name">' . h(lang('Name')) . '</label><input type="text" class="pg-input" id="vp_name" name="name" maxlength="150" required value="' . h($values['name']) . '"></div></div>
        ' . pg_settings_fold(array(
            $row('form', lang('Application form'), 'bi-ui-checks', $form_line, $form_html, 'form'),
            $row('roles', lang('Roles'), 'bi-person-badge', $roles_line, $roles_html, 'roles'),
            $row('rules', lang('Sign-up rules'), 'bi-calendar-check', $rules_line, $rules_html),
            $row('calls', lang('Calls and reminders'), 'bi-megaphone', $calls_line, $calls_html),
            $row('messages', lang('Messages'), 'bi-chat-dots', $messages_line, $messages_html),
            $row('coordinators', lang('Coordinators'), 'bi-people', $coordinators_line, $coordinators_html, 'coordinators'),
        )) . '
        <div class="pg-floatbar d-flex flex-wrap align-items-center gap-2 p-3 my-5">
            <button type="submit" name="submit_save" value="1" class="pg-button pg-button-fill">' . h(lang('Save')) . '</button><a class="pg-button pg-button-quiet" href="' . h($cancel) . '">' . h(lang('Cancel')) . '</a>
        </div>
    </form>';
}
