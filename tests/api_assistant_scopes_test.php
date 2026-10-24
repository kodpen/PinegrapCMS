<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the scopes the Workspace settings give the application key they
 * make for an assistant (api_assistant_scopes(), includes/api/scopes.php),
 * that need no database: one choice per permission group, the read of every
 * group but the ones left out, and the write only for the Workspace and the
 * Tasks.
 *
 * The checks read the result itself and the Claude card's own scope rule
 * (ws_claude_scopes_of()), never api_scopes_expand(): the runner does not
 * switch the Workspace module on, so api_scopes_normalise() would drop
 * workspace:write and tasks:write as unknown.
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

require_once(PG_FUNCTIONS_DIR . '/includes/api/scopes.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/claude.php');

// Permission groups the way api_scope_groups() writes them, with the module
// groups beside the core ones, a write-only group, the webhooks group and a
// group nobody has heard of.
function api_assistant_scopes_test_groups()
{
	return array(
		'products'     => array('label' => 'Products',    'read' => 'products:read',     'write' => 'products:write'),
		'inventory'    => array('label' => 'Inventory',   'read' => '',                  'write' => 'inventory:write'),
		'orders'       => array('label' => 'Orders',      'read' => 'orders:read',       'write' => 'orders:write'),
		'customers'    => array('label' => 'Customers',   'read' => 'customers:read',    'write' => 'customers:write'),
		'pages'        => array('label' => 'Pages',       'read' => 'pages:read',        'write' => 'pages:write'),
		'design'       => array('label' => 'Page design', 'read' => 'design:read',       'write' => 'design:write'),
		'key_codes'    => array('label' => 'Key Codes',   'read' => 'key_codes:read',    'write' => 'key_codes:write'),
		'gift_cards'   => array('label' => 'Gift Cards',  'read' => 'gift_cards:read',   'write' => 'gift_cards:write'),
		'commissions'  => array('label' => 'Commissions', 'read' => 'commissions:read',  'write' => ''),
		'seo'          => array('label' => 'SEO',         'read' => 'seo:read',          'write' => ''),
		'webhooks'     => array('label' => 'Webhooks',    'read' => '',                  'write' => 'webhooks:manage'),
		'meta'         => array('label' => 'Site info',   'read' => 'meta:read',         'write' => ''),
		'system'       => array('label' => 'System',      'read' => 'system:read',       'write' => ''),
		'site_log'     => array('label' => 'Site Log',    'read' => 'site_log:read',     'write' => ''),
		'account'      => array('label' => 'Own account', 'read' => 'account:read',      'write' => 'account:write'),
		'workspace'    => array('label' => 'Workspace',   'read' => 'workspace:read',    'write' => 'workspace:write'),
		'tasks'        => array('label' => 'Tasks',       'read' => 'tasks:read',        'write' => 'tasks:write'),
		'erp'          => array('label' => 'ERP',         'read' => 'erp:read',          'write' => 'erp:write'),
		'erp_cash'     => array('label' => 'Cash',        'read' => 'erp_cash:read',     'write' => 'erp_cash:write'),
		'translations' => array('label' => 'Translations', 'read' => 'translations:read', 'write' => 'translations:write'),
		'foo'          => array('label' => 'Foo',         'read' => 'foo:read',          'write' => 'foo:write'),
	);
}

// The five reads the key is made without.
function test_api_assistant_scopes_left_out_list()
{
	$left_out = api_assistant_scopes_left_out();

	sort($left_out);

	pg_assert_same(array('account:read', 'gift_cards:read', 'key_codes:read', 'site_log:read', 'system:read'), $left_out, 'the five reads left out');
}

// Every group's read but the ones left out, and the Workspace and Tasks
// writes in place of their reads.
function test_api_assistant_scopes_reads_and_workspace_writes()
{
	$groups = api_assistant_scopes_test_groups();
	$leave_out = api_assistant_scopes_left_out();
	$result = api_assistant_scopes($groups, $leave_out);

	foreach ($groups as $key => $group) {
		if ($group['read'] === '') {
			continue;
		}

		if (in_array($key, array('workspace', 'tasks'), true)) {
			pg_assert_false(in_array($group['read'], $result, true), $group['read'] . ' is not stored: the write includes it');
		} elseif (in_array($group['read'], $leave_out, true)) {
			pg_assert_false(in_array($group['read'], $result, true), $group['read'] . ' is left out');
		} else {
			pg_assert_contains($group['read'], $result, $group['read'] . ' is read');
		}
	}

	$writes = array();

	foreach ($result as $scope) {
		if (substr($scope, -5) !== ':read') {
			$writes[] = $scope;
		}
	}

	pg_assert_same(array('tasks:write', 'workspace:write'), $writes, 'the only writes are the Workspace and the Tasks');
	pg_assert_false(in_array('webhooks:manage', $result, true), 'never webhooks:manage');
	pg_assert_contains('foo:read', $result, 'a group nobody listed is read too');
	pg_assert_false(in_array('foo:write', $result, true), 'but never written');
	pg_assert_false(in_array('', $result, true), 'an empty read adds nothing');
	pg_assert_false(in_array('inventory:write', $result, true), 'a write-only group adds nothing');
}

