<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The Workspace's record kinds (ws_record_type_keys(): an order, a product,
 * a contact, an invoice...) are named again in many lists: the tags and
 * their lookups, the change catalogue, the record drawer, bulk changes,
 * the scheduled actions' events, the scopes the API search and the
 * assistants read them with, the audit trail, the customer kinds, the
 * Workspace button on the record's own screen. A kind added to some of them
 * and not the others works half way. These tests lay every record kind
 * against every list and name the list a kind is missing from; a kind left
 * out on purpose is listed with the reason in
 * workspace_record_kinds_test_lists(). They also hold the lists to one
 * another where two must agree (a change kind's writer, its right, its audit
 * line; the scopes; the events).
 *
 * Lists built by a function that needs a database are read from source:
 * tests/helpers/source_reader.php, which also reads the change catalogue
 * ws_change_types() (its schema probes keep tests from calling it).
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

require_once(PG_FUNCTIONS_DIR . '/includes/api/scopes.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/api.php');
require_once(__DIR__ . '/helpers/source_reader.php');

// The rights of other modules a reader of tags carries (ws_viewer()).
function workspace_record_kinds_test_rights()
{
	return array('ecommerce', 'contacts', 'users', 'erp', 'erp_cash', 'forms', 'calendars');
}

// A Workspace member holding only these rights.
function workspace_record_kinds_test_viewer(array $rights)
{
	$viewer = array('id' => 1, 'role' => 3, 'member' => true);

	foreach (workspace_record_kinds_test_rights() as $right) {
		$viewer[$right] = in_array($right, $rights, true);
	}

	return $viewer;
}

// The tags of kinds of change ('?kind' for a kind the catalogue does not
// have, so the list check names it).
function workspace_record_kinds_test_tags_of(array $change_kinds)
{
	$catalogue = pg_test_ws_change_types();
	$tags = array();

	foreach ($change_kinds as $kind) {
		$tags[] = isset($catalogue[$kind]) ? (string) $catalogue[$kind]['tag'] : '?' . $kind;
	}

	return array_values(array_unique($tags));
}

// The events of the scheduled actions with the kind of record each is
// about, read from ws_events_catalog() (it asks the approval and guest
// tables whether they are there). One event per line.
function workspace_record_kinds_test_events()
{
	preg_match_all("/'([a-z_.]+)'(?:\]\s*=|\s*=>)\s*array\('label'[^\n]*?'record'\s*=>\s*'([a-z_]+)'/", pg_test_function_source('ws_events_catalog'), $matches, PREG_SET_ORDER);

	$events = array();

	foreach ($matches as $match) {
		$events[$match[1]] = $match[2];
	}

	return $events;
}

// The kinds the task routes' refs field names in its description.
function workspace_record_kinds_test_api_refs()
{
	$kinds = array();

	foreach (ws_api_routes() as $route) {
		foreach ((array) ($route['params'] ?? array()) as $param) {
			if ((($param['name'] ?? '') === 'refs') && preg_match('/\{type, id\}: (.+)\.$/', (string) ($param['description'] ?? ''), $match)) {
				$kinds = array_merge($kinds, preg_split('/, | or /', $match[1]));
			}
		}
	}

	return array_values(array_unique($kinds));
}

// The kinds the screens at the product root draw a Workspace button for.
function workspace_record_kinds_test_screen_kinds()
{
	$kinds = array();

	foreach (glob(PG_FUNCTIONS_DIR . '/*.php') as $file) {
		$source = (string) file_get_contents($file);

		if ((strpos($source, 'ws_record_button(') !== false)
			&& preg_match_all("/ws_record_button\(\s*\\\$[A-Za-z_]+\s*,\s*'([a-z_]+)'/", $source, $matches)) {
			$kinds = array_merge($kinds, $matches[1]);
		}
	}

	return array_values(array_unique($kinds));
}

/**
 * Every list a record kind is named in: where it is, the kinds it names
 * (as tags), the kinds left out on purpose with the reason, and the kinds
 * other than records it may name beside them ('others'; by default the
 * people and the Workspace's own things a tag can point at).
 *
 * A new record kind goes into each list, or into that list's 'except'
 * with the reason it stays out.
 *
 * @return array name => where, kinds, except, others
 */
