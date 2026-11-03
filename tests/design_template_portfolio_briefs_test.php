<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Web Designer Portfolio design template's briefs
 * (includes/design_templates/pinegrap-portfolio/mail.php and briefs.php):
 * the pages in order and in their folders, the brief form's account,
 * confirmation and e-mails, every widget placed once on its page, the
 * lists and the view reading the Project Briefs form, the form's pick
 * lists equal to the designer's answers and started from ?type= and
 * ?package=, the status the staff's, files kept private, field ids
 * prefixed, every page placeholder naming a page of the template, the
 * e-mails carrying their colours inline and no class of another template.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The parts as the template file reads them: parts.php, mail.php, then
// briefs.php in the same scope. Read once per run.
function _test_pdb_load()
{
	static $out = null;
	if ($out !== null) return $out;
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-portfolio';
	include $dir . '/parts.php';
	include $dir . '/mail.php';
	$brief_part = include $dir . '/briefs.php';
	$out = array(
		'briefs'          => $brief_part,
		'projects'        => $projects,
		'categories'      => $categories,
		'category_titles' => $category_titles,
		'package_choices' => $package_choices,
		'budget_choices'  => $budget_choices,
		'start_choices'   => $start_choices,
		'page_counts'     => $page_count_choices,
		'brand_choices'   => $brand_choices,
		'contact_choices' => $contact_choices,
		'brief_statuses'  => $brief_statuses,
		'mail_shared'     => array($email_header_tree(), $email_footer_tree()),
		'mail_comment'    => $mail_comment_tree,
	);
	return $out;
}

// Every node of a tree, depth first.
function _test_pdb_nodes($node, &$out)
{
	if (!is_array($node)) return;
	if (isset($node['props'])) $out[] = $node;
	if (!empty($node['children']) && is_array($node['children'])) {
		foreach ($node['children'] as $child) _test_pdb_nodes($child, $out);
	}
}

// The template widgets a tree places.
function _test_pdb_widgets($tree)
{
	$nodes = array();
	_test_pdb_nodes($tree, $nodes);
	$out = array();
	foreach ($nodes as $node) {
		if (isset($node['props']['templateWidget'])) $out[] = $node['props']['templateWidget'];
	}
	return $out;
}

// The form controls of a tree by name: their props and their answers.
function _test_pdb_controls($tree)
{
	$nodes = array();
	_test_pdb_nodes($tree, $nodes);
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
		if (isset($out[$name])) {
			// A radio group: one control per answer.
			foreach ($node['props']['_attrs'] as $a) if ($a['name'] === 'value') $out[$name]['options'][] = $a['value'];
			continue;
		}
		if (($node['props']['tag'] ?? '') === 'input') {
			foreach ($node['props']['_attrs'] ?? array() as $a) {
				if ($a['name'] === 'type' && $a['value'] === 'radio') {
					foreach ($node['props']['_attrs'] as $b) if ($b['name'] === 'value') $options[] = $b['value'];
				}
			}
		}
		$out[$name] = array('props' => $node['props'], 'options' => $options);
	}
	return $out;
}

