<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Architecture & Interiors Studio design template's e-mail
 * builders and enquiries (includes/design_templates/pinegrap-architecture/
 * mail.php and enquiries.php): the pages and widgets, the project enquiry
 * and the consultation request opening their own page and mailing both
 * sides, their service starting with what the service pages and the
 * project page send (?service=), the status the studio's, the drawings
 * private, the record pages and lists binding fields their form has, every
 * page link names a template page, ids and classes are the template's own,
 * and every literal has its Turkish.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The page keys of the finished template (_test_ai_page_keys()).
require_once(__DIR__ . '/design_template_architecture_test.php');

// The enquiries' part as the template file reads it: parts.php, mail.php,
// then enquiries.php in the same scope. Read once per run.
function _test_aie_load()
{
	static $out = null;
	if ($out !== null) return $out;
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-architecture';
	include $dir . '/parts.php';
	include $dir . '/mail.php';
	$part = include $dir . '/enquiries.php';
	$public = include $dir . '/pages_public.php';
	$projects_part = include $dir . '/projects.php';
	$pages = array();
	foreach ($part['pages'] as $p) $pages[$p['key']] = $p;
	$out = array('part' => $part, 'pages' => $pages, 'widgets' => $part['widgets'],
		'service_choices' => $enquiry_service_choices, 'project_kinds' => $project_kinds, 'site_choices' => $site_choices,
		'budget_choices' => $budget_choices, 'start_choices' => $start_choices, 'enquiry_statuses' => $enquiry_statuses,
		'places' => $consultation_places, 'consultation_statuses' => $consultation_statuses,
		'contact_choices' => $contact_choices, 'public' => $public, 'projects_part' => $projects_part,
		'shared' => array($email_header_tree(), $email_footer_tree()));
	return $out;
}

function _test_aie_nodes($node, &$out)
{
	if (!is_array($node)) return;
	if (isset($node['props'])) $out[] = $node;
	foreach ((array)($node['children'] ?? array()) as $c) _test_aie_nodes($c, $out);
}

// The name, values and settings of every form control of a tree; radios
// of one name gather their values.
function _test_aie_controls($node)
{
	$nodes = array();
	_test_aie_nodes($node, $nodes);
	$out = array();
	foreach ($nodes as $n) {
		$tag = $n['props']['tag'] ?? '';
		if (!in_array($tag, array('input', 'select', 'textarea'), true)) continue;
		$attrs = array();
		foreach ((array)($n['props']['_attrs'] ?? array()) as $a) $attrs[$a['name']] = $a['value'];
		$name = $attrs['name'] ?? '';
		$options = array();
		foreach ((array)($n['children'] ?? array()) as $o) {
			foreach ((array)($o['props']['_attrs'] ?? array()) as $a) {
				if ($a['name'] === 'value' && $a['value'] !== '') $options[] = $a['value'];
			}
		}
		if (($attrs['type'] ?? '') === 'radio') {
			$out[$name]['tag'] = 'radio';
			$out[$name]['options'][] = $attrs['value'];
			continue;
		}
		$out[$name] = array('tag' => $tag, 'options' => $options, 'cf' => $n['props']['_cf'] ?? array(), 'attrs' => $attrs);
	}
	return $out;
}

// The two forms: the form page, its record page, its lists, its e-mails.
function _test_aie_forms()
{
	return array(
		'enquiry' => array('view' => 'enquiry_view', 'my' => 'my_enquiries', 'staff' => 'staff_enquiries', 'form' => 'enquiry_form',
			'detail' => 'enquiry_detail', 'my_list' => 'my_enquiries_list', 'staff_list' => 'staff_enquiries_list',
			'new' => 'email_enquiry_new', 'received' => 'email_enquiry_received', 'admin' => 'email_enquiry_admin', 'client' => 'email_enquiry_client'),
		'consultation' => array('view' => 'consultation_view', 'my' => 'my_consultations', 'staff' => 'staff_consultations', 'form' => 'consultation_form',
			'detail' => 'consultation_detail', 'my_list' => 'my_consultations_list', 'staff_list' => 'staff_consultations_list',
			'new' => 'email_consultation_new', 'received' => 'email_consultation_received', 'admin' => 'email_consultation_admin', 'client' => 'email_consultation_client'),
	);
}

