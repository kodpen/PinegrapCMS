<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Recruitment Agency design template's job applications and
 * e-mail builders (includes/design_templates/pinegrap-recruitment/mail.php
 * and applications.php): the pages and widgets, the application form (its
 * fields, pick lists, the job read from ?job= as the job page sends it and
 * an open application without one, the office-only status, the private
 * CV, the consent linking the privacy page), the record page with its
 * messages, the lists and e-mails binding fields their form has, the
 * e-mail colours the template's own with their var() fallbacks, every
 * page link naming a template page, ids and classes the template's own, no
 * fixed date and every literal with its Turkish.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The page keys of the finished template (_test_rc_page_keys()).
require_once(__DIR__ . '/design_template_recruitment_test.php');

// The applications' part as the template file reads it: parts.php,
// mail.php, jobs.php, then applications.php in the same scope. Read once
// per run.
function _test_rca_load()
{
	static $out = null;
	if ($out !== null) return $out;
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-recruitment';
	include $dir . '/parts.php';
	include $dir . '/mail.php';
	$jobs_part = include $dir . '/jobs.php';
	$part = include $dir . '/applications.php';
	$pages = array();
	foreach ($part['pages'] as $p) $pages[$p['key']] = $p;
	$job_page_link = null;
	$nodes = array();
	_test_rca_nodes($jobs_part['widgets']['job_detail']['tree'], $nodes);
	foreach ($nodes as $n) {
		$params = $n['props']['_bindParams']['href'] ?? null;
		if ($params && $params['page_id'] === '{{tab:apply}}') $job_page_link = $params;
	}
	$out = array('part' => $part, 'pages' => $pages, 'widgets' => $part['widgets'],
		'job_choices' => $application_job_choices, 'job_titles' => $job_titles,
		'sector_choices' => $application_sector_choices, 'city_choices' => $application_city_choices,
		'notice_choices' => $notice_choices, 'statuses' => $application_statuses,
		'job_page_link' => $job_page_link,
		'shared' => array($email_header_tree(), $email_footer_tree()));
	return $out;
}

function _test_rca_nodes($node, &$out)
{
	if (!is_array($node)) return;
	if (isset($node['props'])) $out[] = $node;
	foreach ((array)($node['children'] ?? array()) as $c) _test_rca_nodes($c, $out);
}

// The name, options, chosen answer and settings of every form control of a
// tree.
function _test_rca_controls($node)
{
	$nodes = array();
	_test_rca_nodes($node, $nodes);
	$out = array();
	foreach ($nodes as $n) {
		$tag = $n['props']['tag'] ?? '';
		if (!in_array($tag, array('input', 'select', 'textarea'), true)) continue;
		$name = '';
		$type = $tag;
		foreach ((array)($n['props']['_attrs'] ?? array()) as $a) {
			if ($a['name'] === 'name') $name = $a['value'];
			if ($a['name'] === 'type') $type = $a['value'];
		}
		$options = array();
		$selected = '';
		$prompt = false;
		foreach ((array)($n['children'] ?? array()) as $o) {
			$value = null;
			$is_selected = false;
			foreach ((array)($o['props']['_attrs'] ?? array()) as $a) {
				if ($a['name'] === 'value') $value = $a['value'];
				if ($a['name'] === 'selected') $is_selected = true;
			}
			if ($value === '') $prompt = true;
			elseif ($value !== null) $options[] = $value;
			if ($is_selected) $selected = $value;
		}
		$out[$name] = array('tag' => $tag, 'type' => $type, 'options' => $options, 'selected' => $selected, 'prompt' => $prompt,
			'cf' => $n['props']['_cf'] ?? array(), 'required' => strpos(json_encode($n['props']['_attrs'] ?? array()), '"required"') !== false);
	}
	return $out;
}

