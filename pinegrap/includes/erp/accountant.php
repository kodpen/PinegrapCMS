<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * ERP - the accountant's pack.
 *
 * Once a month the store hands its accountant what the books are made from:
 * the sales and purchase invoices, the returns, the VAT they carry by rate,
 * what came in and went out of the tills, who owes what at the end of the
 * month and what the stock on hand is worth. The pack is one ZIP: a workbook
 * with a sheet for each of those (a heading row with filters, amounts as
 * numbers, dates as dates) and, beside it, the documents themselves as they
 * were issued - the provider's official copy of an e-document when the store
 * has one, the kept PDF otherwise.
 *
 * A pack is built from the screen (erp_accountant.php) or by the monthly job
 * (erp_accountant_job.php) and its ZIP kept under data/temp/erp_packages,
 * which the web server does not serve. The ZIP is a delivery copy, not a
 * record: the row in erp_accountant_packages keeps what went into it, and a
 * pack whose file is gone is built again from the books (the screen says so,
 * the monthly job does it by itself). It leaves the store by a download from
 * the panel or by a link sent to the accountant: a random token whose hash is
 * kept, good for a few days, opened by erp_accountant_download.php without a
 * login.
 *
 * Amounts in the pack are in the base currency unless a column says it is the
 * document's own; cancelled documents are listed, marked, and left out of
 * every total.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_ERP_ENTRY')) {
    exit;
}

// How long a link sent to the accountant opens the pack.
if (!defined('ERP_ACCOUNTANT_LINK_DAYS')) {
    define('ERP_ACCOUNTANT_LINK_DAYS', 7);
}

/**
 * Whether the pack's table and settings are there (2026.4.4, 4.78).
 *
 * @return bool
 */
function erp_accountant_ready()
{
    static $ready = null;

    if ($ready === null) {
        $ready = function_exists('waf_table_has_column')
            && waf_table_has_column('erp_accountant_packages', 'token_hash')
            && waf_table_has_column('config', 'erp_accountant_email');
    }

    return $ready;
}

/**
 * The accountant's settings: where the pack goes and whether it goes by itself.
 *
 * @return array ['email' => string, 'monthly' => bool, 'pdfs' => bool]
 */
function erp_accountant_settings()
{
    if (!erp_accountant_ready()) {
        return array('email' => '', 'monthly' => false, 'pdfs' => true);
    }

    $row = db_item("SELECT erp_accountant_email, erp_accountant_monthly, erp_accountant_pdfs FROM config LIMIT 1");

    return array(
        'email' => trim((string) ($row['erp_accountant_email'] ?? '')),
        'monthly' => ((int) ($row['erp_accountant_monthly'] ?? 0) === 1),
        'pdfs' => ((int) ($row['erp_accountant_pdfs'] ?? 1) === 1),
    );
}

/**
 * Save the accountant's settings.
 *
 * @param array $input  email, monthly, pdfs
 * @return array ['success' => bool, 'error' => string]
 */
function erp_accountant_save_settings($input)
{
    if (!erp_accountant_ready()) {
        return array('success' => false, 'error' => lang('The accountant pack comes with the software update; run the update to use it.'));
    }

    $email = trim((string) ($input['email'] ?? ''));

    if (($email !== '') && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return array('success' => false, 'error' => lang('Please enter a valid e-mail address for the accountant.'));
    }

    $monthly = !empty($input['monthly']) ? 1 : 0;

    if ($monthly && ($email === '')) {
        return array('success' => false, 'error' => lang('The pack can only go by itself to an address; enter the accountant\'s e-mail address first.'));
    }

    $ok = db("UPDATE config SET
            erp_accountant_email = '" . escape(mb_substr($email, 0, 255)) . "',
            erp_accountant_monthly = '" . $monthly . "',
            erp_accountant_pdfs = '" . (!empty($input['pdfs']) ? 1 : 0) . "'");

    return ($ok !== false)
        ? array('success' => true, 'error' => '')
        : array('success' => false, 'error' => lang('The settings could not be saved.'));
}

/**
 * The month before the one a date is in: the period a pack is usually for.
 *
 * @param string $today  Y-m-d, today when empty
 * @return array ['from' => Y-m-d, 'to' => Y-m-d]
 */
function erp_accountant_last_month($today = '')
{
    $today = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $today) ? (string) $today : date('Y-m-d');
    $first = date('Y-m-01', strtotime(substr($today, 0, 7) . '-01 -1 month'));

    return array('from' => $first, 'to' => date('Y-m-t', strtotime($first)));
}

/**
 * A period from what the screen sent: a month (Y-m) or two dates.
 *
 * @param array $input  month | from, to
 * @return array ['from', 'to', 'error']
 */
function erp_accountant_period($input)
{
    $month = trim((string) ($input['month'] ?? ''));

    if (preg_match('/^\d{4}-\d{2}$/', $month) && checkdate((int) substr($month, 5, 2), 1, (int) substr($month, 0, 4))) {
        return array('from' => $month . '-01', 'to' => date('Y-m-t', strtotime($month . '-01')), 'error' => '');
    }

    $from = trim((string) ($input['from'] ?? ''));
    $to = trim((string) ($input['to'] ?? ''));

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $to) || ($from > $to)) {
        return array('from' => '', 'to' => '', 'error' => lang('Choose a month, or a start date on or before the end date.'));
    }

    if ((strtotime($to) - strtotime($from)) > (366 * 86400)) {
        return array('from' => '', 'to' => '', 'error' => lang('A pack covers at most a year.'));
    }

    return array('from' => $from, 'to' => $to, 'error' => '');
}

/**
 * A period as the pack and its file names write it: "2026-09" for a whole
 * month, "2026-09-01_2026-09-15" otherwise.
 *
 * @param string $from
 * @param string $to
 * @return string
 */
function erp_accountant_period_label($from, $to)
{
    if ((substr($from, 8, 2) === '01') && ($to === date('Y-m-t', strtotime($from)))) {
        return substr($from, 0, 7);
    }

    return $from . '_' . $to;
}

/* ------------------------------------------------------------ the figures */

/**
 * An amount of a document in the base currency.
 *
 * @param int   $kurus
 * @param array $invoice  currency, exchange_rate
 * @return int
 */
function erp_accountant_base($kurus, $invoice)
{
    if (strtoupper((string) $invoice['currency']) === erp_base_currency()) {
        return (int) $kurus;
    }

    return erp_to_base((int) $kurus, (float) $invoice['exchange_rate']);
}

/**
 * The period's documents, issued or cancelled, oldest first.
 *
 * @param string $from
 * @param string $to
 * @return array  erp_invoices rows with account_* snapshot, parent_number, order_number
 */
