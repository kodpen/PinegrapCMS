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

$liveform = new liveform('view_users');

$user = validate_user();
validate_area_access($user, 'manager');

$output_clear_button = '';

// These are only ever appended to, or are set only for some of the filters below, so they have
// to start out empty.
$where = '';
$sql_select_column = '';
$output_rows = '';
$output_email_address_column = '';
$output_user_start_page_column = '';
$output_user_contact_column = '';
$output_last_modified_column = '';
$output_email_address_column_header = '';
$output_user_start_page_column_heading = '';
$output_user_contact_column_header = '';
$output_last_modified_column_header = '';

// If there is a filter set.
if (isset($_GET['filter']) == true) {
    // Send the filter to the search form.
    $filter = $_GET['filter'];
} else {
    $filter = 'default';
}

$filter_for_links = '&filter=' . $filter;
$output_filter_for_links = h($filter_for_links);

// build filters array
$filters_in_array = 
    array(
        'all_my_users'=>lang('All My Users'),
        'my_registered_users'=>lang('My Registered Users'),
        'my_private_users'=>lang('My Private Users'),
        'my_member_users'=>lang('My Member Users'),
        'my_content_managers'=>lang('My Content Managers'),
        'my_calendar_managers'=>lang('My Calendar Managers'),
        'my_submitted_forms_managers'=>lang('My Submitted Forms Managers'),
        'my_visitor_report_managers'=>lang('My Visitor Report Managers'),
        'my_contact_managers'=>lang('My Contact Managers'),
        'my_campaign_managers'=>lang('My Campaign Managers'),
        'my_commerce_managers'=>lang('My Commerce Managers')
    );

// if user is site administrator then output the site administrator filter.
if ($user['role'] < 1) {
    $filters_in_array['all_site_designers'] = lang('All Site Designers');
    $filters_in_array['all_site_administrators'] = lang('All Site Administrators');
}

// if user is site designer then output the site designer filter.
if ($user['role'] < 2) {
    $filters_in_array['all_site_managers'] = lang('All Site Managers');
}

// if sort was set, update session
if (isset($_REQUEST['sort'])) {
    // store sort in session
    $_SESSION['software']['view_users']['sort'] = $_REQUEST['sort'];

    // clear order
    $_SESSION['software']['view_users']['order'] = '';
}

// if order was set, update session
if (isset($_REQUEST['order'])) {
    $_SESSION['software']['view_users']['order'] = $_REQUEST['order'];
}

// If the filter is not default or all my users view.
if ($filter == 'default') {
    
    // If sort value is one of the following set sort value to default.
    // if the sort is not set yet, then default it to empty so that the switch below falls
    // through to its default case
    if (isset($_SESSION['software']['view_users']['sort']) == false) {
        $_SESSION['software']['view_users']['sort'] = '';
    }

    switch (($_SESSION['software']['view_users']['sort'] ?? '')) {
        case 'E-mail Address':
        case 'Start Page':
        case 'Last Modified':
            $_SESSION['software']['view_users']['sort'] = 'Last Modified';
            $_SESSION['software']['view_users']['order'] = 'desc';
            break;
    }
    
    // Output column headers.
    $output_user_role_column_heading = '<th>' . get_column_heading(lang('Role'), ($_SESSION['software']['view_users']['sort'] ?? ''), ($_SESSION['software']['view_users']['order'] ?? ''), $output_filter_for_links) . '</th>';
    $output_private_folder_access_column_heading = '<th style="text-align: center">' . lang('Private User') . '</th>';
    $output_member_user_column_heading = '<th style="text-align: center">' . get_column_heading(lang('Member User'), ($_SESSION['software']['view_users']['sort'] ?? ''), ($_SESSION['software']['view_users']['order'] ?? ''), $output_filter_for_links) . '</th>';
    $output_manage_content_column_heading = '<th style="text-align: center">' . lang('Content Manager') . '</th>';
    $output_manage_calendars_column_heading = '<th style="text-align: center">' . lang('Calendar Manager') . '</th>';
   
    // if forms module is on
    if (FORMS === true) {
        $output_manage_forms_column_header = '<th style="text-align: center">' . lang('Forms Manager') . '</th>';
    }
    
    $output_view_visitors_column_header = '<th style="text-align: center">' . lang('Visitor Manager') . '</th>';
    $output_manage_contacts_column_header = '<th style="text-align: center">' . lang('Contact Manager') . '</th>';
    $output_manage_email_column_header = '<th style="text-align: center">' . lang('Campaign Manager') . '</th>';
    
    // If ecommerce module is on
    if (ECOMMERCE === true) {
        $output_manage_ecommerce_column_header = '<th style="text-align: center">' . lang('Commerce Manager') . '</th>';
    }
    
    $output_manage_users_column_header = '<th style="text-align: center">' . get_column_heading(lang('Site Manager'), ($_SESSION['software']['view_users']['sort'] ?? ''), ($_SESSION['software']['view_users']['order'] ?? ''), $output_filter_for_links) . '</th>';
    $output_edit_design_column_header = '<th style="text-align: center">' . get_column_heading(lang('Site Designer'), ($_SESSION['software']['view_users']['sort'] ?? ''), ($_SESSION['software']['view_users']['order'] ?? ''), $output_filter_for_links) . '</th>';
    
// Else if the user is not on the all users view.
} else {
    
    // If sort value is one of the following set sort value to default.
    // if the sort is not set yet, then default it to empty so that the switch below falls
    // through to its default case
    if (isset($_SESSION['software']['view_users']['sort']) == false) {
        $_SESSION['software']['view_users']['sort'] = '';
    }

    switch (($_SESSION['software']['view_users']['sort'] ?? '')) {
        case lang('Role'):
        case lang('Member User'):
        case lang('Site Manager'):
        case lang('Site Designer'):
            $_SESSION['software']['view_users']['sort'] = lang('Last Modified');
            $_SESSION['software']['view_users']['order'] = 'desc';
            break;
    }
    
    // Select table column
    $sql_select_column .= ',
        user.user_home';
    
    // Output column headers
    $output_email_address_column_header = '<th>' . get_column_heading(lang('E-mail Address'), ($_SESSION['software']['view_users']['sort'] ?? ''), ($_SESSION['software']['view_users']['order'] ?? ''), $output_filter_for_links) . '</th>';
    $output_user_start_page_column_heading = '<th>' . get_column_heading(lang('Start Page'), ($_SESSION['software']['view_users']['sort'] ?? ''), ($_SESSION['software']['view_users']['order'] ?? ''), $output_filter_for_links) . '</th>';
    $output_last_modified_column_header = '<th>' . get_column_heading(lang('Last Modified'), ($_SESSION['software']['view_users']['sort'] ?? ''), ($_SESSION['software']['view_users']['order'] ?? ''), $output_filter_for_links) . '</th>';
}

