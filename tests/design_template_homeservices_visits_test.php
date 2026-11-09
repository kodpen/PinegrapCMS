<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Home Services design template's e-mail builders and visit
 * bookings (includes/design_templates/pinegrap-homeservices/mail.php and
 * visits.php): the pages and widgets, the booking form reading what the
 * service page, the price guide and the area cards send (?service=,
 * ?area=), the day never in the past, the office-only status, the
 * booking's page and lists, the e-mails binding fields the form has and
 * kept out of the search, every page link naming a template page and none
 * a shop page, ids and classes the template's own, no fixed date, and
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

// The page keys of the finished template and the shop's pages.
require_once(__DIR__ . '/design_template_homeservices_test.php');

// The visits' part as the template file reads it: parts.php, mail.php,
// services.php (its record page pieces), then visits.php in the same
// scope. Read once per run.
function _test_hsv_load()
{
	static $out = null;
	if ($out !== null) return $out;
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-homeservices';
	include $dir . '/parts.php';
	include $dir . '/mail.php';
	$services_part = include $dir . '/services.php';
	$public_part = include $dir . '/pages_public.php';
	$part = include $dir . '/visits.php';
	$pages = array();
	foreach ($part['pages'] as $p) $pages[$p['key']] = $p;
	$area_trees = array();
	foreach (array_keys($areas) as $key) $area_trees[] = $area_card($key);
	$out = array('pages' => $pages, 'widgets' => $part['widgets'], 'service_choices' => $service_choices,
		'area_names' => $area_names, 'time_windows' => $time_windows, 'access_choices' => $access_choices,
		'statuses' => $visit_statuses, 'area_cards' => $area_trees, 'prices_page' => null,
		'service_detail' => $services_part['widgets']['service_detail'],
		'shared' => array($email_header_tree(), $email_footer_tree()));
	foreach ($public_part['pages'] as $p) {
		if ($p['key'] === 'prices') $out['prices_page'] = $p;
	}
	return $out;
}

function _test_hsv_nodes($node, &$out)
{
	if (!is_array($node)) return;
	if (isset($node['props'])) $out[] = $node;
	foreach ((array)($node['children'] ?? array()) as $c) _test_hsv_nodes($c, $out);
}

