<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Stock per location at the points that take units away or count them in:
 * the order's location, the checkout's stock check, order completion, the
 * local sale, a bundle's components, the barcode scans and the inventory
 * API.
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
 * @param callable $delta_for function (int $at_location): int
 * @param string   $reason
 * @param string   $ref
 * @return array pg_stock_change()'s answer, plus delta (what was applied)
 */
function pg_stock_change_from_row($product_id, $location_id, $delta_for, $reason, $ref)
{
    $result = array();

    for ($attempt = 0; $attempt < 3; $attempt++) {
        $rows = pg_stock_rows($product_id);
        $total = (int) db_value("SELECT inventory_quantity FROM products WHERE id = '" . (int) $product_id . "'");
        $delta = (int) $delta_for(pg_stock_available_at($rows, $location_id, pg_stock_default_location(), $total));

        $result = pg_stock_change($product_id, $location_id, $delta, $reason, $ref);
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
