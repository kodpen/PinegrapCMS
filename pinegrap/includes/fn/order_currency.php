<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The currency an order is charged in, and its rate.
 *
 * An order keeps its amounts in the currency it was charged in: every stored
 * total stays as it was charged, and no second total in the base currency is
 * stored. orders.charged_currency names that currency ('' for the base one)
 * and orders.charged_rate is how many base units one charged unit was worth
 * on the order's day. An order without a charged currency is in the base
 * currency, as every web order has been so far; a marketplace order may be
 * in the marketplace's currency.
 *
 * A sum over orders reads base units through pg_order_base_sql(); one order's
 * amount turns into base units with pg_order_base_amount(); a screen prints
 * an order's amount in its own currency with pg_order_money(). An order whose
 * rate is not known counts 0 in a base sum rather than its foreign figure as
 * if it were base.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

// Seconds of a general job tick the fill of the older marketplace orders may
// use.
define('PG_ORDER_CURRENCY_FILL_SECONDS', 5);

// Orders read per query by the fill.
define('PG_ORDER_CURRENCY_FILL_BATCH', 1000);

/**
 * A currency code as the order columns hold it: three letters in upper case,
 * '' for anything else. Pure.
 *
 * @param mixed $code
 * @return string
 */
function pg_order_currency_code($code)
{
    $code = strtoupper(trim((string) $code));

    return (preg_match('/^[A-Z]{3}$/', $code) === 1) ? $code : '';
}

/**
 * The charged currency and rate an order is written with. A code that is
 * empty or the base one is the base currency: '' and rate 0. Another code
 * takes the rate of the order's day when one is known, else today's, else 0
 * (not known). The rate is a string of eight decimals, as the column keeps
 * it. Pure.
 *
 * @param string $code        the order's currency code
 * @param string $base        BASE_CURRENCY_CODE
 * @param float  $day_rate    base units per unit on the order's day, 0 when none
 * @param float  $today_rate  base units per unit today, 0 when none
 * @return array  array('currency' => string, 'rate' => string)
 */
function pg_order_charged_pair($code, $base, $day_rate, $today_rate)
{
    $code = pg_order_currency_code($code);

    if (($code === '') || ($code === pg_order_currency_code($base))) {
        return array('currency' => '', 'rate' => '0.00000000');
    }

    $rate = ((float) $day_rate > 0) ? (float) $day_rate : (((float) $today_rate > 0) ? (float) $today_rate : 0.0);

    return array('currency' => $code, 'rate' => number_format($rate, 8, '.', ''));
}

/**
 * Whether orders has the charged currency columns yet. Files land before the
 * upgrade, so every reader and writer asks first.
 *
 * @return bool
 */
function pg_order_charged_ready()
{
    return pg_schema_has('orders', 'charged_currency') && pg_schema_has('orders', 'charged_rate');
}

/**
 * Base units one unit of a currency was worth on a day: the dated history
 * (pg_currency_rate(), the latest rate on or before the day). 0 when none
 * is recorded. Read once a request per code and day: the fill asks for
 * the same day for many orders.
 *
 * @param string $code
 * @param string $day   Y-m-d
 * @return float
 */
function pg_order_day_rate($code, $day)
{
    static $rates = array();

    if (!function_exists('pg_currency_rate') || !pg_currency_rates_table_exists()) {
        return 0.0;
    }

    $key = (string) $code . '|' . (string) $day;

    if (!array_key_exists($key, $rates)) {
        $rate = pg_currency_rate($code, $day);
        $rates[$key] = is_array($rate) ? (float) $rate['rate'] : 0.0;
    }

    return $rates[$key];
}

/**
 * Base units one unit of a currency is worth today: the store's own rate
 * (currencies.exchange_rate holds units per base unit, so its reciprocal),
 * else the latest dated rate. 0 when neither is known. Read once a request
 * per code.
 *
 * @param string $code
 * @return float
 */
