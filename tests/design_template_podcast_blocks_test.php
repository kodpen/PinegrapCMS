<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Podcast Network template's pages on the blocks (version
 * 2.0.0): the home opens with the Podcast: Episode Hero with Chapters on a
 * dark band and shows the figures as the Stats: Counter, the sponsorship
 * page draws the Sponsor Placements and the Steps: Numbered, the listening
 * page the Transcript, the hosts page the Team: Profiles, and an episode's
 * cover sits over the hero's sound wave. Every block class placed ships its
 * rule, every interaction is kept as written, each page has one h1, and
 * the blocks carry the network's own data (the newest episode, its
 * chapters, guest and transcript, the packages, the hosts).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The assembled template (_test_ppd_asm_tpl()) and its node walk.
require_once(__DIR__ . '/design_template_podcast_assembly_test.php');

// The rebuilt pages and the episode record's view, by key.
function _test_ppdb_trees()
{
	$tpl = _test_ppd_asm_tpl();
	$trees = array();
	foreach ($tpl['pages'] as $p) {
		if (in_array($p['key'], array('home', 'sponsor', 'listen', 'hosts'), true)) $trees[$p['key']] = $p['tree'];
	}
	$trees['episode_detail'] = $tpl['widgets']['episode_detail']['tree'];
	return $trees;
}

// parts.php's data, and the newest featured episode the home leads with.
function _test_ppdb_data()
{
	static $d = null;
	if ($d !== null) return $d;
	include PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-podcast/parts.php';
	$lead = null;
	foreach ($episodes as $key => $e) {
		if ($e['featured'] && ($lead === null || $e['days'] < $episodes[$lead]['days'])) $lead = $key;
	}
	$d = array('episodes' => $episodes, 'podcasts' => $podcasts, 'hosts' => $hosts, 'categories' => $categories, 'apps' => $apps,
		'packages' => $packages, 'money' => $money, 'lead' => $lead);
	return $d;
}

// Every node of a tree whose class list holds the class.
function _test_ppdb_with_class($tree, $class)
{
	$nodes = array();
	_test_ppd_asm_nodes($tree, $nodes);
	$out = array();
	foreach ($nodes as $n) {
		if (preg_match('/(?<![\w-])' . preg_quote($class, '/') . '(?![\w-])/', isset($n['props']['cssClass']) ? (string)$n['props']['cssClass'] : '')) $out[] = $n;
	}
	return $out;
}

// Every node of a tree with the tag.
function _test_ppdb_with_tag($tree, $tag)
{
	$nodes = array();
	_test_ppd_asm_nodes($tree, $nodes);
	$out = array();
	foreach ($nodes as $n) if ((isset($n['props']['tag']) ? $n['props']['tag'] : '') === $tag) $out[] = $n;
	return $out;
}

// The first node of a tree with the layer name, or null.
function _test_ppdb_named($tree, $label)
{
	$nodes = array();
	_test_ppd_asm_nodes($tree, $nodes);
	foreach ($nodes as $n) if (isset($n['props']['_label']) && $n['props']['_label'] === $label) return $n;
	return null;
}

// The words of a tree, joined.
function _test_ppdb_words($tree)
{
	$nodes = array();
	_test_ppd_asm_nodes($tree, $nodes);
	$words = array();
	foreach ($nodes as $n) if (isset($n['props']['text']) && is_string($n['props']['text'])) $words[] = $n['props']['text'];
	return implode(' | ', $words);
}

// The node's attribute, or null.
function _test_ppdb_attr($node, $name)
{
	foreach (isset($node['props']['_attrs']) ? $node['props']['_attrs'] : array() as $a) if ($a['name'] === $name) return $a['value'];
	return null;
}

// "4:10" as the duration a <time> carries.
function _test_ppdb_duration($time)
{
	list($m, $s) = explode(':', $time);
	return 'PT' . (int)$m . 'M' . (int)$s . 'S';
}

