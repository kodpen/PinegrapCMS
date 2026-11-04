<?php

/**
 * Pinegrap - Enterprise Website Platform
 *
 * Site Settings - what the Bookings screen writes: the rows of
 * booking_services and booking_staff its cards drew, and the booking_policy
 * and booking_texts rows of config_text.
 *
 * Runs in the scope of includes/settings/screen.php, after the token check and
 * after post_value() is defined. Every box is checked before anything is
 * written, so a refused save leaves every service, staff member and rule as
 * it was. Only the rows the cards drew are written: an id that is not a
 * service, or a staff block without its seen marker, is passed over.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_SETTINGS_ENTRY')) {
    exit;
}

// Before the upgrade the cards draw no control (includes/settings/bookings.php).
if (!function_exists('pg_booking_ready') || !pg_booking_ready()) {
    return;
}

$booking_now = time();
$booking_today = date('Y-m-d');

// ── Services ──
$booking_known = pg_booking_settings_services();
$booking_posted = (isset($_POST['booking_service']) && is_array($_POST['booking_service'])) ? $_POST['booking_service'] : array();
$booking_service_rows = array();
$booking_service_new = array();

$booking_service_errors = array(
    'name'   => lang('Type a name for the service.'),
    'length' => lang('A service lasts from 5 to 1440 minutes.'),
    'price'  => lang('The price is too large.'),
    'deposit' => lang('The deposit must be a part of the price: no more than the price, and only for a service with a price.'),
    'deposit_product' => lang('Choose the product the deposit is charged as.'),
);

foreach ($booking_posted as $key => $row) {

    $key = (string) $key;

    if ($key === 'new') {
        if (!is_array($row) || pg_booking_settings_service_blank($row)) {
            continue;
        }
    } elseif ((preg_match('/^[1-9]\d*$/', $key) !== 1) || !isset($booking_known[(int) $key])) {
        continue;
    }

    $answer = pg_booking_settings_service_clean($row);

    if ($answer['error'] !== '') {
        $liveform->mark_error('booking_service[' . $key . '][' . $answer['error'] . ']', $booking_service_errors[$answer['error']]);
    } elseif ($key === 'new') {
        $booking_service_new[] = $answer['values'];
    } else {
        $booking_service_rows[(int) $key] = $answer['values'];
    }
}

// ── Staff ──
$booking_staff = pg_booking_settings_staff();
$booking_service_ids = array_map('intval', array_keys($booking_known));
$booking_posted = (isset($_POST['booking_staff']) && is_array($_POST['booking_staff'])) ? $_POST['booking_staff'] : array();
$booking_staff_rows = array();

foreach ($booking_posted as $user_id => $row) {

    if (!is_array($row) || empty($row['seen']) || (preg_match('/^[1-9]\d*$/', (string) $user_id) !== 1) || !isset($booking_staff[(int) $user_id])) {
        continue;
    }

    $name = 'booking_staff[' . (int) $user_id . ']';
    $hours = pg_booking_settings_hours_from_fields($row);

    foreach ($hours['bad'] as $box) {
        $liveform->mark_error($name . '[' . $box . ']', lang('Type a time, and an end after the start.'));
    }

    $list = function ($key) use ($row) {
        return (isset($row[$key]) && is_array($row[$key])) ? array_values($row[$key]) : array();
    };

    $exceptions = pg_booking_settings_exceptions_from_rows($list('x_from'), $list('x_to'), $list('x_open'), $list('x_close'), $booking_today);

    if ($exceptions['bad']) {
        $liveform->mark_error($name . '[x_from][]', lang('A day off or special hours row is not right: the first day must be a date, the last day not before it, and the hours must end after they start.'));
    }

    $booking_staff_rows[(int) $user_id] = array(
        'service_ids' => pg_booking_settings_service_ids(isset($row['services']) ? $row['services'] : array(), $booking_service_ids),
        'hours'       => $hours['hours'],
        'exceptions'  => $exceptions['exceptions'],
        'enabled'     => !empty($row['enabled']) ? 1 : 0,
    );
}

// A user picked to be added must be a panel user who is not staff yet.
$booking_add = (int) post_value('booking_staff_add');

$booking_candidates = ($booking_add > 0) ? pg_booking_settings_candidates($booking_staff) : array();

if (($booking_add > 0) && !isset($booking_candidates[$booking_add])) {
    $liveform->mark_error('booking_staff_add', lang('Choose a panel user who does not take bookings yet.'));
}

// ── Booking rules and the widget's texts ──
$booking_policy = pg_booking_settings_policy_clean(array(
    'lead_minutes'   => post_value('booking_lead_minutes'),
    'horizon_days'   => post_value('booking_horizon_days'),
    'cancel_hours'   => post_value('booking_cancel_hours'),
    'reschedule'     => post_value('booking_reschedule') ? 1 : 0,
    'hold_minutes'   => post_value('booking_hold_minutes'),
    'reminder_hours' => (string) post_value('booking_reminder_hours'),
    'auto_confirm'   => post_value('booking_auto_confirm') ? 1 : 0,
    'step_minutes'   => post_value('booking_step_minutes'),
));

$booking_texts = array();

foreach (array_keys(pg_booking_settings_text_fields()) as $key) {
    $booking_texts[$key] = (string) post_value('booking_text_' . $key);
}

$booking_texts = pg_booking_settings_texts_clean($booking_texts);

if ($liveform->check_form_errors()) {
    return;
}

pg_booking_settings_save_services($booking_service_rows, $booking_service_new, $booking_now);

$booking_enabled_services = array();

foreach ($booking_known as $service_id => $service) {
    if (isset($booking_service_rows[(int) $service_id]) ? $booking_service_rows[(int) $service_id]['enabled'] : !empty($service['enabled'])) {
        $booking_enabled_services[] = (int) $service_id;
    }
}

pg_booking_settings_save_staff($booking_staff_rows, $booking_add, $booking_enabled_services, $booking_now);

if (!pg_config_text_save('booking_policy', json_encode($booking_policy))
    || !pg_config_text_save('booking_texts', $booking_texts ? json_encode($booking_texts, JSON_UNESCAPED_UNICODE) : '')) {
    $liveform->add_warning(lang('The booking rules could not be saved: the settings texts table is missing. Run the upgrade.'));
}
