<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The record-kind registry, ws_record_kinds() in
 * includes/workspace/refs.php, describes every kind of site record a
 * Workspace tag can point at in one place. The lists that name kinds one by
 * one stay as they are, so these tests hold the registry and each of them
 * together: a kind a list names that the registry lacks, or a kind the
 * registry gives a list that the list lacks, fails with the list's name. A
 * difference kept on purpose is listed with its reason in
 * workspace_record_kinds_registry_test_lists(); a reason that no longer
 * matches a difference fails as out of date.
 *
 * Lists built by a function that needs a database are read from source
 * (tests/helpers/source_reader.php): the change catalogue ws_change_types()
 * (its schema probes keep tests from calling it), the table map of
 * ws_change_audit_target() and the scope map of ws_ai_type_scope().
 *
 * tests/workspace_record_kinds_test.php lays the record kinds against the
 * lists that have no column here (search, bulk, events, screens...).
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

// The ws_viewer() rights a record kind's tag can ask.
function workspace_record_kinds_registry_test_rights()
{
	return array('ecommerce', 'contacts', 'users', 'erp', 'erp_cash', 'forms', 'calendars');
}

// A Workspace member holding only these rights.
function workspace_record_kinds_registry_test_viewer(array $rights)
{
	$viewer = array('id' => 1, 'role' => 3, 'member' => true);

	foreach (workspace_record_kinds_registry_test_rights() as $right) {
		$viewer[$right] = in_array($right, $rights, true);
	}

	return $viewer;
}

// The registry's kinds whose column $part is not empty, or the column's
// values: $values true gives the values (the change kinds, the audit
// tables), false the kinds.
function workspace_record_kinds_registry_test_column($part, $values)
{
	$out = array();

	foreach (ws_record_kinds() as $kind => $entry) {
		if (!empty($entry[$part])) {
			$out[] = $values ? $entry[$part] : $kind;
		}
	}

	return array_values(array_unique($out));
}

/**
 * Each list the registry is held against: where it is, what it names, the
 * part of the registry it is laid against ('part', as a failure names it),
 * what the registry says the list should name, and the differences kept on
 * purpose (name => reason), for a name the list has and the registry lacks
 * or the other way round.
 *
 * A new record kind goes into ws_record_kinds() and into each list, or into
 * that list's 'except' with the reason.
 *
 * @return array name => where, names, part, registry, except
 */
function workspace_record_kinds_registry_test_lists()
{
	$all = workspace_record_kinds_registry_test_viewer(workspace_record_kinds_registry_test_rights());
	$tags = array();

	foreach (ws_ref_types($all) as $kind => $info) {
		if (!empty($info['record'])) {
			$tags[] = $kind;
		}
	}

	// The changes of the kinds whose Workspace writes are recorded.
	$audited_changes = array();

	foreach (ws_record_kinds() as $entry) {
		if ($entry['audit'] !== '') {
			$audited_changes[] = $entry['change'];
		}
	}

	return array(
		'record_types' => array(
			'where'    => 'ws_record_type_keys() in includes/workspace/refs.php',
			'names'    => ws_record_type_keys(),
			'part'     => 'a kind',
			'registry' => array_keys(ws_record_kinds()),
			'except'   => array(),
		),
		'tags' => array(
			'where'    => 'ws_ref_types() in includes/workspace/refs.php (with record true)',
			'names'    => $tags,
			'part'     => 'a kind',
			'registry' => array_keys(ws_record_kinds()),
			'except'   => array(),
		),
		'changes' => array(
			'where'    => 'the kinds of ws_change_types() in includes/workspace/changes.php',
			'names'    => array_keys(pg_test_ws_change_types()),
			'part'     => 'the change of a kind',
			'registry' => workspace_record_kinds_registry_test_column('change', true),
			'except'   => array(
				'stock'     => 'a product\'s stock: a second kind of change on the product\'s row (tag product); the drawer shows it as a field of the product',
				'channel'   => 'a channel\'s summary: the Workspace\'s own thing, not a site record',
				'task'      => 'a Workspace task, not a site record',
				'task_note' => 'a note on a Workspace task, not a site record',
				'note'      => 'a Workspace note, not a site record (it has no tag)',
			),
		),
		'audit_target' => array(
			'where'    => 'the table map of ws_change_audit_target() in includes/workspace/changes.php',
			'names'    => array_keys(pg_test_source_array('ws_change_audit_target', '$tables')),
			'part'     => 'the change of a kind with an audit table',
			'registry' => $audited_changes,
			'except'   => array(
				'stock' => 'a product\'s stock is written on its product\'s row and recorded on products, as the product is',
			),
		),
		'audit_tables' => array(
			'where'    => 'pg_audit_tables() in includes/fn/audit.php',
			'names'    => array_keys(pg_audit_tables()),
			'part'     => 'the audit table of a kind',
			'registry' => workspace_record_kinds_registry_test_column('audit', true),
			'except'   => array(
				'config'   => 'the site\'s settings row, no record kind',
				'api_apps' => 'an application of the external API, no record kind',
			),
		),
		'quick_edit' => array(
			'where'    => 'ws_quick_edit_types() in includes/workspace/quick_edit.php',
			'names'    => ws_quick_edit_types(),
			'part'     => 'a kind with quick_edit true',
			'registry' => workspace_record_kinds_registry_test_column('quick_edit', false),
			'except'   => array(),
		),
		'customers' => array(
			'where'    => 'ws_customer_types() in includes/workspace/customer.php',
			'names'    => ws_customer_types(),
			'part'     => 'a kind with customer true',
			'registry' => workspace_record_kinds_registry_test_column('customer', false),
			'except'   => array(),
		),
		'scopes' => array(
			'where'    => 'the scope map of ws_ai_type_scope() in includes/workspace/ai.php',
			'names'    => array_keys(pg_test_source_array('ws_ai_type_scope', '$scopes')),
			'part'     => 'a kind (each has its scope)',
			'registry' => array_keys(ws_record_kinds()),
			'except'   => array(
				'user_account' => 'no scope of the external API reads user accounts, so the map leaves it to its default, workspace:read',
				'task'         => 'a Workspace task, not a site record: tasks:read',
			),
		),
	);
}

