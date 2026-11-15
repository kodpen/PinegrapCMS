<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Car Rental design template's e-mail builders and booking
 * requests (includes/design_templates/pinegrap-carrental/mail.php and
 * bookings.php): the pages and widgets with their keys and folders, the
 * booking request (the desk, class, car and deal from a link, every link
 * of the other pages an answer of its list, the dates from today on, the
 * extras and the driver's age, the staff's status, consent with the
 * privacy link, the CAPTCHA, an account for a new sender and both
 * e-mails), the request's page and lists reading only the form's fields,
 * every page link naming a page of the finished template, the template's
 * ids, classes and e-mail colours, no fixed date, and every literal with
 * its Turkish.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The page keys of the finished template (_test_cr_page_keys()).
require_once __DIR__ . '/design_template_carrental_test.php';

// parts.php, pages_public.php, fleet.php, mail.php and bookings.php in one
// scope, as the template file reads them. Read once per run.
function _test_crb_load()
{
	static $out = null;
	if ($out !== null) return $out;
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-carrental';
	include $dir . '/parts.php';
	$public = include $dir . '/pages_public.php';
	$fleet = include $dir . '/fleet.php';
	include $dir . '/mail.php';
	$part = include $dir . '/bookings.php';
	$pages = array();
	foreach ($part['pages'] as $p) $pages[$p['key']] = $p;
	$out = array('part' => $part, 'pages' => $pages, 'widgets' => $part['widgets'],
		'others' => array($public, $fleet['pages'], $fleet['widgets'], $header(), $footer(), $booking_band()),
		'class_titles' => $class_titles, 'car_titles' => $car_titles, 'location_titles' => $location_titles,
		'deal_choices' => $deal_choices, 'extras' => $extras, 'time_choices' => $time_choices, 'age_choices' => $age_choices,
		'booking_statuses' => $booking_statuses, 'company' => $company,
		'header' => $email_header_tree(), 'footer' => $email_footer_tree());
	return $out;
}

// Every node of a tree, depth first.
function _test_crb_nodes($node, &$out)
{
	if (!is_array($node)) return;
	if (isset($node['props'])) $out[] = $node;
	foreach ((array)($node['children'] ?? array()) as $c) _test_crb_nodes($c, $out);
}

// The tag, type, required flag, options and _cf of every form control of
// a tree, by name; a group of radios or boxes counts once, with its values.
function _test_crb_controls($tree)
{
	$nodes = array();
	_test_crb_nodes($tree, $nodes);
	$out = array();
	foreach ($nodes as $n) {
		$tag = $n['props']['tag'] ?? '';
		if (!in_array($tag, array('input', 'select', 'textarea'), true)) continue;
		$name = '';
		$type = '';
		$value = '';
		$required = false;
		foreach ((array)($n['props']['_attrs'] ?? array()) as $a) {
			if ($a['name'] === 'name') $name = $a['value'];
			if ($a['name'] === 'type') $type = $a['value'];
			if ($a['name'] === 'value') $value = $a['value'];
			if ($a['name'] === 'required') $required = true;
		}
		$options = array();
		foreach ((array)($n['children'] ?? array()) as $o) {
			foreach ((array)($o['props']['_attrs'] ?? array()) as $a) {
				if ($a['name'] === 'value' && $a['value'] !== '') $options[] = $a['value'];
			}
		}
		if (($type === 'radio' || $type === 'checkbox') && isset($out[$name])) {
			$out[$name]['options'][] = $value;
			continue;
		}
		if ($type === 'radio' || $type === 'checkbox') $options = array($value);
		$out[$name] = array('tag' => $tag, 'type' => $type, 'required' => $required, 'options' => $options, 'cf' => $n['props']['_cf'] ?? array());
	}
	return $out;
}

// The tokens a tree's bindings read.
function _test_crb_tokens($tree)
{
	$nodes = array();
	_test_crb_nodes($tree, $nodes);
	$tokens = array();
	foreach ($nodes as $n) {
		foreach ((array)($n['props']['_bindings'] ?? array()) as $t) $tokens[$t] = true;
	}
	return array_keys($tokens);
}

