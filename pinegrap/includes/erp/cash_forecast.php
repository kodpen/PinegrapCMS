<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * ERP - the cash forecast: what the books already say will come in and go
 * out over the next 30, 60 or 90 days, and where the tills would stand.
 *
 * Read from what is open today, never from the ledger's past:
 *
 *   in   open sales invoices on their due dates, the repeating invoices'
 *        next ones on the due date the account's term gives them, and the
 *        customers' cheques and notes still in the portfolio or at the bank
 *        on theirs;
 *   out  open purchase invoices, unpaid expenses, the repeating expenses'
 *        next ones, and the cheques and notes given to suppliers that the
 *        bank has not paid yet.
 *
 * Amounts are base-currency kurus: what is still open at the booked rates
 * (includes/erp/aging.php), a cheque's booked amount, and for a repeating
 * document in another currency today's recorded rate. Something past its
 * date is shown on its own, as if it settled today. The position starts
 * from what the tills hold now in the base currency, less the tills the
 * cheques are kept in: those hold the cheques, which are counted on their
 * own dates instead.
 *
 * Nothing here is written or remembered; the cash flow report beside it is
 * what happened, this is what is on the books to happen.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_ERP_ENTRY')) {
    exit;
}

if (!defined('ERP_CASH_FORECAST_MAX_ITEMS')) {
    // The longest list of expected movements the screen prints under the
    // table; the table itself counts everything.
    define('ERP_CASH_FORECAST_MAX_ITEMS', 300);
}

/**
 * The horizons offered: days => label.
 *
 * @return array
 */
function erp_cash_forecast_horizons()
{
    return array(
        30 => lang('Next 30 days'),
        60 => lang('Next 60 days'),
        90 => lang('Next 90 days'),
    );
}

/**
 * A horizon the screen asked for, or 90.
 *
 * @param mixed $value
 * @return int
 */
function erp_cash_forecast_horizon($value)
{
    $value = (int) $value;

    return array_key_exists($value, erp_cash_forecast_horizons()) ? $value : 90;
}

/**
 * The kinds of expected movement, in the order the table lists them.
 *
 * @return array  kind => ['label' => string, 'direction' => in | out]
 */
function erp_cash_forecast_kinds()
{
    return array(
        'sales_invoice' => array('label' => lang('Open sales invoices'), 'direction' => 'in'),
        'recurring_invoice' => array('label' => lang('Repeating invoices'), 'direction' => 'in'),
        'cheque_received' => array('label' => lang('Cheques and notes received'), 'direction' => 'in'),
        'purchase_invoice' => array('label' => lang('Open purchase invoices'), 'direction' => 'out'),
        'expense' => array('label' => lang('Unpaid expenses'), 'direction' => 'out'),
        'recurring_expense' => array('label' => lang('Repeating expenses'), 'direction' => 'out'),
        'cheque_given' => array('label' => lang('Cheques and notes given'), 'direction' => 'out'),
    );
}

/**
 * The table's columns: past due, then one per thirty days up to the horizon.
 *
 * @param string $today    Y-m-d
 * @param int    $horizon  30 | 60 | 90
 * @return array  key => ['start' => Y-m-d|'', 'end' => Y-m-d]
 */
function erp_cash_forecast_buckets($today, $horizon)
{
    $buckets = array('overdue' => array('start' => '', 'end' => date('Y-m-d', strtotime($today . ' -1 day'))));

    for ($offset = 0; $offset < (int) $horizon; $offset += 30) {
        $buckets['d' . ($offset + 30)] = array(
            'start' => date('Y-m-d', strtotime($today . ' +' . $offset . ' days')),
            'end' => date('Y-m-d', strtotime($today . ' +' . ($offset + 29) . ' days')),
        );
    }

    return $buckets;
}

/**
 * Fold the expected movements into the table.
 *
 * Pure. An item dated before today is past due; one past the horizon's last
 * day is left out; one of a kind not in erp_cash_forecast_kinds() is left
 * out. The position after a column is the opening cash plus every net
 * before it and its own, past due first.
 *
 * @param array  $items    [['kind' => string, 'date' => Y-m-d, 'amount' => kurus (positive)], ...]
 * @param string $today    Y-m-d
 * @param int    $horizon  30 | 60 | 90
 * @param int    $cash     What the tills hold today, kurus
 * @return array  buckets (from erp_cash_forecast_buckets()), kinds => [kind => [bucket => kurus, 'total' => kurus]],
 *                in, out, net, position => [bucket => kurus, 'total' => kurus], cash, count, items (kept, by date)
 */
