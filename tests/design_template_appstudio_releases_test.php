<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Mobile App Developer design template's release notes and
 * beta (includes/design_templates/pinegrap-appstudio/releases.php): the
 * pages and widgets the plan names, every page link and tab setting naming
 * a page of the finished template, the sample notes filling the Releases
 * form's fields with answers its pick lists hold, What's New filtering
 * ?app= with filter links that send an app's name, the beta form starting
 * with ?app= and its status kept for the office, field ids of the
 * template's own prefix and no class of another template.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// parts.php, then releases.php in the same scope, as the template file
// reads them. Read once per run.
function _test_ma_rel_load()
{
	static $out = null;
	if ($out !== null) return $out;
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-appstudio';
	include $dir . '/parts.php';
	$apps_before = $apps;
	$releases_before = $releases;
	$part = include $dir . '/releases.php';
	$out = array(
		'part'             => $part,
		'apps'             => $apps,
		'releases'         => $releases,
		'apps_kept'        => $apps_before === $apps,
		'releases_kept'    => $releases_before === $releases,
		'app_names'        => $app_names,
		'platform_choices' => $platform_choices,
	);
	return $out;
}

function _test_ma_rel_nodes($node, &$out)
{
	if (!is_array($node)) return;
	if (isset($node['props'])) $out[] = $node;
	if (!empty($node['children']) && is_array($node['children'])) {
		foreach ($node['children'] as $child) _test_ma_rel_nodes($child, $out);
	}
}

// The page keys of the finished template (the plan's pages), as the parts
// test lists them; the test files load in no fixed order.
function _test_ma_rel_page_keys()
{
	return array(
		'home', 'services', 'process', 'about', 'faq', 'press',
		'app_new', 'apps', 'app',
		'release_new', 'releases', 'beta', 'beta_view', 'staff_beta',
		'support', 'support_view', 'my_tickets', 'staff_tickets', 'privacy', 'email_reply',
		'hire', 'project_view', 'my_projects', 'staff_projects',
		'contact', 'inbox', 'conversation', 'newsletter', 'blog', 'blog_post', 'search', 'error',
		'my_account', 'staff', 'blog_new', 'login', 'register', 'set_password', 'logout', 'profile',
		'change_password', 'email_preferences', 'address_book', 'staff_directory', 'staff_entry', 'forgot_password',
	);
}

function _test_ma_rel_page($key)
{
	$d = _test_ma_rel_load();
	foreach ($d['part']['pages'] as $p) {
		if ($p['key'] === $key) return $p;
	}
	return null;
}

// The form's fields of a widget tree: name => node.
function _test_ma_rel_fields($tree)
{
	$nodes = array();
	_test_ma_rel_nodes($tree, $nodes);
	$fields = array();
	foreach ($nodes as $n) {
		if (!in_array($n['props']['tag'] ?? '', array('input', 'select', 'textarea'), true)) continue;
		foreach ((array)($n['props']['_attrs'] ?? array()) as $a) {
			if ($a['name'] === 'name') $fields[$a['value']] = $n;
		}
	}
	return $fields;
}

function _test_ma_rel_options($node)
{
	$out = array();
	foreach ($node['children'] as $o) {
		foreach ($o['props']['_attrs'] as $a) {
			if ($a['name'] === 'value' && $a['value'] !== '') $out[] = $a['value'];
		}
	}
	return $out;
}

function test_appstudio_releases_pages_and_widgets()
{
	$d = _test_ma_rel_load();
	pg_assert_true($d['apps_kept'] && $d['releases_kept'], 'the part leaves the samples of parts.php as they are');
	$keys = array_column($d['part']['pages'], 'key');
	sort($keys);
	pg_assert_same(array('beta', 'beta_view', 'release_new', 'releases', 'staff_beta'), $keys, 'the plan\'s pages');
	foreach (array('release_form', 'releases_list', 'home_releases', 'beta_form', 'beta_detail', 'staff_beta_list') as $w) {
		pg_assert_true(isset($d['part']['widgets'][$w]), "widget $w");
	}
	foreach ($d['part']['widgets'] as $key => $w) {
		pg_assert_true($w['page'] === 'home' || in_array($w['page'], $keys, true), "widget $key: on a page of the template");
	}
	// Every widget a page places is one of the part's, or a shared one.
	foreach ($d['part']['pages'] as $p) {
		$nodes = array();
		_test_ma_rel_nodes($p['tree'], $nodes);
		foreach ($nodes as $n) {
			if (isset($n['props']['templateWidget']) && $n['props']['templateWidget'] !== 'newsletter_band' && $n['props']['templateWidget'] !== 'login_region') {
				pg_assert_true(isset($d['part']['widgets'][$n['props']['templateWidget']]), $p['key'] . ': widget ' . $n['props']['templateWidget']);
			}
		}
	}
}

