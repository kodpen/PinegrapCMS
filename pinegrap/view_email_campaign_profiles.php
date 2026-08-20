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

include_once('liveform.class.php');
$liveform = new liveform('view_email_campaign_profiles');

// store all values collected in request to session
foreach ($_REQUEST as $key => $value) {
    // if the value is a string then add it to the session
    // we have to do this check because cookie arrays are sometimes included in the $_REQUEST array,
    // for certain php.ini settings
    if (is_string($value) == TRUE) {
        $_SESSION['software']['view_email_campaign_profiles'][$key] = trim($value);
    }
}


// if the sort is not set yet, then default it to empty so that the switch below falls
// through to its default case
if (isset($_SESSION['software']['view_email_campaign_profiles']['sort']) == false) {
    $_SESSION['software']['view_email_campaign_profiles']['sort'] = '';
}

switch (($_SESSION['software']['view_email_campaign_profiles']['sort'] ?? '')) {
    case lang('Name'):
        $sort_column = 'email_campaign_profiles.name';
        break;

    case lang('Enabled'):
        $sort_column = 'email_campaign_profiles.enabled';
        break;
        
    case lang('Action'):
        $sort_column = 'email_campaign_profiles.action';
        break;
        
    case lang('Subject'):
        $sort_column = 'email_campaign_profiles.subject';
        break;

    case lang('Page to Send'):
        $sort_column = 'page.page_name';
        break;

    case lang('Purpose'):
        $sort_column = 'email_campaign_profiles.purpose';
        break;

    case lang('Created'):
        $sort_column = 'email_campaign_profiles.created_timestamp';
        break;

    case lang('Last Modified'):
        $sort_column = 'email_campaign_profiles.last_modified_timestamp';
        break;

    default:
        $sort_column = 'email_campaign_profiles.last_modified_timestamp';
        $_SESSION['software']['view_email_campaign_profiles']['sort'] = lang('Last Modified');
        $_SESSION['software']['view_email_campaign_profiles']['order'] = 'desc';
        break;
}

// if order is not set, set to ascending
if (isset($_SESSION['software']['view_email_campaign_profiles']['order']) == false) {
    $_SESSION['software']['view_email_campaign_profiles']['order'] = 'asc';
}

$all_email_campaign_profiles = 0;

// get the total number of email campaign profiles
$query = "SELECT COUNT(*) FROM email_campaign_profiles";
$result = mysqli_query(db::$con, $query) or output_error('Query failed.');
$row = mysqli_fetch_row($result);
$all_email_campaign_profiles = $row[0];

$where = "";


// If user has a user role, then only get profiles that he/she created.
if (USER_ROLE == 3) {
    // If where is blank, then prepare where in a certain way.
    if ($where == '') {
        $where = "WHERE (email_campaign_profiles.created_user_id = '" . USER_ID . "')";

    // Otherwise where is not blank, so prepare where in a different way.
    } else {
        $where .= "AND (email_campaign_profiles.created_user_id = '" . USER_ID . "')";
    }
}

