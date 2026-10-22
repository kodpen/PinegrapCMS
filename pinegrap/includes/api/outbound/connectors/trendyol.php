<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// Trendyol.
//
// Written against the seller integration documentation on
// developers.trendyol.com as it read in October 2026, after the product and
// order services moved to V2 (the product V1 services closed on 15 September
// 2026, the order V1 services on 15 October 2026). Only V2 paths, or paths the
// documentation marks as shared by V1 and V2, are used.
//
// What this connector does so far: prove the credentials, read the category
// tree and what a category demands, open listings, send stock and price, and
// read the result of a batch. Orders are not named in the table below, so
// mp_can() answers no for them and nothing queues work this connector cannot
// do.
//
// Four things about this provider shape the code:
//
//   Authentication is HTTP Basic with the API key and secret, and every request
//   must also carry a User-Agent of "<seller id> - <integrator>". Without it
//   Trendyol answers 403, so the header is built here rather than left to the
//   HTTP client's default agent. A header given in the list replaces the one
//   curl would have generated.
//
//   The seller id is in the path of every seller-scoped call. The category and
//   attribute services are not seller-scoped, so they cannot tell a wrong seller
//   id from a right one - which is why the connection test reads the seller's
//   own product list instead.
//
//   There are two environments with separate credentials. The test ("stage")
//   environment only answers servers whose IP address Trendyol has authorised
//   and says 503 to everyone else, which reads like an outage unless the
//   message says otherwise.
//
//   In V2 a category's attributes no longer carry their values. The values are
//   a separate, paged service, one call per attribute, and the whole family of
//   category services shares one per-endpoint limit of fifty requests in ten
//   seconds. Fetching a category therefore has a budget of calls, and running
//   out of it is an error rather than a partial answer: an attribute missing
//   half its values would be offered as a short list that silently lacks the
//   right one.
//
// Every function that talks to Trendyol takes an optional $transport, a
// callable with the signature of api_http_request(). The connector table never
// passes one, so production traffic always goes through the address-checked
// client; the tests pass a recorder that answers from saved responses.

if (!defined('PG_INIT_LOADED')) {

	exit;

}

function mp_trendyol_connector() {

	return array(
		'test_credentials'    => 'mp_trendyol_test_credentials',
		'category_tree'       => 'mp_trendyol_category_tree',
		'category_attributes' => 'mp_trendyol_category_attributes',
		'create_listings'     => 'mp_trendyol_create_listings',
		'push_stock_price'    => 'mp_trendyol_push_stock_price',
		'read_task'           => 'mp_trendyol_read_task'
	);

}

/* ------------------------------------------------------------- the account */

// 'stage' or 'production'. Anything else, including a row written before the
// field existed, is the live environment.
function mp_trendyol_environment($account) {

	$value = isset($account['credentials']['environment']) ? (string)$account['credentials']['environment'] : '';

	return ($value === 'stage') ? 'stage' : 'production';

}

function mp_trendyol_base($account) {

	return (mp_trendyol_environment($account) === 'stage')
		? 'https://stageapigw.trendyol.com'
		: 'https://apigw.trendyol.com';

}

// The seller id as it goes into a path and the User-Agent, or '' when what is
// stored is not one. Digits only: anything else would be a path segment
// somebody typed into a form.
function mp_trendyol_seller_id($account) {

	$value = isset($account['credentials']['seller_id']) ? trim((string)$account['credentials']['seller_id']) : '';

	return (preg_match('/^[0-9]{1,20}$/', $value) === 1) ? $value : '';

}

// The second half of the User-Agent. Trendyol reserves named values for
// integrator companies registered with them; a shop running its own software
// is a self integration in their terms.
function mp_trendyol_integrator() {

	return 'SelfIntegration';

}

function mp_trendyol_headers($account) {

	$key    = isset($account['credentials']['api_key'])    ? trim((string)$account['credentials']['api_key'])    : '';
	$secret = isset($account['credentials']['api_secret']) ? trim((string)$account['credentials']['api_secret']) : '';

	return array(
		'Authorization: Basic ' . base64_encode($key . ':' . $secret),
		'User-Agent: ' . mp_trendyol_seller_id($account) . ' - ' . mp_trendyol_integrator(),
		'Accept: application/json'
	);

}

// What is wrong with the stored credentials before anything is sent, or ''.
//
// Checked locally so that a missing secret is reported as a missing secret,
// not as whatever Trendyol says to a request without one, and so that the
// seller id never reaches a path unless it is a number.
function mp_trendyol_credentials_problem($account) {

	$key    = isset($account['credentials']['api_key'])    ? trim((string)$account['credentials']['api_key'])    : '';
	$secret = isset($account['credentials']['api_secret']) ? trim((string)$account['credentials']['api_secret']) : '';

	if ($key === '' || $secret === '') {

		return lang('The Trendyol API key and secret are missing. Copy them from your Trendyol seller panel, under Account Information › Integration Information.');

	}

	if (mp_trendyol_seller_id($account) === '') {

		return lang('The Trendyol seller ID is a number. Copy it from your Trendyol seller panel, under Account Information › Integration Information.');

	}

	return '';

}