function pg_order_today_rate($code)
{
    static $rates = array();

    $code = pg_order_currency_code($code);

    if ($code === '') {
        return 0.0;
    }

    if (!array_key_exists($code, $rates)) {
        $rate = 0.0;
        $stored = db_value("SELECT exchange_rate FROM currencies WHERE UPPER(code) = '" . e($code) . "' ORDER BY id LIMIT 1");

        if (is_numeric($stored) && ((float) $stored > 0)) {
            $rate = 1 / (float) $stored;
        } elseif (function_exists('pg_currency_rate_latest') && pg_currency_rates_table_exists()) {
            $latest = pg_currency_rate_latest($code);
            $rate = is_array($latest) ? (float) $latest['rate'] : 0.0;
        }

        $rates[$code] = $rate;
    }

    return $rates[$code];
}

/**
 * The charged currency and rate for an order in a currency on its day
 * (pg_order_charged_pair()), with the rates looked up; null while the
 * columns are not there, so the writer leaves them out.
 *
 * @param string $code        the order's currency code
 * @param int    $order_date  unix time
 * @return array|null
 */
function pg_order_charged_for($code, $order_date)
{
    if (!pg_order_charged_ready()) {
        return null;
    }

    $base = defined('BASE_CURRENCY_CODE') ? (string) BASE_CURRENCY_CODE : '';
    $code = pg_order_currency_code($code);

    if (($code === '') || ($code === pg_order_currency_code($base))) {
        return pg_order_charged_pair('', $base, 0, 0);
    }

    $day_rate = pg_order_day_rate($code, date('Y-m-d', ((int) $order_date > 0) ? (int) $order_date : time()));

    return pg_order_charged_pair($code, $base, $day_rate, ($day_rate > 0) ? 0 : pg_order_today_rate($code));
}

/**
 * The currency an order's amounts are in: its charged currency, the base
 * one when it has none. Pure.
 *
 * @param array  $order  charged_currency
 * @param string $base   BASE_CURRENCY_CODE
 * @return string
 */
function pg_order_amount_currency($order, $base)
{
    $code = pg_order_currency_code(isset($order['charged_currency']) ? $order['charged_currency'] : '');

    return ($code === '') ? pg_order_currency_code($base) : $code;
}

/**
 * One of an order's amounts in base minor units: as it is when the order is
 * in the base currency, times its rate rounded when it is in another, 0 when
 * that rate is not known. Pure.
 *
 * @param int    $amount  minor units of the order's currency
 * @param array  $order   charged_currency, charged_rate
 * @param string|null $base  BASE_CURRENCY_CODE when omitted
 * @return int
 */
function pg_order_base_amount($amount, $order, $base = null)
{
    $base = pg_order_currency_code(($base === null) ? (defined('BASE_CURRENCY_CODE') ? BASE_CURRENCY_CODE : '') : $base);
    $code = pg_order_currency_code(isset($order['charged_currency']) ? $order['charged_currency'] : '');

    if (($code === '') || ($code === $base)) {
        return (int) $amount;
    }

    $rate = isset($order['charged_rate']) ? (float) $order['charged_rate'] : 0.0;

    return ($rate > 0) ? (int) round((int) $amount * $rate) : 0;
}

/**
 * pg_order_base_amount() in SQL, for an expression over one order's amounts
 * (orders.total, or orders.total - orders.tax). The expression is returned
 * as it is while the columns are not there, which counts every order as
 * base, as before. Pure.
 *
 * @param string $expr   SQL over the order's amounts
 * @param string $base   BASE_CURRENCY_CODE
 * @param bool   $ready  pg_order_charged_ready()
 * @param string $table  the orders table's name or alias in the query
 * @return string  SQL
 */
function pg_order_base_sql_build($expr, $base, $ready, $table = 'orders')
{
    if (!$ready) {
        return (string) $expr;
    }

    $table = (string) preg_replace('/[^a-z0-9_]/i', '', (string) $table);
    $base = pg_order_currency_code($base);
    $codes = ($base === '') ? "''" : "'', '" . $base . "'";

    return "(CASE WHEN " . $table . ".charged_currency IN (" . $codes . ") THEN (" . $expr . ")"
        . " WHEN " . $table . ".charged_rate > 0 THEN ROUND((" . $expr . ") * " . $table . ".charged_rate)"
        . " ELSE 0 END)";
}

