<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Pickup at a location: a shipping method that names a stock location
 * (shipping_methods.stock_location_id) lets the customer collect the order
 * there. It has no rate, the checkout offers it only while that location is
 * enabled and holds every unit of the cart, and the order then takes its
 * units from that location (pg_stock_order_location()). The checkout, the
 * order screen, the receipt and the mails name the place.
 *
 * A site without locations never reads the column: every function here
 * answers what the caller had before.
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
 * The enabled location a pickup method names, or null.
 *
 * @param int   $location_id shipping_methods.stock_location_id
 * @param array $locations   rows with id, enabled (pg_stock_locations())
 * @return array|null
 */
function pg_stock_pickup_location_of($location_id, array $locations)
{
    $location_id = (int) $location_id;

    if ($location_id <= 0) {
        return null;
    }

    foreach ($locations as $location) {
        if (((int) $location['id'] === $location_id) && (!isset($location['enabled']) || ((int) $location['enabled'] === 1))) {
            return $location;
        }
    }

    return null;
}

/**
 * Whether a location holds every unit the cart needs.
 *
 * @param array $needs           product id => array('quantity' => units in the cart, 'total' => products.inventory_quantity)
 * @param array $rows_by_product product id => (location id => quantity) (pg_stock_rows())
 * @param int   $location_id
 * @param int   $default_id
 * @return bool
 */
function pg_stock_pickup_covers(array $needs, array $rows_by_product, $location_id, $default_id)
{
    foreach ($needs as $product_id => $need) {
        $rows = isset($rows_by_product[$product_id]) ? (array) $rows_by_product[$product_id] : array();

        if (pg_stock_available_at($rows, $location_id, $default_id, (int) $need['total']) < (int) $need['quantity']) {
            return false;
        }
    }

    return true;
}

/**
 * The pickup place as one line of plain text: the name, then the address
 * with its line breaks folded into commas.
 *
 * @param array $location row with name, address
 * @return string
 */
function pg_stock_pickup_place(array $location)
{
    $name = trim((string) ($location['name'] ?? ''));
    $parts = preg_split('/\s*[\r\n]+\s*/', trim((string) ($location['address'] ?? '')));
    $address = implode(', ', array_filter((array) $parts, 'strlen'));

    if ($address === '') {
        return $name;
    }

    return ($name === '') ? $address : $name . ', ' . $address;
}

/**
 * A shipping method's description with the pickup place added after it.
 *
 * @param string $description the method's description (HTML when $html)
 * @param string $place       pg_stock_pickup_place(), '' for no pickup
 * @param bool   $html        escape the added text
 * @return string
 */
function pg_stock_pickup_description($description, $place, $html = false)
{
    $place = (string) $place;
    $description = (string) $description;

    if ($place === '') {
        return $description;
    }

    $text = lang('Pickup at') . ': ' . $place;

    if ($html) {
        $text = h($text);
    }

    return ($description === '') ? $text : $description . '; ' . $text;
}

/**
 * The location id each shipping method names, method id => location id,
 * methods naming none left out. Empty without locations.
 *
 * @param array $method_ids
 * @return array
 */
function pg_stock_pickup_method_locations(array $method_ids)
{
    static $cache = array();

    if (!pg_stock_has_locations()) {
        return array();
    }

    $ids = array();

    foreach ($method_ids as $method_id) {
        $method_id = (int) $method_id;

        if (($method_id > 0) && !array_key_exists($method_id, $cache)) {
            $ids[$method_id] = $method_id;
            $cache[$method_id] = 0;
        }
    }

    if ($ids) {
        foreach (db_items("SELECT id, stock_location_id FROM shipping_methods WHERE id IN (" . implode(',', $ids) . ")") as $row) {
            $cache[(int) $row['id']] = (int) $row['stock_location_id'];
        }
    }

    $answer = array();

    foreach ($method_ids as $method_id) {
        $method_id = (int) $method_id;

        if (!empty($cache[$method_id])) {
            $answer[$method_id] = $cache[$method_id];
        }
    }

    return $answer;
}

/**
 * The enabled location a shipping method lets the customer collect at, or
 * null when it is not a pickup method (or the site has no locations).
 *
 * @param int $method_id
 * @return array|null row with id, name, address
 */
function pg_stock_pickup_location($method_id)
{
    $map = pg_stock_pickup_method_locations(array($method_id));

    if (empty($map[(int) $method_id])) {
        return null;
    }

    return pg_stock_pickup_location_of($map[(int) $method_id], pg_stock_locations(true));
}

/**
 * The units a cart needs from one location: its active lines of tracked
 * products that cannot be backordered, summed per product over every
 * recipient.
 *
 * @param int $order_id
 * @return array product id => array('quantity' => int, 'total' => int)
 */
