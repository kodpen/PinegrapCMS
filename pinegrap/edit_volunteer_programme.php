<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * A volunteer programme's screen (Contacts › Volunteers): the head with the
 * name, the state and the button that opens or pauses it; the Applications
 * tab with Approve per row and for the ticked rows; the Volunteers tab; the
 * profile drawer (details, documents with their dates, shifts, hours, Pause
 * and Mark as left); the Shifts tab, the week's board with its Add shift
 * and shift drawers; and, for roles 0-2, the Set-up tab as six folded rows
 * (application form, roles, sign-up rules, calls and reminders, messages,
 * coordinators) with one Save; the Hours tab, the list of the day with
 * Check in, Check out and "Did not come", the hours waiting for approval
 * and the places' QR posters (view=poster prints one). It also takes the
 * list's add drawer. Roles 0-2 see every programme, a coordinator their own.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');

// The Set-up rows are drawn with Site Settings' fold helper, whose file
// holds only functions behind this gate.
if (!defined('PG_SETTINGS_ENTRY')) {
    define('PG_SETTINGS_ENTRY', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/settings/fold.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/list_table.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/volunteer_programme_setup.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/volunteer_board.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/volunteer_board_view.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/volunteer_signup.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/volunteer_hours.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/volunteer_hours_view.php');

$user = validate_user();

if (!pg_volunteer_ready()) {
    output_error(lang('The database has not been upgraded yet. Run the software update first.'));
}

$liveform = new liveform('edit_volunteer_programme');
$vp_staff = ((int) $user['role'] < 3);
$vp_id = (int) ($_POST['id'] ?? $_GET['id'] ?? 0);
$vp = ($vp_id > 0) ? pg_volunteer_programme_get($vp_id) : null;
$vp_self = OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/edit_volunteer_programme.php';
$vp_list = OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_volunteer_programmes.php';
$vp_views = $vp_staff ? array('shifts', 'applications', 'volunteers', 'hours', 'poster', 'setup') : array('shifts', 'applications', 'volunteers', 'hours', 'poster');
$vp_view = (string) ($_POST['view'] ?? $_GET['view'] ?? 'shifts');
$vp_view = in_array($vp_view, $vp_views, true) ? $vp_view : 'shifts';
// The board's week (its Monday, this week by default) and place filter.
$vp_monday = pg_volunteer_week_monday((string) ($_POST['week'] ?? $_GET['week'] ?? '')) ?? pg_volunteer_week_monday(date('Y-m-d'));
$vp_place = pg_waiting_text($_GET['place'] ?? '', 100);
$vp_forms = $vp_staff ? pg_application_program_forms($user) : array();

if ((($vp_id > 0) && ($vp === null)) || (($vp !== null) && !pg_volunteer_user_sees($vp, $user)) || (($vp === null) && !$vp_staff)) {
    output_error(lang('The programme was not found.'), 404);
}

// A place's printable QR poster: a whole page of its own.
if (($vp_view === 'poster') && ($vp !== null)) {
    $vp_poster_place = pg_waiting_text($_GET['place'] ?? '', 100);

    if (!in_array($vp_poster_place, pg_volunteer_programme_places($vp_id), true)) {
        output_error(lang('The place was not found.'), 404);
    }

    header('Cache-Control: no-store, private');
    echo pg_volunteer_poster_page($vp, $vp_poster_place);
    exit;
}

$vp_input = null;
$vp_shift_post = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_token_field();

    // A new programme: its name and form, then its Set-up.
    if ($vp === null) {
        $vp_new_name = pg_waiting_text($_POST['name'] ?? '', 150);
        $vp_new_page = (int) ($_POST['page_id'] ?? 0);
        $vp_new_id = (($vp_new_name !== '') && (($vp_new_page === 0) || isset($vp_forms[$vp_new_page]))) ? pg_volunteer_programme_create($vp_new_name, $vp_new_page, (int) $user['id']) : 0;

        if ($vp_new_id === 0) {
            (new liveform('view_volunteer_programmes'))->add_error(h(lang('Give the programme a name and choose its form from the list.')));
            go($vp_list . '?add=1');
        }

        log_activity('volunteer programme "' . $vp_new_name . '" was created');
        go($vp_self . '?id=' . $vp_new_id . '&view=setup');
    }

    $vp_back = $vp_self . '?id=' . $vp_id . '&view=' . $vp_view . (($vp_view === 'shifts') ? '&week=' . $vp_monday . (($vp_place !== '') ? '&place=' . rawurlencode($vp_place) : '') : '');

    // The board: anyone who sees the programme runs its shifts.
    if (isset($_POST['submit_shift_add'])) {
        $vp_shift_input = pg_volunteer_shift_input($_POST, $vp['roles']);

        if (!$vp_shift_input['errors']) {
            $vp_added = pg_volunteer_shift_add($vp_id, $vp_shift_input['shift'], $vp_shift_input['weeks'], (int) $user['id']);
            log_activity($vp_added . ' shifts of volunteer programme "' . $vp['name'] . '" were added');
            $liveform->add_notice(h(lang(array('string' => 'Shifts added: {var:1}.', 'vars' => array($vp_added)))));
            go($vp_self . '?id=' . $vp_id . '&view=shifts&week=' . pg_volunteer_week_monday(substr($vp_shift_input['shift']['start'], 0, 10)));
        }

        foreach ($vp_shift_input['errors'] as $vp_error) {
            $liveform->add_error(h($vp_error));
        }

        $vp_shift_post = $_POST;
    }

    if (isset($_POST['submit_copy_week'])) {
        $vp_added = pg_volunteer_week_copy_store($vp_id, $vp_monday, (int) $user['id']);

        if ($vp_added > 0) {
            log_activity($vp_added . ' shifts of volunteer programme "' . $vp['name'] . '" were copied from last week');
            $liveform->add_notice(h(lang(array('string' => 'Shifts added: {var:1}.', 'vars' => array($vp_added)))));
        } else {
            $liveform->add_error(h(lang('Nothing to copy: last week had no shifts, or this week has them already.')));
        }

        go($vp_back);
    }

    $vp_shift = (isset($_POST['submit_shift_call']) || isset($_POST['submit_shift_remove'])) ? pg_volunteer_shift_get($vp_id, (int) ($_POST['shift'] ?? 0)) : null;

    if ($vp_shift !== null && isset($_POST['submit_shift_call'])) {
        $vp_asked = pg_volunteer_shift_call($vp, $vp_shift);
        log_activity($vp_asked . ' volunteers were called for a shift of volunteer programme "' . $vp['name'] . '"');
        $liveform->add_notice(h(lang(array('string' => 'Volunteers asked: {var:1}.', 'vars' => array($vp_asked)))));
        go($vp_back . '&shift=' . $vp_shift['id']);
    }

    if ($vp_shift !== null && isset($_POST['submit_shift_remove'])) {
        $vp_told = pg_volunteer_shift_remove($vp, $vp_shift);

        if ($vp_told === false) {
            $liveform->add_error(h(lang('Hours were recorded on this shift: it stays.')));
            go($vp_back . '&shift=' . $vp_shift['id']);
        }

        log_activity('a shift of volunteer programme "' . $vp['name'] . '" was removed');
        $liveform->add_notice(h(lang(array('string' => 'The shift was removed. Volunteers told: {var:1}.', 'vars' => array($vp_told)))));
        go($vp_back);
    }

    // The list of the day: a check-in or out for a volunteer with no phone,
    // and "Did not come".
    $vp_checks = array('submit_check_in' => 'check_in', 'submit_check_out' => 'check_out', 'submit_no_show' => 'no_show');

    foreach ($vp_checks as $vp_button => $vp_step) {
        if (!isset($_POST[$vp_button])) {
            continue;
        }

        $vp_signup = (int) $_POST[$vp_button];
        $vp_known = (int) db_value("SELECT id FROM volunteer_signups WHERE id = '" . $vp_signup . "' AND programme_id = '" . $vp_id . "' AND shift_id IS NOT NULL");
        $vp_done = ($vp_known > 0) && (($vp_step === 'no_show') ? pg_volunteer_no_show_write($vp_id, $vp_signup) : pg_volunteer_check_write($vp_signup, $vp_step, (int) $user['id'], date('Y-m-d H:i:s')));

        if ($vp_done) {
            log_activity('a shift sign-up of volunteer programme "' . $vp['name'] . '" was marked ' . $vp_step);
        } else {
            $liveform->add_error(h(lang('The shift changed meanwhile. Please check it and try again.')));
        }

        go($vp_back);
    }

    // Hours: one row by its button, the ticked rows by the toolbar's fill.
    if (isset($_POST['submit_hours_approve']) || isset($_POST['submit_hours_approve_selected'])) {
        $vp_ids = isset($_POST['submit_hours_approve_selected']) ? (array) ($_POST['approve_hours'] ?? array()) : array($_POST['submit_hours_approve']);
        $vp_done = pg_volunteer_hours_approve($vp_id, array_filter($vp_ids, 'is_numeric'), pg_volunteer_hours_queue($vp_id, date('Y-m-d H:i:s')), (int) $user['id']);

        if ($vp_done > 0) {
            log_activity($vp_done . ' hours rows of volunteer programme "' . $vp['name'] . '" were approved');
            $liveform->add_notice(h(lang(array('string' => 'Hours approved: {var:1}.', 'vars' => array($vp_done)))));
        } else {
            $liveform->add_error(h(lang('Nothing was approved: choose hours waiting for approval first.')));
        }

        go($vp_back);
    }

    // A swap a volunteer asked for, when the programme wants it approved.
    if (isset($_POST['submit_swap_approve']) || isset($_POST['submit_swap_refuse'])) {
        $vp_approve = isset($_POST['submit_swap_approve']);
        $vp_shift_id = (int) ($_POST['shift'] ?? 0);

        if (pg_volunteer_swap_decide($vp, $vp_shift_id, (int) ($_POST[$vp_approve ? 'submit_swap_approve' : 'submit_swap_refuse'] ?? 0), $vp_approve)) {
            log_activity('a place swap on a shift of volunteer programme "' . $vp['name'] . '" was ' . ($vp_approve ? 'approved' : 'refused'));
            $liveform->add_notice(h($vp_approve ? lang('The place was handed over. Both volunteers are told by e-mail.') : lang('The request was refused. The place stays with its volunteer.')));
        } else {
            $liveform->add_error(h(lang('The shift changed meanwhile. Please check it and try again.')));
        }

        go($vp_back . '&shift=' . $vp_shift_id);
    }

    // A new signed link to the volunteer's own shifts, by e-mail.
    if (isset($_POST['submit_send_link'])) {
        $vp_person = pg_volunteer_people($vp, array('active', 'paused'), max(1, (int) $_POST['submit_send_link']))[(int) $_POST['submit_send_link']] ?? null;

        if (($vp_person !== null) && pg_volunteer_link_send($vp, $vp_person)) {
            log_activity('a sign-up link was sent to volunteer "' . $vp_person['name'] . '" of programme "' . $vp['name'] . '"');
            $liveform->add_notice(h(lang(array('string' => 'The link was sent to {var:1}.', 'vars' => array($vp_person['email_address'])))));
        } else {
            $liveform->add_error(h(lang('The link could not be sent: the volunteer needs a valid e-mail address.')));
        }

        go($vp_back . '&volunteer=' . (int) $_POST['submit_send_link']);
    }

    if ($vp_staff && (isset($_POST['submit_open']) || isset($_POST['submit_pause']))) {
        $vp_state = isset($_POST['submit_pause']) ? 'paused' : 'open';
        pg_volunteer_programme_set_state($vp_id, $vp_state);
        log_activity('volunteer programme "' . $vp['name'] . '" was ' . (($vp_state === 'paused') ? 'paused' : 'opened'));
        $liveform->add_notice(h(($vp_state === 'paused') ? lang('The programme is paused: its form makes no new applications.') : lang('The programme is open: its form makes applications.')));
        go($vp_back);
    }

    // Approve, Pause, Resume and Mark as left: the button's value is the
    // volunteer; "Approve selected" takes the ticked rows.
    $vp_moves = array('submit_approve' => 'active', 'submit_resume' => 'active', 'submit_pause_person' => 'paused', 'submit_left' => 'left');

    foreach ($vp_moves as $vp_button => $vp_to) {
        if (!isset($_POST[$vp_button]) && !(($vp_button === 'submit_approve') && isset($_POST['submit_approve_selected']))) {
            continue;
        }

        $vp_ids = isset($_POST['submit_approve_selected']) ? array_map('intval', (array) ($_POST['approve'] ?? array())) : array((int) $_POST[$vp_button]);
        $vp_done = 0;
        $vp_young = 0;

        foreach ($vp_ids as $vp_person_id) {
            $vp_person = pg_volunteer_people($vp, array('applied', 'active', 'paused'), max(1, $vp_person_id))[$vp_person_id] ?? null;

            if ($vp_person === null) {
                continue;
            }

            // An applicant under the programme's minimum age is not approved.
            if (($vp_person['state'] === 'applied') && ($vp['min_age'] > 0)) {
                $vp_birth = pg_volunteer_application_values(pg_application_entry_answers((array) db_items("SELECT name, data, file_id FROM form_data WHERE form_id = '" . (int) $vp_person['form_id'] . "'")), $vp['form_fields'])['birth_date'];
                $vp_age = pg_volunteer_age($vp_birth, date('Y-m-d'));

                if (($vp_age !== null) && ($vp_age < $vp['min_age'])) {
                    $vp_young++;
                    continue;
                }
            }

            if (pg_volunteer_move($vp, $vp_person, $vp_to, (int) $user['id'])) {
                log_activity('volunteer "' . $vp_person['name'] . '" of programme "' . $vp['name'] . '" was moved to ' . $vp_to);
                $vp_done++;
            }
        }

        if ($vp_done > 0) {
            $liveform->add_notice(h(lang(array('string' => 'Changed: {var:1}.', 'vars' => array($vp_done)))));
        }

        if ($vp_young > 0) {
            $liveform->add_error(h(lang(array('string' => '{var:1} applicants are under the minimum age of {var:2} and were not approved.', 'vars' => array($vp_young, $vp['min_age'])))));
        }

        if (($vp_done === 0) && ($vp_young === 0)) {
            $liveform->add_error(h(lang('Nothing was changed: choose an application or a volunteer first.')));
        }

        go($vp_back);
    }

    if ($vp_staff && isset($_POST['submit_save'])) {
        $vp_page = (int) ($_POST['page_id'] ?? 0);
        $vp_fields = isset($vp_forms[$vp_page]) ? array_keys(pg_application_program_fields($vp_page)) : array();
        $vp_courses = pg_course_ready() ? array_map('intval', (array) db_values("SELECT id FROM course")) : array();
        $vp_posted_users = array_map('intval', array_filter((array) ($_POST['coordinators'] ?? array()), 'is_numeric'));
        $vp_users = $vp_posted_users ? array_map('intval', (array) db_values("SELECT user_id FROM user WHERE user_id IN (" . implode(', ', $vp_posted_users) . ")")) : array();
        $vp_input = pg_volunteer_programme_input($_POST, array_keys($vp_forms), $vp_fields, $vp_courses, $vp_users);

        if (!$vp_input['errors']) {
            pg_volunteer_programme_save($vp_id, $vp_input['programme']);
            log_activity('volunteer programme "' . $vp_input['programme']['name'] . '" was modified');
            $liveform->add_notice(h(lang('The programme was saved.')));
            go($vp_back);
        }

        foreach ($vp_input['errors'] as $vp_error) {
            $liveform->add_error(h($vp_error));
        }
    }
}

// What Set-up shows: the posted values after a refused save, else the
// stored programme.
$vp_values = ($vp_input !== null) ? ($vp_input['programme'] + $vp) : $vp;
$vp_today = date('Y-m-d');
$vp_kinds = pg_volunteer_programme_kinds($vp);
$vp_role_names = array_map(function ($role) {
    return (string) $role['name'];
}, $vp['roles']);
$vp_people = pg_volunteer_people($vp, array('applied', 'active', 'paused', 'left'));
$vp_applied = array_filter($vp_people, function ($person) {
    return $person['state'] === 'applied';
});
$vp_volunteers = array_diff_key($vp_people, $vp_applied);
$vp_open = function ($id) use ($vp_id, $vp_view) {
    return 'edit_volunteer_programme.php?id=' . $vp_id . '&amp;view=' . $vp_view . '&amp;volunteer=' . (int) $id;
};
$vp_hours_text = function ($minutes) {
    return pg_format_number(round($minutes / 60, 1), ($minutes % 60) ? 1 : 0);
};

// The Applications tab: a row each, Approve quiet in the row and the
// ticked rows by the toolbar's fill.
$vp_rows = array();

foreach ($vp_applied as $id => $person) {
    $documents = pg_volunteer_documents_state($person['documents'], $vp_kinds, $vp_today);
    $vp_rows[] = array('id' => $id, 'cells' => array(
        'pick' => pg_list_cell('<input type="checkbox" class="pg-checkbox" name="approve[]" value="' . $id . '" aria-label="' . h(lang(array('string' => 'Choose {var:1}', 'vars' => array($person['name'])))) . '">', 'align-middle'),
        'name' => pg_list_cell('<a href="' . $vp_open($id) . '">' . h($person['name']) . '</a>'),
        'applied' => pg_list_cell(h(get_absolute_time(array('timestamp' => (int) $person['applied_at'], 'format' => 'plain_text'))), 'align-middle', array('data-pg-sort-value' => (int) $person['applied_at'])),
        'skills' => pg_list_cell(h(implode(', ', $person['skills']))),
        'documents' => pg_list_cell(($documents !== '') ? pg_volunteer_pill($documents) : '<span class="pg-text-meta">' . h(lang('None needed')) . '</span>', 'align-middle', array('data-pg-sort-value' => $documents)),
        'actions' => pg_list_cell('<button type="submit" name="submit_approve" value="' . $id . '" class="pg-button pg-button-quiet pg-button-sm">' . h(lang('Approve')) . '</button>', 'align-middle text-nowrap text-end'),
    ));
}

$vp_applications_list = pg_list_table_printed(array(
    'list' => 'volunteer_applications',
    'columns' => array(
        'pick' => array('heading' => lang('Choose'), 'kind' => 'actions'),
        'name' => array('heading' => lang('Name')),
        'applied' => array('heading' => lang('Date applied')),
        'skills' => array('heading' => lang('Skills')),
        'documents' => array('heading' => lang('Documents')),
        'actions' => array('heading' => lang('Actions'), 'kind' => 'actions'),
    ),
    'levels' => pg_list_state('volunteer_applications', array('applied' => lang('Date applied')), array('sort' => 'applied', 'order' => 'desc'))['sorts'],
    'rows' => $vp_rows,
));

// The Volunteers tab: state, roles they fit by skills, hours this year,
// show-up rate.
$vp_rows = array();

foreach ($vp_volunteers as $id => $person) {
    $fits = array();

    foreach ($vp['roles'] as $role) {
        if (!array_diff((array) $role['skills'], $person['skills'])) {
            $fits[] = $role['name'];
        }
    }

    $vp_rows[] = array('id' => $id, 'cells' => array(
        'name' => pg_list_cell('<a href="' . $vp_open($id) . '">' . h($person['name']) . '</a>'),
        'state' => pg_list_cell(pg_volunteer_pill($person['state']), 'align-middle', array('data-pg-sort-value' => $person['state'])),
        'roles' => pg_list_cell(h(implode(', ', $fits))),
        'hours' => pg_list_cell(h($vp_hours_text($person['year_minutes'])), 'align-middle', array('data-pg-sort-value' => $person['year_minutes'])),
        'show_up' => pg_list_cell(($person['show_up'] !== null) ? h($person['show_up'] . '%') : '<span class="pg-text-meta">' . h(lang('No shifts yet.')) . '</span>', 'align-middle', array('data-pg-sort-value' => (int) $person['show_up'])),
    ));
}

$vp_volunteers_list = pg_list_table_printed(array(
    'list' => 'volunteer_people',
    'columns' => array(
        'name' => array('heading' => lang('Name')),
        'state' => array('heading' => lang('Status')),
        'roles' => array('heading' => lang('Roles')),
        'hours' => array('heading' => lang('Hours this year'), 'kind' => 'number'),
        'show_up' => array('heading' => lang('Show-up rate'), 'kind' => 'number'),
    ),
    'levels' => pg_list_state('volunteer_people', array('name' => lang('Name')), array('sort' => 'name', 'order' => 'asc'))['sorts'],
    'rows' => $vp_rows,
));

$vp_lists_html = '<form id="vp_people_form" action="edit_volunteer_programme.php" method="post">' . get_token_field()
    . '<input type="hidden" name="id" value="' . $vp_id . '"><input type="hidden" name="view" value="' . $vp_view . '">'
    . (($vp_view === 'volunteers')
        ? pg_list_table_html($vp_volunteers_list['source'], $vp_volunteers_list['page'], lang('Volunteers'))
        : pg_list_table_html($vp_applications_list['source'], $vp_applications_list['page'], lang('Applicants')))
    . '</form>';

// The profile drawer, opened with the screen by ?volunteer=.
$vp_profile = '';
$vp_person = $vp_people[(int) ($_GET['volunteer'] ?? 0)] ?? null;

if ($vp_person !== null) {
    $line = function ($label, $value) {
        return '<div class="pg-card-inner-row"><span class="pg-text-meta flex-shrink-0" style="width:8rem">' . h($label) . '</span><span class="pg-text-2 min-width-0">' . $value . '</span></div>';
    };
    $days = array(1 => lang('Monday'), 2 => lang('Tuesday'), 3 => lang('Wednesday'), 4 => lang('Thursday'), 5 => lang('Friday'), 6 => lang('Saturday'), 7 => lang('Sunday'));
    // A document shows its kind, dates and its file, which get_file.php opens
    // for roles 0-2 and the programme's coordinators only; its number is not
    // listed here.
    $documents = '';

    foreach ($vp_person['documents'] as $document) {
        $ended = ((string) $document['valid_until'] !== '') && ((string) $document['valid_until'] < $vp_today);
        $open = ((string) ($document['file_name'] ?? '') !== '')
            ? ' <a class="pg-text-meta ms-2" href="' . h(OUTPUT_PATH . encode_url_path($document['file_name'])) . '" target="_blank" rel="noopener">' . h(lang('Open file')) . '</a>'
            : '';
        $documents .= $line($document['kind'], h(($document['valid_until'] !== null) ? lang(array('string' => 'Valid until {var:1}', 'vars' => array(get_absolute_time(array('timestamp' => strtotime($document['valid_until']), 'type' => 'date', 'format' => 'plain_text'))))) : lang('No end date')) . ($ended ? ' ' . pg_volunteer_pill('missing') : '') . $open);
    }

    $shifts = '';

    foreach (array_slice($vp_person['signups'], 0, 10) as $signup) {
        $when = ($signup['shift_start'] !== null) ? get_absolute_time(array('timestamp' => strtotime($signup['shift_start']), 'format' => 'plain_text')) : lang('Outside a shift');
        $shifts .= $line($when, h(trim(($vp_role_names[$signup['role']] ?? '') . ' · ' . (string) $signup['place'], ' ·')));
    }

    $buttons = '';

    if ($vp_person['state'] === 'applied') {
        $buttons = '<button type="submit" form="vp_people_form" name="submit_approve" value="' . (int) $vp_person['id'] . '" class="pg-button pg-button-fill">' . h(lang('Approve')) . '</button>';
    } elseif ($vp_person['state'] !== 'left') {
        $buttons = '<button type="submit" form="vp_people_form" name="submit_send_link" value="' . (int) $vp_person['id'] . '" class="pg-button pg-button-quiet" data-confirm-content="' . h(lang('A link to their own shifts is e-mailed to the volunteer. A link sent before stops working.')) . '"><i class="bi bi-envelope" aria-hidden="true"></i>' . h(lang('Send sign-up link')) . '</button>'
            . (($vp_person['state'] === 'paused')
                ? '<button type="submit" form="vp_people_form" name="submit_resume" value="' . (int) $vp_person['id'] . '" class="pg-button pg-button-quiet">' . h(lang('Make active')) . '</button>'
                : '<button type="submit" form="vp_people_form" name="submit_pause_person" value="' . (int) $vp_person['id'] . '" class="pg-button pg-button-quiet">' . h(lang('Pause')) . '</button>')
            . '<button type="submit" form="vp_people_form" name="submit_left" value="' . (int) $vp_person['id'] . '" class="pg-button pg-button-danger" data-confirm-content="' . h(lang('The volunteer is marked as left and takes no new shifts. Their hours stay.')) . '">' . h(lang('Mark as left')) . '</button>';
    }

    $vp_profile = '
    <dialog class="drawer drawer-end" id="vp_profile" aria-labelledby="vp_profile_title" style="width:min(520px,100%);--pg-drawer-height:85vh" data-open="1">
        <div class="d-flex flex-column h-100">
            <div class="drawer-header">
                <div class="flex-grow-1 min-width-0">
                    <h5 class="drawer-title mb-0" id="vp_profile_title">' . h($vp_person['name']) . ' ' . pg_volunteer_pill($vp_person['state']) . '</h5>
                    <p class="small fg-2 mb-0 mt-1">' . h(lang(array('string' => 'Applied on {var:1}', 'vars' => array(get_absolute_time(array('timestamp' => (int) $vp_person['applied_at'], 'type' => 'date', 'format' => 'plain_text')))))) . '</p>
                </div>
                <button type="button" class="btn-close ms-auto" data-bs-dismiss="drawer" aria-label="' . h(lang('Close')) . '"></button>
            </div>
            <div class="drawer-body flex-grow-1 overflow-auto">
                <h6 class="pg-field-label">' . h(lang('Details')) . '</h6>
                <div class="pg-card-inner mb-5">'
                    . $line(lang('E-mail'), h((string) $vp_person['email_address']))
                    . $line(lang('Phone'), h((string) $vp_person['mobile_phone']))
                    . $line(lang('Skills'), h(implode(', ', $vp_person['skills'])))
                    . $line(lang('Free days'), h($vp_person['hours'] ? implode(', ', array_intersect_key($days, $vp_person['hours'])) : lang('Any day')))
                    . $line(lang('Shift calls'), h($vp_person['calls'] ? lang('On') : lang('Off')))
                    . '<div class="pg-card-inner-row"><a class="pg-link" href="edit_contact.php?id=' . (int) $vp_person['contact_id'] . '">' . h(lang('Open the contact')) . '</a>' . (((int) $vp_person['form_id'] > 0) ? ' · <a class="pg-link" href="edit_submitted_form.php?id=' . (int) $vp_person['form_id'] . '">' . h(lang('Open the application')) . '</a>' : '') . '</div>
                </div>
                <h6 class="pg-field-label">' . h(lang('Documents')) . '</h6>
                ' . (($documents !== '') ? '<div class="pg-card-inner mb-5">' . $documents . '</div>' : '<p class="pg-text-meta mb-5">' . h(lang('No documents yet.')) . '</p>') . '
                <h6 class="pg-field-label">' . h(lang('Shifts')) . '</h6>
                ' . (($shifts !== '') ? '<div class="pg-card-inner mb-5">' . $shifts . '</div>' : '<p class="pg-text-meta mb-5">' . h(lang('No shifts yet.')) . '</p>') . '
                <h6 class="pg-field-label">' . h(lang('Hours')) . '</h6>
                <p class="pg-text-2 mb-0">' . h(lang(array('string' => '{var:1} approved hours this year', 'vars' => array($vp_hours_text($vp_person['year_minutes']))))) . (($vp_person['show_up'] !== null) ? ' · ' . h(lang(array('string' => 'show-up rate {var:1}%', 'vars' => array($vp_person['show_up'])))) : '') . '</p>
            </div>
            ' . (($buttons !== '') ? '<div class="drawer-footer border-top p-5 d-flex align-items-center gap-3"><span class="flex-grow-1"></span>' . $buttons . '</div>' : '') . '
        </div>
    </dialog>';
}

// The Shifts tab: the week's board, the Add shift drawer (opened with the
// screen after a refused add) and, by ?shift=, a shift's drawer.
$vp_board_html = '';
$vp_board_bar = array();

if ($vp_view === 'shifts') {
    $vp_now = date('Y-m-d H:i:s');
    $vp_week_shifts = pg_volunteer_board_shifts($vp_id, $vp_monday, pg_volunteer_add_days($vp_monday, 7));
    $vp_board_query = 'id=' . $vp_id . '&amp;view=shifts&amp;week=' . $vp_monday . (($vp_place !== '') ? '&amp;place=' . rawurlencode($vp_place) : '');
    $vp_board_html = pg_volunteer_board_html($vp, $vp_week_shifts, $vp_monday, $vp_place, $vp_now)
        . ($vp['roles'] ? pg_volunteer_shift_add_html($vp, $vp_monday, (array) $vp_shift_post, $vp_shift_post !== null) : '');
    $vp_open_shift = isset($_GET['shift']) ? pg_volunteer_shift_get($vp_id, (int) $_GET['shift']) : null;

    if ($vp_open_shift !== null) {
        $vp_to_call = count(pg_volunteer_shift_call_ids($vp, $vp_open_shift, pg_volunteer_call_people($vp, $vp_open_shift), $vp_now));
        $vp_board_html .= pg_volunteer_shift_drawer_html($vp, $vp_open_shift, $vp_to_call, $vp_board_query, $vp_now);
    }

    $vp_board_bar = array(
        'primary' => array(
            '<button type="button" class="pg-button pg-button-fill" data-pg-vp-drawer="vs_add"' . ($vp['roles'] ? '' : ' disabled') . '><i class="bi bi-plus-circle" aria-hidden="true"></i>' . h(lang('Add shift')) . '</button>',
            '<button type="submit" form="vp_state_form" name="submit_copy_week" value="1" class="pg-button pg-button-quiet"><i class="bi bi-copy" aria-hidden="true"></i>' . h(lang('Copy last week')) . '</button>',
        ),
        'grow' => pg_volunteer_board_week_html($vp, $vp_monday, $vp_place),
        'search' => pg_volunteer_board_places_html($vp, pg_volunteer_board_places(array_values($vp_week_shifts)), $vp_monday, $vp_place),
    );
}

// The Hours tab: "Did not come" is written first, so the list of the day
// and the show-up rate read it.
$vp_hours_html = '';
$vp_hours_queue = array();

if ($vp_view === 'hours') {
    $vp_now = date('Y-m-d H:i:s');
    pg_volunteer_no_show_pass($vp_id, $vp_now);
    $vp_hours_queue = pg_volunteer_hours_queue($vp_id, $vp_now);
    $vp_hours_html = '<form id="vp_hours_form" action="edit_volunteer_programme.php" method="post">' . get_token_field()
        . '<input type="hidden" name="id" value="' . $vp_id . '"><input type="hidden" name="view" value="hours">'
        . pg_volunteer_day_html($vp, pg_volunteer_day_rows($vp_id, substr($vp_now, 0, 10)), $vp_now)
        . pg_volunteer_hours_queue_html($vp, $vp_hours_queue)
        . '</form>'
        . pg_volunteer_posters_html($vp, pg_volunteer_programme_places($vp_id));
}

$vp_setup_html = ($vp_staff && ($vp_view === 'setup')) ? pg_volunteer_setup_html($vp_values, $vp_forms, $vp_list) : '';

$vp_tab = function ($view, $label, $count = null) use ($vp_id, $vp_view) {
    return '<a class="pg-seg-item' . (($vp_view === $view) ? ' is-active" aria-current="page' : '') . '" href="edit_volunteer_programme.php?id=' . $vp_id . '&amp;view=' . $view . '">' . h($label) . (($count !== null) ? ' <span class="pg-seg-count">' . (int) $count . '</span>' : '') . '</a>';
};
$vp_state_button = !$vp_staff ? '' : (($vp['state'] === 'paused')
    ? '<button type="submit" form="vp_state_form" name="submit_open" value="1" class="pg-button pg-button-quiet"><i class="bi bi-play-circle" aria-hidden="true"></i>' . h(lang('Open the programme')) . '</button>'
    : '<button type="submit" form="vp_state_form" name="submit_pause" value="1" class="pg-button pg-button-quiet" data-confirm-content="' . h(lang('Its form makes no new applications. Volunteers keep their shifts.')) . '"><i class="bi bi-pause-circle" aria-hidden="true"></i>' . h(lang('Pause the programme')) . '</button>');

echo pg_page_shell(array(
    'title' => $vp['name'],
    'extra classes' => 'contacts pg-measure-edit',
    'icon' => 'contacts',
    'help' => 'contacts',
    // output_header() prints the heading as markup; the name is staff text.
    'heading' => h($vp['name']) . ' ' . pg_volunteer_programme_pill($vp['state']),
    'heading_description' => lang('Shifts, applications, volunteers and the programme\'s set-up.'),
    'breadcrumb' => array(
        array('label' => lang('Volunteers'), 'url' => $vp_list),
        array('label' => $vp['name']),
    ),
)) . '
<main id="content" class="container-fluid">
    ' . $liveform->get_messages() . '
    ' . pg_screen_toolbar(array(
        'primary' => ($vp_view === 'applications')
            ? array('<button type="submit" form="vp_people_form" name="submit_approve_selected" value="1" class="pg-button pg-button-fill"' . ($vp_applied ? '' : ' disabled') . '><i class="bi bi-check2-circle" aria-hidden="true"></i>' . h(lang('Approve selected')) . '</button>', $vp_state_button)
            : (($vp_view === 'shifts') ? array_merge($vp_board_bar['primary'], array($vp_state_button))
            : (($vp_view === 'hours') ? array('<button type="submit" form="vp_hours_form" name="submit_hours_approve_selected" value="1" class="pg-button pg-button-fill"' . ($vp_hours_queue ? '' : ' disabled') . '><i class="bi bi-check2-circle" aria-hidden="true"></i>' . h(lang('Approve selected')) . '</button>', $vp_state_button) : $vp_state_button)),
        'grow' => $vp_board_bar['grow'] ?? '',
        'search' => $vp_board_bar['search'] ?? '',
        'view' => '<span class="pg-seg" role="group" aria-label="' . h(lang('Programme')) . '">' . $vp_tab('shifts', lang('Shifts')) . $vp_tab('applications', lang('Applicants'), count($vp_applied)) . $vp_tab('volunteers', lang('Volunteers'), count($vp_volunteers)) . $vp_tab('hours', lang('Hours')) . ($vp_staff ? $vp_tab('setup', lang('Set-up')) : '') . '</span>',
    )) . '
    <form id="vp_state_form" action="edit_volunteer_programme.php" method="post">' . get_token_field() . '<input type="hidden" name="id" value="' . $vp_id . '"><input type="hidden" name="view" value="' . $vp_view . '"><input type="hidden" name="week" value="' . $vp_monday . '"></form>
    ' . (($vp_view === 'setup') ? $vp_setup_html : (($vp_view === 'shifts') ? $vp_board_html : (($vp_view === 'hours') ? $vp_hours_html : $vp_lists_html))) . '
    ' . $vp_profile . '
</main>
' . pg_js_bundle_tags('assets/js/pg_list_table.min.js') . pg_fields_script() . '
<script src="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/assets/js/volunteer_programme.js?v=' . filemtime(PG_FUNCTIONS_DIR . '/assets/js/volunteer_programme.js') . '"></script>
' . output_footer();

$liveform->remove_form();
