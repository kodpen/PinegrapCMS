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
