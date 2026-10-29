<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the shipment tracking job (includes/fn/shipment_tracking.php,
 * shipment_tracking_job.php): its place in the dispatcher's catalogue, what a
 * track() answer changes on a parcel, the order parcels are asked in, the
 * time, row and failure budgets of a run, where the next run starts, and the
 * status option of the shipment write path. Geliver's answers come from
 * tests/fixtures/geliver/ through pg_label_call() and a stand-in transport;
 * nothing here reaches Geliver or the database.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(__DIR__ . '/helpers/source_reader.php');

function shipment_tracking_test_account()
{
	return array('api_token' => 'gl-test-token-123456', 'mode' => 'live', 'sender_address' => '', 'service' => '');
}

// Geliver's answer to GET /shipments/<id>: status and body, or the text of a
// fixture of tests/fixtures/geliver/ for a 200 answer; status 0 is not reached.
function shipment_tracking_test_track($status, $body)
{
	if (is_string($status)) {
		$body = (string) file_get_contents(__DIR__ . '/fixtures/geliver/' . $status . '.json');
		$status = 200;
	}

	pg_label_load('geliver');
	pg_label_geliver_transport(function ($method, $url, $headers, $sent) use ($status, $body) {
		return array('status' => $status, 'body' => $body, 'error' => ($status === 0) ? 'Operation timed out' : '', 'sent' => ($status !== 0));
	});

	$answer = pg_label_call('geliver', 'track', array('4aaaf0b4-d071-4edc-a941-cea2f6a78453', shipment_tracking_test_account()));

	pg_label_geliver_transport(null, true);

	return $answer;
}

// The delivered fixture with another tracking status.
function shipment_tracking_test_shipment($code, $sub)
{
	$shipment = json_decode((string) file_get_contents(__DIR__ . '/fixtures/geliver/shipment_delivered.json'), true);
	$shipment['data']['statusCode'] = 'SHIPPED';
	$shipment['data']['trackingStatus']['trackingStatusCode'] = $code;
	$shipment['data']['trackingStatus']['trackingSubStatusCode'] = $sub;

	return json_encode($shipment);
}

function shipment_tracking_test_row($changes = array())
{
	return array_merge(array(
		'id'           => 7,
		'order_id'     => 42,
		'order_number' => '10042',
		'ship_to_id'   => 51,
		'driver'       => 'geliver',
		'reference'    => '4aaaf0b4-d071-4edc-a941-cea2f6a78453',
		'number'       => '64124002705',
		'carrier'      => 'ptt',
		'tracking_url' => '',
		'status'       => 'created',
	), $changes);
}

// Answers the stand-in drivers give in pass tests: driver => list of answers,
// taken in turn ('ok' an in-transit answer, 'delivered', or 'fail').
function shipment_tracking_test_asker($script)
{
	$GLOBALS['shipment_tracking_test_asked'] = array();

	return function ($row) use (&$script) {
		$GLOBALS['shipment_tracking_test_asked'][] = $row['driver'] . ':' . $row['id'];
		$next = $script[$row['driver']] ? array_shift($script[$row['driver']]) : 'ok';

		if ($next === 'fail') {
			return pg_label_failure('The carrier label service could not be reached.');
		}

		return pg_label_answer('track', array('success' => true, 'status' => ($next === 'delivered') ? 'delivered' : 'in_transit'));
	};
}

function shipment_tracking_test_lists($counts)
{
	$lists = array();

	foreach ($counts as $driver => $ids) {
		foreach ($ids as $id) {
			$lists[$driver][] = shipment_tracking_test_row(array('id' => $id, 'driver' => $driver));
		}
	}

	return $lists;
}

function test_shipment_tracking_job_in_the_light_lane()
{
	$jobs = pg_cron_jobs();
	pg_assert_true(isset($jobs['shipment_tracking_job']), 'catalogued');

	$job = $jobs['shipment_tracking_job'];
	pg_assert_same('light', pg_cron_lane($job), 'short calls with a time budget');
	pg_assert_same(3600, $job['interval'], 'hourly');
	pg_assert_true($job['dispatch'], 'the dispatcher may run it');
	pg_assert_true(empty($job['inline']), 'in the rotation, not on every tick');
	pg_assert_same('shipment_tracking_job.php', $job['script']);
	pg_assert_true(is_file(PG_FUNCTIONS_DIR . '/' . $job['script']), 'the script is there');

	$pick = pg_cron_pick(array('shipment_tracking_job' => $job), array(), 1800000000, array('shipment_tracking_job'));
	pg_assert_same(array('light' => 'shipment_tracking_job', 'heavy' => null), $pick, 'handed out when due');

	$script = (string) file_get_contents(PG_FUNCTIONS_DIR . '/shipment_tracking_job.php');
	pg_assert_contains("pg_cron_ran('shipment_tracking_job')", $script, 'records its run under its catalogue name');
	pg_assert_contains('validate_ecommerce_access($user)', $script, 'a web run needs the order rights');
}

