<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Theatre design template's performances
 * (includes/design_templates/pinegrap-theatre/performances.php): the pages
 * and widgets, the Performances collection whose production is a
 * reference into the Productions form, a sample per performance naming
 * its production by title and fitting its fields, What's On from today
 * filtered by the address (production and access), the past performances,
 * the home's featured ones, the production page's own performances (the
 * shown record), the production printed through the reference and never
 * as the stored code, every page link names a template page, ids and
 * classes are the template's own, and every literal has its Turkish.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The page keys (_test_th_page_keys()) and the productions' loader
// (_test_thx_load(), _test_thx_nodes(), _test_thx_controls()).
require_once __DIR__ . '/design_template_theatre_productions_test.php';

// The performances' part as the template file reads it: parts.php, the
// public pages, productions.php, then performances.php in the same scope.
function _test_thf_load()
{
	static $out = null;
	if ($out !== null) return $out;
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-theatre';
	include $dir . '/parts.php';
	$public = include $dir . '/pages_public.php';
	$productions_part = include $dir . '/productions.php';
	$part = include $dir . '/performances.php';
	$pages = array();
	foreach ($part['pages'] as $p) $pages[$p['key']] = $p;
	$production_pages = array();
	foreach ($productions_part['pages'] as $p) $production_pages[$p['key']] = $p;
	$titles = array();
	foreach ($productions as $p) $titles[] = $p['title'];
	$out = array('part' => $part, 'pages' => $pages, 'widgets' => $part['widgets'], 'performances' => $performances,
		'production_titles' => $titles, 'stages' => $stage_titles, 'access' => $access_titles, 'states' => $ticket_state_titles,
		'public' => $public, 'production_pages' => $production_pages);
	return $out;
}

function test_theatre_performances_pages_and_widgets()
{
	$d = _test_thf_load();
	pg_assert_same(array('pages', 'widgets'), array_keys($d['part']), 'the part returns pages and widgets');
	pg_assert_same(array('whats_on', 'past_performances', 'performance', 'performance_new'), array_keys($d['pages']));
	pg_assert_same(array('performance_form', 'whats_on_list', 'past_performances_list', 'home_performances', 'production_performances', 'performance_detail'),
		array_keys($d['widgets']));
	foreach (array('whats_on', 'past_performances', 'performance') as $key) pg_assert_same('public', $d['pages'][$key]['folder'], "$key is public");
	pg_assert_same('private', $d['pages']['performance_new']['folder'], 'only the staff add a performance');
	pg_assert_true(!empty($d['pages']['performance_new']['noindex']), 'the staff page is not indexed');
	foreach ($d['pages'] as $key => $p) {
		pg_assert_false(isset($p['requires']), "$key stands without the shop");
		pg_assert_same('root', $p['tree']['type'], "$key: a page tree");
		pg_assert_true(mb_strlen((string)($p['meta_description'] ?? ''), 'UTF-8') <= 160, "$key: meta description fits a result line");
	}
	foreach ($d['widgets'] as $key => $w) {
		if ($w['page'] === 'home') {
			$tree = null;
			foreach ($d['public']['pages'] as $p) if ($p['key'] === 'home') $tree = $p['tree'];
		} elseif ($w['page'] === 'production') {
			$tree = $d['production_pages']['production']['tree'];
		} else {
			$tree = $d['pages'][$w['page']]['tree'];
		}
		pg_assert_contains('"templateWidget":"' . $key . '"', json_encode($tree), "$key is placed on its page");
	}
}

