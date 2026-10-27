<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The shipping method form, shared by the create and the edit screens
 * (add_shipping_method.php, edit_shipping_method.php): the four field
 * cards, filled from a values array (the defaults of a new method or the
 * stored record), the column values a post is saved with, and the allowed
 * zones and excluded dates written beside it. The screens keep what
 * differs: the edit screen loads the record, offers delete and clears the
 * old links before writing them again.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/**
 * Short day name => long day name, Monday first, as the form lists them.
 *
 * @return array
 */
function pg_shipping_method_form_days()
{
    return array(
        'mon' => 'monday',
        'tue' => 'tuesday',
        'wed' => 'wednesday',
        'thu' => 'thursday',
        'fri' => 'friday',
        'sat' => 'saturday',
        'sun' => 'sunday',
    );
}

/**
 * The form values of a new shipping method: enabled, street address and
 * PO box allowed, handled, shipped and available on weekdays (available on
 * every day), open from today until the end of 2099.
 *
 * @return array  See pg_shipping_method_form_cards()
 */
function pg_shipping_method_form_new_values()
{
    $values = array(
        'name' => '',
        'description' => '',
        'code' => '',
        'carrier_title' => '',
        'carrier_vkn' => '',
        'service' => null,
        'realtime_rate' => false,
        'base_rate' => '',
        'variable_base_rate' => false,
        'primary_weight_rate' => '',
        'primary_weight_rate_first_item_excluded' => false,
        'secondary_weight_rate' => '',
        'secondary_weight_rate_first_item_excluded' => false,
        'item_rate' => '',
        'item_rate_first_item_excluded' => false,
        'street_address' => true,
        'po_box' => true,
        'handle_days' => '',
        'end_of_day' => '',
        'base_transit_days' => '',
        'adjust_transit' => false,
        'transit_on_saturday' => false,
        'transit_on_sunday' => false,
        'status' => true,
        'protected' => false,
        'excluded_transit_dates' => '',
        'start_time' => date('n/j/Y') . ' 12:00 AM',
        'end_time' => (DATE_FORMAT == 'month_day') ? '12/31/2099 11:59 PM' : '31/12/2099 11:59 PM',
    );

    for ($number = 2; $number <= 4; $number++) {
        $values['base_rate_' . $number] = '';
        $values['base_rate_' . $number . '_subtotal'] = '';
    }

    foreach (pg_shipping_method_form_days() as $short => $long) {
        $weekday = !in_array($short, array('sat', 'sun'), true);

        $values['handle_' . $short] = $weekday;
        $values['ship_' . $short] = $weekday;
        $values['available_on_' . $long] = true;
        $values['available_on_' . $long . '_cutoff_time'] = '';
    }

    return $values;
}

/**
 * The form values of a stored shipping method.
 *
 * @param array  $row                     Its shipping_methods row
 * @param string $excluded_transit_dates  Its excluded dates as the form
 *                                        shows them, comma separated
 * @return array  See pg_shipping_method_form_cards()
 */
