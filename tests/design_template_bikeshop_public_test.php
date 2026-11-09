<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Bike Shop design template's public pages
 * (includes/design_templates/pinegrap-bikeshop/pages_public.php): the
 * pages of the plan in the public folder and without the shop, links that
 * name template pages, a shop page only from the home and only behind the
 * shop switch, anchors on their pages, the widgets only the home places,
 * the Bike Shop palette blocks drawn with their classes and rules, prices
 * from the one price table, request links in the lists' own words, the template's ids and
 * classes, heading levels, no fixed dates and every literal in Turkish.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(__DIR__ . '/design_template_bikeshop_test.php');

// parts.php, cards.php and the public pages in one scope, as the template
// file reads them. Read once per run.
function _test_bikeshopp_load()
{
	static $out = null;
	if ($out !== null) return $out;
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-bikeshop';
	include $dir . '/parts.php';
	include $dir . '/cards.php';
	$public_part = include $dir . '/pages_public.php';
	$out = array('part' => $public_part, 'job_kinds' => $job_kinds, 'job_prices' => $job_prices, 'fit_kinds' => $fit_kinds,
		'fit_prices' => $fit_prices, 'frame_sizes' => $frame_sizes, 'products' => $products, 'product_prices' => $product_prices,
		'people' => $people, 'brands' => $brands, 'money' => $money, 'paces' => $paces,
		'lists' => array('job_choices' => $job_choices, 'bike_titles' => $bike_titles, 'fit_titles' => $fit_titles));
	return $out;
}

function _test_bikeshopp_pages()
{
	$pages = array();
	foreach (_test_bikeshopp_load()['part']['pages'] as $p) $pages[$p['key']] = $p;
	return $pages;
}

function _test_bikeshopp_nodes($tree)
{
	$nodes = array();
	_test_bikeshop_nodes($tree, $nodes);
	return $nodes;
}

// The nodes of a tree carrying the given class.
function _test_bikeshopp_with_class($tree, $class)
{
	$out = array();
	foreach (_test_bikeshopp_nodes($tree) as $n) {
		if (in_array($class, preg_split('/\s+/', (string)($n['props']['cssClass'] ?? '')), true)) $out[] = $n;
	}
	return $out;
}

// The node of a tree with the given id.
function _test_bikeshopp_section($tree, $id)
{
	foreach (_test_bikeshopp_nodes($tree) as $n) if (($n['props']['id'] ?? '') === $id) return $n;
	return array();
}

// The links of a tree that open $page with a query, as name => values.
function _test_bikeshopp_queries($tree, $page)
{
	$prefix = '{{page:' . $page . '}}?';
	$out = array();
	foreach (_test_bikeshopp_nodes($tree) as $node) {
		if (!isset($node['props']['href']) || strpos($node['props']['href'], $prefix) !== 0) continue;
		parse_str(substr($node['props']['href'], strlen($prefix)), $q);
		foreach ($q as $name => $value) $out[$name][] = $value;
	}
	return $out;
}

function test_bikeshop_public_pages_are_the_plans()
{
	$part = _test_bikeshopp_load()['part'];
	pg_assert_same(array('pages', 'widgets'), array_keys($part), 'the part returns pages and widgets');
	pg_assert_same(array('home', 'workshop', 'sizes', 'fit', 'about'), array_keys(_test_bikeshopp_pages()), 'the public pages');
	$known = array_flip(_test_bikeshop_page_keys());
	$names = array();
	foreach (_test_bikeshopp_pages() as $key => $p) {
		pg_assert_true(isset($known[$key]), "$key is a planned page");
		foreach (array('name', 'folder', 'title', 'meta_description', 'tree') as $field) {
			pg_assert_true(isset($p[$field]) && $p[$field] !== '', "$key has $field");
		}
		pg_assert_same('public', $p['folder'], "$key is in the public folder");
		pg_assert_false(isset($p['requires']), "$key stands without the shop");
		pg_assert_true(mb_strlen($p['meta_description'], 'UTF-8') <= 160, "$key: meta description fits a result line");
		pg_assert_true((bool)preg_match('/^[a-z0-9-]+$/', $p['name']), "$key: the page name is an address");
		pg_assert_false(isset($names[$p['name']]), "$key: no page name twice");
		$names[$p['name']] = true;
		$json = json_encode($p['tree']);
		foreach (array('account_bar', 'header', 'footer') as $shared) pg_assert_contains('"templateShared":"' . $shared . '"', $json, "$key: the $shared");
	}
}

