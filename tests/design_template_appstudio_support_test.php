<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Mobile App Developer design template's support part
 * (includes/design_templates/pinegrap-appstudio/mail.php and support.php):
 * its pages and folders, every widget placed once on the page it names,
 * the lists and the request page reading the support form, the form's
 * fields with the developer's own pick lists, ?app= and an office-use-only
 * status, the e-mails reading the form's fields, and every page link
 * naming a page of the finished template.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The parts as the template file reads them. Read once per run.
function _test_mas_load()
{
	static $out = null;
	if ($out !== null) return $out;
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-appstudio';
	include $dir . '/parts.php';
	include $dir . '/mail.php';
	$support = include $dir . '/support.php';
	$out = array(
		'support'      => $support,
		'apps'         => $support_app_choices,
		'kinds'        => $ticket_kinds,
		'statuses'     => $ticket_statuses,
		'mail_trees'   => array($email_header_tree(), $email_footer_tree(), $mail_comment_tree),
	);
	return $out;
}

function _test_mas_nodes($node, &$out)
{
	if (!is_array($node)) return;
	if (isset($node['props'])) $out[] = $node;
	if (!empty($node['children']) && is_array($node['children'])) {
		foreach ($node['children'] as $child) _test_mas_nodes($child, $out);
	}
}

// The form's controls by name: their props and their answers.
function _test_mas_controls($tree)
{
	$nodes = array();
	_test_mas_nodes($tree, $nodes);
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
		$out[$name] = array('props' => $node['props'], 'options' => $options);
	}
	return $out;
}

// The field names a tree reads (text, href, src bindings), past the
// widget's own tokens (__…) and flags.
function _test_mas_bound_fields($tree)
{
	$nodes = array();
	_test_mas_nodes($tree, $nodes);
	$out = array();
	foreach ($nodes as $node) {
		foreach (array('text', 'href') as $prop) {
			$b = $node['props']['_bindings'][$prop] ?? '';
			if ($b !== '' && strpos($b, '__') !== 0) $out[$b] = true;
		}
	}
	return array_keys($out);
}

function _test_mas_widgets($tree)
{
	$nodes = array();
	_test_mas_nodes($tree, $nodes);
	$out = array();
	foreach ($nodes as $node) {
		if (isset($node['props']['templateWidget'])) $out[] = $node['props']['templateWidget'];
	}
	return $out;
}

