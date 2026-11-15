<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Film Studio design template's e-mail builders and requests
 * (includes/design_templates/pinegrap-filmstudio/mail.php and
 * enquiries.php): the pages and widgets, the project enquiry (its fields,
 * the service read from ?service= as the service pages and the project page
 * send it, the office-only status, the private brief) and the studio hire
 * request (the space read from ?space= as the space cards send it, a day
 * never in the past), the record pages with their messages, the lists and
 * e-mails binding fields their form has, the e-mail builders in the
 * template's colours with their fallbacks, every page link naming a
 * template page, ids and classes the template's own, no fixed date and
 * every literal with its Turkish.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The page keys of the finished template (_test_fm_page_keys()).
require_once(__DIR__ . '/design_template_filmstudio_test.php');

// The requests' part as the template file reads it: parts.php, mail.php,
// then enquiries.php in the same scope; the public pages and the projects
// for the links that open the forms. Read once per run.
function _test_fme_load()
{
	static $out = null;
	if ($out !== null) return $out;
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-filmstudio';
	include $dir . '/parts.php';
	include $dir . '/mail.php';
	$part = include $dir . '/enquiries.php';
	$public = include $dir . '/pages_public.php';
	$projects_part = include $dir . '/projects.php';
	$pages = array();
	foreach ($part['pages'] as $p) $pages[$p['key']] = $p;
	$out = array('part' => $part, 'pages' => $pages, 'widgets' => $part['widgets'],
		'service_choices' => $enquiry_service_choices, 'screens' => $screen_choices, 'lengths' => $length_choices,
		'budgets' => $budget_choices, 'deliveries' => $delivery_choices, 'statuses' => $enquiry_statuses,
		'spaces' => $space_titles, 'hire_lengths' => $hire_lengths, 'hire_statuses' => $hire_statuses,
		'contact_choices' => $contact_choices, 'public' => $public, 'projects_part' => $projects_part,
		'builders' => array(
			'mail_style' => $mail_style, 'mail_page' => $mail_page, 'mail_heading' => $mail_heading, 'mail_para' => $mail_para,
			'mail_button' => $mail_button, 'mail_line' => $mail_line, 'mail_box' => $mail_box, 'mail_lines' => $mail_lines,
			'mail_greeting' => $mail_greeting, 'mail_reference' => $mail_reference, 'mail_new_account' => $mail_new_account,
			'email_header_tree' => $email_header_tree, 'email_footer_tree' => $email_footer_tree,
		),
		'mail_comment_tree' => $mail_comment_tree, 'mail_settings' => $mail_settings,
		'shared' => array($email_header_tree(), $email_footer_tree()));
	return $out;
}

function _test_fme_nodes($node, &$out)
{
	if (!is_array($node)) return;
	if (isset($node['props'])) $out[] = $node;
	foreach ((array)($node['children'] ?? array()) as $c) _test_fme_nodes($c, $out);
}

// The tag, options and settings of every form control of a tree, with the
// attributes of the first control of each name.
function _test_fme_controls($node)
{
	$nodes = array();
	_test_fme_nodes($node, $nodes);
	$out = array();
	foreach ($nodes as $n) {
		$tag = $n['props']['tag'] ?? '';
		if (!in_array($tag, array('input', 'select', 'textarea'), true)) continue;
		$attrs = array();
		foreach ((array)($n['props']['_attrs'] ?? array()) as $a) $attrs[$a['name']] = $a['value'];
		$name = $attrs['name'] ?? '';
		if (!isset($out[$name])) $out[$name] = array('tag' => $tag, 'attrs' => $attrs, 'options' => array(), 'cf' => $n['props']['_cf'] ?? array());
		foreach ((array)($n['children'] ?? array()) as $o) {
			foreach ((array)($o['props']['_attrs'] ?? array()) as $a) {
				if ($a['name'] === 'value' && $a['value'] !== '') $out[$name]['options'][] = $a['value'];
			}
		}
		// A radio group: each button's value is an answer.
		if ($tag === 'input' && ($attrs['type'] ?? '') === 'radio') $out[$name]['options'][] = $attrs['value'];
	}
	return $out;
}

