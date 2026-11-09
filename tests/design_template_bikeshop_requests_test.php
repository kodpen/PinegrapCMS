<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Bike Shop design template's requests
 * (includes/design_templates/pinegrap-bikeshop/mail.php and requests.php):
 * the pages and widgets of the plan, each widget on its page, the service
 * and fit request forms taking the job, bike kind and package from the
 * address in the lists' own words with the status (and the quote)
 * office-only and the dates not before today, every card opening them
 * with answers of those lists, the request pages, lists and e-mails
 * reading their form, the booking page beside the fit request, the reply
 * e-mail, the e-mail look, the template's ids and classes, no fixed dates
 * and every literal in Turkish.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(__DIR__ . '/design_template_bikeshop_test.php');

// parts.php, cards.php, the public pages, mail.php and requests.php in one
// scope, as the template file reads them. Read once per run.
function _test_bikeshopr_load()
{
	static $out = null;
	if ($out !== null) return $out;
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-bikeshop';
	include $dir . '/parts.php';
	include $dir . '/cards.php';
	$public = include $dir . '/pages_public.php';
	include $dir . '/mail.php';
	$part = include $dir . '/requests.php';
	$pages = array();
	foreach ($part['pages'] as $p) $pages[$p['key']] = $p;
	$dates = array();
	for ($wd = 1; $wd <= 7; $wd++) $dates[] = $ride_on($wd);
	$out = array('part' => $part, 'public' => $public, 'pages' => $pages, 'widgets' => $part['widgets'],
		'lists' => array('job' => $job_choices, 'bike' => $bike_titles, 'fit' => $fit_titles),
		'riding_styles' => $riding_styles, 'service_statuses' => $service_statuses, 'fit_statuses' => $fit_statuses,
		'shared' => array('header' => $header(), 'footer' => $footer()),
		'dates' => $dates, 'email_header' => $email_header_tree(), 'email_footer' => $email_footer_tree());
	return $out;
}

function _test_bikeshopr_nodes($tree)
{
	$nodes = array();
	_test_bikeshop_nodes($tree, $nodes);
	return $nodes;
}

// The name, options and _cf of every form control of a tree.
function _test_bikeshopr_controls($tree)
{
	$out = array();
	foreach (_test_bikeshopr_nodes($tree) as $n) {
		$tag = $n['props']['tag'] ?? '';
		if (!in_array($tag, array('input', 'select', 'textarea'), true)) continue;
		$name = '';
		foreach ((array)($n['props']['_attrs'] ?? array()) as $a) {
			if ($a['name'] === 'name') $name = $a['value'];
		}
		$options = array();
		foreach ((array)($n['children'] ?? array()) as $o) {
			foreach ((array)($o['props']['_attrs'] ?? array()) as $a) {
				if ($a['name'] === 'value' && $a['value'] !== '') $options[] = $a['value'];
			}
		}
		if (isset($out[$name])) continue;
		$out[$name] = array('tag' => $tag, 'options' => $options, 'cf' => $n['props']['_cf'] ?? array());
	}
	return $out;
}

// The page keys this part carries: the plan's pages and the request e-mails.
function _test_bikeshopr_keys()
{
	return array_merge(_test_bikeshop_page_keys(), array('email_service_new', 'email_service_received', 'email_fit_new', 'email_fit_received'));
}

