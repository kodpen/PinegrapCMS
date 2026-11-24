<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Preview's case model (includes/fn/designer_preview_as.php):
 * the cases each widget kind has, the visitors a page offers, the choice
 * read back from the browser, and what a case sets for one widget (record,
 * visitor, flags).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_preview_as_case_and_visitor_lists()
{
	pg_assert_same(array('typical', 'empty', 'long', 'sold_out', 'error'), pg_preview_as_cases());
	pg_assert_same(array('signed_out', 'signed_in', 'member', 'first_visit', 'returning'), pg_preview_as_visitors());
}

function test_preview_as_kind_cases_lists()
{
	pg_assert_same(array('typical', 'empty', 'long'), pg_preview_as_kind_cases('form_list_view'));
	pg_assert_same(array('typical', 'empty', 'long'), pg_preview_as_kind_cases('blog_list'));
	pg_assert_same(array('typical', 'empty', 'long'), pg_preview_as_kind_cases('search_results'));
	pg_assert_same(array('typical', 'empty', 'long'), pg_preview_as_kind_cases('comments_recent'));
	pg_assert_same(array('typical', 'empty', 'long', 'sold_out'), pg_preview_as_kind_cases('catalog_listing'));
}

function test_preview_as_kind_cases_records_and_carts()
{
	pg_assert_same(array('typical', 'long'), pg_preview_as_kind_cases('form_item_view'));
	pg_assert_same(array('typical', 'long'), pg_preview_as_kind_cases('calendar_event_view'));
	pg_assert_same(array('typical', 'long', 'sold_out'), pg_preview_as_kind_cases('catalog_item_view'));
	pg_assert_same(array('typical', 'empty', 'long'), pg_preview_as_kind_cases('shopping_cart'));
	pg_assert_same(array('typical', 'empty', 'long'), pg_preview_as_kind_cases('order_preview'));
}

function test_preview_as_kind_cases_forms_and_others()
{
	pg_assert_same(array('typical', 'error'), pg_preview_as_kind_cases('custom_form'));
	pg_assert_same(array('typical', 'error'), pg_preview_as_kind_cases('login_form'));
	pg_assert_same(array('typical', 'error'), pg_preview_as_kind_cases('registration'));
	pg_assert_same(array('typical'), pg_preview_as_kind_cases('menu_region'));
	pg_assert_same(array('typical'), pg_preview_as_kind_cases('no_such_kind'));
	pg_assert_same(array('typical'), pg_preview_as_kind_cases(''));
	pg_assert_same(array('typical'), pg_preview_as_kind_cases(null));
}

function test_preview_as_record_source()
{
	pg_assert_same('form', pg_preview_as_record_source('form_item_view'));
	pg_assert_same('product', pg_preview_as_record_source('catalog_item_view'));
	pg_assert_same('event', pg_preview_as_record_source('calendar_event_view'));
	pg_assert_same('', pg_preview_as_record_source('catalog_listing'));
	pg_assert_same('', pg_preview_as_record_source(array()));
}

function test_preview_as_page_cases_union_in_fixed_order()
{
	pg_assert_same(array('typical'), pg_preview_as_page_cases(array()));
	pg_assert_same(array('typical'), pg_preview_as_page_cases(array('menu_region')));
	pg_assert_same(
		array('typical', 'empty', 'long', 'sold_out', 'error'),
		pg_preview_as_page_cases(array('custom_form', 'catalog_item_view', 'shopping_cart'))
	);
	pg_assert_same(array('typical', 'long'), pg_preview_as_page_cases(array('form_item_view', 'form_item_view')));
	pg_assert_same(array('typical'), pg_preview_as_page_cases('form_list_view'));
}

function test_preview_as_page_visitors()
{
	pg_assert_same(array('signed_out', 'signed_in'), pg_preview_as_page_visitors(array()));
	pg_assert_same(array('signed_out', 'signed_in', 'member'), pg_preview_as_page_visitors(array('access_rules' => true)));
	pg_assert_same(array('signed_out', 'signed_in', 'first_visit', 'returning'), pg_preview_as_page_visitors(array('audience' => true)));
	pg_assert_same(
		array('signed_out', 'signed_in', 'member', 'first_visit', 'returning'),
		pg_preview_as_page_visitors(array('access_rules' => 1, 'audience' => '1'))
	);
	pg_assert_same(array('signed_out', 'signed_in'), pg_preview_as_page_visitors('x'));
}

function test_preview_as_choice_defaults()
{
	$default = array('record' => 0, 'visitor' => 'signed_out', 'case' => 'typical');
	pg_assert_same($default, pg_preview_as_choice(null, array(), array()));
	pg_assert_same($default, pg_preview_as_choice('junk', array(), array()));
	pg_assert_same($default, pg_preview_as_choice(array(), array('form_item_view'), array()));
}

function test_preview_as_choice_keeps_what_the_page_offers()
{
	$choice = pg_preview_as_choice(
		array('record' => '42', 'visitor' => 'member', 'case' => 'long'),
		array('form_item_view'),
		array('access_rules' => true)
	);
	pg_assert_same(array('record' => 42, 'visitor' => 'member', 'case' => 'long'), $choice);
}

