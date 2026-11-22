<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Pick waves: the addresses waiting to be picked, a wave made from them by
 * a rule, its pick list (one line per product and location in shelf
 * order), the lines ticked on a phone, closing and deleting a wave, and the
 * cancelled orders that leave it. The rules themselves are the pure
 * functions of includes/fn/pick_pack.php; this module reads and writes the
 * pick_waves and pick_wave_orders tables around them.
 *
 * Nothing here reads a price, a total or a customer's e-mail: the screens
 * built on it are open to a user with the "Packing only" right.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/pick_pack.php');

// How far back an address is looked for: order_date is indexed, paid_at
// is not.
if (!defined('PG_PICK_WAITING_DAYS')) {
    define('PG_PICK_WAITING_DAYS', 90);
}

/**
 * Whether the pick and pack tables are there (step 11.0.264).
 *
 * @return bool
 */
function pg_pick_waves_ready()
{
    static $ready = null;

    if ($ready === null) {
        $ready = pg_schema_has('pick_waves') && pg_schema_has('pick_wave_orders') && pg_schema_has('order_packs');
    }

    return $ready;
}

/**
 * Whether a user may open the pick wave screens: Manager and up, the orders
 * right, or the "Packing only" right; the store must be on.
 *
 * @param array $user validate_user()'s row
 * @return bool
 */
function pg_pick_user_can(array $user)
{
    if (!defined('ECOMMERCE') || ECOMMERCE !== true) {
        return false;
    }

    return ((int) $user['role'] < 3) || !empty($user['manage_ecommerce']) || !empty($user['manage_packing']);
}

/**
 * Refuses a user who may not open the pick wave screens, and a site whose
 * database has not been upgraded yet.
 *
 * @param array $user
 * @return void
 */
function pg_pick_validate_access(array $user)
{
    if (!pg_pick_user_can($user)) {
        log_activity(lang('access denied to commerce'));
        output_error(lang('Access denied') . '.');
    }

    if (!pg_pick_waves_ready()) {
        output_error(lang('The database has not been upgraded yet. Run the software update first.'));
    }
}

/**
 * Whether a user sees the orders themselves (the order screen): not one
 * with the "Packing only" right alone.
 *
 * @param array $user
 * @return bool
 */
function pg_pick_user_sees_orders(array $user)
{
    return ((int) $user['role'] < 3) || !empty($user['manage_ecommerce']);
}

/**
 * Today's start in the site's time zone.
 *
 * @return int
 */
function pg_pick_today()
{
    return (int) mktime(0, 0, 0, (int) date('n'), (int) date('j'), (int) date('Y'));
}

/**
 * The location an order's goods are picked at: the one saved on the order,
 * else the default; 0 without locations.
 *
 * @param int $stored orders.stock_location_id
 * @return int
 */
function pg_pick_location_of($stored)
{
    return pg_bin_location_key((int) $stored);
}

/**
 * Every address that could be picked now, as pg_pick_wave_rule_match()
 * reads them: of an order placed in the last PG_PICK_WAITING_DAYS days that
 * is complete or exported and paid, with a unit to send. Shipped, packed
 * and in an open wave are flags the rule reads, not left out here.
 *
 * @param array|null $ship_to_ids only these addresses, null for all
 * @return array rows: order_id, ship_to_id, status, paid, shipped, packed,
 *               in_wave, carrier, marketplace, location_id, ship_by, units
 */
