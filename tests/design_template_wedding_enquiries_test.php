<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Wedding Planner and Venue design template's e-mail builders
 * and enquiries (includes/design_templates/pinegrap-wedding/mail.php and
 * enquiries.php): the pages and widgets, the enquiry form reading what the
 * service, package and space cards and the Real Weddings page send
 * (?service=, ?package=, ?space=), the office-only status, the enquiry's
 * page and lists, the venue visit on the booking widget's starter linked
 * both ways with the enquiry, the e-mail pages kept out of the search,
 * every page link naming a template page and none a shop page, ids and
 * classes the template's own, and every literal with its Turkish.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The page keys of the finished template and the shop's pages.
require_once(__DIR__ . '/design_template_wedding_test.php');

// The enquiries' part as the template file reads it: parts.php, mail.php,
// then enquiries.php in the same scope. Read once per run.
function _test_wde_load()
{
	static $out = null;
	if ($out !== null) return $out;
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-wedding';
	include $dir . '/parts.php';
	include $dir . '/mail.php';
	$part = include $dir . '/enquiries.php';
	$pages = array();
	foreach ($part['pages'] as $p) $pages[$p['key']] = $p;
	$cards = array();
	foreach (array_keys($services) as $key) $cards[] = $service_card($key);
	foreach (array_keys($packages) as $key) $cards[] = $package_card($key);
	foreach (array_keys($spaces) as $key) $cards[] = $space_card($key);
	$out = array('pages' => $pages, 'widgets' => $part['widgets'], 'cards' => $cards,
		'package_choices' => $package_choices, 'service_choices' => $service_choices, 'space_choices' => $space_choices,
		'season_choices' => $season_choices, 'budget_choices' => $budget_choices, 'statuses' => $enquiry_statuses,
		'shared' => array($email_header_tree(), $email_footer_tree()));
	return $out;
}

function _test_wde_nodes($node, &$out)
{
	if (!is_array($node)) return;
	if (isset($node['props'])) $out[] = $node;
	foreach ((array)($node['children'] ?? array()) as $c) _test_wde_nodes($c, $out);
}