// Each page places its blocks, ships the rule of every block class it
// uses, keeps every interaction as written, has one h1, its search
// sections and motion as it scrolls.
function test_podcast_blocks_pages_ship_rules_and_motion()
{
	$tpl = _test_ppd_asm_tpl();
	pg_assert_true(version_compare($tpl['version'], '2.0.0', '>='), 'version 2.0.0');
	$styles = json_decode($tpl['assets']['css'], true);
	$css = $styles[0]['content'];
	$blocks = array(
		'home'           => array('Podcast (Episode Hero)', 'Stats (Counter)'),
		'sponsor'        => array('Podcast (Sponsor Placements)', 'Steps (Numbered)'),
		'listen'         => array('Podcast (Transcript)'),
		'hosts'          => array('Team (Profiles)'),
		'episode_detail' => array('Cover', 'Sound Wave'),
	);
	$search = array(
		'home'           => array('pd-home-episode'),
		'sponsor'        => array('pd-sponsor-audience', 'pd-sponsor-packages', 'pd-sponsor-steps'),
		'listen'         => array('pd-listen', 'pd-listen-transcript'),
		'hosts'          => array('pd-hosts', 'pd-be-a-guest'),
		'episode_detail' => array(),
	);
	$motion = array('home' => 6, 'sponsor' => 6, 'listen' => 4, 'hosts' => 2, 'episode_detail' => 2);
	$trees = _test_ppdb_trees();
	$keys = array_keys($trees);
	sort($keys);
	$wanted = array_keys($blocks);
	sort($wanted);
	pg_assert_same($wanted, $keys, 'the pages and the episode view');
	foreach ($trees as $key => $tree) {
		$nodes = array();
		_test_ppd_asm_nodes($tree, $nodes);
		$class_list = '';
		$labels = array();
		$ids = array();
		$effects = 0;
		$h1 = 0;
		foreach ($nodes as $n) {
			$class_list .= ' ' . (isset($n['props']['cssClass']) ? $n['props']['cssClass'] : '');
			if (isset($n['props']['_label'])) $labels[] = $n['props']['_label'];
			if (!empty($n['props']['searchSection'])) $ids[] = $n['props']['id'];
			if ((isset($n['props']['tag']) ? $n['props']['tag'] : '') === 'h1') $h1++;
			if (!isset($n['props']['_fx'])) continue;
			$effects++;
			$kept = pg_fx_normalize($n['props']['_fx']);
			foreach ($n['props']['_fx'] as $field => $value) {
				pg_assert_true(is_array($kept) && isset($kept[$field]) && $kept[$field] === $value, $key . ': ' . $field . ' is kept as written');
			}
		}
		preg_match_all('/(?<![\w-])pg-podcast(?:-[a-z0-9]+)*(?![\w-])/', $class_list, $m);
		foreach (array_unique($m[0]) as $class) pg_assert_contains('.' . $class, $css, $key . ': the rule of ' . $class . ' ships');
		foreach ($blocks[$key] as $label) pg_assert_true(in_array($label, $labels, true), $key . ': the ' . $label . ' block');
		foreach ($search[$key] as $id) pg_assert_true(in_array($id, $ids, true), $key . ': the search section ' . $id);
		pg_assert_same(1, $h1, $key . ': one h1');
		pg_assert_true($effects >= $motion[$key], $key . ': ' . $effects . ' interactions, at least ' . $motion[$key]);
	}
	// The template's own rules never name a block's class, or a later
	// palette drop would take the block's rule as present.
	pg_assert_false((bool)preg_match('/\.ppd-[^{]*\.pg-podcast/', $css), 'no ppd- rule restyles a block class');
}

