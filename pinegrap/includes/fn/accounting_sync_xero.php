<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Xero's driver for the accounting sync (pg_accounting_sync_dispatch()):
 * each queued row of the table accounting_sync becomes what Xero keeps for
 * it in the organisation the site books into.
 *
 * - A sale is an AUTHORISED sales invoice (ACCREC) on the buyer's contact,
 *   its lines on the income account with the tax rate matched on Xero's
 *   Connections page and the site's own tax amount.
 * - A payment is a Xero payment of that invoice into the clearing account
 *   (online payments) or the bank account (offline, transfer, cash).
 * - A refund is an AUTHORISED credit note (ACCRECCREDIT). Without the ERP
 *   module it is paid out at once, unless the refund was store credit or a
 *   gift card; with it, the receipt that pays the return pays it out.
 * - A cancelled ERP invoice, return or receipt voids what was sent: the
 *   invoice or credit note VOIDED, the payment DELETED.
 *
 * The buyer is a Xero contact found by its pairing, else by e-mail, else
 * made; a product is an item not tracked as stock, found by its pairing,
 * else by its code (the product's SKU), else made. Both pairings are kept.
 *
 * Every create and update carries the row's Idempotency-Key
 * (pg_accounting_sync_key()), so a retried call does not make a second
 * record. A call answered with few requests left in the minute holds the
 * next ones (pg_accounting_sync_xero_pause()).
 *
 * The pure parts are tested in tests/accounting_sync_xero_test.php.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/xero.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/accounting_sync.php');

// How long a row waits that is not ready yet (its sale not sent, a rate not
// matched, an account not chosen), in seconds.
define('PG_ACCOUNTING_SYNC_XERO_NOT_READY', 900);

/* ---------------------------------------------------------------------------
   Pure parts
   --------------------------------------------------------------------------- */

/**
 * An amount in minor units as Xero takes it: a decimal with two places.
 * Pure.
 *
 * @param int $minor
 * @return float
 */
function pg_accounting_sync_xero_amount($minor)
{
    return round(((int) $minor) / 100, 2);
}

/**
 * A quantity as Xero takes it, up to four places. Pure.
 *
 * @param mixed $quantity
 * @return float|int
 */
function pg_accounting_sync_xero_quantity($quantity)
{
    $quantity = round((float) $quantity, 4);

    return (floor($quantity) == $quantity) ? (int) $quantity : $quantity;
}

/**
 * Which chosen account a payment goes to: the bank account for money that
 * comes in by transfer, in cash or on delivery, the clearing account for a
 * payment service or a card, which pays out later. Pure.
 *
 * @param string $method orders.payment_method or erp_cash_transactions.payment_method
 * @return string bank or clearing
 */
function pg_accounting_sync_xero_payment_role($method)
{
    return in_array((string) $method, array('Offline Payment', 'cash', 'transfer'), true) ? 'bank' : 'clearing';
}

/**
 * Whether a refund recorded without the ERP module is money paid back. A
 * refund given as store credit or onto a gift card stays as credit on the
 * buyer's contact. Pure.
 *
 * @param string $refund_type order_refunds.refund_type
 * @return bool
 */
function pg_accounting_sync_xero_refund_paid_out($refund_type)
{
    $type = (string) $refund_type;

    return (strpos($type, 'credit') === false) && (strpos($type, 'gift_card') === false);
}

/**
 * A buyer as a Xero contact. The name is the company, else the person, else
 * the e-mail; ContactNumber names the site's record, for the books' own
 * search. Pure.
 *
 * @param array $buyer number, company, first_name, last_name, email
 * @return array a Contact
 */
function pg_accounting_sync_xero_contact($buyer)
{
    $first = trim((string) ($buyer['first_name'] ?? ''));
    $last = trim((string) ($buyer['last_name'] ?? ''));
    $email = trim((string) ($buyer['email'] ?? ''));
    $email = (filter_var($email, FILTER_VALIDATE_EMAIL) !== false) ? $email : '';
    $name = trim((string) ($buyer['company'] ?? ''));

    if ($name === '') {
        $name = trim($first . ' ' . $last);
    }

    if ($name === '') {
        $name = ($email !== '') ? $email : 'Customer';
    }

    $contact = array('Name' => mb_substr($name, 0, 255));

    foreach (array('FirstName' => $first, 'LastName' => $last) as $key => $value) {
        if ($value !== '') {
            $contact[$key] = mb_substr($value, 0, 255);
        }
    }

    if ($email !== '') {
        $contact['EmailAddress'] = mb_substr($email, 0, 255);
    }

    if ((string) ($buyer['number'] ?? '') !== '') {
        $contact['ContactNumber'] = mb_substr((string) $buyer['number'], 0, 50);
    }

    return $contact;
}

/**
 * The where filter that finds a contact by e-mail, '' when the address
 * cannot be put into one. Pure.
 *
 * @param string $email
 * @return string
 */
function pg_accounting_sync_xero_email_where($email)
{
    $email = trim((string) $email);

    if ((filter_var($email, FILTER_VALIDATE_EMAIL) === false) || (strpbrk($email, '"\\') !== false)) {
        return '';
    }

    return 'EmailAddress=="' . $email . '"';
}

/**
 * A product's item code in Xero: its SKU when it fits Xero's 30 characters,
 * else one made from its id. Pure.
 *
 * @param string $sku        products.name
 * @param int    $product_id
 * @return string
 */
function pg_accounting_sync_xero_item_code($sku, $product_id)
{
    $sku = trim((string) $sku);

    if (($sku !== '') && (mb_strlen($sku) <= 30) && (strpbrk($sku, '"\\') === false)) {
        return $sku;
    }

    return 'pg-' . (int) $product_id;
}

/**
 * A product as a Xero item: sold, not tracked as stock (no inventory
 * account), as the site keeps its stock. Pure.
 *
 * @param string $code
 * @param string $name
 * @return array an Item
 */
function pg_accounting_sync_xero_item($code, $name)
{
    $name = trim((string) $name);

    return array(
        'Code'        => (string) $code,
        'Name'        => mb_substr(($name !== '') ? $name : (string) $code, 0, 50),
        'IsSold'      => true,
        'IsPurchased' => false,
    );
}

/**
 * The tax rates a document's lines need matched that are not:
 * pg_accounting_sync_tax_local_id() ids, smallest first. Pure.
 *
 * @param array $lines      tax_rate
 * @param array $tax_types  tax rate id => Xero TaxType
 * @return int[]
 */
function pg_accounting_sync_xero_unmatched(array $lines, array $tax_types)
{
    $missing = array();

    foreach ($lines as $line) {
        $id = pg_accounting_sync_tax_local_id($line['tax_rate'] ?? 0);

        if (!isset($tax_types[$id]) || ((string) $tax_types[$id] === '')) {
            $missing[$id] = $id;
        }
    }

    sort($missing);

    return array_values($missing);
}

/**
 * A document's line as Xero takes it, amounts without tax, the tax the site
 * charged given as it is. Pure.
 *
 * @param array  $line       description, quantity, unit_price, discount_amount, tax_rate,
 *                           tax_total (erp_order_lines() or erp_invoice_items shape)
 * @param array  $tax_types  tax rate id => Xero TaxType
 * @param string $account_id the income account
 * @param string $item_code  the item, '' for a line without a product
 * @return array a LineItem
 */
function pg_accounting_sync_xero_line(array $line, array $tax_types, $account_id, $item_code)
{
    $description = trim((string) ($line['description'] ?? ''));
    $item = array(
        'Description' => mb_substr(($description !== '') ? $description : '-', 0, 4000),
        'Quantity'    => pg_accounting_sync_xero_quantity($line['quantity'] ?? 1),
        'UnitAmount'  => pg_accounting_sync_xero_amount($line['unit_price'] ?? 0),
        'AccountID'   => (string) $account_id,
        'TaxType'     => (string) ($tax_types[pg_accounting_sync_tax_local_id($line['tax_rate'] ?? 0)] ?? ''),
        'TaxAmount'   => pg_accounting_sync_xero_amount($line['tax_total'] ?? 0),
    );

    if ((int) ($line['discount_amount'] ?? 0) > 0) {
        $item['DiscountAmount'] = pg_accounting_sync_xero_amount($line['discount_amount']);
    }

    if ((string) $item_code !== '') {
        $item['ItemCode'] = (string) $item_code;
    }

    return $item;
}

/**
 * A refund recorded without lines (order_refunds) shared over the sale's
 * tax rates in proportion to what each rate came to, largest remainder
 * first, so the shares add up to the refund. Pure.
 *
 * @param int   $amount refund, minor units, tax included
 * @param array $lines  the sale's lines: line_total, discount_amount, tax_total, tax_rate
 * @return array tax rate id => amount, tax included
 */
function pg_accounting_sync_xero_refund_shares($amount, array $lines)
{
    $amount = max(0, (int) $amount);
    $totals = array();

    foreach ($lines as $line) {
        $id = pg_accounting_sync_tax_local_id($line['tax_rate'] ?? 0);
        $gross = (int) ($line['line_total'] ?? 0) - (int) ($line['discount_amount'] ?? 0) + (int) ($line['tax_total'] ?? 0);
        $totals[$id] = ($totals[$id] ?? 0) + max(0, $gross);
    }

    $sum = array_sum($totals);

    if (($sum <= 0) || ($amount === 0)) {
        reset($totals);

        return ($amount > 0) ? array((int) (key($totals) ?? 0) => $amount) : array();
    }

    $shares = array();
    $remainders = array();

    foreach ($totals as $id => $total) {
        $exact = $amount * $total / $sum;
        $shares[$id] = (int) floor($exact);
        $remainders[$id] = $exact - $shares[$id];
    }

    arsort($remainders);

    foreach (array_keys($remainders) as $id) {
        if (array_sum($shares) >= $amount) {
            break;
        }

        $shares[$id]++;
    }

    return array_filter($shares);
}

/**
 * The tax inside an amount that includes it, at a rate. Pure.
 *
 * @param int $gross    minor units
 * @param int $local_id the rate in thousandths of a percent
 * @return int
 */
function pg_accounting_sync_xero_tax_inside($gross, $local_id)
{
    $local_id = max(0, (int) $local_id);

    if ($local_id === 0) {
        return 0;
    }

    return (int) round(((int) $gross) * $local_id / (100000 + $local_id));
}

/**
 * A sales invoice or credit note. Pure.
 *
 * @param array $document type (ACCREC or ACCRECCREDIT), contact_id, date, due_date,
 *                        currency, reference, number, lines, inclusive
 * @return array an Invoice or a CreditNote
 */
function pg_accounting_sync_xero_document(array $document)
{
    $body = array(
        'Type'            => (string) $document['type'],
        'Contact'         => array('ContactID' => (string) $document['contact_id']),
        'Date'            => (string) $document['date'],
        'LineAmountTypes' => !empty($document['inclusive']) ? 'Inclusive' : 'Exclusive',
        'LineItems'       => array_values($document['lines']),
        'Status'          => 'AUTHORISED',
    );

    if ((string) ($document['currency'] ?? '') !== '') {
        $body['CurrencyCode'] = (string) $document['currency'];
    }

    if ($document['type'] === 'ACCREC') {
        $body['DueDate'] = (string) (($document['due_date'] ?? '') !== '' ? $document['due_date'] : $document['date']);
    }

    if ((string) ($document['number'] ?? '') !== '') {
        $body[($document['type'] === 'ACCREC') ? 'InvoiceNumber' : 'CreditNoteNumber'] = mb_substr((string) $document['number'], 0, 255);
    }

    // Reference is kept for sales invoices alone.
    if (($document['type'] === 'ACCREC') && ((string) ($document['reference'] ?? '') !== '')) {
        $body['Reference'] = mb_substr((string) $document['reference'], 0, 255);
    }

    return $body;
}

/**
 * A payment of an invoice or a paid out credit note. Pure.
 *
 * @param string $kind       Invoice or CreditNote
 * @param string $remote_id  the InvoiceID or CreditNoteID
 * @param string $account_id
 * @param string $date       Y-m-d
 * @param int    $amount     minor units
 * @param string $reference
 * @return array a Payment
 */
function pg_accounting_sync_xero_payment($kind, $remote_id, $account_id, $date, $amount, $reference)
{
    $key = ($kind === 'CreditNote') ? 'CreditNoteID' : 'InvoiceID';
    $payment = array(
        $kind     => array($key => (string) $remote_id),
        'Account' => array('AccountID' => (string) $account_id),
        'Date'    => (string) $date,
        'Amount'  => pg_accounting_sync_xero_amount($amount),
    );

    if ((string) $reference !== '') {
        $payment['Reference'] = mb_substr((string) $reference, 0, 255);
    }

    return $payment;
}

/**
 * The id of the first record of an answer, '' when it has none or the
 * record carries validation errors. Pure.
 *
 * @param mixed  $data       the decoded answer
 * @param string $collection Invoices, CreditNotes, Payments, Contacts, Items
 * @param string $id_key     InvoiceID and the like
 * @return string
 */
function pg_accounting_sync_xero_first_id($data, $collection, $id_key)
{
    $record = (is_array($data) && isset($data[$collection][0]) && is_array($data[$collection][0])) ? $data[$collection][0] : null;

    if (($record === null) || !empty($record['HasErrors']) || !empty($record['ValidationErrors'])) {
        return '';
    }

    $id = isset($record[$id_key]) && is_string($record[$id_key]) ? strtolower($record[$id_key]) : '';

    return xero_uuid_valid($id) ? $id : '';
}

/**
 * Seconds to hold the next call after an answer's limit headers: a minute
 * when the organisation or the app has two calls left in the minute, an
 * hour when the organisation has ten left in the day, 0 otherwise. Pure.
 *
 * @param array $headers lower-case name => value
 * @return int
 */
function pg_accounting_sync_xero_pause(array $headers)
{
    $left = function ($name) use ($headers) {
        return (isset($headers[$name]) && is_numeric($headers[$name])) ? (int) $headers[$name] : null;
    };

    $day = $left('x-daylimit-remaining');

    if (($day !== null) && ($day <= 10)) {
        return 3600;
    }

    foreach (array('x-minlimit-remaining', 'x-appminlimit-remaining') as $name) {
        $minute = $left($name);

        if (($minute !== null) && ($minute <= 2)) {
            return 60;
        }
    }

    return 0;
}

/**
 * A failed call as the driver's answer (pg_accounting_sync_after_attempt()):
 * its status and Xero's words, and on a 429 the Retry-After. Pure.
 *
 * @param array $call status, error, headers
 * @return array
 */
function pg_accounting_sync_xero_failure(array $call)
{
    $status = (int) ($call['status'] ?? 0);
    $outcome = array('ok' => false, 'status' => $status, 'error' => (string) ($call['error'] ?? ''));

    if ($status === 429) {
        $after = $call['headers']['retry-after'] ?? '';
        $outcome['retry_after'] = is_numeric($after) ? max(60, (int) $after) : 60;
    }

    return $outcome;
}

/**
 * A row that is not ready yet, with the reason. Pure.
 *
 * @param string $reason
 * @return array
 */
function pg_accounting_sync_xero_not_ready($reason)
{
    return array('ok' => false, 'wait' => PG_ACCOUNTING_SYNC_XERO_NOT_READY, 'error' => (string) $reason);
}

/**
 * A refusal no try will change. Pure.
 *
 * @param string $reason
 * @return array
 */
function pg_accounting_sync_xero_refused($reason)
{
    return array('ok' => false, 'status' => 422, 'final' => true, 'error' => (string) $reason);
}

/**
 * A unix time or a date as Xero's date, today for none. Pure.
 *
 * @param mixed $value
 * @return string Y-m-d
 */
function pg_accounting_sync_xero_date($value)
{
    if (is_numeric($value) && ((int) $value > 0)) {
        return date('Y-m-d', (int) $value);
    }

    $value = substr(trim((string) $value), 0, 10);

    return ((preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1) && ($value !== '0000-00-00')) ? $value : date('Y-m-d');
}

/* ---------------------------------------------------------------------------
   Calls
   --------------------------------------------------------------------------- */

/**
 * The time before which no call is made, after an answer with few calls
 * left; read and moved within one run of the general job.
 *
 * @param int|null $until a new time, null to read
 * @return int
 */
function pg_accounting_sync_xero_hold($until = null)
{
    static $held = 0;

    if ($until !== null) {
        $held = max($held, (int) $until);
    }

    return $held;
}

/**
 * One accounting call for the organisation, with a JSON body and the
 * Idempotency-Key on a write. Reads the limit headers of the answer.
 *
 * @param array       $context connection_id, tenant_id
 * @param string      $method
 * @param string      $path    after /api.xro/2.0
 * @param array|null  $body
 * @param string      $key     Idempotency-Key, '' for none
 * @return array ok, status, data, error, headers
 */
function pg_accounting_sync_xero_call(array $context, $method, $path, $body = null, $key = '')
{
    $held = pg_accounting_sync_xero_hold() - time();

    if ($held > 0) {
        return array('ok' => false, 'status' => 429, 'data' => null, 'error' => lang('Xero\'s call limit is nearly reached.'),
            'headers' => array('retry-after' => (string) $held));
    }

    $options = array(
        'headers'          => array('Accept: application/json', 'xero-tenant-id: ' . $context['tenant_id']),
        'timeout'          => 30,
        'max_body'         => 4194304,
        'response_headers' => true,
    );

    if ($body !== null) {
        $options['headers'][] = 'Content-Type: application/json';
        $options['body'] = json_encode($body);
    }

    if ($key !== '') {
        $options['headers'][] = 'Idempotency-Key: ' . $key;
    }

    $result = pg_connect_api((int) $context['connection_id'], $method, PG_XERO_API . $path, $options);
    $headers = (isset($result['headers']) && is_array($result['headers'])) ? $result['headers'] : array();
    $pause = pg_accounting_sync_xero_pause($headers);

    if ($pause > 0) {
        pg_accounting_sync_xero_hold(time() + $pause);
    }

    return array(
        'ok'      => !empty($result['ok']),
        'status'  => (int) $result['status'],
        'data'    => $result['data'],
        'error'   => !empty($result['ok']) ? '' : xero_error_message((int) $result['status'], $result['data'], (string) $result['error']),
        'headers' => $headers,
    );
}

/**
 * Makes one record and answers its id.
 *
 * @param array  $context
 * @param string $collection Invoices, CreditNotes, Payments, Contacts, Items
 * @param string $id_key
 * @param array  $record
 * @param string $key        Idempotency-Key
 * @return array ok, id, outcome (the failure)
 */
function pg_accounting_sync_xero_create(array $context, $collection, $id_key, array $record, $key)
{
    $call = pg_accounting_sync_xero_call($context, 'PUT', '/' . $collection, array($collection => array($record)), $key);

    if (!$call['ok']) {
        return array('ok' => false, 'id' => '', 'outcome' => pg_accounting_sync_xero_failure($call));
    }

    $id = pg_accounting_sync_xero_first_id($call['data'], $collection, $id_key);

    if ($id === '') {
        return array('ok' => false, 'id' => '', 'outcome' => array('ok' => false, 'status' => 400,
            'error' => xero_error_message(400, array('Elements' => $call['data'][$collection] ?? array()), lang('Xero gave no id.'))));
    }

    return array('ok' => true, 'id' => $id, 'outcome' => array());
}

/**
 * Voids a sales invoice or credit note, or deletes a payment.
 *
 * @param array  $context
 * @param string $collection Invoices, CreditNotes, Payments
 * @param string $remote_id
 * @param string $key
 * @return array the driver's answer
 */
function pg_accounting_sync_xero_void(array $context, $collection, $remote_id, $key)
{
    if ($collection === 'Payments') {
        $body = array('Status' => 'DELETED');
    } else {
        $id_key = ($collection === 'Invoices') ? 'InvoiceID' : 'CreditNoteID';
        $body = array($collection => array(array($id_key => (string) $remote_id, 'Status' => 'VOIDED')));
    }

    $call = pg_accounting_sync_xero_call($context, 'POST', '/' . $collection . '/' . rawurlencode((string) $remote_id), $body, $key);

    return $call['ok'] ? array('ok' => true, 'remote_id' => (string) $remote_id) : pg_accounting_sync_xero_failure($call);
}

/* ---------------------------------------------------------------------------
   Contacts, items, tax rates
   --------------------------------------------------------------------------- */

/**
 * The buyer's contact: its pairing, else the contact with its e-mail, else
 * one made. A found or made contact is paired when the buyer is a record.
 *
 * @param array $context target, connection_id, tenant_id
 * @param array $buyer   local_table, local_id (0 for a guest), and
 *                       pg_accounting_sync_xero_contact()'s fields
 * @param array $row     the queued row, for the key
 * @return array ok, id, outcome
 */
function pg_accounting_sync_xero_contact_for(array $context, array $buyer, array $row)
{
    $local_id = (int) ($buyer['local_id'] ?? 0);

    if ($local_id > 0) {
        $pair = pg_accounting_sync_pair($context['target'], 'customer', $buyer['local_table'], $local_id);

        if (is_array($pair) && xero_uuid_valid($pair['remote_id'])) {
            return array('ok' => true, 'id' => $pair['remote_id'], 'outcome' => array());
        }
    }

    $id = '';
    $where = pg_accounting_sync_xero_email_where($buyer['email'] ?? '');

    if ($where !== '') {
        $call = pg_accounting_sync_xero_call($context, 'GET', '/Contacts?summaryOnly=true&where=' . rawurlencode($where));

        if (!$call['ok']) {
            return array('ok' => false, 'id' => '', 'outcome' => pg_accounting_sync_xero_failure($call));
        }

        $id = pg_accounting_sync_xero_first_id($call['data'], 'Contacts', 'ContactID');
    }

    if ($id === '') {
        $made = pg_accounting_sync_xero_create($context, 'Contacts', 'ContactID', pg_accounting_sync_xero_contact($buyer),
            pg_accounting_sync_key($row, 'contact'));

        if (!$made['ok']) {
            return $made;
        }

        $id = $made['id'];
    }

    if ($local_id > 0) {
        pg_accounting_sync_pair_save($context['target'], 'customer', $buyer['local_table'], $local_id, $id);
    }

    return array('ok' => true, 'id' => $id, 'outcome' => array());
}

/**
 * The item codes of a document's lines (product id => code): each product
 * by its pairing, else the item with its code, else one made, and paired.
 *
 * @param array $context
 * @param array $lines   product_id, description
 * @param array $row
 * @return array ok, codes, outcome
 */
function pg_accounting_sync_xero_items_for(array $context, array $lines, array $row)
{
    $codes = array();

    foreach ($lines as $line) {
        $product_id = (int) ($line['product_id'] ?? 0);

        if (($product_id <= 0) || isset($codes[$product_id])) {
            continue;
        }

        $pair = pg_accounting_sync_pair($context['target'], 'item', 'products', $product_id);

        if (is_array($pair) && ((string) $pair['sync_token'] !== '')) {
            $codes[$product_id] = (string) $pair['sync_token'];
            continue;
        }

        $product = db_item("SELECT name, short_description FROM products WHERE id = '" . $product_id . "' LIMIT 1");

        if (!is_array($product)) {
            // A product deleted since: the line goes without an item.
            $codes[$product_id] = '';
            continue;
        }

        $code = pg_accounting_sync_xero_item_code($product['name'], $product_id);
        $call = pg_accounting_sync_xero_call($context, 'GET', '/Items?where=' . rawurlencode('Code=="' . $code . '"'));

        if (!$call['ok']) {
            return array('ok' => false, 'codes' => array(), 'outcome' => pg_accounting_sync_xero_failure($call));
        }

        $id = pg_accounting_sync_xero_first_id($call['data'], 'Items', 'ItemID');

        if ($id === '') {
            $name = trim((string) $product['short_description']) !== '' ? $product['short_description'] : ($line['description'] ?? $code);
            $made = pg_accounting_sync_xero_create($context, 'Items', 'ItemID', pg_accounting_sync_xero_item($code, $name),
                pg_accounting_sync_key($row, 'item-' . $product_id));

            if (!$made['ok']) {
                return array('ok' => false, 'codes' => array(), 'outcome' => $made['outcome']);
            }

            $id = $made['id'];
        }

        // The pairing keeps the item's id and, as its token, the code the
        // lines name it by.
        pg_accounting_sync_pair_save($context['target'], 'item', 'products', $product_id, $id, $code);
        $codes[$product_id] = $code;
    }

    return array('ok' => true, 'codes' => $codes, 'outcome' => array());
}

/**
 * The TaxType matched to each rate of a document's lines.
 *
 * @param array $target
 * @param array $lines tax_rate
 * @return array tax rate id => TaxType, matched ones only
 */
function pg_accounting_sync_xero_tax_types(array $target, array $lines)
{
    $types = array();

    foreach ($lines as $line) {
        $id = pg_accounting_sync_tax_local_id($line['tax_rate'] ?? 0);

        if (!isset($types[$id])) {
            $pair = pg_accounting_sync_pair($target, 'tax', 'tax_rate', $id);
            $types[$id] = is_array($pair) ? (string) $pair['remote_id'] : '';
        }
    }

    return array_filter($types, 'strlen');
}

/* ---------------------------------------------------------------------------
   Reading the site's records
   --------------------------------------------------------------------------- */

/**
 * An order and its lines as its invoice carries them (erp_order_lines(),
 * as Paraşüt's order invoice), null when the order is gone.
 *
 * @param int $order_id
 * @return array|null order, lines, totals, expected
 */
function pg_accounting_sync_xero_order($order_id)
{
    $order = db_item("SELECT * FROM orders WHERE id = '" . (int) $order_id . "' LIMIT 1");

    if (!is_array($order)) {
        return null;
    }

    require_once(PG_FUNCTIONS_DIR . '/includes/erp/bootstrap.php');

    $items = (array) db_items("SELECT order_items.*, products.short_description,
            products.tax_rate AS product_tax_rate, products.start AS product_start
        FROM order_items
        LEFT JOIN products ON order_items.product_id = products.id
        WHERE order_items.order_id = '" . (int) $order['id'] . "' AND order_items.saved_for_later = 0
        ORDER BY order_items.id ASC");

    $charged = erp_order_charged_items($order, $items);
    $built = $charged ? erp_order_lines($order, $charged, null, true)
        : array('lines' => array(), 'totals' => array('grand_total' => 0, 'charges_left' => 0));

    return array(
        'order'    => $order,
        'lines'    => $built['lines'],
        'totals'   => $built['totals'],
        'expected' => (int) ($order['total'] ?? 0) + (int) ($order['gift_card_discount'] ?? 0) - (int) $built['totals']['charges_left'],
    );
}

/**
 * The buyer of an order: the site's contact when it has one, else a guest
 * found by e-mail.
 *
 * @param array $order
 * @return array
 */
function pg_accounting_sync_xero_order_buyer(array $order)
{
    $contact_id = (int) ($order['contact_id'] ?? 0);

    return array(
        'local_table' => 'contacts',
        'local_id'    => $contact_id,
        'number'      => ($contact_id > 0) ? 'pg-contact-' . $contact_id : '',
        'company'     => (string) ($order['billing_company'] ?? ''),
        'first_name'  => (string) ($order['billing_first_name'] ?? ''),
        'last_name'   => (string) ($order['billing_last_name'] ?? ''),
        'email'       => (string) ($order['billing_email_address'] ?? ''),
    );
}

/**
 * An ERP invoice and its lines, null when it is gone.
 *
 * @param int $invoice_id
 * @return array|null invoice, lines
 */
function pg_accounting_sync_xero_erp_invoice($invoice_id)
{
    $invoice = db_item("SELECT * FROM erp_invoices WHERE id = '" . (int) $invoice_id . "' LIMIT 1");

    if (!is_array($invoice)) {
        return null;
    }

    $lines = (array) db_items("SELECT * FROM erp_invoice_items WHERE invoice_id = '" . (int) $invoice_id . "' ORDER BY line_no, id");

    return array('invoice' => $invoice, 'lines' => $lines);
}

/**
 * The buyer of an ERP invoice: its account, by the details the invoice was
 * issued with.
 *
 * @param array $invoice
 * @return array
 */
function pg_accounting_sync_xero_erp_buyer(array $invoice)
{
    $account_id = (int) ($invoice['account_id'] ?? 0);
    $account = ($account_id > 0) ? db_item("SELECT title, email FROM erp_accounts WHERE id = '" . $account_id . "' LIMIT 1") : null;
    $title = trim((string) ($invoice['account_title'] ?? ''));
    $email = trim((string) ($invoice['account_email'] ?? ''));

    return array(
        'local_table' => 'erp_accounts',
        'local_id'    => $account_id,
        'number'      => ($account_id > 0) ? 'pg-account-' . $account_id : '',
        'company'     => ($title !== '') ? $title : (is_array($account) ? (string) $account['title'] : ''),
        'email'       => ($email !== '') ? $email : (is_array($account) ? (string) $account['email'] : ''),
    );
}

/* ---------------------------------------------------------------------------
   The records sent
   --------------------------------------------------------------------------- */

/**
 * Sends a sales invoice or a credit note built from lines.
 *
 * @param array  $context
 * @param array  $row
 * @param string $type     ACCREC or ACCRECCREDIT
 * @param array  $buyer
 * @param array  $lines    erp_order_lines() or erp_invoice_items rows
 * @param array  $document date, due_date, currency, reference, number
 * @return array the driver's answer
 */
function pg_accounting_sync_xero_send_document(array $context, array $row, $type, array $buyer, array $lines, array $document)
{
    if (!$lines) {
        return pg_accounting_sync_xero_refused(lang('The record has no lines to send.'));
    }

    $tax_types = pg_accounting_sync_xero_tax_types($context['target'], $lines);
    $missing = pg_accounting_sync_xero_unmatched($lines, $tax_types);

    if ($missing) {
        return pg_accounting_sync_xero_not_ready(lang(array(
            'string' => 'Not matched to a Xero tax rate on the Connections page: {var:1}',
            'vars'   => implode(', ', array_map('pg_accounting_sync_tax_label', $missing)),
        )));
    }

    $contact = pg_accounting_sync_xero_contact_for($context, $buyer, $row);

    if (!$contact['ok']) {
        return $contact['outcome'];
    }

    $items = pg_accounting_sync_xero_items_for($context, $lines, $row);

    if (!$items['ok']) {
        return $items['outcome'];
    }

    $xero_lines = array();

    foreach ($lines as $line) {
        $xero_lines[] = pg_accounting_sync_xero_line($line, $tax_types, $context['accounts']['income']['id'],
            $items['codes'][(int) ($line['product_id'] ?? 0)] ?? '');
    }

    $collection = ($type === 'ACCREC') ? 'Invoices' : 'CreditNotes';
    $made = pg_accounting_sync_xero_create($context, $collection, ($type === 'ACCREC') ? 'InvoiceID' : 'CreditNoteID',
        pg_accounting_sync_xero_document($document + array('type' => $type, 'contact_id' => $contact['id'], 'lines' => $xero_lines)),
        pg_accounting_sync_key($row, 'create'));

    return $made['ok'] ? array('ok' => true, 'remote_id' => $made['id']) : $made['outcome'];
}

/**
 * A sale: an order's (ERP off) or an ERP sales invoice's; a cancelled ERP
 * invoice voids the one sent.
 *
 * @param array $context
 * @param array $row
 * @return array
 */
function pg_accounting_sync_xero_sale(array $context, array $row)
{
    if ($row['local_table'] === 'orders') {
        if (xero_uuid_valid($row['remote_id'])) {
            return array('ok' => true);
        }

        $sale = pg_accounting_sync_xero_order($row['local_id']);

        if ($sale === null) {
            return pg_accounting_sync_xero_refused(lang('The order is gone.'));
        }

        if ($sale['totals']['grand_total'] !== $sale['expected']) {
            return pg_accounting_sync_xero_refused(lang(array(
                'string' => 'The invoice total ({var:1}) does not match the order total ({var:2}). The invoice was not created.',
                'vars'   => array(erp_money_out($sale['totals']['grand_total']), erp_money_out($sale['expected'])),
            )));
        }

        $order = $sale['order'];

        return pg_accounting_sync_xero_send_document($context, $row, 'ACCREC', pg_accounting_sync_xero_order_buyer($order), $sale['lines'], array(
            'date'      => pg_accounting_sync_xero_date(!empty($order['paid_at']) ? $order['paid_at'] : ($order['order_date'] ?? 0)),
            'due_date'  => '',
            'currency'  => erp_base_currency(),
            'reference' => (string) $order['order_number'],
            'number'    => '',
        ));
    }

    return pg_accounting_sync_xero_erp_document($context, $row, 'ACCREC');
}

/**
 * An ERP sales invoice (ACCREC) or return invoice (ACCRECCREDIT); a
 * cancelled one voids the one sent, or ends with nothing sent.
 *
 * @param array  $context
 * @param array  $row
 * @param string $type
 * @return array
 */
function pg_accounting_sync_xero_erp_document(array $context, array $row, $type)
{
    $collection = ($type === 'ACCREC') ? 'Invoices' : 'CreditNotes';
    $document = pg_accounting_sync_xero_erp_invoice($row['local_id']);

    if ($document === null) {
        return pg_accounting_sync_xero_refused(lang('The invoice is gone.'));
    }

    $invoice = $document['invoice'];

    if ((string) $invoice['status'] === 'cancelled') {
        return xero_uuid_valid($row['remote_id'])
            ? pg_accounting_sync_xero_void($context, $collection, $row['remote_id'], pg_accounting_sync_key($row, 'void'))
            : array('ok' => true);
    }

    if (xero_uuid_valid($row['remote_id'])) {
        return array('ok' => true);
    }

    if (((int) ($invoice['withholding_total'] ?? 0) !== 0) || ((int) ($invoice['tax2_total'] ?? 0) !== 0)) {
        return pg_accounting_sync_xero_refused(lang('Xero takes no withheld tax and no second tax on a line, so this invoice was not sent.'));
    }

    $reference = '';

    if ((int) $invoice['order_id'] > 0) {
        $reference = (string) db_value("SELECT order_number FROM orders WHERE id = '" . (int) $invoice['order_id'] . "' LIMIT 1");
    }

    return pg_accounting_sync_xero_send_document($context, $row, $type, pg_accounting_sync_xero_erp_buyer($invoice), $document['lines'], array(
        'date'      => pg_accounting_sync_xero_date($invoice['issue_date']),
        'due_date'  => ((string) $invoice['due_date'] !== '0000-00-00') ? pg_accounting_sync_xero_date($invoice['due_date']) : '',
        'currency'  => (string) $invoice['currency'],
        'reference' => $reference,
        'number'    => (string) $invoice['full_number'],
    ));
}

/**
 * A payment: an order's whole sale paid (ERP off), or an ERP receipt of an
 * invoice or a return; a cancelled receipt deletes the one sent.
 *
 * @param array $context
 * @param array $row
 * @return array
 */
function pg_accounting_sync_xero_payment_row(array $context, array $row)
{
    if ($row['local_table'] === 'orders') {
        if (xero_uuid_valid($row['remote_id'])) {
            return array('ok' => true);
        }

        $sale = pg_accounting_sync_pair($context['target'], 'sale', 'orders', $row['local_id']);

        if (!is_array($sale) || !xero_uuid_valid($sale['remote_id'])) {
            return pg_accounting_sync_xero_not_ready(lang('The sale is not in Xero yet.'));
        }

        $order = pg_accounting_sync_xero_order($row['local_id']);

        if ($order === null) {
            return pg_accounting_sync_xero_refused(lang('The order is gone.'));
        }

        $role = pg_accounting_sync_xero_payment_role($order['order']['payment_method'] ?? '');

        if ($context['accounts'][$role]['id'] === '') {
            return pg_accounting_sync_xero_not_ready(lang('The accounts are not chosen on Xero\'s Connections page.'));
        }

        return pg_accounting_sync_xero_pay($context, $row, 'Invoice', $sale['remote_id'], $context['accounts'][$role]['id'],
            pg_accounting_sync_xero_date(!empty($order['order']['paid_at']) ? $order['order']['paid_at'] : 0),
            (int) $order['totals']['grand_total'], (string) $order['order']['order_number'], 'create');
    }

    $receipt = db_item("SELECT * FROM erp_cash_transactions WHERE id = '" . (int) $row['local_id'] . "' LIMIT 1");

    if (!is_array($receipt)) {
        return pg_accounting_sync_xero_refused(lang('The receipt is gone.'));
    }

    $cancelled = (int) db_value("SELECT COUNT(*) FROM erp_cash_transactions WHERE doc_type = 'cancel' AND doc_id = '" . (int) $receipt['id'] . "'") > 0;

    if ($cancelled) {
        return xero_uuid_valid($row['remote_id'])
            ? pg_accounting_sync_xero_void($context, 'Payments', $row['remote_id'], pg_accounting_sync_key($row, 'void'))
            : array('ok' => true);
    }

    if (xero_uuid_valid($row['remote_id'])) {
        return array('ok' => true);
    }

    // The invoice the receipt settled, and how much of it.
    $settled = db_item("SELECT s.invoice_id, s.amount FROM erp_settlements s
        INNER JOIN erp_account_transactions t ON t.id = s.account_txn_id
        WHERE t.doc_type IN ('collection', 'payment') AND t.doc_id = '" . (int) $receipt['id'] . "'
        ORDER BY s.id LIMIT 1");

    if (!is_array($settled) || ((int) $settled['amount'] <= 0)) {
        return pg_accounting_sync_xero_refused(lang('The receipt settles no invoice.'));
    }

    $kind = 'Invoice';
    $paired = pg_accounting_sync_pair($context['target'], 'sale', 'erp_invoices', (int) $settled['invoice_id']);

    if (!is_array($paired)) {
        $kind = 'CreditNote';
        $paired = pg_accounting_sync_pair($context['target'], 'refund', 'erp_invoices', (int) $settled['invoice_id']);
    }

    if (!is_array($paired) || !xero_uuid_valid($paired['remote_id'])) {
        return pg_accounting_sync_xero_not_ready(lang('The invoice is not in Xero yet.'));
    }

    $role = pg_accounting_sync_xero_payment_role($receipt['payment_method']);

    if ($context['accounts'][$role]['id'] === '') {
        return pg_accounting_sync_xero_not_ready(lang('The accounts are not chosen on Xero\'s Connections page.'));
    }

    return pg_accounting_sync_xero_pay($context, $row, $kind, $paired['remote_id'], $context['accounts'][$role]['id'],
        pg_accounting_sync_xero_date($receipt['doc_date']), (int) $settled['amount'], (string) $receipt['description'], 'create');
}

/**
 * Makes a payment and answers with its id.
 *
 * @return array the driver's answer
 */
function pg_accounting_sync_xero_pay(array $context, array $row, $kind, $remote_id, $account_id, $date, $amount, $reference, $operation)
{
    if ((int) $amount <= 0) {
        return array('ok' => true);
    }

    $made = pg_accounting_sync_xero_create($context, 'Payments', 'PaymentID',
        pg_accounting_sync_xero_payment($kind, $remote_id, $account_id, $date, $amount, $reference),
        pg_accounting_sync_key($row, $operation));

    return $made['ok'] ? array('ok' => true, 'remote_id' => $made['id']) : $made['outcome'];
}

/**
 * A refund: a refund of an order (ERP off) as a credit note shared over the
 * sale's tax rates and paid out; an ERP return invoice as a credit note,
 * voided when cancelled.
 *
 * The credit note's id is kept on the row as soon as it is made, and the
 * payout's id as its token, so a retry after a failed payout does not make
 * a second credit note.
 *
 * @param array $context
 * @param array $row
 * @return array
 */
function pg_accounting_sync_xero_refund(array $context, array $row)
{
    if ($row['local_table'] !== 'order_refunds') {
        return pg_accounting_sync_xero_erp_document($context, $row, 'ACCRECCREDIT');
    }

    $refund = db_item("SELECT * FROM order_refunds WHERE id = '" . (int) $row['local_id'] . "' LIMIT 1");

    if (!is_array($refund)) {
        return pg_accounting_sync_xero_refused(lang('The refund is gone.'));
    }

    $order = pg_accounting_sync_xero_order($refund['order_id']);

    if ($order === null) {
        return pg_accounting_sync_xero_refused(lang('The order is gone.'));
    }

    $date = pg_accounting_sync_xero_date($refund['created_at']);
    $credit_note = xero_uuid_valid($row['remote_id']) ? (string) $row['remote_id'] : '';

    if ($credit_note === '') {
        $lines = array();

        foreach (pg_accounting_sync_xero_refund_shares((int) $refund['amount_cents'], $order['lines']) as $rate_id => $gross) {
            $lines[] = array(
                'description' => lang(array('string' => 'Refund of order {var:1}', 'vars' => (string) $order['order']['order_number'])),
                'quantity'    => 1,
                'unit_price'  => $gross,
                'tax_rate'    => $rate_id / 1000,
                'tax_total'   => pg_accounting_sync_xero_tax_inside($gross, $rate_id),
            );
        }

        $sent = pg_accounting_sync_xero_send_document($context, $row, 'ACCRECCREDIT', pg_accounting_sync_xero_order_buyer($order['order']), $lines, array(
            'date'      => $date,
            'currency'  => erp_base_currency(),
            'inclusive' => true,
        ));

        if (empty($sent['ok'])) {
            return $sent;
        }

        $credit_note = (string) $sent['remote_id'];
        pg_accounting_sync_update((int) $row['id'], array('remote_id' => $credit_note));
    }

    if (!pg_accounting_sync_xero_refund_paid_out($refund['refund_type']) || ((string) $row['sync_token'] !== '')) {
        return array('ok' => true, 'remote_id' => $credit_note);
    }

    $role = pg_accounting_sync_xero_payment_role($order['order']['payment_method'] ?? '');

    if ($context['accounts'][$role]['id'] === '') {
        return pg_accounting_sync_xero_not_ready(lang('The accounts are not chosen on Xero\'s Connections page.'));
    }

    $paid = pg_accounting_sync_xero_pay($context, $row, 'CreditNote', $credit_note, $context['accounts'][$role]['id'], $date,
        (int) $refund['amount_cents'], (string) $order['order']['order_number'], 'payout');

    if (empty($paid['ok'])) {
        return $paid;
    }

    return array('ok' => true, 'remote_id' => $credit_note, 'sync_token' => (string) ($paid['remote_id'] ?? ''));
}

/**
 * The driver: one queued row to Xero (pg_accounting_sync_dispatch()).
 *
 * @param array $row    an accounting_sync row
 * @param array $target service, company
 * @return array the answer pg_accounting_sync_after_attempt() reads
 */
function pg_accounting_sync_send_xero(array $row, array $target)
{
    $state = xero_state();
    $connection = xero_connection($state);

    if (!is_array($connection) || ($state['tenant_id'] !== (string) $target['company'])) {
        return array('ok' => false, 'status' => 401, 'error' => lang('Xero is not connected to this organisation.'));
    }

    if ($state['accounts']['income']['id'] === '') {
        return pg_accounting_sync_xero_not_ready(lang('The accounts are not chosen on Xero\'s Connections page.'));
    }

    $context = array(
        'target'        => $target,
        'connection_id' => (int) $connection['id'],
        'tenant_id'     => $state['tenant_id'],
        'accounts'      => $state['accounts'],
    );

    $row['remote_id'] = (string) ($row['remote_id'] ?? '');
    $row['sync_token'] = (string) ($row['sync_token'] ?? '');
    $row['local_id'] = (int) ($row['local_id'] ?? 0);

    require_once(PG_FUNCTIONS_DIR . '/includes/erp/bootstrap.php');

    switch ((string) $row['kind']) {
        case 'sale':
            return pg_accounting_sync_xero_sale($context, $row);
        case 'payment':
            return pg_accounting_sync_xero_payment_row($context, $row);
        case 'refund':
            return pg_accounting_sync_xero_refund($context, $row);
    }

    return pg_accounting_sync_xero_refused(lang('Xero takes no record of this kind.'));
}
