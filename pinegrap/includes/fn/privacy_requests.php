<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The Privacy centre's request box: a person's requests about their personal
 * data (information, correction, erasure, objection, other) in the
 * privacy_requests table, with the 30 days from the proof of their address,
 * the reminders at 7 and 2 days, "Prepare the answer" (the export or the
 * erase dry run, the e-mail, the erasure) and the 7-day sweep of requests
 * never proven. Each step of a request is an audit_log line
 * (entity_type = 'privacy_request') that names no person.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/**
 * The clock and the list's bounds. Pure.
 *
 * @return array  days (to answer), sweep_days (an unproven row is kept),
 *                remind (days left a reminder goes at), amber, red (days
 *                left the due pill turns), list (rows drawn), batch (sweep)
 */
function pg_privacy_request_limits()
{
    return array('days' => 30, 'sweep_days' => 7, 'remind' => array(7, 2), 'amber' => 7, 'red' => 2, 'list' => 500, 'batch' => 500);
}

/**
 * The kinds of request (P10). Pure.
 *
 * @return string[]
 */
function pg_privacy_request_kinds()
{
    return array('information', 'correction', 'erasure', 'objection', 'other');
}

/**
 * The words of the kinds, statuses, sources and results.
 *
 * @return array  kinds, statuses, sources, results: key => label
 */
function pg_privacy_request_labels()
{
    return array(
        'kinds' => array(
            'information' => lang('Information'),
            'correction'  => lang('Correction'),
            'erasure'     => lang('Erasure'),
            'objection'   => lang('Objection'),
            'other'       => lang('Other'),
        ),
        'statuses' => array(
            'open'     => lang('Open'),
            'answered' => lang('Answered'),
            'refused'  => lang('Refused'),
        ),
        'sources' => array(
            'account' => lang('My Account'),
            'staff'   => lang('Staff'),
            'form'    => lang('Public form'),
        ),
        'results' => array(
            'sent'    => lang('Answer sent'),
            'erased'  => lang('Erased'),
            'refused' => lang('Refused'),
        ),
    );
}

/**
 * When a request must be answered: 30 days from the proof. Pure.
 *
 * @param int $received_at  0 for a request not proven
 * @return int  0 when not proven
 */
function pg_privacy_request_due_at($received_at)
{
    $limits = pg_privacy_request_limits();

    return ((int) $received_at > 0) ? (int) $received_at + ($limits['days'] * 86400) : 0;
}

/**
 * When a request never proven is swept; kept in due_at while the row is
 * unproven, as it has no 30 days yet. Pure.
 *
 * @param int $created
 * @return int
 */
function pg_privacy_request_sweep_at($created)
{
    $limits = pg_privacy_request_limits();

    return (int) $created + ($limits['sweep_days'] * 86400);
}

/**
 * Whole days left until the due moment, a part day counted; negative when
 * overdue. Pure.
 *
 * @param int $due_at
 * @param int $now
 * @return int
 */
function pg_privacy_request_days_left($due_at, $now)
{
    return (int) ceil(((int) $due_at - (int) $now) / 86400);
}

/**
 * The due pill's tone: grey, amber from 7 days, red from 2 days and when
 * overdue. Pure.
 *
 * @param int $days_left
 * @return string  a look A tone
 */
function pg_privacy_request_due_tone($days_left)
{
    $limits = pg_privacy_request_limits();

    if ((int) $days_left <= $limits['red']) {
        return 'rose';
    }

    return ((int) $days_left <= $limits['amber']) ? 'amber' : 'gray';
}

/**
 * The reminder an open request is owed now: 7 or 2 (days left), each once;
 * reminded holds the last one sent. Pure.
 *
 * @param array $row  status, due_at, reminded
 * @param int   $now
 * @return int  0 for none
 */
function pg_privacy_request_reminder_stage(array $row, $now)
{
    if ((($row['status'] ?? '') !== 'open') || ((int) ($row['due_at'] ?? 0) <= 0) || (array_key_exists('received_at', $row) && ((int) $row['received_at'] <= 0))) {
        return 0;
    }

    $left = pg_privacy_request_days_left($row['due_at'], $now);
    $reminded = (int) ($row['reminded'] ?? 0);

    foreach (array_reverse(pg_privacy_request_limits()['remind']) as $stage) {
        if ($left <= $stage) {
            return (($reminded === 0) || ($reminded > $stage)) ? $stage : 0;
        }
    }

    return 0;
}

