<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * QuickBooks Online's driver for the accounting sync
 * (pg_accounting_sync_dispatch()): each queued row of the table
 * accounting_sync becomes what QuickBooks keeps for it in the company the
 * site books into.
 *
 * - A paid order (ERP module off) is a SalesReceipt, paid at once into the
 *   clearing account (online payments) or the bank account (offline); its
 *   payment row needs no call of its own.
 * - An ERP sales invoice is an Invoice, an ERP receipt of it a Payment.
 * - A refund of an order is a RefundReceipt paid out of the same account, or
 *   a CreditMemo when it was given as store credit or onto a gift card; an
 *   ERP return invoice is a CreditMemo.
 * - A cancelled ERP invoice or receipt voids what was sent; a cancelled
 *   return deletes its CreditMemo.
 *
 * Lines are amounts without tax on an item: the product's own (found by its
 * pairing, else by its name, else made, not tracked as stock) or one item
 * for shipping and other charges. A US company's lines carry TAX or NON and
 * the site's tax total; elsewhere each line carries the tax code matched on
 * QuickBooks' Connections page and the site's tax is given per rate.
 *
 * Every write carries the row's key as its requestid
 * (pg_accounting_sync_key()), so a retried call answers the first write
 * instead of making a second one. A void or delete reads the record's
 * current SyncToken first. Calls are spaced to QuickBooks' limits
 * (pg_accounting_sync_quickbooks_pause()).
 *
 * The order, ERP invoice and refund reading and the refund's share over the
 * tax rates are the Xero driver's (includes/fn/accounting_sync_xero.php).
 * The pure parts are tested in tests/accounting_sync_quickbooks_test.php.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/quickbooks.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/accounting_sync.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/accounting_sync_xero.php');

// Calls a run makes in a minute before it holds the rest: QuickBooks allows
// 500 a minute per company, and the site's own screens read it too.
define('PG_ACCOUNTING_SYNC_QUICKBOOKS_MINUTE', 400);

// The shortest time between two calls, in microseconds: QuickBooks allows
// 10 a second per company and application.
define('PG_ACCOUNTING_SYNC_QUICKBOOKS_GAP', 120000);

// The item that carries lines without a product (shipping, surcharges).
define('PG_ACCOUNTING_SYNC_QUICKBOOKS_CHARGES', 'pg-charges');

/* ---------------------------------------------------------------------------
   Pure parts
   --------------------------------------------------------------------------- */

/**
 * Whether a value is a QuickBooks record id: digits. Pure.
 *
 * @param mixed $id
 * @return bool
 */
function pg_accounting_sync_quickbooks_id_valid($id)
{
    return is_scalar($id) && (preg_match('/^[0-9]{1,20}$/', (string) $id) === 1);
}

/**
 * A text put into a query's quotes, '' when it cannot be. Pure.
 *
 * @param string $value
 * @return string
 */
function pg_accounting_sync_quickbooks_quoted($value)
{
    $value = trim((string) $value);

    return (($value === '') || (strpbrk($value, "'\\\"") !== false)) ? '' : "'" . $value . "'";
}

/**
 * A buyer as a QuickBooks customer. DisplayName is unique among customers,
 * vendors and employees: the second try ($suffix) adds the site's record or
 * the e-mail to it. Pure.
 *
 * @param array  $buyer  number, company, first_name, last_name, email
 * @param string $suffix '' for the first try
 * @return array a Customer
 */
function pg_accounting_sync_quickbooks_customer(array $buyer, $suffix = '')
{
    $contact = pg_accounting_sync_xero_contact($buyer);
    $name = $contact['Name'];

    if ((string) $suffix !== '') {
        $name .= ' (' . $suffix . ')';
    }

    $customer = array('DisplayName' => mb_substr($name, 0, 500));

    foreach (array('CompanyName' => trim((string) ($buyer['company'] ?? '')), 'GivenName' => $contact['FirstName'] ?? '',
        'FamilyName' => $contact['LastName'] ?? '') as $key => $value) {
        if ($value !== '') {
            $customer[$key] = mb_substr($value, 0, 100);
        }
    }

    if (isset($contact['EmailAddress'])) {
        $customer['PrimaryEmailAddr'] = array('Address' => mb_substr($contact['EmailAddress'], 0, 100));
    }

    return $customer;
}

/**
 * Whether an answer refused a name already taken (error 6240). Pure.
 *
 * @param mixed $data the decoded answer
 * @return bool
 */
function pg_accounting_sync_quickbooks_name_taken($data)
{
    $errors = (is_array($data) && isset($data['Fault']['Error']) && is_array($data['Fault']['Error'])) ? $data['Fault']['Error'] : array();

    foreach ($errors as $error) {
        if (is_array($error) && isset($error['code']) && ((string) $error['code'] === '6240')) {
            return true;
        }
    }

    return false;
}

/**
 * A product's item name in QuickBooks: its SKU when it fits 100 characters
 * and names no sub-item (no colon), else one made from its id. Pure.
 *
 * @param string $sku        products.name
 * @param int    $product_id
 * @return string
 */
function pg_accounting_sync_quickbooks_item_name($sku, $product_id)
{
    $sku = trim((string) $sku);

    if (($sku !== '') && (mb_strlen($sku) <= 100) && (strpbrk($sku, ":'\\\"") === false)) {
        return $sku;
    }

    return 'pg-' . (int) $product_id;
}

