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
            contacts.image AS contact_image
         FROM orders
         LEFT JOIN user ON orders.user_id = user.user_id
         LEFT JOIN contacts ON orders.contact_id = contacts.id
         WHERE orders.id = '" . escape($_GET['id']) . "'";
$result = mysqli_query(db::$con, $query) or output_error('Query failed.');
$row = mysqli_fetch_assoc($result);
$status = $row['status'];
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
$source = '';
$output_contact_image = '';

// Only set when a contact image is found below, but read in both branches.
$accent_color = '';

// These cells are only filled in for some order/product types, but they are always printed
// in the tables further down.
$output_shipped_quantity_field = '';
$output_custom_field_1_cell = '';
$output_custom_field_2_cell = '';
$output_custom_field_3_cell = '';
$output_custom_field_4_cell = '';

if($contact_file_id == 0){
    if($contact_image){
        $accent_color = get_dominant_area_color(FILE_DIRECTORY_PATH . '/' . $contact_image);
        $output_contact_image = '
        <div class="col-auto overflow-hidden d-print-none rounded-circle bg-body-tertiary" style="width:50px;height:50px;background-color:' . h($accent_color) . ';">
            <img class="lazy object-fit-contain w-100 h-100"  src="' . OUTPUT_PATH . SOFTWARE_DIRECTORY . '/assets/images/loading.gif" data-src="' . PATH . h($row['image']) . '" />
        </div>';
    }else{
        $output_contact_image = '
        <div class="col-auto overflow-hidden d-print-none rounded-circle bg-body-tertiary" style="width:50px;height:50px;background-color:' . h($accent_color) . ';">
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
    <div class="col-aut overflow-hidden d-print-none rounded-circle bg-body-tertiary" style="width:50px;height:50px;background-color:' . h($accent_color) . ';">
        <img class="lazy  object-fit-contain w-100 h-100"  src="' . OUTPUT_PATH . SOFTWARE_DIRECTORY . '/assets/images/loading.gif" data-src="' . PATH . h($file_name) . '" />
    </div>';
}

if ($tracking_code or $http_referer or $referral_source_code or $utm_source) {
    $source .=
        '<div class="card mt-3 bg-transparent border-0">
          
            <div class="card-body"><hr/>';
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
            <div class="card mt-3 border-0 bg-transparent">
                   <div class="card-body">
                        <hr/>
                       <div class="row" >
                           <span class="translateable col text-muted">' . lang('Payment Method') . ':</span>
                           <span class="col translateable text-end">' . lang($payment_method) . '</span>
                       </div>
                       ' . $output_credit_debit_card_information . '
                       ' . $transaction_id_row . '
                       ' . $authorization_code_row . '
                       ' . ($refunded_amount_cents > 0 ?
                           '<div class="row" style="color:#dc3545">
                               <span class="translateable col text-muted">' . lang('Total Refunded') . ':</span>
                               <span class="col text-end">-' . BASE_CURRENCY_SYMBOL . number_format($refunded_amount_cents / 100, 2, '.', ',') . '</span>
                           </div>' : '') . '
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
            <div class="card-body">
                <hr/>
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
        '<div class="card mt-3 border-0 bg-transparent">
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
$output_order_details = '';
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
            $output_ship_date = '';
            $output_delivery_date = '';
            $output_shipping_tracking_numbers = '';
            $output_shipping_tracking_numbers_field = '';
            
            // If the order is complete, then output ship date and shipping tracking numbers.
            if ($status != 'incomplete') {
                // If the form has not been submitted yet, and the ship date is not blank,
                // then prefill ship date field.

                $output_ship_date = prepare_form_data_for_output($ship_tos[$key]['ship_date'], 'date');
                // If the form has not been submitted yet, and the delivery date is not blank,
                // then prefill delivery date field.
                if (
                    !$liveform->field_in_session('id')
                    and $ship_tos[$key]['delivery_date'] != '0000-00-00'
                ) {
                    $output_delivery_date = prepare_form_data_for_output($ship_tos[$key]['delivery_date'], 'date');
                }
                

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
            }
            $output_shipping_tracking_numbers_field = $shipping_tracking_numbers_for_field;  
                
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
                '<div class="col-4">
                    <div class="card border-0 bg-transparent">
                        <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
                            ' . lang('Shipping Information') . '
                            
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
                                        
                                            <span class="col text-muted">' . lang('Ship Date') . ' - ' . lang('Delivery Date') . ':</span>
                                            <span class="col-auto text-end">' . $output_ship_date . ' - ' . $output_delivery_date . '</span>
                                    </div>
                                </div>
                                <div class="col-12 pt-4" id="trackin_number_from_view_orders">
                                    <div class="row">
                                        <span class="col text-muted">' . lang('Shipping Method') . ':</span>
                                        <span class="col-auto badge">' . $shipping_method . '</span>
                                    </div>
                                    <div class="row">
                                        <span class="col text-muted">' . lang('Tracking Numbers') . ':</span>
                                        <span class="col-auto text-end">' . $output_shipping_tracking_numbers . $output_shipping_tracking_numbers_field . '</span>
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
        
        $product_list_style = ' ';
        if ($ship_tos[$key]['ship_to_id'] > 0) {
        } else {
            $product_list_style = ' style="border: 0"';
        }

        $output_custom_field_1_heading = '';

        // If the first custom product field is active, then output heading for it.
        if (ECOMMERCE_CUSTOM_PRODUCT_FIELD_1_LABEL != '') {
            $output_custom_field_1_heading = '<th>' . h(ECOMMERCE_CUSTOM_PRODUCT_FIELD_1_LABEL) . '</th>';
        }

        $output_custom_field_2_heading = '';

        // If the second custom product field is active, then output heading for it.
        if (ECOMMERCE_CUSTOM_PRODUCT_FIELD_2_LABEL != '') {
            $output_custom_field_2_heading = '<th>' . h(ECOMMERCE_CUSTOM_PRODUCT_FIELD_2_LABEL) . '</th>';
        }

        $output_custom_field_3_heading = '';

        // If the third custom product field is active, then output heading for it.
        if (ECOMMERCE_CUSTOM_PRODUCT_FIELD_3_LABEL != '') {
            $output_custom_field_3_heading = '<th>' . h(ECOMMERCE_CUSTOM_PRODUCT_FIELD_3_LABEL) . '</th>';
        }

        $output_custom_field_4_heading = '';

        // If the fourth custom product field is active, then output heading for it.
        if (ECOMMERCE_CUSTOM_PRODUCT_FIELD_4_LABEL != '') {
            $output_custom_field_4_heading = '<th>' . h(ECOMMERCE_CUSTOM_PRODUCT_FIELD_4_LABEL) . '</th>';
        }
        
        $output_order_details .=
            '<tr>
                <td colspan="' . $output_colspan . '"' . $product_list_style . '>
                    <table cellpadding="4" class="order_details table table-bordered w-auto">
                        <tr >
                            <th>' . lang('Product ID / SKU') . '</th>
                            <th>' . lang('Short Description') . '</th>
                            <th>' . lang('Qty') . '</th>
                            <th>' . lang('Shipped Qty') . '</th>
                            <th>' . lang('Price') . '</th>
                            <th>' . lang('Total') . '</th>
                            <th>' . lang('Payment Period') . '</th>
                            <th>' . lang('Number of Payments') . '</th>
                            <th>' . lang('Start Date') . '</th>
                            ' . $output_custom_field_1_heading . '
                            ' . $output_custom_field_2_heading . '
                            ' . $output_custom_field_3_heading . '
                            ' . $output_custom_field_4_heading . '
                        </tr>';
        
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
                $output_product_name = '<span class="cursor-pointer" onclick="document.location.href = \'edit_product.php?id=' . $product_id . '\'">' .  h($product_name) . '</span>';
            
            // else a product was not found, so do not include a link to product   
            } else {
                $output_product_name = h($product_name);
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
            
            $output_shipped_quantity = '';
            
            // if the order is complete, then show shipped quantity field
            if ($status != 'incomplete') {
                // if the form has not been submitted yet and show shipped quantity is enabled, then prefill shipped quantity field
                if (
                    ($liveform->field_in_session('id') == FALSE)
                    && ($show_shipped_quantity == 1)
                ) {
                   $output_shipped_quantity =  $shipped_quantity;
                }
               
                $output_shipped_quantity = ' 
                    <div>
                        <span class="h-50 text-center h4">' . $output_shipped_quantity . '</span>
                    </div>';
                
                
                 
            }
            
            $output_recurring_payment_period = '';
            $output_recurring_number_of_payments = '';
            $output_recurring_start_date = '';
            
            // if order item is a recurring order item, then prepare 
            if ($recurring_payment_period != '') {
                $output_recurring_payment_period = '
                    <div class="col-12 col-sm-4 col-md-auto">
                        <div class="p-2  h-100 rounded">
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
                        <div class="p-2  h-100 rounded">
                            <div class="h-50 text-center text-muted">' . lang('Number of Payments') . '</div>
                            <div class="h-50 text-center">' . $output_recurring_number_of_payments . '</div>
                        </div>
                    </div>';
                    
                $output_recurring_start_date = '
                    <div class="col-12 col-sm-4 col-md-auto">
                        <div class="p-2  h-100 rounded">
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
                        <div class="p-2  h-100 rounded">
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
                    <div class="p-2  h-100 rounded">
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
                    <div class="p-2  h-100 rounded">
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
                    <div class="p-2  h-100 rounded">
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
                            '<fieldset' . $output_top_margin . '>
                                ' . $output_legend . '
                                <div style="padding: 0.7em">
                                    <table cellpadding="4" class="order_details table table-bordered w-auto">
                                        <tr>
                                            <td>Code:</td>
                                            <td>' . $output_code . '</td>
                                        </tr>
                                        <tr>
                                            <td>Amount:</td>
                                            <td>' . prepare_amount($product_price) . '</td>
                                        </tr>
                                        <tr>
                                            <td>Recipient Email:</td>
                                            <td>' . h($order_item_gift_card['recipient_email_address']) . '</td>
                                        </tr>
                                        <tr>
                                            <td>From Name:</td>
                                            <td>' . h($order_item_gift_card['from_name']) . '</td>
                                        </tr>
                                        <tr>
                                            <td style="vertical-align: top">Message:</td>
                                            <td>' . nl2br(h($order_item_gift_card['message'])) . '</td>
                                        </tr>
                                        <tr>
                                            <td>Delivery Date:</td>
                                            <td>' . $output_delivery_date . '</td>
                                        </tr>
                                    </table>
                                </div>
                            </fieldset>';
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
                        '<fieldset' . $output_top_margin . '>
                            ' . $output_legend . '
                            <div style="padding: 0.7em">
                                <table cellpadding="4" class="order_details table table-bordered w-auto">
                                    ' . get_submitted_product_form_content_without_form_fields($order_item_id, $quantity_number, 'backend') . '
                                </table>
                            </div>
                        </fieldset>';
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
                        '<tr >
                            <td>&nbsp;</td>
                            <td colspan="' . $colspan . '">
                                <div style="padding: .7em">
                                    ' . $output_gift_cards . '
                                    ' . $output_forms . '
                                </div>
                            </td>
                        </tr>';
            }
            //shipable
            $output_order_details .=
                    '<tr >
                        <td>' . $output_product_name . '</td>
                        <td>' . $short_description . '</td>
                        <td>' . $quantity . '</td>
                        <td>' . $output_shipped_quantity_field . '</td>
                        <td style="text-align: right">' . prepare_amount($product_price) . '</td>
                        <td style="text-align: right">' . prepare_amount($product_total) . '</td>
                        <td>' . $output_recurring_payment_period . '</td>
                        <td>' . $output_recurring_number_of_payments . '</td>
                        <td>' . $output_recurring_start_date . '</td>
                        ' . $output_custom_field_1_cell . '
                        ' . $output_custom_field_2_cell . '
                        ' . $output_custom_field_3_cell . '
                        ' . $output_custom_field_4_cell . '
                    </tr>
                    ' . $output_form_row;
        }
        $output_order_details .=
                '       </table>
                    </td>
                </tr>';
        
         // if this is not the last ship to, output empty row for spacing
            if ($key < (count($ship_tos) - 1)) {
                $output_order_details .=
                    '<tr>
                        <td colspan="' . $output_colspan . '" style="border: 0">&nbsp;</td>
                    </tr>';
            }
    
    }
    
    // if the order is complete, then output save
    if ($status != 'incomplete') {
        $output_save_button = '<button type="submit" name="submit_save" value="Save" class="btn my-1  btn-success " data-loading-content="' . lang(array('string'=>'Saving') ) . '"><span class="material-icons me-2">save</span><span class="btn-text" >' . lang(array('string'=>'Save') ) . '</span></button>';
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

    $output_order_details .=
            '<tr >
                <th>' . lang('Product ID / SKU') . '</th>
                <th>' . lang('Short Description') . '</th>
                <th>' . lang('Qty') . '</th>
                <th>' . lang('Price') . '</th>
                <th>' . lang('Total') . '</th>
                <th>' . lang('Payment Period') . '</th>
                <th>' . lang('Number of Payments') . '</th>
                <th>' . lang('Start Date') . '</th>
                ' . $output_custom_field_1_heading . '
                ' . $output_custom_field_2_heading . '
                ' . $output_custom_field_3_heading . '
                ' . $output_custom_field_4_heading . '
            </tr>';

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
            $output_product_name = '<span class="cursor-pointer" onclick="document.location.href = \'edit_product.php?id=' . $product_id . '\'">' .  h($product_name) . '</span>';
            // if a product image_name found, output
            if ($image_name) {
                $output_image = '<img  class="rounded-4 card-img cursor-pointer d-print-none" style="top:52px;position:sticky;" src="' .  PATH . $image_name . '" onclick="document.location.href = \'edit_product.php?id=' . $product_id . '\'"/>';
            } 
        
        // else a product was not found, so do not include a link to product   
        } else {
            $output_product_name = h($product_name);
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
                    <div class="p-2  h-100 rounded">
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
                    <div class="p-2  h-100 rounded">
                        <div class="h-50 text-center text-muted">' . lang('Number of Payments') . '</div>
                        <div class="h-50 text-center">' . $output_recurring_number_of_payments . '</div>
                    </div>
                </div>';
                    
            $output_recurring_start_date = '
                <div class="col-12 col-sm-4 col-md-auto">
                    <div class="p-2  h-100 rounded">
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
                <div class="p-2  h-100 rounded">
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
                <div class="p-2  h-100 rounded">
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
                <div class="p-2  h-100 rounded">
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
                <div class="p-2  h-100 rounded">
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
                        '<fieldset' . $output_top_margin . '>
                            ' . $output_legend . '
                            <div style="padding: 0.7em">
                                <table cellpadding="4" class="order_details table table-bordered w-auto">
                                    <tr>
                                        <td>Code:</td>
                                        <td>' . $output_code . '</td>
                                    </tr>
                                    <tr>
                                        <td>Amount:</td>
                                        <td>' . prepare_amount($product_price) . '</td>
                                    </tr>
                                    <tr>
                                        <td>Recipient Email:</td>
                                        <td>' . h($order_item_gift_card['recipient_email_address']) . '</td>
                                    </tr>
                                    <tr>
                                        <td>From Name:</td>
                                        <td>' . h($order_item_gift_card['from_name']) . '</td>
                                    </tr>
                                    <tr>
                                        <td style="vertical-align: top">Message:</td>
                                        <td>' . nl2br(h($order_item_gift_card['message'])) . '</td>
                                    </tr>
                                    <tr>
                                        <td>Delivery Date:</td>
                                        <td>' . $output_delivery_date . '</td>
                                    </tr>
                                </table>
                            </div>
                        </fieldset>';
                    
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
                            '<fieldset' . $output_top_margin . '>
                                ' . $output_legend . '
                                <div style="padding: 0.7em">
                                    <table cellpadding="4" class="order_details table table-bordered w-auto">
                                        ' . get_submitted_product_form_content_without_form_fields($order_item_id, $quantity_number, 'backend') . '
                                    </table>
                                </div>
                            </fieldset>';
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
                    '<tr >
                        <td>&nbsp;</td>
                        <td colspan="' . $colspan . '">
                            <div style="padding: .7em">
                                ' . $output_gift_cards . '
                                ' . $output_forms . '
                            </div>
                        </td>
                    </tr>';
        }
        //not shipable
        $output_order_details .=
                '<tr >
                    <td>' . $output_product_name . '</td>
                    <td>' . $short_description . '</td>
                    <td>' . $quantity . '</td>
                    <td style="text-align: right">' . prepare_amount($product_price) . '</td>
                    <td style="text-align: right">' . prepare_amount($product_total) . '</td>
                    <td>' . $output_recurring_payment_period . '</td>
                    <td>' . $output_recurring_number_of_payments . '</td>
                    <td>' . $output_recurring_start_date . '</td>
                    ' . $output_custom_field_1_cell . '
                    ' . $output_custom_field_2_cell . '
                    ' . $output_custom_field_3_cell . '
                    ' . $output_custom_field_4_cell . '
                </tr>
                ' . $output_form_row;
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
        '<div class="card mt-3 bg-transparent border-0">
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
}

