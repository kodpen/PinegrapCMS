<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Coworking design template's requests and e-mails
 * (includes/design_templates/pinegrap-coworking/mail.php and
 * requests.php): the pages in their folders, the privacy page named as the
 * booking widget expects, both forms' fields and settings, the fields a
 * link fills (?plan=, ?space=) answering the values the other parts send,
 * the record pages, lists and e-mails reading only fields their form has,
 * links to template pages, the template's ids and classes, and every
 * literal in Turkish.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// parts.php, mail.php, the public pages and the request part in one scope,
// as the template file reads them. Read once per run.
function _test_cwr_load()
{
	static $out = null;
	if ($out !== null) return $out;
	include PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-coworking/parts.php';
	include PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-coworking/mail.php';
	$public_part = include PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-coworking/pages_public.php';
	$space_part = include PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-coworking/spaces.php';
	$request_part = include PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-coworking/requests.php';
	$plan_cards = array();
	foreach (array_keys($plans) as $k) $plan_cards[] = $plan_card($k);
	$out = array('part' => $request_part, 'links_json' => json_encode(array($header(), $footer(), $tour_band(), $notice(),
		$plan_cards, $public_part, $space_part)));
	return $out;
}

// Every node of a tree, depth first.
function _test_cwr_nodes($node, &$out)
{
	if (!is_array($node)) return;
	if (isset($node['props'])) $out[] = $node;
	if (!empty($node['children']) && is_array($node['children'])) {
		foreach ($node['children'] as $child) _test_cwr_nodes($child, $out);
	}
}

function _test_cwr_pages()
{
	$pages = array();
	foreach (_test_cwr_load()['part']['pages'] as $p) $pages[$p['key']] = $p;
	return $pages;
}

// The fields of a form widget: name => the node (its _cf and options).
function _test_cwr_form_fields($widget_key)
{
	$nodes = array();
	_test_cwr_nodes(_test_cwr_load()['part']['widgets'][$widget_key]['tree'], $nodes);
	$fields = array();
	foreach ($nodes as $n) {
		if (!in_array($n['props']['tag'] ?? '', array('input', 'select', 'textarea'), true)) continue;
		foreach ($n['props']['_attrs'] ?? array() as $a) {
			if ($a['name'] === 'name' && !isset($fields[$a['value']])) $fields[$a['value']] = $n;
		}
	}
	return $fields;
}

// The answers of a pick list node.
function _test_cwr_options($node)
{
	$values = array();
	foreach ($node['children'] as $o) {
		foreach ($o['props']['_attrs'] as $a) {
			if ($a['name'] === 'value' && $a['value'] !== '') $values[] = $a['value'];
		}
	}
	return $values;
}

function test_coworking_requests_pages_and_folders()
{
	$part = _test_cwr_load()['part'];
	pg_assert_same(array('pages', 'widgets'), array_keys($part), 'the part returns pages and widgets');
	$folders = array(
		'tour' => 'public', 'tour_view' => 'registration', 'my_tours' => 'registration', 'staff_tours' => 'private',
		'office_enquiry' => 'public', 'enquiry_view' => 'registration', 'staff_enquiries' => 'private', 'privacy' => 'public',
		'email_tour_new' => 'private', 'email_tour_received' => 'private', 'email_enquiry_new' => 'private',
		'email_enquiry_received' => 'private', 'email_reply' => 'private',
	);
	$pages = _test_cwr_pages();
	pg_assert_same(array_keys($folders), array_keys($pages), 'the pages');
	foreach ($folders as $key => $folder) {
		pg_assert_same($folder, $pages[$key]['folder'], "$key folder");
		pg_assert_false(isset($pages[$key]['requires']), "$key stands without the shop");
		pg_assert_true(mb_strlen($pages[$key]['meta_description'] ?? '', 'UTF-8') <= 160, "$key: meta description fits");
		if ($folder !== 'public') pg_assert_false($pages[$key]['sitemap'] ?? true, "$key: not in the sitemap");
	}
	pg_assert_same(lang('privacy-policy'), $pages['privacy']['name'], 'the privacy page has the name the booking widget looks for');
	foreach (array('tour_view', 'enquiry_view') as $key) {
		pg_assert_same('{{tab:email_reply}}', $pages[$key]['comments']['email_page'], "$key: messages are mailed");
	}
}

