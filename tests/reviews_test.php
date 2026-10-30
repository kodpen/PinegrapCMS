<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for includes/fn/reviews.php: which comments are product reviews, what
 * a reader is told when they may not write one, the SQL the purchase check
 * and the rating are read with, the AggregateRating a product's markup gets,
 * when the daily pass is due, that every path that writes a comment asks
 * the gate or moves the rating, what the review request e-mail links to and
 * when it starts, and Comment Moderation's verified filter.
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

// The store's reply is kept trimmed and at most 5000 characters long.
function test_reviews_reply_text()
{
	pg_assert_same('Teşekkürler!', pg_review_reply_text("  Teşekkürler!\n"));
	pg_assert_same('', pg_review_reply_text(null));
	pg_assert_same(5000, mb_strlen(pg_review_reply_text(str_repeat('ş', 6000))), 'cut at 5000 characters, not bytes');
}

// A reply is dated when it is first written or changed, keeps its date while
// it stays the same, and loses it when it is taken away.
function test_reviews_reply_time()
{
	$now = 1760000000;
	$then = $now - 7200;

	pg_assert_same($now, pg_review_reply_time('', 'Teşekkürler', 0, $now), 'a new reply');
	pg_assert_same($then, pg_review_reply_time('Teşekkürler', 'Teşekkürler', $then, $now), 'saved again unchanged');
	pg_assert_same($then, pg_review_reply_time("Teşekkürler \n", 'Teşekkürler', (string) $then, $now), 'stored with spaces around it');
	pg_assert_same($now, pg_review_reply_time('Teşekkürler', 'Teşekkür ederiz', $then, $now), 'changed');
	pg_assert_same($now, pg_review_reply_time('Teşekkürler', 'Teşekkürler', 0, $now), 'an old reply without a date gets one');
	pg_assert_same(0, pg_review_reply_time('Teşekkürler', '', $then, $now), 'taken away');
}

// The reply is printed under the review in either markup, as text.
function test_reviews_reply_html()
{
	pg_assert_same('', pg_review_reply_html('', 'now', true), 'no reply, nothing');
	pg_assert_same('', pg_review_reply_html("  \n", 'now', false), 'only spaces, nothing');

	foreach (array(true, false) as $bootstrap) {
		$html = pg_review_reply_html('<script>alert(1)</script> Teşekkürler', '<time datetime="2026-10-18T09:00:00Z">2 days ago</time>', $bootstrap);

		pg_assert_contains('software_seller_reply', $html, $bootstrap ? 'bootstrap' : 'classic');
		pg_assert_contains('&lt;script&gt;', $html, 'the reply is text');
		pg_assert_contains('<time datetime="2026-10-18T09:00:00Z">2 days ago</time>', $html, 'the time as get_relative_time() prints it');
		pg_assert_false(strpos($html, '<script>') !== false, 'no markup from the reply');
	}

	pg_assert_false(strpos(pg_review_reply_html('Teşekkürler', '', true), 'text-body-secondary') !== false, 'no time, no time span');
}

// The moderation screen writes the reply with its date, and the page prints
// it under the review in both markups.
function test_reviews_reply_paths()
{
	$edit = (string) file_get_contents(PG_FUNCTIONS_DIR . '/edit_comment.php');

	pg_assert_contains('pg_review_reply_time($seller_reply, $new_seller_reply, $seller_reply_at, time())', $edit);
	pg_assert_contains('$sql_seller_reply', $edit);

	$page = (string) file_get_contents(PG_FUNCTIONS_DIR . '/get_page_content.php');

	pg_assert_same(2, substr_count($page, "' . \$output_seller_reply . '"), 'under the review in both markups');
}

