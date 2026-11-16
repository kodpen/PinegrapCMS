<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the return address after a campaign gift (pending offer) is
 * added from the designer cart. add_pending_offers() ends with its own
 * redirect to the page named by the posted page_id, which the classic
 * Shopping Cart and Express Order pages post. The designer cart's gift form
 * posts to cart_action.php without a page_id, so that redirect sent the
 * visitor to the site root and cart_action.php never reached its send_to
 * redirect or its offer refresh.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The function redirects only when asked to; the classic pages keep asking.
function test_add_pending_offers_redirect_is_optional()
{
	$source = file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/ecommerce.php');

	pg_assert_contains('function add_pending_offers($liveform, $redirect = true)', $source, 'redirect parameter, on by default');

	$start = strpos($source, 'function add_pending_offers(');
	$end   = strpos($source, "\nfunction ", $start + 1);
	$body  = substr($source, $start, $end - $start);

	pg_assert_contains("if (\$redirect) {\n", $body, 'redirect guarded');
	$guard  = strpos($body, 'if ($redirect) {');
	$header = strpos($body, "header('Location: ' . URL_SCHEME . HOSTNAME . PATH . get_page_name(\$_POST['page_id']));");
	pg_assert_true(($header !== false) && ($header > $guard), 'page_id redirect inside the guard');

	foreach (array('shopping_cart.php', 'express_order.php') as $file) {
		$page = file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
		pg_assert_contains('add_pending_offers($liveform);', $page, $file . ': classic page still redirects');
	}
}

// The designer cart's handler keeps control and redirects to send_to.
function test_cart_action_adds_pending_offer_without_redirect()
{
	$source = file_get_contents(PG_FUNCTIONS_DIR . '/cart_action.php');

	pg_assert_contains('add_pending_offers($lf, false);', $source, 'cart_action.php asks for no redirect');
	pg_assert_true(strpos($source, 'add_pending_offers($lf);') === false, 'no redirecting call left');
}
