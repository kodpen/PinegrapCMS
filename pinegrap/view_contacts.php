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
validate_contacts_access($user);

$liveform = new liveform('view_contacts');

// These are only ever appended to (or conditionally set) further below, so they have to start
// out empty.
$where = '';
$join_table = '';
$sql_columns = '';
$output_rows = '';
$output = '';
$show_hide_contact_group_select = '';
$output_merge_contacts_button = '';

// NOTE: $number_of_screens is never calculated anywhere in this script, so the paging links
// below never render.  Starting it at zero keeps that behaviour and stops the warning.
$number_of_screens = 0;

// if show contact groups is not set yet, then default it to off
if (isset($_SESSION['software']['view_contacts']['show_contact_groups']) == false) {
    $_SESSION['software']['view_contacts']['show_contact_groups'] = false;
}

// if advanced filters are not set yet, then default them to off
if (isset($_SESSION['software']['view_contacts']['advanced_filters']) == false) {
    $_SESSION['software']['view_contacts']['advanced_filters'] = false;
}

// if user has a user role and if the all duplicate contacts filter is on, then user does not have access to this filter so output error
if (($user['role'] == 3) && (($_GET['filter'] ?? '') == 'all_duplicate_contacts')) {
    log_activity(lang('access denied to all duplicate contacts view'), $_SESSION['sessionusername']);
    output_error(lang('Access denied') . '. <a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.');
}

// store all values collected in request to session
foreach ($_REQUEST as $key => $value) {
    // if the value is a string or this is the contact groups array then add it to the session
    // we have to do this check because cookie arrays are sometimes included in the $_REQUEST array,
    // for certain php.ini settings
    if (
        (is_string($value) == TRUE)
        || ($key == 'contact_groups')
    ) {
        // if the value is a string, then trim it
        if (is_string($value) == TRUE) {
            $value = trim($value);
        }

        $_SESSION['software']['view_contacts'][$key] = $value;
    }
}

// if user has a user role, verify that user has access to contact groups that user has selected
if ($user['role'] == 3) {
    // if contact group has been selected and selected contact group is not [All]
    if ((($_SESSION['software']['view_contacts']['contact_group'] ?? '')) && (($_SESSION['software']['view_contacts']['contact_group'] ?? '') != '[' . lang('All') . ']')) {
        // if user does not have access to contact group, then unset contact group selection
        if (validate_contact_group_access($user, ($_SESSION['software']['view_contacts']['contact_group'] ?? '')) == false) {
            unset($_SESSION['software']['view_contacts']['contact_group']);
        }
    }

    // if contact groups have been selected in advanced filters
    if (($_SESSION['software']['view_contacts']['contact_groups'] ?? array())) {
        // loop through all selected contact groups in order to check if user has access to contact groups
        foreach (($_SESSION['software']['view_contacts']['contact_groups'] ?? array()) as $key => $value) {
            // if user does not have access to contact group, then unset contact group selection
            if (validate_contact_group_access($user, $value) == false) {
                unset($_SESSION['software']['view_contacts']['contact_groups'][$key]);
            }
        }
    }
}

// If there is a filter in the query string, save it
if (isset($_GET['filter']) == true) {
    $filter = $_GET['filter'];

// else set the filter to default
} else {
    $filter = 'default';
}

$filter_for_links = '&filter=' . $filter;
$output_filter_for_links = h($filter_for_links);

// build filters array
$filters_in_array = 
    array(
        'all_my_contacts'=>lang('All My Contacts'),
        'my_subscribers'=>lang('My Subscribers'),
        'my_affiliates'=>lang('My Affiliates'),
        'my_customers'=>lang('My Customers'),
        'my_members'=>lang('My Members'),
        'my_active_members'=>lang('My Active Members'),
        'my_expired_members'=>lang('My Expired Members'),
        'my_unregistered_members'=>lang('My Unregistered Members'),
        'my_contacts_by_user'=>lang('My Contacts by User'),
        'my_contacts_by_business_address'=>lang('My Contacts by Business Address'),
        'my_contacts_by_home_address'=>lang('My Contacts by Home Address')
    );

// If user is a manager or above add the all duplicate contacts filter to the array
if ($user['role'] < 3) {
    $filters_in_array['all_duplicate_contacts'] = lang('All Duplicate Contacts');
}


// if show contact groups is true then set the session to show the groups
if (($_GET['show_contact_groups'] ?? '') == 'true') {
    $_SESSION['software']['view_contacts']['show_contact_groups'] = true;

// else if show contact groups is false, then update the session to hide the groups
} elseif ((($_GET['show_contact_groups'] ?? '') == 'false') && (($_GET['show_contact_groups'] ?? '') != '')) {
    $_SESSION['software']['view_contacts']['show_contact_groups'] = false;
}

$output_organize_selected_button = '';

// if this is not the all duplicates view and if the user has selected to show contact groups, then output the organize selected button
if (($filter != 'all_duplicate_contacts') && (($_SESSION['software']['view_contacts']['show_contact_groups'] ?? '') == true)) {
    $output_organize_selected_button = '<button type="button" value="Organize Selected" class=" btn mb-1 mt-1 btn-primary disabled" onclick="window.open(\'organize_contacts.php\', \'popup\', \'toolbar=no,location=no,directories=no,status=yes,menubar=no,resizable=yes,copyhistory=no,scrollbars=yes,width=500,height=500\'); edit_chart_content(\'organize\',\'contact\')"><span class="material-icons me-2">edit</span>' . lang(array('string'=>'Organize Selected') ) . '</button>';
}

// Switch between the subnav filters
switch ($filter) {
    case 'my_subscribers':
        // If where is blank
        if ($where == '') {
            $where .= ' WHERE ';
        
        // else where is not blank, so add and
        } else {
            $where .= ' AND ';
        }
        
        // Set the query filter.
        $where .= ' ((contacts.email_address != "") AND (contacts.opt_in = "1"))';
        
        // Change the heading and subheading.
        $heading = lang('My Subscribers');
        $subheading = lang('All my contacts that can be recipients of an email campaign.');
        break;
        
    case 'my_affiliates':
        // If where is blank
        if ($where == '') {
            $where .= ' WHERE ';

        // else where is not blank, so add and
        } else {
            $where .= ' AND ';
        }

        // Set the query filter.
        $where .= "(contacts.affiliate_approved = '1')";

        // Change the heading and subheading.
        $heading = lang('My Affiliates');
        $subheading = lang('All my contacts with approved affiliate status that can receive order commissions for referring visitors.');
        break;

    case 'my_customers':
        // If where is blank
        if ($where == '') {
            $where .= ' WHERE ';

        // else where is not blank, so add and
        } else {
            $where .= ' AND ';
        }

        // Join table
        $join_table = 'LEFT JOIN orders ON orders.contact_id = contacts.id';

        // Set the query filter.
        $where .= "(orders.status != 'incomplete')";

        // Change the heading and subheading.
        $heading = lang('My Customers');
        $subheading = lang('All my contacts who have submitted orders.');
        break;

    case 'my_members':

        // If where is blank
        if ($where == '') {
            $where .= ' WHERE ';

        // else where is not blank, so add and
        } else {
            $where .= ' AND ';
        }

        // Set the query filter.
        $where .= "(contacts.member_id != '')";

        // Set membership filter and label
        $membership_filter = true;
        $membership_status_label = lang('Member');

        // Change the heading and subheading.
        $heading = lang('My Members');
        $subheading = lang('All my contacts with a member id.');
        break;

    case 'my_active_members':

        // If where is blank
        if ($where == '') {
            $where .= ' WHERE ';

        // else where is not blank, so add and
        } else {
            $where .= ' AND ';
        }

        // Set the query filter.
        $where .= "((contacts.member_id != '') AND ((contacts.expiration_date >= CURRENT_DATE()) OR (contacts.expiration_date = '0000-00-00')))";

        // Set membership filter and label
        $membership_filter = true;
        $membership_status_label = lang('Active Member');

        // Change the heading and subheading.
        $heading = lang('My Active Members');
        $subheading = lang('All my contacts with a member id and a future expiration date.');
        break;

    case 'my_expired_members':

        // If where is blank
        if ($where == '') {
            $where .= ' WHERE ';

        // else where is not blank, so add and
        } else {
            $where .= ' AND ';
        }

        // Set the query filter.
        $where .= "((contacts.member_id != '') AND (contacts.expiration_date < CURRENT_DATE()) AND (contacts.expiration_date != '0000-00-00'))";

        // Set membership filter and label
        $membership_filter = true;
        $membership_status_label = lang('Expired Member');

        // Change the heading and subheading.
        $heading = lang('My Expired Members');
        $subheading = lang('All my contacts with a member id and a lapsed expiration date.');
        break;

    case 'my_unregistered_members':

        // If where is blank
        if ($where == '') {
            $where .= ' WHERE ';

        // else where is not blank, so add and
        } else {
            $where .= ' AND ';
        }

        // Set the query filter.
        $where .= "((contact_user.user_contact IS NULL) AND (contacts.member_id != '') AND ((contacts.expiration_date >= CURRENT_DATE()) OR (contacts.expiration_date = '0000-00-00')))";

        // Set membership filter and label
        $membership_filter = true;
        $membership_status_label = lang('Unregistered Member');

        // Change the heading and subheading.
        $heading = lang('My Unregistered Members');
        $subheading = lang('All my contacts with a member id and valid expiration date, but cannot login due to a missing user account.');
        break;

    case 'my_contacts_by_user':

        // If where is blank
        if ($where == '') {
            $where .= ' WHERE ';

        // else where is not blank, so add and
        } else {
            $where .= ' AND ';
        }


        // Set the query filter.
        $where .= "(contacts.id = contact_user.user_contact)";

        // Change the heading and subheading.
        $heading = lang('My Contacts by User');
        $subheading = lang('All my contacts that have also registered through the website.');
        break;
        
    case 'my_contacts_by_business_address':
        
        $sql_columns = 
            'contacts.business_address_1 as address_1,
            contacts.business_address_2 as address_2,
            contacts.business_city as city,
            contacts.business_state as state,
            contacts.business_country as country,
            contacts.business_zip_code as zip_code,';
        
        // If where is blank
        if ($where == '') {
            $where .= ' WHERE ';

        // else where is not blank, so add and
        } else {
            $where .= ' AND ';
        }

        // Set the query filter.
        $where .= "((((contacts.first_name != '') AND (contacts.last_name != '')) OR (contacts.company != '')) AND ((contacts.business_address_1 != '') AND (contacts.business_city != '') AND (contacts.business_state != '') AND (contacts.business_zip_code != '')))";

        // Change the heading and subheading.
        $heading = lang('My Contacts by Business Address');
        $subheading = lang('All my contacts that have a business address.');
        break;
        
    case 'my_contacts_by_home_address':

        $sql_columns = 
            'contacts.home_address_1 as address_1,
            contacts.home_address_2 as address_2,
            contacts.home_city as city,
            contacts.home_state as state,
            contacts.home_country as country,
            contacts.home_zip_code as zip_code,';

        // If where is blank
        if ($where == '') {
            $where .= ' WHERE ';

        // else where is not blank, so add and
        } else {
            $where .= ' AND ';
        }


        // Set the query filter.
        $where .= "((((contacts.first_name != '') AND (contacts.last_name != '')) OR (contacts.company != '')) AND ((contacts.home_address_1 != '') AND (contacts.home_city != '') AND (contacts.home_state != '') AND (contacts.home_zip_code != '')))";

        // Change the heading and subheading.
        $heading = lang('My Contacts by Home Address');
        $subheading = lang('All my contacts that have a home address.');
        break;
        
    case 'all_duplicate_contacts':
        $sql_columns = 
            'contacts.business_address_1,
            contacts.business_address_2,
            contacts.business_city,
            contacts.business_state,
            contacts.business_country,
            contacts.business_zip_code,
            contacts.home_address_1,
            contacts.home_address_2,
            contacts.home_city,
            contacts.home_state,
            contacts.home_country,
            contacts.home_zip_code,';
        
        $all_email_addresses = array();
        
        // get all e-mail addresses from database
        $query = "SELECT email_address FROM contacts WHERE email_address != '' ORDER BY email_address ASC";
        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
        
        // loop through the results in order to add them to array (we convert e-mail address to lowercase in order to prevent case comparison issues later)
        while ($row = mysqli_fetch_assoc($result)) {
            $all_email_addresses[] = mb_strtolower($row['email_address']);
        }
        
        $checked_email_addresses = array();
        $duplicate_email_addresses = array();
        
        // loop through all the email addresses to see if there are duplicates
        foreach ($all_email_addresses as $email_address) {
            // if the email address is in the checked array then it is a duplicate so add it to the duplicate email addresses array
            if (in_array($email_address, $checked_email_addresses) == TRUE) {
                $duplicate_email_addresses[] = $email_address;
            }
            
            // add this email to the checked email addresses array
            $checked_email_addresses[] = $email_address;
        }
        
        // remove duplicate email addresses from array so that we can build an sql where statement
        $duplicate_email_addresses = array_unique($duplicate_email_addresses);
        
        $duplicate_email_addresses_where_statement = '';
        
        // loop through the duplicate email addresses and build sql where statement so that we get only contacts that are duplicates
        foreach ($duplicate_email_addresses as $email_address) {
            if ($duplicate_email_addresses_where_statement != '') {
                $duplicate_email_addresses_where_statement .= ' OR ';
            }
            
            $duplicate_email_addresses_where_statement .= "(contacts.email_address = '" . escape($email_address) . "')";
        }
        
        // If where is blank
        if ($where == '') {
            $where .= ' WHERE ';
        
        // else where is not blank, so add and
        } else {
            $where .= ' AND ';
        }
        
        // if there are duplicate e-mail addresses to get then set the where statement to get them
        if ($duplicate_email_addresses_where_statement != '') {
            $where .= "(" . $duplicate_email_addresses_where_statement . ")";
        
        // else there are not any duplicate e-mail addresses so set the where statement to get a blank id, that way no contacts will be found for this screen
        } else {
            $where .= "(contacts.id = '')";
        }
        
        // Change the heading and subheading.
        $heading = lang('All Duplicate Contacts');
        $subheading = lang('All contacts that have a duplicate email address.');
        break;

    case'all_my_contacts':
    default:
        // Change the heading and subheading.
        $heading = lang('All My Contacts');
        $subheading = lang('All contacts, subscribers, members, and affiliates that I can edit.');
        break;
}

$my_contacts = 0;
$all_contacts = 0;

// Get contacts based on filter.
$query =
    "SELECT
        COUNT(DISTINCT contacts.id)
    FROM contacts
    LEFT JOIN user AS contact_user ON contacts.id = contact_user.user_contact
    $join_table
    $where";
$result = mysqli_query(db::$con, $query) or output_error('Query failed.');
$row = mysqli_fetch_row($result);
$all_contacts = $row[0];