function erp_accountant_documents($from, $to)
{
    // The account as the document kept it; a document without that copy
    // (a return takes the invoice's party) falls back to the live card.
    return (array) db_items("SELECT i.*, p.full_number AS parent_number, o.order_number,
            a.title AS live_title, a.tax_number AS live_tax_number, a.tax_office AS live_tax_office, a.country_code AS live_country_code
        FROM erp_invoices i
        LEFT JOIN erp_invoices p ON p.id = i.parent_invoice_id
        LEFT JOIN orders o ON o.id = i.order_id
        LEFT JOIN erp_accounts a ON a.id = i.account_id
        WHERE i.status <> 'draft' AND i.doc_type IN ('invoice', 'return')
          AND i.issue_date BETWEEN '" . escape($from) . "' AND '" . escape($to) . "'
        ORDER BY i.issue_date ASC, i.id ASC");
}

/**
 * The period's lines with their tax, keyed by document.
 *
 * @param array $documents
 * @return array  invoice_id => lines
 */
function erp_accountant_lines($documents)
{
    $ids = array();

    foreach ($documents as $document) {
        $ids[] = (int) $document['id'];
    }

    if (empty($ids)) {
        return array();
    }

    $tax2 = waf_table_has_column('erp_invoice_items', 'tax2_amount');
    $withholding = waf_table_has_column('erp_invoice_items', 'withholding_amount');
    $by_invoice = array();

    foreach (array_chunk($ids, 500) as $chunk) {
        foreach ((array) db_items("SELECT invoice_id, tax_rate, line_total, discount_amount, tax_total, withholding_code, withholding_rate"
            . ($tax2 ? ", tax2_rate, tax2_amount" : ", 0 AS tax2_rate, 0 AS tax2_amount")
            . ($withholding ? ", withholding_amount" : ", 0 AS withholding_amount") . "
            FROM erp_invoice_items WHERE invoice_id IN (" . implode(',', $chunk) . ")") as $line) {
            $by_invoice[(int) $line['invoice_id']][] = $line;
        }
    }

    return $by_invoice;
}

/**
 * The words the pack uses for things the database keeps as codes.
 *
 * @return array
 */
function erp_accountant_words()
{
    return array(
        'direction' => array('sales' => lang('Sales'), 'purchase' => lang('Purchase')),
        'doc_type' => array('invoice' => lang('Invoice'), 'return' => lang('Return')),
        'edoc' => array('none' => '', 'einvoice' => lang('e-Invoice'), 'earchive' => lang('e-Archive')),
        'status' => array(
            'issued' => lang('Issued'),
            'partially_paid' => lang('Partially paid'),
            'paid' => lang('Paid'),
            'cancelled' => lang('Cancelled'),
        ),
        'till' => array('cash' => lang('Cash'), 'bank' => lang('Bank'), 'pos' => lang('Card terminal'), 'credit_card' => lang('Credit card')),
        'method' => array('cash' => lang('Cash'), 'transfer' => lang('Bank transfer'), 'card' => lang('Card'), 'cheque' => lang('Cheque'), 'other' => lang('Other')),
        'cash_type' => array(
            'collection' => lang('Collection'),
            'payment' => lang('Payment'),
            'transfer' => lang('Transfer between tills'),
            'cancel' => lang('Cancellation'),
            'opening' => lang('Opening balance'),
            'expense' => lang('Expense'),
        ),
    );
}

/**
 * One document's figures: net, VAT, second tax and withholding, in its own
 * currency and in the base currency.
 *
 * @param array $document
 * @param array $lines
 * @return array
 */
function erp_accountant_document_figures($document, $lines)
{
    $net = 0;
    $vat = 0;
    $tax2 = 0;
    $withheld = 0;

    foreach ($lines as $line) {
        $net += (int) $line['line_total'] - (int) $line['discount_amount'];
        $vat += (int) $line['tax_total'] - (int) $line['tax2_amount'];
        $tax2 += (int) $line['tax2_amount'];
        $withheld += (int) $line['withholding_amount'];
    }

    // A document from before the lines kept their withholding carries it only
    // on its header.
    if (($withheld === 0) && ((int) $document['withholding_total'] !== 0)) {
        $withheld = (int) $document['withholding_total'];
    }

    return array(
        'net' => $net,
        'vat' => $vat,
        'tax2' => $tax2,
        'withholding' => $withheld,
        'total' => (int) $document['grand_total'],
        'net_base' => erp_accountant_base($net, $document),
        'vat_base' => erp_accountant_base($vat, $document),
        'tax2_base' => erp_accountant_base($tax2, $document),
        'withholding_base' => erp_accountant_base($withheld, $document),
        'total_base' => ((int) ($document['grand_total_base'] ?? 0) !== 0) ? (int) $document['grand_total_base'] : erp_accountant_base((int) $document['grand_total'], $document),
    );
}

/**
 * Everything the pack says, as sheets, and the documents it carries.
 *
 * @param string $from
 * @param string $to
 * @param array  $options  cash (bool): include the till sheets
 * @return array ['sheets' => array, 'documents' => array, 'summary' => array]
 */
function erp_accountant_collect($from, $to, $options = array())
{
    $words = erp_accountant_words();
    $base = erp_base_currency();
    $with_cash = !array_key_exists('cash', $options) || !empty($options['cash']);
    $documents = erp_accountant_documents($from, $to);
    $lines = erp_accountant_lines($documents);

    // A second-tax column when the period has any second tax, whether or not
    // the store still names one today.
    $has_tax2 = function_exists('erp_tax2_enabled') && erp_tax2_enabled();
    foreach ($lines as $document_lines) {
        foreach ($document_lines as $line) {
            if ((int) $line['tax2_amount'] !== 0) {
                $has_tax2 = true;
                break 2;
            }
        }
    }
    $tax2_label = (erp_tax2_name() !== '') ? erp_tax2_name() : lang('Second tax');

    $invoice_columns = array(
        array('label' => lang('Date'), 'type' => 'date', 'width' => 11),
        array('label' => lang('Document Number'), 'type' => 'text', 'width' => 20),
        array('label' => lang('e-Document'), 'type' => 'text', 'width' => 11),
        array('label' => lang('GİB number'), 'type' => 'text', 'width' => 18),
        array('label' => lang('ETTN'), 'type' => 'text', 'width' => 38),
        array('label' => lang('Account'), 'type' => 'text', 'width' => 32),
        array('label' => erp_tax_id_label(), 'type' => 'text', 'width' => 14),
        array('label' => lang('Tax office'), 'type' => 'text', 'width' => 16),
        array('label' => lang('Country'), 'type' => 'text', 'width' => 7),
        array('label' => lang('Taxable Amount'), 'type' => 'money', 'width' => 14),
        array('label' => erp_tax_label('tax'), 'type' => 'money', 'width' => 12),
    );

    if ($has_tax2) {
        $invoice_columns[] = array('label' => $tax2_label, 'type' => 'money', 'width' => 12);
    }

    $invoice_columns = array_merge($invoice_columns, array(
        array('label' => lang('Withholding'), 'type' => 'money', 'width' => 12),
        array('label' => lang('Total'), 'type' => 'money', 'width' => 14),
        array('label' => lang('Currency'), 'type' => 'text', 'width' => 8),
        array('label' => lang('Exchange rate'), 'type' => 'number', 'width' => 10),
        array('label' => lang(array('string' => 'Total ({var:1})', 'vars' => $base)), 'type' => 'money', 'width' => 15),
        array('label' => lang('Status'), 'type' => 'text', 'width' => 13),
    ));

    $sales_rows = array();
    $purchase_rows = array();
    $return_rows = array();
    $bucket = erp_vat_bucket();
    $summary = array(
        'sales' => array('count' => 0, 'net' => 0, 'vat' => 0, 'tax2' => 0, 'total' => 0),
        'sales_return' => array('count' => 0, 'net' => 0, 'vat' => 0, 'tax2' => 0, 'total' => 0),
        'purchase' => array('count' => 0, 'net' => 0, 'vat' => 0, 'tax2' => 0, 'total' => 0),
        'purchase_return' => array('count' => 0, 'net' => 0, 'vat' => 0, 'tax2' => 0, 'total' => 0),
        'withholding_sales' => 0,
        'withholding_purchase' => 0,
        'cancelled' => 0,
    );
    $files = array();

    foreach ($documents as $document) {
        $id = (int) $document['id'];
        $figures = erp_accountant_document_figures($document, $lines[$id] ?? array());
        $cancelled = ((string) $document['status'] === 'cancelled');
        $is_return = ((string) $document['doc_type'] === 'return');
        $direction = ((string) $document['direction'] === 'purchase') ? 'purchase' : 'sales';
        $key = $direction . ($is_return ? '_return' : '');

        $row = array(
            (string) $document['issue_date'],
            (string) $document['full_number'],
            $words['edoc'][(string) $document['edoc_kind']] ?? '',
            (string) $document['gib_number'],
            strtoupper((string) $document['gib_uuid']),
            (trim((string) $document['account_title']) !== '') ? (string) $document['account_title'] : (string) $document['live_title'],
            (trim((string) $document['account_title']) !== '') ? (string) $document['account_tax_number'] : (string) $document['live_tax_number'],
            (trim((string) $document['account_title']) !== '') ? (string) $document['account_tax_office'] : (string) $document['live_tax_office'],
            (trim((string) $document['account_country_code']) !== '') ? (string) $document['account_country_code'] : (string) $document['live_country_code'],
            $figures['net'],
            $figures['vat'],
        );

        if ($has_tax2) {
            $row[] = $figures['tax2'];
        }

        $row = array_merge($row, array(
            $figures['withholding'],
            $figures['total'],
            strtoupper((string) $document['currency']),
            (strtoupper((string) $document['currency']) === $base) ? '' : (float) $document['exchange_rate'],
            $figures['total_base'],
            $words['status'][(string) $document['status']] ?? (string) $document['status'],
        ));

        if ($is_return) {
            array_splice($row, 2, 0, array($words['direction'][$direction], (string) $document['parent_number']));
            $return_rows[] = $row;
        } elseif ($direction === 'purchase') {
            array_splice($row, 2, 0, array((string) $document['supplier_invoice_no'], ((string) $document['supplier_invoice_date'] > '0000-00-00') ? (string) $document['supplier_invoice_date'] : ''));
            $purchase_rows[] = $row;
        } else {
            $row[] = (string) ($document['order_number'] ?? '');
            $sales_rows[] = $row;
        }

        if ($cancelled) {
            $summary['cancelled']++;
        } else {
            $summary[$key]['count']++;
            $summary[$key]['net'] += $figures['net_base'];
            $summary[$key]['vat'] += $figures['vat_base'];
            $summary[$key]['tax2'] += $figures['tax2_base'];
            $summary[$key]['total'] += $figures['total_base'];
            $summary['withholding_' . $direction] += $is_return ? -$figures['withholding_base'] : $figures['withholding_base'];

            erp_vat_add_document($bucket, $key, $document, $lines[$id] ?? array());
        }

        $files[] = array('document' => $document, 'direction' => $direction, 'return' => $is_return);
    }

    $kind_labels = array(
        'sales' => lang('Sales invoices'),
        'sales_return' => lang('Sales returns'),
        'purchase' => lang('Purchase invoices'),
        'purchase_return' => lang('Returns to suppliers'),
    );

    // Expenses (4.95): receipts that are not invoices. The VAT of those whose
    // tax is taken back goes on the VAT sheet next to the purchases; a
    // cancelled one is listed and counts for nothing.
    $expense_rows = array();
    $expense_files = array();
    $expense_vat = 0;
    $with_expenses = function_exists('erp_expenses_ready') && erp_expenses_ready();

    if ($with_expenses) {
        $kind_labels['expense'] = lang('Expenses');
        $summary['expense'] = array('count' => 0, 'net' => 0, 'vat' => 0, 'tax2' => 0, 'total' => 0);
        $expense_statuses = erp_expense_statuses();

        foreach (array_reverse(erp_expenses_list(array('from' => $from, 'to' => $to, 'status' => 'all', 'limit' => 5000))) as $expense) {
            $cancelled = ((string) $expense['status'] === 'cancelled');
            $deductible = ((int) $expense['tax_deductible'] === 1);

            if (!$cancelled) {
                $summary['expense']['count']++;
                $summary['expense']['net'] += (int) $expense['net_base'];
                $summary['expense']['vat'] += (int) $expense['tax_base'];
                $summary['expense']['total'] += (int) $expense['total_base'];

                $expense_vat += erp_vat_add_expense($bucket, $expense);
            } else {
                $summary['cancelled']++;
            }

            $expense_rows[] = array(
                (string) $expense['expense_date'],
                (string) $expense['category_name'],
                (string) $expense['category_code'],
                (string) $expense['supplier'],
                (string) $expense['supplier_tax_number'],
                (string) $expense['document_no'],
                (string) $expense['description'],
                (int) $expense['net_base'],
                (float) $expense['tax_rate'],
                (int) $expense['tax_base'],
                (int) $expense['total_base'],
                $deductible ? lang('Yes') : lang('No'),
                strtoupper((string) $expense['currency']),
                (int) $expense['total_amount'],
                $expense_statuses[(string) $expense['status']] ?? (string) $expense['status'],
                (string) $expense['till_name'],
                ((string) $expense['paid_date'] > '0000-00-00') ? (string) $expense['paid_date'] : '',
            );

            $expense_files[] = $expense;
        }
    }

    // VAT by kind of document and rate, returns under the documents they
    // take back from (includes/erp/vat_report.php, which the VAT report
    // screen reads the same way).
    $vat = erp_vat_sorted($bucket['vat']);
    $withholding = $bucket['withholding'];

    $vat_rows = array();

    foreach ($vat as $item) {
        $vat_rows[] = array(
            $kind_labels[$item['key']],
            ($item['tax'] === 'tax2') ? $tax2_label : erp_tax_label('tax'),
            $item['rate'],
            count($item['documents']),
            $item['net'],
            $item['tax_amount'],
        );
    }

    $output_vat = $summary['sales']['vat'] - $summary['sales_return']['vat'];
    $input_vat = $summary['purchase']['vat'] - $summary['purchase_return']['vat'];

    $vat_rows[] = array();
    $vat_rows[] = array(array('v' => lang('Calculated VAT (sales less sales returns)'), 't' => 'bold'), '', '', '', '', array('v' => $output_vat, 't' => 'money_bold'));
    $vat_rows[] = array(array('v' => lang('Deductible VAT (purchases less returns to suppliers)'), 't' => 'bold'), '', '', '', '', array('v' => $input_vat, 't' => 'money_bold'));

    if ($expense_vat !== 0) {
        $vat_rows[] = array(array('v' => lang('Deductible VAT on expenses'), 't' => 'bold'), '', '', '', '', array('v' => $expense_vat, 't' => 'money_bold'));
    }

    // The VAT withheld on sales comes off only when the store says so
    // (includes/erp/rules.php); the difference and its caption follow.
    $net_withholding = erp_vat_net_withholding();

    if ($net_withholding && ($summary['withholding_sales'] !== 0)) {
        $vat_rows[] = array(array('v' => lang('VAT withheld on sales, declared by the buyer'), 't' => 'bold'), '', '', '', '', array('v' => -$summary['withholding_sales'], 't' => 'money_bold'));
    }

    $vat_difference = erp_vat_difference($output_vat, $input_vat + $expense_vat, $summary['withholding_sales']);
    $vat_rows[] = array(array('v' => erp_vat_difference_label($net_withholding), 't' => 'bold'), '', '', '', '', array('v' => $vat_difference, 't' => 'money_bold'));

    if (!empty($withholding)) {
        $vat_rows[] = array();
        $vat_rows[] = array(array('v' => lang('VAT withholding by code'), 't' => 'bold'));

        foreach ($withholding as $item) {
            $vat_rows[] = array($kind_labels[$item['key']], $item['code'], $item['rate'], '', '', $item['amount']);
        }
    }

    $sheets = array();

    $sheets['summary'] = array('title' => lang('Summary'), 'columns' => array(
        array('label' => lang('Item'), 'type' => 'text', 'width' => 52),
        array('label' => lang('Value'), 'type' => 'text', 'width' => 22),
    ), 'rows' => array(), 'filter' => false);

    $sheets['sales'] = array('title' => lang('Sales invoices'), 'columns' => array_merge(
        $invoice_columns,
        array(array('label' => lang('Order'), 'type' => 'text', 'width' => 10))
    ), 'rows' => $sales_rows);

    $purchase_columns = $invoice_columns;
    array_splice($purchase_columns, 2, 0, array(
        array('label' => lang('Supplier Invoice Number'), 'type' => 'text', 'width' => 20),
        array('label' => lang('Supplier Invoice Date'), 'type' => 'date', 'width' => 11),
    ));
    $sheets['purchases'] = array('title' => lang('Purchase invoices'), 'columns' => $purchase_columns, 'rows' => $purchase_rows);

    $return_columns = $invoice_columns;
    array_splice($return_columns, 2, 0, array(
        array('label' => lang('Direction'), 'type' => 'text', 'width' => 10),
        array('label' => lang('Returned invoice'), 'type' => 'text', 'width' => 20),
    ));
    $sheets['returns'] = array('title' => lang('Returns'), 'columns' => $return_columns, 'rows' => $return_rows);

    $sheets['vat'] = array('title' => lang('VAT summary'), 'columns' => array(
        array('label' => lang('Documents'), 'type' => 'text', 'width' => 46),
        array('label' => lang('Tax'), 'type' => 'text', 'width' => 12),
        array('label' => lang('Rate (%)'), 'type' => 'number', 'width' => 9),
        array('label' => lang('Count'), 'type' => 'int', 'width' => 8),
        array('label' => lang(array('string' => 'Taxable amount ({var:1})', 'vars' => $base)), 'type' => 'money', 'width' => 18),
        array('label' => lang(array('string' => 'Tax ({var:1})', 'vars' => $base)), 'type' => 'money', 'width' => 16),
    ), 'rows' => $vat_rows, 'filter' => false);

    if ($with_expenses) {
        $sheets['expenses'] = array('title' => lang('Expenses'), 'columns' => array(
            array('label' => lang('Date'), 'type' => 'date', 'width' => 11),
            array('label' => lang('Category'), 'type' => 'text', 'width' => 26),
            array('label' => lang('Account code'), 'type' => 'text', 'width' => 12),
            array('label' => lang('Supplier'), 'type' => 'text', 'width' => 28),
            array('label' => erp_tax_id_label(), 'type' => 'text', 'width' => 14),
            array('label' => lang('Receipt number'), 'type' => 'text', 'width' => 16),
            array('label' => lang('Description'), 'type' => 'text', 'width' => 30),
            array('label' => lang(array('string' => 'Taxable amount ({var:1})', 'vars' => $base)), 'type' => 'money', 'width' => 16),
            array('label' => lang('Rate (%)'), 'type' => 'number', 'width' => 9),
            array('label' => lang(array('string' => 'Tax ({var:1})', 'vars' => $base)), 'type' => 'money', 'width' => 14),
            array('label' => lang(array('string' => 'Total ({var:1})', 'vars' => $base)), 'type' => 'money', 'width' => 15),
            array('label' => lang('Tax taken back'), 'type' => 'text', 'width' => 10),
            array('label' => lang('Currency'), 'type' => 'text', 'width' => 8),
            array('label' => lang('Total'), 'type' => 'money', 'width' => 14),
            array('label' => lang('Status'), 'type' => 'text', 'width' => 10),
            array('label' => lang('Paid from'), 'type' => 'text', 'width' => 20),
            array('label' => lang('Paid on'), 'type' => 'date', 'width' => 11),
        ), 'rows' => $expense_rows);
    }

    $collections = 0;
    $payments = 0;
    $expenses_paid = 0;

    if ($with_cash) {
        $cash_rows = array();

        foreach ((array) db_items("SELECT c.*, a.title AS account_title, a.tax_number AS account_tax_number, t.name AS till_name
            FROM erp_cash_transactions c
            LEFT JOIN erp_accounts a ON a.id = c.account_id
            LEFT JOIN erp_cash_accounts t ON t.id = c.cash_account_id
            WHERE c.doc_date BETWEEN '" . escape($from) . "' AND '" . escape($to) . "'
            ORDER BY c.doc_date ASC, c.id ASC") as $movement) {
            $in = ((string) $movement['direction'] === 'in');
            $amount_base = (int) $movement['amount_base'];
            $type = (string) $movement['doc_type'];

            if ($type === 'collection') {
                $collections += $in ? $amount_base : -$amount_base;
            } elseif ($type === 'payment') {
                $payments += $in ? -$amount_base : $amount_base;
            } elseif ($type === 'expense') {
                $expenses_paid += $in ? -$amount_base : $amount_base;
            } elseif ($type === 'cancel') {
                $cancelled_type = (string) db_value("SELECT doc_type FROM erp_cash_transactions WHERE id = '" . (int) $movement['doc_id'] . "' LIMIT 1");
                if ($cancelled_type === 'collection') {
                    $collections += $in ? $amount_base : -$amount_base;
                } elseif ($cancelled_type === 'payment') {
                    $payments += $in ? -$amount_base : $amount_base;
                } elseif ($cancelled_type === 'expense') {
                    $expenses_paid += $in ? -$amount_base : $amount_base;
                }
            }

            $cash_rows[] = array(
                (string) $movement['doc_date'],
                $words['cash_type'][$type] ?? $type,
                (string) $movement['till_name'],
                $words['method'][(string) $movement['payment_method']] ?? (string) $movement['payment_method'],
                (string) $movement['account_title'],
                (string) $movement['account_tax_number'],
                (string) $movement['description'],
                $in ? (int) $movement['amount'] : '',
                $in ? '' : (int) $movement['amount'],
                strtoupper((string) $movement['currency']),
                $in ? $amount_base : -$amount_base,
            );
        }

        $sheets['cash'] = array('title' => lang('Receipts and payments'), 'columns' => array(
            array('label' => lang('Date'), 'type' => 'date', 'width' => 11),
            array('label' => lang('Type'), 'type' => 'text', 'width' => 20),
            array('label' => lang('Till or Bank Account'), 'type' => 'text', 'width' => 22),
            array('label' => lang('Method'), 'type' => 'text', 'width' => 14),
            array('label' => lang('Account'), 'type' => 'text', 'width' => 30),
            array('label' => erp_tax_id_label(), 'type' => 'text', 'width' => 14),
            array('label' => lang('Description'), 'type' => 'text', 'width' => 36),
            array('label' => lang('In'), 'type' => 'money', 'width' => 13),
            array('label' => lang('Out'), 'type' => 'money', 'width' => 13),
            array('label' => lang('Currency'), 'type' => 'text', 'width' => 8),
            array('label' => lang(array('string' => 'Net ({var:1})', 'vars' => $base)), 'type' => 'money', 'width' => 14),
        ), 'rows' => $cash_rows);

        $till_rows = array();

        foreach (erp_cashflow_by_till(array('from' => $from, 'to' => $to, 'till' => 0)) as $till) {
            $till_rows[] = array(
                (string) $till['name'],
                $words['till'][(string) $till['kind']] ?? (string) $till['kind'],
                (string) $till['currency'],
                (int) $till['opening'],
                (int) $till['in'],
                (int) $till['out'],
                (int) $till['closing'],
                (int) $till['count'],
            );
        }

        $sheets['tills'] = array('title' => lang('Tills and bank accounts'), 'columns' => array(
            array('label' => lang('Till or Bank Account'), 'type' => 'text', 'width' => 26),
            array('label' => lang('Type'), 'type' => 'text', 'width' => 14),
            array('label' => lang('Currency'), 'type' => 'text', 'width' => 8),
            array('label' => lang(array('string' => 'Opening ({var:1})', 'vars' => $base)), 'type' => 'money', 'width' => 15),
            array('label' => lang(array('string' => 'In ({var:1})', 'vars' => $base)), 'type' => 'money', 'width' => 15),
            array('label' => lang(array('string' => 'Out ({var:1})', 'vars' => $base)), 'type' => 'money', 'width' => 15),
            array('label' => lang(array('string' => 'Closing ({var:1})', 'vars' => $base)), 'type' => 'money', 'width' => 15),
            array('label' => lang('Movements'), 'type' => 'int', 'width' => 10),
        ), 'rows' => $till_rows);
    }

    // Who owes what when the period ends, from the ledger as it stood then.
    $balance_rows = array();
    $receivable = 0;
    $payable = 0;

    foreach ((array) db_items("SELECT a.id, a.title, a.kind, a.tax_number, a.tax_office,
            SUM(IF(t.direction = 'debit', t.amount_base, -t.amount_base)) AS balance
        FROM erp_account_transactions t
        INNER JOIN erp_accounts a ON a.id = t.account_id
        WHERE t.doc_date <= '" . escape($to) . "'
        GROUP BY a.id, a.title, a.kind, a.tax_number, a.tax_office
        HAVING balance <> 0
        ORDER BY a.title ASC") as $account) {
        $balance = (int) $account['balance'];

        if ($balance > 0) {
            $receivable += $balance;
        } else {
            $payable += -$balance;
        }

        $balance_rows[] = array(
            (string) $account['title'],
            (string) $account['tax_number'],
            (string) $account['tax_office'],
            ($balance > 0) ? $balance : '',
            ($balance < 0) ? -$balance : '',
        );
    }

    $sheets['balances'] = array('title' => lang('Account balances'), 'columns' => array(
        array('label' => lang('Account'), 'type' => 'text', 'width' => 34),
        array('label' => erp_tax_id_label(), 'type' => 'text', 'width' => 14),
        array('label' => lang('Tax office'), 'type' => 'text', 'width' => 18),
        array('label' => lang(array('string' => 'Debit balance ({var:1})', 'vars' => $base)), 'type' => 'money', 'width' => 18),
        array('label' => lang(array('string' => 'Credit balance ({var:1})', 'vars' => $base)), 'type' => 'money', 'width' => 18),
    ), 'rows' => $balance_rows);

    $stock_value = 0;

    if (function_exists('erp_stock_ready') && erp_stock_ready()) {
        $stock_rows = array();

        foreach (erp_stock_costed_products() as $product) {
            $tracked = ((int) $product['inventory'] === 1);
            $value = $tracked ? (int) round((int) $product['avg_cost'] * max(0, (int) $product['inventory_quantity'])) : 0;
            $stock_value += $value;

            $stock_rows[] = array(
                (trim((string) $product['product_name']) !== '') ? (string) $product['product_name'] : (string) $product['product_sku'],
                (string) $product['product_sku'],
                $tracked ? (int) $product['inventory_quantity'] : '',
                (int) $product['avg_cost'],
                (int) $product['last_cost'],
                ((string) $product['last_cost_date'] > '0000-00-00') ? (string) $product['last_cost_date'] : '',
                $tracked ? $value : '',
            );
        }

        $sheets['stock'] = array('title' => lang('Stock value'), 'columns' => array(
            array('label' => lang('Product'), 'type' => 'text', 'width' => 34),
            array('label' => lang('SKU'), 'type' => 'text', 'width' => 14),
            array('label' => lang('Stock'), 'type' => 'int', 'width' => 9),
            array('label' => lang('Average cost'), 'type' => 'money', 'width' => 14),
            array('label' => lang('Last purchase'), 'type' => 'money', 'width' => 14),
            array('label' => lang('Last purchase date'), 'type' => 'date', 'width' => 12),
            array('label' => lang('Stock value'), 'type' => 'money', 'width' => 15),
        ), 'rows' => $stock_rows);
    }

    $summary['collections'] = $collections;
    $summary['payments'] = $payments;
    $summary['expenses_paid'] = $expenses_paid;
    $summary['receivable'] = $receivable;
    $summary['payable'] = $payable;
    $summary['stock_value'] = $stock_value;
    $summary['output_vat'] = $output_vat;
    $summary['input_vat'] = $input_vat + $expense_vat;
    $summary['expense_vat'] = $expense_vat;

    $money = function ($kurus) {
        return array('v' => (int) $kurus, 't' => 'money');
    };
    $heading = function ($text) {
        return array(array('v' => $text, 't' => 'bold'), '');
    };

    $summary_rows = array(
        array(lang('Business'), defined('ORGANIZATION_NAME') ? (string) ORGANIZATION_NAME : ''),
        array(erp_tax_id_label(), defined('ERP_SELLER_VKN') ? (string) ERP_SELLER_VKN : ''),
        array(lang('Period'), array('v' => $from, 't' => 'date')),
        array('', array('v' => $to, 't' => 'date')),
        array(lang('Prepared'), date('Y-m-d H:i')),
        array(lang('Base currency'), $base),
        array(),
    );

    foreach ($kind_labels as $key => $label) {
        $summary_rows[] = $heading($label);
        $summary_rows[] = array(lang('Documents'), array('v' => $summary[$key]['count'], 't' => 'int'));
        $summary_rows[] = array(lang('Taxable Amount'), $money($summary[$key]['net']));
        $summary_rows[] = array(erp_tax_label('tax'), $money($summary[$key]['vat']));

        if ($has_tax2) {
            $summary_rows[] = array($tax2_label, $money($summary[$key]['tax2']));
        }

        $summary_rows[] = array(lang('Total'), $money($summary[$key]['total']));
        $summary_rows[] = array();
    }

    $summary_rows[] = $heading(erp_tax_label('tax'));
    $summary_rows[] = array(lang('Calculated VAT (sales less sales returns)'), $money($output_vat));
    $summary_rows[] = array(lang('Deductible VAT (purchases less returns to suppliers)'), $money($input_vat));

    if ($expense_vat !== 0) {
        $summary_rows[] = array(lang('Deductible VAT on expenses'), $money($expense_vat));
    }

    if ($net_withholding && ($summary['withholding_sales'] !== 0)) {
        $summary_rows[] = array(lang('VAT withheld on sales, declared by the buyer'), $money(-$summary['withholding_sales']));
    }

    $summary_rows[] = array(erp_vat_difference_label($net_withholding), $money($vat_difference));

    if (($summary['withholding_sales'] !== 0) || ($summary['withholding_purchase'] !== 0)) {
        $summary_rows[] = array(lang('VAT withheld on sales'), $money($summary['withholding_sales']));
        $summary_rows[] = array(lang('VAT withheld on purchases'), $money($summary['withholding_purchase']));
    }

    $summary_rows[] = array();

    if ($with_cash) {
        $summary_rows[] = $heading(lang('Receipts and payments'));
        $summary_rows[] = array(lang('Collections'), $money($collections));
        $summary_rows[] = array(lang('Payments'), $money($payments));

        if ($expenses_paid !== 0) {
            $summary_rows[] = array(lang('Expenses paid'), $money($expenses_paid));
        }

        $summary_rows[] = array();
    }

    $summary_rows[] = $heading(lang(array('string' => 'At {var:1}', 'vars' => prepare_form_data_for_output($to, 'date', false))));
    $summary_rows[] = array(lang('Receivables'), $money($receivable));
    $summary_rows[] = array(lang('Payables'), $money($payable));

    if (isset($sheets['stock'])) {
        $summary_rows[] = array(lang('Stock value (when the pack was prepared)'), $money($stock_value));
    }

    $summary_rows[] = array();
    $summary_rows[] = array(lang('Cancelled documents (listed, not counted)'), array('v' => $summary['cancelled'], 't' => 'int'));

    $sheets['summary']['rows'] = $summary_rows;

    return array('sheets' => $sheets, 'documents' => $files, 'expenses' => $expense_files, 'summary' => $summary);
}

/* --------------------------------------------------------------- workbook */

/**
 * A date as the number a spreadsheet counts days by (1900 system).
 *
 * @param string $ymd
 * @return int|null
 */
function erp_accountant_serial_date($ymd)
{
    if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', (string) $ymd, $parts) || ((int) $parts[1] === 0)) {
        return null;
    }

    return (int) round((gmmktime(0, 0, 0, (int) $parts[2], (int) $parts[3], (int) $parts[1]) - gmmktime(0, 0, 0, 12, 30, 1899)) / 86400);
}

/**
 * One cell. Money is written in the currency's units with two decimals,
 * dates as dates, so a filter or a SUM works in the accountant's program.
 *
 * @param string $reference
 * @param mixed  $value  A scalar typed by its column, or ['v' => value, 't' => type]
 * @param string $type   text | money | money_bold | date | int | number | bold
 * @return string
 */
function erp_accountant_cell($reference, $value, $type)
{
    if (is_array($value)) {
        $type = (string) ($value['t'] ?? $type);
        $value = $value['v'] ?? '';
    }

    if (($value === '') || ($value === null)) {
        return '';
    }

    $styles = array('money' => 2, 'date' => 3, 'number' => 4, 'money_bold' => 5, 'bold' => 6, 'int' => 7);

    if (($type === 'money') || ($type === 'money_bold')) {
        return '<c r="' . $reference . '" s="' . $styles[$type] . '"><v>' . number_format(((int) $value) / 100, 2, '.', '') . '</v></c>';
    }

    if ($type === 'date') {
        $serial = erp_accountant_serial_date((string) $value);

        if ($serial !== null) {
            return '<c r="' . $reference . '" s="3"><v>' . $serial . '</v></c>';
        }
    }

    if ((($type === 'number') || ($type === 'int')) && is_numeric($value)) {
        $number = is_float($value) ? rtrim(rtrim(number_format($value, 6, '.', ''), '0'), '.') : (string) $value;

        return '<c r="' . $reference . '" s="' . $styles[$type] . '"><v>' . erp_export_xml($number) . '</v></c>';
    }

    return '<c r="' . $reference . '"' . (($type === 'bold') ? ' s="6"' : '') . ' t="inlineStr"><is><t xml:space="preserve">' . erp_export_xml((string) $value) . '</t></is></c>';
}

/**
 * A workbook of several sheets. Each sheet has a bold heading row that stays
 * in view and carries filters, and column widths to suit its contents.
 *
 * @param array  $sheets  [['title', 'columns' => [['label', 'type', 'width']], 'rows', 'filter' => bool]]
 * @param string $path
 * @return bool
 */
function erp_accountant_write_workbook($sheets, $path)
{
    if (!class_exists('ZipArchive')) {
        return false;
    }

    $ns = 'http://schemas.openxmlformats.org/';
    $head = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n";
    $sheets = array_values($sheets);
    $parts = array();
    $overrides = '';
    $sheet_entries = '';
    $relations = '';
    $used_titles = array();

    foreach ($sheets as $index => $sheet) {
        $number = $index + 1;
        $columns = $sheet['columns'];
        $rows = $sheet['rows'];
        $last_column = erp_export_column_letter(max(0, count($columns) - 1));
        $last_row = count($rows) + 1;

        $cols = '<cols>';
        foreach ($columns as $c => $column) {
            $cols .= '<col min="' . ($c + 1) . '" max="' . ($c + 1) . '" width="' . max(6, (int) ($column['width'] ?? 12)) . '" customWidth="1"/>';
        }
        $cols .= '</cols>';

        $data = '<row r="1">';
        foreach ($columns as $c => $column) {
            $data .= '<c r="' . erp_export_column_letter($c) . '1" s="1" t="inlineStr"><is><t xml:space="preserve">' . erp_export_xml((string) $column['label']) . '</t></is></c>';
        }
        $data .= '</row>';

        foreach ($rows as $r => $row) {
            $row_number = $r + 2;
            $data .= '<row r="' . $row_number . '">';

            foreach (array_values((array) $row) as $c => $value) {
                $data .= erp_accountant_cell(erp_export_column_letter($c) . $row_number, $value, (string) ($columns[$c]['type'] ?? 'text'));
            }

            $data .= '</row>';
        }

        $parts['xl/worksheets/sheet' . $number . '.xml'] = $head .
            '<worksheet xmlns="' . $ns . 'spreadsheetml/2006/main">' .
            '<dimension ref="A1:' . $last_column . $last_row . '"/>' .
            '<sheetViews><sheetView workbookViewId="0"' . (($index === 0) ? ' tabSelected="1"' : '') . '><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>' .
            $cols .
            '<sheetData>' . $data . '</sheetData>' .
            ((($sheet['filter'] ?? true) && count($rows)) ? '<autoFilter ref="A1:' . $last_column . $last_row . '"/>' : '') .
            '</worksheet>';

        // Sheet names: 31 characters, none of []:*?/\ and unique.
        $title = mb_substr(trim(preg_replace('/[\[\]\*\?\/\\\\:]/', ' ', (string) $sheet['title'])), 0, 31);
        if (($title === '') || isset($used_titles[mb_strtolower($title)])) {
            $title = mb_substr($title, 0, 27) . ' ' . $number;
        }
        $used_titles[mb_strtolower($title)] = true;

        $overrides .= '<Override PartName="/xl/worksheets/sheet' . $number . '.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
        $sheet_entries .= '<sheet name="' . erp_export_xml($title) . '" sheetId="' . $number . '" r:id="rId' . $number . '"/>';
        $relations .= '<Relationship Id="rId' . $number . '" Type="' . $ns . 'officeDocument/2006/relationships/worksheet" Target="worksheets/sheet' . $number . '.xml"/>';

        // Excel keeps an autofilter's range as a hidden defined name.
        if (($sheet['filter'] ?? true) && count($rows)) {
            $sheets[$index]['_filter_name'] = '<definedName name="_xlnm._FilterDatabase" localSheetId="' . $index . '" hidden="1">\'' . str_replace("'", "''", erp_export_xml($title)) . '\'!$A$1:$' . $last_column . '$' . $last_row . '</definedName>';
        }
    }

    $defined = '';
    foreach ($sheets as $sheet) {
        $defined .= (string) ($sheet['_filter_name'] ?? '');
    }

    $count = count($sheets);

    $parts['[Content_Types].xml'] = $head .
        '<Types xmlns="' . $ns . 'package/2006/content-types">' .
        '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>' .
        '<Default Extension="xml" ContentType="application/xml"/>' .
        '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>' .
        $overrides .
        '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>' .
        '<Override PartName="/xl/sharedStrings.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/>' .
        '</Types>';
    $parts['_rels/.rels'] = $head .
        '<Relationships xmlns="' . $ns . 'package/2006/relationships">' .
        '<Relationship Id="rId1" Type="' . $ns . 'officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>' .
        '</Relationships>';
    $parts['xl/workbook.xml'] = $head .
        '<workbook xmlns="' . $ns . 'spreadsheetml/2006/main" xmlns:r="' . $ns . 'officeDocument/2006/relationships">' .
        '<bookViews><workbookView activeTab="0"/></bookViews>' .
        '<sheets>' . $sheet_entries . '</sheets>' .
        (($defined !== '') ? '<definedNames>' . $defined . '</definedNames>' : '') .
        '</workbook>';
    $parts['xl/_rels/workbook.xml.rels'] = $head .
        '<Relationships xmlns="' . $ns . 'package/2006/relationships">' .
        $relations .
        '<Relationship Id="rId' . ($count + 1) . '" Type="' . $ns . 'officeDocument/2006/relationships/styles" Target="styles.xml"/>' .
        '<Relationship Id="rId' . ($count + 2) . '" Type="' . $ns . 'officeDocument/2006/relationships/sharedStrings" Target="sharedStrings.xml"/>' .
        '</Relationships>';
    $parts['xl/sharedStrings.xml'] = $head . '<sst xmlns="' . $ns . 'spreadsheetml/2006/main" count="0" uniqueCount="0"/>';
    // Cell formats: 0 plain, 1 heading (bold, shaded), 2 money, 3 date,
    // 4 number, 5 money in bold, 6 text in bold, 7 whole number.
    $parts['xl/styles.xml'] = $head .
        '<styleSheet xmlns="' . $ns . 'spreadsheetml/2006/main">' .
        '<numFmts count="1"><numFmt numFmtId="164" formatCode="#,##0.######"/></numFmts>' .
        '<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts>' .
        '<fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill>' .
        '<fill><patternFill patternType="solid"><fgColor rgb="FFE7ECF3"/><bgColor indexed="64"/></patternFill></fill></fills>' .
        '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>' .
        '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>' .
        '<cellXfs count="8">' .
        '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>' .
        '<xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"/>' .
        '<xf numFmtId="4" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>' .
        '<xf numFmtId="14" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>' .
        '<xf numFmtId="164" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>' .
        '<xf numFmtId="4" fontId="1" fillId="0" borderId="0" xfId="0" applyNumberFormat="1" applyFont="1"/>' .
        '<xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/>' .
        '<xf numFmtId="3" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>' .
        '</cellXfs>' .
        '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>' .
        '</styleSheet>';

    $zip = new ZipArchive();

    if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        return false;
    }

    foreach ($parts as $name => $part) {
        $zip->addFromString($name, $part);
    }

    return $zip->close();
}

/* ------------------------------------------------------------------- pack */

/**
 * Where pack files are kept: under data/temp, which the web server does not
 * serve and which holds files the software can make again.
 *
 * @return string  '' when the folder cannot be made
 */
function erp_accountant_directory()
{
    $directory = PG_FUNCTIONS_DIR . '/data/temp/erp_packages';

    if (!is_dir($directory)) {
        @mkdir($directory, 0755, true);
    }

    if (!is_dir($directory)) {
        return '';
    }

    // A second lock on the door, for a server that serves data/ after all.
    if (!is_file($directory . '/.htaccess')) {
        @file_put_contents($directory . '/.htaccess', "Require all denied\n");
    }

    return $directory;
}

/**
 * A file name inside the pack, ASCII and without separators.
 *
 * @param string $name
 * @return string
 */
function erp_accountant_file_part($name)
{
    $name = strtr((string) $name, array('ç' => 'c', 'Ç' => 'C', 'ğ' => 'g', 'Ğ' => 'G', 'ı' => 'i', 'İ' => 'I', 'ö' => 'o', 'Ö' => 'O', 'ş' => 's', 'Ş' => 'S', 'ü' => 'u', 'Ü' => 'U', 'â' => 'a', 'Â' => 'A', 'î' => 'i', 'Î' => 'I', 'û' => 'u', 'Û' => 'U'));
    $name = trim(preg_replace('/[^A-Za-z0-9._-]+/', '_', $name), '_.');

    return ($name !== '') ? mb_substr($name, 0, 80) : 'document';
}

/**
 * The file of a document to put in the pack: the provider's official copy of
 * an e-document first, then the PDF kept when it was issued. A sales document
 * with neither is rendered and kept now, when $render allows; a purchase has
 * no document of ours to render - the supplier's is the one that counts.
 *
 * @param array $entry   From erp_accountant_collect() documents
 * @param bool  $render
 * @param int   $user_id
 * @return array|null  files row plus 'path'
 */
function erp_accountant_document_file($entry, $render, $user_id)
{
    $id = (int) $entry['document']['id'];
    $file = erp_archive_file('edoc', $id);

    if ($file === null) {
        $file = erp_archive_file('invoice', $id);
    }

    if (($file === null) && $render && ($entry['direction'] === 'sales')) {
        $file = erp_archive_invoice($id, $user_id);
    }

    return $file;
}

/**
 * Build a pack for a period and keep it.
 *
 * @param string $from
 * @param string $to
 * @param array  $options  pdfs (bool), cash (bool), source ('manual'|'monthly'), created_by
 * @return array ['success', 'error', 'id', 'warnings' => string[]]
 */
function erp_accountant_build($from, $to, $options = array())
{
    $fail = function ($message) {
        return array('success' => false, 'error' => $message, 'id' => 0, 'warnings' => array());
    };

    if (!erp_accountant_ready()) {
        return $fail(lang('The accountant pack comes with the software update; run the update to use it.'));
    }

    if (!class_exists('ZipArchive')) {
        return $fail(lang('The PHP zip extension is needed to build the pack.'));
    }

    $directory = erp_accountant_directory();

    if ($directory === '') {
        return $fail(lang('The folder for the packs could not be made under data/temp/.'));
    }

    if (function_exists('set_time_limit')) {
        @set_time_limit(300);
    }

    $created_by = (int) ($options['created_by'] ?? 0);
    $collected = erp_accountant_collect($from, $to, array('cash' => !array_key_exists('cash', $options) || !empty($options['cash'])));
    $label = erp_accountant_period_label($from, $to);
    $token = bin2hex(random_bytes(8));
    $workbook_path = $directory . '/book_' . $token . '.xlsx';
    $zip_name = 'accountant_' . $label . '_' . $token . '.zip';
    $zip_path = $directory . '/' . $zip_name;
    $warnings = array();

    if (!erp_accountant_write_workbook($collected['sheets'], $workbook_path)) {
        @unlink($workbook_path);
        return $fail(lang('The workbook could not be written.'));
    }

    $zip = new ZipArchive();

    if ($zip->open($zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        @unlink($workbook_path);
        return $fail(lang('The pack could not be written.'));
    }

    $prefix = erp_accountant_file_part(lang('Accounting')) . '_' . $label;
    $zip->addFile($workbook_path, $prefix . '.xlsx');

    $missing = 0;
    $added = 0;

    if (!array_key_exists('pdfs', $options) || !empty($options['pdfs'])) {
        $folders = array(
            'sales' => erp_accountant_file_part(lang('Sales invoices')),
            'sales_return' => erp_accountant_file_part(lang('Sales returns')),
            'purchase' => erp_accountant_file_part(lang('Purchase invoices')),
            'purchase_return' => erp_accountant_file_part(lang('Returns to suppliers')),
        );
        $names = array();

        foreach ($collected['documents'] as $entry) {
            $document = $entry['document'];
            $file = erp_accountant_document_file($entry, true, $created_by);

            if ($file === null) {
                $missing++;
                continue;
            }

            $folder = $folders[$entry['direction'] . ($entry['return'] ? '_return' : '')];
            $base_name = erp_accountant_file_part(((string) $document['full_number'] !== '') ? $document['full_number'] : ('#' . (int) $document['id']));

            if (((string) $document['status'] === 'cancelled')) {
                $base_name .= '_' . erp_accountant_file_part(lang('Cancelled'));
            }

            $extension = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));
            $name = $folder . '/' . $base_name . '.' . (($extension !== '') ? $extension : 'pdf');

            for ($n = 2; isset($names[$name]); $n++) {
                $name = $folder . '/' . $base_name . '_' . $n . '.' . (($extension !== '') ? $extension : 'pdf');
            }

            $names[$name] = true;
            $zip->addFile($file['path'], 'belgeler/' . $name);
            $added++;
        }

        if ($missing > 0) {
            $warnings[] = lang(array('string' => '{var:1} document(s) have no kept copy to add (a purchase invoice typed by hand has no file of its own).', 'vars' => $missing));
        }

        // The receipts kept with the expenses, named by date and number.
        $expense_folder = erp_accountant_file_part(lang('Expenses'));

        foreach ((array) ($collected['expenses'] ?? array()) as $expense) {
            $file = erp_archive_file('expense', (int) $expense['id']);

            if ($file === null) {
                continue;
            }

            $base_name = erp_accountant_file_part((string) $expense['expense_date'] . '_'
                . (((string) $expense['document_no'] !== '') ? (string) $expense['document_no'] : ('G' . (int) $expense['id'])));

            if ((string) $expense['status'] === 'cancelled') {
                $base_name .= '_' . erp_accountant_file_part(lang('Cancelled'));
            }

            $extension = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));
            $name = $expense_folder . '/' . $base_name . '.' . $extension;

            for ($n = 2; isset($names[$name]); $n++) {
                $name = $expense_folder . '/' . $base_name . '_' . $n . '.' . $extension;
            }

            $names[$name] = true;
            $zip->addFile($file['path'], 'belgeler/' . $name);
            $added++;
        }
    }

    $closed = $zip->close();
    @unlink($workbook_path);

    if (!$closed || !is_file($zip_path)) {
        @unlink($zip_path);
        return $fail(lang('The pack could not be written.'));
    }

    $source = (($options['source'] ?? 'manual') === 'monthly') ? 'monthly' : 'manual';

    $ok = db("INSERT INTO erp_accountant_packages SET
            period_from = '" . escape($from) . "',
            period_to = '" . escape($to) . "',
            file_name = '" . escape($zip_name) . "',
            file_size = '" . (int) @filesize($zip_path) . "',
            documents = '" . (int) count($collected['documents']) . "',
            files_added = '" . (int) $added . "',
            summary = '" . escape(json_encode($collected['summary'])) . "',
            source = '" . $source . "',
            created_by = '" . $created_by . "',
            created_at = '" . time() . "'");

    if ($ok === false) {
        @unlink($zip_path);
        return $fail(lang('The pack could not be recorded.'));
    }

    return array('success' => true, 'error' => '', 'id' => (int) mysqli_insert_id(db::$con), 'warnings' => $warnings);
}

