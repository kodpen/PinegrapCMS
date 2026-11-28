<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// What every marketplace connector has to provide, and what they all share.
//
// The shape is deliberately not an interface or a class hierarchy: this
// codebase is functions, and a connector is a table of function names plus the
// functions themselves. mp_connector('n11') answers that table, and the caller
// asks whether the entry it wants exists before calling it. A connector that
// cannot do something simply does not name it, which is how a provider can be
// added that pushes stock but has no order feed yet.
//
// The contract, all optional except test_credentials():
//
//   test_credentials($account)            can we talk to them at all; may
//                                         answer data['choices'], the options
//                                         of the provider's remote selects
//   push_stock_price($account, $rows)     one batch out, answers a task handle
//   read_task($account, $task_id)         the result of an earlier batch
//   pull_orders($account, $since)         their orders, ours to import
//   order_stage($remote_status)           their status as open, shipped,
//                                         delivered or cancelled
//   approve_lines($account, $line_ids, $package)
//                                         accept a package's lines (Picking);
//                                         $package is the imported package
//   webhook_packages($account, $decoded)  the packages of one delivery their
//                                         webhook posted, already parsed
//   register_webhook($account, $url, $key)
//                                         ask them to post orders to $url with
//                                         $key; answers data['webhook_id']
//   remove_webhook($account)              remove the one in the credentials
//   ship_package($account, $package, $tracking_url)
//                                         tell them the seller shipped it with
//                                         their own carrier; $package['parcel']
//                                         is the parcel's number, carrier (key),
//                                         carrier_name and url; may answer
//                                         data['remote_status'], their word
//                                         for shipped
//   request_label($account, $number, $boxes)
//                                         ask them for the shipping label of the
//                                         parcel with that cargo number, one per
//                                         box, made by their own carrier
//   read_label($account, $number)         that label: data['label'] (one file,
//                                         '' while it is not made yet) and
//                                         data['format'] (pg_label_formats())
//   label_carrier($name)                  whether they label a parcel of the
//                                         carrier a package names (a bool)
//   update_order($account, $lines, $to)   move their order along
//   category_key($account)                the key its categories are cached
//                                         under, when it is not the provider
//                                         (a tree a site); at most 30
//                                         characters
//   category_tree($account)               their category tree, flattened
//   category_attributes($account, $id)    what a category demands, with values
//   create_listings($account, $items)     open listings, answers a task handle,
//                                         or with task_id '' the verdicts at
//                                         once in data['results']
//   listing_status($account, $barcodes)   where those listings stand (approved,
//                                         pending, rejected), by barcode
//   buybox($account, $barcodes)           where the store stands in each
//                                         listing's buybox, by barcode: rank,
//                                         the buybox price, other sellers
//   remote_products($account, $page)     one page of the products the store
//                                         sells there, for matching; answers
//                                         products, page, pages, total, last
//
// Two rules hold for all of them, and they are the correction to the accounting
// integration that is still in the tree:
//
//   Nothing here is called from a web request. Everything goes through
//   marketplace_sync_queue and api_sync_job.php. Saving a product must never
//   wait on somebody else's server.
//
//   Nothing here writes to the catalogue. A connector reports; the caller
//   decides what that means for a row in `products`.

if (!defined('PG_INIT_LOADED')) {

	exit;

}

require_once(dirname(dirname(__FILE__)) . '/http.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/marketplace_pricing.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/ebay.php');

