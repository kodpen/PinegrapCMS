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

// if user is not logged in, send user to registration entrance screen to login or register
if (pg_session_signed_in() == false) {
    header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/registration_entrance.php?send_to=' . urlencode(get_request_uri()));
    exit();
}

// if user has not yet completed registration form, output form
if (!isset($_POST['submit'])) {
    print get_affiliate_sign_up_form_screen();
    
// else user has completed registration form, so process form
} else {
    validate_token_field();
    
    include_once('liveform.class.php');
    $liveform = new liveform('affiliate_sign_up_form');
    
    // get user information that we will use below in several places
    $query =
        "SELECT
            user.user_id,
            contacts.id as contact_id,
            contacts.affiliate_approved
        FROM user
        LEFT JOIN contacts ON user.user_contact = contacts.id
        WHERE user.user_username = ?";
    $result = db_q($query, array((string) $_SESSION['sessionusername'])) or output_error('Query failed.');
    $row = mysqli_fetch_assoc($result);
    $user_id = $row['user_id'];
    $contact_id = $row['contact_id'];
    $existing_affiliate_approved = $row['affiliate_approved'];

    $liveform->add_fields_to_session();

    // Every control on this form posts a single value.
    pg_liveform_single_values($liveform, array(
        'current_page_id', 'next_page_id', 'first_name', 'last_name', 'address_1', 'address_2',
        'city', 'state', 'zip_code', 'country', 'phone_number', 'fax_number', 'email_address',
        'affiliate_name', 'affiliate_website', 'affiliate_code', 'terms_and_conditions'));

    // The pages the form names to come back to and to go on to. Either can be
    // left out, and only a string can name one.
    $current_page_id = (isset($_POST['current_page_id']) && is_string($_POST['current_page_id'])) ? $_POST['current_page_id'] : '';
    $next_page_id = (isset($_POST['next_page_id']) && is_string($_POST['next_page_id'])) ? $_POST['next_page_id'] : '';

    $liveform->validate_required_field('first_name', lang('First Name is required.'));
    $liveform->validate_required_field('last_name', lang('Last Name is required.'));
    $liveform->validate_required_field('address_1', lang(array('string'=>'{var:1} is required.','vars'=>lang('Address') )) );
    $liveform->validate_required_field('city', lang(array('string'=>'{var:1} is required.','vars'=>lang('City') )) );
    $liveform->validate_required_field('country', lang(array('string'=>'{var:1} is required.','vars'=>lang('Country') )) );

    // If a country has been selected and then determine if state and zip code are required.
    if ($liveform->get('country')) {

        // If there is a state in this system for the selected country, then require state.
        if (db(
            "SELECT states.id FROM states
            LEFT JOIN countries ON countries.id = states.country_id
            WHERE countries.code = '" . e($liveform->get('country')) . "'
            LIMIT 1")
        ) {
            $liveform->validate_required_field('state', lang(array('string'=>'{var:1} is required.','vars'=>lang('State/Province') )) );
        }

        // If this country requires a zip code, then require it.
        if (
            db(
                "SELECT zip_code_required FROM countries
                WHERE code = '" . e($liveform->get('country')) . "'")
        ) {
            $liveform->validate_required_field('zip_code', lang(array('string'=>'{var:1} is required.','vars'=>lang('Zip/Postal Code') )) );
        }
    }

    $liveform->validate_required_field('phone_number', lang(array('string'=>'{var:1} is required.','vars'=>lang('Phone') )));
    $liveform->validate_required_field('email_address', lang(array('string'=>'{var:1} is required.','vars'=>lang('Email') )) );
    $liveform->validate_required_field('affiliate_code', lang(array('string'=>'{var:1} is required.','vars'=>lang('Affiliate Code') )) );
    $liveform->validate_required_field('affiliate_name', lang(array('string'=>'{var:1} is required.','vars'=>lang('Affiliate/Company Name') )));
    
    // determine if a terms check box appeared on the form
    $query =
        "SELECT page.page_name
        FROM affiliate_sign_up_form_pages
        LEFT JOIN page ON affiliate_sign_up_form_pages.terms_page_id = page.page_id
        WHERE affiliate_sign_up_form_pages.page_id = ?";
    $result = db_q($query, array((string) $liveform->get_field_value('current_page_id'))) or output_error('Query failed.');
    $row = mysqli_fetch_assoc($result);
    // No row for the legacy screen (current page 0) or a page that is not an affiliate form.
    $terms_page_name = $row ? $row['page_name'] : '';

    // if a terms page name was found, then require terms check box to be checked
    if ($terms_page_name != '') {
        $liveform->validate_required_field('terms_and_conditions', lang('Agreement to the terms and conditions is required.') );
        
    // else a terms page name was not found, so unmark error
    } else {
        $liveform->unmark_error('terms_and_conditions');
    }
    
    // if there is not already an error for the e-mail address field, validate e-mail address
    if ($liveform->check_field_error('email_address') == false) {
        if (validate_email_address($liveform->get_field_value('email_address')) == false) {
            $liveform->mark_error('email_address', lang(array('string'=>'{var:1} is invalid.','vars'=>lang('Email'))) );
        }
    }

    // check to see if e-mail address is already in use
    $query = "SELECT user_id FROM user WHERE (user_email = ?) AND (user_username != ?)";
    $result = db_q($query, array((string) $liveform->get_field_value('email_address'), (string) $_SESSION['sessionusername'])) or output_error('Query failed.');
    if (mysqli_num_rows($result) > 0) {
        $liveform->mark_error('email_address', lang('That email address is already in use. Please enter a different email address.'));
        $liveform->assign_field_value('email_address', '');
    }
    
    // if there is not already an error for the affiliate code field, check if the affiliate code is already in use
    if ($liveform->check_field_error('affiliate_code') == FALSE) {
        $query =
            "SELECT id
            FROM contacts
            WHERE
                (affiliate_code = ?)
                AND (id != ?)";
        $result = db_q($query, array((string) $liveform->get_field_value('affiliate_code'), (string) $contact_id)) or output_error('Query failed.');
        
        // if the affiliate code is already in use, then add error
        if (mysqli_num_rows($result) > 0) {
            $liveform->mark_error('affiliate_code', lang('That affiliate code is already in use. Please enter a different affiliate code.'));
        }
    }

    // if an error does not exist
    if ($liveform->check_form_errors() == false) {
        // if the user did not enter a website, then clear http:// from website value
        if ($liveform->get_field_value('affiliate_website') == 'http://') {
            $liveform->assign_field_value('affiliate_website', '');
        }

        // if a contact was found, update contact
        if ($contact_id != '') {
            // if automatic approval is on or contact is already approved, set contact to be approved
            if ((AFFILIATE_AUTOMATIC_APPROVAL == true) || ($existing_affiliate_approved == 1)) {
                $affiliate_approved = 1;
            } else {
                $affiliate_approved = 0;
            }
            
            $audit_before = pg_audit_fetch('contacts', (int) $contact_id);
            $query = "UPDATE contacts
                     SET
                        first_name = ?,
                        last_name = ?,
                        business_address_1 = ?,
                        business_address_2 = ?,
                        business_city = ?,
                        business_state = ?,
                        business_zip_code = ?,
                        business_country = ?,
                        business_phone = ?,
                        business_fax = ?,
                        email_address = ?,
                        affiliate_name = ?,
                        company = ?,
                        website = ?,
                        affiliate_approved = ?,
                        affiliate_code = ?,
                        user = ?,
                        timestamp = UNIX_TIMESTAMP()
                     WHERE id = ?";
            $result = db_q($query, array(
                (string) $liveform->get_field_value('first_name'),
                (string) $liveform->get_field_value('last_name'),
                (string) $liveform->get_field_value('address_1'),
                (string) $liveform->get_field_value('address_2'),
                (string) $liveform->get_field_value('city'),
                (string) $liveform->get_field_value('state'),
                (string) $liveform->get_field_value('zip_code'),
                (string) $liveform->get_field_value('country'),
                (string) $liveform->get_field_value('phone_number'),
                (string) $liveform->get_field_value('fax_number'),
                (string) $liveform->get_field_value('email_address'),
                (string) $liveform->get_field_value('affiliate_name'),
                (string) $liveform->get_field_value('affiliate_name'),
                (string) $liveform->get_field_value('affiliate_website'),
                (string) $affiliate_approved,
                (string) $liveform->get_field_value('affiliate_code'),
                (int) $user_id,
                (int) $contact_id)) or output_error('Query failed.');
            pg_audit_site_record('update', 'contacts', (int) $contact_id, $audit_before);

        // else a contact was not found, so create contact
        } else {
            // if automatic approval is on, then approve affiliate
            if (AFFILIATE_AUTOMATIC_APPROVAL == true) {
                $affiliate_approved = 1;
            } else {
                $affiliate_approved = 0;
            }
            
            // create contact
            $query = "INSERT INTO contacts (
                         first_name,
                         last_name,
                         business_address_1,
                         business_address_2,
                         business_city,
                         business_state,
                         business_zip_code,
                         business_country,
                         business_phone,
                         business_fax,
                         email_address,
                         affiliate_name,
                         company,
                         website,
                         affiliate_approved,
                         affiliate_code,
                         user,
                         timestamp)
                     VALUES (
                         ?,
                         ?,
                         ?,
                         ?,
                         ?,
                         ?,
                         ?,
                         ?,
                         ?,
                         ?,
                         ?,
                         ?,
                         ?,
                         ?,
                         ?,
                         ?,
                         ?,
                         UNIX_TIMESTAMP())";
            $result = db_q($query, array(
                (string) $liveform->get_field_value('first_name'),
                (string) $liveform->get_field_value('last_name'),
                (string) $liveform->get_field_value('address_1'),
                (string) $liveform->get_field_value('address_2'),
                (string) $liveform->get_field_value('city'),
                (string) $liveform->get_field_value('state'),
                (string) $liveform->get_field_value('zip_code'),
                (string) $liveform->get_field_value('country'),
                (string) $liveform->get_field_value('phone_number'),
                (string) $liveform->get_field_value('fax_number'),
                (string) $liveform->get_field_value('email_address'),
                (string) $liveform->get_field_value('affiliate_name'),
                (string) $liveform->get_field_value('affiliate_name'),
                (string) $liveform->get_field_value('affiliate_website'),
                (string) $affiliate_approved,
                (string) $liveform->get_field_value('affiliate_code'),
                (string) $user_id)) or output_error('Query failed.');

            // get contact id so we can connect user to contact
            $contact_id = mysqli_insert_id(db::$con);

            // The affiliate sign-up form just created this contact.
            pg_audit_site_record('create', 'contacts', (int) $contact_id);
            pg_announce_contact_created($contact_id);
        }
        
        // check if affiliate contact group exists
        $query = "SELECT id FROM contact_groups WHERE id = ?";
        $result = db_q($query, array((string) AFFILIATE_CONTACT_GROUP_ID)) or output_error('Query failed.');
        
        // if affiliate contact group exists
        if (mysqli_num_rows($result) > 0) {
            // check if contact is already in affiliate contact group
            $query =
                "SELECT contact_id
                FROM contacts_contact_groups_xref
                WHERE
                    (contact_id = ?)
                    AND (contact_group_id = ?)";
            $result = db_q($query, array((string) $contact_id, (string) AFFILIATE_CONTACT_GROUP_ID)) or output_error('Query failed.');
            
            // if contact is not already in contact group, then add contact to affiliate contact group
            if (mysqli_num_rows($result) == 0) {
                $query =
                    "INSERT INTO contacts_contact_groups_xref (
                        contact_id,
                        contact_group_id)
                    VALUES (
                        ?,
                        ?)";
                $result = db_q($query, array((string) $contact_id, (string) AFFILIATE_CONTACT_GROUP_ID)) or output_error('Query failed.');
            }
        }

        // update user
        $query = "UPDATE user
                 SET
                    user_email = ?,
                    user_contact = ?,
                    user_user = ?,
                    user_timestamp = UNIX_TIMESTAMP()
                 WHERE user_id = ?";
        $result = db_q($query, array((string) $liveform->get_field_value('email_address'), (string) $contact_id, (string) $user_id, (string) $user_id)) or output_error('Query failed.');
        
        // if there is a group offer and the affiliate has been approved, then determine if we need to add a key code for group offer for this affiliate
        if ((AFFILIATE_GROUP_OFFER_ID != 0) && ($affiliate_approved == 1)) {
            // check if offer exists and get offer code
            $query = "SELECT code FROM offers WHERE id = ?";
            $result = db_q($query, array((string) AFFILIATE_GROUP_OFFER_ID)) or output_error('Query failed.');
            
            // if an offer was found, then continue to check if a key code should be added for group offer
            if (mysqli_num_rows($result) > 0) {
                $offer = mysqli_fetch_assoc($result);
                
                // check if a key code already exists for this group offer and affiliate
                $query =
                    "SELECT id
                    FROM key_codes
                    WHERE
                        (code = ?)
                        AND (offer_code = ?)";
                $result = db_q($query, array((string) $liveform->get_field_value('affiliate_code'), (string) $offer['code'])) or output_error('Query failed.');
                
                // if a key code does not already exist for this group offer and affiliate, then create key code
                if (mysqli_num_rows($result) == 0) {
                    $query =
                        "INSERT INTO key_codes (
                            code,
                            offer_code,
                            enabled,
                            user,
                            timestamp)
                        VALUES (
                            ?,
                            ?,
                            '1',
                            ?,
                            UNIX_TIMESTAMP())";
                    $result = db_q($query, array((string) $liveform->get_field_value('affiliate_code'), (string) $offer['code'], (string) $user_id)) or output_error('Query failed.');
                }
            }
        }

        // store information in session so affiliate sign up confirmation page can output values
        $_SESSION['software']['affiliate_sign_up_confirmation']['first_name'] = $liveform->get_field_value('first_name');
        $_SESSION['software']['affiliate_sign_up_confirmation']['last_name'] = $liveform->get_field_value('last_name');
        $_SESSION['software']['affiliate_sign_up_confirmation']['address_1'] = $liveform->get_field_value('address_1');
        $_SESSION['software']['affiliate_sign_up_confirmation']['address_2'] = $liveform->get_field_value('address_2');
        $_SESSION['software']['affiliate_sign_up_confirmation']['city'] = $liveform->get_field_value('city');
        $_SESSION['software']['affiliate_sign_up_confirmation']['state'] = $liveform->get_field_value('state');
        $_SESSION['software']['affiliate_sign_up_confirmation']['zip_code'] = $liveform->get_field_value('zip_code');
        $_SESSION['software']['affiliate_sign_up_confirmation']['country'] = $liveform->get_field_value('country');
        $_SESSION['software']['affiliate_sign_up_confirmation']['phone_number'] = $liveform->get_field_value('phone_number');
        $_SESSION['software']['affiliate_sign_up_confirmation']['fax_number'] = $liveform->get_field_value('fax_number');
        $_SESSION['software']['affiliate_sign_up_confirmation']['email_address'] = $liveform->get_field_value('email_address');
        $_SESSION['software']['affiliate_sign_up_confirmation']['affiliate_code'] = $liveform->get_field_value('affiliate_code');
        $_SESSION['software']['affiliate_sign_up_confirmation']['affiliate_name'] = $liveform->get_field_value('affiliate_name');
        $_SESSION['software']['affiliate_sign_up_confirmation']['affiliate_website'] = $liveform->get_field_value('affiliate_website');
        
        // prepare body for affiliate sign up confirmation e-mail
        if ($next_page_id) {
            require_once(dirname(__FILE__) . '/get_page_content.php');
            $body = get_page_content($next_page_id, $system_content = '', $extra_system_content = '', $mode = 'preview', $email = true);
        } else {
            $body = get_affiliate_sign_up_confirmation_screen();
        }

        email(array(
            'to' => $liveform->get_field_value('email_address'),
            'bcc' => AFFILIATE_EMAIL_ADDRESS,
            'from_name' => ORGANIZATION_NAME,
            'from_email_address' => EMAIL_ADDRESS,
            'subject' => lang('Affiliate Sign Up Confirmation'),
            'format' => 'html',
            'body' => $body));

        // if automatic approval is on, send welcome e-mail to affiliate
        if (AFFILIATE_AUTOMATIC_APPROVAL == true) {
            // store values in session, so they are accessible on affiliate welcome screen
            $_SESSION['software']['affiliate_welcome']['affiliate_code'] = $liveform->get_field_value('affiliate_code');
            
            email(array(
                'to' => $liveform->get_field_value('email_address'),
                'bcc' => AFFILIATE_EMAIL_ADDRESS,
                'from_name' => ORGANIZATION_NAME,
                'from_email_address' => EMAIL_ADDRESS,
                'subject' => lang('Welcome to the Affiliate Program'),
                'format' => 'html',
                'body' => get_affiliate_welcome_screen()));
            
        // else automatic approval is not on, so send approval e-mail to admin
        } else {

            // In the past we would set the from info to the submitter's address (if one existed),
            // however this caused issues with mail providers using DMARC (e.g. Yahoo, AOL),
            // so we now send it from this site's info and add a reply to for the submitter (if exists).
            
            email(array(
                'to' => AFFILIATE_EMAIL_ADDRESS,
                'bcc' => AFFILIATE_EMAIL_ADDRESS,
                'from_name' => ORGANIZATION_NAME,
                'from_email_address' => EMAIL_ADDRESS,
                'reply_to' => $liveform->get_field_value('email_address'),
                'subject' => lang('Affiliate Approval Needed'),
                'body' => 
                    lang('An affiliate has requested your approval.') . "\n" .
                    "\n" .
                    lang('Affiliate Code') . ': ' . $liveform->get_field_value('affiliate_code') . "\n" .
                    lang('Affiliate / Company Name') . ': ' . $liveform->get_field_value('affiliate_name') . "\n" .
                    lang('Affiliate Website') . ': ' . $liveform->get_field_value('affiliate_website') . "\n" .
                    lang('First Name') . ': ' . $liveform->get_field_value('first_name') . "\n" .
                    lang('Last Name') . ': ' . $liveform->get_field_value('last_name') . "\n" .
                    lang('Address') . ' 1: ' . $liveform->get_field_value('address_1') . "\n" .
                    lang('Address') . ' 2: ' . $liveform->get_field_value('address_2') . "\n" .
                    lang('City') . ': ' . $liveform->get_field_value('city') . "\n" .
                    lang('State / Province') . ': ' . $liveform->get_field_value('state') . "\n" .
                    lang('Zip / Postal Code') . ': ' . $liveform->get_field_value('zip_code') . "\n" .
                    lang('Country') . ': ' . $liveform->get_field_value('country') . "\n" .
                    lang('Phone') . ': ' . $liveform->get_field_value('phone_number') . "\n" .
                    lang('Fax') . ': ' . $liveform->get_field_value('fax_number') . "\n" .
                    lang('Email') . ': ' . $liveform->get_field_value('email_address') . "\n" .
                    "\n" .
                    lang('If you approve, please click on the link below.  If you are not currently logged in, you will be asked to log in.') . "\n" .
                    "\n" .
                    URL_SCHEME . HOSTNAME_SETTING . PATH . SOFTWARE_DIRECTORY . '/approve_affiliate.php?id=' . (int) $contact_id . "\n" .
                    "\n"));

        }

        // remove liveform because we don't need it anymore
        $liveform->remove_form('affiliate_sign_up_form');

        // A designed page (the affiliate sign-up widget names itself in
        // pg_widget_id) with no next page keeps the visitor: it shows the
        // widget's confirmation message in place of the form.
        $widget_cfg = (!$next_page_id && $current_page_id)
            ? pg_member_widget_cfg($_POST['pg_widget_id'] ?? 0, 'affiliate_sign_up_form') : null;

        // if a next page was supplied, forward user to specific page that was supplied
        if ($next_page_id) {
            header('Location: ' . URL_SCHEME . $_SERVER['HTTP_HOST'] . PATH . get_page_name($next_page_id));
            
        } elseif ($widget_cfg !== null) {
            $confirmed = new liveform('affiliate_sign_up_form');
            $confirmed->assign_field_value('screen', 'confirm');
            $confirmed->add_notice(pg_member_confirmation_notice($widget_cfg, lang('Thank you. Your affiliate application has been sent.')));
            header('Location: ' . URL_SCHEME . $_SERVER['HTTP_HOST'] . PATH . get_page_name($current_page_id));

        // else a next page was not supplied, so forward user to general screen
        } else {
            header('Location: ' . URL_SCHEME . $_SERVER['HTTP_HOST'] . PATH . SOFTWARE_DIRECTORY . '/affiliate_sign_up_confirmation.php');
        }

    // else an error does exist
    } else {
        // if a current page was supplied, forward user to specific page
        if ($current_page_id) {
            header('Location: ' . URL_SCHEME . $_SERVER['HTTP_HOST'] . PATH . get_page_name($current_page_id));
            
        // else a current page was not supplied, so forward user to general screen
        } else {
            header('Location: ' . URL_SCHEME . $_SERVER['HTTP_HOST'] . PATH . SOFTWARE_DIRECTORY . '/affiliate_sign_up_form.php');
        }
    }
}