function test_portfolio_brief_pages_and_widgets()
{
	$d = _test_pdb_load();
	$b = $d['briefs'];
	$keys = array();
	foreach ($b['pages'] as $p) $keys[] = $p['key'];
	pg_assert_same(array(
		'brief', 'brief_view', 'my_briefs', 'staff_briefs', 'privacy',
		'email_brief_new', 'email_brief_received', 'email_reply',
		'email_new_message', 'email_message_received', 'email_welcome',
	), $keys, 'the brief pages in order');

	$pages = array();
	foreach ($b['pages'] as $p) {
		$pages[$p['key']] = $p;
		pg_assert_true(trim($p['name']) !== '' && !preg_match('/[^a-z0-9-]/', $p['name']), $p['key'] . ': page name is an address');
		if (strpos($p['key'], 'email_') === 0 || strpos($p['key'], 'staff_') === 0) {
			pg_assert_same('private', $p['folder'], $p['key'] . ': private');
		}
		if (strpos($p['key'], 'email_') === 0) pg_assert_true(!empty($p['noindex']) && $p['search'] === false, $p['key'] . ': out of the search');
	}
	pg_assert_same('public', $pages['brief']['folder'], 'anyone may send a brief');
	pg_assert_same('public', $pages['privacy']['folder'], 'privacy is public');
	pg_assert_same('registration', $pages['brief_view']['folder'], 'a brief\'s page needs an account');
	pg_assert_same('registration', $pages['my_briefs']['folder'], 'My Briefs needs an account');
	$c = $pages['brief_view']['comments'];
	pg_assert_true(!empty($c['attachments']), 'messages may carry a file');
	pg_assert_same('{{tab:email_reply}}', $c['email_page'], 'messages mailed with the reply e-mail');
	pg_assert_same('{{site_email}}', $c['notify_email'], 'the designer is told of every message');
	$f = $pages['brief']['form'];
	pg_assert_same(1, $f['auto_registration'], 'a brief opens an account');
	pg_assert_same('{{tab:brief_view}}', $f['confirmation_page_id'], 'a brief opens its page');
	pg_assert_same('{{tab:email_brief_new}}', $f['notify_page_id'], 'the designer\'s e-mail');
	pg_assert_same('{{tab:email_brief_received}}', $f['confirm_page_id'], 'the client\'s e-mail');
	pg_assert_true(strpos($f['notify_subject'], '^^project_type^^') !== false, 'the subject names the kind of project');

	// Every placed widget is defined, on the page it names, once; every
	// defined widget is placed.
	$placed = array();
	foreach ($b['pages'] as $p) {
		foreach (_test_pdb_widgets($p['tree']) as $w) $placed[$w][] = $p['key'];
	}
	foreach ($b['widgets'] as $key => $w) {
		pg_assert_same(array($w['page']), $placed[$key] ?? array(), "widget $key placed once, on its page");
	}
	unset($placed['newsletter_band']);
	foreach ($placed as $key => $on) pg_assert_true(isset($b['widgets'][$key]), "placed widget $key is defined");

	// The contact form's e-mails read the contact form of standard.php.
	foreach (array('email_message_admin', 'email_message_sender') as $key) {
		pg_assert_same('{{tab:contact}}', $b['widgets'][$key]['config']['custom_form_page_id'], "$key: reads the contact form");
		$nodes = array();
		_test_pdb_nodes($b['widgets'][$key]['tree'], $nodes);
		foreach ($nodes as $node) {
			foreach ($node['props']['_bindings'] ?? array() as $prop => $token) {
				if ($prop === 'eo_visible_if') continue;
				pg_assert_true(in_array($token, array('first_name', 'last_name', 'email', 'company', 'topic', 'message', 'reference_code', '__new_account_email'), true), "$key binds a contact field: $token");
			}
		}
	}

	// The lists and views read the brief form; the lists open the brief.
	$controls = _test_pdb_controls($b['widgets']['brief_form']['tree']);
	foreach ($b['widgets'] as $key => $w) {
		$type = $w['config']['regionType'];
		if ($type === 'form_list_view' || ($type === 'form_item_view' && strpos($key, 'email_message_') !== 0)) {
			pg_assert_same('{{tab:brief}}', $w['config']['custom_form_page_id'], "$key: reads the brief form");
		}
		if ($type === 'form_list_view') {
			pg_assert_same('{{tab:brief_view}}', $w['config']['detail_page_id'], "$key: opens the brief");
			foreach ($w['config']['search_fields'] ?? array() as $field) pg_assert_true(isset($controls[$field]), "$key: searches a field: $field");
		}
	}
	pg_assert_true(!empty($b['widgets']['my_briefs_list']['config']['viewer_filter_submitter']), 'My Briefs shows the client\'s own');
	pg_assert_true(!empty($b['widgets']['staff_briefs_list']['config']['search_enabled']), 'the staff list has a search');
	pg_assert_same('submitter_only', $b['widgets']['brief_detail']['config']['access_control'], 'a brief is its client\'s to see');
	pg_assert_same('comments', $b['widgets']['brief_messages']['config']['regionType'], 'messages are the page\'s comments');
	pg_assert_same('brief_view', $b['widgets']['brief_messages']['page'], 'messages under the brief');

	// Every field a list, view or e-mail binds is a field of the form, a
	// system value or the record's own token.
	$system = array('submitted_date_and_time', 'number_of_comments', 'reference_code', 'form_item_view', 'not_found',
	                '__new_account_email', '__new_account_set_password_url', '__edit_url');
	foreach (array('brief_detail', 'my_briefs_list', 'staff_briefs_list', 'email_brief_admin', 'email_brief_client') as $key) {
		$nodes = array();
		_test_pdb_nodes($b['widgets'][$key]['tree'], $nodes);
		foreach ($nodes as $node) {
			foreach ($node['props']['_bindings'] ?? array() as $prop => $token) {
				if ($prop === 'eo_visible_if' || $prop === 'section') continue;
				pg_assert_true(isset($controls[$token]) || in_array($token, $system, true), "$key binds a known field: $token");
			}
		}
	}

	// Every {{tab:}} and {{page:}} names a page of the template; every
	// {{folder:}} the private folder.
	$known = array_flip(array(
		'home', 'services', 'process', 'about', 'faq', 'project_new', 'work', 'project',
		'brief', 'brief_view', 'my_briefs', 'staff_briefs', 'privacy',
		'email_brief_new', 'email_brief_received', 'email_reply',
		'email_new_message', 'email_message_received', 'email_welcome',
		'contact', 'inbox', 'conversation', 'newsletter', 'blog', 'blog_post', 'search', 'error',
		'my_account', 'staff', 'blog_new', 'login', 'register', 'forgot_password',
	));
	$json = json_encode(array($b, $d['mail_shared'], $d['mail_comment']));
	preg_match_all('/\{\{(tab|page):([a-z0-9_]+)\}\}/', $json, $m);
	foreach ($m[2] as $key) pg_assert_true(isset($known[$key]), "placeholder names a page: $key");
	preg_match_all('/\{\{folder:([a-z0-9_]+)\}\}/', $json, $m);
	pg_assert_same(array('private'), array_values(array_unique($m[1])), 'uploads go to the private folder');
}

