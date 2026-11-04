<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Stock per location (2026.11.0, 11.0.162): the locations, a product's
 * quantity at each, and the one writer that keeps the total.
 *
 * stock_locations are the places units are kept, product_stock one row per
 * product and location, stock_moves one row per change. The contract is the
 * sum rule: once a product has rows, products.inventory_quantity is the sum
 * of them and is written only by pg_stock_change() / pg_stock_set(), which
 * change one row, record the move and recompute the total in one
 * transaction. Every reader of the total (catalogue, cart, completion,
 * marketplaces, ERP) keeps reading products.inventory_quantity unchanged.
 *
 * A site with no location row behaves as before: pg_stock_has_locations()
 * is one cached read, and the writer then changes the total itself. The
 * first location added becomes the default and gets one row per tracked
 * product, filled from its total, so the sum holds from the first moment.
 *
 * The writer announces nothing: its caller passes the returned before/after
 * to pg_product_stock_changed() once per product, as every stock writer does.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/** Largest quantity a row or a total may hold (the columns are INT UNSIGNED, kept signed-safe). */
define('PG_STOCK_QUANTITY_MAX', 2147483647);

/** Longest location name kept (stock_locations.name is VARCHAR(100)). */
define('PG_STOCK_NAME_LENGTH', 100);

/** Longest reference and note kept on a move (VARCHAR(64) and VARCHAR(255)). */
define('PG_STOCK_REF_LENGTH', 64);
define('PG_STOCK_NOTE_LENGTH', 255);

/**
 * Whether the upgrade has created the stock location tables.
 *
 * @return bool
 */
function pg_stock_ready()
{
    return function_exists('pg_schema_has')
        && pg_schema_has('stock_locations')
        && pg_schema_has('product_stock')
        && pg_schema_has('stock_moves');
}

/**
 * Whether the site keeps stock per location: at least one location exists.
 * Read once per request; pass true to read again after a location is added.
 *
 * @param bool $reset
 * @return bool
 */
function pg_stock_has_locations($reset = false)
{
    static $answer = null;

    if ($reset) {
        $answer = null;
    }

    if ($answer === null) {
        $answer = pg_stock_ready()
            && ((int) db_value("SELECT COUNT(*) FROM stock_locations") > 0);
    }

    return $answer;
}

/**
 * The reasons a move may carry. A move with another reason is refused, so
 * the history can be filtered by a fixed list.
 *
 * @return array
 */
function pg_stock_reasons()
{
    return array(
        'panel', 'bulk_edit', 'import', 'checkout', 'local_sale', 'cancel', 'return',
        'barcode', 'api', 'transfer', 'count', 'marketplace', 'erp', 'workspace',
    );
}

/**
 * A quantity as a whole number from 0 to PG_STOCK_QUANTITY_MAX, or null.
 * Accepts an int, or a numeric string or float with no fraction.
 *
 * @param mixed $value
 * @return int|null
 */
function pg_stock_clean_quantity($value)
{
    $number = pg_stock_whole_number($value);

    if (($number === null) || ($number < 0) || ($number > PG_STOCK_QUANTITY_MAX)) {
        return null;
    }

    return $number;
}

/**
 * A delta as a whole number from -PG_STOCK_QUANTITY_MAX to PG_STOCK_QUANTITY_MAX,
 * or null.
 *
 * @param mixed $value
 * @return int|null
 */
function pg_stock_clean_delta($value)
{
    $number = pg_stock_whole_number($value);

    if (($number === null) || (abs($number) > PG_STOCK_QUANTITY_MAX)) {
        return null;
    }

    return $number;
}

/**
 * A value as an int when it is a whole number, else null. Booleans, lists,
 * fractions and text are refused.
 *
 * @param mixed $value
 * @return int|null
 */
function pg_stock_whole_number($value)
{
    if (is_int($value)) {
        return $value;
    }

    if (is_float($value)) {
        return (is_finite($value) && (floor($value) == $value) && (abs($value) <= PHP_INT_MAX))
            ? (int) $value
            : null;
    }

    if (is_string($value) && (preg_match('/^\s*-?[0-9]{1,12}(\.0+)?\s*$/', $value) === 1)) {
        return (int) trim($value);
    }

    return null;
}