/**
 * An item sold and not tracked as stock, its sales booked to the income
 * account. Pure.
 *
 * @param string $name
 * @param string $description
 * @param string $account_id the income account
 * @return array an Item
 */
function pg_accounting_sync_quickbooks_item($name, $description, $account_id)
{
    $item = array(
        'Name'             => (string) $name,
        'Type'             => 'NonInventory',
        'IncomeAccountRef' => array('value' => (string) $account_id),
    );

    if (trim((string) $description) !== '') {
        $item['Description'] = mb_substr(trim((string) $description), 0, 4000);
    }

    return $item;
}

/**
 * A line's quantity and unit price that come to its amount exactly, as
 * QuickBooks checks: a discounted line that does not divide goes as one at
 * its amount, the quantity named in the description. Pure.
 *
 * @param mixed  $quantity
 * @param int    $amount      minor units, discount taken off
 * @param string $description
 * @return array qty, unit_price (minor units), description
 */
function pg_accounting_sync_quickbooks_quantity($quantity, $amount, $description)
{
    $quantity = pg_accounting_sync_xero_quantity($quantity);
    $amount = (int) $amount;

    if (is_int($quantity) && ($quantity > 0) && (($amount % $quantity) === 0)) {
        return array('qty' => $quantity, 'unit_price' => intdiv($amount, $quantity), 'description' => (string) $description);
    }

    return array('qty' => 1, 'unit_price' => $amount, 'description' => trim($quantity . ' × ' . $description));
}

/**
 * A document's line as QuickBooks takes it: its amount without tax, the
 * site's tax kept apart. Pure.
 *
 * @param array  $line     description, quantity, line_total (or quantity × unit_price),
 *                         discount_amount, tax_rate, tax_total
 * @param string $item_id
 * @param string $tax_code TAX or NON for a US company, the matched code elsewhere
 * @return array a Line
 */
function pg_accounting_sync_quickbooks_line(array $line, $item_id, $tax_code)
{
    $gross = isset($line['line_total']) ? (int) $line['line_total']
        : (int) round((float) ($line['quantity'] ?? 1) * (int) ($line['unit_price'] ?? 0));
    $amount = $gross - (int) ($line['discount_amount'] ?? 0);
    $description = trim((string) ($line['description'] ?? ''));
    $split = pg_accounting_sync_quickbooks_quantity($line['quantity'] ?? 1, $amount, ($description !== '') ? $description : '-');

    return array(
        'DetailType'          => 'SalesItemLineDetail',
        'Amount'              => pg_accounting_sync_xero_amount($amount),
        'Description'         => mb_substr($split['description'], 0, 4000),
        'SalesItemLineDetail' => array(
            'ItemRef'    => array('value' => (string) $item_id),
            'Qty'        => $split['qty'],
            'UnitPrice'  => pg_accounting_sync_xero_amount($split['unit_price']),
            'TaxCodeRef' => array('value' => (string) $tax_code),
        ),
    );
}

/**
 * The tax of a document given as the site charged it: for a US company the
 * total alone; elsewhere one TaxLine per rate when every matched code has a
 * single sales rate (QuickBooks works it out from the codes otherwise).
 * Pure.
 *
 * @param array $lines     tax_rate, tax_total, line_total, discount_amount
 * @param bool  $us
 * @param array $tax_rates tax rate id => QuickBooks TaxRate id of the matched code ('' for none)
 * @return array|null TxnTaxDetail, null for none
 */
function pg_accounting_sync_quickbooks_tax_detail(array $lines, $us, array $tax_rates)
{
    $total = 0;
    $by_rate = array();

    foreach ($lines as $line) {
        $id = pg_accounting_sync_tax_local_id($line['tax_rate'] ?? 0);
        $tax = (int) ($line['tax_total'] ?? 0);
        $net = (isset($line['line_total']) ? (int) $line['line_total']
            : (int) round((float) ($line['quantity'] ?? 1) * (int) ($line['unit_price'] ?? 0))) - (int) ($line['discount_amount'] ?? 0);
        $total += $tax;
        $by_rate[$id] = array('tax' => ($by_rate[$id]['tax'] ?? 0) + $tax, 'net' => ($by_rate[$id]['net'] ?? 0) + $net);
    }

    if ($us) {
        return array('TotalTax' => pg_accounting_sync_xero_amount($total));
    }

    $tax_lines = array();

    foreach ($by_rate as $id => $sum) {
        if ((string) ($tax_rates[$id] ?? '') === '') {
            return null;
        }

        $tax_lines[] = array(
            'Amount'        => pg_accounting_sync_xero_amount($sum['tax']),
            'DetailType'    => 'TaxLineDetail',
            'TaxLineDetail' => array(
                'TaxRateRef'       => array('value' => (string) $tax_rates[$id]),
                'PercentBased'     => true,
                'TaxPercent'       => round($id / 1000, 3),
                'NetAmountTaxable' => pg_accounting_sync_xero_amount($sum['net']),
            ),
        );
    }

    return array('TotalTax' => pg_accounting_sync_xero_amount($total), 'TaxLine' => $tax_lines);
}

/**
 * A SalesReceipt, Invoice, RefundReceipt or CreditMemo. Pure.
 *
 * @param array $document customer_id, date, due_date (Invoice), currency, number,
 *                        note, account_id (SalesReceipt, RefundReceipt),
 *                        lines (QuickBooks lines), tax (TxnTaxDetail or null), us
 * @param string $entity
 * @return array
 */
