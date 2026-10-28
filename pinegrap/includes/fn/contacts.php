<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// A module of functions.php: Contact merging.
//
// Loaded by functions.php through require_once, never on its own. The
// functions were moved here from functions.php as they were; PG_FUNCTIONS_DIR
// is the software directory (what dirname(__FILE__) was in functions.php),
// see the note at the top of functions.php.
if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}
/**
 * Every place a contact's id is kept, the one list the contact merge and the
 * personal data erasure share. tests/contact_holders_test.php lays it against
 * every contact_id (and user_contact) column of the install schema and
 * against pg_privacy_erase_tables(), so a new table cannot be missed by
 * either.
 *
 * column: the column holding the contact id (table: when the key is not the
 *         table, type: column => value when the column also names other
 *         kinds of record).
 * merge:  move     merge_contacts() re-points the merged contacts' rows to
 *                  the contact that stays;
 *         one      as move, but a group of duplicates with more than one
 *                  contact holding a row is not merged (a contact has one
 *                  ERP account);
 *         groups   merge_contacts()'s own contact group and opt-in step;
 *         keep     left as it is: the consent log is proof of what that
 *                  record answered;
 *         survivor never names a merged contact: a contact a user holds is
 *                  always the one that stays.
 * erase:  find     pg_privacy_erase_tables() finds the rows by contact_ids;
 *         scope    found through pg_privacy_erase_scope() (orders, forms,
 *                  ERP accounts) or the person's users;
 *         none     not reached by the erasure (Workspace records).
 *
 * @return array  key => column, merge, erase, optional table and type
 */
function pg_contact_holders()
{
    return array(
        'orders'                       => array('column' => 'contact_id', 'merge' => 'move', 'erase' => 'scope'),
        'forms'                        => array('column' => 'contact_id', 'merge' => 'move', 'erase' => 'scope'),
        'email_recipients'             => array('column' => 'contact_id', 'merge' => 'move', 'erase' => 'find'),
        'erp_accounts'                 => array('column' => 'contact_id', 'merge' => 'one', 'erase' => 'scope'),
        'opt_in'                       => array('column' => 'contact_id', 'merge' => 'groups', 'erase' => 'find'),
        'contacts_contact_groups_xref' => array('column' => 'contact_id', 'merge' => 'groups', 'erase' => 'find'),
        'consent_log'                  => array('column' => 'contact_id', 'merge' => 'keep', 'erase' => 'find'),
        'user'                         => array('column' => 'user_contact', 'merge' => 'survivor', 'erase' => 'scope'),
        'ws_channels'                  => array('column' => 'contact_id', 'merge' => 'move', 'erase' => 'none'),
        'ws_channels_customer'         => array('table' => 'ws_channels', 'column' => 'customer_id', 'type' => array('customer_type' => 'contact'), 'merge' => 'move', 'erase' => 'none'),
        'ws_refs'                      => array('column' => 'ref_id', 'type' => array('ref_type' => 'contact'), 'merge' => 'move', 'erase' => 'none'),
        'ws_approvals'                 => array('column' => 'record_id', 'type' => array('record_type' => 'contact'), 'merge' => 'move', 'erase' => 'none'),
        'ws_ai_changes'                => array('column' => 'record_id', 'type' => array('record_type' => 'contact'), 'merge' => 'move', 'erase' => 'none'),
    );
}

/**
 * The contacts of a group of duplicates that hold a row of a holder whose
 * merge is "one"; more than one of them refuses the merge.
 *
 * @param array $contact_ids  the contact that stays and the merged ones
 * @return array  holder key => contact ids holding a row
 */
function pg_contact_merge_conflicts($contact_ids)
{
    $contact_ids = array_values(array_unique(array_map('intval', (array) $contact_ids)));
    $conflicts = array();

    foreach (pg_contact_holders() as $key => $holder) {
        $table = isset($holder['table']) ? $holder['table'] : $key;

        if (($holder['merge'] !== 'one') || !$contact_ids || !pg_schema_has($table, $holder['column'])) {
            continue;
        }

        $holding = db_values(
            "SELECT DISTINCT " . $holder['column'] . " FROM " . $table . " WHERE " . $holder['column'] . " IN (" . implode(',', $contact_ids) . ")"
        );

        if (count((array) $holding) > 1) {
            $conflicts[$key] = array_map('intval', (array) $holding);
        }
    }

    return $conflicts;
}

/**
 * Re-points the rows of every "move" and "one" holder from the merged
 * contacts to the contact that stays. Runs before the merged contacts are
 * deleted, so a merge stopped half-way leaves no row naming a deleted
 * contact that the next merge would not move.
 *
 * @param array $merged_ids  the contacts merged away
 * @param int   $keep_id     the contact that stays
 * @return void
 */
function pg_contact_holders_move($merged_ids, $keep_id)
{
    $keep_id = (int) $keep_id;
    $merged_ids = array_values(array_diff(array_unique(array_map('intval', (array) $merged_ids)), array(0, $keep_id)));

    if (($keep_id <= 0) || !$merged_ids) {
        return;
    }

    foreach (pg_contact_holders() as $key => $holder) {
        $table = isset($holder['table']) ? $holder['table'] : $key;

        if (!in_array($holder['merge'], array('move', 'one'), true) || !pg_schema_has($table, $holder['column'])) {
            continue;
        }

        $where = $holder['column'] . " IN (" . implode(',', $merged_ids) . ")";
        $values = array((string) $keep_id);

        foreach (isset($holder['type']) ? $holder['type'] : array() as $type_column => $type_value) {
            $where .= " AND " . $type_column . " = ?";
            $values[] = $type_value;
        }

        db_q("UPDATE " . $table . " SET " . $holder['column'] . " = ? WHERE " . $where, $values) or output_error(lang('Query failed.'));
    }
}

