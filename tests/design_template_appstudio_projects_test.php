<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Mobile App Developer design template's client projects
 * (includes/design_templates/pinegrap-appstudio/projects.php): the pages in
 * order and in their folders, the request form's account, confirmation and
 * e-mails, every widget placed once on its page, the lists, the view and
 * the e-mails reading the Project Requests form, the form's pick lists
 * equal to the developer's answers and started from ?type= and ?package=,
 * the links of the services and packages choosing an answer, the status
 * the staff's, files kept private, field ids prefixed and every page
 * placeholder naming a page of the finished template.
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
// projects.php in the same scope. Read once per run. Until mail.php is in
// the folder, the e-mail builders it shares with every template's mail.php
// are stood in by plain ones of the same names.
function _test_asproj_appstudio_projects_load()
{
	static $out = null;
	if ($out !== null) return $out;
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-appstudio';
	include $dir . '/parts.php';
	if (is_file($dir . '/mail.php')) {
		include $dir . '/mail.php';
	} else {
		$mail_style = function ($css) use ($attr) {
			return array('_attrs' => array($attr('style', $css)));
		};
		$mail_page = function ($children) use ($el, $shared) {
			return array('type' => 'root', 'props' => array(), 'children' => array(
				$shared('email_header'), $el('div', '', 'Body', $children), $shared('email_footer')));
		};
		$mail_heading = function ($text) use ($heading) {
			return $heading('h1', $text, '');
		};
		$mail_para = function ($text) use ($para) {
			return $para($text);
		};
		$mail_button = function ($text, $href, $extra = array()) use ($link) {
			return $link($text, $href, '', array(), $extra);
		};
		$mail_line = function ($label, $values) use ($el) {
			return $el('p', '', $label, $values);
		};
		$mail_box = function ($children, $name) use ($el) {
			return $el('div', '', $name, $children);
		};
		$mail_greeting = function () use ($el, $span, $bind) {
			return $el('p', '', 'Greeting', array($span('Jane', '', 'First Name', $bind('first_name'))));
		};
		$mail_settings = array('folder' => 'private', 'search' => false, 'sitemap' => false, 'noindex' => true);
	}
	$part = include $dir . '/projects.php';
	$package_links = array();
	foreach (array_keys($packages) as $key) $package_links[$key] = $package_card($key);
	$out = array(
		'projects'        => $part,
		'project_types'   => $project_types,
		'services'        => $services,
		'packages'        => $packages,
		'package_cards'   => $package_links,
		'type_choices'    => $project_type_choices,
		'package_choices' => $package_choices,
		'budget_choices'  => $budget_choices,
		'start_choices'   => $start_choices,
		'design_choices'  => $design_choices,
		'contact_choices' => $contact_choices,
		'statuses'        => $project_statuses,
		'apps'            => $apps,
		'releases'        => $releases,
	);
	return $out;
}

// Every node of a tree, depth first.
function _test_asproj_map_nodes($node, &$out)
{
	if (!is_array($node)) return;
	if (isset($node['props'])) $out[] = $node;
	if (!empty($node['children']) && is_array($node['children'])) {
		foreach ($node['children'] as $child) _test_asproj_map_nodes($child, $out);
	}
}

// The template widgets a tree places.
function _test_asproj_map_widgets($tree)
{
	$nodes = array();
	_test_asproj_map_nodes($tree, $nodes);
	$out = array();
	foreach ($nodes as $node) {
		if (isset($node['props']['templateWidget'])) $out[] = $node['props']['templateWidget'];
	}
	return $out;
}

