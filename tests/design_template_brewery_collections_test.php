<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Craft Brewery design template's Beers and Taproom Events
 * collections (includes/design_templates/pinegrap-brewery/beers.php and
 * events.php): the pages and widgets with their keys, each form a
 * collection with a sample per record whose values are its fields and its
 * pick lists' answers, the cards and rows reading fields the forms have,
 * the beers filtered by style, the events from today on by kind and the
 * past events before today, the home lists, the record pages and the
 * drinkers' notes with stars, every page link naming a page of the
 * finished template and none a shop page, the template's own ids and
 * classes, no faded text, no fixed date, and every literal with its
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

// The page keys of the finished template and the shop's pages.
require_once(__DIR__ . '/design_template_brewery_test.php');

// parts.php, beers.php and events.php in one scope, as the template file
// reads them. Read once per run.
function _test_bwc_load()
{
	static $out = null;
	if ($out !== null) return $out;
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-brewery';
	include $dir . '/parts.php';
	$beer_part = include $dir . '/beers.php';
	$event_part = include $dir . '/events.php';
	$parts = array('beers' => $beer_part, 'events' => $event_part);
	$pages = array();
	$widgets = array();
	foreach ($parts as $name => $part) {
		pg_assert_same(array('pages', 'widgets'), array_keys($part), "$name returns pages and widgets");
		foreach ($part['pages'] as $p) $pages[$p['key']] = $p;
		$widgets = array_merge($widgets, $part['widgets']);
	}
	$out = array('parts' => $parts, 'pages' => $pages, 'widgets' => $widgets, 'beers' => $beers, 'events' => $events,
		'style_titles' => $style_titles, 'availability_titles' => $availability_titles, 'event_kind_titles' => $event_kind_titles,
		'strength_text' => $strength_text, 'event_on' => $event_on);
	return $out;
}

// Every node of a tree, depth first.
function _test_bwc_nodes($node, &$out)
{
	if (!is_array($node)) return;
	if (isset($node['props'])) $out[] = $node;
	foreach ((array)($node['children'] ?? array()) as $c) _test_bwc_nodes($c, $out);
}