function pg_shipping_method_form_row_values($row, $excluded_transit_dates)
{
    $values = array(
        'name' => (string) $row['name'],
        'description' => (string) $row['description'],
        'code' => (string) $row['code'],
        'carrier_title' => (string) ($row['carrier_title'] ?? ''),
        'carrier_vkn' => (string) ($row['carrier_vkn'] ?? ''),
        'service' => (string) $row['service'],
        'realtime_rate' => (bool) $row['realtime_rate'],
        'base_rate' => pg_format_input_amount($row['base_rate'] / 100),
        'variable_base_rate' => ($row['variable_base_rate'] == 1),
        'primary_weight_rate' => pg_format_input_amount($row['primary_weight_rate'] / 100),
        'primary_weight_rate_first_item_excluded' => ($row['primary_weight_rate_first_item_excluded'] == 1),
        'secondary_weight_rate' => pg_format_input_amount($row['secondary_weight_rate'] / 100),
        'secondary_weight_rate_first_item_excluded' => ($row['secondary_weight_rate_first_item_excluded'] == 1),
        'item_rate' => pg_format_input_amount($row['item_rate'] / 100),
        'item_rate_first_item_excluded' => ($row['item_rate_first_item_excluded'] == 1),
        'street_address' => ($row['street_address'] == 1),
        'po_box' => ($row['po_box'] == 1),
        // Zero handling days and a midnight end of day are shown blank.
        'handle_days' => $row['handle_days'] ? (string) $row['handle_days'] : '',
        'end_of_day' => ($row['end_of_day'] != '00:00:00') ? prepare_form_data_for_output($row['end_of_day'], 'time') : '',
        'base_transit_days' => (string) $row['base_transit_days'],
        'adjust_transit' => ($row['adjust_transit'] == 1),
        'transit_on_saturday' => ($row['transit_on_saturday'] == 1),
        'transit_on_sunday' => ($row['transit_on_sunday'] == 1),
        'status' => ($row['status'] == 'enabled'),
        'protected' => (bool) $row['protected'],
        'excluded_transit_dates' => (string) $excluded_transit_dates,
        'start_time' => prepare_form_data_for_output($row['start_time'], 'date and time'),
        'end_time' => prepare_form_data_for_output($row['end_time'], 'date and time'),
    );

    // A variable base rate without a subtotal is shown blank.
    for ($number = 2; $number <= 4; $number++) {
        $values['base_rate_' . $number] = '';
        $values['base_rate_' . $number . '_subtotal'] = '';

        if ($row['base_rate_' . $number . '_subtotal'] > 0) {
            $values['base_rate_' . $number] = pg_format_input_amount($row['base_rate_' . $number] / 100);
            $values['base_rate_' . $number . '_subtotal'] = pg_format_input_amount($row['base_rate_' . $number . '_subtotal'] / 100);
        }
    }

    foreach (pg_shipping_method_form_days() as $short => $long) {
        $values['handle_' . $short] = (bool) $row['handle_' . $short];
        $values['ship_' . $short] = (bool) $row['ship_' . $short];
        $values['available_on_' . $long] = ($row['available_on_' . $long] == '1');

        // A midnight cut-off means none and is shown blank.
        $cutoff_time = $row['available_on_' . $long . '_cutoff_time'];
        $values['available_on_' . $long . '_cutoff_time'] = ($cutoff_time == '00:00:00') ? '' : prepare_form_data_for_output($cutoff_time, 'time', true);
    }

    return $values;
}

/**
 * The options of the allowed zones picker: the allowed zones first and
 * selected, then the others, each group in the order given.
 *
 * @param array $zones        Rows with 'id' and 'name', ordered by name
 * @param array $allowed_ids  The ids of the allowed zones
 * @return string  HTML
 */
function pg_shipping_method_zone_options($zones, $allowed_ids)
{
    $allowed_ids = array_map('strval', $allowed_ids);
    $selected = '';
    $others = '';

    foreach ($zones as $zone) {
        if (in_array((string) $zone['id'], $allowed_ids, true)) {
            $selected .= '<option selected="selected" value="' . h($zone['id']) . '">' . h($zone['name']) . '</option>';
        } else {
            $others .= '<option value="' . h($zone['id']) . '">' . h($zone['name']) . '</option>';
        }
    }

    return $selected . $others;
}

/**
 * The zones picker of a shipping method, read from the database.
 *
 * @param int $shipping_method_id  0 for a new method
 * @return string  HTML
 */
function pg_shipping_method_zone_options_for($shipping_method_id)
{
    $zones = db_items("SELECT id, name FROM zones ORDER BY name");
    $allowed_ids = array();

    if ($shipping_method_id) {
        $result = db_q("SELECT zone_id FROM shipping_methods_zones_xref WHERE shipping_method_id = ?", array((int) $shipping_method_id)) or output_error('Query failed.');

        while ($row = mysqli_fetch_assoc($result)) {
            $allowed_ids[] = $row['zone_id'];
        }
    }

    return pg_shipping_method_zone_options($zones, $allowed_ids);
}

/**
 * An amount as typed (either decimal separator, spaces allowed) in minor
 * units; a blank or zero box is 0.
 *
 * @param mixed $value
 * @return int
 */
