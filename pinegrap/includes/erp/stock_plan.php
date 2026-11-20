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
 *   3. once, on the first runs, the sold units of the 420 days before the
 *      record began, stock unknown, in slices so a tick can stop and resume,
 *   4. rows older than 430 days deleted,
 *   5. the forecast (erp_stock_plan) rebuilt for every tracked product.
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
// Days of sales filled in once, before the record began: last year's 56
// days ahead and the 56 before them (the season), from the first night.
define('ERP_STOCK_PLAN_FILL_DAYS', 420);
// Days a fill slice reads at once.
define('ERP_STOCK_PLAN_FILL_SLICE', 30);
// Rows older than this many days are deleted; the season reads back 420.
define('ERP_STOCK_PLAN_KEEP_DAYS', 430);
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
 * The days the fill covers: the 420 before the record began. Pure.
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
 * null when the 420 days are done. Pure.
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
 * @return array{began:string,fill:string,filled:bool,day:string,planned:string}|false
 */
function erp_stock_plan_marks()
{
    $rows = erp_stock_plan_select("SELECT job_name, last_run_at FROM cron_runs WHERE job_name IN ('"
        . ERP_STOCK_PLAN_MARK_BEGAN . "', '" . ERP_STOCK_PLAN_MARK_FILL . "', '" . ERP_STOCK_PLAN_MARK_FILLED . "', '" . ERP_STOCK_PLAN_MARK_DAY . "', '" . ERP_STOCK_PLAN_MARK_PLANNED . "')");

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
        'planned' => erp_stock_plan_mark_day($values[ERP_STOCK_PLAN_MARK_PLANNED] ?? 0),
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

        $marks['day'] = $yesterday;
    }

    // The 420 days before the record, a slice at a time.
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

    // The forecast, once the night's row and the fill are in.
    if (erp_stock_plan_rebuild_due($marks, $yesterday, erp_stock_plan_cache_ready())) {

        $rebuilt = erp_stock_plan_rebuild($marks, $today, $time_left);

        if ($rebuilt !== 'done') {
            return array('ran' => ($rebuilt === 'more'), 'reason' => $rebuilt, 'rows_day' => ($rebuilt === 'more') ? $yesterday : '');
        }
    }

    return array('ran' => true, 'reason' => 'done', 'rows_day' => $yesterday);
}

/* ---------------------------------------------------------------------------
 * The forecast (erp_stock_plan): one row per tracked product, rebuilt every
 * night after the day's row. Pure functions first, then the reads and the
 * rebuild.
 * ------------------------------------------------------------------------- */

// Days the sales speed is read over, ending yesterday.
define('ERP_STOCK_PLAN_SPEED_DAYS', 56);
// Fewer in-stock days than this in the speed window: not enough history.
define('ERP_STOCK_PLAN_MIN_DAYS', 14);
// Last year's weeks: the season looks this many days back (52 weeks, so a
// weekday falls on the same weekday).
define('ERP_STOCK_PLAN_YEAR_DAYS', 364);
// Each season window must have sold at least this many units.
define('ERP_STOCK_PLAN_SEASON_MIN_UNITS', 10);
define('ERP_STOCK_PLAN_SEASON_MIN', 0.5);
define('ERP_STOCK_PLAN_SEASON_MAX', 2.0);
// The supplier's last orders with an invoice the lead time is measured on.
define('ERP_STOCK_PLAN_LEAD_ORDERS', 5);
// An order-by day within this many days is Soon.
define('ERP_STOCK_PLAN_SOON_DAYS', 14);
// Products rebuilt per batch.
define('ERP_STOCK_PLAN_BATCH', 500);
// Purchase orders read for the supplier of a product never invoiced.
define('ERP_STOCK_PLAN_ORDER_SCAN', 5000);
define('ERP_STOCK_PLAN_MARK_PLANNED', 'erp_stock_plan_planned');
define('ERP_STOCK_PLAN_MARK_CURSOR', 'erp_stock_plan_cursor');

/**
 * Safety, cover and dead-stock days. Defaults until the settings card
 * defines its constants: safety 7, cover 30, dead stock 90. Pure.
 *
 * @return array{safety:int,cover:int,dead:int}
 */
