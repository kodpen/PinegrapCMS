<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Car Dealership design template's test drives and trade-ins
 * (includes/design_templates/pinegrap-dealership/mail.php, requests.php):
 * the pages in order and in their folders, every widget placed once on its
 * page, the lists and views reading their own form, the forms' fields (the
 * car from ?vehicle=, the trade-in photos kept private, the status the
 * staff's), the e-mails, the field ids and classes the template's own, and
 * the Turkish of every literal.
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

// parts.php, mail.php and requests.php in one scope, as the template file
// includes them. Read once per run.
function _test_dlr_load()
{
	static $out = null;
	if ($out !== null) return $out;
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-dealership';
	include $dir . '/parts.php';
	include $dir . '/mail.php';
	$requests = include $dir . '/requests.php';
	$out = array(
		'requests'  => $requests,
		'vehicles'  => $vehicles,
		'mail'     => array($email_header_tree(), $email_footer_tree(), $mail_comment_tree),
	);
	return $out;
}

// Every node of a tree, depth first.
function _test_dlr_nodes($node, &$out)
{
	if (!is_array($node)) return;
	if (isset($node['props'])) $out[] = $node;
	if (!empty($node['children']) && is_array($node['children'])) {
		foreach ($node['children'] as $child) _test_dlr_nodes($child, $out);
	}
}

// The template widgets a tree places.
function _test_dlr_widgets($tree)
{
	$nodes = array();
	_test_dlr_nodes($tree, $nodes);
	$out = array();
	foreach ($nodes as $node) {
		if (isset($node['props']['templateWidget'])) $out[] = $node['props']['templateWidget'];
	}
	return $out;
}

// The controls of a widget tree by field name: their props and the values
// of their options.
function _test_dlr_controls($tree)
{
	$nodes = array();
	_test_dlr_nodes($tree, $nodes);
	$out = array();
	foreach ($nodes as $node) {
		if (!in_array($node['props']['tag'] ?? '', array('input', 'select', 'textarea'), true)) continue;
		$name = '';
		foreach ($node['props']['_attrs'] ?? array() as $a) {
			if ($a['name'] === 'name') $name = $a['value'];
		}
		if ($name === '') continue;
		$options = array();
		foreach ($node['children'] ?? array() as $o) {
			foreach ($o['props']['_attrs'] ?? array() as $a) {
				if ($a['name'] === 'value' && $a['value'] !== '') $options[] = $a['value'];
			}
		}
		$out[$name] = $node['props'] + array('_options' => $options);
	}
	return $out;
}

// The page keys of the finished template (the plan's pages).
function _test_dlr_page_keys()
{
	return array(
		'home', 'service', 'finance', 'about', 'visit', 'faq',
		'vehicle_new', 'vehicles', 'new_cars', 'used_cars', 'vehicle',
		'test_drive', 'test_drive_view', 'my_test_drives', 'staff_test_drives',
		'tradein', 'tradein_view', 'my_tradeins', 'staff_tradeins', 'privacy', 'email_reply',
		'email_test_drive_new', 'email_test_drive_received', 'email_tradein_new', 'email_tradein_received',
		'finance_apply', 'finance_view', 'my_finance', 'staff_finance',
		'contact', 'inbox', 'conversation', 'newsletter', 'blog', 'blog_post', 'search', 'error',
		'my_account', 'staff', 'blog_new', 'login', 'register', 'set_password', 'logout', 'profile',
		'change_password', 'email_preferences', 'staff_directory', 'staff_entry', 'forgot_password',
	);
}