/**
 * An expression over one order's amounts in base minor units, for a sum:
 * SUM(pg_order_base_sql('orders.total')).
 *
 * @param string $expr
 * @param string $table  the orders table's name or alias in the query
 * @return string  SQL
 */
function pg_order_base_sql($expr, $table = 'orders')
{
    return pg_order_base_sql_build($expr, defined('BASE_CURRENCY_CODE') ? BASE_CURRENCY_CODE : '', pg_order_charged_ready(), $table);
}

/**
 * The symbol and suffix an amount in a currency is written with: the base
 * symbol for the base currency, the currency's own symbol when the store
 * lists one, else no symbol and the code after the figure. Pure.
 *
 * @param string $code         the amount's currency
 * @param string $base         BASE_CURRENCY_CODE
 * @param string $base_symbol  BASE_CURRENCY_SYMBOL
 * @param array  $symbols      code => symbol from currencies
 * @return array  array('symbol' => string, 'suffix' => string)
 */
function pg_order_money_parts($code, $base, $base_symbol, $symbols)
{
    $code = pg_order_currency_code($code);

    if (($code === '') || ($code === pg_order_currency_code($base))) {
        return array('symbol' => (string) $base_symbol, 'suffix' => '');
    }

    if (isset($symbols[$code]) && (trim((string) $symbols[$code]) !== '')) {
        return array('symbol' => (string) $symbols[$code], 'suffix' => '');
    }

    return array('symbol' => '', 'suffix' => ' ' . $code);
}

/**
 * One of an order's amounts as a screen prints it, in the currency the
 * order is kept in, never converted: the symbol, the figure, the code after
 * it when the store lists no symbol. Plain text the caller escapes.
 *
 * @param array $order   charged_currency
 * @param int   $amount  minor units of the order's currency
 * @return string
 */
function pg_order_money($order, $amount)
{
    static $symbols = null;

    $base = defined('BASE_CURRENCY_CODE') ? (string) BASE_CURRENCY_CODE : '';
    $code = pg_order_amount_currency($order, $base);

    if (($symbols === null) && ($code !== pg_order_currency_code($base))) {
        $symbols = array();

        foreach ((array) db_items("SELECT code, symbol FROM currencies") as $row) {
            $symbols[pg_order_currency_code($row['code'])] = (string) $row['symbol'];
        }
    }

    $parts = pg_order_money_parts($code, $base, defined('BASE_CURRENCY_SYMBOL') ? (string) BASE_CURRENCY_SYMBOL : '', (array) $symbols);

    return pg_money_text(((int) $amount) / 100, $parts['symbol'], $parts['suffix']);
}

/**
 * The symbol and suffix a panel screen writes an order's amounts with, ready
 * for HTML: pg_format_money($major, $money['symbol'], $money['suffix']). The
 * base symbol as stored (an entity prints as its glyph) for an order in the
 * base currency, the order's currency symbol, else its code after the figure.
 * Read once a request.
 *
 * @param array $order  charged_currency
 * @return array  array('symbol' => string, 'suffix' => string)
 */
function pg_order_money_symbol($order)
{
    static $symbols = null;

    $base = defined('BASE_CURRENCY_CODE') ? (string) BASE_CURRENCY_CODE : '';
    $code = pg_order_amount_currency($order, $base);

    if (($symbols === null) && ($code !== pg_order_currency_code($base))) {
        $symbols = array();

        foreach ((array) db_items("SELECT code, symbol FROM currencies") as $row) {
            $symbols[pg_order_currency_code($row['code'])] = (string) $row['symbol'];
        }
    }

    $parts = pg_order_money_parts($code, $base, defined('BASE_CURRENCY_SYMBOL') ? (string) BASE_CURRENCY_SYMBOL : '', (array) $symbols);

    return array('symbol' => $parts['symbol'], 'suffix' => h($parts['suffix']));
}

