<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * ERP - reading a UBL-TR invoice.
 *
 * An incoming e-invoice is the supplier's signed UBL-TR XML, usually handed
 * over inside a zip. This file turns it into plain arrays: the header, the
 * two parties, the taxes, the lines and the totals, every amount in kurus.
 * It knows nothing about providers - a driver hands over the bytes and the
 * same reader does the rest - and it decides nothing about what the ERP does
 * with the result; includes/erp/edoc/inbox.php does that.
 *
 * Elements are found by namespace, never by prefix: a signer is free to call
 * the namespaces what it likes, and the schema (UBL 2.1, TR1.2 customisation)
 * is what fixes their meaning. The document is parsed without network access
 * and without entity substitution, so a hostile file can neither fetch
 * anything nor expand itself.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_ERP_ENTRY')) {
    exit;
}

define('ERP_UBL_CBC', 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2');
define('ERP_UBL_CAC', 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2');

// KDV's code in the tax authority's tax-type list.
define('ERP_UBL_VAT_CODE', '0015');

// The largest XML file taken out of an archive. A signed invoice with its
// stylesheet is a few hundred kilobytes; a thousand-line one a few megabytes.
define('ERP_UBL_MAX_BYTES', 32 * 1024 * 1024);

/**
 * The XML of an invoice out of what a provider handed over: the XML itself,
 * or a zip with the XML in it.
 *
 * The zip is read here rather than through ZipArchive: the extension is not
 * everywhere, and a temporary file for a document that is already in memory
 * is one more thing that can fail on a shared host. Stored and deflated
 * entries are what integrators produce; anything else is refused by name.
 *
 * @param string $content  The bytes
 * @return array ['success' => bool, 'xml' => string, 'error' => string]
 */
function erp_ubl_unpack($content)
{
    $content = (string) $content;
    $fail = function ($message) {
        return array('success' => false, 'xml' => '', 'error' => $message);
    };

    if (strncmp($content, 'PK', 2) !== 0) {
        $head = ltrim(substr($content, 0, 64), "\xEF\xBB\xBF \t\r\n");

        return (strncmp($head, '<', 1) === 0)
            ? array('success' => true, 'xml' => $content, 'error' => '')
            : $fail(lang('The document is neither XML nor a zip archive.'));
    }

    // The end-of-central-directory record: 22 bytes plus a comment of up to
    // 64 KB, so it is searched for in the tail.
    $tail = substr($content, -min(strlen($content), 65557));
    $end = strrpos($tail, "PK\x05\x06");

    if ($end === false) {
        return $fail(lang('The zip archive is damaged.'));
    }

    $record = unpack('ventries/Vsize/Voffset', substr($tail, $end + 10, 10));
    $position = (int) $record['offset'];
    $entries = (int) $record['entries'];
    $fallback = null;

    for ($i = 0; $i < $entries; $i++) {
        if (substr($content, $position, 4) !== "PK\x01\x02") {
            return $fail(lang('The zip archive is damaged.'));
        }

        $entry = unpack('vmethod/Vcrc/Vcompressed/Vsize/vname_length/vextra_length/vcomment_length', substr($content, $position + 10, 24));
        $local = unpack('Voffset', substr($content, $position + 42, 4));
        $name = substr($content, $position + 46, (int) $entry['name_length']);
        $position += 46 + (int) $entry['name_length'] + (int) $entry['extra_length'] + (int) $entry['comment_length'];

        if (substr($name, -1) === '/') {
            continue;
        }

        $item = array('name' => $name, 'method' => (int) $entry['method'], 'compressed' => (int) $entry['compressed'],
            'size' => (int) $entry['size'], 'offset' => (int) $local['offset']);

        if (strtolower(substr($name, -4)) === '.xml') {
            $fallback = $item;
            break;
        }

        if ($fallback === null) {
            $fallback = $item;
        }
    }

    if ($fallback === null) {
        return $fail(lang('The zip archive holds no document.'));
    }

    if ($fallback['size'] > ERP_UBL_MAX_BYTES) {
        return $fail(lang('The document is too large to read.'));
    }

    $offset = $fallback['offset'];

    if (substr($content, $offset, 4) !== "PK\x03\x04") {
        return $fail(lang('The zip archive is damaged.'));
    }

    $header = unpack('vname_length/vextra_length', substr($content, $offset + 26, 4));
    $data = substr($content, $offset + 30 + (int) $header['name_length'] + (int) $header['extra_length'], $fallback['compressed']);

    if ($fallback['method'] === 0) {
        $xml = $data;
    } elseif (($fallback['method'] === 8) && function_exists('gzinflate')) {
        $xml = @gzinflate($data, ERP_UBL_MAX_BYTES);
    } else {
        return $fail(lang(array('string' => 'The zip archive uses a compression method that cannot be read here ({var:1}).', 'vars' => $fallback['method'])));
    }

    if (!is_string($xml) || ($xml === '')) {
        return $fail(lang('The zip archive is damaged.'));
    }

    return array('success' => true, 'xml' => $xml, 'error' => '');
}

/**
 * A UBL decimal amount as kurus. UBL amounts are xsd:decimal - a point, no
 * thousands separator - so they are not read with erp_kurus(), which is for
 * what people type.
 *
 * @param string $value
 * @return int
 */
function erp_ubl_kurus($value)
{
    $value = trim((string) $value);

    return ($value === '') ? 0 : (int) round(((float) $value) * 100);
}

/**
 * Reads an invoice.
 *
 * @param string $xml
 * @return array ['success' => bool, 'error' => string, 'document' => array]
 *
 * The document: number, uuid, issue_date, issue_time, type_code, profile,
 * currency, exchange_rate {rate, source, target, date}, due_date, notes[],
 * billing_reference {number, date}, despatch[] {number, date},
 * supplier and customer (see erp_ubl_party()), taxes[] and withholdings[]
 * (see erp_ubl_taxes()), allowances[] (document level: charge, amount,
 * rate, reason), totals {line_extension, tax_exclusive, tax_inclusive,
 * allowance, charge, rounding, payable, tax, withholding}, lines[].
 *
 * A line: no, name, description, seller_code, buyer_code, brand, model,
 * quantity, unit_code, price (the decimal as written), line_extension,
 * allowances[], taxes[], withholdings[], notes[].
 */
function erp_ubl_read($xml)
{
    $fail = function ($message) {
        return array('success' => false, 'error' => $message, 'document' => array());
    };

    if (!class_exists('DOMDocument')) {
        return $fail(lang('Reading e-invoices needs the PHP DOM extension.'));
    }

    $previous = libxml_use_internal_errors(true);
    $dom = new DOMDocument();
    $loaded = $dom->loadXML((string) $xml, LIBXML_NONET | LIBXML_COMPACT);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);

    if (!$loaded || !$dom->documentElement || ($dom->documentElement->localName !== 'Invoice')) {
        return $fail(lang('The document is not a UBL invoice.'));
    }

    $xp = new DOMXPath($dom);
    $xp->registerNamespace('cbc', ERP_UBL_CBC);
    $xp->registerNamespace('cac', ERP_UBL_CAC);
    $root = $dom->documentElement;

    $text = function ($path, $context = null) use ($xp, $root) {
        $nodes = $xp->query($path, $context ?: $root);

        return ($nodes && ($nodes->length > 0)) ? trim((string) $nodes->item(0)->textContent) : '';
    };
    $date = function ($value) {
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $value) ? (string) $value : '';
    };

    $notes = array();
    foreach ($xp->query('cbc:Note', $root) as $node) {
        $note = trim((string) $node->textContent);
        if ($note !== '') {
            $notes[] = $note;
        }
    }

    $despatch = array();
    foreach ($xp->query('cac:DespatchDocumentReference', $root) as $node) {
        $number = $text('cbc:ID', $node);
        if ($number !== '') {
            $despatch[] = array('number' => $number, 'date' => $date($text('cbc:IssueDate', $node)));
        }
    }

    $allowances = array();
    foreach ($xp->query('cac:AllowanceCharge', $root) as $node) {
        $allowances[] = erp_ubl_allowance($xp, $node, $text);
    }

    $due_date = $date($text('cac:PaymentMeans/cbc:PaymentDueDate'));
    if ($due_date === '') {
        $due_date = $date($text('cac:PaymentTerms/cbc:PaymentDueDate'));
    }

    $lines = array();
    foreach ($xp->query('cac:InvoiceLine', $root) as $node) {
        $line_notes = array();
        foreach ($xp->query('cbc:Note', $node) as $note_node) {
            $note = trim((string) $note_node->textContent);
            if ($note !== '') {
                $line_notes[] = $note;
            }
        }

        $line_allowances = array();
        foreach ($xp->query('cac:AllowanceCharge', $node) as $allowance_node) {
            $line_allowances[] = erp_ubl_allowance($xp, $allowance_node, $text);
        }

        $quantity_nodes = $xp->query('cbc:InvoicedQuantity', $node);
        $quantity_node = ($quantity_nodes && ($quantity_nodes->length > 0)) ? $quantity_nodes->item(0) : null;

        $lines[] = array(
            'no' => $text('cbc:ID', $node),
            'name' => $text('cac:Item/cbc:Name', $node),
            'description' => $text('cac:Item/cbc:Description', $node),
            'seller_code' => $text('cac:Item/cac:SellersItemIdentification/cbc:ID', $node),
            'buyer_code' => $text('cac:Item/cac:BuyersItemIdentification/cbc:ID', $node),
            'brand' => $text('cac:Item/cbc:BrandName', $node),
            'model' => $text('cac:Item/cbc:ModelName', $node),
            'quantity' => ($quantity_node !== null) ? (float) trim((string) $quantity_node->textContent) : 0.0,
            'unit_code' => ($quantity_node !== null) ? strtoupper(trim((string) $quantity_node->getAttribute('unitCode'))) : '',
            'price' => $text('cac:Price/cbc:PriceAmount', $node),
            'line_extension' => erp_ubl_kurus($text('cbc:LineExtensionAmount', $node)),
            'allowances' => $line_allowances,
            'taxes' => erp_ubl_taxes($xp, $node, 'cac:TaxTotal', $text),
            'withholdings' => erp_ubl_taxes($xp, $node, 'cac:WithholdingTaxTotal', $text),
            'notes' => $line_notes,
        );
    }

    $taxes = erp_ubl_taxes($xp, $root, 'cac:TaxTotal', $text);
    $withholdings = erp_ubl_taxes($xp, $root, 'cac:WithholdingTaxTotal', $text);

    $tax_total = 0;
    foreach ($xp->query('cac:TaxTotal/cbc:TaxAmount', $root) as $node) {
        $tax_total += erp_ubl_kurus($node->textContent);
    }

    $withholding_total = 0;
    foreach ($xp->query('cac:WithholdingTaxTotal/cbc:TaxAmount', $root) as $node) {
        $withholding_total += erp_ubl_kurus($node->textContent);
    }

    $monetary = 'cac:LegalMonetaryTotal/';

    $document = array(
        'number' => $text('cbc:ID'),
        'uuid' => $text('cbc:UUID'),
        'issue_date' => $date($text('cbc:IssueDate')),
        'issue_time' => $text('cbc:IssueTime'),
        'type_code' => strtoupper($text('cbc:InvoiceTypeCode')),
        'profile' => strtoupper($text('cbc:ProfileID')),
        'currency' => strtoupper($text('cbc:DocumentCurrencyCode')),
        'exchange_rate' => array(
            'rate' => (float) $text('cac:PricingExchangeRate/cbc:CalculationRate'),
            'source' => strtoupper($text('cac:PricingExchangeRate/cbc:SourceCurrencyCode')),
            'target' => strtoupper($text('cac:PricingExchangeRate/cbc:TargetCurrencyCode')),
            'date' => $date($text('cac:PricingExchangeRate/cbc:Date')),
        ),
        'due_date' => $due_date,
        'notes' => $notes,
        'billing_reference' => array(
            'number' => $text('cac:BillingReference/cac:InvoiceDocumentReference/cbc:ID'),
            'date' => $date($text('cac:BillingReference/cac:InvoiceDocumentReference/cbc:IssueDate')),
        ),
        'order_reference' => $text('cac:OrderReference/cbc:ID'),
        'despatch' => $despatch,
        'supplier' => erp_ubl_party($xp, 'cac:AccountingSupplierParty/cac:Party', $text),
        'customer' => erp_ubl_party($xp, 'cac:AccountingCustomerParty/cac:Party', $text),
        'taxes' => $taxes,
        'withholdings' => $withholdings,
        'allowances' => $allowances,
        'totals' => array(
            'line_extension' => erp_ubl_kurus($text($monetary . 'cbc:LineExtensionAmount')),
            'tax_exclusive' => erp_ubl_kurus($text($monetary . 'cbc:TaxExclusiveAmount')),
            'tax_inclusive' => erp_ubl_kurus($text($monetary . 'cbc:TaxInclusiveAmount')),
            'allowance' => erp_ubl_kurus($text($monetary . 'cbc:AllowanceTotalAmount')),
            'charge' => erp_ubl_kurus($text($monetary . 'cbc:ChargeTotalAmount')),
            'rounding' => erp_ubl_kurus($text($monetary . 'cbc:PayableRoundingAmount')),
            'payable' => erp_ubl_kurus($text($monetary . 'cbc:PayableAmount')),
            'tax' => $tax_total,
            'withholding' => $withholding_total,
        ),
        'lines' => $lines,
    );

    if (($document['number'] === '') || ($document['uuid'] === '')) {
        return $fail(lang('The document has no number or no ETTN.'));
    }

    return array('success' => true, 'error' => '', 'document' => $document);
}

