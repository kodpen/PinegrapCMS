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
    if ((($row['status'] ?? '') !== 'open') || ((int) ($row['due_at'] ?? 0) <= 0)) {
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
 * The condition of a list view: open, answered (answered or refused), all;
 * an unproven row is in none. Pure.
 *
 * @param string $view
 * @return string
 */
function pg_privacy_request_view_where($view)
{
    if ($view === 'answered') {
        return "(status IN ('answered', 'refused'))";
    }

    return ($view === 'all') ? "(status <> 'unproven')" : "(status = 'open')";
}

/**
 * A request typed in by staff: open at once, its 30 days from now. Pure.
 *
 * @param array $input       kind, email, name, message
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

    return array('errors' => $errors, 'row' => array(
        'kind'        => $kind,
        'status'      => 'open',
        'source'      => 'staff',
        'email'       => is_string($email) ? $email : '',
        'name'        => $text('name', 100),
        'message'     => $text('message', 10000),
        'created_by'  => (int) $operator_id,
        'received_at' => (int) $now,
        'due_at'      => pg_privacy_request_due_at($now),
    ));
}

/**
 * The answer's text as the kind's template, for staff to change. Pure but
 * for the words.
 *
 * @param string $kind
 * @param string $name
 * @param string $site
 * @return string
 */
function pg_privacy_request_answer_template($kind, $name, $site)
{
    $texts = array(
        'information' => lang('You asked which personal data we keep about you. The personal data we keep about you is listed below. Ask us if anything is unclear.'),
        'correction'  => lang('You asked us to correct your personal data. We have made the correction you asked for.'),
        'erasure'     => lang('You asked us to erase your personal data. We have erased it, except for the records the law obliges us to keep, such as invoices.'),
        'objection'   => lang('You objected to how we use your personal data. We have looked at your objection and answer it below.'),
        'other'       => lang('We have looked at your request about your personal data and answer it below.'),
    );

    $hello = ($name !== '') ? lang(array('string' => 'Hello {var:1},', 'vars' => array($name))) : lang('Hello,');

    return $hello . "\n\n" . ($texts[$kind] ?? $texts['other']) . "\n\n" . $site;
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

    $row = db_item("SELECT * FROM privacy_requests WHERE (id = '" . (int) $id . "') AND (status <> 'unproven')");

    return is_array($row) ? $row : null;
}

/**
 * A view of the box, soonest due first; the name of who handles each.
 *
 * @param string $view  open | answered | all
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
        WHERE " . str_replace('(status', '(r.status', pg_privacy_request_view_where($view)) . "
        ORDER BY (r.status = 'open') DESC, r.due_at ASC, r.id DESC
        LIMIT " . (int) pg_privacy_request_limits()['list']);
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
        FROM privacy_requests WHERE status = 'open'");

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
 * What answering would hand over or remove: the export's sections
 * (information) or the erase dry run's tables (erasure), as label => count.
 *
 * @param array $request
 * @param array $operator  role, id
 * @return array  status (pg_privacy_export_build()'s or pg_privacy_erase()'s), lines
 */
function pg_privacy_request_gather(array $request, array $operator)
{
    $input = array('email' => (string) $request['email'], 'contact_id' => (int) $request['contact_id'], 'user_id' => (int) $request['user_id']);

    if ($request['kind'] === 'erasure') {
        $result = pg_privacy_erase($input, $operator, false);
        $labels = pg_privacy_erase_labels();
        $kept = pg_privacy_rules_never_entries();
        $lines = array('go' => array(), 'kept' => array());

        foreach ($result['tables'] as $key => $done) {
            $lines[(in_array($key, $kept, true) && ($done['action'] === 'anonymise')) ? 'kept' : 'go'][$labels[$key] ?? $key] = (int) $done['rows'];
        }

        return array('status' => $result['status'], 'lines' => $lines);
    }

    if ($request['kind'] !== 'information') {
        return array('status' => 'none', 'lines' => array());
    }

    $result = pg_privacy_export_build($input, $operator, array('site' => (string) HOSTNAME_SETTING, 'prepared_by' => ''));
    $labels = pg_privacy_export_sections();
    $lines = array();

    foreach (($result['document'] !== null) ? pg_privacy_export_counts($result['document']) : array() as $key => $count) {
        if ($count > 0) {
            $lines[$labels[$key] ?? $key] = $count;
        }
    }

    return array('status' => $result['status'], 'lines' => array('go' => $lines, 'kept' => array()));
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
    $rows = db_items("SELECT r.id, r.kind, r.status, r.due_at, r.reminded, u.user_email
        FROM privacy_requests r
        LEFT JOIN user u ON (r.assigned_user_id > 0) AND (u.user_id = r.assigned_user_id)
        WHERE (r.status = 'open') AND (r.due_at < '" . ($now + ($limits['amber'] * 86400)) . "') AND ((r.reminded = 0) OR (r.reminded > '" . (int) $limits['red'] . "'))
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
