<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * A walk-in queue place's screen (Calendars › Queue): the head with the
 * name, the state and the button that opens or closes the queue; the Set-up
 * tab as six folded rows (services, hours, taking a number, notices, order,
 * keep personal data) with one Save; the Desks tab, a desk a row with Add
 * desk and Remove, and the staff who may open a desk; the Today tab is the
 * desk screen (queue_desk.php); and "Add a number" for a person at the counter. It also takes the list's add drawer (a name and the
 * first service: a new closed place) and its row menu's Delete. Roles 0-2.
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
require_once(PG_FUNCTIONS_DIR . '/includes/fn/booking_agenda.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/user_picker.php');

$user = validate_user();
validate_area_access($user, 'manager');

if (!pg_waiting_ready()) {
    output_error(lang('The database has not been upgraded yet. Run the software update first.'));
}

$liveform = new liveform('edit_queue_place');
// The list's row menu posts Delete with the place's id as its value.
$qp_id = (int) ($_POST['id'] ?? $_POST['submit_delete'] ?? $_GET['id'] ?? 0);
$qp_place = ($qp_id > 0) ? pg_waiting_place_get($qp_id) : null;
$qp_self = OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/edit_queue_place.php';
$qp_list = OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_queue_places.php';
$qp_view = (($_POST['view'] ?? $_GET['view'] ?? '') === 'desks') ? 'desks' : 'setup';
$qp_today = date('Y-m-d');

if (($qp_id > 0) && ($qp_place === null)) {
    output_error(lang('The place was not found.'), 404);
}

$qp_desks = ($qp_place !== null) ? pg_waiting_desks($qp_id) : array();
$qp_missing_words = array('services' => lang('Services'), 'hours' => lang('Hours'), 'ways' => lang('Taking a number'), 'desks' => lang('Desks'));
$qp_input = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_token_field();
    $qp_list_form = new liveform('view_queue_places');

    // A new place: its name and first service, then the place, closed.
    if ($qp_place === null) {
        $qp_new_name = pg_waiting_text($_POST['name'] ?? '', 150);
        $qp_new_service = pg_waiting_text($_POST['service'] ?? '', 60);
        $qp_new_id = (($qp_new_name !== '') && ($qp_new_service !== '')) ? pg_waiting_place_create($qp_new_name, $qp_new_service, (int) $user['id']) : 0;

        if ($qp_new_id === 0) {
            $qp_list_form->add_error(h(lang('Give the place a name and its first service.')));
            go($qp_list . '?add=1');
        }

        log_activity('queue place "' . $qp_new_name . '" was created');
        go($qp_self . '?id=' . $qp_new_id);
    }

    $qp_back = $qp_self . '?id=' . $qp_id . (($qp_view === 'desks') ? '&view=desks' : '');

    if (isset($_POST['submit_delete'])) {
        if (pg_waiting_place_delete($qp_id)) {
            log_activity('queue place "' . $qp_place['name'] . '" was deleted');
            $qp_list_form->add_notice(h(lang(array('string' => 'The place "{var:1}" was deleted.', 'vars' => array($qp_place['name'])))));
        } else {
            $qp_list_form->add_error(h(lang('A place where a number was taken today cannot be deleted.')));
        }

        go($qp_list);
    }

    if (isset($_POST['submit_open'])) {
        $qp_missing = array_map(function ($code) use ($qp_missing_words) {
            return $qp_missing_words[$code];
        }, pg_waiting_place_missing($qp_place, count($qp_desks)));

        if ($qp_missing) {
            $liveform->add_error(h(lang(array('string' => 'Set these first: {var:1}.', 'vars' => array(implode(', ', $qp_missing))))));
        } else {
            pg_waiting_place_set_status($qp_id, 'open');
            log_activity('queue place "' . $qp_place['name'] . '" was opened');
            $liveform->add_notice(h(lang('The queue is open: people can take a number within its hours.')));
        }

        go($qp_back);
    }

    if (isset($_POST['submit_close'])) {
        pg_waiting_place_set_status($qp_id, 'closed');
        log_activity('queue place "' . $qp_place['name'] . '" was closed');
        $liveform->add_notice(h(lang('The queue is closed: no new numbers are given.')));
        go($qp_back);
    }

    if (isset($_POST['submit_desk_add'])) {
        $qp_desk = pg_waiting_desk_input($_POST, $qp_place['services']);

        if ($qp_desk['error'] !== '') {
            $liveform->add_error(h($qp_desk['error']));
            go($qp_back . '&add=1');
        }

        pg_waiting_desk_add($qp_id, $qp_desk['name'], $qp_desk['services']);
        $liveform->add_notice(h(lang(array('string' => 'The desk "{var:1}" was added.', 'vars' => array($qp_desk['name'])))));
        go($qp_back);
    }

    // A number added by staff: the same rules as the public page, any way.
    if (isset($_POST['submit_number_add'])) {
        $qp_join = pg_waiting_join_input($_POST, $qp_place, true, pg_waiting_takes_bookings($qp_place));
        $qp_taken = ($qp_join['error'] === '') ? pg_waiting_take($qp_place, $qp_join['values'], 'staff') : null;

        if ($qp_taken && $qp_taken['ok']) {
            $liveform->add_notice(h(lang(array('string' => 'Number {var:1} was given.', 'vars' => array($qp_taken['number'])))));
        } elseif ($qp_taken === null) {
            $liveform->add_error(h($qp_join['error']));
        } else {
            $liveform->add_error(h(($qp_taken['error'] === 'way') ? lang('Open the queue to add a number.') : pg_waiting_refusal_text($qp_taken['error'], $qp_place)));
        }

        go($qp_back);
    }

    // Staff: role-3 users who may open a desk here (queue_desk.php).
    if (isset($_POST['submit_staff_add']) || isset($_POST['submit_staff_remove'])) {
        $qp_staff = pg_waiting_place_staff($qp_place['settings']);
        $qp_staff = isset($_POST['submit_staff_add'])
            ? array_merge($qp_staff, array((int) ($_POST['staff_user_id'] ?? 0)))
            : array_diff($qp_staff, array((int) $_POST['submit_staff_remove']));
        pg_waiting_place_staff_save($qp_place, $qp_staff);
        go($qp_back);
    }

    if (isset($_POST['submit_desk_remove'])) {
        if (pg_waiting_desk_remove($qp_id, (int) $_POST['submit_desk_remove'])) {
            $liveform->add_notice(h(lang('The desk was removed.')));
        } else {
            $liveform->add_error(h(lang('An open desk cannot be removed: close it first.')));
        }

        go($qp_back);
    }

    $qp_input = pg_waiting_place_input($_POST, $qp_place, $qp_today);

    if (!$qp_input['errors']) {
        pg_waiting_place_save($qp_id, $qp_input['place']);
        log_activity('queue place "' . $qp_input['place']['name'] . '" was modified');
        $liveform->add_notice(h(lang('The place was saved.')));
        go($qp_back);
    }

    foreach ($qp_input['errors'] as $qp_error) {
        $liveform->add_error(h($qp_error));
    }
}