// If user requested to export, then export them.
if (isset($_GET['export'])) {
    header('Content-type: text/csv');
    header('Content-disposition: attachment; filename=campaign_profiles.csv');

    // Output column headings for CSV data.
    echo
        '"name",' .
        '"enabled",' .
        '"action",' .
        '"action_item",' .
        '"action_item_id",' .
        '"subject",' .
        '"format",' .
        '"body",' .
        '"page_name",' .
        '"page_id",' .
        '"from_name",' .
        '"from_email_address",' .
        '"reply_email_address",' .
        '"bcc_email_address",' .
        '"schedule_length",' .
        '"schedule_unit",' .
        '"schedule_period",' .
        '"schedule_base",' .
        '"schedule_time",' .
        '"purpose",' .
        '"created",' .
        '"created_username",' .
        '"last_modified",' .
        '"last_modified_username"' . "\n";

    // Get all campaign profiles.
    $email_campaign_profiles = db_items(
        "SELECT
            email_campaign_profiles.name,
            email_campaign_profiles.enabled,
            email_campaign_profiles.action,
            email_campaign_profiles.action_item_id,
            email_campaign_profiles.subject,
            email_campaign_profiles.format,
            email_campaign_profiles.body,
            email_campaign_profiles.page_id,
            email_campaign_profiles.from_name,
            email_campaign_profiles.from_email_address,
            email_campaign_profiles.reply_email_address,
            email_campaign_profiles.bcc_email_address,
            email_campaign_profiles.schedule_time,
            email_campaign_profiles.schedule_length,
            email_campaign_profiles.schedule_unit,
            email_campaign_profiles.schedule_period,
            email_campaign_profiles.schedule_base,
            email_campaign_profiles.purpose,
            email_campaign_profiles.created_timestamp,
            created_user.user_username AS created_username,
            email_campaign_profiles.last_modified_timestamp,
            last_modified_user.user_username AS last_modified_username
        FROM email_campaign_profiles
        LEFT JOIN page ON email_campaign_profiles.page_id = page.page_id
        LEFT JOIN user AS created_user ON email_campaign_profiles.created_user_id = created_user.user_id
        LEFT JOIN user AS last_modified_user ON email_campaign_profiles.last_modified_user_id = last_modified_user.user_id
        $where
        ORDER BY $sort_column " . e(($_SESSION['software']['view_email_campaign_profiles']['order'] ?? '')));

    $date_format_code = get_date_format_code();

    // Loop through the campaign profiles in order to output CSV data.
    foreach ($email_campaign_profiles as $email_campaign_profile) {
        $action_item = '';

        switch ($email_campaign_profile['action']) {
            case 'calendar_event_reserved':
                $action_item = db_value("SELECT name FROM calendar_events WHERE id = '" . $email_campaign_profile['action_item_id'] . "'");
                break;

            case 'custom_form_submitted':
                $action_item = db_value("SELECT page_name FROM page WHERE page_id = '" . $email_campaign_profile['action_item_id'] . "'");
                break;

            case 'email_campaign_sent':
                $action_item = db_value("SELECT name FROM email_campaign_profiles WHERE id = '" . $email_campaign_profile['action_item_id'] . "'");
                break;

            case 'product_ordered':
                $product = db_item(
                    "SELECT
                        name,
                        short_description
                    FROM products
                    WHERE id = '" . $email_campaign_profile['action_item_id'] . "'");

                if ($product['name'] != '') {
                    $action_item .= $product['name'];
                }

                if (($product['short_description'] != '') && ($product['short_description'] != $product['name'])) {
                    if ($action_item != '') {
                        $action_item .= ' - ';
                    }

                    $action_item .= $product['short_description'];
                }

                break;
        }

        $page_name = '';

        // If the format is "html", then prepare values for that.
        if ($email_campaign_profile['format'] == 'html') {
            $email_campaign_profile['body'] = '';

            $page_name = db_value("SELECT page_name FROM page WHERE page_id = '" . $email_campaign_profile['page_id'] . "'");

        // Otherwise the format is "plain_text", so prepare values for that.
        } else {
            $email_campaign_profile['page_id'] = '';
        }

        $schedule_time = '';

        if ($email_campaign_profile['schedule_time'] != '00:00:00') {
            $schedule_time = prepare_form_data_for_output($email_campaign_profile['schedule_time'], 'time');
        }

        echo
            '"' . escape_csv($email_campaign_profile['name']) . '",' .
            '"' . $email_campaign_profile['enabled'] . '",' .
            '"' . $email_campaign_profile['action'] . '",' .
            '"' . escape_csv($action_item) . '",' .
            '"' . $email_campaign_profile['action_item_id'] . '",' .
            '"' . escape_csv($email_campaign_profile['subject']) . '",' .
            '"' . $email_campaign_profile['format'] . '",' .
            '"' . escape_csv($email_campaign_profile['body']) . '",' .
            '"' . escape_csv($page_name) . '",' .
            '"' . $email_campaign_profile['page_id'] . '",' .
            '"' . escape_csv($email_campaign_profile['from_name']) . '",' .
            '"' . escape_csv($email_campaign_profile['from_email_address']) . '",' .
            '"' . escape_csv($email_campaign_profile['reply_email_address']) . '",' .
            '"' . escape_csv($email_campaign_profile['bcc_email_address']) . '",' .
            '"' . $email_campaign_profile['schedule_length'] . '",' .
            '"' . $email_campaign_profile['schedule_unit'] . '",' .
            '"' . $email_campaign_profile['schedule_period'] . '",' .
            '"' . $email_campaign_profile['schedule_base'] . '",' .
            '"' . $schedule_time . '",' .
            '"' . $email_campaign_profile['purpose'] . '",' .
            '"' . date($date_format_code . '/Y g:i:s A T', $email_campaign_profile['created_timestamp']) . '",' .
            '"' . escape_csv($email_campaign_profile['created_username']) . '",' .
            '"' . date($date_format_code . '/Y g:i:s A T', $email_campaign_profile['last_modified_timestamp']) . '",' .
            '"' . escape_csv($email_campaign_profile['last_modified_username']) . '"' . "\n";
    }

    exit;

// Otherwise the user did not select to export, so just list the campaign profiles.
} else {

    // Get all email campaign profiles.
    $query =
        "SELECT
            email_campaign_profiles.id,
            email_campaign_profiles.name,
            email_campaign_profiles.enabled,
            email_campaign_profiles.action,
            email_campaign_profiles.action_item_id,
            email_campaign_profiles.subject,
            email_campaign_profiles.format,
            page.page_name,
            email_campaign_profiles.schedule_time,
            email_campaign_profiles.schedule_length,
            email_campaign_profiles.schedule_unit,
            email_campaign_profiles.schedule_period,
            email_campaign_profiles.schedule_base,
            email_campaign_profiles.purpose,
            created_user.user_username AS created_username,
            email_campaign_profiles.created_timestamp,
            last_modified_user.user_username AS last_modified_username,
            email_campaign_profiles.last_modified_timestamp
        FROM email_campaign_profiles
        LEFT JOIN page ON email_campaign_profiles.page_id = page.page_id
        LEFT JOIN user AS created_user ON email_campaign_profiles.created_user_id = created_user.user_id
        LEFT JOIN user AS last_modified_user ON email_campaign_profiles.last_modified_user_id = last_modified_user.user_id
        $where
        ORDER BY $sort_column " . escape(($_SESSION['software']['view_email_campaign_profiles']['order'] ?? ''));
    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
    $email_campaign_profiles = mysqli_fetch_items($result);

    $output_rows = '';

    // if there is at least one result to display
    if ($email_campaign_profiles) {

        foreach ($email_campaign_profiles as $email_campaign_profile) {
            // If this profile is enabled, then use green color for name.
            if ($email_campaign_profile['enabled'] == 1) {
                $output_name_color_class = 'text-success';
            
            // Otherwise this profile is disabled, so use red color for name.
            } else {
                $output_name_color_class = 'text-danger';
            }

            $output_enabled_check_mark = '';

            // If this profile is enabled, then output check mark.
            if ($email_campaign_profile['enabled'] == 1) {
                $output_enabled_check_mark = '<span class="material-icons">task_alt</span>';
            }

            switch ($email_campaign_profile['action']) {
                case 'calendar_event_reserved':
                    $output_action = lang('Calendar Event Reserved');
                    $output_action_item = h(db_value("SELECT name FROM calendar_events WHERE id = '" . $email_campaign_profile['action_item_id'] . "'"));
                    break;

                case 'custom_form_submitted':
                    $output_action = lang('Custom Form Submitted');
                    $output_action_item = h(db_value("SELECT page_name FROM page WHERE page_id = '" . $email_campaign_profile['action_item_id'] . "'"));
                    break;

                case 'email_campaign_sent':
                    $output_action = lang('Auto Campaign Sent');
                    $output_action_item = h(db_value("SELECT name FROM email_campaign_profiles WHERE id = '" . $email_campaign_profile['action_item_id'] . "'"));
                    break;

                case 'order_abandoned':
                    $output_action = lang('Order Abandoned');
                    $output_action_item = '';
                    break;

                case 'order_completed':
                    $output_action = lang('Order Completed');
                    $output_action_item = '';
                    break;

                case 'order_shipped':
                    $output_action = lang('Order Shipped');
                    $output_action_item = '';
                    break;

                case 'product_ordered':
                    $output_action = lang('Product Ordered');

                    $product = db_item(
                        "SELECT
                            name,
                            short_description
                        FROM products
                        WHERE id = '" . $email_campaign_profile['action_item_id'] . "'");

                    $output_action_item = '';

                    if ($product['name'] != '') {
                        $output_action_item .= h($product['name']);
                    }

                    if (($product['short_description'] != '') && ($product['short_description'] != $product['name'])) {
                        if ($output_action_item != '') {
                            $output_action_item .= ' - ';
                        }

                        $output_action_item .= h($product['short_description']);
                    }

                    break;
            }

            $output_page_name = '';

            // If the format is "html", then prepare page name.
            if ($email_campaign_profile['format'] == 'html') {
                $output_page_name = h($email_campaign_profile['page_name']);
            }

            $output_schedule = '';
            $output_schedule_time_title = '';

            // If the length is 0, then use "immediately" terminology.
            if ($email_campaign_profile['schedule_length'] == 0) {
                $output_schedule_time_title = ' ' . lang('immediately') .' ';

            // Otherwise the length is greater than 0, so prepare content for that.
            } else {
                $output_schedule_time_title = $email_campaign_profile['schedule_length'] . ' ';

                // If the unit is days then prepare to output unit is a certain way.
                if ($email_campaign_profile['schedule_unit'] == 'days') {
                    // If the length is 1 the use singular unit.
                    if ($email_campaign_profile['schedule_length'] == 1) {
                        $output_schedule_time_title .= ' ' . lang('day') .' ';

                    // Otherwise the length is 0 or more than 1, so output plural unit.
                    } else {
                        $output_schedule_time_title .= ' ' . lang('days') .' ';
                    }

                // Otherwise the unit is hours, so prepare to output unit in a different way.
                } else {
                    // If the length is 1 the use singular unit.
                    if ($email_campaign_profile['schedule_length'] == 1) {
                        $output_schedule_time_title .= ' ' . lang('hour') .' ';

                    // Otherwise the length is 0 or more than 1, so output plural unit.
                    } else {
                        $output_schedule_time_title .= ' ' . lang('hours') .' ';
                    }
                }
            }

            $output_schedule_after_before = lang($email_campaign_profile['schedule_period']) . ' ';

            // If the base is action, then prepare to output that.
            if ($email_campaign_profile['schedule_base'] == 'action') {
                $output_schedule_action = ' ' . lang('from action') .' ';

            // Otherwise the base is calendar event start time, so output that.
            } else {
                $output_schedule_action = ' ' . lang('from calendar event start time') .' '; 
            }

            // If there is a time, then add it to schedule.
            if ($email_campaign_profile['schedule_time'] != '00:00:00') {
                $output_schedule_action .= ' (' . prepare_form_data_for_output($email_campaign_profile['schedule_time'], 'time') . ')';
            }

          

            $output_schedule = lang(array('string'=>'Schedule: {var:1}{var:2}{var:3}','vars'=>array($output_schedule_time_title,$output_schedule_after_before,$output_schedule_action) ));
            



            $created_username = '';
            
            if ($email_campaign_profile['created_username']) {
                $created_username = lang(array('string'=>'by {var:1}','vars'=>array( h($email_campaign_profile['created_username']) ) ) );
            }
            
            $last_modified_username = '';
            
            if ($email_campaign_profile['last_modified_username']) {
                $last_modified_username = lang(array('string'=>'by {var:1}','vars'=>array( h($email_campaign_profile['last_modified_username']) ) ) );
            }

            $output_rows .=
            '<tr class="unselectable">
                <td class="select-all align-middle text-start"><input class="form-check-input disabled" disabled="" type="checkbox" name="fake_checkbox[]" value="1"></td>
			    <td class="align-middle text-start">
                    <button type="button" class="m-1 btn-data-control btn btn-outline-primary border-2 " data-loading-content=" " title="' . lang('Edit') . '" onclick="window.location.href=\'edit_email_campaign_profile.php?id=' . $email_campaign_profile['id'] . '\'"><i class="bi bi-pencil"></i></button>
                    <!--<button type="button" class="m-1 btn-data-control btn btn-outline-danger border-2 " data-loading-content=" " title="' . lang('Delete') . '" ><i class="material-icons">delete</i></button>-->
                </td>
                <td class="chart_label ' . $output_name_color_class . '">' . h($email_campaign_profile['name']) . '</td>
                <td class="align-middle text-center ' . $output_name_color_class . '">' . $output_enabled_check_mark . '</td>
                <td class="align-middle"><span class="badge bg-primary fw-light">' . $output_action . '</span></td>
                <td class="align-middle">' . $output_action_item . '</td>
                <td class="align-middle">' . h($email_campaign_profile['subject']) . '</td>
                <td class="align-middle">' . $output_page_name . '</td>
                <td class="align-middle"><span class="badge border border-1 border-dark fw-light bg-secondary-subtle text-secondary-emphasis">' . $output_schedule . '</span></td>
                <td class="align-middle">' . lang(h(ucwords($email_campaign_profile['purpose']))) . '</td>
                <td class="align-middle">' . get_relative_time(array('timestamp' => $email_campaign_profile['created_timestamp'])) . ' ' . $created_username . '</td>
                <td class="align-middle">' . get_relative_time(array('timestamp' => $email_campaign_profile['last_modified_timestamp'])) . ' ' . $last_modified_username . '</td>
            </tr>';
        }
    }

    print
    pg_page_shell(
        array(
            'title'=> lang('My Campaign Profiles'),
            'extra classes'=>'campaign',
            'icon'=>'campaign', 
            'heading'=> lang('My Campaign Profiles'),
            'cancel'=>array('enable'=>'true','url'=>'view_email_campaigns.php')
        )
    )    . '
            <div class="row">
            <div class="col-12">
                ' . $liveform->output_errors() . '
                ' . $liveform->get_warnings() . '
                ' . $liveform->output_notices() . '
               
                <div class="row mb-2 flex-wrap">
                    <div class="col-12 col-sm-12 col-md-6 col-xl-9 text-center text-md-start">
                        <h2 class="d-inline-block " data-bs-content="' . lang('Use Campaign Profiles to schedule e-mails automatically for certain actions (e.g. Calendar Event reservation).') . '" title="' . lang('My Campaign Profiles') . '">' . lang('My Campaign Profiles') . '</h2>
                        <nav id="button_bar" class="navigation " aria-label="Button Bar">
                        <a class="btn btn-sm btn-primary m-1 " href="add_email_campaign_profile.php" data-loading-content="' . lang(array('string'=>'Loading') ) . '"><span class="bi bi-plus-circle me-2"></span>' . lang(array('string'=>'Create') ) . '</a>
                            <form class="disable_shortcut d-inline-block" method="get">
                                <div class=" btn-group btn-group-sm flex-wrap">
                                <a class="btn btn-link link-secondary py-0 m-1" href="import_email_campaign_profiles.php"><span class="bi bi-box-arrow-in-right me-1"></span>' . lang(array('string'=>'Import') ) . '</a>
                                <button type="submit" name="export" value="Export" class="btn btn-link link-secondary py-0 m-1"><span class="bi bi-file-earmark-arrow-down bi-me-2"></span>' . lang(array('string'=>'Export') ) . '</button>
                                </div>
                            </form>
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
                                                <input class="form-check-input disabled" disabled title="' . lang(array('string'=>'Select/Deselect All') ) . '" type="checkbox" id="select_all">
                                            </div>
                                        </th>
                                        <th class="noVis">' . lang(array('string'=>'Action') ) . '</th> 
                                        <th>' . get_column_heading(lang('Name'), ($_SESSION['software']['view_email_campaign_profiles']['sort'] ?? ''), ($_SESSION['software']['view_email_campaign_profiles']['order'] ?? '')) . '</th>
                                        <th class="text-center">' . get_column_heading(lang('Enabled'), ($_SESSION['software']['view_email_campaign_profiles']['sort'] ?? ''), ($_SESSION['software']['view_email_campaign_profiles']['order'] ?? '')) . '</th>
                                        <th>' . get_column_heading(lang('Action'), ($_SESSION['software']['view_email_campaign_profiles']['sort'] ?? ''), ($_SESSION['software']['view_email_campaign_profiles']['order'] ?? '')) . '</th>
                                        <th>' . lang('Action Item') . '</th>
                                        <th>' . get_column_heading(lang('Subject'), ($_SESSION['software']['view_email_campaign_profiles']['sort'] ?? ''), ($_SESSION['software']['view_email_campaign_profiles']['order'] ?? '')) . '</th>
                                        <th>' . get_column_heading(lang('Page to Send'), ($_SESSION['software']['view_email_campaign_profiles']['sort'] ?? ''), ($_SESSION['software']['view_email_campaign_profiles']['order'] ?? '')) . '</th>
                                        <th>' . lang('Schedule') . '</th>
                                        <th>' . get_column_heading(lang('Purpose'), ($_SESSION['software']['view_email_campaign_profiles']['sort'] ?? ''), ($_SESSION['software']['view_email_campaign_profiles']['order'] ?? '')) . '</th>
                                        <th>' . get_column_heading(lang('Created'), ($_SESSION['software']['view_email_campaign_profiles']['sort'] ?? ''), ($_SESSION['software']['view_email_campaign_profiles']['order'] ?? '')) . '</th>
                                        <th>' . get_column_heading(lang('Last Modified'), ($_SESSION['software']['view_email_campaign_profiles']['sort'] ?? ''), ($_SESSION['software']['view_email_campaign_profiles']['order'] ?? '')) . '</th>
                                    </tr>
                                </thead>
                                <tbody>' . $output_rows . '</tbody>
                            </table>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </main>' .
    output_footer();
    $liveform->remove_form();
}
?>