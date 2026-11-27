<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The one-stop-shop report (oss_report.php, under Commerce): the taxable
 * amount and the VAT of a period's sales by EU member state and rate, goods
 * and services apart, in euro at the period's last day, as the OSS return
 * asks for them. A line's member state is the one the checkout stored with
 * its tax (order_items.tax_country, 11.0.287); lines stored without it
 * (orders placed before the column, counter sales, renewals, marketplace
 * imports) are shown apart, their state taken from the recipient's address,
 * else the billing one. Amounts are the lines' own once the order discount is
 * shared out as the checkout shares it (pg_order_line_charged_taxes()).
 * Everything but pg_oss_report_read() and pg_oss_euro_rate() is pure.
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
 * The period a report covers: a quarter ('2026-Q3', the Union and non-Union
 * schemes) or a month ('2026-07', the import scheme). A value that does not
 * read as its range gives the period holding $today and an error.
 *
 * @param string $range 'quarter' or 'month'
 * @param string $value '2026-Q3' or '2026-07'
 * @param string $today Y-m-d
 * @return array range, value, from, to (Y-m-d), error ('' or an English text for lang())
 */
function pg_oss_period($range, $value, $today)
{
    $range = ($range === 'month') ? 'month' : 'quarter';
    $value = trim((string) $value);
    $error = '';
    $year = (int) substr((string) $today, 0, 4);
    $month = (int) substr((string) $today, 5, 2);

    if ($range === 'quarter') {
        if (preg_match('/^(\d{4})-Q([1-4])$/', $value, $match) && ((int) $match[1] >= 2000)) {
            $year = (int) $match[1];
            $first = ((int) $match[2] - 1) * 3 + 1;
        } else {
            $error = ($value === '') ? '' : 'The period could not be read; the current one is shown.';
            $first = (int) (floor(($month - 1) / 3) * 3) + 1;
        }
        $last = $first + 2;
        $value = $year . '-Q' . (int) (($first + 2) / 3);
    } else {
        if (preg_match('/^(\d{4})-(\d{2})$/', $value, $match) && ((int) $match[1] >= 2000) && ((int) $match[2] >= 1) && ((int) $match[2] <= 12)) {
            $year = (int) $match[1];
            $first = (int) $match[2];
        } else {
            $error = ($value === '') ? '' : 'The period could not be read; the current one is shown.';
            $first = $month;
        }
        $last = $first;
        $value = sprintf('%04d-%02d', $year, $first);
    }

    $from = sprintf('%04d-%02d-01', $year, $first);
    $to = date('Y-m-t', (int) strtotime(sprintf('%04d-%02d-01', $year, $last)));

    return array('range' => $range, 'value' => $value, 'from' => $from, 'to' => $to, 'error' => $error);
}

/**
 * The periods offered in the picker, newest first: the $count quarters or
 * months up to the one holding $today.
 *
 * @param string $range 'quarter' or 'month'
 * @param string $today Y-m-d
 * @param int    $count
 * @return string[] values as pg_oss_period() reads them
 */
function pg_oss_period_values($range, $today, $count)
{
    $year = (int) substr((string) $today, 0, 4);
    $month = (int) substr((string) $today, 5, 2);
    $values = array();

    if ($range === 'month') {
        for ($i = 0; $i < $count; $i++) {
            $values[] = sprintf('%04d-%02d', $year, $month);
            if (--$month < 1) {
                $month = 12;
                $year--;
            }
        }
    } else {
        $quarter = (int) ceil($month / 3);
        for ($i = 0; $i < $count; $i++) {
            $values[] = $year . '-Q' . $quarter;
            if (--$quarter < 1) {
                $quarter = 4;
                $year--;
            }
        }
    }

    return $values;
}

