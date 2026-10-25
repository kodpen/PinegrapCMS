<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the external API's product reviews and favourites: verified and
 * the store's reply on a comment, the verified filter, a product's reviews and
 * rating, a customer's favourites, and the product.review.created event.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

if (!defined('PG_API_PANEL')) {
	define('PG_API_PANEL', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/api/resources/comments.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/resources/products.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/resources/customers.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/outbound/webhooks.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/schema.php');
require_once(__DIR__ . '/helpers/source_reader.php');

// The route of an id, null when there is none.
function api_reviews_wishlist_test_route($id)
{
	foreach (api_schema() as $route) {
		if ($route['id'] === $id) {
			return $route;
		}
	}

	return null;
}

// The names of a route's parameters.
function api_reviews_wishlist_test_params($route)
{
	$names = array();

	foreach ((array) $route['params'] as $param) {
		$names[] = $param['name'];
	}

	return $names;
}

// A comment says whether it is a verified purchase and carries the store's
// reply; a reply's time means nothing without its words.
function test_api_comment_review_values()
{
	pg_assert_same(
		array('verified' => true, 'store_reply' => 'Thank you!', 'store_reply_at' => '2026-10-30T10:00:00Z'),
		api_comment_review_values(array('verified_order_id' => '438', 'seller_reply' => 'Thank you!', 'seller_reply_at' => '1793354400')),
		'a verified review with a reply'
	);
	pg_assert_same(
		array('verified' => false, 'store_reply' => null, 'store_reply_at' => null),
		api_comment_review_values(array('verified_order_id' => '0', 'seller_reply' => '', 'seller_reply_at' => '1793354400')),
		'no purchase, no reply: the time is dropped'
	);
	pg_assert_same(
		array('verified' => false, 'store_reply' => null, 'store_reply_at' => null),
		api_comment_review_values(array()),
		'before 11.0.14'
	);
}

// The comment declares the fields, reads the columns only when they exist,
// and the list filters on verified.
function test_api_comment_review_declared()
{
	$schema = api_comment_schema();

	pg_assert_same('boolean', $schema['verified'], 'verified');
	pg_assert_same('string?', $schema['store_reply'], 'store_reply');
	pg_assert_same('string?', $schema['store_reply_at'], 'store_reply_at');

	$select = pg_test_function_source('api_comment_select');
	pg_assert_contains("pg_reviews_ready() ? 'comments.verified_order_id,' : '0 AS verified_order_id,'", $select, 'verified behind its probe');
	pg_assert_contains("pg_review_replies_ready() ? 'comments.seller_reply, comments.seller_reply_at,'", $select, 'the reply behind its probe');

	$route = api_reviews_wishlist_test_route('comments.list');
	pg_assert_true(in_array('verified', api_reviews_wishlist_test_params($route), true), 'the list filters on verified');
	pg_assert_contains("pg_review_verified_filter_sql(\$params['verified'] ? 'yes' : 'no')", pg_test_function_source('api_comments_list'), 'the screen\'s filter');
}

// A product carries its rating: the average of its verified, starred reviews
// (null without one) and their number.
function test_api_product_rating()
{
	pg_assert_same(array('average' => 4.33, 'count' => 3), api_product_rating(array('rating_avg' => '4.33', 'rating_count' => '3')), 'as the database answers');
	pg_assert_same(array('average' => 5.0, 'count' => 1), api_product_rating(array('rating_avg' => '5.00', 'rating_count' => 1)), 'one review');
	pg_assert_same(array('average' => null, 'count' => 0), api_product_rating(array('rating_avg' => '0.00', 'rating_count' => '0')), 'none yet');
	pg_assert_same(array('average' => null, 'count' => 0), api_product_rating(array()), 'no columns');

	$schema = api_product_schema();
	pg_assert_same(array('average' => 'number?', 'count' => 'integer'), $schema['rating'], 'declared');
	pg_assert_contains('"products.rating_avg, products.rating_count," : "0 AS rating_avg, 0 AS rating_count,"', pg_test_function_source('api_product_select'), 'read behind the probe');
}

// GET /products/{id}/reviews: the product's own published comments, as the
// comments list gives them.
function test_api_products_reviews()
{
	$route = api_reviews_wishlist_test_route('products.reviews');

	pg_assert_true($route !== null, 'the route');
	pg_assert_same('GET', $route['method']);
	pg_assert_same('/products/{id}/reviews', $route['path']);
	pg_assert_same('comments:read', $route['scope'], 'what visitors wrote');
	pg_assert_same(array('list' => 'Comment'), $route['returns']);
	pg_assert_same(array('id', 'verified', 'limit', 'cursor'), api_reviews_wishlist_test_params($route));

	$handler = pg_test_function_source('api_products_reviews');
	pg_assert_contains("api_fail_not_found(lang('Product'))", $handler, '404 for a product that is not there');
	pg_assert_contains("'item_type' => 'product'", $handler, 'the product\'s own');
	pg_assert_contains("'status'    => 'published'", $handler, 'published only');
	pg_assert_contains('api_comments_list($list);', $handler, 'one list for both routes');
}

// GET /customers/{id}/wishlist: the favourites of the customer's accounts,
// under the customers row rule.
function test_api_customers_wishlist()
{
	$route = api_reviews_wishlist_test_route('customers.wishlist');

	pg_assert_true($route !== null, 'the route');
	pg_assert_same('/customers/{id}/wishlist', $route['path']);
	pg_assert_same('customers:read', $route['scope']);
	pg_assert_same(array('list' => 'WishlistItem'), $route['returns']);

	$handler = pg_test_function_source('api_customers_wishlist');
	$reach = strpos($handler, 'api_customer_reachable_or_fail($id');
	$read = strpos($handler, 'api_customer_wishlist_sql(');
	pg_assert_true(($reach !== false) && ($read !== false) && ($reach < $read), 'the row rule before the read');
	pg_assert_contains('pg_wishlist_ready() ?', $handler, 'an empty list before the table');

	$sql = api_customer_wishlist_sql('5', 0, 51);
	pg_assert_contains("(wishlist_items.user_id > 0) AND (user.user_contact = '5')", $sql, 'the accounts of the contact, never a visitor list');
	pg_assert_contains('ORDER BY wishlist_items.id DESC', $sql, 'newest first');
	pg_assert_contains('LIMIT 51', $sql);
	pg_assert_false(strpos($sql, 'wishlist_items.id <') !== false, 'the first page');
	pg_assert_contains("(wishlist_items.id < '90')", api_customer_wishlist_sql(5, '90', 51), 'a later page');

	pg_assert_same(
		array('product_id' => 7, 'sku' => '7', 'name' => 'TS-01', 'short_description' => 'Blue shirt', 'available' => true, 'added_at' => '2026-10-30T10:00:00Z', 'added_at_unix' => 1793354400),
		api_wishlist_item_present(array('id' => '3', 'product_id' => '7', 'added_at' => '1793354400', 'name' => 'TS-01', 'short_description' => 'Blue shirt', 'enabled' => '1')),
		'a product on sale'
	);
	pg_assert_same(false, api_wishlist_item_present(array('id' => '3', 'product_id' => '7', 'added_at' => '1', 'name' => 'TS-01', 'short_description' => null, 'enabled' => '0'))['available'], 'switched off or binned');
	$gone = api_wishlist_item_present(array('id' => '3', 'product_id' => '7', 'added_at' => '1', 'name' => null, 'short_description' => null, 'enabled' => null));
	pg_assert_same(array(null, null, false), array($gone['name'], $gone['short_description'], $gone['available']), 'a product no longer there');
	pg_assert_same(array_keys(api_wishlist_item_schema()), array_keys($gone), 'declared as presented');

	pg_assert_contains("'WishlistItem'      => 'api_wishlist_item_schema'", (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/api/openapi.php'), 'in the OpenAPI document');
}

// product.review.created: in the catalogue, sent by add_comment.php once the
// review is saved, with what GET /comments says of it.
function test_api_product_review_created()
{
	pg_assert_true(array_key_exists('product.review.created', api_webhook_events()), 'a known event');

	pg_assert_same(
		array('id' => 12, 'item_type' => 'product', 'item_id' => 7, 'rating' => 5, 'verified' => true, 'status' => 'published'),
		pg_review_created_event('12', 'product', '7', 5, 438, '1', '0000-00-00 00:00:00'),
		'a verified review published at once'
	);
	pg_assert_same(
		array('id' => 13, 'item_type' => 'product_group', 'item_id' => 3, 'rating' => null, 'verified' => false, 'status' => 'pending'),
		pg_review_created_event(13, 'product_group', 3, 0, 0, '0', ''),
		'held for moderation, without stars'
	);
	pg_assert_same('scheduled', pg_review_created_event(14, 'product', 7, 4, 0, '0', '2026-11-01 09:00:00')['status'], 'scheduled');

	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/add_comment.php');
	$insert = strpos($source, 'INSERT INTO comments');
	$announce = strpos($source, "pg_announce('product.review.created'");
	pg_assert_true(($insert !== false) && ($announce !== false) && ($insert < $announce), 'after the comment is saved');
	pg_assert_contains('if ($is_product_review && ($comment_id > 0)) {', $source, 'only for a product review');
	pg_assert_contains('$is_product_review = pg_review_gate_applies($item_type, $item_id);', $source, 'the review gate decides what is a review');
}
