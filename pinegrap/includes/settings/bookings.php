<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Site Settings - the Bookings cards: the services that can be booked, the
 * staff who take them (their services, weekly hours and dated exceptions),
 * the booking rules and the booking widget's texts.
 *
 * Fills $pg_settings_cards, one grid column per card, in reading order. Which
 * cards belong here is said once, in includes/settings/registry.php; this file
 * holds them, and bookings.save.php writes what they edit. The rows and the
 * two config_text entries are read through includes/fn/booking_settings.php.
 *
 * Every staff member's block posts booking_staff[<user id>][seen], so the save
 * tells a block that was drawn with every switch off from one that was not
 * drawn at all.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_SETTINGS_ENTRY')) {
    exit;
}

// The tables come with the 2026.11.0 upgrade (11.0.157); before it every
// card says so and draws no control the save would ignore.
$booking_ready = function_exists('pg_booking_ready') && pg_booking_ready();

$booking_not_ready = '
                    <div class="col-12">
                        <div class="alert theme-secondary mb-0 py-3 small">' . lang('Bookings arrive with the 2026.11.0 upgrade, which this site has not run yet.') . '</div>
                    </div>';

// One card in the settings' shape; $body is the inside of its row.
$booking_card = function ($id, $title, $body) {
    return '
    <div id="' . $id . '" class="pg-set-card">
        <div class="card">
            <div class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">
                ' . $title . '
            </div>
            <div class="card-body">
                <div class="row gy-5">' . $body . '
                </div>
            </div>
        </div>
    </div>';
};

// A number box with its unit after it.
$booking_number = function ($name, $id, $label, $value, $unit, $min, $max, $help = '') {
    return '
                    <div class="pg-f-xs">
                        <label for="' . $id . '" class="form-label">' . $label . '</label>
                        <label class="form-control form-adorn form-adorn-end">
                            <span class="form-adorn-text" aria-hidden="true">' . $unit . '</span>
                            <input type="number" name="' . $name . '" id="' . $id . '" class="form-ghost" value="' . (int) $value . '" min="' . (int) $min . '" max="' . (int) $max . '" inputmode="numeric" style="text-align: right;"/>
                        </label>' . (($help !== '') ? '
                        <div class="form-text">' . $help . '</div>' : '') . '
                    </div>';
};

// A switch with its label and description.
$booking_switch = function ($name, $id, $label, $checked, $help = '') {
    return '
                    <div class="col-12">
                        <div class="form-field">
                            <div class="switch"><input value="1"' . ($checked ? ' checked="checked"' : '') . ' type="checkbox" id="' . $id . '" name="' . $name . '"/></div>
                            <label for="' . $id . '">' . $label . '</label>' . (($help !== '') ? '
                            <div class="form-text">' . $help . '</div>' : '') . '
                        </div>
                    </div>';
};

if (!$booking_ready) {

    $pg_settings_cards[] = $booking_card('pgset-booking-services', lang('Bookable services'), $booking_not_ready);
    $pg_settings_cards[] = $booking_card('pgset-booking-staff', lang('Staff'), $booking_not_ready);
    $pg_settings_cards[] = $booking_card('pgset-booking-policy', lang('Booking rules'), $booking_not_ready);
    $pg_settings_cards[] = $booking_card('pgset-booking-texts', lang('Booking widget texts'), $booking_not_ready);

    return;
}

$booking_services = pg_booking_settings_services();
$booking_staff = pg_booking_settings_staff();
$booking_separators = pg_number_separators();
$booking_currency = defined('BASE_CURRENCY_CODE') ? BASE_CURRENCY_CODE : '';
$booking_today = date('Y-m-d');

// The deposit boxes come with the 11.0.169 columns and only while the store
// is on: the deposit is charged as an order.
$booking_deposit_on = defined('ECOMMERCE') && (ECOMMERCE == true)
    && function_exists('pg_booking_deposit_ready') && pg_booking_deposit_ready();
$booking_deposit_products = $booking_deposit_on ? pg_booking_settings_deposit_products() : array();

