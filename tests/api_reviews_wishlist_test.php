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
