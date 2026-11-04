<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The mail queue screen's lists (mail_queue.php): the e-mail queue
 * (mail_outbox) and the text message queue (sms_outbox), shared by the
 * screen and the panel actions that page them (mail_queue_list_rows and
 * sms_queue_list_rows, includes/panel/lists.php).
 *
 * The screen printed the newest 100 rows, while its count cards counted the
 * whole table: failed messages past the 100th could not be found, retried or
 * deleted. The rows are now read one page at a time and searched on the
 * server, newest first.
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
    return array(
        'system'   => lang('System'),
        'campaign' => lang('Campaign'),
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
 * The label of each text message kind.
 *
 * @return array kind => label
 */
function pg_sms_queue_list_kinds()
{
    return array(
        'test'      => lang('Test'),
        'order'     => lang('Order'),
        'shipping'  => lang('Shipping update'),
        'otp'       => lang('Sign-in code'),
        'reminder'  => lang('Reminder'),
        'booking'   => lang('Booking'),
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
 * The search box's condition on the e-mail queue: every word in the
 * recipient, the subject or the last error, or in the name a status or a
 * type is shown under.
 *
 * @param array $terms pg_list_search_terms()'s answer
 * @return array array('sql', 'params')
 */
function pg_mail_queue_list_search(array $terms)
{
    return pg_list_search_where(
        $terms,
        array('recipient', 'subject', 'last_error'),
        array(
            array('expr' => 'status', 'labels' => pg_mail_queue_list_status_labels(pg_mail_queue_list_statuses())),
            array('expr' => 'mail_type', 'labels' => pg_mail_queue_list_types()),
        )
    );
}

/**
 * The search box's condition on the text message queue: every word in the
 * recipient, the message, the reference, the last error or the provider's
 * id, or in the name a status or a kind is shown under.
 *
 * @param array $terms pg_list_search_terms()'s answer
 * @return array array('sql', 'params')
 */
function pg_sms_queue_list_search(array $terms)
{
    return pg_list_search_where(
        $terms,
        array('recipient', 'body', 'reference', 'last_error', 'provider_id'),
        array(
            array('expr' => 'status', 'labels' => pg_mail_queue_list_status_labels(pg_sms_queue_list_statuses())),
            array('expr' => 'kind', 'labels' => pg_sms_queue_list_kinds()),
        )
    );
}

/**
 * One e-mail of the list.
 *
 * @param array $row a mail_outbox row (id, created_at, send_after, status,
 *                   attempts, last_error, sent_at, mail_type, recipient,
 *                   subject)
 * @param int   $now the time "on the next run" is measured from
 * @return array array('class', 'cells')
 */
function pg_mail_queue_list_row(array $row, $now)
{
    $statuses = pg_mail_queue_list_statuses();
    $types = pg_mail_queue_list_types();

    $status = isset($statuses[$row['status']]) ? $statuses[$row['status']] : array((string) $row['status'], 'bg-1 fg-body');

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
        $buttons .= '<button type="submit" name="mail_action" value="retry:' . $id . '" class="btn btn-sm btn-outline theme-secondary me-1" title="' . h(lang('Retry')) . '"><i class="bi bi-arrow-clockwise" aria-hidden="true"></i></button>';
    }

    $buttons .= '<button type="submit" name="mail_action" value="delete:' . $id . '" class="btn btn-sm btn-outline theme-warning" title="' . h(lang('Delete')) . '" data-confirm-content="' . h(pg_mail_queue_list_delete_question($row['status'])) . '"><i class="bi bi-trash" aria-hidden="true"></i></button>';

    return array('class' => 'unselectable', 'cells' => array(
        pg_list_cell($buttons, 'align-middle text-nowrap'),
        pg_list_cell((string) $id, 'align-middle'),
        pg_list_cell('<span class="badge ' . h($status[1]) . '">' . h($status[0]) . '</span>' . $permanent, 'align-middle text-nowrap'),
        pg_list_cell(h(isset($types[$row['mail_type']]) ? $types[$row['mail_type']] : (string) $row['mail_type']), 'align-middle'),
        pg_list_cell(h($row['recipient']), 'align-middle'),
        pg_list_cell(pg_mail_queue_list_wrap(h($row['subject'])), 'align-middle'),
        pg_list_cell((string) (int) $row['attempts'], 'align-middle'),
        pg_list_cell(pg_mail_queue_list_wrap(h((string) $row['last_error'])), 'align-middle small fg-danger'),
        pg_list_cell(pg_mail_queue_list_time($row['created_at']), 'align-middle text-nowrap'),
        pg_list_cell($when, 'align-middle text-nowrap'),
    ));
}

/**
 * One text message of the list.
 *
 * @param array $row a sms_outbox row (id, created_at, send_after,
 *                   expires_at, status, attempts, last_error, sent_at, kind,
 *                   recipient, body, reference, provider, provider_id)
 * @param int   $now the time expiry and "on the next run" are measured from
 * @return array array('class', 'cells')
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

        $buttons .= '<button type="submit" name="sms_action" value="retry:' . $id . '" class="btn btn-sm btn-outline theme-secondary me-1" title="' . h(lang('Retry')) . '"' . $confirm . '><i class="bi bi-arrow-clockwise" aria-hidden="true"></i></button>';
    }

    $buttons .= '<button type="submit" name="sms_action" value="delete:' . $id . '" class="btn btn-sm btn-outline theme-warning" title="' . h(lang('Delete')) . '" data-confirm-content="' . h(pg_mail_queue_list_delete_question($row['status'])) . '"><i class="bi bi-trash" aria-hidden="true"></i></button>';

    return array('class' => 'unselectable', 'cells' => array(
        pg_list_cell($buttons, 'align-middle text-nowrap'),
        pg_list_cell((string) $id, 'align-middle'),
        pg_list_cell('<span class="badge ' . h($status[1]) . '">' . h($status[0]) . '</span>', 'align-middle'),
        pg_list_cell(h(isset($kinds[$row['kind']]) ? $kinds[$row['kind']] : (string) $row['kind']), 'align-middle'),
        pg_list_cell('+' . h($row['recipient']), 'align-middle text-nowrap'),
        pg_list_cell(pg_mail_queue_list_wrap(h($row['body']) . (($reference !== '') ? '<div class="small fg-secondary">' . h($reference) . '</div>' : '')), 'align-middle'),
        pg_list_cell((string) (int) $row['attempts'], 'align-middle'),
        pg_list_cell(pg_mail_queue_list_wrap(h((string) $row['last_error'])), 'align-middle small fg-danger'),
        pg_list_cell(h($provider), 'align-middle small text-nowrap'),
        pg_list_cell(pg_mail_queue_list_time($row['created_at']), 'align-middle text-nowrap'),
        pg_list_cell($when, 'align-middle text-nowrap'),
    ));
}

/**
 * The number of rows of a table a condition selects.
 *
 * @param string $table  mail_outbox or sms_outbox
 * @param string $sql    a condition with ? placeholders, or ''
 * @param array  $params its values
 * @return int|false false when the query failed
 */
function pg_mail_queue_list_count($table, $sql, array $params)
{
    $result = db_q('SELECT COUNT(*) FROM ' . $table . (($sql !== '') ? ' WHERE ' . $sql : ''), $params);

    return $result ? (int) mysqli_fetch_row($result)[0] : false;
}

/**
 * One page of a queue, newest first: the row counts without and with the
 * search box, and the rows of the page window.
 *
 * @param string $queue  mail or sms
 * @param array  $terms  pg_list_search_terms()'s answer
 * @param array  $window pg_list_page_window()'s answer
 * @param int    $now
 * @return array|false array('total', 'filtered', 'rows') or false when a
 *                     query failed
 */
function pg_mail_queue_list_page($queue, array $terms, array $window, $now)
{
    if ($queue === 'sms') {
        $table = 'sms_outbox';
        $columns = 'id, created_at, send_after, expires_at, status, attempts, last_error, sent_at, kind, recipient, body, reference, provider, provider_id';
        $search = pg_sms_queue_list_search($terms);
        $builder = 'pg_sms_queue_list_row';
    } else {
        $table = 'mail_outbox';
        $columns = 'id, created_at, send_after, status, attempts, last_error, sent_at, mail_type, recipient, subject';
        $search = pg_mail_queue_list_search($terms);
        $builder = 'pg_mail_queue_list_row';
    }

    $total = pg_mail_queue_list_count($table, '', array());

    if ($total === false) {
        return false;
    }

    $filtered = ($search['sql'] !== '') ? pg_mail_queue_list_count($table, $search['sql'], $search['params']) : $total;

    if ($filtered === false) {
        return false;
    }

    $result = db_q('SELECT ' . $columns . ' FROM ' . $table
        . (($search['sql'] !== '') ? ' WHERE ' . $search['sql'] : '')
        . ' ORDER BY id DESC' . pg_list_limit_sql($window), $search['params']);

    if (!$result) {
        return false;
    }

    $rows = array();

    while ($row = mysqli_fetch_assoc($result)) {
        $rows[] = $builder($row, $now);
    }

    return array('total' => $total, 'filtered' => $filtered, 'rows' => $rows);
}
