<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Pick and pack: the pure rules. The shelf address sort, the wave rule's
 * filter, the cancelled order leaving its wave, the pick list totals, the
 * verdict on one scan at the pack screen and the pack's state, a short
 * pick's missing lines and count fixes, the Packing card's figures. Nothing here
 * reads or writes the database: the screens and endpoints hand in the rows
 * they read and store what comes back. Verdicts are codes, never texts; the
 * screen that shows one translates it.
 *
 * A parcel is an address (orders' ship_to_id), as labels count it. A variant
 * is its own product; products of one variant group share a group id.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

// order_packs.status values.
if (!defined('PG_PACK_OPEN')) {
    define('PG_PACK_OPEN', 0);
    define('PG_PACK_PACKED', 1);
    define('PG_PACK_SHORT', 2);
}

// pick_waves.status values.
if (!defined('PG_WAVE_OPEN')) {
    define('PG_WAVE_OPEN', 0);
    define('PG_WAVE_PICKED', 1);
    define('PG_WAVE_CLOSED', 2);
}

// Most addresses one wave holds.
if (!defined('PG_PICK_WAVE_MAX')) {
    define('PG_PICK_WAVE_MAX', 200);
}

/**
 * A shelf address split into its parts: runs of digits and runs of letters,
 * any other character (dash, dot, space, slash) only separates them.
 *
 * @param string $bin "A-03-2"
 * @return array ['A', '03', '2']
 */
function pg_pick_bin_parts($bin)
{
    $parts = array();

    if (preg_match_all('/[0-9]+|[^\W0-9_]+/u', (string) $bin, $m)) {
        $parts = $m[0];
    }

    return $parts;
}

/**
 * Compares two shelf addresses part by part, numbers as numbers ("A-3"
 * before "A-10"), letters without case. An empty address sorts after every
 * other. A number part comes before a letter part at the same place, and an
 * address that is a prefix of another comes first.
 *
 * @param string $a
 * @param string $b
 * @return int <0, 0 or >0
 */
function pg_pick_bin_compare($a, $b)
{
    $a = trim((string) $a);
    $b = trim((string) $b);

    if ($a === '' || $b === '') {
        return ($a === '' ? 1 : 0) - ($b === '' ? 1 : 0);
    }

    $pa = pg_pick_bin_parts($a);
    $pb = pg_pick_bin_parts($b);
    $n = min(count($pa), count($pb));

    for ($i = 0; $i < $n; $i++) {
        $x = $pa[$i];
        $y = $pb[$i];
        $xd = ctype_digit($x);
        $yd = ctype_digit($y);

        if ($xd && $yd) {
            // Compare digit strings by value without integer overflow.
            $x = ltrim($x, '0');
            $y = ltrim($y, '0');

            if (strlen($x) !== strlen($y)) {
                return strlen($x) < strlen($y) ? -1 : 1;
            }

            $c = strcmp($x, $y);
        } elseif ($xd !== $yd) {
            return $xd ? -1 : 1;
        } else {
            $c = strcmp(pg_pick_lower($x), pg_pick_lower($y));
        }

        if ($c !== 0) {
            return $c < 0 ? -1 : 1;
        }
    }

    if (count($pa) !== count($pb)) {
        return count($pa) < count($pb) ? -1 : 1;
    }

    // Same parts ("A-3" and "a.03"): keep a fixed order.
    $c = strcmp($a, $b);

    return $c === 0 ? 0 : ($c < 0 ? -1 : 1);
}

// Lower case of a letter run, multibyte where the extension is there.
function pg_pick_lower($s)
{
    return function_exists('mb_strtolower') ? mb_strtolower($s, 'UTF-8') : strtolower($s);
}

/**
 * Sorts lines into shelf order: by shelf address, a line with none last;
 * equal addresses (and the lines with none) by product code, then product
 * id. Keys are kept out: the result is a list.
 *
 * @param array $lines each with 'bin', 'code' and 'product_id'
 * @return array the lines in shelf order
 */
function pg_pick_sort_lines(array $lines)
{
    $lines = array_values($lines);

    usort($lines, function ($a, $b) {
        $c = pg_pick_bin_compare(isset($a['bin']) ? $a['bin'] : '', isset($b['bin']) ? $b['bin'] : '');

        if ($c !== 0) {
            return $c;
        }

        $c = strnatcasecmp(isset($a['code']) ? (string) $a['code'] : '', isset($b['code']) ? (string) $b['code'] : '');

        if ($c !== 0) {
            return $c;
        }

        return (int) (isset($a['product_id']) ? $a['product_id'] : 0) - (int) (isset($b['product_id']) ? $b['product_id'] : 0);
    });

    return $lines;
}

/**
 * The ship-by day of an address: its own ship date when set, else the paid
 * day plus the shipping method's handling days. Both are the start of a
 * day in the site's time zone.
 *
 * @param int $ship_date     the address's ship_date, 0 when none
 * @param int $paid_at       when the order was paid
 * @param int $handling_days the method's handling days
 * @return int unix time of the day's start, 0 when neither is known
 */
function pg_pick_ship_by($ship_date, $paid_at, $handling_days)
{
    $ship_date = (int) $ship_date;
    $paid_at = (int) $paid_at;

    if ($ship_date > 0) {
        return (int) mktime(0, 0, 0, (int) date('n', $ship_date), (int) date('j', $ship_date), (int) date('Y', $ship_date));
    }

    if ($paid_at <= 0) {
        return 0;
    }

    $days = max(0, (int) $handling_days);

    return (int) mktime(0, 0, 0, (int) date('n', $paid_at), (int) date('j', $paid_at) + $days, (int) date('Y', $paid_at));
}

/**
 * Whether an address may join a wave by the rule: paid, not cancelled, not
 * shipped, no checked pack yet, in no other open wave, and through every
 * filter the rule sets. A filter left empty (or 0) lets every address
 * through.
 *
 * @param array $address 'status' (orders.status), 'paid' (bool), 'shipped'
 *                       (bool, a tracking number or a shipped unit), 'packed'
 *                       (bool, a packed pack), 'in_wave' (bool, in an open
 *                       wave), 'carrier', 'marketplace', 'location_id',
 *                       'ship_by' (pg_pick_ship_by()), 'units' (units on
 *                       the address)
 * @param array $rule    'carrier', 'marketplace', 'location_id',
 *                       'ship_by_until' (unix time, addresses due on or
 *                       before it), 'single_item' (bool)
 * @return bool
 */
function pg_pick_wave_rule_match(array $address, array $rule)
{
    if (empty($address['paid']) || !empty($address['shipped']) || !empty($address['packed']) || !empty($address['in_wave'])) {
        return false;
    }

    if (pg_pick_order_cancelled(isset($address['status']) ? $address['status'] : '')) {
        return false;
    }

    foreach (array('carrier', 'marketplace') as $key) {
        if (isset($rule[$key]) && (string) $rule[$key] !== ''
            && (string) (isset($address[$key]) ? $address[$key] : '') !== (string) $rule[$key]) {
            return false;
        }
    }

    if (!empty($rule['location_id'])
        && (int) (isset($address['location_id']) ? $address['location_id'] : 0) !== (int) $rule['location_id']) {
        return false;
    }

    if (!empty($rule['ship_by_until'])) {
        $ship_by = (int) (isset($address['ship_by']) ? $address['ship_by'] : 0);

        if ($ship_by <= 0 || $ship_by > (int) $rule['ship_by_until']) {
            return false;
        }
    }

    if (!empty($rule['single_item']) && (int) (isset($address['units']) ? $address['units'] : 0) !== 1) {
        return false;
    }

    return true;
}

/**
 * The addresses a rule puts into a new wave: those it matches, oldest
 * ship-by day first (an unknown day last), then by order and address, at
 * most $limit of them.
 *
 * @param array $addresses rows as pg_pick_wave_rule_match() reads, each
 *                         with 'order_id' and 'ship_to_id'
 * @param array $rule
 * @param int   $limit
 * @return array the matching rows, in the order they fill the wave
 */
function pg_pick_wave_rule_filter(array $addresses, array $rule, $limit = PG_PICK_WAVE_MAX)
{
    $out = array();

    foreach ($addresses as $address) {
        if (pg_pick_wave_rule_match($address, $rule)) {
            $out[] = $address;
        }
    }

    usort($out, function ($a, $b) {
        $x = (int) (isset($a['ship_by']) ? $a['ship_by'] : 0);
        $y = (int) (isset($b['ship_by']) ? $b['ship_by'] : 0);

        if ($x !== $y) {
            if ($x <= 0 || $y <= 0) {
                return $x <= 0 ? 1 : -1;
            }

            return $x < $y ? -1 : 1;
        }

        foreach (array('order_id', 'ship_to_id') as $key) {
            $c = (int) (isset($a[$key]) ? $a[$key] : 0) - (int) (isset($b[$key]) ? $b[$key] : 0);

            if ($c !== 0) {
                return $c;
            }
        }

        return 0;
    });

    return array_slice($out, 0, max(0, (int) $limit));
}

/**
 * Whether an orders.status value is a cancelled order. The stored word is
 * 'cancelled'; the single-L spelling is read too.
 *
 * @param string $status
 * @return bool
 */
function pg_pick_order_cancelled($status)
{
    $status = strtolower(trim((string) $status));

    return $status === 'cancelled' || $status === 'canceled';
}

/**
 * The wave rows whose order has been cancelled and that have not left the
 * wave yet: the caller sets their removed_at, and the list shows them as
 * gone.
 *
 * @param array $rows pick_wave_orders rows, each with 'ship_to_id',
 *                    'removed_at' and the order's 'status'
 * @return array the ship_to_ids that leave the wave
 */
function pg_pick_wave_leavers(array $rows)
{
    $out = array();

    foreach ($rows as $row) {
        if ((int) (isset($row['removed_at']) ? $row['removed_at'] : 0) > 0) {
            continue;
        }

        if (pg_pick_order_cancelled(isset($row['status']) ? $row['status'] : '')) {
            $out[] = (int) $row['ship_to_id'];
        }
    }

    return $out;
}

/**
 * The pick list: one line per product and stock location with its total
 * units and how many addresses want it, in shelf order.
 *
 * @param array $items order lines of the wave's addresses: 'product_id',
 *                     'location_id', 'ship_to_id', 'quantity', and the
 *                     product's 'bin', 'code', 'name', 'options'
 * @return array lines: product_id, location_id, bin, code, name, options,
 *               units, addresses
 */
function pg_pick_list_totals(array $items)
{
    $lines = array();
    $seen = array();

    foreach ($items as $item) {
        $units = (int) (isset($item['quantity']) ? $item['quantity'] : 0);

        if ($units <= 0) {
            continue;
        }

        $product_id = (int) $item['product_id'];
        $location_id = (int) (isset($item['location_id']) ? $item['location_id'] : 0);
        $key = $product_id . ':' . $location_id;

        if (!isset($lines[$key])) {
            $lines[$key] = array(
                'product_id' => $product_id,
                'location_id' => $location_id,
                'bin' => trim((string) (isset($item['bin']) ? $item['bin'] : '')),
                'code' => (string) (isset($item['code']) ? $item['code'] : ''),
                'name' => (string) (isset($item['name']) ? $item['name'] : ''),
                'options' => isset($item['options']) ? $item['options'] : array(),
                'units' => 0,
                'addresses' => 0,
            );
            $seen[$key] = array();
        }

        $lines[$key]['units'] += $units;
        $ship_to_id = (int) (isset($item['ship_to_id']) ? $item['ship_to_id'] : 0);

        if (!isset($seen[$key][$ship_to_id])) {
            $seen[$key][$ship_to_id] = true;
            $lines[$key]['addresses']++;
        }
    }

    return pg_pick_sort_lines($lines);
}

/**
 * The rule a wave is made by, from what the Make a wave drawer sent: a
 * carrier key, a marketplace account id, a stock location id, the ship-by
 * choice ('today': due today or late, 'tomorrow': due by tomorrow) and
 * single-item addresses only. A value it does not know lets every address
 * through, as an empty field does.
 *
 * @param array $in    the posted fields
 * @param int   $today unix time of today's start
 * @return array 'carrier', 'marketplace', 'location_id', 'ship_by',
 *               'ship_by_until' (0 for any day), 'single_item'
 */
function pg_pick_wave_rule_clean(array $in, $today)
{
    $text = function ($key) use ($in) {
        return (isset($in[$key]) && is_scalar($in[$key])) ? trim((string) $in[$key]) : '';
    };

    $marketplace = $text('marketplace');
    $location_id = $text('location_id');
    $ship_by = $text('ship_by');
    $days = array('today' => 0, 'tomorrow' => 1);
    $until = 0;

    if (isset($days[$ship_by])) {
        $today = (int) $today;
        $until = (int) mktime(0, 0, 0, (int) date('n', $today), (int) date('j', $today) + $days[$ship_by], (int) date('Y', $today));
    } else {
        $ship_by = '';
    }

    return array(
        'carrier' => substr($text('carrier'), 0, 20),
        'marketplace' => ctype_digit($marketplace) ? (string) (int) $marketplace : '',
        'location_id' => ctype_digit($location_id) ? (int) $location_id : 0,
        'ship_by' => $ship_by,
        'ship_by_until' => $until,
        'single_item' => $text('single_item') === '1',
    );
}

/**
 * A pick list line's shelf address at its location. An address kept at
 * location 0 was typed before the first location was added and belongs to
 * the default location only: another location without its own address has
 * none, never the default location's shelf.
 *
 * @param array $bins                location_id => address of one product
 * @param int   $location_id         where the line is picked
 * @param int   $default_location_id the default location, 0 without
 *                                   locations
 * @return string '' when none
 */
function pg_pick_line_bin(array $bins, $location_id, $default_location_id)
{
    $location_id = (int) $location_id;

    if (isset($bins[$location_id])) {
        return (string) $bins[$location_id];
    }

    return ($location_id === (int) $default_location_id && isset($bins[0])) ? (string) $bins[0] : '';
}

/**
 * The key of a pick list line, as the wave's picked list keeps it.
 *
 * @param int $product_id
 * @param int $location_id
 * @return string "product:location"
 */
function pg_pick_line_key($product_id, $location_id)
{
    return (int) $product_id . ':' . (int) $location_id;
}

/**
 * Ticks or unticks one line in a wave's picked list.
 *
 * @param array  $picked the line keys ticked so far
 * @param string $key    pg_pick_line_key()
 * @param bool   $on     tick (true) or untick
 * @return array the keys, each once, sorted; unchanged for a malformed key
 */
function pg_pick_picked_toggle(array $picked, $key, $on)
{
    $key = (string) $key;
    $out = array();

    foreach ($picked as $one) {
        if (is_string($one) && preg_match('/^[0-9]+:[0-9]+$/', $one)) {
            $out[$one] = true;
        }
    }

    if (preg_match('/^[0-9]+:[0-9]+$/', $key)) {
        if ($on) {
            $out[$key] = true;
        } else {
            unset($out[$key]);
        }
    }

    $out = array_keys($out);
    sort($out, SORT_STRING);

    return $out;
}

/**
 * The pick list as the picker sees it: each line with its key and whether
 * it is ticked, the lines still to pick in shelf order and the ticked ones
 * after them, and how many of all are ticked.
 *
 * @param array $lines  pg_pick_list_totals() lines, in shelf order
 * @param array $picked the wave's ticked line keys
 * @return array 'lines', 'done', 'total'
 */
function pg_pick_list_ticked(array $lines, array $picked)
{
    $picked = array_flip(array_map('strval', $picked));
    $open = array();
    $done = array();

    foreach ($lines as $line) {
        $line['key'] = pg_pick_line_key($line['product_id'], isset($line['location_id']) ? $line['location_id'] : 0);
        $line['picked'] = isset($picked[$line['key']]);

        if ($line['picked']) {
            $done[] = $line;
        } else {
            $open[] = $line;
        }
    }

    return array('lines' => array_merge($open, $done), 'done' => count($done), 'total' => count($lines));
}

/**
 * A wave's status after a line was ticked or unticked: picked once every
 * line is ticked, open again when one is unticked. A closed wave stays
 * closed.
 *
 * @param int $status PG_WAVE_*
 * @param int $done   lines ticked
 * @param int $total  lines on the list
 * @return int PG_WAVE_*
 */
function pg_pick_wave_status_after($status, $done, $total)
{
    if ((int) $status === PG_WAVE_CLOSED) {
        return PG_WAVE_CLOSED;
    }

    return ((int) $total > 0 && (int) $done >= (int) $total) ? PG_WAVE_PICKED : PG_WAVE_OPEN;
}

/**
 * How a ship-by day reads against today: 'late' before today, 'today' on
 * it, '' after it or when no day is known.
 *
 * @param int $ship_by unix time of the day's start, 0 unknown
 * @param int $today   unix time of today's start
 * @return string
 */
function pg_pick_ship_by_tone($ship_by, $today)
{
    $ship_by = (int) $ship_by;

    if ($ship_by <= 0) {
        return '';
    }

    if ($ship_by < (int) $today) {
        return 'late';
    }

    return ($ship_by === (int) $today) ? 'today' : '';
}

/**
 * In a single-item wave, the address a scanned product goes to: of the
 * addresses that want it and are not packed yet, the oldest ship-by day
 * (an unknown day last), then the older order, then the lower address id.
 *
 * @param array $rows       the wave's addresses: 'ship_to_id', 'order_id',
 *                          'product_id' (its one unit), 'ship_by', 'done'
 *                          (packed or left the wave)
 * @param int   $product_id the scanned product
 * @return int|null the ship_to_id, null when no address wants it
 */
function pg_pick_single_item_address(array $rows, $product_id)
{
    $best = null;
    $order = function ($row) {
        $ship_by = (int) $row['ship_by'];

        return array($ship_by > 0 ? 0 : 1, $ship_by, (int) $row['order_id'], (int) $row['ship_to_id']);
    };

    foreach ($rows as $row) {
        if ((int) $row['product_id'] !== (int) $product_id || !empty($row['done'])) {
            continue;
        }

        if ($best === null || $order($row) < $order($best)) {
            $best = $row;
        }
    }

    return ($best === null) ? null : (int) $best['ship_to_id'];
}

/**
 * The verdict on one product scan at the pack screen.
 *
 * Codes: 'good' (counts one unit on 'line'), 'not_found' (no product has the
 * code), 'binned' (a product in the Recycle Bin that the address does not
 * want), 'too_many' (every
 * unit of the product on the address is scanned already), 'wrong_variant'
 * (another product of a variant group the address wants: 'line' is the
 * wanted line, 'options' what was scanned, 'wanted_options' what the line
 * wants), 'wrong_product' (not on the address at all).
 *
 * @param array      $lines   the address's lines: 'product_id', 'quantity',
 *                            'scanned', 'group_id' (variant group, 0 none),
 *                            'options'
 * @param array|null $product the scanned product as the stock screens find
 *                            it: 'id', 'group_id', 'binned', 'options'; null
 *                            when nothing matched the code
 * @return array 'verdict', 'line' (index into $lines or null), and for a
 *               wrong variant 'options' and 'wanted_options'
 */
function pg_pack_scan_verdict(array $lines, $product)
{
    if (!is_array($product) || empty($product['id'])) {
        return array('verdict' => 'not_found', 'line' => null);
    }

    $id = (int) $product['id'];
    $on_address = false;

    foreach ($lines as $i => $line) {
        if ((int) $line['product_id'] !== $id) {
            continue;
        }

        $on_address = true;

        if (pg_pack_line_left($line) > 0) {
            return array('verdict' => 'good', 'line' => $i);
        }
    }

    if ($on_address) {
        return array('verdict' => 'too_many', 'line' => null);
    }

    // Only a product the address does not want: one moved to the Recycle
    // Bin after the order was paid is still sent.
    if (!empty($product['binned'])) {
        return array('verdict' => 'binned', 'line' => null);
    }

    $group_id = (int) (isset($product['group_id']) ? $product['group_id'] : 0);

    if ($group_id > 0) {
        $match = null;

        foreach ($lines as $i => $line) {
            if ((int) (isset($line['group_id']) ? $line['group_id'] : 0) !== $group_id) {
                continue;
            }

            // Name the line still waiting for units; a full one only if none waits.
            if ($match === null || (pg_pack_line_left($line) > 0 && pg_pack_line_left($lines[$match]) <= 0)) {
                $match = $i;
            }
        }

        if ($match !== null) {
            return array(
                'verdict' => 'wrong_variant',
                'line' => $match,
                'options' => isset($product['options']) ? $product['options'] : array(),
                'wanted_options' => isset($lines[$match]['options']) ? $lines[$match]['options'] : array(),
            );
        }
    }

    return array('verdict' => 'wrong_product', 'line' => null);
}

// Units of a pack line not scanned yet.
function pg_pack_line_left(array $line)
{
    return max(0, (int) (isset($line['quantity']) ? $line['quantity'] : 0) - (int) (isset($line['scanned']) ? $line['scanned'] : 0));
}

/**
 * Applies a scan's verdict to the pack: a good scan counts one unit on its
 * line and in 'scanned', any other verdict counts in 'refused'.
 *
 * @param array $pack    'lines' (as pg_pack_scan_verdict() reads),
 *                       'scanned', 'refused'
 * @param array $verdict pg_pack_scan_verdict()'s result
 * @return array the pack after the scan
 */
function pg_pack_apply_scan(array $pack, array $verdict)
{
    $pack['scanned'] = (int) (isset($pack['scanned']) ? $pack['scanned'] : 0);
    $pack['refused'] = (int) (isset($pack['refused']) ? $pack['refused'] : 0);

    if ($verdict['verdict'] === 'good' && isset($pack['lines'][$verdict['line']])) {
        $line = $pack['lines'][$verdict['line']];
        $pack['lines'][$verdict['line']]['scanned'] = (int) (isset($line['scanned']) ? $line['scanned'] : 0) + 1;
        $pack['scanned']++;
    } else {
        $pack['refused']++;
    }

    return $pack;
}

/**
 * The units still missing from a pack, by product id.
 *
 * @param array $lines the pack's lines
 * @return array product_id => units missing; empty when all are scanned
 */
function pg_pack_missing(array $lines)
{
    $out = array();

    foreach ($lines as $line) {
        $left = pg_pack_line_left($line);

        if ($left > 0) {
            $id = (int) $line['product_id'];
            $out[$id] = (isset($out[$id]) ? $out[$id] : 0) + $left;
        }
    }

    ksort($out);

    return $out;
}

/**
 * The pack's state from its lines, and whether it may close.
 *
 * Every unit scanned: status packed. Units missing and the packer chose
 * "Short": status short with the missing lines. Units missing otherwise:
 * still open.
 *
 * @param array $lines the pack's lines
 * @param bool  $short whether the packer closes it as short
 * @return array 'status' (PG_PACK_*), 'units' wanted, 'scanned',
 *               'missing' (pg_pack_missing())
 */
function pg_pack_state(array $lines, $short = false)
{
    $units = 0;
    $scanned = 0;

    foreach ($lines as $line) {
        $want = max(0, (int) (isset($line['quantity']) ? $line['quantity'] : 0));
        $units += $want;
        $scanned += min($want, max(0, (int) (isset($line['scanned']) ? $line['scanned'] : 0)));
    }

    $missing = pg_pack_missing($lines);

    if ($units > 0 && !$missing) {
        $status = PG_PACK_PACKED;
    } elseif ($short && $units > 0) {
        $status = PG_PACK_SHORT;
    } else {
        $status = PG_PACK_OPEN;
    }

    return array('status' => $status, 'units' => $units, 'scanned' => $scanned, 'missing' => $missing);
}

/**
 * A pack's lines from the address's order lines: one line per product with
 * its units summed, the scans already counted on the stored pack kept (never
 * more than the line wants). A line whose product is gone (product id 0) is
 * kept on its own: it cannot be scanned and leaves the pack short.
 *
 * @param array      $items  the address's order lines: 'product_id',
 *                           'quantity', and what the screen shows ('name',
 *                           'code', 'options', 'image', 'group_id')
 * @param array|null $stored order_packs.scan_lines decoded, or null
 * @return array lines as pg_pack_scan_verdict() reads them, in the order of
 *               the order lines
 */
function pg_pack_lines_build(array $items, $stored)
{
    $scanned = array();

    foreach ((array) $stored as $row) {
        if (is_array($row) && !empty($row['product_id'])) {
            $id = (int) $row['product_id'];
            $scanned[$id] = (isset($scanned[$id]) ? $scanned[$id] : 0) + max(0, (int) (isset($row['scanned']) ? $row['scanned'] : 0));
        }
    }

    $lines = array();
    $at = array();

    foreach ($items as $item) {
        $id = (int) (isset($item['product_id']) ? $item['product_id'] : 0);
        $quantity = max(0, (int) (isset($item['quantity']) ? $item['quantity'] : 0));

        if ($quantity <= 0) {
            continue;
        }

        if ($id > 0 && isset($at[$id])) {
            $lines[$at[$id]]['quantity'] += $quantity;
            continue;
        }

        $line = array(
            'product_id' => $id,
            'quantity' => $quantity,
            'scanned' => 0,
            'group_id' => (int) (isset($item['group_id']) ? $item['group_id'] : 0),
            'name' => (string) (isset($item['name']) ? $item['name'] : ''),
            'code' => (string) (isset($item['code']) ? $item['code'] : ''),
            'options' => isset($item['options']) ? (array) $item['options'] : array(),
            'image' => (string) (isset($item['image']) ? $item['image'] : ''),
        );

        if ($id > 0) {
            $at[$id] = count($lines);
        }

        $lines[] = $line;
    }

    foreach ($lines as $i => $line) {
        if ($line['product_id'] > 0 && isset($scanned[$line['product_id']])) {
            $lines[$i]['scanned'] = min($line['quantity'], $scanned[$line['product_id']]);
        }
    }

    return $lines;
}

/**
 * What order_packs.scan_lines keeps of a pack's lines: the product, the
 * units wanted and scanned; names and pictures are read again each time.
 *
 * @param array $lines pg_pack_lines_build()
 * @return array
 */
function pg_pack_scan_lines_store(array $lines)
{
    $out = array();

    foreach ($lines as $line) {
        $out[] = array(
            'product_id' => (int) $line['product_id'],
            'quantity' => (int) $line['quantity'],
            'scanned' => (int) (isset($line['scanned']) ? $line['scanned'] : 0),
        );
    }

    return $out;
}

/**
 * A scanned order code as the order number it stands for: blanks and a
 * leading "#" typed by hand are dropped.
 *
 * @param mixed $code
 * @return string '' when nothing is left
 */
function pg_pack_order_code($code)
{
    $code = is_scalar($code) ? trim((string) $code) : '';
    $code = ltrim($code, '#');

    return (strlen($code) > 100) ? '' : trim($code);
}

/**
 * Whether a marketplace package of the order is accepted (Picking) when its
 * pack is checked: still Created and not accepted yet, as the label and the
 * invoiced paths accept it first.
 *
 * @param array $map a marketplace_order_map row: 'remote_status', 'approved'
 * @return bool
 */
function pg_pack_marketplace_acceptable(array $map)
{
    return isset($map['remote_status']) && ((string) $map['remote_status'] === 'Created') && empty($map['approved']);
}

/**
 * The data of order.packed and order.pack_short: the order, the address,
 * the wave, the packer, the units and the lines missing by product id; no
 * customer data.
 *
 * @param array  $pack         'order_id', 'ship_to_id', 'wave_id',
 *                             'packer_id', 'units', 'scanned', 'missing'
 *                             (product_id => units)
 * @param string $order_number
 * @return array
 */
function pg_pack_event_payload(array $pack, $order_number)
{
    $missing = array();

    foreach ((array) (isset($pack['missing']) ? $pack['missing'] : array()) as $product_id => $units) {
        $missing[] = array('product_id' => (int) $product_id, 'units' => (int) $units);
    }

    return array(
        'id' => (int) $pack['order_id'],
        'order_number' => (string) $order_number,
        'ship_to_id' => (int) $pack['ship_to_id'],
        'wave_id' => (int) (isset($pack['wave_id']) ? $pack['wave_id'] : 0),
        'packer_id' => (int) (isset($pack['packer_id']) ? $pack['packer_id'] : 0),
        'units' => (int) (isset($pack['units']) ? $pack['units'] : 0),
        'scanned' => (int) (isset($pack['scanned']) ? $pack['scanned'] : 0),
        'missing' => $missing,
    );
}

// Most units a short pick's count fix may set a location to.
if (!defined('PG_PACK_COUNT_MAX')) {
    define('PG_PACK_COUNT_MAX', 1000000);
}

// The longest time one pack counts towards its packer's units an hour: a
// pack left open over lunch would otherwise drag the figure down.
if (!defined('PG_PACK_TIME_CAP')) {
    define('PG_PACK_TIME_CAP', 3600);
}

/**
 * order_packs.missing as stored, read back: product_id => units missing,
 * only whole units above zero.
 *
 * @param mixed $stored the JSON text, a decoded array or null
 * @return array product_id => units, by product id
 */
function pg_pack_missing_decode($stored)
{
    $rows = is_array($stored) ? $stored : json_decode((string) $stored, true);
    $out = array();

    foreach ((array) $rows as $key => $row) {
        // pg_pack_missing() keeps product_id => units; the event's list form
        // (product_id, units) is read as well.
        if (is_array($row)) {
            $id = isset($row['product_id']) ? $row['product_id'] : null;
            $units = isset($row['units']) ? $row['units'] : 0;
        } else {
            $id = $key;
            $units = $row;
        }

        if (!is_numeric($id) || !is_numeric($units) || (int) $id < 0 || (int) $units <= 0) {
            continue;
        }

        $out[(int) $id] = (isset($out[(int) $id]) ? $out[(int) $id] : 0) + (int) $units;
    }

    ksort($out);

    return $out;
}

/**
 * The short pick drawer's count fixes: the products whose switch "Set this
 * location's count to what was found" is on, with the number typed. Only a
 * product still missing from the pack and tracking its stock is taken; a
 * number that is not a whole number from 0 up is left out.
 *
 * @param mixed $on      posted product_id => '1' of the switches
 * @param mixed $found   posted product_id => the number typed
 * @param array $missing pg_pack_missing(): product_id => units
 * @param array $tracked product ids whose stock is counted
 * @return array product_id => units now at the location
 */
function pg_pack_count_fixes($on, $found, array $missing, array $tracked)
{
    $out = array();
    $tracked = array_map('intval', $tracked);

    foreach ((array) $on as $id => $flag) {
        if (!is_numeric($id) || (string) $flag !== '1') {
            continue;
        }

        $id = (int) $id;

        if ($id <= 0 || empty($missing[$id]) || !in_array($id, $tracked, true)) {
            continue;
        }

        $value = (is_array($found) && isset($found[$id]) && is_scalar($found[$id])) ? trim((string) $found[$id]) : '';

        if ($value === '' || !ctype_digit($value) || (int) $value > PG_PACK_COUNT_MAX) {
            continue;
        }

        $out[$id] = (int) $value;
    }

    ksort($out);

    return $out;
}

/**
 * A short pack's missing lines as short texts, "Cap × 2", in product order.
 *
 * @param array  $missing    product_id => units (pg_pack_missing_decode())
 * @param array  $names      product_id => the name to show
 * @param string $gone_label the name of a line whose product is gone (id 0)
 *                           or not in $names
 * @return string[]
 */
function pg_pack_missing_list(array $missing, array $names, $gone_label)
{
    $out = array();

    foreach ($missing as $id => $units) {
        $name = (isset($names[$id]) && trim((string) $names[$id]) !== '') ? trim((string) $names[$id]) : (string) $gone_label;
        $out[] = $name . ' × ' . (int) $units;
    }

    return $out;
}

/**
 * The first day of the week $today is in, Monday.
 *
 * @param int $today the day's start
 * @return int
 */
function pg_pack_week_start($today)
{
    $weekday = (int) date('N', (int) $today);

    return ($weekday > 1) ? (int) strtotime('-' . ($weekday - 1) . ' days', (int) $today) : (int) $today;
}

/**
 * The Packing card's figures, from the pack records alone.
 *
 * Waiting: addresses paid and not packed (a short one waits too); oldest:
 * the earliest known ship-by day among them; due: those whose ship-by day is
 * today or before. Packed today and the packers' week: packs closed packed
 * from $week_start on. Units an hour counts each pack's time from its first
 * scan to its close, at most PG_PACK_TIME_CAP, and stays null below one
 * minute in all; refused scans count every pack the packer closed, short too.
 *
 * @param int[] $waiting    the ship-by day of each waiting address (0 unknown)
 * @param array $packs      the packs closed this week: 'status', 'packer_id',
 *                          'started_at', 'packed_at' (short_at for a short
 *                          one), 'units', 'refused'
 * @param int   $today      the day's start
 * @return array 'waiting', 'oldest' (0 none known), 'due', 'packed_today',
 *               'packers': rows of 'packer_id', 'packs', 'units',
 *               'per_hour' (int or null), 'refused'; most packs first
 */
function pg_pack_card_figures(array $waiting, array $packs, $today)
{
    $today = (int) $today;
    $oldest = 0;
    $due = 0;

    foreach ($waiting as $ship_by) {
        $ship_by = (int) $ship_by;

        if ($ship_by <= 0) {
            continue;
        }

        if ($oldest === 0 || $ship_by < $oldest) {
            $oldest = $ship_by;
        }

        if ($ship_by <= $today) {
            $due++;
        }
    }

    $packers = array();
    $packed_today = 0;

    foreach ($packs as $pack) {
        $id = (int) (isset($pack['packer_id']) ? $pack['packer_id'] : 0);

        if (!isset($packers[$id])) {
            $packers[$id] = array('packer_id' => $id, 'packs' => 0, 'units' => 0, 'seconds' => 0, 'refused' => 0);
        }

        $packers[$id]['refused'] += max(0, (int) (isset($pack['refused']) ? $pack['refused'] : 0));

        if ((int) (isset($pack['status']) ? $pack['status'] : -1) !== PG_PACK_PACKED) {
            continue;
        }

        $closed = (int) (isset($pack['packed_at']) ? $pack['packed_at'] : 0);
        $started = (int) (isset($pack['started_at']) ? $pack['started_at'] : 0);
        $packers[$id]['packs']++;
        $packers[$id]['units'] += max(0, (int) (isset($pack['units']) ? $pack['units'] : 0));

        if ($started > 0 && $closed > $started) {
            $packers[$id]['seconds'] += min(PG_PACK_TIME_CAP, $closed - $started);
        }

        if ($closed >= $today) {
            $packed_today++;
        }
    }

    $rows = array();

    foreach ($packers as $row) {
        $row['per_hour'] = ($row['seconds'] >= 60) ? (int) round($row['units'] * 3600 / $row['seconds']) : null;
        unset($row['seconds']);
        $rows[] = $row;
    }

    usort($rows, function ($a, $b) {
        return array($b['packs'], $b['units'], $a['packer_id']) <=> array($a['packs'], $a['units'], $b['packer_id']);
    });

    return array(
        'waiting' => count($waiting),
        'oldest' => $oldest,
        'due' => $due,
        'packed_today' => $packed_today,
        'packers' => $rows,
    );
}
