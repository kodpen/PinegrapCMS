<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Etsy as a marketplace connector. The seller signs in once on Connections
 * (the etsy provider of connected accounts, purpose etsy), whose token
 * pg_connect_api() renews; a marketplace account holds no key of its own,
 * only the shop and what a listing names there: a shipping profile, a
 * processing profile (readiness state), who made the goods and when.
 * test_credentials() reads the shop from the sign-in (getMe, then getShop)
 * and its profiles, and answers them as data['choices'] for the account's
 * selects (mp_account_choices_merge()). One account is one shop, in the
 * shop's currency.
 *
 * Etsy's seller taxonomy is one tree for every shop, so it is cached under
 * the provider's key (no category_key): category_tree() reads its nodes,
 * category_attributes() a leaf's properties, for the pairing of a product
 * group on Marketplace Products › Listings.
 *
 * create_listings() makes one listing an article: a draft, its pictures
 * uploaded as files in the product's order, the category's properties every
 * product shares, the inventory with one product a variant, then active,
 * read back at once. Etsy answers each call at once, so there is no task:
 * the verdicts carry the listing id, written on the mappings, and a later
 * send of the same article updates that listing rather than making a second.
 *
 * Every call carries the application's x-api-key header
 * (pg_etsy_app_headers()). The Etsy functions of their own (scopes, the
 * provider, the parsers of these answers) are in includes/fn/etsy.php.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_INIT_LOADED')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/etsy.php');

function mp_etsy_connector() {

	return array(
		'test_credentials'    => 'mp_etsy_test_credentials',
		'category_tree'       => 'mp_etsy_category_tree',
		'category_attributes' => 'mp_etsy_category_attributes',
		'create_listings'     => 'mp_etsy_create_listings',
		'listing_fee'         => 'mp_etsy_listing_fee'
	);

}

// The id of the connected account the shop signs in through, or 0.
function mp_etsy_connection_id() {

	foreach (pg_connect_accounts(array('provider' => 'etsy', 'user_id' => 0, 'purpose' => 'etsy')) as $row) {

		return (int)$row['id'];

	}

	return 0;

}

// A call to the Open API with the seller's token and the application's
// x-api-key: function ($method, $path, $options = array()) answering
// array(ok, status, data, error), data always an array. Null when there is no
// connection.
function mp_etsy_api_call() {

	$connection_id = mp_etsy_connection_id();

	if ($connection_id <= 0) {

		return null;

	}

	$key_headers = pg_etsy_app_headers(pg_connect_app('etsy'));

	return function ($method, $path, $options = array()) use ($connection_id, $key_headers) {

		$headers = isset($options['headers']) ? (array)$options['headers'] : array();

		$options['headers'] = array_merge(array('Accept: application/json'), $key_headers, $headers);
		$options['timeout'] = isset($options['timeout']) ? $options['timeout'] : 30;
		$options['agent'] = 'marketplace';

		$result = pg_connect_api($connection_id, $method, pg_etsy_api_base() . $path, $options);

		$result['data'] = is_array($result['data']) ? $result['data'] : array();

		return $result;

	};

}

