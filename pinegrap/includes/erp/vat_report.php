<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * ERP - the tax report of a period: the tax the sales carried less what the
 * sales returns took back (calculated), the tax of the purchases less what
 * went back to suppliers and the tax on expenses that is taken back
 * (deductible), by kind of document and rate, with the second tax and the
 * VAT withholding apart.
 *
 * The accountant's pack (includes/erp/accountant.php) builds its VAT sheet
 * from the same helpers, so the screen, its workbook and the pack give the
 * same figures. Everything is in the base currency; cancelled documents and
 * drafts count for nothing; an expense whose tax is not taken back is a cost
 * and stays out of the deductible tax.
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
 * An empty collection of tax figures for erp_vat_add_document() and
 * erp_vat_add_expense().
 *
 * @return array ['vat' => array, 'withholding' => array]
 */
function erp_vat_bucket()
{
    return array('vat' => array(), 'withholding' => array());
}

/**
 * Adds one issued document's lines to the tax figures: the tax by rate, the
 * second tax by rate and the withholding by code and rate, in the base
 * currency. A cancelled document is not to be added.
 *
 * @param array  $bucket    erp_vat_bucket(), changed in place
 * @param string $key       sales | sales_return | purchase | purchase_return
 * @param array  $document  erp_invoices row (id, currency, exchange_rate)
 * @param array  $lines     erp_accountant_lines() rows of the document
 * @return void
 */
function erp_vat_add_document(&$bucket, $key, $document, $lines)
{
    $id = (int) $document['id'];

    foreach ($lines as $line) {
        $net = erp_accountant_base((int) $line['line_total'] - (int) $line['discount_amount'], $document);
        $tax = erp_accountant_base((int) $line['tax_total'] - (int) $line['tax2_amount'], $document);
        $rate = number_format((float) $line['tax_rate'], 3, '.', '');
        $vat_key = $key . '|vat|' . $rate;

        if (!isset($bucket['vat'][$vat_key])) {
            $bucket['vat'][$vat_key] = array('key' => $key, 'tax' => 'vat', 'rate' => (float) $line['tax_rate'], 'net' => 0, 'tax_amount' => 0, 'documents' => array());
        }

        $bucket['vat'][$vat_key]['net'] += $net;
        $bucket['vat'][$vat_key]['tax_amount'] += $tax;
        $bucket['vat'][$vat_key]['documents'][$id] = true;

        if ((float) $line['tax2_rate'] > 0) {
            $rate2 = number_format((float) $line['tax2_rate'], 3, '.', '');
            $tax2_key = $key . '|tax2|' . $rate2;

            if (!isset($bucket['vat'][$tax2_key])) {
                $bucket['vat'][$tax2_key] = array('key' => $key, 'tax' => 'tax2', 'rate' => (float) $line['tax2_rate'], 'net' => 0, 'tax_amount' => 0, 'documents' => array());
            }

            $bucket['vat'][$tax2_key]['net'] += $net;
            $bucket['vat'][$tax2_key]['tax_amount'] += erp_accountant_base((int) $line['tax2_amount'], $document);
            $bucket['vat'][$tax2_key]['documents'][$id] = true;
        }

        if ((trim((string) $line['withholding_code']) !== '') && ((int) $line['withholding_amount'] !== 0)) {
            $w_key = $key . '|' . $line['withholding_code'] . '|' . number_format((float) $line['withholding_rate'], 3, '.', '');

            if (!isset($bucket['withholding'][$w_key])) {
                $bucket['withholding'][$w_key] = array('key' => $key, 'code' => (string) $line['withholding_code'], 'rate' => (float) $line['withholding_rate'], 'amount' => 0, 'net' => 0, 'tax_amount' => 0, 'documents' => array());
            }

            $bucket['withholding'][$w_key]['amount'] += erp_accountant_base((int) $line['withholding_amount'], $document);
            $bucket['withholding'][$w_key]['net'] += $net;
            $bucket['withholding'][$w_key]['tax_amount'] += $tax;
            $bucket['withholding'][$w_key]['documents'][$id] = true;
        }
    }
}