// The home's hero is the newest featured episode on a dark band: its cover
// over the sound wave, its podcast, number and length, the only h1, the
// way to listen and the apps; under them its chapters, each start a
// <time>, and its guest with the podcast's host.
function test_podcast_blocks_episode_hero()
{
	$d = _test_ppdb_data();
	$e = $d['episodes'][$d['lead']];
	$podcast = $d['podcasts'][$e['podcast']];
	$trees = _test_ppdb_trees();
	$hero = _test_ppdb_named($trees['home'], 'Podcast (Episode Hero)');
	pg_assert_true($hero !== null, 'the hero');
	pg_assert_same('section', $hero['props']['tag'], 'the hero is a section');
	pg_assert_same('dark', _test_ppdb_attr($hero, 'data-bs-theme'), 'the hero is a dark band');
	pg_assert_contains('ppd-hero', $hero['props']['cssClass'], 'the hero keeps the network\'s glow');
	$h1 = _test_ppdb_with_tag($hero, 'h1');
	pg_assert_same(1, count($h1), 'the hero holds the h1');
	pg_assert_same($e['title'], $h1[0]['props']['text'], 'the h1 is the newest episode');
	$words = _test_ppdb_words($hero);
	foreach (array($podcast['title'], sprintf(lang('Season %1$d, Episode %2$d'), $e['season'], $e['number']), $e['summary']) as $fact) {
		pg_assert_contains($fact, $words, 'the hero names ' . $fact);
	}
	$times = array();
	foreach (_test_ppdb_with_tag($hero, 'time') as $t) $times[] = _test_ppdb_attr($t, 'datetime');
	pg_assert_true(in_array('PT' . $e['minutes'] . 'M', $times, true), 'the length as a duration');

	// The cover grows in on load and the wave slides in under it, a drawing
	// screen readers skip.
	$cover = _test_ppdb_with_class($hero, 'pg-podcast-cover');
	pg_assert_same(1, count($cover), 'one cover');
	pg_assert_same('scale', $cover[0]['props']['_fx']['effect'], 'the cover grows in');
	pg_assert_same('load', $cover[0]['props']['_fx']['trigger'], 'as the page opens');
	$wave = _test_ppdb_with_class($cover[0], 'pg-podcast-wave');
	pg_assert_same(1, count($wave), 'the wave under the cover');
	pg_assert_same('true', _test_ppdb_attr($wave[0], 'aria-hidden'), 'the wave is hidden from screen readers');

	// Listen Now opens the episodes, where the newest is first; each app is
	// a link named for a screen reader.
	pg_assert_contains('"href":"{{page:episodes}}"', json_encode($hero), 'Listen Now opens the episodes');
	$names = array();
	foreach (_test_ppdb_with_tag($hero, 'a') as $a) $names[] = _test_ppdb_attr($a, 'aria-label');
	foreach ($d['apps'] as $a) pg_assert_true(in_array(sprintf(lang('Listen on %s'), $a['name']), $names, true), $a['name'] . ': a named link');

	// A chapter per line of the episode's chapters, in order, each start
	// with its duration from the start of the episode.
	$list = _test_ppdb_with_class($hero, 'pg-podcast-chapters');
	pg_assert_same(1, count($list), 'one chapter list');
	pg_assert_same('ol', $list[0]['props']['tag'], 'the chapters are numbered');
	pg_assert_same(count($e['chapters']), count($list[0]['children']), 'a line per chapter');
	foreach ($e['chapters'] as $n => $c) {
		$li = $list[0]['children'][$n];
		$time = _test_ppdb_with_tag($li, 'time');
		pg_assert_same($c[0], $time[0]['props']['text'], 'chapter ' . ($n + 1) . ': its start');
		pg_assert_same(_test_ppdb_duration($c[0]), _test_ppdb_attr($time[0], 'datetime'), 'chapter ' . ($n + 1) . ': its duration');
		pg_assert_contains($c[1], _test_ppdb_words($li), 'chapter ' . ($n + 1) . ': its title');
	}

	// The guest card: the guest, what they do, a line they said and the
	// host of the podcast.
	pg_assert_true(isset($e['guest']), 'the newest episode has its guest');
	$card = _test_ppdb_named($hero, 'Guest Card');
	pg_assert_true($card !== null, 'the guest card');
	$card_words = _test_ppdb_words($card);
	foreach (array($e['guest']['name'], $e['guest']['role'], $e['guest']['quote'], sprintf(lang('Hosted by %s'), $d['hosts'][$podcast['host']]['name'])) as $fact) {
		pg_assert_contains($fact, $card_words, 'the guest card names ' . $fact);
	}

	// Small coloured text on the dark band takes the theme's darker shade.
	$nodes = array();
	_test_ppd_asm_nodes($hero, $nodes);
	foreach ($nodes as $n) {
		$class = isset($n['props']['cssClass']) ? (string)$n['props']['cssClass'] : '';
		pg_assert_false((bool)preg_match('/(?<![\w-])(text-primary|link-primary|btn-outline-primary|btn-outline-light)(?![\w-])/', $class), 'no plain primary text on the dark band: ' . $class);
	}
}