function erp_cash_forecast_build($items, $today, $horizon, $cash)
{
    $buckets = erp_cash_forecast_buckets($today, $horizon);
    $kinds = erp_cash_forecast_kinds();
    $zero = array_fill_keys(array_merge(array_keys($buckets), array('total')), 0);
    $last = end($buckets)['end'];

    $result = array(
        'buckets' => $buckets,
        'kinds' => array_fill_keys(array_keys($kinds), $zero),
        'in' => $zero,
        'out' => $zero,
        'net' => $zero,
        'position' => $zero,
        'cash' => (int) $cash,
        'count' => 0,
        'items' => array(),
    );

    foreach ((array) $items as $item) {
        $kind = (string) ($item['kind'] ?? '');
        $date = (string) ($item['date'] ?? '');
        $amount = (int) ($item['amount'] ?? 0);

        if (!isset($kinds[$kind]) || ($amount <= 0) || ($date > $last)) {
            continue;
        }

        $key = 'overdue';
        foreach ($buckets as $bucket_key => $bucket) {
            if (($bucket['start'] !== '') && ($date >= $bucket['start']) && ($date <= $bucket['end'])) {
                $key = $bucket_key;
                break;
            }
        }

        $side = $kinds[$kind]['direction'];

        $result['kinds'][$kind][$key] += $amount;
        $result['kinds'][$kind]['total'] += $amount;
        $result[$side][$key] += $amount;
        $result[$side]['total'] += $amount;
        $result['count']++;

        $item['bucket'] = $key;
        $item['direction'] = $side;
        $result['items'][] = $item;
    }

    $running = (int) $cash;

    foreach (array_keys($buckets) as $key) {
        $result['net'][$key] = $result['in'][$key] - $result['out'][$key];
        $running += $result['net'][$key];
        $result['position'][$key] = $running;
    }

    $result['net']['total'] = $result['in']['total'] - $result['out']['total'];
    $result['position']['total'] = $running;

    usort($result['items'], function ($a, $b) {
        $by_date = strcmp((string) $a['date'], (string) $b['date']);

        return ($by_date !== 0) ? $by_date : strcmp((string) $a['kind'], (string) $b['kind']);
    });

    return $result;
}

/**
 * The dates a repetition comes round on from its next date to the horizon's
 * last day, the way the repeating jobs step them: the same day of the month,
 * the month's last day when it has none. A date already past is today's:
 * the job writes it on its next run.
 *
 * @param string $next          Y-m-d
 * @param int    $every_months
 * @param int    $day_of_month
 * @param string $end_date      Y-m-d or '0000-00-00'
 * @param string $today         Y-m-d
 * @param string $last          Y-m-d, the horizon's last day
 * @return array  Y-m-d dates
 */
function erp_cash_forecast_repeats($next, $every_months, $day_of_month, $end_date, $today, $last)
{
    $dates = array();
    $date = (string) $next;
    $end = ((string) $end_date > '0000-00-00') ? (string) $end_date : '';

    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) !== 1) {
        return $dates;
    }

    while (($date <= $last) && (($end === '') || ($date <= $end)) && (count($dates) < 120)) {
        $dates[] = max($date, (string) $today);
        $date = erp_expense_recurring_step($date, max(1, (int) $every_months), max(1, (int) $day_of_month));
    }

    return $dates;
}

/**
 * A repeating document's amount in base kurus: as it is in the base
 * currency, at today's recorded rate otherwise, null when no rate is known.
 *
 * @param int    $amount
 * @param string $currency
 * @param string $today
 * @return int|null
 */
function erp_cash_forecast_to_base($amount, $currency, $today)
{
    $currency = strtoupper(trim((string) $currency));

    if (($currency === '') || ($currency === erp_base_currency()) || !erp_fx_enabled()) {
        return (int) $amount;
    }

    $rate = erp_fx_rate_for($currency, $today);

    return is_array($rate) ? erp_to_base((int) $amount, (float) $rate['rate']) : null;
}

/**
 * The tills the cheques and notes are kept in: their balance is the cheques
 * themselves, which the forecast counts on their own dates.
 *
 * @return array  till ids
 */
function erp_cash_forecast_cheque_tills()
{
    if (!function_exists('erp_cheques_ready') || !erp_cheques_ready()) {
        return array();
    }

    return array_map('intval', array_column((array) db_items("SELECT DISTINCT portfolio_till_id FROM erp_cheques WHERE portfolio_till_id > 0"), 'portfolio_till_id'));
}

/**
 * What the tills hold today towards the forecast: the base-currency tills,
 * the cheque tills left out.
 *
 * @return array ['total' => kurus, 'foreign' => int (tills in another currency, left out),
 *                'cheque_tills' => int (left out)]
 */
function erp_cash_forecast_cash()
{
    $held = erp_aging_cash_total();
    $cheque_tills = erp_cash_forecast_cheque_tills();
    $total = 0;
    $left_out = 0;

    foreach ($held['tills'] as $till) {
        if (in_array((int) $till['id'], $cheque_tills, true)) {
            $left_out++;
            continue;
        }

        $total += (int) $till['balance'];
    }

    return array('total' => $total, 'foreign' => count($held['foreign']), 'cheque_tills' => $left_out);
}

/**
 * Everything open on the books that is due by the horizon's last day.
 *
 * @param string $today    Y-m-d
 * @param int    $horizon
 * @return array ['items' => [[kind, date, amount, title, party, url], ...], 'unpriced' => int]
 */
