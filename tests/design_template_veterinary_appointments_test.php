<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Veterinary design template's e-mail builders and requests
 * (includes/design_templates/pinegrap-veterinary/mail.php and
 * appointments.php): the pages and their folders, the two forms with the
 * pick lists of parts.php and the values a link sends (?service=, ?vet=,
 * ?species=, ?plan=), the office-only status, the lists, record pages and
 * e-mails that read only the fields their form has, the e-mail pages wired
 * to the forms, the free time and emergency boxes beside the request, links
 * to template pages only, the template's ids and classes and every literal
 * in Turkish.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The page and shop keys of the finished template (_test_vt_page_keys(),
// _test_vt_shop_keys()) live in the parts test.
require_once __DIR__ . '/design_template_veterinary_test.php';

// parts.php, mail.php and the requests part in one scope, as the template
// file reads them. Read once per run.
function _test_vta_load()
{
	static $out = null;
	if ($out !== null) return $out;
	include PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-veterinary/parts.php';
	include PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-veterinary/mail.php';
	$part = include PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-veterinary/appointments.php';
	$out = array('part' => $part, 'practice' => $practice, 'pet_choices' => $pet_choices, 'reason_choices' => $reason_choices,
		'vet_choices' => $vet_choices, 'plan_choices' => $plan_choices, 'appointment_statuses' => $appointment_statuses,
		'plan_join_statuses' => $plan_join_statuses, 'email_header' => $email_header_tree(), 'email_footer' => $email_footer_tree());
	return $out;
}

// Every node of a tree, depth first.
function _test_vta_nodes($node, &$out)
{
	if (!is_array($node)) return;
	if (isset($node['props'])) $out[] = $node;
	if (!empty($node['children']) && is_array($node['children'])) {
		foreach ($node['children'] as $child) _test_vta_nodes($child, $out);
	}
}

// The pages of this part, by key.
function _test_vta_pages()
{
	$pages = array();
	foreach (_test_vta_load()['part']['pages'] as $p) $pages[$p['key']] = $p;
	return $pages;
}

// The fields of a form widget by name: its tag, _cf settings and options.
function _test_vta_form_fields($widget_key)
{
	$nodes = array();
	_test_vta_nodes(_test_vta_load()['part']['widgets'][$widget_key]['tree'], $nodes);
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

function test_veterinary_appointments_pages_and_folders()
{
	$part = _test_vta_load()['part'];
	pg_assert_same(array('pages', 'widgets'), array_keys($part), 'the part returns pages and widgets');
	$pages = _test_vta_pages();
	$want = array('appointment' => 'public', 'appointment_view' => 'registration', 'my_appointments' => 'registration',
		'staff_appointments' => 'private', 'plan_join' => 'public', 'plan_join_view' => 'registration', 'staff_plan_joins' => 'private',
		'privacy' => 'public', 'email_appointment_new' => 'private', 'email_appointment_received' => 'private',
		'email_plan_join_new' => 'private', 'email_plan_join_received' => 'private', 'email_reply' => 'private');
	$keys = array_keys($pages);
	sort($keys);
	$expected = array_keys($want);
	sort($expected);
	pg_assert_same($expected, $keys, 'the pages of the step');
	$all = array_flip(_test_vt_page_keys());
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
	foreach (array('appointment_view', 'plan_join_view') as $key) {
		pg_assert_same('{{tab:email_reply}}', $pages[$key]['comments']['email_page'], "$key: answers mailed with the reply e-mail");
		pg_assert_true($pages[$key]['comments']['login'], "$key: messages need an account");
	}
}

// The forms: what is sent opens its own page and mails the reception and
// the sender through the template's e-mail pages.
function test_veterinary_appointments_forms_are_wired()
{
	$pages = _test_vta_pages();
	foreach (array('appointment' => array('appointment_view', 'email_appointment_new', 'email_appointment_received'),
		'plan_join' => array('plan_join_view', 'email_plan_join_new', 'email_plan_join_received')) as $key => $to) {
		$f = $pages[$key]['form'];
		pg_assert_same('{{tab:' . $to[0] . '}}', $f['confirmation_page_id'], "$key: opens its page");
		pg_assert_same('{{tab:' . $to[1] . '}}', $f['notify_page_id'], "$key: the reception's e-mail");
		pg_assert_same('{{tab:' . $to[2] . '}}', $f['confirm_page_id'], "$key: the sender's e-mail");
		pg_assert_same(1, $f['auto_registration'], "$key: opens an account");
		pg_assert_same('{{site_email}}', $f['notify_email'], "$key: mails the practice");
		pg_assert_same(1, substr_count(json_encode($pages[$key]['tree']), '"templateWidget":"' . $key . '_form"'), "$key: places its form once");
	}
}

// The appointment request: the pick lists of parts.php, the service, the
// vet and the pet from the address, a date not before today and the
// office-only status.
function test_veterinary_appointments_appointment_form_fields()
{
	$d = _test_vta_load();
	$f = _test_vta_form_fields('appointment_form');
	pg_assert_same(array('pet_name', 'species', 'pet_age', 'visit', 'service', 'vet', 'preferred_date', 'time', 'notes', 'records',
		'first_name', 'last_name', 'email', 'phone', 'status', 'consent'), array_keys($f), 'the form fields');
	pg_assert_same($d['pet_choices'], $f['species']['options'], 'the pets of parts.php');
	pg_assert_same($d['reason_choices'], $f['service']['options'], 'the services of parts.php');
	pg_assert_same($d['vet_choices'], $f['vet']['options'], 'the vets of parts.php');
	pg_assert_same('species', $f['species']['cf']['url_param'] ?? '', 'pet from ?species=');
	pg_assert_same('service', $f['service']['cf']['url_param'] ?? '', 'service from ?service=');
	pg_assert_same('vet', $f['vet']['cf']['url_param'] ?? '', 'vet from ?vet=');
	pg_assert_same('today', $f['preferred_date']['cf']['rules']['min_date'] ?? '', 'no day in the past');
	pg_assert_same('{{folder:private}}', $f['records']['cf']['upload_folder_id'] ?? '', 'results kept in the private folder');
	pg_assert_true($f['status']['cf']['office_use_only'] ?? false, 'status is the reception\'s');
	pg_assert_same($d['appointment_statuses'], $f['status']['options'], 'the statuses of parts.php');
	foreach (array('first_name', 'last_name', 'email') as $n) pg_assert_true(isset($f[$n]['cf']['contact_field']), "$n fills the contact");
}

// The health plan request: the plans of parts.php from ?plan=.
function test_veterinary_appointments_plan_join_form_fields()
{
	$d = _test_vta_load();
	$f = _test_vta_form_fields('plan_join_form');
	pg_assert_same(array('plan', 'pet_name', 'species', 'pet_age', 'start_date', 'notes',
		'first_name', 'last_name', 'email', 'phone', 'reply_by', 'status', 'consent'), array_keys($f), 'the form fields');
	pg_assert_same($d['plan_choices'], $f['plan']['options'], 'the plans of parts.php');
	pg_assert_same('plan', $f['plan']['cf']['url_param'] ?? '', 'plan from ?plan=');
	pg_assert_false(isset($f['species']['cf']['url_param']), 'the pet is not from the address here');
	pg_assert_same('today', $f['start_date']['cf']['rules']['min_date'] ?? '', 'no start in the past');
	pg_assert_true($f['status']['cf']['office_use_only'] ?? false, 'status is the reception\'s');
	pg_assert_same($d['plan_join_statuses'], $f['status']['options'], 'the statuses of parts.php');
}

// The record pages, lists and e-mails read only fields their form has, and
// name their form.
function test_veterinary_appointments_widgets_read_their_form()
{
	$w = _test_vta_load()['part']['widgets'];
	$system = array('form_item_view', 'not_found', '__edit_url', 'can_edit', 'record_found', 'record_not_found', 'reference_code',
		'number_of_comments', '__new_account_email', '__new_account_set_password_url', 'section');
	$forms = array('appointment' => array_keys(_test_vta_form_fields('appointment_form')),
		'plan_join' => array_keys(_test_vta_form_fields('plan_join_form')));
	$readers = array(
		'appointment' => array('appointment_detail', 'my_appointments_list', 'staff_appointments_list', 'email_appointment_admin', 'email_appointment_client'),
		'plan_join'   => array('plan_join_detail', 'staff_plan_joins_list', 'email_plan_join_admin', 'email_plan_join_client'),
	);
	foreach ($readers as $form => $keys) {
		foreach ($keys as $key) {
			pg_assert_same('{{tab:' . $form . '}}', $w[$key]['config']['custom_form_page_id'], "$key: its form");
			$nodes = array();
			_test_vta_nodes($w[$key]['tree'], $nodes);
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
	pg_assert_same('submitter_only', $w['appointment_detail']['config']['access_control'], 'the owner\'s own request');
	pg_assert_same('submitter_only', $w['plan_join_detail']['config']['access_control'], 'the owner\'s own request');
	pg_assert_true($w['my_appointments_list']['config']['viewer_filter_submitter'], 'My Appointments shows the owner\'s');
	pg_assert_same('email_reply', $w['email_comment']['page'], 'the reply e-mail draws the comment');
}

// The request page offers a free time at once and the emergency line.
function test_veterinary_appointments_request_page_links_booking_and_emergency()
{
	$d = _test_vta_load();
	$json = json_encode(_test_vta_pages()['appointment']['tree']);
	pg_assert_contains('{{page:book_online}}', $json, 'a free time now');
	pg_assert_contains(json_encode(lang('Pick a free time now')), $json, 'the free time box');
	pg_assert_contains('tel:' . preg_replace('/[^0-9+]/', '', $d['practice']['emergency']), $json, 'calls the emergency line');
}

// The e-mail frame: header and footer as shared trees with the practice's
// address, and every e-mail page in the frame.
function test_veterinary_appointments_mail_frame()
{
	$d = _test_vta_load();
	pg_assert_contains('{{site_name}}', json_encode($d['email_header']), 'the header names the site');
	pg_assert_contains($d['practice']['address'] . ' · ' . $d['practice']['phone'], $d['email_footer']['children'][1]['props']['text'], 'the footer gives the address');
	foreach (_test_vta_pages() as $key => $p) {
		if (strpos($key, 'email_') !== 0) continue;
		$json = json_encode($p['tree']);
		pg_assert_contains('"templateShared":"email_header"', $json, "$key: the e-mail header");
		pg_assert_contains('"templateShared":"email_footer"', $json, "$key: the e-mail footer");
		pg_assert_true($p['noindex'] && !$p['sitemap'], "$key: out of the sitemap");
	}
}

// Every link names a template page and no shop page.
function test_veterinary_appointments_links()
{
	$json = json_encode(array(_test_vta_pages(), _test_vta_load()['part']['widgets']));
	preg_match_all('/\{\{(?:page|tab):([a-z0-9_]+)\}\}/', $json, $m);
	$known = array_flip(_test_vt_page_keys());
	pg_assert_true(count(array_unique($m[1])) > 10, 'links found');
	foreach (array_unique($m[1]) as $key) pg_assert_true(isset($known[$key]), "{{page:$key}} names a template page");
	foreach (_test_vt_shop_keys() as $key) pg_assert_false(strpos($json, '{{page:' . $key . '}}') !== false, "no link to $key");
}

// Ids carry vt- (the comment form's own aside), classes are Bootstrap's or the template's, no faded text.
function test_veterinary_appointments_ids_and_classes()
{
	$nodes = array();
	foreach (_test_vta_pages() as $p) _test_vta_nodes($p['tree'], $nodes);
	foreach (_test_vta_load()['part']['widgets'] as $w) {
		if (is_array($w['tree'] ?? null)) _test_vta_nodes($w['tree'], $nodes);
	}
	pg_assert_true(count($nodes) > 200, 'nodes found');
	foreach ($nodes as $n) {
		foreach (preg_split('/\s+/', (string)($n['props']['cssClass'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $c) {
			pg_assert_false((bool)preg_match('/^(pre|pev|pf|pdp|pm|pma|plx|pdl|plw|psl|pco|pcl|pht|pgy|pcn|pmu|pph|psh|ptv|pwd|pch|pmz|pai)-/', $c), "class $c is not another template's");
			pg_assert_false(strpos($c, 'opacity-') === 0, "no faded text ($c)");
		}
		$id = (string)($n['props']['id'] ?? '');
		// The comment thread's form fields keep the ids the Comments widget reads.
		if ($id !== '' && strpos($id, 'comment_') !== 0) pg_assert_true(strpos($id, 'vt-') === 0, "id $id is the template's");
	}
}

// Every literal has its Turkish; a vet reads "veteriner", a pet "evcil
// hayvan", an appointment "randevu", an emergency "acil".
function test_veterinary_appointments_turkish_words()
{
	$map = pg_test_language_map('tr');
	foreach (array('appointments.php', 'mail.php') as $file) {
		$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-veterinary/' . $file);
		preg_match_all("/lang\\('((?:[^'\\\\]|\\\\.)*)'\\)/", $src, $m);
		foreach (array_unique($m[1]) as $raw) {
			$key = stripslashes($raw);
			pg_assert_true(isset($map[$key]) && $map[$key] !== '', "Turkish for '$key'");
			if (!isset($map[$key])) continue;
			$tr = $map[$key];
			if (preg_match('/\bVets?\b/', $key)) pg_assert_true(mb_stripos($tr, 'veteriner', 0, 'UTF-8') !== false, "'$key' says veteriner");
			if (preg_match('/\bPets?\b/', $key)) pg_assert_true(mb_stripos($tr, 'evcil hayvan', 0, 'UTF-8') !== false, "'$key' says evcil hayvan");
			if (preg_match('/\bAppointments?\b/', $key)) pg_assert_true(mb_stripos($tr, 'randevu', 0, 'UTF-8') !== false, "'$key' says randevu");
			if (preg_match('/\bEmergenc(y|ies)\b/', $key)) pg_assert_true(mb_stripos($tr, 'acil', 0, 'UTF-8') !== false, "'$key' says acil");
		}
	}
}
