<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Podcast Network design template's collections
 * (includes/design_templates/pinegrap-podcast/podcasts.php and
 * episodes.php): the pages and widgets of each, the Podcasts and Episodes
 * forms with a sample per record (every sample value has its field, a pick
 * list's value is an answer, an episode names a sample podcast by title),
 * the episode's Podcast field is a reference into the Podcasts form, the
 * recording is an audio file in the episodes folder, the lists and record
 * pages read only the forms' fields, the filters (category from the
 * address, featured, the episodes already out, the podcast page's own
 * episodes), the record pages, links, ids and classes, no fixed date and
 * every literal in Turkish.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The page keys of the finished template and the shop's pages.
require_once(__DIR__ . '/design_template_podcast_test.php');

// parts.php, podcasts.php and episodes.php in one scope, as the template
// file reads them. Read once per run.
function _test_ppdc_load()
{
	static $out = null;
	if ($out !== null) return $out;
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-podcast';
	include $dir . '/parts.php';
	$podcast_part = include $dir . '/podcasts.php';
	$episode_part = include $dir . '/episodes.php';
	$parts = array('podcasts' => $podcast_part, 'episodes' => $episode_part);
	$pages = array();
	$widgets = array();
	foreach ($parts as $name => $part) {
		pg_assert_same(array('pages', 'widgets'), array_keys($part), "$name returns pages and widgets");
		foreach ($part['pages'] as $p) $pages[$p['key']] = $p;
		$widgets = array_merge($widgets, $part['widgets']);
	}
	$out = array('parts' => $parts, 'pages' => $pages, 'widgets' => $widgets, 'podcasts' => $podcasts, 'episodes' => $episodes,
		'category_titles' => $category_titles, 'chapters_text' => $chapters_text, 'aired_on' => $aired_on);
	return $out;
}

// The name, type, options and _cf of every form control of a tree.
function _test_ppdc_controls($node)
{
	$nodes = array();
	_test_ppd_nodes($node, $nodes);
	$out = array();
	foreach ($nodes as $n) {
		$tag = $n['props']['tag'] ?? '';
		if (!in_array($tag, array('input', 'select', 'textarea'), true)) continue;
		$name = '';
		$type = '';
		$accept = '';
		foreach ((array)($n['props']['_attrs'] ?? array()) as $a) {
			if ($a['name'] === 'name') $name = $a['value'];
			if ($a['name'] === 'type') $type = $a['value'];
			if ($a['name'] === 'accept') $accept = $a['value'];
		}
		$options = array();
		foreach ((array)($n['children'] ?? array()) as $o) {
			foreach ((array)($o['props']['_attrs'] ?? array()) as $a) {
				if ($a['name'] === 'value' && $a['value'] !== '') $options[] = $a['value'];
			}
		}
		$out[$name] = array('tag' => $tag, 'type' => $type, 'accept' => $accept, 'options' => $options, 'cf' => $n['props']['_cf'] ?? array());
	}
	return $out;
}

// The tokens a tree's bindings read, and the fields its reference
// bindings read of the record they point at.
function _test_ppdc_tokens($tree)
{
	$nodes = array();
	_test_ppd_nodes($tree, $nodes);
	$tokens = array();
	foreach ($nodes as $n) {
		foreach ((array)($n['props']['_bindings'] ?? array()) as $t) $tokens[$t] = true;
	}
	return array_keys($tokens);
}

