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


function submit_order($type) {
    switch($type) {
        case 'order preview':
            $type_value = 'order_preview';
            break;
        
        case 'express order':
            $type_value = 'express_order';
            break;
    }
    
   
    // Only a string can name the page; page_id[]= counts as empty.
    $page_id = (isset($_REQUEST['page_id']) && is_string($_REQUEST['page_id'])) ? $_REQUEST['page_id'] : '';
    

    $liveform = new liveform($type_value);

    $ghost = $_SESSION['software']['ghost'] ?? false;
    
    // if the mode is paypal_express_checkout_return, then set payment method value, because it might no longer exist in the session
    // for example, if the customer was taken to PayPal, clicked the back button to go back to the website, and then clicked the forward button to go to PayPal again
    // if we don't do this, then the order will be allowed to be submitted without a payment method
    if (($_GET['mode'] ?? '') == 'paypal_express_checkout_return') {
        $liveform->assign_field_value('payment_method', 'PayPal Express Checkout');
    }
    // A payment driver's return (the iyzico 3-D Secure and Pay With Iyzico
    // returns among them) is for the method that driver serves.
    $payment_return_method = pg_payment_return_method((string) ($_GET['mode'] ?? ''));
    if ($payment_return_method !== '') {
        $liveform->assign_field_value('payment_method', $payment_return_method);
    }

    check_banned_ip_addresses('complete order');
    
    // If the customer came from the order preview screen or PayPal, then verify that shipping &
    // billing info is complete.  We don't have to do this if the customer just submitted the
    // express order page because it already does that by validating all the fields on the screen.
    // Normally, the billing and shipping info should be complete at this point, however there are
    // edge cases where a customer might do something unusual, which requires that we check.  For
    // example, a customer might open an additional browser window and submit blank shipping
    // or billing info and then come back to the original window and try to complete the order.

    if (pg_payment_is_return_mode($_GET['mode'] ?? '')) {

        // Check for an incomplete recipient.
        $incomplete_recipient_id = db("
            SELECT id FROM ship_tos
            WHERE
                (order_id = '" . e(($_SESSION['ecommerce']['order_id'] ?? '')) . "')
                AND (complete = '0')
            ORDER BY id LIMIT 1");

        // If there is an incomplete recipient, show error.
        if ($incomplete_recipient_id) {
            $message = lang('Sorry, your order could not be completed because the shipping info is incomplete. Please update the shipping info below.');
            log_activity($message);

            // If the customer came from order preview, then send customer to shipping address page.
            if ($type == 'order preview') {

                $liveform_shipping_address_and_arrival = new liveform('shipping_address_and_arrival');
                $liveform_shipping_address_and_arrival->mark_error('incomplete_ship_tos_error',
                    $message);

                go(PATH . encode_url_path(get_page_name($_SESSION['ecommerce']['shipping_address_and_arrival_page_id'])) .
                    '?ship_to_id=' . $incomplete_recipient_id);

            // Otherwise the customer came from PayPal, by way of express order, so send customer
            // to express order page.
            } else {

                $liveform->mark_error('incomplete_ship_tos_error', $message);
                go(PATH . encode_url_path(get_page_name($page_id)));
            }
        }

        // Check if billing info is complete.
        $billing_complete = db("
            SELECT billing_complete FROM orders
            WHERE id = '" . e(($_SESSION['ecommerce']['order_id'] ?? '')) . "'");
        
        // If billing info is not complete, show error.
        if (!$billing_complete) {

            $message = lang('Sorry, your order could not be completed because the billing info is incomplete. Please update the billing info below.');

            log_activity($message);

            // If the customer came from order preview, then send customer to billing info page.
            if ($type == 'order preview') {

                $liveform_billing_information = new liveform('billing_information');
                $liveform_billing_information->mark_error('incomplete_billing_error',
                    $message);

                go(PATH . encode_url_path(get_page_name($_SESSION['ecommerce']['billing_information_page_id'])));

            // Otherwise the customer came from PayPal, by way of express order, so send customer
            // to express order page.
            } else {

                $liveform->mark_error('incomplete_billing_error', $message);
                go(PATH . encode_url_path(get_page_name($page_id)));
            }
        }
    }

    $user_id = '';
    $contact_id = '';

    // If the user is logged in and not ghosting, then use this user and contact.
    if (USER_LOGGED_IN and !$ghost) {
        $user_id = USER_ID;
        $contact_id = USER_CONTACT_ID;
    }


    // If a contact has not been found, then look for one in order.
    if (!$contact_id) {

        // get contact id for this order
        $query = "SELECT contact_id FROM orders WHERE id = ?";
        $result = db_q($query, array((string) ($_SESSION['ecommerce']['order_id'] ?? ''))) or output_error('Query failed.');
        $row = mysqli_fetch_assoc($result);
        $contact_id = $row['contact_id'];
        
        // check to see if this contact is connected to a user
        $query = "SELECT user_id FROM user WHERE user_contact = ?";
        $result = db_q($query, array((string) $contact_id)) or output_error('Query failed.');
        
        // if the contact is connected to a user, then the contact should not be used
        if (mysqli_num_rows($result) > 0) {
            $contact_id = 0;
        }
    }

    // If a contact id has been found, then check to make sure contact still exists.
    if ($contact_id) {
        $contact_id = db("SELECT id FROM contacts WHERE id = '" . e($contact_id) . "'");
    }

    // If we could not find a contact, then create one.
    if (!$contact_id) {
        $query =
            "INSERT INTO contacts (
                user,
                timestamp)
            VALUES (
                ?,
                UNIX_TIMESTAMP())";
        $result = db_q($query, array((string) $user_id)) or output_error('Query failed.');
        $contact_id = mysqli_insert_id(db::$con);
        pg_audit_site_record('create', 'contacts', (int) $contact_id);

        // if user is logged in, connect new contact record to user record
        if ($user_id) {
            $query = "UPDATE user SET user_contact = ? WHERE user_id = ?";
            $result = db_q($query, array((string) $contact_id, (string) $user_id)) or output_error('Query failed.');
        }
    }

    // check if there is already a contact group record for Orders
    $query = "SELECT id FROM contact_groups WHERE name = 'Orders'";
    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
    
    // if there is a contact group record for Orders contact group, get id
    if (mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_assoc($result);
        $contact_group_id = $row['id'];
        
    // else there is not a contact group record for the Orders contact group, so create contact group
    } else {
        $query =
            "INSERT INTO contact_groups (
                name,
                created_timestamp,
                last_modified_timestamp)
            VALUES (
                'Orders',
                UNIX_TIMESTAMP(),
                UNIX_TIMESTAMP())";
        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
        
        $contact_group_id = mysqli_insert_id(db::$con);
    }
    
    // check if contact is already in Orders contact group
    $query =
        "SELECT contact_id
        FROM contacts_contact_groups_xref
        WHERE
            (contact_id = ?)
            AND (contact_group_id = ?)";
    $result = db_q($query, array((string) $contact_id, (string) $contact_group_id)) or output_error('Query failed.');
    
    // if contact is not already in contact group, then add contact to Orders contact group
    if (mysqli_num_rows($result) == 0) {
        $query =
            "INSERT INTO contacts_contact_groups_xref (
                contact_id,
                contact_group_id)
            VALUES (
                ?,
                ?)";
        $result = db_q($query, array((string) $contact_id, (string) $contact_group_id)) or output_error('Query failed.');
    }
    
    // add user id and contact id to order record in database
    $query = "UPDATE orders
             SET
                user_id = ?,
                contact_id = ?
             WHERE id = ?";
    $result = db_q($query, array((string) $user_id, (string) $contact_id, (string) (int) ($_SESSION['ecommerce']['order_id'] ?? 0))) or output_error('Query failed.');
    
    // initialize a variable for storing the total balance of all gift cards
    $gift_card_balance = 0;
    
    // if gift cards are enabled, then check if applied gift cards are still valid and refresh balances
    if (ECOMMERCE_GIFT_CARD == TRUE) {
        // get applied gift cards
        $query =
            "SELECT
                id,
                gift_card_id,
                code,
                old_balance,
                givex
            FROM applied_gift_cards
            WHERE order_id = ?
            ORDER BY id ASC";
        $result = db_q($query, array((string) ($_SESSION['ecommerce']['order_id'] ?? ''))) or output_error('Query failed.');

        $applied_gift_cards = array();

        // loop through applied gift cards in order to add them to array
        while ($row = mysqli_fetch_assoc($result)) {
            $applied_gift_cards[] = $row;
        }
        
        // if there is at least one applied gift card, then continue to check gift card balances
        if (count($applied_gift_cards) > 0) {
            // If there are only gift card products in the order,
            // then remove all applied gift cards and output error.
            if (
                db_value(
                    "SELECT COUNT(*)
                    FROM order_items
                    LEFT JOIN products ON order_items.product_id = products.id
                    WHERE
                        (order_items.order_id = '" . ($_SESSION['ecommerce']['order_id'] ?? '') . "')" . pg_order_item_active_sql() . "
                        AND products.gift_card = '0'") == 0
            ) {
                db("DELETE FROM applied_gift_cards WHERE order_id = '" . ($_SESSION['ecommerce']['order_id'] ?? '') . "'");
                $liveform->mark_error('gift_card', lang('Sorry, we do not allow gift cards to be applied to an order that only contains gift cards.  We have removed the previously applied gift card(s).  Please add a different type of item to your order in order to apply a gift card.'));
                go(PATH . encode_url_path(get_page_name($page_id)));
            }

            $gift_card_plural_suffix = '';
            
            // if the number of applied gift cards is greater than 1, then prepare plural suffix
            if (count($applied_gift_cards) > 1) {
                $gift_card_plural_suffix = 's';
            }
            
            // loop through the applied gift cards in order to check if they are valid and refresh balances
            foreach ($applied_gift_cards as $key => $applied_gift_card) {
                // If this is a gift card in this system (i.e. not givex),
                // then check that gift card still exists and balance is good.
                if ($applied_gift_card['givex'] == 0) {
                    // Check for a gift card in this system.
                    $gift_card = db_item(
                        "SELECT
                            id,
                            balance,
                            expiration_date
                        FROM gift_cards
                        WHERE id = '" . $applied_gift_card['gift_card_id']  . "'")
                        ?: array('id' => ''); // the card was deleted after it was applied

                    // If a gift card no longer exists, then output error.
                    if ($gift_card['id'] == '') {
                        $liveform->mark_error('gift_card_' . $applied_gift_card['id'], lang(array('string'=>'Sorry, we could not find a gift card for that code ({var:1}).  Therefore, the gift card has been removed from your order. You may try using a different gift card.','vars'=>array(h(protect_gift_card_code($applied_gift_card['code']))))));
                        db("DELETE FROM applied_gift_cards WHERE id = '" . escape($applied_gift_card['id']) . "'");
                        unset($applied_gift_cards[$key]);

                    // Otherwise, if the card has expired, then output error.
                    } else if (
                        ($gift_card['expiration_date'] != '0000-00-00')
                        && ($gift_card['expiration_date'] < date('Y-m-d'))
                    ) {
                        $liveform->mark_error('gift_card_' . $applied_gift_card['id'], lang(array('string'=>'Sorry, we cannot accept the gift card ({var:1}) because it has expired. Therefore, the gift card has been removed from your order. You may try using a different gift card.','vars'=>array(h(protect_gift_card_code($applied_gift_card['code']))))));
                        db("DELETE FROM applied_gift_cards WHERE id = '" . escape($applied_gift_card['id']) . "'");
                        unset($applied_gift_cards[$key]);

                    // Otherwise, if the balance is 0, then output error.
                    } else if ($gift_card['balance'] == 0) {
                        $liveform->mark_error('gift_card_' . $applied_gift_card['id'], lang(array('string'=>'Sorry, we cannot accept the gift card ({var:1}) because there is no remaining balance. Therefore, the gift card has been removed from your order. You may try using a different gift card.','vars'=>array(h(protect_gift_card_code($applied_gift_card['code']))))));
                        db("DELETE FROM applied_gift_cards WHERE id = '" . escape($applied_gift_card['id']) . "'");
                        unset($applied_gift_cards[$key]);

                    // Otherwise the card is valid, so update data for card in database and array.
                    } else {
                        $balance = $gift_card['balance'];

                        // update old balance in database
                        $query = "UPDATE applied_gift_cards SET old_balance = ? WHERE id = ?";
                        $result = db_q($query, array((string) $balance, (string) $applied_gift_card['id'])) or output_error('Query failed.');

                        // update old balance in array
                        $applied_gift_cards[$key]['old_balance'] = $balance;

                        // update total gift card balance
                        $gift_card_balance = $gift_card_balance + $balance;
                    }

                // Otherwise this is a givex gift card, so check if gift card still exists
                // and if balance is good.
                } else {
                    // if cURL does not exist, then add error and send user back to previous screen
                    if (function_exists('curl_init') == FALSE) {
                        $liveform->mark_error('gift_card', lang(array('string'=>'Sorry, we cannot communicate with the gift card service, because cURL is not installed, so we cannot accept the gift card{var:1}. The administrator of this website should install cURL. If you would like to proceed without using the gift card{var:1}, then you may remove the gift card{var:1} and continue.','vars'=>array($gift_card_plural_suffix))));
                        header('Location: ' . URL_SCHEME . HOSTNAME . PATH . encode_url_path(get_page_name($page_id)));
                        exit();
                    }

                    // send a balance request to Givex in order to check gift card balance
                    $result = send_givex_request('balance', $applied_gift_card['code']);
                    
                    // if there was an error or the balance is 0 then add error and possibly remove applied gift card
                    if ((isset($result['curl_errno']) == TRUE) || (isset($result['error_message']) == TRUE) || ($result['balance'] == 0)) {
                        // if there was a curl error, then prepare error message
                        if (isset($result['curl_errno']) == TRUE) {
                            $error_message = lang(array('string'=>'Sorry, we cannot communicate with the gift card service at the moment, so we cannot accept the gift card ({var:1}). Please try again later. If the problem continues, then please contact the administrator of this website. If you would like to proceed without using the gift card, then you may remove the card and continue. cURL Error Number: {var:2}. cURL Error Message: {var:3}.','vars'=>array(h(protect_givex_gift_card_code($applied_gift_card['code'])), h($result['curl_errno']), h($result['curl_error']))));
                        
                        // else if there was a Givex error, then prepare error message and remove gift card
                        } else if (isset($result['error_message']) == TRUE) {
                            $error_message = lang(array('string'=>'Sorry, we cannot accept the gift card ({var:1}) because there was an error from the gift card service: {var:2}. Therefore, the gift card has been removed from your order. You may try using a different gift card.','vars'=>array(h(protect_givex_gift_card_code($applied_gift_card['code'])), h($result['error_message']))));
                            
                            // remove applied gift card from database
                            $query = "DELETE FROM applied_gift_cards WHERE id = ?";
                            $result = db_q($query, array((string) $applied_gift_card['id'])) or output_error(lang('Query failed.'));
                            
                            // remove applied gift card from array
                            unset($applied_gift_cards[$key]);
                            
                        // else if the balance is 0, then prepare error message and remove gift card
                        } else if ($result['balance'] == 0) {
                            $error_message = lang(array('string'=>'Sorry, we cannot accept the gift card ({var:1}) because there is no remaining balance. Therefore, the gift card has been removed from your order. You may try using a different gift card.','vars'=>array(h(protect_givex_gift_card_code($applied_gift_card['code'])))));
                            
                            // remove applied gift card from database
                            $query = "DELETE FROM applied_gift_cards WHERE id = ?";
                            $result = db_q($query, array((string) $applied_gift_card['id'])) or output_error(lang('Query failed.'));
                            
                            // remove applied gift card from array
                            unset($applied_gift_cards[$key]);
                        }
                        
                        // add error
                        $liveform->mark_error('gift_card_' . $applied_gift_card['id'], $error_message);
                        
                    // else the card is valid, so update data for card in database and array
                    } else {
                        $balance = $result['balance'] * 100;
                        
                        // update old balance in database
                        $query = "UPDATE applied_gift_cards SET old_balance = ? WHERE id = ?";
                        $result = db_q($query, array((string) $balance, (string) $applied_gift_card['id'])) or output_error('Query failed.');
                        
                        // update old balance in array
                        $applied_gift_cards[$key]['old_balance'] = $balance;
                        
                        // update total gift card balance
                        $gift_card_balance = $gift_card_balance + $balance;
                    }
                }
            }
        }
    
    // else gift cards are disabled, so delete any applied gift cards that might exist for this order
    // this is used to delete any gift cards that might have been applied right before gift cards were disabled
    } else {
        $query = "DELETE FROM applied_gift_cards WHERE order_id = ?";
        $result = db_q($query, array((string) ($_SESSION['ecommerce']['order_id'] ?? ''))) or output_error('Query failed.');
    }
    
    // if the mode is not paypal_express_checkout_return, then add field values to session and validate data
    if (!pg_payment_is_return_mode($_GET['mode'] ?? '')) {
        // An express order's fields are in the session already: express_order.php
        // added this post before its own checks and then adjusted some values
        // (the billing name and address taken from the recipient when the
        // billing address is the same as the shipping address, the tax number
        // without separators, sanitized rich-text answers, cleared codes).
        // Reading the request again here put the raw post back over them: the
        // hidden, empty billing fields failed the required checks below.
        if ($type != 'express order') {
            $liveform->add_fields_to_session();
        }

        // The billing and payment fields post one value each (express_order.php has done this
        // for an express order already), and the totals the visitor saw are compared below as
        // numbers, so an array there counts as a total not posted.
        pg_liveform_single_values($liveform, array('billing_salutation', 'billing_first_name', 'billing_last_name', 'billing_company', 'billing_address_1', 'billing_address_2', 'billing_city', 'billing_state', 'billing_zip_code', 'billing_country', 'billing_phone_number', 'billing_fax_number', 'billing_email_address', 'custom_field_1', 'custom_field_2', 'opt_in', 'opt_in_displayed', 'tax_exempt', 'tax_number', 'tax_office', 'po_number', 'referral_source', 'update_contact', 'gift_card_code', 'payment_method', 'card_number', 'cardholder', 'expiration', 'expiration_month', 'expiration_year', 'card_verification_number', 'installment', 'notes', 'terms', 'legal_preinfo', 'legal_contract', 'legal_kvkk'));
        pg_liveform_single_values($liveform, array('total', 'total_with_surcharge'), null);

        // Clear stale field errors from a PREVIOUS submit before re-validating.
        // Without this, a payment_gateway error from a prior Iyzipay/PayPal
        // failure (e.g. expired SSL CA, mid-flow timeout) stays in session and
        // wedges every subsequent submit: validation passes, but the check at
        // line ~1328 sees the leftover error and redirects back to the page —
        // visitor sees the SAME old "Ödeme başlatılamadı" banner forever and
        // assumes the system is still broken. The validators below re-add any
        // errors that are *still* applicable to the current POST.
        //
        // Not for an express order: express_order.php has already validated
        // THIS post before calling here (shipping address and method, gift
        // card recipients, product forms, recurring schedules) and none of
        // those checks run again below. Clearing here threw their errors
        // away, and the order went through without them - a gift card with
        // no recipient, a parcel with no shipping method. express_order.php
        // clears the previous submit's errors itself, before its checks.
        if (($type != 'express order') && method_exists($liveform, 'unmark_errors')) {
            $liveform->unmark_errors();
        }

        // if order was submitted from an express order screen, then validate billing information fields
        if ($type == 'express order') {
            // Get page info.
            $query =
                "SELECT
                    custom_field_1_label,
                    custom_field_1_required,
                    custom_field_2_label,
                    custom_field_2_required,
                    form
                FROM express_order_pages
                WHERE page_id = ?";
            $result = db_q($query, array((string) $page_id)) or output_error('Query failed.');
            // No row when the id names no express order page: no custom fields then.
            $row = mysqli_fetch_assoc($result) ?: array();

            $custom_field_1_label = $row['custom_field_1_label'] ?? '';
            $custom_field_1_required = $row['custom_field_1_required'] ?? '';
            $custom_field_2_label = $row['custom_field_2_label'] ?? '';
            $custom_field_2_required = $row['custom_field_2_required'] ?? '';
            $form = $row['form'] ?? '';

            // if there is a custom field 1 label and custom field 1 is required, then validate required field
            if (($custom_field_1_label != '') && ($custom_field_1_required == 1)) {
                $liveform->validate_required_field('custom_field_1', lang(array('string'=>'{var:1} is required.','vars'=>array($custom_field_1_label))));
            }

            // if there is a custom field 2 label and custom field 2 is required, then validate required field
            if (($custom_field_2_label != '') && ($custom_field_2_required == 1)) {
                $liveform->validate_required_field('custom_field_2', lang(array('string'=>'{var:1} is required.','vars'=>array($custom_field_2_label))));
            }
            
            $liveform->validate_required_field('billing_first_name', lang('First Name is required.'));
            $liveform->validate_required_field('billing_last_name', lang('Last Name is required.'));
            $liveform->validate_required_field('billing_address_1', lang('Address 1 is required.'));
            $liveform->validate_required_field('billing_city', lang('City is required.'));
            $liveform->validate_required_field('billing_country', lang('Country is required.'));

            // If a country has been selected and then determine if state and zip code are required.
            if ($liveform->get('billing_country')) {

                // If there is a state in this system for the selected country, then require state.
                if (db(
                    "SELECT states.id FROM states
                    LEFT JOIN countries ON countries.id = states.country_id
                    WHERE countries.code = '" . e($liveform->get('billing_country')) . "'
                    LIMIT 1")
                ) {
                    $liveform->validate_required_field('billing_state', lang('State/Province is required.'));
                }

                // If this country requires a zip code, then require it.
                if (
                    db(
                        "SELECT zip_code_required FROM countries
                        WHERE code = '" . e($liveform->get('billing_country')) . "'")
                ) {
                    $liveform->validate_required_field('billing_zip_code', lang('Zip/Postal Code is required.'));
                }
            }            
            
            $liveform->validate_required_field('billing_phone_number', lang('Phone is required.'));
            $liveform->validate_required_field('billing_email_address', lang('Email is required.'));

            // if there is not already an error for the e-mail address field, validate e-mail address
            if ($liveform->check_field_error('billing_email_address') == false) {
                if (validate_email_address($liveform->get_field_value('billing_email_address')) == false) {
                    $liveform->mark_error('billing_email_address', lang('Email is invalid.'));
                }
            }

            // If a custom billing form is enabled, then submit form.
            if ($form == 1) {

                submit_custom_form(array(
                    'form' => $liveform,
                    'page_id' => $page_id,
                    'page_type' => 'express order',
                    'form_type' => 'billing',
                    'require' => true));
            }
            
            // if there was no opt-in field on previous screen or if the opt-in checkbox was checked
            if (($liveform->get_field_value('opt_in_displayed') == 'false') || ($liveform->get_field_value('opt_in') == '1')) {
                $opt_in = 1;
                
            // else this order is not opted in
            } else {
                $opt_in = 0;
            }
            
            // if tax-exempt was checked
            if ($liveform->get_field_value('tax_exempt') == '1') {
                $tax_exempt = 1;
                
            // else tax-exempt was not checked
            } else {
                $tax_exempt = 0;
            }
            
            // get old address verified value from the database
            $query = "SELECT billing_address_verified FROM orders WHERE id = ?";
            $result = db_q($query, array((string) ($_SESSION['ecommerce']['order_id'] ?? ''))) or output_error('Query failed.');
            $row = mysqli_fetch_assoc($result);

            // The query above selects billing_address_verified, so that is the key to read.
            $address_verified = isset($row['billing_address_verified']) ? $row['billing_address_verified'] : '';

            // Verify address if address verification is enabled and it is a US address

            require_once(dirname(__FILE__) . '/shipping.php');

            $address_verified = verify_address(array(
                'liveform' => $liveform,
                'address_type' => 'billing',
                'old_address_verified' => $address_verified));

            // If there are errors, then set billing incomplete.
            if ($liveform->check_form_errors()) {
                $billing_complete = 0;

            // Otherwise there are no errors, so set billing complete.
            } else {
                $billing_complete = 1;
            }
            
            // add data to orders table
            $query =
                "UPDATE orders
                 SET
                    billing_salutation = ?,
                    billing_first_name = ?,
                    billing_last_name = ?,
                    billing_company = ?,
                    billing_address_1 = ?,
                    billing_address_2 = ?,
                    billing_city = ?,
                    billing_state = ?,
                    billing_zip_code = ?,
                    billing_country = ?,
                    billing_address_verified = ?,
                    billing_phone_number = ?,
                    billing_fax_number = ?,
                    billing_email_address = ?,
                    custom_field_1 = ?,
                    custom_field_2 = ?,
                    opt_in = ?,
                    tax_exempt = ?,
                    po_number = ?,
                    referral_source_code = ?,
                    billing_complete = ?,
                    ip_address = IFNULL(INET_ATON(?), 0)
                WHERE id = ?";
            $result = db_q($query, array((string) ($liveform->get_field_value('billing_salutation')), (string) ($liveform->get_field_value('billing_first_name')), (string) ($liveform->get_field_value('billing_last_name')), (string) ($liveform->get_field_value('billing_company')), (string) ($liveform->get_field_value('billing_address_1')), (string) ($liveform->get_field_value('billing_address_2')), (string) ($liveform->get_field_value('billing_city')), (string) ($liveform->get_field_value('billing_state')), (string) ($liveform->get_field_value('billing_zip_code')), (string) ($liveform->get_field_value('billing_country')), (string) $address_verified, (string) ($liveform->get_field_value('billing_phone_number')), (string) ($liveform->get_field_value('billing_fax_number')), (string) ($liveform->get_field_value('billing_email_address')), (string) ($liveform->get_field_value('custom_field_1')), (string) ($liveform->get_field_value('custom_field_2')), (string) $opt_in, (string) $tax_exempt, (string) ($liveform->get_field_value('po_number')), (string) ($liveform->get_field_value('referral_source')), (string) $billing_complete, (string) $_SERVER['REMOTE_ADDR'], (string) ($_SESSION['ecommerce']['order_id'] ?? ''))) or output_error('Query failed.');

            // Visitor-supplied order note (EO widget: `_bindings.eo_field='notes'`).
            // The `notes` column is only present on upgraded installs — probe once
            // per request before attempting the UPDATE. Defensive: never fatal,
            // never bypassed if the column is missing. Empty submitted value
            // clears any prior note for this draft order.
            if ($liveform->field_in_session('notes')) {
                if (pg_schema_has('orders', 'notes')) {
                    db("UPDATE orders SET notes = '"
                        . escape($liveform->get_field_value('notes'))
                        . "' WHERE id = '" . ($_SESSION['ecommerce']['order_id'] ?? '') . "'");
                }
            }

            // If the user is logged in and not ghosting and the user selected that he/she
            // does not want his/her contact info updated with billing info,
            // then remember that in the session, so if the user comes back
            // to the billing information screen the check box will be unchecked.
            if (USER_LOGGED_IN and !$ghost and !$liveform->get_field_value('update_contact')) {
                $_SESSION['software']['update_contact'] = false;
            } else {
                unset($_SESSION['software']['update_contact']);
            }

            // If the user is not logged in or is ghosting or if the user is logged in and wants
            // his/her contact info updated with billing info, then update contact.
            if (!USER_LOGGED_IN or $ghost or ((isset($_SESSION['software']['update_contact']) ? $_SESSION['software']['update_contact'] : null) !== false)) {

                $sql_fax = '';

                // If a fax field appeared on the form, then update fax number for contact.
                // We don't want to clear the contact's fax number if there was no fax field.
                if ($liveform->field_in_session('billing_fax_number')) {
                    $sql_fax = "business_fax = '" . e($liveform->get('billing_fax_number')) . "',";
                }

                // Identity / tax number and tax office for the invoice, only
                // when the form had them: a layout without them must not
                // clear what the contact already carries.
                $sql_tax = '';
                if ($liveform->field_in_session('tax_number') || $liveform->field_in_session('tax_office')) {
                    $sql_tax = "tax_number = '" . e(trim((string) $liveform->get_field_value('tax_number'))) . "', "
                             . "tax_office = '" . e(trim((string) $liveform->get_field_value('tax_office'))) . "',";
                }
                
                $audit_before = pg_audit_fetch('contacts', (int) $contact_id);
                $query =
                    "UPDATE contacts
                    SET
                        salutation = '" . escape($liveform->get_field_value('billing_salutation')) . "',
                        first_name = '" . escape($liveform->get_field_value('billing_first_name')) . "',
                        last_name = '" . escape($liveform->get_field_value('billing_last_name')) . "',
                        company = '" . escape($liveform->get_field_value('billing_company')) . "',
                        business_address_1 = '" . escape($liveform->get_field_value('billing_address_1')) . "',
                        business_address_2 = '" . escape($liveform->get_field_value('billing_address_2')) . "',
                        business_city = '" . escape($liveform->get_field_value('billing_city')) . "',
                        business_state = '" . escape($liveform->get_field_value('billing_state')) . "',
                        business_zip_code = '" . escape($liveform->get_field_value('billing_zip_code')) . "',
                        business_country = '" . escape($liveform->get_field_value('billing_country')) . "',
                        business_phone = '" . escape($liveform->get_field_value('billing_phone_number')) . "',
                        $sql_fax
                        $sql_tax
                        email_address = '" . escape($liveform->get_field_value('billing_email_address')) . "',
                        lead_source = '" . escape($liveform->get_field_value('referral_source')) . "',
                        opt_in = '$opt_in',
                        user = '$user_id',
                        timestamp = UNIX_TIMESTAMP()
                    WHERE id = '" . $contact_id . "'";
                $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
                pg_audit_site_record('update', 'contacts', (int) $contact_id, $audit_before);

                // The box's answer goes to the consent log as the contact now
                // holds it, here where the contact takes it: this runs also
                // for a submit whose billing or payment is refused, or that a
                // payment page never brings back, and the contact keeps the
                // answer then too. A gateway return skips this block.
                pg_consent_log_checkout($liveform->get_field_value('billing_email_address'), $user_id, $contact_id, $liveform->get_field_value('opt_in_displayed'), $ghost, $opt_in);
            } elseif ($liveform->field_in_session('tax_number')) {
                // The card is not updated from the billing block, but a card with no
                // tax number still takes the one typed here: the order keeps none of
                // its own, and the account its invoice goes to is opened from the card.
                pg_checkout_fill_contact_tax((int) $contact_id, (string) $liveform->get_field_value('tax_number'), (string) $liveform->get_field_value('tax_office'), (int) $user_id);
            }
        }
        
        // even though we are not done with validating all fields, we have to close out of this condition,
        // because we have to get data in order to figure out if the payment method is required and the data is also used by a paypal return
    }
    
    // get billing information for order (we will use this in various areas below)
    $query =
        "SELECT
            billing_salutation,
            billing_first_name,
            billing_last_name,
            billing_company,
            billing_address_1,
            billing_address_2,
            billing_city,
            billing_state,
            billing_zip_code,
            billing_country,
            billing_phone_number,
            billing_fax_number,
            billing_email_address,
            custom_field_1,
            custom_field_2,
            po_number,
            tax_exempt,
            offline_payment_allowed,
            reference_code,
            special_offer_code
        FROM orders
        WHERE id = ?";
    $result = db_q($query, array((string) ($_SESSION['ecommerce']['order_id'] ?? ''))) or output_error('Query failed.');
    $row = mysqli_fetch_assoc($result);
    
    $billing_salutation = $row['billing_salutation'];
    $billing_first_name = $row['billing_first_name'];
    $billing_last_name = $row['billing_last_name'];
    $billing_company = $row['billing_company'];
    $billing_address_1 = $row['billing_address_1'];
    $billing_address_2 = $row['billing_address_2'];
    $billing_city = $row['billing_city'];
    $billing_state = $row['billing_state'];
    $billing_zip_code = $row['billing_zip_code'];
    $billing_country = $row['billing_country'];
    $billing_phone_number = $row['billing_phone_number'];
    $billing_fax_number = $row['billing_fax_number'];
    $billing_email_address = $row['billing_email_address'];
    $custom_field_1 = $row['custom_field_1'];
    $custom_field_2 = $row['custom_field_2'];
    $po_number = $row['po_number'];
    $tax_exempt = $row['tax_exempt'];
    $offline_payment_allowed = $row['offline_payment_allowed'];
    $reference_code = $row['reference_code'];
    $special_offer_code = $row['special_offer_code'];
    
    // if tax is on, apply taxes to order
    if (ECOMMERCE_TAX == true) {
        update_order_item_taxes();
    }
    
    // get all products in cart
    $query =
        "SELECT
            order_items.id as order_item_id,
            order_items.ship_to_id,
            order_items.product_id,
            order_items.product_name,
            order_items.quantity,
            order_items.price,
            order_items.tax_total," . (pg_schema_has('order_items', 'tax_rate') ? "
            order_items.tax_rate AS line_tax_rate," : "") . "
            order_items.recurring_payment_period,
            order_items.recurring_number_of_payments,
            order_items.recurring_start_date,
            order_items.recurrence_number,
            order_items.add_watcher,
            products.id,
            products.enabled,
            products.short_description,
            products.price AS product_price,
            products.recurring,
            products.sage_group_id,
            products.required_product,
            products.commissionable,
            products.commission_rate_limit,
            products.order_receipt_bcc_email_address,
            products.contact_group_id,
            products.membership_renewal,
            products.grant_private_access,
            products.private_folder,
            products.private_days,
            products.send_to_page,
            products.email_page,
            products.email_bcc,
            products.inventory,
            products.inventory_quantity,
            products.out_of_stock,
            products.reward_points,
            products.gift_card,
            products.gift_card_email_subject,
            products.gift_card_email_format,
            products.gift_card_email_body,
            products.gift_card_email_page_id,
            products.submit_form,
            products.submit_form_custom_form_page_id,
            products.submit_form_quantity_type,
            products.submit_form_create,
            products.submit_form_update,
            products.submit_form_update_where_field,
            products.submit_form_update_where_value,
            products.add_comment,
            products.add_comment_page_id,
            products.add_comment_message,
            products.add_comment_name,
            products.add_comment_only_for_submit_form_update,
            calendar_events.id as calendar_event_id,
            calendar_events.reservations,
            calendar_events.limit_reservations,
            calendar_events.number_of_initial_spots
        FROM order_items
        LEFT JOIN products ON order_items.product_id = products.id
        LEFT JOIN calendar_events ON order_items.calendar_event_id = calendar_events.id
        WHERE order_items.order_id = ?" . pg_order_item_active_sql() . "
        ORDER BY order_items.id ASC";
    $result = db_q($query, array((string) ($_SESSION['ecommerce']['order_id'] ?? ''))) or output_error('Query failed.');

    $products = array();

    // foreach product in cart, add product to array
    while ($row = mysqli_fetch_assoc($result)) {
        $products[] = $row;
    }

    // if there are no items in the cart, output error
    if (count($products) == 0) {
        output_error(lang('There are no items. The order cannot be submitted.') . '<a href="javascript:history.go(-1)">' . lang('Go back') . '</a>');
    }
    
    $nonrecurring_products = array();
    $recurring_products = array();

    $recurring_transaction = false;

    // Loop through all ordered products to determine if they are non-recurring
    // or recurring.
    foreach ($products as $product) {
        // if product is not a recurring product or if start date is less than or equal to today's date and payment gateway is not ClearCommerce, then it will go in nonrecurring transaction
        // note: there can be recurring products in the nonrecurring transaction if the products have a start date of 0
        if (
            ($product['recurring'] == 0)
            ||
            (
                ($product['recurring_start_date'] <= date('Y-m-d'))
                && ((ECOMMERCE_CREDIT_DEBIT_CARD == false) || (ECOMMERCE_PAYMENT_GATEWAY != 'ClearCommerce'))
            )
        ) {
            $nonrecurring_products[] = $product;
        }

        // if product is a recurring product, then add it to array
        if ($product['recurring']) {
            
            $recurring_products[] = $product;

            if ($product['price'] > 0) {
                $recurring_transaction = true;
            }

        }
    }

    // These are only added to in the loop below, so they have to start at zero.
    $subtotal = 0;
    $tax = 0;

    // Only the payment gateway branches further below set these; an offline or zero-total
    // order never touches them, but they are always written to the order record.
    $transaction_id = '';
    $authorization_code = '';
    // The payment driver's answer ('paid', 'pending', ...); the legacy gateways leave it ''.
    $payment_status = '';
    $payment_installment = '';
    $installment_charge = '';
    $commission = '';

    // The lines charged today with the rate each was taxed at, for the tax
    // after an order discount (pg_order_charged_tax()).
    $charged_lines = array();

    // loop through all nonrecurring products in order to get totals for order
    foreach ($nonrecurring_products as $product) {

        $quantity = $product['quantity'];

        $total_price = $product['price'] * $product['quantity'];
        // tax_total is already the whole line, so no quantity here.
        $total_tax = $product['tax_total'];

        // update subtotal
        $subtotal = $subtotal + $total_price;

        // update tax
        $tax = $tax + $total_tax;

        $charged_lines[(int) $product['order_item_id']] = array(
            'amount' => (int) $product['price'] * max(1, (int) $product['quantity']),
            'rate' => $product['line_tax_rate'] ?? null,
            'tax' => (int) $product['tax_total'],
        );
    }

    // If the tax is negative then set it to zero.  The tax might be negative
    // if there are negative price products.  We don't want to allow a negative tax though.
    if ($tax < 0) {
        $tax = 0;
    }
    
    // Set the discount for the order if there is one: what the cart worked
    // out, at most the subtotal charged today (recurring lines that start
    // later are not in $subtotal).
    $discount = pg_order_charged_discount($_SESSION['ecommerce']['order_discount'] ?? 0, $subtotal);
    
    // store the orginal tax in case there is a discount
    // and we need to add a discount line item for PayPal Express Checkout
    $original_tax = $tax;

    // If there is an order discount, the tax is taken again per rate on what
    // the discount leaves of the lines, as the order's invoice takes it
    // (pg_order_charged_tax(); never below zero). A discount is only above
    // zero when the subtotal is.
    if ($discount && ($subtotal > 0)) {
        $tax = pg_order_charged_tax($charged_lines, $discount);
    }
    
    // get shipping total for all ship tos
    $query = "SELECT SUM(shipping_cost) as shipping FROM ship_tos WHERE order_id = ?";
    $result = db_q($query, array((string) ($_SESSION['ecommerce']['order_id'] ?? ''))) or output_error('Query failed.');
    $row = mysqli_fetch_assoc($result);
    $shipping = (int) $row['shipping'];

    // The shipping holds tax: on the order's invoice it is taken with the
    // goods of each rate, on their sum, and where that leaves a rate's total
    // no amount reaches (57.93 at 20%) the shipping is charged a minor unit
    // lower (pg_order_charges_reachable()), as the checkout showed it. The
    // recipient that gives up the minor units is the last one whose cost
    // covers them; its cost is lowered when the order is placed (below), so
    // the order's pages read what was charged.
    $shipping_less = 0;
    $shipping_less_ship_to_id = 0;

    if (($shipping > 0) && pg_order_charges_settle_rates()) {
        $settled = pg_order_charges_reachable($charged_lines, $discount, array('shipping' => $shipping));

        if ($settled['shipping'] < $shipping) {
            $shipping_less_ship_to_id = (int) db_value(
                "SELECT id FROM ship_tos
                WHERE order_id = '" . (int) ($_SESSION['ecommerce']['order_id'] ?? 0) . "' AND shipping_cost >= '" . ($shipping - $settled['shipping']) . "'
                ORDER BY id DESC LIMIT 1");

            if ($shipping_less_ship_to_id > 0) {
                $shipping_less = $shipping - $settled['shipping'];
                $shipping = $settled['shipping'];
            }
        }
    }

    $total = $subtotal - $discount + $tax + $shipping;

    $gift_card_discount = 0;

    // If the total is greater than 0 then set gift card discount
    if ($total > 0) {
        // if the gift card balance is greater than the total, then set the gift card discount to the total
        if ($gift_card_balance > $total) {
            $gift_card_discount = $total;
            
        // else the gift card balance is less than or equal to the total, so set the gift card discount to the gift card balance
        } else {
            $gift_card_discount = $gift_card_balance;
        }
    }
    
    // update the total to include the gift card discount
    $total = $total - $gift_card_discount;

    // Store the total without the surcharge that we might add below.
    // We use this further below in the affiliate area,
    // when determining the percent of the gift card discount to the total.
    $total_without_surcharge = $total;

    $surcharge = 0;
    
    // if the total is greater than 0, then a non-recurring transaction is required
    if ($total > 0) {
        $nonrecurring_transaction = TRUE;

        // If a credit card surcharge is enabled and the credit card payment method
        // was selected, then calculate surcharge and add it to total: the
        // exact product rounded half away from zero, the same figure on every
        // PHP version (pg_percent_of()).
        if (
            (ECOMMERCE_SURCHARGE_PERCENTAGE > 0)
            && ($liveform->get_field_value('payment_method') == 'Credit/Debit Card')
        ) {
            $surcharge = pg_percent_of($total, ECOMMERCE_SURCHARGE_PERCENTAGE);

            // Like the shipping, a minor unit lower where it would leave a
            // rate's total no amount reaches (pg_order_charges_reachable()).
            if (pg_order_charges_settle_rates()) {
                $surcharge = pg_order_charges_reachable($charged_lines, $discount, array('shipping' => $shipping, 'surcharge' => (int) $surcharge))['surcharge'];
            }

            $total = $total + $surcharge;
        }
        
    // else the total is not greater than 0, so a non-recurring transaction is not required
    } else {
        $nonrecurring_transaction = FALSE;
    }

    // get various properties for previous express order or order preview page
    // we just need the terms page name in the block below, however we will go ahead and get other values
    // that we will use later in the code, in order to minimize database queries
    $query =
        "SELECT
            page.page_name,
            " . $type_value . "_pages.offline_payment_always_allowed,
            " . $type_value . "_pages.auto_registration,
            " . $type_value . "_pages.pre_save_hook_code,
            " . $type_value . "_pages.post_save_hook_code,
            " . $type_value . "_pages.order_receipt_email,
            " . $type_value . "_pages.order_receipt_email_subject,
            " . $type_value . "_pages.order_receipt_email_format,
            " . $type_value . "_pages.order_receipt_email_header,
            " . $type_value . "_pages.order_receipt_email_footer,
            " . $type_value . "_pages.order_receipt_email_page_id,
            " . $type_value . "_pages.next_page_id
        FROM " . $type_value . "_pages
        LEFT JOIN page ON " . $type_value . "_pages.terms_page_id = page.page_id
        WHERE " . $type_value . "_pages.page_id = ?";
    $result = db_q($query, array((string) $page_id)) or output_error('Query failed.');
    // No row when the id names no page of this type: none of its settings then.
    $row = mysqli_fetch_assoc($result) ?: array();
    
    $terms_page_name = $row['page_name'] ?? '';
    $offline_payment_always_allowed = $row['offline_payment_always_allowed'] ?? '';
    $auto_registration = $row['auto_registration'] ?? '';
    $pre_save_hook_code = $row['pre_save_hook_code'] ?? '';
    $post_save_hook_code = $row['post_save_hook_code'] ?? '';
    $order_receipt_email = $row['order_receipt_email'] ?? '';
    $order_receipt_email_subject = $row['order_receipt_email_subject'] ?? '';
    $order_receipt_email_format = $row['order_receipt_email_format'] ?? '';
    $order_receipt_email_header = $row['order_receipt_email_header'] ?? '';
    $order_receipt_email_footer = $row['order_receipt_email_footer'] ?? '';
    $order_receipt_email_page_id = $row['order_receipt_email_page_id'] ?? '';
    $next_page_id = $row['next_page_id'] ?? '';
    
    // The order preview layouts shipped with the turkish_default install seed post the
    // translated label of the offline payment option as the field value instead of the
    // canonical name. Normalize it before validation so that the allow-list below, the
    // payment method switches and the stored orders.payment_method all see the canonical
    // value. Whether offline payment is actually offered is still decided by the allow-list.
    // The compared string is the UTF-8 byte sequence of the Turkish label "Cevrimdisi Odeme"
    // (with the Turkish characters), written as escapes to keep this file ASCII-only.
    if ($liveform->get_field_value('payment_method') === "\xc3\x87evrimd\xc4\xb1\xc5\x9f\xc4\xb1 \xc3\x96deme") {
        $liveform->assign_field_value('payment_method', 'Offline Payment');
    }

    // "Put it on my account" (ERP, config.erp_open_account_payment) posts
    // 'On Account'. orders.payment_method has no value of its own for it, so
    // from here on the order is an offline payment; whether it may go on the
    // account is decided with the other payment checks below, which set
    // $erp_on_account, and orders.erp_on_account records it.
    $erp_on_account_requested = ($liveform->get_field_value('payment_method') === 'On Account');
    $erp_on_account = false;

    if ($erp_on_account_requested) {
        $liveform->assign_field_value('payment_method', 'Offline Payment');
    }

    // A card typed into the payment service's own fields (Stripe's Payment
    // Element) never reaches this site: the form posts the service's token,
    // which the driver checks, and there are no card inputs to validate.
    $payment_hosted_card_driver = pg_payment_hosted_card_driver(ECOMMERCE_PAYMENT_GATEWAY);

    // if the mode is not a payment service's return, then validate the rest of the fields
    if (!pg_payment_is_return_mode($_GET['mode'] ?? '')) {
        // if a nonrecurring transaction or recurring transaction is required, then require a payment method
        if (($nonrecurring_transaction == TRUE) || ($recurring_transaction == TRUE)) {
            $liveform->validate_required_field('payment_method', lang('A payment method is required.'));

            // Legacy checkout layouts post the translated label as the value, so a
            // value that matches the translation of a known method is mapped back
            // to its constant before anything downstream reads it. Anything else
            // is refused here: orders.payment_method is an ENUM and the connection
            // runs without strict mode, so an unknown value would be stored as ''
            // and the order would be saved with no payment method at all.
            if ($liveform->check_field_error('payment_method') == FALSE) {
                $known_payment_methods = pg_payment_method_values();
                $posted_payment_method = trim((string) $liveform->get_field_value('payment_method'));

                if (in_array($posted_payment_method, $known_payment_methods, true) == FALSE) {
                    $matched_payment_method = '';

                    foreach ($known_payment_methods as $known_payment_method) {
                        if (mb_strtolower($posted_payment_method, 'UTF-8') == mb_strtolower(trim((string) lang($known_payment_method)), 'UTF-8')) {
                            $matched_payment_method = $known_payment_method;
                            break;
                        }
                    }

                    if ($matched_payment_method != '') {
                        $liveform->assign_field_value('payment_method', $matched_payment_method);
                    } else {
                        $liveform->mark_error('payment_method', lang('Please select a payment method.'));
                    }
                }
            }

            // If there is not already an error for the payment method field, then require the
            // selected payment method to be one that is offered for this order. This is an
            // allow-list on purpose: an unknown value would fall through the payment switches
            // further below and complete the order without a charge.
            if ($liveform->check_field_error('payment_method') == FALSE) {
                $allowed_payment_methods = pg_payment_checkout_methods(array(
                    'recurring'       => ($recurring_transaction == TRUE),
                    'offline_allowed' => (($offline_payment_allowed == '1') || ($offline_payment_always_allowed == 1)),
                ));

                // An order goes on the account only for a member who has one
                // to put it on (pg_erp_open_account()) and only while the
                // account's credit limit still covers it; the offline payment
                // settings above are not asked. A subscription is charged
                // again and again, so it never goes on account.
                if ($erp_on_account_requested) {
                    $erp_on_account_refusal = ($recurring_transaction == TRUE)
                        ? lang('The selected payment method is not available.')
                        : pg_erp_open_account_refusal($total);

                    if ($erp_on_account_refusal === '') {
                        $erp_on_account = true;
                    } else {
                        $liveform->mark_error('payment_method', $erp_on_account_refusal);
                    }

                } elseif (in_array($liveform->get_field_value('payment_method'), $allowed_payment_methods, TRUE) == FALSE) {
                    $liveform->mark_error('payment_method', lang('The selected payment method is not available.'));
                }
            }
            
            // if there is not already an error for the payment method field and the credit/debit card payment method was selected, then validate credit/debit card fields
            if (
                ($liveform->check_field_error('payment_method') == FALSE)
                && ($liveform->get_field_value('payment_method') == 'Credit/Debit Card')
                && ($payment_hosted_card_driver === '')
            ) {
                
                $liveform->validate_required_field('card_number', lang('Card Number is required.'));

                // With no payment gateway the number is kept on the order for
                // manual processing, and only ever encrypted: without the key
                // or openssl the card is refused instead of stored in clear.
                if (
                    !$liveform->check_field_error('card_number')
                    && (ECOMMERCE_PAYMENT_GATEWAY == '') && !credit_card_encryption_is_available()
                ) {
                    $liveform->mark_error('card_number', lang('Card payments cannot be accepted right now. Please choose another payment method.'));
                }

                // If there was a single expiration field (new way) then require that field.
                if ($liveform->field_in_session('expiration')) {

                    $expiration_single = true;
                    $liveform->validate_required_field('expiration', lang('Expiration is required.'));

                // Otherwise there must be two expiration fields (old way), so require both fields.
                } else {

                    $expiration_single = false;
                    $liveform->validate_required_field('expiration_month', lang('Expiration Month is required.'));
                    $liveform->validate_required_field('expiration_year', lang('Expiration Year is required.'));
                }

                // If there was a cardholder field then require it.
                if ($liveform->field_in_session('cardholder')) {
                    $liveform->validate_required_field('cardholder', lang('Cardholder is required.'));
                }

                // If there was a security code field then require it.
                if ($liveform->field_in_session('card_verification_number')) {
                    $liveform->validate_required_field('card_verification_number', lang('Security Code is required.'));
                }
                // If there is not already an error for the card number field validate card number
                if (!$liveform->check_field_error('card_number')) {
                
                    // remove non-numeric characters from credit card number
                    $card_number = preg_replace('/[^0-9]/', '', $liveform->get_field_value('card_number'));
                
                    $card_number_prefix4  = mb_substr($card_number, 0, 4);
                    $card_number_prefix6  = mb_substr($card_number, 0, 6);
                    $card_number_prefix2  = mb_substr($card_number, 0, 2);
                    $card_number_length   = mb_strlen($card_number);
                
                    $card_type = '';
                
                    if (
                        ECOMMERCE_AMERICAN_EXPRESS and ($card_number_length == 15) &&
                        ((($card_number_prefix4 >= 3400) && ($card_number_prefix4 <= 3499)) ||
                         (($card_number_prefix4 >= 3700) && ($card_number_prefix4 <= 3799)))
                    ) {
                        $card_type = 'American Express';
                    
                    } else if (
                        ECOMMERCE_DINERS_CLUB and ($card_number_length == 14) &&
                        ((($card_number_prefix4 >= 3000) && ($card_number_prefix4 <= 3059)) ||
                         (($card_number_prefix4 >= 3600) && ($card_number_prefix4 <= 3699)) ||
                         (($card_number_prefix4 >= 3800) && ($card_number_prefix4 <= 3889)))
                    ) {
                        $card_type = 'Diners Club';
                    
                    } else if (
                        ECOMMERCE_DISCOVER_CARD and ($card_number_length == 16) &&
                        (
                            mb_substr($card_number, 0, 4) == 6011 ||
                            mb_substr($card_number, 0, 2) == 65 ||
                            (mb_substr($card_number, 0, 3) >= 644 && mb_substr($card_number, 0, 3) <= 649) ||
                            (mb_substr($card_number, 0, 6) >= 622126 && mb_substr($card_number, 0, 6) <= 622925)
                        )
                    ) {
                        $card_type = 'Discover Card';
                    
                    } else if (
                        ECOMMERCE_MASTERCARD and ($card_number_length == 16) &&
                        (
                            ($card_number_prefix4 >= 5100 && $card_number_prefix4 <= 5599) ||
                            ($card_number_prefix4 >= 2221 && $card_number_prefix4 <= 2720)
                        )
                    ) {
                        $card_type = 'MasterCard';
                    
                    } else if (
                        ECOMMERCE_VISA and
                        (($card_number_length == 13) || ($card_number_length == 16) || ($card_number_length == 19)) &&
                        ($card_number_prefix4 >= 4000) && ($card_number_prefix4 <= 4999)
                    ) {
                        $card_type = 'Visa';
                    
                    } else if (
                        ECOMMERCE_TROY and ($card_number_length == 16) &&
                        (
                            $card_number_prefix6 == 657366 ||
                            $card_number_prefix6 == 657998 ||
                            ($card_number_prefix6 >= 979200 && $card_number_prefix6 <= 979299)
                        ) &&
                        (ECOMMERCE_PAYMENT_GATEWAY == 'Iyzipay' || ECOMMERCE_PAYMENT_GATEWAY == '')
                    ) {
                        $card_type = 'Troy';
                    }

                
                    // Assume that card number is not valid, until we find out otherwise.
                    $valid_credit_card = false;
                
                    // If the credit card number is valid so far, perform mod10 check.
                    if ($card_type) {
                    
                        $checksum = 0;
                    
                        // add even digits in even length strings or odd digits in odd length strings.
                        for ($location = 1 - ($card_number_length % 2); $location < $card_number_length; $location += 2) {
                            $checksum += mb_substr($card_number, $location, 1);
                        }
                    
                        // analyze odd digits in even length strings or even digits in odd length strings.
                        for ($location = ($card_number_length % 2); $location < $card_number_length; $location += 2) {
                            $digit = mb_substr($card_number, $location, 1) * 2;
                            if ($digit < 10) {
                                $checksum += $digit;
                            } else {
                                $checksum += $digit - 9;
                            }
                        }
                    
                        // if checksum is divisible by 10 then credit card number is valid
                        if ($checksum % 10 == 0) {
                            $valid_credit_card = true;
                        }
                    }
                
                    // If the credit card number is not valid, mark error.
                    if (!$valid_credit_card) {
                        $liveform->mark_error('card_number', lang('The credit/debit card number is invalid. Please check that you have entered the number correctly. If the number is correct, then we might not support that type of card, so please try a different card.'));
                    }
                }

                // If there is not already an error for the expiration, then get values.
                if (
                    !$liveform->check_field_error('expiration')
                    and !$liveform->check_field_error('expiration_month')
                    and !$liveform->check_field_error('expiration_year')
                ) {

                    // If there was a single expiration field (new way) then get month and year.
                    if ($expiration_single) {

                        $expiration_parts = explode('/', $liveform->get('expiration'));
                        $expiration_month = trim($expiration_parts[0]);
                        $expiration_year = trim($expiration_parts[1]);

                        if (!$expiration_month or !$expiration_year) {

                            $liveform->mark_error('expiration', lang('The expiration is not valid.'));

                        } else {

                            // If the month has 1 digit, then convert to 2.
                            if (mb_strlen($expiration_month) == 1) {
                                $expiration_month = '0' . $expiration_month;
                            }

                            // If the year has 2 digits, then convert to 4.
                            if (mb_strlen($expiration_year) == 2) {
                                $expiration_year = '20' . $expiration_year;
                            }
                        }

                    // Otherwise there must be two expiration fields (old way).
                    } else {
                        $expiration_month = $liveform->get('expiration_month');
                        $expiration_year = $liveform->get('expiration_year');
                    }
                }

                // If there is not already an error for the expiration, then check if expiration is
                // in the past, or too far in the future.
                if (
                    !$liveform->check_field_error('expiration')
                    and !$liveform->check_field_error('expiration_month')
                    and !$liveform->check_field_error('expiration_year')
                ) {

                    $current_year = date('Y');
                    $current_month = date('m');

                    // If the expiration is in the past, then add error.
                    if (
                        $expiration_year < $current_year
                        or ($expiration_year == $current_year and $expiration_month < $current_month)
                    ) {

                        $message = lang('The expiration date has passed.');

                        // If there was a single expiration field (new way).
                        if ($expiration_single) {
                            $liveform->mark_error('expiration', $message);

                        // Otherwise there must be two expiration fields (old way).
                        } else {
                            $liveform->mark_error('expiration_month', $message);
                            $liveform->mark_error('expiration_year', '');
                        }

                    // Otherwise if the expiration is too far in the future, then add error.
                    } else if ($expiration_year > ($current_year + 20)) {

                        $message = lang(array('string'=>'Sorry, the expiration year ({var:1}) is too far in the future. Please check that you have entered the correct year. We allow a maximum of 20 years in the future.','vars'=>array(h($expiration_year))));
                        // If there was a single expiration field (new way).
                        if ($expiration_single) {
                            $liveform->mark_error('expiration', $message);

                        // Otherwise there must be two expiration fields (old way).
                        } else {
                            $liveform->mark_error('expiration_year', $message);
                        }
                    }
                }
            }
        }

        // if a terms page name was found, then require terms checkbox to be checked
        if ($terms_page_name) {
            $liveform->validate_required_field('terms', lang('Agreement to the terms and conditions is required.'));
        }

        // The pre-information form and the distance sales contract are each
        // accepted when the store asks for them (pg_legal_documents_inject()
        // draws their checkboxes on every checkout).
        if (pg_legal_documents_enabled()) {
            foreach (pg_legal_document_types() as $legal_type => $legal_label) {
                $liveform->validate_required_field('legal_' . $legal_type, lang(array('string' => '{var:1} must be accepted.', 'vars' => array($legal_label))));
            }
        }
        
        // we are done with the validation of fields now
    }

    // Loop through the order items in order to determine if we need to remove order items
    // for products that have been deleted or disabled.  We have to have a special area to
    // do this here because this function does not run the update_order_item_prices() function,
    // which takes care of removing order items in other areas of the system.  In the future,
    // we might need to decide if we should just run update_order_item_prices() here also.
    foreach ($products as $product) {
        // If the product for this order item has been deleted or is disabled
        // then remove order item and add error.
        if (
            ($product['id'] == '')
            || ($product['enabled'] == 0)
        ) {
            remove_order_item($product['order_item_id']);

            // prepare product description for notice
            $product_description = '';
            
            // if there is a name, then add it to the description
            if ($product['product_name'] != '') {
                $product_description .= $product['product_name'];
            }
            
            // if there is a short description, then add it to the description
            if ($product['short_description'] != '') {
                // if the description is not blank, then add separator
                if ($product_description != '') {
                    $product_description .= ' - ';
                }
                
                $product_description .= $product['short_description'];
            }
            
            // if the description is blank, then set default description
            if ($product_description == '') {
                $product_description = lang('an item');
            }
            ;


            $liveform->add_notice(lang(array('string'=>'We are sorry, {var:1} has been removed from your order because it is not currently available.','vars'=>array(h($product_description)))));
        }
    }

    // Check min and max quantity requirements for products.
    check_quantity($liveform);
    
    // check inventory for order items in order to make sure they are still all in stock
    check_inventory($liveform);
    
    // check reservations for calendar events in order to make sure they are still valid and available
    check_reservations($liveform);
    
    // loop through all order items in order to determine if all required products are in order
    foreach ($products as $product) {
        // if product has a required product, then make sure required product is in cart
        if ($product['required_product']) {
            // find out if required product is in cart
            $query =
                "SELECT id
                FROM order_items
                WHERE
                    (order_id = ?)" . pg_order_item_active_sql() . "
                    AND (product_id = ?)";
            $result = db_q($query, array((string) ($_SESSION['ecommerce']['order_id'] ?? ''), (string) $product['required_product'])) or output_error('Query failed.');

            // if required product is not in cart, add product to cart and add notice, so user knows that required product was added to cart
            if (mysqli_num_rows($result) == 0) {
                // get information about required product
                $query = "SELECT name FROM products WHERE id = ?";
                $result = db_q($query, array((string) $product['required_product'])) or output_error('Query failed.');
                $row = mysqli_fetch_assoc($result);
                $required_product_name = $row['name'];

                // add required product to cart
                add_order_item($product['required_product'], 1, 0, '', '');

                $liveform->add_notice(lang(array(
                    'string' => '{var:1} requires {var:2}, so {var:2} has been added to your order.',
                    'vars' => array(h($product['product_name']), h($required_product_name))
                )));
            }
        }
    }
    
    /* begin: check that there are no free order items alone in a ship to */
    
    // products.short_description is the name the shopper sees; order_items.product_name
    // carries the item code, which means nothing to them in a warning.
    $query =
        "SELECT
            order_items.ship_to_id,
            order_items.product_name,
            products.short_description
        FROM order_items
        LEFT JOIN products ON order_items.product_id = products.id
        WHERE
            (order_items.order_id = ?)" . pg_order_item_active_sql() . "
            AND (order_items.ship_to_id > 0)
            AND (order_items.price <= 0)";
    $result = db_q($query, array((string) ($_SESSION['ecommerce']['order_id'] ?? ''))) or output_error('Query failed.');
    
    $free_order_items = array();
    
    while ($row = mysqli_fetch_assoc($result)) {
        $free_order_items[] = $row;
    }
    
    // loop through all free order items to see if they are alone in ship to
    foreach ($free_order_items as $free_order_item) {
        // try to get a non-free order item in the same ship to as the free order item
        $query =
            "SELECT id
            FROM order_items
            WHERE
                (order_id = ?)" . pg_order_item_active_sql() . "
                AND (ship_to_id = ?)
                AND (price > 0)";
        $result = db_q($query, array((string) ($_SESSION['ecommerce']['order_id'] ?? ''), (string) $free_order_item['ship_to_id'])) or output_error('Query failed.');
        
        // if a non-free order item could not be found, add error
        if (mysqli_num_rows($result) == 0) {
            $liveform->mark_error('free_order_item_error', lang(array('string' => '{var:1} is a free item that you have requested to ship with no non-free items. Free items must be shipped with at least one non-free item. You may update your order so that the item is shipped with at least one non-free item or you may remove the item.', 'vars' => array(h($free_order_item['short_description'] != '' ? $free_order_item['short_description'] : $free_order_item['product_name'])))));
        }
    }
    
    /* end: check that there are no free order items alone in a ship to */
    
    // If this is not a PayPal return and there is not already an error or notice and the total is
    // greater than the total that was last displayed to the user, then we need to show the correct
    // total to the user before the order is submitted, so add error.  We don't want to charge the
    // customer a greater amount than they saw.  We have to do this in case gift card balances have
    // changed or tax has changed (e.g. after billing info is submitted on express order screen).
    // If the new total is less than the old total, then we just allow the order to be submitted,
    // because the customer won't care that they are being charged less.  We use round() to resolve
    // any floating point issues with the total.  We use @ to suppress any warnings in PHP 7.1+.
    
    // Installment surcharges (Iyzipay): when the visitor picks an installment
    // plan, the gateway adds a per-plan fee BEFORE the actual capture. The
    // express-order JS updates the visible total + hidden total_with_surcharge
    // to reflect that fee — but if a stale form roundtrip drops the JS-side
    // update, the comparison below would reject a legit order ("total
    // changed" while the only delta is the installment surcharge the visitor
    // explicitly opted into). We allow the discrepancy when:
    //   • visitor selected installment >= 2 (their choice → server fee OK)
    //   • payment_method is the one that does installments (Credit/Debit Card)
    //   • the gateway is Iyzipay (only installment-charging path today)
    // The iyzico driver (pg_payment_iyzico_authorize_with()) works the fee
    // out itself from this server-side total and iyzico's installmentPrice
    // for the card, never from the total the form posted, so nothing is lost
    // by bypassing this preliminary check.
    $_pg_inst_chosen = (int)$liveform->get_field_value('installment');
    $_pg_inst_method = (string)$liveform->get_field_value('payment_method');
    $_pg_skip_total_check =
           $_pg_inst_chosen >= 2
        && $_pg_inst_method === 'Credit/Debit Card'
        && defined('ECOMMERCE_PAYMENT_GATEWAY') && ECOMMERCE_PAYMENT_GATEWAY === 'Iyzipay';

    if( !pg_payment_is_return_mode($_GET['mode'] ?? '')
        and ($liveform->check_form_errors() == FALSE)
        and ($liveform->check_form_notices() == FALSE)
        and !$_pg_skip_total_check
        and
        (
            (
                ($surcharge == 0)
                and (round($total) > round(@($liveform->get('total') * 100)))
            )
            or
            (
                ($surcharge > 0)
                and (round($total) > round(@($liveform->get('total_with_surcharge') * 100)))
            )
        )
    ) {
        $liveform->mark_error('total', lang('Sorry, the order was not accepted, because the total has changed. Can you please review the new total and submit the order again? This might happen if a cost has changed since you last reviewed the order.'));

        if ($surcharge == 0) {
            $old_total = $liveform->get('total');
        } else {
            $old_total = $liveform->get('total_with_surcharge');
        }
        log_activity(lang(array(
            'string' => 'Order (id: {var:1}, reference code: {var:2}) was not accepted because the new total ({var:3}) was greater than the old total ({var:4}) that the customer saw. The customer was asked to review the new total and submit the order again. This might be normal, if, for example, the customer stayed on the page for a long time before submitting the order, because a cost might have changed during that time period. Or, it might be normal if you are using an express order page with tax enabled, because the customer\'s tax is not known until an address is supplied. However, if this issue happens often, then it might indicate an issue that needs to be addressed.',
            'vars' => array(($_SESSION['ecommerce']['order_id'] ?? ''), $reference_code, prepare_amount($total / 100), prepare_amount($old_total))
        )));

    }

    // If hooks are enabled and there is pre-save hook code, then run it.
    if ((defined('PHP_REGIONS') and PHP_REGIONS) && ($pre_save_hook_code != '')) {
        eval(prepare_for_eval($pre_save_hook_code));
    }

    // A cash-on-delivery order is placed only with the code texted to its
    // billing phone number (the SMS Messages card's cod_code event). Checked
    // last, once nothing else stops the order, so a right code is not used
    // up by a submit that comes back for another reason; a code sent to
    // another number than the order's reads as expired.
    $sms_code_posted = (isset($_POST['sms_code']) && is_string($_POST['sms_code'])) ? $_POST['sms_code'] : '';
    $liveform->assign_field_value('sms_code', '');

    if (
        !pg_payment_is_return_mode($_GET['mode'] ?? '')
        && (($nonrecurring_transaction == TRUE) || ($recurring_transaction == TRUE))
        && ($liveform->check_form_errors() == FALSE)
        && ($liveform->check_form_notices() == FALSE)
        && pg_sms_cod_applies($liveform->get_field_value('payment_method'), $erp_on_account_requested, pg_sms_code_on('cod'))
    ) {
        $sms_code_refusal = pg_sms_code_verify('cod', 'order:' . (int) ($_SESSION['ecommerce']['order_id'] ?? 0), (string) $billing_phone_number, $sms_code_posted);

        if ($sms_code_refusal !== '') {
            $liveform->mark_error(($sms_code_refusal === 'number') ? 'billing_phone_number' : 'sms_code', pg_sms_code_sentence($sms_code_refusal));
        }
    }
    
    // if an error or notice exists, then send user back to previous screen
    if (($liveform->check_form_errors() == TRUE) || ($liveform->check_form_notices() == TRUE)) {
        // The checkout comes back with "Put it on my account" still chosen.
        if ($erp_on_account_requested) {
            $liveform->assign_field_value('payment_method', 'On Account');
        }

        header('Location: ' . URL_SCHEME . HOSTNAME . PATH . get_page_name($page_id));
        exit();
    }
    
    // if a nonrecurring transaction or recurring transaction is required, then prepare for communication with payment service differently based on selected payment method
    if (($nonrecurring_transaction == TRUE) || ($recurring_transaction == TRUE)) {
        switch ($liveform->get_field_value('payment_method')) {
            // is credit/debit card payment method was selected, process transaction through a payment gateway if one is selected
            case 'Credit/Debit Card':
                switch (ECOMMERCE_PAYMENT_GATEWAY) {
                    case 'Authorize.Net':
                        // if cURL is not installed, then prepare error and send user back to previous screen
                        if (function_exists('curl_init') == false) {
                            $liveform->mark_error('payment_gateway', lang('This website cannot communicate with the payment gateway. The administrator of this website should install cURL.'));
                        
                            header('Location: ' . URL_SCHEME . HOSTNAME . PATH . get_page_name($page_id));
                            exit();
                        }
                    
                        break;
                    
                    case 'ClearCommerce':
                        // if cURL is not installed, then prepare error and send user back to previous screen
                        if (function_exists('curl_init') == false) {
                            $liveform->mark_error('payment_gateway', lang('This website cannot communicate with the payment gateway. The administrator of this website should install cURL.'));
                        
                            header('Location: ' . URL_SCHEME . HOSTNAME . PATH . get_page_name($page_id));
                            exit();
                        }
                        
                        if (ECOMMERCE_PAYMENT_GATEWAY_MODE == 'test') {
                            $clearcommerce_mode = 'Y';
                            $payment_gateway_host = 'https://test5x.clearcommerce.com:11500';
                        } else {
                            $clearcommerce_mode = 'P';
                            $payment_gateway_host = 'https://xmlic.payfuse.com';
                        }
                        
                        // if transaction type is set to Authorize, then prepare type to send
                        if (ECOMMERCE_PAYMENT_GATEWAY_TRANSACTION_TYPE == 'Authorize') {
                            $clearcommerce_type = 'PreAuth';
                            
                        // else transaction type is Authorize & Capture, so prepare type to send
                        } else {
                            $clearcommerce_type = 'Auth';
                        }

                        // Prepare ClearCommerce currency code.
                        switch (BASE_CURRENCY_CODE) {
                            default:
                            case 'USD':
                                $clearcommerce_currency_code = '840';
                                break;

                            case 'AUD':
                                $clearcommerce_currency_code = '036';
                                break;

                            case 'CAD':
                                $clearcommerce_currency_code = '124';
                                break;

                            case 'EUR':
                                $clearcommerce_currency_code = '978';
                                break;

                            case 'GBP':
                                $clearcommerce_currency_code = '826';
                                break;
                        }
                        
                        break;
                        
                    case 'First Data Global Gateway':
                        // if cURL is not installed, then prepare error and send user back to previous screen
                        if (function_exists('curl_init') == false) {
                            $liveform->mark_error('payment_gateway', lang('This website cannot communicate with the payment gateway. The administrator of this website should install cURL.'));

                            header('Location: ' . URL_SCHEME . HOSTNAME . PATH . get_page_name($page_id));
                            exit();
                        }
                        
                        if (ECOMMERCE_PAYMENT_GATEWAY_MODE == 'test') {
                            $first_data_global_gateway_mode = 'GOOD';
                        } else {
                            $first_data_global_gateway_mode = 'LIVE';
                        }
                        
                        // if transaction type is set to Authorize, then prepare type to send
                        if (ECOMMERCE_PAYMENT_GATEWAY_TRANSACTION_TYPE == 'Authorize') {
                            $first_data_global_gateway_type = 'PREAUTH';
                            
                        // else transaction type is Authorize & Capture, so prepare type to send
                        } else {
                            $first_data_global_gateway_type = 'SALE';
                        }
                        
                        break;
                    
                    case 'PayPal Payflow Pro':
                        $payment_gateway_communication_method = '';
                        
                        // if cURL is installed, then use it
                        if (function_exists('curl_init') == true) {
                            $payment_gateway_communication_method = 'curl';
                            
                        // else cURL is not installed, so check for PayPal Payflow Pro Client
                        } else {
                            // if this server is on Windows, use COM object to connect to PayPal Payflow Pro
                            if (mb_strtoupper(mb_substr(PHP_OS, 0, 3)) == 'WIN') {
                                $payflowpro = new COM("PFProCOMControl.PFProCOMControl.1");
                                
                                // if connection was successful, set communication method
                                if ($payflowpro) {
                                    $payment_gateway_communication_method = 'payflow pro com object';
                                }
                                
                            // else this server is not on Windows, so check for PayPal Payflow Pro Client for Unix (i.e. PHP pfpro extension) is installed
                            } else {
                                // if connection was successful, set communication method
                                if (function_exists('pfpro_process') == true) {
                                    $payment_gateway_communication_method = 'pfpro extension';
                                }
                            }
                        }
                        
                        // set different payment gateway settings based on communication method
                        switch ($payment_gateway_communication_method) {
                            case 'curl':
                                // set payment gateway host
                                if (ECOMMERCE_PAYMENT_GATEWAY_MODE == 'test') {
                                    $payment_gateway_host = 'https://pilot-payflowpro.paypal.com';
                                } else {
                                    $payment_gateway_host = 'https://payflowpro.paypal.com';
                                }
                                
                                break;
                                
                            case 'payflow pro com object':
                            case 'pfpro extension':
                                // set the Pay Flow Pro certificate path so that PHP can find the certificate file (f73e89fd.0)
                                putenv('PFPRO_CERT_PATH=./certs/');
                                
                                // set payment gateway host
                                if (ECOMMERCE_PAYMENT_GATEWAY_MODE == 'test') {
                                    $payment_gateway_host = 'test-payflow.verisign.com';
                                } else {
                                    $payment_gateway_host = 'payflow.verisign.com';
                                }
                                
                                break;
                        }
                        
                        // if there is a communication error with PayPal Payflow Pro, then prepare error and send user back to previous screen
                        if (!$payment_gateway_communication_method) {
                            $liveform->mark_error('payment_gateway', lang('This website cannot communicate with the payment gateway. The administrator of this website should install cURL or the Payflow Pro Client (i.e. PHP pfpro extension for Unix, Payflow Pro COM Object for Windows).'));
                            header('Location: ' . URL_SCHEME . HOSTNAME . PATH . get_page_name($page_id));
                            exit();
                        }
                        
                        // if a user was entered for payment gateway, then use user
                        if (ECOMMERCE_PAYPAL_PAYFLOW_PRO_USER) {
                            $payment_gateway_user = ECOMMERCE_PAYPAL_PAYFLOW_PRO_USER;
                            
                        // else a user was not entered for payment gateway, so use merchant login
                        } else {
                            $payment_gateway_user = ECOMMERCE_PAYPAL_PAYFLOW_PRO_MERCHANT_LOGIN;
                        }
                        
                        break;
                        
                    // if PayPal Payments Pro payment method was selected
                    case 'PayPal Payments Pro':
                        // if cURL is not installed, then prepare error and send user back to previous screen
                        if (function_exists('curl_init') == false) {
                            $liveform->mark_error('payment_gateway', lang('This website cannot communicate with the payment gateway. The administrator of this website should install cURL.'));
                            header('Location: ' . URL_SCHEME . HOSTNAME . PATH . get_page_name($page_id));
                            exit();
                        }
                        
                        // if the payment gateway mode is set to test, set the payment gatway host to the sandbox posting url.
                        if (ECOMMERCE_PAYMENT_GATEWAY_MODE == 'test') {
                            $payment_gateway_host = 'https://api-3t.sandbox.paypal.com/nvp';
                            
                        // else, the payment gameway mode is set to live, so use the live payment gateway host.
                        } else {
                            $payment_gateway_host = 'https://api-3t.paypal.com/nvp';
                        }
                        
                        // Setup the credit card type required for the payment gateway.
                        switch ($card_type) {
                            case 'American Express':
                                $credit_card_type = 'Amex';
                                break;
                            
                            case 'Diners Club':
                                $credit_card_type = 'Diners Club';
                                break;
                            
                            case 'Discover Card':
                                $credit_card_type = 'Discover';
                                break;
                            
                            case 'MasterCard':
                                $credit_card_type = 'MasterCard';
                                break;
                            
                            case 'Visa':
                                $credit_card_type = 'Visa';
                                break;
                        }
                        
                        break;
                        
                    case 'Sage':
                        // if cURL is not installed, then prepare error and send user back to previous screen
                        if (function_exists('curl_init') == false) {
                            $liveform->mark_error('payment_gateway', lang('This website cannot communicate with the payment gateway. The administrator of this website should install cURL.'));

                            header('Location: ' . URL_SCHEME . HOSTNAME . PATH . get_page_name($page_id));
                            exit();
                        }
                        
                        // if transaction type is set to Authorize, then prepare type to send
                        if (ECOMMERCE_PAYMENT_GATEWAY_TRANSACTION_TYPE == 'Authorize') {
                            $sage_transaction_type = '02';
                            
                        // else transaction type is Authorize & Capture, so prepare type to send
                        } else {
                            $sage_transaction_type = '01';
                        }
                        
                        break;

                    case 'Stripe':
                        // if cURL is not installed, then prepare error and send user back to previous screen
                        if (function_exists('curl_init') == false) {
                            $liveform->mark_error('payment_gateway', lang('This website cannot communicate with the payment gateway. The administrator of this website should install cURL.'));

                            header('Location: ' . URL_SCHEME . HOSTNAME . PATH . get_page_name($page_id));
                            exit();
                        }
                        
                        break;

                    case 'Iyzipay':
                        // if cURL is not installed, then prepare error and send user back to previous screen
                        if (function_exists('curl_init') == false) {
                            $liveform->mark_error('payment_gateway', lang('This website cannot communicate with the payment gateway. The administrator of this website should install cURL.'));

                            header('Location: ' . URL_SCHEME . HOSTNAME . PATH . get_page_name($page_id));
                            exit();
                        }
                        
                        break;
                }
                
                break;
                
            // if PayPal payment method was selected
            case 'PayPal Express Checkout':
                // if cURL is not installed, then prepare error and send user back to previous screen
                if (function_exists('curl_init') == false) {
                    $liveform->mark_error('paypal_express_checkout', lang('This website cannot communicate with PayPal. The administrator of this website should install cURL.'));

                    header('Location: ' . URL_SCHEME . HOSTNAME . PATH . get_page_name($page_id));
                    exit();
                }
                
                break;

            // Nothing needs to be prepared for these payment methods.
            case 'Pay With Iyzico':
            case 'Offline Payment':
                break;

            // The allow-list above already rejects any other value; refuse to continue rather than
            // completing the order without a charge. A redirect driver's method is paid by its
            // driver below.
            default:
                if (isset(pg_payment_driver_methods(true)[$liveform->get_field_value('payment_method')])) {
                    break;
                }

                $liveform->mark_error('payment_method', lang('The selected payment method is not available.'));
                header('Location: ' . URL_SCHEME . HOSTNAME . PATH . get_page_name($page_id));
                exit();
        }
    }
    
    // A payment a driver serves (includes/payments/) is taken by the driver,
    // and the gateway code below does not run for it. The order records the
    // driver's answer in the same variables the gateway code sets.
    $payment_driver = ($nonrecurring_transaction == TRUE)
        ? pg_payment_checkout_driver((string) $liveform->get_field_value('payment_method'), (string) ECOMMERCE_PAYMENT_GATEWAY, (string) ($_GET['mode'] ?? ''))
        : '';

    if ($payment_driver !== '') {
        $payment_order = db_item("SELECT * FROM orders WHERE id = '" . (int) ($_SESSION['ecommerce']['order_id'] ?? 0) . "'");
        $payment_order = is_array($payment_order) ? $payment_order : array();
        $payment_return_mode = pg_payment_return_mode($payment_driver);
        $payment_return = ($payment_return_mode !== '') && (($_GET['mode'] ?? '') === $payment_return_mode);

        if ($payment_return) {
            $payment_answer = pg_payment_call($payment_driver, 'complete', array(
                $payment_order,
                $total,
                $_POST,
                $_SESSION['ecommerce']['payment_state'][$payment_driver] ?? array(),
            ));
        } else {
            $payment_answer = pg_payment_call($payment_driver, 'authorize', array($payment_order, $total, array(
                'card_number'              => isset($card_number) ? $card_number : '',
                'expiration_month'         => isset($expiration_month) ? $expiration_month : '',
                'expiration_year'          => isset($expiration_year) ? $expiration_year : '',
                'card_verification_number' => $liveform->get_field_value('card_verification_number'),
                'installment'              => (int) $liveform->get_field_value('installment'),
                'threeds'                  => isset($_POST['threedsecure']),
                'recurring'                => ($recurring_transaction === TRUE),
                'subtotal'                 => $subtotal,
                'discount'                 => $discount,
                // What the instalment charge is settled against: it goes the
                // fewest kurus lower where it would leave a rate's total out
                // of reach, as the shipping does (pg_order_installment_charge()).
                'settle_rates'             => pg_order_charges_settle_rates(),
                'charged_lines'            => $charged_lines,
                'charges'                  => array('shipping' => (int) $shipping, 'surcharge' => (int) $surcharge),
                'lines'                    => $products,
                'ship_to'                  => pg_payment_order_ship_to((int) ($_SESSION['ecommerce']['order_id'] ?? 0)),
                'customer_id'              => $user_id,
                'ip'                       => (string) ($_SERVER['REMOTE_ADDR'] ?? ''),
                'basket_id'                => (string) $page_id,
                'return_url'               => ($payment_return_mode !== '')
                    ? URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/' . $type_value . '.php?mode=' . $payment_return_mode . '&page_id=' . (int) $page_id
                    : '',
                // The payment service's token for a card typed into its own fields.
                'token'                    => (isset($_POST['payment_token']) && is_string($_POST['payment_token'])) ? $_POST['payment_token'] : '',
            )));
        }

        // What the driver keeps for the visitor's return stays in the session,
        // never in the page; an answer without it ends the wait.
        if ($payment_answer['state'] !== array()) {
            $_SESSION['ecommerce']['payment_state'][$payment_driver] = $payment_answer['state'];
        } else {
            unset($_SESSION['ecommerce']['payment_state'][$payment_driver]);
        }

        foreach ($payment_answer['log'] as $payment_log_line) {
            log_activity($payment_log_line);
        }

        if (!$payment_answer['success']) {
            $liveform->mark_error('payment_gateway', h($payment_answer['error']));
            header('Location: ' . URL_SCHEME . HOSTNAME . PATH . get_page_name($page_id));
            exit();
        }

        // The bank's 3-D Secure page, or the service's own payment page.
        if ($payment_answer['status'] === 'redirect') {
            // The order keeps the payment the service now finishes, so the
            // service's own message about it finds the order even when the
            // visitor never returns.
            $payment_started = pg_payment_started_order_values($payment_answer, $total);

            if ($payment_started !== null) {
                db("UPDATE orders
                    SET
                        transaction_id = '" . e($payment_started['transaction_id']) . "',
                        total = '" . $payment_started['total'] . "'
                    WHERE (id = '" . (int) ($_SESSION['ecommerce']['order_id'] ?? 0) . "') AND (status = 'incomplete')");
            }

            if ($payment_answer['html'] !== '') {
                echo $payment_answer['html'];
            } else {
                header('Location: ' . $payment_answer['redirect_url']);
            }

            exit();
        }

        $payment_totals = pg_payment_checkout_totals($payment_answer, $total);
        $total = $payment_totals['total'];
        $installment_charge = $payment_totals['installment_charge'];
        $total_witout_installment_charge = $payment_totals['total_without_installment_charge'];
        $payment_installment = $payment_totals['payment_installment'];
        $transaction_id = $payment_answer['transaction_id'];
        $payment_status = $payment_answer['status'];

        if ($liveform->get_field_value('payment_method') == 'Credit/Debit Card') {
            $card_type = $payment_answer['card_brand'];

            // The order keeps what the service says of a card the site never
            // saw: its last four digits (masked like any card number below)
            // and its expiry.
            if ($payment_hosted_card_driver !== '') {
                $card_number = ($payment_answer['card_last4'] !== '') ? str_repeat('0', 12) . $payment_answer['card_last4'] : '';
                $expiration_month = $payment_answer['card_exp_month'];
                $expiration_year = $payment_answer['card_exp_year'];

            // The bank's 3-D Secure return posts no card and the card checks
            // above do not run for it: the order keeps no card number.
            } elseif ($payment_return) {
                $card_number = '';
                $expiration_month = '';
                $expiration_year = '';
            }
        }
    }

    // if a non-recurring transaction is required, then process transaction through payment gateway, if there is one
    if (($nonrecurring_transaction == TRUE) && ($payment_driver === '')) {
        
        // process transaction differently based on selected payment method
        switch ($liveform->get_field_value('payment_method')) {
            // is credit/debit card payment method was selected, process transaction through a payment gateway if one is selected
            case 'Credit/Debit Card':
                switch (ECOMMERCE_PAYMENT_GATEWAY) {
                    case 'Authorize.Net':
                        // If the Authorize.Net URL has been set in the config.php file
                        // (for an Authorize.Net emulator service), then use it.
                        if ((defined('AUTHORIZENET_URL') == true) && (AUTHORIZENET_URL != '')) {
                            $payment_gateway_host = AUTHORIZENET_URL;
                            
                        // Otherwise use the standard Authorize.Net url.
                        } else {
                            $payment_gateway_host = 'https://secure2.authorize.net/gateway/transact.dll';
                        }

                        if (ECOMMERCE_PAYMENT_GATEWAY_MODE == 'test') {
                            $x_test_request = 'TRUE';
                        } else {
                            $x_test_request = 'FALSE';
                        }
                        
                        // if transaction type is set to Authorize, then prepare x_type to send
                        if (ECOMMERCE_PAYMENT_GATEWAY_TRANSACTION_TYPE == 'Authorize') {
                            $x_type = 'AUTH_ONLY';
                            
                        // else transaction type is Authorize & Capture, so prepare x_type to send
                        } else {
                            $x_type = 'AUTH_CAPTURE';
                        }
                        
                        if ($tax_exempt == 1) {
                            $x_tax_exempt = 'TRUE';
                        } else {
                            $x_tax_exempt = 'FALSE';
                        }
                        
                        $transaction_values =
                            array(
                                'x_version' => '3.1',
                                'x_login' => ECOMMERCE_AUTHORIZENET_API_LOGIN_ID,
                                'x_tran_key' => ECOMMERCE_AUTHORIZENET_TRANSACTION_KEY,
                                'x_test_request' => $x_test_request,
                                'x_delim_data' => 'TRUE',
                                'x_delim_char' => ',',
                                'x_relay_response' => 'FALSE',
                                'x_type' => $x_type,
                                'x_first_name' => $billing_first_name,
                                'x_last_name' => $billing_last_name,
                                'x_company' => $billing_company,
                                'x_email' => $billing_email_address,
                                'x_address' => $billing_address_1 . ' ' . $billing_address_2,
                                'x_city' => $billing_city,
                                'x_state' => $billing_state,
                                'x_zip' => $billing_zip_code,
                                'x_phone' => $billing_phone_number,
                                'x_fax' => $billing_fax_number,
                                'x_po_num' => $po_number,
                                'x_tax_exempt' => $x_tax_exempt,
                                'x_customer_ip' => $_SERVER['REMOTE_ADDR'],
                                'x_amount' => sprintf("%01.2lf", $total / 100),
                                'x_currency_code' => BASE_CURRENCY_CODE,
                                'x_tax' => sprintf("%01.2lf", $tax / 100),
                                'x_card_num' => $card_number,
                                'x_exp_date' => $expiration_month . '/' . $expiration_year,
                                'x_card_code' => $liveform->get_field_value('card_verification_number'),
                                'x_solution_id' => 'A1000013');
                        
                        $post_data = '';
                        
                        foreach ($transaction_values as $name => $value) {
                            if ($post_data) {
                                $post_data .= '&';
                            }
                            
                            $post_data .= $name . '=' . urlencode($value);
                        }
                        
                        $ch = curl_init();
                        
                        curl_setopt($ch, CURLOPT_URL, $payment_gateway_host);
                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                        curl_setopt($ch, CURLOPT_TIMEOUT, 300);
                        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 0);
                        pg_curl_tls($ch);
                        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
                        curl_setopt($ch, CURLOPT_FORBID_REUSE, true);
                        curl_setopt($ch, CURLOPT_POST, 1);
                        
                        // if there is a proxy address, then send cURL request through proxy
                        if (PROXY_ADDRESS != '') {
                            curl_setopt($ch, CURLOPT_HTTPPROXYTUNNEL, true);
                            curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_HTTP);
                            curl_setopt($ch, CURLOPT_PROXY, PROXY_ADDRESS);
                        }
                        
                        $response_data = curl_exec($ch);
                        
                        $curl_errno = curl_errno($ch);
                        $curl_error = curl_error($ch);
                        
                        unset($ch);
                        
                        $response = explode(',', $response_data);
                        
                        // increment the keys of the array by one, so that the keys match up with Authorize.net numbers
                        array_unshift($response, '');

                        // If transaction failed, prepare error.
                        // We had to update the test below to change "trim($response[1]) != 1",
                        // "to trim($response[1]) !== '1'", because Authorize.Net started returning
                        // "1.0" for the response code when there was an error.  That is strange,
                        // because "1" means success, which was causing our previous PHP comparison to match.
                        // Other codes (e.g. "2", "3", etc.)  mean failure, so we have no idea
                        // why they decided to start returning "1.0" for a failure.  "1.0" is
                        // not mentioned in their documentation as being a valid code.
                        if ((count($response) == 1) || (trim($response[1]) !== '1')) {
                            // if there was a response from the payment gateway, then use response text in error
                            if ($response[4]) {
                                $payment_gateway_error_message = lang('Error message from payment gateway: ') . $response[4];
                            // else there was no response, so there was a communication problem
                            } else {
                                $payment_gateway_error_message = lang('An error occurred while trying to communicate with the payment gateway.');
                                
                                // if there was a cURL error, add error number and error message to payment gateway error message
                                if ($curl_errno) {
                                    $payment_gateway_error_message .= ' ' . lang('cURL Error Number:') . ' ' . $curl_errno . '. ' . lang('cURL Error Message:') . ' ' . $curl_error . '.';
                                }
                            }

                            log_activity($payment_gateway_error_message);
                            
                            $liveform->mark_error('payment_gateway', h($payment_gateway_error_message));

                            // send user back to previous screen
                            header('Location: ' . URL_SCHEME . HOSTNAME . PATH . get_page_name($page_id));
                            exit();
                        }
                        
                        $transaction_id = $response[7];
                        $authorization_code = $response[5];
                        
                        break;

                    // We originally added the round function to the total below
                    // in order to solve a problem where there might be a decimal value
                    // for the total, which contains cents (e.g. 1099.5 cents), when an
                    // order discount was applied.  ClearCommerce would return an error then.
                    // We have since fixed the issue so that the discount in cents should
                    // never contain a decimal. This means that the round function is probably
                    // not needed anymore however we have decided to leave it for now, in case it is.
                    
                    case 'ClearCommerce':
                        $transaction_xml =
                            '<EngineDocList>
                                <DocVersion>1.0</DocVersion>
                                <EngineDoc>
                                    <ContentType>OrderFormDoc</ContentType>
                                    <User>
                                        <Name>' . h(ECOMMERCE_CLEARCOMMERCE_USER_ID) . '</Name>
                                        <Password>' . h(ECOMMERCE_CLEARCOMMERCE_PASSWORD) . '</Password>
                                        <Alias>' . h(ECOMMERCE_CLEARCOMMERCE_CLIENT_ID) . '</Alias>
                                    </User>
                                    <Instructions>
                                        <Pipeline>Payment</Pipeline>
                                    </Instructions>
                                    <OrderFormDoc>
                                        <Mode>' . $clearcommerce_mode . '</Mode>
                                        <Consumer>
                                            <PaymentMech>
                                                <CreditCard>
                                                    <Number>' . $card_number . '</Number>
                                                    <Expires DataType="ExpirationDate" Locale="840">' . h($expiration_month) . '/' . h($expiration_year) . '</Expires>
                                                    <Cvv2Val>' . h($liveform->get_field_value('card_verification_number')) . '</Cvv2Val>
                                                    <Cvv2Indicator>1</Cvv2Indicator>
                                                </CreditCard>
                                            </PaymentMech>
                                            <BillTo>
                                                <Location>
                                                    <Address>
                                                        <Name>' . h($billing_first_name) . ' ' . h($billing_last_name) . '</Name>
                                                        <Company>' . h($billing_company) . '</Company>
                                                        <Street1>' . h($billing_address_1) . '</Street1>
                                                        <Street2>' . h($billing_address_2) . '</Street2>
                                                        <City>' . h($billing_city) . '</City>
                                                        <StateProv>' . h($billing_state) . '</StateProv>
                                                        <PostalCode>' . h($billing_zip_code) . '</PostalCode>
                                                        <Country>' . get_country_number($billing_country) . '</Country>
                                                    </Address>
                                                </Location>
                                            </BillTo>
                                        </Consumer>
                                        <Transaction>
                                            <Type>' . $clearcommerce_type . '</Type>
                                            <CurrentTotals>
                                                <Totals>
                                                    <Total DataType="Money" Currency="' . $clearcommerce_currency_code . '">' . round($total) . '</Total>
                                                </Totals>
                                            </CurrentTotals>
                                        </Transaction>
                                    </OrderFormDoc>
                                </EngineDoc>
                            </EngineDocList>';
                        
                        $ch = curl_init();
                        
                        curl_setopt($ch, CURLOPT_URL, $payment_gateway_host);
                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                        curl_setopt($ch, CURLOPT_TIMEOUT, 300);
                        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 0);
                        pg_curl_tls($ch);
                        curl_setopt($ch, CURLOPT_POSTFIELDS, 'CLRCMRC_XML=' . urlencode($transaction_xml));
                        curl_setopt($ch, CURLOPT_FORBID_REUSE, true);
                        curl_setopt($ch, CURLOPT_POST, 1);
                        
                        // if there is a proxy address, then send cURL request through proxy
                        if (PROXY_ADDRESS != '') {
                            curl_setopt($ch, CURLOPT_HTTPPROXYTUNNEL, true);
                            curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_HTTP);
                            curl_setopt($ch, CURLOPT_PROXY, PROXY_ADDRESS);
                        }
                        
                        $response_data = curl_exec($ch);
                        
                        $curl_errno = curl_errno($ch);
                        $curl_error = curl_error($ch);
                        
                        unset($ch);
                        
                        // get CcErrCode
                        preg_match('/<CcErrCode DataType="S32">(.*?)<\/CcErrCode>/', $response_data, $matches);
                        $clearcommerce_CcErrCode = $matches[1];
                        
                        // get CcReturnMsg
                        preg_match('/<CcReturnMsg DataType="String">(.*?)<\/CcReturnMsg>/', $response_data, $matches);
                        $clearcommerce_CcReturnMsg = $matches[1];
                        
                        // get Text
                        preg_match('/<Text DataType="String">(.*?)<\/Text>/', $response_data, $matches);
                        $clearcommerce_Text = $matches[1];
                        
                        // if transaction failed, prepare error
                        if ($clearcommerce_CcErrCode != '1') {
                            // if there was a cErrCode value, then use CcReturnMsg for error message
                            if ($clearcommerce_CcErrCode != '') {
                                $payment_gateway_error_message = lang('Error message from payment gateway: ') . $clearcommerce_CcReturnMsg;
                                
                            // else if there was a Text value, then use value for error message
                            } elseif ($clearcommerce_Text != '') {
                                $payment_gateway_error_message = lang('Error message from payment gateway: ') . $clearcommerce_Text;
                                
                            // else there was no response, so there was a communication problem
                            } else {
                                $payment_gateway_error_message = lang('An error occurred while trying to communicate with the payment gateway.');
                                
                                // if there was a cURL error, add error number and error message to payment gateway error message
                                if ($curl_errno) {
                                    $payment_gateway_error_message .= ' ' . lang('cURL Error Number:') . ' ' . $curl_errno . '. ' . lang('cURL Error Message:') . ' ' . $curl_error . '.';
                                }
                            }

                            log_activity($payment_gateway_error_message);
                            
                            $liveform->mark_error('payment_gateway', h($payment_gateway_error_message));

                            // send user back to previous screen
                            header('Location: ' . URL_SCHEME . HOSTNAME . PATH . get_page_name($page_id));
                            exit();
                        }
                        
                        // get transaction id
                        preg_match('/<TransactionId DataType="String">(.*?)<\/TransactionId>/', $response_data, $matches);
                        $transaction_id = $matches[1];
                        
                        // get authorization code
                        preg_match('/<AuthCode DataType="String">(.*?)<\/AuthCode>/', $response_data, $matches);
                        $authorization_code = $matches[1];
                        
                        break;
                        
                    case 'First Data Global Gateway':
                        // Get the numerical portion of the 1st billing address.
                        preg_match('/[0-9]+/', $billing_address_1, $matches);
                        $avs_address_number = $matches[0];
                        
                        // Build the First Data Global Gateway XML Packet
                        // They do not appear to support foreign currencies (at least at the API level),
                        // so we are not passing currency info.
                        $transaction_xml =
                            '<order>
                                <billing>
                                    <name>' . h($billing_first_name) . ' ' . h($billing_last_name) . '</name>
                                    <company>' . h($billing_company) . '</company>
                                    <address1>' . h($billing_address_1) . '</address1>
                                    <address2>' . h($billing_address_2) . '</address2>
                                    <city>' . h($billing_city) . '</city>
                                    <state>' . h($billing_state) . '</state>
                                    <zip>' . h($billing_zip_code) . '</zip>
                                    <country>' . h($billing_country) . '</country>
                                    <phone>' . h($billing_phone_number) . '</phone>
                                    <fax>' . h($billing_fax_number) . '</fax>
                                    <email>' . h($billing_email_address) . '</email>
                                    <addrnum>' . $avs_address_number . '</addrnum>
                                </billing>
                                <transactiondetails>
                                    <ip>' . h($_SERVER['REMOTE_ADDR']) . '</ip>
                                </transactiondetails>
                                <orderoptions>
                                    <ordertype>' . $first_data_global_gateway_type . '</ordertype>
                                    <result>' . $first_data_global_gateway_mode . '</result>
                                </orderoptions>
                                <payment>
                                    <subtotal>' . $subtotal / 100 . '</subtotal>
                                    <tax>' . $tax / 100 . '</tax>
                                    <shipping>' . $shipping / 100 . '</shipping>
                                    <chargetotal>' . sprintf("%01.2lf", $total / 100) . '</chargetotal>
                                </payment>
                                <creditcard>
                                    <cardnumber>' . $card_number . '</cardnumber>
                                    <cardexpmonth>' . h($expiration_month) . '</cardexpmonth>
                                    <cardexpyear>' .  h(mb_substr($expiration_year, -2)) . '</cardexpyear>
                                    <cvmvalue>' .  h($liveform->get_field_value('card_verification_number')) . '</cvmvalue>
                                    <cvmindicator>provided</cvmindicator>
                                </creditcard>
                                <merchantinfo>
                                    <configfile>' . h(ECOMMERCE_FIRST_DATA_GLOBAL_GATEWAY_STORE_NUMBER) . '</configfile>
                                </merchantinfo>
                            </order>';
                        
                        $ch = curl_init();
                        
                        curl_setopt($ch, CURLOPT_URL, 'https://secure.linkpt.net:1129');
                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                        curl_setopt($ch, CURLOPT_TIMEOUT, 300);
                        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 0);
                        // Setup an SSL connection using the supplied .pem file from First Data Global Gateway
                        curl_setopt($ch, CURLOPT_SSLCERT, FILE_DIRECTORY_PATH . '/' . ECOMMERCE_FIRST_DATA_GLOBAL_GATEWAY_PEM_FILE_NAME);
                        pg_curl_tls($ch);
                        curl_setopt($ch, CURLOPT_POSTFIELDS, $transaction_xml);
                        curl_setopt($ch, CURLOPT_FORBID_REUSE, true);
                        curl_setopt($ch, CURLOPT_POST, 1);
                        
                        // if there is a proxy address, then send cURL request through proxy
                        if (PROXY_ADDRESS != '') {
                            curl_setopt($ch, CURLOPT_HTTPPROXYTUNNEL, true);
                            curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_HTTP);
                            curl_setopt($ch, CURLOPT_PROXY, PROXY_ADDRESS);
                        }
                        
                        $response_data = curl_exec($ch);

                        $curl_errno = curl_errno($ch);
                        $curl_error = curl_error($ch);
                        
                        unset($ch);

                        // get r_approved
                        preg_match('/<r_approved>(.*?)<\/r_approved>/', $response_data, $matches);
                        $first_data_global_gateway_approved = $matches[1];
                        
                        // get r_error
                        preg_match('/<r_error>(.*?)<\/r_error>/', $response_data, $matches);
                        $first_data_global_gateway_error = $matches[1];
                        
                        // get r_message
                        preg_match('/<r_message>(.*?)<\/r_message>/', $response_data, $matches);
                        $first_data_global_gateway_message = $matches[1];
                        
                        // if transaction failed, prepare error
                        if ($first_data_global_gateway_approved != 'APPROVED') {
                            // if there was a cErrCode value, then use CcReturnMsg for error message
                            if ($first_data_global_gateway_error != '') {
                                $payment_gateway_error_message = lang('Error message from payment gateway: ') . $first_data_global_gateway_error;
                            
                            // else if there was a Text value, then use value for error message
                            } elseif ($first_data_global_gateway_message != '') {
                                $payment_gateway_error_message = lang('Error message from payment gateway: ') . $first_data_global_gateway_message;
                            
                            // else there was no response, so there was a communication problem
                            } else {
                                $payment_gateway_error_message = lang('An error occurred while trying to communicate with the payment gateway.');
                            
                                // if there was a cURL error, add error number and error message to payment gateway error message
                                if ($curl_errno) {
                                    $payment_gateway_error_message .= ' ' . lang('cURL Error Number:') . ' ' . $curl_errno . '. ' . lang('cURL Error Message:') . ' ' . $curl_error . '.';
                                }
                            }
                        
                            log_activity($payment_gateway_error_message);
                        
                            $liveform->mark_error('payment_gateway', h($payment_gateway_error_message));
                        
                            // send user back to previous screen
                            header('Location: ' . URL_SCHEME . HOSTNAME . PATH . get_page_name($page_id));
                            exit();
                        }
                        
                        // get transaction id
                        preg_match('/<r_ordernum>(.*?)<\/r_ordernum>/', $response_data, $matches);
                        $transaction_id = $matches[1];
                        
                        // We dont need to get the authorization code
                        $authorization_code = '';
                        
                        break;
                    
                    case 'PayPal Payflow Pro':
                        // if transaction type is set to Authorize, then prepare TRXTYPE to send
                        if (ECOMMERCE_PAYMENT_GATEWAY_TRANSACTION_TYPE == 'Authorize') {
                            $payflow_pro_TRXTYPE = 'A';
                            
                        // else transaction type is Authorize & Capture, so prepare TRXTYPE to send
                        } else {
                            $payflow_pro_TRXTYPE = 'S';
                        }
                        
                        $transaction_values = array(
                            'PARTNER' => ECOMMERCE_PAYPAL_PAYFLOW_PRO_PARTNER,
                            'VENDOR'  => ECOMMERCE_PAYPAL_PAYFLOW_PRO_MERCHANT_LOGIN,
                            'USER'    => $payment_gateway_user,
                            'PWD'     => ECOMMERCE_PAYPAL_PAYFLOW_PRO_PASSWORD,
                            'TRXTYPE' => $payflow_pro_TRXTYPE,
                            'TENDER'  => 'C',
                            'AMT'     => sprintf("%01.2lf", $total / 100),
                            'CURRENCY' => BASE_CURRENCY_CODE,
                            'TAXAMT'  => sprintf("%01.2lf", $tax / 100),
                            'ACCT'    => $card_number,
                            'EXPDATE' => $expiration_month . mb_substr($expiration_year, -2),
                            'CVV2'    => $liveform->get_field_value('card_verification_number'),
                            'FIRSTNAME' => $billing_first_name,
                            'LASTNAME' => $billing_last_name,
                            'COMPANYNAME' => $billing_company,
                            'EMAIL' =>   $billing_email_address,
                            'STREET'  => $billing_address_1 . ' ' . $billing_address_2,
                            'CITY'    => $billing_city,
                            'STATE'    => $billing_state,
                            'ZIP'     => $billing_zip_code,
                            'BILLTOCOUNTRY' => $billing_country);
                        
                        // process transaction differently based on communication method
                        switch ($payment_gateway_communication_method) {
                            case 'curl':
                                $current_time_parts = gettimeofday();
                                $request_id = md5($current_time_parts['sec'] . '.' . $current_time_parts['usec']);
                                
                                $headers =
                                    array(
                                        'Content-Type: text/namevalue',
                                        'X-VPS-VIT-Client-Certification-Id: 978cd78328cf44d58863f98e863f64dd',
                                        'X-VPS-Request-ID: ' . $request_id
                                    );
                                
                                $post_data = '';
                                
                                foreach ($transaction_values as $name => $value) {
                                    if ($post_data) {
                                        $post_data .= '&';
                                    }
                                    
                                    $value = str_replace('"', '', $value);
                                    
                                    $post_data .= $name . '[' . strlen($value) . ']' . '=' . $value;
                                }
                                
                                $ch = curl_init();
                                
                                curl_setopt($ch, CURLOPT_URL, $payment_gateway_host);
                                curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                                curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/4.0 (compatible; MSIE 5.01; Windows NT 5.0)');
                                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                                curl_setopt($ch, CURLOPT_TIMEOUT, 300);
                                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 0);
                                pg_curl_tls($ch);
                                curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
                                curl_setopt($ch, CURLOPT_FORBID_REUSE, true);
                                curl_setopt($ch, CURLOPT_POST, 1);
                                
                                // if there is a proxy address, then send cURL request through proxy
                                if (PROXY_ADDRESS != '') {
                                    curl_setopt($ch, CURLOPT_HTTPPROXYTUNNEL, true);
                                    curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_HTTP);
                                    curl_setopt($ch, CURLOPT_PROXY, PROXY_ADDRESS);
                                }
                                
                                $response_data = curl_exec($ch);
                                
                                $curl_errno = curl_errno($ch);
                                $curl_error = curl_error($ch);
                                
                                unset($ch);
                                
                                parse_str($response_data, $response);
                                    
                                break;
                                
                            case 'payflow pro com object':
                                $post_data = '';
                                
                                foreach ($transaction_values as $name => $value) {
                                    if ($post_data) {
                                        $post_data .= '&';
                                    }
                                    
                                    $value = str_replace('"', '', $value);
                                    
                                    $post_data .= $name . '[' . strlen($value) . ']' . '=' . $value;
                                }
                                
                                $payflowpro = new COM("PFProCOMControl.PFProCOMControl.1");

                                $context = $payflowpro->CreateContext($payment_gateway_host, 443, 30, '', 0, '', '');
                                $response_data = $payflowpro->SubmitTransaction($context, $post_data, strlen($post_data));

                                $payflowpro->DestroyContext($context);
                                
                                parse_str($response_data, $response);
                                
                                break;
                                
                            case 'pfpro extension':
                                $response = pfpro_process($transaction_values, $payment_gateway_host, 443, 30);
                                
                                break;
                        }

                        // if transaction was unsuccessful, prepare error
                        if (!$response || ($response['RESULT'] != 0)) {
                            // if there was a response from the payment gateway
                            if ($response) {
                                switch ($response['RESULT']) {
                                    case 112:
                                        $payment_gateway_error_message = lang('Error message from payment gateway: ') . lang('The billing address that you entered does not match the billing address for this bank card. Please correct the billing address and try again. If you continue to receive this message, please try another bank card, or contact us for help.');
                                        break;
                                    
                                    case 114:
                                        $payment_gateway_error_message = lang('Error message from payment gateway: ') . lang('The verification number that you entered does not match the verification number for this bank card. Please correct the verification number and try again. If you continue to receive this message, please try another bank card, or contact us for help.');
                                        break;
                                    
                                    default:
                                        $payment_gateway_error_message = lang('Error message from payment gateway: ') . $response['RESPMSG'];
                                        break;
                                }
                            
                            // else there was no response, so there was a communication problem
                            } else {
                                $payment_gateway_error_message = lang('An error occurred while trying to communicate with the payment gateway.');
                            
                                // if there was a cURL error, add error number and error message to payment gateway error message
                                if ($curl_errno) {
                                    $payment_gateway_error_message .= ' ' . lang('cURL Error Number:') . ' ' . $curl_errno . '. ' . lang('cURL Error Message:') . ' ' . $curl_error . '.';
                                }
                            }
                        
                            log_activity($payment_gateway_error_message);
                        
                            $liveform->mark_error('payment_gateway', h($payment_gateway_error_message));
                        
                            // send user back to previous screen
                            header('Location: ' . URL_SCHEME . HOSTNAME . PATH . get_page_name($page_id));
                            exit();
                        }
                        
                        $transaction_id = $response['PNREF'];
                        $authorization_code = $response['AUTHCODE'];
                        
                        break;
                        
                    case 'PayPal Payments Pro':
                        // if transaction type is set to Authorize, prepare to authorize transaction.
                        if (ECOMMERCE_PAYMENT_GATEWAY_TRANSACTION_TYPE == 'Authorize') {
                            $paypal_payments_pro_transaction_type = 'Authorization';
                            
                        // else transaction type is Authorize & Capture, prepare to authorize and capture payment.
                        } else {
                            $paypal_payments_pro_transaction_type = 'Sale';
                        }
                        
                        
                        // check if there are shippable items in order
                        $paypal_payments_pro_shipping = false;
                        $query =
                            "SELECT COUNT(*)
                            FROM order_items
                            LEFT JOIN products ON products.id = order_items.product_id
                            WHERE
                                (order_items.order_id = ?)" . pg_order_item_active_sql() . "
                                AND (products.shippable = '1')";
                        $result = db_q($query, array((string) ($_SESSION['ecommerce']['order_id'] ?? ''))) or output_error('Query failed.');
                        $row = mysqli_fetch_row($result);
                        
                        // if there are shippable items in order, then prepare to send information to PayPal
                        if ($row[0] > 0) {
                            // check how many recipients there are for order
                            $query = "SELECT COUNT(*) FROM ship_tos WHERE order_id = ?";
                            $result = db_q($query, array((string) ($_SESSION['ecommerce']['order_id'] ?? ''))) or output_error('Query failed.');
                            $row = mysqli_fetch_row($result);
                            
                            // if there is just one recipient, then prepare to send shipping information to PayPal
                            if ($row[0] == 1) {
                                
                                // getting shipping information for recipient
                                $query =
                                    "SELECT
                                        first_name,
                                        last_name,
                                        address_1,
                                        address_2,
                                        city,
                                        state,
                                        country,
                                        zip_code,
                                        phone_number
                                    FROM ship_tos
                                    WHERE order_id = ?";
                                $result = db_q($query, array((string) ($_SESSION['ecommerce']['order_id'] ?? ''))) or output_error('Query failed.');
                                $row = mysqli_fetch_assoc($result);
                                
                                $first_name = $row['first_name'];
                                $last_name = $row['last_name'];
                                $address_1 = $row['address_1'];
                                $address_2 = $row['address_2'];
                                $city = $row['city'];
                                $state = $row['state'];
                                $country = $row['country'];
                                $zip_code = $row['zip_code'];
                                $phone_number = $row['phone_number'];
                                
                                $shipping_values = array(
                                    'SHIPTONAME' => $first_name . " " . $last_name,
                                    'SHIPTOSTREET' => $address_1,
                                    'SHIPTOCITY' => $city,
                                    'SHIPTOSTATE' => $state,
                                    'SHIPTOCOUNTRYCODE' => $country,
                                    'SHIPTOZIP' => $zip_code,
                                    'SHIPTOSTREET2' => $address_2,
                                    'PHONENUM' => $phone_number);
                                
                                $paypal_payments_pro_shipping = true;
                            }
                        }
                        
                        // Assign values to their names
                        $transaction_values = array(
                            'METHOD' => 'doDirectPayment',
                            'VERSION' => '3.0',
                            'PWD' => ECOMMERCE_PAYPAL_PAYMENTS_PRO_API_PASSWORD,
                            'USER' => ECOMMERCE_PAYPAL_PAYMENTS_PRO_API_USERNAME,
                            'SIGNATURE' => ECOMMERCE_PAYPAL_PAYMENTS_PRO_API_SIGNATURE,
                            'PAYMENTACTION' => $paypal_payments_pro_transaction_type,
                            'BUTTONSOURCE' => 'Camelback_Cart_DP',
                            'IPADDRESS' => $_SERVER['REMOTE_ADDR'],
                            'AMT' => sprintf("%01.2lf", $total / 100),
                            'CREDITCARDTYPE' => $credit_card_type,
                            'ACCT' => $card_number,
                            'EXPDATE' => $expiration_month . $expiration_year,
                            'CVV2' => $liveform->get_field_value('card_verification_number'),
                            'EMAIL' => $billing_email_address,
                            'FIRSTNAME' => $billing_first_name,
                            'LASTNAME' => $billing_last_name,
                            'STREET' => $billing_address_1 . ' ' . $billing_address_2,
                            'CITY' => $billing_city,
                            'STATE' => $billing_state,
                            'ZIP' => $billing_zip_code,
                            'COUNTRYCODE' => $billing_country,
                            'CURRENCYCODE' => BASE_CURRENCY_CODE);
                        
                        // If there is not a gift card discount and not a surcharge, then prepare to send optional details.
                        // We can't send this data if there is a gift card discount or surcharge, because PayPal returns an error:
                        // "The totals of the cart item amounts do not match order amounts."
                        if (($gift_card_discount == 0) && ($surcharge == 0)) {
                            $transaction_values['ITEMAMT'] = sprintf("%01.2lf", ($subtotal - $discount) / 100);
                            $transaction_values['SHIPPINGAMT'] = sprintf("%01.2lf", $shipping / 100);
                            $transaction_values['TAXAMT'] = sprintf("%01.2lf", $tax / 100);
                        }
                        
                        $post_data = '';
                        
                        // Create name value pairs.
                        foreach ($transaction_values as $name => $value) {
                            if ($post_data) {
                                $post_data .= '&';
                            }
                            
                            $value = str_replace('"', '', $value);
                            
                            $post_data .= $name . '=' . urlencode($value);
                        }
                        
                        // if there are shippable items and there are not multiple recipients, create name value pairs for the shipping address.
                        if ($paypal_payments_pro_shipping === true) {
                            foreach ($shipping_values as $name => $value) {
                                if ($post_data) {
                                    $post_data .= '&';
                                }
                                
                                $value = str_replace('"', '', $value);
                                
                                $post_data .= $name . '=' . urlencode($value);
                            }
                        }
                        
                        // Setup cURL options.
                        $ch = curl_init();
                        curl_setopt($ch, CURLOPT_URL, $payment_gateway_host);
                        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/4.0 (compatible; MSIE 5.01; Windows NT 5.0)');
                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                        curl_setopt($ch, CURLOPT_TIMEOUT, 300);
                        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 0);
                        pg_curl_tls($ch);
                        curl_setopt($ch, CURLOPT_FORBID_REUSE, true);
                        curl_setopt($ch, CURLOPT_POST, 1);
                        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
                        
                        // if there is a proxy address, then send cURL request through proxy
                        if (PROXY_ADDRESS != '') {
                            curl_setopt($ch, CURLOPT_HTTPPROXYTUNNEL, true);
                            curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_HTTP);
                            curl_setopt($ch, CURLOPT_PROXY, PROXY_ADDRESS);
                        }
                        
                        $response_data = curl_exec($ch);
                        
                        $curl_errno = curl_errno($ch);
                        $curl_error = curl_error($ch);
                        
                        unset($ch);
                        
                        parse_str($response_data, $response);
                        
                        // if transaction was unsuccessful, prepare error
                        if (!$response || (isset($response['L_ERRORCODE0']) == true)) {
                            // if there was a response from the payment gateway
                            if ($response) {
                                $payment_gateway_error_message = lang('Error message from payment gateway: ') . $response['L_LONGMESSAGE0'];
                            
                            // else there was no response, so there was a communication problem
                            } else {
                                $payment_gateway_error_message = lang('An error occurred while trying to communicate with the payment gateway.');
                            
                                // if there was a cURL error, add error number and error message to payment gateway error message
                                if ($curl_errno) {
                                    $payment_gateway_error_message .= ' ' . lang('cURL Error Number:') . ' ' . $curl_errno . '. ' . lang('cURL Error Message:') . ' ' . $curl_error . '.';
                                }
                            }
                        
                            log_activity($payment_gateway_error_message);
                        
                            $liveform->mark_error('payment_gateway', h($payment_gateway_error_message));
                        
                            // send user back to previous screen
                            header('Location: ' . URL_SCHEME . HOSTNAME . PATH . get_page_name($page_id));
                            exit();
                        }
                        
                        $transaction_id = $response['TRANSACTIONID'];
                        $authorization_code = '';
                        
                        break;
                        
                    case 'Sage':
                        $sage_t_shipping = '';
                        $sage_c_ship_name = '';
                        $sage_c_ship_address = '';
                        $sage_c_ship_city = '';
                        $sage_c_ship_state = '';
                        $sage_c_ship_zip = '';
                        $sage_c_ship_country = '';
                        
                        // determine if there are shippable items
                        $query =
                            "SELECT COUNT(*)
                            FROM order_items
                            LEFT JOIN products ON products.id = order_items.product_id
                            WHERE
                                (order_items.order_id = ?)" . pg_order_item_active_sql() . "
                                AND (products.shippable = '1')";
                        $result = db_q($query, array((string) ($_SESSION['ecommerce']['order_id'] ?? ''))) or output_error('Query failed.');
                        $row = mysqli_fetch_row($result);
                        
                        // if there are shippable items in order, then determine if there is only one recipient
                        if ($row[0] > 0) {
                            // check how many recipients there are for order
                            $query = "SELECT COUNT(*) FROM ship_tos WHERE order_id = ?";
                            $result = db_q($query, array((string) ($_SESSION['ecommerce']['order_id'] ?? ''))) or output_error('Query failed.');
                            $row = mysqli_fetch_row($result);
                            
                            // if there is just one recipient, then prepare to send shipping information to Sage
                            if ($row[0] == 1) {
                                $sage_t_shipping = sprintf("%01.2lf", $shipping / 100);
                                
                                // getting shipping information for recipient
                                $query =
                                    "SELECT
                                        first_name,
                                        last_name,
                                        address_1,
                                        address_2,
                                        city,
                                        state,
                                        zip_code,
                                        country
                                    FROM ship_tos
                                    WHERE order_id = ?";
                                $result = db_q($query, array((string) ($_SESSION['ecommerce']['order_id'] ?? ''))) or output_error('Query failed.');
                                $row = mysqli_fetch_assoc($result);
                                
                                $sage_c_ship_name = $row['first_name'] . ' ' . $row['last_name'];
                                $sage_c_ship_address = $row['address_1'] . ' ' . $row['address_2'];
                                $sage_c_ship_city = $row['city'];
                                $sage_c_ship_state = $row['state'];
                                $sage_c_ship_zip = $row['zip_code'];
                                $sage_c_ship_country = $row['country'];
                            }
                        }

                        // They do not appear to support foreign currencies (at least at the API level),
                        // so we are not passing currency info.
                        
                        $transaction_values = array(
                            'M_id' => ECOMMERCE_SAGE_MERCHANT_ID,
                            'M_key' => ECOMMERCE_SAGE_MERCHANT_KEY,
                            'C_name' => $billing_first_name . ' ' . $billing_last_name,
                            'C_address' => $billing_address_1 . ' ' . $billing_address_2,
                            'C_city' => $billing_city,
                            'C_state' => $billing_state,
                            'C_zip' => $billing_zip_code,
                            'C_country' => $billing_country,
                            'C_email' => $billing_email_address,
                            'C_telephone' => $billing_phone_number,
                            'C_fax' => $billing_fax_number,
                            'C_cardnumber' => $card_number,
                            'C_exp' => $expiration_month . mb_substr($expiration_year, -2), // mmyy
                            'C_cvv' => $liveform->get_field_value('card_verification_number'),
                            'T_amt' => sprintf("%01.2lf", $total / 100),
                            'T_code' => $sage_transaction_type,
                            'T_ordernum' => $reference_code, // we use our reference code instead of order number because we do not know order number yet
                            'T_tax' => sprintf("%01.2lf", $tax / 100),
                            'T_shipping' => $sage_t_shipping,
                            'C_ship_name' => $sage_c_ship_name,
                            'C_ship_address' => $sage_c_ship_address,
                            'C_ship_city' => $sage_c_ship_city,
                            'C_ship_state' => $sage_c_ship_state,
                            'C_ship_zip' => $sage_c_ship_zip,
                            'C_ship_country' => $sage_c_ship_country
                        );
                        
                        $post_data = '';
                        
                        // Create name value pairs.
                        foreach ($transaction_values as $name => $value) {
                            if ($post_data) {
                                $post_data .= '&';
                            }
                            
                            $post_data .= $name . '=' . urlencode($value);
                        }
                        
                        // Setup cURL options.
                        $ch = curl_init();
                        curl_setopt($ch, CURLOPT_URL, 'https://gateway.sagepayments.net/cgi-bin/eftBankcard.dll?transaction');
                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                        curl_setopt($ch, CURLOPT_TIMEOUT, 300);
                        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 0);
                        pg_curl_tls($ch);
                        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
                        curl_setopt($ch, CURLOPT_FORBID_REUSE, true);
                        curl_setopt($ch, CURLOPT_POST, 1);
                        
                        // if there is a proxy address, then send cURL request through proxy
                        if (PROXY_ADDRESS != '') {
                            curl_setopt($ch, CURLOPT_HTTPPROXYTUNNEL, true);
                            curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_HTTP);
                            curl_setopt($ch, CURLOPT_PROXY, PROXY_ADDRESS);
                        }
                        
                        $response_data = curl_exec($ch);
                        
                        $curl_errno = curl_errno($ch);
                        $curl_error = curl_error($ch);
                        
                        unset($ch);
                        
                        // if there was not a response, then there was a communication problem, so prepare error and send user back to previous screen
                        if ($response_data === FALSE) {
                            $payment_gateway_error_message = lang('An error occurred while trying to communicate with the payment gateway.');
                        
                            // if there was a cURL error, add error number and error message to payment gateway error message
                            if ($curl_errno) {
                                $payment_gateway_error_message .= ' ' . lang('cURL Error Number:') . ' ' . $curl_errno . '. ' . lang('cURL Error Message:') . ' ' . $curl_error . '.';
                            }
                        
                            log_activity($payment_gateway_error_message);
                        
                            $liveform->mark_error('payment_gateway', h($payment_gateway_error_message));
                        
                            // send user back to previous screen
                            header('Location: ' . URL_SCHEME . HOSTNAME . PATH . get_page_name($page_id));
                            exit();
                        }

                        // the approval indicator is the 2nd character in the response data string
                        $sage_approval_indicator = trim($response_data[1]);

                        // if the transaction was rejected, then prepare error and forward user back to previous screen
                        if ($sage_approval_indicator != 'A') {
                            $sage_code = trim(mb_substr($response_data, 2, 6));
                            $sage_message = trim(mb_substr($response_data, 8, 32));
                        
                            $payment_gateway_error_message = lang('Error message from payment gateway: ') . $sage_message . ' (' . $sage_code . ')';
                        
                            log_activity($payment_gateway_error_message);
                        
                            $liveform->mark_error('payment_gateway', h($payment_gateway_error_message));
                        
                            // send user back to previous screen
                            header('Location: ' . URL_SCHEME . HOSTNAME . PATH . get_page_name($page_id));
                            exit();
                        }
                        
                        // set the transaction id to Sage's reference code
                        $transaction_id = trim(mb_substr($response_data, 46, 10));
                        
                        // Sage does not return an authorization code
                        $authorization_code = trim(mb_substr($response_data, 2, 6));
                        
                        break;


                    case 'Stripe':
                        require_once(dirname(__FILE__) . '/includes/stripe/lib/Stripe.php');

                        Stripe::setApiKey(ECOMMERCE_STRIPE_API_KEY);

                        // If transaction type is set to Authorize, then set capture to false.
                        if (ECOMMERCE_PAYMENT_GATEWAY_TRANSACTION_TYPE == 'Authorize') {
                            $capture = false;
                            
                        // Otherwise transaction type is Authorize & Capture, so set capture to true.
                        } else {
                            $capture = true;
                        }

                        try {
                            $response = Stripe_Charge::create(array(
                                'amount' => $total,
                                'currency' => strtolower(BASE_CURRENCY_CODE),
                                'card' => array(
                                    'number' => $card_number,
                                    'exp_month' => $expiration_month,
                                    'exp_year' => $expiration_year,
                                    'cvc' => $liveform->get_field_value('card_verification_number'),
                                    'name' => $liveform->get_field_value('cardholder'),
                                    'address_line1' => $billing_address_1,
                                    'address_line2' => $billing_address_2,
                                    'address_city' => $billing_city,
                                    'address_zip' => $billing_zip_code,
                                    'address_state' => $billing_state,
                                    'address_country' => $billing_country),
                                'description' => $billing_email_address,
                                'capture' => $capture
                            ));

                            $transaction_id = $response->id;
                            $authorization_code = '';

                        } catch (Exception $e) {
                            $payment_gateway_error_message = lang('Error message from payment gateway: ') . $e->getMessage();

                            log_activity($payment_gateway_error_message, $_SESSION['sessionusername'] ?? '');

                            $liveform->mark_error('payment_gateway', h($payment_gateway_error_message));

                            header('Location: ' . URL_SCHEME . HOSTNAME . PATH . get_page_name($page_id));
                            exit();
                        }

                        break; 

                    // Paid by the iyzico driver above (pg_payment_checkout_driver()), so
                    // this is reached only if that driver did not take the payment:
                    // refuse rather than record an order nobody was charged for.
                    case 'Iyzipay':
                        $liveform->mark_error('payment_gateway', lang('The selected payment method is not available.'));
                        header('Location: ' . URL_SCHEME . HOSTNAME . PATH . get_page_name($page_id));
                        exit();
                }
                
                break;
            
            // if PayPal Express Checkout payment method was selected, process transaction through PayPal
            case 'PayPal Express Checkout':
                if (ECOMMERCE_PAYPAL_EXPRESS_CHECKOUT_MODE == 'sandbox') {
                    $paypal_express_checkout_host = 'https://api-3t.sandbox.paypal.com/nvp';
                } else {
                    $paypal_express_checkout_host = 'https://api-3t.paypal.com/nvp';
                }
                
                // if transaction type is set to Authorize, then prepare paymentaction to send
                if (ECOMMERCE_PAYPAL_EXPRESS_CHECKOUT_TRANSACTION_TYPE == 'Authorize') {
                    $paypal_express_checkout_paymentaction = 'Authorization';
                    
                // else transaction type is Authorize & Capture, so prepare paymentaction to send
                } else {
                    $paypal_express_checkout_paymentaction = 'Sale';
                }

                $paypal_express_checkout_shipping = '';

                // check if there are shippable order items in order
                $query =
                    "SELECT COUNT(*)
                    FROM order_items
                    LEFT JOIN products ON products.id = order_items.product_id
                    WHERE
                        (order_items.order_id = ?)" . pg_order_item_active_sql() . "
                        AND (products.shippable = '1')";
                $result = db_q($query, array((string) ($_SESSION['ecommerce']['order_id'] ?? ''))) or output_error('Query failed.');
                $row = mysqli_fetch_row($result);
                
                // if there are shippable order items in order, then prepare to send information to PayPal
                if ($row[0] > 0) {
                    // check how many recipients there are for order
                    $query = "SELECT COUNT(*) FROM ship_tos WHERE order_id = ?";
                    $result = db_q($query, array((string) ($_SESSION['ecommerce']['order_id'] ?? ''))) or output_error('Query failed.');
                    $row = mysqli_fetch_row($result);
                    
                    // if there is just one recipient, then prepare to send shipping information to PayPal
                    if ($row[0] == 1) {
                        // getting shipping information for recipient
                        $query =
                            "SELECT
                                ship_to_name,
                                first_name,
                                last_name,
                                address_1,
                                address_2,
                                city,
                                state,
                                country,
                                zip_code,
                                phone_number
                            FROM ship_tos
                            WHERE order_id = ?";
                        $result = db_q($query, array((string) ($_SESSION['ecommerce']['order_id'] ?? ''))) or output_error('Query failed.');
                        $row = mysqli_fetch_assoc($result);
                        
                        $ship_to_name = $row['ship_to_name'];
                        
                        // if recipient is myself (i.e. single-recipient or myself under multi-recipient), then send shipping information to PayPal
                        if ($ship_to_name == 'myself') {
                            $first_name = $row['first_name'];
                            $last_name = $row['last_name'];
                            $address_1 = $row['address_1'];
                            $address_2 = $row['address_2'];
                            $city = $row['city'];
                            $state = $row['state'];
                            $country = $row['country'];
                            $zip_code = $row['zip_code'];
                            $phone_number = $row['phone_number'];
                            
                            $paypal_express_checkout_noshipping = '0';
                            $paypal_express_checkout_addroverride = '1';
                            
                            $paypal_express_checkout_shipping =
                                'PAYMENTREQUEST_0_SHIPTONAME=' . urlencode($first_name) . ' ' . urlencode($last_name) . '&' .
                                'PAYMENTREQUEST_0_SHIPTOSTREET=' . urlencode($address_1) . '&' .
                                'PAYMENTREQUEST_0_SHIPTOCITY=' . urlencode($city) . '&' .
                                'PAYMENTREQUEST_0_SHIPTOSTATE=' . urlencode($state) . '&' .
                                'PAYMENTREQUEST_0_SHIPTOCOUNTRYCODE=' . urlencode($country) . '&' .
                                'PAYMENTREQUEST_0_SHIPTOZIP=' . urlencode($zip_code) . '&' .
                                'PAYMENTREQUEST_0_SHIPTOSTREET2=' . urlencode($address_2) . '&' .
                                'PAYMENTREQUEST_0_PHONENUM=' . urlencode($phone_number) . '&';
                        
                        // else the recipient is not myself, so don't send shipping information to PayPal
                        // we don't want to send shipping information to PayPal under this condition,
                        // because PayPal has a bug where it will prefill the PayPal sign up form with this shipping information,
                        // which will cause confusion if the recipient is not myself
                        } else {
                            $paypal_express_checkout_noshipping = '1';
                            $paypal_express_checkout_addroverride = '0';
                            $paypal_express_checkout_shipping = '';
                        }
                        
                    // else there is more than one recipient, so suppress shipping at PayPal and prepare to not send shipping information to PayPal
                    } else {
                        $paypal_express_checkout_noshipping = '1';
                        $paypal_express_checkout_addroverride = '0';
                        $paypal_express_checkout_shipping = '';
                    }
                    
                // else there are no shippable order items in order, so suppress shipping at PayPal
                } else {
                    $paypal_express_checkout_noshipping = '1';
                    $paypal_express_checkout_addroverride = '0';
                    $paypal_express_checkout_shipping = '';
                }

                $paypal_express_checkout_order_details = '';
                
                // if there is not a gift card discount and not a surcharge,
                // then prepare order details so additional information about order totals and line items appears at PayPal
                // we can't send this data if there is a gift card discount or surcharge,
                // because PayPal returns an error: The totals of the cart item amounts do not match order amounts.
                // there is a chance we could add the gift card discount as a line item with a negative amount (i.e. similar to the order discount),
                // however we have decided not to do that for now because we don't know if that would work and it has not been tested
                // For example, what if the gift card discounts more than the whole subtotal
                if (($gift_card_discount == 0) && ($surcharge == 0)) {
                    $paypal_express_checkout_order_details .=
                        'PAYMENTREQUEST_0_ITEMAMT=' . sprintf("%01.2lf", ($subtotal - $discount) / 100) . '&' .
                        'PAYMENTREQUEST_0_SHIPPINGAMT=' . sprintf("%01.2lf", $shipping / 100) . '&' .
                        'PAYMENTREQUEST_0_TAXAMT=' . sprintf("%01.2lf", $tax / 100) . '&';

                    // loop through non-recurring order items in order to send order item details to PayPal Express Checkout
                    foreach ($nonrecurring_products as $line_item_number => $product) {
                        $paypal_express_checkout_order_details .=
                            'L_PAYMENTREQUEST_0_NAME' . $line_item_number . '=' . urlencode($product['short_description']) . '&' .
                            'L_PAYMENTREQUEST_0_NUMBER' . $line_item_number . '=' . urlencode($product['product_name']) . '&' .
                            'L_PAYMENTREQUEST_0_AMT' . $line_item_number . '=' . sprintf("%01.2lf", $product['price'] / 100) . '&' .
                            'L_PAYMENTREQUEST_0_QTY' . $line_item_number . '=' . $product['quantity'] . '&';
                    }

                    // if there is an order discount, then add line item for discount
                    if ($discount) {
                        $line_item_number += 1;

                        $tax_discount = $original_tax - $tax;

                        $paypal_express_checkout_order_details .=
                            'L_PAYMENTREQUEST_0_NAME' . $line_item_number . '=' . urlencode('Discount') . '&' .
                            'L_PAYMENTREQUEST_0_AMT' . $line_item_number . '=-' . sprintf("%01.2lf", $discount / 100) . '&' .
                            'L_PAYMENTREQUEST_0_QTY' . $line_item_number . '=1&' .
                            'L_PAYMENTREQUEST_0_TAXAMT' . $line_item_number . '=-' . sprintf("%01.2lf", $tax_discount / 100) . '&';
                    }
                }
                
                // if mode is not paypal_express_checkout_return, then prepare to send SetExpressCheckout request to PayPal
                if (($_GET['mode'] ?? '') != 'paypal_express_checkout_return') {
                    $paypal_express_checkout_returnurl = URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/' . $type_value . '.php?mode=paypal_express_checkout_return&page_id=' . $page_id;
                    $paypal_express_checkout_cancelurl = URL_SCHEME . HOSTNAME . PATH . get_page_name($page_id);
                    
                    $post_data =
                        'METHOD=SetExpressCheckout&' .
                        'VERSION=85.0&' .
                        'USER=' . urlencode(ECOMMERCE_PAYPAL_EXPRESS_CHECKOUT_API_USERNAME) . '&' .
                        'PWD=' . urlencode(ECOMMERCE_PAYPAL_EXPRESS_CHECKOUT_API_PASSWORD) . '&' .
                        'SIGNATURE=' . urlencode(ECOMMERCE_PAYPAL_EXPRESS_CHECKOUT_API_SIGNATURE) . '&' .
                        'SOLUTIONTYPE=Sole&' . // this parameter and the one below tell PayPal to allow the customer to pay by credit/debit card without having a PayPal account
                        'LANDINGPAGE=Billing&' .
                        'PAYMENTREQUEST_0_PAYMENTACTION=' . $paypal_express_checkout_paymentaction . '&' .
                        'PAYMENTREQUEST_0_AMT=' . sprintf("%01.2lf", $total / 100) . '&' .
                        'PAYMENTREQUEST_0_CURRENCYCODE=' . BASE_CURRENCY_CODE . '&' .
                        'RETURNURL=' . urlencode($paypal_express_checkout_returnurl) . '&' .
                        'CANCELURL=' . urlencode($paypal_express_checkout_cancelurl) . '&' .
                        'NOSHIPPING=' . $paypal_express_checkout_noshipping . '&' .
                        'ADDROVERRIDE=' . $paypal_express_checkout_addroverride . '&' .
                        $paypal_express_checkout_shipping .
                        $paypal_express_checkout_order_details .
                        'EMAIL=' . urlencode($billing_email_address);
                    
                // else mode is paypal_express_checkout_return, so prepare to send DoExpressCheckoutPayment request to PayPal
                } else {

                    $post_data =
                        'METHOD=DoExpressCheckoutPayment&' .
                        'VERSION=85.0&' .
                        'USER=' . urlencode(ECOMMERCE_PAYPAL_EXPRESS_CHECKOUT_API_USERNAME) . '&' .
                        'PWD=' . urlencode(ECOMMERCE_PAYPAL_EXPRESS_CHECKOUT_API_PASSWORD) . '&' .
                        'SIGNATURE=' . urlencode(ECOMMERCE_PAYPAL_EXPRESS_CHECKOUT_API_SIGNATURE) . '&' .
                        'TOKEN=' . urlencode($_GET['token']) . '&' .
                        'PAYERID=' . urlencode($_GET['PayerID']) . '&' .
                        'PAYMENTREQUEST_0_PAYMENTACTION=' . $paypal_express_checkout_paymentaction . '&' .
                        'BUTTONSOURCE=Camelback_Cart_EC&' .
                        $paypal_express_checkout_shipping .
                        $paypal_express_checkout_order_details .
                        'PAYMENTREQUEST_0_AMT=' . sprintf("%01.2lf", $total / 100) . '&' .
                        'PAYMENTREQUEST_0_CURRENCYCODE=' . BASE_CURRENCY_CODE;
                }
                
                $ch = curl_init();
                
                curl_setopt($ch, CURLOPT_URL, $paypal_express_checkout_host);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                curl_setopt($ch, CURLOPT_TIMEOUT, 300);
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 0);
                pg_curl_tls($ch);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
                curl_setopt($ch, CURLOPT_FORBID_REUSE, true);
                curl_setopt($ch, CURLOPT_POST, 1);
                
                // if there is a proxy address, then send cURL request through proxy
                if (PROXY_ADDRESS != '') {
                    curl_setopt($ch, CURLOPT_HTTPPROXYTUNNEL, true);
                    curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_HTTP);
                    curl_setopt($ch, CURLOPT_PROXY, PROXY_ADDRESS);
                }
                
                $response_data = curl_exec($ch);
                
                $curl_errno = curl_errno($ch);
                $curl_error = curl_error($ch);
                
                unset($ch);
                
                parse_str($response_data, $response);
                
                // if transaction was unsuccessful, prepare error
                if (!$response || (isset($response['L_ERRORCODE0']) == true)) {
                    // if there was a response from PayPal
                    if ($response) {
                        $paypal_express_checkout_error_message = 'Error message from PayPal: ' . $response['L_LONGMESSAGE0'];
                        
                    // else there was no response, so there was a communication problem
                    } else {
                        $paypal_express_checkout_error_message = 'An error occurred while trying to communicate with PayPal.';
                        
                        // if there was a cURL error, add error number and error message to PayPal error message
                        if ($curl_errno) {
                            $paypal_express_checkout_error_message .= ' cURL Error Number: ' . $curl_errno . '. cURL Error Message: ' . $curl_error . '.';
                        }
                    }

                    log_activity($paypal_express_checkout_error_message);
                    
                    $liveform->mark_error('paypal_express_checkout', h($paypal_express_checkout_error_message));

                    // send user back to previous screen
                    header('Location: ' . URL_SCHEME . HOSTNAME . PATH . get_page_name($page_id));
                    exit();
                }
                
                // if mode is not paypal_express_checkout_return, then send user to PayPal
                if (($_GET['mode'] ?? '') != 'paypal_express_checkout_return') {
                    if (ECOMMERCE_PAYPAL_EXPRESS_CHECKOUT_MODE == 'sandbox') {
                        $paypal_express_checkout_url = 'https://www.sandbox.paypal.com/webscr&cmd=_express-checkout&useraction=commit&token=';
                    } else {
                        $paypal_express_checkout_url = 'https://www.paypal.com/webscr&cmd=_express-checkout&useraction=commit&token=';
                    }
                    
                    // send user to PayPal
                    header('Location: ' . $paypal_express_checkout_url . $response['TOKEN']);
                    exit();
                    
                // else mode is paypal_express_checkout_return, so get transaction id
                } else {
                    $transaction_id = $response['PAYMENTINFO_0_TRANSACTIONID'];
                }

                break;

            // An offline payment is not charged here.
            case 'Offline Payment':
                break;

            // The allow-list above already rejects any other value, and a driver's method (Pay With
            // Iyzico) is paid by its driver above; refuse to continue rather than completing the
            // order without a charge.
            default:
                $liveform->mark_error('payment_method', lang('The selected payment method is not available.'));
                header('Location: ' . URL_SCHEME . HOSTNAME . PATH . get_page_name($page_id));
                exit();
        }
    }

    // A key code the special offer code names: the offer code it stands for
    // is written with the order; a single-use one is used up once the order
    // is completed.
    $key_code = pg_order_key_code($special_offer_code);

    $special_offer_values = array();

    if ($key_code && ($key_code['report'] == 'offer_code')) {
        $special_offer_values = array('special_offer_code' => $key_code['offer_code']);
    }
    
    // set order date
    $order_date = time();

    // Unset any existing auto-registration info, from a previous order.  This is important because
    // a user might be submitting multiple orders on behalf of multiple customers, and we don't
    // want the auto-registration info from a previous order to appear on the receipt for a
    // different order/customer.
    unset($_SESSION['software']['auto_registration']);

    // Prepare to remember whether a new user was created or an existing user was found for
    // auto-registration, so later we can create address book items for the user.  We don't create
    // address book items now, because it is more efficient to do it later, when we get recipient
    // data to update ship date, delivery date, and etc.
    $auto_registration_type = '';

    // If auto-registration is enabled and the user is not logged in or is ghosting, then deal with
    // auto-registration.
    if ($auto_registration and (!USER_LOGGED_IN or $ghost)) {

        // Check if user exists for billing email address.
        // If the user is found, then this order will be connected to the user further below.
        $user_id = db_value("SELECT user_id FROM user WHERE user_email = '" . e($billing_email_address) . "'");

        // If a user does not exist, then create user - unless the address is
        // blocked, in which case the order still goes through (the customer
        // paid; refusing the sale here would be a different decision) but no
        // account is opened from it.
        if (!$user_id && pg_email_blocked($billing_email_address)) {
            log_activity(lang(array(
                'string' => 'no account created for a blocked email address ({var:1})',
                'vars'   => array($billing_email_address))), '');

        } elseif (!$user_id) {

            // Create a username by using everything before "@" in the email address,
            // and, if necessary, add numbers to the end to make it unique.
            $username = strtok($billing_email_address, '@');
            $username = get_unique_username($username);

            // Created without a password (algo 3, which no password matches):
            // the customer sets one through the set password link issued
            // below, or on the change password screen while still signed in.
            $user_id = create_member_user(array(
                'username'   => $username,
                'email'      => $billing_email_address,
                'contact_id' => $contact_id,
            ));

            pg_set_password_link_issue($user_id);

            // Remember the account in the session, so the receipt can name it
            // and give the customer its set password link. A staff member
            // ordering for the customer (ghosting) is not the customer: their
            // receipt page names the account only (pg_order_new_account_link()).
            $_SESSION['software']['auto_registration'] = array(
                'email_address' => $billing_email_address,
                'user_id'       => (int) $user_id,
                'ghost'         => (bool) $ghost,
            );

            // If there is a contact for this user, and there is a registration contact group,
            // then add contact to registration contact group.  There should probably always
            // be a contact, but we just add that check to make sure, in case there is some
            // situation where there is not one.
            if (
                ($contact_id)
                && (REGISTRATION_CONTACT_GROUP_ID)
                && (db_value("SELECT id FROM contact_groups WHERE id = '" . REGISTRATION_CONTACT_GROUP_ID  . "'"))
            ) {
                db(
                    "INSERT INTO contacts_contact_groups_xref (
                        contact_id,
                        contact_group_id)
                    VALUES (
                        '" . $contact_id . "',
                        '" . REGISTRATION_CONTACT_GROUP_ID . "')");
            }

            // If this user is not ghosting, then auto-login user.  We don't want to auto-login the
            // user if they are ghosting, because they probably want to remain logged in to their
            // main account.
            if (!$ghost) {

                pg_session_sign_in($user_id, $username);

                // Bind this fresh session to a device token while the device
                // limit is on, so it counts toward the limit and can be signed
                // out from another device. No-op when the limit is off.
                pg_login_set_device_cookie((int) $_SESSION['sessionuserid'], false);

                log_activity(
                    'user was auto-logged in by order auto-registration',
                    $_SESSION['sessionusername'] ?? '');
            }

            // Remember that a new user was created, so later we can create address book items for
            // the new user.
            $auto_registration_type = 'new';

        // Otherwise a user was found, so just take note, so later we can create address book items
        // for the existing user.
        } else {
            $auto_registration_type = 'existing';
        }
    }

    // The affiliate's commission is a value of the order; its rows are
    // written once the order is completed.
    $affiliate_code = get_affiliate_code();
    $affiliate_rate = null;

    if ((AFFILIATE_PROGRAM == true) && ($affiliate_code != '')) {
        $affiliate_rate = pg_order_affiliate_rate($affiliate_code);
    }

    if ($affiliate_rate !== null) {
        $commission = pg_order_commission($nonrecurring_products, $affiliate_rate, $discount, $subtotal, $gift_card_discount, $total_without_surcharge);
    }
    
    // if Credit/Debit Card payment method was selected, then prepare to update fields in order table for Credit/Debit Card
    if ($liveform->get_field_value('payment_method') == 'Credit/Debit Card') {
        // A gateway took the payment: only the masked number is kept. Otherwise
        // the number is kept encrypted for manual processing, cleared once the
        // order is processed (pg_order_card_data_clear()), never in clear. The
        // security code is never stored.
        if ($transaction_id != '') {
            $processed_card_number = protect_credit_card_number($card_number);
        } elseif (credit_card_encryption_is_available()) {
            $processed_card_number = encrypt_credit_card_number($card_number, ENCRYPTION_KEY);
        } else {
            $processed_card_number = '';
        }
        
        $card_values = array(
            'card_type' => $card_type,
            'card_number' => $processed_card_number,
            'expiration_month' => $expiration_month,
            'expiration_year' => $expiration_year,
            'cardholder' => $liveform->get('cardholder') ?? '',
            'card_verification_number' => '',
        );
    } else {
        $card_values = array();
    }

    // If this visitor has a tracking code, then update tracking
    // code in order.  If this visitor does not have a tracking code,
    // then we don't update the tracking code for the order,
    // because we don't want to lose a previous tracking code.

    $tracking_code = get_tracking_code();

    $tracking_values = array();

    if ($tracking_code != '') {
        $tracking_values['tracking_code'] = $tracking_code;
    }

    if (!empty($_SESSION['software']['utm_source'])) {
        $tracking_values += array(
            'utm_source' => $_SESSION['software']['utm_source'] ?? '',
            'utm_medium' => $_SESSION['software']['utm_medium'] ?? '',
            'utm_campaign' => $_SESSION['software']['utm_campaign'] ?? '',
            'utm_term' => $_SESSION['software']['utm_term'] ?? '',
            'utm_content' => $_SESSION['software']['utm_content'] ?? '',
        );
    }


    // An order put on the customer's account is billed to that account
    // (erp_invoice_from_order() reads orders.erp_account_id, the due date
    // comes from the account) and marked, so the job that cancels bank
    // transfers that never arrived leaves it alone. The receipt names the
    // choice the way checkout offered it.
    $erp_values = array();

    if ($erp_on_account && (($erp_open_account = pg_erp_open_account()) !== null)) {
        $erp_values = array('erp_on_account' => '1', 'erp_account_id' => (int) $erp_open_account['id']);
        $_SESSION['ecommerce']['offline_payment_label'] = lang('Put it on my account');

    // A later bank transfer in the same visit is not called that.
    } elseif (($_SESSION['ecommerce']['offline_payment_label'] ?? '') === lang('Put it on my account')) {
        unset($_SESSION['ecommerce']['offline_payment_label']);
    }

    // The recipient that gave up the shipping's minor units (see the totals
    // above) keeps the cost the order was charged.
    if ($shipping_less > 0) {
        db("UPDATE ship_tos SET shipping_cost = shipping_cost - " . (int) $shipping_less . "
            WHERE id = '" . (int) $shipping_less_ship_to_id . "'");
    }

    // Complete the order (includes/fn/order_completion.php): its values, the
    // status, the order number, the legal documents, order.created and the
    // order SMS. The user id is written again, in case we now have new user
    // info from the auto-registration feature that ran above.
    // paid_at: a gateway-confirmed payment lands when the order is placed; an
    // offline payment has no confirmed moment here, so it stays 0, and so does
    // a payment its service left pending (pg_order_checkout_paid_at()).
    $order_number = pg_order_complete(
        (int) ($_SESSION['ecommerce']['order_id'] ?? 0),
        array('payment_method' => $liveform->get_field_value('payment_method'))
        + $erp_values
        + $card_values
        + array(
            'subtotal' => $subtotal,
            'discount' => $discount,
            'tax' => $tax,
            'shipping' => $shipping,
            'gift_card_discount' => $gift_card_discount,
            'surcharge' => $surcharge,
            'total' => $total,
            'payment_installment' => $payment_installment,
            'installment_charges' => $installment_charge,
            'commission' => $commission,
            'order_date' => $order_date,
            'user_id' => $user_id,
        )
        + $special_offer_values
        + array(
            'last_modified_timestamp' => $order_date,
            'transaction_id' => $transaction_id,
            'paid_at' => pg_order_checkout_paid_at((string) $liveform->get_field_value('payment_method'), (string) $transaction_id, (string) $payment_status, (int) $order_date),
            'authorization_code' => $authorization_code,
        )
        + $tracking_values
        + array(
            'affiliate_code' => $affiliate_code,
            'ip_address' => $_SERVER['REMOTE_ADDR'],
        ),
        array_keys(array_filter(array(
            'preinfo'  => ($liveform->get_field_value('legal_preinfo') == 1),
            'contract' => ($liveform->get_field_value('legal_contract') == 1),
            'kvkk'     => ($liveform->get_field_value('legal_kvkk') == 1),
        )))
    );

    // Another request completed this order first (a second submit of the
    // same checkout): that one does the rest, so this one writes nothing more
    // and shows the receipt.
    if ($order_number === null) {
        $completed_order_id = (int) ($_SESSION['ecommerce']['order_id'] ?? 0);

        log_activity(lang('A second checkout of an order that was already completed changed nothing.') . ' (' . $completed_order_id . ')');

        $_SESSION['ecommerce']['completed_order_id'] = $completed_order_id;
        unset($_SESSION['ecommerce']['order_id']);
        $liveform->remove_form($type_value);

        $next_page_url = URL_SCHEME . HOSTNAME . PATH . get_page_name($next_page_id);
        $next_page_url .= (strpos($next_page_url, '?') === false ? '?' : '&') . 'order_id=' . $completed_order_id;
        header('Location: ' . $next_page_url);
        exit();
    }
    
    // Behind the lock, so a second submit of the same checkout writes none of
    // them again: the key code used up, the gift cards redeemed (a shortfall
    // is e-mailed to the store), the affiliate's commission.
    $completed_order_id = (int) ($_SESSION['ecommerce']['order_id'] ?? 0);

    pg_order_complete_key_code($key_code, USER_ID);

    if (ECOMMERCE_GIFT_CARD == TRUE) {
        pg_order_complete_gift_cards($completed_order_id, $order_number, $gift_card_discount, USER_ID, $_SESSION['sessionusername'] ?? '');
    }

    pg_order_complete_commission($completed_order_id, $affiliate_code, $affiliate_rate, $commission, $recurring_products, $user_id);

    // if a recurring transaction is required
    // and Credit/Debit Card was the selected payment method
    // and there is an active payment gateway, process recurring transactions
    if (
        ($recurring_transaction == TRUE)
        && ($liveform->get_field_value('payment_method') == 'Credit/Debit Card')
        && (ECOMMERCE_PAYMENT_GATEWAY != '')
    ) {
        // prepare for communicating with payment gateway in different ways depending on the selected payment gateway
        switch (ECOMMERCE_PAYMENT_GATEWAY) {
            case 'Authorize.Net':
                $start_date_format = 'Y-m-d';

                // If the Authorize.Net recurring URL has been set in the config.php file
                // (for an Authorize.Net emulator service), then use it.
                if ((defined('AUTHORIZENET_RECURRING_URL') == true) && (AUTHORIZENET_RECURRING_URL != '')) {
                    $payment_gateway_host = AUTHORIZENET_RECURRING_URL;
                    
                // Otherwise if test mode is enabled, use standard Authorize.net URL for test mode.
                } else if (ECOMMERCE_PAYMENT_GATEWAY_MODE == 'test') {
                    $payment_gateway_host = 'https://apitest.authorize.net/xml/v1/request.api';

                // Otherwise use standard production Authorize.Net URL.
                } else {
                    $payment_gateway_host = 'https://api2.authorize.net/xml/v1/request.api';
                }
                
                break;
                
            case 'First Data Global Gateway':
                $start_date_format = 'Ymd';
                
                break;
            
            case 'PayPal Payflow Pro':
                $start_date_format = 'mdY';
                
                break;
                
            case 'PayPal Payments Pro':
                $start_date_format = 'Y-m-d\TH:i:s.00\Z';
                
                break;
                
            case 'Sage':
                $start_date_format = 'm/d/Y';
                
                break;
        }
        
        // Loop through the recurring products in order to create recurring
        // transaction if necessary.
        foreach ($recurring_products as $product) {

            // If this product's price is less than or equal to 0,
            // then we don't need to create a transaction for it,
            // so skip to the next product.
            if ($product['price'] <= 0) {
                continue;
            }

            $price = $product['price'] * $product['quantity'];
            $tax = $product['tax_total'];
            $amount = $price + $tax;
            
            // prepare values in a certain way for several payment gateways
            if (
                (ECOMMERCE_PAYMENT_GATEWAY == 'Authorize.Net')
                || (ECOMMERCE_PAYMENT_GATEWAY == 'First Data Global Gateway')
                || (ECOMMERCE_PAYMENT_GATEWAY == 'PayPal Payflow Pro')
                || (ECOMMERCE_PAYMENT_GATEWAY == 'PayPal Payments Pro')
                || (ECOMMERCE_PAYMENT_GATEWAY == 'Sage')
            ) {
                // convert amount from cents to dollars
                $amount = sprintf("%01.2lf", $amount / 100);
                
                if ($billing_company) {
                    $profile_name = $billing_company;
                } else {
                    $profile_name = $billing_first_name . ' ' . $billing_last_name;
                }
                
                $profile_name .= ' - ' . $order_number;
                
                // if the recurring start date for the order item is less than or equal to today,
                // then the order item was included in non-recurring transaction, so determine actual start date for profile
                if ($product['recurring_start_date'] <= date('Y-m-d')) {
                    switch ($product['recurring_payment_period']) {
                        case 'Monthly':
                            $start_date = date($start_date_format, time() + 86400 * 30);
                            break;

                        case 'Weekly':
                            $start_date = date($start_date_format, time() + 86400 * 7);
                            break;

                        case 'Every Two Weeks':
                            $start_date = date($start_date_format, time() + 86400 * 14);
                            break;

                        case 'Twice every Month':
                            $start_date = date($start_date_format, time() + 86400 * 15);
                            break;

                        case 'Every Four Weeks':
                            $start_date = date($start_date_format, time() + 86400 * 28);
                            break;

                        case 'Quarterly':
                            $start_date = date($start_date_format, time() + 86400 * 90);
                            break;

                        case 'Twice every Year':
                            $start_date = date($start_date_format, time() + 86400 * 180);
                            break;

                        case 'Yearly':
                            $start_date = date($start_date_format, time() + 86400 * 365);
                            break;
                    }
                    
                // else the recurring start date is greater than today, so figure out start date from start value
                } else {
                    $start_date = date($start_date_format, strtotime($product['recurring_start_date']));
                }
            }
            
            // process the recurring transaction differently based on the payment gateway
            switch (ECOMMERCE_PAYMENT_GATEWAY) {
                case 'Authorize.Net':
                    // get the payment period for the payment period
                    switch ($product['recurring_payment_period']) {
                        case 'Monthly':  // 1 month
                            $payment_period_length = '1';
                            $payment_period_units = 'months';
                            break;
                        
                        case 'Weekly':  // 7 days
                            $payment_period_length = '7';
                            $payment_period_units = 'days';
                            break;
                        
                        case 'Every Two Weeks':  // 14 days
                            $payment_period_length = '14';
                            $payment_period_units = 'days';
                            break;
                        
                        case 'Twice every Month':  // 15 days
                            $payment_period_length = '15';
                            $payment_period_units = 'days';
                            break;
                        
                        case 'Every Four Weeks':  // 28 days
                            $payment_period_length = '28';
                            $payment_period_units = 'days';
                            break;
                        
                        case 'Quarterly':  //  3 months
                            $payment_period_length = '3';
                            $payment_period_units = 'months';
                            break;
                        
                        case 'Twice every Year':  //  6 months
                            $payment_period_length = '6';
                            $payment_period_units = 'months';
                            break;
                        
                        case 'Yearly':  // 12 months
                            $payment_period_length = '12';
                            $payment_period_units = 'months';
                            break;
                    }
                    
                    // if the number of payments is 0, then set number of payments to 9999, because Authorize.Net requires that for unlimited
                    if ($product['recurring_number_of_payments'] == 0) {
                        $number_of_payments = 9999;
                        
                    // else the number of payments is not 0, so set it to the number of payments
                    } else {
                        $number_of_payments = $product['recurring_number_of_payments'];
                    }
                    
                    // prepare the transaction XML
                    // Authorize.Net does not appear to support foreign currencies at the transaction level
                    // so we have not included anything for that.  Maybe they support changing that at the
                    // account/processor level.
                    $transaction_xml =
                        "<?xml version=\"1.0\" encoding=\"utf-8\"?>" .
                        "<ARBCreateSubscriptionRequest xmlns=\"AnetApi/xml/v1/schema/AnetApiSchema.xsd\">" .
                        "<merchantAuthentication>".
                        "<name>" . h(ECOMMERCE_AUTHORIZENET_API_LOGIN_ID) . "</name>".
                        "<transactionKey>" . h(ECOMMERCE_AUTHORIZENET_TRANSACTION_KEY) . "</transactionKey>".
                        "</merchantAuthentication>".
                            "<subscription>".
                            "<name>" . h($profile_name) . "</name>".
                                "<paymentSchedule>".
                                    "<interval>".
                                        "<length>". $payment_period_length . "</length>".
                                        "<unit>". $payment_period_units . "</unit>".
                                    "</interval>".
                                    "<startDate>" . $start_date . "</startDate>".
                                    "<totalOccurrences>". $number_of_payments . "</totalOccurrences>".
                                "</paymentSchedule>".
                                "<amount>". $amount . "</amount>".
                                "<payment>".
                                    "<creditCard>".
                                        "<cardNumber>" . h($card_number) . "</cardNumber>".
                                        "<expirationDate>" . h($expiration_year) . "-" . h($expiration_month) . "</expirationDate>".
                                    "</creditCard>".
                                "</payment>".
                                "<order>".
                                    "<invoiceNumber>" . $order_number . "</invoiceNumber>".
                                    "<description>" . h($profile_name) . "</description>".
                                "</order>".
                                "<customer>".
                                    "<email>" . h($billing_email_address) . "</email>".
                                "</customer>".
                                "<billTo>".
                                    "<firstName>". h($billing_first_name) . "</firstName>".
                                    "<lastName>" . h($billing_last_name) . "</lastName>".
                                    "<company>" . h($billing_company) . "</company>".
                                    "<address>" . h($billing_address_1) . ' ' . h($billing_address_2) . "</address>".
                                    "<city>" . h($billing_city) . "</city>".
                                    "<state>" . h($billing_state) . "</state>".
                                    "<zip>" . h($billing_zip_code) . "</zip>".
                                    "<country>" . h($billing_country) . "</country>".
                                "</billTo>".
                            "</subscription>".
                        "</ARBCreateSubscriptionRequest>";
                    
                    $ch = curl_init();
                    
                    curl_setopt($ch, CURLOPT_URL, $payment_gateway_host);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                    curl_setopt($ch, CURLOPT_HTTPHEADER, Array("Content-Type: text/xml"));
                    curl_setopt($ch, CURLOPT_HEADER, 1);
                    curl_setopt($ch, CURLOPT_TIMEOUT, 300);
                    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 0);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $transaction_xml);
                    curl_setopt($ch, CURLOPT_FORBID_REUSE, true);
                    curl_setopt($ch, CURLOPT_POST, 1);
                    pg_curl_tls($ch);
                    
                    // if there is a proxy address, then send cURL request through proxy
                    if (PROXY_ADDRESS != '') {
                        curl_setopt($ch, CURLOPT_HTTPPROXYTUNNEL, true);
                        curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_HTTP);
                        curl_setopt($ch, CURLOPT_PROXY, PROXY_ADDRESS);
                    }
                    
                    $response_data = curl_exec($ch);
                    unset($ch);
                    
                    break;
                    
                case 'ClearCommerce':
                    // set frequency
                    switch ($product['recurring_payment_period']) {
                        case 'Monthly':
                            $clearcommerce_OrderFrequencyCycle = 'M';
                            $clearcommerce_OrderFrequencyInterval = '1';
                            break;
                        
                        case 'Weekly':
                            $clearcommerce_OrderFrequencyCycle = 'W';
                            $clearcommerce_OrderFrequencyInterval = '1';
                            break;
                        
                        case 'Every Two Weeks':
                            $clearcommerce_OrderFrequencyCycle = 'W';
                            $clearcommerce_OrderFrequencyInterval = '2';
                            break;
                        
                        case 'Twice every Month':
                            $clearcommerce_OrderFrequencyCycle = 'D';
                            $clearcommerce_OrderFrequencyInterval = '15';
                            break;
                        
                        case 'Every Four Weeks':
                            $clearcommerce_OrderFrequencyCycle = 'W';
                            $clearcommerce_OrderFrequencyInterval = '4';
                            break;
                        
                        case 'Quarterly':
                            $clearcommerce_OrderFrequencyCycle = 'M';
                            $clearcommerce_OrderFrequencyInterval = '3';
                            break;
                        
                        case 'Twice every Year':
                            $clearcommerce_OrderFrequencyCycle = 'M';
                            $clearcommerce_OrderFrequencyInterval = '6';
                            break;
                        
                        case 'Yearly':
                            $clearcommerce_OrderFrequencyCycle = 'M';
                            $clearcommerce_OrderFrequencyInterval = '12';
                            break;
                    }
                    
                    $transaction_xml =
                        '<EngineDocList>
                            <DocVersion>1.0</DocVersion>
                            <EngineDoc>
                                <ContentType>OrderFormDoc</ContentType>
                                <User>
                                    <Name>' . h(ECOMMERCE_CLEARCOMMERCE_USER_ID) . '</Name>
                                    <Password>' . h(ECOMMERCE_CLEARCOMMERCE_PASSWORD) . '</Password>
                                    <Alias>' . h(ECOMMERCE_CLEARCOMMERCE_CLIENT_ID) . '</Alias>
                                </User>
                                <Instructions>
                                    <Pipeline>Payment</Pipeline>
                                </Instructions>
                                <OrderFormDoc>
                                    <Mode>' . $clearcommerce_mode . '</Mode>
                                    <Consumer>
                                        <PaymentMech>
                                            <CreditCard>
                                                <Number>' . $card_number . '</Number>
                                                <Expires DataType="ExpirationDate" Locale="840">' . h($expiration_month) . '/' . h($expiration_year) . '</Expires>
                                                <Cvv2Val>' . h($liveform->get_field_value('card_verification_number')) . '</Cvv2Val>
                                                <Cvv2Indicator>1</Cvv2Indicator>
                                            </CreditCard>
                                        </PaymentMech>
                                        <BillTo>
                                            <Location>
                                                <Address>
                                                    <Name>' . h($billing_first_name) . ' ' . h($billing_last_name) . '</Name>
                                                    <Company>' . h($billing_company) . '</Company>
                                                    <Street1>' . h($billing_address_1) . '</Street1>
                                                    <Street2>' . h($billing_address_2) . '</Street2>
                                                    <City>' . h($billing_city) . '</City>
                                                    <StateProv>' . h($billing_state) . '</StateProv>
                                                    <PostalCode>' . h($billing_zip_code) . '</PostalCode>
                                                    <Country>' . get_country_number($billing_country) . '</Country>
                                                </Address>
                                            </Location>
                                        </BillTo>
                                    </Consumer>
                                    <Transaction>
                                        <Type>' . $clearcommerce_type . '</Type>
                                        <CurrentTotals>
                                            <Totals>
                                                <Total DataType="Money" Currency="' . $clearcommerce_currency_code . '">' . $amount . '</Total>
                                            </Totals>
                                        </CurrentTotals>
                                    </Transaction>
                                    <PbOrder>
                                        <OrderType DataType="S32">0</OrderType>
                                        <TotalNumberPayments DataType="S32">' . $product['recurring_number_of_payments'] . '</TotalNumberPayments>
                                        <OrderFrequencyCycle>' . $clearcommerce_OrderFrequencyCycle . '</OrderFrequencyCycle>
                                        <OrderFrequencyInterval DataType="S32">' . $clearcommerce_OrderFrequencyInterval . '</OrderFrequencyInterval>
                                    </PbOrder>
                                </OrderFormDoc>
                            </EngineDoc>
                        </EngineDocList>';
                    
                    $ch = curl_init();
                    
                    curl_setopt($ch, CURLOPT_URL, $payment_gateway_host);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                    curl_setopt($ch, CURLOPT_TIMEOUT, 300);
                    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 0);
                    pg_curl_tls($ch);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, 'CLRCMRC_XML=' . urlencode($transaction_xml));
                    curl_setopt($ch, CURLOPT_FORBID_REUSE, true);
                    curl_setopt($ch, CURLOPT_POST, 1);
                    
                    // if there is a proxy address, then send cURL request through proxy
                    if (PROXY_ADDRESS != '') {
                        curl_setopt($ch, CURLOPT_HTTPPROXYTUNNEL, true);
                        curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_HTTP);
                        curl_setopt($ch, CURLOPT_PROXY, PROXY_ADDRESS);
                    }
                    
                    $response_data = curl_exec($ch);
                    unset($ch);
                    
                    break;
                    
                case 'First Data Global Gateway':
                    // Get the numerical portion of the 1st billing address.
                    preg_match('/[0-9]+/', $billing_address_1, $matches);
                    $avs_address_number = $matches[0];
                    
                    // set frequency
                    switch ($product['recurring_payment_period']) {
                        case 'Monthly':
                            $first_data_global_gateway_periodicity = 'monthly';
                            break;
                        
                        case 'Weekly':
                            $first_data_global_gateway_periodicity = 'weekly';
                            break;
                        
                        case 'Every Two Weeks':
                            $first_data_global_gateway_periodicity = 'biweekly';
                            break;
                        
                        case 'Twice every Month':
                            $first_data_global_gateway_periodicity = 'd15';
                            break;
                        
                        case 'Every Four Weeks':
                            $first_data_global_gateway_periodicity = 'w4';
                            break;
                        
                        case 'Quarterly':
                            $first_data_global_gateway_periodicity = 'm3';
                            break;
                        
                        case 'Twice every Year':
                            $first_data_global_gateway_periodicity = 'm6';
                            break;
                        
                        case 'Yearly':
                            $first_data_global_gateway_periodicity = 'yearly';
                            break;
                    }
                    
                    // Build the transaction XML
                    $transaction_xml =
                        '<order>
                            <billing>
                                <name>' . h($billing_first_name) . ' ' . h($billing_last_name) . '</name>
                                <company>' . h($billing_company) . '</company>
                                <address1>' . h($billing_address_1) . '</address1>
                                <address2>' . h($billing_address_2) . '</address2>
                                <city>' . h($billing_city) . '</city>
                                <state>' . h($billing_state) . '</state>
                                <zip>' . h($billing_zip_code) . '</zip>
                                <country>' . h($billing_country) . '</country>
                                <phone>' . h($billing_phone_number) . '</phone>
                                <fax>' . h($billing_fax_number) . '</fax>
                                <email>' . h($billing_email_address) . '</email>
                                <addrnum>' . $avs_address_number . '</addrnum>
                            </billing>
                            <transactiondetails>
                                <ip>' . h($_SERVER['REMOTE_ADDR']) . '</ip>
                            </transactiondetails>
                            <orderoptions>
                                <ordertype>' . $first_data_global_gateway_type . '</ordertype>
                                <result>' . $first_data_global_gateway_mode . '</result>
                            </orderoptions>
                            <payment>
                                <tax>' . $tax / 100 . '</tax>
                                <chargetotal>' . $amount . '</chargetotal>
                            </payment>
                            <periodic>
                                <action>SUBMIT</action>
                                <installments>' . $product['recurring_number_of_payments'] . '</installments>
                                <threshold>3</threshold>
                                <startdate>' . $start_date . '</startdate>
                                <periodicity>' . $first_data_global_gateway_periodicity . '</periodicity>
                            </periodic>
                            <creditcard>
                                <cardnumber>' . $card_number . '</cardnumber>
                                <cardexpmonth>' . h($expiration_month) . '</cardexpmonth>
                                <cardexpyear>' .  h(mb_substr($expiration_year, -2)) . '</cardexpyear>
                                <cvmvalue>' .  h($liveform->get_field_value('card_verification_number')) . '</cvmvalue>
                                <cvmindicator>provided</cvmindicator>
                            </creditcard>
                            <merchantinfo>
                                <configfile>' . h(ECOMMERCE_FIRST_DATA_GLOBAL_GATEWAY_STORE_NUMBER) . '</configfile>
                            </merchantinfo>
                        </order>';

                    $ch = curl_init();
                    
                    curl_setopt($ch, CURLOPT_URL, 'https://secure.linkpt.net:1129');
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                    curl_setopt($ch, CURLOPT_TIMEOUT, 300);
                    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 0);
                    // Setup an SSL connection using the supplied .pem file from First Data Global Gateway
                    curl_setopt($ch, CURLOPT_SSLCERT, FILE_DIRECTORY_PATH . '/' . ECOMMERCE_FIRST_DATA_GLOBAL_GATEWAY_PEM_FILE_NAME);
                    pg_curl_tls($ch);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $transaction_xml);
                    curl_setopt($ch, CURLOPT_FORBID_REUSE, true);
                    curl_setopt($ch, CURLOPT_POST, 1);
                    
                    // if there is a proxy address, then send cURL request through proxy
                    if (PROXY_ADDRESS != '') {
                        curl_setopt($ch, CURLOPT_HTTPPROXYTUNNEL, true);
                        curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_HTTP);
                        curl_setopt($ch, CURLOPT_PROXY, PROXY_ADDRESS);
                    }
                    
                    $response_data = curl_exec($ch);
                    unset($ch);

                    break;
                    
                case 'PayPal Payflow Pro':
                    // get the Verisign payment period code for the payment period
                    switch ($product['recurring_payment_period']) {
                        case 'Monthly':  // 1 month
                            $payment_period = 'MONT';
                            break;

                        case 'Weekly':  // 7 days
                            $payment_period = 'WEEK';
                            break;

                        case 'Every Two Weeks':  // 14 days
                            $payment_period = 'BIWK';
                            break;

                        case 'Twice every Month':  // 15 days
                            $payment_period = 'SMMO';
                            break;

                        case 'Every Four Weeks':  // 28 days
                            $payment_period = 'FRWK';
                            break;

                        case 'Quarterly':  //  3 months
                            $payment_period = 'QTER';
                            break;

                        case 'Twice every Year':  //  6 months
                            $payment_period = 'SMYR';
                            break;

                        case 'Yearly':  // 12 months
                            $payment_period = 'YEAR';
                            break;
                    }
                    
                    $transaction_values = array(
                        'PARTNER' => ECOMMERCE_PAYPAL_PAYFLOW_PRO_PARTNER,
                        'VENDOR'  => ECOMMERCE_PAYPAL_PAYFLOW_PRO_MERCHANT_LOGIN,
                        'USER'    => $payment_gateway_user,
                        'PWD'     => ECOMMERCE_PAYPAL_PAYFLOW_PRO_PASSWORD,
                        'TRXTYPE' => 'R',
                        'TENDER'  => 'C',
                        'ACTION'  => 'A',
                        'PROFILENAME' => $profile_name,
                        'AMT'     => $amount,
                        'CURRENCY' => BASE_CURRENCY_CODE,
                        'TAXAMT'  => sprintf("%01.2lf", $tax / 100),
                        'ACCT'    => $card_number,
                        'EXPDATE' => $expiration_month . mb_substr($expiration_year, -2),
                        'CVV2'    => $liveform->get_field_value('card_verification_number'),
                        'START'   => $start_date,
                        'TERM'    => $product['recurring_number_of_payments'],
                        'PAYPERIOD' => $payment_period,
                        'FIRSTNAME' => $billing_first_name,
                        'LASTNAME' => $billing_last_name,
                        'COMPANYNAME' => $billing_company,
                        'EMAIL' =>   $billing_email_address,
                        'STREET'  => $billing_address_1 . ' ' . $billing_address_2,
                        'CITY'    => $billing_city,
                        'STATE'    => $billing_state,
                        'ZIP'     => $billing_zip_code,
                        'COUNTRY' => $billing_country,
                        'COMMENT1' => $profile_name);
                    
                    // process the PayPal Payflow Pro transaction differently based on communication method
                    switch ($payment_gateway_communication_method) {
                        case 'curl':
                            $current_time_parts = gettimeofday();
                            $request_id = md5($current_time_parts['sec'] . '.' . $current_time_parts['usec']);
                            
                            $headers =
                                array(
                                    'Content-Type: text/namevalue',
                                    'X-VPS-VIT-Client-Certification-Id: 978cd78328cf44d58863f98e863f64dd',
                                    'X-VPS-Request-ID: ' . $request_id
                                );
                            
                            $post_data = '';
                            
                            foreach ($transaction_values as $name => $value) {
                                if ($post_data) {
                                    $post_data .= '&';
                                }
                                
                                $value = str_replace('"', '', $value);
                                
                                $post_data .= $name . '[' . strlen($value) . ']' . '=' . $value;
                            }
                            
                            $ch = curl_init();
                            
                            curl_setopt($ch, CURLOPT_URL, $payment_gateway_host);
                            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                            curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/4.0 (compatible; MSIE 5.01; Windows NT 5.0)');
                            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                            curl_setopt($ch, CURLOPT_TIMEOUT, 300);
                            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 0);
                            pg_curl_tls($ch);
                            curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
                            curl_setopt($ch, CURLOPT_FORBID_REUSE, true);
                            curl_setopt($ch, CURLOPT_POST, 1);
                            
                            // if there is a proxy address, then send cURL request through proxy
                            if (PROXY_ADDRESS != '') {
                                curl_setopt($ch, CURLOPT_HTTPPROXYTUNNEL, true);
                                curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_HTTP);
                                curl_setopt($ch, CURLOPT_PROXY, PROXY_ADDRESS);
                            }
                            
                            $response_data = curl_exec($ch);
                            unset($ch);
                            
                            break;
                            
                        case 'payflow pro com object':
                            $post_data = '';
                            
                            foreach ($transaction_values as $name => $value) {
                                if ($post_data) {
                                    $post_data .= '&';
                                }
                                
                                $value = str_replace('"', '', $value);
                                
                                $post_data .= $name . '[' . strlen($value) . ']' . '=' . $value;
                            }
                            
                            $payflowpro = new COM("PFProCOMControl.PFProCOMControl.1");

                            $context = $payflowpro->CreateContext($payment_gateway_host, 443, 30, '', 0, '', '');
                            $response_data = $payflowpro->SubmitTransaction($context, $post_data, strlen($post_data));
                            $payflowpro->DestroyContext($context);
                            
                            break;
                            
                        case 'pfpro extension':
                            $response = pfpro_process($transaction_values, $payment_gateway_host, 443, 30);
                            
                            break;
                    }
                    
                    break;
                    
                case 'PayPal Payments Pro':
                    // get the payment period and frequency for the payment profile
                    switch ($product['recurring_payment_period']) {
                        case 'Monthly':  // 1 month
                            $payment_period = 'Month';
                            $payment_frequency = '1';
                            break;

                        case 'Weekly':  // 1 week
                            $payment_period = 'Week';
                            $payment_frequency = '1';
                            break;

                        case 'Every Two Weeks':  // 2 weeks
                            $payment_period = 'Week';
                            $payment_frequency = '2';
                            break;

                        case 'Twice every Month':  // 2 weeks
                            $payment_period = 'Week';
                            $payment_frequency = '2';
                            break;

                        case 'Every Four Weeks':  // 4 weeks
                            $payment_period = 'Week';
                            $payment_frequency = '4';
                            break;

                        case 'Quarterly':  //  3 months
                            $payment_period = 'Month';
                            $payment_frequency = '3';
                            break;

                        case 'Twice every Year':  //  6 months
                            $payment_period = 'Month';
                            $payment_frequency = '6';
                            break;

                        case 'Yearly':  // 1 year
                            $payment_period = 'Year';
                            $payment_frequency = '1';
                            break;
                    }
                    
                    // Setup the transaction values
                    $transaction_values = array(
                        'METHOD' => 'CreateRecurringPaymentsProfile',
                        'VERSION' => '50.0',
                        'PWD' => ECOMMERCE_PAYPAL_PAYMENTS_PRO_API_PASSWORD,
                        'USER' => ECOMMERCE_PAYPAL_PAYMENTS_PRO_API_USERNAME,
                        'SIGNATURE' => ECOMMERCE_PAYPAL_PAYMENTS_PRO_API_SIGNATURE,
                        'SUBSCRIBERNAME' => $billing_first_name . ' ' . $billing_last_name,
                        'EMAIL' =>   $billing_email_address,
                        'PROFILESTARTDATE' => $start_date,
                        'PROFILEREFERENCE' => $profile_name,
                        'DESC' => $product['short_description'],
                        'BILLINGPERIOD' => $payment_period,
                        'BILLINGFREQUENCY' => $payment_frequency,
                        'TOTALBILLINGCYCLES' => $product['recurring_number_of_payments'],
                        'AMT' => sprintf("%01.2lf", $price / 100),
                        'TAXAMT'  => sprintf("%01.2lf", $tax / 100),
                        'CREDITCARDTYPE' => $credit_card_type,
                        'ACCT' => $card_number,
                        'EXPDATE' => $expiration_month . $expiration_year,
                        'CVV2' => $liveform->get_field_value('card_verification_number'),
                        'FIRSTNAME' => $billing_first_name,
                        'LASTNAME' => $billing_last_name,
                        'STREET' => $billing_address_1 . ' ' . $billing_address_2, // using STREET instead of STREET1 and STREET2 because they do not appear to work correctly, even though API says they should
                        'CITY' => $billing_city,
                        'STATE' => $billing_state,
                        'ZIP' => $billing_zip_code,
                        'COUNTRYCODE' => $billing_country,
                        'CURRENCYCODE' => BASE_CURRENCY_CODE);
                    
                    $post_data = '';
                    
                    // Place the transaction values into the correct format
                    foreach ($transaction_values as $name => $value) {
                        if ($post_data) {
                            $post_data .= '&';
                        }
                        
                        $value = str_replace('"', '', $value);
                        
                        $post_data .= $name . '=' . urlencode($value);
                    }
                    
                    $ch = curl_init();
                    
                    curl_setopt($ch, CURLOPT_URL, $payment_gateway_host);
                    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/4.0 (compatible; MSIE 5.01; Windows NT 5.0)');
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                    curl_setopt($ch, CURLOPT_TIMEOUT, 300);
                    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 0);
                    pg_curl_tls($ch);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
                    curl_setopt($ch, CURLOPT_FORBID_REUSE, true);
                    curl_setopt($ch, CURLOPT_POST, 1);
                    
                    // if there is a proxy address, then send cURL request through proxy
                    if (PROXY_ADDRESS != '') {
                        curl_setopt($ch, CURLOPT_HTTPPROXYTUNNEL, true);
                        curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_HTTP);
                        curl_setopt($ch, CURLOPT_PROXY, PROXY_ADDRESS);
                    }
                    
                    $response_data = curl_exec($ch);
                    unset($ch);
                    
                    // parse response
                    parse_str($response_data, $response);
                    
                    // if a recurring profile id was returned, then set recurring profile id for order item
                    if (isset($response['PROFILEID']) == true) {
                        $query =
                            "UPDATE order_items
                            SET
                                recurring_profile_id = ?,
                                recurring_profile_enabled = '1'
                            WHERE id = ?";
                        $result = db_q($query, array((string) $response['PROFILEID'], (string) $product['order_item_id'])) or output_error('Query failed.');
                    }
                    
                    break;

                case 'Iyzipay':
                
                    // This product is configured for recurring payments.
                    // Iyzipay does not use recurring profile IDs like PayPal.
                    // Instead, we rely on stored card tokens for future charges.
                
                    // Mark the order item as recurring-enabled
                    $query = "
                        UPDATE order_items
                        SET recurring_profile_enabled = '1'
                        WHERE id = ?";
                    $result = db_q($query, array((string) $product['order_item_id'])) or output_error('Query failed.');
                
                    break;


                case 'Sage':
                    $payment_gateway_error_message = '';
                
                    $sage_shipping_recipient = '';
                    $sage_shipping_address = '';
                    $sage_shipping_city = '';
                    $sage_shipping_state = '';
                    $sage_shipping_zip = '';
                    $sage_shipping_country = '';
                    
                    // if this order item is part of a ship to, then get shipping information
                    if ($product['ship_to_id'] != 0) {
                        $query =
                            "SELECT
                                first_name,
                                last_name,
                                address_1,
                                address_2,
                                city,
                                state,
                                zip_code,
                                country
                            FROM ship_tos
                            WHERE id = ?";
                        $result = db_q($query, array((string) $product['ship_to_id'])) or output_error('Query failed.');
                        $row = mysqli_fetch_assoc($result);
                        
                        $sage_shipping_recipient = $row['first_name'] . ' ' . $row['last_name'];
                        $sage_shipping_address = $row['address_1'] . ' ' . $row['address_2'];
                        $sage_shipping_city = $row['city'];
                        $sage_shipping_state = $row['state'];
                        $sage_shipping_zip = $row['zip_code'];
                        $sage_shipping_country = $row['country'];
                    }
                    
                    // create recurring customer
                    
                    // all of these fields are required to be sent even though some do not have values
                    $transaction_values = array(
                        'M_ID' => ECOMMERCE_SAGE_MERCHANT_ID,
                        'M_KEY' => ECOMMERCE_SAGE_MERCHANT_KEY,
                        'CUSTOMER_TYPE' => '1', // "1" is Active and "0" is Inactive.
                        'FIRST_NAME' => $billing_first_name,
                        'LAST_NAME' => $billing_last_name,
                        'ADDRESS' => $billing_address_1 . ' ' . $billing_address_2,
                        'CITY' => $billing_city,
                        'STATE' => $billing_state,
                        'ZIP' => $billing_zip_code,
                        'COUNTRY' => $billing_country,
                        'TELEPHONE' => $billing_phone_number,
                        'FAX' => $billing_fax_number,
                        'EMAIL_ADDRESS' => $billing_email_address,
                        'SHIPPING_RECIPIENT' => $sage_shipping_recipient,
                        'SHIPPING_ADDRESS' => $sage_shipping_address,
                        'SHIPPING_CITY' => $sage_shipping_city,
                        'SHIPPING_STATE' => $sage_shipping_state,
                        'SHIPPING_ZIP' => $sage_shipping_zip,
                        'SHIPPING_COUNTRY' => $sage_shipping_country,
                        'HOLD' => '0', // we don't know what this is
                        'HOLD_MESSAGE' => '', // we don't know what this is
                        'REFERENCE' => $order_number
                    );
                    
                    $post_data = '';
                    
                    foreach ($transaction_values as $name => $value) {
                        if ($post_data) {
                            $post_data .= '&';
                        }
                        
                        $post_data .= $name . '=' . urlencode($value);
                    }
                    
                    // Setup cURL options.
                    $ch = curl_init();
                    curl_setopt($ch, CURLOPT_URL, 'https://www.sagepayments.net/web_services/vterm_extensions/recurring.asmx/CREATE_RECURRING_CUSTOMER');
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                    curl_setopt($ch, CURLOPT_TIMEOUT, 300);
                    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 0);
                    pg_curl_tls($ch);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
                    curl_setopt($ch, CURLOPT_FORBID_REUSE, true);
                    curl_setopt($ch, CURLOPT_POST, 1);
                    
                    // if there is a proxy address, then send cURL request through proxy
                    if (PROXY_ADDRESS != '') {
                        curl_setopt($ch, CURLOPT_HTTPPROXYTUNNEL, true);
                        curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_HTTP);
                        curl_setopt($ch, CURLOPT_PROXY, PROXY_ADDRESS);
                    }
                    
                    $response_data = curl_exec($ch);
                    
                    $curl_errno = curl_errno($ch);
                    $curl_error = curl_error($ch);
                    
                    unset($ch);
                    
                    // if there was an error then prepare error message
                    if (($response_data === FALSE) || (mb_strpos($response_data, '<CUSTOMER_ID>') === FALSE)) {
                        $payment_gateway_error_message = 'A recurring profile could not be created for order number ' . $order_number . ' due to a communication problem with the payment gateway. The CREATE_RECURRING_CUSTOMER transaction failed. The recurring profile will need to be setup manually in your payment gateway\'s control panel. The order was still accepted.';
                        
                        // if there was a cURL error, add error number and error message to payment gateway error message
                        if ($curl_errno) {
                            $payment_gateway_error_message .= ' cURL Error Number: ' . $curl_errno . '. cURL Error Message: ' . $curl_error . '.';
                        }
                        
                        // if the response was not empty, then add response to the end of the error message
                        if (trim($response_data) != '') {
                            $payment_gateway_error_message .= ' ' . trim($response_data);
                        }
                        
                    // else there was not an error, so get customer ID and continue with the rest of the recurring transactions
                    } else {
                        // get customer ID
                        preg_match('/<CUSTOMER_ID>(.*?)<\/CUSTOMER_ID>/', $response_data, $matches);
                        $sage_customer_id = $matches[1];
                        
                        $sage_group_id = $product['sage_group_id'];
                        
                        // if this order item's product does not have a Sage group ID, then create recurring group
                        if ($sage_group_id == 0) {
                            // create recurring group
                            
                            $transaction_values = array(
                                'M_ID' => ECOMMERCE_SAGE_MERCHANT_ID,
                                'M_KEY' => ECOMMERCE_SAGE_MERCHANT_KEY,
                                'GROUP_DESCRIPTION' => $profile_name
                            );
                            
                            $post_data = '';
                            
                            foreach ($transaction_values as $name => $value) {
                                if ($post_data) {
                                    $post_data .= '&';
                                }
                                
                                $post_data .= $name . '=' . urlencode($value);
                            }
                            
                            // Setup cURL options.
                            $ch = curl_init();
                            curl_setopt($ch, CURLOPT_URL, 'https://www.sagepayments.net/web_services/vterm_extensions/recurring.asmx/CREATE_RECURRING_GROUP');
                            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                            curl_setopt($ch, CURLOPT_TIMEOUT, 300);
                            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 0);
                            pg_curl_tls($ch);
                            curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
                            curl_setopt($ch, CURLOPT_FORBID_REUSE, true);
                            curl_setopt($ch, CURLOPT_POST, 1);
                            
                            // if there is a proxy address, then send cURL request through proxy
                            if (PROXY_ADDRESS != '') {
                                curl_setopt($ch, CURLOPT_HTTPPROXYTUNNEL, true);
                                curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_HTTP);
                                curl_setopt($ch, CURLOPT_PROXY, PROXY_ADDRESS);
                            }
                            
                            $response_data = curl_exec($ch);
                            
                            $curl_errno = curl_errno($ch);
                            $curl_error = curl_error($ch);
                            
                            unset($ch);
                            
                            // if there was an error then prepare error message
                            if (($response_data === FALSE) || (mb_strpos($response_data, '<GROUP_ID>') === FALSE)) {
                                $payment_gateway_error_message = 'A recurring profile could not be created for order number ' . $order_number . ' due to a communication problem with the payment gateway. The CREATE_RECURRING_GROUP transaction failed. The recurring profile will need to be setup manually in your payment gateway\'s control panel. The order was still accepted.';
                                
                                // if there was a cURL error, add error number and error message to payment gateway error message
                                if ($curl_errno) {
                                    $payment_gateway_error_message .= ' cURL Error Number: ' . $curl_errno . '. cURL Error Message: ' . $curl_error . '.';
                                }
                                
                                // if the response was not empty, then add response to the end of the error message
                                if (trim($response_data) != '') {
                                    $payment_gateway_error_message .= ' ' . trim($response_data);
                                }
                                
                            // else there was not an error, so get group ID
                            } else {
                                // get customer ID
                                preg_match('/<GROUP_ID>(.*?)<\/GROUP_ID>/', $response_data, $matches);
                                $sage_group_id = $matches[1];
                            }
                        }
                        
                        // if there was not an error getting/creating the group, then continue with the rest of the recurring transactions
                        if ($payment_gateway_error_message == '') {
                            // create either a monthly or daily schedule based on the payment period
                            switch ($product['recurring_payment_period']) {
                                case 'Monthly':
                                case 'Quarterly':
                                case 'Twice every Year':
                                case 'Yearly':
                                    // create recurring monthly schedule
                                    
                                    $sage_monthly_interval = '';
                                    
                                    // set monthly interval differently based on the payment period
                                    switch ($product['recurring_payment_period']) {
                                        case 'Monthly':
                                            $sage_monthly_interval = '1';
                                            break;
                                            
                                        case 'Quarterly':
                                            $sage_monthly_interval = '3';
                                            break;
                                        
                                        case 'Twice every Year':
                                            $sage_monthly_interval = '6';
                                            break;
                                        
                                        case 'Yearly':
                                            $sage_monthly_interval = '12';
                                            break;
                                    }
                                    
                                    $start_date_parts = explode('/', $start_date);
                                    $sage_day_of_month = $start_date_parts[1];
                                    
                                    // all of these fields are required to be sent even though some do not have values
                                    $transaction_values = array(
                                        'M_ID' => ECOMMERCE_SAGE_MERCHANT_ID,
                                        'M_KEY' => ECOMMERCE_SAGE_MERCHANT_KEY,
                                        'SCHEDULE_DESCRIPTION' => $profile_name . ' - ' . $product['recurring_payment_period'],
                                        'MONTHLY_INTERVAL' => $sage_monthly_interval,
                                        'DAY_OF_MONTH' => $sage_day_of_month,
                                        'NON_BUSINESS_DAYS' => '2', // that day (not before or after)
                                        'START_OFFSET' => '0' // we don't know what this is
                                    );
                                    
                                    $post_data = '';
                                    
                                    foreach ($transaction_values as $name => $value) {
                                        if ($post_data) {
                                            $post_data .= '&';
                                        }
                                        
                                        $post_data .= $name . '=' . urlencode($value);
                                    }
                                    
                                    // Setup cURL options.
                                    $ch = curl_init();
                                    curl_setopt($ch, CURLOPT_URL, 'https://www.sagepayments.net/web_services/vterm_extensions/recurring.asmx/CREATE_RECURRING_MONTHLY_SCHEDULE');
                                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                                    curl_setopt($ch, CURLOPT_TIMEOUT, 300);
                                    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 0);
                                    pg_curl_tls($ch);
                                    curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
                                    curl_setopt($ch, CURLOPT_FORBID_REUSE, true);
                                    curl_setopt($ch, CURLOPT_POST, 1);
                                    
                                    // if there is a proxy address, then send cURL request through proxy
                                    if (PROXY_ADDRESS != '') {
                                        curl_setopt($ch, CURLOPT_HTTPPROXYTUNNEL, true);
                                        curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_HTTP);
                                        curl_setopt($ch, CURLOPT_PROXY, PROXY_ADDRESS);
                                    }
                                    
                                    $response_data = curl_exec($ch);
                                    
                                    $curl_errno = curl_errno($ch);
                                    $curl_error = curl_error($ch);
                                    
                                    unset($ch);
                                    
                                    // if there was an error then prepare error message
                                    if (($response_data === FALSE) || (mb_strpos($response_data, '<SCHEDULE_ID>') === FALSE)) {
                                        $payment_gateway_error_message = 'A recurring profile could not be created for order number ' . $order_number . ' due to a communication problem with the payment gateway. The CREATE_RECURRING_MONTHLY_SCHEDULE transaction failed. The recurring profile will need to be setup manually in your payment gateway\'s control panel. The order was still accepted.';
                                        
                                        // if there was a cURL error, add error number and error message to payment gateway error message
                                        if ($curl_errno) {
                                            $payment_gateway_error_message .= ' cURL Error Number: ' . $curl_errno . '. cURL Error Message: ' . $curl_error . '.';
                                        }
                                        
                                        // if the response was not empty, then add response to the end of the error message
                                        if (trim($response_data) != '') {
                                            $payment_gateway_error_message .= ' ' . trim($response_data);
                                        }
                                        
                                    // else there was not an error, so get schedule ID
                                    } else {
                                        preg_match('/<SCHEDULE_ID>(.*?)<\/SCHEDULE_ID>/', $response_data, $matches);
                                        $sage_schedule_id = $matches[1];
                                    }
                                    
                                    break;
                                    
                                case 'Weekly':
                                case 'Every Two Weeks':
                                case 'Twice every Month':
                                case 'Every Four Weeks':
                                    // create recurring daily schedule
                                    
                                    $sage_daily_interval = '';
                                    
                                    // set daily interval differently based on the payment period
                                    switch ($product['recurring_payment_period']) {
                                        case 'Weekly':
                                            $sage_daily_interval = '7';
                                            break;
                                            
                                        case 'Every Two Weeks':
                                            $sage_daily_interval = '14';
                                            break;
                                        
                                        case 'Twice every Month':
                                            $sage_daily_interval = '15';
                                            break;
                                        
                                        case 'Every Four Weeks':
                                            $sage_daily_interval = '28';
                                            break;
                                    }
                                    
                                    $transaction_values = array(
                                        'M_ID' => ECOMMERCE_SAGE_MERCHANT_ID,
                                        'M_KEY' => ECOMMERCE_SAGE_MERCHANT_KEY,
                                        'SCHEDULE_DESCRIPTION' => $profile_name . ' - ' . $product['recurring_payment_period'],
                                        'DAILY_INTERVAL' => $sage_daily_interval,
                                        'START_DATE' => $start_date,
                                        'NON_BUSINESS_DAYS' => '2' // that day (not before or after)
                                    );
                                    
                                    $post_data = '';
                                    
                                    foreach ($transaction_values as $name => $value) {
                                        if ($post_data) {
                                            $post_data .= '&';
                                        }
                                        
                                        $post_data .= $name . '=' . urlencode($value);
                                    }
                                    
                                    // Setup cURL options.
                                    $ch = curl_init();
                                    curl_setopt($ch, CURLOPT_URL, 'https://www.sagepayments.net/web_services/vterm_extensions/recurring.asmx/CREATE_RECURRING_DAILY_SCHEDULE');
                                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                                    curl_setopt($ch, CURLOPT_TIMEOUT, 300);
                                    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 0);
                                    pg_curl_tls($ch);
                                    curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
                                    curl_setopt($ch, CURLOPT_FORBID_REUSE, true);
                                    curl_setopt($ch, CURLOPT_POST, 1);
                                    
                                    // if there is a proxy address, then send cURL request through proxy
                                    if (PROXY_ADDRESS != '') {
                                        curl_setopt($ch, CURLOPT_HTTPPROXYTUNNEL, true);
                                        curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_HTTP);
                                        curl_setopt($ch, CURLOPT_PROXY, PROXY_ADDRESS);
                                    }
                                    
                                    $response_data = curl_exec($ch);
                                    
                                    $curl_errno = curl_errno($ch);
                                    $curl_error = curl_error($ch);
                                    
                                    unset($ch);
                                    
                                    // if there was an error then prepare error message
                                    if (($response_data === FALSE) || (mb_strpos($response_data, '<SCHEDULE_ID>') === FALSE)) {
                                        $payment_gateway_error_message = 'A recurring profile could not be created for order number ' . $order_number . ' due to a communication problem with the payment gateway. The CREATE_RECURRING_DAILY_SCHEDULE transaction failed. The recurring profile will need to be setup manually in your payment gateway\'s control panel. The order was still accepted.';
                                        
                                        // if there was a cURL error, add error number and error message to payment gateway error message
                                        if ($curl_errno) {
                                            $payment_gateway_error_message .= ' cURL Error Number: ' . $curl_errno . '. cURL Error Message: ' . $curl_error . '.';
                                        }
                                        
                                        // if the response was not empty, then add response to the end of the error message
                                        if (trim($response_data) != '') {
                                            $payment_gateway_error_message .= ' ' . trim($response_data);
                                        }
                                        
                                    // else there was not an error, so get schedule ID
                                    } else {
                                        preg_match('/<SCHEDULE_ID>(.*?)<\/SCHEDULE_ID>/', $response_data, $matches);
                                        $sage_schedule_id = $matches[1];
                                    }
                                    
                                    break;
                            }
                            
                            // if there was not an error creating schedule, then continue to process the final recurring transaction
                            if ($payment_gateway_error_message == '') {
                                // create recurring bankcard payer and transaction
                                
                                // if the number of payments is 0, then set values so recurring profile does not expire
                                if ($product['recurring_number_of_payments'] == 0) {
                                    $sage_times_to_process = '0';
                                    $sage_indefinite = '1';
                                    
                                // else the number of payments is not 0, so set values so recurring profile will expire
                                } else {
                                    $sage_times_to_process = $product['recurring_number_of_payments'];
                                    $sage_indefinite = '0';
                                }
                                
                                $transaction_values = array(
                                    'M_ID' => ECOMMERCE_SAGE_MERCHANT_ID,
                                    'M_KEY' => ECOMMERCE_SAGE_MERCHANT_KEY,
                                    'ACTIVE' => '1',
                                    'CUSTOMER_ID' => $sage_customer_id,
                                    'FIRST_NAME' => $billing_first_name,
                                    'LAST_NAME' => $billing_last_name,
                                    'ADDRESS' => $billing_address_1 . ' ' . $billing_address_2,
                                    'CITY' => $billing_city,
                                    'STATE' => $billing_state,
                                    'ZIP' => $billing_zip_code,
                                    'COUNTRY' => $billing_country,
                                    'EMAIL_ADDRESS' => $billing_email_address,
                                    'SHIPPING_RECIPIENT' => $sage_shipping_recipient,
                                    'SHIPPING_ADDRESS' => $sage_shipping_address,
                                    'SHIPPING_CITY' => $sage_shipping_city,
                                    'SHIPPING_STATE' => $sage_shipping_state,
                                    'SHIPPING_ZIP' => $sage_shipping_zip,
                                    'SHIPPING_COUNTRY' => $sage_shipping_country,
                                    'CARDNUMBER' => $card_number,
                                    'CARD_EXP_MONTH' => $expiration_month,
                                    'CARD_EXP_YEAR' => mb_substr($expiration_year, -2),
                                    'GROUP_ID' => $sage_group_id,
                                    'SCHEDULE_ID' => $sage_schedule_id,
                                    'AMOUNT' => $amount,
                                    'T_CODE' => $sage_transaction_type,
                                    'TIMES_TO_PROCESS' => $sage_times_to_process,
                                    'INDEFINITE' => $sage_indefinite,
                                    'REFERENCE' => $order_number,
                                    'START_DATE' => $start_date
                                );
                                
                                $post_data = '';
                                
                                foreach ($transaction_values as $name => $value) {
                                    if ($post_data) {
                                        $post_data .= '&';
                                    }
                                    
                                    $post_data .= $name . '=' . urlencode($value);
                                }
                                
                                // Setup cURL options.
                                $ch = curl_init();
                                curl_setopt($ch, CURLOPT_URL, 'https://www.sagepayments.net/web_services/vterm_extensions/recurring.asmx/CREATE_RECURRING_BANKCARD_PAYER_AND_TRANSACTION');
                                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                                curl_setopt($ch, CURLOPT_TIMEOUT, 300);
                                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 0);
                                pg_curl_tls($ch);
                                curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
                                curl_setopt($ch, CURLOPT_FORBID_REUSE, true);
                                curl_setopt($ch, CURLOPT_POST, 1);
                                
                                // if there is a proxy address, then send cURL request through proxy
                                if (PROXY_ADDRESS != '') {
                                    curl_setopt($ch, CURLOPT_HTTPPROXYTUNNEL, true);
                                    curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_HTTP);
                                    curl_setopt($ch, CURLOPT_PROXY, PROXY_ADDRESS);
                                }
                                
                                $response_data = curl_exec($ch);
                                
                                $curl_errno = curl_errno($ch);
                                $curl_error = curl_error($ch);
                                
                                unset($ch);
                                
                                // if there was an error then prepare error message
                                if (($response_data === FALSE) || (mb_strpos($response_data, '<RECURRING_ID>') === FALSE)) {
                                    $payment_gateway_error_message = 'A recurring profile could not be created for order number ' . $order_number . ' due to a communication problem with the payment gateway. The CREATE_RECURRING_BANKCARD_PAYER_AND_TRANSACTION transaction failed. The recurring profile will need to be setup manually in your payment gateway\'s control panel. The order was still accepted.';
                                    
                                    // if there was a cURL error, add error number and error message to payment gateway error message
                                    if ($curl_errno) {
                                        $payment_gateway_error_message .= ' cURL Error Number: ' . $curl_errno . '. cURL Error Message: ' . $curl_error . '.';
                                    }
                                    
                                    // if the response was not empty, then add response to the end of the error message
                                    if (trim($response_data) != '') {
                                        $payment_gateway_error_message .= ' ' . trim($response_data);
                                    }
                                }
                            }
                        }
                    }
                    
                    // if there was an error creating recurring profile with Sage, then log activity and e-mail administrator
                    if ($payment_gateway_error_message != '') {

                        log_activity($payment_gateway_error_message, $_SESSION['sessionusername'] ?? '');

                        email(array(
                            'to' => ECOMMERCE_EMAIL_ADDRESS,
                            'from_name' => ORGANIZATION_NAME,
                            'from_email_address' => EMAIL_ADDRESS,
                            'subject' => 'Recurring profile error',
                            'body' => $payment_gateway_error_message));

                    }
                    
                    break;
            }
        }
    }

    // store order id as completed order id so it can be found by order receipt screen
    $_SESSION['ecommerce']['completed_order_id'] = ($_SESSION['ecommerce']['order_id'] ?? '');

    // Add values to session for order number and total so they can be easily accessed
    // for outputting to conversion tracking JavaScript services and etc.
    $_SESSION['software']['order_number'] = $order_number;
    $_SESSION['software']['order_total'] = sprintf("%01.2lf", $total / 100);
    
    // E-mail the receipt, when the checkout page sends one
    // (includes/fn/order_completion.php).
    pg_order_complete_receipt(
        (int) ($_SESSION['ecommerce']['order_id'] ?? 0),
        $order_number,
        array(
            'email' => $order_receipt_email,
            'subject' => $order_receipt_email_subject,
            'format' => $order_receipt_email_format,
            'header' => $order_receipt_email_header,
            'footer' => $order_receipt_email_footer,
            'page_id' => $order_receipt_email_page_id,
        ),
        $billing_email_address,
        pg_order_new_account_email() !== ''
    );

    // Each recipient's ship and delivery dates, and the address book of an
    // account auto-registration created or found.
    pg_order_complete_ship_dates((int) ($_SESSION['ecommerce']['order_id'] ?? 0), $order_date, $auto_registration_type, $user_id);

    // The order_completed auto e-mail campaigns.
    pg_order_complete_campaigns($contact_id);
    
    // initialize array that will be used to store all of the contact groups that the contact should be added to
    $contact_groups = array();
    
    // intialize variable to keep track of the total reward points that products should give for this order
    $reward_points = 0;

    // The stock each product was left with by the rows above, so a product
    // that comes twice (two recipients) announces its second row from what
    // the first one left, not from the stock read before the loop.
    $stock_left = array();

    // The order's values a line's submitted form, comment and e-mailed page
    // can name as variables (includes/fn/order_completion.php).
    $order_variables = compact(
        'billing_salutation', 'billing_first_name', 'billing_last_name', 'billing_company',
        'billing_address_1', 'billing_address_2', 'billing_city', 'billing_state',
        'billing_zip_code', 'billing_country', 'billing_phone_number', 'billing_fax_number',
        'billing_email_address', 'custom_field_1', 'custom_field_2', 'po_number',
        'total', 'order_number', 'order_date');

    // loop through all ordered products in order to determine if things need to be done for each product
    foreach ($products as $product) {
        $product_id = $product['product_id'];
        $name = $product['product_name'];
        $short_description = $product['short_description'];
        $quantity = $product['quantity'];
        $product_price = sprintf("%01.2lf", $product['price'] / 100);
        $contact_group_id = $product['contact_group_id'];

        // Stock and a calendar event's spots (includes/fn/order_completion.php).
        pg_order_complete_line_stock($product, $stock_left, $_SESSION['sessionusername'] ?? '');
        pg_order_complete_line_reservation($product, $contact_id);

        // if contact group has not already been added to array, add it to array
        if (in_array($contact_group_id, $contact_groups) == false) {
            $contact_groups[] = $contact_group_id;
        }

        // A membership renewal adds the contact to the membership contact
        // group, when one is selected.
        if (
            pg_order_complete_line_membership($product, $contact_id, $reference_code)
            && (MEMBERSHIP_CONTACT_GROUP_ID != 0)
            && (in_array(MEMBERSHIP_CONTACT_GROUP_ID, $contact_groups) == false)
        ) {
            $contact_groups[] = MEMBERSHIP_CONTACT_GROUP_ID;
        }

        // Private folder access and the send-to page.
        pg_order_complete_line_folder_access($product, $user_id);

        // The reward points the line gives (includes/fn/order_completion.php).
        $reward_points += pg_order_line_reward_points($product, ECOMMERCE_REWARD_PROGRAM == true);

        // Gift cards and their e-mails (includes/fn/order_completion.php).
        pg_order_complete_line_gift_cards($product, (int) ($_SESSION['ecommerce']['order_id'] ?? 0), USER_ID, $_SESSION['sessionusername'] ?? '');

        // If this product creates/updates a submitted a form, adds a comment,
        // or emails a page, then prepare variable fields where variables can
        // be replaced with dynamic data from the order, and the product form
        // answers of each unit (includes/fn/order_completion.php).
        if (
            ($product['submit_form'])
            || ($product['add_comment'])
            || ($product['email_page'])
        ) {
            $variable_fields = pg_order_line_variable_fields($order_variables, $quantity);
            $product_form_variable_fields = pg_order_line_product_form_fields($product['order_item_id']);
        }

        // Create an array that will be used to keep track of whether a submitted form
        // was created or updated for each quantity number, and which submitted form
        // was created/updated.  We will need this info later when we add a comment.
        $forms = array();

        // If this product submits a form, then deal with that.
        if (
            ($product['submit_form'])
            && ($product['submit_form_custom_form_page_id'])
            &&
            (
                ($product['submit_form_create'])
                || ($product['submit_form_update'])
            )
        ) {
            // The user the line names as a watcher, so they are added to the form
            // and the page and get the submitter e-mail (includes/fn/order_completion.php).
            $add_watcher_user = pg_order_line_watcher($product);

            $pretty_urls = check_if_pretty_urls_are_enabled($product['submit_form_custom_form_page_id']);

            // If the quantity type is "one form per quantity",
            // then set the number of forms to the quantity.
            if ($product['submit_form_quantity_type'] == 'One Form per Quantity') {
                $number_of_forms = $quantity;
                
            // Otherwise the quantity type is "one form per product",
            // so set the number of forms to one.
            } else  {
                $number_of_forms = 1;
            }

            for ($quantity_number = 1; $quantity_number <= $number_of_forms; $quantity_number++) {

                // Assume that a submitted form was not updated until we determine that we need to do that.
                // We use this in order to determine if we need to create a form.
                $submitted_form_updated = false;

                // The unit's own product form, else the first unit's.
                $product_form_quantity_number = pg_order_product_form_number($quantity_number, $product_form_variable_fields);

                // Update the oldest submitted form that matches, when the
                // product updates one (includes/fn/order_completion.php).
                $submitted_form_id = pg_order_complete_line_form_update(
                    $product,
                    $variable_fields,
                    $product_form_quantity_number ? $product_form_variable_fields[$product_form_quantity_number] : null,
                    USER_ID,
                    $pretty_urls);

                if ($submitted_form_id) {
                    // Remember that a submitted form was updated, so we know further below to not create a form.
                    $submitted_form_updated = true;

                    // Remember that a form was updated for this quantity number,
                    // for use later when we add a comment.
                    $forms[$quantity_number] = array(
                        'id' => $submitted_form_id,
                        'type' => 'updated');
                }

                // If submit form create is enabled, and a submitted form was not updated up above,
                // then create a submitted form, its e-mails and campaigns; its origin
                // comes from the order row (includes/fn/order_completion.php).
                if (
                    ($product['submit_form_create'])
                    && ($submitted_form_updated == false)
                ) {
                    $created_form_id = pg_order_complete_line_form_create(
                        $product,
                        array(
                            'id' => (int) ($_SESSION['ecommerce']['order_id'] ?? 0),
                            'user_id' => $user_id,
                            'contact_id' => $contact_id,
                            'billing_email_address' => $billing_email_address),
                        $variable_fields,
                        $product_form_quantity_number ? $product_form_variable_fields[$product_form_quantity_number] : null,
                        $add_watcher_user,
                        $pretty_urls);

                    if ($created_form_id) {
                        $submitted_form_id = $created_form_id;

                        // Remember that a form was created for this quantity number,
                        // for use later when we add a comment.
                        $forms[$quantity_number] = array(
                            'id' => $submitted_form_id,
                            'type' => 'created');
                    }
                }

                // The watcher watches the unit's form on the add comment page.
                pg_order_complete_line_watcher($product, $add_watcher_user, $submitted_form_id);

            }
            
        }

        // The line's comments, its e-mailed page and its product_ordered
        // campaigns (includes/fn/order_completion.php).
        pg_order_complete_line_comments($product, $variable_fields ?? array(), $product_form_variable_fields ?? array(), $forms);
        pg_order_complete_line_email_page($product, $billing_email_address, $variable_fields ?? array(), $product_form_variable_fields ?? array(), $forms);
        pg_order_complete_line_campaigns($product, $contact_id);
    }

    // Private access for ever to the store's private folder
    // (includes/fn/order_completion.php).
    pg_order_complete_private_access($user_id);

    // The reward points, and the membership and e-mail of an order that
    // reaches the program's goal.
    if (
        pg_order_complete_rewards($user_id, $contact_id, $reward_points, $order_number, $billing_email_address)
        && (MEMBERSHIP_CONTACT_GROUP_ID != 0)
        && (in_array(MEMBERSHIP_CONTACT_GROUP_ID, $contact_groups) == false)
    ) {
        $contact_groups[] = MEMBERSHIP_CONTACT_GROUP_ID;
    }

    // The contact groups the lines and the rewards collected.
    pg_order_complete_contact_groups($contact_id, $contact_groups);

    // The customer completed an order: the order_abandoned campaigns of this
    // order and of the same customer are cancelled.
    pg_order_complete_abandoned_campaigns((int) ($_SESSION['ecommerce']['order_id'] ?? 0), $contact_id, $billing_email_address);

    // The visit that placed the order, when visitor tracking keeps one.
    pg_order_complete_visitor(
        $_SESSION['software']['visitor_id'] ?? 0,
        $total,
        array('city' => $billing_city, 'state' => $billing_state, 'zip_code' => $billing_zip_code, 'country' => $billing_country));

    // The activity log line and the new_order notification.
    pg_order_complete_announce(
        (int) ($_SESSION['ecommerce']['order_id'] ?? 0),
        $order_number,
        $total,
        array('first_name' => $billing_first_name, 'last_name' => $billing_last_name, 'email_address' => $billing_email_address));

    // If hooks are enabled and there is post-save hook code, then run it.
    if ((defined('PHP_REGIONS') and PHP_REGIONS) && ($post_save_hook_code != '')) {
        eval(prepare_for_eval($post_save_hook_code));
    }
    
    // Capture the just-completed order id BEFORE clearing the cart session
    // so we can pass it to the receipt page via `?order_id=N`. Designers can
    // drop an `order_view` system widget on the configured next_page to show
    // the order summary; without the query string the widget renders empty.
    $completed_order_id = (int)($_SESSION['ecommerce']['order_id'] ?? 0);

    // A store that invoices paid orders on their own (ERP settings) gets this
    // one's invoice once the customer has been answered, so the redirect to
    // the receipt never waits for it. The ERP decides whether the order is
    // paid: a confirmed gateway payment, the customer's account, or nothing
    // left to pay.
    pg_erp_auto_invoice($completed_order_id, 'order_paid', true);

    // remove order id from session so shopping cart is emptied, in case user tries to shop again
    unset($_SESSION['ecommerce']['order_id']);

    // remove liveform because software does not need it anymore
    $liveform->remove_form($type_value);

    // send user to next page (with ?order_id=N for the order_view widget)
    $next_page_url = URL_SCHEME . HOSTNAME . PATH . get_page_name($next_page_id);
    if ($completed_order_id > 0) {
        $next_page_url .= (strpos($next_page_url, '?') === false ? '?' : '&')
                       . 'order_id=' . $completed_order_id;
    }
    header('Location: ' . $next_page_url);
}