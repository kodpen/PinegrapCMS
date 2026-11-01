<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the new dashboard's card list (includes/dashboard/v2/cards.php):
 * the thirty cards, their shapes, and the gates every card names.
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
require_once(PG_FUNCTIONS_DIR . '/includes/dashboard/v2/cards.php');

// The plan's thirty ids, in gallery order; layouts store them, so none may
// change or go missing.
function test_dashboard_v2_cards_has_the_thirty_ids()
{
	$ids = array('ai', 'goals', 'pipeline', 'queue', 'activity', 'flows', 'pins', 'notes',
		'content', 'comments', 'files', 'visitors', 'visitmap', 'engagement', 'gsearch', 'ga',
		'firewall', 'team', 'mytasks', 'waiting', 'week', 'calendars',
		'shop', 'salesmap', 'ops', 'orders', 'offers', 'fx', 'perf', 'subs');

	pg_assert_same($ids, array_keys(pg_dashboard_v2_cards()));
}

// Every card names a known category, width, period and gate, and no id is a
// widget number.
function test_dashboard_v2_cards_shapes_are_valid()
{
	$categories = pg_dashboard_v2_categories();
	$periods = pg_dashboard_v2_periods();
	$gates = pg_dashboard_v2_gates();

	foreach (pg_dashboard_v2_cards() as $id => $card) {
		pg_assert_true(preg_match('/^[a-z]+$/', $id) === 1, $id . ' id');
		pg_assert_true(isset($categories[$card['category']]), $id . ' category');
		pg_assert_true(in_array($card['width'], array('S', 'M', 'L'), true), $id . ' width');
		pg_assert_true(($card['period'] === null) || isset($periods[$card['period']]), $id . ' period');
		pg_assert_true(in_array($card['gate'], $gates, true), $id . ' gate');
		pg_assert_true(($card['title'] !== '') && (strpos($card['icon'], 'bi-') === 0), $id . ' title and icon');
	}
}

// The role-only gates follow the role order (Designer 1 above Manager 2), and
// an unknown gate is closed.
function test_dashboard_v2_gate_by_role()
{
	$designer = array('id' => 1, 'role' => 1, 'manage_visitors' => 0);
	$manager = array('id' => 2, 'role' => 2, 'manage_visitors' => 0);
	$member = array('id' => 3, 'role' => 3, 'manage_visitors' => 0);
	$visitors = array('id' => 4, 'role' => 3, 'manage_visitors' => 1);

	pg_assert_true(pg_dashboard_v2_gate('all', $member), 'all');
	pg_assert_true(pg_dashboard_v2_gate('designer', $designer), 'designer: designer');
	pg_assert_false(pg_dashboard_v2_gate('designer', $manager), 'designer: manager');
	pg_assert_true(pg_dashboard_v2_gate('staff', $manager), 'staff: manager');
	pg_assert_false(pg_dashboard_v2_gate('staff', $member), 'staff: member');
	pg_assert_false(pg_dashboard_v2_gate('visitors', $member), 'visitors: member');
	pg_assert_true(pg_dashboard_v2_gate('visitors', $visitors), 'visitors: right');
	pg_assert_false(pg_dashboard_v2_gate('nope', $designer), 'unknown');
}
