<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Communication › Sends (messages_sends.php): every message the site sends
 * in one list on the list table driver. The list reads no table of its own:
 * its source is one UNION ALL of a SELECT per branch (the e-mail queue, the
 * SMS queue, the WhatsApp queue, manual e-mail campaigns, SMS campaigns,
 * automatic e-mail campaigns as single messages), each holding the period on its own indexed
 * time, and the list table driver reads them as branches (each batch
 * bounded, ordered and cut inside every branch, counted per branch), so a
 * batch reads only the head of each branch's index range. A row id is the branch
 * row's id * 8 + the branch number, whole and unique across branches, so
 * the keyset and the selection resolver work as they are.
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

require_once(PG_FUNCTIONS_DIR . '/includes/fn/mail_queue_list.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/messages_area.php');

/**
 * The branches of the list by number; 7 is the last free number (an eighth
 * source is a question of its own).
 *
 * @return array number => key
 */
function pg_messages_sends_branches()
{
    return array(
        1 => 'mail',
        2 => 'sms',
        3 => 'whatsapp',
        4 => 'email_campaign',
        5 => 'sms_campaign',
        6 => 'automatic',
    );
}

/**
 * The row id of a branch row.
 *
 * @param int $id     the row's id in its own table
 * @param int $branch a key of pg_messages_sends_branches()
 * @return int
 */
function pg_messages_sends_sid($id, $branch)
{
    return (int) $id * 8 + (int) $branch;
}

/**
 * A row id read back: its branch key and the row's own id, or null for an
 * id of no branch.
 *
 * @param mixed $sid
 * @return array|null array('branch' => key, 'id' => int)
 */
function pg_messages_sends_sid_parts($sid)
{
    if (!is_scalar($sid) || !preg_match('/^[0-9]{1,15}$/', (string) $sid)) {
        return null;
    }

    $sid = (int) $sid;
    $branches = pg_messages_sends_branches();
    $number = $sid % 8;
    $id = intdiv($sid, 8);

    if (!isset($branches[$number]) || ($id < 1)) {
        return null;
    }

    return array('branch' => $branches[$number], 'id' => $id);
}

/**
 * The periods the list reads, in days; one is always set.
 *
 * @return array days => label
 */
function pg_messages_sends_periods()
{
    return array(
        '1' => lang('Last 24 hours'),
        '7' => lang('Last 7 days'),
        '30' => lang('Last 30 days'),
        '90' => lang('Last 90 days'),
    );
}

/**
 * The channels of the filter.
 *
 * @return array key => label
 */
function pg_messages_sends_channels()
{
    return array(
        'email' => lang('E-mail'),
        'sms' => lang('SMS'),
        'whatsapp' => lang('WhatsApp'),
    );
}

/**
 * The channel of a branch.
 *
 * @param string $branch a value of pg_messages_sends_branches()
 * @return string email, sms or whatsapp
 */
function pg_messages_sends_branch_channel($branch)
{
    if ($branch === 'whatsapp') {
        return 'whatsapp';
    }

    return in_array($branch, array('sms', 'sms_campaign'), true) ? 'sms' : 'email';
}

/**
 * Whether a branch is a queue of single messages (the e-mail, SMS and
 * WhatsApp queues), which only role <= 2 reads and works.
 *
 * @param string $branch
 * @return bool
 */
function pg_messages_sends_queue_branch($branch)
{
    return in_array($branch, array('mail', 'sms', 'whatsapp'), true);
}

/**
 * A state as look A's pill: the badge class the state list names maps to a
 * tone (secondary gray, info violet, success teal, danger rose, warning
 * amber); anything else is gray.
 *
 * @param string $label plain text
 * @param string $class a theme-* class of pg_messages_sends_states()
 * @return string
 */
function pg_messages_sends_pill($label, $class)
{
    $tones = array('theme-info' => 'violet', 'theme-success' => 'teal', 'theme-danger' => 'rose', 'theme-warning' => 'amber');
    $tone = isset($tones[$class]) ? $tones[$class] : 'gray';

    return '<span class="pg-pill pg-tone-' . $tone . '">' . h((string) $label) . '</span>';
}

/**
 * A row's channel: its tone tile with the channel's icon, then its name.
 *
 * @param string $channel email, sms or whatsapp
 * @param string $name    the shown name
 * @return string
 */
function pg_messages_sends_channel_cell($channel, $name)
{
    $tiles = array('email' => array('bi-envelope', 'blue'), 'sms' => array('bi-chat-dots', 'amber'), 'whatsapp' => array('bi-whatsapp', 'teal'));
    $tile = isset($tiles[$channel]) ? $tiles[$channel] : array('bi-send', 'gray');

    return '<span class="pg-messages-channel-cell"><span class="pg-icon-tile pg-tone-' . $tile[1] . '" aria-hidden="true"><span class="bi ' . $tile[0] . '"></span></span>' . h($name) . '</span>';
}

/**
 * The figures above the list as look A's figure cards (the report screens'
 * recipe): the name beside its tone tile, the figure, a note under it.
 *
 * Each figure: label, icon (bi-…), tone, value (shown text), note ('' none).
 *
 * @param array $figures
 * @return string '' when there is none
 */
function pg_messages_sends_figures_html(array $figures)
{
    $html = '';

    foreach ($figures as $figure) {
        $note = isset($figure['note']) ? (string) $figure['note'] : '';
        $html .= '<div class="pg-card pg-report-fig">'
            . '<dt class="pg-report-fig-name"><span class="pg-icon-tile pg-tone-' . h((string) $figure['tone']) . '" aria-hidden="true"><span class="bi ' . h((string) $figure['icon']) . '"></span></span>' . h((string) $figure['label']) . '</dt>'
            . '<dd class="pg-report-fig-value">' . h((string) $figure['value']) . '</dd>'
            . (($note !== '') ? '<dd class="pg-report-fig-note">' . h($note) . '</dd>' : '')
            . '</div>';
    }

    if ($html === '') {
        return '';
    }

    $columns = min(5, count($figures));

    return '<dl class="pg-report-figs pg-messages-figs" style="--pg-report-figs: ' . $columns . '">' . $html . '</dl>';
}

