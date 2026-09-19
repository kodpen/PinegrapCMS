<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// n11.
//
// Their REST surface, verified against developer.n11.com on 2026-09-06. The
// older SOAP services still answer, and most of the integrations written for
// this marketplace speak them; this connector does not, because n11 has moved
// its own documentation to the REST endpoints and the product services there
// are the ones that take a batch.
//
// Two things about this provider decide the shape of everything below.
//
// It is asynchronous. A push is not a write - it is a submission. The call
// answers with a task id and the words IN_QUEUE, and whether the twelve hundred
// products in that batch actually landed is a separate question asked later, of
// a different endpoint, which answers per SKU. A connector that reported success
// on the submission would be reporting that the letter was posted.
//
// It is batched. One request carries a thousand SKUs, and the rate limit is
// counted in requests. Sending products one at a time is not a slower version of
// the same thing; it is the difference between one call and a thousand.
//
// Credentials go in headers named appKey and appSecret. Not a bearer token, not
// Basic - the header names are theirs and are case-sensitive enough that the
// documentation writes them both ways for different services. Both spellings
// are sent, which costs two headers and removes a class of bug that only shows
// up on one endpoint.

if (!defined('PG_INIT_LOADED')) {

	exit;

}

function mp_n11_connector() {

	return array(
		'test_credentials' => 'mp_n11_test_credentials',
		'push_stock_price' => 'mp_n11_push_stock_price',
		'read_task'        => 'mp_n11_read_task',
		'categories'       => 'mp_n11_categories',
		'pull_orders'      => 'mp_n11_pull_orders',
		'approve_lines'    => 'mp_n11_approve_lines',
		'order_stage'      => 'mp_n11_order_stage',
		'request_collection' => 'mp_n11_request_collection',
		'category_tree'    => 'mp_n11_category_tree',
		'category_attributes' => 'mp_n11_category_attributes',
		'create_listings'  => 'mp_n11_create_listings'
	);

}

function mp_n11_base() {

	return 'https://api.n11.com';

}

// The name n11 files this traffic under. Their support asks for it when a
// batch goes missing, and it is how they tell one integrator's calls from
// another's, so it is sent on every product call rather than left empty.
function mp_n11_integrator() {

	return 'Pinegrap';

}

function mp_n11_headers($account) {

	$key    = isset($account['credentials']['app_key'])    ? (string)$account['credentials']['app_key']    : '';
	$secret = isset($account['credentials']['app_secret']) ? (string)$account['credentials']['app_secret'] : '';

	// Both spellings. Their own documentation writes appKey on the product
	// services and appkey on the order services, and a header the receiver does
	// not recognise is ignored rather than refused.
	return array(
		'Content-Type: application/json',
		'Accept: application/json',
		'appKey: ' . $key,
		'appSecret: ' . $secret,
		'appkey: ' . $key,
		'appsecret: ' . $secret
	);

}

// One call, with their answer already read.
//
// n11 does not always use the status code to say no: a rejected batch can come
// back 200 with status REJECT in the body, and an expired key can come back 200
// with an error object. So the body is parsed first and the status code is only
// the fallback.
function mp_n11_call($account, $method, $path, $body = null, $max_body = 0) {

	$outcome = api_http_request($method, mp_n11_base() . $path, array(
		'body'     => ($body === null) ? null : json_encode($body),
		'headers'  => mp_n11_headers($account),
		'timeout'  => 30,
		'max_body' => $max_body,
		'agent'    => 'marketplace'
	));

	if ($outcome['error'] !== '') {

		// A transport failure. Their server, the network, or a certificate -
		// all three are worth trying again.
		return mp_error($outcome['error'], true);

	}

	// A refusal of the credentials themselves, before anything else is read.
	//
	// n11 answers these with an HTML page rather than their error envelope, so
	// without this the operator is told the reply was not JSON - which is true,
	// and useless. This is the one failure somebody testing a connection is
	// most likely to be looking at.
	if ($outcome['status'] === 401 || $outcome['status'] === 403) {

		return mp_error(lang('n11 did not accept the API key and secret. Check them in your n11 store panel, under API settings.'));

	}

	// Their own rate limit, which is a thousand requests a minute per store.
	if ($outcome['status'] === 429) {

		return mp_error(lang('n11 is rate limiting this store. The queue will try again shortly.'), true);

	}

	$decoded = json_decode($outcome['body'], true);

	if (!is_array($decoded)) {

		return mp_error(
			lang(array('string' => 'n11 answered {var:1} with something that is not JSON.', 'vars' => array($outcome['status']))),
			api_http_should_retry($outcome));

	}

	// Their error envelope. Seen as {"errors":[{"errorMessage":...}]} and as
	// {"error":{"errorMessage":...}} depending on the service.
	$message = mp_n11_error_message($decoded);

	if ($message !== '') {

		return mp_error($message, api_http_should_retry($outcome), $decoded);

	}

	if (!$outcome['ok']) {

		return mp_error(
			lang(array('string' => 'n11 answered {var:1}.', 'vars' => array($outcome['status']))),
			api_http_should_retry($outcome),
			$decoded);

	}

	return mp_ok($decoded);

}

