<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Construction Firm design template's e-mail builders and
 * quote requests (includes/design_templates/pinegrap-construction/mail.php
 * and quotes.php): the pages and widgets, the quote form opening the
 * request's page and mailing both sides, its service starting with what
 * the service pages and the project pages send (?service=), the site visit
 * asked in the same form, the status the firm's, the files private, the
 * request's page and lists binding fields the form has, every page link
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

// The page keys of the finished template (_test_cn_page_keys()).
require_once(__DIR__ . '/design_template_construction_test.php');

// The quotes' part as the template file reads it: parts.php, mail.php,
// then quotes.php in the same scope. Read once per run.
function _test_cnq_load()
{
	static $out = null;
	if ($out !== null) return $out;
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-construction';
	include $dir . '/parts.php';
	include $dir . '/mail.php';
	$part = include $dir . '/quotes.php';
	$public = include $dir . '/pages_public.php';
	$projects_part = include $dir . '/projects.php';
	$pages = array();
	foreach ($part['pages'] as $p) $pages[$p['key']] = $p;
	$out = array('part' => $part, 'pages' => $pages, 'widgets' => $part['widgets'],
		'service_choices' => $quote_service_choices, 'project_kinds' => $project_kinds, 'budget_choices' => $budget_choices,
		'start_choices' => $start_choices, 'statuses' => $quote_statuses, 'visit_choices' => $visit_choices,
		'contact_choices' => $contact_choices, 'public' => $public, 'projects_part' => $projects_part,
		'shared' => array($email_header_tree(), $email_footer_tree()));
	return $out;
}

function _test_cnq_nodes($node, &$out)
{
	if (!is_array($node)) return;
	if (isset($node['props'])) $out[] = $node;
	foreach ((array)($node['children'] ?? array()) as $c) _test_cnq_nodes($c, $out);
}

