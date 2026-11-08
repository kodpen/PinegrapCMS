<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Bike Shop design template's group rides
 * (includes/design_templates/pinegrap-bikeshop/rides.php): the pages and
 * widgets of the plan, each widget on its page, the Group Rides form a
 * collection whose sample rides are dated from today with answers of the
 * pace and drop rule lists, the lists keeping the rides from today on and
 * narrowing to the pace of the address, every ?pace= link in the list's
 * words, the cards and the ride's page reading the form, the week in the
 * rides block's structure, the template's ids and classes, no fixed dates
 * and every literal in Turkish.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(__DIR__ . '/design_template_bikeshop_test.php');

// parts.php, cards.php, the public pages and rides.php in one scope, as
// the template file reads them. Read once per run.
function _test_bikeshopride_load()
{
	static $out = null;
	if ($out !== null) return $out;
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-bikeshop';
	include $dir . '/parts.php';
	include $dir . '/cards.php';
	$public = include $dir . '/pages_public.php';
	$part = include $dir . '/rides.php';
	$pages = array();
	foreach ($part['pages'] as $p) $pages[$p['key']] = $p;
	$public_pages = array();
	foreach ($public['pages'] as $p) $public_pages[$p['key']] = $p;
	$dates = array();
	foreach ($rides as $key => $r) $dates[$key] = $ride_on($r['weekday'], $r['weeks']);
	for ($wd = 1; $wd <= 7; $wd++) $dates[] = $ride_on($wd);
	$out = array('part' => $part, 'pages' => $pages, 'widgets' => $part['widgets'], 'public' => $public_pages,
		'rides' => $rides, 'dates' => $dates, 'pace_titles' => $pace_titles, 'drop_titles' => $drop_titles,
		'safety' => $job_kinds['safety']['title'],
		'shared' => array('header' => $header(), 'footer' => $footer()));
	return $out;
}

function _test_bikeshopride_nodes($tree)
{
	$nodes = array();
	_test_bikeshop_nodes($tree, $nodes);
	return $nodes;
}

// The name, options and _cf of every form control of a tree.
function _test_bikeshopride_controls($tree)
{
	$out = array();
	foreach (_test_bikeshopride_nodes($tree) as $n) {
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
		if (isset($out[$name])) continue;
		$out[$name] = array('tag' => $tag, 'options' => $options, 'cf' => $n['props']['_cf'] ?? array());
	}
	return $out;
}

function test_bikeshop_rides_pages_and_widgets()
{
	$d = _test_bikeshopride_load();
	pg_assert_same(array('pages', 'widgets'), array_keys($d['part']), 'the part returns pages and widgets');
	pg_assert_same(array('rides', 'ride', 'ride_new'), array_keys($d['pages']));
	pg_assert_same(array('ride_form', 'rides_list', 'home_rides', 'ride_detail'), array_keys($d['widgets']));
	$known = array_flip(_test_bikeshop_page_keys());
	$folders = array('rides' => 'public', 'ride' => 'public', 'ride_new' => 'private');
	foreach ($d['pages'] as $key => $p) {
		pg_assert_true(isset($known[$key]), "$key is a planned page");
		pg_assert_same($folders[$key], $p['folder'], "$key: its folder");
		pg_assert_false(isset($p['requires']), "$key stands without the shop");
		pg_assert_same('root', $p['tree']['type'], "$key: a page tree");
		pg_assert_true(mb_strlen((string)($p['meta_description'] ?? ''), 'UTF-8') <= 160, "$key: meta description fits a result line");
		pg_assert_true((bool)preg_match('/^[a-z0-9-]+$/', $p['name']), "$key: the page name is an address");
	}
	pg_assert_true(!empty($d['pages']['ride_new']['noindex']) && $d['pages']['ride_new']['search'] === false, 'the staff page is out of the search');
	$trees = array();
	foreach ($d['pages'] as $key => $p) $trees[$key] = $p['tree'];
	$trees['home'] = $d['public']['home']['tree'];
	foreach ($d['widgets'] as $key => $w) {
		pg_assert_contains('"templateWidget":"' . $key . '"', json_encode($trees[$w['page']]), "$key is placed on its page");
	}
	$all = json_encode($d['part']);
	preg_match_all('/\{\{(?:page|tab):([a-z0-9_]+)\}\}/', $all, $m);
	foreach (array_unique($m[1]) as $key) pg_assert_true(isset($known[$key]), "link {{page:$key}} names a template page");
	foreach (_test_bikeshop_shop_keys() as $key) pg_assert_false(strpos($all, '{{page:' . $key . '}}') !== false, "no link to $key");
	// The header, the footer and the staff bar reach the rides.
	pg_assert_contains('{{page:rides}}', json_encode($d['shared']['header']), 'the header links the rides');
}