// -- Display preparation --
$billing_name_full = trim(
    ($billing_salutation ? h($billing_salutation) . ' ' : '') .
    h($billing_first_name) .
    ($billing_last_name ? ' ' . h($billing_last_name) : '')
);
$_addr_parts = array();
if ($billing_address_1) $_addr_parts[] = h($billing_address_1);
if ($billing_address_2) $_addr_parts[] = h($billing_address_2);
$_cs = trim(h($billing_city) . ($billing_state ? ' / ' . h($billing_state) : ''));
if ($_cs) $_addr_parts[] = $_cs;
if ($billing_zip_code) $_addr_parts[] = h($billing_zip_code);
if ($billing_country)  $_addr_parts[] = h($billing_country);
$billing_address_inline = implode(', ', $_addr_parts);
unset($_addr_parts, $_cs);

// Build print-friendly shipping section
$output_print_shipping = '';
if ($ship_to_exists) {
    foreach ($ship_tos as $key => $st) {
        if ($st['ship_to_id'] > 0) {
            $st_name = trim(($st['salutation'] ? h($st['salutation']) . ' ' : '') . h($st['first_name']) . ' ' . h($st['last_name']));
            $st_addr = '';
            if ($st['address_1']) $st_addr .= h($st['address_1']) . '<br/>';
            if ($st['address_2']) $st_addr .= h($st['address_2']) . '<br/>';
            $_st_cs = trim(h($st['city']) . ($st['state'] ? ', ' . h($st['state']) : '') . ' ' . h($st['zip_code']));
            if ($_st_cs) $st_addr .= $_st_cs . '<br/>';
            if ($st['country']) $st_addr .= h($st['country']);
            $st_method = $st['shipping_method_name'] ? $st['shipping_method_name'] : $st['shipping_method_code'];
            if ($st_name) $output_print_shipping .= '<div><strong>' . $st_name . '</strong></div>';
            if ($st['company']) $output_print_shipping .= '<div>' . h($st['company']) . '</div>';
            if ($st_addr) $output_print_shipping .= '<div style="margin-top:2px">' . $st_addr . '</div>';
            if ($st['phone_number']) $output_print_shipping .= '<div>&#9990; ' . h($st['phone_number']) . '</div>';
            if ($st_method) $output_print_shipping .= '<div style="margin-top:3px">' . lang('Shipping Method') . ': ' . h($st_method) . '</div>';
        }
    }
}