// The sponsorship page draws where a mention plays: one episode and a
// season with the three placements marked and numbered in the packages'
// order (a drawing screen readers skip), then a card per package with the
// same number, where it plays, its price and the way to ask about it.
function test_podcast_blocks_sponsor_placements()
{
	$d = _test_ppdb_data();
	$trees = _test_ppdb_trees();
	$section = _test_ppdb_named($trees['sponsor'], 'Podcast (Sponsor Placements)');
	pg_assert_true($section !== null, 'the placements');
	$packages = array_values($d['packages']);
	pg_assert_same(3, count($packages), 'three packages: the opening, the middle and the season');

	$track = _test_ppdb_with_class($section, 'pg-podcast-track');
	pg_assert_same(1, count($track), 'one drawing');
	pg_assert_same('true', _test_ppdb_attr($track[0], 'aria-hidden'), 'the drawing is hidden from screen readers');
	pg_assert_same(1, count(_test_ppdb_with_class($track[0], 'pg-podcast-bar')), 'one episode');
	pg_assert_same(1, count(_test_ppdb_with_class($track[0], 'pg-podcast-season')), 'one season');
	$marks = _test_ppdb_with_class($track[0], 'pg-podcast-at');
	pg_assert_same(count($packages), count($marks), 'a mark per package');
	pg_assert_contains('pg-podcast-at-mid', $marks[1]['props']['cssClass'], 'the middle mention is marked halfway');
	foreach ($packages as $i => $p) {
		$no = _test_ppdb_with_class($marks[$i], 'pg-podcast-no');
		pg_assert_same((string)($i + 1), $no[0]['props']['text'], 'mark ' . ($i + 1) . ': its number');
		pg_assert_contains($p['at'], _test_ppdb_words($marks[$i]), 'mark ' . ($i + 1) . ': where it plays');
	}

	$list = _test_ppdb_named($section, 'Placements');
	pg_assert_same('ol', $list['props']['tag'], 'the placements are numbered');
	pg_assert_same(count($packages), count($list['children']), 'a card per package');
	foreach ($packages as $i => $p) {
		$card = $list['children'][$i];
		$no = _test_ppdb_with_class($card, 'pg-podcast-no');
		pg_assert_same((string)($i + 1), $no[0]['props']['text'], $p['title'] . ': the number on the drawing');
		$words = _test_ppdb_words($card);
		foreach (array($p['title'], $p['when'], $p['text'], call_user_func($d['money'], $p['price']), $p['per']) as $fact) {
			pg_assert_contains($fact, $words, $p['title'] . ': ' . $fact);
		}
		foreach ($p['perks'] as $perk) pg_assert_contains($perk, $words, $p['title'] . ': ' . $perk);
		pg_assert_contains(json_encode('{{page:sponsor_enquiry}}?package=' . rawurlencode($p['title'])), json_encode($card), $p['title'] . ': asks about it');
	}

	// How it comes together: three numbered step cards.
	$steps = _test_ppdb_named($trees['sponsor'], 'Steps (Numbered)');
	$numbers = array();
	foreach (_test_ppdb_with_class($steps, 'badge') as $b) $numbers[] = $b['props']['text'];
	pg_assert_same(array('1', '2', '3'), $numbers, 'three numbered steps');
	pg_assert_same(3, count(_test_ppdb_with_tag($steps, 'h3')), 'a title per step');
}

