<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Site Settings - the Bookings cards: the services that can be booked, the
 * staff who take them (their services, weekly hours and dated exceptions),
 * the booking rules, the booking messages and the booking widget's texts.
 *
 * Each card is one folded row (includes/settings/fold.php) with its line of
 * state; the services are list rows, each edited in its drawer, whose boxes
 * join the settings form by its id and post under the names they always had.
 * Which cards belong here is said once, in includes/settings/registry.php;
 * this file holds them, and bookings.save.php writes what they edit. The rows
 * and the two config_text entries are read through
 * includes/fn/booking_settings.php.
 *
 * The staff are list rows too, each edited in its drawer; "Add a staff
 * member" opens a drawer with the picker and the weekly hours. Every staff
 * member's row posts booking_staff[<user id>][seen], so the save tells a
 * member who was drawn with every switch off from one who was not drawn.
 *
 * The Messages row shows which booking mails and texts go and leads to the
 * campaign profiles and the SMS messages, where they are set; it posts
 * nothing.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_SETTINGS_ENTRY')) {
    exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/settings/fold.php');

// The tables come with the 2026.11.0 upgrade (11.0.157); before it every
// row says so and draws no control the save would ignore.
$booking_ready = function_exists('pg_booking_ready') && pg_booking_ready();

$booking_not_ready = lang('Bookings arrive with the 2026.11.0 upgrade, which this site has not run yet.');

// One card in the settings' shape; $body is the inside of its row, $button a
// quiet button kept in the folded row's head.
$booking_card = function ($id, $title, $body, $button = '') {
    return '
    <div id="' . $id . '" class="pg-set-card">
        <div class="card">
            <div class="card-header bg-reset border-0 ' . (($button !== '') ? 'd-flex align-items-center gap-3' : 'pg-title-section') . '">
                ' . (($button !== '') ? '<span class="pg-title-section flex-grow-1">' . $title . '</span>' . $button : $title) . '
            </div>
            <div class="card-body">
                <div class="row gy-5">' . $body . '
                </div>
            </div>
        </div>
    </div>';
};

// The folded rows, in reading order: key, card id, name, icon, tone.
$booking_faces = array(
    array('services', 'pgset-booking-services', lang('Bookable services'), 'bi-card-checklist', 'pg-tone-shop'),
    array('staff', 'pgset-booking-staff', lang('Staff'), 'bi-people', 'pg-tone-team'),
    array('policy', 'pgset-booking-policy', lang('Booking rules'), 'bi-sliders', 'pg-tone-violet'),
    array('messages', 'pgset-booking-messages', lang('Messages'), 'bi-envelope', 'pg-tone-blue'),
    array('texts', 'pgset-booking-texts', lang('Booking widget texts'), 'bi-chat-left-text', 'pg-tone-teal'),
);

// $parts: key => array(line, card body, head button).
$booking_fold = function ($parts) use ($booking_faces, $booking_card) {

    $rows = array();

    foreach ($booking_faces as $face) {
        $rows[] = array(
            'key'  => $face[0],
            'name' => $face[2],
            'icon' => $face[3],
            'tone' => $face[4],
            'line' => $parts[$face[0]][0],
            'pill' => null,
            'html' => $booking_card($face[1], $face[2], $parts[$face[0]][1], $parts[$face[0]][2] ?? ''),
        );
    }

    return pg_settings_fold($rows);
};

// A number box with its unit after it.
$booking_number = function ($name, $id, $label, $value, $unit, $min, $max, $help = '') {
    return '
                    <div class="pg-f-xs">
                        <label for="' . $id . '" class="form-label">' . $label . '</label>
                        <label class="form-control form-adorn form-adorn-end">
                            <span class="form-adorn-text" aria-hidden="true">' . $unit . '</span>
                            <input type="number" name="' . $name . '" id="' . $id . '" class="form-ghost text-end" value="' . (int) $value . '" min="' . (int) $min . '" max="' . (int) $max . '" inputmode="numeric"/>
                        </label>' . (($help !== '') ? '
                        <div class="form-text">' . $help . '</div>' : '') . '
                    </div>';
};

// A switch with its label and description; $form joins a drawer's switch to
// the settings form.
$booking_switch = function ($name, $id, $label, $checked, $help = '', $form = '') {
    return '
                    <div class="col-12">
                        <div class="d-flex align-items-center gap-3">
                            <input type="checkbox" class="pg-switch" value="1"' . ($checked ? ' checked="checked"' : '') . ' id="' . $id . '" name="' . $name . '"' . (($form !== '') ? ' form="' . h($form) . '"' : '') . (($help !== '') ? ' aria-describedby="' . $id . '_help"' : '') . '/>
                            <label class="pg-text-2" for="' . $id . '">' . $label . '</label>
                        </div>' . (($help !== '') ? '
                        <div class="form-text" id="' . $id . '_help">' . $help . '</div>' : '') . '
                    </div>';
};

