<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the places outside the main menu that stand for a menu row and
 * draw that row's icon (pg_menu_icon_cite()): the drawing at rest, the
 * stylesheet that sizes it and hides its live part, and the places that ask
 * for it (the Workspace tools dock, the dashboard cards' heads).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/dashboard/v2/cards.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/nav.php');

function test_menu_icon_cite_is_the_rows_drawing_without_a_tile()
{
	$svg = pg_menu_icon_cite('notes');

	pg_assert_contains('class="pg-mi pg-mi-notes pg-mi-cite"', $svg, 'the drawing, marked as a cite, with no margin of the menu');
	pg_assert_same(false, strpos($svg, 'pg-mi-tile'), 'no tile');
	pg_assert_contains('class="pg-mi pg-mi-flows pg-mi-cite me-1"', pg_menu_icon_cite('flows', 'me-1'), 'a class of the place is added');
	pg_assert_same('', pg_menu_icon_cite('no-such-row'), 'a name without a drawing gives nothing');
}

function test_menu_icon_cite_has_its_size_and_hides_the_live_part()
{
	$css = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/css/backend.src.css');

	pg_assert_contains("svg.pg-mi-cite {\n  width: 1em;\n  height: 1em;", $css, 'the size of the glyph it replaces');
	pg_assert_contains(".pg-mi-cite [data-l] {\n  display: none;", $css, 'the flows dot is not drawn');
	pg_assert_contains(".pg-mi-cite .pg-mi-f,\n.pg-mi-cite .pg-mi-date {\n  fill: currentColor;", $css, 'filled parts take the colour');
}

function test_menu_icon_cite_every_dashboard_card_names_a_drawing()
{
	$cards = pg_dashboard_v2_cards();

	foreach (pg_dashboard_v2_card_menu_icons() as $id => $name) {
		pg_assert_true(isset($cards[$id]), 'card ' . $id . ' exists');
		pg_assert_true(pg_menu_icon_cite($name) !== '', 'card ' . $id . ' names a drawing: ' . $name);
	}
}

function test_menu_icon_cite_tools_dock_sends_the_drawing()
{
	$groups = ws_nav_groups(array('id' => 1, 'role' => 1), array('notes' => true, 'flows' => true, 'base' => '/'));
	$nav = ws_side_nav($groups);
	$svg = array();

	foreach ($nav['tools'] as $item) {
		$svg[$item['key']] = $item['svg'] ?? '';
	}

	pg_assert_contains('pg-mi-notes pg-mi-cite', $svg['notes'], 'My Notes draws its row');
	pg_assert_contains('pg-mi-flows pg-mi-cite', $svg['flows'], 'Automation flows draws its row');
	pg_assert_same('', $svg['tasks'], 'My Tasks has no row and keeps its bi icon');
}
