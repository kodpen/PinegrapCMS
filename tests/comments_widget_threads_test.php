<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the item threads of the comments system widget: which product,
 * product group or calendar event a catalog, catalog detail or calendar
 * event view page shows (pg_comment_address_item() in includes/fn/comments.php),
 * the review form's refusals in the thread flags, and the renderer's order
 * (claim, the address item, its check against add_comment.php's rule, the
 * review gate).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function comments_widget_threads_test_renderer()
{
	$module = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/widgets_comments.php');
	$start = strpos($module, 'function _render_system_widget_comments(');

	return ($start === false) ? '' : substr($module, $start);
}

function comments_widget_threads_test_facts($add)
{
	return $add + array('address_item' => null, 'catalog_group_id' => 0, 'top_group_id' => 0, 'event_id' => null, 'get_id' => null);
}

// -- the item in the address ---------------------------------------------------

// A catalog page: the product group the address names, else its own, else the
// top-level one; an address naming a product or nothing names no thread.
function test_comments_widget_threads_catalog()
{
	$group = function ($id) {
		return array('item_type' => 'product_group', 'item_id' => $id);
	};
	$f = 'comments_widget_threads_test_facts';

	pg_assert_same($group(7), pg_comment_address_item('catalog', $f(array('address_item' => array('id' => '7', 'type' => 'product group')))), 'the group in the address');
	pg_assert_same($group(7), pg_comment_address_item('catalog', $f(array('address_item' => array('id' => '7', 'type' => 'product group'), 'catalog_group_id' => 3))), 'the address wins over the page group');
	pg_assert_same(null, pg_comment_address_item('catalog', $f(array('address_item' => array('id' => '9', 'type' => 'product')))), 'a product in a catalog address');
	pg_assert_same(null, pg_comment_address_item('catalog', $f(array('address_item' => array('id' => '', 'type' => '')))), 'an address naming nothing');
	pg_assert_same($group(3), pg_comment_address_item('catalog', $f(array('catalog_group_id' => '3', 'top_group_id' => 1))), 'the page group');
	pg_assert_same($group(1), pg_comment_address_item('catalog', $f(array('catalog_group_id' => 0, 'top_group_id' => '1'))), 'the top-level group');
	pg_assert_same(null, pg_comment_address_item('catalog', $f(array())), 'no group at all');
}

// A catalog detail page: the product or group in the address; no address item
// is the page's own thread; an address naming nothing is no thread.
function test_comments_widget_threads_catalog_detail()
{
	$f = 'comments_widget_threads_test_facts';

	pg_assert_same(array('item_type' => 'product', 'item_id' => 12), pg_comment_address_item('catalog detail', $f(array('address_item' => array('id' => '12', 'type' => 'product')))), 'a product');
	pg_assert_same(array('item_type' => 'product_group', 'item_id' => 4), pg_comment_address_item('catalog detail', $f(array('address_item' => array('id' => 4, 'type' => 'product group')))), 'a product group');
	pg_assert_same(array('item_type' => '', 'item_id' => 0), pg_comment_address_item('catalog detail', $f(array())), 'no item in the address');
	pg_assert_same(null, pg_comment_address_item('catalog detail', $f(array('address_item' => array('id' => '', 'type' => '')))), 'not found');
	pg_assert_same(null, pg_comment_address_item('catalog detail', $f(array('address_item' => array('id' => '5', 'type' => 'page')))), 'an unknown type');
	pg_assert_same(null, pg_comment_address_item('catalog detail', $f(array('address_item' => array('id' => '5x', 'type' => 'product')))), 'an id that is no number');
	pg_assert_same(null, pg_comment_address_item('catalog detail', $f(array('address_item' => array('id' => array(5), 'type' => 'product')))), 'an id that is no value');
}

// A calendar event view page: the event the render's sender names (the
// search index), else ?id=; without one the page's own thread. A recurrence
// shares its event's thread: ?recurrence_number= is not read.
function test_comments_widget_threads_calendar_event()
{
	$f = 'comments_widget_threads_test_facts';
	$event = function ($id) {
		return array('item_type' => 'calendar_event', 'item_id' => $id);
	};

	pg_assert_same($event(21), pg_comment_address_item('calendar event view', $f(array('get_id' => '21'))), '?id=');
	pg_assert_same($event(30), pg_comment_address_item('calendar event view', $f(array('event_id' => 30, 'get_id' => '21'))), 'the search index names the event');
	pg_assert_same(array('item_type' => '', 'item_id' => 0), pg_comment_address_item('calendar event view', $f(array())), 'no id');
	pg_assert_same(array('item_type' => '', 'item_id' => 0), pg_comment_address_item('calendar event view', $f(array('get_id' => ''))), 'an empty id');
	pg_assert_same(array('item_type' => '', 'item_id' => 0), pg_comment_address_item('calendar event view', $f(array('get_id' => array('1')))), 'an id that is no value');
	pg_assert_same(null, pg_comment_address_item('calendar event view', $f(array('get_id' => 'abc'))), 'an id that is no number');
	pg_assert_same(null, pg_comment_address_item('calendar event view', $f(array('get_id' => '-4'))), 'a negative id');
	pg_assert_same(null, pg_comment_address_item('calendar event view', $f(array('event_id' => '0'))), 'the sender names no event');
}

