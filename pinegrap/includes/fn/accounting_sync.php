<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The accounting sync's queue and pairing (table accounting_sync): the
 * moments that write a row (an order paid, a refund recorded, an ERP
 * invoice, receipt or cancellation), the general job's pass that hands each
 * due row to its service's driver, paced and retried on
 * api_webhook_backoff(), and the pairing a driver reads and keeps (a
 * customer, an item, a tax rate and what each became in the service).
 *
 * Out only: nothing changed in the books comes back. No service is called in
 * a request; only job.php sends. One accounting service per site at a time,
 * within one of its companies (a Xero organisation): rows are keyed by both,
 * so another company starts with rows of its own.
 *
 * The source: without the ERP module the paid order (a sale and its payment)
 * and its order_refunds rows; with it the ERP's sales invoices, return
 * invoices and receipts, and their cancellations.
 *
 * A service's driver is includes/fn/accounting_sync_<service>.php with
 * pg_accounting_sync_send_<service>(array $row, array $target); until a
 * service has one, its rows wait. The answer a driver gives is described at
 * pg_accounting_sync_after_attempt().
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

// order_refunds rows read in one pass of the refund scan.
define('PG_ACCOUNTING_SYNC_REFUND_ROWS', 200);

// How long a row a pass took is kept from another pass while its call runs.
define('PG_ACCOUNTING_SYNC_CLAIM', 600);

// The longest wait a driver's answer may ask for, in seconds.
define('PG_ACCOUNTING_SYNC_LONGEST_WAIT', 86400);

/* ---------------------------------------------------------------------------
   Pure parts
   --------------------------------------------------------------------------- */

/**
 * The kinds of record a row pairs, as the table lists them. Pure.
 *
 * @return string[]
 */
function pg_accounting_sync_kinds()
{
    return array('customer', 'item', 'tax', 'sale', 'payment', 'refund');
}

/**
 * Rows of a service handed to its driver in one pass of the general job.
 * Xero allows 60 calls a minute per organisation and a sale may take several
 * (its customer, its items, the invoice); QuickBooks Online allows 500. Pure.
 *
 * @param string $service
 * @return int
 */
function pg_accounting_sync_pace($service)
{
    $paces = array('xero' => 10, 'quickbooks' => 40);

    return isset($paces[$service]) ? $paces[$service] : 10;
}

/**
 * The service and company rows are written for, from Xero's stored state:
 * connected, with an organisation chosen. Pure.
 *
 * @param array $state     xero_state_parse()
 * @param bool  $connected a Xero connection is stored
 * @return array|null service, company
 */
function pg_accounting_sync_xero_target(array $state, $connected)
{
    $tenant = (string) ($state['tenant_id'] ?? '');

    if (!$connected || ($tenant === '')) {
        return null;
    }

    return array('service' => 'xero', 'company' => $tenant);
}

/**
 * The row an ERP event writes. A sales invoice issued is a sale and a return
 * invoice a refund; a proforma, a purchase invoice or a draft writes
 * nothing. A receipt is a payment when it was named for an invoice. Whether
 * the service has the invoice a payment or a return names is read when the
 * row is written. A
 * cancellation puts the record's row back in the queue, so its driver voids
 * what was sent; it never writes a row of its own. Pure.
 *
 * @param string $event   erp.invoice.created and the like
 * @param array  $payload erp_event_invoice()'s or erp_event_receipt()'s
 * @return array|null kind, local_table, local_id, again (a cancellation),
 *                    invoice_id (a payment's invoice, a return's invoice, 0
 *                    otherwise); null when the event writes nothing
 */
