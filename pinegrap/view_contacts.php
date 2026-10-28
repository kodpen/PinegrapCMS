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
validate_contacts_access($user);

$liveform = new liveform('view_contacts');

// These are only ever appended to (or conditionally set) further below, so they have to start
// out empty.
$output = '';
$show_hide_contact_group_select = '';
$output_merge_contacts_button = '';

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

require_once(PG_FUNCTIONS_DIR . '/includes/fn/contact_list.php');

// The view from the query string. A name the list does not know, or one sent
// as an array (?filter[]=x), lists like All My Contacts.
$filter = pg_contact_list_filter_name($_GET['filter'] ?? '');

$filter_for_links = '&filter=' . $filter;
$output_filter_for_links = h($filter_for_links);

$filters_in_array = pg_contact_list_filters($user['role']);

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
    $output_organize_selected_button = '<button type="button" value="Organize Selected" class=" btn mb-1 mt-1 btn-solid theme-primary disabled" onclick="window.open(\'organize_contacts.php\', \'popup\', \'toolbar=no,location=no,directories=no,status=yes,menubar=no,resizable=yes,copyhistory=no,scrollbars=yes,width=500,height=500\'); edit_chart_content(\'organize\',\'contact\')"><span class="material-icons me-3">edit</span>' . lang(array('string'=>'Organize Selected') ) . '</button>';
}

// if advanced filters value was passed in the query string, turn them on or
// off before the session filters are brought into shape below
if (isset($_REQUEST['advanced_filters']) == true) {
    $_SESSION['software']['view_contacts']['advanced_filters'] = ($_REQUEST['advanced_filters'] == 'true');
}

// The list's filters and rows are shared with the action that pages it
// (includes/fn/contact_list.php).
$contact_groups = pg_contact_list_groups($user);
pg_contact_list_prepare($user, $contact_groups);
$list_filter = pg_contact_list_filter($user, $filter, $contact_groups);
$heading = $list_filter['heading'];
$subheading = $list_filter['subheading'];
$advanced_filters = $list_filter['advanced'];
$contact_groups_filter = $list_filter['group_filter'];
$sort_columns = $list_filter['sort_columns'];
$sort_default = $list_filter['sort_default'];
$sort_levels = $list_filter['sort_levels'];