// A place is added in the list's drawer.
if ($qp_place === null) {
    go($qp_list . '?add=1');
}

// What the form shows: the posted values after a refused save, else the
// stored place.
$qp_values = ($qp_input !== null) ? ($qp_input['place'] + $qp_place) : $qp_place;
$qp_settings = $qp_values['settings'];
$qp_missing = pg_waiting_place_missing($qp_values, count($qp_desks));
$qp_not_set = array(lang('Not set'), 'pg-tone-amber');
$qp_switch = function ($name, $on, $label) {
    return '<div class="d-flex align-items-center gap-3 mb-3"><input type="checkbox" class="pg-switch" id="qp_' . $name . '" name="' . $name . '" value="1"' . ($on ? ' checked' : '') . '><label class="pg-text-2" for="qp_' . $name . '">' . h($label) . '</label></div>';
};
$qp_number = function ($name, $value, $label, $min, $max, $end = '') {
    return '<div class="col-12 md:col-6"><label class="pg-field-label" for="qp_' . $name . '">' . h($label) . '</label>'
        . '<span class="pg-input"><input type="number" id="qp_' . $name . '" name="' . $name . '" min="' . $min . '" max="' . $max . '" step="1" value="' . h((string) $value) . '">' . (($end !== '') ? '<span class="pg-input-end">' . h($end) . '</span>' : '') . '</span></div>';
};
$qp_remove = '<button type="button" class="pg-icon-button pg-icon-button-sm" data-pg-queue-remove aria-label="' . h(lang('Remove')) . '"><span class="bi bi-x-lg" aria-hidden="true"></span></button>';

// 1. Services: a row each, its letter, name and starting minutes.
$qp_service_row = function (array $service) use ($qp_remove) {
    return '<div class="pg-card-inner-row flex-wrap" data-pg-queue-row>'
        . '<input type="hidden" name="service_key[]" value="' . h($service['key'] ?? '') . '">'
        . '<input type="text" class="pg-input text-center flex-shrink-0" style="width:3.5rem" name="service_letter[]" maxlength="1" value="' . h($service['letter'] ?? '') . '" aria-label="' . h(lang('Letter')) . '" data-pg-queue-letter>'
        . '<input type="text" class="pg-input flex-grow-1" style="width:auto;min-width:10rem;flex-basis:10rem" name="service_name[]" maxlength="60" value="' . h($service['name'] ?? '') . '" aria-label="' . h(lang('Service')) . '" placeholder="' . h(lang('Service')) . '">'
        . '<span class="pg-input w-auto flex-shrink-0"><input type="number" min="1" max="240" step="1" name="service_minutes[]" value="' . h((string) ($service['minutes'] ?? 10)) . '" aria-label="' . h(lang('Minutes a person takes')) . '"><span class="pg-input-end">' . h(lang('min')) . '</span></span>'
        . $qp_remove . '</div>';
};
$qp_service_rows = '';

