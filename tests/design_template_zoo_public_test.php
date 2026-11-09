<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Zoo design template's public pages
 * (includes/design_templates/pinegrap-zoo/pages_public.php): the pages of
 * the plan in the public folder and without the shop, links that name
 * template pages and no shop page, the widgets only the home places, the
 * home's own order, the Zoo palette blocks drawn with their structure and
 * rules, the data the blocks say, prices from the one price table, form
 * links in the lists' own words, the template's ids and classes, heading
 * levels, no fixed dates and every literal in Turkish.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(__DIR__ . '/design_template_zoo_test.php');

// parts.php, cards.php and the public pages in one scope, as the template
// file reads them. Read once per run.
function _test_zoop_load()
{
	static $out = null;
	if ($out !== null) return $out;
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-zoo';
	include $dir . '/parts.php';
	include $dir . '/cards.php';
	$public_part = include $dir . '/pages_public.php';
	$out = array('part' => $public_part, 'animals' => $animals, 'habitats' => $habitats, 'schedule' => $schedule, 'conservation' => $conservation,
		'ticket_prices' => $ticket_prices, 'tickets' => $tickets, 'memberships' => $memberships, 'adoption_levels' => $adoption_levels,
		'money' => $money, 'membership_price_text' => $membership_price_text, 'adoption_price_text' => $adoption_price_text,
		'visit_choices' => $visit_choices, 'group_kinds' => $group_kinds, 'age_titles' => $age_titles, 'habitat_titles' => $habitat_titles,
		'class_titles' => $class_titles);
	return $out;
}

function _test_zoop_pages()
{
	$pages = array();
	foreach (_test_zoop_load()['part']['pages'] as $p) $pages[$p['key']] = $p;
	return $pages;
}

function _test_zoop_nodes($tree)
{
	$nodes = array();
	_test_zoo_nodes($tree, $nodes);
	return $nodes;
}

// The nodes of a tree carrying the given class.
function _test_zoop_with_class($tree, $class)
{
	$out = array();
	foreach (_test_zoop_nodes($tree) as $n) {
		if (in_array($class, preg_split('/\s+/', (string)($n['props']['cssClass'] ?? '')), true)) $out[] = $n;
	}
	return $out;
}

// The nodes of a tree with the given label.
function _test_zoop_labelled($tree, $label)
{
	$out = array();
	foreach (_test_zoop_nodes($tree) as $n) if (($n['props']['_label'] ?? '') === $label) $out[] = $n;
	return $out;
}

// The top-level sections of a page's main content, by label, in order.
function _test_zoop_main_sections($tree)
{
	foreach ($tree['children'] as $child) {
		if (($child['props']['tag'] ?? '') !== 'main') continue;
		$out = array();
		foreach ($child['children'] as $c) $out[] = $c['props']['_label'] ?? '';
		return $out;
	}
	return array();
}

function test_zoo_public_pages_are_the_plans()
{
	$part = _test_zoop_load()['part'];
	pg_assert_same(array('pages', 'widgets'), array_keys($part), 'the part returns pages and widgets');
	pg_assert_same(array('home', 'today', 'map', 'tickets', 'memberships', 'adopt', 'about', 'faq'), array_keys(_test_zoop_pages()), 'the public pages');
	$known = array_flip(_test_zoo_page_keys());
	$names = array();
	foreach (_test_zoop_pages() as $key => $p) {
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
		pg_assert_contains('"templateShared":"visit_band"', json_encode($p['tree']), "$key: the visit band");
	}
}

function test_zoo_public_links_name_template_pages()
{
	$known = array_flip(_test_zoo_page_keys());
	$json = json_encode(_test_zoop_pages());
	preg_match_all('/\{\{(?:page|tab):([a-z0-9_]+)\}\}/', $json, $m);
	pg_assert_true(count(array_unique($m[1])) > 8, 'links found');
	foreach (array_unique($m[1]) as $key) pg_assert_true(isset($known[$key]), "page link {{page:$key}} names a template page");
	foreach (_test_zoo_shop_keys() as $key) pg_assert_false(strpos($json, '{{page:' . $key . '}}') !== false, "no public page links $key");
	pg_assert_false(strpos($json, '"href":"#"') !== false, 'no link that goes nowhere');
	// An anchor a link names is a section of the page it opens.
	$anchors = 0;
	foreach (_test_zoop_pages() as $key => $p) {
		preg_match_all('/"href":"(?:\{\{page:([a-z_]+)\}\})?#([a-z0-9-]+)"/', json_encode($p['tree']), $a, PREG_SET_ORDER);
		foreach ($a as $hit) {
			$target = $hit[1] !== '' ? $hit[1] : $key;
			if (!isset(_test_zoop_pages()[$target])) continue;
			$anchors++;
			pg_assert_contains('"id":"' . $hit[2] . '"', json_encode(_test_zoop_pages()[$target]['tree']), "$key: #{$hit[2]} is on $target");
		}
	}
	pg_assert_true($anchors >= 2, 'anchors checked');
}

