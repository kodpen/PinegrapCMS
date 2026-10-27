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

$user = validate_user();
validate_email_access($user);

$liveform = new liveform('view_email_campaign_history');

// The columns this list sorts by, in the order the table shows them. The
// Scheduled Time column is shown only while the e-mail campaign job is on,
// so only then is it offered; pg_list_state() falls back to the default for
// anything else and keeps the choice in the session as a code, so it
// survives a language change.
$sort_columns = array(
    'profile'      => lang('Profile'),
    'subject'      => lang('Subject'),
    'page_to_send' => lang('Page to Send'),
);

if (email_campaign_job_enabled()) {
    $sort_columns['scheduled_time'] = lang('Scheduled Time');
}

$sort_columns['status'] = lang('Status');
$sort_columns['purpose'] = lang('Purpose');
$sort_columns['created'] = lang('Created');
$sort_columns['last_modified'] = lang('Last Modified');

// With the job on, campaigns are listed by scheduled time, earliest first;
// otherwise by the last change, newest first.
if (email_campaign_job_enabled()) {
    $sort_default = array('sort' => 'scheduled_time', 'order' => 'asc');
} else {
    $sort_default = array('sort' => 'last_modified', 'order' => 'desc');
}

$list_state = pg_list_state('view_email_campaign_history', $sort_columns, $sort_default);
$sort_levels = $list_state['sorts'];

// What each sort code orders by. Only codes pg_list_state() let through
// reach pg_list_order_by(), and it writes nothing but these.
$order_by = pg_list_order_by($sort_levels, array(
    'profile'        => 'email_campaign_profiles.name',
    'subject'        => 'email_campaigns.subject',
    'page_to_send'   => 'page.page_name',
    'scheduled_time' => 'email_campaigns.start_time',
    'status'         => 'email_campaigns.status',
    'purpose'        => 'email_campaigns.purpose',
    'created'        => 'email_campaigns.created_timestamp',
    'last_modified'  => 'email_campaigns.last_modified_timestamp',
));