// The providers this installation knows how to talk to.
//
// Order is display order. A provider appears here the moment its file exists,
// whether or not the shop has an account with it, because the account screen
// has to offer it before there is one.
//
// 'shipment_template' marks a provider whose listings name a cargo agreement
// (the account's shipment_template column); the account screen asks for one
// only there.
//
// 'key' says what the marketplace knows a product by: 'barcode' for one that
// identifies every listing, stock line and order line by barcode (Trendyol),
// absent for one that uses the seller's own code (n11, where the code is the
// product's name here). It decides what a listing sends as the product's key
// and so what the mapping row stores as remote_code.
//
// A field is a text box unless it says 'type' => 'select'; a select carries
// its 'options' (stored value => label) and the 'default' an unknown answer
// falls back to, and is never empty, so it is never "required". Labels and
// 'help' are English source text, translated where the screen draws them.
//
// A select that says 'remote' => true has no options of its own: they are
// what the marketplace account holds (eBay's business policies), read by
// test_credentials() and kept in the credentials under 'choices'
// (mp_account_choices_merge()). It may be empty until then, its options
// are names the seller gave and are never translated, and the account
// screen leaves it out of "add account".
function mp_providers() {

	return array(
		'n11' => array(
			'name'   => 'n11',
			'file'   => 'n11.php',
			'shipment_template' => true,
			'fields' => array(
				'app_key'    => array('label' => 'API key',    'secret' => false),
				'app_secret' => array('label' => 'API secret', 'secret' => true)
			)
		),
		'trendyol' => array(
			'name'   => 'Trendyol',
			'file'   => 'trendyol.php',
			'key'    => 'barcode',
			// A registered webhook belongs to the seller and the environment
			// these fields name (mp_webhook_owner_changed()).
			'webhook_owner_fields' => array('seller_id', 'environment'),
			'help'   => 'All three are in your Trendyol seller panel, under Account Information › Integration Information. The test environment has credentials of its own and only answers servers whose IP address Trendyol has authorised.',
			'fields' => array(
				'seller_id'   => array('label' => 'Seller ID',  'secret' => false),
				'api_key'     => array('label' => 'API key',    'secret' => false),
				'api_secret'  => array('label' => 'API secret', 'secret' => true),
				'environment' => array(
					'label'   => 'Environment',
					'type'    => 'select',
					'options' => array('production' => 'Live', 'stage' => 'Test environment'),
					'default' => 'production'
				),
				// Who carries the parcels: Trendyol's contracted carrier, whose
				// number arrives with the package, or the shop's own, whose
				// tracking link is sent to Trendyol (mp_account_ships_itself()).
				'delivery' => array(
					'label'   => 'Shipping carrier',
					'type'    => 'select',
					'options' => array(
						'trendyol' => 'Trendyol\'s contracted carrier (tracking number comes from Trendyol)',
						'own'      => 'My own carrier (the tracking link recorded here is sent to Trendyol)'
					),
					'default' => 'trendyol'
				)
			)
		),
		// The seller signs in on Connections (the ebay and ebay_sandbox
		// providers of connected accounts); an account holds no key of its
		// own. One account sells on one eBay site.
		'ebay' => array(
			'name'   => 'eBay',
			'file'   => 'ebay.php',
			// A seller on eBay ships every order with a carrier of its own
			// (mp_account_ships_itself()); the statuses of an order not yet
			// shipped, which mp_sync_ship_pending() reports a parcel for.
			'ships_itself'  => true,
			'open_statuses' => array('NOT_STARTED', 'IN_PROGRESS'),
			'help'   => 'Connect your eBay seller account on Connections first. One account sells on one eBay site: add an account for each further site. Save, then test the connection to read your inventory locations and business policies.',
			'fields' => array(
				'environment' => array(
					'label'   => 'Environment',
					'type'    => 'select',
					'options' => array('production' => 'Live', 'sandbox' => 'Test environment'),
					'default' => 'production'
				),
				'site' => array(
					'label'   => 'eBay site',
					'type'    => 'select',
					'options' => pg_ebay_site_options(),
					'default' => 'EBAY_US'
				),
				'location'           => array('label' => 'Inventory location', 'type' => 'select', 'remote' => true, 'options' => array()),
				'fulfillment_policy' => array('label' => 'Shipping policy',    'type' => 'select', 'remote' => true, 'options' => array()),
				'payment_policy'     => array('label' => 'Payment policy',     'type' => 'select', 'remote' => true, 'options' => array()),
				'return_policy'      => array('label' => 'Return policy',      'type' => 'select', 'remote' => true, 'options' => array())
			)
		),
		// The seller signs in on Connections (the etsy provider of connected
		// accounts); an account is one shop, read from the sign-in, and holds
		// no key of its own. Etsy gives an application made in Turkey no
		// buyer address and no shipment call: such a shop still lists and
		// takes its orders, without the address.
		'etsy' => array(
			'name'   => 'Etsy',
			'file'   => 'etsy.php',
			'help'   => 'Connect your Etsy shop on Connections first. Save, then test the connection to read your shop, its shipping and processing profiles. Etsy gives an app made in Turkey no buyer addresses and no shipment reports: such a shop gets its listings, stock, prices and orders, without the address.',
			// No carrier of Etsy's own: every parcel is the shop's to report.
			'ships_itself'  => true,
			'open_statuses' => array('paid', 'partially refunded'),
			'fields' => array(
				'shop'             => array('label' => 'Etsy shop',          'type' => 'select', 'remote' => true, 'options' => array()),
				'shipping_profile' => array('label' => 'Shipping profile',   'type' => 'select', 'remote' => true, 'options' => array()),
				'readiness_state'  => array('label' => 'Processing profile', 'type' => 'select', 'remote' => true, 'options' => array()),
				'who_made'         => array('label' => 'Who made it',        'type' => 'select', 'remote' => true, 'options' => array()),
				'when_made'        => array('label' => 'When it was made',   'type' => 'select', 'remote' => true, 'options' => array())
			)
		),
		// Managed: no fields of its own. Its page of Connections writes and
		// deletes its account row (includes/fn/merchant_center_push.php); this
		// screen lists it and never offers it under "add account".
		'merchant_center' => array(
			'name'    => 'Google Merchant Center',
			'file'    => 'merchant_center.php',
			'managed' => true,
			'fields'  => array()
		)
	);

}