// The name, values and settings of every form control of a tree; radios
// of one name gather their values.
function _test_hsv_controls($node)
{
	$nodes = array();
	_test_hsv_nodes($node, $nodes);
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

// The ?name= values of every {{page:book}} link of a tree.
function _test_hsv_book_params($tree, $name)
{
	$nodes = array();
	_test_hsv_nodes($tree, $nodes);
	$out = array();
	foreach ($nodes as $n) {
		$href = (string)($n['props']['href'] ?? '');
		if (strpos($href, '{{page:book}}?') !== 0) continue;
		parse_str(substr($href, strlen('{{page:book}}?')), $q);
		if (isset($q[$name])) $out[] = $q[$name];
	}
	return $out;
}

function test_homeservices_visits_pages_and_widgets()
{
	$d = _test_hsv_load();
	pg_assert_same(array('book', 'visit_view', 'my_visits', 'staff_visits', 'privacy', 'email_visit_new', 'email_visit_received', 'email_reply'), array_keys($d['pages']));
	pg_assert_same(array('book_form', 'visit_detail', 'visit_messages', 'my_visits_list', 'staff_visits_list', 'email_visit_admin', 'email_visit_client', 'email_comment'), array_keys($d['widgets']));
	pg_assert_same('public', $d['pages']['book']['folder']);
	pg_assert_same('public', $d['pages']['privacy']['folder']);
	pg_assert_same('registration', $d['pages']['visit_view']['folder'], 'a booking is read signed in');
	pg_assert_same('registration', $d['pages']['my_visits']['folder']);
	pg_assert_same('private', $d['pages']['staff_visits']['folder'], 'only the staff list every booking');
	foreach (array('email_visit_new', 'email_visit_received', 'email_reply') as $key) {
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
	pg_assert_same('submitter_only', $d['widgets']['visit_detail']['config']['access_control'], 'a booking is its sender\'s');
	pg_assert_true(!empty($d['widgets']['my_visits_list']['config']['viewer_filter_submitter']), 'My Visits lists the own bookings');
	pg_assert_true(!empty($d['widgets']['staff_visits_list']['config']['search_enabled']), 'the staff search');
}

// The form opens the booking's page and mails both sides; its service and
// area start with what the links send, the day is never in the past, the
// time window and the access are pick lists and the status is the
// company's.
function test_homeservices_visits_form()
{
	$d = _test_hsv_load();
	$form = $d['pages']['book']['form'];
	pg_assert_same('{{tab:visit_view}}', $form['confirmation_page_id']);
	pg_assert_same(1, $form['auto_registration']);
	pg_assert_same('{{tab:email_visit_new}}', $form['notify_page_id']);
	pg_assert_same('{{tab:email_visit_received}}', $form['confirm_page_id']);
	$c = _test_hsv_controls($d['widgets']['book_form']['tree']);
	pg_assert_same(array('service', 'area', 'notes', 'visit_date', 'time_window', 'address', 'access', 'first_name', 'last_name', 'email', 'phone', 'status', 'consent'), array_keys($c), 'the fields');
	pg_assert_same($d['service_choices'], $c['service']['options'], 'the services');
	pg_assert_same('service', $c['service']['cf']['url_param'] ?? '', 'the service reads ?service=');
	pg_assert_same($d['area_names'], $c['area']['options'], 'the areas');
	pg_assert_same('area', $c['area']['cf']['url_param'] ?? '', 'the area reads ?area=');
	pg_assert_same('date', $c['visit_date']['attrs']['type'] ?? '', 'the day is a date');
	pg_assert_same('today', $c['visit_date']['cf']['rules']['min_date'] ?? '', 'no day in the past');
	pg_assert_same($d['time_windows'], $c['time_window']['options'], 'the time windows');
	pg_assert_same(array_column($d['access_choices'], 0), $c['access']['options'], 'how we get in');
	pg_assert_same($d['statuses'], $c['status']['options'], 'the statuses');
	pg_assert_true(!empty($c['status']['cf']['office_use_only']), 'the status is the company\'s');
	pg_assert_same('email_address', $c['email']['cf']['contact_field'] ?? '', 'the e-mail is the contact\'s');
}

// Every value a link sends is an answer of the form: the area cards'
// ?area=, the price guide's ?service=, and the service page's own link
// sends the record's name.
function test_homeservices_visits_links_choose_in_the_form()
{
	$d = _test_hsv_load();
	$c = _test_hsv_controls($d['widgets']['book_form']['tree']);
	$seen = array();
	foreach ($d['area_cards'] as $card) {
		foreach (_test_hsv_book_params($card, 'area') as $v) {
			pg_assert_true(in_array($v, $c['area']['options'], true), "?area=$v is an answer");
			$seen[$v] = true;
		}
	}
	pg_assert_same(count($d['area_names']), count($seen), 'every area card books its area');
	pg_assert_true($d['prices_page'] !== null, 'the price guide is there');
	$sent = _test_hsv_book_params($d['prices_page']['tree'], 'service');
	pg_assert_true(count($sent) > 0, 'the price guide books its services');
	foreach ($sent as $v) pg_assert_true(in_array($v, $c['service']['options'], true), "?service=$v is an answer");
	$json = json_encode($d['service_detail']['tree']);
	pg_assert_contains('"page_id":"{{tab:book}}","params":[{"name":"service","token":"name"}]', $json, 'the service page books its service');
}

// The booking's page carries its messages and mails each one; the e-mails
// and lists bind fields the form has.
function test_homeservices_visits_record_and_mail()
{
	$d = _test_hsv_load();
	$comments = $d['pages']['visit_view']['comments'];
	pg_assert_true($comments['allow_new'] && $comments['login'], 'messages from the signed-in sender');
	pg_assert_same('{{tab:email_reply}}', $comments['email_page']);
	pg_assert_same('{{site_email}}', $comments['notify_email'], 'the company hears every message');
	$fields = array_keys(_test_hsv_controls($d['widgets']['book_form']['tree']));
	foreach (array('visit_detail', 'my_visits_list', 'staff_visits_list', 'email_visit_admin', 'email_visit_client') as $key) {
		$nodes = array();
		_test_hsv_nodes($d['widgets'][$key]['tree'], $nodes);
		foreach ($nodes as $n) {
			$token = (string)($n['props']['_bindings']['text'] ?? '');
			if ($token === '' || strpos($token, '__') === 0 || in_array($token, array('reference_code', 'not_found', 'number_of_comments'), true)) continue;
			pg_assert_true(in_array($token, $fields, true), "$key binds $token, a field of the form");
		}
		pg_assert_same('{{tab:book}}', $d['widgets'][$key]['config']['custom_form_page_id'], "$key reads the booking form");
	}
	foreach ($d['widgets']['staff_visits_list']['config']['search_fields'] as $name) {
		pg_assert_true(in_array($name, $fields, true), "the staff search reads $name");
	}
	$json = json_encode($d['widgets']['visit_detail']['tree']);
	pg_assert_contains('"eo_visible_if":"record_found"', $json, 'the record');
	pg_assert_contains('"eo_visible_if":"record_not_found"', $json, 'the not-found note');
	pg_assert_contains('"eo_visible_if":"can_edit"', $json, 'the staff\'s Edit button');
	pg_assert_contains('"regionType":"system"', json_encode($d['pages']['email_reply']['tree']), 'the reply e-mail keeps the fixed comment');
	foreach (array('email_visit_admin', 'email_visit_client') as $key) {
		pg_assert_contains('{{page:visit_view}}?r=^^reference_code^^', json_encode($d['widgets'][$key]['tree'], JSON_UNESCAPED_SLASHES), "$key opens the booking");
	}
}

function test_homeservices_visits_links_ids_and_classes()
{
	$d = _test_hsv_load();
	$known = array_flip(_test_hs_page_keys());
	$trees = $d['shared'];
	foreach ($d['pages'] as $p) $trees[] = $p['tree'];
	foreach ($d['widgets'] as $w) $trees[] = $w['tree'];
	$json = json_encode(array($d['shared'], $d['pages'], $d['widgets']));
	preg_match_all('/\{\{(?:page|tab):([a-z0-9_]+)\}\}/', $json, $m);
	foreach (array_unique($m[1]) as $key) pg_assert_true(isset($known[$key]), "page link {{page:$key}} names a template page");
	foreach (_test_hs_shop_keys() as $key) {
		pg_assert_false(strpos($json, '{{page:' . $key . '}}') !== false, "no tree links to $key");
	}
	$nodes = array();
	foreach ($trees as $t) _test_hsv_nodes($t, $nodes);
	foreach ($nodes as $n) {
		foreach (preg_split('/\s+/', (string)($n['props']['cssClass'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $c) {
			pg_assert_false((bool)preg_match('/^(pht|pre|pev|pf|pdp|pm|pma|plx|pdl|plw|psl|pco|pcl|pgy|pch|ptv|pbk|psh|pwd|pmu)-/', $c), "class $c is not another template's");
		}
		$id = (string)($n['props']['id'] ?? '');
		if ($id !== '' && strpos($id, '-') !== false) pg_assert_true(strpos($id, 'hs-') === 0, "id $id is the template's");
	}
}

// The sample is never dated.
function test_homeservices_visits_no_fixed_dates()
{
	$d = _test_hsv_load();
	$json = json_encode(array($d['pages'], $d['widgets']), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	$json = str_replace(array('www.w3.org/2000/svg', '{{year}}'), '', $json);
	pg_assert_false((bool)preg_match('/\b20[0-9]{2}\b/', $json), 'no year in the sample');
	pg_assert_false((bool)preg_match('/\b(January|February|March|April|June|July|August|September|October|November|December)\b/', $json), 'no month in the sample');
}

// Every literal has its Turkish; a visit reads "ziyaret", a service
// "hizmet", an estimate "teklif", and none says "rezervasyon".
function test_homeservices_visits_turkish_words()
{
	$map = pg_test_language_map('tr');
	foreach (array('mail.php', 'visits.php') as $file) {
		$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-homeservices/' . $file);
		preg_match_all("/lang\\('((?:[^'\\\\]|\\\\.)*)'\\)/", $src, $m);
		foreach (array_unique($m[1]) as $raw) {
			$key = stripslashes($raw);
			pg_assert_true(isset($map[$key]) && $map[$key] !== '', "$file: Turkish for '$key'");
			if (!isset($map[$key])) continue;
			$tr = $map[$key];
			// A ^^field^^ token is the field's name, not a word.
			$words = preg_replace('/\^\^[a-z_]+\^\^/', '', $key);
			if (preg_match('/\b[Vv]isits?\b/', $words)) pg_assert_true(mb_stripos($tr, 'ziyaret', 0, 'UTF-8') !== false, "'$key' says ziyaret");
			if (preg_match('/\b[Ss]ervices?\b/', $words)) pg_assert_true(mb_stripos($tr, 'hizmet', 0, 'UTF-8') !== false, "'$key' says hizmet");
			if (preg_match('/\b[Ee]stimates?\b/', $words)) pg_assert_true(mb_stripos($tr, 'teklif', 0, 'UTF-8') !== false, "'$key' says teklif");
			pg_assert_false(mb_stripos($tr, 'rezervasyon', 0, 'UTF-8') !== false, "'$key' never says rezervasyon");
		}
		foreach (array("lang('Service')", "lang('Services')", "lang('Booking')", "lang('book')") as $k) {
			pg_assert_false(strpos($src, $k) !== false, "$file does not use $k");
		}
	}
}