function test_architecture_enquiries_pages_and_widgets()
{
	$d = _test_aie_load();
	pg_assert_same(array('pages', 'widgets'), array_keys($d['part']), 'the part returns pages and widgets');
	pg_assert_same(array('enquiry', 'enquiry_view', 'my_enquiries', 'staff_enquiries', 'consultation', 'consultation_view', 'my_consultations',
		'staff_consultations', 'privacy', 'email_enquiry_new', 'email_enquiry_received', 'email_consultation_new', 'email_consultation_received',
		'email_reply'), array_keys($d['pages']));
	pg_assert_same(array('enquiry_form', 'enquiry_detail', 'enquiry_messages', 'my_enquiries_list', 'staff_enquiries_list',
		'consultation_form', 'consultation_detail', 'consultation_messages', 'my_consultations_list', 'staff_consultations_list',
		'email_enquiry_admin', 'email_enquiry_client', 'email_consultation_admin', 'email_consultation_client', 'email_comment'), array_keys($d['widgets']));
	pg_assert_same('public', $d['pages']['privacy']['folder']);
	foreach (_test_aie_forms() as $form => $f) {
		pg_assert_same('public', $d['pages'][$form]['folder'], "$form is public");
		pg_assert_same('registration', $d['pages'][$f['view']]['folder'], "{$f['view']} is read signed in");
		pg_assert_same('registration', $d['pages'][$f['my']]['folder']);
		pg_assert_same('private', $d['pages'][$f['staff']]['folder'], "only the staff list every $form");
		pg_assert_same('submitter_only', $d['widgets'][$f['detail']]['config']['access_control'], "a $form is its sender's");
		pg_assert_true(!empty($d['widgets'][$f['my_list']]['config']['viewer_filter_submitter']), "{$f['my_list']} lists the own requests");
		pg_assert_true(!empty($d['widgets'][$f['staff_list']]['config']['search_enabled']), "{$f['staff_list']}: the staff search");
	}
	foreach (array('email_enquiry_new', 'email_enquiry_received', 'email_consultation_new', 'email_consultation_received', 'email_reply') as $key) {
		pg_assert_same('private', $d['pages'][$key]['folder'], "$key is private");
		pg_assert_true(!empty($d['pages'][$key]['noindex']) && $d['pages'][$key]['search'] === false, "$key is out of the search");
	}
	foreach ($d['pages'] as $key => $p) {
		pg_assert_false(isset($p['requires']), "$key stands without the shop");
		pg_assert_same('root', $p['tree']['type'], "$key: a page tree");
		pg_assert_true(mb_strlen((string)($p['meta_description'] ?? ''), 'UTF-8') <= 160, "$key: meta description fits a result line");
	}
	foreach ($d['widgets'] as $key => $w) {
		pg_assert_true(isset($d['pages'][$w['page']]), "$key sits on a page of this part");
		pg_assert_contains('"templateWidget":"' . $key . '"', json_encode($d['pages'][$w['page']]['tree']), "$key is placed on its page");
	}
}