/**
 * Adds an expense's tax to the deductible tax when it is taken back.
 *
 * @param array $bucket   erp_vat_bucket(), changed in place
 * @param array $expense  erp_expenses row (id, status, tax_deductible,
 *                        tax_rate, net_base, tax_base)
 * @return int  The tax added, in the base currency (0 when none)
 */
function erp_vat_add_expense(&$bucket, $expense)
{
    if (((string) $expense['status'] === 'cancelled') || ((int) $expense['tax_deductible'] !== 1) || ((int) $expense['tax_base'] === 0)) {
        return 0;
    }

    $rate = number_format((float) $expense['tax_rate'], 3, '.', '');
    $vat_key = 'expense|vat|' . $rate;

    if (!isset($bucket['vat'][$vat_key])) {
        $bucket['vat'][$vat_key] = array('key' => 'expense', 'tax' => 'vat', 'rate' => (float) $expense['tax_rate'], 'net' => 0, 'tax_amount' => 0, 'documents' => array());
    }

    $bucket['vat'][$vat_key]['net'] += (int) $expense['net_base'];
    $bucket['vat'][$vat_key]['tax_amount'] += (int) $expense['tax_base'];
    $bucket['vat'][$vat_key]['documents']['e' . (int) $expense['id']] = true;

    return (int) $expense['tax_base'];
}

/**
 * The tax by rate in reading order: sales, sales returns, purchases, returns
 * to suppliers, expenses; within each the tax before the second tax, and the
 * lower rate first.
 *
 * @param array $vat  erp_vat_bucket()['vat']
 * @return array
 */
function erp_vat_sorted($vat)
{
    ksort($vat);
    $order = array_flip(array('sales', 'sales_return', 'purchase', 'purchase_return', 'expense'));

    uasort($vat, function ($a, $b) use ($order) {
        if ($order[$a['key']] !== $order[$b['key']]) {
            return $order[$a['key']] - $order[$b['key']];
        }
        if ($a['tax'] !== $b['tax']) {
            return strcmp($a['tax'], $b['tax']);
        }
        return ($a['rate'] < $b['rate']) ? -1 : (($a['rate'] > $b['rate']) ? 1 : 0);
    });

    return $vat;
}

/**
 * The words for the kinds of documents the report groups by.
 *
 * @return array
 */
function erp_vat_kind_labels()
{
    return array(
        'sales' => lang('Sales invoices'),
        'sales_return' => lang('Sales returns'),
        'purchase' => lang('Purchase invoices'),
        'purchase_return' => lang('Returns to suppliers'),
        'expense' => lang('Expenses'),
    );
}

/**
 * The name the report gives the second tax.
 *
 * @return string
 */
function erp_vat_tax2_label()
{
    return (erp_tax2_name() !== '') ? erp_tax2_name() : lang('Second tax');
}

/**
 * The tax report of a period.
 *
 * @param string $from  Y-m-d
 * @param string $to    Y-m-d
 * @return array [
 *     'from', 'to',
 *     'kinds'       => kind => [count, net, vat, tax2, withholding, total],
 *     'rates'       => erp_vat_sorted() rows (documents is a count),
 *     'withholding' => rows by kind, code and rate (net, tax_amount, amount, documents),
 *     'totals'      => [calculated, deductible_purchases, deductible_expenses,
 *                       deductible, difference, withheld_sales, withheld_purchases,
 *                       tax2_calculated, tax2_deductible, expense_not_deductible],
 *                       difference by erp_vat_difference(),
 *     'months'      => Y-m => [calculated, withheld_sales, deductible_purchases,
 *                       deductible_expenses, difference],
 *     'net_withholding' => bool, erp_vat_net_withholding(),
 *     'documents'   => the period's documents and expenses, oldest first,
 *     'cancelled'   => count, 'has_tax2' => bool,
 *     'orders'      => [count, tax] completed orders without an invoice,
 * ]
 */
