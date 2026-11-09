<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Campsite design template's stay requests and group
 * enquiries (includes/design_templates/pinegrap-campsite/stays.php and
 * mail.php): the pages and widgets, every widget placed on its page, the
 * stay request reading ?kind=, ?unit= and ?season= with every value the
 * other pages send among its answers, the group enquiry reading ?kind=
 * from the Group Stays cards, office-only statuses, dates not before
 * today, the consent linking the privacy page, the record pages showing
 * only the guest's own record with its messages, the lists and e-mails
 * reading fields of their form, every page link names a template page,
 * ids and classes are the template's own, and every literal has its
 * Turkish.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The page keys of the finished template (_test_cp_page_keys()) and the
// units part's helpers (_test_cpu_nodes(), _test_cpu_controls(),
// _test_cpu_load()).
require_once(__DIR__ . '/design_template_campsite_test.php');
require_once(__DIR__ . '/design_template_campsite_units_test.php');

// The stays part as the template file reads it: parts.php and mail.php
// first, then stays.php in the same scope. Read once per run.
function _test_cps_load()
{
	static $out = null;
	if ($out !== null) return $out;
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-campsite';
	include $dir . '/parts.php';
	include $dir . '/mail.php';
	$part = include $dir . '/stays.php';
	$pages = array();
	foreach ($part['pages'] as $p) $pages[$p['key']] = $p;
	$out = array('part' => $part, 'pages' => $pages, 'widgets' => $part['widgets'],
		'request_kind_choices' => $request_kind_choices, 'request_unit_choices' => $request_unit_choices,
		'season_titles' => $season_titles, 'group_kinds' => $group_kinds, 'group_sizes' => $group_sizes,
		'request_statuses' => $request_statuses, 'enquiry_statuses' => $enquiry_statuses,
		'shared' => array($header(), $footer(), $stay_band(), $email_header_tree(), $email_footer_tree()));
	return $out;
}

