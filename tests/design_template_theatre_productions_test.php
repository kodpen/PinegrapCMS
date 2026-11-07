<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Theatre design template's productions
 * (includes/design_templates/pinegrap-theatre/productions.php): the pages
 * and widgets, the Productions collection with a sample per production
 * whose values fit their fields, the season's filters reading what the
 * filter rows, the footer and the genre cards send, the home lists the
 * productions now playing, the production page opens its performances and
 * the season on its genre and carries the audience's comments, every page
 * link names a template page, ids and classes are the template's own, and
 * every literal has its Turkish.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The page keys of the finished template (_test_th_page_keys()).
require_once __DIR__ . '/design_template_theatre_test.php';

// The productions' part as the template file reads it: parts.php first,
// then the public pages and productions.php in the same scope. Read once
// per run.
function _test_thx_load()
{
	static $out = null;
	if ($out !== null) return $out;
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-theatre';
	include $dir . '/parts.php';
	$public = include $dir . '/pages_public.php';
	$part = include $dir . '/productions.php';
	$pages = array();
	foreach ($part['pages'] as $p) $pages[$p['key']] = $p;
	$out = array('part' => $part, 'pages' => $pages, 'widgets' => $part['widgets'], 'productions' => $productions,
		'statuses' => $production_statuses, 'status_titles' => $production_status_titles, 'genres' => $genre_titles,
		'stages' => $stage_titles, 'public' => $public, 'footer' => $footer());
	return $out;
}

// Every node of a tree, depth first.
function _test_thx_nodes($node, &$out)
{
	if (!is_array($node)) return;
	if (isset($node['props'])) $out[] = $node;
	foreach ((array)($node['children'] ?? array()) as $c) _test_thx_nodes($c, $out);
}