// The query of every link to {{page:<key>}} with a query in the trees.
function _test_fme_queries($trees, $key)
{
	$prefix = '{{page:' . $key . '}}?';
	$out = array();
	foreach ($trees as $tree) {
		$nodes = array();
		_test_fme_nodes($tree, $nodes);
		foreach ($nodes as $n) {
			$href = (string)($n['props']['href'] ?? '');
			if (strpos($href, $prefix) !== 0) continue;
			parse_str(substr($href, strlen($prefix)), $q);
			$out[] = $q;
		}
	}
	return $out;
}

function test_filmstudio_enquiries_pages_and_widgets()
{
	$d = _test_fme_load();
	pg_assert_same(array('pages', 'widgets'), array_keys($d['part']), 'the part returns pages and widgets');
	pg_assert_same(array('enquiry', 'enquiry_view', 'my_enquiries', 'staff_enquiries',
		'studio_hire', 'studio_hire_view', 'my_studio_hires', 'staff_studio_hires', 'privacy',
		'email_enquiry_new', 'email_enquiry_received', 'email_studio_hire_new', 'email_studio_hire_received', 'email_reply'), array_keys($d['pages']));
	pg_assert_same(array('enquiry_form', 'enquiry_detail', 'enquiry_messages', 'my_enquiries_list', 'staff_enquiries_list',
		'email_enquiry_admin', 'email_enquiry_client',
		'studio_hire_form', 'studio_hire_detail', 'studio_hire_messages', 'my_studio_hires_list', 'staff_studio_hires_list',
		'email_studio_hire_admin', 'email_studio_hire_client', 'email_comment'), array_keys($d['widgets']));
	foreach (array('enquiry', 'studio_hire', 'privacy') as $key) pg_assert_same('public', $d['pages'][$key]['folder'], "$key is public");
	foreach (array('enquiry_view', 'my_enquiries', 'studio_hire_view', 'my_studio_hires') as $key) {
		pg_assert_same('registration', $d['pages'][$key]['folder'], "$key is read signed in");
	}
	foreach (array('staff_enquiries', 'staff_studio_hires', 'email_enquiry_new', 'email_enquiry_received',
		'email_studio_hire_new', 'email_studio_hire_received', 'email_reply') as $key) {
		pg_assert_same('private', $d['pages'][$key]['folder'], "$key is private");
		pg_assert_true($d['pages'][$key]['search'] === false, "$key is out of the search");
	}
	$names = array();
	foreach ($d['pages'] as $key => $p) {
		pg_assert_false(isset($p['requires']), "$key stands without the shop");
		pg_assert_same('root', $p['tree']['type'], "$key: a page tree");
		pg_assert_true(mb_strlen((string)($p['meta_description'] ?? ''), 'UTF-8') <= 160, "$key: meta description fits a result line");
		pg_assert_false(isset($names[$p['name']]), "$key: an address of its own");
		$names[$p['name']] = true;
	}
	foreach ($d['widgets'] as $key => $w) {
		pg_assert_true(isset($d['pages'][$w['page']]), "$key sits on a page of this part");
		pg_assert_contains('"templateWidget":"' . $key . '"', json_encode($d['pages'][$w['page']]['tree']), "$key is placed on its page");
	}
	foreach (array('enquiry_detail', 'studio_hire_detail') as $key) {
		pg_assert_same('submitter_only', $d['widgets'][$key]['config']['access_control'], "$key is its sender's");
	}
	foreach (array('my_enquiries_list', 'my_studio_hires_list') as $key) {
		pg_assert_true(!empty($d['widgets'][$key]['config']['viewer_filter_submitter']), "$key lists the own records");
	}
	foreach (array('staff_enquiries_list', 'staff_studio_hires_list') as $key) {
		pg_assert_true(!empty($d['widgets'][$key]['config']['search_enabled']), "$key: the staff search");
	}
	pg_assert_true($d['pages']['enquiry']['form']['form_name'] !== $d['pages']['studio_hire']['form']['form_name'], 'a form each');
}

