<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the layout gallery's "From templates" source
 * (includes/fn/widgets_gallery.php): which layouts of a design template are
 * offered for a widget kind, with their placeholders resolved against the
 * design's pages, and the field names a form view was made for.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function _gallery_node($tag, $props = array(), $children = array())
{
	return array('type' => 'semantic', 'props' => array('tag' => $tag) + $props, 'children' => $children);
}

function _gallery_input($name)
{
	return _gallery_node('input', array('_attrs' => array(array('name' => 'name', 'value' => $name), array('name' => 'type', 'value' => 'text'))));
}

function _gallery_tpl()
{
	$root = function ($children) {
		return array('type' => 'root', 'props' => array(), 'children' => $children);
	};
	return array(
		'id'    => 'sample',
		'name'  => 'Sample',
		'pages' => array(
			array('key' => 'home', 'name' => 'home', 'title' => 'Home'),
			array('key' => 'blog', 'name' => 'blog', 'title' => 'Blog'),
			array('key' => 'blog_form', 'name' => 'blog-form', 'title' => 'Write a post'),
			array('key' => 'shop', 'name' => 'shop', 'title' => 'Shop', 'requires' => 'unknown'),
		),
		'widgets' => array(
			'login_region' => array('config' => array('regionType' => 'login_region'), 'tree' => $root(array(
				_gallery_node('a', array('href' => '{{page:home}}', 'text' => '{{site_name}}')),
			))),
			'blog_form' => array('page' => 'blog_form', 'config' => array('regionType' => 'custom_form'), 'tree' => $root(array(
				_gallery_input('Title'), _gallery_input('body'),
			))),
			'upload_form' => array('page' => 'blog_form', 'config' => array('regionType' => 'custom_form'), 'tree' => $root(array(
				_gallery_node('div', array('_cf' => array('upload_folder_id' => '{{folder:uploads}}'))),
			))),
			'blog_list' => array('page' => 'blog', 'config' => array('regionType' => 'form_list_view', 'custom_form_page_id' => '{{tab:blog_form}}'), 'tree' => $root(array(
				_gallery_node('a', array('_bindParams' => array('href' => array('page_id' => '{{tab:blog}}')))),
			))),
			'home_posts' => array('page' => 'home', 'config' => array('regionType' => 'form_list_view', 'custom_form_page_id' => '{{tab:blog_form}}'), 'tree' => $root(array(
				_gallery_node('p', array('text' => 'Latest')),
			))),
			'starter' => array('page' => 'home', 'config' => array('regionType' => 'form_list_view'), 'tree' => 'starter'),
			'nested' => array('page' => 'home', 'config' => array('regionType' => 'form_list_view', 'custom_form_page_id' => '{{tab:blog_form}}'), 'tree' => $root(array(
				array('type' => 'region', 'props' => array('templateShared' => 'footer'), 'children' => array()),
			))),
			'shop_list' => array('page' => 'shop', 'config' => array('regionType' => 'form_list_view', 'custom_form_page_id' => '{{tab:blog_form}}'), 'tree' => $root(array())),
		),
	);
}

function test_widget_layout_gallery_kind_pages_and_placeholders()
{
	$tpl   = _gallery_tpl();
	$pages = array(
		'home'      => array('id' => 11, 'url' => '/home'),
		'blog'      => array('id' => 12, 'url' => '/blog-2'),
		'blog_form' => array('id' => 13, 'url' => '/blog-form'),
	);

	$lists = pg_sw_template_layouts($tpl, 'form_list_view', $pages);
	pg_assert_same(array('blog_list', 'home_posts'), array_map(function ($l) { return $l['key']; }, $lists),
		'only explicit trees of the kind, on pages the site has, without nested parts');
	pg_assert_same('Blog', $lists[0]['label'], 'labelled with the page title');
	pg_assert_same('12', $lists[0]['tree']['children'][0]['props']['_bindParams']['href']['page_id'], 'a page link becomes the design\'s page id');
	pg_assert_same(array('body', 'title'), $lists[0]['fields'], 'a form view carries the field names of its form');

	$login = pg_sw_template_layouts($tpl, 'login_region', $pages);
	pg_assert_same(1, count($login), 'one login layout');
	pg_assert_same('/home', $login[0]['tree']['children'][0]['props']['href'], 'a page address is the design\'s page');
	pg_assert_same(null, $login[0]['fields'], 'no fields outside form views');
	pg_assert_same('Sample', $login[0]['label'], 'a widget of no page is labelled with the template');

	pg_assert_same(array(), pg_sw_template_layouts($tpl, 'login_region', array()), 'a page the design lacks drops the layout');
	pg_assert_same(array('home_posts'), array_map(function ($l) { return $l['key']; }, pg_sw_template_layouts($tpl, 'form_list_view', array('home' => $pages['home']))),
		'a page link the design lacks drops that layout only');

	$forms = pg_sw_template_layouts($tpl, 'custom_form', $pages);
	pg_assert_same(array('blog_form'), array_map(function ($l) { return $l['key']; }, $forms), 'a folder placeholder drops the layout');

	pg_assert_same(array(), pg_sw_template_layouts($tpl, '', $pages), 'no kind, nothing');
	pg_assert_same(array(), pg_sw_template_layouts(null, 'login_region', $pages), 'no template, nothing');
}

function test_widget_layout_gallery_form_fields_need_the_form()
{
	$tpl = _gallery_tpl();
	unset($tpl['widgets']['blog_form'], $tpl['widgets']['upload_form']);
	$pages = array('home' => array('id' => 11, 'url' => '/home'), 'blog' => array('id' => 12, 'url' => '/blog'));
	pg_assert_same(array(), pg_sw_template_layouts($tpl, 'form_list_view', $pages), 'a form view whose form the template lacks is not offered');
}
