<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Construction Firm design template's projects portfolio
 * (includes/design_templates/pinegrap-construction/projects.php): the
 * pages and widgets, the Projects collection with a sample per project
 * whose values fit their fields, the list's filters reading what the
 * filter links, the sector tiles, the service pages and the footer send,
 * the home lists the featured projects, the project page opens the quote
 * request on its service and the list on its sector, every page link names
 * a template page, ids and classes are the template's own, and every
 * literal has its Turkish.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The page keys of the finished template (_test_cn_page_keys()).
require_once(__DIR__ . '/design_template_construction_test.php');

// The projects' part as the template file reads it: parts.php first, then
// projects.php in the same scope. Read once per run.
function _test_cnj_load()
{
	static $out = null;
	if ($out !== null) return $out;
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-construction';
	include $dir . '/parts.php';
	$part = include $dir . '/projects.php';
	$public = include $dir . '/pages_public.php';
	$pages = array();
	foreach ($part['pages'] as $p) $pages[$p['key']] = $p;
	$out = array('part' => $part, 'pages' => $pages, 'widgets' => $part['widgets'], 'projects' => $projects,
		'sector_titles' => $sector_titles, 'service_titles' => $service_titles, 'status_titles' => $status_titles,
		'quote_service_choices' => $quote_service_choices, 'scope_text' => $scope_text,
		'public' => $public, 'footer' => $footer());
	return $out;
}

// Every node of a tree, depth first.
function _test_cnj_nodes($node, &$out)
{
	if (!is_array($node)) return;
	if (isset($node['props'])) $out[] = $node;
	foreach ((array)($node['children'] ?? array()) as $c) _test_cnj_nodes($c, $out);
}