// Their error text, wherever this particular service put it.
function mp_n11_error_message($decoded) {

	if (isset($decoded['errors']) && is_array($decoded['errors'])) {

		$parts = array();

		foreach ($decoded['errors'] as $error) {

			if (is_array($error)) {

				$parts[] = isset($error['errorMessage']) ? $error['errorMessage']
					: (isset($error['message']) ? $error['message'] : json_encode($error));

			} else {

				$parts[] = (string) $error;

			}

		}

		$joined = trim(implode('; ', array_filter($parts)));

		if ($joined !== '') {

			return $joined;

		}

	}

	if (isset($decoded['error']) && is_array($decoded['error'])) {

		if (isset($decoded['error']['errorMessage'])) {

			return (string) $decoded['error']['errorMessage'];

		}

		if (isset($decoded['error']['message'])) {

			return (string) $decoded['error']['message'];

		}

	}

	return '';

}

/* ------------------------------------------------------------ can we talk */

// The category tree is the cheapest authenticated call they have, and it is a
// GET, so a wrong key is answered without anything having been submitted.
function mp_n11_test_credentials($account) {

	$result = mp_n11_call($account, 'GET', '/cdn/categories', null, 4000);

	if (!$result['ok']) {

		return $result;

	}

	$count = isset($result['data']['categories']) ? count((array)$result['data']['categories']) : 0;

	if (!$count) {

		return mp_error(lang('n11 answered, but returned no categories. The key may belong to a closed store.'));

	}

	return mp_ok(array('categories' => $count));

}

function mp_n11_categories($account) {

	// The whole tree, and it is large - the default response cap would cut it
	// in half and leave json_decode() with a fragment.
	return mp_n11_call($account, 'GET', '/cdn/categories', null, 4194304);

}

/* --------------------------------------------------------- stock and price */

// Send a batch of stock and price changes.
//
// $rows is what mp_product_row() built, one per product. Answers a task handle
// in data['task_id']; the batch is not finished until read_task() says so.
//
// What is sent per SKU is decided here rather than by the caller:
//
//   stockCode is the product's code on their side, and it is the only field
//   that identifies the row. A product mapped to the wrong code updates
//   somebody else's listing, which is why the mapping screen makes the operator
//   type it rather than guessing from the name.
//
//   listPrice and salePrice go together or not at all - their rule, and it is
//   enforced here because sending one alone is accepted and then rejected per
//   SKU on the task, hours later. The shop has one price, so both carry it.
//
//   Sending price at all is optional per account. A shop that sets its
//   marketplace prices by hand over there - most do, because the commission
//   makes them different from the shelf price - turns price off and this sends
//   quantity alone.
function mp_n11_push_stock_price($account, $rows) {

	$rows = (array) $rows;

	if (!$rows) {

		return mp_error(lang('Nothing to send.'));

	}

	// Their ceiling, and the reason the caller batches at all.
	if (count($rows) > 1000) {

		$rows = array_slice($rows, 0, 1000);

	}

	$with_price = !empty($account['push_price']);
	$with_stock = !empty($account['push_stock']);

	if (!$with_price && !$with_stock) {

		return mp_error(lang('This account sends neither stock nor price.'));

	}

	$skus = array();

	foreach ($rows as $row) {

		$code = trim((string)$row['remote_code']);

		if ($code === '') {

			continue;

		}

		$sku = array('stockCode' => $code);

		// A product that does not track stock has no quantity to send, and
		// sending zero is not the same as saying nothing: it takes the listing
		// off sale. A shop selling a service or a download this way would have
		// found every one of them at zero on the marketplace, permanently.
		if ($with_stock && !empty($row['tracked'])) {

			$sku['quantity'] = (int) $row['quantity'];

		}

		if ($with_price) {

			// Two decimal places with a point, which is their format. The shop
			// keeps minor units, so this is the one place the hundred lives.
			$price = number_format((float)$row['price_major'], 2, '.', '');

			$sku['listPrice']    = $price;
			$sku['salePrice']    = $price;
			$sku['currencyType'] = mp_n11_currency();

		}

		// Nothing left to say about this one: it does not track stock and this
		// account does not send prices.
		if (count($sku) === 1) {

			continue;

		}

		$skus[] = $sku;

	}

	if (!$skus) {

		return mp_error(lang('None of these products has anything to send: no code, or no stock and no price.'));

	}

	$result = mp_n11_call($account, 'POST', '/ms/product/tasks/price-stock-update', array(
		'payload' => array(
			'integrator' => mp_n11_integrator(),
			'skus'       => $skus
		)
	), 8000);

	if (!$result['ok']) {

		return $result;

	}

	return mp_n11_task_handle($result['data'], count($skus));

}

