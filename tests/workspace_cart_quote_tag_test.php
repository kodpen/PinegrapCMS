<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Workspace tag of a cart quote (<#cart_quote:N>,
 * includes/workspace/refs.php): what its chip shows a reader with the
 * ecommerce right (number, whom it was made for, how many lines, where it
 * stands now, an open quote past its last day as expired) and what it shows
 * anybody else (the kind alone), and the one-quote view of the cart quote
 * list the chip opens (view_cart_quotes.php?id=), which draws the record's
 * Workspace button for that quote alone. Where the kind is listed beside the
 * other record kinds: tests/workspace_record_kinds_test.php.
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

// A cart quote as the tag's query reads it.
function workspace_cart_quote_tag_test_row(array $over = array())
{
	return array_merge(array(
		'id' => '12', 'status' => 'open', 'valid_until' => '0',
		'first_name' => 'Ayşe', 'last_name' => 'Yılmaz', 'company' => 'Kodpen', 'line_count' => '3',
	), $over);
}

// The chip names the quote by its number, whom it was made for and its
// lines, and opens that quote on the cart quote list.
function test_workspace_cart_quote_tag_names_the_quote()
{
	$ref = ws_cart_quote_ref(workspace_cart_quote_tag_test_row(), 1760000000, '/pg/');
	$lines = lang(array('string' => '{var:1} item(s)', 'vars' => array(3)));

	pg_assert_same(lang('Cart quote') . ' #12', $ref['label'], 'the number');
	pg_assert_same('Ayşe Yılmaz · ' . $lines, $ref['meta'], 'the contact\'s name and the lines');
	pg_assert_same('/pg/view_cart_quotes.php?id=12', $ref['url'], 'the quote alone on the list');
	pg_assert_same(pg_cart_quote_state_label('open'), $ref['status'], 'where it stands');

	$company = ws_cart_quote_ref(workspace_cart_quote_tag_test_row(array('first_name' => ' ', 'last_name' => '')), 1760000000, '/pg/');
	pg_assert_same('Kodpen · ' . $lines, $company['meta'], 'a contact without a name: the company');

	$nobody = ws_cart_quote_ref(workspace_cart_quote_tag_test_row(array('first_name' => null, 'last_name' => null, 'company' => null, 'line_count' => '1')), 1760000000, '/pg/');
	pg_assert_same(lang(array('string' => '{var:1} item(s)', 'vars' => array(1))), $nobody['meta'], 'made for nobody named, or a contact gone: the lines alone');
}

// Nothing writes the expired status, so the chip works it out as the cart
// quote list does: an open quote past its last day is expired.
function test_workspace_cart_quote_tag_state_as_the_list_shows_it()
{
	$now = 1760000000;
	$state = function (array $over) use ($now) {
		return ws_cart_quote_ref(workspace_cart_quote_tag_test_row($over), $now, '/pg/')['status'];
	};

	pg_assert_same(pg_cart_quote_state_label('expired'), $state(array('valid_until' => (string) ($now - 1))), 'open, past its last day');
	pg_assert_same(pg_cart_quote_state_label('open'), $state(array('valid_until' => (string) $now)), 'open, its last second');
	pg_assert_same(pg_cart_quote_state_label('open'), $state(array('valid_until' => '0')), 'open, no end');
	pg_assert_same(pg_cart_quote_state_label('ordered'), $state(array('status' => 'ordered', 'valid_until' => (string) ($now - 1))), 'ordered stays ordered');
	pg_assert_same(pg_cart_quote_state_label('cancelled'), $state(array('status' => 'odd')), 'an unknown status reads as cancelled');
}

// A reader without the ecommerce right sees the kind and nothing that tells
// them more; the quote is read only where e-commerce runs and the tables
// are there. The branch runs a query, so it is read from source.
function test_workspace_cart_quote_tag_hidden_without_the_right()
{
	$source = pg_test_function_source('ws_refs_resolve');
	$start = strpos($source, "if (!empty(\$ids['cart_quote']))");
	pg_assert_true($start !== false, 'ws_refs_resolve() has a cart quote branch');

	$end = strpos($source, 'if (!empty($ids[', (int) $start + 1);
	$branch = substr($source, (int) $start, ($end === false) ? strlen($source) : $end - (int) $start);
	$branch = implode('', array_map('pg_test_source_text', pg_test_source_tokens($branch)));

	pg_assert_contains('pg_cart_quotes_ready()', $branch, 'read only once the tables are there');
	pg_assert_contains("ECOMMERCE === true", $branch, 'read only where e-commerce runs');
	pg_assert_contains("if (\$viewer['ecommerce'])", $branch, 'the chip asks the ecommerce right');
	pg_assert_contains("ws_cart_quote_ref(\$row, \$now, \$base)", $branch, 'drawn by ws_cart_quote_ref()');
	pg_assert_contains("\$put('cart_quote', \$row['id'], lang('Cart quote'), '', '', '', false, 'bi-cart3');", $branch, 'without the right: the kind alone, no number, no link');
}

// The chip's address is a screen of one quote: view_cart_quotes.php reads
// ?id= and draws the record's Workspace button only then, so a list of up
// to 200 quotes counts no conversations.
function test_workspace_cart_quote_tag_opens_one_quote_with_its_workspace_button()
{
	$screen = (string) file_get_contents(PG_FUNCTIONS_DIR . '/view_cart_quotes.php');

	pg_assert_contains("\$quote_id = is_scalar(\$_GET['id'] ?? null) ? max(0, (int) \$_GET['id']) : 0;", $screen, 'the screen reads ?id=');
	pg_assert_contains('pg_cart_quote_panel_rows($contact_id, PG_CART_QUOTE_PANEL_LIMIT, $quote_id)', $screen, 'and lists that quote alone');
	pg_assert_true((bool) preg_match("/if \\(\\(\\\$quote_id > 0\\) && \\\$rows && [^\\n]*\\) \\{\\s*require_once\\([^\\n]*workspace\\/bootstrap\\.php'\\);\\s*\\\$output_workspace_button = ws_record_button\\(\\\$user, 'cart_quote', \\\$quote_id, /", $screen), 'the Workspace button only for one quote that is there');

	pg_assert_contains("q.id = '\" . (int) \$quote_id . \"'", pg_test_function_source('pg_cart_quote_panel_rows'), 'the list narrowed to the quote');
}