if (!$booking_ready) {

    $booking_note = '
                    <div class="col-12">
                        <div class="alert theme-secondary mb-0 py-3 small">' . $booking_not_ready . '</div>
                    </div>';

    $pg_settings_cards[] = $booking_fold(array(
        'services' => array($booking_not_ready, $booking_note),
        'staff'    => array($booking_not_ready, $booking_note),
        'policy'   => array($booking_not_ready, $booking_note),
        'messages' => array($booking_not_ready, $booking_note),
        'texts'    => array($booking_not_ready, $booking_note),
    ));

    return;
}

// A drawer sits outside the settings form (the dialog's drawer host, or the
// page's Bootstrap drawer after the form), so its boxes and its Save join the
// form by its id.
$booking_in_dialog = !empty($pg_settings_in_dialog);
$booking_form = $booking_in_dialog ? 'pg_settings_modal_form' : 'pg_settings_page_form';
$booking_joins = ' form="' . h($booking_form) . '"';

$booking_services = pg_booking_settings_services();
$booking_staff = pg_booking_settings_staff();
$booking_policy = pg_booking_policy();
$booking_texts = pg_booking_texts();
$booking_lines = pg_booking_settings_fold_lines($booking_services, $booking_staff, $booking_policy, $booking_texts);
$booking_separators = pg_number_separators();
$booking_currency = defined('BASE_CURRENCY_CODE') ? BASE_CURRENCY_CODE : '';
$booking_today = date('Y-m-d');

// The deposit boxes come with the 11.0.169 columns. The deposit is charged as
// an order, so with the store off they are drawn disabled: a disabled box is
// not posted and the stored deposit stays.
$booking_deposit_ready = function_exists('pg_booking_deposit_ready') && pg_booking_deposit_ready();
$booking_deposit_on = defined('ECOMMERCE') && (ECOMMERCE == true);
$booking_deposit_products = $booking_deposit_ready ? pg_booking_settings_deposit_products() : array();

// The deposit box and the product its order line names.
$booking_deposit_fields = function ($name, $id, $service) use ($booking_separators, $booking_currency, $booking_deposit_products, $booking_deposit_on, $booking_joins) {

    $product_id = (int) ($service['deposit_product_id'] ?? 0);
    $off = $booking_deposit_on ? '' : ' disabled="disabled"';
    $options = '<option value="0">' . h(lang('No product')) . '</option>';

    foreach ($booking_deposit_products as $option_id => $option_name) {
        $options .= '<option value="' . (int) $option_id . '"' . (($option_id === $product_id) ? ' selected="selected"' : '') . '>' . h($option_name) . '</option>';
    }

    return '
                                <div class="pg-f-xs">
                                    <label for="' . $id . '_deposit" class="form-label">' . lang('Deposit') . '</label>
                                    <label class="form-control form-adorn form-adorn-end">
                                        <span class="form-adorn-text" aria-hidden="true">' . h($booking_currency) . '</span>
                                        <input type="text" name="' . $name . '[deposit]"' . $booking_joins . ' id="' . $id . '_deposit" class="form-ghost text-end" value="' . h(pg_booking_settings_price_text((int) ($service['deposit'] ?? 0), $booking_separators)) . '" maxlength="16" inputmode="decimal"' . $off . '/>
                                    </label>
                                </div>
                                <div class="pg-f-md">
                                    <label for="' . $id . '_deposit_product" class="form-label">' . lang('Deposit product') . '</label>
                                    <select name="' . $name . '[deposit_product]"' . $booking_joins . ' id="' . $id . '_deposit_product" class="form-control"' . $off . '>' . $options . '</select>
                                </div>
                                <div class="col-12">
                                    <div class="form-text mt-0">' . ($booking_deposit_on
                                        ? lang('The deposit is paid in advance as a one-line order of this product. A deposit of 0 asks for none.')
                                        : lang('Deposits are taken as an order: they wait while Commerce is off.')) . '</div>
                                </div>';
};

