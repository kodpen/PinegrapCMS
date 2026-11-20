<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * A marketplace's real deductions on an order's profit, in the Accounting
 * module.
 *
 * A marketplace connector that can read its finance records answers the
 * 'deductions' capability (Trendyol: its settlements). Each record carries
 * the commission of one line of a package; it becomes one cost row of the
 * order the package was imported as: kind and source marketplace, reference
 * the record's own ("settlement:<id>"), amount in the order's own currency
 * and minor units, a return or a discount as a negative row. A real row of
 * the kind replaces the listing's commission estimate when the profit pass
 * computes the order again (erp_order_profit_mark()).
 *
 * It writes cost rows and nothing else: no receipt, till, bank or account
 * move, no invoice settled, no payout. With the Accounting module off it
 * reads and writes nothing. The read is bounded per run and resumes where it
 * stopped: its state is kept per account in config_text.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_ERP_ENTRY')) {
    exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/erp/money.php');

/** Requests one account may spend on its finance records in one run. */
const ERP_ORDER_PROFIT_DEDUCTION_CALLS = 10;

/** How far back the first read of an account starts: thirty days. */
const ERP_ORDER_PROFIT_DEDUCTION_LOOKBACK = 2592000;

/**
 * The config_text row that keeps where an account's read stands.
 *
 * @param int $account_id
 * @return string
 */
function erp_order_profit_deduction_state_key($account_id)
{
    return 'erp_order_profit_deductions_' . (int) $account_id;
}

/**
 * An account's read state from its config_text value: {from, type, page},
 * or a first read thirty days back when nothing usable is kept.
 *
 * @param string $text
 * @param int    $now
 * @return array
 */
function erp_order_profit_deduction_state($text, $now)
{
    $state = json_decode((string) $text, true);

    if (!is_array($state) || !isset($state['from']) || !is_numeric($state['from'])) {
        return array('from' => (int) $now - ERP_ORDER_PROFIT_DEDUCTION_LOOKBACK, 'type' => 0, 'page' => 0);
    }

    return array('from' => (int) $state['from'], 'type' => (int) ($state['type'] ?? 0), 'page' => (int) ($state['page'] ?? 0));
}

/**
 * An amount in one currency as minor units of another, at the day's rates.
 *
 * $rate_of answers the base units per unit of a currency on a day
 * (erp_fx_rate_for()'s rate), or false. Both sides go through the base, as
 * one factor, so erp_to_base() rounds once with round().
 *
 * @param int      $amount  Minor units of $from
 * @param string   $from
 * @param string   $to
 * @param string   $date     Y-m-d
 * @param callable $rate_of  function ($code, $date): float|false
 * @return int|false  Minor units of $to, or false when a rate is missing
 */
function erp_order_profit_deduction_convert($amount, $from, $to, $date, $rate_of)
{
    $from = strtoupper(trim((string) $from));
    $to = strtoupper(trim((string) $to));

    if (($from === '') || ($to === '')) {
        return false;
    }

    if ($from === $to) {
        return (int) $amount;
    }

    $from_rate = call_user_func($rate_of, $from, $date);
    $to_rate = call_user_func($rate_of, $to, $date);

    if (!is_numeric($from_rate) || !is_numeric($to_rate) || ((float) $from_rate <= 0) || ((float) $to_rate <= 0)) {
        return false;
    }

    return erp_to_base((int) $amount, ((float) $from_rate) / ((float) $to_rate));
}

/**
 * The cost rows of a page of finance records. Reads nothing.
 *
 * A record names its order by package; without a known package by its order
 * number, when only one order of the account carries it. A record of an order
 * that is no finished sale (the pass would remove its rows), of a currency
 * not known, or with no rate to convert it, is skipped and the order keeps
 * its estimate.
 *
 * @param array    $records  The connector's rows: ref, package_id, order_number, amount, currency, date
 * @param array    $orders   ['package' => id => order, 'number' => number => list of orders];
 *                           an order is order_id, currency (the unit of orders.total), status
 * @param callable $rate_of  See erp_order_profit_deduction_convert()
 * @return array ['rows' => list of order_id, ref, amount, 'skipped' => int]
 */
function erp_order_profit_deduction_rows($records, $orders, $rate_of)
{
    $rows = array();
    $skipped = 0;

    foreach ((array) $records as $record) {
        $package = (string) ($record['package_id'] ?? '');
        $number = (string) ($record['order_number'] ?? '');
        $order = null;

        if (($package !== '') && isset($orders['package'][$package])) {
            $order = $orders['package'][$package];
        } elseif (($number !== '') && isset($orders['number'][$number]) && (count($orders['number'][$number]) === 1)) {
            $order = $orders['number'][$number][0];
        }

        if (($order === null) || !in_array((string) $order['status'], erp_order_billable_statuses(), true)) {
            $skipped++;
            continue;
        }

        $amount = erp_order_profit_deduction_convert((int) $record['amount'], (string) $record['currency'],
            (string) $order['currency'], date('Y-m-d', (int) ($record['date'] ?? 0) ?: time()), $rate_of);

        if ($amount === false) {
            $skipped++;
            continue;
        }

        $rows[] = array('order_id' => (int) $order['order_id'], 'ref' => (string) $record['ref'], 'amount' => $amount);
    }

    return array('rows' => $rows, 'skipped' => $skipped);
}

/**
 * The imported orders of an account that the records name, by package and
 * by order number. A marketplace order's currency_code is the unit of its
 * amounts; an empty one is the store's base currency.
 *
 * @param int   $account_id
 * @param array $records
 * @return array  As erp_order_profit_deduction_rows() takes it
 */
