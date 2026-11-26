<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Customer rhythm's nightly pass: walks the orders and their lines by date
 * in runs bounded in time, works out the store's median, chance curve and
 * each product's rhythm, then writes one customer_rhythm row per customer
 * and the product summary (config_text 'customer_rhythm'). Read after the
 * fact: nothing is added to the order path. What a written batch has to
 * tell (stage moves, reorder reminders) is left to customer_rhythm_tell.php;
 * a rebuild that moves no stage and finds no new due product tells nothing.
 * The pass also marks the order a reminded customer came back with
 * (customer_rhythm_report.php) and counts it in the summary.
 *
 * Which orders count and who the customer is come from the Customers
 * Report (pg_customer_value_order_counts(), pg_customer_value_key(),
 * pg_customer_value_page_query()); the rules are those of
 * includes/fn/customer_rhythm.php. Between runs the place and the
 * per-customer orders sit in data/temp/customer_rhythm.state.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

// A summary older than this is rebuilt.
define('PG_CUSTOMER_RHYTHM_PASS_INTERVAL', 86400);

// Seconds of a general job tick the pass may use.
define('PG_CUSTOMER_RHYTHM_TICK_SECONDS', 20);

// Orders read per query, customers written per statement.
define('PG_CUSTOMER_RHYTHM_PAGE', 2000);
define('PG_CUSTOMER_RHYTHM_WRITE_BATCH', 500);

// The state file's format; a file of another one is dropped.
define('PG_CUSTOMER_RHYTHM_STATE_VERSION', 1);

// Bytes the product summary may take of its settings text.
define('PG_CUSTOMER_RHYTHM_SUMMARY_BYTES', 60000);

/**
 * The stage codes of customer_rhythm.stage. Pure.
 *
 * @return array  stage name => code
 */
function pg_customer_rhythm_stage_codes()
{
    return array('' => 0, 'regular' => 1, 'due' => 2, 'late' => 3, 'slipping' => 4, 'lost' => 5, 'won_back' => 6);
}

/**
 * A stage's code. Pure.
 *
 * @param string $stage
 * @return int  0 for none or an unknown name
 */
function pg_customer_rhythm_stage_code($stage)
{
    $codes = pg_customer_rhythm_stage_codes();

    return isset($codes[(string) $stage]) ? $codes[(string) $stage] : 0;
}

/**
 * A stage's name from its code. Pure.
 *
 * @param int $code
 * @return string  '' for none or an unknown code
 */
function pg_customer_rhythm_stage_name($code)
{
    $name = array_search((int) $code, pg_customer_rhythm_stage_codes(), true);

    return ($name === false) ? '' : (string) $name;
}

/**
 * Whether the table and what the pass reads are there.
 *
 * @return bool
 */
function pg_customer_rhythm_ready()
{
    return pg_schema_has('customer_rhythm', 'customer_key') && pg_schema_has('config_text') && pg_schema_has('orders') && pg_schema_has('order_items', 'product_id');
}

/**
 * Whether a product can be ordered again: the catalog shows it and it has
 * stock, takes a back order or does not track stock. Pure.
 *
 * @param array $row  enabled, inventory, inventory_quantity, backorder
 * @return bool
 */
function pg_customer_rhythm_product_available($row)
{
    if (!is_array($row) || ((string) (isset($row['enabled']) ? $row['enabled'] : '') !== '1')) {
        return false;
    }

    return ((string) (isset($row['inventory']) ? $row['inventory'] : '') !== '1')
        || ((int) (isset($row['inventory_quantity']) ? $row['inventory_quantity'] : 0) > 0)
        || !empty($row['backorder']);
}

/**
 * A new walk. Pure.
 *
 * @param int $started  the pass start; only orders placed up to it are read
 * @return array
 */
function pg_customer_rhythm_pass_state_new($started)
{
    return array(
        'v' => PG_CUSTOMER_RHYTHM_STATE_VERSION,
        'started' => (int) $started,
        'phase' => 'walk',
        'date' => 0,
        'id' => 0,
        'done' => 0,
        'total' => 0,
        // key => one packed record per counting order (pg_customer_rhythm_pass_orders())
        'c' => array(),
        // key => one packed record per line of those orders (pg_customer_rhythm_pass_lines())
        'l' => array(),
    );
}

/**
 * Adds a page of orders and their lines to the walk: only orders that count
 * (pg_customer_value_order_counts()) of a placed customer. Marketplace
 * orders are kept with their mark, as they count for a product's rhythm
 * only. Pure.
 *
 * @param array $state
 * @param array $orders  rows of pg_customer_value_page_query()
 * @param array $items   order id => list of array(product_id, quantity)
 * @return void
 */