/**
 * One party: the supplier or the customer.
 *
 * @param DOMXPath $xp
 * @param string   $path
 * @param callable $text
 * @return array  title, is_person, first_name, last_name, tax_number,
 *                tax_scheme ('VKN' | 'TCKN' | ''), tax_office, address,
 *                district, city, postcode, country_code, country, email,
 *                phone, website
 */
function erp_ubl_party($xp, $path, $text)
{
    $nodes = $xp->query($path, $xp->document->documentElement);
    $party = ($nodes && ($nodes->length > 0)) ? $nodes->item(0) : null;
    $empty = array('title' => '', 'is_person' => false, 'first_name' => '', 'last_name' => '', 'tax_number' => '',
        'tax_scheme' => '', 'tax_office' => '', 'address' => '', 'district' => '', 'city' => '', 'postcode' => '',
        'country_code' => '', 'country' => '', 'email' => '', 'phone' => '', 'website' => '');

    if ($party === null) {
        return $empty;
    }

    $tax_number = '';
    $scheme = '';

    foreach ($xp->query('cac:PartyIdentification/cbc:ID', $party) as $node) {
        $id_scheme = strtoupper(trim((string) $node->getAttribute('schemeID')));

        if (in_array($id_scheme, array('VKN', 'TCKN'), true)) {
            $tax_number = preg_replace('/\D/', '', (string) $node->textContent);
            $scheme = $id_scheme;
            break;
        }
    }

    $first = trim($text('cac:Person/cbc:FirstName', $party) . ' ' . $text('cac:Person/cbc:MiddleName', $party));
    $last = $text('cac:Person/cbc:FamilyName', $party);
    $title = $text('cac:PartyName/cbc:Name', $party);

    if ($title === '') {
        $title = trim($first . ' ' . $last);
    }

    $address = array();
    foreach (array('cbc:Room', 'cbc:StreetName', 'cbc:BuildingName', 'cbc:BuildingNumber', 'cbc:BlockName', 'cbc:District') as $part) {
        $value = $text('cac:PostalAddress/' . $part, $party);
        if ($value !== '') {
            $address[] = $value;
        }
    }

    $country_code = strtoupper($text('cac:PostalAddress/cac:Country/cbc:IdentificationCode', $party));
    $country = $text('cac:PostalAddress/cac:Country/cbc:Name', $party);

    // A missing code with the country spelt out is still Turkey.
    if (($country_code === '') && in_array(mb_strtolower($country, 'UTF-8'), array('türkiye', 'turkiye', 'turkey'), true)) {
        $country_code = 'TR';
    }

    return array(
        'title' => $title,
        'is_person' => ($scheme === 'TCKN') || (($scheme === '') && (strlen($tax_number) === 11)),
        'first_name' => $first,
        'last_name' => $last,
        'tax_number' => $tax_number,
        'tax_scheme' => $scheme,
        'tax_office' => $text('cac:PartyTaxScheme/cac:TaxScheme/cbc:Name', $party),
        'address' => implode(' ', $address),
        'district' => $text('cac:PostalAddress/cbc:CitySubdivisionName', $party),
        'city' => $text('cac:PostalAddress/cbc:CityName', $party),
        'postcode' => $text('cac:PostalAddress/cbc:PostalZone', $party),
        'country_code' => $country_code,
        'country' => $country,
        'email' => $text('cac:Contact/cbc:ElectronicMail', $party),
        'phone' => $text('cac:Contact/cbc:Telephone', $party),
        'website' => $text('cbc:WebsiteURI', $party),
    );
}

