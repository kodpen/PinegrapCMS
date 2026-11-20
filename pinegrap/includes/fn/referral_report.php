<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The Referrals report (view_referral_report.php): read from the referral
 * programme's commissions rows (includes/fn/referral.php) of the last twelve
 * months. A row is a friend's order that earned a reward: paid while the
 * reward stands, ineligible once a cancel or a refund took it back. By
 * month: the referred orders, the rewards kept and taken back, the credit
 * kept and the kept orders' value; the top referrers by the contact their
 * signed code names.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/** How many months the report reads, the current one included. */
const PG_REFERRAL_REPORT_MONTHS = 12;

/** How many referrers the top list shows. */
const PG_REFERRAL_REPORT_TOP = 10;

/**
 * The first moment of the oldest month the report reads, in the site's time
 * zone. Pure.
 *
 * @param int $now
 * @param int $count months, the current one included
 * @return int
 */
function pg_referral_report_start($now, $count = PG_REFERRAL_REPORT_MONTHS)
{
    return (int) mktime(0, 0, 0, (int) date('n', (int) $now) - (max(1, (int) $count) - 1), 1, (int) date('Y', (int) $now));
}

/**
 * The months the report reads as 'YYYY-MM', oldest first. Pure.
 *
 * @param int $now
 * @param int $count
 * @return string[]
 */
function pg_referral_report_month_keys($now, $count = PG_REFERRAL_REPORT_MONTHS)
{
    $count = max(1, (int) $count);
    $month = (int) date('n', (int) $now);
    $year = (int) date('Y', (int) $now);
    $keys = array();

    for ($i = $count - 1; $i >= 0; $i--) {
        $keys[] = date('Y-m', mktime(12, 0, 0, $month - $i, 1, $year));
    }

    return $keys;
}

/**
 * An empty month of the table. Pure.
 *
 * @return array orders, kept, taken, credit, value
 */
function pg_referral_report_zero()
{
    return array('orders' => 0, 'kept' => 0, 'taken' => 0, 'credit' => 0, 'value' => 0);
}

/**
 * The month table from the referral rows: every referred order, the rewards
 * that stand (paid) and those taken back (ineligible), the credit and the
 * order value of the rewards that stand. A row of a month not in $keys is
 * left out. Pure.
 *
 * @param array    $rows created_timestamp, status, amount, value (minor units)
 * @param string[] $keys pg_referral_report_month_keys()
 * @return array 'YYYY-MM' => pg_referral_report_zero()'s shape, in $keys' order
 */
function pg_referral_report_months(array $rows, array $keys)
{
    $months = array();

    foreach ($keys as $key) {
        $months[$key] = pg_referral_report_zero();
    }

    foreach ($rows as $row) {
        $key = date('Y-m', (int) ($row['created_timestamp'] ?? 0));

        if (!isset($months[$key])) {
            continue;
        }

        $months[$key]['orders']++;

        if ((string) ($row['status'] ?? '') === 'paid') {
            $months[$key]['kept']++;
            $months[$key]['credit'] += (int) ($row['amount'] ?? 0);
            $months[$key]['value'] += (int) ($row['value'] ?? 0);
        } else {
            $months[$key]['taken']++;
        }
    }

    return $months;
}

/**
 * The months added up. Pure.
 *
 * @param array $months pg_referral_report_months()
 * @return array pg_referral_report_zero()'s shape
 */
function pg_referral_report_totals(array $months)
{
    $totals = pg_referral_report_zero();

    foreach ($months as $month) {
        foreach ($totals as $field => $sum) {
            $totals[$field] = $sum + (int) ($month[$field] ?? 0);
        }
    }

    return $totals;
}

/**
 * The top referrers: the rows grouped by the contact their code names when
 * its signature holds, those with a reward that stands, most rewards kept
 * first, then the larger credit, then the lower contact id. Pure.
 *
 * @param array  $rows  affiliate_code, status, amount, value
 * @param string $key   the site's key (pg_referral_key())
 * @param int    $limit
 * @return array rank, contact_id, orders, kept, credit, value
 */
function pg_referral_report_top(array $rows, $key, $limit = PG_REFERRAL_REPORT_TOP)
{
    $people = array();

    foreach ($rows as $row) {
        $contact_id = pg_referral_code_contact($row['affiliate_code'] ?? '', (string) $key);

        if ($contact_id <= 0) {
            continue;
        }

        if (!isset($people[$contact_id])) {
            $people[$contact_id] = array('rank' => 0, 'contact_id' => $contact_id) + pg_referral_report_zero();
            unset($people[$contact_id]['taken']);
        }

        $people[$contact_id]['orders']++;

        if ((string) ($row['status'] ?? '') === 'paid') {
            $people[$contact_id]['kept']++;
            $people[$contact_id]['credit'] += (int) ($row['amount'] ?? 0);
            $people[$contact_id]['value'] += (int) ($row['value'] ?? 0);
        }
    }

    $people = array_values(array_filter($people, function ($person) {
        return $person['kept'] > 0;
    }));

    usort($people, function ($a, $b) {
        if ($a['kept'] !== $b['kept']) {
            return $b['kept'] - $a['kept'];
        }

        if ($a['credit'] !== $b['credit']) {
            return $b['credit'] - $a['credit'];
        }

        return $a['contact_id'] - $b['contact_id'];
    });

    $people = array_slice($people, 0, max(0, (int) $limit));

    foreach ($people as $i => $person) {
        $people[$i]['rank'] = $i + 1;
    }

    return $people;
}