// The name and options of every form control of a tree.
function _test_cnj_controls($node)
{
	$nodes = array();
	_test_cnj_nodes($node, $nodes);
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

// The query of every link to {{page:projects}} in a tree.
function _test_cnj_project_queries($tree)
{
	$nodes = array();
	_test_cnj_nodes($tree, $nodes);
	$out = array();
	$prefix = '{{page:projects}}?';
	foreach ($nodes as $n) {
		$href = (string)($n['props']['href'] ?? '');
		if (strpos($href, $prefix) !== 0) continue;
		parse_str(substr($href, strlen($prefix)), $q);
		$out[] = $q;
	}
	return $out;
}

function test_construction_projects_pages_and_widgets()
{
	$d = _test_cnj_load();
	pg_assert_same(array('pages', 'widgets'), array_keys($d['part']), 'the part returns pages and widgets');
	pg_assert_same(array('projects', 'project', 'project_new'), array_keys($d['pages']));
	pg_assert_same(array('project_form', 'projects_list', 'home_projects', 'project_detail'), array_keys($d['widgets']));
	foreach (array('projects', 'project') as $key) pg_assert_same('public', $d['pages'][$key]['folder'], "$key is public");
	pg_assert_same('private', $d['pages']['project_new']['folder'], 'only the staff add a project');
	pg_assert_true(!empty($d['pages']['project_new']['noindex']), 'the staff page is not indexed');
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
	// The home page places home_projects, which this part defines.
	$home = null;
	foreach ($d['public']['pages'] as $p) if ($p['key'] === 'home') $home = $p;
	pg_assert_contains('"templateWidget":"home_projects"', json_encode($home['tree']), 'the home places the featured projects');
	pg_assert_same('home', $d['widgets']['home_projects']['page']);
}

// The form is a collection with a sample per project; every sample value
// has its field and is filled, a pick list's value is one of its answers,
// the photos are images and featured is the staff's.
function test_construction_projects_form_and_samples()
{
	$d = _test_cnj_load();
	$form = $d['pages']['project_new']['form'];
	pg_assert_same(1, $form['collection'], 'a collection');
	pg_assert_same(count($d['projects']), count($form['sample_records']), 'one sample per project');
	$controls = _test_cnj_controls($d['widgets']['project_form']['tree']);
	$fields = array('name', 'sector', 'service', 'location', 'status', 'size', 'duration', 'client', 'summary', 'scope', 'client_words', 'photo_1', 'photo_2', 'photo_3', 'featured');
	pg_assert_same($fields, array_keys($controls), 'the fields of the form');
	foreach (array('photo_1', 'photo_2', 'photo_3') as $name) pg_assert_same('image', $controls[$name]['cf']['kind'] ?? '', "$name is an image");
	pg_assert_true(!empty($controls['featured']['cf']['office_use_only']), 'featured is the staff\'s');
	pg_assert_true(isset($controls[$form['share_image_field']]), 'the share image is a field');
	pg_assert_same($d['sector_titles'], $controls['sector']['options'], 'the sectors');
	pg_assert_same($d['service_titles'], $controls['service']['options'], 'the services');
	pg_assert_same($d['status_titles'], $controls['status']['options'], 'the statuses');
	// The record's service is an answer of the quote request's service list,
	// so the project page opens the request with it chosen.
	foreach ($d['service_titles'] as $s) pg_assert_true(in_array($s, $d['quote_service_choices'], true), "$s is a quote request answer");
	$featured = 0;
	foreach ($form['sample_records'] as $n => $r) {
		pg_assert_same($fields, array_keys($r), "sample $n: every field");
		foreach ($r as $name => $value) {
			pg_assert_true($value !== '', "sample $n: $name filled");
			if ($controls[$name]['tag'] === 'select') {
				pg_assert_true(in_array($value, $controls[$name]['options'], true), "sample $n: $name '$value' is an answer");
			}
			pg_assert_false((bool)preg_match('/\b(19|20)[0-9]{2}\b/', $value), "sample $n: $name names no year");
		}
		$key = array_keys($d['projects'])[$n];
		pg_assert_same(call_user_func($d['scope_text'], $key), $r['scope'], "sample $n: its scope, a line per item");
		if ($r['featured'] === lang('Yes')) $featured++;
	}
	pg_assert_same(3, $featured, 'three featured samples');
}

// The list reads the parameters the filter links, the sector tiles, the
// service pages and the footer send, each an answer of its pick list; the
// home lists the featured projects, three at most.
function test_construction_projects_filters()
{
	$d = _test_cnj_load();
	$params = array();
	foreach ($d['widgets']['projects_list']['config']['filters'] as $f) {
		pg_assert_same('url parameter', $f['dynamic_value'], 'the filter reads the address');
		$params[$f['field']] = $f['dynamic_value_attribute'];
	}
	pg_assert_same(array('sector' => 'sector', 'service' => 'service'), $params, 'the list filters sector and service');
	$answers = array('sector' => $d['sector_titles'], 'service' => $d['service_titles']);
	$trees = array($d['pages']['projects']['tree'], $d['footer']);
	foreach ($d['public']['pages'] as $p) $trees[] = $p['tree'];
	$seen = array('sector' => 0, 'service' => 0);
	foreach ($trees as $tree) {
		foreach (_test_cnj_project_queries($tree) as $q) {
			foreach ($q as $param => $value) {
				pg_assert_true(isset($answers[$param]), "?$param is a filter of the list");
				if (!isset($answers[$param])) continue;
				pg_assert_true(in_array($value, $answers[$param], true), "?$param=$value is an answer");
				$seen[$param]++;
			}
		}
	}
	pg_assert_true($seen['sector'] >= count($d['sector_titles']) * 2, 'sector links');
	pg_assert_true($seen['service'] >= count($d['service_titles']) * 2, 'service links');
	$c = $d['widgets']['home_projects']['config'];
	pg_assert_same(array(array('field' => 'featured', 'operator' => 'is equal to', 'value' => lang('Yes'), 'dynamic_value' => '')), $c['filters'], 'the featured ones');
	pg_assert_same(3, $c['max_results'], 'three at most');
	foreach (array('projects_list', 'home_projects') as $key) {
		pg_assert_same('{{tab:project_new}}', $d['widgets'][$key]['config']['custom_form_page_id'], "$key: the Projects form");
		pg_assert_same('{{tab:project}}', $d['widgets'][$key]['config']['detail_page_id'], "$key: the project page");
	}
}

// The project page shows the record or the not-found note, opens the quote
// request on its service and the list on its sector.
function test_construction_projects_record_page()
{
	$d = _test_cnj_load();
	$nodes = array();
	_test_cnj_nodes($d['widgets']['project_detail']['tree'], $nodes);
	$links = array();
	$bound = array();
	foreach ($nodes as $n) {
		if (($n['props']['_bindings']['href'] ?? '') === '__page_link') $links[] = $n['props']['_bindParams']['href'];
		foreach (array('text', 'src') as $k) if (isset($n['props']['_bindings'][$k])) $bound[] = $n['props']['_bindings'][$k];
	}
	pg_assert_same(array(
		array('page_id' => '{{tab:quote}}', 'params' => array(array('name' => 'service', 'token' => 'service'))),
		array('page_id' => '{{tab:projects}}', 'params' => array(array('name' => 'sector', 'token' => 'sector'))),
	), $links, 'the quote request on its service, the list on its sector');
	foreach (array('name', 'sector', 'service', 'location', 'size', 'duration', 'status', 'client', 'summary', 'scope', 'client_words', 'photo_1', 'photo_2', 'photo_3') as $f) {
		pg_assert_true(in_array($f, $bound, true), "the page shows $f");
	}
	$json = json_encode($d['widgets']['project_detail']['tree']);
	pg_assert_contains('"eo_visible_if":"record_found"', $json, 'the record');
	pg_assert_contains('"eo_visible_if":"record_not_found"', $json, 'the not-found note');
	pg_assert_contains('"eo_visible_if":"can_edit"', $json, 'the Edit button');
}

function test_construction_projects_links_ids_and_classes()
{
	$d = _test_cnj_load();
	$known = array_flip(_test_cn_page_keys());
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
	foreach ($d['pages'] as $p) _test_cnj_nodes($p['tree'], $nodes);
	foreach ($d['widgets'] as $w) _test_cnj_nodes($w['tree'], $nodes);
	foreach ($nodes as $n) {
		foreach (preg_split('/\s+/', (string)($n['props']['cssClass'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $c) {
			pg_assert_false((bool)preg_match('/^(ptv|pht|pre|pev|pgy|pch|plw|pwd|pph)-/', $c), "class $c is not another template's");
			pg_assert_false((bool)preg_match('/^(text-)?opacity-/', $c), "no faded text class $c");
		}
		$id = (string)($n['props']['id'] ?? '');
		if ($id !== '' && strpos($id, '-') !== false) pg_assert_true(strpos($id, 'cn-') === 0, "id $id is the template's");
	}
}

// Every literal has its Turkish; a project reads "proje" and a quote
// "teklif".
function test_construction_projects_turkish_words()
{
	$map = pg_test_language_map('tr');
	$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-construction/projects.php');
	preg_match_all("/lang\\('((?:[^'\\\\]|\\\\.)*)'\\)/", $src, $m);
	foreach (array_unique($m[1]) as $raw) {
		$key = stripslashes($raw);
		pg_assert_true(isset($map[$key]) && $map[$key] !== '', "Turkish for '$key'");
		if (!isset($map[$key])) continue;
		$tr = $map[$key];
		if (preg_match('/\bProjects?\b/i', $key) && strpos($key, '-') === false) pg_assert_true(mb_stripos($tr, 'proje', 0, 'UTF-8') !== false, "'$key' says proje");
		if (preg_match('/\bquot(e|es|ed)\b/i', $key)) pg_assert_true(mb_stripos($tr, 'teklif', 0, 'UTF-8') !== false, "'$key' says teklif");
	}
}