foreach ($qp_values['services'] as $service) {
    $qp_service_rows .= $qp_service_row($service);
}

$qp_services_html = '<p class="pg-field-help mb-3">' . h(lang('Each service\'s numbers start with its letter. The minutes start the estimate until 20 people are served.')) . '</p>'
    . '<div class="pg-card-inner mb-3" data-pg-queue-list="service">' . $qp_service_rows . '</div>'
    . '<template data-pg-queue-template="service">' . $qp_service_row(array()) . '</template>'
    . '<button type="button" class="pg-button pg-button-quiet pg-button-sm" data-pg-queue-add="service"><span class="bi bi-plus-lg" aria-hidden="true"></span>' . h(lang('Add a service')) . '</button>';
$qp_services_line = $qp_values['services']
    ? implode(', ', array_map(function ($service) {
        return $service['letter'] . ' ' . $service['name'];
    }, $qp_values['services']))
    : lang('No service yet.');

// 2. Hours: the week as on a Bookings staff member, then days off.
$qp_day_names = array(1 => lang('Monday'), 2 => lang('Tuesday'), 3 => lang('Wednesday'), 4 => lang('Thursday'), 5 => lang('Friday'), 6 => lang('Saturday'), 7 => lang('Sunday'));
$qp_time = function ($name, $value, $label) {
    // The picker draws its own box beside the field, as wide as the field's
    // parent: the span gives both their width.
    return '<span class="d-inline-block" style="width:7rem"><input type="time" data-pg-time="hh:mm" class="pg-input" name="' . $name . '" value="' . h($value) . '" aria-label="' . h($label) . '"></span>';
};
$qp_fields = pg_booking_settings_hours_fields($qp_values['hours']);
$qp_week = '';

foreach (pg_booking_settings_week_days(pg_list_period_week_first_day()) as $day) {
    $boxes = '';

    foreach (array(lang('Opens'), lang('Closes'), lang('Opens again'), lang('Closes again')) as $position => $label) {
        $boxes .= $qp_time('hours[h' . $day . 'abcd'[$position] . ']', $qp_fields[$day][$position], $qp_day_names[$day] . ', ' . $label);
    }

    $qp_week .= '<div class="pg-card-inner-row"><div class="row gy-2 flex-grow-1 align-items-center"><div class="col-12 md:col-2 pg-text-2">' . h($qp_day_names[$day]) . '</div><div class="col-12 md:col-10 d-flex flex-wrap gap-2">' . $boxes . '</div></div></div>';
}

$qp_exception_row = function (array $row) use ($qp_time, $qp_remove) {
    return '<div class="pg-card-inner-row flex-wrap" data-pg-queue-row>'
        . '<span class="d-inline-block" style="width:8rem"><input type="date" data-pg-date class="pg-input" name="x_from[]" value="' . h($row['from'] ?? '') . '" aria-label="' . h(lang('First day')) . '"></span>'
        . '<span class="d-inline-block" style="width:8rem"><input type="date" data-pg-date class="pg-input" name="x_to[]" value="' . h($row['to'] ?? '') . '" aria-label="' . h(lang('Last day')) . '"></span>'
        . $qp_time('x_open[]', $row['open'] ?? '', lang('Opens')) . $qp_time('x_close[]', $row['close'] ?? '', lang('Closes')) . $qp_remove . '</div>';
};
$qp_exception_rows = '';

foreach (pg_booking_settings_exception_rows($qp_values['exceptions'], $qp_today) as $row) {
    $qp_exception_rows .= $qp_exception_row($row);
}

$qp_hours_html = '<p class="pg-field-help mb-3">' . h(lang(array('string' => 'In the site\'s time zone, {var:1}. A day left empty is closed.', 'vars' => array(date_default_timezone_get())))) . '</p>'
    . '<div class="pg-card-inner mb-5">' . $qp_week . '</div>'
    . '<div class="pg-field-label">' . h(lang('Days off and special hours')) . '</div>'
    . '<div class="pg-card-inner mb-3" data-pg-queue-list="exception">' . $qp_exception_rows . '</div>'
    . '<template data-pg-queue-template="exception">' . $qp_exception_row(array()) . '</template>'
    . '<button type="button" class="pg-button pg-button-quiet pg-button-sm" data-pg-queue-add="exception"><span class="bi bi-plus-lg" aria-hidden="true"></span>' . h(lang('Add days')) . '</button>'
    . '<div class="pg-field-help mt-3">' . h(lang('A row is the first day, the last day (empty for one day) and the hours. Without hours the days are closed; with hours they replace the week\'s hours.')) . '</div>';
$qp_open_days = count($qp_values['hours']);
$qp_hours_line = $qp_open_days
    ? lang(array('string' => 'Open {var:1} days a week.', 'vars' => array($qp_open_days)))
    : lang('No hours yet: the queue takes no numbers.');

