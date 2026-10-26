<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * ERP - e-Fatura threshold warning. A seller in Turkey outside e-Fatura must
 * join it from the first day of the seventh month of the year after its gross
 * sales reached a threshold: a lower one for sales over the internet and a
 * general one for every sale. This file weighs the calendar year's sales
 * against both thresholds; the amounts are kept in minor units (kurus).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_ERP_ENTRY')) {
    exit;
}

/**
 * Share of a threshold, in percent, at which the warning starts.
 */
const ERP_EFATURA_THRESHOLD_NEAR = 80;

/**
 * Weighs a year's gross sales against the thresholds.
 *
 * A threshold of 0 or less is switched off and gets no row. The threshold
 * amount itself counts as reached ("or more"). A negative sum (returns larger
 * than sales) is read as nothing sold.
 *
 * @param array      $sales   'web' (sales over the internet) and 'all' (every
 *                            sale), minor units
 * @param array      $limits  'web' and 'general' thresholds, minor units
 * @param int|string $year    the calendar year the sales belong to
 *
 * @return array  one row per threshold switched on, internet first: key
 *                ('web'|'general'), limit, sales, share (whole percent, cut
 *                down so that 79.9 does not read as 80), state
 *                ('reached'|'near'|'below'), deadline (Y-m-d the move falls
 *                due if the threshold is reached this year)
 */
function erp_efatura_threshold_rows($sales, $limits, $year)
{
    $rows = array();
    $deadline = sprintf('%04d-07-01', ((int) $year) + 1);

    foreach (array('web' => 'web', 'general' => 'all') as $key => $sales_key) {

        $limit = (int) ($limits[$key] ?? 0);

        if ($limit <= 0) {
            continue;
        }

        $amount = max(0, (int) ($sales[$sales_key] ?? 0));

        // Whole minor units on both sides: 3 Milyon TL is 3e8 kurus, so the
        // products stay far inside a 64-bit integer.
        if ($amount >= $limit) {
            $state = 'reached';
        } elseif (($amount * 100) >= ($limit * ERP_EFATURA_THRESHOLD_NEAR)) {
            $state = 'near';
        } else {
            $state = 'below';
        }

        $rows[] = array(
            'key'      => $key,
            'limit'    => $limit,
            'sales'    => $amount,
            'share'    => intdiv($amount * 100, $limit),
            'state'    => $state,
            'deadline' => $deadline,
        );
    }

    return $rows;
}

/**
 * The most urgent state among the rows: 'reached', 'near', 'below', or ''
 * when no threshold is switched on.
 *
 * @param array $rows  erp_efatura_threshold_rows()
 *
 * @return string
 */
function erp_efatura_threshold_worst($rows)
{
    $order = array('' => 0, 'below' => 1, 'near' => 2, 'reached' => 3);
    $worst = '';

    foreach ($rows as $row) {
        $state = (string) ($row['state'] ?? '');

        if (isset($order[$state]) && ($order[$state] > $order[$worst])) {
            $worst = $state;
        }
    }

    return $worst;
}

/**
 * Whether the upgrade added the two threshold columns (11.0.61).
 *
 * @return bool
 */
function erp_efatura_threshold_ready()
{
    return waf_table_has_column('config', 'erp_efatura_web_threshold')
        && waf_table_has_column('config', 'erp_efatura_general_threshold');
}

/**
 * The thresholds the store keeps, minor units; 0 is off.
 *
 * @return array  web, general
 */
function erp_efatura_threshold_limits()
{
    return array(
        'web'     => defined('ERP_EFATURA_WEB_THRESHOLD') ? max(0, (int) ERP_EFATURA_WEB_THRESHOLD) : 0,
        'general' => defined('ERP_EFATURA_GENERAL_THRESHOLD') ? max(0, (int) ERP_EFATURA_GENERAL_THRESHOLD) : 0,
    );
}

/**
 * Saves the thresholds, typed in whole units of the base currency.
 *
 * @param string $web
 * @param string $general
 *
 * @return array  success (bool), field, error
 */
