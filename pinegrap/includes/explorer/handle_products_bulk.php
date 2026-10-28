<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The Catalog Manager's bulk product edit sub-actions: the choices the panel
 * can offer and the apply on a selection of products. Each handler answers
 * through respond(), which ends the request.
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

// Every live group, in tree order with a depth, so the two group pickers read
// as the tree the operator is standing in rather than as a flat alphabetical
// list. A group whose parent is not in the map is left out; fifty levels is
// the floor of the walk.
function pg_explorer_bulk_product_group_options($group_map)
{
    $groups = array();

    $collect_groups = function ($parent_id, $depth) use (&$collect_groups, &$groups, $group_map) {

        if ($depth > 50) {
            return;
        }

        foreach ($group_map as $group_row) {

            if (((int) $group_row['parent_id']) !== $parent_id) {
                continue;
            }

            $groups[] = array(
                'id' => (int) $group_row['id'],
                'name' => (string) $group_row['name'],
                'depth' => $depth);

            $collect_groups((int) $group_row['id'], $depth + 1);
        }
    };

    $collect_groups(0, 0);

    return $groups;
}

// A product's price after a bulk change, in integer minor units. A money
// amount ('increase', 'decrease') arrives already in minor units; a
// percentage produces fractions of one, so the result is rounded rather than
// cast -- the cast truncates, and the card and the feed then disagree by a
// kuruş. A result at zero or below is the caller's to refuse.
function pg_explorer_bulk_product_new_price($old_price, $method, $value)
{
    $old_price = (int) $old_price;
    $new_price = $old_price;

    if ($method === 'increase') {
        $new_price = $old_price + $value;

    } elseif ($method === 'decrease') {
        $new_price = $old_price - $value;

    } elseif ($method === 'increase_percent') {
        $new_price = ((100 + $value) / 100) * $old_price;

    } elseif ($method === 'decrease_percent') {
        $new_price = ((100 - $value) / 100) * $old_price;
    }

    return (int) round($new_price);
}

// A product's stock quantity after a bulk change: set to the value ('value'),
// raised by it ('increase') or lowered by it (anything else). Stock does not
// go negative: a shelf holds none or some.
function pg_explorer_bulk_product_new_quantity($current_quantity, $mode, $value)
{
    if ($mode === 'value') {
        $new_quantity = $value;

    } elseif ($mode === 'increase') {
        $new_quantity = $current_quantity + $value;

    } else {
        $new_quantity = $current_quantity - $value;
    }

    if ($new_quantity < 0) {
        $new_quantity = 0;
    }

    return $new_quantity;
}

// ── Bulk product edit: the choices the panel can offer ──────────
//
// Which fields the panel draws depends on what this installation
// actually has: shipping zones exist only with shipping switched on,
// barcodes only with the barcode feature enabled. Asking here rather
// than guessing in the browser keeps one answer to "is this on".
function pg_explorer_handle_bulk_product_options($type, $request, $user)
{
    if (pg_catalog_access($user) == false) {
        respond(array('status' => 'error', 'message' => lang('Access denied')));
    }

    $bulk_zones = array();
    $bulk_shipping = (defined('ECOMMERCE_SHIPPING') && ECOMMERCE_SHIPPING);

    if ($bulk_shipping) {
        foreach ((array) db_items("SELECT id, name FROM zones ORDER BY name") as $zone_row) {
            $bulk_zones[] = array('id' => (int) $zone_row['id'], 'name' => (string) $zone_row['name']);
        }
    }

    $bulk_groups = pg_explorer_bulk_product_group_options(pg_catalog_group_map());

    respond(array(
        'status' => 'success',
        'request' => $type,
        'shipping' => $bulk_shipping,
        'zones' => $bulk_zones,
        'barcode_ready' => (defined('BARCODE_ENABLED') && BARCODE_ENABLED),
        // Decoded on the way out: the setting is stored HTML-encoded
        // ("&#8378;"), and the panel escapes what it prints, so a
        // symbol sent as an entity would be shown as its own source.
        'currency_symbol' => html_entity_decode(BASE_CURRENCY_SYMBOL, ENT_QUOTES, 'UTF-8'),
        'groups' => $bulk_groups));
}