echo
output_header_secure(
    array(
        'title'   => '#' . $order_number . ' - ' . lang('Order'),
        'heading' => lang('View Order'),
    )
) . '
<style>
@media print { @page { size: A4 portrait; margin: 8mm 12mm; } }
body {

    font-family: "Lucida Grande","Lucida Sans Unicode","Lucida Sans",Lucida,Arial,Verdana,sans-serif;
    font-size: 85%;
    margin: 0;
    padding: .8em;
}
#content, main#content, .container-fluid { max-width: none !important; padding: 0 !important; margin: 0 !important; }
header, nav, .navbar, #sidebar, .d-print-none { display: none !important; }
h1, h2 { margin: 0 0 .3em 0; }
h1 { font-size: 1.4em; }
h2 { font-size: 1.1em; }
table { border-collapse: collapse; }
td, th { text-align: left; vertical-align: top; }
.ord-section-title {
    font-size: .75em;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .06em;
    border-bottom: 1px solid #333;
    padding-bottom: 2px;
    margin-bottom: 5px;
}
.row { display: flex !important; margin: 1px 0 !important; margin-left: 0 !important; margin-right: 0 !important; }
.col { flex: 1; min-width: 0; padding: 0 !important; }
.col-auto { flex: 0 0 auto; padding: 0 !important; }

