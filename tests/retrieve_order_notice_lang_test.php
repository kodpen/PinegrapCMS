<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the notice the cart and express order pages show after an order
 * is retrieved by its reference code (retrieve_order.php): it goes through
 * lang() like the error messages beside it, so a Turkish site does not show
 * "The cart was retrieved." in English.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_retrieve_order_success_notice_is_translated()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/retrieve_order.php');

	pg_assert_contains("return success_response(lang(array('string' => 'The {var:1} was retrieved.', 'vars' => array(\$order_label))));", $source, 'the success notice goes through lang()');
	pg_assert_same(false, strpos($source, "success_response('The '"), 'no untranslated success notice is left');

	$tr = json_decode((string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/local/tr/t.json'), true);

	pg_assert_true(isset($tr['The {var:1} was retrieved.']), 'the notice has a Turkish translation');
}