// The listening page reads along: the newest episode's chapter and turns,
// each start a <time> inside the chapter, the host and the guest by name,
// the guest in the theme's shade.
function test_podcast_blocks_transcript()
{
	$d = _test_ppdb_data();
	$e = $d['episodes'][$d['lead']];
	$host = $d['hosts'][$d['podcasts'][$e['podcast']]['host']]['name'];
	$trees = _test_ppdb_trees();
	$section = _test_ppdb_named($trees['listen'], 'Podcast (Transcript)');
	pg_assert_true($section !== null && isset($e['script']), 'the transcript of the newest episode');
	$script = _test_ppdb_with_class($section, 'pg-podcast-script');
	pg_assert_same(1, count($script), 'one transcript');
	$rows = $script[0]['children'];
	pg_assert_same(count($e['script']['turns']) + 1, count($rows), 'the chapter and a row per turn');
	$n = $e['script']['chapter'];
	$chapter = $e['chapters'][$n];
	$start = _test_ppdb_with_tag($rows[0], 'time');
	pg_assert_same(_test_ppdb_duration($chapter[0]), _test_ppdb_attr($start[0], 'datetime'), 'the chapter\'s start');
	pg_assert_contains($chapter[1], _test_ppdb_words($rows[0]), 'the chapter\'s title');
	$seconds = function ($time) {
		list($m, $s) = explode(':', $time);
		return 60 * (int)$m + (int)$s;
	};
	$last = $seconds($chapter[0]);
	$end = isset($e['chapters'][$n + 1]) ? $seconds($e['chapters'][$n + 1][0]) : 60 * $e['minutes'];
	foreach ($e['script']['turns'] as $i => $t) {
		$row = $rows[$i + 1];
		$time = _test_ppdb_with_tag($row, 'time');
		pg_assert_same(_test_ppdb_duration($t[0]), _test_ppdb_attr($time[0], 'datetime'), 'turn ' . ($i + 1) . ': its start');
		pg_assert_true($seconds($t[0]) > $last && $seconds($t[0]) < $end, 'turn ' . ($i + 1) . ': in order, inside the chapter');
		$last = $seconds($t[0]);
		$who = $t[1] === 'guest' ? $e['guest']['name'] : $host;
		pg_assert_contains($who . ' | ' . $t[2], _test_ppdb_words($row), 'turn ' . ($i + 1) . ': the speaker and the words');
		$name = null;
		$nodes = array();
		_test_ppd_asm_nodes($row, $nodes);
		foreach ($nodes as $p) if (isset($p['props']['text']) && $p['props']['text'] === $who) $name = $p;
		$shade = $t[1] === 'guest' ? 'text-primary-emphasis' : 'text-body-emphasis';
		pg_assert_true($name !== null && strpos($name['props']['cssClass'], $shade) !== false, 'turn ' . ($i + 1) . ': the speaker in ' . $shade);
	}
	$words = _test_ppdb_words($section);
	pg_assert_contains(sprintf(lang('From the episode %s.'), $e['title']), $words, 'the episode it is from');
	pg_assert_contains(sprintf(lang('%s, host'), $host), $words, 'the host');
	pg_assert_contains(sprintf(lang('%s, guest'), $e['guest']['name']), $words, 'the guest');
}