function erp_vat_report($from, $to)
{
    $documents = erp_accountant_documents($from, $to);
    $lines = erp_accountant_lines($documents);
    $bucket = erp_vat_bucket();
    $blank = array('count' => 0, 'net' => 0, 'vat' => 0, 'tax2' => 0, 'withholding' => 0, 'total' => 0);
    $kinds = array('sales' => $blank, 'sales_return' => $blank, 'purchase' => $blank, 'purchase_return' => $blank, 'expense' => $blank);
    $month_blank = array('calculated' => 0, 'withheld_sales' => 0, 'deductible_purchases' => 0, 'deductible_expenses' => 0, 'difference' => 0);
    $months = array();
    $list = array();
    $cancelled_count = 0;
    $not_deductible = 0;
    $has_tax2 = function_exists('erp_tax2_enabled') && erp_tax2_enabled();

    // Every month of the period, so an empty one shows as nothing rather than
    // as missing.
    for ($month = substr($from, 0, 7); $month <= substr($to, 0, 7); $month = date('Y-m', strtotime($month . '-01 +1 month'))) {
        $months[$month] = $month_blank;
    }

    foreach ($documents as $document) {
        $id = (int) $document['id'];
        $document_lines = $lines[$id] ?? array();
        $figures = erp_accountant_document_figures($document, $document_lines);
        $cancelled = ((string) $document['status'] === 'cancelled');
        $is_return = ((string) $document['doc_type'] === 'return');
        $direction = ((string) $document['direction'] === 'purchase') ? 'purchase' : 'sales';
        $key = $direction . ($is_return ? '_return' : '');
        $month = substr((string) $document['issue_date'], 0, 7);

        if ((int) $figures['tax2'] !== 0) {
            $has_tax2 = true;
        }

        $rates = array();
        foreach ($document_lines as $line) {
            $rates[number_format((float) $line['tax_rate'], 3, '.', '')] = (float) $line['tax_rate'];
        }
        ksort($rates);

        $titled = (trim((string) $document['account_title']) !== '');
        $list[] = array(
            'type' => 'invoice',
            'id' => $id,
            'kind' => $key,
            'date' => (string) $document['issue_date'],
            'number' => (string) $document['full_number'],
            'currency' => strtoupper((string) $document['currency']),
            'account' => $titled ? (string) $document['account_title'] : (string) $document['live_title'],
            'tax_number' => $titled ? (string) $document['account_tax_number'] : (string) $document['live_tax_number'],
            'rates' => array_values($rates),
            'net' => $figures['net_base'],
            'vat' => $figures['vat_base'],
            'tax2' => $figures['tax2_base'],
            'withholding' => $figures['withholding_base'],
            'total' => $figures['total_base'],
            'deductible' => true,
            'cancelled' => $cancelled,
        );

        if ($cancelled) {
            $cancelled_count++;
            continue;
        }

        $kinds[$key]['count']++;
        $kinds[$key]['net'] += $figures['net_base'];
        $kinds[$key]['vat'] += $figures['vat_base'];
        $kinds[$key]['tax2'] += $figures['tax2_base'];
        $kinds[$key]['withholding'] += $figures['withholding_base'];
        $kinds[$key]['total'] += $figures['total_base'];

        erp_vat_add_document($bucket, $key, $document, $document_lines);

        if (isset($months[$month])) {
            $sign = $is_return ? -1 : 1;

            if ($direction === 'sales') {
                $months[$month]['calculated'] += $sign * $figures['vat_base'];
                $months[$month]['withheld_sales'] += $sign * $figures['withholding_base'];
            } else {
                $months[$month]['deductible_purchases'] += $sign * $figures['vat_base'];
            }
        }
    }

    if (function_exists('erp_expenses_ready') && erp_expenses_ready()) {
        foreach (array_reverse(erp_expenses_list(array('from' => $from, 'to' => $to, 'status' => 'all', 'limit' => 5000))) as $expense) {
            $cancelled = ((string) $expense['status'] === 'cancelled');
            $deductible = ((int) $expense['tax_deductible'] === 1);
            $month = substr((string) $expense['expense_date'], 0, 7);

            $list[] = array(
                'type' => 'expense',
                'id' => (int) $expense['id'],
                'kind' => 'expense',
                'date' => (string) $expense['expense_date'],
                'number' => (string) $expense['document_no'],
                'currency' => strtoupper((string) $expense['currency']),
                'account' => ((string) $expense['supplier'] !== '') ? (string) $expense['supplier'] : (string) $expense['category_name'],
                'tax_number' => (string) $expense['supplier_tax_number'],
                'rates' => array((float) $expense['tax_rate']),
                'net' => (int) $expense['net_base'],
                'vat' => (int) $expense['tax_base'],
                'tax2' => 0,
                'withholding' => 0,
                'total' => (int) $expense['total_base'],
                'deductible' => $deductible,
                'cancelled' => $cancelled,
            );

            if ($cancelled) {
                $cancelled_count++;
                continue;
            }

            $kinds['expense']['count']++;
            $kinds['expense']['net'] += (int) $expense['net_base'];
            $kinds['expense']['vat'] += (int) $expense['tax_base'];
            $kinds['expense']['total'] += (int) $expense['total_base'];

            $added = erp_vat_add_expense($bucket, $expense);

            if (!$deductible) {
                $not_deductible += (int) $expense['tax_base'];
            }

            if (isset($months[$month])) {
                $months[$month]['deductible_expenses'] += $added;
            }
        }
    }

    // Expenses and documents of one day, in the order they were written.
    usort($list, function ($a, $b) {
        if ($a['date'] !== $b['date']) {
            return strcmp($a['date'], $b['date']);
        }
        if ($a['type'] !== $b['type']) {
            return strcmp($a['type'], $b['type']);
        }
        return $a['id'] - $b['id'];
    });

    foreach ($months as $month => $figures) {
        $months[$month]['difference'] = erp_vat_difference($figures['calculated'], $figures['deductible_purchases'] + $figures['deductible_expenses'], $figures['withheld_sales']);
    }

    $rates = array();
    foreach (erp_vat_sorted($bucket['vat']) as $item) {
        $item['documents'] = count($item['documents']);
        $rates[] = $item;
    }

    $withholding = array();
    foreach ($bucket['withholding'] as $item) {
        $item['documents'] = count($item['documents']);
        $withholding[] = $item;
    }

    $order = array_flip(array('sales', 'sales_return', 'purchase', 'purchase_return'));
    usort($withholding, function ($a, $b) use ($order) {
        if ($order[$a['key']] !== $order[$b['key']]) {
            return $order[$a['key']] - $order[$b['key']];
        }
        if ($a['code'] !== $b['code']) {
            return strcmp($a['code'], $b['code']);
        }
        return ($a['rate'] < $b['rate']) ? -1 : (($a['rate'] > $b['rate']) ? 1 : 0);
    });

    $expense_tax = 0;
    foreach ($months as $figures) {
        $expense_tax += $figures['deductible_expenses'];
    }

    $calculated = $kinds['sales']['vat'] - $kinds['sales_return']['vat'];
    $deductible_purchases = $kinds['purchase']['vat'] - $kinds['purchase_return']['vat'];
    $withheld_sales = $kinds['sales']['withholding'] - $kinds['sales_return']['withholding'];

    return array(
        'from' => $from,
        'to' => $to,
        'kinds' => $kinds,
        'rates' => $rates,
        'withholding' => $withholding,
        'totals' => array(
            'calculated' => $calculated,
            'deductible_purchases' => $deductible_purchases,
            'deductible_expenses' => $expense_tax,
            'deductible' => $deductible_purchases + $expense_tax,
            'difference' => erp_vat_difference($calculated, $deductible_purchases + $expense_tax, $withheld_sales),
            'withheld_sales' => $withheld_sales,
            'withheld_purchases' => $kinds['purchase']['withholding'] - $kinds['purchase_return']['withholding'],
            'tax2_calculated' => $kinds['sales']['tax2'] - $kinds['sales_return']['tax2'],
            'tax2_deductible' => $kinds['purchase']['tax2'] - $kinds['purchase_return']['tax2'],
            'expense_not_deductible' => $not_deductible,
        ),
        'months' => $months,
        'documents' => $list,
        'cancelled' => $cancelled_count,
        'has_tax2' => $has_tax2,
        'net_withholding' => erp_vat_net_withholding(),
        'orders' => erp_vat_uninvoiced_orders($from, $to),
    );
}