function test_dealership_request_pages_and_widgets()
{
	$d = _test_dlr_load();
	$r = $d['requests'];
	$keys = array();
	foreach ($r['pages'] as $p) $keys[] = $p['key'];
	pg_assert_same(array(
		'test_drive', 'test_drive_view', 'my_test_drives', 'staff_test_drives',
		'tradein', 'tradein_view', 'my_tradeins', 'staff_tradeins', 'privacy',
		'email_test_drive_new', 'email_test_drive_received', 'email_tradein_new', 'email_tradein_received', 'email_reply',
	), $keys, 'the request pages in order');

	$known = array_flip(_test_dlr_page_keys());
	$pages = array();
	foreach ($r['pages'] as $p) {
		$pages[$p['key']] = $p;
		pg_assert_true(trim($p['name']) !== '' && !preg_match('/[^a-z0-9-]/', $p['name']), $p['key'] . ': page name is an address');
		if (strpos($p['key'], 'email_') === 0 || strpos($p['key'], 'staff_') === 0) {
			pg_assert_same('private', $p['folder'], $p['key'] . ': private');
		}
		if (strpos($p['key'], 'email_') === 0) pg_assert_true(!empty($p['noindex']), $p['key'] . ': kept out of search engines');
	}
	foreach (array('test_drive' => 'my_test_drives', 'tradein' => 'my_tradeins') as $form => $my) {
		pg_assert_same('registration', $pages[$form . '_view']['folder'], "$form: its page needs an account");
		pg_assert_same('registration', $pages[$my]['folder'], "$form: My list needs an account");
		pg_assert_true(!empty($pages[$form . '_view']['comments']['attachments']), "$form: messages may carry a file");
		pg_assert_same('{{tab:email_reply}}', $pages[$form . '_view']['comments']['email_page'], "$form: messages mailed with the reply e-mail");
		$f = $pages[$form]['form'];
		pg_assert_same('public', $pages[$form]['folder'], "$form: anyone may ask");
		pg_assert_same(1, $f['auto_registration'], "$form: opens an account");
		pg_assert_same('{{tab:' . $form . '_view}}', $f['confirmation_page_id'], "$form: opens its page");
		pg_assert_same('{{tab:email_' . $form . '_new}}', $f['notify_page_id'], "$form: staff e-mail");
		pg_assert_same('{{tab:email_' . $form . '_received}}', $f['confirm_page_id'], "$form: customer e-mail");
	}

	// Every placed widget is defined, on the page it names, once; every
	// defined widget is placed.
	$placed = array();
	foreach ($r['pages'] as $p) {
		foreach (_test_dlr_widgets($p['tree']) as $w) $placed[$w][] = $p['key'];
	}
	foreach ($r['widgets'] as $key => $w) {
		pg_assert_same(array($w['page']), $placed[$key] ?? array(), "widget $key placed once, on its page");
	}
	// The newsletter band of a public page is the standard part's.
	unset($placed['newsletter_band']);
	foreach ($placed as $key => $on) pg_assert_true(isset($r['widgets'][$key]), "placed widget $key is defined");

	// The lists and views read their own form and open its page; a list
	// searches only fields its form has.
	foreach ($r['widgets'] as $key => $w) {
		$form = strpos($key, 'tradein') !== false ? 'tradein' : 'test_drive';
		$type = $w['config']['regionType'];
		if ($type === 'form_list_view') {
			pg_assert_same('{{tab:' . $form . '}}', $w['config']['custom_form_page_id'], "$key: reads its form");
			pg_assert_same('{{tab:' . $form . '_view}}', $w['config']['detail_page_id'], "$key: opens its page");
			$controls = _test_dlr_controls($r['widgets'][$form . '_form']['tree']);
			foreach ($w['config']['search_fields'] ?? array() as $f) pg_assert_true(isset($controls[$f]), "$key: searches a field: $f");
		}
		if ($type === 'form_item_view') {
			pg_assert_same('{{tab:' . $form . '}}', $w['config']['custom_form_page_id'], "$key: reads its form");
			$access = strpos($key, 'email_') === 0 ? 'public' : 'submitter_only';
			pg_assert_same($access, $w['config']['access_control'], "$key: who may read it");
		}
	}
	pg_assert_true(!empty($r['widgets']['my_tradeins_list']['config']['viewer_filter_submitter']), 'My Trade-ins shows the customer\'s own');
	pg_assert_true(!empty($r['widgets']['my_test_drives_list']['config']['viewer_filter_submitter']), 'My Test Drives shows the customer\'s own');

	// Every {{tab:}} and {{page:}} names a page of the template; every
	// {{folder:}} the private folder.
	$json = json_encode(array($r, $d['mail']));
	preg_match_all('/\{\{(tab|page):([a-z0-9_]+)\}\}/', $json, $m);
	foreach ($m[2] as $key) pg_assert_true(isset($known[$key]), "placeholder names a page: $key");
	preg_match_all('/\{\{folder:([a-z0-9_]+)\}\}/', $json, $m);
	pg_assert_same(array('private'), array_values(array_unique($m[1])), 'uploads go to the private folder');
}