.text-end { text-align: right !important; }
.fw-bold, .fw-semibold { font-weight: bold !important; }
.badge { display: inline !important; font-size: .85em; font-weight: normal; }
.card, .card-body, .card-header { border: none !important; background: transparent !important; padding: 0 !important; margin: 0 !important; box-shadow: none !important; }
.mt-1, .mt-2, .mt-3 { margin-top: 4px !important; }
.mb-1, .mb-2, .mb-3 { margin-bottom: 4px !important; }
.pt-1, .pt-2, .pt-4 { padding-top: 0 !important; }
table.order_details { width: 100%; }
legend { font-size: .9em; font-weight: bold; }
.product_form table { width: auto !important; }
.product_form td { border: none !important; }
</style>
<div id="print-order-content">

    <!-- HEADER: Order title | Organization -->
    <table style="width:100%;margin-bottom:1em">
        <tr>
            <td>
                <h1>' . lang('Order') . ' #' . h($order_number) . '</h1>
                <div>' . lang('Date') . ': ' . $order_date . '</div>
                <div>' . lang('Status') . ': ' . lang(ucwords($status)) . '</div>
            </td>
            <td style="text-align:right;vertical-align:top">
                <strong>' . h(ORGANIZATION_NAME) . '</strong><br/>
                ' . h(ORGANIZATION_ADDRESS_1) . '<br/>
                ' . (ORGANIZATION_ADDRESS_2 != '' ? h(ORGANIZATION_ADDRESS_2) . '<br/>' : '') . '
                ' . h(ORGANIZATION_CITY) . (ORGANIZATION_STATE != '' ? ', ' . h(ORGANIZATION_STATE) : '') . ' ' . h(ORGANIZATION_ZIP_CODE) . '<br/>
                ' . (ORGANIZATION_COUNTRY != '' ? h(ORGANIZATION_COUNTRY) : '') . '
            </td>
        </tr>
    </table>

    <!-- INFO SECTION: Billing | Shipping | Customer + Payment -->
    <table style="width:100%;margin-bottom:1em;font-size:.9em">
        <tr>
            <td style="width:' . ($ship_to_exists ? '33%' : '48%') . ';padding-right:1.5em;vertical-align:top">
                <div class="ord-section-title">' . lang('Billing Information') . '</div>
                ' . ($billing_name_full !== '' ? '<div><strong>' . $billing_name_full . '</strong></div>' : '') . '
                ' . ($billing_company ? '<div>' . h($billing_company) . '</div>' : '') . '
                ' . ($billing_address_inline ? '<div style="margin-top:3px">' . $billing_address_inline . '</div>' : '') . '
                ' . ($billing_phone_number ? '<div>&#9990; ' . h($billing_phone_number) . '</div>' : '') . '
                ' . ($billing_email_address ? '<div>@ ' . h($billing_email_address) . '</div>' : '') . '
                ' . ($billing_fax_number ? '<div>' . lang('Fax') . ': ' . h($billing_fax_number) . '</div>' : '') . '
                ' . ($po_number ? '<div style="margin-top:3px">' . lang('PO Number') . ': ' . h($po_number) . '</div>' : '') . '
                ' . ($custom_field_1 ? '<div>' . lang('Custom Field') . ' #1: ' . h($custom_field_1) . '</div>' : '') . '
                ' . ($custom_field_2 ? '<div>' . lang('Custom Field') . ' #2: ' . h($custom_field_2) . '</div>' : '') . '
            </td>
            ' . ($ship_to_exists && $output_print_shipping !== '' ?
            '<td style="width:33%;padding-right:1.5em;vertical-align:top">
                <div class="ord-section-title">' . lang('Shipping Information') . '</div>
                ' . $output_print_shipping . '
            </td>' : '') . '
            <td style="vertical-align:top">
                <div class="ord-section-title">' . lang('Customer Information') . '</div>
                <div style="font-size:.9em">' .
                $output_contact_row .
                $output_user_row .
                $output_member_id_row .
                $output_ip_address_row . '
                </div>' .
                ($payment_method ? '
                <div style="margin-top:6px">
                    <div class="ord-section-title">' . lang('Payment') . '</div>
                    <div><strong>' . lang(h($payment_method)) . '</strong></div>' .
                    ($special_offer_code ? '<div>' . lang('Special Offer Code') . ': ' . h($special_offer_code) . '</div>' : '') .
                    ($reference_code ? '<div>' . lang('Reference Code') . ': ' . h($reference_code) . '</div>' : '') .
                    ($output_currency_code_row ? '<div>' . lang('Currency') . ': ' . h(get_currency_name_from_code($currency_code)) . ' (' . h($currency_code) . ')</div>' : '') . '
                </div>' : '') . '
            </td>
        </tr>
    </table>

    <!-- PRODUCTS -->
    <div class="ord-section-title" style="margin-bottom:.3em">' . lang('Products') . '</div>
    <table cellpadding="4" class="order_details" style="width:100%">' . $output_order_details . '</table>

    <!-- TOTALS -->
    <table style="margin-left:auto;margin-top:.5em;border-collapse:collapse">
        <tr><td style="padding:.15em .5em">' . lang('Subtotal') . '</td><td style="padding:.15em .5em;text-align:right">' . prepare_amount($subtotal) . '</td></tr>
        ' . ($discount > 0 ? '<tr><td style="padding:.15em .5em">' . lang('Discount') . '</td><td style="padding:.15em .5em;text-align:right">-' . BASE_CURRENCY_SYMBOL . $discount . '</td></tr>' : '') . '
        ' . ($tax > 0 ? '<tr><td style="padding:.15em .5em">' . lang('Tax') . '</td><td style="padding:.15em .5em;text-align:right">' . BASE_CURRENCY_SYMBOL . $tax . '</td></tr>' : '') . '
        ' . ($shipping > 0 ? '<tr><td style="padding:.15em .5em">' . lang('Shipping') . '</td><td style="padding:.15em .5em;text-align:right">' . BASE_CURRENCY_SYMBOL . $shipping . '</td></tr>' : '') . '
        ' . ($gift_card_discount > 0 ? '<tr><td style="padding:.15em .5em">' . lang('Gift Card') . '</td><td style="padding:.15em .5em;text-align:right">-' . BASE_CURRENCY_SYMBOL . $gift_card_discount . '</td></tr>' : '') . '
        ' . ($surcharge > 0 ? '<tr><td style="padding:.15em .5em">' . lang('Surcharge') . '</td><td style="padding:.15em .5em;text-align:right">' . BASE_CURRENCY_SYMBOL . $surcharge . '</td></tr>' : '') . '
        ' . ($installment_charges > 0 && $payment_installment >= 2 ? '<tr><td style="padding:.15em .5em">' . lang('Installment Charge') . '</td><td style="padding:.15em .5em;text-align:right">' . BASE_CURRENCY_SYMBOL . $installment_charges . '</td></tr>' : '') . '
        <tr style="border-top:2px solid #333"><td style="padding:.2em .5em"><strong>' . lang('Total') . '</strong></td><td style="padding:.2em .5em;text-align:right"><strong>' . prepare_amount($total) . '</strong></td></tr>
        ' . ($refunded_amount_cents > 0 ?
            '<tr><td style="padding:.15em .5em;color:#dc3545">' . lang('Total Refunded') . '</td><td style="padding:.15em .5em;text-align:right;color:#dc3545">-' . BASE_CURRENCY_SYMBOL . number_format($refunded_amount_cents / 100, 2, '.', ',') . '</td></tr>
            <tr style="border-top:1px solid #333"><td style="padding:.2em .5em"><strong>' . lang('Net Total') . '</strong></td><td style="padding:.2em .5em;text-align:right"><strong>' . prepare_amount(number_format(($total_cents - $refunded_amount_cents) / 100, 2, '.', ',')) . '</strong></td></tr>'
        : '') . '
    </table>

    <!-- EXTRA INFO: payment details, source, gift cards, affiliate -->
    ' . (($output_payment_information !== '' || $source !== '' || $output_applied_gift_cards !== '' || $output_affiliate !== '' || $output_custom_billing_information !== '') ?
    '<div style="margin-top:1em;padding-top:.5em;border-top:1px solid #ccc;font-size:.85em;display:flex;flex-wrap:wrap;gap:1.5em">' .
        $output_payment_information .
        $source .
        $output_applied_gift_cards .
        $output_affiliate .
        $output_custom_billing_information .
    '</div>' : '') . '

</div>
<script>
window.onload = function() { setTimeout(function() { window.print(); }, 500); };
</script>' .
output_footer_secure();
