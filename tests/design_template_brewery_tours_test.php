<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Craft Brewery design template's e-mail builders and tours
 * (includes/design_templates/pinegrap-brewery/mail.php and tours.php): the
 * pages and widgets with their keys and folders, the tours page showing
 * every tour with its booking link, the tour booking request (the tour from
 * ?tour=, the day from today on, the tasters' age, the staff's status,
 * consent with the privacy link, the CAPTCHA, an account for a new sender
 * and both e-mails), the request's page and lists, the private tastings'
 * booking widget, every page link naming a page of the finished template
 * and none a shop page, the template's own ids, classes and e-mail colours,
 * no fixed date, and every literal with its Turkish.
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
require_once(__DIR__ . '/design_template_brewery_test.php');

// parts.php, mail.php and tours.php in one scope, as the template file
// reads them. Read once per run.
function _test_bwt_load()
{
	static $out = null;
	if ($out !== null) return $out;
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-brewery';
	include $dir . '/parts.php';
	include $dir . '/mail.php';
	$part = include $dir . '/tours.php';
	$pages = array();
	foreach ($part['pages'] as $p) $pages[$p['key']] = $p;
	$out = array('part' => $part, 'pages' => $pages, 'widgets' => $part['widgets'], 'tours' => $tours,
		'tour_titles' => $tour_titles, 'tour_times' => $tour_times, 'group_sizes' => $group_sizes,
		'booking_statuses' => $booking_statuses, 'brewery' => $brewery,
		'header' => $email_header_tree(), 'footer' => $email_footer_tree());
	return $out;
}

// Every node of a tree, depth first.
function _test_bwt_nodes($node, &$out)
{
	if (!is_array($node)) return;
	if (isset($node['props'])) $out[] = $node;
	foreach ((array)($node['children'] ?? array()) as $c) _test_bwt_nodes($c, $out);
}

// The name, type, options and _cf of every form control of a tree.
function _test_bwt_controls($node)
{
	$nodes = array();
	_test_bwt_nodes($node, $nodes);
	$out = array();
	foreach ($nodes as $n) {
		$tag = $n['props']['tag'] ?? '';
		if (!in_array($tag, array('input', 'select', 'textarea'), true)) continue;
		$name = '';
		$type = '';
		$required = false;
		foreach ((array)($n['props']['_attrs'] ?? array()) as $a) {
			if ($a['name'] === 'name') $name = $a['value'];
			if ($a['name'] === 'type') $type = $a['value'];
			if ($a['name'] === 'required') $required = true;
		}
		$options = array();
		foreach ((array)($n['children'] ?? array()) as $o) {
			foreach ((array)($o['props']['_attrs'] ?? array()) as $a) {
				if ($a['name'] === 'value' && $a['value'] !== '') $options[] = $a['value'];
			}
		}
		if ($type === 'radio' && isset($out[$name])) continue;
		$out[$name] = array('tag' => $tag, 'type' => $type, 'required' => $required, 'options' => $options, 'cf' => $n['props']['_cf'] ?? array());
	}
	return $out;
}

// The tokens a tree's bindings read.
function _test_bwt_tokens($tree)
{
	$nodes = array();
	_test_bwt_nodes($tree, $nodes);
	$tokens = array();
	foreach ($nodes as $n) {
		foreach ((array)($n['props']['_bindings'] ?? array()) as $t) $tokens[$t] = true;
	}
	return array_keys($tokens);
}