function pg_customer_rhythm_pass_add_rows(&$state, $orders, $items)
{
    foreach ($orders as $order) {
        $state['date'] = (int) $order['order_date'];
        $state['id'] = (int) $order['id'];
        $state['done']++;

        if (!pg_customer_value_order_counts($order)) {
            continue;
        }

        $key = pg_customer_value_key($order);

        if ($key === '') {
            continue;
        }

        $time = (int) $order['order_date'];
        $market = (isset($order['type']) && ((string) $order['type'] === 'marketplace')) ? 1 : 0;

        if (!isset($state['c'][$key])) {
            $state['c'][$key] = '';
            $state['l'][$key] = '';
        }

        $state['c'][$key] .= pack('VVVVC', $time, min(4294967295, pg_customer_value_order_amount($order)), (int) $order['id'], max(0, (int) $order['contact_id']), $market);

        if (isset($items[(int) $order['id']])) {
            foreach ($items[(int) $order['id']] as $item) {
                if (((int) $item[0] > 0) && ((int) $item[1] > 0)) {
                    $state['l'][$key] .= pack('VVVC', (int) $item[0], min(4294967295, (int) $item[1]), $time, $market);
                }
            }
        }
    }
}

/**
 * One customer's orders from the walk, in the shape
 * pg_customer_rhythm_customer() reads; each keeps its id and contact. Only
 * counting orders were kept, so each is complete. Pure.
 *
 * @param string $text
 * @return array
 */
function pg_customer_rhythm_pass_orders($text)
{
    $orders = array();
    $text = (string) $text;

    // Each record: time, amount, order id, contact (32-bit), marketplace (8-bit).
    for ($at = 0, $length = strlen($text); ($at + 17) <= $length; $at += 17) {
        $field = unpack('Vtime/Vamount/Vid/Vcontact/Cmarket', substr($text, $at, 17));
        $orders[] = array(
            'order_date' => (int) $field['time'],
            'total' => (int) $field['amount'],
            'refunded_amount' => 0,
            'status' => 'complete',
            'cancelled_at' => 0,
            'type' => $field['market'] ? 'marketplace' : 'online',
            'id' => (int) $field['id'],
            'contact_id' => (int) $field['contact'],
        );
    }

    return $orders;
}

/**
 * One customer's lines from the walk, in the shape the rules read. Pure.
 *
 * @param string $key
 * @param string $text
 * @return array  customer, product_id, quantity, time, type
 */
function pg_customer_rhythm_pass_lines($key, $text)
{
    $lines = array();
    $text = (string) $text;

    // Each record: product, quantity, time (32-bit), marketplace (8-bit).
    for ($at = 0, $length = strlen($text); ($at + 13) <= $length; $at += 13) {
        $field = unpack('Vproduct/Vquantity/Vtime/Cmarket', substr($text, $at, 13));
        $lines[] = array(
            'customer' => (string) $key,
            'product_id' => (int) $field['product'],
            'quantity' => (int) $field['quantity'],
            'time' => (int) $field['time'],
            'type' => $field['market'] ? 'marketplace' : 'online',
        );
    }

    return $lines;
}

/**
 * The store-wide figures once the walk is done: the median days to a second
 * order, the chance curve and each product's rhythm, all at the pass start.
 * Customers are read one at a time. Pure.
 *
 * @param array $state
 * @param array $groups         product_id => list of group ids
 * @param array $subscriptions  product ids sold as a subscription
 * @return array  median, curve, products
 */
function pg_customer_rhythm_pass_store($state, $groups, $subscriptions)
{
    $now = (int) $state['started'];
    $gaps = array();

    foreach ($state['c'] as $text) {
        $customer = pg_customer_rhythm_customer(pg_customer_rhythm_pass_orders($text), null, $now);

        if ($customer['first_gap'] !== null) {
            $gaps[] = array('first_gap' => $customer['first_gap']);
        }
    }

    $median = pg_customer_rhythm_store_median($gaps);
    unset($gaps);
    $counts = pg_customer_rhythm_curve_counts();
    $pairs = array();
    $index = 0;

    foreach ($state['c'] as $key => $text) {
        $customer = pg_customer_rhythm_customer(pg_customer_rhythm_pass_orders($text), $median, $now);

        if ($customer['orders'] > 0) {
            pg_customer_rhythm_curve_add($counts, $customer);
        }

        // A small number stands for the customer: the pairs only count them.
        pg_customer_rhythm_pairs_add($pairs, pg_customer_rhythm_pass_lines((string) $index++, $state['l'][$key]), $subscriptions, $now);
    }

    return array(
        'median' => $median,
        'curve' => pg_customer_rhythm_curve_finish($counts),
        'products' => pg_customer_rhythm_products_from_pairs($pairs, $groups, $subscriptions),
    );
}