// 3. Taking a number: the ways, the party size, the cap, the closed text.
$qp_ways = $qp_settings['ways'];
$qp_way_words = array('qr' => lang('QR poster'), 'site' => lang('Site page'), 'kiosk' => lang('Kiosk'));
$qp_ways_on = array_values(array_intersect_key($qp_way_words, array_filter($qp_ways)));
$qp_taking_html = $qp_switch('way_qr', $qp_ways['qr'], lang('From the QR poster'))
    . $qp_switch('way_site', $qp_ways['site'], lang('From the site\'s queue page'))
    . $qp_switch('way_kiosk', $qp_ways['kiosk'], lang('From a kiosk tablet'))
    . $qp_switch('party_size', $qp_settings['party_size'], lang('Ask how many people come'))
    . '<div class="row gy-5 mt-0">' . $qp_number('cap', $qp_settings['cap'] ?: '', lang('Numbers a day at most'), 0, 9999)
    . '<div class="col-12"><label class="pg-field-label" for="qp_closed_text">' . h(lang('Text shown when closed')) . '</label><textarea class="pg-input" id="qp_closed_text" name="closed_text" rows="2" maxlength="500">' . h($qp_settings['closed_text']) . '</textarea></div></div>';
$qp_taking_line = $qp_ways_on
    ? implode(', ', $qp_ways_on) . ' · ' . ($qp_settings['cap'] ? lang(array('string' => 'at most {var:1} a day', 'vars' => array($qp_settings['cap']))) : lang('no daily cap'))
    : lang('No way to take a number is on.');

// 4. Notices: the channels, when to tell, the three texts as tabs.
$qp_notices = $qp_settings['notices'];
$qp_channel_words = array('sms' => lang('SMS'), 'whatsapp' => lang('WhatsApp'), 'email' => lang('E-mail'));
$qp_channels_on = array_values(array_intersect_key($qp_channel_words, array_filter(array_intersect_key($qp_notices, $qp_channel_words))));
$qp_text_tabs = '';
$qp_text_panes = '';

foreach (array('taken' => lang('Number taken'), 'ahead' => lang('Almost your turn'), 'called' => lang('Called')) as $key => $label) {
    $qp_text_tabs .= '<button type="button" class="pg-seg-item" aria-pressed="' . (($key === 'taken') ? 'true' : 'false') . '" data-pg-queue-tab="' . $key . '">' . h($label) . '</button>';
    $qp_text_panes .= '<div data-pg-queue-pane="' . $key . '"' . (($key === 'taken') ? '' : ' hidden') . '><textarea class="pg-input" name="notice_text[' . $key . ']" rows="3" maxlength="500" aria-label="' . h($label) . '">' . h($qp_notices['texts'][$key]) . '</textarea></div>';
}

$qp_notices_html = $qp_switch('notice_sms', $qp_notices['sms'], lang('SMS'))
    . $qp_switch('notice_whatsapp', $qp_notices['whatsapp'], lang('WhatsApp'))
    . $qp_switch('notice_email', $qp_notices['email'], lang('E-mail, when only an e-mail is given'))
    . '<div class="row gy-5 mt-0 mb-5">' . $qp_number('notice_ahead', $qp_notices['ahead'], lang('Tell when this many are ahead'), 1, 20) . '</div>'
    . '<span class="pg-seg mb-3" role="group" aria-label="' . h(lang('Message')) . '">' . $qp_text_tabs . '</span>' . $qp_text_panes
    . '<div class="pg-field-help mt-2">' . h(lang('Leave a text empty for the standard one. {number}, {desk} and {link} are filled in. At most three messages go for a number.')) . '</div>';
$qp_notices_line = $qp_channels_on
    ? lang(array('string' => '{var:1} · told when {var:2} are ahead', 'vars' => array(implode(', ', $qp_channels_on), $qp_notices['ahead'])))
    : lang('No messages are sent.');

// 5. Order: priority groups, the Bookings staff folded in, Not here.
$qp_group_row = function (array $group) use ($qp_remove) {
    return '<div class="pg-card-inner-row" data-pg-queue-row><input type="hidden" name="priority_key[]" value="' . h($group['key'] ?? '') . '">'
        . '<input type="text" class="pg-input flex-grow-1" name="priority_name[]" maxlength="60" value="' . h($group['name'] ?? '') . '" aria-label="' . h(lang('Priority group')) . '" placeholder="' . h(lang('Priority group')) . '">' . $qp_remove . '</div>';
};
$qp_group_rows = '';

foreach ($qp_settings['priority'] as $group) {
    $qp_group_rows .= $qp_group_row($group);
}

$qp_staff_boxes = '';

foreach ((pg_booking_ready() ? pg_booking_agenda_staff() : array()) as $staff_id => $member) {
    $qp_staff_boxes .= '<span class="d-inline-flex align-items-center gap-2 me-4"><input type="checkbox" class="pg-checkbox" id="qp_staff_' . (int) $staff_id . '" name="booking_staff[]" value="' . (int) $staff_id . '"' . (in_array((int) $staff_id, $qp_settings['booking_staff'], true) ? ' checked' : '') . '><label class="pg-text-2" for="qp_staff_' . (int) $staff_id . '">' . h($member['name']) . '</label></span>';
}