// Whether another screen owns the provider's account rows ('managed').
function mp_provider_is_managed($provider) {

	$providers = mp_providers();

	return !empty($providers[$provider]['managed']);

}

// The credential fields of one provider, read out of a submitted form.
//
// A text field is trimmed and may be empty (the caller decides what empty
// means: required on create, "keep the stored one" on update). A select only
// ever answers one of its own options, so a value that came from anywhere but
// the form's own list is stored as the default instead. A remote select's
// options are the account's $choices (field => value => name); a value not
// among them is stored empty.
function mp_provider_credentials($provider, $input, $choices = array()) {

	$providers = mp_providers();

	if (!isset($providers[$provider]['fields'])) {

		return array();

	}

	$values = array();

	foreach ($providers[$provider]['fields'] as $field => $meta) {

		$value = (isset($input[$field]) && is_scalar($input[$field])) ? trim((string)$input[$field]) : '';

		if (mp_provider_field_is_remote($meta)) {

			if (!isset($choices[$field]) || !is_array($choices[$field]) || !isset($choices[$field][$value])) {

				$value = '';

			}

		} elseif (mp_provider_field_is_select($meta)) {

			$options = isset($meta['options']) ? (array)$meta['options'] : array();

			if (!isset($options[$value])) {

				$value = isset($meta['default']) ? (string)$meta['default'] : (string)key($options);

			}

		}

		$values[$field] = $value;

	}

	return $values;

}

function mp_provider_field_is_select($meta) {

	return isset($meta['type']) && $meta['type'] === 'select';

}

function mp_provider_field_is_remote($meta) {

	return mp_provider_field_is_select($meta) && !empty($meta['remote']);

}

// The options test_credentials() read for remote selects, made safe to keep
// in the credentials: only the provider's remote fields, at most 100 options
// each, value and name strings of at most 100 characters. Pure.
function mp_choices_clean($provider, $choices) {

	$providers = mp_providers();
	$clean = array();

	if (!isset($providers[$provider]['fields']) || !is_array($choices)) {

		return $clean;

	}

	foreach ($providers[$provider]['fields'] as $field => $meta) {

		if (!mp_provider_field_is_remote($meta) || !isset($choices[$field]) || !is_array($choices[$field])) {

			continue;

		}

		$clean[$field] = array();

		foreach ($choices[$field] as $value => $name) {

			$value = (string)$value;

			if (($value === '') || (mb_strlen($value) > 100) || !is_scalar($name) || (count($clean[$field]) >= 100)) {

				continue;

			}

			$name = trim((string)$name);
			$clean[$field][$value] = mb_substr(($name !== '') ? $name : $value, 0, 100);

		}

	}

	return $clean;

}

// The credentials with freshly read choices: kept under 'choices', a chosen
// value the account no longer holds cleared, and a field with nothing chosen
// and one choice only given that one. Pure.
function mp_account_choices_merge($provider, $credentials, $choices) {

	$credentials = (array)$credentials;
	$choices = mp_choices_clean($provider, $choices);

	foreach ($choices as $field => $options) {

		$chosen = isset($credentials[$field]) ? (string)$credentials[$field] : '';

		if (($chosen !== '') && !isset($options[$chosen])) {

			$chosen = '';

		}

		if (($chosen === '') && (count($options) === 1)) {

			$chosen = (string)key($options);

		}

		$credentials[$field] = $chosen;

	}

	$credentials['choices'] = $choices;

	return $credentials;

}

function mp_provider_keys_by_barcode($provider) {

	$providers = mp_providers();

	return isset($providers[$provider]['key']) && $providers[$provider]['key'] === 'barcode';

}

// Does this account ship with a carrier of its own, rather than the one the
// marketplace contracts? Always, for a provider that has no carrier of its
// own ('ships_itself', eBay); else read from the account's 'delivery'
// choice, and an account saved before the choice existed, or one that never
// made it, does not.
function mp_account_ships_itself($account) {

	if (isset($account['provider']) && mp_provider_ships_itself((string)$account['provider'])) {

		return true;

	}

	return isset($account['credentials']['delivery']) && $account['credentials']['delivery'] === 'own';

}

