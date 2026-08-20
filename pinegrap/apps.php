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

// Add header in order to start response.
header('Content-Type: application/json');

/* ---------------------------------------------------------
   Security: Banned IP check
   --------------------------------------------------------- */
// ACCESS_IP is the caller's real address, resolved through any trusted proxy
// or CDN. Reading REMOTE_ADDR directly (as this did) reports the edge server
// behind Cloudflare, which made every API log entry and every IP ban useless
// on proxied sites.
define('ACCESS_IP', function_exists('waf_client_ip')
    ? waf_client_ip()
    : (isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : ''));

if (function_exists('waf_ip_is_blocked')
    && !waf_ip_is_allowed(ACCESS_IP)
    && waf_ip_is_blocked(ACCESS_IP)
) {
    $response = array(
        'status'  => 'error',
        'message' => lang('We are currently unable to fulfill your request.')
    );
    echo encode_json($response);
    log_activity(lang(array(
        'string'=>'Visitor was not allowed to App access because the visitor\'s IP address ({var:1}) was banned',
        'vars'=>h(ACCESS_IP)
    )), $_SESSION['sessionusername']);
    exit();
}

/* ---------------------------------------------------------
   Hybrid request input — Cookies excluded (injection risk)
   Priority: JSON body > POST > GET
   --------------------------------------------------------- */
$raw_json = @file_get_contents('php://input');
$request  = json_decode($raw_json, true);
if (!$request || !is_array($request)) {
    // Use GET+POST merge instead of $_REQUEST; cookies are excluded
    $request = array_merge($_GET, $_POST);
}


$API    = isset($request['api_key'])    ? trim($request['api_key'])    : '';
$SECRET = isset($request['secret_key']) ? trim($request['secret_key']) : '';
$action = isset($request['action'])     ? trim($request['action'])     : '';

/* ---------------------------------------------------------
   Validate API key
   --------------------------------------------------------- */
$matched_app = null;

if ($API === '' || $API === ' ' || $API === null) {
	api_error(lang([
	    'string' => '{var:1} is required.',
	    'vars'   => [lang('API KEY')]
	]));
} else {
    // Single indexed lookup — no decryption loop
    $api_hash = hash_hmac('sha256', $API, ENCRYPTION_KEY);
    $query = "
        SELECT
            id as app_id,
            create_user_id,
            name,
            type,
            method,
            permissions,
            api_key,
            api_key_iv,
            timestamp,
            user.user_username as user
        FROM custom_apps
        LEFT JOIN user ON custom_apps.create_user_id = user.user_id
        WHERE custom_apps.api_key_hash = '" . escape($api_hash) . "'
        LIMIT 1
    ";
    $result = mysqli_query(db::$con, $query) or api_error(lang('A system error occurred.'));
    $matched_app = mysqli_fetch_assoc($result);
    unset($api_hash);

    if (empty($matched_app)) {
        // Generic error — do not reveal which field failed
        api_error(lang('Invalid credentials.'));
    }
}

/* ---------------------------------------------------------
   Permissions
   --------------------------------------------------------- */
$app_permissions = array();
if (isset($matched_app['permissions']) && $matched_app['permissions'] !== '') {
    $decoded = json_decode($matched_app['permissions'], true);
    if (is_array($decoded)) {
        $app_permissions = $decoded;
    }
}

/* ---------------------------------------------------------
   Enforce method (optional)
   --------------------------------------------------------- */
if (isset($matched_app['method'])) {
    $app_method = strtoupper($matched_app['method']);
    $current_method = isset($_SERVER['REQUEST_METHOD']) ? strtoupper($_SERVER['REQUEST_METHOD']) : 'GET';
    if ($app_method === 'POST' && $current_method !== 'POST') {
        api_error(lang('This endpoint requires POST method.'));
    }
}

/* ---------------------------------------------------------
   Validate secret key
   --------------------------------------------------------- */

if ($SECRET === '' || $SECRET === ' ' || $SECRET === null) {
    api_error(lang([
	    'string' => '{var:1} is required.',
	    'vars'   => [lang('SECRET KEY')]
	]));
} else {
    // Compute HMAC of the supplied secret — same algorithm used when key was generated
    $secret_hash = hash_hmac('sha256', $SECRET, ENCRYPTION_KEY);

    // Single indexed lookup — no decryption, no full-table scan
    $query = "SELECT user_id, user_username, user_role, user_manage_ecommerce, user_manage_visitors
              FROM user
              WHERE secret_key_hash = '" . escape($secret_hash) . "'
              LIMIT 1";
    $result = mysqli_query(db::$con, $query) or api_error(lang('A system error occurred.'));
    $record = mysqli_fetch_assoc($result);

    if (empty($record)) {
        // Same generic message as api_key — no information leakage
        api_error(lang('Invalid credentials.'));
    }

    $user = array(
        'user_id'          => $record['user_id'],
        'user_username'    => $record['user_username'],
        'role'             => (int)$record['user_role'],
        'manage_ecommerce' => (bool)$record['user_manage_ecommerce'],
        'manage_visitors'  => ($record['user_manage_visitors'] === 'yes'),
    );
}
unset($secret_hash);
$log_user = isset($_SESSION['sessionusername']) && $_SESSION['sessionusername'] !== '' ? $_SESSION['sessionusername'] : $user['user_username'];


/* ---------------------------------------------------------
   Helpers
   --------------------------------------------------------- */
function has_permission($permissions, $action, $type) {
    if (!is_array($permissions)) return false;
    foreach ($permissions as $p) {
        $p_action = isset($p['action']) ? $p['action'] : '';
        $p_type   = isset($p['type'])   ? $p['type']   : '';
        if ($p_action === $action) {
            if ($p_type === $type) return true;
            if ($p_type === 'edit' && $type === 'read') return true;
        }
    }
    return false;
}



// Success response
function api_success($data = array(), $message = '') {
    echo encode_json(array(
        'status'  => 'success',
        'message' => $message,
        'data'    => $data
    ));
    exit();
}

// Error response
function api_error($message) {
    echo encode_json(array(
        'status'  => 'error',
        'message' => $message
    ));
    exit();
}

/* ---------------------------------------------------------
   Main API switch
   --------------------------------------------------------- */
