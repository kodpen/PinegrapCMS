<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the shipped quantity heading of the customer's view order screen
 * (includes/fn/order_view.php): the word goes through lang(), so a Turkish
 * site does not show the English "(Shipped)".
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_order_view_shipped_heading_cell_is_translated()
{
	pg_assert_same('<th style="text-align: left">(' . h(lang('Shipped')) . ')</th>', pg_order_view_shipped_heading_cell(), 'the heading word comes from lang()');
}

function test_view_order_screen_has_no_bare_shipped_heading()
{
	$source = file_get_contents(dirname(__DIR__) . '/pinegrap/get_view_order_screen_content.php');

	pg_assert_same(false, strpos($source, '>(Shipped)<'), 'no hard-coded English heading left');
	pg_assert_contains('pg_order_view_shipped_heading_cell()', $source, 'the screen prints the translated cell');
}
