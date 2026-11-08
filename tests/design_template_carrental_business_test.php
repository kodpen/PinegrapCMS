<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Car Rental design template's business and long-term
 * rentals (includes/design_templates/pinegrap-carrental/business.php): the
 * pages and widgets with their keys and folders, the business page showing
 * every plan with the link that opens the enquiry with it chosen (an
 * answer of the Rental Plan list), the enquiry (the plan from ?plan=, the
 * fleet size and term as bands, no tax number or ID, the start from today
 * on, the staff's status, consent with the privacy link, the CAPTCHA, an
 * account for a new sender and both e-mails), the enquiry's page and lists
 * reading only the form's fields, every page link naming a page of the
 * finished template, the template's ids and classes, no fixed date, and
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

// The page keys of the finished template and the tree helpers.
require_once __DIR__ . '/design_template_carrental_test.php';
require_once __DIR__ . '/design_template_carrental_bookings_test.php';

// parts.php, mail.php, bookings.php and business.php in one scope, as the
// template file reads them. Read once per run.
function _test_crbe_load()
{
	static $out = null;
	if ($out !== null) return $out;
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-carrental';
	include $dir . '/parts.php';
	include $dir . '/mail.php';
	include $dir . '/bookings.php';
	$part = include $dir . '/business.php';
	$pages = array();
	foreach ($part['pages'] as $p) $pages[$p['key']] = $p;
	$out = array('part' => $part, 'pages' => $pages, 'widgets' => $part['widgets'], 'business_plans' => $business_plans,
		'plan_choices' => $plan_choices, 'fleet_size_choices' => $fleet_size_choices, 'rental_term_choices' => $rental_term_choices,
		'class_titles' => $class_titles, 'location_titles' => $location_titles, 'enquiry_statuses' => $enquiry_statuses,
		'parts_links' => array($footer(), $header()));
	return $out;
}

