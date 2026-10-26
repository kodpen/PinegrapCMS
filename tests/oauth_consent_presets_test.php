<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the consent screen's presets (pg_oauth_consent_presets() and
 * its helpers in includes/oauth/rules.php): every preset lets the
 * application propose a record change, none ticks a record write, and the
 * boxes ticked never go beyond what the application asked for.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

if (!defined('PG_OAUTH_ENTRY')) {
	define('PG_OAUTH_ENTRY', true);
}

if (!defined('PG_API_PANEL')) {
	define('PG_API_PANEL', true);
}

if (!defined('PG_INIT_LOADED')) {
	define('PG_INIT_LOADED', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/oauth/rules.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/scopes.php');

// Permission groups the way api_scope_groups() writes them on a site with
// the Workspace and ERP modules switched on (no module is loaded here).
function oauth_consent_presets_test_groups()
{
	return array(
		'products'       => array('label' => 'Products',       'read' => 'products:read',       'write' => 'products:write'),
		'inventory'      => array('label' => 'Inventory',      'read' => '',                    'write' => 'inventory:write'),
		'orders'         => array('label' => 'Orders',         'read' => 'orders:read',         'write' => 'orders:write'),
		'customers'      => array('label' => 'Customers',      'read' => 'customers:read',      'write' => 'customers:write'),
		'comments'       => array('label' => 'Comments',       'read' => 'comments:read',       'write' => 'comments:write'),
		'offers'         => array('label' => 'Offers',         'read' => 'offers:read',         'write' => 'offers:write'),
		'key_codes'      => array('label' => 'Key Codes',      'read' => 'key_codes:read',      'write' => 'key_codes:write'),
		'gift_cards'     => array('label' => 'Gift Cards',     'read' => 'gift_cards:read',     'write' => 'gift_cards:write'),
		'commissions'    => array('label' => 'Commissions',    'read' => 'commissions:read',    'write' => ''),
		'campaigns'      => array('label' => 'Campaigns',      'read' => 'campaigns:read',      'write' => 'campaigns:write'),
		'contact_groups' => array('label' => 'Contact Groups', 'read' => 'contact_groups:read', 'write' => 'contact_groups:write'),
		'webhooks'       => array('label' => 'Webhooks',       'read' => '',                    'write' => 'webhooks:manage'),
		'meta'           => array('label' => 'Site info',      'read' => 'meta:read',           'write' => ''),
		'system'         => array('label' => 'System',         'read' => 'system:read',         'write' => ''),
		'site_log'       => array('label' => 'Site Log',       'read' => 'site_log:read',       'write' => ''),
		'account'        => array('label' => 'Own account',    'read' => 'account:read',        'write' => 'account:write'),
		'workspace'      => array('label' => 'Workspace',      'read' => 'workspace:read',      'write' => 'workspace:write'),
		'tasks'          => array('label' => 'Tasks',          'read' => 'tasks:read',          'write' => 'tasks:write'),
		'erp'            => array('label' => 'ERP',            'read' => 'erp:read',            'write' => 'erp:write'),
		'erp_cash'       => array('label' => 'ERP cash',       'read' => 'erp_cash:read',       'write' => 'erp_cash:write'),
	);
}

// The presets as the consent screen builds them.
function oauth_consent_presets_test_site($groups = null)
{
	$groups = ($groups === null) ? oauth_consent_presets_test_groups() : $groups;

	return pg_oauth_consent_presets($groups, api_assistant_scopes($groups, api_assistant_scopes_left_out()));
}

// Four presets, in the order the screen shows them.
function test_oauth_consent_presets_keys_and_order()
{
	pg_assert_same(array('workspace', 'store', 'accounting', 'all'), array_keys(oauth_consent_presets_test_site()), 'presets');
}

// The propose route needs workspace:write; every preset ticks it, and the
// Tasks write beside it.
function test_oauth_consent_presets_always_let_the_application_propose()
{
	foreach (oauth_consent_presets_test_site() as $key => $preset) {
		pg_assert_contains('workspace:write', $preset['scopes'], $key . ': workspace:write');
		pg_assert_contains('tasks:write', $preset['scopes'], $key . ': tasks:write');
	}
}

// No preset ticks a record write: those are ticked by hand.
function test_oauth_consent_presets_tick_no_record_write()
{
	foreach (oauth_consent_presets_test_site() as $key => $preset) {
		foreach ($preset['scopes'] as $scope) {
			if (substr($scope, -5) !== ':read') {
				pg_assert_true(in_array($scope, array('workspace:write', 'tasks:write'), true), $key . ': ' . $scope . ' is a record write');
			}
		}
	}
}

// Every scope a preset ticks is one of the groups'; a site without the
// Workspace or ERP modules gets no scope of theirs.
function test_oauth_consent_presets_name_known_scopes()
{
	foreach (array(oauth_consent_presets_test_groups(), api_scope_groups()) as $groups) {
		$all = array();

		foreach ($groups as $group) {
			$all[] = $group['read'];
			$all[] = $group['write'];
		}

		foreach (oauth_consent_presets_test_site($groups) as $key => $preset) {
			foreach ($preset['scopes'] as $scope) {
				pg_assert_true(in_array($scope, $all, true), $key . ': ' . $scope . ' is no scope of the site');
			}
		}
	}
}

// The store groups are core groups of the site with a read, so a renamed
// group cannot drop out of the preset unnoticed.
function test_oauth_consent_presets_store_groups_exist()
{
	$groups = api_scope_groups();

	foreach (pg_oauth_consent_store_groups() as $key) {
		pg_assert_true(isset($groups[$key]) && ($groups[$key]['read'] !== ''), $key . ' is a group with a read');
	}
}

// What each preset reads.
function test_oauth_consent_presets_contents()
{
	$presets = oauth_consent_presets_test_site();

	pg_assert_same(array('workspace:read', 'workspace:write', 'tasks:read', 'tasks:write'), $presets['workspace']['scopes'], 'workspace');

	pg_assert_contains('orders:read', $presets['store']['scopes'], 'store reads orders');
	pg_assert_contains('products:read', $presets['store']['scopes'], 'store reads products');
	pg_assert_false(in_array('erp:read', $presets['store']['scopes'], true), 'store reads no accounts');
	pg_assert_false(in_array('gift_cards:read', $presets['store']['scopes'], true), 'store holds no gift card codes');

	pg_assert_contains('erp:read', $presets['accounting']['scopes'], 'accounting reads the ERP');
	pg_assert_contains('erp_cash:read', $presets['accounting']['scopes'], 'accounting reads the cash');
	pg_assert_false(in_array('orders:read', $presets['accounting']['scopes'], true), 'accounting reads no orders');

	foreach (api_assistant_scopes_left_out() as $scope) {
		pg_assert_false(in_array($scope, $presets['all']['scopes'], true), 'all leaves out ' . $scope);
	}

	pg_assert_contains('erp:read', $presets['all']['scopes'], 'all reads the ERP');
	pg_assert_contains('orders:read', $presets['all']['scopes'], 'all reads orders');
}

// A preset the request does not name, or names wrongly, opens as "all".
function test_oauth_consent_preset_key()
{
	$presets = oauth_consent_presets_test_site();

	pg_assert_same('all', pg_oauth_consent_preset_key('', $presets), 'none');
	pg_assert_same('all', pg_oauth_consent_preset_key('everything', $presets), 'unknown');
	pg_assert_same('all', pg_oauth_consent_preset_key(array('store'), $presets), 'not a string');
	pg_assert_same('store', pg_oauth_consent_preset_key('store', $presets), 'store');
}

// The ticked boxes: what the preset ticks of what was asked, in the asked
// order; never a scope the application did not ask for.
function test_oauth_consent_ticked_never_widens()
{
	$asked = array('products:write', 'orders:read', 'workspace:write');

	pg_assert_same(array('orders:read', 'workspace:write'), pg_oauth_consent_ticked($asked, array('workspace:write', 'tasks:write', 'orders:read')), 'common part');
	pg_assert_same(array(), pg_oauth_consent_ticked(array('products:write'), array('workspace:write')), 'only a record write asked');
}