// The review request links to the review form of the item's page: the
// product page, the item's address on it, and the form's anchor.
function test_reviews_request_url()
{
	pg_assert_same(
		'https://example.com/urun/mocha-chair#software_add_comment',
		pg_review_request_url('https://example.com/', 'urun', 'mocha-chair'),
		'a base with a trailing slash gives no double slash');
	pg_assert_same(
		'https://example.com/shop/urun/mocha-chair#software_add_comment',
		pg_review_request_url('https://example.com/shop', 'urun', " mocha-chair\n"),
		'the address is trimmed');
	pg_assert_same(
		'https://example.com/%C3%9Cr%C3%BCn%20Detay/%C3%87ay%20Barda%C4%9F%C4%B1#software_add_comment',
		pg_review_request_url('https://example.com/', 'Ürün Detay', 'Çay Bardağı'),
		'spaces and Turkish letters encoded as encode_url_path() does');
	pg_assert_same('', pg_review_request_url('https://example.com/', '', 'mocha-chair'), 'no product page');
	pg_assert_same('', pg_review_request_url('https://example.com/', 'urun', '  '), 'no address');
	pg_assert_same('', pg_review_request_url('https://example.com/', null, null), 'nothing at all');
}

// The mail-merge fields of the e-mail: the order number, the first review
// form, and a line per item to review.
function test_reviews_request_fields()
{
	$fields = pg_review_request_fields(1042, array(
		array('name' => 'Gone', 'url' => ''),
		array('name' => 'Mocha Office Chair', 'url' => 'https://example.com/urun/mocha#software_add_comment'),
		array('name' => '', 'url' => 'https://example.com/urun/cup#software_add_comment'),
	));

	pg_assert_same(array(
		array('name' => 'order_number', 'data' => '1042'),
		array('name' => 'review_url', 'data' => 'https://example.com/urun/mocha#software_add_comment'),
		array('name' => 'review_links', 'data' => "Mocha Office Chair: https://example.com/urun/mocha#software_add_comment\nhttps://example.com/urun/cup#software_add_comment"),
	), $fields, 'an item without an address is left out; one without a name is its address alone');

	pg_assert_same(array(
		array('name' => 'order_number', 'data' => 'A-7'),
		array('name' => 'review_url', 'data' => ''),
		array('name' => 'review_links', 'data' => ''),
	), pg_review_request_fields('A-7', array()), 'nothing to review');
}

// Each bought line is reviewed on the group of options that sells it, or
// else on the product; an item is asked about once, and only when its page
// can be opened.
function test_reviews_request_targets_from_rows()
{
	$row = function ($product_id, $product, $group = array()) {
		return array(
			'product_id'                => $product_id,
			'product_name'              => $product['name'],
			'product_short_description' => $product['short'],
			'product_address_name'      => $product['address'],
			'product_enabled'           => isset($product['enabled']) ? $product['enabled'] : '1',
			'group_id'                  => isset($group['id']) ? $group['id'] : null,
			'group_name'                => isset($group['name']) ? $group['name'] : null,
			'group_short_description'   => isset($group['short']) ? $group['short'] : null,
			'group_address_name'        => isset($group['address']) ? $group['address'] : null,
		);
	};

	$chair = array('id' => '9', 'name' => 'chair-group', 'short' => 'Mocha Office Chair', 'address' => 'mocha-office-chair');

	$targets = pg_review_request_targets_from_rows(array(
		$row('5', array('name' => 'CH-GRN', 'short' => 'Green', 'address' => 'chair-green'), $chair),
		$row('6', array('name' => 'CH-ORG', 'short' => 'Orange', 'address' => 'chair-orange'), $chair),
		$row('7', array('name' => 'CUP-01', 'short' => '', 'address' => ' tea-cup ')),
		$row('8', array('name' => 'NO-ADDR', 'short' => 'No address', 'address' => '')),
		$row('7', array('name' => 'CUP-01', 'short' => '', 'address' => ' tea-cup ')),
		$row('10', array('name' => 'OLD-01', 'short' => 'Retired', 'address' => 'retired', 'enabled' => '0')),
		$row('11', array('name' => 'LMP-01', 'short' => 'Lamp shade', 'address' => 'lamp-shade', 'enabled' => '0'), array('id' => '12', 'name' => 'Lamp', 'short' => '', 'address' => 'lamp')),
		$row('13', array('name' => 'BAG-01', 'short' => 'Bag', 'address' => 'bag'), array('id' => '14', 'name' => 'Bags', 'short' => 'Bags', 'address' => '')),
	));

	pg_assert_same(array(
		array('item_type' => 'product_group', 'item_id' => 9, 'name' => 'Mocha Office Chair', 'address_name' => 'mocha-office-chair'),
		array('item_type' => 'product', 'item_id' => 7, 'name' => 'CUP-01', 'address_name' => 'tea-cup'),
		array('item_type' => 'product_group', 'item_id' => 12, 'name' => 'Lamp', 'address_name' => 'lamp'),
	), $targets);

	pg_assert_same(array(), pg_review_request_targets_from_rows(array()), 'no lines');
	pg_assert_same(array(), pg_review_request_targets_from_rows(null), 'a failed query');
}