function mp_provider_ships_itself($provider) {

	$providers = mp_providers();

	return !empty($providers[$provider]['ships_itself']);

}

// The remote statuses of a package not shipped yet that the shop may report
// a parcel for (mp_sync_ship_pending()): the provider's 'open_statuses', else
// Trendyol's words, the provider the report was first built for.
function mp_ship_open_statuses($provider) {

	$providers = mp_providers();

	return (isset($providers[$provider]['open_statuses']) && is_array($providers[$provider]['open_statuses']))
		? array_values($providers[$provider]['open_statuses']) : array('Created', 'Picking', 'Invoiced');

}

// The credentials as saved, with the moment the account began shipping itself.
//
// $before are the stored credentials (an empty array for a new account),
// $after the ones about to be written. The moment is kept while the account
// ships itself, set to $now when it starts to, and dropped when it stops.
// Only packages imported from then on are reported with the shop's link
// (mp_sync_ship_pending()): an older import carries the marketplace's carrier
// in its address's method code, and a parcel recorded on it later would take
// that carrier and its tracking page with the shop's number.
function mp_account_delivery_stamp($before, $after, $now) {

	$before = (array) $before;
	$after = (array) $after;

	if (!mp_account_ships_itself(array('credentials' => $after))) {

		unset($after['delivery_since']);

		return $after;

	}

	$since = (mp_account_ships_itself(array('credentials' => $before)) && isset($before['delivery_since']))
		? (int) $before['delivery_since'] : 0;

	$after['delivery_since'] = ($since > 0) ? $since : (int) $now;

	return $after;

}

function mp_provider_is_valid($provider) {

	$providers = mp_providers();

	return isset($providers[$provider]);

}

// The provider's own name, for a screen or a message. Falls back to the key,
// so a row left behind by a provider that has since been removed still reads.
function mp_provider_name($provider) {

	$providers = mp_providers();

	return isset($providers[$provider]['name']) ? $providers[$provider]['name'] : (string)$provider;

}

// Load a provider's functions and answer the table of what it can do.
//
// Answers an empty array for a provider that is not installed, so every caller
// can treat "not installed" and "cannot do this" the same way.
function mp_connector($provider) {

	static $loaded = array();

	if (isset($loaded[$provider])) {

		return $loaded[$provider];

	}

	$providers = mp_providers();

	if (!isset($providers[$provider])) {

		return array();

	}

	$file = dirname(__FILE__) . '/' . $providers[$provider]['file'];

	if (!is_file($file)) {

		return array();

	}

	require_once($file);

	$builder = 'mp_' . $provider . '_connector';

	$loaded[$provider] = function_exists($builder) ? call_user_func($builder) : array();

	return $loaded[$provider];

}

// Can this account do that, and what is the function called?
function mp_can($provider, $capability) {

	$connector = mp_connector($provider);

	return (isset($connector[$capability]) && function_exists($connector[$capability]));

}

// The key an account's categories and their attributes are cached under
// (marketplace_categories.provider, marketplace_category_attributes.provider):
// the provider, or the connector's own key where the marketplace keeps a tree
// a site (eBay: "ebay:EBAY_DE").
function mp_category_key($account) {

	$provider = (string) $account['provider'];

	if (mp_can($provider, 'category_key')) {

		$key = (string) mp_call($provider, 'category_key', array($account));

		if (($key !== '') && (strlen($key) <= 30)) {

			return $key;

		}

	}

	return $provider;

}

function mp_call($provider, $capability, $arguments = array()) {

	$connector = mp_connector($provider);

	if (!mp_can($provider, $capability)) {

		return mp_error(lang(array(
			'string' => '{var:1} cannot do this.',
			'vars'   => array(mp_provider_name($provider))
		)));

	}

	return call_user_func_array($connector[$capability], $arguments);

}

/* -------------------------------------------------------------------- columns */

// Has the database the column for a listing's commission rate
// (marketplace_product_map.commission, 2026.11.0, 11.0.37)? Asked before any
// statement names it: the files land before the upgrade step runs.
function mp_commission_ready() {

	return function_exists('pg_schema_has') && pg_schema_has('marketplace_product_map', 'commission');

}

