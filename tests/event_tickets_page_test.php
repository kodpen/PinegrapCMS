<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the holder's tickets page and the my account tickets
 * (includes/fn/event_tickets_page.php, widgets_event_tickets.php): the
 * guest's name from a product form, a ticket's view (its code drawn again,
 * no QR for a void ticket or a code that does not match), the cards, the
 * account rows with their signed links, and the wiring of tickets.php.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/event_tickets_page.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/widgets_event_tickets.php');

function event_tickets_page_test_lacks($needle, $haystack, $label = '')
{
	pg_assert_false(strpos((string) $haystack, (string) $needle) !== false, $label);
}

function event_tickets_page_test_key()
{
	return str_repeat('ab', 32);
}

// A ticket row as pg_event_tickets_page_rows() reads it, its hash drawn
// under the test key.
function event_tickets_page_test_row($overrides = array())
{
	$row = $overrides + array(
		'id'                => 9,
		'order_item_id'     => 501,
		'unit'              => 2,
		'product_id'        => 77,
		'calendar_event_id' => 12,
		'recurrence_number' => 0,
		'code_salt'         => str_repeat('c', 32),
		'state'             => 'valid',
		'used_at'           => 0,
		'product_title'     => 'Concert ticket',
	);

	if (!isset($row['code_hash'])) {
		$row['code_hash'] = pg_event_ticket_code_hash(pg_event_ticket_code($row['order_item_id'], $row['unit'], $row['code_salt'], event_tickets_page_test_key()));
	}

	return $row;
}

function event_tickets_page_test_context($overrides = array())
{
	return $overrides + array(
		'event'   => array('name' => 'Jazz night', 'when' => 'Friday, 4 December, 2026 20:00 - 23:00', 'place' => 'Main hall', 'ics_url' => '/tickets.php?o=1&t=x&ics=12-0'),
		'guest'   => '',
		'buyer'   => 'Alex Morgan',
		'number'  => 2,
		'count'   => 4,
		'used_on' => '',
	);
}

function test_event_tickets_page_guest_name_from_the_product_form()
{
	pg_assert_same('Deniz Kaya', pg_event_ticket_guest_name(array(
		array('name' => 'email', 'data' => 'deniz@example.com', 'contact_field' => 'email_address'),
		array('name' => 'first', 'data' => 'Deniz', 'contact_field' => 'first_name'),
		array('name' => 'last', 'data' => 'Kaya', 'contact_field' => 'last_name'),
	)), 'the first and last name fields mapped to the contact');

	pg_assert_same('Sam Carter', pg_event_ticket_guest_name(array(
		array('name' => 'meal', 'data' => 'Vegan', 'contact_field' => ''),
		array('name' => 'guest_name', 'data' => ' Sam Carter ', 'contact_field' => ''),
	)), 'a field whose variable name holds a name');

	pg_assert_same('Ayşe Yılmaz', pg_event_ticket_guest_name(array(
		array('name' => 'ad_soyad', 'data' => 'Ayşe Yılmaz', 'contact_field' => ''),
	)), 'a Turkish name field');

	pg_assert_same('', pg_event_ticket_guest_name(array(
		array('name' => 'username_hint', 'data' => 'x', 'contact_field' => ''),
		array('name' => 'meal', 'data' => 'Vegan', 'contact_field' => ''),
	)), 'no name asked');
}

function test_event_tickets_page_view_draws_the_code_again()
{
	$key = event_tickets_page_test_key();
	$view = pg_event_ticket_view(event_tickets_page_test_row(), $key, event_tickets_page_test_context());

	pg_assert_true($view['show_code'], 'a valid ticket shows its code');
	pg_assert_same(pg_event_ticket_code(501, 2, str_repeat('c', 32), $key), $view['code'], 'the code drawn from the row');
	pg_assert_same(1, preg_match('/^[0-9A-Z]{4}-[0-9A-Z]{4}-[0-9A-Z]{4}$/', $view['code']), 'three groups of four');
	pg_assert_same('Jazz night', $view['title'], 'the event names the ticket');
	pg_assert_same('Alex Morgan', $view['name'], 'the buyer stands in without a guest');
	pg_assert_same('Deniz Kaya', pg_event_ticket_view(event_tickets_page_test_row(), $key, event_tickets_page_test_context(array('guest' => 'Deniz Kaya')))['name'], 'the guest first');
	pg_assert_same('Concert ticket', pg_event_ticket_view(event_tickets_page_test_row(array('calendar_event_id' => 0)), $key, event_tickets_page_test_context(array('event' => array())))['title'], 'a product ticket keeps the product');
}

function test_event_tickets_page_no_qr_for_void_or_mismatched()
{
	$key = event_tickets_page_test_key();
	$void = pg_event_ticket_view(event_tickets_page_test_row(array('state' => 'void')), $key, event_tickets_page_test_context());
	pg_assert_false($void['show_code'], 'a void ticket shows no code');
	pg_assert_same('', $void['code'], 'and carries none');
	pg_assert_same('', $void['ics_url'], 'nor a calendar file');

	$other = pg_event_ticket_view(event_tickets_page_test_row(), str_repeat('cd', 32), event_tickets_page_test_context());
	pg_assert_false($other['show_code'], 'a code drawn under another key is not shown');

	$bad = pg_event_ticket_view(event_tickets_page_test_row(array('code_hash' => str_repeat('0', 64))), $key, event_tickets_page_test_context());
	pg_assert_false($bad['show_code'], 'a hash that is not the row\'s');

	pg_assert_same('void', pg_event_ticket_view(event_tickets_page_test_row(array('state' => 'odd')), $key, event_tickets_page_test_context())['state'], 'an unknown state is read as void');

	$used = pg_event_ticket_view(event_tickets_page_test_row(array('state' => 'used', 'used_at' => 1700000000)), $key, event_tickets_page_test_context(array('used_on' => '4/12/2026 20:15')));
	pg_assert_true($used['show_code'], 'a used ticket still shows its code');
	pg_assert_same('4/12/2026 20:15', $used['used_on'], 'and when it was checked in');
}

