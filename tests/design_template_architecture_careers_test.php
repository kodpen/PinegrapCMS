<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Architecture & Interiors Studio design template's careers
 * (includes/design_templates/pinegrap-architecture/careers.php): the pages
 * and widgets, the Jobs collection with a sample per job whose values fit
 * their fields, the job page opening the application with its job
 * (?job=), every ?job= a link sends naming an answer, the application
 * opening its page and mailing both sides with the CV private and the
 * status the studio's, the record page and lists binding fields the form
 * has, every page link names a template page, ids and classes are the
 * template's own, and every literal has its Turkish.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The page keys of the finished template (_test_ai_page_keys()).
require_once(__DIR__ . '/design_template_architecture_test.php');

// The careers' part as the template file reads it: parts.php, mail.php,
// projects.php and enquiries.php, then careers.php in the same scope. Read
// once per run.
function _test_aic_load()
{
	static $out = null;
	if ($out !== null) return $out;
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-architecture';
	include $dir . '/parts.php';
	include $dir . '/mail.php';
	include $dir . '/projects.php';
	include $dir . '/enquiries.php';
	$part = include $dir . '/careers.php';
	$public = include $dir . '/pages_public.php';
	$pages = array();
	foreach ($part['pages'] as $p) $pages[$p['key']] = $p;
	$out = array('part' => $part, 'pages' => $pages, 'widgets' => $part['widgets'], 'jobs' => $jobs,
		'job_choices' => $application_job_choices, 'department_titles' => $department_titles, 'job_type_titles' => $job_type_titles,
		'statuses' => $application_statuses, 'duties_text' => $duties_text, 'public' => $public);
	return $out;
}

function _test_aic_nodes($node, &$out)
{
	if (!is_array($node)) return;
	if (isset($node['props'])) $out[] = $node;
	foreach ((array)($node['children'] ?? array()) as $c) _test_aic_nodes($c, $out);
}

// The name, options and settings of every form control of a tree.
function _test_aic_controls($node)
{
	$nodes = array();
	_test_aic_nodes($node, $nodes);
	$out = array();
	foreach ($nodes as $n) {
		$tag = $n['props']['tag'] ?? '';
		if (!in_array($tag, array('input', 'select', 'textarea'), true)) continue;
		$attrs = array();
		foreach ((array)($n['props']['_attrs'] ?? array()) as $a) $attrs[$a['name']] = $a['value'];
		$options = array();
		foreach ((array)($n['children'] ?? array()) as $o) {
			foreach ((array)($o['props']['_attrs'] ?? array()) as $a) {
				if ($a['name'] === 'value' && $a['value'] !== '') $options[] = $a['value'];
			}
		}
		$out[$attrs['name'] ?? ''] = array('tag' => $tag, 'options' => $options, 'cf' => $n['props']['_cf'] ?? array(), 'attrs' => $attrs);
	}
	return $out;
}