// The form controls of a tree by name: their props and their answers (a
// radio group's answers gathered from its buttons).
function _test_asproj_map_controls($tree)
{
	$nodes = array();
	_test_asproj_map_nodes($tree, $nodes);
	$out = array();
	foreach ($nodes as $node) {
		$tag = $node['props']['tag'] ?? '';
		if (!in_array($tag, array('input', 'select', 'textarea'), true)) continue;
		$attrs = array();
		foreach ($node['props']['_attrs'] ?? array() as $a) $attrs[$a['name']] = $a['value'];
		if (($attrs['name'] ?? '') === '') continue;
		$name = $attrs['name'];
		if (($attrs['type'] ?? '') === 'radio') {
			if (!isset($out[$name])) $out[$name] = array('props' => $node['props'], 'options' => array());
			$out[$name]['options'][] = $attrs['value'];
			continue;
		}
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

function test_appstudio_project_pages_and_widgets()
{
	$d = _test_asproj_appstudio_projects_load();
	$p = $d['projects'];
	$keys = array();
	foreach ($p['pages'] as $page) $keys[] = $page['key'];
	pg_assert_same(array('hire', 'project_view', 'my_projects', 'staff_projects', 'email_project_new', 'email_project_received'),
		$keys, 'the project pages in order');

	$pages = array();
	foreach ($p['pages'] as $page) {
		$pages[$page['key']] = $page;
		pg_assert_true(trim($page['name']) !== '' && !preg_match('/[^a-z0-9-]/', $page['name']), $page['key'] . ': page name is an address');
		if (strpos($page['key'], 'email_') === 0) {
			pg_assert_same('private', $page['folder'], $page['key'] . ': private');
			pg_assert_true(!empty($page['noindex']) && $page['search'] === false, $page['key'] . ': out of the search');
		}
	}
	pg_assert_same('public', $pages['hire']['folder'], 'anyone may ask for a project');
	pg_assert_same('registration', $pages['project_view']['folder'], 'a request\'s page needs an account');
	pg_assert_same('registration', $pages['my_projects']['folder'], 'My Projects needs an account');
	pg_assert_same('private', $pages['staff_projects']['folder'], 'the staff list is private');
	pg_assert_true(strpos(json_encode($pages['staff_projects']['tree']), '"staff_bar"') !== false, 'the staff list carries the staff bar');

	$c = $pages['project_view']['comments'];
	pg_assert_true(!empty($c['attachments']), 'messages may carry a file');
	pg_assert_true(!empty($c['login']), 'messages need an account');
	pg_assert_same('{{tab:email_reply}}', $c['email_page'], 'messages mailed with the reply e-mail');
	pg_assert_same('{{site_email}}', $c['notify_email'], 'the developer is told of every message');

	$f = $pages['hire']['form'];
	pg_assert_same(1, $f['auto_registration'], 'a request opens an account');
	pg_assert_same('{{tab:project_view}}', $f['confirmation_page_id'], 'a request opens its page');
	pg_assert_same('{{tab:email_project_new}}', $f['notify_page_id'], 'the developer\'s e-mail');
	pg_assert_same('{{tab:email_project_received}}', $f['confirm_page_id'], 'the client\'s e-mail');
	pg_assert_same(1, $f['confirm_email'], 'the client gets a reply');
	pg_assert_true(strpos($f['notify_subject'], '^^project_type^^') !== false, 'the subject names the kind of app');

	// Every placed widget is defined, on the page it names, once; every
	// defined widget is placed.
	$placed = array();
	foreach ($p['pages'] as $page) {
		foreach (_test_asproj_map_widgets($page['tree']) as $w) $placed[$w][] = $page['key'];
	}
	foreach ($p['widgets'] as $key => $w) {
		pg_assert_same(array($w['page']), $placed[$key] ?? array(), "widget $key placed once, on its page");
	}
	foreach ($placed as $key => $on) pg_assert_true(isset($p['widgets'][$key]), "placed widget $key is defined");

	// The lists, the view and the e-mails read the request form; the lists
	// open the request.
	$controls = _test_asproj_map_controls($p['widgets']['project_form']['tree']);
	foreach ($p['widgets'] as $key => $w) {
		$type = $w['config']['regionType'];
		if ($type === 'form_list_view' || $type === 'form_item_view') {
			pg_assert_same('{{tab:hire}}', $w['config']['custom_form_page_id'], "$key: reads the request form");
		}
		if ($type === 'form_list_view') {
			pg_assert_same('{{tab:project_view}}', $w['config']['detail_page_id'], "$key: opens the request");
			foreach ($w['config']['search_fields'] ?? array() as $field) pg_assert_true(isset($controls[$field]), "$key: searches a field: $field");
		}
	}
	pg_assert_true(!empty($p['widgets']['my_projects_list']['config']['viewer_filter_submitter']), 'My Projects shows the client\'s own');
	pg_assert_true(!empty($p['widgets']['staff_projects_list']['config']['search_enabled']), 'the staff list has a search');
	pg_assert_same('submitter_only', $p['widgets']['project_detail']['config']['access_control'], 'a request is its client\'s to see');
	pg_assert_same('comments', $p['widgets']['project_messages']['config']['regionType'], 'messages are the page\'s comments');
	pg_assert_same('form_item_view', $p['widgets']['email_project_admin']['config']['regionType'], 'the developer\'s e-mail draws the request');
	pg_assert_same('form_item_view', $p['widgets']['email_project_client']['config']['regionType'], 'the client\'s e-mail draws the request');

	// Every field a list, view or e-mail binds is a field of the form, a
	// system value or the record's own token.
	$system = array('submitted_date_and_time', 'number_of_comments', 'reference_code', 'form_item_view', 'not_found',
	                '__new_account_email', '__new_account_set_password_url', '__edit_url');
	foreach (array('project_detail', 'my_projects_list', 'staff_projects_list', 'email_project_admin', 'email_project_client') as $key) {
		$nodes = array();
		_test_asproj_map_nodes($p['widgets'][$key]['tree'], $nodes);
		foreach ($nodes as $node) {
			foreach ($node['props']['_bindings'] ?? array() as $prop => $token) {
				if ($prop === 'eo_visible_if' || $prop === 'section') continue;
				pg_assert_true(isset($controls[$token]) || in_array($token, $system, true), "$key binds a known field: $token");
			}
		}
	}

	// Every {{tab:}} and {{page:}} names a page of the finished template;
	// every {{folder:}} the private folder.
	$known = array_flip(array(
		'home', 'services', 'process', 'about', 'faq', 'press',
		'app_new', 'apps', 'app', 'release_new', 'releases', 'beta', 'beta_view', 'staff_beta',
		'support', 'support_view', 'my_tickets', 'staff_tickets', 'privacy', 'email_reply',
		'hire', 'project_view', 'my_projects', 'staff_projects', 'email_project_new', 'email_project_received',
		'contact', 'inbox', 'conversation', 'newsletter', 'blog', 'blog_post', 'search', 'error',
		'my_account', 'staff', 'blog_new', 'login', 'register', 'forgot_password',
	));
	$json = json_encode($p);
	preg_match_all('/\{\{(tab|page):([a-z0-9_]+)\}\}/', $json, $m);
	foreach ($m[2] as $key) pg_assert_true(isset($known[$key]), "placeholder names a page: $key");
	preg_match_all('/\{\{folder:([a-z0-9_]+)\}\}/', $json, $m);
	pg_assert_same(array('private'), array_values(array_unique($m[1])), 'uploads go to the private folder');

	// The file leaves the parts' samples as they were.
	pg_assert_true(isset($d['apps']['tally']) && isset($d['releases'][0]['app']), 'the samples of parts.php are kept');
}

// The request form's lists are the developer's own answers, start from the
// links' query (?type=, ?package=), and the status is the staff's.
function test_appstudio_project_form()
{
	$d = _test_asproj_appstudio_projects_load();
	$tree = $d['projects']['widgets']['project_form']['tree'];
	$c = _test_asproj_map_controls($tree);
	foreach (array('project_type', 'package', 'app_name', 'about', 'design', 'budget', 'start', 'documents',
	               'contact_method', 'first_name', 'last_name', 'email', 'phone', 'company', 'status', 'consent') as $name) {
		pg_assert_true(isset($c[$name]), "project form: $name");
	}
	pg_assert_same($d['type_choices'], $c['project_type']['options'], 'kind of app answers');
	pg_assert_same($d['package_choices'], $c['package']['options'], 'package answers');
	pg_assert_same($d['budget_choices'], $c['budget']['options'], 'budget answers');
	pg_assert_same($d['start_choices'], $c['start']['options'], 'start answers');
	pg_assert_same(array_column($d['design_choices'], 0), $c['design']['options'], 'design answers');
	pg_assert_same(array_column($d['contact_choices'], 0), $c['contact_method']['options'], 'contact answers');
	pg_assert_same($d['statuses'], $c['status']['options'], 'status answers');
	pg_assert_same('type', $c['project_type']['props']['_cf']['url_param'], 'kind of app from ?type=');
	pg_assert_same('package', $c['package']['props']['_cf']['url_param'], 'package from ?package=');
	pg_assert_true(!empty($c['status']['props']['_cf']['office_use_only']), 'status is office use only');
	pg_assert_same('{{folder:private}}', $c['documents']['props']['_cf']['upload_folder_id'], 'files kept private');
	$reserved = function_exists('pg_sw_url_param_reserved') ? pg_sw_url_param_reserved() : array();
	foreach ($c as $name => $control) {
		if (isset($control['props']['_cf']['url_param'])) pg_assert_false(in_array($control['props']['_cf']['url_param'], $reserved, true), "$name parameter not reserved");
	}

	// Every service's kind is an answer (?type= from the services page), and
	// every package card's link chooses an answer of the Package list.
	foreach ($d['services'] as $key => $s) {
		pg_assert_true(in_array($d['project_types'][$s['type']], $c['project_type']['options'], true), "service $key: its kind is an answer");
	}
	foreach ($d['package_cards'] as $key => $card) {
		$nodes = array();
		_test_asproj_map_nodes($card, $nodes);
		$found = false;
		foreach ($nodes as $node) {
			$href = $node['props']['href'] ?? '';
			if (strpos($href, '{{page:hire}}?') !== 0) continue;
			parse_str(substr($href, strlen('{{page:hire}}?')), $q);
			pg_assert_true(in_array($q['package'] ?? '', $c['package']['options'], true), "package $key: its link chooses an answer");
			$found = true;
		}
		pg_assert_true($found, "package $key links the request");
	}

	// The consent box links the privacy page.
	pg_assert_true(strpos(json_encode($tree), '{{page:privacy}}') !== false, 'consent links the privacy page');

	// Every field id carries the template's prefix and is unique.
	$nodes = array();
	_test_asproj_map_nodes($tree, $nodes);
	$ids = array();
	foreach ($nodes as $node) {
		if (isset($node['props']['id']) && in_array($node['props']['tag'] ?? '', array('input', 'select', 'textarea'), true)) $ids[] = $node['props']['id'];
	}
	foreach ($ids as $id) pg_assert_same(0, strpos($id, 'ma-'), "field id prefixed: $id");
	pg_assert_same(count($ids), count(array_unique($ids)), 'field ids unique');
}

// The e-mails: drawn in the shared frame, each with its button to the
// request's page; the client's says what it opened for them.
function test_appstudio_project_mails()
{
	$d = _test_asproj_appstudio_projects_load();
	foreach ($d['projects']['pages'] as $page) {
		if (strpos($page['key'], 'email_') !== 0) continue;
		$json = json_encode($page['tree']);
		pg_assert_true(strpos($json, '"email_header"') !== false && strpos($json, '"email_footer"') !== false, $page['key'] . ': header and footer');
	}
	foreach (array('email_project_admin', 'email_project_client') as $key) {
		$json = json_encode($d['projects']['widgets'][$key]['tree']);
		pg_assert_true(strpos($json, '{{page:project_view}}?r=^^reference_code^^') !== false, "$key: opens the request");
	}
	pg_assert_true(strpos(json_encode($d['projects']['widgets']['email_project_client']['tree']), 'has_new_account') !== false, 'the reply names the new account');
	$all = json_encode($d['projects']);
	pg_assert_same(0, preg_match('/\b(pf|pre|pl|pdl|pp)-[a-z]/', str_replace(array('pre-wrap'), '', $all)), 'no class of another template');
	pg_assert_same(0, preg_match('/#0f766e|#ccfbf1/', $all), 'no colour of another template');
}