/**
 * The member state a line is reported under, and whether it was stored.
 * A stored order_items.tax_country is taken as it is ('' is a line taxed in
 * no member state: exempt, tax off or outside the EU). Without one (NULL),
 * the recipient's country for a line with a recipient, else the billing
 * country, when it is a member state on $date and the order was not exempt.
 *
 * @param string|null $stored          order_items.tax_country
 * @param int         $ship_to_id      order_items.ship_to_id
 * @param string      $ship_country    ship_tos.country
 * @param string      $billing_country orders.billing_country
 * @param mixed       $exempt          orders.tax_exempt
 * @param string      $date            Y-m-d, the order's day
 * @return array country ('' for none), stored (bool)
 */
function pg_oss_line_country($stored, $ship_to_id, $ship_country, $billing_country, $exempt, $date)
{
    if ($stored !== null) {
        return array('country' => pg_tax_line_country(true, false, $stored, $date), 'stored' => true);
    }

    $country = ((int) $ship_to_id > 0) ? $ship_country : $billing_country;

    return array('country' => pg_tax_line_country(true, $exempt, $country, $date), 'stored' => false);
}

/**
 * Adds one order's lines to the report's groups.
 *
 * The order discount is shared over the lines charged with the order by
 * their amounts (pg_tax_spread()) and their tax taken again on what is left
 * (pg_order_line_charged_taxes(), the rule of the order's invoice), so a
 * group holds what was charged. A line not charged with the order (a
 * recurring line starting later) and a line in no member state are left
 * out. A line without a kept rate is put at the rate its tax stands for
 * (pg_tax_line_rate()).
 *
 * @param array $report  as pg_oss_report_empty() gives it
 * @param array $order   id, discount, tax (minor units)
 * @param array $lines   id => amount, rate (string|null), tax, quantity, alone (bool),
 *                       country, stored (pg_oss_line_country()), goods (bool)
 * @return array the report
 */
function pg_oss_report_add($report, $order, $lines)
{
    $taxes = pg_order_line_charged_taxes($lines, (int) ($order['discount'] ?? 0), (int) ($order['tax'] ?? 0));
    $amounts = array();

    foreach ($lines as $id => $line) {
        if (empty($line['alone'])) {
            $amounts[$id] = (int) $line['amount'];
        }
    }

    $discount = min(max(0, (int) ($order['discount'] ?? 0)), array_sum($amounts));
    $shares = ($discount > 0) ? pg_tax_spread($discount, $amounts) : array();

    foreach ($lines as $id => $line) {
        if (!empty($line['alone']) || ((string) $line['country'] === '')) {
            continue;
        }

        $rate = (isset($line['rate']) && ($line['rate'] !== ''))
            ? (float) $line['rate']
            : pg_tax_line_rate((int) $line['amount'], (int) $line['tax'], max(1, (int) ($line['quantity'] ?? 1)));
        $part = !empty($line['stored']) ? 'stored' : 'apart';
        $supply = !empty($line['goods']) ? 'goods' : 'services';
        $key = $line['country'] . '|' . $supply . '|' . number_format($rate, 3, '.', '');

        if (!isset($report[$part][$key])) {
            $report[$part][$key] = array(
                'country' => (string) $line['country'],
                'supply' => $supply,
                'rate' => $rate,
                'taxable' => 0,
                'tax' => 0,
                'lines' => 0,
                'orders' => array(),
            );
        }

        $report[$part][$key]['taxable'] += (int) $line['amount'] - (int) ($shares[$id] ?? 0);
        $report[$part][$key]['tax'] += (int) ($taxes[$id] ?? 0);
        $report[$part][$key]['lines']++;
        $report[$part][$key]['orders'][(int) ($order['id'] ?? 0)] = true;
    }

    return $report;
}

/**
 * An empty report: 'stored' holds the lines whose member state the checkout
 * kept, 'apart' the lines whose state is taken from the addresses.
 *
 * @return array
 */
function pg_oss_report_empty()
{
    return array('stored' => array(), 'apart' => array());
}

