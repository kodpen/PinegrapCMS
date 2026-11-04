<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the booking page the Salon, Clinic and Law design templates
 * carry beside their request form: a public page that places the booking
 * widget (kind booking, the editor's starter layout), the request page and
 * the booking page link each other, and the template's privacy page has
 * the name the widget's consent box looks for.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Template id => array(the request form's page key, the booking page key).
function _test_booking_tpl_list()
{
	return array(
		'pinegrap-salon'  => array('appointment', 'book_online'),
	);
}

function _test_booking_tpl($id)
{
	static $cache = array();
	if (!isset($cache[$id])) $cache[$id] = _pg_tpl_include(PG_FUNCTIONS_DIR . '/includes/design_templates/' . $id . '.php');
	return $cache[$id];
}

function _test_booking_tpl_page($tpl, $key)
{
	foreach ($tpl['pages'] as $p) {
		if ($p['key'] === $key) return $p;
	}
	return null;
}

// Every templateWidget key a tree places.
function _test_booking_tpl_placed($node, &$out)
{
	if (!is_array($node)) return;
	if (isset($node['props']['templateWidget'])) $out[] = $node['props']['templateWidget'];
	if (!empty($node['children']) && is_array($node['children'])) {
		foreach ($node['children'] as $child) _test_booking_tpl_placed($child, $out);
	}
}

function test_booking_templates_place_the_widget()
{
	foreach (_test_booking_tpl_list() as $id => $keys) {
		list($form_key, $book_key) = $keys;
		$tpl = _test_booking_tpl($id);
		$page = _test_booking_tpl_page($tpl, $book_key);
		pg_assert_true($page !== null, "$id: a booking page");
		if ($page === null) continue;
		pg_assert_same('public', $page['folder'], "$id: the booking page is public");
		pg_assert_true(empty($page['form']), "$id: the booking page is not a form page");

		$placed = array();
		_test_booking_tpl_placed($page['tree'], $placed);
		$booking = array();
		foreach ($placed as $wkey) {
			pg_assert_true(isset($tpl['widgets'][$wkey]), "$id: placed widget $wkey is defined");
			if (isset($tpl['widgets'][$wkey]) && $tpl['widgets'][$wkey]['config']['regionType'] === 'booking') $booking[] = $wkey;
		}
		pg_assert_same(1, count($booking), "$id: the booking page places one booking widget");
		if (!$booking) continue;
		$w = $tpl['widgets'][$booking[0]];
		pg_assert_same($book_key, $w['page'], "$id: the widget belongs to the booking page");
		pg_assert_same('starter', $w['tree'], "$id: the editor's starter layout");
		pg_assert_true(!isset($w['requires']), "$id: the widget needs no shop");

		// The request form stays and the two pages point at each other.
		$form = _test_booking_tpl_page($tpl, $form_key);
		pg_assert_true($form !== null && !empty($form['form']), "$id: the request form stays");
		pg_assert_true(strpos(json_encode($form['tree']), '{{page:' . $book_key . '}}') !== false, "$id: the request page links the booking page");
		pg_assert_true(strpos(json_encode($page['tree']), '{{page:' . $form_key . '}}') !== false, "$id: the booking page links the request page");
	}
}

// The consent box links the page named lang('privacy-policy')
// (pg_booking_widget_privacy_url()); each template ships it.
function test_booking_templates_privacy_page_name()
{
	foreach (array_keys(_test_booking_tpl_list()) as $id) {
		$names = array();
		foreach (_test_booking_tpl($id)['pages'] as $p) $names[] = $p['name'];
		pg_assert_true(in_array(lang('privacy-policy'), $names, true), "$id: a page named privacy-policy");
	}
}
