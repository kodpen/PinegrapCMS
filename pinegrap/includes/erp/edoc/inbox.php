<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * ERP - incoming e-invoices.
 *
 * What suppliers send the store through GİB arrives at the e-document
 * provider. This file reads the provider's list on request, keeps every
 * document once in erp_edoc_inbox (4.67), reads the document itself from its
 * UBL when somebody looks at it, and proposes the purchase invoice it becomes.
 * Nothing is taken in on its own: the proposal is shown, the operator takes it
 * in, and what is created is a draft - no number is spent and nothing reaches
 * the ledger until the draft is issued like any other purchase invoice.
 *
 * The provider is asked through the one door (erp_edoc_call) for two
 * operations, so any driver that implements them gets this screen:
 *
 *   erp_edoc_<code>_inbox($from, $to, $page)
 *       ['success', 'error', 'items' => [...], 'total' => int, 'pages' => int]
 *       Each item: external_id, gib_uuid, gib_number, invoice_type, profile,
 *       issue_date, supplier_title, supplier_tax_number, currency, tax_base,
 *       total (kurus, the payable amount), provider_status.
 *   erp_edoc_<code>_inbox_document($item, $format)     'ubl' | 'pdf'
 *       ['success', 'error', 'content' (bytes), 'filename', 'mime']
 *
 * The purchase invoice is built with the typed-invoice rules
 * (erp_manual_lines_build()), because that is what will recompute it when it
 * is issued. The document's own figures are compared with the result line by
 * line; a difference is shown before anything is created, and what the ERP
 * cannot hold at all - taxes other than VAT, a discount on the whole document,
 * a withholding written only for the whole document - is refused with the
 * reason rather than taken in wrong. VAT withholding on a line is carried as
 * the line's code and share (includes/erp/withholding.php).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_ERP_ENTRY')) {
    exit;
}

// Pages read in one go. A provider pages at up to a hundred documents; a
// store that receives more in the chosen range reads the rest with a
// narrower range.
if (!defined('ERP_EDOC_INBOX_MAX_PAGES')) {
    define('ERP_EDOC_INBOX_MAX_PAGES', 10);
}

// The widest range one read covers, in days.
if (!defined('ERP_EDOC_INBOX_MAX_DAYS')) {
    define('ERP_EDOC_INBOX_MAX_DAYS', 93);
}

/**
 * Whether the 4.67 table is there.
 *
 * @return bool
 */
function erp_edoc_inbox_installed()
{
    static $installed = null;

    if ($installed === null) {
        $installed = function_exists('waf_table_has_column') && waf_table_has_column('erp_edoc_inbox', 'gib_uuid');
    }

    return $installed;
}

/**
 * The provider incoming documents are read from: the active one, when it
 * declares the capability and its driver implements both operations.
 *
 * @return string  A driver code, or ''
 */
function erp_edoc_inbox_provider()
{
    if (!erp_edoc_installed()) {
        return '';
    }

    $code = erp_edoc_active();

    if (($code === '') || !erp_edoc_has_capability('inbox', $code)
        || !erp_edoc_supports('inbox', $code) || !erp_edoc_supports('inbox_document', $code)) {
        return '';
    }

    return $code;
}

/**
 * Where a document stands on the store's side, as the screens print it.
 *
 * @return array  state => ['label', 'tone']
 */
function erp_edoc_inbox_states()
{
    return array(
        'new' => array('label' => lang('Not taken in'), 'tone' => 'primary'),
        'drafted' => array('label' => lang('Purchase draft'), 'tone' => 'warning'),
        'imported' => array('label' => lang('Taken in'), 'tone' => 'success'),
        'ignored' => array('label' => lang('Put aside'), 'tone' => 'secondary'),
    );
}

/**
 * The state of one row. A document taken in whose purchase invoice has since
 * been deleted or cancelled is waiting again: the decision was undone.
 *
 * @param array $row  An erp_edoc_inbox row joined with invoice_status
 * @return string  A key of erp_edoc_inbox_states()
 */
function erp_edoc_inbox_state($row)
{
    $status = (string) ($row['status'] ?? 'new');

    if ($status === 'ignored') {
        return 'ignored';
    }

    if ($status === 'imported') {
        $invoice_status = (string) ($row['invoice_status'] ?? '');

        if (($invoice_status === '') || ($invoice_status === 'cancelled')) {
            return 'new';
        }

        return ($invoice_status === 'draft') ? 'drafted' : 'imported';
    }

    return 'new';
}

/**
 * The SQL condition for one state, on an erp_edoc_inbox row aliased x joined
 * with erp_invoices aliased i.
 *
 * @param string $state
 * @return string  '' for a state it does not know
 */
function erp_edoc_inbox_state_sql($state)
{
    switch ((string) $state) {
        case 'ignored':
            return "x.status = 'ignored'";
        case 'drafted':
            return "x.status = 'imported' AND i.status = 'draft'";
        case 'imported':
            return "x.status = 'imported' AND i.id IS NOT NULL AND i.status NOT IN ('draft', 'cancelled')";
        case 'new':
            return "(x.status = 'new' OR (x.status = 'imported' AND (i.id IS NULL OR i.status = 'cancelled')))";
    }

    return '';
}

/**
 * How many documents stand in each state.
 *
 * @return array  state => int
 */
