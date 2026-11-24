<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The Pack screen's work (pack.php): finding an address by its order's code,
 * the addresses waiting to be packed, a pack's lines with their pictures and
 * options, one product scan checked against them, and the checked pack: its
 * row closed with packer and time, the label made through the label path,
 * the marketplace package accepted, order.packed announced and one audit
 * line written. A pack closed short keeps its missing lines, may set the
 * location's count down, tells staff (pack_short) and announces
 * order.pack_short; "Finish the pack" opens it again. The Packing card's
 * figures are read from the pack rows. The rules are the pure functions of
 * includes/fn/pick_pack.php.
 *
 * Nothing here reads a price, a total or a customer's e-mail: the screen is
 * open to a user with the "Packing only" right.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/pick_waves.php');

// Most addresses the "or pick an address" list offers.
if (!defined('PG_PACK_CHOICES_MAX')) {
    define('PG_PACK_CHOICES_MAX', 300);
}

/**
 * An address the Pack screen may open: of an order that is complete or
 * exported, with a unit to send. Null otherwise.
 *
 * @param int $ship_to_id
 * @return array|null ship_to_id, order_id, order_number, ship_by, wave_id
 *                    (its open wave, 0 none), location_id (the order's stock
 *                    location, 0 without locations)
 */
function pg_pack_address($ship_to_id)
{
    $rows = pg_pick_waiting_addresses(array((int) $ship_to_id));

    if (!$rows) {
        return null;
    }

    $row = $rows[0];
    $wave_id = (int) db_value(
        "SELECT wave_row.wave_id FROM pick_wave_orders AS wave_row
        INNER JOIN pick_waves ON pick_waves.id = wave_row.wave_id
        WHERE wave_row.ship_to_id = '" . (int) $ship_to_id . "' AND wave_row.removed_at = 0 AND pick_waves.status <> '" . PG_WAVE_CLOSED . "'
        ORDER BY wave_row.wave_id DESC LIMIT 1");

    return array(
        'ship_to_id' => (int) $row['ship_to_id'],
        'order_id' => (int) $row['order_id'],
        'order_number' => (string) db_value("SELECT order_number FROM orders WHERE id = '" . (int) $row['order_id'] . "'"),
        'ship_by' => (int) $row['ship_by'],
        'shipped' => (bool) $row['shipped'],
        'wave_id' => $wave_id,
        'location_id' => (int) $row['location_id'],
    );
}

/**
 * The addresses of the order a scanned or typed code names.
 *
 * @param string $code pg_pack_order_code()
 * @return array ship_to_ids of the order that the screen may open
 */
function pg_pack_find_code($code)
{
    $number = pg_pack_order_code($code);

    if ($number === '') {
        return array();
    }

    $ids = db_values(
        "SELECT ship_tos.id FROM ship_tos
        INNER JOIN orders ON orders.id = ship_tos.order_id
        WHERE orders.order_number = '" . e($number) . "'
        ORDER BY ship_tos.id");

    if (!$ids) {
        return array();
    }

    $out = array();

    foreach (pg_pick_waiting_addresses($ids) as $row) {
        $out[] = (int) $row['ship_to_id'];
    }

    return $out;
}

/**
 * The addresses the "or pick an address" list offers: paid, not shipped and
 * not packed, oldest ship-by day first, an address in a wave too.
 *
 * @return array rows: ship_to_id, order_number, ship_by, units
 */
function pg_pack_choices()
{
    $rows = array_values(array_filter(pg_pick_waiting_addresses(), function ($row) {
        return $row['paid'] && !$row['shipped'] && !$row['packed'];
    }));

    usort($rows, function ($a, $b) {
        if (($a['ship_by'] === 0) !== ($b['ship_by'] === 0)) {
            return ($a['ship_by'] === 0) ? 1 : -1;
        }

        return array($a['ship_by'], $a['order_id'], $a['ship_to_id']) <=> array($b['ship_by'], $b['order_id'], $b['ship_to_id']);
    });

    $rows = array_slice($rows, 0, PG_PACK_CHOICES_MAX);
    $numbers = array();

    if ($rows) {
        foreach (db_items("SELECT id, order_number FROM orders WHERE id IN (" . implode(',', array_unique(array_column($rows, 'order_id'))) . ")") as $order) {
            $numbers[(int) $order['id']] = (string) $order['order_number'];
        }
    }

    $out = array();

    foreach ($rows as $row) {
        $out[] = array(
            'ship_to_id' => $row['ship_to_id'],
            'order_number' => isset($numbers[$row['order_id']]) ? $numbers[$row['order_id']] : '',
            'ship_by' => $row['ship_by'],
            'units' => $row['units'],
        );
    }

    return $out;
}

/**
 * What the Pack screen knows of some products: the variant group, the
 * options a variant stands for ("Size: M"), and whether it is in the
 * Recycle Bin.
 *
 * @param array $ids product ids
 * @return array id => 'group_id', 'options', 'binned'
 */
function pg_pack_product_facts(array $ids)
{
    $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
    $out = array();

    if (!$ids) {
        return $out;
    }

    $in = implode(',', $ids);
    $binned = pg_catalog_bin_ready();

    foreach (db_items("SELECT id" . ($binned ? ", recycled" : "") . " FROM products WHERE id IN (" . $in . ")") as $row) {
        $out[(int) $row['id']] = array('group_id' => 0, 'options' => array(), 'binned' => $binned && ((int) $row['recycled'] === 1));
    }

    // A variant group is a select-type product group, as the product feeds
    // read it; the first one the product is in.
    foreach (db_items(
        "SELECT xref.product, xref.product_group FROM products_groups_xref AS xref
        INNER JOIN product_groups ON product_groups.id = xref.product_group AND product_groups.display_type = 'select'
        WHERE xref.product IN (" . $in . ")
        ORDER BY xref.product, xref.sort_order, xref.product_group") as $row) {
        $id = (int) $row['product'];

        if (isset($out[$id]) && $out[$id]['group_id'] === 0) {
            $out[$id]['group_id'] = (int) $row['product_group'];
        }
    }

    foreach (db_items(
        "SELECT xref.product_id, product_attributes.label AS attribute, product_attribute_options.label AS choice
        FROM products_attributes_xref AS xref
        INNER JOIN product_attributes ON product_attributes.id = xref.attribute_id
        INNER JOIN product_attribute_options ON product_attribute_options.id = xref.option_id
        WHERE xref.product_id IN (" . $in . ") AND product_attribute_options.no_value = 0
        ORDER BY xref.product_id, xref.sort_order") as $row) {
        $id = (int) $row['product_id'];

        if (isset($out[$id])) {
            $out[$id]['options'][] = rtrim((string) $row['attribute'], ': ') . ': ' . $row['choice'];
        }
    }

    return $out;
}

/**
 * The address's pack as it stands, without writing: its row, or a pack not
 * started yet (id 0), with its lines and what was scanned so far.
 *
 * @param array $address pg_pack_address()
 * @return array the order_packs row with 'lines' (pg_pack_lines_build())
 *               and 'status' as an integer
 */
function pg_pack_read(array $address)
{
    $pack = db_item("SELECT * FROM order_packs WHERE ship_to_id = '" . (int) $address['ship_to_id'] . "'");

    if (!$pack) {
        $pack = array('id' => 0, 'order_id' => (int) $address['order_id'], 'ship_to_id' => (int) $address['ship_to_id'], 'wave_id' => (int) $address['wave_id'],
            'status' => PG_PACK_OPEN, 'packer_id' => 0, 'started_at' => 0, 'packed_at' => 0, 'short_at' => 0, 'units' => 0, 'scanned' => 0, 'refused' => 0, 'scan_lines' => null, 'missing' => null);
    }

    $pack['status'] = (int) $pack['status'];
    $pack['lines'] = pg_pack_lines_build(pg_pack_items($address['ship_to_id']), json_decode((string) $pack['scan_lines'], true));
    $state = pg_pack_state($pack['lines']);
    $pack['units'] = $state['units'];
    $pack['scanned'] = $state['scanned'];

    return $pack;
}

/**
 * The address's pack, its row made on the first scan: the packer who starts
 * it and its open wave are kept.
 *
 * @param array $address pg_pack_address()
 * @param int   $user_id
 * @return array as pg_pack_read()
 */
function pg_pack_open(array $address, $user_id)
{
    // The unique key on ship_to_id keeps one row an address.
    db(
        "INSERT IGNORE INTO order_packs (order_id, ship_to_id, wave_id, status, packer_id, started_at, packed_at, short_at, units, scanned, refused, scan_lines, missing)
        VALUES ('" . (int) $address['order_id'] . "', '" . (int) $address['ship_to_id'] . "', '" . (int) $address['wave_id'] . "', '" . PG_PACK_OPEN . "', '" . (int) $user_id . "', '" . time() . "', 0, 0, 0, 0, 0, NULL, NULL)");

    return pg_pack_read($address);
}

/**
 * The address's order lines as the pack shows them: product, units, name,
 * code, picture, variant group and options.
 *
 * @param int $ship_to_id
 * @return array
 */
function pg_pack_items($ship_to_id)
{
    $items = db_items(
        "SELECT order_items.product_id, order_items.quantity, order_items.product_name,
            products.id AS catalog_id, products.name AS code, products.short_description, products.image_name
        FROM order_items
        LEFT JOIN products ON products.id = order_items.product_id
        WHERE order_items.ship_to_id = '" . (int) $ship_to_id . "'" . pg_order_item_active_sql() . "
        ORDER BY order_items.id");
    $facts = pg_pack_product_facts(array_column($items, 'product_id'));
    $out = array();

    foreach ($items as $item) {
        $id = ($item['catalog_id'] !== null) ? (int) $item['product_id'] : 0;
        $name = trim(strip_tags((string) $item['short_description']));

        $out[] = array(
            'product_id' => $id,
            'quantity' => (int) $item['quantity'],
            'name' => ($name !== '') ? $name : (string) $item['product_name'],
            'code' => ($id > 0) ? (string) $item['code'] : (string) $item['product_name'],
            'image' => ($id > 0) ? (string) $item['image_name'] : '',
            'group_id' => isset($facts[$id]) ? $facts[$id]['group_id'] : 0,
            'options' => isset($facts[$id]) ? $facts[$id]['options'] : array(),
        );
    }

    return $out;
}

// The name of the lock that lets one scan of an address count at a time.
function pg_pack_lock_name($ship_to_id)
{
    return pg_pick_waves_lock_name() . '_pack_' . (int) $ship_to_id;
}

/**
 * One product scan on an address's pack: checked against its lines, counted
 * on its row, and the pack closed as packed once every unit is scanned.
 *
 * @param array  $address pg_pack_address()
 * @param string $code    the scanned or typed product code
 * @param int    $user_id
 * @return array 'ok', 'error' ('busy', 'closed'), 'verdict'
 *               (pg_pack_scan_verdict()), 'refusal' (two products' code),
 *               'pack' (after the scan), 'closed' (this scan packed it)
 */
function pg_pack_scan(array $address, $code, $user_id)
{
    $lock = pg_pack_lock_name($address['ship_to_id']);

    if ((int) db_value("SELECT GET_LOCK('" . e($lock) . "', 5)") !== 1) {
        return array('ok' => false, 'error' => 'busy');
    }

    $pack = pg_pack_open($address, $user_id);

    if ($pack['status'] !== PG_PACK_OPEN) {
        db_value("SELECT RELEASE_LOCK('" . e($lock) . "')");

        return array('ok' => false, 'error' => 'closed', 'pack' => $pack);
    }

    $found = pg_barcode_scan_product($code);
    $product = null;

    if ($found['id'] > 0) {
        $facts = pg_pack_product_facts(array($found['id']));
        $product = isset($facts[$found['id']]) ? array('id' => $found['id']) + $facts[$found['id']] : null;
    }

    $verdict = pg_pack_scan_verdict($pack['lines'], $product);
    $pack = pg_pack_apply_scan($pack, $verdict);
    $state = pg_pack_state($pack['lines']);
    $closed = ($state['status'] === PG_PACK_PACKED);
    $now = time();

    db(
        "UPDATE order_packs SET
            scanned = '" . (int) $state['scanned'] . "',
            refused = '" . (int) $pack['refused'] . "',
            units = '" . (int) $state['units'] . "',
            scan_lines = '" . e(json_encode(pg_pack_scan_lines_store($pack['lines']))) . "'"
            . ($closed ? ",
            status = '" . PG_PACK_PACKED . "',
            packer_id = '" . (int) $user_id . "',
            packed_at = '" . $now . "',
            missing = NULL" : "") . "
        WHERE id = '" . (int) $pack['id'] . "'");
    db_value("SELECT RELEASE_LOCK('" . e($lock) . "')");

    $pack['scanned'] = $state['scanned'];
    $pack['units'] = $state['units'];

    if ($closed) {
        $pack['status'] = PG_PACK_PACKED;
        $pack['packer_id'] = (int) $user_id;
        $pack['packed_at'] = $now;
        $pack['missing'] = array();
    }

    return array('ok' => true, 'error' => '', 'verdict' => $verdict, 'refusal' => (string) $found['refusal'], 'pack' => $pack, 'closed' => $closed);
}

/**
 * What follows a checked pack, after its row is closed: one audit line, the
 * label when the address has none, the marketplace package accepted, and
 * order.packed. Each step's outcome comes back as a row for the screen.
 *
 * @param array $address pg_pack_address()
 * @param array $pack    pg_pack_scan()'s pack, closed as packed
 * @param int   $user_id
 * @return array rows: 'tone' (teal, amber), 'text' (plain), 'url' ('' none)
 */
function pg_pack_after_packed(array $address, array $pack, $user_id)
{
    $label = lang(array('string' => 'Order {var:1}', 'vars' => array($address['order_number'])));

    pg_audit('pack', 'order_pack', (int) $pack['id'], array('status' => PG_PACK_OPEN), array(
        'status' => PG_PACK_PACKED,
        'ship_to_id' => (int) $address['ship_to_id'],
        'units' => (int) $pack['units'],
        'scanned' => (int) $pack['scanned'],
        'refused' => (int) $pack['refused'],
    ), $label);

    $rows = array(array('tone' => 'teal', 'text' => lang(array('string' => '{var:1} units checked into the box', 'vars' => array((int) $pack['units']))), 'url' => ''));

    $label_row = pg_pack_label($address, $user_id);

    if ($label_row !== null) {
        $rows[] = $label_row;
    }

    foreach (pg_pack_marketplace_accept((int) $address['order_id']) as $row) {
        $rows[] = $row;
    }

    pg_announce('order.packed', pg_pack_event_payload($pack + array('order_id' => $address['order_id'], 'wave_id' => $address['wave_id']), $address['order_number']));

    return $rows;
}

/**
 * The label of a checked pack: made through the label path when the address
 * has neither a label nor a tracking number and a label service ships with
 * its carrier.
 *
 * @param array $address pg_pack_address()
 * @param int   $user_id
 * @return array|null a row for the screen, null when nothing was asked
 */
function pg_pack_label(array $address, $user_id)
{
    if (!empty($address['shipped']) || !pg_shipment_labels_ready()) {
        return null;
    }

    $check = pg_shipment_label_create_check($address['ship_to_id']);

    // No label service for this carrier: the label is made as before.
    if (!$check['ok'] && in_array($check['code'], array('no_driver', 'label_exists', 'not_ready'), true)) {
        return null;
    }

    $made = $check['ok'] ? pg_shipment_label_create($address['ship_to_id'], $user_id) : array('ok' => false, 'error' => $check['error']);

    if (!empty($made['ok'])) {
        return array('tone' => 'teal', 'text' => lang('Shipping label created'), 'url' => 'shipment_label.php?id=' . (int) $made['id']);
    }

    return array('tone' => 'amber', 'text' => lang(array('string' => 'No label was created: {var:1}', 'vars' => array(strip_tags((string) $made['error'])))), 'url' => '');
}

/**
 * Accepts (Picking) the order's marketplace packages still Created, as the
 * label and invoiced paths accept a package before them.
 *
 * @param int $order_id
 * @return array rows for the screen, one a package asked
 */
function pg_pack_marketplace_accept($order_id)
{
    if (!pg_schema_has('marketplace_order_map') || !pg_shipment_label_marketplace_ready()) {
        return array();
    }

    $rows = array();

    foreach (db_items("SELECT * FROM marketplace_order_map WHERE order_id = '" . (int) $order_id . "' AND status = 'imported'") as $map) {
        if (!pg_pack_marketplace_acceptable($map)) {
            continue;
        }

        $account = mp_account((int) $map['account_id']);

        if (!$account || !mp_can($account['provider'], 'approve_lines')) {
            continue;
        }

        $package = mp_sync_package_from_row($map);
        $line_ids = array();

        foreach ($package['lines'] as $line) {
            if (isset($line['remote_line_id']) && ((string) $line['remote_line_id'] !== '')) {
                $line_ids[] = (string) $line['remote_line_id'];
            }
        }

        $name = mp_provider_name($account['provider']);
        $accepted = $line_ids ? mp_call($account['provider'], 'approve_lines', array($account, $line_ids, $package)) : array('ok' => false, 'error' => '');

        if (!empty($accepted['ok'])) {
            db("UPDATE marketplace_order_map
                SET remote_status = 'Picking', approved = 1, updated_timestamp = UNIX_TIMESTAMP()
                WHERE id = '" . (int) $map['id'] . "'");
            $rows[] = array('tone' => 'teal', 'text' => lang(array('string' => 'The package was accepted on {var:1}', 'vars' => array($name))), 'url' => '');
        } else {
            $rows[] = array('tone' => 'amber', 'text' => lang(array('string' => '{var:1} did not accept the package: {var:2}', 'vars' => array($name, strip_tags((string) $accepted['error'])))), 'url' => '');
        }
    }

    return $rows;
}

/**
 * The sentence a scan's verdict is answered with.
 *
 * @param array  $verdict pg_pack_scan_verdict()
 * @param string $refusal pg_barcode_scan_product()'s refusal ('' none)
 * @return string plain text
 */
function pg_pack_verdict_text(array $verdict, $refusal = '')
{
    switch ($verdict['verdict']) {
        case 'good':
            return lang('Counted.');
        case 'binned':
            return lang('This product is in the Recycle Bin; it is not on this order.');
        case 'too_many':
            return lang('Every unit of this product is in the box already.');
        case 'wrong_variant':
            if (!$verdict['options'] || !$verdict['wanted_options']) {
                return lang('This is another variant of a product on this order.');
            }

            return lang(array('string' => 'This is {var:1}; the order wants {var:2}.', 'vars' => array(
                implode(', ', (array) $verdict['options']),
                implode(', ', (array) $verdict['wanted_options']),
            )));
        case 'wrong_product':
            return lang('This product is not on this order.');
    }

    return ($refusal !== '') ? $refusal : lang('No product found with this barcode or product code.');
}

/**
 * The products of these ids whose stock is counted, the only ones a short
 * pick may set a count for.
 *
 * @param array $ids product ids
 * @return int[]
 */
function pg_pack_tracked_products(array $ids)
{
    $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));

    if (!$ids) {
        return array();
    }

    return array_map('intval', db_values("SELECT id FROM products WHERE id IN (" . implode(',', $ids) . ") AND inventory = '1'"));
}

/**
 * Closes the address's pack as short: the lines not full are kept as its
 * missing units, the packer and the time with them, and the address waits
 * to be finished. A pack with every unit scanned, or none to send, is not
 * closed short.
 *
 * @param array $address pg_pack_address()
 * @param int   $user_id
 * @return array 'ok', 'error' ('busy', 'closed', 'not_short'), 'pack' (its
 *               'missing' as product_id => units)
 */
function pg_pack_close_short(array $address, $user_id)
{
    $lock = pg_pack_lock_name($address['ship_to_id']);

    if ((int) db_value("SELECT GET_LOCK('" . e($lock) . "', 5)") !== 1) {
        return array('ok' => false, 'error' => 'busy');
    }

    $pack = pg_pack_open($address, $user_id);
    $state = pg_pack_state($pack['lines'], true);
    $error = ($pack['status'] !== PG_PACK_OPEN) ? 'closed' : (($state['status'] !== PG_PACK_SHORT) ? 'not_short' : '');

    if ($error !== '') {
        db_value("SELECT RELEASE_LOCK('" . e($lock) . "')");

        return array('ok' => false, 'error' => $error, 'pack' => $pack);
    }

    $now = time();

    db(
        "UPDATE order_packs SET
            status = '" . PG_PACK_SHORT . "',
            packer_id = '" . (int) $user_id . "',
            short_at = '" . $now . "',
            units = '" . (int) $state['units'] . "',
            scanned = '" . (int) $state['scanned'] . "',
            missing = '" . e(json_encode($state['missing'])) . "'
        WHERE id = '" . (int) $pack['id'] . "' AND status = '" . PG_PACK_OPEN . "'");
    db_value("SELECT RELEASE_LOCK('" . e($lock) . "')");

    $pack['status'] = PG_PACK_SHORT;
    $pack['packer_id'] = (int) $user_id;
    $pack['short_at'] = $now;
    $pack['units'] = $state['units'];
    $pack['scanned'] = $state['scanned'];
    $pack['missing'] = $state['missing'];

    return array('ok' => true, 'error' => '', 'pack' => $pack);
}

/**
 * What follows a pack closed short: the counts the packer set, one audit
 * line, staff told (pack_short, once a day) and order.pack_short.
 *
 * @param array $address pg_pack_address()
 * @param array $pack    pg_pack_close_short()'s pack
 * @param array $fixes   pg_pack_count_fixes(): product_id => units found
 * @param int   $user_id
 * @return array rows: 'tone' (teal, amber), 'text' (plain)
 */
function pg_pack_after_short(array $address, array $pack, array $fixes, $user_id)
{
    $label = lang(array('string' => 'Order {var:1}', 'vars' => array($address['order_number'])));
    $rows = array();

    foreach ($fixes as $product_id => $units) {
        $set = pg_stock_set((int) $product_id, (int) $address['location_id'], (int) $units, 'count', (string) $address['order_number'],
            array('user_id' => (int) $user_id, 'note' => lang('Counted at a short pick')));
        $code = (string) db_value("SELECT name FROM products WHERE id = '" . (int) $product_id . "'");

        if (!empty($set['ok'])) {
            pg_marketplace_product_changed((int) $product_id);
            $rows[] = array('tone' => 'teal', 'text' => lang(array('string' => 'The count of {var:1} was set to {var:2}', 'vars' => array($code, (int) $units))));
        } else {
            $rows[] = array('tone' => 'amber', 'text' => lang(array('string' => 'The count of {var:1} was not changed', 'vars' => array($code))));
        }
    }

    pg_audit('pack', 'order_pack', (int) $pack['id'], array('status' => PG_PACK_OPEN), array(
        'status' => PG_PACK_SHORT,
        'ship_to_id' => (int) $address['ship_to_id'],
        'units' => (int) $pack['units'],
        'scanned' => (int) $pack['scanned'],
        'refused' => (int) $pack['refused'],
        'missing' => $pack['missing'],
        'counted' => $fixes,
    ), $label);

    $missing = array_sum($pack['missing']);

    pg_ops_alert('pack_short', lang(array('string' => 'Order {var:1} was closed short: {var:2} units missing.', 'vars' => array($address['order_number'], $missing))));

    pg_announce('order.pack_short', pg_pack_event_payload($pack + array('order_id' => $address['order_id'], 'wave_id' => $address['wave_id']), $address['order_number']));

    return $rows;
}

/**
 * Opens a pack closed short again ("Finish the pack"): its scans are kept
 * and the packer scans what was missing.
 *
 * @param array $address pg_pack_address()
 * @return bool whether a short pack was opened
 */
function pg_pack_reopen(array $address)
{
    $done = db_q(
        "UPDATE order_packs SET status = '" . PG_PACK_OPEN . "', missing = NULL
        WHERE ship_to_id = '" . (int) $address['ship_to_id'] . "' AND status = '" . PG_PACK_SHORT . "'");

    return $done && (mysqli_affected_rows(db::$con) > 0);
}

/**
 * Names of products to show beside a short pack's missing units: the
 * product's short description, else its code.
 *
 * @param array $ids product ids
 * @return array id => name
 */
function pg_pack_product_names(array $ids)
{
    $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
    $out = array();

    if (!$ids) {
        return $out;
    }

    foreach (db_items("SELECT id, name, short_description FROM products WHERE id IN (" . implode(',', $ids) . ")") as $row) {
        $name = trim(strip_tags((string) $row['short_description']));
        $out[(int) $row['id']] = ($name !== '') ? $name : (string) $row['name'];
    }

    return $out;
}

/**
 * The "Short" pill of an address closed short, its missing lines and
 * "Finish the pack", for the order screen and a wave's addresses. '' when
 * the address's pack is not short.
 *
 * @param int        $ship_to_id
 * @param mixed|null $missing    order_packs.missing when the caller read it,
 *                               null to read it here
 * @return string HTML
 */
function pg_pack_short_notice($ship_to_id, $missing = null)
{
    if ($missing === null) {
        $row = db_item("SELECT missing FROM order_packs WHERE ship_to_id = '" . (int) $ship_to_id . "' AND status = '" . PG_PACK_SHORT . "'");

        if (!$row) {
            return '';
        }

        $missing = $row['missing'];
    }

    $missing = pg_pack_missing_decode($missing);
    $list = pg_pack_missing_list($missing, pg_pack_product_names(array_keys($missing)), lang('A removed product'));

    return '<span class="pg-pack-short">'
        . '<span class="pg-pill pg-tone-rose">' . h(lang('Short')) . '</span>'
        . ($list ? '<span class="pg-text-meta">' . h(lang(array('string' => 'Missing: {var:1}', 'vars' => array(implode(', ', $list))))) . '</span>' : '')
        . '<a class="pg-button pg-button-quiet pg-button-sm" href="pack.php?ship_to=' . (int) $ship_to_id . '">' . h(lang('Finish the pack')) . '</a>'
        . '</span>';
}

/**
 * The Packing card's figures: the addresses waiting, the packs closed this
 * week by packer, each packer's name.
 *
 * @return array pg_pack_card_figures() with 'names' (user id => name)
 */
function pg_pack_card_data()
{
    $today = pg_pick_today();
    $week = pg_pack_week_start($today);
    $waiting = array();

    foreach (pg_pick_waiting_addresses() as $row) {
        if ($row['paid'] && !$row['shipped'] && !$row['packed']) {
            $waiting[] = (int) $row['ship_by'];
        }
    }

    $packs = db_items(
        "SELECT status, packer_id, started_at, packed_at, units, refused FROM order_packs
        WHERE (status = '" . PG_PACK_PACKED . "' AND packed_at >= '" . $week . "')
            OR (status = '" . PG_PACK_SHORT . "' AND short_at >= '" . $week . "')");

    $figures = pg_pack_card_figures($waiting, $packs, $today);
    $figures['names'] = array();
    $ids = array_filter(array_column($figures['packers'], 'packer_id'));

    if ($ids) {
        foreach (db_items("SELECT user_id, user_username FROM user WHERE user_id IN (" . implode(',', array_map('intval', $ids)) . ")") as $row) {
            $figures['names'][(int) $row['user_id']] = (string) $row['user_username'];
        }
    }

    return $figures;
}