function test_bikeshop_requests_pages_and_widgets()
{
	$d = _test_bikeshopr_load();
	pg_assert_same(array('pages', 'widgets'), array_keys($d['part']), 'the part returns pages and widgets');
	pg_assert_same(array('service_request', 'service_view', 'my_services', 'staff_services',
		'fit_request', 'fit_view', 'my_fits', 'staff_fits', 'book_online', 'privacy',
		'email_service_new', 'email_service_received', 'email_fit_new', 'email_fit_received', 'email_reply'), array_keys($d['pages']));
	pg_assert_same(array('service_form', 'service_detail', 'service_messages', 'my_services_list', 'staff_services_list',
		'fit_form', 'fit_detail', 'fit_messages', 'my_fits_list', 'staff_fits_list', 'booking_slots',
		'email_service_admin', 'email_service_client', 'email_fit_admin', 'email_fit_client', 'email_comment'), array_keys($d['widgets']));
	$known = array_flip(_test_bikeshopr_keys());
	$folders = array('service_request' => 'public', 'fit_request' => 'public', 'book_online' => 'public', 'privacy' => 'public',
		'service_view' => 'registration', 'my_services' => 'registration', 'fit_view' => 'registration', 'my_fits' => 'registration');
	foreach ($d['pages'] as $key => $p) {
		pg_assert_true(isset($known[$key]), "$key is a planned page");
		pg_assert_same($folders[$key] ?? 'private', $p['folder'], "$key: its folder");
		pg_assert_false(isset($p['requires']), "$key stands without the shop");
		pg_assert_same('root', $p['tree']['type'], "$key: a page tree");
		pg_assert_true(mb_strlen((string)($p['meta_description'] ?? ''), 'UTF-8') <= 160, "$key: meta description fits a result line");
		pg_assert_true((bool)preg_match('/^[a-z0-9-]+$/', $p['name']), "$key: the page name is an address");
		if (strpos($key, 'email_') === 0) {
			pg_assert_true(!empty($p['noindex']) && $p['search'] === false && $p['sitemap'] === false, "$key: an e-mail page is out of the search");
		}
	}
	pg_assert_same(lang('privacy-policy'), $d['pages']['privacy']['name'], 'the booking widget finds the privacy page');
	foreach ($d['widgets'] as $key => $w) {
		pg_assert_contains('"templateWidget":"' . $key . '"', json_encode($d['pages'][$w['page']]['tree']), "$key is placed on its page");
	}
	$all = json_encode($d['part']);
	preg_match_all('/\{\{(?:page|tab):([a-z0-9_]+)\}\}/', $all, $m);
	foreach (array_unique($m[1]) as $key) pg_assert_true(isset($known[$key]), "link {{page:$key}} names a template page");
	pg_assert_false((bool)preg_match('/\{\{(?:page|tab):[a-z0-9_]*[^a-z0-9_}]/', $all), 'every page link is closed');
	foreach (_test_bikeshop_shop_keys() as $key) pg_assert_false(strpos($all, '{{page:' . $key . '}}') !== false, "no link to $key");
	// The e-mail frame places the shared header and footer, drawn by mail.php
	// in the graphite and signal-orange look, written for mail programs.
	pg_assert_contains('"templateShared":"email_header"', json_encode($d['pages']['email_service_new']['tree']), 'the e-mail header is shared');
	pg_assert_contains('"templateShared":"email_footer"', json_encode($d['pages']['email_fit_received']['tree']), 'the e-mail footer is shared');
	$head = json_encode($d['email_header']);
	pg_assert_contains('{{site_name}}', $head, 'the e-mail header names the site');
	pg_assert_contains('#1f2937', $head, 'the e-mail header is graphite for mail programs');
	pg_assert_contains('#c2410c', $head, 'the e-mail header keeps the orange rule for mail programs');
	pg_assert_contains('{{page:home}}', json_encode($d['email_footer']), 'the e-mail footer links the site');
}