// This function merges duplicate contacts together and then forwards the user back to the all duplicate contacts view.
// Every record that names a merged contact is re-pointed to the contact that stays (pg_contact_holders()); a group
// of duplicates where more than one contact has an ERP account is left as it is and counted in $refused.
function merge_contacts($contacts, &$refused = 0)
{
    $refused = 0;
    $child_contacts = array();
    $organized_contacts = array();
    /*** Start contacts array organization: ***/
    /* Organize the contacts array so that a child always comes

    before it's orphans. This is needed so that we can merge in 

    alphabetical order and always deal with the child before it's orphans.*/
    // loop through the contacts to put children contacts in their own array
    foreach ($contacts as $key => $contact) {
        // if the contact has a user id then it is a child so add it to the child contacts array
        if ($contact['user_id'] != '') {
            $child_contacts[$key] = $contact;
        }
    }
    // loop through all contacts to organize them
    foreach ($contacts as $contact) {
        // loop through all child contacts to see if there are any children for this contact
        foreach ($child_contacts as $key => $child_contact) {
            // if the child's email address is the same as the contact's email address, then add it to the organized contacts array,
            // and remove it from the other arrays so that it isn't found again
            if (mb_strtolower($child_contact['email_address']) == mb_strtolower($contact['email_address'])) {
                $organized_contacts[] = $child_contact;
                // remove contact from arrays
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
    /*** End array organization ***/
    $contacts_contact_groups_xref_include_where_statement = '';
    $contacts_contact_groups_xref_include_values = array();
    // loop through the contacts and build sql where statement to include the contacts, this will be used to get all of the contact groups to merge
    foreach ($contacts as $contact) {
        if ($contacts_contact_groups_xref_include_where_statement != '') {
            $contacts_contact_groups_xref_include_where_statement .= ' OR ';
        }
        $contacts_contact_groups_xref_include_where_statement .= "(contacts_contact_groups_xref.contact_id = ?)";
        $contacts_contact_groups_xref_include_values[] = (string) $contact['id'];
    }
    $where = '';
    // if there are contact groups to get then set the where statement to get them
    if ($contacts_contact_groups_xref_include_where_statement != '') {
        // If where is blank
        if ($where == '') {
            $where .= ' WHERE ';
            // else where is not blank, so add and
        } else {
            $where .= ' AND ';
        }
        $where .= "(" . $contacts_contact_groups_xref_include_where_statement . ")";
    }
    $contact_groups = array();
    // get the contact groups that will be merged
    $query = "SELECT

            contacts_contact_groups_xref.contact_id,

            contacts_contact_groups_xref.contact_group_id,

            contact_groups.name,

            opt_in.opt_in,

            contact_groups.email_subscription

        FROM contacts_contact_groups_xref

        LEFT JOIN contact_groups ON contact_groups.id = contacts_contact_groups_xref.contact_group_id

        LEFT JOIN opt_in ON (contacts_contact_groups_xref.contact_id = opt_in.contact_id) AND (contacts_contact_groups_xref.contact_group_id = opt_in.contact_group_id)

        $where";
    $result = db_q($query, $contacts_contact_groups_xref_include_values) or output_error(lang('Query failed.'));
    while ($row = mysqli_fetch_assoc($result)) {
        $contact_groups[] = $row;
    }
    $processed_contact_ids = array();
    $merged_contacts_counter = 0;
    // loop through each contact and process a merge if able
    foreach ($contacts as $contact) {
        // if this contact is a child and if it has not already been processed, then process a merge for a child
        if (($contact['user_id'] != '') && (in_array($contact['id'], $processed_contact_ids) == false)) {
            // define the contact as a child so that it is easier to read comparisons later on
            $child = $contact;
            // add this child to the list of processed contacts so that it isn't processed again
            $processed_contact_ids[] = $child['id'];
            $orphans_to_merge = array();
            // get all the orphans that match the child's e-mail address and put into an array
            foreach ($contacts as $orphan) {
                // if the orphan email adress is the same as the current child's, and if this orphan is not a child,
                // and if this orphan has not already been processed, then add it to add it to arrays to be processed
                if ((mb_strtolower($orphan['email_address']) == mb_strtolower($child['email_address'])) && ($orphan['user_id'] == '') && (in_array($orphan['id'], $processed_contact_ids) == false)) {
                    $orphans_to_merge[] = $orphan;
                    $processed_contact_ids[] = $orphan['id'];
                }
            }
            // a group where more than one contact has an ERP account is not merged: one of the accounts would have to go
            if ((count($orphans_to_merge) > 0) && pg_contact_merge_conflicts(array_merge(array($child['id']), array_column($orphans_to_merge, 'id')))) {
                $refused++;
                $orphans_to_merge = array();
            }
            // if there are orphans to merge, then run the logic to merge them
            if (count($orphans_to_merge) > 0) {
                $orphans_to_merge_sort_order = array();
                // loop through each orphan to merge to build a sorting order array
                foreach ($orphans_to_merge as $key => $orphan_to_merge) {
                    $orphans_to_merge_sort_order[$key] = $orphan_to_merge['timestamp'];
                }
                // sort the orphans to merge array by last modified
                array_multisort($orphans_to_merge_sort_order, SORT_DESC, $orphans_to_merge);
                $contact_groups_to_be_merged = array();
                $delete_from_contacts_where_statement = '';
                $delete_from_contacts_values = array();
                $delete_from_contacts_contact_groups_xref_where_statement = '';
                $delete_from_contacts_contact_groups_xref_values = array();
                // loop through orphans to merge to get the child's new opt in status,
                // an array of all contact groups to be merged into the child,
                // build sql where statements to be used to clean up the contacts and contacts contact groups xref table,
                // and increment the merged contacts counter
                foreach ($orphans_to_merge as $orphan_to_merge) {
                    // if the child's new opt in status is not already set to opt out,
                    // and if this orphan's opt in status is set to opt out then set the child's new opt in status to be opt out
                    if (($child['opt_in'] != 0) && ($orphan_to_merge['opt_in'] == 0)) {
                        $child['opt_in'] = 0;
                    }
                    // loop through contact groups to build a list of contact groups to merge into the child contact,
                    foreach ($contact_groups as $contact_group) {
                        // if the conact groups contact id is the same as this orphan's id, or if it is the same as the child's ids, then add the contact groups to the contact groups to be merged array
                        if (($contact_group['contact_id'] == $orphan_to_merge['id']) || ($contact_group['contact_id'] == $child['id'])) {
                            $contact_groups_to_be_merged[] = $contact_group;
                        }
                    }
                    // build sql where statements to be used to clean up the contacts table
                    if ($delete_from_contacts_where_statement != '') {
                        $delete_from_contacts_where_statement .= ' OR ';
                    }
                    $delete_from_contacts_where_statement .= "(id = ?)";
                    $delete_from_contacts_values[] = (string) $orphan_to_merge['id'];
                    // build sql where statements to be used to clean up the contacts contact groups xref table
                    if ($delete_from_contacts_contact_groups_xref_where_statement != '') {
                        $delete_from_contacts_contact_groups_xref_where_statement .= ' OR ';
                    }
                    $delete_from_contacts_contact_groups_xref_where_statement .= "(contact_id = ?)";
                    $delete_from_contacts_contact_groups_xref_values[] = (string) $orphan_to_merge['id'];
                    // increment the merged contacts counter, this number will be used when outputting a notice to the user
                    $merged_contacts_counter++;
                }
                // if the newest orphan is newer than the child then update the timestamp and last modified user
                if ($orphans_to_merge[0]['timestamp'] > $child['timestamp']) {
                    $child['timestamp'] = $orphans_to_merge[0]['timestamp'];
                    $child['user'] = $orphans_to_merge[0]['user'];
                }
                // re-point the merged contacts' records before the contacts are deleted
                pg_contact_holders_move(array_column($orphans_to_merge, 'id'), $child['id']);
                // update child's contact record with new opt in status, last modified date, and last modified user
                $query = "UPDATE contacts

                    SET

                       opt_in = ?,

                       timestamp = ?,

                       user = ?

                    WHERE id = ?";
                $result = db_q($query, array((string) $child['opt_in'], (string) $child['timestamp'], (string) $child['user'], (string) $child['id'])) or output_error(lang('Query failed.'));
                $where = '';
                // if there are orphans to delete then set the where statement to delete them, and then delete them
                if ($delete_from_contacts_where_statement != '') {
                    // If where is blank
                    if ($where == '') {
                        $where .= ' WHERE ';
                        // else where is not blank, so add and
                    } else {
                        $where .= ' AND ';
                    }
                    $where .= "(" . $delete_from_contacts_where_statement . ")";
                    $query = "DELETE FROM contacts $where";
                    $result = db_q($query, $delete_from_contacts_values) or output_error(lang('Query failed.'));
                }
                $where = '';
                // if there are contact groups to delete, then set the where statement to delete them and then delete the contact group and their opt-in status
                if ($delete_from_contacts_contact_groups_xref_where_statement != '') {
                    // If where is blank
                    if ($where == '') {
                        $where .= ' WHERE ';
                        // else where is not blank, so add and
                    } else {
                        $where .= ' AND ';
                    }
                    $where .= "(" . $delete_from_contacts_contact_groups_xref_where_statement . ")";
                    $query = "DELETE FROM contacts_contact_groups_xref $where";
                    $result = db_q($query, $delete_from_contacts_contact_groups_xref_values) or output_error(lang('Query failed.'));
                    $query = "DELETE FROM opt_in $where";
                    $result = db_q($query, $delete_from_contacts_contact_groups_xref_values) or output_error(lang('Query failed.'));
                }
                $merged_contact_group_ids = array();
                // loop through the contact groups to be merged in order to update contact groups xref and opt in table to add references for the child
                foreach ($contact_groups_to_be_merged as $contact_group_to_be_merged) {
                    // if this contact group has not been merged yet, then process the merge
                    if (in_array($contact_group_to_be_merged['contact_group_id'], $merged_contact_group_ids) == false) {
                        // add this contact group to the merged contact groups array so that it is not processed again
                        $merged_contact_group_ids[] = $contact_group_to_be_merged['contact_group_id'];
                        // delete any records from the contacts contact groups xref table for this contact group and child
                        $query = "DELETE FROM contacts_contact_groups_xref WHERE (contact_id = ?) AND (contact_group_id = ?)";
                        $result = db_q($query, array((string) $child['id'], (string) $contact_group_to_be_merged['contact_group_id'])) or output_error(lang('Query failed.'));
                        // insert a new xref record
                        $query = "INSERT INTO contacts_contact_groups_xref (

                                contact_id,

                                contact_group_id)

                            VALUES (

                                ?,

                                ?

                                )";
                        $result = db_q($query, array((string) $child['id'], (string) $contact_group_to_be_merged['contact_group_id'])) or output_error(lang('Query failed.'));
                        $contact_groups_to_be_merged_opt_in = '1';
                        // loop through the contact groups to be merged to get the new opt in value for this contact group,
                        // and add the contact group's id to the merged contact groups ids array so that they are not processed after this one is complete
                        foreach ($contact_groups_to_be_merged as $contact_group) {
                            // if this contact group id is the same as the contact group id that we are merging in, then continue
                            if ($contact_group['contact_group_id'] == $contact_group_to_be_merged['contact_group_id']) {
                                // if the contact group's opt in is set to opt out, then use that value when merging the contact group
                                if ($contact_group['opt_in'] == '0') {
                                    $contact_groups_to_be_merged_opt_in = '0';
                                }
                                // add this contact group to the meged contact groups array so that it is not processed
                                $merged_contact_group_ids[] = $contact_group['contact_group_id'];
                            }
                        }
                        // if contact group has email subscription turned on, then get the opt in value and update database
                        if ($contact_group_to_be_merged['email_subscription'] == 1) {
                            // if the contact groups to be merged opt in is set to opt out, then opt out the contact group we are merging
                            if ($contact_groups_to_be_merged_opt_in == '0') {
                                $contact_group_to_be_merged['opt_in'] = '0';
                                // else default to opt in
                            } else {
                                $contact_group_to_be_merged['opt_in'] = '1';
                            }
                            // delete any records from the opt in table for this contact group and child
                            $query = "DELETE FROM opt_in WHERE (contact_id = ?) AND (contact_group_id = ?)";
                            $result = db_q($query, array((string) $child['id'], (string) $contact_group_to_be_merged['contact_group_id'])) or output_error(lang('Query failed.'));
                            // insert a new opt in record
                            $query = "INSERT INTO opt_in (

                                    contact_id,

                                    contact_group_id,

                                    opt_in)

                                VALUES (

                                    ?,

                                    ?,

                                    ?)";
                            $result = db_q($query, array((string) $child['id'], (string) $contact_group_to_be_merged['contact_group_id'], (string) $contact_group_to_be_merged['opt_in'])) or output_error(lang('Query failed.'));
                        }
                    }
                }
            }
            // else if this is an orphan that hasn't been processed yet then process a merge for an orphan
        } elseif (in_array($contact['id'], $processed_contact_ids) == false) {
            // put contacts into current orphan to make it easier to read the conditions below
            $current_orphan = $contact;
            $orphans_to_merge = array();
            // get all the orphans that match the current orphan's e-mail address and put them into the orphans to merge and processed contact ids arrays
            foreach ($contacts as $orphan) {
                // if the orphan email adress is the same as the current orphan's,
                // and if this orphan has not already been processed, then add it to the orphans to merge and processed contact ids array
                if ((mb_strtolower($orphan['email_address']) == mb_strtolower($current_orphan['email_address'])) && (in_array($orphan['id'], $processed_contact_ids) == false)) {
                    $orphans_to_merge[] = $orphan;
                    $processed_contact_ids[] = $orphan['id'];
                }
            }
            // a group where more than one contact has an ERP account is not merged: one of the accounts would have to go
            if ((count($orphans_to_merge) > 1) && pg_contact_merge_conflicts(array_column($orphans_to_merge, 'id'))) {
                $refused++;
                $orphans_to_merge = array();
            }
            // if there are orphans to merge, then run the logic to merge them
            if (count($orphans_to_merge) > 0) {
                $orphans_to_merge_sort_order = array();
                // loop through each orphan to merge to build a sorting order array
                foreach ($orphans_to_merge as $key => $orphan_to_merge) {
                    $orphans_to_merge_sort_order[$key] = $orphan_to_merge['timestamp'];
                }
                // sort the orphans to merge array by last modified
                array_multisort($orphans_to_merge_sort_order, SORT_DESC, $orphans_to_merge);
                // set the newest orphan as the remaining orphan
                $remaining_orphan = $orphans_to_merge[0];
                // remove the remaining orphan from the orphans to merge array
                unset($orphans_to_merge[0]);
                $remaining_orphans_fields_to_update = array();
                // get the blank fields from the remaining orphan's contact record
                foreach ($remaining_orphan as $key => $remaining_orphan_field_value) {
                    // if the field value is set to the default and if this is not the user id or opt in field, then add the field to the array
                    if ((($remaining_orphan_field_value == '') || ($remaining_orphan_field_value == '0') || ($remaining_orphan_field_value == '0000-00-00')) && ($key != 'user_id') && ($key != 'opt_in')) {
                        $remaining_orphans_fields_to_update[$key] = $remaining_orphan_field_value;
                    }
                }
                $contact_groups_to_be_merged = array();
                $delete_from_contacts_where_statement = '';
                $delete_from_contacts_values = array();
                $delete_from_contacts_contact_groups_xref_where_statement = '';
                $delete_from_contacts_contact_groups_xref_values = array();
                // loop through orphans to merge to get the remaining orphan's new opt in status,
                // get data to be merged into the remaining orphan's blank field from the current orphan,
                // get an array of all contact groups to be merged into the remaining orphan,
                // build sql where statements to be used to clean up the contacts and contacts contact groups xref tables,
                // and increment the merged contacts counter
                foreach ($orphans_to_merge as $orphan_to_merge) {
                    // get the remaining orphans new opt in status
                    if (($remaining_orphan['opt_in'] != 0) && ($orphan_to_merge['opt_in'] == 0)) {
                        $remaining_orphan['opt_in'] = 0;
                    }
                    // loop through the remaining orphan's fields to update the blank fields with data from the current orphan
                    foreach ($remaining_orphans_fields_to_update as $key => $remaining_orphans_field_to_update) {
                        // if this is the expiration date or if it is the warning expiration date and if it is set to the default, and if the orphan that will be merged has a value, then use that value
                        if ((($key == 'expiration_date') || ($key == 'warning_expiration_date')) && ($remaining_orphans_field_to_update == '0000-00-00') && ($orphan_to_merge[$key] != '0000-00-00')) {
                            $remaining_orphans_fields_to_update[$key] = $orphan_to_merge[$key];
                            // else if this is the affiliate approved or affiliate commission rate and if it is set to the default, and if the orphan that will be merged has a value, then use that value
                        } elseif ((($key == 'affiliate_approved') || ($key == 'affiliate_commission_rate')) && ($remaining_orphans_field_to_update == '0') && ($orphan_to_merge[$key] != '0')) {
                            $remaining_orphans_fields_to_update[$key] = $orphan_to_merge[$key];
                            // else if the remaining field to update is blank, and if the orphan that will be merged has a value, then use that value
                        } elseif (($remaining_orphans_field_to_update == '') && ($orphan_to_merge[$key] != '')) {
                            $remaining_orphans_fields_to_update[$key] = $orphan_to_merge[$key];
                        }
                    }
                    // loop through contact groups to build a list of contact groups to merge into the remaining orphan contact
                    foreach ($contact_groups as $contact_group) {
                        // if the conact groups contact id is the same as this orphan's id, or if it is the same as the remaining orphan's id, then add the contact groups to the contact groups to be merged array
                        if (($contact_group['contact_id'] == $orphan_to_merge['id']) || ($contact_group['contact_id'] == $remaining_orphan['id'])) {
                            $contact_groups_to_be_merged[] = $contact_group;
                        }
                    }
                    // build sql where statements to be used to clean up the contact table
                    if ($delete_from_contacts_where_statement != '') {
                        $delete_from_contacts_where_statement .= ' OR ';
                    }
                    $delete_from_contacts_where_statement .= "(id = ?)";
                    $delete_from_contacts_values[] = (string) $orphan_to_merge['id'];
                    // build sql where statements to be used to clean up the contact groups xref table
                    if ($delete_from_contacts_contact_groups_xref_where_statement != '') {
                        $delete_from_contacts_contact_groups_xref_where_statement .= ' OR ';
                    }
                    $delete_from_contacts_contact_groups_xref_where_statement .= "(contact_id = ?)";
                    $delete_from_contacts_contact_groups_xref_values[] = (string) $orphan_to_merge['id'];
                    // increment the merged contacts counter, this number will be used when outputting a notice to the user
                    $merged_contacts_counter++;
                }
                $sql_columns_to_update = '';
                $sql_columns_values = array();
                // build an sql update statement that updates the remaining contact's blank fields with data
                foreach ($remaining_orphans_fields_to_update as $key => $remaining_orphans_field_to_update) {
                    if ($remaining_orphans_field_to_update != '') {
                        $sql_columns_to_update .= $key . " = ?, ";
                        $sql_columns_values[] = (string) $remaining_orphans_field_to_update;
                    }
                }
                // re-point the merged contacts' records before the contacts are deleted
                pg_contact_holders_move(array_column($orphans_to_merge, 'id'), $remaining_orphan['id']);
                // update remaining orphan's contact record with data from orphans, new last mod date and opt status
                $query = "UPDATE contacts

                    SET

                       $sql_columns_to_update

                       opt_in = ?,

                       timestamp = ?

                    WHERE id = ?";
                $result = db_q($query, array_merge($sql_columns_values, array((string) $remaining_orphan['opt_in'], (string) $remaining_orphan['timestamp'], (string) $remaining_orphan['id']))) or output_error(lang('Query failed.'));
                $where = '';
                // if there are orphans to delete, then prepare the where statement and then delete them
                if ($delete_from_contacts_where_statement != '') {
                    // If where is blank
                    if ($where == '') {
                        $where .= ' WHERE ';
                        // else where is not blank, so add and
                    } else {
                        $where .= ' AND ';
                    }
                    $where .= "(" . $delete_from_contacts_where_statement . ")";
                    $query = "DELETE FROM contacts $where";
                    $result = db_q($query, $delete_from_contacts_values) or output_error(lang('Query failed.'));
                }
                $where = '';
                // if there are contact groups to delete, then prepare the where statement and then delete the contact group and their opt-in status
                if ($delete_from_contacts_contact_groups_xref_where_statement != '') {
                    // If where is blank
                    if ($where == '') {
                        $where .= ' WHERE ';
                        // else where is not blank, so add and
                    } else {
                        $where .= ' AND ';
                    }
                    $where .= "(" . $delete_from_contacts_contact_groups_xref_where_statement . ")";
                    $query = "DELETE FROM contacts_contact_groups_xref $where";
                    $result = db_q($query, $delete_from_contacts_contact_groups_xref_values) or output_error(lang('Query failed.'));
                    $query = "DELETE FROM opt_in $where";
                    $result = db_q($query, $delete_from_contacts_contact_groups_xref_values) or output_error(lang('Query failed.'));
                }
                $merged_contact_group_ids = array();
                // update contact groups xref and opt in table to add references for the remaining orphan
                foreach ($contact_groups_to_be_merged as $contact_group_to_be_merged) {
                    // if this contact group has not been merged yet, then process the merge
                    if (in_array($contact_group_to_be_merged['contact_group_id'], $merged_contact_group_ids) == false) {
                        // add this contact group to the meged contact groups array so that it is not processed again
                        $merged_contact_group_ids[] = $contact_group_to_be_merged['contact_group_id'];
                        // delete the any records from the contacts contact groups xref table for this contact group and orphan
                        $query = "DELETE FROM contacts_contact_groups_xref WHERE (contact_id = ?) AND (contact_group_id = ?)";
                        $result = db_q($query, array((string) $remaining_orphan['id'], (string) $contact_group_to_be_merged['contact_group_id'])) or output_error(lang('Query failed.'));
                        // insert a new xref record
                        $query = "INSERT INTO contacts_contact_groups_xref (

                                contact_id,

                                contact_group_id)

                            VALUES (

                                ?,

                                ?

                                )";
                        $result = db_q($query, array((string) $remaining_orphan['id'], (string) $contact_group_to_be_merged['contact_group_id'])) or output_error(lang('Query failed.'));
                        $contact_groups_to_be_merged_opt_in = 1;
                        // loop through the contact groups to be merged to get the new opt in value,
                        // and add the contact groups to the merged contact groups ids array so that they are not processed again
                        foreach ($contact_groups_to_be_merged as $contact_group) {
                            // if this contact group id is the same as the contact group id that we are merging in, then continue
                            if ($contact_group['contact_group_id'] == $contact_group_to_be_merged['contact_group_id']) {
                                // if the contact group's opt in is set to opt out, then use that value when merging the contact group
                                if ($contact_group['opt_in'] == 0) {
                                    $contact_groups_to_be_merged_opt_in = 0;
                                }
                                // add this contact group to the meged contact groups array so that it is not processed again
                                $merged_contact_group_ids[] = $contact_group['contact_group_id'];
                            }
                        }
                        // if contact group has email subscription turned on, then get the opt in value and update database
                        if ($contact_group_to_be_merged['email_subscription'] == 1) {
                            // if the contact groups to be merged opt in is set to opt out, then opt out the contact group we are merging
                            if ($contact_groups_to_be_merged_opt_in == 0) {
                                $contact_group_to_be_merged['opt_in'] = 0;
                                // else default to opt in
                            } else {
                                $contact_group_to_be_merged['opt_in'] = 1;
                            }
                            // delete the any records from the opt in table for this contact group and child
                            $query = "DELETE FROM opt_in WHERE (contact_id = ?) AND (contact_group_id = ?)";
                            $result = db_q($query, array((string) $remaining_orphan['id'], (string) $contact_group_to_be_merged['contact_group_id'])) or output_error(lang('Query failed.'));
                            // insert a new opt in record
                            $query = "INSERT INTO opt_in (

                                    contact_id,

                                    contact_group_id,

                                    opt_in)

                                VALUES (

                                    ?,

                                    ?,

                                    ?)";
                            $result = db_q($query, array((string) $remaining_orphan['id'], (string) $contact_group_to_be_merged['contact_group_id'], (string) $contact_group_to_be_merged['opt_in'])) or output_error(lang('Query failed.'));
                        }
                    }
                }
            }
        }
    }
    return $merged_contacts_counter;
}

