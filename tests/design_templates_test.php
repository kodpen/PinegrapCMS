<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for turning a visual design into a template
 * (includes/fn/design_templates_custom.php): the keys the pages, folders and
 * components get, and the placeholders written back into the trees, the
 * widget settings and the form settings - the reverse of what
 * _pg_tpl_fill() fills in when the template is opened.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The context a design with three pages, two folders and one contact group
// hands the helpers.
function _test_dtc_ctx()
{
	return array(
		'page_urls'            => array('/home' => 'home', '/blog' => 'blog', '/blog-post' => 'blog-post'),
		'page_ids'             => array(11 => 'home', 12 => 'blog', 13 => 'blog-post'),
		'widgets_by_sid'       => array(7 => 'login-widget'),
		'shared_by_sid'        => array(8 => 'site-header'),
		'folders_by_id'        => array(30 => 'site', 31 => 'members'),
		'contact_groups_by_id' => array(5 => 'newsletter'),
		'site_name'            => 'Acme',
		'site_email'           => 'hello@example.com',
		'year'                 => '2026',
	);
}

// Names become ASCII keys of [a-z0-9_-]; Turkish letters are spelled out.
function test_design_templates_page_key_from_name()
{
	pg_assert_same('about', pg_dtc_page_key('about', array()));
	pg_assert_same('hakkimizda', pg_dtc_page_key('Hakkımızda', array()), 'Turkish');
	pg_assert_same('iletisim_sayfasi', pg_dtc_page_key('İletişim Sayfası', array()), 'dotted capital I, space');
	pg_assert_same('blog_post', pg_dtc_page_key('blog_post', array()), 'underscore kept');
	pg_assert_same('page', pg_dtc_page_key('', array()), 'empty');
	pg_assert_same('page', pg_dtc_page_key('!!!', array()), 'nothing left');
	pg_assert_same('site_header-1', pg_dtc_page_key('Site Header [1]', array()), 'bracketed suffix');
	pg_assert_same('a-b', pg_dtc_page_key('a - b', array()), 'dash between spaces');
}

// A key that is taken gets -2, -3 ...
function test_design_templates_page_key_is_unique()
{
	pg_assert_same('about-2', pg_dtc_page_key('About', array('about' => true)));
	pg_assert_same('about-3', pg_dtc_page_key('about', array('about' => true, 'about-2' => true)));
}

// The home page is 'home'; another page called home takes home-2.
function test_design_templates_page_key_home_rule()
{
	pg_assert_same('home', pg_dtc_page_key('anasayfa', array(), true), 'home page');
	pg_assert_same('home-2', pg_dtc_page_key('home', array('home' => true)), 'a page named home');
	pg_assert_same('home', pg_dtc_page_key('home', array()), 'no home page in the design');
}

// A placed widget and a placed shared component become template references;
// a placement of a deleted row is left out and counted.
function test_design_templates_tokenize_tree_shared_refs()
{
	$tree = array('type' => 'root', 'props' => array(), '_id' => 'sd_1', 'children' => array(
		array('type' => 'shared_ref', '_id' => 'sd_2', 'props' => array('sharedId' => 8, 'sharedName' => 'Site Header'), 'children' => array(array('type' => 'semantic'))),
		array('type' => 'shared_ref', '_id' => 'sd_3', 'props' => array('sharedId' => 7, 'sharedName' => 'login'), 'children' => array()),
		array('type' => 'shared_ref', '_id' => 'sd_4', 'props' => array('sharedId' => 99, 'sharedName' => 'gone'), 'children' => array()),
	));
	$dropped = 0;
	$out = pg_dtc_tokenize_tree($tree, _test_dtc_ctx(), $dropped);

	pg_assert_same(2, count($out['children']), 'deleted placement left out');
	pg_assert_same(1, $dropped, 'counted');
	pg_assert_same(array('type' => 'shared_ref', 'props' => array('templateShared' => 'site-header'), 'children' => array()), $out['children'][0]);
	pg_assert_same(array('type' => 'shared_ref', 'props' => array('templateWidget' => 'login-widget'), 'children' => array()), $out['children'][1]);
	pg_assert_false(isset($out['_id']), 'root id gone');
}

