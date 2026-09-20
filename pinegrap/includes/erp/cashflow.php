<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * ERP - the cash flow report.
 *
 * What came in and what went out of the tills over a period, in the base
 * currency, bucketed by day, week or month, with the running position; then
 * the same period till by till, and split by how the money moved (cash,
 * transfer, card). Read straight from erp_cash_transactions - the cached till
 * balances are the position today, and a report is about a period.
 *
 * Across all tills a transfer between two of the store's own tills is not a
 * flow: the money went from one drawer to another. Those rows are left out of
 * the all-tills figures and shown per till, where they are real.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_ERP_ENTRY')) {
    exit;
}


if (!defined('ERP_CASHFLOW_MAX_DAYS')) {
    // The longest period one report covers; longer ranges are trimmed from
    // the start. Three years of days is 1100 rows, which is already more than
    // a table reads well.
    define('ERP_CASHFLOW_MAX_DAYS', 1100);
}

/**
 * The bucket sizes the report offers.
 *
 * @return array  key => label
 */
function erp_cashflow_groups()
{
    return array(
        'day' => lang('Daily'),
        'week' => lang('Weekly'),
        'month' => lang('Monthly'),
    );
}

/**
 * Normalise what the address asked for.
 *
 * Dates are Y-m-d; the period ends today at the latest (cash flow is what
 * happened, not a forecast) and defaults to this month so far. A group that
 * is not named is picked from the length of the period: days for up to two
 * months, weeks for up to about a year, months beyond. The till is an id or
 * 0 for all of them.
 *
 * @param array $input  from, to, group, till (all optional)
 * @return array  ['from', 'to', 'group', 'till', 'group_chosen' => bool]
 */
function erp_cashflow_options($input)
{
    $input = (array) $input;
    $today = date('Y-m-d');

    $valid = function ($value) {
        $value = trim((string) $value);

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return '';
        }

        $parts = explode('-', $value);

        return checkdate((int) $parts[1], (int) $parts[2], (int) $parts[0]) ? $value : '';
    };

    $to = $valid($input['to'] ?? '');
    if (($to === '') || ($to > $today)) {
        $to = $today;
    }

    $from = $valid($input['from'] ?? '');
    if (($from === '') || ($from > $to)) {
        $from = substr($to, 0, 7) . '-01';
    }

    $days = (int) ((strtotime($to) - strtotime($from)) / 86400) + 1;

    if ($days > ERP_CASHFLOW_MAX_DAYS) {
        $from = date('Y-m-d', strtotime($to . ' -' . (ERP_CASHFLOW_MAX_DAYS - 1) . ' days'));
        $days = ERP_CASHFLOW_MAX_DAYS;
    }

    $group = (string) ($input['group'] ?? '');
    $group_chosen = isset(erp_cashflow_groups()[$group]);

    if (!$group_chosen) {
        $group = ($days <= 62) ? 'day' : (($days <= 400) ? 'week' : 'month');
    }

    $till = (int) ($input['till'] ?? 0);
    if (($till > 0) && ((int) db_value("SELECT COUNT(*) FROM erp_cash_accounts WHERE id = '" . $till . "'") === 0)) {
        $till = 0;
    }

    return array('from' => $from, 'to' => $to, 'group' => $group, 'till' => $till, 'group_chosen' => $group_chosen, 'days' => $days);
}

/**
 * The tills, active first, with the flag that says whether their figures can
 * be added into a base-currency total.
 *
 * @return array  Rows of erp_cash_accounts plus 'in_base' => bool
 */
function erp_cashflow_tills()
{
    $tills = (array) db_items("SELECT * FROM erp_cash_accounts ORDER BY is_active DESC, sort_order ASC, name ASC");
    $base = erp_base_currency();
    $fx = erp_fx_enabled();

    foreach ($tills as $index => $till) {
        $currency = strtoupper(trim((string) $till['currency']));
        $tills[$index]['in_base'] = (!$fx || ($currency === $base));
    }

    return $tills;
}

/**
 * The SQL that restricts a query to the report's tills and, across all tills,
 * leaves the transfers out.
 *
 * @param array  $options
 * @param string $alias
 * @return string  Starts with AND
 */
