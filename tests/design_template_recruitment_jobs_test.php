<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Recruitment Agency design template's job listings
 * (includes/design_templates/pinegrap-recruitment/jobs.php): the pages and
 * widgets, the Jobs collection with a sample per job whose pick-list
 * values are answers, the list filters reading ?sector=, ?city= and
 * ?type= in the words every link sends, the featured jobs on the home,
 * the job page opening the application with the job chosen and the list
 * on its sector, links, ids and classes, and the Turkish of every literal.
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

// The jobs' part as the template file reads it: parts.php first, then
// jobs.php in the same scope. Read once per run.
function _test_rcj_load()
{
	static $out = null;
	if ($out !== null) return $out;
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-recruitment';
	include $dir . '/parts.php';
	$part = include $dir . '/jobs.php';
	$public = include $dir . '/pages_public.php';
	$pages = array();
	foreach ($part['pages'] as $p) $pages[$p['key']] = $p;
	$out = array('part' => $part, 'pages' => $pages, 'widgets' => $part['widgets'], 'jobs' => $jobs,
		'sector_titles' => $sector_titles, 'city_titles' => $city_titles, 'contract_titles' => $contract_titles,
		'mode_titles' => $mode_titles, 'consultant_names' => $consultant_names, 'job_titles' => $job_titles,
		'application_job_choices' => $application_job_choices, 'duties_text' => $duties_text,
		'requirements_text' => $requirements_text, 'public' => $public, 'footer' => $footer());
	return $out;
}

// Every node of a tree, depth first.
function _test_rcj_nodes($node, &$out)
{
	if (!is_array($node)) return;
	if (isset($node['props'])) $out[] = $node;
	foreach ((array)($node['children'] ?? array()) as $c) _test_rcj_nodes($c, $out);
}

// The name and options of every form control of a tree.
function _test_rcj_controls($node)
{
	$nodes = array();
	_test_rcj_nodes($node, $nodes);
	$out = array();
	foreach ($nodes as $n) {
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
		$out[$name] = array('tag' => $tag, 'options' => $options, 'cf' => $n['props']['_cf'] ?? array());
	}
	return $out;
}

// The query of every link to {{page:jobs}} in a tree.
function _test_rcj_job_queries($tree)
{
	$nodes = array();
	_test_rcj_nodes($tree, $nodes);
	$out = array();
	$prefix = '{{page:jobs}}?';
	foreach ($nodes as $n) {
		$href = (string)($n['props']['href'] ?? '');
		if (strpos($href, $prefix) !== 0) continue;
		parse_str(substr($href, strlen($prefix)), $q);
		$out[] = $q;
	}
	return $out;
}

function test_recruitment_jobs_pages_and_widgets()
{
	$d = _test_rcj_load();
	pg_assert_same(array('pages', 'widgets'), array_keys($d['part']), 'the part returns pages and widgets');
	pg_assert_same(array('jobs', 'job', 'job_new'), array_keys($d['pages']));
	pg_assert_same(array('job_form', 'jobs_list', 'home_jobs', 'job_detail'), array_keys($d['widgets']));
	foreach (array('jobs', 'job') as $key) pg_assert_same('public', $d['pages'][$key]['folder'], "$key is public");
	pg_assert_same('private', $d['pages']['job_new']['folder'], 'only the staff add a job');
	pg_assert_true(!empty($d['pages']['job_new']['noindex']), 'the staff page is not indexed');
	foreach ($d['pages'] as $key => $p) {
		pg_assert_false(isset($p['requires']), "$key stands without the shop");
		pg_assert_same('root', $p['tree']['type'], "$key: a page tree");
		pg_assert_true(mb_strlen((string)($p['meta_description'] ?? ''), 'UTF-8') <= 160, "$key: meta description fits a result line");
	}
	foreach ($d['widgets'] as $key => $w) {
		pg_assert_true($w['page'] === 'home' || isset($d['pages'][$w['page']]), "$key sits on a page of this part or the home");
		if ($w['page'] !== 'home') {
			pg_assert_contains('"templateWidget":"' . $key . '"', json_encode($d['pages'][$w['page']]['tree']), "$key is placed on its page");
		}
	}
	$home = null;
	foreach ($d['public']['pages'] as $p) if ($p['key'] === 'home') $home = $p;
	pg_assert_contains('"templateWidget":"home_jobs"', json_encode($home['tree']), 'the home places the featured jobs');
	pg_assert_same('home', $d['widgets']['home_jobs']['page']);
}

