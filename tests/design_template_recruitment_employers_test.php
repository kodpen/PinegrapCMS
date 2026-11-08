<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Recruitment Agency design template's employer enquiries
 * (includes/design_templates/pinegrap-recruitment/employers.php): the
 * pages and widgets, the staff request form (its fields, pick lists, the
 * sector read from ?sector= as the sector pages send it, the office-only
 * status, the private job description, the consent linking the privacy
 * page), the record page with its messages, the lists and e-mails binding
 * fields their form has, every page link naming a template page, ids and
 * classes the template's own, no fixed date and every literal with its
 * Turkish.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The page keys (_test_rc_page_keys()) and the tree helpers
// (_test_rca_nodes(), _test_rca_controls()).
require_once(__DIR__ . '/design_template_recruitment_test.php');
require_once(__DIR__ . '/design_template_recruitment_applications_test.php');

// The employers' part as the template file reads it: parts.php, mail.php,
// jobs.php, applications.php, then employers.php in the same scope. Read
// once per run.
function _test_rce_load()
{
	static $out = null;
	if ($out !== null) return $out;
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-recruitment';
	include $dir . '/parts.php';
	include $dir . '/mail.php';
	include $dir . '/jobs.php';
	include $dir . '/applications.php';
	$public_part = include $dir . '/pages_public.php';
	$part = include $dir . '/employers.php';
	$pages = array();
	foreach ($part['pages'] as $p) $pages[$p['key']] = $p;
	$sector_link = null;
	foreach ($public_part['pages'] as $p) {
		if ($p['key'] !== 'sector_technology') continue;
		preg_match('/\{\{page:hire\}\}\?([^"]+)"/', json_encode($p['tree'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), $m);
		if ($m) parse_str($m[1], $sector_link);
	}
	$out = array('part' => $part, 'pages' => $pages, 'widgets' => $part['widgets'],
		'sector_choices' => $enquiry_sector_choices, 'sector_titles' => $sector_titles, 'city_choices' => $application_city_choices,
		'service_choices' => $enquiry_service_choices, 'contract_choices' => $contract_titles,
		'headcount_choices' => $headcount_choices, 'start_choices' => $start_choices, 'statuses' => $enquiry_statuses,
		'sector_link' => $sector_link, 'technology' => $sectors['technology']['title']);
	return $out;
}

function test_recruitment_employers_pages_and_widgets()
{
	$d = _test_rce_load();
	pg_assert_same(array('pages', 'widgets'), array_keys($d['part']), 'the part returns pages and widgets');
	pg_assert_same(array('hire', 'enquiry_view', 'my_enquiries', 'staff_enquiries', 'email_enquiry_new', 'email_enquiry_received'), array_keys($d['pages']));
	pg_assert_same(array('enquiry_form', 'enquiry_detail', 'enquiry_messages', 'my_enquiries_list', 'staff_enquiries_list',
		'email_enquiry_admin', 'email_enquiry_client'), array_keys($d['widgets']));
	pg_assert_same('public', $d['pages']['hire']['folder'], 'hire is public');
	foreach (array('enquiry_view', 'my_enquiries') as $key) {
		pg_assert_same('registration', $d['pages'][$key]['folder'], "$key is read signed in");
	}
	foreach (array('staff_enquiries', 'email_enquiry_new', 'email_enquiry_received') as $key) {
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
	foreach (array('email_enquiry_new', 'email_enquiry_received') as $key) {
		$json = json_encode($d['pages'][$key]['tree']);
		pg_assert_contains('"templateShared":"email_header"', $json, "$key: the e-mail header");
		pg_assert_contains('"templateShared":"email_footer"', $json, "$key: the e-mail footer");
	}
	pg_assert_same('submitter_only', $d['widgets']['enquiry_detail']['config']['access_control'], 'a staff request is its employer\'s');
	pg_assert_true(!empty($d['widgets']['my_enquiries_list']['config']['viewer_filter_submitter']), 'My Staff Requests lists the own records');
	pg_assert_true(!empty($d['widgets']['staff_enquiries_list']['config']['search_enabled']), 'the staff search');
}

// The staff request: its fields, the sector from ?sector= as the sector
// pages send it, the office-only status, the private job description,
// the company and contact fields and the consent linking the privacy page.
function test_recruitment_employers_form()
{
	$d = _test_rce_load();
	$form = $d['pages']['hire']['form'];
	pg_assert_same('{{tab:enquiry_view}}', $form['confirmation_page_id']);
	pg_assert_same(1, $form['auto_registration'], 'an employer without an account gets one');
	pg_assert_same('{{site_email}}', $form['notify_email']);
	pg_assert_same('{{tab:email_enquiry_new}}', $form['notify_page_id']);
	pg_assert_same(1, $form['confirm_email']);
	pg_assert_same('{{tab:email_enquiry_received}}', $form['confirm_page_id']);
	pg_assert_contains('^^role^^', $form['notify_subject'], 'the office subject names the role');
	$c = _test_rca_controls($d['widgets']['enquiry_form']['tree']);
	pg_assert_same(array('role', 'sector', 'city', 'service', 'contract_type', 'headcount', 'start', 'details', 'job_description',
		'company', 'first_name', 'last_name', 'email', 'phone', 'status', 'consent'), array_keys($c));
	pg_assert_true($c['role']['required'], 'the role is required');
	pg_assert_same($d['sector_choices'], $c['sector']['options'], 'the sectors and another one');
	pg_assert_same('sector', $c['sector']['cf']['url_param'] ?? '', 'the sector reads ?sector=');
	pg_assert_true($c['sector']['prompt'], 'without ?sector= the sector is chosen');
	pg_assert_same($d['city_choices'], $c['city']['options'], 'the cities');
	pg_assert_same($d['service_choices'], $c['service']['options'], 'the employer services');
	pg_assert_same($d['contract_choices'], $c['contract_type']['options'], 'the contract types');
	pg_assert_same($d['headcount_choices'], $c['headcount']['options'], 'how many people');
	pg_assert_same($d['start_choices'], $c['start']['options'], 'the start window');
	pg_assert_same($d['statuses'], $c['status']['options'], 'the statuses');
	pg_assert_same($d['statuses'][0], $c['status']['selected'], 'every request starts as received');
	pg_assert_true(!empty($c['status']['cf']['office_use_only']), 'the status is the agency\'s');
	pg_assert_false(isset($c['status']['cf']['url_param']), 'no link presets the status');
	pg_assert_same('file', $c['job_description']['type'], 'the job description is a file');
	pg_assert_same('{{folder:private}}', $c['job_description']['cf']['upload_folder_id'] ?? '', 'the job description is private');
	pg_assert_false($c['job_description']['required'], 'the job description is optional');
	pg_assert_same('textarea', $c['details']['tag'], 'the role is described in long text');
	pg_assert_same('checkbox', $c['consent']['type'], 'the consent is a box');
	pg_assert_true($c['consent']['required'], 'the consent must be given');
	foreach (array('company' => 'company', 'first_name' => 'first_name', 'last_name' => 'last_name', 'email' => 'email_address', 'phone' => 'mobile_phone') as $name => $contact) {
		pg_assert_same($contact, $c[$name]['cf']['contact_field'] ?? '', "$name fills the contact");
		pg_assert_true($c[$name]['required'], "$name is required");
	}
	$json = json_encode($d['widgets']['enquiry_form']['tree']);
	pg_assert_contains('{{page:privacy}}', $json, 'the consent links the privacy page');
	pg_assert_contains('"section":"captcha"', $json, 'the CAPTCHA');
	// Every sector's title is an answer, and a sector page sends its title
	// under the name the field reads.
	foreach ($d['sector_titles'] as $t) pg_assert_true(in_array($t, $c['sector']['options'], true), "$t is an answer");
	pg_assert_same(array('sector' => $d['technology']), $d['sector_link'], 'a sector page sends ?sector=<title>');
}

// The record page carries the messages; the pages, the lists and the
// e-mails bind fields the form has or the renderer gives.
function test_recruitment_employers_record_and_mail()
{
	$d = _test_rce_load();
	$comments = $d['pages']['enquiry_view']['comments'];
	pg_assert_true($comments['allow_new'] && $comments['login'] && $comments['attachments'], 'messages with a file from the signed-in reader');
	pg_assert_same('{{tab:email_reply}}', $comments['email_page'], 'the reply e-mail every thread shares');
	pg_assert_same('{{site_email}}', $comments['notify_email']);
	pg_assert_same('comments', $d['widgets']['enquiry_messages']['config']['regionType']);
	$fields = array_keys(_test_rca_controls($d['widgets']['enquiry_form']['tree']));
	$renderer = array('reference_code', 'not_found', 'number_of_comments', 'form_item_view', 'submitted_date_and_time');
	foreach (array('enquiry_detail', 'my_enquiries_list', 'staff_enquiries_list', 'email_enquiry_admin', 'email_enquiry_client') as $key) {
		pg_assert_same('{{tab:hire}}', $d['widgets'][$key]['config']['custom_form_page_id'], "$key reads the staff request form");
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
	foreach (array('my_enquiries_list', 'staff_enquiries_list') as $key) {
		pg_assert_same('{{tab:enquiry_view}}', $d['widgets'][$key]['config']['detail_page_id'], "$key opens the staff request");
	}
	foreach ($d['widgets']['staff_enquiries_list']['config']['search_fields'] as $name) {
		pg_assert_true(in_array($name, $fields, true), "the staff search $name, a field of the form");
	}
	$json = json_encode($d['widgets']['enquiry_detail']['tree']);
	foreach (array('record_found', 'record_not_found', 'submitted_this_session', 'has_new_account', 'can_edit') as $flag) {
		pg_assert_contains('"eo_visible_if":"' . $flag . '"', $json, "the record page shows $flag");
	}
	foreach (array('email_enquiry_admin', 'email_enquiry_client') as $key) {
		pg_assert_same('public', $d['widgets'][$key]['config']['access_control'], "$key is drawn for the submission just sent");
		pg_assert_contains('{{page:enquiry_view}}?r=^^reference_code^^', json_encode($d['widgets'][$key]['tree'], JSON_UNESCAPED_SLASHES), "$key opens the record");
	}
	pg_assert_contains('"reference_code"', json_encode($d['widgets']['email_enquiry_admin']['tree']), 'the office e-mail ends with the reference');
	pg_assert_contains('"eo_visible_if":"has_new_account"', json_encode($d['widgets']['email_enquiry_client']['tree']), 'the employer e-mail names a new account');
}

function test_recruitment_employers_links_ids_and_classes()
{
	$d = _test_rce_load();
	$known = array_flip(_test_rc_page_keys());
	$trees = array();
	foreach ($d['pages'] as $p) $trees[] = $p;
	foreach ($d['widgets'] as $w) $trees[] = array($w['config'], $w['tree']);
	$json = json_encode($trees);
	preg_match_all('/\{\{(?:page|tab):([a-z0-9_]+)\}\}/', $json, $m);
	foreach (array_unique($m[1]) as $key) pg_assert_true(isset($known[$key]), "page link {{page:$key}} names a template page");
	foreach (array('product', 'cart', 'checkout', 'order', 'email_order') as $key) {
		pg_assert_false(strpos($json, '{{page:' . $key . '}}') !== false, "no tree links to $key");
	}
	$nodes = array();
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
	$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-recruitment/employers.php');
	preg_match_all("/lang\\('((?:[^'\\\\]|\\\\.)*)'\\)/", $src, $mm);
	foreach ($mm[1] as $raw) {
		pg_assert_false((bool)preg_match('/\b(19|20)[0-9]{2}\b|\b(January|February|March|April|May|June|July|August|September|October|November|December)\b/', $raw), "'$raw' names no fixed date");
	}
}

// Every literal has its Turkish; a staff request reads "personel talebi",
// an employer "işveren", a consultant "danışman", a candidate "aday".
function test_recruitment_employers_turkish_words()
{
	$map = pg_test_language_map('tr');
	$words = array(
		'/\bstaff requests?\b/i'  => 'personel tale',
		'/\bemployers?\b/i'       => 'işveren',
		'/\bconsultants?\b/i'     => 'danışman',
		'/\bcandidates?\b/i'      => 'aday',
	);
	$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-recruitment/employers.php');
	preg_match_all("/lang\\('((?:[^'\\\\]|\\\\.)*)'\\)/", $src, $m);
	foreach (array_unique($m[1]) as $raw) {
		$key = stripslashes($raw);
		pg_assert_true(isset($map[$key]) && $map[$key] !== '', "Turkish for '$key'");
		if (!isset($map[$key]) || preg_match('/^[a-z0-9-]+$/', $key)) continue;
		$value = str_replace('İ', 'i', $map[$key]);
		foreach ($words as $re => $tr) {
			if (preg_match($re, $key)) pg_assert_true(mb_stripos($value, $tr, 0, 'UTF-8') !== false, "'$key' says $tr");
		}
	}
}