// Comment Moderation lists all comments, verified purchases only, or the
// rest; anything else posted lists all.
function test_reviews_verified_filter_sql()
{
	pg_assert_same('comments.verified_order_id > 0', pg_review_verified_filter_sql('yes'));
	pg_assert_same('comments.verified_order_id = 0', pg_review_verified_filter_sql('no'));
	pg_assert_same('', pg_review_verified_filter_sql(''), 'all comments');
	pg_assert_same('', pg_review_verified_filter_sql("yes' OR '1'='1"), 'nothing but the two choices');
	pg_assert_same('', pg_review_verified_filter_sql(array('yes')), 'not a string');
}

// Shipping asks for the review once the order_shipped e-mail is started;
// the request keeps the purchase rule and its once-per-order check; deleting
// the order cancels it; the profile screens offer it and the list names it;
// a link whose address is a mail-merge field reaches the reader as written.
function test_reviews_request_paths()
{
	$root = PG_FUNCTIONS_DIR;

	$update = (string) file_get_contents($root . '/update_order.php');
	$shipped = strpos($update, "'action' => 'order_shipped'");
	$request = strpos($update, 'pg_review_request_start(');

	pg_assert_true(($shipped !== false) && ($request !== false) && ($shipped < $request), 'update_order.php asks after the order_shipped campaign');

	$function = new ReflectionFunction('pg_review_request_start');
	$lines = file($function->getFileName());
	$source = implode('', array_slice($lines, $function->getStartLine() - 1, $function->getEndLine() - $function->getStartLine() + 1));

	pg_assert_contains('pg_review_bought_sql()', $source, 'only a bought order');
	pg_assert_contains('orders.user_id > 0', $source, 'only an order placed with an account');
	pg_assert_contains("email_campaigns.action = 'order_review_request'", $source, 'once per order');
	pg_assert_contains('email_campaigns.order_id', $source, 'once per order');

	pg_assert_contains("'order_review_request'", (string) file_get_contents($root . '/delete_order.php'), 'deleting the order cancels it');

	// Both profile screens share one form: it offers the action and treats
	// it as a commerce action (gated, tied to no record, sent after it).
	require_once($root . '/includes/forms/email_campaign_profile_form.php');

	pg_assert_contains('$action_options[lang(\'Order Review Request\')] = \'order_review_request\'', (string) file_get_contents($root . '/includes/forms/email_campaign_profile_form.php'), 'the profile form offers it');
	pg_assert_true(in_array('order_review_request', pg_email_campaign_profile_commerce_actions(), true), 'a commerce action');
	pg_assert_same(array('action_item_id' => '0', 'schedule_period' => 'after', 'schedule_base' => 'action'), pg_email_campaign_profile_action_values('order_review_request', array()), 'saved like the other order actions');

	pg_assert_contains("'order_review_request' => lang('Order Review Request')", (string) file_get_contents($root . '/view_email_campaign_profiles.php'), 'the profile list names it');

	$mail = (string) file_get_contents($root . '/includes/fn/mail.php');

	pg_assert_contains("case 'order_review_request':", $mail, 'the log names it');
	pg_assert_contains('mailto:|http:\/\/|\^\^)', $mail, 'a link to a mail-merge field is not made absolute');

	$comments = (string) file_get_contents($root . '/view_comments.php');

	pg_assert_contains('pg_review_verified_filter_sql(', $comments, 'Comment Moderation filters verified purchases');
	pg_assert_contains("pg_filter_select('verified'", $comments, 'with the toolbar\'s filter select');
}

