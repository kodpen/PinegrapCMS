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

$liveform = new liveform('view_email_campaigns');

// store all values collected in request to session
foreach ($_REQUEST as $key => $value) {
    // if the value is a string then add it to the session
    // we have to do this check because cookie arrays are sometimes included in the $_REQUEST array,
    // for certain php.ini settings
    if (is_string($value) == TRUE) {
        $_SESSION['software']['view_email_campaigns'][$key] = trim($value);
    }
}

if (defined('EMAIL_CAMPAIGN_JOB') and EMAIL_CAMPAIGN_JOB) {
    $output_subheading_ready_status = lang('scheduled');
} else {
    $output_subheading_ready_status = lang('ready-to-send');
}


// if the sort is not set yet, then default it to empty so that the switch below falls
// through to its default case
if (isset($_SESSION['software']['view_email_campaigns']['sort']) == false) {
    $_SESSION['software']['view_email_campaigns']['sort'] = '';
}

switch (($_SESSION['software']['view_email_campaigns']['sort'] ?? '')) {
    case lang('Profile'):
        $sort_column = 'email_campaign_profiles.name';
        break;

    case lang('Subject'):
        $sort_column = 'email_campaigns.subject';
        break;

    case lang('Page to Send'):
        $sort_column = 'page.page_name';
        break;

    case lang('Scheduled Time'):
        $sort_column = 'email_campaigns.start_time';
        break;

    case lang('Status'):
        $sort_column = 'email_campaigns.status';
        break;

    case lang('Purpose'):
        $sort_column = 'email_campaigns.purpose';
        break;

    case lang('Created'):
        $sort_column = 'email_campaigns.created_timestamp';
        break;

    case lang('Last Modified'):
        $sort_column = 'email_campaigns.last_modified_timestamp';
        break;

    default:
        // If the email campaign job is enabled, then set the default sort column to Scheduled Time
        if (defined('EMAIL_CAMPAIGN_JOB') and EMAIL_CAMPAIGN_JOB) {
            $sort_column = 'email_campaigns.start_time';
            $_SESSION['software']['view_email_campaigns']['sort'] = lang('Scheduled Time');
            $_SESSION['software']['view_email_campaigns']['order'] = 'asc';

        // Otherwise set the default sort column to Last Modified
        } else {
            $sort_column = 'email_campaigns.last_modified_timestamp';
            $_SESSION['software']['view_email_campaigns']['sort'] = lang('Last Modified');
            $_SESSION['software']['view_email_campaigns']['order'] = 'desc';
        }

        break;
}

// if order is not set, set to ascending
if (isset($_SESSION['software']['view_email_campaigns']['order']) == false) {
    $_SESSION['software']['view_email_campaigns']['order'] = 'asc';
}

if (defined('EMAIL_CAMPAIGN_JOB') and EMAIL_CAMPAIGN_JOB) {
    $output_start_time_heading = '<th>' . get_column_heading(lang('Scheduled Time'), ($_SESSION['software']['view_email_campaigns']['sort'] ?? ''), ($_SESSION['software']['view_email_campaigns']['order'] ?? '')) . '</th>';
} else {
    $output_start_time_heading = '';
}

$my_campaigns = 0;
$all_campaigns = 0;

// get all e-mail campaigns
$query =
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
     WHERE
        (email_campaigns.status = 'ready')
        OR (email_campaigns.status = 'paused')
     ORDER BY $sort_column " . escape(($_SESSION['software']['view_email_campaigns']['order'] ?? ''));
$result = mysqli_query(db::$con, $query) or output_error('Query failed.');

$email_campaigns = array();

while ($row = mysqli_fetch_assoc($result)) {
    // Add one to all campaigns
    $all_campaigns++;

    // if user has a role that is greater than user role or if user created e-mail campaign
    if (($user['role'] < 3) || ($row['created_user_id'] == $user['id'])) {
        $email_campaigns[] = $row;

        // Add one to my campaigns
        $my_campaigns++;
    }
}

$output_rows = '';