// ── Services ──
// One list row per service, each edited in its drawer; "Add a service" opens
// an empty drawer whose typed name adds a service on save. A service is never
// deleted here: bookings point at it, so one that is no longer offered is
// switched off. The order is the rows' order, rewritten by Move up and Move
// down.
$booking_service_fields = function ($key, $service) use ($booking_separators, $booking_currency, $booking_deposit_ready, $booking_deposit_fields, $booking_switch, $booking_joins, $booking_form) {

    $name = 'booking_service[' . $key . ']';
    $id = 'booking_service_' . $key;
    $f = $booking_joins;

    return '
                            <div class="row gy-5">
                                <div class="col-12">
                                    <label for="' . $id . '_name" class="form-label">' . lang('Name') . '</label>
                                    <input type="text" name="' . $name . '[name]"' . $f . ' id="' . $id . '_name" class="form-control" value="' . h($service['name']) . '" maxlength="100"/>
                                </div>
                                <div class="pg-f-xs">
                                    <label for="' . $id . '_length" class="form-label">' . lang('Duration') . '</label>
                                    <label class="form-control form-adorn form-adorn-end">
                                        <span class="form-adorn-text" aria-hidden="true">' . lang('min') . '</span>
                                        <input type="number" name="' . $name . '[length]"' . $f . ' id="' . $id . '_length" class="form-ghost text-end" value="' . (int) $service['length_minutes'] . '" min="5" max="1440" step="5" inputmode="numeric"/>
                                    </label>
                                </div>
                                <div class="pg-f-xs">
                                    <label for="' . $id . '_before" class="form-label">' . lang('Break before') . '</label>
                                    <label class="form-control form-adorn form-adorn-end">
                                        <span class="form-adorn-text" aria-hidden="true">' . lang('min') . '</span>
                                        <input type="number" name="' . $name . '[before]"' . $f . ' id="' . $id . '_before" class="form-ghost text-end" value="' . (int) $service['buffer_before'] . '" min="0" max="480" inputmode="numeric"/>
                                    </label>
                                </div>
                                <div class="pg-f-xs">
                                    <label for="' . $id . '_after" class="form-label">' . lang('Break after') . '</label>
                                    <label class="form-control form-adorn form-adorn-end">
                                        <span class="form-adorn-text" aria-hidden="true">' . lang('min') . '</span>
                                        <input type="number" name="' . $name . '[after]"' . $f . ' id="' . $id . '_after" class="form-ghost text-end" value="' . (int) $service['buffer_after'] . '" min="0" max="480" inputmode="numeric"/>
                                    </label>
                                </div>
                                <div class="col-12">
                                    <div class="form-text mt-0">' . lang('The breaks keep bookings apart: no other booking starts within them. They do not shorten the working hours. A service that is no longer offered is switched off rather than deleted, so its bookings keep their service.') . '</div>
                                </div>
                                <div class="pg-f-xs">
                                    <label for="' . $id . '_price" class="form-label">' . lang('Price') . '</label>
                                    <label class="form-control form-adorn form-adorn-end">
                                        <span class="form-adorn-text" aria-hidden="true">' . h($booking_currency) . '</span>
                                        <input type="text" name="' . $name . '[price]"' . $f . ' id="' . $id . '_price" class="form-ghost text-end" value="' . h(pg_booking_settings_price_text($service['price'], $booking_separators)) . '" maxlength="16" inputmode="decimal"/>
                                    </label>
                                </div>' . ($booking_deposit_ready ? $booking_deposit_fields($name, $id, $service) : '') . '
                                <div class="col-12">
                                    <label for="' . $id . '_description" class="form-label">' . lang('Description') . '</label>
                                    <input type="text" name="' . $name . '[description]"' . $f . ' id="' . $id . '_description" class="form-control" value="' . h($service['description']) . '" maxlength="255"/>
                                </div>' . $booking_switch($name . '[enabled]', $id . '_enabled', lang('Can be booked'), !empty($service['enabled']), '', $booking_form) . '
                            </div>';
};

// A drawer's foot: the pane's Save, joined to the form.
$booking_drawer_foot = '<button type="submit"' . $booking_joins . ' class="pg-button pg-button-fill">' . h(lang('Save')) . '</button>';

$output_booking_services = '';
$booking_next_order = 0;
$booking_service_count = count($booking_services);
$booking_position = 0;

foreach ($booking_services as $service_id => $service) {

    $service_id = (int) $service_id;
    $name = 'booking_service[' . $service_id . ']';
    $drawer = pg_booking_settings_service_drawer($service_id);
    $first = ($booking_position === 0);
    $last = ($booking_position === $booking_service_count - 1);
    $booking_position++;
    $booking_next_order = max($booking_next_order, (int) $service['sort_order'] + 1);

    // Move up and Move down submit the form once the script has rewritten
    // every row's order: the pane's Save, as a drawer's Save is.
    $output_booking_services .= '
                        <div class="pg-list-row" data-pg-booking-service>
                            <span class="pg-icon-tile pg-tone-shop" aria-hidden="true"><span class="bi bi-calendar2-check"></span></span>
                            <div class="pg-list-row-main">
                                <div class="pg-list-row-head"><span class="pg-list-row-title">' . h($service['name']) . '</span></div>
                                <div class="pg-list-row-text">' . h(pg_booking_settings_service_line($service, $booking_separators, $booking_currency)) . '</div>
                            </div>
                            <div class="d-flex align-items-center gap-1 align-self-center">
                                <button type="button" class="pg-button pg-button-quiet pg-button-sm" data-bs-toggle="drawer" data-bs-target="#' . $drawer . '" aria-controls="' . $drawer . '" aria-label="' . h(lang(array('string' => '{var:1}: {var:2}', 'vars' => array(lang('Edit'), $service['name'])))) . '"><span class="pg-btn-label">' . h(lang('Edit')) . '</span></button>
                                <span class="pg-menu-wrap">
                                    <button type="button" class="pg-icon-button pg-icon-button-sm" data-bs-toggle="menu" aria-expanded="false" aria-label="' . h(lang(array('string' => '{var:1}: {var:2}', 'vars' => array(lang('More actions'), $service['name'])))) . '"><i class="bi bi-three-dots" aria-hidden="true"></i></button>
                                    <ul class="menu pg-menu-end">
                                        <li><button type="submit" class="menu-item" data-pg-booking-move="up"' . ($first ? ' disabled="disabled"' : '') . '><i class="bi bi-arrow-up me-3" aria-hidden="true"></i>' . h(lang('Move up')) . '</button></li>
                                        <li><button type="submit" class="menu-item" data-pg-booking-move="down"' . ($last ? ' disabled="disabled"' : '') . '><i class="bi bi-arrow-down me-3" aria-hidden="true"></i>' . h(lang('Move down')) . '</button></li>
                                    </ul>
                                </span>
                            </div>
                            <input type="hidden" name="' . $name . '[order]" value="' . (int) $service['sort_order'] . '" data-pg-booking-order/>
                        </div>';

    $pg_settings_modals[] = pg_settings_drawer($drawer, h($service['name']), $booking_service_fields($service_id, $service), $booking_in_dialog, $booking_drawer_foot);
}