function pg_shipping_method_amount_cents($value)
{
    if (!$value || !is_scalar($value)) {
        return 0;
    }

    return (int) round((float) str_replace(' ', '', pg_normalize_amount($value)) * 100);
}

/**
 * The variable base rates of a post: each "rate if the subtotal is at
 * least" pair with a rate of 0 or more and a subtotal above 0, ordered by
 * subtotal and stored from base_rate_2 on. Without a valid pair (or with
 * the switch off) the variable base rate is off and every pair 0.
 *
 * @param array $post  The posted form
 * @return array  'variable_base_rate', 'base_rate_2', 'base_rate_2_subtotal',
 *                … 'base_rate_4_subtotal' => int
 */
function pg_shipping_method_variable_base_rates($post)
{
    $values = array('variable_base_rate' => 0);

    for ($number = 2; $number <= 4; $number++) {
        $values['base_rate_' . $number] = 0;
        $values['base_rate_' . $number . '_subtotal'] = 0;
    }

    // An unticked box is not posted at all.
    if (($post['variable_base_rate'] ?? '') != 1) {
        return $values;
    }

    $rates = array();

    for ($number = 2; $number <= 4; $number++) {
        // Read with either separator, as the money mask posts them in the
        // panel language (1.234,56 or 1,234.56).
        $rate_value = pg_normalize_amount(is_scalar($post['base_rate_' . $number] ?? '') ? (string) ($post['base_rate_' . $number] ?? '') : '');
        $subtotal_value = pg_normalize_amount(is_scalar($post['base_rate_' . $number . '_subtotal'] ?? '') ? (string) ($post['base_rate_' . $number . '_subtotal'] ?? '') : '');

        if (
            (is_numeric($rate_value))
            && ($rate_value >= 0)
            && (is_numeric($subtotal_value))
            && ($subtotal_value > 0)
        ) {
            // The subtotal comes first, so sort() orders the pairs by it.
            $rates[] = array(
                'subtotal' => (int) round($subtotal_value * 100),
                'rate' => (int) round($rate_value * 100));
        }
    }

    if (!$rates) {
        return $values;
    }

    sort($rates);

    $values['variable_base_rate'] = 1;

    foreach ($rates as $key => $rate) {
        $number = $key + 2;

        $values['base_rate_' . $number] = $rate['rate'];
        $values['base_rate_' . $number . '_subtotal'] = $rate['subtotal'];
    }

    return $values;
}

/**
 * The excluded dates of a post: the valid dates of the comma separated
 * box, in storage format, without duplicates, sorted.
 *
 * @param string $text
 * @return array
 */
function pg_shipping_method_excluded_dates($text)
{
    $dates = array();

    foreach (explode(',', is_scalar($text) ? (string) $text : '') as $date) {
        $date = prepare_form_data_for_input(trim($date), 'date');

        // An empty entry has no month or day and is dropped.
        $parts = explode('-', (string) $date);
        $year = $parts[0];
        $month = $parts[1] ?? '';
        $day = $parts[2] ?? '';

        if (is_numeric($month) && is_numeric($day) && is_numeric($year) && checkdate($month, $day, $year)) {
            $dates[] = $date;
        }
    }

    $dates = array_unique($dates);
    sort($dates);

    return $dates;
}

/**
 * The column values a post is saved with, in table order (the user and the
 * timestamp aside).
 *
 * @param array $post  The posted form
 * @return array  Column name => string value
 */