/* ---------------------------------------------------------------- one call */

// One call, with their answer already read. A GET unless a body is given, in
// which case it is a POST of that body as JSON.
function mp_trendyol_call($account, $path, $max_body, $transport = null, $body = null) {

	$problem = mp_trendyol_credentials_problem($account);

	if ($problem !== '') {

		return mp_error($problem);

	}

	if ($transport === null) {

		$transport = 'api_http_request';

	}

	$headers = mp_trendyol_headers($account);

	$options = array(
		'timeout'  => 30,
		'max_body' => $max_body,
		'agent'    => 'marketplace'
	);

	if ($body !== null) {

		$headers[] = 'Content-Type: application/json';

		$options['body'] = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

		// Text that is not valid UTF-8 (a description pasted from an old
		// export) makes json_encode() answer false, which would be sent as
		// the word "false".
		if ($options['body'] === false) {

			return mp_error(lang(array(
				'string' => 'The request to Trendyol could not be written as JSON: {var:1}',
				'vars'   => array(json_last_error_msg())
			)));

		}

	}

	$options['headers'] = $headers;

	$outcome = call_user_func($transport, ($body === null) ? 'GET' : 'POST', mp_trendyol_base($account) . $path, $options);

	return mp_trendyol_read($outcome, mp_trendyol_environment($account));

}

// An answer of api_http_request() as the connector's ok/error shape.
//
// The status code is read before the body for the four answers whose body is
// not worth reading: Trendyol's refusals of the credentials and of the
// User-Agent, its rate limit, and the test environment's 503 for an address it
// does not know. Each of those has one likely cause, and naming the cause is
// more use to the operator than the envelope's exception class.
function mp_trendyol_read($outcome, $environment = 'production') {

	$status = isset($outcome['status']) ? (int)$outcome['status'] : 0;

	$error = isset($outcome['error']) ? (string)$outcome['error'] : '';

	if ($error !== '') {

		// A transport failure: their server, the network, or a certificate.
		return mp_error($error, true);

	}

	if ($status === 401) {

		return mp_error(lang('Trendyol did not accept the API key and secret. Check them in your Trendyol seller panel, under Account Information › Integration Information, and check that they belong to the environment chosen for this account.'));

	}

	if ($status === 403) {

		return mp_error(lang('Trendyol refused the request. Check the seller ID: it has to be the number shown beside the API key in your Trendyol seller panel.'));

	}

	if ($status === 429) {

		return mp_error(lang('Trendyol is rate limiting this store. Wait a minute and try again.'), true);

	}

	if ($status === 503 && $environment === 'stage') {

		return mp_error(lang('The Trendyol test environment answered 503. It answers that to servers whose IP address it has not authorised: ask Trendyol to authorise this server\'s address for the test environment, or switch the account to the live environment.'), true);

	}

	$body = isset($outcome['body']) ? (string)$outcome['body'] : '';

	$decoded = json_decode($body, true);

	$ok = ($status >= 200 && $status < 300);

	if (!is_array($decoded)) {

		return mp_error(
			lang(array('string' => 'Trendyol answered {var:1} with something that is not JSON.', 'vars' => array($status))),
			api_http_should_retry(array('ok' => $ok, 'status' => $status)));

	}

	$message = mp_trendyol_error_message($decoded);

	if ($message !== '' || !$ok) {

		return mp_error(
			($message !== '')
				? $message
				: lang(array('string' => 'Trendyol answered {var:1}.', 'vars' => array($status))),
			api_http_should_retry(array('ok' => $ok, 'status' => $status)),
			$decoded);

	}

	return mp_ok($decoded);

}

// Their error text, or '' when the answer is not an error.
//
// The envelope is {"timestamp", "exception", "errors": [{"key", "message",
// "errorParams"}]} on the services that document one. A bare "message" is read
// too; the exception class alone is the last resort, because it names a Java
// type rather than a reason.
function mp_trendyol_error_message($decoded) {

	if (!is_array($decoded)) {

		return '';

	}

	if (isset($decoded['errors']) && is_array($decoded['errors']) && $decoded['errors']) {

		$parts = array();

		foreach ($decoded['errors'] as $entry) {

			if (is_array($entry)) {

				if (isset($entry['message']) && trim((string)$entry['message']) !== '') {

					$parts[] = trim((string)$entry['message']);

				} elseif (isset($entry['key']) && trim((string)$entry['key']) !== '') {

					$parts[] = trim((string)$entry['key']);

				}

			} elseif (trim((string)$entry) !== '') {

				$parts[] = trim((string)$entry);

			}

		}

		$joined = mb_substr(implode('; ', array_unique($parts)), 0, 500);

		if ($joined !== '') {

			return $joined;

		}

	}

	if (isset($decoded['exception']) && trim((string)$decoded['exception']) !== '') {

		if (isset($decoded['message']) && trim((string)$decoded['message']) !== '') {

			return mb_substr(trim((string)$decoded['message']), 0, 500);

		}

		return mb_substr(trim((string)$decoded['exception']), 0, 500);

	}

	return '';

}

/* ------------------------------------------------------------ can we talk */

