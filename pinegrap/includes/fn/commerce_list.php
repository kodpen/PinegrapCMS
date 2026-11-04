<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The columns of the commerce lists printed whole on the list table driver
 * (browser mode, pg_list_table_printed()): orders (view_orders.php), gift
 * cards (view_gift_cards.php), key codes (view_key_codes.php) and shipping
 * methods (view_shipping_methods.php).
 *
 * A column's key is the code the screen's sort menu and pg_list_state() use,
 * so a heading click in the browser and the toolbar's sort menu keep the
 * same sort; a column the screen does not sort by is not sortable here. A
 * column that changes in place is keyed by its quick edit field, so the
 * answer to an edit finds the cells it names.
 *
 * Loaded where it is needed (require_once), not by functions.php.
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
 * Column specs finished for a printed list: a column the screen's sort menu
 * does not offer is not sortable, and a column with a quick edit field gets
 * that field's input.
 *
 * @param array $specs        key => spec, as pg_list_table_printed() takes them
 * @param array $sort_columns the screen's sort codes => label
 * @param array $quick_edit   the screen's pg_list_edit columns (field => definition)
 * @return array key => spec
 */
function pg_commerce_list_specs(array $specs, array $sort_columns, array $quick_edit)
{
    foreach ($specs as $key => $spec) {
        if (!isset($sort_columns[$key])) {
            $specs[$key]['sortable'] = false;
        }

        $type = $quick_edit[$key]['type'] ?? '';

        if (in_array($type, array('text', 'number', 'money', 'select', 'check', 'date'), true)) {
            $specs[$key]['editable'] = $type;
        }
    }

    return $specs;
}

/**
 * A row's name as the link that opens its record (the driver opens it on a
 * double-click or Enter).
 *
 * @param string $url  the record's screen, relative to the panel
 * @param string $html the name, already escaped
 * @return string
 */
function pg_commerce_list_open_link($url, $html)
{
    return '<a href="' . h($url) . '" data-pg-open>' . $html . '</a>';
}

/**
 * The orders list's columns: the order (its number, or the billing name of
 * a cart that has none), then the columns the list always had. Affiliate
 * Code only while the affiliate program is on.
 *
 * @param bool   $affiliate    whether the affiliate program is on
 * @param string $member_label the site's name for the member id
 * @return array key => spec
 */
function pg_order_list_columns($affiliate, $member_label)
{
    $specs = array(
        'order_number' => array('heading' => lang('Order Number'), 'kind' => 'link'),
        'first_name' => array('heading' => lang('First Name')),
        'last_name' => array('heading' => lang('Last Name')),
        'status' => array('heading' => lang('Status'), 'kind' => 'badge'),
        'type' => array('heading' => lang('Type')),
        'total' => array('heading' => lang('Total'), 'kind' => 'money'),
        'user' => array('heading' => lang('User')),
        'tracking_code' => array('heading' => lang('Tracking Code')),
        'member_id' => array('heading' => (string) $member_label),
        'affiliate_code' => array('heading' => lang('Affiliate Code')),
        'card_data' => array('heading' => lang('Card Data'), 'kind' => 'check'),
        'order_date' => array('heading' => lang('Order Date'), 'kind' => 'date'),
        'actions' => array('heading' => lang('Action'), 'kind' => 'actions'),
    );

    if (!$affiliate) {
        unset($specs['affiliate_code']);
    }

    return $specs;
}

/**
 * The gift card list's columns.
 *
 * @return array key => spec
 */
function pg_gift_card_list_columns()
{
    return array(
        'code' => array('heading' => lang('Code'), 'kind' => 'link'),
        'balance' => array('heading' => lang('Balance'), 'kind' => 'money'),
        'original_amount' => array('heading' => lang('Original Amt'), 'kind' => 'money'),
        'expiration_date' => array('heading' => lang('Expiration Date'), 'kind' => 'date'),
        'notes' => array('heading' => lang('Notes')),
        'from' => array('heading' => lang('From')),
        'recipient' => array('heading' => lang('Recipient')),
        'delivery_date' => array('heading' => lang('Delivery Date'), 'kind' => 'date'),
        'created' => array('heading' => lang('Created'), 'kind' => 'date'),
        'last_modified' => array('heading' => lang('Last Modified'), 'kind' => 'date'),
    );
}

/**
 * The key code list's columns.
 *
 * @return array key => spec
 */
function pg_key_code_list_columns()
{
    return array(
        'code' => array('heading' => lang('Key Code'), 'kind' => 'link'),
        'offer_code' => array('heading' => lang('Offer Code')),
        'offer_message' => array('heading' => lang('Offer Message')),
        'enabled' => array('heading' => lang('Enabled'), 'kind' => 'check'),
        'expiration_date' => array('heading' => lang('Expiration Date'), 'kind' => 'date'),
        'notes' => array('heading' => lang('Notes')),
        'single_use' => array('heading' => lang('Single-Use'), 'kind' => 'check'),
        'report' => array('heading' => lang('Report'), 'kind' => 'badge'),
        'last_modified' => array('heading' => lang('Last Modified'), 'kind' => 'date'),
    );
}

/**
 * The shipping method list's columns. Service and Real-Time Rate only while
 * a method has a service.
 *
 * @param bool $has_service pg_shipping_method_list_has_service()
 * @return array key => spec
 */
function pg_shipping_method_list_columns($has_service)
{
    $specs = array(
        'name' => array('heading' => lang('Name'), 'kind' => 'link'),
        'message' => array('heading' => lang('Message')),
        'code' => array('heading' => lang('Code'), 'kind' => 'badge'),
        'service' => array('heading' => lang('Service')),
        'realtime_rate' => array('heading' => lang('Real-Time Rate'), 'kind' => 'check'),
        'base_rate' => array('heading' => lang('Base Rate'), 'kind' => 'money'),
        'primary_weight_rate' => array('heading' => lang('Primary W. Rate'), 'kind' => 'money'),
        'secondary_weight_rate' => array('heading' => lang('Secondary W. Rate'), 'kind' => 'money'),
        'item_rate' => array('heading' => lang('Item Rate'), 'kind' => 'money'),
        'handling' => array('heading' => lang('Handling'), 'kind' => 'number'),
        'transit' => array('heading' => lang('Transit'), 'kind' => 'number'),
        'street' => array('heading' => lang('Street'), 'kind' => 'check'),
        'po_box' => array('heading' => lang('PO Box'), 'kind' => 'check'),
        'zones' => array('heading' => lang('Shipping Zone')),
        'protected' => array('heading' => lang('Protected'), 'kind' => 'check'),
        'last_modified' => array('heading' => lang('Last Modified'), 'kind' => 'date'),
    );

    if (!$has_service) {
        unset($specs['service'], $specs['realtime_rate']);
    }

    return $specs;
}