if (email_campaign_job_enabled()) {
    $output_start_time_heading = '<th>' . get_column_heading(lang('Scheduled Time'), $sort_levels, '', '', 'scheduled_time') . '</th>';
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
        (email_campaigns.status = 'cancelled')
        OR (email_campaigns.status = 'complete')
     ORDER BY $order_by";
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
        $output_link_url = 'edit_email_campaign.php?id=' . $email_campaign['id'] . '&amp;send_to=' . h(escape_javascript(urlencode(REQUEST_URL)));
        
        $output_start_time_cell = '';
        
        // if the e-mail campaign job is enabled, then prepare to show start time cell
        if (email_campaign_job_enabled()) {
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
        $query = "SELECT COUNT(*) FROM email_recipients WHERE email_campaign_id = ?";
        $result = db_q($query, array((string) $email_campaign['id'])) or output_error('Query failed.');
        $row = mysqli_fetch_row($result);
        $number_of_email_recipients = $row[0];

        // Set the to value differently based on the campaign type.
        switch ($email_campaign['type']) {
            case 'manual':
                $plural_suffix = '';
            
                if (($number_of_email_recipients == 0) or ($number_of_email_recipients > 1)) {
                    $plural_suffix = 's';
                }
            
                $output_to = lang(array('string'=>'{var:1} Contact{suffix:1}','vars'=>pg_format_number($number_of_email_recipients, 0),'suffix'=>$plural_suffix));
            
                break;
            
            case 'automatic':
                // Set to value to the single recipient's email address for this automatic campaign.
                $output_to = h(db_value("SELECT email_address FROM email_recipients WHERE email_campaign_id = '" . $email_campaign['id'] . "'"));
                break;
        }

        // get total number of complete recipients
        $query = "SELECT COUNT(*) FROM email_recipients WHERE (email_campaign_id = ?) AND (complete = '1')";
        $result = db_q($query, array((string) $email_campaign['id'])) or output_error('Query failed.');
        $row = mysqli_fetch_row($result);
        $number_of_completed_email_recipients = $row[0];

        if ($number_of_email_recipients > 0) {
            $progress_percentage = pg_format_number($number_of_completed_email_recipients / $number_of_email_recipients * 100, 0);
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
            <td class="select-all align-middle text-start"><input class="check" type="checkbox" name="email_campaigns[]" value="' . $email_campaign['id'] . '" class="checkbox" /></td>
            <td class="align-middle text-start">
                <button type="button" class="m-1 btn-data-control btn btn-outline theme-primary border-2 " title="' . lang('Edit') . '" onclick="window.location.href=\'' . $output_link_url . '\'"><i class="bi bi-pencil"></i></button>
                <!--<button type="button" class="m-1 btn-data-control btn btn-outline theme-danger border-2 " title="' . lang('Delete') . '" ><i class="material-icons">delete</i></button>-->
            </td>
            <td class="align-middle">' . $output_to . '</td>
            <td class="align-middle">' . h($email_campaign['email_campaign_profile_name']) . '</td>
            <td class="align-middle">' . h($email_campaign['subject']) . '</td>
            <td class="align-middle">' . h($email_campaign['page_name']) . '</td>
            ' . $output_start_time_cell . '
            <td class="align-middle">' . get_email_campaign_status_name($email_campaign['status']) . '</td>
            <td class="align-middle">' . $progress_percentage . '% (' . lang(array('string'=>'{var:1} of {var:2}','vars'=>array(pg_format_number($number_of_completed_email_recipients, 0),pg_format_number($number_of_email_recipients, 0)))) . ')</td>
            <td class="align-middle">' . lang(h(ucwords($email_campaign['purpose']))) . '</td>
            <td class="align-middle">' . get_relative_time(array('timestamp' => $email_campaign['created_timestamp'])) . ' ' . lang(array('string'=>'by {var:1}','vars'=>array( h($created_username) ) ) ) . '</td>
            <td class="align-middle">' . get_relative_time(array('timestamp' => $email_campaign['last_modified_timestamp'])) . ' ' . lang(array('string'=>'by {var:1}','vars'=>array( h($last_modified_username) ) ) ) . '</td>
        </tr>';
    }
}

echo
pg_page_shell(
    array(
        'title'=> lang('My Campaign History'),
        'extra classes'=>'campaign',
        'icon'=>'campaign', 
        'heading'=>lang('My Campaign History'),
        'heading_description' => lang('All completed and cancelled e-mail campaigns that I can manage.'),
                
    )
)  . '
<main id="content" class="container-fluid">
            <div class="row">
            <div class="col-12">
                ' . $liveform->output_errors() . '
                ' . $liveform->get_warnings() . '
                ' . $liveform->output_notices() . '
               
                <div class="row mb-3  flex-wrap">
                    <div class="col-12 text-center md:text-start">
                        
                        <nav id="button_bar" class="navigation " aria-label="Button Bar">
                            <a class="btn btn-sm btn-solid theme-primary m-1 "  href="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY .'/add_email_campaign.php"><span class="bi bi-plus-circle me-3"></span>' . lang(array('string'=>'Create') ) . '</a>
                            <div class="disable_shortcut d-inline-block">
                                <div class=" btn-group btn-group-sm flex-wrap">
                                    <a class="btn btn-link pg-link-secondary py-0 m-1" href="view_email_campaigns.php"><span class="material-icons me-1">campaign</span>' . lang(array('string'=>'My Campaigns') ) . '</a>
                                </div>
                            </div>
                            <div class="pg-toolbar-grow"></div>
                            ' . pg_list_sort_menu('view_email_campaign_history.php', $sort_columns, $sort_levels, $sort_default) . '
                        </nav>
                    </div>
                </div>
                <div class="card my-7">
                    <div class="card-body p-0 position-relative">
                        <form name="form" action="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/delete_email_campaigns.php" method="post" class="disable_shortcut"> 
                            ' . get_token_field() . '
                            <input type="hidden" name="send_to" value="' . h(REQUEST_URL) . '" />
                            <table class="chart table-hover table " style="width:100%;display:none">
                                <thead>
                                    <tr>
                                        <th class="noVis">
                                            <div class="form-field">
                                                <div class="switch"><input class="" title="' . lang(array('string'=>'Select/Deselect All') ) . '" type="checkbox" id="select_all"></div>
                                            </div>
                                        </th>
                                        <th class="noVis">' . lang(array('string'=>'Action') ) . '</th> 
                                        <th>' . lang('To') . '</th>
                                        <th>' . get_column_heading(lang('Profile'), $sort_levels, '', '', 'profile') . '</th>
                                        <th>' . get_column_heading(lang('Subject'), $sort_levels, '', '', 'subject') . '</th>
                                        <th>' . get_column_heading(lang('Page to Send'), $sort_levels, '', '', 'page_to_send') . '</th>
                                        ' . $output_start_time_heading . '
                                        <th>' . get_column_heading(lang('Status'), $sort_levels, '', '', 'status') . '</th>
                                        <th>' . lang('Progress (Subscribers)') . '</th>
                                        <th>' . get_column_heading(lang('Purpose'), $sort_levels, '', '', 'purpose') . '</th>
                                        <th>' . get_column_heading(lang('Created'), $sort_levels, '', '', 'created') . '</th>
                                        <th>' . get_column_heading(lang('Last Modified'), $sort_levels, '', '', 'last_modified') . '</th>
                                    </tr>
                                </thead>
                                <tbody>' . $output_rows . '</tbody>
                            </table>
                            <nav class="buttons navigation text-center position-sticky" style="bottom:.5rem;" aria-label="data edit buttons ">
                                <div class="container">
                                    <div class=" btn-group btn-group-sm flex-wrap justify-content-center mb-0 enable-on-selected">
                                        <button type="button" value="Delete Selected" class=" btn mb-1 mt-1 btn-solid theme-danger disabled" data-confirm-content="' . lang(array('string'=>'WARNING: Selected {var:1} will be permanently deleted.','vars'=>array(lang('campaigns')))) . '"><span class="material-icons me-3">delete</span>' . lang(array('string'=>'Delete Selected') ) . '</button>
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