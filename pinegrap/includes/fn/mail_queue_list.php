<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The mail queue screen's lists (mail_queue.php): the e-mail queue
 * (mail_outbox) and the text message queue (sms_outbox), drawn by the list
 * table driver (includes/fn/list_table.php). Their sources
 * (pg_mail_queue_list_source(), pg_sms_queue_list_source()) serve the
 * screen and the driver's list_table_rows action alike: the screen prints
 * the first rows, the next ones come in batches as the list scrolls,
 * searched and sorted on the server, so a failed message far down the
 * queue can still be found, retried or deleted.
 *
 * Loaded where it is needed (require_once), not by functions.php.
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
 * The badge label and class of each e-mail status.
 *
 * @return array status => array(label, class)
 */
function pg_mail_queue_list_statuses()
{
    return array(
        'queued'  => array(lang('Queued'), 'theme-secondary'),
        'sending' => array(lang('Sending'), 'theme-info'),
        'sent'    => array(lang('Sent'), 'theme-success'),
        'failed'  => array(lang('Failed'), 'theme-danger'),
        // Stopped by the send gate (includes/fn/mail_bounces.php): the
        // address bounced or complained. Not a failure, never retried.
        'suppressed' => array(lang('Blocked'), 'theme-warning'),
    );
}

/**
 * The label of each e-mail type.
 *
 * @return array mail_type => label
 */
function pg_mail_queue_list_types()
{
    // One for every pg_mail_source_kinds() key: a caller's 'source' is
    // copied into mail_type.
    return array(
        'system'    => lang('System'),
        'campaign'  => lang('Campaign'),
        'workspace' => lang('Workspace'),
        'order'     => lang('Order'),
        'form'      => lang('Form'),
        'account'   => lang('Account'),
        'comment'   => lang('Comment'),
        'flow'      => lang('Flow'),
    );
}

/**
 * The badge label and class of each text message status.
 *
 * @return array status => array(label, class)
 */
function pg_sms_queue_list_statuses()
{
    return array(
        'queued'    => array(lang('Queued'), 'theme-secondary'),
        'sending'   => array(lang('Sending'), 'theme-info'),
        'sent'      => array(lang('Sent'), 'theme-success'),
        'failed'    => array(lang('Failed'), 'theme-danger'),
        'uncertain' => array(lang('Outcome unknown'), 'theme-warning'),
    );
}

/**
 * The label of each text message kind, one for every pg_sms_kinds() key.
 *
 * @return array kind => label
 */
function pg_sms_queue_list_kinds()
{
    return array(
        'test'      => lang('Test'),
        'order'     => lang('Order'),
        'shipping'  => lang('Shipping update'),
        'otp'       => lang('One-time code'),
        'reminder'  => lang('Reminder'),
        'stock'     => lang('Back in stock'),
        'booking'   => lang('Booking'),
        'flow'      => lang('Automation flow'),
        'marketing' => lang('Marketing'),
    );
}

/**
 * The labels of a status list, for the search box.
 *
 * @param array $statuses status => array(label, class)
 * @return array status => label
 */
function pg_mail_queue_list_status_labels(array $statuses)
{
    $labels = array();

    foreach ($statuses as $status => $badge) {
        $labels[$status] = $badge[0];
    }

    return $labels;
}

/**
 * A long text in a cell: table.chart cells do not wrap and a cell's
 * max-width does not narrow an auto-layout column, so the width limit sits
 * on an inner block that wraps.
 *
 * @param string $html the escaped text
 * @return string
 */
function pg_mail_queue_list_wrap($html)
{
    return '<div class="text-break" style="white-space:normal;min-width:12rem;max-width:28rem;">' . $html . '</div>';
}

/**
 * The delete button's question: a message not yet sent will not be sent;
 * one already sent only leaves the list.
 *
 * @param string $status the row's status
 * @return string
 */
function pg_mail_queue_list_delete_question($status)
{
    return ((string) $status === 'sent')
        ? lang('The message will be deleted from the queue.')
        : lang('The message will be deleted from the queue and not sent.');
}

