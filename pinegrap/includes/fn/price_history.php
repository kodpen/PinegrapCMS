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
 * with the newest row a writer wrote and writes a row only where the two
 * differ. A save that left the price alone writes nothing, a writer that
 * cannot tell whether it changed the price (the product screen) can call it
 * every time, and no row can carry a price that never reached the table.
 *
 * An offer changes what a visitor pays without changing products.price, and a
 * past offer price inside the period is a price that applied. The general job
 * records those (pg_price_history_offer_pass_run(), source 'offer'): once an
 * hour, the price the open offers give a visitor with no offer code and no
 * account, for each product where it differs from the newest row, so an
 * offer's start, its end and a change of it are all rows. Read in order, the
 * newest row at or before a moment is what such a visitor paid then.
 *
 * products.price is the shop's own price, so every row written here has the
 * channel 'web'. The column is there for a price kept per sales channel later
 * (a marketplace account may sell at prices set by hand on its side).
 *
 * The rows are read back, oldest first, through pg_price_history_list_sql():
 * the external API lists them under GET /products/{id}/price-history
 * (includes/api/resources/products.php), and the MCP tools run the same route.
 *
 * Recording never stops the request that changed the price: the price is
 * already saved when it runs. A failed statement is written to the error log
 * and the next save of that product catches up, because the comparison is
 * with the newest row and not with the previous price.
 *
 * The same step added products.compare_at_price, the merchant's own "was"
 * price for an article. It is not a price the article sells at, so it never
 * reaches the history; the helpers at the end of this file say how it is read
 * and stored by every writer (the product screen and list, the import, the
 * external API, the copy paths).
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
 * import file; api: the external API and the MCP tools; offer: the general
 * job's record of what the open offers made the price; erp is kept for a
 * writer that will need it.
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
 * $except_source leaves the rows of one source out. The writers of
 * products.price pass 'offer': an offer row holds what visitors were shown
 * with an offer applied, not the stored price, so comparing the stored price
 * with it would write a row on every save made while an offer runs.
 *
 * @param int[]  $ids
 * @param string $channel
 * @param string $except_source '' for every source, or one of pg_price_history_sources()
 * @return string '' when the channel or the source is not one, or there is no id
 */