function pg_stock_pickup_cart_needs($order_id)
{
    $needs = array();

    foreach (db_items(
        "SELECT order_items.product_id, SUM(order_items.quantity) AS quantity, products.inventory_quantity
        FROM order_items
        LEFT JOIN products ON order_items.product_id = products.id
        WHERE
            (order_items.order_id = '" . (int) $order_id . "')
            AND (products.inventory = '1')
            AND (products.backorder = '0')" . pg_order_item_active_sql() . "
        GROUP BY order_items.product_id, products.inventory_quantity") as $row) {
        $needs[(int) $row['product_id']] = array(
            'quantity' => (int) $row['quantity'],
            'total' => (int) $row['inventory_quantity'],
        );
    }

    return $needs;
}

/**
 * The shipping methods the checkout may offer, without the pickup methods
 * whose location is off or does not hold every unit of the cart. Keys are
 * kept. Without locations the list comes back unchanged.
 *
 * @param array $method_ids
 * @param int   $order_id
 * @return array
 */
function pg_stock_pickup_methods_filter(array $method_ids, $order_id)
{
    $map = pg_stock_pickup_method_locations($method_ids);

    if (!$map) {
        return $method_ids;
    }

    $locations = pg_stock_locations(true);
    $default_id = pg_stock_default_location();
    $needs = pg_stock_pickup_cart_needs($order_id);
    $rows_by_product = array();

    foreach (array_keys($needs) as $product_id) {
        $rows_by_product[$product_id] = pg_stock_rows($product_id);
    }

    $covers = array();

    foreach ($method_ids as $key => $method_id) {
        $location_id = $map[(int) $method_id] ?? 0;

        if (!$location_id) {
            continue;
        }

        if (!isset($covers[$location_id])) {
            $covers[$location_id] = (pg_stock_pickup_location_of($location_id, $locations) !== null)
                && pg_stock_pickup_covers($needs, $rows_by_product, $location_id, $default_id);
        }

        if (!$covers[$location_id]) {
            unset($method_ids[$key]);
        }
    }

    return $method_ids;
}

/**
 * A shipping method's description with its pickup place, for the checkout,
 * the receipt and the order screen.
 *
 * @param int    $method_id
 * @param string $description
 * @param bool   $html
 * @return string
 */
function pg_stock_pickup_describe($method_id, $description, $html = false)
{
    $location = pg_stock_pickup_location($method_id);

    return pg_stock_pickup_description($description, $location ? pg_stock_pickup_place($location) : '', $html);
}

/**
 * The ^^pickup_place^^ mail-merge field of the given addresses: each pickup
 * place once, "; " between them, empty when none is a pickup.
 *
 * @param int[] $ship_to_ids
 * @return array list of name, data
 */
function pg_stock_pickup_mail_fields($ship_to_ids)
{
    $places = array();
    $ids = array_filter(array_unique(array_map('intval', (array) $ship_to_ids)));

    if ($ids && pg_stock_has_locations()) {
        foreach (db_values("SELECT shipping_method_id FROM ship_tos WHERE id IN (" . implode(',', $ids) . ") ORDER BY id") as $method_id) {
            $location = pg_stock_pickup_location($method_id);

            if ($location) {
                $places[] = pg_stock_pickup_place($location);
            }
        }
    }

    return array(array('name' => 'pickup_place', 'data' => implode('; ', array_unique($places))));
}

/**
 * The location options of the shipping method form: "none" first, then
 * every location (a disabled one marked), the stored one selected.
 *
 * @param array $locations pg_stock_locations()
 * @param int   $selected
 * @return string HTML
 */
function pg_stock_pickup_location_options(array $locations, $selected)
{
    $selected = (int) $selected;
    $output = '<option value="0">' . h(lang('No pickup (ship to the address)')) . '</option>';

    foreach ($locations as $location) {
        $id = (int) $location['id'];
        $label = (string) $location['name'] . (((int) $location['enabled'] === 1) ? '' : ' (' . lang('Off') . ')');
        $output .= '<option value="' . $id . '"' . (($id === $selected) ? ' selected' : '') . '>' . h($label) . '</option>';
    }

    return $output;
}

/**
 * The pickup columns of a shipping method save: the posted location when it
 * is one, and with it no rate and any address type, so the method is never
 * charged or hidden by the address. Empty when the site has no locations
 * (the column is left as it is).
 *
 * @param array $post
 * @param array $locations pg_stock_locations()
 * @return array column => value
 */
function pg_stock_pickup_columns(array $post, array $locations)
{
    if (!$locations) {
        return array();
    }

    $posted = $post['stock_location_id'] ?? 0;
    $location_id = is_scalar($posted) ? (int) $posted : 0;
    $known = false;

    foreach ($locations as $location) {
        if ((int) $location['id'] === $location_id) {
            $known = true;
        }
    }

    if (!$known) {
        return array('stock_location_id' => '0');
    }

    $columns = array(
        'stock_location_id' => (string) $location_id,
        'service' => '',
        'realtime_rate' => '0',
        'base_rate' => '0',
        'variable_base_rate' => '0',
        'street_address' => '1',
        'po_box' => '1',
    );

    for ($number = 2; $number <= 4; $number++) {
        $columns['base_rate_' . $number] = '0';
        $columns['base_rate_' . $number . '_subtotal'] = '0';
    }

    foreach (array('primary_weight_rate', 'secondary_weight_rate', 'item_rate') as $rate) {
        $columns[$rate] = '0';
        $columns[$rate . '_first_item_excluded'] = '';
    }

    return $columns;
}