/**
 * The states a row shows, with the badge each is drawn with. Queue rows
 * take the first six, campaign rows the rest and their own Sending.
 *
 * @return array state => array(label, class)
 */
function pg_messages_sends_states()
{
    return array(
        'queued' => array(lang('Queued'), 'theme-secondary'),
        'sending' => array(lang('Sending'), 'theme-info'),
        'sent' => array(lang('Sent'), 'theme-success'),
        'failed' => array(lang('Failed'), 'theme-danger'),
        'blocked' => array(lang('Blocked'), 'theme-warning'),
        'uncertain' => array(lang('Outcome unknown'), 'theme-warning'),
        'draft' => array(lang('Draft'), 'theme-secondary'),
        'scheduled' => array(lang('Scheduled'), 'theme-info'),
        'paused' => array(lang('Paused'), 'theme-warning'),
        'completed' => array(lang('Completed'), 'theme-success'),
        'cancelled' => array(lang('Cancelled'), 'theme-secondary'),
    );
}

/**
 * The sources a row names: the kind an e-mail was queued with (its
 * mail_type, pg_mail_source_kind()), the kind of a text message, a campaign,
 * or an automatic campaign's profile.
 *
 * @return array source => label
 */
function pg_messages_sends_sources()
{
    return array(
        'order' => lang('Order'),
        'shipping' => lang('Shipping update'),
        'form' => lang('Form'),
        'account' => lang('Account'),
        'comment' => lang('Comment'),
        'flow' => lang('Flow'),
        'workspace' => lang('Workspace'),
        'campaign' => lang('Campaign'),
        'profile' => lang('Automatic campaign'),
        'otp' => lang('One-time code'),
        'reminder' => lang('Reminder'),
        'stock' => lang('Back in stock'),
        'booking' => lang('Booking'),
        'marketing' => lang('Marketing'),
        'test' => lang('Test'),
        'system' => lang('System'),
    );
}

/**
 * The filters of the list, each checked: a value that is not one of the
 * choices reads as its default (every channel, source and state; 7 days).
 *
 * @param array $input the address's values or the list's parameters
 * @return array channel, source, state, period
 */
function pg_messages_sends_filters(array $input)
{
    $pick = function ($key, array $choices, $default) use ($input) {
        $value = (isset($input[$key]) && is_scalar($input[$key])) ? (string) $input[$key] : '';

        return isset($choices[$value]) ? $value : $default;
    };

    return array(
        'channel' => $pick('channel', pg_messages_sends_channels(), ''),
        'source' => $pick('source', pg_messages_sends_sources(), ''),
        'state' => $pick('state', pg_messages_sends_states(), ''),
        'period' => $pick('period', pg_messages_sends_periods(), '7'),
    );
}

/**
 * The screen's address with the filters that differ from the defaults.
 *
 * @param array $filters pg_messages_sends_filters()'s answer
 * @return string
 */
function pg_messages_sends_url(array $filters)
{
    $query = array();

    foreach (array('channel' => '', 'source' => '', 'state' => '', 'period' => '7') as $key => $default) {
        if (isset($filters[$key]) && ((string) $filters[$key] !== $default)) {
            $query[$key] = (string) $filters[$key];
        }
    }

    return 'messages_sends.php' . ($query ? '?' . http_build_query($query) : '');
}

/**
 * Where an old mail queue address leads: the Sends tab with its channel.
 * Only the tab is carried across, never the rest of the query.
 *
 * @param array $get the old address's values
 * @return string
 */
function pg_messages_sends_queue_door($get)
{
    $sms = is_array($get) && isset($get['tab']) && ($get['tab'] === 'sms');

    return pg_messages_sends_url(array('channel' => $sms ? 'sms' : 'email'));
}

/**
 * One branch of the list: its SELECT with the period, the state and source
 * filters and the user's rights in its own WHERE, or null when the filters
 * or the rights leave it out. Every value is a bound placeholder.
 *
 * Columns, the same in every branch: rid (the row's own id, which orders a
 * branch as sid does but on its index), sid, at, channel, row_kind, recipient,
 * what, source, state, done, total, error, attempts, next_at, owner,
 * expires.
 *
 * @param string $branch  a value of pg_messages_sends_branches()
 * @param array  $filters pg_messages_sends_filters()'s answer
 * @param array  $user    the session user (id, role)
 * @param int    $now
 * @return array|null array('sql', 'params')
 */