/**
 * How the site's order screens print while an order kept in another currency
 * than the base is drawn: its amounts as they are (rate 1), its currency's
 * symbol and its code after the figure. null for an order in the base
 * currency, which keeps the visitor's display currency as before. Pure.
 *
 * @param array  $order    charged_currency
 * @param string $base     BASE_CURRENCY_CODE
 * @param array  $symbols  code => symbol from currencies
 * @return array|null  code, symbol, suffix, rate
 */
function pg_order_display_for($order, $base, $symbols)
{
    $code = pg_order_currency_code(isset($order['charged_currency']) ? $order['charged_currency'] : '');

    if (($code === '') || ($code === pg_order_currency_code($base))) {
        return null;
    }

    return array(
        'code'   => $code,
        'symbol' => isset($symbols[$code]) ? trim((string) $symbols[$code]) : '',
        'suffix' => ' ' . $code,
        'rate'   => 1.0,
    );
}

/**
 * The display in force (pg_order_display_for()), null when none. $set
 * replaces it: false clears it, an array sets it.
 *
 * @param array|false|null $set
 * @return array|null
 */
function pg_order_display($set = null)
{
    static $display = null;

    if ($set === false) {
        $display = null;
    } elseif (is_array($set)) {
        $display = $set;
    }

    return $display;
}

/**
 * Starts drawing an order on the site: until pg_order_display_end(), the
 * site's money functions (prepare_price_for_output(), pg_visitor_amount(),
 * pg_visitor_money()) print that order's amounts in its own currency when it
 * is kept in another than the base. Reads the order's currency by id.
 *
 * @param int $order_id
 * @return array|null  the display before, for pg_order_display_end()
 */
function pg_order_display_begin($order_id)
{
    $before = pg_order_display();

    if (!pg_order_charged_ready() || ((int) $order_id < 1)) {
        return $before;
    }

    $market = pg_schema_has('orders', 'market_id');
    $row = db_item("SELECT charged_currency" . ($market ? ", market_id" : "") . " FROM orders WHERE id = '" . (int) $order_id . "' LIMIT 1");
    $code = pg_order_currency_code(isset($row['charged_currency']) ? $row['charged_currency'] : '');
    $base = defined('BASE_CURRENCY_CODE') ? (string) BASE_CURRENCY_CODE : '';

    if (($code === '') || ($code === pg_order_currency_code($base))) {
        // A market's order in the base currency is priced and rounded
        // already: printed as it is, never rounded again by the market.
        if (!empty($row['market_id'])) {
            pg_order_display(array(
                'code'   => pg_order_currency_code($base),
                'symbol' => defined('BASE_CURRENCY_SYMBOL') ? (string) BASE_CURRENCY_SYMBOL : '',
                'suffix' => '',
                'rate'   => 1.0,
            ));
        }

        return $before;
    }

    $symbol = (string) db_value("SELECT symbol FROM currencies WHERE UPPER(code) = '" . e($code) . "' ORDER BY id LIMIT 1");
    $display = pg_order_display_for(array('charged_currency' => $code), $base, array($code => $symbol));
    pg_order_display(($display === null) ? false : $display);

    return $before;
}

/**
 * Ends what pg_order_display_begin() started, back to the display before.
 *
 * @param array|null $before
 * @return void
 */
function pg_order_display_end($before)
{
    pg_order_display(is_array($before) ? $before : false);
}

/**
 * Whether a site's order screen converts its amounts into the visitor's
 * currency, and so adds the base total charged beside them: never while an
 * order kept in its own currency is drawn.
 *
 * @return bool
 */
function pg_order_display_converts()
{
    return (pg_order_display() === null)
        && defined('VISITOR_CURRENCY_CODE') && defined('BASE_CURRENCY_CODE')
        && (VISITOR_CURRENCY_CODE != BASE_CURRENCY_CODE);
}

/**
 * Whether the fill gives an order read from the table its charged currency:
 * a marketplace order still without one whose currency code is another
 * currency than the base. Pure.
 *
 * @param array  $row   type, currency_code, charged_currency
 * @param string $base  BASE_CURRENCY_CODE
 * @return bool
 */