// The editor's state on the nodes goes.
function test_design_templates_tokenize_tree_drops_editor_state()
{
	$tree = array('type' => 'root', '_id' => 'a', '_expanded' => true, 'props' => array(), 'children' => array(
		array('type' => 'content', '_id' => 'b', '_sharedDirty' => true, 'props' => array('contentType' => 'paragraph', 'text' => 'x'), 'children' => array()),
	));
	$out = pg_dtc_tokenize_tree($tree, _test_dtc_ctx());
	pg_assert_same(array('type' => 'root', 'props' => array(), 'children' => array(
		array('type' => 'content', 'props' => array('contentType' => 'paragraph', 'text' => 'x'), 'children' => array()),
	)), $out);
}

// The address of a page of the design: alone, with a query, with a fragment;
// one that only starts the same way is not touched.
function test_design_templates_tokenize_string_page_addresses()
{
	$ctx = _test_dtc_ctx();
	pg_assert_same('{{page:blog}}', pg_dtc_tokenize_string('/blog', $ctx), 'exact');
	pg_assert_same('{{page:blog-post}}', pg_dtc_tokenize_string('/blog-post', $ctx), 'the longer address is its own page');
	pg_assert_same('{{page:blog}}?tag=news', pg_dtc_tokenize_string('/blog?tag=news', $ctx), 'query');
	pg_assert_same('{{page:home}}#contact', pg_dtc_tokenize_string('/home#contact', $ctx), 'fragment');
	pg_assert_same('/blogs', pg_dtc_tokenize_string('/blogs', $ctx), 'prefix only');
	pg_assert_same('/blog/2026', pg_dtc_tokenize_string('/blog/2026', $ctx), 'path below');
	pg_assert_same('https://example.com/blog', pg_dtc_tokenize_string('https://example.com/blog', $ctx), 'other site');
	pg_assert_same('See /blog', pg_dtc_tokenize_string('See /blog', $ctx), 'inside a text');
}

// The site's name only as the whole value; the year only after ©.
function test_design_templates_tokenize_string_site_name_and_year()
{
	$ctx = _test_dtc_ctx();
	pg_assert_same('{{site_name}}', pg_dtc_tokenize_string('Acme', $ctx), 'whole value');
	pg_assert_same('Welcome to Acme', pg_dtc_tokenize_string('Welcome to Acme', $ctx), 'part of a text');
	pg_assert_same('© {{year}} Acme', pg_dtc_tokenize_string('© 2026 Acme', $ctx), 'copyright');
	pg_assert_same('© {{year}}', pg_dtc_tokenize_string('©2026', $ctx), 'no space');
	pg_assert_same('&copy; {{year}} Acme', pg_dtc_tokenize_string('&copy; 2026 Acme', $ctx), 'entity');
	pg_assert_same('Since 2026', pg_dtc_tokenize_string('Since 2026', $ctx), 'a year alone');
	pg_assert_same('© 20261', pg_dtc_tokenize_string('© 20261', $ctx), 'a longer number');

	$ctx['site_name'] = '';
	pg_assert_same('', pg_dtc_tokenize_string('', $ctx), 'no site name, empty value');
}

// href and _attrs values are rewritten, data bindings are not.
function test_design_templates_tokenize_tree_props()
{
	$tree = array('type' => 'content', 'props' => array(
		'contentType' => 'link', 'text' => 'Acme', 'href' => '/blog?p=1',
		'_attrs'      => array(array('name' => 'data-target', 'value' => '/blog-post')),
		'_bindings'   => array('href' => '/blog', 'text' => 'Acme'),
		'_cf'         => array('upload_folder_id' => 31),
	), 'children' => array());
	$out = pg_dtc_tokenize_tree($tree, _test_dtc_ctx());
	pg_assert_same('{{site_name}}', $out['props']['text']);
	pg_assert_same('{{page:blog}}?p=1', $out['props']['href']);
	pg_assert_same('{{page:blog-post}}', $out['props']['_attrs'][0]['value']);
	pg_assert_same('data-target', $out['props']['_attrs'][0]['name']);
	pg_assert_same(array('href' => '/blog', 'text' => 'Acme'), $out['props']['_bindings'], 'bindings untouched');
	pg_assert_same('{{folder:members}}', $out['props']['_cf']['upload_folder_id']);
}

