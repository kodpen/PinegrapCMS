<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Music Artist design template's Releases and Shows
 * collections (includes/design_templates/pinegrap-music/releases.php and
 * shows.php): the pages and widgets with their keys, each form a
 * collection with a sample per record whose values are its fields and its
 * pick lists' answers, the cards and rows reading fields the forms have,
 * the discography filtered by format, the tour from today on by country
 * and the past shows before today, the home lists, the record pages, every
 * page link naming a page of the finished template and none a shop page,
 * the template's own ids and classes, no fixed date, and every literal
 * with its Turkish.
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
require_once(__DIR__ . '/design_template_music_test.php');

// parts.php, releases.php and shows.php in one scope, as the template file
// reads them. Read once per run.
function _test_muc_load()
{
	static $out = null;
	if ($out !== null) return $out;
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-music';
	include $dir . '/parts.php';
	$release_part = include $dir . '/releases.php';
	$show_part = include $dir . '/shows.php';
	$parts = array('releases' => $release_part, 'shows' => $show_part);
	$pages = array();
	$widgets = array();
	foreach ($parts as $name => $part) {
		pg_assert_same(array('pages', 'widgets'), array_keys($part), "$name returns pages and widgets");
		foreach ($part['pages'] as $p) $pages[$p['key']] = $p;
		$widgets = array_merge($widgets, $part['widgets']);
	}
	$out = array('parts' => $parts, 'pages' => $pages, 'widgets' => $widgets, 'releases' => $releases, 'shows' => $shows,
		'format_titles' => $format_titles, 'country_titles' => $country_titles, 'ticket_state_titles' => $ticket_state_titles,
		'tracks_text' => $tracks_text, 'length_text' => $length_text, 'show_on' => $show_on);
	return $out;
}

// Every node of a tree, depth first.
function _test_muc_nodes($node, &$out)
{
	if (!is_array($node)) return;
	if (isset($node['props'])) $out[] = $node;
	foreach ((array)($node['children'] ?? array()) as $c) _test_muc_nodes($c, $out);
}