function pg_pick_waiting_addresses($ship_to_ids = null)
{
    $where = '';

    if ($ship_to_ids !== null) {
        $ids = array_filter(array_map('intval', (array) $ship_to_ids));

        if (!$ids) {
            return array();
        }

        $where = " AND ship_tos.id IN (" . implode(',', $ids) . ")";
    }

    $marketplace = pg_schema_has('marketplace_order_map')
        ? "(SELECT MIN(map.account_id) FROM marketplace_order_map AS map WHERE map.order_id = orders.id)"
        : "0";

    $rows = db_items(
        "SELECT
            ship_tos.id AS ship_to_id,
            ship_tos.order_id,
            ship_tos.ship_date,
            orders.status,
            orders.paid_at,
            orders.stock_location_id,
            shipping_methods.handle_days,
            shipping_methods.carrier,
            " . $marketplace . " AS marketplace,
            (SELECT COALESCE(SUM(order_items.quantity), 0) FROM order_items
                WHERE order_items.ship_to_id = ship_tos.id" . pg_order_item_active_sql() . ") AS units,
            (SELECT COALESCE(SUM(order_items.shipped_quantity), 0) FROM order_items
                WHERE order_items.ship_to_id = ship_tos.id" . pg_order_item_active_sql() . ") AS shipped_units,
            EXISTS (SELECT 1 FROM shipping_tracking_numbers AS tracking WHERE tracking.ship_to_id = ship_tos.id) AS tracked,
            EXISTS (SELECT 1 FROM order_packs WHERE order_packs.ship_to_id = ship_tos.id AND order_packs.status = '" . PG_PACK_PACKED . "') AS packed,
            EXISTS (SELECT 1 FROM pick_wave_orders AS member
                INNER JOIN pick_waves ON pick_waves.id = member.wave_id
                WHERE member.ship_to_id = ship_tos.id AND member.removed_at = 0 AND pick_waves.status <> '" . PG_WAVE_CLOSED . "') AS in_wave
        FROM ship_tos
        INNER JOIN orders ON orders.id = ship_tos.order_id
        LEFT JOIN shipping_methods ON shipping_methods.id = ship_tos.shipping_method_id
        WHERE
            orders.order_date >= '" . (time() - PG_PICK_WAITING_DAYS * 86400) . "'
            AND orders.status IN ('complete', 'exported')
            AND orders.paid_at > 0" . $where . "
        ORDER BY ship_tos.id");

    $out = array();

    foreach ($rows as $row) {
        if ((int) $row['units'] <= 0) {
            continue;
        }

        $ship_date = ((string) $row['ship_date'] !== '' && (string) $row['ship_date'] !== '0000-00-00') ? (int) strtotime((string) $row['ship_date']) : 0;

        $out[] = array(
            'order_id' => (int) $row['order_id'],
            'ship_to_id' => (int) $row['ship_to_id'],
            'status' => (string) $row['status'],
            'paid' => (int) $row['paid_at'] > 0,
            'shipped' => !empty($row['tracked']) || (int) $row['shipped_units'] > 0,
            'packed' => !empty($row['packed']),
            'in_wave' => !empty($row['in_wave']),
            'carrier' => (string) $row['carrier'],
            'marketplace' => ((int) $row['marketplace'] > 0) ? (string) (int) $row['marketplace'] : '',
            'location_id' => pg_pick_location_of($row['stock_location_id']),
            'ship_by' => pg_pick_ship_by($ship_date, (int) $row['paid_at'], (int) $row['handle_days']),
            'units' => (int) $row['units'],
        );
    }

    return $out;
}

/**
 * What a rule would put into a wave now, for the drawer's live line.
 *
 * @param array $rule pg_pick_wave_rule_clean()
 * @return array 'addresses', 'units'
 */
function pg_pick_wave_preview(array $rule)
{
    $match = pg_pick_wave_rule_filter(pg_pick_waiting_addresses(), $rule);
    $units = 0;

    foreach ($match as $row) {
        $units += $row['units'];
    }

    return array('addresses' => count($match), 'units' => $units);
}

// The name of the lock that keeps two waves from taking one address.
function pg_pick_waves_lock_name()
{
    $database = defined('DB_DATABASE') ? DB_DATABASE . '@' . (defined('DB_HOST') ? DB_HOST : '') : (string) db_value("SELECT DATABASE()");

    return 'pg_pick_waves_' . md5($database);
}

/**
 * Makes a wave of the addresses a rule matches, oldest ship-by day first,
 * at most PG_PICK_WAVE_MAX. Under one lock, so an address joins one open
 * wave at most.
 *
 * @param array      $rule        pg_pick_wave_rule_clean()
 * @param int        $user_id     who makes it
 * @param array|null $ship_to_ids only these addresses (a wave made by hand),
 *                                null for every waiting one
 * @return array 'ok', 'id' (the wave), 'error' ('busy', 'empty')
 */
function pg_pick_wave_make(array $rule, $user_id, $ship_to_ids = null)
{
    $lock = pg_pick_waves_lock_name();

    if ((int) db_value("SELECT GET_LOCK('" . e($lock) . "', 5)") !== 1) {
        return array('ok' => false, 'id' => 0, 'error' => 'busy');
    }

    $match = pg_pick_wave_rule_filter(pg_pick_waiting_addresses($ship_to_ids), $rule);

    if (!$match) {
        db_value("SELECT RELEASE_LOCK('" . e($lock) . "')");

        return array('ok' => false, 'id' => 0, 'error' => 'empty');
    }

    $now = time();

    db(
        "INSERT INTO pick_waves (name, location_id, status, single_item, rule, picked, created_by, created_at, closed_at)
        VALUES ('', '" . (int) $rule['location_id'] . "', '" . PG_WAVE_OPEN . "', '" . (!empty($rule['single_item']) ? 1 : 0) . "',
            " . (($ship_to_ids === null) ? "'" . e(json_encode($rule)) . "'" : "NULL") . ", NULL, '" . (int) $user_id . "', '" . $now . "', 0)");
    $wave_id = (int) mysqli_insert_id(db::$con);
    $values = array();

    foreach ($match as $row) {
        $values[] = "('" . $wave_id . "', '" . (int) $row['ship_to_id'] . "', '" . (int) $row['order_id'] . "', '" . (int) $row['ship_by'] . "', 0)";
    }

    db("INSERT INTO pick_wave_orders (wave_id, ship_to_id, order_id, ship_by, removed_at) VALUES " . implode(', ', $values));
    db_value("SELECT RELEASE_LOCK('" . e($lock) . "')");

    return array('ok' => true, 'id' => $wave_id, 'error' => '');
}

/**
 * Marks the addresses whose order was cancelled as gone from their open
 * wave (removed_at), so the wave's lists leave them out and say so.
 *
 * @param int $wave_id one wave, 0 for every open one
 * @return void
 */
function pg_pick_waves_sync_leavers($wave_id = 0)
{
    $rows = db_items(
        "SELECT member.wave_id, member.ship_to_id, member.removed_at, COALESCE(orders.status, '') AS status
        FROM pick_wave_orders AS member
        INNER JOIN pick_waves ON pick_waves.id = member.wave_id
        LEFT JOIN orders ON orders.id = member.order_id
        WHERE member.removed_at = 0 AND pick_waves.status <> '" . PG_WAVE_CLOSED . "'"
        . (((int) $wave_id > 0) ? " AND member.wave_id = '" . (int) $wave_id . "'" : ""));
    $by_wave = array();

    foreach ($rows as $row) {
        $by_wave[(int) $row['wave_id']][] = $row;
    }

    foreach ($by_wave as $id => $members) {
        $leavers = pg_pick_wave_leavers($members);

        if ($leavers) {
            db("UPDATE pick_wave_orders SET removed_at = '" . time() . "'
                WHERE wave_id = '" . (int) $id . "' AND removed_at = 0 AND ship_to_id IN (" . implode(',', array_map('intval', $leavers)) . ")");
        }
    }
}

/**
 * The waves for the Pick waves list, newest first: each with its addresses
 * still in it, their units and how many are packed.
 *
 * @return array rows: id, name, location_id, status, single_item, created_by,
 *               created_at, created_by_name, addresses, units, packed
 */
function pg_pick_wave_list()
{
    pg_pick_waves_sync_leavers();

    return db_items(
        "SELECT
            pick_waves.id, pick_waves.name, pick_waves.location_id, pick_waves.status, pick_waves.single_item,
            pick_waves.created_by, pick_waves.created_at,
            COALESCE(user.user_username, '') AS created_by_name,
            (SELECT COUNT(*) FROM pick_wave_orders AS member WHERE member.wave_id = pick_waves.id AND member.removed_at = 0) AS addresses,
            (SELECT COALESCE(SUM(order_items.quantity), 0) FROM pick_wave_orders AS member
                INNER JOIN order_items ON order_items.ship_to_id = member.ship_to_id" . pg_order_item_active_sql() . "
                WHERE member.wave_id = pick_waves.id AND member.removed_at = 0) AS units,
            (SELECT COUNT(*) FROM pick_wave_orders AS member
                INNER JOIN order_packs ON order_packs.ship_to_id = member.ship_to_id AND order_packs.status = '" . PG_PACK_PACKED . "'
                WHERE member.wave_id = pick_waves.id AND member.removed_at = 0) AS packed
        FROM pick_waves
        LEFT JOIN user ON user.user_id = pick_waves.created_by
        ORDER BY pick_waves.created_at DESC, pick_waves.id DESC
        LIMIT 500");
}

/**
 * One wave, or null.
 *
 * @param int $wave_id
 * @return array|null id, name, location_id, status, single_item, rule,
 *                    picked (decoded list), created_by, created_at, closed_at
 */
function pg_pick_wave($wave_id)
{
    $wave = db_item("SELECT * FROM pick_waves WHERE id = '" . (int) $wave_id . "'");

    if (!$wave) {
        return null;
    }

    $picked = json_decode((string) $wave['picked'], true);
    $wave['picked'] = is_array($picked) ? $picked : array();
    $wave['status'] = (int) $wave['status'];

    return $wave;
}

/**
 * A wave's name as the screens show it: its own, else its number.
 *
 * @param array $wave
 * @return string plain text
 */
function pg_pick_wave_label(array $wave)
{
    return ((string) $wave['name'] !== '') ? (string) $wave['name'] : lang(array('string' => 'Wave {var:1}', 'vars' => array((int) $wave['id'])));
}

/**
 * A wave's status as a pill: Open, Picked, Closed.
 *
 * @param int $status PG_WAVE_*
 * @return string HTML
 */
function pg_pick_wave_pill($status)
{
    $pills = array(
        PG_WAVE_OPEN => array(lang('Open'), 'pg-tone-blue'),
        PG_WAVE_PICKED => array(lang('All picked'), 'pg-tone-teal'),
        PG_WAVE_CLOSED => array(lang('Closed'), 'pg-tone-gray'),
    );
    $pill = isset($pills[(int) $status]) ? $pills[(int) $status] : $pills[PG_WAVE_OPEN];

    return '<span class="pg-pill ' . $pill[1] . '">' . h($pill[0]) . '</span>';
}

/**
 * An address's pack as a pill: Waiting, Packed, Short.
 *
 * @param int $status order_packs.status, -1 when it has no pack yet
 * @return string HTML
 */
function pg_pick_pack_pill($status)
{
    if ((int) $status === PG_PACK_PACKED) {
        return '<span class="pg-pill pg-tone-teal">' . h(lang('Packed')) . '</span>';
    }

    if ((int) $status === PG_PACK_SHORT) {
        return '<span class="pg-pill pg-tone-rose">' . h(lang('Short')) . '</span>';
    }

    return '<span class="pg-pill pg-tone-amber">' . h(lang('Waiting')) . '</span>';
}

/**
 * A wave's addresses for its Addresses view: ship-by day first, those that
 * left the wave last.
 *
 * @param int $wave_id
 * @return array rows: ship_to_id, order_id, order_number, ship_by,
 *               removed_at, units, pack_status (-1 none)
 */
function pg_pick_wave_addresses($wave_id)
{
    pg_pick_waves_sync_leavers($wave_id);

    return db_items(
        "SELECT
            member.ship_to_id, member.order_id, member.ship_by, member.removed_at,
            COALESCE(orders.order_number, '') AS order_number,
            (SELECT COALESCE(SUM(order_items.quantity), 0) FROM order_items
                WHERE order_items.ship_to_id = member.ship_to_id" . pg_order_item_active_sql() . ") AS units,
            COALESCE(order_packs.status, -1) AS pack_status
        FROM pick_wave_orders AS member
        LEFT JOIN orders ON orders.id = member.order_id
        LEFT JOIN order_packs ON order_packs.ship_to_id = member.ship_to_id
        WHERE member.wave_id = '" . (int) $wave_id . "'
        ORDER BY (member.removed_at > 0), (member.ship_by = 0), member.ship_by, member.order_id, member.ship_to_id");
}

/**
 * A wave's pick list: the lines of the addresses still in it, one per
 * product and location in shelf order, each with its shelf address, code,
 * name, variant options and picture.
 *
 * @param int $wave_id
 * @return array pg_pick_list_totals() lines, each with 'image' ('' none)
 */
function pg_pick_wave_lines($wave_id)
{
    $items = db_items(
        "SELECT
            order_items.product_id, order_items.ship_to_id, order_items.quantity,
            order_items.product_name, orders.stock_location_id,
            products.id AS catalog_id, products.name AS code, products.short_description, products.image_name
        FROM pick_wave_orders AS member
        INNER JOIN order_items ON order_items.ship_to_id = member.ship_to_id" . pg_order_item_active_sql() . "
        INNER JOIN orders ON orders.id = order_items.order_id
        LEFT JOIN products ON products.id = order_items.product_id
        WHERE member.wave_id = '" . (int) $wave_id . "' AND member.removed_at = 0");

    $product_ids = array();

    foreach ($items as $item) {
        $product_ids[(int) $item['product_id']] = true;
    }

    unset($product_ids[0]);
    $bins = array();
    $options = array();

    if ($product_ids) {
        $in = implode(',', array_keys($product_ids));

        if (pg_bins_ready()) {
            foreach (db_items("SELECT product_id, location_id, bin FROM product_bins WHERE product_id IN (" . $in . ")") as $row) {
                $bins[(int) $row['product_id']][(int) $row['location_id']] = (string) $row['bin'];
            }
        }

        foreach (db_items(
            "SELECT xref.product_id, product_attributes.label AS attribute, product_attribute_options.label AS choice
            FROM products_attributes_xref AS xref
            INNER JOIN product_attributes ON product_attributes.id = xref.attribute_id
            INNER JOIN product_attribute_options ON product_attribute_options.id = xref.option_id
            WHERE xref.product_id IN (" . $in . ") AND product_attribute_options.no_value = 0
            ORDER BY xref.product_id, xref.sort_order") as $row) {
            $options[(int) $row['product_id']][] = $row['attribute'] . ': ' . $row['choice'];
        }
    }

    $images = array();
    $rows = array();

    foreach ($items as $item) {
        $product_id = (int) $item['product_id'];
        $location_id = pg_pick_location_of($item['stock_location_id']);

        // An address typed before the first location was added stays at 0.
        $bin = isset($bins[$product_id][$location_id]) ? $bins[$product_id][$location_id] : (isset($bins[$product_id][0]) ? $bins[$product_id][0] : '');
        $name = trim(strip_tags((string) $item['short_description']));

        $rows[] = array(
            'product_id' => $product_id,
            'location_id' => $location_id,
            'ship_to_id' => (int) $item['ship_to_id'],
            'quantity' => (int) $item['quantity'],
            'bin' => $bin,
            'code' => ($item['catalog_id'] !== null) ? (string) $item['code'] : (string) $item['product_name'],
            'name' => ($name !== '') ? $name : (string) $item['product_name'],
            'options' => isset($options[$product_id]) ? $options[$product_id] : array(),
        );
        $images[$product_id] = (string) $item['image_name'];
    }

    $lines = pg_pick_list_totals($rows);

    foreach ($lines as $i => $line) {
        $lines[$i]['image'] = isset($images[$line['product_id']]) ? $images[$line['product_id']] : '';
    }

    return $lines;
}

/**
 * Ticks or unticks a line of a wave's pick list, and moves the wave to
 * Picked when every line is ticked (back to Open when one is unticked).
 *
 * @param int    $wave_id
 * @param string $key pg_pick_line_key()
 * @param bool   $on
 * @return array|null 'done', 'total', 'status'; null for a closed or
 *                    missing wave
 */
function pg_pick_wave_tick($wave_id, $key, $on)
{
    $lock = pg_pick_waves_lock_name() . '_' . (int) $wave_id;
    db_value("SELECT GET_LOCK('" . e($lock) . "', 5)");

    $wave = pg_pick_wave($wave_id);

    if (!$wave || $wave['status'] === PG_WAVE_CLOSED) {
        db_value("SELECT RELEASE_LOCK('" . e($lock) . "')");

        return null;
    }

    $picked = pg_pick_picked_toggle($wave['picked'], $key, $on);
    $list = pg_pick_list_ticked(pg_pick_wave_lines($wave_id), $picked);
    $status = pg_pick_wave_status_after($wave['status'], $list['done'], $list['total']);

    db("UPDATE pick_waves SET picked = '" . e(json_encode($picked)) . "', status = '" . (int) $status . "' WHERE id = '" . (int) $wave_id . "'");
    db_value("SELECT RELEASE_LOCK('" . e($lock) . "')");

    return array('done' => $list['done'], 'total' => $list['total'], 'status' => $status);
}

/**
 * Closes a wave: its addresses not packed wait again, free for a new wave.
 *
 * @param int $wave_id
 * @return bool whether it was open
 */
function pg_pick_wave_close($wave_id)
{
    db("UPDATE pick_waves SET status = '" . PG_WAVE_CLOSED . "', closed_at = '" . time() . "'
        WHERE id = '" . (int) $wave_id . "' AND status <> '" . PG_WAVE_CLOSED . "'");

    return mysqli_affected_rows(db::$con) > 0;
}

/**
 * Whether a wave may be deleted: none of its addresses has a closed pack
 * (packed or short).
 *
 * @param int $wave_id
 * @return bool
 */
function pg_pick_wave_deletable($wave_id)
{
    return !db_value(
        "SELECT 1 FROM pick_wave_orders AS member
        INNER JOIN order_packs ON order_packs.ship_to_id = member.ship_to_id AND order_packs.status <> '" . PG_PACK_OPEN . "'
        WHERE member.wave_id = '" . (int) $wave_id . "' LIMIT 1");
}

/**
 * Deletes a wave whose addresses are not packed: they go back to waiting,
 * and an open pack started in it belongs to no wave.
 *
 * @param int $wave_id
 * @return bool false when an address is packed
 */
function pg_pick_wave_delete($wave_id)
{
    if (!pg_pick_wave_deletable($wave_id)) {
        return false;
    }

    db("DELETE FROM pick_wave_orders WHERE wave_id = '" . (int) $wave_id . "'");
    db("UPDATE order_packs SET wave_id = 0 WHERE wave_id = '" . (int) $wave_id . "'");
    db("DELETE FROM pick_waves WHERE id = '" . (int) $wave_id . "'");

    return true;
}

/**
 * The choices of the Make a wave drawer: the carriers of the shipping
 * methods, the marketplace accounts and the enabled stock locations.
 *
 * @return array 'carriers' (key => name), 'marketplaces' (id => name),
 *               'locations' (id => name, empty without locations)
 */
function pg_pick_wave_choices()
{
    $carriers = array();
    $known = pg_shipping_carriers();

    foreach (db_values("SELECT DISTINCT carrier FROM shipping_methods WHERE carrier <> '' ORDER BY carrier") as $carrier) {
        $carriers[(string) $carrier] = isset($known[$carrier]['name']) ? (string) $known[$carrier]['name'] : (string) $carrier;
    }

    $marketplaces = array();

    if (pg_schema_has('marketplace_accounts')) {
        foreach (db_items("SELECT id, name, provider FROM marketplace_accounts ORDER BY name, id") as $row) {
            $marketplaces[(int) $row['id']] = ((string) $row['name'] !== '') ? (string) $row['name'] : ucfirst((string) $row['provider']);
        }
    }

    $locations = array();

    if (pg_stock_has_locations()) {
        foreach (pg_stock_locations(true) as $row) {
            $locations[(int) $row['id']] = (string) $row['name'];
        }
    }

    return array('carriers' => $carriers, 'marketplaces' => $marketplaces, 'locations' => $locations);
}
