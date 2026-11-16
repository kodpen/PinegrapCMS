<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * A booking staff member's meeting link (?c=<code>): "Book a time with
 * <name>". The member's own services (&s=), the free days of a month (&m=)
 * and the times of a day (&d=) as the booking engine works them out for
 * that member alone, then the visitor's details and Confirm. No script: a
 * time is held by a POST (action=hold) and the hold is booked by another
 * (action=confirm), through pg_booking_hold() and pg_booking_confirm() with
 * the source 'link' (includes/fn/booking_links.php). A wrong code and a
 * member who cannot be booked get the same 404.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');

foreach (pg_booking_link_page_headers() as $bw_header) {
    header($bw_header);
}

$bw_post = (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? '')) === 'POST');
$bw_refusal = pg_booking_link_refusal($bw_post, pg_request_cross_site($_SERVER), pg_booking_throttled($bw_post ? 'hold' : 'read'));

if ($bw_refusal !== null) {
    if ($bw_refusal['retry'] > 0) {
        header('Retry-After: ' . $bw_refusal['retry']);
    }

    output_error(h($bw_refusal['message']), $bw_refusal['status']);
}

if ($bw_post) {
    validate_token_field();
}

$bw_input = $bw_post ? $_POST : $_GET;
$bw_code = (isset($bw_input['c']) && is_string($bw_input['c'])) ? $bw_input['c'] : '';
$bw_member = pg_booking_link_member($bw_code);
$bw_services = array();

if ($bw_member !== null) {
    list($bw_bookable) = pg_booking_widget_data();
    $bw_services = pg_booking_link_services($bw_bookable, $bw_member);
}

if (($bw_member === null) || !$bw_services) {
    $bw_title = lang('Online booking');
    set_response_code(404);
    print output_header_secure(array('title' => $bw_title))
        . pg_secure_card('bi-calendar-x', $bw_title, '<p>' . h(lang('This link cannot be used. Please ask the person who gave it to you for a new one.')) . '</p>')
        . output_footer_secure();
    exit;
}

$bw_now = time();
$bw_policy = pg_booking_widget_policy();
$bw_zone = new DateTimeZone($bw_policy['zone']);
$bw_page = OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/book_with.php';
$bw_link = $bw_page . '?c=' . h($bw_code);
$bw_title = lang(array('string' => 'Book a time with {var:1}', 'vars' => $bw_member['name']));
$bw_context = pg_booking_message_context(array('id' => 0, 'end_at' => 0, 'status' => 'cancelled'));

$bw_service_id = isset($bw_input['s']) && is_scalar($bw_input['s']) ? (int) $bw_input['s'] : 0;
$bw_service = pg_booking_link_service($bw_services, $bw_member, $bw_service_id);

if (($bw_service === null) && (count($bw_services) === 1)) {
    $bw_service = $bw_services[0];
}

$bw_error = '';
$bw_values = array('name' => '', 'email' => '', 'phone' => '', 'notes' => '');
$bw_errors = array();
$bw_day = '';

