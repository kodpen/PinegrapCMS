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
validate_ecommerce_access($user);

include_once('liveform.class.php');
$liveform = new liveform('view_order');

// Handle Parasut API actions submitted from the order detail page.
if (isset($_POST['parasut_action'])) {
    validate_token_field();
    $order_id = (int)($_POST['id'] ?? 0);

    if ($order_id && defined('ENABLE_PARASUT') && ENABLE_PARASUT && defined('PARASUT_COMPANY_ID') && PARASUT_COMPANY_ID !== '') {
        $parasut_action = $_POST['parasut_action'];

        $redirect = OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_order.php?id=' . $order_id;

        // Validate that a contact is linked and has a tax number before invoicing.
        $parasut_contact_id = db_value("SELECT contact_id FROM orders WHERE id = '$order_id'");
        $parasut_tax_number = $parasut_contact_id
            ? db_value("SELECT tax_number FROM contacts WHERE id = '" . e($parasut_contact_id) . "'")
            : '';

        if (!$parasut_contact_id) {
            $liveform->mark_error('_error', lang('No contact is linked to this order. Please ensure billing information is complete.'));
            go($redirect);
        }

        if (empty(trim($parasut_tax_number))) {
            $liveform->mark_error('_error', lang('VKN/TCKN is missing from the linked contact. Please update the contact record before creating a document.'));
            go($redirect);
        }

        if ($parasut_action === 'e_invoice') {
            $result = parasut_create_invoice($order_id, 'e_invoice');
            if ($result['success']) {
                $liveform->add_notice(lang('E-Invoice created successfully in Parasut.') . ' (ID: ' . h($result['parasut_invoice_id']) . ')');
            } else {
                $liveform->mark_error('_error', lang('Parasut E-Invoice Error') . ': ' . h($result['error']));
            }

        } elseif ($parasut_action === 'e_archive') {
            $result = parasut_create_invoice($order_id, 'e_archive');
            if ($result['success']) {
                $liveform->add_notice(lang('E-Archive invoice created successfully in Parasut.') . ' (ID: ' . h($result['parasut_invoice_id']) . ')');
            } else {
                $liveform->mark_error('_error', lang('Parasut E-Archive Error') . ': ' . h($result['error']));
            }

        } elseif ($parasut_action === 'e_irsaliye') {
            $result = parasut_create_shipment($order_id);
            if ($result['success']) {
                $liveform->add_notice(lang('E-Shipment document created successfully in Parasut.') . ' (ID: ' . h($result['parasut_shipment_id']) . ')');
            } else {
                $liveform->mark_error('_error', lang('Parasut E-Shipment Error') . ': ' . h($result['error']));
            }
        }
    }

    go(OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_order.php?id=' . $order_id);
}

// if the form has not just been submitted, then output form
if (!$_POST) {
    $query = "SELECT
                orders.*,
                INET_NTOA(ip_address) AS ip_address,
                user.user_username AS username,
                contacts.id AS contact_id,
                contacts.first_name AS contact_first_name,
                contacts.last_name AS contact_last_name,
                contacts.email_address AS contact_email_address,
                contacts.member_id,
                contacts.file_id as contact_file_id,
                contacts.image AS contact_image,
                contacts.tax_number AS contact_tax_number,
                contacts.tax_office AS contact_tax_office
             FROM orders
             LEFT JOIN user ON orders.user_id = user.user_id
             LEFT JOIN contacts ON orders.contact_id = contacts.id
             WHERE orders.id = '" . escape($_GET['id']) . "'";
    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
    $row = mysqli_fetch_assoc($result);

    $status = $row['status'];
    $order_type = $row['type'];
    $order_number = $row['order_number'];
    $order_date = get_absolute_time(array('timestamp' => $row['order_date']));
    $subtotal = number_format($row['subtotal'] / 100, 2, '.', ',');
    $discount = number_format($row['discount'] / 100, 2, '.', ',');
    $tax = number_format($row['tax'] / 100, 2, '.', ',');
    $shipping = number_format($row['shipping'] / 100, 2, '.', ',');
    $gift_card_discount = number_format($row['gift_card_discount'] / 100, 2, '.', ',');
    $surcharge = number_format($row['surcharge'] / 100, 2, '.', ',');
    $payment_installment = $row['payment_installment'];
    $installment_charges = number_format($row['installment_charges'] / 100, 2, '.', ',');
    $total = number_format($row['total'] / 100, 2, '.', ',');
    $total_cents = (int)$row['total'];
    $refunded_amount_cents = (int)($row['refunded_amount'] ?? 0);

    // Captured here with the rest of the order fields, and deliberately NOT
    // read from $row where the refund banner is built further down: $row is
    // reassigned by half a dozen later queries on this screen, so by that
    // point it holds another result set entirely. The columns are 2026.1.27
    // additions, hence the defaults.
    $order_refund_status    = isset($row['refund_status']) ? (string) $row['refund_status'] : '';
    $order_refunded_at      = isset($row['refunded_at']) ? (int) $row['refunded_at'] : 0;
    $order_refund_reference = isset($row['refund_reference']) ? (string) $row['refund_reference'] : '';
    $commission = number_format($row['commission'] / 100, 2, '.', ',');
    $transaction_id = $row['transaction_id'];
    $authorization_code = $row['authorization_code'];
    $special_offer_code = $row['special_offer_code'];
    $referral_source_code = $row['referral_source_code'];
    $reference_code = $row['reference_code'];
    $tracking_code = $row['tracking_code'];
    $utm_source = $row['utm_source'];
    $utm_medium = $row['utm_medium'];
    $utm_campaign = $row['utm_campaign'];
    $utm_term = $row['utm_term'];
    $utm_content = $row['utm_content'];
    $affiliate_code = $row['affiliate_code'];
    $currency_code = $row['currency_code'];
    $http_referer = $row['http_referer'];
    $user_id = $row['user_id'];
    $username = $row['username'];
    $contact_id = $row['contact_id'];
    $contact_image = $row['contact_image'];
    $contact_file_id = $row['contact_file_id'];
    $contact_first_name = $row['contact_first_name'];
    $contact_last_name = $row['contact_last_name'];
    $contact_email_address = $row['contact_email_address'];
    $member_id = $row['member_id'];
    $contact_tax_number = $row['contact_tax_number'] ?? '';
    $contact_tax_office  = $row['contact_tax_office']  ?? '';
    $billing_salutation = $row['billing_salutation'];
    $billing_first_name = $row['billing_first_name'];
    $billing_last_name = $row['billing_last_name'];
    $billing_email_address = $row['billing_email_address'];
    $billing_company = $row['billing_company'];
    $billing_address_1 = $row['billing_address_1'];
    $billing_address_2 = $row['billing_address_2'];
    $billing_city = $row['billing_city'];
    $billing_state = $row['billing_state'];
    $billing_country = $row['billing_country'];
    $billing_zip_code = $row['billing_zip_code'];
    $billing_address_verified = $row['billing_address_verified'];
    $billing_phone_number = $row['billing_phone_number'];
    $billing_fax_number = $row['billing_fax_number'];
    $custom_field_1 = $row['custom_field_1'];
    $custom_field_2 = $row['custom_field_2'];
    $po_number = $row['po_number'];
    $payment_method = $row['payment_method'];
    $ip_address = $row['ip_address'];
    $parasut_invoice_id  = $row['parasut_invoice_id']  ?? '';
    $parasut_shipment_id = $row['parasut_shipment_id'] ?? '';

    $source = '';

    $output_contact_image = '';

    // Only set when a contact image is found below, but read in both branches.
    $accent_color = '';

    if($contact_file_id == 0){
        if($contact_image){
            $accent_color = get_dominant_area_color(FILE_DIRECTORY_PATH . '/' . $contact_image);
            $output_contact_image = '
            <div class="col-auto overflow-hidden d-print-none rounded-circle bg-body-tertiary border border-2" style="width:50px;height:50px;background-color:' . h($accent_color) . ';">
                <img class="lazy object-fit-contain w-100 h-100"  src="' . OUTPUT_PATH . SOFTWARE_DIRECTORY . '/assets/images/loading.gif" data-src="' . PATH . h($row['image']) . '" />
            </div>';
        }else{
            $output_contact_image = '
            <div class="col-auto overflow-hidden d-print-none rounded-circle bg-body-tertiary border border-2" style="width:50px;height:50px;background-color:' . h($accent_color) . ';">
                <img class="lazy object-fit-contain w-100 h-100"  src="' . OUTPUT_PATH . SOFTWARE_DIRECTORY . '/assets/images/loading.gif" data-src="assets/images/person1.png" />
            </div>';
        }
       
    }else{
        $query = 
        "SELECT 
            files.name
        FROM files 
        WHERE files.id = '" . escape($contact_file_id) . "'";
        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
        $file = mysqli_fetch_array($result);
        $file_name = $file['name'];
        $accent_color = get_dominant_area_color(FILE_DIRECTORY_PATH . '/' . $file_name);
        $output_contact_image = '
        <div class="col-auto overflow-hidden d-print-none rounded-circle bg-body-tertiary border border-2" style="width:50px;height:50px;background-color:' . h($accent_color) . ';">
            <img class="lazy  object-fit-contain w-100 h-100"  src="' . OUTPUT_PATH . SOFTWARE_DIRECTORY . '/assets/images/loading.gif" data-src="' . PATH . h($file_name) . '" />
        </div>';
    }


    
    if ($tracking_code or $http_referer or $referral_source_code or $utm_source) {
        $source .=
            '<div class="card mt-3">
                <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
                    ' . lang('Source') . '
                </div>
                <div class="card-body">';

        if ($tracking_code) {
            $source .=
                '<div class="row" >
                    <span class="translateable col text-muted">' . lang('Tracking Code') . ':</span>
                    <span class="col text-end">' . h($tracking_code) . '</span>
                </div>';
        }

        if ($http_referer) {

            // if http referer is greater than 25 characters, then shorten text version
            if (mb_strlen($http_referer) > 25) {
                $http_referer_text = mb_substr($http_referer, 0, 25) . '...';
            } else {
                $http_referer_text = $http_referer;
            }

            $source .=
                '<div class="row" >
                    <span class="translateable col text-muted">' . lang('Referring URL') . ':</span>
                    <span class="col text-end"><a href="' . h(escape_url($http_referer)) . '" target="_blank">' . h($http_referer_text) . '</a></span>
                </div>';
        }

        if ($referral_source_code) {
            $source .=
                '<div class="row" >
                    <span class="translateable col text-muted">' . lang('Referral Source') . ':</span>
                    <span class="col text-end">' . h($referral_source_code) . '</span>
                </div>';
        }

        if ($utm_source) {
            $source .=
                '<hr/>
                <div class="row" >
                    <span class="col-12"><h6 class="text-muted">' . lang('UTF') . '</h6></span>
                </div>
                <div class="row">
                    <span class="translateable col text-muted">' . lang('Source') . ':</span>
                    <span class="col text-end">' . h($utm_source) . '</span>
                </div>';

            if ($utm_medium) {
                $source .=
                    '<div class="row" >
                        <span class="translateable col text-muted">' . lang('Campaign Medium') . ':</span>
                        <span class="col text-end">' . h($utm_medium) . '</span>
                    </div>';
            }

            if ($utm_campaign) {
                $source .=
                    '<div class="row" >
                        <span class="translateable col text-muted">' . lang('Campaign') . ':</span>
                        <span class="col text-end">' . h($utm_campaign) . '</span>
                    </div>';
            }

            if ($utm_term) {
                $source .=
                    '<div class="row" >
                        <span class="translateable col text-muted">' . lang('Term') . ':</span>
                        <span class="col text-end">' . h($utm_term) . '</span>
                    </div>';
            }

            if ($utm_content) {
                $source .=
                    '<div class="row" >
                        <span class="translateable col text-muted">' . lang('Content') . ':</span>
                        <span class="col text-end">' . h($utm_content) . '</span>
                    </div>';
            }
        
        }
        $source .=
            '</div>
        </div>';
    }

    $fax = '';

    if ($billing_fax_number) {
        $fax =
            '<div class="row" >
                <span class="translateable col text-muted">Fax:</span>
                <span class="col text-end">' . h($billing_fax_number) . '</span>
            </div>';
    }

    if ($payment_method == 'Credit/Debit Card') {
        $card_type = $row['card_type'];
        $cardholder = $row['cardholder'];
        $card_number = $row['card_number'];
        
        // if the credit card number is encrypted
        if ((mb_substr($card_number, 0, 1) != '*') && (mb_strlen($card_number) > 16)) {
            // if encryption is enabled, then decrypt the credit card number
            if (
                (defined('ENCRYPTION_KEY') == TRUE)
                && (extension_loaded('mcrypt') == TRUE)
                && (in_array('rijndael-256', mcrypt_list_algorithms()) == TRUE)
            ) {
                $card_number = decrypt_credit_card_number($card_number, ENCRYPTION_KEY);
                
                // if the credit card number is not numeric, then there was a decryption error
                if (is_numeric($card_number) == FALSE) {
                    $card_number = '[decryption error]';
                    
                // else the credit card number was decrypted successfully,
                // so if the user does not have access to view card data,
                // then protect the credit card number
                } else if (($user['role'] == 3) && ($user['view_card_data'] == FALSE)) {
                    $card_number = protect_credit_card_number($card_number);
                }
                
            // else encryption is disabled, so output error
            } else {
                $card_number = '[decryption error]';
            }
        }
        
        $expiration_month = $row['expiration_month'];
        $expiration_year = $row['expiration_year'];
        $card_verification_number = $row['card_verification_number'];
        
        // if the card verification number is not already protected,
        // and the user does not have access to view card data,
        // then protect it
        if (
            (mb_substr($card_verification_number, 0, 1) != '*')
            && (($user['role'] == 3) && ($user['view_card_data'] == FALSE))
        ) {
            $card_verification_number = protect_card_verification_number($card_verification_number);
        }
    }

    // if the billing address has been verified, then output "yes"
    if ($billing_address_verified == '1') {
        $billing_address_verified = lang('Yes');

    // else it has not been verified so output "no"
    } else {
        $billing_address_verified = lang('No');
    }

    if ($row['opt_in']) {
        $opt_in = lang('Yes');
    } else {
        $opt_in = lang('No');
    }

    if ($row['tax_exempt']) {
        $tax_exempt = lang('Yes');
    } else {
        $tax_exempt = lang('No');
    }

    $output_gift_card_discount_row = '';

    // if there is a gift card discount, then prepare to output row for it
    if ($gift_card_discount > 0) {
        // get applied gift cards in order to output them
        $query =
            "SELECT
                gift_card_id,
                code,
                amount,
                new_balance,
                givex,
                authorization_number
            FROM applied_gift_cards
            WHERE order_id = '" . escape($_GET['id']) . "'
            ORDER BY id ASC";
        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
        
        $applied_gift_cards = array();
        
        // loop through applied gift cards in order to add them to array
        while ($row = mysqli_fetch_assoc($result)) {
            $applied_gift_cards[] = $row;
        }
        
        $output_gift_card_label_plural_suffix = '';
        
        // if there is more than 1 applied gift card, then prepare to output gift card label plural suffix
        if (count($applied_gift_cards) > 1) {
            $output_gift_card_label_plural_suffix = 's';
        }
        $output_gift_card_discount_row =
        '<div class="row" >
            <span class="translateable col text-muted">' . lang(array('string'=>'Gift Card{suffix:1}','vars'=>array(),'suffix'=>array($output_gift_card_label_plural_suffix))) . ':</span>
            <span class="col text-end">-' . BASE_CURRENCY_SYMBOL . $gift_card_discount . '</span>
        </div>';
    }
    $output_surcharge_row = '';

    // If there is a credit card surcharge, then output row for it.
    if ($surcharge > 0) {
        $output_surcharge_row =
            '<div class="row" >
                <span class="translateable col text-muted">' . lang('Surcharge') . ':</span>
                <span class="col text-end">' . BASE_CURRENCY_SYMBOL . $surcharge . '</span>
            </div>';
    }
    //if there is installment charge and payment installment((1) is no installment)
    $output_number_of_installment_row = '';
    if(($installment_charges != 0)&&($payment_installment >= 2)){  
        $output_number_of_installment_row =
            '<div class="row" >
                <span class="translateable col text-muted">' . lang('Number of Installments') . ':</span>
                <span class="col">' . $payment_installment . '</span>
            </div>
            <div class="row" >
                <span class="translateable col text-muted">' . lang('Installment Charge') . ':</span>
                <span class="col text-end">' . BASE_CURRENCY_SYMBOL . $installment_charges . '</span>
            </div>';
    }
    $output_payment_information = '';

    // if there was a payment method, then prepare to output payment information
    if ($payment_method != '') {
        $output_credit_debit_card_information = '';
        
        // if Credit/Debit Card payment method was used for order, then prepare to output values for that payment method
        if ($payment_method == 'Credit/Debit Card') {

            $cardholder_row = '';

            if ($cardholder) {
                $cardholder_row =
                    '<div class="row" >
                        <span class="translateable col text-muted">' . lang('Cardholder') . ':</span>
                        <span class="col text-end">' . h($cardholder) . '</span>
                    </div>';
            }

            $expiration_row = '';

            if ($expiration_month and $expiration_year) {
                $expiration_row =
                    '<div class="row" >
                        <span class="translateable col text-muted">' . lang('Expiration') . ':</span>
                        <span class="col text-end">' . h($expiration_month . '/' . $expiration_year) . '</span>
                    </div>';
            }

            $card_verification_number_row = '';

            if ($card_verification_number) {
                $card_verification_number_row =
                    '<div class="row" >
                        <span class="translateable col text-muted">' . lang('Verification Number') . ':</span>
                        <span class="col text-end">' . h($card_verification_number) . '</span>
                    </div>';
            }
            
            $output_credit_debit_card_information =
                '<div class="row" >
                    <span class="translateable col text-muted">' . lang('Card Type') . ':</span>
                    <span class="col text-end">' . h($card_type) . '</span>
                </div>
                ' . $cardholder_row . '
                <div class="row" >
                    <span class="translateable col text-muted">' . lang('Card Number') . ':</span>
                    <span class="col text-end">' . $card_number . '</span>
                </div>
                ' . $expiration_row . '
                ' . $card_verification_number_row;
            
        }

        $transaction_id_row = '';

        if ($transaction_id) {
            $transaction_id_row .=
                '<div class="row" >
                    <span class="translateable col text-muted">' . lang('Transaction ID') . ':</span>
                    <span class="col text-end">' . h($transaction_id) . '</span>
                </div>';
        }

        $authorization_code_row = '';

        if ($authorization_code) {
            $authorization_code_row .=
                '<div class="row" >
                    <span class="translateable col text-muted">' . lang('Authorization Code') . ':</span>
                    <span class="col text-end">' . h($authorization_code) . '</span>
                </div>';
        }
        $output_payment_information =
            '
                <div class="card mt-3">
                   <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
                           ' . lang('Payment Information') . '
                       </div>
                       <div class="card-body">
                           <div class="row" >
                               <span class="translateable col text-muted">' . lang('Payment Method') . ':</span>
                               <span class="col translateable text-end">' . lang($payment_method) . '</span>
                           </div>
                           ' . $output_credit_debit_card_information . '
                           ' . $transaction_id_row . '
                           ' . $authorization_code_row . '
                           ' . ($refunded_amount_cents > 0 ?
                               '<div class="row text-danger mt-2">
                                   <span class="translateable col text-muted">' . lang('Total Refunded') . ':</span>
                                   <span class="col text-end fw-semibold">-' . BASE_CURRENCY_SYMBOL . number_format($refunded_amount_cents / 100, 2, '.', ',') . '</span>
                               </div>'
                           : '') . '
                           <br/>
                       </div>
                </div>';
    }

    $output_user_row = '';

    // If this order has a user, then show user info.
    if ($username != '') {}
        $output_user_row =
            '<div class="row" >
                <span class="translateable col text-muted">' . lang('User') . ':</span>
                <span class="col text-end"><a class="link-secondary" href="edit_user.php?id=' . $user_id . '">' . h($username) . '</a></span>
            </div>';
    //}

    $output_contact_row = '';

    // If this order has a contact, then show contact info.
    if ($contact_id != '') {

        $output_contact = '';
        
        // If there is a first name or last name, then output name.
        if (($contact_first_name != '') or ($contact_last_name != '')) {
            
            // If there is a first name, then start name with that.
            if ($contact_first_name != '') {
                $output_contact .= h($contact_first_name);
            }
            
            // If there is a last name, then add it to the name.
            if ($contact_last_name != '') {
                
                if ($output_contact != '') {
                    $output_contact .= ' ';
                }
                
                $output_contact .= h($contact_last_name);

            }
         
        // Otherwise, if there is an email address then use that.
        } else if ($contact_email_address != '') {
            $output_contact = h($contact_email_address);

        // Otherwise show ID.
        } else {
            $output_contact = $contact_id;
        }

        $output_contact_row =
            '<div class="row" >
                <span class="translateable col text-muted">' . lang('Contact') . ':</span>
                <span class="col text-end"><a class="link-secondary" href="edit_contact.php?id=' . $contact_id . '">' . $output_contact . '</a></span>
            </div>';

    }

    $output_member_id_row = '';

    if ($member_id != '') {
        $output_member_id_row =
            '<div class="row" >
                <span class="col text-muted">' . h(MEMBER_ID_LABEL) . ':</span>
                <span class="col text-end">' . h($member_id) . '</span>
            </div>';
    }

    // If we don't know the IP address for the order, then set it to empty string.
    if ($ip_address == '0.0.0.0') {
        $ip_address = '';
    }

    $output_ip_address_row = '';

    // If this order has an ip, then show ip.
    if ($ip_address != '') {
        $output_ip_address_row =
            '<div class="row" >
                <span class="translateable col text-muted">' . lang('IP Address') . ':</span>
                <span class="col text-end">' . h($ip_address) . '</span>
            </div>';
    }

    $output_affiliate = '';

    // If the affiliate program is enabled and this order had an affiliate code, prepare affiliate output.
    if (AFFILIATE_PROGRAM and $affiliate_code) {

        // get affiliate information from contact
        $query = "SELECT id, affiliate_name FROM contacts WHERE affiliate_code = '" . escape($affiliate_code) . "'";
        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
        
        // if a contact was found for affiliate code, prepare affilate name with link to contact
        if (mysqli_num_rows($result) > 0) {
            $row = mysqli_fetch_assoc($result);
            $affiliate_contact_id = $row['id'];
            $affiliate_name = $row['affiliate_name'];
            
            if ($affiliate_name) {
                $output_affiliate_name = '<a href="edit_contact.php?id=' . $affiliate_contact_id . '">' . h($affiliate_name) . '</a>';
            }
        }
        
        $output_affiliate =
            '<div class="card mt-3">
                <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
                    ' . lang('Affiliate Information') . '
                </div>
                <div class="card-body">
                    <div class="row">
                        <span class="translateable col text-muted">' . lang('Affiliate Name') . ':</span>
                        <span class="col text-end">' . $output_affiliate_name . '</span>
                    </div>
                    <div class="row">
                        <span class="translateable col text-muted">' . lang('Affiliate Code') . ':</span>
                        <span class="col text-end">' . h($affiliate_code) . '</span>
                    </div>
                    <div class="row">
                        <span class="translateable col text-muted">' . lang('Commission') . ':</span>
                        <span class="col text-end">' . BASE_CURRENCY_SYMBOL . $commission . '</span>
                    </div>
                </div>
            </div>';
    }

    $output_custom_billing_information = '';

    $output_custom_billing_form = get_submitted_form_content_without_form_fields(array('type' => 'custom_billing_form', 'order_id' => $_GET['id'], 'style' => 'padding: 10px'));

    if ($output_custom_billing_form != '') {
        $output_custom_billing_information =
            '<div class="card mt-3">
                <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
                    ' . lang('Custom Billing Information') . '
                </div>
                <div class="card-body">
                    <table>' . $output_custom_billing_form . '</table>
                </div>
            </div>';
    }

    // get all ship tos for order
    $query = "SELECT
                DISTINCT ship_to_id,
                ship_to_name,
                salutation,
                first_name,
                last_name,
                company,
                address_1,
                address_2,
                city,
                state,
                zip_code,
                country,
                address_verified,
                phone_number,
                address_type,
                arrival_date,
                arrival_date_code,
                ship_date,
                delivery_date,
                shipping_method_code,
                shipping_methods.id AS shipping_method_id,
                shipping_methods.name AS shipping_method_name,
                shipping_cost,
                packages
             FROM order_items
             LEFT JOIN ship_tos ON order_items.ship_to_id = ship_tos.id
             LEFT JOIN shipping_methods ON ship_tos.shipping_method_id = shipping_methods.id
             WHERE order_items.order_id = '" . escape($_GET['id']) . "'
             ORDER BY ship_to_id";
    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');

    $ship_tos = array();

    // assume that there are no real ship tos until we find out that there is one
    $ship_to_exists = false;

    // foreach ship to, add ship to to array
    while ($row = mysqli_fetch_assoc($result)) {
        $ship_tos[] = $row;
        
        // if this is a real ship to, remember that
        if ($row['ship_to_id'] > 0) {
            $ship_to_exists = true;
        }
    }
    
    // the save and cancel buttons will only be outputted if a real ship to exists
    $output_save_button = '';

    // Initialised here rather than inside the ship-to branch below: orders with
    // no real ship_to (digital goods, gift-card-only carts) must still be able
    // to show the restore button, otherwise the variable is undefined at render.
    $output_restore_button = '';

    $output_order_details = '<div class="list-group-item  mb-0 text-uppercase h5 text-primary fw-bold">' . lang('Order Details') . '</div>';

    if ($ship_to_exists == true) {
        // get all custom shipping form data for this order
        $query =
            "SELECT
                ship_to_id,
                data,
                name,
                type
            FROM form_data
            WHERE
                (order_id = '" . escape($_GET['id']) . "')
                AND (ship_to_id != '0')
            ORDER BY id ASC";
        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
        
        // initialize array that will be used for storing custom shipping form data for ship tos
        $ship_tos_for_custom_fields = array();
        
        // initialize array that will be used for storing custom field names for custom shipping form data
        $custom_field_names = array();
        
        // loop through the fields in order to determine custom field names and add fields to ship tos array for custom shipping form data
        while ($field = mysqli_fetch_assoc($result)) {
            // if this field name has not been added to the custom field names array, then add it,
            // so we can keep track of all necessary field names
            if (in_array($field['name'], $custom_field_names) == FALSE) {
                $custom_field_names[] = $field['name'];
            }
            
            // if there is not already form data for this field in the ship tos array, then just add data to the array
            if ((isset($ship_tos_for_custom_fields[$field['ship_to_id']][$field['name']]) == FALSE) || ($ship_tos_for_custom_fields[$field['ship_to_id']][$field['name']]['data'] == '')) {
                $ship_tos_for_custom_fields[$field['ship_to_id']][$field['name']]['data'] = $field['data'];
                $ship_tos_for_custom_fields[$field['ship_to_id']][$field['name']]['type'] = $field['type'];
                
            // else there is already form data for this field, so this is probably a field that supports multiple values,
            // so just append this additional value
            } else {
                $ship_tos_for_custom_fields[$field['ship_to_id']][$field['name']]['data'] .= ', ' . $field['data'];
            }
        }
        
        $output_custom_field_headings = '';
        
        // loop through the custom field names in order to output headings for them
        foreach ($custom_field_names as $custom_field_name) {
            $output_custom_field_headings .= '<span class="col text-muted">' . h($custom_field_name) . ':</span>';
        }
        
        $output_colspan = 13 + count($custom_field_names);
        
        // loop through all ship tos
        foreach ($ship_tos as $key => $recipient) {
            // if ship to is a real ship to, prepare header with ship to information
            if ($ship_tos[$key]['ship_to_id'] > 0) {
                // if this shipping address is verified, then convert salutation and country to all uppercase
                if ($ship_tos[$key]['address_verified'] == 1) {
                    $ship_tos[$key]['salutation'] = mb_strtoupper($ship_tos[$key]['salutation']);
                    $ship_tos[$key]['country'] = mb_strtoupper($ship_tos[$key]['country']);
                }
                
                if ($ship_tos[$key]['salutation']) {
                    $name = $ship_tos[$key]['salutation'] . ' ' . $ship_tos[$key]['first_name'] . ' ' . $ship_tos[$key]['last_name'];
                } else {
                    $name = $ship_tos[$key]['first_name'] . ' ' . $ship_tos[$key]['last_name'];
                }
                
                $address = '';
                
                if ($ship_tos[$key]['address_1']) {
                    $address .= $ship_tos[$key]['address_1'] . ', ';
                }

                if ($ship_tos[$key]['address_2']) {
                    $address .= $ship_tos[$key]['address_2'] . ', ';
                }
                
                if ($ship_tos[$key]['city']) {
                    $address .= $ship_tos[$key]['city'] . ', ';
                }
                
                if ($ship_tos[$key]['state']) {
                    $address .= $ship_tos[$key]['state'] . ', ';
                }
                
                if ($ship_tos[$key]['zip_code']) {
                    $address .= $ship_tos[$key]['zip_code'] . ', ';
                }
                
                if ($ship_tos[$key]['country']) {
                    $address .= $ship_tos[$key]['country'];
                }
                
                $output_address_verified_check_mark = '';
                
                // if the address has been verified, then prepare to output check mark
                if ($ship_tos[$key]['address_verified'] == 1) {
                    $output_address_verified_check_mark = '<img src="images/check_mark.gif" width="7" height="7" alt="check mark" title="" />';
                }

                $output_arrival_date = '';

                if ($ship_tos[$key]['arrival_date'] != '0000-00-00') {
                    $output_arrival_date = get_absolute_time(array('timestamp' => strtotime($ship_tos[$key]['arrival_date']), 'type' => 'date'));
                }

                $arrival_date_code = '';
                
                if ($ship_tos[$key]['arrival_date_code']) {
                    $arrival_date_code = ' (' . $ship_tos[$key]['arrival_date_code'] . ')';
                }

                $output_ship_date_field = '';
                $output_delivery_date_field = '';
                $output_shipping_tracking_numbers = '';
                $output_shipping_tracking_numbers_field = '';
                
                // If the order is complete, then output ship date and shipping tracking numbers.
                if ($status != 'incomplete') {
                    // If the form has not been submitted yet, and the ship date is not blank,
                    // then prefill ship date field.
                    if (($liveform->field_in_session('id') == false) && ($ship_tos[$key]['ship_date'] != '0000-00-00')) {
                        $liveform->assign_field_value('ship_to_id_' . $ship_tos[$key]['ship_to_id'] . '_ship_date', prepare_form_data_for_output($ship_tos[$key]['ship_date'], 'date'));
                    }

                    $output_ship_date_field =
                        $liveform->output_field(array('class'=>'form-control', 'type' => 'text', 'id' => 'ship_to_id_' . $ship_tos[$key]['ship_to_id'] . '_ship_date', 'name' => 'ship_to_id_' . $ship_tos[$key]['ship_to_id'] . '_ship_date', 'size' => '10', 'maxlength' => '10')) . '
                        <script>
                            $("#ship_to_id_' . $ship_tos[$key]['ship_to_id'] . '_ship_date").datepicker({
                                dateFormat: date_picker_format
                            });
                        </script>';

                    // If the form has not been submitted yet, and the delivery date is not blank,
                    // then prefill delivery date field.
                    if (
                        !$liveform->field_in_session('id')
                        and $ship_tos[$key]['delivery_date'] != '0000-00-00'
                    ) {
                        $liveform->set('ship_to_id_' . $ship_tos[$key]['ship_to_id'] . '_delivery_date', prepare_form_data_for_output($ship_tos[$key]['delivery_date'], 'date'));
                    }

                    $output_delivery_date_field =
                        $liveform->output_field(array('class'=>'form-control', 'type' => 'text', 'id' => 'ship_to_id_' . $ship_tos[$key]['ship_to_id'] . '_delivery_date', 'name' => 'ship_to_id_' . $ship_tos[$key]['ship_to_id'] . '_delivery_date', 'size' => '10', 'maxlength' => '10')) . '
                        <script>
                            $("#ship_to_id_' . $ship_tos[$key]['ship_to_id'] . '_delivery_date").datepicker({
                                dateFormat: date_picker_format
                            });
                        </script>';
 
                    $query =
                        "SELECT number
                        FROM shipping_tracking_numbers
                        WHERE ship_to_id = '" . $ship_tos[$key]['ship_to_id'] . "'
                        ORDER BY id ASC";
                    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
                    $shipping_tracking_numbers = mysqli_fetch_items($result);
                    
                    // loop through the shipping tracking numbers in order to output them
                    foreach ($shipping_tracking_numbers as $shipping_tracking_number) {
                        // if this is not the first shipping tracking number then add a comma and space for separation
                        if ($output_shipping_tracking_numbers != '') {
                            $output_shipping_tracking_numbers .= '<br />';
                        }
                        
                        $shipping_tracking_url = get_shipping_tracking_url($shipping_tracking_number['number'],$recipient['shipping_method_code']);
                        
                        // if a shipping tracking url was found, then output link
                        if ($shipping_tracking_url != '') {
                            $output_shipping_tracking_numbers .= '<a class="link-secondary d-print-none" href="' . h($shipping_tracking_url) . '" target="_blank">' . h($shipping_tracking_number['number']) . '</a>';
                            
                        } 
                    }
                    
                  // if the form has not been submitted yet, then prefill tracking number field with tracking numbers
                  if ($liveform->field_in_session('id') == FALSE) {
                    $shipping_tracking_numbers_for_field = '';

                    // array_key_last() needs PHP 7.3, so find the last key once, before the loop.
                    $last_shipping_tracking_number_key = null;

                    if ($shipping_tracking_numbers) {
                        end($shipping_tracking_numbers);
                        $last_shipping_tracking_number_key = key($shipping_tracking_numbers);
                        reset($shipping_tracking_numbers);
                    }

                    // loop through the shipping tracking numbers in order to prepare value for field
                    foreach ($shipping_tracking_numbers as $shipping_tracking_number => $value) {
                        // if this is not the first shipping tracking number then add a line break
                        if ($shipping_tracking_numbers_for_field != '') {
                            $shipping_tracking_numbers_for_field .= "\n";
                        }
                    
                        
                        if ($shipping_tracking_number === $last_shipping_tracking_number_key) {
                            $shipping_tracking_numbers_for_field .= $value['number'];
                        }else{
                            $shipping_tracking_numbers_for_field .= $value['number'] . ',';
                        }
                    }

                    $liveform->assign_field_value('ship_to_id_' . $ship_tos[$key]['ship_to_id'] . '_tracking_numbers', $shipping_tracking_numbers_for_field) ;
                }

                $output_shipping_tracking_numbers_field = $liveform->output_field(array('type'=>'text', 'id'=>'ship_to_id_tracking_numbers', 'name'=>'ship_to_id_' . $ship_tos[$key]['ship_to_id'] . '_tracking_numbers','class'=>'form-control tagin min-height-tagin' )) . '
                <script>
                    if(document.body.contains(document.querySelector("#ship_to_id_tracking_numbers"))){
                        tagin( document.querySelector("#ship_to_id_tracking_numbers"));
                    }
                </script>';  
                    
                }

                // If the shipping method still exists, then show name and code and link to it.
                if ($recipient['shipping_method_id']) {

                    $shipping_method =
                        '<a class="link-secondary" href="edit_shipping_method.php?id=' . h($recipient['shipping_method_id']) . '">' .
                            h($recipient['shipping_method_name']) . ' (' . h($recipient['shipping_method_code']) . ')' .
                        '</a>';

                // Otherwise the shipping method no longer exists, so just show code.
                } else {
                    $shipping_method = h($recipient['shipping_method_code']);
                }

                $packages = '';

                if ($ship_tos[$key]['packages']) {
                    $packages =
                        '<div class="row my-2">
                            <span class="col text-muted">' . lang('Packages') . ':</span>
                            <span class="col text-end">' . h($ship_tos[$key]['packages']) . '</span>
                        </div>';
                    }
                
                $output_custom_field_cells = '';
                
                // loop through the custom field names in order to output cells for values
                foreach ($custom_field_names as $custom_field_name) {
                    $data = $ship_tos_for_custom_fields[$ship_tos[$key]['ship_to_id']][$custom_field_name]['data'];
                    $type = $ship_tos_for_custom_fields[$ship_tos[$key]['ship_to_id']][$custom_field_name]['type'];
                    
                    // assume that we need to prepare data for HTML until we find out otherwise
                    $prepare_for_html = TRUE;
                    
                    // if type is html, then don't prepare data for html, because data is already html
                    if ($type == 'html') {
                        $prepare_for_html = FALSE;
                    }
                    
                    $output_custom_field_cells .= '<span class="col text-end order_custom_field">' . prepare_form_data_for_output($data, $type, $prepare_for_html) . '</span>';
                }
                
                $output_shipping_details =
                    '<div class="col-12 col-md-12 col-lg-4">
                        <div class="card">
                            <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
                                ' . lang('Shipping Information') . '
                                <div class="print_packing_slip float-end d-print-none">
                                    <button type="button" class="btn btn-link link-secondary py-0 mb-2 position-relative" title="' . lang('Print Packing Slip') . '" onclick="window.open(\'print_packing_slip.php?ship_to_id=' . $ship_tos[$key]['ship_to_id'] . '\', \'\', \'width=750, height=600, resizable=1, scrollbars=1\'); return false;""><span class="material-icons me-1">print</span>' . lang('Print') . '</a>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-12">
                                        <div class="row">
                                            <span class="translateable col text-muted">' . lang('Ship to Name') . ':</span>
                                            <span class="col text-end">' . h($ship_tos[$key]['ship_to_name']) . '</span>
                                        </div>
                                        <div class="row">
                                            <span class="translateable col text-muted">' . lang('Full Name') . ':</span>
                                            <span class="col text-end">' . h($name) . '</span>
                                        </div>
                                        <div class="row">
                                            <span class="translateable col text-muted">' . lang('Company') . ':</span>
                                            <span class="col text-end">' . h($ship_tos[$key]['company']) . '</span>
                                        </div>
                                        <div class="row">
                                            <span class="translateable col-auto text-muted">' . lang('Address') . ':</span>
                                            <span class="col offset-1 " style="font-size:110%;font-weight:500">' . h($address) . '</span>
                                        </div>
                                        <div class="row">
                                            <span class="translateable col text-muted">' . lang('Address Type') . ':</span>
                                            <span class="col text-end translateable">' . ucwords($ship_tos[$key]['address_type']) . '</span>
                                        </div>
                                        <div class="row">
                                            <span class="translateable col text-muted">' . lang('Address Verified') . ':</span>
                                            <span class="col text-end ">' . $output_address_verified_check_mark . '</span>
                                        </div>
                                        <div class="row">
                                            <span class="translateable col text-muted">' . lang('Phone') . ':</span>
                                            <span class="col text-end ">' . h($ship_tos[$key]['phone_number']) . '</span>
                                        </div>
                                    </div>

                                    <div class="col-12">
                                        <div class="row mb-2">
                                            <span class="translateable col text-muted">' . lang('Req. Arrival Date') . ':</span>
                                            <span class="col text-end ">' . $output_arrival_date . $arrival_date_code . '</span>
                                        </div>
                                        <div class="row pt-1 mb-3 justify-content-center">
                                            <div class="col-auto">
                                                <label class="form-label">' . lang('Ship Date') . ':</label>
                                                ' . $output_ship_date_field . '
                                            </div>
                                            <div class="col-auto ">
                                                <label class="form-label">' . lang('Delivery Date') . ':</label>
                                                ' . $output_delivery_date_field . '
                                            </div>
                                        </div>

                                    </div>

                                    <div class="col-12 pt-4" id="trackin_number_from_view_orders">
                                        <div class="row">
                                            <span class="col text-muted">' . lang('Shipping Method') . ':</span>
                                            <span class="col-auto badge">' . $shipping_method . '</span>
                                        </div>
                                        <div class="row">
                                            <span class="col text-muted mt-3">' . lang('Tracking Numbers') . ':</span>
                                            <span class="col-12 mt-2">' . $output_shipping_tracking_numbers . '
                                            ' . $output_shipping_tracking_numbers_field . '</span>
                                        </div>
                                        <div class="row my-2">
                                            <span class="col text-muted">' . lang('Shipping Cost') . ':</span>
                                            <span class="col text-end">' . BASE_CURRENCY_SYMBOL . number_format($ship_tos[$key]['shipping_cost'] / 100, 2, '.', ',') . '</span>
                                        </div>
                                        ' . $packages . '
                                        <div class="row">
                                            ' . $output_custom_field_headings . '
                                            ' . $output_custom_field_cells . '
                                        </div>
                                    </div> 
                                </div>
                            </div>
                        </div>
                    </div>';
            }
            
            $output_custom_field_1_heading = '';

            // If the first custom product field is active, then output heading for it.
            if (ECOMMERCE_CUSTOM_PRODUCT_FIELD_1_LABEL != '') {
                $output_custom_field_1_heading = h(ECOMMERCE_CUSTOM_PRODUCT_FIELD_1_LABEL);
            }

            $output_custom_field_2_heading = '';

            // If the second custom product field is active, then output heading for it.
            if (ECOMMERCE_CUSTOM_PRODUCT_FIELD_2_LABEL != '') {
                $output_custom_field_2_heading = h(ECOMMERCE_CUSTOM_PRODUCT_FIELD_2_LABEL);
            }

            $output_custom_field_3_heading = '';

            // If the third custom product field is active, then output heading for it.
            if (ECOMMERCE_CUSTOM_PRODUCT_FIELD_3_LABEL != '') {
                $output_custom_field_3_heading = h(ECOMMERCE_CUSTOM_PRODUCT_FIELD_3_LABEL);
            }

            $output_custom_field_4_heading = '';

            // If the fourth custom product field is active, then output heading for it.
            if (ECOMMERCE_CUSTOM_PRODUCT_FIELD_4_LABEL != '') {
                $output_custom_field_4_heading = h(ECOMMERCE_CUSTOM_PRODUCT_FIELD_4_LABEL);
            }
            
 
            
            // get all order items in order for this ship to
            $query = "SELECT
                        order_items.id,
                        order_items.product_name,
                        order_items.quantity,
                        order_items.price,
                        order_items.recurring_payment_period,
                        order_items.recurring_number_of_payments,
                        order_items.recurring_start_date,
                        order_items.calendar_event_id,
                        order_items.recurrence_number,
                        order_items.show_shipped_quantity,
                        order_items.shipped_quantity,
                        products.id as product_id,
                        products.image_name as image_name,
                        products.short_description,
                        products.custom_field_1,
                        products.custom_field_2,
                        products.custom_field_3,
                        products.custom_field_4
                     FROM order_items
                     LEFT JOIN products ON order_items.product_id = products.id
                     WHERE order_id = '" . escape($_GET['id']) . "' AND ship_to_id = '" . $ship_tos[$key]['ship_to_id'] . "'";
            $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
            
            $order_items = array();
            
            // loop through order items in order to add them to array
            while ($row = mysqli_fetch_assoc($result)) {
                $order_items[] = $row;
            }
            
            // loop through order items in order to output them
            foreach ($order_items as $order_item) {
                $order_item_id = $order_item['id'];
                $product_name = $order_item['product_name'];
                $image_name = $order_item['image_name'];
                $quantity = $order_item['quantity'];
                $product_price = $order_item['price'] / 100;
                $product_total = ($order_item['price'] * $quantity) / 100;
                $recurring_payment_period = $order_item['recurring_payment_period'];
                $recurring_number_of_payments = $order_item['recurring_number_of_payments'];
                $recurring_start_date = $order_item['recurring_start_date'];
                $calendar_event_id = $order_item['calendar_event_id'];
                $recurrence_number = $order_item['recurrence_number'];
                $show_shipped_quantity = $order_item['show_shipped_quantity'];
                $shipped_quantity = $order_item['shipped_quantity'];
                $product_id = $order_item['product_id'];
                $short_description = h($order_item['short_description']);
                
                $output_image = '';
                // if a product was found, include link to product
                if ($product_id) {
                    $output_product_name = '<span class="cursor-pointer" onclick="document.location.href = \'edit_product.php?id=' . $product_id . '\'">' . lang('Product ID / SKU') . ': ' .  h($product_name) . '</span>';
                    // if a product image_name found, output
                    if ($image_name) {
                        $output_image = '<img  class="rounded-4 card-img cursor-pointer d-print-none" style="top:52px;position:sticky;" src="' .  PATH . $image_name . '" onclick="document.location.href = \'edit_product.php?id=' . $product_id . '\'"/>';
                    } 
                
                // else a product was not found, so do not include a link to product   
                } else {
                    $output_product_name = lang('Product ID / SKU') . ': ' .h($product_name);
                    // if a product image_name found, output
                    if ($image_name) {
                        $output_image = '<img class="rounded-4 card-img d-print-none" style="top:52px;position:sticky;" src="' .  PATH . $image_name . '"/>';
                    } 
                }
                
                // if calendars is enabled and this order item is for a calendar event reservation, then add calendar event name and date and time range to short description
                if ((CALENDARS == TRUE) && ($calendar_event_id != 0)) {
                    $calendar_event = get_calendar_event($calendar_event_id, $recurrence_number);
                    
                    $short_description .=
                        '<p>
                            ' . h($calendar_event['name']) . '<br />
                            ' . $calendar_event['date_and_time_range'] . '
                        </p>';
                }
                
                $output_shipped_quantity_field = '';
                
                // if the order is complete, then show shipped quantity field
                if ($status != 'incomplete') {
                    // if the form has not been submitted yet and show shipped quantity is enabled, then prefill shipped quantity field
                    if (
                        ($liveform->field_in_session('id') == FALSE)
                        && ($show_shipped_quantity == 1)
                    ) {
                        $liveform->assign_field_value('order_item_id_' . $order_item_id . '_shipped_quantity', $shipped_quantity);
                    }
                    $output_shipped_quantity_field = $liveform->output_field(array('class'=>'form-control text-center border-start-0 border-end-0','type'=>'text', 'id'=>'order_item_id_' . $order_item_id . '_shipped_quantity', 'name'=>'order_item_id_' . $order_item_id . '_shipped_quantity', 'size'=>'2', 'maxlength'=>'9', 'inputmode'=>'numeric', 'data-inputmask-alias'=>'decimal', 'data-inputmask-placeholder'=>'0'));
                    
                    
                    $output_shipped_quantity_field = '
                        <div class="input-group number-controls">
                            <button class="btn material-icons minus border border-end-0 d-print-none" type="button">remove</button>
                            ' . $output_shipped_quantity_field . '
                            <button class="btn material-icons plus border border-start-0 d-print-none" type="button">add</button>
                        </div>';


                    
                    
                    
                }
                
                $output_recurring_payment_period = '';
                $output_recurring_number_of_payments = '';
                $output_recurring_start_date = '';
                
                // if order item is a recurring order item, then prepare 
                if ($recurring_payment_period != '') {
                    $output_recurring_payment_period = '
                        <div class="col-12 col-sm-4 col-md-auto">
                            <div class="p-2  border h-100 rounded">
                                <div class="h-50 text-center text-muted">' . lang('Payment Period') . '</div>
                                <div class="h-50 text-center">' . lang($recurring_payment_period) . '</div>
                            </div>
                        </div>';
                    
                    // if the number of payments is set to 0, then change value to [no limit]
                    if ($recurring_number_of_payments == 0) {
                        $output_recurring_number_of_payments = '[no limit]';
                        
                    // else the number of payments is greater than 0, so show value
                    } else {
                        $output_recurring_number_of_payments = number_format($recurring_number_of_payments);
                    }
                    $output_recurring_number_of_payments = '
                        <div class="col-12 col-sm-4 col-md-auto">
                            <div class="p-2  border h-100 rounded">
                                <div class="h-50 text-center text-muted">' . lang('Number of Payments') . '</div>
                                <div class="h-50 text-center">' . $output_recurring_number_of_payments . '</div>
                            </div>
                        </div>';
                        
                    $output_recurring_start_date = '
                        <div class="col-12 col-sm-4 col-md-auto">
                            <div class="p-2  border h-100 rounded">
                                <div class="h-50 text-center text-muted">' . lang('Start Date') . '</div>
                                <div class="h-50 text-center">' . get_absolute_time(array('timestamp' => strtotime($recurring_start_date), 'type' => 'date')) . '</div>
                            </div>
                        </div>';
                }

                $output_custom_field_1 = '';
                // If the first custom product field is active, then output cell for it.
                if (ECOMMERCE_CUSTOM_PRODUCT_FIELD_1_LABEL != '') {

                    $output_custom_field_1 ='
                        <div class="col-12 col-sm-6 col-md-auto">
                            <div class="p-2  border h-100 rounded">
                                <div class="h-50 text-center text-muted">' . $output_custom_field_1_heading . '</div>
                                <div class="h-50 text-center">' . h($order_item['custom_field_1']) .'</div>
                            </div>
                        </div>';
                }

                $output_custom_field_2= '';

                // If the second custom product field is active, then output cell for it.
                if (ECOMMERCE_CUSTOM_PRODUCT_FIELD_2_LABEL != '') {
                    $output_custom_field_2 ='
                    <div class="col-12 col-sm-6 col-md-auto">
                        <div class="p-2  border h-100 rounded">
                            <div class="h-50 text-center text-muted">' . $output_custom_field_2_heading . '</div>
                            <div class="h-50 text-center">' . h($order_item['custom_field_2']) .'</div>
                        </div>
                    </div>';
                }

                $output_custom_field_2 = '';

                // If the third custom product field is active, then output cell for it.
                if (ECOMMERCE_CUSTOM_PRODUCT_FIELD_3_LABEL != '') {
                    $output_custom_field_3 ='
                    <div class="col-12 col-sm-6 col-md-auto">
                        <div class="p-2  border h-100 rounded">
                            <div class="h-50 text-center text-muted">' . $output_custom_field_3_heading . '</div>
                            <div class="h-50 text-center">' . h($order_item['custom_field_3']) .'</div>
                        </div>
                    </div>';
                }

                $output_custom_field_4 = '';

                // If the fourth custom product field is active, then output cell for it.
                if (ECOMMERCE_CUSTOM_PRODUCT_FIELD_4_LABEL != '') {
                    $output_custom_field_4 ='
                    <div class="col-12 col-sm-6 col-md-auto">
                        <div class="p-2  border h-100 rounded">
                            <div class="h-50 text-center text-muted">' . $output_custom_field_4_heading . '</div>
                            <div class="h-50 text-center">' . h($order_item['custom_field_4']) .'</div>
                        </div>
                    </div>';
                }

                $output_gift_cards = '';
                
                // get maximum quantity number, so we can determine how many gift cards there are for this order item
                $query = "SELECT MAX(quantity_number) as number_of_gift_cards FROM order_item_gift_cards WHERE order_item_id = '$order_item_id'";
                $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
                $row = mysqli_fetch_assoc($result);
                $number_of_gift_cards = $row['number_of_gift_cards'];
                
                // if there is a gift card for this order item, then prepare to output gift card data
                if ($number_of_gift_cards > 0) {
                    // create loop in order to output all gift cards
                    for ($quantity_number = 1; $quantity_number <= $number_of_gift_cards; $quantity_number++) {
                     
                        $output_legend_content = lang('Gift Card');
                        
                        // if number of gift cards is greater than 1, then add quantity number to legend
                        if ($number_of_gift_cards > 1) {
                            $output_legend_content .= ' (' . lang(array( 'string'=>'{var:1} of {var:2}','vars'=>array($quantity_number,$number_of_gift_cards) )) . ')'; 
                        }
                        
                        $output_legend = '';
                        
                        // if the legend content is not blank, then output a legend
                        if ($output_legend_content != '') {
                            $output_legend = '<div class="card-header bg-reset border-0 text-uppercase h5 text-secondary fw-bold">' . $output_legend_content . '</div>';
                        }

                        // Get gift card data from database.
                        $order_item_gift_card = db_item(
                            "SELECT
                                gift_cards.id,
                                gift_cards.code,
                                order_item_gift_cards.from_name,
                                order_item_gift_cards.recipient_email_address,
                                order_item_gift_cards.message,
                                order_item_gift_cards.delivery_date
                            FROM order_item_gift_cards
                            LEFT JOIN gift_cards ON ((order_item_gift_cards.order_item_id = gift_cards.order_item_id) AND (order_item_gift_cards.quantity_number = gift_cards.quantity_number))
                            WHERE
                                (order_item_gift_cards.order_item_id = '" . $order_item_id . "')
                                AND (order_item_gift_cards.quantity_number = '" . $quantity_number . "')");

                        $output_code = '';

                        if ($order_item_gift_card['id']) {
                            $output_code = '<a class="link-secondary" href="edit_gift_card.php?id=' . $order_item_gift_card['id'] . '">' . output_gift_card_code($order_item_gift_card['code']) . '</a>';
                        }

                        $output_delivery_date = '';

                        if ($order_item_gift_card['delivery_date'] == '0000-00-00') {
                            $output_delivery_date = lang('Immediate');

                        } else {
                            $output_delivery_date = get_absolute_time(array('timestamp' => strtotime($order_item_gift_card['delivery_date']), 'type' => 'date'));
                        }
                        
                        $output_gift_cards .=
                            '<div class="card my-2">
                                ' . $output_legend . '
                                <div class="card-body">
                                    <div class="row g-1">
                                        <div class="col-12 col-sm-6 col-md-auto">
                                            <div class="p-2 border h-100 rounded">
                                                <div class="h-50 text-muted">' . lang('Code') . '</div>
                                                <div class="h-50">' . $output_code . '</div>
                                            </div>
                                        </div>
                                        <div class="col-12 col-sm-6 col-md-auto">
                                            <div class="p-2 border h-100 rounded">
                                                <div class="h-50 text-center text-muted">' . lang('Amount') . '</div>
                                                <div class="h-50 text-center">' . prepare_amount($product_price) . '</div>
                                            </div>
                                        </div>
                                        <div class="col-12 col-sm-6 col-md-auto">
                                            <div class="p-2 border h-100 rounded">
                                                <div class="h-50 text-muted">' . lang('Recipient Email') . '</div>
                                                <div class="h-50">' . h($order_item_gift_card['recipient_email_address']) . '</div>
                                            </div>
                                        </div>
                                        <div class="col-12 col-sm-6 col-md-auto">
                                            <div class="p-2 border h-100 rounded">
                                                <div class="h-50 text-muted">' . lang('From Name') . '</div>
                                                <div class="h-50">' . h($order_item_gift_card['from_name']) . '</div>
                                            </div>
                                        </div>
                                        <div class="col-12 col-sm-6 col-md-auto">
                                            <div class="p-2 border h-100 rounded">
                                                <div class="h-50 text-muted">' . lang('Message') . '</div>
                                                <div class="h-50">' . nl2br(h($order_item_gift_card['message'])) . '</div>
                                            </div>
                                        </div>
                                        <div class="col-12 col-sm-6 col-md-auto">
                                            <div class="p-2 border h-100 rounded">
                                                <div class="h-50 text-center text-muted">' . lang('Delivery Date') . '</div>
                                                <div class="h-50 text-center">' . $output_delivery_date . '</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>';
                    }
                }

                // assume that there is not a form to output until we find out otherwse
                $output_forms = '';
                
                // get maximum quantity number, so we can determine how many product forms there are for this order item
                $query = "SELECT MAX(quantity_number) as number_of_forms FROM form_data WHERE order_item_id = '$order_item_id'";
                $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
                $row = mysqli_fetch_assoc($result);
                $number_of_forms = $row['number_of_forms'];
                
                // if there is a form for this order item, then prepare to output form
                if ($number_of_forms > 0) {
                    // create loop in order to output all forms
                    for ($quantity_number = 1; $quantity_number <= $number_of_forms; $quantity_number++) {
                       
                        $output_legend_content = lang('Form');
                        
                        // if number of forms is greater than 1, then add quantity number to legend
                        if ($number_of_forms > 1) {
                            $output_legend_content .= ' (' . lang(array( 'string'=>'{var:1} of {var:2}','vars'=>array($quantity_number,$number_of_forms) )) . ')';  
                        }
                        
                        $output_legend = '';
                        
                        // if the legend content is not blank, then output a legend
                        if ($output_legend_content != '') {
                            $output_legend = '<div class="card-header bg-reset border-0 text-uppercase h5 text-secondary fw-bold">' . $output_legend_content . '</div>';
                        }
                        
                        $output_forms .=
                            '<div class="card my-2">
                                ' . $output_legend . '
                                <div class="card-body">
                                    <div class="row g-1">
                                        <span class="col-12"><table>' . get_submitted_product_form_content_without_form_fields($order_item_id, $quantity_number, 'backend') . '</table></span>
                                    </div>
                                </div>
                            </div>';
                    }
                }
                
                $output_form_row = '';
                
                // if there is a form to output, then prepare form row
                if (($output_forms != '') || ($output_gift_cards != '')) {
                    $colspan = 8;

                    // If there is an extra column for the first custom product field, the add one to the colspan.
                    if (ECOMMERCE_CUSTOM_PRODUCT_FIELD_1_LABEL != '') {
                        $colspan++;
                    }

                    // If there is an extra column for the second custom product field, the add one to the colspan.
                    if (ECOMMERCE_CUSTOM_PRODUCT_FIELD_2_LABEL != '') {
                        $colspan++;
                    }

                    // If there is an extra column for the third custom product field, the add one to the colspan.
                    if (ECOMMERCE_CUSTOM_PRODUCT_FIELD_3_LABEL != '') {
                        $colspan++;
                    }

                    // If there is an extra column for the fourth custom product field, the add one to the colspan.
                    if (ECOMMERCE_CUSTOM_PRODUCT_FIELD_4_LABEL != '') {
                        $colspan++;
                    }

                    $output_form_row =
                        '<div class="row g-0">
                            ' . $output_gift_cards . '
                            ' . $output_forms . '
                        </div>';
                }
                //shipable
                $output_order_details .=
                    '<div class="list-group-item">
                            <div class="card-header bg-reset  text-end border-0 text-uppercase h5 text-primary fw-bold">
                                ' . $output_product_name . '
                            </div>
                            <div class="row g-0">
                                <div class="col-4 offset-1 offset-md-0 col-md-2 col-lg-1 px-2">
                                ' .  $output_image . '
                                </div>
                                <div class="col-12 col-12 col-md-10 col-lg-11">
                                    <div class="card-body ">
                                        <div class="row g-1">
                                            <div class="col-12 col-sm-12 col-md-auto">
                                                <div class="p-2  border h-100 rounded">
                                                    <div class="h-50 text-muted">' . lang('Short Description') . '</div>
                                                    <div class="h-50">' . $short_description . '</div>
                                                </div>
                                            </div>
                                            <div class="col-12 col-sm-4 col-md-auto">
                                                <div class="p-2  border h-100 rounded">
                                                    <div class="h-50 text-center text-muted">' . lang('Qty') . '</div>
                                                    <div class="h-50 text-center h4">' . $quantity . '</div>
                                                </div>
                                            </div>
                                            <div class="col-12 col-sm-8 col-md-auto">
                                                <div class="p-2  border h-100 rounded">
                                                    <div class="h-50 text-muted">' . lang('Shipped Qty') . '</div>
                                                    <div class="h-50 pb-2">
                                                        ' . $output_shipped_quantity_field . '
                                                    </div>
                                                </div>
                                            </div> 
                                            <div class="col-12 col-sm-6 col-md-auto">
                                                <div class="p-2  border h-100 rounded">
                                                    <div class="h-50 text-center text-muted">' . lang('Price') . '</div>
                                                    <div class="h-50 text-center">' . prepare_amount($product_price) . '</div>
                                                </div>
                                            </div>
                                            <div class="col-12 col-sm-6 col-md-auto">
                                                <div class="p-2  border h-100 rounded">
                                                    <div class="h-50 text-center text-muted">' . lang('Total') . '</div>
                                                    <div class="h-50 text-center">' . prepare_amount($product_total) . '</div>
                                                </div>
                                            </div>
                                            ' . $output_recurring_payment_period . '
                                            ' . $output_recurring_number_of_payments . '
                                            ' . $output_recurring_start_date . '
                                            ' . $output_custom_field_1 . '
                                            ' . $output_custom_field_2 . '
                                            ' . $output_custom_field_3 . '
                                            ' . $output_custom_field_4 . '
                                        </div>
                                        ' . $output_form_row . '
                                    </div>
                                </div>
                            </div>
                        </div>';
            }
            

            
        
        }
        
        // if the order is complete, then output save
        if ($status != 'incomplete') {
            $output_save_button = '<button type="submit" name="submit_save" value="Save" class="btn my-1  btn-success " data-loading-content="' . lang(array('string'=>'Saving') ) . '"><span class="material-icons me-2">save</span><span class="btn-text" >' . lang(array('string'=>'Save') ) . '</span></button>';
        }

        // if the order is cancelled, output a restore button to revert it to complete
        if ($status == 'cancelled') {
            $output_restore_button = '<button type="submit" name="submit_restore_order" value="restore" class="btn my-1 btn-warning" data-confirm-content="' . lang('Are you sure you want to restore this order to complete?') . '"><i class="bi bi-arrow-counterclockwise me-2"></i><span class="btn-text">' . lang('Restore Order') . '</span></button>';
        }

    // else a real ship to does not exist
    } else {
        $output_custom_field_1_heading = '';

        // If the first custom product field is active, then output heading for it.
        if (ECOMMERCE_CUSTOM_PRODUCT_FIELD_1_LABEL != '') {
            $output_custom_field_1_heading =  h(ECOMMERCE_CUSTOM_PRODUCT_FIELD_1_LABEL);
        }

        $output_custom_field_2_heading = '';

        // If the second custom product field is active, then output heading for it.
        if (ECOMMERCE_CUSTOM_PRODUCT_FIELD_2_LABEL != '') {
            $output_custom_field_2_heading = h(ECOMMERCE_CUSTOM_PRODUCT_FIELD_2_LABEL);
        }

        $output_custom_field_3_heading = '';

        // If the third custom product field is active, then output heading for it.
        if (ECOMMERCE_CUSTOM_PRODUCT_FIELD_3_LABEL != '') {
            $output_custom_field_3_heading = h(ECOMMERCE_CUSTOM_PRODUCT_FIELD_3_LABEL);
        }

        $output_custom_field_4_heading = '';

        // If the fourth custom product field is active, then output heading for it.
        if (ECOMMERCE_CUSTOM_PRODUCT_FIELD_4_LABEL != '') {
            $output_custom_field_4_heading = h(ECOMMERCE_CUSTOM_PRODUCT_FIELD_4_LABEL);
        }
        
        // get all order items in order for this ship to
        $query = "SELECT
                    order_items.id,
                    order_items.product_name,
                    order_items.quantity,
                    order_items.price,
                    order_items.recurring_payment_period,
                    order_items.recurring_number_of_payments,
                    order_items.recurring_start_date,
                    order_items.calendar_event_id,
                    order_items.recurrence_number,
                    products.id as product_id,
                    products.image_name as image_name,
                    products.short_description,
                    products.custom_field_1,
                    products.custom_field_2,
                    products.custom_field_3,
                    products.custom_field_4
                 FROM order_items
                 LEFT JOIN products ON order_items.product_id = products.id
                 WHERE order_id = '" . escape($_GET['id']) . "'";
        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
        
        $order_items = array();
        
        // loop through order items in order to add them to array
        while ($row = mysqli_fetch_assoc($result)) {
            $order_items[] = $row;
        }
        

        // loop through order items in order to output them
        foreach ($order_items as $order_item) {
            $order_item_id = $order_item['id'];
            $image_name = $order_item['image_name'];
            $product_name = $order_item['product_name'];
            $quantity = $order_item['quantity'];
            $product_price = $order_item['price'] / 100;
            $product_total = ($order_item['price'] * $quantity) / 100;
            $recurring_payment_period = $order_item['recurring_payment_period'];
            $recurring_number_of_payments = $order_item['recurring_number_of_payments'];
            $recurring_start_date = $order_item['recurring_start_date'];
            $calendar_event_id = $order_item['calendar_event_id'];
            $recurrence_number = $order_item['recurrence_number'];
            $product_id = $order_item['product_id'];
            $short_description = h($order_item['short_description']);
            

            $output_image = '';
            // if a product was found, include link to product
            if ($product_id) {
                $output_product_name = '<span class="cursor-pointer" onclick="document.location.href = \'edit_product.php?id=' . $product_id . '\'">' . lang('Product ID / SKU') . ': ' .  h($product_name) . '</span>';
                // if a product image_name found, output
                if ($image_name) {
                    $output_image = '<img  class="rounded-4 card-img cursor-pointer d-print-none" style="top:52px;position:sticky;" src="' .  PATH . $image_name . '" onclick="document.location.href = \'edit_product.php?id=' . $product_id . '\'"/>';
                } 
            
            // else a product was not found, so do not include a link to product   
            } else {
                $output_product_name = lang('Product ID / SKU') . ': ' .h($product_name);
                // if a product image_name found, output
                if ($image_name) {
                    $output_image = '<img  class="rounded-4 card-img d-print-none" style="top:52px;position:sticky;" src="' .  PATH . $image_name . '"/>';
                } 
            }
            
            // if calendars is enabled and this order item is for a calendar event reservation, then add calendar event name and date and time range to short description
            if ((CALENDARS == TRUE) && ($calendar_event_id != 0)) {
                $calendar_event = get_calendar_event($calendar_event_id, $recurrence_number);
                
                $short_description .=
                    '<p>
                        ' . h($calendar_event['name']) . '<br />
                        ' . $calendar_event['date_and_time_range'] . '
                    </p>';
            }
            
            $output_recurring_payment_period = '';
            $output_recurring_number_of_payments = '';
            $output_recurring_start_date = '';
            
            // if order item is a recurring order item, then prepare 
            if ($recurring_payment_period != '') {
                $output_recurring_payment_period = '
                    <div class="col-12 col-sm-4 col-md-auto">
                        <div class="p-2  border h-100 rounded">
                            <div class="h-50 text-center text-muted">' . lang('Payment Period') . '</div>
                            <div class="h-50 text-center">' . lang($recurring_payment_period) . '</div>
                        </div>
                    </div>';
                
                // if the number of payments is set to 0, then change value to [no limit]
                if ($recurring_number_of_payments == 0) {
                    $output_recurring_number_of_payments = '[no limit]';
                    
                // else the number of payments is greater than 0, so show value
                } else {
                    $output_recurring_number_of_payments = number_format($recurring_number_of_payments);
                }
                $output_recurring_number_of_payments = '
                    <div class="col-12 col-sm-4 col-md-auto">
                        <div class="p-2  border h-100 rounded">
                            <div class="h-50 text-center text-muted">' . lang('Number of Payments') . '</div>
                            <div class="h-50 text-center">' . $output_recurring_number_of_payments . '</div>
                        </div>
                    </div>';
                        
                $output_recurring_start_date = '
                    <div class="col-12 col-sm-4 col-md-auto">
                        <div class="p-2  border h-100 rounded">
                            <div class="h-50 text-center text-muted">' . lang('Start Date') . '</div>
                            <div class="h-50 text-center">' . get_absolute_time(array('timestamp' => strtotime($recurring_start_date), 'type' => 'date')) . '</div>
                        </div>
                    </div>';
            }

            $output_custom_field_1 = '';

            // If the first custom product field is active, then output cell for it.
            if (ECOMMERCE_CUSTOM_PRODUCT_FIELD_1_LABEL != '') {
                $output_custom_field_1 ='
                <div class="col-12 col-sm-6 col-md-auto">
                    <div class="p-2  border h-100 rounded">
                        <div class="h-50 text-center text-muted">' . $output_custom_field_1_heading . '</div>
                        <div class="h-50 text-center">' . h($order_item['custom_field_1']) .'</div>
                    </div>
                </div>';
            }

            $output_custom_field_2 = '';

            // If the second custom product field is active, then output cell for it.
            if (ECOMMERCE_CUSTOM_PRODUCT_FIELD_2_LABEL != '') {
                $output_custom_field_2 ='
                <div class="col-12 col-sm-6 col-md-auto">
                    <div class="p-2  border h-100 rounded">
                        <div class="h-50 text-center text-muted">' . $output_custom_field_2_heading . '</div>
                        <div class="h-50 text-center">' . h($order_item['custom_field_2']) .'</div>
                    </div>
                </div>';
            }

            $output_custom_field_3_cell = '';

            // If the third custom product field is active, then output cell for it.
            if (ECOMMERCE_CUSTOM_PRODUCT_FIELD_3_LABEL != '') {
                $output_custom_field_3 ='
                <div class="col-12 col-sm-6 col-md-auto">
                    <div class="p-2  border h-100 rounded">
                        <div class="h-50 text-center text-muted">' . $output_custom_field_3_heading . '</div>
                        <div class="h-50 text-center">' . h($order_item['custom_field_3']) .'</div>
                    </div>
                </div>';
            }

            $output_custom_field_4 = '';

            // If the fourth custom product field is active, then output cell for it.
            if (ECOMMERCE_CUSTOM_PRODUCT_FIELD_4_LABEL != '') {
                $output_custom_field_4 ='
                <div class="col-12 col-sm-6 col-md-auto">
                    <div class="p-2  border h-100 rounded">
                        <div class="h-50 text-center text-muted">' . $output_custom_field_4_heading . '</div>
                        <div class="h-50 text-center">' . h($order_item['custom_field_4']) .'</div>
                    </div>
                </div>';
            }

            $output_gift_cards = '';
            
            // get maximum quantity number, so we can determine how many gift cards there are for this order item
            $query = "SELECT MAX(quantity_number) as number_of_gift_cards FROM order_item_gift_cards WHERE order_item_id = '$order_item_id'";
            $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
            $row = mysqli_fetch_assoc($result);
            $number_of_gift_cards = $row['number_of_gift_cards'];
            
            // if there is a gift card for this order item, then prepare to output gift card data
            if ($number_of_gift_cards > 0) {
                // create loop in order to output all gift cards
                for ($quantity_number = 1; $quantity_number <= $number_of_gift_cards; $quantity_number++) {
                   
                    $output_legend_content = lang('Gift Card');
                    
                    // if number of gift cards is greater than 1, then add quantity number to legend
                    if ($number_of_gift_cards > 1) {
                        $output_legend_content .= ' (' . lang(array( 'string'=>'{var:1} of {var:2}','vars'=>array($quantity_number,$number_of_gift_cards) )) . ')';
                    }
                    
                    $output_legend = '';
                    
                    // if the legend content is not blank, then output a legend
                    if ($output_legend_content != '') {
                        $output_legend = '<div class="card-header bg-reset border-0 text-uppercase h5 text-secondary fw-bold">' . $output_legend_content . '</div>';
                    }

                    // Get gift card data from database.
                    $order_item_gift_card = db_item(
                        "SELECT
                            gift_cards.id,
                            gift_cards.code,
                            order_item_gift_cards.from_name,
                            order_item_gift_cards.recipient_email_address,
                            order_item_gift_cards.message,
                            order_item_gift_cards.delivery_date
                        FROM order_item_gift_cards
                        LEFT JOIN gift_cards ON ((order_item_gift_cards.order_item_id = gift_cards.order_item_id) AND (order_item_gift_cards.quantity_number = gift_cards.quantity_number))
                        WHERE
                            (order_item_gift_cards.order_item_id = '" . $order_item_id . "')
                            AND (order_item_gift_cards.quantity_number = '" . $quantity_number . "')");

                    $output_code = '';

                    if ($order_item_gift_card['id']) {
                        $output_code = '<a class="link-secondary" href="edit_gift_card.php?id=' . $order_item_gift_card['id'] . '">' . output_gift_card_code($order_item_gift_card['code']) . '</a>';
                    }

                    $output_delivery_date = '';

                    if ($order_item_gift_card['delivery_date'] == '0000-00-00') {
                        $output_delivery_date = lang('Immediate');

                    } else {
                        $output_delivery_date = get_absolute_time(array('timestamp' => strtotime($order_item_gift_card['delivery_date']), 'type' => 'date'));
                    }
                    
                    $output_gift_cards .=
                        '<div class="card my-2">
                            ' . $output_legend . '
                            <div class="card-body">
                                <div class="row g-1">
                                    <div class="col-12 col-sm-6 col-md-auto">
                                        <div class="p-2 border h-100 rounded">
                                            <div class="h-50 text-muted">' . lang('Code') . '</div>
                                            <div class="h-50">' . $output_code . '</div>
                                        </div>
                                    </div>
                                    <div class="col-12 col-sm-6 col-md-auto">
                                        <div class="p-2 border h-100 rounded">
                                            <div class="h-50 text-center text-muted">' . lang('Amount') . '</div>
                                            <div class="h-50 text-center">' . prepare_amount($product_price) . '</div>
                                        </div>
                                    </div>
                                    <div class="col-12 col-sm-6 col-md-auto">
                                        <div class="p-2 border h-100 rounded">
                                            <div class="h-50 text-muted">' . lang('Recipient Email') . '</div>
                                            <div class="h-50">' . h($order_item_gift_card['recipient_email_address']) . '</div>
                                        </div>
                                    </div>
                                    <div class="col-12 col-sm-6 col-md-auto">
                                        <div class="p-2 border h-100 rounded">
                                            <div class="h-50 text-muted">' . lang('From Name') . '</div>
                                            <div class="h-50">' . h($order_item_gift_card['from_name']) . '</div>
                                        </div>
                                    </div>
                                    <div class="col-12 col-sm-6 col-md-auto">
                                        <div class="p-2 border h-100 rounded">
                                            <div class="h-50 text-muted">' . lang('Message') . '</div>
                                            <div class="h-50">' . nl2br(h($order_item_gift_card['message'])) . '</div>
                                        </div>
                                    </div>
                                    <div class="col-12 col-sm-6 col-md-auto">
                                        <div class="p-2 border h-100 rounded">
                                            <div class="h-50 text-center text-muted">' . lang('Delivery Date') . '</div>
                                            <div class="h-50 text-center">' . $output_delivery_date . '</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>';
                        
                }
            }
            
            // assume that there is not a form to output until we find out otherwse
            $output_forms = '';
            
            // get maximum quantity number, so we can determine how many product forms there are for this order item
            $query = "SELECT MAX(quantity_number) as number_of_forms FROM form_data WHERE order_item_id = '$order_item_id'";
            $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
            $row = mysqli_fetch_assoc($result);
            $number_of_forms = $row['number_of_forms'];
            
            // if there is a form for this order item, then prepare to output form
            if ($number_of_forms > 0) {
                // create loop in order to output all forms
                for ($quantity_number = 1; $quantity_number <= $number_of_forms; $quantity_number++) {
                
                    $output_legend_content = lang('Form');
                    
                    // if number of forms is greater than 1, then add quantity number to legend
                    if ($number_of_forms > 1) {
                        $output_legend_content .= ' (' . lang(array( 'string'=>'{var:1} of {var:2}','vars'=>array($quantity_number,$number_of_forms) )) . ')';
                    }
                    
                    $output_legend = '';
                    
                    // if the legend content is not blank, then output a legend
                    if ($output_legend_content != '') {
                        $output_legend = '<div class="card-header bg-reset border-0 text-uppercase h5 text-secondary fw-bold">' . $output_legend_content . '</legend>';
                    }
                    
                    $output_forms .=
                        '<div class="card my-2">
                            ' . $output_legend . '
                            <div class="card-body">
                                <div class="row g-1">
                                    <span class="col-12"><table>' . get_submitted_product_form_content_without_form_fields($order_item_id, $quantity_number, 'backend') . '</table></span>
                                </div>
                            </div>
                        </div>';
                }
            }
            
            $output_form_row = '';
            
            // if there is a form to output, then prepare form row
            if (($output_forms != '') || ($output_gift_cards != '')) {
                $colspan = 7;

                // If there is an extra column for the first custom product field, the add one to the colspan.
                if (ECOMMERCE_CUSTOM_PRODUCT_FIELD_1_LABEL != '') {
                    $colspan++;
                }

                // If there is an extra column for the second custom product field, the add one to the colspan.
                if (ECOMMERCE_CUSTOM_PRODUCT_FIELD_2_LABEL != '') {
                    $colspan++;
                }

                // If there is an extra column for the third custom product field, the add one to the colspan.
                if (ECOMMERCE_CUSTOM_PRODUCT_FIELD_3_LABEL != '') {
                    $colspan++;
                }

                // If there is an extra column for the fourth custom product field, the add one to the colspan.
                if (ECOMMERCE_CUSTOM_PRODUCT_FIELD_4_LABEL != '') {
                    $colspan++;
                }

                $output_form_row =
                    '<div class="row g-0">
                        ' . $output_gift_cards . '
                        ' . $output_forms . '
                    </div>';
            }
            //not shipable
            $output_order_details .=
                '<div class="list-group-item">
                        <div class="card-header bg-reset  text-end border-0 text-uppercase h5 text-primary fw-bold">
                            ' . $output_product_name . '
                        </div>
                        <div class="row g-0">
                            <div class="col-4 offset-1 offset-md-0 col-md-2 col-lg-1 px-2">
                            ' .  $output_image . '
                            </div>
                            <div class="col-12 col-12 col-md-10 col-lg-11">
                                <div class="card-body ">
                                    <div class="row g-1">
                                        <div class="col-12 col-sm-12 col-md-auto">
                                            <div class="p-2  border h-100 rounded">
                                                <div class="h-50 text-muted">' . lang('Short Description') . '</div>
                                                <div class="h-50">' . $short_description . '</div>
                                            </div>
                                        </div>
                                        <div class="col-12 col-sm-4 col-md-auto">
                                            <div class="p-2  border h-100 rounded">
                                                <div class="h-50 text-center text-muted">' . lang('Qty') . '</div>
                                                <div class="h-50 text-center h4">' . $quantity . '</div>
                                            </div>
                                        </div>
                                        <div class="col-12 col-sm-4 col-md-auto">
                                            <div class="p-2  border h-100 rounded">
                                                <div class="h-50 text-center text-muted">' . lang('Price') . '</div>
                                                <div class="h-50 text-center">' . prepare_amount($product_price) . '</div>
                                            </div>
                                        </div>
                                        <div class="col-12 col-sm-4 col-md-auto">
                                            <div class="p-2  border h-100 rounded">
                                                <div class="h-50 text-center text-muted">' . lang('Total') . '</div>
                                                <div class="h-50 text-center">' . prepare_amount($product_total) . '</div>
                                            </div>
                                        </div>
                                        ' . $output_recurring_payment_period . '
                                        ' . $output_recurring_number_of_payments . '
                                        ' . $output_recurring_start_date . '
                                        ' . $output_custom_field_1 . '
                                        ' . $output_custom_field_2 . '
                                        ' . $output_custom_field_3 . '
                                        ' . $output_custom_field_4 . '   
                                    </div>
                                    ' . $output_form_row . '
                                </div>

                            </div>
                        </div>
                    </div>';
        }
    }

    // if multi currency is enabled, output miscellaneous fieldset with currency information
    if (ECOMMERCE_MULTICURRENCY === true) {
        $currency_name_and_code = '';
        
        // if there is a currency code, then output it
        if ($currency_code != '') {
            $currency_name_and_code = get_currency_name_from_code($currency_code) . ' (' . $currency_code . ')';
        }
        $output_currency_code_row =
        '<div class="row" >
            <span class=" col text-muted">' . lang('Currency') . ':</span>
            <span class="col text-end">' . h($currency_name_and_code) . '</span>
        </div>';
        
    } else {
        $output_currency_code_row = '';
    }
    $output_applied_gift_cards = '';

    // if there is a gift card discount and there is at least one applied gift card, then prepare to output applied gift cards
    // this double check is not redundant, because there can be a situation where there is a discount with no gift cards if there was an error when the gift card transaction was submitted
    if (($gift_card_discount > 0)  && (count($applied_gift_cards) > 0)) {
        // loop through applied gift cards in order to prepare to output them
        foreach ($applied_gift_cards as $applied_gift_card) {
            if ($applied_gift_card['givex'] == 0) {
                $output_gift_card_code = '<a href="edit_gift_card.php?id=' . $applied_gift_card['gift_card_id'] . '">' . output_gift_card_code($applied_gift_card['code']) . '</a>';
            } else {
                $output_gift_card_code = h($applied_gift_card['code']);
            }

            $output_applied_gift_card_rows .=
                '<div class="row" >
                    <span class="translateable col text-muted">' . lang('Code') . ':</span>
                    <span class="col text-end">' . $output_gift_card_code . '</span>
                </div>
                <div class="row" >
                    <span class="translateable col text-muted">' . lang('Amount') . ':</span>
                    <span class="col text-end">' . BASE_CURRENCY_SYMBOL . number_format($applied_gift_card['amount'] / 100, 2, '.', ',') . '</span>
                </div>
                <div class="row" >
                    <span class="translateable col text-muted">' . lang('Givex Auth') . ' #:</span>
                    <span class="col text-end">' . h($applied_gift_card['authorization_number']) . '</span>
                </div>
                <div class="row" >
                    <span class="translateable col text-muted">' . lang('Remaining Balance') . ':</span>
                    <span class="col text-end">' . BASE_CURRENCY_SYMBOL . number_format($applied_gift_card['new_balance'] / 100, 2, '.', ',') . '</span>
                </div>';
        }
        
        $output_applied_gift_cards =
            '<div class="card mt-3">
                    <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
                        ' . lang('Applied Gift Cards') . '
                    </div>
                    <div class="card-body">
                        ' . $output_applied_gift_card_rows . '
                    </div>
                </div>';
    }


	// Get Payment Gateway for button bar output
	$output_gateway_buttons = '';
    $output_payment_mode = '';
	if(ECOMMERCE_PAYMENT_GATEWAY == 'Iyzipay'){
        // if test or live mode for iyzipay gateway. 
		if (ECOMMERCE_PAYMENT_GATEWAY_MODE == 'test') {
			$output_payment_mode = 'sandbox-merchant';
		}else {
            $output_payment_mode = 'merchant';
		}
		if($transaction_id){
            $output_gateway_buttons = '<button type="button" class="btn btn-link link-secondary py-0 mb-2" title="' . ECOMMERCE_PAYMENT_GATEWAY . '" onclick="window.open(\'https://' . $output_payment_mode . '.iyzipay.com/transactions/' . $transaction_id . '\', \'popup\', \'toolbar=no,location=no,directories=no,status=yes,menubar=no,resizable=yes,copyhistory=no,scrollbars=yes,width=500,height=500\');"><span class="material-icons me-1">account_balance</span>' . lang('View Order') . '</button>';
            // Show the refund button only when payment completed and the order
            // is not already cancelled. The cancel button itself is NOT built
            // here - it must stay gateway-independent (see $output_cancel_button
            // below) so bank-transfer / cash-on-delivery orders can be cancelled.
            if ($status != 'cancelled' && $status != 'incomplete') {
                $iyzico_remaining_cents = $total_cents - $refunded_amount_cents;
                if ($iyzico_remaining_cents > 0) {
                    $output_gateway_buttons .= '<button type="button" class="btn btn-link link-secondary py-0 mb-2" data-bs-toggle="modal" data-bs-target="#iyzico-refund-modal"><i class="bi bi-arrow-return-left me-1"></i>' . lang('Refund') . '</button>';
                }
            }
		}else{
		    $output_gateway_buttons = '<button type="button" class="btn btn-link link-secondary py-0 mb-2" title="' . ECOMMERCE_PAYMENT_GATEWAY . '" onclick="window.open(\'https://' . $output_payment_mode . '.iyzipay.com/dashboard\', \'popup\', \'toolbar=no,location=no,directories=no,status=yes,menubar=no,resizable=yes,copyhistory=no,scrollbars=yes,width=500,height=500\');"><span class="material-icons me-2">account_balance</span>' . lang(array('string'=>'View Orders') ) . '</button>';
		}

	}

    // Build the iyzico refund modal (separate form, placed outside the main form).
    // The CANCEL modal is built further down and is gateway-independent.
    $output_iyzico_modals = '';
    if (
        defined('ECOMMERCE_PAYMENT_GATEWAY') && ECOMMERCE_PAYMENT_GATEWAY == 'Iyzipay'
        && $transaction_id
        && $status != 'cancelled'
        && $status != 'incomplete'
    ) {
        $iyzico_remaining_cents = $total_cents - $refunded_amount_cents;
        $iyzico_remaining_formatted = number_format($iyzico_remaining_cents / 100, 2, '.', ',');
        $iyzico_refunded_formatted = number_format($refunded_amount_cents / 100, 2, '.', ',');

        // Refund modal
        if ($iyzico_remaining_cents > 0) {
            $output_iyzico_modals .=
                '<div class="modal fade" id="iyzico-refund-modal" tabindex="-1" aria-labelledby="iyzico-refund-modal-label" aria-hidden="true">
                    <div class="modal-dialog">
                        <form method="post">
                            ' . get_token_field() . '
                            <input type="hidden" name="id" value="' . h($_GET['id']) . '">
                            <input type="hidden" name="send_to" value="' . h($_GET['send_to'] ?? '') . '">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title" id="iyzico-refund-modal-label">' . lang('Refund via Iyzico') . '</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="' . lang('Close') . '"></button>
                                </div>
                                <div class="modal-body">
                                    ' . ($refunded_amount_cents > 0 ?
                                        '<p class="text-muted small">' . lang('Total Refunded') . ': ' . BASE_CURRENCY_SYMBOL . $iyzico_refunded_formatted . '</p>'
                                    : '') . '
                                    <label class="form-label fw-semibold">' . lang('Refund Amount') . ' (' . lang('max') . ': ' . BASE_CURRENCY_SYMBOL . $iyzico_remaining_formatted . ')</label>
                                    <div class="input-group">
                                        <span class="input-group-text">' . BASE_CURRENCY_SYMBOL . '</span>
                                        <input type="number" class="form-control" name="refund_amount" step="0.01" min="0.01" max="' . $iyzico_remaining_formatted . '" value="' . $iyzico_remaining_formatted . '" required>
                                    </div>
                                    <p class="form-text text-muted mt-2">' . lang('This will refund the specified amount via iyzico.') . '</p>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">' . lang('Close') . '</button>
                                    <button type="submit" name="submit_iyzico_refund" value="refund" class="btn btn-primary">' . lang('Confirm Refund') . '</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>';
        }

    }

    // ── Order cancellation (gateway-independent) ────────────────────────
    // Deliberately NOT gated on ECOMMERCE_PAYMENT_GATEWAY or $transaction_id:
    // an operator has to be able to cancel a bank-transfer, cash-on-delivery
    // or zero-total order too. When the order WAS paid through iyzico we pass
    // attempt_refund=true on submit so the payment is voided as well; the
    // modal copy below tells the operator which of the two will happen.
    $output_cancel_button = '';
    $output_cancel_modal  = '';

    // ── Manual refund state ─────────────────────────────────────────────
    //
    // process_order_cancellation() writes refund_status = 'manual_required'
    // when the gateway could not void the payment, or would not have been
    // asked to. Until 2026.4.10 nothing could clear that: the refund itself
    // happens in the provider's dashboard, outside this software, so no code
    // path here ever learned it was done. The flag was therefore permanent,
    // and the dashboard card built on it would have shown the same orders
    // forever — a queue that never drains stops being read.
    //
    // The button below does not move money. It records the operator's
    // statement that they moved it, which is the only thing this side can
    // honestly know, and it leaves an activity log entry naming them.
    $output_refund_banner = '';
    $output_refund_modal  = '';

    // From the copy taken next to the main order query; see the note there.
    $refund_state = $order_refund_status;
    $refund_amount_text = number_format($total_cents / 100, 2, ',', '.')
        . ($currency_code != '' ? ' ' . h($currency_code) : '');

    if (in_array($refund_state, array('manual_required', 'failed', 'pending'), true)) {

        $output_refund_banner =
            '<div class="alert alert-warning d-flex flex-wrap align-items-center gap-2 d-print-none" role="alert">
                <i class="bi bi-cash-coin"></i>
                <div class="flex-grow-1">
                    <strong>' . lang('Refund Pending') . '</strong> · ' . $refund_amount_text . '
                    <div class="small">' . lang('The payment was NOT refunded automatically. Process the refund in your payment provider dashboard.') . '</div>
                </div>
                <button type="button" class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#order-refund-modal">
                    <i class="bi bi-check2 me-1"></i>' . lang('Mark refund as processed') . '
                </button>
            </div>';

        $output_refund_modal =
            '<div class="modal fade" id="order-refund-modal" tabindex="-1" aria-labelledby="order-refund-modal-label" aria-hidden="true">
                <div class="modal-dialog">
                    <form method="post">
                        ' . get_token_field() . '
                        <input type="hidden" name="id" value="' . h($_GET['id']) . '">
                        <input type="hidden" name="send_to" value="' . h($_GET['send_to'] ?? '') . '">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="order-refund-modal-label">' . lang('Mark refund as processed') . '</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="' . lang('Close') . '"></button>
                            </div>
                            <div class="modal-body">
                                <p>' . lang('This only records that you processed the refund; it does not move any money.') . '</p>
                                <p class="fw-semibold mb-2">' . $refund_amount_text . '</p>
                                <label class="form-label fw-semibold mt-2" for="refund_reference">' . lang('Refund reference') . ' <span class="text-muted fw-normal">(' . lang('optional') . ')</span></label>
                                <input type="text" class="form-control" id="refund_reference" name="refund_reference" maxlength="255">
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">' . lang('Close') . '</button>
                                <button type="submit" name="submit_mark_refunded" value="refunded" class="btn btn-success">' . lang('Mark refund as processed') . '</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>';

    } elseif ($refund_state === 'refunded') {

        // Shown too, so the order carries its own evidence rather than the
        // absence of a warning.
        $refund_done_when = ($order_refunded_at > 0)
            ? ' · ' . get_relative_time(array('timestamp' => $order_refunded_at))
            : '';

        $refund_done_reference = ($order_refund_reference !== '')
            ? ' · ' . h($order_refund_reference)
            : '';

        $output_refund_banner =
            '<div class="alert alert-success d-flex align-items-center gap-2 d-print-none" role="alert">
                <i class="bi bi-check2-circle"></i>
                <div><strong>' . lang('Refunded') . '</strong>' . $refund_done_when . $refund_done_reference . '</div>
            </div>';
    }
    if ($status != 'cancelled' && $status != 'incomplete') {

        $cancel_voids_payment =
            defined('ECOMMERCE_PAYMENT_GATEWAY') && ECOMMERCE_PAYMENT_GATEWAY == 'Iyzipay'
            && $transaction_id
            && $total_cents > $refunded_amount_cents;

        $cancel_payment_note = $cancel_voids_payment
            ? '<p class="text-muted small">' . lang('This will void the payment via iyzico.') . '</p>'
            : ($total_cents > 0
                ? '<p class="text-warning small">' . lang('The payment will NOT be refunded automatically. Process the refund in your payment provider dashboard.') . '</p>'
                : '');

        $output_cancel_button =
            '<button type="button" class="btn btn-link link-danger py-0 mb-2" data-bs-toggle="modal" data-bs-target="#order-cancel-modal"><i class="bi bi-x-circle me-1"></i>' . lang('Cancel Order') . '</button>';

        $output_cancel_modal =
            '<div class="modal fade" id="order-cancel-modal" tabindex="-1" aria-labelledby="order-cancel-modal-label" aria-hidden="true">
                <div class="modal-dialog">
                    <form method="post">
                        ' . get_token_field() . '
                        <input type="hidden" name="id" value="' . h($_GET['id']) . '">
                        <input type="hidden" name="send_to" value="' . h($_GET['send_to'] ?? '') . '">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="order-cancel-modal-label">' . lang('Confirm Cancellation') . '</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="' . lang('Close') . '"></button>
                            </div>
                            <div class="modal-body">
                                <p>' . lang('Are you sure you want to cancel this order?') . '</p>
                                ' . $cancel_payment_note . '
                                <label class="form-label fw-semibold mt-2" for="cancellation_reason">' . lang('Cancellation reason') . ' <span class="text-muted fw-normal">(' . lang('optional') . ')</span></label>
                                <textarea class="form-control" id="cancellation_reason" name="cancellation_reason" rows="2" maxlength="500"></textarea>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">' . lang('Close') . '</button>
                                <button type="submit" name="submit_cancel_order" value="cancel" class="btn btn-danger">' . lang('Cancel Order') . '</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>';
    }


    // Build Parasut action buttons if API integration is enabled and credentials are set.
    $output_parasut_buttons      = '';
    $output_parasut_forms        = '';
    $output_parasut_status_rows  = '';
    if (
        defined('ENABLE_PARASUT') && ENABLE_PARASUT
        && defined('PARASUT_COMPANY_ID') && PARASUT_COMPANY_ID !== ''
        && defined('PARASUT_CLIENT_ID') && PARASUT_CLIENT_ID !== ''
    ) {
        $parasut_order_url = OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_order.php?id=' . (int)$_GET['id'];

        // Determine shippable vs non-shippable line composition.
        // Only products with inventory tracking can appear in a Paraşüt e-irsaliye.
        $shippable_count     = 0;
        $non_shippable_count = 0;
        $_oi_q = "SELECT COALESCE(products.inventory, 0) AS inv
                  FROM order_items
                  LEFT JOIN products ON order_items.product_id = products.id
                  WHERE order_items.order_id = '" . e((int)$_GET['id']) . "'";
        $_oi_r = mysqli_query(db::$con, $_oi_q);
        while ($_oi_r && ($_oi_row = mysqli_fetch_assoc($_oi_r))) {
            if (!empty($_oi_row['inv'])) { $shippable_count++; } else { $non_shippable_count++; }
        }
        $has_shippable = ($shippable_count > 0);
        $is_mixed      = ($shippable_count > 0 && $non_shippable_count > 0);

        // Show invoice status badge.
        $output_parasut_invoice_badge  = '';
        $output_parasut_shipment_badge = '';
        if ($parasut_invoice_id !== '') {
            $output_parasut_invoice_badge = ' <span class="badge bg-success ms-1" title="' . lang('Parasut ID') . ': ' . h($parasut_invoice_id) . '">' . lang('Sent') . '</span>';
        }
        if ($parasut_shipment_id !== '') {
            $output_parasut_shipment_badge = ' <span class="badge bg-success ms-1" title="' . lang('Parasut ID') . ': ' . h($parasut_shipment_id) . '">' . lang('Sent') . '</span>';
        }

        // Status rows shown inside the Order Information card.
        $invoice_row_badge  = $parasut_invoice_id !== ''
            ? '<span class="badge bg-success">' . lang('Sent') . '</span>'
              . ' <small class="text-muted">' . lang('Parasut ID') . ': ' . h($parasut_invoice_id) . '</small>'
            : '<span class="badge bg-secondary">' . lang('Not Sent') . '</span>';
        $shipment_row_badge = $parasut_shipment_id !== ''
            ? '<span class="badge bg-success">' . lang('Sent') . '</span>'
              . ' <small class="text-muted">' . lang('Parasut ID') . ': ' . h($parasut_shipment_id) . '</small>'
            : ($has_shippable
                ? '<span class="badge bg-secondary">' . lang('Not Sent') . '</span>'
                : '<span class="badge bg-light text-muted">' . lang('No shippable items') . '</span>');

        $output_parasut_status_rows = '
                                    <hr/>
                                    <div class="row align-items-center">
                                        <span class="col text-muted small">' . lang('E-Invoice') . ' / ' . lang('E-Archive') . ':</span>
                                        <span class="col text-end">' . $invoice_row_badge . '</span>
                                    </div>
                                    <div class="row align-items-center mt-1">
                                        <span class="col text-muted small">' . lang('E-Shipment') . ':</span>
                                        <span class="col text-end">' . $shipment_row_badge . '</span>
                                    </div>';

        // Inline mini-forms that post back to this page with a parasut_action field.
        $output_parasut_forms = '
        <form method="post" id="parasut-e-invoice-form" class="d-none">
            ' . get_token_field() . '
            <input type="hidden" name="id" value="' . (int)$_GET['id'] . '" />
            <input type="hidden" name="parasut_action" value="e_invoice" />
        </form>
        <form method="post" id="parasut-e-archive-form" class="d-none">
            ' . get_token_field() . '
            <input type="hidden" name="id" value="' . (int)$_GET['id'] . '" />
            <input type="hidden" name="parasut_action" value="e_archive" />
        </form>
        ';

        // E-irsaliye form is only rendered when the order has at least one shippable (stock-tracked) item.
        if ($has_shippable) {
            $output_parasut_forms .= '<form method="post" id="parasut-e-irsaliye-form" class="d-none">
            ' . get_token_field() . '
            <input type="hidden" name="id" value="' . (int)$_GET['id'] . '" />
            <input type="hidden" name="parasut_action" value="e_irsaliye" />
        </form>';
        }

        // Escape single quotes in translated strings so they are safe inside JS confirm('...').
        $js_confirm_invoice  = str_replace("'", "\\'", lang('This will create an e-invoice in Parasut for this order. Continue?'));
        $js_confirm_archive  = str_replace("'", "\\'", lang('This will create an e-archive invoice in Parasut for this order. Continue?'));
        $js_confirm_shipment = str_replace("'", "\\'", lang('This will create an e-shipment document in Parasut for this order. Continue?'));
        if ($is_mixed) {
            $js_confirm_shipment = str_replace("'", "\\'", lang('This order contains both shippable and non-shippable items. Only shippable (stock-tracked) items will be included in the e-shipment document.')) . '\n\n' . $js_confirm_shipment;
        }

        // Build the e-irsaliye button separately so the main button group stays simple.
        $output_parasut_shipment_button = '';
        if ($has_shippable) {
            $shipment_btn_class = $parasut_shipment_id ? 'link-success' : ($is_mixed ? 'link-warning' : 'link-secondary');
            $shipment_btn_title = $is_mixed ? h(lang('E-Shipment (only shippable items will be included)')) : 'Paraşüt E-İrsaliye';
            $shipment_mixed_icon = $is_mixed ? ' <i class="bi bi-exclamation-triangle-fill text-warning small"></i>' : '';
            $output_parasut_shipment_button = '
            <button type="button"
                class="btn btn-link py-0 mb-2 ' . $shipment_btn_class . '"
                onclick="pgConfirm({title:\'Paraşüt E-İrsaliye\', message:\'' . $js_confirm_shipment . '\', confirmText:\'' . lang('Continue') . '\', cancelText:\'' . lang('Cancel') . '\', variant:\'primary\'}).then(function(ok){if(ok) document.getElementById(\'parasut-e-irsaliye-form\').submit();}); return false;"
                title="' . $shipment_btn_title . '">
                <i class="bi bi-truck me-1"></i>' . lang('E-Shipment') . $shipment_mixed_icon . $output_parasut_shipment_badge . '
            </button>';
        }

        $output_parasut_buttons = '
        <div class="btn-group btn-group-sm flex-wrap">
            <button type="button"
                class="btn btn-link py-0 mb-2 ' . ($parasut_invoice_id ? 'link-success' : 'link-secondary') . '"
                onclick="pgConfirm({title:\'Paraşüt E-Fatura\', message:\'' . $js_confirm_invoice . '\', confirmText:\'' . lang('Continue') . '\', cancelText:\'' . lang('Cancel') . '\', variant:\'primary\'}).then(function(ok){if(ok) document.getElementById(\'parasut-e-invoice-form\').submit();}); return false;"
                title="Paraşüt E-Fatura">
                <i class="bi bi-receipt me-1"></i>' . lang('E-Invoice') . $output_parasut_invoice_badge . '
            </button>
            <button type="button"
                class="btn btn-link py-0 mb-2 ' . ($parasut_invoice_id ? 'link-success' : 'link-secondary') . '"
                onclick="pgConfirm({title:\'Paraşüt E-Arşiv\', message:\'' . $js_confirm_archive . '\', confirmText:\'' . lang('Continue') . '\', cancelText:\'' . lang('Cancel') . '\', variant:\'primary\'}).then(function(ok){if(ok) document.getElementById(\'parasut-e-archive-form\').submit();}); return false;"
                title="Paraşüt E-Arşiv">
                <i class="bi bi-archive me-1"></i>' . lang('E-Archive') . $output_parasut_invoice_badge . '
            </button>
            ' . $output_parasut_shipment_button . '
        </div>';
    }

    echo

    pg_page_shell(
        array(
            'title'=> '#' . $order_number . ' - ' . lang('Order'),
            'extra classes'=>'store view_order',
            'icon'=>'store',
            'heading'=>lang('View Order'),
            'cancel'=>array('enable'=>'true','url'=>'view_orders.php')
        ,
            'breadcrumb' => array(array('label' => lang('All Orders'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_orders.php'), array('label' => lang('View Order'))),
        )
    ) . get_date_picker_format() . '
        <script src="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/assets/Jquery/jquery-ui-timepicker-addon-1.2.1.min.js"></script>

                <div class="row">
            <div class="col-12">
                ' . $liveform->output_errors() . '
                ' . $liveform->get_warnings() . '
                ' . $liveform->output_notices() . '
                ' . $output_refund_banner . '
                <div class="row mb-2  flex-wrap d-print-none">
                    <div class="col-12 col-sm-12 text-center text-md-start">
<h2 class="d-inline-block text-break header-content-for-add-page" data-bs-content="' . lang('View the details of this order and update shipping information.') . '" title="' . lang('View Order') . '">[#' . $order_number . ']</h2>
                        <nav id="button_bar" class="navigation " aria-label="Button Bar">
                            ' . $output_gateway_buttons . '
                            ' . $output_cancel_button . '
                            ' . $output_parasut_buttons . '
                            <div class=" btn-group btn-group-sm flex-wrap">
                                <button type="button" class="btn btn-link link-secondary py-0 mb-2 position-relative" title="' . lang('Print Order') . '" onclick="window.open(\'print_order.php?id=' . $_GET['id'] . '\', \'\', \'width=794, height=1123, resizable=1, scrollbars=1\'); return false;""><span class="material-icons me-1">print</span>' . lang('Print') . '</a>

                            </div>
                        </nav>
                    </div>
                </div>
                <form method="post">
                    ' . get_token_field() . '
                    ' . $liveform->output_field(array('type'=>'hidden', 'name'=>'id', 'value'=>$_GET['id'])) . '
                    ' . $liveform->output_field(array('type'=>'hidden', 'name'=>'send_to', 'value'=>($_GET['send_to'] ?? ''))) . '

                    <div class="row g-4 ">
                        <div class="col-12 col-md-6 col-lg-4">
                            <div class="card">
                                <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
                                    ' . lang('Order Information') . '
                                </div>
                                <div class="card-body">
                                    <div class="row" >
                                        <span class="translateable col text-muted">' . lang('Type') . ':</span>
                                        <span class="translateable col text-end">' . lang(h(ucwords($order_type))) . '</span>
                                    </div>
                                    <div class="row" >
                                        <span class="translateable col text-muted">' . lang('Status') . ':</span>
                                        <span class="translateable col text-end">' . lang(h(ucwords($status))) . '</span>
                                    </div>
                                    <div class="row" >
                                        <span class="translateable col text-muted">' . lang('Order Number') . ':</span>
                                        <span class="col text-end" style="font-size: 120%;font-weight:500">' . $order_number . '</span>
                                    </div>
                                    <div class="row" >
                                        <span class="translateable col text-muted">' . lang('Order Date') . ':</span>
                                        <span class="col text-end">' . $order_date . '</span>
                                    </div>
                                    <hr/>
                                    <div class="row" >
                                        <span class="translateable col text-muted">' . lang('Subtotal') . ':</span>
                                        <span class="col text-end" style="font-size: 110%">' . prepare_amount($subtotal) . '</span>
                                    </div>
                                    <div class="row" >
                                        <span class="translateable col text-muted">' . lang('Discount') . ':</span>
                                        <span class="col text-end">-' . BASE_CURRENCY_SYMBOL . $discount . '</span>
                                    </div>
                                    <div class="row" >
                                        <span class="translateable col text-muted">' . lang('Tax') . ':</span>
                                        <span class="col text-end">' . BASE_CURRENCY_SYMBOL . $tax . '</span>
                                    </div>
                                    <div class="row" >
                                        <span class="translateable col text-muted">' . lang('Shipping') . ':</span>
                                        <span class="col text-end">' . BASE_CURRENCY_SYMBOL . $shipping . '</span>
                                    </div>
                                    ' . $output_gift_card_discount_row . '
                                    ' . $output_surcharge_row . '
                                    ' . $output_number_of_installment_row . '
                                    <div class="row" >
                                        <span class="translateable col text-muted">' . lang('Total') . ':</span>
                                        <span class="col text-end"><strong style="font-size: 120%">' . prepare_amount($total) . '</strong></span>
                                    </div>
                                    ' . ($refunded_amount_cents > 0 ?
                                        '<div class="row text-danger">
                                            <span class="translateable col text-muted">' . lang('Total Refunded') . ':</span>
                                            <span class="col text-end">-' . BASE_CURRENCY_SYMBOL . number_format($refunded_amount_cents / 100, 2, '.', ',') . '</span>
                                        </div>
                                        <div class="row">
                                            <span class="translateable col text-muted fw-semibold">' . lang('Net Total') . ':</span>
                                            <span class="col text-end"><strong style="font-size: 120%">' . prepare_amount(number_format(($total_cents - $refunded_amount_cents) / 100, 2, '.', ',')) . '</strong></span>
                                        </div>'
                                    : '') . '
                                    <hr/>
                                    <div class="row" >
                                        <span class="translateable col text-muted">' . lang('Special Offer Code') . ':</span>
                                        <span class="col text-end">' . $special_offer_code . '</span>
                                    </div>
                                    <div class="row" >
                                        <span class="translateable col text-muted">' . lang('Reference Code') . ':</span>
                                        <span class="col text-end">' . $reference_code . '</span>
                                    </div>
                                    ' . $output_currency_code_row . '
                                    ' . $output_parasut_status_rows . '
                                </div>
                            </div>
                            ' . $output_payment_information . '
                            ' . $source . '
                            ' . $output_applied_gift_cards . '
                            ' . $output_affiliate . '
                        </div>
                        <div class="col-12 col-md-6 col-lg-4">
                            <div class="card">
                                <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
                                    ' . lang('Billing Information') . '
                                </div>
                                <div class="card-body">
                                    <div class="row" >
                                        <span class="translateable col text-muted">' . lang('Custom Field') . ' #1:</span>
                                        <span class="col text-end">' . h($custom_field_1) . '</span>
                                    </div>
                                    <div class="row" >
                                        <span class="translateable col text-muted">' . lang('Custom Field') . ' #2:</span>
                                        <span class="col text-end">' . h($custom_field_2) . '</span>
                                    </div>
                                    <div class="row" >
                                        <span class="translateable col text-muted">' . lang('Salutation') . ':</span>
                                        <span class="col text-end">' . h($billing_salutation) . '</span>
                                    </div>
                                    <div class="row" >
                                        <span class="translateable col text-muted">' . lang('First Name') . ':</span>
                                        <span class="col text-end">' . h($billing_first_name) . '</span>
                                    </div>
                                    <div class="row" >
                                        <span class="translateable col text-muted">' . lang('Last Name') . ':</span>
                                        <span class="col text-end">' . h($billing_last_name) . '</span>
                                    </div>
                                    <div class="row" >
                                        <span class="translateable col text-muted">' . lang('Company') . ':</span>
                                        <span class="col text-end">' . h($billing_company) . '</span>
                                    </div>
                                    <div class="row" >
                                        <span class="translateable col-auto text-muted">' . lang('Address') . ' 1:</span>
                                        <span class="col offset-1" style="font-size:110%;font-weight:500">' . h($billing_address_1) . '</span>
                                    </div>
                                    <div class="row" >
                                        <span class="translateable col-auto text-muted">' . lang('Address') . ' 2:</span>
                                        <span class="col offset-1" style="font-size:110%;font-weight:500">' . h($billing_address_2) . '</span>
                                    </div>
                                    <div class="row" >
                                        <span class="translateable col text-muted">' . lang('City') . ':</span>
                                        <span class="col text-end">' . h($billing_city) . '</span>
                                    </div>
                                    <div class="row" >
                                        <span class="translateable col text-muted">' . lang('State') . ':</span>
                                        <span class="col text-end">' . h($billing_state) . '</span>
                                    </div>
                                    <div class="row" >
                                        <span class="translateable col text-muted">' . lang('Country') . ':</span>
                                        <span class="col text-end">' . h($billing_country) . '</span>
                                    </div>
                                    <div class="row" >
                                        <span class="translateable col text-muted">' . lang('Zip Code') . ':</span>
                                        <span class="col text-end">' . h($billing_zip_code) . '</span>
                                    </div>
                                    <div class="row" >
                                        <span class="translateable col text-muted">' . lang('Address Verified') . ':</span>
                                        <span class="col text-end">' . $billing_address_verified . '</span>
                                    </div>
                                    <div class="row" >
                                        <span class="translateable col text-muted">' . lang('Phone') . ':</span>
                                        <span class="col text-end">' . h($billing_phone_number) . '</span>
                                    </div>
                                    ' . $fax . '
                                    <div class="row" >
                                        <span class="translateable col text-muted">' . lang('Email') . ':</span>
                                        <span class="col text-end">' . h($billing_email_address) . '</span>
                                    </div>
                                    <div class="row" >
                                        <span class="translateable col text-muted">' . lang('Opt-In') . ':</span>
                                        <span class="col text-end">' . $opt_in . '</span>
                                    </div>
                                    <div class="row" >
                                        <span class="translateable col text-muted">' . lang('PO Number') . ':</span>
                                        <span class="col text-end">' . h($po_number) . '</span>
                                    </div>
                                    <div class="row" >
                                        <span class="translateable col text-muted">' . lang('Tax-Exempt') . ':</span>
                                        <span class="col text-end">' . $tax_exempt . '</span>
                                    </div>
                                    ' . ($contact_tax_number !== '' || $contact_tax_office !== '' ? '
                                    <hr/>
                                    <div class="row">
                                        <span class="translateable col text-muted">VKN / TCKN:</span>
                                        <span class="col text-end">' . h($contact_tax_number) . '</span>
                                    </div>
                                    <div class="row">
                                        <span class="translateable col text-muted">' . lang('Tax Office') . ':</span>
                                        <span class="col text-end">' . h($contact_tax_office) . '</span>
                                    </div>' : '') . '
                                </div>
                            </div>

                            <div class="card mt-3">
                                <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold d-flex">
                                    ' . lang('Customer Information') . ' <span class="ms-auto">' . $output_contact_image . '</span>
                                </div>
                                <div class="card-body">
                                    ' . $output_user_row . '
                                    ' . $output_contact_row . '
                                    ' . $output_member_id_row . '
                                    ' . $output_ip_address_row . '
                                </div>
                            </div> 
                            ' . $output_custom_billing_information . '
                        
                        </div>
                        ' . $output_shipping_details . '
                    </div>
                    <div class=" list-group order-list-group my-4">
                        ' . $output_order_details . ' 
                    </div>
                    <nav class="buttons navigation text-center position-sticky mb-4" style="bottom:.5rem;" aria-label="data edit buttons ">
                        <div class="container">
                            <div class=" btn-group flex-wrap justify-content-center d-print-none">
                                ' . $output_save_button . '
                                ' . $output_restore_button . '
                                <button type="submit" name="submit_delete" value="Delete" class="btn my-1  btn-danger " data-loading-content="' . lang(array('string'=>'Deleting') ) . '" data-confirm-content="' . lang(array('string'=>'WARNING: This {var:1} will be permanently deleted.','vars'=>array(lang('order')))) . '"><span class="material-icons me-2">delete</span><span class="btn-text" >' . lang(array('string'=>'Delete') ) . '</span></button>
                            </div>
                        </div>
                    </nav>
                </form>
            </div>
        </div>
    </main>' .
        $output_iyzico_modals .
        $output_cancel_modal .
        $output_refund_modal .
        $output_parasut_forms .
        output_footer();
        $liveform->remove_form();

// else the form has been submitted
} else {
    
    validate_token_field();
    
    $liveform->add_fields_to_session();
    
    // if the operator submitted the cancel modal
    if ($liveform->get_field_value('submit_cancel_order') == 'cancel') {

        $order_id = (int)$liveform->get('id');

        // One shared implementation for every cancel entry point (customer
        // self-service, admin per-row, admin bulk, this screen). Passing
        // attempt_refund=true forces the iyzico void the operator just
        // confirmed in the modal, independent of the site-wide
        // ECOMMERCE_ORDER_CANCEL_AUTO_REFUND default.
        $response = process_order_cancellation(
            $order_id,
            (string) $liveform->get_field_value('cancellation_reason'),
            true,                                   // admin context (page is admin-gated)
            (int) (isset($user['id']) ? $user['id'] : 0),   // validate_user() key is "id", not "user_id"
            true                                    // attempt the gateway void
        );

        if ($response['status'] == 'already') {
            $liveform->mark_error('_error', lang('Order is already cancelled.'));
            go(URL_SCHEME . HOSTNAME . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_order.php?id=' . $order_id . '&send_to=' . urlencode($liveform->get_field_value('send_to')));
        }

        if ($response['status'] == 'shipped') {
            $liveform->mark_error('_error', lang('This order has already shipped and can no longer be cancelled.'));
            go(URL_SCHEME . HOSTNAME . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_order.php?id=' . $order_id . '&send_to=' . urlencode($liveform->get_field_value('send_to')));
        }

        if ($response['status'] != 'success') {
            $liveform->mark_error('_error', h($response['message']));
            go(URL_SCHEME . HOSTNAME . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_order.php?id=' . $order_id . '&send_to=' . urlencode($liveform->get_field_value('send_to')));
        }

        $liveform->add_notice(lang('The order has been cancelled.'));

        // Surface the refund outcome so a failed void is never silent.
        if ($response['refund_status'] == 'refunded') {
            $liveform->add_notice(lang('The payment has been refunded.'));
        } elseif ($response['refund_status'] == 'manual_required' || $response['refund_status'] == 'failed') {
            $liveform->add_notice(lang('The payment was NOT refunded automatically. Process the refund in your payment provider dashboard.'));
        }

        go(URL_SCHEME . HOSTNAME . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_order.php?id=' . $order_id . '&send_to=' . urlencode($liveform->get_field_value('send_to')));

    // if the user submitted the iyzico refund modal
    } elseif ($liveform->get_field_value('submit_iyzico_refund') == 'refund') {

        $order_id = (int)$liveform->get('id');
        $refund_amount = (float)$liveform->get_field_value('refund_amount');

        // Load order to validate against remaining refundable amount.
        $order_row = db_item(
            "SELECT total, refunded_amount, transaction_id, payment_method, status
             FROM orders WHERE id = '" . e($order_id) . "'"
        );

        if (!$order_row) {
            output_error(lang('Order not found.'));
        }

        if ($order_row['status'] == 'cancelled') {
            $liveform->mark_error('_error',lang('Order is already cancelled.'));
            go(URL_SCHEME . HOSTNAME . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_order.php?id=' . $order_id . '&send_to=' . urlencode($liveform->get_field_value('send_to')));
        }

        $remaining_cents = (int)$order_row['total'] - (int)($order_row['refunded_amount'] ?? 0);
        $refund_cents = (int)round($refund_amount * 100);

        if ($refund_cents <= 0) {
            $liveform->mark_error('_error',lang('The refund amount must be greater than 0.'));
            go(URL_SCHEME . HOSTNAME . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_order.php?id=' . $order_id . '&send_to=' . urlencode($liveform->get_field_value('send_to')));
        }

        if ($refund_cents > $remaining_cents) {
            $liveform->mark_error('_error',lang('The refund amount exceeds the remaining refundable amount.'));
            go(URL_SCHEME . HOSTNAME . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_order.php?id=' . $order_id . '&send_to=' . urlencode($liveform->get_field_value('send_to')));
        }

        // Call iyzico AmountBaseRefund API.
        require_once(dirname(__FILE__) . '/assets/iyzipay-php/IyzipayBootstrap.php');
        IyzipayBootstrap::init();

        $gateway_host = (ECOMMERCE_PAYMENT_GATEWAY_MODE == 'test')
            ? 'https://sandbox-api.iyzipay.com'
            : 'https://api.iyzipay.com';

        $options = new \Iyzipay\Options();
        $options->setApiKey(ECOMMERCE_IYZIPAY_API_KEY);
        $options->setSecretKey(ECOMMERCE_IYZIPAY_SECRET_KEY);
        $options->setBaseUrl($gateway_host);

        $refund_request = new \Iyzipay\Request\AmountBaseRefundRequest();
        $refund_request->setLocale(\Iyzipay\Model\Locale::TR);
        $refund_request->setConversationId((string)rand(100000000, 999999999));
        $refund_request->setPaymentId($order_row['transaction_id']);
        $refund_request->setPrice($refund_amount);
        $refund_request->setIp($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');

        $refund_result = \Iyzipay\Model\AmountBaseRefund::create($refund_request, $options);

        if ($refund_result->getStatus() != 'success') {
            $liveform->mark_error('_error',h($refund_result->getErrorMessage()));
            go(URL_SCHEME . HOSTNAME . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_order.php?id=' . $order_id . '&send_to=' . urlencode($liveform->get_field_value('send_to')));
        }

        // Record the refund.
        $new_refunded_total = (int)($order_row['refunded_amount'] ?? 0) + $refund_cents;
        db("UPDATE orders SET refunded_amount = '" . e($new_refunded_total) . "' WHERE id = '" . e($order_id) . "'");
        db("INSERT INTO order_refunds (order_id, amount_cents, refund_type, transaction_id, created_at)
            VALUES ('" . e($order_id) . "', '" . e($refund_cents) . "', 'refund', '" . e($refund_result->getPaymentId()) . "', NOW())");

        // If full amount has been refunded, mark order as cancelled.
        if ($new_refunded_total >= (int)$order_row['total']) {
            db("UPDATE orders SET status = 'cancelled' WHERE id = '" . e($order_id) . "'");
        }

        log_activity('Refunded ' . $refund_amount . ' ' . BASE_CURRENCY_CODE . ' for order #' . $order_id . '.');

        $liveform->add_notice(lang('The refund has been processed.'));
        go(URL_SCHEME . HOSTNAME . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_order.php?id=' . $order_id . '&send_to=' . urlencode($liveform->get_field_value('send_to')));

    // if the operator confirmed that they processed the refund themselves
    } elseif ($liveform->get_field_value('submit_mark_refunded') == 'refunded') {

        // The refund happens in the payment provider's dashboard, outside this
        // software. Nothing here can observe it, so the operator says so and
        // this records the statement — that is the honest limit of what the
        // panel can know, and it is what lets the pending-refund queue drain.
        $order_id = (int) $liveform->get('id');

        if (!db_item("SHOW COLUMNS FROM orders LIKE 'refund_status'")) {
            output_error(lang('The refund columns do not exist yet. Please run the software upgrade to create them.'));
        }

        $refund_row = db_item("SELECT refund_status FROM orders WHERE id = '" . e($order_id) . "'");

        if (!$refund_row) {
            output_error(lang('Order not found.'));
        }

        // Only from a state that actually owes money. Refuses to overwrite an
        // order already marked refunded, and refuses to invent a refund on an
        // order that never had one pending — either would corrupt the record
        // this button exists to keep.
        if (!in_array((string) $refund_row['refund_status'], array('manual_required', 'failed', 'pending'), true)) {
            $liveform->mark_error('_error', lang('This order is not waiting for a refund.'));
            go(URL_SCHEME . HOSTNAME . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_order.php?id=' . $order_id . '&send_to=' . urlencode($liveform->get_field_value('send_to')));
        }

        $refund_reference = trim(mb_substr((string) $liveform->get_field_value('refund_reference'), 0, 255));

        db(
            "UPDATE orders SET
                refund_status    = 'refunded',
                refunded_at      = '" . time() . "',
                refund_reference = '" . escape($refund_reference) . "'
             WHERE id = '" . e($order_id) . "'");

        // Says "marked", not "refunded": the log is an audit trail and must
        // not claim the software saw money move. log_activity() attaches the
        // signed-in username on its own.
        log_activity(
            'Refund marked as processed by operator for order #' . $order_id
            . ($refund_reference !== '' ? '. Reference: ' . $refund_reference : ''));

        $liveform->add_notice(lang('The refund has been marked as processed.'));
        go(URL_SCHEME . HOSTNAME . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_order.php?id=' . $order_id . '&send_to=' . urlencode($liveform->get_field_value('send_to')));

    // if the user selected to restore a cancelled order back to complete
    } elseif ($liveform->get_field_value('submit_restore_order') == 'restore') {

        $order_id = (int)$liveform->get('id');

        $order_status = db_value("SELECT status FROM orders WHERE id = '" . e($order_id) . "'");

        if ($order_status != 'cancelled') {
            $liveform->mark_error('_error', lang('Only cancelled orders can be restored.'));
            go(URL_SCHEME . HOSTNAME . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_order.php?id=' . $order_id . '&send_to=' . urlencode($liveform->get_field_value('send_to')));
        }

        // Clear the cancellation bookkeeping too. Leaving cancelled_at set
        // would keep an "Order Cancelled" entry on the order_view timeline
        // of an order that is complete again, and would keep the refund
        // banner up. Columns are 2026.1.26 / 2026.1.27 additions, so both
        // groups are probed before use.
        db("UPDATE orders SET status = 'complete' WHERE id = '" . e($order_id) . "'");

        if (db_item("SHOW COLUMNS FROM orders LIKE 'cancelled_at'")) {
            db("UPDATE orders SET cancelled_at = NULL, cancelled_by = 0, cancellation_reason = ''
                WHERE id = '" . e($order_id) . "'");
        }
        if (db_item("SHOW COLUMNS FROM orders LIKE 'refund_status'")) {
            db("UPDATE orders SET refund_status = '', refunded_at = NULL, refund_reference = ''
                WHERE id = '" . e($order_id) . "' AND refund_status != 'refunded'");
        }

        // Re-enable gift cards that were expired when this order was cancelled.
        // We restore expiration to 0000-00-00 (no expiration) since the original
        // value was overwritten during cancellation.
        db("UPDATE gift_cards
            SET expiration_date = '0000-00-00'
            WHERE order_id = '" . e($order_id) . "'
              AND expiration_date != '0000-00-00'
              AND expiration_date <= '" . date('Y-m-d') . "'");

        log_activity('Restored cancelled order #' . $order_id . ' to complete.');

        $liveform->add_notice(lang('The order has been restored to complete.'));
        go(URL_SCHEME . HOSTNAME . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_order.php?id=' . $order_id . '&send_to=' . urlencode($liveform->get_field_value('send_to')));

    // if the user selected to delete this order, then delete it
    } elseif ($liveform->get_field_value('submit_delete') == 'Delete') {

        $order['id'] = $liveform->get('id');

        require_once(dirname(__FILE__) . '/delete_order.php');

        $response = delete_order(array('order' => $order));

        if ($response['status'] == 'error') {
            output_error(h($response['message']));
        }
        
        // if the user is going to be sent to the view order screen, then prepare notice
        if (mb_substr($liveform->get_field_value('send_to'), -15) == 'view_orders.php') {
            $liveform_view_orders = new liveform('view_orders');
            $liveform_view_orders->add_notice('The order has been deleted.');
        }

        header('Location: ' . URL_SCHEME . HOSTNAME . $liveform->get_field_value('send_to'));
        
    // else the user selected to save the order
    } else {

        // Prepare all info in $order array that needs to be updated, so we can pass that to the
        // update_order() function.

        $order = array();
        $order['id'] = $liveform->get('id');

        $order['recipients'] = db_items(
            "SELECT id
            FROM ship_tos
            WHERE order_id = '" . e($liveform->get('id')) . "'");

        // If there are recipients in this order, then loop through them in order to prepare data.
        if (is_array($order['recipients'])) {

            foreach ($order['recipients'] as $key => $recipient) {

                $recipient['ship_date'] = prepare_form_data_for_input($liveform->get('ship_to_id_' . $recipient['id'] . '_ship_date'), 'date');

                $recipient['delivery_date'] = prepare_form_data_for_input($liveform->get('ship_to_id_' . $recipient['id'] . '_delivery_date'), 'date');

                $recipient['tracking_numbers'] = array();

                if ($liveform->get('ship_to_id_' . $recipient['id'] . '_tracking_numbers')) {

                    $tracking_numbers = $liveform->get('ship_to_id_' . $recipient['id'] . '_tracking_numbers');
                    $tracking_numbers = explode(',',$tracking_numbers);
                    
                    foreach ($tracking_numbers as $tracking_number) {
                        if ($tracking_number) {
                            $recipient['tracking_numbers'][] = $tracking_number;
                        }
                    }
                }

                $recipient['items'] = db_items(
                    "SELECT id
                    FROM order_items
                    WHERE ship_to_id = '" . e($recipient['id']) . "'");

                // Loop through order items in order to prepare shipped quantity.
                foreach ($recipient['items'] as $item_key => $item) {

                    $item['shipped_quantity'] =
                        $liveform->get('order_item_id_' . $item['id'] . '_shipped_quantity');

                    $recipient['items'][$item_key] = $item;
                }

                $order['recipients'][$key] = $recipient;
            }
        }

        require_once(dirname(__FILE__) . '/update_order.php');

        $response = update_order(array('order' => $order));

        if ($response['status'] == 'error') {
            output_error(h($response['message']));
        }
        
        // if the user is going to be sent to the view order screen, then prepare notice
        if (mb_substr($liveform->get_field_value('send_to'), -15) == 'view_orders.php') {
            $liveform_view_orders = new liveform('view_orders');
            $liveform_view_orders->add_notice('The order has been saved.');
        }
        
        header('Location: ' . URL_SCHEME . HOSTNAME . $liveform->get_field_value('send_to'));
    }
    
    $liveform->remove_form();
    exit();
}