function pg_messages_sends_branch_sql($branch, array $filters, array $user, $now)
{
    $role = (int) $user['role'];
    $since = (int) $now - ((int) $filters['period'] * 86400);
    $numbers = array_flip(pg_messages_sends_branches());

    if (!isset($numbers[$branch])) {
        return null;
    }

    $number = (int) $numbers[$branch];
    $channel = pg_messages_sends_branch_channel($branch);

    if (($filters['channel'] !== '') && ($filters['channel'] !== $channel)) {
        return null;
    }

    // The queues hold password resets and receipts: only for role <= 2,
    // as the mail queue screen.
    if (pg_messages_sends_queue_branch($branch) && ($role > 2)) {
        return null;
    }

    $now_datetime = date('Y-m-d H:i:s', (int) $now);
    $where = array();
    $params = array();

    switch ($branch) {

        case 'mail':
            $state = "(CASE mail_outbox.status WHEN 'suppressed' THEN 'blocked' ELSE mail_outbox.status END)";
            $source = 'mail_outbox.mail_type';
            $select = "mail_outbox.id AS rid, mail_outbox.id * 8 + $number AS sid, mail_outbox.created_at AS at, 'email' AS channel, 'message' AS row_kind,
                mail_outbox.recipient AS recipient, mail_outbox.subject AS what, mail_outbox.mail_type AS source, $state AS state,
                0 AS done, 0 AS total, mail_outbox.last_error AS error, mail_outbox.attempts AS attempts,
                (CASE WHEN mail_outbox.status = 'sent' THEN mail_outbox.sent_at ELSE mail_outbox.send_after END) AS next_at, 0 AS owner, 0 AS expires
                FROM mail_outbox";
            $where[] = 'mail_outbox.created_at >= ?';
            $params[] = $since;
            break;

        case 'sms':
            $state = 'sms_outbox.status';
            $source = 'sms_outbox.kind';
            $select = "sms_outbox.id AS rid, sms_outbox.id * 8 + $number AS sid, sms_outbox.created_at AS at, 'sms' AS channel, 'message' AS row_kind,
                sms_outbox.recipient AS recipient, sms_outbox.body AS what, sms_outbox.kind AS source, sms_outbox.status AS state,
                0 AS done, 0 AS total, sms_outbox.last_error AS error, sms_outbox.attempts AS attempts,
                (CASE WHEN sms_outbox.status = 'sent' THEN sms_outbox.sent_at ELSE sms_outbox.send_after END) AS next_at, 0 AS owner, sms_outbox.expires_at AS expires
                FROM sms_outbox";
            $where[] = 'sms_outbox.created_at >= ?';
            $params[] = $since;
            break;

        case 'whatsapp':
            // The message is named by its template and language: the body
            // is Meta's template with the payload's values.
            $state = 'whatsapp_outbox.status';
            $source = 'whatsapp_outbox.kind';
            $select = "whatsapp_outbox.id AS rid, whatsapp_outbox.id * 8 + $number AS sid, whatsapp_outbox.created_at AS at, 'whatsapp' AS channel, 'message' AS row_kind,
                whatsapp_outbox.recipient AS recipient, CONCAT(whatsapp_outbox.template_name, ' · ', whatsapp_outbox.template_language) AS what,
                whatsapp_outbox.kind AS source, whatsapp_outbox.status AS state,
                0 AS done, 0 AS total, whatsapp_outbox.last_error AS error, whatsapp_outbox.attempts AS attempts,
                (CASE WHEN whatsapp_outbox.status = 'sent' THEN whatsapp_outbox.sent_at ELSE whatsapp_outbox.send_after END) AS next_at, 0 AS owner, whatsapp_outbox.expires_at AS expires
                FROM whatsapp_outbox";
            $where[] = 'whatsapp_outbox.created_at >= ?';
            $params[] = $since;
            break;

        case 'email_campaign':
            $state = "(CASE WHEN c.status = 'ready' AND c.start_time > ? THEN 'scheduled' WHEN c.status = 'ready' THEN 'sending'
                WHEN c.status = 'complete' THEN 'completed' ELSE c.status END)";
            $source = "'campaign'";
            $select = "c.id AS rid, c.id * 8 + $number AS sid, c.created_timestamp AS at, 'email' AS channel, 'campaign' AS row_kind,
                '' AS recipient, c.subject AS what, 'campaign' AS source, $state AS state,
                (SELECT COUNT(*) FROM email_recipients r WHERE r.email_campaign_id = c.id AND r.complete = 1 AND r.failed = 0) AS done,
                (SELECT COUNT(*) FROM email_recipients r WHERE r.email_campaign_id = c.id) AS total,
                '' AS error, 0 AS attempts, 0 AS next_at, c.created_user_id AS owner, 0 AS expires
                FROM email_campaigns c";
            $params[] = $now_datetime;
            $where[] = "c.type = 'manual'";
            $where[] = 'c.created_timestamp >= ?';
            $params[] = $since;
            break;

        case 'automatic':
            // One recipient per profile send: the first is the message.
            $state = "(CASE WHEN r.failed = 1 THEN 'failed' WHEN r.complete = 1 THEN 'sent' WHEN c.status = 'paused' THEN 'paused'
                WHEN c.status = 'cancelled' THEN 'cancelled' ELSE 'queued' END)";
            $source = "'profile'";
            $select = "c.id AS rid, c.id * 8 + $number AS sid, c.created_timestamp AS at, 'email' AS channel, 'message' AS row_kind,
                r.email_address AS recipient, c.subject AS what, 'profile' AS source, $state AS state,
                0 AS done, 0 AS total, r.last_error AS error, r.attempts AS attempts, r.next_attempt_at AS next_at, c.created_user_id AS owner, 0 AS expires
                FROM email_campaigns c
                LEFT JOIN email_recipients r ON r.id = (SELECT MIN(r2.id) FROM email_recipients r2 WHERE r2.email_campaign_id = c.id)";
            $where[] = "c.type = 'automatic'";
            $where[] = 'c.created_timestamp >= ?';
            $params[] = $since;
            break;

        case 'sms_campaign':
            $state = "(CASE WHEN s.status = 'ready' AND s.start_at > ? THEN 'scheduled' WHEN s.status = 'ready' THEN 'sending'
                WHEN s.status = 'queued' THEN 'completed' ELSE s.status END)";
            $source = "'campaign'";
            $select = "s.id AS rid, s.id * 8 + $number AS sid, (CASE WHEN s.start_at > 0 THEN s.start_at ELSE s.created_at END) AS at, 'sms' AS channel,
                'campaign' AS row_kind, '' AS recipient, s.name AS what, 'campaign' AS source, $state AS state,
                s.queued_count AS done, s.queued_count + s.refused_count AS total, '' AS error, 0 AS attempts, 0 AS next_at, s.created_by AS owner, 0 AS expires
                FROM sms_campaigns s";
            $params[] = (int) $now;
            $where[] = '((s.start_at >= ?) OR ((s.start_at = 0) AND (s.created_at >= ?)))';
            $params[] = $since;
            $params[] = $since;
            break;
    }

    // A role 3 user sees only the campaigns they made.
    if ($role > 2) {
        $where[] = (($branch === 'sms_campaign') ? 's.created_by' : 'c.created_user_id') . ' = ?';
        $params[] = (int) $user['id'];
    }

    if ($filters['source'] !== '') {
        $where[] = $source . ' = ?';
        $params[] = $filters['source'];
    }

    if ($filters['state'] !== '') {

        // The queues filter on their stored status, so idx_due serves it.
        if ($branch === 'mail') {
            $where[] = 'mail_outbox.status = ?';
            $params[] = ($filters['state'] === 'blocked') ? 'suppressed' : $filters['state'];
        } elseif (($branch === 'sms') || ($branch === 'whatsapp')) {
            $where[] = (($branch === 'sms') ? 'sms_outbox' : 'whatsapp_outbox') . '.status = ?';
            $params[] = $filters['state'];
        } else {
            $where[] = $state . ' = ?';

            // The state expression's own "now" comes again.
            if (in_array($branch, array('email_campaign', 'sms_campaign'), true)) {
                $params[] = ($branch === 'email_campaign') ? $now_datetime : (int) $now;
            }

            $params[] = $filters['state'];
        }
    }

    return array('sql' => 'SELECT ' . $select . ' WHERE ' . implode(' AND ', $where), 'params' => $params);
}

