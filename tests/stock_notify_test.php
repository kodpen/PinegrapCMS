<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for includes/fn/stock_notify.php and stock_notify.php: when a product
 * can be asked for and when it is back, the address a request keeps, the
 * answer a request gets, which announcements wake the sender, the notice's
 * link and words, the trim, and the places outside the module that know the
 * requests (the announcers, the general job, account and product deletion,
 * the personal data export, the endpoint's order of checks).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(__DIR__ . '/helpers/source_reader.php');

// A product row as the module reads it.
function stock_notify_test_row($enabled, $inventory, $quantity, $backorder = 0)
{
	return array(
		'enabled'            => (string) $enabled,
		'inventory'          => (string) $inventory,
		'inventory_quantity' => (string) $quantity,
		'backorder'          => (string) $backorder,
	);
}

// Only a product the visitor cannot buy for want of stock can be asked for.
function test_stock_notify_product_out()
{
	pg_assert_true(pg_stock_notify_product_out(stock_notify_test_row(1, 1, 0)), 'tracked, none left');
	pg_assert_true(pg_stock_notify_product_out(stock_notify_test_row(1, 1, -2)), 'tracked, below zero');
	pg_assert_false(pg_stock_notify_product_out(stock_notify_test_row(1, 1, 0, 1)), 'back order: it can be bought');
	pg_assert_false(pg_stock_notify_product_out(stock_notify_test_row(1, 1, 3)), 'in stock');
	pg_assert_false(pg_stock_notify_product_out(stock_notify_test_row(1, 0, 0)), 'not tracked: never out');
	pg_assert_false(pg_stock_notify_product_out(stock_notify_test_row(0, 1, 0)), 'switched off');
	pg_assert_false(pg_stock_notify_product_out(null), 'no product');
	pg_assert_false(pg_stock_notify_product_out(array()), 'an empty row');
}

// A product is back when the catalog shows it and it can be bought from stock.
function test_stock_notify_product_back()
{
	pg_assert_true(pg_stock_notify_product_back(stock_notify_test_row(1, 1, 2)), 'tracked, in stock');
	pg_assert_true(pg_stock_notify_product_back(stock_notify_test_row(1, 0, 0)), 'tracking switched off');
	pg_assert_false(pg_stock_notify_product_back(stock_notify_test_row(1, 1, 0)), 'still none left');
	pg_assert_false(pg_stock_notify_product_back(stock_notify_test_row(1, 1, 0, 1)), 'back order is not stock');
	pg_assert_false(pg_stock_notify_product_back(stock_notify_test_row(0, 1, 5)), 'switched off');
	pg_assert_false(pg_stock_notify_product_back(null), 'no product');
}

// The address a request keeps: trimmed, valid, within the column.
function test_stock_notify_email()
{
	pg_assert_same('ayse@example.com', pg_stock_notify_email('  ayse@example.com '), 'trimmed');
	pg_assert_same('', pg_stock_notify_email('ayse@'), 'not an address');
	pg_assert_same('', pg_stock_notify_email(''), 'empty');
	pg_assert_same('', pg_stock_notify_email(array('ayse@example.com')), 'an array');
	pg_assert_same('', pg_stock_notify_email(null), 'null');
	pg_assert_same('', pg_stock_notify_email(str_repeat('a', 89) . '@example.com'), '101 characters: over VARCHAR(100)');
	pg_assert_same(str_repeat('a', 88) . '@example.com', pg_stock_notify_email(str_repeat('a', 88) . '@example.com'), '100 characters');
	pg_assert_same('', pg_stock_notify_email("ayse@example.com\nBcc: x@example.com"), 'a header line');
}

// What a request comes to, checked in this order.
function test_stock_notify_request_status()
{
	$out = stock_notify_test_row(1, 1, 0);

	pg_assert_same('unavailable', pg_stock_notify_request_status(false, 'a@example.com', $out, false, 0), 'no table yet');
	pg_assert_same('email', pg_stock_notify_request_status(true, '', $out, false, 0), 'no address');
	pg_assert_same('product', pg_stock_notify_request_status(true, 'a@example.com', null, false, 0), 'no product');
	pg_assert_same('product', pg_stock_notify_request_status(true, 'a@example.com', stock_notify_test_row(0, 1, 0), false, 0), 'switched off');
	pg_assert_same('in_stock', pg_stock_notify_request_status(true, 'a@example.com', stock_notify_test_row(1, 1, 4), false, 0), 'it can be bought now');
	pg_assert_same('in_stock', pg_stock_notify_request_status(true, 'a@example.com', stock_notify_test_row(1, 1, 0, 1), false, 0), 'back order');
	pg_assert_same('already', pg_stock_notify_request_status(true, 'a@example.com', $out, true, 0), 'asked before');
	pg_assert_same('already', pg_stock_notify_request_status(true, 'a@example.com', $out, true, PG_STOCK_NOTIFY_PENDING_LIMIT), 'asked before, at the limit');
	pg_assert_same('limit', pg_stock_notify_request_status(true, 'a@example.com', $out, false, PG_STOCK_NOTIFY_PENDING_LIMIT), 'too many waiting');
	pg_assert_same('added', pg_stock_notify_request_status(true, 'a@example.com', $out, false, PG_STOCK_NOTIFY_PENDING_LIMIT - 1), 'one below the limit');
	pg_assert_same('phone', pg_stock_notify_request_status(true, 'a@example.com', $out, false, 0, false), 'a number no text reaches');
	pg_assert_same('email', pg_stock_notify_request_status(true, '', $out, false, 0, false), 'the address first');
	pg_assert_same('added', pg_stock_notify_request_status(true, 'a@example.com', $out, false, 0, true), 'a good number, or none');
	pg_assert_same('added', pg_stock_notify_request_status(true, 'a@example.com', $out, false, 0), 'first request');
}

// Every answer has a sentence for the visitor; a refusal is not a notice.
function test_stock_notify_status_message()
{
	foreach (array('added', 'already', 'in_stock', 'product', 'email', 'phone', 'limit', 'unavailable', 'nonsense') as $status) {
		$message = pg_stock_notify_status_message($status);
		pg_assert_true(is_string($message) && ($message !== ''), 'a sentence for ' . $status);
	}

	// The visitor is told that one message comes, and how.
	pg_assert_same(lang('We will send you one e-mail when this product is back in stock.'), pg_stock_notify_status_message('added'));
	pg_assert_same(lang('We will send you one text message when this product is back in stock.'), pg_stock_notify_status_message('added', true));

	pg_assert_true(pg_stock_notify_status_ok('added'), 'added is good news');
	pg_assert_true(pg_stock_notify_status_ok('already'), 'already is good news');
	pg_assert_false(pg_stock_notify_status_ok('in_stock'), 'in stock is not a request kept');
	pg_assert_false(pg_stock_notify_status_ok('limit'), 'limit');
	pg_assert_false(pg_stock_notify_status_ok(''), 'empty');
}

// Only an inventory.changed that leaves the product buyable wakes the sender.
function test_stock_notify_heard_product()
{
	pg_assert_same(12, pg_stock_notify_heard_product('inventory.changed', array('product_id' => 12, 'quantity' => 3, 'out_of_stock' => false)), 'back in stock');
	pg_assert_same(12, pg_stock_notify_heard_product('inventory.changed', array('product_id' => '12', 'quantity' => 0, 'out_of_stock' => false)), 'not tracked any more');
	pg_assert_same(0, pg_stock_notify_heard_product('inventory.changed', array('product_id' => 12, 'quantity' => 0, 'out_of_stock' => true)), 'still out');
	pg_assert_same(0, pg_stock_notify_heard_product('stock.low', array('product_id' => 12, 'quantity' => 1)), 'another event');
	pg_assert_same(0, pg_stock_notify_heard_product('inventory.changed', array('quantity' => 3, 'out_of_stock' => false)), 'no product');
	pg_assert_same(0, pg_stock_notify_heard_product('inventory.changed', array('product_id' => array(12), 'out_of_stock' => false)), 'not an id');
	pg_assert_same(0, pg_stock_notify_heard_product('inventory.changed', 'nope'), 'not a payload');
	pg_assert_same(0, pg_stock_notify_heard_product('inventory.changed', array('product_id' => 12, 'quantity' => 4, 'out_of_stock' => false, 'source' => 'checkout')), 'a sale takes stock away');
	pg_assert_same(0, pg_stock_notify_heard_product('inventory.changed', array('product_id' => 12, 'quantity' => 4, 'out_of_stock' => false, 'source' => 'local_sale')), 'a local sale too');
	pg_assert_same(12, pg_stock_notify_heard_product('inventory.changed', array('product_id' => 12, 'quantity' => 4, 'out_of_stock' => false, 'source' => 'panel')), 'a product screen save');
}

// The product's address on the site, as a mail carries it.
function test_stock_notify_url()
{
	pg_assert_same('https://shop.test/urun/blue-shirt', pg_stock_notify_url('https://shop.test/', 'urun', 'blue-shirt'), 'detail page and product');
	pg_assert_same('https://shop.test/sub/urun/blue%20shirt', pg_stock_notify_url('https://shop.test/sub', 'urun', 'blue shirt'), 'encoded');
	pg_assert_same('', pg_stock_notify_url('https://shop.test/', '', 'blue-shirt'), 'no detail page');
	pg_assert_same('', pg_stock_notify_url('https://shop.test/', 'urun', ' '), 'no address name');
}

// The notice: the product's name, a link when there is one, escaped.
function test_stock_notify_mail()
{
	$mail = pg_stock_notify_mail('Shirt <b>blue</b> & "red"', 'https://shop.test/urun/shirt?a=1&b=2');

	pg_assert_contains('Shirt <b>blue</b> & "red"', $mail['subject'], 'subject names the product as text');
	pg_assert_contains('Shirt &lt;b&gt;blue&lt;/b&gt; &amp; &quot;red&quot;', $mail['body'], 'body escapes the name');
	pg_assert_false(strpos($mail['body'], '<b>blue</b>') !== false, 'no markup from the name');
	pg_assert_contains('href="https://shop.test/urun/shirt?a=1&amp;b=2"', $mail['body'], 'the link, escaped');

	$plain = pg_stock_notify_mail('Shirt', '');

	pg_assert_false(strpos($plain['body'], '<a ') !== false, 'no link without an address');
	pg_assert_contains('Shirt', $plain['body'], 'still names the product');
	$broken = pg_stock_notify_mail("A\r\nBcc: x@example.com", '');

	pg_assert_false(strpbrk($broken['subject'], "\r\n") !== false, 'a line break in the name stays out of the subject');
}

// The trim removes notices sent a while ago and requests nobody answered in a
// year, in batches.
function test_stock_notify_trim_queries()
{
	$now = 1800000000;
	$queries = pg_stock_notify_trim_queries($now, 500);

	pg_assert_same(2, count($queries), 'two statements');
	pg_assert_contains("(sent_at > 0) AND (sent_at < '" . ($now - PG_STOCK_NOTIFY_KEEP_SENT) . "')", $queries[0], 'sent ones after their keep');
	pg_assert_contains("(sent_at = 0) AND (created_at < '" . ($now - PG_STOCK_NOTIFY_KEEP_WAITING) . "')", $queries[1], 'waiting ones after a year');

	foreach ($queries as $query) {
		pg_assert_contains('DELETE FROM stock_notify', $query, 'deletes');
		pg_assert_contains('LIMIT 500', $query, 'in batches');
	}

	pg_assert_true(PG_STOCK_NOTIFY_KEEP_WAITING >= 365 * 86400, 'a request waits at least a year');
}

// Deleting an account or a product takes its requests with it.
function test_stock_notify_forget_sql()
{
	pg_assert_same("DELETE FROM stock_notify WHERE user_id = '7'", pg_stock_notify_forget_sql('user_id', 7), 'an account');
	pg_assert_same("DELETE FROM stock_notify WHERE product_id = '9'", pg_stock_notify_forget_sql('product_id', '9'), 'a product');
	pg_assert_same('', pg_stock_notify_forget_sql('user_id', 0), 'a guest request is never taken by id 0');
	pg_assert_same('', pg_stock_notify_forget_sql('user_id', array(7)), 'not an id');
	pg_assert_same('', pg_stock_notify_forget_sql('email_address', 7), 'only the two columns');
}

// The sender claims a row before its mail, so two restocks at once mail once,
// and hands the mail to the queue.
function test_stock_notify_send_claims_before_mailing()
{
	$source = pg_test_function_source('pg_stock_notify_send');

	$claim = strpos($source, 'AND (sent_at = 0)');
	$mail = strpos($source, 'email(');

	pg_assert_true($claim !== false, 'claims only an unsent row');
	pg_assert_true(($mail !== false) && ($claim < $mail), 'claims before mailing');
	pg_assert_contains('mysqli_affected_rows', $source, 'mails only the row it claimed');
	pg_assert_contains("'queue'", $source, 'queued when the job runs');
	pg_assert_contains('pg_stock_notify_product_back(', $source, 'reads the stock again first');
}

// A failure while listening must not break the write that announced.
function test_stock_notify_heard_never_breaks_the_writer()
{
	$source = pg_test_function_source('pg_stock_notify_heard');

	pg_assert_contains('pg_error_throws(true)', $source, 'a failed query throws');
	pg_assert_contains('catch (Throwable', $source, 'and is caught');
	pg_assert_contains('finally', $source, 'the old setting comes back');
}

// Every announcer of inventory.changed reaches the listener.
function test_stock_notify_heard_from_every_announcer()
{
	pg_assert_contains('pg_stock_notify_heard(', pg_test_function_source('pg_announce'), 'pg_announce: the site writers, the Workspace, ERP documents');

	$api = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/api/resources/inventory.php');
	$enqueue = strpos($api, "api_webhook_enqueue('inventory.changed'");
	$heard = strpos($api, 'pg_stock_notify_heard(');

	pg_assert_true(($enqueue !== false) && ($heard !== false) && ($heard > $enqueue), 'the stock API, after its announcement');
}

// The places outside the module that know the requests.
function test_stock_notify_outside_the_module()
{
	pg_assert_contains('pg_stock_notify_trim_daily();', (string) file_get_contents(PG_FUNCTIONS_DIR . '/job.php'), 'the general job trims daily');
	pg_assert_contains('pg_stock_notify_forget_user(', (string) file_get_contents(PG_FUNCTIONS_DIR . '/delete_users.php'), 'deleting accounts');
	pg_assert_contains('pg_stock_notify_forget_user(', (string) file_get_contents(PG_FUNCTIONS_DIR . '/edit_user.php'), 'deleting one account');
	pg_assert_contains('pg_stock_notify_forget_product(', pg_test_function_source('pg_pb_delete_product'), 'deleting a product for good');

	pg_assert_true(array_key_exists('stock_notify', pg_privacy_export_sections()), 'an export section');

	$collect = pg_test_function_source('pg_privacy_export_collect');

	pg_assert_contains("\$sections['stock_notify']", $collect, 'filled by the export');
	pg_assert_contains("_pg_privacy_export_in('stock_notify.email_address', \$emails)", $collect, 'by the address typed');
	pg_assert_contains("_pg_privacy_export_in('stock_notify.user_id', \$user_ids)", $collect, 'by the account');
}

// The endpoint refuses before it writes: the size of the body, the method,
// the form token, the rate.
function test_stock_notify_endpoint_order()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/stock_notify.php');

	$discarded = strpos($source, 'pg_post_body_discarded()');
	$method = strpos($source, "!== 'POST'");
	$token = strpos($source, 'hash_equals(');
	$rate = strpos($source, 'pg_stock_notify_throttled()');
	$write = strpos($source, 'pg_stock_notify_request(');

	pg_assert_true($discarded !== false, 'an over-size body is no missing token');
	pg_assert_true(($method !== false) && ($method < $token), 'POST only, before anything else');
	pg_assert_true(($token !== false) && ($token < $write), 'the token before the write');
	pg_assert_true(($rate !== false) && ($rate < $write), 'the rate before the write');
	pg_assert_contains('pg_safe_redirect_path(', $source, 'back to a path on the site');
	pg_assert_contains("new liveform('catalog_detail')", $source, "the notice shows where the product page's messages are");
}