function test_appstudio_releases_links_name_template_pages()
{
	$d = _test_ma_rel_load();
	$known = _test_ma_rel_page_keys();
	$json = json_encode(array($d['part']['pages'], $d['part']['widgets']));
	preg_match_all('/\{\{(page|tab):([a-z_]+)\}\}/', $json, $m, PREG_SET_ORDER);
	pg_assert_true(count($m) > 5, 'links found');
	foreach ($m as $hit) pg_assert_true(in_array($hit[2], $known, true), 'a page of the template: ' . $hit[0]);

	$nodes = array();
	_test_ma_rel_nodes(array('children' => array_merge(array_column($d['part']['pages'], 'tree'), array_column($d['part']['widgets'], 'tree'))), $nodes);
	$ids = 0;
	foreach ($nodes as $n) {
		$class = (string)($n['props']['cssClass'] ?? '');
		pg_assert_false((bool)preg_match('/\b(plw|psl|pre|pco|pev|pcl|pf|pdp|pm)-[a-z]/', $class), 'no class of another template: ' . $class);
		$tag = $n['props']['tag'] ?? '';
		if (in_array($tag, array('input', 'select', 'textarea'), true) && isset($n['props']['id'])) {
			pg_assert_true(strpos($n['props']['id'], 'ma-') === 0, 'field id prefix: ' . $n['props']['id']);
			$ids++;
		}
	}
	pg_assert_true($ids >= 10, 'form fields found');
}

function test_appstudio_release_samples_fill_the_form()
{
	$d = _test_ma_rel_load();
	$page = _test_ma_rel_page('release_new');
	pg_assert_same(1, $page['form']['collection'], 'Releases is a collection');
	pg_assert_same('private', $page['folder'], 'only the staff add notes');
	$fields = _test_ma_rel_fields($d['part']['widgets']['release_form']['tree']);
	$samples = $page['form']['sample_records'];
	pg_assert_same(count($d['releases']), count($samples), 'a sample record per release note');
	$apps = _test_ma_rel_options($fields['app']);
	$platforms = _test_ma_rel_options($fields['platform']);
	pg_assert_same($d['app_names'], $apps, 'the App list holds the app names');
	foreach ($samples as $n => $s) {
		foreach (array_keys($s) as $f) pg_assert_true(isset($fields[$f]), "sample $n: field $f is on the form");
		pg_assert_true(in_array($s['app'], $apps, true), "sample $n: an answer of App");
		pg_assert_true(in_array($s['platform'], $platforms, true), "sample $n: an answer of Platform");
		pg_assert_true((bool)preg_match('/^\d{4}-\d{2}-\d{2}$/', $s['release_date']), "sample $n: a date the list sorts on");
	}
	pg_assert_same('app', $fields['app']['props']['_cf']['url_param'], 'App starts with ?app=');
}

function test_appstudio_whats_new_filters_by_app()
{
	$d = _test_ma_rel_load();
	$list = $d['part']['widgets']['releases_list']['config'];
	pg_assert_same('{{tab:release_new}}', $list['custom_form_page_id'], 'reads the Releases form');
	pg_assert_same('release_date', $list['order_by_field'], 'sorted on the release date');
	pg_assert_same('DESC', $list['order_by_direction'], 'newest first');
	pg_assert_same(array(array('field' => 'app', 'operator' => 'is equal to', 'value' => '', 'dynamic_value' => 'url parameter', 'dynamic_value_attribute' => 'app')),
		$list['filters'], 'filtered by ?app=');
	$home = $d['part']['widgets']['home_releases']['config'];
	pg_assert_same(3, $home['max_results'], 'the home shows three notes');
	// The filter links send each app's name, as the App field stores it.
	$nodes = array();
	_test_ma_rel_nodes(_test_ma_rel_page('releases')['tree'], $nodes);
	$sent = array();
	foreach ($nodes as $n) {
		$href = (string)($n['props']['href'] ?? '');
		if (strpos($href, '{{page:releases}}?') === 0) {
			parse_str(substr($href, strlen('{{page:releases}}?')), $q);
			$sent[] = $q['app'];
		}
	}
	pg_assert_same($d['app_names'], $sent, 'a filter link per app');
}

function test_appstudio_beta_form()
{
	$d = _test_ma_rel_load();
	$page = _test_ma_rel_page('beta');
	pg_assert_same('{{tab:beta_view}}', $page['form']['confirmation_page_id'], 'the sign-up opens its own page');
	pg_assert_same(1, $page['form']['auto_registration'], 'an account for a new tester');
	$fields = _test_ma_rel_fields($d['part']['widgets']['beta_form']['tree']);
	foreach (array('app', 'platform', 'device', 'first_name', 'last_name', 'email', 'status', 'consent') as $f) {
		pg_assert_true(isset($fields[$f]), "beta field $f");
	}
	pg_assert_same('app', $fields['app']['props']['_cf']['url_param'], 'App starts with ?app=');
	pg_assert_same($d['app_names'], _test_ma_rel_options($fields['app']), 'the App list holds the app names');
	pg_assert_true(!empty($fields['status']['props']['_cf']['office_use_only']), 'the status is the developer\'s');
	pg_assert_same('email_address', $fields['email']['props']['_cf']['contact_field'], 'the e-mail is the contact\'s');
	$detail = $d['part']['widgets']['beta_detail']['config'];
	pg_assert_same('submitter_only', $detail['access_control'], 'a sign-up is seen by its tester');
	pg_assert_same('{{tab:beta}}', $detail['custom_form_page_id'], 'reads the Beta Testers form');
	pg_assert_same('{{tab:beta_view}}', $d['part']['widgets']['staff_beta_list']['config']['detail_page_id'], 'the staff list opens a sign-up');
	pg_assert_same('private', _test_ma_rel_page('staff_beta')['folder'], 'the list is the staff\'s');
}
