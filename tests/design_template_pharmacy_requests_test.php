<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Pharmacy design template's e-mail builders and requests
 * (includes/design_templates/pinegrap-pharmacy/mail.php and requests.php):
 * the pages and their folders, the two forms with the pick lists of
 * parts.php and the values a link sends (?service=, ?pharmacist=, ?topic=),
 * the office-only status, the lists, record pages and e-mails that read
 * only the fields their form has, the e-mail pages wired to the forms, the
 * original prescription, booking and on-duty boxes beside the request,
 * links to template pages only, the template's ids and classes and every
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

// The page and shop keys of the finished template (_test_rx_page_keys(),
// _test_rx_shop_keys()) live in the parts test.
require_once __DIR__ . '/design_template_pharmacy_test.php';

// parts.php, mail.php and the requests part in one scope, as the template
// file reads them. Read once per run.
function _test_rxr_load()
{
	static $out = null;
	if ($out !== null) return $out;
	include PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-pharmacy/parts.php';
	include PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-pharmacy/mail.php';
	$part = include PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-pharmacy/requests.php';
	$out = array('part' => $part, 'pharmacy' => $pharmacy, 'request_choices' => $request_choices,
		'pharmacist_choices' => $pharmacist_choices, 'topic_choices' => $topic_choices, 'collect_choices' => $collect_choices,
		'prescription_statuses' => $prescription_statuses, 'question_statuses' => $question_statuses,
		'email_header' => $email_header_tree(), 'email_footer' => $email_footer_tree());
	return $out;
}

// Every node of a tree, depth first.
function _test_rxr_nodes($node, &$out)
{
	if (!is_array($node)) return;
	if (isset($node['props'])) $out[] = $node;
	if (!empty($node['children']) && is_array($node['children'])) {
		foreach ($node['children'] as $child) _test_rxr_nodes($child, $out);
	}
}

// The pages of this part, by key.
function _test_rxr_pages()
{
	$pages = array();
	foreach (_test_rxr_load()['part']['pages'] as $p) $pages[$p['key']] = $p;
	return $pages;
}

// The fields of a form widget by name: its tag, _cf settings and options.
function _test_rxr_form_fields($widget_key)
{
	$nodes = array();
	_test_rxr_nodes(_test_rxr_load()['part']['widgets'][$widget_key]['tree'], $nodes);
	$fields = array();
	foreach ($nodes as $n) {
		$tag = $n['props']['tag'] ?? '';
		if (!in_array($tag, array('input', 'select', 'textarea'), true)) continue;
		$name = '';
		foreach ($n['props']['_attrs'] ?? array() as $a) {
			if ($a['name'] === 'name') $name = $a['value'];
		}
		if ($name === '' || isset($fields[$name])) continue;
		$options = array();
		foreach ($n['children'] as $o) {
			foreach ($o['props']['_attrs'] ?? array() as $a) {
				if ($a['name'] === 'value' && $a['value'] !== '') $options[] = $a['value'];
			}
		}
		$fields[$name] = array('tag' => $tag, 'cf' => $n['props']['_cf'] ?? array(), 'options' => $options);
	}
	return $fields;
}

function test_pharmacy_requests_pages_and_folders()
{
	$part = _test_rxr_load()['part'];
	pg_assert_same(array('pages', 'widgets'), array_keys($part), 'the part returns pages and widgets');
	$pages = _test_rxr_pages();
	$want = array('prescription' => 'public', 'prescription_view' => 'registration', 'my_prescriptions' => 'registration',
		'staff_prescriptions' => 'private', 'ask' => 'public', 'ask_view' => 'registration', 'my_questions' => 'registration',
		'staff_questions' => 'private', 'privacy' => 'public', 'email_prescription_new' => 'private',
		'email_prescription_received' => 'private', 'email_question_new' => 'private', 'email_question_received' => 'private',
		'email_reply' => 'private');
	$keys = array_keys($pages);
	sort($keys);
	$expected = array_keys($want);
	sort($expected);
	pg_assert_same($expected, $keys, 'the pages of the step');
	$all = array_flip(_test_rx_page_keys());
	foreach ($want as $key => $folder) {
		pg_assert_true(isset($all[$key]), "$key is a page of the template");
		pg_assert_same($folder, $pages[$key]['folder'], "$key folder");
		pg_assert_false(isset($pages[$key]['requires']), "$key stands without the shop");
		if (isset($pages[$key]['meta_description'])) {
			pg_assert_true(mb_strlen($pages[$key]['meta_description'], 'UTF-8') <= 160, "$key: meta description fits");
		}
		if ($folder !== 'public') pg_assert_false($pages[$key]['search'] ?? true, "$key: not in the search");
	}
	pg_assert_same(lang('privacy-policy'), $pages['privacy']['name'], 'the privacy page has its shared name');
	foreach (array('prescription_view', 'ask_view') as $key) {
		pg_assert_same('{{tab:email_reply}}', $pages[$key]['comments']['email_page'], "$key: answers mailed with the reply e-mail");
		pg_assert_true($pages[$key]['comments']['login'], "$key: messages need an account");
	}
}