// A repair and a fit are requests: each form opens the request's page,
// mails both sides and opens an account; it starts from the address in the
// lists' own words; the status (and the repair's quote) is the shop's.
function test_bikeshop_requests_forms_are_requests()
{
	$d = _test_bikeshopr_load();
	$forms = array(
		'service' => array('page' => 'service_request', 'widget' => 'service_form', 'subject' => '^^job^^',
			'fields' => array('job', 'bike_kind', 'make_model', 'dropoff_date', 'notes', 'first_name', 'last_name', 'email', 'phone', 'reply_by', 'status', 'quote', 'consent'),
			'params' => array('job' => 'job', 'bike_kind' => 'bike'), 'date' => 'dropoff_date', 'statuses' => $d['service_statuses']),
		'fit' => array('page' => 'fit_request', 'widget' => 'fit_form', 'subject' => '^^fit^^',
			'fields' => array('fit', 'height', 'inseam', 'riding_style', 'preferred_date', 'notes', 'first_name', 'last_name', 'email', 'phone', 'reply_by', 'status', 'consent'),
			'params' => array('fit' => 'fit'), 'date' => 'preferred_date', 'statuses' => $d['fit_statuses']),
	);
	foreach ($forms as $kind => $f) {
		$form = $d['pages'][$f['page']]['form'];
		pg_assert_false(isset($form['collection']), "$kind: a request, not a collection");
		pg_assert_same('{{tab:' . $kind . '_view}}', $form['confirmation_page_id'], "$kind: what is sent opens its page");
		pg_assert_same('{{tab:email_' . $kind . '_new}}', $form['notify_page_id'], "$kind: the shop is mailed");
		pg_assert_same('{{tab:email_' . $kind . '_received}}', $form['confirm_page_id'], "$kind: the rider is mailed");
		pg_assert_same(1, $form['auto_registration'], "$kind: a rider gets an account");
		pg_assert_contains($f['subject'], $form['notify_subject'], "$kind: the subject names the request");
		$tree = $d['widgets'][$f['widget']]['tree'];
		$c = _test_bikeshopr_controls($tree);
		pg_assert_same($f['fields'], array_keys($c), "$kind: the request's fields");
		foreach ($f['params'] as $field => $param) {
			pg_assert_same($param, $c[$field]['cf']['url_param'] ?? '', "$kind: $field starts from ?$param=");
			pg_assert_same($d['lists'][$param], $c[$field]['options'], "$kind: $field offers its list");
		}
		pg_assert_same('today', $c[$f['date']]['cf']['rules']['min_date'] ?? '', "$kind: the date is not before today");
		pg_assert_same($f['statuses'], $c['status']['options'], "$kind: the status offers its list");
		pg_assert_true(!empty($c['status']['cf']['office_use_only']), "$kind: the status is the shop's");
		pg_assert_contains('{{page:privacy}}', json_encode($tree), "$kind: the consent links the privacy page");
		pg_assert_contains('"section":"captcha"', json_encode($tree), "$kind: the form has a CAPTCHA");
	}
	$c = _test_bikeshopr_controls($d['widgets']['fit_form']['tree']);
	pg_assert_same($d['riding_styles'], $c['riding_style']['options'], 'the riding style offers its list');
	$c = _test_bikeshopr_controls($d['widgets']['service_form']['tree']);
	pg_assert_true(!empty($c['quote']['cf']['office_use_only']), 'the quote is the workshop\'s');
	$words = strtolower(json_encode(array($d['widgets']['service_form'], $d['widgets']['fit_form'], $d['widgets']['service_detail'], $d['widgets']['fit_detail'])));
	pg_assert_false(strpos($words, 'book now') !== false || strpos($words, 'booking confirmed') !== false, 'a request is never a booking');
}

// Every link that opens a request (the workshop's job cards and bike kinds,
// the fit packages, the footer) uses answers of the request's lists.
function test_bikeshop_requests_links_use_the_lists_words()
{
	$d = _test_bikeshopr_load();
	$trees = $d['shared'];
	foreach ($d['public']['pages'] as $p) $trees[$p['key']] = $p['tree'];
	foreach ($d['pages'] as $key => $p) $trees[$key] = $p['tree'];
	$seen = array();
	foreach ($trees as $key => $tree) {
		foreach (_test_bikeshopr_nodes($tree) as $n) {
			$href = (string)($n['props']['href'] ?? '');
			if (!preg_match('/^\{\{page:(service_request|fit_request)\}\}\?(.*)$/', $href, $m)) continue;
			parse_str($m[2], $q);
			foreach ($q as $name => $value) {
				pg_assert_true(isset($d['lists'][$name]) && in_array($value, $d['lists'][$name], true), "$key: $name '$value' is an answer");
				$seen[$name] = true;
			}
		}
	}
	foreach (array('job', 'bike', 'fit') as $param) pg_assert_true(isset($seen[$param]), "a link sends ?$param=");
}