function test_podcast_collections_pages_and_widgets()
{
	$d = _test_ppdc_load();
	pg_assert_same(array('podcasts', 'podcast', 'podcast_new'), array_map(function ($p) { return $p['key']; }, $d['parts']['podcasts']['pages']));
	pg_assert_same(array('episodes', 'episode', 'episode_new'), array_map(function ($p) { return $p['key']; }, $d['parts']['episodes']['pages']));
	pg_assert_same(array('podcast_form', 'podcasts_list', 'home_podcasts', 'podcast_detail'), array_keys($d['parts']['podcasts']['widgets']));
	pg_assert_same(array('episode_form', 'episodes_list', 'home_episodes', 'podcast_episodes', 'episode_comments', 'episode_detail'), array_keys($d['parts']['episodes']['widgets']));
	foreach (array('podcasts', 'podcast', 'episodes', 'episode') as $key) pg_assert_same('public', $d['pages'][$key]['folder'], "$key is public");
	foreach (array('podcast_new', 'episode_new') as $key) {
		pg_assert_same('private', $d['pages'][$key]['folder'], "only the staff open $key");
		pg_assert_true(!empty($d['pages'][$key]['noindex']), "$key is not indexed");
	}
	$names = array();
	foreach ($d['pages'] as $key => $p) {
		foreach (array('name', 'title', 'tree') as $f) pg_assert_true(!empty($p[$f]), "$key has $f");
		pg_assert_false(isset($p['requires']), "$key stands without the shop");
		pg_assert_same('root', $p['tree']['type'], "$key: a page tree");
		pg_assert_true(mb_strlen((string)($p['meta_description'] ?? ''), 'UTF-8') <= 160, "$key: meta description fits a result line");
		pg_assert_false(isset($names[$p['name']]), "$key: its page name once");
		$names[$p['name']] = true;
	}
	foreach ($d['widgets'] as $key => $w) {
		pg_assert_false(isset($w['requires']), "$key stands without the shop");
		pg_assert_true($w['page'] === 'home' || isset($d['pages'][$w['page']]), "$key sits on a page of these parts or the home");
		if ($w['page'] !== 'home') {
			pg_assert_contains('"templateWidget":"' . $key . '"', json_encode($d['pages'][$w['page']]['tree']), "$key is placed on its page");
		}
	}
	pg_assert_same('home', $d['widgets']['home_podcasts']['page']);
	pg_assert_same('home', $d['widgets']['home_episodes']['page']);
}

// Each form is a collection with a sample per record; every sample value
// has its field and is filled, a pick list's value is one of its answers,
// an episode names a sample podcast, featured is the staff's.
function test_podcast_collections_forms_and_samples()
{
	$d = _test_ppdc_load();
	$forms = array(
		'podcast_new' => array('podcast_form', 'podcasts', array('title', 'category', 'hosted_by', 'schedule', 'summary', 'about', 'cover', 'apple_url', 'spotify_url', 'feed_url', 'featured')),
		'episode_new' => array('episode_form', 'episodes', array('title', 'podcast', 'episode_number', 'aired', 'length', 'guests', 'summary', 'notes', 'chapters', 'transcript', 'audio', 'listen_url', 'featured')),
	);
	foreach ($forms as $page_key => $w) {
		$form = $d['pages'][$page_key]['form'];
		pg_assert_same(1, $form['collection'], "$page_key: a collection");
		pg_assert_same(count($d[$w[1]]), count($form['sample_records']), "$page_key: one sample per record");
		$controls = _test_ppdc_controls($d['widgets'][$w[0]]['tree']);
		pg_assert_same($w[2], array_keys($controls), "$page_key: its fields");
		pg_assert_true(!empty($controls['featured']['cf']['office_use_only']), "$page_key: featured is the staff's");
		$featured = 0;
		foreach ($form['sample_records'] as $n => $r) {
			foreach ($r as $name => $value) {
				pg_assert_true(isset($controls[$name]), "$page_key sample $n: field $name exists");
				pg_assert_true($value !== '', "$page_key sample $n: $name filled");
				if ($controls[$name]['tag'] === 'select' && ($controls[$name]['cf']['kind'] ?? '') !== 'reference') {
					pg_assert_true(in_array($value, $controls[$name]['options'], true), "$page_key sample $n: $name '$value' is an answer");
				}
			}
			if ($r['featured'] === lang('Yes')) $featured++;
		}
		pg_assert_same(3, $featured, "$page_key: three featured samples");
	}
	$pod = _test_ppdc_controls($d['widgets']['podcast_form']['tree']);
	pg_assert_same('image', $pod['cover']['cf']['kind'] ?? '', 'the cover is an image');
	pg_assert_same('cover', $d['pages']['podcast_new']['form']['share_image_field'], 'the cover is the share image');
	pg_assert_same($d['category_titles'], $pod['category']['options'], 'the categories');
	foreach (array('apple_url', 'spotify_url', 'feed_url') as $name) pg_assert_same('url', $pod[$name]['type'], "$name is an address");
	$ep = _test_ppdc_controls($d['widgets']['episode_form']['tree']);
	pg_assert_same(array('kind' => 'reference', 'ref' => '{{tab:podcast_new}}', 'rss_field' => 'category'), $ep['podcast']['cf'], 'the podcast is a reference into the Podcasts form, the feed\'s category');
	pg_assert_same(array(), $ep['podcast']['options'], 'its choices are the podcasts themselves');
	pg_assert_same('date', $ep['aired']['type'], 'the day it comes out is a date');
	pg_assert_same('url', $ep['listen_url']['type'], 'the app address is an address');
	pg_assert_same('file', $ep['audio']['type'], 'the recording is a file');
	pg_assert_same('audio/*', $ep['audio']['accept'], 'the recording takes sound files');
	pg_assert_same('{{folder:episodes}}', $ep['audio']['cf']['upload_folder_id'] ?? '', 'kept in the episodes folder');
	// The sample episodes name a sample podcast by its title (the label
	// the reference is seeded by), stand oldest first and carry their own
	// chapters and day.
	$titles = array();
	foreach ($d['podcasts'] as $p) $titles[] = $p['title'];
	$samples = $d['pages']['episode_new']['form']['sample_records'];
	$keys = array_reverse(array_keys($d['episodes']));
	foreach ($samples as $n => $r) {
		$e = $d['episodes'][$keys[$n]];
		pg_assert_same($d['podcasts'][$e['podcast']]['title'], $r['podcast'], "episode sample $n: its podcast by title");
		pg_assert_true(in_array($r['podcast'], $titles, true), "episode sample $n: a sample podcast");
		pg_assert_same(call_user_func($d['chapters_text'], $keys[$n]), $r['chapters'], "episode sample $n: its chapters");
		pg_assert_same(call_user_func($d['aired_on'], $e['days']), $r['aired'], "episode sample $n: its day");
		pg_assert_same($e['guests'] !== '', isset($r['guests']), "episode sample $n: guests only when it has some");
	}
	$today = (new DateTime('today'))->format('Y-m-d');
	foreach ($samples as $n => $r) pg_assert_true($r['aired'] <= $today, "episode sample $n: already out");
}