// What the seller's shop holds: the shop itself, its shipping profiles and
// its processing profiles, and the fixed who_made and when_made values.
// $call is mp_etsy_api_call()'s shape; tests pass recorded answers. Answers
// mp_ok() with data['choices'], or mp_error() saying what to set up on Etsy.
function mp_etsy_read_choices($call) {

	$me = call_user_func($call, 'GET', '/users/me');

	if (!$me['ok']) {

		return mp_error(pg_etsy_error_text($me), pg_etsy_retry($me));

	}

	$shop_id = pg_etsy_shop_id($me['data']);

	if ($shop_id === '') {

		return mp_error(lang('The Etsy account has no shop yet. Open a shop on Etsy, then test again.'));

	}

	$shop = call_user_func($call, 'GET', '/shops/' . $shop_id);

	if (!$shop['ok']) {

		return mp_error(pg_etsy_error_text($shop), pg_etsy_retry($shop));

	}

	$profiles = call_user_func($call, 'GET', '/shops/' . $shop_id . '/shipping-profiles');

	if (!$profiles['ok']) {

		return mp_error(pg_etsy_error_text($profiles), pg_etsy_retry($profiles));

	}

	$readiness = call_user_func($call, 'GET', '/shops/' . $shop_id . '/readiness-state-definitions?limit=100&offset=0');

	if (!$readiness['ok']) {

		return mp_error(pg_etsy_error_text($readiness), pg_etsy_retry($readiness));

	}

	$choices = array(
		'shop'             => pg_etsy_shop_choice($shop_id, $shop['data']),
		'shipping_profile' => pg_etsy_shipping_profile_choices($profiles['data']),
		'readiness_state'  => pg_etsy_readiness_choices($readiness['data']),
		'who_made'         => pg_etsy_who_made_choices(),
		'when_made'        => pg_etsy_when_made_choices()
	);

	if (!$choices['shipping_profile']) {

		return mp_error(lang('The Etsy shop has no shipping profile yet. Create one in Shop Manager under Settings › Delivery settings, then test again.'));

	}

	if (!$choices['readiness_state']) {

		return mp_error(lang('The Etsy shop has no processing profile yet. Create one in Shop Manager under Settings › Delivery settings, then test again.'));

	}

	return mp_ok(array('choices' => $choices));

}

// The connection to Etsy works and the shop's choices are read. $call
// replaces the calls to Etsy in tests (mp_etsy_read_choices()).
function mp_etsy_test_credentials($account, $call = null) {

	if ($call === null) {

		$call = mp_etsy_api_call();

		if ($call === null) {

			return mp_error(lang('Connect an Etsy account on Connections first.'));

		}

	}

	return mp_etsy_read_choices($call);

}

/* -------------------------------------------------------------- taxonomy */

// The seller taxonomy, flattened (pg_etsy_taxonomy_rows()). Several thousand
// nested nodes make an answer of a few megabytes; the cap is generous
// because a cut answer is broken JSON, not a smaller tree. $call replaces
// the calls to Etsy in tests.
function mp_etsy_category_tree($account, $call = null) {

	if ($call === null) {

		$call = mp_etsy_api_call();

		if ($call === null) {

			return mp_error(lang('Connect an Etsy account on Connections first.'));

		}

	}

	$answer = call_user_func($call, 'GET', '/seller-taxonomy/nodes', array('timeout' => 120, 'max_body' => 33554432, 'decode' => true));

	if (!$answer['ok']) {

		return mp_error(pg_etsy_error_text($answer), pg_etsy_retry($answer));

	}

	$rows = pg_etsy_taxonomy_rows($answer['data']);

	if (!$rows) {

		return mp_error(lang('Etsy returned no categories.'));

	}

	return mp_ok(array('categories' => $rows));

}

// The properties one taxonomy node takes (getPropertiesByTaxonomyId), as
// attribute rows (pg_etsy_property_rows()). An id that is not a number is
// never sent.
function mp_etsy_category_attributes($account, $category_id, $call = null) {

	$category_id = trim((string)$category_id);

	if (preg_match('/^[0-9]{1,20}$/', $category_id) !== 1) {

		return mp_error(lang('No category to ask about.'));

	}

	if ($call === null) {

		$call = mp_etsy_api_call();

		if ($call === null) {

			return mp_error(lang('Connect an Etsy account on Connections first.'));

		}

	}

	$answer = call_user_func($call, 'GET', '/seller-taxonomy/nodes/' . $category_id . '/properties', array('timeout' => 60, 'max_body' => 8388608, 'decode' => true));

	if (!$answer['ok']) {

		return mp_error(pg_etsy_error_text($answer), pg_etsy_retry($answer));

	}

	return mp_ok(array('attributes' => pg_etsy_property_rows($answer['data'])));

}

/* -------------------------------------------------------------- listings */

// What Etsy charges for publishing, said on Marketplace Products › Listings
// before an account's first listing goes out.
function mp_etsy_listing_fee($account) {

	return lang('Etsy charges USD 0.20 for each listing it publishes, again each time a listing renews after four months or sells one of several, and 6.5% of each sale. Listing an article here publishes it at once.');

}

