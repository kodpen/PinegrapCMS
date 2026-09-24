<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * ERP - the printed invoice.
 *
 * An invoice on screen and an invoice in the customer's hand are the same
 * document, so both are built from the same rows and the same money helpers.
 * The printed form is an HTML template with mustache-style placeholders that
 * the operator may edit in the panel; it is rendered to a PDF with the
 * vendored dompdf library. Nothing in here evaluates template code - the
 * renderer only substitutes and repeats.
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
 * Render a mustache-style template against an array of data.
 *
 * Supported syntax:
 *   {{name}}            value, HTML-escaped
 *   {{{name}}}          value, raw
 *   {{#name}}...{{/name}}  a list of rows repeats the block once per row, with
 *                       the row merged over the surrounding scope; a scalar or
 *                       an associative array renders the block once when truthy
 *   {{^name}}...{{/name}}  renders the block when the value is empty or false
 *   seller.title        dotted names walk into nested arrays
 *
 * Sections nest as long as the inner and outer blocks use different names.
 * Unknown names render as an empty string. The implementation is a pair of
 * regular expressions applied recursively; no template code is executed.
 *
 * @param string $template
 * @param array  $data
 * @return string
 */
function erp_template_render($template, $data)
{
    $data = is_array($data) ? $data : array();

    // Sections first, so the repeated block is rendered against each row before
    // the plain placeholders of the enclosing scope are filled in.
    $template = preg_replace_callback(
        '/\{\{([#^])\s*([A-Za-z0-9_.]+)\s*\}\}(.*?)\{\{\/\s*\2\s*\}\}/s',
        function ($match) use ($data) {
            $inverted = ($match[1] === '^');
            $value = erp_template_lookup($data, $match[2]);
            $block = $match[3];

            if ($inverted) {
                return erp_template_truthy($value) ? '' : erp_template_render($block, $data);
            }

            if (!erp_template_truthy($value)) {
                return '';
            }

            if (is_array($value) && erp_template_is_list($value)) {
                $output = '';
                foreach ($value as $row) {
                    $scope = is_array($row) ? array_merge($data, $row) : $data;
                    $output .= erp_template_render($block, $scope);
                }
                return $output;
            }

            // A truthy scalar or an associative array: the block is shown once.
            // An associative array also becomes the inner scope, so {{title}}
            // inside {{#seller}} reads seller.title.
            $scope = is_array($value) ? array_merge($data, $value) : $data;
            return erp_template_render($block, $scope);
        },
        $template
    );

    // Raw placeholders before escaped ones: {{{x}}} also matches the {{x}}
    // pattern, so the triple form has to be consumed first.
    $template = preg_replace_callback(
        '/\{\{\{\s*([A-Za-z0-9_.]+)\s*\}\}\}/',
        function ($match) use ($data) {
            return erp_template_scalar(erp_template_lookup($data, $match[1]));
        },
        $template
    );

    return preg_replace_callback(
        '/\{\{\s*([A-Za-z0-9_.]+)\s*\}\}/',
        function ($match) use ($data) {
            return h(erp_template_scalar(erp_template_lookup($data, $match[1])));
        },
        $template
    );
}

/**
 * Walk a dotted name into nested arrays. Returns null when any step is missing.
 */
function erp_template_lookup($data, $name)
{
    $value = $data;

    foreach (explode('.', $name) as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) {
            return null;
        }
        $value = $value[$part];
    }

    return $value;
}

/**
 * Whether a section should render: false, null, '', '0', 0 and [] do not.
 */
function erp_template_truthy($value)
{
    if (is_array($value)) {
        return !empty($value);
    }

    return !($value === null || $value === false || $value === '' || $value === '0' || $value === 0);
}

/**
 * A list is an array whose keys are 0..n-1 - the shape the rows come in.
 */
function erp_template_is_list($value)
{
    return array_keys($value) === range(0, count($value) - 1);
}

/**
 * A value as it goes into the page: arrays print nothing, booleans print 1 or
 * nothing, everything else is cast to string.
 */
function erp_template_scalar($value)
{
    if ($value === null || is_array($value)) {
        return '';
    }

    if (is_bool($value)) {
        return $value ? '1' : '';
    }

    return (string) $value;
}

/**
 * A DATE column for the page: an unset date prints nothing rather than a
 * formatted zero.
 */
function erp_document_date($value)
{
    $value = (string) $value;

    if ($value === '' || $value === '0000-00-00' || strpos($value, '0000-00-00') === 0) {
        return '';
    }

    return (string) prepare_form_data_for_output($value, 'date', false);
}

/**
 * The seller's logo, both as an address and as an inline data URI.
 *
 * The PDF renderer runs with remote fetching switched off, so the image has to
 * travel inside the document; the data URI is what the default template uses.
 * The setting holds either a file name from the Files screen or a full URL.
 *
 * @return array ['url' => string, 'data_uri' => string]
 */
function erp_document_logo()
{
    $logo = array('url' => '', 'data_uri' => '');
    $setting = defined('ORGANIZATION_LOGO') ? trim((string) ORGANIZATION_LOGO) : '';

    if ($setting === '') {
        return $logo;
    }

    if (function_exists('pg_resolve_config_image')) {
        $resolved = pg_resolve_config_image($setting);
        if (is_array($resolved) && !empty($resolved['url'])) {
            $logo['url'] = (string) $resolved['url'];
        }
    }

    if (preg_match('#^https?://#i', $setting)) {
        return $logo;
    }

    $directory = defined('FILE_DIRECTORY_PATH') ? FILE_DIRECTORY_PATH : PG_FUNCTIONS_DIR . '/data/files';
    $path = $directory . '/' . basename($setting);
    $types = array('png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'gif' => 'image/gif', 'svg' => 'image/svg+xml', 'webp' => 'image/webp');
    $extension = mb_strtolower(pathinfo($path, PATHINFO_EXTENSION));

    if (isset($types[$extension]) && is_file($path) && is_readable($path)) {
        $contents = @file_get_contents($path);
        if ($contents !== false && $contents !== '') {
            $logo['data_uri'] = 'data:' . $types[$extension] . ';base64,' . base64_encode($contents);
        }
    }

    return $logo;
}

/**
 * The captions the printed invoice carries, in the site language.
 *
 * The template holds no words of its own: every heading, column title and
 * footer caption is read from here as {{label.name}}, so the built-in
 * document follows the software language and a translation change reaches
 * it without an edit. The two captions that name the base currency are
 * resolved here because a template placeholder cannot take an argument. The
 * same array feeds the placeholder reference on the settings screen.
 *
 * @return array  name => translated caption
 */
function erp_invoice_document_labels()
{
    $base_currency = erp_base_currency();

    return array(
        'invoice_no' => lang('Invoice No'),
        'invoice_date' => lang('Invoice Date'),
        'due_date' => lang('Due Date'),
        'returned_invoice' => lang('Returned Invoice'),
        'order_no' => lang('Order No'),
        'order' => lang('Order'),
        'currency' => lang('Currency'),
        'exchange_rate' => lang(array('string' => 'Exchange Rate ({var:1})', 'vars' => array($base_currency))),
        'exchange_rate_date' => lang('Exchange Rate Date'),
        // The seller's number is named by the store's country, the buyer's by
        // theirs (set per document, where the account is known).
        'tax_id' => erp_tax_id_label(),
        'account_tax_id' => erp_tax_id_label(),
        'tax_office' => lang('Tax Office'),
        'bill_to' => lang('BILL TO'),
        'description' => lang('Description'),
        'quantity' => lang('Quantity'),
        'unit_price' => lang('Unit Price'),
        'discount' => lang('Discount'),
        'taxable_amount' => lang('Taxable Amount'),
        'vat' => erp_tax_label('tax'),
        'vat_amount' => erp_tax_label('amount'),
        'total' => lang('Total'),
        'no_lines' => lang('This document has no lines.'),
        'subtotal' => lang('Subtotal'),
        'shipping' => lang('Shipping'),
        'surcharge' => lang('Surcharge'),
        'vat_total' => erp_tax_label('calculated'),
        'grand_total' => lang('Grand Total'),
        'grand_total_base' => lang(array('string' => 'Grand Total ({var:1} equivalent)', 'vars' => array($base_currency))),
        'tax_inclusive' => lang('Total including taxes'),
        'withholding' => lang('VAT withholding'),
        'amount_payable' => lang('Amount payable'),
        'gift_card' => lang('Settled by gift card'),
        'internet_sale' => lang('INTERNET SALE DETAILS'),
        'sale_address' => lang('Address the sale was made at'),
        'payment_method' => lang('Payment Method'),
        'payment_date' => lang('Payment Date'),
        'carrier' => lang('Carrier'),
        'carrier_tax_id' => lang('Tax ID'),
        'shipment_date' => lang('Shipment Date'),
        'note' => lang('Note'),
        'generated_at' => lang('Generated at'),
    );
}

/**
 * Everything the printed invoice says, as one nested array for the template.
 *
 * The figures are formatted here with the same helpers the invoice screen
 * uses, so the document and the screen cannot disagree on a rounding. Every
 * seller constant is guarded: the document must still render on an
 * installation that has not filled in its organization settings. The
 * captions travel with the data under `label`, and `language` is the site
 * language code for the html lang attribute.
 *
 * A document that is not an invoice row - a quote (includes/erp/quotes.php)
 * - passes its own row and lines as $source, shaped like an erp_invoices row
 * with the live_* account columns and erp_invoice_items rows, and may name
 * its own title and captions.
 *
 * @param int        $invoice_id
 * @param array|null $source  ['invoice' => row, 'items' => rows, 'title' => string, 'label' => array]
 * @return array|false  false when the invoice does not exist
 */
function erp_invoice_document_data($invoice_id, $source = null)
{
    $invoice_id = (int) $invoice_id;

    $invoice = is_array($source) ? (array) $source['invoice'] : (($invoice_id > 0)
        ? db_item("SELECT i.*,
                a.title AS live_title, a.is_person AS live_is_person,
                a.tax_number AS live_tax_number, a.tax_office AS live_tax_office,
                a.address AS live_address, a.district AS live_district,
                a.city AS live_city, a.country_code AS live_country_code,
                " . (waf_table_has_column('erp_accounts', 'state') ? 'a.state AS live_state,' : '') . "
                a.postcode AS live_postcode, a.email AS live_email, a.phone AS live_phone,
                o.order_number
            FROM erp_invoices i
            LEFT JOIN erp_accounts a ON i.account_id = a.id
            LEFT JOIN orders o ON i.order_id = o.id
            WHERE i.id = '" . $invoice_id . "' LIMIT 1")
        : null);

    if (!is_array($invoice)) {
        return false;
    }

    $constant = function ($name) {
        return defined($name) ? trim((string) constant($name)) : '';
    };

    $logo = erp_document_logo();

    $seller = array(
        'title' => $constant('ORGANIZATION_NAME'),
        'address_1' => $constant('ORGANIZATION_ADDRESS_1'),
        'address_2' => $constant('ORGANIZATION_ADDRESS_2'),
        'city' => $constant('ORGANIZATION_CITY'),
        'state' => $constant('ORGANIZATION_STATE'),
        'zip_code' => $constant('ORGANIZATION_ZIP_CODE'),
        'country' => $constant('ORGANIZATION_COUNTRY'),
        'vkn' => $constant('ERP_SELLER_VKN'),
        'tax_office' => $constant('ERP_SELLER_TAX_OFFICE'),
        'web_address' => $constant('ERP_WEB_ADDRESS'),
        'email' => $constant('ECOMMERCE_EMAIL_ADDRESS'),
        'logo_url' => $logo['url'],
        'logo_data_uri' => $logo['data_uri'],
    );
    $seller['locality'] = erp_seller_locality($seller);

    // The counterparty is the copy taken when the document was issued. An
    // invoice written before the copy existed reads the live card instead.
    // Since 4.64 the copy carries the district and the postcode of its own,
    // which is what these templates print on their second address line; a
    // copy from before that upgrade has them empty and prints as it did.
    if (trim((string) ($invoice['account_title'] ?? '')) !== '') {
        $account = array(
            'title' => (string) $invoice['account_title'],
            'is_person' => ((int) $invoice['live_is_person'] === 1),
            'tax_number' => (string) $invoice['account_tax_number'],
            'tax_office' => (string) $invoice['account_tax_office'],
            'address' => (string) $invoice['account_address'],
            'district' => (string) ($invoice['account_district'] ?? ''),
            'city' => (string) $invoice['account_city'],
            'state' => (string) ($invoice['account_state'] ?? ''),
            'country' => (string) $invoice['account_country_code'],
            'postcode' => (string) ($invoice['account_postcode'] ?? ''),
            'email' => (string) $invoice['account_email'],
            'phone' => (string) $invoice['live_phone'],
        );
    } else {
        $account = array(
            'title' => (string) $invoice['live_title'],
            'is_person' => ((int) $invoice['live_is_person'] === 1),
            'tax_number' => (string) $invoice['live_tax_number'],
            'tax_office' => (string) $invoice['live_tax_office'],
            'address' => (string) $invoice['live_address'],
            'district' => (string) $invoice['live_district'],
            'city' => (string) $invoice['live_city'],
            'state' => (string) ($invoice['live_state'] ?? ''),
            'country' => (string) $invoice['live_country_code'],
            'postcode' => (string) $invoice['live_postcode'],
            'email' => (string) $invoice['live_email'],
            'phone' => (string) $invoice['live_phone'],
        );
    }

    $account['locality'] = erp_address_locality($account, $account['country']);

    $doc_type = (string) $invoice['doc_type'];
    $is_return = ($doc_type === 'return');

    $titles = array(
        'invoice' => lang('INVOICE'),
        'return' => lang('RETURN INVOICE'),
        'proforma' => lang('PROFORMA INVOICE'),
    );

    $status_labels = array(
        'draft' => lang('Draft'),
        'issued' => lang('Issued'),
        'partially_paid' => lang('Partly paid'),
        'paid' => lang('Paid'),
        'cancelled' => lang('Cancelled'),
    );

    $against_number = '';
    if ($is_return && ((int) $invoice['parent_invoice_id'] > 0)) {
        $against_number = (string) db_value("SELECT full_number FROM erp_invoices WHERE id = '" . (int) $invoice['parent_invoice_id'] . "'");
    }

    $order_number = '';
    if ((int) $invoice['order_id'] > 0) {
        $order_number = ((string) $invoice['order_number'] !== '') ? (string) $invoice['order_number'] : ('#' . (int) $invoice['order_id']);
    }

    // The payment method is stored as a code. The label map lives with the
    // order bridge where the codes are written; when it is not there the code
    // itself is printed rather than nothing.
    $payment_method = (string) $invoice['payment_method'];
    $payment_method_label = $payment_method;
    if ($payment_method !== '' && function_exists('erp_payment_method_labels')) {
        $labels = erp_payment_method_labels();
        if (is_array($labels) && isset($labels[$payment_method])) {
            $payment_method_label = (string) $labels[$payment_method];
        }
    }

    // A document in another currency states its rate, the day it was fixed
    // and the base-currency total; a base-currency document shows none of it.
    $currency = strtoupper(trim((string) $invoice['currency']));
    $is_foreign = ($currency !== erp_base_currency());

    $money = function ($kurus) use ($currency) {
        return erp_money_out_currency((int) $kurus, $currency);
    };

    $document = array(
        'id' => (int) $invoice['id'],
        'full_number' => (string) $invoice['full_number'],
        'issue_date' => erp_document_date($invoice['issue_date']),
        'due_date' => erp_document_date($invoice['due_date']),
        'doc_type' => $doc_type,
        'is_return' => $is_return,
        'title' => (is_array($source) && isset($source['title'])) ? (string) $source['title'] : ($titles[$doc_type] ?? $titles['invoice']),
        'status' => (string) $invoice['status'],
        'status_label' => $status_labels[$invoice['status']] ?? (string) $invoice['status'],
        'order_number' => $order_number,
        'against_number' => $against_number,
        'currency' => $currency,
        'is_foreign' => $is_foreign,
        'base_currency' => erp_base_currency(),
        'exchange_rate' => $is_foreign ? erp_fx_rate_text((float) $invoice['exchange_rate']) : '',
        'exchange_rate_date' => $is_foreign ? erp_document_date($invoice['exchange_rate_date']) : '',
        'grand_total_base' => $is_foreign ? erp_money_out((int) $invoice['grand_total_base']) : '',
        // The internet-sale box is what a Turkish e-Arşiv invoice has to
        // carry; a store outside Turkey that sells online prints none.
        'is_internet_sale' => ((int) $invoice['is_internet_sale'] === 1) && erp_turkish_features(),
        'payment_method' => $payment_method,
        'payment_method_label' => $payment_method_label,
        'payment_date' => erp_document_date($invoice['payment_date']),
        'shipment_date' => erp_document_date($invoice['shipment_date']),
        'carrier_title' => (string) $invoice['carrier_title'],
        'carrier_vkn' => (string) $invoice['carrier_vkn'],
        'web_address' => (string) $invoice['web_address'],
        'notes' => (string) ($invoice['notes'] ?? ''),
        'is_purchase' => ((string) $invoice['direction'] === 'purchase'),
        'supplier_invoice_no' => (string) ($invoice['supplier_invoice_no'] ?? ''),
        'supplier_invoice_date' => erp_document_date($invoice['supplier_invoice_date'] ?? ''),
    );

    $rows = is_array($source) ? (array) ($source['items'] ?? array()) : (array) db_items("SELECT * FROM erp_invoice_items
        WHERE invoice_id = '" . $invoice_id . "' ORDER BY line_no ASC, id ASC");

    $lines = array();
    $tax2_name = (erp_tax2_name() !== '') ? erp_tax2_name() : lang('Second tax');

    foreach ($rows as $row) {
        $discount = (int) $row['discount_amount'];
        $base = (int) $row['line_total'] - $discount;
        $tax = (int) $row['tax_total'];
        // A line's tax_total holds both taxes when it carries a second one;
        // the rate column names both rates.
        $tax2_rate = (float) ($row['tax2_rate'] ?? 0);
        $tax_rate_text = erp_percent_text($row['tax_rate']) . (($tax2_rate > 0) ? (' + ' . erp_percent_text($tax2_rate)) : '');

        $withholding_code = trim((string) ($row['withholding_code'] ?? ''));

        $lines[] = array(
            'no' => (int) $row['line_no'],
            'description' => (string) $row['description'],
            'withholding' => ($withholding_code !== '')
                ? lang(array('string' => 'VAT withholding {var:1}: {var:2}', 'vars' => array(
                    erp_withholding_label($withholding_code, (float) $row['withholding_rate'], false),
                    $money((int) ($row['withholding_amount'] ?? 0)))))
                : '',
            'quantity' => erp_quantity_text($row['quantity']),
            'unit' => (string) $row['unit_code'],
            'unit_price' => $money((int) $row['unit_price']),
            'discount' => ($discount > 0) ? $money($discount) : '',
            'has_discount' => ($discount > 0),
            'base' => $money($base),
            'tax_rate' => $tax_rate_text,
            'tax' => $money($tax),
            'tax2_rate' => ($tax2_rate > 0) ? erp_percent_text($tax2_rate) : '',
            'tax2' => ($tax2_rate > 0) ? $money((int) ($row['tax2_amount'] ?? 0)) : '',
            'total' => $money($base + $tax),
        );
    }

    $totals = array(
        'subtotal' => $money((int) $invoice['subtotal']),
        'discount_total' => $money((int) $invoice['discount_total']),
        'shipping_total' => $money((int) $invoice['shipping_total']),
        'surcharge_total' => $money((int) $invoice['surcharge_total']),
        'gift_card_total' => $money((int) $invoice['gift_card_total']),
        // The first tax alone; a second one, where the document carries it,
        // has its own line (tax2_total) and both are in tax_total.
        'tax_total' => $money((int) $invoice['tax_total'] - (int) ($invoice['tax2_total'] ?? 0)),
        'tax2_total' => $money((int) ($invoice['tax2_total'] ?? 0)),
        'has_tax2' => ((int) ($invoice['tax2_total'] ?? 0) !== 0),
        // With withholding the grand total is what the buyer pays the
        // seller; the document also shows the total before it comes off.
        'withholding_total' => $money((int) ($invoice['withholding_total'] ?? 0)),
        'tax_inclusive' => $money((int) $invoice['grand_total'] + (int) ($invoice['withholding_total'] ?? 0)),
        'has_withholding' => ((int) ($invoice['withholding_total'] ?? 0) !== 0),
        'grand_total' => $money((int) $invoice['grand_total']),
        'grand_total_base' => $is_foreign ? erp_money_out((int) $invoice['grand_total_base']) : '',
        'has_discount' => ((int) $invoice['discount_total'] !== 0),
        'has_shipping' => ((int) $invoice['shipping_total'] !== 0),
        'has_surcharge' => ((int) $invoice['surcharge_total'] !== 0),
        'has_gift_card' => ((int) $invoice['gift_card_total'] !== 0),
    );

    return array(
        'seller' => $seller,
        'account' => $account,
        'invoice' => $document,
        'lines' => $lines,
        'totals' => $totals,
        // A line's tax amount holds both taxes on a document that carries
        // the second one, and its column is named for both.
        'label' => array_merge(erp_invoice_document_labels(), array(
            'account_tax_id' => erp_tax_id_label((string) ($account['country'] ?? '')),
            'tax2' => $tax2_name,
            'vat' => erp_line_tax_label('tax', $totals['has_tax2']),
            'vat_amount' => erp_line_tax_label('amount', $totals['has_tax2']),
        ), is_array($source) ? (array) ($source['label'] ?? array()) : array()),
        'language' => defined('SOFTWARE_LANGUAGE') ? (string) SOFTWARE_LANGUAGE : 'en',
        'paper' => erp_paper_size(),
        'generated_at' => (string) prepare_form_data_for_output(date('Y-m-d H:i:s'), 'date and time', false),
    );
}

/**
 * The built-in invoice template, as shipped.
 */
function erp_invoice_default_template()
{
    $path = PG_FUNCTIONS_DIR . '/includes/erp/templates/invoice_default.html';
    $contents = is_file($path) ? @file_get_contents($path) : false;

    return ($contents === false) ? '' : $contents;
}

/**
 * The template the invoices are printed from: the one saved in the panel when
 * there is one, otherwise the built-in file.
 */
function erp_invoice_template()
{
    $saved = db_value("SELECT erp_invoice_template FROM config LIMIT 1");

    if (is_string($saved) && trim($saved) !== '') {
        return $saved;
    }

    return erp_invoice_default_template();
}

/**
 * The invoice as a complete HTML document.
 *
 * @param int         $invoice_id
 * @param string|null $template  a template to try instead of the saved one
 * @return string|false  false when the invoice does not exist
 */
function erp_invoice_html($invoice_id, $template = null)
{
    $data = erp_invoice_document_data($invoice_id);

    if ($data === false) {
        return false;
    }

    if (!is_string($template)) {
        $template = erp_invoice_template();
    }

    return erp_template_render($template, $data);
}

/**
 * The paper a document is laid out on: US Letter in the countries that use
 * it (the United States, Canada, Mexico, the Philippines and much of Latin
 * America), A4 everywhere else. The built-in templates take it through
 * {{paper}} in their @page rule; a template may still name its own size.
 *
 * @return string  A4 | letter
 */
function erp_paper_size()
{
    $letter = array('US', 'CA', 'MX', 'PH', 'CL', 'CO', 'VE', 'CR', 'GT', 'DO', 'PR', 'PA', 'SV', 'NI', 'HN');

    return in_array(erp_account_country(''), $letter, true) ? 'letter' : 'A4';
}

/**
 * Turn the rendered document into PDF bytes with the vendored dompdf.
 *
 * Remote fetching and embedded PHP are off and the renderer is rooted at the
 * software directory, so a template can only reach what the document itself
 * carries. dompdf writes font-metric caches and temporary files as it goes; it
 * would put them beside its own fonts by default, which sit inside the tree
 * the file-integrity check covers, so they are pointed at data/cache instead.
 *
 * @param string $html
 * @return string|false  false when the library is not installed
 */
function erp_invoice_pdf($html)
{
    $autoload = PG_FUNCTIONS_DIR . '/includes/dompdf/autoload.inc.php';

    if (!is_file($autoload)) {
        return false;
    }

    // The vendored autoloader checks the same include gate as the API entry.
    if (!defined('PG_API_ENTRY')) {
        define('PG_API_ENTRY', true);
    }

    require_once($autoload);

    if (!class_exists('\Dompdf\Dompdf')) {
        return false;
    }

    $cache_directory = PG_FUNCTIONS_DIR . '/data/cache/dompdf';
    if (!is_dir($cache_directory)) {
        @mkdir($cache_directory, 0755, true);
    }

    $options = new \Dompdf\Options();
    $options->setIsRemoteEnabled(false);
    $options->setIsPhpEnabled(false);
    $options->setDefaultFont('DejaVu Sans');
    $options->setChroot(PG_FUNCTIONS_DIR);
    $options->setFontCache($cache_directory);
    $options->setTempDir($cache_directory);
    $options->setLogOutputFile('');

    $dompdf = new \Dompdf\Dompdf($options);
    $dompdf->loadHtml(erp_invoice_pdf_prepare_html((string) $html), 'UTF-8');
    $dompdf->setPaper(erp_paper_size(), 'portrait');
    $dompdf->render();

    return $dompdf->output();
}

/**
 * Make the document safe for dompdf's second parse.
 *
 * dompdf parses the HTML once with html5-php and then again with libxml, and
 * libxml reads HTML as ISO-8859-1 until it meets the charset declaration; once
 * it has decoded a non-ASCII byte it no longer switches. A comment or a title
 * written in Turkish above <meta charset> therefore turns every accented
 * character of the whole document into two wrong ones. Comments never reach the
 * page, so they are dropped, and the one charset declaration is put first in
 * <head>, ahead of anything an edited template may carry there.
 *
 * @param string $html
 * @return string
 */
function erp_invoice_pdf_prepare_html($html)
{
    $html = preg_replace('/<!--.*?-->/s', '', $html);
    $html = preg_replace('/<meta\s[^>]*charset[^>]*>/i', '', $html);

    if (preg_match('/<head\b[^>]*>/i', $html, $match, PREG_OFFSET_CAPTURE)) {
        $position = $match[0][1] + strlen($match[0][0]);
        return substr($html, 0, $position) . '<meta charset="utf-8">' . substr($html, $position);
    }

    return '<meta charset="utf-8">' . $html;
}
