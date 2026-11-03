<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Web Designer Portfolio design template's work pages
 * (includes/design_templates/pinegrap-portfolio/work.php): the Projects
 * collection with its fields and kinds, a sample record per sample case
 * study whose answers are answers of the form's lists, the lists reading
 * the Projects form and filtering on its fields with URL values under names
 * a list may read, the filter links naming Category answers, the case
 * study page's brief button sending the project's kind, and every widget
 * defined on the page that places it (the home's list placed by the public
 * pages).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// parts.php, then work.php in the same scope, as the template file reads
// them. Read once per run.
function _test_pdw_load()
{
	static $out = null;
	if ($out !== null) return $out;
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-portfolio';
	include $dir . '/parts.php';
	$work_part = include $dir . '/work.php';
	$out = array(
		'projects'        => $projects,
		'categories'      => $categories,
		'category_titles' => $category_titles,
		'work_part'       => $work_part,
	);
	return $out;
}

// Every node of a tree, depth first.
function _test_pdw_nodes($node, &$out)
{
	if (!is_array($node)) return;
	if (isset($node['props'])) $out[] = $node;
	if (!empty($node['children']) && is_array($node['children'])) {
		foreach ($node['children'] as $child) _test_pdw_nodes($child, $out);
	}
}

// The widget keys a tree places.
function _test_pdw_widgets($tree)
{
	$nodes = array();
	_test_pdw_nodes($tree, $nodes);
	$out = array();
	foreach ($nodes as $node) {
		if (!empty($node['props']['templateWidget'])) $out[] = $node['props']['templateWidget'];
	}
	return $out;
}

// The controls of a widget tree by field name: tag, kind, options, hidden
// from the visitor.
function _test_pdw_controls($tree)
{
	$nodes = array();
	_test_pdw_nodes($tree, $nodes);
	$out = array();
	foreach ($nodes as $node) {
		if (!in_array($node['props']['tag'] ?? '', array('input', 'select', 'textarea'), true)) continue;
		$name = '';
		foreach ($node['props']['_attrs'] ?? array() as $a) {
			if ($a['name'] === 'name') $name = $a['value'];
		}
		if ($name === '') continue;
		$options = array();
		foreach ($node['children'] ?? array() as $o) {
			foreach ($o['props']['_attrs'] ?? array() as $a) {
				if ($a['name'] === 'value' && $a['value'] !== '') $options[] = $a['value'];
			}
		}
		$out[$name] = array(
			'tag'     => $node['props']['tag'],
			'kind'    => $node['props']['_cf']['kind'] ?? '',
			'office'  => !empty($node['props']['_cf']['office_use_only']),
			'options' => $options,
		);
	}
	return $out;
}

function _test_pdw_pages()
{
	$d = _test_pdw_load();
	$pages = array();
	foreach ($d['work_part']['pages'] as $p) $pages[$p['key']] = $p;
	return $pages;
}

function test_portfolio_work_pages_in_order()
{
	$pages = _test_pdw_pages();
	pg_assert_same(array('work', 'project', 'project_new'), array_keys($pages), 'the work pages in order');
	pg_assert_same('private', $pages['project_new']['folder'], 'the staff page is private');
	pg_assert_true(!empty($pages['project_new']['noindex']), 'the staff page is not indexed');
	pg_assert_same('public', $pages['work']['folder'], 'the work list is public');
	pg_assert_same('public', $pages['project']['folder'], 'the case study is public');
	pg_assert_same(false, $pages['project']['sitemap'], 'the record page is not in the sitemap by itself');
}

function test_portfolio_projects_form_and_samples()
{
	$d = _test_pdw_load();
	$pages = _test_pdw_pages();
	$form = $pages['project_new']['form'];
	pg_assert_same(1, $form['collection'], 'Projects is a collection');
	pg_assert_same('cover', $form['share_image_field'], 'the cover is the share image');
	$controls = _test_pdw_controls($d['work_part']['widgets']['project_form']['tree']);
	foreach (array('title', 'category', 'client', 'industry', 'year', 'role', 'summary', 'challenge', 'approach',
	               'results', 'cover', 'image_2', 'image_3', 'tools', 'url', 'featured') as $f) {
		pg_assert_true(isset($controls[$f]), "the form has $f");
	}
	pg_assert_same('number', $controls['year']['kind'], 'year is a number');
	foreach (array('cover', 'image_2', 'image_3') as $f) pg_assert_same('image', $controls[$f]['kind'], "$f is an image");
	pg_assert_same($d['category_titles'], $controls['category']['options'], 'category answers');
	pg_assert_true($controls['featured']['office'], 'featured is set in the panel');
	pg_assert_same(2, count($controls['featured']['options']), 'featured: yes or no');
	pg_assert_same(count($d['projects']), count($form['sample_records']), 'a record per sample case study');
	$featured = 0;
	foreach ($form['sample_records'] as $n => $r) {
		foreach ($r as $field => $value) pg_assert_true(isset($controls[$field]), "sample $n: $field is a field of the form");
		pg_assert_true(in_array($r['category'], $controls['category']['options'], true), "sample $n: category is an answer");
		pg_assert_true(in_array($r['featured'], $controls['featured']['options'], true), "sample $n: featured is an answer");
		pg_assert_true(ctype_digit($r['year']), "sample $n: year is a plain number");
		if ($r['featured'] === $controls['featured']['options'][0]) $featured++;
	}
	pg_assert_same(3, $featured, 'three samples featured, for the home');
	$titles = array_column($form['sample_records'], 'title');
	foreach ($d['projects'] as $key => $p) pg_assert_true(in_array($p['title'], $titles, true), "sample $key written in");
}

