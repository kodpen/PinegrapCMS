<?php

/**
 * Pinegrap - Enterprise Website Platform
 *
 * Site Settings - what the Ecommerce screen writes: the store, shipping, gift cards, payment and affiliates.
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

    // Product feeds (2026.11.0, 11.0.4): the addresses the feeds pass on must
    // be absolute, so a wrong one is refused here, before anything on this
    // screen is written. Only when the card's fields were posted and the
    // column is there.
    $product_feeds_save_ready = function_exists('pg_product_feed_schema_ready') && pg_product_feed_schema_ready()
        && array_key_exists('product_feed_seller_name', $_POST);

    if ($product_feeds_save_ready) {
        foreach (array('product_feed_seller_url', 'product_feed_privacy_policy_url', 'product_feed_terms_url') as $product_feeds_field) {
            if (!pg_product_feed_valid_url(post_value($product_feeds_field))) {
                $liveform->mark_error($product_feeds_field, lang('Enter a full address starting with https://, or leave the field empty.'));
                return;
            }
        }
    }


    // Reference price rule (11.0.49). The card is drawn only once the column
    // exists; an unknown value is stored as the default rule.
    $sql_reference_price_rule = '';

    if (pg_price_reference_rule_ready() && (post_value('ecommerce_reference_price_rule') !== NULL)) {
        $sql_reference_price_rule = "ecommerce_reference_price_rule = '" . e(pg_price_reference_rule_from_input(post_value('ecommerce_reference_price_rule'))) . "',";
    }

    // The payment drivers' settings (includes/fn/payments.php) are checked
    // here, so a refused one stops the whole card before anything is written,
    // and stored after the card's own columns.
    $payment_driver_decisions = pg_payment_settings_check($_POST, $liveform);

    // The same for the carrier label drivers (includes/fn/carrier_labels.php).
    $label_driver_decisions = pg_label_settings_check($_POST, $liveform);

    // A new gateway secret whose sealed form would not fit its column is
    // refused rather than cut or stored plain.
    foreach (pg_gateway_secret_fields() as $gateway_secret_column => $gateway_secret_width) {
        $gateway_secret_posted = trim((string) post_value($gateway_secret_column));

        if (($gateway_secret_posted !== pg_gateway_secret_mask()) && !pg_gateway_secret_fits($gateway_secret_posted, $gateway_secret_width)) {
            $liveform->mark_error($gateway_secret_column, lang(array('string' => 'This secret is too long to be stored encrypted; at most {var:1} characters.', 'vars' => array(pg_gateway_secret_max_length($gateway_secret_width)))));
        }
    }

    // The order e-mail (also printed on ERP documents), the reward BCC and
    // the affiliate e-mail are one address each.
    foreach (pg_email_field_errors(array(
        'ecommerce_email_address' => post_value('ecommerce_email_address'),
        'ecommerce_reward_program_email_bcc_email_address' => post_value('ecommerce_reward_program_email_bcc_email_address'),
        'affiliate_email_address' => post_value('affiliate_email_address'),
    )) as $email_field => $email_error) {
        $liveform->mark_error($email_field, $email_error);
    }

    // Neither folder may be the Workspace's or below it, nor in the Recycle
    // Bin (pg_settings_folder_choice_ok()): granted access there would open
    // conversation files to every buyer, and product images there could not
    // be shown to visitors.
    foreach (array('product_upload_folder_id', 'ecommerce_private_folder_id') as $folder_field) {
        if (!pg_settings_folder_choice_ok((int) post_value($folder_field))) {
            $liveform->mark_error($folder_field, lang('Choose a folder outside the Workspace and the Recycle Bin.'));
        }
    }

    if ($liveform->check_form_errors()) {
        return;
    }

    // The gateway secrets as written: kept or replaced. The Encryption Key
    // card (security.save.php) seals them again when it resets the key.
    $gateway_secret_key = defined('ENCRYPTION_KEY') ? (string) ENCRYPTION_KEY : '';
    $gateway_secret_new_key = $gateway_secret_key;
    $gateway_secret_stored = db_item("SELECT " . implode(', ', array_keys(pg_gateway_secret_fields())) . " FROM config LIMIT 1");
    $gateway_secret_values = array();

    foreach (pg_gateway_secret_fields() as $gateway_secret_column => $gateway_secret_width) {
        $gateway_secret_values[$gateway_secret_column] = pg_gateway_secret_for_save(post_value($gateway_secret_column), (string) ($gateway_secret_stored[$gateway_secret_column] ?? ''), $gateway_secret_key, $gateway_secret_new_key, $gateway_secret_width);
    }

    // Gift card validity days, grouped the panel's way ("1.095" is 1095),
    // held to the smallint(5) unsigned column.
    $gift_card_validity_days = pg_parse_whole_number(post_value('ecommerce_gift_card_validity_days'), 65535);

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
    // Photo reviews (2026.11.0, 11.0.57), once the column is there.
    $sql_review_photos = pg_schema_has('config', 'review_photos')
        ? "review_photos = '" . ((post_value('review_photos') == 1) ? 1 : 0) . "',"
        : '';

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
            ecommerce_address_verification = '" . escape(post_value('ecommerce_address_verification')) . "',
            ecommerce_address_verification_enforcement_type = '" . escape(post_value('ecommerce_address_verification_enforcement_type')) . "',
            ups = '" . e(post_value('ups')) . "',
            ups_account = '" . e(trim(post_value('ups_account'))) . "',
            fedex = '" . e(post_value('fedex')) . "',
            fedex_account = '" . e(trim(post_value('fedex_account'))) . "',
            ecommerce_product_restriction_message = '" . escape(post_value('ecommerce_product_restriction_message')) . "',
            ecommerce_no_shipping_methods_message = '" . escape(post_value('ecommerce_no_shipping_methods_message')) . "',
            ecommerce_end_of_day_time = '" . e(prepare_form_data_for_input(post_value('ecommerce_end_of_day_time'), 'time')) . "',
            ecommerce_gift_card = '" . escape(post_value('ecommerce_gift_card')) . "',
            ecommerce_gift_card_validity_days = '" . $gift_card_validity_days . "',
            ecommerce_givex = '" . escape(post_value('ecommerce_givex')) . "',
            ecommerce_givex_primary_hostname = '" . escape(post_value('ecommerce_givex_primary_hostname')) . "',
            ecommerce_givex_secondary_hostname = '" . escape(post_value('ecommerce_givex_secondary_hostname')) . "',
            ecommerce_givex_user_id = '" . escape(trim(post_value('ecommerce_givex_user_id'))) . "',
            ecommerce_givex_password = '" . escape(trim(post_value('ecommerce_givex_password'))) . "',
            ecommerce_reward_program = '" . escape(post_value('ecommerce_reward_program')) . "',
            ecommerce_reward_program_points = '" . pg_parse_reward_goal(post_value('ecommerce_reward_program_points')) . "',
            ecommerce_reward_program_membership = '" . escape(post_value('ecommerce_reward_program_membership')) . "',
            ecommerce_reward_program_membership_days = '" . pg_parse_whole_number(post_value('ecommerce_reward_program_membership_days'), 4294967295) . "',
            ecommerce_reward_program_email = '" . escape(post_value('ecommerce_reward_program_email')) . "',
            ecommerce_reward_program_email_bcc_email_address = '" . escape(post_value('ecommerce_reward_program_email_bcc_email_address')) . "',
            ecommerce_reward_program_email_subject = '" . escape(post_value('ecommerce_reward_program_email_subject')) . "',
            ecommerce_reward_program_email_page_id = '" . escape(post_value('ecommerce_reward_program_email_page_id')) . "',
            " . $sql_review_photos . "
            " . $sql_reference_price_rule . "
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
            ecommerce_authorizenet_transaction_key = '" . escape($gateway_secret_values['ecommerce_authorizenet_transaction_key']) . "',
            ecommerce_clearcommerce_client_id = '" . escape(trim(post_value('ecommerce_clearcommerce_client_id'))) . "',
            ecommerce_clearcommerce_user_id = '" . escape(trim(post_value('ecommerce_clearcommerce_user_id'))) . "',
            ecommerce_clearcommerce_password = '" . escape(trim(post_value('ecommerce_clearcommerce_password'))) . "',
            ecommerce_first_data_global_gateway_store_number = '" . escape(trim(post_value('ecommerce_first_data_global_gateway_store_number'))) . "',
            ecommerce_first_data_global_gateway_pem_file_name = '" . escape(trim(post_value('ecommerce_first_data_global_gateway_pem_file_name'))) . "',
            ecommerce_paypal_payflow_pro_partner = '" . escape(trim(post_value('ecommerce_paypal_payflow_pro_partner'))) . "',
            ecommerce_paypal_payflow_pro_merchant_login = '" . escape(trim(post_value('ecommerce_paypal_payflow_pro_merchant_login'))) . "',
            ecommerce_paypal_payflow_pro_user = '" . escape(trim(post_value('ecommerce_paypal_payflow_pro_user'))) . "',
            ecommerce_paypal_payflow_pro_password = '" . escape($gateway_secret_values['ecommerce_paypal_payflow_pro_password']) . "',
            ecommerce_paypal_payments_pro_api_username = '" . escape(trim(post_value('ecommerce_paypal_payments_pro_api_username'))) . "',
            ecommerce_paypal_payments_pro_api_password = '" . escape($gateway_secret_values['ecommerce_paypal_payments_pro_api_password']) . "',
            ecommerce_paypal_payments_pro_api_signature = '" . escape(trim(post_value('ecommerce_paypal_payments_pro_api_signature'))) ."',
            ecommerce_sage_merchant_id = '" . escape(trim(post_value('ecommerce_sage_merchant_id'))) . "',
            ecommerce_sage_merchant_key = '" . escape(trim(post_value('ecommerce_sage_merchant_key'))) . "',
            ecommerce_stripe_api_key = '" . escape(trim(post_value('ecommerce_stripe_api_key'))) . "',
            ecommerce_iyzipay_api_key = '" . escape(trim(post_value('ecommerce_iyzipay_api_key'))) . "',
            ecommerce_iyzipay_secret_key = '" . escape($gateway_secret_values['ecommerce_iyzipay_secret_key']) . "',
            ecommerce_iyzipay_installment = '" . escape(post_value('ecommerce_iyzipay_installment')) . "',
            ecommerce_iyzipay_threeds = '" . escape(post_value('ecommerce_iyzipay_threeds')) . "',
            ecommerce_pay_with_iyzico = '" . escape(post_value('ecommerce_pay_with_iyzico')) . "',
            ecommerce_surcharge_percentage = '" . escape(pg_settings_surcharge_percentage(post_value('ecommerce_surcharge_percentage'))) . "',
            ecommerce_paypal_express_checkout = '" . escape(post_value('ecommerce_paypal_express_checkout')) . "',
            ecommerce_paypal_express_checkout_transaction_type = '" . escape(post_value('ecommerce_paypal_express_checkout_transaction_type')) . "',
            ecommerce_paypal_express_checkout_mode = '" . escape(post_value('ecommerce_paypal_express_checkout_mode')) . "',
            ecommerce_paypal_express_checkout_api_username = '" . escape(trim(post_value('ecommerce_paypal_express_checkout_api_username'))) . "',
            ecommerce_paypal_express_checkout_api_password = '" . escape($gateway_secret_values['ecommerce_paypal_express_checkout_api_password']) . "',
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
            affiliate_default_commission_rate = '" . escape(pg_parse_commission_rate(post_value('affiliate_default_commission_rate'))) . "',
            affiliate_automatic_approval = '" . escape(post_value('affiliate_automatic_approval')) . "',
            affiliate_contact_group_id = '" . escape(post_value('affiliate_contact_group_id')) . "',
            affiliate_email_address = '" . escape(post_value('affiliate_email_address')) . "',
            affiliate_group_offer_id = '" . escape(post_value('affiliate_group_offer_id')) . "',
            last_modified_user_id = '" . USER_ID . "',
            last_modified_timestamp = UNIX_TIMESTAMP()");

    // The carriers' API applications are kept with the connected accounts'
    // applications, the secrets sealed (includes/fn/carrier_api.php).
    pg_carrier_api_settings_save($_POST, USER_ID, $liveform);

    pg_payment_settings_save($payment_driver_decisions, USER_ID);

    // Stored after the card, then the connection test that was asked for.
    pg_label_settings_save($label_driver_decisions, USER_ID, $liveform);



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
        $query = "INSERT INTO next_order_number VALUES (?)";
        $result = db_q($query, array((string) $ecommerce_next_order_number)) or output_error('Query failed.');
        
        // release lock on table
        $query = "UNLOCK TABLES";
        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
    }

    // Product feeds: their own JSON column, written after the screen's
    // columns. A new address that cannot be shown again (no site key) is shown
    // once here; a build asked for, or caused by a feed just switched on, is
    // reported the same way.
    if ($product_feeds_save_ready) {
        $product_feeds_offered = pg_product_feed_screen_groups();
        $product_feeds_current = pg_product_feed_settings();
        $product_feeds_enabled = array();

        foreach (array_keys(pg_product_feed_formats()) as $product_feeds_format) {
            $product_feeds_enabled[$product_feeds_format] = (post_value('product_feed_' . $product_feeds_format) == '1');
        }

        $product_feeds_new_address = (string) post_value('product_feed_new_address');

        $product_feeds_saved = pg_product_feed_save_from_screen(array(
            'seller_name'        => (string) post_value('product_feed_seller_name'),
            'seller_url'         => (string) post_value('product_feed_seller_url'),
            'privacy_policy_url' => (string) post_value('product_feed_privacy_policy_url'),
            'terms_url'          => (string) post_value('product_feed_terms_url'),
            'default_brand'      => (string) post_value('product_feed_default_brand'),
            // The card offers no list while no catalog shows a group (or
            // e-commerce is off); the stored choice is kept until it does.
            'excluded_group_ids' => $product_feeds_offered
                ? pg_product_feed_posted_groups(post_value('product_feed_excluded_groups'), array_keys($product_feeds_offered))
                : $product_feeds_current['excluded_group_ids'],
            'enabled'            => $product_feeds_enabled,
            'new_address'        => isset($product_feeds_enabled[$product_feeds_new_address]) ? $product_feeds_new_address : '',
            'build'              => (bool) post_value('product_feed_build'),
        ));

        $product_feeds_texts = pg_product_feed_format_texts();

        foreach ($product_feeds_saved['issued'] as $product_feeds_format => $product_feeds_token) {
            $liveform->add_notice(lang(array('string' => 'The address of {var:1} is {var:2} - note it now: this site has no key to show it again.', 'vars' => array($product_feeds_texts[$product_feeds_format]['label'], h(pg_product_feed_url($product_feeds_token))))));
        }

        if ($product_feeds_saved['renewed'] !== '') {
            $liveform->add_notice(lang(array('string' => '{var:1} has a new address; the old one no longer works.', 'vars' => array($product_feeds_texts[$product_feeds_saved['renewed']]['label']))));
        }

        if (is_array($product_feeds_saved['built'])) {
            if ($product_feeds_saved['built']['error'] !== '') {
                $liveform->add_notice(lang(array('string' => 'The product feeds were not built: {var:1}', 'vars' => array(pg_product_feed_error_text($product_feeds_saved['built']['error'])))));
            }

            foreach ($product_feeds_saved['built']['formats'] as $product_feeds_format => $product_feeds_result) {
                $liveform->add_notice($product_feeds_result['ok']
                    ? lang(array('string' => '{var:1} was built with {var:2} product(s).', 'vars' => array($product_feeds_texts[$product_feeds_format]['label'], (int) $product_feeds_result['count'])))
                    : lang(array('string' => '{var:1} was not built: {var:2}', 'vars' => array($product_feeds_texts[$product_feeds_format]['label'], pg_product_feed_error_text($product_feeds_result['error'])))));
            }
        }
    }

    // Google Merchant Center (includes/fn/merchant_center.php), after the
    // feeds so a Google feed switched on in the same save already has the
    // address that is registered. The messages carry Google's own words, so
    // they are escaped whole.
    if ($product_feeds_save_ready && array_key_exists('merchant_card', $_POST) && function_exists('pg_merchant_save_from_screen')) {
        $merchant_messages = pg_merchant_save_from_screen(array(
            'account'  => (string) post_value('merchant_account'),
            'country'  => (string) post_value('merchant_center_country'),
            'register' => (bool) post_value('merchant_register'),
            'check'    => (bool) post_value('merchant_check'),
            'unlink'   => (bool) post_value('merchant_unlink'),
        ));

        foreach ($merchant_messages as $merchant_message) {
            if ($merchant_message[0] === 'error') {
                $liveform->add_error(h($merchant_message[1]));
            } else {
                $liveform->add_notice(h($merchant_message[1]));
            }
        }
    }