// Widget settings: a page of the design, a folder, a contact group become
// placeholders; the template mark goes; any other id stays.
function test_design_templates_tokenize_config()
{
	$cfg = array(
		'regionType'                 => 'shopping_cart',
		'template_origin'            => 'hello-pinegrap/cart',
		'next_page_id_with_shipping' => 12,
		'custom_form_page_id'        => '13',
		'other_page_id'              => 500,
		'upload_folder_id'           => 30,
		'contact_group_id'           => 5,
		'product_group_id'           => 12,
		'items_per_page'             => 12,
		'filters'                    => array(array('field' => 'x', 'page_id' => 11)),
		'page_ids'                   => array(11, 500),
	);
	$out = pg_dtc_tokenize_config($cfg, _test_dtc_ctx());
	pg_assert_false(isset($out['template_origin']), 'template mark gone');
	pg_assert_same('{{tab:blog}}', $out['next_page_id_with_shipping']);
	pg_assert_same('{{tab:blog-post}}', $out['custom_form_page_id'], 'digits in a string');
	pg_assert_same(500, $out['other_page_id'], 'page outside the design');
	pg_assert_same('{{folder:site}}', $out['upload_folder_id']);
	pg_assert_same('{{contact_group:newsletter}}', $out['contact_group_id']);
	pg_assert_same(12, $out['product_group_id'], 'product group stays');
	pg_assert_same(12, $out['items_per_page'], 'a number that is no id');
	pg_assert_same('{{tab:home}}', $out['filters'][0]['page_id'], 'nested');
	pg_assert_same(array('{{tab:home}}', 500), $out['page_ids'], 'list under the name');
	pg_assert_same('shopping_cart', $out['regionType']);
}

// Form settings: the pages, the contact group, the site's address.
function test_design_templates_tokenize_form()
{
	$settings = array(
		'exists' => true, 'orphan' => false, 'form_name' => 'Contact',
		'notify_email' => 'hello@example.com', 'notify_page_id' => 12,
		'confirm_page_id' => 0, 'confirmation_page_id' => 999, 'contact_group_id' => 5,
	);
	$out = pg_dtc_tokenize_form($settings, _test_dtc_ctx());
	pg_assert_false(array_key_exists('exists', $out), 'exists gone');
	pg_assert_false(array_key_exists('orphan', $out), 'orphan gone');
	pg_assert_same('{{tab:blog}}', $out['notify_page_id']);
	pg_assert_same(0, $out['confirm_page_id'], 'none stays 0');
	pg_assert_same(999, $out['confirmation_page_id'], 'page outside the design');
	pg_assert_same('{{contact_group:newsletter}}', $out['contact_group_id']);
	pg_assert_same('{{site_email}}', $out['notify_email']);

	$settings['contact_group_id'] = 0;
	$settings['notify_email'] = 'staff@example.com';
	$out = pg_dtc_tokenize_form($settings, _test_dtc_ctx());
	pg_assert_same(0, $out['contact_group_id'], 'no group stays 0');
	pg_assert_same('staff@example.com', $out['notify_email'], 'another address stays');
}

// What the form tokens turn into when the template is opened is what the
// design had: the round trip through _pg_tpl_fill().
function test_design_templates_tokens_fill_back()
{
	$out = pg_dtc_tokenize_config(array('upload_folder_id' => 31, 'contact_group_id' => 5, 'next_page_id' => 11), _test_dtc_ctx());
	$filled = _pg_tpl_fill($out, array(
		'folders' => array('members' => 77), 'contact_groups' => array('newsletter' => 9),
		'tabs' => array('home' => 'tplabc_home'), 'pages' => array(),
	));
	pg_assert_same(77, $filled['upload_folder_id']);
	pg_assert_same(9, $filled['contact_group_id']);
	pg_assert_same('tab:tplabc_home', $filled['next_page_id']);
}