/**
 * The summary figures over the chart. Pure.
 *
 * @param array  $totals pg_referral_report_totals()
 * @param string $symbol
 * @return array pg_report_view_figure() rows
 */
function pg_referral_report_figures(array $totals, $symbol)
{
    $taken = (int) $totals['taken'];

    return array(
        pg_report_view_figure(lang('Referred orders'), pg_format_number((int) $totals['orders'], 0), 'bi-bag', 'blue'),
        pg_report_view_figure(lang('Rewards kept'), pg_format_number((int) $totals['kept'], 0), 'bi-gift', 'violet',
            ($taken > 0) ? lang(array('string' => '{var:1} taken back', 'vars' => pg_format_number($taken, 0))) : ''),
        pg_report_view_figure(lang('Store credit given'), pg_report_view_money((int) $totals['credit'], $symbol), 'bi-wallet2', 'amber'),
        pg_report_view_figure(lang('Referred orders\' value'), pg_report_view_money((int) $totals['value'], $symbol), 'bi-cash-stack', 'teal'),
    );
}

/**
 * The chart of the months: the referred orders' value, with the credit
 * given dashed beside it. Null for fewer than two months or none with a
 * reward. Pure.
 *
 * @param array  $months pg_referral_report_months()
 * @param string $symbol
 * @return array|null
 */
function pg_referral_report_chart(array $months, $symbol)
{
    if (count($months) < 2) {
        return null;
    }

    $value = lang('Referred orders\' value');
    $credit = lang('Store credit given');
    $chart = array('kind' => 'line', 'label' => lang('Value by month'), 'series' => $value, 'compare_label' => $credit, 'values' => array(), 'compare' => array(), 'names' => array(), 'notes' => array(), 'compare_notes' => array(), 'tips' => array());
    $any = false;

    foreach ($months as $key => $row) {
        $name = pg_customer_value_month_name($key);
        $any = $any || ($row['value'] > 0) || ($row['credit'] > 0);
        $chart['values'][] = round($row['value'] / 100, 2);
        $chart['compare'][] = round($row['credit'] / 100, 2);
        $chart['names'][] = $name;
        $chart['notes'][] = pg_report_view_money($row['value'], $symbol);
        $chart['compare_notes'][] = pg_report_view_money($row['credit'], $symbol);
        $chart['tips'][] = $name . ': ' . $value . ' ' . pg_report_view_money($row['value'], $symbol) . ' · ' . $credit . ' ' . pg_report_view_money($row['credit'], $symbol);
    }

    return $any ? $chart : null;
}

/**
 * The referral rows since $start with each friend's order value after its
 * refunds (pg_referral_order_value()). Two queries: the rows with their
 * orders, then the refund rows of those orders.
 *
 * @param int $start
 * @return array affiliate_code, status, amount, created_timestamp, value
 */
function pg_referral_report_rows($start)
{
    if (!pg_referral_ready()) {
        return array();
    }

    $refunded = pg_schema_has('orders', 'refunded_amount') ? 'COALESCE(o.refunded_amount, 0)' : '0';
    $rows = db_items(
        "SELECT c.affiliate_code, c.status, c.amount, c.created_timestamp, c.order_id,
            COALESCE(o.total, 0) AS total, COALESCE(o.gift_card_discount, 0) AS gift_card_discount, COALESCE(o.surcharge, 0) AS surcharge,
            " . $refunded . " AS refunded_amount
        FROM commissions AS c
        LEFT JOIN orders AS o ON o.id = c.order_id
        WHERE (c.created_timestamp >= '" . (int) $start . "') AND " . pg_referral_row_sql('c.affiliate_code'));

    if (!is_array($rows) || empty($rows)) {
        return array();
    }

    $refunds = array();

    if (pg_schema_has('order_refunds')) {
        $ids = implode(',', array_unique(array_map('intval', array_column($rows, 'order_id'))));

        foreach ((array) db_items("SELECT order_id, SUM(amount_cents) AS amount FROM order_refunds WHERE order_id IN (" . $ids . ") GROUP BY order_id") as $refund) {
            $refunds[(int) $refund['order_id']] = (int) $refund['amount'];
        }
    }

    foreach ($rows as $i => $row) {
        // As pg_referral_refunded_total(): the larger of the refund rows and
        // the payment total the order keeps.
        $refund = max((int) $row['refunded_amount'], $refunds[(int) $row['order_id']] ?? 0);
        $rows[$i]['value'] = pg_referral_order_value($row, $refund);
    }

    return $rows;
}

/**
 * The names of the top referrers' contacts, for a reader who may read
 * contacts.
 *
 * @param int[] $contact_ids
 * @return array contact id => name ('' for a contact without one)
 */
function pg_referral_report_names(array $contact_ids)
{
    $contact_ids = array_filter(array_map('intval', $contact_ids));

    if (empty($contact_ids)) {
        return array();
    }

    $names = array();

    foreach ((array) db_items("SELECT id, first_name, last_name, email_address FROM contacts WHERE id IN (" . implode(',', $contact_ids) . ")") as $row) {
        $name = trim($row['first_name'] . ' ' . $row['last_name']);
        $names[(int) $row['id']] = ($name !== '') ? $name : (string) $row['email_address'];
    }

    return $names;
}