// ── A face for a person without a picture ───────────────────────────────
//
// The letters of the name instead of the grey silhouette everybody used to
// share: the first letters of the contact's first and last name, drawn in a
// circle, or - for a login that has no contact yet - the first letters of
// the username in a grey rounded square, so a missing name shows at a
// glance. The picture is an SVG in a data: address, so every <img> that
// showed the silhouette takes it as it is, with its own size and shape.

/**
 * Letters upper-cased for the panel's language: in Turkish "i" is "İ" and
 * "ı" is "I".
 *
 * @param string $text
 * @return string
 */
function pg_initials_upper($text)
{
    $text = (string) $text;

    if (strtolower(substr((string) lang(array('info' => '')), 0, 2)) === 'tr') {
        $text = str_replace(array('i', 'ı'), array('İ', 'I'), $text);
    }

    return mb_strtoupper($text, 'UTF-8');
}

/**
 * The letters a person is shown by.
 *
 * @param string $first_name
 * @param string $last_name
 * @param string $username used when there is no name
 * @return string one or two letters, "?" when there is nothing
 */
function pg_initials($first_name, $last_name, $username = '')
{
    $first = trim((string) $first_name);
    $last = trim((string) $last_name);

    if (($first !== '') || ($last !== '')) {
        if ($last === '') {
            $words = preg_split('/\s+/u', $first, -1, PREG_SPLIT_NO_EMPTY);
            $letters = mb_substr($words[0], 0, 1) . ((count($words) > 1) ? mb_substr($words[count($words) - 1], 0, 1) : '');
        } elseif ($first === '') {
            $letters = mb_substr($last, 0, 1);
        } else {
            $letters = mb_substr($first, 0, 1) . mb_substr($last, 0, 1);
        }
    } else {
        $parts = preg_split('/[\s._@+-]+/u', trim((string) $username), -1, PREG_SPLIT_NO_EMPTY);
        $letters = (count($parts) > 1) ? mb_substr($parts[0], 0, 1) . mb_substr($parts[1], 0, 1) : mb_substr((string) ($parts[0] ?? ''), 0, 2);
    }

    $letters = pg_initials_upper($letters);

    return ($letters !== '') ? $letters : '?';
}