// If the user is not viewing the my manager, administrators, designers or content managers
if (($filter != 'default') && ($filter != 'all_site_administrators') && ($filter != 'all_site_designers') && ($filter != 'all_site_managers')) {
    
    // Select table column
    $sql_select_column .= ',
        contacts.id as contact_id,
        contacts.salutation as contact_salutation,
        contacts.first_name as contact_first_name,
        contacts.last_name as contact_last_name,
        contacts.nickname as contact_nickname,
        contacts.suffix as contact_suffix';
    
    // Output column heading
    $output_user_contact_column_header = '<th>' . lang('User\'s Contact') . '</th>';
}

switch ($filter) {
    case 'my_private_users':
        // Output column heading
        $output_private_folder_access_column_heading = '<th>' . lang('Private Folders') . '</th>';
        break;
        
    case 'my_content_managers':
        // Output content column heading
        $output_manage_content_column_heading = '<th>' . lang('Folders') . '</th>';
        break;
        
    case 'my_calendar_managers':
        // Output content column
        $output_manage_calendars_column_heading = '<th>' . lang('Calendars') . '</th>';
        break;
        
    case 'my_submitted_forms_managers':
        // if forms module is on, then output manage forms header
        if (FORMS === true) {
            
            $all_custom_form_pages = array();
            
            // Get all custom form pages
            $query = "
                SELECT
                    custom_form_pages.form_name,
                    page.page_folder
                FROM page
                LEFT JOIN custom_form_pages ON custom_form_pages.page_id = page.page_id";
            $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
            while ($row=mysqli_fetch_assoc($result)) {
                
                // Fill array with all custom forms
                $all_custom_form_pages[] = $row;
            }
            
            // Output column header
            $output_manage_forms_column_header = '<th>' . lang('Custom Forms') . '</th>';
        }
        break;
        
    case 'my_contact_managers':
        // Output column headings
        $output_manage_contacts_column_header = '<th>' . lang('Contact Groups') . '</th>';
        $output_manage_email_column_header = '<th class="text-center">' . lang('Send Campaigns') . '</th>';
        break;
    
    case 'my_campaign_managers':
        // Output column heading
        $output_manage_contacts_column_header = '<th>' . lang('Contact Groups') . '</th>';
        $output_manage_email_column_header = '<th class="text-center">' . lang('Send Campaigns Only') . '</th>';
        break;
}

// if the sort is not set yet, then default it to empty so that the switch below falls
// through to its default case
if (isset($_SESSION['software']['view_users']['sort']) == false) {
    $_SESSION['software']['view_users']['sort'] = '';
}

switch (($_SESSION['software']['view_users']['sort'] ?? '')) {
    case lang('Username'):
        $sort_column = 'user.user_username';
        break;

    case lang('E-mail Address'):
        $sort_column = 'user.user_email';
        break;

    case lang('Role'):
        $sort_column = 'user.user_role';
        break;
        
    case lang('Member User'):
        $sort_column = 'user_member_id';
        break;
        
    case lang('Start Page'):
        $sort_column = 'user.user_home';
        break;
        
    case lang('Site Manager'):
    case lang('Site Designer'):
        $sort_column = 'user.user_role';
        break;
    case lang('Last Online'):
        $sort_column = 'user.user_online_timestamp';
        break;

    case lang('Last Modified'):
        $sort_column = 'user.user_timestamp';
        break;

    default:
        $sort_column = 'user.user_timestamp';
        $_SESSION['software']['view_users']['sort'] = lang('Last Modified');
        $_SESSION['software']['view_users']['order'] = 'desc';
}

// if order is not set, set to ascending
if (isset($_SESSION['software']['view_users']['order']) == false) {
    $_SESSION['software']['view_users']['order'] = 'asc';
}

// assume that the id does not need to be distinct, until we find out otherwise
$sql_select_id = "user.user_id";

// assume that the from table is the user table until we find out otherwise
$sql_from_table = "user";

// assume that we don't need to join the user table, until we find out otherwise
$sql_join_user_table = "";