// The home places the three lists of the later parts; no other page does,
// and this part brings no widget of its own.
function test_zoo_public_home_widgets()
{
	pg_assert_same(array(), _test_zoop_load()['part']['widgets'], 'no widget of its own');
	$widgets = array();
	foreach (_test_zoop_pages() as $key => $p) {
		foreach (_test_zoop_nodes($p['tree']) as $n) {
			if (isset($n['props']['templateWidget'])) $widgets[$key][] = $n['props']['templateWidget'];
		}
	}
	pg_assert_same(array('home' => array('home_animals', 'home_events', 'home_posts')), $widgets, 'only the home places widgets');
}

// The home opens on the day: a short hero, today's schedule second, then
// the spotlight, the habitats, the map teaser and the conservation band.
// No figures row, quotes or questions on it.
function test_zoo_public_home_order()
{
	$home = _test_zoop_pages()['home']['tree'];
	pg_assert_same(array(lang('Hero'), lang('Today\'s Schedule'), lang('Animal Spotlight'), lang('Habitats'), lang('Park Map'),
		lang('Conservation Projects'), lang('Events'), lang('News')), _test_zoop_main_sections($home), 'the home\'s order');
	pg_assert_same(array(), _test_zoop_labelled($home, lang('Key Figure')), 'no figures row');
	pg_assert_same(array(), _test_zoop_with_class($home, 'accordion'), 'no questions');
}

