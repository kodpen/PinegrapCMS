<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the edit form item view screen's layout
 * (includes/forms/form_item_view_form.php): the Summary rows, the variable
 * lists, the wide layout's columns and the screen drawing them.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/forms/form_item_view_form.php');

function test_form_item_view_form_summary()
{
	$html = pg_form_item_view_form_summary(array(
		'page_name' => 'Order <detail>',
		'form_name' => 'Contact & Quote',
		'collection' => 'b',
		'changed' => '<time>now</time> admin',
	));
	pg_assert_contains('Order &lt;detail&gt;', $html, 'the page name is escaped');
	pg_assert_contains('Contact &amp; Quote', $html, 'the form name is escaped');
	pg_assert_contains('>B</dd>', $html, 'the collection reads as a capital letter');
	pg_assert_contains('<time>now</time> admin', $html, 'the last change is printed as given');
	pg_assert_same(3, substr_count($html, 'border-bottom'), 'every row but the last draws a line');

	$empty = pg_form_item_view_form_summary(array('page_name' => 'P', 'form_name' => '', 'collection' => 'a', 'changed' => ''));
	pg_assert_contains('fg-secondary', $empty, 'no form reads as a muted None');
	pg_assert_contains('>-</dd>', $empty, 'no last change reads as a dash');
}

function test_form_item_view_form_variables()
{
	$help = array(
		'fields' => array(),
		'standard' => array(array('token' => '^^reference_code^^', 'label' => 'Reference Code', 'type_label' => '', 'example' => '')),
		'mail' => array(),
		'syntax' => array('conditional' => '[[^^x^^]]', 'date' => '^^d^^%%Y%%', 'checkbox' => '^^c^^%%Yes|No%%'),
	);
	$html = pg_form_item_view_form_variables($help);
	pg_assert_contains('id="pg_fiv_variables"', $html, 'the click handler finds the card by its id');
	pg_assert_contains('data-pg-insert="^^reference_code^^"', $html, 'a system field inserts its token');
	pg_assert_contains('data-pg-insert="^^c^^%%Yes|No%%"', $html, 'a format sample inserts its syntax');
	pg_assert_contains('list-group-item py-1 px-3 small fg-secondary', $html, 'a form without fields says so');

	$item = pg_form_item_view_form_variable_items(array(array('token' => '^^date^^', 'label' => 'Date', 'type_label' => 'Date', 'example' => '^^date^^%%Y%%')));
	pg_assert_same(2, substr_count($item, 'data-pg-insert='), 'a field with a format gets a second button');
}

function test_form_item_view_form_wide_layout()
{
	$html = pg_form_item_view_form_layout(
		pg_form_item_view_form_card('<p>^^x^^</p>', '', '')
		. pg_form_item_view_form_status('', ''),
		'<button>Save</button>');
	pg_assert_contains('col-12 lg:col-8 2xl:col-9 order-1', $html, 'the form column');
	pg_assert_contains('col-12 lg:col-4 2xl:col-3 order-2', $html, 'the status column');
	pg_assert_contains('#pg_fiv_status { position: sticky; top: 3.3rem;', $html, 'the status box sticks below the top bar');
	pg_assert_contains('@media (min-width: 1024px)', $html, 'only where the columns stand side by side');
	pg_assert_contains('col-12 order-3 position-sticky', $html, 'the sticky save bar');
	pg_assert_contains('&lt;p&gt;^^x^^&lt;/p&gt;</textarea>', $html, 'the layout is escaped in its textarea');
	pg_assert_true(strpos($html, 'order-1') < strpos($html, 'order-2'), 'the status column comes after the form column');

	$screen = (string) file_get_contents(PG_FUNCTIONS_DIR . '/edit_form_item_view.php');
	pg_assert_contains('pg_form_item_view_form_layout(', $screen, 'the screen draws the shared layout');
	pg_assert_true(strpos($screen, 'md:col">') === false, 'the old single column is gone');
}