function test_carrental_business_pages_and_widgets()
{
	$d = _test_crbe_load();
	pg_assert_same(array('pages', 'widgets'), array_keys($d['part']), 'the part returns pages and widgets');
	pg_assert_same(array('business', 'business_enquiry', 'enquiry_view', 'my_enquiries', 'staff_enquiries',
		'email_enquiry_new', 'email_enquiry_received'), array_keys($d['pages']));
	pg_assert_same(array('enquiry_form', 'enquiry_detail', 'enquiry_messages', 'my_enquiries_list', 'staff_enquiries_list',
		'email_enquiry_admin', 'email_enquiry_client'), array_keys($d['widgets']));
	$folders = array('business' => 'public', 'business_enquiry' => 'public', 'enquiry_view' => 'registration', 'my_enquiries' => 'registration',
		'staff_enquiries' => 'private', 'email_enquiry_new' => 'private', 'email_enquiry_received' => 'private');
	$names = array();
	foreach ($d['pages'] as $key => $p) {
		pg_assert_same($folders[$key], $p['folder'], "$key: its folder");
		foreach (array('name', 'title', 'tree') as $f) pg_assert_true(!empty($p[$f]), "$key has $f");
		pg_assert_false(isset($p['requires']), "$key stands without the shop");
		pg_assert_same('root', $p['tree']['type'], "$key: a page tree");
		pg_assert_true((bool)preg_match('/^[a-z-]+$/', $p['name']), "$key: the page name is an address");
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
}

// The business page shows every plan with a link that opens the enquiry
// with the plan chosen; the site's other links reach the business page.
function test_carrental_business_page_shows_the_plans()
{
	$d = _test_crbe_load();
	$json = json_encode($d['pages']['business']['tree']);
	foreach ($d['business_plans'] as $key => $p) {
		pg_assert_contains(json_encode($p['title']), $json, "plan $key is shown");
		$href = '{{page:business_enquiry}}?' . http_build_query(array('plan' => $p['title']), '', '&', PHP_QUERY_RFC3986);
		pg_assert_contains(json_encode($href), $json, "plan $key opens the enquiry with the plan chosen");
		pg_assert_true(in_array($p['title'], $d['plan_choices'], true), "plan $key is an answer of the Rental Plan list");
	}
	pg_assert_contains('{{page:business}}', json_encode($d['parts_links']), 'the footer or header leads to the business page');
}

// The enquiry: its fields, the plan from a link, bands for the size and
// the term, the start from today on, the staff's status, consent with the
// privacy link, the CAPTCHA, an account for a new sender and both e-mails.
function test_carrental_business_enquiry_form()
{
	$d = _test_crbe_load();
	$form = $d['pages']['business_enquiry']['form'];
	pg_assert_same(lang('Business Enquiries'), $form['form_name']);
	pg_assert_same(1, $form['auto_registration'], 'a visitor gets an account');
	pg_assert_same('{{tab:enquiry_view}}', $form['confirmation_page_id'], 'the enquiry opens its own page');
	pg_assert_same('{{tab:email_enquiry_new}}', $form['notify_page_id'], 'the company is e-mailed');
	pg_assert_same('{{tab:email_enquiry_received}}', $form['confirm_page_id'], 'the sender is e-mailed');
	pg_assert_same('{{site_email}}', $form['notify_email']);
	$c = _test_crb_controls($d['widgets']['enquiry_form']['tree']);
	pg_assert_same(array('plan', 'fleet_size', 'rental_term', 'start_date', 'location', 'car_classes', 'needs', 'company',
		'first_name', 'last_name', 'email', 'phone', 'contact_method', 'status', 'consent'), array_keys($c), 'the enquiry\'s fields');
	preg_match_all('/\^\^([a-z_]+)\^\^/', $form['notify_subject'], $m);
	foreach ($m[1] as $f) pg_assert_true(isset($c[$f]), "the notice subject reads $f, a field");
	pg_assert_same('plan', $c['plan']['cf']['url_param'] ?? '', 'the plan starts from ?plan=');
	pg_assert_same($d['plan_choices'], $c['plan']['options'], 'the plans');
	pg_assert_same($d['fleet_size_choices'], $c['fleet_size']['options'], 'the fleet sizes');
	pg_assert_same($d['rental_term_choices'], $c['rental_term']['options'], 'the terms');
	pg_assert_same($d['location_titles'], $c['location']['options'], 'the desks');
	pg_assert_same($d['class_titles'], $c['car_classes']['options'], 'the classes, as boxes');
	pg_assert_same('checkbox', $c['car_classes']['type']);
	pg_assert_same('today', $c['start_date']['cf']['rules']['min_date'] ?? '', 'no start in the past');
	foreach (array('plan', 'fleet_size', 'rental_term', 'start_date', 'company', 'first_name', 'last_name', 'email', 'phone', 'consent') as $f) {
		pg_assert_true($c[$f]['required'], "$f is required");
	}
	foreach (array('location', 'needs') as $f) pg_assert_false($c[$f]['required'], "$f is optional");
	foreach (array_keys($c) as $f) {
		pg_assert_false((bool)preg_match('/tax|vat|vkn|tckn|identity|national_id|licen[cs]e/', $f), "$f: no tax number or ID is asked");
	}
	pg_assert_true(!empty($c['status']['cf']['office_use_only']), 'the status is the staff\'s');
	pg_assert_same($d['enquiry_statuses'], $c['status']['options'], 'the statuses');
	foreach (array('company' => 'company', 'first_name' => 'first_name', 'last_name' => 'last_name', 'email' => 'email_address', 'phone' => 'mobile_phone') as $name => $field) {
		pg_assert_same($field, $c[$name]['cf']['contact_field'] ?? '', "$name fills the contact");
	}
	$json = json_encode($d['widgets']['enquiry_form']['tree']);
	pg_assert_contains('{{page:privacy}}', $json, 'the consent links the privacy page');
	pg_assert_contains('"section":"captcha"', $json, 'a CAPTCHA');
	$own = array('form_item_view', 'not_found', 'reference_code', 'number_of_comments', '__edit_url', 'can_edit', '__new_account_email',
		'__new_account_set_password_url', 'has_set_password_link', 'has_new_account', 'record_found', 'record_not_found', 'submitted_this_session');
	foreach (array('enquiry_detail', 'my_enquiries_list', 'staff_enquiries_list', 'email_enquiry_admin', 'email_enquiry_client') as $key) {
		foreach (_test_crb_tokens($d['widgets'][$key]['tree']) as $t) {
			pg_assert_true(isset($c[$t]) || in_array($t, $own, true), "$key reads $t, a field of the enquiry");
		}
		pg_assert_same('{{tab:business_enquiry}}', $d['widgets'][$key]['config']['custom_form_page_id'], "$key reads the enquiries");
	}
	foreach ($d['widgets']['staff_enquiries_list']['config']['search_fields'] as $f) {
		pg_assert_true(isset($c[$f]), "the staff search reads $f, a field");
	}
}

// The enquiry page is its sender's (and the staff's); My Business
// Enquiries lists the viewer's own, the staff's list everybody's; the
// messages carry a file (the quote) and go by e-mail.
function test_carrental_business_enquiry_pages()
{
	$d = _test_crbe_load();
	$w = $d['widgets'];
	pg_assert_same('submitter_only', $w['enquiry_detail']['config']['access_control'], 'only its sender and the staff open an enquiry');
	foreach (array('email_enquiry_admin', 'email_enquiry_client') as $key) {
		pg_assert_same('public', $w[$key]['config']['access_control'], "$key is drawn for the submission just sent");
	}
	pg_assert_true(!empty($w['my_enquiries_list']['config']['viewer_filter_submitter']), 'My Business Enquiries lists the viewer\'s own');
	pg_assert_false(isset($w['staff_enquiries_list']['config']['viewer_filter']), 'the staff list is everybody\'s');
	pg_assert_true(!empty($w['staff_enquiries_list']['config']['search_enabled']), 'the staff list has a search');
	foreach (array('my_enquiries_list', 'staff_enquiries_list') as $key) {
		pg_assert_same('{{tab:enquiry_view}}', $w[$key]['config']['detail_page_id'], "$key opens the enquiry");
	}
	$c = $d['pages']['enquiry_view']['comments'];
	pg_assert_true($c['allow_new'] && $c['login'] && $c['attachments'], 'signed-in messages with a file');
	pg_assert_same('{{tab:email_reply}}', $c['email_page'], 'messages go by e-mail');
	pg_assert_same('comments', $w['enquiry_messages']['config']['regionType']);
	$json = json_encode($w['enquiry_detail']['tree']);
	foreach (array('record_found', 'record_not_found', 'has_new_account', 'submitted_this_session') as $flag) {
		pg_assert_contains('"eo_visible_if":"' . $flag . '"', $json, "the enquiry page shows $flag");
	}
}

function test_carrental_business_links_ids_and_classes()
{
	$d = _test_crbe_load();
	$known = array_flip(_test_cr_page_keys());
	$trees = array();
	foreach ($d['pages'] as $key => $p) $trees[$key] = $p['tree'];
	foreach ($d['widgets'] as $key => $w) $trees['widget ' . $key] = $w['tree'];
	$json = json_encode(array($d['part']['pages'], $d['widgets']));
	preg_match_all('/\{\{(?:page|tab):([a-z0-9_]+)\}\}/', $json, $m);
	pg_assert_true(count(array_unique($m[1])) > 6, 'links found');
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
			$id = (string)($n['props']['id'] ?? '');
			if ($id === '' || strpos($id, '-') === false) continue;
			if (strpos($id, '-help') === strlen($id) - 5) continue;
			pg_assert_true(strpos($id, 'cr-') === 0, "$key: id $id is the template's");
			pg_assert_true(!isset($ids[$id]), "$key: id $id used once");
			$ids[$id] = true;
		}
	}
	$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-carrental/business.php');
	pg_assert_false((bool)preg_match('/#[0-9a-f]{6}\b/i', $src), 'business.php: its e-mails take their colours from mail.php');
}