function pg_accounting_sync_erp_event_row($event, array $payload)
{
    $id = (int) ($payload['id'] ?? 0);

    if ($id <= 0) {
        return null;
    }

    if (in_array($event, array('erp.invoice.created', 'erp.invoice.cancelled'), true)) {

        $kinds = array('invoice' => 'sale', 'return' => 'refund');
        $doc_type = (string) ($payload['doc_type'] ?? '');
        $again = ($event === 'erp.invoice.cancelled');

        if (((string) ($payload['direction'] ?? '') !== 'sales') || !isset($kinds[$doc_type])) {
            return null;
        }

        if (!$again && in_array((string) ($payload['status'] ?? ''), array('draft', 'cancelled'), true)) {
            return null;
        }

        // A return names the invoice it returns, which the service must have.
        $invoice_id = ($doc_type === 'return') ? max(0, (int) ($payload['parent_invoice_id'] ?? 0)) : 0;

        return array('kind' => $kinds[$doc_type], 'local_table' => 'erp_invoices', 'local_id' => $id, 'again' => $again, 'invoice_id' => $invoice_id);

    }

    if ($event === 'erp.receipt.created') {

        $invoice_id = (int) ($payload['invoice_id'] ?? 0);

        if ($invoice_id <= 0) {
            return null;
        }

        return array('kind' => 'payment', 'local_table' => 'erp_cash_transactions', 'local_id' => $id, 'again' => false, 'invoice_id' => $invoice_id);

    }

    if ($event === 'erp.receipt.cancelled') {
        return array('kind' => 'payment', 'local_table' => 'erp_cash_transactions', 'local_id' => $id, 'again' => true, 'invoice_id' => 0);
    }

    return null;
}

/**
 * The refunds the scan writes a row for: those of an order whose sale the
 * service has, for more than nothing. Pure.
 *
 * @param array $refunds order_refunds rows (id, order_id, amount_cents)
 * @param int[] $sold    the orders whose sale has a row
 * @return int[] refund ids
 */
function pg_accounting_sync_refunds_to_queue(array $refunds, array $sold)
{
    $sold = array_flip(array_map('intval', $sold));
    $ids = array();

    foreach ($refunds as $refund) {
        if (isset($sold[(int) ($refund['order_id'] ?? 0)]) && ((int) ($refund['amount_cents'] ?? 0) > 0)) {
            $ids[] = (int) $refund['id'];
        }
    }

    return $ids;
}

/**
 * The key that makes a retried call the same call: Xero's Idempotency-Key,
 * QuickBooks' requestid. Made from the row's pairing and the operation
 * (create, void, ...), never from the attempt, so every try of one operation
 * carries the same key, and a sale is not made twice from one row. 50
 * characters: QuickBooks' limit, within Xero's 128. The site's key in the
 * hash keeps apart two sites that book into one company. Pure.
 *
 * @param string $secret    the site's key, '' without one
 * @param array  $row       service, company, kind, local_table, local_id
 * @param string $operation
 * @return string
 */
function pg_accounting_sync_key_for($secret, array $row, $operation)
{
    $text = implode('|', array(
        (string) ($row['service'] ?? ''),
        (string) ($row['company'] ?? ''),
        (string) ($row['kind'] ?? ''),
        (string) ($row['local_table'] ?? ''),
        (string) (int) ($row['local_id'] ?? 0),
        (string) $operation,
    ));

    return 'pg-' . substr(hash_hmac('sha256', $text, 'accounting-sync:' . (string) $secret), 0, 47);
}

/**
 * The pairing's local id of a tax rate: the rate in thousandths of a
 * percent, as the site keeps it (DECIMAL(6,3)), so 20% is 20000 and 8.875%
 * is 8875. Its local_table is 'tax_rate'. Pure.
 *
 * @param float|string $rate percent
 * @return int
 */
function pg_accounting_sync_tax_local_id($rate)
{
    return max(0, (int) round(((float) $rate) * 1000));
}

/**
 * The site's tax rates as pairing ids (pg_accounting_sync_tax_local_id()):
 * each rate between 0 and 100 once, smallest first, 0 always, since an
 * untaxed or exempt line is sent with a rate too. Empty values, non-numbers
 * and rates outside 0..100 are left out. Pure.
 *
 * @param array $rates percents as stored
 * @return int[]
 */
function pg_accounting_sync_tax_local_ids(array $rates)
{
    $ids = array(0 => 0);

    foreach ($rates as $rate) {
        if (($rate === null) || ($rate === '') || !is_numeric($rate) || ((float) $rate < 0) || ((float) $rate > 100)) {
            continue;
        }

        $id = pg_accounting_sync_tax_local_id($rate);
        $ids[$id] = $id;
    }

    sort($ids);

    return array_values($ids);
}

