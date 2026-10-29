<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for "New cart quote" on a Workspace channel's customer card
 * (includes/workspace/customer.php, assets/js/workspace_customer.js): the
 * card carries the new cart quote screen's address with the customer's
 * contact filled in only for a reader with the ecommerce right, and only for
 * a contact that reader may see; the right is asked before the contact is
 * looked at; the card writes nothing and draws a plain link; the cart quote
 * stays out of the channel menu and apart from the sales quote.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/bootstrap.php');
require_once(__DIR__ . '/helpers/source_reader.php');

// A Workspace member, with or without the ecommerce right.
function workspace_customer_cart_quote_test_viewer($ecommerce)
{
	return array('id' => 4, 'role' => 3, 'member' => true, 'ecommerce' => $ecommerce, 'erp' => true);
}

// One record of a customer as ws_customer_identity_item() names it.
function workspace_customer_cart_quote_test_item($type, $id, $known)
{
	return array('type' => $type, 'id' => $id, 'kind' => '', 'label' => $type . ' ' . $id, 'detail' => '', 'known' => $known, 'url' => $known ? '/p/' . $type . '/' . $id : '');
}

// A customer as ws_customer_identity() hands it over: the record, the ties
// the reader sees (only those) and the contact the customer stands for.
function workspace_customer_cart_quote_test_identity($customer, $links, $contact_id)
{
	return array(
		'customer'        => $customer,
		'links'           => $links,
		'contact_id'      => $contact_id,
		'user_ids'        => array(),
		'erp_account_ids' => array(),
	);
}

// The source of a loaded function with its whitespace and comments taken
// out, so a test can look for a statement however it is laid out.
function workspace_customer_cart_quote_test_compact($function)
{
	$compact = '';

	foreach (pg_test_source_tokens(pg_test_function_source($function)) as $token) {
		if (!is_array($token) || ($token[0] !== T_WHITESPACE)) {
			$compact .= pg_test_source_text($token);
		}
	}

	return $compact;
}

// A channel about a contact opens the screen on that contact; about a user
// or a current account, on the contact it is tied to.
function test_workspace_customer_cart_quote_url_opens_on_the_customers_contact()
{
	$viewer = workspace_customer_cart_quote_test_viewer(true);

	pg_assert_same('/p/add_cart_quote.php?contact_id=12', ws_customer_cart_quote_url($viewer, workspace_customer_cart_quote_test_identity(
		workspace_customer_cart_quote_test_item('contact', 12, true), array(), 12), '/p/'), 'the contact itself');

	pg_assert_same('/p/add_cart_quote.php?contact_id=21', ws_customer_cart_quote_url($viewer, workspace_customer_cart_quote_test_identity(
		workspace_customer_cart_quote_test_item('user_account', 3, true),
		array(workspace_customer_cart_quote_test_item('contact', 21, true), workspace_customer_cart_quote_test_item('erp_account', 7, true)), 21), '/p/'), 'a user tied to its contact');

	pg_assert_same('/p/add_cart_quote.php?contact_id=21', ws_customer_cart_quote_url($viewer, workspace_customer_cart_quote_test_identity(
		workspace_customer_cart_quote_test_item('erp_account', 7, true),
		array(workspace_customer_cart_quote_test_item('contact', 21, true)), 21), '/p/'), 'a current account tied to its contact');
}

// Without the ecommerce right (add_cart_quote.php's right, e-commerce on)
// nothing is offered, whatever the customer.
function test_workspace_customer_cart_quote_url_needs_the_ecommerce_right()
{
	$identity = workspace_customer_cart_quote_test_identity(workspace_customer_cart_quote_test_item('contact', 12, true), array(), 12);

	pg_assert_same('', ws_customer_cart_quote_url(workspace_customer_cart_quote_test_viewer(false), $identity, '/p/'), 'no ecommerce right');
	pg_assert_same('', ws_customer_cart_quote_url(array('id' => 4, 'role' => 3, 'member' => true), $identity, '/p/'), 'a reader built without module rights');
}