// Every other page type has no item in its address.
function test_comments_widget_threads_other_pages()
{
	$facts = comments_widget_threads_test_facts(array('address_item' => array('id' => '1', 'type' => 'product'), 'get_id' => '1'));

	foreach (array('standard', 'form item view', 'Catalog Detail', '', null, array('catalog')) as $type) {
		pg_assert_same(null, pg_comment_address_item($type, $facts), var_export($type, true));
	}

	pg_assert_same(array('item_type' => '', 'item_id' => 0), pg_comment_address_item('catalog detail', 'x'), 'facts that are not an array');
}

// -- the review form in the thread flags ---------------------------------------

function comments_widget_threads_test_flags($add)
{
	return pg_comments_widget_thread_flags($add + array('allow_new' => true, 'count' => 0, 'name_mode' => 'typed'));
}

// 'sign_in' asks a visitor to sign in; 'not_bought' and 'reviewed' close the
// form for this visitor; no refusal leaves the form as it was.
function test_comments_widget_threads_review_flags()
{
	$open = comments_widget_threads_test_flags(array('signed_in' => true));
	pg_assert_true($open['can_comment'] && $open['comments_open'], 'no refusal: the form');

	$visitor = comments_widget_threads_test_flags(array('review_refusal' => 'sign_in'));
	pg_assert_true($visitor['must_sign_in'] && $visitor['comments_open'], 'sign_in: the sign-in note under an open thread');
	pg_assert_false($visitor['can_comment'] || $visitor['comments_closed'], 'sign_in: no form');

	foreach (array('not_bought', 'reviewed') as $refusal) {
		$member = comments_widget_threads_test_flags(array('signed_in' => true, 'review_refusal' => $refusal));
		pg_assert_true($member['comments_closed'], $refusal . ': closed for this visitor');
		pg_assert_false($member['comments_open'] || $member['can_comment'] || $member['must_sign_in'] || $member['name_is_typed'], $refusal . ': no form');
		pg_assert_true($member['is_signed_in'], $refusal . ': still signed in');
	}

	$closed = comments_widget_threads_test_flags(array('allow_new' => false, 'signed_in' => true, 'review_refusal' => 'not_bought'));
	pg_assert_true($closed['comments_closed'] && !$closed['comments_open'], 'a closed thread stays closed');

	$index = comments_widget_threads_test_flags(array('search_index' => true, 'review_refusal' => 'not_bought'));
	pg_assert_false($index['comments_closed'] || $index['must_sign_in'], 'the search index: no refusal drawn');

	$email = comments_widget_threads_test_flags(array('email' => true, 'review_refusal' => 'sign_in'));
	pg_assert_false($email['comments_closed'] || $email['comments_open'] || $email['must_sign_in'], 'an e-mail: no refusal drawn');

	$odd = comments_widget_threads_test_flags(array('signed_in' => true, 'review_refusal' => array('reviewed')));
	pg_assert_true($odd['can_comment'], 'a refusal that is not a string is none');
	pg_assert_same(23, count($odd), 'still 23 flags');
}

// -- the renderer ----------------------------------------------------------------

// Claimed first; then the item in the address, checked by the rule
// add_comment.php applies, before anything is read; the review gate reads the
// thread the form posts to and feeds both the flags and the closed message.
function test_comments_widget_threads_renderer_order()
{
	$body = comments_widget_threads_test_renderer();
	pg_assert_true($body !== '', 'the renderer');
	if ($body === '') {
		return;
	}

	$claim = strpos($body, 'pg_comments_widget_claim($page_id)');
	$item = strpos($body, 'pg_comment_address_item($page[\'page_type\'], pg_comment_address_item_facts($page_id, $page[\'page_type\'], $ctx))');
	$verify = strpos($body, 'pg_comment_thread_verify($page_id, $page[\'page_type\'], $item[\'item_type\'], $item[\'item_id\'])');
	$read = strpos($body, 'pg_comments_rows($page_id, $read_thread');
	$gate = strpos($body, 'pg_review_gate_applies($post_type, $post_id)');
	$facts = strpos($body, "'review_refusal' => \$review_refusal");
	$message = strpos($body, 'pg_review_refusal_message($review_refusal)');

	pg_assert_true(($claim !== false) && ($item !== false) && ($claim < $item), 'claimed before the address is read');
	pg_assert_true(($verify !== false) && ($item < $verify) && ($verify < $read), 'the item is checked before the thread is read');
	pg_assert_true(($gate !== false) && ($facts !== false) && ($gate < $facts), 'the review gate feeds the flags');
	pg_assert_true($message !== false, 'the refusal sentence');
	pg_assert_contains('$read_thread = $item;', $body, 'the item is the thread read');
	pg_assert_contains("&& \$audience['session'] && !\$is_staff &&", $body, 'staff and renders that are not the visitor\'s are not asked');
}

// A thread the address names nothing for prints nothing; a refused item too.
function test_comments_widget_threads_renderer_refusals()
{
	$body = comments_widget_threads_test_renderer();

	pg_assert_contains("if (\$item === null) {\n            return '';", $body, 'no item: nothing');
	pg_assert_contains("if (!\$verified['accept']) {\n                return '';", $body, 'a refused item: nothing');
}
