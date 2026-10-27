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
// tree and what a category demands, open listings and read whether they were
// approved, send the title, description and pictures over approved ones, send
// stock and price, read the result of a batch, read order
// packages (pulled, or delivered by their webhook), accept one (Picking),
// report one shipped with the seller's own carrier, send the link of a
// package's invoice, read the buyers' returns and approve or reject the ones
// that came back, read the buyers' questions and answer them, register or remove
// that webhook, and read the seller's catalogue a page at a time for matching
// it to the shop's products. What it does not
// name in the table below mp_can() answers no for, so nothing queues work this
// connector cannot do.
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
		'update_listings'     => 'mp_trendyol_update_listings',
		'push_stock_price'    => 'mp_trendyol_push_stock_price',
		'read_task'           => 'mp_trendyol_read_task',
		'listing_status'      => 'mp_trendyol_listing_status',
		'pull_orders'         => 'mp_trendyol_pull_orders',
		'order_stage'         => 'mp_trendyol_order_stage',
		'approve_lines'       => 'mp_trendyol_approve_lines',
		'ship_package'        => 'mp_trendyol_ship_package',
		'send_invoice_link'   => 'mp_trendyol_send_invoice_link',
		'claims'              => 'mp_trendyol_claims',
		'approve_claim'       => 'mp_trendyol_approve_claim',
		'claim_issue_reasons' => 'mp_trendyol_claim_issue_reasons',
		'reject_claim'        => 'mp_trendyol_reject_claim',
		'questions'           => 'mp_trendyol_questions',
		'answer_question'     => 'mp_trendyol_answer_question',
		'webhook_packages'    => 'mp_trendyol_webhook_packages',
		'register_webhook'    => 'mp_trendyol_register_webhook',
		'remove_webhook'      => 'mp_trendyol_remove_webhook',
		'remote_products'     => 'mp_trendyol_remote_products'
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
// which case it is a POST of that body as JSON; $method names another verb
// (PUT, DELETE), sent with the body when there is one. A body given as text
// is sent as it is, with $content_type as its type (a multipart upload).
//
// $allow_empty reads a 2xx whose body is empty or not JSON as success, for a
// call whose answer carries nothing but its status. Every other call keeps
// reading such an answer as an error.
function mp_trendyol_call($account, $path, $max_body, $transport = null, $body = null, $method = null, $allow_empty = false, $content_type = 'application/json') {

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

	if (is_string($body)) {

		$headers[] = 'Content-Type: ' . $content_type;

		$options['body'] = $body;

	} elseif ($body !== null) {

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

	if ($method === null) {

		$method = ($body === null) ? 'GET' : 'POST';

	}

	$outcome = call_user_func($transport, strtoupper((string)$method), mp_trendyol_base($account) . $path, $options);

	return mp_trendyol_read($outcome, mp_trendyol_environment($account), $allow_empty);

}

// An answer of api_http_request() as the connector's ok/error shape.
//
// The status code is read before the body for the four answers whose body is
// not worth reading: Trendyol's refusals of the credentials and of the
// User-Agent, its rate limit, and the test environment's 503 for an address it
// does not know. Each of those has one likely cause, and naming the cause is
// more use to the operator than the envelope's exception class.
//
// A 2xx whose body is not JSON is an error unless $allow_empty says the call
// answers with its status alone; then it is success with no data.
function mp_trendyol_read($outcome, $environment = 'production', $allow_empty = false) {

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

		if ($ok && $allow_empty) {

			return mp_ok(array());

		}

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
// the colour) differs between the products of one article just the same - the
// chair whose products are its colours - so both are is_variant: answered by
// each product's own option, and by the article's value only for a product
// that has none.
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
			'is_variant'     => (!empty($entry['varianter']) || !empty($entry['slicer'])) ? 1 : 0,
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

/* ------------------------------------------------------- listing updates */

// Send the shop's title, description and pictures over listings Trendyol has
// approved.
//
// An approved listing is changed through its content id (the mapping's
// remote_id, read by the approval read), not its barcode: their content update
// takes contentId and only the fields it is given, so price, stock, brand,
// category and attributes stay as they are over there. Attributes are left out
// on purpose: a content update that names one has to name all of them, and
// the variant attributes cannot be changed at all.
//
// $items is what mp_listing_update_items() assembled. An item the body cannot
// carry (no title, a picture on http) is left out and named in
// data['refused'] (content id => reason), so one product's fault does not hold
// back the rest. Answers the batch id in data['task_id'], '' when nothing was
// left to send; their batch result is read with read_task() and names each
// verdict by content id.
function mp_trendyol_update_listings($account, $items, $transport = null) {

	$built = mp_trendyol_update_body(array_slice((array)$items, 0, mp_trendyol_listing_batch_size()));

	if (!$built['items']) {

		return mp_ok(array('task_id' => '', 'sent' => 0, 'refused' => $built['refused']));

	}

	$result = mp_trendyol_call($account,
		'/integration/product/sellers/' . mp_trendyol_seller_id($account) . '/products/content-bulk-update',
		65536, $transport, array('items' => $built['items']));

	if (!$result['ok']) {

		return $result;

	}

	$task_id = isset($result['data']['batchRequestId']) ? trim((string)$result['data']['batchRequestId']) : '';

	if ($task_id === '') {

		return mp_error(lang('Trendyol accepted the listing changes but did not return a batch id.'), true);

	}

	return mp_ok(array('task_id' => $task_id, 'sent' => count($built['items']), 'refused' => $built['refused']));

}

// The content update's items and what was left out of them, as
// array('items' => [...], 'refused' => array(content id => reason)).
//
// One item per content id: the variants of one listing share it, and the
// first product given for it speaks for the listing. The same limits as
// opening a listing: a title (cut at 100), a description (refused over 30,000
// rather than cut, which would break its HTML), one to eight pictures on
// https. The content id goes as a JSON number, so one that does not fit in 64
// bits is refused rather than rounded.
function mp_trendyol_update_body($items) {

	$out = array();

	$refused = array();

	foreach ((array)$items as $item) {

		$content_id = isset($item['content_id']) ? trim((string)$item['content_id']) : '';

		$name = isset($item['name']) ? (string)$item['name'] : $content_id;

		if (isset($out[$content_id]) || isset($refused[$content_id])) {

			continue;

		}

		if (!mp_trendyol_id_fits($content_id)) {

			$refused[$content_id] = lang(array(
				'string' => '{var:1} has no Trendyol content number to update. Wait until the listing is approved.',
				'vars'   => array($name)
			));

			continue;

		}

		$title = isset($item['title']) ? trim((string)$item['title']) : '';

		$description = isset($item['description']) ? trim((string)$item['description']) : '';

		$problem = '';

		if ($title === '') {

			$problem = lang(array('string' => '{var:1} has no title.', 'vars' => array($name)));

		} elseif ($description === '') {

			$problem = lang(array(
				'string' => '{var:1} has no description, and Trendyol will not list a product without one.',
				'vars'   => array($name)
			));

		} elseif (mb_strlen($description) > 30000) {

			$problem = lang(array(
				'string' => 'The description of {var:1} is longer than the 30,000 characters Trendyol takes.',
				'vars'   => array($name)
			));

		}

		$images = array();

		foreach ((array)(isset($item['images']) ? $item['images'] : array()) as $url) {

			if ($problem !== '') {

				break;

			}

			if (stripos((string)$url, 'https://') !== 0) {

				$problem = lang(array(
					'string' => 'Trendyol only fetches pictures over https, and the pictures of {var:1} are not. Set the site address to https first.',
					'vars'   => array($name)
				));

			} elseif (count($images) < 8) {

				$images[] = array('url' => (string)$url);

			}

		}

		if ($problem === '' && !$images) {

			$problem = lang(array(
				'string' => '{var:1} has no picture. The marketplace will not list a product without one.',
				'vars'   => array($name)
			));

		}

		if ($problem !== '') {

			$refused[$content_id] = $problem;

			continue;

		}

		$out[$content_id] = array(
			'contentId'   => (int)$content_id,
			'title'       => mb_substr($title, 0, 100),
			'description' => $description,
			'images'      => $images
		);

	}

	return array('items' => array_values($out), 'refused' => $refused);

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

		// A content update names its lines by content id instead (assumed from
		// the request they echo: the documentation shows no such result).
		if ($barcode === '' && isset($request['contentId']) && is_scalar($request['contentId'])) {

			$barcode = trim((string)$request['contentId']);

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

/* ----------------------------------------------------------- listing state */

// The most barcodes their product filters take in one request.
function mp_trendyol_listing_status_size() {

	return 50;

}

// Where each listing stands on Trendyol, by barcode.
//
// A listing created here goes to their approval first: a SUCCESS batch line
// only means it was handed over. Two services say what happened next, both
// filtered by barcode: the approved products (a content per listing, its
// barcodes inside variants[]) and the unapproved ones (one entry per barcode,
// pendingApproval or rejected). With fifty barcodes asked, each answers with
// at most fifty contents or entries, so one page of each is the whole answer.
//
// Answers data['listings'], barcode => a state array: 'approved' (content_id,
// commission, on_sale, locked, lock_reason, archived, blacklisted,
// product_url), 'rejected' or 'pending' (reasons, doc_needed), or 'unknown'
// for a barcode neither service knows - one never listed, one deleted over
// there, or one still on its way through their batch. Barcodes their rules
// refuse are not asked about and are answered in data['refused'] (barcode =>
// reason).
function mp_trendyol_listing_status($account, $barcodes, $transport = null) {

	$barcodes = array_values(array_unique(array_map(function ($barcode) {

		return trim((string)$barcode);

	}, (array)$barcodes)));

	if (count($barcodes) > mp_trendyol_listing_status_size()) {

		return mp_error(lang(array(
			'string' => 'Trendyol is asked about at most {var:1} barcodes at a time.',
			'vars'   => array(mp_trendyol_listing_status_size())
		)));

	}

	$refused = array();

	// Folded to lower case, so that a barcode they spell in other capitals is
	// still the one that was asked about; the answer keeps the spelling asked.
	$wanted = array();

	foreach ($barcodes as $barcode) {

		$problem = mp_trendyol_barcode_problem($barcode);

		if ($problem !== '') {

			$refused[$barcode] = $problem;

			continue;

		}

		$wanted[mb_strtolower($barcode)] = $barcode;

	}

	if (!$wanted) {

		return mp_ok(array('listings' => array(), 'refused' => $refused));

	}

	$query = implode(',', array_map('rawurlencode', array_values($wanted)));

	$path = '/integration/product/sellers/' . mp_trendyol_seller_id($account) . '/products/';

	$approved = mp_trendyol_call($account, $path . 'approved?barcodes=' . $query . '&page=0&size=100', 8388608, $transport);

	if (!$approved['ok']) {

		return $approved;

	}

	$unapproved = mp_trendyol_call($account, $path . 'unapproved?barcodes=' . $query . '&page=0&size=1000', 8388608, $transport);

	if (!$unapproved['ok']) {

		return $unapproved;

	}

	$listings = array();

	foreach ($wanted as $barcode) {

		$listings[$barcode] = array('state' => 'unknown');

	}

	// The approved answer last: a listing on sale is what the buyer sees,
	// whatever change of it may be waiting for approval beside it. Assigned
	// one by one: a barcode of digits is an integer key, which array_merge()
	// would renumber.
	foreach (array(mp_trendyol_unapproved_states($unapproved['data'], $wanted),
		mp_trendyol_approved_states($approved['data'], $wanted)) as $found) {

		foreach ($found as $barcode => $state) {

			$listings[$barcode] = $state;

		}

	}

	return mp_ok(array('listings' => $listings, 'refused' => $refused));

}

// The approved listings among the barcodes in $wanted (lower case => as asked).
function mp_trendyol_approved_states($decoded, $wanted) {

	$states = array();

	$content = (isset($decoded['content']) && is_array($decoded['content'])) ? $decoded['content'] : array();

	foreach ($content as $entry) {

		if (!is_array($entry)) {

			continue;

		}

		$content_id = mp_trendyol_text($entry, array('contentId'));

		$variants = (isset($entry['variants']) && is_array($entry['variants'])) ? $entry['variants'] : array();

		foreach ($variants as $variant) {

			$barcode = is_array($variant) ? mp_trendyol_text($variant, array('barcode')) : '';

			$key = mb_strtolower($barcode);

			if ($barcode === '' || $content_id === '' || !isset($wanted[$key])) {

				continue;

			}

			$states[$wanted[$key]] = array(
				'state'       => 'approved',
				'content_id'  => $content_id,
				'commission'  => (isset($variant['commission']) && is_numeric($variant['commission'])) ? (float)$variant['commission'] : null,
				'on_sale'     => !empty($variant['onSale']),
				'locked'      => !empty($variant['locked']),
				'lock_reason' => mp_trendyol_text($variant, array('lockReason')),
				'archived'    => !empty($variant['archived']),
				'blacklisted' => !empty($variant['blacklisted']),
				'product_url' => mp_trendyol_text($variant, array('productUrl'))
			);

		}

	}

	return $states;

}

// The listings awaiting approval or rejected among the barcodes in $wanted.
// Each reason is written "reason: detail".
function mp_trendyol_unapproved_states($decoded, $wanted) {

	$states = array();

	$content = (isset($decoded['content']) && is_array($decoded['content'])) ? $decoded['content'] : array();

	foreach ($content as $entry) {

		$barcode = is_array($entry) ? mp_trendyol_text($entry, array('barcode')) : '';

		$key = mb_strtolower($barcode);

		if ($barcode === '' || !isset($wanted[$key])) {

			continue;

		}

		$status = strtolower(mp_trendyol_text($entry, array('status')));

		if ($status !== 'rejected' && $status !== 'pendingapproval') {

			continue;

		}

		$reasons = array();

		$details = (isset($entry['rejectReasonDetails']) && is_array($entry['rejectReasonDetails'])) ? $entry['rejectReasonDetails'] : array();

		foreach ($details as $detail) {

			if (!is_array($detail)) {

				continue;

			}

			$parts = array_filter(array(
				mp_trendyol_text($detail, array('rejectReason')),
				mp_trendyol_text($detail, array('rejectReasonDetail'))
			), 'strlen');

			if ($parts) {

				$reasons[] = implode(': ', $parts);

			}

		}

		$states[$wanted[$key]] = array(
			'state'      => ($status === 'rejected') ? 'rejected' : 'pending',
			'reasons'    => $reasons,
			'doc_needed' => !empty($entry['docNeeded'])
		);

	}

	return $states;

}

/* ------------------------------------------------------------------ orders */

// Their order packages modified since a moment.
//
// The package, not the order, is the unit, as on n11: Trendyol splits a
// customer's order into shipment packages, and it is the package that carries a
// status, a carrier and a tracking number. A partial cancellation breaks a
// package: the old one turns Cancelled and what is left comes back under a new
// package id with the same order number, which the importer sees as one order
// cancelled and one new.
//
// The feed is asked in ascending order of modification time, so a package comes
// back every time it changes and the cursor can stop at the last one read.
// Their window is at most two weeks long, so a cursor older than that starts at
// the oldest moment the window still reaches, an hour inside it so that a clock
// here that runs differently from theirs cannot make the window longer than
// they allow. An account paused for longer than two weeks loses the packages
// modified before that; Trendyol keeps a month and its seller panel still
// shows them.
//
// The window starts five minutes before $since, as on n11, and the importer
// refuses a package it has already seen, so the overlap costs nothing. It ends
// now rather than a little ahead: an end in the future is not something their
// documentation says the service takes.
//
// Answers data['read_to'] the way mp_n11_pull_orders() does: the end of the
// window when the feed ran out, the modification time of the last package read
// when the page cap ran out first.
function mp_trendyol_pull_orders($account, $since, $transport = null) {

	$now = time();

	$since = (int) $since;

	if ($since <= 0) {

		// A first pull. A week, as on n11: long enough to catch what is still
		// unshipped without walking the whole history of the store.
		$since = $now - 604800;

	}

	$start = max($since - 300, $now - 1209600 + 3600) * 1000;

	$end = $now * 1000;

	$packages = array();

	// Their ceiling for one page.
	$size = 200;

	// Twenty pages is a pass. Whatever is left is read by the next one, because
	// the cursor only moves as far as what was read.
	$pages = 20;

	// Twenty full pages that all sit inside the five-minute overlap would hand
	// the next pass the same start for ever, so only then does the pull read
	// on past the cap, up to fifty pages' worth of entries new to this pass: the
	// ten thousand packages they answer for one window. A window started again
	// reads the entries of its last moment a second time, so the new entries are
	// counted, not the requests, which are held to three times as many; page 49
	// is still the last a run of pages that all share one moment can ask for.
	$ceiling = 50;

	// The modification time of the last entry read that carried one.
	$reached = 0;

	$more = false;

	// After a full page the window starts again just before the last moment read
	// (mp_feed_next_page()), so the page asked for is mostly 0.
	$page = 0;

	$seen = array();

	// Entries new to this pass, which the ceiling counts.
	$fresh = 0;

	for ($request = 0; ($request < 3 * $ceiling) && ($page < $ceiling) && ($fresh < $ceiling * $size); $request++) {

		// No status filter: an answer filtered on several statuses once came
		// back without the status field, and every status matters here anyway.
		$result = mp_trendyol_call($account,
			'/integration/order/sellers/' . mp_trendyol_seller_id($account) . '/v2/orders'
				. '?startDate=' . $start
				. '&endDate=' . $end
				. '&orderByField=PackageLastModifiedDate'
				. '&orderByDirection=ASC'
				. '&page=' . $page
				. '&size=' . $size,
			4194304, $transport);

		if (!$result['ok']) {

			// Whatever was read before the failure is still worth importing, and
			// the caller is told not to move the cursor past it.
			return mp_error($result['error'], !empty($result['retry']),
				array('packages' => $packages, 'complete' => false));

		}

		$content = (isset($result['data']['content']) && is_array($result['data']['content']))
			? $result['data']['content'] : array();

		$first = 0;

		$last = 0;

		foreach (array_values($content) as $index => $entry) {

			// Read from every entry, the ones the importer is not handed too: a
			// page of packages still awaiting payment has been read all the same,
			// and the next pass must not start before it again.
			$modified = (is_array($entry) && isset($entry['lastModifiedDate']))
				? mp_trendyol_seconds($entry['lastModifiedDate']) : 0;

			if ($modified > 0) {

				$reached = $modified;

			}

			$moment = (is_array($entry) && isset($entry['lastModifiedDate']))
				? mp_feed_moment($entry['lastModifiedDate']) : 0;

			if ($index === 0) {

				$first = $moment;

			}

			$last = $moment;

			// An entry read again at the same moment is not new, whether or not
			// it is one to import.
			$id = is_array($entry) ? mp_trendyol_text($entry, array('shipmentPackageId', 'id')) : '';

			$new = ($id === '') || mp_feed_first_time($seen, $id, $moment);

			if (!$new) {

				continue;

			}

			$fresh++;

			$package = mp_trendyol_package($entry);

			if ($package !== null) {

				$packages[] = $package;

			}

		}

		$more = (count($content) >= $size);

		if (!$more) {

			break;

		}

		if (($request + 1 >= $pages) && ($reached > $since)) {

			break;

		}

		list($start, $page) = mp_feed_next_page($start, $page, $first, $last);

	}

	if (!$more) {

		return mp_ok(array('packages' => $packages, 'complete' => true, 'read_to' => $now));

	}

	if ($reached > $since) {

		return mp_ok(array('packages' => $packages, 'complete' => true, 'read_to' => $reached, 'more' => true));

	}

	// Even the ceiling did not get past the start. The packages are imported
	// and the cursor stays where it was rather than jumping over the rest.
	return mp_ok(array('packages' => $packages, 'complete' => false, 'more' => true));

}

// One of their packages in the shape mp_import_order() reads, or null for an
// entry that is not one to import.
//
// Awaiting and Verified are packages whose payment is not confirmed yet.
// Trendyol says to do nothing with them but count the stock; once the payment
// clears they come back as Created, modified again. Importing one would write
// an order that may never be paid.
//
// Their amounts are decimal lira and are converted to minor units here, once.
// lineGrossAmount is the price of one unit before any discount, VAT inside, so
// the tax handed on is 0, as on n11. The seller's discount is summed from
// discountDetails, one entry per unit, which keeps a discount that does not
// divide by the quantity at its exact amount; the line's lineSellerDiscount is
// an average per unit and only the fallback. Trendyol's own discounts
// (lineTyDiscount, lineItemTyDiscount, packageTyDiscount) are not counted:
// Trendyol pays the seller as if they had not happened, the same as n11's
// mallDiscount, so counting them would understate what the sale earned.
//
// The older field names (id, amount, discount, lineItemDiscount, merchantSku)
// are read where the new ones are missing; Trendyol renamed them and said the
// old ones would go.
function mp_trendyol_package($entry) {

	if (!is_array($entry)) {

		return null;

	}

	$package_id = mp_trendyol_text($entry, array('shipmentPackageId', 'id'));

	if ($package_id === '') {

		return null;

	}

	$status = mp_trendyol_text($entry, array('status', 'shipmentPackageStatus'));

	if ($status === 'Awaiting' || $status === 'Verified') {

		return null;

	}

	$lines = array();

	$raw_lines = (isset($entry['lines']) && is_array($entry['lines'])) ? $entry['lines'] : array();

	foreach ($raw_lines as $line) {

		if (!is_array($line)) {

			continue;

		}

		$quantity = isset($line['quantity']) ? max(1, (int)$line['quantity']) : 1;

		$discount = mp_trendyol_seller_discount($line, $quantity);

		$lines[] = array(
			'remote_line_id' => mp_trendyol_text($line, array('lineId', 'id')),
			// The barcode is what they know a product by, and what the mapping
			// row of this provider stores; the stock code is the code the
			// listing was sent with, which is the product's name here.
			'code'           => mp_trendyol_text($line, array('barcode')),
			'sku'            => mp_trendyol_text($line, array('stockCode', 'merchantSku')),
			'name'           => mp_trendyol_text($line, array('productName')),
			'quantity'       => $quantity,
			'price'          => mp_trendyol_minor(mp_trendyol_first($line, array('lineGrossAmount', 'amount'))),
			'tax'            => 0,
			'discount'       => (int) round($discount / $quantity),
			'discount_total' => $discount,
			'status'         => mp_trendyol_text($line, array('orderLineItemStatusName'))
		);

	}

	$shipping = mp_trendyol_address($entry, 'shipmentAddress');

	$billing = mp_trendyol_address($entry, 'invoiceAddress');

	$first = mp_trendyol_text($entry, array('customerFirstName'));

	$last = mp_trendyol_text($entry, array('customerLastName'));

	if ($first === '' && $last === '') {

		// One name only: the surname is its last word, as on n11.
		$name = isset($shipping['name']) ? $shipping['name'] : '';

		$space = ($name !== '') ? strrpos($name, ' ') : false;

		$first = ($space === false) ? $name : trim(substr($name, 0, $space));

		$last = ($space === false) ? '' : trim(substr($name, $space + 1));

	}

	$phone = isset($shipping['phone']) ? $shipping['phone'] : '';

	if ($phone === '' && isset($billing['phone'])) {

		$phone = $billing['phone'];

	}

	$order_number = mp_trendyol_text($entry, array('orderNumber'));

	$currency = strtoupper(mp_trendyol_text($entry, array('currencyCode')));

	return array(
		'remote_order_id'   => ($order_number !== '') ? $order_number : $package_id,
		'remote_package_id' => $package_id,
		'remote_status'     => $status,
		// The modification time, not the order date: the map row keeps it as
		// the package's timestamp, and "Try again" moves the pull cursor back to
		// it, so it has to be on the scale the feed is asked on.
		'placed_at'         => isset($entry['lastModifiedDate']) ? mp_trendyol_seconds($entry['lastModifiedDate']) : 0,
		'currency'          => ($currency !== '') ? $currency : 'TRY',
		'customer' => array(
			'first_name' => $first,
			'last_name'  => $last,
			'email'      => mp_trendyol_text($entry, array('customerEmail')),
			'phone'      => $phone,
			// A company only on a corporate order; otherwise the field is
			// whatever the buyer typed into an address.
			'company'    => (!empty($entry['commercial']) && isset($billing['company'])) ? $billing['company'] : ''
		),
		'billing'          => $billing,
		'shipping_address' => $shipping,
		'shipping_cost'    => 0,
		// The carrier and the number are Trendyol's and arrive with the package.
		'tracking' => array(
			'number'  => mp_trendyol_text($entry, array('cargoTrackingNumber')),
			'carrier' => mp_trendyol_text($entry, array('cargoProviderName')),
			'link'    => mp_trendyol_text($entry, array('cargoTrackingLink'))
		),
		'lines'            => $lines
	);

}

// The seller's discount on one line, in minor units, for the whole line.
//
// Summed from discountDetails, one entry per unit. A line without them falls
// back to lineSellerDiscount, an average per unit, times the quantity.
function mp_trendyol_seller_discount($line, $quantity) {

	$details = (isset($line['discountDetails']) && is_array($line['discountDetails'])) ? $line['discountDetails'] : array();

	if ($details) {

		$total = 0;

		foreach ($details as $detail) {

			if (is_array($detail)) {

				$total += mp_trendyol_minor(mp_trendyol_first($detail, array('lineItemSellerDiscount', 'lineItemDiscount')));

			}

		}

		return $total;

	}

	return mp_trendyol_minor(mp_trendyol_first($line, array('lineSellerDiscount', 'discount'))) * $quantity;

}

// One of their addresses in the importer's shape.
//
// address2 is often a copy of address1, and is then replaced by the
// neighbourhood, which their address1 does not always name.
function mp_trendyol_address($entry, $key) {

	$address = (isset($entry[$key]) && is_array($entry[$key])) ? $entry[$key] : array();

	if (!$address) {

		return array();

	}

	$name = mp_trendyol_text($address, array('fullName'));

	if ($name === '') {

		$name = trim(mp_trendyol_text($address, array('firstName')) . ' ' . mp_trendyol_text($address, array('lastName')));

	}

	$line_1 = mp_trendyol_text($address, array('address1', 'fullAddress'));

	$line_2 = mp_trendyol_text($address, array('address2'));

	if ($line_2 === '' || $line_2 === $line_1) {

		$line_2 = mp_trendyol_text($address, array('neighborhood'));

	}

	return array(
		'name'      => $name,
		'company'   => mp_trendyol_text($address, array('company')),
		'address_1' => $line_1,
		'address_2' => $line_2,
		'city'      => mp_trendyol_text($address, array('city')),
		'state'     => mp_trendyol_text($address, array('district')),
		'zip'       => mp_trendyol_text($address, array('postalCode')),
		'country'   => mp_trendyol_text($address, array('countryCode')),
		'phone'     => mp_trendyol_text($address, array('phone'))
	);

}

// The value of the first of $keys that $row has, or null.
function mp_trendyol_first($row, $keys) {

	foreach ($keys as $key) {

		if (isset($row[$key])) {

			return $row[$key];

		}

	}

	return null;

}

// The first of $keys that holds some text, trimmed, or ''. Their ids and the
// tracking number are JSON numbers and come out as digits.
function mp_trendyol_text($row, $keys) {

	foreach ($keys as $key) {

		if (isset($row[$key]) && is_scalar($row[$key]) && !is_bool($row[$key])) {

			$value = trim((string)$row[$key]);

			if ($value !== '') {

				return $value;

			}

		}

	}

	return '';

}

// A decimal amount of theirs (199.90) as minor units.
function mp_trendyol_minor($value) {

	return is_numeric($value) ? (int) round(((float)$value) * 100) : 0;

}

// Their millisecond timestamp as unix seconds.
function mp_trendyol_seconds($value) {

	return is_numeric($value) ? (int) round(((float)$value) / 1000) : 0;

}

// What their package status means here.
//
// A dozen of theirs against four of ours. Only three answers matter to the
// shop: it has gone, it has arrived, or it is not going to happen.
function mp_trendyol_order_stage($remote_status) {

	switch ((string)$remote_status) {

		// With the carrier, at a collection point, or on the way back after a
		// delivery that failed: the goods have left either way, and a parcel
		// that comes back is a return, not a cancellation.
		case 'Shipped':
		case 'AtCollectionPoint':
		case 'UnDelivered':
		case 'Returned':
			return 'shipped';

		case 'Delivered':
			return 'delivered';

		// Not going out under this package. UnPacked is a package that was
		// split: its goods now belong to new packages, which are imported as
		// orders of their own and take the stock again, so the old order gives
		// its stock back or the same goods are taken twice.
		case 'Cancelled':
		case 'UnSupplied':
		case 'UnPacked':
			return 'cancelled';

	}

	// Created, Picking, Invoiced - still the seller's to prepare. Awaiting and
	// Verified never reach the importer.
	return 'open';

}

// Whether a package or line id may go into a status call: one to eighteen
// digits, no leading zero.
//
// A line id is sent as a JSON number, and (int) of a longer string can pass
// PHP_INT_MAX (nineteen digits on a 64-bit build), where it saturates and a
// different line id would be sent; the package id is held to the same length,
// theirs being ten digits today. A leading zero is dropped by (int), so
// "0123" would go out as line 123, and a package id of "0" or "0123" names a
// package Trendyol never gave out.
function mp_trendyol_id_fits($id) {

	return is_string($id) && (preg_match('/^[1-9][0-9]{0,17}$/', $id) === 1);

}

// Accept a package: Created becomes Picking.
//
// Their status service takes the package id in the path and each line with its
// quantity, so the package the lines belong to is needed as well as their ids.
// Only Picking and Invoiced can be sent; everything after that is set by
// Trendyol and its carrier. Accepting is a commitment to ship, so the caller
// decides whether to do it; this only carries it out.
//
// Their answer to a successful update has no documented body, so any 2xx is
// success here, whatever it carries.
//
// Ids are checked with mp_trendyol_id_fits().
function mp_trendyol_approve_lines($account, $line_ids, $package = array(), $transport = null) {

	$package_id = isset($package['remote_package_id']) ? trim((string)$package['remote_package_id']) : '';

	if (!mp_trendyol_id_fits($package_id)) {

		return mp_error(lang('That order has no package number to accept.'));

	}

	$wanted = array();

	foreach ((array)$line_ids as $line_id) {

		$wanted[trim((string)$line_id)] = true;

	}

	$lines = array();

	foreach ((array)(isset($package['lines']) ? $package['lines'] : array()) as $line) {

		$line_id = isset($line['remote_line_id']) ? trim((string)$line['remote_line_id']) : '';

		if (!isset($wanted[$line_id]) || !mp_trendyol_id_fits($line_id)) {

			continue;

		}

		$lines[] = array(
			'lineId'   => (int)$line_id,
			'quantity' => isset($line['quantity']) ? max(1, (int)$line['quantity']) : 1
		);

	}

	if (!$lines) {

		return mp_error(lang('That order has no lines to accept.'));

	}

	// params is an object on their side; an empty PHP array would be sent as
	// [] instead of {}.
	$result = mp_trendyol_call($account,
		'/integration/order/sellers/' . mp_trendyol_seller_id($account) . '/shipment-packages/' . $package_id,
		65536, $transport,
		array('lines' => $lines, 'params' => new stdClass(), 'status' => 'Picking'),
		'PUT', true);

	if (!$result['ok']) {

		return $result;

	}

	return mp_ok(array('accepted' => count($lines)));

}

// Report a package shipped with the seller's own carrier.
//
// Their alternative delivery service takes the carrier's tracking page, not a
// number: the buyer follows that link from the order on Trendyol. The package
// turns Shipped by itself, and Trendyol then gives it a cargo number of its
// own, which the next pull brings in. (The same service takes a phone number
// instead of a link, for a courier the buyer calls; there is no such number
// here to give.) Their answer to a successful call has no documented body, so
// any 2xx is success.
//
// The package id is checked with mp_trendyol_id_fits(), as for accepting a
// package.
function mp_trendyol_ship_package($account, $package, $tracking_url, $transport = null) {

	$package_id = isset($package['remote_package_id']) ? trim((string)$package['remote_package_id']) : '';

	if (!mp_trendyol_id_fits($package_id)) {

		return mp_error(lang('That order has no package number to ship.'));

	}

	$tracking_url = trim((string)$tracking_url);

	if (preg_match('#^https?://[^\s/?\#]+\S*$#i', $tracking_url) !== 1) {

		return mp_error(lang('Trendyol needs the carrier\'s tracking page, an address that starts with http:// or https://, not a tracking number.'));

	}

	// params is an object on their side; an empty PHP array would be sent as
	// [] instead of {}.
	$result = mp_trendyol_call($account,
		'/integration/order/sellers/' . mp_trendyol_seller_id($account) . '/shipment-packages/' . $package_id . '/alternative-delivery',
		65536, $transport,
		array('isPhoneNumber' => false, 'trackingInfo' => $tracking_url, 'params' => new stdClass()),
		'PUT', true);

	if (!$result['ok']) {

		return $result;

	}

	return mp_ok(array('shipped' => true));

}

/* ---------------------------------------------------------------- invoices */

// What is wrong with an invoice link before it is sent, or ''.
//
// Trendyol shows the link to the buyer as the package's invoice and asks that
// it stays reachable for ten years, so it has to be a web address of the
// document itself: http or https, a host, no spaces. The length cap is ours;
// their documentation names none, and an address longer than this is a pasted
// panel session rather than a document link.
function mp_trendyol_invoice_link_problem($url) {

	$url = is_scalar($url) ? trim((string)$url) : '';

	if ($url === '') {

		return lang('Type the address of the invoice (the e-Archive or e-Invoice PDF) first.');

	}

	if (strlen($url) > 1000 || preg_match('#^https?://[^\s/?\#@]+(?:[/?\#]\S*)?$#i', $url) !== 1) {

		return lang('The invoice link has to be a web address that starts with http:// or https:// and opens the invoice itself, without spaces.');

	}

	return '';

}

// The body of their seller invoice link call. The package id is a JSON
// number: it was checked with mp_trendyol_id_fits(), so it fits in 64 bits.
// The invoice number and date are left out: they are required only for micro
// export packages, which this shop's imports do not carry.
function mp_trendyol_invoice_link_body($package_id, $url) {

	return array(
		'invoiceLink'       => trim((string)$url),
		'shipmentPackageId' => (int)$package_id
	);

}

// Send the address of a package's invoice, which Trendyol then shows to the
// buyer.
//
// POST /integration/sellers/{sellerId}/seller-invoice-links answers 201 with
// no documented body, so any 2xx is success. Their 409 means the package holds
// an invoice already or the link was given to another package; their error
// body here is {error, message, status, ...}, which the shared reader does not
// take a message from, so the status is watched on its way through and that
// message is read here. There is no call to replace a link: removing one needs
// the buyer's customer id, which the import does not keep.
function mp_trendyol_send_invoice_link($account, $package, $url, $transport = null) {

	$package_id = isset($package['remote_package_id']) ? trim((string)$package['remote_package_id']) : '';

	if (!mp_trendyol_id_fits($package_id)) {

		return mp_error(lang('That order has no package number to send an invoice for.'));

	}

	$problem = mp_trendyol_invoice_link_problem($url);

	if ($problem !== '') {

		return mp_error($problem);

	}

	$send = ($transport === null) ? 'api_http_request' : $transport;

	$status = 0;

	$watched = function ($method, $address, $options) use ($send, &$status) {

		$outcome = call_user_func($send, $method, $address, $options);

		$status = isset($outcome['status']) ? (int)$outcome['status'] : 0;

		return $outcome;

	};

	$result = mp_trendyol_call($account,
		'/integration/sellers/' . mp_trendyol_seller_id($account) . '/seller-invoice-links',
		65536, $watched, mp_trendyol_invoice_link_body($package_id, $url), 'POST', true);

	if ($result['ok']) {

		return mp_ok(array('sent' => true));

	}

	if ($status === 409) {

		return mp_error(lang('Trendyol already holds an invoice for this package, or this link was already sent for another package. Check the order in your Trendyol seller panel.'));

	}

	$message = (isset($result['data']['message']) && is_scalar($result['data']['message']))
		? trim((string)$result['data']['message']) : '';

	if ($message !== '' && $status >= 400 && $status < 500) {

		return mp_error(mb_substr($message, 0, 500), false, $result['data']);

	}

	return $result;

}

/* ----------------------------------------------------------------- returns */

// How many returns one page asks for, and how many pages one read takes.
// Their ceiling for a page is 200; four pages of fifty keep what the screen
// draws, and what the session keeps of it, to two hundred returns.
function mp_trendyol_claim_page_size() {

	return 50;

}

function mp_trendyol_claim_pages() {

	return 4;

}

// A return or return unit id as it goes into a path or a body, or false.
// Theirs are UUIDs, written as text; the documentation types the path's claim
// id as a string without a pattern, so letters, digits and inner dashes are
// let through and nothing that could leave the path segment.
function mp_trendyol_claim_id_fits($id) {

	return is_string($id) && (preg_match('/^[A-Za-z0-9][A-Za-z0-9-]{0,63}$/', $id) === 1);

}

// The returns opened or changed in the last fourteen days, every status.
//
// GET /integration/order/sellers/{sellerId}/claims, paged like the order feed.
// The window is the order feed's: two weeks, an hour inside it. The read stops
// at the last page their totalPages names, at a short page, or after
// mp_trendyol_claim_pages() pages, when data['more'] says the list is not all
// of them. A page that fails makes the whole read an error: a list missing
// its later pages would look complete.
//
// Answers data['claims'] in the shape of mp_trendyol_claim_rows().
function mp_trendyol_claims($account, $transport = null, $now = null) {

	$now = ($now === null) ? time() : (int)$now;

	$start = ($now - 1209600 + 3600) * 1000;

	$end = $now * 1000;

	$size = mp_trendyol_claim_page_size();

	$claims = array();

	$more = false;

	for ($page = 0; $page < mp_trendyol_claim_pages(); $page++) {

		$result = mp_trendyol_call($account,
			'/integration/order/sellers/' . mp_trendyol_seller_id($account) . '/claims'
				. '?startDate=' . $start
				. '&endDate=' . $end
				. '&page=' . $page
				. '&size=' . $size,
			4194304, $transport);

		if (!$result['ok']) {

			return $result;

		}

		$content = (isset($result['data']['content']) && is_array($result['data']['content']))
			? $result['data']['content'] : array();

		$claims = array_merge($claims, mp_trendyol_claim_rows($result['data']));

		$total_pages = (isset($result['data']['totalPages']) && is_numeric($result['data']['totalPages']))
			? (int)$result['data']['totalPages'] : -1;

		$more = (count($content) >= $size) && (($total_pages < 0) || ($page + 1 < $total_pages));

		if (!$more) {

			break;

		}

	}

	return mp_ok(array('claims' => $claims, 'more' => $more));

}

// Their page of returns as a list of
// {claim_id, order_number, package_id, claimed, customer, carrier, tracking,
// units: [{id, barcode, product, price, status, waiting, reason, note}]}.
//
// A unit is one piece the buyer sends back: a line of two shirts returned is
// two units, each with its own id, status and reason, and it is units that are
// approved. Only a unit WaitingInAction is waiting for the seller and can be
// approved: one Created is still on its way back to the warehouse, one
// Accepted, Rejected or Cancelled is settled. A return without a usable id, or without a unit, is left out, as
// is a unit whose id could not be sent back to them. The price is the line's
// unit price in minor units, VAT inside.
//
// cargoTrackingNumber is typed as a 64-bit number but their own example
// overflows it, so json_decode() makes a float of it; the sender number, which
// is text, is read instead.
function mp_trendyol_claim_rows($decoded) {

	$content = (is_array($decoded) && isset($decoded['content']) && is_array($decoded['content']))
		? $decoded['content'] : array();

	$rows = array();

	foreach ($content as $entry) {

		if (!is_array($entry)) {

			continue;

		}

		$claim_id = mp_trendyol_text($entry, array('claimId', 'id'));

		if (!mp_trendyol_claim_id_fits($claim_id)) {

			continue;

		}

		$units = array();

		foreach ((isset($entry['items']) && is_array($entry['items'])) ? $entry['items'] : array() as $item) {

			if (!is_array($item)) {

				continue;

			}

			$line = (isset($item['orderLine']) && is_array($item['orderLine'])) ? $item['orderLine'] : array();

			$product = mp_trendyol_text($line, array('productName'));

			foreach ((isset($item['claimItems']) && is_array($item['claimItems'])) ? $item['claimItems'] : array() as $unit) {

				$unit_id = is_array($unit) ? mp_trendyol_text($unit, array('id')) : '';

				if (!mp_trendyol_claim_id_fits($unit_id)) {

					continue;

				}

				$reason = '';

				foreach (array('customerClaimItemReason', 'trendyolClaimItemReason') as $key) {

					if (($reason === '') && isset($unit[$key]) && is_array($unit[$key])) {

						$reason = mp_trendyol_text($unit[$key], array('name', 'code'));

					}

				}

				$status = (isset($unit['claimItemStatus']) && is_array($unit['claimItemStatus']))
					? mp_trendyol_text($unit['claimItemStatus'], array('name')) : '';

				$units[] = array(
					'id'      => $unit_id,
					'barcode' => mp_trendyol_text($line, array('barcode')),
					'product' => mb_substr($product, 0, 200),
					'price'   => isset($line['price']) ? mp_trendyol_minor($line['price']) : 0,
					'status'  => $status,
					'waiting' => ($status === 'WaitingInAction'),
					'reason'  => mb_substr($reason, 0, 200),
					'note'    => mb_substr(mp_trendyol_text($unit, array('customerNote')), 0, 500)
				);

			}

		}

		if (!$units) {

			continue;

		}

		$rows[] = array(
			'claim_id'     => $claim_id,
			'order_number' => mp_trendyol_text($entry, array('orderNumber')),
			'package_id'   => mp_trendyol_text($entry, array('orderShipmentPackageId')),
			'claimed'      => isset($entry['claimDate']) ? mp_trendyol_seconds($entry['claimDate']) : 0,
			'customer'     => trim(mp_trendyol_text($entry, array('customerFirstName')) . ' '
				. mp_trendyol_text($entry, array('customerLastName'))),
			'carrier'      => mp_trendyol_text($entry, array('cargoProviderName')),
			'tracking'     => (isset($entry['cargoSenderNumber']) && is_string($entry['cargoSenderNumber']))
				? trim($entry['cargoSenderNumber']) : '',
			'units'        => $units
		);

	}

	return $rows;

}

// Approve units of a return that came back to the warehouse: the buyer is
// refunded. PUT .../claims/{claimId}/items/approve with
// {"claimLineItemIdList": [...]}; their optional params object is left out.
// A successful answer has no documented body, so any 2xx is success.
//
// The caller decides which units: the screen sends the waiting units of the
// return as it read them, never ids posted by the browser.
function mp_trendyol_approve_claim($account, $claim_id, $unit_ids, $transport = null) {

	if (!mp_trendyol_claim_id_fits($claim_id)) {

		return mp_error(lang('That return has no number to approve.'));

	}

	$ids = array();

	foreach (is_array($unit_ids) ? $unit_ids : array() as $unit_id) {

		if (!mp_trendyol_claim_id_fits($unit_id)) {

			return mp_error(lang('That return has no units waiting for approval.'));

		}

		$ids[$unit_id] = true;

	}

	if (!$ids) {

		return mp_error(lang('That return has no units waiting for approval.'));

	}

	$result = mp_trendyol_call($account,
		'/integration/order/sellers/' . mp_trendyol_seller_id($account) . '/claims/' . $claim_id . '/items/approve',
		65536, $transport, array('claimLineItemIdList' => array_keys($ids)), 'PUT', true);

	if (!$result['ok']) {

		return $result;

	}

	return mp_ok(array('approved' => count($ids)));

}

/* ---------------------------------------------------------- return rejection */

// The reasons a return can be rejected with, as their claimIssueReasonId.
//
// GET /integration/order/claim-issue-reasons, not seller-scoped: a list of
// {id, name}, the name in Turkish. They answer the reasons of the Turkish and
// the global marketplace together, and the list does not say which is which,
// so every reason is offered and a wrong one is refused by them.
//
// Answers data['reasons'] in the shape of mp_trendyol_claim_reason_rows().
function mp_trendyol_claim_issue_reasons($account, $transport = null) {

	$result = mp_trendyol_call($account, '/integration/order/claim-issue-reasons', 262144, $transport);

	if (!$result['ok']) {

		return $result;

	}

	return mp_ok(array('reasons' => mp_trendyol_claim_reason_rows($result['data'])));

}

// Their list of reasons as [{id, name, file}], in their order; file says
// whether a rejection with that reason has to carry a document. A reason
// whose id could not be sent back, or without a name, is left out.
function mp_trendyol_claim_reason_rows($decoded) {

	$rows = array();

	foreach (is_array($decoded) ? $decoded : array() as $entry) {

		if (!is_array($entry)) {

			continue;

		}

		$id = mp_trendyol_text($entry, array('id'));

		$name = mb_substr(mp_trendyol_text($entry, array('name')), 0, 200);

		if (!mp_trendyol_id_fits($id) || ($name === '') || isset($rows[$id])) {

			continue;

		}

		$rows[$id] = array('id' => $id, 'name' => $name, 'file' => mp_trendyol_claim_reason_needs_file($id));

	}

	return array_values($rows);

}

// Whether a rejection with this reason has to carry a document. Their guide
// names the three that do not: the return never arrived (1651), the product
// goes to analysis (451), and an exchange the buyer asked for in a question
// (2101). Every other reason needs at least one file.
function mp_trendyol_claim_reason_needs_file($reason_id) {

	return !in_array((string)$reason_id, array('451', '1651', '2101'), true);

}

// The documents a rejection may carry: the extensions their guide names, with
// the type each is sent as, at most mp_trendyol_claim_file_bytes() each
// (their limit) and mp_trendyol_claim_file_count() in all (this screen's, so
// one request stays within what a PHP server usually takes in a post).
function mp_trendyol_claim_file_types() {

	return array(
		'jpg'  => 'image/jpeg',
		'jpeg' => 'image/jpeg',
		'png'  => 'image/png',
		'pdf'  => 'application/pdf',
		'tif'  => 'image/tiff',
		'tiff' => 'image/tiff',
		'doc'  => 'application/msword',
		'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
		'mp4'  => 'video/mp4',
		'mov'  => 'video/quicktime'
	);

}

function mp_trendyol_claim_file_bytes() {

	return 5242880;

}

function mp_trendyol_claim_file_count() {

	return 5;

}

// What stops a rejection from being sent, in words for the operator, or ''.
// $files is a list of {name, data}: the name the operator's file had, and its
// bytes.
function mp_trendyol_claim_reject_problem($reason_id, $description, $files) {

	if (!mp_trendyol_id_fits((string)$reason_id)) {

		return lang('Choose why the return is rejected.');

	}

	$description = is_string($description) ? trim($description) : '';

	if ($description === '') {

		return lang('Write why the return is rejected.');

	}

	if (!mb_check_encoding($description, 'UTF-8') || (mb_strlen($description) > 500)) {

		return lang('The explanation can be at most 500 characters.');

	}

	$files = is_array($files) ? $files : array();

	if (count($files) > mp_trendyol_claim_file_count()) {

		return lang(array(
			'string' => 'At most {var:1} files can be attached.',
			'vars'   => array(mp_trendyol_claim_file_count())
		));

	}

	$types = mp_trendyol_claim_file_types();

	foreach ($files as $file) {

		$name = (is_array($file) && isset($file['name']) && is_string($file['name'])) ? $file['name'] : '';

		$data = (is_array($file) && isset($file['data']) && is_string($file['data'])) ? $file['data'] : '';

		$extension = strtolower((string)substr(strrchr($name, '.'), 1));

		if (!isset($types[$extension])) {

			return lang(array(
				'string' => '{var:1} is not a file type the marketplace takes: send a picture, PDF, Word document or video.',
				'vars'   => array($name)
			));

		}

		if (($data === '') || (strlen($data) > mp_trendyol_claim_file_bytes())) {

			return lang(array(
				'string' => '{var:1} is empty or larger than 5 MB.',
				'vars'   => array($name)
			));

		}

	}

	if (!$files && mp_trendyol_claim_reason_needs_file($reason_id)) {

		return lang('This reason needs a document: attach a photo, video or report of what came back.');

	}

	return '';

}

// The files of a rejection as a multipart/form-data body, each a part named
// "files" as their guide asks. The name sent is the operator's, without the
// characters that would end the header line or the quoted name.
function mp_trendyol_claim_multipart($files, $boundary) {

	$types = mp_trendyol_claim_file_types();

	$body = '';

	foreach ($files as $file) {

		$name = str_replace(array('"', '\\'), '', preg_replace('/[\x00-\x1F\x7F]/', '', (string)$file['name']));

		$extension = strtolower((string)substr(strrchr($name, '.'), 1));

		$body .= '--' . $boundary . "\r\n"
			. 'Content-Disposition: form-data; name="files"; filename="' . $name . '"' . "\r\n"
			. 'Content-Type: ' . (isset($types[$extension]) ? $types[$extension] : 'application/octet-stream') . "\r\n"
			. "\r\n"
			. $file['data'] . "\r\n";

	}

	return $body . '--' . $boundary . "--\r\n";

}

// Reject units of a return that came back to the warehouse: this opens a
// case that Trendyol decides on, with the reason and the documents sent.
//
// POST /integration/order/sellers/{sellerId}/claims/{claimId}/issue. Their
// guide (modified 2026-10-14) puts claimIssueReasonId, claimItemIdList (the
// unit ids, comma separated) and description in the query and the documents
// in a multipart body as "files"; the API reference (2026-01-28) lists the
// three fields in the multipart body instead. The guide's form is sent, the
// fields once only: a field both in the query and in the body could be read
// as two values. A successful answer has no body worth reading.
//
// Only units WaitingInAction can be rejected; the caller sends units of the
// return as it read them, never ids the browser made up.
function mp_trendyol_reject_claim($account, $claim_id, $unit_ids, $reason_id, $description, $files, $transport = null) {

	if (!mp_trendyol_claim_id_fits($claim_id)) {

		return mp_error(lang('That return has no number to reject.'));

	}

	$ids = array();

	foreach (is_array($unit_ids) ? $unit_ids : array() as $unit_id) {

		if (!mp_trendyol_claim_id_fits($unit_id)) {

			return mp_error(lang('Choose the units to reject.'));

		}

		$ids[$unit_id] = true;

	}

	if (!$ids) {

		return mp_error(lang('Choose the units to reject.'));

	}

	$files = is_array($files) ? array_values($files) : array();

	$problem = mp_trendyol_claim_reject_problem($reason_id, $description, $files);

	if ($problem !== '') {

		return mp_error($problem);

	}

	$boundary = 'pg' . bin2hex(random_bytes(16));

	$result = mp_trendyol_call($account,
		'/integration/order/sellers/' . mp_trendyol_seller_id($account) . '/claims/' . $claim_id . '/issue'
			. '?claimIssueReasonId=' . (string)$reason_id
			. '&claimItemIdList=' . implode(',', array_keys($ids))
			. '&description=' . rawurlencode(trim($description)),
		65536, $transport, mp_trendyol_claim_multipart($files, $boundary), 'POST', true,
		'multipart/form-data; boundary=' . $boundary);

	if (!$result['ok']) {

		return $result;

	}

	return mp_ok(array('rejected' => count($ids)));

}

/* --------------------------------------------------------------- questions */

// How many questions one page asks for, and how many pages one read takes.
// Their reference allows 200 a page, their guide 50; the smaller is asked.
function mp_trendyol_question_page_size() {

	return 50;

}

function mp_trendyol_question_pages() {

	return 4;

}

// The buyers' questions asked in the last two weeks, every status, newest
// first.
//
// GET /integration/qna/sellers/{sellerId}/questions/filter. Their window is
// at most two weeks (a longer one is cut to two weeks from its start), so the
// window is the order feed's: two weeks, an hour inside it. The read stops as
// the returns read does (mp_trendyol_claims()), and a page that fails makes
// the whole read an error.
//
// Answers data['questions'] in the shape of mp_trendyol_question_rows().
function mp_trendyol_questions($account, $transport = null, $now = null) {

	$now = ($now === null) ? time() : (int)$now;

	$start = ($now - 1209600 + 3600) * 1000;

	$end = $now * 1000;

	$size = mp_trendyol_question_page_size();

	$questions = array();

	$more = false;

	for ($page = 0; $page < mp_trendyol_question_pages(); $page++) {

		$result = mp_trendyol_call($account,
			'/integration/qna/sellers/' . mp_trendyol_seller_id($account) . '/questions/filter'
				. '?startDate=' . $start
				. '&endDate=' . $end
				. '&orderByField=CreatedDate'
				. '&orderByDirection=DESC'
				. '&page=' . $page
				. '&size=' . $size,
			4194304, $transport);

		if (!$result['ok']) {

			return $result;

		}

		$content = (isset($result['data']['content']) && is_array($result['data']['content']))
			? $result['data']['content'] : array();

		$questions = array_merge($questions, mp_trendyol_question_rows($result['data']));

		$total_pages = (isset($result['data']['totalPages']) && is_numeric($result['data']['totalPages']))
			? (int)$result['data']['totalPages'] : -1;

		$more = (count($content) >= $size) && (($total_pages < 0) || ($page + 1 < $total_pages));

		if (!$more) {

			break;

		}

	}

	return mp_ok(array('questions' => $questions, 'more' => $more));

}

// Their page of questions as a list of
// {id, text, status, waiting, asked, user, product, product_url, answer,
// reason}.
//
// Only a question WAITING_FOR_ANSWER can be answered. The answer shown is the
// active one, else the last rejected one, whose rejection reason is in
// reason. The buyer's name is kept only where they chose to show it (it is
// masked by Trendyol either way). The product link is kept only when it is an
// https address, because the screen draws it as a link. A question without a
// usable id or without text is left out.
function mp_trendyol_question_rows($decoded) {

	$content = (is_array($decoded) && isset($decoded['content']) && is_array($decoded['content']))
		? $decoded['content'] : array();

	$rows = array();

	foreach ($content as $entry) {

		if (!is_array($entry)) {

			continue;

		}

		$id = mp_trendyol_text($entry, array('id'));

		$text = mp_trendyol_text($entry, array('text'));

		if (!mp_trendyol_id_fits($id) || ($text === '')) {

			continue;

		}

		$status = mp_trendyol_text($entry, array('status'));

		$answer = '';

		foreach (array('answer', 'rejectedAnswer') as $key) {

			if (($answer === '') && isset($entry[$key]) && is_array($entry[$key])) {

				$answer = mp_trendyol_text($entry[$key], array('text'));

			}

		}

		$url = mp_trendyol_text($entry, array('webUrl'));

		$rows[] = array(
			'id'          => $id,
			'text'        => mb_substr($text, 0, 2000),
			'status'      => $status,
			'waiting'     => ($status === 'WAITING_FOR_ANSWER'),
			'asked'       => isset($entry['creationDate']) ? mp_trendyol_seconds($entry['creationDate']) : 0,
			'user'        => (isset($entry['showUserName']) && ($entry['showUserName'] === false))
				? '' : mb_substr(mp_trendyol_text($entry, array('userName')), 0, 100),
			'product'     => mb_substr(mp_trendyol_text($entry, array('productName')), 0, 200),
			'product_url' => ((strlen($url) <= 1000) && (preg_match('#^https://[^\s/?\#@]+(?:[/?\#]\S*)?$#i', $url) === 1))
				? $url : '',
			'answer'      => mb_substr($answer, 0, 2000),
			'reason'      => mb_substr(mp_trendyol_text($entry, array('reason')), 0, 500)
		);

	}

	return $rows;

}

// What is wrong with an answer before it is sent, or ''. Their limits are
// 10 to 2000 characters.
function mp_trendyol_answer_problem($text) {

	$length = is_string($text) ? mb_strlen(trim($text)) : 0;

	if ($length < 10) {

		return lang('Write an answer of at least 10 characters.');

	}

	if ($length > 2000) {

		return lang('An answer can be at most 2000 characters long.');

	}

	return '';

}

// Answer a buyer's question. POST .../questions/{id}/answers with {"text"};
// Trendyol reviews the answer before it is published. A successful answer is
// a bare JSON string, which the reader takes as success with no data.
function mp_trendyol_answer_question($account, $question_id, $text, $transport = null) {

	$question_id = is_scalar($question_id) ? trim((string)$question_id) : '';

	if (!mp_trendyol_id_fits($question_id)) {

		return mp_error(lang('That question has no number to answer.'));

	}

	$problem = mp_trendyol_answer_problem($text);

	if ($problem !== '') {

		return mp_error($problem);

	}

	$result = mp_trendyol_call($account,
		'/integration/qna/sellers/' . mp_trendyol_seller_id($account) . '/questions/' . $question_id . '/answers',
		65536, $transport, array('text' => trim($text)), 'POST', true);

	if (!$result['ok']) {

		return $result;

	}

	return mp_ok(array('answered' => true));

}

/* ---------------------------------------------------------------- webhooks */

// The packages of one webhook delivery, in the importer's shape.
//
// Their webhook posts the same page the order feed answers with -
// {totalElements, totalPages, page, size, content: [package]} - so each entry
// goes through mp_trendyol_package() exactly as a pulled one does, and the
// packages it skips (Awaiting, Verified) are skipped here too. A body that is
// one bare package is read as well. Anything else carries no package.
function mp_trendyol_webhook_packages($account, $decoded) {

	$entries = array();

	if (isset($decoded['content']) && is_array($decoded['content'])) {

		$entries = $decoded['content'];

	} elseif (is_array($decoded) && isset($decoded['shipmentPackageId'])) {

		$entries = array($decoded);

	}

	$packages = array();

	foreach ($entries as $entry) {

		$package = mp_trendyol_package($entry);

		if ($package !== null) {

			$packages[] = $package;

		}

	}

	return mp_ok(array('packages' => $packages));

}

// Ask Trendyol to post this seller's order packages to $url.
//
// Every package status is subscribed (an empty list means all of them): the
// webhook replaces the wait for the next pull, and a cancellation matters as
// much as a new order. Trendyol sends $key back in an x-api-key header on every
// delivery; nothing is signed, so that key is all the receiver can check.
//
// Refused before the call: an address that is not https, which Trendyol does
// not deliver to (a development site on plain http cannot be registered), and
// one that contains "trendyol", "dolap" or "localhost", which their service
// refuses.
function mp_trendyol_register_webhook($account, $url, $key, $transport = null) {

	$url = trim((string)$url);

	if (stripos($url, 'https://') !== 0) {

		return mp_error(lang(array(
			'string' => 'Trendyol only sends orders to an https address, and this site\'s address is {var:1}. Set the site address to https first.',
			'vars'   => array($url)
		)));

	}

	if (preg_match('/trendyol|dolap|localhost/i', $url) === 1) {

		return mp_error(lang(array(
			'string' => 'Trendyol does not send orders to an address that contains "trendyol", "dolap" or "localhost": {var:1}',
			'vars'   => array($url)
		)));

	}

	$result = mp_trendyol_call($account,
		'/integration/webhook/sellers/' . mp_trendyol_seller_id($account) . '/webhooks',
		65536, $transport,
		array(
			'url'                => $url,
			'authenticationType' => 'API_KEY',
			'apiKey'             => (string)$key,
			'subscribedStatuses' => array()
		));

	if (!$result['ok']) {

		return $result;

	}

	$webhook_id = mp_trendyol_webhook_id(isset($result['data']['id']) ? $result['data']['id'] : '');

	if ($webhook_id === '') {

		return mp_error(lang('Trendyol accepted the webhook but did not return an id for it.'));

	}

	return mp_ok(array('webhook_id' => $webhook_id));

}

// Remove the webhook this account registered.
//
// Their 404 means it is gone already, which is what removing it was for, so it
// is success too. The read error does not carry the status, so it is taken from
// the answer on its way through.
function mp_trendyol_remove_webhook($account, $transport = null) {

	$webhook_id = mp_trendyol_webhook_id(isset($account['credentials']['webhook_id']) ? $account['credentials']['webhook_id'] : '');

	if ($webhook_id === '') {

		return mp_error(lang('No webhook is registered for this account.'));

	}

	$send = ($transport === null) ? 'api_http_request' : $transport;

	$status = 0;

	$watched = function ($method, $url, $options) use ($send, &$status) {

		$outcome = call_user_func($send, $method, $url, $options);

		$status = isset($outcome['status']) ? (int)$outcome['status'] : 0;

		return $outcome;

	};

	$result = mp_trendyol_call($account,
		'/integration/webhook/sellers/' . mp_trendyol_seller_id($account) . '/webhooks/' . $webhook_id,
		65536, $watched, null, 'DELETE', true);

	if ($result['ok'] || $status === 404) {

		return mp_ok(array('removed' => true));

	}

	return $result;

}

// A webhook id of theirs as it may go into a path, or ''.
function mp_trendyol_webhook_id($value) {

	$value = is_scalar($value) ? trim((string)$value) : '';

	return (preg_match('/^[A-Za-z0-9_-]{1,64}$/', $value) === 1) ? $value : '';

}

/* ------------------------------------------------------- their catalogue */

// One page of the seller's approved products on Trendyol, for matching them to
// the shop's (includes/api/outbound/remote_products.php).
//
// GET /integration/product/sellers/{id}/products/approved lists the seller's
// contents a page at a time, pages counted from 0, each content with its
// variants. One row is given per variant: a variant is what Trendyol sells,
// stocks and prices, and its barcode is the key every stock and price push
// names, so the barcode is the code a match is written with. A variant
// without a barcode is left out.
//
// remote_id is the content id, the one the listing status read writes on a
// mapping row; group_code is productMainId, shared by a content's variants.
//
// Answers data: products (remote_code, remote_id, group_code, barcode, title,
// image_url, price in minor units as sold there, quantity, status), page,
// pages, total, last.
function mp_trendyol_remote_products($account, $page, $transport = null) {

	$page = max(0, (int) $page);

	$result = mp_trendyol_call($account,
		'/integration/product/sellers/' . mp_trendyol_seller_id($account)
			. '/products/approved?page=' . $page . '&size=' . mp_trendyol_remote_page_size(),
		8388608, $transport);

	if (!$result['ok']) {

		return $result;

	}

	$data = $result['data'];

	$content = (isset($data['content']) && is_array($data['content'])) ? $data['content'] : array();

	$products = array();

	foreach ($content as $entry) {

		if (!is_array($entry)) {

			continue;

		}

		$variants = (isset($entry['variants']) && is_array($entry['variants'])) ? $entry['variants'] : array();

		$content_image = mp_trendyol_remote_image($entry);

		foreach ($variants as $variant) {

			if (!is_array($variant)) {

				continue;

			}

			$barcode = mp_trendyol_text($variant, array('barcode'));

			if ($barcode === '') {

				continue;

			}

			$image = mp_trendyol_remote_image($variant);

			$price = (isset($variant['price']) && is_array($variant['price']))
				? mp_trendyol_first($variant['price'], array('salePrice'))
				: mp_trendyol_first($variant, array('salePrice'));

			$quantity = (isset($variant['stock']) && is_array($variant['stock']))
				? mp_trendyol_first($variant['stock'], array('quantity'))
				: mp_trendyol_first($variant, array('quantity'));

			$status = array();

			foreach (array('onSale', 'locked', 'archived', 'blacklisted') as $flag) {

				if (!empty($variant[$flag])) {

					$status[] = $flag;

				}

			}

			$products[] = array(
				'remote_code' => $barcode,
				'remote_id'   => mp_trendyol_text($entry, array('contentId')),
				'group_code'  => mp_trendyol_text($entry, array('productMainId')),
				'barcode'     => $barcode,
				'title'       => mp_trendyol_text($entry, array('title')),
				'image_url'   => ($image !== '') ? $image : $content_image,
				'price'       => mp_trendyol_minor($price),
				'quantity'    => is_numeric($quantity) ? (int) round((float) $quantity) : 0,
				'status'      => implode('/', $status)
			);

		}

	}

	$pages = isset($data['totalPages']) ? (int) $data['totalPages'] : 0;

	// An empty page or the page count, whichever says so first.
	$last = !$content || (($pages > 0) && ($page + 1 >= $pages));

	return mp_ok(array(
		'products' => $products,
		'page'     => $page,
		'pages'    => $pages,
		'total'    => isset($data['totalElements']) ? (int) $data['totalElements'] : count($products),
		'last'     => $last
	));

}

function mp_trendyol_remote_page_size() {

	return 100;

}

// The first https picture in $row's images[] ({url} objects or plain
// addresses), or ''. Shown on a panel served over https, so a plain http
// address of theirs is not kept.
function mp_trendyol_remote_image($row) {

	foreach ((isset($row['images']) && is_array($row['images'])) ? $row['images'] : array() as $image) {

		$url = is_array($image) ? (isset($image['url']) ? $image['url'] : '') : $image;

		if (is_string($url) && preg_match('#^https://[^\s"<>]+$#i', $url)) {

			return $url;

		}

	}

	return '';

}
