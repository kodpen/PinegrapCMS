<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Source test for the focus rings backend.src.js injects: the sortable table
 * heading buttons (style#pg_sort_style) and the list quick-edit cells
 * (style#pg_list_edit_style) draw the panel's 2px --pg-primary-ink ring. The
 * injected styles are unlayered, so they beat the global ring in
 * @layer custom; a --pg-primary ring there read about 2.2:1 on white.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_backend_js_focus_rings_use_the_ink_colour()
{
	foreach (array('backend.src.js', 'backend.min.js') as $file) {
		$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/' . $file);

		pg_assert_contains('.pg-sort-btn:focus-visible{outline:2px solid var(--pg-primary-ink);outline-offset:2px;border-radius:2px}', $source,
			$file . ': sort heading buttons draw the ink ring');
		pg_assert_contains('td[data-pg-edit]:focus-visible{outline:2px solid var(--pg-primary-ink);outline-offset:-2px}', $source,
			$file . ': quick-edit cells draw the ink ring, inset inside the table');
		pg_assert_same(0, preg_match('/:focus-visible\{[^}]*outline:2px solid var\(--pg-primary\)/', $source),
			$file . ': no injected ring uses the light accent');
	}
}