// The form is a collection with a sample per job; every sample value has
// its field and is filled, a pick list's value is one of its answers and
// featured is the staff's.
function test_recruitment_jobs_form_and_samples()
{
	$d = _test_rcj_load();
	$form = $d['pages']['job_new']['form'];
	pg_assert_same(1, $form['collection'], 'a collection');
	pg_assert_same(count($d['jobs']), count($form['sample_records']), 'one sample per job');
	$controls = _test_rcj_controls($d['widgets']['job_form']['tree']);
	$fields = array('title', 'sector', 'city', 'contract_type', 'work_mode', 'salary', 'employer', 'consultant', 'summary', 'duties', 'requirements', 'featured');
	pg_assert_same($fields, array_keys($controls), 'the fields of the form');
	pg_assert_true(!empty($controls['featured']['cf']['office_use_only']), 'featured is the staff\'s');
	pg_assert_same($d['sector_titles'], $controls['sector']['options'], 'the sectors');
	pg_assert_same($d['city_titles'], $controls['city']['options'], 'the cities');
	pg_assert_same($d['contract_titles'], $controls['contract_type']['options'], 'the contract types');
	pg_assert_same($d['mode_titles'], $controls['work_mode']['options'], 'the work modes');
	pg_assert_same($d['consultant_names'], $controls['consultant']['options'], 'the consultants');
	// A sample job's title is an answer of the application's job list, so
	// the job page opens the application with it chosen.
	foreach ($d['job_titles'] as $t) pg_assert_true(in_array($t, $d['application_job_choices'], true), "$t is an application answer");
	$sample_fields = array('title', 'sector', 'city', 'contract_type', 'work_mode', 'salary', 'employer', 'summary', 'duties', 'requirements', 'consultant', 'featured');
	$featured = 0;
	foreach ($form['sample_records'] as $n => $r) {
		pg_assert_same($sample_fields, array_keys($r), "sample $n: every field");
		foreach ($r as $name => $value) {
			pg_assert_true($value !== '', "sample $n: $name filled");
			if ($controls[$name]['tag'] === 'select') {
				pg_assert_true(in_array($value, $controls[$name]['options'], true), "sample $n: $name '$value' is an answer");
			}
			pg_assert_false((bool)preg_match('/\b(19|20)[0-9]{2}\b/', $value), "sample $n: $name names no year");
		}
		$key = array_keys($d['jobs'])[$n];
		pg_assert_same(call_user_func($d['duties_text'], $key), $r['duties'], "sample $n: its duties, a line per item");
		pg_assert_same(call_user_func($d['requirements_text'], $key), $r['requirements'], "sample $n: its requirements, a line per item");
		if ($r['featured'] === lang('Yes')) $featured++;
	}
	pg_assert_same(3, $featured, 'three featured samples');
}

// The list reads the parameters the filter links, the sector and city
// cards, the sector pages and the footer send, each an answer of its pick
// list; the home lists the featured jobs, three at most.
function test_recruitment_jobs_filters()
{
	$d = _test_rcj_load();
	$params = array();
	foreach ($d['widgets']['jobs_list']['config']['filters'] as $f) {
		pg_assert_same('url parameter', $f['dynamic_value'], 'the filter reads the address');
		$params[$f['field']] = $f['dynamic_value_attribute'];
	}
	pg_assert_same(array('sector' => 'sector', 'city' => 'city', 'contract_type' => 'type'), $params, 'the list filters sector, city and contract type');
	$answers = array('sector' => $d['sector_titles'], 'city' => $d['city_titles'], 'type' => $d['contract_titles']);
	$trees = array($d['pages']['jobs']['tree'], $d['footer']);
	foreach ($d['public']['pages'] as $p) $trees[] = $p['tree'];
	$seen = array('sector' => 0, 'city' => 0, 'type' => 0);
	foreach ($trees as $tree) {
		foreach (_test_rcj_job_queries($tree) as $q) {
			foreach ($q as $param => $value) {
				pg_assert_true(isset($answers[$param]), "?$param is a filter of the list");
				if (!isset($answers[$param])) continue;
				pg_assert_true(in_array($value, $answers[$param], true), "?$param=$value is an answer");
				$seen[$param]++;
			}
		}
	}
	pg_assert_true($seen['sector'] >= count($d['sector_titles']) * 2, 'sector links');
	pg_assert_true($seen['city'] >= count($d['city_titles']) * 2, 'city links');
	pg_assert_same(count($d['contract_titles']), $seen['type'], 'a link per contract type');
	$c = $d['widgets']['home_jobs']['config'];
	pg_assert_same(array(array('field' => 'featured', 'operator' => 'is equal to', 'value' => lang('Yes'), 'dynamic_value' => '')), $c['filters'], 'the featured ones');
	pg_assert_same(3, $c['max_results'], 'three at most');
	foreach (array('jobs_list', 'home_jobs') as $key) {
		pg_assert_same('{{tab:job_new}}', $d['widgets'][$key]['config']['custom_form_page_id'], "$key: the Jobs form");
		pg_assert_same('{{tab:job}}', $d['widgets'][$key]['config']['detail_page_id'], "$key: the job page");
		pg_assert_same('submitted_date_and_time', $d['widgets'][$key]['config']['order_by_field'], "$key: the newest first");
	}
}