// The set a site with the store, calendars, ERP and translations on gets.
function test_api_assistant_scopes_expected_set()
{
	$groups = api_assistant_scopes_test_groups();

	unset($groups['foo']);

	$groups['calendars'] = array('label' => 'Calendars', 'read' => 'calendars:read', 'write' => 'calendars:write');
	$groups['comments'] = array('label' => 'Comments', 'read' => 'comments:read', 'write' => 'comments:write');
	$groups['offers'] = array('label' => 'Offers', 'read' => 'offers:read', 'write' => 'offers:write');
	$groups['files'] = array('label' => 'Files', 'read' => 'files:read', 'write' => 'files:write');
	$groups['forms'] = array('label' => 'Forms', 'read' => 'forms:read', 'write' => 'forms:write');
	$groups['visitors'] = array('label' => 'Visitors', 'read' => 'visitors:read', 'write' => '');
	$groups['campaigns'] = array('label' => 'Campaigns', 'read' => 'campaigns:read', 'write' => 'campaigns:write');
	$groups['contact_groups'] = array('label' => 'Contact Groups', 'read' => 'contact_groups:read', 'write' => 'contact_groups:write');

	pg_assert_same(array(
		'calendars:read', 'campaigns:read', 'comments:read', 'commissions:read', 'contact_groups:read',
		'customers:read', 'design:read', 'erp:read', 'erp_cash:read', 'files:read', 'forms:read',
		'meta:read', 'offers:read', 'orders:read', 'pages:read', 'products:read', 'seo:read',
		'tasks:write', 'translations:read', 'visitors:read', 'workspace:write',
	), api_assistant_scopes($groups, api_assistant_scopes_left_out()), 'the 21 scopes');
}

// A read is left out only when the list names it.
function test_api_assistant_scopes_leave_out_is_honoured()
{
	$groups = api_assistant_scopes_test_groups();

	pg_assert_false(in_array('account:read', api_assistant_scopes($groups, array('account:read')), true), 'account:read left out');
	pg_assert_contains('account:read', api_assistant_scopes($groups, array()), 'account:read read when nothing is left out');
	pg_assert_contains('account:read', api_assistant_scopes($groups), 'nothing is left out by default');
	pg_assert_false(in_array('account:write', api_assistant_scopes($groups, array()), true), 'its write never');
}

// One entry per scope, in sort() order.
function test_api_assistant_scopes_unique_and_sorted()
{
	$groups = api_assistant_scopes_test_groups();

	// A module group that repeats a core read.
	$groups['orders_again'] = array('label' => 'Orders', 'read' => 'orders:read', 'write' => '');

	$result = api_assistant_scopes($groups, api_assistant_scopes_left_out());
	$expected = array_values(array_unique($result));

	sort($expected);

	pg_assert_same($expected, $result, 'unique and sorted');
	pg_assert_same(array_keys($result), range(0, count($result) - 1), 'a list');
}

// The key carries every permission the Claude card asks for.
function test_api_assistant_scopes_meet_claude_requirements()
{
	$result = api_assistant_scopes(api_assistant_scopes_test_groups(), api_assistant_scopes_left_out());

	pg_assert_true(empty(array_diff(ws_claude_required_scopes(), ws_claude_scopes_of(json_encode($result)))), 'workspace and tasks, read and write');
}

// Over the site's own groups the same rules hold: no write but the
// Workspace's and the Tasks', and none of the five.
function test_api_assistant_scopes_site_groups()
{
	$result = api_assistant_scopes(api_scope_groups(), api_assistant_scopes_left_out());

	foreach ($result as $scope) {
		if (substr($scope, -5) !== ':read') {
			pg_assert_true(in_array($scope, array('workspace:write', 'tasks:write'), true), $scope . ' is not a write the key may hold');
		}
	}

	foreach (api_assistant_scopes_left_out() as $scope) {
		pg_assert_false(in_array($scope, $result, true), $scope . ' is left out');
	}

	pg_assert_contains('orders:read', $result, 'a core read');
	pg_assert_contains('meta:read', $result, 'the site info');
}
