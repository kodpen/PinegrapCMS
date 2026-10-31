<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The order view's not-found state: the message with a My Account link,
 * the 404 mark on every not-found branch, and the account order links
 * skipping the order receipt e-mail pages.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function order_view_not_found_source($path)
{
	return (string) file_get_contents(dirname(__DIR__) . '/pinegrap/' . $path);
}

// The body of function $name in $source, up to the next function.
function order_view_not_found_function_body($source, $name)
{
	$start = strpos($source, 'function ' . $name);
	if ($start === false) return '';
	$next = strpos($source, "\nfunction ", $start + 1);
	return $next === false ? substr($source, $start) : substr($source, $start, $next - $start);
}

function test_order_view_not_found_message_html()
{
	$html = pg_sw_order_not_found_message_html('Order <b> & co', '/my-account?a=1&b=2');
	pg_assert_contains('Order &lt;b&gt; &amp; co', $html, 'message escaped');
	pg_assert_false(strpos($html, '&amp;amp;') !== false, 'message escaped twice');
	pg_assert_contains('<a href="/my-account?a=1&amp;b=2" class="alert-link">', $html, 'account link');
	pg_assert_contains('>' . h(lang('My Account')) . '</a>', $html, 'account link text');

	$bare = pg_sw_order_not_found_message_html('Not found.', '');
	pg_assert_same('Not found.', $bare);
}

function test_order_view_not_found_pages_without()
{
	$pages = array(
		array('page_id' => 3, 'page_name' => 'a'),
		array('page_id' => 5, 'page_name' => 'b'),
		array('page_id' => 7, 'page_name' => 'c'),
	);
	$left = pg_sw_pages_without($pages, array(3, 0));
	pg_assert_same(array(
		array('page_id' => 5, 'page_name' => 'b'),
		array('page_id' => 7, 'page_name' => 'c'),
	), $left);
	pg_assert_same($pages, pg_sw_pages_without($pages, array()));
	pg_assert_same(array(), pg_sw_pages_without($pages, array(7, '5', 3)));
}

function test_order_view_not_found_source_pins()
{
	$body = order_view_not_found_function_body(order_view_not_found_source('includes/fn/widgets.php'), '_render_system_widget_order_view');
	pg_assert_true($body !== '', 'order view renderer found');
	pg_assert_contains('pg_sw_item_missing(true)', $body, 'order view marks a missing order');
	pg_assert_contains('pg_sw_order_not_found_message_html(', $body, 'order view message has the account link');

	$url = order_view_not_found_function_body(order_view_not_found_source('includes/fn/widgets_account.php'), 'pg_sw_page_type_widget_url');
	pg_assert_contains("'view order'", $url, 'view order pages are filtered');
	pg_assert_contains('pg_sw_pages_without(', $url, 'receipt e-mail pages are dropped');
}
