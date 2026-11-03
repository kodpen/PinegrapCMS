<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Car Dealership design template's finance requests
 * (includes/design_templates/pinegrap-dealership/finance_requests.php):
 * the pages and widgets it returns point at each other, every page and tab
 * link names a page of the finished template, the form's fields are named
 * once and ask for bands, never an ID or a bank number, the Vehicle and
 * Way to pay start from the query string, the status is the staff's, and
 * the file defines no helper outside its own fin_ names.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// parts.php, then the finance file in the same scope, as the template
// file reads them. Also returns the variable names the finance file adds.
function _test_dl_fin_load()
{
	static $out = null;
	if ($out !== null) return $out;
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-dealership';
	include $dir . '/parts.php';
	$before = array_keys(get_defined_vars());
	$part = include $dir . '/finance_requests.php';
	$added = array_diff(array_keys(get_defined_vars()), $before, array('part', 'before'));
	$out = array('part' => $part, 'added' => array_values($added));
	return $out;
}

function _test_dl_fin_nodes($node, &$out)
{
	if (!is_array($node)) return;
	if (isset($node['props'])) $out[] = $node;
	if (!empty($node['children']) && is_array($node['children'])) {
		foreach ($node['children'] as $child) _test_dl_fin_nodes($child, $out);
	}
}

function test_dealership_finance_pages_and_widgets()
{
	$d = _test_dl_fin_load();
	$pages = array();
	foreach ($d['part']['pages'] as $p) $pages[$p['key']] = $p;
	pg_assert_same(array('finance_apply', 'finance_view', 'my_finance', 'staff_finance', 'email_finance_new', 'email_finance_received'),
		array_keys($pages), 'the finance pages');
	$widgets = $d['part']['widgets'];
	foreach ($widgets as $key => $w) {
		pg_assert_true(isset($pages[$w['page']]), "widget $key: on a page of the file");
	}
	// Every widget a page places is defined here, and every one defined is placed.
	$placed = array();
	foreach ($pages as $p) {
		$nodes = array();
		_test_dl_fin_nodes($p['tree'], $nodes);
		foreach ($nodes as $n) if (isset($n['props']['templateWidget'])) $placed[$n['props']['templateWidget']] = true;
	}
	unset($placed['newsletter_band']);
	ksort($placed);
	$defined = $widgets;
	ksort($defined);
	pg_assert_same(array_keys($defined), array_keys($placed), 'each widget placed once');
	pg_assert_same('submitter_only', $widgets['finance_detail']['config']['access_control'], 'the request page is private');
	pg_assert_same('{{tab:email_reply}}', $pages['finance_view']['comments']['email_page'], 'messages mail through the reply e-mail');
	pg_assert_same('{{tab:email_finance_new}}', $pages['finance_apply']['form']['notify_page_id'], 'staff e-mail');
	pg_assert_same('{{tab:email_finance_received}}', $pages['finance_apply']['form']['confirm_page_id'], 'customer e-mail');
	pg_assert_same('private', $pages['staff_finance']['folder'], 'the staff list is private');
	foreach (array('email_finance_new', 'email_finance_received') as $k) {
		pg_assert_true($pages[$k]['noindex'] && $pages[$k]['folder'] === 'private', "$k: private and out of the index");
	}
}

function test_dealership_finance_links_name_template_pages()
{
	$d = _test_dl_fin_load();
	$known = array_flip(array(
		'home', 'finance', 'vehicles', 'vehicle', 'privacy', 'email_reply', 'forgot_password',
		'finance_apply', 'finance_view', 'my_finance', 'staff_finance', 'email_finance_new', 'email_finance_received',
	));
	$nodes = array();
	foreach ($d['part']['pages'] as $p) _test_dl_fin_nodes($p['tree'], $nodes);
	foreach ($d['part']['widgets'] as $w) if (isset($w['tree'])) _test_dl_fin_nodes($w['tree'], $nodes);
	$text = json_encode(array($d['part']['pages'], $d['part']['widgets'], array_map(function ($n) { return $n['props']; }, $nodes)));
	preg_match_all('/\{\{(?:page|tab):([a-z0-9_]+)\}\}/', $text, $m);
	pg_assert_true(count($m[1]) > 10, 'links found');
	foreach (array_unique($m[1]) as $key) pg_assert_true(isset($known[$key]), "link to a known page: $key");
	pg_assert_true(strpos($text, 'pre-') === false && strpos($text, 'pas-') === false, 'no class of another template');
}

function test_dealership_finance_form_fields()
{
	$d = _test_dl_fin_load();
	$nodes = array();
	_test_dl_fin_nodes($d['part']['widgets']['finance_form']['tree'], $nodes);
	$names = array();
	$ids = array();
	$cf = array();
	foreach ($nodes as $n) {
		if (isset($n['props']['id']) && in_array($n['props']['tag'] ?? '', array('input', 'select', 'textarea'), true)) {
			$ids[] = $n['props']['id'];
			pg_assert_same(0, strpos($n['props']['id'], 'dl-fi-'), 'field id ' . $n['props']['id'] . ': the file\'s prefix');
		}
		if (empty($n['props']['_attrs'])) continue;
		$type = '';
		$name = '';
		foreach ($n['props']['_attrs'] as $a) {
			if ($a['name'] === 'name') $name = $a['value'];
			if ($a['name'] === 'type') $type = $a['value'];
		}
		if ($name === '') continue;
		if ($type !== 'radio' || !isset($names[$name])) $names[$name] = isset($names[$name]) ? $names[$name] + 1 : 1;
		if (isset($n['props']['_cf'])) $cf[$name] = $n['props']['_cf'];
	}
	pg_assert_same(count($ids), count(array_unique($ids)), 'field ids unique');
	foreach ($names as $name => $count) {
		pg_assert_same(1, $count, "field $name: once");
		pg_assert_true(!preg_match('/(^|_)(id|tc|iban|bank|account|card|ssn|passport)(_|$)/', $name), "field $name: no ID or bank data");
	}
	foreach (array('vehicle', 'plan', 'deposit', 'term', 'employment', 'income', 'note', 'contact_method',
	               'first_name', 'last_name', 'email', 'phone', 'status', 'consent') as $name) {
		pg_assert_true(isset($names[$name]), "field $name present");
	}
	pg_assert_same('vehicle', $cf['vehicle']['url_param'], 'the vehicle from ?vehicle=');
	pg_assert_same('plan', $cf['plan']['url_param'], 'the plan from ?plan=');
	pg_assert_true(!empty($cf['status']['office_use_only']), 'the status is the staff\'s');
}

function test_dealership_finance_helpers_keep_their_prefix()
{
	$d = _test_dl_fin_load();
	pg_assert_true(count($d['added']) > 0, 'the file adds helpers');
	foreach ($d['added'] as $name) pg_assert_same(0, strpos($name, 'fin_'), "variable \$$name: fin_ prefix");
}