/**
 * The statement that sweeps requests never proven past their 7 days. Pure.
 *
 * @param int $now
 * @param int $batch
 * @return string
 */
function pg_privacy_request_sweep_query($now, $batch)
{
    return "DELETE FROM privacy_requests WHERE (status = 'unproven') AND (received_at = 0) AND (due_at < '" . (int) $now . "') LIMIT " . max(1, (int) $batch);
}

/**
 * The condition of a list view: open, ending (open, within the amber
 * days), answered (answered or refused), all; an unproven row is in none.
 * Pure.
 *
 * @param string $view
 * @param int    $now
 * @return string
 */
function pg_privacy_request_view_where($view, $now = 0)
{
    // An unproven row keeps its sweep moment in due_at: only a proven row
    // (received_at above 0) is ever read as a request.
    $proven = "(received_at > 0) AND ";

    if ($view === 'ending') {
        return $proven . "(status = 'open') AND (due_at < '" . ((int) $now + (pg_privacy_request_limits()['amber'] * 86400)) . "')";
    }

    if ($view === 'answered') {
        return $proven . "(status IN ('answered', 'refused'))";
    }

    return $proven . (($view === 'all') ? "(status <> 'unproven')" : "(status = 'open')");
}

/**
 * A request typed in by staff: open at once, its 30 days from now. Pure.
 *
 * @param array $input       kind, email, name, message, received_on (Y-m-d)
 * @param int   $now
 * @param int   $operator_id
 * @return array  errors (field => true), row
 */
function pg_privacy_request_from_input(array $input, $now, $operator_id)
{
    $text = function ($key, $length) use ($input) {
        $value = isset($input[$key]) && is_string($input[$key]) ? trim($input[$key]) : '';

        return mb_substr(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]+/u', '', $value), 0, $length, 'UTF-8');
    };

    $kind = $text('kind', 16);
    $email = pg_privacy_normalize_email($text('email', 100));
    $errors = array();

    if (!in_array($kind, pg_privacy_request_kinds(), true)) {
        $errors['kind'] = true;
    }

    if (!is_string($email) || ($email === '') || (strlen($email) > 100)) {
        $errors['email'] = true;
    }

    // The day the request came (a letter, a call): today by default, never
    // in the future; its 30 days start on that day.
    $received = (int) $now;
    $day = $text('received_on', 10);

    if (($day !== '') && ($day !== date('Y-m-d', (int) $now))) {
        $parts = (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $day, $match) === 1) && checkdate((int) $match[2], (int) $match[3], (int) $match[1]);
        $received = $parts ? mktime(0, 0, 0, (int) $match[2], (int) $match[3], (int) $match[1]) : 0;

        if (!$parts || ($received > (int) $now)) {
            $errors['received_on'] = true;
        }
    }

    return array('errors' => $errors, 'row' => array(
        'kind'        => $kind,
        'status'      => 'open',
        'source'      => 'staff',
        'email'       => is_string($email) ? $email : '',
        'name'        => $text('name', 100),
        'message'     => $text('message', 10000),
        'created_by'  => (int) $operator_id,
        'received_at' => $received,
        'due_at'      => pg_privacy_request_due_at($received),
    ));
}

/**
 * The fields of a request an audit line keeps: no address, name or text.
 * Pure.
 *
 * @param array $row
 * @return array
 */
function pg_privacy_request_audit_view(array $row)
{
    $view = array();

    foreach (array('kind', 'status', 'source', 'result', 'assigned_user_id', 'verified_at', 'due_at') as $key) {
        if (array_key_exists($key, $row)) {
            $view[$key] = is_numeric($row[$key]) ? (int) $row[$key] : (string) $row[$key];
        }
    }

    return $view;
}

/**
 * Whether the request box's table is there (a migration step adds it).
 *
 * @return bool
 */
function pg_privacy_requests_ready()
{
    return pg_schema_has('privacy_requests');
}

/**
 * One request.
 *
 * @param int $id
 * @return array|null
 */
function pg_privacy_request_get($id)
{
    if (((int) $id <= 0) || !pg_privacy_requests_ready()) {
        return null;
    }

    $row = db_item("SELECT * FROM privacy_requests WHERE (id = '" . (int) $id . "') AND (received_at > 0) AND (status <> 'unproven')");

    return is_array($row) ? $row : null;
}

