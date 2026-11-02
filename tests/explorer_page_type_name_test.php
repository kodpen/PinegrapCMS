<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the page type the File Manager writes: the payload carries the
 * panel-language name of a page's type (get_page_type_name()) and the list,
 * the preview and the type sort read that name, never the stored English
 * code ('custom form', 'shopping cart').
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The payload names the type in the panel language; an unknown code keeps
// the code itself so nothing is drawn empty.
function test_explorer_page_type_name_is_translated()
{
	pg_assert_same(lang('Custom Form'), pg_explorer_page_type_name('custom form'));
	pg_assert_same(lang('Shopping Cart'), pg_explorer_page_type_name('shopping cart'));
	pg_assert_same('', pg_explorer_page_type_name('standard'), 'a standard page needs no type name');
	pg_assert_same('', pg_explorer_page_type_name(''));
	pg_assert_same('legacy kind', pg_explorer_page_type_name('legacy kind'));

	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/view_folder_and_files_f.php');
	pg_assert_contains("'type_name' => pg_explorer_page_type_name(\$page['page_type']),", $source);
}

// The label and the type sort use the translated name.
function test_explorer_page_type_label_reads_the_name()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/view_folders.php');

	pg_assert_contains("if (item.kind === 'page') { return L.page + (item.type_name ? ' · ' + item.type_name : ''); }", $source);
	pg_assert_false(strpos($source, "' (' + item.type + ')'") !== false, 'no raw type code in the label');
	pg_assert_contains("else if (key === 'type') { va = typeSortValue(a); vb = typeSortValue(b); }", $source);
}
