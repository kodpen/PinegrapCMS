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

function get_email_preferences($properties = array()) {

    $page_id = $properties['page_id'];

    $form = new liveform('email_preferences');

    // The address and signature from an email preferences link. Only a string can be
    // either; an array (id[]=) is no link at all and must not reach trim() or h().
    $link_id = (isset($_GET['id']) && is_string($_GET['id'])) ? $_GET['id'] : '';
    $link_signature = (isset($_GET['sig']) && is_string($_GET['sig'])) ? $_GET['sig'] : '';

    // if user is logged in, get e-mail information for user
    if (pg_session_signed_in() == true) {
        // get user information to find contact
        $query =
            "SELECT
                user_email,
                user_contact
            FROM user
            WHERE user_username = ?";
        $result = db_q($query, array((string) $_SESSION['sessionusername'])) or output_error('Query failed.');
        $row = mysqli_fetch_assoc($result);
        $email_address = $row['user_email'];
        $contact_id = $row['user_contact'];

        // get contact information
        $query =
            "SELECT opt_in
            FROM contacts
            WHERE id = ?";
        $result = db_q($query, array((string) $contact_id)) or output_error('Query failed.');
        $row = mysqli_fetch_assoc($result);
        
        // A user without a contact record (an administrator created in the control
        // panel) has no opt-in setting yet, so the box starts unchecked.
        $opt_in = is_array($row) ? $row['opt_in'] : '';
    
    // else user is not logged in
    } else {
        // If an id was not passed in the query string, then the visitor did not come to this page
        // from an email preferences link in an email campaign, so require that the visitor login or register.
        // A link without the signature (issued before links were signed) takes the same route: the
        // visitor signs in and lands back here on the signed-in path.
        if (!$link_id || !$link_signature) {
            header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/registration_entrance.php?send_to=' . urlencode(get_request_uri()));
            exit();
        }
        
        $email_address = str_rot13(base64_decode(trim($link_id)));
        
        if (validate_email_address($email_address) == false) {
            output_error(lang('The id for the email address is not valid.'));
        }
        
        // The signature proves the link was issued by this site for this address; without it
        // the id alone would let anyone who can spell an address change that contact's preferences.
        if (pg_email_preferences_signature_valid($email_address, trim($link_signature)) == false) {
            output_error(lang('The email preferences link is not valid.'));
        }
        
        // get contact information
        $query =
            "SELECT
                id,
                opt_in
            FROM contacts
            WHERE email_address = ?";
        $result = db_q($query, array((string) $email_address)) or output_error('Query failed.');
        
        // if a contact was not found for the e-mail address, then output error
        if (mysqli_num_rows($result) == 0) {
            output_error(lang('A contact for that email address could not be found.'));
        }
        
        $row = mysqli_fetch_assoc($result);
        
        $contact_id = $row['id'];
        $opt_in = $row['opt_in'];
        
    }

    // The SMS box beside the opt-in, while SMS is on and the contact has a
    // mobile number. Its answer lives in the consent log, so it starts from
    // the person's last SMS answer, ticked when there is none.
    $sms_opt_in = pg_consent_sms_offered(pg_consent_sms_number_known(pg_consent_sms_contact_number($contact_id)));

    $attributes =
        'action="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/email_preferences.php" ' .
        'method="post"';

    $form->set('email_address', 'required', true);
    $form->set('email_address', 'maxlength', 100);

    // If the form has not been submitted yet, then prefill values.
    if (!$form->field_in_session('token')) {
        $form->set('email_address', 'value', $email_address);

        if ($opt_in == 1) {
            $form->set('opt_in', 'value', 1);
        }

        if ($sms_opt_in && pg_consent_sms_start_for(array(
                'email' => $email_address,
                'contact_id' => (int) $contact_id))) {
            $form->set('opt_in_sms', 'value', 1);
        }
    }

    // Get all email subscription contact groups,
    // in order to figure out which ones should be shown to the user.
    $contact_groups = db_items(
        "SELECT
            id,
            name,
            description,
            email_subscription_type
        FROM contact_groups
        WHERE email_subscription = '1'
        ORDER BY name");

    // Loop through the contact groups in order to remove ones that should
    // not be shown to the user.
    foreach ($contact_groups as $key => $contact_group) {
        // assume that we should not output contact group, until we find out otherwise
        $show_contact_group = false;
        
        // if contact group's e-mail subscription type is open, then we should output contact group
        if ($contact_group['email_subscription_type'] == 'open') {
            $show_contact_group = true;
            
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
            
            // if contact is in this contact group, then we should output contact group
            if (mysqli_num_rows($result) > 0) {
                $show_contact_group = true;
            }
        }

        // If we should not show this contact group, then remove it from the array,
        // so the contact group will not be passed to the layout, and move to
        // to the next contact group.
        if (!$show_contact_group) {
            unset($contact_groups[$key]);
            continue;
        }

        // If the form has not been submitted yet, then determine if this contact group
        // should be checked or not.
        if (!$form->field_in_session('token')) {
            // check if contact is in this contact group
            $query =
                "SELECT contact_id
                FROM contacts_contact_groups_xref
                WHERE
                    (contact_id = ?)
                    AND (contact_group_id = ?)";
            $result = db_q($query, array((string) $contact_id, (string) $contact_group['id'])) or output_error('Query failed.');
            
            // if contact is in this contact group
            if (mysqli_num_rows($result) > 0) {
                // check if contact is opted-in to contact group
                $query =
                    "SELECT opt_in
                    FROM opt_in
                    WHERE
                        (contact_id = ?)
                        AND (contact_group_id = ?)";
                $result = db_q($query, array((string) $contact_id, (string) $contact_group['id'])) or output_error('Query failed.');
                $row = mysqli_fetch_assoc($result);
                
                // if an opt-in record was not found or opt-in is 1, then contact is opted-in
                if ((mysqli_num_rows($result) == 0) || ($row['opt_in'] == 1)) {
                    $form->set('contact_group_' . $contact_group['id'], 'value', 1);
                }
            }
        }
    }

    $my_account_url = '';

    // If the user is logged in then get my account URL, for cancel button.
    if (USER_LOGGED_IN) {
        $my_account_url = get_page_type_url('my account');
    }

    $system =
        get_token_field() . '

        <input type="hidden" name="id" value="' . h($link_id) . '">
        <input type="hidden" name="sig" value="' . h($link_signature) . '">';

    // If this is being outputted on the frontend, then call frontend JS function.
    if ($page_id) {
        $system .= '<script>software.init_email_preferences()</script>';

    // Otherwise this is being outputted on the backend, so call backend JS function.
    } else {
        $system .= '<script>init_email_preferences()</script>';
    }

    $output = render_layout(array(
        'page_id' => $page_id,
        'page_type' => 'email preferences',
        'messages' => $form->get_messages(),
        'form' => $form,
        'attributes' => $attributes,
        'contact_groups' => $contact_groups,
        'sms_opt_in' => $sms_opt_in,
        'my_account_url' => $my_account_url,
        'system' => $system));

    $output = $form->prepare($output);
        
    $form->remove_form();

    return $output;

}