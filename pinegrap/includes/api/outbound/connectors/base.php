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
//   test_credentials($account)            can we talk to them at all
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
//   update_order($account, $lines, $to)   move their order along
//   category_tree($account)               their category tree, flattened
//   category_attributes($account, $id)    what a category demands, with values
//   create_listings($account, $items)     open listings, answers a task handle
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
				)
			)
		)
	);

}

// The credential fields of one provider, read out of a submitted form.
//
// A text field is trimmed and may be empty (the caller decides what empty
// means: required on create, "keep the stored one" on update). A select only
// ever answers one of its own options, so a value that came from anywhere but
// the form's own list is stored as the default instead.
function mp_provider_credentials($provider, $input) {

	$providers = mp_providers();

	if (!isset($providers[$provider]['fields'])) {

		return array();

	}

	$values = array();

	foreach ($providers[$provider]['fields'] as $field => $meta) {

		$value = (isset($input[$field]) && is_scalar($input[$field])) ? trim((string)$input[$field]) : '';

		if (mp_provider_field_is_select($meta)) {

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

function mp_provider_keys_by_barcode($provider) {

	$providers = mp_providers();

	return isset($providers[$provider]['key']) && $providers[$provider]['key'] === 'barcode';

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

		$waiting = db_value("SELECT id FROM marketplace_sync_queue
			WHERE (account_id = '" . e($account_id) . "')
			  AND (product_id = '" . e($product_id) . "')
			  AND (operation = '" . e($operation) . "')
			  AND (status = 'waiting')
			LIMIT 1");

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

// Queue every mapped product on an account. The "send everything" button.
function mp_enqueue_account($account_id, $operation = 'stock_price') {

	$account_id = (int) $account_id;

	if (!$account_id) {

		return 0;

	}

	$products = (array) db_items("SELECT product_id FROM marketplace_product_map
		WHERE (account_id = '" . e($account_id) . "') AND (status = 'active')");

	$written = 0;

	foreach ($products as $row) {

		$written += mp_enqueue((int)$row['product_id'], $operation);

	}

	return $written;

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
//   (mp_product_price()): minor units in the shop, and decimal where
//   marketplaces want it. Both live in one place because getting either wrong
//   - by a factor of a hundred, or by the tax the shop adds at the checkout
//   and a marketplace counts inside its sale price - is the kind of mistake
//   that sells stock at cost.
//
//   quantity is zero for a product that does not track stock. A marketplace has
//   no concept of "not tracked", and the alternative - sending nothing - leaves
//   the last number they were told standing for ever.
function mp_product_row($account, $map) {

	$product = db_item("SELECT id, name, price, inventory, inventory_quantity, out_of_stock, enabled,
			title, taxable, tax_rate
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

	$price = mp_product_price($product);

	return array(
		'product_id'  => (int)$product['id'],
		'remote_code' => ($remote_code !== '') ? $remote_code : $product['name'],
		'remote_id'   => isset($map['remote_id']) ? (string)$map['remote_id'] : '',
		'name'        => $product['name'],
		'title'       => $product['title'],
		'price'       => $price,
		'price_major' => mp_price_major($price),
		'quantity'    => $quantity,
		'tracked'     => $tracked,
		'taxable'     => !empty($product['taxable']),
		'tax_rate'    => ($product['tax_rate'] === null) ? null : (float)$product['tax_rate'],
		'vat_rate'    => mp_product_vat_rate($product)
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
