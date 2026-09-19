<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * ERP - exporting accounts, invoices and receipts as files.
 *
 * The ledger is not the last stop for its own figures: an accountant's
 * package, a tax adviser, a spreadsheet all want them in their own shape. A
 * profile describes one such shape - the columns, how a row of ours maps onto
 * them, how money and dates are written - and the screen only chooses a
 * profile and a range. Adding a program to export to is adding a profile, not
 * a screen.
 *
 * Two families ship: plain CSV in the conventions the import screen reads
 * back (UTF-8 with a byte order mark, semicolons, ISO dates, a dot decimal),
 * and the Excel templates Paraşüt hands out for its own import, filled from
 * their fourth row so the help text and the header rows stay exactly as that
 * program expects them.
 *
 * Nothing here reads the request or prints HTML, so the profiles can be run
 * against fixture rows from the command line.
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
 * How many rows one export may carry. A larger book belongs in several runs.
 */
if (!defined('ERP_EXPORT_MAX_ROWS')) {
    define('ERP_EXPORT_MAX_ROWS', 20000);
}

// -- profiles ------------------------------------------------------------------

/**
 * The shapes a file can take.
 *
 * entity    accounts | invoices | receipts - what the profile is fed
 * format    csv | xlsx
 * group     generic | parasut - how the screen groups the choice
 * decimal   the decimal separator for CSV text (spreadsheet cells are numeric)
 * date      the PHP date format the profile writes
 * currency_map  ISO code => the code the target program uses
 * template  spreadsheet template under includes/phpexcel/templates, or ''
 * first_row the first data row of that template
 *
 * @return array  profile id => definition
 */
function erp_export_profiles()
{
    static $profiles = null;

    if ($profiles !== null) {
        return $profiles;
    }

    $csv = array('format' => 'csv', 'group' => 'generic', 'decimal' => '.', 'date' => 'Y-m-d', 'currency_map' => array(), 'template' => '', 'first_row' => 2);
    $parasut = array('format' => 'xlsx', 'group' => 'parasut', 'decimal' => '.', 'date' => 'd.m.Y', 'currency_map' => array('TRY' => 'TRL'), 'first_row' => 4);

    $profiles = array(
        'csv_invoices' => $csv + array('entity' => 'invoices', 'label' => lang('Invoices (CSV, one row per line)'), 'file' => 'invoices'),
        'csv_accounts' => $csv + array('entity' => 'accounts', 'label' => lang('Accounts (CSV)'), 'file' => 'accounts'),
        'csv_receipts' => $csv + array('entity' => 'receipts', 'label' => lang('Receipts and payments (CSV)'), 'file' => 'receipts'),
        'parasut_invoices' => $parasut + array('entity' => 'invoices', 'label' => lang('Paraşüt - sales invoices (Excel)'), 'file' => 'parasut_satis_faturalari', 'template' => 'parasut_satis_faturalari.xlsx'),
        'parasut_contacts' => $parasut + array('entity' => 'accounts', 'label' => lang('Paraşüt - customers and suppliers (Excel)'), 'file' => 'parasut_musteri_ve_tedarikciler', 'template' => 'parasut_musteri_ve_tedarikciler.xlsx'),
    );

    return $profiles;
}

/**
 * One profile, or null for an id that is not one.
 *
 * @param string $id
 * @return array|null
 */
function erp_export_profile($id)
{
    $profiles = erp_export_profiles();

    if (!isset($profiles[$id])) {
        return null;
    }

    $profile = $profiles[$id];
    $profile['id'] = $id;

    return $profile;
}

/**
 * The column headings a profile writes.
 *
 * For a spreadsheet template these are the template's own headings and are
 * only used when the template file is missing and a plain sheet is built
 * instead.
 *
 * @param array $profile
 * @return array
 */