// get all contact groups (the array will be used in multiple places in this script)
$query =
    "SELECT
        id,
        name
    FROM contact_groups
    ORDER BY name";
$result = mysqli_query(db::$con, $query) or output_error('Query failed.');

$contact_groups = array();

// create contact group selection list
while ($row = mysqli_fetch_assoc($result)) {
    // if user has access to this contact group, then add contact group to array
    if (validate_contact_group_access($user, $row['id']) == true) {
        $contact_groups[] = $row;
    }
}

// If the user is a basic user
if ($user['role'] == 3) {
    // If where is blank
    if ($where == '') {
        $my_contacts_where = ' WHERE ';

    // else where is not blank, so add and
    } else {
        $my_contacts_where = $where . ' AND ';
    }

    $my_contacts_where .= '(';

    // Set loop counter to zero.
    $loop_count = 0;

    // Loop through the contact groups the user has access to.
    foreach($contact_groups as $contact_group) {

        // If the loop has ran at least once add OR.
        if ($loop_count > 0) {
            $my_contacts_where    .= ' OR ';
        }

        // Add the condition to the sql statement.
        $my_contacts_where .= '(contact_group_id = ' . $contact_group['id'] . ')';

        // Increment the counter. After once loop this doesn't matter any more.
        $loop_count++;
    }

    $my_contacts_where .= ')';

    // Get number of contacts that user has access to.
    $query = "
              SELECT
                 COUNT(DISTINCT contacts_contact_groups_xref.contact_id)
              FROM contacts
              LEFT JOIN user AS contact_user ON contacts.id = contact_user.user_contact
              LEFT JOIN contacts_contact_groups_xref ON contacts_contact_groups_xref.contact_id = contacts.id
              $join_table
              $my_contacts_where";
    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
    $row = mysqli_fetch_row($result);
    $my_contacts = $row[0];

// If user is manager or above.
} else {
    $my_contacts = $all_contacts;
}

// if advanced filters value was passed in the query string
if (isset($_REQUEST['advanced_filters']) == true) {
    // if advanced filters should be turned on
    if ($_REQUEST['advanced_filters'] == 'true') {
        $_SESSION['software']['view_contacts']['advanced_filters'] = true;

    // else advanced filters should be turned off
    } else {
        $_SESSION['software']['view_contacts']['advanced_filters'] = false;
    }
}

// if contact group is not set yet, set default to [All]
if (isset($_SESSION['software']['view_contacts']['contact_group']) == false) {
    $_SESSION['software']['view_contacts']['contact_group'] = '[' . lang('All') . ']';
}

// if filter is set to all dupliate contacts then set the advanced filter varable to false so that none of the logic is ran for the filters,
// and set the contact groups filter to all so that all contact groups are used
if ($filter == 'all_duplicate_contacts') {
    $advanced_filters = FALSE;
    $contact_groups_filter = '[' . lang('All') . ']';
    
// else use the session values
} else {
    $advanced_filters = ($_SESSION['software']['view_contacts']['advanced_filters'] ?? '');
    $contact_groups_filter = ($_SESSION['software']['view_contacts']['contact_group'] ?? '');
}

// if advanced filters are on and contact groups have not already been set in session, set default for contact groups in advanced filters
if (($advanced_filters == true) && (isset($_SESSION['software']['view_contacts']['contact_groups']) == false)) {
    // if contact group is set to all, prepare contact groups so all will be checked
    if ($contact_groups_filter == '[' . lang('All') . ']') {
        foreach ($contact_groups as $contact_group) {
            $_SESSION['software']['view_contacts']['contact_groups'][] = $contact_group['id'];
        }

    // else contact group is not set to all
    } else {
        $_SESSION['software']['view_contacts']['contact_groups'][] = $contact_groups_filter;
    }
}

$decrease_year = array();
$current_year = array();
$increase_year = array();
$decrease_month = array();
$current_month = array();
$increase_month = array();
$decrease_week = array();
$current_week = array();
$increase_week = array();
$decrease_day = array();
$current_day = array();
$increase_day = array();

$output_date_range_time = '';
$show_hide_date_range = '';

// if the filter is not set to all duplicate contacts, then run logic to get date range
if ($filter != 'all_duplicate_contacts') {
    // find the oldest timestamp (this will be used later in a couple of places)
    $query = "SELECT MIN(timestamp) FROM contacts";
    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
    $row = mysqli_fetch_row($result);
    $oldest_timestamp = $row[0];

    // if date has not been set in the session yet, populate start and stop days with default,
    // which is the oldest contact timestamp date to today's date
    if (isset($_SESSION['software']['view_contacts']['start_month']) == false) {
        $_SESSION['software']['view_contacts']['start_month'] = date('m', $oldest_timestamp);
        $_SESSION['software']['view_contacts']['start_day'] = date('d', $oldest_timestamp);
        $_SESSION['software']['view_contacts']['start_year'] = date('Y', $oldest_timestamp);

        $_SESSION['software']['view_contacts']['stop_month'] = date('m');
        $_SESSION['software']['view_contacts']['stop_day'] = date('d');
        $_SESSION['software']['view_contacts']['stop_year'] = date('Y');
    }

    $decrease_year['start_month'] = '01';
    $decrease_year['start_day'] = '01';
   $decrease_year['start_year'] = (int)($_SESSION['software']['view_contacts']['start_year'] ?? '') - 1;
    $decrease_year['stop_month'] = '12';
    $decrease_year['stop_day'] = '31';
    $decrease_year['stop_year'] = (int)($_SESSION['software']['view_contacts']['start_year'] ?? '') - 1;

    $current_year['start_month'] = '01';
    $current_year['start_day'] = '01';
    $current_year['start_year'] = date('Y');
    $current_year['stop_month'] = '12';
    $current_year['stop_day'] = '31';
    $current_year['stop_year'] = date('Y');

    $increase_year['start_month'] = '01';
    $increase_year['start_day'] = '01';
    $increase_year['start_year'] = (int)($_SESSION['software']['view_contacts']['start_year'] ?? '') + 1;
    $increase_year['stop_month'] = '12';
    $increase_year['stop_day'] = '31';
    $increase_year['stop_year'] = (int)($_SESSION['software']['view_contacts']['start_year'] ?? '') + 1;

    $decrease_month['new_time'] = mktime(0, 0, 0, (int)($_SESSION['software']['view_contacts']['start_month'] ?? '') - 1, 1, (int)($_SESSION['software']['view_contacts']['start_year'] ?? ''));
    $decrease_month['new_month'] = date('m', $decrease_month['new_time']);
    $decrease_month['new_year'] = date('Y', $decrease_month['new_time']);
    $decrease_month['start_month'] = $decrease_month['new_month'];
    $decrease_month['start_day'] = '01';
    $decrease_month['start_year'] = $decrease_month['new_year'];
    $decrease_month['stop_month'] = $decrease_month['new_month'];
    $decrease_month['stop_day'] = date('t', $decrease_month['new_time']);
    $decrease_month['stop_year'] = $decrease_month['new_year'];

    $current_month['new_month'] = date('m');
    $current_month['new_year'] = date('Y');
    $current_month['start_month'] = $current_month['new_month'];
    $current_month['start_day'] = '01';
    $current_month['start_year'] = $current_month['new_year'];
    $current_month['stop_month'] = $current_month['new_month'];
    $current_month['stop_day'] = date('t');
    $current_month['stop_year'] = $current_month['new_year'];

    $increase_month['new_time'] = mktime(0, 0, 0, (int)($_SESSION['software']['view_contacts']['start_month'] ?? '') + 1, 1, (int)($_SESSION['software']['view_contacts']['start_year'] ?? ''));
    $increase_month['new_month'] = date('m', $increase_month['new_time']);
    $increase_month['new_year'] = date('Y', $increase_month['new_time']);
    $increase_month['start_month'] = $increase_month['new_month'];
    $increase_month['start_day'] = '01';
    $increase_month['start_year'] = $increase_month['new_year'];
    $increase_month['stop_month'] = $increase_month['new_month'];
    $increase_month['stop_day'] = date('t', $increase_month['new_time']);
    $increase_month['stop_year'] = $increase_month['new_year'];

    $decrease_week['start_date_timestamp'] = mktime(0, 0, 0, (int)($_SESSION['software']['view_contacts']['start_month'] ?? ''), (int)($_SESSION['software']['view_contacts']['start_day'] ?? ''), (int)($_SESSION['software']['view_contacts']['start_year'] ?? ''));
    // if start date is a Sunday, use last Sunday (add 12:00:00 to prevent a bug that results in Saturday being returned)
    if (date('l', $decrease_week['start_date_timestamp']) == 'Sunday') {
        $decrease_week['new_time_start'] = strtotime('last sunday 12:00:00', $decrease_week['start_date_timestamp']);

    // else start date is not a Sunday, so we need to do last sunday twice (add 12:00:00 to prevent a bug that results in Saturday being returned)
    } else {
        $decrease_week['new_time_start'] = strtotime('last sunday 12:00:00', $decrease_week['start_date_timestamp']);
        $decrease_week['new_time_start'] = strtotime('last sunday 12:00:00', $decrease_week['new_time_start']);
    }
    $decrease_week['new_time_stop'] = strtotime('Saturday', $decrease_week['new_time_start']);
    $decrease_week['start_month'] = date('m', $decrease_week['new_time_start']);
    $decrease_week['start_day'] = date('d', $decrease_week['new_time_start']);
    $decrease_week['start_year'] = date('Y', $decrease_week['new_time_start']);
    $decrease_week['stop_month'] = date('m', $decrease_week['new_time_stop']);
    $decrease_week['stop_day'] = date('d', $decrease_week['new_time_stop']);
    $decrease_week['stop_year'] = date('Y', $decrease_week['new_time_stop']);

    // if today is Sunday
    if (date('l') == 'Sunday') {
        $current_week['new_time_start'] = strtotime('Sunday');
    } else {
        $current_week['new_time_start'] = strtotime('last Sunday');
    }
    $current_week['new_time_stop'] = strtotime('Saturday', $current_week['new_time_start']);
    $current_week['start_month'] = date('m', $current_week['new_time_start']);
    $current_week['start_day'] = date('d', $current_week['new_time_start']);
    $current_week['start_year'] = date('Y', $current_week['new_time_start']);
    $current_week['stop_month'] = date('m', $current_week['new_time_stop']);
    $current_week['stop_day'] = date('d', $current_week['new_time_stop']);
    $current_week['stop_year'] = date('Y', $current_week['new_time_stop']);

    $increase_week['start_date_timestamp'] = mktime(0, 0, 0, (int)($_SESSION['software']['view_contacts']['start_month'] ?? ''), (int)($_SESSION['software']['view_contacts']['start_day'] ?? ''), (int)($_SESSION['software']['view_contacts']['start_year'] ?? ''));
    // if start date is a Sunday
    if (date('l', $increase_week['start_date_timestamp']) == 'Sunday') {
        $increase_week['new_time_start'] = strtotime('2 Sunday', $increase_week['start_date_timestamp']);
    } else {
        $increase_week['new_time_start'] = strtotime('Sunday', $increase_week['start_date_timestamp']);
    }
    $increase_week['new_time_stop'] = strtotime('Saturday', $increase_week['new_time_start']);
    $increase_week['start_month'] = date('m', $increase_week['new_time_start']);
    $increase_week['start_day'] = date('d', $increase_week['new_time_start']);
    $increase_week['start_year'] = date('Y', $increase_week['new_time_start']);
    $increase_week['stop_month'] = date('m', $increase_week['new_time_stop']);
    $increase_week['stop_day'] = date('d', $increase_week['new_time_stop']);
    $increase_week['stop_year'] = date('Y', $increase_week['new_time_stop']);

    $decrease_day['new_time'] = mktime(0, 0, 0, (int)($_SESSION['software']['view_contacts']['start_month'] ?? ''), (int)($_SESSION['software']['view_contacts']['start_day'] ?? '') - 1, (int)($_SESSION['software']['view_contacts']['start_year'] ?? ''));
    $decrease_day['new_month'] = date('m', $decrease_day['new_time']);
    $decrease_day['new_day'] = date('d', $decrease_day['new_time']);
    $decrease_day['new_year'] = date('Y', $decrease_day['new_time']);
    $decrease_day['start_month'] = $decrease_day['new_month'];
    $decrease_day['start_day'] = $decrease_day['new_day'];
    $decrease_day['start_year'] = $decrease_day['new_year'];
    $decrease_day['stop_month'] = $decrease_day['new_month'];
    $decrease_day['stop_day'] = $decrease_day['new_day'];
    $decrease_day['stop_year'] = $decrease_day['new_year'];

    $current_day['new_month'] = date('m');
    $current_day['new_day'] = date('d');
    $current_day['new_year'] = date('Y');
    $current_day['start_month'] = $current_day['new_month'];
    $current_day['start_day'] = $current_day['new_day'];
    $current_day['start_year'] = $current_day['new_year'];
    $current_day['stop_month'] = $current_day['new_month'];
    $current_day['stop_day'] = $current_day['new_day'];
    $current_day['stop_year'] = $current_day['new_year'];

    $increase_day['new_time'] = mktime(0, 0, 0, (int)($_SESSION['software']['view_contacts']['start_month'] ?? ''), (int)($_SESSION['software']['view_contacts']['start_day'] ?? '') + 1, (int)($_SESSION['software']['view_contacts']['start_year'] ?? ''));
    $increase_day['new_month'] = date('m', $increase_day['new_time']);
    $increase_day['new_day'] = date('d', $increase_day['new_time']);
    $increase_day['new_year'] = date('Y', $increase_day['new_time']);
    $increase_day['start_month'] = $increase_day['new_month'];
    $increase_day['start_day'] = $increase_day['new_day'];
    $increase_day['start_year'] = $increase_day['new_year'];
    $increase_day['stop_month'] = $increase_day['new_month'];
    $increase_day['stop_day'] = $increase_day['new_day'];
    $increase_day['stop_year'] = $increase_day['new_year'];

    // get timestamps for start and stop dates
    $start_timestamp = mktime(0, 0, 0, (int)($_SESSION['software']['view_contacts']['start_month'] ?? ''), (int)($_SESSION['software']['view_contacts']['start_day'] ?? ''), (int)($_SESSION['software']['view_contacts']['start_year'] ?? ''));
    $stop_timestamp = mktime(23, 59, 59, (int)($_SESSION['software']['view_contacts']['stop_month'] ?? ''), (int)($_SESSION['software']['view_contacts']['stop_day'] ?? ''), (int)($_SESSION['software']['view_contacts']['stop_year'] ?? ''));

    // If where is blank
    if ($where == '') {
        $where .= ' WHERE ';

    // else where is not blank, so add and
    } else {
        $where .= ' AND ';
    }

    $where .= "(contacts.timestamp >= $start_timestamp) AND (contacts.timestamp <= $stop_timestamp)";
    
    // Output start date range time
    $output_date_range_time = h(get_month_name_from_number(($_SESSION['software']['view_contacts']['start_month'] ?? '')) . ' ' . ($_SESSION['software']['view_contacts']['start_day'] ?? '') . ', ' . ($_SESSION['software']['view_contacts']['start_year'] ?? ''));
    $output_date_range_time .= ' - ';

    // Output end date range time
    $output_date_range_time .= h(get_month_name_from_number(($_SESSION['software']['view_contacts']['stop_month'] ?? '')) . ' ' . ($_SESSION['software']['view_contacts']['stop_day'] ?? '') . ', ' . ($_SESSION['software']['view_contacts']['stop_year'] ?? ''));

// else this is the all duplicate contacts filter so hide the date range
} else {
    $show_hide_date_range = ' d-none';
}

