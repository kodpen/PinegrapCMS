<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The All Shipping Methods list (view_shipping_methods.php) and its quick
 * edit: every value of the list the shipping method screen
 * (edit_shipping_method.php) changes is a field, checked with that screen's
 * own form rules (includes/forms/shipping_method_form.php). The zones stay
 * text (several choices), and so does Last Modified.
 *
 * Loaded where it is needed (require_once), not by functions.php; it needs
 * includes/forms/shipping_method_form.php.
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
 * Which carriers can give a real-time rate, as the method screen's service
 * picker asks (pg_shipping_method_form_cards()).
 *
 * @return array 'usps', 'ups', 'fedex' => bool
 */
function pg_shipping_method_quick_ready()
{
    return array(
        'usps' => USPS_USER_ID || pg_carrier_api_ready('usps'),
        'ups' => (bool) UPS,
        'fedex' => (bool) FEDEX,
    );
}

/**
 * The services the list's Service field offers: those of the carriers that
 * are set up, then any other one a method keeps (the screen offers a
 * stored service its carrier no longer has, so the list draws it too).
 *
 * @param array $ready  pg_shipping_method_quick_ready()
 * @param array $stored the services the methods hold
 * @return string[]
 */
function pg_shipping_method_quick_services(array $ready, array $stored)
{
    $services = array();

    foreach (pg_shipping_method_services() as $service) {
        if (!empty($ready[substr($service, 0, strpos($service, '_'))])) {
            $services[] = $service;
        }
    }

    foreach ($stored as $service) {
        $service = (string) $service;

        if (($service !== '') && !in_array($service, $services, true)) {
            $services[] = $service;
        }
    }

    return $services;
}

/**
 * The quick edit fields, keyed by the list's column. The lengths are the
 * screen's maxlength; the day counts the columns' ranges. Pure but for
 * lang() and get_shipping_service_name().
 *
 * @param string[] $services the Service field's choices (pg_shipping_method_quick_services())
 * @return array field => definition
 */
function pg_shipping_method_edit_columns(array $services = array())
{
    $yes_no = array('1' => lang('Yes'), '0' => lang('No'));
    $service_options = array('' => '–');

    foreach ($services as $service) {
        $name = get_shipping_service_name($service);
        $service_options[$service] = ($name !== '') ? $name : $service;
    }

    return array(
        'name' => array('type' => 'text', 'label' => lang('Name'), 'max' => 50, 'required' => true),
        'message' => array('type' => 'text', 'label' => lang('Display Message'), 'max' => 255),
        'code' => array('type' => 'text', 'label' => lang('Code'), 'max' => 50),
        'service' => array('type' => 'select', 'label' => lang('Service'), 'options' => $service_options),
        'realtime_rate' => array('type' => 'select', 'label' => lang('Real-Time Rate'), 'options' => $yes_no),
        'base_rate' => array('type' => 'money', 'label' => lang('Base Rate'), 'empty' => true),
        'primary_weight_rate' => array('type' => 'money', 'label' => lang('Primary Weight Rate'), 'empty' => true),
        'secondary_weight_rate' => array('type' => 'money', 'label' => lang('Secondary Weight Rate'), 'empty' => true),
        'item_rate' => array('type' => 'money', 'label' => lang('Item Rate'), 'empty' => true),
        'handling' => array('type' => 'integer', 'label' => lang('Handling Time'), 'min' => 0, 'max' => 65535, 'empty' => true),
        'transit' => array('type' => 'integer', 'label' => lang('Base Transit Time'), 'min' => 0, 'max' => 2147483647, 'empty' => true),
        'street' => array('type' => 'select', 'label' => lang('Allow Street Address'), 'options' => $yes_no),
        'po_box' => array('type' => 'select', 'label' => lang('Allow PO Box'), 'options' => $yes_no),
        'protected' => array('type' => 'select', 'label' => lang('Protected'), 'options' => $yes_no),
    );
}

/**
 * The shipping_methods column each field writes.
 *
 * @return array field => column
 */
function pg_shipping_method_quick_column_names()
{
    return array(
        'name' => 'name',
        'message' => 'description',
        'code' => 'code',
        'service' => 'service',
        'realtime_rate' => 'realtime_rate',
        'base_rate' => 'base_rate',
        'primary_weight_rate' => 'primary_weight_rate',
        'secondary_weight_rate' => 'secondary_weight_rate',
        'item_rate' => 'item_rate',
        'handling' => 'handle_days',
        'transit' => 'base_transit_days',
        'street' => 'street_address',
        'po_box' => 'po_box',
        'protected' => 'protected',
    );
}