// Every list reads the Projects form, opens the case study, filters on the
// form's fields with URL values under names a list may read; every widget
// sits on the page that places it.
function test_portfolio_work_widgets()
{
	$d = _test_pdw_load();
	$widgets = $d['work_part']['widgets'];
	$pages = _test_pdw_pages();
	$controls = _test_pdw_controls($widgets['project_form']['tree']);
	$reserved = function_exists('pg_sw_url_param_reserved') ? pg_sw_url_param_reserved() : array();
	pg_assert_true(isset($widgets['home_projects']), 'the home\'s list is defined');
	pg_assert_same('home', $widgets['home_projects']['page'], 'the home places it');
	foreach ($widgets as $key => $w) {
		if ($w['page'] !== 'home') {
			pg_assert_true(isset($pages[$w['page']]), "$key: on a work page");
			pg_assert_true(in_array($key, _test_pdw_widgets($pages[$w['page']]['tree']), true), "$key: placed by its page");
		}
		if ($w['config']['regionType'] !== 'form_list_view') continue;
		pg_assert_same('{{tab:project_new}}', $w['config']['custom_form_page_id'], "$key: reads Projects");
		pg_assert_same('{{tab:project}}', $w['config']['detail_page_id'], "$key: opens the case study");
		foreach ($w['config']['filters'] ?? array() as $f) {
			pg_assert_true(isset($controls[$f['field']]) || $f['field'] === 'reference_code', "$key: filters on a field: " . $f['field']);
			if ($f['dynamic_value'] === 'url parameter') {
				pg_assert_true((bool)preg_match('/^[a-z][a-z0-9_]{0,31}$/', $f['dynamic_value_attribute']), "$key: parameter name");
				pg_assert_false(in_array($f['dynamic_value_attribute'], $reserved, true), "$key: parameter not reserved");
			} elseif ($f['value'] !== '' && isset($controls[$f['field']])) {
				pg_assert_true(in_array($f['value'], $controls[$f['field']]['options'], true), "$key: fixed value is an answer: " . $f['value']);
			}
		}
		foreach ($w['config']['search_fields'] ?? array() as $f) pg_assert_true(isset($controls[$f]), "$key: searches a field: $f");
	}
	$filters = $widgets['work_list']['config']['filters'];
	pg_assert_same('category', $filters[0]['dynamic_value_attribute'], 'the work list reads ?category=');
}

// The filter links of the work page name Category answers, one per kind.
function test_portfolio_work_filter_links()
{
	$d = _test_pdw_load();
	$pages = _test_pdw_pages();
	$nodes = array();
	_test_pdw_nodes($pages['work']['tree'], $nodes);
	$seen = array();
	foreach ($nodes as $node) {
		if (!isset($node['props']['href']) || strpos($node['props']['href'], '{{page:work}}?') !== 0) continue;
		parse_str(substr($node['props']['href'], strlen('{{page:work}}?')), $q);
		pg_assert_true(in_array($q['category'], $d['category_titles'], true), 'the link names a Category answer');
		$seen[$q['category']] = true;
	}
	pg_assert_same(count($d['categories']), count($seen), 'a filter link per kind of project');
}

// The case study page: the record's fields on it, and one brief button that
// sends the project's kind as ?type=.
function test_portfolio_project_page()
{
	$d = _test_pdw_load();
	$detail = $d['work_part']['widgets']['project_detail'];
	pg_assert_same('form_item_view', $detail['config']['regionType'], 'the record view');
	pg_assert_same('{{tab:project_new}}', $detail['config']['custom_form_page_id'], 'reads Projects');
	$nodes = array();
	_test_pdw_nodes($detail['tree'], $nodes);
	$tokens = array();
	$sends = 0;
	foreach ($nodes as $node) {
		foreach ($node['props']['_bindings'] ?? array() as $token) $tokens[$token] = true;
		if (($node['props']['_bindings']['href'] ?? '') !== '__page_link') continue;
		pg_assert_same('{{tab:brief}}', $node['props']['_bindParams']['href']['page_id'], 'brief link');
		pg_assert_same(array(array('name' => 'type', 'token' => 'category')), $node['props']['_bindParams']['href']['params'], 'sends the kind');
		$sends++;
	}
	pg_assert_same(1, $sends, 'one brief button');
	foreach (array('title', 'category', 'client', 'industry', 'year', 'role', 'summary', 'challenge', 'approach',
	               'results', 'cover', 'image_2', 'image_3', 'tools', 'url', 'record_found', 'record_not_found') as $t) {
		pg_assert_true(isset($tokens[$t]), "the page reads $t");
	}
}
