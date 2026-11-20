<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * A product form answer's file after the order (includes/fn/product_form_uploads.php):
 * the orders CSV shows the file's name in the answer's column, and a product
 * that creates a submitted form at completion finds the file its value names
 * in the unit's answers.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/product_form_uploads.php');

function test_product_form_uploads_csv_cell_is_the_file_name()
{
	pg_assert_same('mug-photo1.jpg', pg_pfu_answer_text('mug-photo.jpg', 'mug-photo1.jpg'), 'the stored file name did not win over the answer text');
	pg_assert_same('Red', pg_pfu_answer_text('Red', null), 'an answer without a file lost its text');
	pg_assert_same('Red', pg_pfu_answer_text('Red', ''), 'an empty file name replaced the answer text');
	// The file was deleted from Files since: the name the answer kept stays.
	pg_assert_same('old.pdf', pg_pfu_answer_text('old.pdf', null), 'a deleted file emptied the cell');
}

function test_product_form_uploads_submit_form_value_names_one_field()
{
	pg_assert_same('photo', pg_pfu_submit_form_source_name('^^photo^^'), 'a plain variable was not read');
	pg_assert_same('photo', pg_pfu_submit_form_source_name('  ^^photo^^ '), 'spaces around the variable broke it');
	pg_assert_same('', pg_pfu_submit_form_source_name('photo'), 'a value with no variable named a field');
	pg_assert_same('', pg_pfu_submit_form_source_name('^^photo^^ ^^back^^'), 'two variables named one field');
	pg_assert_same('', pg_pfu_submit_form_source_name('see ^^photo^^'), 'text around the variable named a field');
	pg_assert_same('', pg_pfu_submit_form_source_name(''), 'an empty value named a field');
}

function test_product_form_uploads_submit_form_file_is_the_units_file()
{
	$answers = array(
		array('name' => 'colour', 'file_id' => '0'),
		array('name' => 'photo', 'file_id' => '42'),
		array('name' => 'back', 'file_id' => '43'),
	);

	pg_assert_same(42, pg_pfu_submit_form_file_id('^^photo^^', $answers), 'the named field\'s file was not picked');
	pg_assert_same(43, pg_pfu_submit_form_file_id('^^BACK^^', $answers), 'a variable in other case was not matched');
	pg_assert_same(0, pg_pfu_submit_form_file_id('^^colour^^', $answers), 'an answer without a file gave a file');
	pg_assert_same(0, pg_pfu_submit_form_file_id('^^missing^^', $answers), 'a field the unit lacks gave a file');
	pg_assert_same(0, pg_pfu_submit_form_file_id('photo', $answers), 'a value with no variable gave a file');
	pg_assert_same(0, pg_pfu_submit_form_file_id('^^photo^^', array()), 'a unit with no answers gave a file');
}