/**
 * The tax subtotals under a TaxTotal or a WithholdingTaxTotal.
 *
 * @param DOMXPath $xp
 * @param DOMNode  $context
 * @param string   $total_element  'cac:TaxTotal' | 'cac:WithholdingTaxTotal'
 * @param callable $text
 * @return array  Each: code, name, rate, base, amount, exemption_code, exemption_reason
 */
function erp_ubl_taxes($xp, $context, $total_element, $text)
{
    $taxes = array();

    foreach ($xp->query($total_element . '/cac:TaxSubtotal', $context) as $node) {
        $taxes[] = array(
            'code' => $text('cac:TaxCategory/cac:TaxScheme/cbc:TaxTypeCode', $node),
            'name' => $text('cac:TaxCategory/cac:TaxScheme/cbc:Name', $node),
            'rate' => (float) $text('cbc:Percent', $node),
            'base' => erp_ubl_kurus($text('cbc:TaxableAmount', $node)),
            'amount' => erp_ubl_kurus($text('cbc:TaxAmount', $node)),
            'exemption_code' => $text('cac:TaxCategory/cbc:TaxExemptionReasonCode', $node),
            'exemption_reason' => $text('cac:TaxCategory/cbc:TaxExemptionReason', $node),
        );
    }

    return $taxes;
}

/**
 * One AllowanceCharge element.
 *
 * @param DOMXPath $xp
 * @param DOMNode  $node
 * @param callable $text
 * @return array  charge (bool: true a charge, false a discount), amount, base,
 *                rate (a percentage, 0 when none was written), reason
 */
function erp_ubl_allowance($xp, $node, $text)
{
    $factor = $text('cbc:MultiplierFactorNumeric', $node);

    return array(
        'charge' => (strtolower($text('cbc:ChargeIndicator', $node)) === 'true'),
        'amount' => erp_ubl_kurus($text('cbc:Amount', $node)),
        'base' => erp_ubl_kurus($text('cbc:BaseAmount', $node)),
        'rate' => ($factor === '') ? 0.0 : round(((float) $factor) * 100, 3),
        'reason' => $text('cbc:AllowanceChargeReason', $node),
    );
}