/**
 * A tax pairing id as a rate: 20000 is "20%", 8875 is "8.875%". Pure.
 *
 * @param int $local_id
 * @return string
 */
function pg_accounting_sync_tax_label($local_id)
{
    $number = rtrim(rtrim(number_format(((int) $local_id) / 1000, 3, '.', ''), '0'), '.');

    return (($number === '') ? '0' : $number) . '%';
}

/**
 * A row's columns after its driver's answer. Pure.
 *
 * The answer: ok, with the remote_id and sync_token the service gave (each
 * kept as it was when not given); or wait (seconds) with error when the row
 * is not ready (its sale not sent yet, a tax rate not matched, the accounts
 * not chosen), which is no attempt; or status and error as api_http_post()
 * answers, with retry_after (seconds) on a 429 and final for a refusal that
 * will not change (a duplicate, a currency the books lack).
 *
 * A refusal fails at once; a timeout, 408 and 5xx are tried again after
 * api_webhook_backoff() until api_webhook_max_attempts(); a 429 waits its
 * Retry-After, at least a minute, without counting an attempt.
 *
 * @param array $row     attempts
 * @param array $outcome the driver's answer
 * @param int   $now
 * @return array column => value
 */
function pg_accounting_sync_after_attempt(array $row, array $outcome, $now)
{
    $now = (int) $now;
    $attempts = min(255, (int) ($row['attempts'] ?? 0) + 1);
    $error = substr((string) ($outcome['error'] ?? ''), 0, 500);
    $values = array('updated_at' => $now);

    if (!empty($outcome['ok'])) {

        foreach (array('remote_id', 'sync_token') as $column) {
            if (isset($outcome[$column]) && is_scalar($outcome[$column])) {
                $values[$column] = substr((string) $outcome[$column], 0, 64);
            }
        }

        return $values + array('status' => 'sent', 'attempts' => $attempts, 'last_error' => '', 'sent_at' => $now);

    }

    $status = (int) ($outcome['status'] ?? 0);
    $wait = ($status === 429) ? max(60, (int) ($outcome['retry_after'] ?? 0)) : (int) ($outcome['wait'] ?? 0);

    if ($wait > 0) {
        return $values + array('status' => 'waiting', 'next_try_at' => $now + min(PG_ACCOUNTING_SYNC_LONGEST_WAIT, $wait), 'last_error' => $error);
    }

    $values += array('attempts' => $attempts, 'last_error' => ($error !== '') ? $error : 'No answer.');

    $backoff = (empty($outcome['final']) && ($attempts < api_webhook_max_attempts())
        && api_http_should_retry(array('ok' => false, 'status' => $status)))
        ? api_webhook_backoff($attempts)
        : 0;

    if ($backoff <= 0) {
        return $values + array('status' => 'failed');
    }

    return $values + array('status' => 'waiting', 'next_try_at' => $now + $backoff);
}

/**
 * Whether the service's other rows are left for the next pass after this
 * answer: it did not answer, asked to slow down or refused the connection's
 * token. A row that was not ready says nothing of the service. Pure.
 *
 * @param array $outcome the driver's answer
 * @return bool
 */
function pg_accounting_sync_holds(array $outcome)
{
    if (!empty($outcome['ok']) || ((int) ($outcome['wait'] ?? 0) > 0)) {
        return false;
    }

    return in_array((int) ($outcome['status'] ?? 0), array(0, 401, 429), true);
}

/**
 * One row through a driver: its answer, made whole, and the row's columns
 * after it. Pure when the driver is.
 *
 * @param array    $row    an accounting_sync row
 * @param callable $driver (array $row, array $target): array
 * @param array    $target service, company
 * @param int      $now
 * @return array outcome, values
 */
function pg_accounting_sync_attempt(array $row, $driver, array $target, $now)
{
    $outcome = call_user_func($driver, $row, $target);

    if (!is_array($outcome)) {
        $outcome = array('ok' => false, 'status' => 0, 'error' => 'No answer.');
    }

    return array('outcome' => $outcome, 'values' => pg_accounting_sync_after_attempt($row, $outcome, $now));
}

/**
 * The statement that writes a row due at once, or leaves the one there.
 * Values: service, company, kind, local_table, local_id, next_try_at,
 * created_at, updated_at. Pure.
 *
 * @return string
 */