// The request pages read the record (the sender's own), their messages are
// the page's comments mailed with the reply e-mail; the lists read the
// form, the rider's own filtered to the viewer, the staff's searchable.
function test_bikeshop_requests_views_lists_and_mails_read_the_form()
{
	$d = _test_bikeshopr_load();
	$w = $d['widgets'];
	foreach (array('service' => 'my_services', 'fit' => 'my_fits') as $kind => $mine) {
		$form_key = $kind . '_request';
		$view = $kind . '_view';
		pg_assert_same('submitter_only', $w[$kind . '_detail']['config']['access_control'], "$kind: the request is the sender's");
		pg_assert_same('{{tab:' . $form_key . '}}', $w[$kind . '_detail']['config']['custom_form_page_id'], "$kind: of its form");
		pg_assert_same('{{tab:email_reply}}', $d['pages'][$view]['comments']['email_page'], "$kind: messages are mailed");
		pg_assert_same('comments', $w[$kind . '_messages']['config']['regionType'], "$kind: the messages are comments");
		pg_assert_same($view, $w[$kind . '_messages']['page'], "$kind: the messages sit on the request's page");
		pg_assert_true(!empty($w[$mine . '_list']['config']['viewer_filter_submitter']), "$mine shows the rider's own");
		pg_assert_true(!empty($w['staff_' . substr($mine, 3) . '_list']['config']['search_enabled']), "$kind: the staff list is searchable");
		$fields = array_keys(_test_bikeshopr_controls($w[$kind . '_form']['tree']));
		foreach (array($kind . '_detail', $mine . '_list', 'staff_' . substr($mine, 3) . '_list', 'email_' . $kind . '_admin', 'email_' . $kind . '_client') as $key) {
			pg_assert_same('{{tab:' . $form_key . '}}', $w[$key]['config']['custom_form_page_id'], "$key reads $form_key");
			foreach (_test_bikeshopr_nodes($w[$key]['tree']) as $n) {
				$token = $n['props']['_bindings']['text'] ?? '';
				if ($token === '' || strpos($token, '__') === 0 || in_array($token, array('reference_code', 'not_found', 'number_of_comments'), true)) continue;
				pg_assert_true(in_array($token, $fields, true), "$key: $token is a field of the form");
			}
			if (substr($key, -5) === '_list') {
				pg_assert_same('{{tab:' . $view . '}}', $w[$key]['config']['detail_page_id'], "$key opens the request");
			} else {
				pg_assert_same('form_item_view', $w[$key]['config']['regionType'], "$key reads one record");
			}
		}
		foreach (array('email_' . $kind . '_admin', 'email_' . $kind . '_client') as $key) {
			pg_assert_same('public', $w[$key]['config']['access_control'], "$key is drawn for the submission just sent");
			pg_assert_contains('{{page:' . $view . '}}?r=^^reference_code^^', json_encode($w[$key]['tree'], JSON_UNESCAPED_SLASHES), "$key links the request");
		}
	}
	pg_assert_contains('"quote"', json_encode($w['service_detail']['tree']), 'the service request shows the quote');
	pg_assert_contains('"regionType":"system"', json_encode($d['pages']['email_reply']['tree']), 'the reply e-mail keeps the System region');
	pg_assert_same('email_reply', $w['email_comment']['page'], 'the reply e-mail draws the comment');
}

// The booking page places the booking widget in its starter layout and
// links the fit request, which links it back.
function test_bikeshop_requests_book_online()
{
	$d = _test_bikeshopr_load();
	$w = $d['widgets']['booking_slots'];
	pg_assert_same('book_online', $w['page'], 'the widget is the booking page\'s');
	pg_assert_same('booking', $w['config']['regionType'], 'a booking widget');
	pg_assert_same('starter', $w['tree'], 'the editor\'s starter layout');
	pg_assert_true(empty($d['pages']['book_online']['form']), 'the booking page is not a form page');
	pg_assert_contains('{{page:fit_request}}', json_encode($d['pages']['book_online']['tree']), 'the booking page links the fit request');
	pg_assert_contains('{{page:book_online}}', json_encode($d['pages']['fit_request']['tree']), 'the fit request links the booking page');
}