/**
 * Which branches can be read on this site: a queue or campaign table that
 * is not there yet is left out.
 *
 * @return array branch keys
 */
function pg_messages_sends_ready_branches()
{
    $ready = array();

    if (pg_mail_queue_ready()) {
        $ready[] = 'mail';
    }

    if (pg_sms_queue_ready()) {
        $ready[] = 'sms';
    }

    if (pg_messages_sends_whatsapp_ready()) {
        $ready[] = 'whatsapp';
    }

    $ready[] = 'email_campaign';

    if (function_exists('pg_sms_campaigns_ready') && pg_sms_campaigns_ready()) {
        $ready[] = 'sms_campaign';
    }

    $ready[] = 'automatic';

    return $ready;
}

/**
 * The list's derived table: the branches the filters and rights leave, as
 * one UNION ALL. A filter that leaves no branch reads an empty table.
 *
 * @param array $filters  pg_messages_sends_filters()'s answer
 * @param array $user
 * @param int   $now
 * @param array $branches the branch keys this site can read
 * @return array array('sql' => '(…) AS sends', 'params', 'branches' => each
 *               branch's array('sql', 'params') for the driver)
 */
function pg_messages_sends_from(array $filters, array $user, $now, array $branches)
{
    $parts = array();
    $params = array();
    $read = array();

    foreach ($branches as $branch) {
        $sql = pg_messages_sends_branch_sql($branch, $filters, $user, $now);

        if ($sql !== null) {
            $parts[] = '(' . $sql['sql'] . ')';
            $params = array_merge($params, $sql['params']);
            $read[] = $sql;
        }
    }

    if (!$parts) {
        $read[] = array('sql' => "SELECT 0 AS rid, 0 AS sid, 0 AS at, '' AS channel, '' AS row_kind, '' AS recipient, '' AS what, '' AS source, '' AS state,
            0 AS done, 0 AS total, '' AS error, 0 AS attempts, 0 AS next_at, 0 AS owner, 0 AS expires FROM DUAL WHERE 1 = 0", 'params' => array());
        $parts[] = "(SELECT 0 AS rid, 0 AS sid, 0 AS at, '' AS channel, '' AS row_kind, '' AS recipient, '' AS what, '' AS source, '' AS state,
            0 AS done, 0 AS total, '' AS error, 0 AS attempts, 0 AS next_at, 0 AS owner, 0 AS expires FROM DUAL WHERE 1 = 0)";
    }

    return array('sql' => '(' . implode(' UNION ALL ', $parts) . ') AS sends', 'params' => $params, 'branches' => $read);
}

/**
 * The list's columns.
 *
 * @return array key => spec, as pg_list_table_columns() takes them
 */
function pg_messages_sends_columns()
{
    $states = pg_mail_queue_list_status_labels(pg_messages_sends_states());

    return array(
        'what' => array('heading' => lang('Message'), 'width' => '22rem', 'sort' => 'what'),
        'recipient' => array('heading' => lang('To'), 'width' => '14rem', 'sort' => 'recipient'),
        'channel' => array('heading' => lang('Channel'), 'width' => '7rem', 'sort' => pg_list_label_order('channel', pg_messages_sends_channels())),
        'source' => array('heading' => lang('Source'), 'width' => '10rem', 'sort' => pg_list_label_order('source', pg_messages_sends_sources())),
        'state' => array('heading' => lang('Status'), 'width' => '9rem', 'kind' => 'badge', 'sort' => pg_list_label_order('state', $states)),
        'result' => array('heading' => lang('Result'), 'width' => '16rem'),
        'at' => array('heading' => lang('Date'), 'width' => '11rem', 'kind' => 'date', 'sort' => 'at'),
        'actions' => array('heading' => lang('Action'), 'width' => '6rem', 'kind' => 'actions'),
    );
}

/**
 * The list's search: every word in the recipient, the message or the last
 * error, or in the shown name of a channel, source or state.
 *
 * @return array array('columns', 'labels') for pg_list_search_where()
 */
function pg_messages_sends_search()
{
    return array(
        'columns' => array('recipient', 'what', 'error'),
        'labels' => array(
            array('expr' => 'channel', 'labels' => pg_messages_sends_channels()),
            array('expr' => 'source', 'labels' => pg_messages_sends_sources()),
            array('expr' => 'state', 'labels' => pg_mail_queue_list_status_labels(pg_messages_sends_states())),
        ),
    );
}