function pg_accounting_sync_insert_sql()
{
    return "INSERT IGNORE INTO accounting_sync (service, company, kind, local_table, local_id, status, next_try_at, created_at, updated_at)
        VALUES (?, ?, ?, ?, ?, 'waiting', ?, ?, ?)";
}

/**
 * The statement that puts a row back in the queue, due at once with its
 * attempts cleared; it writes no row. Values: next_try_at, updated_at,
 * service, company, kind, local_table, local_id. Pure.
 *
 * @return string
 */
function pg_accounting_sync_again_sql()
{
    return "UPDATE accounting_sync SET status = 'waiting', attempts = 0, next_try_at = ?, last_error = '', updated_at = ?
        WHERE service = ? AND company = ? AND kind = ? AND local_table = ? AND local_id = ?";
}

/**
 * The statement that reads a company's due rows, oldest due first. Values:
 * service, company, now. Pure.
 *
 * @param int $limit
 * @return string
 */
function pg_accounting_sync_due_sql($limit)
{
    return "SELECT * FROM accounting_sync
        WHERE service = ? AND company = ? AND status = 'waiting' AND next_try_at <= ?
        ORDER BY next_try_at, id
        LIMIT " . max(1, (int) $limit);
}

/**
 * The statement that keeps a pairing found or made outside the queue, as
 * sent. Values: service, company, kind, local_table, local_id, remote_id,
 * sync_token, now three times, then remote_id, sync_token, now twice. Pure.
 *
 * @return string
 */
function pg_accounting_sync_pair_sql()
{
    return "INSERT INTO accounting_sync (service, company, kind, local_table, local_id, remote_id, sync_token, status, created_at, updated_at, sent_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, 'sent', ?, ?, ?)
        ON DUPLICATE KEY UPDATE remote_id = ?, sync_token = ?, status = 'sent', last_error = '', updated_at = ?, sent_at = ?";
}

/* ---------------------------------------------------------------------------
   Reading and writing
   --------------------------------------------------------------------------- */

/**
 * Whether the table is there (the 11.0.284 step has run).
 *
 * @return bool
 */
function pg_accounting_sync_ready()
{
    return pg_schema_has('accounting_sync');
}

/**
 * Whether the ERP module is the source: its invoices and receipts are sent,
 * not the orders.
 *
 * @return bool
 */
function pg_accounting_sync_erp_on()
{
    return defined('ERP_ENABLED') && ERP_ENABLED;
}

/**
 * The service and company the site books into now, null for none. Reads
 * the stored state only; no service is called.
 *
 * @return array|null service, company
 */
function pg_accounting_sync_target()
{
    if (!pg_accounting_sync_ready()) {
        return null;
    }

    require_once(PG_FUNCTIONS_DIR . '/includes/fn/xero.php');

    if (!xero_ready()) {
        return null;
    }

    $state = xero_state();

    if ($state['tenant_id'] === '') {
        return null;
    }

    return pg_accounting_sync_xero_target($state, is_array(xero_connection($state)));
}

/**
 * Whether a connection holds the queue: it was refused and must be connected
 * again. Rows are still written and wait. Pure.
 *
 * @param array|null $connection pg_connect_account()
 * @return bool
 */
function pg_accounting_sync_connection_held($connection)
{
    return is_array($connection) && ((string) ($connection['status'] ?? '') === 'reauth');
}

/**
 * Whether the site's accounting connection holds the queue now.
 *
 * @return bool
 */
function pg_accounting_sync_held()
{
    require_once(PG_FUNCTIONS_DIR . '/includes/fn/xero.php');

    return xero_ready() && pg_accounting_sync_connection_held(xero_connection());
}

/**
 * The key of an operation on a row (pg_accounting_sync_key_for()), under the
 * site's key.
 *
 * @param array  $row
 * @param string $operation
 * @return string
 */
function pg_accounting_sync_key(array $row, $operation)
{
    return pg_accounting_sync_key_for(defined('ENCRYPTION_KEY') ? (string) ENCRYPTION_KEY : '', $row, $operation);
}