// One approved product, page size one.
//
// It is seller-scoped, so a wrong seller id is caught here and not on the
// first stock push; it is in the read group with the high per-minute limit;
// and it writes nothing. The category tree would be cheaper to answer and
// proves nothing about the seller id.
function mp_trendyol_test_credentials($account, $transport = null) {

	$seller_id = mp_trendyol_seller_id($account);

	$result = mp_trendyol_call($account,
		'/integration/product/sellers/' . $seller_id . '/products/approved?page=0&size=1',
		65536, $transport);

	if (!$result['ok']) {

		return $result;

	}

	$data = $result['data'];

	if (!isset($data['totalElements']) && !isset($data['content'])) {

		return mp_error(lang('Trendyol answered, but not with the seller\'s product list.'));

	}

	return mp_ok(array(
		'products'    => isset($data['totalElements']) ? (int)$data['totalElements'] : 0,
		'environment' => mp_trendyol_environment($account)
	));

}

/* -------------------------------------------------------------- the taxonomy */

// Their whole category tree, flattened.
//
// Answers rows of array(remote_id, parent_id, name, path, is_leaf), the shape
// mp_categories_refresh() stores. A leaf is a node whose subCategories is
// empty or absent; only a leaf can carry a product.
function mp_trendyol_category_tree($account, $transport = null) {

	// The tree is several megabytes. The cap is generous because a truncated
	// answer is not a shorter tree, it is invalid JSON.
	$result = mp_trendyol_call($account, '/integration/product/product-categories', 33554432, $transport);

	if (!$result['ok']) {

		return $result;

	}

	$rows = mp_trendyol_category_rows($result['data']);

	if (!$rows) {

		return mp_error(lang('Trendyol returned no categories.'));

	}

	return mp_ok(array('categories' => $rows));

}

function mp_trendyol_category_rows($decoded) {

	$rows = array();

	$roots = (isset($decoded['categories']) && is_array($decoded['categories'])) ? $decoded['categories'] : array();

	mp_trendyol_category_walk($roots, '', '', $rows);

	return $rows;

}

function mp_trendyol_category_walk($nodes, $parent_id, $parent_path, &$rows) {

	foreach ((array)$nodes as $node) {

		if (!is_array($node) || !isset($node['id']) || !is_numeric($node['id'])) {

			continue;

		}

		$id = (string)(int)$node['id'];

		$name = isset($node['name']) ? trim((string)$node['name']) : '';

		$path = ($parent_path === '') ? $name : ($parent_path . ' > ' . $name);

		$children = (isset($node['subCategories']) && is_array($node['subCategories']))
			? $node['subCategories'] : array();

		// The walk knows the parent; parentId is only read for a node handed in
		// without one, which the tree never does for anything below the top.
		$parent = $parent_id;

		if ($parent === '' && isset($node['parentId']) && is_numeric($node['parentId'])) {

			$parent = (string)(int)$node['parentId'];

		}

		$rows[] = array(
			'remote_id' => $id,
			'parent_id' => $parent,
			'name'      => $name,
			'path'      => $path,
			'is_leaf'   => $children ? 0 : 1
		);

		if ($children) {

			mp_trendyol_category_walk($children, $id, $path, $rows);

		}

	}

}

/* ------------------------------------------------------------- attributes */

// How many value pages one category fetch may ask for.
//
// Under the documented fifty requests in ten seconds to one endpoint, with the
// attribute list itself counted, so a fetch cannot be throttled by its own
// calls however fast Trendyol answers.
function mp_trendyol_value_call_budget() {

	return 45;

}

// Their ceiling for one page of values.
function mp_trendyol_value_page_size() {

	return 1000;

}

// What one category demands, with each attribute's values.
//
// Answers rows of array(attribute_id, name, mandatory, is_variant,
// custom_allowed, sort_order, values) like the other connectors.
// "varianter" is Trendyol's word for the attribute that tells the variants of
// one article apart; "allowCustom" lets the seller type a value of their own.
// "slicer" (an attribute that splits one article into separate pages, usually
// the colour) has no column here and is not needed to answer the attributes.
function mp_trendyol_category_attributes($account, $category_id, $transport = null) {

	$category_id = trim((string)$category_id);

	if (preg_match('/^[0-9]{1,20}$/', $category_id) !== 1) {

		return mp_error(lang('No category to ask about.'));

	}

	$result = mp_trendyol_call($account,
		'/integration/product/categories/' . $category_id . '/attributes',
		4194304, $transport);

	if (!$result['ok']) {

		return $result;

	}

	$rows = mp_trendyol_attribute_rows($result['data']);

	$budget = mp_trendyol_value_call_budget();

	foreach ($rows as $index => $row) {

		$values = mp_trendyol_attribute_values($account, $category_id, $row['attribute_id'], $budget, $transport);

		if (!$values['ok']) {

			return $values;

		}

		$rows[$index]['values'] = $values['data']['values'];

	}

	return mp_ok(array('attributes' => $rows));

}