// The name, values and settings of every form control of a tree; radios
// of one name gather their values.
function _test_wde_controls($node)
{
	$nodes = array();
	_test_wde_nodes($node, $nodes);
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

function test_wedding_enquiries_pages_and_widgets()
{
	$d = _test_wde_load();
	pg_assert_same(array('enquiry', 'enquiry_view', 'my_enquiries', 'staff_enquiries', 'book_visit', 'privacy', 'email_enquiry_new', 'email_enquiry_received', 'email_reply'), array_keys($d['pages']));
	pg_assert_same(array('enquiry_form', 'enquiry_detail', 'enquiry_messages', 'my_enquiries_list', 'staff_enquiries_list', 'visit_slots', 'email_enquiry_admin', 'email_enquiry_client', 'email_comment'), array_keys($d['widgets']));
	foreach (array('enquiry', 'book_visit', 'privacy') as $key) pg_assert_same('public', $d['pages'][$key]['folder'], "$key is public");
	pg_assert_same('registration', $d['pages']['enquiry_view']['folder'], 'an enquiry is read signed in');
	pg_assert_same('registration', $d['pages']['my_enquiries']['folder']);
	pg_assert_same('private', $d['pages']['staff_enquiries']['folder'], 'only the staff list every enquiry');
	foreach (array('email_enquiry_new', 'email_enquiry_received', 'email_reply') as $key) {
		pg_assert_same('private', $d['pages'][$key]['folder'], "$key is private");
		pg_assert_true(!empty($d['pages'][$key]['noindex']) && $d['pages'][$key]['search'] === false, "$key is out of the search");
	}
	pg_assert_same(lang('privacy-policy'), $d['pages']['privacy']['name'], 'the booking widget\'s consent box finds the privacy page');
	$known = array_flip(_test_wd_page_keys());
	foreach ($d['pages'] as $key => $p) {
		pg_assert_true(isset($known[$key]), "$key is a page of the plan");
		pg_assert_false(isset($p['requires']), "$key stands without the shop");
		pg_assert_same('root', $p['tree']['type'], "$key: a page tree");
		pg_assert_true(mb_strlen((string)($p['meta_description'] ?? ''), 'UTF-8') <= 160, "$key: meta description fits a result line");
	}
	foreach ($d['widgets'] as $key => $w) {
		pg_assert_true(isset($d['pages'][$w['page']]), "$key sits on a page of this part");
		pg_assert_contains('"templateWidget":"' . $key . '"', json_encode($d['pages'][$w['page']]['tree']), "$key is placed on its page");
	}
	pg_assert_same('submitter_only', $d['widgets']['enquiry_detail']['config']['access_control'], 'an enquiry is its sender\'s');
	pg_assert_true(!empty($d['widgets']['my_enquiries_list']['config']['viewer_filter_submitter']), 'My Enquiries lists the own enquiries');
	pg_assert_true(!empty($d['widgets']['staff_enquiries_list']['config']['search_enabled']), 'the staff search');
}

// The form opens the enquiry's page and mails both sides; its package,
// service and space start with what the cards send, the date is never in
// the past and the status is the planner's.
function test_wedding_enquiries_form()
{
	$d = _test_wde_load();
	$form = $d['pages']['enquiry']['form'];
	pg_assert_same('{{tab:enquiry_view}}', $form['confirmation_page_id']);
	pg_assert_same(1, $form['auto_registration']);
	pg_assert_same('{{tab:email_enquiry_new}}', $form['notify_page_id']);
	pg_assert_same('{{tab:email_enquiry_received}}', $form['confirm_page_id']);
	$c = _test_wde_controls($d['widgets']['enquiry_form']['tree']);
	foreach (array('package', 'service', 'space', 'wedding_date', 'season', 'guests', 'budget', 'ceremony', 'wishes', 'documents',
		'first_name', 'last_name', 'partner_name', 'email', 'phone', 'contact_method', 'status', 'consent') as $name) {
		pg_assert_true(isset($c[$name]), "the form has $name");
	}
	preg_match_all('/\^\^([a-z_]+)\^\^/', $form['notify_subject'], $m);
	foreach ($m[1] as $name) pg_assert_true(isset($c[$name]), "the subject reads $name, a field of the form");
	foreach (array('package' => 'package_choices', 'service' => 'service_choices', 'space' => 'space_choices') as $name => $list) {
		pg_assert_same($d[$list], $c[$name]['options'], "the {$name}s");
		pg_assert_same($name, $c[$name]['cf']['url_param'] ?? '', "$name reads ?$name=");
	}
	pg_assert_same($d['season_choices'], $c['season']['options'], 'the seasons');
	pg_assert_same($d['budget_choices'], $c['budget']['options'], 'the budget bands');
	pg_assert_same('today', $c['wedding_date']['cf']['rules']['min_date'] ?? '', 'no wedding date in the past');
	pg_assert_false(isset($c['wedding_date']['attrs']['required']), 'an open date is allowed');
	pg_assert_same($d['statuses'], $c['status']['options'], 'the statuses');
	pg_assert_true(!empty($c['status']['cf']['office_use_only']), 'the status is the planner\'s');
	// Every choice a card sends is an answer of its pick list.
	$seen = 0;
	foreach ($d['cards'] as $card) {
		$nodes = array();
		_test_wde_nodes($card, $nodes);
		foreach ($nodes as $n) {
			$href = (string)($n['props']['href'] ?? '');
			if (strpos($href, '{{page:enquiry}}?') !== 0) continue;
			parse_str(substr($href, strlen('{{page:enquiry}}?')), $q);
			foreach ($q as $name => $value) {
				pg_assert_true(isset($c[$name]['cf']['url_param']), "?$name= is read by the form");
				pg_assert_true(in_array($value, $c[$name]['options'], true), "?$name=$value is an answer");
			}
			$seen++;
		}
	}
	pg_assert_same(count($d['cards']), $seen, 'every card opens the enquiry with its choice');
}

// The enquiry's page carries its messages and mails each one; the e-mails
// and lists bind fields the form has.
function test_wedding_enquiries_record_and_mail()
{
	$d = _test_wde_load();
	$comments = $d['pages']['enquiry_view']['comments'];
	pg_assert_true($comments['allow_new'] && $comments['login'], 'messages from the signed-in sender');
	pg_assert_same('{{tab:email_reply}}', $comments['email_page']);
	$fields = array_keys(_test_wde_controls($d['widgets']['enquiry_form']['tree']));
	foreach (array('enquiry_detail', 'my_enquiries_list', 'staff_enquiries_list', 'email_enquiry_admin', 'email_enquiry_client') as $key) {
		$nodes = array();
		_test_wde_nodes($d['widgets'][$key]['tree'], $nodes);
		foreach ($nodes as $n) {
			foreach (array('text', 'href') as $slot) {
				$token = (string)($n['props']['_bindings'][$slot] ?? '');
				if ($token === '' || strpos($token, '__') === 0 || in_array($token, array('reference_code', 'not_found', 'number_of_comments', 'form_item_view'), true)) continue;
				pg_assert_true(in_array($token, $fields, true), "$key binds $token, a field of the form");
			}
		}
	}
	foreach ($d['widgets']['staff_enquiries_list']['config']['search_fields'] as $name) {
		pg_assert_true(in_array($name, $fields, true), "the staff search reads $name");
	}
	$json = json_encode($d['widgets']['enquiry_detail']['tree']);
	pg_assert_contains('"eo_visible_if":"record_found"', $json, 'the record');
	pg_assert_contains('"eo_visible_if":"record_not_found"', $json, 'the not-found note');
	pg_assert_contains('"regionType":"system"', json_encode($d['pages']['email_reply']['tree']), 'the reply e-mail keeps the fixed comment');
}

// The venue visit is the booking widget's starter on its own page; it and
// the enquiry link each other.
function test_wedding_enquiries_book_visit()
{
	$d = _test_wde_load();
	$w = $d['widgets']['visit_slots'];
	pg_assert_same('booking', $w['config']['regionType']);
	pg_assert_same('starter', $w['tree'], 'the editor\'s starter layout');
	pg_assert_contains('{{page:enquiry}}', json_encode($d['pages']['book_visit']['tree']), 'the visit page links the enquiry');
	pg_assert_contains('{{page:book_visit}}', json_encode($d['pages']['enquiry']['tree']), 'the enquiry links the visit page');
	$booking_pages = 0;
	foreach ($d['widgets'] as $x) {
		if (($x['config']['regionType'] ?? '') === 'booking') $booking_pages++;
	}
	pg_assert_same(1, $booking_pages, 'one booking widget');
}

function test_wedding_enquiries_links_ids_and_classes()
{
	$d = _test_wde_load();
	$known = array_flip(_test_wd_page_keys());
	$trees = $d['shared'];
	foreach ($d['pages'] as $p) $trees[] = $p;
	foreach ($d['widgets'] as $w) $trees[] = array($w['config'], $w['tree']);
	$json = json_encode($trees);
	preg_match_all('/\{\{(?:page|tab):([a-z0-9_]+)\}\}/', $json, $m);
	foreach (array_unique($m[1]) as $key) pg_assert_true(isset($known[$key]), "page link {{page:$key}} names a template page");
	foreach (_test_wd_shop_keys() as $key) {
		pg_assert_false(strpos($json, '{{page:' . $key . '}}') !== false, "no tree links to $key");
	}
	$sets = array();
	foreach ($d['pages'] as $key => $p) $sets[$key] = $p['tree'];
	foreach ($d['widgets'] as $key => $w) $sets[$key] = $w['tree'];
	foreach ($sets as $key => $tree) {
		$nodes = array();
		_test_wde_nodes($tree, $nodes);
		$ids = array();
		foreach ($nodes as $n) {
			foreach (preg_split('/\s+/', (string)($n['props']['cssClass'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $c) {
				pg_assert_false((bool)preg_match('/^(pht|pre|pev|pf|pdp|pm|pma|plx|pdl|plw|psl|pco|pcl|pgy|pch|ptv|pph)-/', $c), "$key: class $c is not another template's");
			}
			$id = (string)($n['props']['id'] ?? '');
			if ($id === '' || strpos($id, '-') === false) continue;
			pg_assert_true(strpos($id, 'wd-') === 0, "$key: id $id is the template's");
			pg_assert_false(isset($ids[$id]), "$key: id $id once");
			$ids[$id] = true;
		}
	}
}

// Every literal has its Turkish; an enquiry reads "talep", a wedding
// "düğün" and a deposit "kapora". Page names are address words.
function test_wedding_enquiries_turkish_words()
{
	$map = pg_test_language_map('tr');
	foreach (array('mail.php', 'enquiries.php') as $file) {
		$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-wedding/' . $file);
		preg_match_all("/lang\\('((?:[^'\\\\]|\\\\.)*)'\\)/", $src, $m);
		foreach (array_unique($m[1]) as $raw) {
			$key = stripslashes($raw);
			pg_assert_true(isset($map[$key]) && $map[$key] !== '', "$file: Turkish for '$key'");
			if (!isset($map[$key]) || preg_match('/^[a-z-]+$/', $key)) continue;
			$tr = $map[$key];
			$words = preg_replace('/\^\^[a-z_]+\^\^/', '', $key);
			if (preg_match('/\b[Ee]nquir(y|ies)\b/', $words)) pg_assert_true(mb_stripos($tr, 'talep', 0, 'UTF-8') !== false || mb_stripos($tr, 'taleb', 0, 'UTF-8') !== false, "'$key' says talep");
			if (preg_match('/\b[Ww]eddings?\b/', $words)) pg_assert_true(mb_stripos($tr, 'düğün', 0, 'UTF-8') !== false, "'$key' says düğün");
			if (preg_match('/\b[Dd]eposits?\b/', $words)) pg_assert_true(mb_stripos($tr, 'kapora', 0, 'UTF-8') !== false, "'$key' says kapora");
		}
	}
}