// Has the database the column for when an imported package's invoice link was
// sent (marketplace_order_map.invoice_sent, 2026.11.0, 11.0.37)? Asked the
// same way.
function mp_invoice_sent_ready() {

	return function_exists('pg_schema_has') && pg_schema_has('marketplace_order_map', 'invoice_sent');

}

/* ------------------------------------------------------------------ accounts */

// One account row with its credentials already decoded.
//
// The credentials are a JSON object encrypted as one blob rather than a set of
// named columns, so a provider that wants a different set of fields needs no
// schema change. The column holds "<ciphertext>:<iv>", both base64, which is
// the pair encrypt_string_with_iv() answers.
function mp_account($account_id) {

	$row = db_item("SELECT * FROM marketplace_accounts WHERE id = '" . e((int)$account_id) . "'");

	if (!$row) {

		return null;

	}

	$row['credentials'] = mp_credentials_decode($row['credentials']);

	return $row;

}

function mp_accounts($provider = '', $status = '') {

	$where = array();

	if ($provider !== '') {

		$where[] = "provider = '" . e($provider) . "'";

	}

	if ($status !== '') {

		$where[] = "status = '" . e($status) . "'";

	}

	$rows = (array) db_items("SELECT * FROM marketplace_accounts"
		. ($where ? " WHERE " . implode(' AND ', $where) : "")
		. " ORDER BY name ASC, id ASC");

	foreach ($rows as $index => $row) {

		$rows[$index]['credentials'] = mp_credentials_decode($row['credentials']);

	}

	return $rows;

}

function mp_credentials_encode($credentials) {

	list($cipher, $iv) = encrypt_string_with_iv(json_encode((array)$credentials));

	return $cipher . ':' . $iv;

}

function mp_credentials_decode($stored) {

	$stored = (string) $stored;

	if ($stored === '' || strpos($stored, ':') === false) {

		return array();

	}

	list($cipher, $iv) = explode(':', $stored, 2);

	$json = decode_ssl_keys($cipher, $iv);

	$values = ($json === '') ? null : json_decode($json, true);

	return is_array($values) ? $values : array();

}

// A credential for a log line or a screen: enough to recognise, not enough to use.
function mp_credential_hint($value) {

	$value = (string) $value;

	if (strlen($value) <= 4) {

		return str_repeat('*', strlen($value));

	}

	return str_repeat('*', 4) . substr($value, -4);

}

