<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The PayPal Express Checkout return (submit_order.php, mode
 * paypal_express_checkout_return) reads the token and PayerID of the query
 * string as one string each. They were passed to urlencode() as they came:
 * token[]= made urlencode() throw a TypeError on PHP 8 (a blank error page
 * instead of the PayPal refusal), and a missing one passed null (deprecated
 * from PHP 8.1).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_submit_order_paypal_return_reads_strings()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/submit_order.php');

	foreach (array('token', 'PayerID') as $name) {
		pg_assert_same(false, strpos($source, "urlencode(\$_GET['" . $name . "'])"), $name . ' passed to urlencode() as it came');
		pg_assert_contains("(isset(\$_GET['" . $name . "']) && is_string(\$_GET['" . $name . "'])) ? \$_GET['" . $name . "'] : ''", $source, $name . ' read as one string');
	}
}