// The name, type, options and _cf of every form control of a tree.
function _test_bwc_controls($node)
{
	$nodes = array();
	_test_bwc_nodes($node, $nodes);
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
function _test_bwc_tokens($tree)
{
	$nodes = array();
	_test_bwc_nodes($tree, $nodes);
	$tokens = array();
	foreach ($nodes as $n) {
		foreach ((array)($n['props']['_bindings'] ?? array()) as $t) $tokens[$t] = true;
	}
	return array_keys($tokens);
}

function test_brewery_collections_pages_and_widgets()
{
	$d = _test_bwc_load();
	pg_assert_same(array('beers', 'beer', 'beer_new'), array_map(function ($p) { return $p['key']; }, $d['parts']['beers']['pages']));
	pg_assert_same(array('events', 'past_events', 'event', 'event_new'), array_map(function ($p) { return $p['key']; }, $d['parts']['events']['pages']));
	pg_assert_same(array('beer_form', 'beers_list', 'home_beers', 'beer_notes', 'beer_detail'), array_keys($d['parts']['beers']['widgets']));
	pg_assert_same(array('event_form', 'events_list', 'past_events_list', 'home_events', 'event_detail'), array_keys($d['parts']['events']['widgets']));
	foreach (array('beers', 'beer', 'events', 'past_events', 'event') as $key) pg_assert_same('public', $d['pages'][$key]['folder'], "$key is public");
	foreach (array('beer', 'event') as $key) pg_assert_false($d['pages'][$key]['sitemap'], "$key is a record page, out of the sitemap");
	foreach (array('beer_new', 'event_new') as $key) {
		pg_assert_same('private', $d['pages'][$key]['folder'], "only the staff open $key");
		pg_assert_true(!empty($d['pages'][$key]['noindex']), "$key is not indexed");
		pg_assert_false($d['pages'][$key]['search'], "$key is out of the site search");
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
	// The home places exactly the two lists the public pages ask for.
	$home = array();
	foreach ($d['widgets'] as $key => $w) {
		if ($w['page'] === 'home') $home[] = $key;
	}
	pg_assert_same(array('home_beers', 'home_events'), $home, 'the home lists');
	pg_assert_same(lang('Brewery Beers'), $d['pages']['beer_new']['form']['form_name']);
	pg_assert_same(lang('Taproom Events'), $d['pages']['event_new']['form']['form_name']);
}

// Each form is a collection with a sample per record; every sample value
// has its field and is filled, a pick list's value is one of its answers,
// the picture is an image and featured is the staff's.
function test_brewery_collections_forms_and_samples()
{
	$d = _test_bwc_load();
	$forms = array(
		'beer_new'  => array('beer_form', 'beers', array('name', 'style', 'availability', 'strength', 'summary', 'notes', 'pairing', 'grain', 'picture', 'featured')),
		'event_new' => array('event_form', 'events', array('title', 'event_date', 'starts', 'kind', 'entry', 'summary', 'ticket_url', 'featured')),
	);
	foreach ($forms as $page_key => $w) {
		$form = $d['pages'][$page_key]['form'];
		pg_assert_same(1, $form['collection'], "$page_key: a collection");
		pg_assert_same(count($d[$w[1]]), count($form['sample_records']), "$page_key: one sample per record");
		$controls = _test_bwc_controls($d['widgets'][$w[0]]['tree']);
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
	$beer = _test_bwc_controls($d['widgets']['beer_form']['tree']);
	pg_assert_same('image', $beer['picture']['cf']['kind'] ?? '', 'the picture is an image');
	pg_assert_same('picture', $d['pages']['beer_new']['form']['share_image_field'], 'the picture is the share image');
	pg_assert_same($d['style_titles'], $beer['style']['options'], 'the styles');
	pg_assert_same($d['availability_titles'], $beer['availability']['options'], 'the availabilities');
	pg_assert_same('title', $beer['name']['cf']['rss_field'] ?? '', 'the name is the record\'s title');
	pg_assert_same('category', $beer['style']['cf']['rss_field'] ?? '', 'the style is the record\'s category');
	// Each sample beer carries its own strength line, as the cards print it.
	$keys = array_keys($d['beers']);
	foreach ($d['pages']['beer_new']['form']['sample_records'] as $n => $r) {
		pg_assert_same(call_user_func($d['strength_text'], $keys[$n]), $r['strength'], "beer sample $n: its strength");
		pg_assert_same($d['beers'][$keys[$n]]['name'], $r['name'], "beer sample $n: its name");
	}
	$event = _test_bwc_controls($d['widgets']['event_form']['tree']);
	pg_assert_same('date', $event['event_date']['type'], 'the event date is a date');
	pg_assert_same('url', $event['ticket_url']['type'], 'the ticket link is an address');
	pg_assert_same($d['event_kind_titles'], $event['kind']['options'], 'the event kinds');
	pg_assert_same('title', $event['title']['cf']['rss_field'] ?? '', 'the title is the record\'s title');
	// The sample events: five from today on, three before today, each on
	// the day parts.php counts from today; a past event is not featured.
	$today = (new DateTime('today'))->format('Y-m-d');
	$to_come = 0;
	$ekeys = array_keys($d['events']);
	foreach ($d['pages']['event_new']['form']['sample_records'] as $n => $r) {
		$e = $d['events'][$ekeys[$n]];
		pg_assert_same(call_user_func($d['event_on'], $e['weekday'], $e['weeks']), $r['event_date'], "event sample $n: its day");
		if ($r['event_date'] >= $today) $to_come++;
		else pg_assert_same(lang('No'), $r['featured'], "event sample $n: a past event is not on the home");
		pg_assert_false(isset($r['ticket_url']), "event sample $n: free to come in, no ticket page");
	}
	pg_assert_same(5, $to_come, 'five events to come');
}

// The cards and rows of the lists, and the record pages, read only fields
// the forms have (or the renderer's own tokens).
function test_brewery_collections_bindings_name_fields()
{
	$d = _test_bwc_load();
	$own = array('form_item_view', 'not_found', '__edit_url', 'can_edit', 'record_found', 'record_not_found');
	$map = array(
		'beer_form'  => array('beers_list', 'home_beers', 'beer_detail'),
		'event_form' => array('events_list', 'past_events_list', 'home_events', 'event_detail'),
	);
	foreach ($map as $form => $lists) {
		$fields = array_keys(_test_bwc_controls($d['widgets'][$form]['tree']));
		foreach ($lists as $key) {
			foreach (_test_bwc_tokens($d['widgets'][$key]['tree']) as $t) {
				pg_assert_true(in_array($t, $fields, true) || in_array($t, $own, true), "$key reads $t, a field of $form");
			}
			foreach ((array)($d['widgets'][$key]['config']['filters'] ?? array()) as $f) {
				pg_assert_true(in_array($f['field'], $fields, true), "$key filters on {$f['field']}, a field of $form");
			}
			if (isset($d['widgets'][$key]['config']['order_by_field'])) {
				pg_assert_true(in_array($d['widgets'][$key]['config']['order_by_field'], $fields, true), "$key orders by a field of $form");
			}
		}
	}
	$beer = _test_bwc_tokens($d['widgets']['beer_detail']['tree']);
	foreach (array('picture', 'name', 'style', 'strength', 'summary', 'availability', 'notes', 'pairing', 'grain') as $t) {
		pg_assert_true(in_array($t, $beer, true), "the beer page shows $t");
	}
	$event = _test_bwc_tokens($d['widgets']['event_detail']['tree']);
	foreach (array('event_date', 'kind', 'title', 'starts', 'entry', 'summary', 'ticket_url') as $t) {
		pg_assert_true(in_array($t, $event, true), "the event page shows $t");
	}
	pg_assert_contains('"show_if":"ticket_url"', json_encode($d['widgets']['event_detail']['tree']), 'the ticket button leaves without an address');
}

// The beers read ?style=, the events ?kind= and today's date, the past
// events the days before today; the home shows the featured ones.
function test_brewery_collections_filters()
{
	$d = _test_bwc_load();
	$w = $d['widgets'];
	$url = function ($field) {
		return array('field' => $field, 'operator' => 'is equal to', 'value' => '', 'dynamic_value' => 'url parameter', 'dynamic_value_attribute' => $field);
	};
	$from_today = array('field' => 'event_date', 'operator' => 'is greater than or equal to', 'value' => '', 'dynamic_value' => 'current date');
	$featured = array('field' => 'featured', 'operator' => 'is equal to', 'value' => lang('Yes'), 'dynamic_value' => '');
	pg_assert_same(array($url('style')), $w['beers_list']['config']['filters'], 'the beers by style');
	pg_assert_same(array($from_today, $url('kind')), $w['events_list']['config']['filters'], 'the events from today, by kind');
	pg_assert_same(array(array('field' => 'event_date', 'operator' => 'is less than', 'value' => '', 'dynamic_value' => 'current date')),
		$w['past_events_list']['config']['filters'], 'the past events before today');
	pg_assert_same(array($featured), $w['home_beers']['config']['filters'], 'the home beers are featured');
	pg_assert_same(array($from_today, $featured), $w['home_events']['config']['filters'], 'the home events are featured and to come');
	pg_assert_same(array('event_date', 'ASC'), array($w['events_list']['config']['order_by_field'], $w['events_list']['config']['order_by_direction']), 'the nearest event first');
	pg_assert_same(array('event_date', 'DESC'), array($w['past_events_list']['config']['order_by_field'], $w['past_events_list']['config']['order_by_direction']), 'the latest past event first');
	pg_assert_same(array('event_date', 'ASC'), array($w['home_events']['config']['order_by_field'], $w['home_events']['config']['order_by_direction']), 'the home: the nearest event first');
	pg_assert_same(3, $w['home_beers']['config']['max_results'], 'three beers on the home');
	pg_assert_same(3, $w['home_events']['config']['max_results'], 'three events on the home');
	foreach (array('beers_list' => 'beer', 'home_beers' => 'beer', 'events_list' => 'event', 'past_events_list' => 'event', 'home_events' => 'event') as $key => $detail) {
		pg_assert_same('{{tab:' . $detail . '}}', $w[$key]['config']['detail_page_id'], "$key opens the $detail page");
		pg_assert_same('{{tab:' . $detail . '_new}}', $w[$key]['config']['custom_form_page_id'], "$key reads the $detail form");
		pg_assert_same('form_list_view', $w[$key]['config']['regionType'], "$key is a list");
		pg_assert_true(trim((string)$w[$key]['config']['empty_message']) !== '', "$key says something when empty");
	}
	// Every filter link of a list page names an answer of its pick list.
	$answers = array('beers' => array('style', $d['style_titles']), 'events' => array('kind', $d['event_kind_titles']));
	foreach ($answers as $page_key => $a) {
		$nodes = array();
		_test_bwc_nodes($d['pages'][$page_key]['tree'], $nodes);
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

// The beer page takes the drinkers' notes with stars in its own Comments
// widget, which the brewery is told about; both record pages show the
// not-found note and the staff's Edit button.
function test_brewery_collections_record_pages()
{
	$d = _test_bwc_load();
	$c = $d['pages']['beer']['comments'];
	pg_assert_true($c['allow_new'], 'drinkers may write');
	pg_assert_true($c['rating'], 'the notes carry stars');
	pg_assert_true($c['login'], 'a drinker signs in to write');
	pg_assert_same(lang('Note'), $c['label'], 'a note is the comments\' noun');
	pg_assert_same('{{tab:email_reply}}', $c['email_page'], 'the brewery is told by e-mail');
	pg_assert_same('{{site_email}}', $c['notify_email'], 'at the site\'s address');
	pg_assert_same('comments', $d['widgets']['beer_notes']['config']['regionType']);
	pg_assert_contains('"templateWidget":"beer_notes"', json_encode($d['pages']['beer']['tree']), 'the notes sit on the beer page');
	pg_assert_contains('"eo_visible_if":"rating_on"', json_encode($d['widgets']['beer_notes']['tree']), 'the stars input shows while the page asks for them');
	pg_assert_false(isset($d['pages']['event']['comments']), 'an event takes no comments');
	foreach (array('beer_detail' => 'beer_new', 'event_detail' => 'event_new') as $key => $form) {
		$cfg = $d['widgets'][$key]['config'];
		pg_assert_same('form_item_view', $cfg['regionType'], "$key: a record view");
		pg_assert_same('{{tab:' . $form . '}}', $cfg['custom_form_page_id'], "$key: reads $form");
		pg_assert_same('public', $cfg['access_control'], "$key: anybody may open it");
		$json = json_encode($d['widgets'][$key]['tree']);
		pg_assert_contains('"eo_visible_if":"record_found"', $json, "$key: the record");
		pg_assert_contains('"eo_visible_if":"record_not_found"', $json, "$key: the not-found note");
		pg_assert_contains('__edit_url', $json, "$key: the staff's Edit button");
		$nodes = array();
		_test_bwc_nodes($d['widgets'][$key]['tree'], $nodes);
		$h1 = 0;
		foreach ($nodes as $n) {
			if (($n['props']['tag'] ?? '') === 'h1') $h1++;
		}
		pg_assert_same(1, $h1, "$key: one h1");
	}
}

function test_brewery_collections_links_ids_and_classes()
{
	$d = _test_bwc_load();
	$known = array_flip(_test_bw_page_keys());
	$trees = array();
	foreach ($d['pages'] as $p) $trees[] = $p;
	foreach ($d['widgets'] as $w) $trees[] = array($w['config'], $w['tree']);
	$json = json_encode($trees);
	preg_match_all('/\{\{(?:page|tab):([a-z0-9_]+)\}\}/', $json, $m);
	pg_assert_true(count(array_unique($m[1])) > 8, 'links found');
	foreach (array_unique($m[1]) as $key) pg_assert_true(isset($known[$key]), "page link {{page:$key}} names a template page");
	foreach (_test_bw_shop_keys() as $key) {
		pg_assert_false(strpos($json, '{{page:' . $key . '}}') !== false, "no tree links to $key");
		pg_assert_false(strpos($json, '{{tab:' . $key . '}}') !== false, "no setting names $key");
	}
	$nodes = array();
	foreach ($d['pages'] as $p) _test_bwc_nodes($p['tree'], $nodes);
	foreach ($d['widgets'] as $w) _test_bwc_nodes($w['tree'], $nodes);
	foreach ($nodes as $n) {
		foreach (preg_split('/\s+/', (string)($n['props']['cssClass'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $c) {
			pg_assert_false((bool)preg_match('/^(pht|pre|pev|pf|pdp|pm|pma|plx|pdl|plw|psl|pco|pcl|pgy|pch|ptv|pph|pwd|pcn|pmu|psc|pmz|pai)-/', $c), "class $c is not another template's");
			// Faded text measures below the contrast a text needs.
			pg_assert_false(strpos($c, 'opacity-') === 0, "class $c fades text");
		}
		$id = (string)($n['props']['id'] ?? '');
		if ($id !== '' && strpos($id, '-') !== false) pg_assert_true(strpos($id, 'bw-') === 0, "id $id is the template's");
	}
}

// No year or month in the sources: the events count from today and a
// seasonal beer names its season in words.
function test_brewery_collections_no_fixed_dates()
{
	foreach (array('beers.php', 'events.php') as $file) {
		$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-brewery/' . $file);
		$src = preg_replace('/@copyright[^\n]*/', '', $src);
		pg_assert_false((bool)preg_match('/\b(19|20)[0-9]{2}\b/', $src), "$file: no year");
		pg_assert_false((bool)preg_match('/\b[0-9]{4}-[0-9]{2}-[0-9]{2}\b/', $src), "$file: no fixed day");
		pg_assert_false((bool)preg_match('/\b(January|February|March|April|June|July|August|September|October|November|December)\b/', $src), "$file: no month");
	}
}

// Every literal has its Turkish; a beer reads "bira", an event "etkinlik",
// the taproom "tadım salonu", and no key falls on a shared word that means
// something else (Entry, Strength).
function test_brewery_collections_turkish_words()
{
	$map = pg_test_language_map('tr');
	foreach (array('beers.php', 'events.php') as $file) {
		$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-brewery/' . $file);
		preg_match_all("/lang\\('((?:[^'\\\\]|\\\\.)*)'\\)/", $src, $m);
		foreach (array_unique($m[1]) as $raw) {
			$key = stripslashes($raw);
			pg_assert_true(isset($map[$key]) && $map[$key] !== '', "$file: Turkish for '$key'");
			if (!isset($map[$key])) continue;
			$tr = $map[$key];
			if (preg_match('/^(Our |All |New )?Beers?\b/', $key)) pg_assert_true(mb_stripos($tr, 'bira', 0, 'UTF-8') !== false, "'$key' says bira");
			if (preg_match('/^(All |New |Past |Taproom )?Events?\b/', $key)) pg_assert_true(mb_stripos($tr, 'etkinlik', 0, 'UTF-8') !== false, "'$key' says etkinlik");
			if (preg_match('/\bTaproom\b/', $key)) pg_assert_true(mb_stripos($tr, 'tadım salonu', 0, 'UTF-8') !== false, "'$key' says tadım salonu");
			pg_assert_false(in_array($key, array('Entry', 'Strength', 'Style', 'Kind'), true), "$file: '$key' means something else in Turkish");
		}
	}
}
