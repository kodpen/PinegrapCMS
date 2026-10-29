<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the collections the design templates ship
 * (includes/design_templates/*.php): which page forms carry the collection
 * flag, and the field kinds (_cf.kind) on their controls, which must be a
 * kind of pg_coll_kinds() that the control's field type can have.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The templates as the loader reads them, by id, read once per run.
function _test_tpl_coll_templates()
{
	static $out = null;
	if ($out === null) {
		$out = array();
		foreach (glob(PG_FUNCTIONS_DIR . '/includes/design_templates/*.php') as $file) {
			$out[basename($file, '.php')] = _pg_tpl_include($file);
		}
	}
	return $out;
}

// An attribute of a node's _attrs, or null.
function _test_tpl_coll_attr($node, $name)
{
	$attrs = (isset($node['props']['_attrs']) && is_array($node['props']['_attrs'])) ? $node['props']['_attrs'] : array();
	foreach ($attrs as $a) {
		if (is_array($a) && isset($a['name']) && $a['name'] === $name) return isset($a['value']) ? (string)$a['value'] : '';
	}
	return null;
}

// The keys of a template's pages whose form is a collection.
function _test_tpl_coll_form_keys($tpl)
{
	$keys = array();
	foreach ((isset($tpl['pages']) && is_array($tpl['pages'])) ? $tpl['pages'] : array() as $p) {
		if (!empty($p['form']['collection'])) $keys[] = (string)$p['key'];
	}
	return $keys;
}

// The form field type a control node becomes, as pg_coll_kinds() names it.
function _test_tpl_coll_field_type($node)
{
	$tag = isset($node['props']['tag']) ? (string)$node['props']['tag'] : '';
	if ($tag === 'select') return 'pick list';
	if ($tag === 'textarea') return 'text area';
	if ($tag !== 'input') return '';
	$type = (string)_test_tpl_coll_attr($node, 'type');
	if ($type === 'file') return 'file upload';
	if ($type === 'radio') return 'radio button';
	if ($type === 'checkbox') return 'check box';
	return 'text box';
}

// Every node of the trees in $value (pages, widgets, shared parts).
function _test_tpl_coll_nodes($value, &$out)
{
	if (!is_array($value)) return;
	if (isset($value['type']) && isset($value['props']) && is_array($value['props'])) $out[] = $value;
	foreach ($value as $child) {
		if (is_array($child)) _test_tpl_coll_nodes($child, $out);
	}
}

// The page forms that are collections, by template: blog, staff, menu,
// events, articles and stories, and the blog categories of hello-pinegrap.
function test_design_template_collection_forms()
{
	$expected = array(
		'hello-pinegrap'      => array('blog_category_new', 'blog_new', 'staff_entry'),
		'pinegrap-bookshop'   => array('blog_new', 'staff_entry'),
		'pinegrap-boutique'   => array('blog_new', 'staff_entry'),
		'pinegrap-cafe'       => array('blog_new', 'event_new', 'menu_new', 'staff_entry'),
		'pinegrap-clinic'     => array('blog_new', 'staff_entry'),
		'pinegrap-forum'      => array('staff_entry'),
		'pinegrap-magazine'   => array('blog_new', 'staff_entry'),
		'pinegrap-news'       => array('blog_new', 'staff_entry'),
		'pinegrap-playground' => array('blog_new', 'staff_entry'),
		'pinegrap-store'      => array('blog_new', 'staff_entry'),
	);
	$templates = _test_tpl_coll_templates();
	foreach ($expected as $id => $keys) {
		pg_assert_true(isset($templates[$id]), $id . ' is a template');
	}
	foreach ($templates as $id => $tpl) {
		$found = array();
		foreach ((isset($tpl['pages']) && is_array($tpl['pages'])) ? $tpl['pages'] : array() as $p) {
			if (!isset($p['form']) || !is_array($p['form']) || !array_key_exists('collection', $p['form'])) continue;
			pg_assert_same(1, $p['form']['collection'], $id . '/' . $p['key'] . ': the flag is 1');
			$found[] = (string)$p['key'];
		}
		sort($found);
		pg_assert_same(isset($expected[$id]) ? $expected[$id] : array(), $found, $id . ': collection forms');
	}
}

// A kind is one the control's field type can have; a file field that takes
// only pictures is an image, and every image field takes only pictures.
function test_design_template_collection_kinds()
{
	$kinds = pg_coll_kinds();
	$images = 0;
	foreach (_test_tpl_coll_templates() as $id => $tpl) {
		$nodes = array();
		_test_tpl_coll_nodes($tpl, $nodes);
		foreach ($nodes as $node) {
			$cf = (isset($node['props']['_cf']) && is_array($node['props']['_cf'])) ? $node['props']['_cf'] : array();
			$type = _test_tpl_coll_field_type($node);
			$label = $id . '/' . (isset($node['props']['id']) ? $node['props']['id'] : '?');
			if (isset($cf['kind'])) {
				pg_assert_true(isset($kinds[$cf['kind']]), $label . ': a known kind');
				pg_assert_true(in_array($type, $kinds[$cf['kind']], true), $label . ': the field type can have the kind');
				// A reference names a page of the template holding a
				// collection form; Publish resolves the key.
				if (isset($cf['ref'])) {
					pg_assert_same('reference', $cf['kind'], $label . ': only a reference names a form');
					pg_assert_true((bool)preg_match('~^\{\{tab:([a-z0-9_]+)\}\}$~', (string)$cf['ref'], $m), $label . ': a tab key');
					pg_assert_true(in_array($m[1], _test_tpl_coll_form_keys($tpl), true), $label . ': the key is a collection form of the template');
				}
			}
			if ($type === 'file upload') {
				$picture = _test_tpl_coll_attr($node, 'accept') === 'image/*';
				pg_assert_same($picture, isset($cf['kind']) && $cf['kind'] === 'image', $label . ': image kind follows accept');
				if ($picture) $images++;
			}
		}
	}
	pg_assert_true($images >= 20, 'covers and photos carry the kind');
}

// The blog's categories: the post form's category field points into the
// categories form, the sample posts name their category by a sample
// category's name, and the post page prints it; the version was raised.
function test_design_template_blog_categories()
{
	$templates = _test_tpl_coll_templates();
	$tpl = $templates['hello-pinegrap'];
	pg_assert_true(version_compare($tpl['version'], '2.5.0', '>='), 'the version raised');

	$pages = array();
	foreach ($tpl['pages'] as $p) $pages[$p['key']] = $p;
	$names = array();
	foreach ($pages['blog_category_new']['form']['sample_records'] as $r) $names[] = $r['name'];
	pg_assert_true(count($names) >= 3, 'sample categories');
	foreach ($pages['blog_new']['form']['sample_records'] as $r) {
		pg_assert_true(in_array($r['category'], $names, true), $r['title'] . ': a sample category');
	}

	$nodes = array();
	_test_tpl_coll_nodes($tpl['widgets']['blog_form'], $nodes);
	$ref = null;
	foreach ($nodes as $n) {
		if (_test_tpl_coll_attr($n, 'name') === 'category') $ref = $n['props']['_cf'];
	}
	pg_assert_same('{{tab:blog_category_new}}', $ref['ref']);
	pg_assert_same('category', $ref['rss_field'], 'the feed category');

	$nodes = array();
	_test_tpl_coll_nodes($tpl['widgets']['blog_post'], $nodes);
	$spec = null;
	foreach ($nodes as $n) {
		if (isset($n['props']['_bindings']['text']) && $n['props']['_bindings']['text'] === '__ref') $spec = $n['props']['_bindParams']['text'];
	}
	pg_assert_same(array('field' => 'category', 'page_id' => '{{tab:blog_category_new}}', 'show' => 'name'), $spec);
}
