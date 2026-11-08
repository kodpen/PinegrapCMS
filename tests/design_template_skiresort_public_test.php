<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Ski Resort design template's public pages
 * (includes/design_templates/pinegrap-skiresort/pages_public.php): the
 * pages of the plan in the public folder and without the shop, links that
 * name template pages and no shop page, the widgets only the home places,
 * the Ski Resort palette blocks drawn with their classes and rules on the
 * home and the conditions page, prices from the one price table, filter and
 * form links in the lists' own words, the template's ids and classes, no
 * fixed dates and every literal in Turkish.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(__DIR__ . '/design_template_skiresort_test.php');

// parts.php, cards.php and the public pages in one scope, as the template
// file reads them. Read once per run.
function _test_skp_load()
{
	static $out = null;
	if ($out !== null) return $out;
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-skiresort';
	include $dir . '/parts.php';
	include $dir . '/cards.php';
	$public_part = include $dir . '/pages_public.php';
	$out = array('part' => $public_part, 'lifts' => $lifts, 'lift_passes' => $lift_passes, 'lift_pass_prices' => $lift_pass_prices,
		'season_pass_prices' => $season_pass_prices, 'rental_prices' => $rental_prices, 'money' => $money, 'conditions' => $conditions,
		'level_titles' => $level_titles, 'group_kinds' => $group_kinds, 'lesson_choices' => $lesson_choices, 'age_titles' => $age_titles,
		'stay_choices' => $stay_choices, 'difficulty_titles' => $difficulty_titles, 'resort' => $resort);
	return $out;
}

function _test_skp_pages()
{
	$pages = array();
	foreach (_test_skp_load()['part']['pages'] as $p) $pages[$p['key']] = $p;
	return $pages;
}

function _test_skp_nodes($tree)
{
	$nodes = array();
	_test_sk_nodes($tree, $nodes);
	return $nodes;
}

// The sections of a tree carrying the given class.
function _test_skp_with_class($tree, $class)
{
	$out = array();
	foreach (_test_skp_nodes($tree) as $n) {
		if (in_array($class, preg_split('/\s+/', (string)($n['props']['cssClass'] ?? '')), true)) $out[] = $n;
	}
	return $out;
}

function test_skiresort_public_pages_are_the_plans()
{
	$part = _test_skp_load()['part'];
	pg_assert_same(array('pages', 'widgets'), array_keys($part), 'the part returns pages and widgets');
	pg_assert_same(array('home', 'conditions', 'passes', 'rental', 'ski_school', 'lodging', 'about', 'faq'), array_keys(_test_skp_pages()), 'the public pages');
	$known = array_flip(_test_sk_page_keys());
	$names = array();
	foreach (_test_skp_pages() as $key => $p) {
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
		pg_assert_contains('"templateShared":"snow_band"', json_encode($p['tree']), "$key: the snow band");
	}
}

function test_skiresort_public_links_name_template_pages()
{
	$known = array_flip(_test_sk_page_keys());
	$json = json_encode(_test_skp_pages());
	preg_match_all('/\{\{(?:page|tab):([a-z0-9_]+)\}\}/', $json, $m);
	pg_assert_true(count(array_unique($m[1])) > 8, 'links found');
	foreach (array_unique($m[1]) as $key) pg_assert_true(isset($known[$key]), "page link {{page:$key}} names a template page");
	foreach (_test_sk_shop_keys() as $key) pg_assert_false(strpos($json, '{{page:' . $key . '}}') !== false, "no public page links $key");
	// An anchor a link names is a section of the page it opens.
	foreach (_test_skp_pages() as $key => $p) {
		preg_match_all('/"href":"(?:\{\{page:([a-z_]+)\}\})?#([a-z0-9-]+)"/', json_encode($p['tree']), $a, PREG_SET_ORDER);
		foreach ($a as $hit) {
			$target = $hit[1] !== '' ? $hit[1] : $key;
			pg_assert_contains('"id":"' . $hit[2] . '"', json_encode(_test_skp_pages()[$target]['tree']), "$key: #{$hit[2]} is on $target");
		}
	}
}

function test_skiresort_public_home_widgets()
{
	pg_assert_same(array(), _test_skp_load()['part']['widgets'], 'no widget of its own');
	$widgets = array();
	foreach (_test_skp_pages() as $key => $p) {
		foreach (_test_skp_nodes($p['tree']) as $n) {
			if (isset($n['props']['templateWidget'])) $widgets[$key][] = $n['props']['templateWidget'];
		}
	}
	pg_assert_same(array('home' => array('home_slopes', 'home_events', 'home_posts')), $widgets, 'only the home places widgets');
}