function test_shipment_tracking_switched_on_with_a_service()
{
	pg_assert_contains('pg_shipment_tracking_job_switch_on()', pg_test_function_source('pg_label_settings_save'), 'saving a service that is on puts the job in the dispatch list');
	pg_assert_contains("'shipment_tracking_job'", pg_test_function_source('pg_shipment_tracking_job_switch_on'));
}

function test_shipment_tracking_delivered_from_geliver()
{
	$answer = shipment_tracking_test_track('shipment_delivered', '');
	$decision = pg_shipment_tracking_decide(shipment_tracking_test_row(array('carrier' => '')), $answer, 1800000000);

	pg_assert_true($decision['ok'], $decision['error']);
	pg_assert_true($decision['delivered'], 'an arrival');
	pg_assert_same('', $decision['status'], 'delivered is never written as a plain status');
	pg_assert_same(gmmktime(14, 29, 45, 4, 23, 2025), $decision['at'], "the carrier's time of the delivery");
	pg_assert_same(array('carrier' => 'ptt', 'tracking_url' => 'https://gonderitakip.ptt.gov.tr/Track/summary?id=KP123456789TR'), $decision['options'], "the carrier and its own tracking page");
	pg_assert_true($decision['write']);

	// A row that has them already writes nothing but the arrival.
	$known = shipment_tracking_test_row(array('tracking_url' => 'https://gonderitakip.ptt.gov.tr/Track/summary?id=KP123456789TR'));
	$decision = pg_shipment_tracking_decide($known, $answer, 1800000000);
	pg_assert_true($decision['delivered']);
	pg_assert_same(array(), $decision['options']);
	pg_assert_false($decision['write']);

	// A time the service gives in the future is now.
	pg_assert_same(1700000000, pg_shipment_tracking_decide($known, $answer, 1700000000)['at']);
}

function test_shipment_tracking_status_from_geliver()
{
	$cases = array(
		array('TRANSIT', 'package_departed', 'in_transit'),
		array('TRANSIT', 'out_for_delivery', 'out_for_delivery'),
		array('RETURNED', 'return_to_sender', 'returned'),
		array('FAILURE', 'package_lost', 'exception'),
	);

	foreach ($cases as $case) {
		$answer = shipment_tracking_test_track(200, shipment_tracking_test_shipment($case[0], $case[1]));
		$decision = pg_shipment_tracking_decide(shipment_tracking_test_row(), $answer, 1800000000);

		pg_assert_true($decision['ok'], $case[0] . ': ' . $decision['error']);
		pg_assert_false($decision['delivered'], $case[0]);
		pg_assert_same($case[2], $decision['status'], $case[0]);
		pg_assert_true($decision['write'], $case[0]);
	}

	// The same status again changes nothing.
	$answer = shipment_tracking_test_track(200, shipment_tracking_test_shipment('TRANSIT', 'package_departed'));
	$row = shipment_tracking_test_row(array('status' => 'in_transit', 'tracking_url' => 'https://gonderitakip.ptt.gov.tr/Track/summary?id=KP123456789TR'));
	$decision = pg_shipment_tracking_decide($row, $answer, 1800000000);
	pg_assert_true($decision['ok']);
	pg_assert_same('', $decision['status']);
	pg_assert_false($decision['write'], 'nothing to write');
}

