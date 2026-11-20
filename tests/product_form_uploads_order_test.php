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

function test_product_form_uploads_order_reader_shows_the_file()
{
	// No file: the caller prints the answer's own text.
	pg_assert_same('', pg_pfu_order_file_html(null, '/', 'link'), 'an answer without a file drew a file');
	pg_assert_same('', pg_pfu_order_file_html('', '/', 'thumbnail'), 'an empty file name drew a file');

	// A link get_file.php serves, its name escaped in the address and the text.
	$link = pg_pfu_order_file_html('my "photo" <1>.pdf', '/shop/', 'link');
	pg_assert_same(
		'<a href="/shop/my%20%22photo%22%20%3C1%3E.pdf" target="_blank" rel="noopener">my &quot;photo&quot; &lt;1&gt;.pdf</a>',
		$link,
		'the file link was not drawn or not escaped');

	// The panel's thumbnail comes with an image only.
	$image = pg_pfu_order_file_html('mug.JPG', '/', 'thumbnail');
	pg_assert_true(strpos($image, '<img src="/mug.JPG"') !== false, 'an image got no thumbnail in the panel');
	pg_assert_true(strpos($image, '>mug.JPG</a>') !== false, 'the thumbnail lost the file name link');
	pg_assert_true(strpos(pg_pfu_order_file_html('print.pdf', '/', 'thumbnail'), '<img') === false, 'a PDF got a thumbnail');
	pg_assert_true(strpos(pg_pfu_order_file_html('mug.jpg', '/', 'link'), '<img') === false, 'a link-only reader got a thumbnail');

	// An e-mail names the file and carries no address.
	pg_assert_same('mug &lt;1&gt;.jpg', pg_pfu_order_file_html('mug <1>.jpg', '/', 'name'), 'the e-mail did not name the file alone');

	// A name with a folder part is cut to the file's own name.
	pg_assert_same('evil.jpg', pg_pfu_order_file_html('../x/evil.jpg', '/', 'name'), 'a path in the name was printed');
}

function test_product_form_uploads_order_reader_mode()
{
	pg_assert_same('name', pg_pfu_order_file_mode('customer', 'backend'), 'a customer e-mail carried a link');
	pg_assert_same('name', pg_pfu_order_file_mode('store', 'link'), 'the store copy carried a link');
	pg_assert_same('thumbnail', pg_pfu_order_file_mode('screen', 'backend'), 'the panel lost its thumbnail');
	pg_assert_same('link', pg_pfu_order_file_mode('screen', 'print'), 'a print showed a thumbnail');
	pg_assert_same('link', pg_pfu_order_file_mode('screen', 'frontend'), 'a site page did not link the file');
}
