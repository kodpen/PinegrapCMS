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
	pg_assert_contains('<label for="type"', $edit, 'the region type label points at the select');
	pg_assert_false(strpos($edit, 'for="name"') !== false, 'the edit screen keeps no label of its own on the name');
}

function test_common_region_form_screens_use_shared_form()
{
	$screens = array(
		'add_common_region.php' => 'pg_common_region_form_card()',
		'edit_common_region.php' => 'pg_common_region_form_card($cregion_name, $cregion_content, ',
	);

	foreach ($screens as $file => $card) {
		$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);

		pg_assert_contains('includes/forms/common_region_form.php', $source, $file . ' loads the form');
		pg_assert_contains($card, $source, $file . ' draws the shared card');
		pg_assert_contains('list($name, $content) = pg_common_region_form_values($_POST);', $source, $file . ' saves the shared values');
		pg_assert_contains("validate_area_access(\$user, 'designer')", $source, $file . ' is for designers');
		pg_assert_contains('validate_token_field()', $source, $file . ' checks the token');
		pg_assert_false(strpos($source, 'Common Region Name') !== false, $file . ' keeps no copy of the card');
	}

	$edit = (string) file_get_contents(PG_FUNCTIONS_DIR . '/edit_common_region.php');

	pg_assert_contains('INSERT INTO cregion', (string) file_get_contents(PG_FUNCTIONS_DIR . '/add_common_region.php'), 'create keeps its INSERT');
	pg_assert_contains('UPDATE cregion', $edit, 'edit keeps its UPDATE');
	pg_assert_contains('!$component_id) . \'', $edit, 'a converted region shows no content field');
	pg_assert_contains('disable_shortcut', $edit, 'the convert form keeps out of the save shortcut');
	pg_assert_contains('pg_common_region_component($id, $user, $warnings)', $edit, 'edit keeps the conversion');
}