/**
 * A date and time cell's text.
 *
 * @param int $timestamp
 * @return string
 */
function pg_mail_queue_list_time($timestamp)
{
    return prepare_form_data_for_output(date('Y-m-d H:i:s', (int) $timestamp), 'date and time');
}

/**
 * The e-mail queue's search columns: every word in the recipient, the
 * subject or the last error, or in the name a status or a type is shown
 * under.
 *
 * @return array array('columns', 'labels') for pg_list_search_where()
 */
function pg_mail_queue_list_search_columns()
{
    return array(
        'columns' => array('recipient', 'subject', 'last_error'),
        'labels' => array(
            array('expr' => 'status', 'labels' => pg_mail_queue_list_status_labels(pg_mail_queue_list_statuses())),
            array('expr' => 'mail_type', 'labels' => pg_mail_queue_list_types()),
        ),
    );
}

/**
 * The text message queue's search columns: every word in the recipient,
 * the message, the reference, the last error or the provider's id, or in
 * the name a status or a kind is shown under.
 *
 * @return array array('columns', 'labels') for pg_list_search_where()
 */
function pg_sms_queue_list_search_columns()
{
    return array(
        'columns' => array('recipient', 'body', 'reference', 'last_error', 'provider_id'),
        'labels' => array(
            array('expr' => 'status', 'labels' => pg_mail_queue_list_status_labels(pg_sms_queue_list_statuses())),
            array('expr' => 'kind', 'labels' => pg_sms_queue_list_kinds()),
        ),
    );
}

/**
 * The columns both queues share around their own: the recipient leads (the
 * row's name), the status, kind, attempts and last error follow the
 * message, the times and the number come last, then the buttons.
 *
 * @param array  $statuses status => array(label, class)
 * @param string $kind     the kind column's SQL (mail_type or kind)
 * @param array  $kinds    kind => label
 * @param array  $own      the queue's own columns after the recipient
 * @param array  $after    the queue's own columns after the last error
 * @return array key => spec, as pg_list_table_columns() takes them
 */
function pg_mail_queue_list_shared_columns(array $statuses, $kind, array $kinds, array $own, array $after)
{
    return array(
        'recipient' => array('heading' => lang('Recipient'), 'width' => '16rem', 'sort' => 'recipient'),
    ) + $own + array(
        'status' => array('heading' => lang('Status'), 'width' => '9rem', 'kind' => 'badge', 'sort' => pg_list_label_order('status', pg_mail_queue_list_status_labels($statuses))),
        'kind' => array('heading' => lang('Kind'), 'width' => '8rem', 'sort' => pg_list_label_order($kind, $kinds)),
        'attempts' => array('heading' => lang('Attempts'), 'width' => '6rem', 'kind' => 'number', 'sort' => 'attempts'),
        'last_error' => array('heading' => lang('Last error'), 'width' => '20rem'),
    ) + $after + array(
        'created' => array('heading' => lang('Created'), 'width' => '11rem', 'kind' => 'date', 'sort' => 'created_at'),
        'when' => array('heading' => lang('Next attempt or sent'), 'width' => '11rem', 'kind' => 'date'),
        'number' => array('heading' => '#', 'width' => '6rem', 'kind' => 'number', 'sort' => 'id'),
        'actions' => array('heading' => lang('Action'), 'width' => '6rem', 'kind' => 'actions'),
    );
}

/**
 * The e-mail queue's columns for the list table driver.
 *
 * @return array key => spec
 */
function pg_mail_queue_list_columns()
{
    return pg_mail_queue_list_shared_columns(
        pg_mail_queue_list_statuses(),
        'mail_type',
        pg_mail_queue_list_types(),
        array('subject' => array('heading' => lang('Subject'), 'width' => '20rem', 'sort' => 'subject')),
        array());
}

/**
 * The text message queue's columns for the list table driver.
 *
 * @return array key => spec
 */