// The enquiry: its fields, the service from ?service= as the service pages
// and the project page send it, the office-only status, the private brief,
// the contact fields and the consent linking the privacy page.
function test_filmstudio_enquiries_enquiry_form()
{
	$d = _test_fme_load();
	$form = $d['pages']['enquiry']['form'];
	pg_assert_same('{{tab:enquiry_view}}', $form['confirmation_page_id']);
	pg_assert_same(1, $form['auto_registration']);
	pg_assert_same('{{tab:email_enquiry_new}}', $form['notify_page_id']);
	pg_assert_same('{{tab:email_enquiry_received}}', $form['confirm_page_id']);
	$c = _test_fme_controls($d['widgets']['enquiry_form']['tree']);
	pg_assert_same(array('service', 'screen', 'purpose', 'length', 'budget', 'delivery', 'details', 'brief',
		'first_name', 'last_name', 'email', 'phone', 'company', 'contact_method', 'status', 'consent'), array_keys($c));
	pg_assert_same($d['service_choices'], $c['service']['options'], 'the services');
	pg_assert_same('service', $c['service']['cf']['url_param'] ?? '', 'the service reads ?service=');
	pg_assert_same($d['screens'], $c['screen']['options'], 'where the film is shown');
	pg_assert_same($d['lengths'], $c['length']['options'], 'the lengths');
	pg_assert_same($d['budgets'], $c['budget']['options'], 'the budget bands');
	pg_assert_same($d['deliveries'], $c['delivery']['options'], 'the delivery windows');
	pg_assert_same($d['statuses'], $c['status']['options'], 'the statuses');
	pg_assert_true(!empty($c['status']['cf']['office_use_only']), 'the status is the studio\'s');
	pg_assert_same('{{folder:private}}', $c['brief']['cf']['upload_folder_id'] ?? '', 'the brief is private');
	pg_assert_same(array_column($d['contact_choices'], 0), $c['contact_method']['options'], 'the ways to reach the client');
	foreach (array('first_name' => 'first_name', 'last_name' => 'last_name', 'email' => 'email_address', 'phone' => 'mobile_phone') as $name => $contact) {
		pg_assert_same($contact, $c[$name]['cf']['contact_field'] ?? '', "$name fills the contact");
	}
	pg_assert_contains('{{page:privacy}}', json_encode($d['widgets']['enquiry_form']['tree']), 'the consent links the privacy page');
	// Every ?service= a link sends is an answer; every service page sends one.
	$trees = array();
	foreach ($d['public']['pages'] as $p) $trees[] = $p['tree'];
	$queries = _test_fme_queries($trees, 'enquiry');
	foreach ($queries as $q) {
		pg_assert_same(array('service'), array_keys($q), 'the enquiry link sends the service');
		pg_assert_true(in_array($q['service'], $c['service']['options'], true), "?service={$q['service']} is an answer");
	}
	pg_assert_true(count($queries) >= 6, 'every service page starts an enquiry with its service');
	pg_assert_contains('"page_id":"{{tab:enquiry}}","params":[{"name":"service","token":"service"}]',
		json_encode($d['projects_part']['widgets']['project_detail']['tree'], JSON_UNESCAPED_SLASHES), 'the project page sends its service');
}