/**
 * Why a method's cell does not change on the list, '' when it does. A
 * pickup method has no service, base rate or address limit (the screen
 * saves it so, pg_stock_pickup_columns()); a variable base rate has a step
 * for each order subtotal, which one box cannot name.
 *
 * @param array  $method a shipping_methods row
 * @param string $field
 * @return string the reason, for the operator
 */
function pg_shipping_method_quick_locked($method, $field)
{
    $pickup = ((int) ($method['stock_location_id'] ?? 0) > 0);

    if ($pickup && in_array($field, array('service', 'realtime_rate', 'base_rate', 'street', 'po_box'), true)) {
        return lang('A pickup method has no rate, service or address limit. Change it in the shipping method.');
    }

    if (($field === 'base_rate') && !empty($method['variable_base_rate'])) {
        return lang('This shipping method has a base rate for each order subtotal. Change it in the shipping method.');
    }

    return '';
}

/**
 * The value a field opens with: amounts in the site's decimal style, a zero
 * handling time blank, switches as 1 or 0.
 *
 * @param array  $method a shipping_methods row
 * @param string $field
 * @return string
 */
function pg_shipping_method_quick_value($method, $field)
{
    $column = pg_shipping_method_quick_column_names()[$field] ?? '';
    $stored = $method[$column] ?? '';

    switch ($field) {
        case 'base_rate':
        case 'primary_weight_rate':
        case 'secondary_weight_rate':
        case 'item_rate':
            return pg_format_input_amount(((int) $stored) / 100);

        case 'handling':
            return ((int) $stored > 0) ? (string) (int) $stored : '';

        case 'transit':
            return (string) (int) $stored;

        case 'realtime_rate':
        case 'street':
        case 'po_box':
        case 'protected':
            return !empty($stored) ? '1' : '0';
    }

    return (string) $stored;
}

/**
 * One quick edit checked the way the method screen checks the same box
 * (pg_shipping_method_form_errors(), pg_shipping_method_form_columns()), as
 * the columns to write. A real-time rate needs a USPS or UPS service, and a
 * service without one turns it off, as the screen saves them. Pure but for
 * lang().
 *
 * @param array  $method a shipping_methods row
 * @param string $field  a key of pg_shipping_method_edit_columns()
 * @param mixed  $value  as pg_list_edit_parse() read it
 * @param array  $ready  pg_shipping_method_quick_ready()
 * @return array array('ok' => true, 'columns' => column => value) or
 *               array('ok' => false, 'code', 'message')
 */
