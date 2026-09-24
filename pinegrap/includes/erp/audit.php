<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * ERP - the audit trail.
 *
 * Who did what in the books, kept in erp_audit_log (4.90) for as long as the
 * store keeps its books: the site's own log is emptied after six months,
 * which is shorter than any period an accountant or a tax audit looks back
 * over. Two things are written here, and nothing else writes the table:
 *
 *   - every event the module announces (erp_event()), with the record's
 *     number and amount as they were at that moment; written on the same
 *     connection as the document, so a document that rolls back leaves no
 *     line behind;
 *   - every line an ERP screen, the ERP API or an ERP job writes to the site
 *     log (log_activity()), in the words it was written in.
 *
 * Lines written by the same request share a request_id, which is how the
 * screen shows one action as one entry. Nothing here may stop the action it
 * records: a line that cannot be written is left out.
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
 * Whether the 4.90 table is there.
 *
 * @return bool
 */
function erp_audit_ready()
{
    static $ready = null;

    if ($ready === null) {
        $ready = function_exists('waf_table_has_column') && waf_table_has_column('erp_audit_log', 'request_id');
    }

    return $ready;
}

/**
 * One id for every line this request writes.
 *
 * @return string  16 hex characters
 */
function erp_audit_request_id()
{
    static $id = null;

    if ($id === null) {
        try {
            $id = bin2hex(random_bytes(8));
        } catch (Exception $e) {
            $id = substr(md5(uniqid('', true) . mt_rand()), 0, 16);
        }
    }

    return $id;
}

/**
 * Who is acting: an operator in the panel, an application through the API,
 * or a scheduled job.
 *
 * @return array  source, user_id, username
 */
function erp_audit_actor()
{
    if (defined('PG_API_ENTRY') && function_exists('api_current_app')) {
        $app = api_current_app();

        if (is_array($app)) {
            return array(
                'source' => 'api',
                'user_id' => (int) ($app['owner']['id'] ?? ($app['owner_user_id'] ?? 0)),
                'username' => mb_substr((string) ($app['name'] ?? ''), 0, 100),
            );
        }
    }

    $user_id = defined('USER_ID') ? (int) USER_ID : 0;
    $username = defined('USER_USERNAME') ? (string) USER_USERNAME : '';

    if (($username === '') && !empty($_SESSION['sessionusername'])) {
        $username = (string) $_SESSION['sessionusername'];
    }

    if ((PHP_SAPI === 'cli') || (($user_id <= 0) && ($username === ''))) {
        return array('source' => 'job', 'user_id' => 0, 'username' => '');
    }

    return array('source' => 'panel', 'user_id' => $user_id, 'username' => mb_substr($username, 0, 100));
}

/**
 * Write one line.
 *
 * @param array $line  event, object_type, object_id, label, amount, currency, description, detail, username
 * @return bool
 */
