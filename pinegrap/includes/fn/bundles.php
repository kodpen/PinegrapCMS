<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Product bundles: a products row with bundle = 1 whose contents are other
 * products in fixed whole quantities, kept in product_bundle_items
 * (2026.11.0, 11.0.54). A bundle is sold, priced, taxed and shipped as one
 * product; a component is a plain product (no bundle inside a bundle, no
 * donation, gift card or recurring product).
 *
 * The bundle's inventory, inventory_quantity, out_of_stock and backorder are
 * a cache of what its components allow (pg_bundle_stock()), so every reader
 * of products.inventory_quantity keeps working unchanged. With
 * bundle_pricing = 'sum' its price is the components' sum less
 * bundle_discount_bp basis points (pg_bundle_price()). The cache is
 * refreshed by pg_bundle_refresh_for(), which pg_marketplace_product_changed()
 * calls: every stock and price writer already calls that after its write,
 * pg_product_stock_changed() included. A product import calls neither and
 * ends with pg_bundle_refresh_all().
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

// The most components one bundle holds, and the most of one component.
define('PG_BUNDLE_MAX_ITEMS', 50);
define('PG_BUNDLE_MAX_QUANTITY', 9999);

/**
 * Whether the bundle schema is in place: the code lands before the upgrade
 * runs.
 *
 * @return bool
 */
function pg_bundles_ready()
{
    return pg_schema_has('product_bundle_items') && pg_schema_has('products', 'bundle');
}

/**
 * The contents a form or a caller handed over, cleaned: whole positive ids
 * and quantities, the bundle itself left out, one row per product (a product
 * named twice adds its quantities), at most PG_BUNDLE_MAX_ITEMS rows in the
 * order given.
 *
 * @param array $items     rows with product_id and quantity
 * @param int   $bundle_id
 * @return array list of array('product_id' => int, 'quantity' => int)
 */
function pg_bundle_items_normalize($items, $bundle_id = 0)
{
    $bundle_id = (int) $bundle_id;
    $merged = array();

    foreach ((array) $items as $item) {
        if (!is_array($item) || !isset($item['product_id'])) {
            continue;
        }

        $product_id = (int) $item['product_id'];
        $quantity = isset($item['quantity']) ? (int) $item['quantity'] : 1;

        if (($product_id <= 0) || ($product_id === $bundle_id) || ($quantity <= 0)) {
            continue;
        }

        if (isset($merged[$product_id])) {
            $merged[$product_id] += $quantity;
        } elseif (count($merged) < PG_BUNDLE_MAX_ITEMS) {
            $merged[$product_id] = $quantity;
        }
    }

    $list = array();

    foreach ($merged as $product_id => $quantity) {
        $list[] = array('product_id' => $product_id, 'quantity' => min($quantity, PG_BUNDLE_MAX_QUANTITY));
    }

    return $list;
}

/**
 * Why a product cannot go into a bundle, '' when it can.
 *
 * @param array $product products row: bundle, selection_type, gift_card, recurring
 * @return string the reason, in English for lang()
 */
function pg_bundle_component_problem($product)
{
    if (!empty($product['bundle'])) {
        return 'A bundle cannot hold another bundle.';
    }

    if (isset($product['selection_type']) && ($product['selection_type'] === 'donation')) {
        return 'A donation cannot be part of a bundle.';
    }

    if (!empty($product['gift_card'])) {
        return 'A gift card cannot be part of a bundle.';
    }

    if (!empty($product['recurring'])) {
        return 'A recurring product cannot be part of a bundle.';
    }

    return '';
}

/**
 * The stock a bundle's components allow.
 *
 * Each tracked component allows floor(its stock / quantity) bundles; the
 * bundle has the least of them. An untracked component sets no limit, and
 * when no component is tracked the bundle tracks no stock either. A bundle
 * with no contents is tracked with none, so it cannot be sold. Back orders
 * are allowed only when every tracked component takes them.
 *
 * @param array $components rows with quantity, inventory, inventory_quantity, backorder
 * @return array inventory (0/1), inventory_quantity, backorder_allowed (bool)
 */
