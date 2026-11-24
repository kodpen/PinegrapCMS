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

// The download link an information answer carries.
require_once(PG_FUNCTIONS_DIR . '/includes/fn/privacy_request_link.php');

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
 * The query of a request's steps, newest first: audit_log names the person
 * who took a step in user_id and username. Pure.
 *
 * @param int $id
 * @return string
 */
function pg_privacy_request_history_query($id)
{
    return "SELECT created_at, action, user_id, username, after_json FROM audit_log
        WHERE (entity_type = 'privacy_request') AND (entity_id = '" . (int) $id . "')
        ORDER BY created_at DESC, id DESC LIMIT 200";
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

    return db_items(pg_privacy_request_history_query($id));
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
 *                 erasure or the export was refused) | file (the export
 *                 could not be written)
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

    // An information answer carries one signed link to the export, never
    // the file; the link stays out of the answer kept with the request.
    $link = null;

    if (!$refuse && ($request['kind'] === 'information')) {
        $link = pg_privacy_link_prepare($request, $operator);

        if (in_array($link['status'], array('refused', 'file'), true)) {
            return $link['status'];
        }

        $link = ($link['status'] === 'ok') ? $link : null;
    }

    $site = pg_privacy_request_site();

    $sent = (bool) email(array(
        'to'                 => (string) $request['email'],
        'from_name'          => $site,
        'from_email_address' => EMAIL_ADDRESS,
        'subject'            => lang(array('string' => '{var:1}: your request about your personal data', 'vars' => array($site))),
        'format'             => 'plain_text',
        'body'               => ($link !== null) ? $text . "\n\n" . pg_privacy_link_mail_lines($link['url']) : $text,
        'type'               => 'system',
        'secret'             => ($link !== null),
    ));

    if (!$sent) {
        if ($link !== null) {
            pg_privacy_link_delete((int) $request['id']);
        }

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

    if ($link !== null) {
        pg_privacy_link_record($request, $link);
    }

    return 'ok';
}

/**
 * The site's name an answer is signed with and its subject names.
 *
 * @return string
 */
function pg_privacy_request_site()
{
    return (defined('ORGANIZATION_NAME') && (trim((string) ORGANIZATION_NAME) !== '')) ? (string) ORGANIZATION_NAME : (string) HOSTNAME_SETTING;
}

/**
 * The state of the four numbered steps of "Prepare the answer": check who
 * asks, gather, write the answer, send. Nothing is gathered before staff
 * has checked who asks; a gather this operator may not run (a person of a
 * higher role, their own account) stops there. Pure.
 *
 * @param array      $request   verified_at
 * @param array|null $gathered  pg_privacy_request_gather(), null when not run
 * @return array  steps (key => done | current | later), open (the step
 *                shown open), can_send
 */
function pg_privacy_request_answer_steps(array $request, $gathered)
{
    $checked = ((int) ($request['verified_at'] ?? 0) > 0);
    $ok = $checked && is_array($gathered) && in_array($gathered['status'] ?? '', array('ok', 'not_found', 'empty'), true);

    if (!$checked) {
        $steps = array('check' => 'current', 'gather' => 'later', 'write' => 'later', 'send' => 'later');
    } elseif (!$ok) {
        $steps = array('check' => 'done', 'gather' => 'current', 'write' => 'later', 'send' => 'later');
    } else {
        $steps = array('check' => 'done', 'gather' => 'done', 'write' => 'current', 'send' => 'later');
    }

    return array('steps' => $steps, 'open' => $checked ? 'gather' : 'check', 'can_send' => $ok);
}

/**
 * What the gather step runs: the erase dry run for an erasure, the
 * personal data export for every other kind. Pure.
 *
 * @param string $kind
 * @return string  erase | export
 */
function pg_privacy_request_gather_mode($kind)
{
    return ($kind === 'erasure') ? 'erase' : 'export';
}

/**
 * The gather step's lists by their names: for the export the sections with
 * rows (found), in the pack's order; for the erase dry run the tables
 * deleted and those kept with their personal fields replaced. Two keys of
 * the same name add up. Pure but for lang().
 *
 * @param string $mode  export | erase
 * @param array  $raw   counts (section => rows) | tables (pg_privacy_erase_run())
 * @return array  found | deleted, kept: name => rows
 */
