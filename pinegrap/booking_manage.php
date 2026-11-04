<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * A booking's manage page, opened from the signed link of its mails
 * (?b=<id>&t=<token>) without an account, and from the bookings section of
 * the my_account widget: the booking, its .ics file (&ics=1), and, while
 * the policy allows it, cancel and a move to another free time of the same
 * service and staff member (&m=<month>&d=<day> pick the time). Opening the
 * link changes nothing; only a POST with the form token does
 * (includes/fn/booking_manage.php).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');

// A personal answer: never kept by a cache, never indexed, and the signed
// address is not handed to another site as a referrer.
header('Cache-Control: no-store, private');
header('X-Robots-Tag: noindex, nofollow');
header('Referrer-Policy: no-referrer');

$bm_post = (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? '')) === 'POST');
$bm_throttled = pg_booking_throttled($bm_post ? 'hold' : 'read');

if ($bm_throttled) {
    header('Retry-After: ' . $bm_throttled);
    output_error(h(lang('Too many requests from your address. Please wait a moment and try again.')), 429);
}

if ($bm_post) {
    validate_token_field();
}

$bm_input = $bm_post ? $_POST : $_GET;
$bm_now = time();
$bm_row = pg_booking_ready() ? pg_booking_manage_find($bm_input['b'] ?? '', $bm_input['t'] ?? '', $bm_now) : null;
$bm_title = lang('Your booking');

if ($bm_row === null) {
    set_response_code(404);
    print output_header_secure(array('title' => $bm_title))
        . pg_secure_card('bi-calendar-x', $bm_title, '<p>' . h(lang('This link cannot be used. It may have expired, or the booking was moved and a newer e-mail carries its link.')) . '</p>')
        . output_footer_secure();
    exit;
}

$bm_id = (int) $bm_row['id'];
$bm_token = (string) $bm_input['t'];
$bm_page = OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/booking_manage.php';
$bm_link = $bm_page . '?b=' . $bm_id . '&t=' . $bm_token;
$bm_context = pg_booking_message_context($bm_row) + array(
    'host'  => (string) HOSTNAME_SETTING,
    'store' => defined('ORGANIZATION_NAME') ? (string) ORGANIZATION_NAME : '',
);

if (!$bm_post && !empty($_GET['ics'])) {
    header('Content-Type: text/calendar; charset=utf-8');
    header('Content-Disposition: attachment; filename="booking-' . $bm_id . '.ics"');
    print pg_booking_ics($bm_row, $bm_context, $bm_now);
    exit;
}

$bm_policy = pg_booking_widget_policy();
$bm_rights = pg_booking_manage_rights($bm_row, $bm_policy, $bm_now);
$bm_engine = $bm_rights['reschedule'] ? pg_booking_manage_engine($bm_row) : null;
$bm_error = '';

if ($bm_post) {
    $bm_action = is_string($_POST['action'] ?? null) ? $_POST['action'] : '';

    if (($bm_action === 'cancel') && $bm_rights['cancel']) {

        if (pg_booking_manage_cancel($bm_row, $bm_now)) {
            go($bm_link . '&done=cancelled');
        }

        $bm_error = lang('The booking was changed meanwhile. Please check it and try again.');

    } elseif (($bm_action === 'move') && ($bm_engine !== null)) {

        $bm_start = (is_scalar($_POST['start'] ?? null) && preg_match('/^\d{1,10}$/', (string) $_POST['start'])) ? (int) $_POST['start'] : 0;
        $bm_day = ($bm_start > 0) ? (new DateTime('@' . $bm_start))->setTimezone(new DateTimeZone($bm_policy['zone']))->format('Y-m-d') : '';
        $bm_moved = (($bm_start > 0) && in_array($bm_start, pg_booking_manage_times($bm_row, $bm_engine, $bm_day, $bm_now), true))
            ? pg_booking_manage_move($bm_row, $bm_engine[0], $bm_start, $bm_now)
            : array('answer' => 'taken', 'token' => '');

        if (($bm_moved['answer'] === 'ok') && ($bm_moved['token'] !== '')) {
            go($bm_page . '?b=' . $bm_id . '&t=' . $bm_moved['token'] . '&done=moved');
        }

        $bm_error = ($bm_moved['answer'] === 'taken') ? pg_booking_widget_taken_text()
            : lang('The booking was changed meanwhile. Please check it and try again.');

    } else {
        $bm_error = lang('This booking can no longer be changed online. Please contact us.');
    }
}

// The booking.
$bm_times = pg_booking_message_times((int) $bm_row['start_at'], (int) $bm_row['end_at'], $bm_context['zone'], $bm_context['date_code'], $bm_context['time_code']);
$bm_status = (string) $bm_row['status'];
$bm_content = '';

$bm_done = is_string($_GET['done'] ?? null) ? $_GET['done'] : '';

if (!$bm_post && ($bm_done === 'cancelled') && ($bm_status === 'cancelled')) {
    $bm_content .= '<div class="alert theme-success" role="status">' . h(lang('Your booking has been cancelled.')) . '</div>';
} elseif (!$bm_post && ($bm_done === 'moved')) {
    $bm_content .= '<div class="alert theme-success" role="status">' . h(lang('Your booking has been moved. An e-mail with the new time is on its way.')) . '</div>';
}

if ($bm_error !== '') {
    $bm_content .= '<div class="alert theme-danger" role="alert">' . h($bm_error) . '</div>';
}

$bm_facts = array(
    lang('Service') => (string) $bm_row['service_name'],
    lang('With')    => (string) $bm_row['staff_name'],
    lang('Date')    => $bm_times['date'],
    lang('Time')    => $bm_times['time'],
    lang('Place')   => (string) $bm_context['place'],
    lang('Status')  => pg_booking_customer_status_label($bm_status),
    lang('Deposit') => pg_booking_deposit_label(pg_booking_deposit_view($bm_row)),
);