// if advanced filters are on, prepare SQL for checked contact groups
if ($advanced_filters == true) {
    // if at least one contact group is checked
    if (is_array(($_SESSION['software']['view_contacts']['contact_groups'] ?? array())) == true) {
        $where_contact_groups = '';

        foreach (($_SESSION['software']['view_contacts']['contact_groups'] ?? array()) as $contact_group) {
            // if this is not the first contact group, then add an OR before SQL
            if ($where_contact_groups) {
                $where_contact_groups .= " OR";
            }

            // if contact group is [None]
            if ($contact_group == '[' . lang('None') . ']') {
                $where_contact_groups .= " (contacts_contact_groups_xref.contact_group_id IS NULL)";

            // else contact group is not [None]
            } else {
                $where_contact_groups .= " (contacts_contact_groups_xref.contact_group_id = '" . escape($contact_group) . "')";
            }
        }

        if ($where_contact_groups) {
            $where .= " AND ($where_contact_groups)";
        } else {
            $where .= " AND (0 = 1)";
        }

    // else no contact groups are checked, so use SQL that will result in no contacts being found
    } else {
        $where .= " AND (0 = 1)";
    }

// else advanced filters are off, so use contact group picklist
} else {
    // if user has selected [All] and user is greater than user role, then do not add any where clause for contact group, because all contact groups are valid
    if (($contact_groups_filter == '[' . lang('All') . ']') && ($user['role'] < 3)) {
        // do nothing

    // else if user has selected [All] and user has a user role, then prepare where clause for all contact groups that user has access to
    } elseif (($contact_groups_filter == '[' . lang('All') . ']') && ($user['role'] == 3)) {
        $where_contact_groups = '';

        foreach ($contact_groups as $contact_group) {
            // if this is not the first contact group, then add an OR before SQL
            if ($where_contact_groups) {
                $where_contact_groups .= " OR";
            }

            $where_contact_groups .= " (contacts_contact_groups_xref.contact_group_id = '" . escape($contact_group['id']) . "')";
        }

        if ($where_contact_groups) {
            $where .= " AND ($where_contact_groups)";
        } else {
            $where .= " AND (0 = 1)";
        }

    // else if user selected [None]
    } elseif ($contact_groups_filter == '[' . lang('None') . ']') {
        $where .= " AND (contacts_contact_groups_xref.contact_group_id IS NULL)";

    // else user selected a contact group
    } else {
        $where .= " AND (contacts_contact_groups_xref.contact_group_id = '" . escape($contact_groups_filter) . "')";
    }
}


// if advanced filters are on, prepare SQL
if ($advanced_filters == true) {
    if (($_SESSION['software']['view_contacts']['salutation'] ?? '')) {$where .= " AND (contacts.salutation LIKE '%" . escape(($_SESSION['software']['view_contacts']['salutation'] ?? '')) . "%')";}
    if (($_SESSION['software']['view_contacts']['first_name'] ?? '')) {$where .= " AND (contacts.first_name LIKE '%" . escape(($_SESSION['software']['view_contacts']['first_name'] ?? '')) . "%')";}
    if (($_SESSION['software']['view_contacts']['last_name'] ?? '')) {$where .= " AND (contacts.last_name LIKE '%" . escape(($_SESSION['software']['view_contacts']['last_name'] ?? '')) . "%')";}
    if (($_SESSION['software']['view_contacts']['suffix'] ?? '')) {$where .= " AND (contacts.suffix LIKE '%" . escape(($_SESSION['software']['view_contacts']['suffix'] ?? '')) . "%')";}
    if (($_SESSION['software']['view_contacts']['nickname'] ?? '')) {$where .= " AND (contacts.nickname LIKE '%" . escape(($_SESSION['software']['view_contacts']['nickname'] ?? '')) . "%')";}
    if (($_SESSION['software']['view_contacts']['company'] ?? '')) {$where .= " AND (contacts.company LIKE '%" . escape(($_SESSION['software']['view_contacts']['company'] ?? '')) . "%')";}
    if (($_SESSION['software']['view_contacts']['title'] ?? '')) {$where .= " AND (contacts.title LIKE '%" . escape(($_SESSION['software']['view_contacts']['title'] ?? '')) . "%')";}
    if (($_SESSION['software']['view_contacts']['department'] ?? '')) {$where .= " AND (contacts.department LIKE '%" . escape(($_SESSION['software']['view_contacts']['department'] ?? '')) . "%')";}
    if (($_SESSION['software']['view_contacts']['office_location'] ?? '')) {$where .= " AND (contacts.office_location LIKE '%" . escape(($_SESSION['software']['view_contacts']['office_location'] ?? '')) . "%')";}
    if (($_SESSION['software']['view_contacts']['business_address_1'] ?? '')) {$where .= " AND (contacts.business_address_1 LIKE '%" . escape(($_SESSION['software']['view_contacts']['business_address_1'] ?? '')) . "%')";}
    if (($_SESSION['software']['view_contacts']['business_address_2'] ?? '')) {$where .= " AND (contacts.business_address_2 LIKE '%" . escape(($_SESSION['software']['view_contacts']['business_address_2'] ?? '')) . "%')";}
    if (($_SESSION['software']['view_contacts']['business_city'] ?? '')) {$where .= " AND (contacts.business_city LIKE '%" . escape(($_SESSION['software']['view_contacts']['business_city'] ?? '')) . "%')";}
    if (($_SESSION['software']['view_contacts']['business_state'] ?? '')) {$where .= " AND (contacts.business_state LIKE '%" . escape(($_SESSION['software']['view_contacts']['business_state'] ?? '')) . "%')";}
    if (($_SESSION['software']['view_contacts']['business_country'] ?? '')) {$where .= " AND (contacts.business_country LIKE '%" . escape(($_SESSION['software']['view_contacts']['business_country'] ?? '')) . "%')";}
    if (($_SESSION['software']['view_contacts']['business_zip_code'] ?? '')) {$where .= " AND (contacts.business_zip_code LIKE '%" . escape(($_SESSION['software']['view_contacts']['business_zip_code'] ?? '')) . "%')";}
    if (($_SESSION['software']['view_contacts']['business_phone'] ?? '')) {$where .= " AND (contacts.business_phone LIKE '%" . escape(($_SESSION['software']['view_contacts']['business_phone'] ?? '')) . "%')";}
    if (($_SESSION['software']['view_contacts']['business_fax'] ?? '')) {$where .= " AND (contacts.business_fax LIKE '%" . escape(($_SESSION['software']['view_contacts']['business_fax'] ?? '')) . "%')";}
    if (($_SESSION['software']['view_contacts']['home_address_1'] ?? '')) {$where .= " AND (contacts.home_address_1 LIKE '%" . escape(($_SESSION['software']['view_contacts']['home_address_1'] ?? '')) . "%')";}
    if (($_SESSION['software']['view_contacts']['home_address_2'] ?? '')) {$where .= " AND (contacts.home_address_2 LIKE '%" . escape(($_SESSION['software']['view_contacts']['home_address_2'] ?? '')) . "%')";}
    if (($_SESSION['software']['view_contacts']['home_city'] ?? '')) {$where .= " AND (contacts.home_city LIKE '%" . escape(($_SESSION['software']['view_contacts']['home_city'] ?? '')) . "%')";}
    if (($_SESSION['software']['view_contacts']['home_state'] ?? '')) {$where .= " AND (contacts.home_state LIKE '%" . escape(($_SESSION['software']['view_contacts']['home_state'] ?? '')) . "%')";}
    if (($_SESSION['software']['view_contacts']['home_country'] ?? '')) {$where .= " AND (contacts.home_country LIKE '%" . escape(($_SESSION['software']['view_contacts']['home_country'] ?? '')) . "%')";}
    if (($_SESSION['software']['view_contacts']['home_zip_code'] ?? '')) {$where .= " AND (contacts.home_zip_code LIKE '%" . escape(($_SESSION['software']['view_contacts']['home_zip_code'] ?? '')) . "%')";}
    if (($_SESSION['software']['view_contacts']['home_phone'] ?? '')) {$where .= " AND (contacts.home_phone LIKE '%" . escape(($_SESSION['software']['view_contacts']['home_phone'] ?? '')) . "%')";}
    if (($_SESSION['software']['view_contacts']['home_fax'] ?? '')) {$where .= " AND (contacts.home_fax LIKE '%" . escape(($_SESSION['software']['view_contacts']['home_fax'] ?? '')) . "%')";}
    if (($_SESSION['software']['view_contacts']['mobile_phone'] ?? '')) {$where .= " AND (contacts.mobile_phone LIKE '%" . escape(($_SESSION['software']['view_contacts']['mobile_phone'] ?? '')) . "%')";}
    if (($_SESSION['software']['view_contacts']['email_address'] ?? '')) {$where .= " AND (contacts.email_address LIKE '%" . escape(($_SESSION['software']['view_contacts']['email_address'] ?? '')) . "%')";}
    if (($_SESSION['software']['view_contacts']['website'] ?? '')) {$where .= " AND (contacts.website LIKE '%" . escape(($_SESSION['software']['view_contacts']['website'] ?? '')) . "%')";}
    if (($_SESSION['software']['view_contacts']['lead_source'] ?? '')) {$where .= " AND (contacts.lead_source LIKE '%" . escape(($_SESSION['software']['view_contacts']['lead_source'] ?? '')) . "%')";}

    // If the subscribers filter is not on.
        if ($filter != 'my_subscribers') {
            if (($_SESSION['software']['view_contacts']['opt_in_status'] ?? '') == 'opt_in') {
                $where .= " AND (contacts.opt_in = '1')";
            } else if (($_SESSION['software']['view_contacts']['opt_in_status'] ?? '') == 'opt_out') {
                $where .= " AND (contacts.opt_in = '0')";
            }

            if (($_SESSION['software']['view_contacts']['description'] ?? '')) {$where .= " AND (contacts.description LIKE '%" . escape(($_SESSION['software']['view_contacts']['description'] ?? '')) . "%')";}

            if (($_SESSION['software']['view_contacts']['opt_in_status'] ?? '') == 'opt_in') {
                $where .= " AND (contacts.opt_in = '1')";
            } else if (($_SESSION['software']['view_contacts']['opt_in_status'] ?? '') == 'opt_out') {
                $where .= " AND (contacts.opt_in = '0')";
            }
        }
     // If any of the Membership filters are not on.
        if (isset($membership_filter) == false) {
            // prepare SQL for membership status
            switch (($_SESSION['software']['view_contacts']['membership_status'] ?? '')) {

                case 'member':
                    $where .=
                        " AND (contacts.member_id != '')
                        AND (contacts.member_id IS NOT NULL)";
                    break;

                case 'active_member':
                    $where .=
                        " AND (contacts.member_id != '')
                        AND (contacts.member_id IS NOT NULL)
                        AND
                        (
                            (contacts.expiration_date >= CURRENT_DATE())
                            OR (contacts.expiration_date = '0000-00-00')
                            OR (contacts.expiration_date IS NULL)
                        )";

                    break;

                case 'expired_member':
                    $where .=
                        " AND (contacts.member_id != '')
                        AND (contacts.member_id IS NOT NULL)
                        AND (contacts.expiration_date < CURRENT_DATE())
                        AND (contacts.expiration_date != '0000-00-00')
                        AND (contacts.expiration_date IS NOT NULL)";
                    break;

                case 'unregistered_member':
                    $where .=
                        " AND (contact_user.user_contact IS NULL)
                        AND (contacts.member_id != '')
                        AND ((contacts.expiration_date >= CURRENT_DATE())
                        OR (contacts.expiration_date = '0000-00-00'))";
                    break;

                case 'non_member':
                    $where .=
                        " AND
                        (
                            (contacts.member_id = '')
                            OR (contacts.member_id IS NULL)
                        )";
                    break;
            }
        }
    if (($_SESSION['software']['view_contacts']['member_id'] ?? '')) {$where .= " AND (contacts.member_id LIKE '%" . escape(($_SESSION['software']['view_contacts']['member_id'] ?? '')) . "%')";}
    if (($_SESSION['software']['view_contacts']['expiration_date'] ?? '')) {$where .= " AND (contacts.expiration_date = '" . escape(prepare_form_data_for_input(($_SESSION['software']['view_contacts']['expiration_date'] ?? ''), 'date')) . "')";}

    if (AFFILIATE_PROGRAM == true) {
        if (($_SESSION['software']['view_contacts']['affiliate_name'] ?? '')) {$where .= " AND (contacts.affiliate_name LIKE '%" . escape(($_SESSION['software']['view_contacts']['affiliate_name'] ?? '')) . "%')";}
        if (($_SESSION['software']['view_contacts']['affiliate_code'] ?? '')) {$where .= " AND (contacts.affiliate_code LIKE '%" . escape(($_SESSION['software']['view_contacts']['affiliate_code'] ?? '')) . "%')";}
    }
}

