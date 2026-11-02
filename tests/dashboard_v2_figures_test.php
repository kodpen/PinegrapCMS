<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the new dashboard's figures and greeting card
 * (includes/dashboard/v2/figures.php, page_greeting.php): the eleven tile
 * figures, their notes and tones, the tile and shortcut picks, the sign-in
 * chips and the presets' greeting defaults.
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
require_once(PG_FUNCTIONS_DIR . '/includes/dashboard/v2/figures.php');
require_once(PG_FUNCTIONS_DIR . '/includes/dashboard/v2/page_greeting.php');

// The eleven figures a tile may show, in the select's order; layouts store
// the keys, so none may change or go missing.
function test_dashboard_v2_figures_has_the_eleven_keys()
{
	pg_assert_same(
		array('visits', 'online', 'forms', 'orders', 'seo', 'a11y', 'tasks', 'inbox', 'blocks', 'subscribers', 'flows'),
		array_keys(pg_dashboard_v2_figures()));

	foreach (pg_dashboard_v2_figures() as $key => $figure) {
		pg_assert_true($figure['label'] !== '', $key . ' label');
		pg_assert_true(in_array($figure['gate'], pg_dashboard_v2_figure_gates(), true), $key . ' gate');
		pg_assert_true(is_string($figure['url']) && ($figure['url'] !== ''), $key . ' url');
	}
}

// Every figure turns its raw counts into a number, a note and a tone; the
// tones are the three the card draws.
function test_dashboard_v2_figure_shape_every_key()
{
	$raw = array(
		'visits'      => array('today' => 19, 'yesterday' => 2),
		'online'      => array('visitors' => 3, 'staff' => 1),
		'forms'       => array('pending' => 4, 'oldest' => time() - 3 * 3600),
		'orders'      => array('today' => 5, 'yesterday' => 7),
		'seo'         => array('average' => 49, 'critical' => 75),
		'a11y'        => array('issues' => 12, 'pages' => 3),
		'tasks'       => array('open' => 7, 'overdue' => 4),
		'inbox'       => array('unread' => 2, 'mentions' => 1),
		'blocks'      => array('count' => 41),
		'subscribers' => array('count' => 6),
		'flows'       => array('runs' => 9, 'failed' => 2),
	);

	foreach ($raw as $key => $counts) {
		$shape = pg_dashboard_v2_figure_shape($key, $counts);
		pg_assert_true(is_int($shape['value']), $key . ' value');
		pg_assert_true(is_string($shape['note']), $key . ' note');
		pg_assert_true(in_array($shape['tone'], array('good', 'warn', 'bad', 'plain'), true), $key . ' tone');
	}

	pg_assert_same(19, pg_dashboard_v2_figure_shape('visits', $raw['visits'])['value']);
	pg_assert_same('good', pg_dashboard_v2_figure_shape('visits', $raw['visits'])['tone']);
	pg_assert_same('warn', pg_dashboard_v2_figure_shape('orders', $raw['orders'])['tone']);
	pg_assert_same('bad', pg_dashboard_v2_figure_shape('tasks', $raw['tasks'])['tone']);
	pg_assert_same('bad', pg_dashboard_v2_figure_shape('flows', $raw['flows'])['tone']);
	pg_assert_same(49, pg_dashboard_v2_figure_shape('seo', $raw['seo'])['value']);
	pg_assert_same('warn', pg_dashboard_v2_figure_shape('seo', $raw['seo'])['tone']);
	pg_assert_same('warn', pg_dashboard_v2_figure_shape('forms', $raw['forms'])['tone']);
}

// Quiet figures read plain with an empty note, never a made-up comparison.
function test_dashboard_v2_figure_shape_quiet_is_plain()
{
	$shape = pg_dashboard_v2_figure_shape('tasks', array('open' => 0, 'overdue' => 0));
	pg_assert_same(0, $shape['value']);
	pg_assert_same('plain', $shape['tone']);

	$shape = pg_dashboard_v2_figure_shape('flows', array('runs' => 3, 'failed' => 0));
	pg_assert_same('good', $shape['tone']);

	$shape = pg_dashboard_v2_figure_shape('visits', array('today' => 0, 'yesterday' => 0));
	pg_assert_same('plain', $shape['tone']);
	pg_assert_same('', $shape['note']);

	pg_assert_same(null, pg_dashboard_v2_figure_shape('nope', array()));
}

// A stored tile the person may not see turns into the next allowed figure
// not already shown; four tiles at most, no figure twice.
function test_dashboard_v2_tiles_resolve()
{
	$allowed = array('visits', 'online', 'forms', 'tasks', 'inbox');

	pg_assert_same(array('visits', 'forms', 'online', 'tasks'),
		pg_dashboard_v2_tiles_resolve(array('visits', 'forms', 'seo', 'tasks'), $allowed));
	pg_assert_same(array('tasks', 'visits', 'online', 'forms'),
		pg_dashboard_v2_tiles_resolve(array('tasks', 'tasks', 'orders'), $allowed));
	pg_assert_same(array('visits'), pg_dashboard_v2_tiles_resolve(array('seo', 'orders'), array('visits')));
	pg_assert_same(array(), pg_dashboard_v2_tiles_resolve(array('seo'), array()));
}