// The number a request keeps for the text message: a mobile one in the
// international form, nothing else.
function test_stock_notify_phone()
{
	pg_assert_same('905321234567', pg_stock_notify_phone('0532 123 45 67'), 'a Turkish mobile');
	pg_assert_same('905321234567', pg_stock_notify_phone(' +90 (532) 123-45-67 '), 'with its country code');
	pg_assert_same('', pg_stock_notify_phone('0212 123 45 67'), 'a landline takes no text');
	pg_assert_same('', pg_stock_notify_phone(''), 'none typed');
	pg_assert_same('', pg_stock_notify_phone(array('0532 123 45 67')), 'not a string');
}

// The text message is a transactional one: its own non-commercial kind, off
// until switched on, naming the product and its address and nothing else.
function test_stock_notify_sms_event()
{
	$kinds = pg_sms_kinds();
	pg_assert_true(isset($kinds['stock']) && ($kinds['stock'] === false), 'not commercial: no İYS consent needed');

	$events = pg_sms_message_events();
	pg_assert_same('stock', $events['stock_back']['kind']);
	pg_assert_same(array('store', 'product', 'url'), $events['stock_back']['fields']);
	pg_assert_false(pg_sms_message_normalize(null)['stock_back']['on'], 'off until switched on');

	$fields = pg_stock_notify_sms_fields("Blue\r\nmug", 'https://example.com/shop/blue-mug');
	pg_assert_same('Blue mug', $fields['product'], 'one line');
	pg_assert_same(
		'Shop: Blue mug, which you asked us about, is back in stock. https://example.com/shop/blue-mug',
		pg_sms_message_render('^^store^^: ^^product^^, which you asked us about, is back in stock. ^^url^^', $fields + array('store' => 'Shop')));
	pg_assert_same('Shop: Blue mug, which you asked us about, is back in stock.',
		pg_sms_message_render('^^store^^: ^^product^^, which you asked us about, is back in stock. ^^url^^', pg_stock_notify_sms_fields('Blue mug', '') + array('store' => 'Shop')),
		'no address, no gap');
}

