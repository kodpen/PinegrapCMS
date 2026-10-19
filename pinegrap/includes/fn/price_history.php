<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Product price history: a row in product_price_history (2026.11.0, 11.0.2)
 * each time the price a product sells at changes.
 *
 * The "price before the discount" a shop may show beside a reduced price is
 * the lowest price of a period before the reduction: 30 days under the
 * Turkish price label regulation and the EU Omnibus rule, the previous 10
 * days for discount advertising in Turkey from 1 August 2026. That figure can
 * only be honest if this table sees every change, wherever it was made, so
 * every code path that writes products.price calls pg_price_history_record()
 * after its write:
 *
 *   - product_builder.php: pg_pb_create_product() (the product screens, a
 *     variant set and its price rules, the API, the Workspace, design
 *     templates), pg_pb_update_product(), pg_pb_quick_update() (the product
 *     list's in-place edit);
 *   - edit_products.php (mass edit), view_folder_and_files_f.php (bulk edit
 *     and quick edit of the file manager), import_products.php,
 *     duplicate_products.php and duplicate_product();
 *   - includes/api/resources/products.php (update and bulk prices; MCP tools
 *     run the same routes), includes/workspace/changes.php (record edit).
 *
 * A new writer of products.price adds the same call. A writer that changes
 * many products in a loop queues each one after its write with
 * pg_price_history_queue() and calls pg_price_history_flush() after the loop:
 * the queue is written every PG_PRICE_HISTORY_CHUNK products and once more
 * when the request ends, so a file import stopped halfway by an error or by
 * the server's time limit still has a row for every price it changed.
 *
 * pg_pb_delete_product() removes a deleted product's rows: MySQL 5.7 may hand
 * its id to a new product after a restart, which must not inherit them.
 *
 * Writers do not say what the price was or became. They name the products
 * they may have changed; the recorder reads the price now stored, compares it
 * with the newest row of the product's history and writes a row only where
 * the two differ. A save that left the price alone writes nothing, a writer
 * that cannot tell whether it changed the price (the product screen) can call
 * it every time, and no row can carry a price that never reached the table.
 *
 * products.price is the shop's own price, so every row written here has the
 * channel 'web'. The column is there for a price kept per sales channel later
 * (a marketplace account may sell at prices set by hand on its side).
 *
 * Recording never stops the request that changed the price: the price is
 * already saved when it runs. A failed statement is written to the error log
 * and the next save of that product catches up, because the comparison is
 * with the newest row and not with the previous price.
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
 * The channel products.price is sold through.
 */
define('PG_PRICE_HISTORY_WEB', 'web');

/**
 * Products read and written per statement, so a bulk change of thousands of
 * products stays a handful of short queries.
 */
define('PG_PRICE_HISTORY_CHUNK', 500);

/**
 * True when the table is in the database (2026.11.0, 11.0.2).
 *
 * @return bool
 */
function pg_price_history_ready()
{
    return pg_schema_has('product_price_history', 'source');
}

/**
 * The source labels a history row can carry.
 *
 * initial: the price the product had when the history began (the upgrade);
 * panel: a screen of the panel, the Workspace included; import: a product
 * import file; api: the external API and the MCP tools; offer and erp are
 * kept for the writers that will need them.
 *
 * @return string[]
 */
function pg_price_history_sources()
{
    return array('initial', 'panel', 'import', 'api', 'offer', 'erp');
}

/**
 * The label to store for a writer's source.
 *
 * An unknown label is stored as 'panel' rather than refused: the row matters
 * more than its label.
 *
 * @param mixed $source
 * @return string
 */
function pg_price_history_source($source)
{
    if (!is_string($source)) {
        return 'panel';
    }

    $source = strtolower(trim($source));

    return in_array($source, pg_price_history_sources(), true) ? $source : 'panel';
}

/**
 * The channel name to store, or null when it is not one: 'web', 'pos' or
 * 'marketplace:<code>' with a lower-case code. Only names of this shape reach
 * a statement, so they are written into it without escaping.
 *
 * @param mixed $channel
 * @return string|null
 */
function pg_price_history_channel($channel)
{
    if (!is_string($channel)) {
        return null;
    }

    return (preg_match('/^(web|pos|marketplace:[a-z0-9_-]{1,40})$/', $channel) === 1) ? $channel : null;
}

/**
 * A currency code as stored: up to three capital letters, nothing else.
 *
 * @param mixed $code
 * @return string
 */
function pg_price_history_currency_code($code)
{
    if (!is_string($code)) {
        return '';
    }

    return substr((string) preg_replace('/[^A-Z]/', '', strtoupper($code)), 0, 3);
}

/**
 * The product ids a writer handed over: one id or a list of them, as integers
 * or as the strings a form posts. Anything that is not a whole number above
 * zero is dropped, and each id is kept once.
 *
 * @param mixed $product_ids
 * @return int[]
 */
function pg_price_history_ids($product_ids)
{
    if (!is_array($product_ids)) {
        $product_ids = array($product_ids);
    }

    $ids = array();

    foreach ($product_ids as $id) {
        if (is_int($id)) {
            $id = (string) $id;
        }

        if (!is_string($id)) {
            continue;
        }

        $id = trim($id);

        if ((preg_match('/^[0-9]{1,10}$/', $id) !== 1) || ((int) $id <= 0)) {
            continue;
        }

        $ids[(int) $id] = true;
    }

    return array_keys($ids);
}

/**
 * The products whose stored price needs a history row.
 *
 * A product with no history gets its first row. One whose newest row has the
 * same price in the same currency gets none. A change of the base currency is
 * a new price even when the number stays: 100 lira and 100 dollars are not
 * the same price.
 *
 * @param array  $current  product id => price now stored (minor units)
 * @param array  $newest   product id => array('price' => ..., 'currency' => ...), the newest row of each product that has one
 * @param string $currency the base currency code
 * @return array product id => price (int), in the order of $current
 */
function pg_price_history_changes($current, $newest, $currency)
{
    $changes = array();

    foreach ($current as $id => $price) {
        $id = (int) $id;
        $price = (int) $price;

        if (isset($newest[$id])
            && ((int) $newest[$id]['price'] === $price)
            && ((string) $newest[$id]['currency'] === (string) $currency)) {
            continue;
        }

        $changes[$id] = $price;
    }

    return $changes;
}

/**
 * The query for the newest history row of each of the products, in one
 * channel. The id orders the rows of a product: they are only ever appended.
 *
 * @param int[]  $ids
 * @param string $channel
 * @return string '' when the channel is not one or there is no id
 */
function pg_price_history_newest_sql($ids, $channel)
{
    $channel = pg_price_history_channel($channel);
    $ids = pg_price_history_ids($ids);

    if (($channel === null) || !$ids) {
        return '';
    }

    return "SELECT history.product_id, history.price, history.currency
        FROM product_price_history AS history
        INNER JOIN (
            SELECT MAX(id) AS id
            FROM product_price_history
            WHERE
                (product_id IN (" . implode(',', $ids) . "))
                AND (channel = '" . $channel . "')
            GROUP BY product_id
        ) AS newest ON newest.id = history.id";
}

/**
 * The statement that writes one row per changed product, or '' when nothing
 * changed.
 *
 * @param array  $changes  product id => price, from pg_price_history_changes()
 * @param string $channel
 * @param string $currency
 * @param string $source   one of pg_price_history_sources()
 * @param int    $now
 * @return string '' when nothing changed or a label is not one the history keeps
 */
function pg_price_history_insert_sql($changes, $channel, $currency, $source, $now)
{
    $channel = pg_price_history_channel($channel);
    $currency = pg_price_history_currency_code($currency);

    if (!$changes || ($channel === null) || !in_array($source, pg_price_history_sources(), true)) {
        return '';
    }

    $values = array();

    foreach ($changes as $id => $price) {
        $values[] = "('" . (int) $id . "', '" . $channel . "', '" . (int) $price . "', '"
            . $currency . "', '" . (int) $now . "', '" . $source . "')";
    }

    return "INSERT INTO product_price_history (product_id, channel, price, currency, changed_at, source) VALUES "
        . implode(', ', $values);
}

/**
 * The base currency's code, which every products.price is kept in.
 *
 * init.php defines it for every request; a caller that runs without it reads
 * the currencies table the way init.php does, and falls back to USD as
 * init.php does when no currency is marked as the base.
 *
 * @return string
 */
function pg_price_history_currency()
{
    static $code = null;

    if ($code !== null) {
        return $code;
    }

    if (defined('BASE_CURRENCY_CODE')) {
        $code = (string) BASE_CURRENCY_CODE;
    } else {
        $result = @mysqli_query(db::$con, "SELECT code FROM currencies WHERE base = '1' LIMIT 1");
        $row = $result ? mysqli_fetch_row($result) : null;
        $code = (is_array($row) && isset($row[0]) && ((string) $row[0] !== '')) ? (string) $row[0] : 'USD';
    }

    // The migration that started the history falls back to USD the same way.
    $code = pg_price_history_currency_code($code);

    if ($code === '') {
        $code = 'USD';
    }

    return $code;
}

/**
 * Write a history row for each of the products whose stored price differs
 * from the newest row of its history. Called after a write that may have
 * changed products.price.
 *
 * @param int|string|array $product_ids
 * @param string           $source one of pg_price_history_sources()
 * @return int the rows written
 */
function pg_price_history_record($product_ids, $source)
{
    $ids = pg_price_history_ids($product_ids);

    if (!$ids || !class_exists('db', false) || empty(db::$con) || !pg_price_history_ready()) {
        return 0;
    }

    $source = pg_price_history_source($source);
    $currency = pg_price_history_currency();
    $written = 0;

    foreach (array_chunk($ids, PG_PRICE_HISTORY_CHUNK) as $chunk) {
        $result = @mysqli_query(db::$con, "SELECT id, price FROM products WHERE id IN (" . implode(',', $chunk) . ")");

        if (!$result) {
            pg_price_history_log_failure('reading the prices');
            return $written;
        }

        $current = array();

        while ($row = mysqli_fetch_assoc($result)) {
            $current[(int) $row['id']] = (int) $row['price'];
        }

        if (!$current) {
            continue;
        }

        $result = @mysqli_query(db::$con, pg_price_history_newest_sql(array_keys($current), PG_PRICE_HISTORY_WEB));

        if (!$result) {
            pg_price_history_log_failure('reading the history');
            return $written;
        }

        $newest = array();

        while ($row = mysqli_fetch_assoc($result)) {
            $newest[(int) $row['product_id']] = $row;
        }

        $changes = pg_price_history_changes($current, $newest, $currency);
        $sql = pg_price_history_insert_sql($changes, PG_PRICE_HISTORY_WEB, $currency, $source, time());

        if ($sql === '') {
            continue;
        }

        if (!@mysqli_query(db::$con, $sql)) {
            pg_price_history_log_failure('writing the history');
            return $written;
        }

        $written += count($changes);
    }

    return $written;
}

/**
 * The products queued by bulk writers and not written yet, per source label,
 * and whether the end-of-request flush is registered.
 *
 * @return array
 */
function &pg_price_history_queue_state()
{
    static $state = array('ids' => array(), 'shutdown' => false);

    return $state;
}

/**
 * Queue products a bulk writer has just written, to be recorded every
 * PG_PRICE_HISTORY_CHUNK products, at pg_price_history_flush() and, whatever
 * happens, when the request ends.
 *
 * @param int|string|array $product_ids
 * @param string           $source one of pg_price_history_sources()
 * @return void
 */
function pg_price_history_queue($product_ids, $source)
{
    $source = pg_price_history_source($source);
    $state = &pg_price_history_queue_state();

    foreach (pg_price_history_ids($product_ids) as $id) {
        $state['ids'][$source][$id] = true;
    }

    // exit() and output_error() end the request inside a loop; the functions
    // registered here still run, and so does the end of a request that ran
    // out of time.
    if (!$state['shutdown']) {
        $state['shutdown'] = true;
        register_shutdown_function('pg_price_history_flush');
    }

    if (isset($state['ids'][$source]) && (count($state['ids'][$source]) >= PG_PRICE_HISTORY_CHUNK)) {
        pg_price_history_flush();
    }
}

/**
 * Record every queued product and empty the queue.
 *
 * @return int the rows written
 */
function pg_price_history_flush()
{
    $state = &pg_price_history_queue_state();
    $queued = $state['ids'];
    $state['ids'] = array();
    $written = 0;

    foreach ($queued as $source => $ids) {
        $written += pg_price_history_record(array_keys($ids), $source);
    }

    return $written;
}

/**
 * Remove the history of a product that is deleted for good (not one moved to
 * the Recycle Bin, which keeps its id and its prices).
 *
 * @param int $product_id
 * @return void
 */
function pg_price_history_forget($product_id)
{
    $product_id = (int) $product_id;

    if (($product_id <= 0) || !class_exists('db', false) || empty(db::$con) || !pg_price_history_ready()) {
        return;
    }

    if (!@mysqli_query(db::$con, "DELETE FROM product_price_history WHERE product_id = '" . $product_id . "'")) {
        pg_price_history_log_failure('removing the history of a deleted product');
    }
}

/**
 * Put a failed history statement in the error log without ending the request.
 *
 * @param string $step
 * @return void
 */
function pg_price_history_log_failure($step)
{
    if (!function_exists('pg_error_write') || !function_exists('pg_error_entry')) {
        return;
    }

    pg_error_write(pg_error_entry(
        'warning',
        'E_USER_WARNING',
        'Price history: ' . $step . ' failed: ' . mysqli_error(db::$con),
        __FILE__,
        __LINE__,
        array()));
}