function erp_export_columns($profile)
{
    switch ($profile['id']) {
        case 'csv_accounts':
            // The same headings the import screen maps by itself, so a file
            // exported here can be loaded back.
            return array(lang('Name'), lang('Type'), lang('Taxpayer'), lang('VKN / TCKN'), lang('Tax Office'), lang('E-mail Address'),
                lang('Phone Number'), lang('Address'), lang('District'), lang('City'), lang('Postal Code'), lang('Country'),
                lang('Currency'), lang('Status'), lang('Notes'), lang('Payment Term (days)'), lang('Balance'), lang('Account ID'));

        case 'csv_invoices':
            return array(lang('Invoice Number'), lang('Date'), lang('Due Date'), lang('Direction'), lang('Document Type'), lang('Status'),
                lang('Account'), lang('VKN / TCKN'), lang('Tax Office'), lang('Country'), lang('Currency'), lang('Exchange Rate'),
                lang('Line No'), lang('Description'), lang('Quantity'), lang('Unit'), lang('Unit Price'), lang('Discount'), lang('VAT Rate'),
                lang('VAT'), lang('Line Total'), lang('Invoice Subtotal'), lang('Invoice VAT'), lang('Invoice Total'),
                lang('Invoice Total (base currency)'), lang('Paid'), lang('Order Number'), lang('Invoice ID'));

        case 'csv_receipts':
            return array(lang('Date'), lang('Direction'), lang('Account'), lang('VKN / TCKN'), lang('Till or Bank Account'),
                lang('Payment Method'), lang('Amount'), lang('Currency'), lang('Exchange Rate'), lang('Amount (base currency)'),
                lang('Description'), lang('Receipt ID'));

        case 'parasut_contacts':
            return array('FİRMA ADI *', 'KISA ADI', 'MÜŞTERİ/TEDARİKÇİ *', 'E-POSTA', 'KATEGORİ', 'ADRES', 'İLÇE', 'İL', 'TELEFON', 'FAKS',
                'VERGİ DAİRESİ', 'VERGİ NUMARASI', 'TC KİMLİK NO', 'AÇILIŞ BAKİYESİ TARİHİ', 'AÇILIŞ BAKİYESİ DÖVİZ CİNSİ',
                'AÇILIŞ BAKİYESİ TUTARI', 'AÇILIŞ BAKİYESİ TÜRÜ');

        case 'parasut_invoices':
            return array('MÜŞTERİ ÜNVANI *', 'FATURA İSMİ', 'FATURA TARİHİ', 'DÖVİZ CİNSİ', 'DÖVİZ KURU', 'VADE TARİHİ',
                'TAHSİLAT TL KARŞILIĞI', 'FATURA TÜRÜ', 'FATURA SERİ', 'FATURA SIRA NO', 'KATEGORİ', 'HİZMET/ÜRÜN *',
                'HİZMET/ÜRÜN AÇIKLAMASI', 'ÇIKIŞ DEPOSU *', 'MİKTAR *', 'BİRİM FİYATI *', 'İNDİRİM TUTARI', 'KDV ORANI *',
                'ÖİV ORANI', 'KONAKLAMA VERGİSİ ORANI');
    }

    return array();
}

// -- the single formatting point -------------------------------------------------

/**
 * Kurus as the profile writes money.
 *
 * Text with two decimals for CSV; a number for a spreadsheet cell, where the
 * reader's own locale decides how it is shown.
 *
 * @param int   $kurus
 * @param array $profile
 * @return string|float
 */
function erp_export_amount($kurus, $profile)
{
    $value = ((int) $kurus) / 100;

    if ($profile['format'] === 'xlsx') {
        return round($value, 2);
    }

    return number_format($value, 2, $profile['decimal'], '');
}

/**
 * A rate (VAT, discount, exchange) without trailing zeros.
 *
 * @param float|string $rate
 * @param array        $profile
 * @param int          $decimals  Most decimals to keep
 * @return string|float|int
 */
function erp_export_rate($rate, $profile, $decimals = 6)
{
    $rate = round((float) $rate, $decimals);

    if ($profile['format'] === 'xlsx') {
        return ($rate == floor($rate)) ? (int) $rate : $rate;
    }

    $text = rtrim(rtrim(number_format($rate, $decimals, '.', ''), '0'), '.');

    return ($profile['decimal'] === '.') ? $text : str_replace('.', $profile['decimal'], $text);
}

/**
 * A stored Y-m-d date as the profile writes it; the zero date is nothing.
 *
 * @param string $ymd
 * @param array  $profile
 * @return string
 */
function erp_export_date($ymd, $profile)
{
    $ymd = (string) $ymd;

    if (($ymd === '') || (strpos($ymd, '0000-00-00') === 0)) {
        return '';
    }

    $time = strtotime($ymd . ' 00:00:00');

    return ($time === false) ? '' : date($profile['date'], $time);
}

/**
 * A currency code as the target program spells it.
 *
 * @param string $code
 * @param array  $profile
 * @return string
 */
function erp_export_currency($code, $profile)
{
    $code = strtoupper(trim((string) $code));

    if ($code === '') {
        $code = erp_base_currency();
    }

    return isset($profile['currency_map'][$code]) ? $profile['currency_map'][$code] : $code;
}

// -- fetching ---------------------------------------------------------------------

/**
 * The filters a run is made with, cleaned.
 *
 * @param array $input  profile, from, to, direction, include_drafts,
 *                      include_passive, not_exported
 * @return array
 */
function erp_export_filters($input)
{
    $date = function ($value) {
        $value = trim((string) $value);
        return (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1) ? $value : '';
    };

    return array(
        'from' => $date($input['from'] ?? ''),
        'to' => $date($input['to'] ?? ''),
        'direction' => (($input['direction'] ?? 'sales') === 'purchase') ? 'purchase' : 'sales',
        'include_drafts' => !empty($input['include_drafts']),
        'include_passive' => !empty($input['include_passive']),
        'not_exported' => !empty($input['not_exported']),
    );
}

/**
 * SQL that leaves out records already written by this profile.
 *
 * @param string $entity  account | invoice | receipt
 * @param string $id_column
 * @param array  $profile
 * @return string
 */
function erp_export_not_exported_sql($entity, $id_column, $profile)
{
    return " AND NOT EXISTS (SELECT 1 FROM erp_export_log l
        WHERE l.entity = '" . escape($entity) . "' AND l.doc_id = " . $id_column . " AND l.profile = '" . escape($profile['id']) . "')";
}