function pg_shipping_method_form_columns($post)
{
    $text = function ($name) use ($post) {
        $value = $post[$name] ?? '';
        return is_scalar($value) ? (string) $value : '';
    };

    $service = $text('service');

    // An unticked box is not posted at all. A service without real-time
    // rates keeps the box off, so the list does not show it as on.
    $realtime_rate = $post['realtime_rate'] ?? 0;

    if (!$service or substr($service, 0, 5) == 'fedex') {
        $realtime_rate = 0;
    }

    $variable = pg_shipping_method_variable_base_rates($post);

    $columns = array(
        'name' => $text('name'),
        'description' => $text('description'),
        'code' => $text('code'),
        'carrier_title' => $text('carrier_title'),
        // A blank number cleans to '' whatever the store's country.
        'carrier_vkn' => ($text('carrier_vkn') === '') ? '' : (string) pg_carrier_number_clean($text('carrier_vkn')),
        'status' => ($text('status') == 1) ? 'enabled' : 'disabled',
        'start_time' => (string) prepare_form_data_for_input($text('start_time'), 'date and time'),
        'end_time' => (string) prepare_form_data_for_input($text('end_time'), 'date and time'),
        'service' => $service,
        'realtime_rate' => is_scalar($realtime_rate) ? (string) $realtime_rate : '0',
        'base_rate' => (string) pg_shipping_method_amount_cents($post['base_rate'] ?? ''),
        'variable_base_rate' => (string) $variable['variable_base_rate'],
    );

    for ($number = 2; $number <= 4; $number++) {
        $columns['base_rate_' . $number] = (string) $variable['base_rate_' . $number];
        $columns['base_rate_' . $number . '_subtotal'] = (string) $variable['base_rate_' . $number . '_subtotal'];
    }

    foreach (array('primary_weight_rate', 'secondary_weight_rate', 'item_rate') as $rate) {
        $columns[$rate] = (string) pg_shipping_method_amount_cents($post[$rate] ?? '');
        $columns[$rate . '_first_item_excluded'] = $text($rate . '_first_item_excluded');
    }

    foreach (array('base_transit_days', 'adjust_transit', 'street_address', 'po_box', 'handle_days') as $name) {
        $columns[$name] = $text($name);
    }

    foreach (array('handle', 'ship') as $prefix) {
        foreach (array_keys(pg_shipping_method_form_days()) as $short) {
            $columns[$prefix . '_' . $short] = $text($prefix . '_' . $short);
        }
    }

    $columns['end_of_day'] = (string) prepare_form_data_for_input($text('end_of_day'), 'time');
    $columns['transit_on_sunday'] = $text('transit_on_sunday');
    $columns['transit_on_saturday'] = $text('transit_on_saturday');

    foreach (pg_shipping_method_form_days() as $long) {
        $columns['available_on_' . $long] = $text('available_on_' . $long);
        $columns['available_on_' . $long . '_cutoff_time'] = (string) prepare_form_data_for_input($text('available_on_' . $long . '_cutoff_time'), 'time');
    }

    $columns['protected'] = $text('protected');

    return $columns;
}

/**
 * Writes the allowed zones and the excluded dates of a post for a shipping
 * method that has none yet.
 *
 * @param int   $shipping_method_id
 * @param array $post  The posted form
 * @return void
 */
function pg_shipping_method_save_links($shipping_method_id, $post)
{
    // No zone chosen posts no list at all.
    foreach ((array) ($post['allowed_zones'] ?? array()) as $zone_id) {
        if ($zone_id && is_scalar($zone_id)) {
            db_q("INSERT INTO shipping_methods_zones_xref (shipping_method_id, zone_id) VALUES (?, ?)", array((int) $shipping_method_id, (string) $zone_id)) or output_error('Query failed.');
        }
    }

    foreach (pg_shipping_method_excluded_dates($post['excluded_transit_dates'] ?? '') as $date) {
        db_q("INSERT INTO excluded_transit_dates (shipping_method_id, date) VALUES (?, ?)", array((int) $shipping_method_id, (string) $date)) or output_error('Query failed.');
    }
}

/**
 * The four field cards of the form, wrapped in their row.
 *
 * @param array  $values        pg_shipping_method_form_new_values() or
 *                              pg_shipping_method_form_row_values(); text
 *                              values raw, switches as booleans, 'service'
 *                              null when the picker keeps its default
 * @param string $zone_options  pg_shipping_method_zone_options()
 * @return string  HTML
 */