// The form is a collection whose production is a reference into the
// Productions form; a sample per performance names its production by title
// (the seeding finds the code), every other pick list value is an answer.
function test_theatre_performances_form_and_samples()
{
	$d = _test_thf_load();
	$form = $d['pages']['performance_new']['form'];
	pg_assert_same(1, $form['collection'], 'a collection');
	pg_assert_same(count($d['performances']), count($form['sample_records']), 'one sample per performance');
	$controls = _test_thx_controls($d['widgets']['performance_form']['tree']);
	pg_assert_same(array('production', 'perf_date', 'perf_time', 'stage', 'access', 'ticket_state', 'ticket_url', 'featured'), array_keys($controls), 'the fields of the form');
	pg_assert_same(array('kind' => 'reference', 'ref' => '{{tab:production_new}}'), array_intersect_key($controls['production']['cf'], array('kind' => 1, 'ref' => 1)),
		'the production is a reference into Productions');
	pg_assert_same(array(), $controls['production']['options'], 'its choices are the Productions records');
	pg_assert_same($d['stages'], $controls['stage']['options'], 'the stages');
	pg_assert_same($d['access'], $controls['access']['options'], 'the access kinds');
	pg_assert_same($d['states'], $controls['ticket_state']['options'], 'the ticket states');
	pg_assert_true(!empty($controls['featured']['cf']['office_use_only']), 'featured is the theatre\'s own choice');
	$today = date('Y-m-d');
	$featured = 0;
	foreach ($form['sample_records'] as $n => $r) {
		pg_assert_true(in_array($r['production'], $d['production_titles'], true), "sample $n: names a production by title");
		pg_assert_true((bool)preg_match('/^\d{4}-\d{2}-\d{2}$/', $r['perf_date']), "sample $n: a date");
		foreach ($r as $name => $value) {
			pg_assert_true(isset($controls[$name]), "sample $n: $name is a field");
			pg_assert_true($value !== '', "sample $n: $name filled");
			if ($name !== 'production' && $controls[$name]['tag'] === 'select') {
				pg_assert_true(in_array($value, $controls[$name]['options'], true), "sample $n: $name '$value' is an answer");
			}
		}
		if (isset($r['ticket_url'])) pg_assert_true($r['perf_date'] > $today, "sample $n: only one to come links tickets");
		if ($r['featured'] === $controls['featured']['options'][0]) {
			$featured++;
			pg_assert_true($r['perf_date'] > $today, "sample $n: a featured one is to come");
		}
	}
	pg_assert_same(3, $featured, 'three featured on the home');
}

// What's On reads the date, ?production= and ?access=; the access bar sends
// the access answers and no production bar is built (codes are made at
// install); the past list, the home and the production page.
function test_theatre_performances_filters()
{
	$d = _test_thf_load();
	$f = $d['widgets']['whats_on_list']['config']['filters'];
	pg_assert_same(array('field' => 'perf_date', 'operator' => 'is greater than or equal to', 'value' => '', 'dynamic_value' => 'current date'), $f[0], 'from today');
	$params = array();
	foreach (array_slice($f, 1) as $row) {
		pg_assert_same('url parameter', $row['dynamic_value'], 'the filter reads the address');
		$params[$row['field']] = $row['dynamic_value_attribute'];
	}
	pg_assert_same(array('production' => 'production', 'access' => 'access'), $params, 'production and access from the address');
	$nodes = array();
	_test_thx_nodes($d['pages']['whats_on']['tree'], $nodes);
	$sent = array();
	foreach ($nodes as $n) {
		$href = (string)($n['props']['href'] ?? '');
		if (strpos($href, '{{page:whats_on}}?') !== 0) continue;
		parse_str(substr($href, strlen('{{page:whats_on}}?')), $q);
		pg_assert_same(array('access'), array_keys($q), 'only the access bar links a filter');
		$sent[] = $q['access'];
	}
	pg_assert_same($d['access'], $sent, 'an access link per answer');
	pg_assert_same(array(array('field' => 'perf_date', 'operator' => 'is less than', 'value' => '', 'dynamic_value' => 'current date')),
		$d['widgets']['past_performances_list']['config']['filters'], 'the past ones before today');
	pg_assert_same('DESC', $d['widgets']['past_performances_list']['config']['order_by_direction'], 'the latest played first');
	$c = $d['widgets']['home_performances']['config'];
	pg_assert_same(3, $c['max_results'], 'three at most');
	pg_assert_same('featured', $c['filters'][1]['field'], 'the featured ones');
	$c = $d['widgets']['production_performances']['config'];
	pg_assert_same(array('field' => 'production', 'operator' => 'is equal to', 'value' => '', 'dynamic_value' => 'shown record', 'dynamic_value_attribute' => ''),
		$c['filters'][0], 'the production page lists the performances of the production it shows');
	pg_assert_same('is greater than or equal to', $c['filters'][1]['operator'], 'from today');
	foreach (array('whats_on_list', 'past_performances_list', 'home_performances', 'production_performances') as $key) {
		pg_assert_same('{{tab:performance_new}}', $d['widgets'][$key]['config']['custom_form_page_id'], "$key: the Performances form");
		pg_assert_same('{{tab:performance}}', $d['widgets'][$key]['config']['detail_page_id'], "$key: the performance page");
		pg_assert_same('perf_date', $d['widgets'][$key]['config']['order_by_field'], "$key: by date");
	}
}