// The forms: what is sent opens its own page and mails the pharmacy and
// the sender through the template's e-mail pages.
function test_pharmacy_requests_forms_are_wired()
{
	$pages = _test_rxr_pages();
	foreach (array('prescription' => array('prescription_view', 'email_prescription_new', 'email_prescription_received', 'prescription_form'),
		'ask' => array('ask_view', 'email_question_new', 'email_question_received', 'ask_form')) as $key => $to) {
		$f = $pages[$key]['form'];
		pg_assert_same('{{tab:' . $to[0] . '}}', $f['confirmation_page_id'], "$key: opens its page");
		pg_assert_same('{{tab:' . $to[1] . '}}', $f['notify_page_id'], "$key: the pharmacy's e-mail");
		pg_assert_same('{{tab:' . $to[2] . '}}', $f['confirm_page_id'], "$key: the sender's e-mail");
		pg_assert_same(1, $f['auto_registration'], "$key: opens an account");
		pg_assert_same('{{site_email}}', $f['notify_email'], "$key: mails the pharmacy");
		pg_assert_same(1, substr_count(json_encode($pages[$key]['tree']), '"templateWidget":"' . $to[3] . '"'), "$key: places its form once");
	}
}

// The prescription request: the pick lists of parts.php, the kind and the
// pharmacist from the address, a day not before today and the office-only
// status; no file upload (prescriptions are shown at the counter).
function test_pharmacy_requests_prescription_form_fields()
{
	$d = _test_rxr_load();
	$f = _test_rxr_form_fields('prescription_form');
	pg_assert_same(array('service', 'pharmacist', 'patient_name', 'birth_date', 'prescription_kind', 'prescription_number', 'medicines',
		'collect', 'preferred_date', 'notes', 'first_name', 'last_name', 'email', 'phone', 'status', 'consent'), array_keys($f), 'the form fields');
	pg_assert_same($d['request_choices'], $f['service']['options'], 'the requests of parts.php');
	pg_assert_same($d['pharmacist_choices'], $f['pharmacist']['options'], 'the pharmacists of parts.php');
	pg_assert_same('service', $f['service']['cf']['url_param'] ?? '', 'kind from ?service=');
	pg_assert_same('pharmacist', $f['pharmacist']['cf']['url_param'] ?? '', 'pharmacist from ?pharmacist=');
	pg_assert_same('today', $f['preferred_date']['cf']['rules']['min_date'] ?? '', 'no day in the past');
	pg_assert_true($f['status']['cf']['office_use_only'] ?? false, 'status is the pharmacy\'s');
	pg_assert_same($d['prescription_statuses'], $f['status']['options'], 'the statuses of parts.php');
	foreach (array('first_name', 'last_name', 'email') as $n) pg_assert_true(isset($f[$n]['cf']['contact_field']), "$n fills the contact");
	pg_assert_false(strpos(json_encode(_test_rxr_load()['part']['widgets']['prescription_form']), '"file"') !== false, 'no prescription upload');
}

// The question: the topics of parts.php from ?topic=.
function test_pharmacy_requests_question_form_fields()
{
	$d = _test_rxr_load();
	$f = _test_rxr_form_fields('ask_form');
	pg_assert_same(array('topic', 'question', 'medicines_taken', 'first_name', 'last_name', 'email', 'phone', 'reply_by', 'status', 'consent'),
		array_keys($f), 'the form fields');
	pg_assert_same($d['topic_choices'], $f['topic']['options'], 'the topics of parts.php');
	pg_assert_same('topic', $f['topic']['cf']['url_param'] ?? '', 'topic from ?topic=');
	pg_assert_true($f['status']['cf']['office_use_only'] ?? false, 'status is the pharmacy\'s');
	pg_assert_same($d['question_statuses'], $f['status']['options'], 'the statuses of parts.php');
}

