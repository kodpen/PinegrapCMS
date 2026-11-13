<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Source test for the style backend.src.js injects for its cell editor
 * (style#pg_list_edit_style): every rule names the cells of a table carrying
 * data-pg-edit-url, the only ones that editor opens. The list table driver's
 * cells carry data-pg-edit too (pg_list_table_edit.js); there the pencil
 * after a quick edit field stood on a line of its own, so the field sat at
 * the top of a taller row while the text cells stayed centred.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_list_edit_style_names_only_its_own_tables()
{
	foreach (array('backend.src.js', 'backend.min.js') as $file) {
		$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/' . $file);
		$start = strpos($source, 'pg_list_edit_style');
		$end = ($start === false) ? false : strpos($source, 'appendChild', $start);

		pg_assert_true(($start !== false) && ($end !== false), $file . ': the injected style is there');

		$style = (string) substr($source, (int) $start, (int) $end - (int) $start);

		pg_assert_true(substr_count($style, 'table[data-pg-edit-url] td[data-pg-edit]') >= 6, $file . ': the cell rules name the editor\'s tables');
		pg_assert_same(0, preg_match('/(?<!table\[data-pg-edit-url\] )td(\[data-pg-edit|\.pg-edit-)/', $style), $file . ': no rule reaches every editable cell');
	}

	// The editor itself opens only such cells.
	pg_assert_contains("var table = cell.closest('table[data-pg-edit-url]');", (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/backend.src.js'));
}