// The Group Rides form is a collection: the sample rides are its records,
// each still to come and with answers of its lists.
function test_bikeshop_rides_form_and_samples()
{
	$d = _test_bikeshopride_load();
	$form = $d['pages']['ride_new']['form'];
	pg_assert_same(1, $form['collection'], 'the rides are a collection');
	pg_assert_same('{{tab:rides}}', $form['confirmation_page_id'], 'a ride published opens the rides');
	$c = _test_bikeshopride_controls($d['widgets']['ride_form']['tree']);
	$fields = array('title', 'ride_date', 'start_time', 'pace', 'drop_rule', 'distance', 'climbing', 'meeting_point', 'route_link', 'summary', 'photo_1', 'featured');
	pg_assert_same($fields, array_keys($c), 'the ride\'s fields');
	pg_assert_same($d['pace_titles'], $c['pace']['options'], 'the pace offers its list');
	pg_assert_same($d['drop_titles'], $c['drop_rule']['options'], 'the drop rule offers its list');
	pg_assert_same('today', $c['ride_date']['cf']['rules']['min_date'] ?? '', 'a new ride is not before today');
	pg_assert_true(!empty($c['featured']['cf']['office_use_only']), 'featured is the staff\'s');
	pg_assert_same('image', $c['photo_1']['cf']['kind'] ?? '', 'the photo is a picture');
	pg_assert_same(count($d['rides']), count($form['sample_records']), 'a record per sample ride');
	$today = date('Y-m-d');
	$featured = 0;
	foreach (array_values($form['sample_records']) as $n => $rec) {
		foreach (array_keys($rec) as $f) pg_assert_true(in_array($f, $fields, true), "sample $n: $f is a field");
		pg_assert_true($rec['ride_date'] > $today, "sample $n: still to come");
		pg_assert_true(in_array($rec['ride_date'], $d['dates'], true), "sample $n: dated from today");
		pg_assert_true(in_array($rec['pace'], $d['pace_titles'], true), "sample $n: its pace is an answer");
		pg_assert_true(in_array($rec['drop_rule'], $d['drop_titles'], true), "sample $n: its drop rule is an answer");
		pg_assert_true(in_array($rec['featured'], $c['featured']['options'], true), "sample $n: featured is an answer");
		if ($rec['featured'] === $c['featured']['options'][0]) $featured++;
	}
	pg_assert_true($featured >= 2, 'the home has featured rides to show');
}