// Switch between the filters.
switch($filter) {
    case 'my_registered_users':
        
        // if where is blank, then add the start of the where clause
        if ($where == '') {
            $where .= "WHERE ";

        // else where is not blank, so add and
        } else {
            $where .= " AND ";
        }
        
        // Set the query filter.
        $where .= "(user.user_contact IS NOT NULL)";
        
        // Change the heading and subheading.
        $heading = lang('My Registered Users');
        $subheading = lang('All my users created through website registration.');
        break;

    case 'my_private_users':
        
        // set select id to be distinct, because we will select from aclfolder
        $sql_select_id = "DISTINCT user.user_id";

        // set the from table
        $sql_from_table = "aclfolder";

        // join user table because we are selecting from aclfolder for this filter
        $sql_join_user_table = "LEFT JOIN user ON aclfolder.aclfolder_user = user.user_id";
        
        // if where is blank, then add the start of the where clause
        if ($where == '') {
            $where .= "WHERE ";

        // else where is not blank, so add and
        } else {
            $where .= " AND ";
        }

        // Set the query filter.
        $where .= "((user.user_role < '3') OR (aclfolder.aclfolder_rights = '1'))";

        // Change the heading and subheading.
        $heading = lang('My Private Users');
        $subheading = lang('All my users that have access to view one or more private folders.');
        
        break;

    case 'my_member_users':
        
        // if where is blank, then add the start of the where clause
        if ($where == '') {
            $where .= "WHERE ";

        // else where is not blank, so add and
        } else {
            $where .= " AND ";
        }
        
        // Set the query filter.
        $where .= "(contacts.member_id != '')";
        // Change the heading and subheading.
        $heading = lang('My Member Users');
        $subheading = lang('All my users that are associated with a contact that contains a member id.');
        break;

    case 'my_content_managers':
        // set select id to be distinct, because we will select from aclfolder
        $sql_select_id = "DISTINCT user.user_id";

        // set the from table
        $sql_from_table = "aclfolder";

        // join user table because we are selecting from aclfolder for this filter
        $sql_join_user_table = "LEFT JOIN user ON aclfolder.aclfolder_user = user.user_id";
        
        // if where is blank, then add the start of the where clause
        if ($where == '') {
            $where .= "WHERE ";

        // else where is not blank, so add and
        } else {
            $where .= " AND ";
        }

        // Set the query filter.
        $where .= "((user.user_role < '3') OR (aclfolder.aclfolder_rights = '2'))";

        // Change the heading and subheading.
        $heading = lang('My Content Managers');
        $subheading = lang('All my users that have edit access to at least one folder.');

        break;

    case 'my_calendar_managers':
        
        // if where is blank, then add the start of the where clause
        if ($where == '') {
            $where .= "WHERE ";

        // else where is not blank, so add and
        } else {
            $where .= " AND ";
        }

        // Set the query filter.
        $where .= "((user.user_role < 3) OR (user.user_manage_calendars = 'yes'))";

        // Change the heading and subheading.
        $heading = lang('My Calendar Managers');
        $subheading = lang('All my users that can add events to at least one calendar.');
        break;

    case 'my_submitted_forms_managers':
        
        // if where is blank, then add the start of the where clause
        if ($where == '') {
            $where .= "WHERE ";

        // else where is not blank, so add and
        } else {
            $where .= " AND ";
        }

        // Set the query filter.
        $where .= "((user.user_role < 3) OR (user.user_manage_forms = 'yes'))";

        // Change the heading and subheading.
        $heading = lang('My Submitted Forms Managers');
        $subheading = lang('All my users that can view and edit data collected by at least one custom form.');
        break;

    case 'my_visitor_report_managers':
        // if where is blank, then add the start of the where clause
        if ($where == '') {
            $where .= "WHERE ";

        // else where is not blank, so add and
        } else {
            $where .= " AND ";
        }

        // Set the query filter.
        $where .= "((user.user_role < 3) OR (user.user_manage_visitors = 'yes'))";

        // Change the heading and subheading.
        $heading = lang('My Visitor Report Managers');
        $subheading = lang('All my users that can view and edit all visitor reports.');
        break;

    case 'my_contact_managers':
        // if where is blank, then add the start of the where clause
        if ($where == '') {
            $where .= "WHERE ";

        // else where is not blank, so add and
        } else {
            $where .= " AND ";
        }

        // Set the query filter.
        $where .= "((user.user_role < 3) OR (user.user_manage_contacts = 'yes'))";

        // Change the heading and subheading.
        $heading = lang('My Contact Managers');
        $subheading = lang('All my users that can view and edit all contacts in at least one contact group.');
        break;

    case 'my_campaign_managers':
        // if where is blank, then add the start of the where clause
        if ($where == '') {
            $where .= "WHERE ";

        // else where is not blank, so add and
        } else {
            $where .= " AND ";
        }

        // Set the query filter.
        $where .= "((user.user_role < 3) OR (user.user_manage_emails = 'yes'))";

        // Change the heading and subheading.
        $heading = lang('My Campaign Managers');
        $subheading = lang('All my users that can send e-mail campaigns to one or more contact groups.');
        break;

    case 'my_commerce_managers':
        // if where is blank, then add the start of the where clause
        if ($where == '') {
            $where .= "WHERE ";

        // else where is not blank, so add and
        } else {
            $where .= " AND ";
        }

        // Set the query filter.
        $where .= "((user.user_role < 3) OR (user.user_manage_ecommerce = 'yes'))";

        // Change the heading and subheading.
        $heading = lang('My Commerce Managers');
        $subheading = lang('All my users that can view and edit all products, product groups, offers, and orders.');
        break;

    case 'all_site_managers':
        // if where is blank, then add the start of the where clause
        if ($where == '') {
            $where .= "WHERE ";

        // else where is not blank, so add and
        } else {
            $where .= " AND ";
        }

        // Set the query filter.
        $where .= "(user.user_role < 3)";

        // Change the heading and subheading.
        $heading = lang('All Site Managers');
        $subheading = lang('All users that can update site settings, and create or import other users and grant them privileges.');
        break;

    case 'all_site_designers':
        // if where is blank, then add the start of the where clause
        if ($where == '') {
            $where .= "WHERE ";

        // else where is not blank, so add and
        } else {
            $where .= " AND ";
        }

        // Set the query filter.
        $where .= "(user.user_role < 2)";

        // Change the heading and subheading.
        $heading = lang('All Site Designers');
        $subheading = lang('All users that can update site settings, site designs, and create or import site managers.');
        break;

    case 'all_site_administrators':
        // if where is blank, then add the start of the where clause
        if ($where == '') {
            $where .= "WHERE ";

        // else where is not blank, so add and
        } else {
            $where .= " AND ";
        }

        // Set the query filter.
        $where .= "(user.user_role < 1)";

        // Change the heading and subheading.
        $heading = lang('All Site Administrators');
        $subheading = lang('All site administrator user accounts.');
        
        break;

    default:

        // Select columns
        $sql_select_column .= ',
            contacts.member_id AS user_member_id';

        // Change the heading and subheading.
        $heading = lang('All My Users');
        $subheading = lang('All users that I have access too.');
        
        break;
}

