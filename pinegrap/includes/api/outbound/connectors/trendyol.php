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
// order services moved to V2 (the V1 endpoints closed on 15 October 2026). Only
// V2 paths, or paths the documentation marks as shared by V1 and V2, are used.
//
// What this connector does so far: prove the credentials, read the category
// tree, and read what a category demands. Stock, price, listings and orders are
// not named in the table below, so mp_can() answers no for them and nothing
// queues work this connector cannot do.
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
		'category_attributes' => 'mp_trendyol_category_attributes'
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

// One GET, with their answer already read.
function mp_trendyol_call($account, $path, $max_body, $transport = null) {

	$problem = mp_trendyol_credentials_problem($account);

	if ($problem !== '') {

		return mp_error($problem);

	}

	if ($transport === null) {

		$transport = 'api_http_request';

	}

	$outcome = call_user_func($transport, 'GET', mp_trendyol_base($account) . $path, array(
		'headers'  => mp_trendyol_headers($account),
		'timeout'  => 30,
		'max_body' => $max_body,
		'agent'    => 'marketplace'
	));

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