function pg_sms_queue_list_columns()
{
    return pg_mail_queue_list_shared_columns(
        pg_sms_queue_list_statuses(),
        'kind',
        pg_sms_queue_list_kinds(),
        array('message' => array('heading' => lang('Message'), 'width' => '20rem')),
        array('provider' => array('heading' => lang('Provider'), 'width' => '10rem', 'sort' => 'provider')));
}

/**
 * One e-mail of the list.
 *
 * @param array $row a mail_outbox row (id, created_at, send_after, status,
 *                   attempts, last_error, sent_at, mail_type, recipient,
 *                   subject)
 * @param int   $now the time "on the next run" is measured from
 * @return array array('id', 'class', 'cells' => column key => pg_list_cell())
 */
function pg_mail_queue_list_row(array $row, $now)
{
    $statuses = pg_mail_queue_list_statuses();
    $types = pg_mail_queue_list_types();

    $status = isset($statuses[$row['status']]) ? $statuses[$row['status']] : array((string) $row['status'], 'bg-1 fg-body');

    // The mail server took it, but a delivery failure came back for it.
    if (isset($row['bounce']) && ($row['status'] === 'sent')) {
        $status = array(lang('Not delivered'), 'theme-danger');
        $row['last_error'] = (string) $row['bounce'];
    }

    // When it went, or when it goes next.
    if ($row['status'] === 'sent') {
        $when = pg_mail_queue_list_time($row['sent_at']);
    } elseif ($row['status'] === 'queued') {
        $when = ((int) $row['send_after'] > (int) $now) ? pg_mail_queue_list_time($row['send_after']) : h(lang('On the next run'));
    } else {
        $when = '';
    }

    // Given up at once: what is wrong is in the message itself, so a retry
    // fails the same way until the cause is fixed where it was written. The
    // operator may still retry it after fixing that.
    $permanent = pg_mail_failed_permanently($row['status'], $row['attempts'])
        ? ' <span class="badge theme-inverse" title="' . h(lang('The queue does not try it again by itself: sent again before its cause is fixed, it would fail the same way.')) . '">' . h(lang('Permanent')) . '</span>'
        : '';

    $id = (int) $row['id'];

    $buttons = '';

    if (in_array($row['status'], array('queued', 'failed'), true)) {
        $buttons .= '<button type="submit" name="mail_action" value="retry:' . $id . '" class="no-submit btn btn-sm btn-outline theme-secondary me-1" title="' . h(lang('Retry')) . '"><i class="bi bi-arrow-clockwise" aria-hidden="true"></i></button>';
    }

    $buttons .= '<button type="submit" name="mail_action" value="delete:' . $id . '" class="no-submit btn btn-sm btn-outline theme-warning" title="' . h(lang('Delete')) . '" data-confirm-content="' . h(pg_mail_queue_list_delete_question($row['status'])) . '"><i class="bi bi-trash" aria-hidden="true"></i></button>';

    return array('id' => $id, 'class' => '', 'cells' => array(
        'recipient' => pg_list_cell(h($row['recipient']), 'align-middle'),
        'subject' => pg_list_cell(pg_mail_queue_list_wrap(h($row['subject'])), 'align-middle'),
        'status' => pg_list_cell('<span class="badge ' . h($status[1]) . '">' . h($status[0]) . '</span>' . $permanent, 'align-middle text-nowrap'),
        'kind' => pg_list_cell(h(isset($types[$row['mail_type']]) ? $types[$row['mail_type']] : (string) $row['mail_type']), 'align-middle'),
        'attempts' => pg_list_cell((string) (int) $row['attempts'], 'align-middle text-end'),
        'last_error' => pg_list_cell(pg_mail_queue_list_wrap(h((string) $row['last_error'])), 'align-middle small fg-danger'),
        'created' => pg_list_cell(pg_mail_queue_list_time($row['created_at']), 'align-middle text-nowrap'),
        'when' => pg_list_cell($when, 'align-middle text-nowrap'),
        'number' => pg_list_cell((string) $id, 'align-middle text-end'),
        'actions' => pg_list_cell($buttons, 'align-middle text-nowrap'),
    ));
}