/**
 * The picture of letters, as a data: address an <img> can show.
 *
 * @param string $initials
 * @param string $kind contact (a named person: a coloured circle) | user (a
 *                     login without a name: a grey rounded square)
 * @param string $seed what picks the colour, so a person keeps theirs
 * @return string
 */
function pg_initials_avatar_url($initials, $kind = 'contact', $seed = '')
{
    static $cache = array();

    $key = $kind . '|' . $initials . '|' . $seed;

    if (isset($cache[$key])) {
        return $cache[$key];
    }

    $tones = array('#4f6bed', '#0f8a7e', '#c2410c', '#7c3aed', '#be185d', '#0369a1', '#15803d', '#a16207');
    $letters = htmlspecialchars((string) $initials, ENT_QUOTES | ENT_XML1, 'UTF-8');
    $size = (mb_strlen((string) $initials) > 1) ? 25 : 30;

    if ($kind === 'user') {
        $shape = "<rect x='1' y='1' width='62' height='62' rx='16' fill='#e9ecef' stroke='#adb5bd' stroke-width='2'/>";
        $ink = '#495057';
    } else {
        $tone = $tones[pg_avatar_tone($initials, $seed)];
        $shape = "<circle cx='32' cy='32' r='32' fill='" . $tone . "'/>";
        $ink = '#ffffff';
    }

    // A size of its own, so an <img> no stylesheet sizes stays small.
    $svg = "<svg xmlns='http://www.w3.org/2000/svg' width='64' height='64' viewBox='0 0 64 64'>" . $shape
        . "<text x='32' y='32' dy='.35em' text-anchor='middle' font-family='system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif' font-size='" . $size . "' font-weight='600' fill='" . $ink . "'>" . $letters . "</text></svg>";

    return $cache[$key] = 'data:image/svg+xml,' . rawurlencode($svg);
}