/**
 * Writes a row due at once, unless the record has one.
 *
 * @param array  $target service, company
 * @param string $kind
 * @param string $local_table
 * @param int    $local_id
 * @return int 1 written, 0 there already or not written
 */
function pg_accounting_sync_write(array $target, $kind, $local_table, $local_id)
{
    $now = time();
    $result = db_q(pg_accounting_sync_insert_sql(), array(
        (string) $target['service'], (string) $target['company'], (string) $kind, (string) $local_table, (int) $local_id, $now, $now, $now));

    return $result ? max(0, (int) mysqli_affected_rows(db::$con)) : 0;
}

/**
 * Puts a record's row back in the queue, so its driver sends what changed
 * (a cancellation: a void). A record without a row is left alone.
 *
 * @param array  $target service, company
 * @param string $kind
 * @param string $local_table
 * @param int    $local_id
 * @return int rows put back
 */
function pg_accounting_sync_again(array $target, $kind, $local_table, $local_id)
{
    $now = time();
    $result = db_q(pg_accounting_sync_again_sql(), array(
        $now, $now, (string) $target['service'], (string) $target['company'], (string) $kind, (string) $local_table, (int) $local_id));

    return $result ? max(0, (int) mysqli_affected_rows(db::$con)) : 0;
}

/**
 * What a local record became in the service: its row in the company, null
 * for none.
 *
 * @param array  $target service, company
 * @param string $kind
 * @param string $local_table
 * @param int    $local_id
 * @return array|null
 */
function pg_accounting_sync_pair(array $target, $kind, $local_table, $local_id)
{
    $result = db_q("SELECT * FROM accounting_sync WHERE service = ? AND company = ? AND kind = ? AND local_table = ? AND local_id = ? LIMIT 1",
        array((string) $target['service'], (string) $target['company'], (string) $kind, (string) $local_table, (int) $local_id));
    $row = $result ? mysqli_fetch_assoc($result) : null;

    return is_array($row) ? $row : null;
}

/**
 * Keeps a pairing found or made outside the queue: a customer or an item a
 * driver found or made for a sale, a tax rate matched on the Connections
 * page. The row is written as sent with the remote id, or brought to it.
 *
 * @param array  $target     service, company
 * @param string $kind
 * @param string $local_table
 * @param int    $local_id
 * @param string $remote_id
 * @param string $sync_token
 * @return bool
 */
function pg_accounting_sync_pair_save(array $target, $kind, $local_table, $local_id, $remote_id, $sync_token = '')
{
    $now = time();
    $remote_id = substr((string) $remote_id, 0, 64);
    $sync_token = substr((string) $sync_token, 0, 64);

    return (bool) db_q(pg_accounting_sync_pair_sql(), array(
        (string) $target['service'], (string) $target['company'], (string) $kind, (string) $local_table, (int) $local_id,
        $remote_id, $sync_token, $now, $now, $now,
        $remote_id, $sync_token, $now, $now));
}

/**
 * Forgets a pairing kept outside the queue: a tax rate no longer matched on
 * the Connections page. A queued kind (sale, payment, refund) is never
 * dropped here.
 *
 * @param array  $target service, company
 * @param string $kind
 * @param string $local_table
 * @param int    $local_id
 * @return bool
 */
function pg_accounting_sync_pair_drop(array $target, $kind, $local_table, $local_id)
{
    if (!in_array((string) $kind, array('customer', 'item', 'tax'), true)) {
        return false;
    }

    return (bool) db_q("DELETE FROM accounting_sync WHERE service = ? AND company = ? AND kind = ? AND local_table = ? AND local_id = ?",
        array((string) $target['service'], (string) $target['company'], (string) $kind, (string) $local_table, (int) $local_id));
}

/**
 * The tax rates the site charges, as pairing ids
 * (pg_accounting_sync_tax_local_ids()): every tax zone's rate, every
 * product's own rate and, with "Rates by the buyer's country" on, each EU
 * member state's standard rate today.
 *
 * @return int[]
 */