// The deposit box and the product its order line names.
$booking_deposit_fields = function ($name, $id, $service) use ($booking_separators, $booking_currency, $booking_deposit_products) {

    $product_id = (int) ($service['deposit_product_id'] ?? 0);
    $options = '<option value="0">' . h(lang('No product')) . '</option>';

    foreach ($booking_deposit_products as $option_id => $option_name) {
        $options .= '<option value="' . (int) $option_id . '"' . (($option_id === $product_id) ? ' selected="selected"' : '') . '>' . h($option_name) . '</option>';
    }

    return '
                                <div class="pg-f-xs">
                                    <label for="' . $id . '_deposit" class="form-label">' . lang('Deposit') . '</label>
                                    <label class="form-control form-adorn form-adorn-end">
                                        <span class="form-adorn-text" aria-hidden="true">' . h($booking_currency) . '</span>
                                        <input type="text" name="' . $name . '[deposit]" id="' . $id . '_deposit" class="form-ghost" value="' . h(pg_booking_settings_price_text((int) ($service['deposit'] ?? 0), $booking_separators)) . '" maxlength="16" inputmode="decimal" style="text-align: right;"/>
                                    </label>
                                </div>
                                <div class="pg-f-md">
                                    <label for="' . $id . '_deposit_product" class="form-label">' . lang('Deposit product') . '</label>
                                    <select name="' . $name . '[deposit_product]" id="' . $id . '_deposit_product" class="form-control">' . $options . '</select>
                                    <div class="form-text">' . lang('The deposit is paid in advance as a one-line order of this product. A deposit of 0 asks for none.') . '</div>
                                </div>';
};

// ── Services ──
// One block per service and an empty one at the end; a name typed there
// adds a service on save. A service is never deleted here: bookings point at
// it, so one that is no longer offered is switched off.
$booking_service_block = function ($key, $service, $legend) use ($booking_separators, $booking_currency, $booking_deposit_on, $booking_deposit_fields) {

    $name = 'booking_service[' . $key . ']';
    $id = 'booking_service_' . $key;

    return '
                    <div class="col-12">
                        <fieldset class="border rounded p-5">
                            <legend class="form-label fs-md fw-bold float-none w-auto px-3 mb-0">' . h($legend) . '</legend>
                            <div class="row gy-5">
                                <div class="pg-f-md">
                                    <label for="' . $id . '_name" class="form-label">' . lang('Name') . '</label>
                                    <input type="text" name="' . $name . '[name]" id="' . $id . '_name" class="form-control" value="' . h($service['name']) . '" maxlength="100"/>
                                </div>
                                <div class="pg-f-xs">
                                    <label for="' . $id . '_length" class="form-label">' . lang('Duration') . '</label>
                                    <label class="form-control form-adorn form-adorn-end">
                                        <span class="form-adorn-text" aria-hidden="true">' . lang('min') . '</span>
                                        <input type="number" name="' . $name . '[length]" id="' . $id . '_length" class="form-ghost" value="' . (int) $service['length_minutes'] . '" min="5" max="1440" step="5" inputmode="numeric" style="text-align: right;"/>
                                    </label>
                                </div>
                                <div class="pg-f-xs">
                                    <label for="' . $id . '_before" class="form-label">' . lang('Break before') . '</label>
                                    <label class="form-control form-adorn form-adorn-end">
                                        <span class="form-adorn-text" aria-hidden="true">' . lang('min') . '</span>
                                        <input type="number" name="' . $name . '[before]" id="' . $id . '_before" class="form-ghost" value="' . (int) $service['buffer_before'] . '" min="0" max="480" inputmode="numeric" style="text-align: right;"/>
                                    </label>
                                </div>
                                <div class="pg-f-xs">
                                    <label for="' . $id . '_after" class="form-label">' . lang('Break after') . '</label>
                                    <label class="form-control form-adorn form-adorn-end">
                                        <span class="form-adorn-text" aria-hidden="true">' . lang('min') . '</span>
                                        <input type="number" name="' . $name . '[after]" id="' . $id . '_after" class="form-ghost" value="' . (int) $service['buffer_after'] . '" min="0" max="480" inputmode="numeric" style="text-align: right;"/>
                                    </label>
                                </div>
                                <div class="pg-f-xs">
                                    <label for="' . $id . '_price" class="form-label">' . lang('Price') . '</label>
                                    <label class="form-control form-adorn form-adorn-end">
                                        <span class="form-adorn-text" aria-hidden="true">' . h($booking_currency) . '</span>
                                        <input type="text" name="' . $name . '[price]" id="' . $id . '_price" class="form-ghost" value="' . h(pg_booking_settings_price_text($service['price'], $booking_separators)) . '" maxlength="16" inputmode="decimal" style="text-align: right;"/>
                                    </label>
                                </div>' . ($booking_deposit_on ? $booking_deposit_fields($name, $id, $service) : '') . '
                                <div class="pg-f-xs">
                                    <label for="' . $id . '_order" class="form-label">' . lang('Display order') . '</label>
                                    <input type="number" name="' . $name . '[order]" id="' . $id . '_order" class="form-control" value="' . (int) $service['sort_order'] . '" min="0" max="65535" inputmode="numeric"/>
                                </div>
                                <div class="col-12">
                                    <label for="' . $id . '_description" class="form-label">' . lang('Description') . '</label>
                                    <input type="text" name="' . $name . '[description]" id="' . $id . '_description" class="form-control" value="' . h($service['description']) . '" maxlength="255"/>
                                </div>
                                <div class="col-12">
                                    <div class="form-field">
                                        <div class="switch"><input value="1"' . (!empty($service['enabled']) ? ' checked="checked"' : '') . ' type="checkbox" id="' . $id . '_enabled" name="' . $name . '[enabled]"/></div>
                                        <label for="' . $id . '_enabled">' . lang('Can be booked') . '</label>
                                    </div>
                                </div>
                            </div>
                        </fieldset>
                    </div>';
};