function pg_privacy_request_gather_lists($mode, array $raw)
{
    $add = function (array &$list, $label, $rows) {
        $list[$label] = ($list[$label] ?? 0) + (int) $rows;
    };

    if ($mode === 'erase') {
        $labels = pg_privacy_erase_labels();
        $lists = array('deleted' => array(), 'kept' => array());

        foreach ((isset($raw['tables']) && is_array($raw['tables'])) ? $raw['tables'] : array() as $key => $table) {
            if ((int) ($table['rows'] ?? 0) > 0) {
                $add($lists[(($table['action'] ?? '') === 'delete') ? 'deleted' : 'kept'], (string) ($labels[$key] ?? $key), $table['rows']);
            }
        }

        return $lists;
    }

    $counts = (isset($raw['counts']) && is_array($raw['counts'])) ? $raw['counts'] : array();
    $found = array();

    foreach (pg_privacy_export_sections() as $key => $label) {
        if ((int) ($counts[$key] ?? 0) > 0) {
            $add($found, (string) $label, $counts[$key]);
        }
    }

    return array('found' => $found);
}

/**
 * Run the gather step: the erase dry run (nothing is written) or the
 * export's counts, under the export's rule of who may see whom.
 *
 * @param array $request
 * @param array $operator  role, id
 * @return array  mode, status (ok | not_found | empty | refused | self),
 *                lists (pg_privacy_request_gather_lists())
 */
function pg_privacy_request_gather(array $request, array $operator)
{
    $mode = pg_privacy_request_gather_mode((string) $request['kind']);
    $input = array('email' => (string) $request['email'], 'contact_id' => (int) $request['contact_id'], 'user_id' => (int) $request['user_id']);

    if ($mode === 'erase') {
        $dry = pg_privacy_erase($input, $operator, false);

        return array('mode' => $mode, 'status' => $dry['status'], 'lists' => pg_privacy_request_gather_lists($mode, $dry));
    }

    $found = pg_privacy_export_find($input);
    $out = array('mode' => $mode, 'status' => 'ok', 'lists' => pg_privacy_request_gather_lists($mode, array()));

    if (pg_privacy_export_is_empty($found['person'])) {
        $out['status'] = 'empty';
    } elseif (!pg_privacy_export_allowed($operator['role'] ?? 3, $operator['id'] ?? 0, $found['users'])) {
        $out['status'] = 'refused';
    } else {
        $document = pg_privacy_export_document($found['person'], pg_privacy_export_collect($found), array());
        $out['lists'] = pg_privacy_request_gather_lists($mode, array('counts' => pg_privacy_export_counts($document)));
        $out['status'] = $out['lists']['found'] ? 'ok' : 'not_found';
    }

    return $out;
}

/**
 * The answer's text from the kind's template, for staff to change before it
 * goes: a greeting, the kind's paragraphs with the gathered lists, the
 * site's name. Plain text. Pure but for lang().
 *
 * @param string $kind
 * @param string $name   the person's name, '' for none
 * @param string $site
 * @param array  $lists  pg_privacy_request_gather_lists()
 * @return string
 */
function pg_privacy_request_answer_text($kind, $name, $site, array $lists)
{
    $lines = function ($list) {
        $out = array();

        foreach ((array) $list as $label => $rows) {
            $out[] = '- ' . $label . ': ' . $rows;
        }

        return implode("\n", $out);
    };

    $found = (array) ($lists['found'] ?? array());
    $deleted = (array) ($lists['deleted'] ?? array());
    $kept = (array) ($lists['kept'] ?? array());
    $parts = array(($name !== '') ? lang(array('string' => 'Hello {var:1},', 'vars' => array($name))) : lang('Hello,'));

    switch ($kind) {
        case 'information':
            if ($found) {
                $parts[] = lang('You asked which personal data we keep about you. We keep these records:');
                $parts[] = $lines($found);
                $parts[] = lang('We keep them for the purposes and periods in our privacy notice. A copy of the records themselves is linked at the end of this e-mail.');
            } else {
                $parts[] = lang('You asked which personal data we keep about you.') . ' ' . lang('We keep no personal data about you.');
            }
            break;

        case 'erasure':
            if (!$deleted && !$kept) {
                $parts[] = lang('You asked us to erase your personal data. We keep no personal data about you, so there was nothing to erase.');
                break;
            }

            $parts[] = lang('You asked us to erase your personal data.') . ' ' . lang('We have erased your personal data.');

            if ($kept) {
                $parts[] = lang('The law obliges us to keep these records; your name and contact details in them have been replaced:');
                $parts[] = $lines($kept);
            }
            break;

        case 'correction':
            $parts[] = lang('You asked us to correct your personal data. We have corrected it as you asked.');
            break;

        case 'objection':
            $parts[] = lang('You objected to how we use your personal data. We have looked into it and stopped the use you objected to.');
            break;

        default:
            $parts[] = lang('Thank you for your request about your personal data.');
    }

    $parts[] = lang('Kind regards,') . "\n" . $site;

    return implode("\n\n", $parts);
}

