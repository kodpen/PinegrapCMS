<?php
/**
 * PineGrap - Enterprise Website Platform
 *
 * Originally developed as LiveSite by Camelback Web Architects.
 * Since 2017, maintained and evolved by Erdal Güral (Kodpen) under the name PineGrap.
 * The final LiveSite update (2019) has been integrated into PineGrap.
 * LiveSite remains available as a separate downloadable legacy version.
 *
 * @author      Camelback Web Architects
 *              Erdal Güral (Kodpen)
 * @link        https://livesite.com
 *              https://kodpen.com
 * @copyright   2001–2019 Camelback Consulting, Inc.
 *              2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');
require_once(dirname(__FILE__) . '/includes/forms/email_campaign_form.php');
require_once(dirname(__FILE__) . '/includes/fn/email_campaign_send.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/email_campaign_create.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/campaign_content.php');
$user = validate_user();
validate_email_access($user);

// Stop early when the screen is opened without an existing record (stale
// link, hand-typed URL, row deleted meanwhile): a "not found" screen instead
// of a half-empty form and PHP notices from the unchecked row below.
$id = is_scalar($_REQUEST['id'] ?? null) ? (int) $_REQUEST['id'] : 0;
if (($id < 1) || ((int) db_value("SELECT COUNT(*) FROM email_campaigns WHERE id = '" . $id . "'") < 1)) {
    output_error(lang('Record not found.') . ' <a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.', 404);
}

// if user has a user role
if ($user['role'] == 3) {
    // get user that created this e-mail campaign, in order to check if user has access to this e-mail campaign
    $query =
        "SELECT created_user_id
        FROM email_campaigns
        WHERE id = ?";
    $result = db_q($query, array((string) $_REQUEST['id'])) or output_error('Query failed.');
    $row = mysqli_fetch_assoc($result);
    $created_user_id = $row['created_user_id'];
    
    // if user did not create this e-mail campaign, then output error
    if ($created_user_id != $user['id']) {
        log_activity(lang('access denied to edit e-mail campaign because user is not the creator of the e-mail campaign'), $_SESSION['sessionusername']);
        output_error(lang('Access denied.'));
    }
}

// if form has not been submitted yet
if (!$_POST) {
    // get e-mail campaign information
    $query = 
        "SELECT 
            email_campaigns.type,
            email_campaigns.subject,
            email_campaigns.format,
            email_campaigns.body,
            email_campaigns.status,
            email_campaigns.email_campaign_profile_id,
            email_campaign_profiles.name AS email_campaign_profile_name,
            email_campaigns.order_id,
            orders.reference_code AS order_reference_code,
            email_campaigns.from_name, 
            email_campaigns.from_email_address, 
            email_campaigns.reply_email_address, 
            email_campaigns.bcc_email_address, 
            email_campaigns.start_time,
            email_campaigns.purpose,
            email_campaigns.created_timestamp, 
            email_campaigns.page_id,
            email_campaigns.last_modified_timestamp,
            user.user_username
        FROM email_campaigns
        LEFT JOIN email_campaign_profiles ON email_campaigns.email_campaign_profile_id = email_campaign_profiles.id
        LEFT JOIN orders ON email_campaigns.order_id = orders.id
        LEFT JOIN user ON user.user_id = email_campaigns.created_user_id
        WHERE email_campaigns.id = ?";
    $result = db_q($query, array((string) $_GET['id'])) or output_error('Query failed.');
    $row = mysqli_fetch_assoc($result);
    
    $type = $row['type'];
    $subject = $row['subject'];
    $format = $row['format'];
    $body = $row['body'];
    $status = $row['status'];
    $email_campaign_profile_id = $row['email_campaign_profile_id'];
    $email_campaign_profile_name = $row['email_campaign_profile_name'];
    $order_id = $row['order_id'];
    $order_reference_code = $row['order_reference_code'];
    $creator_username = $row['user_username'];
    $from_name = $row['from_name'];
    $from_email_address = $row['from_email_address'];
    $reply_email_address = $row['reply_email_address'];
    $bcc_email_address = $row['bcc_email_address'];
    $start_time = $row['start_time'];
    $purpose = $row['purpose'];
    $created_timestamp = $row['created_timestamp'];
    $page_id = $row['page_id'];
    $last_modified_timestamp = (int) $row['last_modified_timestamp'];
    
    // if the creator username is blank then set to placeholder
    if ($creator_username == '') {
        $creator_username = '[' . lang('Unknown') . ']';
    }
    
    $output_button_bar = '';
    
    // if e-mail campaign job is off and e-mail campaign status is ready, then prepare to output button bar with send campaign button
    if ((email_campaign_job_enabled() == false) && ($status == 'ready')) {
        $output_button_bar = pg_screen_toolbar(array(
            'secondary' => '<a class="btn btn-sm btn-text theme-secondary" href="send_email_campaign.php?id=' . h($_GET['id']) . get_token_query_string_field() . '" onclick="window.open(\'send_email_campaign.php?id=' . h($_GET['id']) . get_token_query_string_field() . '\', \'\', \'width=450, height=350, resizable=1, scrollbars=0\'); return false;"><i class="bi bi-send me-1" aria-hidden="true"></i>' . lang('Send Campaign') . '</a>',
        ));
    }
    
    // A completed campaign is shown with its values as text.
    $readonly = ($status == 'complete');

    // if the e-mail campaign is not complete, then prepare information for editing
    if (!$readonly) {
        $output_heading = lang('Edit Campaign');
        $output_subheading = lang('Update this e-mail campaign\'s properties.');
        
        $output_form_start =
            '<form name="form" action="edit_email_campaign.php" method="post">
                ' . get_token_field() . '
                <input type="hidden" name="send_to" value="' . h(($_GET['send_to'] ?? '')) . '" />
                <input type="hidden" name="id" value="' . h($_GET['id']) . '">';
        
        $ready_status = '';
        $paused_status = '';
        $cancelled_status = '';
        
        switch ($status) {
            case 'ready':
                $ready_status = ' selected="selected"';
                break;

            case 'paused':
                $paused_status = ' selected="selected"';
                break;

            case 'cancelled':
                $cancelled_status = ' selected="selected"';
                break;
        }
        
        if (email_campaign_job_enabled()) {
            $ready_label = lang('Scheduled');
        } else {
            $ready_label = lang('Ready to Send');
        }
        
        $output_status_options = '<option value="ready"' . $ready_status . '>' . $ready_label . '</option>';
        
        $output_status_options .= '<option value="paused"' . $paused_status . '>' . lang('Paused') . '</option>';
        
        $output_status_options .= '<option value="cancelled"' . $cancelled_status . '>' . lang('Cancelled') . '</option>';
        
        $output_status = '<label class="form-label" for="status">' . lang('Status') . '</label>
            <select id="status" name="status" class="form-control">' .  $output_status_options . '</select>';
        
        $output_bcc_email_address = '<div class="row"><div class="col-12 md:col-auto"><input value="' . h($bcc_email_address) . '" type="text" class="form-control text-end" id="bcc_email_address" aria-label="' . h(lang('BCC E-mail Address')) . '" name="bcc_email_address" maxlength="100" inputmode="email" data-inputmask-alias="email"></div></div>';

        $output_buttons = '<button type="submit" id="save_button" name="submit_save" value="Save" class="btn my-1  btn-solid theme-success "><span class="bi bi-save me-3" aria-hidden="true"></span><span class="pg-btn-label">' . lang(array('string'=>'Save') ) . '</span></button>';
        $output_form_end = '</form>';
        
    // else the e-mail campaign is complete, so prepare information for viewing
    } else {
        $output_heading = lang('View Campaign');
        $output_subheading = lang('View this e-mail campaign\'s information.');
        $output_form_start = '';
        $output_status = '<div class="form-label">' . lang('Status') . '</div>
            <p class="mb-0">' . h(get_email_campaign_status_name($status)) . '</p>';
        $output_bcc_email_address = '<p>' . h($bcc_email_address) . '</p>';

        $output_buttons = '';
        $output_form_end = '';
    }

    $output_auto_campaign = '';
    
    if ($email_campaign_profile_id) {

        $output_order = '';

        if ($order_reference_code) {
            $output_order_reference = h($order_reference_code);

            // view_order.php asks for commerce; a campaign manager without it
            // reads the reference code without a link to "Access denied".
            if (defined('USER_MANAGE_ECOMMERCE') && USER_MANAGE_ECOMMERCE) {
                $output_order_reference = '<a href="view_order.php?id=' . (int) $order_id . '">' . $output_order_reference . '</a>';
            }

            $output_order =
                '<div class="col-12 my-3">
                    <div class="form-label">' . lang('Order') . '</div>
                    <p class="mb-0">' . $output_order_reference . '</p>
                </div>';
        }

        $output_auto_campaign =
            '<div class="col-12 my-3">
                <div class="form-label">' . lang('Profile') . '</div>
                <p class="mb-0">' . (($email_campaign_profile_name !== null)
                    ? '<a href="edit_email_campaign_profile.php?id=' . $email_campaign_profile_id . '">' . h($email_campaign_profile_name) . '</a>'
                    : h(lang('The campaign profile has been deleted.'))) . '</p>
            </div>
            ' . $output_order;
            
    }
    
    // get total number of recipients
    $query = "SELECT COUNT(*) FROM email_recipients WHERE email_campaign_id = ?";
    $result = db_q($query, array((string) $_GET['id'])) or output_error('Query failed.');
    $row = mysqli_fetch_row($result);
    $number_of_email_recipients = $row[0];
    
    // get total number of complete recipients
    $query = "SELECT COUNT(*) FROM email_recipients WHERE (email_campaign_id = ?) AND (complete = '1')";
    $result = db_q($query, array((string) $_GET['id'])) or output_error('Query failed.');
    $row = mysqli_fetch_row($result);
    $number_of_completed_email_recipients = $row[0];
    
    if ($number_of_email_recipients > 0) {
        $output_progress_percentage = pg_format_number($number_of_completed_email_recipients / $number_of_email_recipients * 100, 0);
    } else {
        $output_progress_percentage = '100';
    }

    // Recipients the job gave up on after its last attempt. They count as
    // complete above, so the campaign can finish; this says how many of them
    // never received the message.
    $output_failed_email_recipients = '';

    if (pg_email_retry_ready()) {
        $number_of_failed_email_recipients = (int) db_value(
            "SELECT COUNT(*)
            FROM email_recipients
            WHERE
                (email_campaign_id = '" . e($_GET['id']) . "')
                AND (failed = '1')");

        if ($number_of_failed_email_recipients > 0) {
            $output_failed_email_recipients = '<p class="small fg-danger mb-0">' . lang(array('string' => '{var:1} could not be delivered', 'vars' => array(pg_format_number($number_of_failed_email_recipients, 0)))) . '</p>';
        }

        // The stored reason of the deliveries still waiting or given up, so
        // the sender learns what to fix (often the mail server settings).
        $delivery_problem = pg_email_campaign_delivery_problem($_GET['id']);
        $problem_line = pg_email_campaign_problem_line($delivery_problem['error'], $delivery_problem['count']);

        if ($problem_line !== '') {
            $output_failed_email_recipients .= '<p class="small fg-danger mb-0 text-break">' . h($problem_line) . '</p>';

            if ($user['role'] <= 2) {
                $output_failed_email_recipients .= '<p class="small mb-0"><a href="smtp_settings.php">' . lang('SMTP Settings') . '</a></p>';
            }
        }
    }

    include_once('liveform.class.php');
    $edit_liveform = new liveform('edit_email_campaign');

    // The content card: the stored kind, the stored body's preview, and for
    // a manual campaign drawn from a page whether the page changed since.
    $content = array(
        'mode'    => $readonly ? 'read' : 'edit',
        'kind'    => pg_campaign_content_kind($format, $page_id),
        'subject' => $subject,
        'preview' => ($format == 'html') ? 'view_email_campaign.php?id=' . (int) $_GET['id'] : '',
    );

    if ($content['kind'] === 'text') {
        $content['text'] = $body;
    } elseif ($content['kind'] === 'html') {
        $content['html'] = pg_campaign_content_without_footer($body);
    } else {
        $design_result = db_q("SELECT page_id, page_name, page_folder, page_style, page_timestamp FROM page WHERE page_id = ?", array((string) (int) $page_id)) or output_error('Query failed.');
        $design = mysqli_fetch_assoc($design_result);

        if ($design) {
            $content['page_name'] = $design['page_name'];
            $content['edit_url'] = check_edit_access($design['page_folder']) ? pg_page_edit_url($design) : '';
            $content['changed'] = ($type == 'manual') && check_view_access($design['page_folder']) && pg_campaign_content_design_changed($design['page_timestamp'], $last_modified_timestamp);
        } else {
            $content['page_name'] = '[' . lang('Deleted') . ']';
        }
    }

    // Prepare to area differently based on the type of campaign.
    switch ($type) {
        case 'manual':
            // get all contact groups that are associated with this e-mail campaign
            $query =
                "SELECT
                    contact_groups.name,
                    contact_groups_email_campaigns_xref.type
                FROM contact_groups_email_campaigns_xref
                LEFT JOIN contact_groups ON contact_groups_email_campaigns_xref.contact_group_id = contact_groups.id
                WHERE contact_groups_email_campaigns_xref.email_campaign_id = ?
                ORDER BY contact_groups.name ASC";
            $result = db_q($query, array((string) $_GET['id'])) or output_error('Query failed.');
            
            $output_included_contact_groups = '';
            $output_excluded_contact_groups = '';
            
            // loop through all contact groups, in order to prepare list of contact groups
            while ($row = mysqli_fetch_assoc($result)) {
                $contact_group_name = $row['name'];
                $contact_group_type = $row['type'];
                
                // if contact group is included
                if ($contact_group_type == 'included') {
                    if ($output_included_contact_groups) {
                        $output_included_contact_groups .= '<br />';
                    }
                    
                    $output_included_contact_groups .= h($contact_group_name);
                    
                // else contact group is excluded
                } else {
                    if ($output_excluded_contact_groups) {
                        $output_excluded_contact_groups .= '<br />';
                    }
                    
                    $output_excluded_contact_groups .= h($contact_group_name);
                }
            }
            
            // if there are no included contact groups, then prepare notice
            if ($output_included_contact_groups == '') {
                $output_included_contact_groups = '[' . lang('None') . ']';
            }
            
            // if there are no excluded contact groups, then prepare notice
            if ($output_excluded_contact_groups == '') {
                $output_excluded_contact_groups = '[' . lang('None') . ']';
            }
            
            // get entered e-mail address
            $query = "SELECT email_address FROM email_recipients WHERE (email_campaign_id = ?) AND (contact_id = '0')";
            $result = db_q($query, array((string) $_GET['id'])) or output_error('Query failed.');
            
            // if an entered e-mail address was found, then prepare to output it
            if (mysqli_num_rows($result) > 0) {
                $row = mysqli_fetch_assoc($result);
                $output_entered_email_address = h($row['email_address']);
                
            // else an entered e-mail address was not found, so prepare to output notice
            } else {
                $output_entered_email_address = '[' . lang('None') . ']';
            }

            $output_to_rows = pg_email_campaign_card(
                lang('E-Mail Message To My Contact Groups'),
                '<div class="col-12 my-3">
                                    <div class="alert theme-primary">
                                        <p>' . lang('Send message to all Subscribers in my selected Contact Groups') . '</p>
                                        <p>' . $output_included_contact_groups . '</p>
                                    </div>

                                    <div class="alert theme-warning">
                                        <p>' . lang('But don\'t send message to any Subscribers that also exist in any of the following Contact Groups') . '</p>
                                        <p>' . $output_excluded_contact_groups . '</p>
                                    </div>

                                    <div class="alert theme-secondary ">
                                        <p>' . lang('Also send message to the following e-mail address') . '</p>
                                        <p>' . $output_entered_email_address . '</p>
                                    </div>
                </div>');

            break;
        
        case 'automatic':
            $to_email_address = db_value("SELECT email_address FROM email_recipients WHERE email_campaign_id = '" . escape($_GET['id']) . "'");

            $output_to_rows = pg_email_campaign_card(
                lang('E-Mail Message To'),
                '<div class="col-12 my-3">
                                    <div class="alert theme-primary">
                                        <p>' . lang('To') . '</p>
                                        <p>' . h($to_email_address) . '</p>
                                    </div>
                                    <div class="alert theme-secondary ">
                                        <p>' . lang('BCC E-mail Address') . '</p>
                                        <div>' . $output_bcc_email_address . '</div>
                                    </div>
                </div>');

            break;
    }
    
    print
    pg_page_shell(
        array(
            'title'=> $output_heading,
            'extra classes'=>'campaign',
            'icon'=>'campaign', 
            'heading'=> $output_heading,
            'heading_description' => $output_subheading,
            'cancel' => array('enable' => 'true', 'url' => pg_send_to_url(OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_email_campaigns.php')),
            'breadcrumb' => array(array('label' => lang('My Campaigns'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_email_campaigns.php'), array('label' => $output_heading)),
        )
    ) . '
<main id="content" class="container-fluid">
                ' . $edit_liveform->output_notices() . '
                ' . $output_button_bar . '
                ' . $output_form_start . '
                ' . pg_email_campaign_layout(
                    pg_campaign_content_card($content)
 . $output_to_rows . pg_email_campaign_from_card(array(
                        'from_name' => $from_name,
                        'from_email_address' => $from_email_address,
                        'reply_email_address' => $reply_email_address,
                    ), $readonly),
                    pg_email_campaign_card(
                        lang('Summary'),
                        '<div class="col-12 my-3">
                            ' . $output_status . '
                        </div>
                        <div class="col-12 my-3">
                            <div class="form-label">' . lang('Progress') . '</div>
                            <div class="progress mb-2" role="progressbar" aria-label="' . h(lang('Progress')) . '" aria-valuenow="' . h($output_progress_percentage) . '" aria-valuemin="0" aria-valuemax="100">
                                <div class="progress-bar" style="width: ' . h($output_progress_percentage) . '%"></div>
                            </div>
                            <p class="mb-0">' . lang(array('string' => '{var:1}%', 'vars' => array($output_progress_percentage))) . ' (' . pg_format_number($number_of_completed_email_recipients, 0) . lang(' of ') . pg_format_number($number_of_email_recipients, 0) . ' ' . lang('subscribers') . ')</p>
                            ' . $output_failed_email_recipients . '
                        </div>
                        ' . $output_auto_campaign . '
                        <div class="col-12 my-3">
                            <p class="small fg-secondary mb-0">' . lang(array('string'=>'Created {var:1} by {var:2}.','vars'=>array( get_relative_time(array('timestamp' => $created_timestamp)),h($creator_username) ))) . '</p>
                        </div>'
                    ) . pg_email_campaign_schedule_card($start_time, $readonly) . pg_email_campaign_purpose_card($purpose, $readonly),
                    $output_buttons
                ) . '
                ' . $output_form_end . '
</main>' .
        output_footer();

    $edit_liveform->remove_form();
    
// else form has been submitted
} else {
    validate_token_field();

    // The sender name, body and return address are text: one posted as an
    // array (from_name[]=x) ended in a fatal error, a missing send_to in
    // warnings. The sender name goes into the From header, so it is trimmed
    // like the subject and the addresses.
    $_POST['from_name'] = trim(pg_email_campaign_posted_text($_POST, 'from_name'));
    $_POST['body'] = pg_email_campaign_posted_text($_POST, 'body');
    $_POST['send_to'] = pg_email_campaign_posted_text($_POST, 'send_to');
    
    // check if the e-mail campaign is complete
    $query =
        "SELECT
            type,
            status,
            subject,
            format,
            page_id
        FROM email_campaigns
        WHERE id = ?";
    $result = db_q($query, array((string) ($_POST['id'] ?? ''))) or output_error('Query failed.');
    $row = mysqli_fetch_assoc($result);
    
    $type = $row['type'];
    $status = $row['status'];
    $subject = $row['subject'];
    $format = $row['format'];
    $body_kind = pg_campaign_content_kind($format, $row['page_id']);
    
    // if the e-mail campaign is complete, then output error
    if ($status == 'complete') {
        output_error(lang('You may not edit a completed campaign.') . ' <a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.');
    }

    // The status is one the form offers ('complete' is set only by sending),
    // and the subject and addresses are checked as on create. BCC is drawn
    // only for an automatic campaign.
    $new_status = pg_email_campaign_allowed_value('status', $_POST['status'] ?? null);
    $new_purpose = pg_email_campaign_allowed_value('purpose', $_POST['purpose'] ?? null);

    $checked_fields = array(
        'subject' => $_POST['subject'] ?? '',
        'from_email_address' => $_POST['from_email_address'] ?? '',
        'reply_email_address' => $_POST['reply_email_address'] ?? '',
    );

    if ($type == 'automatic') {
        $checked_fields['bcc_email_address'] = $_POST['bcc_email_address'] ?? '';
    }

    $field_errors = pg_email_campaign_field_errors($checked_fields);

    if ($new_status === null) {
        $field_errors['status'] = lang('Select a status.');
    }

    if ($new_purpose === null) {
        $field_errors['purpose'] = lang('Select a purpose.');
    }

    if ($field_errors) {
        output_error(pg_email_campaign_field_errors_html($field_errors));
    }

    $sql_body = "";
    $took_design = false;

    // if the format is plain text, then allow the body to be updated
    if ($format == 'plain_text') {
        $sql_body = "body = '" . escape($_POST['body']) . "',";

    // Pasted HTML is cleaned again, without the footer it was stored with,
    // and made ready as on create.
    } elseif ($body_kind === 'html') {
        $pasted_html = pg_mail_html_clean_outgoing(pg_campaign_content_without_footer(pg_email_campaign_posted_text($_POST, 'html_body')));

        if ($pasted_html === '') {
            output_error(lang('Paste the HTML of the e-mail: nothing that can be sent was left of it.') . ' <a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.');
        }

        $sql_body = "body = '" . escape(pg_email_campaign_prepare_html_body($pasted_html, trim((string) $_POST['subject']))) . "',";

    // "Take the design again": a manual campaign's page drawn anew, on the
    // same terms as on create (the user may view the page).
    } elseif (($body_kind === 'design') && ($type == 'manual') && !empty($_POST['take_design'])) {
        $design_result = db_q("SELECT page_folder FROM page WHERE page_id = ?", array((string) (int) $row['page_id'])) or output_error('Query failed.');
        $design = mysqli_fetch_assoc($design_result);

        if (!$design || !check_view_access($design['page_folder'])) {
            output_error(lang('You are not authorized to send that page for the body of the campaign.') . ' <a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.');
        }

        $sql_body = "body = '" . escape(pg_email_campaign_design_body($row['page_id'])) . "',";
        $took_design = true;
    }

    $sql_bcc_email_address = "";

    // If the campaign is an automatic campaign, then allow BCC email address to be updated.
    if ($type == 'automatic') {
        $sql_bcc_email_address = "bcc_email_address = '" . escape(trim((string) ($_POST['bcc_email_address'] ?? ''))) . "',";
    }
    
    // The send time field is drawn only while the campaign job is on; without
    // it the stored start time is kept.
    $sql_start_time = '';

    if (isset($_POST['start_time']) && is_string($_POST['start_time'])) {
        $sql_start_time = "start_time = '" . escape(prepare_form_data_for_input($_POST['start_time'], 'date and time')) . "',";
    }

    $query =
        "UPDATE email_campaigns
        SET
            status = '" . escape($new_status) . "',
            subject = '" . escape(trim((string) $_POST['subject'])) . "',
            " . $sql_body . "
            from_name = '" . escape($_POST['from_name']) . "',
            from_email_address = '" . escape(trim((string) $_POST['from_email_address'])) . "',
            reply_email_address = '" . escape(trim((string) ($_POST['reply_email_address'] ?? ''))) . "',
            " . $sql_bcc_email_address . "
            " . $sql_start_time . "
            purpose = '" . e($new_purpose) . "',
            last_modified_user_id = '" . $user['id'] . "',
            last_modified_timestamp = UNIX_TIMESTAMP()
        WHERE id = '" . escape($_POST['id'] ?? '') . "'";
    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
    
    log_activity(lang(array('string'=>'campaign (subject: {var:1}) was modified','vars'=>$subject)), $_SESSION['sessionusername']);

    include_once('liveform.class.php');

    // Both campaign states are one list now (view_email_campaigns.php).
    $liveform = new liveform('view_email_campaigns');

    // After "Take the design again" the screen stays open on the new body.
    if ($took_design) {
        $edit_liveform = new liveform('edit_email_campaign');
        $edit_liveform->add_notice(lang('The design was taken again; the e-mail below is what will be sent.'));
        header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/edit_email_campaign.php?id=' . (int) $_POST['id']);
        exit;
    }

    $liveform->add_notice(lang('The campaign has been saved.'));

    // If there is a send to set, then forward user to send to.
    if ($_POST['send_to'] != '') {
        header('Location: ' . URL_SCHEME . HOSTNAME . pg_safe_redirect_path($_POST['send_to']));
        
    // Otherwise there is not a send to set, so forward user to view e-mail campaigns screen.
    } else {
        header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/view_email_campaigns.php');
    }
}