function pg_shipping_method_quick_change($method, $field, $value, array $ready)
{
    $names = pg_shipping_method_quick_column_names();

    $refuse = function ($message, $code = 'invalid') {
        return array('ok' => false, 'code' => $code, 'message' => (string) $message);
    };

    if (!isset($names[$field])) {
        return $refuse(lang('This column cannot be changed here.'));
    }

    $locked = pg_shipping_method_quick_locked($method, $field);

    if ($locked !== '') {
        return $refuse($locked, 'locked');
    }

    $value = (string) $value;
    $labels = pg_shipping_method_edit_columns();
    $label = $labels[$field]['label'];
    $columns = array();

    switch ($field) {
        case 'name':
            if (trim($value) === '') {
                return $refuse(lang(array('string' => 'Please enter a valid value for {var:1}', 'vars' => lang('Display Name'))));
            }

            $columns['name'] = $value;
            break;

        case 'message':
        case 'code':
            $columns[$names[$field]] = $value;
            break;

        case 'service':
            if ($value !== '') {
                $choices = pg_shipping_method_service_choices($ready, (string) ($method['service'] ?? ''));
                $offered = ($value === $choices['extra']);

                foreach ($choices['groups'] as $services) {
                    $offered = $offered || in_array($value, $services, true);
                }

                if (!$offered || !in_array($value, pg_shipping_method_services(), true)) {
                    return $refuse(lang(array('string' => 'Choose one of the values offered for {var:1}.', 'vars' => array($label))));
                }
            }

            $columns['service'] = $value;

            if (($value === '') || (substr($value, 0, 5) === 'fedex')) {
                $columns['realtime_rate'] = '0';
            }
            break;

        case 'realtime_rate':
            $service = (string) ($method['service'] ?? '');

            if (($value === '1') && (($service === '') || (substr($service, 0, 5) === 'fedex'))) {
                return $refuse(lang('A real-time rate needs a USPS or UPS service.'));
            }

            $columns['realtime_rate'] = ($value === '1') ? '1' : '0';
            break;

        case 'base_rate':
        case 'primary_weight_rate':
        case 'secondary_weight_rate':
        case 'item_rate':
            if (!pg_shipping_method_form_amount_valid($value)) {
                return $refuse(lang(array('string' => '{var:1} takes an amount, such as {var:2}.', 'vars' => array($label, pg_format_number(12.5, 2)))));
            }

            $cents = pg_shipping_method_amount_cents($value);

            // The rate columns are INT UNSIGNED.
            if ($cents > 4294967295) {
                return $refuse(lang(array('string' => '{var:1} is too large.', 'vars' => array($label))));
            }

            $columns[$field] = (string) $cents;
            break;

        case 'handling':
        case 'transit':
            $max = ($field === 'handling') ? 65535 : 2147483647;

            if (!pg_shipping_method_form_days_valid($value, $max)) {
                return $refuse(lang(array('string' => '{var:1} takes a whole number from {var:2} to {var:3}.', 'vars' => array($label, pg_format_number(0, 0), pg_format_number($max, 0)))));
            }

            $columns[$names[$field]] = (string) (int) $value;
            break;

        default:
            $columns[$names[$field]] = ($value === '1') ? '1' : '0';
    }

    return array('ok' => true, 'columns' => $columns);
}

/**
 * The list's query, without its ORDER BY.
 *
 * @param int $id one method only, 0 for all
 * @return string
 */