/**
 * Which location a write goes to, from the locations that exist.
 *
 * Returns 0 when the site has no locations (the writer then changes the
 * product's total itself), the id asked for when it exists, the default
 * location's id when none was asked for (0), and null when the id asked
 * for is not a location.
 *
 * @param array $locations rows with id and is_default (pg_stock_locations())
 * @param int   $location_id
 * @return int|null
 */
function pg_stock_resolve_location(array $locations, $location_id)
{
    if (!$locations) {
        return 0;
    }

    $location_id = (int) $location_id;

    if ($location_id === 0) {
        return pg_stock_pick_default($locations);
    }

    foreach ($locations as $location) {
        if ((int) $location['id'] === $location_id) {
            return $location_id;
        }
    }

    return null;
}

/**
 * The default location's id among the rows: the one marked default, else
 * the first enabled one, else the first; 0 when there are none.
 *
 * @param array $locations
 * @return int
 */
function pg_stock_pick_default(array $locations)
{
    foreach ($locations as $location) {
        if ((int) $location['is_default'] === 1) {
            return (int) $location['id'];
        }
    }

    foreach ($locations as $location) {
        if (!isset($location['enabled']) || ((int) $location['enabled'] === 1)) {
            return (int) $location['id'];
        }
    }

    $first = reset($locations);

    return $first ? (int) $first['id'] : 0;
}

/**
 * What a change does to a product's rows, without writing: the model the
 * writer's statements follow.
 *
 * $rows is location id => quantity. A location with no row counts as 0.
 * A change that would leave the location below 0 is refused (nothing is
 * taken from another location); one that would pass PG_STOCK_QUANTITY_MAX
 * is refused too.
 *
 * @param array $rows
 * @param int   $location_id
 * @param int   $delta
 * @return array ok, error ('' | 'delta' | 'not_enough' | 'too_many'),
 *               rows, before / after (at the location), total_before, total
 */
function pg_stock_plan(array $rows, $location_id, $delta)
{
    $location_id = (int) $location_id;
    $delta = pg_stock_clean_delta($delta);

    $total_before = (int) array_sum($rows);
    $before = isset($rows[$location_id]) ? (int) $rows[$location_id] : 0;

    $result = array(
        'ok'           => false,
        'error'        => '',
        'rows'         => $rows,
        'before'       => $before,
        'after'        => $before,
        'total_before' => $total_before,
        'total'        => $total_before,
    );

    if ($delta === null) {
        $result['error'] = 'delta';
        return $result;
    }

    $after = $before + $delta;

    if ($after < 0) {
        $result['error'] = 'not_enough';
        return $result;
    }

    if (($after > PG_STOCK_QUANTITY_MAX) || ($total_before + $delta > PG_STOCK_QUANTITY_MAX)) {
        $result['error'] = 'too_many';
        return $result;
    }

    $rows[$location_id] = $after;

    $result['ok'] = true;
    $result['rows'] = $rows;
    $result['after'] = $after;
    $result['total'] = $total_before + $delta;

    return $result;
}

/**
 * The guarded statement that changes one row by $delta. A decrease only
 * matches while the row holds enough units, so two parallel writers can
 * never take the row below 0: the second one changes no row and is refused.
 *
 * @param int $product_id
 * @param int $location_id
 * @param int $delta
 * @param int $now
 * @return string
 */
function pg_stock_change_sql($product_id, $location_id, $delta, $now)
{
    $delta = (int) $delta;

    $where = "(product_id = '" . (int) $product_id . "') AND (location_id = '" . (int) $location_id . "')";

    if ($delta < 0) {
        return "UPDATE product_stock
            SET quantity = quantity - " . abs($delta) . ", updated_at = '" . (int) $now . "'
            WHERE " . $where . " AND (quantity >= " . abs($delta) . ")";
    }

    return "UPDATE product_stock
        SET quantity = quantity + " . $delta . ", updated_at = '" . (int) $now . "'
        WHERE " . $where . " AND (quantity <= " . (PG_STOCK_QUANTITY_MAX - $delta) . ")";
}

/**
 * The statement that writes the sum of a product's rows into its total.
 *
 * @param int $product_id
 * @return string
 */