/**
 * Minor units of the base currency in euro cents at a rate of base units per
 * euro (currency_rates' direction), rounded half away from zero. The base
 * currency's minor unit is taken as a hundredth, as the store keeps prices.
 *
 * @param int   $minor
 * @param float $rate  base units per 1 EUR; 1.0 for a euro store
 * @return int
 */
function pg_oss_to_euro($minor, $rate)
{
    $rate = (float) $rate;

    if ($rate <= 0) {
        return 0;
    }

    return (int) round((int) $minor / $rate);
}

/**
 * A part of the report as rows in euro, sorted by member state, goods before
 * services, then rate, with its totals: each group converted once, the
 * totals the sum of the converted groups.
 *
 * @param array $groups pg_oss_report_add()'s 'stored' or 'apart'
 * @param float $rate   base units per 1 EUR
 * @return array rows (country, supply, rate, taxable, tax, lines, orders), taxable, tax
 */
function pg_oss_report_rows($groups, $rate)
{
    $rows = array();

    foreach ((array) $groups as $group) {
        $rows[] = array(
            'country' => $group['country'],
            'supply' => $group['supply'],
            'rate' => (float) $group['rate'],
            'taxable' => pg_oss_to_euro($group['taxable'], $rate),
            'tax' => pg_oss_to_euro($group['tax'], $rate),
            'lines' => (int) $group['lines'],
            'orders' => count($group['orders']),
        );
    }

    usort($rows, function ($a, $b) {
        if ($a['country'] !== $b['country']) {
            return strcmp($a['country'], $b['country']);
        }
        if ($a['supply'] !== $b['supply']) {
            return ($a['supply'] === 'goods') ? -1 : 1;
        }

        return ($a['rate'] < $b['rate']) ? -1 : (($a['rate'] > $b['rate']) ? 1 : 0);
    });

    return array(
        'rows' => $rows,
        'taxable' => array_sum(array_column($rows, 'taxable')),
        'tax' => array_sum(array_column($rows, 'tax')),
    );
}

/**
 * A rate as the report prints it: up to three decimals, no trailing zeros.
 *
 * @param float $rate
 * @return string
 */
function pg_oss_rate_text($rate)
{
    $text = rtrim(rtrim(number_format((float) $rate, 3, '.', ''), '0'), '.');

    return ($text === '') ? '0' : $text;
}

/**
 * Euro cents as a plain decimal for the CSV file: 1234 is "12.34".
 *
 * @param int $cents
 * @return string
 */
function pg_oss_amount_text($cents)
{
    $cents = (int) $cents;

    return (($cents < 0) ? '-' : '') . intdiv(abs($cents), 100) . '.' . sprintf('%02d', abs($cents) % 100);
}

/**
 * The CSV file's rows: a heading row, then each part's rows, a line apart
 * marking the rows whose member state comes from the addresses.
 *
 * @param array  $stored  pg_oss_report_rows() of the stored part
 * @param array  $apart   pg_oss_report_rows() of the part apart
 * @param array  $labels  heading => text: country, supply, rate, taxable, tax, lines, part,
 *                        goods, services, stored_part, apart_part (already translated)
 * @return array of row arrays
 */
function pg_oss_report_csv_rows($stored, $apart, $labels)
{
    $out = array(array($labels['part'], $labels['country'], $labels['supply'], $labels['rate'], $labels['taxable'], $labels['tax'], $labels['lines']));

    foreach (array('stored' => $stored, 'apart' => $apart) as $part => $figures) {
        foreach ($figures['rows'] as $row) {
            $out[] = array(
                $labels[$part . '_part'],
                $row['country'],
                $labels[$row['supply']],
                pg_oss_rate_text($row['rate']),
                pg_oss_amount_text($row['taxable']),
                pg_oss_amount_text($row['tax']),
                (string) $row['lines'],
            );
        }
    }

    return $out;
}