function pg_accounting_sync_site_tax_rates()
{
    $rates = array();

    foreach (array("SELECT DISTINCT tax_rate FROM tax_zones", "SELECT DISTINCT tax_rate FROM products WHERE tax_rate IS NOT NULL") as $sql) {
        $result = db_q($sql);

        while ($result && ($row = mysqli_fetch_row($result))) {
            $rates[] = $row[0];
        }
    }

    require_once(PG_FUNCTIONS_DIR . '/includes/fn/tax_eu_rates.php');

    if (pg_tax_by_country_on()) {
        $today = date('Y-m-d');

        foreach (pg_eu_vat_rates() as $eu) {
            $rate = pg_eu_vat_rate($eu[0], 'standard', $today);

            if ($rate !== false) {
                $rates[] = $rate;
            }
        }
    }

    return pg_accounting_sync_tax_local_ids($rates);
}

/**
 * Sets a row's columns.
 *
 * @param int   $id
 * @param array $values column => value
 * @return bool
 */
function pg_accounting_sync_update($id, array $values)
{
    $set = array();
    $params = array();

    foreach ($values as $column => $value) {
        if (preg_match('/^[a-z_]+$/', (string) $column) === 1) {
            $set[] = $column . ' = ?';
            $params[] = $value;
        }
    }

    if (!$set) {
        return false;
    }

    $params[] = (int) $id;

    return (bool) db_q("UPDATE accounting_sync SET " . implode(', ', $set) . " WHERE id = ?", $params);
}

/* ---------------------------------------------------------------------------
   The moments that write a row
   --------------------------------------------------------------------------- */

/**
 * The sale and the payment of an order just paid, while the ERP module is
 * off (with it on, its invoice and receipts are the source). Called where an
 * order is stamped paid: pg_order_mark_paid() and a checkout that took the
 * money (pg_order_complete()). Only queued; nothing stops the caller.
 *
 * @param int $order_id
 * @return int rows written
 */
function pg_accounting_sync_order_paid($order_id)
{
    $order_id = (int) $order_id;

    if (($order_id <= 0) || pg_accounting_sync_erp_on()) {
        return 0;
    }

    $target = pg_accounting_sync_target();

    if ($target === null) {
        return 0;
    }

    return pg_accounting_sync_write($target, 'sale', 'orders', $order_id)
        + pg_accounting_sync_write($target, 'payment', 'orders', $order_id);
}

/**
 * An ERP event's row, while the ERP module is the source (erp_event()). A
 * payment or a return is written only for an invoice the service has a row
 * for, so a purchase invoice's payment and the payment or return of an
 * invoice issued before the connection write nothing. Inside a ledger
 * transaction the row rolls back with the receipt or invoice it follows.
 *
 * @param string $event
 * @param array  $payload
 * @return int rows written or put back
 */
function pg_accounting_sync_erp_event($event, array $payload)
{
    if (!pg_accounting_sync_erp_on()) {
        return 0;
    }

    $row = pg_accounting_sync_erp_event_row((string) $event, $payload);

    if ($row === null) {
        return 0;
    }

    $target = pg_accounting_sync_target();

    if ($target === null) {
        return 0;
    }

    if ($row['again']) {
        return pg_accounting_sync_again($target, $row['kind'], $row['local_table'], $row['local_id']);
    }

    if (($row['invoice_id'] > 0)
        && (pg_accounting_sync_pair($target, 'sale', 'erp_invoices', $row['invoice_id']) === null)
        && (pg_accounting_sync_pair($target, 'refund', 'erp_invoices', $row['invoice_id']) === null)) {
        return 0;
    }

    return pg_accounting_sync_write($target, $row['kind'], $row['local_table'], $row['local_id']);
}

/**
 * The general job's refund scan, while the ERP module is off: each
 * order_refunds row of an order whose sale the service has gets a refund
 * row, whichever screen recorded it (Refund on View Order, a return's
 * refund, a refund as store credit). Reads on from the last refund it read,
 * kept in a file; without the file it reads from the first, and the unique
 * key keeps a refund to one row.
 *
 * @return int rows written
 */