$pg_settings_modals[] = pg_settings_drawer(pg_booking_settings_service_drawer('new'), h(lang('Add a service')), $booking_service_fields('new', array(
    'name' => '', 'description' => '', 'length_minutes' => 30, 'buffer_before' => 0, 'buffer_after' => 0,
    'price' => 0, 'deposit' => 0, 'deposit_product_id' => 0, 'enabled' => 1,
)), $booking_in_dialog, $booking_drawer_foot);

$output_booking_services = '
                    <div class="col-12">' . (($output_booking_services !== '')
                        ? '
                        <div data-pg-booking-services>' . $output_booking_services . '
                        </div>'
                        : '
                        <div class="pg-text-meta">' . h(lang('No service yet. Add one, then choose the staff who take it.')) . '</div>') . '
                        <input type="hidden" name="booking_service[new][order]" value="' . (int) $booking_next_order . '"/>
                    </div>';

$booking_add_service = '<button type="button" class="pg-button pg-button-quiet pg-button-sm" data-bs-toggle="drawer" data-bs-target="#' . pg_booking_settings_service_drawer('new') . '" aria-controls="' . pg_booking_settings_service_drawer('new') . '"><i class="bi bi-plus-lg" aria-hidden="true"></i><span class="pg-btn-label">' . h(lang('Add a service')) . '</span></button>';

// ── Staff ──
// One list row per staff member (their letters, name and one line), each
// edited in its drawer: the switch, their services, the weekly hours as two
// ranges a day and the dated exceptions as ranges of days. "Add a staff
// member" opens a drawer with the picker and the weekly hours, prefilled
// from the picked user's working days. The week starts on the day the panel
// language starts it.
$booking_day_names = array(1 => lang('Monday'), 2 => lang('Tuesday'), 3 => lang('Wednesday'), 4 => lang('Thursday'), 5 => lang('Friday'), 6 => lang('Saturday'), 7 => lang('Sunday'));
$booking_week = pg_booking_settings_week_days(pg_list_period_week_first_day());
$booking_service_ids = array_map('intval', array_keys($booking_services));

// The weekly hours grid of $name (booking_staff[<user id>] or
// booking_staff[new]); $fields as pg_booking_settings_hours_fields().
$booking_hours_grid = function ($name, $fields, $caption) use ($booking_week, $booking_day_names, $booking_joins) {

    $labels = array(lang('Opens'), lang('Closes'), lang('Opens again'), lang('Closes again'));
    $grid = '';

    foreach ($booking_week as $day) {

        $cells = '';

        foreach (array('a', 'b', 'c', 'd') as $position => $box) {
            $cells .= '
                                            <td data-label="' . h($labels[$position]) . '"><input type="time" data-pg-time="hh:mm" name="' . $name . '[h' . $day . $box . ']"' . $booking_joins . ' class="form-control form-control-sm" value="' . h($fields[$day][$position]) . '" aria-label="' . h($booking_day_names[$day] . ', ' . $labels[$position]) . '"/></td>';
        }

        $grid .= '
                                        <tr data-pg-booking-day="' . $day . '">
                                            <th scope="row" class="fw-normal">' . $booking_day_names[$day] . '</th>' . $cells . '
                                        </tr>';
    }

    return '
                                <div class="col-12">
                                    <h3 class="pg-group-label">' . lang('Weekly hours') . '</h3>
                                    <table class="table table-hover mb-0 pg-embedded-table pg-booking-hours">
                                        <caption class="visually-hidden">' . h($caption) . '</caption>
                                        <thead><tr><th scope="col">' . lang('Day') . '</th><th scope="col">' . $labels[0] . '</th><th scope="col">' . $labels[1] . '</th><th scope="col">' . $labels[2] . '</th><th scope="col">' . $labels[3] . '</th></tr></thead>
                                        <tbody>' . $grid . '
                                        </tbody>
                                    </table>
                                    <div class="pg-embedded-table-foot">
                                        <button type="button" class="pg-button pg-button-quiet pg-button-sm" data-pg-booking-copy><i class="bi bi-copy" aria-hidden="true"></i>' . lang('Copy the first day to every day') . '</button>
                                    </div>
                                </div>';
};