// The lists keep the rides from today on, nearest first; the rides list
// narrows to the pace of the address, the home's to the featured ones.
function test_bikeshop_rides_lists_from_today_by_pace()
{
	$d = _test_bikeshopride_load();
	foreach (array('rides_list', 'home_rides') as $key) {
		$cfg = $d['widgets'][$key]['config'];
		pg_assert_same('form_list_view', $cfg['regionType'], "$key: a list");
		pg_assert_same('{{tab:ride_new}}', $cfg['custom_form_page_id'], "$key reads the Group Rides");
		pg_assert_same('{{tab:ride}}', $cfg['detail_page_id'], "$key opens the ride");
		pg_assert_same(array('ride_date', 'ASC'), array($cfg['order_by_field'], $cfg['order_by_direction']), "$key: nearest first");
		pg_assert_same(array('field' => 'ride_date', 'operator' => 'is greater than or equal to', 'value' => '', 'dynamic_value' => 'current date'),
			$cfg['filters'][0], "$key: from today on");
		pg_assert_true(trim($cfg['empty_message']) !== '', "$key: words when nothing matches");
	}
	$pace = $d['widgets']['rides_list']['config']['filters'][1];
	pg_assert_same(array('pace', 'is equal to', '', 'url parameter', 'pace'),
		array($pace['field'], $pace['operator'], $pace['value'], $pace['dynamic_value'], $pace['dynamic_value_attribute']), 'the rides list follows ?pace=');
	$home = $d['widgets']['home_rides']['config'];
	pg_assert_same('featured', $home['filters'][1]['field'], 'the home shows the featured rides');
	pg_assert_true($home['max_results'] <= 3, 'the home shows a few');
	// Every ?pace= link, on the rides page, the home and the footer, is an
	// answer of the pace list; each pace has one.
	$trees = $d['shared'];
	foreach ($d['pages'] as $key => $p) $trees[$key] = $p['tree'];
	$trees['home'] = $d['public']['home']['tree'];
	$seen = array();
	foreach ($trees as $key => $tree) {
		foreach (_test_bikeshopride_nodes($tree) as $n) {
			if (!preg_match('/^\{\{page:rides\}\}\?(.*)$/', (string)($n['props']['href'] ?? ''), $m)) continue;
			parse_str($m[1], $q);
			pg_assert_same(array('pace'), array_keys($q), "$key: a rides link sends only ?pace=");
			pg_assert_true(in_array($q['pace'], $d['pace_titles'], true), "$key: pace '{$q['pace']}' is an answer");
			$seen[$q['pace']] = true;
		}
	}
	foreach ($d['pace_titles'] as $t) pg_assert_true(isset($seen[$t]), "a link opens the $t rides");
	pg_assert_contains('{{page:service_request}}?job=' . rawurlencode($d['safety']), json_encode($d['pages']['rides']['tree'], JSON_UNESCAPED_SLASHES),
		'the rides page sends the safety check in the job list\'s words');
}

// The cards and the ride's page bind only the form's fields.
function test_bikeshop_rides_cards_read_the_form()
{
	$d = _test_bikeshopride_load();
	$fields = array_keys(_test_bikeshopride_controls($d['widgets']['ride_form']['tree']));
	foreach (array('rides_list', 'home_rides', 'ride_detail') as $key) {
		$seen = array();
		foreach (_test_bikeshopride_nodes($d['widgets'][$key]['tree']) as $n) {
			foreach ((array)($n['props']['_bindings'] ?? array()) as $attr => $token) {
				if (!in_array($attr, array('text', 'href', 'src', 'alt'), true)) continue;
				if ($token === 'form_item_view' || $token === 'not_found' || strpos($token, '__') === 0) continue;
				pg_assert_true(in_array($token, $fields, true), "$key: $token is a field of the form");
				$seen[$token] = true;
			}
		}
		foreach (array('title', 'ride_date', 'start_time', 'pace', 'drop_rule', 'meeting_point', 'distance', 'climbing', 'summary') as $f) {
			pg_assert_true(isset($seen[$f]), "$key shows $f");
		}
	}
	$detail = $d['widgets']['ride_detail'];
	pg_assert_same('form_item_view', $detail['config']['regionType'], 'the ride\'s page reads one record');
	pg_assert_same('public', $detail['config']['access_control'], 'every visitor sees a ride');
	pg_assert_contains('"href":"route_link"', json_encode($detail['tree']), 'the route button opens the route');
	pg_assert_contains('record_not_found', json_encode($detail['tree']), 'a ride not found says so');
}

// The week: the regular rides of this week in the rides block's structure,
// nearest first, the first marked as the next ride.
function test_bikeshop_rides_week_is_the_block()
{
	$d = _test_bikeshopride_load();
	$cards = array();
	$times = array();
	foreach (_test_bikeshopride_nodes($d['pages']['rides']['tree']) as $n) {
		if (($n['props']['tag'] ?? '') === 'article') $cards[] = $n;
		if (($n['props']['tag'] ?? '') === 'time') $times[] = $n['props']['text'];
	}
	$week = array();
	foreach ($d['rides'] as $key => $r) {
		if ((int)$r['weeks'] === 0) $week[$key] = $d['dates'][$key];
	}
	asort($week);
	pg_assert_same(count($week), count($cards), 'a card per regular ride');
	$expect = array();
	foreach (array_keys($week) as $key) $expect[] = $d['rides'][$key]['time'];
	pg_assert_same($expect, $times, 'the week nearest first, each with its start time');
	pg_assert_contains('"aria-current","value":"true"', str_replace('"name":', '', json_encode($cards[0]['props'])), 'the first is the next ride');
	pg_assert_false(strpos(json_encode($cards[1]['props']), 'aria-current') !== false, 'only one next ride');
	$json = json_encode($d['pages']['rides']['tree']);
	pg_assert_contains('pg-bike-num', $json, 'the figures use the block\'s rule');
	pg_assert_contains('"templateWidget":"rides_list"', $json, 'the calendar follows the week');
}

