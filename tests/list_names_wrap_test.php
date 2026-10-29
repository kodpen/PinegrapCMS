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
		'view_files.php' => '<td class="align-middle text-start text-wrap \' . $file[\'folder_access_control_type\'] . \'">\' . pg_folder_list_name($file[\'folder_name\'], $file[\'folder_access_control_type\']) . \'</td>',
		'view_pages.php' => '<td class="align-middle text-start text-wrap \' . $page[\'folder_access_control_type\'] . \'">\' . pg_folder_list_name($page[\'folder_name\'], $page[\'folder_access_control_type\']) . \'</td>',
		'view_design_files.php' => '<td class="align-middle text-start text-wrap">\' . pg_folder_list_name($file[\'folder_name\']) . \'</td>',
		'view_themes.php' => '<td class="align-middle text-start text-wrap \' . $folder_access_control_type . \'">\' . pg_folder_list_name($file[\'folder_name\']) . \'<span class="badge',
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
	foreach (array('view_files.php', 'view_design_files.php') as $path) {
		$source = list_names_wrap_source($path);
		pg_assert_contains($wrap . '\' . nl2br(h($file[\'description\'])) . \'</div></td>', $source, $path);
		pg_assert_false(strpos($source, 'width:200px') !== false, $path . ': no 200px description box');
		pg_assert_false(strpos($source, 'title="\' . nl2br(h($file[\'description\'])) . \'"') !== false, $path . ': no hover-only title');
	}

	$fields = list_names_wrap_source('view_fields.php');
	pg_assert_contains('$output_label_cell = \'<td class="text-wrap">' . $wrap . '\' . $label_or_information . \'</div></td>\';', $fields);
	// Information text is rich text that can run to paragraphs: it keeps its cut preview.
	pg_assert_contains('<td title="\' . h(strip_tags($label_or_information)) . \'"><span class="text-truncate overflow-hidden d-block" style="width:200px;max-width:100%;">\' . $label_or_information . \'</span></td>', $fields);

	$shipping = list_names_wrap_source('view_shipping_methods.php');
	pg_assert_contains('<td class="align-middle chart_label \' . $status_color_class . \'">' . $wrap . '\' . h($name) . \'</div></td>', $shipping);
	pg_assert_contains('<td class="align-middle">' . $wrap . '\' . h($description) . \'</div></td>', $shipping);
	pg_assert_false(strpos($shipping, 'text-nowrap text-truncate') !== false, 'shipping: no truncated cells');
}
