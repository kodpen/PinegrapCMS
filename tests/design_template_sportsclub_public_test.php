<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Sports Club design template's public pages
 * (includes/design_templates/pinegrap-sportsclub/pages_public.php): the
 * pages of the plan (one per team among them) in the public folder and
 * without the shop, links that name template pages and no shop page, the
 * widgets only the home places, each team page opening its squad, its
 * fixtures and results and the right request form with the team chosen,
 * the sponsors page offering every package, the template's ids and
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

// parts.php and the public pages in one scope, as the template file reads
// them. Read once per run.
function _test_spp_load()
{
	static $out = null;
	if ($out !== null) return $out;
	include PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-sportsclub/parts.php';
	include PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-sportsclub/blocks.php';
	$public_part = include PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-sportsclub/pages_public.php';
	$out = array('part' => $public_part, 'teams' => $teams, 'sponsor_packages' => $sponsor_packages,
		'team_titles' => $team_titles, 'youth_team_titles' => $youth_team_titles, 'package_titles' => $package_titles,
		'trial_team_choices' => $trial_team_choices);
	return $out;
}

// Every node of a tree, depth first.
function _test_spp_nodes($node, &$out)
{
	if (!is_array($node)) return;
	if (isset($node['props'])) $out[] = $node;
	if (!empty($node['children']) && is_array($node['children'])) {
		foreach ($node['children'] as $child) _test_spp_nodes($child, $out);
	}
}

// The pages of this part, by key.
function _test_spp_pages()
{
	$pages = array();
	foreach (_test_spp_load()['part']['pages'] as $p) $pages[$p['key']] = $p;
	return $pages;
}

// The query of each link of a tree that opens the given page with one.
function _test_spp_queries($tree, $page)
{
	$nodes = array();
	_test_spp_nodes($tree, $nodes);
	$out = array();
	foreach ($nodes as $n) {
		$href = (string)($n['props']['href'] ?? '');
		if (strpos($href, '{{page:' . $page . '}}?') !== 0) continue;
		parse_str(substr($href, strlen('{{page:' . $page . '}}?')), $q);
		$out[] = $q;
	}
	return $out;
}

function test_sportsclub_public_pages_are_the_plans()
{
	$d = _test_spp_load();
	$part = $d['part'];
	pg_assert_same(array('pages', 'widgets'), array_keys($part), 'the part returns pages and widgets');
	$want = array('home', 'teams');
	foreach (array_keys($d['teams']) as $key) $want[] = 'team_' . $key;
	$want = array_merge($want, array('club', 'ground', 'academy', 'sponsors', 'faq'));
	pg_assert_same($want, array_keys(_test_spp_pages()), 'the public pages, one per team');
	$names = array();
	foreach (_test_spp_pages() as $key => $p) {
		foreach (array('name', 'folder', 'title', 'meta_description', 'tree') as $field) {
			pg_assert_true(isset($p[$field]) && $p[$field] !== '', "$key has $field");
		}
		pg_assert_same('public', $p['folder'], "$key is in the public folder");
		pg_assert_false(isset($p['requires']), "$key stands without the shop");
		pg_assert_true(mb_strlen($p['meta_description'], 'UTF-8') <= 160, "$key: meta description fits a result line");
		pg_assert_true((bool)preg_match('/^[a-z0-9-]+$/', $p['name']), "$key: the page name is an address");
		pg_assert_false(isset($names[$p['name']]), "$key: no page name twice");
		$names[$p['name']] = true;
	}
}

// Every link names a page the finished template carries; none names a
// page that only the shop makes.
function test_sportsclub_public_links_name_template_pages()
{
	$known = array_flip(_test_sp_page_keys());
	$json = json_encode(_test_spp_pages());
	preg_match_all('/\{\{(?:page|tab):([a-z0-9_]+)\}\}/', $json, $m);
	pg_assert_true(count(array_unique($m[1])) > 15, 'links found');
	foreach (array_unique($m[1]) as $key) pg_assert_true(isset($known[$key]), "page link {{page:$key}} names a template page");
	foreach (_test_sp_shop_keys() as $key) {
		pg_assert_false(strpos($json, '{{page:' . $key . '}}') !== false, "no public page links $key");
	}
}

// The home places the next matches, the last results, the Google reviews
// and the news, the widgets the matches and the standard parts define;
// this part defines none.
function test_sportsclub_public_home_widgets()
{
	pg_assert_same(array(), _test_spp_load()['part']['widgets'], 'no widget of its own');
	$widgets = array();
	foreach (_test_spp_pages() as $key => $p) {
		$nodes = array();
		_test_spp_nodes($p['tree'], $nodes);
		foreach ($nodes as $n) {
			if (isset($n['props']['templateWidget'])) $widgets[$key][] = $n['props']['templateWidget'];
		}
	}
	pg_assert_same(array('home' => array('home_fixtures', 'home_results', 'home_events', 'google_reviews', 'home_posts')), $widgets, 'only the home places widgets');
}