function pg_bundle_stock($components)
{
    $components = (array) $components;

    if (!$components) {
        return array('inventory' => 1, 'inventory_quantity' => 0, 'backorder_allowed' => false);
    }

    $least = null;
    $backorder = true;

    foreach ($components as $component) {
        if (empty($component['inventory'])) {
            continue;
        }

        $per_bundle = max(1, (int) $component['quantity']);
        $stock = (int) $component['inventory_quantity'];
        $allows = ($stock > 0) ? intdiv($stock, $per_bundle) : 0;

        $least = ($least === null) ? $allows : min($least, $allows);

        if (empty($component['backorder'])) {
            $backorder = false;
        }
    }

    if ($least === null) {
        return array('inventory' => 0, 'inventory_quantity' => 0, 'backorder_allowed' => true);
    }

    return array('inventory' => 1, 'inventory_quantity' => $least, 'backorder_allowed' => $backorder);
}

/**
 * A bundle's price in minor units.
 *
 * 'fixed' keeps the price typed on the bundle; 'sum' adds the components'
 * prices times their quantities and takes the discount off, rounded to the
 * nearest minor unit.
 *
 * @param string $pricing     'fixed' or 'sum'
 * @param int    $own_price   products.price of the bundle
 * @param array  $components  rows with price and quantity
 * @param int    $discount_bp the discount in basis points (1250 = 12.5%)
 * @return int
 */
function pg_bundle_price($pricing, $own_price, $components, $discount_bp)
{
    if ($pricing !== 'sum') {
        return (int) $own_price;
    }

    $sum = 0;

    foreach ((array) $components as $component) {
        $sum += (int) $component['price'] * max(1, (int) $component['quantity']);
    }

    $discount_bp = min(10000, max(0, (int) $discount_bp));

    return (int) round($sum * (10000 - $discount_bp) / 10000);
}

/**
 * A discount typed as a percentage ("12.5", "12,5") in basis points, 0 for
 * anything that is not a number from 0 to 100.
 *
 * @param mixed $value
 * @return int
 */
function pg_bundle_discount_bp_from_input($value)
{
    $value = str_replace(',', '.', trim((string) $value));

    if (($value === '') || !is_numeric($value)) {
        return 0;
    }

    $bp = (int) round((float) $value * 100);

    return (($bp < 0) || ($bp > 10000)) ? 0 : $bp;
}

/**
 * A bundle's contents with what the screen and the refresh read of each
 * component, in their order.
 *
 * @param int $bundle_id
 * @return array
 */
function pg_bundle_items($bundle_id)
{
    $bundle_id = (int) $bundle_id;

    if (($bundle_id <= 0) || !pg_bundles_ready()) {
        return array();
    }

    $rows = db_q(
        "SELECT
            product_bundle_items.product_id,
            product_bundle_items.quantity,
            products.name,
            products.short_description,
            products.price,
            products.inventory,
            products.inventory_quantity,
            products.backorder
        FROM product_bundle_items
        INNER JOIN products ON products.id = product_bundle_items.product_id
        WHERE product_bundle_items.bundle_id = ?
        ORDER BY product_bundle_items.sort_order, product_bundle_items.id",
        array($bundle_id));

    if (!$rows) {
        return array();
    }

    $items = array();

    while ($row = mysqli_fetch_assoc($rows)) {
        $items[] = $row;
    }

    return $items;
}

/**
 * Why a bundle cannot hold these contents, '' when it can: every component
 * must exist and may go into a bundle, and a product that is itself in a
 * bundle cannot be one.
 *
 * @param int   $bundle_id
 * @param array $items cleaned contents (pg_bundle_items_normalize())
 * @return string the reason, in English for lang()
 */
