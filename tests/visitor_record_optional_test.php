<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for requests that come without a visitor record. The record is made
 * on the first page view of a person (bots never get one), so a currency
 * picker post that comes straight in, or a counter sale rung up from the
 * panel, has no visitor id in the session. The currency picker, the first
 * cart line and the cart, checkout, express order, order history and custom
 * form screens used to update the visitor row with that missing id, which
 * wrote an "Undefined array key" warning and changed no row. They now skip
 * the update. The currency picker also sends the visitor back when the post
 * has no currency (that used to be a blank page).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Every "UPDATE visitors" in these files sits under an if that checks the
// visitor id first.
function test_visitor_record_optional_updates_are_guarded()
{
	$files = array(
		'update_currency.php',
		'includes/fn/ecommerce.php',
		'shopping_cart.php',
		'billing_information.php',
		'submit_order.php',
		'express_order.php',
		'retrieve_order.php',
		'order_history_retrieve_order.php',
		'order_history_reorder.php',
		'custom_form.php',
	);

	foreach ($files as $file) {
		$source = file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
		$offset = 0;
		$updates = 0;

		while (($position = strpos($source, 'UPDATE visitors', $offset)) !== false) {
			$updates++;
			$offset = $position + 1;

			$before = substr($source, 0, $position);
			$if = strrpos($before, 'if (');
			$condition = ($if === false) ? '' : strtok(substr($before, $if), "\n");

			pg_assert_contains("!empty(\$_SESSION['software']['visitor_id'])", $condition, $file . ' update ' . $updates);
		}

		pg_assert_true($updates > 0, $file . ': no visitor update found');
	}
}

// The redirect is outside the currency branch, so a post without a currency
// goes back to send_to like every other post.
function test_visitor_record_optional_currency_post_always_redirects()
{
	$source = file_get_contents(PG_FUNCTIONS_DIR . '/update_currency.php');

	pg_assert_contains("\nheader('Location: ' . URL_SCHEME . HOSTNAME . pg_safe_redirect_path((\$_REQUEST['send_to'] ?? '')));\nexit();", $source, 'redirect at the top level');
	pg_assert_contains("\$currency_code = \$row['code'] ?? '';", $source, 'unknown currency id');
}

// The utm tags are in the session only after a page view that found them in
// the URL or the cookie, so the order screens read them as optional.
function test_visitor_record_optional_utm_tags_are_optional()
{
	foreach (array('retrieve_order.php', 'order_history_retrieve_order.php') as $file) {
		$source = file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);

		pg_assert_contains("if (!empty(\$_SESSION['software']['utm_source'])) {", $source, $file);
		pg_assert_false(strpos($source, "if (\$_SESSION['software']['utm_source']) {") !== false, $file . ': bare utm_source read');
	}

	$source = file_get_contents(PG_FUNCTIONS_DIR . '/order_history_reorder.php');
	preg_match_all("/\\\$_SESSION\['software'\]\['(utm_[a-z]+|http_referer)'\]( \?\? '')?/", $source, $reads, PREG_SET_ORDER);

	pg_assert_same(6, count($reads), 'order_history_reorder.php: tracking reads');

	foreach ($reads as $read) {
		pg_assert_true(isset($read[2]) && ($read[2] !== ''), 'order_history_reorder.php: ' . $read[1] . ' has no default');
	}
}