function test_brewery_tours_pages_and_widgets()
{
	$d = _test_bwt_load();
	pg_assert_same(array('pages', 'widgets'), array_keys($d['part']), 'the part returns pages and widgets');
	pg_assert_same(array('tours', 'tour_booking', 'booking_view', 'my_bookings', 'staff_bookings', 'book_online', 'privacy',
		'email_booking_new', 'email_booking_received', 'email_reply'), array_keys($d['pages']));
	pg_assert_same(array('tour_booking_form', 'booking_detail', 'booking_messages', 'my_bookings_list', 'staff_bookings_list',
		'booking_slots', 'email_booking_admin', 'email_booking_client', 'email_comment'), array_keys($d['widgets']));
	$folders = array('tours' => 'public', 'tour_booking' => 'public', 'book_online' => 'public', 'privacy' => 'public',
		'booking_view' => 'registration', 'my_bookings' => 'registration', 'staff_bookings' => 'private',
		'email_booking_new' => 'private', 'email_booking_received' => 'private', 'email_reply' => 'private');
	foreach ($d['pages'] as $key => $p) {
		pg_assert_same($folders[$key], $p['folder'], "$key: its folder");
		foreach (array('name', 'title', 'tree') as $f) pg_assert_true(!empty($p[$f]), "$key has $f");
		pg_assert_false(isset($p['requires']), "$key stands without the shop");
		pg_assert_same('root', $p['tree']['type'], "$key: a page tree");
		pg_assert_true(mb_strlen((string)($p['meta_description'] ?? ''), 'UTF-8') <= 160, "$key: meta description fits a result line");
		if (strpos($key, 'email_') === 0) {
			pg_assert_true(!empty($p['noindex']) && $p['search'] === false && $p['sitemap'] === false, "$key: an e-mail page out of search");
		}
	}
	foreach ($d['widgets'] as $key => $w) {
		pg_assert_true(isset($d['pages'][$w['page']]), "$key sits on a page of this part");
		pg_assert_contains('"templateWidget":"' . $key . '"', json_encode($d['pages'][$w['page']]['tree']), "$key is placed on its page");
	}
	pg_assert_contains('"regionType":"system"', json_encode($d['pages']['email_reply']['tree']), 'the reply e-mail keeps the System region');
	pg_assert_same(lang('privacy-policy'), $d['pages']['privacy']['name'], 'the privacy page the booking widget links');
}

// The tours page shows every tour with a link that opens the request with
// the tour chosen, and leads to the private tastings.
function test_brewery_tours_page_shows_the_tours()
{
	$d = _test_bwt_load();
	$json = json_encode($d['pages']['tours']['tree']);
	foreach ($d['tours'] as $key => $t) {
		pg_assert_contains(json_encode($t['title']), $json, "tour $key is shown");
		$href = '{{page:tour_booking}}?' . http_build_query(array('tour' => $t['title']), '', '&', PHP_QUERY_RFC3986);
		pg_assert_contains(json_encode($href), $json, "tour $key opens the request with the tour chosen");
		pg_assert_true(in_array($t['title'], $d['tour_titles'], true), "tour $key is an answer of the request's pick list");
	}
	pg_assert_contains('{{page:book_online}}', $json, 'the way to a private tasting');
}

// The request: its fields, the tour from a link, the day from today on,
// the tasters' age, the staff's status, consent with the privacy link, the
// CAPTCHA, an account for a new sender and both e-mails.
function test_brewery_tours_booking_form()
{
	$d = _test_bwt_load();
	$form = $d['pages']['tour_booking']['form'];
	pg_assert_same(lang('Tour Bookings'), $form['form_name']);
	pg_assert_same(1, $form['auto_registration'], 'a visitor gets an account');
	pg_assert_same('{{tab:booking_view}}', $form['confirmation_page_id'], 'the request opens its own page');
	pg_assert_same('{{tab:email_booking_new}}', $form['notify_page_id'], 'the brewery is e-mailed');
	pg_assert_same('{{tab:email_booking_received}}', $form['confirm_page_id'], 'the visitor is e-mailed');
	pg_assert_same('{{site_email}}', $form['notify_email']);
	$c = _test_bwt_controls($d['widgets']['tour_booking_form']['tree']);
	pg_assert_same(array('tour', 'tour_date', 'tour_time', 'group_size', 'notes', 'first_name', 'last_name', 'email', 'phone',
		'contact_by', 'status', 'legal_age', 'consent'), array_keys($c), 'the request\'s fields');
	pg_assert_same('tour', $c['tour']['cf']['url_param'] ?? '', 'the tour starts from ?tour=');
	pg_assert_same($d['tour_titles'], $c['tour']['options'], 'the tours');
	pg_assert_same($d['tour_times'], $c['tour_time']['options'], 'the start times');
	pg_assert_same($d['group_sizes'], $c['group_size']['options'], 'the group sizes');
	pg_assert_same('date', $c['tour_date']['type'], 'the tour date is a date');
	pg_assert_same('today', $c['tour_date']['cf']['rules']['min_date'] ?? '', 'no tour date in the past');
	pg_assert_true(!empty($c['status']['cf']['office_use_only']), 'the status is the staff\'s');
	pg_assert_same($d['booking_statuses'], $c['status']['options'], 'the statuses');
	pg_assert_true($c['legal_age']['required'] && $c['legal_age']['type'] === 'checkbox', 'the tasters\' age must be confirmed');
	pg_assert_true($c['consent']['required'], 'consent is required');
	pg_assert_false($c['notes']['required'], 'the notes are optional');
	foreach (array('first_name' => 'first_name', 'last_name' => 'last_name', 'email' => 'email_address', 'phone' => 'mobile_phone') as $name => $field) {
		pg_assert_same($field, $c[$name]['cf']['contact_field'] ?? '', "$name fills the contact");
	}
	$json = json_encode($d['widgets']['tour_booking_form']['tree']);
	pg_assert_contains('{{page:privacy}}', $json, 'the consent links the privacy page');
	pg_assert_contains('"section":"captcha"', $json, 'a CAPTCHA');
	// Every value an e-mail, a list or the request page reads is a field
	// of the form, or the renderer's own.
	$own = array('form_item_view', 'not_found', 'reference_code', 'number_of_comments', '__edit_url', 'can_edit', '__new_account_email',
		'__new_account_set_password_url', 'has_set_password_link', 'has_new_account', 'record_found', 'record_not_found', 'submitted_this_session');
	foreach (array('booking_detail', 'my_bookings_list', 'staff_bookings_list', 'email_booking_admin', 'email_booking_client') as $key) {
		foreach (_test_bwt_tokens($d['widgets'][$key]['tree']) as $t) {
			pg_assert_true(isset($c[$t]) || in_array($t, $own, true), "$key reads $t, a field of the request");
		}
		pg_assert_same('{{tab:tour_booking}}', $d['widgets'][$key]['config']['custom_form_page_id'], "$key reads the requests");
	}
	foreach ($d['widgets']['staff_bookings_list']['config']['search_fields'] as $f) {
		pg_assert_true(isset($c[$f]), "the staff search reads $f, a field");
	}
}