function erp_stock_plan_settings()
{
    return array(
        'safety' => defined('ERP_STOCK_PLAN_SAFETY_DAYS') ? max(0, (int) ERP_STOCK_PLAN_SAFETY_DAYS) : 7,
        'cover'  => defined('ERP_STOCK_PLAN_COVER_DAYS') ? max(0, (int) ERP_STOCK_PLAN_COVER_DAYS) : 30,
        'dead'   => defined('ERP_STOCK_PLAN_DEAD_DAYS') ? max(1, (int) ERP_STOCK_PLAN_DEAD_DAYS) : 90,
    );
}

/**
 * Whole days from one day to another (negative when $to is earlier). Pure.
 *
 * @param string $from Y-m-d
 * @param string $to Y-m-d
 * @return int
 */
function erp_stock_plan_days_between($from, $to)
{
    $a = new DateTime((string) $from . ' 12:00:00', new DateTimeZone('UTC'));
    $b = new DateTime((string) $to . ' 12:00:00', new DateTimeZone('UTC'));

    return (int) round(($b->getTimestamp() - $a->getTimestamp()) / 86400);
}

/**
 * (P3) Units a day: the units of the 56 days before today on which the
 * product was in stock, over those days. A day with stock 0 is out of stock
 * and left out, its units too; a day with no row was in stock and sold
 * nothing; a day before the record began (stock unknown) counts as in stock.
 * Days before $history_from are not known at all and are not counted. Under
 * 14 in-stock days, or no sale on them: null, not enough history. Pure.
 *
 * @param array<string,array{sold:int,stock:int|null}> $days day => row
 * @param string $today Y-m-d
 * @param string $history_from The first day the record holds.
 * @return float|null
 */
function erp_stock_plan_speed(array $days, $today, $history_from)
{
    $in_stock = 0;
    $units = 0;

    for ($back = 1; $back <= ERP_STOCK_PLAN_SPEED_DAYS; $back++) {

        $day = erp_stock_plan_shift_day($today, -$back);

        if (($history_from !== '') && ($day < $history_from)) {
            break;
        }

        $row = $days[$day] ?? null;

        if (($row !== null) && ($row['stock'] !== null) && ((int) $row['stock'] === 0)) {
            continue;
        }

        $in_stock++;
        $units += ($row === null) ? 0 : max(0, (int) $row['sold']);
    }

    if (($in_stock < ERP_STOCK_PLAN_MIN_DAYS) || ($units <= 0)) {
        return null;
    }

    return (float) $units / $in_stock;
}

/**
 * (P4) Season: last year's units in the coming 56 days over last year's
 * units in the 56 days before them, between 0.5 and 2. 1 when either window
 * sold under 10 units or reaches back before the record. Pure.
 *
 * @param array<string,array{sold:int,stock:int|null}> $days
 * @param string $today
 * @param string $history_from
 * @return float
 */
function erp_stock_plan_season(array $days, $today, $history_from)
{
    $year_ago = erp_stock_plan_shift_day($today, -ERP_STOCK_PLAN_YEAR_DAYS);
    $before_from = erp_stock_plan_shift_day($year_ago, -ERP_STOCK_PLAN_SPEED_DAYS);

    if (($history_from === '') || ($before_from < $history_from)) {
        return 1.0;
    }

    $ahead = 0;
    $before = 0;

    for ($i = 0; $i < ERP_STOCK_PLAN_SPEED_DAYS; $i++) {
        $ahead += max(0, (int) ($days[erp_stock_plan_shift_day($year_ago, $i)]['sold'] ?? 0));
        $before += max(0, (int) ($days[erp_stock_plan_shift_day($before_from, $i)]['sold'] ?? 0));
    }

    if (($ahead < ERP_STOCK_PLAN_SEASON_MIN_UNITS) || ($before < ERP_STOCK_PLAN_SEASON_MIN_UNITS)) {
        return 1.0;
    }

    return round(min(ERP_STOCK_PLAN_SEASON_MAX, max(ERP_STOCK_PLAN_SEASON_MIN, $ahead / $before)), 2);
}