// Role-only figure gates follow the old dashboard's widget rules.
function test_dashboard_v2_figure_gates_by_role()
{
	$manager = array('id' => 2, 'role' => 2, 'manage_visitors' => 0);
	$member = array('id' => 3, 'role' => 3, 'manage_visitors' => 0);
	$visitors = array('id' => 4, 'role' => 3, 'manage_visitors' => 1);

	pg_assert_true(pg_dashboard_v2_figure_allowed('seo', $manager), 'seo manager');
	pg_assert_false(pg_dashboard_v2_figure_allowed('seo', $member), 'seo member');
	pg_assert_false(pg_dashboard_v2_figure_allowed('a11y', $member), 'a11y member');
	pg_assert_true(pg_dashboard_v2_figure_allowed('visits', $visitors), 'visits with the visitors right');
	pg_assert_false(pg_dashboard_v2_figure_allowed('visits', $member), 'visits member');
	pg_assert_false(pg_dashboard_v2_figure_allowed('nope', $manager), 'unknown');
}

// Visits since a time are summed from the hourly rollup: the hour the time
// falls in and every later one.
function test_dashboard_v2_hours_since()
{
	$since = mktime(22, 14, 0, 10, 29, 2026);
	$stats = array(
		'2026-10-29' => array(21 => array('new_visitors' => 5), 22 => array('new_visitors' => 3), 23 => array('new_visitors' => 2)),
		'2026-10-30' => array(0 => array('new_visitors' => 1), 17 => array('new_visitors' => 10)),
	);

	pg_assert_same(16, pg_dashboard_v2_hours_since($stats, $since, 'new_visitors'));
	pg_assert_same(0, pg_dashboard_v2_hours_since(array(), $since, 'new_visitors'));
}

// Chips: zero chips are not drawn, the rest keep the preset's order.
function test_dashboard_v2_chips_drop_zero()
{
	$chips = pg_dashboard_v2_chips_shape(array(
		'visits' => 128,
		'forms'  => 0,
		'blocks' => 41,
		'nope'   => 3,
	));

	pg_assert_same(array('visits', 'blocks'), array_column($chips, 'key'));
	pg_assert_same('good', $chips[0]['tone']);
	pg_assert_contains('128', $chips[0]['text']);
	pg_assert_same(array(), pg_dashboard_v2_chips_shape(array('visits' => 0)));
}

// The shortcut pool: every preset's shortcuts and the nine offered by
// "+ Add shortcut" are known keys; a pick keeps six at most, in order, only
// allowed ones.
function test_dashboard_v2_shortcuts_pool_and_pick()
{
	$pool = pg_dashboard_v2_shortcuts();

	foreach (pg_dashboard_v2_shortcut_offer() as $key) {
		pg_assert_true(isset($pool[$key]), 'offer ' . $key);
	}

	foreach (array('corporate', 'blog', 'ecommerce', 'agency', 'simple') as $preset) {
		$defaults = pg_dashboard_v2_greeting_preset($preset);

		foreach ($defaults['actions'] as $key) {
			pg_assert_true(isset($pool[$key]), $preset . ' action ' . $key);
		}

		foreach ($defaults['tiles'] as $key) {
			pg_assert_true(isset(pg_dashboard_v2_figures()[$key]), $preset . ' tile ' . $key);
		}

		foreach ($defaults['chips'] as $key) {
			pg_assert_true(isset(pg_dashboard_v2_chip_list()[$key]), $preset . ' chip ' . $key);
		}

		pg_assert_same(4, count($defaults['tiles']), $preset . ' four tiles');
	}

	foreach ($pool as $key => $shortcut) {
		pg_assert_true(($shortcut['label'] !== '') && ($shortcut['url'] !== ''), $key . ' label and url');
		pg_assert_true(in_array($shortcut['gate'], pg_dashboard_v2_figure_gates(), true), $key . ' gate');
	}

	pg_assert_same(array('new_page', 'forms'),
		pg_dashboard_v2_shortcuts_pick(array('new_page', 'orders', 'forms', 'new_page'), array('new_page', 'forms')));
	pg_assert_same(6, count(pg_dashboard_v2_shortcuts_pick(array_keys($pool), array_keys($pool))));
}

// The first default: Ecommerce with the shop on, else Corporate; a stored
// preset that is not one of the five falls back to it.
function test_dashboard_v2_greeting_settings_from_layout()
{
	$settings = pg_dashboard_v2_greeting_settings('', true);
	pg_assert_same('ecommerce', $settings['preset']);
	pg_assert_same(pg_dashboard_v2_greeting_preset('ecommerce')['tiles'], $settings['tiles']);

	$settings = pg_dashboard_v2_greeting_settings('{"preset":"bogus"}', false);
	pg_assert_same('corporate', $settings['preset']);

	$settings = pg_dashboard_v2_greeting_settings('{"v":1,"preset":"blog","actions":["new_page"],"tiles":["inbox","seo"]}', false);
	pg_assert_same('blog', $settings['preset']);
	pg_assert_same(array('new_page'), $settings['actions']);
	pg_assert_same(array('inbox', 'seo'), $settings['tiles']);
	pg_assert_same(1, $settings['top']['since']);

	$settings = pg_dashboard_v2_greeting_settings('{"top":{"since":0,"tiles":0}}', false);
	pg_assert_same(0, $settings['top']['since']);
	pg_assert_same(0, $settings['top']['tiles']);
	pg_assert_same(1, $settings['top']['actions']);
}