// The name, options and _cf of every form control of a tree.
function _test_muc_controls($node)
{
	$nodes = array();
	_test_muc_nodes($node, $nodes);
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
function _test_muc_tokens($tree)
{
	$nodes = array();
	_test_muc_nodes($tree, $nodes);
	$tokens = array();
	foreach ($nodes as $n) {
		foreach ((array)($n['props']['_bindings'] ?? array()) as $t) $tokens[$t] = true;
	}
	return array_keys($tokens);
}

function test_music_collections_pages_and_widgets()
{
	$d = _test_muc_load();
	pg_assert_same(array('discography', 'release', 'release_new'), array_map(function ($p) { return $p['key']; }, $d['parts']['releases']['pages']));
	pg_assert_same(array('tour', 'past_shows', 'show', 'show_new'), array_map(function ($p) { return $p['key']; }, $d['parts']['shows']['pages']));
	pg_assert_same(array('release_form', 'releases_list', 'home_releases', 'release_comments', 'release_detail'), array_keys($d['parts']['releases']['widgets']));
	pg_assert_same(array('show_form', 'tour_list', 'past_shows_list', 'home_shows', 'show_detail'), array_keys($d['parts']['shows']['widgets']));
	foreach (array('discography', 'release', 'tour', 'past_shows', 'show') as $key) pg_assert_same('public', $d['pages'][$key]['folder'], "$key is public");
	foreach (array('release_new', 'show_new') as $key) {
		pg_assert_same('private', $d['pages'][$key]['folder'], "only the staff open $key");
		pg_assert_true(!empty($d['pages'][$key]['noindex']), "$key is not indexed");
	}
	foreach ($d['pages'] as $key => $p) {
		foreach (array('name', 'title', 'tree') as $f) pg_assert_true(!empty($p[$f]), "$key has $f");
		pg_assert_false(isset($p['requires']), "$key stands without the shop");
		pg_assert_same('root', $p['tree']['type'], "$key: a page tree");
		pg_assert_true(mb_strlen((string)($p['meta_description'] ?? ''), 'UTF-8') <= 160, "$key: meta description fits a result line");
	}
	foreach ($d['widgets'] as $key => $w) {
		pg_assert_false(isset($w['requires']), "$key stands without the shop");
		pg_assert_true($w['page'] === 'home' || isset($d['pages'][$w['page']]), "$key sits on a page of this part or the home");
		if ($w['page'] !== 'home') {
			pg_assert_contains('"templateWidget":"' . $key . '"', json_encode($d['pages'][$w['page']]['tree']), "$key is placed on its page");
		}
	}
	// The home places exactly these two lists of the collections.
	pg_assert_same('home', $d['widgets']['home_releases']['page']);
	pg_assert_same('home', $d['widgets']['home_shows']['page']);
}

// Each form is a collection with a sample per record; every sample value
// has its field and is filled, a pick list's value is one of its answers,
// the cover is an image and featured is the staff's.
function test_music_collections_forms_and_samples()
{
	$d = _test_muc_load();
	$forms = array(
		'release_new' => array('release_form', 'releases', array('title', 'format', 'length', 'note', 'summary', 'tracks', 'credits', 'cover', 'listen_url', 'video_url', 'featured')),
		'show_new'    => array('show_form', 'shows', array('show_date', 'doors', 'city', 'country', 'venue', 'support', 'ticket_state', 'ticket_url', 'featured')),
	);
	foreach ($forms as $page_key => $w) {
		$form = $d['pages'][$page_key]['form'];
		pg_assert_same(1, $form['collection'], "$page_key: a collection");
		pg_assert_same(count($d[$w[1]]), count($form['sample_records']), "$page_key: one sample per record");
		$controls = _test_muc_controls($d['widgets'][$w[0]]['tree']);
		pg_assert_same($w[2], array_keys($controls), "$page_key: its fields");
		pg_assert_true(!empty($controls['featured']['cf']['office_use_only']), "$page_key: featured is the staff's");
		$featured = 0;
		foreach ($form['sample_records'] as $n => $r) {
			foreach ($r as $name => $value) {
				pg_assert_true(isset($controls[$name]), "$page_key sample $n: field $name exists");
				pg_assert_true($value !== '', "$page_key sample $n: $name filled");
				if ($controls[$name]['tag'] === 'select') {
					pg_assert_true(in_array($value, $controls[$name]['options'], true), "$page_key sample $n: $name '$value' is an answer");
				}
			}
			if ($r['featured'] === lang('Yes')) $featured++;
		}
		pg_assert_same(3, $featured, "$page_key: three featured samples");
	}
	$rel = _test_muc_controls($d['widgets']['release_form']['tree']);
	pg_assert_same('image', $rel['cover']['cf']['kind'] ?? '', 'the cover is an image');
	pg_assert_same('cover', $d['pages']['release_new']['form']['share_image_field'], 'the cover is the share image');
	pg_assert_same($d['format_titles'], $rel['format']['options'], 'the formats');
	foreach (array('listen_url', 'video_url') as $name) pg_assert_same('url', $rel[$name]['type'], "$name is an address");
	$show = _test_muc_controls($d['widgets']['show_form']['tree']);
	pg_assert_same('date', $show['show_date']['type'], 'the show date is a date');
	pg_assert_same('url', $show['ticket_url']['type'], 'the ticket link is an address');
	pg_assert_same($d['country_titles'], $show['country']['options'], 'the countries');
	pg_assert_same($d['ticket_state_titles'], $show['ticket_state']['options'], 'the ticket states');
	// The sample releases stand oldest first, so the newest leads a list
	// ordered newest first; each carries its own track list.
	$samples = $d['pages']['release_new']['form']['sample_records'];
	$keys = array_reverse(array_keys($d['releases']));
	foreach ($samples as $n => $r) {
		pg_assert_same(call_user_func($d['tracks_text'], $keys[$n]), $r['tracks'], "release sample $n: its tracks");
		pg_assert_same(call_user_func($d['length_text'], $keys[$n]), $r['length'], "release sample $n: its length");
	}
	pg_assert_same($d['releases']['nightbus']['title'], end($samples)['title'], 'the newest release is written last');
	// The sample shows: six from today on, three before today.
	$today = (new DateTime('today'))->format('Y-m-d');
	$to_come = 0;
	foreach ($d['pages']['show_new']['form']['sample_records'] as $r) {
		if ($r['show_date'] >= $today) $to_come++;
		if ($r['show_date'] < $today) pg_assert_false(isset($r['ticket_url']), 'a past show sells no tickets');
	}
	pg_assert_same(6, $to_come, 'six shows to come');
}

// The cards and rows of the lists, and the record pages, read only fields
// the forms have (or the renderer's own tokens).
function test_music_collections_bindings_name_fields()
{
	$d = _test_muc_load();
	$own = array('form_item_view', 'not_found', '__edit_url', 'can_edit', 'record_found', 'record_not_found');
	$map = array(
		'release_form' => array('releases_list', 'home_releases', 'release_detail'),
		'show_form'    => array('tour_list', 'past_shows_list', 'home_shows', 'show_detail'),
	);
	foreach ($map as $form => $lists) {
		$fields = array_keys(_test_muc_controls($d['widgets'][$form]['tree']));
		foreach ($lists as $key) {
			foreach (_test_muc_tokens($d['widgets'][$key]['tree']) as $t) {
				pg_assert_true(in_array($t, $fields, true) || in_array($t, $own, true), "$key reads $t, a field of $form");
			}
			if (isset($d['widgets'][$key]['config']['filters'])) {
				foreach ($d['widgets'][$key]['config']['filters'] as $f) {
					pg_assert_true(in_array($f['field'], $fields, true), "$key filters on {$f['field']}, a field of $form");
				}
			}
		}
	}
	pg_assert_contains('"tracks"', json_encode($d['widgets']['release_detail']['tree']), 'the release page shows its tracks');
	foreach (array('listen_url', 'video_url') as $t) {
		pg_assert_contains('"show_if":"' . $t . '"', json_encode($d['widgets']['release_detail']['tree']), "the $t button leaves without an address");
	}
	pg_assert_contains('"show_if":"ticket_url"', json_encode($d['widgets']['show_detail']['tree']), 'the ticket button leaves without an address');
}

// The discography reads ?format=, the tour ?country= and today's date, the
// past shows the days before today; the home shows the featured ones.
function test_music_collections_filters()
{
	$d = _test_muc_load();
	$w = $d['widgets'];
	$url = function ($field) {
		return array('field' => $field, 'operator' => 'is equal to', 'value' => '', 'dynamic_value' => 'url parameter', 'dynamic_value_attribute' => $field);
	};
	$from_today = array('field' => 'show_date', 'operator' => 'is greater than or equal to', 'value' => '', 'dynamic_value' => 'current date');
	$featured = array('field' => 'featured', 'operator' => 'is equal to', 'value' => lang('Yes'), 'dynamic_value' => '');
	pg_assert_same(array($url('format')), $w['releases_list']['config']['filters'], 'the discography by format');
	pg_assert_same(array($from_today, $url('country')), $w['tour_list']['config']['filters'], 'the tour from today, by country');
	pg_assert_same(array(array('field' => 'show_date', 'operator' => 'is less than', 'value' => '', 'dynamic_value' => 'current date')),
		$w['past_shows_list']['config']['filters'], 'the past shows before today');
	pg_assert_same(array($featured), $w['home_releases']['config']['filters'], 'the home releases are featured');
	pg_assert_same(array($from_today, $featured), $w['home_shows']['config']['filters'], 'the home shows are featured and to come');
	pg_assert_same(array('show_date', 'ASC'), array($w['tour_list']['config']['order_by_field'], $w['tour_list']['config']['order_by_direction']), 'the nearest show first');
	pg_assert_same(array('show_date', 'DESC'), array($w['past_shows_list']['config']['order_by_field'], $w['past_shows_list']['config']['order_by_direction']), 'the latest past show first');
	pg_assert_same('DESC', $w['releases_list']['config']['order_by_direction'], 'the newest release first');
	foreach (array('releases_list' => 'release', 'tour_list' => 'show', 'home_shows' => 'show', 'home_releases' => 'release') as $key => $detail) {
		pg_assert_same('{{tab:' . $detail . '}}', $w[$key]['config']['detail_page_id'], "$key opens the $detail page");
	}
	// Every filter link of a list page names an answer of its pick list.
	$answers = array('discography' => array('format', $d['format_titles']), 'tour' => array('country', $d['country_titles']));
	foreach ($answers as $page_key => $a) {
		$nodes = array();
		_test_muc_nodes($d['pages'][$page_key]['tree'], $nodes);
		$seen = array();
		foreach ($nodes as $n) {
			$href = (string)($n['props']['href'] ?? '');
			if (strpos($href, '{{page:' . $page_key . '}}?') !== 0) continue;
			parse_str(substr($href, strlen('{{page:' . $page_key . '}}?')), $q);
			pg_assert_same(array($a[0]), array_keys($q), "$page_key: the link sets ?{$a[0]}=");
			pg_assert_true(in_array($q[$a[0]], $a[1], true), "$page_key: ?{$a[0]}={$q[$a[0]]} is an answer");
			$seen[$q[$a[0]]] = true;
		}
		pg_assert_same(count($a[1]), count($seen), "$page_key: a link per answer");
	}
}

// The release page takes the fans' words in its own Comments widget, which
// the band is told about; both record pages show the not-found note.
function test_music_collections_record_pages()
{
	$d = _test_muc_load();
	$c = $d['pages']['release']['comments'];
	pg_assert_true($c['allow_new'], 'fans may write');
	pg_assert_same('{{tab:email_reply}}', $c['email_page'], 'the band is told by e-mail');
	pg_assert_same('comments', $d['widgets']['release_comments']['config']['regionType']);
	foreach (array('release_detail', 'show_detail') as $key) {
		$json = json_encode($d['widgets'][$key]['tree']);
		pg_assert_contains('"eo_visible_if":"record_found"', $json, "$key: the record");
		pg_assert_contains('"eo_visible_if":"record_not_found"', $json, "$key: the not-found note");
		pg_assert_contains('__edit_url', $json, "$key: the staff's Edit button");
	}
}

function test_music_collections_links_ids_and_classes()
{
	$d = _test_muc_load();
	$known = array_flip(_test_mu_page_keys());
	$trees = array();
	foreach ($d['pages'] as $p) $trees[] = $p;
	foreach ($d['widgets'] as $w) $trees[] = array($w['config'], $w['tree']);
	$json = json_encode($trees);
	preg_match_all('/\{\{(?:page|tab):([a-z0-9_]+)\}\}/', $json, $m);
	foreach (array_unique($m[1]) as $key) pg_assert_true(isset($known[$key]), "page link {{page:$key}} names a template page");
	foreach (_test_mu_shop_keys() as $key) {
		pg_assert_false(strpos($json, '{{page:' . $key . '}}') !== false, "no tree links to $key");
	}
	$nodes = array();
	foreach ($d['pages'] as $p) _test_muc_nodes($p['tree'], $nodes);
	foreach ($d['widgets'] as $w) _test_muc_nodes($w['tree'], $nodes);
	foreach ($nodes as $n) {
		foreach (preg_split('/\s+/', (string)($n['props']['cssClass'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $c) {
			pg_assert_false((bool)preg_match('/^(pht|pre|pev|pf|pdp|pm|pma|plx|pdl|plw|psl|pco|pcl|pgy|pch|ptv|pph|pwd|pcn)-/', $c), "class $c is not another template's");
		}
		$id = (string)($n['props']['id'] ?? '');
		if ($id !== '' && strpos($id, '-') !== false) pg_assert_true(strpos($id, 'mu-') === 0, "id $id is the template's");
	}
}

// No year or month in the sources: the shows count from today and the
// releases sit in the band's story in words.
function test_music_collections_no_fixed_dates()
{
	foreach (array('releases.php', 'shows.php') as $file) {
		$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-music/' . $file);
		$src = preg_replace('/@copyright[^\n]*/', '', $src);
		pg_assert_false((bool)preg_match('/\b(19|20)[0-9]{2}\b/', $src), "$file: no year");
		pg_assert_false((bool)preg_match('/\b(January|February|March|April|June|July|August|September|October|November|December)\b/', $src), "$file: no month");
	}
}

// Every literal has its Turkish; a show reads "konser", the tour "turne",
// a track "parça", and no key falls on a shared word that means something
// else (Show, Shows, Release, Releases, tour).
function test_music_collections_turkish_words()
{
	$map = pg_test_language_map('tr');
	foreach (array('releases.php', 'shows.php') as $file) {
		$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-music/' . $file);
		preg_match_all("/lang\\('((?:[^'\\\\]|\\\\.)*)'\\)/", $src, $m);
		foreach (array_unique($m[1]) as $raw) {
			$key = stripslashes($raw);
			pg_assert_true(isset($map[$key]) && $map[$key] !== '', "$file: Turkish for '$key'");
			if (!isset($map[$key])) continue;
			$tr = $map[$key];
			if (preg_match('/\bShows?\b/', $key)) pg_assert_true(mb_stripos($tr, 'konser', 0, 'UTF-8') !== false, "'$key' says konser");
			if (preg_match('/^Tour\b/', $key)) pg_assert_true(mb_stripos($tr, 'turne', 0, 'UTF-8') !== false, "'$key' says turne");
			if (preg_match('/\bTracks?\b/', $key)) pg_assert_true(mb_stripos($tr, 'parça', 0, 'UTF-8') !== false, "'$key' says parça");
			pg_assert_false(in_array($key, array('Show', 'Shows', 'Release', 'Releases', 'tour'), true), "$file: '$key' means something else in Turkish");
		}
	}
}