/**
 * The row buttons a row gets under its own queue's rules: an e-mail Retry
 * while queued or failed; a text or WhatsApp Retry while queued, failed or
 * of unknown outcome and not expired (asked first when unknown: it may have reached
 * the phone); Delete on every queue row and campaign. Campaign recipients
 * have no Retry.
 *
 * @param array $record a row of the derived table
 * @param bool  $expired a text message past its expiry
 * @return array list of 'retry' | 'retry_confirm' | 'delete'
 */
function pg_messages_sends_buttons(array $record, $expired = false)
{
    $parts = pg_messages_sends_sid_parts($record['sid']);

    if ($parts === null) {
        return array();
    }

    $state = (string) $record['state'];
    $buttons = array();

    if (($parts['branch'] === 'mail') && in_array($state, array('queued', 'failed'), true)) {
        $buttons[] = 'retry';
    } elseif (in_array($parts['branch'], array('sms', 'whatsapp'), true) && in_array($state, array('queued', 'failed', 'uncertain'), true) && !$expired) {
        $buttons[] = ($state === 'uncertain') ? 'retry_confirm' : 'retry';
    }

    $buttons[] = 'delete';

    return $buttons;
}

/**
 * The delete question of a row.
 *
 * @param array $record
 * @return string
 */
function pg_messages_sends_delete_question(array $record)
{
    if ((string) $record['row_kind'] === 'campaign') {
        return lang('The campaign and its recipients will be deleted.');
    }

    $parts = pg_messages_sends_sid_parts($record['sid']);

    if (($parts !== null) && ($parts['branch'] === 'automatic')) {
        return lang('The automatic campaign send will be deleted.');
    }

    return pg_mail_queue_list_delete_question($record['state'] === 'sent' ? 'sent' : 'queued');
}

/**
 * One row of the list.
 *
 * @param array $record a row of the derived table
 * @param int   $now
 * @return array array('id', 'class', 'cells')
 */
function pg_messages_sends_row(array $record, $now)
{
    $states = pg_messages_sends_states();
    $sources = pg_messages_sends_sources();
    $channels = pg_messages_sends_channels();
    $parts = pg_messages_sends_sid_parts($record['sid']);
    $branch = ($parts !== null) ? $parts['branch'] : '';
    $id = ($parts !== null) ? $parts['id'] : 0;
    $sid = (int) $record['sid'];
    $state = isset($states[$record['state']]) ? $states[$record['state']] : array((string) $record['state'], 'bg-1 fg-body');

    // The mail server took it, but a delivery failure came back for it
    // (a Workspace mail, pg_mail_queue_bounced()).
    if (isset($record['bounce']) && ((string) $record['state'] === 'sent')) {
        $state = array(lang('Not delivered'), 'theme-danger');
        $record['error'] = (string) $record['bounce'];
    }

    // Meta took a WhatsApp message; what became of it on the phone comes
    // later through the webhook (delivered, read, or failed with a reason).
    $delivery = (isset($record['delivery']) && is_array($record['delivery'])) ? $record['delivery'] : null;

    if (($delivery !== null) && ((string) $record['state'] === 'sent') && ($delivery['delivery'] === 'failed')) {
        $state = array(lang('Not delivered'), 'theme-danger');

        if ((string) $record['error'] === '') {
            $record['error'] = ($delivery['error_code'] !== '') ? lang(array('string' => 'Meta error {var:1}', 'vars' => $delivery['error_code'])) : '';
        }
    }
    $what = (string) $record['what'];
    $what_html = ($what !== '') ? h($what) : '[' . h(lang('No name')) . ']';

    // A campaign opens to its screen and folds open to its recipients.
    if ($branch === 'email_campaign') {
        $what_html = '<a href="edit_email_campaign.php?id=' . $id . '" data-pg-open>' . $what_html . '</a>';
    } elseif ($branch === 'sms_campaign') {
        $what_html = '<a href="edit_sms_campaign.php?id=' . $id . '" data-pg-open>' . $what_html . '</a>';
    } elseif ($branch === 'automatic') {
        $what_html = '<a href="edit_email_campaign.php?id=' . $id . '" data-pg-open>' . $what_html . '</a>';
    }

    $what_html = pg_mail_queue_list_wrap($what_html);

    if ((string) $record['row_kind'] === 'campaign') {
        $what_html .= '<details class="small" data-pg-sends-fold="' . $sid . '"><summary>' . h(lang('Recipients')) . '</summary><div class="pg-sends-fold-body"></div></details>';
    }

    $total = (int) $record['total'];

    if ((string) $record['row_kind'] === 'campaign') {
        $recipient = h(lang(array('string' => '{var:1} Contact{suffix:1}', 'vars' => pg_format_number($total, 0), 'suffix' => ($total == 1) ? '' : 's')));
        $result = pg_messages_progress_html((int) $record['done'], $total, pg_format_number((int) $record['done'], 0) . ' / ' . pg_format_number($total, 0));
    } else {
        $recipient = ((($branch === 'sms') || ($branch === 'whatsapp')) ? '+' : '') . h((string) $record['recipient']);
        $result = '';

        if ((string) $record['state'] === 'sent') {
            $result = ((int) $record['next_at'] > 0) ? pg_mail_queue_list_time($record['next_at']) : '';

            // A WhatsApp message the phone took says so in place of the
            // time Meta took it, the furthest state first.
            if (($delivery !== null) && ($delivery['read_at'] > 0)) {
                $result = h(lang('Read:')) . ' ' . pg_mail_queue_list_time($delivery['read_at']);
            } elseif (($delivery !== null) && ($delivery['delivered_at'] > 0)) {
                $result = h(lang('Delivered:')) . ' ' . pg_mail_queue_list_time($delivery['delivered_at']);
            }
        } elseif ((string) $record['state'] === 'queued') {
            $result = ((int) $record['next_at'] > (int) $now) ? pg_mail_queue_list_time($record['next_at']) : h(lang('On the next run'));
        }

        // Given up at once: what is wrong is in the message itself, so the
        // queue does not try it again by itself (Retry stays).
        if (($branch === 'mail') && pg_mail_failed_permanently($record['state'], $record['attempts'])) {
            $result .= ' <span class="pg-pill pg-tone-gray" title="' . h(lang('The queue does not try it again by itself: sent again before its cause is fixed, it would fail the same way.')) . '">' . h(lang('Permanent')) . '</span>';
        }

        if ((int) $record['attempts'] > 0) {
            $result .= ' <span class="small fg-secondary">' . h(lang(array('string' => '{var:1} attempts', 'vars' => (int) $record['attempts']))) . '</span>';
        }

        if ((string) $record['error'] !== '') {
            $result .= pg_mail_queue_list_wrap('<span class="small fg-danger">' . h((string) $record['error']) . '</span>');
        }
    }

    $buttons = '';

    $expired = isset($record['expires']) && ((int) $record['expires'] > 0) && ((int) $record['expires'] <= (int) $now);

    foreach (pg_messages_sends_buttons($record, $expired) as $button) {
        if ($button === 'delete') {
            $buttons .= '<button type="submit" name="sends_action" value="delete:' . $sid . '" class="no-submit pg-icon-button pg-icon-button-sm" title="' . h(lang('Delete')) . '" aria-label="' . h(lang('Delete')) . '" data-confirm-content="' . h(pg_messages_sends_delete_question($record)) . '"><i class="bi bi-trash" aria-hidden="true"></i></button>';
        } else {
            $confirm = ($button === 'retry_confirm')
                ? ' data-confirm-content="' . h(lang('The provider may already have delivered this message, and sent again it would arrive twice. Check the provider\'s report first.')) . '"'
                : '';
            $buttons .= '<button type="submit" name="sends_action" value="retry:' . $sid . '" class="no-submit pg-icon-button pg-icon-button-sm me-1" title="' . h(lang('Retry')) . '" aria-label="' . h(lang('Retry')) . '"' . $confirm . '><i class="bi bi-arrow-clockwise" aria-hidden="true"></i></button>';
        }
    }

    $source = (string) $record['source'];

    return array('id' => $sid, 'class' => '', 'cells' => array(
        'what' => pg_list_cell($what_html, 'align-middle'),
        'recipient' => pg_list_cell($recipient, 'align-middle text-nowrap'),
        'channel' => pg_list_cell(pg_messages_sends_channel_cell((string) $record['channel'], isset($channels[$record['channel']]) ? $channels[$record['channel']] : (string) $record['channel']), 'align-middle text-nowrap'),
        'source' => pg_list_cell(h(isset($sources[$source]) ? $sources[$source] : $source), 'align-middle'),
        'state' => pg_list_cell(pg_messages_sends_pill($state[0], $state[1]), 'align-middle text-nowrap'),
        'result' => pg_list_cell($result, 'align-middle'),
        'at' => pg_list_cell(((int) $record['at'] > 0) ? pg_mail_queue_list_time($record['at']) : '', 'align-middle text-nowrap'),
        'actions' => pg_list_cell($buttons, 'align-middle text-nowrap'),
    ));
}

