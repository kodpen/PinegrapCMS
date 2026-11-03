<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the new dashboard's role defaults (includes/dashboard/v2/
 * layout.php): which layout and look a person sees beside a role default,
 * and which roles the Share with the team section may write.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/dashboard/v2/layout.php');

// No default: the person's own row as it is, or null.
function test_dashboard_v2_share_effective_without_default()
{
	$own = array('enabled' => 1, 'layout' => '{"a":1}', 'look' => '{"s":1}');

	pg_assert_same($own, pg_dashboard_v2_row_effective($own, null), 'own row kept');
	pg_assert_same(null, pg_dashboard_v2_row_effective(null, null), 'nothing');
}

// A default reaches a person who never saved a layout; their own look wins
// when they picked one.
function test_dashboard_v2_share_effective_default_fills_gaps()
{
	$default = array('layout' => '{"d":1}', 'look' => '{"dl":1}', 'locked' => 0);

	$got = pg_dashboard_v2_row_effective(null, $default);
	pg_assert_same('{"d":1}', $got['layout'], 'no row: default layout');
	pg_assert_same('{"dl":1}', $got['look'], 'no row: default look');

	$got = pg_dashboard_v2_row_effective(array('enabled' => 1, 'layout' => '', 'look' => '{"s":1}'), $default);
	pg_assert_same('{"d":1}', $got['layout'], 'empty layout: default layout');
	pg_assert_same('{"s":1}', $got['look'], 'own look kept');
	pg_assert_same(1, $got['enabled'], 'switch kept');

	$own = array('enabled' => 1, 'layout' => '{"a":1}', 'look' => '');
	$got = pg_dashboard_v2_row_effective($own, $default);
	pg_assert_same('{"a":1}', $got['layout'], 'saved layout wins while unlocked');
	pg_assert_same('{"dl":1}', $got['look'], 'no own look: default look');
}

// While locked the default is what everyone in the role sees; their own
// layout stays stored for when the lock lifts.
function test_dashboard_v2_share_effective_locked()
{
	$default = array('layout' => '{"d":1}', 'look' => '{"dl":1}', 'locked' => 1);
	$own = array('enabled' => 1, 'layout' => '{"a":1}', 'look' => '{"s":1}');

	$got = pg_dashboard_v2_row_effective($own, $default);
	pg_assert_same('{"d":1}', $got['layout'], 'locked layout');
	pg_assert_same('{"dl":1}', $got['look'], 'locked look');
	pg_assert_same(1, $got['enabled'], 'switch kept');
}

// Only administrators and designers share; the target is a role or everyone.
function test_dashboard_v2_share_roles()
{
	pg_assert_true(pg_dashboard_v2_can_share(0), 'administrator');
	pg_assert_true(pg_dashboard_v2_can_share(1), 'designer');
	pg_assert_same(false, pg_dashboard_v2_can_share(2), 'manager');
	pg_assert_same(false, pg_dashboard_v2_can_share(3), 'user');

	pg_assert_same(array(9, 0, 1, 2, 3), array_keys(pg_dashboard_v2_share_roles()), 'everyone first, then by role');
	foreach (array(9, 0, 1, 2, 3) as $role) {
		pg_assert_true(pg_dashboard_v2_share_role_valid($role), 'valid ' . $role);
	}
	pg_assert_same(false, pg_dashboard_v2_share_role_valid(4), 'no role 4');
	pg_assert_same(false, pg_dashboard_v2_share_role_valid('x'), 'not a number');
	pg_assert_same(true, pg_dashboard_v2_share_role_valid('2'), 'number as text');
}

// The confirmation names the role and the lock; the section is drawn only
// for those who may share, with the roles in order.
function test_dashboard_v2_share_texts_and_config()
{
	pg_assert_same('Default for everyone', pg_dashboard_v2_share_done(9, false), 'everyone');
	pg_assert_same('Default for the Manager role · locked', pg_dashboard_v2_share_done(2, true), 'manager locked');

	pg_assert_same(null, pg_dashboard_v2_share_config(array('role' => 2)), 'manager: no section');
	$config = pg_dashboard_v2_share_config(array('role' => 1));
	pg_assert_same(array(9, 0, 1, 2, 3), array_map(function ($pair) { return $pair[0]; }, $config['roles']), 'role order');
}