// A contact the reader may not see (outside a user role's contact groups:
// the card names it "Contact" only) is not opened, nor is a customer
// without a contact, nor a contact the identity does not stand for.
function test_workspace_customer_cart_quote_url_needs_a_contact_the_reader_sees()
{
	$viewer = workspace_customer_cart_quote_test_viewer(true);

	pg_assert_same('', ws_customer_cart_quote_url($viewer, workspace_customer_cart_quote_test_identity(
		workspace_customer_cart_quote_test_item('contact', 12, false), array(), 12), '/p/'), 'a contact the reader may not see');

	pg_assert_same('', ws_customer_cart_quote_url($viewer, workspace_customer_cart_quote_test_identity(
		workspace_customer_cart_quote_test_item('user_account', 3, true), array(), 21), '/p/'), 'a tied contact the reader may not see is not among the ties');

	pg_assert_same('', ws_customer_cart_quote_url($viewer, workspace_customer_cart_quote_test_identity(
		workspace_customer_cart_quote_test_item('erp_account', 7, true), array(), 0), '/p/'), 'a current account without a contact');

	pg_assert_same('', ws_customer_cart_quote_url($viewer, workspace_customer_cart_quote_test_identity(
		workspace_customer_cart_quote_test_item('user_account', 12, true),
		array(workspace_customer_cart_quote_test_item('erp_account', 12, true)), 12), '/p/'), 'a user or an account id is never a contact id');

	pg_assert_same('', ws_customer_cart_quote_url($viewer, workspace_customer_cart_quote_test_identity(
		workspace_customer_cart_quote_test_item('user_account', 3, true),
		array(workspace_customer_cart_quote_test_item('contact', 22, true)), 21), '/p/'), 'only the contact the customer stands for');
}

// The right is asked before the contact is looked at, so a reader without
// it is told nothing about the customer's contact; the address is worked
// out from the identity the card has read already, with no query, and only
// opens the screen, which makes the quote and shows its link once: the
// Workspace writes nothing. The card adds the address for its reader, and
// asks whether the cart quote tables are there only once one is offered.
// Read from the source for the order: a call shows what comes out, not
// what was looked at first.
function test_workspace_customer_cart_quote_url_asks_the_right_first_and_writes_nothing()
{
	$compact = workspace_customer_cart_quote_test_compact('ws_customer_cart_quote_url');
	$body = (string) substr($compact, (int) strpos($compact, '{'));
	$gate = "{if(empty(\$viewer['ecommerce'])){return'';}";

	pg_assert_same($gate, substr($body, 0, strlen($gate)), 'the address opens with the right and stops without it');

	foreach (array('INSERT', 'UPDATE', 'db(', 'db_q(', 'db_value(', 'db_item', 'pg_cart_quote_create(', 'pg_cart_quote_new_link(') as $call) {
		pg_assert_false(strpos($compact, $call) !== false, 'reads and writes nothing: ' . $call);
	}

	$card = workspace_customer_cart_quote_test_compact('ws_customer_card');
	$url = strpos($card, 'ws_customer_cart_quote_url($viewer,$identity,');
	$ready = strpos($card, 'pg_cart_quotes_ready()');

	pg_assert_true($url !== false, 'the card asks for the address for its reader');
	pg_assert_contains("\$card['cart_quote_url']=", $card, 'and hands it to the screen');
	pg_assert_true(($url !== false) && ($ready !== false) && ($url < $ready), 'the tables are asked after the right and the contact');
}

// The card draws "New cart quote" as a plain link to the address the server
// built, in its header beside "Remind me", only when an address came; it
// asks nothing of the server and makes nothing in the channel.
function test_workspace_customer_cart_quote_link_on_the_card()
{
	$script = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace_customer.js');
	$start = strpos($script, 'function cartQuoteLink(h, url) {');

	pg_assert_true($start !== false, 'the card has the link');

	$link = (string) substr($script, (int) $start, (int) strpos($script, "\n    }\n", (int) $start) - (int) $start);

	pg_assert_contains("h.el('a', ", $link, 'a link, not a button opening a form');
	pg_assert_contains('link.href = url;', $link, 'to the address the server built');
	pg_assert_contains("h.t('cust_cart_quote_new')", $link, 'named New cart quote');
	pg_assert_false(strpos($link, 'h.api(') !== false, 'it asks nothing of the server');

	pg_assert_true((bool) preg_match('/if \(data\.card\.cart_quote_url\) \{\s*actions\.insertBefore\(cartQuoteLink\(h, data\.card\.cart_quote_url\), actions\.firstChild\);/', $script), 'drawn in the header only when the card brings an address');
	pg_assert_false(strpos($script, 'cart_quote_create') !== false, 'the card never makes a quote itself');

	$strings = ws_customer_js_strings();

	pg_assert_same(lang('New cart quote'), $strings['cust_cart_quote_new'] ?? '', 'the screen\'s words');
}

// A cart quote is never a sales quote: it is not offered where "New sales
// quote" is (the channel menu, whose address comes with the channel), and
// the channel's details carry no cart quote address.
function test_workspace_customer_cart_quote_apart_from_the_sales_quote()
{
	$script = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace.js');
	$start = strpos($script, 'channelMenu: function () {');
	$menu = (string) substr($script, (int) $start, (int) strpos($script, 'wrap.appendChild(menu);', (int) $start) - (int) $start);

	pg_assert_true($start !== false, 'channelMenu() is in workspace.js');
	pg_assert_false(stripos($menu, 'cart_quote') !== false, 'no cart quote in the channel menu');
	pg_assert_false(strpos(pg_test_function_source('ws_channel_detail'), 'cart_quote') !== false, 'the channel\'s details carry no cart quote address');
}