// The name, values and settings of every form control of a tree; radios
// of one name gather their values.
function _test_cnq_controls($node)
{
	$nodes = array();
	_test_cnq_nodes($node, $nodes);
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

function test_construction_quotes_pages_and_widgets()
{
	$d = _test_cnq_load();
	pg_assert_same(array('pages', 'widgets'), array_keys($d['part']), 'the part returns pages and widgets');
	pg_assert_same(array('quote', 'quote_view', 'my_quotes', 'staff_quotes', 'privacy', 'email_quote_new', 'email_quote_received', 'email_reply'), array_keys($d['pages']));
	pg_assert_same(array('quote_form', 'quote_detail', 'quote_messages', 'my_quotes_list', 'staff_quotes_list', 'email_quote_admin', 'email_quote_client', 'email_comment'), array_keys($d['widgets']));
	pg_assert_same('public', $d['pages']['quote']['folder']);
	pg_assert_same('public', $d['pages']['privacy']['folder']);
	pg_assert_same('registration', $d['pages']['quote_view']['folder'], 'a request is read signed in');
	pg_assert_same('registration', $d['pages']['my_quotes']['folder']);
	pg_assert_same('private', $d['pages']['staff_quotes']['folder'], 'only the staff list every request');
	foreach (array('email_quote_new', 'email_quote_received', 'email_reply') as $key) {
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
	pg_assert_same('submitter_only', $d['widgets']['quote_detail']['config']['access_control'], 'a request is its sender\'s');
	pg_assert_true(!empty($d['widgets']['my_quotes_list']['config']['viewer_filter_submitter']), 'My Quote Requests lists the own requests');
	pg_assert_true(!empty($d['widgets']['staff_quotes_list']['config']['search_enabled']), 'the staff search');
}

// The form opens the request's page and mails both sides; its service
// starts with what the links send, the site visit is asked in it, the
// files are private and the status is the firm's.
function test_construction_quotes_form()
{
	$d = _test_cnq_load();
	$form = $d['pages']['quote']['form'];
	pg_assert_same('{{tab:quote_view}}', $form['confirmation_page_id']);
	pg_assert_same(1, $form['auto_registration']);
	pg_assert_same('{{tab:email_quote_new}}', $form['notify_page_id']);
	pg_assert_same('{{tab:email_quote_received}}', $form['confirm_page_id']);
	$c = _test_cnq_controls($d['widgets']['quote_form']['tree']);
	pg_assert_same(array('service', 'project_type', 'location', 'budget', 'start', 'details', 'plans', 'site_visit', 'visit_time',
		'first_name', 'last_name', 'email', 'phone', 'company', 'contact_method', 'status', 'consent'), array_keys($c), 'the fields');
	pg_assert_same($d['service_choices'], $c['service']['options'], 'the services');
	pg_assert_same('service', $c['service']['cf']['url_param'] ?? '', 'the service reads ?service=');
	pg_assert_same($d['project_kinds'], $c['project_type']['options'], 'the kinds of work');
	pg_assert_same($d['budget_choices'], $c['budget']['options'], 'the budgets');
	pg_assert_same($d['start_choices'], $c['start']['options'], 'the starts');
	pg_assert_same(array_column($d['visit_choices'], 0), $c['site_visit']['options'], 'the site visit question');
	pg_assert_same(array_column($d['contact_choices'], 0), $c['contact_method']['options'], 'the ways to reach the client');
	pg_assert_same($d['statuses'], $c['status']['options'], 'the statuses');
	pg_assert_true(!empty($c['status']['cf']['office_use_only']), 'the status is the firm\'s');
	pg_assert_same('{{folder:private}}', $c['plans']['cf']['upload_folder_id'] ?? '', 'plans and photos are private');
	foreach (array('first_name' => 'first_name', 'last_name' => 'last_name', 'email' => 'email_address', 'phone' => 'mobile_phone') as $name => $contact) {
		pg_assert_same($contact, $c[$name]['cf']['contact_field'] ?? '', "$name fills the contact");
	}
	// Every ?service= a link sends is an answer: the service pages and the
	// project page's quote link, which sends the project's own service.
	$seen = 0;
	foreach ($d['public']['pages'] as $p) {
		$nodes = array();
		_test_cnq_nodes($p['tree'], $nodes);
		foreach ($nodes as $n) {
			$href = (string)($n['props']['href'] ?? '');
			if (strpos($href, '{{page:quote}}?') !== 0) continue;
			parse_str(substr($href, strlen('{{page:quote}}?')), $q);
			pg_assert_same(array('service'), array_keys($q), "{$p['key']}: the quote link sends the service");
			pg_assert_true(in_array($q['service'], $c['service']['options'], true), "?service={$q['service']} is an answer");
			$seen++;
		}
	}
	pg_assert_true($seen >= 6, 'every service page asks for a quote with its service');
	pg_assert_contains('"page_id":"{{tab:quote}}","params":[{"name":"service","token":"service"}]',
		json_encode($d['projects_part']['widgets']['project_detail']['tree'], JSON_UNESCAPED_SLASHES), 'the project page sends its service');
}

// The request's page carries its messages and mails each one; the page,
// the lists and the e-mails bind fields the form has.
function test_construction_quotes_record_and_mail()
{
	$d = _test_cnq_load();
	$comments = $d['pages']['quote_view']['comments'];
	pg_assert_true($comments['allow_new'] && $comments['login'], 'messages from the signed-in sender');
	pg_assert_same('{{tab:email_reply}}', $comments['email_page']);
	$fields = array_keys(_test_cnq_controls($d['widgets']['quote_form']['tree']));
	foreach (array('quote_detail', 'my_quotes_list', 'staff_quotes_list', 'email_quote_admin', 'email_quote_client') as $key) {
		$nodes = array();
		_test_cnq_nodes($d['widgets'][$key]['tree'], $nodes);
		foreach ($nodes as $n) {
			foreach (array('text', 'href') as $prop) {
				$token = (string)($n['props']['_bindings'][$prop] ?? '');
				if ($token === '' || strpos($token, '__') === 0 || in_array($token, array('reference_code', 'not_found', 'number_of_comments', 'form_item_view'), true)) continue;
				pg_assert_true(in_array($token, $fields, true), "$key binds $token, a field of the form");
			}
		}
	}
	foreach ($d['widgets']['staff_quotes_list']['config']['search_fields'] as $name) {
		pg_assert_true(in_array($name, $fields, true), "the staff search reads $name");
	}
	$json = json_encode($d['widgets']['quote_detail']['tree']);
	pg_assert_contains('"eo_visible_if":"record_found"', $json, 'the record');
	pg_assert_contains('"eo_visible_if":"record_not_found"', $json, 'the not-found note');
	pg_assert_contains('"regionType":"system"', json_encode($d['pages']['email_reply']['tree']), 'the reply e-mail keeps the fixed comment');
	foreach (array('email_quote_admin', 'email_quote_client') as $key) {
		pg_assert_same('public', $d['widgets'][$key]['config']['access_control'], "$key is drawn for the submission just sent");
		pg_assert_contains('{{page:quote_view}}?r=^^reference_code^^', json_encode($d['widgets'][$key]['tree'], JSON_UNESCAPED_SLASHES), "$key opens the request");
	}
}

function test_construction_quotes_links_ids_and_classes()
{
	$d = _test_cnq_load();
	$known = array_flip(_test_cn_page_keys());
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
	foreach ($d['shared'] as $t) _test_cnq_nodes($t, $nodes);
	foreach ($d['pages'] as $p) _test_cnq_nodes($p['tree'], $nodes);
	foreach ($d['widgets'] as $w) _test_cnq_nodes($w['tree'], $nodes);
	foreach ($nodes as $n) {
		foreach (preg_split('/\s+/', (string)($n['props']['cssClass'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $c) {
			pg_assert_false((bool)preg_match('/^(ptv|pht|pre|pev|pgy|pch|plw|pwd|pph)-/', $c), "class $c is not another template's");
			pg_assert_false((bool)preg_match('/^(text-)?opacity-/', $c), "no faded text class $c");
		}
		$id = (string)($n['props']['id'] ?? '');
		if ($id !== '' && strpos($id, '-') !== false) pg_assert_true(strpos($id, 'cn-') === 0, "id $id is the template's");
	}
}

// Every literal has its Turkish; a quote reads "teklif" and a site visit
// "keşif".
function test_construction_quotes_turkish_words()
{
	$map = pg_test_language_map('tr');
	foreach (array('mail.php', 'quotes.php') as $file) {
		$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-construction/' . $file);
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
			// 'Quote' names a blockquote node of the shared e-mail card.
			if ($key === 'Quote') continue;
			if (preg_match('/\bquotes?\b/i', $words)) pg_assert_true(mb_stripos($tr, 'teklif', 0, 'UTF-8') !== false, "'$key' says teklif");
			if (preg_match('/\bsite visit\b/i', $words)) pg_assert_true(mb_stripos($tr, 'keşif', 0, 'UTF-8') !== false, "'$key' says keşif");
		}
	}
}
