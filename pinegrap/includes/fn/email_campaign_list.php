<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The e-mail campaign lists (view_email_campaigns.php for ready and paused
 * campaigns, view_email_campaign_history.php for complete and cancelled
 * ones) on the list table driver, printed whole in browser mode. Both share
 * the columns, the query and the rows built here.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/list_table.php');

/**
 * The columns a campaign list sorts by, code => heading, in the order the
 * list shows them. Scheduled Time is offered only while the e-mail campaign
 * job is on, since only then is it shown.
 *
 * @param bool $job whether the e-mail campaign job is on
 * @return array
 */
function pg_email_campaign_list_sort_columns($job)
{
    $columns = array(
        'subject'      => lang('Subject'),
        'profile'      => lang('Profile'),
        'page_to_send' => lang('Page to Send'),
    );

    if ($job) {
        $columns['scheduled_time'] = lang('Scheduled Time');
    }

    $columns['status'] = lang('Status');
    $columns['purpose'] = lang('Purpose');
    $columns['created'] = lang('Created');
    $columns['last_modified'] = lang('Last Modified');

    return $columns;
}

/**
 * The sort a campaign list starts with: with the job on, by scheduled time,
 * earliest first; otherwise by the last change, newest first.
 *
 * @param bool $job
 * @return array
 */
function pg_email_campaign_list_sort_default($job)
{
    return $job ? array('sort' => 'scheduled_time', 'order' => 'asc') : array('sort' => 'last_modified', 'order' => 'desc');
}

/**
 * The driver's columns of a campaign list. The columns without a sort code
 * (To, Progress, Delivery report) do not sort, as before.
 *
 * @param bool $job
 * @return array key => spec, as pg_list_table_printed() takes them
 */
function pg_email_campaign_list_columns($job)
{
    $columns = array(
        'subject' => array('heading' => lang('Subject')),
        'to' => array('heading' => lang('To'), 'sortable' => false),
        'profile' => array('heading' => lang('Profile')),
        'page_to_send' => array('heading' => lang('Page to Send')),
    );

    if ($job) {
        $columns['scheduled_time'] = array('heading' => lang('Scheduled Time'), 'kind' => 'date');
    }

    $columns['status'] = array('heading' => lang('Status'), 'kind' => 'badge');
    $columns['progress'] = array('heading' => lang('Progress (Subscribers)'), 'sortable' => false);
    $columns['delivery'] = array('heading' => lang('Delivery report'), 'sortable' => false);
    $columns['purpose'] = array('heading' => lang('Purpose'));
    $columns['created'] = array('heading' => lang('Created'), 'kind' => 'date');
    $columns['last_modified'] = array('heading' => lang('Last Modified'), 'kind' => 'date');

    return $columns;
}

/**
 * A campaign list printed whole: the campaigns in the given statuses that
 * the user may manage (a role 3 user only their own).
 *
 * @param string $list     the list's pg_list_state() name
 * @param array  $statuses the two statuses, e.g. array('ready', 'paused')
 * @param array  $user     validate_user()
 * @param array  $levels   the kept sort levels (pg_list_state()['sorts'])
 * @return array pg_list_table_printed()
 */
