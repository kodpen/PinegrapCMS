<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * One appointment booking of the staff agenda. Without an id it makes a
 * booking for a customer: the service, the staff member and the day are
 * chosen first, then one of the day's free times the slot engine offers
 * (the staff member's hours, their other bookings and their Workspace
 * calendar) and the customer's details; the INSERT is the same as a
 * visitor's (pg_booking_confirm()), so a slot taken meanwhile is refused.
 * With an id it shows the booking and changes its status, its customer
 * details and notes; a cancel keeps the reason in the notes and frees the
 * slot. Every change writes an audit trail line (type booking).
 *
 * Roles 0-2 work on every staff member's bookings; a role-3 staff member on
 * their own (includes/fn/booking_agenda.php).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');

require_once(PG_FUNCTIONS_DIR . '/includes/fn/booking_agenda.php');

$user = validate_user();

if (!pg_booking_ready()) {
    output_error(lang('The database has not been upgraded yet. Run the software update first.'));
}

$booking_scope = pg_booking_agenda_user_scope($user);

if ($booking_scope === false) {
    log_activity('access denied to bookings', 'ACCESS DENIED');
    output_error(lang('Access denied'), 403);
}

include_once('liveform.class.php');
$liveform = new liveform('edit_booking');

$booking_staff = pg_booking_agenda_staff();
$booking_services = pg_booking_agenda_services();
$booking_now = time();
$booking_id = is_scalar($_REQUEST['id'] ?? null) ? (int) $_REQUEST['id'] : 0;
$booking = array();

if ($booking_id > 0) {
    $booking = db_item("SELECT * FROM bookings WHERE id = '" . $booking_id . "'" . pg_booking_agenda_scope_sql($booking_scope));

    if (!$booking) {
        output_error(lang('Record not found.') . ' <a href="view_bookings.php">' . h(lang('Go back')) . '</a>.', 404);
    }
}

// The staff members a new booking may be made for: the enabled ones the
// viewer sees.
$booking_offered_staff = array();

foreach ($booking_staff as $booking_member) {
    if ($booking_member['enabled'] && (($booking_scope === null) || ($booking_member['id'] === $booking_scope))) {
        $booking_offered_staff[$booking_member['id']] = $booking_member;
    }
}

$booking_offered_services = array();

foreach ($booking_services as $booking_service_id => $booking_service) {
    if ((int) $booking_service['enabled'] === 1) {
        $booking_offered_services[$booking_service_id] = $booking_service;
    }
}

$booking_zone = date_default_timezone_get();

// ---------------------------------------------------------------------------
// The submitted forms.
// ---------------------------------------------------------------------------

