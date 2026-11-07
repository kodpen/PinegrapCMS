<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Sports Club design template's collections
 * (includes/design_templates/pinegrap-sportsclub/squad.php and
 * matches.php): the pages and widgets of the plan, the Players and
 * Matches forms with their fields and samples (every pick list value an
 * answer, adult teams only in the squad, played matches with a score and
 * an outcome, the sample matches around today), lists and record pages
 * that read only fields the forms have, the filters (?team=, from today,
 * before today), links that name template pages, the template's ids and
 * classes, no fixed dates and every literal in Turkish.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The page keys of the finished template (_test_sp_page_keys()).
require_once __DIR__ . '/design_template_sportsclub_test.php';

// parts.php, squad.php and matches.php in one scope, as the template file
// reads them. Read once per run.
function _test_spc_load()
{
	static $out = null;
	if ($out !== null) return $out;
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-sportsclub';
	include $dir . '/parts.php';
	$squad_part = include $dir . '/squad.php';
	$match_part = include $dir . '/matches.php';
	$parts = array('squad' => $squad_part, 'matches' => $match_part);
	$pages = array();
	$widgets = array();
	foreach ($parts as $part) {
		foreach ($part['pages'] as $p) $pages[$p['key']] = $p;
		$widgets = array_merge($widgets, $part['widgets']);
	}
	$out = array('parts' => $parts, 'pages' => $pages, 'widgets' => $widgets, 'teams' => $teams, 'players' => $players,
		'matches' => $matches, 'team_titles' => $team_titles, 'squad_team_titles' => $squad_team_titles,
		'position_titles' => $position_titles, 'competition_titles' => $competition_titles, 'venue_titles' => $venue_titles,
		'outcome_titles' => $outcome_titles, 'featured' => $sp_featured_choices);
	return $out;
}

// Every node of a tree, depth first.
function _test_spc_nodes($node, &$out)
{
	if (!is_array($node)) return;
	if (isset($node['props'])) $out[] = $node;
	foreach ((array)($node['children'] ?? array()) as $c) _test_spc_nodes($c, $out);
}

// The name, type, options and _cf of every form control of a tree.
function _test_spc_controls($node)
{
	$nodes = array();
	_test_spc_nodes($node, $nodes);
	$out = array();
	foreach ($nodes as $n) {
		$tag = $n['props']['tag'] ?? '';
		if (!in_array($tag, array('input', 'select', 'textarea'), true)) continue;
		$name = '';
		$type = '';
		foreach ((array)($n['props']['_attrs'] ?? array()) as $a) {
			if ($a['name'] === 'name') $name = $a['value'];
			if ($a['name'] === 'type') $type = $a['value'];
		}
		$options = array();
		foreach ((array)($n['children'] ?? array()) as $o) {
			foreach ((array)($o['props']['_attrs'] ?? array()) as $a) {
				if ($a['name'] === 'value' && $a['value'] !== '') $options[] = $a['value'];
			}
		}
		$out[$name] = array('tag' => $tag, 'type' => $type, 'options' => $options, 'cf' => $n['props']['_cf'] ?? array());
	}
	return $out;
}

// The tokens a tree's bindings read.
function _test_spc_tokens($tree)
{
	$nodes = array();
	_test_spc_nodes($tree, $nodes);
	$tokens = array();
	foreach ($nodes as $n) {
		foreach ((array)($n['props']['_bindings'] ?? array()) as $t) $tokens[$t] = true;
	}
	return array_keys($tokens);
}