function test_shipment_tracking_failures_change_nothing()
{
	$row = shipment_tracking_test_row();

	// Geliver not reached.
	$decision = pg_shipment_tracking_decide($row, shipment_tracking_test_track(0, ''), 1800000000);
	pg_assert_false($decision['ok']);
	pg_assert_same('The carrier label service could not be reached.', $decision['error']);
	pg_assert_false($decision['write']);
	pg_assert_false($decision['delivered']);

	// Refused: the words keep no secret of the account.
	$decision = pg_shipment_tracking_decide($row, shipment_tracking_test_track(401, (string) file_get_contents(__DIR__ . '/fixtures/geliver/error_token.json')), 1800000000);
	pg_assert_false($decision['ok']);
	pg_assert_contains('E1055', $decision['error']);
	pg_assert_false(strpos($decision['error'], 'gl-test-token-123456'), 'the token is masked');

	// No status Geliver gives.
	$decision = pg_shipment_tracking_decide($row, shipment_tracking_test_track(200, '{"result":true,"data":{"id":"s-1","statusCode":"","trackingStatus":null}}'), 1800000000);
	pg_assert_false($decision['ok']);

	// A driver that answered nothing usable.
	pg_assert_false(pg_shipment_tracking_decide($row, null, 1800000000)['ok']);
	pg_assert_false(pg_shipment_tracking_decide($row, array('success' => true, 'status' => 'lost'), 1800000000)['write'], 'an unknown status is not written');
}

function test_shipment_tracking_queue_takes_drivers_in_turn()
{
	$queue = pg_shipment_tracking_queue(shipment_tracking_test_lists(array('a' => array(1, 2, 3), 'b' => array(10))));
	$ids = array_map(function ($row) {
		return $row['id'];
	}, $queue);

	pg_assert_same(array(1, 10, 2, 3), $ids);
	pg_assert_same(array(), pg_shipment_tracking_queue(array()));
}

function test_shipment_tracking_pass_writes_and_counts()
{
	$applied = array();
	$progress = array();
	$apply = function ($row, $decision) use (&$applied) {
		$applied[] = $row['id'] . ':' . ($decision['delivered'] ? 'delivered' : $decision['status']);
	};

	$pass = pg_shipment_tracking_pass(
		shipment_tracking_test_lists(array('geliver' => array(3, 5, 8))),
		shipment_tracking_test_asker(array('geliver' => array('ok', 'delivered', 'fail'))),
		$apply,
		1800000100,
		100,
		function () {
			return 1800000000;
		},
		function ($driver, $id) use (&$progress) {
			$progress[] = $driver . ':' . $id;
		});

	pg_assert_same(array('3:in_transit', '5:delivered'), $applied, 'a failure writes nothing');
	pg_assert_same(array('geliver:3', 'geliver:5', 'geliver:8'), $progress, 'the place moves after every parcel asked about');
	pg_assert_same(3, $pass['asked']);
	pg_assert_same(2, $pass['changed']);
	pg_assert_same(1, $pass['delivered']);
	pg_assert_same(1, $pass['failed']);
	pg_assert_same(array('geliver' => 8), $pass['last']);
	pg_assert_same(array('geliver' => 'The carrier label service could not be reached.'), $pass['errors']);
	pg_assert_false($pass['timed_out']);
}

function test_shipment_tracking_pass_keeps_its_budgets()
{
	$apply = function () {
	};

	// Time: no question once the deadline has come.
	$clock = 1800000000;
	$pass = pg_shipment_tracking_pass(
		shipment_tracking_test_lists(array('geliver' => array(1, 2, 3, 4))),
		shipment_tracking_test_asker(array('geliver' => array())),
		$apply,
		1800000060,
		100,
		function () use (&$clock) {
			$clock += 25;

			return $clock;
		});

	pg_assert_same(2, $pass['asked'], 'two questions before the deadline');
	pg_assert_true($pass['timed_out']);
	pg_assert_same(array('geliver' => 2), $pass['last']);

	// Rows: no more than the batch.
	$pass = pg_shipment_tracking_pass(
		shipment_tracking_test_lists(array('a' => array(1, 2, 3), 'b' => array(4, 5, 6))),
		shipment_tracking_test_asker(array('a' => array(), 'b' => array())),
		$apply,
		1800000060,
		4,
		function () {
			return 1800000000;
		});

	pg_assert_same(4, $pass['asked']);
	pg_assert_same(array('a:1', 'b:4', 'a:2', 'b:5'), $GLOBALS['shipment_tracking_test_asked'], 'in turn');
	pg_assert_same(array('a' => 2, 'b' => 5), $pass['last']);
}

