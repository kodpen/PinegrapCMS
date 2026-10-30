<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - the history of a channel's customer: their orders, invoices,
 * sales quotes and submitted forms, and the messages, tasks, notes and
 * workflow table rows elsewhere in the Workspace that tag them or a record
 * tied to them, and the customer visits on the Work Calendar that name them,
 * as one list, newest first.
 *
 * It is read from the tables those records live in, each time it is asked
 * for; there is no activity table to keep in step, so records from before
 * this file count too. Each source is read only with the right its own
 * screens ask for, as the customer summary reads it (customer.php), and only
 * the newest few below the start of the page asked for; the rows are then
 * laid out as one list (ws_customer_history_build()).
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
 * The entries a page of the history holds when none is asked for, and the
 * most it may hold.
 */
define('WS_CUSTOMER_HISTORY_PAGE', 20);
define('WS_CUSTOMER_HISTORY_MAX', 50);

/**
 * How many entries a page holds: the number asked for, between 1 and
 * WS_CUSTOMER_HISTORY_MAX, or WS_CUSTOMER_HISTORY_PAGE for none.
 *
 * @param mixed $limit
 * @return int
 */
function ws_customer_history_limit($limit)
{
    if (($limit === null) || ($limit === '')) {
        return WS_CUSTOMER_HISTORY_PAGE;
    }

    return max(1, min(WS_CUSTOMER_HISTORY_MAX, (int) $limit));
}

/**
 * Which sources the reader may see: orders the ecommerce right, invoices and
 * sales quotes the ERP right (the read-only right holds it too: the history
 * only reads), submitted forms the forms right; the messages and tasks that
 * tag the customer any member of the Workspace, each only where they may see
 * it.
 *
 * @param array $viewer ws_viewer(), or the API's copy narrowed to the key's
 *                      scopes (ws_api_customer_viewer())
 * @return array source => bool
 */
function ws_customer_history_rights($viewer)
{
    $erp = !empty($viewer['erp']);

    return array(
        'orders'       => !empty($viewer['ecommerce']),
        'invoices'     => $erp,
        'sales_quotes' => $erp,
        'forms'        => !empty($viewer['forms']),
        'workspace'    => !empty($viewer['member']),
    );
}

/**
 * A page of the customer's history for the reader. A source the reader holds
 * no right to is not read at all.
 *
 * @param array $viewer
 * @param array $channel  a ws_channels row the reader may read
 * @param array $identity ws_customer_identity() of the channel
 * @param int   $before   read only what happened before this moment (the
 *                        previous page's before); 0 for the newest
 * @param int   $limit    ws_customer_history_limit()
 * @return array see ws_customer_history_build()
 */