/**
 * Whether the user may read the list: the tab's gate
 * (validate_email_access()'s rule).
 *
 * @param mixed $user
 * @return bool
 */
function pg_messages_sends_list_gate($user)
{
    return is_array($user) && isset($user['role'], $user['id']) && is_numeric($user['role'])
        && pg_messages_gate_allows('email', $user['role'], !empty($user['manage_emails']));
}

/**
 * The list's source for the list table driver: the tab's gate
 * (validate_email_access()'s rule), the filters from the list's
 * parameters, newest first unless sorted otherwise.
 *
 * @param array    $user
 * @param array    $params array('filters' => channel, source, state, period)
 * @param int|null $now
 * @return array|null
 */
function pg_messages_sends_list_source($user, $params, $now = null)
{
    if (!pg_messages_sends_list_gate($user)) {
        return null;
    }

    $now = ($now === null) ? time() : (int) $now;
    $filters = pg_messages_sends_filters((is_array($params) && isset($params['filters']) && is_array($params['filters'])) ? $params['filters'] : array());
    $from = pg_messages_sends_from($filters, $user, $now, pg_messages_sends_ready_branches());
    $columns = pg_messages_sends_columns();
    $levels = pg_list_state('messages_sends', pg_list_table_sort_columns(pg_list_table_columns($columns)), array('sort' => 'at', 'order' => 'desc'))['sorts'];

    return pg_list_table_source(array(
        'list' => 'messages_sends',
        'columns' => $columns,
        'branches' => $from['branches'],
        'branch_id' => 'rid',
        'id' => 'sid',
        'id_order' => 'desc',
        'select' => 'sid, at, channel, row_kind, recipient, what, source, state, done, total, error, attempts, next_at, expires',
        'search' => pg_messages_sends_search(),
        'levels' => $levels,
        'created' => 'at',
        'params_out' => array('filters' => $filters),
        'rows' => function ($records) use ($now) {
            $rows = array();
            $mail = array();

            foreach ($records as $record) {
                $parts = pg_messages_sends_sid_parts($record['sid']);

                if (($parts !== null) && ($parts['branch'] === 'mail')) {
                    $mail[] = array('rid' => $parts['id'], 'state' => $record['state'], 'source' => $record['source']);
                }
            }

            $bounced = pg_mail_queue_bounced(pg_mail_queue_bounce_candidates($mail, 'state', 'source'));
            $delivered = pg_messages_sends_whatsapp_delivery($records);

            foreach ($records as $record) {
                $parts = pg_messages_sends_sid_parts($record['sid']);

                if (($parts !== null) && ($parts['branch'] === 'mail') && isset($bounced[$parts['id']])) {
                    $record['bounce'] = $bounced[$parts['id']];
                }

                if (($parts !== null) && ($parts['branch'] === 'whatsapp') && isset($delivered[$parts['id']])) {
                    $record['delivery'] = $delivered[$parts['id']];
                }

                $rows[] = pg_messages_sends_row($record, $now);
            }

            return $rows;
        },
    ));
}

