<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the day pg_shipment_delivered() (includes/fn/shipping.php)
 * announces: the day the goods arrived, not the delivery date the address
 * holds, which the carrier tracking job and a marketplace poll leave at
 * checkout's estimate. Run in a PHP process of its own with a fake db layer.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Calls pg_shipment_delivered() for an address whose stored delivery date is
// $stored, with the goods arriving at noon UTC on $arrived, and returns the
// order.delivered payload it announced. Null when PHP cannot be started again.
function shipment_delivered_day_test_run($stored, $arrived)
{
	if (!function_exists('exec') || !defined('PHP_BINARY') || (PHP_BINARY === '')) {
		return null;
	}

	$file = @tempnam(sys_get_temp_dir(), 'pgshipday');

	if ($file === false) {
		return null;
	}

	$script = <<<'PHP'
date_default_timezone_set('UTC');
define('PG_FUNCTIONS_DIR', $pg_dir);

$GLOBALS['announced'] = array();

function db_item($sql)
{
	if (strpos($sql, 'FROM ship_tos') !== false) {
		return array(
			'id'                    => 7,
			'order_id'              => 3,
			'delivery_date'         => $GLOBALS['stored'],
			'order_number'          => '1003',
			'contact_id'            => 0,
			'billing_email_address' => 'buyer@example.com',
		);
	}
	throw new Exception('unexpected db_item: ' . $sql);
}

function db_value($sql) { return ''; }
function db_items($sql) { return array(); }
function pg_shipping_default_carrier() { return ''; }
function pg_announce($event, $payload) { $GLOBALS['announced'][] = array($event, $payload); }

require $pg_dir . '/includes/fn/shipping.php';

$result = pg_shipment_delivered(7, strtotime($GLOBALS['arrived'] . ' 12:00:00'), array('notify' => false, 'first' => true));

echo json_encode(array('result' => $result, 'announced' => $GLOBALS['announced']));
PHP;

	file_put_contents($file, '<?php' . "\n"
		. '$pg_dir = ' . var_export(PG_FUNCTIONS_DIR, true) . ";\n"
		. '$GLOBALS[\'stored\'] = ' . var_export((string) $stored, true) . ";\n"
		. '$GLOBALS[\'arrived\'] = ' . var_export((string) $arrived, true) . ";\n"
		. $script . "\n");

	$lines = array();
	$code = 1;
	@exec(escapeshellarg(PHP_BINARY) . ' -d display_errors=stderr -d error_reporting=-1 ' . escapeshellarg($file) . ' 2>&1', $lines, $code);
	@unlink($file);

	$result = json_decode(implode("\n", $lines), true);

	if (($code !== 0) || !is_array($result) || !isset($result['announced'])) {
		return array('error' => implode("\n", $lines));
	}

	return $result;
}

// Goods that arrived on 28 October are announced as delivered on 28 October,
// although the address still holds checkout's estimate of 4 November.
function test_shipment_delivered_announces_the_arrival_day()
{
	$run = shipment_delivered_day_test_run('2026-11-04', '2026-10-28');

	if ($run === null) {
		pg_assert_true(true, 'PHP cannot be started again here');
		return;
	}

	pg_assert_false(isset($run['error']), isset($run['error']) ? $run['error'] : '');
	pg_assert_true($run['result'], 'announced');
	pg_assert_same(1, count($run['announced']), 'one event');
	pg_assert_same('order.delivered', $run['announced'][0][0]);
	pg_assert_same('2026-10-28', $run['announced'][0][1]['delivery_date']);
}

// An address with no delivery date stored gets the arrival day too.
function test_shipment_delivered_empty_date_announces_the_arrival_day()
{
	$run = shipment_delivered_day_test_run('0000-00-00', '2026-10-30');

	if ($run === null) {
		pg_assert_true(true, 'PHP cannot be started again here');
		return;
	}

	pg_assert_false(isset($run['error']), isset($run['error']) ? $run['error'] : '');
	pg_assert_same('2026-10-30', $run['announced'][0][1]['delivery_date']);
}
