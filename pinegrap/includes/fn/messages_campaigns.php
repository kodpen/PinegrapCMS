<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Communication › Campaigns (view_email_campaigns.php): one list for the
 * e-mail and SMS campaigns sent once to many, with a channel and a state
 * filter, and "New campaign", which asks the channel first. The campaigns
 * keep their tables and their own screens (edit_email_campaign.php,
 * edit_sms_campaign.php); this module only lists and deletes them.
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
 * The statuses each state shows, per channel. Active: still to send or
 * sending; finished: done or cancelled.
 *
 * @return array channel => state => statuses
 */
function pg_messages_campaign_state_statuses()
{
    return array(
        'email' => array('active' => array('ready', 'paused'), 'finished' => array('complete', 'cancelled')),
        'sms'   => array('active' => array('draft', 'ready'), 'finished' => array('queued', 'cancelled')),
    );
}

/**
 * The list's filters read from the address: channel (all, email, sms) and
 * state (active, finished). An unknown value falls back to the default; sms
 * only while the SMS campaigns are there.
 *
 * @param array $get
 * @param bool  $sms whether SMS campaigns can be listed
 * @return array channel, state
 */
function pg_messages_campaign_filters($get, $sms)
{
    $channel = (isset($get['channel']) && is_string($get['channel'])) ? $get['channel'] : 'all';
    $state = (isset($get['state']) && is_string($get['state'])) ? $get['state'] : 'active';

    if (!in_array($channel, $sms ? array('all', 'email', 'sms') : array('all', 'email'), true)) {
        $channel = 'all';
    }

    if (!in_array($state, array('active', 'finished'), true)) {
        $state = 'active';
    }

    return array('channel' => $channel, 'state' => $state);
}

/**
 * The channels a filter lists.
 *
 * @param string $channel all, email, sms
 * @param bool   $sms
 * @return array
 */
function pg_messages_campaign_channels($channel, $sms)
{
    if ($channel === 'email') {
        return array('email');
    }

    if ($channel === 'sms') {
        return $sms ? array('sms') : array();
    }

    return $sms ? array('email', 'sms') : array('email');
}

/**
 * One list id for a campaign of either table: even for e-mail, odd for SMS,
 * so a selection names both without a clash.
 *
 * @param string $channel email or sms
 * @param int    $id
 * @return int
 */
function pg_messages_campaign_row_id($channel, $id)
{
    return ((int) $id * 2) + (($channel === 'sms') ? 1 : 0);
}

/**
 * Selected list ids back into the two tables' ids. Anything that is not a
 * positive whole number is dropped.
 *
 * @param mixed $ids
 * @return array email => ids, sms => ids
 */
function pg_messages_campaign_split_ids($ids)
{
    $split = array('email' => array(), 'sms' => array());

    foreach ((array) $ids as $value) {
        if (!is_scalar($value) || !preg_match('/^[0-9]{1,18}$/', (string) $value)) {
            continue;
        }

        $value = (int) $value;
        $id = intdiv($value, 2);

        if ($id < 1) {
            continue;
        }

        $split[($value % 2) ? 'sms' : 'email'][] = $id;
    }

    $split['email'] = array_values(array_unique($split['email']));
    $split['sms'] = array_values(array_unique($split['sms']));

    return $split;
}

/**
 * The list's columns.
 *
 * @return array key => spec, as pg_list_table_printed() takes them
 */
function pg_messages_campaign_columns()
{
    return array(
        'name'          => array('heading' => lang('Name')),
        'channel'       => array('heading' => lang('Channel'), 'kind' => 'badge'),
        'to'            => array('heading' => lang('To')),
        'status'        => array('heading' => lang('Status'), 'kind' => 'badge'),
        'send_time'     => array('heading' => lang('Send Time'), 'kind' => 'date'),
        'result'        => array('heading' => lang('Result'), 'sortable' => false),
        'last_modified' => array('heading' => lang('Last Modified'), 'kind' => 'date'),
    );
}

/**
 * "New campaign" for the toolbar: a menu that asks the channel first, or a
 * plain button while e-mail is the only one.
 *
 * @param bool $sms
 * @return string|array markup, or a pg_screen_toolbar() button array
 */
function pg_messages_new_campaign_button($sms)
{
    if (!$sms) {
        return array('label' => lang('New campaign'), 'url' => 'add_email_campaign.php', 'icon' => 'bi-plus-lg');
    }

    return '<div class="pg-menu-wrap">'
        . '<button type="button" class="btn btn-sm btn-solid theme-primary rounded-pill px-5" data-bs-toggle="menu" data-bs-display="static" aria-expanded="false">'
        . '<i class="bi bi-plus-lg me-2" aria-hidden="true"></i>' . h(lang('New campaign')) . '</button>'
        . '<ul class="menu" aria-label="' . h(lang('Channel')) . '">'
        . '<li><a class="menu-item pg-link-body-emphasis" href="add_email_campaign.php"><i class="bi bi-envelope me-3" aria-hidden="true"></i>' . h(lang('E-mail')) . '</a></li>'
        . '<li><a class="menu-item pg-link-body-emphasis" href="edit_sms_campaign.php"><i class="bi bi-chat-dots me-3" aria-hidden="true"></i>' . h(lang('SMS')) . '</a></li>'
        . '</ul></div>';
}

