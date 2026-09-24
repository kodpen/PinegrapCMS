<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * ERP - profit and loss, by month, product and account.
 *
 * A management view, not the accountant's books: what was sold, what the goods
 * cost, what else was spent, and what is left. Every figure is in the base
 * currency and without VAT.
 *
 * - Sales: the issued sales invoices of the period, less the returns issued
 *   in it; a cancelled document counts for nothing.
 * - Cost of goods: what the stock moves recorded for each line sold or taken
 *   back (includes/erp/stock.php). A line with a product but no move - an
 *   invoice issued before stock was kept - is costed at the product's average
 *   cost today, and the report says how much of the cost was estimated so. A
 *   line with no product (a service, shipping) has no cost of goods.
 * - Costs from purchase invoices: the lines of purchase invoices that carry no
 *   product (a service bought on an invoice), less what went back.
 * - Expenses: the expense receipts of the period (includes/erp/expenses.php),
 *   at their net, plus the VAT that is not taken back.
 * - On request, the completed orders no invoice has been raised for yet, by
 *   their order date: the lines the invoice would carry (erp_order_lines()),
 *   the goods costed at today's average. A store that invoices elsewhere, or
 *   later, otherwise sees almost no sales here.
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
 * A period from what the screen sent: a month, a quarter, a year or two
 * dates; this month when nothing usable was sent.
 *
 * @param array $input  range (month|quarter|year|dates), month (Y-m),
 *                      quarter (Y-Qn), year (Y), from, to
 * @return array ['from', 'to', 'range', 'error']
 */
function erp_profit_period($input)
{
    $range = (string) ($input['range'] ?? 'month');
    $today = date('Y-m-d');

    switch ($range) {
        case 'quarter':
            if (preg_match('/^(\d{4})-Q([1-4])$/', (string) ($input['quarter'] ?? ''), $parts)) {
                $first = sprintf('%04d-%02d-01', (int) $parts[1], ((int) $parts[2] - 1) * 3 + 1);
                return array('from' => $first, 'to' => date('Y-m-t', strtotime($first . ' +2 month')), 'range' => 'quarter', 'error' => '');
            }
            break;

        case 'year':
            if (preg_match('/^\d{4}$/', (string) ($input['year'] ?? ''))) {
                return array('from' => $input['year'] . '-01-01', 'to' => $input['year'] . '-12-31', 'range' => 'year', 'error' => '');
            }
            break;

        case 'dates':
            $from = (string) ($input['from'] ?? '');
            $to = (string) ($input['to'] ?? '');
            $valid = function ($date) {
                return preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date, $parts) && checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1]);
            };

            if (!$valid($from) || !$valid($to)) {
                return array('from' => date('Y-m-01'), 'to' => date('Y-m-t'), 'range' => 'month', 'error' => lang('Please enter a valid date.'));
            }
            if ($from > $to) {
                return array('from' => $to, 'to' => $from, 'range' => 'dates', 'error' => '');
            }
            // Two years at most: the report reads every line of the period.
            if ($to > date('Y-m-d', strtotime($from . ' +2 year -1 day'))) {
                return array('from' => $from, 'to' => date('Y-m-d', strtotime($from . ' +2 year -1 day')), 'range' => 'dates', 'error' => lang('The report covers at most two years at a time; the end date was brought forward.'));
            }
            return array('from' => $from, 'to' => $to, 'range' => 'dates', 'error' => '');

        default:
            if (preg_match('/^(\d{4})-(\d{2})$/', (string) ($input['month'] ?? ''), $parts) && ((int) $parts[2] >= 1) && ((int) $parts[2] <= 12)) {
                $first = $parts[1] . '-' . $parts[2] . '-01';
                return array('from' => $first, 'to' => date('Y-m-t', strtotime($first)), 'range' => 'month', 'error' => '');
            }
    }

    return array('from' => date('Y-m-01', strtotime($today)), 'to' => date('Y-m-t', strtotime($today)), 'range' => 'month', 'error' => '');
}

/**
 * Whether the completed orders without an invoice count in the report: the
 * choice the form sent, kept in this browser for a year, or the one kept.
 *
 * @param string|null $asked  '1' / '0' from the form, or null to read
 * @return bool
 */