// Each form opens its record page and mails both sides; the status is the
// studio's.
function test_architecture_enquiries_forms_open_and_mail()
{
	$d = _test_aie_load();
	foreach (_test_aie_forms() as $form => $f) {
		$settings = $d['pages'][$form]['form'];
		pg_assert_same('{{tab:' . $f['view'] . '}}', $settings['confirmation_page_id'], "$form opens its page");
		pg_assert_same(1, $settings['auto_registration'], "$form opens an account");
		pg_assert_same('{{tab:' . $f['new'] . '}}', $settings['notify_page_id']);
		pg_assert_same('{{tab:' . $f['received'] . '}}', $settings['confirm_page_id']);
		$c = _test_aie_controls($d['widgets'][$f['form']]['tree']);
		pg_assert_same($d['service_choices'], $c['service']['options'], "$form: the services");
		pg_assert_same('service', $c['service']['cf']['url_param'] ?? '', "$form: the service reads ?service=");
		pg_assert_same(array_column($d['contact_choices'], 0), $c['contact_method']['options'], "$form: the ways to reach the client");
		pg_assert_true(!empty($c['status']['cf']['office_use_only']), "$form: the status is the studio's");
		foreach (array('first_name' => 'first_name', 'last_name' => 'last_name', 'email' => 'email_address', 'phone' => 'mobile_phone') as $name => $contact) {
			pg_assert_same($contact, $c[$name]['cf']['contact_field'] ?? '', "$form: $name fills the contact");
		}
		$comments = $d['pages'][$f['view']]['comments'];
		pg_assert_true($comments['allow_new'] && $comments['login'], "{$f['view']}: messages from the signed-in sender");
		pg_assert_same('{{tab:email_reply}}', $comments['email_page']);
	}
	$e = _test_aie_controls($d['widgets']['enquiry_form']['tree']);
	pg_assert_same(array('service', 'project_type', 'location', 'site', 'size', 'budget', 'start', 'details', 'drawings',
		'first_name', 'last_name', 'email', 'phone', 'contact_method', 'status', 'consent'), array_keys($e), 'the enquiry fields');
	pg_assert_same($d['project_kinds'], $e['project_type']['options']);
	pg_assert_same($d['site_choices'], $e['site']['options']);
	pg_assert_same($d['budget_choices'], $e['budget']['options']);
	pg_assert_same($d['start_choices'], $e['start']['options']);
	pg_assert_same($d['enquiry_statuses'], $e['status']['options']);
	pg_assert_same('{{folder:private}}', $e['drawings']['cf']['upload_folder_id'] ?? '', 'drawings and photos are private');
	$c = _test_aie_controls($d['widgets']['consultation_form']['tree']);
	pg_assert_same(array('service', 'place', 'days', 'details', 'first_name', 'last_name', 'email', 'phone', 'contact_method', 'status', 'consent'),
		array_keys($c), 'the consultation fields');
	pg_assert_same($d['places'], $c['place']['options'], 'where to meet');
	pg_assert_same('text', $c['days']['attrs']['type'], 'the days are words, no calendar');
	pg_assert_same($d['consultation_statuses'], $c['status']['options']);
}

// Every ?service= a link sends is an answer of the form it opens: the
// service pages and the project page.
function test_architecture_enquiries_service_links()
{
	$d = _test_aie_load();
	$options = _test_aie_controls($d['widgets']['enquiry_form']['tree'])['service']['options'];
	$seen = 0;
	foreach ($d['public']['pages'] as $p) {
		$nodes = array();
		_test_aie_nodes($p['tree'], $nodes);
		foreach ($nodes as $n) {
			$href = (string)($n['props']['href'] ?? '');
			if (!preg_match('/^\{\{page:(enquiry|consultation)\}\}\?(.*)$/', $href, $m)) continue;
			parse_str($m[2], $q);
			pg_assert_same(array('service'), array_keys($q), "{$p['key']}: the link sends the service");
			pg_assert_true(in_array($q['service'], $options, true), "?service={$q['service']} is an answer");
			$seen++;
		}
	}
	pg_assert_true($seen >= 12, 'every service page links both forms with its service');
	pg_assert_contains('"page_id":"{{tab:enquiry}}","params":[{"name":"service","token":"service"}]',
		json_encode($d['projects_part']['widgets']['project_detail']['tree'], JSON_UNESCAPED_SLASHES), 'the project page sends its service');
}

// The record pages, the lists and the e-mails bind fields their form has.
function test_architecture_enquiries_bindings()
{
	$d = _test_aie_load();
	foreach (_test_aie_forms() as $form => $f) {
		$fields = array_keys(_test_aie_controls($d['widgets'][$f['form']]['tree']));
		foreach (array('detail', 'my_list', 'staff_list', 'admin', 'client') as $role) {
			$key = $f[$role];
			$nodes = array();
			_test_aie_nodes($d['widgets'][$key]['tree'], $nodes);
			foreach ($nodes as $n) {
				foreach (array('text', 'href') as $prop) {
					$token = (string)($n['props']['_bindings'][$prop] ?? '');
					if ($token === '' || strpos($token, '__') === 0 || in_array($token, array('reference_code', 'not_found', 'number_of_comments', 'form_item_view'), true)) continue;
					pg_assert_true(in_array($token, $fields, true), "$key binds $token, a field of the $form form");
				}
			}
			pg_assert_same('{{tab:' . $form . '}}', $d['widgets'][$key]['config']['custom_form_page_id'], "$key reads the $form form");
		}
		foreach ($d['widgets'][$f['staff_list']]['config']['search_fields'] as $name) {
			pg_assert_true(in_array($name, $fields, true), "{$f['staff_list']} searches $name");
		}
		$json = json_encode($d['widgets'][$f['detail']]['tree']);
		pg_assert_contains('"eo_visible_if":"record_found"', $json, "{$f['detail']}: the record");
		pg_assert_contains('"eo_visible_if":"record_not_found"', $json, "{$f['detail']}: the not-found note");
		foreach (array('admin', 'client') as $role) {
			pg_assert_same('public', $d['widgets'][$f[$role]]['config']['access_control'], "{$f[$role]} is drawn for the submission just sent");
			pg_assert_contains('{{page:' . $f['view'] . '}}?r=^^reference_code^^', json_encode($d['widgets'][$f[$role]]['tree'], JSON_UNESCAPED_SLASHES), "{$f[$role]} opens the request");
		}
	}
	pg_assert_contains('"regionType":"system"', json_encode($d['pages']['email_reply']['tree']), 'the reply e-mail keeps the fixed comment');
}