// The forms' fields: the car starts from ?vehicle=, the trade-in photos
// are private pictures, the status is the staff's.
function test_dealership_request_forms()
{
	$d = _test_dlr_load();
	$w = $d['requests']['widgets'];
	$reserved = function_exists('pg_sw_url_param_reserved') ? pg_sw_url_param_reserved() : array();

	$td = _test_dlr_controls($w['test_drive_form']['tree']);
	foreach (array('vehicle', 'drive_date', 'preferred_time', 'note', 'licence', 'contact_method',
	               'first_name', 'last_name', 'email', 'phone', 'status', 'consent') as $name) {
		pg_assert_true(isset($td[$name]), "test drive form: $name");
	}
	pg_assert_same('vehicle', $td['vehicle']['_cf']['url_param'], 'test drive: car from ?vehicle=');
	pg_assert_same(array(lang('Request received'), lang('Confirmed'), lang('Completed'), lang('Cancelled')), $td['status']['_options'], 'test drive statuses');

	$ti = _test_dlr_controls($w['tradein_form']['tree']);
	foreach (array('make', 'model', 'year', 'mileage', 'fuel', 'transmission', 'condition', 'service_history',
	               'photos', 'note', 'vehicle', 'first_name', 'last_name', 'email', 'phone', 'status', 'consent') as $name) {
		pg_assert_true(isset($ti[$name]), "trade-in form: $name");
	}
	pg_assert_same('vehicle', $ti['vehicle']['_cf']['url_param'], 'trade-in: car wanted from ?vehicle=');
	pg_assert_same('number', $ti['mileage']['_cf']['kind'], 'trade-in: mileage is a number');
	pg_assert_same('image', $ti['photos']['_cf']['kind'], 'trade-in: photos are pictures');
	pg_assert_same('{{folder:private}}', $ti['photos']['_cf']['upload_folder_id'], 'trade-in: photos kept private');

	foreach (array('test drive' => $td, 'trade-in' => $ti) as $form => $controls) {
		pg_assert_true(!empty($controls['status']['_cf']['office_use_only']), "$form: status is office use only");
		foreach ($controls as $name => $p) {
			if (isset($p['_cf']['url_param'])) pg_assert_false(in_array($p['_cf']['url_param'], $reserved, true), "$form: $name parameter not reserved");
		}
	}

	// Every field id carries the template's prefix and is unique.
	$ids = array();
	foreach (array('test_drive_form', 'tradein_form') as $key) {
		$nodes = array();
		_test_dlr_nodes($w[$key]['tree'], $nodes);
		foreach ($nodes as $node) {
			if (isset($node['props']['id']) && in_array($node['props']['tag'] ?? '', array('input', 'select', 'textarea'), true)) $ids[] = $node['props']['id'];
		}
	}
	pg_assert_true(count($ids) > 15, 'field ids found');
	foreach ($ids as $id) pg_assert_same(0, strpos($id, 'dl-'), "field id prefixed: $id");
	pg_assert_same(count($ids), count(array_unique($ids)), 'field ids unique');

	// The samples in parts.php are left as they were.
	pg_assert_true(isset($d['vehicles']['velo-volt']['model']), 'the sample vehicles are kept');
}

// Classes are Bootstrap's or the template's own (pdl-), never another
// template's; an e-mail's colours are the palette's, written inline.
function test_dealership_request_classes_and_mail()
{
	$d = _test_dlr_load();
	$nodes = array();
	_test_dlr_nodes(array('children' => array_merge($d['mail'], array_column($d['requests']['pages'], 'tree'), array_values(array_filter(array_column($d['requests']['widgets'], 'tree'))))), $nodes);
	foreach ($nodes as $n) {
		foreach (preg_split('/\s+/', (string)($n['props']['cssClass'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $c) {
			pg_assert_false((bool)preg_match('/^(pre|pev|pf|pdp|pm|pc|pl|plx|pas)-/', $c), "class $c is not another template's");
		}
	}
	$mail = json_encode($d['mail']);
	pg_assert_contains('#0047ab', $mail, 'e-mail link colour is the palette\'s');
	pg_assert_false(strpos($mail, '#0f766e') !== false, 'no other template\'s colour');
	foreach ($d['requests']['widgets'] as $key => $w) {
		if (strpos($key, 'email_') !== 0 || $key === 'email_comment') continue;
		pg_assert_contains('?r=^^reference_code^^', json_encode($w['tree'], JSON_UNESCAPED_SLASHES), "$key: opens the request by its code");
	}
}

// Every literal has its Turkish.
function test_dealership_request_turkish_words()
{
	$map = pg_test_language_map('tr');
	foreach (array('mail.php', 'requests.php') as $file) {
		$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-dealership/' . $file);
		preg_match_all("/lang\\('((?:[^'\\\\]|\\\\.)*)'\\)/", $src, $m);
		foreach (array_unique($m[1]) as $raw) {
			$key = stripslashes($raw);
			pg_assert_true(isset($map[$key]) && $map[$key] !== '', "$file: Turkish for '$key'");
		}
	}
}