/**
 * The stored row as pg_customer_rhythm_step() reads it. Pure.
 *
 * @param array|null $row  a customer_rhythm row
 * @return array|null
 */
function pg_customer_rhythm_pass_stored($row)
{
    if (!is_array($row)) {
        return null;
    }

    return array(
        'stage' => pg_customer_rhythm_stage_name($row['stage']),
        'interval' => ((int) $row['interval_days'] > 0) ? (float) $row['interval_days'] : null,
        'last' => (int) $row['last_order_at'],
    );
}

/**
 * One customer's row at the pass start (H3 to H8). The interval is kept in
 * whole days, as the row stores it, so the bounds of a new row are those a
 * later pass reads back. Between orders a stage moves only as time passes:
 * when the stage of the last pass's day, worked out under today's curve,
 * is already today's stage, the curve alone changed it and the stored stage
 * stays (H7). A move keeps the stage before and its time; a new row is not
 * a move. Null for a customer with no order of their own (a marketplace
 * buyer). Pure.
 *
 * @param string     $key
 * @param array      $orders   pg_customer_rhythm_pass_orders()
 * @param array      $lines    pg_customer_rhythm_pass_lines()
 * @param array|null $stored   the customer_rhythm row, or null
 * @param array      $store    pg_customer_rhythm_pass_store(), with lead (the
 *                             window's days before a due day)
 * @param array      $available  product_id => bool
 * @param int        $now
 * @return array|null  row (column => value), moved (bool), new (bool), due
 *                     (the products in their reorder window), came_back
 *                     (pg_customer_rhythm_came_back())
 */
function pg_customer_rhythm_pass_row($key, $orders, $lines, $stored, $store, $available, $now)
{
    $now = (int) $now;
    $customer = pg_customer_rhythm_customer($orders, $store['median'], $now);

    if ($customer['orders'] < 1) {
        return null;
    }

    if ($customer['interval'] !== null) {
        $customer['interval'] = (float) max(1, min(65535, (int) round($customer['interval'])));
    }

    $before = pg_customer_rhythm_pass_stored($stored);
    $step = pg_customer_rhythm_step($before, $customer, $store['curve']);
    $stage = $step['stage'];
    // From no stage (no interval yet) to one is the store's figures, not a move.
    $moved = ($before !== null) && ($before['stage'] !== '') && $step['moved'];

    if ($moved && ($customer['last'] <= $before['last']) && ((int) $stored['updated_at'] > $customer['last'])) {
        $earlier = $customer;
        $earlier['days'] = (int) floor(((int) $stored['updated_at'] - $customer['last']) / 86400);
        $then = pg_customer_rhythm_step($before, $earlier, $store['curve']);

        if ($then['stage'] === $stage) {
            $stage = $before['stage'];
            $moved = false;
        }
    }

    $interval = $step['interval'];
    $chance = pg_customer_rhythm_chance($store['curve'], $customer['days'], $interval);
    $last_id = 0;
    $contact = 0;
    $contact_at = 0;

    foreach ($orders as $order) {
        if ($order['type'] === 'marketplace') {
            continue;
        }

        $time = (int) $order['order_date'];

        if (($time === $customer['last']) && ((int) $order['id'] > $last_id)) {
            $last_id = (int) $order['id'];
        }

        // The contact of the latest order that names one.
        if (((int) $order['contact_id'] > 0) && ($time >= $contact_at)) {
            $contact = (int) $order['contact_id'];
            $contact_at = $time;
        }
    }

    $next = 0;
    $due_products = pg_customer_rhythm_due_products($lines, $store['products'], $now, $available, isset($store['lead']) ? (int) $store['lead'] : PG_CUSTOMER_RHYTHM_LEAD_DAYS);

    foreach ($due_products as $due) {
        $next = ($next === 0) ? (int) $due['due'] : min($next, (int) $due['due']);
    }

    $row = array(
        'customer_key' => (string) $key,
        'contact_id' => $contact,
        'orders' => (int) $customer['orders'],
        'first_order_at' => (int) $customer['first'],
        'last_order_at' => (int) $customer['last'],
        'last_order_id' => $last_id,
        'interval_days' => ($interval === null) ? 0 : (int) max(1, min(65535, round($interval))),
        'avg_order' => (int) min(4294967295, max(0, $customer['average'])),
        'stage' => pg_customer_rhythm_stage_code($stage),
        'stage_before' => $moved ? pg_customer_rhythm_stage_code($before['stage']) : ($stored ? (int) $stored['stage_before'] : 0),
        'stage_at' => ($moved || !$stored) ? $now : (int) $stored['stage_at'],
        'chance' => (int) max(0, min(100, round($chance * 100))),
        'expected_value' => (int) min(4294967295, max(0, pg_customer_rhythm_expected($chance, $interval, $customer['average'], $customer['best_year']))),
        'next_due_at' => (int) max(0, $next),
        'updated_at' => $now,
    );

    // An order within 14 days of the last reminder counts as coming back (H13).
    $came_back = $stored ? pg_customer_rhythm_came_back($orders, isset($stored['reminded_at']) ? $stored['reminded_at'] : 0, isset($stored['came_back_order_id']) ? $stored['came_back_order_id'] : 0) : null;

    return array('row' => $row, 'moved' => $moved, 'new' => ($stored === null), 'due' => $due_products, 'came_back' => $came_back);
}

