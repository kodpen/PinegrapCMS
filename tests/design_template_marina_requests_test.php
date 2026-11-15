<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Marina design template's berth requests and charter
 * enquiries (includes/design_templates/pinegrap-marina/requests.php and
 * mail.php): the pages and widgets, every widget placed on its page, the
 * berth request reading ?kind= and ?season= with every value the other
 * pages send among its answers, the charter enquiry reading ?boat= from
 * the boat page, office-only statuses, dates not before today, the
 * consent linking the privacy page, the record pages showing only the
 * skipper's own record with its messages, the lists and e-mails reading
 * fields of their form, every page link names a template page, ids and
 * classes are the template's own, and every literal has its Turkish.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The page keys of the finished template (_test_mn_page_keys()) and the
// charters part's helpers (_test_mnc_nodes(), _test_mnc_controls(),
// _test_mnc_load()).
require_once(__DIR__ . '/design_template_marina_test.php');
require_once(__DIR__ . '/design_template_marina_charters_test.php');

// The requests part as the template file reads it: parts.php, mail.php
// and charters.php first, then requests.php in the same scope. Read once
// per run.
function _test_mnr_load()
{
	static $out = null;
	if ($out !== null) return $out;
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-marina';
	include $dir . '/parts.php';
	include $dir . '/mail.php';
	include $dir . '/charters.php';
	$part = include $dir . '/requests.php';
	$pages = array();
	foreach ($part['pages'] as $p) $pages[$p['key']] = $p;
	$out = array('part' => $part, 'pages' => $pages, 'widgets' => $part['widgets'],
		'request_kind_choices' => $request_kind_choices, 'season_titles' => $season_titles,
		'charter_boat_choices' => $charter_boat_choices, 'experience_choices' => $experience_choices,
		'request_statuses' => $request_statuses, 'enquiry_statuses' => $enquiry_statuses,
		'shared' => array($header(), $footer(), $berth_band(), $rate_table(), $email_header_tree(), $email_footer_tree()));
	return $out;
}

// The query values of every link to a page key in a tree.
function _test_mnr_queries($tree, $key)
{
	$nodes = array();
	_test_mnc_nodes($tree, $nodes);
	$out = array();
	$prefix = '{{page:' . $key . '}}?';
	foreach ($nodes as $n) {
		$href = (string)($n['props']['href'] ?? '');
		if (strpos($href, $prefix) !== 0) continue;
		parse_str(substr($href, strlen($prefix)), $q);
		$out[] = $q;
	}
	return $out;
}

function test_marina_requests_pages_and_widgets()
{
	$d = _test_mnr_load();
	pg_assert_same(array('pages', 'widgets'), array_keys($d['part']), 'the part returns pages and widgets');
	pg_assert_same(array('berth_request', 'request_view', 'my_requests', 'staff_requests', 'privacy',
		'email_request_new', 'email_request_received', 'email_reply',
		'charter_enquiry', 'enquiry_view', 'my_enquiries', 'staff_enquiries',
		'email_enquiry_new', 'email_enquiry_received'), array_keys($d['pages']));
	$folders = array('berth_request' => 'public', 'privacy' => 'public', 'charter_enquiry' => 'public',
		'request_view' => 'registration', 'my_requests' => 'registration', 'enquiry_view' => 'registration', 'my_enquiries' => 'registration',
		'staff_requests' => 'private', 'staff_enquiries' => 'private');
	foreach ($folders as $key => $folder) pg_assert_same($folder, $d['pages'][$key]['folder'], "$key: folder");
	$known = array_flip(_test_mn_page_keys());
	$names = array();
	foreach ($d['pages'] as $key => $p) {
		pg_assert_true(isset($known[$key]), "$key is a page of the plan");
		pg_assert_false(isset($p['requires']), "$key stands without the shop");
		pg_assert_same('root', $p['tree']['type'], "$key: a page tree");
		pg_assert_true(mb_strlen((string)($p['meta_description'] ?? ''), 'UTF-8') <= 160, "$key: meta description fits a result line");
		pg_assert_true((bool)preg_match('/^[a-z0-9-]+$/', $p['name']), "$key: the address is a slug");
		pg_assert_false(isset($names[$p['name']]), "$key: its own address");
		$names[$p['name']] = true;
		if (strpos($key, 'email_') === 0) {
			pg_assert_same('private', $p['folder'], "$key: a private e-mail page");
			pg_assert_true(!empty($p['noindex']) && empty($p['search']) && empty($p['sitemap']), "$key: out of search and sitemap");
		}
	}
	// No address is one of the charters' pages.
	$c = _test_mnc_load();
	foreach ($c['pages'] as $p) pg_assert_false(isset($names[$p['name']]), "{$p['key']}: its address is not a request page's");
	$placed = array();
	foreach ($d['widgets'] as $key => $w) {
		pg_assert_true(isset($d['pages'][$w['page']]), "$key sits on a page of this part");
		pg_assert_contains('"templateWidget":"' . $key . '"', json_encode($d['pages'][$w['page']]['tree']), "$key is placed on its page");
		$placed[$w['page']] = true;
	}
	foreach (array_keys($d['pages']) as $key) {
		if ($key === 'privacy') continue;
		pg_assert_true(isset($placed[$key]), "$key carries a widget");
	}
}