$output_booking_services = '
                    <div class="col-12">
                        <div class="form-text">' . lang('The breaks keep bookings apart: no other booking starts within them. They do not shorten the working hours. A service that is no longer offered is switched off rather than deleted, so its bookings keep their service.') . '</div>
                    </div>';

$booking_next_order = 0;

foreach ($booking_services as $service_id => $service) {
    $output_booking_services .= $booking_service_block((int) $service_id, $service, $service['name']);
    $booking_next_order = max($booking_next_order, (int) $service['sort_order'] + 1);
}

$output_booking_services .= $booking_service_block('new', array(
    'name' => '', 'description' => '', 'length_minutes' => 30, 'buffer_before' => 0, 'buffer_after' => 0,
    'price' => 0, 'deposit' => 0, 'deposit_product_id' => 0, 'enabled' => 1, 'sort_order' => $booking_next_order,
), lang('New service'));

$output_booking_services .= '
                    <div class="col-12">
                        <div class="form-text">' . lang('Type a name in New service and save to add it.') . '</div>
                    </div>';

$pg_settings_cards[] = $booking_card('pgset-booking-services', lang('Bookable services'), $output_booking_services);

// ── Staff ──
// Each staff member: the switch, their services, the weekly hours as two
// ranges a day, and the dated exceptions as ranges of days. The week starts
// on the day the panel language starts it.
$booking_day_names = array(1 => lang('Monday'), 2 => lang('Tuesday'), 3 => lang('Wednesday'), 4 => lang('Thursday'), 5 => lang('Friday'), 6 => lang('Saturday'), 7 => lang('Sunday'));
$booking_week = pg_booking_settings_week_days(pg_list_period_week_first_day());

// One exception row; $index is '' for the template the add button copies.
$booking_exception_row = function ($user_id, $row) {

    $name = 'booking_staff[' . (int) $user_id . ']';

    return '
                                        <tr>
                                            <td><input type="date" data-pg-date name="' . $name . '[x_from][]" class="form-control form-control-sm" value="' . h($row['from']) . '" aria-label="' . h(lang('First day')) . '"/></td>
                                            <td><input type="date" data-pg-date name="' . $name . '[x_to][]" class="form-control form-control-sm" value="' . h($row['to']) . '" aria-label="' . h(lang('Last day')) . '"/></td>
                                            <td><input type="time" data-pg-time="hh:mm" name="' . $name . '[x_open][]" class="form-control form-control-sm" value="' . h($row['open']) . '" aria-label="' . h(lang('Opens')) . '"/></td>
                                            <td><input type="time" data-pg-time="hh:mm" name="' . $name . '[x_close][]" class="form-control form-control-sm" value="' . h($row['close']) . '" aria-label="' . h(lang('Closes')) . '"/></td>
                                        </tr>';
};