function erp_cashflow_scope_sql($options, $alias = 'c')
{
    if ((int) $options['till'] > 0) {
        return " AND " . $alias . ".cash_account_id = '" . (int) $options['till'] . "'";
    }

    return " AND " . $alias . ".doc_type <> 'transfer'";
}

/**
 * The position at the start of the period, in base kurus.
 *
 * A till's opening figure is a column, not a movement, and is in the till's
 * own currency; so it is counted only for tills kept in the base currency,
 * the way the Cash and Bank total is. Movements before the period are
 * counted at their booked base value whatever the till.
 *
 * @param array $options
 * @return array ['opening' => int, 'foreign_tills' => int]
 */
function erp_cashflow_opening($options)
{
    $opening = 0;
    $foreign = 0;

    foreach (erp_cashflow_tills() as $till) {
        if (((int) $options['till'] > 0) && ((int) $till['id'] !== (int) $options['till'])) {
            continue;
        }
        if ($till['in_base']) {
            $opening += (int) $till['opening_balance'];
        } else {
            $foreign++;
        }
    }

    // Before the period, transfers net to nothing across all tills and are
    // real for one till; the scope clause handles both.
    $opening += (int) db_value("SELECT COALESCE(SUM(CASE WHEN c.direction = 'in' THEN c.amount_base ELSE -c.amount_base END), 0)
        FROM erp_cash_transactions c
        WHERE c.doc_date < '" . escape($options['from']) . "'" . erp_cashflow_scope_sql($options));

    return array('opening' => $opening, 'foreign_tills' => $foreign);
}

/**
 * The bucket a date falls in, as the key the SQL side produces too.
 *
 * @param string $date  Y-m-d
 * @param string $group
 * @return string
 */
function erp_cashflow_bucket_key($date, $group)
{
    $time = strtotime($date);

    switch ($group) {
        case 'week':
            // ISO week, Monday first; YEARWEEK(date, 3) on the SQL side.
            return date('oW', $time);
        case 'month':
            return date('Y-m', $time);
    }

    return date('Y-m-d', $time);
}

/**
 * The bucket's caption and its first and last day, clipped to the period.
 *
 * @param string $key
 * @param string $group
 * @param array  $options
 * @return array ['label' => string, 'start' => Y-m-d, 'end' => Y-m-d]
 */
function erp_cashflow_bucket_span($key, $group, $options)
{
    switch ($group) {
        case 'week':
            $start = date('Y-m-d', strtotime(substr($key, 0, 4) . 'W' . substr($key, 4, 2) . '1'));
            $end = date('Y-m-d', strtotime($start . ' +6 days'));
            break;
        case 'month':
            $start = $key . '-01';
            $end = date('Y-m-t', strtotime($start));
            break;
        default:
            $start = $key;
            $end = $key;
    }

    $start_clipped = max($start, $options['from']);
    $end_clipped = min($end, $options['to']);

    switch ($group) {
        case 'week':
            $label = prepare_form_data_for_output($start_clipped, 'date', false) . ' – ' . prepare_form_data_for_output($end_clipped, 'date', false);
            break;
        case 'month':
            $label = substr($key, 5, 2) . '/' . substr($key, 0, 4);
            break;
        default:
            $label = prepare_form_data_for_output($key, 'date', false);
    }

    return array('label' => $label, 'start' => $start_clipped, 'end' => $end_clipped);
}

/**
 * The report: every bucket in the period, empty ones included, with the
 * money in, the money out, the net and the running position.
 *
 * @param array $options  From erp_cashflow_options()
 * @return array  opening, closing, in, out, net, count, foreign_tills,
 *                rows => [[key, label, start, end, in, out, net, count, balance], ...]
 */
function erp_cashflow_report($options)
{
    $group = $options['group'];

    switch ($group) {
        case 'week':
            $key_sql = "YEARWEEK(c.doc_date, 3)";
            break;
        case 'month':
            $key_sql = "DATE_FORMAT(c.doc_date, '%Y-%m')";
            break;
        default:
            $key_sql = "c.doc_date";
    }

    $sums = (array) db_items("SELECT " . $key_sql . " AS bucket, c.direction, SUM(c.amount_base) AS total, COUNT(*) AS movements
        FROM erp_cash_transactions c
        WHERE c.doc_date BETWEEN '" . escape($options['from']) . "' AND '" . escape($options['to']) . "'" . erp_cashflow_scope_sql($options) . "
        GROUP BY bucket, c.direction");

    $by_key = array();
    foreach ($sums as $sum) {
        $key = (string) $sum['bucket'];
        if (!isset($by_key[$key])) {
            $by_key[$key] = array('in' => 0, 'out' => 0, 'count' => 0);
        }
        $by_key[$key][((string) $sum['direction'] === 'in') ? 'in' : 'out'] += (int) $sum['total'];
        $by_key[$key]['count'] += (int) $sum['movements'];
    }

    // Walk the period so a quiet week still has its row: a gap in a cash
    // flow is information.
    $keys = array();
    for ($time = strtotime($options['from']); $time <= strtotime($options['to']); $time = strtotime('+1 day', $time)) {
        $keys[erp_cashflow_bucket_key(date('Y-m-d', $time), $group)] = true;
    }

    $opening = erp_cashflow_opening($options);
    $running = $opening['opening'];
    $rows = array();
    $total_in = 0;
    $total_out = 0;
    $count = 0;

    foreach (array_keys($keys) as $key) {
        $in = isset($by_key[$key]) ? (int) $by_key[$key]['in'] : 0;
        $out = isset($by_key[$key]) ? (int) $by_key[$key]['out'] : 0;
        $movements = isset($by_key[$key]) ? (int) $by_key[$key]['count'] : 0;
        $running += $in - $out;
        $total_in += $in;
        $total_out += $out;
        $count += $movements;

        $span = erp_cashflow_bucket_span($key, $group, $options);

        $rows[] = array(
            'key' => $key,
            'label' => $span['label'],
            'start' => $span['start'],
            'end' => $span['end'],
            'in' => $in,
            'out' => $out,
            'net' => $in - $out,
            'count' => $movements,
            'balance' => $running,
        );
    }

    return array(
        'opening' => $opening['opening'],
        'closing' => $running,
        'in' => $total_in,
        'out' => $total_out,
        'net' => $total_in - $total_out,
        'count' => $count,
        'foreign_tills' => $opening['foreign_tills'],
        'rows' => $rows,
    );
}

/**
 * The period till by till: opening, in, out, closing - transfers included,
 * because for one till a transfer is money that arrived or left.
 *
 * @param array $options
 * @return array  Rows: id, name, kind, currency, is_active, in_base, opening, in, out, net, closing, count
 */
function erp_cashflow_by_till($options)
{
    $before = (array) db_items("SELECT cash_account_id, COALESCE(SUM(CASE WHEN direction = 'in' THEN amount_base ELSE -amount_base END), 0) AS total
        FROM erp_cash_transactions
        WHERE doc_date < '" . escape($options['from']) . "'
        GROUP BY cash_account_id");
    $before_by_till = array();
    foreach ($before as $row) {
        $before_by_till[(int) $row['cash_account_id']] = (int) $row['total'];
    }

    $period = (array) db_items("SELECT cash_account_id, direction, SUM(amount_base) AS total, COUNT(*) AS movements
        FROM erp_cash_transactions
        WHERE doc_date BETWEEN '" . escape($options['from']) . "' AND '" . escape($options['to']) . "'
        GROUP BY cash_account_id, direction");
    $period_by_till = array();
    foreach ($period as $row) {
        $id = (int) $row['cash_account_id'];
        if (!isset($period_by_till[$id])) {
            $period_by_till[$id] = array('in' => 0, 'out' => 0, 'count' => 0);
        }
        $period_by_till[$id][((string) $row['direction'] === 'in') ? 'in' : 'out'] += (int) $row['total'];
        $period_by_till[$id]['count'] += (int) $row['movements'];
    }

    $rows = array();

    foreach (erp_cashflow_tills() as $till) {
        $id = (int) $till['id'];

        if (((int) $options['till'] > 0) && ($id !== (int) $options['till'])) {
            continue;
        }

        $in = isset($period_by_till[$id]) ? (int) $period_by_till[$id]['in'] : 0;
        $out = isset($period_by_till[$id]) ? (int) $period_by_till[$id]['out'] : 0;
        $movements = isset($period_by_till[$id]) ? (int) $period_by_till[$id]['count'] : 0;

        // A passive till with nothing in the period is noise.
        if (((int) $till['is_active'] !== 1) && ($movements === 0)) {
            continue;
        }

        // The opening column is in the till's currency; for a till kept in
        // another currency the base position starts from its movements alone.
        $opening = ($till['in_base'] ? (int) $till['opening_balance'] : 0) + ($before_by_till[$id] ?? 0);

        $rows[] = array(
            'id' => $id,
            'name' => (string) $till['name'],
            'kind' => (string) $till['kind'],
            'currency' => strtoupper(trim((string) $till['currency'])),
            'is_active' => ((int) $till['is_active'] === 1),
            'in_base' => (bool) $till['in_base'],
            'opening' => $opening,
            'in' => $in,
            'out' => $out,
            'net' => $in - $out,
            'closing' => $opening + $in - $out,
            'count' => $movements,
        );
    }

    return $rows;
}

/**
 * The period split by how the money moved.
 *
 * @param array $options
 * @return array  method => ['label' => string, 'in' => int, 'out' => int, 'count' => int]
 */
function erp_cashflow_by_method($options)
{
    // The receipt screen's labels, in the order the form offers the methods.
    $labels = array(
        'cash' => lang('Cash'),
        'transfer' => lang('Bank transfer'),
        'card' => lang('Card'),
        'cheque' => lang('Cheque'),
        'other' => lang('Other'),
    );

    $result = array();
    foreach ($labels as $method => $label) {
        $result[$method] = array('label' => $label, 'in' => 0, 'out' => 0, 'count' => 0);
    }

    $sums = (array) db_items("SELECT c.payment_method, c.direction, SUM(c.amount_base) AS total, COUNT(*) AS movements
        FROM erp_cash_transactions c
        WHERE c.doc_date BETWEEN '" . escape($options['from']) . "' AND '" . escape($options['to']) . "'" . erp_cashflow_scope_sql($options) . "
        GROUP BY c.payment_method, c.direction");

    foreach ($sums as $sum) {
        $method = isset($result[(string) $sum['payment_method']]) ? (string) $sum['payment_method'] : 'other';
        $result[$method][((string) $sum['direction'] === 'in') ? 'in' : 'out'] += (int) $sum['total'];
        $result[$method]['count'] += (int) $sum['movements'];
    }

    // Only what moved.
    foreach ($result as $method => $row) {
        if ($row['count'] === 0) {
            unset($result[$method]);
        }
    }

    return $result;
}

/**
 * The period table as CSV columns and rows, amounts written the way the
 * invoice export writes them.
 *
 * @param array $report  From erp_cashflow_report()
 * @param array $options
 * @return array ['columns' => [...], 'rows' => [[...], ...]]
 */
function erp_cashflow_csv($report, $options)
{
    $profile = erp_export_profile('csv_invoices');

    $columns = array(lang('Period'), lang('Start'), lang('End'), lang('Money in'), lang('Money out'), lang('Net change'), lang('Movements'), lang('Balance'));
    $rows = array();

    $rows[] = array(lang('Opening Balance'), '', $options['from'], '', '', '', '', erp_export_amount($report['opening'], $profile));

    foreach ($report['rows'] as $row) {
        $rows[] = array(
            $row['label'],
            $row['start'],
            $row['end'],
            erp_export_amount($row['in'], $profile),
            erp_export_amount($row['out'], $profile),
            erp_export_amount($row['net'], $profile),
            (int) $row['count'],
            erp_export_amount($row['balance'], $profile),
        );
    }

    $rows[] = array(lang('Total'), $options['from'], $options['to'], erp_export_amount($report['in'], $profile), erp_export_amount($report['out'], $profile), erp_export_amount($report['net'], $profile), (int) $report['count'], erp_export_amount($report['closing'], $profile));

    return array('columns' => $columns, 'rows' => $rows);
}