// The registry's entries have the same parts, of the same types.
function test_workspace_record_kinds_registry_entries_are_whole()
{
	$rights = workspace_record_kinds_registry_test_rights();

	foreach (ws_record_kinds() as $kind => $entry) {
		pg_assert_same(array('prefixes', 'right', 'scope', 'change', 'audit', 'quick_edit', 'customer'), array_keys($entry), $kind . ': its parts, in their order');

		pg_assert_true(is_array($entry['prefixes']) && (count($entry['prefixes']) > 0), $kind . ': at least one prefix');
		pg_assert_same(array_values(array_unique($entry['prefixes'])), $entry['prefixes'], $kind . ': no prefix twice');
		pg_assert_true(($entry['right'] === '') || in_array($entry['right'], $rights, true), $kind . ': its right is one ws_viewer() carries, or none');
		pg_assert_true(is_string($entry['scope']) && (preg_match('/^[a-z_]+:read$/', $entry['scope']) === 1), $kind . ': a read scope');
		pg_assert_true(is_string($entry['change']) && is_string($entry['audit']), $kind . ': change and audit are names, \'\' for none');
		pg_assert_true(is_bool($entry['quick_edit']) && is_bool($entry['customer']), $kind . ': quick_edit and customer are booleans');
	}
}

// Each list names what the registry says, or the difference is listed with
// its reason; a reason left for a difference that is gone is out of date.
function test_workspace_record_kinds_registry_matches_every_list()
{
	foreach (workspace_record_kinds_registry_test_lists() as $name => $list) {
		pg_assert_true(count($list['names']) > 0, $name . ': ' . $list['where'] . ' was read');

		$only_list = array_values(array_diff($list['names'], $list['registry']));
		$only_registry = array_values(array_diff($list['registry'], $list['names']));

		foreach ($only_list as $item) {
			pg_assert_true(isset($list['except'][$item]),
				$name . ': ' . $list['where'] . ' names "' . $item . '", which is not ' . $list['part'] . ' in ws_record_kinds(): describe it there, or add it to the \''
				. $name . '\' exceptions in workspace_record_kinds_registry_test_lists() with the reason');
		}

		foreach ($only_registry as $item) {
			pg_assert_true(isset($list['except'][$item]),
				$name . ': ws_record_kinds() names "' . $item . '" as ' . $list['part'] . ', which ' . $list['where'] . ' lacks: add it there, or to the \''
				. $name . '\' exceptions in workspace_record_kinds_registry_test_lists() with the reason');
		}

		foreach (array_keys($list['except']) as $item) {
			pg_assert_true(in_array($item, $only_list, true) || in_array($item, $only_registry, true),
				$name . ': the exception for "' . $item . '" is out of date (the list and ws_record_kinds() agree on it now, or neither names it): take it off');
		}
	}
}

// The registry keeps the order of ws_record_type_keys(), so the list can be
// read from it later without moving a kind.
function test_workspace_record_kinds_registry_keeps_the_record_type_order()
{
	pg_assert_same(ws_record_type_keys(), array_keys(ws_record_kinds()), 'the kinds of ws_record_kinds(), in the order of ws_record_type_keys()');
}