// Comments: off is null; on carries the keys prepare reads.
function test_design_templates_page_comments()
{
	pg_assert_same(null, pg_dtc_page_comments(array('pg_comments' => 0), _test_dtc_ctx()));
	$c = pg_dtc_page_comments(array(
		'pg_comments' => 1, 'pg_comments_label' => 'Comment', 'pg_comments_allow_new' => 1,
		'pg_comments_rating' => 0, 'pg_comments_auto_publish' => 1, 'pg_comments_show_date' => 1,
		'pg_comments_login' => 0, 'pg_comments_email_page' => 13, 'pg_comments_email_subject' => 'New',
		'pg_comments_notify_email' => 'hello@example.com',
	), _test_dtc_ctx());
	pg_assert_same('{{tab:blog-post}}', $c['email_page']);
	pg_assert_same('{{site_email}}', $c['notify_email']);
	pg_assert_same(1, $c['allow_new']);
	pg_assert_same(0, $c['rating']);
	pg_assert_same('Comment', $c['label']);
}

// Folders: up to the root (not included), parents first, keys unique.
function test_design_templates_folders()
{
	$rows = array(
		1  => array('folder_name' => 'root', 'folder_parent' => 0, 'folder_access_control_type' => 'public'),
		30 => array('folder_name' => 'Site', 'folder_parent' => 1, 'folder_access_control_type' => 'public'),
		31 => array('folder_name' => 'Members', 'folder_parent' => 30, 'folder_access_control_type' => 'registration'),
		32 => array('folder_name' => 'site', 'folder_parent' => 31, 'folder_access_control_type' => 'membership'),
	);
	$f = pg_dtc_folders(array(32, 1, 30), $rows, 1);
	pg_assert_same(array('site', 'members', 'site-2'), array_keys($f['folders']), 'parents first');
	pg_assert_same(array('name' => 'Site', 'access' => 'public'), $f['folders']['site'], 'under the root: no parent');
	pg_assert_same(array('name' => 'Members', 'access' => 'registration', 'parent' => 'site'), $f['folders']['members']);
	pg_assert_same(array('name' => 'site', 'access' => 'public', 'parent' => 'members'), $f['folders']['site-2'], 'unknown access reads public');
	pg_assert_same(array(30 => 'site', 31 => 'members', 32 => 'site-2'), $f['by_id']);
	pg_assert_false(isset($f['by_id'][1]), 'root is no template folder');
}

// The shared components a tree places, without following them.
function test_design_templates_tree_shared_ids()
{
	$tree = array('type' => 'root', 'children' => array(
		array('type' => 'shared_ref', 'props' => array('sharedId' => 8), 'children' => array()),
		array('type' => 'semantic', 'props' => array(), 'children' => array(
			array('type' => 'shared_ref', 'props' => array('sharedId' => '7'), 'children' => array()),
			array('type' => 'shared_ref', 'props' => array('sharedId' => 8), 'children' => array()),
		)),
	));
	pg_assert_same(array(8, 7), pg_dtc_tree_shared_ids($tree));
}

// The empty placeholder of a widget the editor lays out.
function test_design_templates_is_starter_tree()
{
	pg_assert_true(pg_dtc_is_starter_tree(array('type' => 'root', 'props' => array(), 'children' => array(
		array('type' => 'loop_area', 'props' => array(), 'children' => array())))));
	pg_assert_false(pg_dtc_is_starter_tree(array('type' => 'root', 'props' => array(), 'children' => array(
		array('type' => 'loop_area', 'props' => array(), 'children' => array(array('type' => 'semantic')))))), 'filled');
	pg_assert_false(pg_dtc_is_starter_tree(array('type' => 'root', 'props' => array('cssClass' => 'x'), 'children' => array(
		array('type' => 'loop_area', 'props' => array(), 'children' => array())))), 'root props');
}

// The link pass is no-op for a tree without template references, and
// points a nested reference at its row.
function test_design_templates_link_widgets_nested()
{
	$tree = array('type' => 'root', 'props' => array(), 'children' => array(
		array('type' => 'semantic', 'props' => array('tag' => 'nav'), 'children' => array(
			array('type' => 'shared_ref', 'props' => array('templateWidget' => 'login'), 'children' => array()),
		)),
	));
	$plain = array('type' => 'root', 'props' => array(), 'children' => array(array('type' => 'semantic', 'props' => array(), 'children' => array())));
	pg_assert_true(_pg_tpl_link_widgets($plain, array(), array()) === $plain, 'unchanged');
	$out = _pg_tpl_link_widgets($tree, array('login' => array('id' => 42, 'name' => 'login-widget')), array());
	pg_assert_same(array('sharedId' => 42, 'sharedName' => 'login-widget'), $out['children'][0]['children'][0]['props']);
}