function erp_cash_forecast_items($today, $horizon)
{
    $buckets = erp_cash_forecast_buckets($today, $horizon);
    $last = end($buckets)['end'];
    $items = array();
    $unpriced = 0;

    foreach (array('sales' => 'sales_invoice', 'purchase' => 'purchase_invoice') as $direction => $kind) {
        foreach (erp_aging_invoices($direction, $today) as $row) {
            if ((string) $row['effective_due'] > $last) {
                continue;
            }

            $items[] = array(
                'kind' => $kind,
                'date' => (string) $row['effective_due'],
                'amount' => (int) $row['open_base'],
                'title' => (string) $row['full_number'],
                'party' => (string) $row['title'],
                'url' => 'edit_erp_invoice.php?id=' . (int) $row['id'],
            );
        }
    }

    if (erp_expenses_ready()) {
        foreach ((array) db_items("SELECT id, expense_date, due_date, supplier, description, total_base FROM erp_expenses
            WHERE status = 'unpaid' AND IF(due_date > '0000-00-00', due_date, expense_date) <= '" . escape($last) . "'") as $row) {
            $items[] = array(
                'kind' => 'expense',
                'date' => ((string) $row['due_date'] > '0000-00-00') ? (string) $row['due_date'] : (string) $row['expense_date'],
                'amount' => (int) $row['total_base'],
                'title' => (string) $row['description'],
                'party' => (string) $row['supplier'],
                'url' => 'edit_erp_expense.php?id=' . (int) $row['id'],
            );
        }
    }

    foreach (erp_expense_recurrences(true) as $row) {
        $gross = ((int) $row['includes_tax'] === 1) ? (int) $row['amount'] : (int) $row['amount'] + erp_apply_rate((int) $row['amount'], (float) $row['tax_rate']);
        $amount = erp_cash_forecast_to_base($gross, (string) $row['currency'], $today);

        foreach (erp_cash_forecast_repeats((string) $row['next_date'], (int) $row['every_months'], (int) $row['day_of_month'], (string) $row['end_date'], $today, $last) as $date) {
            if ($amount === null) {
                $unpriced++;
                continue;
            }

            // Left to pay, it falls due its term after the date it is written.
            $cash_date = ((string) $row['pay_mode'] === 'paid') ? $date : date('Y-m-d', strtotime($date . ' +' . (int) $row['due_days'] . ' days'));

            $items[] = array(
                'kind' => 'recurring_expense',
                'date' => $cash_date,
                'amount' => $amount,
                'title' => (string) $row['description'],
                'party' => (string) $row['supplier'],
                'url' => 'erp_expenses.php',
            );
        }
    }

    foreach (erp_invoice_recurrences(true) as $row) {
        $amount = erp_cash_forecast_to_base((int) $row['grand_total'], (string) $row['currency'], $today);

        foreach (erp_cash_forecast_repeats((string) $row['next_date'], (int) $row['every_months'], (int) $row['day_of_month'], (string) $row['end_date'], $today, $last) as $date) {
            if ($amount === null) {
                $unpriced++;
                continue;
            }

            $items[] = array(
                'kind' => 'recurring_invoice',
                'date' => erp_account_due_date((int) $row['account_id'], $date),
                'amount' => $amount,
                'title' => lang('Repeating invoice'),
                'party' => (string) ($row['account_title'] ?? ''),
                'url' => 'erp_invoice_recurrences.php',
            );
        }
    }

    if (erp_cheques_ready()) {
        foreach ((array) db_items("SELECT c.id, c.direction, c.kind, c.due_date, c.amount_base, c.serial_no, a.title AS account_title
            FROM erp_cheques c
            LEFT JOIN erp_accounts a ON a.id = c.account_id
            WHERE c.status IN ('portfolio', 'deposited', 'given') AND c.due_date <= '" . escape($last) . "'") as $row) {
            $items[] = array(
                'kind' => ((string) $row['direction'] === 'given') ? 'cheque_given' : 'cheque_received',
                'date' => (string) $row['due_date'],
                'amount' => (int) $row['amount_base'],
                'title' => (((string) $row['kind'] === 'note') ? lang('Promissory note') : lang('Cheque')) . (((string) $row['serial_no'] !== '') ? ' ' . (string) $row['serial_no'] : ''),
                'party' => (string) $row['account_title'],
                'url' => 'erp_cheque.php?id=' . (int) $row['id'],
            );
        }
    }

    return array('items' => $items, 'unpriced' => $unpriced);
}

/**
 * The forecast for the screen.
 *
 * @param int    $horizon
 * @param string $today  Y-m-d, '' for today
 * @return array  erp_cash_forecast_build()'s result plus horizon, today, unpriced, foreign_tills, cheque_tills
 */
function erp_cash_forecast($horizon, $today = '')
{
    $today = erp_aging_as_of($today);
    $horizon = erp_cash_forecast_horizon($horizon);
    $collected = erp_cash_forecast_items($today, $horizon);
    $cash = erp_cash_forecast_cash();

    $forecast = erp_cash_forecast_build($collected['items'], $today, $horizon, $cash['total']);
    $forecast['horizon'] = $horizon;
    $forecast['today'] = $today;
    $forecast['unpriced'] = $collected['unpriced'];
    $forecast['foreign_tills'] = $cash['foreign'];
    $forecast['cheque_tills'] = $cash['cheque_tills'];

    return $forecast;
}