// Links name template pages; only the home links a shop page, the
// catalog's own, and only while the shop is on (bikes and parts lead to the
// sizes page otherwise); an anchor a link names is a section of the page
// it opens.
function test_bikeshop_public_links_name_template_pages()
{
	$known = array_flip(_test_bikeshop_page_keys());
	$json = json_encode(_test_bikeshopp_pages());
	preg_match_all('/\{\{(?:page|tab):([a-z0-9_]+)\}\}/', $json, $m);
	pg_assert_true(count(array_unique($m[1])) > 10, 'links found');
	foreach (array_unique($m[1]) as $key) pg_assert_true(isset($known[$key]), "page link {{page:$key}} names a template page");
	foreach (_test_bikeshopp_pages() as $page_key => $p) {
		$page_json = json_encode($p['tree']);
		foreach (_test_bikeshop_shop_keys() as $key) {
			if ($page_key === 'home' && $key === 'shop') continue;
			pg_assert_false(strpos($page_json, '{{page:' . $key . '}}') !== false, "$page_key does not link $key");
		}
		preg_match_all('/"href":"(?:\{\{page:([a-z_]+)\}\})?#([a-z0-9-]+)"/', $page_json, $a, PREG_SET_ORDER);
		foreach ($a as $hit) {
			$target = $hit[1] !== '' ? $hit[1] : $page_key;
			pg_assert_contains('"id":"' . $hit[2] . '"', json_encode(_test_bikeshopp_pages()[$target]['tree']), "$page_key: #{$hit[2]} is on $target");
		}
	}
	pg_assert_contains('{{page:sizes}}', json_encode(_test_bikeshopp_pages()['home']['tree']), 'the bikes lead to the sizes page');
	// The source leads the home to the shop only behind the switch.
	$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-bikeshop/pages_public.php');
	pg_assert_contains('$shop_on = defined(\'ECOMMERCE\') && ECOMMERCE;', $src, 'the shop switch');
	preg_match_all('/\$shop_on \?[^\n]*\{\{page:shop\}\}|if \(\$shop_on\)[^\n]*\{\{page:shop\}\}/', $src, $guarded);
	preg_match_all('/\{\{page:shop\}\}/', $src, $all);
	pg_assert_same(count($all[0]), count($guarded[0]), 'every shop link is behind the switch');
}

function test_bikeshop_public_home_widgets()
{
	pg_assert_same(array(), _test_bikeshopp_load()['part']['widgets'], 'no widget of its own');
	$widgets = array();
	foreach (_test_bikeshopp_pages() as $key => $p) {
		foreach (_test_bikeshopp_nodes($p['tree']) as $n) {
			if (isset($n['props']['templateWidget'])) $widgets[$key][] = $n['props']['templateWidget'];
		}
	}
	pg_assert_same(array('home' => array('home_rides', 'home_posts')), $widgets, 'only the home places widgets');
}

