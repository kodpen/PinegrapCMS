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
 
// set memory limit to unlimited
ini_set('memory_limit', '-1');
include('init.php');
$user = validate_user();
validate_email_access($user);

// if form has not been submitted yet
if (!$_POST) {
    // if a page id was passed in the query string, then check the HTML radio button and show and hide rows
    if (isset($_GET['page_id']) == TRUE) {
        $output_format_plain_text_checked = '';
        $output_format_html_checked = ' checked="checked"';

        $query = "SELECT page_name, page_folder FROM page WHERE page_id = '" . escape($_GET['page_id']) . "'";
        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
        $row = mysqli_fetch_assoc($result);
        $page_name = $row['page_name'];
        $folder_id = $row['page_folder'];
        
        // if the user has edit access to the page, then prepare to show body preview row and set iframe source
        if (check_edit_access($folder_id) == true) {
            $output_body_preview_iframe_source = OUTPUT_PATH . h(encode_url_path($page_name)) . '?edit=no&email=true';

        // else the user does not have edit access to the page, so hide body preview row and set iframe source to blank
        } else {
            $output_body_preview_iframe_source = '';
        }

    // else a page id was not passed in the query string, so check the plain text radio button and show and hide rows
    } else {
        $output_format_plain_text_checked = ' checked="checked"';
        $output_format_html_checked = '';
        $output_body_preview_iframe_source = '';
    }

    $output_body = '';

    $plain_text_email_campaign_footer = db_value("SELECT plain_text_email_campaign_footer FROM config");

    if ($plain_text_email_campaign_footer != '') {
        $output_body =
            "\n" .
            "\n" .
            "\n" .
            h($plain_text_email_campaign_footer);
    }

    // get all pages
    $query =
        "SELECT
            page.page_id as id,
            page.page_name as name,
            page.page_folder as folder_id,
            folder.folder_archived
        FROM page
        LEFT JOIN folder ON page.page_folder = folder.folder_id
        WHERE folder.folder_archived = '0'
        ORDER BY page.page_name";
    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
    
    $pages = array();
    
    // loop through all pages so they can be added to array
    while ($row = mysqli_fetch_assoc($result)) {
        $pages[] = $row;
    }
    
    $output_page_options = '';
    
    // loop through all pages in order to prepare options for pick list
    foreach ($pages as $page) {
        // if the user has access to view this page, then prepare option for this page
        if (check_view_access($page['folder_id']) == true) {
            // assume that this page should not be selected by default, until we find out otherwise
            $selected = '';
            
            // if this page should be selected, then prepare to select it
            if ($page['id'] == ($_GET['page_id'] ?? '')) {
                $selected = ' selected="selected"';
            }
            
            // prepare option
            $output_page_options .= '<option value="' . $page['id'] . '"' . $selected . '>' . h($page['name']) . '</option>';
        }
    }
    
    // get all contact groups
    $query =
        "SELECT
           id,
           name
        FROM contact_groups
        ORDER BY name";
    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');

    $output_contact_group_rows = '';
    
    // loop through all contact groups
    while ($row = mysqli_fetch_assoc($result)) {
        $id = $row['id'];
        $name = $row['name'];
        
        // if user has access to contact group, then include this contact group
        if (validate_contact_group_access($user, $id) == true) {
            // get number of contacts in contact group
            $number_of_contacts = get_number_of_contacts($id, $require_email = true);
            
            // if contact group has at least one contact
            if ($number_of_contacts > 0) {
                $output_contact_group_rows .=
                    '<tr>
                        <td>' . h($name) . ' (' . number_format($number_of_contacts) . ')</td>
                        <td style="text-align: center"><div class="form-check  form-check-inline"><input class="form-check-input" type="radio" name="contact_group_' . $id . '" value="ignored" class="radio" checked="checked" /></div></td>
                        <td style="text-align: center"><div class="form-check  form-check-inline"><input class="form-check-input" type="radio" name="contact_group_' . $id . '" value="included" class="radio" /></div></td>
                        <td style="text-align: center"><div class="form-check  form-check-inline"><input class="form-check-input" type="radio" name="contact_group_' . $id . '" value="excluded" class="radio" /></div></td>
                    </tr>';
            }
        }
    }
    
    // if there is at least one contact group to show, then prepare to output contact groups
    if ($output_contact_group_rows != '') {
        $output_contact_groups =
            '<div class="alert alert-primary"><p>' . lang('Send message to all Subscribers in my selected Contact Groups') . '</p></div>
            <div style="margin-bottom: 1.5em">
                <table class="table">
                    <tr>
                        <th>&nbsp;</th>
                        <th style="text-align: center">' . lang('Ignore') . '</th>
                        <th style="text-align: center">' . lang('Include') . '</th>
                        <th style="text-align: center">' . lang('Exclude') . '</th>
                    </tr>
                    ' . $output_contact_group_rows . '
                </table>
            </div>';
    } else {
        $output_contact_groups = '<div class="alert alert-danger"><p>' . lang('You do not have access to any contact groups with subscribers.') . '</p></div>';
    }
    
    // only set when the e-mail campaign job is available, but always output below
    $output_start_time = '';

    // if an e-mail campaign job is setup on the server, then allow e-mail campaign to be scheduled
    if (defined('EMAIL_CAMPAIGN_JOB') and EMAIL_CAMPAIGN_JOB === true) {
        $output_start_time =
            '<div class="col-12">
                <div class="card my-4">
                    <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
                        ' . lang('E-Mail Message Delivery Schedule') . '
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-12 col-md-6 col-lg-4 my-2">
                                <label class="form-label" for="start_time">' . lang('Send at this Date & Time') . '</label>
                                <input value="" type="text" maxlength="19" class="form-control" id="start_time" name="start_time" />
                                <div class="text-end form-text">' . lang('Leave blank to send as soon as possible.') . '</div>
                                ' . get_date_time_picker_format() . '
                                <script>
                                    $("#start_time").datetimepicker(datetimepicker_options);
                                </script>
                            </div>
                        </div>
                    </div>
                </div>
            </div>';
        }

    print
    
    pg_page_shell(
        array(
            'title'=> lang('Create Campaign'),
            'extra classes'=>'campaign',
            'icon'=>'campaign',
            'heading'=> lang('Create Campaign'),
            'cancel'=>array('enable'=>'true','url'=>'view_email_campaigns.php'),
            'breadcrumb' => array(
                array('label' => lang('My Campaigns'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_email_campaigns.php'),
                array('label' => lang('Create Campaign')),
            ),
        )
    ) . '
    <script src="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/assets/Jquery/jquery-ui-timepicker-addon-1.2.1.min.js"></script>
            <div class="row">
            <div class="col-12">
                <div class="row mb-2  flex-wrap">
                    <div class="col-12 col-sm-12 text-center text-md-start">
                        <h2 class="d-inline-block text-break header-content-for-add-page" data-bs-content="' . lang('Create a new e-mail campaign to send to all subscribers in selected contact groups.') . '" title="' . lang('Create Campaign') . '">[' . lang('Campaign Subject') . ']</h2>
                    </div>
                </div>
                <form name="form" action="add_email_campaign.php" method="post" >
                    ' . get_token_field() . '
                    <div class="row">
                        <div class="col-12">
                            <div class="card my-4">
                                <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
                                    ' . lang('E-Mail Message') . '
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-12 my-2">
                                            <label for="subject" class="form-label">' . lang('Subject') . '</label>
                                            <input type="text" name="subject" placeholder="' . lang('Subject') . '" maxlength="255" id="subject" class="form-control add-header-content-updater" />
                                        </div>
                                        <div class="col-12 my-1">
                                            <div class="col-12">
                                                <label class="form-label">' . lang('Format') . '</label>
                                            </div>
                                            <div class="form-check  form-check-inline">
                                                <input class="form-check-input collapse-switcher" type="radio" id="format_plain_text" name="format" value="plain_text" data-bs-target="#email_format_plain_text_row"' . $output_format_plain_text_checked . ' />
                                                <label for="format_plain_text">' . lang('Plain Text') . '</label> 
                                            </div>
                                            <div class="form-check  form-check-inline">
                                                <input class="form-check-input collapse-switcher" type="radio" id="format_html" name="format" value="html"  data-bs-target="#email_format_html_row"' . $output_format_html_checked . '/>
                                                <label for="format_html">' . lang('HTML') . '</label>
                                            </div>
                                            <div class="collapse popover fade bs-popover-bottom p-0 mb-2 w-100" id="email_format_plain_text_row">
                                                <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(18px, 0px);"></div>
                                                <div class="popover-body">
                                                    <div class="row">
                                                        <div class="col-12 my-1">
                                                          <label for="email_body" class="form-label">' . lang('Body') . '</label>
                                                          <textarea name="body" id="email_body" placeholder="' . lang('Type E-mail body here') . '..." class="form-control" style="width: 99%; height: 300px">' . $output_body . '</textarea>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="collapse popover fade bs-popover-bottom p-0 mb-2 w-100"  id="email_format_html_row">
                                                <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(111px, 0px);"></div>
                                                <div class="popover-body">
                                                    <div class="row">
                                                        <div class="col-12 col-sm-8 my-1">
                                                            <label class="form-label" for="page_id">' . lang('Page') . '</label>
                                                            <select class="form-select collapse-if-selected" id="page_id" name="page_id" onchange="document.getElementById(\'body_preview_iframe\').src = \'' . OUTPUT_PATH . '\' + this.options[this.selectedIndex].firstChild.nodeValue + \'?edit=no&email=true\';" data-bs-target="#body_preview_row"><option value="">-' . lang(array('string'=>'Select {var:1}','vars'=>array(lang('Page')) )) . '-</option>' . $output_page_options . '</select>
                                                        </div>
                                                        <div class="collapse popover fade bs-popover-bottom p-0 mb-2 w-100" id="body_preview_row">
                                                            <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(59px, 0px);"></div>
                                                            <div class="popover-body">
                                                                <div class="row">
                                                                    <div class="col-12 my-1">
                                                                        <div style="margin-bottom: 1em">' . lang('Body Preview') . '</div>
                                                                        <iframe id="body_preview_iframe" src="' . $output_body_preview_iframe_source . '" style="min-width:250px;width: 100%;max-width:100%;min-height:300px; height: 500px;resize: both;overflow: auto;"></iframe>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="card my-4">
                                <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
                                    ' . lang('E-Mail Message To My Contact Groups') . '
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-12 my-2">
                                            ' . $output_contact_groups . '
                                        </div>
                                        <div class="col-12 my-2">
                                            <div class="alert alert-secondary row justify-content-between">
                                                <div class="col-auto">
                                                <p>' . lang('Also send message to the following e-mail address') . ': </p>
                                                </div>
                                                <div class="col-12 col-6 col-lg-4">
                                                    <input type="text" class="form-control text-end" id="entered_email_address" name="entered_email_address" maxlength="100" inputmode="email" data-inputmask-alias="email">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="card my-4">
                                <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
                                    ' . lang('E-Mail Message From') . '
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-12 col-sm-6 col-xl-12 my-2">
                                            <label class="form-label" for="from_name">' . lang('From Name') . '</label>
                                            <input value="' . h(ORGANIZATION_NAME) . '" type="text" class="form-control" id="from_name" name="from_name" />
                                        </div>
                                        <div class="col-12">
                                            <div class="row">
                                                <div class="col-12 col-sm-6 col-xl-4 my-2">
                                                    <label class="form-label" for="from_email_address">' . lang('From E-mail Address') . '</label>
                                                    <input value="' . h($user['email_address']) .'" type="text" class="form-control text-end" id="from_email_address" name="from_email_address" maxlength="100" inputmode="email" data-inputmask-alias="email"/>
                                                </div>
                                                <div class="col-12 col-sm-6 col-xl-4 my-2">
                                                    <label class="form-label" for="reply_email_address">' . lang('Reply to E-mail Address') . '</label>
                                                    <input value="' . h($user['email_address']) .'" type="text" class="form-control text-end" id="reply_email_address" name="reply_email_address" maxlength="100" inputmode="email" data-inputmask-alias="email"/>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        ' . $output_start_time . '
                        <div class="col-12">
                            <div class="card my-4">
                                <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
                                ' . lang('Purpose') . ' (' . lang('as defined by the CAN-SPAM Act') . ')
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-12 my-3">
                                            <label class="form-label">' . lang('Purpose') . '</label>
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="purpose" id="purpose_commercial" value="commercial" checked="checked">
                                                <label class="form-check-label" for="purpose_commercial">' . lang('Commercial') . ' (' . lang('send email to opted-in contacts only. Example: \'We have an offer for you\'') . ')</label>
                                            </div>
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="purpose" id="purpose_transactional" value="transactional">
                                                <label class="form-check-label" for="purpose_transactional">' . lang('Transactional') . ' (' . lang('send email, regardless of opt-in. Example: \'Your order has been shipped\'') . ')</label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <nav class="buttons navigation text-center position-sticky mb-4" style="bottom:.5rem;" aria-label="data edit buttons ">
                        <div class="container">
                            <div class=" btn-group flex-wrap justify-content-center">
                                <button type="submit" id="create_button" name="submit_create" value="Create" class="btn my-1  btn-success " data-loading-content="' . lang(array('string'=>'Creating') ) . '"><span class="bi bi-plus-circle me-2"></span><span class="btn-text">' . lang(array('string'=>'Create') ) . '</span></button>
                            </div>
                        </div>
                    </nav>
                </form>
            </div>
        </div>
    </main>' .
    output_footer();
    
// else form has been submitted
} else {
    validate_token_field();
    
    // if the user selected plain text, then clear the page id, so we don't store it with the e-mail campaign
    if ($_POST['format'] == 'plain_text') {
        $_POST['page_id'] = '';

    // else the user selected HTML for the format, so do some checks
    } else {
        // if the user did not select a page, then output error
        if ($_POST['page_id'] == '') {
            output_error(lang('Please select a page for the body of the campaign.') . ' <a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.');
        }

        // get folder id for selected page in order to check if user has access to view content in folder
        $query = "SELECT page_folder FROM page WHERE page_id = '" . escape($_POST['page_id'] ?? '') . "'";
        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
        $row = mysqli_fetch_assoc($result);
        $folder_id = $row['page_folder'];
        
        // if the user does not have view access to the selected page's folder, then log activity and output error
        if (check_view_access($folder_id) == false) {
            log_activity(lang('access denied for user to send a page for a campaign, because user does not have view access to the page'), $_SESSION['sessionusername']);
            output_error(lang('You are not authorized to send that page for the body of the campaign.') . ' <a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.');
        }
    }
    
    $recipients = array();
    $recipients_contact_id_xref = array();
    
    // get all contact groups
    $query = "SELECT id FROM contact_groups";
    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
    
    $contact_groups = array();
    
    while ($row = mysqli_fetch_assoc($result)) {
        $contact_groups[] = $row['id'];
    }
    
    $included_contact_groups = array();
    
    // loop through all contact groups in order to get included e-mail addresses
    foreach ($contact_groups as $contact_group_id) {
        // if this contact group was included, then get e-mail addresses for this contact group
        if (($_POST['contact_group_' . $contact_group_id] ?? '') == 'included') {
            // if user has access to contact group, then continue
            if (validate_contact_group_access($user, $contact_group_id) == true) {
                // store this included contact group in array, so later we can store which contact groups were included
                $included_contact_groups[] = $contact_group_id;
                
                // get e-mail subscription information about contact group
                $query =
                    "SELECT email_subscription
                    FROM contact_groups
                    WHERE id = '" . escape($contact_group_id) . "'";
                $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
                $row = mysqli_fetch_assoc($result);
                $email_subscription = $row['email_subscription'];
                
                // if this is a subscription contact group
                if ($email_subscription == 1) {
                    // get all contacts in this contact group that are global opted-in and opted-in to this contact group
                    $query =
                        "SELECT
                            contacts.id,
                            contacts.email_address,
                            opt_in.opt_in
                        FROM contacts_contact_groups_xref
                        LEFT JOIN contacts ON contacts_contact_groups_xref.contact_id = contacts.id
                        LEFT JOIN opt_in ON (contacts_contact_groups_xref.contact_id = opt_in.contact_id) AND (contacts_contact_groups_xref.contact_group_id = opt_in.contact_group_id)
                        WHERE
                            (contacts_contact_groups_xref.contact_group_id = '" . escape($contact_group_id) . "')
                            AND (contacts.opt_in = 1)
                            AND (
                                (opt_in.opt_in = 1)
                                OR (opt_in.opt_in IS NULL)
                            )
                            AND (contacts.email_address != '')";
                    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
                    
                // else this is not a subscription contact group
                } else {
                    // get all contacts in this contact group that are global opted-in
                    $query =
                        "SELECT
                            contacts.id,
                            contacts.email_address
                        FROM contacts_contact_groups_xref
                        LEFT JOIN contacts ON contacts_contact_groups_xref.contact_id = contacts.id
                        WHERE
                            (contacts_contact_groups_xref.contact_group_id = '" . escape($contact_group_id) . "')
                            AND (contacts.opt_in = 1)
                            AND (contacts.email_address != '')";
                    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
                }
                
                // loop through all contact e-mail addresses
                while ($row = mysqli_fetch_assoc($result)) {
                    $email_address = mb_strtolower($row['email_address']);
                    $recipients[] = $email_address;
                    
                    // prevent duplicate entries from overwriting the first contact id found for this e-mail address
                    if (!array_key_exists($email_address, $recipients_contact_id_xref)) {
                        $recipients_contact_id_xref[$email_address] = $row['id'];
                    }
                }
            }
        }
    }
    
    // remove duplicate e-mail addresses before we exclude e-mail addresses
    $recipients = array_unique($recipients);
    
    $excluded_contact_groups = array();
    
    // loop through all contact groups in order to get excluded e-mail addresses
    foreach ($contact_groups as $contact_group_id) {
        // if this contact group was excluded, then get e-mail addresses for this contact group
        if (($_POST['contact_group_' . $contact_group_id] ?? '') == 'excluded') {
            // if user has access to contact group, then continue
            if (validate_contact_group_access($user, $contact_group_id) == true) {
                // store this excluded contact group in array, so later we can store which contact groups were excluded
                $excluded_contact_groups[] = $contact_group_id;
                
                // get all contacts in this contact group
                $query =
                    "SELECT contacts.email_address
                    FROM contacts_contact_groups_xref
                    LEFT JOIN contacts ON contacts_contact_groups_xref.contact_id = contacts.id
                    WHERE
                        (contacts_contact_groups_xref.contact_group_id = '" . escape($contact_group_id) . "')
                        AND (contacts.email_address != '')";
                $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
                
                // loop through all contact e-mail addresses
                while ($row = mysqli_fetch_assoc($result)) {
                    // check if this e-mail address has been included
                    $key = array_search(mb_strtolower($row['email_address']), $recipients);
                    
                    // if this e-mail address has been included, then exclude this e-mail address
                    if ($key !== false) {
                        unset($recipients[$key]);
                    }
                }
            }
        }
    }
    
    // if entered e-mail address was entered
    if ($_POST['entered_email_address']) {
        // if e-mail address is valid
        if (validate_email_address($_POST['entered_email_address']) == true) {
            $recipients[] = mb_strtolower($_POST['entered_email_address']);
            
            // if user has a role that is greater than user role, then possibly create contact for entered e-mail address
            if ($user['role'] < 3) {
                // determine if entered e-mail address is already in use by a contact
                $query = "SELECT id
                         FROM contacts
                         WHERE email_address = '" . escape($_POST['entered_email_address'] ?? '') . "'";
                $result_contacts = mysqli_query(db::$con, $query) or output_error('Query failed.');

                // determine if entered e-mail address is already in use by a user
                $query = "SELECT user_id
                         FROM user
                         WHERE user_email = '" . escape($_POST['entered_email_address'] ?? '') . "'";
                $result_users = mysqli_query(db::$con, $query) or output_error('Query failed.');

                // if entered e-mail address is not in use by a contact or a user, create contact for entered e-mail address
                if ((mysqli_num_rows($result_contacts) == 0) && (mysqli_num_rows($result_users) == 0)) {
                    $query = "INSERT INTO contacts
                             (email_address, user, timestamp)
                             VALUES ('" . escape($_POST['entered_email_address'] ?? '') . "', '" . $user['id'] . "', UNIX_TIMESTAMP())";
                    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
                }
            }

        // else e-mail address is not valid, so generate error
        } else {
            output_error(lang('The e-mail address you entered under Enter Recipient is invalid.') . ' <a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.');
        }
    }

    // if there are no recipients, output error
    if (!$recipients) {
        output_error(lang('Please select at least one recipient.') . ' <a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.');
    }

    // remove duplicate e-mail addresses
    $recipients = array_unique($recipients);
    
    // get subject
    $subject = $_POST['subject'];

    // if plain text was selected for the format, then store body in variable
    if ($_POST['format'] == 'plain_text') {
        $body = $_POST['body'];

    // else HTML was selected for the format, so prepare body and store in variable
    } else {
        require_once(dirname(__FILE__) . '/get_page_content.php');

        // get html for page
        $body = get_page_content($_POST['page_id'], $system_content = '', $extra_system_content = '', $mode = 'preview', $email = true);
        
        // find if there is a base tag in the HTML
        $base_in_html = preg_match('/<\s*base\s+[^>]*href\s*=\s*["\'](?:http:\/\/|https:\/\/|ftp:\/\/).*?["\']/is', $body);

        // if there is not a base tag in the HTML, add base tag and convert relative links to absolute links
        if (!$base_in_html) {
            $base = '<head>' . "\n" . '<base href="' . URL_SCHEME . HOSTNAME_SETTING . '/" />';
            $body = preg_replace('/<head>/i', $base, $body);

            // change relative URLs to absolute URLs for links
            $body = preg_replace('/(<\s*a\s+[^>]*href\s*=\s*["\'])(?!ftp:\/\/|https:\/\/|mailto:|http:\/\/)(?:\/|\.\.\/|\.\/|)(.*?["\'].*?>)/is', "$1" . URL_SCHEME . HOSTNAME_SETTING . "/$2", $body);

            // change relative URLs to absolute URLs for images
            $body = preg_replace('/(<\s*img\s+[^>]*src\s*=\s*["\'])(?!http:\/\/|https:\/\/)(?:\/|\.\.\/|\.\/|)(.*?["\'].*?>)/is', "$1" . URL_SCHEME . HOSTNAME_SETTING . "/$2", $body);

            // change relative URLs to absolute URLs for CSS background images
            $body = preg_replace('/(background-image\s*:\s*url\s*\(\s*(?:"|\'|))(?!http:\/\/|https:\/\/)(?:\/|\.\.\/|\.\/|)(.*?(?:"|\'|).*?\))/is', "$1" . URL_SCHEME . HOSTNAME_SETTING . "/$2", $body);

            // change relative URLs to absolute URLs for HTML background images
            $body = preg_replace('/(background\s*=\s*["\'])(?!http:\/\/|https:\/\/)(?:\/|\.\.\/|\.\/|)(.*?["\'])/is', "$1" . URL_SCHEME . HOSTNAME_SETTING . "/$2", $body);
        }
        
        // get all links in order to add tracking codes to links
        preg_match_all('/(<\s*a\s+[^>]*href\s*=\s*["\']\s*)(.*?)(\s*["\'].*?>)/is', $body, $links);

        // If the date format is month and then day, then use that format.
        if (DATE_FORMAT == 'month_day') {
            $month_and_day_format = 'm/d';

        // Otherwise the date format is day and then month, so use that format.
        } else {
            $month_and_day_format = 'd/m';
        }

        // set tracking code to contain the page name and date and time
        $tracking_code = get_page_name($_POST['page_id']) . '_' . date($month_and_day_format . '/Y_h:i_A');

        // loop through all links in order to add tracking codes to links
        foreach ($links[0] as $key => $link) {
            // set the URL that was found in the link
            $url = $links[2][$key];
            
            // remove new lines from the link
            $url = str_replace("\r\n", '', $url);
            $url = str_replace("\n", '', $url);
            
            $url_parts = @parse_url($url);
            
            // if the URL is valid
            // and if there is not a scheme or the scheme is http or https
            // and if there is not a hostname or the hostname is this site's hostname
            // and if there is not already a tracking code in the URL
            // then continue with adding tracking code to URL
            if (
                ($url_parts != false)
                && ((isset($url_parts['scheme']) == false) || ($url_parts['scheme'] == '') || (mb_strtolower($url_parts['scheme']) == 'http') || (mb_strtolower($url_parts['scheme']) == 'https'))
                && ((isset($url_parts['host']) == false) || ($url_parts['host'] == '') || (mb_strtolower(str_replace('www.', '', $url_parts['host'])) == mb_strtolower(str_replace('www.', '', HOSTNAME_SETTING))))
                && ((isset($url_parts['query']) == false) || ($url_parts['query'] == '') || mb_strpos($url_parts['query'], 't=') === false)
            ) {
                $new_url = '';
                
                // if there is a scheme, then add scheme to new URL
                if ((isset($url_parts['scheme']) == true) && ($url_parts['scheme'] != '')) {
                    $new_url .= $url_parts['scheme'] . '://';
                }
                
                // if there is a hostname, then add hostname to new URL
                if ((isset($url_parts['host']) == true) && ($url_parts['host'] != '')) {
                    $new_url .= $url_parts['host'];
                }
                
                // if there is a path, then add path to new URL
                if ((isset($url_parts['path']) == true) && ($url_parts['path'] != '')) {
                    $new_url .= $url_parts['path'];
                }
                
                $new_url .= '?';
                
                // if there is a query string, then add query string and ampersand to new URL
                if ((isset($url_parts['query']) == true) && ($url_parts['query'] != '')) {
                    $new_url .= $url_parts['query'] . '&amp;';
                }
                
                $new_url .= 't=' . h(urlencode($tracking_code));
                
                // if there is a bookmark, then add bookmark to the new URL
                if ((isset($url_parts['fragment']) == true) && ($url_parts['fragment'] != '')) {
                    $new_url .= '#' . $url_parts['fragment'];
                }
                
                $entire_link = $links[0][$key];
                $link_start = $links[1][$key];
                $link_end = $links[3][$key];        
                
                // replace the link with the new link
                $body = str_replace($entire_link, $link_start . $new_url . $link_end, $body);
            }
        }
        
        // get URL for page
        $page_url = URL_SCHEME . HOSTNAME_SETTING . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_email_campaign.php?r=<reference_code></reference_code>';

        $email_preferences_url = URL_SCHEME . HOSTNAME_SETTING . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/email_preferences.php?id=<email_address_id></email_address_id>';
        
        $footer =
			     '<div class="software_email_footer" style="font-family: arial; font-size: 11px; color: #666666; text-align: center; background-color: #ffffff; padding: 5px; margin-top: 15px">
			         <a href="' . $page_url . '" style="color: #666666">' . lang('View this email at our site') . '</a><br>
			         ' . h(ORGANIZATION_NAME) . '
			         ' . h(ORGANIZATION_ADDRESS_1) . '
			         ' . h(ORGANIZATION_ADDRESS_2) . '
			         ' . h(ORGANIZATION_CITY) . ' ' . h(ORGANIZATION_STATE) . ' ' . h(ORGANIZATION_ZIP_CODE) . ' ' . h(ORGANIZATION_COUNTRY) . '<br>
			         <a href="' . $email_preferences_url . '" style="color: #666666">' . lang('Update email preferences') . '</a>' . lang(' or ') . '<a href="' . $email_preferences_url . '" style="color: #666666">' . lang('unsubscribe') . '</a><br>
			     </div>
			     </body>';

        $body = preg_replace('/<\/body>/i', $footer, $body);
    }

    // wrap long lines (RFC 821)
    $body = wordwrap($body, 900, "\n", 1);
    
    // create e-mail campaign
    $query =
        "INSERT INTO email_campaigns (
            from_name,
            from_email_address,
            reply_email_address,
            subject,
            format,
            body,
            page_id,
            start_time,
            purpose,
            created_user_id,
            created_timestamp,
            last_modified_user_id,
            last_modified_timestamp)
        VALUES (
            '" . escape($_POST['from_name'] ?? '') . "',
            '" . escape($_POST['from_email_address'] ?? '') . "',
            '" . escape($_POST['reply_email_address'] ?? '') . "',
            '" . escape($_POST['subject'] ?? '') . "',
            '" . escape($_POST['format'] ?? '') . "',
            '" . escape($body) . "',
            '" . escape($_POST['page_id'] ?? '') . "',
            '" . escape(prepare_form_data_for_input($_POST['start_time'], 'date and time')) . "',
            '" . e($_POST['purpose'] ?? '') . "',
            '" . $user['id'] . "',
            UNIX_TIMESTAMP(),
            '" . $user['id'] . "',
            UNIX_TIMESTAMP())";
    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
    
    $email_campaign_id = mysqli_insert_id(db::$con);
    
    // loop through all recipients in order to create record in database for each recipient
    foreach ($recipients as $email_address) {
        if (array_key_exists($email_address, $recipients_contact_id_xref)) {
            $contact_id = $recipients_contact_id_xref[$email_address];
        } else {
            $contact_id = 0;
        }
        
        // create e-mail recipients
        $query =
            "INSERT INTO email_recipients (
                email_campaign_id,
                email_address,
                contact_id)
            VALUES (
                '$email_campaign_id',
                '" . escape($email_address) . "',
                '" . $contact_id . "')";
        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
    }
    
    // loop through included contact groups in order to store contacts groups that were included in this e-mail campaign
    foreach ($included_contact_groups as $contact_group_id) {
        $query =
            "INSERT INTO contact_groups_email_campaigns_xref (
                contact_group_id,
                email_campaign_id,
                type)
            VALUES (
                '" . escape($contact_group_id) . "',
                '$email_campaign_id',
                'included')";
        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
    }
    
    // loop through excluded contact groups in order to store contacts groups that were excluded in this e-mail campaign
    foreach ($excluded_contact_groups as $contact_group_id) {
        $query =
            "INSERT INTO contact_groups_email_campaigns_xref (
                contact_group_id,
                email_campaign_id,
                type)
            VALUES (
                '" . escape($contact_group_id) . "',
                '$email_campaign_id',
                'excluded')";
        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
    }

    $log_page = '';

    // if HTML was selected for the format, then add page info to log message
    if ($_POST['format'] == 'html') {
        $log_page = ', ' . lang('page') . ': ' . get_page_name($_POST['page_id']);
    }
    
    log_activity(lang(array('string'=>'campaign (subject: {var:1}) was created','vars'=>$_POST['subject'] . $log_page)), $_SESSION['sessionusername']);

    include_once('liveform.class.php');
    $liveform = new liveform('view_email_campaigns');
    
    // if email campaign job is active
    if (defined('EMAIL_CAMPAIGN_JOB') and EMAIL_CAMPAIGN_JOB === true) {
        $liveform->add_notice(lang('The campaign has been created, and it will be sent at the scheduled time.'));
        
    // else email campaign job is not active
    } else {
        $liveform->add_notice(lang(array('string'=>'The campaign has been created, and you may {var:1}.','vars'=>'<a href="send_email_campaign.php?id=' . $email_campaign_id . get_token_query_string_field() . '" onclick="window.open(\'send_email_campaign.php?id=' . $email_campaign_id . get_token_query_string_field() . '\', \'\', \'width=450, height=350, resizable=1, scrollbars=0\'); return false;">' . lang('send it now') . '</a>')) );
    }
    
    // forward user to view e-mail campaigns screen
    header('Location: ' . URL_SCHEME . $_SERVER['HTTP_HOST'] . PATH . SOFTWARE_DIRECTORY . '/view_email_campaigns.php');
}