/**
 * The euro rate at the period's last day: base units per 1 EUR from the
 * dated rates (pg_currency_rate()), 1.0 for a euro store, false when none is
 * recorded on or before the day.
 *
 * @param string $date Y-m-d
 * @return array|false rate, rate_date
 */
function pg_oss_euro_rate($date)
{
    $base = strtoupper(defined('BASE_CURRENCY_CODE') ? (string) BASE_CURRENCY_CODE : '');

    if ($base === 'EUR') {
        return array('rate' => 1.0, 'rate_date' => (string) $date);
    }

    if (!function_exists('pg_currency_rate') || !function_exists('pg_currency_rates_table_exists') || !pg_currency_rates_table_exists()) {
        return false;
    }

    $rate = pg_currency_rate('EUR', $date);

    return is_array($rate) ? array('rate' => (float) $rate['rate'], 'rate_date' => (string) $rate['rate_date']) : false;
}

/**
 * Reads a period's completed and exported orders, order by order, into the
 * report's groups (base currency, minor units).
 *
 * @param string $from Y-m-d
 * @param string $to   Y-m-d
 * @return array pg_oss_report_empty() filled
 */
function pg_oss_report_read($from, $to)
{
    $report = pg_oss_report_empty();
    $start = (int) strtotime($from . ' 00:00:00');
    $end = (int) strtotime($to . ' 23:59:59');
    $with_rate = pg_schema_has('order_items', 'tax_rate');
    $with_country = pg_schema_has('order_items', 'tax_country');
    $clearcommerce = defined('ECOMMERCE_CREDIT_DEBIT_CARD') && (ECOMMERCE_CREDIT_DEBIT_CARD == true)
        && defined('ECOMMERCE_PAYMENT_GATEWAY') && (ECOMMERCE_PAYMENT_GATEWAY == 'ClearCommerce');

    $orders = (array) db_items(
        "SELECT id, discount, tax, order_date, billing_country, tax_exempt
         FROM orders
         WHERE status IN ('complete', 'exported')
           AND order_date >= '" . $start . "' AND order_date <= '" . $end . "'
         ORDER BY id");

    foreach ($orders as $order) {
        $day = date('Y-m-d', (int) $order['order_date']);
        $lines = array();

        foreach ((array) db_items(
            "SELECT order_items.id, order_items.price, order_items.quantity, order_items.tax, order_items.tax_total,
                    order_items.ship_to_id, order_items.recurring_payment_period, order_items.recurring_start_date,
                    ship_tos.country AS ship_country, products.shippable" . ($with_rate ? ", order_items.tax_rate" : "") . ($with_country ? ", order_items.tax_country" : "") . "
             FROM order_items
             LEFT JOIN ship_tos ON ship_tos.id = order_items.ship_to_id
             LEFT JOIN products ON products.id = order_items.product_id
             WHERE order_items.order_id = '" . (int) $order['id'] . "'" . pg_order_item_active_sql() . "
             ORDER BY order_items.id") as $item) {
            $where = pg_oss_line_country($with_country ? $item['tax_country'] : null, $item['ship_to_id'], (string) $item['ship_country'], (string) $order['billing_country'], $order['tax_exempt'], $day);
            $lines[(int) $item['id']] = array(
                'amount' => (int) $item['price'] * max(1, (int) $item['quantity']),
                'rate' => $with_rate ? $item['tax_rate'] : null,
                'tax' => pg_order_item_line_tax($item['tax_total'], $item['tax'], $item['quantity']),
                'quantity' => (int) $item['quantity'],
                'alone' => !pg_order_line_charged_today((string) $item['recurring_payment_period'] !== '', $item['recurring_start_date'], 0, $day, $clearcommerce),
                'country' => $where['country'],
                'stored' => $where['stored'],
                'goods' => !empty($item['shippable']),
            );
        }

        if ($lines) {
            $report = pg_oss_report_add($report, $order, $lines);
        }
    }

    return $report;
}