function pg_shipping_method_form_cards($values, $zone_options)
{
    $checked = function ($name) use ($values) {
        return !empty($values[$name]) ? ' checked="checked"' : '';
    };

    $value = function ($name) use ($values) {
        return h((string) $values[$name]);
    };

    $day_labels = array(
        'mon' => lang('Monday'),
        'tue' => lang('Tuesday'),
        'wed' => lang('Wednesday'),
        'thu' => lang('Thursday'),
        'fri' => lang('Friday'),
        'sat' => lang('Saturday'),
        'sun' => lang('Sunday'),
    );

    $output_service_value = '';

    if ($values['service'] !== null) {
        $output_service_value = '
                                            <script>$("#service").val("' . escape_javascript($values['service']) . '")</script>';
    }

    $output_variable_base_rates = '';

    for ($number = 2; $number <= 4; $number++) {
        $output_variable_base_rates .= '
                                                        <div class="col-12 my-1">
                                                            <label class="form-label" for="base_rate_' . $number . '">' . lang('or') . '</label>
                                                            <div class="input-group">
                                                                <input value="' . $value('base_rate_' . $number) . '" style="min-width: 90px;" type="text" name="base_rate_' . $number . '" id="base_rate_' . $number . '" class="form-control" maxlength="12" inputmode="numeric"' . pg_money_mask_html() . '  style="text-align: right;" />
                                                                <label class="input-group-text" for="base_rate_' . $number . '">' . BASE_CURRENCY_SYMBOL . '</label>
                                                                <label class="input-group-text text-wrap" for="base_rate_' . $number . '_subtotal">' . lang('if recipient subtotal is at least') . '</label>
                                                                <input value="' . $value('base_rate_' . $number . '_subtotal') . '" style="min-width: 90px;" type="text" name="base_rate_' . $number . '_subtotal" id="base_rate_' . $number . '_subtotal" class="form-control" maxlength="12" inputmode="numeric"' . pg_money_mask_html() . '  style="text-align: right;" />
                                                                <label class="input-group-text" for="base_rate_' . $number . '_subtotal">' . BASE_CURRENCY_SYMBOL . '</label>
                                                            </div>
                                                        </div>';
    }

    $rate_labels = array(
        'primary_weight_rate' => lang('Primary Weight Rate'),
        'secondary_weight_rate' => lang('Secondary Weight Rate'),
        'item_rate' => lang('Item Rate'),
    );

    $output_rates = '';

    foreach ($rate_labels as $rate => $label) {
        $output_rates .= '
                                        <div class="col-12 lg:col-4 my-3">
                                            <div class=" border-1 border rounded-4 p-3 m-1">
                                                <label class="form-label" for="' . $rate . '">' . $label . '</label>
                                                <label class="form-control form-adorn form-adorn-end">
                                                    <span class="form-adorn-text" aria-hidden="true">' . BASE_CURRENCY_SYMBOL . '</span>
                                                    <input value="' . $value($rate) . '" type="text" name="' . $rate . '" id="' . $rate . '" class="form-ghost" maxlength="12" inputmode="numeric"' . pg_money_mask_html() . '  style="text-align: right;" />
                                                </label>
                                                <div class="form-field my-3 ms-5">
                                                    <div class="switch"><input class="" type="checkbox" id="' . $rate . '_first_item_excluded" name="' . $rate . '_first_item_excluded" value="1"' . $checked($rate . '_first_item_excluded') . ' /></div>
                                                    <label class="text-wrap" for="' . $rate . '_first_item_excluded">' . lang('Don\'t apply to first item') . '</label>
                                                </div>
                                            </div>
                                        </div>';
    }

    // The handling and shipping day boxes.
    $day_boxes = function ($prefix) use ($day_labels, $checked) {
        $output = '';

        foreach ($day_labels as $short => $label) {
            $output .= '
                                                <div class="col">
                                                    <div class="form-field">
                                                        <input class="check" type="checkbox" id="' . $prefix . '_' . $short . '" name="' . $prefix . '_' . $short . '" value="1"' . $checked($prefix . '_' . $short) . '>
                                                        <label class="" for="' . $prefix . '_' . $short . '">' . $label . '</label>
                                                    </div>
                                                </div>';
        }

        return $output;
    };

    $output_available_days = '';

    foreach (pg_shipping_method_form_days() as $short => $long) {
        $field = 'available_on_' . $long;

        $output_available_days .= '
                                                <div class="col">
                                                    <div class="form-field">
                                                        <input class="check collapse-switcher" type="checkbox" id="' . $field . '" name="' . $field . '" value="1"' . $checked($field) . ' data-bs-target="#' . $field . '_cutoff_time_cell">
                                                        <label class="" for="' . $field . '">' . $day_labels[$short] . '</label>
                                                    </div>
                                                    <div class="collapse popover fade bs-popover-bottom p-0 mb-3 w-auto" id="' . $field . '_cutoff_time_cell">
                                                        <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(25px, 0px);"></div>
                                                        <div class="popover-body">
                                                            <div class="row">
                                                                <div class="col-12">
                                                                    <label for="' . $field . '_cutoff_time" class="form-label">' . lang('Cut-off time') . '</label>
                                                                    <input type="text" name="' . $field . '_cutoff_time" id="' . $field . '_cutoff_time" size="8" maxlength="8" value="' . $value($field . '_cutoff_time') . '"  class="form-control" data-pg-time/>
                                                                    <div class="form-text text-end">h:mm AM/PM</div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>';
    }

    return '
                    <div class="row">
                        <div class="col-12">
                            <div class="card my-7">
                                <div class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">
                                    ' . lang('Main Informations') . '
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-12 sm:col-6 lg:col-4 my-3">
                                            <label for="name" class="form-label">' . lang('Display Name') . '</label>
                                            <input value="' . $value('name') . '" type="text" name="name" placeholder="' . lang('Shipping Method Name') . '" id="name" maxlength="50" class="form-control" />
                                        </div>
                                        <div class="col-12 sm:col-6 lg:col-8 my-3">
                                            <label for="description" class="form-label">' . lang('Display Message') . '</label>
                                            <input value="' . $value('description') . '" type="text" name="description" id="description" class="form-control" maxlength="255" />
                                        </div>
                                        <div class="col-12 sm:col-6 lg:col-4 my-3">
                                            <label for="code" class="form-label">' . lang('Code') . '</label>
                                            <input value="' . $value('code') . '" type="text" name="code" id="code" class="form-control" maxlength="50" />
                                            <div class="form-text text-end">' . lang('Shipping Method Code for Order Reporting') . '</div>
                                        </div>
                                        <div class="col-12 sm:col-6 lg:col-4 my-3">
                                            <label for="carrier_title" class="form-label">' . lang('Carrier Title') . '</label>
                                            <input value="' . $value('carrier_title') . '" type="text" name="carrier_title" id="carrier_title" class="form-control" maxlength="255" />
                                            <div class="form-text text-end">' . lang('Registered company name of the carrier, printed on e-archive invoices for internet sales') . '</div>
                                        </div>
                                        <div class="col-12 sm:col-6 lg:col-4 my-3">
                                            ' . pg_carrier_number_field($value('carrier_vkn')) . '
                                        </div>
                                        <div class="col-12 sm:col-6 lg:col-4 my-3">
                                            <label for="service" class="form-label">' . lang('Service') . '</label>
                                            ' . render(array('template' => 'shipping_method_service.php')) . $output_service_value . '
                                            <div class="form-text text-end">' . lang('Service for Real-Time Rate & Delivery') . '</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="card my-7">
                                <div class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">
                                    ' . lang('Shipping Method Charges') . '
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-12 my-3 collapse" id="realtime_rate_row">
                                            <div class="form-field">
                                                <div class="switch"><input class="" type="checkbox" id="realtime_rate" name="realtime_rate" value="1"' . $checked('realtime_rate') . '/></div>
                                                <label class="" for="realtime_rate">' . lang('Real-Time Rate') . '</label>
                                            </div>
                                            <script>init_shipping_method_service()</script>
                                        </div>
                                        <div class="col-12 my-3">
                                            <div class="col-12 lg:col-4">
                                                <div class=" border-1 border rounded-4 p-3 m-1">
                                                    <label class="form-label" for="base_rate">' . lang('Base Rate') . '</label>
                                                    <label class="form-control form-adorn form-adorn-end">
                                                        <span class="form-adorn-text" aria-hidden="true">' . BASE_CURRENCY_SYMBOL . '</span>
                                                        <input value="' . $value('base_rate') . '" type="text" name="base_rate" id="base_rate" class="form-ghost" maxlength="12" inputmode="numeric"' . pg_money_mask_html() . '  style="text-align: right;" />
                                                    </label>
                                                    <div class="form-field my-3 ms-5">
                                                        <div class="switch"><input class="collapse-switcher" type="checkbox" id="variable_base_rate" name="variable_base_rate" value="1"' . $checked('variable_base_rate') . ' data-bs-target="#base_rate_rows"/></div>
                                                        <label class="text-wrap" for="variable_base_rate">' . lang('Enable variable base rate') . '</label>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="collapse popover fade bs-popover-bottom p-0 mb-3 mt-1" id="base_rate_rows">
                                                <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(59px, 0px);"></div>
                                                <div class="popover-body">
                                                    <div class="row">
                                                        <div class="col-12 my-1">' . lang('The base rate above') . '</div>' . $output_variable_base_rates . '
                                                    </div>
                                                </div>
                                            </div>
                                        </div>' . $output_rates . '
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="card my-7">
                                <div class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">
                                    ' . lang('Destination Delivery Options') . '
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-12 my-3">
                                            <div class="form-field">
                                                <div class="switch"><input class="" type="checkbox" id="street_address" name="street_address" value="1"' . $checked('street_address') . '/></div>
                                                <label class="" for="street_address">' . lang('Allow Street Address') . '</label>
                                            </div>
                                        </div>
                                        <div class="col-12 my-3">
                                            <div class="form-field">
                                                <div class="switch"><input class="" type="checkbox" id="po_box" name="po_box" value="1"' . $checked('po_box') . '/></div>
                                                <label class="" for="po_box">' . lang('Allow PO Box') . '</label>
                                            </div>
                                        </div>
                                        <div class="col-12 my-3">
                                            <label for="allowed_zones" class="form-label">' . lang('Allowed Zones') . '</label>
                                            <select style="width:100%" class="form-control" data-pg-combobox data-placeholder="' . lang('Click to select shipping zone(s)') . '" id="allowed_zones" name="allowed_zones[]" multiple="multiple">' . $zone_options . '</select>
                                        </div>
                                        <div class="col-12 md:col-6 lg:col-4 my-1">
                                            <label for="handle_days" class="form-label">' . lang('Handling Time') . '</label>
                                            <div class="input-group">
                                                <input value="' . $value('handle_days') . '" type="text" name="handle_days" id="handle_days" class="form-control" size="7" maxlength="7" inputmode="numeric" data-inputmask-alias="decimal"  style="text-align: right;" />
                                                <span class="input-group-text" title="' . lang('day(s) from order date') . '">' . lang('day(s)') . '</span>
                                            </div>
                                        </div>
                                        <div class="col-12 md:col-6 lg:col-4 my-1">
                                            <label for="end_of_day" class="form-label">' . lang('End of Day Time') . '</label>
                                            <input value="' . $value('end_of_day') . '" type="text" name="end_of_day" id="end_of_day" class="form-control" maxlength="8" data-pg-time/>
                                            <div class="form-text text-end" >' . lang('h:mm AM/PM') . '</div>
                                        </div>
                                        <div class="col-12 my-3">
                                            <h5 class="fg-secondary">' . lang('Handling on') . '</h5>
                                            <div class="row row-cols-1 sm:row-cols-2 md:row-cols-3 lg:row-cols-auto">' . $day_boxes('handle') . '
                                            </div>
                                        </div>
                                        <div class="col-12 my-3">
                                            <h5 class="fg-secondary mt-5">' . lang('Ships on') . '</h5>
                                            <div class="row row-cols-1 sm:row-cols-2 md:row-cols-3 lg:row-cols-auto">' . $day_boxes('ship') . '
                                            </div>
                                        </div>
                                        <div class="col-12 md:col-6 lg:col-4 my-1">
                                            <label for="base_transit_days" class="form-label">' . lang('Base Transit Time') . '</label>
                                            <div class="input-group">
                                                <input value="' . $value('base_transit_days') . '" type="text" name="base_transit_days" id="base_transit_days" class="form-control" size="7" maxlength="7" inputmode="numeric" data-inputmask-alias="decimal"  style="text-align: right;" />
                                                <span class="input-group-text" title="' . lang('day(s) from order date') . '">' . lang('day(s)') . '</span>
                                            </div>
                                        </div>
                                        <div class="col-12 my-5">
                                            <div class="form-field">
                                                <div class="switch"><input class="" type="checkbox" id="adjust_transit" name="adjust_transit" value="1"' . $checked('adjust_transit') . '/></div>
                                                <label class="" for="adjust_transit">' . lang('Adjust Transit for Country') . '</label>
                                            </div>
                                        </div>
                                        <div class="col-12 my-5">
                                            <div class="form-field">
                                                <div class="switch"><input class="" type="checkbox" id="transit_on_saturday" name="transit_on_saturday" value="1"' . $checked('transit_on_saturday') . '/></div>
                                                <label class="" for="transit_on_saturday">' . lang('Transit on Saturday') . '</label>
                                            </div>
                                        </div>
                                        <div class="col-12 my-5">
                                            <div class="form-field">
                                                <div class="switch"><input class="" type="checkbox" id="transit_on_sunday" name="transit_on_sunday" value="1"' . $checked('transit_on_sunday') . '/></div>
                                                <label class="" for="transit_on_sunday">' . lang('Transit on Sunday') . '</label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="card my-7">
                                <div class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">
                                    ' . lang('Shipping Method Availability') . '
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-12 my-3">
                                            <div class="form-field">
                                                <div class="switch"><input class="" type="checkbox" id="status" name="status" value="1"' . $checked('status') . ' /></div>
                                                <label class="" for="status">' . lang('Enabled this shipping method') . '</label>
                                            </div>
                                        </div>
                                        <div class="col-12 my-3">
                                            <div class="form-field">
                                                <div class="switch"><input class="" type="checkbox" id="protected" name="protected" value="1"' . $checked('protected') . ' /></div>
                                                <label class="" for="protected">' . lang('Protected') . '</label>
                                            </div>
                                        </div>

                                        <div class="col-12 mt-5 mb-3">
                                            <label for="excluded_transit_dates" class="form-label">' . lang('Excluded Dates') . '</label>
                                            <input value="' . $value('excluded_transit_dates') . '" type="text" name="excluded_transit_dates" id="excluded_transit_dates" class="form-control tagin min-height-tagin" data-pg-chips maxlength="255"/>
                                            <div class="form-text text-end">' . lang('Excluded Dates for Shipping & Handling (e.g. Holidays). Split dates with enter key or \',\' key') . ' (' . get_date_format_help() . ')</div>
                                        </div>
                                        <div class="col-12 lg:col-8 my-5">
                                            <div class="row">
                                                <div class="col-12 md:col">
                                                    <label for="start_time" class="form-label">' . lang('Start Time') . '</label>
                                                    <input value="' . $value('start_time') . '" type="text" name="start_time" id="start_time" class="form-control" maxlength="10" autocomplete="off" data-pg-datetime/>
                                                </div>
                                                <div class="col-12 md:col">
                                                    <label for="end_time" class="form-label">' . lang('End Time') . '</label>
                                                    <input value="' . $value('end_time') . '" type="text" name="end_time" id="end_time" class="form-control " maxlength="10" autocomplete="off" data-pg-datetime/>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-12 my-3">
                                            <h5 class="fg-secondary mt-5">' . lang('Available on') . '</h5>
                                            <div class="row row-cols-1 sm:row-cols-2 md:row-cols-3 lg:row-cols-4 xl:row-cols-5">' . $output_available_days . '
                                            </div>
                                            <h6><em>' . lang(array('string'=>'For reference, the current site time is {var:1}.','vars'=>date("g:i A"))) . '</em></h6>
                                        </div>

                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>';
}