function workspace_record_kinds_test_lists()
{
	static $lists = null;

	if ($lists !== null) {
		return $lists;
	}

	$all = workspace_record_kinds_test_viewer(workspace_record_kinds_test_rights());

	// The ERP's documents are tagged and read, never written from the
	// Workspace (docs/memory/workspace/workspace-record-changes.md).
	$tag_only = array_fill_keys(array('invoice', 'waybill', 'receipt', 'edoc', 'erp_quote'), 'an ERP document is tag-only: no kind of ws_change_types() writes it');

	$records = array();

	foreach (ws_ref_types($all) as $kind => $info) {
		if (!empty($info['record'])) {
			$records[] = $kind;
		}
	}

	preg_match_all("/if \(!empty\(\\\$ids\['([a-z_]+)'\]\)\)/", pg_test_function_source('ws_refs_resolve'), $resolved);
	preg_match_all("/^\s*case '([a-z_]+)':/m", pg_test_function_source('ws_ref_search'), $searched);

	// A Workspace note has no tag.
	$change_tags = array_values(array_diff(workspace_record_kinds_test_tags_of(array_keys(pg_test_ws_change_types())), array('')));

	$ai_scoped = array();

	foreach (array_keys(pg_test_source_array('ws_ai_type_scope', '$scopes')) as $kind) {
		if (ws_ai_type_scope($kind) !== 'workspace:read') {
			$ai_scoped[] = $kind;
		}
	}

	$audited = array();

	foreach (array_keys(pg_test_ws_change_types()) as $kind) {
		if (ws_change_audit_target($kind, 'update', array('ok' => true)) !== array()) {
			$audited[] = $kind;
		}
	}

	$lists = array(
		'tags' => array(
			'where'  => 'ws_ref_types() in includes/workspace/refs.php (with record true)',
			'kinds'  => $records,
			'except' => array(),
			'others' => array(),
		),
		'resolve' => array(
			'where'  => 'a branch of ws_refs_resolve() in includes/workspace/refs.php',
			'kinds'  => array_values(array_unique($resolved[1])),
			'except' => array(),
		),
		'search' => array(
			'where'  => 'a case of ws_ref_search() in includes/workspace/refs.php',
			'kinds'  => array_values(array_unique($searched[1])),
			'except' => array(),
		),
		'api_refs' => array(
			'where'  => 'the refs field of the task routes (ws_api_routes(), includes/workspace/api.php)',
			'kinds'  => workspace_record_kinds_test_api_refs(),
			'except' => array(),
			'others' => array(),
		),
		'changes' => array(
			'where'  => 'a tag of ws_change_types() in includes/workspace/changes.php',
			'kinds'  => $change_tags,
			'except' => $tag_only,
		),
		'quick_edit' => array(
			'where'  => 'ws_quick_edit_types() in includes/workspace/quick_edit.php',
			'kinds'  => workspace_record_kinds_test_tags_of(ws_quick_edit_types()),
			'except' => $tag_only + array(
				'user_account' => 'a user\'s role is given on the user screen',
				'form'         => 'its answers field (kind answers) has no input in the drawer (saValueInput() in workspace.js)',
				'file'         => 'a file\'s words are edited in the file manager',
				'page'         => 'a page is edited in the file manager',
			),
			'others' => array(),
		),
		'bulk' => array(
			'where'  => 'ws_bulk_types() in includes/workspace/bulk.php',
			'kinds'  => workspace_record_kinds_test_tags_of(ws_bulk_types()),
			'except' => $tag_only + array(
				'order'        => 'left out of bulk changes; a status move may cancel and refund the order (ws_change_write_order())',
				'user_account' => 'left out of bulk changes; a role is an administrator\'s to give (ws_change_allowed())',
				'form'         => 'left out of bulk changes; each submitted form holds answers of its own',
			),
			'others' => array(),
		),
		'events' => array(
			'where'  => 'an event of ws_events_catalog() in includes/workspace/watch.php',
			'kinds'  => array_values(array_unique(workspace_record_kinds_test_events())),
			'except' => array_fill_keys(array('product_group', 'offer', 'user_account', 'erp_account', 'invoice', 'waybill', 'receipt', 'edoc', 'erp_quote', 'calendar_event', 'file', 'page'),
				'no event a scheduled action starts on is about it'),
			'others' => array('task', 'channel', 'approval'),
		),
		'search_scopes' => array(
			'where'  => 'ws_api_search_record_scopes() in includes/workspace/api_day.php',
			'kinds'  => array_keys(ws_api_search_record_scopes()),
			'except' => array(
				'user_account' => 'no scope of the external API reads user accounts',
			),
			'others' => array(),
		),
		'ai_scopes' => array(
			'where'  => 'ws_ai_type_scope() in includes/workspace/ai.php',
			'kinds'  => $ai_scoped,
			'except' => array(
				'user_account' => 'no scope of the external API reads user accounts, so the Workspace permission is asked',
			),
		),
		'audit' => array(
			'where'  => 'pg_audit_tables() in includes/fn/audit.php, through ws_change_audit_target() in includes/workspace/changes.php',
			'kinds'  => workspace_record_kinds_test_tags_of($audited),
			'except' => $tag_only + array_fill_keys(array('product_group', 'contact', 'erp_account', 'form', 'calendar_event'),
				'its table is not in pg_audit_tables(), as ws_change_audit_target() says'),
		),
		'customers' => array(
			'where'  => 'ws_customer_types() in includes/workspace/customer.php',
			'kinds'  => ws_customer_types(),
			'except' => array_fill_keys(array('order', 'product', 'product_group', 'offer', 'invoice', 'waybill', 'receipt', 'edoc', 'erp_quote', 'form', 'calendar_event', 'file', 'page'),
				'a record about a customer, not one: a customer is a contact, a user or a current account'),
			'others' => array(),
		),
		'screens' => array(
			'where'  => 'a ws_record_button() call on the record\'s own screen (a PHP file at the product root)',
			'kinds'  => workspace_record_kinds_test_screen_kinds(),
			'except' => array(
				'file' => 'the file manager draws no Workspace button',
				'page' => 'the page screens draw no Workspace button',
			),
			'others' => array(),
		),
	);

	return $lists;
}