/**
 * The statement writing a batch of rows: inserted, or the pass's columns
 * replaced. The reminder columns (reminded_at, held_back,
 * came_back_order_id) are left to their own writers. Pure.
 *
 * @param array $rows  rows of pg_customer_rhythm_pass_row()
 * @return array  array(sql, params); sql '' for no rows
 */
function pg_customer_rhythm_pass_write_query($rows)
{
    if (!$rows) {
        return array('', array());
    }

    $columns = array_keys(reset($rows));
    $params = array();
    $tuples = array();

    foreach ($rows as $row) {
        $tuples[] = '(' . implode(', ', array_fill(0, count($columns), '?')) . ')';

        foreach ($columns as $column) {
            $params[] = $row[$column];
        }
    }

    $update = array();

    foreach ($columns as $column) {
        if ($column !== 'customer_key') {
            $update[] = $column . ' = VALUES(' . $column . ')';
        }
    }

    $sql = 'INSERT INTO customer_rhythm (' . implode(', ', $columns) . ') VALUES ' . implode(', ', $tuples)
        . ' ON DUPLICATE KEY UPDATE ' . implode(', ', $update);

    return array($sql, $params);
}

/**
 * The product summary as stored: the pass start, the store's median and
 * curve, and each product's rhythm as array(days per unit, pairs,
 * customers, 1 when from its group). When the products do not fit the
 * settings text, those with the most pairs are kept; 'products_all' says
 * how many had a rhythm. Pure.
 *
 * @param array $store  pg_customer_rhythm_pass_store()
 * @param int   $at
 * @param array $counts  customers, moved, stages (code => customers),
 *                       optional reminders ('YYYY-MM' => told, held back)
 *                       and returns ('YYYY-MM' => came back and value, told
 *                       and held back)
 * @return string  JSON
 */
function pg_customer_rhythm_summary_encode($store, $at, $counts)
{
    $products = array();

    foreach ($store['products'] as $id => $rhythm) {
        $products[(int) $id] = array((float) $rhythm['days_per_unit'], (int) $rhythm['pairs'], (int) $rhythm['customers'], ($rhythm['from'] === 'group') ? 1 : 0);
    }

    $summary = array(
        'at' => (int) $at,
        'median' => $store['median'],
        'curve' => $store['curve'],
        'customers' => (int) $counts['customers'],
        'stages' => $counts['stages'],
        'products_all' => count($products),
        'products' => (object) $products,
    );

    if (isset($counts['reminders'])) {
        $summary['reminders'] = (object) $counts['reminders'];
    }

    if (isset($counts['returns'])) {
        $summary['returns'] = (object) $counts['returns'];
    }

    $json = json_encode($summary);

    if (strlen($json) > PG_CUSTOMER_RHYTHM_SUMMARY_BYTES) {
        uasort($products, function ($a, $b) {
            return $b[1] - $a[1];
        });

        $kept = array();
        $bytes = strlen(json_encode(array_merge($summary, array('products' => new stdClass()))));

        foreach ($products as $id => $rhythm) {
            $bytes += strlen(json_encode(array((string) $id => $rhythm))) - 1;

            if ($bytes > PG_CUSTOMER_RHYTHM_SUMMARY_BYTES) {
                break;
            }

            $kept[$id] = $rhythm;
        }

        ksort($kept);
        $summary['products'] = (object) $kept;
        $json = json_encode($summary);
    }

    return $json;
}

/**
 * The product summary, or null before the first pass.
 *
 * @return array|null
 */