// The lists and record pages read only fields the forms have, the
// renderer's own tokens, or a field of the podcast through the reference.
function test_podcast_collections_bindings_name_fields()
{
	$d = _test_ppdc_load();
	$own = array('form_item_view', 'not_found', '__edit_url', 'can_edit', 'record_found', 'record_not_found', '__ref');
	$pod_fields = array_keys(_test_ppdc_controls($d['widgets']['podcast_form']['tree']));
	$map = array(
		'podcast_form' => array('podcasts_list', 'home_podcasts', 'podcast_detail'),
		'episode_form' => array('episodes_list', 'home_episodes', 'podcast_episodes', 'episode_detail'),
	);
	foreach ($map as $form => $lists) {
		$fields = array_keys(_test_ppdc_controls($d['widgets'][$form]['tree']));
		foreach ($lists as $key) {
			foreach (_test_ppdc_tokens($d['widgets'][$key]['tree']) as $t) {
				pg_assert_true(in_array($t, $fields, true) || in_array($t, $own, true), "$key reads $t, a field of $form");
			}
			$nodes = array();
			_test_ppd_nodes($d['widgets'][$key]['tree'], $nodes);
			foreach ($nodes as $n) {
				foreach ((array)($n['props']['_bindParams'] ?? array()) as $spec) {
					pg_assert_same('podcast', $spec['field'], "$key: a reference through the Podcast field");
					pg_assert_true(in_array($spec['show'], $pod_fields, true), "$key: shows {$spec['show']}, a field of the podcast");
				}
			}
			foreach ((array)($d['widgets'][$key]['config']['filters'] ?? array()) as $f) {
				pg_assert_true(in_array($f['field'], $fields, true), "$key filters on {$f['field']}, a field of $form");
			}
		}
	}
	$episode = json_encode($d['widgets']['episode_detail']['tree']);
	foreach (array('"chapters"', '"notes"', '"transcript"') as $t) pg_assert_contains($t, $episode, "the episode page shows $t");
	foreach (array('audio', 'listen_url') as $t) pg_assert_contains('"show_if":"' . $t . '"', $episode, "the $t button leaves without it");
	foreach (array('apple_url', 'spotify_url', 'feed_url') as $t) {
		pg_assert_contains('"show_if":"' . $t . '"', json_encode($d['widgets']['podcast_detail']['tree']), "the $t button leaves without an address");
	}
}