// One exception row.
$booking_exception_row = function ($user_id, $row) use ($booking_joins) {

    $name = 'booking_staff[' . (int) $user_id . ']';

    return '
                                        <tr>
                                            <td data-label="' . h(lang('First day')) . '"><input type="date" data-pg-date name="' . $name . '[x_from][]"' . $booking_joins . ' class="form-control form-control-sm" value="' . h($row['from']) . '" aria-label="' . h(lang('First day')) . '"/></td>
                                            <td data-label="' . h(lang('Last day')) . '"><input type="date" data-pg-date name="' . $name . '[x_to][]"' . $booking_joins . ' class="form-control form-control-sm" value="' . h($row['to']) . '" aria-label="' . h(lang('Last day')) . '"/></td>
                                            <td data-label="' . h(lang('Opens')) . '"><input type="time" data-pg-time="hh:mm" name="' . $name . '[x_open][]"' . $booking_joins . ' class="form-control form-control-sm" value="' . h($row['open']) . '" aria-label="' . h(lang('Opens')) . '"/></td>
                                            <td data-label="' . h(lang('Closes')) . '"><input type="time" data-pg-time="hh:mm" name="' . $name . '[x_close][]"' . $booking_joins . ' class="form-control form-control-sm" value="' . h($row['close']) . '" aria-label="' . h(lang('Closes')) . '"/></td>
                                        </tr>';
};

// The time zone the hours are read in, and where public holidays are kept.
$booking_zone_line = '
                                <div class="col-12">
                                    <div class="form-text mt-0">' . lang(array(
                                        'string' => 'Hours are in the site\'s time zone, {var:1}. A day left empty is a day off. Busy times on the staff member\'s Workspace calendar are not offered.',
                                        'vars'   => h(date_default_timezone_get()))) . '</div>
                                </div>';

$booking_holidays_link = '
                                <div class="col-12">
                                    <a href="#settings/workspace_team/pgset-ws-calendar" class="small">' . h(lang('Holidays are kept in Team and working hours')) . '</a>
                                </div>';

$output_booking_staff = '';
$booking_letter_tones = array('', ' pg-initials-2', ' pg-initials-3');