// A kind's prefixes are the ones ws_ref_types() offers after #, and its
// right is the one that shows its tag there.
function test_workspace_record_kinds_registry_prefixes_and_right_are_the_tags()
{
	$rights = workspace_record_kinds_registry_test_rights();
	$types = ws_ref_types(workspace_record_kinds_registry_test_viewer($rights));

	foreach (ws_record_kinds() as $kind => $entry) {
		if (!pg_assert_true(isset($types[$kind]), $kind . ': ws_ref_types() has its tag')) {
			continue;
		}

		pg_assert_same($types[$kind]['prefixes'], $entry['prefixes'], $kind . ': the prefixes ws_ref_types() offers');

		$alone = ws_ref_types(workspace_record_kinds_registry_test_viewer(($entry['right'] === '') ? array() : array($entry['right'])));
		pg_assert_true(isset($alone[$kind]), $kind . ': its right (' . (($entry['right'] === '') ? 'none' : $entry['right']) . ') shows its tag');

		if ($entry['right'] !== '') {
			$without = ws_ref_types(workspace_record_kinds_registry_test_viewer(array_values(array_diff($rights, array($entry['right'])))));
			pg_assert_false(isset($without[$kind]), $kind . ': without ' . $entry['right'] . ' its tag is not shown');
		}
	}
}

// A kind's change is a kind of the change catalogue tagged as the record,
// asking the record's right.
function test_workspace_record_kinds_registry_change_is_tagged_as_the_kind()
{
	$catalogue = pg_test_ws_change_types();

	foreach (ws_record_kinds() as $kind => $entry) {
		if ($entry['change'] === '') {
			continue;
		}

		if (!pg_assert_true(isset($catalogue[$entry['change']]), $kind . ': its change ' . $entry['change'] . ' is a kind of ws_change_types()')) {
			continue;
		}

		pg_assert_same($kind, (string) $catalogue[$entry['change']]['tag'], $kind . ': its change ' . $entry['change'] . ' is tagged as ' . $kind);
		pg_assert_same($entry['right'], (string) $catalogue[$entry['change']]['right'], $kind . ': its change asks the right of its tag');
	}

	// Every kind of change tagged as a record kind is that kind's change,
	// or a second change on its row (the listed exceptions).
	$except = workspace_record_kinds_registry_test_lists()['changes']['except'];

	foreach ($catalogue as $change => $type) {
		$tag = (string) $type['tag'];

		if (isset(ws_record_kinds()[$tag]) && !isset($except[$change])) {
			pg_assert_same($change, ws_record_kinds()[$tag]['change'], $change . ': tagged as ' . $tag . ', so it is the change ws_record_kinds() gives ' . $tag);
		}
	}
}

// A kind's audit table is the one ws_change_audit_target() records its
// change on, and a table the audit trail follows.
function test_workspace_record_kinds_registry_audit_is_the_change_target()
{
	$tables = pg_audit_tables();
	$registry_tables = workspace_record_kinds_registry_test_column('audit', true);

	foreach (ws_record_kinds() as $kind => $entry) {
		if ($entry['audit'] !== '') {
			pg_assert_true(isset($tables[$entry['audit']]), $kind . ': its audit table ' . $entry['audit'] . ' is in pg_audit_tables()');
			pg_assert_true($entry['change'] !== '', $kind . ': an audit table needs a change that writes it');
		}

		if ($entry['change'] === '') {
			continue;
		}

		$target = ws_change_audit_target($entry['change'], 'update', array('ok' => true));
		pg_assert_same($entry['audit'], (string) ($target['table'] ?? ''), $kind . ': ws_change_audit_target() records its change ' . $entry['change'] . ' on ' . (($entry['audit'] === '') ? 'no table' : $entry['audit']));
	}

	foreach (pg_test_source_array('ws_change_audit_target', '$tables') as $change => $table) {
		pg_assert_contains($table, $registry_tables, $change . ': ws_change_audit_target() records it on ' . $table . ', the audit table of a kind of ws_record_kinds()');
	}
}

// The drawer edits a kind through its own change; the scope is what the
// assistants ask.
function test_workspace_record_kinds_registry_quick_edit_and_scope()
{
	foreach (ws_record_kinds() as $kind => $entry) {
		if ($entry['quick_edit']) {
			pg_assert_same($kind, $entry['change'], $kind . ': the drawer edits it through the change of its own name');
		}

		pg_assert_same(ws_ai_type_scope($kind), $entry['scope'], $kind . ': the scope ws_ai_type_scope() asks');
	}
}