// Each record kind is in every list, or that list names the reason it is
// not; a reason for a kind that is in the list now is out of date.
function test_workspace_record_kinds_are_in_every_list()
{
	$records = ws_record_type_keys();

	foreach (workspace_record_kinds_test_lists() as $name => $list) {
		foreach ($records as $kind) {
			if (isset($list['except'][$kind])) {
				pg_assert_false(in_array($kind, $list['kinds'], true),
					$kind . ' is in ' . $list['where'] . ' now: take it off the \'' . $name . '\' exceptions in workspace_record_kinds_test_lists()');
				continue;
			}

			pg_assert_true(in_array($kind, $list['kinds'], true),
				$kind . ' is a record kind (ws_record_type_keys()) but is missing from ' . $list['where']
				. ': add it there, or to the \'' . $name . '\' exceptions in workspace_record_kinds_test_lists() with the reason');
		}

		foreach (array_keys($list['except']) as $kind) {
			pg_assert_contains($kind, $records, $name . ': an exception names a record kind');
		}
	}
}

// A list names record kinds and, beside them, only the other kinds it is
// meant to: a kind spelled wrong, or one taken out of ws_record_type_keys(),
// is named here.
function test_workspace_record_kinds_lists_name_only_known_kinds()
{
	$records = ws_record_type_keys();
	$others = array_values(array_diff(ws_tag_type_keys(), $records));

	foreach (workspace_record_kinds_test_lists() as $name => $list) {
		pg_assert_true(count($list['kinds']) > 0, $name . ': ' . $list['where'] . ' was read');

		foreach ($list['kinds'] as $kind) {
			pg_assert_true(in_array($kind, $records, true) || in_array($kind, $list['others'] ?? $others, true),
				$name . ': ' . $list['where'] . ' names "' . $kind . '", which is no record kind (ws_record_type_keys())');
		}
	}
}

// Every kind of change has its writer: ws_change_write() calls
// ws_change_write_<kind>() by name.
function test_workspace_record_kinds_change_kinds_have_a_writer()
{
	foreach (array_keys(pg_test_ws_change_types()) as $kind) {
		pg_assert_true(function_exists('ws_change_write_' . $kind), 'ws_change_write_' . $kind . '() exists');
	}
}

