<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the webhook events section of the API documentation
 * (includes/api/console_view.php): every event of the catalogue has its own
 * block with the fields of data and the body a delivery of its sample
 * carries, and every catalogue line links to its block.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

if (!defined('PG_API_PANEL')) {
	define('PG_API_PANEL', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/api/schema.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/console_view.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/outbound/webhooks.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/resources/webhooks.php');

function api_console_events_test_rows()
{
	return array(
		array(
			'name'        => 'order.created',
			'description' => 'A new <b>order</b> was placed',
			'module'      => '',
			'fields'      => array(
				array('name' => 'id', 'type' => 'integer', 'nullable' => false, 'description' => 'The order (GET /orders/{id}).'),
				array('name' => 'order_number', 'type' => 'string', 'nullable' => true, 'description' => 'Its number'),
			),
			'sample'      => array('id' => 1042, 'order_number' => null),
		),
		array(
			'name'        => 'erp.invoice.created',
			'description' => 'An invoice was issued',
			'module'      => 'erp',
			'fields'      => array(),
			'sample'      => null,
		),
	);
}

function test_api_console_event_anchor()
{
	pg_assert_same('ev_order_created', api_console_event_anchor('order.created'), 'dots become underscores');
	pg_assert_same('ev_erp_invoice_created', api_console_event_anchor('erp.invoice.created'), 'a module event');
}

// Each event gets a block with its anchor, its description escaped, each
// field with its type and meaning, null allowed said in words, and the
// envelope of its sample as a delivery carries it.
function test_api_console_event_blocks_show_fields_and_sample()
{
	$html = api_console_event_blocks_html(api_console_events_test_rows());

	pg_assert_true(strpos($html, 'id="ev_order_created"') !== false, 'the block has its anchor');
	pg_assert_true(strpos($html, 'A new &lt;b&gt;order&lt;/b&gt; was placed') !== false, 'the description is escaped');
	pg_assert_true(strpos($html, '<code>order_number</code>') !== false, 'a field name is listed');
	pg_assert_true(strpos($html, '<span class="doc-type">integer</span>') !== false, 'a field type is listed');
	pg_assert_true(strpos($html, 'The order (GET /orders/{id}).') !== false, 'a field meaning is listed');
	pg_assert_true(strpos($html, 'can be null &middot; Its number') !== false, 'a nullable field says so');
	pg_assert_true(substr_count($html, 'can be null') === 1, 'only the nullable field says so');

	$body = h(api_console_event_example('order.created', array('id' => 1042, 'order_number' => null)));

	pg_assert_true(strpos($html, $body) !== false, 'the sample is shown as the delivered envelope');
}

// The example is the delivered envelope, one key to a line; an empty object
// stays an object.
function test_api_console_event_example()
{
	$lines = explode("
", api_console_event_example('order.created', array('id' => 1042, 'order_number' => null)));

	pg_assert_same(array(
		'{',
		'    "event": "order.created",',
		'    "created_at": "2026-01-25T09:14:03Z",',
		'    "data": {',
		'        "id": 1042,',
		'        "order_number": null',
		'    }',
		'}',
	), $lines, 'the envelope, one key to a line');

	pg_assert_true(strpos(api_console_event_example('x.y', array('items' => (object) array(), 'url' => 'https://a.example/b')), '"items": {}') !== false, 'an empty object stays an object');
	pg_assert_true(strpos(api_console_event_example('x.y', array('url' => 'https://a.example/b')), '"https://a.example/b"') !== false, 'slashes are not escaped');
}

// An event with no fields and no sample says so instead of printing an empty
// table or an empty example; a module's event names its module.
function test_api_console_event_blocks_without_fields()
{
	$rows = api_console_events_test_rows();

	$html = api_console_event_blocks_html(array($rows[1]));

	pg_assert_true(strpos($html, 'id="ev_erp_invoice_created"') !== false, 'the block has its anchor');
	pg_assert_true(strpos($html, 'This event carries no fields in data.') !== false, 'no fields is said in words');
	pg_assert_true(strpos($html, '<table') === false, 'no empty table');
	pg_assert_true(strpos($html, 'doc-curl') === false, 'no empty example');
	pg_assert_true(strpos($html, '<span class="doc-scope">ERP</span>') !== false, 'the module is named');
}

// The live catalogue: every event has one block, every catalogue line links
// to an anchor that exists, and every block shows an example.
function test_api_console_events_every_event_has_a_block()
{
	$html = api_console_events_html();

	$events = array_keys(api_webhook_events());

	pg_assert_true(count($events) > 0, 'the catalogue has events');

	$missing = array();

	foreach ($events as $event) {
		$anchor = api_console_event_anchor($event);
		if (substr_count($html, 'id="' . $anchor . '"') !== 1 || strpos($html, 'href="#' . $anchor . '"') === false) {
			$missing[] = $event;
		}
	}

	pg_assert_same(array(), $missing, 'every event has one block and a link to it');
	pg_assert_same(count($events), substr_count($html, 'class="doc-ep doc-event"'), 'one block per event');
	pg_assert_same(count($events), substr_count($html, '<div class="doc-curl doc-sample mt-1">'), 'every block shows an example');
}