function pg_customer_rhythm_summary()
{
    $summary = json_decode(pg_config_text('customer_rhythm'), true);

    return (is_array($summary) && isset($summary['at'])) ? $summary : null;
}

/**
 * Where the walk's state lives between runs.
 *
 * @return string
 */
function pg_customer_rhythm_state_path()
{
    return PG_FUNCTIONS_DIR . '/data/temp/customer_rhythm.state';
}

/**
 * The pass record (config_text 'customer_rhythm_pass'), read past the
 * request cache since it is compared and swapped.
 *
 * @return array  array(raw text, record)
 */
function pg_customer_rhythm_pass_read()
{
    $raw = (string) db_value("SELECT value FROM config_text WHERE name = 'customer_rhythm_pass'");
    $record = json_decode($raw, true);

    if (!is_array($record)) {
        $record = array();
    }

    return array($raw, array_merge(array('lease' => 0, 'started' => 0, 'done' => 0, 'total' => 0), $record));
}

/**
 * Whether a pass is due: one under way, or a summary missing or a day old;
 * a pass that ended without its summary waits the interval from its end.
 * Pure.
 *
 * @param array    $record
 * @param int|null $summary_at
 * @param int      $now
 * @return bool
 */
function pg_customer_rhythm_pass_due($record, $summary_at, $now)
{
    if ((int) $record['started'] > 0) {
        return true;
    }

    if (isset($record['saved'], $record['ended']) && ((int) $record['saved'] === 0) && (((int) $now - (int) $record['ended']) < PG_CUSTOMER_RHYTHM_PASS_INTERVAL)) {
        return false;
    }

    return ($summary_at === null) || (((int) $now - (int) $summary_at) >= PG_CUSTOMER_RHYTHM_PASS_INTERVAL);
}

/**
 * A walk's state from its file, or null for none, a broken one or one of
 * another format or pass. Pure.
 *
 * @param string $text
 * @param int    $started
 * @return array|null
 */
function pg_customer_rhythm_state_decode($text, $started)
{
    $state = ((string) $text === '') ? false : @unserialize((string) $text, array('allowed_classes' => false));

    if (!is_array($state) || !isset($state['v'], $state['started'], $state['phase'], $state['c'], $state['l'])
        || ((int) $state['v'] !== PG_CUSTOMER_RHYTHM_STATE_VERSION) || ((int) $state['started'] !== (int) $started)) {
        return null;
    }

    return $state;
}

/**
 * Product groups and subscription products, as the store's figures read
 * them.
 *
 * @return array  array(groups, subscriptions)
 */
function pg_customer_rhythm_pass_catalog()
{
    $groups = array();

    if (pg_schema_has('products_groups_xref', 'product_group')) {
        foreach (db_items("SELECT product, product_group FROM products_groups_xref WHERE (product > 0) AND (product_group > 0)") as $row) {
            $groups[(int) $row['product']][] = (int) $row['product_group'];
        }
    }

    $subscriptions = pg_schema_has('products', 'recurring')
        ? array_map('intval', db_values("SELECT id FROM products WHERE recurring = 1"))
        : array();

    if (pg_schema_has('subscriptions', 'product_id')) {
        $subscriptions = array_merge($subscriptions, array_map('intval', db_values("SELECT DISTINCT product_id FROM subscriptions WHERE product_id > 0")));
    }

    return array($groups, array_values(array_unique($subscriptions)));
}

/**
 * Which products with a rhythm can be ordered again.
 *
 * @param array $ids
 * @return array  product_id => bool
 */
function pg_customer_rhythm_pass_available($ids)
{
    $available = array();
    $backorder = pg_schema_has('products', 'backorder') ? 'backorder' : '0 AS backorder';

    foreach (array_chunk(array_map('intval', $ids), 1000) as $chunk) {
        foreach (db_items("SELECT id, enabled, inventory, inventory_quantity, " . $backorder . " FROM products WHERE id IN (" . implode(',', $chunk) . ")") as $row) {
            $available[(int) $row['id']] = pg_customer_rhythm_product_available($row);
        }

        // A product that is gone cannot be ordered.
        foreach ($chunk as $id) {
            if (!isset($available[$id])) {
                $available[$id] = false;
            }
        }
    }

    return $available;
}

/**
 * The order lines as the walk keeps them: each line's units less those
 * given back through a refunded return; a line given back whole is left
 * out. Pure.
 *
 * @param array $rows      list of array(order id, line id, product id, quantity)
 * @param array $returned  line id => units given back
 * @return array  order id => list of array(product_id, quantity)
 */
