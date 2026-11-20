<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Stock holds: units claimed for an order for a few minutes while its
 * payment runs, so two orders cannot both take the last unit. Kept in
 * stock_holds (2026.11.0, 11.0.253): the order, the product, the stock
 * location (0 on a site without locations), the units, expires_at and
 * created_at. One row per order, product and location; placing again
 * replaces the order's rows.
 *
 * A row is a claim, never a stock figure: products.inventory_quantity and
 * the per-location rows are read here, never written. Units free for an
 * order are the stock (at the order's location, with locations) less the
 * unexpired holds of other orders (pg_stock_hold_units_free()); a row that
 * ran out counts for nothing from that second and the general job deletes it
 * a day later (pg_stock_hold_sweep_daily()).
 *
 * Placing (pg_stock_hold_place()) takes the products the order needs in id
 * order, each under its own short GET_LOCK for the read and the write only,
 * never across a payment. A bundle line holds its tracked components, one
 * pool with the same product bought alone. Products that do not track stock
 * or take back orders are never held.
 *
 * Releasing: pg_stock_hold_release() (completion, a failed payment, the
 * return to the cart, a deleted order); a deleted product takes its rows
 * (pg_stock_hold_forget_product()).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

// How long a hold lasts after it is placed.
define('PG_STOCK_HOLD_MINUTES', 15);

// How long a product's lock is waited for; a lock not got counts as "not
// enough".
define('PG_STOCK_HOLD_LOCK_WAIT', 5);

// Rows the sweep deletes per statement, and statements per run.
define('PG_STOCK_HOLD_SWEEP_ROWS', 1000);
define('PG_STOCK_HOLD_SWEEP_BATCHES', 20);

/**
 * Whether the stock_holds table of 2026.11.0 (11.0.253) is there: files land
 * before the upgrade runs.
 *
 * @return bool
 */
function pg_stock_holds_ready()
{
    return pg_schema_has('stock_holds');
}

/**
 * Units free for an order: the stock less the unexpired holds of other
 * orders, never below 0. The order's own rows are not counted against it; a
 * row whose expires_at is now or earlier counts for nothing. Pure.
 *
 * @param int   $stock    units in stock (at the location, with locations)
 * @param array $holds    rows with order_id, quantity, expires_at
 * @param int   $order_id the order asking; 0 counts every hold
 * @param int   $now      Unix time
 * @return int
 */
function pg_stock_hold_units_free($stock, array $holds, $order_id, $now)
{
    $held = 0;
    $order_id = (int) $order_id;
    $now = (int) $now;

    foreach ($holds as $hold) {
        if (($order_id > 0) && ((int) $hold['order_id'] === $order_id)) {
            continue;
        }

        if ((int) $hold['expires_at'] <= $now) {
            continue;
        }

        $held += max(0, (int) $hold['quantity']);
    }

    return max(0, max(0, (int) $stock) - $held);
}

/**
 * The units an order needs held, product id => units, in id order (the order
 * the locks are taken in, so two placings never wait on each other in a
 * circle). A plain line holds its product; a bundle line holds each
 * component for its quantity per bundle times the line's quantity, added to
 * the same product bought alone (one pool, as pg_bundle_cart_allowance()
 * counts). A product that does not track stock, takes back orders or is not
 * in $stock is never held. Pure.
 *
 * @param array $lines rows with product_id, quantity and, for a bundle,
 *                     components: rows with product_id and quantity
 * @param array $stock product_id => array(tracked, backorder)
 * @return array
 */
function pg_stock_hold_order_needs(array $lines, array $stock)
{
    $needs = array();

    $add = function ($product_id, $units) use (&$needs, $stock) {
        $product_id = (int) $product_id;
        $units = (int) $units;

        if (($product_id <= 0) || ($units <= 0) || !isset($stock[$product_id])) {
            return;
        }

        if (empty($stock[$product_id]['tracked']) || !empty($stock[$product_id]['backorder'])) {
            return;
        }

        $needs[$product_id] = (isset($needs[$product_id]) ? $needs[$product_id] : 0) + $units;
    };

    foreach ($lines as $line) {
        $quantity = (int) $line['quantity'];

        if ($quantity <= 0) {
            continue;
        }

        if (isset($line['components']) && is_array($line['components'])) {
            foreach ($line['components'] as $component) {
                $add($component['product_id'], (int) $component['quantity'] * $quantity);
            }

            continue;
        }

        $add($line['product_id'], $quantity);
    }

    ksort($needs);

    return $needs;
}

/**
 * A location id as a hold row keeps it: 0 for none. Pure.
 *
 * @param mixed $location_id
 * @return int
 */
function pg_stock_hold_location($location_id)
{
    return max(0, (int) $location_id);
}

/**
 * The name of a product's hold lock. GET_LOCK names belong to the whole
 * database server, so the database is part of the name, hashed to stay
 * within the 64 characters a name may have. Pure.
 *
 * @param int    $product_id
 * @param string $database
 * @return string
 */
function pg_stock_hold_lock_name($product_id, $database = '')
{
    $prefix = ((string) $database === '') ? '' : substr(md5((string) $database), 0, 16) . '_';

    return 'pg_stock_hold_' . $prefix . (int) $product_id;
}

/**
 * The sweep's statement: rows that ran out a day ago or more, in a batch.
 * Pure.
 *
 * @param int $now
 * @param int $limit
 * @return string
 */
function pg_stock_hold_sweep_query($now, $limit)
{
    return "DELETE FROM stock_holds WHERE expires_at < " . ((int) $now - 86400) . " LIMIT " . max(1, (int) $limit);
}

/**
 * The order's lines and the stock flags of the products they hold, as
 * pg_stock_hold_order_needs() reads them: active lines only (a line saved
 * for later is not bought), a bundle line with its components.
 *
 * @param int $order_id
 * @return array array(lines, stock)
 */
function pg_stock_hold_order_lines($order_id)
{
    $rows = db_items(
        "SELECT
            order_items.product_id,
            order_items.quantity,
            products.inventory,
            products.backorder
        FROM order_items
        LEFT JOIN products ON order_items.product_id = products.id
        WHERE (order_items.order_id = '" . (int) $order_id . "')" . pg_order_item_active_sql() . "
        ORDER BY order_items.id ASC");

    $bundle_ids = array();

    if ($rows && pg_bundles_ready()) {
        $bundle_ids = pg_bundle_ids_among(array_column($rows, 'product_id'));
    }

    $lines = array();
    $stock = array();
    $contents = array();

    foreach ($rows as $row) {
        $product_id = (int) $row['product_id'];
        $line = array('product_id' => $product_id, 'quantity' => (int) $row['quantity']);

        if (isset($bundle_ids[$product_id])) {
            if (!isset($contents[$product_id])) {
                $contents[$product_id] = pg_bundle_items($product_id);
            }

            $line['components'] = array();

            foreach ($contents[$product_id] as $component) {
                $component_id = (int) $component['product_id'];
                $line['components'][] = array('product_id' => $component_id, 'quantity' => (int) $component['quantity']);
                $stock[$component_id] = array(
                    'tracked'   => ((string) $component['inventory'] === '1'),
                    'backorder' => ((string) $component['backorder'] === '1'));
            }
        } elseif ($row['inventory'] !== null) {
            $stock[$product_id] = array(
                'tracked'   => ((string) $row['inventory'] === '1'),
                'backorder' => ((string) $row['backorder'] === '1'));
        }

        $lines[] = $line;
    }

    return array($lines, $stock);
}

/**
 * The unexpired hold rows of a product at a location.
 *
 * @param int $product_id
 * @param int $location_id
 * @param int $now
 * @return array rows with order_id, quantity, expires_at
 */
function pg_stock_hold_live_rows($product_id, $location_id, $now)
{
    return db_items(
        "SELECT order_id, quantity, expires_at
        FROM stock_holds
        WHERE
            (product_id = '" . (int) $product_id . "')
            AND (location_id = '" . pg_stock_hold_location($location_id) . "')
            AND (expires_at > '" . (int) $now . "')");
}

/**
 * Units of a product free for an order now: what the product holds (at the
 * location, with locations) less other orders' live holds. A product with no
 * holds table yet answers its stock.
 *
 * @param int $product_id
 * @param int $location_id 0 without locations
 * @param int $total       products.inventory_quantity
 * @param int $order_id
 * @return int
 */
function pg_stock_hold_free_now($product_id, $location_id, $total, $order_id)
{
    $stock = (int) $total;
    $at_location = pg_stock_quantity_at((int) $product_id, (int) $location_id, $stock);

    if ($at_location !== null) {
        $stock = (int) $at_location;
    }

    if (!pg_stock_holds_ready()) {
        return max(0, $stock);
    }

    $now = time();

    return pg_stock_hold_units_free($stock, pg_stock_hold_live_rows($product_id, $location_id, $now), $order_id, $now);
}

/**
 * Units other orders hold now, product id => units: rows of the order asking
 * and rows that ran out are left out. Pure.
 *
 * @param array $holds    rows with product_id, order_id, quantity, expires_at
 * @param int   $order_id the order asking; 0 counts every hold
 * @param int   $now      Unix time
 * @return array
 */
function pg_stock_hold_held_by_others(array $holds, $order_id, $now)
{
    $held = array();
    $order_id = (int) $order_id;

    foreach ($holds as $hold) {
        $product_id = (int) $hold['product_id'];
        $units = max(0, (int) $hold['quantity']);

        if (($order_id > 0) && ((int) $hold['order_id'] === $order_id)) {
            continue;
        }

        if (((int) $hold['expires_at'] <= (int) $now) || ($units === 0)) {
            continue;
        }

        $held[$product_id] = (isset($held[$product_id]) ? $held[$product_id] : 0) + $units;
    }

    return $held;
}

/**
 * The stock a cart check counts: the stock as it is when nothing is held, so
 * a site with no holds checks exactly as before; else the stock less the
 * held units, never below 0. Pure.
 *
 * @param int $stock units in stock (at the location, with locations)
 * @param int $held  units other orders hold
 * @return int
 */
function pg_stock_hold_less_held($stock, $held)
{
    if ((int) $held <= 0) {
        return (int) $stock;
    }

    return max(0, (int) $stock - (int) $held);
}

/**
 * Units other orders hold now of the given products at a location, product
 * id => units, read in one query for check_inventory() and
 * pg_bundle_check_inventory(). Empty before the upgrade.
 *
 * @param array $product_ids
 * @param int   $location_id 0 without locations
 * @param int   $order_id    the cart's order
 * @return array
 */
function pg_stock_hold_held_for(array $product_ids, $location_id, $order_id)
{
    $ids = array();

    foreach ($product_ids as $product_id) {
        if ((int) $product_id > 0) {
            $ids[(int) $product_id] = (int) $product_id;
        }
    }

    if (!$ids || !pg_stock_holds_ready()) {
        return array();
    }

    $now = time();

    $rows = db_items(
        "SELECT product_id, order_id, quantity, expires_at
        FROM stock_holds
        WHERE
            (product_id IN (" . implode(',', $ids) . "))
            AND (location_id = '" . pg_stock_hold_location($location_id) . "')
            AND (expires_at > '" . $now . "')");

    return pg_stock_hold_held_by_others($rows ? $rows : array(), $order_id, $now);
}

/**
 * Hold the units an order needs for PG_STOCK_HOLD_MINUTES. Each product is
 * taken in id order under its own lock, held for the read of its free units
 * and the write of the row only. When any product cannot be covered, or its
 * lock is not got in time, every hold of the order is removed and the
 * product is named. Rows of products the order no longer needs go.
 *
 * Before the upgrade has run nothing is held and the answer is placed.
 *
 * @param int $order_id
 * @return array array(placed => bool, product_id => the product short, 0)
 */
function pg_stock_hold_place($order_id)
{
    $order_id = (int) $order_id;

    if (($order_id <= 0) || !pg_stock_holds_ready()) {
        return array('placed' => true, 'product_id' => 0);
    }

    list($lines, $stock) = pg_stock_hold_order_lines($order_id);
    $needs = pg_stock_hold_order_needs($lines, $stock);
    $location_id = pg_stock_hold_location(pg_stock_order_location($order_id));

    $keep = $needs ? " AND ((location_id != '" . $location_id . "') OR (product_id NOT IN (" . implode(',', array_keys($needs)) . ")))" : '';
    db("DELETE FROM stock_holds WHERE (order_id = '" . $order_id . "')" . $keep);

    $database = defined('DB_DATABASE') ? (string) DB_DATABASE : (string) db_value("SELECT DATABASE()");

    foreach ($needs as $product_id => $units) {
        $lock = e(pg_stock_hold_lock_name($product_id, $database));

        if ((int) db_value("SELECT GET_LOCK('" . $lock . "', " . PG_STOCK_HOLD_LOCK_WAIT . ")") !== 1) {
            pg_stock_hold_release($order_id);

            return array('placed' => false, 'product_id' => $product_id);
        }

        $total = (int) db_value("SELECT inventory_quantity FROM products WHERE id = '" . (int) $product_id . "'");
        $free = pg_stock_hold_free_now($product_id, $location_id, $total, $order_id);

        if ($free >= $units) {
            $now = time();

            db(
                "INSERT INTO stock_holds (order_id, product_id, location_id, quantity, expires_at, created_at)
                VALUES ('" . $order_id . "', '" . (int) $product_id . "', '" . $location_id . "', '" . (int) $units . "', '" . ($now + PG_STOCK_HOLD_MINUTES * 60) . "', '" . $now . "')
                ON DUPLICATE KEY UPDATE quantity = VALUES(quantity), expires_at = VALUES(expires_at), created_at = VALUES(created_at)");
        }

        db("SELECT RELEASE_LOCK('" . $lock . "')");

        if ($free < $units) {
            pg_stock_hold_release($order_id);

            return array('placed' => false, 'product_id' => $product_id);
        }
    }

    return array('placed' => true, 'product_id' => 0);
}

/**
 * Remove every hold of an order: it completed, its payment failed, the
 * visitor came back to the cart, or it was deleted.
 *
 * @param int $order_id
 * @return void
 */
function pg_stock_hold_release($order_id)
{
    $order_id = (int) $order_id;

    if (($order_id > 0) && pg_stock_holds_ready()) {
        db("DELETE FROM stock_holds WHERE order_id = '" . $order_id . "'");
    }
}

/**
 * Remove the holds of a deleted product, so a product given the same id
 * after a MySQL 5.7 restart starts with none.
 *
 * @param int $product_id
 * @return void
 */
function pg_stock_hold_forget_product($product_id)
{
    $product_id = (int) $product_id;

    if (($product_id > 0) && pg_stock_holds_ready()) {
        db("DELETE FROM stock_holds WHERE product_id = '" . $product_id . "'");
    }
}

/**
 * The general job's sweep, once a day: rows that ran out a day ago, in
 * batches. Expired rows already count for nothing; this only keeps the table
 * small.
 *
 * @return int rows deleted
 */
function pg_stock_hold_sweep_daily()
{
    if (!pg_stock_holds_ready()) {
        return 0;
    }

    $path = PG_FUNCTIONS_DIR . '/data/temp/stock_holds_swept.txt';
    $last = is_file($path) ? (int) @file_get_contents($path) : 0;
    $now = time();

    if (($now - $last) < 86400) {
        return 0;
    }

    @file_put_contents($path, (string) $now, LOCK_EX);

    $removed = 0;
    $delete = pg_stock_hold_sweep_query($now, PG_STOCK_HOLD_SWEEP_ROWS);

    for ($batch = 0; $batch < PG_STOCK_HOLD_SWEEP_BATCHES; $batch++) {
        db($delete);
        $affected = max(0, (int) mysqli_affected_rows(db::$con));
        $removed += $affected;

        if ($affected < PG_STOCK_HOLD_SWEEP_ROWS) {
            break;
        }
    }

    return $removed;
}
