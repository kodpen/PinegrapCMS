<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The Catalog Manager's quick edit of one product field and the commerce
 * access sub-actions (read and write the basic users' commerce rights). Each
 * handler answers through respond(), which ends the request.
 * Called by pg_explorer_handle() and loaded by view_folder_and_files_f.php.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

// A price typed in the currency, in integer minor units, or null when it is
// empty, not a number or below zero. A comma is read as the decimal point.
// round() rather than a cast: the cast truncates and the card and the feed
// then disagree by a kuruş. Zero is allowed and negative is not: a shop
// really does carry free articles -- donations, account payments -- but
// nothing costs less than nothing.
function pg_explorer_catalog_quick_price($value)
{
    $number = str_replace(',', '.', (string) $value);

    if (($number === '') || (is_numeric($number) == false)) {
        return null;
    }

    $number = (int) round(((float) $number) * 100);

    if ($number < 0) {
        return null;
    }

    return $number;
}

// A typed stock count in whole units, or null when it is empty or not a
// number. Stock does not go negative: a shelf holds none or some.
function pg_explorer_catalog_quick_quantity($value)
{
    $value = (string) $value;

    if (($value === '') || (is_numeric($value) == false)) {
        return null;
    }

    $number = (int) $value;

    if ($number < 0) {
        $number = 0;
    }

    return $number;
}

// The width a column type declares ('varchar(255)' is 255), or 0 when it
// declares none.
function pg_explorer_column_length($column_type)
{
    if (preg_match('/\((\d+)\)/', (string) $column_type, $matches)) {
        return (int) $matches[1];
    }

    return 0;
}

// The three figures a shop changes all day -- price, stock and the name
// the storefront prints -- edited where they are already being read.
// The bulk panel exists for a selection and the product screen for
// everything else; neither is the right amount of ceremony for "this
// one is two lira more than it says".
//
// Deliberately three fields and no more. A general "set any column"
// endpoint would have to re-implement every rule the product screen
// carries (addresses, variant attributes, tax, zones) or quietly skip
// them, and quietly skipping them is how a catalog drifts.
function pg_explorer_handle_catalog_quick_edit($type, $request, $user)
{
    if (pg_catalog_access($user) == false) {
        respond(array('status' => 'error', 'message' => lang('Access denied')));
    }

    $quick_id = (int) (isset($request['item_id']) ? $request['item_id'] : 0);
    $quick_field = isset($request['field']) ? (string) $request['field'] : '';
    $quick_value = isset($request['value']) ? trim((string) $request['value']) : '';

    if (($quick_id <= 0) || (in_array($quick_field, array('price', 'quantity', 'short_description'), true) == false)) {
        respond(array('status' => 'error', 'message' => lang('Invalid request.')));
    }

    // Live rows only, the same rule the rest of this screen follows: a
    // product in the bin is restored before it is edited.
    $quick_row = db_item(
        "SELECT id, name, price, inventory, inventory_quantity, out_of_stock, short_description
        FROM products
        WHERE id = '" . e($quick_id) . "'" . pg_catalog_live_filter('products'));

    if (!$quick_row) {
        respond(array('status' => 'error', 'message' => lang('Sorry, we could not accept your request.')));
    }

    $quick_sql = '';
    $quick_note = '';

    // The column the field writes and the value it is given, compared
    // with the row as read above: the same value again announces
    // nothing.
    $quick_target = '';
    $quick_new = '';

    if ($quick_field === 'price') {

        $quick_number = pg_explorer_catalog_quick_price($quick_value);

        if ($quick_number === null) {
            respond(array('status' => 'error', 'message' => lang('Enter an amount for the price change.')));
        }

        $quick_sql = "price = '" . e($quick_number) . "'";
        $quick_note = lang('price changed');
        $quick_target = 'price';
        $quick_new = $quick_number;

    } elseif ($quick_field === 'quantity') {

        // A number written against a product that does not count its
        // stock is a number nobody will ever read. Saying so is better
        // than storing it and letting the operator believe the shelf
        // is now being watched.
        if ($quick_row['inventory'] != '1') {
            respond(array('status' => 'error', 'message' => lang('Stock is not tracked for this product.')));
        }

        $quick_number = pg_explorer_catalog_quick_quantity($quick_value);

        if ($quick_number === null) {
            respond(array('status' => 'error', 'message' => lang('Enter an inventory quantity.')));
        }

        // Out of stock is a consequence rather than a field: putting
        // something back on the shelf takes the flag off it, and
        // emptying the shelf puts it on, stamped the day it ran out.
        $quick_sql = "inventory_quantity = '" . e($quick_number) . "', "
            . pg_product_out_of_stock_sql(true, $quick_number, $quick_row['out_of_stock']);
        $quick_note = lang('stock quantity changed');
        $quick_target = 'inventory_quantity';
        $quick_new = $quick_number;

    } else {

        // Cut to what the column holds rather than letting MySQL do it
        // without saying so. Asked of the schema instead of assumed:
        // the width has changed before now.
        $quick_column = db_item("SHOW COLUMNS FROM products WHERE Field = 'short_description'");
        $quick_limit = ($quick_column) ? pg_explorer_column_length($quick_column['Type']) : 0;

        if (($quick_limit > 0) && (mb_strlen($quick_value) > $quick_limit)) {
            respond(array(
                'status' => 'error',
                'message' => lang(array('string' => 'This can be at most {var:1} characters long.', 'vars' => $quick_limit))));
        }

        $quick_sql = "short_description = '" . e($quick_value) . "'";
        $quick_note = lang('short description changed');
        $quick_target = 'short_description';
        $quick_new = $quick_value;
    }

    $quick_changed = ((string) $quick_row[$quick_target] !== (string) $quick_new);

    // address_name is left alone on purpose. It is derived from the
    // short description when a product is created, but by the time it
    // is being edited here it is a published address -- rewriting it
    // from a typo correction would break every link to the page.
    $audit_before = pg_audit_fetch('products', $quick_id);
    db(
        "UPDATE products
        SET " . $quick_sql . ",
            timestamp = UNIX_TIMESTAMP(),
            user = '" . e($user['id']) . "'
        WHERE id = '" . e($quick_id) . "'");
    pg_audit_record('update', 'products', $quick_id, $audit_before);

    if ($quick_field === 'price') {
        pg_price_history_record($quick_id, 'panel');
    }

    // Price and stock are what the marketplaces are told about, and
    // this is now one of the places they change. Queue only -- the
    // scheduled job does the sending. A stock count goes through the
    // stock helper, which queues the marketplace itself.
    if (($quick_field === 'price') && function_exists('pg_marketplace_product_changed')) {
        pg_marketplace_product_changed($quick_id);
    }

    // A stock write like the product screen's: inventory.changed, and
    // stock.low on the crossing, when the count really moved.
    if (($quick_field === 'quantity') && $quick_changed) {
        pg_product_stock_changed($quick_id, (int) $quick_row['inventory_quantity'], $quick_number, 'panel');
    }

    if ($quick_changed) {
        pg_announce('product.updated', array('id' => $quick_id, 'name' => (string) $quick_row['name']));
    }

    $quick_message = lang(array(
        'string' => '{var:1} was updated ({var:2}).',
        'vars' => array(h($quick_row['name']), $quick_note)));

    log_activity($quick_message, $_SESSION['sessionusername']);

    respond(array(
        'status' => 'success',
        'request' => $type,
        'message' => $quick_message));
}

// The store's counterpart to the folder access panel, with one real
// difference: a folder's rights belong to that folder, while commerce
// rights belong to the user and cover the whole store. There is no
// per-group permission to set here, and inventing one would promise a
// separation the storefront does not enforce.
//
// The three switches are the ones the user screen carries under
// "Commerce Management Rights", written to the same columns, so a
// change here and a change there are the same change. Basic users only:
// every other role manages commerce by virtue of its role, which is why
// the folder panel lists the same set.
function pg_explorer_handle_catalog_access_get($type, $request, $user)
{
    if (pg_catalog_access($user) == false) {
        respond(array('status' => 'error', 'message' => lang('Access denied')));
    }

    $response = array(
        'status' => 'success',
        'request' => $type,
        'can_manage_users' => ($user['role'] <= 2),
        'offline_payment' => (defined('ECOMMERCE_OFFLINE_PAYMENT') && (ECOMMERCE_OFFLINE_PAYMENT == true)),
        'users' => array());

    if ($user['role'] <= 2) {

        $rows = db_items(
            "SELECT
                user_id,
                user_username,
                user_manage_ecommerce,
                manage_ecommerce_reports,
                user_set_offline_payment
            FROM user
            WHERE user_role = '3'
            ORDER BY user_username");

        foreach ((array) $rows as $row) {

            $response['users'][] = array(
                'id' => (int) $row['user_id'],
                'username' => (string) $row['user_username'],
                'manage' => ($row['user_manage_ecommerce'] == 'yes'),
                'reports' => ($row['manage_ecommerce_reports'] == '1'),
                'offline_payment' => ($row['user_set_offline_payment'] == '1'));
        }
    }

    respond($response);
}

function pg_explorer_handle_catalog_access_set($type, $request, $user)
{
    if ((pg_catalog_access($user) == false) || ($user['role'] > 2)) {
        respond(array('status' => 'error', 'message' => lang('Access denied')));
    }

    $items = isset($request['users']) ? $request['users'] : array();

    if ((is_array($items) == false) || (count($items) == 0)) {
        respond(array('status' => 'error', 'message' => lang('Invalid request.')));
    }

    $saved = 0;

    foreach ($items as $item) {

        $row_user_id = (int) (isset($item['id']) ? $item['id'] : 0);

        if ($row_user_id <= 0) {
            continue;
        }

        // Basic users only, checked against the table rather than taken
        // from the request: the id arrives from a browser, and a manager
        // is not something this panel may hand rights to.
        $target = db_item("SELECT user_id FROM user WHERE (user_id = '" . e($row_user_id) . "') AND (user_role = '3')");

        if (is_array($target) == false) {
            continue;
        }

        $manage = ((isset($item['manage'])) && ($item['manage'])) ? 'yes' : 'no';
        $reports = ((isset($item['reports'])) && ($item['reports'])) ? '1' : '0';
        $offline = ((isset($item['offline_payment'])) && ($item['offline_payment'])) ? '1' : '0';

        db(
            "UPDATE user SET
                user_manage_ecommerce = '" . e($manage) . "',
                manage_ecommerce_reports = '" . e($reports) . "',
                user_set_offline_payment = '" . e($offline) . "'
            WHERE user_id = '" . e($row_user_id) . "'");

        $saved++;
    }

    if ($saved > 0) {
        log_activity(lang('Commerce Management Rights'), $_SESSION['sessionusername']);
    }

    respond(array(
        'status' => ($saved > 0) ? 'success' : 'error',
        'request' => $type,
        'count' => $saved,
        'message' => ($saved > 0) ? lang('The changes were saved.') : lang('Sorry, we could not accept your request.')));
}
