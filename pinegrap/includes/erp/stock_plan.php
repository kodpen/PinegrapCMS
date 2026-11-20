<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * ERP - the daily stock record behind stock planning (erp_stock_days).
 *
 * Per stock-tracked product and site day, one row when it sold or its stock
 * was 0 at the night pass: the units sold and that stock (NULL = unknown).
 * Units come from complete and exported orders of every type (web,
 * marketplace, counter) and from sales invoices with order_id = 0; an
 * order's own invoice is the order and is not counted again. A bundle's
 * units go to its components and a bundle has no row of its own. Carts,
 * cancelled orders, drafts and cancelled invoices never count; a return is
 * not taken off.
 *
 * The night pass (erp_stock_plan_run(), from erp_stock_plan_job.php):
 *   1. yesterday's row with the stock read now; a day's stock is written
 *      once and never rewritten,
 *   2. the 14 days before today counted again (a late marketplace order, a
 *      later cancel): only the units change,
 *   3. once, on the first runs, the sold units of the 365 days before the
 *      record began, stock unknown, in slices so a tick can stop and resume,
 *   4. rows older than 400 days deleted.
 * Its bookkeeping (the day the record began, the fill's place, the last day
 * recorded) is kept as marks in cron_runs, a day written as YYYYMMDD.
 *
 * The record only reads stock and sales; it never changes a count.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_ERP_ENTRY')) {
    exit;
}

// Days counted again every night, before today.
define('ERP_STOCK_PLAN_RECOUNT_DAYS', 14);
// Days of sales filled in once, before the record began.
define('ERP_STOCK_PLAN_FILL_DAYS', 365);
// Days a fill slice reads at once.
define('ERP_STOCK_PLAN_FILL_SLICE', 30);
// Rows older than this many days are deleted.
define('ERP_STOCK_PLAN_KEEP_DAYS', 400);
// Rows written by one INSERT.
define('ERP_STOCK_PLAN_WRITE_BATCH', 200);
// The marks in cron_runs.
define('ERP_STOCK_PLAN_MARK_BEGAN', 'erp_stock_plan_began');
define('ERP_STOCK_PLAN_MARK_FILL', 'erp_stock_plan_fill');
define('ERP_STOCK_PLAN_MARK_FILLED', 'erp_stock_plan_filled');
define('ERP_STOCK_PLAN_MARK_DAY', 'erp_stock_plan_day');

/**
 * A Y-m-d day as the number kept in a mark. Pure.
 *
 * @param string $day
 * @return int
 */
function erp_stock_plan_day_mark($day)
{
    return (int) str_replace('-', '', (string) $day);
}

/**
 * A mark's number as a Y-m-d day, '' for none. Pure.
 *
 * @param int $mark
 * @return string
 */
function erp_stock_plan_mark_day($mark)
{
    $mark = (int) $mark;

    if ($mark < 10000101) {
        return '';
    }

    $text = (string) $mark;

    return substr($text, 0, 4) . '-' . substr($text, 4, 2) . '-' . substr($text, 6, 2);
}

/**
 * A day moved by whole days, on the calendar (no clock change involved). Pure.
 *
 * @param string $day Y-m-d
 * @param int $days
 * @return string
 */
function erp_stock_plan_shift_day($day, $days)
{
    $date = new DateTime((string) $day . ' 12:00:00', new DateTimeZone('UTC'));
    $date->modify(((int) $days >= 0 ? '+' : '') . (int) $days . ' days');

    return $date->format('Y-m-d');
}

/**
 * The site day a moment falls on (PHP's zone is the site's, set by init.php).
 *
 * @param int $ts
 * @return string Y-m-d
 */
function erp_stock_plan_day_of($ts)
{
    return date('Y-m-d', (int) $ts);
}

/**
 * The first moment of a site day and of the next one; a day the clocks
 * change has 23 or 25 hours.
 *
 * @param string $day Y-m-d
 * @return array{0:int,1:int}
 */