/**
 * The tiles' figures, from indexed reads: queued and sending on the
 * queues' idx_due, sent in 24 hours, failed and outcome unknown; a
 * campaign's waiting recipients count as queued. A role 3 user counts only
 * their own campaigns' waiting recipients.
 *
 * @param array $user
 * @param int   $now
 * @return array queued, sending, sent, failed, uncertain
 */
function pg_messages_sends_tiles(array $user, $now)
{
    $counts = array('queued' => 0, 'sending' => 0, 'sent' => 0, 'failed' => 0, 'uncertain' => 0);

    if ((int) $user['role'] <= 2) {
        if (pg_mail_queue_ready()) {
            $row = db_item(
                "SELECT
                    SUM(CASE WHEN status = 'queued' THEN 1 ELSE 0 END) AS queued,
                    SUM(CASE WHEN status = 'sending' THEN 1 ELSE 0 END) AS sending,
                    SUM(CASE WHEN (status = 'sent') AND (sent_at >= " . ((int) $now - 86400) . ") THEN 1 ELSE 0 END) AS sent,
                    SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) AS failed
                FROM mail_outbox");

            foreach (array('queued', 'sending', 'sent', 'failed') as $key) {
                $counts[$key] += is_array($row) ? (int) $row[$key] : 0;
            }
        }

        if (pg_sms_queue_ready()) {
            foreach (pg_sms_queue_counts() as $key => $value) {
                $counts[$key] += (int) $value;
            }
        }

        if (pg_messages_sends_whatsapp_ready()) {
            foreach (pg_messages_sends_whatsapp_counts($now) as $key => $value) {
                $counts[$key] += (int) $value;
            }
        }
    }

    $waiting = db_q(
        "SELECT COUNT(*) FROM email_recipients r
        JOIN email_campaigns c ON c.id = r.email_campaign_id
        WHERE (r.complete = 0) AND (c.status = 'ready')" . (((int) $user['role'] > 2) ? ' AND (c.created_user_id = ?)' : ''),
        ((int) $user['role'] > 2) ? array((int) $user['id']) : array());

    if ($waiting) {
        $counts['queued'] += (int) mysqli_fetch_row($waiting)[0];
    }

    return $counts;
}

/**
 * Retries or deletes one row under its own queue's or campaign's rules.
 * Queue rows only for role <= 2; a campaign through its own delete, which
 * keeps a role 3 user to their own.
 *
 * @param array  $user
 * @param string $action 'retry:<sid>' or 'delete:<sid>'
 * @return string the outcome: '', 'queued' (an e-mail), 'queued_sms' (a
 *                text or WhatsApp message), 'sent', 'unsent', 'deleted'
 */
function pg_messages_sends_action(array $user, $action)
{
    if (!is_string($action) || !preg_match('/^(retry|delete):([0-9]{1,15})$/', $action, $match)) {
        return '';
    }

    $parts = pg_messages_sends_sid_parts($match[2]);

    if ($parts === null) {
        return '';
    }

    $queue = pg_messages_sends_queue_branch($parts['branch']);

    if ($queue && ((int) $user['role'] > 2)) {
        return '';
    }

    if ($match[1] === 'retry') {
        if ($parts['branch'] === 'mail') {
            return pg_mail_queue_retry($parts['id']);
        }

        if ($parts['branch'] === 'sms') {
            return pg_sms_queue_retry($parts['id']) ? 'queued_sms' : '';
        }

        if ($parts['branch'] === 'whatsapp') {
            return pg_messages_sends_whatsapp_retry($parts['id']) ? 'queued_sms' : '';
        }

        return '';
    }

    switch ($parts['branch']) {
        case 'mail':
            return pg_mail_queue_delete($parts['id']) ? 'deleted' : '';
        case 'sms':
            return pg_sms_queue_delete($parts['id']) ? 'deleted' : '';
        case 'whatsapp':
            return pg_messages_sends_whatsapp_delete($parts['id']) ? 'deleted' : '';
        case 'sms_campaign':
            return (pg_sms_campaigns_delete(array($parts['id']), $user) > 0) ? 'deleted' : '';
        default:
            require_once(PG_FUNCTIONS_DIR . '/includes/fn/email_campaign_list.php');

            return (pg_email_campaigns_delete(array($parts['id']), $user) > 0) ? 'deleted' : '';
    }
}

/**
 * A campaign row's recipients, for its fold: an e-mail campaign's from
 * email_recipients (with whether the address bounced), an SMS campaign's
 * from the SMS queue by its reference (gone after the sweep; the campaign's
 * counts stay). At most 200 lines; null when the user may not see it.
 *
 * @param array $user
 * @param mixed $sid
 * @return string|null markup
 */