function test_bikeshop_rides_ids_classes_and_headings()
{
	$d = _test_bikeshopride_load();
	$ids = array();
	$trees = array();
	foreach ($d['pages'] as $key => $p) $trees[$key] = $p['tree'];
	foreach ($d['widgets'] as $key => $w) $trees['widget ' . $key] = $w['tree'];
	foreach ($trees as $key => $tree) {
		foreach (_test_bikeshopride_nodes($tree) as $n) {
			foreach (preg_split('/\s+/', (string)($n['props']['cssClass'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $c) {
				pg_assert_false((bool)preg_match('/^(psk|pzo|pgl|pmz|pph|pvt|pre|pev|pcw|sk)-/', $c), "$key: class $c is not another template's");
				pg_assert_false((bool)preg_match('/^[pm][trblxy]?(-[a-z]{2,3})?-[6-9]$/', $c), "$key: class $c exists in Bootstrap 5.3");
				pg_assert_false(strpos($c, 'opacity-') === 0, "$key: no faded text ($c)");
			}
			$id = (string)($n['props']['id'] ?? '');
			if ($id === '') continue;
			pg_assert_true(strpos($id, 'bikeshop-') === 0, "$key: id $id is the template's");
			pg_assert_false(isset($ids[$id]), "id $id once");
			$ids[$id] = true;
		}
	}
	foreach (array('rides', 'ride_new') as $key) {
		$levels = array();
		foreach (_test_bikeshopride_nodes($d['pages'][$key]['tree']) as $n) {
			if (preg_match('/^h([1-6])$/', (string)($n['props']['tag'] ?? ''), $m)) $levels[] = (int)$m[1];
		}
		$set = array_values(array_unique($levels));
		sort($set);
		pg_assert_same(range(1, max($set)), $set, "$key: no heading level skipped");
	}
}

// The only dates are the samples' counted from today.
function test_bikeshop_rides_no_fixed_dates()
{
	$d = _test_bikeshopride_load();
	$json = rawurldecode(json_encode($d['part'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
	$json = str_replace(array_values($d['dates']), '', $json);
	// The sample pictures' addresses carry their sizes, not years.
	$json = preg_replace('/https?:\/\/[^"\s]+/', '', $json);
	pg_assert_false((bool)preg_match('/\b(19|20)[0-9]{2}\b/', $json), 'no year in the sample');
	pg_assert_false((bool)preg_match('/\b(January|February|March|April|June|July|August|September|October|November|December)\b/', $json), 'no month in the sample');
}

function test_bikeshop_rides_turkish_words()
{
	$map = pg_test_language_map('tr');
	$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-bikeshop/rides.php');
	preg_match_all("/lang\\('((?:[^'\\\\]|\\\\.)*)'\\)/", $src, $m);
	foreach (array_unique($m[1]) as $raw) {
		$key = stripslashes($raw);
		pg_assert_true(isset($map[$key]) && $map[$key] !== '', "rides.php: Turkish for '$key'");
		if (!isset($map[$key])) continue;
		if (preg_match('/\bgroup rides?\b/i', $key)) pg_assert_true(mb_stripos($map[$key], 'grup sürüş', 0, 'UTF-8') !== false, "'$key' says grup sürüşü");
		if (preg_match('/\bpace\b/i', $key) && strpos($key, ',') === false) pg_assert_true(mb_stripos($map[$key], 'tempo', 0, 'UTF-8') !== false, "'$key' says tempo");
	}
}