// The episode page plays the record's Audio file in its own player: bound
// src, nothing loaded before it is pressed, the box gone without a file,
// the Listen buttons after it.
function test_podcast_collections_episode_player_is_bound()
{
	$d = _test_ppdc_load();
	$nodes = array();
	_test_ppd_nodes($d['widgets']['episode_detail']['tree'], $nodes);
	$audio = null;
	foreach ($nodes as $n) if (($n['props']['tag'] ?? '') === 'audio') $audio = $n;
	pg_assert_true($audio !== null, 'an audio element on the episode page');
	pg_assert_same('audio', $audio['props']['_bindings']['src'] ?? '', 'its address is the Audio field');
	$attrs = array();
	foreach ($audio['props']['_attrs'] as $a) $attrs[$a['name']] = $a['value'];
	pg_assert_same('none', $attrs['preload'] ?? '', 'nothing loads before it is pressed');
	pg_assert_false(isset($attrs['src']) || isset($attrs['autoplay']), 'no typed address, never plays by itself');
	pg_assert_contains('pg-field-value', $audio['props']['cssClass'], 'the box is judged by the player');
	$html = _render_tree_node($d['widgets']['episode_detail']['tree'], 0);
	pg_assert_contains('src="^^audio^^"', $html, 'the page prints the bound address');
	pg_assert_true(strpos($html, 'src="^^audio^^"') < strpos($html, '"^^listen_url^^"'), 'the Listen buttons follow the player');
	$player_box = '';
	if (preg_match('#<div class="pg-hide-if-empty ppd-wave[^"]*".*?</audio>\s*</div>#s', $html, $m)) $player_box = $m[0];
	pg_assert_true($player_box !== '', 'the player stands in a box marked to hide when empty');
	$empty = pg_sw_hide_empty_fields(str_replace(' src="^^audio^^"', '', $player_box));
	pg_assert_same('', trim($empty), 'without a file the box goes');
	$full = str_replace('^^audio^^', '/files/e12.mp3', $player_box);
	pg_assert_same($full, pg_sw_hide_empty_fields($full), 'with a file it stays');
}

// The podcasts page reads ?category=; the episodes lists show only the
// episodes already out, newest first; a podcast's page lists its own; the
// home shows the featured ones.
function test_podcast_collections_filters()
{
	$d = _test_ppdc_load();
	$w = $d['widgets'];
	$out = array('field' => 'aired', 'operator' => 'is less than or equal to', 'value' => '', 'dynamic_value' => 'current date');
	$featured = array('field' => 'featured', 'operator' => 'is equal to', 'value' => lang('Yes'), 'dynamic_value' => '');
	pg_assert_same(array(array('field' => 'category', 'operator' => 'is equal to', 'value' => '', 'dynamic_value' => 'url parameter', 'dynamic_value_attribute' => 'category')),
		$w['podcasts_list']['config']['filters'], 'the podcasts by category');
	pg_assert_same(array($featured), $w['home_podcasts']['config']['filters'], 'the home podcasts are featured');
	pg_assert_same(array($out), $w['episodes_list']['config']['filters'], 'the episodes already out');
	pg_assert_same(array($featured, $out), $w['home_episodes']['config']['filters'], 'the home episodes are featured and out');
	pg_assert_same(array(array('field' => 'podcast', 'operator' => 'is equal to', 'value' => '', 'dynamic_value' => 'shown record', 'dynamic_value_attribute' => ''), $out),
		$w['podcast_episodes']['config']['filters'], 'a podcast page lists its own episodes already out');
	foreach (array('episodes_list', 'home_episodes', 'podcast_episodes') as $key) {
		pg_assert_same(array('aired', 'DESC'), array($w[$key]['config']['order_by_field'], $w[$key]['config']['order_by_direction']), "$key: the newest first");
		pg_assert_same('{{tab:episode_new}}', $w[$key]['config']['custom_form_page_id'], "$key: the Episodes form");
		pg_assert_same('{{tab:episode}}', $w[$key]['config']['detail_page_id'], "$key opens the episode page");
	}
	foreach (array('podcasts_list', 'home_podcasts') as $key) {
		pg_assert_same('{{tab:podcast}}', $w[$key]['config']['detail_page_id'], "$key opens the podcast page");
	}
	// Every filter link of the podcasts page names a category.
	$nodes = array();
	_test_ppd_nodes($d['pages']['podcasts']['tree'], $nodes);
	$seen = array();
	foreach ($nodes as $n) {
		$href = (string)($n['props']['href'] ?? '');
		if (strpos($href, '{{page:podcasts}}?') !== 0) continue;
		parse_str(substr($href, strlen('{{page:podcasts}}?')), $q);
		pg_assert_same(array('category'), array_keys($q), 'the link sets ?category=');
		pg_assert_true(in_array($q['category'], $d['category_titles'], true), "?category={$q['category']} is an answer");
		$seen[$q['category']] = true;
	}
	pg_assert_same(count($d['category_titles']), count($seen), 'a link per category');
}