// The studio hire request: the space from ?space= as the space cards send
// it, a day never in the past, the office-only status.
function test_filmstudio_enquiries_studio_hire_form()
{
	$d = _test_fme_load();
	$form = $d['pages']['studio_hire']['form'];
	pg_assert_same('{{tab:studio_hire_view}}', $form['confirmation_page_id']);
	pg_assert_same(1, $form['auto_registration']);
	pg_assert_same('{{tab:email_studio_hire_new}}', $form['notify_page_id']);
	pg_assert_same('{{tab:email_studio_hire_received}}', $form['confirm_page_id']);
	$c = _test_fme_controls($d['widgets']['studio_hire_form']['tree']);
	pg_assert_same(array('space', 'preferred_date', 'hire_length', 'crew_size', 'kit', 'notes',
		'first_name', 'last_name', 'email', 'phone', 'company', 'contact_method', 'status', 'consent'), array_keys($c));
	pg_assert_same($d['spaces'], $c['space']['options'], 'the spaces');
	pg_assert_same('space', $c['space']['cf']['url_param'] ?? '', 'the space reads ?space=');
	pg_assert_same('date', $c['preferred_date']['attrs']['type'] ?? '', 'the day is a date');
	pg_assert_same('today', $c['preferred_date']['cf']['rules']['min_date'] ?? '', 'never a day in the past');
	pg_assert_same('number', $c['crew_size']['cf']['kind'] ?? '', 'the crew is a number');
	pg_assert_same($d['hire_lengths'], $c['hire_length']['options'], 'the lengths');
	pg_assert_same($d['hire_statuses'], $c['status']['options'], 'the statuses');
	pg_assert_true(!empty($c['status']['cf']['office_use_only']), 'the status is the studio\'s');
	pg_assert_contains('{{page:privacy}}', json_encode($d['widgets']['studio_hire_form']['tree']), 'the consent links the privacy page');
	$trees = array();
	foreach ($d['public']['pages'] as $p) $trees[] = $p['tree'];
	$queries = _test_fme_queries($trees, 'studio_hire');
	foreach ($queries as $q) {
		pg_assert_same(array('space'), array_keys($q), 'the hire link sends the space');
		pg_assert_true(in_array($q['space'], $c['space']['options'], true), "?space={$q['space']} is an answer");
	}
	pg_assert_true(count($queries) >= count($d['spaces']), 'every space card asks for its space');
}

// The record pages carry their messages and mail each one; the pages, the
// lists and the e-mails bind fields their form has.
function test_filmstudio_enquiries_record_and_mail()
{
	$d = _test_fme_load();
	$cases = array(
		'enquiry'     => array('enquiry_view', 'my_enquiries_list', 'staff_enquiries_list', 'email_enquiry_admin', 'email_enquiry_client'),
		'studio_hire' => array('studio_hire_view', 'my_studio_hires_list', 'staff_studio_hires_list', 'email_studio_hire_admin', 'email_studio_hire_client'),
	);
	$renderer = array('reference_code', 'not_found', 'number_of_comments', 'form_item_view');
	foreach ($cases as $key => $c) {
		list($view, $mine, $staff, $admin, $client) = $c;
		$comments = $d['pages'][$view]['comments'];
		pg_assert_true($comments['allow_new'] && $comments['login'], "$view: messages from the signed-in sender");
		pg_assert_same('{{tab:email_reply}}', $comments['email_page']);
		pg_assert_same('{{site_email}}', $comments['notify_email'], "$view: the studio hears of each message");
		pg_assert_contains('"templateWidget":"' . $key . '_messages"', json_encode($d['pages'][$view]['tree']), "$view: the messages under the record");
		$fields = array_keys(_test_fme_controls($d['widgets'][$key . '_form']['tree']));
		foreach (array($key . '_detail', $mine, $staff, $admin, $client) as $w) {
			pg_assert_same('{{tab:' . $key . '}}', $d['widgets'][$w]['config']['custom_form_page_id'], "$w reads the form of $key");
			$nodes = array();
			_test_fme_nodes($d['widgets'][$w]['tree'], $nodes);
			foreach ($nodes as $n) {
				foreach (array('text', 'href', 'src') as $prop) {
					$token = (string)($n['props']['_bindings'][$prop] ?? '');
					if ($token === '' || strpos($token, '__') === 0 || in_array($token, $renderer, true)) continue;
					pg_assert_true(in_array($token, $fields, true), "$w binds $token, a field of the form");
				}
			}
		}
		foreach (array($mine, $staff) as $w) {
			pg_assert_same('{{tab:' . $view . '}}', $d['widgets'][$w]['config']['detail_page_id'], "$w opens the record");
		}
		foreach ($d['widgets'][$staff]['config']['search_fields'] as $name) {
			pg_assert_true(in_array($name, $fields, true), "$staff searches $name");
		}
		$json = json_encode($d['widgets'][$key . '_detail']['tree']);
		pg_assert_contains('"eo_visible_if":"record_found"', $json, "$view: the record");
		pg_assert_contains('"eo_visible_if":"record_not_found"', $json, "$view: the not-found note");
		pg_assert_contains('"eo_visible_if":"can_edit"', $json, "$view: the staff change the status");
		foreach (array($admin, $client) as $w) {
			pg_assert_same('public', $d['widgets'][$w]['config']['access_control'], "$w is drawn for the submission just sent");
			pg_assert_contains('{{page:' . $view . '}}?r=^^reference_code^^', json_encode($d['widgets'][$w]['tree'], JSON_UNESCAPED_SLASHES), "$w opens the record");
		}
		pg_assert_contains('"reference_code"', json_encode($d['widgets'][$admin]['tree']), "$admin ends with the reference");
		pg_assert_contains('"has_new_account"', json_encode($d['widgets'][$client]['tree']), "$client names the account opened");
	}
	pg_assert_contains('"regionType":"system"', json_encode($d['pages']['email_reply']['tree']), 'the reply e-mail keeps the fixed comment');
	pg_assert_same($d['mail_comment_tree'], $d['widgets']['email_comment']['tree'], 'the reply e-mail draws the comment card');
}