$qp_order_html = '<div class="pg-field-label">' . h(lang('Priority groups')) . '</div><p class="pg-field-help mb-3">' . h(lang('A person in one goes first; the desk sees the group.')) . '</p>'
    . '<div class="pg-card-inner mb-3" data-pg-queue-list="group">' . $qp_group_rows . '</div>'
    . '<template data-pg-queue-template="group">' . $qp_group_row(array()) . '</template>'
    . '<button type="button" class="pg-button pg-button-quiet pg-button-sm mb-5" data-pg-queue-add="group"><span class="bi bi-plus-lg" aria-hidden="true"></span>' . h(lang('Add a group')) . '</button>'
    . (($qp_staff_boxes !== '') ? '<div class="pg-field-label">' . h(lang('Bookings with these staff join the queue')) . '</div><div class="mb-3">' . $qp_staff_boxes . '</div>' : '')
    . '<div class="row gy-5 mt-0 mb-4">' . $qp_number('grace_minutes', $qp_settings['grace_minutes'], lang('A booking keeps its time when late by'), 0, 60, lang('min'))
    . $qp_number('not_here_minutes', $qp_settings['not_here_minutes'], lang('Not here after'), 1, 60, lang('min')) . '</div>'
    . $qp_switch('move_back', $qp_settings['move_back'], lang('Move a person who is not here back once'));
$qp_order_line = lang(array('string' => '{var:1} priority groups · Not here after {var:2} min', 'vars' => array(count($qp_settings['priority']), $qp_settings['not_here_minutes'])));

// 6. Keep personal data.
$qp_keep_html = '<div class="row gy-5">' . $qp_number('keep_days', $qp_settings['keep_days'], lang('Keep names, phones and e-mails for'), PG_WAITING_KEEP_MIN, PG_WAITING_KEEP_MAX, lang('days')) . '</div>'
    . '<div class="pg-field-help mt-2">' . h(lang('Then they are cleared; the counts and times stay for the report.')) . '</div>';

$qp_setup_html = '<form id="qp_form" action="edit_queue_place.php" method="post">
        ' . get_token_field() . '
        <input type="hidden" name="id" value="' . $qp_id . '">
        <div class="row gy-5 mb-5">
            <div class="col-12 md:col-6"><label class="pg-field-label" for="qp_name">' . h(lang('Name')) . '</label><input type="text" class="pg-input" id="qp_name" name="name" maxlength="150" required value="' . h($qp_values['name']) . '"></div>
            <div class="col-12 md:col-6"><label class="pg-field-label" for="qp_address">' . h(lang('Address')) . '</label><input type="text" class="pg-input" id="qp_address" name="address" maxlength="255" value="' . h($qp_values['address']) . '"></div>
        </div>
        ' . pg_settings_fold(array(
            array('key' => 'services', 'name' => lang('Services'), 'icon' => 'bi-ui-checks', 'tone' => '', 'line' => $qp_services_line, 'pill' => in_array('services', $qp_missing, true) ? $qp_not_set : null, 'attention' => in_array('services', $qp_missing, true), 'html' => $qp_services_html),
            array('key' => 'hours', 'name' => lang('Hours'), 'icon' => 'bi-clock', 'tone' => '', 'line' => $qp_hours_line, 'pill' => in_array('hours', $qp_missing, true) ? $qp_not_set : null, 'attention' => in_array('hours', $qp_missing, true), 'html' => $qp_hours_html),
            array('key' => 'taking', 'name' => lang('Taking a number'), 'icon' => 'bi-qr-code', 'tone' => '', 'line' => $qp_taking_line, 'pill' => in_array('ways', $qp_missing, true) ? $qp_not_set : null, 'attention' => in_array('ways', $qp_missing, true), 'html' => $qp_taking_html),
            array('key' => 'notices', 'name' => lang('Messages'), 'icon' => 'bi-chat-dots', 'tone' => '', 'line' => $qp_notices_line, 'pill' => null, 'attention' => false, 'html' => $qp_notices_html),
            array('key' => 'order', 'name' => lang('Calling order'), 'icon' => 'bi-sort-down', 'tone' => '', 'line' => $qp_order_line, 'pill' => null, 'attention' => false, 'html' => $qp_order_html),
            array('key' => 'keep', 'name' => lang('Keep personal data'), 'icon' => 'bi-shield-lock', 'tone' => '', 'line' => lang(array('string' => 'Cleared after {var:1} days.', 'vars' => array($qp_settings['keep_days']))), 'pill' => null, 'attention' => false, 'html' => $qp_keep_html),
        )) . '
        <div class="pg-floatbar d-flex flex-wrap align-items-center gap-2 p-3 my-5">
            <button type="submit" name="submit_save" value="1" class="pg-button pg-button-fill">' . h(lang('Save')) . '</button><a class="pg-button pg-button-quiet" href="' . h($qp_list) . '">' . h(lang('Cancel')) . '</a>
        </div>
    </form>';