/**
 * (P5) Each product's supplier: the account of its latest purchase
 * invoice, else of its latest purchase order. Pure.
 *
 * @param array $invoice_lines product_id, account_id, issue_date, id
 * @param array $orders account_id, issue_date, id, lines (each product_id)
 * @return array<int,array{account_id:int,source:string}> product id => supplier
 */
function erp_stock_plan_suppliers(array $invoice_lines, array $orders)
{
    $newer = function ($row, $than) {
        return ($than === null)
            || ((string) $row['issue_date'] > (string) $than['issue_date'])
            || (((string) $row['issue_date'] === (string) $than['issue_date']) && ((int) $row['id'] > (int) $than['id']));
    };

    $from_invoice = array();

    foreach ($invoice_lines as $row) {
        $id = (int) $row['product_id'];
        if (($id > 0) && ((int) $row['account_id'] > 0) && $newer($row, $from_invoice[$id] ?? null)) {
            $from_invoice[$id] = $row;
        }
    }

    $from_order = array();

    foreach ($orders as $order) {
        if ((int) $order['account_id'] <= 0) {
            continue;
        }

        foreach ((array) ($order['lines'] ?? array()) as $line) {
            $id = (int) ($line['product_id'] ?? 0);
            if (($id > 0) && $newer($order, $from_order[$id] ?? null)) {
                $from_order[$id] = $order;
            }
        }
    }

    $suppliers = array();

    foreach ($from_order as $id => $order) {
        $suppliers[$id] = array('account_id' => (int) $order['account_id'], 'source' => 'order');
    }

    foreach ($from_invoice as $id => $row) {
        $suppliers[$id] = array('account_id' => (int) $row['account_id'], 'source' => 'invoice');
    }

    ksort($suppliers);

    return $suppliers;
}

/**
 * (P6) Measured lead times: per supplier, the median of the days from a
 * purchase order's date to its first purchase invoice, over its last five
 * orders that have one. Pure.
 *
 * @param array $orders account_id, id, issue_date, invoice_date (first invoice)
 * @return array<int,int> account id => days
 */
function erp_stock_plan_lead_measured(array $orders)
{
    usort($orders, function ($a, $b) {
        $by_date = strcmp((string) $b['issue_date'], (string) $a['issue_date']);
        return ($by_date !== 0) ? $by_date : ((int) $b['id'] - (int) $a['id']);
    });

    $spans = array();

    foreach ($orders as $order) {
        $account = (int) $order['account_id'];

        if (($account <= 0) || (count($spans[$account] ?? array()) >= ERP_STOCK_PLAN_LEAD_ORDERS)) {
            continue;
        }

        $spans[$account][] = max(0, erp_stock_plan_days_between($order['issue_date'], $order['invoice_date']));
    }

    $lead = array();

    foreach ($spans as $account => $days) {
        sort($days);
        $count = count($days);
        $middle = intdiv($count, 2);
        $lead[$account] = ($count % 2 === 1) ? $days[$middle] : (int) round(($days[$middle - 1] + $days[$middle]) / 2);
    }

    ksort($lead);

    return $lead;
}

/**
 * (P6) A supplier's lead time and where it came from: measured, else the
 * days typed on the account card (0 = not typed), else unknown. Pure.
 *
 * @param int|null $measured
 * @param int $typed
 * @return array{days:int|null,source:string} source measured|typed|none
 */
function erp_stock_plan_lead($measured, $typed)
{
    if ($measured !== null) {
        return array('days' => max(0, (int) $measured), 'source' => 'measured');
    }

    if ((int) $typed > 0) {
        return array('days' => (int) $typed, 'source' => 'typed');
    }

    return array('days' => null, 'source' => 'none');
}

/**
 * Open quantities per product: what purchase orders still wait for.
 * Pure.
 *
 * @param array $progress_lists erp_purchase_order_progress() of each open order
 * @return array<int,float> product id => units
 */
function erp_stock_plan_open_quantities(array $progress_lists)
{
    $open = array();

    foreach ($progress_lists as $progress) {
        foreach ((array) $progress as $row) {
            $id = (int) ($row['product_id'] ?? 0);
            if (($id > 0) && ((float) $row['open'] > 0)) {
                $open[$id] = round(($open[$id] ?? 0) + (float) $row['open'], 4);
            }
        }
    }

    ksort($open);

    return $open;
}

