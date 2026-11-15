<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Private School design template's e-mail builders and
 * admissions (includes/design_templates/pinegrap-school/mail.php and
 * admissions.php): the pages and widgets, the application opening its own
 * page and mailing both sides, its programme starting with what the
 * programme pages send (?programme=), the visit request mailing both
 * sides, the statuses the school's, the documents private, the record
 * page, lists and e-mails binding fields their form has, every page link
 * names a template page, ids and classes are the template's own, and every
 * literal has its Turkish.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The page keys of the finished template (_test_sh_page_keys()).
require_once(__DIR__ . '/design_template_school_test.php');

// The admissions' part as the template file reads it: parts.php, mail.php,
// then admissions.php in the same scope. Read once per run.
function _test_sha_load()
{
	static $out = null;
	if ($out !== null) return $out;
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-school';
	include $dir . '/parts.php';
	include $dir . '/mail.php';
	$part = include $dir . '/admissions.php';
	$public = include $dir . '/pages_public.php';
	$pages = array();
	foreach ($part['pages'] as $p) $pages[$p['key']] = $p;
	$out = array('part' => $part, 'pages' => $pages, 'widgets' => $part['widgets'],
		'programme_choices' => $application_programme_choices, 'start_choices' => $start_choices,
		'relation_choices' => $relation_choices, 'statuses' => $application_statuses,
		'visit_times' => $visit_times, 'visitor_counts' => $visitor_counts, 'visit_statuses' => $visit_statuses,
		'contact_choices' => $contact_choices, 'public' => $public,
		'shared' => array($email_header_tree(), $email_footer_tree()));
	return $out;
}

function _test_sha_nodes($node, &$out)
{
	if (!is_array($node)) return;
	if (isset($node['props'])) $out[] = $node;
	foreach ((array)($node['children'] ?? array()) as $c) _test_sha_nodes($c, $out);
}

