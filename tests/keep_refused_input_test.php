<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests that the field and calendar screens keep what was typed when a save
 * is refused: the post is stored before the redirect, the screen draws from
 * it, and the stored form is removed once drawn.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function pg_test_keep_input_source($file)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
}

function test_field_screens_keep_refused_input()
{
	foreach (array('add_field.php', 'edit_field.php') as $file) {
		$source = pg_test_keep_input_source($file);

		pg_assert_true((bool) preg_match('/check_form_errors\(\)[^{]*\{\s*\$liveform->add_fields_to_session\(\);\s*header\(/', $source), $file . ' stores the post before sending the form back');
		pg_assert_contains('pg_field_form_session_values($liveform, $field)', $source, $file . ' reads the sent-back post');
		pg_assert_contains('pg_field_form_posted_field($field, $posted)', $source, $file . ' draws the cards from it');
		pg_assert_false(strpos($source, '$liveform->unmark_errors();') !== false, $file . ' does not leave an error entry behind');
		pg_assert_true(substr_count($source, '$liveform->remove_form();') >= 1, $file . ' removes the form once drawn');
	}
}

function test_edit_calendar_keeps_refused_name()
{
	$source = pg_test_keep_input_source('edit_calendar.php');

	pg_assert_true((bool) preg_match('/if \(\$liveform->prefill\(\)\) \{\s*\$liveform->assign_field_value\(\'name\', \$row\[\'name\'\]\);/', $source), 'the stored name fills the box only when no post came back');
}
