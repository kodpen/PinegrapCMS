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
        $type = ($type === 'integer') ? 'number' : $type;

        if (in_array($type, pg_list_table_edit_kinds(), true)) {
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

/**
 * The product attribute columns that change in place: the name and the
 * label. The options are a list of their own and change on the attribute's
 * screen.
 *
 * @return array field => definition (pg_list_edit_parse())
 */
function pg_product_attribute_edit_columns()
{
    return array(
        'name' => array('type' => 'text', 'label' => lang('Name'), 'max' => 100, 'required' => true),
        'label' => array('type' => 'text', 'label' => lang('Label'), 'max' => 255),
    );
}

/**
 * The product attributes list's columns.
 *
 * @return array key => spec
 */
function pg_product_attribute_list_columns()
{
    return array(
        'name' => array('heading' => lang('Name'), 'editable' => 'text'),
        'label' => array('heading' => lang('Label'), 'editable' => 'text'),
        'options' => array('heading' => lang('Options')),
        'created' => array('heading' => lang('Created'), 'kind' => 'date'),
        'last_modified' => array('heading' => lang('Last Modified'), 'kind' => 'date'),
    );
}

/**
 * The product attributes read for the list, each with its option labels in
 * their order: every one, or the one asked for.
 *
 * @param int $id 0 for all
 * @return array
 */
function pg_product_attribute_list_rows($id = 0)
{
    $rows = db_items(
        "SELECT
            product_attributes.id, product_attributes.name, product_attributes.label,
            product_attributes.created_timestamp, product_attributes.last_modified_timestamp,
            created_user.user_username AS created_username,
            last_modified_user.user_username AS last_modified_username
        FROM product_attributes
        LEFT JOIN user AS created_user ON product_attributes.created_user_id = created_user.user_id
        LEFT JOIN user AS last_modified_user ON product_attributes.last_modified_user_id = last_modified_user.user_id
        " . ($id ? "WHERE product_attributes.id = '" . (int) $id . "'" : "") . "
        ORDER BY product_attributes.name") ?: array();
    $options = array();

    foreach ((db_items("SELECT product_attribute_id, label, no_value FROM product_attribute_options" . ($id ? " WHERE product_attribute_id = '" . (int) $id . "'" : "") . " ORDER BY sort_order") ?: array()) as $option) {
        $options[(int) $option['product_attribute_id']][] = (string) $option['label'] . ($option['no_value'] ? lang(' [\'No Thanks\' Option]') : '');
    }

    foreach ($rows as $index => $row) {
        $rows[$index]['options'] = $options[(int) $row['id']] ?? array();
    }

    return $rows;
}

/**
 * A product attribute row's cells. An attribute without a label says so.
 *
 * @param array $row        pg_product_attribute_list_rows()
 * @param array $quick_edit field => definition, empty for none
 * @return array key => pg_list_cell()
 */
function pg_product_attribute_list_cells(array $row, array $quick_edit)
{
    $id = (int) $row['id'];

    return array(
        'name' => pg_list_cell(pg_commerce_list_open_link('edit_product_attribute.php?id=' . $id, h($row['name'])), 'align-middle', pg_list_edit_cell_attributes($quick_edit, 'name', $id, $row['name'])),
        'label' => pg_list_cell(((string) $row['label'] !== '') ? h($row['label']) : '<span class="fg-danger">[' . h(lang('No Label')) . ']</span>', 'align-middle', pg_list_edit_cell_attributes($quick_edit, 'label', $id, $row['label'])),
        'options' => pg_list_cell(h(implode(', ', $row['options'])), 'align-middle', array('data-pg-sort-value' => count($row['options']) . ' ' . implode(', ', $row['options']))),
        'created' => pg_list_table_when_cell($row['created_timestamp'], (string) ($row['created_username'] ?? '')),
        'last_modified' => pg_list_table_when_cell($row['last_modified_timestamp'], (string) ($row['last_modified_username'] ?? '')),
    );
}

/**
 * Writes a product attribute's name or label changed on the list: a name is
 * not used by another attribute, as on the attribute's screen.
 *
 * @param int    $id
 * @param string $field
 * @param mixed  $value pg_list_edit_parse()'s value
 * @param int    $user_id
 * @return array the answer pg_list_edit_reply() sends
 */
function pg_product_attribute_quick_update($id, $field, $value, $user_id)
{
    $quick_edit = pg_product_attribute_edit_columns();
    $rows = pg_product_attribute_list_rows((int) $id);

    if (!$rows || !isset($quick_edit[$field])) {
        return array('ok' => false, 'code' => 'invalid', 'message' => lang('Record not found.'));
    }

    $value = (string) $value;

    if (($field === 'name') && ((int) db_value("SELECT COUNT(*) FROM product_attributes WHERE name = '" . e($value) . "' AND id != '" . (int) $id . "'") > 0)) {
        return array('ok' => false, 'code' => 'invalid', 'message' => lang('Sorry, the name that you entered is already in use, so please enter a different name.'));
    }

    db_q("UPDATE product_attributes SET `" . (($field === 'name') ? 'name' : 'label') . "` = ?, last_modified_user_id = ?, last_modified_timestamp = UNIX_TIMESTAMP() WHERE id = ?",
        array($value, (string) (int) $user_id, (string) (int) $id)) or output_error('Query failed.');

    log_activity(lang(array('string' => '{var:1} ({var:2}) was modified', 'vars' => array(lang('product attribute'), ($field === 'name') ? $value : $rows[0]['name']))), $_SESSION['sessionusername'] ?? '');

    $cells = pg_product_attribute_list_cells(pg_product_attribute_list_rows((int) $id)[0], $quick_edit);

    return array('ok' => true, 'value' => $value, 'html' => $cells[$field]['html'], 'class' => $cells[$field]['class'],
        'cells' => array('last_modified' => array('html' => $cells['last_modified']['html'], 'class' => $cells['last_modified']['class'])));
}
