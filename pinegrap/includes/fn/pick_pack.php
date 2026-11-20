<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Pick and pack: the pure rules. The shelf address sort, the wave rule's
 * filter, the cancelled order leaving its wave, the pick list totals, the
 * verdict on one scan at the pack screen and the pack's state. Nothing here
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
 * Whether an address may join a wave by the rule: paid, not cancelled, no
 * checked pack yet, in no other open wave, and through every filter the
 * rule sets. A filter left empty (or 0) lets every address through.
 *
 * @param array $address 'status' (orders.status), 'paid' (bool), 'packed'
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
    if (empty($address['paid']) || !empty($address['packed']) || !empty($address['in_wave'])) {
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
 * The verdict on one product scan at the pack screen.
 *
 * Codes: 'good' (counts one unit on 'line'), 'not_found' (no product has the
 * code), 'binned' (the product is in the Recycle Bin), 'too_many' (every
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

    if (!empty($product['binned'])) {
        return array('verdict' => 'binned', 'line' => null);
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