function pg_accounting_sync_queue_refunds()
{
    if (pg_accounting_sync_erp_on()) {
        return 0;
    }

    $target = pg_accounting_sync_target();

    if ($target === null) {
        return 0;
    }

    $path = PG_FUNCTIONS_DIR . '/data/temp/accounting_sync_refunds.txt';
    $after = is_file($path) ? (int) @file_get_contents($path) : 0;
    $refunds = array();
    $result = db_q("SELECT id, order_id, amount_cents FROM order_refunds WHERE id > ? ORDER BY id LIMIT " . PG_ACCOUNTING_SYNC_REFUND_ROWS, array($after));

    while ($result && ($row = mysqli_fetch_assoc($result))) {
        $refunds[] = $row;
    }

    if (!$refunds) {
        return 0;
    }

    $orders = array_values(array_unique(array_map('intval', array_column($refunds, 'order_id'))));
    $sold = array();
    $result = db_q("SELECT local_id FROM accounting_sync
        WHERE service = ? AND company = ? AND kind = 'sale' AND local_table = 'orders'
            AND local_id IN (" . implode(', ', array_fill(0, count($orders), '?')) . ")",
        array_merge(array((string) $target['service'], (string) $target['company']), $orders));

    if (!$result) {
        return 0;
    }

    while ($row = mysqli_fetch_row($result)) {
        $sold[] = (int) $row[0];
    }

    $written = 0;

    foreach (pg_accounting_sync_refunds_to_queue($refunds, $sold) as $refund_id) {
        $written += pg_accounting_sync_write($target, 'refund', 'order_refunds', $refund_id);
    }

    @file_put_contents($path, (string) max(array_map('intval', array_column($refunds, 'id'))), LOCK_EX);

    return $written;
}

/* ---------------------------------------------------------------------------
   The general job's pass
   --------------------------------------------------------------------------- */

/**
 * A service's driver function, '' while it has none. Its file,
 * includes/fn/accounting_sync_<service>.php, is loaded when present.
 *
 * @param string $service
 * @return string
 */
function pg_accounting_sync_driver($service)
{
    $service = (string) $service;

    if (preg_match('/^[a-z0-9_]+$/', $service) !== 1) {
        return '';
    }

    $file = PG_FUNCTIONS_DIR . '/includes/fn/accounting_sync_' . $service . '.php';

    if (is_file($file)) {
        require_once($file);
    }

    $name = 'pg_accounting_sync_send_' . $service;

    return function_exists($name) ? $name : '';
}

/**
 * The general job's pass: the due rows of the company the site books into,
 * at most the service's pace and within the time budget. A row is claimed
 * before its call, so two jobs never send it twice. The pass stops when the
 * service did not answer, asked to slow down or refused the token; a
 * service without a driver, or a connection marked to be connected again,
 * leaves its rows waiting.
 *
 * @param int $budget seconds
 * @return int rows sent
 */
function pg_accounting_sync_dispatch($budget)
{
    $target = pg_accounting_sync_target();

    if ($target === null) {
        return 0;
    }

    $driver = pg_accounting_sync_driver($target['service']);

    // A connection that must be connected again holds every row.
    if (($driver === '') || pg_accounting_sync_held()) {
        return 0;
    }

    require_once(PG_FUNCTIONS_DIR . '/includes/api/outbound/webhooks.php');

    $started = microtime(true);
    $now = time();
    $rows = array();
    $result = db_q(pg_accounting_sync_due_sql(pg_accounting_sync_pace($target['service'])),
        array((string) $target['service'], (string) $target['company'], $now));

    while ($result && ($row = mysqli_fetch_assoc($result))) {
        $rows[] = $row;
    }

    $sent = 0;

    foreach ($rows as $row) {

        if ((microtime(true) - $started) >= $budget) {
            break;
        }

        // The claim: a row another pass took is left to it.
        $claimed = db_q("UPDATE accounting_sync SET next_try_at = ? WHERE id = ? AND status = 'waiting' AND next_try_at = ?",
            array($now + PG_ACCOUNTING_SYNC_CLAIM, (int) $row['id'], (int) $row['next_try_at']));

        if (!$claimed || (mysqli_affected_rows(db::$con) < 1)) {
            continue;
        }

        $attempt = pg_accounting_sync_attempt($row, $driver, $target, time());
        pg_accounting_sync_update((int) $row['id'], $attempt['values']);

        if (!empty($attempt['outcome']['ok'])) {
            $sent++;
        }

        if (pg_accounting_sync_holds($attempt['outcome'])) {
            break;
        }

    }

    return $sent;
}