function pg_customer_rhythm_items_kept($rows, $returned)
{
    $items = array();

    foreach ($rows as $row) {
        $quantity = (int) $row[3] - (int) (isset($returned[(int) $row[1]]) ? $returned[(int) $row[1]] : 0);

        if (((int) $row[2] > 0) && ($quantity > 0)) {
            $items[(int) $row[0]][] = array((int) $row[2], $quantity);
        }
    }

    return $items;
}

/**
 * The units of these orders' lines given back through a return whose money
 * went back (refunded_at set), as pg_event_tickets_refunded_units() counts
 * them.
 *
 * @param array $order_ids
 * @return array  line id => units
 */
function pg_customer_rhythm_returned_units($order_ids)
{
    $units = array();

    if (!$order_ids || !function_exists('pg_returns_ready') || !pg_returns_ready()) {
        return $units;
    }

    foreach (array_chunk(array_values(array_unique(array_map('intval', $order_ids))), 1000) as $chunk) {
        $rows = db_items("SELECT order_return_items.order_item_id, SUM(order_return_items.quantity) AS quantity"
            . " FROM order_return_items JOIN order_returns ON order_returns.id = order_return_items.return_id"
            . " WHERE (order_returns.order_id IN (" . implode(',', $chunk) . ")) AND (order_returns.refunded_at > 0)"
            . " GROUP BY order_return_items.order_item_id");

        foreach ((array) $rows as $row) {
            $units[(int) $row['order_item_id']] = (int) $row['quantity'];
        }
    }

    return $units;
}

/**
 * The customers none of whose orders still carries their key: the orders
 * were deleted, or an erasure put a token in place of the address, after
 * the walk read them. Pure.
 *
 * @param array $ids      customer key => list of order ids the walk read
 * @param array $current  order id => billing_email_address, contact_id as
 *                        they are now; an order not there is gone
 * @return array  the keys gone
 */
function pg_customer_rhythm_keys_gone($ids, $current)
{
    $gone = array();

    foreach ($ids as $key => $list) {
        $kept = false;

        foreach ((array) $list as $id) {
            if (isset($current[(int) $id]) && (pg_customer_value_key($current[(int) $id]) === (string) $key)) {
                $kept = true;
                break;
            }
        }

        if (!$kept) {
            $gone[] = (string) $key;
        }
    }

    return $gone;
}

/**
 * The customers of a write batch whose orders no longer carry their key
 * (pg_customer_rhythm_keys_gone()), so an erasure made while the pass runs
 * is not undone by writing the row back. The latest order of each is read
 * first; only a customer whose latest order changed has the rest read.
 *
 * @param array $keys
 * @param array $state  the walk (c: key => packed orders)
 * @return array  the keys gone; none when the orders cannot be read
 */
function pg_customer_rhythm_pass_gone($keys, $state)
{
    $read = function ($ids) {
        $current = array();

        foreach (array_chunk(array_values(array_unique(array_map('intval', $ids))), 1000) as $chunk) {
            $result = db_q("SELECT id, billing_email_address, contact_id FROM orders WHERE id IN (" . implode(',', $chunk) . ")");

            if ($result === false) {
                return null;
            }

            while ($row = mysqli_fetch_assoc($result)) {
                $current[(int) $row['id']] = $row;
            }

            mysqli_free_result($result);
        }

        return $current;
    };

    $all = array();
    $latest = array();

    foreach ($keys as $key) {
        $all[$key] = array();

        foreach (pg_customer_rhythm_pass_orders(isset($state['c'][$key]) ? $state['c'][$key] : '') as $order) {
            $all[$key][] = (int) $order['id'];
        }

        // The walk reads by date, so the last record is the latest order.
        $latest[$key] = $all[$key] ? array($all[$key][count($all[$key]) - 1]) : array();
    }

    $ids = array();

    foreach ($latest as $list) {
        $ids = array_merge($ids, $list);
    }

    $current = $ids ? $read($ids) : array();

    if ($current === null) {
        return array();
    }

    $suspects = array();

    foreach (pg_customer_rhythm_keys_gone($latest, $current) as $key) {
        $suspects[$key] = $all[$key];
    }

    if (!$suspects) {
        return array();
    }

    $ids = array();

    foreach ($suspects as $list) {
        $ids = array_merge($ids, $list);
    }

    $current = $ids ? $read($ids) : array();

    return ($current === null) ? array() : pg_customer_rhythm_keys_gone($suspects, $current);
}

/**
 * The general job's pass: under one claim of the pass record, walk the
 * orders for at most $budget seconds from where the last run stopped; once
 * the walk is done, work out the store's figures and write the customers'
 * rows in batches, then remove the rows of customers no longer found and
 * write the product summary. A run that loses the claim, or finds another
 * run's lease alive, does nothing. Each written batch is handed to
 * pg_customer_rhythm_tell().
 *
 * @param int      $budget  seconds
 * @param int|null $now
 * @return array|null  phase, read, written, moved, told, done; null when nothing ran
 */