/**
 * The Answer tab's numbered steps as Bootstrap 6's stepper with cards
 * (ui/step-rail.md): a done step green, the current one active and marked,
 * a later one plain; the number is the stepper's counter. Each step folds
 * in a <details> of one name, so one is open at a time; .theme-reset keeps
 * the item's theme out of the step's own buttons and fields. Pure.
 *
 * @param array  $steps  key => done | current | later
 * @param string $open   the step shown open
 * @param array  $items  key => title, line (one line of help), body (markup)
 * @return string
 */
function pg_privacy_request_answer_stepper(array $steps, $open, array $items)
{
    $html = '<ol class="stepper pg-stepper-cards mb-0">';

    foreach ($items as $key => $item) {
        $state = $steps[$key] ?? 'later';
        $class = ($state === 'done') ? ' active theme-success' : (($state === 'current') ? ' active' : '');

        $html .= '<li class="stepper-item' . $class . '"' . (($state === 'current') ? ' aria-current="step"' : '') . '>'
            . '<details name="privacy_answer_step" class="theme-reset"' . (($key === $open) ? ' open' : '') . '>'
            . '<summary class="list-unstyled"><span class="pg-list-row-title">' . h($item['title']) . '</span><span class="pg-text-meta d-block">' . h($item['line']) . '</span></summary>'
            . '<div class="pt-4">' . $item['body'] . '</div>'
            . '</details></li>';
    }

    return $html . '</ol>';
}

/**
 * A label and value list of the gather step.
 *
 * @param array $list  name => rows
 * @return string
 */
function _pg_privacy_request_gather_kv(array $list)
{
    $html = '<dl class="pg-kv mb-5">';

    foreach ($list as $label => $rows) {
        $html .= '<dt>' . h($label) . '</dt><dd>' . pg_format_number($rows, 0) . '</dd>';
    }

    return $html . '</dl>';
}

/**
 * The Answer tab, "Prepare the answer": four numbered steps, one open at a
 * time (check who asks, gather, write the answer, send). The send button is
 * the drawer's foot. A closed request shows the answer sent, read-only.
 *
 * @param array      $request
 * @param array|null $gathered  pg_privacy_request_gather(), null when not run
 * @return string
 */
