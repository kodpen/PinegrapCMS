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
                // Marked, not returned: the checks below mark their fields on
                // the same save, and check_form_errors() stops the write.
                $liveform->mark_error($product_feeds_field, lang('Enter a full address starting with https://, or leave the field empty.'));
            }
        }
    }


    // Stock locations (2026.11.0, includes/fn/stock_moves.php): checked here,
    // before anything on this screen is written, and stored after the card's
    // columns. Only when the card's fields were posted and the tables exist.
    $stock_locations_plan = null;

    if (array_key_exists('stock_location_card', $_POST)) {
        require_once(PG_FUNCTIONS_DIR . '/includes/fn/stock_moves.php');
    }

    if (array_key_exists('stock_location_card', $_POST) && pg_stock_ready()) {
        $stock_locations_plan = pg_stock_locations_save_plan(array(
            'name'        => isset($_POST['stock_location_name']) ? $_POST['stock_location_name'] : array(),
            'address'     => isset($_POST['stock_location_address']) ? $_POST['stock_location_address'] : array(),
            'order'       => isset($_POST['stock_location_order']) ? $_POST['stock_location_order'] : array(),
            'enabled'     => isset($_POST['stock_location_enabled']) ? $_POST['stock_location_enabled'] : array(),
            'default'     => post_value('stock_location_default'),
            'new_name'    => post_value('stock_location_new_name'),
            'new_address' => post_value('stock_location_new_address'),
        ), pg_stock_locations());

        foreach ($stock_locations_plan['errors'] as $stock_locations_field => $stock_locations_error) {
            $liveform->mark_error($stock_locations_field, ($stock_locations_error === 'default_disabled')
                ? lang('The default location cannot be turned off. Make another location the default first.')
                : lang('Enter a name of at most 100 characters.'));
        }

        if ($stock_locations_plan['errors']) {
            return;
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

    // UPS, FedEx or Verify US Addresses (USPS) switched on while the
    // carrier's keys are not on its page of Connections yet is refused; a
    // switch already on saves.
    foreach (array_keys(pg_carrier_api_definitions()) as $carrier_api) {
        $carrier_api_switch = pg_carrier_api_switch($carrier_api);
        $carrier_api_refusal = pg_carrier_api_choice_refusal($carrier_api, (post_value($carrier_api_switch) == 1), pg_carrier_api_switched_on($carrier_api), pg_carrier_api_ready($carrier_api));

        if ($carrier_api_refusal !== '') {
            $liveform->mark_error($carrier_api_switch, $carrier_api_refusal);
        }
    }

    // The gateways' keys, PayPal Express Checkout's and Givex's are written
    // on their pages of Connections, never here.

    // PayPal Express Checkout or Givex switched on while its keys are not on
    // its page of Connections yet is refused; a switch already on saves.
    $method_connection_stored = pg_payment_method_connection_stored();

    foreach (pg_payment_method_connections() as $method_id => $method_definition) {
        $method_refusal = pg_payment_method_choice_refusal($method_id, (post_value($method_definition['switch']) == 1), !empty($method_connection_stored[$method_definition['switch']]), $method_connection_stored);

        if ($method_refusal !== '') {
            $liveform->mark_error($method_definition['switch'], $method_refusal);
        }
    }

    // A card gateway newly chosen whose keys are not on its page of
    // Connections yet is refused; an unchanged choice always saves.
    $gateway_choice_refusal = pg_payment_gateway_choice_refusal(
        (string) post_value('ecommerce_payment_gateway'),
        (string) db_value("SELECT ecommerce_payment_gateway FROM config LIMIT 1"),
        (post_value('ecommerce_credit_debit_card') == 1),
        pg_payment_gateway_connection_stored());

    if ($gateway_choice_refusal !== '') {
        $liveform->mark_error('ecommerce_payment_gateway', $gateway_choice_refusal);
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

    // The address autocomplete card (includes/fn/address_autocomplete.php).
    $addr_settings_decision = pg_addr_settings_check($_POST, $liveform);

    // Next Order Number: written only when it was changed on this screen
    // (the value the screen was opened with comes back in a hidden field),
    // so a save of another field never sets back a counter that orders have
    // moved since; and only above every number already given.
    $next_order_number_write = false;

    if (isset($_POST['ecommerce_next_order_number'])) {
        $next_order_number_posted = trim((string) $_POST['ecommerce_next_order_number']);
        $next_order_number_was = isset($_POST['ecommerce_next_order_number_was']) ? trim((string) $_POST['ecommerce_next_order_number_was']) : null;

        if ($next_order_number_posted !== $next_order_number_was) {
            $next_order_number_refusal = pg_next_order_number_refusal($next_order_number_posted, (int) db_value("SELECT MAX(order_number) FROM orders"));

            if ($next_order_number_refusal !== '') {
                $liveform->mark_error('ecommerce_next_order_number', $next_order_number_refusal);
            } else {
                $next_order_number_write = true;
            }
        }
    }

    // Referral programme: every box checked before any write.
    $referral_decision = null;

    if (array_key_exists('referral_reward', $_POST)) {
        $referral_decision = pg_referral_settings_check($_POST, function ($offer_id) {
            return (int) db_value("SELECT id FROM offers WHERE id = '" . (int) $offer_id . "'") === (int) $offer_id;
        });
        $referral_errors = pg_referral_settings_errors();

        foreach ($referral_decision['errors'] as $referral_field => $referral_error) {
            $liveform->mark_error($referral_field, $referral_errors[$referral_error]);
        }
    }

    if ($liveform->check_form_errors()) {
        return;
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

    // The protected currency box is drawn only while Multiple Currencies is
    // on; a save without it keeps the stored currency instead of blanking it.
    $sql_iyzipay_protected_currency = array_key_exists('iyzipay_protected_currency_code', $_POST)
        ? "iyzipay_protected_currency_code = '" . escape(post_value('iyzipay_protected_currency_code')) . "',"
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
            " . $sql_iyzipay_protected_currency . "
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
            ecommerce_iyzipay_installment = '" . escape(post_value('ecommerce_iyzipay_installment')) . "',
            ecommerce_iyzipay_threeds = '" . escape(post_value('ecommerce_iyzipay_threeds')) . "',
            ecommerce_pay_with_iyzico = '" . escape(post_value('ecommerce_pay_with_iyzico')) . "',
            ecommerce_surcharge_percentage = '" . escape(pg_settings_surcharge_percentage(post_value('ecommerce_surcharge_percentage'))) . "',
            ecommerce_paypal_express_checkout = '" . escape(post_value('ecommerce_paypal_express_checkout')) . "',
            ecommerce_paypal_express_checkout_transaction_type = '" . escape(post_value('ecommerce_paypal_express_checkout_transaction_type')) . "',
            ecommerce_paypal_express_checkout_mode = '" . escape(post_value('ecommerce_paypal_express_checkout_mode')) . "',
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

    // The carriers' environments; their client IDs and secrets are typed on
    // their pages of Connections (includes/fn/carrier_api.php).
    pg_carrier_api_settings_save($_POST, USER_ID, $liveform);

    pg_payment_settings_save($payment_driver_decisions, USER_ID);

    // Stored after the card, then the connection test that was asked for.
    pg_label_settings_save($label_driver_decisions, USER_ID, $liveform);

    // The address autocomplete switch: the chosen provider on, the others off.
    pg_addr_settings_save($addr_settings_decision, USER_ID, $liveform);



    // The Next Order Number, when it was changed and accepted above. The lock
    // is the one order completion takes before it reads the counter.
    if ($next_order_number_write) {
        $ecommerce_next_order_number = (string) (int) $next_order_number_posted;

        $query = "LOCK TABLES next_order_number WRITE";
        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');

        // Read again under the lock: an order may have taken a number since
        // the check above, and its number must not be given out twice.
        $next_order_number_row = db_item("SELECT next_order_number FROM next_order_number LIMIT 1");
        $next_order_number_current = is_array($next_order_number_row) ? (int) $next_order_number_row['next_order_number'] : null;
        $next_order_number_moved = pg_next_order_number_set_back($ecommerce_next_order_number, $next_order_number_was, $next_order_number_current);

        // Set back, it writes nothing; the warning waits for the unlock, as
        // no other table may be touched under it.
        if (!$next_order_number_moved) {
            if ($next_order_number_current !== null) {
                db_q("UPDATE next_order_number SET next_order_number = ?", array($ecommerce_next_order_number)) or output_error('Query failed.');
            } else {
                db_q("INSERT INTO next_order_number VALUES (?)", array($ecommerce_next_order_number)) or output_error('Query failed.');
            }
        }

        // release lock on table
        $query = "UNLOCK TABLES";
        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');

        if ($next_order_number_moved) {
            $liveform->add_warning(lang(array('string' => 'Orders took numbers while this screen was open, so the Next Order Number was not changed; it is {var:1} now.', 'vars' => array($next_order_number_current))));
        }
    }

    // Points to store credit: two config_text rows, only when the reward
    // program card was posted. '' (empty or zero) turns conversion off.
    if (array_key_exists('credit_points_per_unit', $_POST)) {
        pg_config_text_save('credit_points_per_unit', pg_credit_points_parse_rate(post_value('credit_points_per_unit')));
        $credit_points_smallest_minor = pg_credit_points_parse_smallest(post_value('credit_points_smallest'));
        pg_config_text_save('credit_points_smallest', ($credit_points_smallest_minor > 0) ? (string) $credit_points_smallest_minor : '');
    }

    // Referral programme: config_text rows, read by pg_referral_settings().
    if (($referral_decision !== null) && !$referral_decision['errors']) {
        foreach ($referral_decision['values'] as $referral_name => $referral_value) {
            pg_config_text_save($referral_name, $referral_value);
        }
    }

    // The checked pack before a label: a config_text row, '1' on, '' (no
    // row) off; written only when the Shipping card drew the switch.
    if (post_value('pack_before_label_card') && pg_schema_has('config_text')) {
        require_once(PG_FUNCTIONS_DIR . '/includes/fn/config_text.php');
        pg_config_text_save('pack_before_label', post_value('pack_before_label') ? '1' : '');
    }

    // Rates by the buyer's country: a config_text row, '1' on, '' (no row)
    // off; written only when the Store card drew the switch.
    if (post_value('ecommerce_tax_by_country_card') && pg_schema_has('config_text')) {
        require_once(PG_FUNCTIONS_DIR . '/includes/fn/config_text.php');
        pg_config_text_save('ecommerce_tax_by_country', post_value('ecommerce_tax_by_country') ? '1' : '');
    }

    // The bonus on refunds given as store credit: a config_text row, '' is
    // none.
    if (array_key_exists('credit_refund_bonus', $_POST)) {
        pg_config_text_save('credit_refund_bonus', pg_credit_refund_parse_bonus(post_value('credit_refund_bonus')));
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
            // Only a market the card offered is taken; without markets the
            // field is not drawn and the stored choice stays.
            'market_id'          => array_key_exists('product_feed_market_id', $_POST)
                ? pg_product_feed_posted_market(post_value('product_feed_market_id'), pg_product_feed_market_choices())
                : $product_feeds_current['market_id'],
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
                $liveform->add_warning(lang(array('string' => 'The product feeds were not built: {var:1}', 'vars' => array(pg_product_feed_error_text($product_feeds_saved['built']['error'])))));
            }

            // A format that was not built is a warning: beside the green
            // "saved" a failure read as one more success.
            foreach ($product_feeds_saved['built']['formats'] as $product_feeds_format => $product_feeds_result) {
                if ($product_feeds_result['ok']) {
                    $liveform->add_notice(lang(array('string' => '{var:1} was built with {var:2} product(s).', 'vars' => array($product_feeds_texts[$product_feeds_format]['label'], (int) $product_feeds_result['count']))));
                } else {
                    $liveform->add_warning(lang(array('string' => '{var:1} was not built: {var:2}', 'vars' => array($product_feeds_texts[$product_feeds_format]['label'], pg_product_feed_error_text($product_feeds_result['error'])))));
                }
            }
        }
    }

    // Stock locations, checked above.
    if ($stock_locations_plan !== null) {
        if (!pg_stock_locations_save($stock_locations_plan)) {
            $liveform->add_warning(lang('The stock locations could not all be saved.'));
        }
    }
