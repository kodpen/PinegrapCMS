<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the reference code in a cart or express order link (?r= or the
 * older ?reference_code=) and for the order history screens' messages. The
 * cart, quote and express order pages used to read $_GET['r'] without
 * checking it, so a link with only ?reference_code= wrote two "Undefined
 * array key" warnings, and ?r[]=x or ?reference_code[]=x stopped the page
 * with an error (an array reached mysqli_real_escape_string()). A list now
 * counts as not given. The order history screens printed "Query failed."
 * and their reorder and delete notices in English on every site.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/retrieve_order.php');

// ?r= comes first; an empty value or a list is skipped for the other one.
function test_order_reference_code_reads_one_value()
{
	$saved = $_GET;

	$cases = array(
		array(array(), ''),
		array(array('r' => 'ABCDEFGHIJ'), 'ABCDEFGHIJ'),
		array(array('reference_code' => 'ABCDEFGHIJ'), 'ABCDEFGHIJ'),
		array(array('r' => 'ABCDEFGHIJ', 'reference_code' => 'KLMNOPQRST'), 'ABCDEFGHIJ'),
		array(array('r' => '', 'reference_code' => 'KLMNOPQRST'), 'KLMNOPQRST'),
		array(array('r' => array('x')), ''),
		array(array('reference_code' => array('x')), ''),
		array(array('r' => array('x'), 'reference_code' => 'KLMNOPQRST'), 'KLMNOPQRST'),
		array(array('r' => 'ABCDEFGHIJ', 'reference_code' => array('x')), 'ABCDEFGHIJ'),
	);

	foreach ($cases as $index => $case) {
		$_GET = $case[0];
		pg_assert_same($case[1], retrieve_order_reference_code(), 'case ' . $index);
	}

	$_GET = $saved;
}

// The cart and express order pages ask the same function before they call
// retrieve_order(), so a list does not reach it.
function test_order_reference_code_callers_use_the_reader()
{
	foreach (array('get_shopping_cart.php', 'get_express_order.php') as $file) {
		$source = file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);

		pg_assert_contains("if (retrieve_order_reference_code() != '') {", $source, $file);
		pg_assert_false(strpos($source, "!empty(\$_GET['reference_code'])") !== false, $file . ': bare reference_code check');
	}
}

// The designer cart and express order widgets restore a saved cart from ?r=;
// a list returns before the query (it used to warn and look up "Array").
function test_order_reference_code_widget_skips_a_list()
{
	$saved = $_GET;

	$_GET = array('r' => array('x'));
	pg_assert_same(null, _eo_restore_cart_from_reference_code(), 'list');

	$_GET = array('r' => 'not a code');
	pg_assert_same(null, _eo_restore_cart_from_reference_code(), 'not alphanumeric');

	$_GET = $saved;
}

// Every message the order history screens print goes through lang().
function test_order_reference_code_history_messages_are_translated()
{
	$files = array('order_history_retrieve_order.php', 'order_history_reorder.php', 'order_history_delete_order.php');

	foreach ($files as $file) {
		$source = file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);

		pg_assert_false(strpos($source, "output_error('") !== false, $file . ': untranslated error');
		pg_assert_false(strpos($source, "add_notice('") !== false, $file . ': untranslated notice');
		pg_assert_false(strpos($source, "_notice = ' Your") !== false, $file . ': untranslated switched-order notice');
	}

	$language = json_decode(file_get_contents(PG_FUNCTIONS_DIR . '/includes/local/tr.json'), true);

	foreach (array('Query failed.', 'A new order has been created, and it appears below.', 'A new order has been created.', 'The order has been deleted.', 'Your previous order has been saved and may be retrieved when needed.') as $key) {
		pg_assert_true(isset($language[$key]), $key);
	}
}