/**
 * One pack, with where its file is.
 *
 * @param int $id
 * @return array|null
 */
function erp_accountant_package($id)
{
    if (!erp_accountant_ready()) {
        return null;
    }

    $row = db_item("SELECT * FROM erp_accountant_packages WHERE id = '" . (int) $id . "' LIMIT 1");

    if (!is_array($row)) {
        return null;
    }

    $row['path'] = erp_accountant_directory() . '/' . basename((string) $row['file_name']);
    $row['exists'] = is_file($row['path']);

    return $row;
}

/**
 * The latest packs, newest first.
 *
 * @param int $limit
 * @return array
 */
function erp_accountant_packages($limit = 24)
{
    if (!erp_accountant_ready()) {
        return array();
    }

    return (array) db_items("SELECT p.*, u.user_username AS created_by_name
        FROM erp_accountant_packages p
        LEFT JOIN user u ON u.user_id = p.created_by
        ORDER BY p.id DESC
        LIMIT " . max(1, (int) $limit));
}

/**
 * The name a pack is downloaded as.
 *
 * @param array $package
 * @return string
 */
function erp_accountant_download_name($package)
{
    $business = defined('ORGANIZATION_NAME') ? erp_accountant_file_part((string) ORGANIZATION_NAME) : '';

    return (($business !== '' && $business !== 'document') ? $business . '_' : '')
        . erp_accountant_file_part(lang('Accounting')) . '_'
        . erp_accountant_period_label((string) $package['period_from'], (string) $package['period_to']) . '.zip';
}

/**
 * Send a pack's file to the browser.
 *
 * @param array $package
 * @return bool  false when the file is gone
 */
function erp_accountant_stream($package)
{
    if (empty($package['exists'])) {
        return false;
    }

    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . erp_accountant_download_name($package) . '"');
    header('Content-Length: ' . (int) filesize($package['path']));
    header('Cache-Control: private, no-store');
    header('X-Content-Type-Options: nosniff');
    readfile($package['path']);

    return true;
}