// The Desks tab: a desk a row, Add desk in a drawer, Remove in the row.
$qp_service_names = array_column($qp_place['services'], 'name', 'key');
$qp_desk_rows = '';

foreach ($qp_desks as $desk) {
    $names = array_values(array_intersect_key($qp_service_names, array_flip($desk['services'])));
    $state = ($desk['state'] === 'open') ? lang(array('string' => 'Open · {var:1}', 'vars' => array($desk['person']))) : (($desk['state'] === 'paused') ? lang('Paused') : lang('Closed'));
    $qp_desk_rows .= '<div class="pg-card-inner-row flex-wrap">'
        . '<span class="pg-icon-tile" aria-hidden="true"><span class="bi bi-person-workspace"></span></span>'
        . '<div class="flex-grow-1 min-width-0"><strong class="pg-list-row-title">' . h($desk['name']) . '</strong><div class="pg-text-meta">' . h(implode(', ', $names)) . '</div></div>'
        . '<span class="pg-text-2 text-nowrap">' . h($state) . '</span>'
        . '<button type="submit" form="qp_desks_form" name="submit_desk_remove" value="' . (int) $desk['id'] . '" class="pg-button pg-button-danger pg-button-sm"' . (($desk['state'] === 'open') ? ' disabled' : '') . ' data-confirm-content="' . h(lang(array('string' => 'The desk "{var:1}" is removed. Numbers it served stay in the figures.', 'vars' => array($desk['name'])))) . '">' . h(lang('Remove')) . '</button>'
        . '</div>';
}

$qp_desk_boxes = '';

foreach ($qp_place['services'] as $service) {
    $qp_desk_boxes .= '<div class="d-flex align-items-center gap-2 mb-2"><input type="checkbox" class="pg-checkbox" id="qp_desk_' . h($service['key']) . '" name="desk_services[]" value="' . h($service['key']) . '" checked><label class="pg-text-2" for="qp_desk_' . h($service['key']) . '">' . h($service['letter'] . ' ' . $service['name']) . '</label></div>';
}

// Staff: users with the User role who may open a desk here and call
// people; managers and above always may.
$qp_staff_rows = '';

foreach (pg_waiting_place_staff($qp_place['settings']) as $staff_id) {
    $qp_staff_rows .= '<div class="pg-card-inner-row">'
        . '<span class="pg-icon-tile" aria-hidden="true"><span class="bi bi-person"></span></span>'
        . '<span class="pg-text-2 flex-grow-1 min-width-0">' . h(pg_user_picker_label($staff_id)) . '</span>'
        . '<button type="submit" form="qp_desks_form" name="submit_staff_remove" value="' . $staff_id . '" class="pg-button pg-button-quiet pg-button-sm">' . h(lang('Remove')) . '</button></div>';
}

$qp_staff_html = '<section class="pg-card p-5 mb-5" aria-labelledby="qp_staff_title"><h2 class="pg-title-section mb-0" id="qp_staff_title">' . h(lang('Staff')) . '</h2>'
    . '<p class="pg-card-sub mb-5">' . h(lang('Users with the User role who may open a desk here and call people; they find it as My desk in the menu. Managers and above always may.')) . '</p>'
    . (($qp_staff_rows !== '') ? '<div class="pg-card-inner mb-5">' . $qp_staff_rows . '</div>' : '')
    . '<div class="d-flex flex-wrap align-items-end gap-3"><div class="flex-grow-1" style="min-width:14rem;max-width:24rem"><label class="pg-field-label" for="qp_staff_user">' . h(lang('User')) . '</label>'
    . '<select class="pg-input" id="qp_staff_user" name="staff_user_id" form="qp_desks_form" data-pg-combobox data-pg-source="user_search_action.php" data-pg-min-length="2" data-placeholder="' . h(lang('Search for a user')) . '"><option value=""></option></select></div>'
    . '<button type="submit" form="qp_desks_form" name="submit_staff_add" value="1" class="pg-button pg-button-quiet"><i class="bi bi-person-plus" aria-hidden="true"></i>' . h(lang('Add staff')) . '</button></div></section>';