if ($list_filter['membership_label'] !== null) {
    $membership_filter = true;
    $membership_status_label = $list_filter['membership_label'];
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

// The oldest contact's date starts the year picker (pg_contact_list_prepare()
// has already put the date range in shape).
$oldest_timestamp = (int) db_value("SELECT MIN(timestamp) FROM contacts");

// if the filter is not set to all duplicate contacts, then run logic to get date range
if ($filter != 'all_duplicate_contacts') {
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

    // The arrows of the month unit step from the last month on screen
    // (pg_list_period_month_steps()).
    $month_steps = pg_list_period_month_steps($_SESSION['software']['view_contacts'] ?? array());
    $decrease_month = $month_steps['previous'];

    $current_month['new_month'] = date('m');
    $current_month['new_year'] = date('Y');
    $current_month['start_month'] = $current_month['new_month'];
    $current_month['start_day'] = '01';
    $current_month['start_year'] = $current_month['new_year'];
    $current_month['stop_month'] = $current_month['new_month'];
    $current_month['stop_day'] = date('t');
    $current_month['stop_year'] = $current_month['new_year'];

    $increase_month = $month_steps['next'];

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

    // The range on screen, in the site's date order.
    $output_date_range_time = h(pg_list_period_heading($_SESSION['software']['view_contacts'] ?? array()));

// else this is the all duplicate contacts filter so hide the date range
} else {
    $show_hide_date_range = ' d-none';
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
             " . $list_filter['from'] . "
             " . $list_filter['where'] . "
             GROUP BY contacts.id
             ORDER BY contacts.last_name, contacts.first_name";

    $result = db_q($query, $list_filter['params']) or output_error('Query failed.');
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
    // Mass deletion is triggered by a GET form, so require the token to block forged links.
    validate_token_field();

    // get all contacts that need to be deleted
    $query =
        "SELECT contacts.id
        " . $list_filter['from'] . "
        " . $list_filter['where'] . "
        GROUP BY contacts.id";
    $result = db_q($query, $list_filter['params']) or output_error('Query failed.');

    $number_of_contacts = mysqli_num_rows($result);

    $contacts = array();

    // loop through all contacts that need to be deleted, so they can be added to array
    while ($row = mysqli_fetch_assoc($result)) {
        $contacts[] = $row;
    }

    // loop through all contacts that need to be deleted, so they can be deleted
    foreach ($contacts as $contact) {
        // delete contact
        $query = "DELETE FROM contacts WHERE id = ?";
        $result = db_q($query, array((string) $contact['id'])) or output_error('Query failed.');

        // delete contact references in contacts_contact_groups_xref
        $query = "DELETE FROM contacts_contact_groups_xref WHERE contact_id = ?";
        $result = db_q($query, array((string) $contact['id'])) or output_error('Query failed.');

        // delete contact references in opt_in
        $query = "DELETE FROM opt_in WHERE contact_id = ?";
        $result = db_q($query, array((string) $contact['id'])) or output_error('Query failed.');
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
                    $query = "SELECT name FROM contact_groups WHERE id = ?";
                    $result = db_q($query, array((string) $contact_group)) or output_error('Query failed.');
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
            $query = "SELECT name FROM contact_groups WHERE id = ?";
            $result = db_q($query, array((string) $contact_groups_filter)) or output_error('Query failed.');
            $row = mysqli_fetch_assoc($result);

            $contact_group_list = $row['name'];
        }
    }

    // if at least one contact was deleted
    if ($number_of_contacts > 0) {
        log_activity(lang(array('string'=>'{var:1} contact(s) from contact group(s) ({var:2}) were deleted','vars'=>array(pg_format_number($number_of_contacts, 0),$contact_group_list  ) )), $_SESSION['sessionusername']);

        $liveform->add_notice(lang(array('string'=>'{var:1} contact(s) from contact group(s) ({var:2}) were deleted','vars'=>array(pg_format_number($number_of_contacts, 0),$contact_group_list  ) )) );
    } else {
        $liveform->add_notice(lang('No contacts were deleted'));
    }

    header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/view_contacts.php');

// else, if the user selected to merge contacts, then merge them
} elseif (($_GET['submit_data'] ?? '') == 'Merge Contacts') {
    // Mass merge is triggered by a GET form, so require the token to block forged links.
    validate_token_field();

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
        " . $list_filter['from'] . "
        " . $list_filter['where'] . "
        ORDER BY contacts.email_address ASC";
    $result = db_q($query, $list_filter['params']) or output_error('Query failed.');
    while($row = mysqli_fetch_assoc($result)) {
        $contacts_to_merge[] = $row;
    }

    // merge the contacts, then refresh the screen with a notice
    $number_of_merged_contacts = merge_contacts($contacts_to_merge, $number_of_refused_groups);
    
    $notice = '';
    
    // if contacts were merged then output a notice informing the user how many were merged
    if ($number_of_merged_contacts > 0) {
        $notice = lang(array(
            'string'=>'{var:1} contact(s) were merged successfully',
            'vars'=>pg_format_number($number_of_merged_contacts, 0) ));
        log_activity(lang(array(
            'string'=>'{var:1} contact(s) were merged successfully',
            'vars'=>pg_format_number($number_of_merged_contacts, 0) )), $_SESSION['sessionusername']);
    
    // else output a notice informing the user that no contacts where merged.
    } else {
        $notice = lang('No contacts were merged. Contacts tied to User accounts cannot be merged.');
    }
    
    // a group where more than one contact has an ERP account was left as it is
    if ($number_of_refused_groups > 0) {
        $notice .= ' ' . lang(array(
            'string'=>'{var:1} group(s) of duplicate contacts were not merged: more than one contact of the group has a current account.',
            'vars'=>pg_format_number($number_of_refused_groups, 0) ));
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

            $output_contact_group_options .= '<option value="' . $contact_group['id'] . '"' . $selected . '>' . h($contact_group['name']) . ' (' . pg_format_number($number_of_contacts, 0) . ')</option>';
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

            $output_contact_group_options ='<option value="[' . lang('None') . ']"' . $selected . '>[' . lang('None') . '] (' . pg_format_number($number_of_contacts, 0) . ')</option>' . $output_contact_group_options;
        }

        // if all contact group is selected
        if ($contact_groups_filter == '[' . lang('All') . ']') {
            $selected = ' selected="selected"';
        } else {
            $selected = '';
        }

        $output_contact_group_options = '<option value="[' . lang('All') . ']"' . $selected . '>[' . lang('All') . ']</option>' . $output_contact_group_options;
    }
    
    // The rows of the other views come one page at a time from the
    // contact_list_rows action (includes/panel/lists.php). The duplicates view
    // orders the copies of an address in PHP, so it is read and printed whole.
    $output_rows = '';
    $output_list_attributes = ' data-pg-list-action="contact_list_rows" data-pg-list-params="' . h(encode_json(array('filter' => $filter))) . '"';

    if ($filter == 'all_duplicate_contacts') {
        $output_list_attributes = '';
        $query = "SELECT " . pg_contact_list_select($filter) . "
            " . $list_filter['from'] . "
            " . $list_filter['where'] . "
            GROUP BY contacts.id
            ORDER BY " . $list_filter['order_by'];
        $result = db_q($query, $list_filter['params']) or output_error('Query failed.');

        $contacts = array();

        while ($row = mysqli_fetch_assoc($result)) {
            $contacts[] = $row;
        }

        foreach (pg_contact_list_rows($user, $filter, $contact_groups, true, pg_contact_list_duplicates_order($contacts)) as $list_row) {
            $output_rows .= pg_list_row_html($list_row);
        }
    }

    $output_image_header = '<th>' . lang(array('string'=>'Image') ) . '</th>';

    // if the advanced filters are off
    if ($advanced_filters == false) {
    
        $output_contact_group_selection = '
        <div class="input-group input-group-sm w-auto'. $show_hide_contact_group_select .'">
            <label class="input-group-text material-icons" title="' . lang('Group') . '" for="contact_group">groups</label>
            <select id="contact_group" aria-label="' . h(lang('Group')) . '" name="contact_group" class="form-control" title="' . lang('Content that viewed') . '" onchange="submit_form(\'search_form\')">' . $output_contact_group_options . '</select>
        </div>';
        $output_advanced_filters_value = 'true';
        $output_advanced_filters_label = lang('Add Advanced Filters');
        $output_advanced_filters = '';
        $advanced_filters_icon = 'bi-funnel';
    
        // if the all duplicate contacts filter is on then hide the contact group select list and the advanced filters button
        if ($filter == 'all_duplicate_contacts') {
            $show_hide_contact_group_select = ' d-none';
            $output_advanced_filters_class = ' d-none';
            
        // else show the contact groups pick list and advanced filters button
        } else {
            $show_hide_contact_group_select = '';
            $output_advanced_filters_class = '';
        }

    // else the advanced filters are on
    } else {
      
        $output_contact_group_selection = '';
        $output_advanced_filters_value = 'false';
        $output_advanced_filters_label = lang('Remove Advanced Filters');
        $advanced_filters_icon = 'bi-funnel-fill';
        $output_advanced_filters_class = ' active';
        $output_contact_groups = '';

        // if user has a role that is greater than user role, then prepare to output [None] option
        if ($user['role'] < 3) {
            // if none contact group is selected
            if ((is_array(($_SESSION['software']['view_contacts']['contact_groups'] ?? array())) == true) && (in_array('[' . lang('None') . ']', ($_SESSION['software']['view_contacts']['contact_groups'] ?? array())) == true)) {
                $checked = ' checked="checked"';
            } else {
                $checked = '';
            }

            $output_contact_groups .= '<div class="form-field"><input type="checkbox" name="contact_groups[]" id="contact_group_[' . lang('None') . ']" value="[' . lang('None') . ']"' . $checked . ' class="check multiselect-checkbox" /><label class="" for="contact_group_[' . lang('None') . ']"> [' . lang('None') . '] (' . get_number_of_contacts('[' . lang('None') . ']', $require_email = false) . ')</label></div>';
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

            $output_contact_groups .= '<div class="form-field"><input type="checkbox" name="contact_groups[]" id="contact_group_' . $contact_group['id'] . '" value="' . $contact_group['id'] . '"' . $checked . ' class="check multiselect-checkbox" /><label class="" for="contact_group_' . $contact_group['id'] . '"> ' . h($contact_group['name']) . ' (' . $number_of_contacts . ')</label></div>';
        }

        // If the Opt in status is not on display the opt in select box.
        if ($filter != 'my_subscribers') {
            // only the chosen status is marked
            $opt_in_status_any_selected = '';
            $opt_in_status_opt_in_selected = '';
            $opt_in_status_opt_out_selected = '';

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
                <select class="form-control" id="opt_in_status" name="opt_in_status"><option value="any"' . $opt_in_status_any_selected . '>' . lang('Any') . '</option><option value="opt_in"' . $opt_in_status_opt_in_selected . '>Opt-In</option><option value="opt_out"' . $opt_in_status_opt_out_selected . '>Opt-Out</option></select>';
        } else {
            $output_opt_status = 'Opt-In';
        }

        // If any of the Membership filters are not on.
        if (isset($membership_filter) == false) {
            // prepare selection for membership status pick list; only the
            // chosen one is marked
            $membership_status_any_selected = '';
            $membership_status_member_or_expired_member_selected = '';
            $membership_status_member_selected = '';
            $membership_status_expired_member_selected = '';
            $membership_status_unregistered_member_selected = '';
            $membership_status_non_member_selected = '';

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
                <div class="col-12 sm:col-6 md:col-12 my-1">
                    <label for="membership_status" class="form-label">' . lang('Status') . '</label>
                    <select class="form-control" id="membership_status" name="membership_status">
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
                <div class="col-12 sm:col-6 md:col-12 my-1">
                    <label for="membership_status" class="form-label">' . lang('Status') . '</label>
                    <input value="' . $membership_status_label . '" type="text" class="form-control disabled" disabled="disabled" id="membership_status" name="fake_membership_status" />
                </div>';
        }

        if (AFFILIATE_PROGRAM == true) {
            $output_affiliate =
                '<div class="col-12"><h5 class="fg-success fw-bold mt-7 mb-3">' . lang('Affiliate') . '</h5></div>
                <div class="col-12 sm:col-6 md:col-12 my-1">
                    <label for="affiliate_name" class="form-label">' . lang('Affiliate Name') . '</label>
                    <input type="text" id="affiliate_name" name="affiliate_name" class="form-control" value="' . h(($_SESSION['software']['view_contacts']['affiliate_name'] ?? '')) . '"/>
                </div>
                <div class="col-12 sm:col-6 md:col-12 my-1">
                    <label for="affiliate_code" class="form-label">' . lang('Affiliate Code') . '</label>
                    <input type="text" id="affiliate_code" name="affiliate_code" class="form-control" value="' . h(($_SESSION['software']['view_contacts']['affiliate_code'] ?? '')) . '"/>
                </div>';
        }




        $output_advanced_filters =
            '<div class="advanced_filters advanced-filter-bar  position-fixed-md"  id="advanced_filters" >
                <div class="p-3 border justify-content-between d-flex flex-wrap header">
                    <p class="m-0"><span class="material-icons pe-1">filter_list</span>' . lang('Filters') . '</p>
                    <a class="btn btn-close " title="' . $output_advanced_filters_label . '" href="view_contacts.php?advanced_filters=' . $output_advanced_filters_value . '" ></a>
                </div>
                <form class="advanced-filter-body p-3 pt-0 disable_shortcut" id="search_advanced" action="view_contacts.php" method="get">
                    <div class="row">
                        <div class="col-12"><h5 class="fg-success fw-bold mt-7 mb-3">' . lang('Contact Groups') . '</h5></div>
                        <div class="col-12 md:col-12 my-1">
                            <div class="card multiselect-checkbox-container rounded-0">
                                <div class="card-header border-0 bg-reset">
                                    <div class="form-field">
                                        <div class="switch"><input id="multiselect-checkbox-checker-0" class="multiselect-checkbox-checker" title="' . lang(array('string'=>'Select/Deselect All') ) . '" type="checkbox"></div>
                                        <label for="multiselect-checkbox-checker-0" class="">' . lang('Select All') . '</label>
                                    </div>
                                </div>
                                <div class="card-body overflow-auto" style="max-height:200px">
                                    <input type="hidden" name="contact_groups" value="" />
                                    ' . $output_contact_groups . '
                                </div>
                            </div>
                        </div>

                        <div class="col-12"><h5 class="fg-success fw-bold mt-7 mb-3">' . lang('General') . '</h5></div>
                        <div class="col-12 sm:col-6 md:col-12 my-1">
                            <label for="salutation" class="form-label">' . lang('Salutation') . '</label>
                            <input type="text" id="salutation" name="salutation" class="form-control" value="' . h(($_SESSION['software']['view_contacts']['salutation'] ?? '')) . '" />
                        </div>
                        <div class="col-12 sm:col-6 md:col-12 my-1">
                            <label for="first_name" class="form-label">' . lang('First Name') . '</label>
                            <input type="text" id="first_name" name="first_name" class="form-control" value="' . h(($_SESSION['software']['view_contacts']['first_name'] ?? '')) . '" />
                        </div>
                        <div class="col-12 sm:col-6 md:col-12 my-1">
                            <label for="last_name" class="form-label">' . lang('Last Name') . '</label>
                            <input type="text" id="last_name" name="last_name" class="form-control" value="' . h(($_SESSION['software']['view_contacts']['last_name'] ?? '')) . '" />
                        </div>
                        <div class="col-12 sm:col-6 md:col-12 my-1">
                            <label for="suffix" class="form-label">' . lang('Suffix') . '</label>
                            <input type="text" id="suffix" name="suffix" class="form-control" value="' . h(($_SESSION['software']['view_contacts']['suffix'] ?? '')) . '" />
                        </div>
                        <div class="col-12 sm:col-6 md:col-12 my-1">
                            <label for="nickname" class="form-label">' . lang('Nickname') . '</label>
                            <input type="text" id="nickname" name="nickname" class="form-control" value="' . h(($_SESSION['software']['view_contacts']['nickname'] ?? '')) . '" />
                        </div>
                        <div class="col-12 sm:col-6 md:col-12 my-1">
                            <label for="company" class="form-label">' . lang('Company') . '</label>
                            <input type="text" id="company" name="company" class="form-control" value="' . h(($_SESSION['software']['view_contacts']['company'] ?? '')) . '" />
                        </div>
                        <div class="col-12 sm:col-6 md:col-12 my-1">
                            <label for="title" class="form-label">' . lang('Title') . '</label>
                            <input type="text" id="title" name="title" class="form-control" value="' . h(($_SESSION['software']['view_contacts']['title'] ?? '')) . '" />
                        </div>
                        <div class="col-12 sm:col-6 md:col-12 my-1">
                            <label for="department" class="form-label">' . lang('Department') . '</label>
                            <input type="text" id="department" name="department" class="form-control" value="' . h(($_SESSION['software']['view_contacts']['department'] ?? '')) . '" />
                        </div>
                        <div class="col-12 sm:col-6 md:col-12 my-1">
                            <label for="office_location" class="form-label">' . lang('Office Location') . '</label>
                            <input type="text" id="office_location" name="office_location" class="form-control" value="' . h(($_SESSION['software']['view_contacts']['office_location'] ?? '')) . '" />
                        </div>
                        <div class="col-12 sm:col-6 md:col-12 my-1">
                            <label for="business_phone" class="form-label">' . lang('Business Phone') . '</label>
                            <input type="text" id="business_phone" name="business_phone" class="form-control" value="' . h(($_SESSION['software']['view_contacts']['business_phone'] ?? '')) . '" />
                        </div>
                        <div class="col-12 sm:col-6 md:col-12 my-1">
                            <label for="home_phone" class="form-label">' . lang('Home Phone') . '</label>
                            <input type="text" id="home_phone" name="home_phone" class="form-control" value="' . h(($_SESSION['software']['view_contacts']['home_phone'] ?? '')) . '" />
                        </div>
                        <div class="col-12 sm:col-6 md:col-12 my-1">
                            <label for="mobile_phone" class="form-label">' . lang('Mobile Phone') . '</label>
                            <input type="text" id="mobile_phone" name="mobile_phone" class="form-control" value="' . h(($_SESSION['software']['view_contacts']['mobile_phone'] ?? '')) . '" />
                        </div>
                        <div class="col-12 sm:col-6 md:col-12 my-1">
                            <label for="business_fax" class="form-label">' . lang('Business Fax') . '</label>
                            <input type="text" id="business_fax" name="business_fax" class="form-control" value="' . h(($_SESSION['software']['view_contacts']['business_fax'] ?? '')) . '" />
                        </div>
                        <div class="col-12 sm:col-6 md:col-12 my-1">
                            <label for="home_fax" class="form-label">' . lang('Home Fax') . '</label>
                            <input type="text" id="home_fax" name="home_fax" class="form-control" value="' . h(($_SESSION['software']['view_contacts']['home_fax'] ?? '')) . '" />
                        </div>
                        <div class="col-12 sm:col-6 md:col-12 my-1">
                            <label for="email_address" class="form-label">' . lang('Email') . '</label>
                            <input type="text" id="email_address" name="email_address" class="form-control" value="' . h(($_SESSION['software']['view_contacts']['email_address'] ?? '')) . '" maxlength="100" inputmode="email" data-inputmask-alias="email" />
                        </div>
                        <div class="col-12 sm:col-6 md:col-12 my-1">
                            <label for="opt_in_status" class="form-label">' . lang('Opt-In Status') . '</label>
                            ' . $output_opt_status . '
                        </div>
                        <div class="col-12 sm:col-6 md:col-12 my-1">
                            <label for="website" class="form-label">' . lang('Website') . '</label>
                            <input type="text" id="website" name="website" class="form-control" value="' . h(($_SESSION['software']['view_contacts']['website'] ?? '')) . '"/>
                        </div>
                        <div class="col-12 sm:col-6 md:col-12 my-1">
                            <label for="website" class="form-label">' . lang('Website') . '</label>
                            <input type="text" id="website" name="website" class="form-control" value="' . h(($_SESSION['software']['view_contacts']['website'] ?? '')) . '"/>
                        </div>
                        <div class="col-12 sm:col-6 md:col-12 my-1">
                            <label for="lead_source" class="form-label">' . lang('Lead Source') . '</label>
                            <input type="text" id="lead_source" name="lead_source" class="form-control" value="' . h(($_SESSION['software']['view_contacts']['lead_source'] ?? '')) . '"/>
                        </div>
                        <div class="col-12 sm:col-6 md:col-12 my-1">
                            <label for="description" class="form-label">' . lang('Description') . '</label>
                            <input type="text" id="description" name="description" class="form-control" value="' . h(($_SESSION['software']['view_contacts']['description'] ?? '')) . '"/>
                        </div>

                        <div class="col-12"><h5 class="fg-success fw-bold mt-7 mb-3">' . lang('Business') . '</h5></div>
                        <div class="col-12 sm:col-6 md:col-12 my-1">
                            <label for="business_address_1" class="form-label">' . lang('Address') . ' 1</label>
                            <input type="text" id="business_address_1" name="business_address_1" class="form-control" value="' . h(($_SESSION['software']['view_contacts']['business_address_1'] ?? '')) . '"/>
                        </div>
                        <div class="col-12 sm:col-6 md:col-12 my-1">
                            <label for="business_address_2" class="form-label">' . lang('Address') . ' 2</label>
                            <input type="text" id="business_address_2" name="business_address_2" class="form-control" value="' . h(($_SESSION['software']['view_contacts']['business_address_2'] ?? '')) . '"/>
                        </div>
                        <div class="col-12 sm:col-6 md:col-12 my-1">
                            <label for="business_city" class="form-label">' . lang('City') . '</label>
                            <input type="text" id="business_city" name="business_city" class="form-control" value="' . h(($_SESSION['software']['view_contacts']['business_city'] ?? '')) . '"/>
                        </div>
                        <div class="col-12 sm:col-6 md:col-12 my-1">
                            <label for="business_state" class="form-label">' . lang('State') . '</label>
                            <input type="text" id="business_state" name="business_state" class="form-control" value="' . h(($_SESSION['software']['view_contacts']['business_state'] ?? '')) . '"/>
                        </div>
                        <div class="col-12 sm:col-6 md:col-12 my-1">
                            <label for="business_zip_code" class="form-label">' . lang('Zip Code') . '</label>
                            <input type="text" id="business_zip_code" name="business_zip_code" class="form-control" value="' . h(($_SESSION['software']['view_contacts']['business_zip_code'] ?? '')) . '"/>
                        </div>
                        <div class="col-12 sm:col-6 md:col-12 my-1">
                            <label for="business_country" class="form-label">' . lang('Country') . '</label>
                            <input type="text" id="business_country" name="business_country" class="form-control" value="' . h(($_SESSION['software']['view_contacts']['business_country'] ?? '')) . '"/>
                        </div>

                        <div class="col-12"><h5 class="fg-success fw-bold mt-7 mb-3">' . lang('Home') . '</h5></div>
                        <div class="col-12 sm:col-6 md:col-12 my-1">
                            <label for="home_address_1" class="form-label">' . lang('Address') . ' 1</label>
                            <input type="text" id="home_address_1" name="home_address_1" class="form-control" value="' . h(($_SESSION['software']['view_contacts']['home_address_1'] ?? '')) . '"/>
                        </div>
                        <div class="col-12 sm:col-6 md:col-12 my-1">
                            <label for="home_address_2" class="form-label">' . lang('Address') . ' 2</label>
                            <input type="text" id="home_address_2" name="home_address_2" class="form-control" value="' . h(($_SESSION['software']['view_contacts']['home_address_2'] ?? '')) . '"/>
                        </div>
                        <div class="col-12 sm:col-6 md:col-12 my-1">
                            <label for="home_city" class="form-label">' . lang('City') . '</label>
                            <input type="text" id="home_city" name="home_city" class="form-control" value="' . h(($_SESSION['software']['view_contacts']['home_city'] ?? '')) . '"/>
                        </div>
                        <div class="col-12 sm:col-6 md:col-12 my-1">
                            <label for="home_state" class="form-label">' . lang('State') . '</label>
                            <input type="text" id="home_state" name="home_state" class="form-control" value="' . h(($_SESSION['software']['view_contacts']['home_state'] ?? '')) . '"/>
                        </div>
                        <div class="col-12 sm:col-6 md:col-12 my-1">
                            <label for="home_zip_code" class="form-label">' . lang('Zip Code') . '</label>
                            <input type="text" id="home_zip_code" name="home_zip_code" class="form-control" value="' . h(($_SESSION['software']['view_contacts']['home_zip_code'] ?? '')) . '"/>
                        </div>
                        <div class="col-12 sm:col-6 md:col-12 my-1">
                            <label for="home_country" class="form-label">' . lang('Country') . '</label>
                            <input type="text" id="home_country" name="home_country" class="form-control" value="' . h(($_SESSION['software']['view_contacts']['home_country'] ?? '')) . '"/>
                        </div>

                        <div class="col-12"><h5 class="fg-success fw-bold mt-7 mb-3">' . lang('Membership') . '</h5></div>
                        ' . $output_membership_status . '
                        <div class="col-12 sm:col-6 md:col-12 my-1">
                            <label for="member_id" class="form-label">' . h(MEMBER_ID_LABEL) . '</label>
                            <input type="text" id="member_id" name="member_id" class="form-control" value="' . h(($_SESSION['software']['view_contacts']['member_id'] ?? '')) . '"/>
                        </div>
                        <div class="col-12 sm:col-6 md:col-12 my-1">
                            <label for="member_id" class="form-label">' . lang('Expiration Date') . '</label>
                            <input type="text" id="expiration_date" name="expiration_date" class="form-control" value="' . h(($_SESSION['software']['view_contacts']['expiration_date'] ?? '')) . '" data-pg-date />
                            ' . get_date_picker_format() . pg_fields_script() . '
                        </div>
                        
                        ' . $output_affiliate . '
                        
                        <div class="col-12"><h5 class="fg-success fw-bold mt-7 mb-3">' . lang('Date Range') . '</h5></div>
                        <div class="col-12">
                            <label class="form-label">' . lang('From') . '</label>
                            <select class="form-control my-1" name="start_month">' . select_month(($_SESSION['software']['view_contacts']['start_month'] ?? '')) . '</select>
                            <div class="input-group input-group-sm">
                                <select class="form-control my-1" name="start_day">' . select_day(($_SESSION['software']['view_contacts']['start_day'] ?? '')) . '</select>
                                <select class="form-control my-1" name="start_year">' . select_year($years, ($_SESSION['software']['view_contacts']['start_year'] ?? '')) . '</select>
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label">' . lang('To') . '</label>
                            <select class="form-control my-1" name="stop_month">' . select_month(($_SESSION['software']['view_contacts']['stop_month'] ?? '')) . '</select>
                            <div class="input-group input-group-sm">
                                <select class="form-control my-1" name="stop_day">' . select_day(($_SESSION['software']['view_contacts']['stop_day'] ?? '')) . '</select>
                                <select class="form-control my-1" name="stop_year">' . select_year($years, ($_SESSION['software']['view_contacts']['stop_year'] ?? '')) . '</select>
                            </div>
                        </div>
                        <div class="col-12 text-center position-sticky my-3" style="bottom:.5rem;">
                            <button type="submit" name="submit_data" value="Update" class="btn btn-solid theme-primary my-1"><i class="material-icons me-3">sync</i>' . lang('Update') . '</button>
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
            <button type="submit" name="submit_data" value="Delete Contacts" class="btn btn-link pg-link-danger py-0 m-1" data-confirm-content="' . lang('WARNING: All contacts that match the filters will be permanently deleted. This includes contacts from all result pages that might exist. Please make sure that you perform an update to the filters before you attempt to delete. An update will allow you to see which contacts will be deleted before you actually delete them. If you would like to continue with the deletion, please click OK. Otherwise, please click Cancel.') . '"><span class="bi bi-trash3-fill me-1"></span>' . lang(array('string'=>'Delete All Contacts') ) . '</button>
        </div>';
    }
    
    $output_user_label = '';
    $output_opt_in_label = '';
    $output_phone_label = '';
    $output_email_address_label = '';
    $output_address_labels = '';
    
    // If the contacts by user filter, my_contacts by business address, and my contacts by home address is not on, then output the user label
    if (($filter != 'my_contacts_by_business_address') && ($filter != 'my_contacts_by_home_address')) {
        $output_user_label = '<th>' . get_column_heading(lang('User'), $sort_levels, '', $output_filter_for_links, 'user') . '</th>';

    }

    // if the filter is not set to either my contacts by business address or my contacts by home address then output the op_in, phone and email address labels
    if (($filter != 'my_contacts_by_business_address') && ($filter != 'my_contacts_by_home_address')) {
        $output_opt_in_label = '<th class="text-center">' . get_column_heading(lang('Opt-In'), $sort_levels, '', $output_filter_for_links, 'opt_in') . '</th>';
        $output_phone_label = '<th>' . lang('Phone') . '</th>';
        $output_email_address_label = '<th>' . get_column_heading(lang('Email'), $sort_levels, '', $output_filter_for_links, 'email') . '</th>';
    
    // else one of the above filters has been selected so output the address table headings
    } else {
        $output_address_labels = '<th>' . lang('Address') . '</th>';
        $output_address_labels .= '<th>' . get_column_heading(lang('City'), $sort_levels, '', $output_filter_for_links, 'city') . '</th>';
        $output_address_labels .= '<th>' . get_column_heading(lang('State'), $sort_levels, '', $output_filter_for_links, 'state') . '</th>';
        $output_address_labels .= '<th>' . get_column_heading(lang('Zip Code'), $sort_levels, '', $output_filter_for_links, 'zip_code') . '</th>';
        $output_address_labels .= '<th>' . get_column_heading(lang('Country'), $sort_levels, '', $output_filter_for_links, 'country') . '</th>';
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
                <div class="form-field">
                    <div class="switch"><input class="" title="' . lang(array('string'=>'Select/Deselect All') ) . '" type="checkbox" id="select_all"></div>
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
             <button type="submit" name="submit_data" value="Merge Contacts" class="btn btn-link pg-link-warning py-0 m-1" data-confirm-content="' . lang('WARNING: All contacts that match the filters will be permanently merged together. This includes contacts from all result pages that might exist. Please make sure that you perform an update to the filters before you attempt to merge. An update will allow you to see which contacts will be merged before you actually merge them. If you would like to continue with the merge, please click OK. Otherwise, please click Cancel.') . '"><span class="material-icons me-1">healing</span>' . lang(array('string'=>'Merge') ) . '</button>
         </div>';
        
        // output the merge selected button
        $output_modify_selected_buttons = '<button type="button" value="Merge Selected" class="btn mb-1 mt-1 btn-solid theme-warning disabled" data-confirm-content="' . lang(array('string'=>'WARNING: The selected duplicate {var:1} will be merged together.','vars'=>array(lang('contacts')))) . '"><span class="material-icons me-3">healing</span>' . lang(array('string'=>'Merge Selected') ) . '</button>';
    
    // else output the default table headings
    } else {
        $output_table_headings = 
            '<th class="noVis">
                <div class="form-field">
                    <div class="switch"><input class="" title="' . lang(array('string'=>'Select/Deselect All') ) . '" type="checkbox" id="select_all"></div>
                </div>
            </th>
            <th class="noVis">' . lang('Action') . '</th> 
            ' . $output_image_header . '
            <th>' . get_column_heading(lang('First Name'), $sort_levels, '', $output_filter_for_links, 'first_name') . '</th>
            <th>' . get_column_heading(lang('Last Name'), $sort_levels, '', $output_filter_for_links, 'last_name') . '</th>
            <th>' . get_column_heading(lang('Company'), $sort_levels, '', $output_filter_for_links, 'company') . '</th>
            ' . $output_phone_label . '
            ' . $output_email_address_label . '
            ' . $output_user_label . '
            ' . $output_opt_in_label . '
            ' . $output_address_labels . '
            <th>' . $output_contact_groups_toggle . '</th>
            <th>' . get_column_heading(lang('Last Modified'), $sort_levels, '', $output_filter_for_links, 'last_modified') . '</th>';
        
        // output the opt in and opt out buttons
        $output_modify_selected_buttons = '
        <button type="button" value="Opt-In Selected" class="btn mb-1 mt-1 btn-solid theme-secondary disabled" data-confirm-content="' . lang(array('string'=>'WARNING: The selected {var:1} will be opted-in.','vars'=>array(lang('contacts')))) . '"><span class="material-icons me-3">radio_button_checked</span>' . lang(array('string'=>'Opt-In Selected') ) . '</button>
        <button type="button" value="Opt-Out Selected" class="btn mb-1 mt-1 btn-solid theme-secondary disabled" data-confirm-content="' . lang(array('string'=>'WARNING: The selected {var:1} will be opted-out.','vars'=>array(lang('contacts')))) . '"><span class="material-icons me-3">radio_button_unchecked</span>' . lang(array('string'=>'Opt-Out Selected') ) . '</button>';
    
    }
    
    $output .=
    pg_page_shell(
        array(
            'title'=> lang($heading),
            'extra classes'=>'contact',
            'icon'=>'contact', 
            'heading'=>lang($heading),
            'heading_description' => ($subheading ?? lang('Everyone in the contact database')),
                    
        )
    ) . '
