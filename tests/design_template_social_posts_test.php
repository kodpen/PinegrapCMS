<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Social Community design template's posts
 * (includes/design_templates/pinegrap-social/posts.php): the part gives its
 * pages and widgets, every widget sits on a page of the finished template,
 * the samples fill only fields the Posts form has, one for each sample post,
 * every list and the post view keep hidden posts out, the filters name
 * fields of the form, the member's lists show only their own or their
 * saved posts, and every page link names a page of the finished template.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(__DIR__ . '/helpers/language_map.php');

// parts.php and posts.php in one scope, as the template file reads them.
function _test_social_posts_load()
{
	static $out = null;
	if ($out !== null) return $out;
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-social';
	include $dir . '/parts.php';
	$part = include $dir . '/posts.php';
	$count = 0;
	foreach ($social_posts as $list) $count += count($list);
	$out = array('part' => $part, 'post_count' => $count, 'hidden' => $post_hidden);
	return $out;
}

function _test_social_posts_pages($part)
{
	$pages = array();
	foreach ($part['pages'] as $p) $pages[$p['key']] = $p;
	return $pages;
}

// The names of the fields of a form widget's tree.
function _test_social_posts_field_names($node, &$names)
{
	if (!is_array($node)) return;
	if (!empty($node['props']['_attrs'])) {
		$tag = isset($node['props']['tag']) ? $node['props']['tag'] : '';
		foreach ($node['props']['_attrs'] as $a) {
			if ($a['name'] === 'name' && in_array($tag, array('input', 'select', 'textarea'), true)) $names[$a['value']] = true;
		}
	}
	if (!empty($node['children'])) {
		foreach ($node['children'] as $child) _test_social_posts_field_names($child, $names);
	}
}

function test_social_posts_part_gives_its_pages_and_widgets()
{
	$d = _test_social_posts_load();
	$pages = _test_social_posts_pages($d['part']);
	foreach (array('post_new', 'explore', 'tag', 'post', 'my_posts', 'saved') as $key) {
		pg_assert_true(isset($pages[$key]), "page $key");
	}
	pg_assert_same(6, count($pages), 'only the step\'s pages');
	foreach ($d['part']['widgets'] as $key => $w) {
		pg_assert_true(isset($pages[$w['page']]) || $w['page'] === 'home', "widget $key sits on a page of the template");
	}
	pg_assert_true(isset($d['part']['widgets']['post_comments']), 'the post page\'s comments widget');
	pg_assert_same('comments', $d['part']['widgets']['post_comments']['config']['regionType']);
	pg_assert_same('{{tab:email_comment}}', $pages['post']['comments']['email_page'], 'comments mail the post\'s savers');
	foreach (array('post_new', 'my_posts', 'saved') as $key) {
		pg_assert_same('registration', $pages[$key]['folder'], "$key is for signed-in members");
	}
}

function test_social_posts_samples_fill_the_form()
{
	$d = _test_social_posts_load();
	$pages = _test_social_posts_pages($d['part']);
	$form = $pages['post_new']['form'];
	pg_assert_same(1, $form['collection'], 'the Posts form is a collection');
	$names = array();
	_test_social_posts_field_names($d['part']['widgets']['post_form']['tree'], $names);
	unset($names['guidelines']);
	pg_assert_same($d['post_count'], count($form['sample_records']), 'one sample record per sample post');
	foreach ($form['sample_records'] as $n => $r) {
		pg_assert_same(array(), array_diff(array_keys($r), array_keys($names)), "sample $n fills only form fields");
		pg_assert_true($r['photo'] !== '' && $r['alt_text'] !== '' && $r['caption'] !== '', "sample $n has a photo, alt text and caption");
		pg_assert_true($r['status'] !== $d['hidden'], "sample $n is live");
	}
	pg_assert_true(isset($names[$form['share_image_field']]), 'the share image is a form field');
}

function test_social_posts_lists_keep_hidden_posts_out()
{
	$d = _test_social_posts_load();
	$names = array();
	_test_social_posts_field_names($d['part']['widgets']['post_form']['tree'], $names);
	foreach ($d['part']['widgets'] as $key => $w) {
		$type = $w['config']['regionType'];
		if ($type !== 'form_list_view' && $type !== 'form_item_view') continue;
		$live = false;
		foreach ($w['config']['filters'] as $f) {
			pg_assert_true(isset($names[$f['field']]), "$key filters on a form field ({$f['field']})");
			if ($f['field'] === 'status' && $f['operator'] === 'is not equal to' && $f['value'] === $d['hidden']) $live = true;
		}
		pg_assert_true($live, "$key shows live posts only");
		pg_assert_same('{{tab:post_new}}', $w['config']['custom_form_page_id'], "$key reads the Posts form");
	}
	$mine = $d['part']['widgets']['my_posts_list']['config'];
	pg_assert_true($mine['viewer_filter'] && $mine['viewer_filter_submitter'] && !$mine['viewer_filter_watcher'] && !$mine['viewer_filter_editor'], 'My Posts: the member\'s own');
	$saved = $d['part']['widgets']['saved_posts_list']['config'];
	pg_assert_true($saved['viewer_filter'] && !$saved['viewer_filter_submitter'] && $saved['viewer_filter_watcher'] && !$saved['viewer_filter_editor'], 'Saved: the followed posts');
	pg_assert_same('{{tab:post}}', $saved['detail_page_id'], 'watchers are counted on the post page');
}

function test_social_posts_page_links_name_template_pages()
{
	$d = _test_social_posts_load();
	$known = array_flip(array(
		'home', 'topics', 'about', 'guidelines', 'faq', 'help', 'privacy', 'terms',
		'my_profile', 'people', 'member', 'staff_members',
		'post_new', 'explore', 'tag', 'post', 'my_posts', 'saved',
		'report', 'report_view', 'my_reports', 'staff_reports', 'staff_report', 'staff_posts',
		'email_reply', 'email_comment', 'email_report_new', 'email_report_received',
		'contact', 'inbox', 'conversation', 'newsletter', 'blog', 'blog_post', 'search', 'error',
		'my_account', 'staff', 'blog_new', 'login', 'register', 'set_password', 'logout', 'profile',
		'change_password', 'email_preferences', 'staff_directory', 'staff_entry', 'forgot_password',
	));
	$json = json_encode($d['part']);
	preg_match_all('/\{\{(?:page|tab):([a-z_]+)\}\}/', $json, $m);
	foreach (array_unique($m[1]) as $key) pg_assert_true(isset($known[$key]), "link to $key names a template page");
	pg_assert_contains('{{tab:report}}', $json, 'the post page links the report form');
	pg_assert_true(!preg_match('/\b(?:pf|pht|pmk)-/', $json), 'only the template\'s own classes');
}