function erp_edoc_inbox_counts()
{
    $counts = array_fill_keys(array_keys(erp_edoc_inbox_states()), 0);

    if (!erp_edoc_inbox_installed()) {
        return $counts;
    }

    foreach (array_keys($counts) as $state) {
        $counts[$state] = (int) db_value("SELECT COUNT(*) FROM erp_edoc_inbox x
            LEFT JOIN erp_invoices i ON i.id = x.invoice_id
            WHERE " . erp_edoc_inbox_state_sql($state));
    }

    return $counts;
}

/**
 * The words of the UBL-TR invoice types, for the list and the review.
 *
 * @param string $code  InvoiceTypeCode
 * @return string
 */
function erp_edoc_inbox_type_label($code)
{
    $labels = array(
        'SATIS' => lang('Sale'),
        'IADE' => lang('Return'),
        'TEVKIFAT' => lang('VAT withholding'),
        'ISTISNA' => lang('VAT exemption'),
        'OZELMATRAH' => lang('Special tax base'),
        'IHRACKAYITLI' => lang('Export-registered'),
        'KONAKLAMAVERGISI' => lang('Accommodation tax'),
        'SGK' => 'SGK',
    );

    $code = strtoupper(trim((string) $code));

    return $labels[$code] ?? $code;
}

/**
 * Reads the provider's list for a date range and keeps what it says.
 *
 * A document seen before has the provider's fields refreshed and keeps the
 * store's decision; a new one arrives as 'new'. A purchase invoice already
 * carrying a document's ETTN (taken in before, or linked by hand) marks the
 * document as taken in.
 *
 * @param string $from  Y-m-d
 * @param string $to    Y-m-d
 * @return array ['success' => bool, 'error' => string, 'found' => int,
 *                'added' => int, 'truncated' => bool]
 */
function erp_edoc_inbox_fetch($from, $to)
{
    $out = array('success' => false, 'error' => '', 'found' => 0, 'added' => 0, 'truncated' => false);

    if (!erp_edoc_inbox_installed()) {
        return array_merge($out, array('error' => lang('Run the software upgrade first: the incoming e-invoice table is not there yet.')));
    }

    $provider = erp_edoc_inbox_provider();

    if ($provider === '') {
        return array_merge($out, array('error' => lang('The e-document provider in use does not hand over incoming invoices.')));
    }

    $valid = function ($date) {
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $date) && (strtotime((string) $date) !== false);
    };

    if (!$valid($from) || !$valid($to) || ($from > $to)) {
        return array_merge($out, array('error' => lang('Choose a start date on or before the end date.')));
    }

    if (((strtotime($to) - strtotime($from)) / 86400) > ERP_EDOC_INBOX_MAX_DAYS) {
        return array_merge($out, array('error' => lang(array('string' => 'Read at most {var:1} days at a time.', 'vars' => ERP_EDOC_INBOX_MAX_DAYS))));
    }

    $page = 1;
    $pages = 1;

    do {
        $answer = erp_edoc_call('inbox', array($from, $to, $page), $provider);

        if (empty($answer['success'])) {
            return array_merge($out, array('error' => (string) $answer['error']));
        }

        foreach ((array) ($answer['items'] ?? array()) as $item) {
            $saved = erp_edoc_inbox_keep($provider, (array) $item);

            if ($saved === 'added') {
                $out['added']++;
            }

            if ($saved !== '') {
                $out['found']++;
            }
        }

        $pages = max(1, (int) ($answer['pages'] ?? 1));
        $page++;
    } while (($page <= $pages) && ($page <= ERP_EDOC_INBOX_MAX_PAGES));

    $out['truncated'] = ($pages > ERP_EDOC_INBOX_MAX_PAGES);

    // Documents a purchase invoice already carries.
    erp_query("UPDATE erp_edoc_inbox x
        INNER JOIN erp_invoices i ON i.gib_uuid = x.gib_uuid AND i.direction = 'purchase' AND i.status <> 'cancelled'
        SET x.status = 'imported', x.invoice_id = i.id, x.account_id = i.account_id, x.updated_at = '" . time() . "'
        WHERE x.status = 'new' AND x.gib_uuid <> ''");

    erp_edoc_settings_save($provider, array('inbox_read_at' => time(), 'inbox_read_to' => $to));

    $out['success'] = true;

    return $out;
}

/**
 * Keeps one list item. The provider's fields are written, the store's are
 * left alone.
 *
 * @param string $provider
 * @param array  $item  See the header
 * @return string  'added', 'kept', or '' when the item carried no ETTN
 */
function erp_edoc_inbox_keep($provider, $item)
{
    // Kept as the provider writes it: İşbaşı looks a document up by its
    // ETTN case-sensitively, and the table's collation compares without case.
    $uuid = trim((string) ($item['gib_uuid'] ?? ''));

    if (!preg_match('/^[0-9A-F\-]{32,36}$/i', $uuid)) {
        return '';
    }

    $issue_date = (string) ($item['issue_date'] ?? '');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $issue_date)) {
        $issue_date = '0000-00-00';
    }

    $currency = strtoupper(trim((string) ($item['currency'] ?? '')));
    if (!preg_match('/^[A-Z]{3}$/', $currency)) {
        $currency = erp_base_currency();
    }

    $columns = array(
        'external_id' => mb_substr(trim((string) ($item['external_id'] ?? '')), 0, 64),
        'gib_number' => mb_substr(trim((string) ($item['gib_number'] ?? '')), 0, 20),
        'invoice_type' => mb_substr(strtoupper(trim((string) ($item['invoice_type'] ?? ''))), 0, 20),
        'profile' => mb_substr(trim((string) ($item['profile'] ?? '')), 0, 30),
        'issue_date' => $issue_date,
        'supplier_title' => mb_substr(trim((string) ($item['supplier_title'] ?? '')), 0, 255),
        // Letters and digits: a provider outside Turkey sends VAT ids.
        'supplier_tax_number' => substr(erp_edoc_account_tax_key($item['supplier_tax_number'] ?? ''), 0, 32),
        'currency' => $currency,
        'tax_base' => (int) ($item['tax_base'] ?? 0),
        'total' => (int) ($item['total'] ?? 0),
        'provider_status' => mb_substr(trim((string) ($item['provider_status'] ?? '')), 0, 100),
    );

    $pairs = array();

    foreach ($columns as $column => $value) {
        $pairs[$column] = $column . " = '" . escape((string) $value) . "'";
    }

    $now = time();

    // Looked up first rather than INSERT ... ON DUPLICATE KEY UPDATE, which
    // spends an id on every document read again.
    $id = (int) db_value("SELECT id FROM erp_edoc_inbox
        WHERE provider = '" . escape((string) $provider) . "' AND gib_uuid = '" . escape($uuid) . "' LIMIT 1");

    if ($id > 0) {
        // The type read from the document's UBL outranks the list's guess.
        $pairs['invoice_type'] = "invoice_type = IF(document_at > 0 OR '" . escape($columns['invoice_type']) . "' = '', invoice_type, '" . escape($columns['invoice_type']) . "')";
        $ok = erp_query("UPDATE erp_edoc_inbox SET gib_uuid = '" . escape($uuid) . "', " . implode(', ', $pairs) . ", updated_at = '" . $now . "' WHERE id = '" . $id . "'");

        return ($ok === false) ? '' : 'kept';
    }

    $ok = erp_query("INSERT INTO erp_edoc_inbox SET
            provider = '" . escape((string) $provider) . "',
            gib_uuid = '" . escape($uuid) . "',
            status = 'new',
            created_at = '" . $now . "',
            updated_at = '" . $now . "',
            " . implode(",\n            ", $pairs));

    return ($ok === false) ? '' : 'added';
}

/**
 * One row, with the purchase invoice it became.
 *
 * @param int $id
 * @return array|null
 */
function erp_edoc_inbox_row($id)
{
    if (!erp_edoc_inbox_installed()) {
        return null;
    }

    $row = db_item("SELECT x.*, i.status AS invoice_status, i.full_number AS invoice_number
        FROM erp_edoc_inbox x
        LEFT JOIN erp_invoices i ON i.id = x.invoice_id
        WHERE x.id = '" . (int) $id . "' LIMIT 1");

    return is_array($row) ? $row : null;
}

/**
 * A file of the document straight from the provider: its UBL (the XML,
 * taken out of the archive the provider wraps it in) or its PDF.
 *
 * @param array  $row
 * @param string $format  'ubl' | 'pdf'
 * @return array ['success', 'error', 'content', 'filename', 'mime']
 */
function erp_edoc_inbox_file($row, $format)
{
    $format = ((string) $format === 'pdf') ? 'pdf' : 'ubl';
    $answer = erp_edoc_call('inbox_document', array($row, $format), (string) $row['provider']);

    if (empty($answer['success'])) {
        return array('success' => false, 'error' => (string) $answer['error'], 'content' => '', 'filename' => '', 'mime' => '');
    }

    $name = ((string) $row['gib_number'] !== '') ? (string) $row['gib_number'] : (string) $row['gib_uuid'];

    if ($format === 'ubl') {
        $unpacked = erp_ubl_unpack((string) $answer['content']);

        if (!$unpacked['success']) {
            return array('success' => false, 'error' => $unpacked['error'], 'content' => '', 'filename' => '', 'mime' => '');
        }

        return array('success' => true, 'error' => '', 'content' => $unpacked['xml'], 'filename' => $name . '.xml', 'mime' => 'application/xml');
    }

    if (strncmp((string) $answer['content'], '%PDF', 4) !== 0) {
        return array('success' => false, 'error' => lang('The provider did not return a PDF for this document.'), 'content' => '', 'filename' => '', 'mime' => '');
    }

    return array('success' => true, 'error' => '', 'content' => (string) $answer['content'], 'filename' => $name . '.pdf', 'mime' => 'application/pdf');
}

/**
 * The document as read from its UBL. Read from the provider once and kept on
 * the row; $refresh reads it again.
 *
 * @param array $row
 * @param bool  $refresh
 * @return array ['success' => bool, 'error' => string, 'document' => array]
 */
function erp_edoc_inbox_document($row, $refresh = false)
{
    if (!$refresh && (trim((string) ($row['document'] ?? '')) !== '')) {
        $kept = json_decode((string) $row['document'], true);

        if (is_array($kept) && !empty($kept['uuid'])) {
            return array('success' => true, 'error' => '', 'document' => $kept);
        }
    }

    $file = erp_edoc_inbox_file($row, 'ubl');

    if (!$file['success']) {
        return array('success' => false, 'error' => $file['error'], 'document' => array());
    }

    $read = erp_ubl_read($file['content']);

    if (!$read['success']) {
        return $read;
    }

    $document = $read['document'];

    if (strcasecmp((string) $document['uuid'], (string) $row['gib_uuid']) !== 0) {
        return array('success' => false, 'error' => lang('The provider returned a different document than the one asked for.'), 'document' => array());
    }

    erp_query("UPDATE erp_edoc_inbox SET
            document = '" . escape(json_encode($document, JSON_UNESCAPED_UNICODE)) . "',
            document_at = '" . time() . "',
            invoice_type = '" . escape(mb_substr((string) $document['type_code'], 0, 20)) . "',
            updated_at = '" . time() . "'
        WHERE id = '" . (int) $row['id'] . "'");

    return array('success' => true, 'error' => '', 'document' => $document);
}

/**
 * The account a supplier's tax number already has, if any: an active one
 * before a passive one, a supplier before a customer.
 *
 * @param string $tax_number
 * @return array|null
 */
function erp_edoc_inbox_supplier_account($tax_number)
{
    // Matched on letters and digits, the way the account sync matches: an
    // account may carry the number with spaces or dashes.
    $key = erp_edoc_account_tax_key($tax_number);
    $ids = array();

    foreach ((array) (erp_edoc_accounts_by_tax_key(true)[$key] ?? array()) as $account) {
        $ids[] = (int) $account['id'];
    }

    if (($key === '') || empty($ids)) {
        return null;
    }

    $row = db_item("SELECT * FROM erp_accounts
        WHERE id IN (" . implode(', ', $ids) . ")
        ORDER BY (status = 'active') DESC, (kind IN ('supplier', 'both')) DESC, id ASC
        LIMIT 1");

    return is_array($row) ? $row : null;
}

/**
 * The supplier account the document would open, as erp_account_save() takes it.
 *
 * @param array $party  erp_ubl_party() output
 * @param array $document
 * @return array
 */
function erp_edoc_inbox_account_data($party, $document)
{
    $country = strtoupper((string) ($party['country_code'] ?? ''));

    return array(
        'kind' => 'supplier',
        'title' => mb_substr((string) $party['title'], 0, 255),
        'is_person' => !empty($party['is_person']) ? 1 : 0,
        'tax_number' => (string) $party['tax_number'],
        'tax_office' => mb_substr((string) $party['tax_office'], 0, 100),
        'email' => mb_substr((string) $party['email'], 0, 255),
        'phone' => mb_substr((string) $party['phone'], 0, 50),
        'address' => mb_substr((string) $party['address'], 0, 255),
        'district' => mb_substr((string) $party['district'], 0, 100),
        'city' => mb_substr((string) $party['city'], 0, 100),
        'country_code' => preg_match('/^[A-Z]{2}$/', $country) ? $country : erp_default_country_code(),
        'postcode' => mb_substr((string) $party['postcode'], 0, 20),
        'currency' => erp_base_currency(),
        'status' => 'active',
        'notes' => lang(array('string' => 'Opened from incoming e-invoice {var:1}.', 'vars' => (string) $document['number'])),
    );
}

/**
 * The store's product an incoming line is for, when the document says so
 * beyond doubt: the buyer's item code (the store's own code, as the supplier
 * keeps it) read as a barcode or SKU, or the supplier's code read as a
 * barcode only - a supplier's own item number is theirs and says nothing
 * about the store's SKUs. 0 when neither names a product; the operator can
 * link the line on the draft before it is issued.
 *
 * @param array $line  One line of erp_ubl_read()
 * @return int
 */
function erp_edoc_inbox_line_product($line)
{
    $buyer_code = trim((string) ($line['buyer_code'] ?? ''));

    if ($buyer_code !== '') {
        $row = erp_product_by_barcode($buyer_code);

        if (is_array($row)) {
            return (int) $row['id'];
        }
    }

    $seller_code = trim((string) ($line['seller_code'] ?? ''));

    if (($seller_code !== '') && (mb_strlen($seller_code) <= 100) && defined('BARCODE_ENABLED') && BARCODE_ENABLED
        && function_exists('waf_table_has_column') && waf_table_has_column('product_barcodes', 'barcode')) {
        return (int) db_value("SELECT product_id FROM product_barcodes WHERE barcode = '" . escape($seller_code) . "' LIMIT 1");
    }

    return 0;
}

/**
 * The document's lines as typed-invoice lines, with what could not be kept
 * exactly.
 *
 * A unit price is kept in kurus. When the supplier priced below a kurus
 * (447.108 for a line of 75) the quantity and price cannot both be kept and
 * still add up to the line; the line is then written as one unit of its
 * amount, with the real quantity and price in its description, so the
 * document still adds up.
 *
 * @param array $document  erp_ubl_read() output
 * @return array ['lines' => typed lines, 'source' => the document's figures
 *                per line (text, net, vat, withholding, withholding_label,
 *                exemption), 'folded' => line numbers written as one
 *                unit, 'refusals' => string[]]
 */
function erp_edoc_inbox_lines($document)
{
    $lines = array();
    $source = array();
    $folded = array();
    $refusals = array();

    foreach ((array) $document['lines'] as $line) {
        $vat = null;

        foreach ((array) $line['taxes'] as $tax) {
            $is_vat = ((string) $tax['code'] === ERP_UBL_VAT_CODE) || (mb_strtoupper((string) $tax['name'], 'UTF-8') === 'KDV');

            if ($is_vat && ($vat === null)) {
                $vat = $tax;
            } elseif (!$is_vat && ((int) $tax['amount'] !== 0)) {
                $refusals['tax_' . $tax['code']] = lang(array(
                    'string' => 'Line {var:1} carries {var:2}, a tax other than VAT. The ERP keeps VAT only, so this invoice has to be entered by hand.',
                    'vars' => array($line['no'], ((string) $tax['name'] !== '') ? $tax['name'] : $tax['code']),
                ));
            }
        }

        // The line's VAT withholding: its code and share, as the supplier
        // wrote them (the share is a percentage of the line's VAT).
        $withholding = null;
        foreach ((array) $line['withholdings'] as $withheld) {
            if (((int) $withheld['amount'] !== 0) || ((float) $withheld['rate'] > 0)) {
                if ($withholding !== null) {
                    $refusals['withholding_twice'] = lang(array('string' => 'Line {var:1} carries two VAT withholdings; a line can hold one.', 'vars' => $line['no']));
                }
                $withholding = $withheld;
            }
        }

        $discount = 0;
        $discount_rates = array();

        foreach ((array) $line['allowances'] as $allowance) {
            if (!empty($allowance['charge'])) {
                if ((int) $allowance['amount'] !== 0) {
                    $refusals['charge'] = lang(array('string' => 'Line {var:1} carries a surcharge, which a typed invoice line cannot hold.', 'vars' => $line['no']));
                }
                continue;
            }

            $discount += (int) $allowance['amount'];
            if ((float) $allowance['rate'] > 0) {
                $discount_rates[] = (float) $allowance['rate'];
            }
        }

        $net = (int) $line['line_extension'];
        $gross = $net + $discount;
        $quantity = ((float) $line['quantity'] > 0) ? (float) $line['quantity'] : 1.0;
        $unit_code = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $line['unit_code']));

        // NIU (number of international units) is what most Turkish
        // integrators write for a piece; the ERP's piece is C62.
        if (($unit_code === '') || ($unit_code === 'NIU')) {
            $unit_code = 'C62';
        }

        $unit_price = null;
        foreach (array(erp_ubl_kurus($line['price']), ($quantity > 0) ? (int) round($gross / $quantity) : 0) as $candidate) {
            if (($candidate >= 0) && (erp_line_total($candidate, $quantity) === $gross)) {
                $unit_price = $candidate;
                break;
            }
        }

        // Name or description: integrators disagree on which carries the
        // product's name. When the name is only the seller's code, the
        // description is the name.
        $name = trim((string) $line['name']);
        $description = trim((string) $line['description']);
        if (($name === '') || (($description !== '') && ($description !== '0') && ($name === trim((string) $line['seller_code'])))) {
            $text = ($description !== '') ? $description : $name;
        } else {
            $text = $name;
        }
        if ($text === '') {
            $text = lang(array('string' => 'Line {var:1}', 'vars' => $line['no']));
        }

        $name_text = $text;

        if ($unit_price === null) {
            $folded[] = (string) $line['no'];
            $price = (strpos((string) $line['price'], '.') !== false) ? rtrim(rtrim((string) $line['price'], '0'), '.') : (string) $line['price'];
            $text .= ' (' . rtrim(rtrim(number_format($quantity, 4, '.', ''), '0'), '.') . ' × ' . $price . ')';
            $quantity = 1.0;
            $unit_code = 'C62';
            $unit_price = $gross;
        }

        $discount_rate = 0.0;
        if ($discount > 0) {
            $line_total = erp_line_total($unit_price, $quantity);
            $candidates = $discount_rates;
            if ($line_total > 0) {
                $candidates[] = round($discount * 100 / $line_total, 3);
            }

            foreach ($candidates as $candidate) {
                $discount_rate = (float) $candidate;
                if (erp_apply_rate($line_total, $discount_rate) === $discount) {
                    break;
                }
            }
        }

        $lines[] = array(
            'product_id' => erp_edoc_inbox_line_product($line),
            'description' => mb_substr($text, 0, 255),
            'quantity' => $quantity,
            'unit_code' => substr($unit_code, 0, 10),
            'unit_price' => $unit_price,
            'tax_rate' => ($vat !== null) ? (float) $vat['rate'] : 0.0,
            'discount_rate' => $discount_rate,
            'withholding_code' => ($withholding !== null) ? substr(preg_replace('/[^A-Za-z0-9]/', '', (string) $withholding['code']), 0, 10) : '',
            'withholding_rate' => ($withholding !== null) ? (float) $withholding['rate'] : 0.0,
        );

        $source[] = array(
            'no' => (string) $line['no'],
            'text' => $name_text,
            'net' => $net,
            'vat' => ($vat !== null) ? (int) $vat['amount'] : 0,
            'withholding' => ($withholding !== null) ? (int) $withholding['amount'] : 0,
            'withholding_label' => ($withholding !== null) ? erp_withholding_label((string) $withholding['code'], (float) $withholding['rate'], false) : '',
            'exemption' => ($vat !== null) ? trim($vat['exemption_code'] . ' ' . $vat['exemption_reason']) : '',
        );
    }

    return array('lines' => $lines, 'source' => $source, 'folded' => $folded, 'refusals' => array_values($refusals));
}

/**
 * What taking a document in would create, checked against the document.
 *
 * @param array $row  An erp_edoc_inbox_row()
 * @return array ['success', 'error', 'document', 'account' (existing row or
 *                null), 'account_data' (what would be opened), 'lines',
 *                'built' (erp_manual_lines_build()), 'source', 'folded',
 *                'refusals' string[], 'warnings' string[], 'differences'
 *                [label => [document, erp]], 'existing' (a purchase invoice
 *                carrying the ETTN, or null), 'candidate' (one carrying the
 *                supplier's number, or null), 'header' (the draft's header)]
 */
function erp_edoc_inbox_proposal($row)
{
    $read = erp_edoc_inbox_document($row);

    if (!$read['success']) {
        return array('success' => false, 'error' => $read['error']);
    }

    $document = $read['document'];
    $supplier = (array) $document['supplier'];
    $refusals = array();
    $warnings = array();

    $account = erp_edoc_inbox_supplier_account($supplier['tax_number'] ?? '');
    $account_data = erp_edoc_inbox_account_data($supplier, $document);

    if (($account === null) && (trim((string) $account_data['title']) === '')) {
        $refusals[] = lang('The document names no supplier.');
    }

    if (($account !== null) && ((string) $account['status'] !== 'active')) {
        $refusals[] = lang(array('string' => 'The supplier\'s account ({var:1}) is passive. Make it active to take the invoice in.', 'vars' => $account['title']));
    }

    // TL is what some integrators write; the code is TRY.
    $currency = strtoupper((string) $document['currency']);
    if (($currency === 'TL') || ($currency === 'TRL')) {
        $currency = 'TRY';
    }

    if (!erp_fx_currency_allowed($currency)) {
        $refusals[] = lang(array('string' => 'The invoice is in {var:1}, which is not enabled for the ERP.', 'vars' => $currency));
    }

    // A withholding the lines do not carry cannot be put on any of them.
    $line_withholding = 0;
    foreach ((array) $document['lines'] as $line) {
        foreach ((array) $line['withholdings'] as $withheld) {
            $line_withholding += (int) $withheld['amount'];
        }
    }

    if (((int) $document['totals']['withholding'] !== 0) && ($line_withholding === 0)) {
        $refusals[] = lang('The invoice gives its VAT withholding only for the whole document, not per line, so it cannot be put on the lines.');
    }

    foreach ((array) $document['allowances'] as $allowance) {
        if ((int) $allowance['amount'] !== 0) {
            $refusals[] = lang('The invoice carries a discount or a surcharge on the whole document, which a typed invoice cannot hold.');
            break;
        }
    }

    $seller_vkn = defined('ERP_SELLER_VKN') ? preg_replace('/\D/', '', (string) ERP_SELLER_VKN) : '';
    $customer_vkn = (string) ($document['customer']['tax_number'] ?? '');

    if (($seller_vkn !== '') && ($customer_vkn !== '') && ($customer_vkn !== $seller_vkn)) {
        $warnings[] = lang(array('string' => 'The invoice is addressed to tax number {var:1}, not to the store\'s ({var:2}).', 'vars' => array($customer_vkn, $seller_vkn)));
    }

    $mapped = erp_edoc_inbox_lines($document);
    $refusals = array_merge($refusals, $mapped['refusals']);

    if (!empty($mapped['folded'])) {
        $warnings[] = lang(array('string' => 'Line(s) {var:1} are priced below a kuruş; each is kept as one unit of its amount, with the quantity and price in its description.', 'vars' => implode(', ', $mapped['folded'])));
    }

    $built = erp_manual_lines_build($mapped['lines']);

    if ($built['error'] !== '') {
        $refusals[] = $built['error'];
    }

    $differences = array();

    if ($built['error'] === '') {
        $totals = $built['totals'];
        $document_vat = 0;

        foreach ((array) $document['taxes'] as $tax) {
            if (((string) $tax['code'] === ERP_UBL_VAT_CODE) || (mb_strtoupper((string) $tax['name'], 'UTF-8') === 'KDV')) {
                $document_vat += (int) $tax['amount'];
            }
        }

        $compare = array(
            lang('Net') => array((int) $document['totals']['tax_exclusive'], (int) $totals['subtotal'] - (int) $totals['discount_total']),
            lang('VAT') => array($document_vat, (int) $totals['tax_total']),
            lang('VAT withholding') => array((int) $document['totals']['withholding'], (int) $totals['withholding_total']),
            // The payable amount has the withholding taken off, as the
            // ERP's total does, and the rounding added, which it does not.
            lang('Total') => array((int) $document['totals']['payable'] - (int) $document['totals']['rounding'], (int) $totals['grand_total']),
        );

        foreach ($compare as $label => $pair) {
            if ($pair[0] !== $pair[1]) {
                $differences[$label] = $pair;
            }
        }

        // A kurus per line is rounding - the supplier worked the VAT out
        // on an unrounded amount. More than that is a figure the typed
        // lines cannot reproduce, and taking it in would record a different
        // invoice than the one received.
        $gap = abs($compare[lang('Total')][0] - $compare[lang('Total')][1]);

        if (!empty($refusals)) {
            // Already refused; the gap is a consequence, not a second reason.
        } elseif ($gap > max(1, count($mapped['lines']))) {
            $refusals[] = lang(array(
                'string' => 'The lines do not add up to the invoice (a difference of {var:1}). Enter it by hand from the PDF.',
                'vars' => erp_money_out_currency($gap, $currency),
            ));
        } elseif ($gap > 0) {
            $warnings[] = lang(array(
                'string' => 'The totals differ from the invoice by {var:1} of rounding. Adjust a line in the draft before issuing it if the figure must match to the kuruş.',
                'vars' => erp_money_out_currency($gap, $currency),
            ));
        }
    }

    $existing = db_item("SELECT id, full_number, status FROM erp_invoices
        WHERE direction = 'purchase' AND gib_uuid = '" . escape((string) $document['uuid']) . "' AND status <> 'cancelled'
        ORDER BY id ASC LIMIT 1");

    $candidate = null;
    if (!is_array($existing) && ($account !== null)) {
        $candidate = db_item("SELECT id, full_number, status, issue_date, grand_total, currency FROM erp_invoices
            WHERE direction = 'purchase' AND account_id = '" . (int) $account['id'] . "'
              AND supplier_invoice_no = '" . escape(mb_substr((string) $document['number'], 0, 32)) . "'
              AND status <> 'cancelled'
            ORDER BY id ASC LIMIT 1");
    }

    $base = erp_base_currency();
    $rate = (array) $document['exchange_rate'];
    $exchange_rate = 0.0;
    $rate_date = (string) $document['issue_date'];

    if (($currency !== $base) && ((string) $rate['source'] === $currency) && ((string) $rate['target'] === $base) && ((float) $rate['rate'] > 0)) {
        $exchange_rate = (float) $rate['rate'];
        $rate_date = ((string) $rate['date'] !== '') ? (string) $rate['date'] : $rate_date;
    }

    $notes = array(lang(array('string' => 'Taken from incoming e-invoice {var:1} (ETTN {var:2}).', 'vars' => array($document['number'], $document['uuid']))));

    if ((string) $document['billing_reference']['number'] !== '') {
        $notes[] = lang(array('string' => 'Returns invoice {var:1}', 'vars' => $document['billing_reference']['number']));
    }

    return array(
        'success' => true,
        'error' => '',
        'document' => $document,
        'currency' => $currency,
        'account' => $account,
        'account_data' => $account_data,
        'lines' => $mapped['lines'],
        'source' => $mapped['source'],
        'folded' => $mapped['folded'],
        'built' => $built,
        'refusals' => array_values(array_unique($refusals)),
        'warnings' => $warnings,
        'differences' => $differences,
        'existing' => is_array($existing) ? $existing : null,
        'candidate' => is_array($candidate) ? $candidate : null,
        'header' => array(
            'direction' => 'purchase',
            'currency' => $currency,
            'exchange_rate' => $exchange_rate,
            'exchange_rate_date' => $rate_date,
            'exchange_rate_source' => ($currency === $base) ? 'base' : (($exchange_rate > 0) ? 'document' : ''),
            'issue_date' => (string) $document['issue_date'],
            'due_date' => (string) $document['due_date'],
            'supplier_invoice_no' => mb_substr((string) $document['number'], 0, 32),
            'supplier_invoice_date' => (string) $document['issue_date'],
            'notes' => implode("\n", $notes),
        ),
    );
}

/**
 * Takes a document in: opens the supplier's account when there is none, and
 * writes the purchase invoice as a draft carrying the document's ETTN and
 * number. The draft is issued like any other, from its own screen.
 *
 * @param int $id       erp_edoc_inbox.id
 * @param int $user_id
 * @return array ['success' => bool, 'error' => string, 'invoice_id' => int,
 *                'account_opened' => bool]
 */
function erp_edoc_inbox_import($id, $user_id = 0)
{
    $fail = function ($message) {
        return array('success' => false, 'error' => $message, 'invoice_id' => 0, 'account_opened' => false);
    };

    $row = erp_edoc_inbox_row($id);

    if ($row === null) {
        return $fail(lang('The document could not be found.'));
    }

    if (erp_edoc_inbox_state($row) !== 'new') {
        return $fail(lang('This document has already been dealt with.'));
    }

    // Read before the transaction: the provider call must not hold a lock.
    $proposal = erp_edoc_inbox_proposal($row);

    if (!$proposal['success']) {
        return $fail($proposal['error']);
    }

    if (!empty($proposal['refusals'])) {
        return $fail(implode(' ', $proposal['refusals']));
    }

    if ($proposal['existing'] !== null) {
        return erp_edoc_inbox_link($id, (int) $proposal['existing']['id'], $user_id);
    }

    if (!erp_tx_begin()) {
        return $fail(lang('Could not start a database transaction.'));
    }

    $locked = db_item("SELECT status, invoice_id FROM erp_edoc_inbox WHERE id = '" . (int) $id . "' LIMIT 1 FOR UPDATE");

    // Another screen may have taken it in or set it aside since it was read.
    if (!is_array($locked) || ((string) $locked['status'] !== (string) $row['status'])
        || ((int) $locked['invoice_id'] !== (int) $row['invoice_id'])) {
        erp_tx_rollback();
        return $fail(lang('This document has already been dealt with.'));
    }

    $account_opened = false;
    $account_id = ($proposal['account'] !== null) ? (int) $proposal['account']['id'] : 0;

    if ($account_id === 0) {
        $saved = erp_account_save($proposal['account_data'] + array('created_by' => (int) $user_id));

        if (!$saved['success']) {
            erp_tx_rollback();
            return $fail($saved['error']);
        }

        $account_id = (int) $saved['id'];
        $account_opened = true;
    }

    $draft = erp_invoice_draft_save(array_merge($proposal['header'], array(
        'account_id' => $account_id,
        'lines' => $proposal['lines'],
        'created_by' => (int) $user_id,
    )));

    if (!$draft['success']) {
        erp_tx_rollback();
        return $fail($draft['error']);
    }

    $invoice_id = (int) $draft['invoice_id'];
    $type = (string) $proposal['document']['type_code'];
    $types = array('SATIS', 'ISTISNA', 'TEVKIFAT', 'IADE', 'OZELMATRAH', 'IHRACAT');

    $ok = erp_query("UPDATE erp_invoices SET
            invoice_type = '" . escape(in_array($type, $types, true) ? $type : 'SATIS') . "',
            gib_uuid = '" . escape(substr((string) $row['gib_uuid'], 0, 36)) . "',
            gib_number = '" . escape(mb_substr((string) $proposal['document']['number'], 0, 20)) . "'
        WHERE id = '" . $invoice_id . "'");

    $ok = ($ok !== false) && (erp_query("UPDATE erp_edoc_inbox SET
            status = 'imported',
            invoice_id = '" . $invoice_id . "',
            account_id = '" . $account_id . "',
            handled_by = '" . (int) $user_id . "',
            handled_at = '" . time() . "',
            updated_at = '" . time() . "'
        WHERE id = '" . (int) $id . "'") !== false);

    if (!$ok || !erp_tx_commit()) {
        $error = erp_db_error();
        erp_tx_rollback();
        return $fail($error);
    }

    return array('success' => true, 'error' => '', 'invoice_id' => $invoice_id, 'account_opened' => $account_opened);
}

/**
 * Marks a document as taken in by a purchase invoice that already exists -
 * one typed in by hand before the document was read. The invoice gains the
 * ETTN when it has none, so the next read finds it on its own.
 *
 * @param int $id
 * @param int $invoice_id
 * @param int $user_id
 * @return array ['success', 'error', 'invoice_id', 'account_opened' => false]
 */
function erp_edoc_inbox_link($id, $invoice_id, $user_id = 0)
{
    $fail = function ($message) {
        return array('success' => false, 'error' => $message, 'invoice_id' => 0, 'account_opened' => false);
    };

    $row = erp_edoc_inbox_row($id);
    $invoice = db_item("SELECT id, account_id, status, gib_uuid FROM erp_invoices
        WHERE id = '" . (int) $invoice_id . "' AND direction = 'purchase' LIMIT 1");

    if (($row === null) || !is_array($invoice) || ((string) $invoice['status'] === 'cancelled')) {
        return $fail(lang('The purchase invoice could not be found.'));
    }

    if (((string) $invoice['gib_uuid'] !== '') && (strcasecmp((string) $invoice['gib_uuid'], (string) $row['gib_uuid']) !== 0)) {
        return $fail(lang('That purchase invoice already belongs to another e-invoice.'));
    }

    if ((string) $invoice['gib_uuid'] === '') {
        erp_query("UPDATE erp_invoices SET
                gib_uuid = '" . escape((string) $row['gib_uuid']) . "',
                gib_number = '" . escape(mb_substr((string) $row['gib_number'], 0, 20)) . "'
            WHERE id = '" . (int) $invoice['id'] . "' AND gib_uuid = ''");
    }

    $ok = erp_query("UPDATE erp_edoc_inbox SET
            status = 'imported',
            invoice_id = '" . (int) $invoice['id'] . "',
            account_id = '" . (int) $invoice['account_id'] . "',
            handled_by = '" . (int) $user_id . "',
            handled_at = '" . time() . "',
            updated_at = '" . time() . "'
        WHERE id = '" . (int) $id . "'");

    if ($ok === false) {
        return $fail(erp_db_error());
    }

    return array('success' => true, 'error' => '', 'invoice_id' => (int) $invoice['id'], 'account_opened' => false);
}

/**
 * Sets a document aside, or brings it back. A document whose purchase
 * invoice stands is neither: the invoice is dealt with on its own screen.
 *
 * @param int  $id
 * @param bool $aside
 * @param int  $user_id
 * @return array ['success' => bool, 'error' => string]
 */
function erp_edoc_inbox_set_aside($id, $aside, $user_id = 0)
{
    $row = erp_edoc_inbox_row($id);

    if ($row === null) {
        return array('success' => false, 'error' => lang('The document could not be found.'));
    }

    $state = erp_edoc_inbox_state($row);

    if (!in_array($state, array('new', 'ignored'), true)) {
        return array('success' => false, 'error' => lang('A document taken into the ERP is dealt with through its purchase invoice.'));
    }

    $ok = erp_query("UPDATE erp_edoc_inbox SET
            status = '" . ($aside ? 'ignored' : 'new') . "',
            invoice_id = 0,
            handled_by = '" . (int) $user_id . "',
            handled_at = '" . time() . "',
            updated_at = '" . time() . "'
        WHERE id = '" . (int) $id . "'");

    return ($ok === false)
        ? array('success' => false, 'error' => erp_db_error())
        : array('success' => true, 'error' => '');
}