function ws_customer_history($viewer, $channel, $identity, $before, $limit)
{
    $rights = ws_customer_history_rights($viewer);
    $before = max(0, (int) $before);
    $read = $limit + 1;
    $data = array();

    if ($rights['orders']) {
        $data['orders'] = ws_customer_history_order_rows($identity, $before, $read);
    }

    if ($rights['invoices']) {
        $data['invoices'] = ws_customer_history_invoice_rows($identity, $before, $read);
    }

    if ($rights['sales_quotes']) {
        $data['sales_quotes'] = ws_customer_history_quote_rows($identity, $before, $read);
    }

    if ($rights['forms']) {
        $data['forms'] = ws_customer_history_form_rows($viewer, $identity, $before, $read);
    }

    if ($rights['workspace']) {
        $data = array_merge($data, ws_customer_history_ref_rows($viewer, $channel, $identity, $before, $read));
        $data['visits'] = ws_customer_history_visit_rows($identity, $before, $read);
    }

    return ws_customer_history_build($rights, $data, 'ws_customer_money_text', OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/', $limit);
}

/**
 * The history laid out from the rows its readers handed over: one entry per
 * row of a source the reader may see (a form only when its folder is open to
 * them), newest first, cut at $limit.
 *
 * A page never ends inside a second: when the first entry left out happened
 * in the same second as the last ones shown, those wait for the next page
 * too, so reading below `before` misses none of them. Only a page made of
 * one second alone keeps it, and the next page starts below that second.
 *
 * @param array    $rights ws_customer_history_rights()
 * @param array    $data   rows per source: orders, invoices, sales_quotes
 *                         (each with state_label), forms (each with open),
 *                         messages (channel_name, sender_name, excerpt),
 *                         tasks (title, status_label), notes (name, excerpt,
 *                         updated_at), rows (table_id, table, title,
 *                         updated_at), visits (title, starts_at)
 * @param callable $money  function ($minor, $currency): string
 * @param string   $base   the panel's address, ending in /
 * @param int      $limit  the entries the page holds
 * @return array entries (kind, id, at, title, detail, status_label, amount
 *               (minor units, null for none), amount_text, url), more
 *               (whether older entries are left), before (where the next page
 *               starts; 0 when there is none)
 */
function ws_customer_history_build($rights, $data, $money, $base, $limit)
{
    $entries = array();

    $entry = function ($kind, $id, $at, $title, $detail, $status_label, $amount, $amount_text, $url) {
        return array(
            'kind'         => $kind,
            'id'           => (int) $id,
            'at'           => (int) $at,
            'title'        => (string) $title,
            'detail'       => (string) $detail,
            'status_label' => (string) $status_label,
            'amount'       => $amount,
            'amount_text'  => (string) $amount_text,
            'url'          => (string) $url,
        );
    };

    if (!empty($rights['orders'])) {
        $statuses = array('complete' => lang('Complete'), 'exported' => lang('Exported'), 'cancelled' => lang('Cancelled'));

        foreach ((array) ($data['orders'] ?? array()) as $row) {
            $total = ws_customer_minor($row['total'] ?? 0);

            $entries[] = $entry('order', $row['id'], $row['order_date'] ?? 0, lang('Order') . ' #' . $row['order_number'], '',
                $statuses[$row['status']] ?? (string) $row['status'], $total, call_user_func($money, $total, ''), $base . 'view_order.php?id=' . (int) $row['id']);
        }
    }

    if (!empty($rights['invoices'])) {
        $statuses = array(
            'draft'          => lang('Draft'),
            'issued'         => lang('Issued'),
            'partially_paid' => lang('Partially paid'),
            'paid'           => lang('Paid'),
            'cancelled'      => lang('Cancelled'),
        );

        foreach ((array) ($data['invoices'] ?? array()) as $row) {
            $total = ws_customer_minor($row['grand_total'] ?? 0);
            $number = ((string) $row['full_number'] !== '') ? (string) $row['full_number'] : '#' . (int) $row['id'];
            $kind = (($row['direction'] ?? '') === 'purchase') ? lang('Purchase invoice') : lang('Invoice');
            $detail = implode(' · ', array_filter(array((string) ($row['account_title'] ?? ''), ws_customer_date_text($row['issue_date'] ?? '')), 'strlen'));

            $entries[] = $entry('invoice', $row['id'], $row['created_at'] ?? 0, $kind . ' ' . $number, $detail,
                $statuses[$row['status']] ?? (string) $row['status'], $total, call_user_func($money, $total, (string) $row['currency']), $base . 'edit_erp_invoice.php?id=' . (int) $row['id']);
        }
    }

    if (!empty($rights['sales_quotes'])) {
        foreach ((array) ($data['sales_quotes'] ?? array()) as $row) {
            $total = ws_customer_minor($row['grand_total'] ?? 0);
            $number = ((string) $row['full_number'] !== '') ? (string) $row['full_number'] : '#' . (int) $row['id'];

            $entries[] = $entry('sales_quote', $row['id'], $row['created_at'] ?? 0, lang('Sales quote') . ' ' . $number, (string) ($row['account_title'] ?? ''),
                (string) ($row['state_label'] ?? ''), $total, call_user_func($money, $total, (string) $row['currency']), $base . 'edit_erp_quote.php?id=' . (int) $row['id']);
        }
    }

    if (!empty($rights['forms'])) {
        foreach ((array) ($data['forms'] ?? array()) as $row) {
            if (empty($row['open'])) {
                continue;
            }

            $name = trim(trim((string) ($row['form_name'] ?? '')) . ' ' . (string) ($row['reference_code'] ?? ''));

            $entries[] = $entry('form', $row['id'], $row['submitted_timestamp'] ?? 0, ($name !== '') ? $name : lang('Form'), '',
                ((int) ($row['complete'] ?? 0) === 1) ? '' : lang('Incomplete'), null, '', $base . 'edit_submitted_form.php?id=' . (int) $row['id']);
        }
    }

    if (!empty($rights['workspace'])) {
        foreach ((array) ($data['messages'] ?? array()) as $row) {
            $entries[] = $entry('message', $row['id'], $row['created_at'] ?? 0, '#' . $row['channel_name'],
                trim((string) $row['sender_name'] . ': ' . (string) $row['excerpt'], ' :'), '', null, '',
                $base . 'workspace.php?channel=' . (int) $row['channel_id'] . '&message=' . (int) $row['id']);
        }

        foreach ((array) ($data['tasks'] ?? array()) as $row) {
            $entries[] = $entry('task', $row['id'], $row['created_at'] ?? 0, (string) $row['title'], lang('Task'),
                (string) ($row['status_label'] ?? ''), null, '', $base . 'workspace_tasks.php?task=' . (int) $row['id']);
        }

        foreach ((array) ($data['notes'] ?? array()) as $row) {
            $entries[] = $entry('note', $row['id'], $row['updated_at'] ?? 0, (string) $row['name'], (string) $row['excerpt'], '', null, '',
                $base . 'workspace_notes.php?note=' . (int) $row['id']);
        }

        // A customer visit on the Work Calendar, at the time it starts.
        foreach ((array) ($data['visits'] ?? array()) as $row) {
            $entries[] = $entry('visit', $row['id'], $row['starts_at'] ?? 0, (string) $row['title'], lang('Customer visit'), '', null, '',
                $base . 'workspace_calendar.php?month=' . date('Y-m', (int) ($row['starts_at'] ?? 0)) . '&event=' . (int) $row['id']);
        }

        foreach ((array) ($data['rows'] ?? array()) as $row) {
            $entries[] = $entry('wtable_row', $row['id'], $row['updated_at'] ?? 0, (string) $row['title'], (string) $row['table'], '', null, '',
                $base . 'workspace_wtables.php?id=' . (int) $row['table_id'] . '&row=' . (int) $row['id'] . '#ws-wt-row-form');
        }
    }

    usort($entries, function ($a, $b) {
        if ($a['at'] !== $b['at']) {
            return ($a['at'] < $b['at']) ? 1 : -1;
        }

        if ($a['kind'] !== $b['kind']) {
            return strcmp($a['kind'], $b['kind']);
        }

        return $b['id'] - $a['id'];
    });

    $limit = max(1, (int) $limit);

    if (count($entries) <= $limit) {
        return array('entries' => $entries, 'more' => false, 'before' => 0);
    }

    $boundary = $entries[$limit]['at'];
    $page = array_slice($entries, 0, $limit);
    $kept = array_values(array_filter($page, function ($item) use ($boundary) {
        return $item['at'] > $boundary;
    }));

    if (empty($kept)) {
        return array('entries' => $page, 'more' => true, 'before' => $boundary);
    }

    return array('entries' => $kept, 'more' => true, 'before' => $boundary + 1);
}

/**
 * The customer's orders below the page's start, newest first: the orders the
 * summary counts (ws_customer_order_where()).
 *
 * @param array $identity ws_customer_identity()
 * @param int   $before   0 for none
 * @param int   $limit
 * @return array[] id, order_number, status, total, order_date
 */
function ws_customer_history_order_rows($identity, $before, $limit)
{
    if (!defined('ECOMMERCE') || (ECOMMERCE !== true)) {
        return array();
    }

    $schema = function_exists('pg_schema_has');
    $where = ws_customer_order_where($identity, $schema && pg_schema_has('orders', 'erp_account_id'), $schema && pg_schema_has('orders', 'type'));

    if ($where === '') {
        return array();
    }

    return (array) db_items("SELECT id, order_number, status, total, order_date FROM orders
        WHERE " . $where . (($before > 0) ? " AND order_date < '" . (int) $before . "'" : '') . "
        ORDER BY order_date DESC, id DESC
        LIMIT " . max(1, (int) $limit));
}

/**
 * The invoices of the customer's current accounts below the page's start,
 * sales and purchase, drafts and cancelled ones too, newest first by when
 * they were written.
 *
 * @param array $identity ws_customer_identity()
 * @param int   $before   0 for none
 * @param int   $limit
 * @return array[] id, direction, full_number, status, issue_date, currency, grand_total, account_title, created_at
 */
function ws_customer_history_invoice_rows($identity, $before, $limit)
{
    $ids = ws_customer_ids($identity['erp_account_ids'] ?? array());

    if (empty($ids) || !defined('ERP_ENABLED') || !ERP_ENABLED) {
        return array();
    }

    return (array) db_items("SELECT i.id, i.direction, i.full_number, i.status, i.issue_date, i.currency, i.grand_total, i.created_at,
            a.title AS account_title
        FROM erp_invoices i
        LEFT JOIN erp_accounts a ON a.id = i.account_id
        WHERE i.account_id IN ('" . implode("','", $ids) . "')" . (($before > 0) ? " AND i.created_at < '" . (int) $before . "'" : '') . "
        ORDER BY i.created_at DESC, i.id DESC
        LIMIT " . max(1, (int) $limit));
}

/**
 * The sales quotes of the customer's current accounts below the page's start,
 * in every state, newest first, each with its state as the ERP calls it now
 * (erp_quote_state(): an open quote past its date is expired).
 *
 * @param array $identity ws_customer_identity()
 * @param int   $before   0 for none
 * @param int   $limit
 * @return array[] id, full_number, currency, grand_total, account_title, created_at, state_label
 */
function ws_customer_history_quote_rows($identity, $before, $limit)
{
    $ids = ws_customer_ids($identity['erp_account_ids'] ?? array());

    if (empty($ids) || !ws_erp_quotes_readable()) {
        return array();
    }

    $rows = (array) db_items("SELECT q.id, q.full_number, q.status, q.valid_until, q.currency, q.grand_total, q.created_at,
            a.title AS account_title, i.status AS invoice_status
        FROM erp_quotes q
        LEFT JOIN erp_accounts a ON a.id = q.account_id
        LEFT JOIN erp_invoices i ON i.id = q.invoice_id
        WHERE q.account_id IN ('" . implode("','", $ids) . "')" . (($before > 0) ? " AND q.created_at < '" . (int) $before . "'" : '') . "
        ORDER BY q.created_at DESC, q.id DESC
        LIMIT " . max(1, (int) $limit));

    $statuses = erp_quote_statuses();

    foreach ($rows as $index => $row) {
        $state = erp_quote_state($row);
        $rows[$index]['state_label'] = $statuses[$state][0] ?? $state;
    }

    return $rows;
}

/**
 * The forms the customer's contact submitted below the page's start, newest
 * first, each with whether the reader may open it: in a folder they may edit,
 * as the form screens and the form tag ask. More are read than the page
 * holds, since the ones the reader may not open are dropped.
 *
 * @param array $viewer
 * @param array $identity ws_customer_identity()
 * @param int   $before   0 for none
 * @param int   $limit
 * @return array[] id, reference_code, submitted_timestamp, complete, form_name, open
 */
function ws_customer_history_form_rows($viewer, $identity, $before, $limit)
{
    $contact_id = (int) ($identity['contact_id'] ?? 0);

    if ($contact_id <= 0) {
        return array();
    }

    $rows = (array) db_items("SELECT f.id, f.reference_code, f.submitted_timestamp, f.complete, p.page_folder, c.form_name
        FROM forms f
        LEFT JOIN page p ON p.page_id = f.page_id
        LEFT JOIN custom_form_pages c ON c.page_id = f.page_id
        WHERE f.contact_id = '" . $contact_id . "'" . (($before > 0) ? " AND f.submitted_timestamp < '" . (int) $before . "'" : '') . "
        ORDER BY f.submitted_timestamp DESC, f.id DESC
        LIMIT " . (max(1, (int) $limit) * 3));

    foreach ($rows as $index => $row) {
        $rows[$index]['open'] = ws_viewer_folder_open($viewer, (int) $row['page_folder']);
    }

    return $rows;
}

/**
 * The messages, tasks, notes and workflow table rows that tag the customer
 * or a record tied to it (ws_refs), below the page's start, newest first: a
 * message only in a channel the reader may read and not in this channel,
 * whose own messages are on screen already; a task only where the reader may
 * see it; a note only where the reader may read it, a row only where they
 * reach its table. Nothing for whoever is no member of the Workspace. The
 * API marks its reader: without tasks:read no task (history_tasks false),
 * and Claude's key reads messages and tasks only in channels Claude may be
 * asked in (history_claude), as every other Workspace read of channel
 * content (ws_api_refs_within()), and notes and tables as their own routes
 * do. The panel's reader carries neither mark.
 *
 * @param array $viewer
 * @param array $channel  the customer's channel
 * @param array $identity ws_customer_identity()
 * @param int   $before   0 for none
 * @param int   $limit
 * @return array messages (id, channel_id, channel_name, sender_name, excerpt,
 *               created_at), tasks (id, title, status_label, created_at),
 *               notes (id, name, excerpt, updated_at), rows (id, table_id,
 *               table, title, updated_at)
 */
function ws_customer_history_ref_rows($viewer, $channel, $identity, $before, $limit)
{
    $out = array('messages' => array(), 'tasks' => array(), 'notes' => array(), 'rows' => array());

    if (empty($viewer['member']) || !is_array($identity)) {
        return $out;
    }

    $records = array(
        'contact'      => ws_customer_ids(array($identity['contact_id'] ?? 0)),
        'user_account' => ws_customer_ids($identity['user_ids'] ?? array()),
        'erp_account'  => ws_customer_ids($identity['erp_account_ids'] ?? array()),
    );
    $records[$identity['customer']['type']][] = (int) $identity['customer']['id'];
    $where = array();
    $keys = array();

    foreach ($records as $type => $ids) {
        if (!empty($ids)) {
            $where[] = "(ref_type = '" . e($type) . "' AND ref_id IN ('" . implode("','", ws_customer_ids($ids)) . "'))";

            foreach (ws_customer_ids($ids) as $id) {
                $keys[] = $type . ':' . $id;
            }
        }
    }

    if (empty($where)) {
        return $out;
    }

    $sources = ws_ref_sources(db_items("SELECT source_type, source_id FROM ws_refs WHERE " . implode(' OR ', $where) . " ORDER BY id DESC LIMIT 500"));
    $message_ids = $sources['message'];
    $task_ids = $sources['task'];

    $after = ($before > 0) ? " AND created_at < '" . (int) $before . "'" : '';
    $claude = !empty($viewer['history_claude']);
    $channels = array();

    if (!empty($message_ids)) {
        foreach ((array) db_items("SELECT id, channel_id, sender_id, body, created_at FROM ws_messages
            WHERE id IN (" . implode(',', $message_ids) . ") AND deleted_at = 0 AND channel_id <> '" . (int) $channel['id'] . "'" . $after . "
            ORDER BY created_at DESC, id DESC") as $message) {
            $channel_id = (int) $message['channel_id'];

            if (!array_key_exists($channel_id, $channels)) {
                $channels[$channel_id] = ws_channel($channel_id);
            }

            if (!$channels[$channel_id] || !ws_can_read_channel($viewer, $channels[$channel_id])
                || ($claude && !ws_claude_channel_allowed($channels[$channel_id]))) {
                continue;
            }

            $out['messages'][] = array(
                'id'           => (int) $message['id'],
                'channel_id'   => $channel_id,
                'channel_name' => (string) $channels[$channel_id]['name'],
                'sender_name'  => ws_person_name($message['sender_id']),
                'excerpt'      => ws_plain_excerpt($viewer, $message['body'], 140),
                'created_at'   => (int) $message['created_at'],
            );

            if (count($out['messages']) >= $limit) {
                break;
            }
        }
    }

    if (!empty($task_ids) && ($viewer['history_tasks'] ?? true)) {
        $tasks = (array) db_items("SELECT * FROM ws_tasks WHERE id IN (" . implode(',', $task_ids) . ")" . $after . " ORDER BY created_at DESC, id DESC");
        $assignees = ws_task_assignees_map(array_keys($task_ids));

        foreach ($tasks as $task) {
            if (!ws_can_see_task($viewer, $task, $assignees[(int) $task['id']] ?? array())) {
                continue;
            }

            if ($claude && ((int) $task['channel_id'] > 0)) {
                if (!array_key_exists((int) $task['channel_id'], $channels)) {
                    $channels[(int) $task['channel_id']] = ws_channel((int) $task['channel_id']);
                }

                if (!$channels[(int) $task['channel_id']] || !ws_claude_channel_allowed($channels[(int) $task['channel_id']])) {
                    continue;
                }
            }

            $out['tasks'][] = array(
                'id'           => (int) $task['id'],
                'title'        => (string) $task['title'],
                'status_label' => ws_task_status_label($task['status']),
                'created_at'   => (int) $task['created_at'],
            );

            if (count($out['tasks']) >= $limit) {
                break;
            }
        }
    }

    foreach (ws_ref_notes_open($viewer, $sources['note'], $before) as $note) {
        if ($claude && !ws_api_note_reachable($viewer, $note)) {
            continue;
        }

        $out['notes'][] = array(
            'id'         => (int) $note['id'],
            'name'       => ws_note_title($viewer, $note),
            'excerpt'    => ws_plain_excerpt($viewer, $note['body'], 140),
            'updated_at' => (int) $note['updated_at'],
        );

        if (count($out['notes']) >= $limit) {
            break;
        }
    }

    foreach (ws_ref_rows_open($viewer, $sources['wtable_row'], $keys, $before) as $row) {
        if ($claude && !ws_api_wtable_reachable($viewer, array('id' => $row['table_id'], 'access' => $row['access']))) {
            continue;
        }

        $out['rows'][] = $row;

        if (count($out['rows']) >= $limit) {
            break;
        }
    }

    return $out;
}

/**
 * The customer visits on the Work Calendar that name the customer's contact
 * (ws_events.contact_id, 2026.11.0, 11.0.118) and start below the page's
 * start, latest first. Every member of the Workspace reads the plan, so
 * nothing more is asked of the reader; nothing before the column is there.
 *
 * @param array $identity ws_customer_identity()
 * @param int   $before   0 for none
 * @param int   $limit
 * @return array[] id, title, starts_at
 */
function ws_customer_history_visit_rows($identity, $before, $limit)
{
    $contact_id = (int) ($identity['contact_id'] ?? 0);

    if (($contact_id <= 0) || !ws_event_contacts_ready()) {
        return array();
    }

    return (array) db_items("SELECT id, title, starts_at FROM ws_events
        WHERE contact_id = '" . $contact_id . "' AND kind = 'visit'" . (($before > 0) ? " AND starts_at < '" . (int) $before . "'" : '') . "
        ORDER BY starts_at DESC, id DESC
        LIMIT " . max(1, (int) $limit));
}