// A kind of change whose table the audit trail follows gets its audit line
// on that table; one whose table it does not follow gets none.
function test_workspace_record_kinds_audit_line_follows_the_audit_tables()
{
	$audited = pg_audit_tables();

	foreach (pg_test_ws_change_types() as $kind => $type) {
		$target = ws_change_audit_target($kind, 'update', array('ok' => true));

		if (isset($audited[$type['table']])) {
			pg_assert_same($type['table'], $target['table'] ?? '', $kind . ': its table ' . $type['table'] . ' is in pg_audit_tables(), so ws_change_audit_target() records it there');
		} else {
			pg_assert_same(array(), $target, $kind . ': its table ' . $type['table'] . ' is not in pg_audit_tables()');
		}
	}
}

// The right that lets a person change a kind of record is the right that
// shows them its tag: the person who may write it may also find it.
function test_workspace_record_kinds_change_right_is_the_tag_right()
{
	$records = ws_record_type_keys();
	$rights = workspace_record_kinds_test_rights();

	foreach (pg_test_ws_change_types() as $kind => $type) {
		$tag = (string) $type['tag'];
		$right = (string) $type['right'];

		if (!in_array($tag, $records, true)) {
			continue;
		}

		pg_assert_true(($right === '') || in_array($right, $rights, true), $kind . ': its right ' . $right . ' is one ws_viewer() carries');

		$alone = workspace_record_kinds_test_viewer(($right === '') ? array() : array($right));
		pg_assert_true(isset(ws_ref_types($alone)[$tag]), $kind . ': its right (' . (($right === '') ? 'none' : $right) . ') shows the ' . $tag . ' tag');

		if ($right !== '') {
			$without = workspace_record_kinds_test_viewer(array_values(array_diff($rights, array($right))));
			pg_assert_false(isset(ws_ref_types($without)[$tag]), $kind . ': without ' . $right . ' the ' . $tag . ' tag is not shown');
		}
	}
}

// The assistants read a kind of record with the scope the API search reads
// it with: every kind the search finds, none of them under the Workspace
// permission alone.
function test_workspace_record_kinds_assistants_and_search_ask_the_same_scope()
{
	foreach (ws_api_search_record_scopes() as $kind => $scope) {
		pg_assert_same($scope, ws_ai_type_scope($kind), $kind . ': ws_ai_type_scope() asks what ws_api_search_record_scopes() asks');
	}
}

// The record route of Claude's queue is declared with workspace:read alone,
// so its handler asks the scope of the kind it reads (ws_ai_type_scope() of
// the kind's tag) before it looks the record up: the key reads no kind of
// record there that it could not read through the API search.
function test_workspace_record_kinds_claude_record_route_asks_the_kind_scope()
{
	$route = null;

	foreach (ws_claude_api_routes() as $row) {
		if ($row['id'] === 'workspace.claude.record') {
			$route = $row;
		}
	}

	pg_assert_same('workspace:read', $route['scope'] ?? null, 'workspace.claude.record is declared with workspace:read');

	$source = pg_test_function_source('ws_api_claude_record');
	$asks = strpos($source, "api_require_scope(ws_ai_type_scope((string) \$types[\$type]['tag']));");
	$looks = strpos($source, 'ws_change_allowed(');

	pg_assert_true($asks !== false, 'ws_api_claude_record() asks the scope of the kind\'s tag with api_require_scope(ws_ai_type_scope())');
	pg_assert_true(($asks !== false) && ($looks !== false) && ($asks < $looks), 'ws_api_claude_record() asks the scope before ws_change_allowed() looks the record up');
}

// A kind the drawer edits is a kind of change of that name that takes
// update, and its short list names fields the kind has (the product's stock
// is the stock kind's quantity).
function test_workspace_record_kinds_quick_edit_kinds_are_changes()
{
	$catalogue = pg_test_ws_change_types();
	$short = pg_test_source_array('ws_quick_edit_short_list', '$lists');

	pg_assert_same(ws_quick_edit_types(), array_keys($short), 'ws_quick_edit_short_list() has a list for each kind of ws_quick_edit_types(), in its order');

	foreach (ws_quick_edit_types() as $kind) {
		if (!pg_assert_true(isset($catalogue[$kind]), $kind . ' is a kind of ws_change_types()')) {
			continue;
		}

		pg_assert_same($kind, (string) $catalogue[$kind]['tag'], $kind . ': tagged as itself');
		pg_assert_contains('update', $catalogue[$kind]['actions'], $kind . ': takes update');

		foreach (ws_quick_edit_short_list($kind) as $field) {
			$stock = ($kind === 'product') && ($field === WS_QUICK_EDIT_STOCK_FIELD) && isset($catalogue['stock']['fields'][$field]);
			pg_assert_true($stock || isset($catalogue[$kind]['fields'][$field]), $kind . ': its short list names ' . $field . ', a field of the kind');
		}
	}
}

