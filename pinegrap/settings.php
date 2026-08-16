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
 *              2016–2025 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');

$user = validate_user();
validate_area_access($user, 'manager');

include_once('liveform.class.php');
$liveform = new liveform('settings');

if (!$_POST) {

    $query = "SELECT * FROM config";
    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
    $row = mysqli_fetch_assoc($result);
    
    $url_scheme = $row['url_scheme'];
    $hostname = $row['hostname'];
    $email_address = $row['email_address'];
    $title = $row['title'];
    $meta_description = $row['meta_description'];
    $mobile = $row['mobile'];
    $search_type = $row['search_type'];
    $social_networking = $row['social_networking'];
    $social_networking_type = $row['social_networking_type'];
    $social_networking_facebook = $row['social_networking_facebook'];
    $social_networking_twitter = $row['social_networking_twitter'];
    $social_networking_linkedin = $row['social_networking_linkedin'];
    $social_networking_whatsapp = $row['social_networking_whatsapp'];
    $social_networking_telegram = $row['social_networking_telegram'];
    $social_networking_pinterest = $row['social_networking_pinterest'];
    $social_networking_reddit = $row['social_networking_reddit'];
    $social_networking_email = $row['social_networking_email'];
    $social_networking_code = $row['social_networking_code'];
    $captcha = $row['captcha'];
    $auto_dialogs = $row['auto_dialogs'];
    $mass_deletion = $row['mass_deletion'];
    $strong_password = $row['strong_password'];
    $password_hint = $row['password_hint'];
    $remember_me = $row['remember_me'];
    $forgot_password_link = $row['forgot_password_link'];
    $proxy_address = $row['proxy_address'];
    $badge_label = $row['badge_label'];
    $timezone = $row['timezone'];
    $date_format = $row['date_format'];
    $time_format = $row['time_format'];
    $organization_name = $row['organization_name'];
    $organization_address_1 = $row['organization_address_1'];
    $organization_address_2 = $row['organization_address_2'];
    $organization_city = $row['organization_city'];
    $organization_state = $row['organization_state'];
    $organization_zip_code = $row['organization_zip_code'];
    $organization_country = $row['organization_country'];
    $opt_in_label = $row['opt_in_label'];
    $plain_text_email_campaign_footer = $row['plain_text_email_campaign_footer'];
    $visitor_tracking = $row['visitor_tracking'];
    $allowed_bots = isset($row['allowed_bots']) ? $row['allowed_bots'] : '';
    $block_unknown_bots = isset($row['block_unknown_bots']) ? (int)$row['block_unknown_bots'] : 1;

    // ── Firewall settings ────────────────────────────────────────────────
    // Every read is guarded: an install that has not run the 2026.2.4 upgrade
    // still renders this screen, it just shows the defaults.
    $perf_monitor             = isset($row['perf_monitor']) ? (int)$row['perf_monitor'] : 1;
    $waf_enabled              = isset($row['waf_enabled']) ? (int)$row['waf_enabled'] : 0;
    $waf_mode                 = isset($row['waf_mode']) ? $row['waf_mode'] : 'monitor';
    $waf_sensitivity          = isset($row['waf_sensitivity']) ? $row['waf_sensitivity'] : 'medium';
    $waf_signature_scan       = isset($row['waf_signature_scan']) ? (int)$row['waf_signature_scan'] : 1;
    $waf_rate_limit           = isset($row['waf_rate_limit']) ? (int)$row['waf_rate_limit'] : 1;
    $waf_rate_limit_requests  = isset($row['waf_rate_limit_requests']) ? (int)$row['waf_rate_limit_requests'] : 300;
    $waf_rate_limit_sensitive = isset($row['waf_rate_limit_sensitive']) ? (int)$row['waf_rate_limit_sensitive'] : 30;
    $waf_auto_ban             = isset($row['waf_auto_ban']) ? (int)$row['waf_auto_ban'] : 1;
    $waf_auto_ban_threshold   = isset($row['waf_auto_ban_threshold']) ? (int)$row['waf_auto_ban_threshold'] : 5;
    $waf_auto_ban_minutes     = isset($row['waf_auto_ban_minutes']) ? (int)$row['waf_auto_ban_minutes'] : 60;
    $waf_block_attack_tools   = isset($row['waf_block_attack_tools']) ? (int)$row['waf_block_attack_tools'] : 1;
    $waf_verify_bots          = isset($row['waf_verify_bots']) ? (int)$row['waf_verify_bots'] : 1;
    $waf_trusted_proxies      = isset($row['waf_trusted_proxies']) ? $row['waf_trusted_proxies'] : '';
    $waf_exclusions           = isset($row['waf_exclusions']) ? $row['waf_exclusions'] : '';
    $waf_blocked_agents       = isset($row['waf_blocked_agents']) ? $row['waf_blocked_agents'] : '';
    $waf_log_retention_days   = isset($row['waf_log_retention_days']) ? (int)$row['waf_log_retention_days'] : 14;
    $waf_log_max_rows         = isset($row['waf_log_max_rows']) ? (int)$row['waf_log_max_rows'] : 20000;
    $waf_schema_ready         = isset($row['waf_enabled']);
    $tracking_code_duration = $row['tracking_code_duration'];
    $pay_per_click_flag = $row['pay_per_click_flag'];
    $stats_url = $row['stats_url'];
    $google_analytics = $row['google_analytics'];
    $google_analytics_web_property_id = $row['google_analytics_web_property_id'];
    $page_editor_version = $row['page_editor_version'];
    $page_editor_font = $row['page_editor_font'];
    $page_editor_font_size = $row['page_editor_font_size'];
    $page_editor_font_style = $row['page_editor_font_style'];
    $page_editor_font_color = $row['page_editor_font_color'];
    $page_editor_background_color = $row['page_editor_background_color'];
    $registration_contact_group_id = $row['registration_contact_group_id'];
    $registration_email_address = $row['registration_email_address'];
    $member_id_label = $row['member_id_label'];
    $membership_contact_group_id = $row['membership_contact_group_id'];
    $membership_email_address = $row['membership_email_address'];
    $membership_expiration_warning_email = $row['membership_expiration_warning_email'];
    $membership_expiration_warning_email_subject = $row['membership_expiration_warning_email_subject'];
    $membership_expiration_warning_email_page_id = $row['membership_expiration_warning_email_page_id'];
    $membership_expiration_warning_email_days_before_expiration = $row['membership_expiration_warning_email_days_before_expiration'];
    $ecommerce_on_or_off = $row['ecommerce'];
    $ecommerce_multicurrency = $row['ecommerce_multicurrency'];
    $ecommerce_tax = $row['ecommerce_tax'];
    $ecommerce_tax_exempt = $row['ecommerce_tax_exempt'];
    $ecommerce_tax_exempt_label = $row['ecommerce_tax_exempt_label'];
    $ecommerce_shipping = $row['ecommerce_shipping'];
    $ecommerce_recipient_mode = $row['ecommerce_recipient_mode'];
    $usps_user_id = $row['usps_user_id'];
    $ecommerce_address_verification = $row['ecommerce_address_verification'];
    $ecommerce_address_verification_enforcement_type = $row['ecommerce_address_verification_enforcement_type'];
    $ups = $row['ups'];
    $ups_key = $row['ups_key'];
    $ups_user_id = $row['ups_user_id'];
    $ups_password = $row['ups_password'];
    $ups_account = $row['ups_account'];
    $fedex = $row['fedex'];
    $fedex_key = $row['fedex_key'];
    $fedex_password = $row['fedex_password'];
    $fedex_account = $row['fedex_account'];
    $fedex_meter = $row['fedex_meter'];
    $ecommerce_product_restriction_message = $row['ecommerce_product_restriction_message'];
    $ecommerce_no_shipping_methods_message = $row['ecommerce_no_shipping_methods_message'];
    $ecommerce_end_of_day_time = $row['ecommerce_end_of_day_time'];
    $ecommerce_email_address = $row['ecommerce_email_address'];
    $ecommerce_gift_card = $row['ecommerce_gift_card'];
    $ecommerce_gift_card_validity_days = $row['ecommerce_gift_card_validity_days'];
    $ecommerce_givex = $row['ecommerce_givex'];
    $ecommerce_givex_primary_hostname = $row['ecommerce_givex_primary_hostname'];
    $ecommerce_givex_secondary_hostname = $row['ecommerce_givex_secondary_hostname'];
    $ecommerce_givex_user_id = $row['ecommerce_givex_user_id'];
    $ecommerce_givex_password = $row['ecommerce_givex_password'];
    $ecommerce_credit_debit_card = $row['ecommerce_credit_debit_card'];
    $ecommerce_american_express = $row['ecommerce_american_express'];
    $ecommerce_diners_club = $row['ecommerce_diners_club'];
    $ecommerce_discover_card = $row['ecommerce_discover_card'];
    $ecommerce_mastercard = $row['ecommerce_mastercard'];
    $ecommerce_visa = $row['ecommerce_visa'];
    $ecommerce_troy = $row['ecommerce_troy'];
    $ecommerce_show_product_images = $row['ecommerce_show_product_images'];
    $barcode_enabled        = $row['barcode_enabled']        ?? 0;
    $barcode_default_type   = $row['barcode_default_type']   ?? 'CODE128';
    $barcode_label_width    = $row['barcode_label_width']    ?? 60;
    $barcode_label_height   = $row['barcode_label_height']   ?? 40;
    $barcode_label_template = $row['barcode_label_template'] ?? '';
    $ecommerce_payment_gateway = $row['ecommerce_payment_gateway'];
    $ecommerce_payment_gateway_transaction_type = $row['ecommerce_payment_gateway_transaction_type'];
    $ecommerce_payment_gateway_mode = $row['ecommerce_payment_gateway_mode'];
    $ecommerce_authorizenet_api_login_id = $row['ecommerce_authorizenet_api_login_id'];
    $ecommerce_authorizenet_transaction_key = $row['ecommerce_authorizenet_transaction_key'];
    $ecommerce_clearcommerce_client_id = $row['ecommerce_clearcommerce_client_id'];
    $ecommerce_clearcommerce_user_id = $row['ecommerce_clearcommerce_user_id'];
    $ecommerce_clearcommerce_password = $row['ecommerce_clearcommerce_password'];
    $ecommerce_first_data_global_gateway_store_number = $row['ecommerce_first_data_global_gateway_store_number'];
    $ecommerce_first_data_global_gateway_pem_file_name = $row['ecommerce_first_data_global_gateway_pem_file_name'];
    $ecommerce_paypal_payflow_pro_partner = $row['ecommerce_paypal_payflow_pro_partner'];
    $ecommerce_paypal_payflow_pro_merchant_login = $row['ecommerce_paypal_payflow_pro_merchant_login'];
    $ecommerce_paypal_payflow_pro_user = $row['ecommerce_paypal_payflow_pro_user'];
    $ecommerce_paypal_payflow_pro_password = $row['ecommerce_paypal_payflow_pro_password'];
    $ecommerce_paypal_payments_pro_api_username = $row['ecommerce_paypal_payments_pro_api_username'];
    $ecommerce_paypal_payments_pro_api_password = $row['ecommerce_paypal_payments_pro_api_password'];
    $ecommerce_paypal_payments_pro_api_signature = $row['ecommerce_paypal_payments_pro_api_signature'];
    $ecommerce_sage_merchant_id = $row['ecommerce_sage_merchant_id'];
    $ecommerce_sage_merchant_key = $row['ecommerce_sage_merchant_key'];
    $ecommerce_stripe_api_key = $row['ecommerce_stripe_api_key'];
	$ecommerce_iyzipay_api_key = $row['ecommerce_iyzipay_api_key'];
	$ecommerce_iyzipay_secret_key = $row['ecommerce_iyzipay_secret_key'];
	$ecommerce_iyzipay_threeds = $row['ecommerce_iyzipay_threeds'];
    $ecommerce_pay_with_iyzico = $row['ecommerce_pay_with_iyzico'];
    $ecommerce_surcharge_percentage = $row['ecommerce_surcharge_percentage'];
    $ecommerce_paypal_express_checkout = $row['ecommerce_paypal_express_checkout'];
    $ecommerce_paypal_express_checkout_transaction_type = $row['ecommerce_paypal_express_checkout_transaction_type'];
    $ecommerce_paypal_express_checkout_mode = $row['ecommerce_paypal_express_checkout_mode'];
    $ecommerce_paypal_express_checkout_api_username = $row['ecommerce_paypal_express_checkout_api_username'];
    $ecommerce_paypal_express_checkout_api_password = $row['ecommerce_paypal_express_checkout_api_password'];
    $ecommerce_paypal_express_checkout_api_signature = $row['ecommerce_paypal_express_checkout_api_signature'];
    $ecommerce_offline_payment = $row['ecommerce_offline_payment'];
    $ecommerce_offline_payment_only_specific_orders = $row['ecommerce_offline_payment_only_specific_orders'];
    $ecommerce_private_folder_id = $row['ecommerce_private_folder_id'];
    $ecommerce_retrieve_order_next_page_id = $row['ecommerce_retrieve_order_next_page_id'];
    $ecommerce_reward_program = $row['ecommerce_reward_program'];
    $ecommerce_reward_program_points = $row['ecommerce_reward_program_points'];
    $ecommerce_reward_program_membership = $row['ecommerce_reward_program_membership'];
    $ecommerce_reward_program_membership_days = $row['ecommerce_reward_program_membership_days'];
    $ecommerce_reward_program_email = $row['ecommerce_reward_program_email'];
    $ecommerce_reward_program_email_bcc_email_address = $row['ecommerce_reward_program_email_bcc_email_address'];
    $ecommerce_reward_program_email_subject = $row['ecommerce_reward_program_email_subject'];
    $ecommerce_reward_program_email_page_id = $row['ecommerce_reward_program_email_page_id'];
    $ecommerce_custom_product_field_1_label = $row['ecommerce_custom_product_field_1_label'];
    $ecommerce_custom_product_field_2_label = $row['ecommerce_custom_product_field_2_label'];
    $ecommerce_custom_product_field_3_label = $row['ecommerce_custom_product_field_3_label'];
    $ecommerce_custom_product_field_4_label = $row['ecommerce_custom_product_field_4_label'];
    $forms = $row['forms'];
    $calendars = $row['calendars'];
    $ads = $row['ads'];
    $affiliate_program = $row['affiliate_program'];
    $affiliate_default_commission_rate = $row['affiliate_default_commission_rate'];
    $affiliate_automatic_approval = $row['affiliate_automatic_approval'];
    $affiliate_contact_group_id = $row['affiliate_contact_group_id'];
    $affiliate_email_address = $row['affiliate_email_address'];
    $affiliate_group_offer_id = $row['affiliate_group_offer_id'];
    $additional_sitemap_content = $row['additional_sitemap_content'];
    $additional_robots_content = $row['additional_robots_content'];
    $debug = $row['debug'];
    $last_modified_user_id = $row['last_modified_user_id'];
    $last_modified_timestamp = $row['last_modified_timestamp'];
	$ecommerce_iyzipay_installment = $row['ecommerce_iyzipay_installment'];
    $custom_css = $row['custom_css'];
	if($ecommerce_iyzipay_installment){
		$ecommerce_iyzipay_installment_option_1_selected='';
		$ecommerce_iyzipay_installment_option_2_selected='';
		$ecommerce_iyzipay_installment_option_3_selected='';
		$ecommerce_iyzipay_installment_option_6_selected='';
		$ecommerce_iyzipay_installment_option_9_selected='';
		$ecommerce_iyzipay_installment_option_12_selected='';

		if($ecommerce_iyzipay_installment == '1'){$ecommerce_iyzipay_installment_option_1_selected ='selected="selected"';}
		if($ecommerce_iyzipay_installment == '2'){$ecommerce_iyzipay_installment_option_2_selected ='selected="selected"';}
		if($ecommerce_iyzipay_installment == '3'){$ecommerce_iyzipay_installment_option_3_selected ='selected="selected"';}
		if($ecommerce_iyzipay_installment == '6'){$ecommerce_iyzipay_installment_option_6_selected ='selected="selected"';}
		if($ecommerce_iyzipay_installment == '9'){$ecommerce_iyzipay_installment_option_9_selected ='selected="selected"';}
		if($ecommerce_iyzipay_installment == '12'){$ecommerce_iyzipay_installment_option_12_selected ='selected="selected"';}

        $ecommerce_iyzipay_installment_options ='<option value="1" '.$ecommerce_iyzipay_installment_option_1_selected.'>' . lang('No Installment') . '</option><option value="2" '.$ecommerce_iyzipay_installment_option_2_selected.'>' . lang(array('string'=>'Maximum {var:1}','vars'=>array('2') )) . '</option><option value="3" '.$ecommerce_iyzipay_installment_option_3_selected.'>' . lang(array('string'=>'Maximum {var:1}','vars'=>array('3') )) . '</option><option value="6" '.$ecommerce_iyzipay_installment_option_6_selected.'>' . lang(array('string'=>'Maximum {var:1}','vars'=>array('6') )) . '</option><option value="9" '.$ecommerce_iyzipay_installment_option_9_selected.'>' . lang(array('string'=>'Maximum {var:1}','vars'=>array('9') )) . '</option><option value="12" '.$ecommerce_iyzipay_installment_option_12_selected.'>' . lang(array('string'=>'Maximum {var:1}','vars'=>array('12') )) . '</option>';

	}
    $strutured_data = $row['strutured_data'];
    $advanced_visual_effects = $row['advanced_visual_effects'];
    $enable_parasut = $row['enable_parasut'];
    $parasut_tc_in_field = $row['parasut_tc_in_field'];
    $parasut_client_id = $row['parasut_client_id'] ?? '';
    $parasut_client_secret = $row['parasut_client_secret'] ?? '';
    $parasut_username = $row['parasut_username'] ?? '';
    $parasut_password = $row['parasut_password'] ?? '';
    $parasut_company_id = $row['parasut_company_id'] ?? '';
    $parasut_use_sandbox = $row['parasut_use_sandbox'] ?? 0;
    $parasut_default_product_id  = $row['parasut_default_product_id']  ?? '';
    $parasut_default_warehouse_id = $row['parasut_default_warehouse_id'] ?? '';
    $enable_iyzipay_protected_currency = $row['enable_iyzipay_protected_currency'];
    $iyzipay_protected_currency_code = $row['iyzipay_protected_currency_code'];
    $indexnow_key = $row['indexnow_key'];




    $parasut_tc_in_field_option_1_selected = ($parasut_tc_in_field == 'do not use' || $parasut_tc_in_field == '') ? 'selected="selected"' : '';
    $parasut_tc_in_field_option_2_selected = ($parasut_tc_in_field == 'custom_field_1') ? 'selected="selected"' : '';
    $parasut_tc_in_field_option_3_selected = ($parasut_tc_in_field == 'custom_field_2') ? 'selected="selected"' : '';
    $parasut_tc_in_field_option_4_selected = ($parasut_tc_in_field == 'tax_number')     ? 'selected="selected"' : '';

    $parasut_tc_in_field_options =
        '<option value="do not use" ' . $parasut_tc_in_field_option_1_selected . '>' . lang('Do Not Use') . '</option>' .
        '<option value="custom_field_1" '  . $parasut_tc_in_field_option_2_selected . '>custom_field_1</option>' .
        '<option value="custom_field_2" '  . $parasut_tc_in_field_option_3_selected . '>custom_field_2</option>' .
        '<option value="tax_number" '       . $parasut_tc_in_field_option_4_selected . '>contacts.tax_number (' . lang('VKN / TCKN') . ')</option>';

    $parasut_use_sandbox_checked = ($parasut_use_sandbox == 1) ? ' checked="checked"' : '';


    $output_enforcement = '';
    // if there is enforcement tell user its
    if( defined('ENFORCEMENT_SOFTWARE_LANGUAGE') ){
        $output_enforcement = '(' . lang('Enforcement: ') . ENFORCEMENT_SOFTWARE_LANGUAGE . ')';
    }
    $software_language = $row['software_language'];
    if($software_language != NULL){
        $selected_tr = '';
        $selected_en = '';
        if($software_language == 'tr'){
            $selected_tr ='selected="selected"';
        }
        if($software_language == 'en'){
            $selected_en ='selected="selected"';
        }
        $select_software_language_options ='<option value="en" '.$selected_en.'>' . lang('English') . '</option><option value="tr" '.$selected_tr.'>' . lang('Turkish') . '</option>';
        $output_software_language ='
        <div class="col-12 col-md-4 my-2">
            <label class="form-label" for="software_language">' . lang('Software Language') . $output_enforcement . '</label>
            <select class="form-select" id="software_language" name="software_language">'.$select_software_language_options.'</select>
        </div>';
    }

    

    $last_modified = '';

    if ($last_modified_timestamp) {

        $last_modified .= lang('Last modified') . ' ' . get_relative_time(array('timestamp' => $last_modified_timestamp)) . ' ';

        if ($last_modified_user_id) {

            $last_modified_username = db_value(
                "SELECT user_username FROM user WHERE user_id = '$last_modified_user_id'");

            if ($last_modified_username != '') {
                $last_modified .= lang(array('string'=>'by {var:1}','vars'=>array( h($last_modified_username) ) ) );
            }

        }

    }

    if ($url_scheme == 'https://') {
        $secure_mode_checked = ' checked="checked"';
    } else {
        $secure_mode_checked = '';
    }

    if ($forgot_password_link == 1) {
        $forgot_password_link_checked = ' checked="checked"';
    } else {
        $forgot_password_link_checked = '';
    }

    // If the search type is "simple", then select that radio button.
    if ($search_type == 'simple') {
        $search_type_simple_checked = ' checked="checked"';
        $search_type_advanced_checked = '';
    
    // Otherwise the search type is "advanced", so select it.
    } else {
        $search_type_simple_checked = '';
        $search_type_advanced_checked = ' checked="checked"';
    }

    $mobile_checked = '';

    // If mobile is enabled, then check check box.
    if ($mobile == 1) {
        $mobile_checked = ' checked="checked"';
    }

    // Assume that social networking should not be checked until we find out otherwise.
    $social_networking_checked = '';

    // If social networking is enabled, then check check box and determine which other rows should be shown.
    if ($social_networking == 1) {
        $social_networking_checked = ' checked="checked"';
    }
    
    // If the social networking type is "simple", then select that radio button.
    if ($social_networking_type == 'simple') {
        $social_networking_type_simple_checked = ' checked="checked"';
        $social_networking_type_advanced_checked = '';
    
    // Otherwise the social networking type is "advanced", so select it.
    } else {
        $social_networking_type_simple_checked = '';
        $social_networking_type_advanced_checked = ' checked="checked"';
    }
    
    // if facebook is enabled, then check check box
    if ($social_networking_facebook == 1) {
        $social_networking_facebook_checked = ' checked="checked"';
    } else {
        $social_networking_facebook_checked = '';
    }

    // if twitter/x is enabled, then check check box
    if ($social_networking_twitter == 1) {
        $social_networking_twitter_checked = ' checked="checked"';
    } else {
        $social_networking_twitter_checked = '';
    }

    // if linkedin is enabled, then check check box
    if ($social_networking_linkedin == 1) {
        $social_networking_linkedin_checked = ' checked="checked"';
    } else {
        $social_networking_linkedin_checked = '';
    }

    // if whatsapp is enabled, then check check box
    if ($social_networking_whatsapp == 1) {
        $social_networking_whatsapp_checked = ' checked="checked"';
    } else {
        $social_networking_whatsapp_checked = '';
    }

    // if telegram is enabled, then check check box
    if ($social_networking_telegram == 1) {
        $social_networking_telegram_checked = ' checked="checked"';
    } else {
        $social_networking_telegram_checked = '';
    }

    // if pinterest is enabled, then check check box
    if ($social_networking_pinterest == 1) {
        $social_networking_pinterest_checked = ' checked="checked"';
    } else {
        $social_networking_pinterest_checked = '';
    }

    // if reddit is enabled, then check check box
    if ($social_networking_reddit == 1) {
        $social_networking_reddit_checked = ' checked="checked"';
    } else {
        $social_networking_reddit_checked = '';
    }

    // if email is enabled, then check check box
    if ($social_networking_email == 1) {
        $social_networking_email_checked = ' checked="checked"';
    } else {
        $social_networking_email_checked = '';
    }
    
    if ($captcha == 1) {
        $captcha_checked = ' checked="checked"';
    } else {
        $captcha_checked = '';
    }

    if ($auto_dialogs == 1) {
        $auto_dialogs_checked = ' checked="checked"';
    } else {
        $auto_dialogs_checked = '';
    }

    if ($mass_deletion == 1) {
        $mass_deletion_checked = ' checked="checked"';
    } else {
        $mass_deletion_checked = '';
    }

    if ($strong_password == 1) {
        $strong_password_checked = ' checked="checked"';
    } else {
        $strong_password_checked = '';
    }

    if ($password_hint == 1) {
        $password_hint_checked = ' checked="checked"';
    } else {
        $password_hint_checked = '';
    }
    
    if ($remember_me == 1) {
        $remember_me_checked = ' checked="checked"';
    } else {
        $remember_me_checked = '';
    }

    if ($debug == 1) {
        $debug_checked = ' checked="checked"';
    } else {
        $debug_checked = '';
    }

    if ($strutured_data == 1) {
        $strutured_data_checked = ' checked="checked"';
    } else {
        $strutured_data_checked = '';
    }
    if ($enable_parasut == 1) {
        $enable_parasut_checked = ' checked="checked"';
    } else {
        $enable_parasut_checked = '';
    }
    if ($enable_iyzipay_protected_currency == 1) {
        $enable_iyzipay_protected_currency_checked = ' checked="checked"';
    } else {
        $enable_iyzipay_protected_currency_checked = '';
    }
    
    $output_iyzipay_protected_currency_select = '';

    if ($ecommerce_multicurrency == 1) {
        $output_iyzipay_protected_currency_select_options = '<option value="">-' . lang(array('string'=>'Select {var:1}','vars'=>array(lang('Currency') ) )) . '-</option>';
        // get all of the currency information (we dont need base currency).
        $query =
            "SELECT
                id,
                name,
                base,
                code,
                symbol
            FROM currencies
            WHERE base != 1";

        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
        $currencies = array();

        while ($row = mysqli_fetch_assoc($result)) {
            $currencies[] = $row;
        }

      
        foreach($currencies as $currency){
            if($iyzipay_protected_currency_code == $currency['code']){
                $output_iyzipay_protected_currency_select_options .= '<option value="' . $currency['code'] . '" selected="selected">' . $currency['code'] . ' - ' . $currency['name'] . '</option>';
            }else{
                $output_iyzipay_protected_currency_select_options .= '<option value="' . $currency['code'] . '" >' . $currency['code'] . ' - ' . $currency['name'] . '</option>';
            }

        }

        $output_iyzipay_protected_currency_select = '<select class="form-select" name="iyzipay_protected_currency_code" name="iyzipay_protected_currency_code">' . $output_iyzipay_protected_currency_select_options . '</select>';

    }
  
    



    $timezones = get_timezones();

    // Check to see if the server's timezone is one of the timezones in our pick list
    // and get the label if it exists.
    $server_timezone_label = array_search(SERVER_TIMEZONE, $timezones);

    // If a label could not be found then just use the actual server's timezone for the label.
    if (!$server_timezone_label) {
        $server_timezone_label = SERVER_TIMEZONE;
    }

    $output_timezone_options = '<option value="">Server Default: ' . h($server_timezone_label) . '</option>';

    // If there is a value for the current timezone setting and it is not in our list of supported timezones,
    // then output a custom option for it.  We add this feature so that if someone needs
    // to use a timezone that is not in our list, they can manually set it in the database,
    // and they can continue to save the site settings without the value getting wiped out.
    if (($timezone != '') && (in_array($timezone, $timezones) == false)) {
        $output_timezone_options .= '<option value="' . h($timezone) . '" selected="selected">Custom: ' . h($timezone) . '</option>';
    }

    // Loop through the time zones in order to prepare options for pick list.
    foreach ($timezones as $label => $value) {
        $selected = '';

        // If this timezone is the current timezone, then select it.
        if ($value == $timezone) {
            $selected = ' selected="selected"';
        }

        $output_timezone_options .= '<option value="' . h($value) . '"' . $selected . '>' . h($label) . '</option>';
    }

    // ── IP lists ─────────────────────────────────────────────────────────
    // Only MANUAL entries are shown here, because saving this screen rewrites
    // whatever it displays. Automatic temporary bans live in the same table
    // and are listed separately (read-only) so a routine settings save cannot
    // silently release every IP the firewall banned overnight.
    $waf_ip_columns = ($waf_schema_ready
        && function_exists('waf_table_has_column')
        && waf_table_has_column('banned_ip_addresses', 'list_type'));

    if ($waf_ip_columns) {
        $query = "SELECT ip_address, list_type
                  FROM banned_ip_addresses
                  WHERE source = 'manual'
                  ORDER BY id";
    } else {
        $query = "SELECT ip_address, 'block' AS list_type FROM banned_ip_addresses ORDER BY id";
    }

    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
    $ip_list_rows = mysqli_fetch_items($result);

    $blocked_ips = array();
    $allowed_ips = array();

    foreach ($ip_list_rows as $value) {
        if (isset($value['list_type']) && $value['list_type'] === 'allow') {
            $allowed_ips[] = $value['ip_address'];
        } else {
            $blocked_ips[] = $value['ip_address'];
        }
    }

    // The tagin widget parses a comma separated value.
    $output_banned_ip_addresses = implode(',', $blocked_ips);
    $output_allowed_ip_addresses = implode(',', $allowed_ips);

    // Currently active automatic bans, for the read-only summary.
    $output_auto_bans = '';

    if ($waf_ip_columns) {
        $auto_result = mysqli_query(
            db::$con,
            "SELECT ip_address, note, expires_at
             FROM banned_ip_addresses
             WHERE source = 'auto' AND (expires_at = 0 OR expires_at > " . time() . ")
             ORDER BY expires_at DESC
             LIMIT 25"
        );

        if ($auto_result) {
            $auto_rows = mysqli_fetch_items($auto_result);

            foreach ($auto_rows as $auto_row) {
                $remaining = max(0, (int) $auto_row['expires_at'] - time());

                $output_auto_bans .= '<span class="badge bg-danger-subtle text-danger-emphasis border border-danger-subtle me-1 mb-1">'
                    . '<i class="bi bi-shield-slash me-1"></i>' . h($auto_row['ip_address'])
                    . ' <span class="opacity-75">(' . ceil($remaining / 60) . ' ' . lang('minute(s)') . ')</span>'
                    . '</span>';
            }
        }
    }

    // If the date format is "month_day", then select that radio button.
    if ($date_format == 'month_day') {
        $date_format_month_day_checked = ' checked="checked"';
        $date_format_day_month_checked = '';
    
    // Otherwise the date format is "day_month", so select it.
    } else {
        $date_format_month_day_checked = '';
        $date_format_day_month_checked = ' checked="checked"';
    }

    // If the time format is "twelve_hours", then select that radio button.
    if ($time_format == 'twelve_hours') {
        $time_format_twelve_hours_checked = ' checked="checked"';
        $time_format_twenty_four_hours_checked = '';
    
    // Otherwise the time format is "twenty_four_hours", so select it.
    } else {
        $time_format_twelve_hours_checked = '';
        $time_format_twenty_four_hours_checked = ' checked="checked"';
    }
    
    $page_editor_version_latest_checked = '';
    $page_editor_version_previous_checked = '';
    
    // if the latest editor is selected, then check that option
    if ($page_editor_version == 'latest') {
        $page_editor_version_latest_checked = ' checked="checked"';
    
    // else check the previous editor option
    } else {
        $page_editor_version_previous_checked = ' checked="checked"';
    }

    if ($page_editor_font == 1) {
        $page_editor_font_checked = ' checked="checked"';
    } else {
        $page_editor_font_checked = '';
    }

    if ($page_editor_font_size == 1) {
        $page_editor_font_size_checked = ' checked="checked"';
    } else {
        $page_editor_font_size_checked = '';
    }

    if ($page_editor_font_style == 1) {
        $page_editor_font_style_checked = ' checked="checked"';
    } else {
        $page_editor_font_style_checked = '';
    }

    if ($page_editor_font_color == 1) {
        $page_editor_font_color_checked = ' checked="checked"';
    } else {
        $page_editor_font_color_checked = '';
    }

    if ($page_editor_background_color == 1) {
        $page_editor_background_color_checked = ' checked="checked"';
    } else {
        $page_editor_background_color_checked = '';
    }
    
    $spell_checker_engine_info = get_spell_checker_engine_info();
    
    if ($membership_expiration_warning_email == 1) {
        $membership_expiration_warning_email_checked = ' checked="checked"';
    } else {
        $membership_expiration_warning_email_checked = '';
    }
    
    if ($ecommerce_on_or_off == 1) {
        $ecommerce_checked = ' checked="checked"';
    } else {
        $ecommerce_checked = '';
    }
    
    // get next order number
    $query = "SELECT next_order_number FROM next_order_number";
    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
    $row = mysqli_fetch_assoc($result);
    $ecommerce_next_order_number = $row['next_order_number'];
    
    if ($ecommerce_multicurrency == 1) {
        $ecommerce_multicurrency_checked = ' checked="checked"';
    } else {
        $ecommerce_multicurrency_checked = '';
    }
    
    if ($ecommerce_tax == 1) {
        $ecommerce_tax_checked = ' checked="checked"';
    } else {
        $ecommerce_tax_checked = '';
    }
    
    if ($ecommerce_tax_exempt == 1) {
        $ecommerce_tax_exempt_checked = ' checked="checked"';
    } else {
        $ecommerce_tax_exempt_checked = '';
    }
    
    if ($ecommerce_shipping == 1) {
        $ecommerce_shipping_checked = ' checked="checked"';
    } else {
        $ecommerce_shipping_checked = '';
    }

    if ($ecommerce_recipient_mode == 'single recipient') {
        $ecommerce_recipient_mode_single_recipient = ' checked="checked"';
        $ecommerce_recipient_mode_multirecipient = '';
    } else {
        $ecommerce_recipient_mode_single_recipient = '';
        $ecommerce_recipient_mode_multirecipient = ' checked="checked"';
    }
    $ecommerce_address_verification_checked = '';
    if ($ecommerce_address_verification == 1) {
        $ecommerce_address_verification_checked = ' checked="checked"';
    }

    if ($ecommerce_address_verification_enforcement_type == 'warning') {
        $ecommerce_address_verification_enforcement_type_warning_checked = ' checked="checked"';
        $ecommerce_address_verification_enforcement_type_error_checked = '';
    
    } else {
        $ecommerce_address_verification_enforcement_type_warning_checked = '';
        $ecommerce_address_verification_enforcement_type_error_checked = ' checked="checked"';
    }

    $ups_checked = '';

    if ($ups) {
        $ups_checked = ' checked="checked"';
    }

    $fedex_checked = '';

    if ($fedex) {
        $fedex_checked = ' checked="checked"';
    }
    
    if ($ecommerce_gift_card == 1) {
        $ecommerce_gift_card_checked = ' checked="checked"';
    } else {
        $ecommerce_gift_card_checked = '';
    }
    
    if ($ecommerce_gift_card_validity_days == 0) {
        $ecommerce_gift_card_validity_days = '';
    }

    if ($ecommerce_givex == 1) {
        $ecommerce_givex_checked = ' checked="checked"';
    } else {
        $ecommerce_givex_checked = '';
    }
    
    if ($ecommerce_credit_debit_card == 1) {
        $ecommerce_credit_debit_card_checked = ' checked="checked"';
    } else {
        $ecommerce_credit_debit_card_checked = '';
    }
    
    if ($ecommerce_american_express == 1) {
        $ecommerce_american_express_checked = ' checked="checked"';
    } else {
        $ecommerce_american_express_checked = '';
    }
    
    if ($ecommerce_diners_club == 1) {
        $ecommerce_diners_club_checked = ' checked="checked"';
    } else {
        $ecommerce_diners_club_checked = '';
    }
    
    if ($ecommerce_discover_card == 1) {
        $ecommerce_discover_card_checked = ' checked="checked"';
    } else {
        $ecommerce_discover_card_checked = '';
    }
    
    if ($ecommerce_mastercard == 1) {
        $ecommerce_mastercard_checked = ' checked="checked"';
    } else {
        $ecommerce_mastercard_checked = '';
    }

    if ($ecommerce_visa == 1) {
        $ecommerce_visa_checked = ' checked="checked"';
    } else {
        $ecommerce_visa_checked = '';
    }

    if ($ecommerce_troy == 1) {
        $ecommerce_troy_checked = ' checked="checked"';
    } else {
        $ecommerce_troy_checked = '';
    }

    $barcode_enabled_checked = ($barcode_enabled == 1) ? ' checked="checked"' : '';

    if ($ecommerce_show_product_images == 1) {
        $ecommerce_show_product_images_checked = ' checked="checked"';
    } else {
        $ecommerce_show_product_images_checked = '';
    }

    // prepare all pem file options for First Data Global Gateway pem file name picklist
    $query = "SELECT name FROM files WHERE (type = 'pem')";
    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
    $ecommerce_first_data_global_gateway_pem_file_name_options = '';
    while ($row = mysqli_fetch_assoc($result)) {
        // if file is the current selected pem file, select it by default
        if ($row['name'] == $ecommerce_first_data_global_gateway_pem_file_name) {
            $selected_or_not = ' selected="selected"';
        } else {
            $selected_or_not = '';
        }

        $ecommerce_first_data_global_gateway_pem_file_name_options .= '<option value="' . h($row['name']) . '"' . $selected_or_not . '>' . h($row['name']) . '</option>';
    }
    
    // initialize variables for holding select information for payment gateway pick list
    $ecommerce_payment_gateway_authorizenet = '';
    $ecommerce_payment_gateway_clearcommerce = '';
    $ecommerce_payment_gateway_first_data_global_gateway = '';
    $ecommerce_payment_gateway_paypal_payflow_pro = '';
    $ecommerce_payment_gateway_paypal_payments_pro = '';
    $ecommerce_payment_gateway_sage = '';
    $ecommerce_payment_gateway_stripe = '';
     $ecommerce_payment_gateway_iyzipay = '';

    // prepare payment gateway option to be selected
    switch ($ecommerce_payment_gateway) {
        case 'Authorize.Net':
            $ecommerce_payment_gateway_authorizenet = ' selected="selected"';
            break;
            
        case 'ClearCommerce':
            $ecommerce_payment_gateway_clearcommerce = ' selected="selected"';
            break;
            
        case 'First Data Global Gateway':
            $ecommerce_payment_gateway_first_data_global_gateway = ' selected="selected"';
            break;
            
        case 'PayPal Payflow Pro':
            $ecommerce_payment_gateway_paypal_payflow_pro = ' selected="selected"';
            break;
            
        case 'PayPal Payments Pro':
            $ecommerce_payment_gateway_paypal_payments_pro = ' selected="selected"';
            break;
            
        case 'Sage':
            $ecommerce_payment_gateway_sage = ' selected="selected"';
            break;

        case 'Stripe':
            $ecommerce_payment_gateway_stripe = ' selected="selected"';
            break;
			
        case 'Iyzipay':
            $ecommerce_payment_gateway_iyzipay = ' selected="selected"';
            break;
    }
    
    if ($ecommerce_payment_gateway_transaction_type == 'Authorize & Capture') {
        $ecommerce_payment_gateway_transaction_type_authorize = '';
        $ecommerce_payment_gateway_transaction_type_authorize_and_capture = ' checked="checked"';
    } else {
        $ecommerce_payment_gateway_transaction_type_authorize = ' checked="checked"';
        $ecommerce_payment_gateway_transaction_type_authorize_and_capture = '';
    }
    
    if ($ecommerce_payment_gateway_mode == 'live') {
        $ecommerce_payment_gateway_mode_test = '';
        $ecommerce_payment_gateway_mode_live = ' checked="checked"';
    } else {
        $ecommerce_payment_gateway_mode_test = ' checked="checked"';
        $ecommerce_payment_gateway_mode_live = '';
    }

    // If the surcharge is set to 0, then output empty string instead of 0.
    if ($ecommerce_surcharge_percentage == 0) {
        $ecommerce_surcharge_percentage = '';

    // Otherwise, there is a value, so remove unnecessary zeros.
    } else {
        $ecommerce_surcharge_percentage = floatval($ecommerce_surcharge_percentage);
    }
    
    // assume that reset encryption key should not be disabled, until we find out otherwise
    $ecommerce_reset_encryption_key_disabled = '';
    $ecommerce_reset_encryption_key_disabled_message = '';
    
    // if mcrypt is disabled, then disable reset encryption key
    if ((extension_loaded('mcrypt') == FALSE) || (in_array('rijndael-256', mcrypt_list_algorithms()) == FALSE)) {
        $ecommerce_reset_encryption_key_disabled = ' disabled="disabled"';
        $ecommerce_reset_encryption_key_disabled_message = ' (' . lang('MCrypt is disabled') . ')';
    }
    
    if ($ecommerce_paypal_express_checkout == 1) {
        $ecommerce_paypal_express_checkout_checked = ' checked="checked"';
    } else {
        $ecommerce_paypal_express_checkout_checked = '';
    }
    
    if ($ecommerce_paypal_express_checkout_transaction_type == 'Authorize & Capture') {
        $ecommerce_paypal_express_checkout_transaction_type_authorize = '';
        $ecommerce_paypal_express_checkout_transaction_type_authorize_and_capture = ' checked="checked"';
    } else {
        $ecommerce_paypal_express_checkout_transaction_type_authorize = ' checked="checked"';
        $ecommerce_paypal_express_checkout_transaction_type_authorize_and_capture = '';
    }
    
    if ($ecommerce_paypal_express_checkout_mode == 'live') {
        $ecommerce_paypal_express_checkout_mode_sandbox = '';
        $ecommerce_paypal_express_checkout_mode_live = ' checked="checked"';
    } else {
        $ecommerce_paypal_express_checkout_mode_sandbox = ' checked="checked"';
        $ecommerce_paypal_express_checkout_mode_live = '';
    }
    
    if ($ecommerce_offline_payment == 1) {
        $ecommerce_offline_payment_checked = ' checked="checked"';
    } else {
        $ecommerce_offline_payment_checked = '';
    }
	if($ecommerce_iyzipay_threeds == 1){
		$ecommerce_iyzipay_threeds_checked = 'checked="checked"';
	} else {
		$ecommerce_iyzipay_threeds_checked = '';
	}

    if ($ecommerce_pay_with_iyzico == 1) {
        $ecommerce_pay_with_iyzico_checked = 'checked="checked"';
    } else {
        $ecommerce_pay_with_iyzico_checked = '';
    }


    if ($ecommerce_offline_payment_only_specific_orders == 1) {
        $ecommerce_offline_payment_only_specific_orders_checked = ' checked="checked"';
    } else {
        $ecommerce_offline_payment_only_specific_orders_checked = '';
    }
    
    if ($ecommerce_reward_program == 1) {
        $ecommerce_reward_program_checked = ' checked="checked"';
    } else {
        $ecommerce_reward_program_checked = '';
    }
    
    if ($ecommerce_reward_program_membership == 1) {
        $ecommerce_reward_program_membership_checked = ' checked="checked"';
    } else {
        $ecommerce_reward_program_membership_checked = '';
    }
    
    if ($ecommerce_reward_program_email == 1) {
        $ecommerce_reward_program_email_checked = ' checked="checked"';
    } else {
        $ecommerce_reward_program_email_checked = '';
    }
    
    // if membership days is 0 for reward program, then set value to blank
    if ($ecommerce_reward_program_membership_days == 0) {
        $ecommerce_reward_program_membership_days = '';
    }
    
    // initialize variables for determining if e-commerce rows are shown or hidden
    $ecommerce_payment_gateway_transaction_type_row_style = 'display: none';
    $ecommerce_payment_gateway_mode_row_style = 'display: none';
    $ecommerce_authorizenet_api_login_id_row_style = 'display: none';
    $ecommerce_authorizenet_transaction_key_row_style = 'display: none';
    $ecommerce_clearcommerce_client_id_row_style = 'display: none';
    $ecommerce_clearcommerce_user_id_row_style = 'display: none';
    $ecommerce_clearcommerce_password_row_style = 'display: none';
    $ecommerce_first_data_global_gateway_store_number_row_style = 'display: none';
    $ecommerce_first_data_global_gateway_pem_file_name_row_style = 'display: none';
    $ecommerce_paypal_payments_pro_gateway_mode_row_style = 'display: none';
    $ecommerce_paypal_payments_pro_api_username_row_style = 'display: none';
    $ecommerce_paypal_payments_pro_api_password_row_style = 'display: none';
    $ecommerce_paypal_payments_pro_api_signature_row_style = 'display: none';
    $ecommerce_paypal_payflow_pro_partner_row_style = 'display: none';
    $ecommerce_paypal_payflow_pro_merchant_login_row_style = 'display: none';
    $ecommerce_paypal_payflow_pro_user_row_style = 'display: none';
    $ecommerce_paypal_payflow_pro_password_row_style = 'display: none';
    $ecommerce_sage_merchant_id_row_style = 'display: none';
    $ecommerce_sage_merchant_key_row_style = 'display: none';
    $ecommerce_stripe_api_key_row_style = 'display: none';
	$ecommerce_iyzipay_api_key_row_style = 'display: none';
	$ecommerce_iyzipay_secret_key_row_style = 'display: none';
	$ecommerce_iyzipay_installment_row_style = 'display: none';
	$ecommerce_iyzipay_3ds_row_style = 'display: none';
    $ecommerce_iyzipay_protected_currency_row_style = 'display: none';
    $ecommerce_surcharge_percentage_row_style = 'display: none';
    $ecommerce_reset_encryption_key_row_style = 'display: none';
    
    // if e-commerce is on then prepare to show e-commerce fields
    if ($ecommerce_on_or_off == 1) {

        // if credit/debit card is on, then prepare to show credit/debit card fields
        if ($ecommerce_credit_debit_card == 1) {
            $ecommerce_surcharge_percentage_row_style = '';
            $ecommerce_reset_encryption_key_row_style = '';
            
            // if there is a payment gateway selected, then prepare to show payment gateway fields
            if ($ecommerce_payment_gateway != '') {
                $ecommerce_payment_gateway_transaction_type_row_style = '';
                $ecommerce_payment_gateway_mode_row_style = '';
				
                
                // prepare payment gateway fields depending on which payment gateway is selected
                switch ($ecommerce_payment_gateway) {
                    case 'Authorize.Net':
                        $ecommerce_authorizenet_api_login_id_row_style = '';
                        $ecommerce_authorizenet_transaction_key_row_style = '';
                        break;
                        
                    case 'ClearCommerce':
                        $ecommerce_clearcommerce_client_id_row_style = '';
                        $ecommerce_clearcommerce_user_id_row_style = '';
                        $ecommerce_clearcommerce_password_row_style = '';
                        break;
                        
                    case 'First Data Global Gateway':
                        $ecommerce_first_data_global_gateway_store_number_row_style = '';
                        $ecommerce_first_data_global_gateway_pem_file_name_row_style = '';
                        break;
                        
                    case 'PayPal Payflow Pro':
                        $ecommerce_paypal_payflow_pro_partner_row_style = '';
                        $ecommerce_paypal_payflow_pro_merchant_login_row_style = '';
                        $ecommerce_paypal_payflow_pro_user_row_style = '';
                        $ecommerce_paypal_payflow_pro_password_row_style = '';
                        break;
                        
                    case 'PayPal Payments Pro':
                        $ecommerce_payment_gateway_mode_row_style = 'display: none';
                        $ecommerce_paypal_payments_pro_api_username_row_style = '';
                        $ecommerce_paypal_payments_pro_api_password_row_style = '';
                        $ecommerce_paypal_payments_pro_api_signature_row_style = '';
                        $ecommerce_paypal_payments_pro_gateway_mode_row_style = '';
                        break;
                        
                    case 'Sage':
                        $ecommerce_payment_gateway_mode_row_style = 'display: none';
                        $ecommerce_sage_merchant_id_row_style = '';
                        $ecommerce_sage_merchant_key_row_style = '';
                        break;

                    case 'Stripe':
                        $ecommerce_payment_gateway_mode_row_style = 'display: none';
                        $ecommerce_stripe_api_key_row_style = '';
                        break;
						
                    case 'Iyzipay':
						$ecommerce_payment_gateway_transaction_type_row_style = 'display: none';
                        $ecommerce_iyzipay_api_key_row_style = '';
						$ecommerce_iyzipay_installment_row_style = '';
						$ecommerce_iyzipay_secret_key_row_style = '';
						$ecommerce_iyzipay_3ds_row_style = '';
                        if ($ecommerce_multicurrency == 1) {
                            $ecommerce_iyzipay_protected_currency_row_style = '';
                        }
                        
                        break;
                }
            }
        }
        

    }
    
    if ($forms == 1) {
        $forms_checked = ' checked="checked"';
    } else {
        $forms_checked = '';
    }
    
    if ($calendars == 1) {
        $calendars_checked = ' checked="checked"';
    } else {
        $calendars_checked = '';
    }    

    if ($ads == 1) {
        $ads_checked = ' checked="checked"';
    } else {
        $ads_checked = '';
    }
    
    if ($affiliate_program == 1) {
        $affiliate_program_checked = ' checked="checked"';
    } else {
        $affiliate_program_checked = '';
    }
    
    if ($affiliate_automatic_approval == 1) {
        $affiliate_automatic_approval_checked = ' checked="checked"';
    } else {
        $affiliate_automatic_approval_checked = '';
    }
    
    if ($visitor_tracking == 1) {
        $visitor_tracking_checked = ' checked="checked"';
    } else {
        $visitor_tracking_checked = '';
    }

    $block_unknown_bots_checked = ($block_unknown_bots == 1) ? ' checked="checked"' : '';

    // ── Firewall switches ────────────────────────────────────────────────
    $perf_monitor_checked           = ($perf_monitor == 1) ? ' checked="checked"' : '';

    // With monitoring off there is nothing recorded and nothing kept, so the
    // link would open an empty screen. Hiding it is the same rule the rest of
    // the panel follows: a control that leads nowhere is worse than no
    // control, because the operator has to click it to find that out.
    $output_performance_log_button = '';

    if ($perf_monitor == 1) {
        $output_performance_log_button =
            '<a class="btn btn-link link-secondary py-0 mb-2 " data-loading-content="' . lang('Performance Log') . '" href="'
            . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_performance_log.php"><span class="material-icons me-1">speed</span>'
            . lang('Performance Log') . '</a>';
    }
    $waf_enabled_checked            = ($waf_enabled == 1) ? ' checked="checked"' : '';
    $waf_signature_scan_checked     = ($waf_signature_scan == 1) ? ' checked="checked"' : '';
    $waf_rate_limit_checked         = ($waf_rate_limit == 1) ? ' checked="checked"' : '';
    $waf_auto_ban_checked           = ($waf_auto_ban == 1) ? ' checked="checked"' : '';
    $waf_block_attack_tools_checked = ($waf_block_attack_tools == 1) ? ' checked="checked"' : '';
    $waf_verify_bots_checked        = ($waf_verify_bots == 1) ? ' checked="checked"' : '';

    $waf_mode_monitor_checked = ($waf_mode !== 'block') ? ' checked="checked"' : '';
    $waf_mode_block_checked   = ($waf_mode === 'block') ? ' checked="checked"' : '';

    $output_waf_sensitivity_options = '';

    foreach (array(
        'low'    => lang('Low') . ' — ' . lang('only overwhelming evidence blocks'),
        'medium' => lang('Medium') . ' — ' . lang('recommended'),
        'high'   => lang('High') . ' — ' . lang('weak signals also block'),
    ) as $value => $label) {
        $selected = ($waf_sensitivity === $value) ? ' selected="selected"' : '';
        $output_waf_sensitivity_options .= '<option value="' . h($value) . '"' . $selected . '>' . h($label) . '</option>';
    }

    // ── Third-party WAF / CDN in front of this site ──────────────────────
    //
    // Worth surfacing next to the switch because it changes what this setting
    // means. With Cloudflare in front, edge rules run first and this firewall
    // is the second layer — which still matters, because anything hitting the
    // origin IP directly skips the edge entirely, and that is exactly how
    // proxied sites get attacked.
    //
    // Live detection from the current request wins; the stored value is the
    // fallback for admin panels reached over a path that bypasses the CDN.
    $waf_external = function_exists('waf_detect_external') ? waf_detect_external() : false;
    $waf_external_name = $waf_external ? $waf_external['name'] : '';
    $waf_external_live = (bool) $waf_external;

    if (!$waf_external_name && !empty($row['waf_external_provider'])) {
        $waf_external_names = array(
            'cloudflare' => 'Cloudflare', 'sucuri' => 'Sucuri',
            'incapsula'  => 'Imperva (Incapsula)', 'akamai' => 'Akamai',
            'cloudfront' => 'AWS CloudFront', 'fastly' => 'Fastly',
            'stackpath'  => 'StackPath', 'azure' => 'Azure Front Door',
        );

        $stored_key = $row['waf_external_provider'];

        $waf_external_name = isset($waf_external_names[$stored_key])
            ? $waf_external_names[$stored_key]
            : $stored_key;
    }

    if ($waf_external_name) {
        $output_waf_external =
            '<div class="alert alert-info d-flex align-items-start py-2 px-3 mb-3">'
            . '<i class="bi bi-shield-check me-2 mt-1"></i>'
            . '<div class="small">'
            . '<strong>' . lang(array('string' => '{var:1} detected in front of this site.', 'vars' => h($waf_external_name))) . '</strong>'
            . ($waf_external_live ? '' : ' <span class="text-muted">(' . lang('last seen on a previous request') . ')</span>')
            . '<br>' . lang('Its rules run before this one, so Pinegrap acts as a second layer. Keep this firewall on: requests made straight to the server address bypass the external service entirely.')
            . '<br>' . lang('Add the provider edge addresses to Trusted Proxies below, otherwise every visitor will appear to share one IP address.')
            . '</div></div>';
    } else {
        $output_waf_external =
            '<div class="alert alert-secondary d-flex align-items-start py-2 px-3 mb-3">'
            . '<i class="bi bi-info-circle me-2 mt-1"></i>'
            . '<div class="small">' . lang('No external firewall or CDN was detected in front of this site, so this firewall is the only layer protecting it.') . '</div>'
            . '</div>';
    }

    // ── Live IP resolution readout ───────────────────────────────────────
    //
    // The single most useful diagnostic on this screen. Trusted Proxies is
    // easy to get wrong and the symptom is silent: every visitor collapses
    // into one address, statistics go flat, and IP rules stop meaning
    // anything. Showing the operator what the firewall actually resolved for
    // their own request turns that into something they can see immediately.
    $waf_detected_ip = function_exists('waf_client_ip') ? waf_client_ip() : '';
    $waf_raw_ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';
    $waf_ip_unresolved = (function_exists('waf_ip_is_infrastructure')
        && $waf_detected_ip !== ''
        && waf_ip_is_infrastructure($waf_detected_ip));

    $output_waf_ip_check =
        '<div class="' . ($waf_ip_unresolved ? 'alert alert-warning' : 'alert alert-light border')
        . ' d-flex align-items-start py-2 px-3 mb-3"><i class="bi '
        . ($waf_ip_unresolved ? 'bi-exclamation-triangle' : 'bi-geo-alt') . ' me-2 mt-1"></i>'
        . '<div class="small"><strong>' . lang('Your address as the firewall sees it') . ':</strong> '
        . '<code>' . h($waf_detected_ip) . '</code>'
        . ($waf_detected_ip !== $waf_raw_ip
            ? ' <span class="text-muted">(' . lang('connection from') . ' <code>' . h($waf_raw_ip) . '</code>)</span>'
            : '');

    if ($waf_ip_unresolved) {
        $output_waf_ip_check .= '<br>'
            . lang('This is not a visitor address — it is your server or proxy. Every visitor is currently being seen as this same address, so rate limiting and IP rules cannot tell them apart. Add the address shown in brackets to Trusted Proxies below to fix it.');
    } elseif ($waf_detected_ip !== $waf_raw_ip) {
        $output_waf_ip_check .= '<br><span class="text-success">'
            . lang('Resolved through a trusted proxy — visitor addresses are being read correctly.') . '</span>';
    }

    $output_waf_ip_check .= '</div></div>';

    // Self-ban guard. The two IP fields are one line apart and take the same
    // kind of value, so putting your own address in the wrong one is an easy
    // mistake with a severe result: you lock yourself out of your own site.
    // Checking the operator's live address against the stored block list
    // turns that into something the screen tells them, rather than something
    // they discover from the firewall log.
    $output_waf_self_ban = '';

    if ($waf_detected_ip !== '' && function_exists('waf_ip_is_blocked')) {
        if (waf_ip_is_blocked($waf_detected_ip)) {
            $output_waf_self_ban =
                '<div class="alert alert-danger d-flex align-items-start py-2 px-3 mb-3">'
                . '<i class="bi bi-exclamation-octagon me-2 mt-1"></i>'
                . '<div class="small"><strong>'
                . lang(array('string' => 'Your own address ({var:1}) is on the banned list.', 'vars' => h($waf_detected_ip)))
                . '</strong><br>'
                . lang('You are seeing this screen only because you are already signed in. Did you mean to add it to the allowed list instead?')
                . '</div></div>';
        }
    }

    // ── Outgoing identity ────────────────────────────────────────────────
    //
    // Worth stating plainly, because the operator needs this string to
    // configure OTHER systems: the allowed list on a sibling Pinegrap site,
    // a Cloudflare rule, a hosting firewall.
    //
    // Deliberately NOT privileged here. It is shown so the string is known,
    // not because this firewall grants it anything — a user agent is a claim
    // anyone can make, and treating it as a credential would hand every
    // attacker a bypass by typing one word.
    $output_waf_identity = '';

    if (function_exists('pinegrap_user_agent')) {
        $output_waf_identity =
            '<div class="d-flex align-items-start py-2 px-3 mb-3 border rounded">'
            . '<i class="bi bi-send me-2 mt-1 text-muted"></i>'
            . '<div class="small"><strong>' . lang('This site identifies itself as') . ':</strong> '
            . '<code>' . h(pinegrap_user_agent()) . '</code>'
            . '<br><span class="text-muted">'
            . lang('Sent with licence checks, update checks and other outgoing requests. The host part differs per site, so match on "Pinegrap" if you ever need to list it somewhere.')
            . '<br>'
            . lang('On the site being called, exclude the endpoint path instead — one entry covers every site that calls it, forever. It is not treated as privileged here: a user agent is a claim, not a credential.')
            . '</span></div></div>';
    }

    if (!$waf_schema_ready) {
        $output_waf_self_ban = '';
        $output_waf_ip_check = '';
        $output_waf_external =
            '<div class="alert alert-warning d-flex align-items-start py-2 px-3 mb-3">'
            . '<i class="bi bi-exclamation-triangle me-2 mt-1"></i>'
            . '<div class="small">' . lang('The database has not been upgraded for the firewall yet. Run the software update, then reload this screen.') . '</div>'
            . '</div>';
    }

    // if Google Analytics is enabled, check it and display the related rows
    if ($google_analytics == 1) {
        $google_analytics_checked = ' checked="checked"';
        
    // else, do not check it and hide the related rows
    } else {
        $google_analytics_checked = '';
    }

    if ($advanced_visual_effects == 1) {
        $advanced_visual_effects_checked = ' checked="checked"';
    } else {
        $advanced_visual_effects_checked = '';
    }

    $output_os_family = PHP_OS_FAMILY;
    if (PHP_OS_FAMILY === "Linux") {
        $cron_job_general = '/usr/local/bin/php -q '.dirname(__FILE__) . '/job.php >/dev/null 2>&1';
        $cron_job_exchange_rates = '/usr/local/bin/php -q '.dirname(__FILE__) . '/update_exchange_rates.php >/dev/null 2>&1';
        $cron_job_email_campaign = '/usr/local/bin/php -q '.dirname(__FILE__) . '/email_campaign_job.php >/dev/null 2>&1';
        $cron_job_search_index = '/usr/local/bin/php -q '.dirname(__FILE__) . '/update_search_index.php >/dev/null 2>&1';
        $cron_job_requrring_payment = '/usr/local/bin/php -q '.dirname(__FILE__) . '/recurring_payment_job.php >/dev/null 2>&1';
        $cron_job_membership = '/usr/local/bin/php -q '.dirname(__FILE__) . '/membership_job.php >/dev/null 2>&1';
        $cron_job_auto_backup = '/usr/local/bin/php -q '.dirname(__FILE__) . '/auto_backup.php >/dev/null 2>&1';
    } elseif (PHP_OS_FAMILY === "Windows") {
        $cron_job_general = 'C:\PHP\php.exe -q '.dirname(__FILE__) . '\job.php';
        $cron_job_exchange_rates = 'C:\PHP\php.exe -q '.dirname(__FILE__) . '\update_exchange_rates.php';
        $cron_job_email_campaign = 'C:\PHP\php.exe -q '.dirname(__FILE__) . '\email_campaign_job.php';
        $cron_job_search_index = 'C:\PHP\php.exe -q '.dirname(__FILE__) . '\update_search_index.php';
        $cron_job_requrring_payment = 'C:\PHP\php.exe -q '.dirname(__FILE__) . '\recurring_payment_job.php';
        $cron_job_membership = 'C:\PHP\php.exe -q '.dirname(__FILE__) . '\membership_job.php';
        $cron_job_auto_backup = 'C:\PHP\php.exe -q '.dirname(__FILE__) . '\auto_backup.php';
    }
    $output_warnings_for_auto_backup = '';
    if (!extension_loaded('pdo_mysql') ) {
        $output_warnings_for_auto_backup = '<div class="alert alert-warning">' . lang('pdo_mysql.dll is not enabled. Please enable it for Auto Backup feature.') . '</div>';
    }
    //localhost default ip.
    $server_addr = '127.0.0.1';
    //check server ip.
    if(isset($_SERVER['SERVER_ADDR'])){
        $server_addr = $_SERVER['SERVER_ADDR'];
    }
    $output_install_upgrade_button = '';
    
    if(is_dir('install')){
         $output_install_upgrade_button = '<a class="btn btn-link link-secondary py-0 mb-2 " data-loading-content="' . lang('Reinstall or Upgrade') . '" href="'. OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY.'/install"><span class="material-icons me-1">install_desktop</span>' . lang('Reinstall or Upgrade') . '</a>';
    }
    
    
    $output =
    pg_page_shell(
        array(
            'title'=> lang('Site Settings'),
            'extra classes'=>'setting',
            'icon'=>'setting',
            'heading'=>lang('Site Settings'),
            'head' =>
                '<script src="assets/jsbarcode/JsBarcode.all.min.js"></script>',
        )
    ) . '
    
        ' . get_codemirror_includes() . '
        <div class="row">
            <div class="col-12">
                ' . $liveform->output_errors() . '
                ' . $liveform->get_warnings() . '
                ' . $liveform->output_notices() . '
                <div class="row mb-2  flex-wrap">
                    <div class="col-12 col-sm-12 text-center text-md-start">
                        <h2 class="d-inline-block " data-bs-content="' . lang('All site-wide settings and defaults.') . '" title="' . lang('Site Settings') . '">' . lang('Site Settings') . '</h2>
                        <p class="p-0 m-0">' . $last_modified . '</p>

                        <nav id="button_bar" class="navigation " aria-label="Button Bar">
                            
                            <div class=" btn-group btn-group-sm flex-wrap">
                                
                                <a class="btn btn-link link-secondary py-0 mb-2 " data-loading-content="' . lang('Checking for Updates') . '" href="'. OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY.'/software_update.php"><span class="material-icons me-1">sync</span>' . lang('Check for Updates') . '</a>
                                ' . $output_install_upgrade_button . '
                                <a class="btn btn-link link-secondary py-0 mb-2 " data-loading-content="' . lang('Clean Up') . '" href="'. OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY.'/clean_up.php"><span class="material-icons me-1">cleaning_services</span>' . lang('Clean Up') . '</a>
                                <a class="btn btn-link link-secondary py-0 mb-2 " data-loading-content="' . lang('Clearing Cache') . '" data-confirm-content="' . lang('All server-side caches will be cleared.') . '" href="'. OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY.'/purge_cache.php?token=' . $_SESSION['software']['token'] . '"><span class="material-icons me-1">cached</span>' . lang('Purge Cache') . '</a>
                                <a class="btn btn-link link-secondary py-0 mb-2 " href="'. OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY.'/si.php" onclick="window.open(this.href, \'SoftwareInformations\', \'resizable=no,status=no,location=no,toolbar=no,menubar=no,fullscreen=no,scrollbars=no,dependent=no\'); return false;"><span class="material-icons me-1">info</span>' . lang('System Informations') . '</a>
                                <a class="btn btn-link link-secondary py-0 mb-2 " data-loading-content="' . lang('Site Log') . '" href="'. OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY.'/view_log.php"><span class="material-icons me-1">timeline</span>' . lang('Site Log') . '</a>
                                ' . $output_performance_log_button . '
                            </div>
                            <div class=" btn-group btn-group-sm flex-wrap">
                                <button type="button" class="btn btn-link link-secondary py-0 mb-2 " data-bs-toggle="modal" data-bs-target="#cron_jobs"><span class="material-icons me-1">schedule</span>' . lang('Cron Jobs') . '</button>
                            </div>
                            
                        </nav>
                    </div>
                    <div class="col-auto text-center">
                        <div id="system_status_bar" class="d-flex justify-content-center  border rounded-pill border-secondary bg-dark-subtle px-3 py-2 m-2">
                            <span class="placeholder-glow d-flex align-items-center gap-2">
                                <span class="placeholder rounded-circle" style="width:1.25rem;height:1.25rem;"></span>
                                <span class="placeholder rounded-circle" style="width:1.25rem;height:1.25rem;"></span>
                                <span class="placeholder rounded-circle" style="width:1.25rem;height:1.25rem;"></span>
                                <span class="placeholder rounded-circle" style="width:1.25rem;height:1.25rem;"></span>
                                <span class="placeholder rounded-circle" style="width:1.25rem;height:1.25rem;"></span>
                                <span class="placeholder rounded-circle" style="width:1.25rem;height:1.25rem;"></span>
                                <span class="vr mx-1"></span>
                                <span class="placeholder rounded" style="width:2.5rem;height:1rem;"></span>
                            </span>
                        </div>
                        <script>
                            var statuspopover = new bootstrap.Popover(document.body, {
                              selector: \'.status-popover\',
                              trigger: "hover"
                            });
                        </script>
                    </div>
                </div>
                <div class="modal fade" id="cron_jobs" tabindex="-1" aria-labelledby="cron_jobs" aria-hidden="true">
                    <div class="modal-dialog modal-xl ">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">' . lang('Cron Jobs') . ' (' . $output_os_family . ')</h5>
                                <button type="button" title="' . lang('Close') . '" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body ">
                                <div class="row">
                                    <div class="col-12">
                                        <div class="alert alert-success form-text" role="alert">
                                            ' . lang('There are several optional Pinegrap programs or "jobs" which can be scheduled to run automatically on your web server. The setup of these jobs (commonly referred to as "scheduled tasks" or "cron jobs") is optional depending on which Pinegrap features that are going to be used.') . '
                                        </div>
                                    </div>
                                    <div class="col-12 my-3">
                                        <h5>' . lang('General Jobs') . '</h5>
                                        <p>' . lang('The general job is an optional feature which only needs to be enabled if you are using the scheduled comment feature to publish comments at a future date &amp; time.') . '</p>
                                        <textarea id="cron_job_general">' . $cron_job_general . '</textarea>
                                        ' . get_codemirror_javascript(array('id' => 'cron_job_general', 'code_type' => 'plain','readonly'=>true )) . '
                                        <div class="form-text text-end">' . lang('Recommended Schedule: Every 5 Minutes') . '</div>
                                    </div>
                                    <div class="col-12 my-3">
                                        <h5>' . lang('Exchange Rates Jobs') . '</h5>
                                        <p>' . lang('The exchange rates job is an optional feature which only needs to be enabled if you are using the multi-currency e-commerce feature and want exchange rates for currencies to be updated automatically. Exchange rates can be manually updated via the Admin Panel.') . '</p>
                                        <textarea id="cron_job_exchange_rates">' . $cron_job_exchange_rates . '</textarea>
                                        ' . get_codemirror_javascript(array('id' => 'cron_job_exchange_rates', 'code_type' => 'plain','readonly'=>true )) . '
                                        <div class="form-text text-end">' . lang('Recommended Schedule: Once a Day') . '</div>
                                    </div>
                                    <div class="col-12 my-3">
                                        <h5>' . lang('Email Campaign Jobs') . '</h5>
                                        <p>' . lang(array('string' => 'The e-mail campaign job is an alternative to users manually sending e-mail campaigns from the Software. It is a script that can be scheduled to automatically send e-mail Campaigns. Also, the e-mail campaign job allows e-mail campaigns to be scheduled to be sent at a later time. If you are interested in using the e-mail campaign job, please complete {var:1}.', 'vars' => array('<a class="link-secondary" href="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/smtp_settings.php">' . lang('campaign smtp settings') . '</a>'))) . '</p>
                                        <div class="alert alert-danger">' . lang('WARNING: Once the e-mail campaign job is enabled, it will send e-mails for all campaigns where the status was "Ready to Send", so please make sure you update the status for old, incomplete, e-mail campaigns to be "Cancelled" before you enable the e-mail campaign job.') . '</div>
                                        <textarea id="cron_job_email_campaign">' . $cron_job_email_campaign . '</textarea>
                                        ' . get_codemirror_javascript(array('id' => 'cron_job_email_campaign', 'code_type' => 'plain','readonly'=>true )) . '
                                        <div class="form-text text-end">' . lang('Recommended Schedule: Every 5 Minutes') . '</div>
                                    </div>
                                    <div class="col-12 my-3">
                                        <h5>' . lang('Update Search Index Jobs') . '</h5>
                                        <p>' . lang('The update search index job is an alternative to clicking the Update Search Index button on the Pages tab. It does the spidering of your website and updates the search index with any new or changed content it finds. Since this is an intensive script that may slow down your site while it runs, you should not run it more than once an hour at the most. Less frequently is even better.') . '</p>
                                        <textarea id="cron_job_search_index">' . $cron_job_search_index . '</textarea>
                                        ' . get_codemirror_javascript(array('id' => 'cron_job_search_index', 'code_type' => 'plain','readonly'=>true )) . '
                                        <div class="form-text text-end">' . lang('Recommended Schedule: Once a Day') . '</div>
                                    </div>
                                    <div class="col-12 my-3">
                                        <h5>' . lang('Recurring Payment Jobs') . '</h5>
                                        <p>' . lang('The recurring payment job is an optional feature which only needs to be enabled if you want actions to be performed when a recurring payment profile is disabled (i.e. suspended, cancelled, or expired) (e.g. credit card declined). This job requires the PayPal Website Payments Pro payment gateway. The following actions can be performed when the recurring payment profile is disabled. These options can be set in the properties for the product that creates the recurring payment profile.') . '<br>
                                        ' . lang('Expire membership.') . '<br>
                                        ' . lang('Revoke private access.') . '<br>
                                        ' . lang('Send an e-mail to the customer.') . '<br>
                                        ' . lang('For example, if you have a membership product which has a monthly recurring payment, you might want to expire a persons membership if his/her payment fails (e.g. credit card declined). Also, you might want to send an e-mail to the member with a link to order new membership.') . '</p>
                                        <div class="alert alert-secondary">' . lang('NOTE: The Recurring Payment Job is NOT REQUIRED for setting up Recurring Products. That is handled through your payment gateway automatically once an Order is submitted for one or more recurring products through your Pinegrap website.') . '</div>
                                        <textarea id="cron_job_requrring_payment">' . $cron_job_requrring_payment . '</textarea>
                                        ' . get_codemirror_javascript(array('id' => 'cron_job_requrring_payment', 'code_type' => 'plain','readonly'=>true )) . '
                                        <div class="form-text text-end">' . lang('Recommended Schedule: Once a Day') . '</div>
                                    </div>
                                    <div class="col-12 my-3">
                                        <h5>' . lang('Membership Jobs') . '</h5>
                                        <p>' . lang('The membership job is an optional feature which only needs to be enabled if you want one or more of the features below. Send membership expiration warning e-mail to members whose membership is about to expire. You can enable and configure this feature via the Settings Page. Remove contacts from the Membership Contact Group when a contact\'s membership is no longer valid (e.g. membership has expired). This feature runs automatically once a scheduled task is setup for the membership job.') . '</p>
                                        <textarea id="cron_job_membership">' . $cron_job_membership . '</textarea>
                                        ' . get_codemirror_javascript(array('id' => 'cron_job_membership', 'code_type' => 'plain','readonly'=>true )) . '
                                        <div class="form-text text-end">' . lang('Recommended Schedule: Once a Day') . '</div>
                                    </div>
                                    <div class="col-12 my-3">
                                        <h5>' . lang('Auto Backup Jobs') . '</h5>
                                        ' . $output_warnings_for_auto_backup . '
                                        <p>' . lang('The auto backup job is an optional feature that must be enabled only if you want the automatic backup feature.') . '</p>
                                        <div class="alert alert-secondary">' . lang('NOTE: Auto Backup feature is available by default but can be disabled by a developer from the config.php file. This feature can only be operated once a day.') . '</div>
                                        <textarea id="cron_job_auto_backup">' . $cron_job_auto_backup . '</textarea>
                                        ' . get_codemirror_javascript(array('id' => 'cron_job_auto_backup', 'code_type' => 'plain','readonly'=>true )) . '
                                        <div class="form-text text-end">' . lang('Recommended Schedule: Once a week') . '</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <form name="form" action="settings.php" method="post" autocomplete="off">
                    <!--
                        The following two fields are used to workaround a Safari bug where it incorrectly
                        autofills the member id label field and payment service password field.
                        https://discussions.apple.com/thread/5476502
                        https://discussions.apple.com/thread/6027332
                    -->
                    <input id="fake_user_name" name="fake_user[name]" style="position:absolute; top:-100px;" type="text" value="No Autofill for Site Settings">
                    <input id="fake_password" name="fake_password[name]" style="position:absolute; top:-100px;" type="password" value="No Autofill for Site Settings">
                    ' . get_token_field() . '
                    <div class="row">
                        <div class="col-12">
                            <div class="card my-4">
                                <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
                                    ' . lang('General') . '
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-12 col-md-4 my-2">
                                            <label for="name" class="form-label">' . lang('Website IP Address') . '</label>
                                            <input type="text" name="ip" id="ip" readonly="true" class="disabled form-control" value="' . h($server_addr) . '" inputmode="numeric" data-inputmask-alias="ip"/>
                                        </div>
                                        <div class="col-12 col-md-4 my-2">
                                            <label for="hostname" class="form-label">' . lang('Hostname') . '</label>
                                            <input type="text" name="hostname" id="hostname" maxlength="255" class="form-control" value="' . h($hostname) . '" />
                                        </div>
                                        <div class="col-12 col-md-4 my-2">
                                            <label for="email_address" class="form-label">' . lang('Support E-mail Address') . '</label>
                                            <input type="text" name="email_address" id="email_address" class="form-control" value="' . h($email_address) . '" inputmode="email" data-inputmask-alias="email"/>
                                        </div>
                                        <div class="col-12 my-3">
                                            <div class="form-check form-switch">
                                                <input value="1"' . $secure_mode_checked . ' class="form-check-input" type="checkbox" id="secure_mode" name="secure_mode"/>
                                                <label class="form-check-label" for="secure_mode">' . lang('Secure Mode') . '</label>
                                            </div>
                                            <div class="alert alert-warning">
                                                <p class="mb-2">' . lang(array('string'=>'Warning: Do not enable Secure Mode until you have {var:1} that your site has a working SSL Certificate.','vars'=>array('<a class="alert-link" href="https://' . HOSTNAME_SETTING . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/test_secure_mode.php" target="_blank">' . lang('verified') . '</a>') )) . '</p>
                                                <p class="mb-0 small">' . lang('A padlock in your browser is not enough on its own. If SSL is terminated by a proxy or CDN before the request reaches this server (for example Cloudflare "Flexible" SSL), this server sees plain HTTP and Secure Mode will redirect every request in an endless loop, making the site unreachable. The test page above reports what this server actually sees. If it asks you to, set TRUST_PROXY_SSL_HEADERS in the config file before enabling Secure Mode.') . '</p>
                                            </div>
                                        </div>
                                        ' . $output_software_language . '
                                        <div class="col-12 col-md-4 my-2">
                                            <label for="subscription_id" class="form-label">' . lang('Subscription ID') . '</label>
                                            <input type="text" name="subscription_id" id="subscription_id" maxlength="10" autocomplete="false" class="form-control" value="' . SUBSCRIPTION_ID . '" />
                                        </div>
                                        <div class="col-12 col-md-4 my-2">
                                            <label for="subscription_key" class="form-label">' . lang('Subscription Key') . '</label>
                                            <input type="text" name="subscription_key" id="subscription_key" maxlength="19" autocomplete="false" class="form-control input-mask-key-code" value="' . SUBSCRIPTION_KEY . '" />
                                        </div>
                                        <div class="col-12 col-md-4 my-2">
                                            <label for="proxy_address" class="form-label">' . lang('Proxy Address') . '</label>
                                            <input type="text" name="proxy_address" id="proxy_address" maxlength="255" class="form-control" value="' . h($proxy_address) . '" />
                                        </div>
                                        <div class="col-12 my-1">
                                            <div class="form-check form-switch">
                                                <input value="1"' . $debug_checked . ' class="form-check-input" type="checkbox" id="debug" name="debug"/>
                                                <label class="form-check-label" for="debug">' . lang('Verbose Database Errors') . '</label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="card my-4">
                                <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
                                    ' . lang('Search Engine Optimization') . '
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-12 col-md-4 my-2">
                                            <label for="title" class="form-label">' . lang('Web Browser Title') . '</label>
                                            <input type="text" name="title" id="title" class="form-control" maxlength="255" value="' . h($title) . '"/>
                                            <div id="seo_c_title"></div>
                                        </div>
                                        <div class="col-12 mt-1 mb-2">
                                            <label for="meta_description" class="form-label">' . lang('Web Browser Description') . '</label>
                                            <textarea name="meta_description" id="meta_description" class="form-control" maxlength="255" >' . h($meta_description) . '</textarea>
                                            <div id="seo_c_meta_description"></div>
                                        </div>
                                        <div class="col-12 col-md-6 my-2">
                                            <label for="additional_sitemap_content" class="form-label">' . lang('Additional sitemap.xml Content') . '</label>
                                            <textarea name="additional_sitemap_content" id="additional_sitemap_content" class="form-control" >' . h($additional_sitemap_content) . '</textarea>
                                            ' . get_codemirror_javascript(array('id' => 'additional_sitemap_content', 'code_type' => 'xml.text')) . '
                                        </div>
                                        <div class="col-12 col-md-6 my-2">
                                            <label for="additional_robots_content" class="form-label">' . lang('Additional robots.txt Content') . '</label>
                                            <textarea name="additional_robots_content" id="additional_robots_content" class="form-control" >' . h($additional_robots_content) . '</textarea>
                                            ' . get_codemirror_javascript(array('id' => 'additional_robots_content', 'code_type' => 'plain')) . '
                                            <div class="mt-2">
                                                <button type="button" class="btn btn-sm btn-outline-secondary bi bi-slash-circle bi-me-2" onclick="pgRobotsNoIndex()" title="' . lang('Adds the rule that closes the entire site to search engines.') . '">' . lang('Block All Search Engines') . '</button>
                                                <div class="form-text text-warning d-none" id="robots_noindex_hint"></div>
                                            </div>
                                        </div>

                                        <div class="col-12 col-md-6 my-2">
                                            <label for="indexnow_key" class="form-label">' . lang('IndexNow Key') . '</label>
                                            <div class="input-group">
                                                <input type="text" name="indexnow_key" id="indexnow_key" class="form-control" value="' . h($indexnow_key) . '"/>
                                                <button type="button" class="btn btn-outline-secondary bi bi-key-fill bi-me-2" onclick="generateIndexNowKey()">' . lang('Generate') . '</button>
                                            </div>
                                            <div class="form-text">' . lang('If you provide an IndexNow Key, search engines that support IndexNow will be automatically notified when your sitemap.xml file is updated.') . ' 
                                                <a class="link-secondary" href="https://www.indexnow.org/" target="_blank">' . lang('Learn more about IndexNow') . '</a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="card my-4">
                                <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
                                    ' . lang('Campaigns') . '
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-12 col-md-4 my-2">
                                            <label for="organization_name" class="form-label">' . lang('Organization Name') . '</label>
                                            <input type="text" name="organization_name" id="organization_name" class="form-control" value="' . h($organization_name) . '"/>
                                        </div>
                                        <div class="col-12 col-md-4 my-2">
                                            <label for="organization_address_1" class="form-label">' . lang('Organization Address') . ' 1</label>
                                            <input type="text" name="organization_address_1" id="organization_address_1" class="form-control" value="' . h($organization_address_1) . '"/>
                                        </div>
                                        <div class="col-12 col-md-4 my-2">
                                            <label for="organization_address_2" class="form-label">' . lang('Organization Address') . ' 2</label>
                                            <input type="text" name="organization_address_2" id="organization_address_2" class="form-control" value="' . h($organization_address_2) . '"/>
                                        </div>
                                        <div class="col-12 col-md-4 my-2">
                                            <label for="organization_city" class="form-label">' . lang('Organization City') . '</label>
                                            <input type="text" name="organization_city" id="organization_city" class="form-control" value="' . h($organization_city) . '"/>
                                        </div>
                                        <div class="col-12 col-md-4 my-2">
                                            <label for="organization_state" class="form-label">' . lang('Organization State') . '</label>
                                            <input type="text" name="organization_state" id="organization_state" class="form-control" value="' . h($organization_state) . '"/>
                                        </div>
                                        <div class="col-12 col-md-4 my-2">
                                            <label for="organization_zip_code" class="form-label">' . lang('Organization Zip Code') . '</label>
                                            <input type="text" name="organization_zip_code" id="organization_zip_code" class="form-control" value="' . h($organization_zip_code) . '"/>
                                        </div>
                                        <div class="col-12 col-md-4 my-2">
                                            <label for="organization_country" class="form-label">' . lang('Organization Country') . '</label>
                                            <input type="text" name="organization_country" id="organization_country" class="form-control" value="' . h($organization_country) . '"/>
                                        </div>
                                        <div class="col-12 col-md-4 my-2">
                                            <label for="opt_in_label" class="form-label">' . lang('Opt-In Label') . '</label>
                                            <input type="text" name="opt_in_label" id="opt_in_label" class="form-control" value="' . h($opt_in_label) . '" maxlength="255"/>
                                        </div>
                                        <div class="col-12 col-md-4 my-2">
                                            <label for="plain_text_email_campaign_footer" class="form-label">' . lang('Plain Text Footer') . '</label>
                                            <textarea name="plain_text_email_campaign_footer" id="plain_text_email_campaign_footer" class="form-control">' . h($plain_text_email_campaign_footer) . '</textarea>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="card my-4">
                                <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
                                    ' . lang('Security') . '
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-12 my-1">
                                            <div class="form-check form-switch">
                                                <input value="1"' . $mass_deletion_checked . ' class="form-check-input" type="checkbox" id="mass_deletion" name="mass_deletion"/>
                                                <label class="form-check-label" for="mass_deletion">' . lang('Allow Mass Deletion') . '</label>
                                            </div>
                                        </div>
                                        <div class="col-12 my-1">
                                            <div class="form-check form-switch">
                                                <input value="1"' . $captcha_checked . ' class="form-check-input" type="checkbox" id="captcha" name="captcha"/>
                                                <label class="form-check-label" for="captcha">' . lang('Enable CAPTCHA') . ' (' . lang('spam protection') . ')</label>
                                            </div>
                                        </div>
                                        <div class="col-12 my-1">
                                            <div class="form-check form-switch">
                                                <input value="1"' . $strong_password_checked . ' class="form-check-input" type="checkbox" id="strong_password" name="strong_password"/>
                                                <label class="form-check-label" for="strong_password">' . lang('Require Strong Password') . '</label>
                                            </div>
                                        </div>
                                        <div class="col-12 my-1">
                                            <div class="form-check form-switch">
                                                <input value="1"' . $password_hint_checked . ' class="form-check-input" type="checkbox" id="password_hint" name="password_hint"/>
                                                <label class="form-check-label" for="password_hint">' . lang('Allow Password Hint') . '</label>
                                            </div>
                                        </div>
                                        <div class="col-12 my-1">
                                            <div class="form-check form-switch">
                                                <input value="1"' . $remember_me_checked . ' class="form-check-input" type="checkbox" id="remember_me" name="remember_me"/>
                                                <label class="form-check-label" for="remember_me">' . lang('Allow Remember Me') . '</label>
                                            </div>
                                        </div>
                                        <div class="col-12 my-1">
                                            <div class="form-check form-switch">
                                                <input value="1"' . $forgot_password_link_checked . ' class="form-check-input" type="checkbox" id="forgot_password_link" name="forgot_password_link"/>
                                                <label class="form-check-label" for="forgot_password_link">' . lang('Forgot Password Link') . '</label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="card my-4">
                                <div class="card-header bg-reset border-0 d-flex flex-wrap justify-content-between align-items-center">
                                    <span class="text-uppercase h5 text-primary fw-bold mb-0">' . lang('Web Application Firewall &amp; Bot Filtering') . '</span>
                                    <a href="view_waf_log.php" class="btn btn-sm btn-outline-secondary"><i class="bi bi-list-columns-reverse me-1"></i>' . lang('Firewall Log') . '</a>
                                </div>
                                <div class="card-body">
                                    ' . $output_waf_external . $output_waf_self_ban . $output_waf_ip_check . $output_waf_identity . '
                                    <div class="row">
                                        <div class="col-12 my-1">
                                            <div class="form-check form-switch mb-2">
                                                <input value="1"' . $waf_enabled_checked . ' class="form-check-input collapse-switcher" type="checkbox" id="waf_enabled" name="waf_enabled" data-bs-target="#waf_enabled_row"/>
                                                <label class="form-check-label" for="waf_enabled">' . lang('Enable Firewall') . '</label>
                                                <div class="form-text">' . lang('Inspects every request for injection, scripting and traversal attacks, limits request rates, and enforces the IP lists above.') . '</div>
                                            </div>
                                            <div class="collapse popover fade bs-popover-bottom p-0 mb-2 w-100" id="waf_enabled_row">
                                                <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(52px, 0px);"></div>
                                                <div class="popover-body">
                                                    <div class="row">
                                                        <div class="col-12 mb-3">
                                                            <label class="form-label">' . lang('Mode') . '</label>
                                                            <div class="form-check">
                                                                <input class="form-check-input" type="radio" name="waf_mode" id="waf_mode_monitor" value="monitor"' . $waf_mode_monitor_checked . '/>
                                                                <label class="form-check-label" for="waf_mode_monitor"><strong>' . lang('Monitor') . '</strong> &mdash; ' . lang('record only, block nothing') . '</label>
                                                                <div class="form-text">' . lang('Start here. The log fills with what blocking would have stopped, so you can confirm on your own traffic that nothing legitimate is caught before you switch it on.') . '</div>
                                                            </div>
                                                            <div class="form-check mt-2">
                                                                <input class="form-check-input" type="radio" name="waf_mode" id="waf_mode_block" value="block"' . $waf_mode_block_checked . '/>
                                                                <label class="form-check-label" for="waf_mode_block"><strong>' . lang('Block') . '</strong> &mdash; ' . lang('reject attacking requests') . '</label>
                                                                <div class="form-text">' . lang('Attacks receive a 403 response and repeat offenders are banned temporarily.') . '</div>
                                                            </div>
                                                        </div>
                                                        <div class="col-12 col-md-6 my-1">
                                                            <label for="waf_sensitivity" class="form-label">' . lang('Sensitivity') . '</label>
                                                            <select name="waf_sensitivity" id="waf_sensitivity" class="form-select">' . $output_waf_sensitivity_options . '</select>
                                                            <div class="form-text">' . lang('Each rule contributes a score. Sensitivity sets how much evidence is needed before a request counts as an attack.') . '</div>
                                                        </div>
                                                        <div class="col-12 col-md-3 my-1">
                                                            <label for="waf_log_retention_days" class="form-label">' . lang('Log Retention') . '</label>
                                                            <div class="input-group">
                                                                <input type="text" name="waf_log_retention_days" id="waf_log_retention_days" class="form-control" value="' . (int) $waf_log_retention_days . '" size="4" maxlength="4" inputmode="numeric" style="text-align: right;"/>
                                                                <span class="input-group-text">' . lang('day(s)') . '</span>
                                                            </div>
                                                        </div>
                                                        <div class="col-12 col-md-3 my-1">
                                                            <label for="waf_log_max_rows" class="form-label">' . lang('Log Size Limit') . '</label>
                                                            <div class="input-group">
                                                                <input type="text" name="waf_log_max_rows" id="waf_log_max_rows" class="form-control" value="' . (int) $waf_log_max_rows . '" size="7" maxlength="7" inputmode="numeric" style="text-align: right;"/>
                                                                <span class="input-group-text">' . lang('row(s)') . '</span>
                                                            </div>
                                                            <div class="form-text">' . lang('Identical events within five minutes already share one row. This is the hard ceiling: the oldest rows are dropped beyond it, so an attack cannot fill the database.') . '</div>
                                                        </div>
                                                        <div class="col-12 mt-3">
                                                            <div class="form-check form-switch">
                                                                <input value="1"' . $waf_signature_scan_checked . ' class="form-check-input" type="checkbox" id="waf_signature_scan" name="waf_signature_scan"/>
                                                                <label class="form-check-label" for="waf_signature_scan">' . lang('Attack Signature Scanning') . '</label>
                                                                <div class="form-text">' . lang('Checks query strings, form data, cookies and headers for SQL injection, cross-site scripting, path traversal and command injection. Signed-in staff are never scanned, because designers legitimately submit HTML, JavaScript and SQL.') . '</div>
                                                            </div>
                                                        </div>
                                                        <div class="col-12 mt-2">
                                                            <div class="form-check form-switch">
                                                                <input value="1"' . $waf_block_attack_tools_checked . ' class="form-check-input" type="checkbox" id="waf_block_attack_tools" name="waf_block_attack_tools"/>
                                                                <label class="form-check-label" for="waf_block_attack_tools">' . lang('Block Penetration Testing Tools') . '</label>
                                                                <div class="form-text">' . lang('Rejects sqlmap, Nikto, Acunetix, nuclei and similar scanners by their user agent, whatever else they claim to be.') . '</div>
                                                            </div>
                                                        </div>
                                                        <div class="col-12 mt-2">
                                                            <div class="form-check form-switch">
                                                                <input value="1"' . $waf_verify_bots_checked . ' class="form-check-input" type="checkbox" id="waf_verify_bots" name="waf_verify_bots"/>
                                                                <label class="form-check-label" for="waf_verify_bots">' . lang('Verify Search Engine Crawlers') . '</label>
                                                                <div class="form-text">' . lang('Confirms by reverse DNS that a visitor claiming to be Googlebot really is one. Anyone can type Googlebot into a user agent; without this check, doing so grants crawler privileges. A failed lookup is never treated as a forgery, so a DNS outage cannot block a real crawler.') . '</div>
                                                            </div>
                                                        </div>
                                                        <div class="col-12 mt-3">
                                                            <div class="form-check form-switch mb-2">
                                                                <input value="1"' . $waf_rate_limit_checked . ' class="form-check-input collapse-switcher" type="checkbox" id="waf_rate_limit" name="waf_rate_limit" data-bs-target="#waf_rate_limit_row"/>
                                                                <label class="form-check-label" for="waf_rate_limit">' . lang('Rate Limiting') . '</label>
                                                                <div class="form-text">' . lang('Caps how many requests one address may make per minute. Signed-in staff and verified crawlers are exempt.') . '</div>
                                                            </div>
                                                            <div class="collapse popover fade bs-popover-bottom p-0 mb-2 w-100" id="waf_rate_limit_row">
                                                                <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(52px, 0px);"></div>
                                                                <div class="popover-body">
                                                                    <div class="row">
                                                                        <div class="col-12 col-md-6 my-1">
                                                                            <label for="waf_rate_limit_requests" class="form-label">' . lang('All Requests') . '</label>
                                                                            <div class="input-group">
                                                                                <input type="text" name="waf_rate_limit_requests" id="waf_rate_limit_requests" class="form-control" value="' . (int) $waf_rate_limit_requests . '" size="5" maxlength="5" inputmode="numeric" style="text-align: right;"/>
                                                                                <span class="input-group-text">' . lang('per minute') . '</span>
                                                                            </div>
                                                                        </div>
                                                                        <div class="col-12 col-md-6 my-1">
                                                                            <label for="waf_rate_limit_sensitive" class="form-label">' . lang('Sign-in, Forms and Checkout') . '</label>
                                                                            <div class="input-group">
                                                                                <input type="text" name="waf_rate_limit_sensitive" id="waf_rate_limit_sensitive" class="form-control" value="' . (int) $waf_rate_limit_sensitive . '" size="5" maxlength="5" inputmode="numeric" style="text-align: right;"/>
                                                                                <span class="input-group-text">' . lang('per minute') . '</span>
                                                                            </div>
                                                                        </div>
                                                                        <div class="col-12">
                                                                            <div class="form-text">' . lang('Offices and schools share one address across many people. If visitors report being rate limited, raise these numbers or add the address to the allowed list.') . '</div>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="col-12 mt-2">
                                                            <div class="form-check form-switch mb-2">
                                                                <input value="1"' . $waf_auto_ban_checked . ' class="form-check-input collapse-switcher" type="checkbox" id="waf_auto_ban" name="waf_auto_ban" data-bs-target="#waf_auto_ban_row"/>
                                                                <label class="form-check-label" for="waf_auto_ban">' . lang('Automatic Temporary Bans') . '</label>
                                                                <div class="form-text">' . lang('Repeat offenders are banned for a while instead of being blocked one request at a time. Bans always expire, and are never placed in Monitor mode.') . '</div>
                                                            </div>
                                                            <div class="collapse popover fade bs-popover-bottom p-0 mb-2 w-100" id="waf_auto_ban_row">
                                                                <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(52px, 0px);"></div>
                                                                <div class="popover-body">
                                                                    <div class="row">
                                                                        <div class="col-12 col-md-6 my-1">
                                                                            <label for="waf_auto_ban_threshold" class="form-label">' . lang('Ban After') . '</label>
                                                                            <div class="input-group">
                                                                                <input type="text" name="waf_auto_ban_threshold" id="waf_auto_ban_threshold" class="form-control" value="' . (int) $waf_auto_ban_threshold . '" size="4" maxlength="4" inputmode="numeric" style="text-align: right;"/>
                                                                                <span class="input-group-text">' . lang('events / 10 min') . '</span>
                                                                            </div>
                                                                        </div>
                                                                        <div class="col-12 col-md-6 my-1">
                                                                            <label for="waf_auto_ban_minutes" class="form-label">' . lang('Ban Duration') . '</label>
                                                                            <div class="input-group">
                                                                                <input type="text" name="waf_auto_ban_minutes" id="waf_auto_ban_minutes" class="form-control" value="' . (int) $waf_auto_ban_minutes . '" size="5" maxlength="5" inputmode="numeric" style="text-align: right;"/>
                                                                                <span class="input-group-text">' . lang('minute(s)') . '</span>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="col-12 col-md-6 mt-3">
                                                            <label for="waf_trusted_proxies" class="form-label">' . lang('Trusted Proxies') . '</label>
                                                            <input type="text" value="' . h(waf_list_to_string($waf_trusted_proxies)) . '" name="waf_trusted_proxies" id="waf_trusted_proxies" class="form-control tagin min-height-tagin" data-placeholder="' . lang('Add Address') . '"/>
                                                            <script>
                                                                if(document.body.contains(document.querySelector("input#waf_trusted_proxies"))){
                                                                    tagin(document.querySelector("#waf_trusted_proxies"));
                                                                }
                                                            </script>
                                                            <div class="form-text">' . lang('Addresses of your own load balancer or CDN, one per line. Only these are allowed to declare a visitor address through forwarding headers, since those headers are trivially forged. Cloudflare is recognised automatically.') . '</div>
                                                        </div>
                                                        <div class="col-12 col-md-6 mt-3">
                                                            <label for="waf_exclusions" class="form-label">' . lang('Excluded Paths') . '</label>
                                                            <input type="text" value="' . h(waf_list_to_string($waf_exclusions)) . '" name="waf_exclusions" id="waf_exclusions" class="form-control tagin min-height-tagin" data-placeholder="' . lang('Add Path') . '"/>
                                                            <script>
                                                                if(document.body.contains(document.querySelector("input#waf_exclusions"))){
                                                                    tagin(document.querySelector("#waf_exclusions"));
                                                                }
                                                            </script>
                                                            <div class="form-text">' . lang('Script names or URL paths that skip inspection entirely, before any other rule. Matched against the path only — never the query string, which anyone could append to steal the exemption. Intended for payment gateway callbacks, where losing a request loses a paid order, and for your own licence or update endpoint if this site serves other Pinegrap installations.') . '</div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-12">
                                            <hr class="my-4"/>
                                            <h6 class="text-uppercase text-primary fw-bold mb-1">' . lang('Bot Filtering') . '</h6>
                                            <div class="form-text mb-3">' . lang('Works on its own, whether or not the firewall above is enabled. Search engines, social link previews, uptime monitors and payment gateway callbacks are recognised and never blocked.') . '</div>
                                            <div class="form-check form-switch mb-2">
                                                <input value="1"' . $block_unknown_bots_checked . ' class="form-check-input collapse-switcher" type="checkbox" id="block_unknown_bots" name="block_unknown_bots" data-bs-target="#block_unknown_bots_row"/>
                                                <label class="form-check-label" for="block_unknown_bots">' . lang('Block Unknown Bots') . '</label>
                                                <div class="form-text">' . lang('Crawlers that are not recognised and not on your allowed list receive a 403 response, reducing server load and preventing visitor count inflation. The REST API is exempt, so your own integrations keep working.') . '</div>
                                            </div>
                                            <div class="collapse popover fade bs-popover-bottom p-0 mb-2 w-100" id="block_unknown_bots_row">
                                                <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(52px, 0px);"></div>
                                                <div class="popover-body">
                                                    <div class="row">
                                                        <div class="col-12 col-md-6">
                                                            <label for="allowed_bots" class="form-label"><i class="bi bi-check-circle text-success me-1"></i>' . lang('Allowed Bots') . ' <span class="badge bg-success-subtle text-success-emphasis border border-success-subtle fw-normal">' . lang('never blocked') . '</span></label>
                                                            <input type="text" value="' . h(waf_list_to_string($allowed_bots)) . '" name="allowed_bots" id="allowed_bots" class="form-control tagin min-height-tagin border-success-subtle" data-placeholder="' . lang('Allow a crawler') . '"/>
                                                            <script>
                                                                if(document.body.contains(document.querySelector("input#allowed_bots"))){
                                                                    tagin(document.querySelector("#allowed_bots"));
                                                                }
                                                            </script>
                                                            <div class="form-text">' . lang('Extra crawlers to permit, on top of the built-in list. You do not need to list Google, Bing, Yandex, Facebook, Twitter or the payment gateways — those are already recognised.') . '</div>
                                                        </div>
                                                        <div class="col-12 col-md-6">
                                                            <label for="waf_blocked_agents" class="form-label"><i class="bi bi-slash-circle text-danger me-1"></i>' . lang('Blocked Bots') . ' <span class="badge bg-danger-subtle text-danger-emphasis border border-danger-subtle fw-normal">' . lang('always blocked') . '</span></label>
                                                            <input type="text" value="' . h(waf_list_to_string($waf_blocked_agents)) . '" name="waf_blocked_agents" id="waf_blocked_agents" class="form-control tagin min-height-tagin border-danger-subtle" data-placeholder="' . lang('Block a crawler') . '"/>
                                                            <script>
                                                                if(document.body.contains(document.querySelector("input#waf_blocked_agents"))){
                                                                    tagin(document.querySelector("#waf_blocked_agents"));
                                                                }
                                                            </script>
                                                            <div class="form-text">' . lang('Extra crawlers to reject. Checked after the built-in lists, so these can never override a search engine or a payment gateway.') . '</div>
                                                        </div>
                                                        <div class="col-12 mt-2">
                                                            <div class="form-text">' . lang('SEO scrapers and AI training crawlers (AhrefsBot, SemrushBot, GPTBot, ClaudeBot, Bytespider and similar) are already on the built-in reject list.') . '</div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-12">
                                            <hr class="my-4"/>
                                            <h6 class="text-uppercase text-primary fw-bold mb-1">' . lang('IP Lists') . '</h6>
                                            <div class="form-text mb-3">' . lang('Checked before every other rule, and enforced whether or not the firewall is enabled. Behind a CDN these are matched against the real visitor address, not the edge server — provided the edge is listed under Trusted Proxies above.') . '</div>
                                            <div class="row">
                                                <div class="col-12 col-md-6 mb-2">
                                                    <label for="allowed_ip_addresses" class="form-label"><i class="bi bi-check-circle text-success me-1"></i>' . lang('Allowed IP Addresses') . ' <span class="badge bg-success-subtle text-success-emphasis border border-success-subtle fw-normal">' . lang('never blocked') . '</span></label>
                                                    <input type="text" value="' . h($output_allowed_ip_addresses) . '" name="allowed_ip_addresses" id="allowed_ip_addresses" class="form-control tagin min-height-tagin border-success-subtle" data-placeholder="' . lang('Allow an address') . '"/>
                                                    <div class="form-text">' . lang('These addresses skip every firewall rule. Add your own office or VPN address before enabling blocking, so a mistuned rule cannot lock you out of your own site.') . '</div>
                                                    <script>
                                                        if(document.body.contains(document.querySelector("input#allowed_ip_addresses"))){
                                                            tagin(document.querySelector("#allowed_ip_addresses"));
                                                        }
                                                    </script>
                                                </div>
                                                <div class="col-12 col-md-6 mb-2">
                                                    <label for="banned_ip_addresses" class="form-label"><i class="bi bi-slash-circle text-danger me-1"></i>' . lang('Banned IP Addresses') . ' <span class="badge bg-danger-subtle text-danger-emphasis border border-danger-subtle fw-normal">' . lang('always blocked') . '</span></label>
                                                    <input type="text" value="' . h($output_banned_ip_addresses) . '" name="banned_ip_addresses" id="banned_ip_addresses" class="form-control tagin min-height-tagin border-danger-subtle" data-placeholder="' . lang('Block an address') . '"/>
                                                    <div class="form-text">' . lang('Wildcards and CIDR ranges are supported. For example: 192.168.0.1, 192.168.1.*, 10.0.0.0/8, 2001:db8::/32') . '</div>
                                                    <script>
                                                        if(document.body.contains(document.querySelector("input#banned_ip_addresses"))){
                                                            tagin(document.querySelector("#banned_ip_addresses"));
                                                        }
                                                    </script>
                                                </div>
                                                ' . ($output_auto_bans ? '<div class="col-12 mt-2">
                                                    <label class="form-label">' . lang('Automatic Temporary Bans') . '</label>
                                                    <div>' . $output_auto_bans . '</div>
                                                    <div class="form-text">' . lang('Placed by the firewall and released automatically when they expire. Saving this screen does not clear them.') . '</div>
                                                </div>' : '') . '
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-12 col-md-6">
                            <div class="card my-4">
                                <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
                                    ' . lang('Date & Time') . '
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-12 my-2">
                                            <label class="form-label">' . lang('Current Site Time') . '</label>
                                            <div class="w-100">' . get_absolute_time(array('timestamp' => time(), 'timezone_type' => 'site')) . '</div>
                                        </div>
                                        <div class="col-12 my-2">
                                            <label for="timezone" class="form-label">' . lang('Timezone') . '</label>
                                            <select class="form-select" id="timezone" name="timezone">' . $output_timezone_options . '</select>
                                        </div>
                                        <div class="col-12 my-1">
                                            <label class="form-label">'. lang('Date Format') . '</label>
                                            <div class="form-check">
                                                <input value="month_day" class="form-check-input" type="radio" id="date_format_month_day" name="date_format" ' . $date_format_month_day_checked . '>
                                                <label class="form-check-label" for="date_format_month_day">'. lang('month') . '/'. lang('day') . '/'. lang('year') . ' (2/14/' . date('Y') . ')</label>
                                            </div>
                                            <div class="form-check">
                                                <input value="day_month" class="form-check-input" type="radio" id="date_format_day_month" name="date_format" ' . $date_format_day_month_checked . '>
                                                <label class="form-check-label" for="date_format_day_month">'. lang('day') . '/'. lang('month') . '/'. lang('year') . ' (14/2/' . date('Y') . ')</label>
                                            </div>
                                        </div>
                                        <div class="col-12 my-1">
                                            <label class="form-label">'. lang('Time Format') . '</label>
                                            <div class="form-check">
                                                <input value="twelve_hours" class="form-check-input" type="radio" id="time_format_twelve_hours" name="time_format" ' . $time_format_twelve_hours_checked . '>
                                                <label class="form-check-label" for="time_format_twelve_hours">'. lang('hour') . ':'. lang('minute') . ' am/pm (11:30 pm)</label>
                                            </div>
                                            <div class="form-check">
                                                <input value="twenty_four_hours" class="form-check-input" type="radio" id="time_format_twenty_four_hours" name="time_format" ' . $time_format_twenty_four_hours_checked . '>
                                                <label class="form-check-label" for="time_format_twenty_four_hours">'. lang('hour') . ':'. lang('minute') . ' (23:30)</label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-12 col-md-6">
                            <div class="card my-4">
                                <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
                                    ' . lang('Rich-text Editor') . '
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-12 my-1">
                                            <label class="form-label">'. lang('Editor Version') . '</label>
                                            <div class="form-check">
                                                <input value="latest" class="form-check-input" type="radio" id="page_editor_version_latest" name="page_editor_version" ' . $page_editor_version_latest_checked . '>
                                                <label class="form-check-label" for="page_editor_version_latest">'. lang('Latest') . '</label>
                                            </div>
                                            <div class="form-check">
                                                <input value="previous" class="form-check-input" type="radio" id="page_editor_version_previous" name="page_editor_version" ' . $page_editor_version_previous_checked . '>
                                                <label class="form-check-label" for="page_editor_version_previous">'. lang('Previous') . '</label>
                                            </div>
                                        </div>
                                        <div class="col-12 my-1">
                                            <div class="form-check form-switch">
                                                <input value="1"' . $page_editor_font_checked . ' class="form-check-input" type="checkbox" id="page_editor_font" name="page_editor_font"/>
                                                <label class="form-check-label" for="page_editor_font">' . lang('Font Selection') . '</label>
                                            </div>
                                        </div>
                                        <div class="col-12 my-1">
                                            <div class="form-check form-switch">
                                                <input value="1"' . $page_editor_font_size_checked . ' class="form-check-input" type="checkbox" id="page_editor_font_size" name="page_editor_font_size"/>
                                                <label class="form-check-label" for="page_editor_font_size">' . lang('Font Size Selection') . '</label>
                                            </div>
                                        </div>
                                        <div class="col-12 my-1">
                                            <div class="form-check form-switch">
                                                <input value="1"' . $page_editor_font_style_checked . ' class="form-check-input" type="checkbox" id="page_editor_font_style" name="page_editor_font_style"/>
                                                <label class="form-check-label" for="page_editor_font_style">' . lang('Font Style Selection') . '</label>
                                            </div>
                                        </div>
                                        <div class="col-12 my-1">
                                            <div class="form-check form-switch">
                                                <input value="1"' . $page_editor_font_color_checked . ' class="form-check-input" type="checkbox" id="page_editor_font_color" name="page_editor_font_color"/>
                                                <label class="form-check-label" for="page_editor_font_color">' . lang('Font Color Button') . '</label>
                                            </div>
                                        </div>
                                        <div class="col-12 my-1">
                                            <div class="form-check form-switch">
                                                <input value="1"' . $page_editor_background_color_checked . ' class="form-check-input" type="checkbox" id="page_editor_background_color" name="page_editor_background_color"/>
                                                <label class="form-check-label" for="page_editor_background_color">' . lang('Background Color Button') . '</label>
                                            </div>
                                        </div>
                                        <div class="col-12 my-2">
                                            <label for="spell_check_engine_info" class="form-check-label">' . lang('Spell Checker Engine') . '</label>
                                            <input class="form-control disabled" readonly="true" id="spell_check_engine_info" value="' . $spell_checker_engine_info['name'] . '"/>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="card my-4">
                                <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
                                    ' . lang('Registration & Membership') . '
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-12 col-md-6 col-lg-4 my-2">
                                            <label for="registration_contact_group_id" class="form-label">' . lang('Registration Contact Group') . '</label>
                                            <select class="form-select" id="registration_contact_group_id" name="registration_contact_group_id"><option value="">-' . lang(array('string'=>'Select {var:1}','vars'=>array(lang('Contact Group') ) )) . '-</option>' . select_contact_group($registration_contact_group_id, $user) . '</select>
                                        </div>
                                        <div class="col-12 col-md-6 col-lg-4 my-2">
                                            <label for="registration_email_address" class="form-label">' . lang('Registration E-mail Address') . '</label>
                                            <input type="text" name="registration_email_address" id="registration_email_address" class="form-control" value="' . h($registration_email_address) . '" inputmode="email" data-inputmask-alias="email"/>
                                        </div>
                                        <div class="col-12"><hr/></div>
                                        <div class="col-12">
                                            <div class="row">
                                                <div class="col-12 col-md-6 col-lg-4 my-2">
                                                    <label for="member_id_label" class="form-label">' . lang('Member ID Label') . '</label>
                                                    <input type="text" name="member_id_label" id="member_id_label" class="form-control" value="' . h($member_id_label) . '"/>
                                                </div>
                                                <div class="col-12 col-md-6 col-lg-4 my-2">
                                                    <label for="membership_contact_group_id" class="form-label">' . lang('Membership Contact Group') . '</label>
                                                    <select class="form-select" id="membership_contact_group_id" name="membership_contact_group_id"><option value="">-' . lang(array('string'=>'Select {var:1}','vars'=>array(lang('Contact Group') ) )) . '-</option>' . select_contact_group($membership_contact_group_id, $user) . '</select>
                                                </div>
                                                <div class="col-12 col-md-6 col-lg-4 my-2">
                                                    <label for="membership_email_address" class="form-label">' . lang('Membership E-mail Address') . '</label>
                                                    <input type="text" name="membership_email_address" id="membership_email_address" class="form-control" value="' . h($membership_email_address) . '" inputmode="email" data-inputmask-alias="email"/>
                                                </div>
                                                <div class="col-12 mt-4 mb-2">
                                                    <div class="form-check form-switch">
                                                        <input type="checkbox" name="membership_expiration_warning_email" id="membership_expiration_warning_email" value="1"' . $membership_expiration_warning_email_checked . ' class="form-check-input collapse-switcher" data-bs-target="#membership_expiration_warning_email_row"/>
                                                        <label class="form-check-label" for="membership_expiration_warning_email">' . lang('Send Expiration Warning E-mail to Members') . '</label>
                                                    </div>
                                                    <div class="collapse popover fade bs-popover-bottom p-0 mb-2" id="membership_expiration_warning_email_row">
                                                        <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(90px, 0px);"></div>
                                                        <div class="popover-body">
                                                            <div class="row">
                                                                <div class="col-12">
                                                                    <div class="alert alert-warning">' . lang('Requires scheduled task for membership job') . '</div>
                                                                </div>
                                                                <div class="col-12 col-md-6 col-lg-4 my-2">
                                                                    <label for="membership_expiration_warning_email_subject" class="form-label">' . lang('Subject') . '</label>
                                                                    <input type="text" name="membership_expiration_warning_email_subject" id="membership_expiration_warning_email_subject" class="form-control" value="' . h($membership_expiration_warning_email_subject) . '"/>
                                                                    <div class="form-text text-end">' . lang('Member\'s Expiration Date will be appended to Subject') . '</div>
                                                                </div>
                                                                <div class="col-12 col-md-6 col-lg-4 my-2">
                                                                    <label for="membership_expiration_warning_email_page_id" class="form-label">' . lang('Page') . '</label>
                                                                    <select class="form-select" id="membership_expiration_warning_email_page_id" name="membership_expiration_warning_email_page_id"><option value="">-' . lang(array('string'=>'Select {var:1}','vars'=>array(lang('Page') ) )) . '-</option>' . select_page($membership_expiration_warning_email_page_id) . '</select>
                                                                </div>
                                                                <div class="col-12 col-md-6 col-lg-4 my-2">
                                                                    <label for="membership_expiration_warning_email_days_before_expiration" class="form-label">' . lang('Send') . '</label>
                                                                    <div class="input-group">
                                                                        <input type="text" name="membership_expiration_warning_email_days_before_expiration" id="membership_expiration_warning_email_days_before_expiration" class="form-control text-end" value="' . h($membership_expiration_warning_email_days_before_expiration) . '" maxlength="4" inputmode="numeric" data-inputmask-alias="decimal"/>
                                                                        <span class="input-group-text" title="' . lang('day(s) before expiration date') . '">' . lang('day(s)') . '</span>
                                                                    </div>
                                                                </div>
                                                                </tr>
                                                            </div>
                                                        </div>
                                                    </div> 
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="card my-4">
                                <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
                                    ' . lang('Feature Options') . '
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-12 col-md-4 my-2">
                                            <label for="badge_label" class="form-label">' . lang('Default Badge Label') . '</label>
                                            <input type="text" name="badge_label" id="badge_label" maxlength="100" class="form-control" value="' . h($badge_label) . '" />
                                        </div>
                                        <div class="col-12 my-1">
                                            <label class="form-label">'. lang('Site Search Type') . '</label>
                                            <div class="form-check">
                                                <input value="simple" class="form-check-input" type="radio" id="search_type_simple" name="search_type" ' . $search_type_simple_checked . '>
                                                <label class="form-check-label" for="search_type_simple">'. lang('Simple') . '</label>
                                            </div>
                                            <div class="form-check">
                                                <input value="advanced" class="form-check-input" type="radio" id="search_type_advanced" name="search_type" ' . $search_type_advanced_checked . '>
                                                <label class="form-check-label" for="search_type_advanced">'. lang('Advanced') . '</label>
                                            </div>
                                        </div>
                                        <div class="col-12 my-1"><hr/></div>
                                        <div class="col-12 my-1">
                                            <div class="form-check form-switch">
                                                <input value="1"' . $perf_monitor_checked . ' class="form-check-input" type="checkbox" id="perf_monitor" name="perf_monitor"/>
                                                <label class="form-check-label" for="perf_monitor">' . lang('Enable Performance Monitoring') . '</label>
                                                <div class="form-text">' . lang('Records how long each request takes, so slow pages can be found. The measurement is written after the response has been sent, so visitors do not wait for it.') . '</div>
                                            </div>
                                        </div>
                                        <div class="col-12 my-1">
                                            <div class="form-check form-switch">
                                                <input value="1"' . $mobile_checked . ' class="form-check-input" type="checkbox" id="mobile" name="mobile"/>
                                                <label class="form-check-label" for="mobile">' . lang('Enable Mobile') . '</label>
                                            </div>
                                        </div>
                                        <div class="col-12 my-1">
                                            <div class="form-check form-switch">
                                                <input value="1"' . $auto_dialogs_checked . ' class="form-check-input" type="checkbox" id="auto_dialogs" name="auto_dialogs"/>
                                                <label class="form-check-label" for="auto_dialogs">' . lang('Enable Auto Dialogs') . '</label>
                                            </div>
                                        </div>
                                        <div class="col-12 my-1">
                                            <div class="form-check form-switch">
                                                <input value="1"' . $social_networking_checked . '  id="social_networking" name="social_networking" class="form-check-input collapse-switcher" type="checkbox" role="switch" data-bs-target="#social_networking_type_row"/>
                                                <label class="form-check-label d-inline" for="social_networking">' . lang('Enable Social Networking') . '</label>
                                            </div>
                                            <div class="collapse popover  fade bs-popover-bottom p-0 mb-2 w-100" id="social_networking_type_row">
                                                <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(59px, 0px);"></div>
                                                <div class="popover-body">
                                                    <div class="row">
                                                        <label class="form-label">'. lang('Setup') . '</label>
                                                        <div class="col-12 my-1">
                                                            <div class="form-check form-check-inline">
                                                                <input value="simple" class="form-check-input collapse-switcher" type="radio" id="social_networking_type_simple" name="social_networking_type" ' . $social_networking_type_simple_checked . ' data-bs-target="#social_networking_services_row">
                                                                <label class="form-check-label" for="social_networking_type_simple">'. lang('Simple') . '</label>
                                                            </div>
                                                            <div class="form-check form-check-inline">
                                                                <input value="advanced" class="form-check-input collapse-switcher" type="radio" id="social_networking_type_advanced" name="social_networking_type" ' . $social_networking_type_advanced_checked . ' data-bs-target="#social_networking_code_row">
                                                                <label class="form-check-label" for="social_networking_type_advanced">'. lang('Advanced') . '</label>
                                                            </div>
                                                            <div class="collapse popover fade bs-popover-bottom p-0 mb-2" id="social_networking_services_row">
                                                                <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(15px, 0px);"></div>
                                                                <div class="popover-body">
                                                                    <div class="row">
                                                                        <label class="form-label">'. lang('Services') . '</label>
                                                                        <div class="col-12 my-1">
                                                                            <div class="form-check form-switch">
                                                                                <input type="checkbox" name="social_networking_facebook" id="social_networking_facebook" value="1"' . $social_networking_facebook_checked . ' class="form-check-input" />
                                                                                <label class="form-check-label" for="social_networking_facebook">Facebook</label>
                                                                            </div>
                                                                        </div>
                                                                        <div class="col-12 my-1">
                                                                            <div class="form-check form-switch">
                                                                                <input type="checkbox" name="social_networking_twitter" id="social_networking_twitter" value="1"' . $social_networking_twitter_checked . ' class="form-check-input" />
                                                                                <label class="form-check-label" for="social_networking_twitter">X (Twitter)</label>
                                                                            </div>
                                                                        </div>
                                                                        <div class="col-12 my-1">
                                                                            <div class="form-check form-switch">
                                                                                <input type="checkbox" name="social_networking_linkedin" id="social_networking_linkedin" value="1"' . $social_networking_linkedin_checked . ' class="form-check-input" />
                                                                                <label class="form-check-label" for="social_networking_linkedin">LinkedIn</label>
                                                                            </div>
                                                                        </div>
                                                                        <div class="col-12 my-1">
                                                                            <div class="form-check form-switch">
                                                                                <input type="checkbox" name="social_networking_whatsapp" id="social_networking_whatsapp" value="1"' . $social_networking_whatsapp_checked . ' class="form-check-input" />
                                                                                <label class="form-check-label" for="social_networking_whatsapp">WhatsApp</label>
                                                                            </div>
                                                                        </div>
                                                                        <div class="col-12 my-1">
                                                                            <div class="form-check form-switch">
                                                                                <input type="checkbox" name="social_networking_telegram" id="social_networking_telegram" value="1"' . $social_networking_telegram_checked . ' class="form-check-input" />
                                                                                <label class="form-check-label" for="social_networking_telegram">Telegram</label>
                                                                            </div>
                                                                        </div>
                                                                        <div class="col-12 my-1">
                                                                            <div class="form-check form-switch">
                                                                                <input type="checkbox" name="social_networking_pinterest" id="social_networking_pinterest" value="1"' . $social_networking_pinterest_checked . ' class="form-check-input" />
                                                                                <label class="form-check-label" for="social_networking_pinterest">Pinterest</label>
                                                                            </div>
                                                                        </div>
                                                                        <div class="col-12 my-1">
                                                                            <div class="form-check form-switch">
                                                                                <input type="checkbox" name="social_networking_reddit" id="social_networking_reddit" value="1"' . $social_networking_reddit_checked . ' class="form-check-input" />
                                                                                <label class="form-check-label" for="social_networking_reddit">Reddit</label>
                                                                            </div>
                                                                        </div>
                                                                        <div class="col-12 my-1">
                                                                            <div class="form-check form-switch">
                                                                                <input type="checkbox" name="social_networking_email" id="social_networking_email" value="1"' . $social_networking_email_checked . ' class="form-check-input" />
                                                                                <label class="form-check-label" for="social_networking_email">'. lang('Email') . '</label>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <div class="collapse popover fade bs-popover-bottom p-0 mb-2 w-100" id="social_networking_code_row">
                                                                <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(90px, 0px);"></div>
                                                                <div class="popover-body">
                                                                    <div class="row">
                                                                        <div class="col-12 my-1">
                                                                            <label for="social_networking_code" class="form-label">'. lang('Code') . '</label>
                                                                            <textarea class="form-control" id="social_networking_code" name="social_networking_code">' . h($social_networking_code) . '</textarea>
                                                                            ' . get_codemirror_javascript(array('id' => 'social_networking_code', 'code_type' => 'mixed')) . '
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-12 my-1"><hr/></div>
                                        <div class="col-12 my-1">
                                            <div class="form-check form-switch">
                                                <input value="1"' . $forms_checked . ' class="form-check-input" type="checkbox" id="forms" name="forms"/>
                                                <label class="form-check-label" for="forms">' . lang('Enable Forms') . '</label>
                                            </div>
                                        </div>
                                        <div class="col-12 my-1">
                                            <div class="form-check form-switch">
                                                <input value="1"' . $calendars_checked . ' class="form-check-input" type="checkbox" id="calendars" name="calendars"/>
                                                <label class="form-check-label" for="calendars">' . lang('Enable Calendars') . '</label>
                                            </div>
                                        </div>
                                        <div class="col-12 my-1">
                                            <div class="form-check form-switch">
                                                <input value="1"' . $ads_checked . ' class="form-check-input" type="checkbox" id="ads" name="ads"/>
                                                <label class="form-check-label" for="ads">' . lang('Enable Ads') . '</label>
                                            </div>
                                        </div>
                                        <div class="col-12 my-1">
                                            <div class="form-check form-switch">
                                                <input value="1"' . $visitor_tracking_checked . ' class="form-check-input collapse-switcher" type="checkbox" id="visitor_tracking" name="visitor_tracking" data-bs-target="#visitor_tracking_row"/>
                                                <label class="form-check-label" for="visitor_tracking">' . lang('Enable Visitor Tracking') . '</label>
                                            </div>
                                            <div class="collapse popover fade bs-popover-bottom p-0 mb-2 w-100" id="visitor_tracking_row">
                                                <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(52px, 0px);"></div>
                                                <div class="popover-body">
                                                    <div class="row">
                                                        <div class="col-12 col-sm-6 col-lg-4 my-1">
                                                            <label for="statracking_code_durationrt" class="form-label">*' . lang('Tracking Code Duration') . '</label>
                                                            <div class="input-group">
                                                                <input type="text" required name="tracking_code_duration" id="tracking_code_duration" class="form-control" value="' . h($tracking_code_duration) . '" size="7" maxlength="7" inputmode="numeric" data-inputmask-alias="decimal" data-inputmask-placeholder="1" style="text-align: right;" min="1" max="365" />
                                                                <span class="input-group-text">' . lang('day(s)') . '</span>
                                                            </div>
                                                        </div>
                                                        <div class="col-12 col-sm-6 col-lg-4 my-1">
                                                            <label for="pay_per_click_flag" class="form-label">' . lang('Pay Per Click Tracking Code Flag') . '</label>
                                                            <input type="text" name="pay_per_click_flag" id="pay_per_click_flag" class="form-control" value="' . h($pay_per_click_flag) . '" />
                                                        </div>
                                                        <div class="col-12 my-1">
                                                            <div class="form-check form-switch">
                                                                <input value="1"' . $google_analytics_checked . ' class="form-check-input collapse-switcher" type="checkbox" id="google_analytics" name="google_analytics" data-bs-target="#google_analytics_row"/>
                                                                <label class="form-check-label" for="google_analytics">' . lang('Enable Google Analytics') . '</label>
                                                            </div>
                                                        </div>
                                                        <div class="collapse popover fade bs-popover-bottom p-0 mb-2" id="google_analytics_row">
                                                            <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(52px, 0px);"></div>
                                                            <div class="popover-body">
                                                                <div class="row">
                                                                    <div class="col-12 my-1">
                                                                        <label for="stats_url" class="form-label">' . lang('Website Analytics URL') . '</label>
                                                                        <input type="text" name="stats_url" id="stats_url" class="form-control" value="' . h($stats_url) . '" />
                                                                    </div>
                                                                    <div class="col-12 my-1">
                                                                        <label for="google_analytics_web_property_id" class="form-label">' . lang('Web Property ID') . '</label>
                                                                        <input type="text" name="google_analytics_web_property_id" id="google_analytics_web_property_id" class="form-control" value="' . $google_analytics_web_property_id. '" maxlength="50"/>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-12 my-1">
                                            <div class="form-check form-switch">
                                                <input value="1"' . $affiliate_program_checked . ' class="form-check-input collapse-switcher" type="checkbox" id="affiliate_program" name="affiliate_program" data-bs-target="#affiliate_program_row"/>
                                                <label class="form-check-label" for="affiliate_program">' . lang('Enable Affiliate Program') . '</label>
                                            </div>
                                            <div class="collapse popover fade bs-popover-bottom p-0 mb-2" id="affiliate_program_row">
                                                <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(50px, 0px);"></div>
                                                <div class="popover-body">
                                                    <div class="row">
                                                        <div class="col-12 my-1">
                                                            <div class="form-check form-switch">
                                                                <input value="1"' . $affiliate_automatic_approval_checked . ' class="form-check-input" type="checkbox" id="affiliate_automatic_approval" name="affiliate_automatic_approval"/>
                                                                <label class="form-check-label" for="affiliate_automatic_approval">' . lang('Automatically Approve Affiliates') . '</label>
                                                            </div>
                                                        </div>
                                                        <div class="col-12 col-md-4 col-lg-3 my-1">
                                                            <label for="affiliate_default_commission_rate_row" class="form-label">' . lang('Default Commission Rate') . '</label>
                                                            <div class="input-group">
                                                                <input value="' . $affiliate_default_commission_rate . '" type="text" name="affiliate_default_commission_rate_row" id="affiliate_default_commission_rate_row" class="form-control" size="6" maxlength="6" inputmode="numeric" data-inputmask-alias="decimal" data-inputmask-placeholder="0"  style="text-align: right;" />
                                                                <label for="affiliate_default_commission_rate"  class="input-group-text">%</label>
                                                            </div>
                                                        </div>
                                                        <div class="col-12 col-md-8 col-lg-3 my-1">
                                                            <label class="form-label" for="affiliate_contact_group_id">'. lang('Affiliate Contact Group') . '</label>
                                                            <select class="form-select" id="affiliate_contact_group_id_row" name="affiliate_contact_group_id_row"><option value="">-' . lang(array('string'=>'Select {var:1}','vars'=>array(lang('Contact Group')) )) . '-</option>' . select_contact_group($affiliate_contact_group_id, $user) . '</select>
                                                        </div>
                                                        <div class="col-12 col-md-4 col-lg-3 my-1">
                                                            <label for="affiliate_email_address_row" class="form-label">' . lang('Support E-mail Address') . '</label>
                                                            <input type="text" name="affiliate_email_address_row" id="affiliate_email_address_row" class="form-control" value="' . h($affiliate_email_address) . '" inputmode="email" data-inputmask-alias="email"/>
                                                        </div>
                                                        <div class="col-12 col-md-8 col-lg-3 my-1">
                                                            <label class="form-label" for="affiliate_group_offer_id">'. lang('Group Offer') . '</label>
                                                            <select class="form-select" id="affiliate_group_offer_id" name="affiliate_group_offer_id"><option value="">-' . lang(array('string'=>'Select {var:1}','vars'=>array(lang('Offer')) )) . '-</option>' . select_offer($affiliate_group_offer_id) . '</select>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-12 my-1">
                                            <div class="form-check form-switch">
                                                <input value="1"' . $ecommerce_checked . ' class="form-check-input collapse-switcher" type="checkbox" id="ecommerce" name="ecommerce" data-bs-target="#ecommerce_row"/>
                                                <label class="form-check-label" for="ecommerce">' . lang('Enable Commerce') . '</label>
                                            </div>
                                            <div class="collapse popover fade bs-popover-bottom p-0 mb-2" id="ecommerce_row">
                                                <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(50px, 0px);"></div>
                                                <div class="popover-body">
                                                    <div class="row">
                                                        <div class="col-12 col-md-6 col-lg-4 my-1">
                                                            <label for="ecommerce_next_order_number" class="form-label">' . lang('Next Order Number') . '</label>
                                                            <input type="number" name="ecommerce_next_order_number" id="ecommerce_next_order_number" class="form-control" value="' . $ecommerce_next_order_number . '" maxlength="20"/>
                                                        </div>
                                                        <div class="col-12 col-md-6 col-lg-4 my-1">
                                                            <label for="ecommerce_email_address" class="form-label">' . lang('Commerce E-mail Address') . '</label>
                                                            <input type="text" name="ecommerce_email_address" id="ecommerce_email_address" class="form-control" value="' . h($ecommerce_email_address) . '" inputmode="email" data-inputmask-alias="email"/>
                                                        </div>
                                                        <div class="col-12 my-1">
                                                            <div class="form-check form-switch">
                                                                <input value="1"' . $ecommerce_multicurrency_checked . ' class="form-check-input" type="checkbox" id="ecommerce_multicurrency" name="ecommerce_multicurrency" onclick="show_or_hide_ecommerce_payment_gateway()"/>
                                                                <label class="form-check-label" for="ecommerce_multicurrency">' . lang('Multi-Currency') . '</label>
                                                            </div>
                                                        </div>
                                                        <div class="col-12 my-1">
                                                            <div class="form-check form-switch">
                                                                <input value="1"' . $ecommerce_show_product_images_checked . ' class="form-check-input" type="checkbox" id="ecommerce_show_product_images" name="ecommerce_show_product_images"/>
                                                                <label class="form-check-label" for="ecommerce_show_product_images">' . lang('Show Product Images in Tables') . '</label>
                                                            </div>
                                                        </div>

                                                        <!-- ── Barcode ── -->
                                                        <div class="col-12 my-1">
                                                            <div class="form-check form-switch">
                                                                <input value="1"' . $barcode_enabled_checked . ' class="form-check-input collapse-switcher" type="checkbox" id="barcode_enabled" name="barcode_enabled" data-bs-target="#barcode_settings_row"/>
                                                                <label class="form-check-label fw-semibold" for="barcode_enabled"><i class="bi bi-upc-scan me-1"></i>' . lang('Enable Barcode Feature') . '</label>
                                                            </div>
                                                            <div class="collapse popover fade bs-popover-bottom p-0 mb-2" id="barcode_settings_row">
                                                                <div class="popover-arrow" style="position:absolute;left:0px;transform:translate(30px,0px);"></div>
                                                                <div class="popover-body">
                                                                    <div class="row g-2">
                                                                        <div class="col-12 col-md-auto">
                                                                            <label class="form-label">' . lang('Default Barcode Type') . '</label>
                                                                            <select name="barcode_default_type" id="barcode_default_type" class="form-select form-select-sm">
                                                                                <option value="CODE128"' . ($barcode_default_type === 'CODE128' ? ' selected' : '') . '>Code 128</option>
                                                                                <option value="EAN13"'   . ($barcode_default_type === 'EAN13'   ? ' selected' : '') . '>EAN-13</option>
                                                                                <option value="CODE39"'  . ($barcode_default_type === 'CODE39'  ? ' selected' : '') . '>Code 39</option>
                                                                                <option value="UPC"'     . ($barcode_default_type === 'UPC'     ? ' selected' : '') . '>UPC-A</option>
                                                                            </select>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <!-- ── /Barcode ── -->

                                                        <div class="col-12 my-1">
                                                            <div class="form-check form-switch">
                                                                <input value="1"' . $ecommerce_tax_checked . ' class="form-check-input collapse-switcher" type="checkbox" id="ecommerce_tax" name="ecommerce_tax" data-bs-target="#ecommerce_tax_row"/>
                                                                <label class="form-check-label" for="ecommerce_tax">' . lang('Tax') . '</label>
                                                            </div>
                                                            <div class="collapse popover fade bs-popover-bottom p-0 mb-2" id="ecommerce_tax_row">
                                                                <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(30px, 0px);"></div>
                                                                <div class="popover-body">
                                                                    <div class="row">
                                                                        <div class="col-12 my-1">
                                                                            <div class="form-check form-switch">
                                                                                <input value="1"' . $ecommerce_tax_exempt_checked . ' class="form-check-input collapse-switcher" type="checkbox" id="ecommerce_tax_exempt" name="ecommerce_tax_exempt" data-bs-target="#ecommerce_tax_exempt_row"/>
                                                                                <label class="form-check-label" for="ecommerce_tax_exempt">' . lang('Allow Tax-Exempt') . '</label>
                                                                            </div>
                                                                            <div class="collapse popover fade bs-popover-bottom p-0 mb-2" id="ecommerce_tax_exempt_row">
                                                                                <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(40px, 0px);"></div>
                                                                                <div class="popover-body">
                                                                                    <div class="row">
                                                                                        <div class="col-12 my-1">
                                                                                            <label for="ecommerce_tax_exempt_label" class="form-label">' . lang('Tax-Exempt Label') . '</label>
                                                                                            <input type="text" name="ecommerce_tax_exempt_label" id="ecommerce_tax_exempt_label" class="form-control" value="' . h($ecommerce_tax_exempt_label) . '" maxlength="255"/>
                                                                                        </div>
                                                                                    </div>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="col-12 my-1">
                                                            <div class="form-check form-switch">
                                                                <input value="1"' . $ecommerce_shipping_checked . ' class="form-check-input collapse-switcher" type="checkbox" id="ecommerce_shipping" name="ecommerce_shipping" data-bs-target="#ecommerce_shipping_row"/>
                                                                <label class="form-check-label" for="ecommerce_shipping">' . lang('Shipping') . '</label>
                                                            </div>
                                                            <div class="collapse popover fade bs-popover-bottom p-0 mb-2" id="ecommerce_shipping_row">
                                                                <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(30px, 0px);"></div>
                                                                <div class="popover-body">
                                                                    <div class="row">
                                                                        <div class="col-12 my-1">
                                                                            <label class="form-label">'. lang('Recipient Mode') . '</label>
                                                                            <div class="form-check">
                                                                                <input value="single recipient" class="form-check-input" type="radio" id="ecommerce_recipient_mode_single_recipient" name="ecommerce_recipient_mode" ' . $ecommerce_recipient_mode_single_recipient . '>
                                                                                <label class="form-check-label" for="ecommerce_recipient_mode_single_recipient">'. lang('Single Recipient') . '</label>
                                                                            </div>
                                                                            <div class="form-check">
                                                                                <input value="multi-recipient" class="form-check-input" type="radio" id="ecommerce_recipient_mode_multirecipient" name="ecommerce_recipient_mode" ' . $ecommerce_recipient_mode_multirecipient . '>
                                                                                <label class="form-check-label" for="ecommerce_recipient_mode_multirecipient">'. lang('Multi-Recipient') . '</label>
                                                                            </div>
                                                                        </div>
                                                                        <div class="col-12 col-md-6 col-lg-3 my-1">
                                                                            <label for="usps_user_id" class="form-label">' . lang('USPS Web Tools User ID') . '</label>
                                                                            <input type="text" name="usps_user_id" id="usps_user_id" class="form-control" value="' . h($usps_user_id) . '" maxlength="100"/>
                                                                        </div>
                                                                        <div class="col-12 my-1">
                                                                            <div class="form-check form-switch">
                                                                                <input value="1"' . $ecommerce_address_verification_checked . ' class="form-check-input collapse-switcher" type="checkbox" id="ecommerce_address_verification" name="ecommerce_address_verification" data-bs-target="#ecommerce_address_verification_row"/>
                                                                                <label class="form-check-label" for="ecommerce_address_verification">' . lang('Verify US Addresses') . '</label>
                                                                            </div>
                                                                            <div class="collapse popover fade bs-popover-bottom p-0 mb-2" id="ecommerce_address_verification_row">
                                                                                <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(40px, 0px);"></div>
                                                                                <div class="popover-body">
                                                                                    <div class="row">
                                                                                        <div class="alert alert-primary">' . lang('Requires an approved USPS Web Tools account') . '</div>
                                                                                        <div class="col-12 my-1">
                                                                                            <label class="form-label">'. lang('Enforcement') . '</label>
                                                                                            <div class="form-check">
                                                                                                <input value="warning" class="form-check-input" type="radio" id="ecommerce_address_verification_enforcement_type_warning" name="ecommerce_address_verification_enforcement_type" ' . $ecommerce_address_verification_enforcement_type_warning_checked . '>
                                                                                                <label class="form-check-label" for="ecommerce_address_verification_enforcement_type_warning">'. lang('Warning') . '</label>
                                                                                            </div>
                                                                                            <div class="form-check">
                                                                                                <input value="error" class="form-check-input" type="radio" id="ecommerce_address_verification_enforcement_type_error" name="ecommerce_address_verification_enforcement_type" ' . $ecommerce_address_verification_enforcement_type_error_checked . '>
                                                                                                <label class="form-check-label" for="ecommerce_address_verification_enforcement_type_error">'. lang('Error') . '</label>
                                                                                            </div>
                                                                                        </div>
                                                                                    </div>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                        <div class="col-12 my-1">
                                                                            <div class="form-check form-switch">
                                                                                <input value="1"' . $ups_checked . ' class="form-check-input collapse-switcher" type="checkbox" id="ups" name="ups" data-bs-target="#ups_row"/>
                                                                                <label class="form-check-label" for="ups">' . lang('UPS') . '</label>
                                                                            </div>
                                                                            <div class="collapse popover fade bs-popover-bottom p-0 mb-2" id="ups_row">
                                                                                <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(30px, 0px);"></div>
                                                                                <div class="popover-body">
                                                                                    <div class="row">
                                                                                        <div class="col-12 col-md-6 col-lg-3 my-1">
                                                                                            <label for="ups_key" class="form-label">' . lang('Access Key') . '</label>
                                                                                            <input type="text" name="ups_key" id="ups_key" class="form-control" value="' . h($ups_key) . '" maxlength="100"/>
                                                                                        </div>
                                                                                        <div class="col-12 col-md-6 col-lg-3 my-1">
                                                                                            <label for="ups_user_id" class="form-label">' . lang('User ID') . '</label>
                                                                                            <input type="text" name="ups_user_id" id="ups_user_id" class="form-control" value="' . h($ups_user_id) . '" maxlength="100"/>
                                                                                        </div>
                                                                                        <div class="col-12 col-md-6 col-lg-3 my-1">
                                                                                            <label for="ups_password" class="form-label">' . lang('Password') . '</label>
                                                                                            <input type="password" name="ups_password" id="ups_password" class="form-control" value="' . h($ups_password) . '" maxlength="100"/>
                                                                                        </div>
                                                                                        <div class="col-12 col-md-6 col-lg-3 my-1">
                                                                                            <label for="ups_account" class="form-label">' . lang('Account Number') . '</label>
                                                                                            <input type="text" name="ups_account" id="ups_account" class="form-control" value="' . h($ups_account) . '" maxlength="100"/>
                                                                                        </div>
                                                                                    </div>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                        <div class="col-12 my-1">
                                                                            <div class="form-check form-switch">
                                                                                <input value="1"' . $fedex_checked . ' class="form-check-input collapse-switcher" type="checkbox" id="fedex" name="fedex" data-bs-target="#fedex_row"/>
                                                                                <label class="form-check-label" for="fedex">' . lang('FedEx') . '</label>
                                                                            </div>
                                                                            <div class="collapse popover fade bs-popover-bottom p-0 mb-2" id="fedex_row">
                                                                                <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(30px, 0px);"></div>
                                                                                <div class="popover-body">
                                                                                    <div class="row">
                                                                                        <div class="col-12 col-md-6 col-lg-3 my-1">
                                                                                            <label for="fedex_key" class="form-label">' . lang('Key') . '</label>
                                                                                            <input type="text" name="fedex_key" id="fedex_key" class="form-control" value="' . h($fedex_key) . '" maxlength="100"/>
                                                                                        </div>
                                                                                        <div class="col-12 col-md-6 col-lg-3 my-1">
                                                                                            <label for="fedex_password" class="form-label">' . lang('Password') . '</label>
                                                                                            <input type="password" name="fedex_password" id="fedex_password" class="form-control" value="' . h($fedex_password) . '" maxlength="100"/>
                                                                                        </div>
                                                                                        <div class="col-12 col-md-6 col-lg-3 my-1">
                                                                                            <label for="fedex_account" class="form-label">' . lang('Account Number') . '</label>
                                                                                            <input type="text" name="fedex_account" id="fedex_account" class="form-control" value="' . h($fedex_account) . '" maxlength="100"/>
                                                                                        </div>
                                                                                        <div class="col-12 col-md-6 col-lg-3 my-1">
                                                                                            <label for="fedex_meter" class="form-label">' . lang('Meter Number') . '</label>
                                                                                            <input type="text" name="fedex_meter" id="fedex_meter" class="form-control" value="' . h($fedex_meter) . '" maxlength="100"/>
                                                                                        </div>
                                                                                    </div>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                        <div class="col-12 my-1">
                                                                            <label for="ecommerce_product_restriction_message" class="form-label">' . lang('Product Restriction Message') . '</label>
                                                                            <input type="text" name="ecommerce_product_restriction_message" id="ecommerce_product_restriction_message" class="form-control" value="' . h($ecommerce_product_restriction_message) . '" maxlength="255"/>
                                                                        </div>
                                                                        <div class="col-12 my-1">
                                                                            <label for="ecommerce_no_shipping_methods_message" class="form-label">' . lang('No Shipping Methods Message') . '</label>
                                                                            <input type="text" name="ecommerce_no_shipping_methods_message" id="ecommerce_no_shipping_methods_message" class="form-control" value="' . h($ecommerce_no_shipping_methods_message) . '" maxlength="255"/>
                                                                        </div>
                                                                        ' . get_time_picker_format() . '
                                                                        <script src="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/assets/Jquery/jquery-ui-timepicker-addon-1.2.1.min.js"></script>
                                                                        <div class="col-12 col-md-auto my-1">
                                                                            <label for="ecommerce_end_of_day_time" class="form-label">' . lang('End of Day Time') . '</label>
                                                                            <input type="text" name="ecommerce_end_of_day_time" id="ecommerce_end_of_day_time" class="form-control timepicker" value="' . prepare_form_data_for_output($ecommerce_end_of_day_time, 'time') . '" maxlength="8"/>
                                                                            <div class="form-text">(h:mm AM/PM), ' . lang('Current Site Time') . ': ' . h(date('g:i A')) . '</div>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="col-12 my-1">
                                                            <div class="form-check form-switch">
                                                                <input value="1"' . $ecommerce_gift_card_checked . ' class="form-check-input collapse-switcher" type="checkbox" id="ecommerce_gift_card" name="ecommerce_gift_card" data-bs-target="#ecommerce_gift_card_row"/>
                                                                <label class="form-check-label" for="ecommerce_gift_card">' . lang('Accept Gift Cards') . '</label>
                                                            </div>
                                                            <div class="collapse popover fade bs-popover-bottom p-0 mb-2" id="ecommerce_gift_card_row">
                                                                <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(30px, 0px);"></div>
                                                                <div class="popover-body">
                                                                    <div class="row">
                                                                        <div class="col-12 col-md-auto my-1">
                                                                            <label for="ecommerce_gift_card_validity_days" class="form-label">' . lang('Validity Length') . '</label>
                                                                            <div class="input-group">
                                                                                <input type="text" name="ecommerce_gift_card_validity_days" id="ecommerce_gift_card_validity_days" class="form-control" value="' . $ecommerce_gift_card_validity_days . '" size="7" maxlength="7" inputmode="numeric" data-inputmask-alias="decimal"  style="text-align: right;" />
                                                                                <span class="input-group-text">' . lang('day(s)') . '</span>
                                                                            </div>
                                                                            <div class="text-end form-text">' . lang('leave blank for no expiration') . '</div>
                                                                        </div>
                                                                        <div class="col-12 my-1">
                                                                            <div class="form-check form-switch">
                                                                                <input value="1"' . $ecommerce_givex_checked . ' class="form-check-input collapse-switcher" type="checkbox" id="ecommerce_givex" name="ecommerce_givex" data-bs-target="#ecommerce_givex_row"/>
                                                                                <label class="form-check-label" for="ecommerce_givex">' . lang('Accept Givex Cards') . '</label>
                                                                            </div>
                                                                            <div class="collapse popover fade bs-popover-bottom p-0 mb-2" id="ecommerce_givex_row">
                                                                                <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(30px, 0px);"></div>
                                                                                <div class="popover-body">
                                                                                    <div class="row">
                                                                                        <div class="alert alert-primary">' . lang('Requires Givex service') . '</div>
                                                                                        <div class="col-12 col-md-6 col-lg-3 my-1">
                                                                                            <label for="ecommerce_givex_primary_hostname" class="form-label">' . lang('Primary Hostname') . '</label>
                                                                                            <input type="text" name="ecommerce_givex_primary_hostname" id="ecommerce_givex_primary_hostname" class="form-control" value="' . h($ecommerce_givex_primary_hostname) . '" maxlength="100"/>
                                                                                        </div>
                                                                                        <div class="col-12 col-md-6 col-lg-3 my-1">
                                                                                            <label for="ecommerce_givex_secondary_hostname" class="form-label">' . lang('Secondary Hostname') . '</label>
                                                                                            <input type="text" name="ecommerce_givex_secondary_hostname" id="ecommerce_givex_secondary_hostname" class="form-control" value="' . h($ecommerce_givex_secondary_hostname) . '" maxlength="100"/>
                                                                                        </div>
                                                                                        <div class="col-12 col-md-6 col-lg-3 my-1">
                                                                                            <label for="ecommerce_givex_user_id" class="form-label">' . lang('User ID') . '</label>
                                                                                            <input type="text" name="ecommerce_givex_user_id" id="ecommerce_givex_user_id" class="form-control" value="' . h($ecommerce_givex_user_id) . '" maxlength="100"/>
                                                                                        </div>
                                                                                        <div class="col-12 col-md-6 col-lg-3 my-1">
                                                                                            <label for="ecommerce_givex_password" class="form-label">' . lang('Password') . '</label>
                                                                                            <input type="text" name="ecommerce_givex_password" id="ecommerce_givex_password" class="form-control" value="' . h($ecommerce_givex_password) . '" maxlength="100"/>
                                                                                        </div>
                                                                                    </div>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="col-12 my-1">
                                                            <div class="form-check form-switch">
                                                                <input value="1"' . $ecommerce_reward_program_checked . ' class="form-check-input collapse-switcher" type="checkbox" id="ecommerce_reward_program" name="ecommerce_reward_program" data-bs-target="#ecommerce_reward_program_row"/>
                                                                <label class="form-check-label" for="ecommerce_reward_program">' . lang('Enable Reward Program') . '</label>
                                                            </div>
                                                            <div class="collapse popover fade bs-popover-bottom p-0 mb-2 w-100" id="ecommerce_reward_program_row">
                                                                <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(30px, 0px);"></div>
                                                                <div class="popover-body">
                                                                    <div class="row">
                                                                        <div class="col-12 col-md-4 col-lg-3 my-1">
                                                                            <label for="ecommerce_reward_program_points" class="form-label">' . lang('Goal') . '</label>
                                                                            <div class="input-group">
                                                                                <input value="' . $ecommerce_reward_program_points . '" type="text" name="ecommerce_reward_program_points" id="ecommerce_reward_program_points" class="form-control" size="6" maxlength="6" inputmode="numeric" data-inputmask-alias="decimal" data-inputmask-placeholder="0"  style="text-align: right;" />
                                                                                <label for="ecommerce_reward_program_points"  class="input-group-text">' . lang('point(s)') . '</label>
                                                                            </div>
                                                                        </div>
                                                                        <div class="col-12 my-1">
                                                                            <div class="form-check form-switch">
                                                                                <input value="1"' . $ecommerce_reward_program_membership_checked . ' class="form-check-input collapse-switcher" type="checkbox" id="ecommerce_reward_program_membership" name="ecommerce_reward_program_membership" data-bs-target="#ecommerce_reward_program_membership_row"/>
                                                                                <label class="form-check-label" for="ecommerce_reward_program_membership">' . lang('Grant Membership') . '</label>
                                                                            </div>
                                                                            <div class="collapse popover fade bs-popover-bottom p-0 mb-2" id="ecommerce_reward_program_membership_row">
                                                                                <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(30px, 0px);"></div>
                                                                                <div class="popover-body">
                                                                                    <div class="row">
                                                                                        <div class="col-12 my-2">
                                                                                            <label for="ecommerce_reward_program_membership_days" class="form-label">' . lang('Membership Length') . '</label>
                                                                                            <div class="input-group">
                                                                                                <input value="' . $ecommerce_reward_program_membership_days . '" type="text" name="ecommerce_reward_program_membership_days" id="ecommerce_reward_program_membership_days" class="form-control" size="5" maxlength="5" inputmode="numeric" data-inputmask-alias="decimal" data-inputmask-placeholder="0"  style="text-align: right;" />
                                                                                                <label for="ecommerce_reward_program_membership_days"  class="input-group-text">' . lang('day(s)') . '</label>
                                                                                            </div>
                                                                                            <div class="form-text text-end">(' . lang('leave blank for lifetime membership') . ')</div>
                                                                                        </div>
                                                                                    </div>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                        <div class="col-12 my-1">
                                                                            <div class="form-check form-switch">
                                                                                <input value="1"' . $ecommerce_reward_program_email_checked . ' class="form-check-input collapse-switcher" type="checkbox" id="ecommerce_reward_program_email" name="ecommerce_reward_program_email" data-bs-target="#ecommerce_reward_program_email_row"/>
                                                                                <label class="form-check-label" for="ecommerce_reward_program_email">' . lang('Send E-mail') . '</label>
                                                                            </div>
                                                                            <div class="collapse popover fade bs-popover-bottom p-0 mb-2 w-100" id="ecommerce_reward_program_email_row">
                                                                                <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(30px, 0px);"></div>
                                                                                <div class="popover-body">
                                                                                    <div class="row">
                                                                                        <div class="col-12 col-sm-6 col-lg-4 my-1">
                                                                                            <label class="form-label" for="ecommerce_reward_program_email_bcc_email_address">' . lang('BCC E-mail Address') . '</label>
                                                                                            <input type="text" value="' . h($ecommerce_reward_program_email_bcc_email_address) . '" class="form-control text-end" id="ecommerce_reward_program_email_bcc_email_address" name="ecommerce_reward_program_email_bcc_email_address" maxlength="100" inputmode="email" data-inputmask-alias="email"/>
                                                                                        </div>
                                                                                        <div class="col-12 col-sm-6 col-lg-4 my-1">
                                                                                            <label class="form-label" for="ecommerce_reward_program_email_subject">' . lang('Subject') . '</label>
                                                                                            <input type="text" value="' . h($ecommerce_reward_program_email_subject) . '" id="ecommerce_reward_program_email_subject" name="ecommerce_reward_program_email_subject" class="form-control" maxlength="255" >
                                                                                        </div>
                                                                                        <div class="col-12 col-sm-6 col-lg-4 my-1">
                                                                                            <label class="form-label" for="ecommerce_reward_program_email_page_id">' . lang('Page') . '</label>
                                                                                            <select class="form-select" id="ecommerce_reward_program_email_page_id" name="ecommerce_reward_program_email_page_id"><option value="">-' . lang(array('string'=>'Select {var:1}','vars'=>array(lang('Page')) )) . '-</option>' . select_page($ecommerce_reward_program_email_page_id) . '</select>
                                                                                        </div>
                                                                                    </div>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="col-12 my-1">
                                                            <div class="form-check form-switch">
                                                                <input value="1"' . $strutured_data_checked . ' class="form-check-input" type="checkbox" id="strutured_data" name="strutured_data"/>
                                                                <label class="form-check-label" for="strutured_data">' . lang('Enable Strutured Data for Products') . '</label>
                                                            </div>
                                                        </div>
                                                        <div class="col-12 my-1">
                                                        <div class="form-check form-switch">
                                                            <input value="1"' . $enable_parasut_checked . ' class="form-check-input collapse-switcher" type="checkbox" id="enable_parasut" name="enable_parasut" data-bs-target="#ecommerce_parasut_configration_row"/>
                                                            <label class="form-check-label" for="enable_parasut">' . lang('Enable Parasut Configuration') . '</label>
                                                        </div>
                                                        <div class="collapse popover fade bs-popover-bottom p-0 mb-2 w-100" id="ecommerce_parasut_configration_row">
                                                            <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(30px, 0px);"></div>
                                                            <div class="popover-body">
                                                                <div class="row">
                                                                    <div class="col-12 my-1"><h6 class="text-muted">' . lang('API Connection') . '</h6></div>
                                                                    <div class="col-12 col-sm-6 col-lg-4 my-1">
                                                                        <label class="form-label" for="parasut_client_id">Client ID</label>
                                                                        <input type="text" class="form-control" id="parasut_client_id" name="parasut_client_id" value="' . h($parasut_client_id) . '" autocomplete="off" />
                                                                    </div>
                                                                    <div class="col-12 col-sm-6 col-lg-4 my-1">
                                                                        <label class="form-label" for="parasut_client_secret">Client Secret</label>
                                                                        <input type="password" class="form-control" id="parasut_client_secret" name="parasut_client_secret" value="' . h($parasut_client_secret) . '" autocomplete="new-password" />
                                                                    </div>
                                                                    <div class="col-12 col-sm-6 col-lg-4 my-1">
                                                                        <label class="form-label" for="parasut_username">' . lang('Username') . ' (E-posta)</label>
                                                                        <input type="text" class="form-control" id="parasut_username" name="parasut_username" value="' . h($parasut_username) . '" autocomplete="off" />
                                                                    </div>
                                                                    <div class="col-12 col-sm-6 col-lg-4 my-1">
                                                                        <label class="form-label" for="parasut_password">' . lang('Password') . '</label>
                                                                        <input type="password" class="form-control" id="parasut_password" name="parasut_password" value="' . h($parasut_password) . '" autocomplete="new-password" />
                                                                    </div>
                                                                    <div class="col-12 col-sm-6 col-lg-4 my-1">
                                                                        <label class="form-label" for="parasut_company_id">' . lang('Company ID') . ' <span class="text-muted small">(Firma No)</span></label>
                                                                        <input type="text" class="form-control" id="parasut_company_id" name="parasut_company_id" value="' . h($parasut_company_id) . '" autocomplete="off" />
                                                                        <div class="form-text">' . lang('You can find your Company ID in the Parasut URL: app.parasut.com/v4/{company_id}') . '</div>
                                                                    </div>
                                                                    <div class="col-12 my-1">
                                                                        <div class="form-check form-switch">
                                                                            <input value="1"' . $parasut_use_sandbox_checked . ' class="form-check-input" type="checkbox" id="parasut_use_sandbox" name="parasut_use_sandbox" />
                                                                            <label class="form-check-label" for="parasut_use_sandbox">' . lang('Use Sandbox (Test) Environment') . '</label>
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-12 my-1"><hr/><h6 class="text-muted">' . lang('Invoice Settings') . '</h6></div>
                                                                    <div class="col-12 col-sm-6 col-lg-4 my-1">
                                                                        <label class="form-label" for="parasut_tc_in_field">' . lang('Select field to used as Republic of Turkey identification number') . '</label>
                                                                        <select class="form-select" id="parasut_tc_in_field" name="parasut_tc_in_field">' . $parasut_tc_in_field_options . '</select>
                                                                    </div>
                                                                    <div class="col-12 col-sm-6 col-lg-4 my-1">
                                                                        <label class="form-label" for="parasut_default_product_id">' . lang('Default Product/Service ID') . ' <span class="text-muted small">(Varsayılan Ürün/Hizmet)</span></label>
                                                                        <input type="text" class="form-control" id="parasut_default_product_id" name="parasut_default_product_id" value="' . h($parasut_default_product_id) . '" autocomplete="off" />
                                                                        <div class="form-text">' . lang('Parasut product/service ID used for all invoice line items. Create a generic product in Parasut and enter its ID here.') . '</div>
                                                                    </div>
                                                                    <div class="col-12 col-sm-6 col-lg-4 my-1">
                                                                        <label class="form-label" for="parasut_default_warehouse_id">' . lang('Default Warehouse ID') . ' <span class="text-muted small">(Varsayılan Depo)</span></label>
                                                                        <input type="text" class="form-control" id="parasut_default_warehouse_id" name="parasut_default_warehouse_id" value="' . h($parasut_default_warehouse_id) . '" autocomplete="off" />
                                                                        <div class="form-text">' . lang('Fetched automatically from Parasut on first use. Override here if you have multiple warehouses.') . '</div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="col-12 my-1"><hr/></div>
                                                        <div class="col-12 my-1"><h5>' . lang('Payment Methods') . '</h5></div>
                                                        <div class="col-12 my-1">
                                                            <div class="form-check form-switch">
                                                                <input value="1"' . $ecommerce_credit_debit_card_checked . ' class="form-check-input collapse-switcher" type="checkbox" id="ecommerce_credit_debit_card" name="ecommerce_credit_debit_card" data-bs-target="#ecommerce_credit_debit_card_row"/>
                                                                <label class="form-check-label" for="ecommerce_credit_debit_card">' . lang('Credit/Debit Card') . '</label>
                                                            </div>
                                                            <div class="collapse popover fade bs-popover-bottom p-0 mb-2" id="ecommerce_credit_debit_card_row">
                                                                <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(30px, 0px);"></div>
                                                                <div class="popover-body">
                                                                    <div class="row">
                                                                        <div class="col-12 my-1">
                                                                            <label class="form-label">'. lang('Accepted Cards') . '</label>
                                                                        </div>
                                                                        <div class="col-12 my-1">
                                                                            <div class="form-check form-switch">
                                                                                <input value="1"' . $ecommerce_american_express_checked . ' class="form-check-input collapse-switcher" type="checkbox" id="ecommerce_american_express" name="ecommerce_american_express"/>
                                                                                <label class="form-check-label" for="ecommerce_american_express">' . lang('American Express') . '</label>
                                                                            </div>
                                                                        </div>
                                                                        <div class="col-12 my-1">
                                                                            <div class="form-check form-switch">
                                                                                <input value="1"' . $ecommerce_diners_club_checked . ' class="form-check-input collapse-switcher" type="checkbox" id="ecommerce_diners_club" name="ecommerce_diners_club"/>
                                                                                <label class="form-check-label" for="ecommerce_diners_club">' . lang('Diners Club') . '</label>
                                                                            </div>
                                                                        </div>
                                                                        <div class="col-12 my-1">
                                                                            <div class="form-check form-switch">
                                                                                <input value="1"' . $ecommerce_discover_card_checked . ' class="form-check-input collapse-switcher" type="checkbox" id="ecommerce_discover_card" name="ecommerce_discover_card"/>
                                                                                <label class="form-check-label" for="ecommerce_discover_card">' . lang('Discover Card') . '</label>
                                                                            </div>
                                                                        </div>
                                                                        <div class="col-12 my-1">
                                                                            <div class="form-check form-switch">
                                                                                <input value="1"' . $ecommerce_mastercard_checked . ' class="form-check-input collapse-switcher" type="checkbox" id="ecommerce_mastercard" name="ecommerce_mastercard"/>
                                                                                <label class="form-check-label" for="ecommerce_mastercard">' . lang('MasterCard') . '</label>
                                                                            </div>
                                                                        </div>
                                                                        <div class="col-12 my-1">
                                                                            <div class="form-check form-switch">
                                                                                <input value="1"' . $ecommerce_visa_checked . ' class="form-check-input collapse-switcher" type="checkbox" id="ecommerce_visa" name="ecommerce_visa"/>
                                                                                <label class="form-check-label" for="ecommerce_visa">' . lang('Visa') . '</label>
                                                                            </div>
                                                                        </div>
                                                                        <div class="col-12 my-1">
                                                                            <div class="form-check form-switch">
                                                                                <input value="1"' . $ecommerce_troy_checked . ' class="form-check-input collapse-switcher" type="checkbox" id="ecommerce_troy" name="ecommerce_troy"/>
                                                                                <label class="form-check-label" for="ecommerce_troy">' . lang('Troy') . '</label>
                                                                            </div>
                                                                        </div>

                                                                        <div class="col-12 my-1">
                                                                            <div class="row">
                                                                                <div class="col-12 col-md-auto my-1">
                                                                                    <label class="form-check-label" for="ecommerce_payment_gateway">' . lang('Payment Gateway') . '</label>
                                                                                    <select class="form-select collapse-if-selected mb-1" name="ecommerce_payment_gateway" id="ecommerce_payment_gateway" onchange="show_or_hide_ecommerce_payment_gateway()" data-bs-target="#ecommerce_payment_gateway_row">
                                                                                        <option value="">-' . lang('None') . '-</option>
                                                                                        <option value="Authorize.Net"' . $ecommerce_payment_gateway_authorizenet . '>Authorize.Net</option>
                                                                                        <option value="ClearCommerce"' . $ecommerce_payment_gateway_clearcommerce . '>ClearCommerce/PayFuse</option>
                                                                                        <option value="First Data Global Gateway"' . $ecommerce_payment_gateway_first_data_global_gateway . '>First Data Global Gateway</option>
                                                                                        <option value="PayPal Payflow Pro"' . $ecommerce_payment_gateway_paypal_payflow_pro . '>PayPal Payflow Pro</option>
                                                                                        <option value="PayPal Payments Pro"' . $ecommerce_payment_gateway_paypal_payments_pro . '>PayPal Payments Pro</option>
                                                                                        <option value="Sage"' . $ecommerce_payment_gateway_sage . '>Sage</option>
                                                                                        <option value="Stripe"' . $ecommerce_payment_gateway_stripe . '>Stripe</option>
                                                                                        <option value="Iyzipay"' . $ecommerce_payment_gateway_iyzipay . '>Iyzipay</option>
                                                                                    </select>
                                                                                </div>
                                                                            </div>
                                                                            <div class="collapse popover fade bs-popover-bottom p-0 mb-2 " id="ecommerce_payment_gateway_row">
                                                                                <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(59px, 0px);"></div>
                                                                                <div class="popover-body">
                                                                                    <div class="row">
                                                                                        <div class="col-12 my-1" id="ecommerce_payment_gateway_transaction_type_row" style="' . $ecommerce_payment_gateway_transaction_type_row_style . '">
                                                                                            <label class="form-label">'. lang('Transaction Type') . '</label>
                                                                                            <div class="form-check">
                                                                                                <input value="Authorize" class="form-check-input" type="radio" id="ecommerce_payment_gateway_transaction_type_authorize" name="ecommerce_payment_gateway_transaction_type" ' . $ecommerce_payment_gateway_transaction_type_authorize . '>
                                                                                                <label class="form-check-label" for="ecommerce_payment_gateway_transaction_type_authorize">'. lang('Authorize') . '</label>
                                                                                            </div>
                                                                                            <div class="form-check">
                                                                                                <input value="Authorize &amp; Capture" class="form-check-input" type="radio" id="ecommerce_payment_gateway_transaction_type_authorize_and_capture" name="ecommerce_payment_gateway_transaction_type" ' . $ecommerce_payment_gateway_transaction_type_authorize_and_capture . '>
                                                                                                <label class="form-check-label" for="ecommerce_payment_gateway_transaction_type_authorize_and_capture">'. lang('Authorize & Capture') . '</label>
                                                                                            </div>
                                                                                        </div>
                                                                                        <div class="col-12 my-1" id="ecommerce_payment_gateway_mode_row" style="' . $ecommerce_payment_gateway_mode_row_style . '">
                                                                                            <label class="form-label">'. lang('Mode') . '</label>
                                                                                            <div class="form-check">
                                                                                                <input value="test" class="form-check-input" type="radio" id="ecommerce_payment_gateway_mode_test" name="ecommerce_payment_gateway_mode" ' . $ecommerce_payment_gateway_mode_test . '>
                                                                                                <label class="form-check-label" for="ecommerce_payment_gateway_mode_test">'. lang('Test') . '</label>
                                                                                            </div>
                                                                                            <div class="form-check">
                                                                                                <input value="live" class="form-check-input" type="radio" id="ecommerce_payment_gateway_mode_live" name="ecommerce_payment_gateway_mode" ' . $ecommerce_payment_gateway_mode_live . '>
                                                                                                <label class="form-check-label" for="ecommerce_payment_gateway_mode_live">'. lang('Live') . '</label>
                                                                                            </div>
                                                                                        </div>
                                                                                        <div class="col-12 my-1" id="ecommerce_paypal_payments_pro_gateway_mode_row" style="' . $ecommerce_paypal_payments_pro_gateway_mode_row_style . '">
                                                                                            <label class="form-label">'. lang('Mode') . '</label>
                                                                                            <div class="form-check">
                                                                                                <input value="test" class="form-check-input" type="radio" id="ecommerce_paypal_payments_pro_gateway_mode_test" name="ecommerce_paypal_payments_pro_gateway_mode" ' . $ecommerce_payment_gateway_mode_test . '>
                                                                                                <label class="form-check-label" for="ecommerce_paypal_payments_pro_gateway_mode_test">'. lang('Sandbox') . '</label>
                                                                                            </div>
                                                                                            <div class="form-check">
                                                                                                <input value="live" class="form-check-input" type="radio" id="ecommerce_paypal_payments_pro_gateway_mode_live" name="ecommerce_paypal_payments_pro_gateway_mode" ' . $ecommerce_payment_gateway_mode_live . '>
                                                                                                <label class="form-check-label" for="ecommerce_paypal_payments_pro_gateway_mode_live">'. lang('Live') . '</label>
                                                                                            </div>
                                                                                        </div>
                                                                                        <div class="col-12 col-md-6 col-lg-4 my-1" id="ecommerce_authorizenet_api_login_id_row" style="' . $ecommerce_authorizenet_api_login_id_row_style . '">
                                                                                            <label class="form-check-label" for="ecommerce_authorizenet_api_login_id">'. lang('API Login ID') . '</label>
                                                                                            <input class="form-control" type="text" id="ecommerce_authorizenet_api_login_id" name="ecommerce_authorizenet_api_login_id" value="' . h($ecommerce_authorizenet_api_login_id) . '" size="40" maxlength="100" />
                                                                                        </div>
                                                                                        <div class="col-12 col-md-6 col-lg-4 my-1" id="ecommerce_authorizenet_transaction_key_row" style="' . $ecommerce_authorizenet_transaction_key_row_style . '">
                                                                                            <label class="form-check-label" for="ecommerce_authorizenet_transaction_key">'. lang('Transaction Key') . '</label>
                                                                                            <input class="form-control" type="password" id="ecommerce_authorizenet_transaction_key" name="ecommerce_authorizenet_transaction_key" value="' . h($ecommerce_authorizenet_transaction_key) . '" size="40" maxlength="100" />
                                                                                        </div>

                                                                                        <div class="col-12 col-md-6 col-lg-4 my-1" id="ecommerce_clearcommerce_client_id_row" style="' . $ecommerce_clearcommerce_client_id_row_style . '">
                                                                                            <label class="form-check-label" for="ecommerce_clearcommerce_client_id">'. lang('Client ID') . '</label>
                                                                                            <input class="form-control" type="text" id="ecommerce_clearcommerce_client_id" name="ecommerce_clearcommerce_client_id" value="' . h($ecommerce_clearcommerce_client_id) . '" size="40" maxlength="100" />
                                                                                        </div>
                                                                                        <div class="col-12 col-md-6 col-lg-4 my-1" id="ecommerce_clearcommerce_user_id_row" style="' . $ecommerce_clearcommerce_user_id_row_style . '">
                                                                                            <label class="form-check-label" for="ecommerce_clearcommerce_user_id">'. lang('User ID') . '</label>
                                                                                            <input class="form-control" type="text" id="ecommerce_clearcommerce_user_id" name="ecommerce_clearcommerce_user_id" value="' . h($ecommerce_clearcommerce_user_id) . '" size="40" maxlength="100" />
                                                                                        </div>
                                                                                        <div class="col-12 col-md-6 col-lg-4 my-1" id="ecommerce_clearcommerce_password_row" style="' . $ecommerce_clearcommerce_password_row_style . '">
                                                                                            <label class="form-check-label" for="ecommerce_clearcommerce_password">'. lang('Password') . '</label>
                                                                                            <input class="form-control" type="password" id="ecommerce_clearcommerce_password" name="ecommerce_clearcommerce_password" value="' . h($ecommerce_clearcommerce_password) . '" size="40" maxlength="100" />
                                                                                        </div>

                                                                                        <div class="col-12 col-md-6 col-lg-4 my-1" id="ecommerce_first_data_global_gateway_store_number_row" style="' . $ecommerce_first_data_global_gateway_store_number_row_style . '">
                                                                                            <label class="form-check-label" for="ecommerce_first_data_global_gateway_store_number">'. lang('Store Number') . '</label>
                                                                                            <input class="form-control" type="text" id="ecommerce_first_data_global_gateway_store_number" name="ecommerce_first_data_global_gateway_store_number" value="' . h($ecommerce_first_data_global_gateway_store_number) . '" size="40" maxlength="100" />
                                                                                        </div>
                                                                                        <div class="col-12 col-md-6 col-lg-4 my-1" id="ecommerce_first_data_global_gateway_pem_file_name_row" style="' . $ecommerce_first_data_global_gateway_pem_file_name_row_style . '">
                                                                                            <label class="form-check-label" for="ecommerce_first_data_global_gateway_pem_file_name">'. lang('PEM File') . '</label>
                                                                                            <select class="form-select" name="ecommerce_first_data_global_gateway_pem_file_name" id="ecommerce_first_data_global_gateway_pem_file_name"><option value="">-' . lang('None') . '-</option>' . $ecommerce_first_data_global_gateway_pem_file_name_options . '</select>
                                                                                        </div>
                                                                                    
                                                                                        <div class="col-12 col-md-6 col-lg-4 my-1" id="ecommerce_paypal_payflow_pro_partner_row" style="' . $ecommerce_paypal_payflow_pro_partner_row_style . '">
                                                                                            <label class="form-check-label" for="ecommerce_paypal_payflow_pro_partner">'. lang('Partner') . '</label>
                                                                                            <input class="form-control" type="text" id="ecommerce_paypal_payflow_pro_partner" name="ecommerce_paypal_payflow_pro_partner" value="' . h($ecommerce_paypal_payflow_pro_partner) . '" size="40" maxlength="100" />
                                                                                        </div>
                                                                                        <div class="col-12 col-md-6 col-lg-4 my-1" id="ecommerce_paypal_payflow_pro_merchant_login_row" style="' . $ecommerce_paypal_payflow_pro_merchant_login_row_style . '">
                                                                                            <label class="form-check-label" for="ecommerce_paypal_payflow_pro_merchant_login">'. lang('Merchant Login') . '</label>
                                                                                            <input class="form-control" type="text" id="ecommerce_paypal_payflow_pro_merchant_login" name="ecommerce_paypal_payflow_pro_merchant_login" value="' . h($ecommerce_paypal_payflow_pro_merchant_login) . '" size="40" maxlength="100" />
                                                                                        </div>
                                                                                        <div class="col-12 col-md-6 col-lg-4 my-1" id="ecommerce_paypal_payflow_pro_user_row" style="' . $ecommerce_paypal_payflow_pro_user_row_style . '">
                                                                                            <label class="form-check-label" for="ecommerce_paypal_payflow_pro_user">'. lang('User') . '</label>
                                                                                            <input class="form-control" type="text" id="ecommerce_paypal_payflow_pro_user" name="ecommerce_paypal_payflow_pro_user" value="' . h($ecommerce_paypal_payflow_pro_user) . '" size="40" maxlength="100" />
                                                                                        </div>
                                                                                        <div class="col-12 col-md-6 col-lg-4 my-1" id="ecommerce_paypal_payflow_pro_password_row" style="' . $ecommerce_paypal_payflow_pro_password_row_style . '">
                                                                                            <label class="form-check-label" for="ecommerce_paypal_payflow_pro_password">'. lang('Password') . '</label>
                                                                                            <input class="form-control" type="password" id="ecommerce_paypal_payflow_pro_password" name="ecommerce_paypal_payflow_pro_password" value="' . h($ecommerce_paypal_payflow_pro_password) . '" size="40" maxlength="100" />
                                                                                        </div>

                                                                                        <div class="col-12 col-md-6 col-lg-4 my-1" id="ecommerce_paypal_payments_pro_api_username_row" style="' . $ecommerce_paypal_payments_pro_api_username_row_style . '">
                                                                                            <label class="form-check-label" for="ecommerce_paypal_payments_pro_api_username">'. lang('API Username') . '</label>
                                                                                            <input class="form-control" type="text" id="ecommerce_paypal_payments_pro_api_username" name="ecommerce_paypal_payments_pro_api_username" value="' . h($ecommerce_paypal_payments_pro_api_username) . '" size="40" maxlength="100" />
                                                                                        </div>
                                                                                        <div class="col-12 col-md-6 col-lg-4 my-1" id="ecommerce_paypal_payments_pro_api_password_row" style="' . $ecommerce_paypal_payments_pro_api_password_row_style . '">
                                                                                            <label class="form-check-label" for="ecommerce_paypal_payments_pro_api_password">'. lang('API Password') . '</label>
                                                                                            <input class="form-control" type="password" id="ecommerce_paypal_payments_pro_api_password" name="ecommerce_paypal_payments_pro_api_password" value="' . h($ecommerce_paypal_payments_pro_api_password) . '" size="40" maxlength="100" />
                                                                                        </div>
                                                                                        <div class="col-12 col-md-6 col-lg-4 my-1" id="ecommerce_paypal_payments_pro_api_signature_row" style="' . $ecommerce_paypal_payments_pro_api_signature_row_style . '">
                                                                                            <label class="form-check-label" for="ecommerce_paypal_payments_pro_api_signature">'. lang('API Signature') . '</label>
                                                                                            <input class="form-control" type="password" id="ecommerce_paypal_payments_pro_api_signature" name="ecommerce_paypal_payments_pro_api_signature" value="' . h($ecommerce_paypal_payments_pro_api_signature) . '" size="40" maxlength="100" />
                                                                                        </div>

                                                                                        <div class="col-12 col-md-6 col-lg-4 my-1" id="ecommerce_sage_merchant_id_row" style="' . $ecommerce_sage_merchant_id_row_style . '">
                                                                                            <label class="form-check-label" for="ecommerce_sage_merchant_id">'. lang('Merchant ID') . '</label>
                                                                                            <input class="form-control" type="text" id="ecommerce_sage_merchant_id" name="ecommerce_sage_merchant_id" value="' . h($ecommerce_sage_merchant_id) . '" size="40" maxlength="100" />
                                                                                        </div>
                                                                                        <div class="col-12 col-md-6 col-lg-4 my-1" id="ecommerce_sage_merchant_key_row" style="' . $ecommerce_sage_merchant_key_row_style . '">
                                                                                            <label class="form-check-label" for="ecommerce_sage_merchant_key">'. lang('Merchant Key') . '</label>
                                                                                            <input class="form-control" type="password" id="ecommerce_sage_merchant_key" name="ecommerce_sage_merchant_key" value="' . h($ecommerce_sage_merchant_key) . '" size="40" maxlength="100" />
                                                                                        </div>
                                                                                       
                                                                                        <div class="col-12 col-md-6 col-lg-4 my-1" id="ecommerce_stripe_api_key_row" style="' . $ecommerce_stripe_api_key_row_style . '">
                                                                                            <label class="form-check-label" for="ecommerce_stripe_api_key">'. lang('API Key') . '</label>
                                                                                            <input class="form-control" type="password" id="ecommerce_stripe_api_key" name="ecommerce_stripe_api_key" value="' . h($ecommerce_stripe_api_key) . '" size="40" maxlength="100" />
                                                                                            <div class="form-text">' . lang('Enter the Test or Live Secret Key') . '</div>
                                                                                        </div>
                                                                                       
                                                                                        <div class="col-12 col-md-6 col-lg-4 my-1" id="ecommerce_iyzipay_api_key_row" style="' . $ecommerce_iyzipay_api_key_row_style . '">
                                                                                            <label class="form-check-label" for="ecommerce_iyzipay_api_key">'. lang('API Key') . '</label>
                                                                                            <input class="form-control" type="password" id="ecommerce_iyzipay_api_key" name="ecommerce_iyzipay_api_key" value="' . h($ecommerce_iyzipay_api_key) . '" size="40" maxlength="100" />
                                                                                        </div>
                                                                                        <div class="col-12 col-md-6 col-lg-4 my-1" id="ecommerce_iyzipay_secret_key_row" style="' . $ecommerce_iyzipay_secret_key_row_style . '">
                                                                                            <label class="form-check-label" for="ecommerce_iyzipay_secret_key">'. lang('Secret Key') . '</label>
                                                                                            <input class="form-control" type="password" id="ecommerce_iyzipay_secret_key" name="ecommerce_iyzipay_secret_key" value="' . h($ecommerce_iyzipay_secret_key) . '" size="40" maxlength="100" />
                                                                                        </div>
                                                                                        <div class="col-12 col-md-6 col-lg-4 my-1" id="ecommerce_iyzipay_installment_row" style="' . $ecommerce_iyzipay_installment_row_style . '">
                                                                                            <label class="form-check-label" for="ecommerce_iyzipay_secret_key">'. lang('Installment') . '</label>
                                                                                            <select class="form-select" id="ecommerce_iyzipay_installment" name="ecommerce_iyzipay_installment">'.$ecommerce_iyzipay_installment_options.'</select>
                                                                                        </div>
                                                                                        <div class="col-12 col-md-4 col-lg-3 my-1" id="ecommerce_surcharge_percentage_row" style="' . $ecommerce_surcharge_percentage_row_style . '">
                                                                                            <label for="ecommerce_surcharge_percentage" class="form-label">' . lang('Surcharge') . '</label>
                                                                                            <div class="input-group">
                                                                                                <input value="' . $ecommerce_surcharge_percentage . '" type="text" name="ecommerce_surcharge_percentage" id="ecommerce_surcharge_percentage" class="form-control" size="7" maxlength="7" inputmode="numeric" data-inputmask-alias="decimal" data-inputmask-placeholder="0"  style="text-align: right;" />
                                                                                                <label for="affiliate_default_commission_rate"  class="input-group-text">%</label>
                                                                                            </div>
                                                                                        </div>
                                                                                        <div class="col-12 my-1" id="ecommerce_iyzipay_threeds_row" style="' . $ecommerce_iyzipay_3ds_row_style . '">
                                                                                            <div class="form-check form-switch">
                                                                                                <input value="1"' . $ecommerce_iyzipay_threeds_checked . ' class="form-check-input collapse-switcher" type="checkbox" id="ecommerce_iyzipay_threeds" name="ecommerce_iyzipay_threeds"/>
                                                                                                <label class="form-check-label" for="ecommerce_iyzipay_threeds">' . lang('3D Secure') . '</label>
                                                                                            </div>
                                                                                        </div>
                                                                                        <div class="col-12 my-1">
                                                                                            <div class="form-check form-switch">
                                                                                                <input value="1" ' . $ecommerce_pay_with_iyzico_checked . ' class="form-check-input" type="checkbox" id="ecommerce_pay_with_iyzico" name="ecommerce_pay_with_iyzico"/>
                                                                                                <label class="form-check-label" for="ecommerce_pay_with_iyzico">' . lang('Enable Pay with Iyzico') . '</label>
                                                                                            </div>
                                                                                        </div>

                                                                                        <div class="col-12" id="ecommerce_iyzipay_protected_currency_row" style="' . $ecommerce_iyzipay_protected_currency_row_style . '">
                                                                                            <div class="form-check form-switch my-1">
                                                                                                <input value="1"' . $enable_iyzipay_protected_currency_checked . ' class="form-check-input collapse-switcher" type="checkbox" id="enable_iyzipay_protected_currency" name="enable_iyzipay_protected_currency" data-bs-target="#enable_iyzipay_protected_currency_row"/>
                                                                                                <label class="form-check-label" for="enable_iyzipay_protected_currency">' . lang('Enable Iyzipay Protected Currency') . '</label>
                                                                                            </div>
                                                                                            <div class="collapse popover fade bs-popover-bottom p-0 mb-2 w-100" id="enable_iyzipay_protected_currency_row">
                                                                                                <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(30px, 0px);"></div>
                                                                                                <div class="popover-body">
                                                                                                    <div class="row">
                                                                                                        <div class="col-12 col-sm-6 col-lg-4 my-1">
                                                                                                            <label class="form-label" for="iyzipay_protected_currency_code">' . lang('Currency to be converted in submiting order for currency protection') . '</label>
                                                                                                            ' . $output_iyzipay_protected_currency_select . '
                                                                                                        </div>
                                                                                                    </div>
                                                                                                </div>
                                                                                            </div>
                                                                                        </div>

                                                                                        <div class="col-12 my-1" id="ecommerce_reset_encryption_key_row" style="' . $ecommerce_reset_encryption_key_row_style . '">
                                                                                            <div class="form-check form-switch">
                                                                                                <input value="1"' . $ecommerce_reset_encryption_key_disabled . ' class="form-check-input collapse-switcher" type="checkbox" id="ecommerce_reset_encryption_key" name="ecommerce_reset_encryption_key"/>
                                                                                                <label class="form-check-label" for="ecommerce_reset_encryption_key">' . lang('Reset Encryption Key') . '</label>
                                                                                                <div class="form-text">' . $ecommerce_reset_encryption_key_disabled_message . '</div>
                                                                                            </div>
                                                                                        </div>
                                                                                    </div>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="col-12 my-1">
                                                            <div class="form-check form-switch">
                                                                <input value="1"' . $ecommerce_paypal_express_checkout_checked . ' class="form-check-input collapse-switcher" type="checkbox" id="ecommerce_paypal_express_checkout" name="ecommerce_paypal_express_checkout" data-bs-target="#ecommerce_paypal_express_checkout_row"/>
                                                                <label class="form-check-label" for="ecommerce_paypal_express_checkout">' . lang('PayPal Express Checkout') . '</label>
                                                            </div>
                                                            <div class="collapse popover fade bs-popover-bottom p-0 mb-2" id="ecommerce_paypal_express_checkout_row">
                                                                <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(30px, 0px);"></div>
                                                                <div class="popover-body">
                                                                    <div class="row">
                                                                        <div class="col-12 my-1">
                                                                            <label class="form-label">'. lang('Transaction Type') . '</label>
                                                                            <div class="form-check">
                                                                                <input value="Authorize" class="form-check-input" type="radio" id="ecommerce_paypal_express_checkout_transaction_type_authorize" name="ecommerce_paypal_express_checkout_transaction_type" ' . $ecommerce_paypal_express_checkout_transaction_type_authorize . '>
                                                                                <label class="form-check-label" for="ecommerce_paypal_express_checkout_transaction_type_authorize">'. lang('Authorize') . '</label>
                                                                            </div>
                                                                            <div class="form-check">
                                                                                <input value="Authorize &amp; Capture" class="form-check-input" type="radio" id="ecommerce_paypal_express_checkout_transaction_type_authorize_and_capture" name="ecommerce_paypal_express_checkout_transaction_type" ' . $ecommerce_paypal_express_checkout_transaction_type_authorize_and_capture . '>
                                                                                <label class="form-check-label" for="ecommerce_paypal_express_checkout_transaction_type_authorize_and_capture">'. lang('Authorize & Capture') . '</label>
                                                                            </div>
                                                                        </div>
                                                                        <div class="col-12 my-1">
                                                                            <label class="form-label">'. lang('Mode') . '</label>
                                                                            <div class="form-check">
                                                                                <input value="sandbox" class="form-check-input" type="radio" id="ecommerce_paypal_express_checkout_mode_sandbox" name="ecommerce_paypal_express_checkout_mode" ' . $ecommerce_paypal_express_checkout_mode_sandbox . '>
                                                                                <label class="form-check-label" for="ecommerce_paypal_express_checkout_mode_sandbox">'. lang('Sandbox') . '</label>
                                                                            </div>
                                                                            <div class="form-check">
                                                                                <input value="live" class="form-check-input" type="radio" id="ecommerce_paypal_express_checkout_mode_live" name="ecommerce_paypal_express_checkout_mode" ' . $ecommerce_paypal_express_checkout_mode_live . '>
                                                                                <label class="form-check-label" for="ecommerce_paypal_express_checkout_mode_live">'. lang('Live') . '</label>
                                                                            </div>
                                                                        </div>

                                                                        <div class="col-12 col-md-6 col-lg-4 my-1">
                                                                            <label class="form-check-label" for="ecommerce_paypal_express_checkout_api_username">'. lang('API Username') . '</label>
                                                                            <input class="form-control" type="text" id="ecommerce_paypal_express_checkout_api_username" name="ecommerce_paypal_express_checkout_api_username" value="' . h($ecommerce_paypal_express_checkout_api_username) . '" size="40" maxlength="100" />
                                                                        </div>
                                                                        <div class="col-12 col-md-6 col-lg-4 my-1">
                                                                            <label class="form-check-label" for="ecommerce_paypal_express_checkout_api_password">'. lang('API Password') . '</label>
                                                                            <input class="form-control" type="password" id="ecommerce_paypal_express_checkout_api_password" name="ecommerce_paypal_express_checkout_api_password" value="' . h($ecommerce_paypal_express_checkout_api_password) . '" size="40" maxlength="100" />
                                                                        </div>
                                                                        <div class="col-12 col-md-6 col-lg-4 my-1">
                                                                            <label class="form-check-label" for="ecommerce_paypal_express_checkout_api_signature">'. lang('API Signature') . '</label>
                                                                            <input class="form-control" type="password" id="ecommerce_paypal_express_checkout_api_signature" name="ecommerce_paypal_express_checkout_api_signature" value="' . h($ecommerce_paypal_express_checkout_api_signature) . '" size="40" maxlength="100" />
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="col-12 my-1">
                                                            <div class="form-check form-switch">
                                                                <input value="1"' . $ecommerce_offline_payment_checked . ' class="form-check-input collapse-switcher" type="checkbox" id="ecommerce_offline_payment" name="ecommerce_offline_payment" data-bs-target="#ecommerce_offline_payment_row"/>
                                                                <label class="form-check-label" for="ecommerce_offline_payment">' . lang('Allow Offline Payments') . '</label>
                                                            </div>
                                                            <div class="collapse popover fade bs-popover-bottom p-0 mb-2" id="ecommerce_offline_payment_row">
                                                                <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(30px, 0px);"></div>
                                                                <div class="popover-body">
                                                                    <div class="row">
                                                                        <div class="col-12 my-1">
                                                                            <div class="form-check form-switch">
                                                                                <input value="1"' . $ecommerce_offline_payment_only_specific_orders_checked . ' class="form-check-input" type="checkbox" id="ecommerce_offline_payment_only_specific_orders" name="ecommerce_offline_payment_only_specific_orders"/>
                                                                                <label class="form-check-label" for="ecommerce_offline_payment_only_specific_orders">' . lang('Only on specific orders') . '</label>
                                                                            </div> 
                                                                        </div> 
                                                                    </div> 
                                                                </div>
                                                            </div> 
                                                        </div>
                                                        <div class="col-12 my-1"><hr/></div>
                                                        <div class="col-12 col-md-6 col-lg-4 my-1">
                                                            <label class="form-check-label" for="ecommerce_private_folder_id">'. lang('Grant Private Access') . '</label>
                                                            <select class="form-select" id="ecommerce_private_folder_id" name="ecommerce_private_folder_id">
                                                                <option value="">-' . lang(array('string'=>'Select {var:1}','vars'=>array(lang('Folder')) )) . '-</option>
                                                                ' . select_folder($ecommerce_private_folder_id, 0, 0, 0, array(), array(), 'private') . '
                                                            </select>
                                                        </div>
                                                        <div class="col-12 col-md-6 col-lg-4 my-1">
                                                            <label class="form-check-label" for="ecommerce_retrieve_order_next_page_id">'. lang('Reorder/Retrieve Order Next Page') . '</label>
                                                            <select class="form-select" id="ecommerce_retrieve_order_next_page_id" name="ecommerce_retrieve_order_next_page_id">
                                                                <option value="">-' . lang(array('string'=>'Select {var:1}','vars'=>array(lang('Page')) )) . '-</option>
                                                                ' . select_page($ecommerce_retrieve_order_next_page_id) . '
                                                            </select>
                                                        </div>
                                                        <div class="col-12 my-1"><hr/></div>
                                                        <div class="col-12 col-md-6 col-lg-3 my-1">
                                                            <label class="form-check-label" for="ecommerce_custom_product_field_1_label">'. lang(array('string'=>'Custom Product Field {var:1} Label','vars'=>array('#1') )) . '</label>
                                                            <input class="form-control" type="text" id="ecommerce_custom_product_field_1_label" name="ecommerce_custom_product_field_1_label" value="' . h($ecommerce_custom_product_field_1_label) . '" />
                                                        </div>
                                                        <div class="col-12 col-md-6 col-lg-3 my-1">
                                                            <label class="form-check-label" for="ecommerce_custom_product_field_2_label">'. lang(array('string'=>'Custom Product Field {var:1} Label','vars'=>array('#2') )) . '</label>
                                                            <input class="form-control" type="text" id="ecommerce_custom_product_field_2_label" name="ecommerce_custom_product_field_2_label" value="' . h($ecommerce_custom_product_field_2_label) . '" />
                                                        </div>
                                                        <div class="col-12 col-md-6 col-lg-3 my-1">
                                                            <label class="form-check-label" for="ecommerce_custom_product_field_3_label">'. lang(array('string'=>'Custom Product Field {var:1} Label','vars'=>array('#3') )) . '</label>
                                                            <input class="form-control" type="text" id="ecommerce_custom_product_field_3_label" name="ecommerce_custom_product_field_3_label" value="' . h($ecommerce_custom_product_field_3_label) . '" />
                                                        </div>
                                                        <div class="col-12 col-md-6 col-lg-3 my-1">
                                                            <label class="form-check-label" for="ecommerce_custom_product_field_4_label">'. lang(array('string'=>'Custom Product Field {var:1} Label','vars'=>array('#4') )) . '</label>
                                                            <input class="form-control" type="text" id="ecommerce_custom_product_field_4_label" name="ecommerce_custom_product_field_4_label" value="' . h($ecommerce_custom_product_field_4_label) . '" />
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                       
                        <div class="col-12">
                            <div class="card my-4">
                                <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
                                    ' . lang('Theme') . '
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-12 my-3">
                                            <div class="form-check form-switch">
                                                <input value="1"' . $advanced_visual_effects_checked . ' class="form-check-input" type="checkbox" id="advanced_visual_effects" name="advanced_visual_effects"/>
                                                <label class="form-check-label" for="advanced_visual_effects">' . lang('Enable Transparent Acrylic Effect') . '<br/><span class="form-text">' . lang('If the device you are using is weak in hardware, you can disable this setting to get a faster interface.') . '</span></label>
                                            </div>
                                        </div>
                                        <div class="col-12 my-1">
                                            <label class="form-label">' . lang('Custom CSS') . '</label>
                                            <div id="edit_custom">
                                                <textarea name="custom_css" id="custom_css" rows="25" cols="60" wrap="off">' . $custom_css . '</textarea>
                                                ' . get_codemirror_includes() . '
                                                ' . get_codemirror_javascript(array('id' => 'custom_css', 'code_type' => 'css')) . '
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <input type="hidden" id="submitted_button_field" name="submitted_button_field" value="submit" />          
                    <nav class="buttons navigation text-center position-sticky mb-4" style="bottom:.5rem;" aria-label="data edit buttons ">
                        <div class="container">
                            <div class=" btn-group flex-wrap justify-content-center">
                                <button type="submit" id="create_button" name="submit_save" value="Save" class="btn my-1  btn-success " data-loading-content="' . lang(array('string'=>'Saving') ) . '"><span class="material-icons me-2">save</span><span class="btn-text" >' . lang(array('string'=>'Save') ) . '</span></button>
                            </div>
                        </div>
                    </nav>

                </form>
            </div>
        </div>
    </main>
    <script>
        // SEO character counters — logic in assets/backend.src.js
        initSeoCounters([
            { sel: "#title",            counterId: "seo_c_title",            min: 50,  max: 60  },
            { sel: "#meta_description", counterId: "seo_c_meta_description", min: 150, max: 160 }
        ]);

        // Fills the additional robots.txt field with the rule that blocks every crawler.
        // The field is managed by CodeMirror, which hides the original textarea and keeps
        // its own document, so writing to textarea.value alone would not show up on screen
        // and would be overwritten on submit.
        function pgRobotsNoIndex() {
            var snippet = "User-agent: *\\nDisallow: /";
            var textarea = document.getElementById("additional_robots_content");
            var hint = document.getElementById("robots_noindex_hint");
            if (!textarea) {
                return;
            }

            // CodeMirror.fromTextArea() inserts its wrapper right after the textarea and
            // exposes the instance on that element. The walk is bounded and stays inside
            // this column, so it can never pick up the sitemap editor next door.
            var cm = null;
            var sibling = textarea.nextElementSibling;
            var hops = 0;
            while (sibling && hops < 3) {
                if (sibling.CodeMirror) {
                    cm = sibling.CodeMirror;
                    break;
                }
                sibling = sibling.nextElementSibling;
                hops++;
            }
            var current = cm ? cm.getValue() : textarea.value;

            var already = /^\\s*User-agent:\\s*\\*\\s*$[\\s\\S]*?^\\s*Disallow:\\s*\\/\\s*$/mi.test(current);
            if (already) {
                if (hint) {
                    hint.textContent = ' . json_encode(lang('This rule has already been added.')) . ';
                    hint.classList.remove("d-none");
                }
                return;
            }

            // Keep whatever the operator already wrote, append below it.
            var updated = (current.replace(/\\s+$/, "") === "") ? snippet : current.replace(/\\s+$/, "") + "\\n\\n" + snippet;
            if (cm) {
                cm.setValue(updated);
            } else {
                textarea.value = updated;
            }
            if (hint) {
                hint.textContent = ' . json_encode(lang('The entire site will be closed to search engines. It takes effect once you save.')) . ';
                hint.classList.remove("d-none");
            }
        }

        $(function() {
            $.ajax({
                contentType: "application/json",
                url: "api.php",
                data: JSON.stringify({
                    action: "get_widget_data",
                    token: software_token,
                    widget_id: "system_status"
                }),
                type: "POST",
                success: function(response) {
                    if(response.status == "success"){
                        $("#system_status_bar").html(response.data);
                    }
                }
            });
        });
    </script>' .
    output_footer();

    print $output;
    
    $liveform->remove_form('settings');

} else {
    
    validate_token_field();
    
    $hostname = $_POST['hostname'];

    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
    // Remove http:// or https:// from hostname.
    $hostname = preg_replace('/http:\/\//i', '', $hostname);
    $hostname = preg_replace('/https:\/\//i', '', $hostname);

    // if the user selected to reset encryption key, then do that
    if (
        isset($_POST['ecommerce_reset_encryption_key'])
        && $_POST['ecommerce_reset_encryption_key'] == 1
    ){
    
        // if MCrypt is disabled, then output error
        if ((extension_loaded('mcrypt') == FALSE) || (in_array('rijndael-256', mcrypt_list_algorithms()) == FALSE)) {
            output_error(lang('The encryption key could not be reset, because the MCrypt PHP extension is not enabled') . '. <a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.');
        }
        
        // get contents of config.php file in order to reset encryption key
        $config_content = file_get_contents(CONFIG_FILE_PATH);
        
        // open the config.php file so the encryption key can be reset
        $handle = @fopen(CONFIG_FILE_PATH, 'w');
        
        // if the config.php file could not be opened for writing, then output error
        if ($handle == FALSE) {
            output_error(lang(array('string'=>'The encryption key could not be reset, because the config.php file ({var:1}) is not writable. Please configure the config.php file so it can be written to and then try again. For Unix, set the permissions for the file to 777. For Windows, give the anonymous web user rights to write to and delete the file.','vars'=>array(OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/data/config.php') )) . ' <a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.');
        }
        
        $old_encryption_key = ENCRYPTION_KEY;
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
                    $query = "UPDATE orders SET card_number = '" . encrypt_credit_card_number($order['card_number'], $new_encryption_key) . "' WHERE id = '" . $order['id'] . "'";
                    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
                }
                
            // else the credit card number is not already encrypted, so encrypt it for the first time
            } else {
                $query = "UPDATE orders SET card_number = '" . encrypt_credit_card_number($order['card_number'], $new_encryption_key) . "' WHERE id = '" . $order['id'] . "'";
                $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
            }
        }
    }
    
    // if secure mode was checked then use secure url scheme
    if (isset($_POST['secure_mode']) && $_POST['secure_mode'] == 1) {
        $url_scheme = 'https://';

    // else secure mode was not checked, so use standard url scheme
    } else {
        $url_scheme = 'http://';
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





    // if null mean no software update yet so software language and theme not gonna update
    $sql_software_language ='';
    //check if not null, than update.
    $query = "SELECT * FROM config";
    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
    $row = mysqli_fetch_assoc($result);

    $software_language = $row['software_language'];
    if($software_language != NULL){
        $sql_software_language ="software_language = '" . escape($_POST['software_language']) . "',";
    }
   
    //prepare subscription_key for write to db
    $subscription_key = str_replace('-','',$_POST['subscription_key']);
    // check if subscription_key is set and subscription_key not equal to db subscription_key
    if(isset($_POST['subscription_key']) && $_POST['subscription_key'] != SUBSCRIPTION_KEY){
        //remove all session about license, we will set it from license_check() again.
        unset($_SESSION['software']['settings']['license']['last_check']);
        unset($_SESSION['software']['settings']['license']['countdown']);
        unset($_SESSION['software']['settings']['license']['expiration_date_formatted']);
        unset($_SESSION['software']['settings']['license']['status']);
        unset($_SESSION['software']['settings']['license']['error_code']);
    } 

  
    $current_indexnow_key = db("SELECT indexnow_key FROM config");
    $current_indexnow_key = ($current_indexnow_key !== null) ? $current_indexnow_key : '';
    $new_indexnow_key     = isset($_POST['indexnow_key']) ? trim($_POST['indexnow_key']) : '';
    
    // If old key exists and changed, delete old file and its DB record
    if ($current_indexnow_key !== '' && $current_indexnow_key !== $new_indexnow_key) {
        $old_file_name = $current_indexnow_key . '.txt';
        $old_file_path = FILE_DIRECTORY_PATH . '/' . $old_file_name;
    
        // Delete physical file if present
        if (file_exists($old_file_path)) {
            // Suppress warnings to avoid noisy outputs in production
            @unlink($old_file_path);
        }
    
        // Delete DB record for old file name
        db("DELETE FROM files WHERE name = '" . escape($old_file_name) . "'");
    }
    
    // If new key is provided, ensure file and DB record are in sync
    if ($new_indexnow_key !== '') {
        $new_file_name = $new_indexnow_key . '.txt';
        $new_file_path = FILE_DIRECTORY_PATH . '/' . $new_file_name;
    
        // Write the key into the file (overwrite if exists)
        $handle = @fopen($new_file_path, 'w');
        if ($handle) {
            fwrite($handle, $new_indexnow_key);
            fclose($handle);
        }
    
        // Determine file size safely
        $file_size = file_exists($new_file_path) ? (int)filesize($new_file_path) : 0;
    
        // Resolve target folder ID
        $folder_id = getPublicRootFolderId();
    
        // Fetch existing DB record by name (normalize different db() return shapes)
        $existing = db("SELECT name, folder, description, type, size, design, user
                        FROM files
                        WHERE name = '" . escape($new_file_name) . "'
                        LIMIT 1");
    
        // Normalize result to an associative array or null
        if (is_array($existing) && isset($existing[0]) && is_array($existing[0])) {
            $existing = $existing[0];
        } elseif (!is_array($existing) || (is_array($existing) && !isset($existing['name']))) {
            $existing = null;
        }
    
        // Prepare target values
        $target = [
            'folder'      => (int)$folder_id,
            'description' => 'IndexNow verification key',
            'type'        => 'txt',
            'size'        => (int)$file_size,
            'design'      => 1,
            'user'        => (int)USER_ID
        ];
    
        if ($existing) {
            // Compare field-by-field; update only if any value differs
            $needsUpdate =
                ((int)$existing['folder']      !== $target['folder'])      ||
                ((string)$existing['description'] !== $target['description']) ||
                ((string)$existing['type']        !== $target['type'])        ||
                ((int)$existing['size']        !== $target['size'])        ||
                ((int)$existing['design']      !== $target['design'])      ||
                ((int)$existing['user']        !== $target['user']);
        
            if ($needsUpdate) {
                db("UPDATE files SET
                        folder = '" . escape($target['folder']) . "',
                        description = '" . escape($target['description']) . "',
                        type = '" . escape($target['type']) . "',
                        size = '" . escape($target['size']) . "',
                        design = '" . escape($target['design']) . "',
                        user = '" . escape($target['user']) . "',
                        timestamp = UNIX_TIMESTAMP()
                    WHERE name = '" . escape($new_file_name) . "'");
            }
        } else {
            // Insert new record only if it does not exist
            db("INSERT INTO files (
                    name, folder, description, type, size, design, user, timestamp
                ) VALUES (
                    '" . escape($new_file_name) . "',
                    '" . escape($target['folder']) . "',
                    '" . escape($target['description']) . "',
                    '" . escape($target['type']) . "',
                    '" . escape($target['size']) . "',
                    '" . escape($target['design']) . "',
                    '" . escape($target['user']) . "',
                    UNIX_TIMESTAMP()
                )");
        }
    
        // Log activity once per operation (create or update); message reflects the file name
        log_activity(
            lang(['string' => 'IndexNow file ({var:1}) was synchronized', 'vars' => $new_file_name]),
            $_SESSION['sessionusername']
        );
    }



    // Helper function to get POST value or NULL if not set
    function post_value($key) {
        return array_key_exists($key, $_POST) ? $_POST[$key] : NULL;
    }

    // ── Performance monitoring ───────────────────────────────────────────
    //
    // Switching it off discards what was collected. Keeping the rows would
    // leave a report that silently ages: the screen still opens, the numbers
    // still look current, and nothing says they stopped being updated weeks
    // ago. Stale data presented as live is worse than no data.
    //
    // Only on the transition, so re-saving the screen while it is already off
    // does not fire the queries again.
    $perf_monitor_new = post_value('perf_monitor') ? 1 : 0;
    $perf_monitor_old = (int) db_value("SELECT perf_monitor FROM config");

    if ($perf_monitor_old === 1 && $perf_monitor_new === 0) {
        if (db_item("SHOW TABLES LIKE 'perf_stats'")) {
            db("TRUNCATE perf_stats");
        }

        if (db_item("SHOW TABLES LIKE 'perf_log'")) {
            db("TRUNCATE perf_log");
        }

        log_activity(lang('performance monitoring was turned off and its records were cleared'), $_SESSION['sessionusername']);
    }

    // ── Firewall settings ────────────────────────────────────────────────
    // Built as a fragment rather than inlined, so an install that has not run
    // the 2026.2.4 upgrade simply saves nothing here instead of failing the
    // whole settings save with an unknown-column error.
    $sql_waf_settings = '';

    if (function_exists('waf_table_has_column')
        && waf_table_has_column('config', 'waf_enabled')
    ) {
        // Clamp the numeric fields. A rate limit of 0 would lock every
        // visitor out of the site the moment blocking is turned on.
        $waf_post_mode = (post_value('waf_mode') === 'block') ? 'block' : 'monitor';

        $waf_post_sensitivity = post_value('waf_sensitivity');

        if (!in_array($waf_post_sensitivity, array('low', 'medium', 'high'), true)) {
            $waf_post_sensitivity = 'medium';
        }

        $waf_post_requests  = max(10, min(60000, (int) post_value('waf_rate_limit_requests')));
        $waf_post_sensitive = max(3,  min(60000, (int) post_value('waf_rate_limit_sensitive')));
        $waf_post_threshold = max(1,  min(1000,  (int) post_value('waf_auto_ban_threshold')));
        $waf_post_minutes   = max(1,  min(43200, (int) post_value('waf_auto_ban_minutes')));
        $waf_post_retention = max(1,  min(3650,  (int) post_value('waf_log_retention_days')));
        $waf_post_max_rows  = max(500, min(5000000, (int) post_value('waf_log_max_rows')));

        $sql_waf_settings =
            "waf_enabled = '" . escape(post_value('waf_enabled') ? 1 : 0) . "',
             waf_mode = '" . escape($waf_post_mode) . "',
             waf_sensitivity = '" . escape($waf_post_sensitivity) . "',
             waf_signature_scan = '" . escape(post_value('waf_signature_scan') ? 1 : 0) . "',
             waf_rate_limit = '" . escape(post_value('waf_rate_limit') ? 1 : 0) . "',
             waf_rate_limit_requests = " . $waf_post_requests . ",
             waf_rate_limit_sensitive = " . $waf_post_sensitive . ",
             waf_auto_ban = '" . escape(post_value('waf_auto_ban') ? 1 : 0) . "',
             waf_auto_ban_threshold = " . $waf_post_threshold . ",
             waf_auto_ban_minutes = " . $waf_post_minutes . ",
             waf_block_attack_tools = '" . escape(post_value('waf_block_attack_tools') ? 1 : 0) . "',
             waf_verify_bots = '" . escape(post_value('waf_verify_bots') ? 1 : 0) . "',
             waf_trusted_proxies = '" . escape(trim((string) post_value('waf_trusted_proxies'))) . "',
             waf_exclusions = '" . escape(trim((string) post_value('waf_exclusions'))) . "',
             waf_blocked_agents = '" . escape(trim((string) post_value('waf_blocked_agents'))) . "',
             waf_log_retention_days = " . $waf_post_retention . ",
             waf_log_max_rows = " . $waf_post_max_rows . ",";
    }

    $query =
        "UPDATE config
        SET
            url_scheme = '$url_scheme',
            hostname = '" . escape(post_value('hostname')) . "',
            email_address = '" . escape(post_value('email_address')) . "',
            title = '" . escape(post_value('title')) . "',
            meta_description = '" . escape(post_value('meta_description')) . "',
            mobile = '" . escape(post_value('mobile')) . "',
            search_type = '" . escape(post_value('search_type')) . "',
            social_networking = '" . escape(post_value('social_networking')) . "',
            social_networking_type = '" . escape(post_value('social_networking_type')) . "',
            social_networking_facebook = '" . escape(post_value('social_networking_facebook')) . "',
            social_networking_twitter = '" . escape(post_value('social_networking_twitter')) . "',
            social_networking_linkedin = '" . escape(post_value('social_networking_linkedin')) . "',
            social_networking_whatsapp = '" . escape(post_value('social_networking_whatsapp')) . "',
            social_networking_telegram = '" . escape(post_value('social_networking_telegram')) . "',
            social_networking_pinterest = '" . escape(post_value('social_networking_pinterest')) . "',
            social_networking_reddit = '" . escape(post_value('social_networking_reddit')) . "',
            social_networking_email = '" . escape(post_value('social_networking_email')) . "',
            social_networking_code = '" . escape(post_value('social_networking_code')) . "',
            captcha = '" . escape(post_value('captcha')) . "',
            auto_dialogs = '" . escape(post_value('auto_dialogs')) . "',
            mass_deletion = '" . escape(post_value('mass_deletion')) . "',
            strong_password = '" . escape(post_value('strong_password')) . "',
            password_hint = '" . escape(post_value('password_hint')) . "',
            remember_me = '" . escape(post_value('remember_me')) . "',
            forgot_password_link = '" . escape(post_value('forgot_password_link')) . "',
            proxy_address = '" . escape(post_value('proxy_address')) . "',
            badge_label = '" . escape(post_value('badge_label')) . "',
            timezone = '" . escape(post_value('timezone')) . "',
            date_format = '" . escape(post_value('date_format')) . "',
            time_format = '" . escape(post_value('time_format')) . "',
            organization_name = '" . escape(post_value('organization_name')) . "',
            organization_address_1 = '" . escape(post_value('organization_address_1')) . "',
            organization_address_2 = '" . escape(post_value('organization_address_2')) . "',
            organization_city = '" . escape(post_value('organization_city')) . "',
            organization_state = '" . escape(post_value('organization_state')) . "',
            organization_zip_code = '" . escape(post_value('organization_zip_code')) . "',
            organization_country = '" . escape(post_value('organization_country')) . "',
            opt_in_label = '" . escape(post_value('opt_in_label')) . "',
            plain_text_email_campaign_footer = '" . escape(trim(post_value('plain_text_email_campaign_footer'))) . "',
            debug = '" . escape(post_value('debug')) . "',
            visitor_tracking = '" . escape(post_value('visitor_tracking')) . "',
            perf_monitor = '" . escape($perf_monitor_new) . "',
            allowed_bots = '" . escape(trim(post_value('allowed_bots'))) . "',
            block_unknown_bots = '" . escape(post_value('block_unknown_bots')) . "',
            " . $sql_waf_settings . "
            tracking_code_duration = '" . e(post_value('tracking_code_duration')) . "',
            pay_per_click_flag = '" . escape(post_value('pay_per_click_flag')) . "',
            stats_url = '" . escape(post_value('stats_url')) . "',
            google_analytics = '" . escape(post_value('google_analytics')) . "',
            google_analytics_web_property_id = '" . escape(post_value('google_analytics_web_property_id')) . "',
            page_editor_version = '" . escape(post_value('page_editor_version')) . "',
            page_editor_font = '" . escape(post_value('page_editor_font')) . "',
            page_editor_font_size = '" . escape(post_value('page_editor_font_size')) . "',
            page_editor_font_style = '" . escape(post_value('page_editor_font_style')) . "',
            page_editor_font_color = '" . escape(post_value('page_editor_font_color')) . "',
            page_editor_background_color = '" . escape(post_value('page_editor_background_color')) . "',
            registration_contact_group_id = '" . escape(post_value('registration_contact_group_id')) . "',
            registration_email_address = '" . escape(post_value('registration_email_address')) . "',
            member_id_label = '" . escape(post_value('member_id_label')) . "',
            membership_contact_group_id = '" . escape(post_value('membership_contact_group_id')) . "',
            membership_email_address = '" . escape(post_value('membership_email_address')) . "',
            membership_expiration_warning_email = '" . escape(post_value('membership_expiration_warning_email')) . "',
            membership_expiration_warning_email_subject = '" . escape(post_value('membership_expiration_warning_email_subject')) . "',
            membership_expiration_warning_email_page_id = '" . escape(post_value('membership_expiration_warning_email_page_id')) . "',
            membership_expiration_warning_email_days_before_expiration = '" . escape(post_value('membership_expiration_warning_email_days_before_expiration')) . "',
            ecommerce = '" . escape(post_value('ecommerce')) . "',
            ecommerce_multicurrency = '" . escape(post_value('ecommerce_multicurrency')) . "',
            ecommerce_tax = '" . escape(post_value('ecommerce_tax')) . "',
            ecommerce_tax_exempt = '" . escape(post_value('ecommerce_tax_exempt')) . "',
            ecommerce_tax_exempt_label = '" . escape(post_value('ecommerce_tax_exempt_label')) . "',
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
            ecommerce_email_address = '" . escape(post_value('ecommerce_email_address')) . "',
            ecommerce_gift_card = '" . escape(post_value('ecommerce_gift_card')) . "',
            ecommerce_gift_card_validity_days = '" . escape($gift_card_validity_days) . "',
            ecommerce_givex = '" . escape(post_value('ecommerce_givex')) . "',
            ecommerce_givex_primary_hostname = '" . escape(post_value('ecommerce_givex_primary_hostname')) . "',
            ecommerce_givex_secondary_hostname = '" . escape(post_value('ecommerce_givex_secondary_hostname')) . "',
            ecommerce_givex_user_id = '" . escape(trim(post_value('ecommerce_givex_user_id'))) . "',
            ecommerce_givex_password = '" . escape(trim(post_value('ecommerce_givex_password'))) . "',
            ecommerce_credit_debit_card = '" . escape(post_value('ecommerce_credit_debit_card')) . "',
            ecommerce_american_express = '" . escape(post_value('ecommerce_american_express')) . "',
            ecommerce_diners_club = '" . escape(post_value('ecommerce_diners_club')) . "',
            ecommerce_discover_card = '" . escape(post_value('ecommerce_discover_card')) . "',
            ecommerce_mastercard = '" . escape(post_value('ecommerce_mastercard')) . "',
            ecommerce_visa = '" . escape(post_value('ecommerce_visa')) . "',
            ecommerce_troy = '" . escape(post_value('ecommerce_troy')) . "',
            ecommerce_show_product_images = '" . escape(post_value('ecommerce_show_product_images')) . "',
            barcode_enabled        = '" . escape(post_value('barcode_enabled'))        . "',
            barcode_default_type   = '" . escape(trim(post_value('barcode_default_type') ?: 'CODE128')) . "',
            barcode_label_width    = '" . (int)(post_value('barcode_label_width')  ?: 60) . "',
            barcode_label_height   = '" . (int)(post_value('barcode_label_height') ?: 40) . "',
            barcode_label_template = '" . escape(post_value('barcode_label_template')) . "',
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
            ecommerce_private_folder_id = '" . e(post_value('ecommerce_private_folder_id')) . "',
            ecommerce_retrieve_order_next_page_id = '" . escape(post_value('ecommerce_retrieve_order_next_page_id')) . "',
            ecommerce_reward_program = '" . escape(post_value('ecommerce_reward_program')) . "',
            ecommerce_reward_program_points = '" . escape(post_value('ecommerce_reward_program_points')) . "',
            ecommerce_reward_program_membership = '" . escape(post_value('ecommerce_reward_program_membership')) . "',
            ecommerce_reward_program_membership_days = '" . escape(post_value('ecommerce_reward_program_membership_days')) . "',
            ecommerce_reward_program_email = '" . escape(post_value('ecommerce_reward_program_email')) . "',
            ecommerce_reward_program_email_bcc_email_address = '" . escape(post_value('ecommerce_reward_program_email_bcc_email_address')) . "',
            ecommerce_reward_program_email_subject = '" . escape(post_value('ecommerce_reward_program_email_subject')) . "',
            ecommerce_reward_program_email_page_id = '" . escape(post_value('ecommerce_reward_program_email_page_id')) . "',
            ecommerce_custom_product_field_1_label = '" . escape(post_value('ecommerce_custom_product_field_1_label')) . "',
            ecommerce_custom_product_field_2_label = '" . escape(post_value('ecommerce_custom_product_field_2_label')) . "',
            ecommerce_custom_product_field_3_label = '" . escape(post_value('ecommerce_custom_product_field_3_label')) . "',
            ecommerce_custom_product_field_4_label = '" . escape(post_value('ecommerce_custom_product_field_4_label')) . "',
            forms = '" . escape(post_value('forms')) . "',
            calendars = '" . escape(post_value('calendars')) . "',
            ads = '" . escape(post_value('ads')) . "',
            affiliate_program = '" . escape(post_value('affiliate_program')) . "',
            affiliate_default_commission_rate = '" . escape(post_value('affiliate_default_commission_rate')) . "',
            affiliate_automatic_approval = '" . escape(post_value('affiliate_automatic_approval')) . "',
            affiliate_contact_group_id = '" . escape(post_value('affiliate_contact_group_id')) . "',
            affiliate_email_address = '" . escape(post_value('affiliate_email_address')) . "',
            affiliate_group_offer_id = '" . escape(post_value('affiliate_group_offer_id')) . "',
            additional_sitemap_content = '" . escape(trim(post_value('additional_sitemap_content'))) . "',
            additional_robots_content = '" . escape(trim(post_value('additional_robots_content'))) . "',
            subscription_id = '" . escape(post_value('subscription_id')) . "',
            subscription_key = '" . escape($subscription_key) . "',
            $sql_software_language 
            custom_css = '" . escape(post_value('custom_css')) . "',
            strutured_data = '" . escape(post_value('strutured_data')) . "',
            advanced_visual_effects = '" . escape(post_value('advanced_visual_effects')) . "',
            last_modified_user_id = '" . USER_ID . "',
            enable_parasut = '" . escape(post_value('enable_parasut')) . "',
            parasut_tc_in_field = '" . escape(post_value('parasut_tc_in_field')) . "',
            parasut_client_id = '" . escape(trim(post_value('parasut_client_id'))) . "',
            parasut_client_secret = '" . escape(trim(post_value('parasut_client_secret'))) . "',
            parasut_username = '" . escape(trim(post_value('parasut_username'))) . "',
            parasut_password = '" . escape(trim(post_value('parasut_password'))) . "',
            parasut_company_id = '" . escape(trim(post_value('parasut_company_id'))) . "',
            parasut_use_sandbox = '" . escape(post_value('parasut_use_sandbox')) . "',
            parasut_default_product_id = '" . escape(trim(post_value('parasut_default_product_id'))) . "',
            parasut_default_warehouse_id = '" . escape(trim(post_value('parasut_default_warehouse_id'))) . "',
            enable_iyzipay_protected_currency = '" . escape(post_value('enable_iyzipay_protected_currency')) . "',
            iyzipay_protected_currency_code = '" . escape(post_value('iyzipay_protected_currency_code')) . "',
            indexnow_key = '" . escape(post_value('indexnow_key')) . "',

            last_modified_timestamp = UNIX_TIMESTAMP()";

    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
    
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
    
    // ── IP allow / block lists ───────────────────────────────────────────
    //
    // This screen only owns MANUAL entries. The old code TRUNCATEd the whole
    // table, which would now also delete every automatic temporary ban and the
    // entire allow list — so a routine settings save would quietly release
    // every address the firewall had banned, and drop the operator's own
    // lockout protection with it.
    $waf_ip_columns_ready = (function_exists('waf_table_has_column')
        && waf_table_has_column('banned_ip_addresses', 'list_type'));

    if ($waf_ip_columns_ready) {
        $query = "DELETE FROM banned_ip_addresses WHERE source = 'manual'";
    } else {
        $query = "TRUNCATE banned_ip_addresses";
    }

    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');

    // assume that no banned ip addresses are invalid until we determine otherwise
    $invalid_banned_ip_addresses = false;

    $ip_lists_to_save = array('block' => 'banned_ip_addresses');

    if ($waf_ip_columns_ready) {
        $ip_lists_to_save['allow'] = 'allowed_ip_addresses';
    }

    foreach ($ip_lists_to_save as $list_type => $field_name) {
        $submitted = isset($_POST[$field_name]) ? $_POST[$field_name] : '';
        $entries = preg_split('/[\r\n,;]+/', $submitted);
        $valid_entries = array();

        foreach ($entries as $entry) {
            $entry = trim($entry);

            if ($entry === '') {
                continue;
            }

            // Validation lives in waf.php so the format accepted here and the
            // format matched at request time can never drift apart. It accepts
            // a plain address, an IPv4 wildcard, or CIDR notation for either
            // address family — the old check here required exactly four
            // dot-separated parts, so it silently discarded every CIDR range
            // and every IPv6 address an operator typed.
            $is_valid = function_exists('waf_valid_ip_pattern')
                ? waf_valid_ip_pattern($entry)
                : (bool) preg_match('/^(?:\d{1,3}|\*)(?:\.(?:\d{1,3}|\*)){3}$/', $entry);

            if (!$is_valid) {
                $invalid_banned_ip_addresses = true;
                continue;
            }

            $valid_entries[$entry] = $entry;
        }

        foreach ($valid_entries as $entry) {
            if ($waf_ip_columns_ready) {
                // ON DUPLICATE KEY so the unique index added in 2026.2.7
                // cannot turn a re-saved settings screen into a hard
                // "Query failed" error. Re-saving simply refreshes the row.
                $query = "INSERT INTO banned_ip_addresses
                            (ip_address, list_type, source, created_at)
                          VALUES
                            ('" . escape($entry) . "', '" . escape($list_type) . "', 'manual', " . time() . ")
                          ON DUPLICATE KEY UPDATE created_at = VALUES(created_at)";
            } else {
                $query = "INSERT INTO banned_ip_addresses (ip_address) VALUES('" . escape($entry) . "')";
            }

            $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
        }
    }

    if (isset($recipient['id'])) {
        $tracking_numbers = $liveform->get('ship_to_id_' . $recipient['id'] . '_tracking_numbers');
        $tracking_numbers = explode(',', $tracking_numbers);
        
        foreach ($tracking_numbers as $tracking_number) {
            if ($tracking_number) {
                $recipient['tracking_numbers'][] = $tracking_number;
            }
        }
    }
    
    log_activity(lang('settings were modified'), $_SESSION['sessionusername']);
    
    $liveform->add_notice(lang('The Site Settings have been saved.'));
    
    // if one or more banned IP addresses were invalid, then prepare notice to warn user
    if ($invalid_banned_ip_addresses == true) {
        $liveform->add_notice(lang('One or more banned IP addresses were not added because they were invalid.') );
    }
    
    // forward user back to settings screen
    // we are using $url_scheme because we want to make sure we use the scheme they just selected
    header('Location: ' . $url_scheme . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/settings.php');
}