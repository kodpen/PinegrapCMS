<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The visitor-facing sentence of add_to_cart.php (the storefront's
 * add_to_cart action). The refusal for a product that could not be added
 * was a bare English literal, so a Turkish storefront showed it in English.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_add_to_cart_texts_refusal_translated()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/add_to_cart.php');
	$sentence = 'Sorry, that product could not be added. The product might no longer exist or might have recently been disabled.';

	pg_assert_contains("\$message = lang('" . $sentence . "');", $source, 'the refusal goes through lang()');
	pg_assert_same(0, substr_count($source, "\$message = '" . $sentence . "';"), 'no bare English refusal');
}
