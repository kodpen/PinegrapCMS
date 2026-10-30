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

    // One-click unsubscribe (RFC 8058). A mail client that found the
    // List-Unsubscribe and List-Unsubscribe-Post headers on a commercial
    // campaign POSTs "List-Unsubscribe=One-Click" to the address in the
    // header, with no session, no page and no form token. The signature in
    // the address is what authorises the request - it can only have come from
    // a message this site sent to that address - so no CSRF token is asked
    // for; the RFC forbids requiring one. The answer is plain text, never a
    // redirect: the client does not follow it and nobody reads it.
    if (($_POST['List-Unsubscribe'] ?? '') === 'One-Click') {

        // Only a string can be an id or a signature; an array (id[]=) is refused
        // as a missing one is.
        $one_click_id = (isset($_GET['id']) && is_string($_GET['id'])) ? $_GET['id'] : '';
        $one_click_sig = (isset($_GET['sig']) && is_string($_GET['sig'])) ? $_GET['sig'] : '';

        $one_click_address = str_rot13((string) base64_decode($one_click_id));

        header('Content-Type: text/plain; charset=utf-8');

        if (
            ($one_click_address === '')
            || !pg_email_preferences_signature_valid($one_click_address, $one_click_sig)
        ) {
            http_response_code(403);
            print 'Invalid signature';
            exit;
        }

        // Every contact with the address, as the preferences form does.
        $audit_same_address = pg_audit_fetch_by('contacts', 'email_address', $one_click_address);
        db(
            "UPDATE contacts
            SET opt_in = '0', timestamp = UNIX_TIMESTAMP()
            WHERE email_address = '" . e($one_click_address) . "'");
        pg_audit_site_record_many('update', 'contacts', $audit_same_address);

        // The person behind the address: the account that uses it and that
        // account's contact, else the first contact with the address (as the
        // signed preferences link picks it, with no account). The refusal is
        // kept with them as well as with the address, so it is still found
        // after the person saves a new address. contacts.user is not read: a
        // form a signed-in visitor fills in for somebody else stores the
        // visitor there.
        $one_click_person = db_item(
            "SELECT user_id, user_contact AS contact_id
            FROM user
            WHERE user_email = '" . e($one_click_address) . "'
            ORDER BY user_id
            LIMIT 1");

        if (!$one_click_person || ((int) $one_click_person['contact_id'] <= 0)) {
            $one_click_person = array(
                'user_id' => $one_click_person ? (int) $one_click_person['user_id'] : 0,
                'contact_id' => (int) db_value(
                    "SELECT id
                    FROM contacts
                    WHERE email_address = '" . e($one_click_address) . "'
                    ORDER BY id
                    LIMIT 1"));
        }

        // The refusal goes to the consent log once: a client that repeats the
        // POST finds it already recorded for the person and adds nothing.
        pg_consent_log_answers(
            array(
                'email' => $one_click_address,
                'user_id' => (int) $one_click_person['user_id'],
                'contact_id' => (int) $one_click_person['contact_id']),
            array('email:marketing' => false),
            'unsubscribe');

        log_activity(lang(array('string' => '{var:1} unsubscribed with one click.', 'vars' => array($one_click_address))), 'UNKNOWN');

        http_response_code(200);
        print 'Unsubscribed';
        exit;
    }

    validate_token_field();
    
    include_once('liveform.class.php');
    $liveform = new liveform('email_preferences');

    $liveform->add_fields_to_session();

    // The address and the opt-in are single values, and so are the id and the
    // signature the form carries; the list check boxes are handled once the
    // lists are known, below.
    pg_liveform_single_values($liveform, array('email_address', 'opt_in', 'opt_in_sms', 'opt_in_sms_shown', 'id', 'sig'));

    // The id and signature of the link the visitor came from, for the way back
    // and for a visitor who is not signed in. Only a string can be either.
    $posted_id = (isset($_POST['id']) && is_string($_POST['id'])) ? $_POST['id'] : '';
    $posted_sig = (isset($_POST['sig']) && is_string($_POST['sig'])) ? $_POST['sig'] : '';

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
    if (!empty($posted_id)) {
        $url_id = '?id=' . urlencode($posted_id) . '&sig=' . urlencode($posted_sig);
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
    
    // Set when the signed-in path creates the visitor's contact below: the
    // screen was drawn without one, so it showed no closed list.
    $contact_created = false;

    // if user is logged in
    if (!empty($_SESSION['sessionusername'])) {
        // check to see if e-mail address is already in use
        $query =
            "SELECT user_id
            FROM user
            WHERE
                (
                    (user_username = ?)
                    OR (user_email = ?)
                )
                AND (user_username != ?)";
        $result = db_q($query, array(
            (string) $liveform->get_field_value('email_address'),
            (string) $liveform->get_field_value('email_address'),
            (string) $_SESSION['sessionusername'])) or output_error('Query failed.');
        
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
            WHERE user_username = ?";
        $result = db_q($query, array((string) $_SESSION['sessionusername'])) or output_error('Query failed.');
        $row = mysqli_fetch_assoc($result);
        
        $user_id = $row['user_id'];
        $contact_id = $row['user_contact'];
        $current_email_address = $row['user_email'];

        // look for contact
        $query = "SELECT id FROM contacts WHERE id = ?";
        $result = db_q($query, array((string) $contact_id)) or output_error('Query failed.');

        // if a contact was not found, create contact (we will update later)
        if (mysqli_num_rows($result) == 0) {
            $query = "INSERT INTO contacts (
                        email_address,
                        user,
                        timestamp)
                     VALUES (
                        ?,
                        ?,
                        UNIX_TIMESTAMP())";
            $result = db_q($query, array((string) $current_email_address, (string) $user_id)) or output_error('Query failed.');

            // get contact id so we can connect user to contact
            $contact_id = mysqli_insert_id(db::$con);
            $contact_created = true;
            pg_audit_site_record('create', 'contacts', (int) $contact_id);

            // A visitor who set their e-mail preferences without having a
            // contact record has one now.
            pg_announce_contact_created($contact_id);
            
            // check if registration contact group exists
            $query = "SELECT id FROM contact_groups WHERE id = ?";
            $result = db_q($query, array((string) REGISTRATION_CONTACT_GROUP_ID)) or output_error('Query failed.');
            
            // if registration contact group exists, assign contact to contact group
            if (mysqli_num_rows($result) > 0) {
                $query =
                    "INSERT INTO contacts_contact_groups_xref (
                        contact_id,
                        contact_group_id)
                    VALUES (
                        ?,
                        ?)";
                $result = db_q($query, array((string) $contact_id, (string) REGISTRATION_CONTACT_GROUP_ID)) or output_error('Query failed.');
            }
        }
        
        // if e-mail address has changed and user's current e-mail address is the same as the user's current username,
        if (($liveform->get_field_value('email_address') != $current_email_address) && ($current_email_address == $_SESSION['sessionusername'])) {
            // prepare sql to update user's username
            $sql_update_username = "user_username = ?, ";
            $sql_update_username_values = array((string) $liveform->get_field_value('email_address'));
        } else {
            $sql_update_username = '';
            $sql_update_username_values = array();
        }

        // update user
        $query = "UPDATE user SET user_email = ?,$sql_update_username user_contact = ? WHERE user_id = ?";
        $result = db_q($query, array_merge(
            array((string) $liveform->get_field_value('email_address')),
            $sql_update_username_values,
            array((string) $contact_id, (string) $user_id))) or output_error('Query failed.');
        
        // if username was changed
        if ($sql_update_username) {
            // The remember-me token is keyed by user id, not username, so an
            // email or username change needs no cookie update at all.

            // update username in session
            $_SESSION['sessionusername'] = $liveform->get_field_value('email_address');
            
            $liveform->add_notice(lang(array('string' => 'Your username has been updated. Username: {var:1}', 'vars' => array(h($liveform->get_field_value('email_address'))))));
        }
    
    // else user is not logged in
    } else {
        $current_email_address = str_rot13(base64_decode($posted_id));
        
        // Only a link this site signed for the address may change the contact behind it;
        // the id alone is an obfuscated e-mail address anyone could produce.
        if (pg_email_preferences_signature_valid($current_email_address, $posted_sig) == false) {
            output_error(lang('The email preferences link is not valid.'));
        }
        
        // get contact information
        $query =
            "SELECT id
            FROM contacts
            WHERE email_address = ?";
        $result = db_q($query, array((string) $current_email_address)) or output_error('Query failed.');
        
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
                WHERE email_address = ?";
            $result = db_q($query, array((string) $liveform->get_field_value('email_address'))) or output_error('Query failed.');
            
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

    // One check box per list, each a single value.
    pg_liveform_single_values($liveform, array_map(function ($contact_group) {
        return 'contact_group_' . $contact_group['id'];
    }, $contact_groups));
    
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
                    (contact_id = ?)
                    AND (contact_group_id = ?)";
            $result = db_q($query, array((string) $contact_id, (string) $contact_group['id'])) or output_error('Query failed.');
            
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
    $query = "SELECT id FROM contacts WHERE email_address = ?";
    $result = db_q($query, array((string) $current_email_address)) or output_error('Query failed.');
    
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
    
    // The opt-in column is written only when the form drew its control; its
    // value goes beside the clause. $sql_update_user holds an integer only.
    $opt_in_posted = pg_sw_posted_control($pg_controls, 'opt_in');

    // loop through all contacts in order to update them
    foreach ($contacts as $contact) {
        // update contact
        $audit_before = pg_audit_fetch('contacts', (int) $contact['id']);
        $query =
            "UPDATE contacts
            SET
                email_address = ?,
                " . ($opt_in_posted ? "opt_in = ?," : '') . "
                $sql_update_user
                timestamp = UNIX_TIMESTAMP()
            WHERE id = ?";
        $result = db_q($query, array_merge(
            array((string) $liveform->get_field_value('email_address')),
            $opt_in_posted ? array((string) $liveform->get_field_value('opt_in')) : array(),
            array((string) $contact['id']))) or output_error('Query failed.');
        pg_audit_site_record('update', 'contacts', (int) $contact['id'], $audit_before);
        
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
                        (contact_id = ?)
                        AND (contact_group_id = ?)";
                $result = db_q($query, array((string) $contact['id'], (string) $contact_group['id'])) or output_error('Query failed.');
                
                // if contact is not already in contact group, then add contact to contact group
                if (mysqli_num_rows($result) == 0) {
                    $query =
                        "INSERT INTO contacts_contact_groups_xref (
                            contact_id,
                            contact_group_id)
                        VALUES (
                            ?,
                            ?)";
                    $result = db_q($query, array((string) $contact['id'], (string) $contact_group['id'])) or output_error('Query failed.');
                }
            }
            
            // check to see if there is already an opt-in record
            $query =
                "SELECT contact_id
                FROM opt_in
                WHERE
                    (contact_id = ?)
                    AND (contact_group_id = ?)";
            $result = db_q($query, array((string) $contact['id'], (string) $contact_group['id'])) or output_error('Query failed.');
            
            // if there is not already an opt-in record, then create record
            if (mysqli_num_rows($result) == 0) {
                $query =
                    "INSERT INTO opt_in (
                        contact_id,
                        contact_group_id,
                        opt_in)
                    VALUES (
                        ?,
                        ?,
                        ?)";
                $result = db_q($query, array(
                    (string) $contact['id'],
                    (string) $contact_group['id'],
                    (string) $liveform->get_field_value('contact_group_' . $contact_group['id']))) or output_error('Query failed.');
                
            // else an opt-in record already exists, so update record
            } else {
                $query =
                    "UPDATE opt_in
                    SET opt_in = ?
                    WHERE
                        (contact_id = ?)
                        AND (contact_group_id = ?)";
                $result = db_q($query, array(
                    (string) $liveform->get_field_value('contact_group_' . $contact_group['id']),
                    (string) $contact['id'],
                    (string) $contact_group['id'])) or output_error('Query failed.');
            }
        }
    }
    
    // The answers of this post for the consent log, under the same guards as
    // the writes above: the opt-in and the lists the screen drew. When the
    // opt-in is unticked the lists are left out: the legacy screen hides their
    // boxes then, and a hidden ticked box still posts '1'. A contact created
    // by this post was in no closed list when the screen was drawn, so only
    // the open lists were on it.
    $consent_answers = array();

    if (pg_sw_posted_control($pg_controls, 'opt_in')) {
        $consent_answers['email:marketing'] = ($liveform->get_field_value('opt_in') === '1');
    }

    if (!isset($consent_answers['email:marketing']) || $consent_answers['email:marketing']) {
        foreach ($contact_groups as $contact_group) {
            if ($contact_created && ($contact_group['email_subscription_type'] != 'open')) {
                continue;
            }

            if (pg_sw_posted_control($pg_controls, 'contact_group_' . $contact_group['id'])) {
                $consent_answers['email:group:' . $contact_group['id']] = ($liveform->get_field_value('contact_group_' . $contact_group['id']) === '1');
            }
        }
    }

    // The SMS box: drawn by a widget that lists it, or by the legacy screen's
    // layout, which marks it with opt_in_sms_shown (a custom layout without
    // the box posts neither, so its silence is no refusal). It is recorded
    // only while SMS is on and the contact has a mobile number, as the
    // screen drew it; the consent log is where the answer is kept.
    $sms_drawn = ($pg_controls === null)
        ? ($liveform->get_field_value('opt_in_sms_shown') === '1')
        : pg_sw_posted_control($pg_controls, 'opt_in_sms');
    $sms_number_known = pg_consent_sms_number_known(pg_consent_sms_contact_number($contact_id));
    $consent_answers += pg_consent_sms_answers(
        $sms_drawn && pg_consent_sms_offered($sms_number_known),
        $sms_number_known,
        $liveform->get_field_value('opt_in_sms'));

    // Only the answers that differ from the person's last recorded ones are
    // written, once per person rather than once per contact. The address is
    // the one just saved; the rows recorded under the address before the
    // save are read as well, whichever account or contact they carry. A
    // signed-in visitor answers on the account page, a visitor without a
    // session through the signed link in an e-mail.
    pg_consent_log_answers(
        array(
            'email' => $liveform->get_field_value('email_address'),
            'user_id' => $user_id,
            'contact_id' => $contact_id,
            'previous_email' => $current_email_address),
        $consent_answers,
        !empty($_SESSION['sessionusername']) ? 'account' : 'unsubscribe');

    $liveform->add_notice(lang('Your email preferences have been updated.'));
    
    // send user back to e-mail preferences screen
    header('Location: ' . URL_SCHEME . $_SERVER['HTTP_HOST'] . $email_preferences_path . $url_id);

}