// The attribute definitions of one category, without their values.
function mp_trendyol_attribute_rows($decoded) {

	$rows = array();

	$list = (isset($decoded['categoryAttributes']) && is_array($decoded['categoryAttributes']))
		? $decoded['categoryAttributes'] : array();

	$seen = array();

	foreach ($list as $position => $entry) {

		if (!is_array($entry) || !isset($entry['attribute']['id']) || !is_numeric($entry['attribute']['id'])) {

			continue;

		}

		$id = (string)(int)$entry['attribute']['id'];

		if (isset($seen[$id])) {

			continue;

		}

		$seen[$id] = true;

		$rows[] = array(
			'attribute_id'   => $id,
			'name'           => isset($entry['attribute']['name']) ? trim((string)$entry['attribute']['name']) : '',
			'mandatory'      => !empty($entry['required']) ? 1 : 0,
			'is_variant'     => !empty($entry['varianter']) ? 1 : 0,
			'custom_allowed' => !empty($entry['allowCustom']) ? 1 : 0,
			// Their list has no order of its own; the order they send it in is
			// the order their panel shows.
			'sort_order'     => (int)$position,
			'values'         => array()
		);

	}

	return $rows;

}

// Every value of one attribute, page by page, spending from $budget.
function mp_trendyol_attribute_values($account, $category_id, $attribute_id, &$budget, $transport = null) {

	$values = array();

	$seen = array();

	$size = mp_trendyol_value_page_size();

	for ($page = 0; ; $page++) {

		if ($budget <= 0) {

			return mp_error(lang('This Trendyol category has more attribute values than can be fetched in one go. Try again in a minute; if it keeps happening, the category is too large for this screen.'));

		}

		$budget--;

		$result = mp_trendyol_call($account,
			'/integration/product/categories/' . $category_id . '/attributes/' . $attribute_id
				. '/values?page=' . $page . '&size=' . $size,
			4194304, $transport);

		if (!$result['ok']) {

			return $result;

		}

		$read = mp_trendyol_value_page($result['data']);

		foreach ($read['values'] as $value) {

			if (!isset($seen[$value['id']])) {

				$seen[$value['id']] = true;

				$values[] = $value;

			}

		}

		// Their page count decides where the list ends, because a server that
		// pages by less than was asked for would make every page look short.
		// Only an answer without a count falls back to "a short page is the
		// last"; an empty page ends it either way.
		if (!$read['values']) {

			break;

		}

		if ($read['total_pages'] > 0) {

			if ($page + 1 >= $read['total_pages']) {

				break;

			}

		} elseif (count($read['values']) < $size) {

			break;

		}

	}

	return mp_ok(array('values' => $values));

}

// One page of values as array(values => [[id, value]], total_pages).
function mp_trendyol_value_page($decoded) {

	$values = array();

	$content = (isset($decoded['content']) && is_array($decoded['content'])) ? $decoded['content'] : array();

	foreach ($content as $entry) {

		if (!is_array($entry) || !isset($entry['attributeValueId']) || !is_numeric($entry['attributeValueId'])) {

			continue;

		}

		$values[] = array(
			'id'    => (string)(int)$entry['attributeValueId'],
			'value' => isset($entry['attributeValue']) ? trim((string)$entry['attributeValue']) : ''
		);

	}

	return array(
		'values'      => $values,
		'total_pages' => isset($decoded['totalPages']) ? max(0, (int)$decoded['totalPages']) : 0
	);

}

/* ---------------------------------------------------------------- listings */

// Their ceiling for one create request.
function mp_trendyol_listing_batch_size() {

	return 1000;

}

// Open listings for one article's products.
//
// $items is what mp_listing_items() assembled; 'code' is the barcode, because
// this provider is keyed on barcodes ('key' in mp_providers()), and it is the
// barcode their batch result names each verdict by. Answers a task handle in
// data['task_id'] like the other connectors: the batch is not finished until
// read_task() says so, and a line that finished SUCCESS is a product sent to
// their approval, not yet a product on sale.
//
// Every refusal this connector can make is made before the request: their own
// verdict arrives per barcode minutes later and in their words, and an article
// whose barcode, brand or pictures cannot go is something the operator can see
// and fix now.
function mp_trendyol_create_listings($account, $items, $transport = null) {

	$items = array_slice((array)$items, 0, mp_trendyol_listing_batch_size());

	if (!$items) {

		return mp_error(lang('Nothing to list.'));

	}

	$names = array();

	foreach ($items as $item) {

		$name = isset($item['brand']) ? trim((string)$item['brand']) : '';

		if ($name !== '') {

			$names[$name] = true;

		}

	}

	// The body is checked once without brand ids first, so that a product
	// without a brand, a barcode their rules refuse or a picture on http is
	// reported without a single call having been made.
	$checked = mp_trendyol_listing_body($account, $items, null);

	if (!$checked['ok']) {

		return $checked;

	}

	$brands = mp_trendyol_brand_ids($account, array_keys($names), $transport);

	if (!$brands['ok']) {

		return $brands;

	}

	$body = mp_trendyol_listing_body($account, $items, $brands['data']['ids']);

	if (!$body['ok']) {

		return $body;

	}

	$result = mp_trendyol_call($account,
		'/integration/product/sellers/' . mp_trendyol_seller_id($account) . '/v2/products',
		65536, $transport, array('items' => $body['data']['items']));

	if (!$result['ok']) {

		return $result;

	}

	$task_id = isset($result['data']['batchRequestId']) ? trim((string)$result['data']['batchRequestId']) : '';

	if ($task_id === '') {

		return mp_error(lang('Trendyol accepted the listing request but did not return a batch id.'), true);

	}

	return mp_ok(array('task_id' => $task_id, 'sent' => count($body['data']['items'])));

}

