<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * ERP - due dates and aging.
 *
 * One place works out how far past its due date an open invoice is, and which
 * age bucket that puts it in. The aging report, the badges on the invoice list
 * and the dashboard cards all read from here, so the three screens can never
 * disagree about what is overdue.
 *
 * Figures are point in time: an invoice is measured as it stood on the as-of
 * date, with only the settlements and returns dated on or before that day
 * taken off it. Asked for today, that is exactly erp_invoice_open_amount();
 * asked for a month end, it reproduces the position of that month end.
 *
 * Base-currency figures are the booked ones (grand_total_base, the
 * settlements' amount_base, the returns' grand_total_base): the same integers
 * the account balances are made of, so the report reconciles with the
 * accounts screen and no new rounding happens here.
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
 * The age buckets, in the order they are shown: key => label.
 *
 * @return array
 */
function erp_aging_buckets()
{
    return array(
        'current' => lang('Not yet due'),
        'd1_30' => lang('1-30 days'),
        'd31_60' => lang('31-60 days'),
        'd61_90' => lang('61-90 days'),
        'd90p' => lang('Over 90 days'),
    );
}

/**
 * Whether a bucket key names one of the buckets.
 *
 * @param string $key
 * @return bool
 */
function erp_aging_bucket_valid($key)
{
    return array_key_exists((string) $key, erp_aging_buckets());
}

/**
 * The SQL for an invoice's effective due date.
 *
 * Every writer fills due_date, but the column allows a zero date; a document
 * without a term is treated as due on the day it was issued.
 *
 * @param string $alias  Table alias of erp_invoices in the query
 * @return string
 */
function erp_aging_due_sql($alias = 'i')
{
    return "IF(" . $alias . ".due_date = '0000-00-00', " . $alias . ".issue_date, " . $alias . ".due_date)";
}

/**
 * The as-of date to work from: a valid Y-m-d, or today.
 *
 * @param string $value
 * @return string  Y-m-d
 */
function erp_aging_as_of($value)
{
    $value = trim((string) $value);

    if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $parts) === 1
        && checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1])) {
        return $value;
    }

    return date('Y-m-d');
}

/**
 * Days an invoice is past due on the as-of date.
 *
 * Positive when the due date has passed, zero on the due date itself, negative
 * while the term still runs. A due date that does not parse reads as due
 * today.
 *
 * @param string $due_date  Y-m-d
 * @param string $as_of     Y-m-d
 * @return int
 */
function erp_aging_days($due_date, $as_of)
{
    $due = date_create((string) $due_date);
    $at = date_create((string) $as_of);

    if (($due === false) || ($at === false)) {
        return 0;
    }

    $diff = $due->diff($at);

    return $diff->invert ? -((int) $diff->days) : (int) $diff->days;
}

/**
 * The bucket a number of days past due falls in.
 *
 * @param int $days  From erp_aging_days()
 * @return string  Bucket key
 */
function erp_aging_bucket($days)
{
    $days = (int) $days;

    if ($days <= 0) {
        return 'current';
    }
    if ($days <= 30) {
        return 'd1_30';
    }
    if ($days <= 60) {
        return 'd31_60';
    }
    if ($days <= 90) {
        return 'd61_90';
    }

    return 'd90p';
}

/**
 * The invoices that were open on the as-of date, aged.
 *
 * Drafts and cancelled documents are never open; return documents are the
 * credit, not the claim. Everything else issued by the date is measured, and
 * only what still had money owing on it is returned. Asked for a date that is
 * not in the past, the current status narrows the scan to what is open now -
 * for an earlier date a paid invoice may still have been open, so the sums
 * decide.
 *
 * Each row carries: open (document currency, kurus), open_base (base
 * currency, booked rates, kurus), effective_due, days (past due) and bucket,
 * beside the invoice columns and the live account title.
 *
 * @param string $direction  'sales' (receivables), 'purchase' (payables) or '' for both
 * @param string $as_of      Y-m-d
 * @param array  $filters    account_id (int), overdue (bool), due_within
 *                           (int days ahead, 0 = off), bucket (key)
 * @return array
 */