/**
 * (P7, P8, P9) A product's plan. Days left are the stock over units a day
 * with the season; the run-out day is today plus them; the order-by day is
 * the run-out day less lead time and safety days, none while the lead time
 * is unknown. The quantity covers lead time, safety and cover days, less the
 * stock and the open quantity on purchase orders, rounded up, never below 0;
 * an unknown lead time counts as 0 days for the quantity and the state.
 * Pure.
 *
 * @param array $in speed (float|null), season, stock, open, lead (int|null), today
 * @param array $settings erp_stock_plan_settings()
 * @return array{days_left:int|null,runout_day:string|null,order_by_day:string|null,quantity:int,state:string}
 */
function erp_stock_plan_forecast(array $in, array $settings)
{
    $speed = $in['speed'];
    $today = (string) $in['today'];

    if (($speed === null) || ($speed <= 0)) {
        return array('days_left' => null, 'runout_day' => null, 'order_by_day' => null, 'quantity' => 0, 'state' => 'no_history');
    }

    $daily = $speed * (float) $in['season'];
    $stock = max(0, (int) $in['stock']);
    $lead = ($in['lead'] === null) ? null : max(0, (int) $in['lead']);
    $lead_days = ($lead === null) ? 0 : $lead;

    $days_left = (int) floor(round($stock / $daily, 6));
    $runout = erp_stock_plan_shift_day($today, $days_left);
    $order_by = erp_stock_plan_shift_day($runout, -($lead_days + (int) $settings['safety']));

    $need = $daily * ($lead_days + (int) $settings['safety'] + (int) $settings['cover']) - $stock - (float) $in['open'];
    $quantity = max(0, (int) ceil(round($need, 6)));

    if (($lead !== null) && ($days_left < $lead)) {
        $state = 'runs_out';
    } elseif ($order_by <= $today) {
        $state = 'order_now';
    } elseif ($order_by <= erp_stock_plan_shift_day($today, ERP_STOCK_PLAN_SOON_DAYS)) {
        $state = 'soon';
    } else {
        $state = 'fine';
    }

    return array(
        'days_left'    => $days_left,
        'runout_day'   => $runout,
        'order_by_day' => ($lead === null) ? null : $order_by,
        'quantity'     => $quantity,
        'state'        => $state,
    );
}

/**
 * (P10) Dead stock: in stock and no sale for the dead-stock days (counted
 * from the last sale, or from the first day the record holds when it never
 * sold). Its value is units times the average cost, in minor units; null
 * when no cost is known. Pure.
 *
 * @param int $stock
 * @param string|null $last_sale Y-m-d
 * @param string $history_from
 * @param string $today
 * @param int $dead_days
 * @param int|null $avg_cost Minor units; null or 0 = not known.
 * @return array{dead:bool,value:int|null}
 */
function erp_stock_plan_dead($stock, $last_sale, $history_from, $today, $dead_days, $avg_cost)
{
    $since = (($last_sale !== null) && ($last_sale !== '')) ? (string) $last_sale : (string) $history_from;
    $stock = (int) $stock;

    if (($stock <= 0) || ($since === '') || (erp_stock_plan_days_between($since, $today) <= (int) $dead_days)) {
        return array('dead' => false, 'value' => null);
    }

    $known = ($avg_cost !== null) && ((int) $avg_cost > 0);

    return array('dead' => true, 'value' => $known ? (int) round($stock * (int) $avg_cost) : null);
}

/**
 * The INSERT of a batch of plan rows. The notice stamp is the bell's and
 * survives the rebuild. Pure.
 *
 * @param array $rows
 * @return string
 */
