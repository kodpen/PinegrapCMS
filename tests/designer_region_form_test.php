<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the designer region form shared by the create and the edit
 * screens (includes/forms/designer_region_form.php): the values a save
 * writes, the labels of the card, and that both screens use the shared
 * form.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/forms/designer_region_form.php');

function test_designer_region_form_values_trim_the_name_only()
{
	pg_assert_same(
		array('header', "  <p>Hi</p>\n"),
		pg_designer_region_form_values(array('name' => "  header \n", 'content' => "  <p>Hi</p>\n")),
		'name trimmed, content kept as posted');
	pg_assert_same(array('', ''), pg_designer_region_form_values(array()), 'missing fields save empty');
	pg_assert_same(array('', ''), pg_designer_region_form_values(array('name' => array('x'), 'content' => array('y'))), 'arrays save empty');
}

function test_designer_region_form_labels_point_at_their_fields()
{
	$form = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/forms/designer_region_form.php');
	$edit = (string) file_get_contents(PG_FUNCTIONS_DIR . '/edit_designer_region.php');

	pg_assert_same(1, substr_count($form, 'for="name"'), 'only the name label points at the name');
	pg_assert_contains('<label for="code"', $form, 'the code snippet label points at the textarea');
	pg_assert_contains('<label for="type"', $edit, 'the region type label points at the select');
	pg_assert_false(strpos($edit, 'for="name"') !== false, 'the edit screen keeps no label of its own on the name');
}

function test_designer_region_form_screens_use_shared_form()
{
	$screens = array(
		'add_designer_region.php' => 'pg_designer_region_form_card($name, $content, \'\', $name_taken)',
		'edit_designer_region.php' => 'pg_designer_region_form_card($form_name, $form_content, ',
	);

	foreach ($screens as $file => $card) {
		$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);

		pg_assert_contains('includes/forms/designer_region_form.php', $source, $file . ' loads the form');
		pg_assert_contains($card, $source, $file . ' draws the shared card');
		pg_assert_contains('list($name, $content) = pg_designer_region_form_values($_POST);', $source, $file . ' saves the shared values');
		pg_assert_contains("validate_area_access(\$user, 'designer')", $source, $file . ' is for designers');
		pg_assert_contains('validate_token_field()', $source, $file . ' checks the token');
		pg_assert_false(strpos($source, 'HTML Code Snippet') !== false, $file . ' keeps no copy of the card');
	}

	pg_assert_contains('INSERT INTO cregion', (string) file_get_contents(PG_FUNCTIONS_DIR . '/add_designer_region.php'), 'create keeps its INSERT');
	pg_assert_contains('UPDATE cregion', (string) file_get_contents(PG_FUNCTIONS_DIR . '/edit_designer_region.php'), 'edit keeps its UPDATE');
}