function test_appstudio_support_pages_and_widgets()
{
	$d = _test_mas_load();
	$s = $d['support'];
	$keys = array();
	$pages = array();
	foreach ($s['pages'] as $p) {
		$keys[] = $p['key'];
		$pages[$p['key']] = $p;
		pg_assert_true(trim($p['name']) !== '' && !preg_match('/[^a-z0-9-]/', $p['name']), $p['key'] . ': page name is an address');
		if (strpos($p['key'], 'email_') === 0 || strpos($p['key'], 'staff_') === 0) {
			pg_assert_same('private', $p['folder'], $p['key'] . ': private');
		}
	}
	pg_assert_same(array('support', 'support_view', 'my_tickets', 'staff_tickets', 'privacy',
		'email_ticket_new', 'email_ticket_received', 'email_reply'), $keys, 'the support pages in order');

	pg_assert_same('public', $pages['support']['folder'], 'anyone may ask for help');
	pg_assert_same('public', $pages['privacy']['folder'], 'the privacy notice is public');
	pg_assert_same('registration', $pages['support_view']['folder'], 'a request\'s page needs an account');
	pg_assert_same('registration', $pages['my_tickets']['folder'], 'My Support Requests needs an account');
	$c = $pages['support_view']['comments'];
	pg_assert_true(!empty($c['attachments']), 'messages may carry a screenshot');
	pg_assert_same('{{tab:email_reply}}', $c['email_page'], 'messages mailed with the reply e-mail');
	$f = $pages['support']['form'];
	pg_assert_same(1, $f['auto_registration'], 'a request opens an account');
	pg_assert_same('{{tab:support_view}}', $f['confirmation_page_id'], 'a request opens its page');
	pg_assert_same('{{tab:email_ticket_new}}', $f['notify_page_id'], 'developer e-mail');
	pg_assert_same('{{tab:email_ticket_received}}', $f['confirm_page_id'], 'user e-mail');

	// Every placed widget is defined, on the page it names, once; every
	// defined widget is placed.
	$placed = array();
	foreach ($s['pages'] as $p) {
		foreach (_test_mas_widgets($p['tree']) as $w) $placed[$w][] = $p['key'];
	}
	foreach ($s['widgets'] as $key => $w) {
		pg_assert_same(array($w['page']), $placed[$key] ?? array(), "widget $key placed once, on its page");
	}
	unset($placed['newsletter_band']);
	foreach ($placed as $key => $on) pg_assert_true(isset($s['widgets'][$key]), "placed widget $key is defined");

	// The lists, the request page and the e-mails read the support form and
	// only fields it has.
	$controls = _test_mas_controls($s['widgets']['support_form']['tree']);
	$fields = array_merge(array_keys($controls), array('submitted_date_and_time', 'number_of_comments', 'reference_code', 'form_item_view', 'not_found'));
	foreach ($s['widgets'] as $key => $w) {
		$type = $w['config']['regionType'];
		if ($type === 'form_list_view' || $type === 'form_item_view') {
			pg_assert_same('{{tab:support}}', $w['config']['custom_form_page_id'], "$key: reads the support form");
			foreach (_test_mas_bound_fields($w['tree']) as $b) pg_assert_true(in_array($b, $fields, true), "$key: reads a field of the form: $b");
		}
		if ($type === 'form_list_view') {
			pg_assert_same('{{tab:support_view}}', $w['config']['detail_page_id'], "$key: opens the request's page");
			foreach ($w['config']['search_fields'] ?? array() as $sf) pg_assert_true(isset($controls[$sf]), "$key: searches a field: $sf");
		}
	}
	pg_assert_true(!empty($s['widgets']['my_tickets_list']['config']['viewer_filter_submitter']), 'My Support Requests shows the user\'s own');
	pg_assert_true(!empty($s['widgets']['staff_tickets_list']['config']['search_enabled']), 'the staff list has a search');
	pg_assert_same('submitter_only', $s['widgets']['ticket_detail']['config']['access_control'], 'a request is its sender\'s to see');
	pg_assert_same('public', $s['widgets']['email_ticket_admin']['config']['access_control'], 'an e-mail draws the request just sent');

	// Every {{tab:}} and {{page:}} names a page of the finished template;
	// every {{folder:}} the private folder.
	$known = array_flip(array(
		'home', 'services', 'process', 'about', 'faq', 'press', 'app_new', 'apps', 'app',
		'release_new', 'releases', 'beta', 'beta_view', 'staff_beta',
		'support', 'support_view', 'my_tickets', 'staff_tickets', 'privacy', 'email_reply',
		'hire', 'project_view', 'my_projects', 'staff_projects',
		'contact', 'inbox', 'conversation', 'newsletter', 'blog', 'blog_post', 'search', 'error',
		'my_account', 'staff', 'blog_new', 'login', 'register', 'set_password', 'logout', 'profile',
		'change_password', 'email_preferences', 'address_book', 'staff_directory', 'staff_entry', 'forgot_password',
		'email_ticket_new', 'email_ticket_received',
	));
	$json = json_encode(array($s, $d['mail_trees']));
	preg_match_all('/\{\{(tab|page):([a-z0-9_]+)\}\}/', $json, $m);
	foreach ($m[2] as $key) pg_assert_true(isset($known[$key]), "placeholder names a page: $key");
	preg_match_all('/\{\{folder:([a-z0-9_]+)\}\}/', $json, $m);
	pg_assert_same(array('private'), array_values(array_unique($m[1])), 'files go to the private folder');
	// No class of another template.
	pg_assert_false((bool)preg_match('/"cssClass":"[^"]*\b(re|plw|pp|pco)-/', $json), 'no class of another template');
}

// The form's lists are the developer's own answers, the app starts from
// the app page's ?app=, the status is the staff's and the ids are the
// template's.
function test_appstudio_support_form()
{
	$d = _test_mas_load();
	$controls = _test_mas_controls($d['support']['widgets']['support_form']['tree']);
	foreach (array('app', 'kind', 'subject', 'message', 'app_version', 'device', 'attachment',
	               'first_name', 'last_name', 'email', 'status', 'consent') as $name) {
		pg_assert_true(isset($controls[$name]), "support form: $name");
	}
	pg_assert_same($d['apps'], $controls['app']['options'], 'app answers');
	pg_assert_same($d['kinds'], $controls['kind']['options'], 'kind answers');
	pg_assert_same($d['statuses'], $controls['status']['options'], 'status answers');
	pg_assert_same('app', $controls['app']['props']['_cf']['url_param'], 'app from ?app=');
	$reserved = function_exists('pg_sw_url_param_reserved') ? pg_sw_url_param_reserved() : array();
	pg_assert_false(in_array('app', $reserved, true), '?app= is not reserved');
	pg_assert_true(!empty($controls['status']['props']['_cf']['office_use_only']), 'status is office use only');
	pg_assert_same('{{folder:private}}', $controls['attachment']['props']['_cf']['upload_folder_id'], 'files kept private');
	pg_assert_same('email_address', $controls['email']['props']['_cf']['contact_field'], 'the e-mail is the contact\'s');
	$ids = array();
	foreach ($controls as $c) $ids[] = $c['props']['id'];
	foreach ($ids as $id) pg_assert_same(0, strpos($id, 'ma-'), "field id prefixed: $id");
	pg_assert_same(count($ids), count(array_unique($ids)), 'field ids unique');
}