// If the user is not viewing all site administrators, designers or managers
if (($filter != 'all_site_administrators') || ($filter != 'all_site_designers') || ($filter != 'all_site_managers')) {
    
    // Join contacts table
    $sql_join_contacts_table = "LEFT JOIN contacts ON user.user_contact = contacts.id";
} else {
    $sql_join_contacts_table = "";
}
        
$my_users = 0;
$all_users = 0;

// Get number of all users for filter.
$query = "SELECT
            COUNT($sql_select_id)
         FROM $sql_from_table
         $sql_join_user_table
         $sql_join_contacts_table
         $where";
$result = mysqli_query(db::$con, $query) or output_error('Query failed.');
$row = mysqli_fetch_row($result);
$all_users = $row[0];

// if user is an administrator, then select all users
if ($user['role'] == 0) {
    // if where is blank, then add the start of the where clause
    if ($where == '') {
        $where .= "WHERE ";

    // else where is not blank, so add and
    } else {
        $where .= " AND ";
    }

    $where .= '(user.user_role >= ' . $user['role'] . ')';

// else user is not an administrator, so only show users that have less power than current user
} else {
    // if where is blank, then add the start of the where clause
    if ($where == '') {
        $where .= "WHERE ";

    // else where is not blank, so add and
    } else {
        $where .= " AND ";
    }

    $where .= '(user.user_role > ' . $user['role'] . ')';
}

// Get number of user that user has access to manage.
$query = "SELECT
            COUNT($sql_select_id)
         FROM $sql_from_table
         $sql_join_user_table
         $sql_join_contacts_table
         $where";
$result = mysqli_query(db::$con, $query) or output_error('Query failed.');
$row = mysqli_fetch_row($result);
$my_users = $row[0];


// if user is an administrator, get number of administrators in order to prevent the last administrator from being deleted
if ($user['role'] == 0) {
    $query = "SELECT COUNT(user_id) FROM user WHERE user_role = '0'";
    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
    $row = mysqli_fetch_row($result);
    $number_of_administrators = $row[0];
}

// Set checkmark in variable
$output_checkmark = '<span class="material-icons">task_alt</span>';

/* get results for just this screen*/
$query =
    "SELECT
        $sql_select_id as id,
        user.user_username as username,
        user.user_email as email,
        user.user_role as role,
        user.user_manage_contacts as manage_contacts,
        user.user_manage_visitors as manage_visitors,
        user.user_manage_ecommerce as manage_ecommerce,
        user.user_manage_forms as manage_forms,
        user.user_manage_calendars as manage_calendars,
        user.user_manage_emails as manage_emails,
        user.user_contact as user_contact,
        user_2.user_username as user,
        user.user_online_timestamp as last_seen,
        user.user_timestamp as timestamp" .
        $sql_select_column . "
    FROM $sql_from_table
    $sql_join_user_table
    LEFT JOIN user as user_2 ON user.user_user = user_2.user_id
    $sql_join_contacts_table
    $where
    ORDER BY $sort_column " . escape(($_SESSION['software']['view_users']['order'] ?? '')) . " ";
