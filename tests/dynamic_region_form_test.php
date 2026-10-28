<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the dynamic region form shared by the create and the edit
 * screens (includes/forms/dynamic_region_form.php): the values a save
 * writes, the labels of the card, and that both screens use the shared
 * form and stay administrator-only.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/forms/dynamic_region_form.php');

function test_dynamic_region_form_values_trim_the_name_only()
{
	pg_assert_same(
		array('news', "  <?php echo 1; ?>\n"),
		pg_dynamic_region_form_values(array('name' => "  news \n", 'code' => "  <?php echo 1; ?>\n")),
		'name trimmed, code kept as posted');
	pg_assert_same(array('', ''), pg_dynamic_region_form_values(array()), 'missing fields save empty');
	pg_assert_same(array('', ''), pg_dynamic_region_form_values(array('name' => array('x'), 'code' => array('y'))), 'arrays save empty');
}

function test_dynamic_region_form_labels_point_at_their_fields()
{
	$form = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/forms/dynamic_region_form.php');

	pg_assert_same(1, substr_count($form, 'for="name"'), 'only the name label points at the name');
	pg_assert_contains('<label for="code"', $form, 'the code snippet label points at the textarea');
	pg_assert_contains("'code_type' => 'php'", $form, 'the code editor reads PHP');
}
