<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Source pins for the order print view's product links: the product name
 * and image are real links, reachable from the keyboard.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function print_order_product_links_source($path)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $path);
}

// The print view links the product as the order view does (#2960).
function test_print_order_product_links_are_anchors()
{
	$source = print_order_product_links_source('print_order.php');

	pg_assert_false((bool) preg_match('/<(span|img)\b[^>]*\bonclick=/', $source), 'onclick on a span or img');
	pg_assert_false(strpos($source, "document.location.href = \\'edit_product.php") !== false, 'navigation in a script');
	pg_assert_true(substr_count($source, '<a href="edit_product.php?id=\' . (int) $product_id . \'" class="pg-link-body-emphasis text-decoration-none">') === 2, 'product name linked in both item blocks');
	pg_assert_true(strpos($source, '<a href="edit_product.php?id=\' . (int) $product_id . \'" class="d-block pg-print-none" style="top:52px;position:sticky;" tabindex="-1" aria-hidden="true"><img class="rounded-9 card-img" src="\' .  PATH . h($image_name) . \'" alt=""/></a>') !== false, 'product image inside a silent link');
}
