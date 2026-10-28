<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the comments of a designed product page: a 'standard' page whose
 * top level places a catalog_item_view widget reads as a catalog detail page
 * (pg_comment_thread_page_type() in includes/fn/comments.php), so its
 * comments widget shows the product's reviews, add_comment.php takes only
 * that thread, and links, e-mails and recent lists follow.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function comments_product_view_test_source($file, $function)
{
	$module = (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
	if ($function === '') {
		return $module;
	}

	$start = strpos($module, 'function ' . $function . '(');

	if ($start === false) {
		return '';
	}

	$end = strpos($module, "\nfunction ", $start + 10);

	return ($end === false) ? substr($module, $start) : substr($module, $start, $end - $start);
}

// Only a standard page with the product widget and no record view.
function test_comments_product_view_page_type()
{
	pg_assert_same('catalog detail', pg_comment_product_view_page_type('standard', false, true), 'a designed product page');
	pg_assert_same('standard', pg_comment_product_view_page_type('standard', true, true), 'a record view decides first');
	pg_assert_same('standard', pg_comment_product_view_page_type('standard', false, false), 'no product widget');
	pg_assert_same('catalog', pg_comment_product_view_page_type('catalog', false, true), 'a classic catalog page keeps its type');
	pg_assert_same('form item view', pg_comment_product_view_page_type('form item view', false, true), 'a form item view page keeps its type');
	pg_assert_same('my account', pg_comment_product_view_page_type('my account', false, true), 'another page type keeps its type');
	pg_assert_same('', pg_comment_product_view_page_type(array('standard'), false, true), 'no value');
	pg_assert_same('blog', pg_comment_thread_page_type(0, 'blog'), 'no page: its type, no read');
}

// The item in the address of a designed product page: as catalog detail,
// but without a '/' it is ?pid= or no thread, never the page's own.
function test_comments_product_view_address_item()
{
	$f = function ($add) {
		return $add + array('address_item' => null, 'catalog_group_id' => 0, 'top_group_id' => 0, 'event_id' => null, 'get_id' => null, 'product_view' => true, 'pid' => null);
	};

	pg_assert_same(array('item_type' => 'product', 'item_id' => 12), pg_comment_address_item('catalog detail', $f(array('address_item' => array('id' => '12', 'type' => 'product')))), 'a product');
	pg_assert_same(array('item_type' => 'product_group', 'item_id' => 4), pg_comment_address_item('catalog detail', $f(array('address_item' => array('id' => '4', 'type' => 'product group')))), 'a select group');
	pg_assert_same(null, pg_comment_address_item('catalog detail', $f(array('address_item' => array('id' => '', 'type' => '')))), 'not found');
	pg_assert_same(array('item_type' => 'product', 'item_id' => 7), pg_comment_address_item('catalog detail', $f(array('pid' => '7'))), '?pid=');
	pg_assert_same(null, pg_comment_address_item('catalog detail', $f(array())), 'no product: no thread');
	pg_assert_same(null, pg_comment_address_item('catalog detail', $f(array('pid' => 'x'))), '?pid= that is no number');
	pg_assert_same(array('item_type' => '', 'item_id' => 0), pg_comment_address_item('catalog detail', array('product_view' => false, 'pid' => '7')), 'a classic page ignores ?pid=');
}

// Every reader of the thread takes the page type through the same function.
function test_comments_product_view_readers()
{
	$verify = comments_product_view_test_source('includes/fn/comments.php', 'pg_comment_thread_verify');
	pg_assert_contains('$page_type = pg_comment_thread_page_type($page_id, $page_type);', $verify, 'add_comment.php\'s check');
	pg_assert_true(strpos($verify, 'pg_comment_thread_page_type') > strpos($verify, 'pg_sw_record_comment_context'), 'after the record view');

	$render = comments_product_view_test_source('includes/fn/widgets_comments.php', '_render_system_widget_comments');
	pg_assert_contains('pg_comment_thread_page_type($page_id, $page[\'page_type\'])', $render, 'the widget');
	pg_assert_contains('pg_comment_address_item_facts($page_id, $thread_type, $ctx, $thread_type !== $page[\'page_type\'])', $render, 'the address facts');

	$email = comments_product_view_test_source('includes/fn/widgets_comments.php', '_pg_comments_widget_email');
	pg_assert_true(strpos($email, 'pg_comment_thread_page_type(') < strpos($email, 'pg_comments_email_decision('), 'the e-mail, before its decision');

	pg_assert_contains('pg_comment_thread_page_type($page_id, $page_type)', comments_product_view_test_source('includes/fn/widgets_comments_recent.php', ''), 'the recent list');

	$query = comments_product_view_test_source('includes/fn/content.php', 'get_query_string_for_page_url');
	pg_assert_contains('default:', $query, 'links on any other page type');
	pg_assert_contains("(\$item_type === 'product') || (\$item_type === 'product_group')", $query, 'a product or group');
}

// aggregateRating on a designed product page: only when its comments widget
// draws this product's reviews with their stars.
function test_comments_product_view_rating_markup()
{
	$on = array('comments' => '1', 'comments_rating' => '1');
	$item = array('item_type' => 'product', 'item_id' => 12);

	pg_assert_true(pg_comment_rating_markup_decision($on, 'catalog detail', true, $item, 12), 'the product\'s starred reviews show');
	pg_assert_true(pg_comment_rating_markup_decision($on, 'catalog detail', true, $item, '12'), 'an id as text');
	pg_assert_false(pg_comment_rating_markup_decision(array('comments' => '1', 'comments_rating' => '0'), 'catalog detail', true, $item, 12), 'no stars');
	pg_assert_false(pg_comment_rating_markup_decision(array('comments' => '0', 'comments_rating' => '1'), 'catalog detail', true, $item, 12), 'comments off');
	pg_assert_false(pg_comment_rating_markup_decision($on, 'standard', true, $item, 12), 'not a product page');
	pg_assert_false(pg_comment_rating_markup_decision($on, 'catalog detail', false, $item, 12), 'no comments widget');
	pg_assert_false(pg_comment_rating_markup_decision($on, 'catalog detail', true, array('item_type' => 'product_group', 'item_id' => 12), 12), 'a select group\'s thread');
	pg_assert_false(pg_comment_rating_markup_decision($on, 'catalog detail', true, array('item_type' => 'product', 'item_id' => 13), 12), 'another product');
	pg_assert_false(pg_comment_rating_markup_decision($on, 'catalog detail', true, null, 12), 'no thread');
	pg_assert_false(pg_comment_rating_markup_decision($on, 'catalog detail', true, $item, 0), 'no product');

	$build = comments_product_view_test_source('includes/fn/widgets_catalog.php', '_pg_build_product_jsonld');
	pg_assert_contains('if ($rating_shown && (($aggregate_rating = pg_review_product_aggregate_jsonld(', $build, 'the builder asks');
	pg_assert_contains('pg_comment_product_view_rating_shown($page_id,', (string) file_get_contents(PG_FUNCTIONS_DIR . '/get_page_content.php'), 'the page passes the answer');
}

// The canvas reads a designed product page as catalog detail, as the server.
function test_comments_product_view_canvas()
{
	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.js');
	pg_assert_contains("if (_sdCommentsProductViewPage(p)) type = 'catalog detail';", $js, 'the thread');
	pg_assert_contains("return _swTabHasWidget(p, ['catalog_item_view']) && !_swTabHasWidget(p, ['form_item_view']);", $js, 'the same rule');
}

// The store, boutique and bookshop product page: a reviews widget with
// stars, newest first, never pinned.
function test_comments_product_view_templates()
{
	foreach (array('pinegrap-store', 'pinegrap-boutique', 'pinegrap-bookshop') as $id) {
		$tpl = _pg_tpl_include(PG_FUNCTIONS_DIR . '/includes/design_templates/' . $id . '.php');
		pg_assert_true(is_array($tpl), $id . ' loaded');
		if (!is_array($tpl)) {
			continue;
		}

		$page = null;
		foreach ($tpl['pages'] as $p) {
			if (isset($p['key']) && $p['key'] === 'product') {
				$page = $p;
			}
		}
		pg_assert_true(is_array($page), $id . ': the product page');
		if (!is_array($page)) {
			continue;
		}

		pg_assert_true(!empty($page['comments']['rating']) && !empty($page['comments']['login']), $id . ': comments with stars, signed in');
		pg_assert_contains('"templateWidget":"product_reviews"', json_encode($page['tree']), $id . ': the reviews on the page');

		$w = isset($tpl['widgets']['product_reviews']) ? $tpl['widgets']['product_reviews'] : null;
		pg_assert_same('comments', is_array($w) ? $w['config']['regionType'] : null, $id . ': a comments widget');
		pg_assert_same('none', is_array($w) ? $w['config']['featured'] : null, $id . ': never pinned');
		pg_assert_same('product', is_array($w) ? $w['page'] : null, $id . ': on the product page');
		pg_assert_same('ecommerce', is_array($w) ? $w['requires'] : null, $id . ': only with the shop');
	}
}