function erp_order_profit_deduction_orders($account_id, $records)
{
    $packages = array();
    $numbers = array();

    foreach ((array) $records as $record) {
        if ((string) $record['package_id'] !== '') {
            $packages[(string) $record['package_id']] = "'" . e((string) $record['package_id']) . "'";
        }

        if ((string) $record['order_number'] !== '') {
            $numbers[(string) $record['order_number']] = "'" . e((string) $record['order_number']) . "'";
        }
    }

    $orders = array('package' => array(), 'number' => array());

    if (empty($packages) && empty($numbers)) {
        return $orders;
    }

    $match = array();

    if (!empty($packages)) {
        $match[] = "marketplace_order_map.remote_package_id IN (" . implode(',', $packages) . ")";
    }

    if (!empty($numbers)) {
        $match[] = "marketplace_order_map.remote_order_id IN (" . implode(',', $numbers) . ")";
    }

    $base = function_exists('erp_base_currency') ? erp_base_currency() : (defined('BASE_CURRENCY_CODE') ? (string) BASE_CURRENCY_CODE : '');

    foreach ((array) db_items("SELECT marketplace_order_map.remote_package_id, marketplace_order_map.remote_order_id,
            orders.id, orders.currency_code, orders.status
        FROM marketplace_order_map
        INNER JOIN orders ON orders.id = marketplace_order_map.order_id
        WHERE marketplace_order_map.account_id = '" . (int) $account_id . "'
            AND marketplace_order_map.status = 'imported'
            AND (" . implode(' OR ', $match) . ")") as $row) {
        $currency = strtoupper(trim((string) $row['currency_code']));
        $order = array('order_id' => (int) $row['id'], 'currency' => ($currency !== '') ? $currency : $base, 'status' => (string) $row['status']);

        if ((string) $row['remote_package_id'] !== '') {
            $orders['package'][(string) $row['remote_package_id']] = $order;
        }

        if ((string) $row['remote_order_id'] !== '') {
            $orders['number'][(string) $row['remote_order_id']][] = $order;
        }
    }

    return $orders;
}

/**
 * Write cost rows of real deductions and ask the pass to compute each order
 * again. A row read again keeps its place (UNIQUE order_id, kind,
 * source_ref) and takes the amount read last.
 *
 * @param array $rows  erp_order_profit_deduction_rows()['rows']
 * @return bool
 */
function erp_order_profit_deduction_write($rows)
{
    $now = time();
    $marked = array();

    foreach ((array) $rows as $row) {
        $ok = db_q("INSERT INTO erp_order_costs (order_id, kind, source, source_ref, amount, created_at, updated_at)
            VALUES (?, 'marketplace', 'marketplace', ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE source = VALUES(source), amount = VALUES(amount), updated_at = VALUES(updated_at)",
            array((int) $row['order_id'], mb_substr((string) $row['ref'], 0, 100), (int) $row['amount'], $now, $now));

        if ($ok === false) {
            return false;
        }

        $marked[(int) $row['order_id']] = true;
    }

    foreach (array_keys($marked) as $order_id) {
        erp_order_profit_mark($order_id);
    }

    return true;
}

/**
 * The deductions pass: each active marketplace account whose connector can
 * read its finance records, at most ERP_ORDER_PROFIT_DEDUCTION_CALLS
 * requests each, from where its last run stopped. An account whose read
 * fails keeps its state and is read again next run; the others go on.
 *
 * With the Accounting module off it reads and writes nothing.
 *
 * @param callable      $time_left  Seconds left in this run
 * @param bool|null     $enabled    Whether the Accounting module is on; null reads ERP_ENABLED
 * @param callable|null $transport  For tests; production goes through api_http_request()
 * @param int|null      $now
 * @return array ['reason' => off|done|more|error, 'done' => cost rows written]
 */
function erp_order_profit_deductions_run($time_left, $enabled = null, $transport = null, $now = null)
{
    if ($enabled === null) {
        $enabled = defined('ERP_ENABLED') && ERP_ENABLED;
    }

    if (!$enabled || !erp_order_profit_ready() || !pg_schema_has('marketplace_order_map')) {
        return array('reason' => 'off', 'done' => 0);
    }

    require_once(PG_FUNCTIONS_DIR . '/includes/api/outbound/connectors/base.php');
    require_once(PG_FUNCTIONS_DIR . '/includes/erp/fx.php');

    $now = ($now === null) ? time() : (int) $now;
    $done = 0;
    $reason = 'done';

    $rate_of = function ($code, $date) {
        $rate = erp_fx_rate_for($code, $date);

        return is_array($rate) ? (float) $rate['rate'] : false;
    };

    foreach (mp_accounts('', 'active') as $account) {
        if (!mp_can((string) $account['provider'], 'deductions')) {
            continue;
        }

        if (call_user_func($time_left) <= 0) {
            return array('reason' => 'more', 'done' => $done);
        }

        $key = erp_order_profit_deduction_state_key($account['id']);
        $state = erp_order_profit_deduction_state(pg_config_text($key), $now);

        $result = mp_call((string) $account['provider'], 'deductions',
            array($account, $state, ERP_ORDER_PROFIT_DEDUCTION_CALLS, $transport, $now));

        if (empty($result['ok'])) {
            $reason = 'error';
            continue;
        }

        $records = (array) ($result['data']['records'] ?? array());
        $rows = erp_order_profit_deduction_rows($records, erp_order_profit_deduction_orders((int) $account['id'], $records), $rate_of);

        if (!erp_order_profit_deduction_write($rows['rows'])) {
            return array('reason' => 'error', 'done' => $done);
        }

        $done += count($rows['rows']);

        pg_config_text_save($key, json_encode($result['data']['state']));

        if (empty($result['data']['caught_up']) && ($reason !== 'error')) {
            $reason = 'more';
        }
    }

    return array('reason' => $reason, 'done' => $done);
}
