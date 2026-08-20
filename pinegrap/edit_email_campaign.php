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
 *              2016–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');
$user = validate_user();
validate_email_access($user);

// if user has a user role
if ($user['role'] == 3) {
    // get user that created this e-mail campaign, in order to check if user has access to this e-mail campaign
    $query =
        "SELECT created_user_id
        FROM email_campaigns
        WHERE id = '" . escape($_REQUEST['id']) . "'";
    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
    $row = mysqli_fetch_assoc($result);
    $created_user_id = $row['created_user_id'];
    
    // if user did not create this e-mail campaign, then output error
    if ($created_user_id != $user['id']) {
        log_activity(lang('access denied to send e-mail campaign because user is not the creator of the e-mail campaign'), $_SESSION['sessionusername']);
        output_error_in_popup(lang('Access denied.'));
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
            user.user_username
        FROM email_campaigns
        LEFT JOIN email_campaign_profiles ON email_campaigns.email_campaign_profile_id = email_campaign_profiles.id
        LEFT JOIN orders ON email_campaigns.order_id = orders.id
        LEFT JOIN user ON user.user_id = email_campaigns.created_user_id
        WHERE email_campaigns.id = '" . escape($_GET['id']) . "'";
    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
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
    
    // if the creator username is blank then set to placeholder
    if ($creator_username == '') {
        $creator_username = '[' . lang('Unknown') . ']';
    }
    
    // if the start time is set to 0's, then set to blank
    if ($start_time == '0000-00-00 00:00:00') {
        $start_time = '';
    }
    
    $output_button_bar = '';
    
    // if e-mail campaign job is off and e-mail campaign status is ready, then prepare to output button bar with send campaign button
    if (((defined('EMAIL_CAMPAIGN_JOB') == false) || (EMAIL_CAMPAIGN_JOB == false)) && ($status == 'ready')) {
        $output_button_bar =
            '<nav id="button_bar" class="navigation " aria-label="Button Bar">
                <div class=" btn-group btn-group-sm flex-wrap">
                    ' . $output_product_form_designer_button . '
                    <a class="btn btn-link link-secondary py-0 mb-2 " data-loading-content="' . lang('Loading') . '" href="send_email_campaign.php?id=' . h($_GET['id']) . get_token_query_string_field() . '" onclick="window.open(\'send_email_campaign.php?id=' . h($_GET['id']) . get_token_query_string_field() . '\', \'\', \'width=450, height=350, resizable=1, scrollbars=0\'); return false;"><span class="material-icons me-1">send</span>' . lang('Send Campaign') . '</a>
                </div>
            </nav>';
    }
    
    // if the e-mail campaign is not complete, then prepare information for editing
    if ($status != 'complete') {
        $output_heading = lang('Edit Campaign');
        $output_subheading = lang('Update this e-mail campaign\'s properties.');
        
        $output_form_start =
            '<form name="form" action="edit_email_campaign.php" method="post">
                ' . get_token_field() . '
                <input type="hidden" name="send_to" value="' . h(($_GET['send_to'] ?? '')) . '" />
                <input type="hidden" name="id" value="' . h($_GET['id']) . '">';
        
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
        
        if (defined('EMAIL_CAMPAIGN_JOB') and EMAIL_CAMPAIGN_JOB === true) {
            $ready_label = lang('Scheduled');
        } else {
            $ready_label = lang('Ready to Send');
        }
        
        $output_status_options = '<option value="ready"' . $ready_status . '>' . $ready_label . '</option>';
        
        $output_status_options .= '<option value="paused"' . $paused_status . '>' . lang('Paused') . '</option>';
        
        $output_status_options .= '<option value="cancelled"' . $cancelled_status . '>' . lang('Cancelled') . '</option>';
        
        $output_status = '<select id="status" name="status" class="form-select">' .  $output_status_options . '</select>';
        
        $output_subject = '<input value="' . h($subject) . '" type="text" name="subject" placeholder="' . lang('Subject') . '" maxlength="255" id="subject" class="form-control add-header-content-updater" />';
        $output_bcc_email_address = '<div class="row"><div class="col-12 col-md-auto"><input value="' . h($bcc_email_address) . '" type="text" class="form-control text-end" id="bcc_email_address" name="bcc_email_address" maxlength="100" inputmode="email" data-inputmask-alias="email"></div></div>';
        $output_from_name = '<input value="' . h($from_name) . '" type="text" class="form-control" id="from_name" name="from_name" />';
        $output_from_email_address = '<input value="' . h($from_email_address) . '" type="text" class="form-control text-end" id="from_email_address" name="from_email_address" maxlength="100" inputmode="email" data-inputmask-alias="email"/>';
        $output_reply_email_address = '<input value="' . h($reply_email_address) . '" type="text" class="form-control text-end" id="reply_email_address" name="reply_email_address" maxlength="100" inputmode="email" data-inputmask-alias="email"/>';
        $output_start_time =
            '<input value="' . prepare_form_data_for_output($start_time, 'date and time') . '" type="text" maxlength="19" class="form-control" id="start_time" name="start_time" />
            <div class="text-end form-text">' . lang('Leave blank to send as soon as possible.') . '</div>
            ' . get_date_time_picker_format() . '
            <script>
                $("#start_time").datetimepicker(datetimepicker_options);
            </script>';

        if ($purpose == 'commercial') {
            $purpose_commercial_checked = 'checked="checked"';
            $purpose_transactional_checked = '';
        } else {
            $purpose_commercial_checked = '';
            $purpose_transactional_checked = 'checked="checked"';
        }

        $output_purpose =
            '<div class="form-check">
                <input class="form-check-input" type="radio" name="purpose" id="purpose_commercial" value="commercial"' . $purpose_commercial_checked . '>
                <label class="form-check-label" for="purpose_commercial">' . lang('Commercial') . ' (' . lang('send email to opted-in contacts only. Example: \'We have an offer for you\'') . ')</label>
            </div>
            <div class="form-check">
                <input class="form-check-input" type="radio" name="purpose" id="purpose_transactional" value="transactional"' . $purpose_transactional_checked . '>
                <label class="form-check-label" for="purpose_transactional">' . lang('Transactional') . ' (' . lang('send email, regardless of opt-in. Example: \'Your order has been shipped\'') . ')</label>
            </div>';

        $output_buttons =
            '<nav class="buttons navigation text-center position-sticky mb-4" style="bottom:.5rem;" aria-label="data edit buttons ">
                <div class="container">
                    <div class=" btn-group flex-wrap justify-content-center">
                        <button type="submit" id="save_button" name="submit_save" value="Save" class="btn my-1  btn-success " data-loading-content="' . lang(array('string'=>'Saving') ) . '"><span class="material-icons me-2">save</span><span class="btn-text">' . lang(array('string'=>'Save') ) . '</span></button>
                    </div>
                </div>
            </nav>';
        $output_form_end = '</form>';
        
    // else the e-mail campaign is complete, so prepare information for viewing
    } else {
        $output_heading = lang('View Campaign');
        $output_subheading = lang('View this e-mail campaign\'s information.');
        $output_form_start = '';
        $output_status = h(get_email_campaign_status_name($status));
        $output_subject = '<p>' . h($subject) . '</p>';
        $output_bcc_email_address = '<p>' . h($bcc_email_address) . '</p>';
        $output_from_name = '<p>' . h($from_name) . '</p>';
        $output_from_email_address = '<p>' . h($from_email_address) . '</p>';
        $output_reply_email_address = '<p>' . h($reply_email_address) . '</p>';
        
        $output_start_time = '<p>' . h(prepare_form_data_for_output($start_time, 'date and time')) . '</p>';
        
        // if the start time is blank, then set to better value
        if ($output_start_time == '') {
            $output_start_time = '<p>' . lang('Immediately') . '</p>';
        }

        $output_purpose = '<p>' . lang(h(ucwords($purpose))) . '</p>';
        
        $output_buttons = '';
        $output_form_end = '';
    }

    $output_auto_campaign = '';
    
    if ($email_campaign_profile_id) {

        $output_order = '';

        if ($order_reference_code) {
            $output_order =
                '<div class="col-12 col-sm-6 col-xl my-2">
                    <div class="alert border-4 alert-secondary" role="alert">
                        <h4 class="alert-heading">' . lang('Order') . '</h4>
                        <p><a href="view_order.php?id=' . $order_id . '">' . $order_reference_code . '</a></p>
                    </div>
                </div>';
        }

        $output_auto_campaign =
            '<div class="col-12 col-sm-6 col-xl my-2">
                <div class="alert border-4 alert-secondary" role="alert">
                    <h4 class="alert-heading">' . lang('Profile') . '</h4>
                    <p><a href="edit_email_campaign_profile.php?id=' . $email_campaign_profile_id . '">' . h($email_campaign_profile_name) . '</a></p>
                </div>
            </div>
            ' . $output_order;
            
    }
    
    // get total number of recipients
    $query = "SELECT COUNT(*) FROM email_recipients WHERE email_campaign_id = '" . escape($_GET['id']) . "'";
    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
    $row = mysqli_fetch_row($result);
    $number_of_email_recipients = $row[0];
    
    // get total number of complete recipients
    $query = "SELECT COUNT(*) FROM email_recipients WHERE (email_campaign_id = '" . escape($_GET['id']) . "') AND (complete = '1')";
    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
    $row = mysqli_fetch_row($result);
    $number_of_completed_email_recipients = $row[0];
    
    if ($number_of_email_recipients > 0) {
        $output_progress_percentage = number_format($number_of_completed_email_recipients / $number_of_email_recipients * 100);
    } else {
        $output_progress_percentage = '100';
    }

    $output_format = '';
    $output_body = '';

    // if the format is plain text, then output that
    if ($format == 'plain_text') {
        $output_format = lang('Plain Text');

        $output_readonly_attribute = '';

        // if the status is complete, then make the text area read-only
        if ($status == 'complete') {
            $output_readonly_attribute = ' readonly="readonly"';
        }

        $output_body = '<textarea name="body" placeholder="' . lang('Type E-mail body here') . '..."' . $output_readonly_attribute . ' class="form-control" style="width: 99%; height: 300px">' . h($body) . '</textarea>';

    // else the format is HTML, so output that
    } else {
        $output_format = lang('HTML');
        $output_body = '<iframe id="body_preview_iframe" src="view_email_campaign.php?id=' . h($_GET['id']) .'" style="min-width:250px;width: 100%;max-width:100%;min-height:300px; height: 500px;resize: both;overflow: auto;"></iframe>';
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
                WHERE contact_groups_email_campaigns_xref.email_campaign_id = '" . escape($_GET['id']) . "'
                ORDER BY contact_groups.name ASC";
            $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
            
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
            $query = "SELECT email_address FROM email_recipients WHERE (email_campaign_id = '" . escape($_GET['id']) . "') AND (contact_id = '0')";
            $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
            
            // if an entered e-mail address was found, then prepare to output it
            if (mysqli_num_rows($result) > 0) {
                $row = mysqli_fetch_assoc($result);
                $output_entered_email_address = h($row['email_address']);
                
            // else an entered e-mail address was not found, so prepare to output notice
            } else {
                $output_entered_email_address = '[' . lang('None') . ']';
            }

            $output_to_rows =
                '<div class="col-12">
                    <div class="card my-4">
                        <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
                            ' . lang('E-Mail Message To My Contact Groups') . '
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-12 my-2">
                                    <div class="alert alert-primary">
                                        <p>' . lang('Send message to all Subscribers in my selected Contact Groups') . '</p>
                                        <p>' . $output_included_contact_groups . '</p>
                                    </div>

                                    <div class="alert alert-warning">
                                        <p>' . lang('But don\'t send message to any Subscribers that also exist in any of the following Contact Groups') . '</p>
                                        <p>' . $output_excluded_contact_groups . '</p>
                                    </div>

                                    <div class="alert alert-secondary ">
                                        <p>' . lang('Also send message to the following e-mail address') . '</p>
                                        <p>' . $output_entered_email_address . '</p>
                                    </div>
                                </div>
                                
                            </div>
                        </div>
                    </div>
                </div>';

            break;
        
        case 'automatic':
            $to_email_address = db_value("SELECT email_address FROM email_recipients WHERE email_campaign_id = '" . escape($_GET['id']) . "'");

            $output_to_rows =
                '<div class="col-12">
                    <div class="card my-4">
                        <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
                            ' . lang('E-Mail Message To') . '
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-12 my-2">
                                    <div class="alert alert-primary">
                                        <p>' . lang('To') . '</p>
                                        <p>' . h($to_email_address) . '</p>
                                    </div>
                                    <div class="alert alert-secondary ">
                                        <p>' . lang('BCC E-mail Address') . '</p>
                                        <p>' . $output_bcc_email_address . '</p>
                                    </div>
                                </div>
                                
                            </div>
                        </div>
                    </div>
                </div>';

            break;
    }
    
    // if an e-mail campaign job is setup on the server, then allow e-mail campaign to be scheduled
    if (defined('EMAIL_CAMPAIGN_JOB') and EMAIL_CAMPAIGN_JOB === true) {
        $output_start_time_rows =
            '<div class="col-12">
                <div class="card my-4">
                    <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
                        ' . lang('E-Mail Message Delivery Schedule') . '
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-12 col-md-6 col-lg-4 my-2">
                                <label class="form-label" for="start_time">' . lang('Send at this Date & Time') . '</label>
                                ' . $output_start_time . '
                            </div>
                        </div>
                    </div>
                </div>
            </div>';
    }
    print
    pg_page_shell(
        array(
            'title'=> lang('Edit Campaign'),
            'extra classes'=>'campaign',
            'icon'=>'campaign', 
            'heading'=> lang('Edit Campaign'),
            'cancel'=>array('enable'=>'true','url'=>'view_email_campaigns.php')
        ,
            'breadcrumb' => array(array('label' => lang('My Campaigns'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_email_campaigns.php'), array('label' => $output_heading)),
        )
    ) . '
    <script src="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/assets/Jquery/jquery-ui-timepicker-addon-1.2.1.min.js"></script>
            <div class="row">
            <div class="col-12">
                <div class="row mb-2  flex-wrap">
                    <div class="col-12 col-sm-12 text-center text-md-start">
<h2 class="d-inline-block text-break header-content-for-add-page" data-bs-content="' . $output_subheading . '" title="' . $output_heading . '">[' . h($subject) . ']</h2>
                        <p>' . lang(array('string'=>'Created {var:1} by {var:2}.','vars'=>array( get_relative_time(array('timestamp' => $created_timestamp)),h($creator_username) ))) . '</p>
                        ' . $output_button_bar . '
                    </div>
                </div>
                ' . $output_form_start . '
                <div class="row">

                    <div class="col-12 col-sm-6 col-xl my-2">
                        <div class="alert border-4 alert-secondary" role="alert">
                            <h4 class="alert-heading">' . lang('Status') . '</h4>
                            <p class="mb-0">' . $output_status . '</p>
                        </div>
                    </div>
                    <div class="col-12 col-sm-6 col-xl my-2">
                        <div class="alert border-4 alert-secondary" role="alert">
                            <h4 class="alert-heading">' . lang('Progress') . '</h4>
                            <p>' . $output_progress_percentage . '% (' . number_format($number_of_completed_email_recipients) . lang(' of ') . number_format($number_of_email_recipients) . ' ' . lang('subscribers') . ')</p>
                        </div>
                    </div> 
                    ' . $output_auto_campaign . '
                    <div class="col-12">
                        <div class="card my-4">
                            <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
                                ' . lang('E-Mail Message') . '
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-12 my-2">
                                        <label for="subject" class="form-label">' . lang('Subject') . '</label>
                                        ' . $output_subject . '
                                    </div> 
                                    <div class="col-12 my-2">
                                        <label for="format" class="form-label">' . lang('Format') . '</label>
                                        <p>' . $output_format . '</p>
                                    </div> 
                                    <div class="col-12 my-2">
                                        <p for="subject" class="form-label">' . lang('Body') . '</p>
                                        ' . $output_body . '
                                    </div> 
                                </div> 
                            </div> 
                        </div> 
                    </div>
                    ' . $output_to_rows . '
                    <div class="col-12">
                        <div class="card my-4">
                            <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
                                ' . lang('E-Mail Message From') . '
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-12 col-sm-6 col-xl-12 my-2">
                                        <label class="form-label" for="from_name">' . lang('From Name') . '</label>
                                        ' . $output_from_name . '
                                    </div>
                                    <div class="col-12">
                                        <div class="row">
                                            <div class="col-12 col-sm-6 col-xl-4 my-2">
                                                <label class="form-label" for="from_email_address">' . lang('From E-mail Address') . '</label>
                                                ' . $output_from_email_address . '
                                            </div>
                                            <div class="col-12 col-sm-6 col-xl-4 my-2">
                                                <label class="form-label" for="reply_email_address">' . lang('Reply to E-mail Address') . '</label>
                                                ' . $output_reply_email_address . '
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    ' . $output_start_time_rows . '
                    <div class="col-12">
                        <div class="card my-4">
                            <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
                            ' . lang('Purpose') . ' (' . lang('as defined by the CAN-SPAM Act') . ')
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-12 my-3">
                                        <label class="form-label">' . lang('Purpose') . '</label>
                                        ' . $output_purpose . '
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    ' . $output_buttons . '
                    ' . $output_form_end . '
                </div>
            </div>
        </main>' .
        output_footer();
    
// else form has been submitted
} else {
    validate_token_field();
    
    // check if the e-mail campaign is complete
    $query =
        "SELECT
            type,
            status,
            subject,
            format
        FROM email_campaigns
        WHERE id = '" . escape($_POST['id'] ?? '') . "'";
    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
    $row = mysqli_fetch_assoc($result);
    
    $type = $row['type'];
    $status = $row['status'];
    $subject = $row['subject'];
    $format = $row['format'];
    
    // if the e-mail campaign is complete, then output error
    if ($status == 'complete') {
        output_error(lang('You may not edit a completed campaign.') . ' <a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.');
    }

    $sql_body = "";

    // if the format is plain text, then allow the body to be updated
    if ($format == 'plain_text') {
        $sql_body = "body = '" . escape($_POST['body'] ?? '') . "',";
    }

    $sql_bcc_email_address = "";

    // If the campaign is an automatic campaign, then allow BCC email address to be updated.
    if ($type == 'automatic') {
        $sql_bcc_email_address = "bcc_email_address = '" . escape($_POST['bcc_email_address'] ?? '') . "',";
    }
    
    $query =
        "UPDATE email_campaigns
        SET
            status = '" . escape($_POST['status'] ?? '') . "',
            subject = '" . escape($_POST['subject'] ?? '') . "',
            " . $sql_body . "
            from_name = '" . escape($_POST['from_name'] ?? '') . "',
            from_email_address = '" . escape($_POST['from_email_address'] ?? '') . "',
            reply_email_address = '" . escape($_POST['reply_email_address'] ?? '') . "',
            " . $sql_bcc_email_address . "
            start_time = '" . escape(prepare_form_data_for_input($_POST['start_time'], 'date and time')) . "',
            purpose = '" . e($_POST['purpose'] ?? '') . "',
            last_modified_user_id = '" . $user['id'] . "',
            last_modified_timestamp = UNIX_TIMESTAMP()
        WHERE id = '" . escape($_POST['id'] ?? '') . "'";
    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
    
    log_activity(lang(array('string'=>'campaign (subject: {var:1}) was modified','vars'=>$subject)), $_SESSION['sessionusername']);

    include_once('liveform.class.php');

    if (mb_strpos($_POST['send_to'], 'view_email_campaign_history.php') !== false) {
        $liveform = new liveform('view_email_campaign_history');
    } else {
        $liveform = new liveform('view_email_campaigns');
    }

    $liveform->add_notice(lang('The campaign has been saved.'));

    // If there is a send to set, then forward user to send to.
    if ($_POST['send_to'] != '') {
        header('Location: ' . URL_SCHEME . HOSTNAME . $_POST['send_to']);
        
    // Otherwise there is not a send to set, so forward user to view e-mail campaigns screen.
    } else {
        header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/view_email_campaigns.php');
    }
}