// The berth request is a form of the page; it reads ?kind= and ?season=,
// each a pick list whose answers hold every value the template sends.
// Dates never before today, the status the harbour office's, the consent
// linking the privacy page.
function test_marina_requests_berth_form()
{
	$d = _test_mnr_load();
	$form = $d['pages']['berth_request']['form'];
	pg_assert_same('{{tab:request_view}}', $form['confirmation_page_id'], 'the request opens its own page');
	pg_assert_same(1, $form['auto_registration'], 'an account for a new skipper');
	pg_assert_same('{{tab:email_request_new}}', $form['notify_page_id']);
	pg_assert_same('{{tab:email_request_received}}', $form['confirm_page_id']);
	$c = _test_mnc_controls($d['widgets']['request_form']['tree']);
	foreach (array('arrival_date', 'departure_date', 'berth_kind', 'season', 'boat_name', 'vessel', 'boat_length', 'beam', 'draught',
		'crew', 'power', 'notes', 'first_name', 'last_name', 'email', 'phone', 'status', 'consent') as $name) {
		pg_assert_true(isset($c[$name]), "the request asks for $name");
	}
	pg_assert_contains('^^berth_kind^^', $form['notify_subject'], 'the subject names a field of the form');
	foreach (array('arrival_date', 'departure_date') as $name) {
		pg_assert_same('date', $c[$name]['type'], "$name is a date");
		pg_assert_same('today', $c[$name]['cf']['rules']['min_date'] ?? '', "$name not before today");
	}
	pg_assert_same('kind', $c['berth_kind']['cf']['url_param'] ?? '', 'the kind reads ?kind=');
	pg_assert_same('season', $c['season']['cf']['url_param'] ?? '', 'the season reads ?season=');
	pg_assert_same($d['request_kind_choices'], $c['berth_kind']['options'], 'the kinds');
	foreach ($d['season_titles'] as $s) pg_assert_true(in_array($s, $c['season']['options'], true), "$s is a season answer");
	pg_assert_same('number', $c['crew']['cf']['kind'] ?? '', 'the crew is a number');
	pg_assert_true(!empty($c['status']['cf']['office_use_only']), 'the status is the harbour office\'s');
	pg_assert_same($d['request_statuses'], $c['status']['options'], 'the statuses');
	pg_assert_contains('{{page:privacy}}', json_encode($d['widgets']['request_form']['tree']), 'the consent links the privacy page');
	// Every ?kind= and ?season= the public pages and the rate table send is
	// an answer.
	$c2 = _test_mnc_load();
	$trees = $d['shared'];
	foreach ($c2['public']['pages'] as $p) $trees[] = $p['tree'];
	$seen = array();
	foreach ($trees as $tree) {
		foreach (_test_mnr_queries($tree, 'berth_request') as $q) {
			foreach ($q as $param => $value) {
				pg_assert_true(in_array($param, array('kind', 'season'), true), "?$param is read by the request");
				$field = $param === 'kind' ? 'berth_kind' : 'season';
				pg_assert_true(in_array($value, $c[$field]['options'], true), "?$param=$value is an answer");
				$seen[$param] = true;
			}
		}
	}
	pg_assert_true(isset($seen['kind'], $seen['season']), 'the template sends both parameters');
}