if ($bw_post) {
    $bw_action = (isset($_POST['action']) && is_string($_POST['action'])) ? $_POST['action'] : '';

    if (($bw_action === 'hold') && ($bw_service !== null)) {

        $bw_start = (is_scalar($_POST['start'] ?? null) && preg_match('/^\d{1,10}$/', (string) $_POST['start'])) ? (int) $_POST['start'] : 0;
        $bw_day = ($bw_start > 0) ? (new DateTime('@' . $bw_start))->setTimezone($bw_zone)->format('Y-m-d') : '';
        $bw_answer = 'taken';

        // The time this session held before is free again, the same time too.
        pg_booking_release_session_hold($bw_now);

        if ($bw_day !== '') {
            list($bw_from, $bw_until) = pg_booking_link_busy_window($bw_day, '+1 day', $bw_policy['zone']);
            $bw_starts = pg_booking_link_times($bw_service, $bw_member, $bw_day, pg_booking_link_busy($bw_member, $bw_from, $bw_until, $bw_now), $bw_now, $bw_policy);

            if (in_array($bw_start, $bw_starts, true)) {
                $bw_held = pg_booking_hold(array(
                    'service'    => $bw_service,
                    'service_id' => (int) $bw_service['id'],
                    'staff_id'   => (int) $bw_member['id'],
                    'start_at'   => $bw_start,
                ), $bw_policy['hold_minutes'], $bw_now, PG_BOOKING_LINK_SOURCE);
                $bw_answer = $bw_held['answer'];

                if ($bw_answer === 'ok') {
                    $_SESSION['software']['booking_hold'] = (int) $bw_held['id'];
                    $_SESSION['software']['booking_hold_slot'] = array((int) $bw_service['id'], (int) $bw_member['id'], $bw_start);
                    go($bw_page . '?c=' . $bw_code . '&s=' . (int) $bw_service['id'] . '&details=1');
                }
            }
        }

        $bw_error = pg_booking_link_answer_text($bw_answer);

    } elseif ($bw_action === 'confirm') {

        $bw_hold = pg_booking_link_held($_SESSION['software']['booking_hold'] ?? 0, $_SESSION['software']['booking_hold_slot'] ?? null, $bw_member, $bw_services);
        $bw_details = pg_booking_link_details($_POST);
        $bw_values = $bw_details['values'];
        $bw_errors = $bw_details['errors'];

        if ($bw_hold === null) {
            $bw_error = pg_booking_link_answer_text('expired');
        } elseif (!$bw_errors) {
            $bw_service = $bw_hold['service'];
            $bw_status = !empty($bw_policy['auto_confirm']) ? 'confirmed' : 'requested';
            $bw_visitor = pg_booking_widget_visitor();
            $bw_booking = $bw_values + array(
                'id'         => $bw_hold['id'],
                'user_id'    => $bw_visitor['user_id'],
                'service'    => $bw_service,
                'service_id' => (int) $bw_service['id'],
                'staff_id'   => (int) $bw_member['id'],
                'start_at'   => $bw_hold['start'],
            );
            $bw_done = pg_booking_confirm($bw_booking, $bw_status, $bw_now, PG_BOOKING_LINK_SOURCE);

            // The hold ran out: book the time at once when it is still free.
            if (($bw_done['answer'] === 'expired') && pg_booking_widget_offered($bw_service, array($bw_member), $bw_hold['start'], $bw_now, $bw_policy)) {
                unset($bw_booking['id']);
                $bw_done = pg_booking_confirm($bw_booking, $bw_status, $bw_now, PG_BOOKING_LINK_SOURCE);
            }

            if ($bw_done['answer'] === 'ok') {
                unset($_SESSION['software']['booking_hold'], $_SESSION['software']['booking_hold_slot']);
                $_SESSION['software']['booking_link_done'] = array((int) $bw_done['id'], (string) $bw_done['token'], $bw_status);
                go($bw_page . '?c=' . $bw_code . '&done=1');
            }

            if ($bw_done['answer'] !== 'error') {
                pg_booking_release_session_hold($bw_now);
            }

            $bw_error = pg_booking_link_answer_text($bw_done['answer']);
            $bw_day = (new DateTime('@' . $bw_hold['start']))->setTimezone($bw_zone)->format('Y-m-d');
        } else {
            $bw_error = lang('Please check the marked fields.');
        }

    } else {
        $bw_error = lang('Invalid request.');
    }
}

$bw_content = '';

if ($bw_error !== '') {
    $bw_content .= '<div class="alert theme-danger" role="alert">' . h($bw_error) . '</div>';
}

// The booking just made, from this session only.
$bw_made = (!$bw_post && !empty($_GET['done']) && isset($_SESSION['software']['booking_link_done']) && is_array($_SESSION['software']['booking_link_done']))
    ? $_SESSION['software']['booking_link_done'] : null;
$bw_made_row = ($bw_made !== null) ? pg_booking_message_row((int) $bw_made[0]) : null;