function pg_price_history_newest_sql($ids, $channel, $except_source = '')
{
    $channel = pg_price_history_channel($channel);
    $ids = pg_price_history_ids($ids);

    if (($channel === null) || !$ids) {
        return '';
    }

    $source_sql = '';

    if ($except_source !== '') {
        if (!in_array($except_source, pg_price_history_sources(), true)) {
            return '';
        }

        $source_sql = "
                AND (source <> '" . $except_source . "')";
    }

    return "SELECT history.product_id, history.price, history.currency
        FROM product_price_history AS history
        INNER JOIN (
            SELECT MAX(id) AS id
            FROM product_price_history
            WHERE
                (product_id IN (" . implode(',', $ids) . "))
                AND (channel = '" . $channel . "')" . $source_sql . "
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
 * The query that lists one product's history rows, oldest first: what the
 * external API answers GET /products/{id}/price-history with, and the order
 * a window's lowest price is read in.
 *
 * Rows are ordered by changed_at and then by id, and a cursor compares the
 * pair, so two rows stamped in the same second are each walked once. The
 * optional channel narrows the rows to one sales channel and is written into
 * the statement as it is, so only a name pg_price_history_channel() accepts
 * reaches it; $since keeps rows stamped at or after a moment.
 *
 * @param int|string $product_id
 * @param string     $channel  '' or null for every channel
 * @param int        $since    unix time, 0 for no lower bound
 * @param array|null $cursor   array('v' => changed_at, 'i' => id) of the last row read, as api_cursor_decode() gives it
 * @param int        $rows     rows to fetch (a caller fetches one more than its page to learn whether there is another)
 * @return string '' when the product id is not one or the channel is not a channel
 */
function pg_price_history_list_sql($product_id, $channel = null, $since = 0, $cursor = null, $rows = 50)
{
    $ids = pg_price_history_ids($product_id);

    if (count($ids) !== 1) {
        return '';
    }

    $where = array("product_id = '" . $ids[0] . "'");

    if (is_string($channel) && ($channel !== '')) {
        $channel = pg_price_history_channel($channel);

        if ($channel === null) {
            return '';
        }

        $where[] = "channel = '" . $channel . "'";
    }

    if ((int) $since > 0) {
        $where[] = "changed_at >= '" . (int) $since . "'";
    }

    if (is_array($cursor) && isset($cursor['v'], $cursor['i'])) {
        $where[] = "(changed_at > '" . (int) $cursor['v'] . "'
                OR (changed_at = '" . (int) $cursor['v'] . "' AND id > '" . (int) $cursor['i'] . "'))";
    }

    return "SELECT id, channel, price, currency, changed_at, source
        FROM product_price_history
        WHERE " . implode(' AND ', $where) . "
        ORDER BY changed_at ASC, id ASC
        LIMIT " . max(1, (int) $rows);
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

        // Against the newest row a writer of products.price wrote: an offer
        // row is not a stored price (pg_price_history_newest_sql()).
        $result = @mysqli_query(db::$con, pg_price_history_newest_sql(array_keys($current), PG_PRICE_HISTORY_WEB, 'offer'));

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

/* ------------------------------------------------------------------ *
 * Offer prices: the general job's pass
 * ------------------------------------------------------------------ */

/**
 * How often the general job looks at the offers, in seconds. Offers start and
 * end by the day and are changed by hand, so an hour is close enough: a
 * price is recorded at most this long after it applies, and stays in the
 * history at most this long after it stopped.
 */
define('PG_PRICE_HISTORY_OFFER_EVERY', 3600);

/**
 * Seconds one tick of the general job spends on the pass; a large catalogue
 * is walked over several ticks.
 */
define('PG_PRICE_HISTORY_OFFER_BUDGET', 10);

/**
 * The price the offer pass records for each product: the stored price, or
 * the lowest price the open offers give it, in whole minor units as the cart
 * charges it (it stores the discounted figure in an INT column, rounded).
 * An offer never raises a price: a discounted figure above the stored price
 * was worked out before the price was changed, and the stored price stands.
 *
 * @param array $prices     product id => products.price
 * @param array $discounted product id => discounted price, as get_discounted_product_prices() gives it (whole minor units)
 * @return array product id => price (int), in the order of $prices
 */
function pg_price_history_offer_prices($prices, $discounted)
{
    $out = array();

    foreach ($prices as $id => $price) {
        $id = (int) $id;
        $price = (int) $price;

        if (isset($discounted[$id]) && is_numeric($discounted[$id])) {
            $offer = max(0, (int) round((float) $discounted[$id]));

            if ($offer < $price) {
                $price = $offer;
            }
        }

        $out[$id] = $price;
    }

    return $out;
}

/**
 * The file the pass keeps between ticks: where a pass under way has got to,
 * when the last one finished and whether an offer gave any product a price
 * then. A file rather than a column: losing it (the cleanup of data/temp)
 * costs one full pass, nothing more.
 *
 * @return string
 */
function pg_price_history_offer_marker()
{
    return PG_FUNCTIONS_DIR . '/data/temp/price_history_offers.json';
}

/**
 * The pass's marker as it is used: the last product id done by a pass under
 * way (0: none under way), when the last pass finished, whether an offer gave
 * a price at the end of it, and whether one has so far in the pass under way.
 * Anything unreadable is a pass that never ran, as if offers had run then, so
 * the first pass is a full one.
 *
 * @param mixed $raw the decoded marker
 * @return array('after_id' => int, 'done_at' => int, 'offers' => bool, 'seen' => bool)
 */
function pg_price_history_offer_state($raw)
{
    $raw = is_array($raw) ? $raw : array();

    return array(
        'after_id' => isset($raw['after_id']) ? max(0, (int) $raw['after_id']) : 0,
        'done_at' => isset($raw['done_at']) ? max(0, (int) $raw['done_at']) : 0,
        'offers' => array_key_exists('offers', $raw) ? (bool) $raw['offers'] : true,
        'seen' => !empty($raw['seen']),
    );
}

/**
 * Whether a pass is due at a moment: one is under way, or the last one
 * finished PG_PRICE_HISTORY_OFFER_EVERY seconds ago or more.
 *
 * @param array $state pg_price_history_offer_state()
 * @param int   $now
 * @return bool
 */
function pg_price_history_offer_pass_due_at($state, $now)
{
    return ($state['after_id'] > 0) || ($state['done_at'] <= ((int) $now - PG_PRICE_HISTORY_OFFER_EVERY));
}

/**
 * Whether a due pass has nothing to look at: no offer gives any product a
 * price now, none did at the end of the last pass, and no pass is under way.
 * The history then already holds what visitors are shown, the stored prices,
 * which their writers record themselves.
 *
 * @param array $state      pg_price_history_offer_state()
 * @param array $discounted get_discounted_product_prices() now
 * @return bool
 */
function pg_price_history_offer_pass_idle($state, $discounted)
{
    return !$discounted && !$state['offers'] && ($state['after_id'] === 0);
}

/**
 * Whether the general job should run the pass on this tick.
 *
 * @return bool
 */
function pg_price_history_offer_pass_due()
{
    if (!defined('ECOMMERCE') || (ECOMMERCE !== true) || !pg_price_history_ready()) {
        return false;
    }

    $state = pg_price_history_offer_state(@json_decode((string) @file_get_contents(pg_price_history_offer_marker()), true));

    return pg_price_history_offer_pass_due_at($state, time());
}

/**
 * Record, for every product whose price an offer has changed since the
 * newest row of its history, the price visitors are shown now (source
 * 'offer'). Products are walked by id for as many seconds as the tick gives;
 * a pass that runs out of time goes on from the same id on the next tick.
 *
 * The offers are read as get_discounted_product_prices() reads them for a
 * visitor with no special offer code, no account and nothing in the cart,
 * the member prices left out: a price that applies to everybody. Only a run
 * with no signed-in user and no shopping session has that view, which a
 * background run of the general job is; any other run leaves the pass for
 * the next background tick.
 *
 * Written rows are compared with the newest row of any source, so the end of
 * an offer is recorded too: the stored price, as an offer row, from the hour
 * the offer stopped.
 *
 * @param int $seconds
 * @return array('state' => 'done'|'more'|'idle'|'skipped', 'written' => int)
 */
function pg_price_history_offer_pass_run($seconds)
{
    $result = array('state' => 'skipped', 'written' => 0);

    if ((defined('USER_ID') && USER_ID) || !empty($_SESSION['ecommerce'])) {
        return $result;
    }

    if (!class_exists('db', false) || empty(db::$con) || !pg_price_history_ready() || !function_exists('get_discounted_product_prices')) {
        return $result;
    }

    $lock = "LEFT(CONCAT('pg_price_history_offers.', DATABASE()), 64)";

    if ((int) db_value("SELECT GET_LOCK(" . $lock . ", 0)") !== 1) {
        return $result;
    }

    $marker = pg_price_history_offer_marker();
    $state = pg_price_history_offer_state(@json_decode((string) @file_get_contents($marker), true));
    $discounted = get_discounted_product_prices(false);
    $currency = pg_price_history_currency();
    $deadline = microtime(true) + max(1, (int) $seconds);

    if (pg_price_history_offer_pass_idle($state, $discounted)) {
        $state['done_at'] = time();
        $result['state'] = 'idle';
    } else {
        $state['seen'] = $state['seen'] || (bool) $discounted;
        $result['state'] = 'more';

        while (microtime(true) < $deadline) {
            $products = @mysqli_query(db::$con,
                "SELECT id, price
                FROM products
                WHERE id > '" . (int) $state['after_id'] . "'
                ORDER BY id
                LIMIT " . PG_PRICE_HISTORY_CHUNK);

            if (!$products) {
                pg_price_history_log_failure('reading the prices for the offer pass');
                break;
            }

            $prices = array();

            while ($row = mysqli_fetch_assoc($products)) {
                $prices[(int) $row['id']] = (int) $row['price'];
            }

            if (!$prices) {
                $state = array('after_id' => 0, 'done_at' => time(), 'offers' => $state['seen'], 'seen' => false);
                $result['state'] = 'done';
                break;
            }

            $current = pg_price_history_offer_prices($prices, $discounted);
            $newest_result = @mysqli_query(db::$con, pg_price_history_newest_sql(array_keys($current), PG_PRICE_HISTORY_WEB));

            if (!$newest_result) {
                pg_price_history_log_failure('reading the history for the offer pass');
                break;
            }

            $newest = array();

            while ($row = mysqli_fetch_assoc($newest_result)) {
                $newest[(int) $row['product_id']] = $row;
            }

            $changes = pg_price_history_changes($current, $newest, $currency);
            $sql = pg_price_history_insert_sql($changes, PG_PRICE_HISTORY_WEB, $currency, 'offer', time());

            if (($sql !== '') && !@mysqli_query(db::$con, $sql)) {
                pg_price_history_log_failure('writing the offer prices');
                break;
            }

            if ($sql !== '') {
                $result['written'] += count($changes);
            }

            $ids = array_keys($prices);
            $state['after_id'] = (int) end($ids);
        }
    }

    @file_put_contents($marker, json_encode($state), LOCK_EX);

    db("SELECT RELEASE_LOCK(" . $lock . ")");

    return $result;
}

/* ------------------------------------------------------------------ *
 * Compare-at price (products.compare_at_price)
 * ------------------------------------------------------------------ */

/**
 * The largest compare-at price the column holds: a signed INT of minor
 * units, the same type as products.price.
 */
define('PG_COMPARE_AT_PRICE_MAX', 2147483647);

/**
 * True when products.compare_at_price is in the database (2026.11.0, 11.0.2).
 * Every reader and writer asks first: a site that took the code but not yet
 * the upgrade has no such column, and a statement naming it fails.
 *
 * @return bool
 */
function pg_compare_at_price_ready()
{
    return pg_schema_has('products', 'compare_at_price');
}

/**
 * products.compare_at_price as it is stored, from an amount already in minor
 * units: what the product screen's reader made of a typed amount
 * (pg_pb_compare_at_price_from_input()), the external API's money value, or a
 * stored value being copied.
 *
 * null means there is none. Nothing, an empty value and zero all say that: no
 * shop shows a "was" price of nothing, and an empty box or a 0 in an import
 * file is how a merchant takes one off. A whole number from 1 to
 * PG_COMPARE_AT_PRICE_MAX is kept as it is.
 *
 * false is a value that cannot be stored: negative, larger than the column,
 * or not a whole number of minor units. The caller refuses it. The session
 * is not strict, so MySQL would otherwise cut it to fit without a word.
 *
 * @param mixed $cents
 * @return int|null|false
 */
function pg_compare_at_price_value($cents)
{
    if ($cents === null) {
        return null;
    }

    if (is_string($cents)) {
        $cents = trim($cents);

        if ($cents === '') {
            return null;
        }

        if (preg_match('/^-?[0-9]{1,15}$/', $cents) !== 1) {
            return false;
        }

        $cents = (int) $cents;
    } elseif (is_float($cents)) {
        if (!is_finite($cents) || (floor($cents) != $cents) || (abs($cents) > 1e15)) {
            return false;
        }

        $cents = (int) $cents;
    } elseif (!is_int($cents)) {
        return false;
    }

    if ($cents === 0) {
        return null;
    }

    if (($cents < 0) || ($cents > PG_COMPARE_AT_PRICE_MAX)) {
        return false;
    }

    return $cents;
}

/**
 * The SQL value that writes a compare-at price: NULL for none, the quoted
 * whole number otherwise.
 *
 * It takes what pg_compare_at_price_value() accepts. A value that function
 * refuses is written as NULL, so a statement can never carry a figure the
 * column does not hold; a writer that must not clear a stored price on bad
 * input checks the value first and leaves the column out.
 *
 * @param mixed $value
 * @return string
 */
function pg_compare_at_price_sql($value)
{
    $value = pg_compare_at_price_value($value);

    return is_int($value) ? "'" . $value . "'" : 'NULL';
}