function erp_stock_plan_day_bounds($day)
{
    $parts = explode('-', (string) $day);
    $year = (int) ($parts[0] ?? 0);
    $month = (int) ($parts[1] ?? 0);
    $date = (int) ($parts[2] ?? 0);

    return array(
        (int) mktime(0, 0, 0, $month, $date, $year),
        (int) mktime(0, 0, 0, $month, $date + 1, $year),
    );
}

/**
 * Whether a sale line counts: a complete or exported order of any type, or
 * an issued sales invoice not raised from an order. Pure.
 *
 * @param array $line
 * @return bool
 */
function erp_stock_plan_line_counts(array $line)
{
    if (($line['source'] ?? '') === 'order') {
        return in_array((string) ($line['status'] ?? ''), array('complete', 'exported'), true);
    }

    if (($line['source'] ?? '') === 'invoice') {
        return (($line['direction'] ?? '') === 'sales')
            && (($line['doc_type'] ?? '') === 'invoice')
            && ((int) ($line['order_id'] ?? 0) === 0)
            && in_array((string) ($line['status'] ?? ''), array('issued', 'partially_paid', 'paid'), true);
    }

    return false;
}

/**
 * Units sold per site day and tracked product. Pure.
 *
 * A line: source ('order' with ts, or 'invoice' with date), status,
 * product_id, quantity, and components (product id => units per bundle,
 * the snapshot taken at the sale); a bundle line without a snapshot takes
 * the bundle's contents today from $bundles.
 *
 * @param array $lines
 * @param array<int,bool> $tracked Tracked products that are not bundles.
 * @param array<int,array<int,int>> $bundles Bundle id => component => units.
 * @return array<string,array<int,int>> day => product id => units
 */
function erp_stock_plan_count_units(array $lines, array $tracked, array $bundles)
{
    $units = array();

    foreach ($lines as $line) {

        if (!erp_stock_plan_line_counts($line)) {
            continue;
        }

        $day = ($line['source'] === 'order') ? erp_stock_plan_day_of((int) ($line['ts'] ?? 0)) : (string) ($line['date'] ?? '');
        $quantity = (int) round((float) ($line['quantity'] ?? 0));
        $product_id = (int) ($line['product_id'] ?? 0);

        if (($day === '') || ($quantity <= 0) || ($product_id <= 0)) {
            continue;
        }

        $parts = !empty($line['components']) ? $line['components'] : ($bundles[$product_id] ?? array($product_id => 1));

        foreach ($parts as $part_id => $per_unit) {

            $part_id = (int) $part_id;

            if (empty($tracked[$part_id])) {
                continue;
            }

            $units[$day][$part_id] = ($units[$day][$part_id] ?? 0) + $quantity * max(1, (int) $per_unit);
        }
    }

    ksort($units);

    foreach ($units as $day => $products) {
        ksort($products);
        $units[$day] = $products;
    }

    return $units;
}

/**
 * A day's rows: only a product that sold or whose stock was 0. Without a
 * stock map (the fill) only the sales, stock unknown. Pure.
 *
 * @param array<int,int> $units product id => units sold
 * @param array<int,int>|null $stock product id => stock now
 * @return array<int,array{product_id:int,sold:int,stock:int|null}>
 */
function erp_stock_plan_rows(array $units, $stock)
{
    $ids = array_keys($units);

    if (is_array($stock)) {
        $ids = array_merge($ids, array_keys($stock));
    }

    $ids = array_unique(array_map('intval', $ids));
    sort($ids);

    $rows = array();

    foreach ($ids as $id) {

        $sold = max(0, (int) ($units[$id] ?? 0));
        $level = (is_array($stock) && isset($stock[$id])) ? max(0, (int) $stock[$id]) : null;

        if (($sold > 0) || ($level === 0)) {
            $rows[] = array('product_id' => $id, 'sold' => $sold, 'stock' => $level);
        }
    }

    return $rows;
}

/**
 * What a recount changes in a day's rows: the units it now finds, a row
 * that sold nothing kept only for a stock of 0. Stock is never named. Pure.
 *
 * @param array<int,array{sold:int,stock:int|null}> $existing product id => row
 * @param array<int,int> $units product id => units now
 * @return array{set:array<int,int>,delete:array<int,int>}
 */