function pg_messages_sends_recipients_html(array $user, $sid)
{
    $parts = pg_messages_sends_sid_parts($sid);

    if (($parts === null) || !in_array($parts['branch'], array('email_campaign', 'sms_campaign'), true)) {
        return null;
    }

    $id = (int) $parts['id'];
    $mine = (int) $user['role'] <= 2;
    $lines = array();

    if ($parts['branch'] === 'email_campaign') {
        $owner = db_value("SELECT created_user_id FROM email_campaigns WHERE id = '" . $id . "'");

        if (($owner === null) || (!$mine && ((int) $owner !== (int) $user['id']))) {
            return null;
        }

        foreach (db_items("SELECT email_address, complete, failed, last_error FROM email_recipients WHERE email_campaign_id = '" . $id . "' ORDER BY id LIMIT 200") as $row) {
            $state = ((int) $row['failed'] === 1) ? 'failed' : (((int) $row['complete'] === 1) ? 'sent' : 'queued');
            $lines[] = array($row['email_address'], $state, (string) $row['last_error']);
        }
    } else {
        $owner = db_value("SELECT created_by FROM sms_campaigns WHERE id = '" . $id . "'");

        if (($owner === null) || (!$mine && ((int) $owner !== (int) $user['id']))) {
            return null;
        }

        if (pg_sms_queue_ready()) {
            $items = db_q("SELECT recipient, status, last_error FROM sms_outbox WHERE reference LIKE ? ORDER BY id LIMIT 200", array('smscampaign:' . $id . ':%'));

            while ($items && ($row = mysqli_fetch_assoc($items))) {
                $lines[] = array('+' . $row['recipient'], (string) $row['status'], (string) $row['last_error']);
            }
        }
    }

    if (!$lines) {
        return '<p class="small fg-secondary mb-0">' . h(lang('No recipient is listed.')) . '</p>';
    }

    $states = pg_messages_sends_states();
    $html = '';

    foreach ($lines as $line) {
        $state = isset($states[$line[1]]) ? $states[$line[1]] : array($line[1], 'bg-1 fg-body');
        $html .= '<li>' . h($line[0]) . ' ' . pg_messages_sends_pill($state[0], $state[1])
            . (($line[2] !== '') ? ' <span class="fg-danger">' . h($line[2]) . '</span>' : '') . '</li>';
    }

    return '<ul class="list-unstyled mb-0">' . $html . '</ul>';
}

/* ---------------------------------------------------------------------------
   The WhatsApp queue's rows (whatsapp_outbox) as the list works them
   --------------------------------------------------------------------------- */

/**
 * Whether the upgrade has made the WhatsApp queue. Read here rather than
 * through includes/fn/whatsapp.php, which this screen does not load.
 *
 * @return bool
 */
function pg_messages_sends_whatsapp_ready()
{
    return pg_schema_has('whatsapp_outbox');
}

/**
 * What the webhook wrote about the WhatsApp rows of a batch: delivered,
 * read, or failed with Meta's code. One read by primary key for the batch.
 *
 * @param array $records rows of the derived table
 * @return array row id => delivery, delivered_at, read_at, error_code
 */
function pg_messages_sends_whatsapp_delivery(array $records)
{
    $ids = array();

    foreach ($records as $record) {
        $parts = pg_messages_sends_sid_parts($record['sid']);

        if (($parts !== null) && ($parts['branch'] === 'whatsapp') && ((string) $record['state'] === 'sent')) {
            $ids[] = (int) $parts['id'];
        }
    }

    if (!$ids) {
        return array();
    }

    $found = array();

    foreach ((array) db_items("SELECT id, delivery, delivered_at, read_at, error_code FROM whatsapp_outbox WHERE id IN (" . implode(', ', $ids) . ")") as $row) {
        $found[(int) $row['id']] = pg_messages_sends_whatsapp_delivery_row($row);
    }

    return $found;
}

/**
 * A row's delivery columns, typed. Pure.
 *
 * @param array $row delivery, delivered_at, read_at, error_code
 * @return array the same keys
 */
function pg_messages_sends_whatsapp_delivery_row(array $row)
{
    return array(
        'delivery' => (string) ($row['delivery'] ?? ''),
        'delivered_at' => (int) ($row['delivered_at'] ?? 0),
        'read_at' => (int) ($row['read_at'] ?? 0),
        'error_code' => (string) ($row['error_code'] ?? ''),
    );
}

/**
 * Puts a WhatsApp message back in the queue at once, under the SMS queue's
 * rule: one waiting, failed or of unknown outcome and not expired. One
 * being sent, one that was sent and one past its expiry are left alone.
 *
 * @param int $id
 * @return bool whether the message was put back in the queue
 */
function pg_messages_sends_whatsapp_retry($id)
{
    db(
        "UPDATE whatsapp_outbox
        SET status = 'queued', send_after = 0, attempts = 0, claimed_at = 0
        WHERE
            (id = '" . (int) $id . "')
            AND (status IN ('queued', 'failed', 'uncertain'))
            AND ((expires_at = 0) OR (expires_at > UNIX_TIMESTAMP()))");

    return mysqli_affected_rows(db::$con) > 0;
}

/**
 * Removes a WhatsApp message from the queue, sent or not.
 *
 * @param int $id
 * @return bool whether a message was removed
 */
function pg_messages_sends_whatsapp_delete($id)
{
    db("DELETE FROM whatsapp_outbox WHERE id = '" . (int) $id . "'");

    return mysqli_affected_rows(db::$con) > 0;
}

/**
 * The WhatsApp queue's figures for the tiles, from the queue's idx_due.
 *
 * @param int $now
 * @return array queued, sending, sent (in the last 24 hours), failed,
 *               uncertain
 */
function pg_messages_sends_whatsapp_counts($now)
{
    $row = db_item(
        "SELECT
            SUM(CASE WHEN status = 'queued' THEN 1 ELSE 0 END) AS queued,
            SUM(CASE WHEN status = 'sending' THEN 1 ELSE 0 END) AS sending,
            SUM(CASE WHEN (status = 'sent') AND (sent_at >= " . ((int) $now - 86400) . ") THEN 1 ELSE 0 END) AS sent,
            SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) AS failed,
            SUM(CASE WHEN status = 'uncertain' THEN 1 ELSE 0 END) AS uncertain
        FROM whatsapp_outbox");

    $counts = array();

    foreach (array('queued', 'sending', 'sent', 'failed', 'uncertain') as $status) {
        $counts[$status] = (is_array($row) && isset($row[$status])) ? (int) $row[$status] : 0;
    }

    return $counts;
}
