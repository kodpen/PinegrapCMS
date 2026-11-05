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

validate_token_field();

include_once('liveform.class.php');
require_once(PG_FUNCTIONS_DIR . '/includes/forms/contact_form.php');

// The contacts list sends its selection as the list table driver keeps it:
// the contacts picked, or the whole view by its condition less the
// exceptions. It is read back with the list's own query and the user's
// rights (pg_contact_list_selected_ids()).
require_once(PG_FUNCTIONS_DIR . '/includes/fn/contact_list.php');
$contact_ids = pg_contact_list_selected_ids($user, $_POST['selection'] ?? '', $_POST['list_params'] ?? '');

if ($contact_ids === false) {
    output_error(lang('Query failed.'));
}

if ($contact_ids === null) {
    output_error(lang('Too many contacts are selected. Select fewer contacts and try again.') . ' <a href="javascript:history.go(-1);">' . lang('Go back') . '</a>.');
}

// if at least one contact was selected
if ($contact_ids) {
    $number_of_contacts = 0;

    switch ($_POST['action'] ?? '') {
        // if contacts are being organized
        case 'organize':
            $add_to_contact_groups = pg_contact_list_posted_group_ids($_POST['add_to_contact_groups'] ?? '');
            $remove_from_contact_groups = pg_contact_list_posted_group_ids($_POST['remove_from_contact_groups'] ?? '');

            // A group filled by rules is left to its rules: neither added to
            // nor taken from here.
            $add_split = pg_audience_hand_ids($add_to_contact_groups, pg_audience_dynamic_group_ids());
            $remove_split = pg_audience_hand_ids($remove_from_contact_groups, pg_audience_dynamic_group_ids());
            $add_to_contact_groups = $add_split['hand'];
            $remove_from_contact_groups = $remove_split['hand'];
            $refused_by_rule = $add_split['rule'] || $remove_split['rule'];

            // Nothing chosen: nothing is changed or logged, and the list says why.
            if (!$add_to_contact_groups && !$remove_from_contact_groups && !$refused_by_rule) {
                $liveform_view_contacts = new liveform('view_contacts');
                $liveform_view_contacts->add_notice(lang('No contact group was selected.'));
                break;
            }

            // Who actually moved, by group, announced once per group below.
            $moved = array();

            foreach ($contact_ids as $contact_id) {
                // if user has access to contact, then continue
                if (validate_contact_access($user, $contact_id) == true) {
                    // loop through all contact groups that contact should be added to
                    foreach ($add_to_contact_groups as $contact_group_id) {
                        // only a group the user may manage, as on the single save
                        if (validate_contact_group_access($user, $contact_group_id) == false) {
                            continue;
                        }

                        // check to see if contact is already in contact group
                        $query =
                            "SELECT contact_id
                            FROM contacts_contact_groups_xref
                            WHERE
                                (contact_id = ?)
                                AND (contact_group_id = ?)";
                        $result = db_q($query, array((string) $contact_id, (string) $contact_group_id)) or output_error('Query failed.');
                        
                        // if contact is not already in contact group, then add contact to contact group
                        if (mysqli_num_rows($result) == 0) {
                            $query =
                                "INSERT INTO contacts_contact_groups_xref (
                                    contact_id,
                                    contact_group_id)
                                VALUES (
                                    ?,
                                    ?)";
                            $result = db_q($query, array((string) $contact_id, (string) $contact_group_id)) or output_error('Query failed.');

                            $moved[(int) $contact_group_id]['added'][] = (int) $contact_id;
                        }
                    }
                    
                    // loop through all contact groups that contact should be removed from
                    foreach ($remove_from_contact_groups as $contact_group_id) {
                        // only a group the user may manage, as on the single save
                        if (validate_contact_group_access($user, $contact_group_id) == false) {
                            continue;
                        }

                        // remove contact from contact group
                        $query =
                            "DELETE FROM contacts_contact_groups_xref
                            WHERE
                                (contact_id = ?)
                                AND (contact_group_id = ?)";
                        $result = db_q($query, array((string) $contact_id, (string) $contact_group_id)) or output_error('Query failed.');

                        if (mysqli_affected_rows(db::$con) > 0) {
                            $moved[(int) $contact_group_id]['removed'][] = (int) $contact_id;
                        }
                    }
                }
            }

            // Only the contacts whose groups really changed count.
            $number_of_contacts = pg_contact_list_organized_count($moved);

            foreach ($moved as $contact_group_id => $contacts) {
                pg_announce_contact_group_members(
                    $contact_group_id,
                    isset($contacts['added']) ? $contacts['added'] : array(),
                    isset($contacts['removed']) ? $contacts['removed'] : array());
            }

            // if more than 0 contacts were organized, then log activity
            if ($number_of_contacts > 0) {
                log_activity( lang(array('string'=>'{var:1} contact(s) were organized','vars'=>pg_format_number($number_of_contacts, 0) )) , $_SESSION['sessionusername']);
            }
            $liveform_view_contacts = new liveform('view_contacts');
            $liveform_view_contacts->add_notice( lang(array('string'=>'{var:1} contact(s) were organized','vars'=>pg_format_number($number_of_contacts, 0) )) );

            if ($refused_by_rule) {
                $liveform_view_contacts->add_notice(pg_audience_door_message());
            }
            
            break;

        // if contacts are being opted-in
        case 'optin':
            // every contact with a ticked address follows; only real changes count
            $number_of_contacts = pg_contact_bulk_set_opt_in($user, $contact_ids, '1');
            
            // if at least one contact was opted-in, log activity
            if ($number_of_contacts > 0) {
                log_activity( lang(array('string'=>'{var:1} contact(s) were opted-in','vars'=>pg_format_number($number_of_contacts, 0) )) , $_SESSION['sessionusername']);
            }
            
            $liveform_view_contacts = new liveform('view_contacts');
            $liveform_view_contacts->add_notice( lang(array('string'=>'{var:1} contact(s) were opted-in','vars'=>pg_format_number($number_of_contacts, 0) )) );
            
            break;

        // if contacts are being opted-out
        case 'optout':
            // every contact with a ticked address follows; only real changes count
            $number_of_contacts = pg_contact_bulk_set_opt_in($user, $contact_ids, '0');
            
            // if more than 0 contacts were opted-out, then log activity
            if ($number_of_contacts > 0) {
                log_activity( lang(array('string'=>'{var:1} contact(s) were opted-out','vars'=>pg_format_number($number_of_contacts, 0) )) , $_SESSION['sessionusername']);
            }
            
            $liveform_view_contacts = new liveform('view_contacts');
            $liveform_view_contacts->add_notice( lang(array('string'=>'{var:1} contact(s) were opted-out','vars'=>pg_format_number($number_of_contacts, 0) )) );
            
            break;

        // if contacts are being deleted
        case 'delete':
            $audit_before = array();

            foreach ($contact_ids as $contact_id) {
                // if user has access to contact, then continue
                if (validate_contact_access($user, $contact_id) == true) {
                    // A contact a user account or an ERP account holds stays
                    // and is named in the notice; the others are deleted.
                    $refused = pg_contact_delete_refusals(array($contact_id));

                    if ($refused) {
                        $delete_refusals = ($delete_refusals ?? array()) + $refused;
                        continue;
                    }

                    $audit_before += pg_audit_fetch_many('contacts', array($contact_id));

                    // delete contact
                    $query = "DELETE FROM contacts WHERE id = ?";
                    $result = db_q($query, array((string) $contact_id)) or output_error('Query failed.');
                    
                    // delete contact references in contacts_contact_groups_xref
                    $query = "DELETE FROM contacts_contact_groups_xref WHERE contact_id = ?";
                    $result = db_q($query, array((string) $contact_id)) or output_error('Query failed.');
                    
                    // delete contact references in opt_in
                    $query = "DELETE FROM opt_in WHERE contact_id = ?";
                    $result = db_q($query, array((string) $contact_id)) or output_error('Query failed.');

                    $number_of_contacts++;
                }
            }

            pg_audit_record_many('delete', 'contacts', $audit_before);
            
            // if more than 0 contacts were deleted, then log activity
            if ($number_of_contacts > 0) {
                log_activity( lang(array('string'=>'{var:1} contact(s) were deleted','vars'=>pg_format_number($number_of_contacts, 0) )) , $_SESSION['sessionusername']);
            }
            
            $liveform_view_contacts = new liveform('view_contacts');
            $liveform_view_contacts->add_notice( lang(array('string'=>'{var:1} contact(s) were deleted','vars'=>pg_format_number($number_of_contacts, 0) )) );

            if (!empty($delete_refusals)) {
                $liveform_view_contacts->add_warning(h(pg_contact_delete_refused_notice($delete_refusals)));
            }
            
            break;
            
        // if contacts are being merged, then merge them
        case 'merge':
            // if user has a user role then user does not have access to this filter so output error
            if ($user['role'] == 3) {
                log_activity(lang('access denied to merge contacts'), $_SESSION['sessionusername']);
                output_error(lang('Access denied') . '. <a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.');
            }
            
            $where = '';
            
            // loop through selected contacts and build an sql where statement to get the data for only these contacts
            foreach ($contact_ids as $contact_id) {
                // If where is not blank then add OR
                if ($where != '') {
                    $where .= ' OR ';
                }
                
                $where .= "(contacts.id = '" . escape($contact_id) . "')";
            }
            
            // if where is not blank, then prepare it for sql query
            if ($where != '') {
                $where = "WHERE (" . $where . ")";
            }
            
            $selected_contacts = array();
            
            // get contacts to be merged information
            $query = 
                "SELECT " . pg_contact_merge_select(pg_contact_merge_optional_columns(pg_contact_merge_schema_columns())) . "
                FROM contacts
                LEFT JOIN user AS contact_user ON contacts.id = contact_user.user_contact
                LEFT JOIN contacts_contact_groups_xref ON contacts.id = contacts_contact_groups_xref.contact_id
                $where
                ORDER BY contacts.email_address ASC";
            $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
            while($row = mysqli_fetch_assoc($result)) {
                $selected_contacts[] = $row;
            }
            
            $duplicate_contacts_to_merge = array();
            
            // loop through all contacts to remove any contacts that are not duplicates
            foreach ($selected_contacts as $selected_contact) {
                $duplicate_counter = 0;
                
                // loop through all contacts to determine if this contact has any duplicates
                foreach ($selected_contacts as $current_contact) {
                    // if the selected contact email is the same as the current contact email then increment the counter
                    if (mb_strtolower($selected_contact['email_address']) == mb_strtolower($current_contact['email_address'])) {
                        $duplicate_counter++;
                    }
                }
                
                // if the counter is greater than one, then add this contact to the duplicate contacts to be merged array
                if ($duplicate_counter > 1) {
                    $duplicate_contacts_to_merge[] = $selected_contact;
                }
            }
            
            // merge the contacts, then refresh the screen with a notice
            $number_of_merged_contacts = merge_contacts($duplicate_contacts_to_merge, $number_of_refused_groups);
            
            $notice = '';
            
            // if contacts were merged then output a notice informing the user how many were merged
            if ($number_of_merged_contacts > 0) {
                $notice =  lang(array('string'=>'{var:1} contact(s) have been merged successfully.','vars'=>pg_format_number($number_of_merged_contacts, 0) ));
                log_activity( lang(array('string'=>'{var:1} contact(s) have been merged successfully.','vars'=>pg_format_number($number_of_merged_contacts, 0) )) , $_SESSION['sessionusername']);
            
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
            
            $liveform_view_contacts = new liveform('view_contacts');
            $liveform_view_contacts->add_notice($notice);
            
            break;
    }
}

// If there is a send to value then send user back to that screen
if ((isset($_REQUEST['send_to']) == TRUE) && ($_REQUEST['send_to'] != '')) {
    header('Location: ' . URL_SCHEME . HOSTNAME . pg_safe_redirect_path(($_REQUEST['send_to'] ?? '')));
    
// else send user to the default view
} else {
    header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/view_contacts.php');
}
?>