function test_recruitment_applications_pages_and_widgets()
{
	$d = _test_rca_load();
	pg_assert_same(array('pages', 'widgets'), array_keys($d['part']), 'the part returns pages and widgets');
	pg_assert_same(array('apply', 'application_view', 'my_applications', 'staff_applications', 'privacy',
		'email_application_new', 'email_application_received', 'email_reply'), array_keys($d['pages']));
	pg_assert_same(array('application_form', 'application_detail', 'application_messages', 'my_applications_list', 'staff_applications_list',
		'email_application_admin', 'email_application_client', 'email_comment'), array_keys($d['widgets']));
	foreach (array('apply', 'privacy') as $key) pg_assert_same('public', $d['pages'][$key]['folder'], "$key is public");
	foreach (array('application_view', 'my_applications') as $key) {
		pg_assert_same('registration', $d['pages'][$key]['folder'], "$key is read signed in");
	}
	foreach (array('staff_applications', 'email_application_new', 'email_application_received', 'email_reply') as $key) {
		pg_assert_same('private', $d['pages'][$key]['folder'], "$key is private");
		pg_assert_true($d['pages'][$key]['search'] === false, "$key is out of the search");
		pg_assert_true($d['pages'][$key]['sitemap'] === false, "$key is out of the sitemap");
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
	foreach (array('email_application_new', 'email_application_received', 'email_reply') as $key) {
		$json = json_encode($d['pages'][$key]['tree']);
		pg_assert_contains('"templateShared":"email_header"', $json, "$key: the e-mail header");
		pg_assert_contains('"templateShared":"email_footer"', $json, "$key: the e-mail footer");
	}
	pg_assert_same('submitter_only', $d['widgets']['application_detail']['config']['access_control'], 'an application is its candidate\'s');
	pg_assert_true(!empty($d['widgets']['my_applications_list']['config']['viewer_filter_submitter']), 'My Applications lists the own records');
	pg_assert_true(!empty($d['widgets']['staff_applications_list']['config']['search_enabled']), 'the staff search');
	pg_assert_contains('"regionType":"system"', json_encode($d['pages']['email_reply']['tree']), 'the reply e-mail keeps the fixed comment');
}

// The application: its fields, the job from ?job= as the job page sends
// it and an open application without one, the office-only status, the
// private CV, the contact fields and the consent linking the privacy page.
function test_recruitment_applications_form()
{
	$d = _test_rca_load();
	$form = $d['pages']['apply']['form'];
	pg_assert_same('{{tab:application_view}}', $form['confirmation_page_id']);
	pg_assert_same(1, $form['auto_registration'], 'a candidate without an account gets one');
	pg_assert_same('{{site_email}}', $form['notify_email']);
	pg_assert_same('{{tab:email_application_new}}', $form['notify_page_id']);
	pg_assert_same(1, $form['confirm_email']);
	pg_assert_same('{{tab:email_application_received}}', $form['confirm_page_id']);
	$c = _test_rca_controls($d['widgets']['application_form']['tree']);
	pg_assert_same(array('job', 'sector', 'city', 'notice_period', 'first_name', 'last_name', 'email', 'phone', 'cv', 'cover_letter', 'status', 'consent'), array_keys($c));
	pg_assert_same($d['job_choices'], $c['job']['options'], 'the jobs and the open application');
	pg_assert_same('job', $c['job']['cf']['url_param'] ?? '', 'the job reads ?job=');
	pg_assert_same(lang('Open application'), $c['job']['selected'], 'without ?job= it is an open application');
	pg_assert_false($c['job']['prompt'], 'no empty answer before the open application');
	pg_assert_same($d['sector_choices'], $c['sector']['options'], 'the sectors');
	pg_assert_same($d['city_choices'], $c['city']['options'], 'the cities');
	pg_assert_same($d['notice_choices'], $c['notice_period']['options'], 'the notice periods');
	pg_assert_same($d['statuses'], $c['status']['options'], 'the statuses');
	pg_assert_same($d['statuses'][0], $c['status']['selected'], 'every application starts as received');
	pg_assert_true(!empty($c['status']['cf']['office_use_only']), 'the status is the agency\'s');
	pg_assert_false(isset($c['status']['cf']['url_param']), 'no link presets the status');
	pg_assert_same('file', $c['cv']['type'], 'the CV is a file');
	pg_assert_same('{{folder:private}}', $c['cv']['cf']['upload_folder_id'] ?? '', 'the CV is private');
	pg_assert_same('textarea', $c['cover_letter']['tag'], 'the covering letter is long text');
	pg_assert_same('checkbox', $c['consent']['type'], 'the consent is a box');
	pg_assert_true($c['consent']['required'], 'the consent must be given');
	foreach (array('first_name' => 'first_name', 'last_name' => 'last_name', 'email' => 'email_address', 'phone' => 'mobile_phone') as $name => $contact) {
		pg_assert_same($contact, $c[$name]['cf']['contact_field'] ?? '', "$name fills the contact");
		pg_assert_true($c[$name]['required'], "$name is required");
	}
	$json = json_encode($d['widgets']['application_form']['tree']);
	pg_assert_contains('{{page:privacy}}', $json, 'the consent links the privacy page');
	pg_assert_contains('"section":"captcha"', $json, 'the CAPTCHA');
	// Every sample job's title is an answer, and the job page sends the
	// title under the name the field reads.
	foreach ($d['job_titles'] as $t) pg_assert_true(in_array($t, $c['job']['options'], true), "$t is an answer");
	pg_assert_same(array(array('name' => 'job', 'token' => 'title')), $d['job_page_link']['params'] ?? null, 'the job page sends ?job=<title>');
}

// The record page carries the messages and mails each one; the pages, the
// lists and the e-mails bind fields the form has or the renderer gives.
function test_recruitment_applications_record_and_mail()
{
	$d = _test_rca_load();
	$comments = $d['pages']['application_view']['comments'];
	pg_assert_true($comments['allow_new'] && $comments['login'] && $comments['attachments'], 'messages with a file from the signed-in reader');
	pg_assert_same('{{tab:email_reply}}', $comments['email_page']);
	pg_assert_same('{{site_email}}', $comments['notify_email']);
	pg_assert_same('comments', $d['widgets']['application_messages']['config']['regionType']);
	$fields = array_keys(_test_rca_controls($d['widgets']['application_form']['tree']));
	$renderer = array('reference_code', 'not_found', 'number_of_comments', 'form_item_view', 'submitted_date_and_time');
	foreach (array('application_detail', 'my_applications_list', 'staff_applications_list', 'email_application_admin', 'email_application_client') as $key) {
		pg_assert_same('{{tab:apply}}', $d['widgets'][$key]['config']['custom_form_page_id'], "$key reads the application form");
		$nodes = array();
		_test_rca_nodes($d['widgets'][$key]['tree'], $nodes);
		foreach ($nodes as $n) {
			foreach (array('text', 'href') as $prop) {
				$token = (string)($n['props']['_bindings'][$prop] ?? '');
				if ($token === '' || strpos($token, '__') === 0 || in_array($token, $renderer, true)) continue;
				pg_assert_true(in_array($token, $fields, true), "$key binds $token, a field of the form");
			}
		}
	}
	foreach (array('my_applications_list', 'staff_applications_list') as $key) {
		pg_assert_same('{{tab:application_view}}', $d['widgets'][$key]['config']['detail_page_id'], "$key opens the application");
	}
	foreach ($d['widgets']['staff_applications_list']['config']['search_fields'] as $name) {
		pg_assert_true(in_array($name, $fields, true), "the staff search $name, a field of the form");
	}
	$json = json_encode($d['widgets']['application_detail']['tree']);
	foreach (array('record_found', 'record_not_found', 'submitted_this_session', 'has_new_account', 'can_edit') as $flag) {
		pg_assert_contains('"eo_visible_if":"' . $flag . '"', $json, "the record page shows $flag");
	}
	foreach (array('email_application_admin', 'email_application_client') as $key) {
		pg_assert_same('public', $d['widgets'][$key]['config']['access_control'], "$key is drawn for the submission just sent");
		pg_assert_contains('{{page:application_view}}?r=^^reference_code^^', json_encode($d['widgets'][$key]['tree'], JSON_UNESCAPED_SLASHES), "$key opens the record");
	}
	pg_assert_contains('"reference_code"', json_encode($d['widgets']['email_application_admin']['tree']), 'the office e-mail ends with the reference');
	pg_assert_contains('"eo_visible_if":"has_new_account"', json_encode($d['widgets']['email_application_client']['tree']), 'the candidate e-mail names a new account');
	pg_assert_contains('"__comment_url"', json_encode($d['widgets']['email_comment']['tree']), 'the reply card links its comment');
}

// The e-mails keep the template's emerald family when the stylesheet is
// dropped: every colour of mail.php is one of the family, a palette colour
// is written plain and then inside var() with the same fallback, and no
// var() goes without one.
function test_recruitment_applications_mail_colours()
{
	$mail = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-recruitment/mail.php');
	preg_match_all('/#[0-9a-f]{3,6}\b/i', $mail, $colors);
	pg_assert_true(count($colors[0]) > 10, 'colours found');
	foreach (array_unique($colors[0]) as $hex) {
		pg_assert_true(in_array(strtolower($hex), array('#047857', '#a7f3d0', '#ecfdf5', '#ffffff', '#1f2937', '#4b5563'), true), "e-mail colour $hex is the template's");
	}
	pg_assert_contains('var(--bs-primary,#047857)', $mail, 'the emerald fallback');
	pg_assert_false((bool)preg_match('/var\(--[a-z0-9-]+\)/', $mail), 'every var() keeps its fallback');
	preg_match_all('/(?<![-a-z])(color|background-color):var\((--[a-z0-9-]+),(#[0-9a-f]{6})\)/', $mail, $vars, PREG_SET_ORDER);
	pg_assert_true(count($vars) >= 4, 'palette colours through var()');
	foreach ($vars as $v) {
		pg_assert_contains($v[1] . ':' . $v[3] . ';' . $v[0], $mail, "{$v[2]} is written plain first for a client without custom properties");
	}
}

function test_recruitment_applications_links_ids_and_classes()
{
	$d = _test_rca_load();
	$known = array_flip(_test_rc_page_keys());
	$trees = $d['shared'];
	foreach ($d['pages'] as $p) $trees[] = $p;
	foreach ($d['widgets'] as $w) $trees[] = array($w['config'], $w['tree']);
	$json = json_encode($trees);
	preg_match_all('/\{\{(?:page|tab):([a-z0-9_]+)\}\}/', $json, $m);
	foreach (array_unique($m[1]) as $key) pg_assert_true(isset($known[$key]), "page link {{page:$key}} names a template page");
	foreach (array('product', 'cart', 'checkout', 'order', 'email_order') as $key) {
		pg_assert_false(strpos($json, '{{page:' . $key . '}}') !== false, "no tree links to $key");
	}
	$nodes = array();
	foreach ($d['shared'] as $t) _test_rca_nodes($t, $nodes);
	foreach ($d['pages'] as $p) _test_rca_nodes($p['tree'], $nodes);
	foreach ($d['widgets'] as $w) _test_rca_nodes($w['tree'], $nodes);
	foreach ($nodes as $n) {
		foreach (preg_split('/\s+/', (string)($n['props']['cssClass'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $c) {
			pg_assert_false((bool)preg_match('/^p[a-z]{2}-/', $c) && strpos($c, 'prc-') !== 0 && strpos($c, 'pg-') !== 0, "class $c is not another template's");
			pg_assert_false((bool)preg_match('/^(text-)?opacity-/', $c), "no faded text class $c");
		}
		$id = (string)($n['props']['id'] ?? '');
		if ($id !== '' && strpos($id, '-') !== false) pg_assert_true(strpos($id, 'rc-') === 0, "id $id is the template's");
	}
	// The sample names no year, month or day.
	foreach (array('mail.php', 'applications.php') as $file) {
		$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-recruitment/' . $file);
		preg_match_all("/lang\\('((?:[^'\\\\]|\\\\.)*)'\\)/", $src, $mm);
		foreach ($mm[1] as $raw) {
			pg_assert_false((bool)preg_match('/\b(19|20)[0-9]{2}\b|\b(January|February|March|April|May|June|July|August|September|October|November|December)\b/', $raw), "$file: '$raw' names no fixed date");
		}
	}
	pg_assert_contains('"id":"rc-your-data"', json_encode($d['pages']['privacy']['tree']), 'the privacy text is a search section');
}

// Every literal has its Turkish; an application reads "başvuru", a
// candidate "aday", an employer "işveren", a consultant "danışman", a
// staff request "personel talebi", Career Advice "Kariyer Rehberi".
function test_recruitment_applications_turkish_words()
{
	$map = pg_test_language_map('tr');
	$words = array(
		'/\bapplications?\b/i'    => 'başvuru',
		'/\bcandidates?\b/i'      => 'aday',
		'/\bemployers?\b/i'       => 'işveren',
		'/\bconsultants?\b/i'     => 'danışman',
		'/\bstaff requests?\b/i'  => 'personel tale',
		'/\bcareer advice\b/i'    => 'kariyer rehber',
	);
	foreach (array('mail.php', 'applications.php') as $file) {
		$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-recruitment/' . $file);
		preg_match_all("/lang\\('((?:[^'\\\\]|\\\\.)*)'\\)/", $src, $m);
		foreach (array_unique($m[1]) as $raw) {
			$key = stripslashes($raw);
			pg_assert_true(isset($map[$key]) && $map[$key] !== '', "$file: Turkish for '$key'");
			if (!isset($map[$key]) || preg_match('/^[a-z0-9-]+$/', $key)) continue;
			$value = str_replace('İ', 'i', $map[$key]);
			foreach ($words as $re => $tr) {
				if (preg_match($re, $key)) pg_assert_true(mb_stripos($value, $tr, 0, 'UTF-8') !== false, "'$key' says $tr");
			}
		}
	}
}