$bm_content .= '<dl class="mb-12">';

foreach ($bm_facts as $bm_label => $bm_value) {
    if ($bm_value !== '') {
        $bm_content .= '<dt class="small fg-secondary">' . h($bm_label) . '</dt><dd class="mb-5">' . h($bm_value) . '</dd>';
    }
}

$bm_content .= '</dl>';

if ($bm_status !== 'cancelled') {
    $bm_content .= '<p class="mb-12"><a class="btn btn-outline theme-secondary" href="' . h($bm_link . '&ics=1') . '">'
        . '<i class="bi bi-calendar-plus me-3" aria-hidden="true"></i>' . h(lang('Add to calendar')) . '</a></p>';
}

$bm_hidden = get_token_field()
    . '<input type="hidden" name="b" value="' . $bm_id . '">'
    . '<input type="hidden" name="t" value="' . h($bm_token) . '">';

// A move: the free days of a month, then the times of the chosen day.
if ($bm_engine !== null) {
    $bm_months = pg_booking_manage_months($bm_now, (int) $bm_policy['horizon_days'], $bm_policy['zone']);
    $bm_month = (is_string($_GET['m'] ?? null) && in_array($_GET['m'], $bm_months, true)) ? $_GET['m'] : $bm_months[0];
    $bm_index = array_search($bm_month, $bm_months, true);
    $bm_days = pg_booking_manage_days($bm_row, $bm_engine, $bm_month, $bm_now);
    $bm_day = (is_string($_GET['d'] ?? null) && in_array($_GET['d'], $bm_days, true)) ? $_GET['d'] : '';
    $bm_zone = new DateTimeZone($bm_policy['zone']);

    $bm_content .= '<h2 class="h6 mb-5">' . h(lang('Move to another time')) . '</h2>'
        . '<div class="d-flex align-items-center justify-content-between mb-5">';
    $bm_content .= ($bm_index > 0)
        ? '<a class="btn btn-sm btn-outline theme-secondary" href="' . h($bm_link . '&m=' . $bm_months[$bm_index - 1]) . '" aria-label="' . h(lang('Previous month')) . '"><i class="bi bi-chevron-left" aria-hidden="true"></i></a>'
        : '<span></span>';
    $bm_content .= '<span class="fw-semibold">' . h(pg_widget_month_name((int) substr($bm_month, 5, 2)) . ' ' . substr($bm_month, 0, 4)) . '</span>';
    $bm_content .= ($bm_index < count($bm_months) - 1)
        ? '<a class="btn btn-sm btn-outline theme-secondary" href="' . h($bm_link . '&m=' . $bm_months[$bm_index + 1]) . '" aria-label="' . h(lang('Next month')) . '"><i class="bi bi-chevron-right" aria-hidden="true"></i></a>'
        : '<span></span>';
    $bm_content .= '</div>';

    if (!$bm_days) {
        $bm_content .= '<p class="fg-secondary mb-12">' . h(lang('No free times this month.')) . '</p>';
    } else {
        $bm_content .= '<div class="d-flex flex-wrap gap-3 mb-12">';

        foreach ($bm_days as $bm_free_day) {
            $bm_label = (new DateTime($bm_free_day . ' 12:00:00', $bm_zone))->format($bm_context['date_code']);
            $bm_content .= '<a class="btn btn-sm ' . (($bm_free_day === $bm_day) ? 'btn-solid theme-primary' : 'btn-outline theme-secondary') . '"'
                . ' href="' . h($bm_link . '&m=' . $bm_month . '&d=' . $bm_free_day) . '"'
                . (($bm_free_day === $bm_day) ? ' aria-current="date"' : '') . '>' . h($bm_label) . '</a>';
        }

        $bm_content .= '</div>';
    }

    if ($bm_day !== '') {
        $bm_starts = pg_booking_manage_times($bm_row, $bm_engine, $bm_day, $bm_now);
        $bm_content .= '<form method="post" action="' . h($bm_page) . '" class="mb-12">' . $bm_hidden
            . '<input type="hidden" name="action" value="move">'
            . '<p class="mb-5">' . h(lang('Choose the new time:')) . '</p><div class="d-flex flex-wrap gap-3">';

        foreach ($bm_starts as $bm_start) {
            $bm_content .= '<button type="submit" name="start" value="' . (int) $bm_start . '" class="btn btn-sm btn-outline theme-primary">'
                . h(pg_booking_time_label($bm_start, $bm_policy['zone'])) . '</button>';
        }

        $bm_content .= '</div></form>';
    }
}

if ($bm_rights['cancel']) {
    // Asked twice without a script: the button that cancels shows only once
    // the customer opened this.
    $bm_content .= '<details><summary class="btn btn-outline theme-danger">' . h(lang('Cancel booking')) . '</summary>'
        . '<form method="post" action="' . h($bm_page) . '" class="mt-5">' . $bm_hidden
        . '<input type="hidden" name="action" value="cancel">'
        . '<p class="mb-5">' . h(lang('The time will be given to someone else.')) . '</p>'
        . '<button type="submit" class="btn btn-solid theme-danger">' . h(lang('Yes, cancel')) . '</button>'
        . '</form></details>';
} elseif (in_array($bm_status, array('requested', 'confirmed'), true)) {
    $bm_content .= '<p class="fg-secondary">' . h(lang('This booking can no longer be changed online. Please contact us.')) . '</p>';
}

print output_header_secure(array('title' => $bm_title))
    . pg_secure_card('bi-calendar-check', $bm_title, $bm_content)
    . output_footer_secure();