function pg_order_currency_fill_needs($row, $base)
{
    if (!isset($row['type']) || ((string) $row['type'] !== 'marketplace')) {
        return false;
    }

    if (pg_order_currency_code(isset($row['charged_currency']) ? $row['charged_currency'] : '') !== '') {
        return false;
    }

    $code = pg_order_currency_code(isset($row['currency_code']) ? $row['currency_code'] : '');

    return ($code !== '') && ($code !== pg_order_currency_code($base));
}

/**
 * The fill's place as kept in config_text: the last order id read, and
 * whether it is through. Pure.
 *
 * @param string $text  JSON, '' for a fill not started
 * @return array  after, done
 */
function pg_order_currency_fill_cursor($text)
{
    $cursor = json_decode((string) $text, true);
    $cursor = is_array($cursor) ? $cursor : array();

    return array(
        'after' => max(0, isset($cursor['after']) ? (int) $cursor['after'] : 0),
        'done'  => !empty($cursor['done']),
    );
}

/**
 * The fill's place after a batch: on past the last id while the batch was
 * full, through when it was shorter. Pure.
 *
 * @param array $cursor   pg_order_currency_fill_cursor()
 * @param int   $last_id  the last id the batch read
 * @param int   $read     how many rows it read
 * @param int   $batch    how many it asked for
 * @return array  after, done
 */
function pg_order_currency_fill_next($cursor, $last_id, $read, $batch)
{
    return array(
        'after' => max((int) $cursor['after'], (int) $last_id),
        'done'  => ((int) $read < (int) $batch),
    );
}

/**
 * Gives the marketplace orders written before the charged currency columns
 * their currency and the rate of their day (2026.11.0, 11.0.283), from the
 * general job, for at most $seconds a run: the orders read by id a batch at
 * a time, the place kept in config_text after every batch, so the next run
 * goes on where this one stopped. Once through, the marker says so and a
 * run reads nothing more. An order that has a charged currency is never
 * written again, so a run twice changes nothing. Waits for the columns.
 *
 * @param float $seconds
 * @return array  the cursor as left (pg_order_currency_fill_cursor())
 */
function pg_order_currency_fill_run($seconds = PG_ORDER_CURRENCY_FILL_SECONDS)
{
    $cursor = pg_order_currency_fill_cursor(pg_config_text('order_currency_fill'));

    if ($cursor['done'] || !pg_schema_has('config_text') || !pg_order_charged_ready()) {
        return $cursor;
    }

    $base = defined('BASE_CURRENCY_CODE') ? (string) BASE_CURRENCY_CODE : '';
    $started = microtime(true);

    while (!$cursor['done'] && ((microtime(true) - $started) < (float) $seconds)) {
        // A failed read stops the run without moving the place: an empty
        // answer would otherwise end the fill.
        $result = db_q("SELECT id, type, order_date, currency_code, charged_currency FROM orders
            WHERE id > ? ORDER BY id LIMIT " . (int) PG_ORDER_CURRENCY_FILL_BATCH, array((int) $cursor['after']));

        if (!($result instanceof mysqli_result)) {
            break;
        }

        $rows = array();

        while ($row = mysqli_fetch_assoc($result)) {
            $rows[] = $row;
        }

        $last = (int) $cursor['after'];

        foreach ($rows as $row) {
            $last = (int) $row['id'];

            if (!pg_order_currency_fill_needs($row, $base)) {
                continue;
            }

            $pair = pg_order_charged_for($row['currency_code'], (int) $row['order_date']);

            if (($pair === null) || ($pair['currency'] === '')) {
                continue;
            }

            db("UPDATE orders SET
                    charged_currency = '" . e($pair['currency']) . "',
                    charged_rate = '" . e($pair['rate']) . "'
                WHERE id = '" . (int) $row['id'] . "' AND charged_currency = ''");
        }

        $cursor = pg_order_currency_fill_next($cursor, $last, count($rows), PG_ORDER_CURRENCY_FILL_BATCH);

        pg_config_text_save('order_currency_fill', (string) json_encode($cursor['done'] ? array('done' => 1) : $cursor));
    }

    return $cursor;
}
