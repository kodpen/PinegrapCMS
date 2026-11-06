<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for pg_comment_copy_columns() (includes/fn/reviews.php) and its use
 * in duplicate_page_f.php: a copied page keeps its reviews' stars, the
 * store's answers and which comments are questions and answers.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Before the steps nothing beyond the classic columns is written.
function test_comment_copy_columns_before_the_steps()
{
	pg_assert_same(array(), pg_comment_copy_columns(array('kind' => 'question', 'seller_reply' => 'Hi'), array(), false, false), 'no columns yet');
	pg_assert_same(array(), pg_comment_copy_columns('not a row', array(), true, true), 'not a row');
}

// The store's answer under a review is copied with the review; the purchase
// that verified it is not: a product's rating counts verified reviews over
// every page, so a copy would count twice.
function test_comment_copy_columns_review_reply()
{
	$columns = pg_comment_copy_columns(array('seller_reply' => 'Thanks', 'seller_reply_at' => '1700000000', 'verified_order_id' => '42'), array(), true, false);

	pg_assert_same(array('seller_reply' => 'Thanks', 'seller_reply_at' => 1700000000), $columns, 'reply kept, verified order left out');
	pg_assert_same(array('seller_reply' => null, 'seller_reply_at' => 0), pg_comment_copy_columns(array('seller_reply' => null), array(), true, false), 'no reply stays NULL');
}

// A question stays a question; an answer names the copy of its question.
function test_comment_copy_columns_questions()
{
	$copied = array(10 => 110);

	pg_assert_same(array('kind' => 'question', 'answer_of' => 0), pg_comment_copy_columns(array('kind' => 'question', 'answer_of' => '0'), $copied, false, true), 'a question');
	pg_assert_same(array('kind' => 'answer', 'answer_of' => 110), pg_comment_copy_columns(array('kind' => 'answer', 'answer_of' => '10'), $copied, false, true), 'an answer names the copied question');
	pg_assert_same(array('kind' => 'answer', 'answer_of' => 0), pg_comment_copy_columns(array('kind' => 'answer', 'answer_of' => '7'), $copied, false, true), 'its question was not copied');
	pg_assert_same(array('kind' => 'comment', 'answer_of' => 0), pg_comment_copy_columns(array('kind' => 'other'), $copied, false, true), 'an unknown kind is a comment');
	pg_assert_same(array('kind' => 'comment', 'answer_of' => 0), pg_comment_copy_columns(array(), $copied, false, true), 'no kind read');
}

// The page copy writes the stars and the columns above.
function test_comment_copy_columns_page_copy_wiring()
{
	$copy = (string) file_get_contents(PG_FUNCTIONS_DIR . '/duplicate_page_f.php');
	$insert = strpos($copy, 'INSERT INTO comments');
	$statement = ($insert !== false) ? substr($copy, $insert, (int) strpos($copy, 'or output_error', $insert) - $insert) : '';

	pg_assert_true(strpos($statement, 'rating,') !== false, 'the rating column is written');
	pg_assert_true(strpos($statement, "(string) \$comment['rating']") !== false, 'the rating value is bound');
	pg_assert_true(strpos($copy, 'pg_comment_copy_columns($comment, $comment_copies, pg_review_replies_ready(), pg_product_questions_ready())') !== false, 'the review and question columns are asked for');
}