function pg_accounting_sync_quickbooks_document($entity, array $document)
{
    $body = array(
        'CustomerRef' => array('value' => (string) $document['customer_id']),
        'TxnDate'     => (string) $document['date'],
        'Line'        => array_values($document['lines']),
    );

    if ((string) ($document['currency'] ?? '') !== '') {
        $body['CurrencyRef'] = array('value' => (string) $document['currency']);
    }

    // DocNumber holds 21 characters; QuickBooks numbers the record without one.
    $number = (string) ($document['number'] ?? '');

    if (($number !== '') && (mb_strlen($number) <= 21)) {
        $body['DocNumber'] = $number;
    }

    if ((string) ($document['note'] ?? '') !== '') {
        $body['PrivateNote'] = mb_substr((string) $document['note'], 0, 4000);
    }

    if (($entity === 'Invoice') && ((string) ($document['due_date'] ?? '') !== '')) {
        $body['DueDate'] = (string) $document['due_date'];
    }

    if (in_array($entity, array('SalesReceipt', 'RefundReceipt'), true)) {
        $body['DepositToAccountRef'] = array('value' => (string) $document['account_id']);
    }

    // A US company works its tax out itself and refuses GlobalTaxCalculation.
    if (empty($document['us'])) {
        $body['GlobalTaxCalculation'] = 'TaxExcluded';
    }

    if (!empty($document['tax'])) {
        $body['TxnTaxDetail'] = $document['tax'];
    }

    return $body;
}

/**
 * A Payment of an Invoice. Pure.
 *
 * @param string $customer_id
 * @param string $invoice_id
 * @param string $account_id
 * @param string $date      Y-m-d
 * @param int    $amount    minor units
 * @param string $currency  '' for the home currency
 * @param string $reference
 * @return array
 */
function pg_accounting_sync_quickbooks_payment($customer_id, $invoice_id, $account_id, $date, $amount, $currency, $reference)
{
    $payment = array(
        'CustomerRef'         => array('value' => (string) $customer_id),
        'TotalAmt'            => pg_accounting_sync_xero_amount($amount),
        'TxnDate'             => (string) $date,
        'DepositToAccountRef' => array('value' => (string) $account_id),
        'Line'                => array(array(
            'Amount'    => pg_accounting_sync_xero_amount($amount),
            'LinkedTxn' => array(array('TxnId' => (string) $invoice_id, 'TxnType' => 'Invoice')),
        )),
    );

    if ((string) $currency !== '') {
        $payment['CurrencyRef'] = array('value' => (string) $currency);
    }

    if ((string) $reference !== '') {
        $payment['PaymentRefNum'] = mb_substr((string) $reference, 0, 21);
    }

    return $payment;
}

/**
 * The record of an answer (a write's or a read's): its Id and SyncToken,
 * null when it has none. Pure.
 *
 * @param mixed  $data   the decoded answer
 * @param string $entity SalesReceipt and the like
 * @return array|null id, sync_token, record
 */
function pg_accounting_sync_quickbooks_record($data, $entity)
{
    $record = (is_array($data) && isset($data[$entity]) && is_array($data[$entity])) ? $data[$entity] : null;

    if (($record === null) || !pg_accounting_sync_quickbooks_id_valid($record['Id'] ?? '')) {
        return null;
    }

    $token = (isset($record['SyncToken']) && is_scalar($record['SyncToken'])) ? (string) $record['SyncToken'] : '';

    return array('id' => (string) $record['Id'], 'sync_token' => (preg_match('/^[0-9]{1,20}$/', $token) === 1) ? $token : '', 'record' => $record);
}

/**
 * The Id of the first row of a query answer, '' for none. Pure.
 *
 * @param mixed  $data
 * @param string $entity
 * @return string
 */
function pg_accounting_sync_quickbooks_first_id($data, $entity)
{
    $rows = quickbooks_query_rows($data, $entity);
    $id = (isset($rows[0]) && is_array($rows[0])) ? ($rows[0]['Id'] ?? '') : '';

    return pg_accounting_sync_quickbooks_id_valid($id) ? (string) $id : '';
}

/**
 * How long to wait before the next call, after the times of this run's
 * calls: the rest of the gap since the last one in microseconds, or -1 when
 * the run has made its calls of the minute (the rest waits for the next
 * pass). Pure.
 *
 * @param float[] $stamps microtime(true) of the calls made
 * @param float   $now
 * @return int
 */
function pg_accounting_sync_quickbooks_pause(array $stamps, $now)
{
    $recent = 0;

    foreach ($stamps as $stamp) {
        if ($stamp > $now - 60) {
            $recent++;
        }
    }

    if ($recent >= PG_ACCOUNTING_SYNC_QUICKBOOKS_MINUTE) {
        return -1;
    }

    $last = $stamps ? max($stamps) : 0;
    $gap = PG_ACCOUNTING_SYNC_QUICKBOOKS_GAP - (int) round(($now - $last) * 1000000);

    return max(0, $gap);
}

/**
 * A failed call as the driver's answer: its status and QuickBooks' words;
 * a 429 waits Retry-After, a minute at least. Pure.
 *
 * @param array $call status, error, headers
 * @return array
 */
function pg_accounting_sync_quickbooks_failure(array $call)
{
    return pg_accounting_sync_xero_failure($call);
}

/* ---------------------------------------------------------------------------
   Calls
   --------------------------------------------------------------------------- */