// The create request's items, or the first reason one of them cannot go.
//
// $brand_ids is name => Trendyol brand id; null checks everything except the
// brand ids, which is the pass made before any brand has been looked up.
function mp_trendyol_listing_body($account, $items, $brand_ids) {

	$currency = defined('BASE_CURRENCY_CODE') ? strtoupper((string)BASE_CURRENCY_CODE) : 'TRY';

	// Trendyol Türkiye takes prices in lira and the create request has no
	// currency field. A shop priced in another currency would list every
	// product at a number that means something else over there.
	if ($currency !== 'TRY') {

		return mp_error(lang(array(
			'string' => 'Trendyol sells in Turkish lira, and this shop\'s prices are in {var:1}.',
			'vars'   => array($currency)
		)));

	}

	$preparing = isset($account['preparing_day']) ? (int)$account['preparing_day'] : 0;

	$out = array();

	foreach ((array)$items as $item) {

		$name = isset($item['stock_code']) ? (string)$item['stock_code'] : '';

		$barcode = isset($item['code']) ? trim((string)$item['code']) : '';

		$problem = mp_trendyol_barcode_problem($barcode);

		if ($problem !== '') {

			return mp_error(lang(array('string' => '{var:1}: {var:2}', 'vars' => array($name, $problem))));

		}

		$main_id = isset($item['group_code']) ? trim((string)$item['group_code']) : '';

		// The model code groups the variants; cutting it short could make two
		// articles one, so a long one is refused rather than trimmed.
		if ($main_id === '' || mb_strlen($main_id) > 40) {

			return mp_error(lang(array(
				'string' => 'The article code {var:1} cannot be Trendyol\'s model code: it has to be 1 to 40 characters.',
				'vars'   => array($main_id)
			)));

		}

		if (mb_strlen($name) > 100) {

			return mp_error(lang(array(
				'string' => 'The product code {var:1} is longer than the 100 characters Trendyol keeps as a stock code.',
				'vars'   => array($name)
			)));

		}

		$title = isset($item['title']) ? trim((string)$item['title']) : '';

		if ($title === '') {

			return mp_error(lang(array('string' => '{var:1} has no title.', 'vars' => array($name))));

		}

		$description = isset($item['description']) ? trim((string)$item['description']) : '';

		if ($description === '') {

			return mp_error(lang(array(
				'string' => '{var:1} has no description, and Trendyol will not list a product without one.',
				'vars'   => array($name)
			)));

		}

		// A cut description is broken HTML, so it is refused instead.
		if (mb_strlen($description) > 30000) {

			return mp_error(lang(array(
				'string' => 'The description of {var:1} is longer than the 30,000 characters Trendyol takes.',
				'vars'   => array($name)
			)));

		}

		$price = round((float)(isset($item['price_major']) ? $item['price_major'] : 0), 2);

		if ($price <= 0) {

			return mp_error(lang(array('string' => '{var:1} has no price.', 'vars' => array($name))));

		}

		$brand = isset($item['brand']) ? trim((string)$item['brand']) : '';

		if ($brand === '') {

			return mp_error(lang(array(
				'string' => '{var:1} has no brand. Trendyol needs one for every product: fill in Brand on the product.',
				'vars'   => array($name)
			)));

		}

		$images = array();

		foreach ((array)(isset($item['images']) ? $item['images'] : array()) as $url) {

			// They fetch the pictures themselves and only over https.
			if (stripos((string)$url, 'https://') !== 0) {

				return mp_error(lang(array(
					'string' => 'Trendyol only fetches pictures over https, and the pictures of {var:1} are not. Set the site address to https first.',
					'vars'   => array($name)
				)));

			}

			if (count($images) < 8) {

				$images[] = array('url' => (string)$url);

			}

		}

		if (!$images) {

			return mp_error(lang(array(
				'string' => '{var:1} has no picture. The marketplace will not list a product without one.',
				'vars'   => array($name)
			)));

		}

		$attributes = array();

		foreach ((array)(isset($item['attributes']) ? $item['attributes'] : array()) as $attribute) {

			if (!isset($attribute['id']) || !is_numeric($attribute['id'])) {

				continue;

			}

			$entry = array('attributeId' => (int)$attribute['id']);

			// A value from their list, or free text where the category allows
			// it; never both.
			if (isset($attribute['value_id']) && is_numeric($attribute['value_id'])) {

				$entry['attributeValueId'] = (int)$attribute['value_id'];

			} elseif (isset($attribute['custom_value']) && trim((string)$attribute['custom_value']) !== '') {

				$entry['customAttributeValue'] = trim((string)$attribute['custom_value']);

			} else {

				continue;

			}

			$attributes[] = $entry;

		}

		$row = array(
			'barcode'       => $barcode,
			'title'         => mb_substr($title, 0, 100),
			'productMainId' => $main_id,
			'brandId'       => 0,
			'categoryId'    => (int)(isset($item['category_id']) ? $item['category_id'] : 0),
			'quantity'      => max(0, (int)(isset($item['quantity']) ? $item['quantity'] : 0)),
			'stockCode'     => $name,
			'description'   => $description,
			// One price in the shop, so both carry it; their rule is only that
			// the list price is not below the sale price.
			'listPrice'     => $price,
			'salePrice'     => $price,
			'vatRate'       => mp_trendyol_vat_rate($item),
			'images'        => $images,
			'attributes'    => $attributes
		);

		// The account's days to prepare, sent as their delivery duration.
		if ($preparing > 0) {

			$row['deliveryOption'] = array('deliveryDuration' => min(30, $preparing));

		}

		if ($brand_ids !== null) {

			if (!isset($brand_ids[$brand]) || (int)$brand_ids[$brand] <= 0) {

				return mp_error(lang(array(
					'string' => 'Trendyol has no brand called {var:1}. Write the brand on the product exactly as Trendyol does.',
					'vars'   => array($brand)
				)));

			}

			$row['brandId'] = (int)$brand_ids[$brand];

		}

		$out[] = $row;

	}

	return mp_ok(array('items' => $out));

}