// What a scheduled action's event is about, as the catalogue says it, is
// the record ws_events_record_of() finds in the event's payload.
function test_workspace_record_kinds_events_name_the_record_they_find()
{
	$events = workspace_record_kinds_test_events();

	pg_assert_true(isset($events['order.created'], $events['form.submitted'], $events['workspace.task.completed']), 'the catalogue was read');

	$payload = array('id' => 7, 'product_id' => 7, 'channel_id' => 7);

	foreach ($events as $event => $record) {
		$found = ws_events_record_of($event, $payload);

		// An approval request is about the task it was asked for, or else
		// its channel.
		if ($record === 'approval') {
			pg_assert_contains($found[0], array('task', 'channel'), $event . ': the task or the channel of the request');
			continue;
		}

		pg_assert_same(array($record, 7), $found, $event . ': ws_events_record_of() finds the ' . $record . ' ws_events_catalog() names');
	}
}

// A word typed after # picks one kind: no two kinds share a prefix. The
// ERP's sales quote and the site's offer (a discount) are told apart this
// way, and the quote is shown only with the ERP right.
function test_workspace_record_kinds_prefixes_pick_one_kind()
{
	$owners = array();

	foreach (ws_ref_types(workspace_record_kinds_test_viewer(workspace_record_kinds_test_rights())) as $kind => $info) {
		foreach ($info['prefixes'] as $prefix) {
			pg_assert_false(isset($owners[$prefix]), '"' . $prefix . '" picks ' . $kind . (isset($owners[$prefix]) ? ' and ' . $owners[$prefix] : ''));
			$owners[$prefix] = $kind;
		}
	}

	pg_assert_same('erp_quote', $owners['satis'] ?? null, '#satis picks the sales quote');
	pg_assert_same('erp_quote', $owners['salesquote'] ?? null, '#salesquote picks the sales quote');
	pg_assert_same('offer', $owners['teklif'] ?? null, '#teklif still picks the offer');

	pg_assert_true(isset(ws_ref_types(workspace_record_kinds_test_viewer(array('erp')))['erp_quote']), 'the ERP right shows the sales quote tag');
	pg_assert_false(isset(ws_ref_types(workspace_record_kinds_test_viewer(array('ecommerce', 'contacts', 'users', 'forms', 'calendars')))['erp_quote']), 'without the ERP right the sales quote tag is not shown');
}

// A sales quote's grand_total is kept in the quote's own currency
// (erp_quote_save()), so its chip prints it in that currency, as the quote
// screens do, not through ws_money_out() (the visitor's currency at its
// exchange rate). The branch runs queries, so it is read from source.
function test_workspace_record_kinds_erp_quote_total_in_its_currency()
{
	$source = pg_test_function_source('ws_refs_resolve');
	$start = strpos($source, "if (!empty(\$ids['erp_quote']))");
	pg_assert_true($start !== false, 'ws_refs_resolve() has a sales quote branch');

	$end = strpos($source, 'if (!empty($ids[', (int) $start + 1);
	$branch = substr($source, (int) $start, ($end === false) ? strlen($source) : $end - (int) $start);
	// The code alone: a comment may name the helper the branch does not call.
	$branch = implode('', array_map('pg_test_source_text', pg_test_source_tokens($branch)));

	pg_assert_contains('q.currency', $branch, 'the quote\'s currency is read');
	pg_assert_contains("erp_money_out_currency((int) \$row['grand_total'], (string) \$row['currency'])", $branch, 'the total is printed in the quote\'s currency');
	pg_assert_true(strpos($branch, 'ws_money_out(') === false, 'the total does not go through the visitor\'s currency');

	// Outside the ERP's own screens refs.php loads the quote helpers alone,
	// so the money helpers are loaded with them.
	pg_assert_contains("'/includes/erp/money.php'", pg_test_function_source('ws_erp_quotes_readable'), 'the money helpers are loaded with the quote helpers');
}