/**
 * One accounting call for the company: a JSON body, the requestid on a
 * write, spaced to QuickBooks' limits within the run.
 *
 * @param array       $context connection_id, environment, realm_id
 * @param string      $method  GET or POST
 * @param string      $path    after /v3/company/<realmId>/, with its own query
 * @param array|null  $body
 * @param string      $key     the requestid, '' for none
 * @return array ok, status, data, error, headers
 */
function pg_accounting_sync_quickbooks_call(array $context, $method, $path, $body = null, $key = '')
{
    static $stamps = array();

    $wait = pg_accounting_sync_quickbooks_pause($stamps, microtime(true));

    if ($wait < 0) {
        return array('ok' => false, 'status' => 429, 'data' => null, 'error' => lang('QuickBooks\' call limit is nearly reached.'),
            'headers' => array('retry-after' => '60'));
    }

    if ($wait > 0) {
        usleep($wait);
    }

    $stamps[] = microtime(true);
    $stamps = array_slice($stamps, -PG_ACCOUNTING_SYNC_QUICKBOOKS_MINUTE);

    $url = quickbooks_api_base($context['environment']) . $context['realm_id'] . '/' . $path
        . ((strpos($path, '?') === false) ? '?' : '&') . 'minorversion=' . PG_QUICKBOOKS_MINOR_VERSION
        . (($key !== '') ? '&requestid=' . rawurlencode($key) : '');

    $options = array(
        'headers'          => array('Accept: application/json'),
        'timeout'          => 60,
        'max_body'         => 4194304,
        'response_headers' => true,
    );

    if ($body !== null) {
        $options['headers'][] = 'Content-Type: application/json';
        $options['body'] = json_encode($body);
    }

    $result = pg_connect_api((int) $context['connection_id'], $method, $url, $options);

    return array(
        'ok'      => !empty($result['ok']),
        'status'  => (int) $result['status'],
        'data'    => $result['data'],
        'error'   => !empty($result['ok']) ? '' : quickbooks_error_message((int) $result['status'], $result['data'], (string) $result['error']),
        'headers' => (isset($result['headers']) && is_array($result['headers'])) ? $result['headers'] : array(),
    );
}

/**
 * Makes one record and answers its Id and SyncToken.
 *
 * @param array  $context
 * @param string $entity SalesReceipt and the like
 * @param array  $record
 * @param string $key    requestid
 * @return array ok, id, sync_token, data, outcome (the failure)
 */
function pg_accounting_sync_quickbooks_create(array $context, $entity, array $record, $key)
{
    $call = pg_accounting_sync_quickbooks_call($context, 'POST', strtolower($entity), $record, $key);
    $made = $call['ok'] ? pg_accounting_sync_quickbooks_record($call['data'], $entity) : null;

    if ($made === null) {
        $outcome = $call['ok'] ? array('ok' => false, 'status' => 400, 'error' => lang('QuickBooks gave no id.'))
            : pg_accounting_sync_quickbooks_failure($call);

        return array('ok' => false, 'id' => '', 'sync_token' => '', 'data' => $call['data'], 'outcome' => $outcome);
    }

    return array('ok' => true, 'id' => $made['id'], 'sync_token' => $made['sync_token'], 'data' => $call['data'], 'outcome' => array());
}

/**
 * Reads a record by its Id.
 *
 * @param array  $context
 * @param string $entity
 * @param string $id
 * @return array ok, record (pg_accounting_sync_quickbooks_record()), outcome
 */
function pg_accounting_sync_quickbooks_read(array $context, $entity, $id)
{
    $call = pg_accounting_sync_quickbooks_call($context, 'GET', strtolower($entity) . '/' . rawurlencode((string) $id));
    $record = $call['ok'] ? pg_accounting_sync_quickbooks_record($call['data'], $entity) : null;

    if ($record === null) {
        return array('ok' => false, 'record' => null,
            'outcome' => $call['ok'] ? array('ok' => false, 'status' => 404, 'error' => lang('QuickBooks gave no record.')) : pg_accounting_sync_quickbooks_failure($call));
    }

    return array('ok' => true, 'record' => $record, 'outcome' => array());
}

/**
 * Voids an Invoice or a Payment, or deletes a CreditMemo, with its current
 * SyncToken.
 *
 * @param array  $context
 * @param string $entity Invoice, Payment, CreditMemo
 * @param string $id
 * @param string $key
 * @return array the driver's answer
 */
function pg_accounting_sync_quickbooks_void(array $context, $entity, $id, $key)
{
    $read = pg_accounting_sync_quickbooks_read($context, $entity, $id);

    if (!$read['ok']) {
        return $read['outcome'];
    }

    $body = array('Id' => (string) $id, 'SyncToken' => $read['record']['sync_token']);
    $operations = array('Invoice' => 'operation=void', 'Payment' => 'operation=update&include=void', 'CreditMemo' => 'operation=delete');

    if ($entity === 'Payment') {
        $body['sparse'] = true;
    }

    $call = pg_accounting_sync_quickbooks_call($context, 'POST', strtolower($entity) . '?' . $operations[$entity], $body, $key);

    return $call['ok'] ? array('ok' => true, 'remote_id' => (string) $id, 'sync_token' => '') : pg_accounting_sync_quickbooks_failure($call);
}

/* ---------------------------------------------------------------------------
   Customers, items, tax codes
   --------------------------------------------------------------------------- */

/**
 * The buyer's customer: its pairing, else the customer with its e-mail,
 * else one made (named again with its record or e-mail when the name is
 * taken). A found or made customer is paired when the buyer is a record.
 *
 * @param array $context
 * @param array $buyer   local_table, local_id, and the customer's fields
 * @param array $row
 * @return array ok, id, outcome
 */
