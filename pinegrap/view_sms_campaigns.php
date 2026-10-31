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
$output_rows = '';

while ($campaign = mysqli_fetch_assoc($result)) {
    if (($user['role'] == 3) && ((int) $campaign['created_by'] !== (int) $user['id'])) {
        continue;
    }

    $name = ($campaign['name'] !== '') ? $campaign['name'] : '[' . lang('No name') . ']';
    $text = (mb_strlen($campaign['body']) > 80) ? mb_substr($campaign['body'], 0, 80) . '…' : $campaign['body'];

    $when = '';

    if ((int) $campaign['start_at'] > 0) {
        $when = get_relative_time(array('timestamp' => (int) $campaign['start_at']));
    }

    // What the queue still knows, then the counts kept on the campaign.
    $output_result = '';

    if ($campaign['status'] === 'queued') {
        $outbox = pg_sms_campaign_outbox_counts($campaign['id']);

        $output_result = lang(array(
            'string' => '{var:1} queued, {var:2} refused',
            'vars'   => array(pg_format_number($campaign['queued_count'], 0), pg_format_number($campaign['refused_count'], 0)),
        ));

        if ($outbox) {
            $output_result .= '<br><span class="form-text">' . lang(array(
                'string' => 'In the SMS queue: {var:1} sent, {var:2} waiting, {var:3} failed',
                'vars'   => array(
                    pg_format_number($outbox['sent'] ?? 0, 0),
                    pg_format_number(($outbox['queued'] ?? 0) + ($outbox['sending'] ?? 0), 0),
                    pg_format_number(($outbox['failed'] ?? 0) + ($outbox['uncertain'] ?? 0), 0),
                ),
            )) . '</span>';
        }
    }

    $updated_username = ($campaign['updated_username'] != '') ? $campaign['updated_username'] : '[' . lang('Unknown') . ']';

    $output_rows .=
        '<tr>
            <td class="select-all align-middle text-start"><input class="check" type="checkbox" name="sms_campaigns[]" value="' . (int) $campaign['id'] . '" aria-label="' . h(lang(array('string' => 'Select {var:1}', 'vars' => array($name)))) . '" /></td>
            <td class="align-middle text-start"><a class="m-1 btn-data-control btn btn-outline theme-primary border-2" title="' . h(lang('Edit')) . '" aria-label="' . h(lang(array('string' => 'Edit {var:1}', 'vars' => array($name)))) . '" href="edit_sms_campaign.php?id=' . (int) $campaign['id'] . '"><i class="bi bi-pencil" aria-hidden="true"></i></a></td>
            <td class="align-middle">' . h($name) . '</td>
            <td class="align-middle">' . h($text) . '</td>
            <td class="align-middle">' . h($statuses[$campaign['status']] ?? $campaign['status']) . '</td>
            <td class="align-middle">' . $when . '</td>
            <td class="align-middle">' . $output_result . '</td>
            <td class="align-middle">' . get_relative_time(array('timestamp' => (int) $campaign['updated_at'])) . ' ' . lang(array('string' => 'by {var:1}', 'vars' => array(h($updated_username)))) . '</td>
        </tr>';
}

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
            )) . '
            <div class="card my-7">
                <div class="card-body p-0 position-relative">
                    <form name="form" action="view_sms_campaigns.php" method="post" class="disable_shortcut">
                        ' . get_token_field() . '
                        <table class="chart table-hover table" style="width:100%;display:none">
                            <thead>
                                <tr>
                                    <th class="noVis">
                                        <div class="form-field">
                                            <div class="switch"><input title="' . h(lang('Select/Deselect All')) . '" type="checkbox" id="select_all"></div>
                                        </div>
                                    </th>
                                    <th class="noVis">' . lang('Action') . '</th>
                                    <th>' . lang('Name') . '</th>
                                    <th>' . lang('Text') . '</th>
                                    <th>' . lang('Status') . '</th>
                                    <th>' . lang('Send Time') . '</th>
                                    <th>' . lang('Result') . '</th>
                                    <th>' . lang('Last Modified') . '</th>
                                </tr>
                            </thead>
                            <tbody>' . $output_rows . '</tbody>
                        </table>
                        <nav class="buttons navigation text-center position-sticky" style="bottom:.5rem;" aria-label="' . h(lang('data edit buttons')) . '">
                            <div class="container">
                                <div class="btn-group btn-group-sm flex-wrap justify-content-center mb-0 enable-on-selected">
                                    <button type="button" value="Delete Selected" class="btn mb-1 mt-1 btn-solid theme-danger disabled" data-confirm-content="' . h(lang(array('string' => 'WARNING: Selected {var:1} will be permanently deleted.', 'vars' => array(lang('campaigns'))))) . '"><span class="material-icons me-3" aria-hidden="true">delete</span>' . lang('Delete Selected') . '</button>
                                </div>
                            </div>
                        </nav>
                    </form>
                </div>
            </div>
        </div>
    </div>
</main>
' .
output_footer();

$liveform->remove_form();