// The charter enquiry reads ?boat= from the boat page; every sample boat
// is an answer.
function test_marina_requests_charter_form()
{
	$d = _test_mnr_load();
	$form = $d['pages']['charter_enquiry']['form'];
	pg_assert_same('{{tab:enquiry_view}}', $form['confirmation_page_id']);
	pg_assert_same('{{tab:email_enquiry_new}}', $form['notify_page_id']);
	pg_assert_same('{{tab:email_enquiry_received}}', $form['confirm_page_id']);
	pg_assert_contains('^^boat^^', $form['notify_subject']);
	$c = _test_mnc_controls($d['widgets']['enquiry_form']['tree']);
	foreach (array('boat', 'start_date', 'end_date', 'guests', 'experience', 'details', 'contact_method',
		'first_name', 'last_name', 'email', 'phone', 'status', 'consent') as $name) {
		pg_assert_true(isset($c[$name]), "the enquiry asks for $name");
	}
	foreach (array('start_date', 'end_date') as $name) pg_assert_same('today', $c[$name]['cf']['rules']['min_date'] ?? '', "$name not before today");
	pg_assert_same('boat', $c['boat']['cf']['url_param'] ?? '', 'the boat reads ?boat=');
	pg_assert_same($d['charter_boat_choices'], $c['boat']['options']);
	pg_assert_same($d['experience_choices'], $c['experience']['options']);
	pg_assert_same($d['enquiry_statuses'], $c['status']['options']);
	pg_assert_true(!empty($c['status']['cf']['office_use_only']), 'the status is the charter desk\'s');
	pg_assert_contains('{{page:privacy}}', json_encode($d['widgets']['enquiry_form']['tree']), 'the consent links the privacy page');
	// The boat page sends the boat's title as ?boat=.
	$ch = _test_mnc_load();
	$json = json_encode($ch['widgets']['boat_detail']['tree']);
	pg_assert_contains('{"name":"boat","token":"title"}', $json, 'the boat page sends its title');
	pg_assert_contains('{{tab:charter_enquiry}}', $json, 'the boat page opens the enquiry');
	foreach ($ch['boats'] as $b) pg_assert_true(in_array($b['name'], $c['boat']['options'], true), "{$b['name']} is an answer");
}

// The record pages show only the skipper's own record and the messages
// under it; the lists show the skipper's own or, for the staff, every one
// with a search; the e-mails read the record they are sent for. Every
// binding reads a field of the form or a system token.
function test_marina_requests_records_lists_and_mail()
{
	$d = _test_mnr_load();
	$sets = array(
		'berth_request' => array('request_detail', 'my_requests_list', 'staff_requests_list', 'email_request_admin', 'email_request_client', 'request_form', 'request_view', 'request_messages'),
		'charter_enquiry' => array('enquiry_detail', 'my_enquiries_list', 'staff_enquiries_list', 'email_enquiry_admin', 'email_enquiry_client', 'enquiry_form', 'enquiry_view', 'enquiry_messages'),
	);
	$system = array('form_item_view', 'reference_code', 'number_of_comments', 'submitted_date_and_time', 'not_found',
		'__new_account_email', '__new_account_set_password_url', '__edit_url');
	foreach ($sets as $form_key => $s) {
		list($detail, $mine, $staff, $admin, $client, $form, $view, $messages) = $s;
		$fields = array_keys(_test_mnc_controls($d['widgets'][$form]['tree']));
		$detail_widget = $d['widgets'][$detail];
		pg_assert_same('submitter_only', $detail_widget['config']['access_control'], "$detail: the skipper's own");
		pg_assert_same('{{tab:' . $form_key . '}}', $detail_widget['config']['custom_form_page_id']);
		pg_assert_same($view, $detail_widget['page']);
		pg_assert_same('{{tab:email_reply}}', $d['pages'][$view]['comments']['email_page'], "$view: messages are mailed");
		pg_assert_same($view, $d['widgets'][$messages]['page']);
		pg_assert_same('comments', $d['widgets'][$messages]['config']['regionType']);
		pg_assert_true(!empty($d['widgets'][$mine]['config']['viewer_filter_submitter']), "$mine: the skipper's own");
		pg_assert_true(!empty($d['widgets'][$staff]['config']['search_enabled']), "$staff: a search");
		foreach ($d['widgets'][$staff]['config']['search_fields'] as $f) pg_assert_true(in_array($f, $fields, true), "$staff searches the field $f");
		foreach (array($mine, $staff) as $list) {
			pg_assert_same('{{tab:' . $form_key . '}}', $d['widgets'][$list]['config']['custom_form_page_id']);
			pg_assert_same('{{tab:' . $view . '}}', $d['widgets'][$list]['config']['detail_page_id']);
		}
		foreach (array($admin, $client) as $mail) pg_assert_same('public', $d['widgets'][$mail]['config']['access_control'], "$mail reads the record sent");
		pg_assert_contains('{{page:' . $view . '}}?r=^^reference_code^^', json_encode($d['widgets'][$admin]['tree'], JSON_UNESCAPED_SLASHES), "$admin opens the record");
		foreach (array($detail, $mine, $staff, $admin, $client) as $key) {
			$nodes = array();
			_test_mnc_nodes($d['widgets'][$key]['tree'], $nodes);
			foreach ($nodes as $n) {
				foreach ((array)($n['props']['_bindings'] ?? array()) as $prop => $token) {
					if ($prop === 'eo_visible_if' || $prop === 'section' || $prop === 'action') continue;
					pg_assert_true(in_array($token, $fields, true) || in_array($token, $system, true), "$key reads the field $token");
				}
			}
		}
	}
	$json = json_encode($d['widgets']['request_detail']['tree']);
	foreach (array('record_found', 'record_not_found', 'can_edit', 'has_new_account', 'submitted_this_session') as $flag) {
		pg_assert_contains('"eo_visible_if":"' . $flag . '"', $json, "the request page: $flag");
	}
	pg_assert_contains('"regionType":"system"', json_encode($d['pages']['email_reply']['tree']), 'the reply e-mail keeps the fixed comment');
	pg_assert_contains('"templateWidget":"email_comment"', json_encode($d['pages']['email_reply']['tree']), 'the reply e-mail draws the comment');
}