// if there is at least one result to display
if ($email_campaigns) {

    foreach ($email_campaigns as $email_campaign) {

        // Only filled in when the e-mail campaign job is enabled, but always printed in the row.
        $output_start_time_cell = '';

        $output_link_url = 'edit_email_campaign.php?id=' . $email_campaign['id'] . '&amp;send_to=' . h(escape_javascript(urlencode(REQUEST_URL)));
        
        // if the e-mail campaign job is enabled, then prepare to show start time cell
        if (defined('EMAIL_CAMPAIGN_JOB') and EMAIL_CAMPAIGN_JOB) {
            // if start time was not set, then clear start time
            if ($email_campaign['start_time'] == '0000-00-00 00:00:00') {
                $start_time = '';
            
            // else start time was set, so prepare format for start time
            } else {
                $start_time = get_relative_time(array('timestamp' => strtotime($email_campaign['start_time'])));
            }
            
            $output_start_time_cell = '<td class="align-middle">' . $start_time . '</td>';
        }
        
        // get total number of recipients
        $query = "SELECT COUNT(*) FROM email_recipients WHERE email_campaign_id = '" . $email_campaign['id'] . "'";
        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
        $row = mysqli_fetch_row($result);
        $number_of_email_recipients = $row[0];

        // Set the to value differently based on the campaign type.
        switch ($email_campaign['type']) {
            case 'manual':
                $plural_suffix = '';

                if (($number_of_email_recipients == 0) or ($number_of_email_recipients > 1)) {
                    $plural_suffix = 's';
                }

                $output_to = lang(array('string'=>'{var:1} Contact{suffix:1}','vars'=>number_format($number_of_email_recipients),'suffix'=>$plural_suffix));

                break;
            
            case 'automatic':
                // Set to value to the single recipient's email address for this automatic campaign.
                $output_to = h(db_value("SELECT email_address FROM email_recipients WHERE email_campaign_id = '" . $email_campaign['id'] . "'"));
                break;
        }

        // get total number of complete recipients
        $query = "SELECT COUNT(*) FROM email_recipients WHERE (email_campaign_id = '" . $email_campaign['id'] . "') AND (complete = '1')";
        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
        $row = mysqli_fetch_row($result);
        $number_of_completed_email_recipients = $row[0];

        if ($number_of_email_recipients > 0) {
            $progress_percentage = number_format($number_of_completed_email_recipients / $number_of_email_recipients * 100);
        } else {
            $progress_percentage = '100';
        }

        if ($email_campaign['created_username']) {
            $created_username = $email_campaign['created_username'];
        } else {
            $created_username = '[' . lang('Unknown') . ']';
        }

        if ($email_campaign['last_modified_username']) {
            $last_modified_username = $email_campaign['last_modified_username'];
        } else {
            $last_modified_username = '[' . lang('Unknown') . ']';
        }

        $output_rows .=
            '<tr>
                <td class="select-all align-middle text-start"><input class="form-check-input " type="checkbox" name="email_campaigns[]" value="' . $email_campaign['id'] . '" class="checkbox" /></td>
			    <td class="align-middle text-start">
                    <button type="button" class="m-1 btn-data-control btn btn-outline-primary border-2 " data-loading-content=" " title="' . lang('Edit') . '" onclick="window.location.href=\'' . $output_link_url . '\'"><i class="bi bi-pencil"></i></button>
                    <!--<button type="button" class="m-1 btn-data-control btn btn-outline-danger border-2 " data-loading-content=" " title="' . lang('Delete') . '" ><i class="material-icons">delete</i></button>-->
                </td>
                <td class="align-middle">' . $output_to . '</td>
                <td class="align-middle">' . h($email_campaign['email_campaign_profile_name']) . '</td>
                <td class="align-middle">' . h($email_campaign['subject']) . '</td>
                <td class="align-middle">' . h($email_campaign['page_name']) . '</td>
                ' . $output_start_time_cell . '
                <td class="align-middle">' . get_email_campaign_status_name($email_campaign['status']) . '</td>
                <td class="align-middle">' . $progress_percentage . '% (' . lang(array('string'=>'{var:1} of {var:2}','vars'=>array(number_format($number_of_completed_email_recipients),number_format($number_of_email_recipients)))) . ')</td>
                <td class="align-middle">' . lang(h(ucwords($email_campaign['purpose']))) . '</td>
                <td class="align-middle">' . get_relative_time(array('timestamp' => $email_campaign['created_timestamp'])) . ' ' . lang(array('string'=>'by {var:1}','vars'=>array( h($created_username) ) ) ) . '</td>
                <td class="align-middle">' . get_relative_time(array('timestamp' => $email_campaign['last_modified_timestamp'])) . ' ' . lang(array('string'=>'by {var:1}','vars'=>array( h($last_modified_username) ) ) ) . '</td>
            </tr>';
    }
}

