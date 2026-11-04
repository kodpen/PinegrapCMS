<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the common region form shared by the create and the edit
 * screens (includes/forms/common_region_form.php): the values a save
 * writes, the labels of the card, and that both screens use the shared
 * form while the edit screen keeps its shared component conversion.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/forms/common_region_form.php');

function test_common_region_form_values_trim_the_name_only()
{
	pg_assert_same(
		array('footer', "  <p>Hi</p>\n"),
		pg_common_region_form_values(array('name' => "  footer \n", 'content' => "  <p>Hi</p>\n")),
		'name trimmed, content kept as posted');
	pg_assert_same(array('', ''), pg_common_region_form_values(array()), 'missing fields save empty');
	pg_assert_same(array('', ''), pg_common_region_form_values(array('name' => array('x'), 'content' => array('y'))), 'arrays save empty');
}

function test_common_region_form_labels_point_at_their_fields()
{
	$form = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/forms/common_region_form.php');
	$edit = (string) file_get_contents(PG_FUNCTIONS_DIR . '/edit_common_region.php');

	pg_assert_same(1, substr_count($form, 'for="name"'), 'only the name label points at the name');
	pg_assert_contains('<label for="content_textarea"', $form, 'the content label points at the textarea');
	pg_assert_contains('<label for="type"', $form, 'the region type label points at the select');
	pg_assert_false(strpos($edit, 'for="name"') !== false, 'the edit screen keeps no label of its own on the name');
	pg_assert_false(strpos($edit, 'for="type"') !== false, 'the edit screen keeps no copy of the type field');
}

function test_common_region_form_screens_use_shared_form()
{
	$screens = array(
		'add_common_region.php' => 'pg_common_region_form_cards($name, $content, array(\'name_error\' => $name_error))',
		'edit_common_region.php' => 'pg_common_region_form_cards($form_name, $form_content, array(',
	);

	foreach ($screens as $file => $card) {
		$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);

		pg_assert_contains('includes/forms/common_region_form.php', $source, $file . ' loads the form');
		pg_assert_contains($card, $source, $file . ' draws the shared cards');
		pg_assert_contains('pg_common_region_form_layout(', $source, $file . ' uses the shared layout');
		pg_assert_contains('list($name, $content) = pg_common_region_form_values($_POST);', $source, $file . ' saves the shared values');
		pg_assert_contains("validate_area_access(\$user, 'designer')", $source, $file . ' is for designers');
		pg_assert_contains('validate_token_field()', $source, $file . ' checks the token');
		pg_assert_false(strpos($source, 'Common Region Name') !== false, $file . ' keeps no copy of the card');
	}

	$edit = (string) file_get_contents(PG_FUNCTIONS_DIR . '/edit_common_region.php');

	pg_assert_contains("pg_cregion_save(0, \$name, ", (string) file_get_contents(PG_FUNCTIONS_DIR . '/add_common_region.php'), 'create writes through pg_cregion_save()');
	pg_assert_contains('pg_cregion_save($id, $name, $new_content, $cregion_designer_type, ', $edit, 'edit writes the region it checked at the top through pg_cregion_save()');
	pg_assert_contains("'with_content' => !\$component_id,", $edit, 'a converted region shows no content field');
	pg_assert_contains("'type_locked' => (bool) \$component_id,", $edit, 'a converted region keeps the common type');
	pg_assert_contains('disable_shortcut', $edit, 'the convert form keeps out of the save shortcut');
	pg_assert_contains('pg_common_region_component($id, $user, $warnings)', $edit, 'edit keeps the conversion');
}

// A region made a shared component is not deleted while the component
// exists: the delete branch refuses before the DELETE and names the
// component, and the Delete button's confirmation says so (#5516).
function test_common_region_delete_refused_while_its_component_exists()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/edit_common_region.php');
	$branch = strpos($source, "if ((\$_POST['submit_delete'] ?? '') == 'Delete') {");
	$refusal = strpos($source, "'This common region is the shared component \"{var:1}\". Delete the component first; its content comes back here, then the region can be deleted.'");

	pg_assert_true($branch !== false, 'delete branch');
	pg_assert_true($refusal !== false && $refusal > $branch, 'refusal inside the branch');
	pg_assert_true($refusal < strpos($source, 'pg_cregion_delete($id)'), 'before the DELETE');
	pg_assert_contains("lang('This region is a shared component and cannot be deleted while the component exists. Delete the component first.')", $source, 'confirmation');
}

function test_common_region_form_wide_layout_columns_and_save_bar()
{
	$layout = pg_common_region_form_layout('<div id="cards"></div>', '<button id="b"></button>');
	pg_assert_contains('<div class="row"><div id="cards"></div>', $layout, 'the cards open the row');
	pg_assert_contains('col-12 order-3 position-sticky', $layout, 'the save bar is sticky under both columns');
	pg_assert_contains('<button id="b"></button>', $layout, 'the buttons go in the bar');

	$src = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/forms/common_region_form.php');
	$form_column = strpos($src, 'col-12 lg:col-8 2xl:col-9 order-1');
	$status_column = strpos($src, 'col-12 lg:col-4 2xl:col-3 order-2');
	pg_assert_true($form_column !== false, 'the form column');
	pg_assert_true(($status_column !== false) && ($status_column > $form_column), 'the status column follows the form column in the source');
	pg_assert_contains('style="top:3.3rem;"', $src, 'the status box sticks below the top bar');

	$edit = (string) file_get_contents(PG_FUNCTIONS_DIR . '/edit_common_region.php');
	pg_assert_contains("'status' => \$output_status,", $edit, 'the edit screen adds its Summary rows');
	pg_assert_contains("lang('Last Modified')", $edit, 'when and by whom it was last saved');
	pg_assert_false(strpos($edit, "'grow'") !== false, 'the body tag left the toolbar for the Summary');
}

function test_common_region_form_type_labels_and_status_row()
{
	pg_assert_same(array('common', 'designer'), array_keys(pg_common_region_form_type_labels()), 'one label per type the edit screen offers');

	$row = pg_common_region_form_status_row('Shared Component', '<b>Footer</b>', false);
	pg_assert_contains('<dt class="fw-normal">Shared Component</dt>', $row, 'the label');
	pg_assert_contains('<b>Footer</b></dd>', $row, 'the value as given');
	pg_assert_contains('border-bottom', $row, 'a line under a row that is not the last');
	pg_assert_false(strpos(pg_common_region_form_status_row('A', 'b'), 'border-bottom') !== false, 'no line under the last row');
}