/**
 * Give a pack a new link and return its address. The token itself is not
 * kept, only its hash: the address is only ever in the message that carries
 * it.
 *
 * @param int $id
 * @param int $days
 * @return string  '' on failure
 */
function erp_accountant_new_link($id, $days = ERP_ACCOUNTANT_LINK_DAYS)
{
    $token = bin2hex(random_bytes(24));

    $ok = db("UPDATE erp_accountant_packages SET
            token_hash = '" . hash('sha256', $token) . "',
            expires_at = '" . (time() + max(1, (int) $days) * 86400) . "'
        WHERE id = '" . (int) $id . "'");

    if ($ok === false) {
        return '';
    }

    return URL_SCHEME . HOSTNAME_SETTING . PATH . SOFTWARE_DIRECTORY . '/erp_accountant_download.php?t=' . $token;
}

/**
 * The pack a link opens, or null for a link that is not one, has run out or
 * whose file is gone.
 *
 * @param string $token
 * @return array|null
 */
function erp_accountant_package_by_token($token)
{
    if (!erp_accountant_ready() || (preg_match('/^[a-f0-9]{48}$/', (string) $token) !== 1)) {
        return null;
    }

    $id = (int) db_value("SELECT id FROM erp_accountant_packages
        WHERE token_hash = '" . hash('sha256', (string) $token) . "' AND expires_at > '" . time() . "'
        LIMIT 1");

    $package = ($id > 0) ? erp_accountant_package($id) : null;

    return (is_array($package) && $package['exists']) ? $package : null;
}

/**
 * Count a download.
 *
 * @param int $id
 * @return void
 */
function erp_accountant_count_download($id)
{
    db("UPDATE erp_accountant_packages SET downloads = downloads + 1, last_download_at = '" . time() . "' WHERE id = '" . (int) $id . "'");
}

/**
 * E-mail the accountant a link to a pack.
 *
 * @param int    $id
 * @param string $to
 * @param int    $user_id
 * @return array ['success' => bool, 'error' => string]
 */
function erp_accountant_send($id, $to, $user_id = 0)
{
    $to = trim((string) $to);

    if (($to === '') || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return array('success' => false, 'error' => lang('Please enter a valid e-mail address for the accountant.'));
    }

    $package = erp_accountant_package($id);

    if (!is_array($package) || !$package['exists']) {
        return array('success' => false, 'error' => lang('That pack is no longer there. Build it again.'));
    }

    $sender = erp_reconciliation_sender();

    if ($sender === '') {
        return array('success' => false, 'error' => lang('The store has no e-mail address to send from. Set one under Settings.'));
    }

    // A failed e-mail must not cost the accountant the link they already have.
    $previous = db_item("SELECT token_hash, expires_at FROM erp_accountant_packages WHERE id = '" . (int) $id . "'");
    $link = erp_accountant_new_link($id);

    if ($link === '') {
        return array('success' => false, 'error' => lang('The link could not be made.'));
    }

    $period = prepare_form_data_for_output((string) $package['period_from'], 'date', false) . ' – ' . prepare_form_data_for_output((string) $package['period_to'], 'date', false);
    $business = defined('ORGANIZATION_NAME') ? (string) ORGANIZATION_NAME : '';
    $expires = prepare_form_data_for_output(date('Y-m-d', time() + ERP_ACCOUNTANT_LINK_DAYS * 86400), 'date', false);
    $size = number_format(((int) $package['file_size']) / 1048576, 1, erp_number_separators()['decimal'], '');

    $body = '<!DOCTYPE html><html><body style="font-family: Arial, Helvetica, sans-serif; color: #222222; margin: 0; padding: 20px;">'
        . '<div style="max-width: 620px; margin: 0 auto;">'
        . '<p style="font-size: 15px; line-height: 1.5;">' . h(lang(array('string' => 'The accounting records of {var:1} for {var:2} are ready.', 'vars' => array($business, $period)))) . '</p>'
        . '<p style="font-size: 15px; line-height: 1.5;">' . h(lang('The pack holds a workbook (sales and purchase invoices, returns, VAT by rate, receipts and payments, tills, account balances, stock value) and the documents as they were issued.')) . '</p>'
        . '<p style="margin: 24px 0;"><a href="' . h($link) . '" style="background: #0d6efd; color: #ffffff; padding: 12px 20px; border-radius: 6px; text-decoration: none; font-weight: bold;">' . h(lang('Download the pack')) . '</a></p>'
        . '<p style="font-size: 13px; color: #555555; line-height: 1.5;">' . h(lang(array('string' => 'ZIP, {var:1} MB. The link works until {var:2}; ask for a new one after that.', 'vars' => array($size, $expires)))) . '</p>'
        . '</div></body></html>';

    $sent = email(array(
        'to' => $to,
        'from_name' => $business,
        'from_email_address' => $sender,
        'reply_to' => $sender,
        'subject' => lang(array('string' => '{var:1} - accounting records, {var:2}', 'vars' => array($business, $period))),
        'format' => 'html',
        'body' => $body,
        'type' => 'system',
    ));

    if (!$sent) {
        if (is_array($previous)) {
            db("UPDATE erp_accountant_packages SET
                    token_hash = '" . escape((string) $previous['token_hash']) . "',
                    expires_at = '" . (int) $previous['expires_at'] . "'
                WHERE id = '" . (int) $id . "'");
        }

        return array('success' => false, 'error' => lang('The e-mail could not be sent.'));
    }

    db("UPDATE erp_accountant_packages SET sent_to = '" . escape(mb_substr($to, 0, 255)) . "', sent_at = '" . time() . "', sent_by = '" . (int) $user_id . "'
        WHERE id = '" . (int) $id . "'");

    if (function_exists('log_activity')) {
        log_activity(lang(array('string' => 'Accountant pack for {var:1} sent to {var:2}.', 'vars' => array($period, $to))));
    }

    return array('success' => true, 'error' => '');
}

/**
 * The monthly run: last month's pack, built and sent to the accountant once.
 * Nothing happens when the switch is off, no address is set, or last month's
 * monthly pack has already been sent; one that was built but could not be
 * sent is sent again.
 *
 * @return array ['ran' => bool, 'reason' => string, 'id' => int]
 */
function erp_accountant_monthly_run()
{
    $settings = erp_accountant_settings();

    if (!$settings['monthly'] || ($settings['email'] === '')) {
        return array('ran' => false, 'reason' => 'off', 'id' => 0);
    }

    $period = erp_accountant_last_month();

    // Built and sent is done. Built but not sent (the mail server was down)
    // is sent again on the next run, without building the pack twice.
    $existing = db_item("SELECT id, sent_at FROM erp_accountant_packages
        WHERE source = 'monthly' AND period_from = '" . escape($period['from']) . "' AND period_to = '" . escape($period['to']) . "'
        ORDER BY id DESC
        LIMIT 1");

    if (is_array($existing) && ((int) $existing['sent_at'] > 0)) {
        return array('ran' => false, 'reason' => 'done', 'id' => (int) $existing['id']);
    }

    $package = is_array($existing) ? erp_accountant_package((int) $existing['id']) : null;

    if (is_array($package) && $package['exists']) {
        $id = (int) $package['id'];
    } else {
        $built = erp_accountant_build($period['from'], $period['to'], array('pdfs' => $settings['pdfs'], 'cash' => true, 'source' => 'monthly'));

        if (!$built['success']) {
            return array('ran' => false, 'reason' => $built['error'], 'id' => 0);
        }

        $id = (int) $built['id'];
    }

    $sent = erp_accountant_send($id, $settings['email']);

    return array('ran' => true, 'reason' => $sent['success'] ? 'sent' : $sent['error'], 'id' => $id);
}
