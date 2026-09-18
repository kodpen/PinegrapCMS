<?php

/**
 * Pinegrap - Enterprise Website Platform
 *
 * Site Settings - what the Ecommerce screen writes: the store, shipping, gift cards, invoicing, payment and affiliates.
 *
 * Runs in the scope of includes/settings/screen.php, after the token check and
 * after post_value() is defined. Only the columns edited by the cards on this
 * screen are written, so a screen that was not submitted cannot have its
 * settings overwritten -- which is what the single screen used to do.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_SETTINGS_ENTRY')) {
    exit;
}

/**
 * Build the encrypted Parasut credentials blob for the settings save.
 *
 * The screen never renders a stored secret back into the form, so an empty box
 * means "leave it alone" and not "clear it" - otherwise every unrelated save on
 * the e-commerce settings page would wipe the connection. Clearing is done by
 * turning the integration off, not by blanking a field nobody filled in.
 *
 * @return string  "<ciphertext>:<iv>", or '' when nothing has ever been stored
 */
function pg_parasut_credentials_for_save()
{
    $current = ['client_secret' => '', 'password' => ''];

    $stored = (string) db_value("SELECT parasut_credentials_enc FROM config LIMIT 1");
    if (($stored !== '') && (strpos($stored, ':') !== false)) {
        list($cipher, $iv) = explode(':', $stored, 2);
        $json = decode_ssl_keys($cipher, $iv);
        $values = ($json === '') ? null : json_decode($json, true);
        if (is_array($values)) {
            $current['client_secret'] = (string) ($values['client_secret'] ?? '');
            $current['password'] = (string) ($values['password'] ?? '');
        }
    }

    $posted_secret = trim(post_value('parasut_client_secret'));
    $posted_password = trim(post_value('parasut_password'));

    if ($posted_secret !== '') {
        $current['client_secret'] = $posted_secret;
    }
    if ($posted_password !== '') {
        $current['password'] = $posted_password;
    }

    if (($current['client_secret'] === '') && ($current['password'] === '')) {
        return '';
    }

    list($cipher, $iv) = encrypt_string_with_iv(json_encode($current));

    return $cipher . ':' . $iv;
}



    // if the user selected to reset encryption key, then do that
    if (
        isset($_POST['ecommerce_reset_encryption_key'])
        && $_POST['ecommerce_reset_encryption_key'] == 1
    ){
    
        // if OpenSSL is disabled, then output error
        if (extension_loaded('openssl') == FALSE) {
            output_error(lang('The encryption key could not be reset, because the OpenSSL PHP extension is not enabled') . '. <a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.');
        }
        
        // get contents of config.php file in order to reset encryption key
        $config_content = file_get_contents(CONFIG_FILE_PATH);
        
        // open the config.php file so the encryption key can be reset
        $handle = @fopen(CONFIG_FILE_PATH, 'w');
        
        // if the config.php file could not be opened for writing, then output error
        if ($handle == FALSE) {
            output_error(lang(array('string'=>'The encryption key could not be reset, because the config.php file ({var:1}) is not writable. Please configure the config.php file so it can be written to and then try again. For Unix, set the permissions for the file to 777. For Windows, give the anonymous web user rights to write to and delete the file.','vars'=>array(OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/data/config.php') )) . ' <a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.');
        }
        
        $old_encryption_key = (defined('ENCRYPTION_KEY') == TRUE) ? ENCRYPTION_KEY : '';
        $new_encryption_key = generate_encryption_key();
        
        // if there is not an old encryption key in the config.php file, then add new encryption key to config.php file
        if (defined('ENCRYPTION_KEY') == FALSE) {
            $config_content = str_replace('?>', "define('ENCRYPTION_KEY', '" . $new_encryption_key . "'); // DO NOT MODIFY OR SHARE\r\n?>", $config_content);
            
        // else there is an old encryption key in the config.php file, so update it
        } else {
            $config_content = str_replace($old_encryption_key, $new_encryption_key, $config_content);
        }
        
        // update the config.php file with the new content
        @fwrite($handle, $config_content);
        
        // close the config.php file
        @fclose($handle);
        
        // get all orders that have an unencrypted credit card number or encrypted credit card number
        $query = 
            "SELECT
                id,
                card_number
            FROM orders
            WHERE
                (card_number != '')
                AND (SUBSTRING(card_number, 1, 1) != '*')";
        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
        
        $orders = array();
        
        while ($row = mysqli_fetch_assoc($result)) {
            $orders[] = $row;
        }
        
        // loop through all orders in order to re-encrypt or encrypt credit card numbers
        foreach ($orders as $order) {
            // if the credit card number is already encrypted, then decrypt it with old key and encrypt it with new key
            if (mb_strlen($order['card_number']) > 16) {
                $order['card_number'] = decrypt_credit_card_number($order['card_number'], $old_encryption_key);
                
                // if the decryption was successful, then encrypt it with new key and store it
                if (is_numeric($order['card_number']) == TRUE) {
                    $query = "UPDATE orders SET card_number = '" . e(encrypt_credit_card_number($order['card_number'], $new_encryption_key)) . "' WHERE id = '" . (int) $order['id'] . "'";
                    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
                }
                
            // else the credit card number is not already encrypted, so encrypt it for the first time
            } else {
                $query = "UPDATE orders SET card_number = '" . e(encrypt_credit_card_number($order['card_number'], $new_encryption_key)) . "' WHERE id = '" . (int) $order['id'] . "'";
                $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
            }
        }
    }

    // Remove commas from gift card validity days.
    $gift_card_validity_days = str_replace(',', '', $_POST['ecommerce_gift_card_validity_days']);

    // Determine payment gateway mode.
    if (
        isset($_POST['ecommerce_payment_gateway'])
        && $_POST['ecommerce_payment_gateway'] == 'PayPal Payments Pro'
    ){
        $payment_gateway_mode = $_POST['ecommerce_paypal_payments_pro_gateway_mode'];
    } else {
        $payment_gateway_mode = $_POST['ecommerce_payment_gateway_mode'];
    }

    // Where the product screens file an uploaded image (2026.4.4).
    $sql_product_upload_folder = "";

    if (waf_table_has_column('config', 'product_upload_folder_id')) {
        $sql_product_upload_folder = "product_upload_folder_id = '" . escape((int) post_value('product_upload_folder_id')) . "',";
    }
    // Foreign currency in the ERP (2026.4.4). The currency list is whatever
    // was ticked, kept to three-letter codes the store lists; the base is
    // never stored because it is always allowed.
    $sql_erp_fx = "";

    // The default payment term (2026.4.4, 4.53): whole days, ten years at most.
    $sql_erp_due = "";

    if (waf_table_has_column('config', 'erp_default_due_days')) {
        $sql_erp_due = "erp_default_due_days = '" . min(3650, max(0, (int) post_value('erp_default_due_days'))) . "',";
    }

    if (waf_table_has_column('config', 'erp_fx_enabled')) {
        $erp_fx_codes = array();
        $erp_fx_known = array();

        foreach ((array) db_items("SELECT code FROM currencies WHERE base != 1") as $erp_fx_row) {
            $erp_fx_known[] = strtoupper(trim((string) $erp_fx_row['code']));
        }

        foreach ((array) post_value('erp_fx_currencies') as $erp_fx_code) {
            $erp_fx_code = strtoupper(trim((string) $erp_fx_code));

            if (preg_match('/^[A-Z]{3}$/', $erp_fx_code) && in_array($erp_fx_code, $erp_fx_known, true) && !in_array($erp_fx_code, $erp_fx_codes, true)) {
                $erp_fx_codes[] = $erp_fx_code;
            }
        }

        $sql_erp_fx = "erp_fx_enabled = '" . ((post_value('erp_fx_enabled') == 1) ? 1 : 0) . "',
            erp_fx_currencies = '" . escape(substr(implode(',', $erp_fx_codes), 0, 64)) . "',
            erp_fx_auto_diff = '" . ((post_value('erp_fx_auto_diff') == 1) ? 1 : 0) . "',";
    }

    // Only what the cards on this screen edit.
    db("UPDATE config
        SET
            " . $sql_product_upload_folder . "
            ecommerce = '" . escape(post_value('ecommerce')) . "',
            ecommerce_multicurrency = '" . escape(post_value('ecommerce_multicurrency')) . "',
            ecommerce_tax = '" . escape(post_value('ecommerce_tax')) . "',
            ecommerce_tax_exempt = '" . escape(post_value('ecommerce_tax_exempt')) . "',
            ecommerce_tax_exempt_label = '" . escape(post_value('ecommerce_tax_exempt_label')) . "',
            ecommerce_email_address = '" . escape(post_value('ecommerce_email_address')) . "',
            ecommerce_show_product_images = '" . escape(post_value('ecommerce_show_product_images')) . "',
            barcode_enabled        = '" . escape(post_value('barcode_enabled'))        . "',
            barcode_default_type   = '" . escape(trim(post_value('barcode_default_type') ?: 'CODE128')) . "',
            iyzipay_protected_currency_code = '" . escape(post_value('iyzipay_protected_currency_code')) . "',
            ecommerce_shipping = '" . escape(post_value('ecommerce_shipping')) . "',
            ecommerce_recipient_mode = '" . escape(post_value('ecommerce_recipient_mode')) . "',
            usps_user_id = '" . e(trim(post_value('usps_user_id'))) . "',
            ecommerce_address_verification = '" . escape(post_value('ecommerce_address_verification')) . "',
            ecommerce_address_verification_enforcement_type = '" . escape(post_value('ecommerce_address_verification_enforcement_type')) . "',
            ups = '" . e(post_value('ups')) . "',
            ups_key = '" . e(trim(post_value('ups_key'))) . "',
            ups_user_id = '" . e(trim(post_value('ups_user_id'))) . "',
            ups_password = '" . e(trim(post_value('ups_password'))) . "',
            ups_account = '" . e(trim(post_value('ups_account'))) . "',
            fedex = '" . e(post_value('fedex')) . "',
            fedex_key = '" . e(trim(post_value('fedex_key'))) . "',
            fedex_password = '" . e(trim(post_value('fedex_password'))) . "',
            fedex_account = '" . e(trim(post_value('fedex_account'))) . "',
            fedex_meter = '" . e(trim(post_value('fedex_meter'))) . "',
            ecommerce_product_restriction_message = '" . escape(post_value('ecommerce_product_restriction_message')) . "',
            ecommerce_no_shipping_methods_message = '" . escape(post_value('ecommerce_no_shipping_methods_message')) . "',
            ecommerce_end_of_day_time = '" . e(prepare_form_data_for_input(post_value('ecommerce_end_of_day_time'), 'time')) . "',
            ecommerce_gift_card = '" . escape(post_value('ecommerce_gift_card')) . "',
            ecommerce_gift_card_validity_days = '" . escape($gift_card_validity_days) . "',
            ecommerce_givex = '" . escape(post_value('ecommerce_givex')) . "',
            ecommerce_givex_primary_hostname = '" . escape(post_value('ecommerce_givex_primary_hostname')) . "',
            ecommerce_givex_secondary_hostname = '" . escape(post_value('ecommerce_givex_secondary_hostname')) . "',
            ecommerce_givex_user_id = '" . escape(trim(post_value('ecommerce_givex_user_id'))) . "',
            ecommerce_givex_password = '" . escape(trim(post_value('ecommerce_givex_password'))) . "',
            ecommerce_reward_program = '" . escape(post_value('ecommerce_reward_program')) . "',
            ecommerce_reward_program_points = '" . escape(post_value('ecommerce_reward_program_points')) . "',
            ecommerce_reward_program_membership = '" . escape(post_value('ecommerce_reward_program_membership')) . "',
            ecommerce_reward_program_membership_days = '" . escape(post_value('ecommerce_reward_program_membership_days')) . "',
            ecommerce_reward_program_email = '" . escape(post_value('ecommerce_reward_program_email')) . "',
            ecommerce_reward_program_email_bcc_email_address = '" . escape(post_value('ecommerce_reward_program_email_bcc_email_address')) . "',
            ecommerce_reward_program_email_subject = '" . escape(post_value('ecommerce_reward_program_email_subject')) . "',
            ecommerce_reward_program_email_page_id = '" . escape(post_value('ecommerce_reward_program_email_page_id')) . "',
            enable_parasut = '" . escape(post_value('enable_parasut')) . "',
            parasut_tc_in_field = '" . escape(post_value('parasut_tc_in_field')) . "',
            parasut_client_id = '" . escape(trim(post_value('parasut_client_id'))) . "',
            parasut_credentials_enc = '" . escape(pg_parasut_credentials_for_save()) . "',
            parasut_username = '" . escape(trim(post_value('parasut_username'))) . "',
            parasut_company_id = '" . escape(trim(post_value('parasut_company_id'))) . "',
            parasut_default_product_id = '" . escape(trim(post_value('parasut_default_product_id'))) . "',
            parasut_default_warehouse_id = '" . escape(trim(post_value('parasut_default_warehouse_id'))) . "',
            erp_enabled = '" . escape(post_value('erp_enabled')) . "',
            erp_default_series = '" . escape(trim(post_value('erp_default_series'))) . "',
            erp_web_address = '" . escape(trim(post_value('erp_web_address'))) . "',
            erp_seller_vkn = '" . escape(substr(preg_replace('/\D/', '', (string) post_value('erp_seller_vkn')), 0, 11)) . "',
            erp_seller_tax_office = '" . escape(trim(post_value('erp_seller_tax_office'))) . "',
            " . $sql_erp_fx . "
            " . $sql_erp_due . "
            ecommerce_credit_debit_card = '" . escape(post_value('ecommerce_credit_debit_card')) . "',
            ecommerce_american_express = '" . escape(post_value('ecommerce_american_express')) . "',
            ecommerce_diners_club = '" . escape(post_value('ecommerce_diners_club')) . "',
            ecommerce_discover_card = '" . escape(post_value('ecommerce_discover_card')) . "',
            ecommerce_mastercard = '" . escape(post_value('ecommerce_mastercard')) . "',
            ecommerce_visa = '" . escape(post_value('ecommerce_visa')) . "',
            ecommerce_troy = '" . escape(post_value('ecommerce_troy')) . "',
            ecommerce_payment_gateway = '" . escape(post_value('ecommerce_payment_gateway')) . "',
            ecommerce_payment_gateway_transaction_type = '" . escape(post_value('ecommerce_payment_gateway_transaction_type')) . "',
            ecommerce_payment_gateway_mode = '" . escape($payment_gateway_mode) . "',
            ecommerce_authorizenet_api_login_id = '" . escape(trim(post_value('ecommerce_authorizenet_api_login_id'))) . "',
            ecommerce_authorizenet_transaction_key = '" . escape(trim(post_value('ecommerce_authorizenet_transaction_key'))) . "',
            ecommerce_clearcommerce_client_id = '" . escape(trim(post_value('ecommerce_clearcommerce_client_id'))) . "',
            ecommerce_clearcommerce_user_id = '" . escape(trim(post_value('ecommerce_clearcommerce_user_id'))) . "',
            ecommerce_clearcommerce_password = '" . escape(trim(post_value('ecommerce_clearcommerce_password'))) . "',
            ecommerce_first_data_global_gateway_store_number = '" . escape(trim(post_value('ecommerce_first_data_global_gateway_store_number'))) . "',
            ecommerce_first_data_global_gateway_pem_file_name = '" . escape(trim(post_value('ecommerce_first_data_global_gateway_pem_file_name'))) . "',
            ecommerce_paypal_payflow_pro_partner = '" . escape(trim(post_value('ecommerce_paypal_payflow_pro_partner'))) . "',
            ecommerce_paypal_payflow_pro_merchant_login = '" . escape(trim(post_value('ecommerce_paypal_payflow_pro_merchant_login'))) . "',
            ecommerce_paypal_payflow_pro_user = '" . escape(trim(post_value('ecommerce_paypal_payflow_pro_user'))) . "',
            ecommerce_paypal_payflow_pro_password = '" . escape(trim(post_value('ecommerce_paypal_payflow_pro_password'))) . "',
            ecommerce_paypal_payments_pro_api_username = '" . escape(trim(post_value('ecommerce_paypal_payments_pro_api_username'))) . "',
            ecommerce_paypal_payments_pro_api_password = '" . escape(trim(post_value('ecommerce_paypal_payments_pro_api_password'))) ."',
            ecommerce_paypal_payments_pro_api_signature = '" . escape(trim(post_value('ecommerce_paypal_payments_pro_api_signature'))) ."',
            ecommerce_sage_merchant_id = '" . escape(trim(post_value('ecommerce_sage_merchant_id'))) . "',
            ecommerce_sage_merchant_key = '" . escape(trim(post_value('ecommerce_sage_merchant_key'))) . "',
            ecommerce_stripe_api_key = '" . escape(trim(post_value('ecommerce_stripe_api_key'))) . "',
            ecommerce_iyzipay_api_key = '" . escape(trim(post_value('ecommerce_iyzipay_api_key'))) . "',
            ecommerce_iyzipay_secret_key = '" . escape(trim(post_value('ecommerce_iyzipay_secret_key'))) . "',
            ecommerce_iyzipay_installment = '" . escape(post_value('ecommerce_iyzipay_installment')) . "',
            ecommerce_iyzipay_threeds = '" . escape(post_value('ecommerce_iyzipay_threeds')) . "',
            ecommerce_pay_with_iyzico = '" . escape(post_value('ecommerce_pay_with_iyzico')) . "',
            ecommerce_surcharge_percentage = '" . escape(post_value('ecommerce_surcharge_percentage')) . "',
            ecommerce_paypal_express_checkout = '" . escape(post_value('ecommerce_paypal_express_checkout')) . "',
            ecommerce_paypal_express_checkout_transaction_type = '" . escape(post_value('ecommerce_paypal_express_checkout_transaction_type')) . "',
            ecommerce_paypal_express_checkout_mode = '" . escape(post_value('ecommerce_paypal_express_checkout_mode')) . "',
            ecommerce_paypal_express_checkout_api_username = '" . escape(trim(post_value('ecommerce_paypal_express_checkout_api_username'))) . "',
            ecommerce_paypal_express_checkout_api_password = '" . escape(trim(post_value('ecommerce_paypal_express_checkout_api_password'))) . "',
            ecommerce_paypal_express_checkout_api_signature = '" . escape(trim(post_value('ecommerce_paypal_express_checkout_api_signature'))) . "',
            ecommerce_offline_payment = '" . escape(post_value('ecommerce_offline_payment')) . "',
            ecommerce_offline_payment_only_specific_orders = '" . escape(post_value('ecommerce_offline_payment_only_specific_orders')) . "',
            ecommerce_offline_payment_cancel_days = '" . min(255, max(0, (int) preg_replace('/\D/', '', (string) post_value('ecommerce_offline_payment_cancel_days')))) . "',
            ecommerce_private_folder_id = '" . e(post_value('ecommerce_private_folder_id')) . "',
            ecommerce_retrieve_order_next_page_id = '" . escape(post_value('ecommerce_retrieve_order_next_page_id')) . "',
            ecommerce_custom_product_field_1_label = '" . escape(post_value('ecommerce_custom_product_field_1_label')) . "',
            ecommerce_custom_product_field_2_label = '" . escape(post_value('ecommerce_custom_product_field_2_label')) . "',
            ecommerce_custom_product_field_3_label = '" . escape(post_value('ecommerce_custom_product_field_3_label')) . "',
            ecommerce_custom_product_field_4_label = '" . escape(post_value('ecommerce_custom_product_field_4_label')) . "',
            enable_iyzipay_protected_currency = '" . escape(post_value('enable_iyzipay_protected_currency')) . "',
            affiliate_program = '" . escape(post_value('affiliate_program')) . "',
            affiliate_default_commission_rate = '" . escape(post_value('affiliate_default_commission_rate')) . "',
            affiliate_automatic_approval = '" . escape(post_value('affiliate_automatic_approval')) . "',
            affiliate_contact_group_id = '" . escape(post_value('affiliate_contact_group_id')) . "',
            affiliate_email_address = '" . escape(post_value('affiliate_email_address')) . "',
            affiliate_group_offer_id = '" . escape(post_value('affiliate_group_offer_id')) . "',
            last_modified_user_id = '" . USER_ID . "',
            last_modified_timestamp = UNIX_TIMESTAMP()");

    
    // if there was a next order number field, update next order number
    if (isset($_POST['ecommerce_next_order_number']) == true) {
        // if next order number that was submitted is blank, set order number to 1
        if (!$_POST['ecommerce_next_order_number']) {
            $ecommerce_next_order_number = 1;
        } else {
            $ecommerce_next_order_number = $_POST['ecommerce_next_order_number'];
        }
        
        // lock table, so no one can read table
        $query = "LOCK TABLES next_order_number WRITE";
        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
        
        // delete existing record for next order number
        $query = "DELETE FROM next_order_number";
        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
        
        // create new record for next order number
        $query = "INSERT INTO next_order_number VALUES ('" . escape($ecommerce_next_order_number) . "')";
        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
        
        // release lock on table
        $query = "UNLOCK TABLES";
        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
    }