// What is wrong with a barcode by their rules, or ''.
//
// At most forty characters; letters (Turkish ones included) and digits, with
// the dot, the dash and the underscore as the only other characters.
function mp_trendyol_barcode_problem($barcode) {

	$barcode = (string)$barcode;

	if ($barcode === '') {

		return lang('Trendyol needs a barcode for every product.');

	}

	if (mb_strlen($barcode) > 40 || preg_match('/^[\p{L}\p{N}._-]+$/u', $barcode) !== 1) {

		return lang(array(
			'string' => 'the barcode {var:1} cannot be used on Trendyol: at most 40 letters and digits, with dots, dashes and underscores.',
			'vars'   => array($barcode)
		));

	}

	return '';

}

// Trendyol takes the rates 0, 1, 10 and 20.
//
// The same answer n11 gets, for the same reason: the rate can be any number,
// and the nearest allowed rate below it is sent - rounding up would overstate
// the tax the buyer pays.
//
// The rate is the one inside the price that is sent (vat_rate,
// mp_applied_vat_rate()): 0 for a product the shop does not tax or a country
// in no tax zone, because Trendyol works the VAT back out of the sale price
// and a rate the price does not carry would come out of the seller's net.
// Without one (tax off: the stored price is final, VAT inside) the product's
// own rate, else the zone's.
function mp_trendyol_vat_rate($item) {

	if (isset($item['vat_rate']) && $item['vat_rate'] !== null) {

		$rate = (float)$item['vat_rate'];

	} else {

		$rate = (isset($item['tax_rate']) && $item['tax_rate'] !== null)
			? (float)$item['tax_rate']
			: (function_exists('get_default_tax_rate') ? (float)get_default_tax_rate() : 20.0);

	}

	$best = 0;

	foreach (array(0, 1, 10, 20) as $candidate) {

		if ($rate >= $candidate) {

			$best = $candidate;

		}

	}

	return $best;

}

// Their brand ids for a set of brand names, as name => id.
//
// One call per name, to the by-name service, which matches whole names and
// minds the case. A name it does not know is not an error here; the body
// check names the product whose brand is missing.
function mp_trendyol_brand_ids($account, $names, $transport = null) {

	$ids = array();

	foreach ((array)$names as $name) {

		$name = trim((string)$name);

		if ($name === '') {

			continue;

		}

		$result = mp_trendyol_call($account,
			'/integration/product/brands/by-name?name=' . rawurlencode($name),
			262144, $transport);

		if (!$result['ok']) {

			return $result;

		}

		$id = mp_trendyol_brand_match($result['data'], $name);

		if ($id > 0) {

			$ids[$name] = $id;

		}

	}

	return mp_ok(array('ids' => $ids));

}

// The id of the brand called $name in their answer, or 0.
//
// The answer is a bare list (or, on other brand services, wrapped in
// "brands"). The exact name wins; otherwise one brand whose name differs only
// in case, and none when two do, because picking between them is a guess.
function mp_trendyol_brand_match($answer, $name) {

	$list = (isset($answer['brands']) && is_array($answer['brands'])) ? $answer['brands'] : (array)$answer;

	$folded = array();

	foreach ($list as $brand) {

		if (!is_array($brand) || !isset($brand['id']) || !is_numeric($brand['id']) || !isset($brand['name'])) {

			continue;

		}

		if (trim((string)$brand['name']) === $name) {

			return (int)$brand['id'];

		}

		if (mb_strtolower(trim((string)$brand['name'])) === mb_strtolower($name)) {

			$folded[(int)$brand['id']] = true;

		}

	}

	return (count($folded) === 1) ? (int)key($folded) : 0;

}

/* --------------------------------------------------------- stock and price */