function erp_profit_orders_choice($asked = null)
{
    $name = 'pg_erp_profit_orders';

    if ($asked !== null) {
        $on = ((string) $asked === '1');

        // The positional form: the options array needs PHP 7.3.
        if (!headers_sent()) {
            setcookie($name, $on ? '1' : '0', time() + 365 * 86400, '/', '', !empty($_SERVER['HTTPS']) && ($_SERVER['HTTPS'] !== 'off'), true);
        }

        return $on;
    }

    return ((string) ($_COOKIE[$name] ?? '') === '1');
}

/**
 * An empty set of figures.
 *
 * @return array
 */
function erp_profit_blank()
{
    return array(
        'sales' => 0,
        'order_sales' => 0,
        'returns' => 0,
        'revenue' => 0,
        'cogs' => 0,
        'cogs_estimated' => 0,
        'gross' => 0,
        'purchase_costs' => 0,
        'expenses' => 0,
        'net' => 0,
    );
}

/**
 * Work out the derived figures of one set.
 *
 * @param array $figures
 * @return array
 */
function erp_profit_finish($figures)
{
    $figures['revenue'] = $figures['sales'] + $figures['order_sales'] - $figures['returns'];
    $figures['gross'] = $figures['revenue'] - $figures['cogs'];
    $figures['net'] = $figures['gross'] - $figures['purchase_costs'] - $figures['expenses'];

    return $figures;
}

/**
 * The margin of a revenue, as a percentage with one decimal, or null when
 * there was no revenue to measure it against.
 *
 * @param int $profit
 * @param int $revenue
 * @return float|null
 */
function erp_profit_margin($profit, $revenue)
{
    return ((int) $revenue !== 0) ? round(((int) $profit) * 100 / ((int) $revenue), 1) : null;
}

/**
 * A margin as the screens write it: the sign in front of the percent sign
 * (-%12,5, not %-12,5), or a dash when there is none.
 *
 * @param float|null $margin
 * @return string
 */
function erp_profit_margin_text($margin)
{
    if ($margin === null) {
        return '—';
    }

    return (($margin < 0) ? '-' : '') . erp_percent_text(abs((float) $margin));
}

/**
 * The report.
 *
 * @param string $from     Y-m-d
 * @param string $to       Y-m-d
 * @param array  $options  orders (bool): count the completed orders that
 *                         have no invoice yet
 * @return array [
 *     'totals'   => figures,
 *     'months'   => 'Y-m' => figures,
 *     'products' => [...], sorted by gross profit, highest first,
 *     'accounts' => [...], sorted by gross profit, highest first,
 *     'expenses' => erp_expense_totals() of the period,
 *     'purchase_cost_lines' => int,
 *     'estimated_lines' => int, 'uncosted_lines' => int,
 *     'orders' => ['included' => bool, 'count' => int, 'net' => int],
 * ]
 */