function erp_stock_plan_cache_sql(array $rows)
{
    $sql_day = function ($day) {
        return ($day === null) ? 'NULL' : "'" . erp_stock_plan_shift_day($day, 0) . "'";
    };

    $values = array();

    foreach ($rows as $row) {
        $values[] = '(' . (int) $row['product_id'] . ', '
            . number_format((float) $row['units_day'], 3, '.', '') . ', '
            . number_format((float) $row['season'], 2, '.', '') . ', '
            . (int) $row['supplier_id'] . ', '
            . (($row['lead_days'] === null) ? 'NULL' : min(65535, max(0, (int) $row['lead_days']))) . ", '"
            . (in_array($row['lead_source'], array('measured', 'typed'), true) ? $row['lead_source'] : 'none') . "', "
            . (($row['days_left'] === null) ? 'NULL' : max(0, (int) $row['days_left'])) . ', '
            . $sql_day($row['runout_day']) . ', '
            . $sql_day($row['order_by_day']) . ', '
            . max(0, (int) $row['quantity']) . ", '"
            . (in_array($row['state'], array('runs_out', 'order_now', 'soon', 'fine'), true) ? $row['state'] : 'no_history') . "', "
            . $sql_day($row['last_sale']) . ')';
    }

    return "INSERT INTO erp_stock_plan (product_id, units_day, season, supplier_id, lead_days, lead_source, days_left,
        runout_day, order_by_day, quantity, state, last_sale) VALUES " . implode(', ', $values) . "
        ON DUPLICATE KEY UPDATE units_day = VALUES(units_day), season = VALUES(season), supplier_id = VALUES(supplier_id),
        lead_days = VALUES(lead_days), lead_source = VALUES(lead_source), days_left = VALUES(days_left),
        runout_day = VALUES(runout_day), order_by_day = VALUES(order_by_day), quantity = VALUES(quantity),
        state = VALUES(state), last_sale = VALUES(last_sale)";
}

/**
 * One product's cache row from what the night read. Pure.
 *
 * @param int $product_id
 * @param array $ctx days, stock, today, history_from, supplier, lead, open, last_sale
 * @param array $settings
 * @return array
 */
function erp_stock_plan_product_row($product_id, array $ctx, array $settings)
{
    $speed = erp_stock_plan_speed($ctx['days'], $ctx['today'], $ctx['history_from']);
    $season = ($speed === null) ? 1.0 : erp_stock_plan_season($ctx['days'], $ctx['today'], $ctx['history_from']);
    $lead = $ctx['lead'];

    $plan = erp_stock_plan_forecast(array(
        'speed'  => $speed,
        'season' => $season,
        'stock'  => $ctx['stock'],
        'open'   => $ctx['open'],
        'lead'   => $lead['days'],
        'today'  => $ctx['today'],
    ), $settings);

    return array(
        'product_id'   => (int) $product_id,
        'units_day'    => ($speed === null) ? 0.0 : round($speed, 3),
        'season'       => $season,
        'supplier_id'  => (int) $ctx['supplier'],
        'lead_days'    => $lead['days'],
        'lead_source'  => $lead['source'],
        'days_left'    => $plan['days_left'],
        'runout_day'   => $plan['runout_day'],
        'order_by_day' => $plan['order_by_day'],
        'quantity'     => $plan['quantity'],
        'state'        => $plan['state'],
        'last_sale'    => $ctx['last_sale'],
    );
}

/**
 * Whether tonight's plan is still to be built: after the night's row and
 * the fill, once a night. Pure.
 *
 * @param array $marks erp_stock_plan_marks()
 * @param string $yesterday
 * @param bool $ready The table is in place.
 * @return bool
 */
function erp_stock_plan_rebuild_due(array $marks, $yesterday, $ready)
{
    return $ready && ($marks['began'] !== '') && !empty($marks['filled'])
        && ($marks['day'] >= $yesterday) && (($marks['planned'] ?? '') < $yesterday);
}

/**
 * Whether erp_stock_plan is in place (2026.11.0, 11.0.251).
 *
 * @return bool
 */
function erp_stock_plan_cache_ready()
{
    return pg_schema_has('erp_stock_plan');
}

/**
 * The first day the record holds: the fill's start, or the day the record
 * began while the fill has not run, never before the pruning edge.
 *
 * @param array $marks erp_stock_plan_marks()
 * @param string $today
 * @return string
 */
function erp_stock_plan_history_from(array $marks, $today)
{
    if ($marks['began'] === '') {
        return '';
    }

    $from = $marks['filled'] ? erp_stock_plan_fill_range($marks['began'])['from'] : (($marks['fill'] !== '') ? $marks['fill'] : $marks['began']);

    return max($from, erp_stock_plan_shift_day($today, -ERP_STOCK_PLAN_KEEP_DAYS));
}