/**
 * The colour a person's letters are drawn in, as a place 0-7 in the list of
 * eight tones; the seed keeps a person's colour whatever their name becomes.
 * The picture of letters (pg_initials_avatar_url()) and the panel's Avatar
 * (pg_avatar_markup()) take the same place, so a person has one colour in
 * the panel, in an e-mail and on a print.
 *
 * @param string     $initials
 * @param string|int $seed
 * @return int 0-7
 */
function pg_avatar_tone($initials, $seed = '')
{
    $seed = (string) $seed;

    return abs(crc32(($seed !== '') ? $seed : (string) $initials)) % 8;
}

/**
 * A person's letters and tone, the two fields every person brief carries
 * beside its picture address, so the panel can draw them as an Avatar.
 *
 * @param string     $initials
 * @param string|int $seed
 * @return array initials, tone
 */
function pg_avatar_letters($initials, $seed = '')
{
    $initials = (string) $initials;

    return array('initials' => ($initials !== '') ? $initials : '?', 'tone' => pg_avatar_tone($initials, $seed));
}

/**
 * The Bootstrap 6 theme class of a tone place (pg_avatar_tone()).
 *
 * @param int $tone
 * @return string
 */
function pg_avatar_theme_class($tone)
{
    $themes = array('theme-primary', 'theme-info', 'theme-warning', 'theme-accent', 'theme-danger', 'theme-secondary', 'theme-success', 'theme-inverse');

    return $themes[((int) $tone % 8 + 8) % 8];
}

