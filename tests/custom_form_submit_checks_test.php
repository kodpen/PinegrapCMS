<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Server checks of a custom form's submit (custom_form.php) that the browser
 * also makes: a field's maximum length (pg_cf_value_too_long()), and the
 * upload fields a visitor's post may not fill because the form did not draw
 * them for that visitor (pg_cf_undrawn_upload_ids()).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_custom_form_value_too_long()
{
	pg_assert_same(false, pg_cf_value_too_long('abcde', 5), 'a value at the limit passes');
	pg_assert_same(true, pg_cf_value_too_long('abcdef', 5), 'one character over is refused');
	pg_assert_same(false, pg_cf_value_too_long(str_repeat('x', 5000), 0), 'no limit set');
	pg_assert_same(false, pg_cf_value_too_long('çğışöü', 6), 'letters are counted, not bytes');
	pg_assert_same(false, pg_cf_value_too_long("ab\r\ncd", 5), 'a line break counts once, as the browser counts it');
	pg_assert_same(true, pg_cf_value_too_long("ab\r\ncde", 5), 'a line break still counts');
	pg_assert_same(false, pg_cf_value_too_long(array('aaaaaaaa'), 3), 'a list of choices is checked against its options instead');
}

function test_custom_form_undrawn_upload_ids()
{
	$fields = array(
		0 => array('id' => '11', 'type' => 'file upload', 'office_use_only' => '0'),
		1 => array('id' => '12', 'type' => 'file upload', 'office_use_only' => '1'),
		2 => array('id' => '13', 'type' => 'text box', 'office_use_only' => '1'),
		3 => array('id' => '14', 'type' => 'file upload', 'office_use_only' => '0'),
	);

	pg_assert_same(array('12'), pg_cf_undrawn_upload_ids($fields, false, array()),
		'an office use only upload is not drawn for a visitor');
	pg_assert_same(array(), pg_cf_undrawn_upload_ids($fields, true, array()),
		'staff in edit mode see office use only fields');
	pg_assert_same(array('12', '14'), pg_cf_undrawn_upload_ids($fields, false, array(3 => true)),
		'a field the form dropped is not drawn either');
}
