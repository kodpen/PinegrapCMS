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
 * which order status). The shop's events only where the shop is on.
 *
 * @return array event => label, when, record, right, filter
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
            'stock.low'            => array('label' => lang('A product runs low in stock'), 'when' => lang('When a product runs low in stock'), 'record' => 'product', 'right' => 'ecommerce', 'filter' => ''),
        );
    }

    $catalog += array(
        'customer.created' => array('label' => lang('A new contact is added'), 'when' => lang('When a new contact is added'), 'record' => 'contact', 'right' => 'contacts', 'filter' => ''),
        'customer.updated' => array('label' => lang('A contact is changed'), 'when' => lang('When a contact is changed'), 'record' => 'contact', 'right' => 'contacts', 'filter' => ''),
        'form.submitted'   => array('label' => lang('A form is submitted'), 'when' => lang('When a form is submitted'), 'record' => 'form', 'right' => 'forms', 'filter' => 'page'),
    );

    return $catalog;
}

/**
 * The record an event is about, as a tag names it: the kind and the id.
 * Each event carries the id under its own key (an order's id, a stock
 * warning's product_id, a submission's id).
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
 * Is this event written into the channels that watch its record? The orders'
 * events, a changed contact, a changed product and a product low in stock. A
 * submitted form is not: nobody can have tagged it before it existed.
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
 * itself, and its filter when it has one: the form (the page the form is on)
 * or the status the order moved to.
 *
 * @param array  $rule    type event: event, page_id, status
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
 * the scheduled actions waiting for it are queued and the channels watching
 * its record are told. The rows taken more than WS_EVENTS_KEEP ago are swept.
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
 * an hour; the starts stand at the head of a chain (depth 0, no source
 * action).
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

        foreach ((array) db_items("SELECT id, rules FROM ws_scheduled_actions WHERE status = 'active'" . ws_scheduled_actions_only() . " AND rules LIKE '%\"event\"%'") as $row) {
            foreach ((array) json_decode((string) $row['rules'], true) as $rule) {
                if (is_array($rule) && (($rule['type'] ?? '') === 'event')) {
                    $waiting[(int) $row['id']] = $rule;
                }
            }
        }
    }

    $now = time();
    $queued = 0;
    $context = json_encode(array('event' => (string) $event, 'payload' => (array) $payload, 'event_id' => (int) $event_id), JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR);

    foreach ($waiting as $action_id => $rule) {
        if (!ws_events_rule_matches($rule, $event, $payload)) {
            continue;
        }

        $recent = (int) db_value("SELECT COUNT(*) FROM ws_scheduled_queue WHERE action_id = '" . (int) $action_id . "' AND created_at > '" . ($now - 3600) . "'");

        if ($recent >= WS_SCHEDULED_CHAIN_HOURLY) {
            continue;
        }

        db("INSERT INTO ws_scheduled_queue (action_id, due_at, source_action_id, source_run_id, depth, status, created_at, taken_at, context)
            VALUES ('" . (int) $action_id . "', '" . $now . "', 0, 0, 0, 'waiting', '" . $now . "', 0, '" . e((string) $context) . "')");
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
    );

    if (($type === '') || ($id <= 0)) {
        return $values;
    }

    $values['{{record}}'] = '<#' . $type . ':' . $id . '>';

    $refs = ws_refs_resolve($viewer, array(array('sigil' => '#', 'type' => $type, 'id' => $id)));
    $ref = $refs[$type . ':' . $id] ?? null;

    if (!$ref || !$ref['known']) {
        return $values;
    }

    $values['{{record_title}}'] = (string) $ref['label'];

    if ((string) $ref['url'] !== '') {
        $values['{{record_link}}'] = ((substr((string) $ref['url'], 0, 1) === '/') && defined('URL_SCHEME') && defined('HOSTNAME_SETTING'))
            ? URL_SCHEME . HOSTNAME_SETTING . $ref['url']
            : (string) $ref['url'];
    }

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
 * an order. Null where the database is older.
 *
 * @param array $viewer
 * @return array|null
 */
function ws_events_js_config($viewer)
{
    if (!ws_events_ready() || !function_exists('ws_scheduled_join_ready') || !ws_scheduled_join_ready()) {
        return null;
    }

    $list = array();

    foreach (ws_events_catalog() as $key => $info) {
        if (!empty($viewer[$info['right']])) {
            $list[$key] = array('label' => $info['label'], 'filter' => $info['filter']);
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

    $statuses = array();

    foreach (ws_events_order_statuses() as $status => $label) {
        $statuses[] = array($status, $label);
    }

    return array('list' => $list, 'forms' => $forms, 'statuses' => $statuses);
}

/**
 * The texts of the event rule, the ready-made actions that use it and the
 * channel's watch switch.
 *
 * @return array key => text
 */
function ws_events_js_strings()
{
    return array(
        'sa_when_event'        => lang('When something happens'),
        'sa_event'             => lang('What happens'),
        'sa_event_help'        => lang('It runs once for each time it happens; {{record}} in a text tags the record it is about.'),
        'sa_event_form'        => lang('Form'),
        'sa_event_any_form'    => lang('Any form'),
        'sa_event_status'      => lang('Status it moves to'),
        'sa_event_any_status'  => lang('Any status'),
        'sa_tpl_order_event'   => lang('New order to the channel'),
        'sa_tpl_order_event_help' => lang('Every new order is written in the channel as it comes in.'),
        'sa_tpl_order_event_text' => lang('New order {{record}}'),
        'sa_tpl_form_event'    => lang('Web form to the channel'),
        'sa_tpl_form_event_help' => lang('Every submitted form is written in the channel with its fields; “Make a task of it” on the message makes it a task.'),
        'sa_tpl_stock_event'   => lang('Task when stock runs low'),
        'sa_tpl_stock_event_help' => lang('A product that falls to the site\'s low-stock threshold becomes a task to restock it.'),
        'sa_tpl_stock_event_task' => lang('Restock {{record_title}}'),
        'watch_label'          => lang('Write the events of the records tied to it in the channel'),
        'watch_help'           => lang('The orders of the channel\'s customer and the orders, products and contacts tagged here: a line when one is placed, shipped, cancelled or changed. Only the tag and what happened are written; amounts and addresses are not.'),
    );
}