function test_sportsclub_collections_pages_and_widgets()
{
	$d = _test_spc_load();
	$keys = function ($list) { return array_map(function ($p) { return $p['key']; }, $list); };
	pg_assert_same(array('squad', 'player', 'player_new'), $keys($d['parts']['squad']['pages']));
	pg_assert_same(array('fixtures', 'results', 'match', 'match_new'), $keys($d['parts']['matches']['pages']));
	pg_assert_same(array('player_form', 'players_list', 'player_detail'), array_keys($d['parts']['squad']['widgets']));
	pg_assert_same(array('match_form', 'fixtures_list', 'results_list', 'home_fixtures', 'home_results', 'match_detail'), array_keys($d['parts']['matches']['widgets']));
	foreach (array('squad', 'player', 'fixtures', 'results', 'match') as $key) pg_assert_same('public', $d['pages'][$key]['folder'], "$key is public");
	foreach (array('player_new', 'match_new') as $key) {
		pg_assert_same('private', $d['pages'][$key]['folder'], "only the staff open $key");
		pg_assert_true(!empty($d['pages'][$key]['noindex']), "$key is not indexed");
	}
	foreach ($d['pages'] as $key => $p) {
		foreach (array('name', 'title', 'tree') as $f) pg_assert_true(!empty($p[$f]), "$key has $f");
		pg_assert_false(isset($p['requires']), "$key stands without the shop");
		pg_assert_same('root', $p['tree']['type'], "$key: a page tree");
		pg_assert_true(mb_strlen((string)($p['meta_description'] ?? ''), 'UTF-8') <= 160, "$key: meta description fits a result line");
		pg_assert_true((bool)preg_match('/^[a-z0-9-]+$/', $p['name']), "$key: the page name is an address");
	}
	foreach ($d['widgets'] as $key => $w) {
		pg_assert_false(isset($w['requires']), "$key stands without the shop");
		pg_assert_true($w['page'] === 'home' || isset($d['pages'][$w['page']]), "$key sits on a page of this part or the home");
		if ($w['page'] !== 'home') {
			pg_assert_contains('"templateWidget":"' . $key . '"', json_encode($d['pages'][$w['page']]['tree']), "$key is placed on its page");
		}
	}
	pg_assert_same('home', $d['widgets']['home_fixtures']['page']);
	pg_assert_same('home', $d['widgets']['home_results']['page']);
}

// Each form is a collection with one sample per record of parts.php; every
// sample field has its control and is filled, a pick list's value is one
// of its answers and featured is the staff's.
function test_sportsclub_collections_forms_and_samples()
{
	$d = _test_spc_load();
	$forms = array(
		'player_new' => array('player_form', 'players', array('name', 'team', 'position', 'shirt_number', 'summary', 'bio', 'photo', 'featured')),
		'match_new'  => array('match_form', 'matches', array('title', 'team', 'opponent', 'competition', 'venue', 'match_date', 'kickoff', 'score', 'outcome', 'report', 'video_url', 'featured')),
	);
	foreach ($forms as $page_key => $w) {
		$form = $d['pages'][$page_key]['form'];
		pg_assert_same(1, $form['collection'], "$page_key: a collection");
		pg_assert_same(count($d[$w[1]]), count($form['sample_records']), "$page_key: one sample per record");
		$controls = _test_spc_controls($d['widgets'][$w[0]]['tree']);
		pg_assert_same($w[2], array_keys($controls), "$page_key: its fields");
		pg_assert_true(!empty($controls['featured']['cf']['office_use_only']), "$page_key: featured is the staff's");
		foreach ($form['sample_records'] as $n => $r) {
			foreach ($r as $name => $value) {
				pg_assert_true(isset($controls[$name]), "$page_key sample $n: field $name exists");
				pg_assert_true($value !== '', "$page_key sample $n: $name filled");
				if ($controls[$name]['tag'] === 'select') {
					pg_assert_true(in_array($value, $controls[$name]['options'], true), "$page_key sample $n: $name '$value' is an answer");
				}
			}
			pg_assert_true(in_array($r['featured'], $d['featured'], true), "$page_key sample $n: featured is Yes or No");
		}
	}
	$p = _test_spc_controls($d['widgets']['player_form']['tree']);
	pg_assert_same('image', $p['photo']['cf']['kind'] ?? '', 'the photo is an image');
	pg_assert_same('photo', $d['pages']['player_new']['form']['share_image_field'], 'the photo is the share image');
	pg_assert_same('number', $p['shirt_number']['cf']['kind'] ?? '', 'the shirt number sorts as a number');
	pg_assert_same($d['squad_team_titles'], $p['team']['options'], 'a player belongs to a senior team');
	foreach ($d['teams'] as $key => $t) {
		pg_assert_same(!$t['youth'], in_array($t['title'], $d['squad_team_titles'], true), "team $key: in the squad list only when senior");
	}
	pg_assert_same($d['position_titles'], $p['position']['options'], 'the positions');
	$m = _test_spc_controls($d['widgets']['match_form']['tree']);
	pg_assert_same('date', $m['match_date']['type'], 'the match date is a date');
	pg_assert_same('url', $m['video_url']['type'], 'the video is an address');
	pg_assert_same($d['team_titles'], $m['team']['options'], 'every team plays matches');
	pg_assert_same($d['competition_titles'], $m['competition']['options'], 'the competitions');
	pg_assert_same($d['venue_titles'], $m['venue']['options'], 'home or away');
	pg_assert_same($d['outcome_titles'], $m['outcome']['options'], 'the outcomes');
	// The sample matches: those to come after today with no score, the
	// played ones before today with a score and an outcome.
	$today = (new DateTime('today'))->format('Y-m-d');
	$to_come = 0;
	foreach ($d['pages']['match_new']['form']['sample_records'] as $n => $r) {
		if ($r['match_date'] > $today) {
			$to_come++;
			pg_assert_false(isset($r['score']) || isset($r['outcome']), "match sample $n: no score before it is played");
		} else {
			pg_assert_true($r['match_date'] < $today, "match sample $n: not today");
			pg_assert_true(isset($r['score'], $r['outcome']), "match sample $n: a played match has its score");
		}
	}
	$want = 0;
	foreach ($d['matches'] as $mt) if ($mt['weeks'] >= 0) $want++;
	pg_assert_same($want, $to_come, 'the matches to come');
}