// Their ceiling for one stock and price request.
function mp_trendyol_stock_batch_size() {

	return 1000;

}

// The most stock Trendyol takes for one product.
function mp_trendyol_max_quantity() {

	return 20000;

}

// How long Trendyol refuses a request identical to one it had before.
function mp_trendyol_repeat_window() {

	return 900;

}

// Send a batch of stock and price changes.
//
// $rows is what mp_product_row() built, one per product; 'remote_code' is the
// barcode, which is the only key their inventory service takes. Answers a task
// handle in data['task_id'], read later with read_task(): their batch result
// for a stock and price update carries no status of its own, only one per
// line.
//
// Products this connector can tell will be refused are left out of the
// request and answered in data['refused'] (product id => reason), so that each
// fails on its own reason rather than the batch failing on one of them. When
// nothing is left to send, task_id is '' and the rest had nothing to say.
//
// Stock and price are optional per line on their side, so an account that
// does not send prices sends quantities alone and the prices set in the
// Trendyol panel stand.
function mp_trendyol_push_stock_price($account, $rows, $transport = null) {

	$rows = array_slice((array)$rows, 0, mp_trendyol_stock_batch_size());

	if (!$rows) {

		return mp_error(lang('Nothing to send.'));

	}

	$with_price = !empty($account['push_price']);
	$with_stock = !empty($account['push_stock']);

	if (!$with_price && !$with_stock) {

		return mp_error(lang('This account sends neither stock nor price.'));

	}

	$currency = defined('BASE_CURRENCY_CODE') ? strtoupper((string)BASE_CURRENCY_CODE) : 'TRY';

	// The request has no currency field: a number sent is a number of lira.
	if ($with_price && $currency !== 'TRY') {

		return mp_error(lang(array(
			'string' => 'Trendyol sells in Turkish lira, and this shop\'s prices are in {var:1}. Turn off sending prices for this account to send stock alone.',
			'vars'   => array($currency)
		)));

	}

	$built = mp_trendyol_stock_body($rows, $with_stock, $with_price);

	if (!$built['items']) {

		return mp_ok(array('task_id' => '', 'sent' => 0, 'refused' => $built['refused']));

	}

	$result = mp_trendyol_call($account,
		'/integration/inventory/sellers/' . mp_trendyol_seller_id($account) . '/products/price-and-inventory',
		65536, $transport, array('items' => $built['items']));

	if (!$result['ok']) {

		// The same body inside fifteen minutes is refused, which happens when
		// a batch is sent again after a timeout that their side had in fact
		// accepted. It is asked again once the window has passed, when it is
		// either accepted with a batch id of its own or has been replaced by
		// newer numbers; failing it for good would report an error about
		// numbers Trendyol already has.
		if (mp_trendyol_is_repeat_refusal($result['error'])) {

			return mp_error(
				lang('Trendyol had this same stock and price request in the last 15 minutes and refuses it until then. It is sent again after that.'),
				true,
				array('retry_after' => mp_trendyol_repeat_window()));

		}

		return $result;

	}

	$task_id = isset($result['data']['batchRequestId']) ? trim((string)$result['data']['batchRequestId']) : '';

	if ($task_id === '') {

		return mp_error(lang('Trendyol accepted the stock and price update but did not return a batch id.'), true);

	}

	return mp_ok(array('task_id' => $task_id, 'sent' => count($built['items']), 'refused' => $built['refused']));

}

// The request's items, and the products left out with the reason for each.
//
// Answers array(items, refused). Per product:
//
//   a barcode their rules refuse is left out: Trendyol would refuse it per
//   line minutes later, and the reason here names the rule.
//
//   a second product under a barcode already in the batch is left out: one
//   line in their answer would be read as the verdict for both.
//
//   quantity is sent only for a product that tracks stock (zero would take an
//   untracked product off sale), and above their ceiling of twenty thousand
//   the ceiling is sent - the most that can be offered there.
//
//   price is sent as both list and sale price, the shop having one price, and
//   a product without a price is left out rather than offered for nothing.
//   The row's price_major already carries the tax the checkout adds
//   (mp_product_price()): their sale price is what the buyer pays, VAT
//   inside.
//
//   a product with nothing to say (untracked, and prices not sent) is simply
//   not in the request.
function mp_trendyol_stock_body($rows, $with_stock, $with_price) {

	$items = array();

	$refused = array();

	$seen = array();

	foreach ((array)$rows as $row) {

		if (!is_array($row) || empty($row['product_id'])) {

			continue;

		}

		$product_id = (int)$row['product_id'];

		$name = isset($row['name']) ? (string)$row['name'] : (string)$product_id;

		$barcode = isset($row['remote_code']) ? trim((string)$row['remote_code']) : '';

		$problem = mp_trendyol_barcode_problem($barcode);

		if ($problem !== '') {

			$refused[$product_id] = lang(array('string' => '{var:1}: {var:2}', 'vars' => array($name, $problem)));

			continue;

		}

		$key = mb_strtolower($barcode);

		if (isset($seen[$key])) {

			$refused[$product_id] = lang(array(
				'string' => '{var:1}: another product in the same batch is sent under the barcode {var:2}.',
				'vars'   => array($name, $barcode)
			));

			continue;

		}

		$item = array('barcode' => $barcode);

		if ($with_stock && !empty($row['tracked'])) {

			$item['quantity'] = min(mp_trendyol_max_quantity(), max(0, (int)(isset($row['quantity']) ? $row['quantity'] : 0)));

		}

		if ($with_price) {

			$price = round((float)(isset($row['price_major']) ? $row['price_major'] : 0), 2);

			if ($price <= 0) {

				$refused[$product_id] = lang(array(
					'string' => '{var:1} has no price, so Trendyol is not sent its stock and price.',
					'vars'   => array($name)
				));

				continue;

			}

			$item['salePrice'] = $price;
			$item['listPrice'] = $price;

		}

		if (count($item) === 1) {

			continue;

		}

		$seen[$key] = true;

		$items[] = $item;

	}

	return array('items' => $items, 'refused' => $refused);

}