// The home and the conditions page place the palette blocks: the board on
// a dark band, the lift status and the snow report, the pass cards; their
// rules exist in the family file, so the template ships them.
function test_skiresort_public_places_the_palette_blocks()
{
	$pages = _test_skp_pages();
	$boards = _test_skp_with_class($pages['home']['tree'], 'pg-ski-board');
	pg_assert_same(1, count($boards), 'the home draws the conditions board');
	pg_assert_same(array(array('name' => 'data-bs-theme', 'value' => 'dark')), $boards[0]['props']['_attrs'], 'the board is a dark band');
	pg_assert_same(3, count(_test_skp_with_class($pages['conditions']['tree'], 'pg-ski-board')), 'the conditions page: board, lift status, snow report');
	foreach (array('home', 'passes') as $key) {
		$lines = 0;
		foreach (_test_skp_nodes($pages[$key]['tree']) as $n) if (($n['props']['_label'] ?? '') === lang('Price Line')) $lines++;
		pg_assert_same(12, $lines, "$key: the pass cards' price lines");
		pg_assert_same(1, count(_test_skp_with_class($pages[$key]['tree'], 'border-primary')), "$key: one pass card set apart");
	}
	$rules = _pg_designer_block_css_parse((string)file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer_blocks_skiresort.js'));
	$json = json_encode($pages);
	preg_match_all('/\bpg-[a-z0-9-]+/', $json, $m);
	foreach (array_unique($m[0]) as $class) pg_assert_true(isset($rules['rules'][$class]), "$class is a rule of the Ski Resort family");
	// Every entrance is one the interaction normalizer keeps.
	$fx = 0;
	foreach ($pages as $key => $p) {
		foreach (_test_skp_nodes($p['tree']) as $n) {
			if (!isset($n['props']['_fx'])) continue;
			$fx++;
			$kept = pg_fx_normalize($n['props']['_fx']);
			pg_assert_true(is_array($kept) && $kept['effect'] === 'slide-up' && $kept['stagger'] === 90, "$key: an entrance the normalizer keeps");
		}
	}
	pg_assert_true($fx >= 5, 'entrances placed');
}

// The board counts the open lifts from the sample lifts and says every
// condition the staff write; the lift status lists every lift with its
// status in words; the pass cards say the prices of the one table.
function test_skiresort_public_blocks_say_the_data()
{
	$d = _test_skp_load();
	$pages = _test_skp_pages();
	$conditions = json_encode($pages['conditions']['tree'], JSON_UNESCAPED_UNICODE);
	$open = 0;
	foreach ($d['lifts'] as $l) {
		if ($l['status'] === 'open') $open++;
		pg_assert_contains(json_encode($l['title'], JSON_UNESCAPED_UNICODE), $conditions, 'lift listed');
	}
	pg_assert_contains(json_encode(sprintf(lang('%1$s / %2$s'), $open, count($d['lifts'])), JSON_UNESCAPED_UNICODE), $conditions, 'open lifts counted');
	foreach ($d['conditions'] as $c) pg_assert_contains(json_encode($c['value'], JSON_UNESCAPED_UNICODE), $conditions, 'a condition');
	foreach (array('bi-check-circle-fill', 'bi-x-circle-fill', 'bi-pause-circle-fill') as $icon) pg_assert_contains($icon, $conditions, "status icon $icon");
	$passes = json_encode($pages['passes']['tree'], JSON_UNESCAPED_UNICODE);
	foreach (array('lift_pass_prices', 'season_pass_prices', 'rental_prices') as $table) {
		foreach ($d[$table] as $prices) pg_assert_contains(json_encode($d['money']($prices), JSON_UNESCAPED_UNICODE), $passes, "a price of $table");
	}
}

// Form links use the lists' own answers.
function test_skiresort_public_links_use_the_answers()
{
	$d = _test_skp_load();
	foreach (_test_skp_pages() as $key => $p) {
		$q = _test_skc_like_queries($p['tree'], 'lesson_request');
		foreach ($q['level'] ?? array() as $v) pg_assert_true(in_array($v, $d['level_titles'], true), "$key: a level answer");
		foreach ($q['lesson'] ?? array() as $v) pg_assert_true(in_array($v, $d['lesson_choices'], true), "$key: a lesson answer");
		foreach ($q['age'] ?? array() as $v) pg_assert_true(in_array($v, $d['age_titles'], true), "$key: an age answer");
		$q = _test_skc_like_queries($p['tree'], 'group_enquiry');
		foreach ($q['group'] ?? array() as $v) pg_assert_true(in_array($v, $d['group_kinds'], true), "$key: a group answer");
		foreach ($q['stay'] ?? array() as $v) pg_assert_true(in_array($v, $d['stay_choices'], true), "$key: a stay answer");
		$q = _test_skc_like_queries($p['tree'], 'slopes');
		foreach ($q['difficulty'] ?? array() as $v) pg_assert_true(in_array($v, $d['difficulty_titles'], true), "$key: a difficulty answer");
	}
	pg_assert_same(count($d['level_titles']), count(_test_skc_like_queries(_test_skp_pages()['ski_school']['tree'], 'lesson_request')['level']) - 6, 'the ski school links every level (and each lesson its own)');
	pg_assert_same(count($d['group_kinds']), count(_test_skc_like_queries(_test_skp_pages()['lodging']['tree'], 'group_enquiry')['group']), 'the lodging page links every group kind');
}

// The links of a tree that open $page with a query, as name => values.
function _test_skc_like_queries($tree, $page)
{
	$prefix = '{{page:' . $page . '}}?';
	$out = array();
	foreach (_test_skp_nodes($tree) as $node) {
		if (!isset($node['props']['href']) || strpos($node['props']['href'], $prefix) !== 0) continue;
		parse_str(substr($node['props']['href'], strlen($prefix)), $q);
		foreach ($q as $name => $value) $out[$name][] = $value;
	}
	return $out;
}

function test_skiresort_public_ids_and_classes()
{
	$ids = array();
	foreach (_test_skp_pages() as $key => $p) {
		foreach (_test_skp_nodes($p['tree']) as $n) {
			foreach (preg_split('/\s+/', (string)($n['props']['cssClass'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $c) {
				pg_assert_false((bool)preg_match('/^(pgl|pmz|pph|pvt|pre|pev|sk)-/', $c), "$key: class $c is not another template's");
				pg_assert_false((bool)preg_match('/^[pm][trblxy]?(-[a-z]{2,3})?-[6-9]$/', $c), "$key: class $c exists in Bootstrap 5.3");
				pg_assert_false(strpos($c, 'opacity-') === 0, "$key: no faded text ($c)");
				pg_assert_false(in_array($c, array('text-white', 'bg-white', 'bg-dark', 'text-dark', 'btn-light', 'text-muted'), true), "$key: no fixed colour ($c)");
			}
			$id = (string)($n['props']['id'] ?? '');
			if ($id === '') continue;
			pg_assert_true(strpos($id, 'skr-') === 0, "$key: id $id is the template's");
			pg_assert_false(isset($ids[$id]), "id $id once");
			$ids[$id] = true;
		}
	}
	pg_assert_true(count($ids) > 15, 'search anchors found');
}

// Heading levels: one h1 per page, no level skipped.
function test_skiresort_public_heading_levels()
{
	foreach (_test_skp_pages() as $key => $p) {
		$levels = array();
		foreach (_test_skp_nodes($p['tree']) as $n) {
			$tag = (string)($n['props']['tag'] ?? '');
			if (preg_match('/^h([1-6])$/', $tag, $m)) $levels[] = (int)$m[1];
		}
		pg_assert_same(1, count(array_keys($levels, 1, true)), "$key: one h1");
		$set = array_values(array_unique($levels));
		sort($set);
		pg_assert_same(range(1, max($set)), $set, "$key: no heading level skipped");
	}
}

function test_skiresort_public_no_fixed_dates()
{
	$json = rawurldecode(json_encode(_test_skp_pages(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
	pg_assert_false((bool)preg_match('/\b(19|20)[0-9]{2}\b/', $json), 'no year in the sample');
	pg_assert_false((bool)preg_match('/\b(January|February|March|April|June|July|August|September|October|November|December)\b/', $json), 'no month in the sample');
}

// Every literal of the pages has its Turkish, with the template's words.
function test_skiresort_public_turkish_words()
{
	$map = pg_test_language_map('tr');
	$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-skiresort/pages_public.php');
	preg_match_all("/lang\\('((?:[^'\\\\]|\\\\.)*)'\\)/", $src, $m);
	foreach (array_unique($m[1]) as $raw) {
		$key = stripslashes($raw);
		pg_assert_true(isset($map[$key]) && $map[$key] !== '', "Turkish for '$key'");
		if (!isset($map[$key])) continue;
		$tr = $map[$key];
		if (preg_match('/\bSlopes?\b/', $key)) pg_assert_true(mb_stripos($tr, 'pist', 0, 'UTF-8') !== false, "'$key' says pist");
		if (preg_match('/\bSki School\b/i', $key)) pg_assert_true(mb_stripos($tr, 'kayak okul', 0, 'UTF-8') !== false, "'$key' says kayak okulu");
		if (preg_match('/^Lift Passes$/', $key)) pg_assert_true(mb_stripos($tr, 'skipass', 0, 'UTF-8') !== false, "'$key' says skipass");
	}
}