// The episode page takes the listeners' words in its own Comments widget,
// which the network is told about; both record pages show the not-found
// note and the staff's Edit button.
function test_podcast_collections_record_pages()
{
	$d = _test_ppdc_load();
	$c = $d['pages']['episode']['comments'];
	pg_assert_true($c['allow_new'], 'listeners may write');
	pg_assert_same('{{tab:email_reply}}', $c['email_page'], 'the network is told by e-mail');
	pg_assert_same('comments', $d['widgets']['episode_comments']['config']['regionType']);
	foreach (array('podcast_detail', 'episode_detail') as $key) {
		$json = json_encode($d['widgets'][$key]['tree']);
		pg_assert_contains('"eo_visible_if":"record_found"', $json, "$key: the record");
		pg_assert_contains('"eo_visible_if":"record_not_found"', $json, "$key: the not-found note");
		pg_assert_contains('__edit_url', $json, "$key: the staff's Edit button");
	}
	foreach (array('podcast', 'episode') as $key) pg_assert_same(false, $d['pages'][$key]['sitemap'], "$key: a record page, not one page of the sitemap");
}

function test_podcast_collections_links_ids_and_classes()
{
	$d = _test_ppdc_load();
	$known = array_flip(_test_ppd_page_keys());
	$trees = array();
	foreach ($d['pages'] as $p) $trees[] = $p;
	foreach ($d['widgets'] as $w) $trees[] = array($w['config'], $w['tree']);
	$json = json_encode($trees);
	preg_match_all('/\{\{(?:page|tab):([a-z0-9_]+)\}\}/', $json, $m);
	foreach (array_unique($m[1]) as $key) pg_assert_true(isset($known[$key]), "page link {{page:$key}} names a template page");
	foreach (_test_ppd_shop_keys() as $key) {
		pg_assert_false(strpos($json, '{{page:' . $key . '}}') !== false, "no tree links to $key");
	}
	$nodes = array();
	foreach ($d['pages'] as $p) _test_ppd_nodes($p['tree'], $nodes);
	foreach ($d['widgets'] as $w) _test_ppd_nodes($w['tree'], $nodes);
	foreach ($nodes as $n) {
		foreach (preg_split('/\s+/', (string)($n['props']['cssClass'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $c) {
			pg_assert_false((bool)preg_match('/^(pht|pre|pev|pf|pdp|pm|pma|pmu|pmk|psc|pmz|pai|psh|plx|pdl|plw|psl|pco|pcl|pgy|pch|ptv|pph|pwd|pcn)-/', $c), "class $c is not another template's");
		}
		$id = (string)($n['props']['id'] ?? '');
		if ($id !== '' && strpos($id, '-') !== false) pg_assert_true(strpos($id, 'pd-') === 0, "id $id is the template's");
	}
}

// No year or month in the sources: the episodes count back from today.
function test_podcast_collections_no_fixed_dates()
{
	foreach (array('podcasts', 'episodes') as $file) {
		$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-podcast/' . $file . '.php');
		$src = preg_replace('/@copyright[^\n]*/', '', $src);
		pg_assert_false((bool)preg_match('/\b(19|20)[0-9]{2}\b/', $src), "$file: no year in the sample");
		pg_assert_false((bool)preg_match('/\b(January|February|March|April|June|July|August|September|October|November|December)\b/', $src), "$file: no month in the sample");
	}
}

// Every literal has its Turkish; an episode reads "bölüm", a guest
// "konuk", and no key falls on a shared word that reads otherwise.
function test_podcast_collections_turkish_words()
{
	$map = pg_test_language_map('tr');
	foreach (array('podcasts', 'episodes') as $file) {
		$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-podcast/' . $file . '.php');
		preg_match_all("/lang\\('((?:[^'\\\\]|\\\\.)*)'\\)/", $src, $m);
		foreach (array_unique($m[1]) as $raw) {
			$key = stripslashes($raw);
			pg_assert_true(isset($map[$key]) && $map[$key] !== '', "$file: Turkish for '$key'");
			pg_assert_false(in_array($key, array('Show', 'Shows', 'Season', 'Show Notes', 'Guests', 'Host', 'Listening'), true), "$file: '$key' reads otherwise in Turkish");
			if (!isset($map[$key])) continue;
			$tr = $map[$key];
			// A page name is an address: written without Turkish letters.
			if (preg_match('/^[a-z-]+$/', $key)) continue;
			if (preg_match('/\b[Ee]pisodes?\b/', $key)) pg_assert_true(mb_stripos($tr, 'bölüm', 0, 'UTF-8') !== false, "$file: '$key' says bölüm");
			if (preg_match('/\b[Gg]uests?\b/', $key)) pg_assert_true(mb_stripos($tr, 'konuk', 0, 'UTF-8') !== false, "$file: '$key' says konuk");
		}
	}
}