/**
 * Which sent queue rows a delivery failure answered: a Workspace mail's
 * row names its item in outcome_ref ("<item>:<previous row>",
 * ws_mail_send_ref()); the item is the row's only while its outbox_id is
 * that row (Send again moves it); a bounce is an incoming item with no
 * conversation, a writer and in_reply_to the item's Message-ID in the same
 * box (ws_mail_bounce_store()). Pure: the reads are given.
 *
 * @param array $refs    queue row id => outcome_ref
 * @param array $items   ws_mail_items rows (id, box_id, direction, outbox_id, rfc_message_id)
 * @param array $bounces ws_mail_items rows (box_id, in_reply_to, body_text), oldest first
 * @return array queue row id => the failure's words
 */
function pg_mail_queue_bounce_ties(array $refs, array $items, array $bounces)
{
    $by_id = array();

    foreach ($items as $item) {
        $by_id[(int) $item['id']] = $item;
    }

    $said = array();

    foreach ($bounces as $bounce) {
        $key = (int) $bounce['box_id'] . '|' . (string) $bounce['in_reply_to'];

        if (!isset($said[$key])) {
            $said[$key] = trim((string) $bounce['body_text']);
        }
    }

    $out = array();

    foreach ($refs as $row_id => $ref) {
        if (!preg_match('/^(\d+):\d+$/', (string) $ref, $match) || !isset($by_id[(int) $match[1]])) {
            continue;
        }

        $item = $by_id[(int) $match[1]];
        $key = (int) $item['box_id'] . '|' . (string) $item['rfc_message_id'];

        if (((string) $item['direction'] === 'out') && ((int) $item['outbox_id'] === (int) $row_id)
            && ((string) $item['rfc_message_id'] !== '') && isset($said[$key])) {
            $out[(int) $row_id] = $said[$key];
        }
    }

    return $out;
}

/**
 * The delivery failures of the given sent Workspace queue rows, read for
 * one page of a list: the rows' properties by primary key, their items by
 * primary key, the bounces through idx_conversation (conversation 0).
 * Nothing is stored. A site without the Workspace's mail tables reads none.
 *
 * @param int[] $row_ids mail_outbox ids, sent and of type workspace
 * @return array queue row id => the failure's words
 */