function test_carrental_bookings_pages_and_widgets()
{
	$d = _test_crb_load();
	pg_assert_same(array('pages', 'widgets'), array_keys($d['part']), 'the part returns pages and widgets');
	pg_assert_same(array('book', 'booking_view', 'my_bookings', 'staff_bookings', 'privacy',
		'email_booking_new', 'email_booking_received', 'email_reply'), array_keys($d['pages']));
	pg_assert_same(array('book_form', 'booking_detail', 'booking_messages', 'my_bookings_list', 'staff_bookings_list',
		'email_booking_admin', 'email_booking_client', 'email_comment'), array_keys($d['widgets']));
	$folders = array('book' => 'public', 'privacy' => 'public', 'booking_view' => 'registration', 'my_bookings' => 'registration',
		'staff_bookings' => 'private', 'email_booking_new' => 'private', 'email_booking_received' => 'private', 'email_reply' => 'private');
	$names = array();
	foreach ($d['pages'] as $key => $p) {
		pg_assert_same($folders[$key], $p['folder'], "$key: its folder");
		foreach (array('name', 'title', 'tree') as $f) pg_assert_true(!empty($p[$f]), "$key has $f");
		pg_assert_false(isset($p['requires']), "$key stands without the shop");
		pg_assert_same('root', $p['tree']['type'], "$key: a page tree");
		pg_assert_true(!isset($names[$p['name']]), "$key: page name used once");
		$names[$p['name']] = true;
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
	pg_assert_same(lang('privacy-policy'), $d['pages']['privacy']['name'], 'the privacy page the consent boxes link');
}

// The request: its fields, the choices a link makes, the dates from today
// on, the extras, the driver's age, the staff's status, consent with the
// privacy link, the CAPTCHA, an account for a new sender and both e-mails.
function test_carrental_bookings_booking_form()
{
	$d = _test_crb_load();
	$form = $d['pages']['book']['form'];
	pg_assert_same(lang('Booking Requests'), $form['form_name']);
	pg_assert_same(1, $form['auto_registration'], 'a visitor gets an account');
	pg_assert_same('{{tab:booking_view}}', $form['confirmation_page_id'], 'the request opens its own page');
	pg_assert_same('{{tab:email_booking_new}}', $form['notify_page_id'], 'the company is e-mailed');
	pg_assert_same('{{tab:email_booking_received}}', $form['confirm_page_id'], 'the renter is e-mailed');
	pg_assert_same('{{site_email}}', $form['notify_email']);
	$c = _test_crb_controls($d['widgets']['book_form']['tree']);
	pg_assert_same(array('pickup_location', 'return_location', 'pickup_date', 'pickup_time', 'return_date', 'return_time', 'flight',
		'car_class', 'car', 'deal', 'extras', 'driver_age', 'notes', 'first_name', 'last_name', 'email', 'phone', 'status', 'consent'),
		array_keys($c), 'the request\'s fields');
	preg_match_all('/\^\^([a-z_]+)\^\^/', $form['notify_subject'], $m);
	foreach ($m[1] as $f) pg_assert_true(isset($c[$f]), "the notice subject reads $f, a field");
	foreach (array('pickup_location' => 'location', 'car_class' => 'class', 'car' => 'car', 'deal' => 'deal') as $name => $param) {
		pg_assert_same($param, $c[$name]['cf']['url_param'] ?? '', "$name starts from ?$param=");
	}
	pg_assert_same($d['location_titles'], $c['pickup_location']['options'], 'the pick-up desks');
	pg_assert_same($d['location_titles'], $c['return_location']['options'], 'the return desks');
	pg_assert_same($d['class_titles'], $c['car_class']['options'], 'the classes');
	pg_assert_same($d['car_titles'], $c['car']['options'], 'the cars');
	pg_assert_same($d['deal_choices'], $c['deal']['options'], 'the deals');
	pg_assert_same($d['time_choices'], $c['pickup_time']['options'], 'the pick-up times');
	pg_assert_same($d['time_choices'], $c['return_time']['options'], 'the return times');
	pg_assert_same($d['age_choices'], $c['driver_age']['options'], 'the driver\'s age bands');
	pg_assert_same('checkbox', $c['extras']['type'], 'the extras are boxes');
	pg_assert_same(count($d['extras']), count($c['extras']['options']), 'one box per extra');
	foreach (array_values($d['extras']) as $n => $e) {
		pg_assert_true(strpos($c['extras']['options'][$n], $e['title']) === 0, "the box of {$e['title']}");
	}
	foreach (array('pickup_date', 'return_date') as $f) {
		pg_assert_same('date', $c[$f]['type'], "$f is a date");
		pg_assert_same('today', $c[$f]['cf']['rules']['min_date'] ?? '', "no $f in the past");
	}
	foreach (array('pickup_location', 'pickup_date', 'return_date', 'car_class', 'first_name', 'last_name', 'email', 'phone', 'consent') as $f) {
		pg_assert_true($c[$f]['required'], "$f is required");
	}
	foreach (array('return_location', 'flight', 'car', 'notes') as $f) pg_assert_false($c[$f]['required'], "$f is optional");
	pg_assert_true(!empty($c['status']['cf']['office_use_only']), 'the status is the staff\'s');
	pg_assert_same($d['booking_statuses'], $c['status']['options'], 'the statuses');
	foreach (array('first_name' => 'first_name', 'last_name' => 'last_name', 'email' => 'email_address', 'phone' => 'mobile_phone') as $name => $field) {
		pg_assert_same($field, $c[$name]['cf']['contact_field'] ?? '', "$name fills the contact");
	}
	$json = json_encode($d['widgets']['book_form']['tree']);
	pg_assert_contains('{{page:privacy}}', $json, 'the consent links the privacy page');
	pg_assert_contains('"section":"captcha"', $json, 'a CAPTCHA');
	// Every value an e-mail, a list or the request page reads is a field
	// of the form, or the renderer's own.
	$own = array('form_item_view', 'not_found', 'reference_code', 'number_of_comments', '__edit_url', 'can_edit', '__new_account_email',
		'__new_account_set_password_url', 'has_set_password_link', 'has_new_account', 'record_found', 'record_not_found', 'submitted_this_session',
		'first_name');
	foreach (array('booking_detail', 'my_bookings_list', 'staff_bookings_list', 'email_booking_admin', 'email_booking_client') as $key) {
		foreach (_test_crb_tokens($d['widgets'][$key]['tree']) as $t) {
			pg_assert_true(isset($c[$t]) || in_array($t, $own, true), "$key reads $t, a field of the request");
		}
		pg_assert_same('{{tab:book}}', $d['widgets'][$key]['config']['custom_form_page_id'], "$key reads the requests");
	}
	foreach ($d['widgets']['staff_bookings_list']['config']['search_fields'] as $f) {
		pg_assert_true(isset($c[$f]), "the staff search reads $f, a field");
	}
}

// Every link of the other pages that opens the request with a choice made
// sends an answer of that choice's list.
function test_carrental_bookings_links_send_answers()
{
	$d = _test_crb_load();
	$lists = array('location' => $d['location_titles'], 'class' => $d['class_titles'], 'deal' => $d['deal_choices']);
	$json = json_encode($d['others']);
	preg_match_all('/"(\{\{page:book\}\}\?[^"]*)"/', $json, $m);
	pg_assert_true(count($m[1]) > 3, 'links to the request with a choice found');
	foreach (array_unique($m[1]) as $href) {
		$q = _test_cr_query(json_decode('"' . $href . '"'), 'book');
		foreach ($q as $param => $value) {
			pg_assert_true(isset($lists[$param]), "?$param= is a choice of the request");
			if (isset($lists[$param])) pg_assert_true(in_array($value, $lists[$param], true), "?$param=$value is an answer");
		}
	}
	// The car page's Book button sends the car's title, an answer of the
	// Car list.
	pg_assert_contains('"page_id":"{{tab:book}}","params":[{"name":"car","token":"title"}]', $json, 'the car page sends its title as ?car=');
}

// The request page is its sender's (and the staff's); My Bookings lists
// the viewer's own, the staff's list everybody's; the messages go by
// e-mail.
function test_carrental_bookings_request_pages()
{
	$d = _test_crb_load();
	$w = $d['widgets'];
	pg_assert_same('submitter_only', $w['booking_detail']['config']['access_control'], 'only its sender and the staff open a request');
	foreach (array('email_booking_admin', 'email_booking_client') as $key) {
		pg_assert_same('public', $w[$key]['config']['access_control'], "$key is drawn for the submission just sent");
	}
	pg_assert_true(!empty($w['my_bookings_list']['config']['viewer_filter_submitter']), 'My Bookings lists the viewer\'s own');
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
}

function test_carrental_bookings_links_ids_and_classes()
{
	$d = _test_crb_load();
	$known = array_flip(_test_cr_page_keys());
	$trees = array('email_header' => $d['header'], 'email_footer' => $d['footer']);
	foreach ($d['pages'] as $key => $p) $trees[$key] = $p['tree'];
	foreach ($d['widgets'] as $key => $w) $trees['widget ' . $key] = $w['tree'];
	$json = json_encode(array($trees, $d['part']['pages'], $d['widgets']));
	preg_match_all('/\{\{(?:page|tab):([a-z0-9_]+)\}\}/', $json, $m);
	pg_assert_true(count(array_unique($m[1])) > 8, 'links found');
	foreach (array_unique($m[1]) as $key) pg_assert_true(isset($known[$key]), "page link {{page:$key}} names a template page");
	$own = array('pcr-brand', 'pcr-display', 'pcr-eyebrow', 'pcr-hero', 'pcr-lift', 'pcr-price');
	foreach ($trees as $key => $tree) {
		$nodes = array();
		_test_crb_nodes($tree, $nodes);
		$ids = array();
		foreach ($nodes as $n) {
			foreach (preg_split('/\s+/', (string)($n['props']['cssClass'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $c) {
				if (preg_match('/^p[a-z]{2}-/', $c)) pg_assert_true(in_array($c, $own, true), "$key: class $c is the template's");
				pg_assert_false(strpos($c, 'opacity-') === 0, "$key: no faded text ($c)");
			}
			// The form's controls; the Comments widget's own fields keep
			// their names (comment_message), a section's anchor its own.
			$id = (string)($n['props']['id'] ?? '');
			if ($id === '' || strpos($id, '-') === false || !in_array($n['props']['tag'] ?? '', array('input', 'select', 'textarea'), true)) continue;
			pg_assert_true(strpos($id, 'cr-') === 0, "$key: id $id is the template's");
			pg_assert_true(!isset($ids[$id]), "$key: id $id used once");
			$ids[$id] = true;
		}
	}
	// The e-mail footer names the company's address and phone, and every
	// colour of the e-mails is the template's own.
	$footer = json_encode($d['footer']);
	pg_assert_contains(trim(json_encode($d['company']['address']), '"'), $footer, 'the e-mail footer names the address');
	pg_assert_contains($d['company']['phone'], $footer, 'the e-mail footer names the phone');
	foreach (array('mail.php', 'bookings.php') as $file) {
		$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-carrental/' . $file);
		preg_match_all('/#[0-9a-f]{6}\b/i', $src, $colors);
		foreach (array_unique($colors[0]) as $hex) {
			pg_assert_true(in_array(strtolower($hex), array('#b45309', '#e7e5e4', '#fafaf9', '#ffffff', '#1f2937', '#4b5563'), true), "$file: e-mail colour $hex is the template's");
		}
	}
}

// No year, month or fixed day in the sources: a request's sample date is
// words.
function test_carrental_bookings_no_fixed_dates()
{
	foreach (array('mail.php', 'bookings.php') as $file) {
		$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-carrental/' . $file);
		$src = preg_replace('/@copyright[^\n]*/', '', $src);
		pg_assert_false((bool)preg_match('/\b(19|20)[0-9]{2}\b/', $src), "$file: no year");
		pg_assert_false((bool)preg_match('/\b[0-9]{4}-[0-9]{2}-[0-9]{2}\b/', $src), "$file: no fixed day");
		pg_assert_false((bool)preg_match('/\b(January|February|March|April|June|July|August|September|October|November|December)\b/', $src), "$file: no month");
	}
}

// Every literal has its Turkish; pick-up reads "teslim", a return "iade",
// an extra "ek hizmet", a deal "kampanya".
function test_carrental_bookings_turkish_words()
{
	$map = pg_test_language_map('tr');
	$words = array('/\b[Pp]ick-up\b/' => '/teslim/iu', '/\b[Rr]eturn (day|desk|time)\b/' => '/(İ|i)ade/u', '/\bExtras\b/' => '/ek hizmet/iu', '/\bDeal\b/' => '/kampanya/iu');
	foreach (array('mail.php', 'bookings.php') as $file) {
		$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-carrental/' . $file);
		preg_match_all("/lang\\('((?:[^'\\\\]|\\\\.)*)'\\)/", $src, $m);
		foreach (array_unique($m[1]) as $raw) {
			$key = stripslashes($raw);
			pg_assert_true(isset($map[$key]) && $map[$key] !== '', "$file: Turkish for '$key'");
			if (!isset($map[$key])) continue;
			foreach ($words as $re => $tr) {
				if (preg_match($re, $key)) pg_assert_true((bool)preg_match($tr, $map[$key]), "'$key' says $tr");
			}
			pg_assert_false(in_array($key, array('Bookings', 'Booking', 'Contact', 'Deposit', 'Doors', 'Available', 'Time', 'Kind'), true), "$file: '$key' means something else in Turkish");
		}
	}
}