$result = mysqli_query(db::$con, $query) or output_error('Query failed.');
while ($row = mysqli_fetch_assoc($result)) {
    $id = $row['id'];
    $username = $row['username'];
    $email = $row['email'];
    $role = $row['role'];
    $manage_contacts = $row['manage_contacts'];
    $manage_visitors = $row['manage_visitors'];
    $manage_ecommerce = $row['manage_ecommerce'];
    $manage_forms = $row['manage_forms'];
    $manage_emails = $row['manage_emails'];
    $manage_calendars = $row['manage_calendars'];
    $last_modified_username = $row['user'];
    $user_contact = $row['user_contact'];
    $last_seen = $row['last_seen'];

    // If the filter is on default or all my users view.
    if ($filter == 'default') {
        
        // Set database variables
        $user_member_id = $row['user_member_id'];
        $aclfolder_rights = isset($row['aclfolder_rights']) ? $row['aclfolder_rights'] : '';
    
    // else the user in not on the all my users view.
    } else {
        
        // Set database variables
        $user_home = $row['user_home'];
        $timestamp = $row['timestamp'];
    }
    
    // If the user is not viewing the my manager, administrators, designers or all my users
    if (($filter != 'default') || ($filter != 'all_site_administrators') || ($filter != 'all_site_designers') || ($filter != 'all_site_managers')) {
        
        // Set database variables
        // NOTE: the condition above is always true (a value cannot differ from every one of those
        // filters at once), so these columns are read even for the filters whose query does not
        // select them.  Left as-is on purpose; only the missing-key warnings are silenced here.
        $contact_id = isset($row['contact_id']) ? $row['contact_id'] : '';
        $contact_salutation = isset($row['contact_salutation']) ? $row['contact_salutation'] : '';
        $contact_first_name = isset($row['contact_first_name']) ? $row['contact_first_name'] : '';
        $contact_last_name = isset($row['contact_last_name']) ? $row['contact_last_name'] : '';
        $contact_nickname = isset($row['contact_nickname']) ? $row['contact_nickname'] : '';
        $contact_suffix = isset($row['contact_suffix']) ? $row['contact_suffix'] : '';
    }
    
    // If the user is viewing the my registered users or my member users view
    if (($filter == 'my_registered_users') || ($filter == 'my_member_users')) {
        
        // Set database variables
        $contact_timestamp = $row['contact_timestamp'];
    }
    
    // get current date
    $current_date = date('Y-m-d');

    // if this user is not an administrator or the number of administrators is greater than one, then we can allow this user to be deleted, so prepare checkbox
    if (($role != 0) || ($number_of_administrators > 1)) {
        $output_checkbox = ' <input class="form-check-input " type="checkbox" name="users[]" value="' . $id . '" />';
        $output_selectable_class = '';
    } else {
        $output_checkbox = '<input type="checkbox" name="users[]" value="' . $id . '" class="form-check-input disabled" disabled="disabled"  />';
        
        $output_selectable_class = 'unselectable';
    }

    // if user is an administrator, designer, or manager, then user has access to contacts, e-commerce, forms, and e-mails automatically
    if ($role < 3) {
        $manage_contacts = 'yes';
        $manage_visitors = 'yes';
        $manage_ecommerce = 'yes';
        $manage_forms = 'yes';
        $manage_emails = 'yes';
        $manage_calendars = 'yes';
    }

    $output_link_url = 'edit_user.php?id=' . $id . '&send_to=' . h(escape_javascript(urlencode(REQUEST_URL)));

    // If the filter is not default or all my users view.
    if ($filter == 'default') {
        // output table columns
        $output_user_role_column = '<td class="align-middle"><span class="badge bg-secondary fw-light">' . get_role_name($role) . '</span></td>';
        
        // if user has a memeber id
        if ($user_member_id != '') {
            
            // output checkmark
            $output_member_user_column = '<td class="align-middle text-center">' . $output_checkmark . '</td>';
            
        // Else output a blank cell
        } else {
            $output_member_user_column = '<td class="align-middle text-center">&nbsp;</td>';
        }
        
        // Get folders user has access to
        $query2 = "SELECT DISTINCT aclfolder_user FROM aclfolder WHERE ((aclfolder_user = '" . $id . "') AND (aclfolder_rights = 1))";
        $result2 = mysqli_query(db::$con, $query2) or output_error('Query failed.');
        $row2 = mysqli_fetch_assoc($result2);
        
        // if user is a manager or above, or if the basic user has a aclfolder_user value
        if (($role < 3) || (isset($row2['aclfolder_user']) && ($row2['aclfolder_user'] != ''))) {
            $output_private_folder_access_column = '<td class="align-middle text-center">' . $output_checkmark . '</td>';
        
        // Else output the checkmark
        } else {
            $output_private_folder_access_column = '<td class="align-middle text-center">&nbsp;</td>';
        }
        
        // If user can edit content
        if (($role < 3) || (no_acl_check($id) == TRUE)) {
            // Output checkmark
            $output_manage_content_column = '<td class="align-middle text-center">' . $output_checkmark . '</td>';
        
        // Else output the a blank cell
        } else {
            $output_manage_content_column = '<td class="align-middle text-center">&nbsp;</td>';
        }
        
        // if user has access to manage calendars
        if ($manage_calendars == 'yes') {
            $manage_calendars = $output_checkmark;

        // else user does not have access to manage calendars
        } else {
            $manage_calendars = '';
        }
        
        // If forms module is on
        if (FORMS === true) {
            // if user has access to manage forms
            if ($manage_forms == 'yes') {
                $manage_forms = $output_checkmark;

            // else user does not have access to manage forms
            } else {
                $manage_forms = '';
            }
            
            // output column checkmark
            $output_manage_forms_column = '<td class="align-middle text-center">' . $manage_forms . '</td>';
        }
        
        // output column
        $output_manage_calendars_column = '<td class="align-middle text-center">' . $manage_calendars . '</td>';
        
        // if user has access to manage visitors
        if ($manage_visitors == 'yes') {
            $manage_visitors = $output_checkmark;

        // else user does not have access to manage contacts
        } else {
            $manage_visitors = '';
        }
        
        // output column
        $output_view_visitors_column = '<td class="align-middle text-center">' . $manage_visitors . '</td>';
        
        // if user has access to manage contacts
        if ($manage_contacts == 'yes') {
            $manage_contacts = $output_checkmark;

        // else user does not have access to manage contacts
        } else {
            $manage_contacts = '';
        }
        
        // output column
        $output_manage_contacts_column = '<td class="align-middle text-center">' . $manage_contacts . '</td>';
        
        if (ECOMMERCE === true) {
            // if user has access to manage e-commerce
            if ($manage_ecommerce == 'yes') {
                $manage_ecommerce = $output_checkmark;

            // else user does not have access to manage e-commerce
            } else {
                $manage_ecommerce = '';
            }
            
            // output column
            $output_manage_ecommerce_column = '<td class="align-middle text-center">' . $manage_ecommerce . '</td>';
        }
        
        // If user is manager or above
        if ($role < 3) {
            
            // output checkmark
            $output_manage_users_column = '<td class="align-middle text-center">' . $output_checkmark . '</td>';
        } else {
            
            // output blank cell
            $output_manage_users_column = '<td class="align-middle text-center">&nbsp;</td>';
        }
        // If user is a designer or administrator
        if ($role <= 1) {
            // output checkmark
            $output_edit_design_column = '<td class="align-middle text-center">' . $output_checkmark . '</td>';
        } else {
            
            // output blank cell
            $output_edit_design_column = '<td class="align-middle text-center">&nbsp;</td>';
        }
    
    // Else if the user is not on the all my users view.
    } else {    
        // If user home is not set to none
        if ($user_home != 0) {
            // get start page name
            $user_start_page = get_page_name($user_home);
           
        // Else user has no start page
        } else {
            $user_start_page = '';
        }
        
        if ($last_modified_username) {
            $last_modified_username = lang(array('string'=>'by {var:1}','vars'=>array( h($last_modified_username) ) ) );
        }
        // output columns
        $output_email_address_column = '<td class="align-middle">' . h($email) . '</td>';
        $output_user_start_page_column = '<td class="align-middle">' . h($user_start_page) . '</td>';
        $output_last_modified_column = '<td class="align-middle">' . get_relative_time(array('timestamp' => $timestamp)) . ' ' . $last_modified_username . '</td>';
    }
    
    // If the user is not viewing the my manager, administrators, designers or all my users
    if (($filter != 'default') && ($filter != 'all_site_administrators') && ($filter != 'all_site_designers') && ($filter != 'all_site_managers')) {
        $output_contact_name = '';
        
        // Output the contact name
        // If there is a nickname
        if ($contact_nickname != '') {
            
            // output nickname
            $output_contact_name = h($contact_nickname); 
        
        // else if there is no nickname
        } else {
            
            // If there is a salutation
            if ($contact_salutation != '') {
                
                // output the salutation
                $output_contact_name = h($contact_salutation) . ' ';
            }
            
            // If there is a first name
            if ($contact_first_name != '') {
                
                // output first name
                $output_contact_name .= h($contact_first_name) . ' ';
            }
            
            // If there is a last name
            if ($contact_last_name != '') {
                
                // output last name
                $output_contact_name .= h($contact_last_name);
            }
            
            // If there is a last name
            if ($contact_suffix != '') {
                
                // output last name
                $output_contact_name .= ' ' . h($contact_suffix);
            }
        }
        
        // If there is a contact name
        if ($output_contact_name != '') {
            
            // Output table cell with a link to the edit contacts screen
            $output_user_contact_column = '<td><a class="btn btn-link link-secondary py-0 m-1" href="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/edit_contact.php?id=' . $contact_id . '">' . $output_contact_name . '</a></td>';
            
        // Else output a blank cell
        } else {
            $output_user_contact_column = '<td class="align-middle">&nbsp;</td>';
        }
    }
    
    switch ($filter) {
        case 'my_private_users':
            
            // If user is a manager or above
            if ($role < 3) {
                // Output all
                $output_private_folder_access_column =
                    '<td class="align-middle text-center">' . lang('All') . '</td>';
            } else {
                // Else find the folders user has access to
                // Initialize variables
                $folder_names_that_user_has_access_to = '';
                
                // Get all folders user has access to
                $query2 = "
                    SELECT
                        folder.folder_name
                    FROM folder 
                    LEFT JOIN aclfolder ON aclfolder.aclfolder_folder = folder.folder_id
                    WHERE 
                        ((aclfolder_user = '" . $id . "')
                        AND (aclfolder_rights = '1'))
                    ORDER BY folder_name ASC";
                $result2 = mysqli_query(db::$con, $query2) or output_error('Query failed.');
                
                // Loop through all folders that user has access to.
                while ($row2 = mysqli_fetch_assoc($result2)) {
                    $folder_names_that_user_has_access_to .= '<li><span class="dropdown-item-text badge text-dark fw-light py-2" >' . h($row2['folder_name']) . '</span></li>';
                }
                
                // Output content column
                $output_private_folder_access_column =
                    '<td class="align-middle text-center">
                        <div class="row row-cols-2 align-middle">
                            <div class="dropdown col">
                                <button type="button" class="m-1 btn btn-sm p-1 no-popover dropdown-toggle" data-bs-auto-close="outside" data-bs-toggle="dropdown" aria-expanded="false"><span class="material-icons d-inline">folder</span></button>
                                <ul class="dropdown-menu">
                                '. $folder_names_that_user_has_access_to . '
                                </ul>
                            </div>
                        </div>
                    </td>';
            }
            
            break;
            
        case 'my_content_managers':
            // If user is a manager or above
            if ($role < 3) {
                // Output all
                $output_manage_content_column =
                    '<td class="align-middle">' . lang('All Pages and Files') . '</td>';
            } else {
                // Initialize variables
                $folder_names_that_user_has_access_to = '';
                
                // Get all folders user has access to
                $query2 = "
                    SELECT
                        folder.folder_name,
                        folder.folder_parent
                    FROM folder 
                    LEFT JOIN aclfolder ON aclfolder.aclfolder_folder = folder.folder_id
                    WHERE 
                        ((aclfolder_user = '" . $id . "')
                        AND (aclfolder_rights = '2'))
                    ORDER BY folder_name ASC";
                $result2 = mysqli_query(db::$con, $query2) or output_error('Query failed.');
                
                // Loop through all folders that user has access to.
                while ($row2 = mysqli_fetch_assoc($result2)) {
                    
                    // If the folder is the top level folder
                    if ($row2['folder_parent'] == 0) {
                        
                        // Output folder name and then break out of loop.
                        $folder_names_that_user_has_access_to .= '<li><span class="dropdown-item-text badge text-dark fw-light py-2" >' . h($row2['folder_name']) . '</span></li>';
                        break;
                        
                    // Else if the top most folder was not found continue loop
                    } else {
                        // output folder names
                        $folder_names_that_user_has_access_to .= '<li><span class="dropdown-item-text badge text-dark fw-light py-2" >' . h($row2['folder_name']) . '</span></li>';
                    }
                }
                
                // Output content column
                $output_manage_content_column =
                    '<td class="align-middle text-center">
                        <div class="row row-cols-2 align-middle">
                            <div class="dropdown col">
                                <button type="button" class="m-1 btn btn-sm p-1 no-popover dropdown-toggle" data-bs-auto-close="outside" data-bs-toggle="dropdown" aria-expanded="false"><span class="material-icons d-inline">folder</span></button>
                                <ul class="dropdown-menu">
                                '. $folder_names_that_user_has_access_to . '
                                </ul>
                            </div>
                        </div>
                    </td>';
            }
            
            break;
            
        case 'my_calendar_managers':
            
            // If user is a manager or above
            if ($role < 3) {
                // Output all
                $output_manage_calendars_column =
                    '<td class="align-middle text-center">' . lang('All') . '</td>';
            } else {
                // Initialize variables
                $calendars_that_user_has_access_to = '';
                    
                // Get all calendars that user has access to manage
                $query2 = "
                    SELECT
                        calendars.name
                    FROM users_calendars_xref
                    LEFT JOIN calendars ON calendars.id = users_calendars_xref.calendar_id
                    WHERE 
                        users_calendars_xref.user_id = '" . $id . "'";
                $result2 = mysqli_query(db::$con, $query2) or output_error('Query failed.');
                while ($row2 = mysqli_fetch_assoc($result2)) {
                    $calendars_that_user_has_access_to .= '<li><span class="dropdown-item-text badge text-dark fw-light py-2" >' . h($row2['name']) . '</span></li>';
                }
                
                // Output content column
                $output_manage_calendars_column =
                    '<td class="align-middle text-center">
                        <div class="row row-cols-2 align-middle">
                            <div class="dropdown col">
                                <button type="button" class="m-1 btn btn-sm p-1 no-popover dropdown-toggle" data-bs-auto-close="outside" data-bs-toggle="dropdown" aria-expanded="false"><span class="material-icons d-inline">today</span></button>
                                <ul class="dropdown-menu">
                                '. $calendars_that_user_has_access_to . '
                                </ul>
                            </div>
                        </div>
                    </td>';
            }
            
            break;
            
        case 'my_submitted_forms_managers':
            if (FORMS === true) {
                
                // If user is a manager or above
                if ($role < 3) {
                    // Output all
                    $output_manage_forms_column =
                        '<td class="align-middle text-center">' . lang('All') . '</td>';
                } else {
                    $submitted_forms_that_user_has_access_to = '';
                
                    // Get folders that user has access to
                    $folders_that_user_has_access_to = get_folders_that_user_has_access_to($id);
                    
                    foreach ($all_custom_form_pages as $custom_form_page) {
                        // if user has access to folder that custom form is in, then output custom form
                        if (in_array($custom_form_page['page_folder'], $folders_that_user_has_access_to) == true) {
                            if ($custom_form_page['form_name'] != '') {
                                $submitted_forms_that_user_has_access_to .= '<li><span class="dropdown-item-text badge text-dark fw-light py-2" >' . h($custom_form_page['form_name']) . '</span></li>';
                             
                            }
                        }
                    }
                    
                    // Output content column
                    $output_manage_forms_column =
                        '<td class="align-middle text-center">
                            <div class="row row-cols-2 align-middle">
                                <div class="dropdown col">
                                    <button type="button" class="m-1 btn btn-sm p-1 no-popover dropdown-toggle" data-bs-auto-close="outside" data-bs-toggle="dropdown" aria-expanded="false"><span class="material-icons d-inline">list_alt</span></button>
                                    <ul class="dropdown-menu">
                                    '. $submitted_forms_that_user_has_access_to . '
                                    </ul>
                                </div>
                            </div>
                        </td>';
                }
            }
            break;
            
        case 'my_campaign_managers':
            // if user has access to manage campaigns
            if (($role == 3) && ($manage_emails == 'yes') && ($manage_contacts != 'yes')) {
                $manage_emails = $output_checkmark;

            // else user does not have access to manage campaigns
            } else {
                $manage_emails = '';
            }

            // Output content column
            $output_manage_email_column = '<td class="align-middle text-center">' . $manage_emails . '</td>';
            break;
    }
    
    // If contact or campaigns managers filters are selected
    if (($filter == 'my_contact_managers') || ($filter == 'my_campaign_managers')) {
        
        // If user is a manager or above
            if ($role < 3) {
                // Output all
                $output_manage_contacts_column =
                    '<td class="align-middle text-center">' . lang('All') . '</td>';
            } else {
                
            $contact_group_that_user_has_access_to = '';
                
            // Get contact groups user can edit
            $query2 = "
                SELECT
                    contact_groups.name as contact_group_name
                FROM users_contact_groups_xref
                LEFT JOIN contact_groups ON contact_groups.id = users_contact_groups_xref.contact_group_id
                WHERE users_contact_groups_xref.user_id = '" . $id . "'";
            $result2 = mysqli_query(db::$con, $query2) or output_error('Query failed.');
            while ($row2 = mysqli_fetch_assoc($result2)) {
                // If there is a contact group then prepare to output the contact group
                if ($row2['contact_group_name'] != '') {
                    $contact_group_that_user_has_access_to .= '<li><span class="dropdown-item-text badge text-dark fw-light py-2" >' . h($row2['contact_group_name']) . '</span></li>';
                }
            }
            
            // Output content column
            $output_manage_contacts_column =
                '<td class="align-middle text-center">
                    <div class="row row-cols-2 align-middle">
                        <div class="dropdown col">
                            <button type="button" class="m-1 btn btn-sm p-1 no-popover dropdown-toggle" data-bs-auto-close="outside" data-bs-toggle="dropdown" aria-expanded="false"><span class="material-icons d-inline">groups</span></button>
                            <ul class="dropdown-menu">
                            '. $contact_group_that_user_has_access_to . '
                            </ul>
                        </div>
                    </div>
                </td>';
        }
    }
    
    // If user is on the all my uses view or on the site administrator, designer, manager, my contact managers or my contact managers views.
    if (($filter == 'default') || ($filter == 'my_contact_managers')) {
        
        // if user has access to manage e-mails
        if ($manage_emails == 'yes') {
            $manage_emails = $output_checkmark;

        // else user does not have access to manage e-mails
        } else {
            $manage_emails = '';
        }
    
        // output column
        $output_manage_email_column = '<td class="align-middle text-center">' . $manage_emails . '</td>';
    }


    if($last_seen >= 1){
        $output_last_seen = '<td class="align-middle">' . get_relative_time(array('timestamp' => $last_seen)) . ' </td>';
    }else{
        $output_last_seen = '<td class="align-middle">[' . lang('Never') . ']</td>';
    }

    $output_rows .=
        '<tr class="' . $output_selectable_class . '">
            <td class="select-all align-middle text-start">' . $output_checkbox . '</td>
			<td class="align-middle text-start action-buttons">
                <button type="button" class="m-1 btn-data-control btn btn-outline-primary border-2 " data-loading-content=" " title="' . lang('Edit') . '" onclick="window.location.href=\'' . $output_link_url . '\'"><i class="bi bi-pencil"></i></button>
                <!--<button type="button" class="m-1 btn-data-control btn btn-outline-danger border-2 " data-loading-content=" " title="' . lang('Delete') . '" ><i class="material-icons">delete</i></button>-->
            </td>
            <td class="align-middle">' . h($username) . '</td>
            ' . $output_email_address_column . '
            ' . $output_user_role_column . '
            ' . $output_user_start_page_column . '
            ' . $output_private_folder_access_column . '
            ' . $output_member_user_column . '
            ' . $output_manage_content_column . '
            ' . $output_manage_calendars_column . '
            ' . $output_manage_forms_column . '
            ' . $output_view_visitors_column . '
            ' . $output_manage_contacts_column . '
            ' . $output_manage_email_column . '
            ' . $output_manage_ecommerce_column . '
            ' . $output_manage_users_column . '
            ' . $output_edit_design_column . '
            ' . $output_user_contact_column . '
            ' . $output_last_modified_column . '
            ' . $output_last_seen . '
        </tr>';
}