function test_preview_as_choice_drops_what_the_page_lacks()
{
	// No access rules: no member; no list: no empty case; a record id that
	// is not a whole positive number is no record.
	$choice = pg_preview_as_choice(
		array('record' => '-3', 'visitor' => 'member', 'case' => 'empty'),
		array('form_item_view'),
		array()
	);
	pg_assert_same(array('record' => 0, 'visitor' => 'signed_out', 'case' => 'typical'), $choice);

	$choice = pg_preview_as_choice(array('record' => '4x', 'visitor' => array(), 'case' => 'EMPTY'), array('form_list_view'), array());
	pg_assert_same(array('record' => 0, 'visitor' => 'signed_out', 'case' => 'typical'), $choice);

	$choice = pg_preview_as_choice(array('record' => 1.5, 'visitor' => 'returning'), array(), array('audience' => true));
	pg_assert_same(array('record' => 0, 'visitor' => 'returning', 'case' => 'typical'), $choice);
}

function test_preview_as_choice_drops_a_record_on_a_page_without_one()
{
	$choice = pg_preview_as_choice(array('record' => 9), array('catalog_listing'), array());
	pg_assert_same(0, $choice['record']);
	$choice = pg_preview_as_choice(array('record' => 9), array('catalog_item_view'), array());
	pg_assert_same(9, $choice['record']);
}

function test_preview_as_setup_typical_record_widget()
{
	$choice = array('record' => 0, 'visitor' => 'signed_out', 'case' => 'typical');
	pg_assert_same(
		array('case' => 'typical', 'record' => array('source' => 'form', 'pick' => 'default', 'id' => 0), 'visitor' => 'signed_out', 'flags' => array()),
		pg_preview_as_setup('form_item_view', $choice)
	);

	$choice['record'] = 7;
	$choice['visitor'] = 'signed_in';
	pg_assert_same(
		array('case' => 'typical', 'record' => array('source' => 'product', 'pick' => 'chosen', 'id' => 7), 'visitor' => 'signed_in', 'flags' => array()),
		pg_preview_as_setup('catalog_item_view', $choice)
	);
}

function test_preview_as_setup_long_and_sold_out_record_widget()
{
	$choice = array('record' => 7, 'visitor' => 'signed_out', 'case' => 'long');
	pg_assert_same(
		array('case' => 'long', 'record' => array('source' => 'product', 'pick' => 'longest', 'id' => 0), 'visitor' => 'signed_out', 'flags' => array('long')),
		pg_preview_as_setup('catalog_item_view', $choice)
	);

	$choice['case'] = 'sold_out';
	pg_assert_same(
		array('case' => 'sold_out', 'record' => array('source' => 'product', 'pick' => 'sold_out', 'id' => 0), 'visitor' => 'signed_out', 'flags' => array('sold_out')),
		pg_preview_as_setup('catalog_item_view', $choice)
	);
}

function test_preview_as_setup_lists()
{
	$choice = array('record' => 0, 'visitor' => 'signed_out', 'case' => 'empty');
	pg_assert_same(
		array('case' => 'empty', 'record' => null, 'visitor' => 'signed_out', 'flags' => array('empty')),
		pg_preview_as_setup('catalog_listing', $choice)
	);
	$choice['case'] = 'sold_out';
	pg_assert_same(array('sold_out'), pg_preview_as_setup('catalog_listing', $choice)['flags']);
	$choice['case'] = 'long';
	pg_assert_same(array('long'), pg_preview_as_setup('shopping_cart', $choice)['flags']);
}

function test_preview_as_setup_form_error()
{
	$choice = array('record' => 0, 'visitor' => 'signed_in', 'case' => 'error');
	pg_assert_same(
		array('case' => 'error', 'record' => null, 'visitor' => 'signed_in', 'flags' => array('form_error')),
		pg_preview_as_setup('custom_form', $choice)
	);
}

function test_preview_as_setup_case_the_kind_lacks_is_typical()
{
	// The page's case is one the widget does not have: the widget is drawn
	// as it is on a typical visit, beside the widget the case is for.
	$choice = array('record' => 0, 'visitor' => 'signed_out', 'case' => 'error');
	pg_assert_same(
		array('case' => 'typical', 'record' => null, 'visitor' => 'signed_out', 'flags' => array()),
		pg_preview_as_setup('menu_region', $choice)
	);
	$choice['case'] = 'sold_out';
	$setup = pg_preview_as_setup('form_item_view', $choice);
	pg_assert_same('typical', $setup['case']);
	pg_assert_same('default', $setup['record']['pick']);
}

function test_preview_as_setup_reads_a_raw_choice_safely()
{
	pg_assert_same(
		array('case' => 'typical', 'record' => null, 'visitor' => 'signed_out', 'flags' => array()),
		pg_preview_as_setup('catalog_listing', 'junk')
	);
	$setup = pg_preview_as_setup('calendar_event_view', array('record' => '12', 'visitor' => 'nobody', 'case' => 'long'));
	pg_assert_same('signed_out', $setup['visitor']);
	pg_assert_same(array('source' => 'event', 'pick' => 'longest', 'id' => 0), $setup['record']);
}