function pg_shipping_method_list_sql($id = 0)
{
    return "SELECT
            shipping_methods.id,
            shipping_methods.name,
            shipping_methods.description,
            shipping_methods.code,
            shipping_methods.status,
            shipping_methods.start_time,
            shipping_methods.end_time,
            shipping_methods.service,
            shipping_methods.realtime_rate,
            shipping_methods.base_rate,
            shipping_methods.variable_base_rate,
            shipping_methods.base_rate_2,
            shipping_methods.base_rate_2_subtotal,
            shipping_methods.base_rate_3,
            shipping_methods.base_rate_3_subtotal,
            shipping_methods.base_rate_4,
            shipping_methods.base_rate_4_subtotal,
            shipping_methods.primary_weight_rate,
            shipping_methods.secondary_weight_rate,
            shipping_methods.item_rate,
            shipping_methods.handle_days,
            shipping_methods.base_transit_days,
            shipping_methods.street_address,
            shipping_methods.po_box,
            shipping_methods.protected,
            shipping_methods.stock_location_id,
            user.user_username AS user,
            shipping_methods.timestamp
        FROM shipping_methods
        LEFT JOIN user ON shipping_methods.user = user.user_id"
        . (((int) $id > 0) ? "
        WHERE shipping_methods.id = '" . (int) $id . "'" : '');
}

/**
 * The names of the zones a method ships to, by name.
 *
 * @param int $id
 * @return string[]
 */
function pg_shipping_method_list_zones($id)
{
    return db_values(
        "SELECT zones.name
        FROM shipping_methods_zones_xref
        LEFT JOIN zones ON shipping_methods_zones_xref.zone_id = zones.id
        WHERE shipping_methods_zones_xref.shipping_method_id = '" . (int) $id . "'
        ORDER BY zones.name");
}

/**
 * A method's cells, drawn the one way the list and the answer to an edit
 * both use. The name is green while checkout offers the method, else red
 * with the reason under it; a variable base rate shows every step with the
 * subtotal it starts at. A cell is a field only when its column is in
 * $quick_edit and the method lets it change (pg_shipping_method_quick_locked()).
 *
 * @param array  $method     a row of pg_shipping_method_list_sql()
 * @param array  $zones      pg_shipping_method_list_zones()
 * @param array  $quick_edit pg_shipping_method_edit_columns(), empty without quick edit
 * @param string $now        Y-m-d H:i:s, for the name's colour
 * @return array key => pg_list_cell()
 */
function pg_shipping_method_list_cells($method, array $zones = array(), array $quick_edit = array(), $now = '')
{
    $id = (int) ($method['id'] ?? 0);
    $now = ($now !== '') ? $now : date('Y-m-d H:i:s');
    $yes = '<span class="material-icons" aria-hidden="true">task_alt</span><span class="visually-hidden">' . h(lang('Yes')) . '</span>';

    $attributes = function ($field, array $more = array()) use ($method, $quick_edit, $id) {
        if (pg_shipping_method_quick_locked($method, $field) !== '') {
            return $more;
        }

        return $more + pg_list_edit_cell_attributes($quick_edit, $field, $id, pg_shipping_method_quick_value($method, $field));
    };

    $money = function ($cents) {
        return pg_format_money(((int) $cents) / 100, BASE_CURRENCY_SYMBOL);
    };

    $rate = function ($field) use ($method, $money, $attributes) {
        return pg_list_cell($money($method[$field] ?? 0), 'align-middle text-end', $attributes($field, array('data-pg-sort-value' => (string) (int) ($method[$field] ?? 0))));
    };

    $check = function ($field, $column) use ($method, $yes, $attributes) {
        $on = !empty($method[$column]);

        return pg_list_cell($on ? $yes : '', 'align-middle', $attributes($field, array('data-pg-sort-value' => $on ? '1' : '0')));
    };

    if (!empty($method['variable_base_rate'])) {
        $base_rate = $money($method['base_rate'] ?? 0) . ' (@' . $money(0) . ')';

        for ($number = 2; $number <= 4; $number++) {
            if (!empty($method['base_rate_' . $number . '_subtotal'])) {
                $base_rate .= '<br>' . $money($method['base_rate_' . $number]) . ' (@' . $money($method['base_rate_' . $number . '_subtotal']) . ')';
            }
        }
    } else {
        $base_rate = $money($method['base_rate'] ?? 0);
    }

    $state = isset($method['status']) ? pg_shipping_method_list_state($method, $now) : '';
    $output_state = ($state === 'Disabled') ? pg_list_status_word(lang('Disabled')) : (($state === 'Inactive') ? pg_list_status_word(lang('Inactive')) : '');
    $name = (string) ($method['name'] ?? '');
    $service = (string) ($method['service'] ?? '');
    $username = (string) ($method['user'] ?? '');

    return array(
        'name' => pg_list_cell(
            pg_commerce_list_open_link('edit_shipping_method.php?id=' . $id, '<span class="d-inline-block text-wrap text-break" style="min-width:12rem;max-width:20rem;">' . h($name) . '</span>') . $output_state,
            'align-middle chart_label' . (($state === '') ? ' fg-success' : ' fg-danger'),
            $attributes('name', array('data-pg-sort-value' => $name))),
        'message' => pg_list_cell('<div class="text-wrap text-break" style="min-width:15rem;max-width:24rem;">' . h((string) ($method['description'] ?? '')) . '</div>', 'align-middle', $attributes('message')),
        'code' => pg_list_cell('<span class="badge fg-body fw-light">' . h((string) ($method['code'] ?? '')) . '</span>', 'align-middle', $attributes('code', array('data-pg-sort-value' => (string) ($method['code'] ?? '')))),
        'service' => pg_list_cell(h(($service !== '') ? get_shipping_service_name($service) : ''), 'align-middle', $attributes('service')),
        'realtime_rate' => $check('realtime_rate', 'realtime_rate'),
        'base_rate' => pg_list_cell($base_rate, 'align-middle text-end', $attributes('base_rate', array('data-pg-sort-value' => (string) (int) ($method['base_rate'] ?? 0)))),
        'primary_weight_rate' => $rate('primary_weight_rate'),
        'secondary_weight_rate' => $rate('secondary_weight_rate'),
        'item_rate' => $rate('item_rate'),
        // Blank instead of zero handling days.
        'handling' => pg_list_cell(!empty($method['handle_days']) ? h((string) $method['handle_days']) : '', 'align-middle', $attributes('handling', array('data-pg-sort-value' => (string) (int) ($method['handle_days'] ?? 0)))),
        'transit' => pg_list_cell(h((string) ($method['base_transit_days'] ?? '')), 'align-middle', $attributes('transit', array('data-pg-sort-value' => (string) (int) ($method['base_transit_days'] ?? 0)))),
        'street' => $check('street', 'street_address'),
        'po_box' => $check('po_box', 'po_box'),
        'zones' => pg_list_cell(implode(',<br>', array_map('h', $zones))),
        'protected' => $check('protected', 'protected'),
        'last_modified' => pg_list_cell(
            get_relative_time(array('timestamp' => $method['timestamp'] ?? 0))
                . (($username !== '') ? ' ' . lang(array('string' => 'by {var:1}', 'vars' => array(h($username)))) : ''),
            'align-middle',
            array('data-pg-sort-value' => (string) (int) ($method['timestamp'] ?? 0))),
    );
}

/**
 * The quick edit answer from a method read again: the edited cell and every
 * other cell of the row (a service turns the real-time rate off, Last
 * Modified follows every change), each with the value its field opens with.
 *
 * @param array  $cells pg_shipping_method_list_cells()
 * @param string $field the edited cell's column
 * @return array
 */
function pg_shipping_method_quick_saved(array $cells, $field)
{
    $view = function ($cell) {
        $out = array('html' => $cell['html'], 'class' => $cell['class']);

        if (isset($cell['attributes']['data-pg-edit-value'])) {
            $out['value'] = (string) $cell['attributes']['data-pg-edit-value'];
        }

        return $out;
    };

    $answer = array('ok' => true) + $view($cells[$field]);
    $answer['value'] = $answer['value'] ?? '';
    $answer['cells'] = array();

    foreach ($cells as $key => $cell) {
        if ($key !== $field) {
            $answer['cells'][$key] = $view($cell);
        }
    }

    return $answer;
}

/**
 * Write one quick edit from the list the way the method's screen writes the
 * same box: the screen's checks (pg_shipping_method_quick_change()), the
 * last change stamped with who made it, a line in the Site Log and the
 * shipping_method.updated event the screen's save announces. The same value
 * again writes nothing.
 *
 * @param int    $method_id
 * @param string $field      a key of $quick_edit
 * @param mixed  $value      as pg_list_edit_parse() read it
 * @param int    $user_id
 * @param array  $quick_edit pg_shipping_method_edit_columns()
 * @return array the answer for pg_list_edit_reply()
 */
function pg_shipping_method_quick_update($method_id, $field, $value, $user_id, array $quick_edit)
{
    $method_id = (int) $method_id;
    $method = db_item(pg_shipping_method_list_sql($method_id));

    if (!$method) {
        return array('ok' => false, 'code' => 'not_found', 'message' => lang('The shipping method could not be found.'));
    }

    $change = pg_shipping_method_quick_change($method, $field, $value, pg_shipping_method_quick_ready());

    if (!$change['ok']) {
        return $change;
    }

    $changed = array();

    foreach ($change['columns'] as $column => $new) {
        if ((string) $method[$column] !== (string) $new) {
            $changed[$column] = (string) $new;
        }
    }

    if ($changed) {
        $assignments = array();

        foreach (array_keys($changed) as $column) {
            $assignments[] = $column . ' = ?';
        }

        $parameters = array_values($changed);
        $parameters[] = (int) $user_id;
        $parameters[] = $method_id;

        if (!db_q("UPDATE shipping_methods SET " . implode(', ', $assignments) . ", user = ?, timestamp = UNIX_TIMESTAMP() WHERE id = ?", $parameters)) {
            return array('ok' => false, 'code' => 'invalid', 'message' => lang('The value could not be saved.'));
        }

        $label = $quick_edit[$field]['label'] ?? $field;
        $column = pg_shipping_method_quick_column_names()[$field];

        log_activity(
            lang(array(
                'string' => 'shipping method ({var:1}) was changed on the shipping method list: {var:2} from "{var:3}" to "{var:4}"',
                'vars'   => array((string) $method['name'], $label, pg_shipping_method_quick_value($method, $field), pg_shipping_method_quick_value(array($column => $change['columns'][$column]), $field)),
            )),
            $_SESSION['sessionusername'] ?? '');

        pg_announce('shipping_method.updated', array('id' => $method_id, 'name' => (string) ($changed['name'] ?? $method['name'])));

        $method = db_item(pg_shipping_method_list_sql($method_id));
    }

    return pg_shipping_method_quick_saved(pg_shipping_method_list_cells($method, pg_shipping_method_list_zones($method_id), $quick_edit), $field);
}