function pg_accounting_sync_quickbooks_customer_for(array $context, array $buyer, array $row)
{
    $local_id = (int) ($buyer['local_id'] ?? 0);

    if ($local_id > 0) {
        $pair = pg_accounting_sync_pair($context['target'], 'customer', $buyer['local_table'], $local_id);

        if (is_array($pair) && pg_accounting_sync_quickbooks_id_valid($pair['remote_id'])) {
            return array('ok' => true, 'id' => (string) $pair['remote_id'], 'outcome' => array());
        }
    }

    $id = '';
    $email = (filter_var(trim((string) ($buyer['email'] ?? '')), FILTER_VALIDATE_EMAIL) !== false)
        ? pg_accounting_sync_quickbooks_quoted($buyer['email']) : '';

    if ($email !== '') {
        $call = pg_accounting_sync_quickbooks_call($context, 'GET', 'query?query='
            . rawurlencode('select Id from Customer where PrimaryEmailAddr = ' . $email . ' maxresults 1'));

        if (!$call['ok']) {
            return array('ok' => false, 'id' => '', 'outcome' => pg_accounting_sync_quickbooks_failure($call));
        }

        $id = pg_accounting_sync_quickbooks_first_id($call['data'], 'Customer');
    }

    if ($id === '') {
        $made = pg_accounting_sync_quickbooks_create($context, 'Customer', pg_accounting_sync_quickbooks_customer($buyer),
            pg_accounting_sync_key($row, 'contact'));

        if (!$made['ok'] && pg_accounting_sync_quickbooks_name_taken($made['data'])) {
            $suffix = ((string) ($buyer['number'] ?? '') !== '') ? (string) $buyer['number'] : trim((string) ($buyer['email'] ?? ''));
            $made = pg_accounting_sync_quickbooks_create($context, 'Customer',
                pg_accounting_sync_quickbooks_customer($buyer, ($suffix !== '') ? $suffix : 'pg-' . (int) $row['id']),
                pg_accounting_sync_key($row, 'contact-2'));
        }

        if (!$made['ok']) {
            return array('ok' => false, 'id' => '', 'outcome' => $made['outcome']);
        }

        $id = $made['id'];
    }

    if ($local_id > 0) {
        pg_accounting_sync_pair_save($context['target'], 'customer', $buyer['local_table'], $local_id, $id);
    }

    return array('ok' => true, 'id' => $id, 'outcome' => array());
}

/**
 * One item by its pairing, else by its name, else made, and paired.
 *
 * @param array  $context
 * @param int    $product_id 0 for the charges item
 * @param string $name
 * @param string $description
 * @param string $key
 * @return array ok, id, outcome
 */
function pg_accounting_sync_quickbooks_item_for(array $context, $product_id, $name, $description, $key)
{
    $pair = pg_accounting_sync_pair($context['target'], 'item', 'products', (int) $product_id);

    if (is_array($pair) && pg_accounting_sync_quickbooks_id_valid($pair['remote_id'])) {
        return array('ok' => true, 'id' => (string) $pair['remote_id'], 'outcome' => array());
    }

    $call = pg_accounting_sync_quickbooks_call($context, 'GET', 'query?query='
        . rawurlencode('select Id from Item where Name = ' . pg_accounting_sync_quickbooks_quoted($name) . ' maxresults 1'));

    if (!$call['ok']) {
        return array('ok' => false, 'id' => '', 'outcome' => pg_accounting_sync_quickbooks_failure($call));
    }

    $id = pg_accounting_sync_quickbooks_first_id($call['data'], 'Item');

    if ($id === '') {
        $made = pg_accounting_sync_quickbooks_create($context, 'Item',
            pg_accounting_sync_quickbooks_item($name, $description, $context['accounts']['income']['id']), $key);

        if (!$made['ok']) {
            return array('ok' => false, 'id' => '', 'outcome' => $made['outcome']);
        }

        $id = $made['id'];
    }

    // The pairing keeps the item's id and, as its token, the name it has.
    pg_accounting_sync_pair_save($context['target'], 'item', 'products', (int) $product_id, $id, $name);

    return array('ok' => true, 'id' => $id, 'outcome' => array());
}

/**
 * The item of each line's product (product id => item id, 0 the charges
 * item for lines without a product or with one deleted since).
 *
 * @param array $context
 * @param array $lines product_id, description
 * @param array $row
 * @return array ok, items, outcome
 */
function pg_accounting_sync_quickbooks_items_for(array $context, array $lines, array $row)
{
    $items = array();

    foreach ($lines as $line) {
        $product_id = (int) ($line['product_id'] ?? 0);
        $product = ($product_id > 0) ? db_item("SELECT name, short_description FROM products WHERE id = '" . $product_id . "' LIMIT 1") : null;
        $product_id = is_array($product) ? $product_id : 0;

        if (isset($items[$product_id])) {
            continue;
        }

        if ($product_id > 0) {
            $description = (trim((string) $product['short_description']) !== '') ? $product['short_description'] : ($line['description'] ?? '');
            $item = pg_accounting_sync_quickbooks_item_for($context, $product_id,
                pg_accounting_sync_quickbooks_item_name($product['name'], $product_id), $description, pg_accounting_sync_key($row, 'item-' . $product_id));
        } else {
            $item = pg_accounting_sync_quickbooks_item_for($context, 0, PG_ACCOUNTING_SYNC_QUICKBOOKS_CHARGES,
                lang('Shipping and other charges'), pg_accounting_sync_key($row, 'item-0'));
        }

        if (!$item['ok']) {
            return array('ok' => false, 'items' => array(), 'outcome' => $item['outcome']);
        }

        $items[$product_id] = $item['id'];
    }

    return array('ok' => true, 'items' => $items, 'outcome' => array());
}

