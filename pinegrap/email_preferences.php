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

// if user has not yet completed form
if (!$_POST) {
    print get_email_preferences_screen();

// else user has completed form, so process form
} else {
    
    validate_token_field();
    
    include_once('liveform.class.php');
    $liveform = new liveform('email_preferences');

    $liveform->add_fields_to_session();

    $liveform->validate_required_field('email_address', lang('Email is required.'));
    
    // get email preferences page, if one exists
    $query = "SELECT page_id FROM page WHERE page_type = 'email preferences'";
    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');

    // if there is a email preferences page, prepare path with page name
    if (mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_assoc($result);
        $email_preferences_path = PATH . encode_url_path(get_page_name($row['page_id']));
        
    // else there is not a email preferences, so prepare default path
    } else {
        $email_preferences_path = PATH . SOFTWARE_DIRECTORY . '/email_preferences.php';
    }
    
    // A designed page (the email_preferences widget) names itself in
    // return_to; every answer goes back there. The id and signature are
    // added below as for the legacy page, so they are left off its address.
    // pg_controls lists what the widget drew: the opt-in and the lists it
    // did not draw are left as they are.
    $pg_return_to = pg_sw_return_to();
    $pg_controls  = pg_sw_posted_controls();
    if ($pg_return_to !== '') {
        $email_preferences_path = strtok($pg_return_to, '?');
    }

    // if an id was submitted, set id (and its signature) for query string in case we need to forward user back to e-mail preferences screen
    if (!empty($_POST['id'])) {
        $url_id = '?id=' . urlencode($_POST['id']) . '&sig=' . urlencode((string) ($_POST['sig'] ?? ''));
    } else {
        $url_id = '';
    }
    
    // if there are validation errors, then send user back to e-mail preferences screen
    if ($liveform->check_form_errors() == true) {
        header('Location: ' . URL_SCHEME . $_SERVER['HTTP_HOST'] . $email_preferences_path . $url_id);
        exit();
    }

    // validate e-mail address
    if (validate_email_address($liveform->get_field_value('email_address')) == false) {
        $liveform->mark_error('email_address', lang('Please enter a valid email address.'));
        $liveform->assign_field_value('email_address', '');
        
        // send user back to e-mail preferences screen
        header('Location: ' . URL_SCHEME . $_SERVER['HTTP_HOST'] . $email_preferences_path . $url_id);
        exit();
    }
    
    $user_id = 0;
    
    // if user is logged in
    if (!empty($_SESSION['sessionusername'])) {
        // check to see if e-mail address is already in use
        $query =
            "SELECT user_id
            FROM user
            WHERE
                (
                    (user_username = '" . escape($liveform->get_field_value('email_address')) . "')
                    OR (user_email = '" . escape($liveform->get_field_value('email_address')) . "')
                )
                AND (user_username != '" . escape($_SESSION['sessionusername']) . "')";
        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
        
        // if e-mail address is already in use by another user
        if (mysqli_num_rows($result) > 0) {
            $liveform->mark_error('email_address', lang('That email address is already in use. Please enter a different email address.'));
            $liveform->assign_field_value('email_address', '');
            
            // send user back to e-mail preferences screen
            header('Location: ' . URL_SCHEME . $_SERVER['HTTP_HOST'] . $email_preferences_path. $url_id);
            exit();
        }
        
        // get user information
        $query =
            "SELECT
                user_id,
                user_contact,
                user_email
            FROM user
            WHERE user_username = '" . escape($_SESSION['sessionusername']) . "'";
        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
        $row = mysqli_fetch_assoc($result);
        
        $user_id = $row['user_id'];
        $contact_id = $row['user_contact'];
        $current_email_address = $row['user_email'];

        // look for contact
        $query = "SELECT id FROM contacts WHERE id = '" . $contact_id . "'";
        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');

        // if a contact was not found, create contact (we will update later)
        if (mysqli_num_rows($result) == 0) {
            $query = "INSERT INTO contacts (
                        email_address,
                        user,
                        timestamp)
                     VALUES (
                        '" . escape($current_email_address) . "',
                        '$user_id',
                        UNIX_TIMESTAMP())";
            $result = mysqli_query(db::$con, $query) or output_error('Query failed.');

            // get contact id so we can connect user to contact
            $contact_id = mysqli_insert_id(db::$con);

            // A visitor who set their e-mail preferences without having a
            // contact record has one now.
            pg_announce_contact_created($contact_id);
            
            // check if registration contact group exists
            $query = "SELECT id FROM contact_groups WHERE id = '" . REGISTRATION_CONTACT_GROUP_ID  . "'";
            $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
            
            // if registration contact group exists, assign contact to contact group
            if (mysqli_num_rows($result) > 0) {
                $query =
                    "INSERT INTO contacts_contact_groups_xref (
                        contact_id,
                        contact_group_id)
                    VALUES (
                        '" . $contact_id . "',
                        '" . REGISTRATION_CONTACT_GROUP_ID . "')";
                $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
            }
        }
        
        // if e-mail address has changed and user's current e-mail address is the same as the user's current username,
        if (($liveform->get_field_value('email_address') != $current_email_address) && ($current_email_address == $_SESSION['sessionusername'])) {
            // prepare sql to update user's username
            $sql_update_username = "user_username = '" . escape($liveform->get_field_value('email_address')) . "', ";
        } else {
            $sql_update_username = '';
        }

        // update user
        $query = "UPDATE user SET user_email = '" . escape($liveform->get_field_value('email_address')) . "',$sql_update_username user_contact = '$contact_id' WHERE user_id = '" . $user_id . "'";
        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
        
        // if username was changed
        if ($sql_update_username) {
            // The remember-me token is keyed by user id, not username, so an
            // email or username change needs no cookie update at all.

            // update username in session
            $_SESSION['sessionusername'] = $liveform->get_field_value('email_address');
            
            $liveform->add_notice(lang(array('string' => 'Your username has been updated. Username: {var:1}', 'vars' => array($liveform->get_field_value('email_address')))));
        }
    
    // else user is not logged in
    } else {
        $current_email_address = str_rot13(base64_decode((string) ($_POST['id'] ?? '')));
        
        // Only a link this site signed for the address may change the contact behind it;
        // the id alone is an obfuscated e-mail address anyone could produce.
        if (pg_email_preferences_signature_valid($current_email_address, (string) ($_POST['sig'] ?? '')) == false) {
            output_error(lang('The email preferences link is not valid.'));
        }
        
        // get contact information
        $query =
            "SELECT id
            FROM contacts
            WHERE email_address = '" . escape($current_email_address) . "'";
        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
        
        // if a contact was not found for the e-mail address, then output error
        if (mysqli_num_rows($result) == 0) {
            output_error(lang('A contact for that email address could not be found.'));
            
        // else get contact id
        } else {
            $row = mysqli_fetch_assoc($result);
            $contact_id = $row['id'];
        }
        
        // if user has updated e-mail address, check that new e-mail address is not already in use by a contact
        if ($liveform->get_field_value('email_address') != $current_email_address) {
            // check to see if e-mail address is already in use by a contact
            $query =
                "SELECT id
                FROM contacts
                WHERE email_address = '" . escape($liveform->get_field_value('email_address')) . "'";
            $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
            
            // if e-mail address is already in use by a contact
            if (mysqli_num_rows($result) > 0) {
                $liveform->mark_error('email_address', lang('That email address is already in use. Please enter a different email address.'));
                $liveform->assign_field_value('email_address', '');
                
                // send user back to e-mail preferences screen
                header('Location: ' . URL_SCHEME . $_SERVER['HTTP_HOST'] . $email_preferences_path . $url_id);
                exit();
            }
            
            // set id and signature for query string with new e-mail address
            $url_id = '?' . pg_email_preferences_query($liveform->get_field_value('email_address'));
        }
    }
    
    // get all subscription contact groups, so that contacts can be opted-in or opted-out to contact groups
    $query =
        "SELECT
            id,
            email_subscription_type
        FROM contact_groups
        WHERE email_subscription = 1";
    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
    
    $contact_groups = array();
    
    while ($row = mysqli_fetch_assoc($result)) {
        $contact_groups[] = $row;
    }
    
    // loop through contact groups in order to determine which contact groups appeared on form
    foreach ($contact_groups as $key => $contact_group) {
        // assume that we should not include contact group, until we find out otherwise
        $include_contact_group = false;
        
        // if contact group's e-mail subscription type is open, then we should include contact group
        if ($contact_group['email_subscription_type'] == 'open') {
            $include_contact_group = true;
            
        // else contact group's e-mail subscription type is closed
        } else {
            // check if contact is in this contact group
            $query =
                "SELECT contact_id
                FROM contacts_contact_groups_xref
                WHERE
                    (contact_id = '" . $contact_id . "')
                    AND (contact_group_id = '" . $contact_group['id'] . "')";
            $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
            
            // if contact is in this contact group, then we should include contact group
            if (mysqli_num_rows($result) > 0) {
                $include_contact_group = true;
            }
        }
        
        // if contact group should not be included, remove contact group from array
        if ($include_contact_group == false) {
            unset($contact_groups[$key]);
        }
    }
    
    // get all contacts with e-mail address, so they can be updated
    $query = "SELECT id FROM contacts WHERE email_address = '" . escape($current_email_address) . "'";
    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
    
    $contacts = array();
    
    // assume that the main contact for this visitor is not in the contacts array until we find out otherwise
    $main_contact_in_array = false;
    
    while ($row = mysqli_fetch_assoc($result)) {
        $contacts[] = $row;
        
        // if this is the main contact for this visitor, then take note of that.
        if ($row['id'] == $contact_id) {
            $main_contact_in_array = true;
        }
    }
    
    // if the main contact for this visitor is not in the contacts array, then add it (this happens if a user's contact's e-mail address does not match the user's e-mail address)
    if ($main_contact_in_array == false) {
        $contacts[] = array('id' => $contact_id);
    }
    
    // The contact's link to its user account is written only from the signed-in
    // path; a visitor arriving from a campaign link has no user to record, and
    // writing 0 here would cut the account loose from its own contact.
    if ($user_id > 0) {
        $sql_update_user = "user = '" . (int) $user_id . "',";
    } else {
        $sql_update_user = '';
    }
    
    // loop through all contacts in order to update them
    foreach ($contacts as $contact) {
        // update contact
        $query =
            "UPDATE contacts
            SET
                email_address = '" . escape($liveform->get_field_value('email_address')) . "',
                " . (pg_sw_posted_control($pg_controls, 'opt_in') ? "opt_in = '" . escape($liveform->get_field_value('opt_in')) . "'," : '') . "
                $sql_update_user
                timestamp = UNIX_TIMESTAMP()
            WHERE id = '" . $contact['id'] . "'";
        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
        
        // loop through contact groups in order to opt-in or opt-out contact
        foreach ($contact_groups as $contact_group) {
            if (!pg_sw_posted_control($pg_controls, 'contact_group_' . $contact_group['id'])) {
                continue;
            }
            // if user opted-in to this group
            if ($liveform->get_field_value('contact_group_' . $contact_group['id'])) {
                // check to see if contact is already in contact group
                $query =
                    "SELECT contact_id
                    FROM contacts_contact_groups_xref
                    WHERE
                        (contact_id = '" . $contact['id'] . "')
                        AND (contact_group_id = '" . $contact_group['id'] . "')";
                $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
                
                // if contact is not already in contact group, then add contact to contact group
                if (mysqli_num_rows($result) == 0) {
                    $query =
                        "INSERT INTO contacts_contact_groups_xref (
                            contact_id,
                            contact_group_id)
                        VALUES (
                            '" . $contact['id'] . "',
                            '" . $contact_group['id'] . "')";
                    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
                }
            }
            
            // check to see if there is already an opt-in record
            $query =
                "SELECT contact_id
                FROM opt_in
                WHERE
                    (contact_id = '" . $contact['id'] . "')
                    AND (contact_group_id = '" . $contact_group['id'] . "')";
            $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
            
            // if there is not already an opt-in record, then create record
            if (mysqli_num_rows($result) == 0) {
                $query =
                    "INSERT INTO opt_in (
                        contact_id,
                        contact_group_id,
                        opt_in)
                    VALUES (
                        '" . $contact['id'] . "',
                        '" . $contact_group['id'] . "',
                        '" . escape($liveform->get_field_value('contact_group_' . $contact_group['id'])) . "')";
                $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
                
            // else an opt-in record already exists, so update record
            } else {
                $query =
                    "UPDATE opt_in
                    SET opt_in = '" . escape($liveform->get_field_value('contact_group_' . $contact_group['id'])) . "'
                    WHERE
                        (contact_id = '" . $contact['id'] . "')
                        AND (contact_group_id = '" . $contact_group['id'] . "')";
                $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
            }
        }
    }
    
    $liveform->add_notice(lang('Your email preferences have been updated.'));
    
    // send user back to e-mail preferences screen
    header('Location: ' . URL_SCHEME . $_SERVER['HTTP_HOST'] . $email_preferences_path . $url_id);

}