// The request page is its sender's (and the staff's); My Tour Bookings
// lists the viewer's own, the staff's list everybody's; the messages go by
// e-mail; the private tastings are the booking widget's starter.
function test_brewery_tours_request_pages()
{
	$d = _test_bwt_load();
	$w = $d['widgets'];
	pg_assert_same('submitter_only', $w['booking_detail']['config']['access_control'], 'only its sender and the staff open a request');
	foreach (array('email_booking_admin', 'email_booking_client') as $key) {
		pg_assert_same('public', $w[$key]['config']['access_control'], "$key is drawn for the submission just sent");
	}
	pg_assert_true(!empty($w['my_bookings_list']['config']['viewer_filter_submitter']), 'My Tour Bookings lists the viewer\'s own');
	pg_assert_false(isset($w['staff_bookings_list']['config']['viewer_filter']), 'the staff list is everybody\'s');
	pg_assert_true(!empty($w['staff_bookings_list']['config']['search_enabled']), 'the staff list has a search');
	foreach (array('my_bookings_list', 'staff_bookings_list') as $key) {
		pg_assert_same('{{tab:booking_view}}', $w[$key]['config']['detail_page_id'], "$key opens the request");
	}
	$c = $d['pages']['booking_view']['comments'];
	pg_assert_true($c['allow_new'] && $c['login'], 'signed-in messages');
	pg_assert_same('{{tab:email_reply}}', $c['email_page'], 'messages go by e-mail');
	pg_assert_same('comments', $w['booking_messages']['config']['regionType']);
	pg_assert_same('comments', $w['email_comment']['config']['regionType']);
	$json = json_encode($w['booking_detail']['tree']);
	foreach (array('record_found', 'record_not_found', 'has_new_account', 'submitted_this_session') as $flag) {
		pg_assert_contains('"eo_visible_if":"' . $flag . '"', $json, "the request page shows $flag");
	}
	pg_assert_same(array('regionType' => 'booking'), $w['booking_slots']['config'], 'the booking widget');
	pg_assert_same('starter', $w['booking_slots']['tree'], 'in the editor\'s starter layout');
	$book = json_encode($d['pages']['book_online']['tree']);
	pg_assert_contains('{{page:tour_booking}}', $book, 'the private tastings lead to the tour request');
	pg_assert_contains('{{page:book_online}}', json_encode($d['pages']['tours']['tree']), 'the tours lead to the private tastings');
}