function pg_stock_sum_sql($product_id)
{
    $product_id = (int) $product_id;

    return "UPDATE products
        SET inventory_quantity = (
            SELECT COALESCE(SUM(quantity), 0) FROM product_stock WHERE product_id = '" . $product_id . "')
        WHERE id = '" . $product_id . "'";
}

/**
 * The guarded statement that changes a product's total when the site has
 * no locations, the same shape as the inventory API's atomic decrement.
 *
 * @param int $product_id
 * @param int $delta
 * @return string
 */
function pg_stock_total_sql($product_id, $delta)
{
    $delta = (int) $delta;
    $where = "id = '" . (int) $product_id . "'";

    if ($delta < 0) {
        return "UPDATE products
            SET inventory_quantity = inventory_quantity - " . abs($delta) . "
            WHERE " . $where . " AND (inventory_quantity >= " . abs($delta) . ")";
    }

    return "UPDATE products
        SET inventory_quantity = inventory_quantity + " . $delta . "
        WHERE " . $where . " AND (inventory_quantity <= " . (PG_STOCK_QUANTITY_MAX - $delta) . ")";
}

/**
 * The locations, default first, then in their order.
 *
 * @param bool $enabled_only
 * @return array rows with id, name, address, enabled, is_default, sort_order
 */
