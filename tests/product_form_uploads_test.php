<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * A File Upload field on a product form (includes/fn/product_form_uploads.php):
 * the field's allow-list only narrows the upload blocklist, the size cap never
 * goes above the server's, who opens a product answer's file (the cart's
 * owner, the buyer, order staff; not a stranger), and a removed cart line,
 * unit or order takes its files with it.
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

function pfu_test_upload($name, $size = 1000, $error = UPLOAD_ERR_OK)
{
	return array('name' => $name, 'size' => $size, 'error' => $error, 'tmp_name' => '/tmp/x');
}

function test_product_form_uploads_allow_list_sits_on_top_of_the_blocklist()
{
	$rule = pg_pfu_rule_clean(array('upload' => array('images' => true, 'extensions' => array('php', '.AI', 'phtml', 'psd', 'ai'))));

	pg_assert_same(array('ai', 'psd'), $rule['extensions'], 'a blocked extension stayed in the allow-list');

	pg_assert_same('', pg_pfu_file_refusal(pfu_test_upload('mug.JPG'), $rule, 0), 'an image was refused');
	pg_assert_same('', pg_pfu_file_refusal(pfu_test_upload('print.ai'), $rule, 0), 'a typed extension was refused');
	pg_assert_true(pg_pfu_file_refusal(pfu_test_upload('menu.pdf'), $rule, 0) !== '', 'a type the field does not take was let through');
	pg_assert_true(pg_pfu_file_refusal(pfu_test_upload('shell.php'), $rule, 0) !== '', 'a blocked file was let through');
	pg_assert_true(pg_pfu_file_refusal(pfu_test_upload('photo.php.jpg'), $rule, 0) !== '', 'a blocked inner extension was let through');

	// No type chosen: whatever the blocklist lets through, never what it refuses.
	$open = pg_pfu_rule_clean(null);
	pg_assert_same('', pg_pfu_file_refusal(pfu_test_upload('letter.docx'), $open, 0), 'an open field refused a plain file');
	pg_assert_true(pg_pfu_file_refusal(pfu_test_upload('run.exe'), $open, 0) !== '', 'an open field took a blocked file');
	pg_assert_true(pg_pfu_file_refusal(pfu_test_upload('.htaccess'), $open, 0) !== '', 'an open field took a dotfile');
}

function test_product_form_uploads_field_editor_refuses_a_blocked_extension()
{
	$posted = pg_pfu_rule_from_post(array('upload_images' => '1', 'upload_extensions' => 'ai, php .psd', 'upload_max_mb' => ''), 0);

	pg_assert_true(isset($posted['errors']['upload_extensions']), 'a blocked extension was saved silently');
	pg_assert_contains('php', $posted['errors']['upload_extensions'], 'the refusal does not name the extension');
	pg_assert_same(array('ai', 'psd'), $posted['rule']['extensions'], 'the rule kept a blocked extension');

	$json = pg_pfu_rules_json($posted['rule'], '{"min_date":"today"}');
	$back = json_decode($json, true);
	pg_assert_same('today', $back['min_date'], 'the other rules were lost');
	pg_assert_same($posted['rule'], pg_pfu_rule_clean($json), 'the stored rule does not read back');
}

function test_product_form_uploads_size_cap()
{
	$mb = 1024 * 1024;
	$rule = pg_pfu_rule_clean(array('upload' => array('pdf' => true, 'max_mb' => 5)));

	pg_assert_same(5 * $mb, pg_pfu_size_cap($rule, 64 * $mb), 'the field limit was not used');
	pg_assert_same(2 * $mb, pg_pfu_size_cap($rule, 2 * $mb), 'the cap went above the server limit');
	pg_assert_same(8 * $mb, pg_pfu_size_cap(pg_pfu_rule_clean(null), 8 * $mb), 'no field limit is not the server limit');

	pg_assert_same('', pg_pfu_file_refusal(pfu_test_upload('a.pdf', 5 * $mb), $rule, 64 * $mb), 'a file at the cap was refused');
	pg_assert_true(pg_pfu_file_refusal(pfu_test_upload('a.pdf', 5 * $mb + 1), $rule, 64 * $mb) !== '', 'a file over the cap was taken');
	pg_assert_true(pg_pfu_file_refusal(pfu_test_upload('a.pdf', 0, UPLOAD_ERR_INI_SIZE), $rule, 64 * $mb) !== '', 'an upload error was taken');
	pg_assert_same('', pg_pfu_file_refusal(pfu_test_upload('', 0, UPLOAD_ERR_NO_FILE), $rule, 64 * $mb), 'no file chosen is a refusal');

	$over = pg_pfu_rule_from_post(array('upload_pdf' => '1', 'upload_max_mb' => '100'), 64 * $mb);
	pg_assert_true(isset($over['errors']['upload_max_mb']), 'a size above the server limit was saved');
	$fine = pg_pfu_rule_from_post(array('upload_pdf' => '1', 'upload_max_mb' => '64'), 64 * $mb);
	pg_assert_same(array(), $fine['errors'], 'the server limit itself was refused');
	pg_assert_same(64, $fine['rule']['max_mb'], 'the largest size was not kept');
	$bad = pg_pfu_rule_from_post(array('upload_max_mb' => '2.5'), 64 * $mb);
	pg_assert_true(isset($bad['errors']['upload_max_mb']), 'a decimal size was saved');
}