// The brief form's lists are the designer's own answers, start from the
// links' query (?type=, ?package=), and the status is the staff's.
function test_portfolio_brief_form()
{
	$d = _test_pdb_load();
	$c = _test_pdb_controls($d['briefs']['widgets']['brief_form']['tree']);
	foreach (array('project_type', 'package', 'pages', 'brand_assets', 'budget', 'start', 'deadline', 'goals', 'reference_sites',
	               'files', 'contact_method', 'first_name', 'last_name', 'email', 'phone', 'status', 'consent') as $name) {
		pg_assert_true(isset($c[$name]), "brief form: $name");
	}
	pg_assert_same($d['category_titles'], $c['project_type']['options'], 'project type answers');
	pg_assert_same($d['package_choices'], $c['package']['options'], 'package answers');
	pg_assert_same($d['budget_choices'], $c['budget']['options'], 'budget answers');
	pg_assert_same($d['start_choices'], $c['start']['options'], 'start answers');
	pg_assert_same($d['page_counts'], $c['pages']['options'], 'page count answers');
	pg_assert_same(array_column($d['brand_choices'], 0), $c['brand_assets']['options'], 'brand answers');
	pg_assert_same(array_column($d['contact_choices'], 0), $c['contact_method']['options'], 'contact answers');
	pg_assert_same($d['brief_statuses'], $c['status']['options'], 'status answers');
	pg_assert_same('type', $c['project_type']['props']['_cf']['url_param'], 'project type from ?type=');
	pg_assert_same('package', $c['package']['props']['_cf']['url_param'], 'package from ?package=');
	pg_assert_true(!empty($c['status']['props']['_cf']['office_use_only']), 'status is office use only');
	pg_assert_same('{{folder:private}}', $c['files']['props']['_cf']['upload_folder_id'], 'files kept private');
	$reserved = function_exists('pg_sw_url_param_reserved') ? pg_sw_url_param_reserved() : array();
	foreach ($c as $name => $control) {
		if (isset($control['props']['_cf']['url_param'])) pg_assert_false(in_array($control['props']['_cf']['url_param'], $reserved, true), "$name parameter not reserved");
	}
	// Every sample case study's kind of project is an answer, so ?type=
	// from a case study or a service chooses it.
	foreach ($d['projects'] as $key => $p) {
		pg_assert_true(in_array($d['categories'][$p['category']]['title'], $c['project_type']['options'], true), "project $key: its kind is an answer");
	}

	// The consent box links the privacy page.
	$json = json_encode($d['briefs']['widgets']['brief_form']['tree']);
	pg_assert_true(strpos($json, '{{page:privacy}}') !== false, 'consent links the privacy page');

	// Every field id carries the template's prefix and is unique.
	$nodes = array();
	_test_pdb_nodes($d['briefs']['widgets']['brief_form']['tree'], $nodes);
	$ids = array();
	foreach ($nodes as $node) {
		if (isset($node['props']['id']) && in_array($node['props']['tag'] ?? '', array('input', 'select', 'textarea'), true)) $ids[] = $node['props']['id'];
	}
	foreach ($ids as $id) pg_assert_same(0, strpos($id, 'pd-'), "field id prefixed: $id");
	pg_assert_same(count($ids), count(array_unique($ids)), 'field ids unique');
}

// The e-mails: every page drawn in the frame with the shared header and
// footer, the reply e-mail keeping the System region after its card, the
// button bound to the comment's address, and no class of another template.
function test_portfolio_brief_mails()
{
	$d = _test_pdb_load();
	foreach ($d['briefs']['pages'] as $p) {
		if (strpos($p['key'], 'email_') !== 0) continue;
		$json = json_encode($p['tree']);
		pg_assert_true(strpos($json, '"email_header"') !== false && strpos($json, '"email_footer"') !== false, $p['key'] . ': header and footer');
		pg_assert_true(strpos($json, 'max-width:600px') !== false, $p['key'] . ': the frame\'s width inline');
	}
	$reply = json_encode($d['briefs']['pages'][7]['tree']);
	pg_assert_same('email_reply', $d['briefs']['pages'][7]['key'], 'the reply e-mail');
	pg_assert_true(strpos($reply, '"regionType":"system"') !== false, 'the reply e-mail keeps the System region');
	pg_assert_true(strpos(json_encode($d['mail_comment']), '__comment_url') !== false, 'the comment card links the comment');
	$all = json_encode(array($d['briefs'], $d['mail_shared'], $d['mail_comment']));
	pg_assert_same(0, preg_match('/\b(pf|pre|pl|pdl)-[a-z]/', str_replace(array('pre-wrap'), '', $all)), 'no class of another template');
	pg_assert_same(0, preg_match('/#0f766e|#ccfbf1/', $all), 'no colour of another template');
}