<main id="content" class="container-fluid">  
    ' . $output_advanced_filters . '
            <div class="row">
            <div class="col-12">
                ' . $liveform->output_errors() . '
                ' . $liveform->get_warnings() . '
                ' . $liveform->output_notices() . '
                <nav id="button_bar" class="pg-toolbar navigation" aria-label="Button Bar">
                            <form id="export_form" class="disable_shortcut d-inline-block" method="get">
                                ' . get_token_field() . '
                                <a class="btn btn-sm btn-solid theme-primary m-1 " href="add_contact.php?send_to=' . h(REQUEST_URL) . '"><span class="bi bi-plus-circle me-3"></span>' . lang(array('string'=>'Create') ) . '</a>
                                <div class=" btn-group btn-group-sm flex-wrap">
                                    <a class="btn btn-link pg-link-secondary py-0 m-1" href="import_contacts.php?send_to=' . h(REQUEST_URL) . '"><span class="bi bi-box-arrow-in-right me-1"></span>' . lang(array('string'=>'Import') ) . '</a>
                                    <button type="submit" name="submit_data" value="Export Contacts" class="btn btn-link pg-link-secondary py-0 m-1"><span class="bi bi-file-earmark-arrow-down bi-me-2"></span>' . lang(array('string'=>'Export') ) . '</button>
                                </div>
                                ' . $output_merge_contacts_button . $output_delete_contacts_button . '
                            </form>
                            <div class="pg-toolbar-grow"></div>
                            ' . (($filter != 'all_duplicate_contacts') ? pg_list_sort_menu('view_contacts.php', $sort_columns, $sort_levels, $sort_default, array('filter' => $filter)) : '') . '
                            <form id="search_form" action="view_contacts.php" method="get" class="search_form disable_shortcut">
                                <input type="hidden" name="filter" value="' . h($filter) . '">
                                <div class="input-group input-group-sm w-auto">
                                    <label class="input-group-text material-icons" title="' . lang('Content that viewed') . '" for="filter">visibility</label>
                                    <select id="filter" name="filter" aria-label="' . h(lang('Content that viewed')) . '" class="form-control" title="' . lang('Content that viewed') . '" onchange="submit_form(\'search_form\')">' . get_filter_options($filters_in_array, $filter) . '</select>
                                </div>
                                ' . $output_contact_group_selection . '
                            </form>
                            <a class="btn btn-sm btn-text theme-secondary' . $output_advanced_filters_class . '" title="' . $output_advanced_filters_label . '" aria-label="' . $output_advanced_filters_label . '" href="view_contacts.php?advanced_filters=' . $output_advanced_filters_value . '"><i class="bi ' . $advanced_filters_icon . '" aria-hidden="true"></i></a>
                            ' . (($show_hide_date_range === '') ? pg_period_nav('view_contacts.php', ($_SESSION['software']['view_contacts'] ?? array()), array(
                                'day'   => array($decrease_day, $current_day, $increase_day),
                                'week'  => array($decrease_week, $current_week, $increase_week),
                                'month' => array($decrease_month, $current_month, $increase_month),
                                'year'  => array($decrease_year, $current_year, $increase_year),
                            ), $output_date_range_time) : '') . '
                </nav>
                <div class="card my-7">
                    <div class="card-body p-0 position-relative">
                        <form name="form"  action="edit_contacts.php" method="post" class="view_orders"> 
                            ' . get_token_field() . ' 
                            <input type="hidden" name="action" />
                            <input type="hidden" name="add_to_contact_groups" />
                            <input type="hidden" name="remove_from_contact_groups" />
                            <input type="hidden" name="send_to" value="' . h(REQUEST_URL) . '" />
                            <table class="chart table-hover table " style="width:100%;display:none"' . $output_list_attributes . '>
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
                                        <button type="button" value="Delete Selected" class="btn mb-1 mt-1 btn-solid theme-danger disabled" data-confirm-content="' . lang(array('string'=>'WARNING: Selected {var:1} will be permanently deleted.','vars'=>array(lang('contacts')))) . '"><span class="material-icons me-3">delete</span>' . lang(array('string'=>'Delete Selected') ) . '</button>
                                    </div>
                                </div>
                            </nav>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    
</main>' . output_footer();

    echo $output;

    $liveform->remove_form('view_contacts');
}