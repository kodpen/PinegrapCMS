<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// Schema steps of the LiveSite years, 2017.2 through 2023.2.1, moved here from
// install/index.php as they were. Loaded once per upgrade run by runner.php;
// never requested on its own.
if (!defined('INSTALL_OR_UPDATE')) {
	exit;
}

// Add custom shipping form support to express order


function upgrade_to_2017_2_1() {

	db("ALTER TABLE express_order_pages ADD shipping_form TINYINT UNSIGNED NOT NULL DEFAULT 0");

	db("ALTER TABLE express_order_pages DROP shipping_address_and_arrival_page_id");

	// Add new form type property to fields because we will need to distiguish between shipping
	// and billing fields for express order
	db("ALTER TABLE form_fields ADD form_type ENUM('', 'custom', 'product', 'shipping', 'billing') NOT NULL DEFAULT ''");

	db("ALTER TABLE form_fields ADD INDEX form_type (form_type)");

	// Get all fields in order to set form type
	

	$fields = db_items(

	"SELECT

			form_fields.id,

			form_fields.product_id,

			page.page_type

		FROM form_fields

		LEFT JOIN page ON form_fields.page_id = page.page_id

		ORDER BY form_fields.id");

	foreach ($fields as $field) {

		$field['form_type'] = '';

		if ($field['page_type'] == 'custom form') {

			$field['form_type'] = 'custom';

		}
		else if ($field['page_type'] == 'shipping address and arrival') {

			$field['form_type'] = 'shipping';

		}
		else if (

		$field['page_type'] == 'billing information' or $field['page_type'] == 'express order') {

			$field['form_type'] = 'billing';

		}
		else if ($field['product_id']) {

			$field['form_type'] = 'product';

		}

		db(

		"UPDATE form_fields

			SET form_type = '" . $field['form_type'] . "'

			WHERE id = '" . $field['id'] . "'");

	}

}

// Update forgot password feature to send token link in email instead of temp password.


function upgrade_to_2017_2_2() {

	// Try to find a change random password page, so we can update field names
	$page_id = db("SELECT page_id FROM page WHERE page_type = 'change random password' LIMIT 1");

	// Change page_type enum from change random password to set password for in page table
	db("ALTER TABLE page CHANGE page_type page_type ENUM( 'standard', 'change password', 

		'set password', 'email a friend', 'error', 'folder view', 'forgot password', 'login', 

		'logout', 'photo gallery', 'membership confirmation', 'membership entrance', 'my account', 

		'my account profile', 'email preferences', 'view order', 'update address book', 'custom form', 

		'custom form confirmation', 'form list view', 'form item view', 'form view directory', 

		'calendar view', 'calendar event view', 'catalog', 'catalog detail', 'express order', 

		'order form', 'shopping cart', 'shipping address and arrival', 'shipping method', 

		'billing information', 'order preview', 'order receipt', 'registration confirmation', 

		'registration entrance', 'search results', 'affiliate sign up form', 

		'affiliate sign up confirmation', 'affiliate welcome') NOT NULL DEFAULT 'standard'");

	// Find root folder to assign to set-pass page
	$root_folder = db("SELECT folder_id FROM folder WHERE folder_parent = '0' LIMIT 1");

	// If page id was found, rename change random password to set-pass
	// And change folder to root
	if ($page_id != '') {

		db(

		"UPDATE page SET 

				page_type = 'set password', 

				page_name = 'set-pass', 

				page_folder = '" . $root_folder . "' 

			WHERE page_id = '" . $page_id . "' LIMIT 1");

	}

	// If a change random password page was found and there is a custom layout, then update custom layout
	if ($page_id and file_exists(LAYOUT_DIRECTORY_PATH . '/' . $page_id . '.php')) {

		// Get the custom layout content
		$content = file_get_contents(LAYOUT_DIRECTORY_PATH . '/' . $page_id . '.php');

		// If a custom layout file was found, then continue to update it
		if ($content) {

			// Backup old file
			copy(

			LAYOUT_DIRECTORY_PATH . '/' . $page_id . '.php',

			LAYOUT_DIRECTORY_PATH . '/' . $page_id . '.bak.php');

			// Remove password verify
			$content = str_replace('<input type="password" name="new_password_verify" id="new_password_verify" placeholder="Confirm New Password*">', '', $content);

			// Update custom layout
			file_put_contents(LAYOUT_DIRECTORY_PATH . '/' . $page_id . '.php', $content);

		}

	}

	// Try to find a forgot password page, so we can update button to send email
	$page_id = db("SELECT page_id FROM page WHERE page_type = 'forgot password' LIMIT 1");

	// If a forgot password page was found and there is a custom layout, then update custom layout
	if ($page_id and file_exists(LAYOUT_DIRECTORY_PATH . '/' . $page_id . '.php')) {

		// Get the custom layout content
		$content = file_get_contents(LAYOUT_DIRECTORY_PATH . '/' . $page_id . '.php');

		// If a custom layout file was found, then continue to update it
		if ($content) {

			// Backup old file
			copy(

			LAYOUT_DIRECTORY_PATH . '/' . $page_id . '.php',

			LAYOUT_DIRECTORY_PATH . '/' . $page_id . '.bak.php');

			$new_label = 'Send Email';

			// Search for two common old labels and replace with new label.
			$content = str_replace('Email Temporary Password', $new_label, $content);

			$content = str_replace('Send Password', $new_label, $content);

			// Update custom layout
			file_put_contents(LAYOUT_DIRECTORY_PATH . '/' . $page_id . '.php', $content);

		}

	}

	// Alter table to handle token to be emailed for reset password. We purposely allow NULL for
	// the token column, so that we can use a UNIQUE index. Most of the users won't have a token
	// at any given time, but MySQL allows a UNIQUE index for multiple NULL records (does not allow
	// that for empty string).
	db(

	"ALTER TABLE user

		DROP user_random_password,

		ADD token VARCHAR(64),

		ADD token_timestamp INT UNSIGNED NOT NULL DEFAULT 0,

		ADD UNIQUE (token)");

}

// Add feature to allow the commerce manager to set whether the key code or offer code should be
// reported on, for each key code.


function upgrade_to_2017_2_3() {

	db("ALTER TABLE key_codes ADD report ENUM('key_code', 'offer_code') NOT NULL DEFAULT 'key_code'");

	// Update existing key codes so that report is set to offer code for single-use key codes,
	// because that was the previous way that we used to determine if an offer code should be
	// reported on.
	db("UPDATE key_codes SET report = 'offer_code' WHERE single_use = '1'");

}

// Add real-time delivery date feature.


function upgrade_to_2017_2_4() {

	// Add new real-time rate column because the service column is now going to be used for both
	// real-time rates and delivery dates.
	db("ALTER TABLE shipping_methods ADD realtime_rate TINYINT UNSIGNED NOT NULL DEFAULT 0");

	db("UPDATE shipping_methods SET realtime_rate = '1' WHERE service != ''");

	db("ALTER TABLE shipping_methods CHANGE service service ENUM('', 'usps_priority', 'usps_express', 'usps_ground', 'ups_next_day_air', 'ups_next_day_air_early', 'ups_next_day_air_saver', 'ups_2nd_day_air', 'ups_2nd_day_air_am', 'ups_3_day_select', 'ups_ground', 'fedex_first_overnight', 'fedex_priority_overnight', 'fedex_standard_overnight', 'fedex_2_day_am', 'fedex_2_day', 'fedex_express_saver', 'fedex_ground') NOT NULL DEFAULT ''");

	db("ALTER TABLE shipping_rates CHANGE service service ENUM('usps_priority', 'usps_express', 'usps_ground', 'ups_next_day_air', 'ups_next_day_air_early', 'ups_next_day_air_saver', 'ups_2nd_day_air', 'ups_2nd_day_air_am', 'ups_3_day_select', 'ups_ground', 'fedex_first_overnight', 'fedex_priority_overnight', 'fedex_standard_overnight', 'fedex_2_day_am', 'fedex_2_day', 'fedex_express_saver', 'fedex_ground') NOT NULL DEFAULT 'usps_priority'");

	db(

	"ALTER TABLE config

		ADD ups TINYINT UNSIGNED NOT NULL DEFAULT 0,

		ADD fedex TINYINT UNSIGNED NOT NULL DEFAULT 0,

		ADD fedex_key VARCHAR(100) NOT NULL DEFAULT '',

		ADD fedex_password VARCHAR(100) NOT NULL DEFAULT '',

		ADD fedex_account VARCHAR(100) NOT NULL DEFAULT '',

		ADD fedex_meter VARCHAR(100) NOT NULL DEFAULT ''");

	// Enable new ups check box if site was using UPS.
	db("UPDATE config SET ups = '1' WHERE ups_key != ''");

	// Create delivery date cache table in order to minimize requests to carriers.
	db(

	"CREATE TABLE shipping_delivery_dates (

			id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,

			service ENUM('usps_priority', 'usps_express', 'usps_ground', 'ups_next_day_air', 'ups_next_day_air_early', 'ups_next_day_air_saver', 'ups_2nd_day_air', 'ups_2nd_day_air_am', 'ups_3_day_select', 'ups_ground', 'fedex_first_overnight', 'fedex_priority_overnight', 'fedex_standard_overnight', 'fedex_2_day_am', 'fedex_2_day', 'fedex_express_saver', 'fedex_ground') NOT NULL DEFAULT 'usps_priority',

			zip_code VARCHAR(50) NOT NULL DEFAULT '',

			ship_date DATE NOT NULL DEFAULT '0000-00-00',

			delivery_date DATE NOT NULL DEFAULT '0000-00-00',

			timestamp INT UNSIGNED NOT NULL DEFAULT 0,

			INDEX combination (service, zip_code, ship_date),

			INDEX timestamp (timestamp)

		)" . ENGINE);

}

// Add handling features in order to determine, more precisely, when a shipment is shipped out.


function upgrade_to_2017_2_5() {

	db(

	"ALTER TABLE shipping_methods

		ADD handle_days SMALLINT UNSIGNED NOT NULL DEFAULT 0,

		ADD handle_mon TINYINT UNSIGNED NOT NULL DEFAULT 0,

		ADD handle_tue TINYINT UNSIGNED NOT NULL DEFAULT 0,

		ADD handle_wed TINYINT UNSIGNED NOT NULL DEFAULT 0,

		ADD handle_thu TINYINT UNSIGNED NOT NULL DEFAULT 0,

		ADD handle_fri TINYINT UNSIGNED NOT NULL DEFAULT 0,

		ADD handle_sat TINYINT UNSIGNED NOT NULL DEFAULT 0,

		ADD handle_sun TINYINT UNSIGNED NOT NULL DEFAULT 0,

		ADD ship_mon TINYINT UNSIGNED NOT NULL DEFAULT 0,

		ADD ship_tue TINYINT UNSIGNED NOT NULL DEFAULT 0,

		ADD ship_wed TINYINT UNSIGNED NOT NULL DEFAULT 0,

		ADD ship_thu TINYINT UNSIGNED NOT NULL DEFAULT 0,

		ADD ship_fri TINYINT UNSIGNED NOT NULL DEFAULT 0,

		ADD ship_sat TINYINT UNSIGNED NOT NULL DEFAULT 0,

		ADD ship_sun TINYINT UNSIGNED NOT NULL DEFAULT 0,

		ADD end_of_day TIME NOT NULL DEFAULT '00:00:00'");

	// Update existing shipping methods so weekdays are enabled for both handling and shipping.
	db(

	"UPDATE shipping_methods SET

			handle_mon = '1',

			handle_tue = '1',

			handle_wed = '1',

			handle_thu = '1',

			handle_fri = '1',

			ship_mon = '1',

			ship_tue = '1',

			ship_wed = '1',

			ship_thu = '1',

			ship_fri = '1'");

}

// Add feature to allow only certain countries to require zip code.  Also, adding indexes to increase
// performance.


function upgrade_to_2017_2_6() {

	db(

	"ALTER TABLE countries

		ADD zip_code_required TINYINT UNSIGNED NOT NULL DEFAULT 0,

		ADD INDEX code (code),

		ADD INDEX default_selected (default_selected)");

	// Update certain countries so that zip code is required.
	// Source: https://www.ups.com/worldshiphelp/WS16/ENU/AppHelp/Codes/Countries_Territories_Requiring_Postal_Codes.htm
	

	$zip_code_required_countries = array(
		'DZ',
		'AR',
		'AM',
		'AU',
		'AT',
		'AZ',
		'A2',
		'BD',
		'BY',
		'BE',
		'BA',
		'BR',
		'BN',
		'BG',
		'CA',
		'IC',
		'CN',
		'HR',
		'CY',
		'CZ',
		'DK',
		'EN',
		'EE',
		'FO',
		'FI',
		'FR',
		'GE',
		'DE',
		'GR',
		'GL',
		'GU',
		'GG',
		'HO',
		'HU',
		'IN',
		'ID',
		'IL',
		'IT',
		'JP',
		'JE',
		'KZ',
		'KR',
		'KO',
		'KG',
		'LV',
		'LI',
		'LT',
		'LU',
		'MK',
		'MG',
		'M3',
		'MY',
		'MH',
		'MQ',
		'YT',
		'MX',
		'MN',
		'ME',
		'NL',
		'NZ',
		'NB',
		'NO',
		'PK',
		'PH',
		'PL',
		'PO',
		'PT',
		'PR',
		'RE',
		'RU',
		'SA',
		'SF',
		'CS',
		'SG',
		'SK',
		'SI',
		'ZA',
		'ES',
		'LK',
		'NT',
		'SX',
		'UV',
		'VL',
		'SE',
		'CH',
		'TW',
		'TJ',
		'TH',
		'TU',
		'TN',
		'TR',
		'TM',
		'VI',
		'UA',
		'GB',
		'US',
		'UY',
		'UZ',
		'VA',
		'VN',
		'WL',
		'YA'
	);

	foreach ($zip_code_required_countries as $country) {

		db("UPDATE countries SET zip_code_required = '1' WHERE code = '" . e($country) . "'");

	}

}

// Add index for order reference code in order to improve performance.  When an order is created
// a lookup is done in order to check that the new reference code is unique.  Previously, when a
// site had millions of orders, that lookup could become slow.  That might have caused the customer
// to experience a 1-2 second delay after adding an item to the cart.


function upgrade_to_2017_2_7() {

	// Get orders that do not have a reference code, in order to add a reference code, so that we
	// can add a unique index further below.  We noticed that one site had a bunch of orders
	// with no reference code.  We are not sure why.
	$orders = db_items("SELECT id FROM orders WHERE reference_code = ''");

	if ($orders) {

		// Add a regular index temporarily for performance reasons.
		db("ALTER TABLE orders ADD INDEX reference_code (reference_code)");

		// Loop through the orders in order to insert a reference code.
		foreach ($orders as $order) {

			db(

			"UPDATE orders SET reference_code = '" . e(generate_order_reference_code()) . "'

				WHERE id = '" . e($order['id']) . "'");

		}

		// Remove the regular index, because we don't need it anymore and we want to add a unique
		// index below.
		db("ALTER TABLE orders DROP INDEX reference_code");

	}

	// Now, add the unique index that we want.
	db("ALTER TABLE orders ADD UNIQUE reference_code (reference_code)");

}

// Add order shipped auto campaign feature


function upgrade_to_2017_2_8() {

	db(

	"ALTER TABLE email_campaign_profiles

		ADD purpose ENUM('commercial', 'transactional') NOT NULL DEFAULT 'commercial',

		CHANGE action action ENUM('calendar_event_reserved', 'custom_form_submitted', 'email_campaign_sent', 'order_abandoned', 'order_completed', 'order_shipped', 'product_ordered') NOT NULL DEFAULT 'calendar_event_reserved'");

	db(

	"ALTER TABLE email_campaigns

		ADD purpose ENUM('commercial', 'transactional') NOT NULL DEFAULT 'commercial',

		CHANGE action action ENUM('', 'calendar_event_reserved', 'custom_form_submitted', 'email_campaign_sent', 'gift_card_ordered', 'order_abandoned', 'order_completed', 'order_shipped', 'product_ordered') NOT NULL DEFAULT ''");

	db("UPDATE email_campaigns SET purpose = 'transactional' WHERE action = 'gift_card_ordered'");

}

// Add feature to allow ul class for menu to be set.


function upgrade_to_2017_2_9() {

	db("ALTER TABLE menus ADD class VARCHAR(255) NOT NULL DEFAULT ''");

}

// Add notes feature to key codes.


function upgrade_to_2017_2_10() {

	db("ALTER TABLE key_codes ADD notes MEDIUMTEXT NOT NULL DEFAULT ''");

}

// Add MailChimp feature to sync products and orders.


function upgrade_to_2017_2_11() {

	db("ALTER TABLE config

		ADD mailchimp TINYINT UNSIGNED NOT NULL DEFAULT 0,

		ADD mailchimp_key VARCHAR(100) NOT NULL DEFAULT '',

		ADD mailchimp_list_id VARCHAR(100) NOT NULL DEFAULT '',

		ADD mailchimp_store_id VARCHAR(100) NOT NULL DEFAULT '',

		ADD mailchimp_sync_running TINYINT UNSIGNED NOT NULL DEFAULT 0,

		ADD mailchimp_sync_days SMALLINT UNSIGNED NOT NULL DEFAULT 0,

		ADD mailchimp_sync_limit INT UNSIGNED NOT NULL DEFAULT 0,

		ADD mailchimp_automation TINYINT UNSIGNED NOT NULL DEFAULT 0");

	// Set defaults so job will sync the past 3 years of orders, and will sync a max of 200 orders
	// each time it runs.
	db("UPDATE config SET mailchimp_sync_days = '1095', mailchimp_sync_limit = '200'");

	db("ALTER TABLE orders

		ADD mailchimp_sync_timestamp INT UNSIGNED NOT NULL DEFAULT 0,

		ADD mailchimp_sync_error TINYINT UNSIGNED NOT NULL DEFAULT 0,

		ADD INDEX mailchimp_sync_timestamp (mailchimp_sync_timestamp)");

	// If there is not already an index for billing_email_address, then add one.  We noticed that
	// a few sites had customizations where there was already an index for that column.
	// The index for the billing email address is necessary because we need to look up the total
	// number of orders and total revenue for a customer, in order to send it to MailChimp.
	if (!db("SHOW INDEX FROM orders WHERE Column_name = 'billing_email_address'")) {

		db("ALTER TABLE orders ADD INDEX billing_email_address (billing_email_address)");

	}

	db("ALTER TABLE product_groups

		ADD mailchimp_sync_timestamp INT UNSIGNED NOT NULL DEFAULT 0,

		ADD INDEX timestamp (timestamp),

		ADD INDEX mailchimp_sync_timestamp (mailchimp_sync_timestamp)");

	db("ALTER TABLE products

		ADD mailchimp_sync_timestamp INT UNSIGNED NOT NULL DEFAULT 0,

		ADD INDEX mailchimp_sync_timestamp (mailchimp_sync_timestamp)");

}

// Add feature to allow multiple products for offer rules.


function upgrade_to_2017_2_12() {

	db("

		CREATE TABLE offer_rules_products_xref

		(

			offer_rule_id INT UNSIGNED NOT NULL DEFAULT 0,

			product_id INT UNSIGNED NOT NULL DEFAULT 0,

			INDEX offer_rule_id (offer_rule_id),

			INDEX product_id (product_id)

		)" . ENGINE);

	// Get existing offer rules that have a required product in order to move info to new table.
	$offer_rules = db_items("

		SELECT id, required_product_id FROM offer_rules WHERE required_product_id != '0'");

	// If there are offer rules, the move info to new table.
	if ($offer_rules) {

		foreach ($offer_rules as $offer_rule) {

			db("

				INSERT INTO offer_rules_products_xref (

					offer_rule_id,

					product_id)

				VALUES (

					'" . e($offer_rule['id']) . "',

					'" . e($offer_rule['required_product_id']) . "')");

		}

	}

	// Remove the old product column that is no longer necessary.
	db("ALTER TABLE offer_rules DROP required_product_id");

}

// Add custom layout support for form item views.


function upgrade_to_2017_2_13() {

	// We have to run the query below because even though we did not support custom layout for form
	// item views in the past, a form item view might have been set to custom in the DB, if it was
	// duplicated from another page.  After the update, we want all form item views to have a
	// system layout, like before.
	

	db("UPDATE page SET layout_type = 'system', layout_modified = '0'

		WHERE page_type = 'form item view'");

}

//subsription key
// Add language option and default theme option for software.
//Theme is Depricated, we are using browser based light/dark theme now.
function upgrade_to_2019_1_1() {

	db("ALTER TABLE config
	ADD subscription_key VARCHAR(256) NOT NULL DEFAULT '',
	ADD software_theme ENUM('coloron','darkon','lighton') NOT NULL DEFAULT 'coloron'");

}

// Add developer pass pin to All Users (default pin:0000)
//Developers define to config.php to page name and an unlock pin to lock pages.
//If page name define like below, users redirect to developer_lock.php.
//Software ask's pin code to unlock and access to page.
//If user enter pin  correct, software redirect user to page try to access and no redirect developer_lock.php until Pin code is change.
function upgrade_to_2019_1_2() {

	db("ALTER TABLE user ADD user_devpasspin VARCHAR(4) NOT NULL DEFAULT '0000'");

}

// Here we integrate payment gateway iyzipay api key and secret key
function upgrade_to_2019_1_3() {

	db("ALTER TABLE config 

		ADD ecommerce_iyzipay_api_key VARCHAR(255) NOT NULL DEFAULT '',

		ADD ecommerce_iyzipay_secret_key VARCHAR(255) NOT NULL DEFAULT ''");

}

// Than update gateway Select and include iyzipay option
function upgrade_to_2019_1_4() {

	db("ALTER TABLE config CHANGE ecommerce_payment_gateway ecommerce_payment_gateway ENUM('', 'Authorize.Net', 'ClearCommerce', 'First Data Global Gateway', 'PayPal Payflow Pro', 'PayPal Payments Pro', 'Sage', 'Stripe','Iyzipay') NOT NULL DEFAULT ''");

}

function upgrade_to_2019_1_5() {

	db("ALTER TABLE config ADD ecommerce_iyzipay_installment ENUM('1', '2', '3', '6', '9', '12') NOT NULL DEFAULT '1'");

}

function upgrade_to_2019_1_6() {

	db("ALTER TABLE config ADD ecommerce_iyzipay_threeds TINYINT UNSIGNED NOT NULL DEFAULT 0");

}

function upgrade_to_2019_2_3() {

	db("ALTER TABLE config ADD time_format ENUM('twelve_hours', 'twenty_four_hours') NOT NULL DEFAULT 'twelve_hours'");

}

//The Future lets you upload/select multiple images and use it in your product detail page both product and product group.
function upgrade_to_2020_1_1() {

	db("

	   CREATE TABLE products_images_xref

	   (

		   product INT UNSIGNED NOT NULL DEFAULT 0,

		   file_name VARCHAR(255) DEFAULT ''

	   )" . ENGINE);

	db("

	   CREATE TABLE product_groups_images_xref

	   (

		   product_group INT UNSIGNED NOT NULL DEFAULT 0,

		   file_name VARCHAR(255) DEFAULT ''

	   )" . ENGINE);

}

function upgrade_to_2020_1_5() {

	//this update contains some updates installment options for submit order,order checkout and view order pages to show instalment prices and amounts
	//payment installment is if there is installment and how many installments
	db("ALTER TABLE orders ADD payment_installment INT UNSIGNED NOT NULL DEFAULT 1");

	//and installment charge is increase of installments charges.
	db("ALTER TABLE orders ADD installment_charges INT UNSIGNED NOT NULL DEFAULT 0");

	//it is not out of stock message, it is for new out of stock products fallow method. if this is return to 1 from submit_order page than this product, displayed on out of stock page and welcome page
	db("ALTER TABLE products

	ADD out_of_stock INT UNSIGNED NOT NULL DEFAULT 0,

	ADD out_of_stock_timestamp INT UNSIGNED NOT NULL DEFAULT 0");

}

// who is online.
function upgrade_to_2020_1_6() {

	db("ALTER TABLE user ADD user_online_timestamp INT UNSIGNED NOT NULL DEFAULT 0");

}

//default product code
//this is product image code template for Add Product pages.
//after product image code loop developed and products support multiple image selection this example may help users to add faster products
function upgrade_to_2020_1_7() {

	db("ALTER TABLE config ADD product_image_code_template TEXT");

}

function upgrade_to_2020_2_3() {

	db("ALTER TABLE config MODIFY subscription_key VARCHAR(256)");

}

// Dashboard widget screen options for the welcome page
function upgrade_to_2021_1_3() {
	// Create dashboard table with main configuration
	db("
		CREATE TABLE dashboard (
			main_weather_location VARCHAR(255) DEFAULT 'london'
		)" . ENGINE
	);

	// Add visual configuration columns
	db("ALTER TABLE dashboard ADD bg_image VARCHAR(256) NOT NULL DEFAULT 'bg_metapolis'");
	db("ALTER TABLE dashboard ADD widget_themes ENUM('blur_one', 'blur_two', 'blur_three') NOT NULL DEFAULT 'blur_one'");
}

//The Future lets you widget activate/deactivate or order them.
function upgrade_to_2021_1_8() {

	//insert into dashboard order_widget default all widgets active and default order.
	db("ALTER TABLE dashboard ADD order_widgets VARCHAR(256) NOT NULL DEFAULT '1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19'");

}

// Yahoo weather api integration
function upgrade_to_2021_1_9() {
	db("ALTER TABLE dashboard ADD weather_app_id VARCHAR(256) DEFAULT ''");
	db("ALTER TABLE dashboard ADD weather_key VARCHAR(256) DEFAULT ''");
	db("ALTER TABLE dashboard ADD weather_secret VARCHAR(256) DEFAULT ''");
}

// Note Widget
function upgrade_to_2021_1_11() {
	db("ALTER TABLE dashboard ADD notes_widget_data TEXT");
	$query = "UPDATE dashboard
            SET
            notes_widget_data = 'PGgxPkNvbW1vbiBub3RlIGFyZWEgZm9yIHNpdGUgPHN0cm9uZyBzdHlsZT0iY29sb3I6IHJnYigxMDIsIDE4NSwgMTAyKTsiPmFkbWluaXN0cmF0b3JzPC9zdHJvbmc+PC9oMT48cD48YnI+PC9wPjxwPjxzdHJvbmc+YWRkPC9zdHJvbmc+IG9yIDxzdHJvbmc+ZWRpdDwvc3Ryb25nPiBub3RlcyBoZXJlLjwvcD48cD5vciBsaXN0cyBsaWtlOjwvcD48b2w+PGxpPkxpc3QgY29udGVudDwvbGk+PGxpPkFub3RoZXIgbGlzdCBjb250ZW50PC9saT48L29sPjx1bD48bGk+U3ViIGxpc3QgY29udGVudDwvbGk+PGxpPkFub3RoZXIgc3ViIGxpc3QgY29udGVudDwvbGk+PC91bD4='
        ";
	$result = mysqli_query(db::$con, $query) or output_error('Query failed.');
}

function upgrade_to_2021_1_12() {
	db("ALTER TABLE dashboard CHANGE widget_themes widget_themes ENUM('blur_one', 'blur_two','blur_three','classic')");
}

function upgrade_to_2021_1_14() {
	db("ALTER TABLE config ADD custom_css TEXT");
	$query = "UPDATE config
            SET
            custom_css = '/* Custom Stylesheet that can overwrite backend CSS files */'
        ";
	$result = mysqli_query(db::$con, $query) or output_error('Query failed.');
}

function upgrade_to_2021_3() {
	db("ALTER TABLE user ADD secret_key VARCHAR(100) DEFAULT ''");
	db("ALTER TABLE user ADD secret_key_iv VARBINARY(32) DEFAULT ''");
}

// Custom Applications allow updating website data with a REST API type method.
function upgrade_to_2021_4_1() {

	db("
  CREATE TABLE custom_apps
  (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY
  )" . ENGINE);

	db("ALTER TABLE custom_apps ADD create_user_id INT UNSIGNED NOT NULL DEFAULT 0");
	db("ALTER TABLE custom_apps ADD name VARCHAR(256) DEFAULT ''");
	db("ALTER TABLE custom_apps ADD type VARCHAR(100) DEFAULT ''");
	db("ALTER TABLE custom_apps ADD method ENUM('POST', 'GET')");
	db("ALTER TABLE custom_apps ADD api_key VARCHAR(100) DEFAULT ''");
	db("ALTER TABLE custom_apps ADD api_key_iv VARBINARY(32) DEFAULT ''");
	db("ALTER TABLE custom_apps ADD timestamp INT UNSIGNED NOT NULL DEFAULT 0");
}

//software gained pin pages to menu features with this upgrade.
function upgrade_to_2021_4_3() {
	db("ALTER TABLE user ADD selected_appmenu_items_array TEXT");
	$query = "UPDATE user
            SET
            selected_appmenu_items_array = 'default'
        ";
	$result = mysqli_query(db::$con, $query) or output_error('Query failed.');
}

//Notification system is included in the software. Site activities will now generate notifications.
function upgrade_to_2021_4_4() {

	db("
  CREATE TABLE notifications
  (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY
  )" . ENGINE);

	db("ALTER TABLE notifications ADD action VARCHAR(100) DEFAULT ''");
  	db("ALTER TABLE notifications ADD type VARCHAR(100) DEFAULT ''");
	db("ALTER TABLE notifications ADD title VARCHAR(256) DEFAULT ''");
	db("ALTER TABLE notifications ADD product_id VARCHAR(100) DEFAULT ''");
	db("ALTER TABLE notifications ADD form_id VARCHAR(100) DEFAULT ''");
	db("ALTER TABLE notifications ADD order_id VARCHAR(100) DEFAULT ''");
	db("ALTER TABLE notifications ADD order_total VARCHAR(100) DEFAULT ''");
	db("ALTER TABLE notifications ADD user VARCHAR(100) DEFAULT ''");
	db("ALTER TABLE notifications ADD readed INT UNSIGNED NOT NULL DEFAULT 0");
	db("ALTER TABLE notifications ADD timestamp INT UNSIGNED NOT NULL DEFAULT 0");
}

//Notifications gained link and comment tracking features with this upgrade.
function upgrade_to_2021_4_7() {
	db("ALTER TABLE notifications ADD comment_id VARCHAR(100) DEFAULT ''");
	db("ALTER TABLE notifications ADD send_to VARCHAR(500) DEFAULT ''");
}

// we add media support to rss fields.
function upgrade_to_2022_1() {
	db("ALTER TABLE form_fields CHANGE rss_field rss_field ENUM('category', 'title','description','media')");
}

// we add image connect to contact support.
// if textbox or textarea its save in image and use form data
// if its file upload input, use file id.
function upgrade_to_2022_1_1() {
	db("ALTER TABLE contacts ADD image TEXT");
	db("ALTER TABLE contacts ADD file_id INT UNSIGNED NOT NULL DEFAULT 0");
	db("ALTER TABLE form_fields CHANGE contact_field contact_field ENUM('','salutation','first_name','last_name','suffix','nickname','company','title','department','office_location','business_address_1','business_address_2','business_city','business_state','business_country','business_zip_code','business_phone','business_fax','home_address_1','home_address_2','home_city','home_state','home_country','home_zip_code','home_phone','home_fax','mobile_phone','email_address','website','lead_source','opt_in','description','affiliate_name','image')");
}

// google strutured data is manage by site managers
// default is disabled
function upgrade_to_2022_1_2() {
	db("ALTER TABLE config
	ADD strutured_data TINYINT UNSIGNED NOT NULL DEFAULT 0");
}

// we add option to control visual effects
// defaul enabled
function upgrade_to_2022_1_7() {
	db("ALTER TABLE config
	ADD advanced_visual_effects INT UNSIGNED NOT NULL DEFAULT 1");
}

//auto backup feature is initialize in this upgrade.
//cron job or with any request can create a simple backup or update last auto backup.
// last_software_auto_backup using for check last auto backup date, because auto backup can run only once a day for performance and security reasons.
function upgrade_to_2022_1_8() {
	db("ALTER TABLE config
	ADD last_software_auto_backup INT UNSIGNED NOT NULL DEFAULT 0");
}

//We used to store software subscription information with "subscription_key" but now it will be stored as "subscription_id". (10 digit a-z 1-9)
//In addition, the software license key will be stored with the "subscription_key". (16 digit 1-9)
function upgrade_to_2022_1_9() {
	$query = "UPDATE config SET subscription_id = subscription_key, subscription_key = ''";
	$result = mysqli_query(db::$con, $query) or output_error('Query failed.');

}

// We remove default values for these columns, because mysql new releases do not support default for TEXT.
// new upgrades do not contain default values but old upgraded sites may contain defaults.
function upgrade_to_2022_2() {
	db("ALTER TABLE config ALTER custom_css  DROP DEFAULT");
	db("ALTER TABLE dashboard ALTER notes_widget_data  DROP DEFAULT");
	db("ALTER TABLE user ALTER selected_appmenu_items_array  DROP DEFAULT");
}

// local_sale_history table log all local orders with barcode scanned sales.
// We record online sales and local sales separately.
// also we create a table for local sale items and connect them with local sale id
function upgrade_to_2022_2_1() {
	db(" CREATE TABLE local_sale_history ( id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY )" . ENGINE);
  	db("ALTER TABLE local_sale_history ADD sale_date INT UNSIGNED NOT NULL DEFAULT 0");
	db("CREATE TABLE local_sale_history_items (
		id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
		local_sale_id INT UNSIGNED NOT NULL DEFAULT 0, 
		product_id INT UNSIGNED NOT NULL DEFAULT 0,
		product_name varchar(100) NOT NULL DEFAULT '',
		quantity INT UNSIGNED NOT NULL DEFAULT 0,
		price INT UNSIGNED NOT NULL DEFAULT 0,
		tax INT UNSIGNED NOT NULL DEFAULT 0
	)" . ENGINE);
}

// a new menu and pinning menu has been created.
// pin menu items from the old build cannot fit in the new pin area. So we reset the menu and select the most functional links.
// Users will be able to pin links up to a certain number if they wish.
function upgrade_to_2022_2_2() {
	db("UPDATE user SET selected_appmenu_items_array = 'default' WHERE selected_appmenu_items_array != 'default'");
}

//enable parasut configuration.
// this is a e-invoice website used at Turkey.
//you can merge order and contact data at parasut.com with 2 excel file exported from [all orders] page.
function upgrade_to_2022_3_2() {
	db("ALTER TABLE config ADD parasut_tc_in_field ENUM('do not use', 'custom_field_1', 'custom_field_2') NOT NULL DEFAULT 'do not use'");
	db("ALTER TABLE config ADD enable_parasut INT UNSIGNED NOT NULL DEFAULT 0");
	db("ALTER TABLE orders ADD parasut_exported INT UNSIGNED NOT NULL DEFAULT 0");
}

function upgrade_to_2023_1() {
	db("ALTER TABLE config ADD enable_iyzipay_protected_currency INT UNSIGNED NOT NULL DEFAULT 0");
	db("ALTER TABLE config ADD iyzipay_protected_currency_code VARCHAR(255) NOT NULL DEFAULT ''");

}

function upgrade_to_2023_2_1() {
	db("ALTER TABLE comments ADD rating TINYINT UNSIGNED NOT NULL DEFAULT 0");
	db("ALTER TABLE page ADD comments_rating TINYINT UNSIGNED NOT NULL DEFAULT 0");
}