function pg_privacy_request_answer_pane(array $request, $gathered)
{
    if ($request['status'] !== 'open') {
        return ((string) $request['answer'] !== '')
            ? '<h6 class="pg-title-status">' . h(lang('The answer sent')) . '</h6><p class="pg-text-2 text-break">' . nl2br(h((string) $request['answer'])) . '</p>'
            : '<p class="pg-text-2">' . h(lang('No answer was sent.')) . '</p>';
    }

    $state = pg_privacy_request_answer_steps($request, $gathered);
    $erase = (pg_privacy_request_gather_mode((string) $request['kind']) === 'erase');
    $lists = is_array($gathered) ? (array) $gathered['lists'] : array();
    $first = lang('Check who asks first.');

    // 1. Who asks.
    $check = '<div class="form-field mb-4"><div class="d-flex align-items-center gap-3"><input type="checkbox" class="pg-checkbox" id="privacy_request_verified" name="verified" value="1"' . (((int) $request['verified_at'] > 0) ? ' checked' : '') . '><label class="pg-text-2" for="privacy_request_verified">' . h(lang('I checked who asks')) . '</label></div>'
        . '<div class="form-text">' . h(($request['source'] === 'staff') ? lang('The request was typed in by staff: make sure it comes from the person whose data it is.') : lang('The address was proven by its link. Ask for more only when the request is about somebody else.')) . '</div></div>'
        . '<button type="submit" name="action" value="save" class="pg-button pg-button-quiet pg-button-sm">' . h(lang('Save')) . '</button>';

    // 2. Gather.
    $problems = array(
        'refused' => lang('Your role may not see this person\'s data: someone with a higher role answers this request.'),
        'self'    => lang('This request is about your own account: another manager answers it.'),
    );

    if (!is_array($gathered)) {
        $gather = '<p class="pg-text-2 mb-0">' . h($first) . '</p>';
    } elseif (isset($problems[$gathered['status']])) {
        $gather = '<div class="alert theme-warning small py-3 mb-0">' . h($problems[$gathered['status']]) . '</div>';
    } elseif ($erase) {
        $gather = (!empty($lists['deleted']) ? '<h6 class="pg-title-status">' . h(lang('What is erased')) . '</h6>' . _pg_privacy_request_gather_kv($lists['deleted']) : '')
            . (!empty($lists['kept']) ? '<h6 class="pg-title-status">' . h(lang('What the law keeps, with the personal fields replaced')) . '</h6>' . _pg_privacy_request_gather_kv($lists['kept']) : '')
            . ((empty($lists['deleted']) && empty($lists['kept'])) ? '<p class="pg-text-2 mb-0">' . h(lang('Nothing of this person was found.')) . '</p>' : '');
    } else {
        $ids = ((int) $request['contact_id'] > 0) ? 'contact_id=' . (int) $request['contact_id'] : (((int) $request['user_id'] > 0) ? 'user_id=' . (int) $request['user_id'] : '');
        $gather = (!empty($lists['found']) ? _pg_privacy_request_gather_kv($lists['found']) : '<p class="pg-text-2">' . h(lang('Nothing of this person was found.')) . '</p>')
            . (($ids !== '') ? '<a class="pg-button pg-button-quiet pg-button-sm" href="privacy_export.php?' . $ids . '"><i class="bi bi-download" aria-hidden="true"></i>' . h(lang('Personal data export')) . '</a>' : '');
    }

    // 3. The answer, from the kind's text.
    $write = $state['can_send']
        ? '<label class="visually-hidden" for="privacy_request_answer">' . h(lang('The answer')) . '</label><textarea class="pg-input" id="privacy_request_answer" name="answer" rows="12">'
            . h(pg_privacy_request_answer_text((string) $request['kind'], (string) $request['name'], pg_privacy_request_site(), $lists)) . '</textarea>'
        : '<p class="pg-text-2 mb-0">' . h($first) . '</p>';

    // 4. Where it goes.
    $send = '<dl class="pg-kv mb-0"><dt>' . h(lang('To')) . '</dt><dd>' . h((string) $request['email']) . '</dd>'
        . '<dt>' . h(lang('Then')) . '</dt><dd>' . h($erase ? lang('The data is erased and the request closed') : lang('The request is closed')) . '</dd>'
        . ((($request['kind'] === 'information') && !empty($lists['found'])) ? '<dt>' . h(lang('The data')) . '</dt><dd>' . h(lang('A download link valid 7 days, under the answer; no attachment')) . '</dd>' : '')
        . '</dl>';

    return pg_privacy_request_answer_stepper($state['steps'], $state['open'], array(
        'check'  => array('title' => lang('Check who asks'), 'line' => lang('Make sure the request comes from the person whose data it is.'), 'body' => $check),
        'gather' => array('title' => lang('Gather'), 'line' => $erase ? lang('What erasing this person removes. Nothing is erased yet.') : lang('What the site keeps about this person.'), 'body' => $gather),
        'write'  => array('title' => lang('Write the answer'), 'line' => lang('The e-mail from the kind\'s text; change it as needed.'), 'body' => $write),
        'send'   => array('title' => lang('Send'), 'line' => $erase ? lang('The e-mail goes first, then the data is erased.') : lang('The answer goes by e-mail and is kept with the request.'), 'body' => $send),
    ));
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
    <dialog class="drawer drawer-end" id="' . h($id) . '" aria-labelledby="' . h($id) . '_title" style="width:min(480px,100%);--pg-drawer-height:88vh"' . ($open ? ' data-open="1"' : '') . '>
        <form method="post" action="privacy_centre.php?view=requests" class="d-flex flex-column h-100">
            ' . get_token_field() . '<input type="hidden" name="view" value="requests">
            <div class="drawer-header">
                <div class="flex-grow-1 min-w-0">
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
 * A request's drawer: the person, the kind and the due pill; tabs Request,
 * Answer and History. The Request and History tabs end in Refuse, Cancel
 * and Save; the Answer tab in Cancel and Send the answer (Erase and send for
 * an erasure). A closed request shows its fields and the answer sent as
 * plain text with Close only.
 *
 * @param array      $request
 * @param array      $labels    pg_privacy_request_labels()
 * @param int        $now
 * @param string     $tab       request | answer | history: the tab shown
 * @param array|null $gathered  pg_privacy_request_gather(), null when not run
 * @return string
 */
function pg_privacy_request_drawer(array $request, array $labels, $now, $tab = 'request', $gathered = null)
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
        . '<dt>' . h(lang('Who asks')) . '</dt><dd>' . h(((int) $request['verified_at'] > 0) ? lang('Checked') : lang('Not checked yet')) . '</dd>'
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
            . _pg_privacy_request_field('privacy_request_reason', lang('Why it is refused'), '<textarea class="pg-input" id="privacy_request_reason" name="reason" rows="3"></textarea>', lang('Only when you refuse: the reason is sent to the person.'));
    }

    // History: the request's audit lines, newest first.
    $history = '';
    $lines = pg_privacy_request_history($id);

    foreach ($lines as $line) {
        $history .= '<div class="pg-list-row is-read"><div class="pg-list-row-main"><div class="pg-list-row-head"><span class="pg-list-row-title">'
            . h(pg_audit_view_labels('action')[$line['action']] ?? $line['action']) . '</span><span class="pg-list-row-time">' . get_relative_time(array('timestamp' => (int) $line['created_at'])) . '</span></div>'
            . '<div class="pg-list-row-text">' . h((string) $line['username']) . '</div></div></div>';
    }

    $panes = array(
        'request' => array(lang('Request'), $facts),
        'answer'  => array(lang('Answer'), pg_privacy_request_answer_pane($request, $gathered) . ($open ? '' : pg_privacy_link_card($request, $lines, $now))),
        'history' => array(lang('History'), ($history !== '') ? '<div class="pg-look-rows">' . $history . '</div>' : '<p class="pg-text-2">' . h(lang('No step yet.')) . '</p>'),
    );
    $tab = isset($panes[$tab]) ? $tab : 'request';
    $tabs = '<span class="pg-seg" role="tablist" aria-label="' . h(lang('Request')) . '">';
    $body = '<input type="hidden" name="request" value="' . $id . '"><input type="hidden" name="tab" value="' . h($tab) . '">';

    foreach ($panes as $key => $pane) {
        $on = ($key === $tab);
        $tabs .= '<button type="button" class="pg-seg-item" role="tab" id="privacy_request_tab_' . $key . '" aria-selected="' . ($on ? 'true' : 'false') . '" aria-controls="privacy_request_pane_' . $key . '"' . ($on ? '' : ' tabindex="-1"') . '>' . h($pane[0]) . '</button>';
        $body .= '<div role="tabpanel" id="privacy_request_pane_' . $key . '" aria-labelledby="privacy_request_tab_' . $key . '"' . ($on ? '' : ' hidden') . '>' . $pane[1] . '</div>';
    }

    // The foot follows the tab: Save on Request and History, the answer's
    // own button on Answer; the drawer's script swaps them.
    $answer = ($tab === 'answer');
    $steps = pg_privacy_request_answer_steps($request, $gathered);
    $erase = ($request['kind'] === 'erasure');

    $foot = $open
        ? '<button type="submit" name="action" value="refuse" class="pg-button pg-button-danger" data-privacy-foot="request" data-confirm-content="' . h(lang('The reason is sent to the person and the request is closed.')) . '"' . ($answer ? ' hidden' : '') . '>' . h(lang('Refuse')) . '</button>'
            . '<span class="flex-grow-1"></span>'
            . '<button type="button" class="pg-button pg-button-quiet" data-bs-dismiss="drawer">' . h(lang('Cancel')) . '</button>'
            . '<button type="submit" name="action" value="save" class="pg-button pg-button-fill" data-privacy-foot="request"' . ($answer ? ' hidden' : '') . '>' . h(lang('Save')) . '</button>'
            . '<button type="submit" name="action" value="send" class="pg-button ' . ($erase ? 'pg-button-danger' : 'pg-button-fill') . '" data-privacy-foot="answer"'
                . ($erase ? ' data-confirm-content="' . h(lang('The person\'s data is erased after the e-mail has gone. This cannot be undone.')) . '"' : '')
                . ($steps['can_send'] ? '' : ' disabled') . ($answer ? '' : ' hidden') . '>' . h($erase ? lang('Erase and send') : lang('Send the answer')) . '</button>'
        : '<span class="flex-grow-1"></span><button type="button" class="pg-button pg-button-quiet" data-bs-dismiss="drawer">' . h(lang('Close')) . '</button>';

    $person = ($request['name'] !== '') ? $request['name'] : $request['email'];
    $head = h($labels['kinds'][$request['kind']] ?? $request['kind']) . ' ' . pg_privacy_request_due_pill($request, $now);

    return _pg_privacy_request_drawer_frame('privacy_request', $person, $head, $tabs . '</span>', $body, $foot, true);
}