function pg_stock_locations($enabled_only = false)
{
    if (!pg_stock_ready()) {
        return array();
    }

    return db_items(
        "SELECT id, name, address, enabled, is_default, sort_order
        FROM stock_locations
        " . ($enabled_only ? "WHERE enabled = '1'" : "") . "
        ORDER BY is_default DESC, sort_order, id");
}

/**
 * The default location's id, 0 when the site has no locations.
 *
 * @return int
 */
function pg_stock_default_location()
{
    if (!pg_stock_has_locations()) {
        return 0;
    }

    return pg_stock_pick_default(pg_stock_locations());
}

/**
 * A product's quantity per location, location id => quantity. Empty when
 * the site has no locations or the product has no rows.
 *
 * @param int $product_id
 * @return array
 */
function pg_stock_rows($product_id)
{
    if (!pg_stock_has_locations()) {
        return array();
    }

    $rows = array();

    foreach (db_items("SELECT location_id, quantity FROM product_stock WHERE product_id = '" . (int) $product_id . "'") as $row) {
        $rows[(int) $row['location_id']] = (int) $row['quantity'];
    }

    return $rows;
}

/**
 * Add a location. The first one becomes the default and gets one row per
 * tracked product, filled from the product's total (those rows carry no
 * move: the total does not change).
 *
 * @param string $name
 * @param string $address
 * @return array ok, id, error ('' | 'not_ready' | 'name' | 'write')
 */
function pg_stock_location_add($name, $address = '')
{
    if (!pg_stock_ready()) {
        return array('ok' => false, 'id' => 0, 'error' => 'not_ready');
    }

    $name = trim((string) $name);

    if (($name === '') || (mb_strlen($name) > PG_STOCK_NAME_LENGTH)) {
        return array('ok' => false, 'id' => 0, 'error' => 'name');
    }

    $now = time();
    $first = !pg_stock_has_locations(true);

    $order = (int) db_value("SELECT MAX(sort_order) FROM stock_locations") + 1;

    $saved = db_q(
        "INSERT INTO stock_locations (name, address, enabled, is_default, sort_order, created_at, updated_at)
        VALUES (?, ?, 1, ?, ?, ?, ?)",
        array($name, trim((string) $address), $first ? 1 : 0, min($order, 65535), $now, $now));

    if (!$saved) {
        return array('ok' => false, 'id' => 0, 'error' => 'write');
    }

    $id = (int) mysqli_insert_id(db::$con);

    if ($first) {
        // INSERT IGNORE keeps a row that is already there, so a second run
        // after a broken request fills only what is missing.
        db("INSERT IGNORE INTO product_stock (product_id, location_id, quantity, updated_at)
            SELECT id, '" . $id . "', inventory_quantity, '" . $now . "'
            FROM products
            WHERE inventory = '1'");
    }

    pg_stock_has_locations(true);

    return array('ok' => true, 'id' => $id, 'error' => '');
}

/**
 * Give a product that has no row yet one row at the default location,
 * filled from its total, so the sum rule holds before its first change.
 * A product whose tracking was switched on after the first location was
 * added is the case it serves.
 *
 * @param int $product_id
 * @param int $default_id
 * @return void
 */
function pg_stock_seed_product($product_id, $default_id)
{
    $product_id = (int) $product_id;

    if ((int) db_value("SELECT COUNT(*) FROM product_stock WHERE product_id = '" . $product_id . "'") > 0) {
        return;
    }

    db("INSERT IGNORE INTO product_stock (product_id, location_id, quantity, updated_at)
        SELECT id, '" . (int) $default_id . "', inventory_quantity, '" . time() . "'
        FROM products
        WHERE (id = '" . $product_id . "') AND (inventory = '1')");
}

/**
 * Change a product's stock at one location by $delta: the guarded row
 * update, the move, then the sum into products.inventory_quantity, in one
 * transaction. Without locations the product's total is changed by the
 * same guarded shape and no move is written.
 *
 * Options: user_id (default USER_ID), note, transaction (false when the
 * caller holds one, as a transfer's two changes do).
 *
 * @param int    $product_id
 * @param int    $location_id 0 = the default location
 * @param int    $delta
 * @param string $reason a key of pg_stock_reasons()
 * @param string $ref    what caused it (an order number, an API key id)
 * @param array  $options
 * A product that does not track inventory has no rows and is refused
 * ('untracked') while the site has locations.
 *
 * @return array ok, error ('' | 'reason' | 'delta' | 'location' | 'product'
 *               | 'untracked' | 'not_enough' | 'too_many' | 'write'), location_id, before,
 *               after (at the location), total_before, total
 */
function pg_stock_change($product_id, $location_id, $delta, $reason, $ref = '', array $options = array())
{
    $product_id = (int) $product_id;

    $refuse = function ($error, $location = 0) {
        return array('ok' => false, 'error' => $error, 'location_id' => $location,
            'before' => null, 'after' => null, 'total_before' => null, 'total' => null);
    };

    if (!in_array((string) $reason, pg_stock_reasons(), true)) {
        return $refuse('reason');
    }

    $delta = pg_stock_clean_delta($delta);

    if ($delta === null) {
        return $refuse('delta');
    }

    $product = db_item("SELECT id, inventory, inventory_quantity FROM products WHERE id = '" . $product_id . "'");

    if (!$product) {
        return $refuse('product');
    }

    if (!pg_stock_has_locations()) {
        return pg_stock_change_total($product_id, (int) $product['inventory_quantity'], $delta);
    }

    if ((int) $product['inventory'] !== 1) {
        return $refuse('untracked');
    }

    $locations = pg_stock_locations();
    $location_id = pg_stock_resolve_location($locations, $location_id);

    if (!$location_id) {
        return $refuse('location');
    }

    $own_transaction = !isset($options['transaction']) || ($options['transaction'] !== false);

    if ($own_transaction) {
        db("START TRANSACTION");
    }

    pg_stock_seed_product($product_id, pg_stock_pick_default($locations));

    $rows = array();

    // FOR UPDATE holds the product's rows until the commit, so the total
    // written below is the sum of what this writer saw.
    foreach (db_items("SELECT location_id, quantity FROM product_stock WHERE product_id = '" . $product_id . "' FOR UPDATE") as $row) {
        $rows[(int) $row['location_id']] = (int) $row['quantity'];
    }

    $plan = pg_stock_plan($rows, $location_id, $delta);

    if (!$plan['ok']) {
        if ($own_transaction) {
            db("ROLLBACK");
        }

        return $refuse($plan['error'], $location_id);
    }

    $done = array(
        'ok'           => true,
        'error'        => '',
        'location_id'  => $location_id,
        'before'       => $plan['before'],
        'after'        => $plan['after'],
        'total_before' => $plan['total_before'],
        'total'        => $plan['total'],
    );

    // Nothing to change: no row touched, no move recorded.
    if ($delta === 0) {
        if ($own_transaction) {
            db("COMMIT");
        }

        return $done;
    }

    $now = time();

    if (!isset($rows[$location_id])) {
        db("INSERT IGNORE INTO product_stock (product_id, location_id, quantity, updated_at)
            VALUES ('" . $product_id . "', '" . $location_id . "', 0, '" . $now . "')");
    }

    $changed = db_q(pg_stock_change_sql($product_id, $location_id, $delta, $now));

    if (!$changed || (mysqli_affected_rows(db::$con) < 1)) {
        if ($own_transaction) {
            db("ROLLBACK");
        }

        return $refuse($changed ? 'not_enough' : 'write', $location_id);
    }

    $user_id = isset($options['user_id'])
        ? (int) $options['user_id']
        : (defined('USER_ID') ? (int) USER_ID : 0);

    db_q(
        "INSERT INTO stock_moves (product_id, location_id, delta, quantity_after, reason, ref, note, user_id, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
        array(
            $product_id,
            $location_id,
            $delta,
            $plan['after'],
            $reason,
            mb_substr((string) $ref, 0, PG_STOCK_REF_LENGTH),
            mb_substr(isset($options['note']) ? (string) $options['note'] : '', 0, PG_STOCK_NOTE_LENGTH),
            $user_id,
            $now,
        ));

    db(pg_stock_sum_sql($product_id));

    if ($own_transaction) {
        db("COMMIT");
    }

    return $done;
}

/**
 * Set a product's stock at one location to $quantity: the difference to
 * the row goes through pg_stock_change(), so it is guarded and recorded
 * like any change. Without locations the product's total is set.
 *
 * @param int    $product_id
 * @param int    $location_id 0 = the default location
 * @param int    $quantity
 * @param string $reason
 * @param string $ref
 * @param array  $options see pg_stock_change()
 * @return array see pg_stock_change(); error 'quantity' for a bad quantity
 */
function pg_stock_set($product_id, $location_id, $quantity, $reason, $ref = '', array $options = array())
{
    $quantity = pg_stock_clean_quantity($quantity);

    if ($quantity === null) {
        return array('ok' => false, 'error' => 'quantity', 'location_id' => 0,
            'before' => null, 'after' => null, 'total_before' => null, 'total' => null);
    }

    if (!pg_stock_has_locations()) {
        $current = (int) db_value("SELECT inventory_quantity FROM products WHERE id = '" . (int) $product_id . "'");

        return pg_stock_change($product_id, 0, $quantity - $current, $reason, $ref, $options);
    }

    $locations = pg_stock_locations();
    $resolved = pg_stock_resolve_location($locations, $location_id);

    // A product with no row yet gets its default row first, so the
    // difference below is taken from what the writer will find.
    if ($resolved) {
        pg_stock_seed_product($product_id, pg_stock_pick_default($locations));
    }

    $current = $resolved
        ? (int) db_value("SELECT quantity FROM product_stock WHERE product_id = '" . (int) $product_id . "' AND location_id = '" . (int) $resolved . "'")
        : 0;

    // A parallel change between this read and the guarded update makes the
    // row land off $quantity by that change, never below 0.
    return pg_stock_change($product_id, $resolved ? $resolved : $location_id, $quantity - $current, $reason, $ref, $options);
}

/**
 * The writer's path for a site without locations: the total changed by the
 * guarded statement, no row and no move.
 *
 * @param int $product_id
 * @param int $before
 * @param int $delta
 * @return array see pg_stock_change()
 */
function pg_stock_change_total($product_id, $before, $delta)
{
    $plan = pg_stock_plan(array(0 => $before), 0, $delta);

    $result = array(
        'ok'           => false,
        'error'        => $plan['error'],
        'location_id'  => 0,
        'before'       => $before,
        'after'        => $before,
        'total_before' => $before,
        'total'        => $before,
    );

    if (!$plan['ok']) {
        return $result;
    }

    if ($delta !== 0) {
        $changed = db_q(pg_stock_total_sql($product_id, $delta));

        if (!$changed) {
            $result['error'] = 'write';
            return $result;
        }

        if (mysqli_affected_rows(db::$con) < 1) {
            $result['error'] = 'not_enough';
            return $result;
        }
    }

    // Read back: a parallel writer may have moved the total since $before
    // was read, and the guarded statement applied $delta to what it found.
    $after = (int) db_value("SELECT inventory_quantity FROM products WHERE id = '" . (int) $product_id . "'");

    $result['ok'] = true;
    $result['before'] = $after - $delta;
    $result['after'] = $after;
    $result['total_before'] = $after - $delta;
    $result['total'] = $after;

    return $result;
}
