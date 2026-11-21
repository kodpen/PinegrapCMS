<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Stock per location at the points that take units away or count them in:
 * the order's location, the checkout's stock check, order completion, the
 * local sale, a bundle's components, the barcode scans, the inventory API
 * and the writers that work on the total (marketplace orders, ERP,
 * Workspace: pg_stock_total_write()).
 *
 * A site without locations takes none of these paths: each caller keeps its
 * own statement on products.inventory_quantity, so its writes are the ones
 * it made before. With locations every change goes through
 * pg_stock_change() (includes/fn/stock_locations.php), which keeps the
 * product's total as the sum of its rows.
 *
 * A sale takes its units from one location only. The checkout refuses what
 * that location cannot cover, as it refuses what the total cannot cover; a
 * completion that still finds too few units there (a parallel sale) takes
 * what is left, as the total's decrement stops at 0. Nothing is taken from
 * another location.
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
 * Which location an order takes its units from. The location already stored
 * on the order while it is an enabled location, else the first enabled
 * location a recipient's shipping method names, else the default.
 *
 * @param array $locations           rows with id, enabled, is_default (pg_stock_locations())
 * @param int   $stored              orders.stock_location_id
 * @param array $method_location_ids shipping_methods.stock_location_id per recipient, in order
 * @return int 0 when there are no locations
 */
function pg_stock_pick_order_location(array $locations, $stored, array $method_location_ids)
{
    if (!$locations) {
        return 0;
    }

    $enabled = array();

    foreach ($locations as $location) {
        if (!isset($location['enabled']) || ((int) $location['enabled'] === 1)) {
            $enabled[(int) $location['id']] = true;
        }
    }

    if (isset($enabled[(int) $stored])) {
        return (int) $stored;
    }

    foreach ($method_location_ids as $method_location_id) {
        if (isset($enabled[(int) $method_location_id])) {
            return (int) $method_location_id;
        }
    }

    return pg_stock_pick_default($locations);
}

/**
 * How many units a product has at a location, from its rows. A product with
 * no row yet holds its whole total at the default location (the writer seeds
 * that row on its first change) and nothing elsewhere.
 *
 * @param array $rows        location id => quantity (pg_stock_rows())
 * @param int   $location_id
 * @param int   $default_id
 * @param int   $total       products.inventory_quantity
 * @return int
 */
function pg_stock_available_at(array $rows, $location_id, $default_id, $total)
{
    if (!$rows) {
        return ((int) $location_id === (int) $default_id) ? max(0, (int) $total) : 0;
    }

    return isset($rows[(int) $location_id]) ? max(0, (int) $rows[(int) $location_id]) : 0;
}

/**
 * How many units a sale takes from a location that holds $at_location: the
 * line's quantity, or what is left there when that is less (a sale through
 * the stock leaves 0, as the total's decrement does).
 *
 * @param int $quantity
 * @param int $at_location
 * @return int
 */
function pg_stock_sale_amount($quantity, $at_location)
{
    return max(0, min((int) $quantity, (int) $at_location));
}

/**
 * Whether a table records the units a sale took (stock_taken, step
 * 11.0.269): order_items or order_item_components. Before the step the
 * takers write nothing and a cancel puts back the whole quantity.
 *
 * @param string $table
 * @return bool
 */
function pg_stock_taken_ready($table)
{
    return function_exists('pg_schema_has') && pg_schema_has((string) $table, 'stock_taken');
}

/**
 * Keep on an order line, or a bundle line's component row, the units its
 * sale took from the stock.
 *
 * @param string $table order_items or order_item_components
 * @param int    $id    the row
 * @param int    $taken units taken, 0 when the sale took none
 * @return void
 */
function pg_stock_taken_record($table, $id, $taken)
{
    if (((int) $id <= 0) || !in_array($table, array('order_items', 'order_item_components'), true) || !pg_stock_taken_ready($table)) {
        return;
    }

    db("UPDATE " . $table . " SET stock_taken = '" . max(0, (int) $taken) . "' WHERE id = '" . (int) $id . "'");
}

/**
 * What a sale took from the stock: with stock locations what the location
 * gave ($given); without them the smaller of the stock before and what was
 * asked, the unsigned column stopping at 0. A later line of the same
 * product passes the stock the line before left, so each line records its
 * own share. Pure.
 *
 * @param int      $before the stock before this line
 * @param int      $asked  units the line asked for
 * @param int|null $given  units the location gave, null without locations
 * @return int
 */