function pg_email_campaign_list($list, array $statuses, array $user, array $levels)
{
    $job = email_campaign_job_enabled();

    // Only codes pg_list_state() let through reach pg_list_order_by(), and
    // it writes nothing but these.
    $order_by = pg_list_order_by($levels, array(
        'profile'        => 'email_campaign_profiles.name',
        'subject'        => 'email_campaigns.subject',
        'page_to_send'   => 'page.page_name',
        'scheduled_time' => 'email_campaigns.start_time',
        'status'         => 'email_campaigns.status',
        'purpose'        => 'email_campaigns.purpose',
        'created'        => 'email_campaigns.created_timestamp',
        'last_modified'  => 'email_campaigns.last_modified_timestamp',
    ));

    // Each list shows campaigns in two statuses.
    $statuses = array_values($statuses);

    $result = db_q(
        "SELECT
            email_campaigns.id,
            email_campaigns.type,
            email_campaign_profiles.name AS email_campaign_profile_name,
            email_campaigns.subject,
            page.page_name,
            email_campaigns.start_time,
            email_campaigns.status,
            email_campaigns.purpose,
            email_campaigns.created_user_id,
            created_user.user_username as created_username,
            email_campaigns.created_timestamp,
            last_modified_user.user_username as last_modified_username,
            email_campaigns.last_modified_timestamp
        FROM email_campaigns
        LEFT JOIN email_campaign_profiles ON email_campaigns.email_campaign_profile_id = email_campaign_profiles.id
        LEFT JOIN page ON email_campaigns.page_id = page.page_id
        LEFT JOIN user as created_user ON email_campaigns.created_user_id = created_user.user_id
        LEFT JOIN user as last_modified_user ON email_campaigns.last_modified_user_id = last_modified_user.user_id
        WHERE (email_campaigns.status = ?) OR (email_campaigns.status = ?)
        ORDER BY $order_by", array((string) ($statuses[0] ?? ''), (string) ($statuses[1] ?? ''))) or output_error('Query failed.');

    $campaigns = array();

    while ($row = mysqli_fetch_assoc($result)) {
        // A role above User sees every campaign, a User only their own.
        if (($user['role'] < 3) || ($row['created_user_id'] == $user['id'])) {
            $campaigns[] = $row;
        }
    }

    // Delivered, suppressed, bounced and complained per campaign, one read
    // for the whole list.
    $delivery_counts = $campaigns ? pg_mail_bounce_campaign_counts(array_column($campaigns, 'id')) : array();
    $send_to = urlencode(REQUEST_URL);
    $rows = array();

    foreach ($campaigns as $campaign) {
        $id = (int) $campaign['id'];
        $count = db_q("SELECT COUNT(*), COALESCE(SUM(complete = '1'), 0) FROM email_recipients WHERE email_campaign_id = ?", array((string) $id)) or output_error('Query failed.');
        list($total, $done) = array_map('intval', mysqli_fetch_row($count));

        if ($campaign['type'] === 'automatic') {
            // The single recipient's e-mail address of an automatic campaign.
            $recipient = db_q("SELECT email_address FROM email_recipients WHERE email_campaign_id = ? LIMIT 1", array((string) $id)) or output_error('Query failed.');
            $recipient = mysqli_fetch_row($recipient);
            $output_to = h($recipient ? (string) $recipient[0] : '');
        } else {
            $output_to = lang(array('string' => '{var:1} Contact{suffix:1}', 'vars' => pg_format_number($total, 0), 'suffix' => ($total == 1) ? '' : 's'));
        }

        $percentage = ($total > 0) ? $done / $total * 100 : 100;
        $subject = ((string) $campaign['subject'] !== '') ? h($campaign['subject']) : '[' . h(lang('No name')) . ']';

        $cells = array(
            'subject' => pg_list_cell('<a href="edit_email_campaign.php?id=' . $id . '&amp;send_to=' . h($send_to) . '" data-pg-open>' . $subject . '</a>'),
            'to' => pg_list_cell($output_to, 'align-middle', array('data-pg-sort-value' => $total)),
            'profile' => pg_list_cell(h($campaign['email_campaign_profile_name'])),
            'page_to_send' => pg_list_cell(h($campaign['page_name'])),
        );

        if ($job) {
            $start = ($campaign['start_time'] == '0000-00-00 00:00:00') ? 0 : (int) strtotime($campaign['start_time']);
            $cells['scheduled_time'] = pg_list_cell($start ? get_relative_time(array('timestamp' => $start)) : '', 'align-middle', array('data-pg-sort-value' => $start));
        }

        $cells['status'] = pg_list_cell(get_email_campaign_status_name($campaign['status']));
        $cells['progress'] = pg_list_cell(lang(array('string' => '{var:1}%', 'vars' => array(pg_format_number($percentage, 0))))
            . ' (' . lang(array('string' => '{var:1} of {var:2}', 'vars' => array(pg_format_number($done, 0), pg_format_number($total, 0)))) . ')',
            'align-middle', array('data-pg-sort-value' => round($percentage, 2)));
        $cells['delivery'] = pg_list_cell(pg_mail_bounce_campaign_cell($delivery_counts[$id] ?? array()));
        $cells['purpose'] = pg_list_cell(lang(h(ucwords((string) $campaign['purpose']))));
        $cells['created'] = pg_list_table_when_cell($campaign['created_timestamp'], (string) $campaign['created_username']);
        $cells['last_modified'] = pg_list_table_when_cell($campaign['last_modified_timestamp'], (string) $campaign['last_modified_username']);

        $rows[] = array('id' => $id, 'cells' => $cells);
    }

    return pg_list_table_printed(array(
        'list' => $list,
        'columns' => pg_email_campaign_list_columns($job),
        'levels' => $levels,
        'rows' => $rows,
        'ids_field' => 'email_campaigns[]',
    ));
}

/**
 * The Delete Selected button of a campaign list's selection bar; the list
 * sits in a form posting to delete_email_campaigns.php.
 *
 * @return string
 */
function pg_email_campaign_list_delete_button()
{
    return '<button type="button" value="Delete Selected" class="btn btn-sm btn-solid theme-danger" data-confirm-content="'
        . h(lang(array('string' => 'WARNING: Selected {var:1} will be permanently deleted.', 'vars' => array(lang('campaigns'))))) . '">'
        . '<i class="bi bi-trash me-1" aria-hidden="true"></i>' . lang('Delete Selected') . '</button>';
}