/**
 * The sales TaxRate of each matched code, read once a run: '' for a code
 * with none or more than one, so QuickBooks works that tax out itself.
 *
 * @param array $context
 * @param array $codes tax rate id => TaxCode id
 * @return array ok, rates (tax rate id => TaxRate id), outcome
 */
function pg_accounting_sync_quickbooks_tax_rates(array $context, array $codes)
{
    static $read = array();

    $rates = array();

    foreach ($codes as $local_id => $code) {
        $cache = $context['realm_id'] . '/' . $code;

        if (!isset($read[$cache])) {
            $got = pg_accounting_sync_quickbooks_read($context, 'TaxCode', $code);

            if (!$got['ok']) {
                return array('ok' => false, 'rates' => array(), 'outcome' => $got['outcome']);
            }

            $details = $got['record']['record']['SalesTaxRateList']['TaxRateDetail'] ?? array();
            $rate = (is_array($details) && (count($details) === 1)) ? ($details[0]['TaxRateRef']['value'] ?? '') : '';
            $read[$cache] = pg_accounting_sync_quickbooks_id_valid($rate) ? (string) $rate : '';
        }

        $rates[$local_id] = $read[$cache];
    }

    return array('ok' => true, 'rates' => $rates, 'outcome' => array());
}

/* ---------------------------------------------------------------------------
   The records sent
   --------------------------------------------------------------------------- */

/**
 * Sends a SalesReceipt, Invoice, RefundReceipt or CreditMemo built from
 * lines.
 *
 * @param array  $context
 * @param array  $row
 * @param string $entity
 * @param array  $buyer
 * @param array  $lines    erp_order_lines() or erp_invoice_items rows
 * @param array  $document date, due_date, currency, number, note, account_id
 * @return array the driver's answer
 */
function pg_accounting_sync_quickbooks_send_document(array $context, array $row, $entity, array $buyer, array $lines, array $document)
{
    if (!$lines) {
        return pg_accounting_sync_xero_refused(lang('The record has no lines to send.'));
    }

    $codes = array();

    if (!$context['us']) {
        $codes = pg_accounting_sync_xero_tax_types($context['target'], $lines);
        $missing = pg_accounting_sync_xero_unmatched($lines, $codes);

        if ($missing) {
            return pg_accounting_sync_xero_not_ready(lang(array(
                'string' => 'Not matched to a QuickBooks tax code on the Connections page: {var:1}',
                'vars'   => implode(', ', array_map('pg_accounting_sync_tax_label', $missing)),
            )));
        }
    }

    $customer = pg_accounting_sync_quickbooks_customer_for($context, $buyer, $row);

    if (!$customer['ok']) {
        return $customer['outcome'];
    }

    $items = pg_accounting_sync_quickbooks_items_for($context, $lines, $row);

    if (!$items['ok']) {
        return $items['outcome'];
    }

    $rates = $context['us'] ? array('ok' => true, 'rates' => array()) : pg_accounting_sync_quickbooks_tax_rates($context, $codes);

    if (!$rates['ok']) {
        return $rates['outcome'];
    }

    $quickbooks_lines = array();

    foreach ($lines as $line) {
        $product_id = (int) ($line['product_id'] ?? 0);
        $code = $context['us'] ? (((int) ($line['tax_total'] ?? 0) !== 0) ? 'TAX' : 'NON')
            : $codes[pg_accounting_sync_tax_local_id($line['tax_rate'] ?? 0)];

        $quickbooks_lines[] = pg_accounting_sync_quickbooks_line($line, $items['items'][$product_id] ?? $items['items'][0], $code);
    }

    $made = pg_accounting_sync_quickbooks_create($context, $entity, pg_accounting_sync_quickbooks_document($entity, $document + array(
        'customer_id' => $customer['id'],
        'lines'       => $quickbooks_lines,
        'tax'         => pg_accounting_sync_quickbooks_tax_detail($lines, $context['us'], $rates['rates']),
        'us'          => $context['us'],
    )), pg_accounting_sync_key($row, 'create'));

    return $made['ok'] ? array('ok' => true, 'remote_id' => $made['id'], 'sync_token' => $made['sync_token']) : $made['outcome'];
}

/**
 * The chosen account a payment goes to or comes out of, '' when not chosen.
 *
 * @param array  $context
 * @param string $method
 * @return string
 */
function pg_accounting_sync_quickbooks_account(array $context, $method)
{
    return (string) $context['accounts'][pg_accounting_sync_xero_payment_role($method)]['id'];
}

/**
 * A row whose accounts are not chosen yet. Pure.
 *
 * @return array
 */
function pg_accounting_sync_quickbooks_no_accounts()
{
    return pg_accounting_sync_xero_not_ready(lang('The accounts are not chosen on QuickBooks\' Connections page.'));
}

/**
 * A sale: a paid order as a SalesReceipt (ERP off), an ERP sales invoice as
 * an Invoice, voided when cancelled.
 *
 * @param array $context
 * @param array $row
 * @return array
 */