/**
 * Accounts for an accounts profile.
 *
 * @param array $filters
 * @param array $profile
 * @return array
 */
function erp_export_fetch_accounts($filters, $profile)
{
    $where = "1 = 1";

    if (!$filters['include_passive']) {
        $where .= " AND a.status = 'active'";
    }

    if ($filters['not_exported']) {
        $where .= erp_export_not_exported_sql('account', 'a.id', $profile);
    }

    return (array) db_items("SELECT a.* FROM erp_accounts a WHERE " . $where . " ORDER BY a.title ASC, a.id ASC LIMIT " . (int) ERP_EXPORT_MAX_ROWS);
}

/**
 * Invoices, each with its lines, for an invoices profile.
 *
 * Cancelled documents never leave: they are reversed in the ledger and a
 * program that received them would carry a document ours does not. Returns are
 * counted and left out - the spreadsheet templates have no row for them - and
 * the count is handed back so the screen can say so.
 *
 * @param array $filters
 * @param array $profile
 * @return array ['invoices' => array (each with 'lines' and 'settled_base'), 'returns_skipped' => int]
 */
function erp_export_fetch_invoices($filters, $profile)
{
    $where = "i.direction = '" . escape($filters['direction']) . "' AND i.status <> 'cancelled'";

    $statuses = array('issued', 'partially_paid', 'paid');
    if ($filters['include_drafts']) {
        $statuses[] = 'draft';
    }
    $where .= " AND i.status IN ('" . implode("','", $statuses) . "')";

    if ($filters['from'] !== '') {
        $where .= " AND i.issue_date >= '" . escape($filters['from']) . "'";
    }
    if ($filters['to'] !== '') {
        $where .= " AND i.issue_date <= '" . escape($filters['to']) . "'";
    }

    $returns_skipped = (int) db_value("SELECT COUNT(*) FROM erp_invoices i WHERE " . $where . " AND i.doc_type = 'return'");

    $where .= " AND i.doc_type <> 'return'";

    if ($filters['not_exported']) {
        $where .= erp_export_not_exported_sql('invoice', 'i.id', $profile);
    }

    $invoices = (array) db_items("SELECT i.*, a.title AS live_title, a.tax_number AS live_tax_number, a.tax_office AS live_tax_office,
            a.country_code AS live_country_code, a.is_person, o.order_number
        FROM erp_invoices i
        LEFT JOIN erp_accounts a ON i.account_id = a.id
        LEFT JOIN orders o ON i.order_id = o.id
        WHERE " . $where . "
        ORDER BY i.issue_date ASC, i.id ASC
        LIMIT " . (int) ERP_EXPORT_MAX_ROWS, 'id');

    if (empty($invoices)) {
        return array('invoices' => array(), 'returns_skipped' => $returns_skipped);
    }

    $ids = implode(',', array_map('intval', array_keys($invoices)));

    foreach ($invoices as $id => $unused) {
        $invoices[$id]['lines'] = array();
        $invoices[$id]['settled_base'] = 0;
    }

    $lines = (array) db_items("SELECT * FROM erp_invoice_items WHERE invoice_id IN (" . $ids . ") ORDER BY invoice_id ASC, line_no ASC, id ASC");
    foreach ($lines as $line) {
        $invoices[(int) $line['invoice_id']]['lines'][] = $line;
    }

    // What was collected against a foreign-currency invoice, in the base
    // currency: the figure the accounting side books the receipt at.
    $settled = (array) db_items("SELECT invoice_id, SUM(amount_base) AS settled_base FROM erp_settlements WHERE invoice_id IN (" . $ids . ") GROUP BY invoice_id");
    foreach ($settled as $row) {
        $invoices[(int) $row['invoice_id']]['settled_base'] = (int) $row['settled_base'];
    }

    return array('invoices' => array_values($invoices), 'returns_skipped' => $returns_skipped);
}

/**
 * Collections and payments for a receipts profile.
 *
 * @param array $filters
 * @param array $profile
 * @return array
 */
function erp_export_fetch_receipts($filters, $profile)
{
    $where = "c.doc_type IN ('collection','payment')";

    if ($filters['from'] !== '') {
        $where .= " AND c.doc_date >= '" . escape($filters['from']) . "'";
    }
    if ($filters['to'] !== '') {
        $where .= " AND c.doc_date <= '" . escape($filters['to']) . "'";
    }

    if ($filters['not_exported']) {
        $where .= erp_export_not_exported_sql('receipt', 'c.id', $profile);
    }

    return (array) db_items("SELECT c.*, a.title AS account_title, a.tax_number AS account_tax_number, t.name AS till_name
        FROM erp_cash_transactions c
        LEFT JOIN erp_accounts a ON c.account_id = a.id
        LEFT JOIN erp_cash_accounts t ON c.cash_account_id = t.id
        WHERE " . $where . "
        ORDER BY c.doc_date ASC, c.id ASC
        LIMIT " . (int) ERP_EXPORT_MAX_ROWS);
}

// -- row mappers -----------------------------------------------------------------

/**
 * The words the generic files use for an account's kind, taxpayer and status.
 *
 * Written as the import screen reads them, so the file loads back.
 *
 * @return array
 */
function erp_export_account_words()
{
    return array(
        'kind' => array('customer' => lang('Customer'), 'supplier' => lang('Supplier'), 'both' => lang('Customer and supplier')),
        'person' => array(1 => lang('Person'), 0 => lang('Company')),
        'status' => array('active' => lang('Active'), 'passive' => lang('Passive')),
    );
}

/**
 * Rows for the generic accounts file.
 *
 * @param array $accounts
 * @param array $profile
 * @return array
 */
function erp_export_rows_csv_accounts($accounts, $profile)
{
    $words = erp_export_account_words();
    $rows = array();

    foreach ($accounts as $a) {
        $rows[] = array(
            (string) $a['title'],
            isset($words['kind'][$a['kind']]) ? $words['kind'][$a['kind']] : (string) $a['kind'],
            $words['person'][((int) $a['is_person'] === 1) ? 1 : 0],
            (string) $a['tax_number'],
            (string) $a['tax_office'],
            (string) $a['email'],
            (string) $a['phone'],
            (string) $a['address'],
            (string) $a['district'],
            (string) $a['city'],
            (string) $a['postcode'],
            strtoupper((string) $a['country_code']),
            erp_export_currency($a['currency'], $profile),
            isset($words['status'][$a['status']]) ? $words['status'][$a['status']] : (string) $a['status'],
            (string) $a['notes'],
            (string) (int) ($a['payment_days'] ?? 0),
            erp_export_amount($a['balance'], $profile),
            (string) (int) $a['id'],
        );
    }

    return $rows;
}

/**
 * Rows for the generic invoices file: one row per line, the header repeated.
 *
 * @param array $invoices  From erp_export_fetch_invoices()
 * @param array $profile
 * @return array
 */
function erp_export_rows_csv_invoices($invoices, $profile)
{
    $rows = array();
    $direction_words = array('sales' => lang('Sales'), 'purchase' => lang('Purchase'));
    $type_words = array('invoice' => lang('Invoice'), 'proforma' => lang('Proforma'), 'return' => lang('Return'));

    foreach ($invoices as $i) {
        $header = erp_export_invoice_party($i);
        $lines = !empty($i['lines']) ? $i['lines'] : array(array('line_no' => 0, 'description' => '', 'quantity' => 0, 'unit_code' => '', 'unit_price' => 0, 'discount_amount' => 0, 'tax_rate' => 0, 'tax_total' => 0, 'line_total' => 0));

        foreach ($lines as $line) {
            $rows[] = array(
                (string) $i['full_number'],
                erp_export_date($i['issue_date'], $profile),
                erp_export_date($i['due_date'], $profile),
                isset($direction_words[$i['direction']]) ? $direction_words[$i['direction']] : (string) $i['direction'],
                isset($type_words[$i['doc_type']]) ? $type_words[$i['doc_type']] : (string) $i['doc_type'],
                (string) $i['status'],
                $header['title'],
                $header['tax_number'],
                $header['tax_office'],
                $header['country'],
                erp_export_currency($i['currency'], $profile),
                erp_export_rate($i['exchange_rate'], $profile, 6),
                (string) (int) $line['line_no'],
                (string) $line['description'],
                erp_export_rate($line['quantity'], $profile, 4),
                (string) $line['unit_code'],
                erp_export_amount($line['unit_price'], $profile),
                erp_export_amount($line['discount_amount'], $profile),
                erp_export_rate($line['tax_rate'], $profile, 3),
                erp_export_amount($line['tax_total'], $profile),
                erp_export_amount($line['line_total'], $profile),
                erp_export_amount($i['subtotal'], $profile),
                erp_export_amount($i['tax_total'], $profile),
                erp_export_amount($i['grand_total'], $profile),
                erp_export_amount($i['grand_total_base'], $profile),
                erp_export_amount($i['paid_total'], $profile),
                (string) ($i['order_number'] ?? ''),
                (string) (int) $i['id'],
            );
        }
    }

    return $rows;
}

/**
 * Rows for the generic receipts file.
 *
 * @param array $receipts
 * @param array $profile
 * @return array
 */
function erp_export_rows_csv_receipts($receipts, $profile)
{
    $rows = array();
    $direction_words = array('collection' => lang('Collection'), 'payment' => lang('Payment'));

    foreach ($receipts as $r) {
        $rows[] = array(
            erp_export_date($r['doc_date'], $profile),
            isset($direction_words[$r['doc_type']]) ? $direction_words[$r['doc_type']] : (string) $r['doc_type'],
            (string) ($r['account_title'] ?? ''),
            (string) ($r['account_tax_number'] ?? ''),
            (string) ($r['till_name'] ?? ''),
            (string) $r['payment_method'],
            erp_export_amount($r['amount'], $profile),
            erp_export_currency($r['currency'], $profile),
            erp_export_rate($r['exchange_rate'], $profile, 6),
            erp_export_amount($r['amount_base'], $profile),
            (string) $r['description'],
            (string) (int) $r['id'],
        );
    }

    return $rows;
}

/**
 * Who an invoice was made out to: the copy taken when it was issued, or the
 * live card for documents older than the copy.
 *
 * @param array $invoice
 * @return array title, tax_number, tax_office, country, is_person
 */
function erp_export_invoice_party($invoice)
{
    $snapshot = (trim((string) ($invoice['account_title'] ?? '')) !== '');

    return array(
        'title' => $snapshot ? (string) $invoice['account_title'] : (string) ($invoice['live_title'] ?? ''),
        'tax_number' => $snapshot ? (string) $invoice['account_tax_number'] : (string) ($invoice['live_tax_number'] ?? ''),
        'tax_office' => $snapshot ? (string) $invoice['account_tax_office'] : (string) ($invoice['live_tax_office'] ?? ''),
        'country' => strtoupper($snapshot ? (string) $invoice['account_country_code'] : (string) ($invoice['live_country_code'] ?? '')),
        'is_person' => ((int) ($invoice['is_person'] ?? 1) === 1),
    );
}

/**
 * Rows for Paraşüt's customers and suppliers template.
 *
 * A company is identified by tax office and tax number, a person by the
 * national identity number, and the template keeps them in different
 * columns. The opening balance columns are left empty on purpose: balances
 * here are the sum of movements, and handing the figure over as an opening
 * entry would count it twice on the other side.
 *
 * @param array $accounts
 * @param array $profile
 * @return array
 */
function erp_export_rows_parasut_contacts($accounts, $profile)
{
    $rows = array();

    foreach ($accounts as $a) {
        $person = ((int) $a['is_person'] === 1);
        $address = trim((string) $a['address']);
        if (trim((string) $a['postcode']) !== '') {
            $address = trim($address . ' ' . $a['postcode']);
        }

        $rows[] = array(
            (string) $a['title'],
            '',
            ((string) $a['kind'] === 'supplier') ? 'T' : 'M',
            (string) $a['email'],
            '',
            $address,
            (string) $a['district'],
            (string) $a['city'],
            (string) $a['phone'],
            '',
            $person ? '' : (string) $a['tax_office'],
            $person ? '' : (string) $a['tax_number'],
            $person ? (string) $a['tax_number'] : '',
            '', '', '', '',
        );
    }

    return $rows;
}

/**
 * Rows for Paraşüt's sales invoices template.
 *
 * The header columns are written on a document's first line only; the lines
 * that follow carry the goods columns alone, which is how that template
 * reads a multi-line invoice. Unit prices are net: the template adds VAT
 * from the rate, and ours are stored net with the line tax beside them, so
 * nothing is backed out. The exchange rate goes only on a foreign-currency
 * document, and the base-currency figure of what was collected only where
 * that document has been paid against.
 *
 * @param array $invoices  From erp_export_fetch_invoices()
 * @param array $profile
 * @return array
 */
function erp_export_rows_parasut_invoices($invoices, $profile)
{
    $rows = array();
    $base = erp_base_currency();

    foreach ($invoices as $i) {
        $party = erp_export_invoice_party($i);
        $currency = strtoupper(trim((string) $i['currency']));
        $foreign = (($currency !== '') && ($currency !== $base));

        $kind = 'Fatura';
        if ((string) $i['status'] === 'draft') {
            $kind = 'Taslak';
        } elseif ((string) $i['doc_type'] === 'proforma') {
            $kind = 'Proforma';
        }

        $name = trim((string) $i['full_number']);
        if (trim((string) ($i['order_number'] ?? '')) !== '') {
            $name = trim($name . ' #' . $i['order_number']);
        }

        $header = array(
            $party['title'],
            $name,
            erp_export_date($i['issue_date'], $profile),
            erp_export_currency($currency, $profile),
            $foreign ? erp_export_rate($i['exchange_rate'], $profile, 6) : '',
            erp_export_date($i['due_date'], $profile),
            ($foreign && ((int) $i['settled_base'] > 0)) ? erp_export_amount($i['settled_base'], $profile) : '',
            $kind,
            (string) $i['series'],
            ((int) $i['number'] > 0) ? (int) $i['number'] : '',
            '',
        );
        $blank_header = array_fill(0, count($header), '');

        $lines = !empty($i['lines']) ? $i['lines'] : array();
        if (empty($lines)) {
            // A document without lines still has to appear, or the run would
            // silently drop it; one line naming the document carries the total.
            $lines[] = array('description' => $name, 'quantity' => 1, 'unit_price' => (int) $i['subtotal'], 'discount_amount' => (int) $i['discount_total'], 'tax_rate' => 0);
        }

        $first = true;
        foreach ($lines as $line) {
            $goods = array(
                (string) $line['description'],
                '',
                '',
                erp_export_rate($line['quantity'], $profile, 4),
                erp_export_amount($line['unit_price'], $profile),
                ((int) $line['discount_amount'] > 0) ? erp_export_amount($line['discount_amount'], $profile) : '',
                erp_export_rate($line['tax_rate'], $profile, 3),
                '',
                '',
            );

            $rows[] = array_merge($first ? $header : $blank_header, $goods);
            $first = false;
        }
    }

    return $rows;
}

/**
 * Run a profile over its records.
 *
 * @param array $profile
 * @param array $filters
 * @return array ['columns' => array, 'rows' => array, 'ids' => array (record ids for the log), 'entity' => string,
 *                'returns_skipped' => int, 'warnings' => array]
 */
function erp_export_build($profile, $filters)
{
    $warnings = array();
    $returns_skipped = 0;
    $ids = array();

    switch ($profile['entity']) {
        case 'accounts':
            $records = erp_export_fetch_accounts($filters, $profile);
            $entity = 'account';
            break;

        case 'receipts':
            $records = erp_export_fetch_receipts($filters, $profile);
            $entity = 'receipt';
            break;

        default:
            $fetched = erp_export_fetch_invoices($filters, $profile);
            $records = $fetched['invoices'];
            $returns_skipped = (int) $fetched['returns_skipped'];
            $entity = 'invoice';
            break;
    }

    foreach ($records as $record) {
        $ids[] = (int) $record['id'];
    }

    $mapper = 'erp_export_rows_' . $profile['id'];
    $rows = function_exists($mapper) ? $mapper($records, $profile) : array();

    // The Paraşüt templates know four currencies and spell the lira TRL. A
    // store whose base is something else still gets its file; the operator
    // is told the codes may need a hand on the other side.
    if (($profile['group'] === 'parasut') && !isset($profile['currency_map'][erp_base_currency()])) {
        $warnings[] = lang(array('string' => 'The base currency {var:1} is not one this template lists; check the currency column after uploading.', 'vars' => erp_base_currency()));
    }

    if ($returns_skipped > 0) {
        $warnings[] = lang(array('string' => '{var:1} return document(s) were not exported; this format has no row for them.', 'vars' => $returns_skipped));
    }

    return array(
        'columns' => erp_export_columns($profile),
        'rows' => $rows,
        'ids' => $ids,
        'entity' => $entity,
        'returns_skipped' => $returns_skipped,
        'warnings' => $warnings,
    );
}

// -- writers -------------------------------------------------------------------

/**
 * A CSV file: UTF-8 with a byte order mark, semicolons, the heading row first.
 *
 * @param array  $columns
 * @param array  $rows
 * @param string $path
 * @return bool
 */
function erp_export_write_csv($columns, $rows, $path)
{
    $handle = @fopen($path, 'w');

    if ($handle === false) {
        return false;
    }

    fwrite($handle, "\xEF\xBB\xBF");
    fputcsv($handle, $columns, ';', '"', '\\');

    foreach ($rows as $row) {
        fputcsv($handle, array_map('strval', $row), ';', '"', '\\');
    }

    fclose($handle);

    return true;
}

/**
 * The column letters of a zero-based index: 0 => A, 26 => AA.
 *
 * @param int $index
 * @return string
 */
function erp_export_column_letter($index)
{
    $letters = '';

    for ($number = $index + 1; $number > 0; $number = (int) (($number - 1) / 26)) {
        $letters = chr(65 + (($number - 1) % 26)) . $letters;
    }

    return $letters;
}

/**
 * One value as XML text. Control characters are dropped: legal in a CSV,
 * fatal to a workbook.
 *
 * @param string $value
 * @return string
 */
function erp_export_xml($value)
{
    $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', (string) $value);

    return str_replace(array('&', '<', '>', '"'), array('&amp;', '&lt;', '&gt;', '&quot;'), $value);
}

/**
 * The <row> elements for a block of rows, numbers as numeric cells and
 * everything else as inline text.
 *
 * @param array $rows
 * @param int   $first_row  Row number of the first row
 * @param int   $style      Cell style index, 0 for the workbook default
 * @return string
 */
function erp_export_sheet_rows($rows, $first_row, $style = 0)
{
    $xml = '';
    $number = $first_row - 1;
    $style_attribute = ($style > 0) ? (' s="' . (int) $style . '"') : '';

    foreach ($rows as $row) {
        $number++;
        $xml .= '<row r="' . $number . '">';

        foreach (array_values($row) as $index => $value) {
            if (($value === '') || ($value === null)) {
                continue;
            }

            $reference = erp_export_column_letter($index) . $number;

            if (is_int($value) || is_float($value)) {
                $xml .= '<c r="' . $reference . '"' . $style_attribute . '><v>' . erp_export_xml(is_float($value) ? rtrim(rtrim(number_format($value, 6, '.', ''), '0'), '.') : (string) $value) . '</v></c>';
            } else {
                $xml .= '<c r="' . $reference . '"' . $style_attribute . ' t="inlineStr"><is><t xml:space="preserve">' . erp_export_xml($value) . '</t></is></c>';
            }
        }

        $xml .= '</row>';
    }

    return $xml;
}

/**
 * Where a profile's spreadsheet template lives, or '' when it is not there.
 *
 * @param array $profile
 * @return string
 */
function erp_export_template_path($profile)
{
    if (empty($profile['template'])) {
        return '';
    }

    $path = PG_FUNCTIONS_DIR . '/includes/phpexcel/templates/' . $profile['template'];

    return is_file($path) ? $path : '';
}

/**
 * A spreadsheet: the profile's template with our rows from its first data
 * row, or a plain sheet with a heading row when the template is missing.
 *
 * The template is opened as the zip it is and only its worksheet part is
 * rewritten: the help text, the heading rows, the column widths and the
 * shared strings stay untouched, which is what the receiving program checks.
 * Rows at or past the first data row are dropped before ours go in, so a
 * template that ships with sample rows does not hand them on.
 *
 * @param array  $profile
 * @param array  $columns
 * @param array  $rows
 * @param string $path  Where to write the .xlsx
 * @return bool
 */
function erp_export_write_xlsx($profile, $columns, $rows, $path)
{
    if (!class_exists('ZipArchive')) {
        return false;
    }

    $template = erp_export_template_path($profile);

    if ($template !== '') {
        if (!@copy($template, $path)) {
            return false;
        }

        $zip = new ZipArchive();

        if ($zip->open($path) !== true) {
            @unlink($path);
            return false;
        }

        $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');

        if (($sheet === false) || (strpos($sheet, '<sheetData') === false)) {
            $zip->close();
            @unlink($path);
            return false;
        }

        $first_row = max(1, (int) $profile['first_row']);

        // Keep the rows above the data area exactly as they are, drop the rest.
        $kept = '';
        if (preg_match_all('/<row\b[^>]*?(?:\/>|>.*?<\/row>)/s', $sheet, $matches)) {
            foreach ($matches[0] as $row_xml) {
                if (preg_match('/\br="(\d+)"/', $row_xml, $r) && ((int) $r[1] < $first_row)) {
                    $kept .= $row_xml;
                }
            }
        }

        $sheet_data = '<sheetData>' . $kept . erp_export_sheet_rows($rows, $first_row) . '</sheetData>';
        $sheet = preg_replace('/<sheetData\b[^>]*?(?:\/>|>.*?<\/sheetData>)/s', $sheet_data, $sheet, 1);

        $last_row = $first_row + max(0, count($rows) - 1);
        $last_column = erp_export_column_letter(max(0, count($columns) - 1));
        $sheet = preg_replace('/<dimension ref="[^"]*"\/>/', '<dimension ref="A1:' . $last_column . $last_row . '"/>', $sheet, 1);

        $zip->addFromString('xl/worksheets/sheet1.xml', $sheet);

        return $zip->close();
    }

    return erp_export_write_plain_xlsx($columns, $rows, $path, $profile['label']);
}

/**
 * A one-sheet workbook from nothing: heading row in bold, data underneath.
 *
 * @param array  $columns
 * @param array  $rows
 * @param string $path
 * @param string $sheet_title
 * @return bool
 */
function erp_export_write_plain_xlsx($columns, $rows, $path, $sheet_title)
{
    $ns = 'http://schemas.openxmlformats.org/';
    $head = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n";

    $last_cell = erp_export_column_letter(max(0, count($columns) - 1)) . (count($rows) + 1);

    $sheet = $head .
        '<worksheet xmlns="' . $ns . 'spreadsheetml/2006/main">' .
        '<dimension ref="A1:' . $last_cell . '"/>' .
        '<sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>' .
        '<sheetData>' .
        erp_export_sheet_rows(array($columns), 1, 1) .
        erp_export_sheet_rows($rows, 2) .
        '</sheetData></worksheet>';

    $title = mb_substr(preg_replace('/[\[\]\*\?\/\\\\:]/', ' ', (string) $sheet_title), 0, 31);

    $parts = array(
        '[Content_Types].xml' => $head .
            '<Types xmlns="' . $ns . 'package/2006/content-types">' .
            '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>' .
            '<Default Extension="xml" ContentType="application/xml"/>' .
            '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>' .
            '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>' .
            '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>' .
            '<Override PartName="/xl/sharedStrings.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/>' .
            '</Types>',
        '_rels/.rels' => $head .
            '<Relationships xmlns="' . $ns . 'package/2006/relationships">' .
            '<Relationship Id="rId1" Type="' . $ns . 'officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>' .
            '</Relationships>',
        'xl/workbook.xml' => $head .
            '<workbook xmlns="' . $ns . 'spreadsheetml/2006/main" xmlns:r="' . $ns . 'officeDocument/2006/relationships">' .
            '<bookViews><workbookView activeTab="0"/></bookViews>' .
            '<sheets><sheet name="' . erp_export_xml($title) . '" sheetId="1" r:id="rId1"/></sheets>' .
            '</workbook>',
        'xl/_rels/workbook.xml.rels' => $head .
            '<Relationships xmlns="' . $ns . 'package/2006/relationships">' .
            '<Relationship Id="rId1" Type="' . $ns . 'officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>' .
            '<Relationship Id="rId2" Type="' . $ns . 'officeDocument/2006/relationships/styles" Target="styles.xml"/>' .
            '<Relationship Id="rId3" Type="' . $ns . 'officeDocument/2006/relationships/sharedStrings" Target="sharedStrings.xml"/>' .
            '</Relationships>',
        // Text is carried inline, so the shared strings table is empty; it is
        // present because some readers look for it before the sheet.
        'xl/sharedStrings.xml' => $head .
            '<sst xmlns="' . $ns . 'spreadsheetml/2006/main" count="0" uniqueCount="0"/>',
        // Two cell formats: 0 plain, 1 the bold heading. Fills and borders are
        // required by Excel even when nothing uses them.
        'xl/styles.xml' => $head .
            '<styleSheet xmlns="' . $ns . 'spreadsheetml/2006/main">' .
            '<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts>' .
            '<fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills>' .
            '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>' .
            '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>' .
            '<cellXfs count="2"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/></cellXfs>' .
            '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>' .
            '</styleSheet>',
        'xl/worksheets/sheet1.xml' => $sheet,
    );

    $zip = new ZipArchive();

    if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        return false;
    }

    foreach ($parts as $name => $part) {
        $zip->addFromString($name, $part);
    }

    return $zip->close();
}

/**
 * Write a built export to a file in the profile's format.
 *
 * @param array  $profile
 * @param array  $built  From erp_export_build()
 * @param string $path
 * @return bool
 */
function erp_export_write($profile, $built, $path)
{
    if ($profile['format'] === 'xlsx') {
        return erp_export_write_xlsx($profile, $built['columns'], $built['rows'], $path);
    }

    return erp_export_write_csv($built['columns'], $built['rows'], $path);
}

/**
 * The file name a run is downloaded as. ASCII only: a header cannot carry
 * the Turkish letters and a receiver must not have to guess.
 *
 * @param array  $profile
 * @param string $stamp  Y-m-d or similar
 * @return string
 */
function erp_export_file_name($profile, $stamp)
{
    $name = preg_replace('/[^A-Za-z0-9._-]+/', '_', (string) $profile['file'] . '_' . $stamp);

    return trim($name, '_') . '.' . $profile['format'];
}

/**
 * The media type of a format.
 *
 * @param string $format
 * @return string
 */
function erp_export_mime($format)
{
    return ($format === 'xlsx')
        ? 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        : 'text/csv; charset=utf-8';
}

// -- the export log ----------------------------------------------------------------

/**
 * Record that these records went out in this run.
 *
 * @param string $entity  account | invoice | receipt
 * @param array  $ids
 * @param array  $profile
 * @param string $run_token  32 hex characters
 * @param int    $user_id
 * @return bool
 */
function erp_export_log_run($entity, $ids, $profile, $run_token, $user_id)
{
    if (empty($ids) || (preg_match('/^[a-f0-9]{32}$/', (string) $run_token) !== 1)) {
        return false;
    }

    $now = time();
    $values = array();

    foreach ($ids as $id) {
        $values[] = "('" . escape($entity) . "', '" . (int) $id . "', '" . escape($profile['id']) . "', '" . $run_token . "', '" . (int) $user_id . "', '" . $now . "')";
    }

    $ok = true;

    foreach (array_chunk($values, 500) as $chunk) {
        if (erp_query("INSERT INTO erp_export_log (entity, doc_id, profile, run_token, created_by, created_at) VALUES " . implode(', ', $chunk)) === false) {
            $ok = false;
        }
    }

    return $ok;
}

/**
 * The last runs, one row each.
 *
 * @param int $limit
 * @return array  run_token, entity, profile, rows, created_by, created_at
 */
function erp_export_recent_runs($limit = 20)
{
    return (array) db_items("SELECT run_token, MAX(entity) AS entity, MAX(profile) AS profile, COUNT(*) AS rows_written,
            MAX(created_by) AS created_by, MIN(created_at) AS created_at
        FROM erp_export_log
        GROUP BY run_token
        ORDER BY MIN(created_at) DESC, run_token DESC
        LIMIT " . (int) $limit);
}

/**
 * A fresh run token.
 *
 * @return string
 */
function erp_export_new_token()
{
    return bin2hex(random_bytes(16));
}

/**
 * Where a finished file waits to be downloaded.
 *
 * data/temp is denied to the web server and is where the rest of the panel
 * stages files.
 *
 * @param string $token
 * @param string $format
 * @return string  '' for a token that is not one of ours or a folder that cannot be written
 */
function erp_export_temp_path($token, $format)
{
    if ((preg_match('/^[a-f0-9]{32}$/', (string) $token) !== 1) || !in_array($format, array('csv', 'xlsx'), true)) {
        return '';
    }

    $directory = PG_FUNCTIONS_DIR . '/data/temp';

    if (!is_dir($directory)) {
        @mkdir($directory, 0755, true);
    }

    if (!is_dir($directory) || !is_writable($directory)) {
        return '';
    }

    return $directory . '/erp_export_' . $token . '.' . $format;
}

/**
 * Remove finished files nobody downloaded.
 *
 * @param int $max_age  Seconds
 * @return int
 */
function erp_export_sweep($max_age = 86400)
{
    $directory = PG_FUNCTIONS_DIR . '/data/temp';
    $removed = 0;

    foreach ((array) glob($directory . '/erp_export_*.{csv,xlsx}', GLOB_BRACE) as $file) {
        if (is_file($file) && ((time() - (int) @filemtime($file)) > $max_age) && @unlink($file)) {
            $removed++;
        }
    }

    return $removed;
}
