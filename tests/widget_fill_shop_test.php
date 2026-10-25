<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the formats of a bound value in the shop widgets (catalog,
 * catalog detail, cart, express order, order view): their fills read a
 * token's %%...%% suffix and never print it, a template without a suffix
 * fills byte for byte as before, and the order view's dates take a date
 * format.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The shop fills before this change: the longest-first str_replace() loop,
// the catalog page tokens' loop in the map's order, and strtr() over a map
// keyed by whole tokens.
function _widget_fill_shop_old_longest($html, $values)
{
	$keys = array_keys($values);
	usort($keys, function ($a, $b) { return strlen($b) - strlen($a); });
	foreach ($keys as $k) {
		$html = str_replace('^^' . $k . '^^', $values[$k], $html);
	}
	return $html;
}

function _widget_fill_shop_old_ordered($html, $values)
{
	foreach ($values as $k => $v) {
		$html = str_replace('^^' . $k . '^^', $v, $html);
	}
	return $html;
}

function _widget_fill_shop_source($name)
{
	$fn = new ReflectionFunction($name);
	$lines = file($fn->getFileName());
	return implode('', array_slice($lines, $fn->getStartLine() - 1, $fn->getEndLine() - $fn->getStartLine() + 1));
}

function _widget_fill_shop_templates()
{
	return array(
		'<a href="^^__url^^" title="^^__t^^">^^__t^^ &amp; ^^__missing^^</a><i>^^__x^^</i>',
		'<span>^^__t_two^^^^__t^^</span> ^^__price^^ ^^__x^^ ^^__t^^',
		'^^^__t^^^ %%d.m.Y%% ^^__t^^ %%x%%',
		'',
		'<p>no token at all</p>',
	);
}

function _widget_fill_shop_values()
{
	// '__x' holds a token: the old loops rescanned it, so the order of the
	// keys shows in the output.
	return array(
		'__x'     => '^^__t^^',
		'__url'   => '/a?b=1&amp;c=2',
		'__t'     => 'A &quot;B&quot; &lt;i&gt;',
		'__t_two' => 'longer key',
		'__price' => '<span class="pg-price">₺12,50</span>',
		'__num'   => 3,
	);
}

// -- byte for byte without a suffix -------------------------------------------

function test_widget_fill_shop_byte_identical_without_suffix()
{
	$values = _widget_fill_shop_values();
	$map = array();
	foreach ($values as $k => $v) {
		$map['^^' . $k . '^^'] = (string) $v;
	}
	foreach (_widget_fill_shop_templates() as $i => $html) {
		pg_assert_same(_widget_fill_shop_old_longest($html, $values), pg_sw_fill_longest($html, $values), 'longest first ' . $i);
		pg_assert_same(_widget_fill_shop_old_ordered($html, $values), _pg_catalog_fill_page_tokens($html, $values), 'page tokens ' . $i);
		pg_assert_same(strtr($html, $map), pg_sw_fill_token_map($html, $map), 'token map ' . $i);
	}
	pg_assert_same('<p>^^__a^^</p>', pg_sw_fill_longest('<p>^^__a^^</p>', array()), 'an empty map changes nothing');
	pg_assert_same('<p>^^__a^^</p>', pg_sw_fill_token_map('<p>^^__a^^</p>', array()), 'an empty token map changes nothing');
	pg_assert_same('<p>^^__a^^</p>', _eo_replace_tokens('<p>^^__a^^</p>', array()), 'the express order\'s fill too');
}

// -- a format is read and never printed ---------------------------------------

