<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the designs home's shared components panel
 * (pg_designer_components_card(), includes/designer_screen.php): the screen
 * prints the button, the count and an empty panel; the rows come from
 * shared_component/home_rows when the panel first opens.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_designer_home_components_rows_load_on_open()
{
	$screen = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/designer_screen.php');
	$panel = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/panel/shared_component.php');
	$script = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/designer_home.js');

	$start = strpos($screen, 'function pg_designer_components_card(');
	$end = strpos($screen, 'function pg_designer_components_rows(');
	pg_assert_true($start !== false && $end !== false && $end > $start, 'card and rows are two functions');
	$card = substr($screen, $start, $end - $start);

	pg_assert_contains('SELECT COUNT(*) FROM shared_components', $card, 'the card counts');
	pg_assert_true(strpos($card, 'pg_shared_component_usage') === false, 'the card reads no usage');
	pg_assert_true(strpos($card, 'db_items(') === false, 'the card reads no rows');
	pg_assert_contains('id="sd-comp-rows"', $card, 'the empty body the script fills');

	pg_assert_contains("case 'home_rows':", $panel, 'routed by the panel action');
	pg_assert_contains('pg_designer_is_full($user)', $panel, 'full designer only');
	pg_assert_contains('pg_designer_components_rows()', $panel, 'answers the rows');
	pg_assert_true(in_array('home_rows', pg_page_cache_panel_sub_actions()['shared_component']['clean'], true), 'reads only: the page cache stays');

	pg_assert_contains("sub_action: 'home_rows'", $script, 'the script asks for the rows');
	pg_assert_contains("opener.addEventListener('click', load)", $script, 'on opening the panel');
}
