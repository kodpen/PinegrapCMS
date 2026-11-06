<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - the box of a flow that reads one record of the site (kind
 * record_get): an order, product, contact, booking, task or submitted form
 * named by its id or by one value of the run, read with the flow owner's
 * rights as they stand when the box runs. The kind is checked as the change
 * box checks it (ws_change_allowed()), a booking by the agenda's own rule;
 * each record again as it is read. It gives the boxes after it the record's
 * id, whether it was found (1 or 0) and the record's fields as values. A
 * record that is not there, or one out of the owner's reach, is no failure:
 * the box is done, found is 0 and every field is empty, so a later
 * condition decides, and a run tells nothing of a record it may not read.
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
 * The kinds of record the box reads and the fields each gives: key =>
 * array(type, column, label). Types: text, number, money (minor units,
 * given in units with two decimals), bool (1 or 0), time (a unix time,
 * given as Y-m-d H:i), day (a DATE column, the zero date as none), answers
 * (a submitted form's text answers, one "name: answer" a line). A column
 * starting with "__" is one ws_change_record() reads beside the row. A
 * card's digits, a booking's manage link and an address of a visitor's
 * machine are never fields. Pure but for lang().
 *
 * @return array kind => label, fields
 */
function ws_flow_record_get_fields()
{
    return array(
        'order' => array('label' => lang('Order'), 'fields' => array(
            'order_number'   => array('text', 'order_number', lang('Order number')),
            'status'         => array('text', 'status', lang('Status')),
            'order_date'     => array('time', 'order_date', lang('Order date')),
            'total'          => array('money', 'total', lang('Total')),
            'subtotal'       => array('money', 'subtotal', lang('Subtotal')),
            'tax'            => array('money', 'tax', lang('Tax')),
            'shipping'       => array('money', 'shipping', lang('Shipping')),
            'discount'       => array('money', 'discount', lang('Discount')),
            'currency'       => array('text', 'currency_code', lang('Currency')),
            'payment_method' => array('text', 'payment_method', lang('Payment method')),
            'first_name'     => array('text', 'billing_first_name', lang('First name')),
            'last_name'      => array('text', 'billing_last_name', lang('Last name')),
            'email'          => array('text', 'billing_email_address', lang('E-mail')),
            'company'        => array('text', 'billing_company', lang('Company')),
            'phone'          => array('text', 'billing_phone_number', lang('Phone')),
            'city'           => array('text', 'billing_city', lang('City')),
            'country'        => array('text', 'billing_country', lang('Country')),
            'contact_id'     => array('number', 'contact_id', lang('Contact')),
            'tax_exempt'     => array('bool', 'tax_exempt', lang('Tax exempt')),
            'po_number'      => array('text', 'po_number', lang('PO Number')),
            'custom_field_1' => array('text', 'custom_field_1', lang('Custom field 1')),
            'custom_field_2' => array('text', 'custom_field_2', lang('Custom field 2')),
        )),
        'product' => array('label' => lang('Product'), 'fields' => array(
            'name'              => array('text', 'name', lang('Name')),
            'short_description' => array('text', 'short_description', lang('Short description')),
            'price'             => array('money', 'price', lang('Price')),
            'enabled'           => array('bool', 'enabled', lang('On sale')),
            'track_stock'       => array('bool', 'inventory', lang('Track Inventory')),
            'quantity'          => array('number', 'inventory_quantity', lang('Quantity in stock')),
            'brand'             => array('text', 'brand', lang('Brand')),
            'gtin'              => array('text', 'gtin', lang('GTIN')),
            'mpn'               => array('text', 'mpn', lang('MPN')),
            'custom_field_1'    => array('text', 'custom_field_1', lang('Custom field 1')),
            'custom_field_2'    => array('text', 'custom_field_2', lang('Custom field 2')),
            'custom_field_3'    => array('text', 'custom_field_3', lang('Custom field 3')),
            'custom_field_4'    => array('text', 'custom_field_4', lang('Custom field 4')),
        )),
        'contact' => array('label' => lang('Contact'), 'fields' => array(
            'first_name'      => array('text', 'first_name', lang('First name')),
            'last_name'       => array('text', 'last_name', lang('Last name')),
            'company'         => array('text', 'company', lang('Company')),
            'title'           => array('text', 'title', lang('Title')),
            'email'           => array('text', 'email_address', lang('E-mail')),
            'opt_in'          => array('bool', 'opt_in', lang('Opt-In')),
            'phone'           => array('text', 'mobile_phone', lang('Mobile phone')),
            'business_phone'  => array('text', 'business_phone', lang('Business Phone')),
            'city'            => array('text', 'business_city', lang('City')),
            'country'         => array('text', 'business_country', lang('Country')),
            'lead_source'     => array('text', 'lead_source', lang('Lead Source')),
            'member_id'       => array('text', 'member_id', lang('Member ID')),
            'expiration_date' => array('day', 'expiration_date', lang('Expiration Date')),
        )),
        'booking' => array('label' => lang('Booking'), 'fields' => array(
            'name'       => array('text', 'name', lang('Name')),
            'email'      => array('text', 'email', lang('E-mail')),
            'phone'      => array('text', 'phone', lang('Phone')),
            'status'     => array('text', 'status', lang('Status')),
            'service'    => array('text', '__service_name', lang('Service')),
            'service_id' => array('number', 'service_id', lang('Service')),
            'staff_id'   => array('number', 'staff_id', lang('Staff member')),
            'start'      => array('time', 'start_at', lang('Start')),
            'end'        => array('time', 'end_at', lang('End')),
            'source'     => array('text', 'source', lang('Source')),
            'order_id'   => array('number', 'order_id', lang('Order')),
        )),
        'task' => array('label' => lang('Task'), 'fields' => array(
            'title'       => array('text', 'title', lang('Title')),
            'description' => array('text', 'description', lang('Description')),
            'status'      => array('text', 'status', lang('Status')),
            'priority'    => array('text', 'priority', lang('Priority')),
            'start_date'  => array('day', 'start_date', lang('Start date')),
            'due_date'    => array('day', 'due_date', lang('Due date')),
            'channel_id'  => array('number', 'channel_id', lang('Channel')),
            'creator_id'  => array('number', 'creator_id', lang('Created by')),
        )),
        'form' => array('label' => lang('Form'), 'fields' => array(
            'reference_code' => array('text', 'reference_code', lang('Reference code')),
            'complete'       => array('bool', 'complete', lang('Complete')),
            'page_id'        => array('number', 'page_id', lang('Form')),
            'contact_id'     => array('number', 'contact_id', lang('Contact')),
            'submitted'      => array('time', 'submitted_timestamp', lang('Submitted')),
            'answers'        => array('answers', '__answers', lang('Answers')),
        )),
    );
}

/**
 * The values one kind of record gives beside record_id and found, as
 * ws_step_outputs() declares them: money and yes/no are numbers. Pure but
 * for lang().
 *
 * @param string $kind
 * @return array[] key, type, label
 */
function ws_flow_record_get_outputs($kind)
{
    $out = array();

    foreach ((ws_flow_record_get_fields()[(string) $kind]['fields'] ?? array()) as $key => $field) {
        $out[] = array('key' => $key, 'type' => in_array($field[0], array('number', 'money', 'bool'), true) ? 'number' : 'text', 'label' => $field[2]);
    }

    return $out;
}

/**
 * Minor units as units with two decimals and a point, as a value of a run
 * is compared and worked out ("12.50"). Pure.
 *
 * @param mixed $minor
 * @return string
 */
function ws_flow_record_get_money($minor)
{
    $minor = is_numeric($minor) ? (int) round((float) $minor) : 0;
    $sign = ($minor < 0) ? '-' : '';
    $minor = abs($minor);

    return $sign . intdiv($minor, 100) . '.' . str_pad((string) ($minor % 100), 2, '0', STR_PAD_LEFT);
}

/**
 * What a box that reads a record gives, from the record read: its id,
 * found 1 and each field as the text it fills in; for no record, no id,
 * found 0 and every field empty. Pure.
 *
 * @param string     $kind
 * @param array|null $row  the record's row with its id, null for none
 * @return array record_id, found, then the kind's fields
 */
function ws_step_record_get_data($kind, $row)
{
    $found = is_array($row);
    $out = array('record_id' => $found ? (int) ($row['id'] ?? 0) : 0, 'found' => $found ? 1 : 0);

    foreach ((ws_flow_record_get_fields()[(string) $kind]['fields'] ?? array()) as $key => $field) {
        $raw = $found ? ($row[$field[1]] ?? null) : null;

        if (!$found || ($raw === null)) {
            $out[$key] = '';
            continue;
        }

        switch ($field[0]) {
            case 'money':
                $out[$key] = ws_flow_record_get_money($raw);
                break;

            case 'bool':
                $out[$key] = ((int) $raw === 1) ? '1' : '0';
                break;

            case 'number':
                $out[$key] = (string) (int) $raw;
                break;

            case 'time':
                $out[$key] = ((int) $raw > 0) ? date('Y-m-d H:i', (int) $raw) : '';
                break;

            case 'day':
                $raw = trim((string) $raw);
                $out[$key] = (($raw === '') || (strpos($raw, '0000-00-00') === 0)) ? '' : substr($raw, 0, 10);
                break;

            case 'answers':
                $lines = array();

                foreach ((array) $raw as $name => $answer) {
                    $lines[] = $name . ': ' . trim((string) preg_replace('/\s+/u', ' ', (string) $answer));
                }

                $out[$key] = implode("\n", $lines);
                break;

            default:
                $out[$key] = trim(str_replace("\r\n", "\n", (string) $raw));
        }
    }

    return $out;
}

/**
 * May this person read records of this kind at all, as the change box
 * would let them change one (ws_change_allowed() without a record), and a
 * booking where the agenda shows them one (pg_booking_agenda_user_scope())?
 *
 * @param array  $viewer
 * @param string $kind
 * @return bool
 */
function ws_step_record_get_kind_allowed($viewer, $kind)
{
    if (!is_array($viewer) || !isset(ws_flow_record_get_fields()[$kind])) {
        return false;
    }

    if ($kind === 'booking') {
        if (empty($viewer['member']) || !function_exists('pg_booking_ready') || !pg_booking_ready()) {
            return false;
        }

        require_once(PG_FUNCTIONS_DIR . '/includes/fn/booking_agenda.php');

        return pg_booking_agenda_user_scope(array('id' => (int) $viewer['id'], 'role' => (int) $viewer['role'])) !== false;
    }

    return ws_change_allowed($viewer, $kind);
}

/**
 * Checks a box that reads a record with somebody's rights, the owner as
 * they publish and again each time the box runs: a kind they may read and
 * a record named by its id or by one value alone. A record that is not
 * there or out of reach is found as the box runs: it reads none.
 *
 * @param array $viewer the flow's owner
 * @param array $config ws_step_config_normalize('record_get')
 * @return array ok, error, paused, action (type, record, label, id)
 */
function ws_step_record_get_check($viewer, $config)
{
    $refuse = function ($error) {
        return array('ok' => false, 'error' => $error, 'paused' => false, 'action' => null);
    };

    $kind = (string) ($config['record'] ?? '');

    if (!ws_step_record_get_kind_allowed($viewer, $kind)) {
        return $refuse(lang('Choose a kind of record you may read.'));
    }

    $id = (string) ($config['id'] ?? '');

    if (!ws_step_row_names_row($id)) {
        return $refuse(lang('Name the record by its id or by one value, such as {{trigger.record_id}}.'));
    }

    return array('ok' => true, 'error' => '', 'paused' => false, 'action' => array(
        'type'   => 'record_get',
        'record' => $kind,
        'label'  => (string) ws_flow_record_get_fields()[$kind]['label'],
        'id'     => $id,
    ));
}

/**
 * One record as the box reads it, with the owner's reach asked for this
 * record: what ws_change_allowed() lets them change (a contact of their
 * groups, a form in a folder they edit, a task they may change), a booking
 * on their agenda. Null for a record that is not there and for one out of
 * reach alike.
 *
 * @param array  $owner
 * @param string $kind
 * @param int    $id
 * @return array|null the row, its id under id
 */
function ws_step_record_get_read($owner, $kind, $id)
{
    if ((int) $id <= 0) {
        return null;
    }

    if ($kind === 'booking') {
        require_once(PG_FUNCTIONS_DIR . '/includes/fn/booking_agenda.php');

        $row = db_item("SELECT b.*, s.name AS __service_name
            FROM bookings b
            LEFT JOIN booking_services s ON s.id = b.service_id
            WHERE b.id = '" . (int) $id . "' AND b.status <> 'held'
            LIMIT 1");
        $scope = pg_booking_agenda_user_scope(array('id' => (int) $owner['id'], 'role' => (int) $owner['role']));

        return (is_array($row) && pg_booking_agenda_scope_allows($scope, (int) $row['staff_id'])) ? $row : null;
    }

    if (!ws_change_allowed($owner, $kind, (int) $id)) {
        return null;
    }

    $row = ws_change_record($kind, (int) $id);

    return is_array($row) ? $row : null;
}

/**
 * Reads the record a box names, with the flow owner's rights checked
 * (ws_step_record_get_check()). The id text is filled in already: one that
 * names no record, no id at all or a record out of reach reads nothing,
 * and the box is done with found 0.
 *
 * @param array $owner  the flow's owner, checked
 * @param array $action ws_step_record_get_check(), filled
 * @return array ok, text, record_id, data
 */
function ws_step_record_get_do($owner, $action)
{
    $kind = (string) $action['record'];
    // A value filled in as JSON text keeps its quotes.
    $id = ws_step_row_id(trim((string) $action['id'], " \""));
    $row = ws_step_record_get_read($owner, $kind, $id);
    $data = ws_step_record_get_data($kind, $row);

    return array(
        'ok'        => true,
        'text'      => ($row !== null)
            ? lang(array('string' => 'Read the record {var:1} ({var:2}).', 'vars' => array($id, (string) $action['label'])))
            : lang(array('string' => 'No such record ({var:1}) was found: nothing was read.', 'vars' => (string) $action['label'])),
        'record_id' => (int) $data['record_id'],
        'data'      => $data,
    );
}

/**
 * What a box that reads a record does, in words. Pure but for lang().
 *
 * @param array $action ws_step_record_get_check()
 * @return string
 */
function ws_step_record_get_text($action)
{
    return lang(array('string' => 'Reads a record ({var:1}).', 'vars' => (string) ($action['label'] ?? '')));
}

/**
 * The kinds a person may build the box on, for the canvas: each with its
 * label and the values it gives.
 *
 * @param array $viewer
 * @return array[] kind, label, outputs
 */
function ws_flow_record_get_kinds($viewer)
{
    $out = array();

    foreach (ws_flow_record_get_fields() as $kind => $info) {
        if (ws_step_record_get_kind_allowed($viewer, $kind)) {
            $out[] = array('kind' => $kind, 'label' => (string) $info['label'], 'outputs' => ws_flow_record_get_outputs($kind));
        }
    }

    return $out;
}