function erp_aging_invoices($direction, $as_of, $filters = array())
{
    $as_of = erp_aging_as_of($as_of);
    $direction = (string) $direction;
    $account_id = (int) ($filters['account_id'] ?? 0);
    $overdue_only = !empty($filters['overdue']);
    $due_within = (int) ($filters['due_within'] ?? 0);
    $bucket = (string) ($filters['bucket'] ?? '');

    $where = "i.doc_type = 'invoice' AND i.status NOT IN ('draft', 'cancelled') AND i.issue_date <= '" . escape($as_of) . "'";

    if (($direction === 'sales') || ($direction === 'purchase')) {
        $where .= " AND i.direction = '" . $direction . "'";
    }
    if ($account_id > 0) {
        $where .= " AND i.account_id = '" . $account_id . "'";
    }
    if ($as_of >= date('Y-m-d')) {
        $where .= " AND i.status IN ('issued', 'partially_paid')";
    }

    $rows = (array) db_items("SELECT i.id, i.direction, i.full_number, i.account_id, i.account_title, i.order_id,
            i.issue_date, i.due_date, i.currency, i.grand_total, i.grand_total_base, i.status,
            " . erp_aging_due_sql('i') . " AS effective_due,
            a.title AS live_account_title,
            (SELECT COALESCE(SUM(s.amount), 0) FROM erp_settlements s
                WHERE s.invoice_id = i.id AND s.doc_date <= '" . escape($as_of) . "') AS paid_as_of,
            (SELECT COALESCE(SUM(s.amount_base), 0) FROM erp_settlements s
                WHERE s.invoice_id = i.id AND s.doc_date <= '" . escape($as_of) . "') AS paid_base_as_of,
            (SELECT COALESCE(SUM(r.grand_total), 0) FROM erp_invoices r
                WHERE r.parent_invoice_id = i.id AND r.doc_type = 'return' AND r.status <> 'cancelled'
                  AND r.issue_date <= '" . escape($as_of) . "') AS returned_as_of,
            (SELECT COALESCE(SUM(r.grand_total_base), 0) FROM erp_invoices r
                WHERE r.parent_invoice_id = i.id AND r.doc_type = 'return' AND r.status <> 'cancelled'
                  AND r.issue_date <= '" . escape($as_of) . "') AS returned_base_as_of
        FROM erp_invoices i
        LEFT JOIN erp_accounts a ON a.id = i.account_id
        WHERE " . $where . "
        ORDER BY effective_due ASC, i.id ASC");

    $aged = array();

    foreach ($rows as $row) {
        $open = (int) $row['grand_total'] - (int) $row['paid_as_of'] - (int) $row['returned_as_of'];

        if ($open <= 0) {
            continue;
        }

        // Booked rates: a foreign document whose receipts came in at a higher
        // rate can read as over-covered in the base currency while money is
        // still owed in its own. Nothing negative is owed, so it reads as zero
        // and the difference is the exchange gap the ledger settles on closing.
        $open_base = (int) $row['grand_total_base'] - (int) $row['paid_base_as_of'] - (int) $row['returned_base_as_of'];
        if ($open_base < 0) {
            $open_base = 0;
        }

        $days = erp_aging_days((string) $row['effective_due'], $as_of);
        $key = erp_aging_bucket($days);

        if ($overdue_only && ($days <= 0)) {
            continue;
        }
        if (($due_within > 0) && (($days > 0) || (-$days > $due_within))) {
            continue;
        }
        if (($bucket !== '') && ($bucket !== $key)) {
            continue;
        }

        $row['open'] = $open;
        $row['open_base'] = $open_base;
        $row['days'] = $days;
        $row['bucket'] = $key;
        $row['title'] = (trim((string) $row['live_account_title']) !== '')
            ? (string) $row['live_account_title']
            : (string) $row['account_title'];

        $aged[] = $row;
    }

    return $aged;
}

/**
 * Aged rows folded into one line per account.
 *
 * @param array $rows  From erp_aging_invoices()
 * @return array  ['accounts' => [account_id => [id, title, count, oldest_days,
 *                buckets => [key => kurus], total]], 'totals' => [buckets, total, count]]
 */
function erp_aging_by_account($rows)
{
    $empty = array_fill_keys(array_keys(erp_aging_buckets()), 0);
    $accounts = array();
    $totals = array('buckets' => $empty, 'total' => 0, 'count' => 0);

    foreach ($rows as $row) {
        $account_id = (int) $row['account_id'];

        if (!isset($accounts[$account_id])) {
            $accounts[$account_id] = array(
                'id' => $account_id,
                'title' => (string) $row['title'],
                'count' => 0,
                'oldest_days' => 0,
                'buckets' => $empty,
                'total' => 0,
            );
        }

        $base = (int) $row['open_base'];
        $key = (string) $row['bucket'];

        $accounts[$account_id]['buckets'][$key] += $base;
        $accounts[$account_id]['total'] += $base;
        $accounts[$account_id]['count']++;
        if ((int) $row['days'] > $accounts[$account_id]['oldest_days']) {
            $accounts[$account_id]['oldest_days'] = (int) $row['days'];
        }

        $totals['buckets'][$key] += $base;
        $totals['total'] += $base;
        $totals['count']++;
    }

    // Largest claim first; the same total keeps the order the rows came in.
    uasort($accounts, function ($a, $b) {
        if ($a['total'] === $b['total']) {
            return 0;
        }
        return ($a['total'] > $b['total']) ? -1 : 1;
    });

    return array('accounts' => $accounts, 'totals' => $totals);
}

/**
 * The figures the dashboard cards show for one direction.
 *
 * @param string $direction  'sales' or 'purchase'
 * @param string $as_of      Y-m-d
 * @param int    $top        How many overdue accounts to list
 * @return array  total_base, count, overdue_count, overdue_base, due_soon_count,
 *                due_soon_base, buckets (key => kurus), top (account lines)
 */
function erp_aging_summary($direction, $as_of, $top = 5)
{
    $rows = erp_aging_invoices($direction, $as_of);
    $folded = erp_aging_by_account($rows);

    $summary = array(
        'total_base' => (int) $folded['totals']['total'],
        'count' => (int) $folded['totals']['count'],
        'overdue_count' => 0,
        'overdue_base' => 0,
        'due_soon_count' => 0,
        'due_soon_base' => 0,
        'buckets' => $folded['totals']['buckets'],
        'top' => array(),
    );

    $overdue = array();

    foreach ($rows as $row) {
        $days = (int) $row['days'];

        if ($days > 0) {
            $summary['overdue_count']++;
            $summary['overdue_base'] += (int) $row['open_base'];
            $overdue[] = $row;
        } elseif (-$days <= 7) {
            $summary['due_soon_count']++;
            $summary['due_soon_base'] += (int) $row['open_base'];
        }
    }

    $summary['top'] = array_slice(erp_aging_by_account($overdue)['accounts'], 0, (int) $top, true);

    return $summary;
}

/**
 * What the tills hold, in the base currency.
 *
 * A till kept in another currency is listed but not added in, as the cash
 * screen does: a total only means something in one currency.
 *
 * @return array  ['total' => kurus, 'tills' => rows added in, 'foreign' => rows left out]
 */
function erp_aging_cash_total()
{
    $tills = (array) db_items("SELECT id, name, kind, currency, balance FROM erp_cash_accounts
        WHERE is_active = 1 ORDER BY sort_order ASC, id ASC");

    $result = array('total' => 0, 'tills' => array(), 'foreign' => array());

    foreach ($tills as $till) {
        $currency = strtoupper(trim((string) $till['currency']));

        if (!erp_fx_enabled() || ($currency === erp_base_currency())) {
            $result['total'] += (int) $till['balance'];
            $result['tills'][] = $till;
        } else {
            $result['foreign'][] = $till;
        }
    }

    return $result;
}

/**
 * The aging table as CSV columns and rows.
 *
 * Amounts are written the way the generic invoice export writes them, so a
 * spreadsheet reads both files the same way.
 *
 * @param array $folded  From erp_aging_by_account()
 * @return array  ['columns' => [...], 'rows' => [[...], ...]]
 */
function erp_aging_csv($folded)
{
    $profile = erp_export_profile('csv_invoices');
    $buckets = erp_aging_buckets();

    $columns = array_merge(array(lang('Account'), lang('Documents')), array_values($buckets), array(lang('Total')));
    $rows = array();

    foreach ($folded['accounts'] as $account) {
        $row = array($account['title'], (int) $account['count']);
        foreach (array_keys($buckets) as $key) {
            $row[] = erp_export_amount($account['buckets'][$key], $profile);
        }
        $row[] = erp_export_amount($account['total'], $profile);
        $rows[] = $row;
    }

    $row = array(lang('Total'), (int) $folded['totals']['count']);
    foreach (array_keys($buckets) as $key) {
        $row[] = erp_export_amount($folded['totals']['buckets'][$key], $profile);
    }
    $row[] = erp_export_amount($folded['totals']['total'], $profile);
    $rows[] = $row;

    return array('columns' => $columns, 'rows' => $rows);
}