// Record how the last conversation with this account went.
//
// Written after every batch, because the account screen's only honest answer to
// "is this working" is when it last worked and what it said when it did not.
function mp_account_outcome($account_id, $ok, $error = '') {

	$account_id = (int) $account_id;

	if (!$account_id) {

		return;

	}

	if ($ok) {

		db("UPDATE marketplace_accounts
			SET last_success = UNIX_TIMESTAMP(), last_error = ''
			WHERE id = '" . e($account_id) . "'");

		return;

	}

	db("UPDATE marketplace_accounts
		SET last_failure = UNIX_TIMESTAMP(), last_error = '" . e(mb_substr((string)$error, 0, 500)) . "'
		WHERE id = '" . e($account_id) . "'");

}

/* -------------------------------------------------------------------- answers */

// The two shapes every connector function answers with.
//
// One shape, so the caller never has to know which provider it was talking to
// in order to read the result. 'retry' is the connector's judgement rather than
// the caller's: only the connector knows that this provider says 429 in a body
// with a 200 on it.
function mp_ok($data = array()) {

	return array('ok' => true, 'error' => '', 'retry' => false, 'data' => $data);

}

function mp_error($message, $retry = false, $data = array()) {

	return array('ok' => false, 'error' => (string)$message, 'retry' => (bool)$retry, 'data' => $data);

}

/* -------------------------------------------------------------------- returns */

// The ids of a return's units waiting for the seller, which an approval
// sends. A return is in the shape a connector's claims read answers with;
// the connector decides which of its statuses is waiting.
function mp_claim_waiting($claim) {

	$ids = array();

	foreach ((isset($claim['units']) && is_array($claim['units'])) ? $claim['units'] : array() as $unit) {

		if (!empty($unit['waiting']) && isset($unit['id'])) {

			$ids[] = (string)$unit['id'];

		}

	}

	return $ids;

}

/* ---------------------------------------------------------------- order feeds */

// A moment of an order feed as the feed counts it, in whole milliseconds, or 0
// for an entry without one. A number far too large to be seconds is already
// milliseconds; a date string is read as seconds. Always rounded down: the next
// request starts at this moment, and a start rounded up would pass over an
// entry modified later in the same millisecond or second.
function mp_feed_moment($value) {

	if (is_numeric($value)) {

		$value = (float) $value;

		return (int) (($value > 100000000000) ? floor($value) : floor($value) * 1000);

	}

	$parsed = is_string($value) ? strtotime($value) : false;

	return ($parsed === false) ? 0 : ((int) $parsed) * 1000;

}

// Where an order feed is asked next after a full page, as array(start, page).
//
// The feeds are asked inside a fixed window in ascending order of modification
// time, a page of entries at a time. Asking for page 1, 2, ... of the same
// window loses a package whenever one that was already read changes while the
// pages are walked: it leaves its place, every later entry moves up one, and
// the entry that moves onto the page already read is never read at all. So the
// window starts again just before the moment of the last entry read, from page
// 0. One millisecond before, so that the entries of that moment come back
// whether the feed reads startDate as inclusive or exclusive; the caller skips
// the ones it has (mp_feed_first_time()).
//
// Only a page whose entries all share one moment cannot move the start, and
// is followed by the next page of the same window. $first and $last are the
// moments of the page's first and last entries (mp_feed_moment()).
function mp_feed_next_page($start, $page, $first, $last) {

	$from = $last - 1;

	if (($last > $first) && ($from > $start)) {

		return array($from, 0);

	}

	return array($start, $page + 1);

}

// Whether an entry is new to this pass: not read already with the same
// modification moment. A package that changed while the pages were walked comes
// back with a later moment and is handed over again; the pass keeps its newest
// copy (mp_order_cancellations_first()).
function mp_feed_first_time(&$seen, $package_id, $moment) {

	$key = $package_id . '|' . (int) $moment;

	if (isset($seen[$key])) {

		return false;

	}

	$seen[$key] = true;

	return true;

}

/* ---------------------------------------------------------------------- queue */

// Ask for a product to be sent, some time soon.
//
// Cheap on purpose: this is called from the inventory endpoint and from the
// product screen, inside a request somebody is waiting on. It writes one row
// per mapped account and returns.
//
// A product already waiting is not queued twice. The queue carries an
// instruction ("send this product's stock and price"), not an event, so two
// changes a second apart are one send - and a marketplace that is rate limited
// per minute must not be told the same thing forty times because somebody held
// down the arrow on a quantity field.
function mp_enqueue($product_id, $operation = 'stock_price', $priority = 5) {

	$product_id = (int) $product_id;

	if (!$product_id || !defined('DB_CONNECTED')) {

		return 0;

	}

	$maps = (array) db_items("SELECT marketplace_product_map.account_id, marketplace_accounts.provider
		FROM marketplace_product_map
		INNER JOIN marketplace_accounts ON marketplace_accounts.id = marketplace_product_map.account_id
		WHERE (marketplace_product_map.product_id = '" . e($product_id) . "')
		  AND (marketplace_product_map.status = 'active')
		  AND (marketplace_accounts.status = 'active')");

	if (!$maps) {

		return 0;

	}

	$written = 0;

	foreach ($maps as $map) {

		$account_id = (int) $map['account_id'];

		// A provider whose connector cannot send stock and price yet gets no
		// row: the job would only fail it six times and leave it on the
		// "did not go through" list for a reason nobody can act on.
		if ($operation === 'stock_price' && !mp_can((string)$map['provider'], 'push_stock_price')) {

			continue;

		}

		// A row leased by a pass that is sending it now does not count: that
		// pass read the product before this change, so the change needs a row
		// of its own or it is marked sent with the old stock and never sent.
		$waiting = 0;

		foreach ((array) db_items("SELECT id, status, remote_task_id FROM marketplace_sync_queue
			WHERE (account_id = '" . e($account_id) . "')
			  AND (product_id = '" . e($product_id) . "')
			  AND (operation = '" . e($operation) . "')
			  AND (status = 'waiting')") as $pending) {

			if (mp_enqueue_absorbs($pending)) {

				$waiting = (int) $pending['id'];

				break;

			}

		}

		if ($waiting) {

			// Already asked for. Pull the run time forward if this caller is in
			// more of a hurry than the one that queued it - that is what the
			// "send now" button on the product screen does.
			if ((int)$priority < 5) {

				db("UPDATE marketplace_sync_queue
					SET priority = '" . e((int)$priority) . "', run_after = 0, updated_timestamp = UNIX_TIMESTAMP()
					WHERE id = '" . e((int)$waiting) . "'");

			}

			continue;

		}

		db("INSERT INTO marketplace_sync_queue
			(account_id, product_id, operation, priority, status, run_after, created_timestamp, updated_timestamp)
			VALUES (
				'" . e($account_id) . "',
				'" . e($product_id) . "',
				'" . e($operation) . "',
				'" . e((int)$priority) . "',
				'waiting',
				0,
				UNIX_TIMESTAMP(),
				UNIX_TIMESTAMP())");

		$written++;

	}

	return $written;

}

// Whether a queue row can carry one more change of its product: a waiting
// row no pass has leased yet. A leased row carries its pass's token in
// remote_task_id (mp_sync_lease_token()) until it is sent or let go.
function mp_enqueue_absorbs($row) {

	return is_array($row)
		&& isset($row['status']) && ((string)$row['status'] === 'waiting')
		&& isset($row['remote_task_id']) && ((string)$row['remote_task_id'] === '');

}

// How long to wait before trying a failed row again.
//
// Minutes, doubling: 2, 4, 8, 16, 32, 64. Six attempts covers a couple of hours
// of somebody else's outage, which is longer than most, and stops well short of
// a queue that retries a genuinely broken mapping for a week.
function mp_backoff_seconds($attempts) {

	$attempts = max(1, min(6, (int)$attempts));

	return 60 * (int) pow(2, $attempts);

}

function mp_max_attempts() {

	return 6;

}

/* ----------------------------------------------------------------- the batch */

// What a connector is handed for one product.
//
// Assembled here rather than in each connector so that every provider is told
// the same thing about a product, and so the rules for what a price and a
// quantity mean live in one place:
//
//   price is what the shop's checkout charges for one unit, tax included
//   (mp_product_price()), moved by the account's price rules when it has
//   any (mp_pricing_product_price(), never under the profit floor): minor
//   units in the shop, and decimal where marketplaces want it. Both live in
//   one place because getting either wrong - by a factor of a hundred, or by
//   the tax the shop adds at the checkout and a marketplace counts inside its
//   sale price - is the kind of mistake that sells stock at cost. The drivers
//   send it as both the sale and the list price: a rule's price carries no
//   struck-through price. site_price keeps the checkout's figure,
//   price_rule says whether a rule set price, price_own whether it is the
//   price typed on the listing, which wins over the rules, and price_robot
//   whether the repricer's robot set it (marketplace_repricer.php).
//
//   quantity is zero for a product that does not track stock. A marketplace has
//   no concept of "not tracked", and the alternative - sending nothing - leaves
//   the last number they were told standing for ever.
function mp_product_row($account, $map) {

	$product = db_item("SELECT id, name, price, inventory, inventory_quantity, out_of_stock, enabled,
			title, taxable, tax_rate, brand
		FROM products WHERE id = '" . e((int)$map['product_id']) . "'");

	if (!$product) {

		return null;

	}

	$tracked = !empty($product['inventory']);

	$quantity = $tracked ? max(0, (int)$product['inventory_quantity']) : 0;

	// A product that is switched off in the shop, or flagged out of stock, is
	// offered as zero rather than left alone. Anything else keeps selling it
	// over there after it stopped selling here.
	if (empty($product['enabled']) || !empty($product['out_of_stock'])) {

		$quantity = 0;

	}

	// The mapping row is whatever the caller had to hand, so both of its columns
	// are read defensively: the queue's own select carries them, a caller
	// assembling a row by product id may not.
	$remote_code = isset($map['remote_code']) ? trim((string)$map['remote_code']) : '';

	$site_price = mp_product_price($product);

	$vat_rate = mp_product_vat_rate($product);

	$channel = mp_pricing_product_price($account, $product, $site_price,
		($vat_rate === null) ? mp_pricing_tax_off_rate($product) : $vat_rate);

	$price = $channel['price'];

	return array(
		'product_id'  => (int)$product['id'],
		'remote_code' => ($remote_code !== '') ? $remote_code : $product['name'],
		'remote_id'   => isset($map['remote_id']) ? (string)$map['remote_id'] : '',
		'name'        => $product['name'],
		'title'       => $product['title'],
		'price'       => $price,
		'price_major' => mp_price_major($price),
		'site_price'  => $site_price,
		'price_rule'  => $channel['rule'],
		'price_own'   => !empty($channel['own']),
		'price_robot' => !empty($channel['robot']),
		'quantity'    => $quantity,
		'tracked'     => $tracked,
		'taxable'     => !empty($product['taxable']),
		'tax_rate'    => ($product['tax_rate'] === null) ? null : (float)$product['tax_rate'],
		'vat_rate'    => $vat_rate
	);

}

// The price a marketplace is told, in minor units: what the shop's checkout
// charges for one unit of the product.
//
// The shop keeps prices without tax and adds it at the checkout; a
// marketplace's sale price is the amount the buyer pays, tax inside (Trendyol
// and n11 both work back the VAT from it). Sending the stored price as it is
// sold the product cheaper over there by the tax, on every price send. So
// the tax the checkout would add goes on here, by the checkout's own formula
// (get_tax_inclusive_price(), the one update_order_item_taxes() uses), with
// the shop's own country standing in for the buyer's address: a marketplace
// price is one number for every buyer. Nothing is added when the checkout
// would add nothing - tax off, product not taxable, country in no zone.
//
// The zone is the country's (get_default_tax_rate()), not the ERP store's
// zone the till uses (local_sale_line_rate()): it is the same source the
// declared VAT rate is read from (mp_product_vat_rate()), so the price and
// the rate a listing states always agree.
//
// Takes any row that carries price, taxable and tax_rate from products: the
// queue's product, a listing's product, the mapping screen's row.
function mp_product_price($product) {

	$tax = mp_shop_tax_state();

	return get_tax_inclusive_price(
		isset($product['price']) ? $product['price'] : 0,
		isset($product['taxable']) ? $product['taxable'] : 0,
		isset($product['tax_rate']) ? $product['tax_rate'] : null,
		$tax['zone_rate'],
		$tax['enabled']
	);

}

// The VAT rate inside mp_product_price()'s figure, from the same inputs, or
// null when the shop does not tax (see mp_applied_vat_rate()).
function mp_product_vat_rate($product) {

	$tax = mp_shop_tax_state();

	return mp_applied_vat_rate(
		isset($product['taxable']) ? $product['taxable'] : 0,
		isset($product['tax_rate']) ? $product['tax_rate'] : null,
		$tax['zone_rate'],
		$tax['enabled']
	);

}

// Whether the shop charges tax and its country's zone rate (false in no
// zone, or when tax is off): the inputs both the price and the VAT rate a
// marketplace is told are built from.
function mp_shop_tax_state() {

	$enabled = defined('ECOMMERCE_TAX') && ECOMMERCE_TAX == true;

	return array(
		'enabled'   => $enabled,
		'zone_rate' => ($enabled && function_exists('get_default_tax_rate')) ? get_default_tax_rate() : false
	);

}

// The rate get_tax_inclusive_price() adds on top of the stored price, for the
// same arguments: the rate a marketplace has to be told, because Trendyol and
// n11 work the VAT back out of the sale price.
//
// null when tax is off: the shop then charges the stored price as it is, so
// that price is final with any VAT already inside, and the drivers declare
// the product's own or the zone's rate as they always did. 0 when the price
// carries no tax - product not taxable, or the country in no zone, whatever
// rate the product has of its own. Otherwise get_effective_tax_rate().
function mp_applied_vat_rate($taxable, $product_rate, $zone_rate, $tax_enabled) {

	if (!$tax_enabled) {

		return null;

	}

	if (!((int)$taxable) || $zone_rate === false || $zone_rate === null) {

		return 0.0;

	}

	return (float)get_effective_tax_rate($product_rate, $zone_rate);

}

// Minor units as the number a marketplace wants to see.
//
// Almost every currency divides by a hundred, and the exceptions are the reason
// this is not written inline: a shop in Japan has no decimal places and a shop
// in Kuwait has three, so dividing by a hundred there is out by a factor of a
// hundred or of ten. The table belongs to the API's meta resource, which is not
// loaded in a cron; when it is not, two is assumed, which is right for every
// currency the marketplaces here settle in.
function mp_price_major($minor) {

	$decimals = function_exists('api_currency_minor_units')
		? (int) api_currency_minor_units(defined('BASE_CURRENCY_CODE') ? BASE_CURRENCY_CODE : 'TRY')
		: 2;

	if ($decimals <= 0) {

		return (float) (int) $minor;

	}

	return round(((int)$minor) / pow(10, $decimals), $decimals);

}

/* ------------------------------------------------------------------- logging */

// A line in the sync log.
//
// Deliberately the request log's poorer cousin: no bodies, no credentials, one
// row per batch rather than per product. The panel needs to answer "did it go
// and what came back", and a table that also holds the payloads is a table
// nobody prunes.
function mp_log($account_id, $operation, $ok, $message, $count = 0) {

	log_activity(lang(array(
		'string' => 'Marketplace sync ({var:1}): {var:2} product(s), {var:3}.',
		'vars'   => array($operation, (int)$count, $ok ? lang('sent') : mb_substr((string)$message, 0, 200))
	)), 'system');

	mp_account_outcome($account_id, $ok, $message);

}