// The name, values and settings of every form control of a tree; radios
// of one name gather their values.
function _test_sha_controls($node)
{
	$nodes = array();
	_test_sha_nodes($node, $nodes);
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

function test_school_admissions_pages_and_widgets()
{
	$d = _test_sha_load();
	pg_assert_same(array('pages', 'widgets'), array_keys($d['part']), 'the part returns pages and widgets');
	pg_assert_same(array('apply', 'application_view', 'my_applications', 'staff_applications', 'visit', 'staff_visits', 'privacy',
		'email_application_new', 'email_application_received', 'email_visit_new', 'email_visit_received', 'email_reply'), array_keys($d['pages']));
	pg_assert_same(array('application_form', 'application_detail', 'application_messages', 'my_applications_list', 'staff_applications_list',
		'visit_form', 'staff_visits_list', 'email_application_admin', 'email_application_parent', 'email_visit_admin', 'email_visit_parent',
		'email_comment'), array_keys($d['widgets']));
	foreach (array('apply', 'visit', 'privacy') as $key) pg_assert_same('public', $d['pages'][$key]['folder'], "$key is public");
	pg_assert_same('registration', $d['pages']['application_view']['folder'], 'an application is read signed in');
	pg_assert_same('registration', $d['pages']['my_applications']['folder']);
	foreach (array('staff_applications', 'staff_visits') as $key) pg_assert_same('private', $d['pages'][$key]['folder'], "only the staff open $key");
	foreach (array('email_application_new', 'email_application_received', 'email_visit_new', 'email_visit_received', 'email_reply') as $key) {
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
	pg_assert_same('submitter_only', $d['widgets']['application_detail']['config']['access_control'], 'an application is its sender\'s');
	pg_assert_true(!empty($d['widgets']['my_applications_list']['config']['viewer_filter_submitter']), 'My Applications lists the own applications');
	foreach (array('staff_applications_list', 'staff_visits_list') as $key) {
		pg_assert_true(!empty($d['widgets'][$key]['config']['search_enabled']), "$key: the staff search");
	}
	pg_assert_false(isset($d['widgets']['staff_visits_list']['config']['detail_page_id']), 'a visit request has no page of its own');
}

// The application opens its page and mails both sides; its programme
// starts with what the programme pages send, the documents are private
// and the status is the school's.
function test_school_admissions_application_form()
{
	$d = _test_sha_load();
	$form = $d['pages']['apply']['form'];
	pg_assert_same('{{tab:application_view}}', $form['confirmation_page_id']);
	pg_assert_same(1, $form['auto_registration']);
	pg_assert_same('{{tab:email_application_new}}', $form['notify_page_id']);
	pg_assert_same('{{tab:email_application_received}}', $form['confirm_page_id']);
	$c = _test_sha_controls($d['widgets']['application_form']['tree']);
	pg_assert_same(array('child_first_name', 'child_last_name', 'child_birth_date', 'current_school', 'programme', 'start', 'notes', 'documents',
		'first_name', 'last_name', 'relation', 'phone', 'email', 'contact_method', 'status', 'consent'), array_keys($c), 'the fields');
	pg_assert_same($d['programme_choices'], $c['programme']['options'], 'the programmes');
	pg_assert_same('programme', $c['programme']['cf']['url_param'] ?? '', 'the programme reads ?programme=');
	pg_assert_same($d['start_choices'], $c['start']['options'], 'the starts');
	pg_assert_same($d['relation_choices'], $c['relation']['options'], 'the relations');
	pg_assert_same(array_column($d['contact_choices'], 0), $c['contact_method']['options'], 'the ways to reach the family');
	pg_assert_same($d['statuses'], $c['status']['options'], 'the statuses');
	pg_assert_true(!empty($c['status']['cf']['office_use_only']), 'the status is the school\'s');
	pg_assert_same('{{folder:private}}', $c['documents']['cf']['upload_folder_id'] ?? '', 'the documents are private');
	pg_assert_same('date', $c['child_birth_date']['attrs']['type'], 'the birth date is a date');
	foreach (array('first_name' => 'first_name', 'last_name' => 'last_name', 'email' => 'email_address', 'phone' => 'mobile_phone') as $name => $contact) {
		pg_assert_same($contact, $c[$name]['cf']['contact_field'] ?? '', "$name fills the contact");
	}
	// Every ?programme= a link sends to the form is an answer.
	$seen = 0;
	foreach ($d['public']['pages'] as $p) {
		$nodes = array();
		_test_sha_nodes($p['tree'], $nodes);
		foreach ($nodes as $n) {
			$href = (string)($n['props']['href'] ?? '');
			if (strpos($href, '{{page:apply}}?') !== 0) continue;
			parse_str(substr($href, strlen('{{page:apply}}?')), $q);
			pg_assert_same(array('programme'), array_keys($q), "{$p['key']}: the application link sends the programme");
			pg_assert_true(in_array($q['programme'], $c['programme']['options'], true), "?programme={$q['programme']} is an answer");
			$seen++;
		}
	}
	pg_assert_same(5, $seen, 'every programme page opens the application with its programme');
}

// The visit request mails both sides; its answers are the parts' lists
// and its status is the school's.
function test_school_admissions_visit_form()
{
	$d = _test_sha_load();
	$form = $d['pages']['visit']['form'];
	pg_assert_same('{{tab:email_visit_new}}', $form['notify_page_id']);
	pg_assert_same('{{tab:email_visit_received}}', $form['confirm_page_id']);
	pg_assert_false(isset($form['auto_registration']), 'a visit opens no account');
	$c = _test_sha_controls($d['widgets']['visit_form']['tree']);
	pg_assert_same(array('preferred_day', 'visit_time', 'visitors', 'programme', 'first_name', 'last_name', 'email', 'phone',
		'contact_method', 'notes', 'status', 'consent'), array_keys($c), 'the fields');
	pg_assert_same($d['visit_times'], $c['visit_time']['options'], 'the times of day');
	pg_assert_same($d['visitor_counts'], $c['visitors']['options'], 'the visitors');
	pg_assert_same($d['programme_choices'], $c['programme']['options'], 'the programmes');
	pg_assert_same($d['visit_statuses'], $c['status']['options'], 'the statuses');
	pg_assert_true(!empty($c['status']['cf']['office_use_only']), 'the status is the school\'s');
	pg_assert_same('text', $c['preferred_day']['attrs']['type'], 'the day is words');
}

// The application's page carries its messages and mails each one; the
// page, the lists and the e-mails bind fields their form has.
function test_school_admissions_record_and_mail()
{
	$d = _test_sha_load();
	$comments = $d['pages']['application_view']['comments'];
	pg_assert_true($comments['allow_new'] && $comments['login'], 'messages from the signed-in parent');
	pg_assert_same('{{tab:email_reply}}', $comments['email_page']);
	$fields = array(
		'apply' => array_keys(_test_sha_controls($d['widgets']['application_form']['tree'])),
		'visit' => array_keys(_test_sha_controls($d['widgets']['visit_form']['tree'])),
	);
	$forms = array('application_detail' => 'apply', 'my_applications_list' => 'apply', 'staff_applications_list' => 'apply',
		'email_application_admin' => 'apply', 'email_application_parent' => 'apply',
		'staff_visits_list' => 'visit', 'email_visit_admin' => 'visit', 'email_visit_parent' => 'visit');
	foreach ($forms as $key => $form) {
		pg_assert_same('{{tab:' . $form . '}}', $d['widgets'][$key]['config']['custom_form_page_id'], "$key reads the $form form");
		$nodes = array();
		_test_sha_nodes($d['widgets'][$key]['tree'], $nodes);
		foreach ($nodes as $n) {
			foreach (array('text', 'href') as $prop) {
				$token = (string)($n['props']['_bindings'][$prop] ?? '');
				if ($token === '' || strpos($token, '__') === 0 || in_array($token, array('reference_code', 'not_found', 'number_of_comments', 'form_item_view'), true)) continue;
				pg_assert_true(in_array($token, $fields[$form], true), "$key binds $token, a field of the $form form");
			}
		}
		foreach ((array)($d['widgets'][$key]['config']['search_fields'] ?? array()) as $name) {
			pg_assert_true(in_array($name, $fields[$form], true), "$key: the staff search reads $name");
		}
	}
	$json = json_encode($d['widgets']['application_detail']['tree']);
	pg_assert_contains('"eo_visible_if":"record_found"', $json, 'the record');
	pg_assert_contains('"eo_visible_if":"record_not_found"', $json, 'the not-found note');
	pg_assert_contains('"regionType":"system"', json_encode($d['pages']['email_reply']['tree']), 'the reply e-mail keeps the fixed comment');
	foreach (array('email_application_admin', 'email_application_parent', 'email_visit_admin', 'email_visit_parent') as $key) {
		pg_assert_same('public', $d['widgets'][$key]['config']['access_control'], "$key is drawn for the submission just sent");
	}
	foreach (array('email_application_admin', 'email_application_parent') as $key) {
		pg_assert_contains('{{page:application_view}}?r=^^reference_code^^', json_encode($d['widgets'][$key]['tree'], JSON_UNESCAPED_SLASHES), "$key opens the application");
	}
}

function test_school_admissions_links_ids_and_classes()
{
	$d = _test_sha_load();
	$known = array_flip(_test_sh_page_keys());
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
	foreach ($d['shared'] as $t) _test_sha_nodes($t, $nodes);
	foreach ($d['pages'] as $p) _test_sha_nodes($p['tree'], $nodes);
	foreach ($d['widgets'] as $w) _test_sha_nodes($w['tree'], $nodes);
	$ids = array();
	foreach ($nodes as $n) {
		foreach (preg_split('/\s+/', (string)($n['props']['cssClass'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $c) {
			pg_assert_false((bool)preg_match('/^(ptv|pht|pre|pev|pgy|pch|plw|pwd|pph|pcn|psc)-/', $c), "class $c is not another template's");
			pg_assert_false((bool)preg_match('/^(text-)?opacity-/', $c), "no faded text class $c");
		}
		$id = (string)($n['props']['id'] ?? '');
		if ($id === '') continue;
		if (strpos($id, '-') !== false) pg_assert_true(strpos($id, 'sh-') === 0, "id $id is the template's");
		$ids[] = $id;
	}
	pg_assert_same(count($ids), count(array_unique($ids)), 'every id once');
}

// Every literal has its Turkish; an application reads "başvuru" and a
// school visit "ziyaret".
function test_school_admissions_turkish_words()
{
	$map = pg_test_language_map('tr');
	foreach (array('mail.php', 'admissions.php') as $file) {
		$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-school/' . $file);
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
			if (preg_match('/\b(admission )?applications?\b/i', $words)) pg_assert_true(mb_stripos($tr, 'başvuru', 0, 'UTF-8') !== false, "'$key' says başvuru");
			if (preg_match('/\bschool visit\b/i', $words)) pg_assert_true(mb_stripos($tr, 'ziyaret', 0, 'UTF-8') !== false, "'$key' says ziyaret");
		}
	}
}