// The hosts page: a profile per host with the podcast they host, its
// category and day, and the podcasts filtered on its category; the
// episode page's cover sits over the hero's sound wave.
function test_podcast_blocks_hosts_and_episode_cover()
{
	$d = _test_ppdb_data();
	$trees = _test_ppdb_trees();
	$section = _test_ppdb_named($trees['hosts'], 'Team (Profiles)');
	$cards = array();
	foreach (_test_ppdb_with_tag($section, 'article') as $a) $cards[] = $a;
	pg_assert_same(count($d['hosts']), count($cards), 'a profile per host');
	$i = 0;
	foreach ($d['hosts'] as $key => $h) {
		$p = null;
		foreach ($d['podcasts'] as $podcast) if ($podcast['host'] === $key) $p = $podcast;
		$words = _test_ppdb_words($cards[$i]);
		$category = $d['categories'][$p['category']]['title'];
		foreach (array($h['name'], $h['role'], $h['bio'], $category, $p['schedule']) as $fact) pg_assert_contains($fact, $words, $key . ': ' . $fact);
		pg_assert_contains(json_encode('{{page:podcasts}}?category=' . rawurlencode($category)), json_encode($cards[$i]), $key . ': the podcast\'s category');
		pg_assert_same(1, count(_test_ppdb_with_tag($cards[$i], 'h3')), $key . ': a heading');
		$i++;
	}

	$cover = _test_ppdb_with_class($trees['episode_detail'], 'pg-podcast-cover');
	pg_assert_same(1, count($cover), 'the episode\'s cover');
	pg_assert_contains('"src":"__ref"', json_encode($cover[0]), 'the cover is the podcast\'s, read through the reference');
	$wave = _test_ppdb_with_class($cover[0], 'pg-podcast-wave');
	pg_assert_same('true', _test_ppdb_attr($wave[0], 'aria-hidden'), 'the wave is hidden from screen readers');
}

// What a phone, a 1024 px screen and the dark theme showed: the header
// opens out from 1200 px, where its six links and the button fit; a
// picture's size is its width and height props, as the image renderer
// drops a width or height among the Attributes panel's entries; on the
// dark theme's tertiary bands coloured text takes the emphasis tint and
// a secondary link or outline button the muted body colour.
function test_podcast_blocks_header_pictures_and_dark_bands()
{
	include PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-podcast/parts.php';
	$navs = _test_ppdb_with_class($header(), 'navbar');
	pg_assert_same(1, count($navs), 'one navbar');
	pg_assert_contains('navbar-expand-xl', $navs[0]['props']['cssClass'], 'the menu opens out from 1200 px');
	pg_assert_false(strpos(json_encode($navs[0]), '-lg-') !== false, 'no lg utility left in the menu');

	$tpl = _test_ppd_asm_tpl();
	$trees = array();
	foreach ($tpl['pages'] as $p) $trees[$p['key']] = $p['tree'];
	foreach ($tpl['widgets'] as $key => $w) $trees['widget ' . $key] = $w['tree'];
	$sized = 0;
	foreach ($trees as $key => $tree) {
		$nodes = array();
		_test_ppd_asm_nodes($tree, $nodes);
		foreach ($nodes as $n) {
			if (($n['type'] ?? '') !== 'content' || ($n['props']['contentType'] ?? '') !== 'image') continue;
			foreach (array('width', 'height') as $side) {
				pg_assert_true(_test_ppdb_attr($n, $side) === null, "$key: an image's $side is a prop, not an attribute");
			}
			if (isset($n['props']['width'])) $sized++;
		}
	}
	pg_assert_true($sized >= 3, 'the portraits carry their size');

	$css = $tpl['assets']['css'];
	foreach (array(':is(.text-primary,.link-primary){color:var(--bs-primary-text-emphasis)!important}',
		'.link-secondary{color:var(--bs-secondary-color)!important}',
		'.btn-outline-primary{--bs-btn-color:var(--bs-primary-text-emphasis)}',
		'.btn-outline-secondary{--bs-btn-color:var(--bs-secondary-color)}') as $rule) {
		pg_assert_contains('[data-bs-theme=dark] .bg-body-tertiary ' . $rule, $css, 'dark tertiary band: ' . $rule);
	}
}