/**
 * Orders the merged rows newest change first, id last, before the driver
 * applies a heading's sort in the browser.
 *
 * @param array $rows each with 'modified' (timestamp) and 'id'
 * @return array
 */
function pg_messages_campaign_sort($rows)
{
    usort($rows, function ($a, $b) {
        if ((int) $a['modified'] !== (int) $b['modified']) {
            return ((int) $b['modified'] < (int) $a['modified']) ? -1 : 1;
        }

        return ((int) $b['id'] < (int) $a['id']) ? -1 : (((int) $b['id'] > (int) $a['id']) ? 1 : 0);
    });

    return $rows;
}

/**
 * The e-mail campaigns in the given statuses the user may manage, as merged
 * rows (id, modified, cells). Manual ones only: an automatic campaign, one
 * per profile send, is a single message of the Sends tab.
 *
 * @param array $statuses
 * @param array $user
 * @return array
 */
function pg_messages_campaign_email_rows(array $statuses, array $user)
{
    // The statuses come from pg_messages_campaign_state_statuses(), never
    // from the request.
    $in = pg_messages_campaign_status_in($statuses);
    $campaigns = db_items(
        "SELECT
            email_campaigns.id,
            email_campaigns.type,
            email_campaigns.subject,
            email_campaigns.start_time,
            email_campaigns.status,
            email_campaigns.created_user_id,
            email_campaigns.last_modified_timestamp,
            last_modified_user.user_username AS last_modified_username
        FROM email_campaigns
        LEFT JOIN user AS last_modified_user ON email_campaigns.last_modified_user_id = last_modified_user.user_id
        WHERE (email_campaigns.status IN ($in)) AND (email_campaigns.type = 'manual')");

    $mine = array();

    foreach ((array) $campaigns as $campaign) {
        if (($user['role'] < 3) || ((int) $campaign['created_user_id'] === (int) $user['id'])) {
            $mine[] = $campaign;
        }
    }

    // Delivered, suppressed, bounced and complained per campaign, one read
    // for the whole list.
    $delivery = $mine ? pg_mail_bounce_campaign_counts(array_column($mine, 'id')) : array();
    $send_to = urlencode(REQUEST_URL);
    $rows = array();

    foreach ($mine as $campaign) {
        $id = (int) $campaign['id'];
        $count = db_q("SELECT COUNT(*), COALESCE(SUM(complete = '1'), 0) FROM email_recipients WHERE email_campaign_id = ?", array((string) $id)) or output_error('Query failed.');
        list($total, $done) = array_map('intval', mysqli_fetch_row($count));

        $to = h(lang(array('string' => '{var:1} Contact{suffix:1}', 'vars' => pg_format_number($total, 0), 'suffix' => ($total == 1) ? '' : 's')));

        $subject = ((string) $campaign['subject'] !== '') ? h($campaign['subject']) : '[' . h(lang('No name')) . ']';
        $start = ($campaign['start_time'] == '0000-00-00 00:00:00') ? 0 : (int) strtotime($campaign['start_time']);
        $percentage = ($total > 0) ? $done / $total * 100 : 100;
        $result = lang(array('string' => '{var:1}%', 'vars' => array(pg_format_number($percentage, 0))))
            . ' (' . lang(array('string' => '{var:1} of {var:2}', 'vars' => array(pg_format_number($done, 0), pg_format_number($total, 0)))) . ')'
            . '<div class="small fg-secondary">' . pg_mail_bounce_campaign_cell($delivery[$id] ?? array()) . '</div>';

        $rows[] = array(
            'id' => pg_messages_campaign_row_id('email', $id),
            'modified' => (int) $campaign['last_modified_timestamp'],
            'cells' => array(
                'name' => pg_list_cell('<a href="edit_email_campaign.php?id=' . $id . '&amp;send_to=' . h($send_to) . '" data-pg-open>' . $subject . '</a>'),
                'channel' => pg_list_cell(h(lang('E-mail'))),
                'to' => pg_list_cell($to, 'align-middle', array('data-pg-sort-value' => $total)),
                'status' => pg_list_cell(get_email_campaign_status_name($campaign['status'])),
                'send_time' => pg_list_cell($start ? get_relative_time(array('timestamp' => $start)) : '', 'align-middle', array('data-pg-sort-value' => $start)),
                'result' => pg_list_cell($result, 'align-middle', array('data-pg-sort-value' => round($percentage, 2))),
                'last_modified' => pg_list_table_when_cell($campaign['last_modified_timestamp'], (string) $campaign['last_modified_username']),
            ),
        );
    }

    return $rows;
}

/**
 * The SMS campaigns in the given statuses the user may manage, as merged
 * rows.
 *
 * @param array $statuses
 * @param array $user
 * @return array
 */
function pg_messages_campaign_sms_rows(array $statuses, array $user)
{
    // The statuses come from pg_messages_campaign_state_statuses(), never
    // from the request.
    $in = pg_messages_campaign_status_in($statuses);
    $campaigns = db_items(
        "SELECT
            sms_campaigns.id,
            sms_campaigns.name,
            sms_campaigns.body,
            sms_campaigns.status,
            sms_campaigns.start_at,
            sms_campaigns.queued_count,
            sms_campaigns.refused_count,
            sms_campaigns.created_by,
            sms_campaigns.updated_at,
            user.user_username AS updated_username
        FROM sms_campaigns
        LEFT JOIN user ON sms_campaigns.updated_by = user.user_id
        WHERE sms_campaigns.status IN ($in)");

    $names = pg_sms_campaign_statuses();
    $rows = array();

    foreach ((array) $campaigns as $campaign) {
        if (((int) $user['role'] === 3) && ((int) $campaign['created_by'] !== (int) $user['id'])) {
            continue;
        }

        $id = (int) $campaign['id'];
        $name = ((string) $campaign['name'] !== '') ? (string) $campaign['name'] : '[' . lang('No name') . ']';
        $text = (mb_strlen((string) $campaign['body']) > 80) ? mb_substr((string) $campaign['body'], 0, 80) . '…' : (string) $campaign['body'];
        $start_at = (int) $campaign['start_at'];
        $result = '';

        // What the queue still knows, then the counts kept on the campaign.
        if ($campaign['status'] === 'queued') {
            $result = lang(array('string' => '{var:1} queued, {var:2} refused', 'vars' => array(pg_format_number($campaign['queued_count'], 0), pg_format_number($campaign['refused_count'], 0))));
            $outbox = pg_sms_campaign_outbox_counts($id);

            if ($outbox) {
                $result .= '<div class="small fg-secondary">' . lang(array(
                    'string' => 'In the SMS queue: {var:1} sent, {var:2} waiting, {var:3} failed',
                    'vars'   => array(
                        pg_format_number($outbox['sent'] ?? 0, 0),
                        pg_format_number(($outbox['queued'] ?? 0) + ($outbox['sending'] ?? 0), 0),
                        pg_format_number(($outbox['failed'] ?? 0) + ($outbox['uncertain'] ?? 0), 0),
                    ),
                )) . '</div>';
            }
        }

        $rows[] = array(
            'id' => pg_messages_campaign_row_id('sms', $id),
            'modified' => (int) $campaign['updated_at'],
            'cells' => array(
                'name' => pg_list_cell('<a href="edit_sms_campaign.php?id=' . $id . '" data-pg-open>' . h($name) . '</a><div class="small fg-secondary">' . h($text) . '</div>'),
                'channel' => pg_list_cell(h(lang('SMS'))),
                'to' => pg_list_cell(''),
                'status' => pg_list_cell(h($names[$campaign['status']] ?? $campaign['status'])),
                'send_time' => pg_list_cell($start_at > 0 ? get_relative_time(array('timestamp' => $start_at)) : '', 'align-middle', array('data-pg-sort-value' => $start_at)),
                'result' => pg_list_cell($result, 'align-middle', array('data-pg-sort-value' => (int) $campaign['queued_count'])),
                'last_modified' => pg_list_table_when_cell($campaign['updated_at'], (string) $campaign['updated_username']),
            ),
        );
    }

    return $rows;
}

/**
 * The Campaigns tab's list for the filters, printed whole: e-mail and SMS
 * campaigns merged, newest change first.
 *
 * @param array $filters pg_messages_campaign_filters()
 * @param array $user
 * @param array $levels  the kept sort levels
 * @param bool  $sms     whether SMS campaigns can be listed
 * @return array pg_list_table_printed()
 */
function pg_messages_campaign_list(array $filters, array $user, array $levels, $sms)
{
    $statuses = pg_messages_campaign_state_statuses();
    $rows = array();

    foreach (pg_messages_campaign_channels($filters['channel'], $sms) as $channel) {
        $wanted = $statuses[$channel][$filters['state']];
        $rows = array_merge($rows, ($channel === 'sms') ? pg_messages_campaign_sms_rows($wanted, $user) : pg_messages_campaign_email_rows($wanted, $user));
    }

    $printed = array();

    foreach (pg_messages_campaign_sort($rows) as $row) {
        $printed[] = array('id' => $row['id'], 'cells' => $row['cells']);
    }

    return pg_list_table_printed(array(
        'list' => 'view_email_campaigns',
        'columns' => pg_messages_campaign_columns(),
        'levels' => $levels,
        'rows' => $printed,
        'ids_field' => 'campaigns[]',
    ));
}

/**
 * A status list for an IN (...) clause, each value quoted and escaped.
 *
 * @param array $statuses
 * @return string
 */
function pg_messages_campaign_status_in(array $statuses)
{
    $quoted = array();

    foreach ($statuses as $status) {
        $quoted[] = "'" . e((string) $status) . "'";
    }

    return $quoted ? implode(', ', $quoted) : "''";
}