// mail.php exposes the builders every e-mail of the template shares, in
// the midnight palette: every colour one of its family, and a colour given
// through var() keeps its fallback for a client that blocks the CSS.
function test_filmstudio_enquiries_mail_builders()
{
	$d = _test_fme_load();
	foreach ($d['builders'] as $name => $fn) pg_assert_true(is_callable($fn), "$name is a builder");
	pg_assert_same(array('folder' => 'private', 'search' => false, 'sitemap' => false, 'noindex' => true), $d['mail_settings']);
	$b = $d['builders'];
	$frame = json_encode($b['mail_page'](array()));
	pg_assert_contains('"templateShared":"email_header"', $frame, 'the frame places the header');
	pg_assert_contains('"templateShared":"email_footer"', $frame, 'the frame places the footer');
	pg_assert_contains('"__comment_url"', json_encode($d['mail_comment_tree']), 'the comment card links the comment');
	$family = array('#1e3a8a', '#bfdbfe', '#eff6ff', '#ffffff', '#1f2937', '#4b5563', '#b45309');
	$trees = array($d['shared'], $d['mail_comment_tree'], $b['mail_button']('x', '#'), $b['mail_new_account'](), $b['mail_reference'](),
		$b['mail_greeting'](), $b['mail_lines'](array(array('a', 'b', 'c')), 'n'));
	foreach ($d['pages'] as $key => $p) if (strpos($key, 'email_') === 0) $trees[] = $p['tree'];
	foreach ($d['widgets'] as $key => $w) if (strpos($key, 'email_') === 0) $trees[] = $w['tree'];
	$styles = '';
	$nodes = array();
	foreach ($trees as $t) _test_fme_nodes(array('children' => array($t)), $nodes);
	foreach ($nodes as $n) {
		foreach ((array)($n['props']['_attrs'] ?? array()) as $a) if ($a['name'] === 'style') $styles .= $a['value'] . ';';
	}
	$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-filmstudio/mail.php');
	preg_match_all('/#[0-9a-f]{3,6}\b/i', $styles . $src, $m);
	pg_assert_true(count($m[0]) > 10, 'colours found');
	foreach (array_unique($m[0]) as $hex) pg_assert_true(in_array(strtolower($hex), $family, true), "e-mail colour $hex is the template's");
	preg_match_all('/var\((?=-)([^)]*)\)/', $styles . $src, $v);
	pg_assert_true(count($v[1]) >= 3, 'colours through var()');
	foreach ($v[1] as $inner) {
		pg_assert_true((bool)preg_match('/^--[a-z0-9-]+,\s*#[0-9a-f]{6}$/i', $inner), "var($inner) keeps its fallback");
	}
	pg_assert_contains('background-color:#1e3a8a;background-color:var(--bs-primary,#1e3a8a)', $styles, 'the button is the palette\'s blue');
}

