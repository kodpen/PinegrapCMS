<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the name check of the common, designer and dynamic region
 * screens: a name another region of the same table already has is refused
 * (create always, edit only on a rename), and the screens draw the name
 * field again with its error.
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
require_once(PG_FUNCTIONS_DIR . '/includes/forms/designer_region_form.php');
require_once(PG_FUNCTIONS_DIR . '/includes/forms/dynamic_region_form.php');

/**
 * A lookup stub: records each query and answers from $answers in order.
 */
function region_name_taken_test_lookup(array &$calls, array $answers)
{
	return function ($sql, $values) use (&$calls, &$answers) {
		$calls[] = array($sql, $values);

		return array_shift($answers);
	};
}

function test_region_name_taken_skips_an_empty_name()
{
	$calls = array();
	$lookup = region_name_taken_test_lookup($calls, array('1'));

	pg_assert_false(pg_cregion_name_taken('', null, $lookup), 'empty cregion name');
	pg_assert_false(pg_dregion_name_taken('', 4, $lookup), 'empty dregion name');
	pg_assert_same(array(), $calls, 'no query for an empty name');
}

function test_region_name_taken_create_always_checks()
{
	$calls = array();
	pg_assert_true(pg_cregion_name_taken('footer', null, region_name_taken_test_lookup($calls, array('1'))), 'a used cregion name is taken');
	pg_assert_same(array(array('SELECT COUNT(*) FROM cregion WHERE cregion_name = ?', array('footer'))), $calls, 'one bound cregion query');

	$calls = array();
	pg_assert_false(pg_cregion_name_taken('footer', null, region_name_taken_test_lookup($calls, array('0'))), 'a free cregion name');

	$calls = array();
	pg_assert_true(pg_dregion_name_taken('news', null, region_name_taken_test_lookup($calls, array('2'))), 'a used dregion name is taken');
	pg_assert_same(array(array('SELECT COUNT(*) FROM dregion WHERE dregion_name = ?', array('news'))), $calls, 'one bound dregion query');
}

function test_region_name_taken_edit_checks_only_a_rename()
{
	$calls = array();
	pg_assert_false(pg_cregion_name_taken('footer', 7, region_name_taken_test_lookup($calls, array('footer', '1'))), 'unchanged cregion name saves even when shared');
	pg_assert_same(array(array('SELECT cregion_name FROM cregion WHERE cregion_id = ?', array(7))), $calls, 'only the stored name is read');

	$calls = array();
	pg_assert_true(pg_cregion_name_taken('header', 7, region_name_taken_test_lookup($calls, array('footer', '1'))), 'a rename onto a used name is taken');
	pg_assert_same(array('SELECT COUNT(*) FROM cregion WHERE cregion_name = ? AND cregion_id != ?', array('header', 7)), $calls[1], 'its own row is left out');

	$calls = array();
	pg_assert_false(pg_dregion_name_taken('news', '3', region_name_taken_test_lookup($calls, array('news'))), 'unchanged dregion name');

	$calls = array();
	pg_assert_false(pg_dregion_name_taken('blog', 3, region_name_taken_test_lookup($calls, array('news', '0'))), 'a rename onto a free name');
	pg_assert_same(array('SELECT COUNT(*) FROM dregion WHERE dregion_name = ? AND dregion_id != ?', array('blog', 3)), $calls[1], 'its own dregion row is left out');
}