// What came back from a submission.
//
// IN_QUEUE with an id is the only answer that means "ask again later". REJECT
// is the whole batch refused before it was looked at, and the reasons are the
// only explanation there will be - there is no task to query afterwards.
function mp_n11_task_handle($data, $sent) {

	$status = isset($data['status']) ? (string)$data['status'] : '';

	$task_id = isset($data['id']) ? (string)$data['id'] : '';

	// REJECT is the batch refused before it was looked at, and it is the only
	// answer that is final. Anything else without an id is an answer that could
	// not be read, which is a different thing and is worth asking again - the
	// two were folded together here at first, and that turned a momentary
	// oddity in their reply into a batch permanently marked as failed.
	if ($status === 'REJECT') {

		$reasons = isset($data['reasons']) ? (array)$data['reasons'] : array();

		return mp_error($reasons
			? implode('; ', $reasons)
			: lang('n11 refused the batch without saying why.'));

	}

	if ($task_id === '') {

		return mp_error(lang('n11 accepted the batch but did not return a task id.'), true);

	}

	return mp_ok(array('task_id' => $task_id, 'sent' => (int)$sent));

}

// The result of an earlier batch, per SKU.
//
// Answers data['finished'] false while it is still running, and otherwise a
// results map keyed by stockCode. n11 reports the task as PROCESSED even when
// every line in it failed, so "finished" and "worked" are two different
// questions and the caller is given both.
function mp_n11_read_task($account, $task_id) {

	$task_id = trim((string)$task_id);

	if ($task_id === '') {

		return mp_error(lang('No task to ask about.'));

	}

	$result = mp_n11_call($account, 'POST', '/ms/product/task-details/page-query', array(
		'taskId'   => (int)$task_id,
		'pageable' => array('page' => 0, 'size' => 1000)
	), 4194304);

	if (!$result['ok']) {

		return $result;

	}

	$data = $result['data'];

	$status = isset($data['status']) ? (string)$data['status'] : '';

	if ($status === 'IN_QUEUE') {

		return mp_ok(array('finished' => false, 'status' => $status, 'results' => array()));

	}

	if ($status === 'REJECT') {

		$reasons = isset($data['reasons']) ? (array)$data['reasons'] : array();

		return mp_error($reasons ? implode('; ', $reasons) : lang('n11 rejected the batch.'));

	}

	$results = array();

	$content = array();

	if (isset($data['skus']['content']) && is_array($data['skus']['content'])) {

		$content = $data['skus']['content'];

	}

	foreach ($content as $line) {

		if (!is_array($line)) {

			continue;

		}

		// itemCode is the stockCode that was sent. It is how a line in their
		// answer is matched back to a product here; there is no other key.
		$code = isset($line['itemCode']) ? (string)$line['itemCode'] : '';

		if ($code === '') {

			continue;

		}

		$line_status = isset($line['status']) ? (string)$line['status'] : '';

		$reasons = array();

		if (isset($line['reasons'])) {

			$reasons = (array) $line['reasons'];

		} elseif (isset($line['sku']['reasons'])) {

			$reasons = (array) $line['sku']['reasons'];

		}

		$results[$code] = array(
			'ok'     => ($line_status === 'SUCCESS'),
			'status' => $line_status,
			'error'  => mp_n11_reason_text($reasons)
		);

	}

	return mp_ok(array(
		'finished' => true,
		'status'   => ($status !== '') ? $status : 'PROCESSED',
		'results'  => $results
	));

}

