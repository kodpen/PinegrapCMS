<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * My SMS Campaigns: the SMS campaigns a user can manage, their status, when
 * they are sent and what became of their messages; Delete Selected posts
 * back here. The campaigns are queued by the general job
 * (includes/fn/sms_campaigns.php).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/list_table.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/sms_campaigns.php');

$user = validate_user();
validate_email_access($user);

if (!pg_sms_campaigns_ready()) {
    output_error(lang('The SMS campaigns are not available yet; run the upgrade.'));
}

$liveform = new liveform('view_sms_campaigns');

// Delete Selected: a role 3 user deletes only campaigns of their own. A
// queued campaign's messages stay in the SMS queue.
if ($_POST) {
    validate_token_field();

    $deleted = 0;

    foreach ((array) ($_POST['sms_campaigns'] ?? array()) as $id) {
        // A nested list (sms_campaigns[][]=x) cast to int is 1: skipped, it
        // would delete campaign 1.
        if (!is_scalar($id)) {
            continue;
        }

        $id = (int) $id;
        $owner = db_value("SELECT created_by FROM sms_campaigns WHERE id = '" . $id . "'");

        if (($owner === null) || (($user['role'] == 3) && ((int) $owner !== (int) $user['id']))) {
            continue;
        }

        // A failed DELETE ends the request before the xref rows go or the
        // campaign is counted as deleted.
        db_q("DELETE FROM sms_campaigns WHERE id = ?", array((string) $id)) or output_error('Query failed.');
        db_q("DELETE FROM contact_groups_sms_campaigns_xref WHERE sms_campaign_id = ?", array((string) $id)) or output_error('Query failed.');
        $deleted++;
    }

    if ($deleted > 0) {
        log_activity(lang(array('string' => '{var:1} SMS campaign(s) were deleted', 'vars' => array($deleted))), $_SESSION['sessionusername']);
        $liveform->add_notice(lang(array('string' => '{var:1} SMS campaign(s) were deleted', 'vars' => array($deleted))));
    }

    header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/view_sms_campaigns.php');
    exit;
}

$result = db_q(
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
    ORDER BY sms_campaigns.updated_at DESC, sms_campaigns.id DESC",
    array()) or output_error('Query failed.');

$statuses = pg_sms_campaign_statuses();

// Every campaign the user may manage is printed: the list table driver sorts
// and searches them in the browser (browser mode) and keeps a heading's sort.
$list_rows = array();

while ($campaign = mysqli_fetch_assoc($result)) {
    if (($user['role'] == 3) && ((int) $campaign['created_by'] !== (int) $user['id'])) {
        continue;
    }

    $name = ($campaign['name'] !== '') ? $campaign['name'] : '[' . lang('No name') . ']';
    $text = (mb_strlen($campaign['body']) > 80) ? mb_substr($campaign['body'], 0, 80) . '…' : $campaign['body'];
    $start_at = (int) $campaign['start_at'];

    // What the queue still knows, then the counts kept on the campaign.
    $output_result = '';

    if ($campaign['status'] === 'queued') {
        $outbox = pg_sms_campaign_outbox_counts($campaign['id']);

        $output_result = lang(array(
            'string' => '{var:1} queued, {var:2} refused',
            'vars'   => array(pg_format_number($campaign['queued_count'], 0), pg_format_number($campaign['refused_count'], 0)),
        ));

        if ($outbox) {
            $output_result .= '<div class="small fg-secondary">' . lang(array(
                'string' => 'In the SMS queue: {var:1} sent, {var:2} waiting, {var:3} failed',
                'vars'   => array(
                    pg_format_number($outbox['sent'] ?? 0, 0),
                    pg_format_number(($outbox['queued'] ?? 0) + ($outbox['sending'] ?? 0), 0),
                    pg_format_number(($outbox['failed'] ?? 0) + ($outbox['uncertain'] ?? 0), 0),
                ),
            )) . '</div>';
        }
    }

    $list_rows[] = array(
        'id' => (int) $campaign['id'],
        'cells' => array(
            'name' => pg_list_cell('<a href="edit_sms_campaign.php?id=' . (int) $campaign['id'] . '" data-pg-open>' . h($name) . '</a>'),
            'text' => pg_list_cell(h($text)),
            'status' => pg_list_cell(h($statuses[$campaign['status']] ?? $campaign['status'])),
            'send_time' => pg_list_cell($start_at > 0 ? get_relative_time(array('timestamp' => $start_at)) : '', 'align-middle', array('data-pg-sort-value' => $start_at)),
            'result' => pg_list_cell($output_result, 'align-middle', array('data-pg-sort-value' => (int) $campaign['queued_count'])),
            'last_modified' => pg_list_table_when_cell($campaign['updated_at'], (string) $campaign['updated_username']),
        ),
    );
}