function test_event_tickets_page_cards()
{
	$key = event_tickets_page_test_key();
	$valid = pg_event_ticket_view(event_tickets_page_test_row(), $key, event_tickets_page_test_context());
	$html = pg_event_ticket_card_html($valid);

	pg_assert_contains('<svg', $html, 'the QR is inline SVG');
	event_tickets_page_test_lacks('<img', $html, 'never a picture at an address');
	pg_assert_contains(h($valid['code']), $html, 'the code is written out');
	pg_assert_contains('Main hall', $html, 'the place');
	pg_assert_contains('ics=12-0', $html, 'Add to calendar');

	$void = pg_event_ticket_card_html(pg_event_ticket_view(event_tickets_page_test_row(array('state' => 'void')), $key, event_tickets_page_test_context()));
	event_tickets_page_test_lacks('<svg', $void, 'a void ticket draws no QR');
	pg_assert_contains('pg-ticket-void', $void, 'and is marked');

	$escaped = pg_event_ticket_card_html(pg_event_ticket_view(event_tickets_page_test_row(), $key,
		event_tickets_page_test_context(array('guest' => '<b>x</b>', 'event' => array('name' => '"><script>', 'when' => '', 'place' => '', 'ics_url' => '')))));
	event_tickets_page_test_lacks('<script>', $escaped, 'the event name is escaped');
	event_tickets_page_test_lacks('<b>x</b>', $escaped, 'the guest name is escaped');

	$page = pg_event_tickets_page_html(array('order_number' => '12340'), array($valid, $valid));
	pg_assert_contains('window.print()', $page, 'Print');
	pg_assert_same(2, substr_count($page, '<article'), 'a card per ticket');
}

function test_event_tickets_page_account_rows_carry_signed_links()
{
	$key = event_tickets_page_test_key();
	$view = pg_event_tickets_account_view(array(
		array('id' => 41, 'order_number' => '12340', 'order_date' => 1763460000, 'tickets' => 4, 'valid' => 4, 'titles' => 'Jazz night'),
		array('id' => 38, 'order_number' => '12288', 'order_date' => 1759400000, 'tickets' => 2, 'valid' => 0, 'titles' => 'Autumn concert'),
	), $key, '/tickets.php', 'j/n/Y');

	pg_assert_same(2, count($view), 'one row per order');
	pg_assert_same('/tickets.php?o=41&t=' . pg_event_tickets_link_token(41, $key), $view[0]['url'], 'the signed link');
	pg_assert_true(pg_event_tickets_link_token_valid(41, substr($view[0]['url'], strpos($view[0]['url'], '&t=') + 3), $key), 'its token opens the page');
	pg_assert_same('success', $view[0]['tone'], 'valid tickets');
	pg_assert_same('secondary', $view[1]['tone'], 'none valid');
	pg_assert_same(array(), pg_event_tickets_account_view(array(array('id' => 41, 'order_number' => '1', 'order_date' => 0, 'tickets' => 1, 'valid' => 1, 'titles' => '')), '', '/tickets.php', 'j/n/Y'), 'no link without the key');

	$html = pg_event_tickets_account_html($view);
	pg_assert_contains(h($view[0]['url']), $html, 'the section links each order');
	pg_assert_same('', pg_event_tickets_account_html(array()), 'no section without tickets');

	$rows = pg_sw_list_account_ticket_rows(7, $view);
	pg_assert_same(array(7), array_keys($rows['account.tickets']), 'filed under the account');
	pg_assert_same($view[1]['url'], $rows['account.tickets'][7][1]['__tickets_url'], 'the row carries the link');
}

function test_event_tickets_page_wiring()
{
	$page = (string) file_get_contents(PG_FUNCTIONS_DIR . '/tickets.php');
	pg_assert_contains('pg_event_tickets_page_find(', $page, 'the link is checked');
	pg_assert_contains("Referrer-Policy: no-referrer", $page, 'the signed address is not passed on');
	pg_assert_contains('no-store', $page, 'nor kept by a cache');
	pg_assert_contains('noindex', $page, 'nor indexed');

	$find = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/event_tickets_page.php');
	pg_assert_true(strpos($find, 'pg_event_tickets_link_token_valid(') < strpos($find, 'FROM orders'), 'the token is checked before the order is read');

	$extras = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/widgets_account.php');
	pg_assert_contains("\$out['flags']['has_tickets']", $extras, 'my account sets the flag');
	pg_assert_contains("\$out['sections']['tickets']", $extras, 'and the section');

	$lists = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/widgets_lists_order.php');
	pg_assert_contains("'account.tickets' => array(", $lists, 'the list is registered');

	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');
	pg_assert_contains("'account.tickets': {", $js, 'the designer knows the list');
	pg_assert_contains("function _sdAccountTicketsHtml(", $js, 'and draws the section\'s twin');
	pg_assert_contains("['has_tickets',", $js, 'and the flag');
}
