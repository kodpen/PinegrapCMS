<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for a link outside a widget kept by page id (props._bindings.href =
 * '__page_link', props._bindParams.href = {page_id, params}): the saved body
 * keeps the typed href as the fallback and names the page in
 * data-pg-page-link (_apply_bindings()), and the drawn page puts the page's
 * current address in its place (pg_page_link_expand_html()), so a rename
 * reaches the link without rewriting the tree.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function _page_link_by_id_props($page_id, $href = '/old-name')
{
	return array(
		'href' => $href,
		'_bindings' => array('href' => '__page_link'),
		'_bindParams' => array('href' => array('page_id' => $page_id, 'params' => array())),
	);
}

function _page_link_by_id_attr($props)
{
	foreach ((isset($props['_attrs']) ? $props['_attrs'] : array()) as $attr) {
		if (is_array($attr) && isset($attr['name']) && $attr['name'] === 'data-pg-page-link') {
			return $attr['value'];
		}
	}

	return null;
}

function test_page_link_by_id_outside_widget_keeps_fallback()
{
	$props = _apply_bindings(_page_link_by_id_props(12));
	pg_assert_same('/old-name', $props['href'], 'the typed href stays as the fallback');
	pg_assert_same('12', _page_link_by_id_attr($props), 'the page is named for the drawn page');

	$props = _apply_bindings(_page_link_by_id_props('12'));
	pg_assert_same('12', _page_link_by_id_attr($props), 'digits');

	foreach (array(0, 'tab:k', -3, 'x', null) as $page_id) {
		$props = _apply_bindings(_page_link_by_id_props($page_id));
		pg_assert_same('/old-name', $props['href'], var_export($page_id, true));
		pg_assert_same(null, _page_link_by_id_attr($props), 'no page: ' . var_export($page_id, true));
	}

	$props = _page_link_by_id_props(12, '');
	$props['_attrs'] = array(array('name' => 'href', 'value' => '/attrs-name'), array('name' => 'data-pg-page-link', 'value' => '99'));
	$props = _apply_bindings($props);
	pg_assert_same('', $props['href'], 'an empty prop leaves the _attrs href to print');
	pg_assert_same(array(
		array('name' => 'href', 'value' => '/attrs-name'),
		array('name' => 'data-pg-page-link', 'value' => '12'),
	), $props['_attrs'], 'one page attribute, the bound one');
}

function test_page_link_by_id_inside_widget_prints_hash()
{
	pg_sw_finish_open(array('sid' => 3, 'kind' => 'form_item_view'));

	try {
		$props = _apply_bindings(_page_link_by_id_props(12));
	} finally {
		pg_sw_finish_close();
	}

	pg_assert_same('#', $props['href'], 'a sentinel no widget expanded prints # as before');
	pg_assert_same(null, _page_link_by_id_attr($props));
}

function test_page_link_by_id_rendered_link()
{
	$node = array('type' => 'content', 'props' => array('contentType' => 'link', 'text' => 'About') + _page_link_by_id_props(7, '/about-us'), 'children' => array());
	$html = _render_tree_node($node, 0);
	pg_assert_contains('href="/about-us"', $html);
	pg_assert_contains('data-pg-page-link="7"', $html);
	pg_assert_same(array(7), pg_page_link_ids($html));

	$out = pg_page_link_expand_html($html, array(7 => '/about'));
	pg_assert_contains('href="/about"', $out, 'the current address');
	pg_assert_true(strpos($out, 'data-pg-page-link') === false, 'the marker is gone');
	pg_assert_true(strpos($out, '/about-us') === false, 'the fallback is replaced');
}

function test_page_link_by_id_expand_html()
{
	pg_assert_same(array(), pg_page_link_ids('<a href="/x">x</a>'));
	pg_assert_same(array(3, 4), pg_page_link_ids('<a href="/a" data-pg-page-link="3">a</a><a data-pg-page-link="4" href="/b">b</a><a href="/c" data-pg-page-link="3">c</a>'));

	$html = '<a class="nav-link" href="/old" data-pg-page-link="3">a</a>';
	pg_assert_same('<a class="nav-link" href="/new&amp;more">a</a>', pg_page_link_expand_html($html, array(3 => '/new&more')), 'escaped once');
	pg_assert_same('<a class="nav-link" href="/old">a</a>', pg_page_link_expand_html($html, array(3 => '')), 'a missing or binned page keeps the fallback');
	pg_assert_same('<a class="nav-link" href="/old">a</a>', pg_page_link_expand_html($html, array()), 'a page not read keeps the fallback');

	pg_assert_same('<a href="/p" class="b">a</a>', pg_page_link_expand_html('<a data-pg-page-link="3" class="b">a</a>', array(3 => '/p')), 'a tag without an href gets one');
	pg_assert_same('<A HREF="/p">a</A>', pg_page_link_expand_html('<A HREF="/o" DATA-PG-PAGE-LINK="3">a</A>', array(3 => '/p')), 'any case');

	$text = '<p>data-pg-page-link="3" href="/o"</p>';
	pg_assert_same($text, pg_page_link_expand_html($text, array(3 => '/p')), 'text outside a tag is left alone');
	$other = '<div href="/o" data-pg-page-link="3"></div>';
	pg_assert_same('<div href="/o"></div>', pg_page_link_expand_html($other, array(3 => '/p')), 'only a link takes the address');
	pg_assert_same('<a href="/p" data-href="/o">a</a>', pg_page_link_expand_html('<a data-href="/o" data-pg-page-link="3">a</a>', array(3 => '/p')), 'a data-href is no href');
}
