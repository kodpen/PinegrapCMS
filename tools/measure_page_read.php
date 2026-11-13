<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Measures a live page's read of a bound workflow table at the table's
 * row limit (5000): the time, memory and answer size of the bridge's rows
 * call (includes/workspace/page_bridge.php) on a table with formulas, a
 * row link and a lookup, for the bounds of workspace/canvas-bridge.md.
 *
 * Run only on a throwaway install (tools/setup_sandbox.sh): it makes two
 * tables owned by the first panel user, a binding to a canvas id no message
 * has, and removes all of them at the end.
 *
 * Usage:  php tools/measure_page_read.php [rows] [runs]   (5000, 5)
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (PHP_SAPI !== 'cli') {
    exit;
}

$rows_wanted = max(1, (int) ($argv[1] ?? 5000));
$runs = max(1, (int) ($argv[2] ?? 5));

chdir(dirname(__DIR__) . '/pinegrap');
$_SERVER['REQUEST_URI'] = '/';
$_SERVER['HTTP_HOST'] = '127.0.0.1';
require './init.php';
require_once PG_FUNCTIONS_DIR . '/includes/workspace/bootstrap.php';

if (!ws_wtables_ready() || !ws_canvas_tables_ready()) {
    fwrite(STDERR, "The workflow tables or canvas bindings are not there: run the upgrade first.\n");
    exit(1);
}

$user = db_item("SELECT * FROM user ORDER BY id LIMIT 1");
$viewer = ws_rights($user);

/**
 * Stops with the step that failed.
 *
 * @param string $what
 * @param array  $result
 */
function measure_need($what, $result)
{
    if (empty($result['ok'])) {
        fwrite(STDERR, $what . ' failed: ' . json_encode($result) . "\n");
        exit(1);
    }
}

// Products: 50 rows the orders link to.
$made = ws_wtable_create($viewer, array('name' => 'Measure products ' . time()));
measure_need('products', $made);
$products = ws_wtable((int) $made['table_id']);
$p_name = ws_wtable_column_create($viewer, $products, array('name' => 'Name', 'type' => 'text'));
$p_price = ws_wtable_column_create($viewer, $products, array('name' => 'Price', 'type' => 'number', 'options' => array('decimals' => 2)));
measure_need('product columns', $p_price);

for ($i = 1; $i <= 50; $i++) {
    measure_need('product row', ws_wtable_row_create($viewer, ws_wtable((int) $products['id']), array('cells' => array(
        (string) $p_name['column_id'] => 'Product ' . $i, (string) $p_price['column_id'] => $i * 1.5))));
}

$product_ids = array_map('intval', (array) db_values("SELECT id FROM ws_wtable_rows WHERE table_id = '" . (int) $products['id'] . "' ORDER BY id"));

// Orders: the measured table.
$made = ws_wtable_create($viewer, array('name' => 'Measure orders ' . time()));
measure_need('orders', $made);
$orders = ws_wtable((int) $made['table_id']);
$col = array();

foreach (array(
    'title'   => array('name' => 'Title', 'type' => 'text'),
    'region'  => array('name' => 'Region', 'type' => 'text'),
    'qty'     => array('name' => 'Qty', 'type' => 'number'),
    'unit'    => array('name' => 'Unit', 'type' => 'number', 'options' => array('decimals' => 2)),
    'when'    => array('name' => 'Ordered', 'type' => 'date'),
    'product' => array('name' => 'Product', 'type' => 'row', 'options' => array('table' => (int) $products['id'])),
) as $key => $input) {
    $result = ws_wtable_column_create($viewer, $orders, $input);
    measure_need('column ' . $key, $result);
    $col[$key] = (int) $result['column_id'];
}

foreach (array(
    'price' => array('name' => 'Price', 'type' => 'lookup', 'options' => array('link' => $col['product'], 'column' => (int) $p_price['column_id'])),
    'total' => array('name' => 'Total', 'type' => 'formula', 'options' => array('expression' => 'Qty * Unit')),
    'big'   => array('name' => 'Big', 'type' => 'formula', 'options' => array('expression' => 'IF(Qty * Unit > 100, "yes", "no")')),
) as $key => $input) {
    $result = ws_wtable_column_create($viewer, $orders, $input);
    measure_need('column ' . $key, $result);
    $col[$key] = (int) $result['column_id'];
}

