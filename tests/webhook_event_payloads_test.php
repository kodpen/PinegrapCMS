<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the fields and samples of the webhook events
 * (api_webhook_event_payloads()): each field set is the set of keys the
 * announcing code writes, read from the code itself (a literal array by the
 * tokenizer, a payload function by calling it), each sample has exactly those
 * keys with values of the declared types; GET /webhooks/events is routed as
 * its own route, not as /webhooks/{id}; the event list it answers; and the
 * checks of a sample sent by POST /webhooks/{id}/test.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

if (!defined('PG_API_ENTRY')) {
	define('PG_API_ENTRY', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/api/router.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/schema.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/outbound/webhooks.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/outbound/orders.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/resources/webhooks.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/resources/users.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/outbound/webhook_payloads.php');
require_once(PG_FUNCTIONS_DIR . '/includes/erp/api.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/api.php');
foreach (array('tasks', 'flow_run_events', 'messages', 'app_commands', 'groups', 'wtable_shares') as $webhook_payloads_test_file) {
	require_once(PG_FUNCTIONS_DIR . '/includes/workspace/' . $webhook_payloads_test_file . '.php');
}

// Every event a subscription may name, the modules' too, whether or not the
// module is switched on in this run.
function webhook_payloads_test_catalogue()
{
	return api_webhook_core_events() + erp_webhook_events() + ws_webhook_events();
}

// The events that must have their fields and sample: every one.
function webhook_payloads_test_in_family($event)
{
	static $events = null;

	if ($events === null) {
		$events = webhook_payloads_test_catalogue();
	}

	return isset($events[$event]);
}

// The payload functions the announcing code calls, each run on a fixture:
// the keys it writes are the keys it answers. A fixture may take the event,
// for a function whose keys depend on what its caller hands it.
function webhook_payloads_test_builders()
{
	return array(
		'pg_return_event_payload' => function () {
			return pg_return_event_payload(array(
				'id' => 7, 'order_id' => 3, 'ship_to_id' => 4, 'status' => 'requested', 'source' => 'account',
				'reason' => 'damaged', 'items' => array(array('order_item_id' => 9, 'quantity' => 1)),
			), '1001');
		},
		'pg_cart_quote_event_payload' => function () {
			return pg_cart_quote_event_payload(array('id' => 5, 'status' => 'open', 'contact_id' => 2, 'order_id' => 0, 'valid_until' => 0), array(array('product_id' => 3, 'quantity' => 2)), 1000);
		},
		'pg_credit_event_data' => function () {
			return pg_credit_event_data(array('id' => 4, 'user_id' => 2, 'kind' => 'refund', 'reason' => '', 'amount' => 100, 'balance' => 100, 'expires_at' => 0, 'customer_id' => 8));
		},
		'mp_order_event_payload' => function () {
			return mp_order_event_payload(array('id' => 1, 'provider' => 'trendyol'), array('order_id' => 3, 'remote_order_id' => 'A', 'remote_package_id' => 'B', 'remote_status' => 'Created'), '1001');
		},
		'pg_subscription_webhook_data' => function () {
			return pg_subscription_webhook_data(array('id' => 2, 'product_id' => 3, 'status' => 'active', 'amount' => 100, 'currency' => 'USD', 'next_charge_at' => 0, 'retry_count' => 0), 8, 'renewed', 'active', 5);
		},
		'pg_booking_event_payload' => function () {
			return pg_booking_event_payload(array('id' => 1, 'service_id' => 2, 'staff_id' => 3, 'user_id' => 0, 'name' => 'A', 'email' => 'a@example.com', 'start_at' => 0, 'end_at' => 0, 'status' => 'confirmed', 'source' => 'site'));
		},
		'pg_commission_status_event' => function () {
			return pg_commission_status_event(array('id' => 1, 'reference_code' => 'R', 'affiliate_code' => 'A', 'order_id' => 3, 'amount' => 10, 'status' => 'pending'), 'payable');
		},
		'pg_referral_rewarded_event' => function () {
			return pg_referral_rewarded_event(array('id' => 1, 'reference_code' => 'C', 'affiliate_code' => 'R1-0000000000', 'order_id' => 3, 'amount' => 10, 'contact_id' => 4, 'user_id' => 5, 'card_id' => 6));
		},
		'pg_review_created_event' => function () {
			return pg_review_created_event(4, 'product', 3, 5, 0, '1', '');
		},
		'pg_stock_event_location_fields' => function () {
			return pg_stock_event_location_fields(array('id' => 2, 'name' => 'Depot', 'quantity' => 3));
		},
		'pg_shipment_event_values' => function () {
			return pg_shipment_event_values(array());
		},
		'pg_store_currency_event' => function () {
			return pg_store_currency_event('EUR', 2, '0.0281', 0);
		},
		'pg_affiliate_approved_event' => function () {
			return pg_affiliate_approved_event(5, 'ayse10', 'Ayşe');
		},
		'pg_order_reservation_event' => function () {
			return pg_order_reservation_event(array('calendar_event_id' => 3, 'recurrence_number' => 0, 'quantity' => 2, 'order_item_id' => 9), 4, array('order_id' => 1, 'order_number' => 1001), '2026-11-20 10:00:00');
		},
		'pg_campaign_sent_event' => function () {
			return pg_campaign_sent_event('email', 4, 'manual');
		},
		'pg_content_review_due_payload' => function () {
			return pg_content_review_due_payload(array('item_id' => 3, 'item_kind' => 'page', 'user_id' => 2, 'review_on' => '2026-11-17'), 'About', 'Deniz');
		},
		'pg_form_paid_event_payload' => function () {
			return pg_form_paid_event_payload(array('id' => 4, 'page_id' => 2, 'reference_code' => 'R', 'contact_id' => 3, 'order_id' => 1, 'order_number' => '1001', 'paid_at' => 1000));
		},
		'api_user_event_payload' => function () {
			return api_user_event_payload(3, array('username' => 'deniz', 'email' => 'd@example.com', 'role' => 2));
		},
		'pg_whatsapp_received_event' => function () {
			return pg_whatsapp_received_event(4, array('from' => '905551112233', 'profile_name' => 'A', 'type' => 'text', 'body' => 'Hi'), 7);
		},
		'pg_consent_changed_event' => function () {
			return pg_consent_changed_event(9, array('channel' => 'email', 'purpose' => 'marketing', 'status' => 'granted', 'source' => 'checkout', 'created_at' => 1000), 4);
		},
		'ws_task_event_payload' => function () {
			return ws_task_event_payload(array('id' => 1, 'title' => 'T', 'status' => 'todo', 'priority' => 'normal', 'due_date' => '', 'department_id' => 0, 'channel_id' => 2, 'creator_id' => 3), array(3));
		},
		'ws_flow_run_event_body' => function () {
			return ws_flow_run_event_body(array('id' => 1, 'channel_id' => 2, 'flow_id' => 3), array('flow_name' => 'F'), true, array());
		},
		'ws_app_mention_payload' => function () {
			return ws_app_mention_payload(array('id' => 1, 'sender_kind' => 'user', 'sender_id' => 2, 'parent_id' => 0, 'kind' => 'text', 'body' => 'b', 'task_id' => 0), array('id' => 3, 'name' => 'c', 'kind' => 'public'), null, 4, 't');
		},
		'ws_app_command_payload' => function () {
			return ws_app_command_payload(array('id' => 3, 'name' => 'c', 'kind' => 'public'), null, 4, 'stock', 2, 't', 'b');
		},
		'ws_group_event_payload' => function () {
			return ws_group_event_payload(array('id' => 1, 'name' => 'G', 'parent_id' => 0, 'color' => 0), '');
		},
		// Only an update hands it the columns written.
		'ws_wtable_row_event_body' => function ($event = '') {
			return ws_wtable_row_event_body(array('id' => 1, 'name' => 'T'), array('id' => 2), array(3), 4, ($event === 'workspace.wtable.row.updated') ? array(5) : array());
		},
		'pg_contact_group_members_event' => function () {
			return pg_contact_group_members_event(3, 'Newsletter', array(4), array());
		},
	);
}

// The helpers that announce through a payload function and lay the literal
// array of one argument over it: the function, and that argument's place.
function webhook_payloads_test_helpers()
{
	return array(
		'pg_return_announce'     => array('pg_return_event_payload', 2),
		'pg_cart_quote_announce' => array('pg_cart_quote_event_payload', 2),
		'pg_credit_announce'     => array('pg_credit_event_data', 2),
		'mp_order_announce'      => array('mp_order_event_payload', 4),
		'ws_group_announce'      => array('ws_group_event_payload', 2),
	);
}

// The ERP's announcing helpers (includes/erp/events.php): each reads its
// record and calls erp_event() with a literal array. The place of the event
// name in a call (null: the helper names it itself), the place of the extra
// fields laid over it (null: none), the event a call without the name means,
// and the events only for which the helper adds $payload['…'] keys.
function webhook_payloads_test_erp_helpers()
{
	return array(
		'erp_event_invoice'        => array('event' => 1, 'extra' => 2, 'default' => null, 'adds_for' => array()),
		'erp_event_receipt'        => array('event' => 1, 'extra' => null, 'default' => null, 'adds_for' => array()),
		'erp_event_account'        => array('event' => null, 'extra' => null, 'default' => null, 'adds_for' => array()),
		'erp_event_waybill'        => array('event' => 1, 'extra' => null, 'default' => 'erp.waybill.created', 'adds_for' => array()),
		'erp_event_expense'        => array('event' => 1, 'extra' => null, 'default' => null, 'adds_for' => array()),
		'erp_event_quote'          => array('event' => 1, 'extra' => null, 'default' => null, 'adds_for' => array('erp.quote.signed')),
		'erp_event_purchase_order' => array('event' => 1, 'extra' => null, 'default' => null, 'adds_for' => array()),
		'erp_event_stock_count'    => array('event' => null, 'extra' => null, 'default' => null, 'adds_for' => array()),
	);
}

// The calls whose event name is not written where they are, by file: the
// events they may announce (a family, its literal keys counted for each of
// them; 'stock' for pg_product_stock_events(), run on its own; 'other' for a
// module's; '' for a function that only passes an announcement on). A choice
// between names written in place ($new ? 'a.created' : 'a.updated') is read
// as those names.
function webhook_payloads_test_dynamic_calls()
{
	return array(
		'includes/fn/events.php'                => '',
		'includes/erp/events.php'               => '',
		'includes/fn/returns.php'               => 'order.return.',
		'includes/fn/cart_quotes.php'           => 'cart_quote.',
		'includes/fn/store_credit_events.php'   => 'credit.',
		'includes/fn/subscription_renewal.php'  => 'subscription.',
		'includes/api/outbound/orders.php'      => 'marketplace.order.',
		'includes/fn/ecommerce.php'             => 'stock',
		'includes/workspace/groups.php'         => '',
		'includes/fn/experiments_sheet.php'     => 'experiment.',
		'includes/erp/quotes.php'               => 'erp.quote.',
	);
}

function webhook_payloads_test_skip(array $tokens, $i)
{
	$count = count($tokens);

	while (($i < $count) && is_array($tokens[$i]) && in_array($tokens[$i][0], array(T_WHITESPACE, T_COMMENT, T_DOC_COMMENT), true)) {
		$i++;
	}

	return $i;
}

function webhook_payloads_test_text($token)
{
	return is_array($token) ? $token[1] : $token;
}

// The arguments of the call whose '(' is at $open, each as its list of
// tokens without the blanks around it.
function webhook_payloads_test_arguments(array $tokens, $open)
{
	$arguments = array();
	$current = array();
	$depth = 0;

	for ($i = $open + 1, $count = count($tokens); $i < $count; $i++) {
		$text = webhook_payloads_test_text($tokens[$i]);

		if (in_array($text, array('(', '[', '{'), true) || (is_array($tokens[$i]) && in_array($tokens[$i][0], array(T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES), true))) {
			$depth++;
		} elseif (in_array($text, array(')', ']', '}'), true)) {
			if ($depth === 0) {
				break;
			}

			$depth--;
		} elseif (($text === ',') && ($depth === 0)) {
			$arguments[] = $current;
			$current = array();
			continue;
		}

		if (!is_array($tokens[$i]) || !in_array($tokens[$i][0], array(T_WHITESPACE, T_COMMENT, T_DOC_COMMENT), true)) {
			$current[] = $tokens[$i];
		}
	}

	if (!empty($current)) {
		$arguments[] = $current;
	}

	return $arguments;
}

// The builder functions an argument of which is laid over what they answer
// (ws_flow_run_event_body()'s $about), by that argument's place.
function webhook_payloads_test_builder_arguments()
{
	return array('ws_flow_run_event_body' => 3);
}

// The string keys of the literal array whose array( or [ is at $i, and the
// place just after it.
function webhook_payloads_test_literal_keys(array $argument, $i)
{
	$keys = array();
	$i += ($argument[$i] === '[') ? 1 : 2;
	$depth = 0;
	$expect = true;

	for ($count = count($argument); $i < $count; $i++) {
		$text = webhook_payloads_test_text($argument[$i]);

		if (in_array($text, array('(', '['), true)) {
			$depth++;
			continue;
		}

		if (in_array($text, array(')', ']'), true)) {
			if ($depth === 0) {
				return array($keys, $i + 1);
			}

			$depth--;
			continue;
		}

		if ($depth > 0) {
			continue;
		}

		if ($text === ',') {
			$expect = true;
			continue;
		}

		if ($expect && is_array($argument[$i]) && ($argument[$i][0] === T_CONSTANT_ENCAPSED_STRING)) {
			$keys[] = stripslashes(substr($argument[$i][1], 1, -1));
		}

		$expect = false;
	}

	return array($keys, $i);
}

// What one payload argument writes, read at its own level: the string keys
// of its literal arrays (at the start or laid over with +), the keys of a
// variable laid over with + (read from $tokens, the file, before $line), the
// functions it calls (a payload function, or one whose answer is added with
// +), and the keys of a builder's own argument that is laid over its answer.
// A function called inside a value (a nested task) is not the payload's.
function webhook_payloads_test_payload(array $argument, array $tokens = array(), $line = 0)
{
	$out = array('keys' => array(), 'calls' => array(), 'literal' => false, 'variable' => '');

	if (empty($argument)) {
		return $out;
	}

	if (is_array($argument[0]) && ($argument[0][0] === T_VARIABLE) && (count($argument) === 1)) {
		$out['variable'] = $argument[0][1];
		return $out;
	}

	$takes = webhook_payloads_test_builder_arguments();
	$depth = 0;

	for ($i = 0, $count = count($argument); $i < $count; $i++) {
		$text = webhook_payloads_test_text($argument[$i]);
		$starts = ($depth === 0) && (($i === 0) || ($argument[$i - 1] === '+'));

		if ($starts && ((is_array($argument[$i]) && ($argument[$i][0] === T_ARRAY)) || ($text === '['))) {
			list($keys, $next) = webhook_payloads_test_literal_keys($argument, $i);
			$out['keys'] = array_merge($out['keys'], $keys);
			$out['literal'] = $out['literal'] || ($i === 0);
			$i = $next - 1;
			continue;
		}

		if ($starts && is_array($argument[$i]) && ($argument[$i][0] === T_VARIABLE) && ($i > 0)) {
			$found = webhook_payloads_test_variable($tokens, $argument[$i][1], $line);
			$out['keys'] = array_merge($out['keys'], $found['keys']);
			$out['calls'] = array_merge($out['calls'], $found['calls']);
			continue;
		}

		if (($depth === 0) && is_array($argument[$i]) && ($argument[$i][0] === T_STRING) && isset($argument[$i + 1]) && ($argument[$i + 1] === '(')) {
			$out['calls'][] = $argument[$i][1];

			if (isset($takes[$argument[$i][1]])) {
				$inner = webhook_payloads_test_arguments($argument, $i + 1);
				$given = isset($inner[$takes[$argument[$i][1]]]) ? $inner[$takes[$argument[$i][1]]] : array();
				$found = webhook_payloads_test_payload($given, $tokens, $line);

				if ($found['variable'] !== '') {
					$found = webhook_payloads_test_variable($tokens, $found['variable'], $line);
				}

				$out['keys'] = array_merge($out['keys'], $found['keys']);
			}
		}

		$depth += (in_array($text, array('(', '['), true) ? 1 : 0) - (in_array($text, array(')', ']'), true) ? 1 : 0);
	}

	return $out;
}

// The literal array a variable was last given before $line in the same
// file: the stock API builds its payload in a variable first.
function webhook_payloads_test_variable(array $tokens, $variable, $line)
{
	$found = array('keys' => array(), 'calls' => array(), 'literal' => false, 'variable' => '');

	for ($i = 0, $count = count($tokens); $i < $count; $i++) {
		if (!is_array($tokens[$i]) || ($tokens[$i][0] !== T_VARIABLE) || ($tokens[$i][1] !== $variable) || ($tokens[$i][2] >= $line)) {
			continue;
		}

		$next = webhook_payloads_test_skip($tokens, $i + 1);

		if (webhook_payloads_test_text($tokens[$next]) !== '=') {
			continue;
		}

		$argument = array();

		for ($j = $next + 1; ($j < $count) && ($tokens[$j] !== ';'); $j++) {
			if (!is_array($tokens[$j]) || !in_array($tokens[$j][0], array(T_WHITESPACE, T_COMMENT, T_DOC_COMMENT), true)) {
				$argument[] = $tokens[$j];
			}
		}

		$found = webhook_payloads_test_payload($argument, $tokens, $tokens[$i][2]);
	}

	return $found;
}

// Every call of pg_announce(), api_webhook_enqueue() and the announcing
// helpers in the product's code: the file, the line, the function called,
// the event (name, prefix or none) and what its payload writes.
function webhook_payloads_test_calls()
{
	static $calls = null;

	if ($calls !== null) {
		return $calls;
	}

	$calls = array();
	$helpers = webhook_payloads_test_helpers();
	$erp = webhook_payloads_test_erp_helpers();
	$names = array_merge(array('pg_announce', 'api_webhook_enqueue', 'erp_event'), array_keys($helpers), array_keys($erp));
	$root = rtrim(str_replace('\\', '/', PG_FUNCTIONS_DIR), '/') . '/';
	$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(PG_FUNCTIONS_DIR, FilesystemIterator::SKIP_DOTS));

	foreach ($files as $file) {
		$path = str_replace('\\', '/', (string) $file);

		if ((substr($path, -4) !== '.php') || (strpos($path, '/vendor/') !== false)) {
			continue;
		}

		$source = (string) file_get_contents($path);

		if ((strpos($source, 'pg_announce') === false) && (strpos($source, 'api_webhook_enqueue') === false) && (strpos($source, '_announce(') === false) && (strpos($source, 'erp_event') === false)) {
			continue;
		}

		$tokens = token_get_all($source);
		$relative = substr($path, strlen($root));

		$within = '';

		foreach ($tokens as $i => $token) {
			if (is_array($token) && ($token[0] === T_FUNCTION)) {
				$next = webhook_payloads_test_skip($tokens, $i + 1);

				if (is_array($tokens[$next]) && ($tokens[$next][0] === T_STRING)) {
					$within = $tokens[$next][1];
				}
			}

			if (!is_array($token) || ($token[0] !== T_STRING) || !in_array($token[1], $names, true)) {
				continue;
			}

			$before = $i - 1;

			while (($before >= 0) && is_array($tokens[$before]) && ($tokens[$before][0] === T_WHITESPACE)) {
				$before--;
			}

			if (($before >= 0) && is_array($tokens[$before]) && in_array($tokens[$before][0], array(T_FUNCTION, T_OBJECT_OPERATOR, T_DOUBLE_COLON), true)) {
				continue;
			}

			$open = webhook_payloads_test_skip($tokens, $i + 1);

			if (webhook_payloads_test_text($tokens[$open]) !== '(') {
				continue;
			}

			$arguments = webhook_payloads_test_arguments($tokens, $open);
			$spot = isset($erp[$token[1]]) ? $erp[$token[1]]['event'] : 0;
			$event = (($spot !== null) && isset($arguments[$spot])) ? $arguments[$spot] : array();
			$name = null;
			$prefix = false;

			if (isset($event[0]) && is_array($event[0]) && ($event[0][0] === T_CONSTANT_ENCAPSED_STRING)) {
				$name = array(stripslashes(substr($event[0][1], 1, -1)));
				$prefix = (count($event) > 1) && (webhook_payloads_test_text($event[1]) === '.');
			}

			// $created ? 'zone.created' : 'zone.updated': every name in place,
			// unless a function gives one.
			$calls_one = false;

			foreach ($event as $at => $part) {
				$calls_one = $calls_one || (is_array($part) && ($part[0] === T_STRING) && isset($event[$at + 1]) && ($event[$at + 1] === '('));
			}

			if (in_array('?', array_map('webhook_payloads_test_text', $event), true) && !$calls_one) {
				$name = array();

				foreach ($event as $part) {
					if (is_array($part) && ($part[0] === T_CONSTANT_ENCAPSED_STRING)) {
						$name[] = stripslashes(substr($part[1], 1, -1));
					}
				}

				$name = empty($name) ? null : $name;
			}

			if (isset($erp[$token[1]]) && ($name === null) && ($spot !== null) && !isset($arguments[$spot]) && ($erp[$token[1]]['default'] !== null)) {
				$name = array($erp[$token[1]]['default']);
			}

			$place = isset($helpers[$token[1]]) ? $helpers[$token[1]][1] : (isset($erp[$token[1]]) ? $erp[$token[1]]['extra'] : 1);
			$payload = webhook_payloads_test_payload((($place !== null) && isset($arguments[$place])) ? $arguments[$place] : array(), $tokens, $token[2]);

			if ($payload['variable'] !== '') {
				$payload = webhook_payloads_test_variable($tokens, $payload['variable'], $token[2]);
			}

			// The $payload['…'] = keys a helper assigns in its own body.
			$assigned = array();

			for ($j = $i; ($token[1] === 'erp_event') && ($j > 0) && !(is_array($tokens[$j]) && ($tokens[$j][0] === T_FUNCTION)); $j--) {
				if (is_array($tokens[$j]) && ($tokens[$j][0] === T_VARIABLE) && ($tokens[$j][1] === '$payload') && ($tokens[$j + 1] === '[')
					&& is_array($tokens[$j + 2]) && ($tokens[$j + 2][0] === T_CONSTANT_ENCAPSED_STRING)) {
					$assigned[] = stripslashes(substr($tokens[$j + 2][1], 1, -1));
				}
			}

			if (isset($helpers[$token[1]])) {
				$payload['calls'][] = $helpers[$token[1]][0];
			}

			$calls[] = array(
				'file'     => $relative,
				'line'     => $token[2],
				'function' => $token[1],
				'event'    => $name,
				'prefix'   => $prefix,
				'payload'  => $payload,
				'within'   => $within,
				'assigned' => $assigned,
			);
		}
	}

	return $calls;
}

// The keys a set of payload functions write, run on their fixtures.
function webhook_payloads_test_builder_keys(array $functions, $where, $event = '')
{
	$builders = webhook_payloads_test_builders();
	$keys = array();

	foreach ($functions as $function) {
		if (!isset($builders[$function])) {
			continue;
		}

		$keys = array_merge($keys, array_keys($builders[$function]($event)));
	}

	return $keys;
}

// The keys each event of this step is announced with, every announcing call
// together, and where they come from.
function webhook_payloads_test_written()
{
	$events = webhook_payloads_test_catalogue();
	$written = array();
	$add = function ($event, array $keys) use (&$written) {
		$written[$event] = array_values(array_unique(array_merge(isset($written[$event]) ? $written[$event] : array(), $keys)));
	};

	foreach (webhook_payloads_test_calls() as $call) {
		if ($call['event'] === null) {
			continue;
		}

		foreach (array_keys($events) as $event) {
			$hit = $call['prefix'] ? (strpos($event, $call['event'][0]) === 0) : in_array($event, $call['event'], true);

			if ($hit && webhook_payloads_test_in_family($event)) {
				$add($event, array_merge($call['payload']['keys'], webhook_payloads_test_builder_keys($call['payload']['calls'], $call['file'], $event)));
			}
		}
	}

	// A call whose name is chosen elsewhere, with a literal payload: its keys
	// count for every event of the family its file is listed with.
	$dynamic = webhook_payloads_test_dynamic_calls();

	foreach (webhook_payloads_test_calls() as $call) {
		$family = isset($dynamic[$call['file']]) ? $dynamic[$call['file']] : '';

		if (($call['event'] !== null) || (substr($family, -1) !== '.') || empty($call['payload']['keys'])) {
			continue;
		}

		foreach (array_keys($events) as $event) {
			if ((strpos($event, $family) === 0) && webhook_payloads_test_in_family($event)) {
				$add($event, $call['payload']['keys']);
			}
		}
	}

	// The calls that name their event elsewhere, by the payload function
	// they call.
	$families = array(
		'pg_return_event_payload'      => 'order.return.',
		'pg_cart_quote_event_payload'  => 'cart_quote.',
		'pg_credit_event_data'         => 'credit.',
		'pg_subscription_webhook_data' => 'subscription.',
		'mp_order_event_payload'       => 'marketplace.order.',
	);

	foreach (webhook_payloads_test_calls() as $call) {
		if ($call['event'] !== null) {
			continue;
		}

		foreach ($call['payload']['calls'] as $function) {
			if (!isset($families[$function])) {
				continue;
			}

			foreach (array_keys($events) as $event) {
				if (strpos($event, $families[$function]) === 0) {
					$add($event, webhook_payloads_test_builder_keys(array($function), $call['file']));
				}
			}
		}
	}

	// An ERP helper's call: the keys of the erp_event() call in its body, the
	// $payload['…'] keys it adds for some events, and the extra fields the
	// caller lays over them.
	$erp = webhook_payloads_test_erp_helpers();
	$base = array();
	$assigned = array();

	foreach (webhook_payloads_test_calls() as $call) {
		if (($call['function'] === 'erp_event') && isset($erp[$call['within']])) {
			$base[$call['within']] = array_merge(isset($base[$call['within']]) ? $base[$call['within']] : array(), $call['payload']['keys']);
			$assigned[$call['within']] = array_merge(isset($assigned[$call['within']]) ? $assigned[$call['within']] : array(), $call['assigned']);
		}
	}

	foreach (webhook_payloads_test_calls() as $call) {
		$helper = $call['function'];

		if (!isset($erp[$helper]) || ($erp[$helper]['event'] === null)) {
			continue;
		}

		$family = ($call['event'] === null) ? (isset($dynamic[$call['file']]) ? $dynamic[$call['file']] : '') : '';

		foreach (array_keys($events) as $event) {
			if ($call['event'] === null) {
				$hit = (substr($family, -1) === '.') && (strpos($event, $family) === 0);
			} else {
				$hit = $call['prefix'] ? (strpos($event, $call['event'][0]) === 0) : in_array($event, $call['event'], true);
			}

			if (!$hit || !webhook_payloads_test_in_family($event)) {
				continue;
			}

			$keys = array_merge(isset($base[$helper]) ? $base[$helper] : array(), $call['payload']['keys']);

			if (in_array($event, $erp[$helper]['adds_for'], true) && isset($assigned[$helper])) {
				$keys = array_merge($keys, $assigned[$helper]);
			}

			$add($event, $keys);
		}
	}

	// A subscription event is named by pg_subscription_webhook_event().
	foreach (array('renewed', 'renewal_failed', 'status_paused', 'status_cancelled') as $kind) {
		pg_assert_true(isset($written[pg_subscription_webhook_event($kind, 'active', '')]), 'subscription kind ' . $kind . ' is announced');
	}

	pg_assert_same('subscription.resumed', pg_subscription_webhook_event('status_active', 'paused', 'active'), 'a resume is announced');

	// The site's own stock writers announce what pg_product_stock_events()
	// gives: both events, on a site with stock locations.
	foreach (pg_product_stock_events(3, 'Mug', true, 9, 2, 5, 'checkout', array('id' => 2, 'name' => 'Depot', 'quantity' => 1)) as $event) {
		$add($event[0], array_keys($event[1]));
	}

	return $written;
}

function test_webhook_payloads_cover_this_steps_events()
{
	$payloads = api_webhook_event_payloads();
	$events = webhook_payloads_test_catalogue();

	foreach (array_keys($events) as $event) {
		if (webhook_payloads_test_in_family($event)) {
			pg_assert_true(isset($payloads[$event]), $event . ' has its fields and sample');
		}
	}

	foreach (array_keys($payloads) as $event) {
		pg_assert_true(isset($events[$event]), $event . ' is in the catalogue');
	}
}

function test_webhook_payloads_every_dynamic_call_is_known()
{
	$known = webhook_payloads_test_dynamic_calls();
	$erp = webhook_payloads_test_erp_helpers();

	foreach (webhook_payloads_test_calls() as $call) {
		if ($call['event'] !== null) {
			continue;
		}

		$where = $call['file'] . ':' . $call['line'];

		if (($call['function'] === 'pg_announce') || ($call['function'] === 'api_webhook_enqueue')) {
			pg_assert_true(isset($known[$call['file']]), $where . ' names its event elsewhere: say which events it announces');
		} elseif ($call['function'] === 'erp_event') {
			pg_assert_true(isset($erp[$call['within']]) || isset($known[$call['file']]), $where . ' names its event elsewhere: say which events it announces');
		} elseif (isset($erp[$call['function']]) && ($erp[$call['function']]['event'] !== null)) {
			pg_assert_true(isset($known[$call['file']]), $where . ' names its event elsewhere: say which events it announces');
		}
	}
}

function test_webhook_payloads_fields_are_the_keys_the_code_writes()
{
	$payloads = api_webhook_event_payloads();
	$written = webhook_payloads_test_written();

	foreach ($payloads as $event => $payload) {
		if (!webhook_payloads_test_in_family($event)) {
			continue;
		}

		pg_assert_true(isset($written[$event]), $event . ' is announced somewhere the test reads');

		$fields = array_keys($payload['fields']);
		$keys = isset($written[$event]) ? $written[$event] : array();
		sort($fields);
		sort($keys);

		pg_assert_same($keys, $fields, $event . ': the fields are the keys the announcing code writes');
	}
}

function test_webhook_payloads_samples_match_their_fields()
{
	$checks = array(
		'integer' => 'is_int',
		'string'  => 'is_string',
		'boolean' => 'is_bool',
		'object'  => 'is_array',
		'object[]' => 'is_array',
		'string[]' => 'is_array',
		'integer[]' => 'is_array',
		'number'  => function ($value) {
			return is_float($value) || is_int($value);
		},
	);

	foreach (api_webhook_event_payloads() as $event => $payload) {
		pg_assert_same(array_keys($payload['fields']), array_keys($payload['sample']), $event . ': the sample has every field, in order');

		foreach ($payload['fields'] as $name => $field) {
			pg_assert_same(2, count($field), $event . '.' . $name . ' is a type and a meaning');

			$type = (string) $field[0];
			$nullable = (substr($type, -1) === '?');
			$base = rtrim($type, '?');

			pg_assert_true(isset($checks[$base]), $event . '.' . $name . ' has a known type: ' . $type);
			pg_assert_true(trim((string) $field[1]) !== '', $event . '.' . $name . ' says what it means');

			if (!array_key_exists($name, $payload['sample']) || !isset($checks[$base])) {
				continue;
			}

			$value = $payload['sample'][$name];

			pg_assert_true(($nullable && ($value === null)) || call_user_func($checks[$base], $value), $event . '.' . $name . ' sample is a ' . $type);
		}
	}
}

function test_webhook_payloads_event_rows()
{
	$rows = api_webhook_event_rows(
		array('order.created' => 'A new order was placed', 'erp.invoice.created' => 'An invoice was issued'),
		array('erp.invoice.created' => 'erp'),
		array('order.created' => array(
			'fields' => array('id' => array('integer', 'The order'), 'order_number' => array('string?', 'Its number')),
			'sample' => array('id' => 4, 'order_number' => null),
		))
	);

	pg_assert_same(array(
		array(
			'name'        => 'order.created',
			'description' => 'A new order was placed',
			'module'      => '',
			'fields'      => array(
				array('name' => 'id', 'type' => 'integer', 'nullable' => false, 'description' => 'The order'),
				array('name' => 'order_number', 'type' => 'string', 'nullable' => true, 'description' => 'Its number'),
			),
			'sample'      => array('id' => 4, 'order_number' => null),
		),
		array(
			'name'        => 'erp.invoice.created',
			'description' => 'An invoice was issued',
			'module'      => 'erp',
			'fields'      => array(),
			'sample'      => null,
		),
	), $rows, 'each event with its module, its fields and its sample; null for an event without one yet');

	pg_assert_same(array_keys(api_webhook_event_schema()), array_keys($rows[0]), 'the rows answer the declared fields');
}

// GET /webhooks/events is its own route: the router reads {id} of
// /webhooks/{id} as digits only, so "events" never reaches webhooks.get.
function test_webhook_payloads_events_route_is_not_a_webhook_id()
{
	$found = null;

	foreach (api_all_routes() as $route) {
		$accepted = array_merge(array($route['method']), isset($route['also_accepts']) ? $route['also_accepts'] : array());

		if (in_array('GET', $accepted, true) && preg_match(api_route_pattern($route), '/webhooks/events')) {
			$found = $route['id'];
			break;
		}
	}

	pg_assert_same('webhooks.events', $found, 'GET /webhooks/events reaches its own route');

	$routes = array();

	foreach (api_schema() as $route) {
		$routes[$route['id']] = $route;
	}

	pg_assert_same('webhooks:manage', $routes['webhooks.events']['scope'] ?? null, 'under the scope of the other webhook routes');
	pg_assert_same('api_webhooks_events', $routes['webhooks.events']['handler'] ?? null, 'answered by its handler');
	pg_assert_true(function_exists('api_webhooks_events'), 'the handler is there');

	$event = null;

	foreach ($routes['webhooks.test']['params'] as $param) {
		if ($param['name'] === 'event') {
			$event = $param;
		}
	}

	pg_assert_true(is_array($event) && ($event['in'] === 'body') && empty($event['required']), 'POST /webhooks/{id}/test takes an optional event');
}

function test_webhook_payloads_test_event_refusal()
{
	$payloads = array('order.created' => array('fields' => array(), 'sample' => array()));

	pg_assert_same('', api_webhook_sample_refusal('order.created', array('order.created', 'order.shipped'), $payloads), 'an event of the subscription with a sample');
	pg_assert_same('not_listened', api_webhook_sample_refusal('order.shipped', array('order.created'), $payloads), 'an event the subscription does not listen to');
	pg_assert_same('no_sample', api_webhook_sample_refusal('order.shipped', array('order.shipped'), $payloads), 'an event without a sample yet');
	pg_assert_same('not_listened', api_webhook_sample_refusal('', array('order.created'), $payloads), 'no name');
}

function test_webhook_payloads_sample_body_and_header()
{
	$body = api_webhook_sample_body('order.created', array('id' => 4), '2026-11-17T10:00:00Z');

	pg_assert_same(array('event' => 'order.created', 'created_at' => '2026-11-17T10:00:00Z', 'data' => array('id' => 4), 'test' => true), json_decode($body, true), 'the envelope of a real event, marked test');
	pg_assert_true(api_webhook_body_is_test($body), 'a sample is sent with X-Pinegrap-Test');

	pg_assert_false(api_webhook_body_is_test(api_webhook_body('order.created', array('id' => 4, 'test' => true), '2026-11-17T10:00:00Z')), 'a real event whose data says test is not');
	pg_assert_false(api_webhook_body_is_test('{"event":"webhook.test","created_at":"","data":{"webhook_id":1}}'), 'the plain test event is as it was');
	pg_assert_false(api_webhook_body_is_test(''), 'an empty body');

	pg_assert_same(
		array('Content-Type: application/json', 'X-Pinegrap-Event: order.created', 'X-Pinegrap-Delivery: 9', 'X-Pinegrap-Signature: t=100,v1=' . api_webhook_signature('s', '100', $body), 'X-Pinegrap-Test: 1'),
		api_webhook_delivery_headers('order.created', 9, 's', '100', $body),
		'the headers of a sample'
	);
}