/**
 * A view of the box, soonest due first; the name of who handles each.
 *
 * @param string $view  open | ending | answered | all
 * @return array
 */
function pg_privacy_requests_list($view)
{
    if (!pg_privacy_requests_ready()) {
        return array();
    }

    return db_items("SELECT r.*, u.user_username AS assigned_name
        FROM privacy_requests r
        LEFT JOIN user u ON (r.assigned_user_id > 0) AND (u.user_id = r.assigned_user_id)
        WHERE " . preg_replace('/\((status|due_at|received_at) /', '(r.$1 ', pg_privacy_request_view_where($view, time())) . "
        ORDER BY (r.status = 'open') DESC, r.due_at ASC, r.id DESC
        LIMIT " . (int) pg_privacy_request_limits()['list']);
}

/**
 * The rows the Requests count reads: open and proven. Pure.
 *
 * @return string
 */
function pg_privacy_request_counts_where()
{
    return "(received_at > 0) AND (status = 'open')";
}

/**
 * The rows the reminders look at: open, proven, within the amber days and
 * not yet told of the last stage. Pure.
 *
 * @param int $now
 * @return string
 */
function pg_privacy_request_remind_where($now)
{
    $limits = pg_privacy_request_limits();

    return "(r.received_at > 0) AND (r.status = 'open') AND (r.due_at < '" . ((int) $now + ($limits['amber'] * 86400)) . "') AND ((r.reminded = 0) OR (r.reminded > '" . (int) $limits['red'] . "'))";
}

/**
 * The open requests, and those of them ending within the amber days.
 *
 * @param int|null $now
 * @return array  open, ending
 */
function pg_privacy_requests_counts($now = null)
{
    if (!pg_privacy_requests_ready()) {
        return array('open' => 0, 'ending' => 0);
    }

    $now = ($now === null) ? time() : (int) $now;
    $row = db_item("SELECT COUNT(*) AS open_count, SUM(due_at < '" . ($now + (pg_privacy_request_limits()['amber'] * 86400)) . "') AS ending_count
        FROM privacy_requests WHERE " . pg_privacy_request_counts_where());

    return array('open' => (int) ($row['open_count'] ?? 0), 'ending' => (int) ($row['ending_count'] ?? 0));
}

/**
 * The request's steps, newest first.
 *
 * @param int $id
 * @return array
 */
function pg_privacy_request_history($id)
{
    if (!pg_schema_has('audit_log')) {
        return array();
    }

    return db_items("SELECT created_at, action, actor_name, after_json FROM audit_log
        WHERE (entity_type = 'privacy_request') AND (entity_id = '" . (int) $id . "')
        ORDER BY created_at DESC, id DESC LIMIT 200");
}

/**
 * The people who may handle a request: manager and up.
 *
 * @return array  user_id => user name
 */
function pg_privacy_request_handlers()
{
    $handlers = array();

    foreach (db_items("SELECT user_id, user_username FROM user WHERE user_role <= 2 ORDER BY user_username") as $row) {
        $handlers[(int) $row['user_id']] = (string) $row['user_username'];
    }

    return $handlers;
}

/**
 * Add a request typed in by staff.
 *
 * @param array $input     see pg_privacy_request_from_input()
 * @param array $operator  id
 * @return array  errors, id
 */
function pg_privacy_request_add(array $input, array $operator)
{
    $out = pg_privacy_request_from_input($input, time(), (int) ($operator['id'] ?? 0));

    if ($out['errors'] || !pg_privacy_requests_ready()) {
        return array('errors' => $out['errors'] ?: array('table' => true), 'id' => 0);
    }

    $row = $out['row'];
    $found = pg_privacy_export_find(array('email' => $row['email']));
    $row['contact_id'] = (int) (reset($found['person']['contact_ids']) ?: 0);
    $row['user_id'] = (int) (reset($found['person']['user_ids']) ?: 0);

    $columns = array_keys($row);

    if (db_q("INSERT INTO privacy_requests (" . implode(', ', $columns) . ") VALUES (" . implode(', ', array_fill(0, count($columns), '?')) . ")", array_values($row)) === false) {
        return array('errors' => array('table' => true), 'id' => 0);
    }

    $id = (int) mysqli_insert_id(db::$con);
    pg_audit('create', 'privacy_request', $id, array(), pg_privacy_request_audit_view($row), '');

    return array('errors' => array(), 'id' => $id);
}

/**
 * Write a request's changed columns and its audit line.
 *
 * @param array  $request  the row before
 * @param array  $changes  column => value
 * @param string $action   the audit action
 * @return bool
 */
function _pg_privacy_request_write(array $request, array $changes, $action)
{
    $sets = array();

    foreach (array_keys($changes) as $column) {
        $sets[] = '`' . $column . '` = ?';
    }

    if (!$sets || (db_q("UPDATE privacy_requests SET " . implode(', ', $sets) . " WHERE id = ?", array_merge(array_values($changes), array((int) $request['id']))) === false)) {
        return false;
    }

    pg_audit($action, 'privacy_request', (int) $request['id'], pg_privacy_request_audit_view($request), pg_privacy_request_audit_view(array_merge($request, $changes)), '');

    return true;
}

/**
 * Save who handles an open request and whether staff checked who asks.
 *
 * @param array $request
 * @param array $input     assigned_user_id, verified (bool)
 * @return bool
 */
function pg_privacy_request_save(array $request, array $input)
{
    if (($request['status'] ?? '') !== 'open') {
        return false;
    }

    $assigned = (int) ($input['assigned_user_id'] ?? 0);
    $changes = array(
        'assigned_user_id' => isset(pg_privacy_request_handlers()[$assigned]) ? $assigned : 0,
        'verified_at'      => empty($input['verified']) ? 0 : (((int) $request['verified_at'] > 0) ? (int) $request['verified_at'] : time()),
    );

    if (((int) $request['assigned_user_id'] === $changes['assigned_user_id']) && ((int) $request['verified_at'] === $changes['verified_at'])) {
        return true;
    }

    return _pg_privacy_request_write($request, $changes, 'update');
}

/**
 * Send the answer by e-mail and close the request; for an erasure, erase
 * the person after the mail has gone (the address is gone after it), then
 * keep the answer with the anonymised request. Refusing sends the reason.
 *
 * @param array  $request
 * @param string $text      the answer, plain text
 * @param array  $operator  role, id
 * @param bool   $refuse
 * @return string  ok | open | unverified | empty | mail | refused (the
 *                 erasure was refused)
 */
function pg_privacy_request_send(array $request, $text, array $operator, $refuse = false)
{
    $text = trim((string) $text);

    if (($request['status'] ?? '') !== 'open') {
        return 'open';
    }

    if ($text === '') {
        return 'empty';
    }

    if (!$refuse && ((int) $request['verified_at'] <= 0)) {
        return 'unverified';
    }

    $erase = !$refuse && ($request['kind'] === 'erasure');

    if ($erase) {
        $dry = pg_privacy_erase(array('email' => (string) $request['email'], 'contact_id' => (int) $request['contact_id'], 'user_id' => (int) $request['user_id']), $operator, false);

        if (!in_array($dry['status'], array('ok', 'not_found', 'empty'), true)) {
            return 'refused';
        }
    }

    $site = (defined('ORGANIZATION_NAME') && (trim((string) ORGANIZATION_NAME) !== '')) ? (string) ORGANIZATION_NAME : (string) HOSTNAME_SETTING;

    $sent = (bool) email(array(
        'to'                 => (string) $request['email'],
        'from_name'          => $site,
        'from_email_address' => EMAIL_ADDRESS,
        'subject'            => lang(array('string' => '{var:1}: your request about your personal data', 'vars' => array($site))),
        'format'             => 'plain_text',
        'body'               => $text,
        'type'               => 'system',
    ));

    if (!$sent) {
        return 'mail';
    }

    if ($erase && ($dry['status'] === 'ok')) {
        pg_privacy_erase(array('email' => (string) $request['email'], 'contact_id' => (int) $request['contact_id'], 'user_id' => (int) $request['user_id']), $operator, true);
    }

    _pg_privacy_request_write($request, array(
        'status'    => $refuse ? 'refused' : 'answered',
        'result'    => $refuse ? 'refused' : ($erase ? 'erased' : 'sent'),
        'answer'    => mb_substr($text, 0, 60000, 'UTF-8'),
        'closed_at' => time(),
    ), $refuse ? 'refuse' : 'answer');

    return 'ok';
}

/**
 * Remove the requests never proven past their 7 days.
 *
 * @param int|null $now
 * @return int  rows removed
 */
function pg_privacy_requests_sweep($now = null)
{
    if (!pg_privacy_requests_ready()) {
        return 0;
    }

    $now = ($now === null) ? time() : (int) $now;

    return (db(pg_privacy_request_sweep_query($now, pg_privacy_request_limits()['batch'])) === false) ? 0 : max(0, (int) mysqli_affected_rows(db::$con));
}

/**
 * Tell the panel at 7 and 2 days left: a bell line for managers and up, and
 * an e-mail to who handles the request or, with nobody, the site's address.
 *
 * @param int|null $now
 * @return int  reminders sent
 */
function pg_privacy_requests_remind($now = null)
{
    if (!pg_privacy_requests_ready()) {
        return 0;
    }

    $now = ($now === null) ? time() : (int) $now;
    $limits = pg_privacy_request_limits();
    $rows = db_items("SELECT r.id, r.kind, r.status, r.received_at, r.due_at, r.reminded, u.user_email
        FROM privacy_requests r
        LEFT JOIN user u ON (r.assigned_user_id > 0) AND (u.user_id = r.assigned_user_id)
        WHERE " . pg_privacy_request_remind_where($now) . "
        LIMIT 100");
    $sent = 0;

    foreach ($rows as $row) {
        $stage = pg_privacy_request_reminder_stage($row, $now);

        if ($stage === 0) {
            continue;
        }

        db("UPDATE privacy_requests SET reminded = '" . $stage . "' WHERE id = '" . (int) $row['id'] . "'");

        create_notification(array('action' => 'privacy_request', 'type' => 'warning', 'title' => (string) (int) $row['id'], 'order_total' => (string) $stage, 'user' => 'system'));

        $to = (trim((string) ($row['user_email'] ?? '')) !== '') ? (string) $row['user_email'] : (defined('EMAIL_ADDRESS') ? (string) EMAIL_ADDRESS : '');

        if ($to !== '') {
            $line = lang(array('string' => 'A privacy request must be answered within {var:1} days.', 'vars' => array($stage)));

            email(array(
                'to'                 => $to,
                'from_email_address' => EMAIL_ADDRESS,
                'subject'            => $line,
                'format'             => 'plain_text',
                'body'               => $line . "\n\n" . URL_SCHEME . HOSTNAME_SETTING . PATH . SOFTWARE_DIRECTORY . '/privacy_centre.php?view=requests&request=' . (int) $row['id'],
                'type'               => 'system',
                'queue'              => true,
            ));
        }

        $sent++;
    }

    return $sent;
}

/**
 * The list's columns (P11).
 *
 * @return array  key => spec of pg_list_table_printed()
 */
function pg_privacy_request_list_columns()
{
    return array(
        'kind'     => array('heading' => lang('Kind')),
        'person'   => array('heading' => lang('Person')),
        'received' => array('heading' => lang('Received'), 'kind' => 'date'),
        'due'      => array('heading' => lang('Due'), 'kind' => 'badge'),
        'handler'  => array('heading' => lang('Handled by')),
        'status'   => array('heading' => lang('Status'), 'kind' => 'badge'),
        'result'   => array('heading' => lang('Result')),
    );
}

/**
 * The due pill: days left in grey, amber from 7 days, red from 2 and when
 * overdue; a closed request shows nothing.
 *
 * @param array $row
 * @param int   $now
 * @return string  markup
 */
function pg_privacy_request_due_pill(array $row, $now)
{
    if ($row['status'] !== 'open') {
        return '';
    }

    $left = pg_privacy_request_days_left($row['due_at'], $now);
    $text = ($left < 0) ? lang(array('string' => '{var:1} days overdue', 'vars' => array(-$left))) : lang(array('string' => '{var:1} days left', 'vars' => array($left)));

    return '<span class="pg-pill pg-tone-' . pg_privacy_request_due_tone($left) . '">' . h($text) . '</span>';
}

/**
 * One row of the list.
 *
 * @param array $row     a row of pg_privacy_requests_list()
 * @param array $labels  pg_privacy_request_labels()
 * @param int   $now
 * @return array  key => pg_list_cell()
 */
function pg_privacy_request_list_cells(array $row, array $labels, $now)
{
    $id = (int) $row['id'];
    $person = ($row['name'] !== '') ? $row['name'] : $row['email'];
    $tones = array('open' => 'blue', 'answered' => 'teal', 'refused' => 'rose');

    return array(
        'kind'     => pg_list_cell('<a href="privacy_centre.php?view=requests&amp;request=' . $id . '">' . h($labels['kinds'][$row['kind']] ?? $row['kind']) . '</a>'),
        'person'   => pg_list_cell(h($person) . (($row['name'] !== '') ? '<div class="pg-text-meta">' . h($row['email']) . '</div>' : '')),
        'received' => pg_list_table_when_cell((int) $row['received_at']),
        'due'      => pg_list_cell(pg_privacy_request_due_pill($row, $now), 'align-middle', array('data-pg-sort-value' => (int) $row['due_at'])),
        'handler'  => pg_list_cell(h((string) ($row['assigned_name'] ?? ''))),
        'status'   => pg_list_cell('<span class="pg-pill pg-tone-' . ($tones[$row['status']] ?? 'gray') . '">' . h($labels['statuses'][$row['status']] ?? $row['status']) . '</span>'),
        'result'   => pg_list_cell(h($labels['results'][$row['result']] ?? '')),
    );
}

/**
 * The Privacy centre's views as link segments: Inventory and Requests with
 * the count of open requests.
 *
 * @param string $active  inventory | requests
 * @param int    $open
 * @return string  markup
 */
function pg_privacy_centre_views($active, $open)
{
    $views = array('inventory' => array(lang('Inventory'), 'privacy_centre.php'), 'requests' => array(lang('Requests'), 'privacy_centre.php?view=requests'));
    $html = '<nav class="pg-seg mb-5" aria-label="' . h(lang('Privacy centre')) . '">';

    foreach ($views as $key => $view) {
        $html .= '<a class="pg-seg-item' . (($key === $active) ? ' is-active' : '') . '" href="' . h($view[1]) . '"' . (($key === $active) ? ' aria-current="page"' : '') . '>' . h($view[0])
            . ((($key === 'requests') && ($open > 0)) ? '<span class="pg-seg-count">' . pg_format_number($open, 0) . '</span>' : '') . '</a>';
    }

    return $html . '</nav>';
}

/**
 * A request's drawer, and the drawer that adds one: Bootstrap 6's drawer
 * built as Application Access' #api_new, opened through pgUI.
 *
 * @param string $id
 * @param string $head   markup under the title (a line, a pill)
 * @param string $title
 * @param string $body   the form's fields and panes
 * @param string $foot   the buttons
 * @param string $tabs   the tab strip, '' for none
 * @param bool   $open   opened with the screen
 * @return string
 */
function _pg_privacy_request_drawer_frame($id, $title, $head, $tabs, $body, $foot, $open)
{
    return '
    <dialog class="drawer drawer-end" id="' . h($id) . '" aria-labelledby="' . h($id) . '_title" style="width:min(480px,100%)"' . ($open ? ' data-open="1"' : '') . '>
        <form method="post" action="privacy_centre.php?view=requests" class="d-flex flex-column h-100">
            ' . get_token_field() . '<input type="hidden" name="view" value="requests">
            <div class="drawer-header">
                <div class="flex-grow-1 min-width-0">
                    <h5 class="drawer-title mb-0" id="' . h($id) . '_title">' . h($title) . '</h5>
                    <p class="small fg-2 mb-0 mt-1 d-flex align-items-center gap-3">' . $head . '</p>
                </div>
                <button type="button" class="btn-close ms-auto" data-bs-dismiss="drawer" aria-label="' . h(lang('Close')) . '"></button>
            </div>
            ' . (($tabs !== '') ? '<div class="px-5 pt-3 pb-3 border-bottom">' . $tabs . '</div>' : '') . '
            <div class="drawer-body flex-grow-1 overflow-auto">' . $body . '</div>
            <div class="drawer-footer border-top p-5 d-flex align-items-center gap-3">' . $foot . '</div>
        </form>
    </dialog>';
}

/**
 * A labelled field of a drawer.
 *
 * @param string $id
 * @param string $label
 * @param string $control  markup
 * @param string $help
 * @return string
 */
function _pg_privacy_request_field($id, $label, $control, $help = '')
{
    return '<div class="form-field mb-5"><label class="form-label pg-field-label" for="' . h($id) . '">' . h($label) . '</label>' . $control
        . (($help !== '') ? '<div class="form-text">' . h($help) . '</div>' : '') . '</div>';
}

/**
 * The drawer that adds a request typed in by staff.
 *
 * @param bool $open
 * @return string
 */
function pg_privacy_request_add_drawer($open)
{
    $kinds = '';

    foreach (pg_privacy_request_labels()['kinds'] as $key => $label) {
        $kinds .= '<option value="' . h($key) . '">' . h($label) . '</option>';
    }

    $body = '<input type="hidden" name="action" value="add">'
        . _pg_privacy_request_field('privacy_request_add_kind', lang('Kind'), '<select class="pg-input" id="privacy_request_add_kind" name="kind">' . $kinds . '</select>')
        . _pg_privacy_request_field('privacy_request_add_email', lang('E-mail address'), '<input type="email" class="pg-input" id="privacy_request_add_email" name="email" maxlength="100" required>', lang('The answer goes to this address.'))
        . _pg_privacy_request_field('privacy_request_add_received', lang('Received on'), '<input type="date" data-pg-date class="pg-input" id="privacy_request_add_received" name="received_on" value="' . h(date('Y-m-d')) . '" max="' . h(date('Y-m-d')) . '" required>', lang('The day the request came; its 30 days start then.'))
        . _pg_privacy_request_field('privacy_request_add_name', lang('Name'), '<input type="text" class="pg-input" id="privacy_request_add_name" name="name" maxlength="100">')
        . _pg_privacy_request_field('privacy_request_add_message', lang('What the person asks'), '<textarea class="pg-input" id="privacy_request_add_message" name="message" rows="5"></textarea>');

    $foot = '<span class="flex-grow-1"></span>'
        . '<button type="button" class="pg-button pg-button-quiet" data-bs-dismiss="drawer">' . h(lang('Cancel')) . '</button>'
        . '<button type="submit" class="pg-button pg-button-fill">' . h(lang('Add')) . '</button>';

    return _pg_privacy_request_drawer_frame('privacy_request_add', lang('Add a request'), h(lang('A request that came by e-mail, phone or post.')), '', $body, $foot, $open);
}

/**
 * A request's drawer: the person, the kind and the due pill; tabs Request
 * and History; Refuse, Cancel and Save at the foot. A closed request shows
 * its fields as plain text with Close only.
 *
 * @param array $request
 * @param array $labels   pg_privacy_request_labels()
 * @param int   $now
 * @return string
 */
function pg_privacy_request_drawer(array $request, array $labels, $now)
{
    $id = (int) $request['id'];
    $open = ($request['status'] === 'open');
    $handler = '';

    foreach (pg_privacy_request_handlers() as $user_id => $username) {
        if ((int) $request['assigned_user_id'] === $user_id) {
            $handler = $username;
        }
    }

    $time = function ($at) {
        return ((int) $at > 0) ? h(get_absolute_time(array('timestamp' => (int) $at, 'format' => 'plain_text'))) : '–';
    };

    $facts = '<dl class="pg-kv mb-5">'
        . '<dt>' . h(lang('E-mail address')) . '</dt><dd>' . h($request['email']) . '</dd>'
        . '<dt>' . h(lang('Came from')) . '</dt><dd>' . h($labels['sources'][$request['source']] ?? $request['source']) . '</dd>'
        . '<dt>' . h(lang('Address')) . '</dt><dd>' . h(($request['source'] === 'staff') ? lang('Typed in by staff') : lang('Proven by its link')) . '</dd>'
        . '<dt>' . h(lang('Received')) . '</dt><dd>' . $time($request['received_at']) . '</dd>'
        . '<dt>' . h(lang('Status')) . '</dt><dd>' . h($labels['statuses'][$request['status']] ?? $request['status']) . '</dd>'
        . ($open ? '' : '<dt>' . h(lang('Handled by')) . '</dt><dd>' . h($handler) . '</dd>'
            . '<dt>' . h(lang('Result')) . '</dt><dd>' . h($labels['results'][$request['result']] ?? '') . ' · ' . $time($request['closed_at']) . '</dd>')
        . '</dl>'
        . '<h6 class="pg-title-status">' . h(lang('What the person asks')) . '</h6><p class="pg-text-2 text-break">' . nl2br(h((string) $request['message'])) . '</p>';

    if ($open) {
        $handlers = '<option value="0">' . h(lang('Nobody yet')) . '</option>';

        foreach (pg_privacy_request_handlers() as $user_id => $username) {
            $handlers .= '<option value="' . $user_id . '"' . (((int) $request['assigned_user_id'] === $user_id) ? ' selected' : '') . '>' . h($username) . '</option>';
        }

        $facts .= _pg_privacy_request_field('privacy_request_handler', lang('Handled by'), '<select class="pg-input" id="privacy_request_handler" name="assigned_user_id">' . $handlers . '</select>', lang('Reminders at 7 and 2 days go to this person.'))
            . '<div class="form-field mb-5"><div class="d-flex align-items-center gap-3"><input type="checkbox" class="pg-checkbox" id="privacy_request_verified" name="verified" value="1"' . (((int) $request['verified_at'] > 0) ? ' checked' : '') . '><label class="pg-text-2" for="privacy_request_verified">' . h(lang('I checked who asks')) . '</label></div>'
            . '<div class="form-text">' . h(($request['source'] === 'staff') ? lang('The request was typed in by staff: make sure it comes from the person whose data it is.') : lang('The address was proven by its link. Ask for more only when the request is about somebody else.')) . '</div></div>'
            . _pg_privacy_request_field('privacy_request_reason', lang('Why it is refused'), '<textarea class="pg-input" id="privacy_request_reason" name="reason" rows="3"></textarea>', lang('Only when you refuse: the reason is sent to the person.'));
    } elseif ((string) $request['answer'] !== '') {
        $facts .= '<h6 class="pg-title-status">' . h(lang('The answer sent')) . '</h6><p class="pg-text-2 text-break">' . nl2br(h((string) $request['answer'])) . '</p>';
    }

    // History: the request's audit lines, newest first.
    $history = '';

    foreach (pg_privacy_request_history($id) as $line) {
        $history .= '<div class="pg-list-row is-read"><div class="pg-list-row-main"><div class="pg-list-row-head"><span class="pg-list-row-title">'
            . h(pg_audit_view_labels('action')[$line['action']] ?? $line['action']) . '</span><span class="pg-list-row-time">' . get_relative_time(array('timestamp' => (int) $line['created_at'])) . '</span></div>'
            . '<div class="pg-list-row-text">' . h((string) $line['actor_name']) . '</div></div></div>';
    }

    $tabs = '<span class="pg-seg" role="tablist" aria-label="' . h(lang('Request')) . '">';
    $panes = array('request' => array(lang('Request'), $facts), 'history' => array(lang('History'), ($history !== '') ? '<div class="pg-look-rows">' . $history . '</div>' : '<p class="pg-text-2">' . h(lang('No step yet.')) . '</p>'));
    $body = '<input type="hidden" name="request" value="' . $id . '">';

    foreach ($panes as $key => $pane) {
        $first = ($key === 'request');
        $tabs .= '<button type="button" class="pg-seg-item" role="tab" id="privacy_request_tab_' . $key . '" aria-selected="' . ($first ? 'true' : 'false') . '" aria-controls="privacy_request_pane_' . $key . '"' . ($first ? '' : ' tabindex="-1"') . '>' . h($pane[0]) . '</button>';
        $body .= '<div role="tabpanel" id="privacy_request_pane_' . $key . '" aria-labelledby="privacy_request_tab_' . $key . '"' . ($first ? '' : ' hidden') . '>' . $pane[1] . '</div>';
    }

    $foot = $open
        ? '<button type="submit" name="action" value="refuse" class="pg-button pg-button-danger" data-confirm-content="' . h(lang('The reason is sent to the person and the request is closed.')) . '">' . h(lang('Refuse')) . '</button>'
            . '<span class="flex-grow-1"></span>'
            . '<button type="button" class="pg-button pg-button-quiet" data-bs-dismiss="drawer">' . h(lang('Cancel')) . '</button>'
            . '<button type="submit" name="action" value="save" class="pg-button pg-button-fill">' . h(lang('Save')) . '</button>'
        : '<span class="flex-grow-1"></span><button type="button" class="pg-button pg-button-quiet" data-bs-dismiss="drawer">' . h(lang('Close')) . '</button>';

    $person = ($request['name'] !== '') ? $request['name'] : $request['email'];
    $head = h($labels['kinds'][$request['kind']] ?? $request['kind']) . ' ' . pg_privacy_request_due_pill($request, $now);

    return _pg_privacy_request_drawer_frame('privacy_request', $person, $head, $tabs . '</span>', $body, $foot, true);
}