function pg_mail_queue_bounced(array $row_ids)
{
    $row_ids = array_values(array_unique(array_filter(array_map('intval', $row_ids))));

    if (!$row_ids) {
        return array();
    }

    $refs = array();

    foreach ((array) db_items("SELECT id, properties FROM mail_outbox WHERE id IN (" . implode(',', $row_ids) . ")") as $row) {
        $properties = pg_mail_properties_decode($row['properties']);

        if (is_array($properties) && (($properties['on_outcome'] ?? '') === 'workspace_mail') && is_string($properties['outcome_ref'] ?? null)) {
            $refs[(int) $row['id']] = $properties['outcome_ref'];
        }
    }

    $item_ids = array();

    foreach ($refs as $ref) {
        if (preg_match('/^(\d+):\d+$/', $ref, $match)) {
            $item_ids[] = (int) $match[1];
        }
    }

    // A failed read (no ws_mail_items table) returns false: nothing to show.
    $items = $item_ids ? db_items("SELECT id, box_id, direction, outbox_id, rfc_message_id FROM ws_mail_items WHERE id IN (" . implode(',', array_unique($item_ids)) . ")") : array();

    if (!is_array($items) || !$items) {
        return array();
    }

    $ids = array();

    foreach ($items as $item) {
        if ((string) $item['rfc_message_id'] !== '') {
            $ids[] = "'" . e((string) $item['rfc_message_id']) . "'";
        }
    }

    $bounces = $ids ? db_items("SELECT box_id, in_reply_to, body_text FROM ws_mail_items WHERE conversation_id = 0 AND direction = 'in' AND user_id > 0
        AND in_reply_to IN (" . implode(',', array_unique($ids)) . ") ORDER BY id LIMIT 200") : array();

    return pg_mail_queue_bounce_ties($refs, $items, is_array($bounces) ? $bounces : array());
}

/**
 * The ids of a page's rows to look a delivery failure up for: sent rows of
 * type workspace. Pure.
 *
 * @param array  $records list rows
 * @param string $state   the column holding the status
 * @param string $type    the column holding the mail type
 * @return int[]
 */
function pg_mail_queue_bounce_candidates(array $records, $state, $type)
{
    $ids = array();

    foreach ($records as $record) {
        if ((($record[$state] ?? '') === 'sent') && (($record[$type] ?? '') === 'workspace')) {
            $ids[] = (int) ($record['rid'] ?? $record['id']);
        }
    }

    return $ids;
}

/**
 * One text message of the list.
 *
 * @param array $row a sms_outbox row (id, created_at, send_after,
 *                   expires_at, status, attempts, last_error, sent_at, kind,
 *                   recipient, body, reference, provider, provider_id)
 * @param int   $now the time expiry and "on the next run" are measured from
 * @return array array('id', 'class', 'cells' => column key => pg_list_cell())
 */
function pg_sms_queue_list_row(array $row, $now)
{
    $statuses = pg_sms_queue_list_statuses();
    $kinds = pg_sms_queue_list_kinds();

    $status = isset($statuses[$row['status']]) ? $statuses[$row['status']] : array((string) $row['status'], 'bg-1 fg-body');

    $expired = ((int) $row['expires_at'] > 0) && ((int) $row['expires_at'] <= (int) $now);

    // When it went, or when it goes next.
    if ($row['status'] === 'sent') {
        $when = pg_mail_queue_list_time($row['sent_at']);
    } elseif ($row['status'] === 'queued') {

        // A row past its expiry is never sent: the next run marks it failed
        // without an attempt, so it does not claim that run.
        if ($expired) {
            $when = h(lang('Expired'));
        } else {
            $when = ((int) $row['send_after'] > (int) $now) ? pg_mail_queue_list_time($row['send_after']) : h(lang('On the next run'));
        }
    } else {
        $when = '';
    }

    // Who took it, under which id: what a question to the provider about
    // this message needs.
    $provider = '';

    if ((string) $row['provider'] !== '') {
        $definition = function_exists('pg_sms_provider_definition') ? pg_sms_provider_definition($row['provider']) : null;
        $provider = ($definition !== null) ? $definition['label'] : (string) $row['provider'];

        if ((string) $row['provider_id'] !== '') {
            $provider .= ' ' . $row['provider_id'];
        }
    }

    $reference = (string) $row['reference'];

    $id = (int) $row['id'];

    $buttons = '';

    if (in_array($row['status'], array('queued', 'failed', 'uncertain'), true) && !$expired) {

        // A message whose outcome is unknown may already be on the
        // recipient's phone; sending it again is the operator's call, made
        // after a look at the provider's report.
        $confirm = ($row['status'] === 'uncertain')
            ? ' data-confirm-content="' . h(lang('The provider may already have delivered this message, and sent again it would arrive twice. Check the provider\'s report first.')) . '"'
            : '';

        $buttons .= '<button type="submit" name="sms_action" value="retry:' . $id . '" class="no-submit btn btn-sm btn-outline theme-secondary me-1" title="' . h(lang('Retry')) . '"' . $confirm . '><i class="bi bi-arrow-clockwise" aria-hidden="true"></i></button>';
    }

    $buttons .= '<button type="submit" name="sms_action" value="delete:' . $id . '" class="no-submit btn btn-sm btn-outline theme-warning" title="' . h(lang('Delete')) . '" data-confirm-content="' . h(pg_mail_queue_list_delete_question($row['status'])) . '"><i class="bi bi-trash" aria-hidden="true"></i></button>';

    return array('id' => $id, 'class' => '', 'cells' => array(
        'recipient' => pg_list_cell('+' . h($row['recipient']), 'align-middle text-nowrap'),
        'message' => pg_list_cell(pg_mail_queue_list_wrap(h($row['body']) . (($reference !== '') ? '<div class="small fg-secondary">' . h($reference) . '</div>' : '')), 'align-middle'),
        'status' => pg_list_cell('<span class="badge ' . h($status[1]) . '">' . h($status[0]) . '</span>', 'align-middle'),
        'kind' => pg_list_cell(h(isset($kinds[$row['kind']]) ? $kinds[$row['kind']] : (string) $row['kind']), 'align-middle'),
        'attempts' => pg_list_cell((string) (int) $row['attempts'], 'align-middle text-end'),
        'last_error' => pg_list_cell(pg_mail_queue_list_wrap(h((string) $row['last_error'])), 'align-middle small fg-danger'),
        'provider' => pg_list_cell(h($provider), 'align-middle small text-nowrap'),
        'created' => pg_list_cell(pg_mail_queue_list_time($row['created_at']), 'align-middle text-nowrap'),
        'when' => pg_list_cell($when, 'align-middle text-nowrap'),
        'number' => pg_list_cell((string) $id, 'align-middle text-end'),
        'actions' => pg_list_cell($buttons, 'align-middle text-nowrap'),
    ));
}

/**
 * A queue's source for the list table driver, newest first unless the user
 * sorted it otherwise. Only the screen's own users (a role above User) get
 * one, and only once the queue's table exists.
 *
 * @param string $queue mail or sms
 * @param array  $user  the session user
 * @return array|null pg_list_table_source(), or null when the list may not
 *                    be opened
 */
function pg_mail_queue_list_queue_source($queue, $user)
{
    if (!is_array($user) || !isset($user['role']) || !is_numeric($user['role']) || ((int) $user['role'] > 2)) {
        return null;
    }

    if (!(($queue === 'sms') ? pg_sms_queue_ready() : pg_mail_queue_ready())) {
        return null;
    }

    require_once(PG_FUNCTIONS_DIR . '/includes/fn/list_table.php');

    if ($queue === 'sms') {
        $list = 'mail_queue_sms';
        $columns = pg_sms_queue_list_columns();
        $select = 'id, created_at, send_after, expires_at, status, attempts, last_error, sent_at, kind, recipient, body, reference, provider, provider_id';
        $search = pg_sms_queue_list_search_columns();
        $builder = 'pg_sms_queue_list_row';
    } else {
        $list = 'mail_queue';
        $columns = pg_mail_queue_list_columns();
        $select = 'id, created_at, send_after, status, attempts, last_error, sent_at, mail_type, recipient, subject';
        $search = pg_mail_queue_list_search_columns();
        $builder = 'pg_mail_queue_list_row';
    }

    $levels = pg_list_state($list, pg_list_table_sort_columns(pg_list_table_columns($columns)), array('sort' => 'created', 'order' => 'desc'))['sorts'];
    $now = time();

    return pg_list_table_source(array(
        'list' => $list,
        'columns' => $columns,
        'from' => ($queue === 'sms') ? 'sms_outbox' : 'mail_outbox',
        'id' => 'id',
        'id_order' => 'desc',
        'select' => $select,
        'search' => $search,
        'levels' => $levels,
        'created' => 'created_at',
        'rows' => function ($records) use ($builder, $now, $queue) {
            $rows = array();
            $bounced = ($queue === 'sms') ? array() : pg_mail_queue_bounced(pg_mail_queue_bounce_candidates($records, 'status', 'mail_type'));

            foreach ($records as $record) {
                if (isset($bounced[(int) $record['id']])) {
                    $record['bounce'] = $bounced[(int) $record['id']];
                }

                $rows[] = $builder($record, $now);
            }

            return $rows;
        },
    ));
}

/**
 * The e-mail queue's source for the list table driver.
 *
 * @param array $user   the session user
 * @param array $params the list parameters the browser sends back (none)
 * @return array|null
 */
function pg_mail_queue_list_source($user, $params)
{
    return pg_mail_queue_list_queue_source('mail', $user);
}

/**
 * The text message queue's source for the list table driver.
 *
 * @param array $user   the session user
 * @param array $params the list parameters the browser sends back (none)
 * @return array|null
 */
function pg_sms_queue_list_source($user, $params)
{
    return pg_mail_queue_list_queue_source('sms', $user);
}