// Both forms: auto-registration, their own record page and e-mails, an
// office-only status starting with Request received, a consent box with
// the privacy link, a CAPTCHA.
function test_coworking_requests_forms()
{
	$pages = _test_cwr_pages();
	$cases = array(
		'tour' => array('tour_form', 'tour_view', 'email_tour_new', 'email_tour_received',
			array('plan', 'tour_date', 'tour_time', 'work', 'notes', 'first_name', 'last_name', 'email', 'phone', 'reply_by', 'status', 'consent')),
		'office_enquiry' => array('enquiry_form', 'enquiry_view', 'email_enquiry_new', 'email_enquiry_received',
			array('team_size', 'start', 'space', 'company', 'work', 'notes', 'first_name', 'last_name', 'email', 'phone', 'reply_by', 'status', 'consent')),
	);
	foreach ($cases as $key => $c) {
		$form = $pages[$key]['form'];
		pg_assert_same(1, $form['auto_registration'], "$key: an account for a new sender");
		pg_assert_same('{{tab:' . $c[1] . '}}', $form['confirmation_page_id'], "$key: opens its record");
		pg_assert_same('{{tab:' . $c[2] . '}}', $form['notify_page_id'], "$key: staff e-mail");
		pg_assert_same('{{tab:' . $c[3] . '}}', $form['confirm_page_id'], "$key: sender e-mail");
		$fields = _test_cwr_form_fields($c[0]);
		pg_assert_same($c[4], array_keys($fields), "$key: the fields");
		pg_assert_true(!empty($fields['status']['props']['_cf']['office_use_only']), "$key: status is the front desk's");
		preg_match_all('/\^\^([a-z_]+)\^\^/', $form['notify_subject'], $m);
		foreach ($m[1] as $token) pg_assert_true(isset($fields[$token]), "$key: subject reads $token, a field");
		$json = json_encode(_test_cwr_load()['part']['widgets'][$c[0]]['tree']);
		pg_assert_contains('{{page:privacy}}', $json, "$key: consent links the privacy page");
		pg_assert_contains('"section":"captcha"', $json, "$key: CAPTCHA");
	}
	$date = _test_cwr_form_fields('tour_form')['tour_date'];
	pg_assert_same('today', $date['props']['_cf']['rules']['min_date'], 'no tour in the past');
}

// The links of the other parts fill fields whose answers hold what they
// send; every office of the spaces is an answer of the enquiry's Office.
function test_coworking_requests_links_fill_the_forms()
{
	$params = array('tour' => array('plan' => 'tour_form'), 'office_enquiry' => array('space' => 'enquiry_form'));
	preg_match_all('/\{\{page:(tour|office_enquiry)\}\}\?([^"]+)/', str_replace('\\/', '/', _test_cwr_load()['links_json']), $m, PREG_SET_ORDER);
	pg_assert_true(count($m) >= 5, 'the other parts link the tour with a plan chosen');
	foreach ($m as $hit) {
		parse_str(html_entity_decode(json_decode('"' . $hit[2] . '"')), $q);
		foreach ($q as $name => $value) {
			pg_assert_true(isset($params[$hit[1]][$name]), "?$name is a field of $hit[1]");
			$fields = _test_cwr_form_fields($params[$hit[1]][$name]);
			pg_assert_same($name, $fields[$name]['props']['_cf']['url_param'] ?? '', "$name starts with ?$name");
			pg_assert_true(in_array($value, _test_cwr_options($fields[$name]), true), "'$value' is an answer of $name");
		}
	}
	$space = _test_cwr_form_fields('enquiry_form')['space'];
	pg_assert_same('space', $space['props']['_cf']['url_param'] ?? '', 'the office starts with ?space=');
	pg_assert_true(count(_test_cwr_options($space)) >= 2, 'the offices are the answers');
}

