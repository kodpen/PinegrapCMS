<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests that folder names, descriptions, form field labels and shipping
 * method names wrap in the files, pages, design files, themes, form fields
 * and shipping methods lists instead of being cut in a fixed-width box whose
 * full text only a hover popover showed.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function list_names_wrap_source($path)
{
	return file_get_contents(PG_FUNCTIONS_DIR . '/' . $path);
}

function test_list_names_wrap_folder_name()
{
	$output = pg_folder_list_name('Docs <A>', 'private');
	pg_assert_same(pg_folder_access_mark('private') . '<span class="material-icons d-inline" aria-hidden="true">folder</span><span class="badge fw-light fg-reset d-inline text-wrap text-break">Docs &lt;A&gt;</span>', $output);
	pg_assert_same('<span class="material-icons d-inline" aria-hidden="true">folder</span><span class="badge fw-light fg-reset d-inline text-wrap text-break">X</span>', pg_folder_list_name('X'));
}

function test_list_names_wrap_folder_cells()
{
	$cells = array(
		'includes/fn/page_list.php' => 'pg_list_cell(pg_folder_list_name($page[\'folder_name\'], $page[\'folder_access_control_type\']), \'align-middle text-start text-wrap \' . $page[\'folder_access_control_type\'], $quick(\'folder\', pg_page_quick_edit_value(\'folder\', $page)))',
		'view_design_files.php' => '\'folder\' => pg_list_cell(pg_folder_list_name($file[\'folder_name\']), \'align-middle text-wrap\')',
		'includes/fn/theme_list_edit.php' => '\'folder\' => pg_list_cell(pg_folder_list_name((string) $theme[\'folder_name\']) . \'<span class="badge fw-light fg-reset d-inline">(\' . get_access_control_type_name($access) . \')</span>\', \'align-middle text-wrap \' . $access, $attributes(\'folder\'))',
	);
	foreach ($cells as $path => $cell) {
		$source = list_names_wrap_source($path);
		pg_assert_contains($cell, $source, $path);
		pg_assert_false(strpos($source, 'width: 100px') !== false, $path . ': no 100px folder box');
	}
}

function test_list_names_wrap_text_cells()
{
	$wrap = '<div class="text-wrap text-break" style="max-width:20rem;">';
	foreach (array('view_design_files.php') as $path) {
		$source = list_names_wrap_source($path);
		pg_assert_contains($wrap . '\' . nl2br(h($file[\'description\'])) . \'</div>\')', $source, $path);
		pg_assert_false(strpos($source, 'width:200px') !== false, $path . ': no 200px description box');
		pg_assert_false(strpos($source, 'title="\' . nl2br(h($file[\'description\'])) . \'"') !== false, $path . ': no hover-only title');
	}

	$fields = list_names_wrap_source('includes/fn/field_list.php');
	pg_assert_contains('$label = \'' . $wrap . '\' . h($row[\'label\']) . \'</div>\';', $fields);
	// Information text is rich text that can run to paragraphs: it keeps its cut preview.
	pg_assert_contains('<span class="text-truncate overflow-hidden d-block" style="width:200px;max-width:100%;" title="\' . h(pg_liveform_message_text($text)) . \'">\' . $text . \'</span>', $fields);

	require_once(PG_FUNCTIONS_DIR . '/includes/fn/list_table.php');
	require_once(PG_FUNCTIONS_DIR . '/includes/fn/commerce_list.php');
	require_once(PG_FUNCTIONS_DIR . '/includes/forms/shipping_method_form.php');
	require_once(PG_FUNCTIONS_DIR . '/includes/fn/shipping_method_list_edit.php');
	foreach (array('BASE_CURRENCY_SYMBOL' => '$', 'DATE_FORMAT' => 'day_month') as $name => $value) {
		if (!defined($name)) {
			define($name, $value);
		}
	}
	$shipping = list_names_wrap_source('includes/fn/shipping_method_list_edit.php');
	$cells = pg_shipping_method_list_cells(array('id' => 1, 'name' => 'Courier', 'description' => 'Two days', 'timestamp' => 0));
	// The name and message wrap with a floor width, so the table does not
	// squeeze them to a word per line next to one-line columns.
	pg_assert_contains('<span class="d-inline-block text-wrap text-break" style="min-width:12rem;max-width:20rem;">Courier</span>', $cells['name']['html']);
	pg_assert_contains('<div class="text-wrap text-break" style="min-width:15rem;max-width:24rem;">Two days</div>', $cells['message']['html']);
	pg_assert_false(strpos($shipping, 'text-nowrap text-truncate') !== false, 'shipping: no truncated cells');
}