function erp_profit_report($from, $to, $options = array())
{
    $from = (string) $from;
    $to = (string) $to;

    $totals = erp_profit_blank();
    $months = array();

    // Every month of the period, in order, so a month with nothing in it is
    // still a row.
    for ($month = substr($from, 0, 7); $month <= substr($to, 0, 7); $month = date('Y-m', strtotime($month . '-01 +1 month'))) {
        $months[$month] = erp_profit_blank();
    }

    $products = array();
    $accounts = array();
    $estimated_lines = 0;
    $uncosted_lines = 0;
    $purchase_cost_lines = 0;

    $documents = (array) db_items("SELECT i.id, i.direction, i.doc_type, i.issue_date, i.currency, i.exchange_rate, i.account_id,
            COALESCE(NULLIF(i.account_title, ''), a.title) AS account_title
        FROM erp_invoices i
        LEFT JOIN erp_accounts a ON a.id = i.account_id
        WHERE i.status NOT IN ('draft', 'cancelled') AND i.doc_type IN ('invoice', 'return')
          AND i.issue_date BETWEEN '" . escape($from) . "' AND '" . escape($to) . "'
        ORDER BY i.issue_date ASC, i.id ASC");

    $by_id = array();
    foreach ($documents as $document) {
        $by_id[(int) $document['id']] = $document;
    }

    $lines = array();
    $line_ids = array();
    $parent_ids = array();

    foreach (array_chunk(array_keys($by_id), 500) as $chunk) {
        foreach ((array) db_items("SELECT id, invoice_id, product_id, description, quantity, line_total, discount_amount, parent_line_id
            FROM erp_invoice_items WHERE invoice_id IN (" . implode(',', $chunk) . ")
            ORDER BY invoice_id ASC, line_no ASC, id ASC") as $line) {
            $lines[] = $line;
            $line_ids[] = (int) $line['id'];

            if ((int) $line['parent_line_id'] > 0) {
                $parent_ids[(int) $line['parent_line_id']] = (int) $line['parent_line_id'];
            }
        }
    }

    // What the stock moves recorded, for the lines and for the invoice lines
    // the returns point at.
    $moves = array();
    $stock = function_exists('erp_stock_ready') && erp_stock_ready();

    if ($stock) {
        foreach (array_chunk(array_values(array_unique(array_merge($line_ids, array_values($parent_ids)))), 500) as $chunk) {
            foreach ((array) db_items("SELECT line_id, kind, cost_total FROM erp_stock_moves
                WHERE kind IN ('sale', 'sales_return') AND line_id IN (" . implode(',', $chunk) . ")") as $move) {
                $moves[(string) $move['kind'] . ':' . (int) $move['line_id']] = (int) $move['cost_total'];
            }
        }
    }

    $parents = array();
    foreach (array_chunk(array_values($parent_ids), 500) as $chunk) {
        foreach ((array) db_items("SELECT id, product_id, quantity FROM erp_invoice_items WHERE id IN (" . implode(',', $chunk) . ")") as $parent) {
            $parents[(int) $parent['id']] = $parent;
        }
    }

    // A product's average cost today, for a line whose move is missing.
    $averages = array();
    $average = function ($product_id) use (&$averages, $stock) {
        $product_id = (int) $product_id;

        if (!array_key_exists($product_id, $averages)) {
            $cost = $stock ? erp_stock_cost($product_id) : null;
            $unit = is_array($cost) ? (int) $cost['avg_cost'] : 0;

            if (($unit <= 0) && is_array($cost)) {
                $unit = (int) $cost['last_cost'];
            }

            $averages[$product_id] = ($unit > 0) ? $unit : null;
        }

        return $averages[$product_id];
    };

    $product_ids = array();

    foreach ($lines as $line) {
        $document = $by_id[(int) $line['invoice_id']];
        $month = substr((string) $document['issue_date'], 0, 7);
        $net = erp_accountant_base((int) $line['line_total'] - (int) $line['discount_amount'], $document);
        $sales = ((string) $document['direction'] === 'sales');
        $return = ((string) $document['doc_type'] === 'return');
        $product_id = (int) $line['product_id'];
        $quantity = (float) $line['quantity'];

        if (!$sales) {
            // A purchase line with a product goes into stock and comes back
            // as cost of goods when sold; one without is a cost now.
            if ($product_id === 0) {
                $amount = $return ? -$net : $net;
                $totals['purchase_costs'] += $amount;
                $months[$month]['purchase_costs'] += $amount;
                $purchase_cost_lines++;
            }
            continue;
        }

        // The cost of the goods on this line.
        $cost = 0;
        $estimated = false;
        $costed = true;

        if ($return && ((int) $line['parent_line_id'] > 0) && isset($parents[(int) $line['parent_line_id']])) {
            $parent = $parents[(int) $line['parent_line_id']];
            $product_id = ($product_id > 0) ? $product_id : (int) $parent['product_id'];
        }

        if ($product_id > 0) {
            $key = ($return ? 'sales_return:' : 'sale:') . (int) $line['id'];

            if (isset($moves[$key])) {
                $cost = $moves[$key];
            } elseif ($return && isset($parent) && isset($moves['sale:' . (int) $parent['id']]) && ((float) $parent['quantity'] > 0)) {
                $cost = (int) round($moves['sale:' . (int) $parent['id']] * $quantity / (float) $parent['quantity']);
            } elseif (($unit = $average($product_id)) !== null) {
                $cost = (int) round($unit * $quantity);
                $estimated = true;
            } else {
                $costed = false;
            }
        }

        unset($parent);

        if ($estimated) {
            $estimated_lines++;
        }
        if (!$costed) {
            $uncosted_lines++;
        }

        $sign = $return ? -1 : 1;

        if ($return) {
            $totals['returns'] += $net;
            $months[$month]['returns'] += $net;
        } else {
            $totals['sales'] += $net;
            $months[$month]['sales'] += $net;
        }

        $totals['cogs'] += $sign * $cost;
        $months[$month]['cogs'] += $sign * $cost;

        if ($estimated) {
            $totals['cogs_estimated'] += $sign * $cost;
            $months[$month]['cogs_estimated'] += $sign * $cost;
        }

        // By product; the lines with none share one row.
        $product_key = ($product_id > 0) ? $product_id : 0;

        if (!isset($products[$product_key])) {
            $products[$product_key] = array('product_id' => $product_key, 'quantity' => 0.0, 'revenue' => 0, 'cost' => 0, 'estimated' => 0, 'uncosted' => 0, 'lines' => 0);
        }

        $products[$product_key]['quantity'] += $sign * $quantity;
        $products[$product_key]['revenue'] += $sign * $net;
        $products[$product_key]['cost'] += $sign * $cost;
        $products[$product_key]['estimated'] += $estimated ? 1 : 0;
        $products[$product_key]['uncosted'] += $costed ? 0 : 1;
        $products[$product_key]['lines']++;

        if ($product_id > 0) {
            $product_ids[$product_id] = $product_id;
        }

        // By account.
        $account_key = (int) $document['account_id'];

        if (!isset($accounts[$account_key])) {
            $accounts[$account_key] = array('account_id' => $account_key, 'title' => (string) $document['account_title'], 'documents' => array(), 'revenue' => 0, 'cost' => 0, 'estimated' => 0);
        }

        $accounts[$account_key]['documents'][(int) $document['id']] = true;
        $accounts[$account_key]['revenue'] += $sign * $net;
        $accounts[$account_key]['cost'] += $sign * $cost;
        $accounts[$account_key]['estimated'] += $estimated ? 1 : 0;
    }

    // The completed orders no invoice stands for yet, by their order date.
    $orders_info = erp_profit_uninvoiced_orders($from, $to);
    $orders_info['included'] = !empty($options['orders']);

    if ($orders_info['included'] && ($orders_info['count'] > 0)) {
        foreach (erp_profit_order_lines($from, $to) as $line) {
            $month = $line['month'];
            $net = $line['net'];
            $product_id = $line['product_id'];
            $quantity = $line['quantity'];
            $cost = 0;
            $costed = true;

            if ($product_id > 0) {
                $unit = $average($product_id);

                if ($unit !== null) {
                    $cost = (int) round($unit * $quantity);
                } else {
                    $costed = false;
                    $uncosted_lines++;
                }
            }

            $totals['order_sales'] += $net;
            $totals['cogs'] += $cost;
            $totals['cogs_estimated'] += $cost;

            if (isset($months[$month])) {
                $months[$month]['order_sales'] += $net;
                $months[$month]['cogs'] += $cost;
                $months[$month]['cogs_estimated'] += $cost;
            }

            if ($cost !== 0) {
                $estimated_lines++;
            }

            $product_key = ($product_id > 0) ? $product_id : 0;

            if (!isset($products[$product_key])) {
                $products[$product_key] = array('product_id' => $product_key, 'quantity' => 0.0, 'revenue' => 0, 'cost' => 0, 'estimated' => 0, 'uncosted' => 0, 'lines' => 0);
            }

            $products[$product_key]['quantity'] += $quantity;
            $products[$product_key]['revenue'] += $net;
            $products[$product_key]['cost'] += $cost;
            $products[$product_key]['estimated'] += ($cost !== 0) ? 1 : 0;
            $products[$product_key]['uncosted'] += $costed ? 0 : 1;
            $products[$product_key]['lines']++;

            if ($product_id > 0) {
                $product_ids[$product_id] = $product_id;
            }

            // An order with an account joins that account's row; one without
            // gets a row of its own customer.
            $account_key = ($line['account_id'] > 0) ? $line['account_id'] : ('c' . $line['contact_id']);

            if (!isset($accounts[$account_key])) {
                $accounts[$account_key] = array('account_id' => max(0, $line['account_id']), 'title' => $line['customer'], 'documents' => array(), 'revenue' => 0, 'cost' => 0, 'estimated' => 0);
            }

            $accounts[$account_key]['documents']['o' . $line['order_id']] = true;
            $accounts[$account_key]['revenue'] += $net;
            $accounts[$account_key]['cost'] += $cost;
            $accounts[$account_key]['estimated'] += ($cost !== 0) ? 1 : 0;
        }
    }

    // Expenses, month by month.
    $expenses = function_exists('erp_expense_totals') ? erp_expense_totals($from, $to) : array('categories' => array(), 'total' => array('cost' => 0));

    if (function_exists('erp_expenses_ready') && erp_expenses_ready()) {
        foreach ((array) db_items("SELECT DATE_FORMAT(expense_date, '%Y-%m') AS month,
                SUM(total_base - IF(tax_deductible = 1, tax_base, 0)) AS cost
            FROM erp_expenses
            WHERE status <> 'cancelled' AND expense_date BETWEEN '" . escape($from) . "' AND '" . escape($to) . "'
            GROUP BY DATE_FORMAT(expense_date, '%Y-%m')") as $row) {
            if (isset($months[(string) $row['month']])) {
                $months[(string) $row['month']]['expenses'] += (int) $row['cost'];
            }
            $totals['expenses'] += (int) $row['cost'];
        }
    }

    foreach ($months as $key => $figures) {
        $months[$key] = erp_profit_finish($figures);
    }

    $totals = erp_profit_finish($totals);

    // Names for the products.
    $names = array();
    foreach (array_chunk(array_values($product_ids), 500) as $chunk) {
        foreach ((array) db_items("SELECT id, name, short_description FROM products WHERE id IN (" . implode(',', $chunk) . ")") as $product) {
            $names[(int) $product['id']] = $product;
        }
    }

    foreach ($products as $key => $product) {
        $products[$key]['sku'] = isset($names[$key]) ? (string) $names[$key]['name'] : '';
        $products[$key]['name'] = ($key === 0)
            ? lang('Lines without a product (services, shipping)')
            : (isset($names[$key]) ? (((string) $names[$key]['short_description'] !== '') ? (string) $names[$key]['short_description'] : (string) $names[$key]['name']) : ('#' . $key));
        $products[$key]['profit'] = $product['revenue'] - $product['cost'];
        $products[$key]['margin'] = erp_profit_margin($products[$key]['profit'], $product['revenue']);
    }

    foreach ($accounts as $key => $account) {
        $accounts[$key]['documents'] = count($account['documents']);
        $accounts[$key]['profit'] = $account['revenue'] - $account['cost'];
        $accounts[$key]['margin'] = erp_profit_margin($accounts[$key]['profit'], $account['revenue']);

        if ($accounts[$key]['title'] === '') {
            $accounts[$key]['title'] = ((int) $account['account_id'] > 0) ? ('#' . (int) $account['account_id']) : lang('No account');
        }
    }

    $by_profit = function ($a, $b) {
        return ($b['profit'] <=> $a['profit']) ?: ($b['revenue'] <=> $a['revenue']);
    };

    uasort($products, $by_profit);
    uasort($accounts, $by_profit);

    return array(
        'from' => $from,
        'to' => $to,
        'totals' => $totals,
        'months' => $months,
        'products' => array_values($products),
        'accounts' => array_values($accounts),
        'expenses' => $expenses,
        'purchase_cost_lines' => $purchase_cost_lines,
        'estimated_lines' => $estimated_lines,
        'uncosted_lines' => $uncosted_lines,
        'orders' => $orders_info,
    );
}

/**
 * The SQL that picks the completed orders of a period no invoice stands for:
 * a finished sale (erp_order_billable_sql()) whose invoice link is empty - a
 * cancelled invoice empties it again, and counts for nothing itself.
 *
 * @param string $from
 * @param string $to
 * @return string  WHERE condition over orders
 */
function erp_profit_orders_where($from, $to)
{
    $start = strtotime((string) $from . ' 00:00:00');
    $end = strtotime((string) $to . ' 23:59:59');

    return erp_order_billable_sql('orders.status') . " AND orders.erp_invoice_id = 0
        AND orders.order_date BETWEEN '" . (int) $start . "' AND '" . (int) $end . "'";
}

/**
 * How many completed orders of the period have no invoice, and what they
 * come to without tax.
 *
 * @param string $from
 * @param string $to
 * @return array ['count' => int, 'net' => int]
 */
function erp_profit_uninvoiced_orders($from, $to)
{
    if (!function_exists('waf_table_has_column') || !waf_table_has_column('orders', 'erp_invoice_id')) {
        return array('count' => 0, 'net' => 0);
    }

    $row = db_item("SELECT COUNT(*) AS count, COALESCE(SUM(orders.total + orders.gift_card_discount - COALESCE(orders.tax, 0)), 0) AS net
        FROM orders WHERE " . erp_profit_orders_where($from, $to));

    return array('count' => is_array($row) ? (int) $row['count'] : 0, 'net' => is_array($row) ? (int) $row['net'] : 0);
}

/**
 * The lines the uninvoiced orders of a period would be invoiced with, one
 * entry per line: order, month, product, quantity and net (without tax, in
 * the base currency: an order is kept in it).
 *
 * @param string $from
 * @param string $to
 * @return array
 */
function erp_profit_order_lines($from, $to)
{
    $orders = (array) db_items("SELECT orders.*,
            COALESCE(NULLIF(orders.billing_company, ''), TRIM(CONCAT(orders.billing_first_name, ' ', orders.billing_last_name))) AS customer
        FROM orders WHERE " . erp_profit_orders_where($from, $to) . "
        ORDER BY orders.order_date ASC, orders.id ASC");

    if (empty($orders)) {
        return array();
    }

    $items = array();
    $ids = array();
    foreach ($orders as $order) {
        $ids[] = (int) $order['id'];
    }

    foreach (array_chunk($ids, 500) as $chunk) {
        foreach ((array) db_items("SELECT order_items.*, products.short_description, products.tax_rate AS product_tax_rate
            FROM order_items
            LEFT JOIN products ON order_items.product_id = products.id
            WHERE order_items.order_id IN (" . implode(',', $chunk) . ") AND order_items.saved_for_later = 0
            ORDER BY order_items.id ASC") as $item) {
            $items[(int) $item['order_id']][] = $item;
        }
    }

    $out = array();

    foreach ($orders as $order) {
        $order_items = $items[(int) $order['id']] ?? array();

        if (empty($order_items)) {
            continue;
        }

        $built = erp_order_lines($order, $order_items);
        $month = date('Y-m', (int) $order['order_date']);
        $customer = trim((string) $order['customer']);

        foreach ($built['lines'] as $line) {
            $out[] = array(
                'order_id' => (int) $order['id'],
                'account_id' => (int) ($order['erp_account_id'] ?? 0),
                'contact_id' => (int) ($order['contact_id'] ?? 0),
                'customer' => ($customer !== '') ? $customer : ('#' . (string) $order['order_number']),
                'month' => $month,
                'product_id' => (int) ($line['product_id'] ?? 0),
                'quantity' => (float) ($line['quantity'] ?? 0),
                'net' => (int) $line['line_total'] - (int) $line['discount_amount'],
            );
        }
    }

    return $out;
}

/**
 * The report as a workbook: the summary, the months, the products, the
 * accounts and the expense categories.
 *
 * @param array  $report  erp_profit_report()
 * @param string $path    Where to write the .xlsx
 * @return bool
 */
function erp_profit_workbook($report, $path)
{
    $base = erp_base_currency();
    $money = function ($label, $width = 16) use ($base) {
        return array('label' => $label . ' (' . $base . ')', 'type' => 'money', 'width' => $width);
    };
    $totals = $report['totals'];

    $summary_rows = array(
        array(lang('Period'), array('t' => 'date', 'v' => (string) $report['from'])),
        array('', array('t' => 'date', 'v' => (string) $report['to'])),
        array(lang('Sales'), $totals['sales']),
        array(lang('Completed orders without an invoice'), $totals['order_sales']),
        array(lang('Returns'), -$totals['returns']),
        array(lang('Net sales'), $totals['revenue']),
        array(lang('Cost of goods sold'), -$totals['cogs']),
        array(lang('Gross profit'), $totals['gross']),
        array(lang('Costs from purchase invoices'), -$totals['purchase_costs']),
        array(lang('Expenses'), -$totals['expenses']),
        array(lang('Net profit'), array('t' => 'money_bold', 'v' => $totals['net'])),
    );

    if ((int) $totals['cogs_estimated'] !== 0) {
        $summary_rows[] = array(lang('Of the cost of goods, estimated at today\'s average cost'), $totals['cogs_estimated']);
    }

    $sheets = array();

    $sheets['summary'] = array('title' => lang('Profit and loss'), 'columns' => array(
        array('label' => lang('Line item'), 'type' => 'text', 'width' => 44),
        $money(lang('Amount'), 20),
    ), 'rows' => $summary_rows, 'filter' => false);

    $month_rows = array();
    foreach ($report['months'] as $month => $figures) {
        $month_rows[] = array($month, $figures['revenue'], $figures['cogs'], $figures['gross'], $figures['purchase_costs'], $figures['expenses'], $figures['net']);
    }

    $sheets['months'] = array('title' => lang('By month'), 'columns' => array(
        array('label' => lang('Month'), 'type' => 'text', 'width' => 10),
        $money(lang('Net sales')),
        $money(lang('Cost of goods sold')),
        $money(lang('Gross profit')),
        $money(lang('Costs from purchase invoices')),
        $money(lang('Expenses')),
        $money(lang('Net profit')),
    ), 'rows' => $month_rows);

    $product_rows = array();
    foreach ($report['products'] as $product) {
        $product_rows[] = array(
            (string) $product['sku'],
            (string) $product['name'],
            (float) $product['quantity'],
            $product['revenue'],
            $product['cost'],
            $product['profit'],
            ($product['margin'] !== null) ? (float) $product['margin'] : '',
            ((int) $product['estimated'] > 0) ? lang('Estimated') : '',
        );
    }

    $sheets['products'] = array('title' => lang('By product'), 'columns' => array(
        array('label' => lang('Code'), 'type' => 'text', 'width' => 16),
        array('label' => lang('Product'), 'type' => 'text', 'width' => 40),
        array('label' => lang('Quantity'), 'type' => 'number', 'width' => 10),
        $money(lang('Net sales')),
        $money(lang('Cost')),
        $money(lang('Gross profit')),
        array('label' => lang('Margin (%)'), 'type' => 'number', 'width' => 10),
        array('label' => lang('Cost'), 'type' => 'text', 'width' => 12),
    ), 'rows' => $product_rows);

    $account_rows = array();
    foreach ($report['accounts'] as $account) {
        $account_rows[] = array(
            (string) $account['title'],
            (int) $account['documents'],
            $account['revenue'],
            $account['cost'],
            $account['profit'],
            ($account['margin'] !== null) ? (float) $account['margin'] : '',
        );
    }

    $sheets['accounts'] = array('title' => lang('By account'), 'columns' => array(
        array('label' => lang('Account'), 'type' => 'text', 'width' => 40),
        array('label' => lang('Documents'), 'type' => 'int', 'width' => 10),
        $money(lang('Net sales')),
        $money(lang('Cost')),
        $money(lang('Gross profit')),
        array('label' => lang('Margin (%)'), 'type' => 'number', 'width' => 10),
    ), 'rows' => $account_rows);

    $expense_rows = array();
    foreach ((array) ($report['expenses']['categories'] ?? array()) as $category) {
        $expense_rows[] = array((string) $category['name'], (string) $category['code'], (int) $category['count'], $category['net'], $category['tax'], $category['total'], $category['cost']);
    }

    $sheets['expenses'] = array('title' => lang('Expenses'), 'columns' => array(
        array('label' => lang('Category'), 'type' => 'text', 'width' => 32),
        array('label' => lang('Code'), 'type' => 'text', 'width' => 12),
        array('label' => lang('Count'), 'type' => 'int', 'width' => 8),
        $money(lang('Net')),
        $money(erp_tax_label('tax')),
        $money(lang('Total')),
        $money(lang('Cost')),
    ), 'rows' => $expense_rows);

    return erp_accountant_write_workbook($sheets, $path);
}