// The cards and rows of the lists, and the record pages, read only fields
// the forms have (or the renderer's own tokens).
function test_sportsclub_collections_bindings_name_fields()
{
	$d = _test_spc_load();
	$own = array('form_item_view', 'not_found', '__edit_url', 'can_edit', 'record_found', 'record_not_found');
	$map = array(
		'player_form' => array('players_list', 'player_detail'),
		'match_form'  => array('fixtures_list', 'results_list', 'home_fixtures', 'home_results', 'match_detail'),
	);
	foreach ($map as $form => $lists) {
		$fields = array_keys(_test_spc_controls($d['widgets'][$form]['tree']));
		foreach ($lists as $key) {
			foreach (_test_spc_tokens($d['widgets'][$key]['tree']) as $t) {
				pg_assert_true(in_array($t, $fields, true) || in_array($t, $own, true), "$key reads $t, a field of $form");
			}
			foreach ((array)($d['widgets'][$key]['config']['filters'] ?? array()) as $f) {
				pg_assert_true(in_array($f['field'], $fields, true), "$key filters on {$f['field']}, a field of $form");
			}
		}
	}
	$detail = json_encode($d['widgets']['match_detail']['tree']);
	foreach (array('score', 'outcome', 'report') as $t) pg_assert_contains('"' . $t . '"', $detail, "the match page shows $t");
	pg_assert_contains('"show_if":"video_url"', $detail, 'the video button leaves without an address');
	foreach (array('player_detail', 'match_detail') as $key) {
		$json = json_encode($d['widgets'][$key]['tree']);
		pg_assert_contains('"eo_visible_if":"record_found"', $json, "$key: the record");
		pg_assert_contains('"eo_visible_if":"record_not_found"', $json, "$key: the not-found note");
		pg_assert_contains('__edit_url', $json, "$key: the staff's Edit button");
	}
}

// The squad reads ?team=, the fixtures ?team= and the days from today on,
// the results ?team= and the days before today; each filter link names a
// Team answer.
function test_sportsclub_collections_filters()
{
	$d = _test_spc_load();
	$w = $d['widgets'];
	$team = array('field' => 'team', 'operator' => 'is equal to', 'value' => '', 'dynamic_value' => 'url parameter', 'dynamic_value_attribute' => 'team');
	$from_today = array('field' => 'match_date', 'operator' => 'is greater than or equal to', 'value' => '', 'dynamic_value' => 'current date');
	$before_today = array('field' => 'match_date', 'operator' => 'is less than', 'value' => '', 'dynamic_value' => 'current date');
	pg_assert_same(array($team), $w['players_list']['config']['filters'], 'the squad by team');
	pg_assert_same(array($from_today, $team), $w['fixtures_list']['config']['filters'], 'the fixtures from today, by team');
	pg_assert_same(array($before_today, $team), $w['results_list']['config']['filters'], 'the results before today, by team');
	pg_assert_same(array($from_today), $w['home_fixtures']['config']['filters'], 'the home fixtures from today');
	pg_assert_same(array($before_today), $w['home_results']['config']['filters'], 'the home results before today');
	foreach (array('fixtures_list' => 'ASC', 'home_fixtures' => 'ASC', 'results_list' => 'DESC', 'home_results' => 'DESC') as $key => $dir) {
		pg_assert_same(array('match_date', $dir), array($w[$key]['config']['order_by_field'], $w[$key]['config']['order_by_direction']), "$key: in date order");
		pg_assert_same('{{tab:match}}', $w[$key]['config']['detail_page_id'], "$key opens the match page");
		pg_assert_same('{{tab:match_new}}', $w[$key]['config']['custom_form_page_id'], "$key reads the Matches form");
	}
	pg_assert_same(array('shirt_number', 'ASC'), array($w['players_list']['config']['order_by_field'], $w['players_list']['config']['order_by_direction']), 'the squad by shirt number');
	pg_assert_same('{{tab:player}}', $w['players_list']['config']['detail_page_id'], 'the squad opens the player page');
	$answers = array('squad' => $d['squad_team_titles'], 'fixtures' => $d['team_titles'], 'results' => $d['team_titles']);
	foreach ($answers as $page_key => $list) {
		$nodes = array();
		_test_spc_nodes($d['pages'][$page_key]['tree'], $nodes);
		$seen = array();
		foreach ($nodes as $n) {
			$href = (string)($n['props']['href'] ?? '');
			if (strpos($href, '{{page:' . $page_key . '}}?') !== 0) continue;
			parse_str(substr($href, strlen('{{page:' . $page_key . '}}?')), $q);
			pg_assert_same(array('team'), array_keys($q), "$page_key: the link sets ?team=");
			pg_assert_true(in_array($q['team'], $list, true), "$page_key: ?team={$q['team']} is an answer");
			$seen[$q['team']] = true;
		}
		pg_assert_same(count($list), count($seen), "$page_key: a link per team");
	}
}

