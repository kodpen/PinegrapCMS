<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * A File Upload field in the classic cart and the classic express order
 * (includes/fn/product_form_uploads.php): what a save does with the field
 * (place a new file, refuse it, remove the kept one, keep it), which answers
 * a save clears before it writes the line's form again, the input's accept
 * list, and the kept file's row with its Remove box.
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

function pfu_cart_test_upload($name, $error = UPLOAD_ERR_OK)
{
	return array('name' => $name, 'size' => 1000, 'error' => $error, 'tmp_name' => '/tmp/x');
}

function test_product_form_uploads_cart_upload_given()
{
	pg_assert_true(pg_pfu_upload_given(pfu_cart_test_upload('mug.jpg')), 'a sent file was not seen');
	pg_assert_true(pg_pfu_upload_given(pfu_cart_test_upload('big.jpg', UPLOAD_ERR_INI_SIZE)), 'a file over the limit counts as sent, so it is refused');
	pg_assert_false(pg_pfu_upload_given(pfu_cart_test_upload('', UPLOAD_ERR_NO_FILE)), 'an empty input counted as a file');
	pg_assert_false(pg_pfu_upload_given(null), 'no $_FILES entry counted as a file');
	pg_assert_false(pg_pfu_upload_given(array('name' => array('a.jpg'), 'error' => array(0))), 'a file list counted as one file');
}

function test_product_form_uploads_cart_save_keeps_a_stored_file_when_no_new_one_comes()
{
	pg_assert_same('keep', pg_pfu_save_action(false, '', false, true), 'a save without a file dropped the stored one');
	pg_assert_same('keep', pg_pfu_save_action(false, '', false, false), 'a save with nothing at all did something');
	pg_assert_same('place', pg_pfu_save_action(true, '', false, true), 'a new file did not replace the stored one');
	pg_assert_same('place', pg_pfu_save_action(true, '', true, true), 'a new file lost to the Remove box');
	pg_assert_same('refuse', pg_pfu_save_action(true, 'Too big.', false, true), 'a refused file was placed');
	pg_assert_same('refuse', pg_pfu_save_action(true, 'Too big.', true, true), 'a refused file with Remove ticked removed the stored one');
	pg_assert_same('remove', pg_pfu_save_action(false, '', true, true), 'Remove did not remove the stored file');
	pg_assert_same('keep', pg_pfu_save_action(false, '', true, false), 'Remove with nothing stored did something');
}

function test_product_form_uploads_cart_holds_file_after_the_save()
{
	pg_assert_true(pg_pfu_holds_file('place', false), 'a placed file does not count');
	pg_assert_false(pg_pfu_holds_file('remove', true), 'a removed file still counts');
	pg_assert_true(pg_pfu_holds_file('keep', true), 'a kept file does not count');
	pg_assert_false(pg_pfu_holds_file('keep', false), 'nothing kept counts as a file');
	pg_assert_true(pg_pfu_holds_file('refuse', true), 'the stored file behind a refused one does not count');
	pg_assert_false(pg_pfu_holds_file('refuse', false), 'a refused file counts');
}

function test_product_form_uploads_cart_reset_clears_all_but_the_kept_files()
{
	$where = pg_pfu_reset_where(12, array(5, '7', 0, 'x'), 3);

	pg_assert_contains("form_data.order_item_id = '12'", $where, 'the line is not pinned');
	pg_assert_contains("form_data.order_item_id != '0'", $where, 'a submitted form answer (order_item_id 0) could match');
	pg_assert_contains("form_data.quantity_number > '3'", $where, 'a unit above the new quantity keeps its answers');
	pg_assert_contains("form_data.form_field_id NOT IN (5, 7)", $where, 'the File Upload answers are cleared too');

	// Without a File Upload field every answer of the line goes, as before.
	pg_assert_same("(form_data.order_item_id = '12') AND (form_data.order_item_id != '0')", pg_pfu_reset_where(12, array(), 3), 'a line without a file field kept answers');
	pg_assert_contains("form_data.quantity_number > '0'", pg_pfu_reset_where(12, array(5), -2), 'a negative quantity reached the SQL');
}

function test_product_form_uploads_cart_accept_attribute()
{
	pg_assert_same('.jpg,.jpeg,.png,.gif,.webp,.pdf,.ai', pg_pfu_accept_attribute(pg_pfu_rule_clean(array('images' => true, 'pdf' => true, 'extensions' => array('ai')))), 'the accept list does not match the rule');
	pg_assert_same('', pg_pfu_accept_attribute(pg_pfu_rule_clean(null)), 'an open field limited the picker');
}

function test_product_form_uploads_cart_kept_file_row()
{
	$html = pg_pfu_kept_file_html(array('id' => 9, 'name' => 'my "mug".png', 'size' => 2048), 'order_item_3_quantity_number_1_form_field_5', '/');

	pg_assert_contains('href="/my%20%22mug%22.png"', $html, 'the link does not open the file');
	pg_assert_contains('my &quot;mug&quot;.png', $html, 'the name is not escaped');
	pg_assert_contains('<img', $html, 'an image has no preview');
	pg_assert_contains('name="order_item_3_quantity_number_1_form_field_5_remove"', $html, 'the Remove box is missing');
	pg_assert_contains('2 KB', $html, 'the size is missing');

	$pdf = pg_pfu_kept_file_html(array('id' => 9, 'name' => 'proof.pdf', 'size' => 10), 'f', '/');
	pg_assert_false(mb_strpos($pdf, '<img') !== false, 'a PDF got an image preview');

	pg_assert_same('', pg_pfu_kept_file_html(null, 'f', '/'), 'no kept file drew a row');
}

function test_product_form_uploads_cart_classic_pages_use_the_file_path()
{
	$root = PG_FUNCTIONS_DIR;

	foreach (array('shopping_cart.php', 'express_order.php') as $file) {
		$code = (string) file_get_contents($root . '/' . $file);
		pg_assert_true(strpos($code, 'pg_upload_refuse_discarded_body()') !== false, $file . ' reads a discarded body as a missing token');
		pg_assert_true(strpos($code, 'pg_pfu_cart_reset(') !== false, $file . ' clears the kept files on every save');
		pg_assert_true(strpos($code, 'pg_pfu_cart_save_file(') !== false, $file . ' does not save the file');
		pg_assert_false(strpos($code, "DELETE FROM form_data WHERE (order_item_id = ?)") !== false, $file . ' still clears every answer of the line');
	}

	foreach (array('get_shopping_cart.php', 'get_express_order.php') as $file) {
		$code = (string) file_get_contents($root . '/' . $file);
		pg_assert_same(2, substr_count($code, 'enctype="multipart/form-data"'), $file . ' does not post multipart in both layouts');
	}
}