// The query values of every link to a page key in a tree.
function _test_cps_queries($tree, $key)
{
	$nodes = array();
	_test_cpu_nodes($tree, $nodes);
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

function test_campsite_stays_pages_and_widgets()
{
	$d = _test_cps_load();
	pg_assert_same(array('pages', 'widgets'), array_keys($d['part']), 'the part returns pages and widgets');
	pg_assert_same(array('book', 'request_view', 'my_requests', 'staff_requests', 'privacy',
		'email_request_new', 'email_request_received', 'email_reply',
		'groups', 'group_enquiry', 'enquiry_view', 'my_enquiries', 'staff_enquiries',
		'email_enquiry_new', 'email_enquiry_received'), array_keys($d['pages']));
	$folders = array('book' => 'public', 'privacy' => 'public', 'groups' => 'public', 'group_enquiry' => 'public',
		'request_view' => 'registration', 'my_requests' => 'registration', 'enquiry_view' => 'registration', 'my_enquiries' => 'registration',
		'staff_requests' => 'private', 'staff_enquiries' => 'private');
	foreach ($folders as $key => $folder) pg_assert_same($folder, $d['pages'][$key]['folder'], "$key: folder");
	$names = array();
	foreach ($d['pages'] as $key => $p) {
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
	$placed = array();
	foreach ($d['widgets'] as $key => $w) {
		pg_assert_true(isset($d['pages'][$w['page']]), "$key sits on a page of this part");
		pg_assert_contains('"templateWidget":"' . $key . '"', json_encode($d['pages'][$w['page']]['tree']), "$key is placed on its page");
		$placed[$w['page']] = true;
	}
	foreach (array_keys($d['pages']) as $key) {
		if (in_array($key, array('privacy', 'groups'), true)) continue;
		pg_assert_true(isset($placed[$key]), "$key carries a widget");
	}
}

// The stay request is a form of the page; it reads ?kind=, ?unit= and
// ?season=, each a pick list whose answers hold every value the template
// sends. Dates never before today, the status the reception's, the
// consent linking the privacy page.
function test_campsite_stays_request_form()
{
	$d = _test_cps_load();
	$form = $d['pages']['book']['form'];
	pg_assert_same('{{tab:request_view}}', $form['confirmation_page_id'], 'the request opens its own page');
	pg_assert_same(1, $form['auto_registration'], 'an account for a new guest');
	pg_assert_same('{{tab:email_request_new}}', $form['notify_page_id']);
	pg_assert_same('{{tab:email_request_received}}', $form['confirm_page_id']);
	$c = _test_cpu_controls($d['widgets']['request_form']['tree']);
	foreach (array('arrival_date', 'departure_date', 'adults', 'children', 'stay_kind', 'season', 'unit', 'outfit', 'outfit_length',
		'pets', 'notes', 'first_name', 'last_name', 'email', 'phone', 'status', 'consent') as $name) {
		pg_assert_true(isset($c[$name]), "the request asks for $name");
	}
	pg_assert_contains('stay_kind', $form['notify_subject'], 'the subject names a field of the form');
	foreach (array('arrival_date', 'departure_date') as $name) {
		pg_assert_same('date', $c[$name]['type'], "$name is a date");
		pg_assert_same('today', $c[$name]['cf']['rules']['min_date'] ?? '', "$name not before today");
	}
	pg_assert_same(array('stay_kind' => 'kind', 'unit' => 'unit', 'season' => 'season'), array_filter(array(
		'stay_kind' => $c['stay_kind']['cf']['url_param'] ?? '',
		'unit'      => $c['unit']['cf']['url_param'] ?? '',
		'season'    => $c['season']['cf']['url_param'] ?? '',
	)), 'the fields read ?kind=, ?unit= and ?season=');
	pg_assert_same($d['request_kind_choices'], $c['stay_kind']['options'], 'the kinds');
	pg_assert_same($d['request_unit_choices'], $c['unit']['options'], 'the places');
	foreach ($d['season_titles'] as $s) pg_assert_true(in_array($s, $c['season']['options'], true), "$s is a season answer");
	pg_assert_true(!empty($c['status']['cf']['office_use_only']), 'the status is the reception\'s');
	pg_assert_same($d['request_statuses'], $c['status']['options'], 'the statuses');
	pg_assert_contains('{{page:privacy}}', json_encode($d['widgets']['request_form']['tree']), 'the consent links the privacy page');
	// Every ?kind=, ?unit= and ?season= the public pages send is an answer.
	$u = _test_cpu_load();
	$seen = 0;
	foreach ($u['public']['pages'] as $p) {
		foreach (_test_cps_queries($p['tree'], 'book') as $q) {
			foreach ($q as $param => $value) {
				pg_assert_true(in_array($param, array('kind', 'unit', 'season'), true), "?$param is read by the request");
				$field = array('kind' => 'stay_kind', 'unit' => 'unit', 'season' => 'season')[$param] ?? '';
				if ($field === '') continue;
				pg_assert_true(in_array($value, $c[$field]['options'], true), "?$param=$value is an answer");
				$seen++;
			}
		}
	}
	pg_assert_true($seen >= count($d['season_titles']), 'the public pages send values the request reads');
}

// The group enquiry reads ?kind= from the Group Stays cards; its file goes
// to the private folder.
function test_campsite_stays_group_form()
{
	$d = _test_cps_load();
	$form = $d['pages']['group_enquiry']['form'];
	pg_assert_same('{{tab:enquiry_view}}', $form['confirmation_page_id']);
	pg_assert_same('{{tab:email_enquiry_new}}', $form['notify_page_id']);
	pg_assert_same('{{tab:email_enquiry_received}}', $form['confirm_page_id']);
	$c = _test_cpu_controls($d['widgets']['enquiry_form']['tree']);
	foreach (array('group_kind', 'group_size', 'arrival_date', 'departure_date', 'stay_kind', 'details', 'documents',
		'first_name', 'last_name', 'email', 'phone', 'organisation', 'status', 'consent') as $name) {
		pg_assert_true(isset($c[$name]), "the enquiry asks for $name");
	}
	pg_assert_same('kind', $c['group_kind']['cf']['url_param'] ?? '', 'the kind of group reads ?kind=');
	pg_assert_same($d['group_kinds'], $c['group_kind']['options']);
	pg_assert_same($d['group_sizes'], $c['group_size']['options']);
	pg_assert_same($d['enquiry_statuses'], $c['status']['options']);
	pg_assert_true(!empty($c['status']['cf']['office_use_only']), 'the status is the park\'s');
	pg_assert_same('{{folder:private}}', $c['documents']['cf']['upload_folder_id'] ?? '', 'a group\'s file stays private');
	pg_assert_contains('{{page:privacy}}', json_encode($d['widgets']['enquiry_form']['tree']), 'the consent links the privacy page');
	$queries = _test_cps_queries($d['pages']['groups']['tree'], 'group_enquiry');
	pg_assert_same(4, count($queries), 'a card per kind of group');
	foreach ($queries as $q) {
		pg_assert_same(array('kind'), array_keys($q));
		pg_assert_true(in_array($q['kind'], $c['group_kind']['options'], true), "?kind={$q['kind']} is an answer");
	}
}

// The record pages show only the guest's own record and the messages
// under it; the lists show the guest's own or, for the staff, every one
// with a search; the e-mails read the record they are sent for. Every
// binding reads a field of the form or a system token.
function test_campsite_stays_records_lists_and_mail()
{
	$d = _test_cps_load();
	$sets = array(
		'book' => array('request_detail', 'my_requests_list', 'staff_requests_list', 'email_request_admin', 'email_request_client', 'request_form', 'request_view', 'request_messages'),
		'group_enquiry' => array('enquiry_detail', 'my_enquiries_list', 'staff_enquiries_list', 'email_enquiry_admin', 'email_enquiry_client', 'enquiry_form', 'enquiry_view', 'enquiry_messages'),
	);
	$system = array('form_item_view', 'reference_code', 'number_of_comments', 'submitted_date_and_time', 'not_found', 'documents',
		'__new_account_email', '__new_account_set_password_url', '__edit_url');
	foreach ($sets as $form_key => $s) {
		list($detail, $mine, $staff, $admin, $client, $form, $view, $messages) = $s;
		$fields = array_keys(_test_cpu_controls($d['widgets'][$form]['tree']));
		$detail_widget = $d['widgets'][$detail];
		pg_assert_same('submitter_only', $detail_widget['config']['access_control'], "$detail: the guest's own");
		pg_assert_same('{{tab:' . $form_key . '}}', $detail_widget['config']['custom_form_page_id']);
		pg_assert_same($view, $detail_widget['page']);
		pg_assert_same('{{tab:email_reply}}', $d['pages'][$view]['comments']['email_page'], "$view: messages are mailed");
		pg_assert_same($view, $d['widgets'][$messages]['page']);
		pg_assert_same('comments', $d['widgets'][$messages]['config']['regionType']);
		pg_assert_true(!empty($d['widgets'][$mine]['config']['viewer_filter_submitter']), "$mine: the guest's own");
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
			_test_cpu_nodes($d['widgets'][$key]['tree'], $nodes);
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
}

function test_campsite_stays_links_ids_and_classes()
{
	$d = _test_cps_load();
	$known = array_flip(_test_cp_page_keys());
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
	foreach ($d['pages'] as $p) _test_cpu_nodes($p['tree'], $nodes);
	foreach ($d['widgets'] as $w) _test_cpu_nodes($w['tree'], $nodes);
	foreach ($nodes as $n) {
		foreach (preg_split('/\s+/', (string)($n['props']['cssClass'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $c) {
			pg_assert_false((bool)preg_match('/^(ptv|pht|pre|pev|pgy|pch|plw|pwd|pph|pcn|psh|pmu|psc)-/', $c), "class $c is not another template's");
			pg_assert_false((bool)preg_match('/^(text-)?opacity-/', $c), "no faded text class $c");
		}
		$id = (string)($n['props']['id'] ?? '');
		if ($id !== '' && strpos($id, '-') !== false) pg_assert_true(strpos($id, 'cp-') === 0, "id $id is the template's");
		pg_assert_false((bool)preg_match('/\b(19|20)[0-9]{2}\b/', (string)($n['props']['text'] ?? '')), 'no fixed year in a text');
	}
}

// Every literal of both files has its Turkish; a stay request reads
// "konaklama talebi" and a group enquiry "grup talebi".
function test_campsite_stays_turkish_words()
{
	$map = pg_test_language_map('tr');
	foreach (array('stays.php', 'mail.php') as $file) {
		$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-campsite/' . $file);
		preg_match_all("/lang\\('((?:[^'\\\\]|\\\\.)*)'\\)/", $src, $m);
		foreach (array_unique($m[1]) as $raw) {
			$key = stripslashes($raw);
			pg_assert_true(isset($map[$key]) && $map[$key] !== '', "$file: Turkish for '$key'");
			if (!isset($map[$key])) continue;
			$tr = $map[$key];
			if (preg_match('/^[a-z-]+$/', $key)) continue;
			if (preg_match('/\bstay request\b/i', $key)) pg_assert_true(mb_stripos($tr, 'konaklama tale', 0, 'UTF-8') !== false, "'$key' says konaklama talebi");
			if (preg_match('/\bgroup enquir(y|ies)\b/i', $key)) pg_assert_true(mb_stripos($tr, 'grup tale', 0, 'UTF-8') !== false, "'$key' says grup talebi");
		}
	}
}
