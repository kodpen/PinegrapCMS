<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Create and edit an SMS campaign: its text, the contact groups it goes to
 * and when it is sent (kept as a draft, now, or at a time). A queued or
 * cancelled campaign is shown read-only; a scheduled one can be cancelled.
 * The general job queues it (includes/fn/sms_campaigns.php). The cards are
 * the e-mail campaign form's (includes/forms/email_campaign_form.php).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/sms_campaigns.php');
require_once(dirname(__FILE__) . '/includes/forms/email_campaign_form.php');

$user = validate_user();
validate_email_access($user);

if (!pg_sms_campaigns_ready()) {
    output_error(lang('The SMS campaigns are not available yet; run the upgrade.'));
}

pg_request_single_values(array('id'));

$id = is_scalar($_REQUEST['id'] ?? null) ? (int) $_REQUEST['id'] : 0;

$campaign = array(
    'id' => 0, 'name' => '', 'body' => '', 'status' => 'draft', 'start_at' => 0,
    'queued_count' => 0, 'refused_count' => 0, 'queued_at' => 0, 'created_by' => $user['id'],
);
$choices = array();

if ($id) {
    $result = db_q("SELECT * FROM sms_campaigns WHERE id = ?", array((string) $id)) or output_error('Query failed.');
    $campaign = mysqli_fetch_assoc($result);

    if (!$campaign) {
        output_error(lang('The SMS campaign could not be found.') . ' <a href="view_sms_campaigns.php">' . lang('Go back') . '</a>.');
    }

    if (($user['role'] == 3) && ((int) $campaign['created_by'] !== (int) $user['id'])) {
        log_activity(lang('access denied to edit an SMS campaign because the user did not create it'), $_SESSION['sessionusername']);
        output_error(lang('You do not have access to this SMS campaign.'));
    }

    $result = db_q("SELECT contact_group_id, type FROM contact_groups_sms_campaigns_xref WHERE sms_campaign_id = ?", array((string) $id)) or output_error('Query failed.');

    while ($row = mysqli_fetch_assoc($result)) {
        $choices[(int) $row['contact_group_id']] = $row['type'];
    }
}

$readonly = !in_array($campaign['status'], array('draft', 'ready'), true);

// The room the text has: the SMS layer appends the sender identity and the
// type label before it applies its length limit.
$body_limit = pg_sms_campaign_body_limit(pg_sms_marketing_settings());
$groups = pg_sms_campaign_groups($user);
$errors = array();
$send = ($campaign['status'] === 'ready') ? 'at' : 'draft';
$start_text = ((int) $campaign['start_at'] > 0) ? prepare_form_data_for_output(date('Y-m-d H:i:s', (int) $campaign['start_at']), 'date and time') : '';

if ($_POST && !$readonly) {
    validate_token_field();

    if (isset($_POST['submit_cancel']) && $id && ($campaign['status'] === 'ready')) {
        $cancelled = db_q("UPDATE sms_campaigns SET status = 'cancelled', updated_at = ?, updated_by = ? WHERE id = ? AND status = 'ready'",
            array((string) time(), (string) $user['id'], (string) $id));

        if ($cancelled === false) {
            output_error('Query failed.');
        }

        // No row changed when the job claimed the campaign after it was read:
        // it is no longer ready, so nothing was cancelled to log.
        if (mysqli_affected_rows(db::$con) === 1) {
            log_activity(lang(array('string' => 'SMS campaign cancelled: {var:1}', 'vars' => array($campaign['name']))), $_SESSION['sessionusername']);
        }

        header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/view_sms_campaigns.php');
        exit;
    }

    // The time as the date and time picker writes it, read in the site's
    // time zone; null when it is not a date and time.
    $start_text = trim((string) ($_POST['start_at'] ?? ''));
    $parsed = ($start_text === '') ? '' : prepare_form_data_for_input($start_text, 'date and time');
    $start_at = preg_match('/^(\d{4})-(\d{2})-(\d{2}) (\d{2}):(\d{2})$/', $parsed, $m) && checkdate((int) $m[2], (int) $m[3], (int) $m[1])
        && ((int) $m[4] < 24) && ((int) $m[5] < 60) ? strtotime($parsed . ':00') : null;

    $decision = pg_sms_campaign_decide($_POST, array_keys($groups), time(), ($start_at === false) ? null : $start_at, $body_limit);
    $errors = $decision['errors'];

    if (!$errors) {
        $saved = pg_sms_campaign_save($id, $decision['values'], $user['id']);

        if (!$saved) {
            output_error('Query failed.');
        }

        log_activity(lang(array('string' => 'SMS campaign saved: {var:1}', 'vars' => array($decision['values']['name']))), $_SESSION['sessionusername']);

        $liveform = new liveform('view_sms_campaigns');
        $liveform->add_notice(($decision['values']['status'] === 'ready')
            ? lang('The SMS campaign was scheduled. The scheduled job queues it at its time.')
            : lang('The SMS campaign was saved as a draft.'));

        header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/view_sms_campaigns.php');
        exit;
    }

    // Shown again with what was posted.
    $campaign['name'] = (string) ($_POST['name'] ?? '');
    $campaign['body'] = (string) ($_POST['body'] ?? '');
    $send = (string) ($_POST['send'] ?? 'draft');
    $choices = array();

    foreach (array_keys($groups) as $group_id) {
        $choices[$group_id] = (string) ($_POST['contact_group_' . $group_id] ?? 'ignored');
    }
}

