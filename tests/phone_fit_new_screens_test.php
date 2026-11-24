<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests that the 2026.11.0 screens fit a 390 px phone with long names: a
 * volunteer place's QR poster button shortens its name, and a queue place's
 * desk and screen rows draw their title as a block that ends in an ellipsis.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/volunteer_hours_view.php');

function test_phone_fit_volunteer_poster_button_shortens_a_long_place()
{
	$place = 'Barınak ana bina, arka bahçe girişi (Moda Cad. 112)';
	$html = pg_volunteer_posters_html(array('id' => 4), array($place));

	pg_assert_true(strpos($html, 'class="pg-button pg-button-quiet pg-button-sm mw-100"') !== false, 'the button is no wider than its card');
	pg_assert_true(strpos($html, '<span class="text-truncate">' . h($place) . '</span>') !== false, 'the name ends in an ellipsis');
	pg_assert_true(strpos($html, 'title="' . h($place) . '"') !== false, 'the whole name stays in the tooltip');
}

function test_phone_fit_queue_place_row_titles_are_blocks()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/edit_queue_place.php');

	pg_assert_same(0, substr_count($source, '<strong class="pg-list-row-title">'), 'an inline title neither wraps nor ends in an ellipsis');
	pg_assert_same(2, substr_count($source, '<strong class="pg-list-row-title d-block">'), 'the desk and screen rows draw a block title');
}