// The job page shows the record or the not-found note, opens the
// application on its title and the list on its sector.
function test_recruitment_jobs_record_page()
{
	$d = _test_rcj_load();
	$nodes = array();
	_test_rcj_nodes($d['widgets']['job_detail']['tree'], $nodes);
	$links = array();
	$bound = array();
	foreach ($nodes as $n) {
		if (($n['props']['_bindings']['href'] ?? '') === '__page_link') $links[] = $n['props']['_bindParams']['href'];
		if (isset($n['props']['_bindings']['text'])) $bound[] = $n['props']['_bindings']['text'];
	}
	pg_assert_same(array(
		array('page_id' => '{{tab:apply}}', 'params' => array(array('name' => 'job', 'token' => 'title'))),
		array('page_id' => '{{tab:jobs}}', 'params' => array(array('name' => 'sector', 'token' => 'sector'))),
	), $links, 'the application on its title, the list on its sector');
	foreach (array('title', 'sector', 'city', 'contract_type', 'work_mode', 'salary', 'employer', 'summary', 'duties', 'requirements', 'consultant') as $f) {
		pg_assert_true(in_array($f, $bound, true), "the page shows $f");
	}
	$json = json_encode($d['widgets']['job_detail']['tree']);
	pg_assert_contains('"eo_visible_if":"record_found"', $json, 'the record');
	pg_assert_contains('"eo_visible_if":"record_not_found"', $json, 'the not-found note');
	pg_assert_contains('"eo_visible_if":"can_edit"', $json, 'the Edit button');
}

function test_recruitment_jobs_links_ids_and_classes()
{
	$d = _test_rcj_load();
	$known = array_flip(_test_rc_page_keys());
	$trees = array();
	foreach ($d['pages'] as $p) $trees[] = $p;
	foreach ($d['widgets'] as $w) $trees[] = array($w['config'], $w['tree']);
	$json = json_encode($trees);
	preg_match_all('/\{\{(?:page|tab):([a-z0-9_]+)\}\}/', $json, $m);
	foreach (array_unique($m[1]) as $key) pg_assert_true(isset($known[$key]), "page link {{page:$key}} names a template page");
	foreach (array('product', 'cart', 'checkout', 'order') as $key) {
		pg_assert_false(strpos($json, '{{page:' . $key . '}}') !== false, "no tree links to $key");
	}
	$nodes = array();
	foreach ($d['pages'] as $p) _test_rcj_nodes($p['tree'], $nodes);
	foreach ($d['widgets'] as $w) _test_rcj_nodes($w['tree'], $nodes);
	foreach ($nodes as $n) {
		foreach (preg_split('/\s+/', (string)($n['props']['cssClass'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $c) {
			pg_assert_false((bool)preg_match('/^(pht|pre|pev|pf|pdp|pm|pma|plx|pdl|plw|psl|pco|pcl|pgy|pch|ptv|pph|pwd|pcn|pmu|psh|pmz|pai)-/', $c), "class $c is not another template's");
			pg_assert_false((bool)preg_match('/^(text-)?opacity-/', $c), "no faded text class $c");
		}
		$id = (string)($n['props']['id'] ?? '');
		if ($id !== '' && strpos($id, '-') !== false) pg_assert_true(strpos($id, 'rc-') === 0, "id $id is the template's");
	}
}

// Every literal has its Turkish; an application reads "başvuru", a
// candidate "aday", an employer "işveren", a consultant "danışman".
function test_recruitment_jobs_turkish_words()
{
	$map = pg_test_language_map('tr');
	$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-recruitment/jobs.php');
	preg_match_all("/lang\\('((?:[^'\\\\]|\\\\.)*)'\\)/", $src, $m);
	$words = array(
		'/\bapplications?\b/i' => 'başvuru',
		'/\bcandidates?\b/i'   => 'aday',
		'/\bemployers?\b/i'    => 'işveren',
		'/\bconsultants?\b/i'  => 'danışman',
	);
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