// A team page opens its fixtures and results filtered on the team; an
// adult team's page its squad and the trial request, a youth team's the
// academy registration, each with the team chosen in the words the forms'
// lists use.
function test_sportsclub_public_team_pages_choose_the_team()
{
	$d = _test_spp_load();
	$pages = _test_spp_pages();
	foreach ($d['teams'] as $key => $t) {
		$tree = $pages['team_' . $key]['tree'];
		foreach (array('fixtures', 'results') as $list) {
			$q = _test_spp_queries($tree, $list);
			pg_assert_true(count($q) >= 1, "team $key: links $list");
			foreach ($q as $one) pg_assert_same($t['title'], $one['team'], "team $key: $list filtered on the team");
		}
		$form = $t['youth'] ? 'academy_register' : 'trial';
		$q = _test_spp_queries($tree, $form);
		pg_assert_true(count($q) >= 1, "team $key: opens $form");
		$answers = $t['youth'] ? $d['youth_team_titles'] : $d['trial_team_choices'];
		foreach ($q as $one) {
			pg_assert_same($t['title'], $one['team'], "team $key: $form with the team chosen");
			pg_assert_true(in_array($one['team'], $answers, true), "team $key: a Team answer of $form");
		}
		$squad = _test_spp_queries($tree, 'squad');
		if ($t['youth']) {
			pg_assert_same(array(), $squad, "team $key: a youth team names no squad");
		} else {
			pg_assert_true(count($squad) >= 1, "team $key: opens its squad");
			foreach ($squad as $one) pg_assert_same($t['title'], $one['team'], "team $key: squad filtered on the team");
		}
	}
	// The teams page and the academy link every team page and every youth
	// team page.
	$teams_json = json_encode($pages['teams']['tree']);
	foreach (array_keys($d['teams']) as $key) pg_assert_contains('{{page:team_' . $key . '}}', $teams_json, "teams page links team $key");
	$academy = _test_spp_queries($pages['academy']['tree'], 'academy_register');
	$named = array();
	foreach ($academy as $q) if (isset($q['team'])) $named[$q['team']] = true;
	foreach ($d['youth_team_titles'] as $title) pg_assert_true(isset($named[$title]), "academy registers for $title");
}

// The sponsors page offers every package once, opening the enquiry with
// the package chosen.
function test_sportsclub_public_sponsors_offer_every_package()
{
	$d = _test_spp_load();
	$named = array();
	foreach (_test_spp_queries(_test_spp_pages()['sponsors']['tree'], 'sponsor_enquiry') as $q) {
		if (!isset($q['package'])) continue;
		pg_assert_true(in_array($q['package'], $d['package_titles'], true), 'the link names a Package answer');
		$named[] = $q['package'];
	}
	$all = $d['package_titles'];
	sort($all);
	sort($named);
	pg_assert_same($all, $named, 'every package once');
}

// Search anchors and ids carry the template's prefix, classes are its own
// or Bootstrap's, no Bootstrap 5.3 class that does not exist and no faded
// text.
function test_sportsclub_public_ids_and_classes()
{
	$ids = array();
	foreach (_test_spp_pages() as $key => $p) {
		$nodes = array();
		_test_spp_nodes($p['tree'], $nodes);
		foreach ($nodes as $n) {
			foreach (preg_split('/\s+/', (string)($n['props']['cssClass'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $c) {
				pg_assert_false((bool)preg_match('/^(pgy|pmu|pmz|psh|pcn|ptv|pwd|pph|psc|pre|pev|pdp|plx|pht)-/', $c), "$key: class $c is not another template's");
				pg_assert_false((bool)preg_match('/^[pm][trblxy]?(-[a-z]{2,3})?-[6-9]$/', $c), "$key: class $c exists in Bootstrap 5.3");
				pg_assert_false(strpos($c, 'opacity-') === 0, "$key: no faded text ($c)");
				pg_assert_false($c === 'ratio-3x4', "$key: Bootstrap has no ratio-3x4");
			}
			$id = (string)($n['props']['id'] ?? '');
			if ($id === '') continue;
			pg_assert_true(strpos($id, 'sp-') === 0, "$key: id $id is the template's");
			pg_assert_false(isset($ids[$key][$id]), "$key: id $id once on the page");
			$ids[$key][$id] = true;
		}
		pg_assert_true(!empty($ids[$key]), "$key has a search section");
	}
}

// The sample is never dated: a year or a month would read as a past
// season a year later.
function test_sportsclub_public_no_fixed_dates()
{
	$json = json_encode(_test_spp_pages(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	// The SVG namespace and the escapes of a maps address are not dates.
	$json = preg_replace('/%[0-9A-F]{2}/', '', str_replace('www.w3.org/2000/svg', '', $json));
	pg_assert_false((bool)preg_match('/\b(19|20)[0-9]{2}\b/', $json), 'no year in the sample');
	pg_assert_false((bool)preg_match('/\b(January|February|March|April|June|July|August|September|October|November|December)\b/', $json), 'no month in the sample');
}

// Every literal has its Turkish, and a team reads "takım", a squad
// "kadro", the youth academy "altyapı"; shared keys whose Turkish names
// another thing are not used.
function test_sportsclub_public_turkish_words()
{
	$map = pg_test_language_map('tr');
	$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-sportsclub/pages_public.php');
	preg_match_all("/lang\\('((?:[^'\\\\]|\\\\.)*)'\\)/", $src, $m);
	foreach (array_unique($m[1]) as $raw) {
		$key = stripslashes($raw);
		pg_assert_true(isset($map[$key]) && $map[$key] !== '', "Turkish for '$key'");
		if (!isset($map[$key])) continue;
		if (preg_match('/\bTeams?\b/', $key)) pg_assert_true(mb_stripos($map[$key], 'takım', 0, 'UTF-8') !== false, "'$key' says takım");
		if (preg_match('/\bSquads?\b/', $key)) pg_assert_true(mb_stripos($map[$key], 'kadro', 0, 'UTF-8') !== false, "'$key' says kadro");
		if (preg_match('/\bYouth Academy\b/', $key)) pg_assert_true(mb_stripos($map[$key], 'altyapı', 0, 'UTF-8') !== false, "'$key' says altyapı");
	}
	foreach (array('Home', 'Away', 'Draw', 'Forward', 'Friendly', 'Testimonials', 'Classes') as $k) {
		pg_assert_false(strpos($src, "lang('" . $k . "')") !== false, "the pages do not use lang('$k')");
	}
}