$qp_desks_html = '<form id="qp_desks_form" action="edit_queue_place.php" method="post">' . get_token_field() . '<input type="hidden" name="id" value="' . $qp_id . '"><input type="hidden" name="view" value="desks"></form>'
    . '<section class="pg-card p-5 mb-5" aria-labelledby="qp_desks_title"><h2 class="pg-title-section mb-0" id="qp_desks_title">' . h(lang('Desks')) . '</h2>'
    . '<p class="pg-card-sub mb-5">' . h($qp_desks ? lang('Staff open a desk for their shift; only an open desk is called to.') : lang('No desks yet. Add the desks people are called to.')) . '</p>'
    . ($qp_desks ? '<div class="pg-card-inner">' . $qp_desk_rows . '</div>' : '') . '</section>'
    . $qp_staff_html
    . '<dialog class="drawer drawer-end" id="qp_desk_add" aria-labelledby="qp_desk_add_title" style="width:min(480px,100%);--pg-drawer-height:70vh"' . (isset($_GET['add']) ? ' data-open="1"' : '') . '>
        <form method="post" action="edit_queue_place.php" class="d-flex flex-column h-100">
            ' . get_token_field() . '<input type="hidden" name="id" value="' . $qp_id . '"><input type="hidden" name="view" value="desks">
            <div class="drawer-header">
                <div class="flex-grow-1 min-width-0">
                    <h5 class="drawer-title mb-0" id="qp_desk_add_title">' . h(lang('Add desk')) . '</h5>
                    <p class="small fg-2 mb-0 mt-1">' . h(lang('A counter, a room or a chair people are called to.')) . '</p>
                </div>
                <button type="button" class="btn-close ms-auto" data-bs-dismiss="drawer" aria-label="' . h(lang('Close')) . '"></button>
            </div>
            <div class="drawer-body flex-grow-1 overflow-auto">
                <div class="form-field mb-5"><label class="form-label pg-field-label" for="qp_desk_name">' . h(lang('Name')) . '</label><input type="text" class="pg-input" id="qp_desk_name" name="desk_name" maxlength="100" required placeholder="' . h(lang('Desk 1')) . '"></div>
                <div class="pg-field-label">' . h(lang('Services it serves')) . '</div>' . $qp_desk_boxes . '
            </div>
            <div class="drawer-footer border-top p-5 d-flex align-items-center gap-3"><span class="flex-grow-1"></span>
                <button type="button" class="pg-button pg-button-quiet" data-bs-dismiss="drawer">' . h(lang('Cancel')) . '</button>
                <button type="submit" name="submit_desk_add" value="1" class="pg-button pg-button-fill">' . h(lang('Add')) . '</button>
            </div>
        </form>
    </dialog>';

// Add a number: the service, then the person's details, all optional.
$qp_number_boxes = '';

foreach ($qp_place['services'] as $service) {
    $qp_number_boxes .= '<option value="' . h($service['key']) . '">' . h($service['letter'] . ' ' . $service['name']) . '</option>';
}

$qp_number_priority = '<option value="">' . h(lang('No priority')) . '</option>';

foreach ($qp_place['settings']['priority'] as $group) {
    $qp_number_priority .= '<option value="' . h($group['key']) . '">' . h($group['name']) . '</option>';
}

$qp_number_field = function ($name, $type, $label, $extra = '') {
    return '<div class="form-field mb-5"><label class="form-label pg-field-label" for="qp_number_' . $name . '">' . h($label) . '</label><input type="' . $type . '" class="pg-input" id="qp_number_' . $name . '" name="' . $name . '"' . $extra . '></div>';
};
$qp_number_html = '<dialog class="drawer drawer-end" id="qp_number_add" aria-labelledby="qp_number_add_title" style="width:min(480px,100%);--pg-drawer-height:80vh">
        <form method="post" action="edit_queue_place.php" class="d-flex flex-column h-100">
            ' . get_token_field() . '<input type="hidden" name="id" value="' . $qp_id . '"><input type="hidden" name="view" value="' . $qp_view . '">
            <div class="drawer-header">
                <div class="flex-grow-1 min-width-0">
                    <h5 class="drawer-title mb-0" id="qp_number_add_title">' . h(lang('Add a number')) . '</h5>
                    <p class="small fg-2 mb-0 mt-1">' . h(lang('For a person at the counter. With a phone they get the messages.')) . '</p>
                </div>
                <button type="button" class="btn-close ms-auto" data-bs-dismiss="drawer" aria-label="' . h(lang('Close')) . '"></button>
            </div>
            <div class="drawer-body flex-grow-1 overflow-auto">
                <div class="form-field mb-5"><label class="form-label pg-field-label" for="qp_number_service">' . h(lang('Service')) . '</label><select class="pg-input" id="qp_number_service" name="service">' . $qp_number_boxes . '</select></div>'
                . ($qp_place['settings']['party_size'] ? $qp_number_field('party_size', 'number', lang('How many people come'), ' min="1" max="' . PG_WAITING_PARTY_MAX . '" step="1" value="1"') : '')
                . $qp_number_field('name', 'text', lang('Name (optional)'), ' maxlength="100"')
                . $qp_number_field('phone', 'tel', lang('Phone (optional)'), ' maxlength="40"')
                . $qp_number_field('email', 'email', lang('E-mail (optional)'), ' maxlength="190"')
                . ($qp_place['settings']['priority'] ? '<div class="form-field mb-5"><label class="form-label pg-field-label" for="qp_number_priority">' . h(lang('Priority')) . '</label><select class="pg-input" id="qp_number_priority" name="priority">' . $qp_number_priority . '</select></div>' : '')
                . (pg_waiting_takes_bookings($qp_place) ? '<div class="d-flex align-items-start gap-3 mb-5"><input type="checkbox" class="pg-switch" id="qp_number_booking" name="booking" value="1" aria-describedby="qp_number_booking_help"><div><label class="pg-text-2" for="qp_number_booking">' . h(lang('Has a booking today')) . '</label><p class="pg-field-help mb-0" id="qp_number_booking_help">' . h(lang('Found by the phone or e-mail above, among the bookings of the staff this place folds in.')) . '</p></div></div>' : '') . '
            </div>
            <div class="drawer-footer border-top p-5 d-flex align-items-center gap-3"><span class="flex-grow-1"></span>
                <button type="button" class="pg-button pg-button-quiet" data-bs-dismiss="drawer">' . h(lang('Cancel')) . '</button>
                <button type="submit" name="submit_number_add" value="1" class="pg-button pg-button-fill">' . h(lang('Add')) . '</button>
            </div>
        </form>
    </dialog>';