// Their reasons come as strings on some services and as objects on others.
function mp_n11_reason_text($reasons) {

	$parts = array();

	foreach ((array)$reasons as $reason) {

		if (is_array($reason)) {

			$parts[] = isset($reason['message']) ? $reason['message']
				: (isset($reason['errorMessage']) ? $reason['errorMessage'] : json_encode($reason));

		} else {

			$parts[] = (string) $reason;

		}

	}

	return mb_substr(trim(implode('; ', array_filter($parts))), 0, 500);

}


/* ------------------------------------------------------------------ orders */

// Their orders since a moment, as packages.
//
// The package, not the order, is the unit. n11 splits a customer order into
// shipment packages and it is the package that carries a status, a courier and
// a tracking number; two packages of one order arrive here as two entries and
// are imported as two orders, because that is how they will be shipped.
//
// Time is milliseconds and their clock is GMT+3, not UTC. Sending a UTC
// timestamp reads as three hours earlier over there, which quietly re-imports
// three hours of orders on every poll - or, going the other way, skips them.
//
// The window is closed at both ends, and it starts a little before $since. A
// feed that is queried right up to "now" loses whatever was written in the
// second the query was made; the overlap costs nothing because the importer is
// keyed on the package id and refuses a repeat.
function mp_n11_pull_orders($account, $since) {

	$since = (int) $since;

	if ($since <= 0) {

		// A first pull. A week is long enough to catch what is still unshipped
		// without walking the whole history of the store.
		$since = time() - 604800;

	}

	$start = ($since - 300) * 1000;

	$end = (time() + 60) * 1000;

	$packages = array();

	$page = 0;

	// Their ceiling is a hundred a page. The cap on pages is what stops a first
	// pull on a busy store from running until the cron is killed; whatever is
	// left is picked up on the next pass, because the cursor only moves as far
	// as what was actually read.
	for ($page = 0; $page < 20; $page++) {

		$path = '/rest/delivery/v1/shipmentPackages'
			. '?startDate=' . $start
			. '&endDate=' . $end
			. '&page=' . $page
			. '&size=100'
			. '&orderByField=true'
			. '&orderByDirection=ASC';

		$result = mp_n11_call($account, 'GET', $path, null, 4194304);

		if (!$result['ok']) {

			// Whatever was read before the failure is still worth importing, and
			// the caller is told not to move the cursor past it.
			return mp_error($result['error'], !empty($result['retry']),
				array('packages' => $packages, 'complete' => false));

		}

		$content = mp_n11_order_content($result['data']);

		if (!$content) {

			break;

		}

		foreach ($content as $entry) {

			$package = mp_n11_package($entry);

			if ($package !== null) {

				$packages[] = $package;

			}

		}

		if (count($content) < 100) {

			break;

		}

	}

	return mp_ok(array('packages' => $packages, 'complete' => true, 'read_to' => time()));

}

// Their listing wraps the rows differently depending on the service and the
// day: content, shipmentPackages, or the bare array.
function mp_n11_order_content($data) {

	foreach (array('content', 'shipmentPackages', 'packages') as $key) {

		if (isset($data[$key]) && is_array($data[$key])) {

			return $data[$key];

		}

	}

	// A bare list, which is what an empty page sometimes answers with.
	if (isset($data[0]) && is_array($data[0])) {

		return $data;

	}

	return array();

}