$statuses = pg_sms_campaign_statuses();

// The text card.
if ($readonly) {
    $output_message =
        '<div class="col-12 md:col-6 my-3"><div class="form-label">' . lang('Name') . '</div><p>' . h($campaign['name']) . '</p></div>
        <div class="col-12 my-3"><div class="form-label">' . lang('Text') . '</div><p style="white-space: pre-wrap">' . h($campaign['body']) . '</p></div>';
} else {
    $output_message =
        '<div class="col-12 md:col-6 my-3">
            <label class="form-label" for="name">' . lang('Name') . '</label>
            <input type="text" class="form-control" id="name" name="name" maxlength="100" value="' . h($campaign['name']) . '">
            <div class="form-text">' . lang('Only shown in the panel.') . '</div>
        </div>
        <div class="col-12 my-3">
            <label class="form-label" for="body">' . lang('Text') . '</label>
            <textarea class="form-control" id="body" name="body" rows="5" maxlength="' . (int) $body_limit . '" data-pg-sms-limit="' . (int) $body_limit . '" aria-describedby="body_help body_count">' . h($campaign['body']) . '</textarea>
            <div class="form-text" id="body_help">' . lang('The sender identity and the type label from Settings, Communication, SMS Messages are added below the text of every message.') . '</div>
            <div class="form-text" id="body_count" aria-live="polite" data-pg-sms-template="' . h(lang('{var:1} characters left')) . '"></div>
        </div>
        <script>
            (function () {
                var box = document.getElementById(\'body\');
                var count = document.getElementById(\'body_count\');
                var limit = parseInt(box.getAttribute(\'data-pg-sms-limit\'), 10);

                // Characters as the server counts them: a line break is one.
                function update() {
                    var used = Array.from(box.value.replace(/\r\n?/g, \'\n\').trim()).length;
                    count.textContent = count.getAttribute(\'data-pg-sms-template\').replace(\'{var:1}\', String(limit - used));
                    count.classList.toggle(\'text-danger\', used > limit);
                }

                box.addEventListener(\'input\', update);
                update();
            })();
        </script>';
}

// The contact group card: the e-mail campaign's Ignore, Include and Exclude
// table; a read-only campaign lists its groups.
$output_group_rows = '';

foreach ($groups as $group_id => $group_name) {
    $choice = $choices[$group_id] ?? 'ignored';

    if ($readonly) {
        if ($choice !== 'ignored') {
            $output_group_rows .= '<tr><td>' . h($group_name) . '</td><td>' . (($choice === 'included') ? lang('Include') : lang('Exclude')) . '</td></tr>';
        }

        continue;
    }

    $output_group_rows .= '<tr><td id="contact_group_name_' . $group_id . '">' . h($group_name) . '</td>';

    foreach (array('ignored' => 'contact_group_ignore', 'included' => 'contact_group_include', 'excluded' => 'contact_group_exclude') as $value => $heading) {
        $output_group_rows .=
            '<td class="text-center"><div class="form-field d-inline-grid"><input class="radio" type="radio" name="contact_group_' . $group_id . '" value="' . $value . '" aria-labelledby="contact_group_name_' . $group_id . ' ' . $heading . '"' . (($choice === $value) ? ' checked="checked"' : '') . ' /></div></td>';
    }

    $output_group_rows .= '</tr>';
}

if ($output_group_rows === '') {
    $output_groups = '<div class="alert theme-secondary"><p class="mb-0">' . lang('No contact group.') . '</p></div>';
} elseif ($readonly) {
    $output_groups = '<table class="table"><tbody>' . $output_group_rows . '</tbody></table>';
} else {
    $output_groups =
        '<div class="alert theme-primary"><p class="mb-0">' . lang('Send the message to the contacts with a mobile number in my selected contact groups.') . '</p></div>
        <table class="table">
            <thead><tr>
                <th><span class="visually-hidden">' . lang('Contact Group') . '</span></th>
                <th id="contact_group_ignore" class="text-center">' . lang('Ignore') . '</th>
                <th id="contact_group_include" class="text-center">' . lang('Include') . '</th>
                <th id="contact_group_exclude" class="text-center">' . lang('Exclude') . '</th>
            </tr></thead>
            <tbody>' . $output_group_rows . '</tbody>
        </table>';
}

// The delivery card.
if ($readonly) {
    $output_delivery =
        '<div class="col-12 md:col-6 my-3"><div class="form-label">' . lang('Status') . '</div><p>' . h($statuses[$campaign['status']] ?? $campaign['status']) . '</p></div>';

    if ($campaign['status'] === 'queued') {
        $outbox = pg_sms_campaign_outbox_counts($campaign['id']);

        $output_delivery .=
            '<div class="col-12 md:col-6 my-3"><div class="form-label">' . lang('Result') . '</div><p>'
            . lang(array('string' => '{var:1} queued, {var:2} refused', 'vars' => array(pg_format_number($campaign['queued_count'], 0), pg_format_number($campaign['refused_count'], 0))))
            . ($outbox ? '<br>' . lang(array('string' => 'In the SMS queue: {var:1} sent, {var:2} waiting, {var:3} failed', 'vars' => array(
                pg_format_number($outbox['sent'] ?? 0, 0),
                pg_format_number(($outbox['queued'] ?? 0) + ($outbox['sending'] ?? 0), 0),
                pg_format_number(($outbox['failed'] ?? 0) + ($outbox['uncertain'] ?? 0), 0)))) : '')
            . '</p></div>';
    }
} else {
    $output_send_options = '';

    foreach (array('draft' => lang('Keep as a draft'), 'now' => lang('Send now'), 'at' => lang('Send at this Date & Time')) as $value => $label) {
        $output_send_options .=
            '<div class="form-field">
                <input class="radio" type="radio" id="send_' . $value . '" name="send" value="' . $value . '"' . (($send === $value) ? ' checked="checked"' : '') . ' />
                <label for="send_' . $value . '">' . $label . '</label>
            </div>';
    }

    $output_delivery =
        '<div class="col-12 md:col-6 my-3">
            <fieldset><legend class="form-label fs-md">' . lang('When') . '</legend>' . $output_send_options . '</fieldset>
        </div>
        <div class="col-12 md:col-6 lg:col-4 my-3">
            <label class="form-label" for="start_at">' . lang('Date & Time') . '</label>
            <input value="' . h($start_text) . '" type="text" maxlength="19" class="form-control" id="start_at" name="start_at" data-pg-datetime />
            <div class="form-text">' . lang('Used with Send at this Date & Time.') . '</div>
            ' . get_date_time_picker_format() . pg_fields_script() . '
        </div>';
}

$output_errors = '';

if ($errors) {
    $output_errors = '<div class="alert theme-danger" role="alert"><p class="mb-0">' . implode('<br>', array_map('h', $errors)) . '</p></div>';
}

$output_buttons = '';

if (!$readonly) {
    $output_buttons =
        '<nav class="buttons navigation text-center position-sticky mb-7" style="bottom:.5rem;" aria-label="' . h(lang('data edit buttons')) . '">
            <div class="container">
                <div class="btn-group flex-wrap justify-content-center">
                    <button type="submit" name="submit_save" value="Save" class="btn my-1 btn-solid theme-success"><span class="bi bi-check-circle me-3" aria-hidden="true"></span>' . lang('Save') . '</button>
                    ' . (($campaign['status'] === 'ready') ? '<button type="submit" name="submit_cancel" value="Cancel" class="btn my-1 btn-solid theme-danger"><span class="bi bi-x-circle me-3" aria-hidden="true"></span>' . lang('Cancel Campaign') . '</button>' : '') . '
                </div>
            </div>
        </nav>';
}

$heading = $id ? lang('Edit SMS Campaign') : lang('Create SMS Campaign');

echo
pg_page_shell(
    array(
        'title'               => $heading,
        'extra classes'       => 'campaign',
        'icon'                => 'campaign',
        'heading'             => $heading,
        'heading_description' => lang('A text message to the contacts of selected contact groups. Numbers without SMS consent, refused by İYS or without a sender identity are refused by the SMS checks.'),
        'cancel'              => array('enable' => 'true', 'url' => 'view_sms_campaigns.php'),
        'breadcrumb'          => array(
            array('label' => lang('My SMS Campaigns'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_sms_campaigns.php'),
            array('label' => $heading),
        ),
    )
) . '
<main id="content" class="container-fluid">
    <div class="row">
        <div class="col-12">
            ' . $output_errors . '
            ' . ($readonly ? '' : '<form name="form" action="edit_sms_campaign.php' . ($id ? '?id=' . $id : '') . '" method="post">' . get_token_field()) . '
                <div class="row">
                    ' . pg_email_campaign_card(lang('SMS Message'), $output_message) . '
                    ' . pg_email_campaign_card(lang('SMS Message To My Contact Groups'), '<div class="col-12 my-3">' . $output_groups . '</div>') . '
                    ' . pg_email_campaign_card(lang('SMS Message Delivery'), $output_delivery) . '
                </div>
                ' . $output_buttons . '
            ' . ($readonly ? '' : '</form>') . '
        </div>
    </div>
</main>' .
output_footer();