echo
pg_page_shell(
    array(
        'title'=> lang('My Campaigns'),
        'extra classes'=>'campaign',
        'icon'=>'campaign', 
        'heading'=>lang('My Campaigns'),
                
    )
) . '
    <div class="row">
        <div class="col-12">
            ' . $liveform->output_errors() . '
            ' . $liveform->get_warnings() . '
            ' . $liveform->output_notices() . '
           
            <div class="row mb-2  flex-wrap">
                <div class="col-12 text-center text-md-start">
                    <h2 class="d-inline-block " data-bs-content="' . lang(array('string'=>'All {var:1} and paused e-mail campaigns that I can manage.  See My Campaign History for completed and cancelled campaigns.','vars'=>$output_subheading_ready_status)) . '" title="' . lang('My Campaigns') . '">' . lang('My Campaigns') . '</h2>
                    <nav id="button_bar" class="navigation " aria-label="Button Bar">
                        <a class="btn btn-sm btn-primary m-1 " href="add_email_campaign.php" data-loading-content="' . lang(array('string'=>'Loading') ) . '"><span class="bi bi-plus-circle me-2"></span>' . lang(array('string'=>'Create') ) . '</a>
                        <div class="disable_shortcut d-inline-block">
                            <div class=" btn-group btn-group-sm flex-wrap">
                                <a class="btn btn-link link-secondary py-0 m-1" href="view_email_campaign_history.php"><span class="material-icons me-1">history</span>' . lang(array('string'=>'My Campaign History') ) . '</a>
                            </div>
                        </div>
                    </nav>
                </div>
            </div>
            <div class="card my-4">
                <div class="card-body p-0 position-relative">
                    <form name="form"  action="delete_email_campaigns.php" method="post" class="disable_shortcut"> 
                        ' . get_token_field() . '
                        <input type="hidden" name="send_to" value="' . h(REQUEST_URL) . '" />
                        <table class="chart table-hover table " style="width:100%;display:none">
                            <thead>
                                <tr>
                                    <th class="noVis">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" title="' . lang(array('string'=>'Select/Deselect All') ) . '" type="checkbox" id="select_all">
                                        </div>
                                    </th>
                                    <th class="noVis">' . lang(array('string'=>'Action') ) . '</th> 
                                    <th>' . lang('To') . '</th>
                                    <th>' . get_column_heading(lang('Profile'), ($_SESSION['software']['view_email_campaigns']['sort'] ?? ''), ($_SESSION['software']['view_email_campaigns']['order'] ?? '')) . '</th>
                                    <th>' . get_column_heading(lang('Subject'), ($_SESSION['software']['view_email_campaigns']['sort'] ?? ''), ($_SESSION['software']['view_email_campaigns']['order'] ?? '')) . '</th>
                                    <th>' . get_column_heading(lang('Page to Send'), ($_SESSION['software']['view_email_campaigns']['sort'] ?? ''), ($_SESSION['software']['view_email_campaigns']['order'] ?? '')) . '</th>
                                    ' . $output_start_time_heading . '
                                    <th>' . get_column_heading(lang('Status'), ($_SESSION['software']['view_email_campaigns']['sort'] ?? ''), ($_SESSION['software']['view_email_campaigns']['order'] ?? '')) . '</th>
                                    <th>' . lang('Progress (Subscribers)') . '</th>
                                    <th>' . get_column_heading(lang('Purpose'), ($_SESSION['software']['view_email_campaigns']['sort'] ?? ''), ($_SESSION['software']['view_email_campaigns']['order'] ?? '')) . '</th>
                                    <th>' . get_column_heading(lang('Created'), ($_SESSION['software']['view_email_campaigns']['sort'] ?? ''), ($_SESSION['software']['view_email_campaigns']['order'] ?? '')) . '</th>
                                    <th>' . get_column_heading(lang('Last Modified'), ($_SESSION['software']['view_email_campaigns']['sort'] ?? ''), ($_SESSION['software']['view_email_campaigns']['order'] ?? '')) . '</th>
                                </tr>
                            </thead>
                            <tbody>' . $output_rows . '</tbody>
                        </table>
                        <nav class="buttons navigation text-center position-sticky" style="bottom:.5rem;" aria-label="data edit buttons ">
                            <div class="container">
                                <div class=" btn-group btn-group-sm flex-wrap justify-content-center mb-0 enable-on-selected">
                                    <button type="button" value="Delete Selected" class=" btn mb-1 mt-1 btn-danger disabled" data-loading-content="' . lang(array('string'=>'Deleting') ) . '" data-confirm-content="' . lang(array('string'=>'WARNING: Selected {var:1} will be permanently deleted.','vars'=>array(lang('campaigns')))) . '"><span class="material-icons me-2">delete</span>' . lang(array('string'=>'Delete Selected') ) . '</button>
                                </div>
                            </div>
                        </nav>
                    </form>
                </div>
            </div>
        </div>
    </div>
</main>' .
output_footer();
$liveform->remove_form();