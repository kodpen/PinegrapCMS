<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for includes/fn/reviews.php: which comments are product reviews, what
 * a reader is told when they may not write one, the SQL the purchase check
 * and the rating are read with, the AggregateRating a product's markup gets,
 * when the daily pass is due, and that every path that writes a comment asks
 * the gate or moves the rating.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// A product is reviewed; so is a group sold as one product with options. A
// browsing group is a category, and a page or a form record is no product.
function test_reviews_item_is_product()
{
	pg_assert_true(pg_review_item_is_product('product'), 'a product');
	pg_assert_true(pg_review_item_is_product('product_group', 'select'), 'a group of options');
	pg_assert_false(pg_review_item_is_product('product_group', 'browse'), 'a category');
	pg_assert_false(pg_review_item_is_product('product_group'), 'a group of unknown kind');
	pg_assert_false(pg_review_item_is_product(''), 'the page itself');
	pg_assert_false(pg_review_item_is_product('submitted_form'), 'a form record');
	pg_assert_false(pg_review_item_is_product('calendar_event'), 'an event');
}

// Signing in comes first, then the purchase, then a purchase not used yet.
function test_reviews_refusal_order()
{
	pg_assert_same('sign_in', pg_review_refusal(false, 0, 0), 'a visitor');
	pg_assert_same('sign_in', pg_review_refusal(false, 3, 12), 'a visitor, whatever was bought on the account');
	pg_assert_same('not_bought', pg_review_refusal(true, 0, 0), 'a customer who did not buy it');
	pg_assert_same('reviewed', pg_review_refusal(true, 2, 0), 'every purchase of it already reviewed');
	pg_assert_same('', pg_review_refusal(true, 2, 41), 'a purchase left to review');
	pg_assert_same('', pg_review_refusal(true, '1', '41'), 'numbers read from the database');
}

// Each refusal has its own sentence; a reader who may review is told nothing.
function test_reviews_refusal_messages()
{
	pg_assert_same('', pg_review_refusal_message(''));

	$sentences = array();

	foreach (array('sign_in', 'not_bought', 'reviewed') as $refusal) {
		$sentence = pg_review_refusal_message($refusal);
		pg_assert_true($sentence !== '', $refusal . ' is told why');
		$sentences[$sentence] = true;
	}

	pg_assert_same(3, count($sentences), 'three different sentences');
	pg_assert_same('', pg_review_refusal_message('anything else'));
}

// A bought order is finished and, when it is a bank transfer, paid or put on
// the account; a cart line saved for later was not bought.
function test_reviews_purchase_sql()
{
	$bought = pg_review_bought_sql();

	pg_assert_contains("orders.status IN ('complete', 'exported')", $bought);
	pg_assert_contains("orders.payment_method <> 'Offline Payment'", $bought);
	pg_assert_contains('orders.paid_at > 0', $bought);
	pg_assert_contains('orders.erp_on_account = 1', $bought);

	$product = pg_review_item_lines_sql('product', 17);

	pg_assert_contains("order_items.product_id = '17'", $product);
	pg_assert_contains('order_items.saved_for_later = 0', $product);

	$group = pg_review_item_lines_sql('product_group', 5);

	pg_assert_contains("products_groups_xref.product_group = '5'", $group);
	pg_assert_contains('order_items.saved_for_later = 0', $group);

	pg_assert_same('', pg_review_item_lines_sql('submitted_form', 5), 'only products and groups have lines');

	// The id reaches the statement as a number, whatever was posted.
	$posted = pg_review_item_lines_sql('product', "1' OR '1'='1");

	pg_assert_contains("order_items.product_id = '1'", $posted);
	pg_assert_false(strpos($posted, "'1'='1") !== false, 'nothing of the posted text but its number');
}

// The rating counts published, verified reviews of products with one to five
// stars, and nothing else.
function test_reviews_rating_sql()
{
	$sql = pg_review_rating_sql();

	pg_assert_contains("comments.item_type = 'product'", $sql);
	pg_assert_contains("comments.published = '1'", $sql);
	pg_assert_contains('comments.verified_order_id > 0', $sql);
	pg_assert_contains('comments.rating BETWEEN 1 AND 5', $sql);
}

// The markup carries a rating only when real reviews stand behind it.
function test_reviews_aggregate_jsonld()
{
	pg_assert_same(array(
		'@type'       => 'AggregateRating',
		'ratingValue' => 4.5,
		'reviewCount' => 2,
		'bestRating'  => 5,
		'worstRating' => 1,
	), pg_review_aggregate_jsonld('4.50', '2'), 'as read from the products row');

	pg_assert_same(4.33, pg_review_aggregate_jsonld(4.333333, 3)['ratingValue'], 'two decimals');
	pg_assert_same(null, pg_review_aggregate_jsonld('0.00', '0'), 'no reviews');
	pg_assert_same(null, pg_review_aggregate_jsonld('4.00', '0'), 'an average without a review behind it');
	pg_assert_same(null, pg_review_aggregate_jsonld('0.00', '3'), 'reviews without stars');
	pg_assert_same(null, pg_review_aggregate_jsonld('7.00', '3'), 'an average no review can give');

	$encoded = json_encode(array('aggregateRating' => pg_review_aggregate_jsonld('5.00', '1')));

	pg_assert_contains('"ratingValue":5', $encoded, 'a number in the markup, not a string');
}

// Once a day: not before the day is over, and at once on a site that never ran it.
function test_reviews_refresh_due()
{
	$now = 1760000000;

	pg_assert_true(pg_review_refresh_due(0, $now), 'never ran');
	pg_assert_false(pg_review_refresh_due($now - 3600, $now), 'an hour ago');
	pg_assert_false(pg_review_refresh_due($now - 86399, $now), 'a second short of a day');
	pg_assert_true(pg_review_refresh_due($now - 86400, $now), 'a day ago');
}

// Every path that writes a product comment asks the gate before it writes, or
// moves the rating after it: add_comment.php does both, the moderation screen,
// the scheduled publish and the API move the rating.
function test_reviews_write_paths()
{
	$root = PG_FUNCTIONS_DIR;

	$add = (string) file_get_contents($root . '/add_comment.php');
	$gate = strpos($add, 'pg_review_gate_applies($item_type, $item_id)');
	$insert = strpos($add, 'INSERT INTO comments');

	pg_assert_true(($gate !== false) && ($insert !== false) && ($gate < $insert), 'add_comment.php asks before it inserts');
	pg_assert_contains('verified_order_id', $add, 'and keeps the order');
	pg_assert_true(strpos($add, 'pg_review_comment_changed($item_type, $item_id)') > $insert, 'and moves the rating after it');

	pg_assert_contains('pg_review_comment_changed($item_type, $item_id)', (string) file_get_contents($root . '/edit_comment.php'), 'the moderation screen');
	pg_assert_contains('pg_review_refresh_products($reviewed_products)', (string) file_get_contents($root . '/job.php'), 'the scheduled publish');
	pg_assert_contains('pg_review_refresh_daily()', (string) file_get_contents($root . '/job.php'), 'the daily pass');

	$api = (string) file_get_contents($root . '/includes/api/resources/comments.php');

	pg_assert_same(2, substr_count($api, 'pg_review_comment_changed('), 'the API, on update and on delete');

	$page = (string) file_get_contents($root . '/get_page_content.php');

	pg_assert_contains('pg_review_eligibility(', $page, 'the comment form asks before it is drawn');
	pg_assert_contains('pg_review_product_aggregate_jsonld(', $page, 'the product markup carries the rating');
}