// No year, month or fixed day in the source.
function test_carrental_business_no_fixed_dates()
{
	$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-carrental/business.php');
	$src = preg_replace('/@copyright[^\n]*/', '', $src);
	pg_assert_false((bool)preg_match('/\b(19|20)[0-9]{2}\b/', $src), 'business.php: no year');
	pg_assert_false((bool)preg_match('/\b[0-9]{4}-[0-9]{2}-[0-9]{2}\b/', $src), 'business.php: no fixed day');
	pg_assert_false((bool)preg_match('/\b(January|February|March|April|June|July|August|September|October|November|December)\b/', $src), 'business.php: no month');
}

// Every literal has its Turkish; an enquiry reads "talep", a business
// rental "kurumsal", a quote "teklif".
function test_carrental_business_turkish_words()
{
	$map = pg_test_language_map('tr');
	$words = array('/\b[Ee]nquir(y|ies)\b/' => '/tale[pb]/iu', '/^Business\b|\bbusiness rental/' => '/kurumsal/iu', '/\bquote\b/' => '/teklif/iu');
	$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-carrental/business.php');
	preg_match_all("/lang\\('((?:[^'\\\\]|\\\\.)*)'\\)/", $src, $m);
	foreach (array_unique($m[1]) as $raw) {
		$key = stripslashes($raw);
		pg_assert_true(isset($map[$key]) && $map[$key] !== '', "Turkish for '$key'");
		if (!isset($map[$key])) continue;
		foreach ($words as $re => $tr) {
			if (preg_match($re, $key)) pg_assert_true((bool)preg_match($tr, $map[$key]), "'$key' says $tr");
		}
		pg_assert_false(in_array($key, array('Contact', 'Bookings', 'Deposit', 'Doors', 'Available', 'Return', 'Deal', 'Desk', 'What you need'), true),
			"'$key' means something else in Turkish");
	}
}