/**
 * A person in the panel as a Bootstrap 6 Avatar: their picture when there is
 * one, else their letters on their tone; a login without a name is a grey
 * rounded square, so the missing name shows. No <img> is printed without an
 * address. Panel markup only: sites, e-mails and prints keep the picture of
 * letters (pg_initials_avatar_url()).
 *
 * @param array $person initials, tone, avatar (a picture address), avatar_kind
 *                      (photo | contact | user), presence (online | away |
 *                      offline, empty for no dot), name (the label)
 * @param string $size  xs | sm | lg | xl, empty for the default 2.5rem
 * @param string $class more classes
 * @return string
 */
function pg_avatar_markup($person, $size = '', $class = '')
{
    $kind = (string) ($person['avatar_kind'] ?? '');
    $picture = (string) ($person['avatar'] ?? '');
    $initials = (string) ($person['initials'] ?? '');

    if ($initials === '') {
        $initials = '?';
    }

    // A picture of letters is drawn as letters; only a photo stays a picture.
    $photo = ($picture !== '') && (strncmp($picture, 'data:image/svg+xml', 18) !== 0) && ($kind !== 'contact') && ($kind !== 'user');

    $classes = array('avatar');

    if (in_array($size, array('xs', 'sm', 'lg', 'xl'), true)) {
        $classes[] = 'avatar-' . $size;
    }

    if ($photo) {
        $inner = '<img class="avatar-img" src="' . h($picture) . '" alt="" loading="lazy">';
    } elseif ($kind === 'user') {
        $classes[] = 'avatar-subtle';
        $classes[] = 'theme-secondary';
        $classes[] = 'pg-avatar-user';
        $inner = h($initials);
    } else {
        $classes[] = pg_avatar_theme_class(isset($person['tone']) ? (int) $person['tone'] : pg_avatar_tone($initials));
        $inner = h($initials);
    }

    if ($class !== '') {
        $classes[] = $class;
    }

    $presence = (string) ($person['presence'] ?? '');

    if (in_array($presence, array('online', 'away', 'offline'), true)) {
        $inner .= '<span class="avatar-status status-' . $presence . '"></span>';
    }

    $name = (string) ($person['name'] ?? '');

    return '<span class="' . h(implode(' ', $classes)) . '"' . (($name !== '') ? ' role="img" aria-label="' . h($name) . '"' : ' aria-hidden="true"') . '>' . $inner . '</span>';
}

/**
 * The picture of letters for a person, from their name: a coloured circle
 * when they have one, a grey square from the username when they do not.
 *
 * @param string     $first_name
 * @param string     $last_name
 * @param string     $username
 * @param string|int $seed what keeps a person's colour
 * @return string data: address
 */
function pg_avatar_for($first_name, $last_name, $username = '', $seed = '')
{
    $named = (trim((string) $first_name) !== '') || (trim((string) $last_name) !== '');

    return pg_initials_avatar_url(pg_initials($first_name, $last_name, $username), $named ? 'contact' : 'user', (string) $seed);
}

/**
 * pg_avatar_for() as the panel's Avatar: a person without a picture, drawn
 * from their name as their letters on their tone, a grey rounded square
 * from the username when they have no name.
 *
 * @param string     $first_name
 * @param string     $last_name
 * @param string     $username
 * @param string|int $seed what keeps a person's colour
 * @param string     $class more classes (a size of its own)
 * @return string
 */
function pg_avatar_markup_for($first_name, $last_name, $username = '', $seed = '', $class = '')
{
    $named = (trim((string) $first_name) !== '') || (trim((string) $last_name) !== '');

    return pg_avatar_markup(array('avatar_kind' => $named ? 'contact' : 'user') + pg_avatar_letters(pg_initials($first_name, $last_name, $username), (string) $seed), '', $class);
}

/**
 * The account menu's quick form: what is missing of the signed-in person's
 * address-book contact - their first and last name (and, if given, mobile
 * phone), their picture - written into it. The contact is made, and tied to
 * the login, when there is none yet, the way the My Account profile makes it.
 * A name already there is kept when the form does not send one.
 *
 * @param int    $user_id
 * @param string $first_name
 * @param string $last_name
 * @param string $mobile_phone empty leaves the one stored
 * @param string $photo        a picture as a data: address, empty for none
 * @return array ok, error
 */