// Record pages, lists and e-mails read only fields of their form.
function test_coworking_requests_widgets_read_the_form()
{
	$w = _test_cwr_load()['part']['widgets'];
	$forms = array('{{tab:tour}}' => 'tour_form', '{{tab:office_enquiry}}' => 'enquiry_form');
	$system = array('form_item_view', 'not_found', '__edit_url', 'can_edit', 'record_found', 'record_not_found', 'section',
		'reference_code', 'number_of_comments', '__new_account_email', '__new_account_set_password_url', 'has_set_password_link');
	$checked = 0;
	foreach ($w as $key => $widget) {
		$form_page = $widget['config']['custom_form_page_id'] ?? '';
		if (!isset($forms[$form_page]) || $widget['config']['regionType'] === 'custom_form') continue;
		$checked++;
		$fields = _test_cwr_form_fields($forms[$form_page]);
		$nodes = array();
		_test_cwr_nodes($widget['tree'], $nodes);
		$read = 0;
		foreach ($nodes as $n) {
			foreach ($n['props']['_bindings'] ?? array() as $what => $token) {
				if ($what === 'eo_visible_if' || in_array($token, $system, true)) continue;
				pg_assert_true(isset($fields[$token]), "$key reads $token, a field of its form");
				$read++;
			}
		}
		pg_assert_true($read >= 3, "$key reads the request");
		foreach ($widget['config']['search_fields'] ?? array() as $s) pg_assert_true(isset($fields[$s]), "$key: search field $s");
	}
	pg_assert_same(9, $checked, 'every record page, list and e-mail of both forms');
	pg_assert_same('submitter_only', $w['tour_detail']['config']['access_control'], 'a tour request is its sender\'s');
	pg_assert_same('submitter_only', $w['enquiry_detail']['config']['access_control'], 'an enquiry is its sender\'s');
	pg_assert_true($w['my_tours_list']['config']['viewer_filter_submitter'], 'My Tours shows one\'s own');
	foreach ($w as $key => $widget) pg_assert_true(isset(_test_cwr_pages()[$widget['page']]), "$key sits on a page of the part");
}

// Every link names a template page and no shop page.
function test_coworking_requests_links()
{
	$json = json_encode(array(_test_cwr_pages(), _test_cwr_load()['part']['widgets']));
	preg_match_all('/\{\{(?:page|tab):([a-z0-9_]+)\}\}/', $json, $m);
	$known = array_flip(array('home', 'plans', 'amenities', 'about', 'faq', 'location',
		'spaces', 'space', 'space_new', 'book_online', 'tour', 'tour_view', 'office_enquiry', 'enquiry_view', 'privacy', 'contact',
		'my_account', 'staff', 'staff_tours', 'staff_enquiries', 'staff_signups', 'event_new', 'events', 'blog_new', 'inbox',
		'my_tours', 'blog', 'newsletter', 'forgot_password', 'email_tour_new', 'email_tour_received', 'email_enquiry_new',
		'email_enquiry_received', 'email_reply'));
	foreach (array_unique($m[1]) as $key) pg_assert_true(isset($known[$key]), "{{page:$key}} names a template page");
}

// Ids carry cw-, classes are Bootstrap's or the template's, no faded text.
function test_coworking_requests_ids_and_classes()
{
	$nodes = array();
	foreach (_test_cwr_pages() as $p) _test_cwr_nodes($p['tree'], $nodes);
	foreach (_test_cwr_load()['part']['widgets'] as $w) _test_cwr_nodes($w['tree'], $nodes);
	foreach ($nodes as $n) {
		foreach (preg_split('/\s+/', (string)($n['props']['cssClass'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $c) {
			pg_assert_false((bool)preg_match('/^(pgy|pre|pev|pf|pdp|pm|pma|plx|pdl|plw|psl|pco|pcl|pht)-/', $c), "class $c is not another template's");
			pg_assert_false(strpos($c, 'opacity-') === 0, "no faded text ($c)");
		}
		$id = (string)($n['props']['id'] ?? '');
		// The comment form's own ids are the Comments widget's.
		if ($id !== '' && strpos($id, 'comment_') !== 0) pg_assert_true(strpos($id, 'cw-') === 0, "id $id is the template's");
	}
}

// Every literal has its Turkish; a tour reads "tur".
function test_coworking_requests_turkish_words()
{
	$map = pg_test_language_map('tr');
	foreach (array('mail.php', 'requests.php') as $file) {
		$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-coworking/' . $file);
		preg_match_all("/lang\\('((?:[^'\\\\]|\\\\.)*)'\\)/", $src, $m);
		foreach (array_unique($m[1]) as $raw) {
			$key = stripslashes($raw);
			pg_assert_true(isset($map[$key]) && $map[$key] !== '', "Turkish for '$key'");
			if (!isset($map[$key])) continue;
			if (preg_match('/\b[Tt]ours?\b/', $key)) pg_assert_true(mb_stripos($map[$key], 'tur', 0, 'UTF-8') !== false, "'$key' says tur");
		}
	}
}