// One of their packages in the shape the importer reads.
//
// Money is the reason this is not a straight copy. n11 quotes prices as decimal
// major units - 199.90 - and this shop stores minor units, so every amount is
// multiplied here rather than in the importer, once, where the marketplace's
// own convention is known.
//
// The discounts are the fiddly part: sellerDiscount and sellerCouponDiscount
// come off the seller, mallDiscount is n11's own money and the seller is paid
// as if it had not happened. So only the seller's two are a discount on this
// order; counting the mall's would understate what was earned.
function mp_n11_package($entry) {

	if (!is_array($entry)) {

		return null;

	}

	$package_id = isset($entry['id']) ? (string)$entry['id'] : '';

	if ($package_id === '') {

		return null;

	}

	$lines = array();

	$raw_lines = isset($entry['lines']) && is_array($entry['lines']) ? $entry['lines'] : array();

	foreach ($raw_lines as $line) {

		if (!is_array($line)) {

			continue;

		}

		$quantity = isset($line['quantity']) ? max(1, (int)$line['quantity']) : 1;

		$discount = mp_n11_minor($line, 'sellerDiscount') + mp_n11_minor($line, 'sellerCouponDiscount');

		$lines[] = array(
			'remote_line_id' => isset($line['orderLineId']) ? (string)$line['orderLineId'] : '',
			// stockCode is the seller's own product code - the one the mapping
			// screen matches on. productId is n11's and means nothing here.
			'code'           => isset($line['stockCode']) ? trim((string)$line['stockCode']) : '',
			'name'           => isset($line['productName']) ? (string)$line['productName'] : '',
			'quantity'       => $quantity,
			'price'          => mp_n11_minor($line, 'price'),
			// They quote tax inside the price rather than beside it, so there is
			// nothing to add here. The shop works the same way for a value added
			// tax, which is what this marketplace trades under.
			'tax'            => 0,
			// Per unit, like everything else the importer is handed. n11 gives
			// the discount for the whole line.
			'discount'       => ($quantity > 0) ? (int) round($discount / $quantity) : $discount,
			'status'         => isset($line['orderItemLineItemStatusName'])
				? (string)$line['orderItemLineItemStatusName'] : ''
		);

	}

	$name = isset($entry['customerfullName']) ? trim((string)$entry['customerfullName']) : '';

	$space = ($name !== '') ? strrpos($name, ' ') : false;

	return array(
		'remote_order_id'   => isset($entry['orderNumber']) ? (string)$entry['orderNumber'] : $package_id,
		'remote_package_id' => $package_id,
		'remote_status'     => isset($entry['shipmentPackageStatus']) ? (string)$entry['shipmentPackageStatus'] : '',
		'placed_at'         => mp_n11_seconds($entry, 'lastModifiedDate'),
		'currency'          => 'TRY',
		'customer' => array(
			// One field, and the surname is the last word of it. Wrong for a
			// double-barrelled surname and right for everything else; there is
			// no second field to read.
			'first_name' => ($space === false) ? $name : trim(substr($name, 0, $space)),
			'last_name'  => ($space === false) ? ''    : trim(substr($name, $space + 1)),
			'email'      => isset($entry['customerEmail']) ? (string)$entry['customerEmail'] : '',
			'phone'      => mp_n11_address_field($entry, 'shippingAddress', 'phone'),
			'company'    => ''
		),
		'billing'          => mp_n11_address($entry, 'billingAddress'),
		'shipping_address' => mp_n11_address($entry, 'shippingAddress'),
		'shipping_cost'    => 0,
		// n11 assigns the courier and the number - a seller on this marketplace
		// ships through their contracted carriers, so the tracking travels this
		// way rather than being pushed back.
		'tracking' => array(
			'number'   => isset($entry['cargoTrackingNumber']) ? (string)$entry['cargoTrackingNumber'] : '',
			'carrier'  => isset($entry['cargoProviderName']) ? (string)$entry['cargoProviderName'] : '',
			'link'     => isset($entry['cargoTrackingLink']) ? (string)$entry['cargoTrackingLink'] : ''
		),
		'lines'            => $lines
	);

}

// One of their addresses in the importer's shape.
function mp_n11_address($entry, $key) {

	$address = (isset($entry[$key]) && is_array($entry[$key])) ? $entry[$key] : array();

	if (!$address) {

		return array();

	}

	$read = function ($names) use ($address) {

		foreach ($names as $name) {

			if (isset($address[$name]) && trim((string)$address[$name]) !== '') {

				return trim((string)$address[$name]);

			}

		}

		return '';

	};

	return array(
		'name'      => $read(array('fullName', 'firstName')),
		'address_1' => $read(array('address', 'addressText', 'fullAddress')),
		'address_2' => $read(array('neighborhood', 'address2')),
		'city'      => $read(array('city', 'cityName')),
		'state'     => $read(array('district', 'districtName', 'town')),
		'zip'       => $read(array('postalCode', 'zipCode')),
		'country'   => $read(array('country', 'countryName')),
		'phone'     => $read(array('gsm', 'phone', 'mobilePhone'))
	);

}