// Two saves of one order at once both passed the once-per-order check: the
// check and the start run under a lock of the order, released on every path.
function test_reviews_request_lock()
{
	$function = new ReflectionFunction('pg_review_request_start');
	$lines = file($function->getFileName());
	$source = implode('', array_slice($lines, $function->getStartLine() - 1, $function->getEndLine() - $function->getStartLine() + 1));

	$lock = strpos($source, "GET_LOCK('");
	$check = strpos($source, "email_campaigns.action = 'order_review_request'");
	$create = strpos($source, 'create_auto_email_campaigns(');
	$release = strpos($source, "RELEASE_LOCK('");

	pg_assert_contains('$lock = pg_review_request_lock_name($order_id);', $source, 'a lock per order');
	pg_assert_contains("', 5)\") !== 1", $source, 'waits five seconds, gives up when not held');
	pg_assert_true(($lock !== false) && ($check !== false) && ($lock < $check), 'the check runs under the lock');
	pg_assert_true(($create !== false) && ($check < $create), 'the start after the check');
	pg_assert_contains('finally', $source, 'released on every path');
	pg_assert_true(($release !== false) && ($create < $release), 'released after the start');
}

// An order cancelled after it shipped (staff may) loses its pending review
// request: its customer's review form would say "not bought". Only the
// request that cancels the order cancels it.
function test_reviews_request_cancel()
{
	$function = new ReflectionFunction('pg_review_request_cancel');
	$lines = file($function->getFileName());
	$source = implode('', array_slice($lines, $function->getStartLine() - 1, $function->getEndLine() - $function->getStartLine() + 1));

	pg_assert_contains("email_campaigns.status = 'cancelled'", $source);
	pg_assert_contains("email_campaigns.action = 'order_review_request'", $source);
	pg_assert_contains("email_campaigns.status = 'ready'", $source, 'only one not sent yet');
	pg_assert_contains("email_campaigns.status = 'paused'", $source, 'only one not sent yet');
	pg_assert_contains('email_campaigns.order_id', $source);

	$cancellation = new ReflectionFunction('process_order_cancellation');
	$lines = file($cancellation->getFileName());
	$source = implode('', array_slice($lines, $cancellation->getStartLine() - 1, $cancellation->getEndLine() - $cancellation->getStartLine() + 1));

	$write = strpos($source, "AND status <> 'cancelled'");
	$guard = strpos($source, 'mysqli_affected_rows(db::$con) < 1');
	$cancel = strpos($source, 'pg_review_request_cancel($order_id)');

	pg_assert_true(($write !== false) && ($guard !== false) && ($cancel !== false) && ($write < $guard) && ($guard < $cancel), 'process_order_cancellation() cancels it after its own status write');
}

// pg_review_request_lock_name() run in its own process, with DB_DATABASE and
// DB_HOST defined as a site's configuration defines them: the tests after
// this one must not find them defined. Null when PHP cannot be started again.
function reviews_test_lock_name($database, $host, $order_id)
{
	if (!function_exists('exec') || !defined('PHP_BINARY') || (PHP_BINARY === '')) {
		return null;
	}

	$code = "define('PG_TEST_RUNNER', true);"
		. ' require ' . var_export(PG_FUNCTIONS_DIR . '/functions.php', true) . ';'
		. " define('DB_DATABASE', " . var_export($database, true) . ');'
		. " define('DB_HOST', " . var_export($host, true) . ');'
		. ' echo pg_review_request_lock_name(' . var_export($order_id, true) . ');';

	$out = array();
	$status = 1;
	@exec(escapeshellarg(PHP_BINARY) . ' -r ' . escapeshellarg($code) . ' 2>&1', $out, $status);

	return ($status === 0) ? implode("\n", $out) : 'failed: ' . implode("\n", $out);
}

// GET_LOCK names belong to the whole database server, so the lock of an
// order's review request carries the database: two sites on one server do
// not wait for each other's order of the same id.
function test_reviews_request_lock_name()
{
	$name = reviews_test_lock_name('shop', 'db', 42);

	if ($name === null) {
		return;
	}

	pg_assert_same('pg_review_request_' . md5('shop@db') . '_42', $name, 'the database and the order');
	pg_assert_false($name === reviews_test_lock_name('other_shop', 'db', 42), 'another database, another lock');
	pg_assert_same('pg_review_request_' . md5('shop@db') . '_7', reviews_test_lock_name('shop', 'db', "7' OR '1'='1"), 'the order id as a number');
	pg_assert_true(strlen((string) reviews_test_lock_name('shop', 'db', 4294967295)) <= 64, 'within the 64 characters GET_LOCK takes');
}