function pg_accounting_sync_quickbooks_sale(array $context, array $row)
{
    if ($row['local_table'] !== 'orders') {
        return pg_accounting_sync_quickbooks_erp_document($context, $row, 'Invoice');
    }

    if (pg_accounting_sync_quickbooks_id_valid($row['remote_id'])) {
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
    $account = pg_accounting_sync_quickbooks_account($context, $order['payment_method'] ?? '');

    if ($account === '') {
        return pg_accounting_sync_quickbooks_no_accounts();
    }

    return pg_accounting_sync_quickbooks_send_document($context, $row, 'SalesReceipt', pg_accounting_sync_xero_order_buyer($order), $sale['lines'], array(
        'date'       => pg_accounting_sync_xero_date(!empty($order['paid_at']) ? $order['paid_at'] : ($order['order_date'] ?? 0)),
        'currency'   => erp_base_currency(),
        'note'       => lang(array('string' => 'Order {var:1}', 'vars' => (string) $order['order_number'])),
        'account_id' => $account,
    ));
}

/**
 * An ERP sales invoice (Invoice) or return invoice (CreditMemo); a
 * cancelled one voids or deletes the one sent, or ends with nothing sent.
 *
 * @param array  $context
 * @param array  $row
 * @param string $entity
 * @return array
 */
function pg_accounting_sync_quickbooks_erp_document(array $context, array $row, $entity)
{
    $document = pg_accounting_sync_xero_erp_invoice($row['local_id']);

    if ($document === null) {
        return pg_accounting_sync_xero_refused(lang('The invoice is gone.'));
    }

    $invoice = $document['invoice'];
    $sent = pg_accounting_sync_quickbooks_id_valid($row['remote_id']);

    if ((string) $invoice['status'] === 'cancelled') {
        return $sent ? pg_accounting_sync_quickbooks_void($context, $entity, $row['remote_id'], pg_accounting_sync_key($row, 'void')) : array('ok' => true);
    }

    if ($sent) {
        return array('ok' => true);
    }

    if (((int) ($invoice['withholding_total'] ?? 0) !== 0) || ((int) ($invoice['tax2_total'] ?? 0) !== 0)) {
        return pg_accounting_sync_xero_refused(lang('QuickBooks takes no withheld tax and no second tax on a line, so this invoice was not sent.'));
    }

    $order_number = ((int) $invoice['order_id'] > 0)
        ? (string) db_value("SELECT order_number FROM orders WHERE id = '" . (int) $invoice['order_id'] . "' LIMIT 1") : '';
    $number = (string) $invoice['full_number'];

    return pg_accounting_sync_quickbooks_send_document($context, $row, $entity, pg_accounting_sync_xero_erp_buyer($invoice), $document['lines'], array(
        'date'     => pg_accounting_sync_xero_date($invoice['issue_date']),
        'due_date' => ((string) $invoice['due_date'] !== '0000-00-00') ? pg_accounting_sync_xero_date($invoice['due_date']) : '',
        'currency' => (string) $invoice['currency'],
        'number'   => $number,
        'note'     => trim(((mb_strlen($number) > 21) ? $number . ' ' : '')
            . (($order_number !== '') ? lang(array('string' => 'Order {var:1}', 'vars' => $order_number)) : '')),
    ));
}

/**
 * A payment: an order's is the SalesReceipt itself (ERP off); an ERP
 * receipt of an invoice is a Payment, voided when the receipt is cancelled.
 *
 * @param array $context
 * @param array $row
 * @return array
 */
function pg_accounting_sync_quickbooks_payment_row(array $context, array $row)
{
    if ($row['local_table'] === 'orders') {
        return array('ok' => true);
    }

    $receipt = db_item("SELECT * FROM erp_cash_transactions WHERE id = '" . (int) $row['local_id'] . "' LIMIT 1");

    if (!is_array($receipt)) {
        return pg_accounting_sync_xero_refused(lang('The receipt is gone.'));
    }

    $sent = pg_accounting_sync_quickbooks_id_valid($row['remote_id']);

    if ((int) db_value("SELECT COUNT(*) FROM erp_cash_transactions WHERE doc_type = 'cancel' AND doc_id = '" . (int) $receipt['id'] . "'") > 0) {
        return $sent ? pg_accounting_sync_quickbooks_void($context, 'Payment', $row['remote_id'], pg_accounting_sync_key($row, 'void')) : array('ok' => true);
    }

    if ($sent) {
        return array('ok' => true);
    }

    $settled = db_item("SELECT s.invoice_id, s.amount FROM erp_settlements s
        INNER JOIN erp_account_transactions t ON t.id = s.account_txn_id
        WHERE t.doc_type IN ('collection', 'payment') AND t.doc_id = '" . (int) $receipt['id'] . "'
        ORDER BY s.id LIMIT 1");

    if (!is_array($settled) || ((int) $settled['amount'] <= 0)) {
        return pg_accounting_sync_xero_refused(lang('The receipt settles no invoice.'));
    }

    $paired = pg_accounting_sync_pair($context['target'], 'sale', 'erp_invoices', (int) $settled['invoice_id']);

    if (!is_array($paired)) {
        if (is_array(pg_accounting_sync_pair($context['target'], 'refund', 'erp_invoices', (int) $settled['invoice_id']))) {
            return pg_accounting_sync_xero_refused(lang('QuickBooks records money paid back on a credit memo only in its own screens: record this refund there.'));
        }
    }

    if (!is_array($paired) || !pg_accounting_sync_quickbooks_id_valid($paired['remote_id'])) {
        return pg_accounting_sync_xero_not_ready(lang('The invoice is not in QuickBooks yet.'));
    }

    $account = pg_accounting_sync_quickbooks_account($context, $receipt['payment_method']);

    if ($account === '') {
        return pg_accounting_sync_quickbooks_no_accounts();
    }

    // The customer and currency are the invoice's, as QuickBooks keeps it.
    $invoice = pg_accounting_sync_quickbooks_read($context, 'Invoice', $paired['remote_id']);

    if (!$invoice['ok']) {
        return $invoice['outcome'];
    }

    $made = pg_accounting_sync_quickbooks_create($context, 'Payment', pg_accounting_sync_quickbooks_payment(
        (string) ($invoice['record']['record']['CustomerRef']['value'] ?? ''), $paired['remote_id'], $account,
        pg_accounting_sync_xero_date($receipt['doc_date']), (int) $settled['amount'],
        (string) ($invoice['record']['record']['CurrencyRef']['value'] ?? ''), (string) $receipt['description']
    ), pg_accounting_sync_key($row, 'create'));

    return $made['ok'] ? array('ok' => true, 'remote_id' => $made['id'], 'sync_token' => $made['sync_token']) : $made['outcome'];
}

/**
 * A refund: an order's (ERP off) as a RefundReceipt out of the payment's
 * account, or a CreditMemo when it stays as credit, its lines the refund
 * shared over the sale's tax rates; an ERP return invoice as a CreditMemo,
 * deleted when cancelled.
 *
 * @param array $context
 * @param array $row
 * @return array
 */
function pg_accounting_sync_quickbooks_refund(array $context, array $row)
{
    if ($row['local_table'] !== 'order_refunds') {
        return pg_accounting_sync_quickbooks_erp_document($context, $row, 'CreditMemo');
    }

    if (pg_accounting_sync_quickbooks_id_valid($row['remote_id'])) {
        return array('ok' => true);
    }

    $refund = db_item("SELECT * FROM order_refunds WHERE id = '" . (int) $row['local_id'] . "' LIMIT 1");

    if (!is_array($refund)) {
        return pg_accounting_sync_xero_refused(lang('The refund is gone.'));
    }

    $order = pg_accounting_sync_xero_order($refund['order_id']);

    if ($order === null) {
        return pg_accounting_sync_xero_refused(lang('The order is gone.'));
    }

    $paid_out = pg_accounting_sync_xero_refund_paid_out($refund['refund_type']);
    $account = $paid_out ? pg_accounting_sync_quickbooks_account($context, $order['order']['payment_method'] ?? '') : '';

    if ($paid_out && ($account === '')) {
        return pg_accounting_sync_quickbooks_no_accounts();
    }

    $lines = array();

    foreach (pg_accounting_sync_xero_refund_shares((int) $refund['amount_cents'], $order['lines']) as $rate_id => $gross) {
        $tax = pg_accounting_sync_xero_tax_inside($gross, $rate_id);
        $lines[] = array(
            'description' => lang(array('string' => 'Refund of order {var:1}', 'vars' => (string) $order['order']['order_number'])),
            'quantity'    => 1,
            'line_total'  => $gross - $tax,
            'tax_rate'    => $rate_id / 1000,
            'tax_total'   => $tax,
        );
    }

    return pg_accounting_sync_quickbooks_send_document($context, $row, $paid_out ? 'RefundReceipt' : 'CreditMemo',
        pg_accounting_sync_xero_order_buyer($order['order']), $lines, array(
            'date'       => pg_accounting_sync_xero_date($refund['created_at']),
            'currency'   => erp_base_currency(),
            'note'       => lang(array('string' => 'Refund of order {var:1}', 'vars' => (string) $order['order']['order_number'])),
            'account_id' => $account,
        ));
}

/**
 * The driver: one queued row to QuickBooks (pg_accounting_sync_dispatch()).
 *
 * @param array $row    an accounting_sync row
 * @param array $target service, company
 * @return array the answer pg_accounting_sync_after_attempt() reads
 */
function pg_accounting_sync_send_quickbooks(array $row, array $target)
{
    $state = quickbooks_state();
    $connection = quickbooks_connection($state);

    if (!is_array($connection) || ($state['realm_id'] === '') || ($state['realm_id'] !== (string) $target['company'])) {
        return array('ok' => false, 'status' => 401, 'error' => lang('QuickBooks is not connected to this company.'));
    }

    if ($state['accounts']['income']['id'] === '') {
        return pg_accounting_sync_quickbooks_no_accounts();
    }

    $context = array(
        'target'        => $target,
        'connection_id' => (int) $connection['id'],
        'environment'   => $state['environment'],
        'realm_id'      => $state['realm_id'],
        'accounts'      => $state['accounts'],
        'us'            => quickbooks_tax_us($state),
    );

    $row['remote_id'] = (string) ($row['remote_id'] ?? '');
    $row['sync_token'] = (string) ($row['sync_token'] ?? '');
    $row['local_id'] = (int) ($row['local_id'] ?? 0);
    $row['id'] = (int) ($row['id'] ?? 0);

    require_once(PG_FUNCTIONS_DIR . '/includes/erp/bootstrap.php');

    switch ((string) $row['kind']) {
        case 'sale':
            return pg_accounting_sync_quickbooks_sale($context, $row);
        case 'payment':
            return pg_accounting_sync_quickbooks_payment_row($context, $row);
        case 'refund':
            return pg_accounting_sync_quickbooks_refund($context, $row);
    }

    return pg_accounting_sync_xero_refused(lang('QuickBooks takes no record of this kind.'));
}
