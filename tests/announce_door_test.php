<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests that every announced moment goes through pg_announce(): no product
 * file but the door itself queues a webhook with api_webhook_enqueue(), the
 * ERP module's erp_event() hands its events to the door, and the places that
 * called the queue directly keep what they did (the Workspace record and the
 * queue, without the in-process listeners).
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

// The files that queue an event without the in-process listeners, with the
// events each one announces that way.
function announce_door_test_quiet_calls()
{
	return array(
		'product_builder.php'                       => array('product.deleted'),
		'custom_form.php'                           => array('form.submitted'),
		'update_order.php'                          => array('order.shipped'),
		'shipworks.php'                             => array('order.shipped'),
		'includes/api/outbound/orders.php'          => array('order.shipped'),
		'includes/api/resources/product_groups.php' => array('product_group.updated', 'product_group.updated', 'product_group.deleted', 'product_group.deleted'),
		'includes/api/resources/pages.php'          => array('page.updated'),
		'includes/api/resources/files.php'          => array('file.created'),
		'includes/api/resources/customers.php'      => array('customer.created', 'customer.updated', 'customer.deleted'),
		'includes/api/resources/orders.php'         => array('order.status_changed'),
		'includes/api/resources/products.php'       => array('product.created', 'product.updated', 'product.updated', 'product.updated', 'product.deleted', 'product.updated'),
		'includes/api/resources/inventory.php'      => array('inventory.changed', 'stock.low'),
		'includes/fn/ecommerce.php'                 => array('order.cancelled'),
		'includes/fn/order_completion.php'          => array('order.created'),
	);
}

// The statement of every pg_announce() call in a source, from the name to its
// semicolon.
function announce_door_test_statements($source)
{
	$found = array();
	$offset = 0;

	while (($at = strpos($source, 'pg_announce(', $offset)) !== false) {
		$end = strpos($source, ';', $at);
		$found[] = substr($source, $at, ($end === false) ? null : $end - $at);
		$offset = $at + 1;
	}

	return $found;
}

function test_announce_door_no_direct_enqueue_outside_the_door()
{
	$root = rtrim(str_replace('\\', '/', PG_FUNCTIONS_DIR), '/') . '/';
	$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(PG_FUNCTIONS_DIR, FilesystemIterator::SKIP_DOTS));

	foreach ($files as $file) {
		$path = str_replace('\\', '/', (string) $file);

		if ((substr($path, -4) !== '.php') || (strpos($path, '/vendor/') !== false)) {
			continue;
		}

		$relative = substr($path, strlen($root));

		if (in_array($relative, array('includes/fn/events.php', 'includes/api/outbound/webhooks.php'), true)) {
			continue;
		}

		$source = (string) file_get_contents($path);

		if (strpos($source, 'api_webhook_enqueue') === false) {
			continue;
		}

		$tokens = token_get_all($source);

		foreach ($tokens as $i => $token) {
			if (!is_array($token) || ($token[0] !== T_STRING) || ($token[1] !== 'api_webhook_enqueue')) {
				continue;
			}

			$next = $i + 1;

			while (isset($tokens[$next]) && is_array($tokens[$next]) && ($tokens[$next][0] === T_WHITESPACE)) {
				$next++;
			}

			pg_assert_false(isset($tokens[$next]) && ($tokens[$next] === '('), $relative . ':' . $token[2] . ' announces through pg_announce(), not api_webhook_enqueue()');
		}
	}
}

function test_announce_door_quiet_callers_keep_their_behaviour()
{
	foreach (announce_door_test_quiet_calls() as $relative => $events) {
		$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $relative);
		$quiet = array();

		foreach (announce_door_test_statements($source) as $statement) {
			if (strpos($statement, "array('listeners' => false)") === false) {
				continue;
			}

			if (preg_match("/^pg_announce\\('([a-z_.]+)'/", $statement, $match)) {
				$quiet[] = $match[1];
			}
		}

		pg_assert_same($events, $quiet, $relative . ': the events queued without the listeners');
	}
}

function test_announce_door_erp_event_goes_through_the_door()
{
	// The ERP module is not loaded by the runner: its source is read.
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/erp/events.php');
	$start = strpos($source, "\nfunction erp_event(");
	$end = ($start === false) ? false : strpos($source, "\nfunction ", $start + 1);
	$body = ($start === false) ? '' : (($end === false) ? substr($source, $start) : substr($source, $start, $end - $start));

	pg_assert_contains("pg_announce((string) \$event, (array) \$payload, array('listeners' => false))", $body, 'erp_event() hands its event to the door');
	pg_assert_false(strpos($body, 'pg_event_record(') !== false, 'the door records it: no second Workspace record');
	pg_assert_false(strpos($body, 'api_webhook_enqueue') !== false, 'no direct queue');

	$audit = strpos($body, 'erp_audit_event(');
	$alert = strpos($body, 'erp_alert_event(');
	$door = strpos($body, 'pg_announce(');

	pg_assert_true(($audit !== false) && ($alert !== false) && ($audit < $alert) && ($alert < $door), 'the audit trail and the alerts first, as before');
}

function test_announce_door_listeners_only_when_asked()
{
	$body = pg_test_function_source('pg_announce');

	$record = strpos($body, 'pg_event_record(');
	$listen = strpos($body, 'pg_announce_listeners(');
	$queue = strpos($body, 'api_webhook_enqueue($event, (array) $data, true)');

	pg_assert_true(($record !== false) && ($listen !== false) && ($queue !== false) && ($record < $listen) && ($listen < $queue), 'recorded, heard, then queued as recorded');
	pg_assert_contains("if (!isset(\$options['listeners']) || \$options['listeners'])", $body, 'the listeners run unless the caller says no');

	$listeners = pg_test_function_source('pg_announce_listeners');

	foreach (array('pg_page_cache_mark_dirty(', 'pg_stock_notify_heard(', 'pg_ap_heard(', 'pg_contact_group_joined_heard(', 'pg_feedback_heard(', 'pg_notify_announced(') as $call) {
		pg_assert_contains($call, $listeners, $call . ' is a listener');
		pg_assert_false(strpos($body, $call) !== false, $call . ' runs only through the listeners');
	}
}

// With no database the queue takes nothing, and the door says so with a count.
function test_announce_door_returns_the_rows_queued()
{
	if (defined('DB_CONNECTED')) {
		pg_assert_true(true);

		return;
	}

	pg_assert_same(0, pg_announce('product.updated', array('id' => 1, 'name' => 'Test'), array('listeners' => false)));
}