function test_bikeshop_requests_ids_classes_and_headings()
{
	$d = _test_bikeshopr_load();
	$ids = array();
	$trees = array();
	foreach ($d['pages'] as $key => $p) $trees[$key] = $p['tree'];
	foreach ($d['widgets'] as $key => $w) {
		if (is_array($w['tree'])) $trees['widget ' . $key] = $w['tree'];
	}
	foreach ($trees as $key => $tree) {
		foreach (_test_bikeshopr_nodes($tree) as $n) {
			foreach (preg_split('/\s+/', (string)($n['props']['cssClass'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $c) {
				pg_assert_false((bool)preg_match('/^(psk|pzo|pgl|pmz|pph|pvt|pre|pev|pcw|sk)-/', $c), "$key: class $c is not another template's");
				pg_assert_false((bool)preg_match('/^[pm][trblxy]?(-[a-z]{2,3})?-[6-9]$/', $c), "$key: class $c exists in Bootstrap 5.3");
				pg_assert_false(strpos($c, 'opacity-') === 0, "$key: no faded text ($c)");
			}
			$id = (string)($n['props']['id'] ?? '');
			if ($id === '') continue;
			pg_assert_true(strpos($id, 'bikeshop-') === 0 || strpos($id, 'comment_') === 0, "$key: id $id is the template's");
			if (strpos($id, 'comment_') === 0) continue;
			pg_assert_false(isset($ids[$id]), "id $id once");
			$ids[$id] = true;
		}
	}
	foreach (array('service_request', 'fit_request', 'my_services', 'my_fits', 'book_online', 'privacy') as $key) {
		$levels = array();
		foreach (_test_bikeshopr_nodes($d['pages'][$key]['tree']) as $n) {
			if (preg_match('/^h([1-6])$/', (string)($n['props']['tag'] ?? ''), $m)) $levels[] = (int)$m[1];
		}
		$set = array_values(array_unique($levels));
		sort($set);
		pg_assert_same(range(1, max($set)), $set, "$key: no heading level skipped");
	}
}

// The only dates are the samples' counted from today.
function test_bikeshop_requests_no_fixed_dates()
{
	$d = _test_bikeshopr_load();
	$json = rawurldecode(json_encode($d['part'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
	$json = str_replace($d['dates'], '', $json);
	// The comment stars are SVG pictures: their namespace address is no date.
	$json = str_replace('http://www.w3.org/2000/svg', '', $json);
	pg_assert_false((bool)preg_match('/\b(19|20)[0-9]{2}\b/', $json), 'no year in the sample');
	pg_assert_false((bool)preg_match('/\b(January|February|March|April|June|July|August|September|October|November|December)\b/', $json), 'no month in the sample');
}

function test_bikeshop_requests_turkish_words()
{
	$map = pg_test_language_map('tr');
	foreach (array('mail.php', 'requests.php') as $file) {
		$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-bikeshop/' . $file);
		preg_match_all("/lang\\('((?:[^'\\\\]|\\\\.)*)'\\)/", $src, $m);
		foreach (array_unique($m[1]) as $raw) {
			$key = stripslashes($raw);
			pg_assert_true(isset($map[$key]) && $map[$key] !== '', "$file: Turkish for '$key'");
			if (!isset($map[$key])) continue;
			// The shared list words of other templates say "bakım talebi".
			if (preg_match('/\bservice requests?\b/i', $key)) pg_assert_true(mb_stripos($map[$key], 'servis tale', 0, 'UTF-8') !== false || mb_stripos($map[$key], 'bakım tale', 0, 'UTF-8') !== false, "'$key' says servis talebi");
			if (preg_match('/\bworkshop\b/i', $key)) pg_assert_true(mb_stripos($map[$key], 'atölye', 0, 'UTF-8') !== false, "'$key' says atölye");
		}
	}
}