foreach ($booking_staff as $user_id => $member) {

    $user_id = (int) $user_id;
    $name = 'booking_staff[' . $user_id . ']';
    $id = 'booking_staff_' . $user_id;
    $drawer = pg_booking_settings_member_drawer($user_id);
    $chosen = pg_booking_settings_service_ids(pg_booking_json($member['service_ids']), $booking_service_ids);

    $options = '';

    foreach ($booking_services as $service_id => $service) {
        $options .= '<option value="' . (int) $service_id . '"' . (in_array((int) $service_id, $chosen, true) ? ' selected="selected"' : '') . '>' . h($service['name']) . '</option>';
    }

    $exceptions = '';

    foreach (pg_booking_settings_exception_rows($member['exceptions'], $booking_today) as $row) {
        $exceptions .= $booking_exception_row($user_id, $row);
    }

    $exceptions .= $booking_exception_row($user_id, array('from' => '', 'to' => '', 'open' => '', 'close' => ''));

    // The seen marker stays in the pane, so a save tells a member drawn
    // with every switch off from one that was not drawn.
    $output_booking_staff .= '
                        <div class="pg-list-row" data-pg-booking-member>
                            <span class="pg-initials' . $booking_letter_tones[$user_id % 3] . '" aria-hidden="true">' . h(pg_initials($member['first_name'], $member['last_name'], (string) $member['user_username'])) . '</span>
                            <div class="pg-list-row-main">
                                <div class="pg-list-row-head"><span class="pg-list-row-title">' . h($member['person']) . '</span></div>
                                <div class="pg-list-row-text">' . h(pg_booking_settings_staff_line($member, $booking_service_ids)) . '</div>
                            </div>
                            <div class="d-flex align-items-center gap-1 align-self-center">
                                <button type="button" class="pg-button pg-button-quiet pg-button-sm" data-bs-toggle="drawer" data-bs-target="#' . $drawer . '" aria-controls="' . $drawer . '" aria-label="' . h(lang(array('string' => '{var:1}: {var:2}', 'vars' => array(lang('Edit'), $member['person'])))) . '"><span class="pg-btn-label">' . h(lang('Edit')) . '</span></button>
                            </div>
                            <input type="hidden" name="' . $name . '[seen]" value="1"/>
                        </div>';

    $pg_settings_modals[] = pg_settings_drawer($drawer, h($member['person']), '
                            <div class="row gy-5" data-pg-booking-staff>' . $booking_switch($name . '[enabled]', $id . '_enabled', lang('Takes bookings'), !empty($member['enabled']), '', $booking_form) . '
                                <div class="col-12">
                                    <label for="' . $id . '_services" class="form-label">' . lang('Bookable services') . '</label>
                                    <select multiple data-pg-combobox name="' . $name . '[services][]"' . $booking_joins . ' id="' . $id . '_services" class="form-control" data-placeholder="' . h(lang('Choose the services')) . '">' . $options . '</select>
                                </div>' . $booking_hours_grid($name, pg_booking_settings_hours_fields($member['hours']), lang(array('string' => 'Weekly hours of {var:1}', 'vars' => $member['person']))) . '
                                <div class="col-12">
                                    <h3 class="pg-group-label">' . lang('Days off and special hours') . '</h3>
                                    <table class="table table-hover mb-0 pg-embedded-table pg-booking-hours">
                                        <caption class="visually-hidden">' . h(lang(array('string' => 'Days off and special hours of {var:1}', 'vars' => $member['person']))) . '</caption>
                                        <thead><tr><th scope="col">' . lang('First day') . '</th><th scope="col">' . lang('Last day') . '</th><th scope="col">' . lang('Opens') . '</th><th scope="col">' . lang('Closes') . '</th></tr></thead>
                                        <tbody data-pg-booking-exceptions>' . $exceptions . '
                                        </tbody>
                                    </table>
                                    <template data-pg-booking-exception>' . $booking_exception_row($user_id, array('from' => '', 'to' => '', 'open' => '', 'close' => '')) . '</template>
                                    <div class="pg-embedded-table-foot">
                                        <button type="button" class="pg-button pg-button-quiet pg-button-sm" data-pg-booking-add-exception><i class="bi bi-plus-lg" aria-hidden="true"></i>' . lang('Add a row') . '</button>
                                    </div>
                                    <div class="form-text">' . lang('Without hours the days are off; with hours they replace the weekly hours on those days. A public holiday is closed unless a row gives it hours. Past days drop off the list.') . '</div>
                                </div>' . $booking_zone_line . $booking_holidays_link . '
                            </div>', $booking_in_dialog, $booking_drawer_foot);
}

$booking_candidates = pg_booking_settings_candidates($booking_staff);
$booking_add_staff = '';

if ($booking_candidates) {

    // Each user carries the hours the grid takes when they are picked: their
    // own working days, else the company's week, else Monday to Friday.
    $booking_workweek = pg_booking_settings_workweek();
    $booking_own_days = pg_booking_settings_own_workdays(array_keys($booking_candidates));
    $booking_add_fields = pg_booking_settings_hours_fields(pg_booking_settings_new_member_hours(0, $booking_workweek));
    $options = '<option value="0">' . lang('-Not selected-') . '</option>';

    foreach ($booking_candidates as $user_id => $person) {
        $prefill = pg_booking_settings_hours_fields(pg_booking_settings_new_member_hours($booking_own_days[(int) $user_id] ?? 0, $booking_workweek));
        $options .= '<option value="' . (int) $user_id . '" data-pg-booking-hours="' . h(json_encode($prefill)) . '">' . h($person) . '</option>';
    }

    $booking_add_drawer = pg_booking_settings_member_drawer('new');

    $pg_settings_modals[] = pg_settings_drawer($booking_add_drawer, h(lang('Add a staff member')), '
                            <div class="row gy-5" data-pg-booking-staff>
                                <div class="col-12">
                                    <label for="booking_staff_add" class="form-label">' . lang('Panel user') . '</label>
                                    <select name="booking_staff_add"' . $booking_joins . ' id="booking_staff_add" class="form-control" data-pg-combobox data-pg-booking-pick>' . $options . '</select>
                                    <div class="form-text">' . lang('The hours below follow the user\'s working days in Team and working hours. Saving adds them with every service that can be booked and these hours.') . '</div>
                                </div>' . $booking_hours_grid('booking_staff[new]', $booking_add_fields, lang('Weekly hours')) . $booking_zone_line . $booking_holidays_link . '
                            </div>', $booking_in_dialog, $booking_drawer_foot);

    $booking_add_staff = '<button type="button" class="pg-button pg-button-quiet pg-button-sm" data-bs-toggle="drawer" data-bs-target="#' . $booking_add_drawer . '" aria-controls="' . $booking_add_drawer . '"><i class="bi bi-plus-lg" aria-hidden="true"></i><span class="pg-btn-label">' . h(lang('Add a staff member')) . '</span></button>';
}

$output_booking_staff = '
                    <div class="col-12">' . (($output_booking_staff !== '')
                        ? '
                        <div data-pg-booking-members>' . $output_booking_staff . '
                        </div>'
                        : '
                        <div class="pg-text-meta">' . h($booking_candidates
                            ? lang('Nobody takes bookings yet. Add a staff member, then choose their services and hours.')
                            : lang('There is no panel user to add.')) . '</div>') . '
                        <div class="d-none">' . get_date_picker_format() . pg_fields_script() . '</div>
                    </div>';