// The production field holds a code: the rows and the record page print
// the production's title through the reference, never the field itself;
// the record page sends the code to What's On.
function test_theatre_performances_print_the_production_through_the_reference()
{
	$d = _test_thf_load();
	$refs = 0;
	$links = array();
	foreach ($d['widgets'] as $key => $w) {
		$nodes = array();
		_test_thx_nodes($w['tree'], $nodes);
		foreach ($nodes as $n) {
			foreach ((array)($n['props']['_bindings'] ?? array()) as $prop => $token) {
				if ($prop === 'text') pg_assert_true($token !== 'production', "$key: the code is never printed");
				if ($token === '__ref') {
					pg_assert_same(array('field' => 'production', 'page_id' => '{{tab:production_new}}', 'show' => 'title'), $n['props']['_bindParams'][$prop], "$key: the production's title");
					$refs++;
				}
				if ($token === '__page_link') $links[] = $n['props']['_bindParams'][$prop];
			}
		}
	}
	pg_assert_true($refs >= 5, 'the rows and the record page read the reference');
	pg_assert_same(array(array('page_id' => '{{tab:whats_on}}', 'params' => array(array('name' => 'production', 'token' => 'production')))), $links,
		'the record page opens the production\'s other dates');
	$json = json_encode($d['widgets']['performance_detail']['tree']);
	pg_assert_contains('"eo_visible_if":"record_found"', $json, 'the record');
	pg_assert_contains('"eo_visible_if":"record_not_found"', $json, 'the not-found note');
	pg_assert_contains('"eo_visible_if":"can_edit"', $json, 'the Edit button');
	pg_assert_contains('"show_if":"ticket_url"', $json, 'the ticket button leaves without an address');
}

function test_theatre_performances_links_ids_and_classes()
{
	$d = _test_thf_load();
	$known = array_flip(_test_th_page_keys());
	$trees = array();
	foreach ($d['pages'] as $p) $trees[] = $p;
	foreach ($d['widgets'] as $w) $trees[] = array($w['config'], $w['tree']);
	$json = json_encode($trees);
	preg_match_all('/\{\{(?:page|tab):([a-z0-9_]+)\}\}/', $json, $m);
	foreach (array_unique($m[1]) as $key) pg_assert_true(isset($known[$key]), "page link {{page:$key}} names a template page");
	foreach (array('tickets', 'friends', 'product', 'cart', 'checkout', 'order') as $key) {
		pg_assert_false(strpos($json, '{{page:' . $key . '}}') !== false, "no tree links to $key");
	}
	pg_assert_false((bool)preg_match('/\b20[0-9]{2}-[01][0-9]-[0-3][0-9]\b/', file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-theatre/performances.php')),
		'no fixed date in the source');
	$nodes = array();
	foreach ($d['pages'] as $p) _test_thx_nodes($p['tree'], $nodes);
	foreach ($d['widgets'] as $w) _test_thx_nodes($w['tree'], $nodes);
	foreach ($nodes as $n) {
		foreach (preg_split('/\s+/', (string)($n['props']['cssClass'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $c) {
			pg_assert_false((bool)preg_match('/^(ptv|pht|pre|pev|pgy|pch|plw|pwd|pph|pcn|pmu|psc|psh|pmz|pai)-/', $c), "class $c is not another template's");
			pg_assert_false((bool)preg_match('/^(text-)?opacity-/', $c), "no faded text class $c");
		}
		$id = (string)($n['props']['id'] ?? '');
		if ($id !== '' && strpos($id, '-') !== false) pg_assert_true(strpos($id, 'th-') === 0, "id $id is the template's");
	}
}

// Every literal has its Turkish; a performance reads "temsil", a
// production "oyun" or "yapım".
function test_theatre_performances_turkish_words()
{
	$map = pg_test_language_map('tr');
	$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-theatre/performances.php');
	preg_match_all("/lang\\('((?:[^'\\\\]|\\\\.)*)'\\)/", $src, $m);
	foreach (array_unique($m[1]) as $raw) {
		$key = stripslashes($raw);
		pg_assert_true(isset($map[$key]) && $map[$key] !== '', "Turkish for '$key'");
		if (!isset($map[$key]) || strpos($key, '-') !== false) continue;
		$tr = $map[$key];
		if (preg_match('/\bProductions?\b/', $key)) pg_assert_true(mb_stripos($tr, 'oyun', 0, 'UTF-8') !== false || mb_stripos($tr, 'yapım', 0, 'UTF-8') !== false, "'$key' says oyun or yapım");
		if (preg_match('/\bPerformances?\b/', $key)) pg_assert_true(mb_stripos($tr, 'temsil', 0, 'UTF-8') !== false, "'$key' says temsil");
	}
}