// The account's shop, shipping and processing profiles, who made the goods
// and when, or null while one of them has not been chosen.
function mp_etsy_listing_choices($account) {

	$credentials = (isset($account['credentials']) && is_array($account['credentials'])) ? $account['credentials'] : array();

	$choices = array();

	foreach (array('shop', 'shipping_profile', 'readiness_state', 'who_made', 'when_made') as $field) {

		$value = isset($credentials[$field]) ? trim((string)$credentials[$field]) : '';

		if ($value === '') {

			return null;

		}

		$choices[$field] = $value;

	}

	return (preg_match('/^[0-9]{1,20}$/', $choices['shop']) === 1) ? $choices : null;

}

// The rate from the base currency to $currency today (1 for the same
// currency), or null when none is recorded (Settings › Currencies).
function mp_etsy_rate($currency) {

	$base = defined('BASE_CURRENCY_CODE') ? strtoupper((string)BASE_CURRENCY_CODE) : '';

	if ($base === strtoupper($currency)) {

		return 1.0;

	}

	require_once(PG_FUNCTIONS_DIR . '/includes/fn/currency_rates.php');

	$rate = pg_currency_rate($currency, date('Y-m-d'));

	return is_array($rate) ? (float)$rate['rate'] : null;

}

// The listing an earlier send of these products made on the account, as its
// mapping keeps it (remote_id), or ''.
function mp_etsy_known_listing($account, $items) {

	$ids = array();

	foreach ((array)$items as $item) {

		$ids[] = (int)$item['product_id'];

	}

	if (!$ids) {

		return '';

	}

	return (string)db_value("SELECT remote_id FROM marketplace_product_map
		WHERE (account_id = '" . e((int)$account['id']) . "')
		  AND (product_id IN (" . implode(',', $ids) . "))
		  AND (remote_id <> '')
		ORDER BY id ASC
		LIMIT 1");

}

// The content of one of this site's pictures from its address, or null for
// an address that is not one (pg_etsy_image_file_name()) or a file that
// cannot be read.
function mp_etsy_image_bytes($url) {

	$base = (defined('URL_SCHEME') ? URL_SCHEME : 'https://')
		. (defined('HOSTNAME_SETTING') ? HOSTNAME_SETTING : '')
		. (defined('PATH') ? PATH : '/');

	$name = pg_etsy_image_file_name($url, $base);

	if (($name === '') || !defined('FILE_DIRECTORY_PATH')) {

		return null;

	}

	$file = FILE_DIRECTORY_PATH . '/' . $name;

	if (!is_file($file) || (filesize($file) > 20971520)) {

		return null;

	}

	$bytes = file_get_contents($file);

	return ($bytes === false) ? null : $bytes;

}

// A JSON call: $body encoded, its type named.
function mp_etsy_json($call, $method, $path, $body) {

	return call_user_func($call, $method, $path, array(
		'headers' => array('Content-Type: application/json'),
		'body'    => json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
	));

}

// The same verdict for every item: SKU => ok, error, and the listing id
// when there is one, which the mapping keeps so the next send updates it.
function mp_etsy_verdicts($items, $ok, $error, $listing_id) {

	$results = array();

	foreach ($items as $item) {

		$results[(string)$item['code']] = array('ok' => $ok, 'error' => $ok ? '' : (string)$error, 'content_id' => (string)$listing_id);

	}

	return $results;

}

// List one article's items on the account's Etsy shop as one listing.
//
// Refused before any call, as an article (mp_sync_send_listings() writes it
// on the article's row): the account's choices not made, a product without
// a code or a picture, a title Etsy would refuse, products that cannot be one
// listing's options (pg_etsy_variations()). Then the shop's currency is read
// and the rate to it found; no rate or no price refuses the article too.
//
// Then a draft (createDraftListing), or the listing an earlier send made
// brought up to date (updateListing): Etsy has no listing by SKU, so the
// mapping's listing id is what keeps a second send from making a second
// listing. Its pictures are uploaded as files in the product's order, each
// over the one at its rank; the properties every product shares are set;
// the inventory is put with one product a variant; the listing is set
// active and read back, and a listing Etsy keeps inactive is refused with
// what it is.
//
// A failure Etsy may get over (no answer, 429, 5xx) before a listing exists
// gives the article back to the queue. Once one exists every failure is the
// article's verdict carrying the listing id, so a later send updates it
// rather than making another. $call, $rate and $read replace Etsy, the rate
// table and the files in tests; $known is the listing id of an earlier send
// (null reads it from the mappings).
function mp_etsy_create_listings($account, $items, $call = null, $rate = null, $read = null, $known = null) {

	$items = array_values((array)$items);

	$choices = mp_etsy_listing_choices($account);

	if ($choices === null) {

		return mp_error(lang('Choose the Etsy account\'s shop, shipping and processing profiles, who made the goods and when first: test the connection, then pick them.'));

	}

	if (!$items) {

		return mp_ok(array('task_id' => '', 'results' => array()));

	}

	foreach ($items as $item) {

		$problem = pg_etsy_item_problem($item);

		if ($problem !== '') {

			return mp_error($problem);

		}

	}

	$problem = pg_etsy_title_problem(pg_etsy_listing_title($items));

	if ($problem !== '') {

		return mp_error($problem);

	}

	$variations = pg_etsy_variations($items);

	if (!$variations['ok']) {

		return mp_error($variations['error']);

	}

	$images = pg_etsy_listing_images($items);

	$read = ($read === null) ? 'mp_etsy_image_bytes' : $read;

	$files = array();

	foreach ($images as $url) {

		$bytes = call_user_func($read, $url);

		$type = ($bytes === null) ? '' : pg_etsy_image_type($bytes);

		if ($type === '') {

			return mp_error(lang(array('string' => 'The picture {var:1} cannot be read as a JPEG, PNG, GIF or WebP file, so it cannot be uploaded to Etsy.', 'vars' => array(basename((string)parse_url($url, PHP_URL_PATH))))));

		}

		$files[] = array('name' => basename((string)parse_url($url, PHP_URL_PATH)), 'bytes' => $bytes, 'type' => $type);

	}

	if ($call === null) {

		$call = mp_etsy_api_call();

		if ($call === null) {

			return mp_error(lang('Connect an Etsy account on Connections first.'));

		}

	}

	$shop_id = $choices['shop'];

	$shop = call_user_func($call, 'GET', '/shops/' . $shop_id);

	if (!$shop['ok']) {

		return mp_error(pg_etsy_error_text($shop), pg_etsy_retry($shop));

	}

	$currency = isset($shop['data']['currency_code']) && is_scalar($shop['data']['currency_code']) ? strtoupper(trim((string)$shop['data']['currency_code'])) : '';

	if (preg_match('/^[A-Z]{3}$/', $currency) !== 1) {

		return mp_error(lang('Etsy named no currency for the shop.'), true);

	}

	$rate = ($rate === null) ? mp_etsy_rate($currency) : $rate;

	if (!is_numeric($rate) || ((float)$rate <= 0)) {

		return mp_error(lang(array(
			'string' => 'There is no exchange rate from {var:1} to {var:2} yet, so the price on Etsy cannot be worked out.',
			'vars'   => array(defined('BASE_CURRENCY_CODE') ? BASE_CURRENCY_CODE : '', $currency)
		)));

	}

	$prices = array();

	foreach ($items as $item) {

		$prices[(string)$item['code']] = pg_etsy_price(isset($item['price_major']) ? $item['price_major'] : 0, $rate);

		if ($prices[(string)$item['code']] === '') {

			return mp_error(lang(array('string' => '{var:1} has no price.', 'vars' => array((string)$item['code']))));

		}

	}

	$quantity = 0;

	foreach ($items as $item) {

		$quantity += max(0, (int)$item['quantity']);

	}

	if ($quantity <= 0) {

		return mp_error(lang('None of the article\'s products is in stock. Etsy publishes a listing only with something to sell.'));

	}

	$fields = pg_etsy_draft_fields($items, $choices, $prices[(string)$items[0]['code']]);

	// The listing an earlier send made, if Etsy still has it.
	$listing_id = ($known === null) ? mp_etsy_known_listing($account, $items) : (string)$known;

	if ($listing_id !== '') {

		$found = call_user_func($call, 'GET', '/listings/' . rawurlencode($listing_id));

		if (!$found['ok'] && ((int)$found['status'] !== 404)) {

			return mp_error(pg_etsy_error_text($found), pg_etsy_retry($found));

		}

		if (!$found['ok']) {

			$listing_id = '';

		}

	}

	if ($listing_id !== '') {

		$update = $fields;

		unset($update['quantity'], $update['price']);

		$answer = mp_etsy_json($call, 'PATCH', '/shops/' . $shop_id . '/listings/' . $listing_id, $update);

		if (!$answer['ok']) {

			return mp_ok(array('task_id' => '', 'results' => mp_etsy_verdicts($items, false, pg_etsy_error_text($answer), $listing_id)));

		}

	} else {

		$answer = mp_etsy_json($call, 'POST', '/shops/' . $shop_id . '/listings', $fields);

		if (!$answer['ok']) {

			if (pg_etsy_retry($answer)) {

				return mp_error(pg_etsy_error_text($answer), true);

			}

			return mp_ok(array('task_id' => '', 'results' => mp_etsy_verdicts($items, false, pg_etsy_error_text($answer), '')));

		}

		$listing_id = pg_etsy_listing_id($answer['data']);

		if ($listing_id === '') {

			return mp_ok(array('task_id' => '', 'results' => mp_etsy_verdicts($items, false, lang('Etsy made the draft but named no listing id.'), '')));

		}

	}

	foreach ($files as $index => $file) {

		$multipart = pg_etsy_multipart(array('rank' => $index + 1, 'overwrite' => 'true'),
			$file['name'], $file['bytes'], $file['type'], 'pg' . bin2hex(random_bytes(12)));

		$answer = call_user_func($call, 'POST', '/shops/' . $shop_id . '/listings/' . $listing_id . '/images', array(
			'headers' => array('Content-Type: ' . $multipart['content_type']),
			'body'    => $multipart['body'],
			'timeout' => 120
		));

		if (!$answer['ok']) {

			return mp_ok(array('task_id' => '', 'results' => mp_etsy_verdicts($items, false, pg_etsy_error_text($answer), $listing_id)));

		}

	}

	foreach ($variations['shared'] as $property_id => $value) {

		$answer = mp_etsy_json($call, 'PUT', '/shops/' . $shop_id . '/listings/' . $listing_id . '/properties/' . $property_id,
			array('value_ids' => $value['value_ids'], 'values' => $value['values']));

		if (!$answer['ok']) {

			return mp_ok(array('task_id' => '', 'results' => mp_etsy_verdicts($items, false, pg_etsy_error_text($answer), $listing_id)));

		}

	}

	$answer = mp_etsy_json($call, 'PUT', '/listings/' . $listing_id . '/inventory',
		pg_etsy_inventory_body($items, $variations['variations'], $prices, $choices['readiness_state']));

	if (!$answer['ok']) {

		return mp_ok(array('task_id' => '', 'results' => mp_etsy_verdicts($items, false, pg_etsy_error_text($answer), $listing_id)));

	}

	$answer = mp_etsy_json($call, 'PATCH', '/shops/' . $shop_id . '/listings/' . $listing_id, array('state' => 'active'));

	if (!$answer['ok']) {

		return mp_ok(array('task_id' => '', 'results' => mp_etsy_verdicts($items, false, pg_etsy_error_text($answer), $listing_id)));

	}

	// updateListing answers the listing as it now is; only an answer without
	// its state is read again.
	$read_back = isset($answer['data']['state']) ? $answer : call_user_func($call, 'GET', '/listings/' . $listing_id);

	$verdict = $read_back['ok']
		? pg_etsy_listing_verdict($read_back['data'])
		: array('ok' => false, 'error' => pg_etsy_error_text($read_back));

	return mp_ok(array('task_id' => '', 'results' => mp_etsy_verdicts($items, $verdict['ok'], $verdict['error'], $listing_id)));

}
