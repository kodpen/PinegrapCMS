<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the approval state of Trendyol listings: what the connector reads
 * from their approved and unapproved product services
 * (includes/api/outbound/connectors/trendyol.php), and what the job writes on
 * a product's mapping row for each state (includes/api/outbound/sync.php).
 *
 * Trendyol is never called: the connector takes a transport with the signature
 * of api_http_request(), and the one here answers from the saved responses in
 * tests/fixtures/trendyol/ (built in the shape of their V2 product filter
 * documentation, September 2026, not recorded from a live account).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The outbound files sit behind init.php's gate and only declare functions
// behind it, so the gate is answered here.
if (!defined('PG_INIT_LOADED')) {
	define('PG_INIT_LOADED', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/api/outbound/sync.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/outbound/connectors/trendyol.php');

function listing_status_test_account()
{
	return array(
		'id'          => 7,
		'provider'    => 'trendyol',
		'credentials' => array(
			'seller_id'   => '123456',
			'api_key'     => 'key-abc',
			'api_secret'  => 'secret-xyz',
			'environment' => 'production',
		),
	);
}

function listing_status_test_fixture($name)
{
	return (string) file_get_contents(dirname(__FILE__) . '/fixtures/trendyol/' . $name);
}

// Barcodes of digits come back as integer keys, as PHP stores them; the
// lists of keys are compared as text.

// A transport that answers by path (the part before the query string) from
// $routes (path => array(status, body)), writes every call into $calls, and
// answers 404 for any other path.
function listing_status_test_transport($routes, &$calls)
{
	return function ($method, $url, $options) use ($routes, &$calls) {

		$calls[] = array('method' => $method, 'url' => $url, 'options' => $options);

		$path = (string) parse_url($url, PHP_URL_PATH);

		if (isset($routes[$path])) {
			return array('ok' => ($routes[$path][0] >= 200 && $routes[$path][0] < 300), 'status' => $routes[$path][0],
				'body' => $routes[$path][1], 'error' => '', 'duration_ms' => 1);
		}

		return array('ok' => false, 'status' => 404, 'body' => '{"errors":[{"message":"no route in test"}]}',
			'error' => '', 'duration_ms' => 1);
	};
}

function listing_status_test_routes()
{
	return array(
		'/integration/product/sellers/123456/products/approved'
			=> array(200, listing_status_test_fixture('products-approved-states.json')),
		'/integration/product/sellers/123456/products/unapproved'
			=> array(200, listing_status_test_fixture('products-unapproved-states.json')),
	);
}

function listing_status_test_wanted($barcodes)
{
	$wanted = array();

	foreach ($barcodes as $barcode) {
		$wanted[mb_strtolower($barcode)] = $barcode;
	}

	return $wanted;
}

function listing_status_test_row($overrides = array())
{
	return array_merge(array(
		'id'          => 31,
		'product_id'  => 501,
		'remote_code' => '8680000000011',
		'remote_id'   => '',
		'status'      => 'active',
		'last_error'  => '',
	), $overrides);
}

/* ------------------------------------------------------------ readers */

function test_marketplace_trendyol_listing_status_connector_names_the_call()
{
	pg_assert_true(mp_can('trendyol', 'listing_status'));
	pg_assert_false(mp_can('n11', 'listing_status'));
}

// An approved listing is a content with variants; the barcode is a variant's.
function test_marketplace_trendyol_listing_status_reads_approved_variants()
{
	$states = mp_trendyol_approved_states(json_decode(listing_status_test_fixture('products-approved-states.json'), true),
		listing_status_test_wanted(array('8680000000011', '8680000000028', '8680000000035', 'TY-CAP-1')));

	pg_assert_same(array('8680000000011', '8680000000028', '8680000000035', 'TY-CAP-1'), array_map('strval', array_keys($states)),
		'only the barcodes asked for, under the spelling they were asked with');

	pg_assert_same(array(
		'state'       => 'approved',
		'content_id'  => '1239111111',
		'commission'  => 13.5,
		'on_sale'     => true,
		'locked'      => false,
		'lock_reason' => '',
		'archived'    => false,
		'blacklisted' => false,
		'product_url' => 'https://www.trendyol.com/ornek/pamuklu-tisort-p-1239111111?merchantId=123456',
	), $states['8680000000011']);

	pg_assert_same('1239111111', $states['8680000000028']['content_id'], 'a second variant of the same content');
	pg_assert_true($states['8680000000028']['locked']);
	pg_assert_same('Fiyat kontrolü', $states['8680000000028']['lock_reason']);
	pg_assert_false($states['8680000000028']['on_sale']);

	pg_assert_true($states['8680000000035']['archived']);
	pg_assert_same(12.0, $states['8680000000035']['commission'], 'a commission sent as text');
	pg_assert_true($states['TY-CAP-1']['blacklisted']);
	pg_assert_same(null, $states['TY-CAP-1']['commission'], 'no commission given');

	pg_assert_same(array(), mp_trendyol_approved_states(array('content' => 'nope'), listing_status_test_wanted(array('1'))));
}

function test_marketplace_trendyol_listing_status_reads_unapproved_entries()
{
	$states = mp_trendyol_unapproved_states(json_decode(listing_status_test_fixture('products-unapproved-states.json'), true),
		listing_status_test_wanted(array('8680000000042', '8680000000059', '8680000000066')));

	pg_assert_same(array(
		'state'      => 'rejected',
		'reasons'    => array('Görsel hatalı: Ürün görseli beyaz arka planlı olmalı', 'Marka uyuşmazlığı: Marka belgesi eksik'),
		'doc_needed' => false,
	), $states['8680000000042']);

	pg_assert_same(array('state' => 'pending', 'reasons' => array(), 'doc_needed' => true), $states['8680000000059']);
	pg_assert_same(array('state' => 'pending', 'reasons' => array(), 'doc_needed' => false), $states['8680000000066']);
}

/* --------------------------------------------------------------- calls */

function test_marketplace_trendyol_listing_status_asks_both_services()
{
	$calls = array();

	$result = mp_trendyol_listing_status(listing_status_test_account(),
		array('8680000000011', '8680000000042', '8680000000059', '8680000000077', 'TY-ÇAP.1'),
		listing_status_test_transport(listing_status_test_routes(), $calls));

	pg_assert_true($result['ok'], $result['error']);
	pg_assert_same(2, count($calls));

	pg_assert_same('GET', $calls[0]['method']);
	pg_assert_same('https://apigw.trendyol.com/integration/product/sellers/123456/products/approved'
		. '?barcodes=8680000000011,8680000000042,8680000000059,8680000000077,TY-%C3%87AP.1&page=0&size=100', $calls[0]['url']);
	pg_assert_same('https://apigw.trendyol.com/integration/product/sellers/123456/products/unapproved'
		. '?barcodes=8680000000011,8680000000042,8680000000059,8680000000077,TY-%C3%87AP.1&page=0&size=1000', $calls[1]['url']);

	$listings = $result['data']['listings'];

	pg_assert_same('approved', $listings['8680000000011']['state']);
	pg_assert_same('rejected', $listings['8680000000042']['state']);
	pg_assert_same('pending', $listings['8680000000059']['state']);
	pg_assert_same(array('state' => 'unknown'), $listings['8680000000077'], 'in neither answer');
	pg_assert_same(array('state' => 'unknown'), $listings['TY-ÇAP.1']);
	pg_assert_same(array(), $result['data']['refused']);
}

function test_marketplace_trendyol_listing_status_refuses_before_calling()
{
	$calls = array();
	$transport = listing_status_test_transport(listing_status_test_routes(), $calls);

	$many = array();

	for ($i = 0; $i < 51; $i++) {
		$many[] = '86800000' . sprintf('%05d', $i);
	}

	$result = mp_trendyol_listing_status(listing_status_test_account(), $many, $transport);

	pg_assert_false($result['ok'], 'more than fifty');
	pg_assert_contains('50', $result['error']);

	$result = mp_trendyol_listing_status(listing_status_test_account(), array('not a barcode!', ''), $transport);

	pg_assert_true($result['ok'], 'nothing left to ask is not an error');
	pg_assert_same(array(), $result['data']['listings']);
	pg_assert_same(array('not a barcode!', ''), array_map('strval', array_keys($result['data']['refused'])));

	pg_assert_same(0, count($calls), 'nothing was sent');

	$result = mp_trendyol_listing_status(listing_status_test_account(), array('not a barcode!', '8680000000011'), $transport);

	pg_assert_same(array('8680000000011'), array_map('strval', array_keys($result['data']['listings'])), 'the valid one is asked about');
	pg_assert_same(array('not a barcode!'), array_map('strval', array_keys($result['data']['refused'])), 'the other is reported');
	pg_assert_contains('barcodes=8680000000011&', $calls[0]['url']);
}

function test_marketplace_trendyol_listing_status_failure_stops_at_the_first_call()
{
	$calls = array();

	$result = mp_trendyol_listing_status(listing_status_test_account(), array('8680000000011'),
		listing_status_test_transport(array(
			'/integration/product/sellers/123456/products/approved' => array(503, '<html></html>'),
		), $calls));

	pg_assert_false($result['ok']);
	pg_assert_true($result['retry']);
	pg_assert_same(1, count($calls), 'the second service is not asked');
}

/* ------------------------------------------------------ the map row */

function test_marketplace_trendyol_listing_status_unknown_and_plain_pending_write_nothing()
{
	pg_assert_same(null, mp_sync_listing_row_update(listing_status_test_row(), array('state' => 'unknown'), 'Trendyol'));
	pg_assert_same(null, mp_sync_listing_row_update(listing_status_test_row(), null, 'Trendyol'), 'not answered at all');
	pg_assert_same(null, mp_sync_listing_row_update(listing_status_test_row(),
		array('state' => 'pending', 'reasons' => array(), 'doc_needed' => false), 'Trendyol'));
}

function test_marketplace_trendyol_listing_status_approval_stores_the_content_and_frees_an_error_row()
{
	$approved = array('state' => 'approved', 'content_id' => '1239111111', 'locked' => false, 'lock_reason' => '',
		'archived' => false, 'blacklisted' => false);

	pg_assert_same(array('remote_id' => '1239111111'), mp_sync_listing_row_update(listing_status_test_row(), $approved, 'Trendyol'));

	pg_assert_same(array('remote_id' => '1239111111', 'status' => 'active', 'last_error' => ''),
		mp_sync_listing_row_update(listing_status_test_row(array('status' => 'error', 'last_error' => 'Ürün onaylı değil')), $approved, 'Trendyol'),
		'a stock line refused while it waited no longer stops the product');

	pg_assert_same(array('remote_id' => '1239111111'),
		mp_sync_listing_row_update(listing_status_test_row(array('status' => 'paused', 'last_error' => 'old')), $approved, 'Trendyol'),
		'a paused row stays paused, and keeps what it says');

	pg_assert_same(null, mp_sync_listing_row_update(listing_status_test_row(array('remote_id' => '1239111111')), $approved, 'Trendyol'),
		'nothing to change');
}

// A push refusal on a row that still sends (no price, a second product under
// the barcode) is not caused by the approval, so reading the approval does not
// wipe it: the row would say nothing while its stock and price are not sent.
function test_marketplace_trendyol_listing_status_approval_keeps_a_push_refusal_on_an_active_row()
{
	$approved = array('state' => 'approved', 'content_id' => '555', 'locked' => false, 'lock_reason' => '',
		'archived' => false, 'blacklisted' => false);

	$refused = listing_status_test_row(array('last_error' => 'Trendyol refuses a product without a price.'));

	pg_assert_same(array('remote_id' => '555'), mp_sync_listing_row_update($refused, $approved, 'Trendyol'));

	pg_assert_same(array('remote_id' => '555'),
		mp_sync_listing_row_update($refused, array_merge($approved, array('archived' => true)), 'Trendyol'),
		'a note does not replace it either');

	pg_assert_same(array('remote_id' => '555', 'last_error' => ''),
		mp_sync_listing_row_update(listing_status_test_row(array(
			'last_error' => 'Trendyol needs a document for this listing; upload it in the seller panel.')), $approved, 'Trendyol'),
		'the document note written while it waited is cleared');

	pg_assert_same(array('remote_id' => '555', 'last_error' => 'Archived on Trendyol.'),
		mp_sync_listing_row_update(listing_status_test_row(), array_merge($approved, array('archived' => true)), 'Trendyol'),
		'an empty row takes the note');
}

// Rejections are fixed in Trendyol's seller panel (a document, the images), so
// a rejected row is still asked about and its approval sends the product again.
function test_marketplace_trendyol_listing_status_rejected_row_approved_after_a_fix_sends_again()
{
	$rejected = listing_status_test_row(array('status' => 'error', 'last_error' => 'Rejected by Trendyol: Görsel hatalı'));

	pg_assert_same(array('remote_id' => '777', 'status' => 'active', 'last_error' => ''),
		mp_sync_listing_row_update($rejected, array('state' => 'approved', 'content_id' => '777', 'locked' => false,
			'lock_reason' => '', 'archived' => false, 'blacklisted' => false), 'Trendyol'));

	pg_assert_same(null, mp_sync_listing_row_update($rejected, array('state' => 'rejected', 'reasons' => array('Görsel hatalı'),
		'doc_needed' => false), 'Trendyol'), 'the same rejection read again writes nothing');

	$source = listing_status_test_function_source('mp_sync_listing_states_account');

	pg_assert_true(strpos($source, 'mp_listing_rejected_prefix') === false, 'rejected rows are not left out of the set');
	pg_assert_contains("(remote_id = '')", $source);
}

function listing_status_test_function_source($name)
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/api/outbound/sync.php');

	$start = strpos($source, "\nfunction " . $name . '(');

	pg_assert_true($start !== false, $name . ' is found');

	$end = strpos($source, "\n}\n", (int)$start);

	return (string) substr($source, (int)$start, (int)$end - (int)$start);
}


function test_marketplace_trendyol_listing_status_approval_notes_a_locked_archived_or_blacklisted_listing()
{
	$base = array('state' => 'approved', 'content_id' => '9', 'locked' => false, 'lock_reason' => '', 'archived' => false, 'blacklisted' => false);

	pg_assert_same('Locked on Trendyol: Fiyat kontrolü', mp_sync_listing_row_update(listing_status_test_row(),
		array_merge($base, array('locked' => true, 'lock_reason' => 'Fiyat kontrolü')), 'Trendyol')['last_error']);

	pg_assert_same('Locked on Trendyol.', mp_sync_listing_row_update(listing_status_test_row(),
		array_merge($base, array('locked' => true)), 'Trendyol')['last_error']);

	pg_assert_same('Archived on Trendyol.', mp_sync_listing_row_update(listing_status_test_row(),
		array_merge($base, array('archived' => true)), 'Trendyol')['last_error']);

	pg_assert_same('Blacklisted on Trendyol.', mp_sync_listing_row_update(listing_status_test_row(),
		array_merge($base, array('blacklisted' => true, 'locked' => true)), 'Trendyol')['last_error'], 'the strongest note first');

	$noted = mp_sync_listing_row_update(listing_status_test_row(array('status' => 'error', 'last_error' => 'x')),
		array_merge($base, array('archived' => true)), 'Trendyol');

	pg_assert_same('active', $noted['status'], 'a note does not keep the row in error');
}

function test_marketplace_trendyol_listing_status_rejection_and_document_request()
{
	$rejected = array('state' => 'rejected', 'reasons' => array('Görsel hatalı: Beyaz arka plan', 'Marka uyuşmazlığı: Belge eksik'),
		'doc_needed' => false);

	$update = mp_sync_listing_row_update(listing_status_test_row(), $rejected, 'Trendyol');

	pg_assert_same(array('status' => 'error',
		'last_error' => 'Rejected by Trendyol: Görsel hatalı: Beyaz arka plan; Marka uyuşmazlığı: Belge eksik'), $update);

	pg_assert_same(0, strpos($update['last_error'], mp_listing_rejected_prefix('Trendyol')),
		'the screen knows a rejected row by this prefix');
	pg_assert_same('Rejected by Trendyol: ', mp_listing_rejected_prefix('Trendyol'));

	pg_assert_same(null, mp_sync_listing_row_update(listing_status_test_row($update), $rejected, 'Trendyol'), 'already written');

	pg_assert_same('Rejected by Trendyol: no reason given', mp_sync_listing_row_update(listing_status_test_row(),
		array('state' => 'rejected', 'reasons' => array(), 'doc_needed' => false), 'Trendyol')['last_error']);

	$long = mp_sync_listing_row_update(listing_status_test_row(),
		array('state' => 'rejected', 'reasons' => array(str_repeat('ç', 600)), 'doc_needed' => false), 'Trendyol');

	pg_assert_same(500, mb_strlen($long['last_error']), 'cut to the column');

	pg_assert_same(array('last_error' => 'Trendyol needs a document for this listing; upload it in the seller panel.'),
		mp_sync_listing_row_update(listing_status_test_row(), array('state' => 'pending', 'reasons' => array(), 'doc_needed' => true), 'Trendyol'),
		'information, not a failure: the status stays');

	$document = array('state' => 'pending', 'reasons' => array(), 'doc_needed' => true);

	pg_assert_same(null, mp_sync_listing_row_update(listing_status_test_row(array('last_error' => 'Trendyol refuses a product without a price.')),
		$document, 'Trendyol'), 'a push refusal on an active row stays');

	pg_assert_same(array('last_error' => 'Trendyol needs a document for this listing; upload it in the seller panel.'),
		mp_sync_listing_row_update(listing_status_test_row(array('status' => 'error', 'last_error' => 'Ürün onaylı değil')),
			$document, 'Trendyol'), 'a row stopped while it waits says what it waits for');

	pg_assert_same(array('last_error' => 'Trendyol needs a document for this listing; upload it in the seller panel.'),
		mp_sync_listing_row_update(listing_status_test_row(array('status' => 'error', 'last_error' => 'Rejected by Trendyol: Belge eksik')),
			$document, 'Trendyol'), 'a rejected listing sent back for approval');
}

/* --------------------------------------------------------- the set */

// The rows asked about are a hundred from a start picked at random each pass,
// so rows whose barcode Trendyol never answers for cannot hide the others,
// whatever the job's period.
function test_marketplace_trendyol_listing_status_window_turns_through_the_set()
{
	$ids = range(1, 250);

	pg_assert_same(range(1, 100), mp_sync_listing_window($ids, 0, 100));
	pg_assert_same(range(101, 200), mp_sync_listing_window($ids, 100, 100));
	pg_assert_same(array_merge(range(201, 250), range(1, 50)), mp_sync_listing_window($ids, 200, 100), 'it wraps round');
	pg_assert_same(range(51, 150), mp_sync_listing_window($ids, 300, 100), 'a start past the end counts on from the top');

	pg_assert_same(array(4, 5, 6), mp_sync_listing_window(array(4, 5, 6), 17, 100), 'a small set is asked about whole');
	pg_assert_same(array(), mp_sync_listing_window(array(), 3, 100));

	// Every row is in some window: a start of k asks about rows k+1 to k+100.
	$seen = array();

	for ($start = 0; $start < 200; $start += 100) {

		$seen = array_merge($seen, mp_sync_listing_window(range(1, 200), $start, 100));

	}

	pg_assert_same(range(1, 200), $seen);

	$source = listing_status_test_function_source('mp_sync_listing_states_account');

	pg_assert_true(strpos($source, 'time()') === false, 'the start does not come from the clock');
	pg_assert_contains('mt_rand()', $source);
}

// Reading listings is the least urgent step: it runs after the push and the
// order pull, and stops between calls when the pass's time is up.
function test_marketplace_trendyol_listing_status_runs_last_and_keeps_the_deadline()
{
	$run = listing_status_test_function_source('mp_sync_run');

	pg_assert_true(strpos($run, 'mp_sync_listing_states(') > strpos($run, 'mp_sync_orders('), 'after the order pull');
	pg_assert_true(strpos($run, 'mp_sync_listing_states(') > strpos($run, 'mp_sync_push('), 'after the push');

	$account = listing_status_test_function_source('mp_sync_listing_states_account');

	pg_assert_contains('$deadline', $account);
	pg_assert_true(strpos($account, 'microtime(true) >= $deadline') > strpos($account, 'array_chunk('),
		'checked before each call, not only before each account');
}

// The content id belongs to the code: matching the product under another code
// leaves it to be read again.
function test_marketplace_trendyol_listing_status_matching_again_forgets_the_content_id()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/marketplace_settings.php');

	pg_assert_contains('SET remote_code = \'" . e($remote_code) . "\', remote_id = \'\', status = \'active\', last_error = \'\'', $source);
}

/* ---------------------------------------------------------- screen */

function test_marketplace_trendyol_listing_status_screen_shows_the_state()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/marketplace_settings.php');

	pg_assert_contains("'string' => 'On {var:1}'", $source);
	pg_assert_contains("lang('Awaiting approval')", $source);
	pg_assert_contains("mp_listing_rejected_prefix(", $source);
}