function erp_stock_plan_recount(array $existing, array $units)
{
    $set = array();
    $delete = array();

    foreach ($units as $id => $sold) {

        $sold = (int) $sold;

        if (($sold > 0) && (!isset($existing[$id]) || ((int) $existing[$id]['sold'] !== $sold))) {
            $set[(int) $id] = $sold;
        }
    }

    foreach ($existing as $id => $row) {

        if ((int) ($units[$id] ?? 0) > 0) {
            continue;
        }

        if (($row['stock'] !== null) && ((int) $row['stock'] === 0)) {
            if ((int) $row['sold'] !== 0) {
                $set[(int) $id] = 0;
            }
        } else {
            $delete[] = (int) $id;
        }
    }

    ksort($set);
    sort($delete);

    return array('set' => $set, 'delete' => $delete);
}

/**
 * The INSERT for a day's rows. The night's own row fills only a stock not
 * known yet; every other write leaves stock as it was. Pure.
 *
 * @param string $day Y-m-d
 * @param array $rows erp_stock_plan_rows() shape
 * @param bool $night
 * @return string
 */
function erp_stock_plan_upsert_sql($day, array $rows, $night)
{
    $values = array();

    foreach ($rows as $row) {
        $values[] = "(" . (int) $row['product_id'] . ", '" . erp_stock_plan_shift_day($day, 0) . "', "
            . max(0, (int) $row['sold']) . ", "
            . (($row['stock'] === null) ? 'NULL' : max(0, (int) $row['stock'])) . ")";
    }

    return "INSERT INTO erp_stock_days (product_id, day, sold, stock) VALUES " . implode(', ', $values)
        . " ON DUPLICATE KEY UPDATE sold = VALUES(sold), "
        . ($night ? 'stock = IF(stock IS NULL, VALUES(stock), stock)' : 'stock = stock');
}

/**
 * The days counted again tonight: the 14 before today. Pure.
 *
 * @param string $today
 * @return array{0:string,1:string}
 */
function erp_stock_plan_recount_range($today)
{
    return array(erp_stock_plan_shift_day($today, -ERP_STOCK_PLAN_RECOUNT_DAYS), erp_stock_plan_shift_day($today, -1));
}

/**
 * The days the fill covers: the 365 before the record began. Pure.
 *
 * @param string $began
 * @return array{from:string,to:string}
 */
function erp_stock_plan_fill_range($began)
{
    return array('from' => erp_stock_plan_shift_day($began, -ERP_STOCK_PLAN_FILL_DAYS), 'to' => erp_stock_plan_shift_day($began, -1));
}

/**
 * The fill's next slice, going back from the day before the record began;
 * null when the 365 days are done. Pure.
 *
 * @param string $began
 * @param string $cursor The earliest day filled so far, '' before the first.
 * @param int $days
 * @return array{from:string,to:string}|null
 */
function erp_stock_plan_fill_slice($began, $cursor, $days)
{
    $range = erp_stock_plan_fill_range($began);
    $to = ($cursor === '') ? $range['to'] : erp_stock_plan_shift_day($cursor, -1);

    if ($to < $range['from']) {
        return null;
    }

    $from = erp_stock_plan_shift_day($to, -(max(1, (int) $days) - 1));

    return array('from' => max($from, $range['from']), 'to' => $to);
}

/**
 * Whether the one-time fill still has work. Pure.
 *
 * @param array $marks began (Y-m-d or ''), filled (bool)
 * @return bool
 */
function erp_stock_plan_fill_due(array $marks)
{
    return (($marks['began'] ?? '') !== '') && empty($marks['filled']);
}

/**
 * The order lines of [$start, $end). Pure.
 *
 * @param int $start
 * @param int $end
 * @return string
 */
function erp_stock_plan_orders_sql($start, $end)
{
    return "SELECT oi.id AS line_id, oi.product_id, oi.quantity, o.order_date, o.status
        FROM orders o
        JOIN order_items oi ON oi.order_id = o.id
        WHERE o.status IN ('complete', 'exported')
        AND o.order_date >= " . (int) $start . "
        AND o.order_date < " . (int) $end;
}