// The name and options of every form control of a tree.
function _test_thx_controls($node)
{
	$nodes = array();
	_test_thx_nodes($node, $nodes);
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

// The query of every link to {{page:season}} in a tree.
function _test_thx_queries($tree)
{
	$nodes = array();
	_test_thx_nodes($tree, $nodes);
	$out = array();
	$prefix = '{{page:season}}?';
	foreach ($nodes as $n) {
		$href = (string)($n['props']['href'] ?? '');
		if (strpos($href, $prefix) !== 0) continue;
		parse_str(substr($href, strlen($prefix)), $q);
		$out[] = $q;
	}
	return $out;
}

function test_theatre_productions_pages_and_widgets()
{
	$d = _test_thx_load();
	pg_assert_same(array('pages', 'widgets'), array_keys($d['part']), 'the part returns pages and widgets');
	pg_assert_same(array('season', 'production', 'production_new'), array_keys($d['pages']));
	pg_assert_same(array('production_form', 'season_list', 'home_productions', 'production_comments', 'production_detail'), array_keys($d['widgets']));
	foreach (array('season', 'production') as $key) pg_assert_same('public', $d['pages'][$key]['folder'], "$key is public");
	pg_assert_same('private', $d['pages']['production_new']['folder'], 'only the staff add a production');
	pg_assert_true(!empty($d['pages']['production_new']['noindex']), 'the staff page is not indexed');
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
	pg_assert_contains('"templateWidget":"home_productions"', json_encode($home['tree']), 'the home places the productions now playing');
	// The production page places its performances, which the performances
	// part defines, and takes comments told to the theatre.
	pg_assert_contains('"templateWidget":"production_performances"', json_encode($d['pages']['production']['tree']), 'the production page lists its performances');
	$c = $d['pages']['production']['comments'];
	pg_assert_true($c['allow_new'] && $c['login'], 'signed-in visitors comment');
	pg_assert_same('{{tab:email_reply}}', $c['email_page'], 'the theatre is told by e-mail');
}

// The form is a collection with a sample per production; every sample value
// has its field and is filled, a pick list's value is one of its answers,
// the photos are images.
function test_theatre_productions_form_and_samples()
{
	$d = _test_thx_load();
	$form = $d['pages']['production_new']['form'];
	pg_assert_same(1, $form['collection'], 'a collection');
	pg_assert_same(count($d['productions']), count($form['sample_records']), 'one sample per production');
	$controls = _test_thx_controls($d['widgets']['production_form']['tree']);
	$fields = array('title', 'genre', 'status', 'stage', 'run', 'running_time', 'age_guidance', 'credits', 'summary', 'cast', 'photo_1', 'photo_2', 'photo_3');
	pg_assert_same($fields, array_keys($controls), 'the fields of the form');
	foreach (array('photo_1', 'photo_2', 'photo_3') as $f) pg_assert_same('image', $controls[$f]['cf']['kind'] ?? '', "$f is an image");
	pg_assert_true(isset($controls[$form['share_image_field']]), 'the share image is a field');
	pg_assert_same($d['statuses'], $controls['status']['options'], 'the three states');
	pg_assert_same($d['genres'], $controls['genre']['options'], 'the genres');
	pg_assert_same($d['stages'], $controls['stage']['options'], 'the stages');
	$states = array();
	foreach ($form['sample_records'] as $n => $r) {
		pg_assert_same($fields, array_keys($r), "sample $n: every field");
		foreach ($r as $name => $value) {
			pg_assert_true($value !== '', "sample $n: $name filled");
			if ($controls[$name]['tag'] === 'select') {
				pg_assert_true(in_array($value, $controls[$name]['options'], true), "sample $n: $name '$value' is an answer");
			}
			pg_assert_false((bool)preg_match('/\b(19|20)[0-9]{2}\b/', $value), "sample $n: $name names no year");
		}
		$key = array_keys($d['productions'])[$n];
		pg_assert_same(implode("\n", $d['productions'][$key]['cast']), $r['cast'], "sample $n: its cast, a line per item");
		$states[$r['status']] = true;
	}
	pg_assert_same(3, count($states), 'samples in every state');
}

// The season reads the parameters the filter rows, the footer and the
// genre cards send, each an answer of its list; the home lists the
// productions now playing.
function test_theatre_productions_filters()
{
	$d = _test_thx_load();
	$params = array();
	foreach ($d['widgets']['season_list']['config']['filters'] as $f) {
		pg_assert_same('url parameter', $f['dynamic_value'], 'the filter reads the address');
		$params[$f['field']] = $f['dynamic_value_attribute'];
	}
	pg_assert_same(array('status' => 'status', 'genre' => 'genre'), $params, 'the season filters the state and the genre');
	$seen = array('status' => 0, 'genre' => 0);
	$home = null;
	foreach ($d['public']['pages'] as $p) if ($p['key'] === 'home') $home = $p;
	foreach (array($d['pages']['season']['tree'], $d['footer'], $home['tree']) as $tree) {
		foreach (_test_thx_queries($tree) as $q) {
			pg_assert_same(1, count($q), 'one filter per link');
			$name = key($q);
			$list = $name === 'status' ? $d['statuses'] : $d['genres'];
			pg_assert_true(in_array($q[$name], $list, true), "?$name={$q[$name]} is an answer");
			$seen[$name]++;
		}
	}
	pg_assert_same(count($d['statuses']) * 2, $seen['status'], 'a state link in the filter row and the footer each');
	pg_assert_same(count($d['genres']) * 3, $seen['genre'], 'a genre link in the filter row, the footer and the home each');
	$c = $d['widgets']['home_productions']['config'];
	pg_assert_same(array(array('field' => 'status', 'operator' => 'is equal to', 'value' => $d['status_titles']['now'], 'dynamic_value' => '')), $c['filters'], 'the ones playing now');
	pg_assert_same(3, $c['max_results'], 'three at most');
	foreach (array('season_list', 'home_productions') as $key) {
		pg_assert_same('{{tab:production_new}}', $d['widgets'][$key]['config']['custom_form_page_id'], "$key: the Productions form");
		pg_assert_same('{{tab:production}}', $d['widgets'][$key]['config']['detail_page_id'], "$key: the production page");
	}
}

// The production page shows the record or the not-found note, opens the
// performances of the production and the season on its genre.
function test_theatre_productions_record_page()
{
	$d = _test_thx_load();
	$nodes = array();
	_test_thx_nodes($d['widgets']['production_detail']['tree'], $nodes);
	$links = array();
	$bound = array();
	foreach ($nodes as $n) {
		if (($n['props']['_bindings']['href'] ?? '') === '__page_link') $links[] = $n['props']['_bindParams']['href'];
		foreach (array('text', 'src') as $k) if (isset($n['props']['_bindings'][$k])) $bound[] = $n['props']['_bindings'][$k];
	}
	pg_assert_same(array(
		array('page_id' => '{{tab:whats_on}}', 'params' => array(array('name' => 'production', 'token' => 'title'))),
		array('page_id' => '{{tab:season}}', 'params' => array(array('name' => 'genre', 'token' => 'genre'))),
	), $links, 'its performances and the season on its genre');
	foreach (array('title', 'genre', 'status', 'stage', 'run', 'running_time', 'age_guidance', 'credits', 'summary', 'cast', 'photo_1', 'photo_2', 'photo_3') as $f) {
		pg_assert_true(in_array($f, $bound, true), "the page shows $f");
	}
	$json = json_encode($d['widgets']['production_detail']['tree']);
	pg_assert_contains('"eo_visible_if":"record_found"', $json, 'the record');
	pg_assert_contains('"eo_visible_if":"record_not_found"', $json, 'the not-found note');
	pg_assert_contains('"eo_visible_if":"can_edit"', $json, 'the Edit button');
}

function test_theatre_productions_links_ids_and_classes()
{
	$d = _test_thx_load();
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

// Every literal has its Turkish; a production reads "oyun" or "yapım", a
// performance "temsil".
function test_theatre_productions_turkish_words()
{
	$map = pg_test_language_map('tr');
	$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-theatre/productions.php');
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