function pg_customer_rhythm_job_run($budget = PG_CUSTOMER_RHYTHM_TICK_SECONDS, $now = null)
{
    if (!defined('ECOMMERCE') || !ECOMMERCE || !pg_customer_rhythm_ready()) {
        return null;
    }

    $now = ($now === null) ? time() : (int) $now;
    $budget = max(1, (int) $budget);
    $clock = microtime(true);

    list($raw, $record) = pg_customer_rhythm_pass_read();

    if ((int) $record['lease'] > $now) {
        return null;
    }

    $summary = pg_customer_rhythm_summary();

    if (!pg_customer_rhythm_pass_due($record, ($summary === null) ? null : (int) $summary['at'], $now)) {
        return null;
    }

    $path = pg_customer_rhythm_state_path();
    $state = null;

    if (((int) $record['started'] > 0) && is_file($path)) {
        $state = pg_customer_rhythm_state_decode((string) @file_get_contents($path), (int) $record['started']);
    }

    if ($state === null) {
        $state = pg_customer_rhythm_pass_state_new($now);
        $state['total'] = (int) db_value("SELECT COUNT(*) FROM orders WHERE (status IN ('complete', 'exported')) AND (order_date > 0) AND (order_date <= " . (int) $now . ")");
    }

    $lease = json_encode(array('lease' => $now + $budget + 60, 'started' => (int) $state['started'], 'done' => (int) $state['done'], 'total' => (int) $state['total']));

    if (!pg_config_text_claim('customer_rhythm_pass', $raw, $lease)) {
        return null;
    }

    $out = array('phase' => $state['phase'], 'read' => 0, 'written' => 0, 'moved' => 0, 'told' => 0, 'done' => false);
    $settings = pg_customer_rhythm_settings();

    if ($state['phase'] === 'walk') {
        $present = array();

        foreach (array('refunded_amount', 'type', 'cancelled_at') as $column) {
            if (pg_schema_has('orders', $column)) {
                $present[] = $column;
            }
        }

        $active = pg_order_item_active_sql('order_items');

        while (true) {
            list($sql, $params) = pg_customer_value_page_query($state['started'], $state['date'], $state['id'], PG_CUSTOMER_RHYTHM_PAGE, $present);
            $orders = db_q($sql, $params);

            if ($orders === false) {
                break;
            }

            $rows = array();

            while ($row = mysqli_fetch_assoc($orders)) {
                $rows[] = $row;
            }

            mysqli_free_result($orders);
            $items = array();

            if ($rows) {
                $ids = array();

                foreach ($rows as $row) {
                    $ids[] = (int) $row['id'];
                }

                $result = db_q("SELECT order_id, id, product_id, quantity FROM order_items WHERE order_id IN (" . implode(',', $ids) . ") AND (product_id > 0)" . $active);

                if ($result === false) {
                    break;
                }

                $lines = array();

                while ($item = mysqli_fetch_row($result)) {
                    $lines[] = $item;
                }

                mysqli_free_result($result);
                $items = pg_customer_rhythm_items_kept($lines, pg_customer_rhythm_returned_units($ids));
            }

            pg_customer_rhythm_pass_add_rows($state, $rows, $items);
            $out['read'] += count($rows);

            if (count($rows) < PG_CUSTOMER_RHYTHM_PAGE) {
                list($groups, $subscriptions) = pg_customer_rhythm_pass_catalog();
                $state['store'] = pg_customer_rhythm_pass_store($state, $groups, $subscriptions);
                $state['store']['lead'] = (int) $settings['lead_days'];
                $state['available'] = pg_customer_rhythm_pass_available(array_keys($state['store']['products']));
                $state['keys'] = array_keys($state['c']);
                sort($state['keys'], SORT_STRING);
                $state['cursor'] = 0;
                $state['counts'] = array('customers' => 0, 'moved' => 0, 'stages' => array(), 'months' => array(), 'back' => array());
                $state['phase'] = 'write';
                break;
            }

            if ((microtime(true) - $clock) >= $budget) {
                break;
            }
        }
    }

    if (($state['phase'] === 'write') && ((microtime(true) - $clock) < $budget)) {
        $total = count($state['keys']);

        while ($state['cursor'] < $total) {
            $keys = array_slice($state['keys'], $state['cursor'], PG_CUSTOMER_RHYTHM_WRITE_BATCH);
            $stored = array();
            $result = db_q("SELECT customer_key, contact_id, stage, stage_before, stage_at, interval_days, last_order_at, reminded_at, held_back, came_back_order_id, updated_at FROM customer_rhythm WHERE customer_key IN (" . implode(', ', array_fill(0, count($keys), '?')) . ")", $keys);

            if ($result === false) {
                break;
            }

            while ($row = mysqli_fetch_assoc($result)) {
                $stored[(string) $row['customer_key']] = $row;
            }

            mysqli_free_result($result);
            $rows = array();
            $batch = array();
            $gone = array_flip(pg_customer_rhythm_pass_gone($keys, $state));

            foreach ($keys as $key) {
                if (isset($gone[$key])) {
                    continue;
                }

                $made =pg_customer_rhythm_pass_row($key, pg_customer_rhythm_pass_orders($state['c'][$key]), pg_customer_rhythm_pass_lines($key, $state['l'][$key]), isset($stored[$key]) ? $stored[$key] : null, $state['store'], $state['available'], $state['started']);

                if ($made === null) {
                    continue;
                }

                $rows[] = $made['row'];
                $batch[$key] = $made;
                $state['counts']['customers']++;
                $state['counts']['moved'] += $made['moved'] ? 1 : 0;
                $code = (int) $made['row']['stage'];
                $state['counts']['stages'][$code] = (isset($state['counts']['stages'][$code]) ? $state['counts']['stages'][$code] : 0) + 1;
                $out['moved'] += $made['moved'] ? 1 : 0;
            }

            list($sql, $params) = pg_customer_rhythm_pass_write_query($rows);

            if (($sql !== '') && (db_q($sql, $params) === false)) {
                break;
            }

            $out['written'] += count($rows);
            $state['cursor'] += count($keys);

            // Who came back after their last reminder, before a new
            // reminder clears the mark.
            $back = array();

            foreach ($batch as $key => $made) {
                if (!empty($made['came_back'])) {
                    $back[$key] = $made['came_back'];
                }
            }

            list($sql, $params) = pg_customer_rhythm_came_back_query($back);

            if (($sql !== '') && (db_q($sql, $params) !== false)) {
                if (!isset($state['counts']['back'])) {
                    $state['counts']['back'] = array();
                }

                foreach ($back as $key => $came) {
                    pg_customer_rhythm_return_count($state['counts']['back'], (int) $stored[$key]['reminded_at'], !empty($stored[$key]['held_back']), $came['amount']);
                }
            }
            $told = pg_customer_rhythm_tell($batch, $stored, $settings, (int) $state['started']);
            $out['told'] += $told['told'];

            if (!isset($state['counts']['months'])) {
                $state['counts']['months'] = array();
            }

            $state['counts']['months'] = pg_customer_rhythm_reminders_merge($state['counts']['months'], $told['months']);

            if ((microtime(true) - $clock) >= $budget) {
                break;
            }
        }

        if ($state['cursor'] >= $total) {
            // A customer whose orders no longer count (erased, cancelled,
            // refunded) was not written this pass.
            db_q("DELETE FROM customer_rhythm WHERE updated_at < ?", array((int) $state['started']));
            $state['counts']['returns'] = pg_customer_rhythm_returns_merge(($summary !== null) && isset($summary['returns']) ? $summary['returns'] : array(), isset($state['counts']['back']) ? $state['counts']['back'] : array());
            $state['counts']['reminders'] = pg_customer_rhythm_reminders_merge(($summary !== null) && isset($summary['reminders']) ? $summary['reminders'] : array(), isset($state['counts']['months']) ? $state['counts']['months'] : array());
            $saved = pg_config_text_save('customer_rhythm', pg_customer_rhythm_summary_encode($state['store'], $state['started'], $state['counts']));
            @unlink($path);
            pg_config_text_claim('customer_rhythm_pass', $lease, json_encode(array('lease' => 0, 'started' => 0, 'done' => (int) $state['done'], 'total' => (int) $state['total'], 'saved' => $saved ? 1 : 0, 'ended' => time())));
            $out['done'] = true;

            return $out;
        }
    }

    // Written whole, then moved, so a run killed while writing leaves the
    // last good state and its place behind.
    $temp = $path . '.' . getmypid();

    if (@file_put_contents($temp, serialize($state)) !== false) {
        @rename($temp, $path);
    }

    pg_config_text_claim('customer_rhythm_pass', $lease, json_encode(array('lease' => 0, 'started' => (int) $state['started'], 'done' => (int) $state['done'], 'total' => (int) $state['total'])));
    $out['phase'] = $state['phase'];

    return $out;
}