function test_marina_requests_links_ids_and_classes()
{
	$d = _test_mnr_load();
	$known = array_flip(_test_mn_page_keys());
	$trees = array();
	foreach ($d['pages'] as $p) $trees[] = $p;
	foreach ($d['widgets'] as $w) $trees[] = array($w['config'], $w['tree']);
	$trees[] = $d['shared'];
	$json = json_encode($trees);
	preg_match_all('/\{\{(?:page|tab):([a-z0-9_]+)\}\}/', $json, $m);
	foreach (array_unique($m[1]) as $key) pg_assert_true(isset($known[$key]), "page link {{page:$key}} names a template page");
	foreach (array('product', 'cart', 'checkout', 'order') as $key) {
		pg_assert_false(strpos($json, '{{page:' . $key . '}}') !== false, "no tree links to $key");
	}
	pg_assert_false((bool)preg_match('/[?&]view=/', json_encode($trees, JSON_UNESCAPED_SLASHES)), 'no ?view= link');
	$nodes = array();
	foreach ($d['pages'] as $p) _test_mnc_nodes($p['tree'], $nodes);
	foreach ($d['widgets'] as $w) _test_mnc_nodes($w['tree'], $nodes);
	foreach ($nodes as $n) {
		foreach (preg_split('/\s+/', (string)($n['props']['cssClass'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $c) {
			pg_assert_false((bool)preg_match('/^(pht|pre|pev|pf|pdp|pm|pma|plx|pdl|plw|psl|pco|pcl|pgy|pch|ptv|pph|pwd|pcn|pmu|psc|psh|pmz|pai|pcp|prt)-/', $c), "class $c is not another template's");
			pg_assert_false((bool)preg_match('/^(text-)?opacity-/', $c), "no faded text class $c");
		}
		$id = (string)($n['props']['id'] ?? '');
		if ($id !== '' && strpos($id, '-') !== false) pg_assert_true(strpos($id, 'mn-') === 0, "id $id is the template's");
		pg_assert_false((bool)preg_match('/\b(19|20)[0-9]{2}\b/', (string)($n['props']['text'] ?? '')), 'no fixed year in a text');
	}
}

// Every literal of both files has its Turkish; a berth request reads
// "bağlama talebi" and a charter enquiry "kiralama talebi".
function test_marina_requests_turkish_words()
{
	$map = pg_test_language_map('tr');
	foreach (array('requests.php', 'mail.php') as $file) {
		$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-marina/' . $file);
		preg_match_all("/lang\\('((?:[^'\\\\]|\\\\.)*)'\\)/", $src, $m);
		foreach (array_unique($m[1]) as $raw) {
			$key = stripslashes($raw);
			pg_assert_true(isset($map[$key]) && $map[$key] !== '', "$file: Turkish for '$key'");
			if (!isset($map[$key])) continue;
			$tr = $map[$key];
			if (preg_match('/^[a-z-]+$/', $key)) continue;
			if (preg_match('/\bberth requests?\b/i', $key)) pg_assert_true(mb_stripos($tr, 'bağlama tale', 0, 'UTF-8') !== false, "'$key' says bağlama talebi");
			if (preg_match('/\bcharter enquir(y|ies)\b/i', $key)) pg_assert_true(mb_stripos($tr, 'kiralama tale', 0, 'UTF-8') !== false, "'$key' says kiralama talebi");
		}
	}
}