if ($_POST) {
    validate_token_field();
    $liveform->add_fields_to_session();

    // A new booking for a customer.
    if ($booking_id === 0) {
        $fields = array(
            'service_id' => (int) $liveform->get_field_value('service_id'),
            'staff_id'   => (int) $liveform->get_field_value('staff_id'),
            'start_at'   => (int) $liveform->get_field_value('start_at'),
            'name'       => trim((string) $liveform->get_field_value('name')),
            'email'      => trim((string) $liveform->get_field_value('email')),
            'phone'      => trim((string) $liveform->get_field_value('phone')),
            'notes'      => trim((string) $liveform->get_field_value('notes')),
        );
        $day = (string) $liveform->get_field_value('day');
        $back = 'edit_booking.php?' . http_build_query(array('service_id' => $fields['service_id'], 'staff_id' => $fields['staff_id'], 'date' => $day));

        foreach (pg_booking_agenda_check($fields, $booking_offered_services, $booking_offered_staff) as $field => $message) {
            $liveform->mark_error($field, $message);
        }

        // The time must still be one the slot engine offers for that day.
        if (!$liveform->check_form_errors()
            && !in_array($fields['start_at'], pg_booking_agenda_free_slots($booking_offered_services[$fields['service_id']], $booking_offered_staff[$fields['staff_id']], $day, $booking_now), true)) {
            $liveform->mark_error('start_at', lang('This time has just been taken. Choose another one.'));
        }

        if ($liveform->check_form_errors()) {
            go($back);
        }

        $status = ($liveform->get_field_value('status') === 'requested') ? 'requested' : 'confirmed';

        $written = pg_booking_confirm(array(
            'service'    => $booking_offered_services[$fields['service_id']],
            'service_id' => $fields['service_id'],
            'staff_id'   => $fields['staff_id'],
            'start_at'   => $fields['start_at'],
            'name'       => $fields['name'],
            'email'      => $fields['email'],
            'phone'      => $fields['phone'],
            'notes'      => $fields['notes'],
        ), $status, $booking_now, 'panel');

        if ($written['answer'] === 'taken') {
            $liveform->mark_error('start_at', lang('This time has just been taken. Choose another one.'));
            go($back);
        }

        if ($written['answer'] !== 'ok') {
            $liveform->add_error(lang('The booking could not be saved.'));
            go($back);
        }

        pg_booking_agenda_audit('create', $written['id'], array(), (array) db_item("SELECT * FROM bookings WHERE id = '" . (int) $written['id'] . "'"));
        log_activity('booking #' . (int) $written['id'] . ' was created', 'ACTIVITY');

        $liveform->remove_form();
        $notice = new liveform('edit_booking');
        $notice->add_notice(lang('The booking has been created.'));
        go('edit_booking.php?id=' . (int) $written['id']);
    }

    // A change to a booking.
    $back = 'edit_booking.php?id=' . $booking_id;
    // A booking in a final status has no status field: it keeps its own.
    $status = (string) $liveform->get_field_value('status');
    $status = ($status === '') ? (string) $booking['status'] : $status;
    $name = trim((string) $liveform->get_field_value('name'));
    $email = trim((string) $liveform->get_field_value('email'));
    $phone = trim((string) $liveform->get_field_value('phone'));
    $notes = rtrim((string) $liveform->get_field_value('notes'));

    if (($status !== $booking['status']) && !in_array($status, pg_booking_agenda_moves_now($booking, $booking_now), true)) {
        $liveform->mark_error('status', lang('This status cannot be chosen for the booking.'));
    }

    if ($name === '') {
        $liveform->mark_error('name', lang('Enter the customer\'s name.'));
    }

    // The same rule as a new booking: a valid address, and an address or a
    // phone to reach the customer.
    $contact_error = pg_booking_agenda_contact_error($email, $phone);

    if ($contact_error !== '') {
        $liveform->mark_error('email', $contact_error);
    }

    if ($liveform->check_form_errors()) {
        go($back);
    }

    $customer = pg_booking_customer(array('user_id' => $booking['user_id'], 'name' => $name, 'email' => $email, 'phone' => $phone, 'notes' => $notes));
    $cancel = ($status === 'cancelled') && ($booking['status'] !== 'cancelled');

    if ($cancel) {
        $customer['notes'] = pg_booking_cancel_notes($customer['notes'], (string) $liveform->get_field_value('cancel_reason'), lang('Cancelled'));
    }

    // A live hold confirmed or requested here goes through the visitor's
    // confirm, which also stores the manage link's token.
    if (($booking['status'] === 'held') && in_array($status, array('requested', 'confirmed'), true)) {
        $written = pg_booking_confirm(array('id' => $booking_id) + $customer, $status, $booking_now, 'panel');

        if ($written['answer'] !== 'ok') {
            $liveform->add_error(lang('The booking was changed meanwhile. Check it and save again.'));
            go($back);
        }

        $after = (array) db_item("SELECT * FROM bookings WHERE id = '" . $booking_id . "'");
        pg_booking_agenda_audit('update', $booking_id, $booking, $after);
        log_activity('booking #' . $booking_id . ' was modified', 'ACTIVITY');

        $liveform->remove_form();
        $notice = new liveform('edit_booking');
        $notice->add_notice(lang('The booking has been saved.'));
        go($back);
    }

    // The status it moves from is part of the write, so two screens cannot
    // both move it.
    if (!pg_booking_agenda_status_write($booking, $status, $customer, $booking_now)) {
        $liveform->add_error(lang('The booking was changed meanwhile. Check it and save again.'));
        go($back);
    }

    $after = (array) db_item("SELECT * FROM bookings WHERE id = '" . $booking_id . "'");
    pg_booking_agenda_audit($cancel ? 'cancel' : 'update', $booking_id, $booking, $after);
    log_activity('booking #' . $booking_id . ' was ' . ($cancel ? 'cancelled' : 'modified'), 'ACTIVITY');

    // A request confirmed or a booking cancelled here tells the customer and
    // announces booking.confirmed / booking.cancelled.
    $status_event = pg_booking_status_event((string) $booking['status'], $status);

    if ($status_event !== '') {
        pg_booking_changed($booking_id, $status_event);
    }

    $liveform->remove_form();
    $notice = new liveform('edit_booking');
    $notice->add_notice($cancel ? lang('The booking has been cancelled.') : lang('The booking has been saved.'));
    go($back);
}