$columns = array(
    'name' => array('heading' => lang('Name')),
    'text' => array('heading' => lang('Text')),
    'status' => array('heading' => lang('Status'), 'kind' => 'badge'),
    'send_time' => array('heading' => lang('Send Time'), 'kind' => 'date'),
    'result' => array('heading' => lang('Result')),
    'last_modified' => array('heading' => lang('Last Modified'), 'kind' => 'date'),
);
$sort_columns = array();

foreach ($columns as $key => $spec) {
    $sort_columns[$key] = $spec['heading'];
}

$sort_default = array('sort' => 'last_modified', 'order' => 'desc');
$list_state = pg_list_state('view_sms_campaigns', $sort_columns, $sort_default);
$list = pg_list_table_printed(array(
    'list' => 'view_sms_campaigns',
    'columns' => $columns,
    'levels' => $list_state['sorts'],
    'rows' => $list_rows,
    'ids_field' => 'sms_campaigns[]',
));

$output_warning = '';

if (!pg_sms_ready()) {
    $output_warning = '<div class="alert theme-warning" role="status"><p class="mb-0">' . lang('SMS is not set up: a campaign is kept, but every number is refused until a provider is set up under Settings, Communication.') . '</p></div>';
} elseif (!pg_sms_marketing_identity_filled(pg_sms_marketing_settings())) {
    $output_warning = '<div class="alert theme-warning" role="status"><p class="mb-0">' . lang('The sender identity of marketing messages is empty: every number is refused until the trade name or MERSİS number is set under Settings, Communication, SMS Messages.') . '</p></div>';
}

echo
pg_page_shell(
    array(
        'title'               => lang('My SMS Campaigns'),
        'extra classes'       => 'campaign',
        'icon'                => 'campaign',
        'heading'             => lang('My SMS Campaigns'),
        'heading_description' => lang('Text messages to the contacts of selected contact groups. Only numbers with SMS consent that İYS has not refused receive them; the sender identity and the type label are added to every message.'),
    )
) . '
<main id="content" class="container-fluid">
    <div class="row">
        <div class="col-12">
            ' . $liveform->output_errors() . '
            ' . $liveform->output_notices() . '
            ' . $output_warning . '
            ' . pg_screen_toolbar(array(
                'primary'   => array('label' => lang('Create'), 'url' => 'edit_sms_campaign.php', 'icon' => 'bi-plus-circle'),
                'secondary' => array('label' => lang('My Campaigns'), 'url' => 'view_email_campaigns.php', 'icon' => 'bi-megaphone'),
                'search'    => array(pg_list_table_search_box('view_sms_campaigns'), pg_list_sort_menu('view_sms_campaigns.php', $sort_columns, $list_state['sorts'], $sort_default)),
            )) . '
            <form name="form" action="view_sms_campaigns.php" method="post" class="disable_shortcut">
                ' . get_token_field() . '
                ' . pg_list_table_html($list['source'], $list['page'], lang('My SMS Campaigns'), '<button type="button" value="Delete Selected" class="btn btn-sm btn-solid theme-danger" data-confirm-content="' . h(lang(array('string' => 'WARNING: Selected {var:1} will be permanently deleted.', 'vars' => array(lang('campaigns'))))) . '"><i class="bi bi-trash me-1" aria-hidden="true"></i>' . lang('Delete Selected') . '</button>') . '
            </form>
        </div>
    </div>
</main>
' . pg_js_bundle_tags('assets/js/pg_list_table.min.js') .
output_footer();

$liveform->remove_form();