function test_widget_fill_shop_reads_the_format()
{
	$values = array('__name' => 'kırmızı &amp; mavi', '__count' => '3', '__price' => '<b>₺12,50</b>');
	$html = '<h2>^^__name^^%%upper%%</h2><p>^^__count^^%%number:2%%</p><p>^^__price^^%%upper%%</p><i>^^__later^^%%lower%%</i>';
	$filled = '<h2>KIRMIZI &amp; MAVI</h2><p>3.00</p><p><b>₺12,50</b></p><i>^^__later^^%%lower%%</i>';

	pg_assert_same($filled, pg_sw_fill_longest($html, $values), 'longest first');
	pg_assert_same($filled, _pg_catalog_fill_page_tokens($html, $values), 'page tokens');
	$map = array();
	foreach ($values as $k => $v) {
		$map['^^' . $k . '^^'] = $v;
	}
	pg_assert_same($filled, pg_sw_fill_token_map($html, $map), 'token map');
	pg_assert_same($filled, _eo_replace_tokens($html, $map), 'express order');

	// The token left for a later pass is filled there with its format, and
	// the sweep takes an unanswered one with its suffix.
	pg_assert_same('<i>ankara</i>', pg_sw_fill_longest('<i>^^__later^^%%lower%%</i>', array('__later' => 'ANKARA')), 'a later pass');
	pg_assert_same('<i></i>', pg_sw_sweep_tokens('<i>^^__later^^%%lower%%</i>'), 'the sweep');
}

// -- the order view's dates ---------------------------------------------------

function test_widget_fill_shop_order_view_dates()
{
	$ts = mktime(9, 5, 0, 11, 3, 2026);
	pg_assert_same(array(
		'__order_date'          => array('type' => 'date and time', 'data' => '2026-11-03 09:05:00'),
		'__arrival_date'        => array('type' => 'date', 'data' => '2026-11-10'),
		'__withdrawal_deadline' => array('type' => 'date', 'data' => '2026-11-24'),
	), _pg_sw_order_view_dates($ts, '2026-11-10', '2026-11-24 00:00:00'));
	pg_assert_same(array(), _pg_sw_order_view_dates(0, '0000-00-00', ''), 'no dates');
	pg_assert_same(array(), _pg_sw_order_view_dates('x', 'tomorrow', null), 'nothing that reads as a date');

	if (!defined('DATE_FORMAT')) {
		define('DATE_FORMAT', 'day_month');
	}
	if (!defined('TIME_FORMAT')) {
		define('TIME_FORMAT', 'twenty_four_hours');
	}
	pg_assert_same(
		'<b>10.11.2026</b> <i>10 Nov</i>',
		pg_sw_fill_longest('<b>^^__arrival_date^^%%d.m.Y%%</b> <i>^^__arrival_date^^</i>', array('__arrival_date' => '10 Nov'), _pg_sw_order_view_dates(0, '2026-11-10', '')),
		'a date format on the arrival date, the configured one without'
	);
}

// -- the renderers use the fills ----------------------------------------------

function test_widget_fill_shop_renderers_read_formats()
{
	$renderers = array(
		'_render_system_widget_catalog_listing'     => 2,
		'_render_system_widget_catalog_item_view'   => 2,
		'_render_system_widget_shopping_cart'       => 3,
		'_render_system_widget_order_view'          => 3,
	);
	foreach ($renderers as $name => $count) {
		$code = _widget_fill_shop_source($name);
		pg_assert_false(strpos($code, "str_replace('^^' . \$k . '^^'") !== false, $name . ' has no fill of its own');
		pg_assert_false(strpos($code, 'str_replace($tok,') !== false, $name . ' has no fill of its own');
		pg_assert_same($count, substr_count($code, '= pg_sw_fill_longest('), $name . ' fills through pg_sw_fill_longest()');
	}
	pg_assert_same(2, substr_count(_widget_fill_shop_source('_render_system_widget_catalog_listing'), '_pg_catalog_fill_page_tokens('), 'the listing\'s page tokens');
	pg_assert_same(2, substr_count(_widget_fill_shop_source('_render_system_widget_order_view'), ', $_ov_typed)'), 'the order view types its dates');
	pg_assert_false(strpos(_widget_fill_shop_source('_eo_render_recipient_loop'), 'strtr(') !== false, 'the recipient loop fills through _eo_replace_tokens()');
	pg_assert_contains('pg_sw_fill_token_map(', _widget_fill_shop_source('_eo_replace_tokens'));
}