function test_product_form_uploads_who_opens_the_file()
{
	$lines = array(array('order_id' => '40', 'order_user_id' => '7', 'order_status' => 'complete'));

	pg_assert_true(pg_pfu_access_verdict($lines, array('cart_order_id' => 40)), 'the cart owner was refused');
	pg_assert_true(pg_pfu_access_verdict($lines, array('user_id' => 7, 'role' => 3)), 'the buyer was refused');
	pg_assert_true(pg_pfu_access_verdict($lines, array('user_id' => 9, 'role' => 3, 'manage_ecommerce' => 1)), 'order staff was refused');
	pg_assert_true(pg_pfu_access_verdict($lines, array('user_id' => 9, 'role' => 2)), 'a manager was refused');

	pg_assert_false(pg_pfu_access_verdict($lines, array()), 'a stranger opened the file');
	pg_assert_false(pg_pfu_access_verdict($lines, array('cart_order_id' => 41)), 'another cart opened the file');
	pg_assert_false(pg_pfu_access_verdict($lines, array('user_id' => 9, 'role' => 3)), 'another member opened the file');
	pg_assert_false(pg_pfu_access_verdict(array(array('order_id' => '0', 'order_user_id' => '0')), array('cart_order_id' => 0)), 'an empty cart matched a line of no order');
	pg_assert_false(pg_pfu_access_verdict($lines, array('user_id' => 0, 'role' => 0)), 'a role without a user opened the file');
}

function test_product_form_uploads_removed_line_takes_its_file()
{
	$rows = array(
		array('file_id' => '11', 'name' => 'mug.jpg'),
		array('file_id' => '11', 'name' => 'mug.jpg'),
		array('file_id' => '12', 'name' => 'shared.pdf'),
		array('file_id' => '0', 'name' => ''),
	);

	pg_assert_same(array(11 => 'mug.jpg'), pg_pfu_files_to_remove($rows, array('12')), 'a removed line did not take its file, or took a file still held');
	pg_assert_same(array(), pg_pfu_files_to_remove($rows, array(), array(11, 12)), 'a kept file was removed');

	// The file directory sits inside $parent; a name may not reach out of it.
	$parent = sys_get_temp_dir() . '/pg_pfu_' . getmypid() . '_' . mt_rand();
	$dir = $parent . '/files';
	mkdir($dir, 0777, true);
	file_put_contents($dir . '/mug.jpg', 'x');
	file_put_contents($parent . '/other.jpg', 'y');

	pg_assert_same(1, pg_pfu_unlink_files(array('mug.jpg', '../other.jpg', 'missing.jpg', '..'), $dir), 'the file count is wrong');
	pg_assert_false(is_file($dir . '/mug.jpg'), 'the line file stayed on disk');
	pg_assert_true(is_file($parent . '/other.jpg'), 'a file outside the directory was deleted');

	@unlink($parent . '/other.jpg');
	@rmdir($dir);
	@rmdir($parent);
}

function test_product_form_uploads_every_removal_path_calls_the_one_function()
{
	$root = PG_FUNCTIONS_DIR;
	$ecommerce = (string) file_get_contents($root . '/includes/fn/ecommerce.php');

	foreach (array('remove_order_item' => 'pg_pfu_remove_line_files(', 'pg_order_drop_saved_lines' => 'pg_pfu_remove_line_files(') as $function => $call) {
		$start = strpos($ecommerce, 'function ' . $function . '(');
		pg_assert_true($start !== false, $function . ' is missing');
		$body = substr($ecommerce, $start, 2500);
		$call_at = strpos($body, $call);
		$delete_at = strpos($body, 'DELETE FROM form_data');
		pg_assert_true(($call_at !== false) && ($call_at < $delete_at), $function . ' deletes the answers without their files');
	}

	$remove = (string) file_get_contents($root . '/remove_item_from_cart.php');
	pg_assert_true(strpos($remove, 'pg_pfu_remove_line_files(') < strpos($remove, 'DELETE FROM form_data WHERE (order_item_id'), 'remove_item_from_cart.php deletes the answers without their files');
	pg_assert_true(strpos($remove, 'pg_pfu_remove_line_files(') !== false, 'remove_item_from_cart.php does not call the function');

	$delete = (string) file_get_contents($root . '/delete_order.php');
	pg_assert_true(strpos($delete, 'pg_pfu_remove_order_files(') !== false, 'delete_order.php does not call the function');
	pg_assert_true(strpos($delete, 'pg_pfu_remove_order_files(') < strpos($delete, 'DELETE FROM form_data'), 'delete_order.php deletes the answers without their files');
}

function test_product_form_uploads_field_type_offered_on_product_forms()
{
	require_once(PG_FUNCTIONS_DIR . '/includes/forms/field_form.php');

	foreach (array('product', 'product_group', 'custom') as $form_type) {
		pg_assert_true(in_array('file upload', pg_field_form_types($form_type, true), true), 'File Upload is not offered on a ' . $form_type . ' form');
	}

	pg_assert_false(in_array('file upload', pg_field_form_types('shipping', true), true), 'File Upload is offered on a shipping form');
	pg_assert_false(in_array('signature', pg_field_form_types('product', true), true), 'Signature is offered on a product form');
	pg_assert_contains('value="file upload"', select_field_type('', 'product'), 'the type list of a product form has no File Upload');
	pg_assert_contains('value="file upload"', select_field_type('', 'product_group'), 'the type list of a variant set has no File Upload');
}