function test_architecture_enquiries_links_ids_and_classes()
{
	$d = _test_aie_load();
	$known = array_flip(_test_ai_page_keys());
	$trees = $d['shared'];
	foreach ($d['pages'] as $p) $trees[] = $p;
	foreach ($d['widgets'] as $w) $trees[] = array($w['config'], $w['tree']);
	$json = json_encode($trees);
	preg_match_all('/\{\{(?:page|tab):([a-z0-9_]+)\}\}/', $json, $m);
	foreach (array_unique($m[1]) as $key) pg_assert_true(isset($known[$key]), "page link {{page:$key}} names a template page");
	foreach (array('product', 'cart', 'checkout', 'order', 'email_order') as $key) {
		pg_assert_false(strpos($json, '{{page:' . $key . '}}') !== false, "no tree links to $key");
	}
	$nodes = array();
	foreach ($d['shared'] as $t) _test_aie_nodes($t, $nodes);
	foreach ($d['pages'] as $p) _test_aie_nodes($p['tree'], $nodes);
	foreach ($d['widgets'] as $w) _test_aie_nodes($w['tree'], $nodes);
	foreach ($nodes as $n) {
		foreach (preg_split('/\s+/', (string)($n['props']['cssClass'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $c) {
			pg_assert_false((bool)preg_match('/^p[a-z]{2}-/', $c) && strpos($c, 'pai-') !== 0, "class $c is not another template's");
			pg_assert_false((bool)preg_match('/^(text-)?opacity-/', $c), "no faded text class $c");
			pg_assert_false((bool)preg_match('/^rounded-(?!0$|circle$)/', $c), "square corners: $c");
		}
		$id = (string)($n['props']['id'] ?? '');
		if ($id !== '' && strpos($id, '-') !== false) pg_assert_true(strpos($id, 'ai-') === 0, "id $id is the template's");
	}
}

// Every literal has its Turkish; an enquiry reads "proje talebi" and a
// consultation "ön görüşme".
function test_architecture_enquiries_turkish_words()
{
	$map = pg_test_language_map('tr');
	foreach (array('mail.php', 'enquiries.php') as $file) {
		$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-architecture/' . $file);
		preg_match_all("/lang\\('((?:[^'\\\\]|\\\\.)*)'\\)/", $src, $m);
		foreach (array_unique($m[1]) as $raw) {
			$key = stripslashes($raw);
			pg_assert_true(isset($map[$key]) && $map[$key] !== '', "$file: Turkish for '$key'");
			if (!isset($map[$key])) continue;
			$tr = $map[$key];
			$words = preg_replace('/\^\^[a-z_]+\^\^/', '', $key);
			if (strpos($words, '-') !== false && strpos($words, ' ') === false) continue;
			// Shared with other templates' buttons and the staff bar (parts.php).
			if (in_array($key, array('Book a Consultation', 'Consultation Requests'), true)) continue;
			if (preg_match('/\bproject enquir(y|ies)\b/i', $words)) pg_assert_true(mb_stripos($tr, 'proje tale', 0, 'UTF-8') !== false, "'$key' says proje talebi");
			if (preg_match('/\bconsultations?\b/i', $words)) pg_assert_true(mb_stripos($tr, 'ön görüşme', 0, 'UTF-8') !== false, "'$key' says ön görüşme");
		}
	}
}