// Whether a refusal is their "same request within fifteen minutes" rule.
//
// Their documentation gives the text, in Turkish, and not the status code; the
// English wording is matched too in case the message is translated.
function mp_trendyol_is_repeat_refusal($message) {

	return preg_match('/15\s*(dakika|minute)/iu', (string)$message) === 1;

}

/* --------------------------------------------------------------- batches */

// The result of an earlier batch, per barcode.
//
// Answers data['finished'] false while it runs, and otherwise a results map
// keyed by barcode, which is what the mapping rows of this provider store.
// Their results are kept for four hours; the queue asks well inside that.
function mp_trendyol_read_task($account, $task_id, $transport = null) {

	$task_id = trim((string)$task_id);

	if (preg_match('/^[A-Za-z0-9-]{1,64}$/', $task_id) !== 1) {

		return mp_error(lang('No task to ask about.'));

	}

	$result = mp_trendyol_call($account,
		'/integration/product/sellers/' . mp_trendyol_seller_id($account) . '/products/batch-requests/' . $task_id,
		8388608, $transport);

	if (!$result['ok']) {

		return $result;

	}

	return mp_ok(mp_trendyol_batch_results($result['data']));

}

// A batch result as array(finished, status, results).
//
// A create or update batch carries a status of its own, COMPLETED when it is
// done. A stock and price batch carries none, so there it is finished when
// every line has its own verdict.
function mp_trendyol_batch_results($decoded) {

	$status = isset($decoded['status']) ? strtoupper(trim((string)$decoded['status'])) : '';

	$items = (isset($decoded['items']) && is_array($decoded['items'])) ? $decoded['items'] : array();

	$results = array();

	$every_line_answered = (bool)$items;

	foreach ($items as $item) {

		if (!is_array($item)) {

			continue;

		}

		$line = isset($item['status']) ? strtoupper(trim((string)$item['status'])) : '';

		if ($line === '') {

			$every_line_answered = false;

		}

		$request = (isset($item['requestItem']) && is_array($item['requestItem'])) ? $item['requestItem'] : array();

		$barcode = isset($request['barcode']) ? trim((string)$request['barcode']) : '';

		if ($barcode === '' && isset($request['product']['barcode'])) {

			$barcode = trim((string)$request['product']['barcode']);

		}

		if ($barcode === '') {

			continue;

		}

		$error = '';

		if ($line !== 'SUCCESS') {

			$error = mp_trendyol_reason_text(isset($item['failureReasons']) ? $item['failureReasons'] : array());

			if ($error === '') {

				$error = ($line === '')
					? lang('Trendyol finished the batch without a verdict for this product.')
					: lang('Trendyol refused this product without saying why.');

			}

		}

		$results[$barcode] = array(
			'ok'     => ($line === 'SUCCESS'),
			'status' => $line,
			'error'  => $error
		);

	}

	// Without a status of its own, a batch is finished when every line it says
	// it holds has a verdict. A result that lists fewer lines than its own
	// count is still being written, and reading it as finished would have the
	// queue close the lines not yet listed as done.
	if ($status === '' && isset($decoded['itemCount']) && is_numeric($decoded['itemCount'])
		&& count($items) < (int)$decoded['itemCount']) {

		$every_line_answered = false;

	}

	$finished = ($status === 'COMPLETED') || ($status === '' && $every_line_answered);

	return array(
		'finished' => $finished,
		'status'   => ($status !== '') ? $status : ($finished ? 'COMPLETED' : 'IN_PROGRESS'),
		'results'  => $finished ? $results : array()
	);

}

// Their reasons as one line. Seen as strings; read as objects too, because
// the documentation shows the list only empty.
function mp_trendyol_reason_text($reasons) {

	$parts = array();

	foreach ((array)$reasons as $reason) {

		if (is_array($reason)) {

			foreach (array('message', 'reason', 'description', 'key') as $key) {

				if (isset($reason[$key]) && trim((string)$reason[$key]) !== '') {

					$parts[] = trim((string)$reason[$key]);

					break;

				}

			}

		} elseif (trim((string)$reason) !== '') {

			$parts[] = trim((string)$reason);

		}

	}

	return mb_substr(implode('; ', array_unique($parts)), 0, 500);

}