// The sender texts a row with a number instead of mailing it, once per row
// (the reference), reads the kept digits back with the plus, and mails when
// the text cannot be queued; the request keeps a number only while texts go.
function test_stock_notify_sms_wiring()
{
	$send = pg_test_function_source('pg_stock_notify_send');

	pg_assert_contains('stock_notify.phone', $send, 'the number is read');
	pg_assert_contains('pg_stock_notify_phone(pg_sms_dial(', $send, 'read back with the plus');
	pg_assert_contains("pg_sms_message_queue('stock_back', pg_sms_dial(\$phone)", $send, 'queued, never sent in the request');
	pg_assert_contains("'stock:' . (int) \$row['id']", $send, 'one text per request');
	pg_assert_true(strpos($send, 'pg_sms_message_queue(') < strpos($send, 'email(array('), 'the text first, the mail when it is not queued');

	$request = pg_test_function_source('pg_stock_notify_request');
	pg_assert_contains('pg_stock_notify_sms_on()', $request, 'a number is kept only while texts go');
	pg_assert_contains("e(\$phone)", $request, 'and written');

	$endpoint = (string) file_get_contents(PG_FUNCTIONS_DIR . '/stock_notify.php');
	pg_assert_contains("'phone'", $endpoint, 'the endpoint reads the number');
}