function test_architecture_careers_pages_and_widgets()
{
	$d = _test_aic_load();
	pg_assert_same(array('pages', 'widgets'), array_keys($d['part']), 'the part returns pages and widgets');
	pg_assert_same(array('careers', 'job', 'job_new', 'application', 'application_view', 'my_applications', 'staff_applications',
		'email_application_new', 'email_application_received'), array_keys($d['pages']));
	pg_assert_same(array('job_form', 'jobs_list', 'job_detail', 'application_form', 'application_detail', 'application_messages',
		'my_applications_list', 'staff_applications_list', 'email_application_admin', 'email_application_client'), array_keys($d['widgets']));
	foreach (array('careers', 'job', 'application') as $key) pg_assert_same('public', $d['pages'][$key]['folder'], "$key is public");
	foreach (array('application_view', 'my_applications') as $key) pg_assert_same('registration', $d['pages'][$key]['folder'], "$key is read signed in");
	foreach (array('job_new', 'staff_applications', 'email_application_new', 'email_application_received') as $key) {
		pg_assert_same('private', $d['pages'][$key]['folder'], "only the staff open $key");
	}
	foreach (array('job_new', 'email_application_new', 'email_application_received') as $key) {
		pg_assert_true(!empty($d['pages'][$key]['noindex']), "$key is not indexed");
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
	pg_assert_same('submitter_only', $d['widgets']['application_detail']['config']['access_control'], 'an application is its sender\'s');
	pg_assert_true(!empty($d['widgets']['my_applications_list']['config']['viewer_filter_submitter']), 'My Applications lists the own');
	pg_assert_true(!empty($d['widgets']['staff_applications_list']['config']['search_enabled']), 'the staff search');
	pg_assert_same('{{tab:job_new}}', $d['widgets']['jobs_list']['config']['custom_form_page_id'], 'the list reads the Jobs form');
	pg_assert_same('{{tab:job}}', $d['widgets']['jobs_list']['config']['detail_page_id'], 'a job card opens the job page');
}

// The Jobs form is a collection with a sample per job; every sample value
// has its field and is filled, a pick list's value is one of its answers.
// The job card of parts.php binds fields of this form.
function test_architecture_careers_jobs_collection()
{
	$d = _test_aic_load();
	$form = $d['pages']['job_new']['form'];
	pg_assert_same(1, $form['collection'], 'a collection');
	pg_assert_same(count($d['jobs']), count($form['sample_records']), 'one sample per job');
	$c = _test_aic_controls($d['widgets']['job_form']['tree']);
	$fields = array('title', 'department', 'type', 'location', 'summary', 'duties');
	pg_assert_same($fields, array_keys($c), 'the fields of the form');
	pg_assert_same($d['department_titles'], $c['department']['options'], 'the teams');
	pg_assert_same($d['job_type_titles'], $c['type']['options'], 'the job types');
	foreach ($form['sample_records'] as $n => $r) {
		pg_assert_same($fields, array_keys($r), "sample $n: every field");
		foreach ($r as $name => $value) {
			pg_assert_true($value !== '', "sample $n: $name filled");
			if ($c[$name]['tag'] === 'select') pg_assert_true(in_array($value, $c[$name]['options'], true), "sample $n: $name '$value' is an answer");
		}
		$key = array_keys($d['jobs'])[$n];
		pg_assert_same(call_user_func($d['duties_text'], $key), $r['duties'], "sample $n: its duties, a line per duty");
		pg_assert_true(in_array($r['title'], $d['job_choices'], true), "sample $n: its title is an answer of the application");
	}
	foreach (array('jobs_list', 'job_detail') as $key) {
		$nodes = array();
		_test_aic_nodes($d['widgets'][$key]['tree'], $nodes);
		foreach ($nodes as $n) {
			$token = (string)($n['props']['_bindings']['text'] ?? '');
			if ($token === '' || $token === 'not_found') continue;
			pg_assert_true(in_array($token, $fields, true), "$key binds $token, a field of the Jobs form");
		}
	}
}

// The job page opens the application with its title; every ?job= a link
// sends is an answer of the application's job list.
function test_architecture_careers_apply_links()
{
	$d = _test_aic_load();
	$links = array();
	$nodes = array();
	_test_aic_nodes($d['widgets']['job_detail']['tree'], $nodes);
	foreach ($nodes as $n) {
		if (($n['props']['_bindings']['href'] ?? '') === '__page_link') $links[] = $n['props']['_bindParams']['href'];
	}
	pg_assert_same(array(array('page_id' => '{{tab:application}}', 'params' => array(array('name' => 'job', 'token' => 'title')))), $links, 'the job page opens its application');
	$c = _test_aic_controls($d['widgets']['application_form']['tree']);
	pg_assert_same('job', $c['job']['cf']['url_param'] ?? '', 'the job reads ?job=');
	pg_assert_same($d['job_choices'], $c['job']['options'], 'the jobs');
	$trees = array();
	foreach ($d['pages'] as $p) $trees[] = $p['tree'];
	foreach ($d['public']['pages'] as $p) $trees[] = $p['tree'];
	$seen = 0;
	foreach ($trees as $tree) {
		$nodes = array();
		_test_aic_nodes($tree, $nodes);
		foreach ($nodes as $n) {
			$href = (string)($n['props']['href'] ?? '');
			if (strpos($href, '{{page:application}}?') !== 0) continue;
			parse_str(substr($href, strlen('{{page:application}}?')), $q);
			pg_assert_same(array('job'), array_keys($q), 'an application link sends the job');
			pg_assert_true(in_array($q['job'], $c['job']['options'], true), "?job={$q['job']} is an answer");
			$seen++;
		}
	}
	pg_assert_true($seen >= 1, 'the open application link');
}

// The application opens its page and mails both sides; the CV is private,
// the status the studio's, and the page, lists and e-mails bind its fields.
function test_architecture_careers_application()
{
	$d = _test_aic_load();
	$form = $d['pages']['application']['form'];
	pg_assert_same('{{tab:application_view}}', $form['confirmation_page_id']);
	pg_assert_same(1, $form['auto_registration']);
	pg_assert_same('{{tab:email_application_new}}', $form['notify_page_id']);
	pg_assert_same('{{tab:email_application_received}}', $form['confirm_page_id']);
	$c = _test_aic_controls($d['widgets']['application_form']['tree']);
	pg_assert_same(array('job', 'experience', 'portfolio_link', 'portfolio', 'cv', 'cover_letter', 'first_name', 'last_name', 'email', 'phone', 'status', 'consent'), array_keys($c), 'the fields');
	pg_assert_same('{{folder:private}}', $c['cv']['cf']['upload_folder_id'] ?? '', 'the CV is private');
	pg_assert_same('{{folder:private}}', $c['portfolio']['cf']['upload_folder_id'] ?? '', 'the portfolio is private');
	pg_assert_same('url', $c['portfolio_link']['attrs']['type'] ?? '', 'the portfolio link is an address');
	pg_assert_same($d['statuses'], $c['status']['options'], 'the statuses');
	pg_assert_true(!empty($c['status']['cf']['office_use_only']), 'the status is the studio\'s');
	$comments = $d['pages']['application_view']['comments'];
	pg_assert_true($comments['allow_new'] && $comments['login'], 'messages from the signed-in applicant');
	pg_assert_same('{{tab:email_reply}}', $comments['email_page']);
	$fields = array_keys($c);
	foreach (array('application_detail', 'my_applications_list', 'staff_applications_list', 'email_application_admin', 'email_application_client') as $key) {
		$nodes = array();
		_test_aic_nodes($d['widgets'][$key]['tree'], $nodes);
		foreach ($nodes as $n) {
			foreach (array('text', 'href') as $prop) {
				$token = (string)($n['props']['_bindings'][$prop] ?? '');
				if ($token === '' || strpos($token, '__') === 0 || in_array($token, array('reference_code', 'not_found', 'number_of_comments', 'submitted_date_and_time', 'form_item_view'), true)) continue;
				pg_assert_true(in_array($token, $fields, true), "$key binds $token, a field of the form");
			}
		}
	}
	foreach ($d['widgets']['staff_applications_list']['config']['search_fields'] as $name) {
		pg_assert_true(in_array($name, $fields, true), "the staff search reads $name");
	}
	foreach (array('email_application_admin', 'email_application_client') as $key) {
		pg_assert_same('public', $d['widgets'][$key]['config']['access_control'], "$key is drawn for the submission just sent");
		pg_assert_contains('{{page:application_view}}?r=^^reference_code^^', json_encode($d['widgets'][$key]['tree'], JSON_UNESCAPED_SLASHES), "$key opens the application");
	}
	foreach (array('application_detail', 'job_detail') as $key) {
		$json = json_encode($d['widgets'][$key]['tree']);
		pg_assert_contains('"eo_visible_if":"record_found"', $json, "$key: the record");
		pg_assert_contains('"eo_visible_if":"record_not_found"', $json, "$key: the not-found note");
	}
}

function test_architecture_careers_links_ids_and_classes()
{
	$d = _test_aic_load();
	$known = array_flip(_test_ai_page_keys());
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
	foreach ($d['pages'] as $p) _test_aic_nodes($p['tree'], $nodes);
	foreach ($d['widgets'] as $w) _test_aic_nodes($w['tree'], $nodes);
	foreach ($nodes as $n) {
		foreach (preg_split('/\s+/', (string)($n['props']['cssClass'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $c) {
			pg_assert_false((bool)preg_match('/^p[a-z]{2}-/', $c) && strpos($c, 'pai-') !== 0, "class $c is not another template's");
			pg_assert_false((bool)preg_match('/^rounded-(?!0$|circle$)/', $c), "square corners: $c");
			pg_assert_false((bool)preg_match('/^(text-)?opacity-/', $c), "no faded text class $c");
		}
		$id = (string)($n['props']['id'] ?? '');
		if ($id !== '' && strpos($id, '-') !== false) pg_assert_true(strpos($id, 'ai-') === 0, "id $id is the template's");
	}
}

// Every literal has its Turkish; a job opening reads "iş ilanı" and an
// application "başvuru".
function test_architecture_careers_turkish_words()
{
	$map = pg_test_language_map('tr');
	$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-architecture/careers.php');
	preg_match_all("/lang\\('((?:[^'\\\\]|\\\\.)*)'\\)/", $src, $m);
	foreach (array_unique($m[1]) as $raw) {
		$key = stripslashes($raw);
		pg_assert_true(isset($map[$key]) && $map[$key] !== '', "Turkish for '$key'");
		if (!isset($map[$key])) continue;
		$tr = $map[$key];
		$words = preg_replace('/\^\^[a-z_]+\^\^/', '', $key);
		// A page name (a lower-case word or words joined by hyphens) is a web
		// address, written without Turkish letters.
		if (strpos($words, ' ') === false && $words === strtolower($words)) continue;
		// mb_stripos() does not fold the dotted capital İ to i, so both
		// spellings are looked for.
		if (preg_match('/\bjob openings?\b/i', $words)) pg_assert_true(mb_strpos($tr, 'ilan', 0, 'UTF-8') !== false || mb_strpos($tr, 'İlan', 0, 'UTF-8') !== false, "'$key' says ilan");
		if (preg_match('/\bapplications?\b/i', $words)) pg_assert_true(mb_stripos($tr, 'başvuru', 0, 'UTF-8') !== false, "'$key' says başvuru");
	}
}
