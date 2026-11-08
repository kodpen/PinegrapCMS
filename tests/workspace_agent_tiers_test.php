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
