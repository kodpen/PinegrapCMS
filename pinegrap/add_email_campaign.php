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
 
// set memory limit to unlimited
ini_set('memory_limit', '-1');
include('init.php');
require_once(dirname(__FILE__) . '/includes/forms/email_campaign_form.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/email_campaign_recipients.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/email_campaign_create.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/campaign_content.php');
$user = validate_user();
validate_email_access($user);

// The page is one value; one sent as an array (?page_id[]=x) counts as not
// given: it ended in an error page.
pg_request_single_values(array('page_id'));

// if form has not been submitted yet
if (!$_POST) {
    // A page passed in the address (a page's "Send as campaign") is the
    // design chosen; without one the design is still the first kind shown.
    $output_body_kind = 'design';
    $output_preview = '';
    $output_edit_url = '';

    $output_body = '';

    $plain_text_email_campaign_footer = db_value("SELECT plain_text_email_campaign_footer FROM config");

    if ($plain_text_email_campaign_footer != '') {
        $output_body =
            "\n" .
            "\n" .
            "\n" .
            $plain_text_email_campaign_footer;
    }

    // get all pages
    $query =
        "SELECT
            page.page_id as id,
            page.page_name as name,
            page.page_folder as folder_id,
            page.page_style,
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
            
            // The preview frame loads the URL the option carries, built from
            // the page name, never from the option's label; "Edit the design"
            // is offered only on a page the user may edit.
            $page_url = OUTPUT_PATH . encode_url_path($page['name']) . '?edit=no&email=true';
            $page_edit = check_edit_access($page['folder_id']) ? pg_page_edit_url(array('page_id' => $page['id'], 'page_style' => $page['page_style'])) : '';

            if ($selected !== '') {
                $output_preview = $page_url;
                $output_edit_url = $page_edit;
            }

            $output_page_options .= '<option value="' . $page['id'] . '" data-url="' . h($page_url) . '" data-edit="' . h($page_edit) . '"' . $selected . '>' . h($page['name']) . '</option>';
        }
    }
    
    // get all contact groups
    $query =
        "SELECT
           id,
           name,
           email_subscription
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
            // count the contacts with an address and those opted in: both are
            // shown, so a change of purpose needs no reload
            $result_count = db_q(pg_email_campaign_group_count_query($row['email_subscription'] == 1), array((string) $id)) or output_error('Query failed.');
            $counts = mysqli_fetch_assoc($result_count);
            
            // if contact group has at least one contact with an address
            if ((int) $counts['total'] > 0) {
                $output_contact_group_rows .=
                    '<tr>
                        <td id="contact_group_name_' . $id . '">' . pg_email_campaign_group_label($name, $counts['total'], $counts['opted_in']) . '</td>
                        <td class="text-center"><div class="form-field d-inline-grid"><input class="radio" type="radio" name="contact_group_' . $id . '" value="ignored" aria-labelledby="contact_group_name_' . $id . ' contact_group_ignore" checked="checked" /></div></td>
                        <td class="text-center"><div class="form-field d-inline-grid"><input class="radio" type="radio" name="contact_group_' . $id . '" value="included" aria-labelledby="contact_group_name_' . $id . ' contact_group_include" /></div></td>
                        <td class="text-center"><div class="form-field d-inline-grid"><input class="radio" type="radio" name="contact_group_' . $id . '" value="excluded" aria-labelledby="contact_group_name_' . $id . ' contact_group_exclude" /></div></td>
                    </tr>';
            }
        }
    }
    
    // if there is at least one contact group to show, then prepare to output contact groups
    if ($output_contact_group_rows != '') {
        $output_contact_groups =
            '<div class="alert theme-primary"><p>' . lang('Send message to all Subscribers in my selected Contact Groups') . '</p></div>
            <div class="mb-7">
                <table class="table">
                    <thead>
                        <tr>
                            <th scope="col"><span class="visually-hidden">' . lang('Contact Group') . '</span></th>
                            <th id="contact_group_ignore" scope="col" class="text-center">' . lang('Ignore') . '</th>
                            <th id="contact_group_include" scope="col" class="text-center">' . lang('Include') . '</th>
                            <th id="contact_group_exclude" scope="col" class="text-center">' . lang('Exclude') . '</th>
                        </tr>
                    </thead>
                    <tbody>
                    ' . $output_contact_group_rows . '
                    </tbody>
                </table>
            </div>';
    } else {
        $output_contact_groups = '<div class="alert theme-danger"><p>' . lang('You do not have access to any contact groups with subscribers.') . '</p></div>';
    }
    
    print
    
    pg_page_shell(
        array(
            'title'=> lang('Create Campaign'),
            'extra classes'=>'campaign',
            'icon'=>'campaign',
            'heading'=> lang('Create Campaign'),
            'heading_description' => lang('Create a new e-mail campaign to send to all subscribers in selected contact groups.'),
            'cancel'=>array('enable'=>'true','url'=>'view_email_campaigns.php'),
            'breadcrumb' => array(
                array('label' => lang('My Campaigns'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_email_campaigns.php'),
                array('label' => lang('Create Campaign')),
            ),
        )
    ) . '
<main id="content" class="container-fluid">
                <form name="form" action="add_email_campaign.php" method="post" >
                    ' . get_token_field() . '
                    ' . pg_email_campaign_layout(
                        pg_campaign_content_card(array(
                            'mode'         => 'add',
                            'kind'         => $output_body_kind,
                            'page_options' => $output_page_options,
                            'edit_url'     => $output_edit_url,
                            'text'         => $output_body,
                            'preview'      => $output_preview,
                        )) . pg_email_campaign_card(
                            lang('E-Mail Message To My Contact Groups'),
                            '<div class="col-12 my-3">
                                ' . $output_contact_groups . '
                            </div>
                            <div class="col-12 my-3">
                                <div class="alert theme-secondary">
                                    <div class="row align-items-center justify-content-between">
                                        <div class="col-12 md:col-auto">
                                            <label class="form-label md:mb-0" for="entered_email_address">' . lang('Also send message to the following e-mail address') . ':</label>
                                        </div>
                                        <div class="col-12 md:col-6 xl:col-5">
                                            <input type="text" class="form-control text-end" id="entered_email_address" name="entered_email_address" maxlength="100" inputmode="email" data-inputmask-alias="email">
                                        </div>
                                    </div>
                                </div>
                            </div>'
                        ) . pg_email_campaign_from_card(array(
                            'from_name' => ORGANIZATION_NAME,
                            'from_email_address' => $user['email_address'],
                            'reply_email_address' => $user['email_address'],
                        )),
                        pg_email_campaign_schedule_card('') . pg_email_campaign_purpose_card('commercial'),
                        '<button type="submit" id="create_button" name="submit_create" value="Create" class="btn my-1  btn-solid theme-success "><span class="bi bi-plus-circle me-3"></span><span class="pg-btn-label">' . lang(array('string'=>'Create') ) . '</span></button>'
                    ) . '
                </form>
                ' . pg_campaign_content_script() . '
</main>' .
    output_footer();
    
// else form has been submitted
} else {
    validate_token_field();

    // The format and purpose are ones the form offers, and the subject and
    // addresses are checked before anything is stored: a campaign without
    // a sender address only failed later, at every delivery.
    // The body kind names the format: a design and pasted HTML are HTML, a
    // pasted body without a page.
    $body_kind = pg_campaign_content_posted_kind($_POST);
    $_POST['format'] = ($body_kind !== null) ? pg_campaign_content_format($body_kind) : null;
    $_POST['purpose'] = pg_email_campaign_allowed_value('purpose', $_POST['purpose'] ?? null);
    $_POST['page_id'] = (isset($_POST['page_id']) && is_scalar($_POST['page_id'])) ? (string) $_POST['page_id'] : '';

    // The sender name and the body are text: one posted as an array
    // (from_name[]=x) was stored as 'Array'. The sender name goes into the
    // From header, so it is trimmed like the subject and the addresses.
    $_POST['from_name'] = trim(pg_email_campaign_posted_text($_POST, 'from_name'));
    $_POST['body'] = pg_email_campaign_posted_text($_POST, 'body');

    $field_errors = pg_email_campaign_field_errors(array(
        'subject' => $_POST['subject'] ?? '',
        'from_email_address' => $_POST['from_email_address'] ?? '',
        'reply_email_address' => $_POST['reply_email_address'] ?? '',
    ));

    if ($_POST['format'] === null) {
        $field_errors['format'] = lang('Select a format.');
    }

    if ($_POST['purpose'] === null) {
        $field_errors['purpose'] = lang('Select a purpose.');
    }

    if ($field_errors) {
        output_error(pg_email_campaign_field_errors_html($field_errors));
    }

    $_POST['subject'] = trim((string) $_POST['subject']);
    $_POST['from_email_address'] = trim((string) $_POST['from_email_address']);
    $_POST['reply_email_address'] = trim((string) ($_POST['reply_email_address'] ?? ''));

    // Pasted HTML: cleaned by the outgoing mode before anything else, so an
    // empty or refused paste stops here.
    $pasted_html = '';

    if ($body_kind === 'html') {
        $pasted_html = pg_mail_html_clean_outgoing(pg_email_campaign_posted_text($_POST, 'html_body'));

        if ($pasted_html === '') {
            output_error(lang('Paste the HTML of the e-mail: nothing that can be sent was left of it.') . ' <a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.');
        }
    }

    // A plain text or pasted body is stored without a page.
    if ($body_kind !== 'design') {
        $_POST['page_id'] = '0';

    // else the user selected HTML for the format, so do some checks
    } else {
        // if the user did not select a page, then output error
        if ($_POST['page_id'] == '') {
            output_error(lang('Please select a page for the body of the campaign.') . ' <a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.');
        }

        // get folder id for selected page in order to check if user has access to view content in folder
        $query = "SELECT page_folder FROM page WHERE page_id = ?";
        $result = db_q($query, array((string) ($_POST['page_id'] ?? ''))) or output_error('Query failed.');
        $row = mysqli_fetch_assoc($result);

        // A page that does not exist (a stale form, a hand-made post) stored
        // a ready campaign with an empty body.
        if (!$row) {
            output_error(lang('Please select a page for the body of the campaign.') . ' <a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.');
        }

        $folder_id = $row['page_folder'];
        
        // if the user does not have view access to the selected page's folder, then log activity and output error
        if (check_view_access($folder_id) == false) {
            log_activity(lang('access denied for user to send a page for a campaign, because user does not have view access to the page'), $_SESSION['sessionusername']);
            output_error(lang('You are not authorized to send that page for the body of the campaign.') . ' <a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.');
        }
    }
    
    // The included and excluded groups as the form posts them
    // (contact_group_<id>), and the one entered address.
    $groups = array();

    foreach ($_POST as $name => $value) {
        if ((preg_match('/^contact_group_(\d+)$/', (string) $name, $match) === 1) && is_string($value)) {
            $groups[(int) $match[1]] = $value;
        }
    }

    $entered_email_address = is_string($_POST['entered_email_address'] ?? null) ? $_POST['entered_email_address'] : '';

    if (($entered_email_address !== '') && (validate_email_address($entered_email_address) == false)) {
        output_error(lang('The address you entered under Also send message to the following e-mail address is not valid.') . ' <a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.');
    }

    $picked = pg_email_campaign_pick_recipients($user, $_POST['purpose'], $groups, $entered_email_address);

    if ($picked === false) {
        output_error(lang('Query failed.'));
    }

    // if there are no recipients, output error
    if (!$picked['recipients']) {
        output_error(lang('Please select at least one recipient.') . ' <a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.');
    }

    
    // get subject
    $subject = $_POST['subject'];

    // if plain text was selected for the format, then store body in variable
    if ($_POST['format'] == 'plain_text') {
        $body = $_POST['body'];

    // Pasted HTML gets the same base, tracking code and footer as a design.
    } elseif ($body_kind === 'html') {
        $body = pg_email_campaign_prepare_html_body($pasted_html, trim((string) $_POST['subject']));

    // else HTML was selected for the format, so prepare body and store in variable
    } else {
        // The page as its recipients may read it: nothing of the sender's
        // session (token, account widgets) goes into the e-mail.
        $body = pg_email_campaign_page_body($_POST['page_id']);
        
        $body = pg_email_campaign_prepare_html_body($body, get_page_name($_POST['page_id']));
    }

    $email_campaign_id = pg_email_campaign_insert(array(
        'from_name'           => $_POST['from_name'],
        'from_email_address'  => $_POST['from_email_address'],
        'reply_email_address' => $_POST['reply_email_address'],
        'subject'             => $_POST['subject'],
        'format'              => $_POST['format'],
        'body'                => $body,
        'page_id'             => $_POST['page_id'],
        'start_time'          => prepare_form_data_for_input((string) ($_POST['start_time'] ?? ''), 'date and time'),
        'purpose'             => $_POST['purpose'],
        'status'              => 'ready',
    ), $picked, $user['id']);

    if (!$email_campaign_id) {
        output_error(lang('Query failed.'));
    }

    $log_page = '';

    // if a design was chosen, then add page info to log message
    if ($body_kind === 'design') {
        $log_page = ', ' . lang('page') . ': ' . get_page_name($_POST['page_id']);
    }
    
    log_activity(lang(array('string'=>'campaign (subject: {var:1}) was created','vars'=>$_POST['subject'] . $log_page)), $_SESSION['sessionusername']);

    include_once('liveform.class.php');
    $liveform = new liveform('view_email_campaigns');
    
    // if email campaign job is active
    if (email_campaign_job_enabled()) {
        $liveform->add_notice(lang('The campaign has been created, and it will be sent at the scheduled time.'));
        
    // else email campaign job is not active
    } else {
        $liveform->add_notice(lang(array('string'=>'The campaign has been created, and you may {var:1}.','vars'=>'<a href="send_email_campaign.php?id=' . $email_campaign_id . get_token_query_string_field() . '" onclick="window.open(\'send_email_campaign.php?id=' . $email_campaign_id . get_token_query_string_field() . '\', \'\', \'width=450, height=350, resizable=1, scrollbars=0\'); return false;">' . lang('send it now') . '</a>')) );
    }
    
    // forward user to view e-mail campaigns screen
    header('Location: ' . URL_SCHEME . $_SERVER['HTTP_HOST'] . PATH . SOFTWARE_DIRECTORY . '/view_email_campaigns.php');
}