$output_booking_staff = '
                    <div class="col-12">
                        <div class="form-text">' . lang(array(
                            'string' => 'Hours are in the site\'s time zone, {var:1}. A day left empty is a day off. Busy times on the staff member\'s Workspace calendar are not offered.',
                            'vars'   => h(date_default_timezone_get()))) . '</div>
                    </div>';

foreach ($booking_staff as $user_id => $member) {

    $name = 'booking_staff[' . (int) $user_id . ']';
    $id = 'booking_staff_' . (int) $user_id;
    $chosen = pg_booking_settings_service_ids(pg_booking_json($member['service_ids']), array_map('intval', array_keys($booking_services)));
    $fields = pg_booking_settings_hours_fields($member['hours']);

    $options = '';

    foreach ($booking_services as $service_id => $service) {
        $options .= '<option value="' . (int) $service_id . '"' . (in_array((int) $service_id, $chosen, true) ? ' selected="selected"' : '') . '>' . h($service['name']) . '</option>';
    }

    $grid = '';

    foreach ($booking_week as $day) {

        $cells = '';

        foreach (array('a', 'b', 'c', 'd') as $position => $box) {
            $labels = array(lang('Opens'), lang('Closes'), lang('Opens again'), lang('Closes again'));
            $cells .= '
                                            <td><input type="time" data-pg-time="hh:mm" name="' . $name . '[h' . $day . $box . ']" class="form-control form-control-sm" value="' . h($fields[$day][$position]) . '" aria-label="' . h($booking_day_names[$day] . ', ' . $labels[$position]) . '"/></td>';
        }

        $grid .= '
                                        <tr data-pg-booking-day="' . $day . '">
                                            <th scope="row" class="fw-normal">' . $booking_day_names[$day] . '</th>' . $cells . '
                                        </tr>';
    }

    $exceptions = '';

    foreach (pg_booking_settings_exception_rows($member['exceptions'], $booking_today) as $row) {
        $exceptions .= $booking_exception_row($user_id, $row);
    }

    $exceptions .= $booking_exception_row($user_id, array('from' => '', 'to' => '', 'open' => '', 'close' => ''));

    $output_booking_staff .= '
                    <div class="col-12">
                        <fieldset class="border rounded p-5" data-pg-booking-staff>
                            <legend class="form-label fs-md fw-bold float-none w-auto px-3 mb-0">' . h($member['person']) . '</legend>
                            <input type="hidden" name="' . $name . '[seen]" value="1"/>
                            <div class="row gy-5">
                                <div class="col-12">
                                    <div class="form-field">
                                        <div class="switch"><input value="1"' . (!empty($member['enabled']) ? ' checked="checked"' : '') . ' type="checkbox" id="' . $id . '_enabled" name="' . $name . '[enabled]"/></div>
                                        <label for="' . $id . '_enabled">' . lang('Takes bookings') . '</label>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <label for="' . $id . '_services" class="form-label">' . lang('Bookable services') . '</label>
                                    <select multiple data-pg-combobox name="' . $name . '[services][]" id="' . $id . '_services" class="form-control" data-placeholder="' . h(lang('Choose the services')) . '">' . $options . '</select>
                                </div>
                                <div class="col-12">
                                    <div class="table-responsive">
                                    <table class="table table-sm align-middle mb-3">
                                        <caption class="visually-hidden">' . h(lang(array('string' => 'Weekly hours of {var:1}', 'vars' => $member['person']))) . '</caption>
                                        <thead><tr><th scope="col">' . lang('Day') . '</th><th scope="col">' . lang('Opens') . '</th><th scope="col">' . lang('Closes') . '</th><th scope="col">' . lang('Opens again') . '</th><th scope="col">' . lang('Closes again') . '</th></tr></thead>
                                        <tbody>' . $grid . '
                                        </tbody>
                                    </table>
                                    </div>
                                    <button type="button" class="btn btn-sm btn-outline theme-secondary" data-pg-booking-copy><i class="bi bi-copy me-1" aria-hidden="true"></i>' . lang('Copy the first day to every day') . '</button>
                                </div>
                                <div class="col-12">
                                    <div class="form-label">' . lang('Days off and special hours') . '</div>
                                    <div class="table-responsive">
                                    <table class="table table-sm align-middle mb-3">
                                        <caption class="visually-hidden">' . h(lang(array('string' => 'Days off and special hours of {var:1}', 'vars' => $member['person']))) . '</caption>
                                        <thead><tr><th scope="col">' . lang('First day') . '</th><th scope="col">' . lang('Last day') . '</th><th scope="col">' . lang('Opens') . '</th><th scope="col">' . lang('Closes') . '</th></tr></thead>
                                        <tbody data-pg-booking-exceptions>' . $exceptions . '
                                        </tbody>
                                    </table>
                                    </div>
                                    <template data-pg-booking-exception>' . $booking_exception_row($user_id, array('from' => '', 'to' => '', 'open' => '', 'close' => '')) . '</template>
                                    <button type="button" class="btn btn-sm btn-outline theme-secondary" data-pg-booking-add-exception><i class="bi bi-plus-lg me-1" aria-hidden="true"></i>' . lang('Add a row') . '</button>
                                    <div class="form-text">' . lang('Without hours the days are off; with hours they replace the weekly hours on those days. A public holiday is closed unless a row gives it hours. Past days drop off the list.') . '</div>
                                </div>
                            </div>
                        </fieldset>
                    </div>';
}