/**
 * The completed orders of a period no invoice has been raised for, and the
 * tax they carry: a sale the report cannot see.
 *
 * @param string $from
 * @param string $to
 * @return array ['count', 'tax']
 */
function erp_vat_uninvoiced_orders($from, $to)
{
    if (!function_exists('waf_table_has_column') || !waf_table_has_column('orders', 'erp_invoice_id')) {
        return array('count' => 0, 'tax' => 0);
    }

    $row = db_item("SELECT COUNT(*) AS count, COALESCE(SUM(COALESCE(orders.tax, 0)), 0) AS tax
        FROM orders WHERE " . erp_profit_orders_where($from, $to));

    return array('count' => is_array($row) ? (int) $row['count'] : 0, 'tax' => is_array($row) ? (int) $row['tax'] : 0);
}

/**
 * The report as a workbook: the summary, the tax by rate, the withholding,
 * the months and the documents.
 *
 * @param array  $report  erp_vat_report()
 * @param string $path    Where to write the .xlsx
 * @return bool
 */
function erp_vat_workbook($report, $path)
{
    $base = erp_base_currency();
    $money = function ($label, $width = 16) use ($base) {
        return array('label' => $label . ' (' . $base . ')', 'type' => 'money', 'width' => $width);
    };
    $labels = erp_vat_kind_labels();
    $tax_label = erp_tax_label('tax');
    $tax2_label = erp_vat_tax2_label();
    $totals = $report['totals'];

    $summary_rows = array(
        array(lang('Period'), array('t' => 'date', 'v' => (string) $report['from'])),
        array('', array('t' => 'date', 'v' => (string) $report['to'])),
        array(lang('Calculated VAT (sales less sales returns)'), $totals['calculated']),
        array(lang('Deductible VAT (purchases less returns to suppliers)'), $totals['deductible_purchases']),
        array(lang('Deductible VAT on expenses'), $totals['deductible_expenses']),
    );

    if ($report['net_withholding'] && ($totals['withheld_sales'] !== 0)) {
        $summary_rows[] = array(lang('VAT withheld on sales, declared by the buyer'), -$totals['withheld_sales']);
    }

    $summary_rows[] = array(erp_vat_difference_label($report['net_withholding']), array('t' => 'money_bold', 'v' => $totals['difference']));

    if (($totals['withheld_sales'] !== 0) || ($totals['withheld_purchases'] !== 0)) {
        $summary_rows[] = array(lang('VAT withheld on sales'), $totals['withheld_sales']);
        $summary_rows[] = array(lang('VAT withheld on purchases'), $totals['withheld_purchases']);
    }

    if ($report['has_tax2']) {
        $summary_rows[] = array(lang(array('string' => '{var:1} on sales (less returns)', 'vars' => $tax2_label)), $totals['tax2_calculated']);
        $summary_rows[] = array(lang(array('string' => '{var:1} on purchases (less returns)', 'vars' => $tax2_label)), $totals['tax2_deductible']);
    }

    if ($totals['expense_not_deductible'] !== 0) {
        $summary_rows[] = array(lang('Tax on expenses that is not deductible (a cost)'), $totals['expense_not_deductible']);
    }

    $summary_rows[] = array(lang('Cancelled documents (listed, not counted)'), array('v' => (int) $report['cancelled'], 't' => 'int'));

    $sheets = array();

    $sheets['summary'] = array('title' => lang('Summary'), 'columns' => array(
        array('label' => lang('Line item'), 'type' => 'text', 'width' => 56),
        $money(lang('Amount'), 20),
    ), 'rows' => $summary_rows, 'filter' => false);

    $rate_rows = array();
    foreach ($report['rates'] as $item) {
        $rate_rows[] = array(
            $labels[$item['key']],
            ($item['tax'] === 'tax2') ? $tax2_label : $tax_label,
            $item['rate'],
            (int) $item['documents'],
            $item['net'],
            $item['tax_amount'],
        );
    }

    $sheets['rates'] = array('title' => lang('By rate'), 'columns' => array(
        array('label' => lang('Document type'), 'type' => 'text', 'width' => 26),
        array('label' => lang('Tax'), 'type' => 'text', 'width' => 12),
        array('label' => lang('Rate (%)'), 'type' => 'number', 'width' => 9),
        array('label' => lang('Number of documents'), 'type' => 'int', 'width' => 12),
        $money(lang('Taxable Amount'), 18),
        $money(lang('Tax amount'), 16),
    ), 'rows' => $rate_rows);

    if (!empty($report['withholding'])) {
        $withholding_rows = array();
        foreach ($report['withholding'] as $item) {
            $withholding_rows[] = array($labels[$item['key']], $item['code'], $item['rate'], (int) $item['documents'], $item['net'], $item['tax_amount'], $item['amount']);
        }

        $sheets['withholding'] = array('title' => lang('Withholding'), 'columns' => array(
            array('label' => lang('Document type'), 'type' => 'text', 'width' => 26),
            array('label' => lang('Code'), 'type' => 'text', 'width' => 8),
            array('label' => lang('Rate (%)'), 'type' => 'number', 'width' => 9),
            array('label' => lang('Number of documents'), 'type' => 'int', 'width' => 12),
            $money(lang('Taxable Amount'), 18),
            $money($tax_label, 16),
            $money(lang('Withheld'), 16),
        ), 'rows' => $withholding_rows);
    }

    if (count($report['months']) > 1) {
        $month_rows = array();
        foreach ($report['months'] as $month => $figures) {
            $row = array($month, $figures['calculated']);

            if ($report['net_withholding']) {
                $row[] = -$figures['withheld_sales'];
            }

            $month_rows[] = array_merge($row, array($figures['deductible_purchases'], $figures['deductible_expenses'], $figures['difference']));
        }

        $month_columns = array(
            array('label' => lang('Month'), 'type' => 'text', 'width' => 10),
            $money(erp_tax_label('calculated'), 18),
        );

        if ($report['net_withholding']) {
            $month_columns[] = $money(lang('Withheld on sales'), 18);
        }

        $sheets['months'] = array('title' => lang('By month'), 'columns' => array_merge($month_columns, array(
            $money(lang('Deductible, purchases'), 18),
            $money(lang('Deductible, expenses'), 18),
            $money(lang('Difference'), 18),
        )), 'rows' => $month_rows);
    }

    $document_rows = array();
    foreach ($report['documents'] as $item) {
        $rates = array();
        foreach ($item['rates'] as $rate) {
            $rates[] = erp_percent_text($rate);
        }

        $row = array(
            array('t' => 'date', 'v' => $item['date']),
            $item['number'],
            $labels[$item['kind']],
            $item['account'],
            $item['tax_number'],
            implode(', ', $rates),
            $item['net'],
            $item['vat'],
        );

        if ($report['has_tax2']) {
            $row[] = $item['tax2'];
        }

        $row[] = $item['withholding'];
        $row[] = $item['total'];
        $row[] = $item['currency'];
        $row[] = $item['cancelled'] ? lang('Cancelled') : ((($item['type'] === 'expense') && !$item['deductible']) ? lang('Not deductible') : '');
        $document_rows[] = $row;
    }

    $document_columns = array(
        array('label' => lang('Date'), 'type' => 'date', 'width' => 11),
        array('label' => lang('Document Number'), 'type' => 'text', 'width' => 20),
        array('label' => lang('Document type'), 'type' => 'text', 'width' => 20),
        array('label' => lang('Account'), 'type' => 'text', 'width' => 32),
        array('label' => erp_tax_id_label(), 'type' => 'text', 'width' => 14),
        array('label' => lang('Rate'), 'type' => 'text', 'width' => 10),
        $money(lang('Taxable Amount'), 16),
        $money($tax_label, 14),
    );

    if ($report['has_tax2']) {
        $document_columns[] = $money($tax2_label, 14);
    }

    $document_columns[] = $money(lang('Withholding'), 14);
    $document_columns[] = $money(lang('Total'), 16);
    $document_columns[] = array('label' => lang('Currency'), 'type' => 'text', 'width' => 8);
    $document_columns[] = array('label' => lang('Note'), 'type' => 'text', 'width' => 16);

    $sheets['documents'] = array('title' => lang('Documents of the period'), 'columns' => $document_columns, 'rows' => $document_rows);

    return erp_accountant_write_workbook($sheets, $path);
}