// The pages place the Zoo palette blocks: today's schedule on the home and
// the today page, the spotlight, the map on the home and the map page, the
// projects on the home, adopt and about. Every pg- rule they use is a rule
// of the Zoo family, so the template ships it; every entrance is one the
// interaction normalizer keeps.
function test_zoo_public_places_the_palette_blocks()
{
	$pages = _test_zoop_pages();
	$where = array(
		lang('Today\'s Schedule')      => array('home', 'today'),
		lang('Animal Spotlight')       => array('home'),
		lang('Park Map')               => array('home', 'map'),
		lang('Conservation Projects')  => array('home', 'adopt', 'about'),
	);
	foreach ($where as $label => $keys) {
		foreach ($pages as $key => $p) {
			$n = 0;
			foreach (_test_zoop_labelled($p['tree'], $label) as $node) if (($node['props']['tag'] ?? '') === 'section') $n++;
			pg_assert_same(in_array($key, $keys, true) ? 1 : 0, $n, "$key: the $label block");
		}
	}
	$rules = _pg_designer_block_css_parse((string)file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer_blocks_zoo.js'));
	preg_match_all('/\bpg-[a-z0-9-]+/', json_encode($pages), $m);
	pg_assert_true(count($m[0]) > 0, 'the family\'s rules are used');
	foreach (array_unique($m[0]) as $class) pg_assert_true(isset($rules['rules'][$class]), "$class is a rule of the Zoo family");
	$fx = 0;
	$scale = 0;
	foreach ($pages as $key => $p) {
		foreach (_test_zoop_nodes($p['tree']) as $n) {
			if (!isset($n['props']['_fx'])) continue;
			$fx++;
			$kept = pg_fx_normalize($n['props']['_fx']);
			pg_assert_true(is_array($kept) && $kept['trigger'] === 'view', "$key: an entrance on view the normalizer keeps");
			pg_assert_true(in_array($kept['effect'], array('slide-up', 'scale'), true), "$key: the blocks' entrances");
			if ($kept['effect'] === 'scale') $scale++;
		}
	}
	pg_assert_true($fx >= 10, 'entrances placed');
	pg_assert_same(1, $scale, 'the spotlight photo scales in');
}

// The schedule lists every slot with its time in a <time>, one slot
// marked as next; the spotlight marks the animal's own red-list level of
// seven; the map pins every sample animal in its zone's colour.
function test_zoo_public_blocks_say_the_data()
{
	$d = _test_zoop_load();
	$pages = _test_zoop_pages();
	$times = array();
	foreach (_test_zoop_nodes($pages['today']['tree']) as $n) {
		if (($n['props']['tag'] ?? '') === 'time') $times[] = $n['props']['text'];
	}
	pg_assert_same(array_column($d['schedule'], 'time'), $times, 'a slot per item of the schedule');
	$current = 0;
	foreach (_test_zoop_nodes($pages['today']['tree']) as $n) {
		foreach ($n['props']['_attrs'] ?? array() as $a) if ($a['name'] === 'aria-current' && $a['value'] === 'time') $current++;
	}
	pg_assert_same(1, $current, 'one slot marked next');
	$scale = _test_zoop_labelled($pages['home']['tree'], lang('Status Scale'));
	pg_assert_same(1, count($scale), 'the red list scale');
	pg_assert_same(count($d['conservation']), count($scale[0]['children']), 'seven levels');
	$marked = array();
	foreach ($scale[0]['children'] as $li) if (!empty($li['props']['_attrs'])) $marked[] = $li['children'][0]['props']['text'];
	pg_assert_same(array($d['conservation'][$d['animals']['giraffe']['status']]['code']), $marked, 'the giraffe\'s level is marked');
	$map = json_encode($pages['map']['tree'], JSON_UNESCAPED_UNICODE);
	foreach ($d['animals'] as $key => $a) {
		pg_assert_contains(json_encode($a['title'], JSON_UNESCAPED_UNICODE), $map, "$key on the map");
	}
	pg_assert_same(count($d['animals']), count(_test_zoop_with_class(_test_zoop_labelled($pages['map']['tree'], lang('Zones'))[0], 'pg-zoo-pin')), 'a pin per animal');
	foreach ($d['habitats'] as $key => $h) pg_assert_contains('pg-zoo-pin ' . $h['pin'], $map, "$key: its zone's colour");
}

// The prices are the one table's: the tickets page, the memberships page
// and the adopt page say every price the shop will.
function test_zoo_public_prices_from_the_table()
{
	$d = _test_zoop_load();
	$pages = _test_zoop_pages();
	$json = function ($key) use ($pages) { return json_encode($pages[$key]['tree'], JSON_UNESCAPED_UNICODE); };
	foreach ($d['ticket_prices'] as $key => $prices) pg_assert_contains(json_encode($d['money']($prices), JSON_UNESCAPED_UNICODE), $json('tickets'), "ticket $key");
	foreach (array_keys($d['memberships']) as $key) pg_assert_contains(json_encode($d['membership_price_text']($key), JSON_UNESCAPED_UNICODE), $json('memberships'), "membership $key");
	foreach (array_keys($d['adoption_levels']) as $key) pg_assert_contains(json_encode($d['adoption_price_text']($key), JSON_UNESCAPED_UNICODE), $json('adopt'), "adoption $key");
	$adopt = $json('adopt');
	foreach ($d['animals'] as $key => $a) {
		pg_assert_same($a['adopt'], strpos($adopt, json_encode($a['title'], JSON_UNESCAPED_UNICODE)) !== false, "$key: on the adopt page only when it can be adopted");
	}
}

// The links of a tree that open $page with a query, as name => values.
function _test_zoop_queries($tree, $page)
{
	$prefix = '{{page:' . $page . '}}?';
	$out = array();
	foreach (_test_zoop_nodes($tree) as $node) {
		if (!isset($node['props']['href']) || strpos($node['props']['href'], $prefix) !== 0) continue;
		parse_str(substr($node['props']['href'], strlen($prefix)), $q);
		foreach ($q as $name => $value) $out[$name][] = $value;
	}
	return $out;
}

// Form and list links use the lists' own answers.
function test_zoo_public_links_use_the_answers()
{
	$d = _test_zoop_load();
	foreach (_test_zoop_pages() as $key => $p) {
		$q = _test_zoop_queries($p['tree'], 'visit_request');
		foreach ($q['visit'] ?? array() as $v) pg_assert_true(in_array($v, $d['visit_choices'], true), "$key: a visit answer");
		foreach ($q['group'] ?? array() as $v) pg_assert_true(in_array($v, $d['group_kinds'], true), "$key: a group answer");
		foreach ($q['age'] ?? array() as $v) pg_assert_true(in_array($v, $d['age_titles'], true), "$key: an age answer");
		$q = _test_zoop_queries($p['tree'], 'animals');
		foreach ($q['habitat'] ?? array() as $v) pg_assert_true(in_array($v, $d['habitat_titles'], true), "$key: a habitat answer");
		foreach ($q['class'] ?? array() as $v) pg_assert_true(in_array($v, $d['class_titles'], true), "$key: a class answer");
	}
	pg_assert_same(2, count(_test_zoop_queries(_test_zoop_pages()['tickets']['tree'], 'visit_request')['visit']), 'the tickets page asks for a group tour and a school trip');
	pg_assert_same($d['habitat_titles'], _test_zoop_queries(_test_zoop_pages()['map']['tree'], 'animals')['habitat'], 'the map page opens every habitat');
}

function test_zoo_public_ids_and_classes()
{
	$ids = array();
	$own = array();
	foreach (_test_zoop_pages() as $key => $p) {
		foreach (_test_zoop_nodes($p['tree']) as $n) {
			foreach (preg_split('/\s+/', (string)($n['props']['cssClass'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $c) {
				pg_assert_false((bool)preg_match('/^(prx|pmz|pph|pvt|pre|pev|pgl|pmn|prt|plb|pht|pma|pco|pcl|pgy|pch|psk|sk)-/', $c), "$key: class $c is not another template's");
				pg_assert_false((bool)preg_match('/^[pm][trblxy]?(-[a-z]{2,3})?-[6-9]$/', $c), "$key: class $c exists in Bootstrap 5.3");
				pg_assert_false(strpos($c, 'opacity-') === 0, "$key: no faded text ($c)");
				pg_assert_false((bool)preg_match('/-eyebrow$/', $c), "$key: no eyebrow line ($c)");
				pg_assert_false(in_array($c, array('text-white', 'bg-white', 'bg-dark', 'text-dark', 'btn-light', 'text-muted'), true), "$key: no fixed colour ($c)");
				if (strpos($c, 'pzo-') === 0) $own[$c] = true;
			}
			$id = (string)($n['props']['id'] ?? '');
			if ($id === '') continue;
			pg_assert_true(strpos($id, 'zoo-') === 0, "$key: id $id is the template's");
			pg_assert_false(isset($ids[$id]), "id $id once");
			$ids[$id] = true;
		}
	}
	pg_assert_true(count($ids) > 20, 'search anchors found');
	foreach (array('pzo-display', 'pzo-hero', 'pzo-price', 'pzo-zone') as $c) pg_assert_true(isset($own[$c]), "$c is used");
}

// Heading levels: one h1 per page, no level skipped.
function test_zoo_public_heading_levels()
{
	foreach (_test_zoop_pages() as $key => $p) {
		$levels = array();
		foreach (_test_zoop_nodes($p['tree']) as $n) {
			$tag = (string)($n['props']['tag'] ?? '');
			if (preg_match('/^h([1-6])$/', $tag, $m)) $levels[] = (int)$m[1];
		}
		pg_assert_same(1, count(array_keys($levels, 1, true)), "$key: one h1");
		$set = array_values(array_unique($levels));
		sort($set);
		pg_assert_same(range(1, max($set)), $set, "$key: no heading level skipped");
	}
}

function test_zoo_public_no_fixed_dates()
{
	$json = rawurldecode(json_encode(_test_zoop_pages(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
	pg_assert_false((bool)preg_match('/\b(19|20)[0-9]{2}\b/', $json), 'no year in the sample');
	pg_assert_false((bool)preg_match('/\b(January|February|March|April|June|July|August|September|October|November|December)\b/', $json), 'no month in the sample');
}

// Every literal of the pages has its Turkish, with the template's words.
function test_zoo_public_turkish_words()
{
	$map = pg_test_language_map('tr');
	$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-zoo/pages_public.php');
	preg_match_all("/lang\\('((?:[^'\\\\]|\\\\.)*)'\\)/", $src, $m);
	foreach (array_unique($m[1]) as $raw) {
		$key = stripslashes($raw);
		pg_assert_true(isset($map[$key]) && $map[$key] !== '', "Turkish for '$key'");
		if (!isset($map[$key])) continue;
		$tr = $map[$key];
		if (preg_match('/^Habitats?\b/', $key)) pg_assert_true(mb_stripos($tr, 'yaşam alan', 0, 'UTF-8') !== false, "'$key' says yaşam alanı");
		if (preg_match('/\bfeeding\b/i', $key)) pg_assert_true(mb_stripos($tr, 'besleme', 0, 'UTF-8') !== false, "'$key' says besleme");
		if (preg_match('/\bMemberships?\b/', $key)) pg_assert_true(mb_stripos($tr, 'üyeli', 0, 'UTF-8') !== false, "'$key' says üyelik");
		if (preg_match('/\bTickets?\b/', $key)) pg_assert_true(mb_stripos($tr, 'bilet', 0, 'UTF-8') !== false, "'$key' says bilet");
		if (preg_match('/\badoption\b/i', $key)) pg_assert_true(mb_stripos($tr, 'sahiplen', 0, 'UTF-8') !== false, "'$key' says sahiplenme");
		if (preg_match('/\bschool trip\b/i', $key)) pg_assert_true(mb_stripos($tr, 'okul gezi', 0, 'UTF-8') !== false, "'$key' says okul gezisi");
		if (preg_match('/\bkeeper talks?\b/i', $key)) pg_assert_true(mb_stripos($tr, 'bakıcı sohbet', 0, 'UTF-8') !== false, "'$key' says bakıcı sohbeti");
	}
}
