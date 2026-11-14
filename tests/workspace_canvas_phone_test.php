<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the canvas screens at phone width (assets/js/workspace_page_card.js,
 * workspace_wtables.php, backend.src.css): the part chips under the name,
 * Full screen at the joined card's foot, and New table floating on the
 * Data list.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_workspace_canvas_phone_card()
{
	$js = file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace_page_card.js');

	pg_assert_true(strpos($js, "'d-none sm:d-flex flex-wrap gap-1 ms-auto'") !== false, 'chips beside the name from 576 px');
	pg_assert_true(strpos($js, "'d-flex sm:d-none flex-wrap gap-1 mt-2'") !== false, 'chips under the name on a phone');
	pg_assert_true(strpos($js, "fullButton.dataset.pageFull = '1'") !== false && strpos($js, "stage.querySelector('[data-page-full]')") !== false, 'the foot presses the page card\'s own Full screen');
	pg_assert_true(strpos($js, "if (opts.bare && !fullButton.hidden)") !== false, 'the phone classes only where the button shows: a display class beats hidden');
	pg_assert_true(strpos(file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace.min.js'), 'data-page-full') !== false, 'the bundle is built');
	pg_assert_true(strpos($js, "h.el('div', 'pg-seg pg-seg-icons mt-2')") !== false && strpos($js, "h.el('div', 'pg-seg pg-seg-icons mb-3')") !== false, 'the Drawing / Page switch and the Versions filter are icon segments');
	pg_assert_true(strpos($js, 'btn-group') === false, 'no button group left on the card');
}

function test_workspace_canvas_phone_data_list()
{
	$screen = file_get_contents(PG_FUNCTIONS_DIR . '/workspace_wtables.php');
	$css = file_get_contents(PG_FUNCTIONS_DIR . '/assets/css/backend.src.css');

	pg_assert_true(strpos($screen, 'class="pg-button pg-button-fill ws-wt-fab sm:d-none"') !== false, 'New table floats on a phone');
	pg_assert_true(strpos($screen, 'class="pg-button pg-button-fill d-none sm:d-inline-flex" data-bs-toggle="dialog" data-bs-target="#ws_wt_new"') !== false, 'the toolbar\'s New table from 576 px');

	pg_assert_true(strpos($css, '.pg-seg-icons .pg-seg-item:not(.is-active, [aria-pressed="true"], [aria-current]) .pg-seg-label {') !== false, 'below 576 px only the chosen segment keeps its label');

	preg_match('/\.ws-wt-fab \{([^}]*)\}/', $css, $rule);
	pg_assert_true(!empty($rule), 'the placing rule is there');
	pg_assert_true(!preg_match('/\b(width|height|padding|font|color|background|border|shadow)\b/', $rule[1]), 'the screen\'s class only places the button');
}