$booking_candidates = pg_booking_settings_candidates($booking_staff);

if ($booking_candidates) {

    $options = '<option value="0">' . lang('-Not selected-') . '</option>';

    foreach ($booking_candidates as $user_id => $person) {
        $options .= '<option value="' . (int) $user_id . '">' . h($person) . '</option>';
    }

    $output_booking_staff .= '
                    <div class="pg-f-md">
                        <label for="booking_staff_add" class="form-label">' . lang('Add a staff member') . '</label>
                        <select name="booking_staff_add" id="booking_staff_add" class="form-control">' . $options . '</select>
                        <div class="form-text">' . lang('Saving adds the panel user with every service that can be booked and Monday to Friday, 09:00 to 17:00; change them after.') . '</div>
                    </div>';

} elseif (!$booking_staff) {
    $output_booking_staff .= '
                    <div class="col-12"><div class="form-text">' . lang('There is no panel user to add.') . '</div></div>';
}

$pg_settings_cards[] = $booking_card('pgset-booking-staff', lang('Staff'), $output_booking_staff . '
                    <div class="d-none">' . get_date_picker_format() . pg_fields_script() . '</div>');

// Copy the first day of the grid to every day, and add an exception row.
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

            document.addEventListener("click", function (event) {

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
$booking_policy = pg_booking_policy();

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
                        <input type="text" name="booking_reminder_hours" id="booking_reminder_hours" class="form-control" value="' . h(implode(', ', $booking_policy['reminder_hours'])) . '" maxlength="20" spellcheck="false" autocomplete="off"/>
                        <div class="form-text">' . lang('Hours before the start a reminder is sent, separated by commas; at most three, between 1 and 168. Empty sends none.') . '</div>
                    </div>'
    . $booking_switch('booking_reschedule', 'booking_reschedule', lang('Visitors may move their booking'), $booking_policy['reschedule'],
        lang('The link in the confirmation offers another time within the cancellation window.'))
    . $booking_switch('booking_auto_confirm', 'booking_auto_confirm', lang('Confirm bookings at once'), $booking_policy['auto_confirm'],
        lang('Off, a booking made on the site waits as a request until staff confirm it; the time stays taken meanwhile.'));

$pg_settings_cards[] = $booking_card('pgset-booking-policy', lang('Booking rules'), $output_booking_policy);

// ── Booking widget texts ──
$booking_texts = pg_booking_texts();
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

$pg_settings_cards[] = $booking_card('pgset-booking-texts', lang('Booking widget texts'), $output_booking_texts);