// One field of one of their addresses, named by the importer's key (the shape
// mp_n11_address() answers with), not by theirs.
function mp_n11_address_field($entry, $key, $field) {

	$address = mp_n11_address($entry, $key);

	return isset($address[$field]) ? $address[$field] : '';

}

// A decimal amount of theirs as minor units.
function mp_n11_minor($row, $key) {

	if (!isset($row[$key])) {

		return 0;

	}

	// A comma is a decimal separator in their locale and would truncate the
	// number to its whole part if it reached (float) unchanged.
	$value = str_replace(',', '.', (string)$row[$key]);

	if (!is_numeric($value)) {

		return 0;

	}

	return (int) round(((float)$value) * 100);

}

// Their millisecond timestamp as seconds.
function mp_n11_seconds($row, $key) {

	if (!isset($row[$key])) {

		return 0;

	}

	$value = $row[$key];

	if (is_numeric($value)) {

		$value = (float) $value;

		// Milliseconds if it is far too large to be seconds.
		return (int) (($value > 100000000000) ? round($value / 1000) : $value);

	}

	$parsed = strtotime((string)$value);

	return ($parsed === false) ? 0 : (int) $parsed;

}


// What their status means here.
//
// Seven of theirs against four of ours, so this is a narrowing rather than a
// translation. Only three answers matter to the shop: it has gone, it has
// arrived, or it is not going to happen.
function mp_n11_order_stage($remote_status) {

	switch ((string)$remote_status) {

		case 'Shipped':
			return 'shipped';

		case 'Delivered':
			return 'delivered';

		case 'Cancelled':
		case 'UnSupplied':
			return 'cancelled';

	}

	// Created, Picking, Unpacked - still ours to deal with.
	return 'open';

}

// Ask n11 to collect the package.
//
// This is what "ship it" means on this marketplace: the seller does not enter a
// tracking number, they ask one of n11's contracted carriers to come and get
// it, and n11 assigns the number afterwards. HLZ is Horoz, CEVA is Ceva, BL is
// Borusan; the account has to have an agreement with whichever is asked for.
//
// Deliberately not called from the scheduled job. A collection request brings a
// courier to an address, and nothing on a timer should be able to do that.
function mp_n11_request_collection($account, $requests) {

	$details = array();

	foreach ((array)$requests as $request) {

		$details[] = array(
			'id'              => (string)$request['package_id'],
			'orderLineId'     => (int)$request['line_id'],
			'boxQuantity'     => max(1, (int)$request['boxes']),
			'desi'            => max(1, (int)$request['desi']),
			'shipmentCompany' => (string)$request['carrier']
		);

	}

	if (!$details) {

		return mp_error(lang('Nothing to collect.'));

	}

	return mp_n11_call($account, 'PUT', '/rest/delivery/v1/collectionRequest',
		array('collectionRequestDetails' => $details), 4000);

}

// Accept the lines of an order: Created becomes Picking.
//
// The only status change their update service performs today, and it is a
// commitment - it tells n11 the seller is preparing the goods, and their
// dispatch clock is running from it. So the caller decides whether to do it;
// this only carries it out.
function mp_n11_approve_lines($account, $line_ids) {

	$lines = array();

	foreach ((array)$line_ids as $line_id) {

		$line_id = trim((string)$line_id);

		if ($line_id !== '') {

			$lines[] = array('lineId' => (int)$line_id);

		}

	}

	if (!$lines) {

		return mp_error(lang('That order has no lines to accept.'));

	}

	$result = mp_n11_call($account, 'PUT', '/rest/order/v1/update', array(
		'lines'  => $lines,
		'status' => 'Picking'
	), 8000);

	if (!$result['ok']) {

		return $result;

	}

	$refused = array();

	$content = isset($result['data']['content']) ? (array)$result['data']['content'] : array();

	foreach ($content as $line) {

		if (!is_array($line)) {

			continue;

		}

		if (isset($line['status']) && (string)$line['status'] !== 'SUCCESS') {

			$refused[] = (isset($line['lineId']) ? $line['lineId'] . ': ' : '')
				. (isset($line['reasons']) ? mp_n11_reason_text((array)$line['reasons']) : '');

		}

	}

	if ($refused) {

		return mp_error(implode('; ', $refused));

	}

	return mp_ok(array('accepted' => count($lines)));

}