// Copy the first day of the grid to every day, add an exception row, fill
// the add drawer's grid for the picked user, and move a service up or down.
// Bound once on the document: the pane's markup is replaced on every visit.
$pg_settings_scripts[] = '
    <script>
        (function () {
            if (window.pgBookingSettingsBound) {
                return;
            }

            window.pgBookingSettingsBound = true;

            function setValue(input, value) {
                if (window.pgDateTimeField && input.hasAttribute("data-pg-time")) {
                    window.pgDateTimeField.set(input, value);
                } else {
                    input.value = value;
                    input.dispatchEvent(new Event("change", {bubbles: true}));
                }
            }

            // A user picked in the add drawer fills its grid with the hours
            // their option carries. Listened to through jQuery: a combobox
            // may announce a pick only there.
            jQuery(document).on("change", "[data-pg-booking-pick]", function () {
                var option = this.options[this.selectedIndex];
                var block = this.closest("[data-pg-booking-staff]");
                var hours = (option && option.getAttribute("data-pg-booking-hours")) ? JSON.parse(option.getAttribute("data-pg-booking-hours")) : null;

                if (!block || !hours) {
                    return;
                }

                block.querySelectorAll("[data-pg-booking-day]").forEach(function (row) {
                    var day = hours[row.getAttribute("data-pg-booking-day")] || ["", "", "", ""];

                    row.querySelectorAll("input[data-pg-time]").forEach(function (input, position) {
                        setValue(input, day[position] || "");
                    });
                });
            });

            document.addEventListener("click", function (event) {

                // Move up and Move down: the row changes place, the order of
                // every row is written again from the top, and the button
                // then submits the form.
                var move = event.target.closest("[data-pg-booking-move]");

                if (move) {
                    var service = move.closest("[data-pg-booking-service]");
                    var up = move.getAttribute("data-pg-booking-move") === "up";
                    var other = service ? (up ? service.previousElementSibling : service.nextElementSibling) : null;

                    if (!other || !other.hasAttribute("data-pg-booking-service")) {
                        event.preventDefault();
                        return;
                    }

                    service.parentNode.insertBefore(up ? service : other, up ? other : service);

                    service.parentNode.querySelectorAll("[data-pg-booking-order]").forEach(function (input, position) {
                        input.value = String(position);
                    });
                    return;
                }

                var copy = event.target.closest("[data-pg-booking-copy]");
                var add = event.target.closest("[data-pg-booking-add-exception]");
                var block = (copy || add) ? (copy || add).closest("[data-pg-booking-staff]") : null;

                if (!block) {
                    return;
                }

                if (copy) {
                    var rows = block.querySelectorAll("[data-pg-booking-day]");
                    var first = rows[0].querySelectorAll("input[data-pg-time]");

                    for (var i = 1; i < rows.length; i++) {
                        rows[i].querySelectorAll("input[data-pg-time]").forEach(function (input, position) {
                            setValue(input, first[position].value);
                        });
                    }
                    return;
                }

                var body = block.querySelector("[data-pg-booking-exceptions]");
                var template = block.querySelector("template[data-pg-booking-exception]");
                var row = template.content.firstElementChild.cloneNode(true);

                body.appendChild(row);

                if (window.pgFields) {
                    window.pgFields.init(row);
                }

                var focus = row.querySelector("input:not([type=hidden])");

                if (focus) {
                    focus.focus();
                }
            });
        })();
    </script>';

// ── Booking rules ──

$booking_step_options = '';

foreach (array(0, 5, 10, 15, 20, 30, 45, 60, 90, 120) as $step) {
    $booking_step_options .= '<option value="' . $step . '"' . (($booking_policy['step_minutes'] === $step) ? ' selected="selected"' : '') . '>' . (($step === 0) ? lang('The length of the service') : lang(array('string' => 'Every {var:1} minutes', 'vars' => $step))) . '</option>';
}

$output_booking_policy =
    $booking_number('booking_lead_minutes', 'booking_lead_minutes', lang('Lead time'), $booking_policy['lead_minutes'], lang('min'), 0, 43200,
        lang('The earliest a booking may start, counted from now.'))
    . $booking_number('booking_horizon_days', 'booking_horizon_days', lang('Booking horizon'), $booking_policy['horizon_days'], lang('day(s)'), 1, 730,
        lang('How far ahead times are offered.'))
    . $booking_number('booking_hold_minutes', 'booking_hold_minutes', lang('Hold'), $booking_policy['hold_minutes'], lang('min'), 2, 60,
        lang('How long a chosen time is kept for the visitor while the details are typed.'))
    . $booking_number('booking_cancel_hours', 'booking_cancel_hours', lang('Cancellation window'), $booking_policy['cancel_hours'], lang('hour(s)'), 0, 720,
        lang('The visitor may cancel up to this long before the start. 0 allows it until the start.')) . '
                    <div class="pg-f-md">
                        <label for="booking_step_minutes" class="form-label">' . lang('Times offered') . '</label>
                        <select name="booking_step_minutes" id="booking_step_minutes" class="form-control">' . $booking_step_options . '</select>
                        <div class="form-text">' . lang('The distance between the start times a visitor can choose.') . '</div>
                    </div>
                    <div class="pg-f-sm">
                        <label for="booking_reminder_hours" class="form-label">' . lang('Reminders') . '</label>
                        <label class="form-control form-adorn form-adorn-end">
                            <span class="form-adorn-text" aria-hidden="true">' . lang('hours before') . '</span>
                            <input type="text" name="booking_reminder_hours" id="booking_reminder_hours" class="form-ghost" value="' . h(implode(', ', $booking_policy['reminder_hours'])) . '" maxlength="20" spellcheck="false" autocomplete="off" aria-describedby="booking_reminder_hours_help"/>
                        </label>
                        <div class="form-text" id="booking_reminder_hours_help">' . lang('Up to three, separated by commas, each between 1 and 168, such as 24, 2. Empty sends none. Whether a mail or a text goes is shown under Messages.') . '</div>
                    </div>'
    . $booking_switch('booking_reschedule', 'booking_reschedule', lang('Visitors may move their booking'), $booking_policy['reschedule'],
        lang('The link in the confirmation offers another time within the cancellation window.'))
    . $booking_switch('booking_auto_confirm', 'booking_auto_confirm', lang('Confirm bookings at once'), $booking_policy['auto_confirm'],
        lang('Off, a booking made on the site waits as a request until staff confirm it; the time stays taken meanwhile.'));