/**
 * The lines of sales invoices not raised from an order, issued from $from
 * to $to. Pure.
 *
 * @param string $from Y-m-d
 * @param string $to Y-m-d
 * @return string
 */
function erp_stock_plan_invoices_sql($from, $to)
{
    return "SELECT ii.id AS line_id, ii.product_id, ii.quantity, i.issue_date, i.status, i.direction, i.doc_type, i.order_id
        FROM erp_invoices i
        JOIN erp_invoice_items ii ON ii.invoice_id = i.id
        WHERE i.direction = 'sales'
        AND i.doc_type = 'invoice'
        AND i.order_id = 0
        AND i.status IN ('issued', 'partially_paid', 'paid')
        AND i.issue_date >= '" . erp_stock_plan_shift_day($from, 0) . "'
        AND i.issue_date <= '" . erp_stock_plan_shift_day($to, 0) . "'";
}

/**
 * Whether erp_stock_days is in place (2026.11.0, 11.0.249).
 *
 * @return bool
 */
function erp_stock_plan_ready()
{
    return pg_schema_has('erp_stock_days');
}

/**
 * Rows of a read, or false when it failed.
 *
 * @param string $sql
 * @return array|false
 */
function erp_stock_plan_select($sql)
{
    $result = db_q($sql);

    if (!($result instanceof mysqli_result)) {
        return false;
    }

    $rows = array();

    while ($row = mysqli_fetch_assoc($result)) {
        $rows[] = $row;
    }

    mysqli_free_result($result);

    return $rows;
}

/**
 * The record's marks.
 *
 * @return array{began:string,fill:string,filled:bool,day:string}|false
 */
function erp_stock_plan_marks()
{
    $rows = erp_stock_plan_select("SELECT job_name, last_run_at FROM cron_runs WHERE job_name IN ('"
        . ERP_STOCK_PLAN_MARK_BEGAN . "', '" . ERP_STOCK_PLAN_MARK_FILL . "', '" . ERP_STOCK_PLAN_MARK_FILLED . "', '" . ERP_STOCK_PLAN_MARK_DAY . "')");

    if ($rows === false) {
        return false;
    }

    $values = array();

    foreach ($rows as $row) {
        $values[$row['job_name']] = (int) $row['last_run_at'];
    }

    return array(
        'began'  => erp_stock_plan_mark_day($values[ERP_STOCK_PLAN_MARK_BEGAN] ?? 0),
        'fill'   => erp_stock_plan_mark_day($values[ERP_STOCK_PLAN_MARK_FILL] ?? 0),
        'filled' => !empty($values[ERP_STOCK_PLAN_MARK_FILLED]),
        'day'    => erp_stock_plan_mark_day($values[ERP_STOCK_PLAN_MARK_DAY] ?? 0),
    );
}

/**
 * Write a mark.
 *
 * @param string $name
 * @param int $value
 * @return bool
 */
function erp_stock_plan_mark_set($name, $value)
{
    return db_q(
        "INSERT INTO cron_runs (job_name, last_run_at) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE last_run_at = VALUES(last_run_at)",
        array((string) $name, (int) $value)) !== false;
}

/**
 * The day the record began (the first night recorded), '' before it.
 *
 * @return string
 */
function erp_stock_plan_began()
{
    $marks = erp_stock_plan_marks();

    return ($marks === false) ? '' : $marks['began'];
}

/**
 * The tracked products among $ids that are not bundles, with their stock.
 *
 * @param array<int,int> $ids
 * @return array<int,int>|false product id => stock
 */