function test_brewery_tours_links_ids_and_classes()
{
	$d = _test_bwt_load();
	$known = array_flip(_test_bw_page_keys());
	$trees = array($d['header'], $d['footer']);
	foreach ($d['pages'] as $p) $trees[] = $p;
	foreach ($d['widgets'] as $w) $trees[] = array($w['config'], $w['tree']);
	$json = json_encode($trees);
	preg_match_all('/\{\{(?:page|tab):([a-z0-9_]+)\}\}/', $json, $m);
	pg_assert_true(count(array_unique($m[1])) > 8, 'links found');
	foreach (array_unique($m[1]) as $key) pg_assert_true(isset($known[$key]), "page link {{page:$key}} names a template page");
	foreach (_test_bw_shop_keys() as $key) {
		pg_assert_false(strpos($json, '{{page:' . $key . '}}') !== false, "no tree links to $key");
		pg_assert_false(strpos($json, '{{tab:' . $key . '}}') !== false, "no setting names $key");
	}
	$nodes = array();
	foreach ($trees as $t) _test_bwt_nodes($t['tree'] ?? $t, $nodes);
	foreach ($d['widgets'] as $w) _test_bwt_nodes($w['tree'], $nodes);
	foreach ($nodes as $n) {
		foreach (preg_split('/\s+/', (string)($n['props']['cssClass'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $c) {
			pg_assert_false((bool)preg_match('/^(pht|pre|pev|pf|pdp|pm|pma|plx|pdl|plw|psl|pco|pcl|pgy|pch|ptv|pph|pwd|pcn|pmu|psc|pmz|pai)-/', $c), "class $c is not another template's");
			pg_assert_false(strpos($c, 'opacity-') === 0, "class $c fades text");
		}
		$id = (string)($n['props']['id'] ?? '');
		if ($id !== '' && strpos($id, '-') !== false) pg_assert_true(strpos($id, 'bw-') === 0, "id $id is the template's");
	}
	// The e-mail footer names the tours address and the drinking age, and
	// every colour of the e-mails is the template's amber family.
	$footer = json_encode($d['footer']);
	pg_assert_contains($d['brewery']['tours'], $footer, 'the e-mail footer names the tours address');
	pg_assert_contains(json_encode($d['brewery']['adults']), $footer, 'the e-mail footer says the beers are for adults');
	foreach (array('mail.php', 'tours.php') as $file) {
		$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-brewery/' . $file);
		preg_match_all('/#[0-9a-f]{6}\b/i', $src, $colors);
		foreach (array_unique($colors[0]) as $hex) {
			pg_assert_true(in_array(strtolower($hex), array('#92400e', '#fde68a', '#fffbeb', '#ffffff', '#1f2937', '#4b5563', '#b45309'), true), "$file: e-mail colour $hex is the template's");
		}
	}
}

// No year, month or fixed day in the sources: a request's sample date is
// words.
function test_brewery_tours_no_fixed_dates()
{
	foreach (array('mail.php', 'tours.php') as $file) {
		$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-brewery/' . $file);
		$src = preg_replace('/@copyright[^\n]*/', '', $src);
		pg_assert_false((bool)preg_match('/\b(19|20)[0-9]{2}\b/', $src), "$file: no year");
		pg_assert_false((bool)preg_match('/\b[0-9]{4}-[0-9]{2}-[0-9]{2}\b/', $src), "$file: no fixed day");
		pg_assert_false((bool)preg_match('/\b(January|February|March|April|June|July|August|September|October|November|December)\b/', $src), "$file: no month");
	}
}

// Every literal has its Turkish; a tour booking reads "tur rezervasyonu"
// (never the shared "Bookings", which is "Randevular"), the taproom
// "tadım salonu".
function test_brewery_tours_turkish_words()
{
	$map = pg_test_language_map('tr');
	foreach (array('mail.php', 'tours.php') as $file) {
		$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-brewery/' . $file);
		preg_match_all("/lang\\('((?:[^'\\\\]|\\\\.)*)'\\)/", $src, $m);
		foreach (array_unique($m[1]) as $raw) {
			$key = stripslashes($raw);
			pg_assert_true(isset($map[$key]) && $map[$key] !== '', "$file: Turkish for '$key'");
			if (!isset($map[$key])) continue;
			$tr = $map[$key];
			if (preg_match('/\b[Tt]our [Bb]ookings?\b/', $key)) pg_assert_true(mb_stripos($tr, 'tur rezervasyon', 0, 'UTF-8') !== false, "'$key' says tur rezervasyonu");
			if (preg_match('/\b[Tt]aproom\b/', $key)) pg_assert_true(mb_stripos($tr, 'tadım salonu', 0, 'UTF-8') !== false, "'$key' says tadım salonu");
			pg_assert_false(in_array($key, array('Bookings', 'Booking', 'Time', 'Entry', 'Kind'), true), "$file: '$key' means something else in Turkish");
		}
	}
}