function erp_efatura_threshold_save($web, $general)
{
    $values = array();

    foreach (array('erp_efatura_web_threshold' => $web, 'erp_efatura_general_threshold' => $general) as $field => $value) {

        $value = trim((string) $value);

        if ($value === '') {
            $value = '0';
        }

        // Whole units only: a separator typed for thousands would otherwise
        // turn 500.000 into 500.
        if (!preg_match('/^[0-9]{1,10}$/', $value) || ((int) $value > 1000000000)) {
            return array('success' => false, 'field' => $field, 'error' => lang('Enter a whole amount without separators, from 0 to 1000000000.'));
        }

        $values[$field] = ((int) $value) * 100;
    }

    $ok = db("UPDATE config SET
        erp_efatura_web_threshold = '" . (int) $values['erp_efatura_web_threshold'] . "',
        erp_efatura_general_threshold = '" . (int) $values['erp_efatura_general_threshold'] . "'");

    if ($ok === false) {
        return array('success' => false, 'field' => 'erp_efatura_web_threshold', 'error' => lang('The settings could not be saved.'));
    }

    // The status reads the thresholds from the next request on; the sums it
    // weighs them against do not change.
    erp_efatura_threshold_forget_status();

    return array('success' => true, 'field' => '', 'error' => '');
}

/**
 * Drops the System Status cache so the tile shows the new thresholds now
 * rather than in up to ten minutes.
 */
function erp_efatura_threshold_forget_status()
{
    $file = PG_FUNCTIONS_DIR . '/data/temp/system_status_cache.json';

    if (is_file($file)) {
        @unlink($file);
    }
}

/**
 * Gross sales of a calendar year, from the orders: complete and exported
 * ones (erp_order_billable_statuses()), in the base currency the order totals
 * are kept in, the whole sale (total and the part a gift card paid). Sales
 * over the internet leave counter sales out and keep marketplace orders;
 * every sale keeps them all. Invoices written in the ERP without an order are
 * not sales of the shop and are not counted.
 *
 * @param int $year
 *
 * @return array|false  web, all (minor units); false when the read failed
 */
function erp_efatura_threshold_sales($year)
{
    $year = (int) $year;
    $from = mktime(0, 0, 0, 1, 1, $year);
    $to = mktime(0, 0, 0, 1, 1, $year + 1);

    // gift_card_discount is UNSIGNED: added to a signed total as it is, a
    // negative total makes the sum an out-of-range error under strict SQL.
    $sale = "(total + CAST(gift_card_discount AS SIGNED))";

    // A site whose orders have no type yet sells only on its own site.
    $web = pg_schema_has('orders', 'type') ? "CASE WHEN type <> 'local' THEN " . $sale . " ELSE 0 END" : $sale;

    $row = db_item("SELECT
            COALESCE(SUM(" . $web . "), 0) AS web,
            COALESCE(SUM(" . $sale . "), 0) AS sales
        FROM orders
        WHERE status IN ('" . implode("', '", array_map('escape', erp_order_billable_statuses())) . "')
            AND order_date >= '" . (int) $from . "'
            AND order_date < '" . (int) $to . "'");

    if (!is_array($row)) {
        return false;
    }

    return array('web' => (int) $row['web'], 'all' => (int) $row['sales']);
}

/**
 * The year so far weighed against the thresholds. The sums are kept for a day
 * in data/temp (a cache the cleanup may delete): they move slowly and the
 * warning is about a deadline half a year away, so one read a day is enough.
 *
 * @param bool $fresh  read the orders again whatever the cache holds
 *
 * @return array  year, at (when the sums were read), sales, rows, worst
 */
function erp_efatura_threshold_report($fresh = false)
{
    $year = (int) date('Y');
    $file = PG_FUNCTIONS_DIR . '/data/temp/erp_efatura_threshold.json';
    $sales = null;
    $at = 0;

    if (!$fresh && is_file($file)) {
        $cached = json_decode((string) @file_get_contents($file), true);

        if (is_array($cached) && ((int) ($cached['year'] ?? 0) === $year)
            && ((time() - (int) ($cached['at'] ?? 0)) < 86400) && is_array($cached['sales'] ?? null)) {
            $sales = $cached['sales'];
            $at = (int) $cached['at'];
        }
    }

    if ($sales === null) {
        $sales = erp_efatura_threshold_sales($year);

        if ($sales === false) {
            $sales = array('web' => 0, 'all' => 0);
        } else {
            $at = time();
            @file_put_contents($file, json_encode(array('year' => $year, 'at' => $at, 'sales' => $sales)), LOCK_EX);
        }
    }

    $rows = erp_efatura_threshold_rows($sales, erp_efatura_threshold_limits(), $year);

    return array(
        'year'  => $year,
        'at'    => $at,
        'sales' => $sales,
        'rows'  => $rows,
        'worst' => erp_efatura_threshold_worst($rows),
    );
}
