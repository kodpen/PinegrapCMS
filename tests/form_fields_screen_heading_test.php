<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for a custom form's fields list and Edit Field: the list prints the
 * notices Create, Save and Delete leave for it and then removes its form, so
 * "The field has been saved." shows once (#9028); and both screens title
 * the form type with pg_upper_words(), which upper-cases a Turkish first
 * letter where ucwords() left "özel Form" (#9032).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_view_fields_removes_its_notices_once_printed()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/view_fields.php');
	$printed = strpos($source, "\$liveform->get_messages()");
	$removed = strrpos($source, "\$liveform->remove_form();");

	pg_assert_true($printed !== false, 'the list prints its notices');
	pg_assert_true(($removed !== false) && ($removed > $printed), 'and removes its form after printing them');
}

function test_field_screens_title_the_form_type_in_utf8()
{
	foreach (array('view_fields.php', 'edit_field.php', 'add_field.php') as $file) {
		$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);

		pg_assert_false(strpos($source, 'ucwords($form_type_name)') !== false, $file . ': no byte-wise ucwords() on the form type');
		pg_assert_contains('pg_upper_words($form_type_name)', $source, $file);
	}

	pg_assert_same('Özel Form', pg_upper_words('özel form'));
	pg_assert_same('Custom Shipping Form', pg_upper_words('custom shipping form'));
}