function test_filmstudio_enquiries_links_ids_and_classes()
{
	$d = _test_fme_load();
	$known = array_flip(_test_fm_page_keys());
	$trees = $d['shared'];
	foreach ($d['pages'] as $p) $trees[] = $p;
	foreach ($d['widgets'] as $w) $trees[] = array($w['config'], $w['tree']);
	$json = json_encode($trees);
	preg_match_all('/\{\{(?:page|tab):([a-z0-9_]+)\}\}/', $json, $m);
	foreach (array_unique($m[1]) as $key) pg_assert_true(isset($known[$key]), "page link {{page:$key}} names a template page");
	foreach ($d['pages'] as $key => $p) pg_assert_true(isset($known[$key]), "$key is a page of the finished template");
	foreach (array('product', 'cart', 'checkout', 'order', 'email_order') as $key) {
		pg_assert_false(strpos($json, '{{page:' . $key . '}}') !== false, "no tree links to $key");
	}
	$nodes = array();
	foreach ($d['shared'] as $t) _test_fme_nodes($t, $nodes);
	foreach ($d['pages'] as $p) _test_fme_nodes($p['tree'], $nodes);
	foreach ($d['widgets'] as $w) _test_fme_nodes($w['tree'], $nodes);
	foreach ($nodes as $n) {
		foreach (preg_split('/\s+/', (string)($n['props']['cssClass'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $c) {
			pg_assert_false((bool)preg_match('/^(pht|pre|pev|pf|pdp|pm|pma|plx|pdl|plw|psl|pco|pcl|pgy|pch|ptv|pph|pwd|pcn|pmu|psc|psh|pmz|pai|pcy)-/', $c), "class $c is not another template's");
			pg_assert_false((bool)preg_match('/^(text-)?opacity-/', $c), "no faded text class $c");
		}
		$id = (string)($n['props']['id'] ?? '');
		if ($id !== '' && strpos($id, '-') !== false) pg_assert_true(strpos($id, 'fm-') === 0, "id $id is the template's");
	}
}

// The sample is never dated: the day of a hire is "Tuesday next week", a
// delivery a window in words.
function test_filmstudio_enquiries_no_fixed_dates()
{
	$d = _test_fme_load();
	$json = json_encode(array($d['pages'], $d['widgets']), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	// The SVG namespace of the drawn stars is not a date.
	$json = str_replace('www.w3.org/2000/svg', '', $json);
	pg_assert_false((bool)preg_match('/\b(19|20)[0-9]{2}\b/', $json), 'no year in the sample');
	pg_assert_false((bool)preg_match('/\b(January|February|March|April|June|July|August|September|October|November|December)\b/', $json), 'no month in the sample');
}

// Every literal has its Turkish; an enquiry reads "talep" (or "talebi"),
// a studio hire "stüdyo kiralama", a treatment "film önerisi", the shoot
// "çekim".
function test_filmstudio_enquiries_turkish_words()
{
	$map = pg_test_language_map('tr');
	foreach (array('mail.php', 'enquiries.php') as $file) {
		$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-filmstudio/' . $file);
		preg_match_all("/lang\\('((?:[^'\\\\]|\\\\.)*)'\\)/", $src, $m);
		foreach (array_unique($m[1]) as $raw) {
			$key = stripslashes($raw);
			pg_assert_true(isset($map[$key]) && $map[$key] !== '', "$file: Turkish for '$key'");
			if (!isset($map[$key])) continue;
			$tr = $map[$key];
			// A ^^field^^ token is the field's name, not a word; a page name
			// (with hyphens) is a web address.
			$words = preg_replace('/\^\^[a-z_]+\^\^/', '', $key);
			if (strpos($words, '-') !== false && strpos($words, ' ') === false) continue;
			if (preg_match('/\benquir(y|ies)\b/i', $words)) pg_assert_true((bool)preg_match('/tale[pb]/iu', $tr), "'$key' says talep");
			if (preg_match('/\bstudio hire\b/i', $words)) pg_assert_true(mb_stripos($tr, 'stüdyo kiralama', 0, 'UTF-8') !== false, "'$key' says stüdyo kiralama");
			if (preg_match('/\btreatment\b/i', $words)) pg_assert_true(mb_stripos($tr, 'film öneri', 0, 'UTF-8') !== false, "'$key' says film önerisi");
			if (preg_match('/\b(the|a|your) shoot\b/i', $words)) pg_assert_true(mb_stripos($tr, 'çekim', 0, 'UTF-8') !== false, "'$key' says çekim");
		}
	}
}