function test_shipment_tracking_pass_survives_a_failing_driver()
{
	$pass = pg_shipment_tracking_pass(
		shipment_tracking_test_lists(array('down' => array(1, 2, 3, 4, 5), 'up' => array(10, 11, 12, 13))),
		shipment_tracking_test_asker(array('down' => array('fail', 'fail', 'fail', 'fail', 'fail'), 'up' => array())),
		function () {
		},
		1800000060,
		100,
		function () {
			return 1800000000;
		});

	pg_assert_same(
		array('down:1', 'up:10', 'down:2', 'up:11', 'down:3', 'up:12', 'up:13'),
		$GLOBALS['shipment_tracking_test_asked'],
		'asked nothing more after three failures in a row; the other driver goes on');
	pg_assert_same(array('down' => true), $pass['stopped']);
	pg_assert_same(array('down' => 3, 'up' => 13), $pass['last'], 'the parcels not asked about wait after its place');
	pg_assert_same(3, $pass['failed']);

	// An answer between failures starts the count again.
	$pass = pg_shipment_tracking_pass(
		shipment_tracking_test_lists(array('flaky' => array(1, 2, 3, 4, 5, 6))),
		shipment_tracking_test_asker(array('flaky' => array('fail', 'fail', 'ok', 'fail', 'fail', 'ok'))),
		function () {
		},
		1800000060,
		100,
		function () {
			return 1800000000;
		});

	pg_assert_same(array(), $pass['stopped']);
	pg_assert_same(6, $pass['asked']);
}

function test_shipment_tracking_next_place()
{
	$rows = shipment_tracking_test_lists(array('geliver' => array(5, 7, 9)))['geliver'];

	pg_assert_same(0, pg_shipment_tracking_next_place(4, $rows, 9, 100), 'the last open parcel asked about: the round starts again');
	pg_assert_same(9, pg_shipment_tracking_next_place(4, $rows, 9, 3), 'a full batch: more may follow');
	pg_assert_same(7, pg_shipment_tracking_next_place(4, $rows, 7, 100), 'stopped part way: after the last one asked about');
	pg_assert_same(4, pg_shipment_tracking_next_place(4, $rows, null, 100), 'none asked: where it was');
	pg_assert_same(0, pg_shipment_tracking_next_place(4, array(), null, 100), 'nothing open');
}

function test_shipment_tracking_state_read()
{
	pg_assert_same(array('after' => array(), 'logged' => array()), pg_shipment_tracking_state_read(''));
	pg_assert_same(array('after' => array(), 'logged' => array()), pg_shipment_tracking_state_read('not json'));

	$state = pg_shipment_tracking_state_read('{"after":{"geliver":12,"Bad Key":3,"basit":"7","aras":-1},"logged":{"geliver":1800000000},"other":1}');
	pg_assert_same(array('after' => array('geliver' => 12), 'logged' => array('geliver' => 1800000000)), $state);
	pg_assert_same($state, pg_shipment_tracking_state_read(json_encode($state)), 'what is saved reads back');
}

function test_shipment_tracking_writes_through_the_write_path()
{
	pg_assert_same('in_transit', pg_shipment_status_reported('in_transit'));
	pg_assert_same('exception', pg_shipment_status_reported('exception'));
	pg_assert_same('', pg_shipment_status_reported('delivered'), 'an arrival only through pg_shipment_delivered()');
	pg_assert_same('', pg_shipment_status_reported('lost'));
	pg_assert_same('', pg_shipment_status_reported(null));

	$record = pg_test_function_source('pg_shipment_record');
	pg_assert_contains("pg_shipment_status_reported(\$opts['status'])", $record);
	pg_assert_contains('AND (delivered_at = 0) AND (status !=', $record, 'never over an arrival, only a change');
	pg_assert_contains("if (!empty(\$opts['existing_only'])) {", $record, 'a poll adds no row');

	$apply = pg_test_function_source('pg_shipment_tracking_apply');
	pg_assert_contains("'existing_only' => true", $apply);
	pg_assert_true(strpos($apply, 'pg_shipment_record(') < strpos($apply, 'pg_shipment_delivered('), 'the tracking page before the arrival');
	pg_assert_contains("if (!\$recorded['ok'] || !\$decision['delivered'])", $apply, 'no arrival for a parcel removed meanwhile');

	$open = pg_test_function_source('pg_shipment_tracking_open');

	foreach (array("(shipment_labels.state = 'active')", "(shipment_labels.is_return = '0')", "(shipping_tracking_numbers.delivered_at = '0')", "NOT IN ('delivered', 'returned')", "(orders.status NOT IN ('incomplete', 'cancelled'))", 'ORDER BY shipment_labels.id') as $part) {
		pg_assert_contains($part, $open);
	}
}