// if user requested to export contacts, export contacts
if (($_GET['submit_data'] ?? '') == 'Export Contacts') {
    // force download dialog
    header("Content-type: text/csv");
    header("Content-disposition: attachment; filename=contacts.csv");

    if (AFFILIATE_PROGRAM == true) {
        $output_affiliate_headings = ',affiliate_approved,affiliate_name,affiliate_code,affiliate_commission_rate';
    }

    print 'first_name,last_name,nickname,company,title,department,office_location,business_address_1,business_address_2,business_city,business_state,business_country,business_zip_code,business_phone,business_fax,home_address_1,home_address_2,home_city,home_state,home_country,home_zip_code,home_phone,home_fax,mobile_phone,email_address,website,lead_source,opt_in,description,member_id,expiration_date' . $output_affiliate_headings . "\n";

    $number_of_contacts = 0;

    $query = "SELECT
                contacts.image,
                contacts.file_id,
                contacts.first_name,
                contacts.last_name,
                contacts.nickname,
                contacts.company,title,
                contacts.department,
                contacts.office_location,
                contacts.business_address_1,
                contacts.business_address_2,
                contacts.business_city,
                contacts.business_state,
                contacts.business_country,
                contacts.business_zip_code,
                contacts.business_phone,
                contacts.business_fax,
                contacts.home_address_1,
                contacts.home_address_2,
                contacts.home_city,
                contacts.home_state,
                contacts.home_country,
                contacts.home_zip_code,
                contacts.home_phone,
                contacts.home_fax,
                contacts.mobile_phone,
                contacts.email_address,
                contacts.website,
                contacts.lead_source,
                contacts.opt_in,
                contacts.description,
                contacts.member_id,
                contacts.expiration_date,
                contacts.affiliate_approved,
                contacts.affiliate_name,
                contacts.affiliate_code,
                contacts.affiliate_commission_rate
             FROM contacts
             LEFT JOIN user AS contact_user ON contacts.id = contact_user.user_contact
             LEFT JOIN contacts_contact_groups_xref ON contacts.id = contacts_contact_groups_xref.contact_id
             $join_table
             $where
             GROUP BY contacts.id
             ORDER BY contacts.last_name, contacts.first_name";

    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
    while($row = mysqli_fetch_assoc($result)) {
        // for each value in the row
        foreach ($row as $key => $value) {
           // replace quotation mark with two quotation marks
           $value = str_replace('"', '""', $value);
           // add quotation marks around value
           $value = '"' . $value . '"';
           // set new value
           $row[$key] = $value;
        }

        if (AFFILIATE_PROGRAM == true) {
            $output_affiliate_values = ',' . $row['affiliate_approved'] . ',' . $row['affiliate_name'] . ',' . $row['affiliate_code'] . ',' . $row['affiliate_commission_rate'];
        }

        print $row['first_name'] . ',' . $row['last_name'] . ',' . $row['nickname'] . ',' . $row['company'] . ',' . $row['title'] . ',' . $row['department'] . ',' . $row['office_location'] . ',' . $row['business_address_1'] . ',' . $row['business_address_2'] . ',' . $row['business_city'] . ',' . $row['business_state'] . ',' . $row['business_country'] . ',' . $row['business_zip_code'] . ',' . $row['business_phone'] . ',' . $row['business_fax'] . ',' . $row['home_address_1'] . ',' . $row['home_address_2'] . ',' . $row['home_city'] . ',' . $row['home_state'] . ',' . $row['home_country'] . ',' . $row['home_zip_code'] . ',' . $row['home_phone'] . ',' . $row['home_fax'] . ',' . $row['mobile_phone'] . ',' . $row['email_address'] . ',' . $row['website'] . ',' . $row['lead_source'] . ',' . $row['opt_in'] . ',' . $row['description'] . ',' . $row['member_id'] . ',' . $row['expiration_date'] . $output_affiliate_values . "\n";

        $number_of_contacts++;
    }

    log_activity(lang(array('string'=>'{var:1} contacts were exported','vars'=>$number_of_contacts)), $_SESSION['sessionusername']);
     
// if mass deletion is allowed and user requested to delete contacts, delete contacts
} elseif ((MASS_DELETION == true) && (($_GET['submit_data'] ?? '') == 'Delete Contacts')) {
    // get all contacts that need to be deleted
    $query =
        "SELECT contacts.id
        FROM contacts
        LEFT JOIN user AS contact_user ON contacts.id = contact_user.user_contact
        LEFT JOIN contacts_contact_groups_xref ON contacts.id = contacts_contact_groups_xref.contact_id
        $join_table
        $where
        GROUP BY contacts.id";
    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');

    $number_of_contacts = mysqli_num_rows($result);

    $contacts = array();

    // loop through all contacts that need to be deleted, so they can be added to array
    while ($row = mysqli_fetch_assoc($result)) {
        $contacts[] = $row;
    }

    // loop through all contacts that need to be deleted, so they can be deleted
    foreach ($contacts as $contact) {
        // delete contact
        $query = "DELETE FROM contacts WHERE id = '" . $contact['id'] . "'";
        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');

        // delete contact references in contacts_contact_groups_xref
        $query = "DELETE FROM contacts_contact_groups_xref WHERE contact_id = '" . $contact['id'] . "'";
        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');

        // delete contact references in opt_in
        $query = "DELETE FROM opt_in WHERE contact_id = '" . $contact['id'] . "'";
        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
    }

    // prepare list of contact groups for log

    $contact_group_list = '';

    // if advanced filters are on
    if ($advanced_filters == true) {
        if (is_array(($_SESSION['software']['view_contacts']['contact_groups'] ?? array())) == true) {
            foreach (($_SESSION['software']['view_contacts']['contact_groups'] ?? array()) as $contact_group) {
                if ($contact_group_list) {
                    $contact_group_list .= ', ';
                }

                // if this contact group is the [None] contact group
                if ($contact_group == '[' . lang('None') . ']') {
                    $contact_group_list .= '[' . lang('None') . ']';

                // else this contact group is not the [None] contact group, so get contact group name
                } else {
                    $query = "SELECT name FROM contact_groups WHERE id = '" . escape($contact_group) . "'";
                    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
                    $row = mysqli_fetch_assoc($result);

                    $contact_group_list .= $row['name'];
                }
            }
        }

    // else advanced filters are off
    } else {
        // if the [All] contact group was selected
        if ($contact_groups_filter == '[' . lang('All') . ']') {
            $contact_group_list = '[' . lang('All') . ']';

        // else if the [None] contact group was selected
        } elseif ($contact_groups_filter == '[' . lang('None') . ']') {
            $contact_group_list = '[' . lang('None') . ']';

        // else get group name
        } else {
            // get contact group name
            $query = "SELECT name FROM contact_groups WHERE id = '" . escape($contact_groups_filter) . "'";
            $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
            $row = mysqli_fetch_assoc($result);

            $contact_group_list = $row['name'];
        }
    }

    // if at least one contact was deleted
    if ($number_of_contacts > 0) {
        log_activity(lang(array('string'=>'{var:1} contact(s) from contact group(s) ({var:2}) were deleted','vars'=>array(number_format($number_of_contacts),$contact_group_list  ) )), $_SESSION['sessionusername']);

        $liveform->add_notice(lang(array('string'=>'{var:1} contact(s) from contact group(s) ({var:2}) were deleted','vars'=>array(number_format($number_of_contacts),$contact_group_list  ) )) );
    } else {
        $liveform->add_notice(lang('No contacts were deleted'));
    }

    header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/view_contacts.php');

// else, if the user selected to merge contacts, then merge them
} elseif (($_GET['submit_data'] ?? '') == 'Merge Contacts') {
    $contacts_to_merge = array();
    
    // get contacts to be merged information
    $query = 
        "SELECT
            contacts.image,
            contacts.file_id,
            contacts.id,
            contacts.salutation,
            contacts.first_name,
            contacts.last_name,
            contacts.suffix,
            contacts.nickname,
            contacts.company,
            contacts.title,
            contacts.department,
            contacts.office_location,
            contacts.business_address_1,
            contacts.business_address_2,
            contacts.business_city,
            contacts.business_state,
            contacts.business_country,
            contacts.business_zip_code,
            contacts.business_phone,
            contacts.business_fax,
            contacts.home_address_1,
            contacts.home_address_2,
            contacts.home_city,
            contacts.home_state,
            contacts.home_country,
            contacts.home_zip_code,
            contacts.home_phone,
            contacts.home_fax,
            contacts.mobile_phone,
            contacts.email_address,
            contacts.website,
            contacts.lead_source,
            contacts.opt_in,
            contacts.description,
            contacts.member_id,
            contacts.expiration_date,
            contacts.warning_expiration_date,
            contacts.affiliate_approved,
            contacts.affiliate_name,
            contacts.affiliate_code,
            contacts.affiliate_commission_rate,
            contacts.user,
            contact_user.user_id as user_id,
            contacts.timestamp
        FROM contacts
        LEFT JOIN user AS contact_user ON contacts.id = contact_user.user_contact
        LEFT JOIN contacts_contact_groups_xref ON contacts.id = contacts_contact_groups_xref.contact_id
        $join_table
        $where
        ORDER BY contacts.email_address ASC";
    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
    while($row = mysqli_fetch_assoc($result)) {
        $contacts_to_merge[] = $row;
    }

    // merge the contacts, then refresh the screen with a notice
    $number_of_merged_contacts = merge_contacts($contacts_to_merge);
    
    $notice = '';
    
    // if contacts were merged then output a notice informing the user how many were merged
    if ($number_of_merged_contacts > 0) {
        $notice = lang(array(
            'string'=>'{var:1} contact(s) were merged successfully',
            'vars'=>number_format($number_of_merged_contacts) ));
        log_activity(lang(array(
            'string'=>'{var:1} contact(s) were merged successfully',
            'vars'=>number_format($number_of_merged_contacts) )), $_SESSION['sessionusername']);
    
    // else output a notice informing the user that no contacts where merged.
    } else {
        $notice = lang('No contacts were merged. Contacts tied to User accounts cannot be merged.');
    }
    
    $liveform->add_notice($notice);
    
    header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/view_contacts.php?filter=all_duplicate_contacts');

// else user did not request to export contacts or to merge contacts, so view contacts
} else {
    // get minimum year from oldest timestamp
    $oldest_year = date('Y', $oldest_timestamp);
    if (($_SESSION['software']['view_contacts']['start_year'] ?? '') < $oldest_year) {
        $oldest_year = ($_SESSION['software']['view_contacts']['start_year'] ?? '');
    }

    $this_year = date('Y');
    if (($_SESSION['software']['view_contacts']['stop_year'] ?? '') > $this_year) {
        $this_year = ($_SESSION['software']['view_contacts']['stop_year'] ?? '');
    }

    $years = array();

    // create html for year options
    for ($i = $oldest_year; $i <= $this_year; $i++) {
        $years[] = $i;
    }

    // if sort was set, update session
    if (isset($_REQUEST['sort'])) {
        // store sort in session
        $_SESSION['software']['view_contacts']['sort'] = $_REQUEST['sort'];

        // clear order
        $_SESSION['software']['view_contacts']['order'] = '';
    }

    // if order was set, update session
    if (isset($_REQUEST['order'])) {
        $_SESSION['software']['view_contacts']['order'] = $_REQUEST['order'];
    }

    // If a screen was passed and it is a positive integer, then use it.
    // These checks are necessary in order to avoid SQL errors below for a bogus screen value.
    if (
        isset($_REQUEST['screen'])
        and $_REQUEST['screen']
        and is_numeric($_REQUEST['screen'])
        and $_REQUEST['screen'] > 0
        and $_REQUEST['screen'] == round($_REQUEST['screen'])
    ) {
        $screen = (int) $_REQUEST['screen'];

    // Otherwise, use the default, which is the first screen.
    } else {
        $screen = 1;
    }

    // if the advanced filters are not on, then prepare contact group picklist
    if ($advanced_filters == false) {
        $output_contact_group_options = '';

        // create contact group selection list
        foreach ($contact_groups as $contact_group) {
            // if the contact group is equal to selected contact group
            if ($contact_group['id'] == $contact_groups_filter) {
                $selected = ' selected="selected"';
            } else {
                $selected = '';
            }

            // get number of contacts in contact group
            $number_of_contacts = get_number_of_contacts($contact_group['id'], $require_email = false);

            $output_contact_group_options .= '<option value="' . $contact_group['id'] . '"' . $selected . '>' . h($contact_group['name']) . ' (' . number_format($number_of_contacts) . ')</option>';
        }

        // if user has a role that is greater than user role, then prepare to output [None] option
        if ($user['role'] < 3) {
            // if none contact group is selected
            if ($contact_groups_filter == '[' . lang('None') . ']') {
                $selected = ' selected="selected"';
            } else {
                $selected = '';
            }

            $number_of_contacts = get_number_of_contacts('[' . lang('None') . ']', $require_email = false);

            $output_contact_group_options ='<option value="[' . lang('None') . ']"' . $selected . '>[' . lang('None') . '] (' . number_format($number_of_contacts) . ')</option>' . $output_contact_group_options;
        }

        // if all contact group is selected
        if ($contact_groups_filter == '[' . lang('All') . ']') {
            $selected = ' selected="selected"';
        } else {
            $selected = '';
        }

        $output_contact_group_options = '<option value="[' . lang('All') . ']"' . $selected . '>[' . lang('All') . ']</option>' . $output_contact_group_options;
    }
    
    $sort_order = '';
    
    // if the filter is set to all duplicate contacts then hard set the sql statement to sort by the email addresses in alphabetical order,
    if ($filter == 'all_duplicate_contacts') {
        $sort_column = "email_address";
        $sort_order = 'asc';
        
    // else sort the view based on what the user selected
    } else {
        // if the sort is not set yet, then default it to empty so that the switch below falls
        // through to its default case
        if (isset($_SESSION['software']['view_contacts']['sort']) == false) {
            $_SESSION['software']['view_contacts']['sort'] = '';
        }

        switch (($_SESSION['software']['view_contacts']['sort'] ?? '')) {
            case lang('First Name'):
                $sort_column = 'first_name';
                break;

            case lang('Last Name'):
                $sort_column = 'last_name';
                break;

            case lang('Company'):
                $sort_column = 'company';
                break;

            case lang('Email'):
                $sort_column = 'email_address';
                break;

            case lang('User'):
                $sort_column = 'contact_user.user_username';
                break;

            case lang('Opt-In'):
                $sort_column = 'opt_in';
                break;
                
            case lang('City'):
                // if the my contacts by business address filter is on, then set sort column to business city
                if ($filter == 'my_contacts_by_business_address') {
                    $sort_column = 'business_city';
                    
                // else if the filter is set to my contacts by home address, then set the sort column to home city
                } elseif ($filter == 'my_contacts_by_home_address') {
                    $sort_column = 'home_city';
                
                // else set sort column to the default
                } else {
                    $sort_column = 'timestamp';
                    $_SESSION['software']['view_contacts']['sort'] = 'Last Modified';
                }
                
                break;
                
            case lang('State'):
                // if the my contacts by business address filter is on, then set sort column to business state
                if ($filter == 'my_contacts_by_business_address') {
                    $sort_column = 'business_state';
                    
                // else if the filter is set to my contacts by home address, then set the sort column to home state
                } elseif ($filter == 'my_contacts_by_home_address') {
                    $sort_column = 'home_state';
                
                // else set sort column to the default
                } else {
                    $sort_column = 'timestamp';
                    $_SESSION['software']['view_contacts']['sort'] = 'Last Modified';
                }
                
                break;
                
            case lang('Zip Code'):
                // if the my contacts by business address filter is on, then set sort column to business zip code
                if ($filter == 'my_contacts_by_business_address') {
                    $sort_column = 'business_zip_code';
                    
                // else if the filter is set to my contacts by home address, then set the sort column to home zipe code
                } elseif ($filter == 'my_contacts_by_home_address') {
                    $sort_column = 'home_zip_code';
                
                // else set sort column to the default
                } else {
                    $sort_column = 'timestamp';
                    $_SESSION['software']['view_contacts']['sort'] = 'Last Modified';
                }
                
                break;
                
            case lang('Country'):
                // if the my contacts by business address filter is on, then set sort column to business country
                if ($filter == 'my_contacts_by_business_address') {
                    $sort_column = 'business_country';
                    
                // else if the filter is set to my contacts by home address, then set the sort column to home country
                } elseif ($filter == 'my_contacts_by_home_address') {
                    $sort_column = 'home_zip_code';
                
                // else set sort column to the default
                } else {
                    $sort_column = 'timestamp';
                    $_SESSION['software']['view_contacts']['sort'] = 'Last Modified';
                }
                
                break;

            case lang('Last Modified'):
                $sort_column = 'timestamp';
                break;

            default:
                $sort_column = 'timestamp';
                $_SESSION['software']['view_contacts']['sort'] = lang('Last Modified');
                $_SESSION['software']['view_contacts']['order'] = 'desc';
                break;
        }

        if (!($_SESSION['software']['view_contacts']['order'] ?? '')) {
            $_SESSION['software']['view_contacts']['order'] = 'asc';
        }
        
        // if the sort order is blank then set it to the order in the session
        if ($sort_order == '') {
            $sort_order = ($_SESSION['software']['view_contacts']['order'] ?? '');
        }
    }
    


    // get total number of results for all screens, so that we can output links to different screens
    $query =
        "SELECT contacts.id
        FROM contacts
        LEFT JOIN user AS contact_user ON contacts.id = contact_user.user_contact
        LEFT JOIN contacts_contact_groups_xref ON contacts.id = contacts_contact_groups_xref.contact_id
        $join_table
        $where
        GROUP BY contacts.id";
    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
    $number_of_results = mysqli_num_rows($result);

   

    // if there are more than one screen
    if ($number_of_screens > 1) {

        $output_screen_links .= '
            <nav class="mt-3 navigation " aria-label="data pagination"> 
                <ul class="pagination pagination-sm flex-wrap justify-content-center">';
        // build Previous button if necessary
        $previous = $screen - 1;
        // if previous screen is greater than zero, output previous link
        if ($previous > 0) {
            $output_screen_links .= '<li class="page-item mt-1 mb-1"><a class="page-link" href="view_contacts.php?filter=' . h($filter) . '&screen=' . $previous . $output_filter_for_links . '" aria-label="Previous"><span aria-hidden="true">&laquo;</span></a></li>';
        }else{
            $output_screen_links .= '<li class="page-item mt-1 mb-1 disabled"><a class="page-link" href="#!" aria-label="Previous"><span aria-hidden="true">&laquo;</span></a></li>';
        }

        // build HTML output for links to screens
        for ($i = 1; $i <= $number_of_screens; $i++) {
            // if this number is the current screen, then select option
            if ($i == $screen) {
                $active = 'active"';
            // else this number is not the current screen, so do not select option
            } else {
                $active = '';
            }
            $output_screen_links .= '<li class="page-item mt-1 mb-1 ' . $active . '"><a class="page-link " href="view_contacts.php?filter=' . h($filter) . '&screen=' . $i . $output_filter_for_links . '">' . $i . '</a></li>';
        }
        // build Next button if necessary
        $next = $screen + 1;
        // if next screen is less than or equal to the total number of screens, output next link
        if ($next <= $number_of_screens) {
            $output_screen_links .= '<li class="page-item mt-1 mb-1"><a class="page-link" href="view_contacts.php?filter=' . h($filter) . '&screen=' . $next . $output_filter_for_links . '" aria-label="Next"><span aria-hidden="true">&raquo;</span></a></li>';
        }else{
            $output_screen_links .= '<li class="page-item mt-1 mb-1 disabled"><a class="page-link" href="#!" aria-label="Next"><span aria-hidden="true">&raquo;</span></a></li>';
        }
        $output_screen_links .= '</ul></nav>';
    }

    // get results for just this screen
    $query =
        "SELECT
            contacts.image,
            contacts.file_id,
            contacts.id,
            contacts.first_name,
            contacts.last_name,
            contacts.company,
            contacts.business_phone,
            contacts.home_phone,
            contacts.mobile_phone,
            contacts.email_address,
            contacts.opt_in,
            $sql_columns
            last_modified_user.user_username as last_modified_username,
            contact_user.user_id as user_id,
            contact_user.user_username as user_username,
            contact_user.user_role as user_role,
            contacts.timestamp
        FROM contacts
        LEFT JOIN user AS contact_user ON contacts.id = contact_user.user_contact
        LEFT JOIN user AS last_modified_user ON contacts.user = last_modified_user.user_id
        LEFT JOIN contacts_contact_groups_xref ON contacts.id = contacts_contact_groups_xref.contact_id
        $join_table
        $where
        GROUP BY contacts.id
        ORDER BY $sort_column " . escape($sort_order) . " ";
    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');

    $contacts = array();

    // loop through all contacts, so they can be added to array
    while ($row = mysqli_fetch_assoc($result)) {
        $contacts[] = $row;
    }
    
    // if the filter is set to all duplicate contacts then organize the contacts to be sorted by e-mail address, and then by child orphan relationship
    if ($filter == 'all_duplicate_contacts') {
        $child_contacts = array();
        $organized_contacts = array();
        
        // loop through the contacts to put children contacts in their own array
        foreach ($contacts as $key => $contact) {
            // if the contact has a user id then it is a child so add it to the child contacts array
            if ($contact['user_id'] != '') {
                $child_contacts[$key] = $contact;
            }
        }
        
        // loop through all contacts to organize them
        foreach ($contacts as $contact) {
            // loop through all child contacts to see if there are any children for this contact, and add any matches to the organzied contacts array
            foreach ($child_contacts as $key => $child_contact) {
                // if the child's email address is the same as the contact's email address, then add it to the organized contacts array, 
                // and remove it from the other arrays so that it isn't found again
                if ($child_contact['email_address'] == $contact['email_address']) {
                    $organized_contacts[] = $child_contact;
                    
                    // remove contact from arrays so that this child contact is not found again
                    unset($contacts[$key]);
                    unset($child_contacts[$key]);
                }
            }
            
            // if this contact is not a child then add it to the organized contacts array
            if ($contact['user_id'] == '') {
                $organized_contacts[] = $contact;
            }
        }
        
        // if there are organized contacts, then unset the contacts array and set it to the organized contacts array
        if (count($organized_contacts) > 0) {
            unset($contacts);
            $contacts = $organized_contacts;
        }
    }
    
    // loop through all contacts that need to be outputted, so they can be outputted
    foreach ($contacts as $contact) {
        // Set link url
        $output_link_url = 'edit_contact.php?id=' . $contact['id'] . '&send_to=' . h(escape_javascript(REQUEST_URL));

        $output_opt_in_row = '';
        $output_phone_row = '';
        $output_email_address_row = '';
        $output_image_header ='';
        $output_image_column ='';
		//default show image
		$show_image = true;
		if($show_image == true){
			$output_image_header ='<th>' . lang(array('string'=>'Image') ) . '</th>';
            if($contact['file_id'] == 0){
                if($contact['image']){
                    // output image
                    $output_image_column ='<td class="align-middle text-start"><img style="width: 50px;height:50px;" class="img-fluid img-thumbnail lazy" src="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/assets/images/loading.gif" data-src="' . h($contact['image']) . '" /></td>';
                }else{
                    // output no image
                    $output_image_column ='<td class="align-middle text-start"><img style="width: 50px;height:50px;" class="img-fluid img-thumbnail lazy" src="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/assets/images/loading.gif" data-src="assets/images/person1.png" /></td>';
                }
            }else{
                //check file
                $query = 
                "SELECT 
                    files.name
                FROM files 
                WHERE files.id = '" . escape($contact['file_id']) . "'";
                $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
                $file = mysqli_fetch_array($result);
                $file_name = $file['name'];
                $output_image_column ='<td class="align-middle text-start"><img style="width: 50px;height:50px;" class="img-fluid img-thumbnail lazy" src="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/assets/images/loading.gif" data-src="' .  PATH . $file_name . '" /></td>';
            }
		}


        // if the filter is not set to either my contacts by business address or my contacts by home address then output my contacts by user, opt in, phone and e-mail address rows
        if (($filter != 'my_contacts_by_business_address') && ($filter != 'my_contacts_by_home_address')) {
            $user_display = '';
            
            // if there is a user for this contact
            if ($contact['user_id']) {
                // if the editor user is an administrator or the editor user has access to edit this user, then prepare username with link
                if (($user['role'] == 0) || ($user['role'] < $contact['user_role'])) {
                    $user_display = '<a class="link-secondary" href="edit_user.php?id=' . $contact['user_id'] . '">' . h($contact['user_username']) . '</a>';

                // else the editor user does not have access to edit this user, so prepare username without link
                } else {
                    $user_display = h($contact['user_username']);
                }
            }

            // Show the user column column.
            $output_user_row = '<td class="align-middle">' . $user_display . '</td>';

            // If the contact is opted in then prepare the checkmark.
            if ($contact['opt_in']) {
                $opt_in_display = '<span class="material-icons">task_alt</span>';
            } else {
                $opt_in_display = '';
            }
            
            // output opt in row
            $output_opt_in_row = '<td class="align-middle text-center">' . $opt_in_display . '</td>';
            
            $output_phone_numbers = '';
            
            // build phone numbers to be outputted in the Phone row
            if ($contact['business_phone'] != '') {
                $output_phone_numbers .= lang('Business') . ': ' . h($contact['business_phone']);
            }
            
            if ($contact['home_phone'] != '') {
                if ($output_phone_numbers != '') {
                    $output_phone_numbers .= '<br />';
                }
                $output_phone_numbers .= lang('Home') . ': ' . h($contact['home_phone']);
            }
            
            if ($contact['mobile_phone'] != '') {
                if ($output_phone_numbers != '') {
                    $output_phone_numbers .= '<br />';
                }
                $output_phone_numbers .= lang('Mobile') . ': ' . h($contact['mobile_phone']);
            }
            
            // output phone row
            $output_phone_row  = '<td class="align-middle">' . $output_phone_numbers . '</td>';
            
            // output email address row
            $output_email_address_row  = '<td class="align-middle"><a class="link-secondary" href="mailto:' . h($contact['email_address']) . '">' . h($contact['email_address']) . '</a></td>';
        }
        
        $output_contact_groups = '';
        
        // If show groups is on, or the filter is set to all duplicate contacts then get contact groups.
        if ((($_SESSION['software']['view_contacts']['show_contact_groups'] ?? '') == true) || ($filter == 'all_duplicate_contacts')) {
            // get contact groups that this contact is in
            $query = "SELECT contact_group_id FROM contacts_contact_groups_xref WHERE contact_id = '" . $contact['id'] . "'";
            $result = mysqli_query(db::$con, $query) or output_error('Query failed.');

            $contact_groups_for_contact = array();

            // loop through all contacts, so they can be added to array
            while ($row = mysqli_fetch_assoc($result)) {
                $contact_groups_for_contact[] = $row['contact_group_id'];
            }

            // loop through all contact groups that this user has access to in order to prepare list of contact groups to output for this contact
            foreach ($contact_groups as $contact_group) {
                // if contact is in this contact group, then prepare to output contact group
                if (in_array($contact_group['id'], $contact_groups_for_contact) == true) {
                    if ($output_contact_groups) {
                        $output_contact_groups .= ',<br />';
                    }

                    $output_contact_groups .= h($contact_group['name']);
                }
            }
        }
        
        $output_address_rows = '';
        
        // If the filter is set to contacts by business address or contacts by home address then output address rows
        if (($filter == 'my_contacts_by_business_address') || ($filter == 'my_contacts_by_home_address')) {
            // set the street address line 1
            $output_street_address = h($contact['address_1']);
            
            // if there is a second street address, then combine address line 1 with address line 2 to get the full street address
            if ($contact['address_2'] != '') {
                $output_street_address .= ',<br />' . h($contact['address_2']);
            }
            
            // output address rows
            $output_address_rows = 
                '<td class="align-middle">' . $output_street_address . '</td>
                <td class="align-middle">' . h($contact['city']) . '</td>
                <td class="align-middle">' . h($contact['state']) . '</td>
                <td class="align-middle">' . h($contact['zip_code']) . '</td>
                <td class="align-middle">' . h($contact['country']) . '</td>';
        }
        
        // if the all duplicate contacts filter is on then build address row and output the appropriate table layout
        if ($filter == 'all_duplicate_contacts') {
            // dynamically build a business address string to be outputted
            $business_address = '';
            
            if ($contact['business_address_1'] != '') {
                $business_address .= $contact['business_address_1'];
            }
            
            if ($contact['business_address_2'] != '') {
                if ($business_address != '') {
                    $business_address .= ' ';
                }
                
                $business_address .= $contact['business_address_2'];
            }
            
            if ($contact['business_city'] != '') {
                if ($business_address != '') {
                    $business_address .= ' ';
                }
                
                $business_address .= $contact['business_city'];
            }
            
            if ($contact['business_state'] != '') {
                if ($business_address != '') {
                    $business_address .= ', ';
                }
                
                $business_address .= $contact['business_state'];
            }
            
            if ($contact['business_zip_code'] != '') {
                if ($business_address != '') {
                    $business_address .= ' ';
                }
                
                $business_address .= $contact['business_zip_code'];
            }
            
            if ($contact['business_country'] != '') {
                if ($business_address != '') {
                    $business_address .= ' ';
                }
                
                $business_address .= $contact['business_country'];
            }
            
            if ($business_address != '') {
                $business_address = lang('Business') . ': ' . $business_address;
            }
            
            // remove any double spaces that may have been entered or created
            $business_address = str_replace("  ", " ", $business_address);
            
            // dynamically build a home address string to be outputted
            $home_address = '';
            
            if ($contact['home_address_1'] != '') {
                $home_address .= $contact['home_address_1'];
            }
            
            if ($contact['home_address_2'] != '') {
                if ($home_address != '') {
                    $home_address .= ' ';
                }
                
                $home_address .= $contact['home_address_2'];
            }
            
            if ($contact['home_city'] != '') {
                if ($home_address != '') {
                    $home_address .= ' ';
                }
                
                $home_address .= $contact['home_city'];
            }
            
            if ($contact['home_state'] != '') {
                if ($home_address != '') {
                    $home_address .= ', ';
                }
                
                $home_address .= $contact['home_state'];
            }
            
            if ($contact['home_zip_code'] != '') {
                if ($home_address != '') {
                    $home_address .= ' ';
                }
                
                $home_address .= $contact['home_zip_code'];
            }
            
            if ($contact['home_country'] != '') {
                if ($home_address != '') {
                    $home_address .= ' ';
                }
                
                $home_address .= $contact['home_country'];
            }
            
            if ($home_address != '') {
                $home_address = lang('Home') . ': ' . $home_address;
            }
            
            // remove any double spaces that may have been entered or created
            $home_address = str_replace("  ", " ", $home_address);
            
            $output_address_row = '';
            
            // if there is a business address then output it
            if ($business_address != '') {
                $output_address_row .= h($business_address);
            }
            
            // if there is a home address then output it
            if ($home_address != '') {
                // if there is a business address then add a break tag to get the home address on it's own line
                if ($business_address != '') {
                    $output_address_row .= '<br />';
                }
                
                $output_address_row .= h($home_address);
            }
            
            $output_address_row = '<td class="align-middle">' . $output_address_row . '</td>';
            
            $output_rows .=
                '<tr id="' . $contact['id'] . '">
                    <td class="select-all align-middle text-start"><input class="form-check-input " type="checkbox" name="contacts[]" value="' . $contact['id'] . '" class="checkbox" /></td>
                    <td class="align-middle text-start col-reorder-none action-buttons">
                        <button type="button" class="btn-data-control m-1 btn btn-outline-primary border-2" data-loading-content=" " title="' . lang('Edit') . '" onclick="window.location.href=\'' . $output_link_url . '\'"><i class="bi bi-pencil"></i></button>
                        <!--<button type="button" class="btn-data-control m-1 btn btn-outline-danger border-2" data-loading-content=" " title="' . lang('Delete') . '" ><i class="material-icons">delete</i></button>-->
                    </td>
                    ' . $output_image_column . '
                    ' . $output_email_address_row . '
                    <td class="align-middle">' . h($contact['first_name']) . '</td>
                    <td class="align-middle">' . h($contact['last_name']) . '</td>
                    <td class="align-middle">' . h($contact['company']) . '</td>
                    ' . $output_address_row . '
                    ' . $output_phone_row . '
                    ' . $output_user_row . '
                    <td class="align-middle">' . $output_contact_groups . '</td>
                    <td class="align-middle">' . get_relative_time(array('timestamp' => $contact['timestamp'])) . '<br />' . h($contact['last_modified_username']) . '</td>
                </tr>';
            
        // else output the standard table layout
        } else {
            $output_rows .=
                '<tr id="' . $contact['id'] . '">
                    <td class="select-all align-middle text-start"><input class="form-check-input " type="checkbox" name="contacts[]" value="' . $contact['id'] . '" class="checkbox" /></td>
                    <td class="align-middle text-start col-reorder-none action-buttons">
                        <button type="button" class="btn-data-control m-1 btn btn-outline-primary border-2" data-loading-content=" " title="' . lang('Edit') . '" onclick="window.location.href=\'' . $output_link_url . '\'"><i class="bi bi-pencil"></i></button>
                        <!--<button type="button" class="btn-data-control m-1 btn btn-outline-danger border-2" data-loading-content=" " title="' . lang('Delete') . '" ><i class="material-icons">delete</i></button>-->
                    </td>
                    ' . $output_image_column . '
                    <td class="align-middle">' . h($contact['first_name']) . '</td>
                    <td class="align-middle">' . h($contact['last_name']) . '</td>
                    <td class="align-middle">' . h($contact['company']) . '</td>
                    ' . $output_phone_row . '
                    ' . $output_email_address_row . '
                    ' . $output_user_row . '
                    ' . $output_opt_in_row . '
                    ' . $output_address_rows . '
                    <td class="align-middle">' . $output_contact_groups . '</td>
                    <td class="align-middle">' . get_relative_time(array('timestamp' => $contact['timestamp'])) . '<br />' . h($contact['last_modified_username']) . '</td>
                </tr>';
        }
    }

    // if the advanced filters are off
    if ($advanced_filters == false) {
    
        $output_contact_group_selection = '
        <div class="input-group input-group-sm'. $show_hide_contact_group_select .'">
            <label class="input-group-text mt-1 mb-1 material-icons" title="' . lang('Group') . '" for="contact_group">groups</label>
            <select id="contact_group" name="contact_group" class="form-select mt-1 mb-1" title="' . lang('Content that viewed') . '" onchange="submit_form(\'search_form\')">' . $output_contact_group_options . '</select>
        </div>';
        $output_advanced_filters_value = 'true';
        $output_advanced_filters_label = lang('Add Advanced Filters');
        $output_advanced_filters = '';
        $advanced_filters_icon = 'filter_list';
    
        // if the all duplicate contacts filter is on then hide the contact group select list and the advanced filters button
        if ($filter == 'all_duplicate_contacts') {
            $show_hide_contact_group_select = ' d-none';
            $output_advanced_filters_class = 'd-none';
            
        // else show the contact groups pick list and advanced filters button
        } else {
            $show_hide_contact_group_select = '';
            $output_advanced_filters_class = 'btn-primary';
        }

    // else the advanced filters are on
    } else {
      
        $output_contact_group_selection = '';
        $output_advanced_filters_value = 'false';
        $output_advanced_filters_label = lang('Remove Advanced Filters');
        $advanced_filters_icon = 'filter_list_off';
        $output_advanced_filters_class = 'btn-danger';
        $output_contact_groups = '';

        // if user has a role that is greater than user role, then prepare to output [None] option
        if ($user['role'] < 3) {
            // if none contact group is selected
            if ((is_array(($_SESSION['software']['view_contacts']['contact_groups'] ?? array())) == true) && (in_array('[' . lang('None') . ']', ($_SESSION['software']['view_contacts']['contact_groups'] ?? array())) == true)) {
                $checked = ' checked="checked"';
            } else {
                $checked = '';
            }

            $output_contact_groups .= '<div class="form-check"><input type="checkbox" name="contact_groups[]" id="contact_group_[' . lang('None') . ']" value="[' . lang('None') . ']"' . $checked . ' class="form-check-input multiselect-checkbox" /><label class="form-check-label" for="contact_group_[' . lang('None') . ']"> [' . lang('None') . '] (' . get_number_of_contacts('[' . lang('None') . ']', $require_email = false) . ')</label></div>';
        }

        foreach ($contact_groups as $contact_group) {
            // get number of contacts in contact group
            $number_of_contacts = get_number_of_contacts($contact_group['id'], $require_email = false);

            // if this contact group should be checked
            if ((is_array(($_SESSION['software']['view_contacts']['contact_groups'] ?? array())) == true) && (in_array($contact_group['id'], ($_SESSION['software']['view_contacts']['contact_groups'] ?? array())) == true)) {
                $checked = ' checked="checked"';
            } else {
                $checked = '';
            }

            $output_contact_groups .= '<div class="form-check"><input type="checkbox" name="contact_groups[]" id="contact_group_' . $contact_group['id'] . '" value="' . $contact_group['id'] . '"' . $checked . ' class="form-check-input multiselect-checkbox" /><label class="form-check-label" for="contact_group_' . $contact_group['id'] . '"> ' . h($contact_group['name']) . ' (' . $number_of_contacts . ')</label></div>';
        }

        // If the Opt in status is not on display the opt in select box.
        if ($filter != 'my_subscribers') {

            switch (($_SESSION['software']['view_contacts']['opt_in_status'] ?? '')) {
                case 'any':
                        $opt_in_status_any_selected = ' selected="selected"';
                    break;
                case 'opt_in':
                        $opt_in_status_opt_in_selected = ' selected="selected"';
                    break;
                case 'opt_out':
                        $opt_in_status_opt_out_selected = ' selected="selected"';
                    break;
            }

            // Output select box.
            $output_opt_status = '
                <select class="form-select" id="opt_in_status" name="opt_in_status"><option value="any"' . $opt_in_status_any_selected . '>' . lang('Any') . '</option><option value="opt_in"' . $opt_in_status_opt_in_selected . '>Opt-In</option><option value="opt_out"' . $opt_in_status_opt_out_selected . '>Opt-Out</option></select>';
        } else {
            $output_opt_status = 'Opt-In';
        }

        // If any of the Membership filters are not on.
        if (isset($membership_filter) == false) {
            // prepare selection for membership status pick list
            switch (($_SESSION['software']['view_contacts']['membership_status'] ?? '')) {
                case 'any':
                    $membership_status_any_selected = ' selected="selected"';
                    break;

                case 'member':
                    $membership_status_member_or_expired_member_selected = ' selected="selected"';
                    break;

                case 'active_member':
                    $membership_status_member_selected = ' selected="selected"';
                    break;

                case 'expired_member':
                    $membership_status_expired_member_selected = ' selected="selected"';
                    break;

                case 'unregistered_member':
                    $membership_status_unregistered_member_selected = ' selected="selected"';
                    break;

                case 'non_member':
                    $membership_status_non_member_selected = ' selected="selected"';
                    break;
            }

            $output_membership_status = '
                <div class="col-12 col-sm-6 col-md-12 my-1">
                    <label for="membership_status" class="form-label">' . lang('Status') . '</label>
                    <select class="form-select" id="membership_status" name="membership_status">
                        <option value="any"' . $membership_status_any_selected . '>' . lang('Any') . '</option>
                        <option value="member"' . $membership_status_member_or_expired_member_selected . '>' . lang('Member') . '</option>
                        <option value="active_member"' . $membership_status_member_selected . '>' . lang('Active Member') . '</option>
                        <option value="expired_member"' . $membership_status_expired_member_selected . '>' . lang('Expired Member') . '</option>
                        <option value="unregistered_member"' . $membership_status_unregistered_member_selected . '>' . lang('Unregistered Member') . '</option>
                        <option value="non_member"' . $membership_status_non_member_selected . '>' . lang('Non-Member') . '</option>
                    </select>
                </div>';
        } else {
            $output_membership_status = '
                <div class="col-12 col-sm-6 col-md-12 my-1">
                    <label for="membership_status" class="form-label">' . lang('Status') . '</label>
                    <input value="' . $membership_status_label . '" type="text" class="form-control disabled" disabled="disabled" id="membership_status" name="fake_membership_status" />
                </div>';
        }

        if (AFFILIATE_PROGRAM == true) {
            $output_affiliate =
                '<div class="col-12"><h5 class="text-success fw-bold mt-4 mb-2">' . lang('Affiliate') . '</h5></div>
                <div class="col-12 col-sm-6 col-md-12 my-1">
                    <label for="affiliate_name" class="form-label">' . lang('Affiliate Name') . '</label>
                    <input type="text" id="affiliate_name" name="affiliate_name" class="form-control" value="' . h(($_SESSION['software']['view_contacts']['affiliate_name'] ?? '')) . '"/>
                </div>
                <div class="col-12 col-sm-6 col-md-12 my-1">
                    <label for="affiliate_code" class="form-label">' . lang('Affiliate Code') . '</label>
                    <input type="text" id="affiliate_code" name="affiliate_code" class="form-control" value="' . h(($_SESSION['software']['view_contacts']['affiliate_code'] ?? '')) . '"/>
                </div>';
        }




        $output_advanced_filters =
            '<div class="advanced_filters advanced-filter-bar  position-fixed-md"  id="advanced_filters" >
                <div class="p-2 border justify-content-between d-flex flex-wrap header">
                    <p class="m-0"><span class="material-icons pe-1">filter_list</span>' . lang('Filters') . '</p>
                    <a class="btn btn-close " title="' . $output_advanced_filters_label . '" href="view_contacts.php?advanced_filters=' . $output_advanced_filters_value . '" ></a>
                </div>
                <form class="advanced-filter-body p-2 pt-0 disable_shortcut" id="search_advanced" action="view_contacts.php" method="get">
                    <div class="row">
                        <div class="col-12"><h5 class="text-success fw-bold mt-4 mb-2">' . lang('Contact Groups') . '</h5></div>
                        <div class="col-12 col-md-12 my-1">
                            <div class="card multiselect-checkbox-container rounded-0">
                                <div class="card-header border-0 bg-reset">
                                    <div class="form-check form-switch">
                                        <input id="multiselect-checkbox-checker-0" class="form-check-input multiselect-checkbox-checker" title="' . lang(array('string'=>'Select/Deselect All') ) . '" type="checkbox">
                                        <label for="multiselect-checkbox-checker-0" class="form-check-label">' . lang('Select All') . '</label>
                                    </div>
                                </div>
                                <div class="card-body overflow-auto" style="max-height:200px">
                                    <input type="hidden" name="contact_groups" value="" />
                                    ' . $output_contact_groups . '
                                </div>
                            </div>
                        </div>

                        <div class="col-12"><h5 class="text-success fw-bold mt-4 mb-2">' . lang('General') . '</h5></div>
                        <div class="col-12 col-sm-6 col-md-12 my-1">
                            <label for="salutation" class="form-label">' . lang('Salutation') . '</label>
                            <input type="text" id="salutation" name="salutation" class="form-control" value="' . h(($_SESSION['software']['view_contacts']['salutation'] ?? '')) . '" />
                        </div>
                        <div class="col-12 col-sm-6 col-md-12 my-1">
                            <label for="first_name" class="form-label">' . lang('First Name') . '</label>
                            <input type="text" id="first_name" name="first_name" class="form-control" value="' . h(($_SESSION['software']['view_contacts']['first_name'] ?? '')) . '" />
                        </div>
                        <div class="col-12 col-sm-6 col-md-12 my-1">
                            <label for="last_name" class="form-label">' . lang('Last Name') . '</label>
                            <input type="text" id="last_name" name="last_name" class="form-control" value="' . h(($_SESSION['software']['view_contacts']['last_name'] ?? '')) . '" />
                        </div>
                        <div class="col-12 col-sm-6 col-md-12 my-1">
                            <label for="suffix" class="form-label">' . lang('Suffix') . '</label>
                            <input type="text" id="suffix" name="suffix" class="form-control" value="' . h(($_SESSION['software']['view_contacts']['suffix'] ?? '')) . '" />
                        </div>
                        <div class="col-12 col-sm-6 col-md-12 my-1">
                            <label for="nickname" class="form-label">' . lang('Nickname') . '</label>
                            <input type="text" id="nickname" name="nickname" class="form-control" value="' . h(($_SESSION['software']['view_contacts']['nickname'] ?? '')) . '" />
                        </div>
                        <div class="col-12 col-sm-6 col-md-12 my-1">
                            <label for="company" class="form-label">' . lang('Company') . '</label>
                            <input type="text" id="company" name="company" class="form-control" value="' . h(($_SESSION['software']['view_contacts']['company'] ?? '')) . '" />
                        </div>
                        <div class="col-12 col-sm-6 col-md-12 my-1">
                            <label for="title" class="form-label">' . lang('Title') . '</label>
                            <input type="text" id="title" name="title" class="form-control" value="' . h(($_SESSION['software']['view_contacts']['title'] ?? '')) . '" />
                        </div>
                        <div class="col-12 col-sm-6 col-md-12 my-1">
                            <label for="department" class="form-label">' . lang('Department') . '</label>
                            <input type="text" id="department" name="department" class="form-control" value="' . h(($_SESSION['software']['view_contacts']['department'] ?? '')) . '" />
                        </div>
                        <div class="col-12 col-sm-6 col-md-12 my-1">
                            <label for="office_location" class="form-label">' . lang('Office Location') . '</label>
                            <input type="text" id="office_location" name="office_location" class="form-control" value="' . h(($_SESSION['software']['view_contacts']['office_location'] ?? '')) . '" />
                        </div>
                        <div class="col-12 col-sm-6 col-md-12 my-1">
                            <label for="business_phone" class="form-label">' . lang('Business Phone') . '</label>
                            <input type="text" id="business_phone" name="business_phone" class="form-control" value="' . h(($_SESSION['software']['view_contacts']['business_phone'] ?? '')) . '" />
                        </div>
                        <div class="col-12 col-sm-6 col-md-12 my-1">
                            <label for="home_phone" class="form-label">' . lang('Home Phone') . '</label>
                            <input type="text" id="home_phone" name="home_phone" class="form-control" value="' . h(($_SESSION['software']['view_contacts']['home_phone'] ?? '')) . '" />
                        </div>
                        <div class="col-12 col-sm-6 col-md-12 my-1">
                            <label for="mobile_phone" class="form-label">' . lang('Mobile Phone') . '</label>
                            <input type="text" id="mobile_phone" name="mobile_phone" class="form-control" value="' . h(($_SESSION['software']['view_contacts']['mobile_phone'] ?? '')) . '" />
                        </div>
                        <div class="col-12 col-sm-6 col-md-12 my-1">
                            <label for="business_fax" class="form-label">' . lang('Business Fax') . '</label>
                            <input type="text" id="business_fax" name="business_fax" class="form-control" value="' . h(($_SESSION['software']['view_contacts']['business_fax'] ?? '')) . '" />
                        </div>
                        <div class="col-12 col-sm-6 col-md-12 my-1">
                            <label for="home_fax" class="form-label">' . lang('Home Fax') . '</label>
                            <input type="text" id="home_fax" name="home_fax" class="form-control" value="' . h(($_SESSION['software']['view_contacts']['home_fax'] ?? '')) . '" />
                        </div>
                        <div class="col-12 col-sm-6 col-md-12 my-1">
                            <label for="email_address" class="form-label">' . lang('Email') . '</label>
                            <input type="text" id="email_address" name="email_address" class="form-control" value="' . h(($_SESSION['software']['view_contacts']['email_address'] ?? '')) . '" maxlength="100" inputmode="email" data-inputmask-alias="email" />
                        </div>
                        <div class="col-12 col-sm-6 col-md-12 my-1">
                            <label for="opt_in_status" class="form-label">' . lang('Opt-In Status') . '</label>
                            ' . $output_opt_status . '
                        </div>
                        <div class="col-12 col-sm-6 col-md-12 my-1">
                            <label for="website" class="form-label">' . lang('Website') . '</label>
                            <input type="text" id="website" name="website" class="form-control" value="' . h(($_SESSION['software']['view_contacts']['website'] ?? '')) . '"/>
                        </div>
                        <div class="col-12 col-sm-6 col-md-12 my-1">
                            <label for="website" class="form-label">' . lang('Website') . '</label>
                            <input type="text" id="website" name="website" class="form-control" value="' . h(($_SESSION['software']['view_contacts']['website'] ?? '')) . '"/>
                        </div>
                        <div class="col-12 col-sm-6 col-md-12 my-1">
                            <label for="lead_source" class="form-label">' . lang('Lead Source') . '</label>
                            <input type="text" id="lead_source" name="lead_source" class="form-control" value="' . h(($_SESSION['software']['view_contacts']['lead_source'] ?? '')) . '"/>
                        </div>
                        <div class="col-12 col-sm-6 col-md-12 my-1">
                            <label for="description" class="form-label">' . lang('Description') . '</label>
                            <input type="text" id="description" name="description" class="form-control" value="' . h(($_SESSION['software']['view_contacts']['description'] ?? '')) . '"/>
                        </div>

                        <div class="col-12"><h5 class="text-success fw-bold mt-4 mb-2">' . lang('Business') . '</h5></div>
                        <div class="col-12 col-sm-6 col-md-12 my-1">
                            <label for="business_address_1" class="form-label">' . lang('Address') . ' 1</label>
                            <input type="text" id="business_address_1" name="business_address_1" class="form-control" value="' . h(($_SESSION['software']['view_contacts']['business_address_1'] ?? '')) . '"/>
                        </div>
                        <div class="col-12 col-sm-6 col-md-12 my-1">
                            <label for="business_address_2" class="form-label">' . lang('Address') . ' 2</label>
                            <input type="text" id="business_address_2" name="business_address_2" class="form-control" value="' . h(($_SESSION['software']['view_contacts']['business_address_2'] ?? '')) . '"/>
                        </div>
                        <div class="col-12 col-sm-6 col-md-12 my-1">
                            <label for="business_city" class="form-label">' . lang('City') . '</label>
                            <input type="text" id="business_city" name="business_city" class="form-control" value="' . h(($_SESSION['software']['view_contacts']['business_city'] ?? '')) . '"/>
                        </div>
                        <div class="col-12 col-sm-6 col-md-12 my-1">
                            <label for="business_state" class="form-label">' . lang('State') . '</label>
                            <input type="text" id="business_state" name="business_state" class="form-control" value="' . h(($_SESSION['software']['view_contacts']['business_state'] ?? '')) . '"/>
                        </div>
                        <div class="col-12 col-sm-6 col-md-12 my-1">
                            <label for="business_zip_code" class="form-label">' . lang('Zip Code') . '</label>
                            <input type="text" id="business_zip_code" name="business_zip_code" class="form-control" value="' . h(($_SESSION['software']['view_contacts']['business_zip_code'] ?? '')) . '"/>
                        </div>
                        <div class="col-12 col-sm-6 col-md-12 my-1">
                            <label for="business_country" class="form-label">' . lang('Country') . '</label>
                            <input type="text" id="business_country" name="business_country" class="form-control" value="' . h(($_SESSION['software']['view_contacts']['business_country'] ?? '')) . '"/>
                        </div>

                        <div class="col-12"><h5 class="text-success fw-bold mt-4 mb-2">' . lang('Home') . '</h5></div>
                        <div class="col-12 col-sm-6 col-md-12 my-1">
                            <label for="home_address_1" class="form-label">' . lang('Address') . ' 1</label>
                            <input type="text" id="home_address_1" name="home_address_1" class="form-control" value="' . h(($_SESSION['software']['view_contacts']['home_address_1'] ?? '')) . '"/>
                        </div>
                        <div class="col-12 col-sm-6 col-md-12 my-1">
                            <label for="home_address_2" class="form-label">' . lang('Address') . ' 2</label>
                            <input type="text" id="home_address_2" name="home_address_2" class="form-control" value="' . h(($_SESSION['software']['view_contacts']['home_address_2'] ?? '')) . '"/>
                        </div>
                        <div class="col-12 col-sm-6 col-md-12 my-1">
                            <label for="home_city" class="form-label">' . lang('City') . '</label>
                            <input type="text" id="home_city" name="home_city" class="form-control" value="' . h(($_SESSION['software']['view_contacts']['home_city'] ?? '')) . '"/>
                        </div>
                        <div class="col-12 col-sm-6 col-md-12 my-1">
                            <label for="home_state" class="form-label">' . lang('State') . '</label>
                            <input type="text" id="home_state" name="home_state" class="form-control" value="' . h(($_SESSION['software']['view_contacts']['home_state'] ?? '')) . '"/>
                        </div>
                        <div class="col-12 col-sm-6 col-md-12 my-1">
                            <label for="home_zip_code" class="form-label">' . lang('Zip Code') . '</label>
                            <input type="text" id="home_zip_code" name="home_zip_code" class="form-control" value="' . h(($_SESSION['software']['view_contacts']['home_zip_code'] ?? '')) . '"/>
                        </div>
                        <div class="col-12 col-sm-6 col-md-12 my-1">
                            <label for="home_country" class="form-label">' . lang('Country') . '</label>
                            <input type="text" id="home_country" name="home_country" class="form-control" value="' . h(($_SESSION['software']['view_contacts']['home_country'] ?? '')) . '"/>
                        </div>

                        <div class="col-12"><h5 class="text-success fw-bold mt-4 mb-2">' . lang('Membership') . '</h5></div>
                        ' . $output_membership_status . '
                        <div class="col-12 col-sm-6 col-md-12 my-1">
                            <label for="member_id" class="form-label">' . h(MEMBER_ID_LABEL) . '</label>
                            <input type="text" id="member_id" name="member_id" class="form-control" value="' . h(($_SESSION['software']['view_contacts']['member_id'] ?? '')) . '"/>
                        </div>
                        <div class="col-12 col-sm-6 col-md-12 my-1">
                            <label for="member_id" class="form-label">' . lang('Expiration Date') . '</label>
                            <input type="text" id="expiration_date" name="expiration_date" class="form-control" value="' . h(($_SESSION['software']['view_contacts']['expiration_date'] ?? '')) . '" />
                            ' . get_date_picker_format() . '
                            <script>
                                $("#expiration_date").datepicker(datetimepicker_options);
                            </script>
                        </div>
                        
                        ' . $output_affiliate . '
                        
                        <div class="col-12"><h5 class="text-success fw-bold mt-4 mb-2">' . lang('Date Range') . '</h5></div>
                        <div class="col-12">
                            <label class="form-label">' . lang('From') . '</label>
                            <select class="form-select my-1" name="start_month">' . select_month(($_SESSION['software']['view_contacts']['start_month'] ?? '')) . '</select>
                            <div class="input-group input-group-sm">
                                <select class="form-select my-1" name="start_day">' . select_day(($_SESSION['software']['view_contacts']['start_day'] ?? '')) . '</select>
                                <select class="form-select my-1" name="start_year">' . select_year($years, ($_SESSION['software']['view_contacts']['start_year'] ?? '')) . '</select>
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label">' . lang('To') . '</label>
                            <select class="form-select my-1" name="stop_month">' . select_month(($_SESSION['software']['view_contacts']['stop_month'] ?? '')) . '</select>
                            <div class="input-group input-group-sm">
                                <select class="form-select my-1" name="stop_day">' . select_day(($_SESSION['software']['view_contacts']['stop_day'] ?? '')) . '</select>
                                <select class="form-select my-1" name="stop_year">' . select_year($years, ($_SESSION['software']['view_contacts']['stop_year'] ?? '')) . '</select>
                            </div>
                        </div>
                        <div class="col-12 text-center position-sticky my-2" style="bottom:.5rem;">
                            <button type="submit" name="submit_data" value="Update" data-loading-content="' . lang('Updating') . '" class="btn btn-primary my-1"><i class="material-icons me-2">sync</i>' . lang('Update') . '</button>
                        </div>
                    </div>
                </form>
            </div>';
            


            
    }

    $output_delete_contacts_button = '';

    // if mass deletion is allowed, then prepare to output delete contacts button
    if (MASS_DELETION == true) {
        $output_delete_contacts_button = '
        <div class=" btn-group btn-group-sm flex-wrap">
            <button type="submit" name="submit_data" value="Delete Contacts" class="btn btn-link link-danger py-0 m-1" data-loading-content="' . lang(array('string'=>'Deleting') ) . '" data-confirm-content="' . lang('WARNING: All contacts that match the filters will be permanently deleted. This includes contacts from all result pages that might exist. Please make sure that you perform an update to the filters before you attempt to delete. An update will allow you to see which contacts will be deleted before you actually delete them. If you would like to continue with the deletion, please click OK. Otherwise, please click Cancel.') . '"><span class="bi bi-trash3-fill me-1"></span>' . lang(array('string'=>'Delete All Contacts') ) . '</button>
        </div>';
    }
    
    $output_user_label = '';
    $output_opt_in_label = '';
    $output_phone_label = '';
    $output_email_address_label = '';
    $output_address_labels = '';
    
    // If the contacts by user filter, my_contacts by business address, and my contacts by home address is not on, then output the user label
    if (($filter != 'my_contacts_by_business_address') && ($filter != 'my_contacts_by_home_address')) {
        $output_user_label = '<th>' . get_column_heading(lang('User'), ($_SESSION['software']['view_contacts']['sort'] ?? ''), $sort_order, $output_filter_for_links) . '</th>';

    }

    // if the filter is not set to either my contacts by business address or my contacts by home address then output the op_in, phone and email address labels
    if (($filter != 'my_contacts_by_business_address') && ($filter != 'my_contacts_by_home_address')) {
        $output_opt_in_label = '<th class="text-center">' . get_column_heading(lang('Opt-In'), ($_SESSION['software']['view_contacts']['sort'] ?? ''), $sort_order, $output_filter_for_links) . '</th>';
        $output_phone_label = '<th>' . lang('Phone') . '</th>';
        $output_email_address_label = '<th>' . get_column_heading(lang('Email'), ($_SESSION['software']['view_contacts']['sort'] ?? ''), $sort_order, $output_filter_for_links) . '</th>';
    
    // else one of the above filters has been selected so output the address table headings
    } else {
        $output_address_labels = '<th>' . lang('Address') . '</th>';
        $output_address_labels .= '<th>' . get_column_heading(lang('City'), ($_SESSION['software']['view_contacts']['sort'] ?? ''), $sort_order, $output_filter_for_links) . '</th>';
        $output_address_labels .= '<th>' . get_column_heading(lang('State'), ($_SESSION['software']['view_contacts']['sort'] ?? ''), $sort_order, $output_filter_for_links) . '</th>';
        $output_address_labels .= '<th>' . get_column_heading(lang('Zip Code'), ($_SESSION['software']['view_contacts']['sort'] ?? ''), $sort_order, $output_filter_for_links) . '</th>';
        $output_address_labels .= '<th>' . get_column_heading(lang('Country'), ($_SESSION['software']['view_contacts']['sort'] ?? ''), $sort_order, $output_filter_for_links) . '</th>';
    }
    
    $output_table_headings = '';
    $output_modify_selected_buttons = '';
    
    $output_contact_groups_toggle = '';

    // if this is not the all duplicates view, then output the contact groups toggle
    if ($filter != 'all_duplicate_contacts') {
        // if the user has selected to show contact groups, then prepare toggle to hide contact groups
        if (($_SESSION['software']['view_contacts']['show_contact_groups'] ?? '') == true) {
            $output_contact_groups_toggle = '<a href="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_contacts.php?filter=' . h($filter) . '&show_contact_groups=false" title="Hide Contact Groups">' . lang('Hide Groups') . '</a>';
        
        // else set the show groups toggle link's value and label
        } else {
            $output_contact_groups_toggle = '<a href="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_contacts.php?filter=' . h($filter) . '&show_contact_groups=true" title="Show Contact Groups">' . lang('Show Groups') . '</a>';
        }
    }
    
    // if the all duplicate contacts filter is on then output the appropriate table headings and merge contacts buttons
    if ($filter == 'all_duplicate_contacts') {
        $output_table_headings = 
            '<th class="noVis">
                <div class="form-check form-switch">
                    <input class="form-check-input" title="' . lang(array('string'=>'Select/Deselect All') ) . '" type="checkbox" id="select_all">
                </div>
            </th>
            <th class="noVis">' . lang('Action') . '</th>
            ' . $output_image_header . '
            <th>' . lang('Email') . '</th>
            <th>' . lang('First Name') . '</th>
            <th>' . lang('Last Name') . '</th>
            <th>' . lang('Company') . '</th>
            <th>' . lang('Address') . '</th>
            ' . $output_phone_label . '
            <th>' . lang('User') . '</th>
            <th>' . lang('Contact Groups') . ' ' . $output_contact_groups_toggle . '</th>
            <th>' . lang('Last Modified') . '</th>';
        
        $output_merge_contacts_button = '
         <div class=" btn-group btn-group-sm flex-wrap">
             <button type="submit" name="submit_data" value="Merge Contacts" class="btn btn-link link-warning py-0 m-1" data-loading-content="' . lang(array('string'=>'Merging') ) . '" data-confirm-content="' . lang('WARNING: All contacts that match the filters will be permanently merged together. This includes contacts from all result pages that might exist. Please make sure that you perform an update to the filters before you attempt to merge. An update will allow you to see which contacts will be merged before you actually merge them. If you would like to continue with the merge, please click OK. Otherwise, please click Cancel.') . '"><span class="material-icons me-1">healing</span>' . lang(array('string'=>'Merge') ) . '</button>
         </div>';
        
        // output the merge selected button
        $output_modify_selected_buttons = '<button type="button" value="Merge Selected" class="btn mb-1 mt-1 btn-warning disabled" data-loading-content="' . lang(array('string'=>'Merging') ) . '" data-confirm-content="' . lang(array('string'=>'WARNING: The selected duplicate {var:1} will be merged together.','vars'=>array(lang('contacts')))) . '"><span class="material-icons me-2">healing</span>' . lang(array('string'=>'Merge Selected') ) . '</button>';
    
    // else output the default table headings
    } else {
        $output_table_headings = 
            '<th class="noVis">
                <div class="form-check form-switch">
                    <input class="form-check-input" title="' . lang(array('string'=>'Select/Deselect All') ) . '" type="checkbox" id="select_all">
                </div>
            </th>
            <th class="noVis">' . lang('Action') . '</th> 
            ' . $output_image_header . '
            <th>' . get_column_heading(lang('First Name'), ($_SESSION['software']['view_contacts']['sort'] ?? ''), $sort_order, $output_filter_for_links) . '</th>
            <th>' . get_column_heading(lang('Last Name'), ($_SESSION['software']['view_contacts']['sort'] ?? ''), $sort_order, $output_filter_for_links) . '</th>
            <th>' . get_column_heading(lang('Company'), ($_SESSION['software']['view_contacts']['sort'] ?? ''), $sort_order, $output_filter_for_links) . '</th>
            ' . $output_phone_label . '
            ' . $output_email_address_label . '
            ' . $output_user_label . '
            ' . $output_opt_in_label . '
            ' . $output_address_labels . '
            <th>' . $output_contact_groups_toggle . '</th>
            <th>' . get_column_heading(lang('Last Modified'), ($_SESSION['software']['view_contacts']['sort'] ?? ''), $sort_order, $output_filter_for_links) . '</th>';
        
        // output the opt in and opt out buttons
        $output_modify_selected_buttons = '
        <button type="button" value="Opt-In Selected" class="btn mb-1 mt-1 btn-secondary disabled" data-loading-content="' . lang(array('string'=>'Loading') ) . '" data-confirm-content="' . lang(array('string'=>'WARNING: The selected {var:1} will be opted-in.','vars'=>array(lang('contacts')))) . '"><span class="material-icons me-2">radio_button_checked</span>' . lang(array('string'=>'Opt-In Selected') ) . '</button>
        <button type="button" value="Opt-Out Selected" class="btn mb-1 mt-1 btn-secondary disabled" data-loading-content="' . lang(array('string'=>'Loading') ) . '" data-confirm-content="' . lang(array('string'=>'WARNING: The selected {var:1} will be opted-out.','vars'=>array(lang('contacts')))) . '"><span class="material-icons me-2">radio_button_unchecked</span>' . lang(array('string'=>'Opt-Out Selected') ) . '</button>';
    
    }
    
    $output .=
    pg_page_shell(
        array(
            'title'=> lang($heading),
            'extra classes'=>'contact',
            'icon'=>'contact', 
            'heading'=>lang($heading),
                    
        )
    ) . '  
    ' . $output_advanced_filters . '
            <div class="row">
            <div class="col-12">
                ' . $liveform->output_errors() . '
                ' . $liveform->get_warnings() . '
                ' . $liveform->output_notices() . '
                <div class="row mb-2  flex-wrap">
                    <div class="col-12 col-sm-12 col-md-6 col-xl-8 text-center text-md-start">
                        <h2 class="d-inline-block " data-bs-content="' . $subheading . '" title="' . $heading . '">' . $heading . '</h2>
                        <nav id="button_bar" class="navigation " aria-label="Button Bar">
                            <form id="export_form" class="disable_shortcut d-inline-block" method="get">
                                <a class="btn btn-sm btn-primary m-1 " href="add_contact.php?send_to=' . h(REQUEST_URL) . '" data-loading-content="' . lang(array('string'=>'Loading') ) . '"><span class="bi bi-plus-circle me-2"></span>' . lang(array('string'=>'Create') ) . '</a>
                                <div class=" btn-group btn-group-sm flex-wrap">
                                    <a class="btn btn-link link-secondary py-0 m-1" href="import_contacts.php?send_to=' . h(REQUEST_URL) . '"><span class="bi bi-box-arrow-in-right me-1"></span>' . lang(array('string'=>'Import') ) . '</a>
                                    <button type="submit" name="submit_data" value="Export Contacts" class="btn btn-link link-secondary py-0 m-1"><span class="bi bi-file-earmark-arrow-down bi-me-2"></span>' . lang(array('string'=>'Export') ) . '</button>
                                </div>
                                ' . $output_merge_contacts_button . $output_delete_contacts_button . '
                            </form>
                        </nav>
                    </div> 
                    <div class="col-12 col-sm-12 col-md-6 col-xl-4 ">
                        <div class="row justify-content-center justify-content-md-end">
                            <form id="search_form" action="view_contacts.php" method="get" class="search_form disable_shortcut col-auto">
                                <input type="hidden" name="filter" value="' . h($filter) . '">
                                <div class="input-group input-group-sm">
                                    <a class="btn btn-sm  my-1 ' . $output_advanced_filters_class . '" data-loading-content=" " title="' . $output_advanced_filters_label . '" href="view_contacts.php?advanced_filters=' . $output_advanced_filters_value . '" ><i class="material-icons">'. $advanced_filters_icon . '</i></a>
                                    <label class="input-group-text mt-1 mb-1 material-icons" title="' . lang('Content that viewed') . '" for="filter">visibility</label>
                                    <select id="filter" name="filter" class="form-select mt-1 mb-1" title="' . lang('Content that viewed') . '" onchange="submit_form(\'search_form\')">' . get_filter_options($filters_in_array, $filter) . '</select>
                                </div>
                                ' . $output_contact_group_selection . '
                                <div class="row justify-content-center justify-content-md-end' . $show_hide_date_range . '" >
                                    <div class="btn-group btn-group-sm col-auto py-0 px-1 my-1">
                                        <a class="btn py-0 px-1 border-start border-top border-bottom" href="view_contacts.php?start_month=' . $decrease_year['start_month'] . '&start_day=' . $decrease_year['start_day'] . '&start_year=' . $decrease_year['start_year'] . '&stop_month=' . $decrease_year['stop_month'] . '&stop_day=' . $decrease_year['stop_day'] . '&stop_year=' . $decrease_year['stop_year'] . '"><</a>
                                        <a class="btn py-0 px-1 border-bottom border-top" href="view_contacts.php?start_month=' . $current_year['start_month'] . '&start_day=' . $current_year['start_day'] . '&start_year=' . $current_year['start_year'] . '&stop_month=' . $current_year['stop_month'] . '&stop_day=' . $current_year['stop_day'] . '&stop_year=' . $current_year['stop_year'] . '">' . lang('Year') . '</a>
                                        <a class="btn py-0 px-1 border-end border-top border-bottom" href="view_contacts.php?start_month=' . $increase_year['start_month'] . '&start_day=' . $increase_year['start_day'] . '&start_year=' . $increase_year['start_year'] . '&stop_month=' . $increase_year['stop_month'] . '&stop_day=' . $increase_year['stop_day'] . '&stop_year=' . $increase_year['stop_year'] . '">></a>
                                    </div>
                                    <div class="btn-group btn-group-sm col-auto py-0 px-1 my-1">
                                        <a class="btn py-0 px-1 border-start border-top border-bottom" href="view_contacts.php?start_month=' . $decrease_month['start_month'] . '&start_day=' . $decrease_month['start_day'] . '&start_year=' . $decrease_month['start_year'] . '&stop_month=' . $decrease_month['stop_month'] . '&stop_day=' . $decrease_month['stop_day'] . '&stop_year=' . $decrease_month['stop_year'] . '"><</a>
                                        <a class="btn py-0 px-1 border-bottom border-top" href="view_contacts.php?start_month=' . $current_month['start_month'] . '&start_day=' . $current_month['start_day'] . '&start_year=' . $current_month['start_year'] . '&stop_month=' . $current_month['stop_month'] . '&stop_day=' . $current_month['stop_day'] . '&stop_year=' . $current_month['stop_year'] . '">' . lang('Month') . '</a>
                                        <a class="btn py-0 px-1 border-end border-top border-bottom" href="view_contacts.php?start_month=' . $increase_month['start_month'] . '&start_day=' . $increase_month['start_day'] . '&start_year=' . $increase_month['start_year'] . '&stop_month=' . $increase_month['stop_month'] . '&stop_day=' . $increase_month['stop_day'] . '&stop_year=' . $increase_month['stop_year'] . '">></a>
                                    </div>
                                    <div class="btn-group btn-group-sm col-auto py-0 px-1 my-1">    
                                        <a class="btn py-0 px-1 border-start border-top border-bottom" href="view_contacts.php?start_month=' . $decrease_week['start_month'] . '&start_day=' . $decrease_week['start_day'] . '&start_year=' . $decrease_week['start_year'] . '&stop_month=' . $decrease_week['stop_month'] . '&stop_day=' . $decrease_week['stop_day'] . '&stop_year=' . $decrease_week['stop_year'] . '"><</a>
                                        <a class="btn py-0 px-1 border-bottom border-top" href="view_contacts.php?start_month=' . $current_week['start_month'] . '&start_day=' . $current_week['start_day'] . '&start_year=' . $current_week['start_year'] . '&stop_month=' . $current_week['stop_month'] . '&stop_day=' . $current_week['stop_day'] . '&stop_year=' . $current_week['stop_year'] . '">' . lang('Week') . '</a>
                                        <a class="btn py-0 px-1 border-end border-top border-bottom" href="view_contacts.php?start_month=' . $increase_week['start_month'] . '&start_day=' . $increase_week['start_day'] . '&start_year=' . $increase_week['start_year'] . '&stop_month=' . $increase_week['stop_month'] . '&stop_day=' . $increase_week['stop_day'] . '&stop_year=' . $increase_week['stop_year'] . '">></a>
                                    </div>
                                    <div class="btn-group btn-group-sm col-auto py-0 px-1 my-1">    
                                        <a class="btn py-0 px-1 border-start border-top border-bottom" href="view_contacts.php?start_month=' . $decrease_day['start_month'] . '&start_day=' . $decrease_day['start_day'] . '&start_year=' . $decrease_day['start_year'] . '&stop_month=' . $decrease_day['stop_month'] . '&stop_day=' . $decrease_day['stop_day'] . '&stop_year=' . $decrease_day['stop_year'] . '"><</a>
                                        <a class="btn py-0 px-1 border-bottom border-top" href="view_contacts.php?start_month=' . $current_day['start_month'] . '&start_day=' . $current_day['start_day'] . '&start_year=' . $current_day['start_year'] . '&stop_month=' . $current_day['stop_month'] . '&stop_day=' . $current_day['stop_day'] . '&stop_year=' . $current_day['stop_year'] . '">' . lang('Day') . '</a>
                                        <a class="btn py-0 px-1 border-end border-top border-bottom" href="view_contacts.php?start_month=' . $increase_day['start_month'] . '&start_day=' . $increase_day['start_day'] . '&start_year=' . $increase_day['start_year'] . '&stop_month=' . $increase_day['stop_month'] . '&stop_day=' . $increase_day['stop_day'] . '&stop_year=' . $increase_day['stop_year'] . '">></a>
                                    </div>    
                                </div>
                                <p class="text-center text-md-end p-0 m-0' . $show_hide_date_range . '">
                                    <span class="badge text-dark fw-light border-2">    ' . $output_date_range_time . '</span>
                                </p>
                            </form>
                        </div>
                    </div>
                </div>
                <div class="card my-4">
                    <div class="card-body p-0 position-relative">
                        <form name="form"  action="edit_contacts.php" method="post" class="view_orders"> 
                            ' . get_token_field() . ' 
                            <input type="hidden" name="action" />
                            <input type="hidden" name="add_to_contact_groups" />
                            <input type="hidden" name="remove_from_contact_groups" />
                            <input type="hidden" name="send_to" value="' . h(REQUEST_URL) . '" />
                            <table class="chart table-hover table " style="width:100%;display:none">
                                <thead>
                                    <tr>' . $output_table_headings . '</tr>
                                </thead>
                                <tbody>
                                    ' . $output_rows . '
                                </tbody>
                            </table>
                            <nav class="buttons navigation text-center position-sticky" style="bottom:.5rem;" aria-label="data edit buttons ">
                                <div class="container">
                                    <div class=" btn-group btn-group-sm flex-wrap justify-content-center mb-0 enable-on-selected">
                                        ' . $output_organize_selected_button . $output_modify_selected_buttons . '
                                        <button type="button" value="Delete Selected" class="btn mb-1 mt-1 btn-danger disabled" data-loading-content="' . lang(array('string'=>'Deleting') ) . '" data-confirm-content="' . lang(array('string'=>'WARNING: Selected {var:1} will be permanently deleted.','vars'=>array(lang('contacts')))) . '"><span class="material-icons me-2">delete</span>' . lang(array('string'=>'Delete Selected') ) . '</button>
                                    </div>
                                </div>
                            </nav>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </main>
    ' . output_footer();

    echo $output;

    $liveform->remove_form('view_contacts');
}