function pg_contact_quick_save($user_id, $first_name, $last_name, $mobile_phone = '', $photo = '')
{
    $user_id = (int) $user_id;
    $first = trim(mb_substr(preg_replace('/\s+/u', ' ', (string) $first_name), 0, 50));
    $last = trim(mb_substr(preg_replace('/\s+/u', ' ', (string) $last_name), 0, 50));
    $phone = trim(mb_substr(preg_replace('/\s+/u', ' ', (string) $mobile_phone), 0, 50));
    $photo = (string) $photo;

    if ($user_id <= 0) {
        return array('ok' => false, 'error' => lang('Invalid request.'));
    }

    $user = db_item("SELECT user_id, user_username, user_email, user_contact FROM user WHERE user_id = '" . $user_id . "'");

    if (!is_array($user)) {
        return array('ok' => false, 'error' => lang('Invalid request.'));
    }

    $contact_id = (int) $user['user_contact'];
    $contact = ($contact_id > 0) ? db_item("SELECT id, first_name, last_name FROM contacts WHERE id = '" . $contact_id . "'") : null;

    if (!is_array($contact)) {
        $contact_id = 0;
    }

    $named = is_array($contact) && ((trim((string) $contact['first_name']) !== '') || (trim((string) $contact['last_name']) !== ''));
    $writes_name = ($first !== '') || ($last !== '');

    // A name is asked for until there is one; after that it is only written
    // when the form sends it.
    if ((!$named || $writes_name) && (($first === '') || ($last === ''))) {
        return array('ok' => false, 'error' => lang('Please enter your first and last name.'));
    }

    if (!$writes_name && ($photo === '') && ($phone === '')) {
        return array('ok' => false, 'error' => lang('Choose a picture first.'));
    }

    // The picture first: a file that is not a picture stops the save before
    // anything is written.
    $file_id = 0;

    if ($photo !== '') {
        $stored = pg_contact_photo_store($user_id, $photo);

        if (!$stored['ok']) {
            return array('ok' => false, 'error' => $stored['error']);
        }

        $file_id = (int) $stored['file_id'];
    }

    if ($contact_id > 0) {
        $set = array();

        if ($writes_name) {
            $set[] = "first_name = '" . e($first) . "'";
            $set[] = "last_name = '" . e($last) . "'";
        }

        if ($phone !== '') {
            $set[] = "mobile_phone = '" . e($phone) . "'";
        }

        if ($file_id > 0) {
            $set[] = "file_id = '" . $file_id . "'";
            $set[] = "image = ''";
        }

        db("UPDATE contacts SET " . implode(', ', $set) . ", timestamp = UNIX_TIMESTAMP() WHERE id = '" . $contact_id . "'");
    } else {
        db("INSERT INTO contacts (first_name, last_name, mobile_phone, email_address, file_id, user, timestamp)
            VALUES ('" . e($first) . "', '" . e($last) . "', '" . e($phone) . "', '" . e((string) $user['user_email']) . "', '" . $file_id . "', '" . $user_id . "', UNIX_TIMESTAMP())");

        $contact_id = (int) mysqli_insert_id(db::$con);

        if ($contact_id <= 0) {
            return array('ok' => false, 'error' => lang('Your name could not be saved.'));
        }

        // The group new accounts are put in, as the profile screen does.
        if (defined('REGISTRATION_CONTACT_GROUP_ID') && ((int) REGISTRATION_CONTACT_GROUP_ID > 0)
            && ((int) db_value("SELECT COUNT(*) FROM contact_groups WHERE id = '" . (int) REGISTRATION_CONTACT_GROUP_ID . "'") > 0)) {
            db("INSERT INTO contacts_contact_groups_xref (contact_id, contact_group_id) VALUES ('" . $contact_id . "', '" . (int) REGISTRATION_CONTACT_GROUP_ID . "')");
        }

        db("UPDATE user SET user_contact = '" . $contact_id . "' WHERE user_id = '" . $user_id . "'");
    }

    if (function_exists('log_activity')) {
        log_activity(lang(array('string' => 'user ({var:1}) completed their address book details', 'vars' => (string) $user['user_username'])), (string) $user['user_username']);
    }

    return array('ok' => true, 'error' => '');
}

/**
 * The folder profile pictures from the account menu are kept in: "Profile
 * pictures" under the top folder of the file manager, made on the first one.
 * Public, as a face shown next to a name is; the file names cannot be
 * guessed.
 *
 * @param int $user_id who is making it, when it is made
 * @return int 0 when the file manager has no top folder
 */
function pg_profile_photo_folder_id($user_id)
{
    $top = function_exists('getPublicRootFolderId') ? (int) getPublicRootFolderId() : 0;

    if ($top <= 0) {
        $top = (int) db_value("SELECT folder_id FROM folder WHERE folder_parent = '0' ORDER BY folder_id LIMIT 1");
    }

    if ($top <= 0) {
        return 0;
    }

    $names = array_unique(array(lang('Profile pictures'), 'Profile pictures'));
    $quoted = array();

    foreach ($names as $name) {
        $quoted[] = "'" . e($name) . "'";
    }

    $existing = (int) db_value("SELECT folder_id FROM folder WHERE folder_parent = '" . $top . "' AND folder_name IN (" . implode(', ', $quoted) . ") ORDER BY folder_id LIMIT 1");

    if ($existing > 0) {
        return $existing;
    }

    $level = (int) db_value("SELECT folder_level FROM folder WHERE folder_id = '" . $top . "'");

    db("INSERT INTO folder (folder_name, folder_parent, folder_level, folder_order, folder_access_control_type, folder_archived, folder_timestamp, folder_user)
        VALUES ('" . e(lang('Profile pictures')) . "', '" . $top . "', '" . ($level + 1) . "', '0', 'public', '0', UNIX_TIMESTAMP(), '" . (int) $user_id . "')");

    return (int) mysqli_insert_id(db::$con);
}

/**
 * A profile picture sent by the account menu, kept as a file: made square,
 * at most 256 pixels and saved again as JPEG where the server can draw
 * (which also drops what the camera wrote into it), otherwise kept as it
 * came if it is a JPEG, PNG or WebP picture of at most 2 MB.
 *
 * @param int    $user_id
 * @param string $data a data: address
 * @return array ok, error, file_id
 */
function pg_contact_photo_store($user_id, $data)
{
    $fail = function ($message) {
        return array('ok' => false, 'error' => $message, 'file_id' => 0);
    };

    if (!preg_match('#^data:image/(jpeg|png|webp);base64,#i', $data, $match)) {
        return $fail(lang('The picture has to be a JPEG, PNG or WebP image.'));
    }

    $binary = base64_decode(str_replace(' ', '+', substr($data, strpos($data, ',') + 1)), true);

    if (($binary === false) || ($binary === '') || (strlen($binary) > 2097152)) {
        return $fail(lang('The picture could not be read, or it is larger than 2 MB.'));
    }

    $info = @getimagesizefromstring($binary);

    if (!is_array($info) || !in_array((int) $info[2], array(IMAGETYPE_JPEG, IMAGETYPE_PNG, defined('IMAGETYPE_WEBP') ? IMAGETYPE_WEBP : -1), true)) {
        return $fail(lang('The picture has to be a JPEG, PNG or WebP image.'));
    }

    $extension = image_type_to_extension((int) $info[2], false);
    $extension = ($extension === 'jpeg') ? 'jpg' : $extension;

    // Drawn again where the server can: square, small, and nothing but the
    // pixels.
    if (function_exists('imagecreatefromstring') && function_exists('imagejpeg')) {
        $source = pg_image_from_string($binary);

        if ($source !== false) {
            $width = imagesx($source);
            $height = imagesy($source);
            $side = min($width, $height);
            $size = min(256, $side);
            $canvas = imagecreatetruecolor($size, $size);
            $white = imagecolorallocate($canvas, 255, 255, 255);
            imagefill($canvas, 0, 0, $white);
            imagecopyresampled($canvas, $source, 0, 0, (int) (($width - $side) / 2), (int) (($height - $side) / 2), $size, $size, $side, $side);

            ob_start();
            imagejpeg($canvas, null, 88);
            $redrawn = (string) ob_get_clean();

            pg_image_destroy($canvas);
            pg_image_destroy($source);

            if ($redrawn !== '') {
                $binary = $redrawn;
                $extension = 'jpg';
            }
        }
    }

    $folder_id = pg_profile_photo_folder_id($user_id);

    if ($folder_id <= 0) {
        return $fail(lang('The picture could not be saved.'));
    }

    $name = 'profile-' . (int) $user_id . '-' . bin2hex(random_bytes(6)) . '.' . $extension;

    if (function_exists('get_unique_name')) {
        $name = get_unique_name(array('name' => $name, 'type' => 'file'));
    }

    $path = FILE_DIRECTORY_PATH . '/' . $name;

    if (file_exists($path) || (@file_put_contents($path, $binary) === false)) {
        return $fail(lang('The picture could not be saved.'));
    }

    $size = @getimagesize($path);

    db("INSERT INTO files (name, folder, type, size, user, description, design, optimized, image_width, image_height, timestamp)
        VALUES ('" . e($name) . "', '" . (int) $folder_id . "', '" . e($extension) . "', '" . (int) filesize($path) . "', '" . (int) $user_id . "', '',
            '0', '0', " . (is_array($size) ? (int) $size[0] : 'NULL') . ", " . (is_array($size) ? (int) $size[1] : 'NULL') . ", UNIX_TIMESTAMP())");

    $file_id = (int) mysqli_insert_id(db::$con);

    if ($file_id <= 0) {
        @unlink($path);

        return $fail(lang('The picture could not be saved.'));
    }

    return array('ok' => true, 'error' => '', 'file_id' => $file_id);
}

/**
 * The tax fields a checkout writes onto a contact card that is not updated
 * from the billing block: a signed-in customer who unticked "Update my
 * contact info with this billing info", or a checkout layout without that
 * box. Only a card with no tax number takes the one typed at checkout (and
 * the tax office typed beside it); a card that already names a number keeps
 * it. The order keeps no tax number of its own, so otherwise the number is
 * lost and the account its invoice goes to opens without one.
 *
 * Spaces, dots and dashes typed as separators go, as on the express order
 * screen; both values are cut to their contacts columns.
 *
 * @param array  $card    The contact row (tax_number, tax_office)
 * @param string $number  Typed at checkout
 * @param string $office  Typed at checkout
 * @return array  Column => value to write; empty when nothing is written
 */
function pg_checkout_contact_tax_fill(array $card, string $number, string $office): array
{
    $number = mb_substr((string) preg_replace('/[\s.\-]+/u', '', $number), 0, 20);
    $office = mb_substr(trim($office), 0, 100);

    if (($number === '') || (trim((string) ($card['tax_number'] ?? '')) !== '')) {
        return array();
    }

    $fill = array('tax_number' => $number);

    if ($office !== '') {
        $fill['tax_office'] = $office;
    }

    return $fill;
}

/**
 * Writes what pg_checkout_contact_tax_fill() decides onto the checkout's
 * contact card.
 *
 * @param int    $contact_id
 * @param string $number   Typed at checkout
 * @param string $office   Typed at checkout
 * @param int    $user_id  The signed-in customer, recorded as the card's last editor
 * @return bool  True when the card took the number
 */
function pg_checkout_fill_contact_tax(int $contact_id, string $number, string $office, int $user_id): bool
{
    if ($contact_id <= 0) {
        return false;
    }

    $card = db_item("SELECT tax_number, tax_office FROM contacts WHERE id = '" . $contact_id . "'");

    if (!is_array($card)) {
        return false;
    }

    $fill = pg_checkout_contact_tax_fill($card, $number, $office);

    if ($fill === array()) {
        return false;
    }

    $set = array();

    foreach ($fill as $column => $value) {
        $set[] = $column . " = '" . e($value) . "'";
    }

    // The empty-number condition again: a card that took a number since it
    // was read keeps it.
    db("UPDATE contacts
        SET " . implode(', ', $set) . ", user = '" . $user_id . "', timestamp = UNIX_TIMESTAMP()
        WHERE id = '" . $contact_id . "' AND tax_number = ''");

    return mysqli_affected_rows(db::$con) > 0;
}

/**
 * The name a contact is shown under on public pages: the first and last
 * name, else the nickname, '' when the contact has neither. Pure; NULL (a
 * LEFT JOIN without a contact) and any value that is not a scalar read as ''.
 *
 * The login username is never used: it is half of a credential, and a
 * member's defaults to the local part of their e-mail address.
 *
 * The SQL twin is pg_sw_submitter_name_sql() (includes/fn/widgets.php).
 * Callers: pg_submitted_form_author_name() (the BlogPosting author) and the
 * name a member's comment gets, pg_comment_member_name()
 * (includes/fn/comments.php).
 *
 * @param mixed $first_name
 * @param mixed $last_name
 * @param mixed $nickname
 * @return string
 */
function pg_contact_display_name($first_name, $last_name, $nickname = '')
{
    $first = is_scalar($first_name) ? trim((string) $first_name) : '';
    $last = is_scalar($last_name) ? trim((string) $last_name) : '';
    $name = trim($first . ' ' . $last);

    if ($name === '') {
        $name = is_scalar($nickname) ? trim((string) $nickname) : '';
    }

    return $name;
}

/**
 * The contacts a person with the user role (3) may open, as SQL: those filed
 * in a contact group given to them (users_contact_groups_xref), the rule of
 * validate_contact_access() written as one condition. A contact in no group,
 * or only in groups given to others, is outside. Shared by the external API
 * (api_customer_owner_filter()) and the Workspace's record changes
 * (ws_change_allowed(), ws_bulk_match()); from the manager role up every
 * contact is open and the callers add no condition.
 *
 * @param int    $user_id user.user_id of the person
 * @param string $column  the contact id column of the caller's query
 * @return string
 */
function pg_contact_owner_sql($user_id, $column)
{
    return $column . " IN (SELECT contacts_contact_groups_xref.contact_id FROM contacts_contact_groups_xref"
        . " INNER JOIN users_contact_groups_xref ON users_contact_groups_xref.contact_group_id = contacts_contact_groups_xref.contact_group_id"
        . " WHERE users_contact_groups_xref.user_id = '" . (int) $user_id . "')";
}