switch ($action) {




	case 'product':
    // The store has to be on.
    if (!(defined('ECOMMERCE') && ECOMMERCE === true)) {
        api_error(lang('Permission denied.'));
    }
    // User role: a contributor needs the manage_ecommerce flag.
    if ($user['role'] >= 3 && !$user['manage_ecommerce']) {
        api_error(lang('Permission denied.'));
    }
    // App permission: applies to every role - it narrows what the API key may do.
    $can_read = has_permission($app_permissions, 'product', 'read');
    $can_edit = has_permission($app_permissions, 'product', 'edit');
    if (!$can_read && !$can_edit) {
        api_error(lang('Permission denied: You cannot access product info.'));
    }

    // Validate product name
    $request_name = isset($request['name']) ? trim($request['name']) : '';
    if ($request_name === '') {
        api_error(lang('name parameter must be send.'));
    }

    // Fetch product
    $query = "
        SELECT *
        FROM products
        WHERE name = '" . escape($request_name) . "'
        LIMIT 1
    ";
    $result = mysqli_query(db::$con, $query) or api_error('Query failed.');
    $row = mysqli_fetch_assoc($result);

    if (!$row || empty($row['id'])) {
        api_error(lang('product cant be found.'));
    }

    $id = $row['id'];
    $update_fields = array();
    $update_attempted = false;

    // If edit permission, process updates
    if ($can_edit) {
        // Handle direct field updates (like price, title, etc.)
        $editable_columns = array(
            'enabled','short_description','full_description','keywords','image_name',
            'price','taxable','order_receipt_message','selection_type',
            'default_quantity','minimum_quantity','maximum_quantity',
            'title','meta_description','meta_keywords',
            'inventory','inventory_quantity',
            'shippable','weight','primary_weight_points','secondary_weight_points',
            'length','width','height','container_required','preparation_time',
            'free_shipping','extra_shipping_cost','commissionable','commission_rate_limit',
            'custom_field_1','custom_field_2','custom_field_3','custom_field_4',
            'notes','google_product_category','gtin','brand','mpn'
        );

        $boolean_columns = array(
            'enabled','taxable','inventory','shippable',
            'container_required','free_shipping','commissionable'
        );

        function normalize_boolean_value($value) {
            $v = strtolower(trim((string)$value));
            if ($v === '1' || $v === 'on' || $v === 'enable' || $v === 'enabled' || $v === 'true') return '1';
            if ($v === '0' || $v === 'off' || $v === 'disable' || $v === 'disabled' || $v === 'false') return '0';
            if (is_numeric($value)) return ((int)$value !== 0) ? '1' : '0';
            return '0';
        }

        foreach ($editable_columns as $col) {
            if (isset($request[$col])) {
                if (in_array($col, $boolean_columns, true)) {
                    $normalized = normalize_boolean_value($request[$col]);
                    $update_fields[] = $col . " = '" . escape($normalized) . "'";
                } elseif ($col == 'price') {
                    $update_fields[] = "price = '" . escape((int)round($request[$col] * 100)) . "'";
                } elseif ($col == 'extra_shipping_cost') {
                    $update_fields[] = "extra_shipping_cost = '" . escape((int)round($request[$col] * 100)) . "'";
                } elseif (in_array($col, ['weight','length','width','height'])) {
                    $update_fields[] = $col . " = '" . escape((float)$request[$col]) . "'";
                } elseif ($col == 'inventory_quantity') {
                    $new_qty = (int)$request[$col];
                    if ($new_qty < 0) $new_qty = 0;
                    $update_fields[] = "inventory_quantity = '" . escape($new_qty) . "'";
                    $update_fields[] = "out_of_stock = '" . (($new_qty <= 0) ? '1' : '0') . "'";
                } else {
                    $update_fields[] = $col . " = '" . escape($request[$col]) . "'";
                }
                $update_attempted = true;
            }
        }

		// Handle increase_quantity / decrease_quantity (mutually exclusive)
		if (isset($request['increase_quantity']) && isset($request['decrease_quantity'])) {
		    api_error(lang('increase_quantity and decrease_quantity cannot be used together.'));
		}
		$current_qty = (int)$row['inventory_quantity'];
		$inventory   = (int)$row['inventory'];

		// Increase quantity (default +1 if not provided)
		if (isset($request['increase_quantity'])) {
		    $delta = (isset($request['increase_quantity']) && $request['increase_quantity'] !== '')
		        ? (int)$request['increase_quantity'] : 1;
		    if ($delta <= 0) { $delta = 1; }
		
		    $new_qty = $current_qty + $delta;
		
		    // Auto-enable stock tracking if disabled
		    if ($inventory == 0) {
		        $update_fields[] = "inventory = '1'";
		        $inventory = 1;
		    }
		
		    $update_fields[] = "inventory_quantity = '" . escape($new_qty) . "'";
		    // Always in stock after increase
		    $update_fields[] = "out_of_stock = '0'";
		    $update_attempted = true;
		}

		// Decrease quantity (default -1 if not provided)
		if (isset($request['decrease_quantity'])) {
		    $delta = (isset($request['decrease_quantity']) && $request['decrease_quantity'] !== '')
		        ? (int)$request['decrease_quantity'] : 1;
		    if ($delta <= 0) { $delta = 1; }
		
		    $new_qty = $current_qty - $delta;
		    if ($new_qty < 0) { $new_qty = 0; }
		
		    // Auto-enable stock tracking if disabled
		    if ($inventory == 0) {
		        $update_fields[] = "inventory = '1'";
		        $inventory = 1;
		    }
		
		    $update_fields[] = "inventory_quantity = '" . escape($new_qty) . "'";
		    // If quantity is 0, mark out_of_stock = 1, else 0
		    $update_fields[] = "out_of_stock = '" . (($new_qty == 0) ? '1' : '0') . "'";
		    $update_attempted = true;
		}

        if (!empty($update_fields)) {
            $update_query = "
                UPDATE products
                SET " . implode(", ", $update_fields) . ",
                    user = '" . $user['user_id'] . "',
                    timestamp = UNIX_TIMESTAMP()
                WHERE id = '" . escape($id) . "'
                LIMIT 1
            ";
            mysqli_query(db::$con, $update_query) or api_error('Update failed.');

			log_activity(lang(array('string'=>'Product ({var:1}) were modified by a custom app: Name: {var:2}, API Key: ({var:3}), Secret Key User: {var:4}','vars'=>array($row['name'], $matched_app['name'], $api_key, $user['user_username']))),$log_user);
        }
    }

    // Always fetch latest product info
    $query = "
        SELECT *
        FROM products
        WHERE id = '" . escape($id) . "'
        LIMIT 1
    ";
    $result = mysqli_query(db::$con, $query) or api_error('Query failed.');
    $updated_row = mysqli_fetch_assoc($result);

    // Format numeric values
    $price               = sprintf("%01.2lf", $updated_row['price'] / 100);
    $extra_shipping_cost = sprintf("%01.2lf", $updated_row['extra_shipping_cost'] / 100);
    $weight = ($updated_row['weight'] > 0) ? $updated_row['weight']+0 : '';
    $length = ($updated_row['length'] > 0) ? $updated_row['length']+0 : '';
    $width  = ($updated_row['width']  > 0) ? $updated_row['width']+0  : '';
    $height = ($updated_row['height'] > 0) ? $updated_row['height']+0 : '';

    // Build response — do not use h(): JSON encoding handles its own escaping
    $response = array(
        'id'                  => $updated_row['id'],
        'name'                => $updated_row['name'],
        'enabled'             => $updated_row['enabled'],
        'short_description'   => $updated_row['short_description'],
        'full_description'    => $updated_row['full_description'],
        'keywords'            => $updated_row['keywords'],
        'image_name'          => $updated_row['image_name'],
        'price'               => $price,
        'taxable'             => $updated_row['taxable'],
        'order_receipt_message'=> $updated_row['order_receipt_message'],
        'selection_type'      => $updated_row['selection_type'],
        'default_quantity'    => $updated_row['default_quantity'],
        'minimum_quantity'    => $updated_row['minimum_quantity'],
        'maximum_quantity'    => $updated_row['maximum_quantity'],
        'title'               => $updated_row['title'],
        'meta_description'    => $updated_row['meta_description'],
        'meta_keywords'       => $updated_row['meta_keywords'],
        'inventory'           => $updated_row['inventory'],
        'inventory_quantity'  => $updated_row['inventory_quantity'],
        'shippable'           => $updated_row['shippable'],
        'weight'              => $weight,
        'primary_weight_points'=> $updated_row['primary_weight_points'],
        'secondary_weight_points'=> $updated_row['secondary_weight_points'],
        'length'              => $length,
        'width'               => $width,
        'height'              => $height,
        'container_required'  => $updated_row['container_required'],
        'preparation_time'    => $updated_row['preparation_time'],
        'free_shipping'       => $updated_row['free_shipping'],
        'extra_shipping_cost' => $extra_shipping_cost,
        'commissionable'      => $updated_row['commissionable'],
        'commission_rate_limit'=> $updated_row['commission_rate_limit'],
        'custom_field_1'      => $updated_row['custom_field_1'],
        'custom_field_2'      => $updated_row['custom_field_2'],
        'custom_field_3'      => $updated_row['custom_field_3'],
        'custom_field_4'      => $updated_row['custom_field_4'],
        'notes'               => $updated_row['notes'],
        'google_product_category' => $updated_row['google_product_category'],
        'gtin'                => $updated_row['gtin'],
        'brand'               => $updated_row['brand'],
        'mpn'                 => $updated_row['mpn']
    );

    // Final response
    if ($update_attempted && $can_edit) {
        api_success($response, lang('Product updated successfully.'));
    } else {
        api_success($response, lang('Product retrieved successfully.'));
    }
    break;
       

	case 'site_settings':
	    // Role check: only admins (role < 3) can access site settings
	    if ($user['role'] >= 3) {
	        api_error(lang('Permission denied: You are not authorized to access site settings.'));
	    }

	    // Permission checks (defined in apps_settings.php)
	    $can_read = has_permission($app_permissions, 'site_settings', 'read');
	    $can_edit = has_permission($app_permissions, 'site_settings', 'edit');

	    if (!$can_read && !$can_edit) {
	        api_error(lang('Permission denied: You cannot access site settings.'));
	    }

	    // Boolean fields to normalize to 1/0
	    $boolean_columns = array(
	        'mobile','social_networking','captcha','auto_dialogs','mass_deletion','strong_password',
	        'password_hint','remember_me','forgot_password_link','visitor_tracking','google_analytics',
	        'membership_expiration_warning_email','ecommerce','ecommerce_multicurrency','ecommerce_tax',
	        'ecommerce_tax_exempt','ecommerce_shipping','ecommerce_address_verification','ups','ups_key',
	        'ups_user_id','ups_password','ups_account','fedex','ecommerce_gift_card','ecommerce_givex',
	        'ecommerce_credit_debit_card','ecommerce_american_express','ecommerce_diners_club',
	        'ecommerce_discover_card','ecommerce_mastercard','ecommerce_visa','ecommerce_troy',
	        'ecommerce_payment_gateway','ecommerce_iyzipay_threeds','ecommerce_paypal_express_checkout',
	        'ecommerce_offline_payment','ecommerce_reward_program','ecommerce_reward_program_membership',
	        'forms','calendars','ads','affiliate_program','affiliate_automatic_approval','debug'
	    );

	    // Editable fields (full set from your config list)
	    $editable_columns = array(
	        'url_scheme','hostname','email_address','title','meta_description','meta_keywords','mobile',
	        'search_type','social_networking','social_networking_type','social_networking_facebook',
	        'social_networking_twitter','social_networking_linkedin','social_networking_whatsapp',
	        'social_networking_telegram','social_networking_pinterest','social_networking_reddit',
	        'social_networking_email','social_networking_code','captcha','auto_dialogs','mass_deletion',
	        'strong_password','password_hint','remember_me','forgot_password_link','proxy_address',
	        'badge_label','timezone','date_format','time_format','organization_name','organization_address_1',
	        'organization_address_2','organization_city','organization_state','organization_zip_code',
	        'organization_country','opt_in_label','plain_text_email_campaign_footer','visitor_tracking',
	        'tracking_code_duration','pay_per_click_flag','stats_url','google_analytics',
	        'google_analytics_web_property_id','page_editor_font','page_editor_font_size','page_editor_font_style',
	        'page_editor_font_color','page_editor_background_color','registration_contact_group_id',
	        'registration_email_address','member_id_label','membership_contact_group_id',
	        'membership_email_address','membership_expiration_warning_email',
	        'membership_expiration_warning_email_subject','membership_expiration_warning_email_page_id',
	        'membership_expiration_warning_email_days_before_expiration','ecommerce','ecommerce_multicurrency',
	        'ecommerce_tax','ecommerce_tax_exempt','ecommerce_tax_exempt_label','ecommerce_shipping',
	        'ecommerce_recipient_mode','usps_user_id','ecommerce_address_verification',
	        'ecommerce_address_verification_enforcement_type','ups','ups_key','ups_user_id','ups_password',
	        'ups_account','fedex','fedex_key','fedex_password','fedex_account','fedex_meter',
	        'ecommerce_product_restriction_message','ecommerce_no_shipping_methods_message',
	        'ecommerce_end_of_day_time','ecommerce_email_address','ecommerce_gift_card',
	        'ecommerce_gift_card_validity_days','ecommerce_givex','ecommerce_givex_primary_hostname',
	        'ecommerce_givex_secondary_hostname','ecommerce_givex_user_id','ecommerce_givex_password',
	        'ecommerce_credit_debit_card','ecommerce_american_express','ecommerce_diners_club',
	        'ecommerce_discover_card','ecommerce_mastercard','ecommerce_visa','ecommerce_troy',
	        'ecommerce_payment_gateway','ecommerce_payment_gateway_transaction_type','ecommerce_payment_gateway_mode',
	        'ecommerce_authorizenet_api_login_id','ecommerce_authorizenet_transaction_key',
	        'ecommerce_clearcommerce_client_id','ecommerce_clearcommerce_user_id','ecommerce_clearcommerce_password',
	        'ecommerce_first_data_global_gateway_store_number','ecommerce_first_data_global_gateway_pem_file_name',
	        'ecommerce_paypal_payflow_pro_partner','ecommerce_paypal_payflow_pro_merchant_login',
	        'ecommerce_paypal_payflow_pro_user','ecommerce_paypal_payflow_pro_password',
	        'ecommerce_paypal_payments_pro_api_username','ecommerce_paypal_payments_pro_api_password',
	        'ecommerce_paypal_payments_pro_api_signature','ecommerce_sage_merchant_id',
	        'ecommerce_sage_merchant_key','ecommerce_stripe_api_key','ecommerce_iyzipay_api_key',
	        'ecommerce_iyzipay_secret_key','ecommerce_iyzipay_threeds','ecommerce_surcharge_percentage',
	        'ecommerce_paypal_express_checkout','ecommerce_paypal_express_checkout_transaction_type',
	        'ecommerce_paypal_express_checkout_mode','ecommerce_paypal_express_checkout_api_username',
	        'ecommerce_paypal_express_checkout_api_password','ecommerce_paypal_express_checkout_api_signature',
	        'ecommerce_offline_payment','ecommerce_offline_payment_only_specific_orders',
	        'ecommerce_private_folder_id','ecommerce_retrieve_order_next_page_id','ecommerce_reward_program',
	        'ecommerce_reward_program_points','ecommerce_reward_program_membership',
	        'ecommerce_reward_program_membership_days','ecommerce_reward_program_email',
	        'ecommerce_reward_program_email_bcc_email_address','ecommerce_reward_program_email_subject',
	        'ecommerce_reward_program_email_page_id','ecommerce_custom_product_field_1_label',
	        'ecommerce_custom_product_field_2_label','ecommerce_custom_product_field_3_label',
	        'ecommerce_custom_product_field_4_label','forms','calendars','ads','additional_sitemap_content',
	        'additional_robots_content','debug','affiliate_program','affiliate_automatic_approval'
	    );

	    // Normalize boolean inputs to '1' or '0'
	    function normalize_boolean_value($value) {
	        $v = strtolower(trim((string)$value));
	        if ($v === '1' || $v === 'on' || $v === 'enable' || $v === 'enabled' || $v === 'true') { return '1'; }
	        if ($v === '0' || $v === 'off' || $v === 'disable' || $v === 'disabled' || $v === 'false') { return '0'; }
	        // If numeric but not recognized strings, coerce non-zero to 1, else 0
	        if (is_numeric($value)) { return ((int)$value !== 0) ? '1' : '0'; }
	        // Default to 0 for any other string
	        return '0';
	    }

	    $update_fields = array();
	    $update_attempted = false;

	    // Only apply updates if user has edit permission. If not, ignore request params.
	    if ($can_edit) {
	        foreach ($editable_columns as $col) {
	            if (isset($request[$col])) {
	                if (in_array($col, $boolean_columns, true)) {
	                    $normalized = normalize_boolean_value($request[$col]);
	                    $update_fields[] = $col . " = '" . escape($normalized) . "'";
	                } else {
	                    $update_fields[] = $col . " = '" . escape($request[$col]) . "'";
	                }
	                $update_attempted = true;
	            }
	        }

			if (!empty($update_fields)) {
			    $update_fields[] = "last_modified_user_id = '" . escape($user['user_id']) . "'";
			    $update_fields[] = "last_modified_timestamp = UNIX_TIMESTAMP()";
			
			    $update_query = "
			        UPDATE config
			        SET " . implode(", ", $update_fields) . "
			        LIMIT 1
			    ";
			
			    mysqli_query(db::$con, $update_query) or api_error('Update failed.');
				
				log_activity(lang(array('string'=>'Settings were modified by a custom app: Name: {var:1}, API Key: ({var:2}), Secret Key User: {var:3}','vars'=>array($matched_app['name'],$api_key,$user['user_username']))),$log_user);
			}
	    }

	    // Always fetch latest config (reflects edited values if any)
	    $query = "SELECT * FROM config LIMIT 1";
	    $result = mysqli_query(db::$con, $query) or api_error('Query failed.');
	    $row = mysqli_fetch_assoc($result);
	    if (!$row) {
	        api_error(lang('Site settings not found.'));
	    }

	    // Build response with full settings (strings escaped via h())
	    $response = array(
	        'url_scheme' => h($row['url_scheme']),
	        'hostname' => h($row['hostname']),
	        'email_address' => h($row['email_address']),
	        'title' => h($row['title']),
	        'meta_description' => h($row['meta_description']),
	        'meta_keywords' => h($row['meta_keywords']),
	        'mobile' => h($row['mobile']),
	        'search_type' => h($row['search_type']),
	        'social_networking' => h($row['social_networking']),
	        'social_networking_type' => h($row['social_networking_type']),
	        'social_networking_facebook' => h($row['social_networking_facebook']),
	        'social_networking_twitter' => h($row['social_networking_twitter']),
	        'social_networking_linkedin' => h($row['social_networking_linkedin']),
	        'social_networking_whatsapp' => h($row['social_networking_whatsapp']),
	        'social_networking_telegram' => h($row['social_networking_telegram']),
	        'social_networking_pinterest' => h($row['social_networking_pinterest']),
	        'social_networking_reddit' => h($row['social_networking_reddit']),
	        'social_networking_email' => h($row['social_networking_email']),
	        'social_networking_code' => h($row['social_networking_code']),
	        'captcha' => h($row['captcha']),
	        'auto_dialogs' => h($row['auto_dialogs']),
	        'mass_deletion' => h($row['mass_deletion']),
	        'strong_password' => h($row['strong_password']),
	        'password_hint' => h($row['password_hint']),
	        'remember_me' => h($row['remember_me']),
	        'forgot_password_link' => h($row['forgot_password_link']),
	        'proxy_address' => h($row['proxy_address']),
	        'badge_label' => h($row['badge_label']),
	        'timezone' => h($row['timezone']),
	        'date_format' => h($row['date_format']),
	        'time_format' => h($row['time_format']),
	        'organization_name' => h($row['organization_name']),
	        'organization_address_1' => h($row['organization_address_1']),
	        'organization_address_2' => h($row['organization_address_2']),
	        'organization_city' => h($row['organization_city']),
	        'organization_state' => h($row['organization_state']),
	        'organization_zip_code' => h($row['organization_zip_code']),
	        'organization_country' => h($row['organization_country']),
	        'opt_in_label' => h($row['opt_in_label']),
	        'plain_text_email_campaign_footer' => h($row['plain_text_email_campaign_footer']),
	        'visitor_tracking' => h($row['visitor_tracking']),
	        'tracking_code_duration' => h($row['tracking_code_duration']),
	        'pay_per_click_flag' => h($row['pay_per_click_flag']),
	        'stats_url' => h($row['stats_url']),
	        'google_analytics' => h($row['google_analytics']),
	        'google_analytics_web_property_id' => h($row['google_analytics_web_property_id']),
	        'page_editor_font' => h($row['page_editor_font']),
	        'page_editor_font_size' => h($row['page_editor_font_size']),
	        'page_editor_font_style' => h($row['page_editor_font_style']),
	        'page_editor_font_color' => h($row['page_editor_font_color']),
	        'page_editor_background_color' => h($row['page_editor_background_color']),
	        'registration_contact_group_id' => h($row['registration_contact_group_id']),
	        'registration_email_address' => h($row['registration_email_address']),
	        'member_id_label' => h($row['member_id_label']),
	        'membership_contact_group_id' => h($row['membership_contact_group_id']),
	        'membership_email_address' => h($row['membership_email_address']),
	        'membership_expiration_warning_email' => h($row['membership_expiration_warning_email']),
	        'membership_expiration_warning_email_subject' => h($row['membership_expiration_warning_email_subject']),
	        'membership_expiration_warning_email_page_id' => h($row['membership_expiration_warning_email_page_id']),
	        'membership_expiration_warning_email_days_before_expiration' => h($row['membership_expiration_warning_email_days_before_expiration']),
	        'ecommerce_on_or_off' => h($row['ecommerce']),
	        'ecommerce_multicurrency' => h($row['ecommerce_multicurrency']),
	        'ecommerce_tax' => h($row['ecommerce_tax']),
	        'ecommerce_tax_exempt' => h($row['ecommerce_tax_exempt']),
	        'ecommerce_tax_exempt_label' => h($row['ecommerce_tax_exempt_label']),
	        'ecommerce_shipping' => h($row['ecommerce_shipping']),
	        'ecommerce_recipient_mode' => h($row['ecommerce_recipient_mode']),
	        'usps_user_id' => h($row['usps_user_id']),
	        'ecommerce_address_verification' => h($row['ecommerce_address_verification']),
	        'ecommerce_address_verification_enforcement_type' => h($row['ecommerce_address_verification_enforcement_type']),
	        'ups' => h($row['ups']),
	        'ups_key' => h($row['ups_key']),
	        'ups_user_id' => h($row['ups_user_id']),
	        'ups_password' => h($row['ups_password']),
	        'ups_account' => h($row['ups_account']),
	        'fedex' => h($row['fedex']),
	        'fedex_key' => h($row['fedex_key']),
	        'fedex_password' => h($row['fedex_password']),
	        'fedex_account' => h($row['fedex_account']),
	        'fedex_meter' => h($row['fedex_meter']),
	        'ecommerce_product_restriction_message' => h($row['ecommerce_product_restriction_message']),
	        'ecommerce_no_shipping_methods_message' => h($row['ecommerce_no_shipping_methods_message']),
	        'ecommerce_end_of_day_time' => h($row['ecommerce_end_of_day_time']),
	        'ecommerce_email_address' => h($row['ecommerce_email_address']),
	        'ecommerce_gift_card' => h($row['ecommerce_gift_card']),
	        'ecommerce_gift_card_validity_days' => h($row['ecommerce_gift_card_validity_days']),
	        'ecommerce_givex' => h($row['ecommerce_givex']),
	        'ecommerce_givex_primary_hostname' => h($row['ecommerce_givex_primary_hostname']),
	        'ecommerce_givex_secondary_hostname' => h($row['ecommerce_givex_secondary_hostname']),
	        'ecommerce_givex_user_id' => h($row['ecommerce_givex_user_id']),
	        'ecommerce_givex_password' => h($row['ecommerce_givex_password']),
	        'ecommerce_credit_debit_card' => h($row['ecommerce_credit_debit_card']),
	        'ecommerce_american_express' => h($row['ecommerce_american_express']),
	        'ecommerce_diners_club' => h($row['ecommerce_diners_club']),
	        'ecommerce_discover_card' => h($row['ecommerce_discover_card']),
	        'ecommerce_mastercard' => h($row['ecommerce_mastercard']),
	        'ecommerce_visa' => h($row['ecommerce_visa']),
	        'ecommerce_troy' => h($row['ecommerce_troy']),
	        'ecommerce_payment_gateway' => h($row['ecommerce_payment_gateway']),
	        'ecommerce_payment_gateway_transaction_type' => h($row['ecommerce_payment_gateway_transaction_type']),
	        'ecommerce_payment_gateway_mode' => h($row['ecommerce_payment_gateway_mode']),
	        'ecommerce_authorizenet_api_login_id' => h($row['ecommerce_authorizenet_api_login_id']),
	        'ecommerce_authorizenet_transaction_key' => h($row['ecommerce_authorizenet_transaction_key']),
	        'ecommerce_clearcommerce_client_id' => h($row['ecommerce_clearcommerce_client_id']),
	        'ecommerce_clearcommerce_user_id' => h($row['ecommerce_clearcommerce_user_id']),
	        'ecommerce_clearcommerce_password' => h($row['ecommerce_clearcommerce_password']),
	        'ecommerce_first_data_global_gateway_store_number' => h($row['ecommerce_first_data_global_gateway_store_number']),
	        'ecommerce_first_data_global_gateway_pem_file_name' => h($row['ecommerce_first_data_global_gateway_pem_file_name']),
	        'ecommerce_paypal_payflow_pro_partner' => h($row['ecommerce_paypal_payflow_pro_partner']),
	        'ecommerce_paypal_payflow_pro_merchant_login' => h($row['ecommerce_paypal_payflow_pro_merchant_login']),
	        'ecommerce_paypal_payflow_pro_user' => h($row['ecommerce_paypal_payflow_pro_user']),
	        'ecommerce_paypal_payflow_pro_password' => h($row['ecommerce_paypal_payflow_pro_password']),
	        'ecommerce_paypal_payments_pro_api_username' => h($row['ecommerce_paypal_payments_pro_api_username']),
	        'ecommerce_paypal_payments_pro_api_password' => h($row['ecommerce_paypal_payments_pro_api_password']),
	        'ecommerce_paypal_payments_pro_api_signature' => h($row['ecommerce_paypal_payments_pro_api_signature']),
	        'ecommerce_sage_merchant_id' => h($row['ecommerce_sage_merchant_id']),
	        'ecommerce_sage_merchant_key' => h($row['ecommerce_sage_merchant_key']),
	        'ecommerce_stripe_api_key' => h($row['ecommerce_stripe_api_key']),
	        'ecommerce_iyzipay_api_key' => h($row['ecommerce_iyzipay_api_key']),
	        'ecommerce_iyzipay_secret_key' => h($row['ecommerce_iyzipay_secret_key']),
	        'ecommerce_iyzipay_threeds' => h($row['ecommerce_iyzipay_threeds']),
	        'ecommerce_surcharge_percentage' => h($row['ecommerce_surcharge_percentage']),
	        'ecommerce_paypal_express_checkout' => h($row['ecommerce_paypal_express_checkout']),
	        'ecommerce_paypal_express_checkout_transaction_type' => h($row['ecommerce_paypal_express_checkout_transaction_type']),
	        'ecommerce_paypal_express_checkout_mode' => h($row['ecommerce_paypal_express_checkout_mode']),
	        'ecommerce_paypal_express_checkout_api_username' => h($row['ecommerce_paypal_express_checkout_api_username']),
	        'ecommerce_paypal_express_checkout_api_password' => h($row['ecommerce_paypal_express_checkout_api_password']),
	        'ecommerce_paypal_express_checkout_api_signature' => h($row['ecommerce_paypal_express_checkout_api_signature']),
	        'ecommerce_offline_payment' => h($row['ecommerce_offline_payment']),
	        'ecommerce_offline_payment_only_specific_orders' => h($row['ecommerce_offline_payment_only_specific_orders']),
	        'ecommerce_private_folder_id' => h($row['ecommerce_private_folder_id']),
	        'ecommerce_retrieve_order_next_page_id' => h($row['ecommerce_retrieve_order_next_page_id']),
	        'ecommerce_reward_program' => h($row['ecommerce_reward_program']),
	        'ecommerce_reward_program_points' => h($row['ecommerce_reward_program_points']),
	        'ecommerce_reward_program_membership' => h($row['ecommerce_reward_program_membership']),
	        'ecommerce_reward_program_membership_days' => h($row['ecommerce_reward_program_membership_days']),
	        'ecommerce_reward_program_email' => h($row['ecommerce_reward_program_email']),
	        'ecommerce_reward_program_email_bcc_email_address' => h($row['ecommerce_reward_program_email_bcc_email_address']),
	        'ecommerce_reward_program_email_subject' => h($row['ecommerce_reward_program_email_subject']),
	        'ecommerce_reward_program_email_page_id' => h($row['ecommerce_reward_program_email_page_id']),
	        'ecommerce_custom_product_field_1_label' => h($row['ecommerce_custom_product_field_1_label']),
	        'ecommerce_custom_product_field_2_label' => h($row['ecommerce_custom_product_field_2_label']),
	        'ecommerce_custom_product_field_3_label' => h($row['ecommerce_custom_product_field_3_label']),
	        'ecommerce_custom_product_field_4_label' => h($row['ecommerce_custom_product_field_4_label']),
	        'forms' => h($row['forms']),
	        'calendars' => h($row['calendars']),
	        'ads' => h($row['ads']),
	        'additional_sitemap_content' => h($row['additional_sitemap_content']),
	        'additional_robots_content' => h($row['additional_robots_content']),
	        'debug' => h($row['debug']),
	        'affiliate_program' => h($row['affiliate_program']),
	        'affiliate_automatic_approval' => h($row['affiliate_automatic_approval'])
			
	    );

	    // Final response message
	    if ($update_attempted && $can_edit) {
	        api_success($response, h(lang('Site settings updated successfully.')));
	    } else {
	        api_success($response, h(lang('Site settings retrieved successfully.')));
	    }
	    break;

    // ─── PAGES ───────────────────────────────────────────────────────────────
    case 'pages':
        $can_read = has_permission($app_permissions, 'pages', 'read');
        $can_edit = has_permission($app_permissions, 'pages', 'edit');
        if (!$can_read && !$can_edit) {
            api_error(lang('Permission denied: You cannot access pages.'));
        }

        $req_page_id   = isset($request['id'])   ? (int)trim($request['id'])   : 0;
        $req_page_name = isset($request['name']) ? trim($request['name'])       : '';
        $update_fields    = array();
        $update_attempted = false;

        // Editable fields. page_meta_keywords is still accepted so that apps written
        // against the older field keep working, but it no longer drives anything;
        // page_search_keywords is the list the site search and the tag cloud read.
        $editable_columns = array('page_title', 'page_meta_description', 'page_meta_keywords', 'page_search_keywords', 'page_name');

        if ($req_page_id || $req_page_name !== '') {
            // Fetch specific page
            if ($req_page_id) {
                $page_where = "page_id = '" . escape($req_page_id) . "'";
            } else {
                $page_where = "page_name = '" . escape($req_page_name) . "'";
            }
            $result = mysqli_query(db::$con,
                "SELECT page_id, page_name, page_title, page_meta_description, page_meta_keywords, page_search, page_search_keywords
                 FROM page WHERE $page_where LIMIT 1"
            ) or api_error('Query failed.');
            $row = mysqli_fetch_assoc($result);
            if (!$row) api_error(lang('Page not found.'));

            $found_page_id = $row['page_id'];
            $original_page_search = $row['page_search'];
            $original_search_keywords = $row['page_search_keywords'];

            if ($can_edit) {
                foreach ($editable_columns as $col) {
                    if (isset($request[$col])) {
                        $update_fields[] = $col . " = '" . escape($request[$col]) . "'";
                        $update_attempted = true;
                    }
                }
                if (!empty($update_fields)) {
                    // Every editable column feeds the SEO score, so the
                    // stored analysis is stale after this write.
                    $update_fields[] = "seo_analysis_current = '0'";

                    mysqli_query(db::$con,
                        "UPDATE page SET " . implode(', ', $update_fields) .
                        " WHERE page_id = '" . escape($found_page_id) . "'"
                    ) or api_error('Update failed.');
                    log_activity(lang(array(
                        'string' => 'Page ({var:1}) modified by custom app: {var:2} (API Key: {var:3}, User: {var:4})',
                        'vars'   => array($row['page_name'], $matched_app['name'], $api_key, $user['user_username'])
                    )), $log_user);

                    // The tag cloud is built from the promote on keyword list, so it has
                    // to follow a write to it.
                    if (isset($request['page_search_keywords'])) {
                        update_tag_cloud_keywords_for_page(
                            $found_page_id,
                            $original_page_search,
                            $request['page_search_keywords'],
                            $original_page_search,
                            $original_search_keywords);
                    }

                    // Re-fetch updated row
                    $result = mysqli_query(db::$con,
                        "SELECT page_id, page_name, page_title, page_meta_description, page_meta_keywords, page_search, page_search_keywords
                         FROM page WHERE page_id = '" . escape($found_page_id) . "' LIMIT 1"
                    ) or api_error('Query failed.');
                    $row = mysqli_fetch_assoc($result);
                }
            }

            $page_response = array(
                'page_id'               => $row['page_id'],
                'page_name'             => $row['page_name'],
                'page_title'            => $row['page_title'],
                'page_meta_description' => $row['page_meta_description'],
                'page_meta_keywords'    => $row['page_meta_keywords'],
                'page_search_keywords'  => $row['page_search_keywords'],
                'url'                   => OUTPUT_PATH . $row['page_name'],
            );
            api_success($page_response, $update_attempted
                ? lang('Page updated successfully.')
                : lang('Page retrieved successfully.'));

        } else {
            // List all pages
            $result = mysqli_query(db::$con,
                "SELECT page_id, page_name, page_title FROM page ORDER BY page_name"
            ) or api_error('Query failed.');
            $pages_list = mysqli_fetch_items($result);
            $pages_output = array();
            foreach ($pages_list as $p) {
                $pages_output[] = array(
                    'page_id'    => $p['page_id'],
                    'page_name'  => $p['page_name'],
                    'page_title' => $p['page_title'],
                    'url'        => OUTPUT_PATH . $p['page_name'],
                );
            }
            api_success(array('pages' => $pages_output, 'total' => count($pages_output)),
                lang('Pages retrieved successfully.'));
        }
        break;

    // ─── USERS ───────────────────────────────────────────────────────────────
    case 'users':
        // Manager or above required
        if ($user['role'] >= 2) {
            api_error(lang('Permission denied: You are not authorized to access users.'));
        }
        $can_read = has_permission($app_permissions, 'users', 'read');
        $can_edit = has_permission($app_permissions, 'users', 'edit');
        if (!$can_read && !$can_edit) {
            api_error(lang('Permission denied: You cannot access users.'));
        }

        $req_user_id       = isset($request['id'])       ? (int)$request['id']       : 0;
        $req_user_username = isset($request['username']) ? trim($request['username']) : '';
        $update_fields     = array();
        $update_attempted  = false;

        // Safe editable fields (password, role, secret_key cannot be changed via API)
        $editable_columns = array('user_email');

        $role_labels = array(0 => 'Administrator', 1 => 'Manager', 2 => 'Designer', 3 => 'Contributor');

        if ($req_user_id || $req_user_username !== '') {
            if ($req_user_id) {
                $user_where = "user_id = '" . escape($req_user_id) . "'";
            } else {
                $user_where = "user_username = '" . escape($req_user_username) . "'";
            }
            $result = mysqli_query(db::$con,
                "SELECT user_id, user_username, user_email, user_role,
                        user_manage_contacts, user_manage_visitors, user_manage_ecommerce,
                        user_manage_forms, user_manage_calendars, user_manage_emails,
                        user_online_timestamp, user_timestamp
                 FROM user WHERE $user_where LIMIT 1"
            ) or api_error('Query failed.');
            $row = mysqli_fetch_assoc($result);
            if (!$row) api_error(lang('User not found.'));

            $found_user_id = $row['user_id'];

            if ($can_edit) {
                foreach ($editable_columns as $col) {
                    if (isset($request[$col])) {
                        $update_fields[] = $col . " = '" . escape($request[$col]) . "'";
                        $update_attempted = true;
                    }
                }
                if (!empty($update_fields)) {
                    mysqli_query(db::$con,
                        "UPDATE user SET " . implode(', ', $update_fields) .
                        " WHERE user_id = '" . escape($found_user_id) . "'"
                    ) or api_error('Update failed.');
                    log_activity(lang(array(
                        'string' => 'User ({var:1}) modified by custom app: {var:2} (API Key: {var:3}, User: {var:4})',
                        'vars'   => array($row['user_username'], $matched_app['name'], $api_key, $user['user_username'])
                    )), $log_user);
                    $result = mysqli_query(db::$con,
                        "SELECT user_id, user_username, user_email, user_role,
                                user_manage_contacts, user_manage_visitors, user_manage_ecommerce,
                                user_manage_forms, user_manage_calendars, user_manage_emails,
                                user_online_timestamp, user_timestamp
                         FROM user WHERE user_id = '" . escape($found_user_id) . "' LIMIT 1"
                    ) or api_error('Query failed.');
                    $row = mysqli_fetch_assoc($result);
                }
            }

            $user_response = array(
                'user_id'            => $row['user_id'],
                'username'           => $row['user_username'],
                'email'              => $row['user_email'],
                'role'               => (int)$row['user_role'],
                'role_label'         => isset($role_labels[(int)$row['user_role']]) ? $role_labels[(int)$row['user_role']] : 'Unknown',
                'manage_contacts'    => $row['user_manage_contacts'],
                'manage_visitors'    => $row['user_manage_visitors'],
                'manage_ecommerce'   => $row['user_manage_ecommerce'],
                'manage_forms'       => $row['user_manage_forms'],
                'manage_calendars'   => $row['user_manage_calendars'],
                'manage_emails'      => $row['user_manage_emails'],
                'last_seen_timestamp'=> $row['user_online_timestamp'],
                'created_timestamp'  => $row['user_timestamp'],
            );
            api_success($user_response, $update_attempted
                ? lang('User updated successfully.')
                : lang('User retrieved successfully.'));

        } else {
            // List all users
            $result = mysqli_query(db::$con,
                "SELECT user_id, user_username, user_email, user_role, user_online_timestamp
                 FROM user ORDER BY user_role ASC, user_username ASC"
            ) or api_error('Query failed.');
            $users_list = mysqli_fetch_items($result);
            $users_output = array();
            foreach ($users_list as $u) {
                $users_output[] = array(
                    'user_id'             => $u['user_id'],
                    'username'            => $u['user_username'],
                    'email'               => $u['user_email'],
                    'role'                => (int)$u['user_role'],
                    'role_label'          => isset($role_labels[(int)$u['user_role']]) ? $role_labels[(int)$u['user_role']] : 'Unknown',
                    'last_seen_timestamp' => $u['user_online_timestamp'],
                );
            }
            api_success(array('users' => $users_output, 'total' => count($users_output)),
                lang('Users retrieved successfully.'));
        }
        break;

    // ─── VISITORS ────────────────────────────────────────────────────────────
    case 'visitors':
        $can_read = has_permission($app_permissions, 'visitors', 'read');
        if (!$can_read) {
            api_error(lang('Permission denied: You cannot access visitor data.'));
        }
        // Role: manager or above, or manage_visitors flag required
        if ($user['role'] >= 3 && !$user['manage_visitors']) {
            api_error(lang('Permission denied: You are not authorized to access visitor data.'));
        }

        $req_visitor_id = isset($request['id'])        ? (int)$request['id']          : 0;
        $date_from      = isset($request['date_from']) ? trim($request['date_from'])   : '';
        $date_to        = isset($request['date_to'])   ? trim($request['date_to'])     : '';
        $limit_count    = isset($request['limit'])     ? max(1, min((int)$request['limit'], 500)) : 50;
        $page_filter    = isset($request['page'])      ? trim($request['page'])        : '';

        if ($req_visitor_id) {
            // Single visitor detail
            $result = mysqli_query(db::$con,
                "SELECT id, INET_NTOA(ip_address) as ip_address,
                        landing_page_name, first_visit, page_views,
                        city, state, zip_code, country,
                        referring_host_name, referring_search_engine, referring_search_terms,
                        utm_source, utm_medium, utm_campaign, utm_term, utm_content,
                        custom_form_submitted, custom_form_name,
                        order_created, order_completed, order_total,
                        start_timestamp, stop_timestamp
                 FROM visitors WHERE id = '" . escape($req_visitor_id) . "' LIMIT 1"
            ) or api_error('Query failed.');
            $row = mysqli_fetch_assoc($result);
            if (!$row) api_error(lang('Visitor not found.'));
            $row['order_total_formatted'] = ($row['order_total'] > 0)
                ? sprintf('%01.2f', $row['order_total'] / 100) : '0.00';
            api_success($row, lang('Visitor retrieved successfully.'));

        } else {
            // Summary stats + visitor list
            $where_parts = array();
            if ($date_from !== '') {
                $ts_from = strtotime($date_from);
                if ($ts_from) $where_parts[] = "start_timestamp >= '" . escape($ts_from) . "'";
            }
            if ($date_to !== '') {
                $ts_to = strtotime($date_to . ' 23:59:59');
                if ($ts_to) $where_parts[] = "start_timestamp <= '" . escape($ts_to) . "'";
            }
            if ($page_filter !== '') {
                $where_parts[] = "landing_page_name = '" . escape($page_filter) . "'";
            }
            $where_sql = !empty($where_parts) ? 'WHERE ' . implode(' AND ', $where_parts) : '';

            // Aggregate stats
            $stats_result = mysqli_query(db::$con,
                "SELECT COUNT(*) as total_visitors,
                        SUM(page_views) as total_page_views,
                        SUM(custom_form_submitted) as total_form_submissions,
                        SUM(order_completed) as total_orders,
                        SUM(first_visit) as new_visitors
                 FROM visitors $where_sql"
            ) or api_error('Query failed.');
            $stats = mysqli_fetch_assoc($stats_result);

            // Visitor list
            $list_result = mysqli_query(db::$con,
                "SELECT id, INET_NTOA(ip_address) as ip_address, landing_page_name,
                        first_visit, page_views, city, country, start_timestamp
                 FROM visitors $where_sql
                 ORDER BY start_timestamp DESC
                 LIMIT " . escape($limit_count)
            ) or api_error('Query failed.');
            $visitors_list = mysqli_fetch_items($list_result);

            api_success(array(
                'summary'  => array(
                    'total_visitors'        => (int)$stats['total_visitors'],
                    'total_page_views'      => (int)$stats['total_page_views'],
                    'total_form_submissions'=> (int)$stats['total_form_submissions'],
                    'total_orders'          => (int)$stats['total_orders'],
                    'new_visitors'          => (int)$stats['new_visitors'],
                ),
                'visitors' => $visitors_list,
                'total'    => count($visitors_list),
            ), lang('Visitor data retrieved successfully.'));
        }
        break;

    // ─── PRODUCTS LIST ───────────────────────────────────────────────────────
    case 'products':
        // The store has to be on.
        if (!(defined('ECOMMERCE') && ECOMMERCE === true)) {
            api_error(lang('Permission denied.'));
        }
        // User role: a contributor needs the manage_ecommerce flag.
        if ($user['role'] >= 3 && !$user['manage_ecommerce']) {
            api_error(lang('Permission denied.'));
        }
        // App permission: applies to every role.
        if (!has_permission($app_permissions, 'products', 'read')) {
            api_error(lang('Permission denied: You cannot access products.'));
        }

        // Filters
        $req_id      = isset($request['id'])      ? (int)trim($request['id'])      : 0;
        $req_name    = isset($request['name'])     ? trim($request['name'])         : '';
        $req_enabled = isset($request['enabled'])  ? trim($request['enabled'])      : '';
        $req_search  = isset($request['search'])   ? trim($request['search'])       : '';
        $req_limit   = isset($request['limit'])    ? max(1, min((int)$request['limit'], 500)) : 100;

        // Single product by id
        if ($req_id > 0) {
            $result = mysqli_query(db::$con,
                "SELECT products.id, products.name, products.enabled, products.price,
                        products.inventory, products.inventory_quantity,
                        products.short_description, products.image_name,
                        products.out_of_stock, products.out_of_stock_timestamp,
                        products.taxable, products.form_name, products.seo_score,
                        products.timestamp,
                        user.user_username as user
                 FROM products
                 LEFT JOIN user ON products.user = user.user_id
                 WHERE products.id = '" . escape($req_id) . "'
                 LIMIT 1"
            ) or api_error(lang('A system error occurred.'));
            $row = mysqli_fetch_assoc($result);
            if (empty($row)) {
                api_error(lang('Product not found.'));
            }
            $row['price'] = number_format($row['price'] / 100, 2, '.', '');
            api_success($row, lang('Product retrieved successfully.'));
        }

        // Build WHERE
        $where_parts = array();
        if ($req_name !== '') {
            $where_parts[] = "products.name LIKE '%" . escape($req_name) . "%'";
        }
        if ($req_search !== '') {
            $where_parts[] = "(products.name LIKE '%" . escape($req_search) . "%' OR products.short_description LIKE '%" . escape($req_search) . "%')";
        }
        if ($req_enabled === '1' || $req_enabled === '0') {
            $where_parts[] = "products.enabled = '" . escape($req_enabled) . "'";
        }
        $where_sql = empty($where_parts) ? '' : 'WHERE ' . implode(' AND ', $where_parts);

        $result = mysqli_query(db::$con,
            "SELECT products.id, products.name, products.enabled, products.price,
                    products.inventory, products.inventory_quantity,
                    products.short_description, products.image_name,
                    products.out_of_stock, products.out_of_stock_timestamp,
                    products.taxable, products.form_name, products.seo_score,
                    products.timestamp,
                    user.user_username as user
             FROM products
             LEFT JOIN user ON products.user = user.user_id
             $where_sql
             ORDER BY products.name ASC
             LIMIT " . escape($req_limit)
        ) or api_error(lang('A system error occurred.'));

        $products = array();
        while ($row = mysqli_fetch_assoc($result)) {
            $row['price'] = number_format($row['price'] / 100, 2, '.', '');
            $products[] = $row;
        }
        api_success(array('products' => $products, 'total' => count($products)), lang('Products retrieved successfully.'));
        break;

    // ─── ORDERS LIST ─────────────────────────────────────────────────────────
    case 'orders':
        // The store has to be on.
        if (!(defined('ECOMMERCE') && ECOMMERCE === true)) {
            api_error(lang('Permission denied.'));
        }
        // User role: a contributor needs the manage_ecommerce flag.
        if ($user['role'] >= 3 && !$user['manage_ecommerce']) {
            api_error(lang('Permission denied.'));
        }
        // App permission: applies to every role.
        if (!has_permission($app_permissions, 'orders', 'read')) {
            api_error(lang('Permission denied: You cannot access orders.'));
        }

        // Filters
        $req_id           = isset($request['id'])            ? (int)trim($request['id'])     : 0;
        $req_status       = isset($request['status'])        ? trim($request['status'])       : '';
        $req_order_number = isset($request['order_number'])  ? trim($request['order_number']) : '';
        $req_email        = isset($request['email'])         ? trim($request['email'])        : '';
        $req_date_from    = isset($request['date_from'])     ? trim($request['date_from'])    : '';
        $req_date_to      = isset($request['date_to'])       ? trim($request['date_to'])      : '';
        $req_limit        = isset($request['limit'])         ? max(1, min((int)$request['limit'], 500)) : 50;

        // Single order by id
        if ($req_id > 0) {
            $result = mysqli_query(db::$con,
                "SELECT orders.id, orders.order_number, orders.order_date, orders.status,
                        orders.billing_first_name, orders.billing_last_name,
                        orders.billing_email_address, orders.billing_company,
                        orders.billing_address_1, orders.billing_address_2,
                        orders.billing_city, orders.billing_state, orders.billing_zip_code,
                        orders.billing_country, orders.billing_phone_number,
                        orders.subtotal, orders.discount, orders.tax, orders.shipping,
                        orders.surcharge, orders.total,
                        orders.payment_method, orders.transaction_id,
                        orders.special_offer_code, orders.notes,
                        user.user_username as user,
                        contacts.member_id
                 FROM orders
                 LEFT JOIN user ON orders.user_id = user.user_id
                 LEFT JOIN contacts ON orders.contact_id = contacts.id
                 WHERE orders.id = '" . escape($req_id) . "'
                 LIMIT 1"
            ) or api_error(lang('A system error occurred.'));
            $row = mysqli_fetch_assoc($result);
            if (empty($row)) {
                api_error(lang('Order not found.'));
            }
            // Prices are stored in cents
            foreach (array('subtotal','discount','tax','shipping','surcharge','total') as $f) {
                $row[$f] = number_format($row[$f] / 100, 2, '.', '');
            }
            // Order items
            $items_result = mysqli_query(db::$con,
                "SELECT order_items.id, products.name as product_name, order_items.quantity,
                        order_items.price, order_items.item_number
                 FROM order_items
                 LEFT JOIN products ON order_items.product_id = products.id
                 WHERE order_items.order_id = '" . escape($req_id) . "'
                 ORDER BY order_items.id ASC"
            ) or api_error(lang('A system error occurred.'));
            $items = array();
            while ($item = mysqli_fetch_assoc($items_result)) {
                $item['price'] = number_format($item['price'] / 100, 2, '.', '');
                $items[] = $item;
            }
            $row['items'] = $items;
            api_success($row, lang('Order retrieved successfully.'));
        }

        // Build WHERE
        $where_parts = array();

        // Date range — no default; omitting both returns all orders
        if ($req_date_from !== '') {
            $ts_from = strtotime($req_date_from);
            if ($ts_from !== false) $where_parts[] = "orders.order_date >= '" . escape($ts_from) . "'";
        }
        if ($req_date_to !== '') {
            $ts_to = strtotime($req_date_to . ' 23:59:59');
            if ($ts_to !== false) $where_parts[] = "orders.order_date <= '" . escape($ts_to) . "'";
        }

        // Status filter
        $allowed_statuses = array('complete', 'incomplete', 'exported');
        if ($req_status !== '' && in_array($req_status, $allowed_statuses)) {
            $where_parts[] = "orders.status = '" . escape($req_status) . "'";
        } elseif ($req_status === 'complete_or_exported') {
            $where_parts[] = "(orders.status = 'complete' OR orders.status = 'exported')";
        }

        if ($req_order_number !== '') {
            $where_parts[] = "orders.order_number LIKE '%" . escape($req_order_number) . "%'";
        }
        if ($req_email !== '') {
            $where_parts[] = "orders.billing_email_address LIKE '%" . escape($req_email) . "%'";
        }

        $where_sql = empty($where_parts) ? '' : 'WHERE ' . implode(' AND ', $where_parts);

        $result = mysqli_query(db::$con,
            "SELECT orders.id, orders.order_number, orders.order_date, orders.status,
                    orders.billing_first_name, orders.billing_last_name,
                    orders.billing_email_address, orders.billing_company,
                    orders.subtotal, orders.discount, orders.tax, orders.shipping,
                    orders.surcharge, orders.total,
                    orders.payment_method, orders.transaction_id,
                    orders.special_offer_code,
                    user.user_username as user,
                    contacts.member_id
             FROM orders
             LEFT JOIN user ON orders.user_id = user.user_id
             LEFT JOIN contacts ON orders.contact_id = contacts.id
             $where_sql
             ORDER BY orders.id DESC
             LIMIT " . escape($req_limit)
        ) or api_error(lang('A system error occurred.'));

        $orders = array();
        while ($row = mysqli_fetch_assoc($result)) {
            foreach (array('subtotal','discount','tax','shipping','surcharge','total') as $f) {
                $row[$f] = number_format($row[$f] / 100, 2, '.', '');
            }
            $orders[] = $row;
        }
        api_success(array('orders' => $orders, 'total' => count($orders)), lang('Orders retrieved successfully.'));
        break;

    default:
        api_error(lang('Invalid action.'));
}




