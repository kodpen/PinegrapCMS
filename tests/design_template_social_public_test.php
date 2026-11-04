<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Social Community design template's public pages
 * (includes/design_templates/pinegrap-social/pages_public.php): the eight
 * pages load from the parts, each has its key, folder, title and
 * description, every page link names a page of the finished template, the
 * home page places the two lists of other parts, every topic has its
 * anchored search section, anchors are unique, nothing offers likes,
 * following or messages between members, the classes are the template's
 * own, no date is written into the sample, and every literal has its
 * Turkish.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(__DIR__ . '/helpers/language_map.php');
// The page keys of the finished template and the node walker.
require_once(__DIR__ . '/design_template_social_test.php');

// The public pages as the template file reads them: parts.php first, then
// pages_public.php in the same scope. Read once per run.
function _test_social_public_load()
{
	static $out = null;
	if ($out !== null) return $out;
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-social';
	include $dir . '/parts.php';
	$part = include $dir . '/pages_public.php';
	$out = array('part' => $part, 'topics' => $topics);
	return $out;
}

function test_social_public_pages_are_the_plans()
{
	$d = _test_social_public_load();
	pg_assert_same(array(), $d['part']['widgets'], 'no widget of its own');
	$keys = array();
	foreach ($d['part']['pages'] as $p) {
		$keys[] = $p['key'];
		pg_assert_same('public', $p['folder'], $p['key'] . ': public folder');
		pg_assert_true($p['name'] !== '' && $p['title'] !== '' && $p['meta_description'] !== '', $p['key'] . ': name, title, description');
		pg_assert_same('root', $p['tree']['type'], $p['key'] . ': a page tree');
	}
	pg_assert_same(array('home', 'topics', 'about', 'guidelines', 'faq', 'help', 'privacy', 'terms'), $keys);
}

function test_social_public_links_and_lists()
{
	$d = _test_social_public_load();
	$json = json_encode($d['part']['pages']);
	$known = array_flip(_test_social_page_keys());
	preg_match_all('/\{\{(?:page|tab):([a-z0-9_]+)\}\}/', $json, $m);
	foreach (array_unique($m[1]) as $key) pg_assert_true(isset($known[$key]), "page link {{page:$key}} names a template page");
	foreach (array('home_featured_posts', 'home_latest_posts', 'home_news', 'newsletter_band') as $w) pg_assert_contains('"templateWidget":"' . $w . '"', $json, "home places $w");
	pg_assert_contains('"templateShared":"join_band"', $json, 'the join band');
}

// Every topic's anchor is a search section on the topics page; anchors and
// field ids are unique on each page.
function test_social_public_anchors()
{
	$d = _test_social_public_load();
	foreach ($d['part']['pages'] as $p) {
		$nodes = array();
		_test_social_nodes($p['tree'], $nodes);
		$ids = array();
		$sections = array();
		foreach ($nodes as $n) {
			if (!isset($n['props']['id'])) continue;
			pg_assert_false(isset($ids[$n['props']['id']]), $p['key'] . ': id ' . $n['props']['id'] . ' once');
			$ids[$n['props']['id']] = true;
			if (!empty($n['props']['searchSection'])) $sections[$n['props']['id']] = true;
		}
		if ($p['key'] === 'topics') {
			foreach (array_keys($d['topics']) as $key) pg_assert_true(isset($sections['topic-' . $key]), "topic-$key is a search section");
		}
		if ($p['key'] !== 'home') pg_assert_true(count($sections) > 0, $p['key'] . ': has search sections');
	}
}

// The community has no likes, no following and no member-to-member
// messages: no page offers them as a feature.
function test_social_public_offers_no_likes_or_follows()
{
	$d = _test_social_public_load();
	$nodes = array();
	foreach ($d['part']['pages'] as $p) _test_social_nodes($p['tree'], $nodes);
	foreach ($nodes as $n) {
		$c = (string)($n['props']['cssClass'] ?? '');
		pg_assert_false((bool)preg_match('/\bbi-(heart-fill|hand-thumbs-up|person-plus-fill|envelope-heart)\b/', $c), "icon $c offers no like or follow");
		$href = (string)($n['props']['href'] ?? '');
		pg_assert_false((bool)preg_match('/like|follow|message/i', $href), "link $href");
	}
}

function test_social_public_classes_and_dates()
{
	$d = _test_social_public_load();
	$nodes = array();
	foreach ($d['part']['pages'] as $p) _test_social_nodes($p['tree'], $nodes);
	foreach ($nodes as $n) {
		foreach (preg_split('/\s+/', (string)($n['props']['cssClass'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $c) {
			pg_assert_false((bool)preg_match('/^(pre|pev|pf|pdp|pm|pma|pmk|pht|pdl|pco|pc|pl|plx|plw|psl|pn)-/', $c), "class $c is not another template's");
		}
		if (strpos((string)($n['props']['cssClass'] ?? ''), 'bi ') === 0) pg_assert_contains('aria-hidden', json_encode($n), 'icon hidden from a screen reader');
		if (($n['props']['contentType'] ?? '') === 'image' && strpos((string)$n['props']['cssClass'], 'psc-avatar') === false) {
			pg_assert_true((string)$n['props']['alt'] !== '', 'a photo is described');
		}
	}
	$json = json_encode($d['part']['pages'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	pg_assert_false((bool)preg_match('/\b(19|20)[0-9]{2}\b/', $json), 'no year in the sample');
	pg_assert_false((bool)preg_match('/\b(January|February|March|April|June|July|August|September|October|November|December)\b/', $json), 'no month in the sample');
}

// Every literal has its Turkish, and a post is a "gönderi" there.
function test_social_public_turkish_words()
{
	$map = pg_test_language_map('tr');
	$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-social/pages_public.php');
	preg_match_all("/lang\\('((?:[^'\\\\]|\\\\.)*)'\\)/", $src, $m);
	foreach (array_unique($m[1]) as $raw) {
		$key = stripslashes($raw);
		pg_assert_true(isset($map[$key]) && $map[$key] !== '', "Turkish for '$key'");
		if (preg_match('/\bposts\b/i', $key) && isset($map[$key])) {
			pg_assert_true(mb_stripos($map[$key], 'gönderi', 0, 'UTF-8') !== false, "'$key' says gönderi");
		}
	}
}