function pg_bundle_items_problem($bundle_id, $items)
{
    if ($items && pg_bundle_is_component($bundle_id)) {
        return 'A product that is part of a bundle cannot be a bundle itself.';
    }

    $ids = array();

    foreach ((array) $items as $item) {
        $ids[] = (int) $item['product_id'];
    }

    if (!$ids) {
        return '';
    }

    $found = db_items(
        "SELECT id, bundle, selection_type, gift_card, recurring
        FROM products
        WHERE id IN (" . implode(',', $ids) . ")");

    if (count($found) !== count($ids)) {
        return 'A product in the bundle could not be found.';
    }

    foreach ($found as $product) {
        $problem = pg_bundle_component_problem($product);

        if ($problem !== '') {
            return $problem;
        }
    }

    return '';
}

/**
 * Replace a bundle's contents. Nothing is written when a component is
 * refused.
 *
 * @param int   $bundle_id
 * @param array $items rows with product_id and quantity
 * @return string '' when saved, otherwise the reason in English for lang()
 */
function pg_bundle_items_save($bundle_id, $items)
{
    $bundle_id = (int) $bundle_id;

    if (($bundle_id <= 0) || !pg_bundles_ready()) {
        return 'Bundles are not available until the software update has run.';
    }

    $items = pg_bundle_items_normalize($items, $bundle_id);
    $problem = pg_bundle_items_problem($bundle_id, $items);

    if ($problem !== '') {
        return $problem;
    }

    if (db_q("DELETE FROM product_bundle_items WHERE bundle_id = ?", array($bundle_id)) === false) {
        return 'The bundle contents could not be saved.';
    }

    foreach ($items as $sort_order => $item) {
        db_q(
            "INSERT INTO product_bundle_items (bundle_id, product_id, quantity, sort_order) VALUES (?, ?, ?, ?)",
            array($bundle_id, $item['product_id'], $item['quantity'], $sort_order));
    }

    return '';
}

/**
 * The bundles a product is a component of.
 *
 * @param int $product_id
 * @return array bundle ids
 */
function pg_bundle_ids_containing($product_id)
{
    $product_id = (int) $product_id;

    if (($product_id <= 0) || !pg_bundles_ready()) {
        return array();
    }

    return array_map('intval', db_values(
        "SELECT DISTINCT bundle_id FROM product_bundle_items WHERE product_id = '" . $product_id . "'"));
}

/**
 * Recompute one bundle's cached stock and price from its components and
 * write what changed. A stock change is announced like any other
 * (pg_product_stock_changed(), source 'bundle'); a price change is recorded
 * in the price history and queued for the marketplaces.
 *
 * @param int $bundle_id
 * @return bool whether the row changed
 */
function pg_bundle_refresh($bundle_id)
{
    $bundle_id = (int) $bundle_id;

    if (($bundle_id <= 0) || !pg_bundles_ready()) {
        return false;
    }

    $bundle = db_item(
        "SELECT price, inventory, inventory_quantity, out_of_stock, backorder, bundle, bundle_pricing, bundle_discount_bp
        FROM products
        WHERE id = '" . $bundle_id . "'
        LIMIT 1");

    if (!$bundle || empty($bundle['bundle'])) {
        return false;
    }

    $components = pg_bundle_items($bundle_id);
    $stock = pg_bundle_stock($components);
    $price = pg_bundle_price($bundle['bundle_pricing'], $bundle['price'], $components, $bundle['bundle_discount_bp']);
    $backorder = (!empty($bundle['backorder']) && $stock['backorder_allowed']) ? 1 : 0;

    $stock_changed = ((int) $bundle['inventory'] !== $stock['inventory'])
        || ((int) $bundle['inventory_quantity'] !== $stock['inventory_quantity']);
    $price_changed = ((int) $bundle['price'] !== $price);
    $backorder_changed = ((int) $bundle['backorder'] !== $backorder);

    if (!$stock_changed && !$price_changed && !$backorder_changed) {
        return false;
    }

    db("UPDATE products SET
            price = '" . $price . "',
            inventory = '" . $stock['inventory'] . "',
            inventory_quantity = '" . $stock['inventory_quantity'] . "',
            backorder = '" . $backorder . "',
            " . pg_product_out_of_stock_sql((bool) $stock['inventory'], $stock['inventory_quantity'], (string) $bundle['out_of_stock']) . "
        WHERE id = '" . $bundle_id . "'
        LIMIT 1");

    if ($price_changed) {
        pg_price_history_record($bundle_id, 'panel');
    }

    if ($stock_changed) {
        pg_product_stock_changed(
            $bundle_id,
            ((string) $bundle['inventory'] === '1') ? (int) $bundle['inventory_quantity'] : null,
            $stock['inventory_quantity'],
            'bundle');
    } else {
        pg_marketplace_product_changed($bundle_id);
    }

    return true;
}

/**
 * Refresh the bundles a product is a component of, after its stock or price
 * was written. Called from pg_marketplace_product_changed(); the refresh of a
 * bundle calls that again for the bundle itself, which holds no bundle, so
 * the guard only saves the lookups.
 *
 * @param int $product_id
 * @return int the bundles that changed
 */
function pg_bundle_refresh_for($product_id)
{
    static $running = false;

    if ($running || !pg_bundles_ready()) {
        return 0;
    }

    $running = true;
    $changed = 0;

    foreach (pg_bundle_ids_containing($product_id) as $bundle_id) {
        if (pg_bundle_refresh($bundle_id)) {
            $changed++;
        }
    }

    $running = false;

    return $changed;
}

/**
 * Refresh every bundle: after a product import, which writes stock and
 * prices without the per-product hooks.
 *
 * @return int the bundles that changed
 */
function pg_bundle_refresh_all()
{
    if (!pg_bundles_ready()) {
        return 0;
    }

    $changed = 0;

    foreach (db_values("SELECT id FROM products WHERE bundle = '1'") as $bundle_id) {
        if (pg_bundle_refresh($bundle_id)) {
            $changed++;
        }
    }

    return $changed;
}

/**
 * Whether a product is in a bundle: a component cannot be deleted.
 *
 * @param int $product_id
 * @return bool
 */
function pg_bundle_is_component($product_id)
{
    return count(pg_bundle_ids_containing($product_id)) > 0;
}

/**
 * The bundles among some products, as a set (id => true). Empty before the
 * bundle schema exists.
 *
 * @param array $product_ids
 * @return array
 */
function pg_bundle_ids_among($product_ids)
{
    $ids = array();

    foreach ((array) $product_ids as $product_id) {
        if ((int) $product_id > 0) {
            $ids[(int) $product_id] = (int) $product_id;
        }
    }

    if (!$ids || !pg_bundles_ready()) {
        return array();
    }

    $set = array();

    foreach (db_values("SELECT id FROM products WHERE (id IN (" . implode(',', $ids) . ")) AND (bundle = '1')") as $bundle_id) {
        $set[(int) $bundle_id] = true;
    }

    return $set;
}

/**
 * How much of each cart line the stock allows, the lines taken in order.
 *
 * A plain line of a product that tracks stock without back orders takes
 * what is left of it. A bundle line takes, from each such component, its
 * quantity per bundle times the bundles allowed, so a component bought alone
 * and inside a bundle is counted once. Other lines are not limited. A
 * bundle with no contents allows none.
 *
 * @param array $lines rows with id, product_id, quantity and, for a bundle,
 *                     components: rows with product_id and quantity
 * @param array $stock product_id => array(tracked, quantity, backorder)
 * @return array line id => array(allowed, unavailable): unavailable is true
 *               when the stock alone, before any other line, allows none
 */
function pg_bundle_cart_allowance($lines, $stock)
{
    $left = array();
    $limited = array();

    foreach ((array) $stock as $product_id => $row) {
        if (!empty($row['tracked']) && empty($row['backorder'])) {
            $limited[(int) $product_id] = true;
            $left[(int) $product_id] = max(0, (int) $row['quantity']);
        }
    }

    $initial = $left;
    $answer = array();

    foreach ((array) $lines as $line) {
        $wanted = max(0, (int) $line['quantity']);

        if (isset($line['components'])) {
            $allowed = $line['components'] ? $wanted : 0;
            $alone = $allowed;

            foreach ($line['components'] as $component) {
                $product_id = (int) $component['product_id'];
                $per_bundle = max(1, (int) $component['quantity']);

                if (isset($limited[$product_id])) {
                    $allowed = min($allowed, intdiv($left[$product_id], $per_bundle));
                    $alone = min($alone, intdiv($initial[$product_id], $per_bundle));
                }
            }

            foreach ($line['components'] as $component) {
                $product_id = (int) $component['product_id'];

                if (isset($limited[$product_id])) {
                    $left[$product_id] -= $allowed * max(1, (int) $component['quantity']);
                }
            }

            $answer[$line['id']] = array('allowed' => $allowed, 'unavailable' => ($alone === 0));
            continue;
        }

        $product_id = (int) $line['product_id'];

        if (!isset($limited[$product_id])) {
            $answer[$line['id']] = array('allowed' => $wanted, 'unavailable' => false);
            continue;
        }

        $allowed = min($wanted, $left[$product_id]);
        $left[$product_id] -= $allowed;

        $answer[$line['id']] = array('allowed' => $allowed, 'unavailable' => ($initial[$product_id] === 0));
    }

    return $answer;
}

/**
 * Take a sold bundle line off its components' stock and keep the contents
 * it was sold with in order_item_components, so a later edit of the bundle
 * never changes this order, its return or its invoice.
 *
 * Each tracked component with stock left goes down by its quantity per
 * bundle times the line's quantity and is announced like any sale
 * (pg_product_stock_changed()); $stock_left carries what the lines above
 * left, as the sale loops do for plain products. The bundle's own cached
 * stock follows through the refresh the announcement runs.
 *
 * @param int    $order_item_id
 * @param int    $bundle_id
 * @param int    $quantity      bundles sold on the line
 * @param string $source        checkout or local_sale
 * @param array  $stock_left    product_id => stock left, carried by the caller
 * @return void
 */
function pg_bundle_take_stock($order_item_id, $bundle_id, $quantity, $source, &$stock_left)
{
    $order_item_id = (int) $order_item_id;
    $quantity = max(0, (int) $quantity);
    $order_id = (int) db_value("SELECT order_id FROM order_items WHERE id = '" . $order_item_id . "'");
    $costs = pg_schema_has('erp_product_costs');

    foreach (pg_bundle_items($bundle_id) as $component) {
        $product_id = (int) $component['product_id'];
        $per_bundle = max(1, (int) $component['quantity']);
        $unit_cost = $costs ? (int) db_value("SELECT avg_cost FROM erp_product_costs WHERE product_id = '" . $product_id . "'") : 0;

        db_q(
            "INSERT INTO order_item_components (order_id, order_item_id, product_id, quantity, unit_cost) VALUES (?, ?, ?, ?, ?)",
            array($order_id, $order_item_id, $product_id, $per_bundle, $unit_cost));

        if (isset($stock_left[$product_id])) {
            $before = $stock_left[$product_id];
        } else {
            $now = db_item("SELECT inventory, inventory_quantity FROM products WHERE id = '" . $product_id . "' LIMIT 1");

            if (!$now || ((string) $now['inventory'] !== '1')) {
                continue;
            }

            $before = (int) $now['inventory_quantity'];
        }

        if (($before <= 0) || ($quantity === 0)) {
            continue;
        }

        db("UPDATE products SET inventory_quantity = (inventory_quantity - '" . ($per_bundle * $quantity) . "') WHERE id = '" . $product_id . "'");

        // The column is unsigned and the session is not strict, so selling
        // through the stock leaves 0; the guard raises the warning once.
        db("UPDATE products SET out_of_stock = '1', out_of_stock_timestamp = UNIX_TIMESTAMP()
            WHERE id = '" . $product_id . "' AND inventory_quantity <= 0 AND out_of_stock != '1'");

        if (mysqli_affected_rows(db::$con) > 0) {
            if (function_exists('pg_page_cache_mark_dirty')) {
                pg_page_cache_mark_dirty();
            }

            create_notification(array(
                'action' => 'out_stock',
                'type' => 'warning',
                'title' => $component['name'] . ' - ' . $component['short_description'],
                'product_id' => $product_id));
        }

        $stock_left[$product_id] = pg_product_stock_changed($product_id, $before, null, $source);
    }
}

/**
 * check_inventory() for a cart that holds a bundle: every line is checked
 * against one stock pool (pg_bundle_cart_allowance()), so a component bought
 * alone and inside a bundle cannot be sold twice. A line the stock cannot
 * cover is shortened or removed with the notices check_inventory() gives.
 *
 * @param object $liveform
 * @param int    $order_id
 * @param array  $bundle_ids the bundles in the cart, as a set
 * @return void
 */
function pg_bundle_check_inventory($liveform, $order_id, $bundle_ids)
{
    $rows = db_items(
        "SELECT
            order_items.id,
            order_items.product_id,
            order_items.quantity,
            ship_tos.id AS ship_to_id,
            ship_tos.complete AS ship_to_complete,
            products.name,
            products.short_description,
            products.out_of_stock_message,
            products.inventory,
            products.inventory_quantity,
            products.backorder
        FROM order_items
        LEFT JOIN products ON order_items.product_id = products.id
        LEFT JOIN ship_tos ON order_items.ship_to_id = ship_tos.id
        WHERE (order_items.order_id = '" . (int) $order_id . "')" . pg_order_item_active_sql() . "
        ORDER BY order_items.id ASC");

    $stock = array();
    $lines = array();
    $contents = array();

    foreach ($rows as $row) {
        $product_id = (int) $row['product_id'];
        $line = array('id' => (int) $row['id'], 'product_id' => $product_id, 'quantity' => (int) $row['quantity']);

        if (isset($bundle_ids[$product_id])) {
            if (!isset($contents[$product_id])) {
                $contents[$product_id] = pg_bundle_items($product_id);
            }

            $line['components'] = $contents[$product_id];

            foreach ($contents[$product_id] as $component) {
                $stock[(int) $component['product_id']] = array(
                    'tracked'   => ((string) $component['inventory'] === '1'),
                    'quantity'  => (int) $component['inventory_quantity'],
                    'backorder' => ((string) $component['backorder'] === '1'));
            }
        } elseif ($row['inventory'] !== null) {
            $stock[$product_id] = array(
                'tracked'   => ((string) $row['inventory'] === '1'),
                'quantity'  => (int) $row['inventory_quantity'],
                'backorder' => ((string) $row['backorder'] === '1'));
        }

        $lines[] = $line;
    }

    $allowance = pg_bundle_cart_allowance($lines, $stock);

    foreach ($rows as $row) {
        $answer = $allowance[(int) $row['id']];

        if ($answer['allowed'] >= (int) $row['quantity']) {
            continue;
        }

        $description = trim(implode(' - ', array_filter(array((string) $row['name'], (string) $row['short_description']), 'strlen')));

        if ($description === '') {
            $description = lang('an item');
        }

        if ($answer['allowed'] === 0) {
            remove_order_item($row['id']);

            if ($answer['unavailable']) {
                $liveform->add_notice(lang(array('string' => 'We\'re sorry, {var:1} has been removed from your order because it is not currently available.', 'vars' => h($description))) . $row['out_of_stock_message']);
            } else {
                $liveform->add_notice(lang(array('string' => 'We\'re sorry, {var:1} has been removed from your order because the availabilty of the item is currently limited.', 'vars' => h($description))));
            }

            continue;
        }

        db("UPDATE order_items SET quantity = '" . (int) $answer['allowed'] . "' WHERE id = '" . (int) $row['id'] . "'");

        if (($row['ship_to_id'] != '') && ($row['ship_to_complete'] == 1)) {
            require_once(PG_FUNCTIONS_DIR . '/shipping.php');
            update_shipping_cost_for_ship_to($row['ship_to_id']);
        }

        $liveform->add_notice(lang(array('string' => 'We\'re sorry, the quantity you entered for {var:1} has been reduced because the availabilty of the item is currently limited.', 'vars' => h($description))));
    }
}

/**
 * Put back the components a sold bundle line took, from the contents it was
 * sold with (order_item_components), so an edit of the bundle since then
 * changes nothing. A component that no longer tracks stock is skipped.
 *
 * @param int    $order_item_id
 * @param int    $quantity bundles to put back
 * @param string $source   the writer, for pg_product_stock_changed()
 * @return bool false when the line was not sold as a bundle
 */
function pg_bundle_return_stock($order_item_id, $quantity, $source)
{
    if (!pg_bundles_ready()) {
        return false;
    }

    $components = db_items(
        "SELECT order_item_components.product_id, order_item_components.quantity, products.inventory, products.inventory_quantity
        FROM order_item_components
        INNER JOIN products ON products.id = order_item_components.product_id
        WHERE order_item_components.order_item_id = '" . (int) $order_item_id . "'");

    if (!$components) {
        return false;
    }

    foreach ($components as $component) {
        if ((string) $component['inventory'] !== '1') {
            continue;
        }

        $product_id = (int) $component['product_id'];
        $before = (int) $component['inventory_quantity'];

        db("UPDATE products SET inventory_quantity = inventory_quantity + '" . (max(1, (int) $component['quantity']) * max(0, (int) $quantity)) . "' WHERE id = '" . $product_id . "'");
        db("UPDATE products SET out_of_stock = '0' WHERE (id = '" . $product_id . "') AND (inventory_quantity > 0)");

        pg_product_stock_changed($product_id, $before, null, $source);
    }

    return true;
}
