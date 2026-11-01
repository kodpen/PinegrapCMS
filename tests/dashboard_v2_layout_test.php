<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the new dashboard's presets and layout save (includes/dashboard/
 * v2/presets.php, layout.php): every preset card is a known card, the first
 * default, and the clean-up a saved layout and look go through.
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
require_once(PG_FUNCTIONS_DIR . '/includes/dashboard/v2/presets.php');

// Every preset names known cards with a width, none twice, and recommends
// known cards (the gallery tags one only while it is not on the dashboard).
function test_dashboard_v2_presets_name_known_cards()
{
	$cards = pg_dashboard_v2_cards();

	foreach (pg_dashboard_v2_presets() as $key => $preset) {
		$ids = array();
		foreach (pg_dashboard_v2_preset_cards($key) as $entry) {
			pg_assert_true(isset($cards[$entry['id']]), $key . ': ' . $entry['id']);
			pg_assert_true(in_array($entry['w'], array('S', 'M', 'L'), true), $key . ': ' . $entry['id'] . ' width');
			$ids[] = $entry['id'];
		}
		pg_assert_same(count($ids), count(array_unique($ids)), $key . ' repeats');

		foreach ($preset['recommended'] as $id) {
			pg_assert_true(isset($cards[$id]), $key . ' recommends ' . $id);
		}
	}
}

// The first default follows the shop switch; Simple takes the first
// default's shortcuts and tiles.
function test_dashboard_v2_preset_layout()
{
	pg_assert_same('ecommerce', pg_dashboard_v2_preset_layout('bogus', true)['preset']);
	pg_assert_same('corporate', pg_dashboard_v2_preset_layout('bogus', false)['preset']);

	$simple = pg_dashboard_v2_preset_layout('simple', false);
	pg_assert_same(pg_dashboard_v2_greeting_preset('corporate')['actions'], $simple['actions']);
	pg_assert_same(array('ai', 'goals', 'queue', 'activity', 'visitors'), array_column($simple['cards'], 'id'));
	pg_assert_same('L', $simple['cards'][4]['w']);
	pg_assert_same(0, $simple['cards'][4]['per']);
	pg_assert_same(array('since' => 1, 'actions' => 1, 'tiles' => 1, 'status' => 1), $simple['top']);
}

// Unknown and unseen ids, repeats and bad values are dropped or defaulted;
// keys outside the pools go; a card the person may not see keeps its
// stored place; goals, pins and notes come from the stored layout.
function test_dashboard_v2_layout_clean()
{
	pg_assert_same(null, pg_dashboard_v2_layout_clean('x', null, array(), array(), array(), false));
	pg_assert_same(null, pg_dashboard_v2_layout_clean(array('cards' => 'x'), null, array(), array(), array(), false));

	$old = array(
		'cards' => array(array('id' => 'visitors'), array('id' => 'firewall', 'w' => 'M'), array('id' => 'engagement')),
		'goals' => array(array('figure' => 'visits', 'target' => 10)),
		'notes' => 'kept',
	);

	$clean = pg_dashboard_v2_layout_clean(array(
		'preset'  => 'blog',
		'cards'   => array(
			array('id' => 'engagement', 'w' => 'L', 'per' => 7, 'cmp' => 1, 'badge' => 0),
			array('id' => 'engagement'),
			array('id' => 'nope'),
			array('id' => 'firewall'),
			array('id' => 'visitors', 'w' => 'X', 'per' => 2),
		),
		'top'     => array('since' => 0, 'status' => false, 'extra' => 1),
		'actions' => array('forms', 'forms', 'add_user', 'nope', 5),
		'tiles'   => array('visits', 'orders', 'seo', 'tasks', 'inbox', 'online'),
		'goals'   => array('dropped'),
		'notes'   => 'dropped',
	), $old, array('visitors', 'engagement'), array('forms', 'edit_page'), array('visits', 'seo', 'tasks', 'inbox', 'online'), false);

	pg_assert_same('blog', $clean['preset']);
	pg_assert_same(array(
		array('id' => 'engagement', 'w' => 'L', 'per' => 0, 'cmp' => 1, 'badge' => 0),
		array('id' => 'firewall', 'w' => 'M'),
		array('id' => 'visitors', 'w' => 'M', 'per' => 2, 'cmp' => 0, 'badge' => 1),
	), $clean['cards']);
	pg_assert_same(array('since' => 0, 'actions' => 1, 'tiles' => 1, 'status' => 0), $clean['top']);
	pg_assert_same(array('forms'), $clean['actions']);
	pg_assert_same(array('visits', 'seo', 'tasks', 'inbox'), $clean['tiles']);
	pg_assert_same($old['goals'], $clean['goals']);
	pg_assert_same(array(), $clean['pins']);
	pg_assert_same('kept', $clean['notes']);

	$clean = pg_dashboard_v2_layout_clean(array('preset' => 'nope', 'cards' => array()), null, array(), array(), array(), true);
	pg_assert_same('ecommerce', $clean['preset']);
	pg_assert_same(array(), $clean['cards']);
}

// A look keeps only values among the choices; the rest stands for the
// site's Appearance value.
function test_dashboard_v2_look_clean()
{
	pg_assert_same(array('dense' => 0, 'calm' => 0), pg_dashboard_v2_look_clean('x'));
	pg_assert_same(
		array('style' => 'glass', 'dense' => 1, 'calm' => 0),
		pg_dashboard_v2_look_clean(array('style' => 'glass', 'backdrop' => 'neon', 'dense' => true, 'extra' => 1))
	);
}