/* -------------------------------------------------------------- listings */

// Their whole category tree, flattened.
//
// Answers rows of array(remote_id, parent_id, name, path, is_leaf). The tree
// arrives nested and is walked here rather than in the caller, because the one
// thing a caller has to know - is this a leaf - is expressed by n11 as
// subCategories coming back null, and that is a detail of their JSON rather
// than a fact about categories.
//
// Only a leaf can carry a product. A listing filed against a branch is refused.
function mp_n11_category_tree($account) {

	$result = mp_n11_call($account, 'GET', '/cdn/categories', null, 8388608);

	if (!$result['ok']) {

		return $result;

	}

	$rows = array();

	$roots = isset($result['data']['categories']) ? (array)$result['data']['categories'] : array();

	mp_n11_category_walk($roots, '', '', $rows);

	if (!$rows) {

		return mp_error(lang('n11 returned no categories.'));

	}

	return mp_ok(array('categories' => $rows));

}

function mp_n11_category_walk($nodes, $parent_id, $parent_path, &$rows) {

	foreach ((array)$nodes as $node) {

		if (!is_array($node) || !isset($node['id'])) {

			continue;

		}

		$id = (string) $node['id'];

		$name = isset($node['name']) ? (string)$node['name'] : '';

		$path = ($parent_path === '') ? $name : ($parent_path . ' > ' . $name);

		$children = (isset($node['subCategories']) && is_array($node['subCategories']))
			? $node['subCategories'] : array();

		$rows[] = array(
			'remote_id' => $id,
			'parent_id' => $parent_id,
			'name'      => $name,
			'path'      => $path,
			'is_leaf'   => $children ? 0 : 1
		);

		if ($children) {

			mp_n11_category_walk($children, $id, $path, $rows);

		}

	}

}

// What one category demands.
//
// Answers rows of array(attribute_id, name, mandatory, is_variant,
// custom_allowed, sort_order, values). isVariant is the one that decides how a
// set of products is listed: an attribute marked that way is what makes two
// rows two options of one article rather than two articles.
function mp_n11_category_attributes($account, $category_id) {

	$category_id = (int) $category_id;

	if (!$category_id) {

		return mp_error(lang('No category to ask about.'));

	}

	$result = mp_n11_call($account, 'GET', '/cdn/category/' . $category_id . '/attribute', null, 2097152);

	if (!$result['ok']) {

		return $result;

	}

	$rows = array();

	$attributes = isset($result['data']['categoryAttributes'])
		? (array)$result['data']['categoryAttributes'] : array();

	foreach ($attributes as $attribute) {

		if (!is_array($attribute) || !isset($attribute['attributeId'])) {

			continue;

		}

		$values = array();

		foreach ((array)(isset($attribute['attributeValues']) ? $attribute['attributeValues'] : array()) as $value) {

			if (is_array($value) && isset($value['id'])) {

				$values[] = array(
					'id'    => (string)$value['id'],
					'value' => isset($value['value']) ? (string)$value['value'] : ''
				);

			}

		}

		$rows[] = array(
			'attribute_id'   => (string)$attribute['attributeId'],
			'name'           => isset($attribute['attributeName']) ? (string)$attribute['attributeName'] : '',
			'mandatory'      => !empty($attribute['isMandatory']) ? 1 : 0,
			'is_variant'     => !empty($attribute['isVariant']) ? 1 : 0,
			'custom_allowed' => !empty($attribute['isCustomValue']) ? 1 : 0,
			'sort_order'     => isset($attribute['attributeOrder']) ? (int)$attribute['attributeOrder'] : 0,
			'values'         => $values
		);

	}

	return mp_ok(array('attributes' => $rows));

}