function erp_stock_plan_tracked(array $ids)
{
    $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
    $not_bundle = pg_bundles_ready() ? ' AND bundle = 0' : '';
    $stock = array();

    foreach (array_chunk($ids, 500) as $chunk) {

        $rows = erp_stock_plan_select("SELECT id, inventory_quantity FROM products
            WHERE inventory = 1" . $not_bundle . " AND id IN (" . implode(',', $chunk) . ")");

        if ($rows === false) {
            return false;
        }

        foreach ($rows as $row) {
            $stock[(int) $row['id']] = (int) $row['inventory_quantity'];
        }
    }

    return $stock;
}

/**
 * Units sold per day and tracked product from $from to $to.
 *
 * @param string $from Y-m-d
 * @param string $to Y-m-d
 * @return array<string,array<int,int>>|false
 */
function erp_stock_plan_units($from, $to)
{
    $start = erp_stock_plan_day_bounds($from);
    $end = erp_stock_plan_day_bounds($to);
    $bundles_ready = pg_bundles_ready();

    $orders = erp_stock_plan_select(erp_stock_plan_orders_sql($start[0], $end[1]));
    $invoices = erp_stock_plan_select(erp_stock_plan_invoices_sql($from, $to));

    if (($orders === false) || ($invoices === false)) {
        return false;
    }

    // The components each bundle line recorded at the sale.
    $order_parts = array();
    $invoice_parts = array();

    if ($bundles_ready) {

        $rows = erp_stock_plan_select("SELECT c.order_item_id, c.product_id, c.quantity
            FROM order_item_components c
            JOIN orders o ON o.id = c.order_id
            WHERE o.status IN ('complete', 'exported')
            AND o.order_date >= " . (int) $start[0] . "
            AND o.order_date < " . (int) $end[1]);

        if ($rows === false) {
            return false;
        }

        foreach ($rows as $row) {
            $order_parts[(int) $row['order_item_id']][(int) $row['product_id']] = max(1, (int) $row['quantity']);
        }

        $line_ids = array();

        foreach ($invoices as $row) {
            $line_ids[] = (int) $row['line_id'];
        }

        foreach (array_chunk($line_ids, 500) as $chunk) {

            $rows = erp_stock_plan_select("SELECT line_id, product_id, quantity FROM erp_invoice_item_components
                WHERE line_id IN (" . implode(',', $chunk) . ")");

            if ($rows === false) {
                return false;
            }

            foreach ($rows as $row) {
                $invoice_parts[(int) $row['line_id']][(int) $row['product_id']] = max(1, (int) $row['quantity']);
            }
        }
    }

    $lines = array();
    $ids = array();

    foreach ($orders as $row) {
        $lines[] = array(
            'source'     => 'order',
            'status'     => $row['status'],
            'ts'         => (int) $row['order_date'],
            'product_id' => (int) $row['product_id'],
            'quantity'   => $row['quantity'],
            'components' => $order_parts[(int) $row['line_id']] ?? array(),
        );
        $ids[(int) $row['product_id']] = true;
    }

    foreach ($invoices as $row) {
        $lines[] = array(
            'source'     => 'invoice',
            'status'     => $row['status'],
            'direction'  => $row['direction'],
            'doc_type'   => $row['doc_type'],
            'order_id'   => (int) $row['order_id'],
            'date'       => (string) $row['issue_date'],
            'product_id' => (int) $row['product_id'],
            'quantity'   => $row['quantity'],
            'components' => $invoice_parts[(int) $row['line_id']] ?? array(),
        );
        $ids[(int) $row['product_id']] = true;
    }

    // Bundles sold without a snapshot take their contents today.
    $bundles = array();

    if ($bundles_ready && !empty($ids)) {

        foreach (array_chunk(array_keys($ids), 500) as $chunk) {

            $rows = erp_stock_plan_select("SELECT bundle_id, product_id, quantity FROM product_bundle_items
                WHERE bundle_id IN (" . implode(',', $chunk) . ")");

            if ($rows === false) {
                return false;
            }

            foreach ($rows as $row) {
                $bundles[(int) $row['bundle_id']][(int) $row['product_id']] = max(1, (int) $row['quantity']);
            }
        }
    }

    foreach ($order_parts + $invoice_parts as $parts) {
        foreach ($parts as $id => $quantity) {
            $ids[(int) $id] = true;
        }
    }

    foreach ($bundles as $parts) {
        foreach ($parts as $id => $quantity) {
            $ids[(int) $id] = true;
        }
    }

    $stock = erp_stock_plan_tracked(array_keys($ids));

    if ($stock === false) {
        return false;
    }

    return erp_stock_plan_count_units($lines, array_fill_keys(array_keys($stock), true), $bundles);
}

/**
 * Write a day's rows in batches.
 *
 * @param string $day
 * @param array $rows
 * @param bool $night
 * @return bool
 */
function erp_stock_plan_write($day, array $rows, $night)
{
    foreach (array_chunk($rows, ERP_STOCK_PLAN_WRITE_BATCH) as $chunk) {
        if (db_q(erp_stock_plan_upsert_sql($day, $chunk, $night)) === false) {
            return false;
        }
    }

    return true;
}

/**
 * Yesterday's row: its units and the stock read now, for every tracked
 * product that sold or has none left.
 *
 * @param string $day
 * @return bool
 */
function erp_stock_plan_record_day($day)
{
    $units = erp_stock_plan_units($day, $day);

    if ($units === false) {
        return false;
    }

    $sold = $units[$day] ?? array();
    $stock = erp_stock_plan_tracked(array_keys($sold));

    if ($stock === false) {
        return false;
    }

    $empty = erp_stock_plan_select("SELECT id, inventory_quantity FROM products
        WHERE inventory = 1" . (pg_bundles_ready() ? ' AND bundle = 0' : '') . " AND inventory_quantity <= 0");

    if ($empty === false) {
        return false;
    }

    foreach ($empty as $row) {
        $stock[(int) $row['id']] = (int) $row['inventory_quantity'];
    }

    return erp_stock_plan_write($day, erp_stock_plan_rows($sold, $stock), true);
}

/**
 * Count the units of $from to $to again; a day's stock stays as it was.
 *
 * @param string $from
 * @param string $to
 * @return bool
 */
function erp_stock_plan_recount_days($from, $to)
{
    $units = erp_stock_plan_units($from, $to);

    if ($units === false) {
        return false;
    }

    $rows = erp_stock_plan_select("SELECT product_id, day, sold, stock FROM erp_stock_days
        WHERE day >= '" . erp_stock_plan_shift_day($from, 0) . "' AND day <= '" . erp_stock_plan_shift_day($to, 0) . "'");

    if ($rows === false) {
        return false;
    }

    $existing = array();

    foreach ($rows as $row) {
        $existing[(string) $row['day']][(int) $row['product_id']] = array(
            'sold'  => (int) $row['sold'],
            'stock' => ($row['stock'] === null) ? null : (int) $row['stock'],
        );
    }

    for ($day = $from; $day <= $to; $day = erp_stock_plan_shift_day($day, 1)) {

        $plan = erp_stock_plan_recount($existing[$day] ?? array(), $units[$day] ?? array());
        $write = array();

        foreach ($plan['set'] as $id => $sold) {
            $write[] = array('product_id' => $id, 'sold' => $sold, 'stock' => null);
        }

        if (!erp_stock_plan_write($day, $write, false)) {
            return false;
        }

        foreach (array_chunk($plan['delete'], 500) as $chunk) {
            if (db_q("DELETE FROM erp_stock_days WHERE day = ? AND product_id IN (" . implode(',', array_map('intval', $chunk)) . ")", array($day)) === false) {
                return false;
            }
        }
    }

    return true;
}

/**
 * The sold units of a fill slice, stock unknown; a day already recorded
 * keeps its stock.
 *
 * @param string $from
 * @param string $to
 * @return bool
 */
function erp_stock_plan_fill_days($from, $to)
{
    $units = erp_stock_plan_units($from, $to);

    if ($units === false) {
        return false;
    }

    foreach ($units as $day => $sold) {
        if (!erp_stock_plan_write($day, erp_stock_plan_rows($sold, null), false)) {
            return false;
        }
    }

    return true;
}

/**
 * Adds the night pass to the jobs the general job runs, when the general
 * job is the one running them; a site that schedules each job on its own
 * keeps its own schedule.
 *
 * @return bool the job is (now) in the list
 */
function erp_stock_plan_job_switch_on()
{
    if (!pg_schema_has('config', 'job_dispatch')) {
        return false;
    }

    $row = db_q("SELECT job_dispatch_enabled, job_dispatch FROM config LIMIT 1");
    $row = ($row instanceof mysqli_result) ? mysqli_fetch_assoc($row) : null;

    if (empty($row) || ((int) $row['job_dispatch_enabled'] !== 1)) {
        return false;
    }

    $list = array();

    foreach (explode(',', (string) $row['job_dispatch']) as $name) {
        if (trim($name) !== '') {
            $list[] = trim($name);
        }
    }

    if (in_array('erp_stock_plan_job', $list, true)) {
        return true;
    }

    $list[] = 'erp_stock_plan_job';

    return db_q("UPDATE config SET job_dispatch = ?", array(implode(',', $list))) !== false;
}

/**
 * The night pass. Returns when done or when $time_left says the tick is
 * over; 'more' asks the caller to come back.
 *
 * @param bool|null $erp_on The Accounting module; null reads ERP_ENABLED.
 * @param callable|null $time_left Seconds this tick may still work.
 * @param int|null $now
 * @return array{ran:bool,reason:string,rows_day:string}
 */
function erp_stock_plan_run($erp_on = null, $time_left = null, $now = null)
{
    if ($erp_on === null) {
        $erp_on = defined('ERP_ENABLED') && ERP_ENABLED;
    }

    if (!$erp_on) {
        return array('ran' => false, 'reason' => 'off', 'rows_day' => '');
    }

    if (!erp_stock_plan_ready()) {
        return array('ran' => false, 'reason' => 'not_ready', 'rows_day' => '');
    }

    if ($time_left === null) {
        $time_left = function () {
            return 60;
        };
    }

    $today = erp_stock_plan_day_of(($now === null) ? time() : (int) $now);
    $yesterday = erp_stock_plan_shift_day($today, -1);
    $marks = erp_stock_plan_marks();

    if ($marks === false) {
        return array('ran' => false, 'reason' => 'error', 'rows_day' => '');
    }

    // The record begins with the first night recorded.
    if ($marks['began'] === '') {

        if (!erp_stock_plan_mark_set(ERP_STOCK_PLAN_MARK_BEGAN, erp_stock_plan_day_mark($yesterday))) {
            return array('ran' => false, 'reason' => 'error', 'rows_day' => '');
        }

        $marks['began'] = $yesterday;
    }

    // Yesterday's row, the recount and the pruning, once a night.
    if ($marks['day'] < $yesterday) {

        $recount = erp_stock_plan_recount_range($today);

        if (!erp_stock_plan_record_day($yesterday)
            || !erp_stock_plan_recount_days($recount[0], $recount[1])
            || (db_q("DELETE FROM erp_stock_days WHERE day < ?", array(erp_stock_plan_shift_day($today, -ERP_STOCK_PLAN_KEEP_DAYS))) === false)
            || !erp_stock_plan_mark_set(ERP_STOCK_PLAN_MARK_DAY, erp_stock_plan_day_mark($yesterday))) {
            return array('ran' => false, 'reason' => 'error', 'rows_day' => '');
        }
    }

    // The 365 days before the record, a slice at a time.
    while (erp_stock_plan_fill_due($marks)) {

        if ($time_left() <= 5) {
            return array('ran' => true, 'reason' => 'more', 'rows_day' => $yesterday);
        }

        $slice = erp_stock_plan_fill_slice($marks['began'], $marks['fill'], ERP_STOCK_PLAN_FILL_SLICE);

        if ($slice === null) {

            if (!erp_stock_plan_mark_set(ERP_STOCK_PLAN_MARK_FILLED, 1)) {
                return array('ran' => false, 'reason' => 'error', 'rows_day' => '');
            }

            $marks['filled'] = true;
            break;
        }

        if (!erp_stock_plan_fill_days($slice['from'], $slice['to'])
            || !erp_stock_plan_mark_set(ERP_STOCK_PLAN_MARK_FILL, erp_stock_plan_day_mark($slice['from']))) {
            return array('ran' => false, 'reason' => 'error', 'rows_day' => '');
        }

        $marks['fill'] = $slice['from'];
    }

    return array('ran' => true, 'reason' => 'done', 'rows_day' => $yesterday);
}