// The record pages, lists and e-mails read only fields their form has, and
// name their form; a request and a question are their sender's own.
function test_pharmacy_requests_widgets_read_their_form()
{
	$w = _test_rxr_load()['part']['widgets'];
	$system = array('form_item_view', 'not_found', '__edit_url', 'can_edit', 'record_found', 'record_not_found', 'reference_code',
		'number_of_comments', '__new_account_email', '__new_account_set_password_url', 'section');
	$forms = array('prescription' => array_keys(_test_rxr_form_fields('prescription_form')),
		'ask' => array_keys(_test_rxr_form_fields('ask_form')));
	$readers = array(
		'prescription' => array('prescription_detail', 'my_prescriptions_list', 'staff_prescriptions_list', 'email_prescription_admin', 'email_prescription_client'),
		'ask'          => array('question_detail', 'my_questions_list', 'staff_questions_list', 'email_question_admin', 'email_question_client'),
	);
	foreach ($readers as $form => $keys) {
		foreach ($keys as $key) {
			pg_assert_same('{{tab:' . $form . '}}', $w[$key]['config']['custom_form_page_id'], "$key: its form");
			$nodes = array();
			_test_rxr_nodes($w[$key]['tree'], $nodes);
			$read = 0;
			foreach ($nodes as $n) {
				foreach ($n['props']['_bindings'] ?? array() as $what => $token) {
					if ($what === 'eo_visible_if' || in_array($token, $system, true)) continue;
					pg_assert_true(in_array($token, $forms[$form], true), "$key reads $token, a field of the form");
					$read++;
				}
			}
			pg_assert_true($read >= 3, "$key reads the request");
			foreach ($w[$key]['config']['search_fields'] ?? array() as $s) pg_assert_true(in_array($s, $forms[$form], true), "$key: search field $s");
		}
	}
	pg_assert_same('submitter_only', $w['prescription_detail']['config']['access_control'], 'the customer\'s own request');
	pg_assert_same('submitter_only', $w['question_detail']['config']['access_control'], 'a private question');
	pg_assert_true($w['my_prescriptions_list']['config']['viewer_filter_submitter'], 'My Prescriptions shows the customer\'s');
	pg_assert_true($w['my_questions_list']['config']['viewer_filter_submitter'], 'My Questions shows the customer\'s');
	pg_assert_same('email_reply', $w['email_comment']['page'], 'the reply e-mail draws the comment');
}

// The request page says the original prescription is shown, offers online
// booking, the on-duty page and the emergency number.
function test_pharmacy_requests_request_page_boxes()
{
	$d = _test_rxr_load();
	$json = json_encode(_test_rxr_pages()['prescription']['tree']);
	pg_assert_contains(json_encode(lang('Bring the original prescription')), $json, 'the original prescription box');
	pg_assert_contains(json_encode(lang('Book a vaccination or check')), $json, 'the booking box');
	pg_assert_contains('{{page:book_online}}', $json, 'links online booking');
	pg_assert_contains('{{page:on_duty}}', $json, 'links the on-duty page');
	pg_assert_contains('tel:' . preg_replace('/[^0-9+]/', '', $d['pharmacy']['emergency']), $json, 'calls the emergency number');
	pg_assert_contains('tel:' . preg_replace('/[^0-9+]/', '', $d['pharmacy']['emergency']), json_encode(_test_rxr_pages()['ask']['tree']), 'the question page too');
}

// The e-mail frame: header and footer as shared trees with the pharmacy's
// address, and every e-mail page in the frame.
function test_pharmacy_requests_mail_frame()
{
	$d = _test_rxr_load();
	pg_assert_contains('{{site_name}}', json_encode($d['email_header']), 'the header names the site');
	pg_assert_contains($d['pharmacy']['address'] . ' · ' . $d['pharmacy']['phone'], $d['email_footer']['children'][1]['props']['text'], 'the footer gives the address');
	foreach (_test_rxr_pages() as $key => $p) {
		if (strpos($key, 'email_') !== 0) continue;
		$json = json_encode($p['tree']);
		pg_assert_contains('"templateShared":"email_header"', $json, "$key: the e-mail header");
		pg_assert_contains('"templateShared":"email_footer"', $json, "$key: the e-mail footer");
		pg_assert_true($p['noindex'] && !$p['sitemap'], "$key: out of the sitemap");
	}
}