// ---------------------------------------------------------------------------
// The screen.
// ---------------------------------------------------------------------------

$booking_breadcrumb = array(array('label' => lang('Bookings'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_bookings.php'));

$booking_save_bar = function ($label) {
    return '
            <div class="col-12 order-3 position-sticky" style="bottom:0; z-index:3;">
                <nav class="buttons navigation text-center mb-7" aria-label="' . h(lang('data edit buttons')) . '">
                    <div class="container">
                        <div class="btn-group flex-wrap justify-content-center">
                            <button type="submit" name="submit_save" value="Save" class="btn my-1 btn-solid theme-success"><i class="bi bi-check-lg me-2" aria-hidden="true"></i><span class="pg-btn-label">' . h($label) . '</span></button>
                        </div>
                    </div>
                </nav>
            </div>';
};

$booking_field = function ($name, $label, $type = 'text', $extra = array()) use ($liveform) {
    return '
                        <div class="mb-5">
                            <label for="' . h($name) . '" class="form-label">' . h($label) . '</label>
                            ' . $liveform->output_field(array_merge(array('type' => $type, 'id' => $name, 'name' => $name, 'class' => 'form-control'), $extra)) . '
                        </div>';
};

if ($booking_id === 0) {

    // The choice of service, staff member and day (a GET form: each change
    // draws the day's free times again).
    $choice_service = (int) ($_GET['service_id'] ?? 0);
    $choice_staff = ($booking_scope !== null) ? (int) $booking_scope : (int) ($_GET['staff_id'] ?? 0);
    // The agenda links with an ISO date; the picker sends the site's order.
    $choice_day = (isset($_GET['date']) && is_string($_GET['date'])) ? trim($_GET['date']) : '';

    if (!pg_booking_date_valid($choice_day)) {
        $choice_day = pg_date_input_iso($choice_day);
    }

    if (!pg_booking_date_valid($choice_day)) {
        $choice_day = date('Y-m-d', $booking_now);
    }

    // One staff member or one service only: chosen already.
    if (!isset($booking_offered_staff[$choice_staff]) && (count($booking_offered_staff) === 1)) {
        $choice_staff = (int) key($booking_offered_staff);
    }

    $choice_staff_services = isset($booking_offered_staff[$choice_staff]) ? array_intersect_key($booking_offered_services, array_flip($booking_offered_staff[$choice_staff]['service_ids'])) : $booking_offered_services;

    if (!isset($choice_staff_services[$choice_service]) && (count($choice_staff_services) === 1)) {
        $choice_service = (int) key($choice_staff_services);
    }

    $staff_options = '<option value="">' . h(lang('Choose a staff member')) . '</option>';

    foreach ($booking_offered_staff as $booking_member) {
        $staff_options .= '<option value="' . (int) $booking_member['id'] . '"' . (($booking_member['id'] === $choice_staff) ? ' selected' : '') . '>' . h($booking_member['name']) . '</option>';
    }

    $service_options = '<option value="">' . h(lang('Choose a service')) . '</option>';

    foreach ($choice_staff_services as $booking_service_id => $booking_service) {
        $service_options .= '<option value="' . (int) $booking_service_id . '"' . (($booking_service_id === $choice_service) ? ' selected' : '') . '>' . h($booking_service['name']) . ' (' . h(lang(array('string' => '{var:1} min', 'vars' => array((int) $booking_service['length_minutes'])))) . ')</option>';
    }

    $slots = (isset($booking_offered_staff[$choice_staff]) && isset($choice_staff_services[$choice_service]))
        ? pg_booking_agenda_free_slots($choice_staff_services[$choice_service], $booking_offered_staff[$choice_staff], $choice_day, $booking_now)
        : null;

    $choice_card = '
                <form action="edit_booking.php" method="get" id="booking_choice" class="card mb-7">
                    <div class="card-header"><h2 class="h6 mb-0">' . h(lang('Service and time')) . '</h2></div>
                    <div class="card-body">
                        <div class="row g-3 align-items-end">
                            <div class="col-12 md:col-4">
                                <label for="choice_staff" class="form-label">' . h(lang('Staff')) . '</label>
                                <select class="form-control" id="choice_staff" name="staff_id"' . (($booking_scope !== null) ? ' disabled' : '') . '>' . $staff_options . '</select>
                            </div>
                            <div class="col-12 md:col-4">
                                <label for="choice_service" class="form-label">' . h(lang('Service')) . '</label>
                                <select class="form-control" id="choice_service" name="service_id">' . $service_options . '</select>
                            </div>
                            <div class="col-12 md:col-4">
                                <label for="choice_date" class="form-label">' . h(lang('Date')) . '</label>
                                <input type="text" class="form-control" id="choice_date" name="date" value="' . h(prepare_form_data_for_output($choice_day, 'date', false)) . '" autocomplete="off" data-pg-date>
                            </div>
                        </div>
                        <noscript><button type="submit" class="btn btn-sm btn-solid theme-primary rounded-pill px-5 mt-5">' . h(lang('Show free times')) . '</button></noscript>
                    </div>
                </form>
                <script>
                    document.getElementById("booking_choice").addEventListener("change", function () {
                        // The reload draws the day again; the customer details
                        // typed so far wait in this tab and are put back.
                        var details = document.getElementById("booking_details"), draft = {};

                        if (details) {
                            ["name", "email", "phone", "notes", "status"].forEach(function (name) {
                                if (details.elements[name]) {
                                    draft[name] = details.elements[name].value;
                                }
                            });

                            try {
                                sessionStorage.setItem("pg_booking_draft", JSON.stringify(draft));
                            } catch (e) {}
                        }

                        this.submit();
                    });
                </script>';

    // Puts back the details the choice card's reload kept (read once).
    $draft_script = '
    <script>
        (function () {
            var details = document.getElementById("booking_details"), draft = null;

            try {
                draft = JSON.parse(sessionStorage.getItem("pg_booking_draft") || "null");
                sessionStorage.removeItem("pg_booking_draft");
            } catch (e) {}

            if (!details || !draft || (typeof draft !== "object")) {
                return;
            }

            Object.keys(draft).forEach(function (name) {
                var field = details.elements[name];

                if (field && (typeof draft[name] === "string") && ((field.value === "") || (name === "status"))) {
                    field.value = draft[name];
                }
            });
        })();
    </script>';

    if ($slots === null) {
        $slot_field = '<p class="fg-secondary mb-5">' . h(lang('Choose a staff member and a service to see the free times.')) . '</p>';
    } elseif (!$slots) {
        $slot_field = '<p class="fg-secondary mb-5">' . h(lang('No free time on this day. Choose another day.')) . '</p>';
    } else {
        $slot_options = array(h(lang('Choose a time')) => '');

        foreach ($slots as $slot) {
            $slot_options[h(pg_booking_agenda_time($slot) . ' – ' . pg_booking_agenda_time($slot + ((int) $choice_staff_services[$choice_service]['length_minutes'] * 60)))] = (string) $slot;
        }

        $slot_field = $booking_field('start_at', lang('Time'), 'select', array('options' => $slot_options, 'required' => 'required'));
    }

    if (!$liveform->field_in_session('status')) {
        $liveform->assign_field_value('status', 'confirmed');
    }

    $status_options = array(h(lang('Confirmed')) => 'confirmed', h(lang('Requested')) => 'requested');

    $no_staff = !$booking_offered_staff || !$booking_offered_services;

    echo
    pg_page_shell(array(
        'title'               => lang('New booking'),
        'extra classes'       => 'calendar',
        'icon'                => 'calendar',
        'heading'             => lang('New booking'),
        'heading_description' => lang(array('string' => 'Book a free time of a staff member for a customer. Times are in the site\'s time zone ({var:1}).', 'vars' => array($booking_zone))),
        'breadcrumb'          => array_merge($booking_breadcrumb, array(array('label' => lang('New booking')))),
    )) . '
<main id="content" class="container-fluid">
    ' . $liveform->output_errors() . '
    ' . $liveform->output_notices() . '
    ' . ($no_staff ? '<p class="fg-secondary">' . h(lang('There is no enabled service or staff member to book yet.')) . '</p>' : $choice_card . '
    <form action="edit_booking.php" method="post" id="booking_details">
        ' . get_token_field() . '
        <input type="hidden" name="service_id" value="' . (int) $choice_service . '">
        <input type="hidden" name="staff_id" value="' . (int) $choice_staff . '">
        <input type="hidden" name="day" value="' . h($choice_day) . '">
        <div class="row">
            <div class="col-12 lg:col-8 2xl:col-9 order-1">
                <div class="card mb-7">
                    <div class="card-header"><h2 class="h6 mb-0">' . h(lang('Customer')) . '</h2></div>
                    <div class="card-body">
                        ' . $slot_field . '
                        ' . $booking_field('name', lang('Name'), 'text', array('maxlength' => '100', 'required' => 'required', 'autocomplete' => 'off')) . '
                        <div class="row g-3">
                            <div class="col-12 md:col-6">' . $booking_field('email', lang('E-mail'), 'email', array('maxlength' => '191', 'autocomplete' => 'off')) . '</div>
                            <div class="col-12 md:col-6">' . $booking_field('phone', lang('Phone'), 'tel', array('maxlength' => '40', 'autocomplete' => 'off')) . '</div>
                        </div>
                        <p class="form-text mb-5">' . h(lang('An e-mail address or a phone number is needed to reach the customer.')) . '</p>
                        ' . $booking_field('notes', lang('Notes'), 'textarea', array('rows' => '3')) . '
                    </div>
                </div>
            </div>
            <div class="col-12 lg:col-4 2xl:col-3 order-2">
                <div class="position-sticky" style="top:3.3rem;">
                    <div class="card mb-7">
                        <div class="card-header"><h2 class="h6 mb-0">' . h(lang('Status')) . '</h2></div>
                        <div class="card-body">
                            ' . $booking_field('status', lang('Status'), 'select', array('options' => $status_options)) . '
                        </div>
                    </div>
                </div>
            </div>
            ' . ($slots ? $booking_save_bar(lang('Create booking')) : '') . '
        </div>
    </form>' . $draft_script) . '
    ' . get_date_picker_format() . pg_fields_script() . '
</main>' .
    output_footer();

    $liveform->remove_form();
    exit();
}

// An existing booking.
if (!$liveform->field_in_session('status')) {
    $liveform->assign_field_value('status', $booking['status']);
    $liveform->assign_field_value('name', $booking['name']);
    $liveform->assign_field_value('email', $booking['email']);
    $liveform->assign_field_value('phone', $booking['phone']);
    $liveform->assign_field_value('notes', (string) $booking['notes']);
}

$badges = pg_booking_agenda_status_badges();
$status_options = array(h($badges[$booking['status']][0] ?? $booking['status']) => (string) $booking['status']);

foreach (pg_booking_agenda_moves_now($booking, $booking_now) as $to) {
    $status_options[h($badges[$to][0])] = $to;
}

$final = (count($status_options) === 1);
$service_name = isset($booking_services[(int) $booking['service_id']]) ? $booking_services[(int) $booking['service_id']]['name'] : '';
$staff_name = isset($booking_staff[(int) $booking['staff_id']]) ? $booking_staff[(int) $booking['staff_id']]['name'] : '';

// The customer card: the contact the booking's address belongs to, for
// those who may open contacts.
$contact_link = '';

if (((int) $user['role'] <= 2) || !empty($user['manage_contacts'])) {
    $contact_id = pg_booking_agenda_contact_id($booking);

    if ($contact_id > 0) {
        $contact_link = '<a class="btn btn-sm btn-outline theme-secondary" href="edit_contact.php?id=' . (int) $contact_id . '"><i class="bi bi-person-vcard me-1" aria-hidden="true"></i>' . h(lang('Customer card')) . '</a>';
    }
}

$facts = array(
    lang('Time')    => h(pg_booking_agenda_time($booking['start_at'], true)) . ' – ' . h(pg_booking_agenda_time($booking['end_at'])),
    lang('Service') => h($service_name),
    lang('Staff')   => h($staff_name),
    lang('Source')  => h(pg_booking_agenda_source_label($booking['source'])),
    lang('Created') => h(pg_booking_agenda_time($booking['created_at'], true)),
);

$facts_html = '';

foreach ($facts as $label => $value) {
    $facts_html .= '<dt class="col-5 fw-normal fg-secondary">' . h($label) . '</dt><dd class="col-7 text-break">' . $value . '</dd>';
}

// The reason is kept only by a cancel, so it shows only while Cancelled is
// the chosen status.
$cancel_field = in_array('cancelled', $status_options, true) && ($booking['status'] !== 'cancelled')
    ? '<div id="booking_cancel_reason"' . (($liveform->get_field_value('status') === 'cancelled') ? '' : ' hidden') . '>' . $booking_field('cancel_reason', lang('Reason for cancelling'), 'textarea', array('rows' => '2', 'aria-describedby' => 'cancel_reason_help')) . '<p class="small fg-secondary mb-0" id="cancel_reason_help">' . h(lang('Kept in the notes when the status becomes Cancelled.')) . '</p></div>
                            <script>
                                document.getElementById("status").addEventListener("change", function () {
                                    document.getElementById("booking_cancel_reason").hidden = (this.value !== "cancelled");
                                });
                            </script>'
    : '';

// Where it was talked about in the workspace, and the tasks about it.
$workspace_button = '';

if (defined('WORKSPACE_ENABLED') && WORKSPACE_ENABLED) {
    require_once(PG_FUNCTIONS_DIR . '/includes/workspace/bootstrap.php');
    $workspace_button = ws_record_button($user, 'booking', (int) $booking['id'], lang('Booking') . ' #' . (int) $booking['id'], 'btn btn-sm btn-text theme-secondary');
}

echo
pg_page_shell(array(
    'title'               => lang('Booking') . ' #' . (int) $booking['id'],
    'extra classes'       => 'calendar',
    'icon'                => 'calendar',
    'heading'             => lang('Booking') . ' #' . (int) $booking['id'],
    'heading_description' => lang(array('string' => 'Times are in the site\'s time zone ({var:1}).', 'vars' => array($booking_zone))),
    'breadcrumb'          => array_merge($booking_breadcrumb, array(array('label' => lang('Booking') . ' #' . (int) $booking['id']))),
)) . '
<main id="content" class="container-fluid">
    ' . (($workspace_button !== '') ? pg_screen_toolbar(array('secondary' => array($workspace_button))) : '') . '
    ' . $liveform->output_errors() . '
    ' . $liveform->output_notices() . '
    <form action="edit_booking.php" method="post">
        ' . get_token_field() . '
        <input type="hidden" name="id" value="' . (int) $booking['id'] . '">
        <div class="row">
            <div class="col-12 lg:col-8 2xl:col-9 order-1">
                <div class="card mb-7">
                    <div class="card-header d-flex justify-content-between align-items-center"><h2 class="h6 mb-0">' . h(lang('Customer')) . '</h2>' . $contact_link . '</div>
                    <div class="card-body">
                        ' . $booking_field('name', lang('Name'), 'text', array('maxlength' => '100', 'required' => 'required', 'autocomplete' => 'off')) . '
                        <div class="row g-3">
                            <div class="col-12 md:col-6">' . $booking_field('email', lang('E-mail'), 'email', array('maxlength' => '191', 'autocomplete' => 'off')) . '</div>
                            <div class="col-12 md:col-6">' . $booking_field('phone', lang('Phone'), 'tel', array('maxlength' => '40', 'autocomplete' => 'off')) . '</div>
                        </div>
                        <p class="form-text mb-5">' . h(lang('An e-mail address or a phone number is needed to reach the customer.')) . '</p>
                        ' . $booking_field('notes', lang('Notes'), 'textarea', array('rows' => '4')) . '
                    </div>
                </div>
            </div>
            <div class="col-12 lg:col-4 2xl:col-3 order-2">
                <div class="position-sticky" style="top:3.3rem;">
                    <div class="card mb-7">
                        <div class="card-header d-flex justify-content-between align-items-center"><h2 class="h6 mb-0">' . h(lang('Booking')) . '</h2>' . pg_booking_agenda_badge($booking['status']) . '</div>
                        <div class="card-body">
                            <dl class="row small mb-5">' . $facts_html . '</dl>
                            ' . ($final ? '<p class="small fg-secondary mb-0">' . h(lang('This status is final.')) . '</p>' : $booking_field('status', lang('Status'), 'select', array('options' => $status_options)) . $cancel_field) . '
                        </div>
                    </div>
                </div>
            </div>
            ' . $booking_save_bar(lang('Save')) . '
        </div>
    </form>
</main>' .
output_footer();

$liveform->remove_form();