function pg_stock_taken_of($before, $asked, $given = null)
{
    if ($given !== null) {
        return max(0, (int) $given);
    }

    return max(0, min((int) $before, (int) $asked));
}

/**
 * How many units a cancel puts back: the smaller of what the sale took and
 * what is asked; all that is asked when nothing was recorded (a line from
 * before the column, or an untracked product). Pure.
 *
 * @param mixed $taken the row's stock_taken: null for not recorded
 * @param int   $asked
 * @return int
 */
function pg_stock_taken_put_back($taken, $asked)
{
    $asked = max(0, (int) $asked);

    if (($taken === null) || ($taken === '')) {
        return $asked;
    }

    return min($asked, max(0, (int) $taken));
}

/**
 * Lower a row's stock_taken by the units a cancel put back, so the same
 * cancel applied twice gives the stock back once. A row with nothing
 * recorded is left alone.
 *
 * @param string $table order_items or order_item_components
 * @param int    $id
 * @param int    $units
 * @return void
 */
function pg_stock_taken_returned($table, $id, $units)
{
    if (((int) $id <= 0) || ((int) $units <= 0) || !in_array($table, array('order_items', 'order_item_components'), true) || !pg_stock_taken_ready($table)) {
        return;
    }

    db("UPDATE " . $table . " SET stock_taken = GREATEST(0, CAST(stock_taken AS SIGNED) - " . (int) $units . ")
        WHERE id = '" . (int) $id . "' AND stock_taken IS NOT NULL");
}

/**
 * The change a stock API call makes at a location that holds $at_location:
 * set writes the quantity (below 0 is 0), adjust moves by the signed amount
 * and stops at 0, as the total's GREATEST(0, ...) does.
 *
 * @param string $op          set or adjust
 * @param int    $quantity
 * @param int    $at_location
 * @return int the delta for pg_stock_change()
 */
function pg_stock_api_delta($op, $quantity, $at_location)
{
    $quantity = (int) $quantity;
    $at_location = max(0, (int) $at_location);

    if ($op === 'set') {
        return max(0, $quantity) - $at_location;
    }

    return max($quantity, -$at_location);
}

/**
 * The location keys an inventory.changed or stock.low payload carries when
 * the site keeps stock per location. Empty without a location, so a site
 * without locations sends the payloads it sent before.
 *
 * @param array|null $location id, name, quantity (left at the location)
 * @return array
 */
function pg_stock_event_location_fields($location)
{
    if (!is_array($location) || empty($location['id'])) {
        return array();
    }

    return array(
        'location_id'       => (int) $location['id'],
        'location_name'     => (string) ($location['name'] ?? ''),
        'location_quantity' => (int) ($location['quantity'] ?? 0),
    );
}

/**
 * A location's name, '' for none. Read once per request.
 *
 * @param int $location_id
 * @return string
 */
function pg_stock_location_name($location_id)
{
    static $names = null;

    if ($names === null) {
        $names = array();

        foreach (pg_stock_locations() as $location) {
            $names[(int) $location['id']] = (string) $location['name'];
        }
    }

    return $names[(int) $location_id] ?? '';
}

/**
 * The location id a request names, checked: 0 for none or for a site
 * without locations, the id when it is a location, null when it is not one.
 *
 * @param mixed $value
 * @return int|null
 */
function pg_stock_requested_location($value)
{
    if (($value === null) || ($value === '') || ((string) $value === '0')) {
        return 0;
    }

    $id = pg_stock_whole_number($value);

    if (($id === null) || ($id < 1) || !pg_stock_has_locations()) {
        return null;
    }

    $resolved = pg_stock_resolve_location(pg_stock_locations(), $id);

    return $resolved ? (int) $resolved : null;
}

/**
 * The location an order takes its units from, read from the order and its
 * recipients' shipping methods. 0 when the site has no locations.
 *
 * @param int $order_id
 * @return int
 */
function pg_stock_order_location($order_id)
{
    if (!pg_stock_has_locations()) {
        return 0;
    }

    $order_id = (int) $order_id;

    $method_location_ids = db_values(
        "SELECT shipping_methods.stock_location_id
        FROM ship_tos
        LEFT JOIN shipping_methods ON ship_tos.shipping_method_id = shipping_methods.id
        WHERE (ship_tos.order_id = '" . $order_id . "') AND (shipping_methods.id IS NOT NULL)
        ORDER BY ship_tos.id");

    $stored = (int) db_value("SELECT stock_location_id FROM orders WHERE id = '" . $order_id . "'");

    return pg_stock_pick_order_location(pg_stock_locations(true), $stored, $method_location_ids);
}

/**
 * The location an order line's units go back to: the location saved on its
 * order while it is still a location, else 0 (the default). 0 without
 * locations.
 *
 * @param int $order_item_id
 * @return int
 */
function pg_stock_order_item_location($order_item_id)
{
    if (!pg_stock_has_locations()) {
        return 0;
    }

    $stored = (int) db_value(
        "SELECT orders.stock_location_id
        FROM order_items
        INNER JOIN orders ON orders.id = order_items.order_id
        WHERE order_items.id = '" . (int) $order_item_id . "'");

    return $stored ? (int) pg_stock_resolve_location(pg_stock_locations(), $stored) : 0;
}

/**
 * Store the order's location on the order at completion and answer it.
 * 0 (nothing written) when the site has no locations.
 *
 * @param int $order_id
 * @return int
 */
function pg_stock_order_location_save($order_id)
{
    $location_id = pg_stock_order_location($order_id);

    if ($location_id > 0) {
        db("UPDATE orders SET stock_location_id = '" . (int) $location_id . "' WHERE id = '" . (int) $order_id . "'");
    }

    return $location_id;
}

/**
 * A product's units at a location as the checkout counts them, or null when
 * the site has no locations (the checkout then counts the total).
 *
 * @param int $product_id
 * @param int $location_id
 * @param int $total products.inventory_quantity
 * @return int|null
 */
function pg_stock_quantity_at($product_id, $location_id, $total)
{
    if (!$location_id || !pg_stock_has_locations()) {
        return null;
    }

    return pg_stock_available_at(pg_stock_rows($product_id), $location_id, pg_stock_default_location(), $total);
}

/**
 * Change a product's units at a location by a delta worked out from what the
 * location holds, read again when a parallel writer moved the row between
 * the read and the guarded update.
 *
 * @param int      $product_id
 * @param int      $location_id
 * @param callable $delta_for function (int $at_location, int $total): int
 * @param string   $reason
 * @param string   $ref
 * @param array    $options   see pg_stock_change()
 * @return array pg_stock_change()'s answer, plus delta (what was applied)
 */
function pg_stock_change_from_row($product_id, $location_id, $delta_for, $reason, $ref, array $options = array())
{
    $result = array();

    for ($attempt = 0; $attempt < 3; $attempt++) {
        $rows = pg_stock_rows($product_id);
        $total = (int) db_value("SELECT inventory_quantity FROM products WHERE id = '" . (int) $product_id . "'");
        $delta = (int) $delta_for(pg_stock_available_at($rows, $location_id, pg_stock_default_location(), $total), $total);

        $result = pg_stock_change($product_id, $location_id, $delta, $reason, $ref, $options);
        $result['delta'] = $delta;

        if ($result['error'] !== 'not_enough') {
            break;
        }
    }

    return $result;
}

/**
 * Take a sale's units off a product at the order's location. Answers null
 * when the site has no locations: the caller then runs its own decrement of
 * the total, as before.
 *
 * @param int    $product_id
 * @param int    $quantity
 * @param int    $location_id 0 = the default location
 * @param string $reason      checkout, local_sale or marketplace
 * @param string $ref         the order number or id
 * @return array|null taken (units), location (id, name, quantity left there)
 */
function pg_stock_sale_take($product_id, $quantity, $location_id, $reason, $ref = '')
{
    if (!pg_stock_has_locations()) {
        return null;
    }

    $location_id = (int) $location_id ? (int) $location_id : pg_stock_default_location();

    $result = pg_stock_change_from_row($product_id, $location_id,
        function ($at_location) use ($quantity) {
            return -pg_stock_sale_amount($quantity, $at_location);
        },
        $reason, $ref);

    $taken = $result['ok'] ? -$result['delta'] : 0;

    $rows = pg_stock_rows($product_id);

    return array(
        'taken'    => $taken,
        'location' => array(
            'id'       => $location_id,
            'name'     => pg_stock_location_name($location_id),
            'quantity' => isset($rows[$location_id]) ? (int) $rows[$location_id] : 0,
        ),
    );
}

/**
 * The change a writer that names no stock row (a marketplace order, ERP,
 * Workspace) makes at a location that holds $at_location: set asks for a
 * new total, adjust moves by the signed amount. An increase lands whole at
 * the location; a decrease takes only what the location holds, never from
 * another location.
 *
 * @param string $op          set or adjust
 * @param int    $quantity    the total asked for (set) or the signed amount (adjust)
 * @param int    $at_location
 * @param int    $total       products.inventory_quantity
 * @return int the delta for pg_stock_change()
 */
function pg_stock_total_delta($op, $quantity, $at_location, $total)
{
    $quantity = (int) $quantity;
    $delta = ($op === 'set') ? max(0, $quantity) - max(0, (int) $total) : $quantity;

    return max($delta, -max(0, (int) $at_location));
}

/**
 * Change a tracked product's stock for a writer that works on the total
 * (marketplace orders and their cancellation, ERP documents and counts,
 * Workspace). Answers null when the site has no locations or the product
 * does not track stock (it has no rows): the caller then runs its own
 * statement on products.inventory_quantity, as before. With locations the
 * change goes through pg_stock_change() at $location_id, so the rows and
 * the total stay one sum and the move is recorded.
 *
 * @param int    $product_id
 * @param string $op          set or adjust (see pg_stock_total_delta())
 * @param int    $quantity
 * @param int    $location_id 0 = the default location
 * @param string $reason      a key of pg_stock_reasons()
 * @param string $ref         what caused it (an order number, a document id)
 * @param array  $options     see pg_stock_change() (transaction => false inside a caller's transaction)
 * @return array|null pg_stock_change()'s answer, plus delta (what was applied)
 */
function pg_stock_total_write($product_id, $op, $quantity, $location_id, $reason, $ref = '', array $options = array())
{
    if (!pg_stock_has_locations()) {
        return null;
    }

    if ((string) db_value("SELECT inventory FROM products WHERE id = '" . (int) $product_id . "'") !== '1') {
        return null;
    }

    $location_id = (int) $location_id ? (int) $location_id : pg_stock_default_location();

    return pg_stock_change_from_row($product_id, $location_id,
        function ($at_location, $total) use ($op, $quantity) {
            return pg_stock_total_delta($op, $quantity, $at_location, $total);
        },
        $reason, $ref, $options);
}

/**
 * A barcode scan at a location: an increase adds the units there, a
 * decrease takes what the location holds, down to 0 (the scan screens'
 * rule for the total). Answers null when the site has no locations: the
 * screen then writes the total itself, as before.
 *
 * @param int $product_id  a product that tracks stock
 * @param int $location_id 0 = the default location
 * @param int $quantity    signed: + for an increase, - for a decrease
 * @return array|null ok, error, total_before, total, location (id, name, quantity left there)
 */
function pg_stock_scan($product_id, $location_id, $quantity)
{
    if (!pg_stock_has_locations()) {
        return null;
    }

    $location_id = (int) $location_id ? (int) $location_id : pg_stock_default_location();
    $quantity = (int) $quantity;

    $result = pg_stock_change_from_row($product_id, $location_id,
        function ($at_location) use ($quantity) {
            return ($quantity < 0) ? -pg_stock_sale_amount(-$quantity, $at_location) : $quantity;
        },
        'barcode', '');

    $rows = pg_stock_rows($product_id);

    return array(
        'ok'           => $result['ok'],
        'error'        => $result['error'],
        'total_before' => (int) $result['total_before'],
        'total'        => (int) $result['total'],
        'location'     => array(
            'id'       => $location_id,
            'name'     => pg_stock_location_name($location_id),
            'quantity' => isset($rows[$location_id]) ? (int) $rows[$location_id] : 0,
        ),
    );
}

/**
 * The location picker of a barcode scan screen: a select of the enabled
 * locations, the default first and chosen. '' without locations.
 *
 * @return string HTML
 */
function pg_stock_scan_location_field()
{
    if (!pg_stock_has_locations()) {
        return '';
    }

    $options = '';

    foreach (pg_stock_locations(true) as $location) {
        $options .= '<option value="' . (int) $location['id'] . '"' . ((int) $location['is_default'] === 1 ? ' selected' : '') . '>' . h($location['name']) . '</option>';
    }

    return '<label class="form-control form-adorn my-3">
            <span class="form-adorn-text" aria-hidden="true">' . lang('Stock Location') . ':</span>
            <select class="form-ghost" id="scanner_location_input" aria-label="' . h(lang('Stock Location')) . '">' . $options . '</select>
        </label>';
}
