<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - what happens on the site, heard in the workspace: a new order,
 * an order shipped, a form submitted, a product low in stock.
 *
 * The event is kept where it is announced (pg_event_record(),
 * includes/fn/events.php): one row in ws_events_in, nothing more, because a
 * visitor is waiting for the checkout or the form to answer. The workspace's
 * own run takes the rows later (ws_events_process(), at the start of
 * ws_scheduled_run(): the open screen's ws_tick, the general job, the
 * workspace's job), oldest first, at most WS_EVENTS_BATCH a run, and does two
 * things with each:
 *
 * - the scheduled actions with an event rule that matches it are queued, one
 *   start each, the event carried with the start (the way a join rule queues
 *   its greeting), so the run that follows carries them out with the
 *   placeholders of the event filled in (scheduled.php);
 * - the channels that watch the record it is about hear about it in a line:
 *   a channel whose customer placed the order, a channel where the order,
 *   the product or the contact was tagged. The line names the record with its
 *   tag and says what happened - never an amount or an address - so the tag
 *   is drawn for each reader by their own rights and nothing is shown to a
 *   member that they could not open. A channel may switch this off
 *   (ws_channels.watch); discussions and guests' rooms never hear it. The
 *   same line is not written twice in WS_WATCH_QUIET seconds.
 *
 * A taken row is kept for WS_EVENTS_KEEP seconds and then swept.
 *
 * The workspace's own events start scheduled actions the same way: a task
 * completed, moved to another status or gone overdue, an approval request
 * settled, a guest writing in their room (ws_events_own()). They are kept by
 * ws_event(), called where each happens (tasks.php, reminders.php,
 * approvals.php, guests.php), and only while an active action waits for
 * that event, so a site that automates none of them writes nothing more. A
 * rule may be narrowed to one channel; an action is started only by what
 * its creator may read - the task, the channel of the request, the guest's
 * room - and an event caused by a scheduled action's own run carries that
 * run's chain depth, so two actions that cause each other's events stop at
 * WS_SCHEDULED_CHAIN_DEPTH. None of them is written into a watching channel.
 * A box of a flow's run that waits for a task counts as waiting for its
 * status change too, and the event wakes it (flow_runs.php).
 *
 * A submitted form tagged in a message (<#form:N>) is drawn with a small card
 * under the message: the first fields of the submission and a link to it,
 * for a reader who may open the submission.
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
 * The most events one run takes.
 */
define('WS_EVENTS_BATCH', 200);

/**
 * Seconds a taken event is kept before it is swept.
 */
define('WS_EVENTS_KEEP', 604800);

/**
 * Seconds in which a channel is not told the same thing twice.
 */
define('WS_WATCH_QUIET', 600);

/**
 * The most channels one event is written into.
 */
define('WS_WATCH_CHANNELS', 20);

/**
 * The most fields the card of a submitted form shows.
 */
define('WS_FORM_CARD_FIELDS', 4);

/**
 * Is the inbox of the site's events there (2026.4.8, 8.85)?
 *
 * @return bool
 */
function ws_events_ready()
{
    static $ready = null;

    if ($ready === null) {
        $ready = function_exists('pg_schema_has') && pg_schema_has('ws_events_in');
    }

    return $ready;
}

/**
 * Can a channel be told about the records it watches (2026.4.8, 8.85)?
 *
 * @return bool
 */
function ws_watch_ready()
{
    static $ready = null;

    if ($ready === null) {
        $ready = function_exists('waf_table_has_column') && waf_table_has_column('ws_channels', 'watch');
    }

    return $ready;
}

/**
 * The events a scheduled action can start on: what it is called, how the
 * rule says it, what kind of record it is about, the right the creator needs
 * to see that record, and the one filter it takes (page: which form; status:
 * which order status; task_status: the status a task moves to; outcome: how
 * an approval request ended). The workspace's own events may also be
 * narrowed to one channel (channel). The shop's events only where the shop
 * is on.
 *
 * @return array event => label, when, record, right, filter, channel
 */
function ws_events_catalog()
{
    static $catalog = null;

    if ($catalog !== null) {
        return $catalog;
    }

    $catalog = array();

    if (defined('ECOMMERCE') && (ECOMMERCE === true)) {
        $catalog += array(
            'order.created'        => array('label' => lang('A new order is placed'), 'when' => lang('When a new order is placed'), 'record' => 'order', 'right' => 'ecommerce', 'filter' => ''),
            'order.status_changed' => array('label' => lang('An order changes status'), 'when' => lang('When an order changes status'), 'record' => 'order', 'right' => 'ecommerce', 'filter' => 'status'),
            'order.shipped'        => array('label' => lang('An order is shipped'), 'when' => lang('When an order is shipped'), 'record' => 'order', 'right' => 'ecommerce', 'filter' => ''),
            'order.delivered'      => array('label' => lang('An order is delivered'), 'when' => lang('When an order is delivered'), 'record' => 'order', 'right' => 'ecommerce', 'filter' => ''),
            'order.cancelled'      => array('label' => lang('An order is cancelled'), 'when' => lang('When an order is cancelled'), 'record' => 'order', 'right' => 'ecommerce', 'filter' => ''),
            'order.return.requested' => array('label' => lang('A return is requested'), 'when' => lang('When a return is requested'), 'record' => 'order', 'right' => 'ecommerce', 'filter' => ''),
            'stock.low'            => array('label' => lang('A product runs low in stock'), 'when' => lang('When a product runs low in stock'), 'record' => 'product', 'right' => 'ecommerce', 'filter' => ''),
        );
    }

    $catalog += array(
        'customer.created' => array('label' => lang('A new contact is added'), 'when' => lang('When a new contact is added'), 'record' => 'contact', 'right' => 'contacts', 'filter' => ''),
        'customer.updated' => array('label' => lang('A contact is changed'), 'when' => lang('When a contact is changed'), 'record' => 'contact', 'right' => 'contacts', 'filter' => ''),
        'form.submitted'   => array('label' => lang('A form is submitted'), 'when' => lang('When a form is submitted'), 'record' => 'form', 'right' => 'forms', 'filter' => 'page'),
    );

    // The workspace's own: every member of the staff may wait for them, and
    // each event is checked against what its creator may read when it comes.
    $catalog += array(
        'workspace.task.completed'      => array('label' => lang('A task is completed'), 'when' => lang('When a task is completed'), 'record' => 'task', 'right' => 'member', 'filter' => '', 'channel' => true),
        'workspace.task.status_changed' => array('label' => lang('A task changes status'), 'when' => lang('When a task changes status'), 'record' => 'task', 'right' => 'member', 'filter' => 'task_status', 'channel' => true),
        'workspace.task.overdue'        => array('label' => lang('A task becomes overdue'), 'when' => lang('When a task becomes overdue'), 'record' => 'task', 'right' => 'member', 'filter' => '', 'channel' => true),
    );

    if (function_exists('ws_approvals_ready') && ws_approvals_ready()) {
        $catalog['workspace.approval.decided'] = array('label' => lang('An approval request is settled'), 'when' => lang('When an approval request is settled'), 'record' => 'approval', 'right' => 'member', 'filter' => 'outcome', 'channel' => true);
    }

    if (function_exists('ws_guests_ready') && ws_guests_ready()) {
        $catalog['workspace.guest.message'] = array('label' => lang('A guest writes'), 'when' => lang('When a guest writes'), 'record' => 'channel', 'right' => 'member', 'filter' => '', 'channel' => true);
    }

    if (function_exists('ws_mail_ready') && ws_mail_ready()) {
        $catalog['workspace.mail.received'] = array('label' => lang('An e-mail is received'), 'when' => lang('When an e-mail is received'), 'record' => 'channel', 'right' => 'member', 'filter' => '', 'channel' => true);
    }

    return $catalog;
}

/**
 * The workspace's own events a scheduled action can start on, the ones
 * ws_event() keeps.
 *
 * @return string[]
 */
function ws_events_own()
{
    return array(
        'workspace.task.completed',
        'workspace.task.status_changed',
        'workspace.task.overdue',
        'workspace.approval.decided',
        'workspace.guest.message',
        'workspace.mail.received',
    );
}

/**
 * Is this one of the workspace's own events?
 *
 * @param string $event
 * @return bool
 */
function ws_events_is_own($event)
{
    return in_array((string) $event, ws_events_own(), true);
}

/**
 * How an approval request can end, for the filter of its rule and for
 * {{record_status}}.
 *
 * @return array outcome => label
 */
function ws_events_approval_outcomes()
{
    return array(
        'approved'  => lang('Approved'),
        'rejected'  => lang('Rejected'),
        'expired'   => lang('Expired'),
        'withdrawn' => lang('Closed without a decision'),
    );
}

/**
 * The chain depth an event caused right now stands at. A scheduled action's
 * run says its own depth as it starts (ws_scheduled_execute()) and -1 as it
 * ends; what it does in between - a task it closes, a request it settles -
 * starts the actions waiting for that event one step further down the same
 * chain. Outside a run it is 0, the head of a chain.
 *
 * @param int|null $run_depth the depth of the run starting, -1 when it ends, null to read
 * @return int
 */
function ws_event_depth($run_depth = null)
{
    static $depth = 0;

    if ($run_depth !== null) {
        $depth = ((int) $run_depth < 0) ? 0 : (int) $run_depth + 1;
    }

    return $depth;
}

/**
 * The workspace's own events some active scheduled action, or a box of a
 * flow's run, waits for. Read once a request, with one look at the actions'
 * rules and one at the waiting boxes (flow_runs.php); a box that starts
 * waiting drops what was read ($forget), so the next call reads again.
 *
 * @param bool $forget drop what this request read
 * @return string[]
 */
function ws_events_awaited($forget = false)
{
    static $awaited = null;

    if ($forget) {
        $awaited = null;

        return array();
    }

    if ($awaited !== null) {
        return $awaited;
    }

    $awaited = array();

    if (function_exists('ws_scheduled_join_ready') && ws_scheduled_join_ready()) {
        foreach ((array) db_items("SELECT rules FROM ws_scheduled_actions WHERE status = 'active'" . ws_scheduled_actions_only() . " AND rules LIKE '%workspace.%'") as $row) {
            foreach ((array) json_decode((string) $row['rules'], true) as $rule) {
                if (is_array($rule) && (($rule['type'] ?? '') === 'event') && ws_events_is_own($rule['event'] ?? '')) {
                    $awaited[] = (string) $rule['event'];
                }
            }
        }
    }

    if (function_exists('ws_flow_waits_awaited')) {
        foreach (ws_flow_waits_awaited() as $event) {
            if (ws_events_is_own($event)) {
                $awaited[] = $event;
            }
        }
    }

    return $awaited = array_values(array_unique($awaited));
}

/**
 * Something that happened in the workspace, kept for the scheduled actions
 * and the boxes of flows' runs that wait for it: one row in ws_events_in,
 * worked off by the next run like the site's events. Nothing is kept when no
 * active action and no waiting box waits for this event, or when the event
 * is not one of the workspace's own (ws_events_own()).
 * The webhooks hear about the workspace through pg_announce(), on their own
 * terms; this is for the site's own scheduled actions and flows only.
 *
 * @param string $event
 * @param array  $payload channel_id, and the id of the task, the request or the message
 * @return bool whether it was kept
 */
function ws_event($event, $payload = array())
{
    $event = (string) $event;

    if (!ws_events_is_own($event) || !ws_events_ready() || !in_array($event, ws_events_awaited(), true)) {
        return false;
    }

    $payload = (array) $payload;
    $depth = ws_event_depth();

    if ($depth > 0) {
        $payload['_depth'] = $depth;
    }

    $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR);

    db("INSERT INTO ws_events_in (event, payload, created_at, taken_at)
        VALUES ('" . e($event) . "', '" . e(($json === false) ? '{}' : $json) . "', UNIX_TIMESTAMP(), 0)");

    return true;
}

/**
 * May the creator of a scheduled action read what one of the workspace's
 * own events is about: the task, or the channel of the request or of the
 * guest's message? Asked before the event starts their action.
 *
 * @param int    $user_id
 * @param string $event
 * @param array  $payload
 * @return bool
 */
function ws_events_creator_sees($user_id, $event, $payload)
{
    static $viewers = array();

    $user_id = (int) $user_id;

    if (!array_key_exists($user_id, $viewers)) {
        $viewers[$user_id] = function_exists('ws_change_viewer_for_id') ? ws_change_viewer_for_id($user_id) : null;
    }

    $viewer = $viewers[$user_id];

    if (!is_array($viewer) || empty($viewer['member'])) {
        return false;
    }

    if (strpos((string) $event, 'workspace.task.') === 0) {
        $task = ws_task((int) ($payload['id'] ?? 0));

        return $task && ws_can_see_task($viewer, $task);
    }

    $channel = ws_channel((int) ($payload['channel_id'] ?? 0));

    return $channel && ws_can_read_channel($viewer, $channel);
}

/**
 * The channels a rule of the workspace's own events may be narrowed to, for
 * this person: the public ones, and the private channels and guests' rooms
 * they are in. Never a discussion.
 *
 * @param array $viewer
 * @return array[] [id, name as the list shows it]
 */
function ws_events_channels($viewer)
{
    $out = array();

    foreach ((array) db_items("SELECT c.id, c.name, c.kind
        FROM ws_channels c
        LEFT JOIN ws_channel_members m ON m.channel_id = c.id AND m.user_id = '" . (int) $viewer['id'] . "'
        WHERE c.archived_at = 0 AND c.kind IN ('public', 'private', 'guest') AND (c.kind = 'public' OR m.user_id IS NOT NULL)
        ORDER BY c.name
        LIMIT 300") as $row) {
        $out[] = array((int) $row['id'], (((string) $row['kind'] === 'public') ? '#' : '🔒 ') . $row['name']);
    }

    return $out;
}

/**
 * The record an event is about, as a tag names it: the kind and the id.
 * Each event carries the id under its own key (an order's id, a stock
 * warning's product_id, a submission's id). A task's events are about the
 * task; a settled approval request about the task it asked about, otherwise
 * its channel; a guest's message about the guest's room.
 *
 * @param string $event
 * @param array  $payload
 * @return array type ('' for none), id
 */
function ws_events_record_of($event, $payload)
{
    $event = (string) $event;
    $payload = (array) $payload;

    if (strpos($event, 'order.') === 0) {
        return array('order', (int) ($payload['id'] ?? 0));
    }

    if (strpos($event, 'workspace.task.') === 0) {
        return array('task', (int) ($payload['id'] ?? 0));
    }

    if ($event === 'workspace.approval.decided') {
        return (((string) ($payload['record_type'] ?? '') === 'task') && ((int) ($payload['record_id'] ?? 0) > 0))
            ? array('task', (int) $payload['record_id'])
            : array('channel', (int) ($payload['channel_id'] ?? 0));
    }

    if (($event === 'workspace.guest.message') || ($event === 'workspace.mail.received')) {
        return array('channel', (int) ($payload['channel_id'] ?? 0));
    }

    switch ($event) {
        case 'stock.low':
        case 'product.updated':
            return array('product', (int) ($payload['product_id'] ?? ($payload['id'] ?? 0)));

        case 'customer.created':
        case 'customer.updated':
            return array('contact', (int) ($payload['id'] ?? 0));

        case 'form.submitted':
            return array('form', (int) ($payload['id'] ?? 0));
    }

    return array('', 0);
}

/**
 * Is this event written into the channels that watch its record? An order
 * placed, moved to another status, shipped, delivered or cancelled, a changed
 * contact, a changed product and a product low in stock. A submitted form is
 * not: nobody can have tagged it before it existed. A return requested
 * (order.return.requested) is not either: it only starts scheduled actions,
 * and ws_watch_line() has no line for it.
 *
 * @param string $event
 * @return bool
 */
function ws_events_watched($event)
{
    return in_array((string) $event, array(
        'order.created', 'order.status_changed', 'order.shipped', 'order.delivered', 'order.cancelled',
        'customer.updated', 'product.updated', 'stock.low',
    ), true);
}

/**
 * Does an event rule of a scheduled action match this event? The event
 * itself, and its filters when it has them: the form (the page the form is
 * on), the status the order or the task moved to or how the approval request
 * ended (status), and the channel the workspace's event happened in.
 *
 * @param array  $rule    type event: event, page_id, status, channel_id
 * @param string $event
 * @param array  $payload
 * @return bool
 */
function ws_events_rule_matches($rule, $event, $payload)
{
    if (!is_array($rule) || (($rule['type'] ?? '') !== 'event') || ((string) ($rule['event'] ?? '') !== (string) $event)) {
        return false;
    }

    $payload = (array) $payload;

    if (((string) ($rule['status'] ?? '') !== '') && ((string) ($payload['status'] ?? '') !== (string) $rule['status'])) {
        return false;
    }

    if (((int) ($rule['page_id'] ?? 0) > 0) && ((int) ($payload['form_id'] ?? 0) !== (int) $rule['page_id'])) {
        return false;
    }

    if (((int) ($rule['channel_id'] ?? 0) > 0) && ((int) ($payload['channel_id'] ?? 0) !== (int) $rule['channel_id'])) {
        return false;
    }

    return true;
}

/**
 * The statuses an order moves between, with their names.
 *
 * @return array status => label
 */
function ws_events_order_statuses()
{
    return array(
        'incomplete' => lang('Incomplete'),
        'complete'   => lang('Complete'),
        'exported'   => lang('Exported'),
        'cancelled'  => lang('Cancelled'),
    );
}

/**
 * The forms of the site, for the filter of a form rule: the page each is on,
 * and its name.
 *
 * @return array page id => name
 */
function ws_events_forms()
{
    $out = array();

    foreach ((array) db_items("SELECT c.page_id, c.form_name, p.page_name
        FROM custom_form_pages c
        INNER JOIN page p ON p.page_id = c.page_id
        ORDER BY c.form_name, p.page_name
        LIMIT 300") as $row) {
        $name = ((string) $row['form_name'] !== '') ? (string) $row['form_name'] : (string) $row['page_name'];
        $out[(int) $row['page_id']] = $name;
    }

    return $out;
}

/**
 * Is there an event the run has not taken yet? One indexed read, for
 * ws_scheduled_due() to start a run.
 *
 * @return bool
 */
function ws_events_due()
{
    return ws_events_ready() && (bool) db_value("SELECT id FROM ws_events_in WHERE taken_at = 0 LIMIT 1");
}

/**
 * Works off the events the run has not taken yet, oldest first: each is
 * claimed (a conditional UPDATE, so two runs that meet do not both take it),
 * the scheduled actions waiting for it are queued, the boxes of flows' runs
 * waiting for it are woken and the channels watching its record are told.
 * The rows taken more than WS_EVENTS_KEEP ago are swept.
 *
 * @param int $limit
 * @return int how many events were taken
 */
function ws_events_process($limit = WS_EVENTS_BATCH)
{
    if (!ws_events_ready()) {
        return 0;
    }

    $now = time();

    db("DELETE FROM ws_events_in WHERE taken_at > 0 AND taken_at < '" . ($now - WS_EVENTS_KEEP) . "' LIMIT 1000");

    $rows = (array) db_items("SELECT * FROM ws_events_in WHERE taken_at = 0 ORDER BY id LIMIT " . max(1, (int) $limit));
    $taken = 0;

    foreach ($rows as $row) {
        db("UPDATE ws_events_in SET taken_at = '" . time() . "' WHERE id = '" . (int) $row['id'] . "' AND taken_at = 0");

        if (mysqli_affected_rows(db::$con) !== 1) {
            continue;
        }

        $taken++;
        $payload = json_decode((string) $row['payload'], true);
        $payload = is_array($payload) ? $payload : array();

        ws_events_start((int) $row['id'], (string) $row['event'], $payload);

        // The boxes of the flows' runs waiting for it (flow_runs.php).
        if (function_exists('ws_flow_wake')) {
            ws_flow_wake((string) $row['event'], $payload);
        }

        if (ws_watch_ready()) {
            ws_watch_write((string) $row['event'], $payload);
        }
    }

    return $taken;
}

/**
 * Queues the scheduled actions an event starts: one start for each action
 * whose event rule matches, the event carried with it. Like a start from a
 * chain, an action is not started more than WS_SCHEDULED_CHAIN_HOURLY times
 * an hour; the starts stand at the head of a chain (no source action), at
 * depth 0 - or, for a workspace event a scheduled action's run caused, one
 * step below that run, and not at all past WS_SCHEDULED_CHAIN_DEPTH. A
 * workspace event starts an action only when its creator may read what it
 * is about.
 *
 * @param int    $event_id
 * @param string $event
 * @param array  $payload
 * @return int how many were queued
 */
function ws_events_start($event_id, $event, $payload)
{
    if (!function_exists('ws_scheduled_join_ready') || !ws_scheduled_join_ready()) {
        return 0;
    }

    static $waiting = null;

    if ($waiting === null) {
        $waiting = array();

        foreach ((array) db_items("SELECT id, rules, created_by FROM ws_scheduled_actions WHERE status = 'active'" . ws_scheduled_actions_only() . " AND rules LIKE '%\"event\"%'") as $row) {
            foreach ((array) json_decode((string) $row['rules'], true) as $rule) {
                if (is_array($rule) && (($rule['type'] ?? '') === 'event')) {
                    $waiting[(int) $row['id']] = array('rule' => $rule, 'created_by' => (int) $row['created_by']);
                }
            }
        }
    }

    $payload = (array) $payload;
    $depth = max(0, (int) ($payload['_depth'] ?? 0));
    unset($payload['_depth']);

    if ($depth >= WS_SCHEDULED_CHAIN_DEPTH) {
        return 0;
    }

    $own = ws_events_is_own($event);
    $now = time();
    $queued = 0;
    $context = json_encode(array('event' => (string) $event, 'payload' => $payload, 'event_id' => (int) $event_id), JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR);

    foreach ($waiting as $action_id => $item) {
        if (!ws_events_rule_matches($item['rule'], $event, $payload)) {
            continue;
        }

        if ($own && !ws_events_creator_sees($item['created_by'], $event, $payload)) {
            continue;
        }

        $recent = (int) db_value("SELECT COUNT(*) FROM ws_scheduled_queue WHERE action_id = '" . (int) $action_id . "' AND created_at > '" . ($now - 3600) . "'");

        if ($recent >= WS_SCHEDULED_CHAIN_HOURLY) {
            continue;
        }

        db("INSERT INTO ws_scheduled_queue (action_id, due_at, source_action_id, source_run_id, depth, status, created_at, taken_at, context)
            VALUES ('" . (int) $action_id . "', '" . $now . "', 0, 0, '" . $depth . "', 'waiting', '" . $now . "', 0, '" . e((string) $context) . "')");
        $queued++;
    }

    return $queued;
}

/**
 * What the placeholders of an action started by an event become, for the
 * action's creator: the event's name, the record's tag, its link and name,
 * a form's fields, the customer, an order's amount and status. Each is
 * filled only where the creator may see the record.
 *
 * @param array  $viewer the creator
 * @param string $event
 * @param array  $payload
 * @return array placeholder => value
 */
function ws_events_fill_values($viewer, $event, $payload)
{
    $catalog = ws_events_catalog();
    list($type, $id) = ws_events_record_of($event, $payload);
    $info = $catalog[$event] ?? null;
    $values = array(
        '{{event}}'         => $info ? (string) $info['label'] : (string) $event,
        '{{record}}'        => '',
        '{{record_link}}'   => '',
        '{{record_title}}'  => '',
        '{{form_fields}}'   => '',
        '{{customer}}'      => '',
        '{{amount}}'        => '',
        '{{record_status}}' => '',
        '{{actor}}'         => '',
        '{{message}}'       => '',
    );

    if (($type === '') || ($id <= 0)) {
        return $values;
    }

    $values['{{record}}'] = '<#' . $type . ':' . $id . '>';

    $refs = ws_refs_resolve($viewer, array(array('sigil' => '#', 'type' => $type, 'id' => $id)));
    $ref = $refs[$type . ':' . $id] ?? null;

    if (ws_events_is_own($event)) {
        return ws_events_own_values($viewer, $event, (array) $payload, $values, $ref);
    }

    if (!$ref || !$ref['known']) {
        return $values;
    }

    $values['{{record_title}}'] = (string) $ref['label'];
    $values['{{record_link}}'] = ws_events_absolute_url($ref['url']);

    switch ($type) {
        case 'order':
            $order = db_item("SELECT total, status, billing_first_name, billing_last_name, billing_company FROM orders WHERE id = '" . $id . "'");

            if (is_array($order)) {
                $statuses = ws_events_order_statuses();
                $status = (($event === 'order.status_changed') && ((string) ($payload['status'] ?? '') !== '')) ? (string) $payload['status'] : (string) $order['status'];
                $values['{{customer}}'] = trim(((string) $order['billing_company'] !== '') ? (string) $order['billing_company'] : $order['billing_first_name'] . ' ' . $order['billing_last_name']);
                $values['{{amount}}'] = ws_money_out((int) $order['total']);
                $values['{{record_status}}'] = (string) ($statuses[$status] ?? $status);
            }

            break;

        case 'contact':
            $values['{{customer}}'] = (string) $ref['label'];
            break;

        case 'form':
            $values['{{form_fields}}'] = ws_events_form_text($id);
            $contact_id = (int) db_value("SELECT contact_id FROM forms WHERE id = '" . $id . "'");

            if (($contact_id > 0) && !empty($viewer['contacts'])) {
                $contact = ws_refs_resolve($viewer, array(array('sigil' => '#', 'type' => 'contact', 'id' => $contact_id)))['contact:' . $contact_id] ?? null;
                $values['{{customer}}'] = ($contact && $contact['known']) ? (string) $contact['label'] : '';
            }

            break;
    }

    return $values;
}

/**
 * A panel address as a whole one, for an e-mail or a webhook: the site's
 * scheme and host before a path that starts at the root.
 *
 * @param string $url
 * @return string
 */
function ws_events_absolute_url($url)
{
    $url = (string) $url;

    return (($url !== '') && (substr($url, 0, 1) === '/') && defined('URL_SCHEME') && defined('HOSTNAME_SETTING'))
        ? URL_SCHEME . HOSTNAME_SETTING . $url
        : $url;
}

/**
 * What the placeholders become for one of the workspace's own events, for
 * the action's creator: the task or the channel tagged, its name and link,
 * the status the task moved to or how the request ended, who did it, and a
 * guest's name and words. The name and the link only where the creator may
 * read the record or the channel; the tag is drawn for each reader by their
 * own rights.
 *
 * @param array      $viewer  the creator
 * @param string     $event
 * @param array      $payload
 * @param array      $values  what ws_events_fill_values() has so far
 * @param array|null $ref     the record, resolved for the creator
 * @return array placeholder => value
 */
function ws_events_own_values($viewer, $event, $payload, $values, $ref)
{
    $known = $ref && $ref['known'];

    if ($known) {
        $values['{{record_title}}'] = (string) $ref['label'];
        $values['{{record_link}}'] = ws_events_absolute_url($ref['url']);
    }

    if ((int) ($payload['actor_id'] ?? 0) > 0) {
        $values['{{actor}}'] = '<@user:' . (int) $payload['actor_id'] . '>';
    }

    if (strpos($event, 'workspace.task.') === 0) {
        $values['{{record_status}}'] = $known ? ws_task_status_label((string) ($payload['status'] ?? '')) : '';

        return $values;
    }

    // A request or a guest's message: what it says only to a creator who
    // reads its channel still; the link opens the message itself.
    $channel = ws_channel((int) ($payload['channel_id'] ?? 0));

    if (!$channel || !is_array($viewer) || empty($viewer['member']) || !ws_can_read_channel($viewer, $channel)) {
        return $values;
    }

    $message_id = (int) ($payload['message_id'] ?? 0);
    $link = (defined('OUTPUT_PATH') && defined('OUTPUT_SOFTWARE_DIRECTORY'))
        ? ws_events_absolute_url(OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/workspace.php?channel=' . (int) $channel['id'] . (($message_id > 0) ? '&message=' . $message_id : ''))
        : '';

    if ($event === 'workspace.approval.decided') {
        $values['{{record_title}}'] = (string) ($payload['title'] ?? '');
        $values['{{record_link}}'] = $link;
        $values['{{record_status}}'] = (string) (ws_events_approval_outcomes()[(string) ($payload['status'] ?? '')] ?? '');

        return $values;
    }

    $values['{{record_link}}'] = $link;

    // An e-mail: who wrote and the subject; its text stays on the card.
    if ($event === 'workspace.mail.received') {
        $values['{{record_title}}'] = (string) ($payload['subject'] ?? '');
        $values['{{customer}}'] = (string) ($payload['customer'] ?? '');

        return $values;
    }

    $values['{{customer}}'] = (string) ($payload['guest'] ?? '');
    $values['{{message}}'] = (string) ($payload['text'] ?? '');

    return $values;
}

/**
 * The fields of submitted forms as they were filled in, in the order of the
 * form: a label and a value each, the values of one field (the boxes ticked)
 * joined. A file is never written out - only that one is attached - nor a
 * signature, nor a field kept for the office; a field left empty is left
 * out.
 *
 * @param int[] $form_ids submissions (forms.id)
 * @param int   $limit    the most fields of each, 0 for all
 * @return array submission id => [[label, value], ...]
 */
function ws_events_form_values($form_ids, $limit = 0)
{
    $form_ids = array_values(array_unique(array_filter(array_map('intval', (array) $form_ids))));

    if (empty($form_ids)) {
        return array();
    }

    $fields = array();

    foreach ((array) db_items("SELECT form_data.form_id, form_data.form_field_id, form_data.name, form_data.data, form_data.file_id,
            form_fields.label, form_fields.type, form_fields.office_use_only
        FROM form_data
        LEFT JOIN form_fields ON form_fields.id = form_data.form_field_id
        WHERE form_data.form_id IN (" . implode(',', $form_ids) . ")
        ORDER BY form_data.form_id, form_fields.sort_order, form_data.id
        LIMIT 2000") as $row) {
        $type = (string) ($row['type'] ?? '');

        if (($type === 'information') || ((int) ($row['office_use_only'] ?? 0) === 1)) {
            continue;
        }

        $label = trim(strip_tags((string) (($row['label'] ?? '') !== '' ? $row['label'] : $row['name'])));
        $label = rtrim(preg_replace('/\s+/u', ' ', $label), ': ');

        if ($type === 'file upload' || ((int) $row['file_id'] > 0)) {
            $value = lang('(file attached)');
        } elseif ($type === 'signature') {
            $value = lang('(signed)');
        } else {
            $value = trim(preg_replace('/\s+/u', ' ', (string) $row['data']));
        }

        if (($value === '') || ($label === '')) {
            continue;
        }

        $key = ((int) $row['form_field_id'] > 0) ? 'f' . (int) $row['form_field_id'] : 'n' . $row['name'];
        $form_id = (int) $row['form_id'];

        if (isset($fields[$form_id][$key])) {
            $fields[$form_id][$key][1] .= ', ' . $value;
            continue;
        }

        $fields[$form_id][$key] = array($label, $value);
    }

    $out = array();

    foreach ($fields as $form_id => $list) {
        $list = array_values($list);

        foreach ($list as $index => $field) {
            $list[$index][1] = mb_substr($field[1], 0, 300);
        }

        $out[$form_id] = ($limit > 0) ? array_slice($list, 0, $limit) : $list;
    }

    return $out;
}

/**
 * The fields of one submission as lines of text, for {{form_fields}}.
 *
 * @param int $form_id
 * @return string
 */
function ws_events_form_text($form_id)
{
    $lines = array();

    foreach (ws_events_form_values(array((int) $form_id))[(int) $form_id] ?? array() as $field) {
        $lines[] = $field[0] . ': ' . $field[1];
    }

    return implode("\n", $lines);
}

/**
 * The cards of the submitted forms tagged in a text: one under the message
 * for each form the reader may open (ws_refs_resolve() gave it its fields),
 * with the first fields and a link to the submission.
 *
 * @param string $body
 * @param array  $refs ws_refs_resolve() output covering the text's tokens
 * @return string HTML
 */
function ws_form_cards_html($body, $refs)
{
    if (strpos((string) $body, '<#form:') === false) {
        return '';
    }

    $html = '';
    $seen = array();

    foreach (ws_tokens($body) as $token) {
        $key = $token['type'] . ':' . (int) $token['id'];

        if (($token['type'] !== 'form') || isset($seen[$key])) {
            continue;
        }

        $seen[$key] = true;
        $ref = $refs[$key] ?? null;

        if (!$ref || !$ref['known'] || empty($ref['fields'])) {
            continue;
        }

        $rows = '';

        foreach ($ref['fields'] as $field) {
            $rows .= '<dt>' . h($field[0]) . '</dt><dd>' . h(mb_substr((string) $field[1], 0, 160)) . '</dd>';
        }

        $head = '<i class="bi bi-ui-checks" aria-hidden="true"></i><span>' . h($ref['label']) . '</span>'
            . (((string) $ref['meta'] !== '') ? '<small>' . h($ref['meta']) . '</small>' : '');

        $html .= '<div class="ws-form-card">'
            . (((string) $ref['url'] !== '') ? '<a class="ws-form-card-head" href="' . h($ref['url']) . '">' . $head . '</a>' : '<div class="ws-form-card-head">' . $head . '</div>')
            . '<dl class="ws-form-card-fields">' . $rows . '</dl></div>';
    }

    return $html;
}

/**
 * The customer of an order, as the channels name customers: its contact,
 * the user who placed it, its current account.
 *
 * @param int $order_id
 * @return array contact_id, user_id, erp_account_id
 */
function ws_watch_order_customer($order_id)
{
    $erp = function_exists('pg_schema_has') && pg_schema_has('orders', 'erp_account_id');
    $row = db_item("SELECT contact_id, user_id" . ($erp ? ', erp_account_id' : '') . " FROM orders WHERE id = '" . (int) $order_id . "'");

    return array(
        'contact_id'     => is_array($row) ? (int) $row['contact_id'] : 0,
        'user_id'        => is_array($row) ? (int) $row['user_id'] : 0,
        'erp_account_id' => (is_array($row) && $erp) ? (int) $row['erp_account_id'] : 0,
    );
}

/**
 * The channels that watch a record: the ones it was tagged in, and - for an
 * order and a contact - the ones about its customer. Team channels only
 * (public and private), not archived, watching.
 *
 * @param string $type
 * @param int    $id
 * @return array[] channel rows
 */
function ws_watch_channels($type, $id)
{
    $id = (int) $id;
    $where = array();

    $tagged = array_map('intval', (array) db_values("SELECT DISTINCT channel_id FROM ws_refs
        WHERE ref_type = '" . e($type) . "' AND ref_id = '" . $id . "' AND channel_id > 0
        LIMIT 200"));

    if (!empty($tagged)) {
        $where[] = "id IN (" . implode(',', $tagged) . ")";
    }

    $customer = array('contact_id' => 0, 'user_id' => 0, 'erp_account_id' => 0);

    if ($type === 'order') {
        $customer = ws_watch_order_customer($id);
    } elseif ($type === 'contact') {
        $customer['contact_id'] = $id;
    }

    if ($customer['contact_id'] > 0) {
        $where[] = "contact_id = '" . $customer['contact_id'] . "'";
    }

    if (function_exists('ws_customer_ready') && ws_customer_ready()) {
        if ($customer['user_id'] > 0) {
            $where[] = "(customer_type = 'user_account' AND customer_id = '" . $customer['user_id'] . "')";
        }

        if ($customer['erp_account_id'] > 0) {
            $where[] = "(customer_type = 'erp_account' AND customer_id = '" . $customer['erp_account_id'] . "')";
        }
    }

    if (empty($where)) {
        return array();
    }

    return (array) db_items("SELECT * FROM ws_channels
        WHERE archived_at = 0 AND kind IN ('public', 'private') AND watch = 1 AND (" . implode(' OR ', $where) . ")
        ORDER BY last_message_at DESC
        LIMIT " . WS_WATCH_CHANNELS);
}

/**
 * The line a watching channel is told: the record's tag and what happened.
 * Nothing else of the record - its tag is drawn for each reader by their
 * own rights.
 *
 * @param string $event
 * @param string $type
 * @param int    $id
 * @param array  $payload
 * @return string '' when the event says nothing to a channel
 */
function ws_watch_line($event, $type, $id, $payload)
{
    $tag = '<#' . $type . ':' . (int) $id . '>';

    switch ($event) {
        case 'order.created':
            return lang(array('string' => 'New order: {var:1}', 'vars' => $tag));

        case 'order.status_changed':
            $statuses = ws_events_order_statuses();
            $status = (string) ($payload['status'] ?? '');

            return ($status !== '') ? lang(array('string' => 'The status of {var:1} is now {var:2}', 'vars' => array($tag, $statuses[$status] ?? $status))) : '';

        case 'order.shipped':
            return lang(array('string' => '{var:1} was shipped', 'vars' => $tag));

        case 'order.delivered':
            return lang(array('string' => '{var:1} was delivered', 'vars' => $tag));

        case 'order.cancelled':
            return lang(array('string' => '{var:1} was cancelled', 'vars' => $tag));

        case 'customer.updated':
        case 'product.updated':
            return lang(array('string' => '{var:1} was updated', 'vars' => $tag));

        case 'stock.low':
            return lang(array('string' => '{var:1} is running low in stock', 'vars' => $tag));
    }

    return '';
}

/**
 * Tells the channels watching an event's record about it, in a line each. A
 * channel that was told the same within WS_WATCH_QUIET seconds is not told
 * again: a burst of the same event leaves one line.
 *
 * @param string $event
 * @param array  $payload
 * @return int how many channels were told
 */
function ws_watch_write($event, $payload)
{
    if (!ws_events_watched($event) || !ws_watch_ready()) {
        return 0;
    }

    list($type, $id) = ws_events_record_of($event, $payload);

    if (($type === '') || ($id <= 0)) {
        return 0;
    }

    $body = ws_watch_line($event, $type, $id, $payload);

    if ($body === '') {
        return 0;
    }

    $told = 0;
    $since = time() - WS_WATCH_QUIET;

    foreach (ws_watch_channels($type, $id) as $channel) {
        if (db_value("SELECT id FROM ws_messages
            WHERE channel_id = '" . (int) $channel['id'] . "' AND sender_kind = 'system' AND created_at > '" . $since . "'
            AND body = '" . e($body) . "' LIMIT 1")) {
            continue;
        }

        ws_message_system((int) $channel['id'], $body);
        $told++;
    }

    return $told;
}

/**
 * Whether a channel hears about the records it watches, for its settings:
 * null where it cannot (the database is older, a discussion, a guest's
 * room).
 *
 * @param array $channel
 * @return bool|null
 */
function ws_watch_channel_state($channel)
{
    if (!ws_watch_ready() || !in_array((string) ($channel['kind'] ?? ''), array('public', 'private'), true)) {
        return null;
    }

    return (int) ($channel['watch'] ?? 1) === 1;
}

/**
 * What the scheduled actions' form needs to offer an event rule, for this
 * person: the events whose records they may see, the forms, the statuses of
 * an order, and for the workspace's own events the values of their filter
 * and the channels they may be narrowed to. Null where the database is
 * older.
 *
 * @param array $viewer
 * @return array|null
 */
function ws_events_js_config($viewer)
{
    if (!ws_events_ready() || !function_exists('ws_scheduled_join_ready') || !ws_scheduled_join_ready()) {
        return null;
    }

    $pairs = function ($map) {
        $out = array();

        foreach ($map as $value => $label) {
            $out[] = array($value, $label);
        }

        return $out;
    };

    // The values of a workspace event's filter, with what the list calls them.
    $own_filters = array(
        'task_status' => array('filter_label' => lang('Status it moves to'), 'any' => lang('Any status'), 'options' => $pairs(ws_task_statuses())),
        'outcome'     => array('filter_label' => lang('How it ended'), 'any' => lang('However it ended'), 'options' => $pairs(ws_events_approval_outcomes())),
    );

    $list = array();
    $channels = false;

    foreach (ws_events_catalog() as $key => $info) {
        if (empty($viewer[$info['right']])) {
            continue;
        }

        $list[$key] = array('label' => $info['label'], 'filter' => $info['filter']);

        if (isset($own_filters[$info['filter']])) {
            $list[$key] += $own_filters[$info['filter']];
        }

        if (!empty($info['channel'])) {
            $list[$key]['channel'] = true;
            $channels = true;
        }
    }

    if (empty($list)) {
        return null;
    }

    $forms = array();

    if (isset($list['form.submitted'])) {
        foreach (ws_events_forms() as $page_id => $name) {
            $forms[] = array($page_id, $name);
        }
    }

    return array(
        'list'     => $list,
        'forms'    => $forms,
        'statuses' => $pairs(ws_events_order_statuses()),
        'channels' => $channels ? ws_events_channels($viewer) : array(),
    );
}

/**
 * The texts of the channel's watch switch.
 *
 * @return array key => text
 */
function ws_events_js_strings()
{
    return array(
        'watch_label'          => lang('Write the events of the records tied to it in the channel'),
        'watch_help'           => lang('The orders of the channel\'s customer and the orders, products and contacts tagged here: a line when one is placed, shipped, cancelled or changed. Only the tag and what happened are written; amounts and addresses are not.'),
    );
}