// The rows written at once: the store's own shape, as a row create keeps it.
$regions = array('North', 'South', 'East', 'West', 'Centre');
$now = time();
$values = array();

for ($i = 1; $i <= $rows_wanted; $i++) {
    $cells = array(
        (string) $col['title']   => 'Order ' . $i . ' for a customer with a name of some length',
        (string) $col['region']  => $regions[$i % 5],
        (string) $col['qty']     => ($i % 17) + 1,
        (string) $col['unit']    => round(($i % 23) * 1.25, 2),
        (string) $col['when']    => date('Y-m-d', $now - ($i * 3600)),
        (string) $col['product'] => $product_ids[$i % count($product_ids)],
    );
    $values[] = "('" . (int) $orders['id'] . "', '" . $i . "', '" . e(json_encode($cells)) . "', 1, '" . (int) $viewer['id'] . "', '" . $now . "', '" . (int) $viewer['id'] . "', '" . $now . "')";

    if (count($values) === 500) {
        db("INSERT INTO ws_wtable_rows (table_id, position, cells, revision, created_by, created_at, updated_by, updated_at) VALUES " . implode(',', $values));
        $values = array();
    }
}

if ($values) {
    db("INSERT INTO ws_wtable_rows (table_id, position, cells, revision, created_by, created_at, updated_by, updated_at) VALUES " . implode(',', $values));
}

db("UPDATE ws_wtables SET row_count = '" . $rows_wanted . "' WHERE id = '" . (int) $orders['id'] . "'");

// A binding to a canvas no message has: rows checks the binding, the reach
// of the message is the answer's own check before it.
$canvas_id = 2000000000 + mt_rand(0, 100000000);
db("INSERT INTO ws_canvas_tables (message_id, table_id, access, bound_by_kind, bound_by_id, created_at)
    VALUES ('" . $canvas_id . "', '" . (int) $orders['id'] . "', 'read', 'user', '" . (int) $viewer['id'] . "', UNIX_TIMESTAMP())");

$cases = array(
    'first 100'                  => array('limit' => 100),
    'first 500'                  => array('limit' => 500),
    'where + sort, 500'          => array('limit' => 500, 'where' => array(array('column' => $col['region'], 'op' => 'eq', 'value' => 'North')),
        'sort' => $col['total'], 'dir' => 'desc'),
    'totals, 500'                => array('limit' => 500, 'totals' => true),
    'last page (from 4900), 100' => array('limit' => 100, 'from' => max(0, $rows_wanted - 100)),
);

printf("%d rows, %d runs each; PHP %s\n\n", $rows_wanted, $runs, PHP_VERSION);
printf("%-28s %10s %10s %12s %10s\n", 'read', 'median ms', 'max ms', 'answer KB', 'rows');

foreach ($cases as $name => $args) {
    $args['table'] = (int) $orders['id'];
    $times = array();
    $bytes = 0;
    $count = 0;

    for ($run = 0; $run < $runs; $run++) {
        $start = microtime(true);
        $answer = ws_page_bridge_rows($viewer, array('id' => $canvas_id), $args);
        $times[] = (microtime(true) - $start) * 1000;

        if (empty($answer['ok'])) {
            fwrite(STDERR, $name . ': ' . json_encode($answer) . "\n");
            break;
        }

        $bytes = strlen(json_encode(array('id' => 1, 'ok' => true, 'data' => $answer['data'])));
        $count = count($answer['data']['rows']);
    }

    sort($times);
    printf("%-28s %10.1f %10.1f %12.1f %10d\n", $name, $times[(int) floor(count($times) / 2)], end($times), $bytes / 1024, $count);
}

printf("\npeak memory %.1f MB\n", memory_get_peak_usage(true) / 1048576);

db("DELETE FROM ws_canvas_tables WHERE message_id = '" . $canvas_id . "'");
ws_wtable_delete_final((int) $orders['id']);
ws_wtable_delete_final((int) $products['id']);