function erp_audit_write($line)
{
    if (!erp_audit_ready() || !class_exists('db') || !db::$con) {
        return false;
    }

    $actor = erp_audit_actor();
    $username = (isset($line['username']) && ((string) $line['username'] !== '')) ? mb_substr((string) $line['username'], 0, 100) : $actor['username'];
    $ip = mb_substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
    $detail = isset($line['detail']) ? json_encode($line['detail'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null;

    $query = "INSERT INTO erp_audit_log
            (created_at, request_id, source, user_id, username, ip, event, object_type, object_id, label, amount, currency, description, detail)
        VALUES (
            UNIX_TIMESTAMP(),
            '" . escape(erp_audit_request_id()) . "',
            '" . escape($actor['source']) . "',
            '" . (int) $actor['user_id'] . "',
            '" . escape($username) . "',
            '" . escape($ip) . "',
            '" . escape(mb_substr((string) ($line['event'] ?? ''), 0, 64)) . "',
            '" . escape(mb_substr((string) ($line['object_type'] ?? ''), 0, 20)) . "',
            '" . max(0, (int) ($line['object_id'] ?? 0)) . "',
            '" . escape(mb_substr((string) ($line['label'] ?? ''), 0, 255)) . "',
            '" . (int) ($line['amount'] ?? 0) . "',
            '" . escape(mb_substr(strtoupper((string) ($line['currency'] ?? '')), 0, 3)) . "',
            " . ((isset($line['description']) && ((string) $line['description'] !== '')) ? "'" . escape(mb_substr((string) $line['description'], 0, 5000)) . "'" : "NULL") . ",
            " . (is_string($detail) ? "'" . escape($detail) . "'" : "NULL") . "
        )";

    return (bool) @mysqli_query(db::$con, $query);
}

/**
 * The line for an announced event, from its payload.
 *
 * @param string $event
 * @param array  $payload
 * @return bool
 */
function erp_audit_event($event, $payload)
{
    $event = (string) $event;
    $payload = (array) $payload;
    $parts = explode('.', $event);
    $type = $parts[1] ?? '';

    $label = '';
    $amount = 0;

    if (($type === 'invoice') || ($type === 'waybill')) {
        $label = (string) ($payload['number'] ?? '');
        $amount = (int) ($payload['grand_total'] ?? 0);
    } elseif ($type === 'receipt') {
        $label = '#' . (int) ($payload['id'] ?? 0);
        $amount = (int) ($payload['amount'] ?? 0);
    } elseif ($type === 'account') {
        $label = (string) ($payload['title'] ?? '');
    } elseif ($type === 'expense') {
        $label = trim((string) ($payload['supplier'] ?? '') . ((trim((string) ($payload['document_no'] ?? '')) !== '') ? ' · ' . $payload['document_no'] : ''));
        $amount = (int) ($payload['total_amount'] ?? 0);
    }

    return erp_audit_write(array(
        'event' => $event,
        'object_type' => $type,
        'object_id' => (int) ($payload['id'] ?? 0),
        'label' => $label,
        'amount' => $amount,
        'currency' => (string) ($payload['currency'] ?? ''),
        'detail' => $payload,
    ));
}

/**
 * The site log's hook: keep the line when the ERP wrote it.
 *
 * Whether it did is read from the file that called log_activity(): an ERP
 * screen (erp_*.php, add_erp_*.php, edit_erp_*.php and the like) or a file
 * under includes/erp/.
 *
 * @param string $description  The line, in the words it was written in
 * @param string $username
 * @return bool
 */
function erp_audit_log_line($description, $username = '')
{
    $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
    $file = str_replace('\\', '/', (string) ($trace[1]['file'] ?? ''));

    if (($file === '') || !erp_audit_is_erp_file($file)) {
        return false;
    }

    return erp_audit_write(array(
        'event' => 'log',
        'description' => (string) $description,
        'username' => (string) $username,
    ));
}

/**
 * @param string $file  A path with forward slashes
 * @return bool
 */
function erp_audit_is_erp_file($file)
{
    if (strpos($file, '/includes/erp/') !== false) {
        return true;
    }

    return (bool) preg_match('~(^|_)erp(_|\.)~', basename($file));
}

/**
 * The kinds of record the trail names, for the filter and the links.
 *
 * @return array  object_type => [label, url prefix]
 */
function erp_audit_object_types()
{
    return array(
        'invoice' => array(lang('Invoices'), 'edit_erp_invoice.php?id='),
        'receipt' => array(lang('Receipts and payments'), 'erp_receipt.php?id='),
        'account' => array(lang('Accounts'), 'edit_erp_account.php?id='),
        'waybill' => array(lang('Delivery Notes'), 'edit_erp_waybill.php?id='),
        'expense' => array(lang('Expenses'), 'edit_erp_expense.php?id='),
    );
}

/**
 * What an event line says, in the panel's language.
 *
 * @param array $row  An erp_audit_log row
 * @return string
 */
function erp_audit_event_text($row)
{
    $event = (string) $row['event'];

    if ($event === 'erp.receipt.created') {
        $detail = json_decode((string) $row['detail'], true);

        return ((string) ($detail['direction'] ?? '') === 'payment') ? lang('Payment recorded') : lang('Collection recorded');
    }

    $texts = array(
        'erp.invoice.created' => lang('Invoice issued'),
        'erp.invoice.paid' => lang('Invoice paid in full'),
        'erp.invoice.cancelled' => lang('Invoice cancelled'),
        'erp.invoice.edoc_changed' => lang('E-document status changed'),
        'erp.receipt.cancelled' => lang('Receipt cancelled'),
        'erp.account.created' => lang('Account opened'),
        'erp.waybill.created' => lang('Delivery note issued'),
        'erp.expense.created' => lang('Expense recorded'),
        'erp.expense.paid' => lang('Expense paid'),
        'erp.expense.cancelled' => lang('Expense cancelled'),
    );

    return $texts[$event] ?? $event;
}

/**
 * The WHERE clause for the screen's filters.
 *
 * @param array $filters  from, to (Y-m-d), user, type, id, search
 * @return string
 */
function erp_audit_where($filters)
{
    $where = array('1 = 1');

    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($filters['from'] ?? ''))) {
        $where[] = "created_at >= UNIX_TIMESTAMP('" . escape($filters['from']) . " 00:00:00')";
    }

    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($filters['to'] ?? ''))) {
        $where[] = "created_at < UNIX_TIMESTAMP(DATE_ADD('" . escape($filters['to']) . "', INTERVAL 1 DAY))";
    }

    if ((string) ($filters['user'] ?? '') !== '') {
        if ($filters['user'] === '@job') {
            $where[] = "source = 'job'";
        } else {
            $where[] = "username = '" . escape((string) $filters['user']) . "'";
        }
    }

    $types = erp_audit_object_types();
    $type = (string) ($filters['type'] ?? '');

    if (isset($types[$type])) {
        $where[] = "object_type = '" . escape($type) . "'";

        if ((int) ($filters['id'] ?? 0) > 0) {
            $where[] = "object_id = '" . (int) $filters['id'] . "'";
        }
    } elseif ($type === 'log') {
        $where[] = "event = 'log'";
    }

    $search = trim((string) ($filters['search'] ?? ''));

    if ($search !== '') {
        $like = "'%" . escape(addcslashes($search, '%_\\')) . "%'";
        $where[] = "(label LIKE " . $like . " OR description LIKE " . $like . " OR username LIKE " . $like . " OR ip LIKE " . $like . ")";
    }

    return implode(' AND ', $where);
}