echo
    pg_page_shell(
        array(
            'title'=> lang($heading),
            'extra classes'=>'users',
            'icon'=>'account', 
            'heading'=>lang($heading),
                    
        )
    ). '
            <div class="row">
            <div class="col-12">
                ' . $liveform->output_errors() . '
                ' . $liveform->get_warnings() . '
                ' . $liveform->output_notices() . '
               
                <div class="row mb-2  flex-wrap">
                    <div class="col-12 col-sm-12 col-md-6 col-xl-9 text-center text-md-start">
                        <h2 class="d-inline-block " data-bs-content="' . $subheading . '" title="' . $heading . '">' . $heading . '</h2>
                        <nav id="button_bar" class="navigation " aria-label="Button Bar">
                            <a class="btn btn-sm btn-primary m-1 " href="add_user.php?send_to=' . h(REQUEST_URL) . '" data-loading-content="' . lang(array('string'=>'Loading') ) . '"><span class="bi bi-plus-circle me-2"></span>' . lang(array('string'=>'Create') ) . '</a>
                            <form id="export_form" class="disable_shortcut d-inline-block" method="get">
                                <div class=" btn-group btn-group-sm flex-wrap">
                                    <a class="btn btn-link link-secondary py-0 m-1" href="import_users.php?send_to=' . h(REQUEST_URL) . '"><span class="bi bi-box-arrow-in-right me-1"></span>' . lang(array('string'=>'Import') ) . '</a>
                                </div>
                            </form>
                        </nav>
                    </div>
                    <div class="col-12 col-sm-12 col-md-6 col-xl-3 ">
                        <div class="row justify-content-center justify-content-md-end">
                            <form id="search_form" action="view_users.php" method="get" class="search_form col-auto">
                                <input type="hidden" name="filter" value="' . h($filter) . '">
                                <div class="input-group input-group-sm">
                                    <label class="input-group-text mt-1 mb-1 material-icons" title="' . lang('Content that viewed') . '" for="filter_select">visibility</label>
                                    <select id="filter_select" name="filter" class="form-select mt-1 mb-1" title="' . lang('Content that viewed') . '" onchange="submit_form(\'search_form\')">' . get_filter_options($filters_in_array, $filter) . '</select>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                <div class="card my-4">
                    <div class="card-body p-0 position-relative">
                        <form name="form"  action="delete_users.php" method="post" class="disable_shortcut"> 
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
                                        <th>' . get_column_heading(lang('Username'), ($_SESSION['software']['view_users']['sort'] ?? ''), ($_SESSION['software']['view_users']['order'] ?? ''), $output_filter_for_links) . '</th>
                                        ' . $output_email_address_column_header . '
                                        ' . $output_user_role_column_heading . '
                                        ' . $output_user_start_page_column_heading . '
                                        ' . $output_private_folder_access_column_heading . '
                                        ' . $output_member_user_column_heading . '
                                        ' . $output_manage_content_column_heading . '
                                        ' . $output_manage_calendars_column_heading . '
                                        ' . $output_manage_forms_column_header . '
                                        ' . $output_view_visitors_column_header . '
                                        ' . $output_manage_contacts_column_header . '
                                        ' . $output_manage_email_column_header . '
                                        ' . $output_manage_ecommerce_column_header . '
                                        ' . $output_manage_users_column_header . '
                                        ' . $output_edit_design_column_header . '
                                        ' . $output_user_contact_column_header . '
                                        ' . $output_last_modified_column_header . '
                                        <th>' . get_column_heading(lang('Last Online'), ($_SESSION['software']['view_users']['sort'] ?? ''), ($_SESSION['software']['view_users']['order'] ?? ''), $output_filter_for_links) . '</th>
                                    </tr>
                                </thead>
                                <tbody>' . $output_rows . '</tbody>
                            </table>
                            <nav class="buttons navigation text-center position-sticky" style="bottom:.5rem;" aria-label="data edit buttons ">
                                <div class="container">
                                    <div class=" btn-group btn-group-sm flex-wrap justify-content-center mb-0 enable-on-selected">
                                        <button type="button" value="Delete Selected" class=" btn mb-1 mt-1 btn-danger disabled" data-loading-content="' . lang(array('string'=>'Deleting') ) . '" data-confirm-content="' . lang(array('string'=>'WARNING: Selected {var:1} will be permanently deleted.','vars'=>array(lang('users')))) . '"><span class="material-icons me-2">delete</span>' . lang(array('string'=>'Delete Selected') ) . '</button>
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

$liveform->remove_form('view_users');

function get_role_name($role_id)
{
    switch ($role_id)
    {
        case 0:
            return(lang('Administrator'));
            break;

        case 1:
            return(lang('Designer'));
            break;

        case 2:
            return(lang('Manager'));
            break;

        case 3:
            return(lang('User'));
            break;
    }
}