// The head: the state and the button that follows it, quiet beside the
// tab's own fill (Save, Add desk).
$qp_state_button = ($qp_place['status'] === 'closed')
    ? '<button type="submit" form="qp_state_form" name="submit_open" value="1" class="pg-button pg-button-quiet"><i class="bi bi-play-circle" aria-hidden="true"></i>' . h(lang('Open the queue')) . '</button>'
    : '<button type="submit" form="qp_state_form" name="submit_close" value="1" class="pg-button pg-button-quiet" data-confirm-content="' . h(lang('No new numbers are given. People already waiting are still called.')) . '"><i class="bi bi-stop-circle" aria-hidden="true"></i>' . h(lang('Close the queue')) . '</button>';

if ($qp_place['status'] === 'paused') {
    $qp_state_button .= '<button type="submit" form="qp_state_form" name="submit_open" value="1" class="pg-button pg-button-quiet"><i class="bi bi-play-circle" aria-hidden="true"></i>' . h(lang('Open the queue')) . '</button>';
}

$qp_tab = function ($view, $label, $count = null) use ($qp_id, $qp_view) {
    return '<a class="pg-seg-item' . (($qp_view === $view) ? ' is-active" aria-current="page' : '') . '" href="edit_queue_place.php?id=' . $qp_id . (($view === 'setup') ? '' : '&amp;view=' . $view) . '">' . h($label) . (($count !== null) ? ' <span class="pg-seg-count">' . (int) $count . '</span>' : '') . '</a>';
};

echo pg_page_shell(array(
    'title' => $qp_place['name'],
    'extra classes' => 'calendar pg-measure-edit',
    'icon' => 'calendar',
    'help' => 'calendars',
    // output_header() prints the heading as markup; the name is staff text.
    'heading' => h($qp_place['name']) . ' ' . pg_waiting_place_pill($qp_place['status']),
    'heading_description' => ($qp_place['address'] !== '') ? $qp_place['address'] : lang('Where people take a number and wait to be called.'),
    'breadcrumb' => array(
        array('label' => lang('Queue'), 'url' => $qp_list),
        array('label' => $qp_place['name']),
    ),
)) . '
<main id="content" class="container-fluid">
    ' . $liveform->get_messages() . '
    ' . pg_screen_toolbar(array(
        'primary' => ($qp_view === 'desks')
            ? array('<button type="button" class="pg-button pg-button-fill" data-pg-queue-drawer="qp_desk_add"' . ($qp_place['services'] ? '' : ' disabled') . '><i class="bi bi-plus-circle" aria-hidden="true"></i>' . h(lang('Add desk')) . '</button>', $qp_state_button)
            : $qp_state_button,
        'view' => '<span class="pg-seg" role="group" aria-label="' . h(lang('Place')) . '">' . $qp_tab('setup', lang('Set-up')) . $qp_tab('desks', lang('Desks'), count($qp_desks)) . '<a class="pg-seg-item" href="queue_desk.php?place=' . $qp_id . '">' . h(lang('Today')) . '</a></span>',
        'secondary' => '<button type="button" class="pg-button pg-button-ghost" data-pg-queue-drawer="qp_number_add"' . (($qp_place['status'] === 'open') ? '' : ' disabled title="' . h(lang('Open the queue to add a number.')) . '"') . '><i class="bi bi-person-plus" aria-hidden="true"></i>' . h(lang('Add a number')) . '</button>'
            . '<a class="pg-button pg-button-ghost" href="queue_poster.php?id=' . $qp_id . '" target="_blank" rel="noopener"><i class="bi bi-qr-code" aria-hidden="true"></i>' . h(lang('Print the QR poster')) . '</a>',
    )) . '
    <form id="qp_state_form" action="edit_queue_place.php" method="post">' . get_token_field() . '<input type="hidden" name="id" value="' . $qp_id . '"><input type="hidden" name="view" value="' . $qp_view . '"></form>
    ' . (($qp_view === 'desks') ? $qp_desks_html : $qp_setup_html) . $qp_number_html . '
</main>
' . get_date_picker_format() . pg_fields_script() . '
<script src="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/assets/js/queue_place.js?v=' . filemtime(PG_FUNCTIONS_DIR . '/assets/js/queue_place.js') . '"></script>
' . output_footer();

$liveform->remove_form();