// The channel screen offers the same customer kinds as the server.
function test_workspace_record_kinds_customer_kinds_match_the_channel_screen()
{
	$script = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace.js');

	pg_assert_true((bool) preg_match('/CFG\.customer \? \[([^\]]*)\]/', $script, $list), 'workspace.js lists the customer kinds');
	preg_match_all("/'([a-z_]+)'/", $list[1] ?? '', $listed);
	pg_assert_same(ws_customer_types(), $listed[1], 'the customer kinds of the channel screen\'s customer field');

	pg_assert_true((bool) preg_match('/\(type === \'contact\'\) \|\| \(CFG\.customer && \(([^\n]*)\)\)\) \{/', $script, $button), 'workspace.js offers a customer channel from a record');
	preg_match_all("/type === '([a-z_]+)'/", $button[1] ?? '', $offered);
	pg_assert_same(ws_customer_types(), array_merge(array('contact'), $offered[1]), 'the kinds the record drawer opens a customer channel for');
}

// The source reader: literals, nested arrays, keys, and code kept as code.
function test_workspace_record_kinds_source_reader_reads_literals()
{
	$read = pg_test_source_array_in(
		'$x = 1; $list = array(1, -2, 2.5, "a\\tb", \'it\\\'s\', true, NULL, \'c\' => FOO, 3 * 2, \'k\' => [7, array(),], lang(\'Name\'), -$y,);',
		'$list');

	pg_assert_same(array(1, -2, 2.5, "a\tb", 'it\'s', true, null), array_slice($read, 0, 7), 'literals');
	pg_assert_same('FOO', $read['c']->code ?? null, 'a constant, under its key');
	pg_assert_same('3 * 2', $read[7]->code ?? null, 'a literal followed by an operator is code');
	pg_assert_same(array(7, array()), $read['k'], 'a nested array');
	pg_assert_same("lang('Name')", $read[8]->code ?? null, 'a call');
	pg_assert_same('-$y', $read[9]->code ?? null, 'a negated variable');

	$refused = '';

	try {
		pg_test_source_array_in('$list = array(FOO => 1);', '$list');
	} catch (RuntimeException $exception) {
		$refused = $exception->getMessage();
	}

	pg_assert_contains('not a literal', $refused, 'a key worked out by code is refused');
}

// The change catalogue read from source: every kind the code knows, with
// fields of four parts.
function test_workspace_record_kinds_source_reader_reads_the_change_catalogue()
{
	$types = pg_test_ws_change_types();

	pg_assert_same(array('product', 'stock', 'order', 'contact', 'erp_account', 'product_group', 'channel', 'user', 'page', 'file', 'offer', 'form', 'calendar_event', 'task', 'task_note', 'note'), array_keys($types), 'the kinds, in their order');

	pg_assert_same('money', $types['product']['fields']['price'][0], 'the kind of value');
	pg_assert_same(2147483647, $types['product']['fields']['price'][1], 'a literal limit');
	pg_assert_same('price', $types['product']['fields']['price'][3], 'the column');
	pg_assert_true($types['file']['fields']['content'][1] instanceof PgTestSourceExpression, 'a limit worked out by code');
	pg_assert_same(array(0, 100, 3, true), $types['product']['fields']['tax_rate'][1], 'a decimal\'s bounds');
	pg_assert_same('user_id', $types['user']['key'], 'a key column');
	pg_assert_same('product', $types['stock']['tag'], 'the stock is tagged as its product');
	pg_assert_same(array('update', 'create', 'delete', 'add', 'remove'), $types['product_group']['actions'], 'the actions');

	foreach ($types as $kind => $type) {
		foreach (array('fields', 'create', 'members') as $part) {
			foreach ((array) ($type[$part] ?? array()) as $name => $field) {
				pg_assert_true(is_array($field) && (count($field) === 4) && is_string($field[0]) && is_string($field[3]),
					$kind . '.' . $name . ': [kind, limit, label, column]');
			}
		}
	}
}