// Every link names a page the finished template carries; no tree links a
// shop page; ids and classes are the template's.
function test_sportsclub_collections_links_ids_and_classes()
{
	$d = _test_spc_load();
	$known = array_flip(_test_sp_page_keys());
	$json = json_encode(array($d['pages'], $d['widgets']));
	preg_match_all('/\{\{(?:page|tab):([a-z0-9_]+)\}\}/', $json, $m);
	foreach (array_unique($m[1]) as $key) pg_assert_true(isset($known[$key]), "page link {{page:$key}} names a template page");
	foreach (_test_sp_shop_keys() as $key) {
		pg_assert_false(strpos($json, '{{page:' . $key . '}}') !== false, "no tree links to $key");
	}
	$nodes = array();
	foreach ($d['pages'] as $p) _test_spc_nodes($p['tree'], $nodes);
	foreach ($d['widgets'] as $w) if (is_array($w['tree'])) _test_spc_nodes($w['tree'], $nodes);
	foreach ($nodes as $n) {
		foreach (preg_split('/\s+/', (string)($n['props']['cssClass'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $c) {
			pg_assert_false((bool)preg_match('/^(pgy|pmu|pmz|psh|pcn|ptv|pwd|pph|psc|pre|pev|pdp|plx|pht)-/', $c), "class $c is not another template's");
			pg_assert_false(strpos($c, 'opacity-') === 0, "no faded text ($c)");
		}
		$id = (string)($n['props']['id'] ?? '');
		if ($id !== '' && strpos($id, '-') !== false) pg_assert_true(strpos($id, 'sp-') === 0, "id $id is the template's");
	}
}

// The source carries no fixed date: the sample matches are counted from
// today.
function test_sportsclub_collections_no_fixed_dates()
{
	foreach (array('squad.php', 'matches.php') as $file) {
		$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-sportsclub/' . $file);
		$src = preg_replace('/@copyright[^\n]*/', '', $src);
		pg_assert_false((bool)preg_match('/\b(19|20)[0-9]{2}\b/', $src), "$file: no year");
		pg_assert_false((bool)preg_match('/\b(January|February|March|April|June|July|August|September|October|November|December)\b/', $src), "$file: no month");
	}
}

// Every literal has its Turkish; a squad reads "kadro", a team "takım".
function test_sportsclub_collections_turkish_words()
{
	$map = pg_test_language_map('tr');
	foreach (array('squad.php', 'matches.php') as $file) {
		$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-sportsclub/' . $file);
		preg_match_all("/lang\\('((?:[^'\\\\]|\\\\.)*)'\\)/", $src, $m);
		foreach (array_unique($m[1]) as $raw) {
			$key = stripslashes($raw);
			pg_assert_true(isset($map[$key]) && $map[$key] !== '', "$file: Turkish for '$key'");
			if (!isset($map[$key])) continue;
			if (preg_match('/\bSquads?\b/', $key)) pg_assert_true(mb_stripos($map[$key], 'kadro', 0, 'UTF-8') !== false, "'$key' says kadro");
			if (preg_match('/\bTeams?\b/', $key)) pg_assert_true(mb_stripos($map[$key], 'takım', 0, 'UTF-8') !== false, "'$key' says takım");
		}
		foreach (array('Home', 'Away', 'Draw', 'Forward', 'Friendly', 'Full time') as $k) {
			pg_assert_false(strpos($src, "lang('" . $k . "')") !== false, "$file does not use lang('$k')");
		}
	}
}