// ── Messages ──
// Which booking mails and texts go: a switched-on campaign profile with a
// booking action, a switched-on SMS event. Each is set on its own screen;
// this row only shows it and posts nothing.
$booking_mail_actions = db_values(
    "SELECT DISTINCT action FROM email_campaign_profiles
    WHERE (enabled = '1') AND (action_item_id = '0')
        AND (action IN ('" . implode("', '", array_map('e', pg_booking_mail_actions())) . "'))");

$booking_sms_events = array();

if (function_exists('pg_sms_message_settings')) {
    foreach (pg_sms_message_settings() as $event => $row) {
        if (!empty($row['on'])) {
            $booking_sms_events[] = $event;
        }
    }
}

$booking_messages = pg_booking_settings_messages(is_array($booking_mail_actions) ? $booking_mail_actions : array(), $booking_sms_events, $booking_policy['reminder_hours']);

$booking_message_faces = array(
    'mails' => array('bi-envelope', 'blue', OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_email_campaign_profiles.php'),
    'texts' => array('bi-chat-dots', 'teal', '#settings/contact/pgset-sms-messages'),
);

$booking_message_rows = '';

foreach ($booking_messages['rows'] as $key => $row) {
    $booking_message_rows .= '
                            <div class="pg-value-row">
                                <span class="pg-icon-tile pg-tone-' . $booking_message_faces[$key][1] . '" aria-hidden="true"><i class="bi ' . $booking_message_faces[$key][0] . '"></i></span>
                                <span class="pg-value-row-text"><span class="pg-value-row-name">' . h($row['name']) . '</span><span class="pg-value-row-line">' . h($row['line']) . '</span></span>
                                <a class="pg-button pg-button-quiet pg-button-sm" href="' . h($booking_message_faces[$key][2]) . '" aria-label="' . h(lang('Change') . ': ' . $row['name']) . '">' . h(lang('Change')) . '</a>
                            </div>';
}

$output_booking_messages = '
                    <div class="col-12">
                        <div class="pg-card-inner">' . $booking_message_rows . '
                        </div>
                        <div class="pg-text-meta mt-2">' . h($booking_messages['meta']) . '</div>
                    </div>';


// ── Booking widget texts ──
$output_booking_texts = '
                    <div class="col-12">
                        <div class="form-text">' . lang('Plain text. A box left empty shows the widget\'s own wording in the visitor\'s language.') . '</div>
                    </div>';

foreach (pg_booking_settings_text_fields() as $key => $field) {

    $value = isset($booking_texts[$key]) ? $booking_texts[$key] : '';

    $output_booking_texts .= ($key === 'title')
        ? '
                    <div class="pg-f-lg">
                        <label for="booking_text_' . $key . '" class="form-label">' . $field[0] . '</label>
                        <input type="text" name="booking_text_' . $key . '" id="booking_text_' . $key . '" class="form-control" value="' . h($value) . '" maxlength="' . (int) $field[1] . '"/>
                    </div>'
        : '
                    <div class="col-12">
                        <label for="booking_text_' . $key . '" class="form-label">' . $field[0] . '</label>
                        <textarea name="booking_text_' . $key . '" id="booking_text_' . $key . '" class="form-control" rows="2" maxlength="' . (int) $field[1] . '">' . h($value) . '</textarea>
                    </div>';
}


// ── The rows ──
$pg_settings_cards[] = $booking_fold(array(
    'services' => array($booking_lines['services'], $output_booking_services, $booking_add_service),
    'staff'    => array($booking_lines['staff'], $output_booking_staff, $booking_add_staff),
    'policy'   => array($booking_lines['policy'], $output_booking_policy),
    'messages' => array($booking_messages['line'], $output_booking_messages),
    'texts'    => array($booking_lines['texts'], $output_booking_texts),
));