// ── Bulk product edit: apply ────────────────────────────────────
//
// What edit_products.php did to a checked list, on the selection.
// Every field is optional; an untouched one is not written.
//
// Two rules carried over from that screen because they are about
// money rather than about code:
//
//   A decrease that would take a price to zero or below is refused for
//   that product rather than clamped -- a zero-priced article in a shop
//   is a giveaway, and silently making one is worse than skipping it.
//   The response says how many were left alone.
//
//   Prices are integer kuruş, and a percentage produces fractions of
//   one. round() rather than a cast: the cast truncates, and the card
//   and the feed then disagree by a kuruş.
function pg_explorer_handle_products_bulk_edit($type, $request, $user)
{
    if (pg_catalog_access($user) == false) {
        respond(array('status' => 'error', 'message' => lang('Access denied')));
    }

    $items = isset($request['items']) ? $request['items'] : array();
    $set = (isset($request['set']) && is_array($request['set'])) ? $request['set'] : array();

    if ((is_array($items) == false) || (count($items) == 0) || (count($set) == 0)) {
        respond(array('status' => 'error', 'message' => lang('Invalid request.')));
    }

    $product_ids = array();

    foreach ($items as $item) {

        $item_id = (int) (isset($item['id']) ? $item['id'] : 0);

        if ($item_id <= 0) {
            continue;
        }

        // Only live products: a binned one is restored before it is
        // edited, the same way the rest of this screen treats the bin.
        $product_row = db_item(
            "SELECT id, name, price
            FROM products
            WHERE id = '" . e($item_id) . "'" . pg_catalog_live_filter('products'));

        if ($product_row) {
            $product_ids[$item_id] = $product_row;
        }
    }

    if (count($product_ids) == 0) {
        respond(array('status' => 'error', 'message' => lang('Sorry, we could not accept your request.')));
    }

    // What the panel asked for, read once.
    $bulk_enabled = (isset($set['enabled']) && in_array((string) $set['enabled'], array('0', '1'), true)) ? (string) $set['enabled'] : '';
    $bulk_price_method = isset($set['price_method']) ? (string) $set['price_method'] : '';
    $bulk_price_value = isset($set['price_value']) ? str_replace(',', '.', trim((string) $set['price_value'])) : '';
    $bulk_inventory = (isset($set['inventory']) && in_array((string) $set['inventory'], array('0', '1'), true)) ? (string) $set['inventory'] : '';
    $bulk_quantity_mode = isset($set['quantity_mode']) ? (string) $set['quantity_mode'] : '';
    $bulk_quantity_value = isset($set['quantity_value']) ? trim((string) $set['quantity_value']) : '';
    $bulk_tax_method = isset($set['tax_method']) ? (string) $set['tax_method'] : '';
    $bulk_allow_zones = (isset($set['allow_zones']) && is_array($set['allow_zones'])) ? $set['allow_zones'] : array();
    $bulk_disallow_zones = (isset($set['disallow_zones']) && is_array($set['disallow_zones'])) ? $set['disallow_zones'] : array();
    $bulk_add_group = (int) (isset($set['add_group']) ? $set['add_group'] : 0);
    $bulk_remove_group = (int) (isset($set['remove_group']) ? $set['remove_group'] : 0);
    $bulk_barcodes = (isset($set['assign_barcodes']) && ($set['assign_barcodes'] == '1'));

    if (in_array($bulk_price_method, array('increase', 'decrease', 'increase_percent', 'decrease_percent'), true) == false) {
        $bulk_price_method = '';
    }

    if ($bulk_price_method !== '') {

        if (($bulk_price_value === '') || (is_numeric($bulk_price_value) == false) || ((float) $bulk_price_value <= 0)) {
            respond(array('status' => 'error', 'message' => lang('Enter an amount for the price change.')));
        }

        $bulk_price_value = (float) $bulk_price_value;

        // A money amount is typed in the currency and stored in kuruş;
        // a percentage is a percentage and is not converted.
        if (($bulk_price_method === 'increase') || ($bulk_price_method === 'decrease')) {
            $bulk_price_value = $bulk_price_value * 100;
        }
    }

    if (in_array($bulk_quantity_mode, array('value', 'increase', 'decrease'), true) == false) {
        $bulk_quantity_mode = '';
    }

    if ($bulk_quantity_mode !== '') {

        if (($bulk_quantity_value === '') || (is_numeric($bulk_quantity_value) == false)) {
            respond(array('status' => 'error', 'message' => lang('Enter an inventory quantity.')));
        }

        $bulk_quantity_value = (int) $bulk_quantity_value;
    }

    $bulk_tax_sql = '';

    if ($bulk_tax_method === 'zone') {

        // NULL rather than zero: "follow the zone" and "zero-rated"
        // are different statements about an article.
        $bulk_tax_sql = "tax_rate = NULL,";

    } elseif ($bulk_tax_method === 'set') {

        $bulk_tax_rate = parse_tax_rate(isset($set['tax_value']) ? $set['tax_value'] : '');

        if ($bulk_tax_rate === NULL) {
            // Asking to set a rate and giving none is a mistake, not an
            // instruction to clear: clearing has its own option.
            respond(array('status' => 'error', 'message' => lang('Enter a tax rate, or choose to follow the tax zone.')));
        }

        $bulk_tax_sql = "tax_rate = '" . e($bulk_tax_rate) . "',";
    }

    if ($bulk_remove_group > 0) {
        // Taking a product out of every group it is in would leave it
        // reachable only from All Products, which no operator means by
        // "remove from this group".
        $bulk_remove_group = isset(pg_catalog_group_map()[$bulk_remove_group]) ? $bulk_remove_group : 0;
    }

    if (($bulk_add_group > 0) && (isset(pg_catalog_group_map()[$bulk_add_group]) == false)) {
        $bulk_add_group = 0;
    }

    $anything =
        ($bulk_enabled !== '')
        || ($bulk_price_method !== '')
        || ($bulk_inventory !== '')
        || ($bulk_quantity_mode !== '')
        || ($bulk_tax_sql !== '')
        || (count($bulk_allow_zones) > 0)
        || (count($bulk_disallow_zones) > 0)
        || ($bulk_add_group > 0)
        || ($bulk_remove_group > 0)
        || $bulk_barcodes;

    if ($anything == false) {
        respond(array('status' => 'error', 'message' => lang('Invalid request.')));
    }

    $updated = 0;
    $price_changed = 0;
    $price_skipped = 0;
    $barcodes_assigned = 0;
    $notes = array();

    foreach ($product_ids as $product_id => $product_row) {

        $sql_parts = array();

        if ($bulk_enabled !== '') {

            // The tag cloud is rebuilt from the product's keywords when
            // it goes on sale and dropped when it comes off, which is
            // what edit_products.php does and what the storefront's
            // keyword cloud reads.
            db("DELETE FROM tag_cloud_keywords WHERE (item_id = '" . e($product_id) . "') AND (item_type = 'product')");

            if (
                ($bulk_enabled === '1')
                && (db_value("SELECT COUNT(*) FROM tag_cloud_keywords_xref WHERE (item_id = '" . e($product_id) . "') AND (item_type = 'product')") > 0)
            ) {
                $keywords = explode(',', (string) db_value("SELECT keywords FROM products WHERE id = '" . e($product_id) . "'"));
                $keywords = array_unique(array_filter(array_map('trim', $keywords), 'strlen'));

                foreach ($keywords as $keyword) {
                    db(
                        "INSERT INTO tag_cloud_keywords (keyword, item_id, item_type)
                        VALUES ('" . e($keyword) . "', '" . e($product_id) . "', 'product')");
                }
            }

            $sql_parts[] = "enabled = '" . e($bulk_enabled) . "'";
        }

        if ($bulk_price_method !== '') {

            $new_price = pg_explorer_bulk_product_new_price($product_row['price'], $bulk_price_method, $bulk_price_value);

            if ($new_price <= 0) {
                $price_skipped++;
            } else {
                $sql_parts[] = "price = '" . e($new_price) . "'";
                $price_changed++;
            }
        }

        $new_quantity = null;

        if ($bulk_quantity_mode !== '') {

            $current_quantity = (int) db_value("SELECT inventory_quantity FROM products WHERE id = '" . e($product_id) . "'");

            $new_quantity = pg_explorer_bulk_product_new_quantity($current_quantity, $bulk_quantity_mode, $bulk_quantity_value);

            $sql_parts[] = "inventory_quantity = '" . e($new_quantity) . "'";
        }

        if ($bulk_inventory !== '') {
            $sql_parts[] = "inventory = '" . e($bulk_inventory) . "'";
        }

        // Out of stock is a consequence, not a field the panel offers:
        // stock tracking switched off means nothing can be out of it,
        // and a quantity above zero means this one no longer is.
        if (($bulk_inventory === '0') || (($new_quantity !== null) && ($new_quantity > 0))) {
            $sql_parts[] = "out_of_stock = '0'";
        }

        foreach ($bulk_allow_zones as $zone_id) {

            $zone_id = (int) $zone_id;

            if ($zone_id <= 0) {
                continue;
            }

            if (db_value("SELECT COUNT(*) FROM products_zones_xref WHERE (product_id = '" . e($product_id) . "') AND (zone_id = '" . e($zone_id) . "')") == 0) {
                db("INSERT INTO products_zones_xref (product_id, zone_id) VALUES ('" . e($product_id) . "', '" . e($zone_id) . "')");
            }
        }

        foreach ($bulk_disallow_zones as $zone_id) {

            $zone_id = (int) $zone_id;

            if ($zone_id <= 0) {
                continue;
            }

            db("DELETE FROM products_zones_xref WHERE (product_id = '" . e($product_id) . "') AND (zone_id = '" . e($zone_id) . "')");
        }

        // A product sits in as many groups as it likes, so adding is an
        // insert if it is not already there and removing takes it out
        // of that one group only -- it keeps selling and keeps the
        // others, which is what the row menu's "remove from group"
        // already means here.
        if ($bulk_add_group > 0) {

            if (db_value("SELECT COUNT(*) FROM products_groups_xref WHERE (product = '" . e($product_id) . "') AND (product_group = '" . e($bulk_add_group) . "')") == 0) {

                $next_order = (int) db_value("SELECT MAX(sort_order) FROM products_groups_xref WHERE product_group = '" . e($bulk_add_group) . "'") + 1;

                db(
                    "INSERT INTO products_groups_xref (product, product_group, sort_order)
                    VALUES ('" . e($product_id) . "', '" . e($bulk_add_group) . "', '" . e($next_order) . "')");
            }
        }

        if ($bulk_remove_group > 0) {
            db(
                "DELETE FROM products_groups_xref
                WHERE (product = '" . e($product_id) . "') AND (product_group = '" . e($bulk_remove_group) . "')");
        }

        if ($bulk_barcodes) {
            // Skips a product that already carries one, and says so by
            // returning nothing: relabelling an article that is already
            // on a shelf is not what "assign barcodes" means.
            if (pg_assign_product_barcode($product_id) !== '') {
                $barcodes_assigned++;
            }
        }

        // The stock as stored, when this edit may move it: compared
        // with the row after the write, so only the products whose
        // stock really moved are announced. A selection is a few rows
        // an operator changed on purpose, not an import, so each one
        // is announced the way edit_products.php announces it.
        $stock_was = (($bulk_quantity_mode !== '') || ($bulk_inventory !== ''))
            ? db_item("SELECT inventory, inventory_quantity FROM products WHERE id = '" . e($product_id) . "'")
            : null;

        if ((count($sql_parts) > 0) || ($bulk_tax_sql !== '')) {

            $audit_before = pg_audit_fetch('products', $product_id);
            db(
                "UPDATE products
                SET
                    " . $bulk_tax_sql . "
                    " . implode(",\n                            ", $sql_parts) . (count($sql_parts) > 0 ? "," : "") . "
                    timestamp = UNIX_TIMESTAMP(),
                    user = '" . e($user['id']) . "'
                WHERE id = '" . e($product_id) . "'");
            pg_audit_record('update', 'products', $product_id, $audit_before);

            // A bundle's stock (and a summed price) is worked out from its
            // components: whatever this edit wrote there is put back
            // (includes/fn/bundles.php).
            pg_bundle_refresh((int) $product_id);

            if ($bulk_price_method !== '') {
                pg_price_history_queue($product_id, 'panel');
            }

            if ($stock_was) {
                $stock_now = db_item("SELECT inventory, inventory_quantity FROM products WHERE id = '" . e($product_id) . "'");
                $stock_delta = $stock_now ? pg_product_stock_delta(
                    ((string) $stock_was['inventory'] === '1'),
                    $stock_was['inventory_quantity'],
                    ((string) $stock_now['inventory'] === '1'),
                    $stock_now['inventory_quantity']) : null;

                if ($stock_delta) {
                    pg_product_stock_changed((int) $product_id, $stock_delta[0], $stock_delta[1], 'bulk_edit');
                }
            }
        }

        // Whatever wrote a price or a stock figure has to tell the
        // marketplaces, and this panel writes both. edit_products.php
        // -- the screen this one replaced -- has always done it; the
        // line did not come across, so a bulk change was the one way
        // to move a price that never reached n11.
        if (
            (($bulk_price_method !== '') || ($bulk_quantity_mode !== '') || ($bulk_inventory !== ''))
            && function_exists('pg_marketplace_product_changed')
        ) {
            pg_marketplace_product_changed($product_id);
        }

        $updated++;
    }

    // A product skipped because its price would have gone to zero or
    // below kept its price and gets no history row.
    pg_price_history_flush();

    // What was actually done, in the operator's words rather than a
    // bare count: a run that changed the price and the stock of eleven
    // products should say so.
    if ($bulk_enabled !== '') {
        $notes[] = ($bulk_enabled === '1') ? lang('published') : lang('unpublished');
    }

    if ($price_changed > 0) {
        $notes[] = lang('price changed');
    }

    if ($bulk_inventory !== '') {
        $notes[] = ($bulk_inventory === '1') ? lang('stock tracking on') : lang('stock tracking off');
    }

    if ($bulk_quantity_mode !== '') {
        $notes[] = lang('stock quantity changed');
    }

    if ($bulk_tax_sql !== '') {
        $notes[] = ($bulk_tax_method === 'zone') ? lang('tax rate cleared') : lang('tax rate changed');
    }

    if ((count($bulk_allow_zones) > 0) || (count($bulk_disallow_zones) > 0)) {
        $notes[] = lang('shipping zones changed');
    }

    if ($bulk_add_group > 0) {
        $notes[] = lang('added to a product group');
    }

    if ($bulk_remove_group > 0) {
        $notes[] = lang('removed from a product group');
    }

    $message = lang(array(
        'string' => '{var:1} products were updated ({var:2}).',
        'vars' => array($updated, implode(', ', $notes))));

    if (count($notes) == 0) {
        $message = lang(array('string' => '{var:1} products were updated', 'vars' => $updated));
    }

    // Everything below is appended as its own sentence, so the count
    // has to end like one.
    if (mb_substr($message, -1) !== '.') {
        $message .= '.';
    }

    if ($bulk_barcodes) {
        $message .= ' ' . lang(array('string' => '{var:1} barcode(s) assigned.', 'vars' => array($barcodes_assigned)));
    }

    if ($price_skipped > 0) {
        $message .= ' ' . lang(array(
            'string' => 'The price was left alone on {var:1} of them, because the change would have taken it to zero or below.',
            'vars' => $price_skipped));
    }

    log_activity($message, $_SESSION['sessionusername']);

    respond(array(
        'status' => ($price_skipped > 0) ? 'partial' : 'success',
        'request' => $type,
        'updated' => $updated,
        'price_skipped' => $price_skipped,
        'barcodes' => $barcodes_assigned,
        'message' => $message));
}