/**
 * One page of the trail, one entry per request, newest first.
 *
 * A filter picks the requests that have a matching line; each is then shown
 * with all of its lines, so an invoice found by its number still shows the
 * log line its screen wrote.
 *
 * @param array $filters
 * @param int   $before  Show requests whose last line is older than this id
 * @param int   $limit
 * @return array ['entries' => [['request_id', 'rows']], 'next' => int]
 */
function erp_audit_entries($filters, $before = 0, $limit = 100)
{
    if (!erp_audit_ready()) {
        return array('entries' => array(), 'next' => 0);
    }

    $limit = max(1, min(500, (int) $limit));
    $groups = db_items(
        "SELECT request_id, MAX(id) AS last_id
        FROM erp_audit_log
        WHERE " . erp_audit_where($filters) . "
        GROUP BY request_id
        " . (($before > 0) ? "HAVING last_id < '" . (int) $before . "'" : "") . "
        ORDER BY last_id DESC
        LIMIT " . ($limit + 1)
    );

    $next = 0;

    if (count($groups) > $limit) {
        $groups = array_slice($groups, 0, $limit);
        $next = (int) $groups[$limit - 1]['last_id'];
    }

    if (empty($groups)) {
        return array('entries' => array(), 'next' => 0);
    }

    $ids = array();
    foreach ($groups as $group) {
        $ids[] = "'" . escape((string) $group['request_id']) . "'";
    }

    $by_request = array();
    foreach ((array) db_items("SELECT * FROM erp_audit_log WHERE request_id IN (" . implode(', ', $ids) . ") ORDER BY id ASC") as $row) {
        $by_request[(string) $row['request_id']][] = $row;
    }

    $entries = array();
    foreach ($groups as $group) {
        if (!empty($by_request[(string) $group['request_id']])) {
            $entries[] = array('request_id' => (string) $group['request_id'], 'rows' => $by_request[(string) $group['request_id']]);
        }
    }

    return array('entries' => $entries, 'next' => $next);
}

/**
 * The people and applications the trail names, for the filter.
 *
 * @return array  username => source
 */
function erp_audit_people()
{
    if (!erp_audit_ready()) {
        return array();
    }

    $people = array();
    foreach ((array) db_items("SELECT username, MAX(source) AS source FROM erp_audit_log WHERE username <> '' GROUP BY username ORDER BY username ASC LIMIT 300") as $row) {
        $people[(string) $row['username']] = (string) $row['source'];
    }

    return $people;
}

/**
 * The trail as CSV, line by line, for the filters given; at most 50,000 lines.
 *
 * @param array $filters
 * @return void
 */
function erp_audit_csv($filters)
{
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, array(
        lang('Date'), lang('Source'), lang('User'), lang('IP address'), lang('Action'),
        lang('Record'), lang('Record ID'), lang('Number or name'), lang('Amount'), lang('Currency'), lang('Details'),
    ), ';');

    $sources = erp_audit_sources();
    $types = erp_audit_object_types();
    $last = 0;
    $written = 0;

    // In slices, so a long trail is not held in memory at once.
    while ($written < 50000) {
        $rows = db_items("SELECT * FROM erp_audit_log WHERE " . erp_audit_where($filters) . (($last > 0) ? " AND id < '" . $last . "'" : "") . " ORDER BY id DESC LIMIT 1000");

        if (empty($rows)) {
            break;
        }

        foreach ($rows as $row) {
            $is_log = ((string) $row['event'] === 'log');
            fputcsv($out, array(
                date('Y-m-d H:i:s', (int) $row['created_at']),
                $sources[(string) $row['source']] ?? (string) $row['source'],
                (string) $row['username'],
                (string) $row['ip'],
                $is_log ? (string) $row['description'] : erp_audit_event_text($row),
                $is_log ? '' : ($types[(string) $row['object_type']][0] ?? (string) $row['object_type']),
                $is_log ? '' : (int) $row['object_id'],
                (string) $row['label'],
                $is_log ? '' : number_format(((int) $row['amount']) / 100, 2, '.', ''),
                (string) $row['currency'],
                $is_log ? '' : (string) $row['detail'],
            ), ';');
            $last = (int) $row['id'];
            $written++;
        }
    }

    fclose($out);
}

/**
 * @return array  source => label
 */
function erp_audit_sources()
{
    return array(
        'panel' => lang('Panel'),
        'api' => lang('API'),
        'job' => lang('Scheduled job'),
    );
}