// Every link names a template page and no shop page.
function test_pharmacy_requests_links()
{
	$json = json_encode(array(_test_rxr_pages(), _test_rxr_load()['part']['widgets']));
	preg_match_all('/\{\{(?:page|tab):([a-z0-9_]+)\}\}/', $json, $m);
	$known = array_flip(_test_rx_page_keys());
	pg_assert_true(count(array_unique($m[1])) > 10, 'links found');
	foreach (array_unique($m[1]) as $key) pg_assert_true(isset($known[$key]), "{{page:$key}} names a template page");
	foreach (_test_rx_shop_keys() as $key) pg_assert_false(strpos($json, '{{page:' . $key . '}}') !== false, "no link to $key");
}

// Ids carry rx- (the comment form's own aside), classes are Bootstrap's or
// the template's, no faded text.
function test_pharmacy_requests_ids_and_classes()
{
	$nodes = array();
	foreach (_test_rxr_pages() as $p) _test_rxr_nodes($p['tree'], $nodes);
	foreach (_test_rxr_load()['part']['widgets'] as $w) {
		if (is_array($w['tree'] ?? null)) _test_rxr_nodes($w['tree'], $nodes);
	}
	pg_assert_true(count($nodes) > 200, 'nodes found');
	foreach ($nodes as $n) {
		foreach (preg_split('/\s+/', (string)($n['props']['cssClass'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $c) {
			pg_assert_false((bool)preg_match('/^(pre|pev|pf|pdp|pm|pma|plx|pdl|plw|psl|pco|pcl|pht|pgy|pcn|pmu|pph|psh|ptv|pwd|pch|pmz|pai|pvt)-/', $c), "class $c is not another template's");
			pg_assert_false(strpos($c, 'opacity-') === 0, "no faded text ($c)");
		}
		$id = (string)($n['props']['id'] ?? '');
		// The comment thread's form fields keep the ids the Comments widget reads.
		if ($id !== '' && strpos($id, 'comment_') !== 0) pg_assert_true(strpos($id, 'rx-') === 0, "id $id is the template's");
	}
}

// Every literal has its Turkish, and the Turkish names the template's
// things one way (page addresses excepted): a pharmacy is "eczane", a
// pharmacist "eczacı", a prescription "reçete", a vaccination "aşı".
function test_pharmacy_requests_turkish_words()
{
	$map = pg_test_language_map('tr');
	foreach (array('requests.php', 'mail.php') as $file) {
		$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-pharmacy/' . $file);
		preg_match_all("/lang\\('((?:[^'\\\\]|\\\\.)*)'\\)/", $src, $m);
		foreach (array_unique($m[1]) as $raw) {
			$key = stripslashes($raw);
			pg_assert_true(isset($map[$key]) && $map[$key] !== '', "Turkish for '$key'");
			if (!isset($map[$key]) || preg_match('/^[a-z0-9-]+$/', $key)) continue;
			$tr = $map[$key];
			if (preg_match('/\bPharmacists?\b/i', $key)) pg_assert_true(mb_stripos($tr, 'eczacı', 0, 'UTF-8') !== false, "'$key' says eczacı");
			elseif (preg_match('/\bPharmac(y|ies)\b/i', $key)) pg_assert_true(mb_stripos($tr, 'eczane', 0, 'UTF-8') !== false, "'$key' says eczane");
			if (preg_match('/\bPrescriptions?\b/i', $key)) pg_assert_true(mb_stripos($tr, 'reçete', 0, 'UTF-8') !== false, "'$key' says reçete");
			if (preg_match('/\bon[ -]duty\b/i', $key)) pg_assert_true(mb_stripos($tr, 'nöbet', 0, 'UTF-8') !== false, "'$key' says nöbet");
			if (preg_match('/\bVaccinations?\b/i', $key)) pg_assert_true(mb_stripos($tr, 'aşı', 0, 'UTF-8') !== false, "'$key' says aşı");
		}
	}
}
