<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the risk tiers of agent work (includes/workspace/agent_tiers.php):
 * the tier of a change from the catalogue, of an answer, and what a
 * channel-agent row's tier allows.
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

if (!defined('PG_INIT_LOADED')) {
	define('PG_INIT_LOADED', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/api.php');

// A catalogue of the shape ws_change_types() builds (it reads the database
// to build itself): the kinds and fields the tiers tell apart.
function workspace_agent_tiers_test_types()
{
	return array(
		'product'     => array('actions' => array('update', 'create', 'delete'), 'fields' => array('name' => array('text'), 'enabled' => array('bool'), 'price' => array('money'))),
		'order'       => array('actions' => array('update'), 'fields' => array('status' => array('enum'), 'notes' => array('long'), 'po_number' => array('text'), 'cancellation_reason' => array('long'))),
		'task'        => array('actions' => array('update'), 'fields' => array('title' => array('text'))),
		'note'        => array('actions' => array('create'), 'fields' => array('title' => array('text'))),
		'erp_account' => array('actions' => array('update', 'create'), 'fields' => array('name' => array('text'), 'credit_limit' => array('money'))),
		'erp_quote'   => array('actions' => array('create', 'invoice'), 'fields' => array()),
		'user'        => array('actions' => array('update'), 'fields' => array('email' => array('text'))),
	);
}

function test_workspace_change_tier()
{
	$t = workspace_agent_tiers_test_types();

	// Reversible: text and flags of a product, a task, a note, an order's notes.
	pg_assert_same(2, ws_change_tier('product', 'update', array('name', 'enabled'), $t));
	pg_assert_same(2, ws_change_tier('product', 'create', array('name'), $t));
	pg_assert_same(2, ws_change_tier('task', 'update', array(), $t));
	pg_assert_same(2, ws_change_tier('note', 'create', array(), $t));
	pg_assert_same(2, ws_change_tier('order', 'update', array('notes', 'po_number'), $t));

	// Irreversible: a deletion, money, an order's status, accounting, accounts.
	pg_assert_same(3, ws_change_tier('product', 'delete', array(), $t));
	pg_assert_same(3, ws_change_tier('product', 'update', array('name', 'price'), $t));
	pg_assert_same(3, ws_change_tier('order', 'update', array('status'), $t));
	pg_assert_same(3, ws_change_tier('order', 'update', array('cancellation_reason'), $t));
	pg_assert_same(3, ws_change_tier('erp_account', 'update', array('name'), $t));
	pg_assert_same(3, ws_change_tier('erp_quote', 'create', array(), $t));
	pg_assert_same(3, ws_change_tier('user', 'update', array(), $t));

	// Unknown kinds, actions and fields are never run unattended.
	pg_assert_same(3, ws_change_tier('nothing', 'update', array(), $t));
	pg_assert_same(3, ws_change_tier('order', 'delete', array(), $t));
	pg_assert_same(3, ws_change_tier('product', 'update', array('no_such_field'), $t));
}

function test_workspace_changes_tier_and_allows()
{
	$t = workspace_agent_tiers_test_types();

	pg_assert_same(1, ws_changes_tier(array(), $t));
	pg_assert_same(2, ws_changes_tier(array(array('type' => 'task', 'action' => 'update', 'fields' => array('title' => 'x'))), $t));
	pg_assert_same(2, ws_changes_tier(array(array('type' => 'task', 'action' => 'update', 'fields' => array('title'))), $t), 'field names as a list');
	pg_assert_same(3, ws_changes_tier(array(
		array('type' => 'task', 'action' => 'update', 'fields' => array('title' => 'x')),
		array('type' => 'product', 'action' => 'update', 'fields' => array('price' => 100)),
	), $t));

	pg_assert_true(ws_agent_tier_allows(1, 1));
	pg_assert_true(!ws_agent_tier_allows(1, 2), 'tier 1 only reads and writes messages');
	pg_assert_true(ws_agent_tier_allows(2, 2));
	pg_assert_true(!ws_agent_tier_allows(2, 3));
	pg_assert_true(ws_agent_tier_allows(3, 3));
	pg_assert_true(!ws_agent_tier_allows(9, 2), 'a tier out of range counts as 1');
	pg_assert_true(!ws_agent_tier_allows(0, 2));
}

// The kinds held irreversible as a whole are kinds of the catalogue.
function test_workspace_change_tier3_types_are_in_the_catalogue()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/changes.php');

	foreach (ws_change_tier3_types() as $type) {
		pg_assert_true(strpos($source, "        '" . $type . "' => array(") !== false, $type . ' is a kind');
	}
}

// A catalogue with the columns the undo compares against.
function workspace_agent_undo_test_types()
{
	return array(
		'product' => array('actions' => array('update', 'create', 'delete'), 'fields' => array('name' => array('text', 255, 'Name', 'name'), 'price' => array('money', 0, 'Price', 'price'), 'tags' => array('ids', 'tag', 'Tags', ''))),
		'note'    => array('actions' => array('create'), 'fields' => array('title' => array('text', 150, 'Title', 'title'))),
		'task'    => array('actions' => array('update'), 'fields' => array('title' => array('text', 150, 'Title', 'title'))),
	);
}

function workspace_agent_undo_test_row($type, $action, $fields, $extra = array())
{
	return array_merge(array(
		'record_type'     => $type,
		'action'          => $action,
		'record_id'       => 7,
		'fields'          => json_encode($fields),
		'status'          => 'applied',
		'applied_by_kind' => 'app',
		'decided_at'      => 1000,
	), $extra);
}

function test_workspace_agent_applies_at_once()
{
	$t = workspace_agent_undo_test_types();
	$name = workspace_agent_undo_test_row('product', 'update', array(array('name' => 'name', 'from' => 'A', 'to' => 'B')));
	$price = workspace_agent_undo_test_row('product', 'update', array(array('name' => 'price', 'from' => 100, 'to' => 200)));

	pg_assert_same(2, ws_change_row_tier($name, $t));
	pg_assert_same(3, ws_change_row_tier($price, $t));
	pg_assert_true(ws_agent_applies_at_once(array($name), 2, $t));
	pg_assert_true(!ws_agent_applies_at_once(array($name), 1, $t), 'tier 1 only proposes');
	pg_assert_true(!ws_agent_applies_at_once(array($name, $price), 2, $t), 'one change above the tier keeps the whole answer a proposal');
	pg_assert_true(ws_agent_applies_at_once(array($name, $price), 3, $t));
	pg_assert_true(!ws_agent_applies_at_once(array(), 3, $t));
	pg_assert_same(3, ws_change_row_tier(workspace_agent_undo_test_row('mcp_call', 'run', array()), $t), 'an unknown kind is tier 3');
}

function test_workspace_agent_undo_reverse()
{
	$t = workspace_agent_undo_test_types();

	$update = ws_agent_undo_reverse(workspace_agent_undo_test_row('product', 'update', array(array('name' => 'name', 'from' => 'A', 'to' => 'B'))), $t);
	pg_assert_same(array('action' => 'update', 'fields' => array(array('name' => 'name', 'from' => 'B', 'to' => 'A'))), $update, 'written back from the before value, checked against what the agent wrote');

	$create = ws_agent_undo_reverse(workspace_agent_undo_test_row('product', 'create', array(array('name' => 'name', 'from' => '', 'to' => 'New'))), $t);
	pg_assert_same(array('action' => 'delete', 'fields' => array(array('name' => 'name', 'from' => 'New', 'to' => ''))), $create, 'a new record is deleted');

	pg_assert_same(null, ws_agent_undo_reverse(workspace_agent_undo_test_row('note', 'create', array(array('name' => 'title', 'from' => '', 'to' => 'N'))), $t), 'a kind that cannot delete');
	pg_assert_same(null, ws_agent_undo_reverse(workspace_agent_undo_test_row('product', 'update', array(array('name' => 'price', 'from' => 1, 'to' => 2))), $t), 'tier 3 is irreversible');
	pg_assert_same(null, ws_agent_undo_reverse(workspace_agent_undo_test_row('product', 'update', array(array('name' => 'tags', 'from' => '', 'to' => array(1)))), $t), 'a value the record does not keep');
	pg_assert_same(null, ws_agent_undo_reverse(workspace_agent_undo_test_row('product', 'update', array(array('name' => 'name', 'from' => 'A', 'to' => 'B')), array('record_id' => 0)), $t));
}

function test_workspace_agent_undo_open()
{
	$row = workspace_agent_undo_test_row('task', 'update', array());

	pg_assert_true(ws_agent_undo_open($row, 1000 + WS_AGENT_UNDO_SECONDS));
	pg_assert_true(!ws_agent_undo_open($row, 1001 + WS_AGENT_UNDO_SECONDS), 'seven days');
	pg_assert_true(!ws_agent_undo_open(array_merge($row, array('applied_by_kind' => 'user')), 1000), 'a person applied it');
	pg_assert_true(!ws_agent_undo_open(array_merge($row, array('status' => 'undone')), 1000));
	pg_assert_true(!ws_agent_undo_open(array_merge($row, array('decided_at' => 0)), 1000));
}