// Open listings for a set of products.
//
// $items is what the caller assembled: one entry per product, each carrying the
// article it belongs to and the values that article's category demands.
// Answers a task handle, the same asynchronous shape as a stock push - and for
// the same reason, it is not finished until read_task() says so.
//
// productMainId is what groups the variants. Every product of one article sends
// the same value, and n11 then shows them as one listing with options rather
// than as a page each. Sending a different one per product is the difference
// between a shirt with four sizes and four shirts.
function mp_n11_create_listings($account, $items) {

	$items = (array) $items;

	if (!$items) {

		return mp_error(lang('Nothing to list.'));

	}

	if (count($items) > 1000) {

		$items = array_slice($items, 0, 1000);

	}

	$template = trim((string)$account['shipment_template']);

	if ($template === '') {

		return mp_error(lang('This account has no cargo template. n11 refuses a listing without one; copy the name from your n11 store settings.'));

	}

	$skus = array();

	foreach ($items as $item) {

		$images = array();

		$order = 1;

		foreach ((array)$item['images'] as $url) {

			$images[] = array('url' => (string)$url, 'order' => $order);

			$order++;

		}

		if (!$images) {

			return mp_error(lang(array(
				'string' => '{var:1} has no picture. n11 will not list a product without one.',
				'vars'   => array((string)$item['code'])
			)));

		}

		$attributes = array();

		foreach ((array)$item['attributes'] as $attribute) {

			$entry = array('id' => (int)$attribute['id']);

			// A value from their list, or free text where the category allows
			// it. Sending both, or neither, is refused per SKU on the task.
			if (isset($attribute['value_id']) && (string)$attribute['value_id'] !== '') {

				$entry['valueId'] = (int) $attribute['value_id'];

			} else {

				$entry['customValue'] = (string) $attribute['custom_value'];

			}

			$attributes[] = $entry;

		}

		$price = number_format((float)$item['price_major'], 2, '.', '');

		$skus[] = array(
			'integrator'       => mp_n11_integrator(),
			'title'            => mb_substr((string)$item['title'], 0, 250),
			'description'      => (string)$item['description'],
			'categoryId'       => (int)$item['category_id'],
			'currencyType'     => mp_n11_currency(),
			'productMainId'    => (string)$item['group_code'],
			'preparingDay'     => max(1, (int)$account['preparing_day']),
			'shipmentTemplate' => $template,
			'stockCode'        => (string)$item['code'],
			'quantity'         => max(0, (int)$item['quantity']),
			'salePrice'        => $price,
			'listPrice'        => $price,
			'vatRate'          => mp_n11_vat_rate($item),
			'images'           => $images,
			'attributes'       => $attributes
		);

	}

	$result = mp_n11_call($account, 'POST', '/ms/product/tasks/product-create', array(
		'payload' => array(
			'integrator' => mp_n11_integrator(),
			'skus'       => $skus
		)
	), 8000);

	if (!$result['ok']) {

		return $result;

	}

	return mp_n11_task_handle($result['data'], count($skus));

}

// n11 accepts four rates and nothing else.
//
// A product with no rate of its own follows the shop's tax zone, and that
// number can be anything; a product with 8.5 per cent has to be sent as
// something from their list or the SKU is refused. The nearest of the four is
// the honest answer, and it is rounded down rather than up - overstating the
// rate on a marketplace listing overcharges the buyer.
function mp_n11_vat_rate($item) {

	$rate = isset($item['tax_rate']) && $item['tax_rate'] !== null
		? (float) $item['tax_rate']
		: (function_exists('get_default_tax_rate') ? (float) get_default_tax_rate() : 20.0);

	$allowed = array(0, 1, 10, 20);

	$best = 0;

	foreach ($allowed as $candidate) {

		if ($rate >= $candidate) {

			$best = $candidate;

		}

	}

	return $best;

}

// n11 sells in Turkish lira and accepts USD and EUR for the price fields.
//
// The shop's own currency decides, and anything else is sent as TL: a marketplace
// that only trades in three currencies cannot be told about a fourth, and being
// refused per SKU on a task hours later is a worse answer than being priced in
// the currency the marketplace actually settles in.
function mp_n11_currency() {

	$code = defined('BASE_CURRENCY_CODE') ? strtoupper((string)BASE_CURRENCY_CODE) : 'TRY';

	if ($code === 'USD' || $code === 'EUR') {

		return $code;

	}

	return 'TL';

}