/**
 * What every batch of the rebuild shares: suppliers' lead times, the typed
 * lead days, the open quantities and the purchase orders scanned for a
 * supplier.
 *
 * @return array|false
 */
function erp_stock_plan_shared()
{
    $shared = array('measured' => array(), 'typed' => array(), 'open' => array(), 'orders' => array());

    if (!function_exists('erp_purchase_orders_ready') || !erp_purchase_orders_ready()) {
        return $shared;
    }

    $orders = erp_stock_plan_select("SELECT o.id, o.account_id, o.issue_date, MIN(i.issue_date) AS invoice_date
        FROM erp_purchase_orders o
        JOIN erp_invoices i ON i.purchase_order_id = o.id
            AND i.direction = 'purchase' AND i.doc_type = 'invoice' AND i.status NOT IN ('draft', 'cancelled')
        WHERE o.status <> 'cancelled' AND o.account_id > 0
        GROUP BY o.id, o.account_id, o.issue_date");

    if ($orders === false) {
        return false;
    }

    $shared['measured'] = erp_stock_plan_lead_measured($orders);

    if (pg_schema_has('erp_accounts', 'lead_days')) {

        $rows = erp_stock_plan_select("SELECT id, lead_days FROM erp_accounts WHERE lead_days > 0");

        if ($rows === false) {
            return false;
        }

        foreach ($rows as $row) {
            $shared['typed'][(int) $row['id']] = (int) $row['lead_days'];
        }
    }

    $rows = erp_stock_plan_select("SELECT id, account_id, issue_date, status, line_data FROM erp_purchase_orders
        WHERE status <> 'cancelled'
        ORDER BY issue_date DESC, id DESC
        LIMIT " . ERP_STOCK_PLAN_ORDER_SCAN);

    if ($rows === false) {
        return false;
    }

    $progress = array();

    foreach ($rows as $row) {

        $lines = erp_purchase_order_lines($row);
        $shared['orders'][] = array('id' => (int) $row['id'], 'account_id' => (int) $row['account_id'], 'issue_date' => (string) $row['issue_date'], 'lines' => $lines);

        if (in_array((string) $row['status'], array('open', 'sent', 'partial'), true)) {
            $quantities = erp_purchase_order_quantities((int) $row['id']);
            $progress[] = erp_purchase_order_progress($lines, $quantities['received'], $quantities['drafted']);
        }
    }

    $shared['open'] = erp_stock_plan_open_quantities($progress);

    return $shared;
}

/**
 * Rebuild the plan rows of one batch of tracked products after $cursor.
 *
 * @param int $cursor
 * @param array $shared erp_stock_plan_shared()
 * @param string $today
 * @param string $history_from
 * @param array $settings
 * @return int|false The last product id done, 0 when no product is left.
 */
function erp_stock_plan_rebuild_batch($cursor, array $shared, $today, $history_from, array $settings)
{
    $products = erp_stock_plan_select("SELECT id, inventory_quantity FROM products
        WHERE inventory = 1" . (pg_bundles_ready() ? ' AND bundle = 0' : '') . " AND id > " . (int) $cursor . "
        ORDER BY id ASC LIMIT " . ERP_STOCK_PLAN_BATCH);

    if ($products === false) {
        return false;
    }

    if (empty($products)) {
        return (db_q("DELETE FROM erp_stock_plan WHERE product_id > ?", array((int) $cursor)) === false) ? false : 0;
    }

    $stock = array();

    foreach ($products as $row) {
        $stock[(int) $row['id']] = (int) $row['inventory_quantity'];
    }

    $ids = implode(',', array_keys($stock));
    $last_id = max(array_keys($stock));

    // The speed window and last year's two season windows.
    $speed_from = erp_stock_plan_shift_day($today, -ERP_STOCK_PLAN_SPEED_DAYS);
    $season_from = erp_stock_plan_shift_day($today, -(ERP_STOCK_PLAN_YEAR_DAYS + ERP_STOCK_PLAN_SPEED_DAYS));
    $season_to = erp_stock_plan_shift_day($today, -ERP_STOCK_PLAN_YEAR_DAYS + ERP_STOCK_PLAN_SPEED_DAYS - 1);

    $rows = erp_stock_plan_select("SELECT product_id, day, sold, stock FROM erp_stock_days
        WHERE product_id IN (" . $ids . ")
        AND ((day >= '" . $speed_from . "' AND day < '" . erp_stock_plan_shift_day($today, 0) . "')
            OR (day >= '" . $season_from . "' AND day <= '" . $season_to . "'))");
    $sales = erp_stock_plan_select("SELECT product_id, MAX(day) AS last_sale FROM erp_stock_days
        WHERE product_id IN (" . $ids . ") AND sold > 0 GROUP BY product_id");
    $invoice_lines = erp_stock_plan_select("SELECT ii.product_id, i.account_id, i.issue_date, i.id
        FROM erp_invoices i
        JOIN erp_invoice_items ii ON ii.invoice_id = i.id
        WHERE i.direction = 'purchase' AND i.doc_type = 'invoice' AND i.status NOT IN ('draft', 'cancelled')
        AND ii.product_id IN (" . $ids . ")");

    if (($rows === false) || ($sales === false) || ($invoice_lines === false)) {
        return false;
    }

    $days = array();

    foreach ($rows as $row) {
        $days[(int) $row['product_id']][(string) $row['day']] = array(
            'sold'  => (int) $row['sold'],
            'stock' => ($row['stock'] === null) ? null : (int) $row['stock'],
        );
    }

    $last_sale = array();

    foreach ($sales as $row) {
        $last_sale[(int) $row['product_id']] = (string) $row['last_sale'];
    }

    $suppliers = erp_stock_plan_suppliers($invoice_lines, $shared['orders']);
    $write = array();

    foreach ($stock as $id => $level) {

        $supplier = (int) ($suppliers[$id]['account_id'] ?? 0);
        $lead = ($supplier > 0)
            ? erp_stock_plan_lead($shared['measured'][$supplier] ?? null, (int) ($shared['typed'][$supplier] ?? 0))
            : array('days' => null, 'source' => 'none');

        $write[] = erp_stock_plan_product_row($id, array(
            'days'         => $days[$id] ?? array(),
            'stock'        => $level,
            'today'        => $today,
            'history_from' => $history_from,
            'supplier'     => $supplier,
            'lead'         => $lead,
            'open'         => (float) ($shared['open'][$id] ?? 0),
            'last_sale'    => $last_sale[$id] ?? null,
        ), $settings);
    }

    // A product no longer tracked (or deleted, or now a bundle) loses its row.
    if ((db_q(erp_stock_plan_cache_sql($write)) === false)
        || (db_q("DELETE FROM erp_stock_plan WHERE product_id > ? AND product_id <= ? AND product_id NOT IN (" . $ids . ")",
            array((int) $cursor, (int) $last_id)) === false)) {
        return false;
    }

    return (int) $last_id;
}

/**
 * The night's rebuild of the plan, in batches; resumes from the cursor mark.
 *
 * @param array $marks
 * @param string $today
 * @param callable $time_left
 * @return string done|more|error
 */
function erp_stock_plan_rebuild(array $marks, $today, $time_left)
{
    $shared = erp_stock_plan_shared();

    if ($shared === false) {
        return 'error';
    }

    $settings = erp_stock_plan_settings();
    $history_from = erp_stock_plan_history_from($marks, $today);
    $cursor = (int) db_value("SELECT last_run_at FROM cron_runs WHERE job_name = '" . ERP_STOCK_PLAN_MARK_CURSOR . "' LIMIT 1");

    while (true) {

        if ($time_left() <= 5) {
            return 'more';
        }

        $done = erp_stock_plan_rebuild_batch($cursor, $shared, $today, $history_from, $settings);

        if ($done === false) {
            return 'error';
        }

        if ($done === 0) {
            break;
        }

        $cursor = $done;

        if (!erp_stock_plan_mark_set(ERP_STOCK_PLAN_MARK_CURSOR, $cursor)) {
            return 'error';
        }
    }

    if (!erp_stock_plan_mark_set(ERP_STOCK_PLAN_MARK_CURSOR, 0)
        || !erp_stock_plan_mark_set(ERP_STOCK_PLAN_MARK_PLANNED, erp_stock_plan_day_mark(erp_stock_plan_shift_day($today, -1)))) {
        return 'error';
    }

    return 'done';
}