if (is_array($bw_made_row) && ((int) $bw_made_row['staff_id'] === (int) $bw_member['id'])) {
    $bw_made_context = pg_booking_message_context($bw_made_row);
    $bw_times = pg_booking_message_times((int) $bw_made_row['start_at'], (int) $bw_made_row['end_at'], $bw_made_context['zone'], $bw_made_context['date_code'], $bw_made_context['time_code']);
    $bw_facts = array(
        lang('Service')      => (string) $bw_made_row['service_name'],
        lang('Staff member') => (string) $bw_member['name'],
        lang('Date')         => $bw_times['date'],
        lang('Time')         => $bw_times['time'],
    );

    $bw_content .= '<div class="alert theme-success" role="status">' . h(pg_booking_widget_done_text((string) $bw_made[2], pg_booking_texts())) . '</div><dl class="mb-12">';

    foreach ($bw_facts as $bw_label => $bw_value) {
        if ($bw_value !== '') {
            $bw_content .= '<dt class="small fg-secondary">' . h($bw_label) . '</dt><dd class="mb-5">' . h($bw_value) . '</dd>';
        }
    }

    if ($bw_made_context['meeting_url'] !== '') {
        $bw_content .= '<dt class="small fg-secondary">' . h(lang('Meeting address')) . '</dt><dd class="mb-5 text-break">'
            . '<a href="' . h($bw_made_context['meeting_url']) . '" rel="noopener noreferrer" target="_blank">' . h($bw_made_context['meeting_url']) . '</a></dd>';
    }

    $bw_content .= '</dl>';

    if ((string) $bw_made[1] !== '') {
        $bw_content .= '<p class="mb-0"><a class="btn btn-outline theme-secondary" href="' . h(OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/booking_manage.php?b=' . (int) $bw_made[0] . '&t=' . $bw_made[1] . '&ics=1') . '">'
            . '<i class="bi bi-calendar-plus me-3" aria-hidden="true"></i>' . h(lang('Add to calendar')) . '</a></p>';
    }

    print output_header_secure(array('title' => $bw_title))
        . pg_secure_card('bi-calendar-check', $bw_title, $bw_content)
        . output_footer_secure();
    exit;
}

// The service: chosen from a list when the link offers more than one.
if ($bw_service === null) {
    $bw_content .= '<p class="mb-5">' . h(lang('Choose a service:')) . '</p><div class="list-group mb-0">';

    foreach ($bw_services as $bw_row) {
        $bw_price = (int) $bw_row['price'];
        $bw_meta = lang(array('string' => '{var:1} min', 'vars' => (int) $bw_row['length_minutes']))
            . ((($bw_price > 0) && function_exists('prepare_price_for_output')) ? ' · ' . html_entity_decode(prepare_price_for_output($bw_price, false, 0, 'plain_text'), ENT_QUOTES | ENT_HTML5, 'UTF-8') : '');
        $bw_content .= '<a class="list-group-item list-group-item-action" href="' . $bw_link . '&amp;s=' . (int) $bw_row['id'] . '">'
            . '<span class="d-block fw-semibold">' . h($bw_row['name']) . '</span>'
            . '<span class="d-block small fg-secondary">' . h($bw_meta) . '</span></a>';
    }

    print output_header_secure(array('title' => $bw_title))
        . pg_secure_card('bi-calendar-plus', $bw_title, $bw_content . '</div>')
        . output_footer_secure();
    exit;
}

$bw_service_link = $bw_link . '&amp;s=' . (int) $bw_service['id'];
$bw_content .= '<p class="mb-7"><span class="fw-semibold">' . h($bw_service['name']) . '</span> · ' . h(lang(array('string' => '{var:1} min', 'vars' => (int) $bw_service['length_minutes'])))
    . ((count($bw_services) > 1) ? ' · <a href="' . $bw_link . '">' . h(lang('Change')) . '</a>' : '') . '</p>';

// The details form, for the time this session holds.
$bw_hold = pg_booking_link_held($_SESSION['software']['booking_hold'] ?? 0, $_SESSION['software']['booking_hold_slot'] ?? null, $bw_member, array($bw_service));
$bw_show_form = ($bw_hold !== null) && (($bw_post && ($bw_errors || ($bw_error === ''))) || (!$bw_post && !empty($_GET['details'])));

if ($bw_show_form) {
    if (!$bw_post) {
        $bw_visitor = pg_booking_widget_visitor();
        unset($bw_visitor['user_id']);
        $bw_values = $bw_visitor + array('notes' => '');
    }

    $bw_times = pg_booking_message_times($bw_hold['start'], $bw_hold['start'] + ((int) $bw_service['length_minutes'] * 60), $bw_context['zone'], $bw_context['date_code'], $bw_context['time_code']);
    $bw_field = function ($name, $label, $type, $autocomplete, $required) use ($bw_values, $bw_errors) {
        $invalid = isset($bw_errors[$name]);
        $id = 'bw_' . $name;
        $attributes = ' id="' . $id . '" name="' . $name . '" autocomplete="' . $autocomplete . '"' . ($required ? ' required' : '')
            . ($invalid ? ' aria-invalid="true" aria-describedby="' . $id . '_error"' : '');
        $control = ($type === 'textarea')
            ? '<textarea class="form-control' . ($invalid ? ' is-invalid' : '') . '"' . $attributes . ' rows="3" maxlength="1000">' . h($bw_values[$name]) . '</textarea>'
            : '<input type="' . $type . '" class="form-control' . ($invalid ? ' is-invalid' : '') . '"' . $attributes . ' value="' . h($bw_values[$name]) . '">';

        return '<div class="mb-5"><label class="form-label" for="' . $id . '">' . h($label) . '</label>' . $control
            . ($invalid ? '<div class="invalid-feedback" id="' . $id . '_error">' . h($bw_errors[$name]) . '</div>' : '') . '</div>';
    };
    $bw_privacy = pg_booking_widget_privacy_url();

    $bw_content .= '<p class="mb-7"><i class="bi bi-clock me-3" aria-hidden="true"></i>' . h($bw_times['date'] . ', ' . $bw_times['time'])
        . ' · <a href="' . $bw_service_link . '&amp;m=' . substr($bw_times['start'], 0, 7) . '&amp;d=' . substr($bw_times['start'], 0, 10) . '">' . h(lang('Pick another time')) . '</a></p>'
        . '<p class="small fg-secondary mb-7">' . h(lang(array('string' => 'We keep this time for you for {var:1} minutes.', 'vars' => (int) $bw_policy['hold_minutes']))) . '</p>'
        . '<form method="post" action="' . $bw_page . '" novalidate>' . get_token_field()
        . '<input type="hidden" name="c" value="' . h($bw_code) . '">'
        . '<input type="hidden" name="s" value="' . (int) $bw_service['id'] . '">'
        . '<input type="hidden" name="action" value="confirm">'
        . $bw_field('name', lang('Name'), 'text', 'name', true)
        . $bw_field('email', lang('E-mail'), 'email', 'email', true)
        . $bw_field('phone', lang('Phone'), 'tel', 'tel', false)
        . $bw_field('notes', lang('Note'), 'textarea', 'off', false)
        . '<div class="form-field mb-7"><input class="check' . (isset($bw_errors['consent']) ? ' is-invalid' : '') . '" type="checkbox" id="bw_consent" name="consent" value="1" required'
        . (!empty($_POST['consent']) ? ' checked' : '') . '>'
        . '<label for="bw_consent">' . h(lang('I agree that my details are kept to arrange this appointment.'))
        . (($bw_privacy !== '') ? ' <a href="' . $bw_privacy . '" target="_blank" rel="noopener">' . h(lang('Privacy notice')) . '</a>' : '') . '</label>'
        . (isset($bw_errors['consent']) ? '<div class="invalid-feedback">' . h($bw_errors['consent']) . '</div>' : '') . '</div>'
        . '<button type="submit" class="btn btn-solid theme-primary w-100">' . h(lang('Confirm')) . '</button></form>';

    print output_header_secure(array('title' => $bw_title))
        . pg_secure_card('bi-calendar-plus', $bw_title, $bw_content)
        . output_footer_secure();
    exit;
}

// The time: a month's free days, then the chosen day's times.
$bw_months = pg_booking_manage_months($bw_now, (int) $bw_policy['horizon_days'], $bw_policy['zone']);
$bw_month = ($bw_day !== '') ? substr($bw_day, 0, 7) : ((isset($_GET['m']) && is_string($_GET['m']) && in_array($_GET['m'], $bw_months, true)) ? $_GET['m'] : $bw_months[0]);
$bw_month = in_array($bw_month, $bw_months, true) ? $bw_month : $bw_months[0];
$bw_index = (int) array_search($bw_month, $bw_months, true);
list($bw_from, $bw_until) = pg_booking_link_busy_window($bw_month . '-01', '+1 month', $bw_policy['zone']);
$bw_busy = pg_booking_link_busy($bw_member, $bw_from, $bw_until, $bw_now);
$bw_days = pg_booking_link_days($bw_service, $bw_member, $bw_month, $bw_busy, $bw_now, $bw_policy);

if ($bw_day === '') {
    $bw_day = (isset($_GET['d']) && is_string($_GET['d']) && in_array($_GET['d'], $bw_days, true)) ? $_GET['d'] : '';
}

$bw_content .= '<div class="d-flex align-items-center justify-content-between mb-5">';
$bw_content .= ($bw_index > 0)
    ? '<a class="btn btn-sm btn-outline theme-secondary" href="' . $bw_service_link . '&amp;m=' . $bw_months[$bw_index - 1] . '" aria-label="' . h(lang('Previous month')) . '"><i class="bi bi-chevron-left" aria-hidden="true"></i></a>'
    : '<span></span>';
$bw_content .= '<span class="fw-semibold">' . h(pg_widget_month_name((int) substr($bw_month, 5, 2)) . ' ' . substr($bw_month, 0, 4)) . '</span>';
$bw_content .= ($bw_index < count($bw_months) - 1)
    ? '<a class="btn btn-sm btn-outline theme-secondary" href="' . $bw_service_link . '&amp;m=' . $bw_months[$bw_index + 1] . '" aria-label="' . h(lang('Next month')) . '"><i class="bi bi-chevron-right" aria-hidden="true"></i></a>'
    : '<span></span>';
$bw_content .= '</div>';

if (!$bw_days) {
    $bw_content .= '<p class="fg-secondary mb-0">' . h(lang('No free times this month.')) . '</p>';
} else {
    $bw_content .= '<p class="mb-5">' . h(lang('Choose a day:')) . '</p><div class="d-flex flex-wrap gap-3 mb-12">';

    foreach ($bw_days as $bw_free_day) {
        $bw_label = (new DateTime($bw_free_day . ' 12:00:00', $bw_zone))->format($bw_context['date_code']);
        $bw_content .= '<a class="btn btn-sm ' . (($bw_free_day === $bw_day) ? 'btn-solid theme-primary' : 'btn-outline theme-secondary') . '"'
            . ' href="' . $bw_service_link . '&amp;m=' . $bw_month . '&amp;d=' . $bw_free_day . '"'
            . (($bw_free_day === $bw_day) ? ' aria-current="date"' : '') . '>' . h($bw_label) . '</a>';
    }

    $bw_content .= '</div>';
}

if ($bw_day !== '') {
    list($bw_from, $bw_until) = pg_booking_link_busy_window($bw_day, '+1 day', $bw_policy['zone']);
    $bw_starts = pg_booking_link_times($bw_service, $bw_member, $bw_day, pg_booking_link_busy($bw_member, $bw_from, $bw_until, $bw_now), $bw_now, $bw_policy);

    if (!$bw_starts) {
        $bw_content .= '<p class="fg-secondary mb-0">' . h(lang('No free times on this day.')) . '</p>';
    } else {
        $bw_content .= '<form method="post" action="' . $bw_page . '">' . get_token_field()
            . '<input type="hidden" name="c" value="' . h($bw_code) . '">'
            . '<input type="hidden" name="s" value="' . (int) $bw_service['id'] . '">'
            . '<input type="hidden" name="action" value="hold">'
            . '<p class="mb-5">' . h(lang('Choose a time:')) . '</p><div class="d-flex flex-wrap gap-3">';

        foreach ($bw_starts as $bw_start) {
            $bw_content .= '<button type="submit" name="start" value="' . (int) $bw_start . '" class="btn btn-sm btn-outline theme-primary">'
                . h(pg_booking_time_label($bw_start, $bw_policy['zone'])) . '</button>';
        }

        $bw_content .= '</div></form>';
    }
}

print output_header_secure(array('title' => $bw_title))
    . pg_secure_card('bi-calendar-plus', $bw_title, $bw_content)
    . output_footer_secure();