// The pages place the palette blocks: the workshop board's load scale on
// the home and the workshop page with this week's level current, the jobs
// with their turnarounds, the size chart with a row per frame size and its
// keyed heads, the fit points numbered with the fit sheet on the fit page;
// every pg- class is a rule of the family, and every entrance one the
// interaction normalizer keeps.
function test_bikeshop_public_places_the_palette_blocks()
{
	$d = _test_bikeshopp_load();
	$pages = _test_bikeshopp_pages();
	foreach (array('home', 'workshop') as $key) {
		$scales = array();
		foreach (_test_bikeshopp_nodes($pages[$key]['tree']) as $n) if (($n['props']['_label'] ?? '') === lang('Load Scale')) $scales[] = $n;
		pg_assert_same(1, count($scales), "$key: the load scale");
		pg_assert_same(4, count($scales[0]['children']), "$key: four load levels");
		$current = 0;
		foreach ($scales[0]['children'] as $level) if (strpos(json_encode($level), '"aria-current"') !== false) $current++;
		pg_assert_same(1, $current, "$key: one level is this week's");
	}
	$workshop = json_encode($pages['workshop']['tree'], JSON_UNESCAPED_UNICODE);
	foreach ($d['job_kinds'] as $j) {
		pg_assert_contains(json_encode($j['turnaround'], JSON_UNESCAPED_UNICODE), $workshop, 'a turnaround');
		pg_assert_contains(json_encode($j['summary'], JSON_UNESCAPED_UNICODE), $workshop, 'a job on the board');
	}
	$sizes = $pages['sizes']['tree'];
	$rows = 0;
	foreach (_test_bikeshopp_nodes($sizes) as $n) if (($n['props']['_label'] ?? '') === lang('Size Row')) $rows++;
	pg_assert_same(count($d['frame_sizes']), $rows, 'a row per frame size');
	pg_assert_same(6, count(_test_bikeshopp_with_class(_test_bikeshopp_section($sizes, 'bikeshop-sizes-chart'), 'pg-bike-key')), 'four keyed heads and two keyed measures');
	foreach ($d['frame_sizes'] as $riders) pg_assert_contains(json_encode($riders, JSON_UNESCAPED_UNICODE), json_encode($sizes, JSON_UNESCAPED_UNICODE), 'rider heights');
	pg_assert_same(5, count(_test_bikeshopp_with_class(_test_bikeshopp_section($pages['fit']['tree'], 'bikeshop-fit-points'), 'pg-bike-key')), 'five fit points');
	pg_assert_same(5, count(_test_bikeshopp_with_class(_test_bikeshopp_section($pages['home']['tree'], 'bikeshop-home-fit'), 'pg-bike-key')), 'the fit points on the home');
	pg_assert_contains('"_label":"' . lang('Fit Sheet') . '"', json_encode($pages['fit']['tree'], JSON_UNESCAPED_UNICODE), 'the fit sheet on the fit page');
	pg_assert_false(strpos(json_encode($pages['home']['tree'], JSON_UNESCAPED_UNICODE), '"_label":"' . lang('Fit Sheet') . '"') !== false, 'no fit sheet on the home');
	$rules = _pg_designer_block_css_parse((string)file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer_blocks_bikeshop.js'));
	preg_match_all('/\bpg-[a-z0-9-]+/', json_encode($pages), $m);
	foreach (array_unique($m[0]) as $class) pg_assert_true(isset($rules['rules'][$class]), "$class is a rule of the Bike Shop family");
	$fx = array('slide-up' => 0, 'fade' => 0);
	foreach ($pages as $key => $p) {
		foreach (_test_bikeshopp_nodes($p['tree']) as $n) {
			if (!isset($n['props']['_fx'])) continue;
			$kept = pg_fx_normalize($n['props']['_fx']);
			pg_assert_true(is_array($kept) && isset($fx[$kept['effect']]) && $kept['trigger'] === 'view', "$key: an entrance on view the normalizer keeps");
			$fx[$kept['effect']]++;
		}
	}
	pg_assert_true($fx['slide-up'] >= 6, 'lists rise on view');
	pg_assert_same(2, $fx['fade'], 'the fit photo fades in, on the home and the fit page');
}

// The prices are the one table's: the jobs' "from" prices on the workshop
// page, the fit packages on the fit page, the featured bikes and each
// group's lowest price on the home.
function test_bikeshop_public_prices_from_the_table()
{
	$d = _test_bikeshopp_load();
	$pages = _test_bikeshopp_pages();
	$workshop = json_encode($pages['workshop']['tree'], JSON_UNESCAPED_UNICODE);
	foreach ($d['job_prices'] as $prices) pg_assert_contains(json_encode(sprintf(lang('From %s'), $d['money']($prices)), JSON_UNESCAPED_UNICODE), $workshop, 'a job price');
	$fit = json_encode($pages['fit']['tree'], JSON_UNESCAPED_UNICODE);
	foreach ($d['fit_prices'] as $prices) pg_assert_contains(json_encode($d['money']($prices), JSON_UNESCAPED_UNICODE), $fit, 'a fit price');
	$apart = 0;
	foreach (_test_bikeshopp_with_class($pages['fit']['tree'], 'border-2') as $n) if (strpos($n['props']['cssClass'], 'border-primary') !== false) $apart++;
	pg_assert_same(1, $apart, 'one fit package set apart');
	$home = json_encode($pages['home']['tree'], JSON_UNESCAPED_UNICODE);
	$featured = 0;
	foreach ($d['products'] as $key => $p) {
		if ($p['group'] !== 'bikes') continue;
		$has = strpos($home, json_encode($p['title'], JSON_UNESCAPED_UNICODE)) !== false;
		pg_assert_same(!empty($p['featured']), $has, "bike $key on the home only when featured");
		if ($has) {
			$featured++;
			pg_assert_contains(json_encode($d['money']($d['product_prices'][$key]), JSON_UNESCAPED_UNICODE), $home, "bike $key: its price");
		}
	}
	pg_assert_true($featured >= 2, 'the newest bikes');
	pg_assert_contains(json_encode(sprintf(lang('From %s'), $d['money']($d['product_prices']['tube'])), JSON_UNESCAPED_UNICODE), $home, 'the parts from their lowest price');
}

// Request links use the lists' own answers: every bike kind on the
// workshop page, every fit package on the fit page.
function test_bikeshop_public_links_use_the_answers()
{
	$d = _test_bikeshopp_load();
	foreach (_test_bikeshopp_pages() as $key => $p) {
		$q = _test_bikeshopp_queries($p['tree'], 'service_request');
		foreach ($q['job'] ?? array() as $v) pg_assert_true(in_array($v, $d['lists']['job_choices'], true), "$key: a job answer");
		foreach ($q['bike'] ?? array() as $v) pg_assert_true(in_array($v, $d['lists']['bike_titles'], true), "$key: a bike answer");
		$q = _test_bikeshopp_queries($p['tree'], 'fit_request');
		foreach ($q['fit'] ?? array() as $v) pg_assert_true(in_array($v, $d['lists']['fit_titles'], true), "$key: a fit answer");
	}
	$pages = _test_bikeshopp_pages();
	pg_assert_same($d['lists']['bike_titles'], _test_bikeshopp_queries($pages['workshop']['tree'], 'service_request')['bike'], 'the workshop links every bike kind');
	pg_assert_same($d['lists']['fit_titles'], _test_bikeshopp_queries($pages['fit']['tree'], 'fit_request')['fit'], 'the fit page links every package');
	pg_assert_contains('{{page:service_request}}', json_encode($pages['home']['tree']), 'the home asks for a service');
}

function test_bikeshop_public_ids_and_classes()
{
	$ids = array();
	foreach (_test_bikeshopp_pages() as $key => $p) {
		foreach (_test_bikeshopp_nodes($p['tree']) as $n) {
			foreach (preg_split('/\s+/', (string)($n['props']['cssClass'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $c) {
				pg_assert_false((bool)preg_match('/^(pzo|psk|prx|pmz|pph|pvt|pre|pev|pgl|pmn|prt|plb|pbk|pbw|sk)-/', $c), "$key: class $c is not another template's");
				pg_assert_false((bool)preg_match('/^[pm][trblxy]?(-[a-z]{2,3})?-[6-9]$/', $c), "$key: class $c exists in Bootstrap 5.3");
				pg_assert_false(strpos($c, 'opacity-') === 0, "$key: no faded text ($c)");
				pg_assert_false((bool)preg_match('/-eyebrow$/', $c), "$key: no eyebrow line ($c)");
				pg_assert_false((bool)preg_match('/^pg-(?!bike-|comment-|field-value$|hide-if-empty$)[a-z]+-/', $c), "$key: only the bike block family's rules ($c)");
			}
			$id = (string)($n['props']['id'] ?? '');
			if ($id === '') continue;
			pg_assert_true(strpos($id, 'bikeshop-') === 0, "$key: id $id is the template's");
			if (in_array($id, array('bikeshop-nav'), true)) continue;
			pg_assert_false(isset($ids[$id]), "id $id once");
			$ids[$id] = true;
		}
	}
	pg_assert_true(count($ids) > 15, 'search anchors found');
	pg_assert_same(1, count(_test_bikeshopp_with_class(_test_bikeshopp_pages()['home']['tree'], 'pbs-workbench')), 'the home opens on the workbench');
}

// Heading levels: one h1 per page, no level skipped.
function test_bikeshop_public_heading_levels()
{
	foreach (_test_bikeshopp_pages() as $key => $p) {
		$levels = array();
		foreach (_test_bikeshopp_nodes($p['tree']) as $n) {
			$tag = (string)($n['props']['tag'] ?? '');
			if (preg_match('/^h([1-6])$/', $tag, $m)) $levels[] = (int)$m[1];
		}
		pg_assert_same(1, count(array_keys($levels, 1, true)), "$key: one h1");
		$set = array_values(array_unique($levels));
		sort($set);
		pg_assert_same(range(1, max($set)), $set, "$key: no heading level skipped");
	}
}

// The pages carry no year or month: the shared footer's {{year}} is a
// token, and the shop's own year never appears.
function test_bikeshop_public_no_fixed_dates()
{
	$json = rawurldecode(json_encode(_test_bikeshopp_pages(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
	pg_assert_false((bool)preg_match('/\b(19|20)[0-9]{2}\b/', $json), 'no year in the sample');
	pg_assert_false((bool)preg_match('/\b(January|February|March|April|June|July|August|September|October|November|December)\b/', $json), 'no month in the sample');
}

// Every literal of the pages has its Turkish, with the template's words.
function test_bikeshop_public_turkish_words()
{
	$map = pg_test_language_map('tr');
	$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-bikeshop/pages_public.php');
	preg_match_all("/lang\\('((?:[^'\\\\]|\\\\.)*)'\\)/", $src, $m);
	foreach (array_unique($m[1]) as $raw) {
		$key = stripslashes($raw);
		pg_assert_true(isset($map[$key]) && $map[$key] !== '', "Turkish for '$key'");
		if (!isset($map[$key])) continue;
		$tr = $map[$key];
		if (preg_match('/^Workshop/', $key)) pg_assert_true(mb_stripos($tr, 'atölye', 0, 'UTF-8') !== false, "'$key' says atölye");
		if (preg_match('/\bService\b/', $key)) pg_assert_true(mb_stripos($tr, 'bakım', 0, 'UTF-8') !== false, "'$key' says bakım");
		if (preg_match('/\bGroup Rides?\b/', $key)) pg_assert_true(mb_stripos($tr, 'grup sürüş', 0, 'UTF-8') !== false, "'$key' says grup sürüşü");
		if (preg_match('/\bBike Fit\b/', $key)) pg_assert_true(mb_stripos($tr, 'bike fit', 0, 'UTF-8') !== false, "'$key' says bike fit");
	}
}
