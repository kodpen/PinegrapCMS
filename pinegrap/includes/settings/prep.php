<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Every value the settings screens read out of the config row, and the
 * option lists built from it. Included by includes/settings/screen.php before
 * the category module, so the moved markup finds the same variables it always
 * did. Moved out of settings.php on 2026-09-12; the code is unchanged.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_SETTINGS_ENTRY')) {
    exit;
}



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
    $banned_email_addresses = (string) ($row['banned_email_addresses'] ?? '');
    $remember_me_device_limit = ($row['remember_me_device_limit'] ?? 0);
    $software_update_channel = ((($row['software_update_channel'] ?? 'stable') === 'beta') ? 'beta' : 'stable');
    $remember_me_device_limit_enabled = ($row['remember_me_device_limit_enabled'] ?? 0);
    $remember_me_device_limit_strict = ($row['remember_me_device_limit_strict'] ?? 0);
    $forgot_password_link = $row['forgot_password_link'];
    $oauth_google_enabled = $row['oauth_google_enabled'];
    $oauth_google_client_id = $row['oauth_google_client_id'];
    // Shown in the field like the other API secrets on this screen (site
    // convention - the operator preferred seeing it over a keep-blank rule);
    // stored encrypted at rest either way.
    $oauth_google_client_secret_value = '';
    if (($row['oauth_google_client_secret'] ?? '') !== '') {
        $oauth_google_client_secret_value = (string) decode_ssl_keys($row['oauth_google_client_secret'], ($row['oauth_google_secret_iv'] ?? ''));
    }
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

    // MailChimp, moved in from mailchimp_settings.php. The store id falls back
    // to the site's own hostname because that is the name the store is created
    // with and the screen has always offered it as the default.
    $mailchimp = $row['mailchimp'];
    $mailchimp_key = $row['mailchimp_key'];
    $mailchimp_list_id = $row['mailchimp_list_id'];
    $mailchimp_store_id = ($row['mailchimp_store_id'] !== '') ? $row['mailchimp_store_id'] : HOSTNAME_SETTING;
    $mailchimp_sync_days = $row['mailchimp_sync_days'];
    $mailchimp_sync_limit = $row['mailchimp_sync_limit'];
    $mailchimp_automation = $row['mailchimp_automation'];

    $mailchimp_checked = ($mailchimp == 1) ? ' checked="checked"' : '';
    $mailchimp_automation_checked = ($mailchimp_automation == 1) ? ' checked="checked"' : '';
    $visitor_tracking = $row['visitor_tracking'];
    $allowed_bots = isset($row['allowed_bots']) ? $row['allowed_bots'] : '';
    $block_unknown_bots = isset($row['block_unknown_bots']) ? (int)$row['block_unknown_bots'] : 1;

    // ── Live chat settings ───────────────────────────────────────────────
    // The columns arrive with the 2026.4.2/2026.4.3 upgrades; thanks to the
    // isset guards the screen still opens fine on installs that have not
    // upgraded.
    $chat_enabled = isset($row['chat_enabled']) ? (int) $row['chat_enabled'] : 0;
    $chat_site_enabled = isset($row['chat_site_enabled']) ? (int) $row['chat_site_enabled'] : 0;
    $chat_operator_user_id = isset($row['chat_operator_user_id']) ? (int) $row['chat_operator_user_id'] : 0;
    $chat_welcome_message = isset($row['chat_welcome_message']) ? $row['chat_welcome_message'] : '';
    $chat_offline_email = isset($row['chat_offline_email']) ? (int) $row['chat_offline_email'] : 1;
    $chat_captcha = isset($row['chat_captcha']) ? (int) $row['chat_captcha'] : 1;
    $chat_retention_days = isset($row['chat_retention_days']) ? (int) $row['chat_retention_days'] : 60;
    $chat_widget_theme = isset($row['chat_widget_theme']) ? $row['chat_widget_theme'] : 'auto';
    $chat_widget_color = isset($row['chat_widget_color']) ? $row['chat_widget_color'] : '#0d6efd';
    $chat_widget_icon = isset($row['chat_widget_icon']) ? $row['chat_widget_icon'] : 'chat';
    $chat_widget_title = isset($row['chat_widget_title']) ? $row['chat_widget_title'] : '';
    $chat_allow_files = isset($row['chat_allow_files']) ? (int) $row['chat_allow_files'] : 0;
    $chat_allow_images = isset($row['chat_allow_images']) ? (int) $row['chat_allow_images'] : 0;
    $chat_visitor_image_limit = isset($row['chat_visitor_image_limit']) ? (int) $row['chat_visitor_image_limit'] : 5;
    $chat_upload_folder_id = isset($row['chat_upload_folder_id']) ? (int) $row['chat_upload_folder_id'] : 0;
    $product_upload_folder_id = isset($row['product_upload_folder_id']) ? (int) $row['product_upload_folder_id'] : 0;

    // ── Application API ─────────────────────────────────────────────────
    // The five columns arrive with the 2026.4.4 upgrade. Until it has run the
    // card shows a note instead of its switches; the defaults here are the
    // ones includes/api/bootstrap.php falls back to without the columns.
    $api_settings_ready     = isset($row['api_enabled']);
    $api_enabled            = isset($row['api_enabled']) ? (int) $row['api_enabled'] : 1;
    $api_require_https      = isset($row['api_require_https']) ? (int) $row['api_require_https'] : 1;
    $api_openapi_public     = isset($row['api_openapi_public']) ? (int) $row['api_openapi_public'] : 0;
    $api_log_retention_days = isset($row['api_log_retention_days']) ? (int) $row['api_log_retention_days'] : 30;
    $api_upload_folder_id   = isset($row['api_upload_folder_id']) ? (int) $row['api_upload_folder_id'] : 0;

    // ── Image limits ────────────────────────────────────
    // Read through pg_image_settings() rather than straight from $row, so the
    // screen shows exactly the numbers the engine will use — including on an
    // install that has the code but not the 2026.4.4 columns, where the block
    // below is hidden and the shipped defaults apply.
    $image_settings_ready = pg_image_settings_ready();
    $image_settings       = pg_image_settings();

    $image_library = '';

    if (class_exists('Imagick')) {
        $image_library = 'Imagick';
    } elseif (extension_loaded('gd')) {
        $image_library = 'GD';
    }

    // ── Firewall settings ────────────────────────────────────────────────
    // Every read is guarded: an install that has not run the 2026.2.4 upgrade
    // still renders this screen, it just shows the defaults.
    $perf_monitor_setting             = isset($row['perf_monitor']) ? (int)$row['perf_monitor'] : 1;
    $job_dispatch_enabled     = isset($row['job_dispatch_enabled']) ? (int)$row['job_dispatch_enabled'] : 0;
    $job_dispatch             = isset($row['job_dispatch']) ? (string)$row['job_dispatch'] : '';
    $waf_enabled              = isset($row['waf_enabled']) ? (int)$row['waf_enabled'] : 0;
    $waf_mode                 = isset($row['waf_mode']) ? $row['waf_mode'] : 'monitor';
    $waf_sensitivity          = isset($row['waf_sensitivity']) ? $row['waf_sensitivity'] : 'medium';
    $waf_signature_scan       = isset($row['waf_signature_scan']) ? (int)$row['waf_signature_scan'] : 1;
    $waf_rate_limit           = isset($row['waf_rate_limit']) ? (int)$row['waf_rate_limit'] : 1;
    $waf_rate_limit_requests  = isset($row['waf_rate_limit_requests']) ? (int)$row['waf_rate_limit_requests'] : 300;
    $waf_rate_limit_sensitive = isset($row['waf_rate_limit_sensitive']) ? (int)$row['waf_rate_limit_sensitive'] : 30;
    $waf_rate_limit_api       = isset($row['waf_rate_limit_api']) ? (int)$row['waf_rate_limit_api'] : 180;
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

    // Sign-in throttle arrives with 2026.4.4. Column presence is the schema
    // probe, like waf_enabled above; without it the block is not rendered at
    // all rather than offering a switch that saves nowhere.
    $login_throttle_ready     = isset($row['login_throttle']);
    $login_throttle           = isset($row['login_throttle']) ? (int)$row['login_throttle'] : 1;
    $login_throttle_attempts  = isset($row['login_throttle_attempts']) ? (int)$row['login_throttle_attempts'] : 10;
    $login_throttle_minutes   = isset($row['login_throttle_minutes']) ? (int)$row['login_throttle_minutes'] : 15;
    $login_throttle_lockout   = isset($row['login_throttle_lockout']) ? (int)$row['login_throttle_lockout'] : 60;

    // Security headers, the plain-text block log, the concurrency and ban
    // ceilings and the sign-in question arrive together in one upgrade step;
    // one column stands for the set, like waf_enabled above.
    $security_ready               = isset($row['security_headers']);
    $security_headers             = isset($row['security_headers']) ? (int)$row['security_headers'] : 1;
    $security_frame_protection    = isset($row['security_frame_protection']) ? (int)$row['security_frame_protection'] : 1;
    $security_hsts                = isset($row['security_hsts']) ? (int)$row['security_hsts'] : 0;
    $security_csp_mode            = isset($row['security_csp_mode']) ? (string)$row['security_csp_mode'] : 'report';
    $security_csp_policy          = isset($row['security_csp_policy']) ? (string)$row['security_csp_policy'] : '';
    $waf_text_log                 = isset($row['waf_text_log']) ? (int)$row['waf_text_log'] : 1;
    $waf_inflight_limit           = isset($row['waf_inflight_limit']) ? (int)$row['waf_inflight_limit'] : 3;
    $waf_auto_ban_max_minutes     = isset($row['waf_auto_ban_max_minutes']) ? (int)$row['waf_auto_ban_max_minutes'] : 10080;
    $login_throttle_captcha_after = isset($row['login_throttle_captcha_after']) ? (int)$row['login_throttle_captcha_after'] : 3;

    // AI bot switches arrive with 2026.4.4; column presence is the schema
    // probe, like waf_enabled above.
    $waf_ai_ready             = isset($row['waf_allow_ai_fetchers']);
    $waf_allow_ai_fetchers    = isset($row['waf_allow_ai_fetchers']) ? (int)$row['waf_allow_ai_fetchers'] : 1;
    $waf_allow_ai_search      = isset($row['waf_allow_ai_search']) ? (int)$row['waf_allow_ai_search'] : 1;
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
    $ecommerce_offline_payment_cancel_days = (int) ($row['ecommerce_offline_payment_cancel_days'] ?? 0);
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
    $enable_parasut = $row['enable_parasut'] ?? 0;
    $parasut_tc_in_field = $row['parasut_tc_in_field'] ?? 'do not use';
    $parasut_client_id = $row['parasut_client_id'] ?? '';
    $parasut_username = $row['parasut_username'] ?? '';
    // The secret and the password are never handed back to the screen - the form
    // used to render them into value attributes, which put them in the page
    // source. All the screen gets is whether something is stored; an empty box
    // means "keep what is there" (see pg_parasut_credentials_for_save()).
    $parasut_credentials_stored = (($row['parasut_credentials_enc'] ?? '') !== '');
    $parasut_credential_placeholder = $parasut_credentials_stored ? lang('Saved') : '';
    $parasut_credential_help = $parasut_credentials_stored
        ? lang('The secret and the password are stored and are not shown. Leave these boxes empty to keep them.')
        : lang('The secret and the password are stored encrypted and are never shown again after saving.');
    $parasut_company_id = $row['parasut_company_id'] ?? '';
    $erp_enabled = $row['erp_enabled'] ?? 0;
    $erp_enabled_checked = ($erp_enabled == 1) ? ' checked="checked"' : '';
    $erp_default_series = $row['erp_default_series'] ?? 'PGF';
    $erp_web_address = $row['erp_web_address'] ?? '';
    $erp_seller_vkn = $row['erp_seller_vkn'] ?? '';
    $erp_seller_tax_office = $row['erp_seller_tax_office'] ?? '';
    $parasut_default_product_id  = $row['parasut_default_product_id']  ?? '';
    $parasut_default_warehouse_id = $row['parasut_default_warehouse_id'] ?? '';
    $enable_iyzipay_protected_currency = $row['enable_iyzipay_protected_currency'];
    $iyzipay_protected_currency_code = $row['iyzipay_protected_currency_code'];
    $indexnow_key = $row['indexnow_key'];

    // Structured data settings (2026.4.4). Read with fallbacks so this screen
    // renders unchanged on a database that has not run the upgrade; the
    // fields themselves are only shown when the columns exist.
    $og_default_image = $row['og_default_image'] ?? '';
    $app_icon = $row['app_icon'] ?? '';
    $organization_logo = $row['organization_logo'] ?? '';
    $merchant_country = $row['merchant_country'] ?? '';
    $merchant_shipping_rate = (int) ($row['merchant_shipping_rate'] ?? -1);
    $merchant_transit_days_min = (int) ($row['merchant_transit_days_min'] ?? 1);
    $merchant_transit_days_max = (int) ($row['merchant_transit_days_max'] ?? 3);
    $merchant_return_days = (int) ($row['merchant_return_days'] ?? -1);
    $merchant_return_fees = (int) ($row['merchant_return_fees'] ?? 0);
    $site_custom_jsonld = $row['custom_jsonld'] ?? '';

    // ── SEO card: share-image pickers ────────────────────────────────────────
    // Only built where the columns behind them exist: a control that saves
    // nothing is worse than no control at all.
    $output_share_image_pickers = '';
    $output_app_icon_picker = '';
    $output_structured_data_dependent = '';

    if (waf_table_has_column('config', 'og_default_image')) {
        // The pickers list the site's image files. A stored value that no
        // longer matches anything (an old hand-typed address) is kept as its
        // own option, so opening and saving this screen cannot silently
        // clear it.
        $seo_image_files = db_items(
            "SELECT name FROM files
            WHERE
                ((type = 'jpg') || (type = 'jpeg') || (type = 'png') || (type = 'gif') || (type = 'webp') || (type = 'avif') || (type = 'bmp'))
                AND (attachment = 0)
            ORDER BY name ASC");

        $og_image_options = '<option value="">' . lang('(not selected)') . '</option>';
        $logo_image_options = '<option value="">' . lang('(not selected)') . '</option>';
        $og_value_matched = ($og_default_image == '');
        $logo_value_matched = ($organization_logo == '');

        foreach ($seo_image_files as $seo_image_file) {
            $og_image_options .= '<option value="' . h($seo_image_file['name']) . '"' . (($seo_image_file['name'] == $og_default_image) ? ' selected="selected"' : '') . '>' . h($seo_image_file['name']) . '</option>';
            $logo_image_options .= '<option value="' . h($seo_image_file['name']) . '"' . (($seo_image_file['name'] == $organization_logo) ? ' selected="selected"' : '') . '>' . h($seo_image_file['name']) . '</option>';

            if ($seo_image_file['name'] == $og_default_image) {
                $og_value_matched = true;
            }

            if ($seo_image_file['name'] == $organization_logo) {
                $logo_value_matched = true;
            }
        }

        if (!$og_value_matched) {
            $og_image_options .= '<option value="' . h($og_default_image) . '" selected="selected">' . h($og_default_image) . '</option>';
        }

        if (!$logo_value_matched) {
            $logo_image_options .= '<option value="' . h($organization_logo) . '" selected="selected">' . h($organization_logo) . '</option>';
        }

        // The icon the panel wears once it is installed on a phone or a desktop.
        // Same list as the share images, because it is the same question - which
        // of this site's own pictures - and an operator should not have to learn
        // two different pickers for it. Sizing is not asked about: the launcher
        // wants exact squares and manifest_icon.php draws them, fitting the
        // picture whole rather than cropping it.
        if (waf_table_has_column('config', 'app_icon')) {

            $app_icon_options = '<option value="">' . lang('(not selected)') . '</option>';
            $app_icon_matched = ($app_icon == '');

            foreach ($seo_image_files as $seo_image_file) {

                $app_icon_options .= '<option value="' . h($seo_image_file['name']) . '"' . (($seo_image_file['name'] == $app_icon) ? ' selected="selected"' : '') . '>' . h($seo_image_file['name']) . '</option>';

                if ($seo_image_file['name'] == $app_icon) {
                    $app_icon_matched = true;
                }
            }

            if (!$app_icon_matched) {
                $app_icon_options .= '<option value="' . h($app_icon) . '" selected="selected">' . h($app_icon) . '</option>';
            }

            $output_app_icon_picker = '
                                        <div id="pgsub-app" class="col-12  mb-0"><h6 class="text-muted text-uppercase">' . lang('Installed Application') . '</h6></div>
                                        <div class="pg-f-md">
                                            <label for="app_icon" class="form-label">' . lang('Application Icon') . '</label>
                                            <select name="app_icon" id="app_icon" class="form-select">' . $app_icon_options . '</select>
                                            <div class="form-text">' . lang('Shown on the home screen when the panel is installed as an application. The picture is fitted whole onto a square, so nothing is cropped; a square picture of at least 512x512 looks best.') . '</div>
                                        </div>';
        }

        $output_share_image_pickers = '
                                        <div id="pgsub-social" class="col-12  mb-0"><h6 class="text-muted text-uppercase">' . lang('Social Sharing') . '</h6></div>
                                        <div class="pg-f-md">
                                            <label for="og_default_image" class="form-label">' . lang('Default Share Image') . '</label>
                                            <select name="og_default_image" id="og_default_image" class="form-select">' . $og_image_options . '</select>
                                            <div class="form-text">' . lang('Used as the social share picture for pages that do not have one of their own.') . '</div>
                                        </div>
                                        <div class="pg-f-md">
                                            <label for="organization_logo" class="form-label">' . lang('Organization Logo') . '</label>
                                            <select name="organization_logo" id="organization_logo" class="form-select">' . $logo_image_options . '</select>
                                            <div class="form-text">' . lang('Used in the structured data that introduces your organization and as the publisher logo on blog posts.') . '</div>
                                        </div>';

        // Everything in here exists ONLY as structured data output, so it
        // hides with the switch, the way the other dependent groups on this
        // screen do. The share image and logo above stay out of it: the share
        // image feeds Open Graph, which has its own setting.
        $output_structured_data_dependent = '
                                        <div class="collapse popover fade bs-popover-bottom p-0  w-100" id="strutured_data_dependent_row">
                                            <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(30px, 0px);"></div>
                                            <div class="popover-body">
                                               <div class="row gy-3">
                                                    <div class="col-12 ">
                                                        <label for="custom_jsonld" class="form-label">' . lang('Site-Wide JSON-LD') . '</label>
                                                        <textarea name="custom_jsonld" id="custom_jsonld" class="form-control" >' . h($site_custom_jsonld) . '</textarea>
                                                        ' . get_codemirror_javascript(array('id' => 'custom_jsonld', 'code_type' => 'plain')) . '
                                                        <div class="form-text">' . lang('Added to every page as structured data. Must be valid JSON. Use this for blocks you would otherwise paste into a template by hand, such as an Organization or SoftwareApplication description.') . '</div>
                                                    </div>
                                                    <div class="col-12 ">
                                                        <h6 class="text-muted">' . lang('Structured Data (Google Merchant)') . '</h6>
                                                        <div class="form-text">' . lang('These facts appear on every physical product as Google Merchant shipping and return details. Digital products are left out automatically.') . '</div>
                                                    </div>
                                                    <div class="pg-f-xs">
                                                        <label for="merchant_country" class="form-label">' . lang('Merchant Country (2-letter code)') . '</label>
                                                        <input type="text" name="merchant_country" id="merchant_country" maxlength="2" class="form-control text-uppercase" value="' . h($merchant_country) . '" placeholder="TR"/>
                                                    </div>
                                                    <div class="pg-f-sm">
                                                        <label for="merchant_shipping_rate" class="form-label">' . lang('Standard Shipping Rate') . '</label>
                                                        <input type="text" name="merchant_shipping_rate" id="merchant_shipping_rate" class="form-control" value="' . (($merchant_shipping_rate >= 0) ? h(sprintf('%01.2f', $merchant_shipping_rate / 100)) : '') . '"/>
                                                        <div class="form-text">' . lang('Leave blank to leave shipping out of the markup. Enter 0 for free shipping.') . '</div>
                                                    </div>
                                                    <div class="pg-f-xs">
                                                        <label for="merchant_transit_days_min" class="form-label">' . lang('Transit Time Min (days)') . '</label>
                                                        <input type="number" name="merchant_transit_days_min" id="merchant_transit_days_min" min="0" max="60" class="form-control" value="' . (int) $merchant_transit_days_min . '"/>
                                                    </div>
                                                    <div class="pg-f-xs">
                                                        <label for="merchant_transit_days_max" class="form-label">' . lang('Transit Time Max (days)') . '</label>
                                                        <input type="number" name="merchant_transit_days_max" id="merchant_transit_days_max" min="0" max="60" class="form-control" value="' . (int) $merchant_transit_days_max . '"/>
                                                    </div>
                                                    <div class="pg-f-xs">
                                                        <label for="merchant_return_days" class="form-label">' . lang('Return Window (days)') . '</label>
                                                        <input type="text" name="merchant_return_days" id="merchant_return_days" class="form-control" value="' . (($merchant_return_days >= 0) ? (int) $merchant_return_days : '') . '"/>
                                                        <div class="form-text">' . lang('Leave blank to leave the return policy out of the markup. Enter 0 if returns are not accepted.') . '</div>
                                                    </div>
                                                    <div class="pg-f-md">
                                                        <label for="merchant_return_fees" class="form-label">' . lang('Return Shipping') . '</label>
                                                        <select name="merchant_return_fees" id="merchant_return_fees" class="form-select">
                                                            <option value="0"' . (($merchant_return_fees == 0) ? ' selected="selected"' : '') . '>' . lang('Free for the customer') . '</option>
                                                            <option value="1"' . (($merchant_return_fees == 1) ? ' selected="selected"' : '') . '>' . lang('Customer pays') . '</option>
                                                        </select>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>';
    }

    // ── SEO card: the structured data switch ─────────────────────────────────
    // Always rendered - the strutured_data column has existed since the
    // LiveSite era. It used to live in the e-commerce card, which made it
    // invisible (and its blocks uncontrollable) the moment e-commerce was
    // off, while the blocks it gates now - blog posts, breadcrumbs, the
    // organization - have nothing to do with e-commerce.
    $strutured_data_switch_checked = ($strutured_data == 1) ? ' checked="checked"' : '';

    $output_structured_data_section = '
                                        <div id="pgsub-structured" class="col-12  mb-0"><h6 class="text-muted text-uppercase">' . lang('Structured Data') . '</h6></div>
                                        <div class="col-12 ">
                                            <div class="form-check form-switch">
                                                <input value="1"' . $strutured_data_switch_checked . ' class="form-check-input' . (($output_structured_data_dependent != '') ? ' collapse-switcher' : '') . '" type="checkbox" id="strutured_data" name="strutured_data"' . (($output_structured_data_dependent != '') ? ' data-bs-target="#strutured_data_dependent_row"' : '') . '/>
                                                <label class="form-check-label" for="strutured_data">' . lang('Describe pages to search engines with structured data') . '</label>
                                            </div>
                                            <div class="form-text">' . lang('Products, product lists, blog posts, breadcrumbs and your organization are described in machine-readable form. The fields below only apply while this is on.') . '</div>
                                        </div>
                                        ' . $output_structured_data_dependent;




    $parasut_tc_in_field_option_1_selected = ($parasut_tc_in_field == 'do not use' || $parasut_tc_in_field == '') ? 'selected="selected"' : '';
    $parasut_tc_in_field_option_2_selected = ($parasut_tc_in_field == 'custom_field_1') ? 'selected="selected"' : '';
    $parasut_tc_in_field_option_3_selected = ($parasut_tc_in_field == 'custom_field_2') ? 'selected="selected"' : '';
    $parasut_tc_in_field_option_4_selected = ($parasut_tc_in_field == 'tax_number')     ? 'selected="selected"' : '';

    $parasut_tc_in_field_options =
        '<option value="do not use" ' . $parasut_tc_in_field_option_1_selected . '>' . lang('Do Not Use') . '</option>' .
        '<option value="custom_field_1" '  . $parasut_tc_in_field_option_2_selected . '>custom_field_1</option>' .
        '<option value="custom_field_2" '  . $parasut_tc_in_field_option_3_selected . '>custom_field_2</option>' .
        '<option value="tax_number" '       . $parasut_tc_in_field_option_4_selected . '>contacts.tax_number (' . lang('VKN / TCKN') . ')</option>';



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
        <div class="pg-f-md">
            <label class="form-label" for="software_language">' . lang('Software Language') . $output_enforcement . '</label>
            <select class="form-select" id="software_language" name="software_language">'.$select_software_language_options.'</select>
        </div>';
    }

    

    $last_modified = pg_settings_last_modified($row);

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

    if ($remember_me_device_limit_strict == 1) {
        $remember_me_device_limit_strict_checked = ' checked="checked"';
    } else {
        $remember_me_device_limit_strict_checked = '';
    }

    if ($oauth_google_enabled == 1) {
        $oauth_google_enabled_checked = ' checked="checked"';
    } else {
        $oauth_google_enabled_checked = '';
    }

    if ($remember_me_device_limit_enabled == 1) {
        $remember_me_device_limit_enabled_checked = ' checked="checked"';
    } else {
        $remember_me_device_limit_enabled_checked = '';
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

        while ($currency_row = mysqli_fetch_assoc($result)) {
            $currencies[] = $currency_row;
        }

      
        foreach($currencies as $currency){
            if($iyzipay_protected_currency_code == $currency['code']){
                $output_iyzipay_protected_currency_select_options .= '<option value="' . $currency['code'] . '" selected="selected">' . $currency['code'] . ' - ' . $currency['name'] . '</option>';
            }else{
                $output_iyzipay_protected_currency_select_options .= '<option value="' . $currency['code'] . '" >' . $currency['code'] . ' - ' . $currency['name'] . '</option>';
            }

        }

        $output_iyzipay_protected_currency_select = '<select class="form-select" name="iyzipay_protected_currency_code">' . $output_iyzipay_protected_currency_select_options . '</select>';

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

                $output_auto_bans .= '<span class="badge bg-danger-subtle text-danger-emphasis border border-danger-subtle me-1 ">'
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
    $next_order_number_row = mysqli_fetch_assoc($result);
    $ecommerce_next_order_number = $next_order_number_row['next_order_number'];
    
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
    while ($pem_row = mysqli_fetch_assoc($result)) {
        // if file is the current selected pem file, select it by default
        if ($pem_row['name'] == $ecommerce_first_data_global_gateway_pem_file_name) {
            $selected_or_not = ' selected="selected"';
        } else {
            $selected_or_not = '';
        }

        $ecommerce_first_data_global_gateway_pem_file_name_options .= '<option value="' . h($pem_row['name']) . '"' . $selected_or_not . '>' . h($pem_row['name']) . '</option>';
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
    
    // if OpenSSL is disabled, then disable reset encryption key
    if (extension_loaded('openssl') == FALSE) {
        $ecommerce_reset_encryption_key_disabled = ' disabled="disabled"';
        $ecommerce_reset_encryption_key_disabled_message = ' (' . lang('OpenSSL is disabled') . ')';
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

    // ── Live chat: form fragments ────────────────────────────────────────
    $chat_enabled_checked = ($chat_enabled == 1) ? ' checked="checked"' : '';
    $chat_site_enabled_checked = ($chat_site_enabled == 1) ? ' checked="checked"' : '';
    $chat_offline_email_checked = ($chat_offline_email == 1) ? ' checked="checked"' : '';
    $chat_captcha_checked = ($chat_captcha == 1) ? ' checked="checked"' : '';
    $chat_allow_files_checked = ($chat_allow_files == 1) ? ' checked="checked"' : '';
    $chat_allow_images_checked = ($chat_allow_images == 1) ? ' checked="checked"' : '';

    // Operator list: only staff (role <= 2) can be selected.
    $output_chat_operator_options = '<option value="0">' . lang('Select') . '</option>';

    $chat_operator_users = db_items("
        SELECT user.user_id AS id, user.user_username AS username,
            contacts.first_name AS first_name, contacts.last_name AS last_name
        FROM user
        LEFT JOIN contacts ON contacts.id = user.user_contact
        WHERE user.user_role <= 2
        ORDER BY user.user_username");

    foreach ($chat_operator_users as $chat_operator_user) {
        $chat_operator_name = trim($chat_operator_user['first_name'] . ' ' . $chat_operator_user['last_name']);

        if ($chat_operator_name != '') {
            $chat_operator_name .= ' (' . $chat_operator_user['username'] . ')';
        } else {
            $chat_operator_name = $chat_operator_user['username'];
        }

        $output_chat_operator_options .= '<option value="' . (int) $chat_operator_user['id'] . '"'
            . (((int) $chat_operator_user['id'] === $chat_operator_user_id) ? ' selected="selected"' : '')
            . '>' . h($chat_operator_name) . '</option>';
    }

    $output_chat_retention_options = '';

    foreach (array(7, 15, 30, 60, 90, 180) as $chat_retention_option) {
        $output_chat_retention_options .= '<option value="' . $chat_retention_option . '"'
            . (($chat_retention_days == $chat_retention_option) ? ' selected="selected"' : '')
            . '>' . $chat_retention_option . ' ' . lang('day(s)') . '</option>';
    }

    $chat_theme_options = array('auto' => lang('Auto'), 'light' => lang('Light'), 'dark' => lang('Dark'));
    $output_chat_theme_options = '';

    foreach ($chat_theme_options as $chat_theme_value => $chat_theme_label) {
        $output_chat_theme_options .= '<option value="' . $chat_theme_value . '"'
            . (($chat_widget_theme == $chat_theme_value) ? ' selected="selected"' : '')
            . '>' . $chat_theme_label . '</option>';
    }

    $chat_icon_options = array('chat' => lang('Chat Bubble'), 'support' => lang('Headset'), 'help' => lang('Question Mark'));
    $output_chat_icon_options = '';

    foreach ($chat_icon_options as $chat_icon_value => $chat_icon_label) {
        $output_chat_icon_options .= '<option value="' . $chat_icon_value . '"'
            . (($chat_widget_icon == $chat_icon_value) ? ' selected="selected"' : '')
            . '>' . $chat_icon_label . '</option>';
    }

    $block_unknown_bots_checked = ($block_unknown_bots == 1) ? ' checked="checked"' : '';

    // ── Firewall switches ────────────────────────────────────────────────
    $perf_monitor_checked           = ($perf_monitor_setting == 1) ? ' checked="checked"' : '';

    $job_dispatch_enabled_checked   = ($job_dispatch_enabled == 1) ? ' checked="checked"' : '';

    // One row per dispatchable job, built from the same catalogue the general
    // job dispatches from, so a job added there appears here without a second
    // edit. The cadence in brackets is the interval the dispatcher enforces,
    // not a suggestion: a job switched on here runs no more often than that
    // however frequently the general job itself is scheduled.
    $job_dispatch_selection = array();

    foreach (explode(',', $job_dispatch) as $job_dispatch_name) {

        $job_dispatch_name = trim($job_dispatch_name);

        if ($job_dispatch_name !== '') {
            $job_dispatch_selection[] = $job_dispatch_name;
        }
    }

    $output_job_dispatch_switches = '';

    foreach (pg_cron_jobs() as $job_dispatch_name => $job_dispatch_job) {

        if (!$job_dispatch_job['dispatch']) {
            continue;
        }

        $output_job_dispatch_switches .= '
                                        <div class="pg-f-md">
                                            <div class="form-check form-switch">
                                                <input value="1"' . (in_array($job_dispatch_name, $job_dispatch_selection, true) ? ' checked="checked"' : '') . ' class="form-check-input" type="checkbox" id="job_dispatch_job_' . h($job_dispatch_name) . '" name="job_dispatch_job[' . h($job_dispatch_name) . ']"/>
                                                <label class="form-check-label" for="job_dispatch_job_' . h($job_dispatch_name) . '">' . h($job_dispatch_job['label']) . ' <span class="text-muted">(' . h(pg_cron_interval_label($job_dispatch_job['interval'])) . ')</span>' . (pg_cron_job_active($job_dispatch_name) ? '' : ' <span class="text-warning-emphasis">&middot; ' . lang('disabled in config.php') . '</span>') . '</label>
                                            </div>
                                        </div>';
    }

    $waf_enabled_checked            = ($waf_enabled == 1) ? ' checked="checked"' : '';
    $waf_signature_scan_checked     = ($waf_signature_scan == 1) ? ' checked="checked"' : '';
    $waf_rate_limit_checked         = ($waf_rate_limit == 1) ? ' checked="checked"' : '';
    $waf_auto_ban_checked           = ($waf_auto_ban == 1) ? ' checked="checked"' : '';
    $waf_block_attack_tools_checked = ($waf_block_attack_tools == 1) ? ' checked="checked"' : '';
    $waf_verify_bots_checked        = ($waf_verify_bots == 1) ? ' checked="checked"' : '';

    // ── AI bot switches + published range list freshness ─────────────────
    // Rendered as a prebuilt fragment so the block simply does not exist on
    // an install that has not run the 2026.4.4 upgrade.
    $output_login_throttle = '';

    if ($login_throttle_ready) {

        $login_throttle_checked = ($login_throttle == 1) ? ' checked="checked"' : '';

        $output_login_throttle = '
                                        <div class="col-12 ">
                                            <div class="form-check form-switch ">
                                                <input value="1"' . $login_throttle_checked . ' class="form-check-input collapse-switcher" type="checkbox" id="login_throttle" name="login_throttle" data-bs-target="#login_throttle_row"/>
                                                <label class="form-check-label" for="login_throttle">' . lang('Limit Failed Sign-in Attempts') . '</label>
                                                <div class="form-text">' . lang('Counts failed sign-ins against both the visitor\'s address and the account being tried, and locks that pair out for a while once the limit is passed. Successful sign-ins are never counted.') . '</div>
                                            </div>
                                            <div class="collapse popover fade bs-popover-bottom p-0  w-100" id="login_throttle_row">
                                                <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(52px, 0px);"></div>
                                                <div class="popover-body">
                                                   <div class="row gy-3">
                                                        <div class="pg-f-xs">
                                                            <label for="login_throttle_attempts" class="form-label">' . lang('Failed Attempts') . '</label>
                                                            <input type="text" name="login_throttle_attempts" id="login_throttle_attempts" class="form-control" value="' . (int) $login_throttle_attempts . '" size="5" maxlength="4" inputmode="numeric" style="text-align: right;"/>
                                                        </div>
                                                        <div class="pg-f-sm">
                                                            <label for="login_throttle_minutes" class="form-label">' . lang('Counted Over') . '</label>
                                                            <div class="input-group">
                                                                <input type="text" name="login_throttle_minutes" id="login_throttle_minutes" class="form-control" value="' . (int) $login_throttle_minutes . '" size="5" maxlength="4" inputmode="numeric" style="text-align: right;"/>
                                                                <span class="input-group-text">' . lang('minutes') . '</span>
                                                            </div>
                                                        </div>
                                                        <div class="pg-f-sm">
                                                            <label for="login_throttle_lockout" class="form-label">' . lang('Locked Out For') . '</label>
                                                            <div class="input-group">
                                                                <input type="text" name="login_throttle_lockout" id="login_throttle_lockout" class="form-control" value="' . (int) $login_throttle_lockout . '" size="5" maxlength="5" inputmode="numeric" style="text-align: right;"/>
                                                                <span class="input-group-text">' . lang('minutes') . '</span>
                                                            </div>
                                                        </div>
                                                        ' . ($security_ready ? '<div class="pg-f-xs">
                                                            <label for="login_throttle_captcha_after" class="form-label">' . lang('Ask a Question After') . '</label>
                                                            <div class="input-group">
                                                                <input type="text" name="login_throttle_captcha_after" id="login_throttle_captcha_after" class="form-control" value="' . (int) $login_throttle_captcha_after . '" size="4" maxlength="3" inputmode="numeric" style="text-align: right;"/>
                                                                <span class="input-group-text">' . lang('failures') . '</span>
                                                            </div>
                                                            <div class="form-text">' . lang('From this many failures on, a sign-in attempt must also answer a simple arithmetic question before the password is checked. A person answers it in a second; a password list cannot. 0 turns it off.') . '</div>
                                                        </div>' : '') . '
                                                        <div class="col-12">
                                                            <div class="form-text">' . lang('Offices and schools share one address across many people. If visitors report being locked out, raise the number of attempts or add the address to the allowed list.') . '</div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>';
    }

    $output_waf_ai_block = '';

    if ($waf_ai_ready) {

        $waf_allow_ai_fetchers_checked = ($waf_allow_ai_fetchers == 1) ? ' checked="checked"' : '';
        $waf_allow_ai_search_checked   = ($waf_allow_ai_search == 1) ? ' checked="checked"' : '';

        // Freshness per stored list. The verifier stops treating a miss as
        // forgery 30 days after the last refresh, so the operator should be
        // able to see at a glance whether the lists are being kept current.
        $waf_ai_provider_labels = array(
            'openai-chatgpt-user' => 'OpenAI ChatGPT-User',
            'openai-searchbot'    => 'OpenAI SearchBot',
            'anthropic-bots'      => 'Anthropic (Claude)',
            'perplexity-user'     => 'Perplexity',
        );

        $waf_ai_fetched = array();
        $waf_ai_rows = db_items("SELECT provider, fetched_at FROM waf_bot_ranges");

        if (is_array($waf_ai_rows)) {
            foreach ($waf_ai_rows as $waf_ai_row) {
                $waf_ai_fetched[$waf_ai_row['provider']] = (int) $waf_ai_row['fetched_at'];
            }
        }

        $waf_ai_freshness_parts = array();

        foreach ($waf_ai_provider_labels as $waf_ai_provider => $waf_ai_label) {

            if (!empty($waf_ai_fetched[$waf_ai_provider])) {
                $waf_ai_age_days = (int) floor((time() - $waf_ai_fetched[$waf_ai_provider]) / 86400);
                $waf_ai_freshness_parts[] = $waf_ai_label . ': '
                    . ($waf_ai_age_days < 1
                        ? lang('today')
                        : lang(array('string' => '{var:1} day{suffix:1} ago', 'vars' => $waf_ai_age_days, 'suffix' => ($waf_ai_age_days == 1 ? '' : 's'))));
            } else {
                $waf_ai_freshness_parts[] = $waf_ai_label . ': ' . lang('not fetched yet');
            }
        }

        $output_waf_ai_block = '
                                                        <div class="col-12 ">
                                                            <div class="form-check form-switch">
                                                                <input value="1"' . $waf_allow_ai_fetchers_checked . ' class="form-check-input" type="checkbox" id="waf_allow_ai_fetchers" name="waf_allow_ai_fetchers"/>
                                                                <label class="form-check-label" for="waf_allow_ai_fetchers">' . lang('Allow AI Assistant Fetchers (IP Verified)') . '</label>
                                                                <div class="form-text">' . lang('ChatGPT-User, Claude-User and Perplexity-User fetch a single page at the moment a person asks the assistant about your site. Allowing them lets your pages appear as sources in AI answers. Each request is verified against the operator\'s published IP list, so a scraper merely claiming the name is still blocked. AI training crawlers such as GPTBot and ClaudeBot stay blocked regardless of this switch.') . '</div>
                                                            </div>
                                                        </div>
                                                        <div class="col-12 ">
                                                            <div class="form-check form-switch">
                                                                <input value="1"' . $waf_allow_ai_search_checked . ' class="form-check-input" type="checkbox" id="waf_allow_ai_search" name="waf_allow_ai_search"/>
                                                                <label class="form-check-label" for="waf_allow_ai_search">' . lang('Allow AI Search Indexers (IP Verified)') . '</label>
                                                                <div class="form-text">' . lang('OAI-SearchBot and Claude-SearchBot index pages for AI search results, the way a search engine crawler does. Allowing them lets your site rank in ChatGPT and Claude search. Verified against published IP lists as well.') . '</div>
                                                            </div>
                                                        </div>
                                                        <div class="col-12 ">
                                                            <button type="submit" name="waf_refresh_ranges" value="1" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-repeat me-1"></i>' . lang('Update Bot IP Lists') . '</button>
                                                            <div class="form-text">' . h(implode(' · ', $waf_ai_freshness_parts)) . '</div>
                                                            <div class="form-text">' . lang('The lists refresh on their own: with panel visits, and daily when the Bot IP lists job is enabled under Cron jobs. This button fetches them right now and also saves the settings above.') . '</div>
                                                        </div>';
    }

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
            '<div class="alert alert-info d-flex align-items-start py-2 px-3 ">'
            . '<i class="bi bi-shield-check me-2 "></i>'
            . '<div class="small">'
            . '<strong>' . lang(array('string' => '{var:1} detected in front of this site.', 'vars' => h($waf_external_name))) . '</strong>'
            . ($waf_external_live ? '' : ' <span class="text-muted">(' . lang('last seen on a previous request') . ')</span>')
            . '<br>' . lang('Its rules run before this one, so Pinegrap acts as a second layer. Keep this firewall on: requests made straight to the server address bypass the external service entirely.')
            . '<br>' . lang('Add the provider edge addresses to Trusted Proxies below, otherwise every visitor will appear to share one IP address.')
            . '</div></div>';
    } else {
        $output_waf_external =
            '<div class="alert alert-secondary d-flex align-items-start py-2 px-3 ">'
            . '<i class="bi bi-info-circle me-2 "></i>'
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
        . ' d-flex align-items-start py-2 px-3 "><i class="bi '
        . ($waf_ip_unresolved ? 'bi-exclamation-triangle' : 'bi-geo-alt') . ' me-2 "></i>'
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
                '<div class="alert alert-danger d-flex align-items-start py-2 px-3 ">'
                . '<i class="bi bi-exclamation-octagon me-2 "></i>'
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
            '<div class="d-flex align-items-start py-2 px-3  border rounded">'
            . '<i class="bi bi-send me-2  text-muted"></i>'
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
            '<div class="alert alert-warning d-flex align-items-start py-2 px-3 ">'
            . '<i class="bi bi-exclamation-triangle me-2 "></i>'
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

    // PHP_OS_FAMILY only exists as of PHP 7.2, so derive the same value when it is missing.
    if (defined('PHP_OS_FAMILY')) {
        $php_os_family = PHP_OS_FAMILY;
    } else {
        $php_os_name = strtoupper(PHP_OS);

        if (substr($php_os_name, 0, 3) === 'WIN') {
            $php_os_family = 'Windows';
        } elseif ($php_os_name === 'LINUX') {
            $php_os_family = 'Linux';
        } elseif ($php_os_name === 'DARWIN') {
            $php_os_family = 'Darwin';
        } elseif (strpos($php_os_name, 'BSD') !== false) {
            $php_os_family = 'BSD';
        } elseif (($php_os_name === 'SUNOS') || ($php_os_name === 'SOLARIS')) {
            $php_os_family = 'Solaris';
        } else {
            $php_os_family = 'Unknown';
        }
    }

    $output_os_family = $php_os_family;

    // Windows takes the php.exe form; every other family - Linux, Darwin,
    // BSD, Solaris - is unix-like and takes the same command. The earlier
    // Linux/Windows pair left all of these variables undefined on those other
    // families, which printed a PHP warning inside each command box.
    if ($php_os_family === "Windows") {
        $cron_job_general = 'C:\PHP\php.exe -q '.dirname(__FILE__) . '\job.php';
        $cron_job_exchange_rates = 'C:\PHP\php.exe -q '.dirname(__FILE__) . '\update_exchange_rates.php';
        $cron_job_email_campaign = 'C:\PHP\php.exe -q '.dirname(__FILE__) . '\email_campaign_job.php';
        $cron_job_search_index = 'C:\PHP\php.exe -q '.dirname(__FILE__) . '\update_search_index.php';
        $cron_job_seo_score = 'C:\PHP\php.exe -q '.dirname(__FILE__) . '\seo_score_job.php';
        $cron_job_seo_analyze = 'C:\PHP\php.exe -q '.dirname(__FILE__) . '\seo_analyze_job.php';
        $cron_job_requrring_payment = 'C:\PHP\php.exe -q '.dirname(__FILE__) . '\recurring_payment_job.php';
        $cron_job_membership = 'C:\PHP\php.exe -q '.dirname(__FILE__) . '\membership_job.php';
        $cron_job_auto_backup = 'C:\PHP\php.exe -q '.dirname(__FILE__) . '\auto_backup.php';
        $cron_job_webhook = 'C:\PHP\php.exe -q '.dirname(__FILE__) . '\api_webhook_job.php';
        $cron_job_push = 'C:\PHP\php.exe -q '.dirname(__FILE__) . '\push_job.php';
    } else {
        $cron_job_general = '/usr/local/bin/php -q '.dirname(__FILE__) . '/job.php >/dev/null 2>&1';
        $cron_job_exchange_rates = '/usr/local/bin/php -q '.dirname(__FILE__) . '/update_exchange_rates.php >/dev/null 2>&1';
        $cron_job_email_campaign = '/usr/local/bin/php -q '.dirname(__FILE__) . '/email_campaign_job.php >/dev/null 2>&1';
        $cron_job_search_index = '/usr/local/bin/php -q '.dirname(__FILE__) . '/update_search_index.php >/dev/null 2>&1';
        $cron_job_seo_score = '/usr/local/bin/php -q '.dirname(__FILE__) . '/seo_score_job.php >/dev/null 2>&1';
        $cron_job_seo_analyze = '/usr/local/bin/php -q '.dirname(__FILE__) . '/seo_analyze_job.php >/dev/null 2>&1';
        $cron_job_requrring_payment = '/usr/local/bin/php -q '.dirname(__FILE__) . '/recurring_payment_job.php >/dev/null 2>&1';
        $cron_job_membership = '/usr/local/bin/php -q '.dirname(__FILE__) . '/membership_job.php >/dev/null 2>&1';
        $cron_job_auto_backup = '/usr/local/bin/php -q '.dirname(__FILE__) . '/auto_backup.php >/dev/null 2>&1';
        $cron_job_webhook = '/usr/local/bin/php -q '.dirname(__FILE__) . '/api_webhook_job.php >/dev/null 2>&1';
        $cron_job_push = '/usr/local/bin/php -q '.dirname(__FILE__) . '/push_job.php >/dev/null 2>&1';
    }
    $output_warnings_for_auto_backup = '';
    if (!extension_loaded('pdo_mysql') ) {
        $output_warnings_for_auto_backup = '<div class="alert alert-warning">' . lang('pdo_mysql.dll is not enabled. Please enable it for Auto Backup feature.') . '</div>';
    }

    // The structure pass parses rendered markup with DOMDocument. Without the
    // extension the job records its run and exits, so the operator would see
    // a scheduled task that reports healthy and analyzes nothing.
    $output_warnings_for_seo_analyze = '';
    if (!class_exists('DOMDocument')) {
        $output_warnings_for_seo_analyze = '<div class="alert alert-warning">' . lang('The PHP DOM extension is not enabled. Please enable it for the SEO structure analysis job.') . '</div>';
    }
    //localhost default ip.
    $server_addr = '127.0.0.1';
    //check server ip.
    if(isset($_SERVER['SERVER_ADDR'])){
        $server_addr = $_SERVER['SERVER_ADDR'];
    }