function test_region_name_taken_cards_mark_the_name()
{
	$message = 'The name that you entered is already in use, so please enter a different name.';

	foreach (array('common_region_form.php', 'designer_region_form.php', 'dynamic_region_form.php') as $file) {
		$form = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/forms/' . $file);

		pg_assert_contains('pg_region_form_name_error_attributes($name_error)', $form, $file . ' marks the name input');
		pg_assert_contains('pg_region_form_name_error($name_error)', $form, $file . ' prints the message under the name');
	}

	pg_assert_same('', pg_region_form_name_error_attributes(''), 'no attributes without an error');
	pg_assert_same('', pg_region_form_name_error(''), 'no message without an error');
	pg_assert_same(' is-invalid" aria-invalid="true" aria-describedby="name_error', pg_region_form_name_error_attributes($message), 'invalid input described by the message');
	pg_assert_contains('id="name_error" class="invalid-feedback d-block"', pg_region_form_name_error($message), 'message box');
	pg_assert_contains('Name is &lt;required&gt;', pg_region_form_name_error('Name is <required>'), 'the message is escaped');
	pg_assert_contains('lang(\'' . $message . '\')', (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/forms/common_region_form.php'), 'the existing translated message');
}

function test_region_name_taken_screens_refuse_and_redraw()
{
	$message = "\$liveform->mark_error('name', \$name_error);";
	$screens = array(
		'add_common_region.php' => array('pg_cregion_name_taken($name)', 'pg_common_region_form_cards($name, $content, array(\'name_error\' => $name_error))'),
		'edit_common_region.php' => array('pg_cregion_name_taken($name, $id)', "'name_error' => \$name_error,"),
		'add_designer_region.php' => array('pg_cregion_name_taken($name)', 'pg_designer_region_form_card($name, $content, \'\', $name_error)'),
		'edit_designer_region.php' => array('pg_cregion_name_taken($name, $id)', 'pg_designer_region_form_card($form_name, $form_content, '),
		'add_dynamic_region.php' => array('pg_dregion_name_taken($name)', 'pg_dynamic_region_form_cards($name, $code, array(\'name_error\' => $name_error))'),
		'edit_dynamic_region.php' => array('pg_dregion_name_taken($name, $id)', "'name_error' => \$name_error, 'status'"),
	);

	foreach ($screens as $file => $expected) {
		$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);

		pg_assert_contains('$name_error = pg_region_form_name_refusal($name, ' . $expected[0] . ');', $source, $file . ' checks the name');
		pg_assert_contains('$name_error = pg_region_form_name_error_text($liveform);', $source, $file . ' reads the refusal back');
		pg_assert_contains($message, $source, $file . ' refuses with the existing message');
		pg_assert_contains("add_fields_to_session(array('trim' => false))", $source, $file . ' keeps the posted content as typed');
		pg_assert_contains($expected[1], $source, $file . ' draws the posted values again');
		pg_assert_contains('$liveform->output_errors()', $source, $file . ' shows the error');
		pg_assert_contains('$liveform->remove_form()', $source, $file . ' clears it once drawn');
	}
}

function test_region_name_refusal()
{
	pg_assert_same('Name is required.', pg_region_form_name_refusal('', false), 'a blank name is refused');
	pg_assert_same('', pg_region_form_name_refusal('footer', false), 'a free name');
	pg_assert_same('The name that you entered is already in use, so please enter a different name.', pg_region_form_name_refusal('footer', true), 'a used name');
	pg_assert_same('', pg_region_form_name_refusal(str_repeat('ş', 100), false), '100 characters fit the column');
	pg_assert_same('The name can be at most 100 characters long.', pg_region_form_name_refusal(str_repeat('ş', 101), false), 'a longer name would be cut by the column');
	pg_assert_same('The name can be at most 100 characters long.', pg_region_form_name_refusal(str_repeat('ş', 101), true), 'too long is said before used');

	foreach (array('common_region_form.php', 'designer_region_form.php', 'dynamic_region_form.php') as $file) {
		pg_assert_contains('maxlength="100" required="required"', (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/forms/' . $file), $file . ' marks the name required');
	}
}

function test_region_duplicate_name_fits_the_column()
{
	pg_assert_same(100, pg_unique_name_limit('common_region'), 'cregion.cregion_name is varchar(100)');
	pg_assert_same(100, pg_unique_name_limit('designer_region'), 'designer regions share the cregion table');

	$long = 'pg-test uzun ' . str_repeat('ş', 87);
	$copy = pg_bracket_suffix_name($long, pg_unique_name_limit('common_region'));
	pg_assert_same(100, mb_strlen($copy, 'UTF-8'), 'the copy fits the column');
	pg_assert_same('[1]', mb_substr($copy, -3, null, 'UTF-8'), 'the copy keeps its suffix');
	pg_assert_true($copy !== $long, 'the copy differs from its source');
}
