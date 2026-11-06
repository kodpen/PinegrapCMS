<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Travel Agency design template's destinations and tours
 * (includes/design_templates/pinegrap-travel/tours.php): the pages and
 * widgets, the two collections with a sample per destination and per tour
 * whose values fit their fields, the lists' filters reading what the
 * filter links and the footer send, the home lists the featured records,
 * the tour page takes reviews with stars and books the tour it shows,
 * every page link names a template page and none a shop page, ids and
 * classes are the template's own, and every literal has its Turkish.
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
require_once(__DIR__ . '/design_template_travel_test.php');

// The tours' part as the template file reads it: parts.php first, then
// tours.php in the same scope. Read once per run.
function _test_tvt_load()
{
	static $out = null;
	if ($out !== null) return $out;
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-travel';
	include $dir . '/parts.php';
	$part = include $dir . '/tours.php';
	$pages = array();
	foreach ($part['pages'] as $p) $pages[$p['key']] = $p;
	$out = array('pages' => $pages, 'widgets' => $part['widgets'], 'tours' => $tours, 'destinations' => $destinations,
		'region_titles' => $region_titles, 'destination_names' => $destination_names, 'style_titles' => $style_titles,
		'itinerary_text' => $itinerary_text, 'deposit_text' => $money($deposit));
	return $out;
}

// Every node of a tree, depth first.
function _test_tvt_nodes($node, &$out)
{
	if (!is_array($node)) return;
	if (isset($node['props'])) $out[] = $node;
	foreach ((array)($node['children'] ?? array()) as $c) _test_tvt_nodes($c, $out);
}

// The name and options of every form control of a tree.
function _test_tvt_controls($node)
{
	$nodes = array();
	_test_tvt_nodes($node, $nodes);
	$out = array();
	foreach ($nodes as $n) {
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
		$out[$name] = array('tag' => $tag, 'options' => $options, 'cf' => $n['props']['_cf'] ?? array());
	}
	return $out;
}

function test_travel_tours_pages_and_widgets()
{
	$d = _test_tvt_load();
	pg_assert_same(array('destinations', 'destination', 'tours', 'tour', 'destination_new', 'tour_new'), array_keys($d['pages']));
	pg_assert_same(array('destination_form', 'tour_form', 'destinations_list', 'home_destinations', 'tours_list', 'home_tours', 'tour_reviews', 'destination_detail', 'tour_detail'), array_keys($d['widgets']));
	foreach (array('destinations', 'destination', 'tours', 'tour') as $key) pg_assert_same('public', $d['pages'][$key]['folder'], "$key is public");
	foreach (array('destination_new', 'tour_new') as $key) {
		pg_assert_same('private', $d['pages'][$key]['folder'], "only the staff open $key");
		pg_assert_true(!empty($d['pages'][$key]['noindex']), "$key is not indexed");
	}
	foreach ($d['pages'] as $key => $p) {
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
	pg_assert_same('home', $d['widgets']['home_tours']['page']);
	pg_assert_same('home', $d['widgets']['home_destinations']['page']);
}

// Each form is a collection with a sample per record; every sample value
// has its field and is filled, a pick list's value is one of its answers,
// the photos are images and featured is the staff's.
function test_travel_tours_forms_and_samples()
{
	$d = _test_tvt_load();
	$forms = array(
		'destination_new' => array('destination_form', 'destinations', array('name', 'country', 'region', 'season', 'summary', 'description', 'cover', 'featured'), array('cover')),
		'tour_new'        => array('tour_form', 'tours', array('name', 'destination', 'style', 'days', 'group_size', 'length', 'departures', 'price', 'summary', 'highlights', 'itinerary', 'photo_1', 'photo_2', 'photo_3', 'featured'), array('photo_1', 'photo_2', 'photo_3')),
	);
	foreach ($forms as $page_key => $w) {
		$form = $d['pages'][$page_key]['form'];
		pg_assert_same(1, $form['collection'], "$page_key: a collection");
		pg_assert_same(count($d[$w[1]]), count($form['sample_records']), "$page_key: one sample per record");
		$controls = _test_tvt_controls($d['widgets'][$w[0]]['tree']);
		foreach ($w[2] as $name) pg_assert_true(isset($controls[$name]), "$page_key: the form has $name");
		foreach ($w[3] as $name) pg_assert_same('image', $controls[$name]['cf']['kind'] ?? '', "$page_key: $name is an image");
		pg_assert_true(!empty($controls['featured']['cf']['office_use_only']), "$page_key: featured is the staff's");
		pg_assert_true(isset($controls[$form['share_image_field']]), "$page_key: the share image is a field");
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
	// The pick lists give the words parts.php gives, so the links filter.
	$dest = _test_tvt_controls($d['widgets']['destination_form']['tree']);
	pg_assert_same($d['region_titles'], $dest['region']['options'], 'the regions');
	$tour = _test_tvt_controls($d['widgets']['tour_form']['tree']);
	pg_assert_same($d['destination_names'], $tour['destination']['options'], 'the destinations');
	pg_assert_same($d['style_titles'], $tour['style']['options'], 'the styles');
	pg_assert_same('number', $tour['days']['cf']['kind'] ?? '', 'days sort as a number');
	pg_assert_same('number', $tour['group_size']['cf']['kind'] ?? '', 'group size sorts as a number');
	// The sample itinerary is the tour's own, a line per day.
	foreach ($d['pages']['tour_new']['form']['sample_records'] as $n => $r) {
		$key = array_keys($d['tours'])[$n];
		pg_assert_same(call_user_func($d['itinerary_text'], $key), $r['itinerary'], "tour sample $n: its itinerary");
	}
}

// The lists read the parameters the filter links and the footer send; the
// home lists show the featured records, three at most.
function test_travel_tours_filters()
{
	$d = _test_tvt_load();
	$expect = array('destinations_list' => array('region' => 'region'), 'tours_list' => array('destination' => 'destination', 'style' => 'style'));
	foreach ($expect as $key => $want) {
		$params = array();
		foreach ($d['widgets'][$key]['config']['filters'] as $f) {
			pg_assert_same('url parameter', $f['dynamic_value'], "$key: the filter reads the address");
			$params[$f['field']] = $f['dynamic_value_attribute'];
		}
		pg_assert_same($want, $params, "$key: its filters");
	}
	// Every filter link of a list page names an answer of its pick list.
	$answers = array('region' => $d['region_titles'], 'destination' => $d['destination_names'], 'style' => $d['style_titles']);
	foreach (array('destinations', 'tours') as $page_key) {
		$nodes = array();
		_test_tvt_nodes($d['pages'][$page_key]['tree'], $nodes);
		$seen = 0;
		foreach ($nodes as $n) {
			$href = (string)($n['props']['href'] ?? '');
			if (strpos($href, '{{page:' . $page_key . '}}?') !== 0) continue;
			parse_str(substr($href, strlen('{{page:' . $page_key . '}}?')), $q);
			foreach ($q as $param => $value) pg_assert_true(in_array($value, $answers[$param], true), "$page_key: ?$param=$value is an answer");
			$seen++;
		}
		pg_assert_true($seen >= 4, "$page_key: filter links");
	}
	foreach (array('home_tours', 'home_destinations') as $key) {
		$c = $d['widgets'][$key]['config'];
		pg_assert_same(array(array('field' => 'featured', 'operator' => 'is equal to', 'value' => lang('Yes'), 'dynamic_value' => '')), $c['filters'], "$key: the featured ones");
		pg_assert_same(3, $c['max_results'], "$key: three at most");
	}
}

// The tour page takes reviews with stars in its own Comments widget, books
// the tour it shows (?tour= from the record's name) and names the deposit;
// the destination page opens its tours (?destination=).
function test_travel_tours_record_pages()
{
	$d = _test_tvt_load();
	$c = $d['pages']['tour']['comments'];
	pg_assert_true($c['allow_new'] && $c['rating'], 'reviews with stars');
	pg_assert_same('{{tab:email_reply}}', $c['email_page'], 'the agency is told by e-mail');
	pg_assert_same('comments', $d['widgets']['tour_reviews']['config']['regionType']);
	$links = array();
	foreach (array('tour_detail', 'destination_detail') as $key) {
		$nodes = array();
		_test_tvt_nodes($d['widgets'][$key]['tree'], $nodes);
		foreach ($nodes as $n) {
			if (($n['props']['_bindings']['href'] ?? '') === '__page_link') $links[$key][] = $n['props']['_bindParams']['href'];
		}
	}
	pg_assert_same(array(array('page_id' => '{{tab:book}}', 'params' => array(array('name' => 'tour', 'token' => 'name')))), $links['tour_detail'], 'the tour page books its tour');
	pg_assert_same(array(array('page_id' => '{{tab:tours}}', 'params' => array(array('name' => 'destination', 'token' => 'name')))), $links['destination_detail'], 'the destination page opens its tours');
	pg_assert_contains($d['deposit_text'], json_encode($d['widgets']['tour_detail']['tree'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 'the tour page names the deposit');
	foreach (array('tour_detail', 'destination_detail') as $key) {
		$json = json_encode($d['widgets'][$key]['tree']);
		pg_assert_contains('"eo_visible_if":"record_found"', $json, "$key: the record");
		pg_assert_contains('"eo_visible_if":"record_not_found"', $json, "$key: the not-found note");
	}
}

function test_travel_tours_links_ids_and_classes()
{
	$d = _test_tvt_load();
	$known = array_flip(_test_tv_page_keys());
	$trees = array();
	foreach ($d['pages'] as $p) $trees[] = $p;
	foreach ($d['widgets'] as $w) $trees[] = array($w['config'], $w['tree']);
	$json = json_encode($trees);
	preg_match_all('/\{\{(?:page|tab):([a-z0-9_]+)\}\}/', $json, $m);
	foreach (array_unique($m[1]) as $key) pg_assert_true(isset($known[$key]), "page link {{page:$key}} names a template page");
	foreach (_test_tv_shop_keys() as $key) {
		pg_assert_false(strpos($json, '{{page:' . $key . '}}') !== false, "no tree links to $key");
	}
	$nodes = array();
	foreach ($trees as $t) _test_tvt_nodes(is_array($t) && isset($t['tree']) ? $t['tree'] : $t, $nodes);
	foreach ($nodes as $n) {
		foreach (preg_split('/\s+/', (string)($n['props']['cssClass'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $c) {
			pg_assert_false((bool)preg_match('/^(pht|pre|pev|pf|pdp|pm|pma|plx|pdl|plw|psl|pco|pcl|pgy|pch)-/', $c), "class $c is not another template's");
		}
		$id = (string)($n['props']['id'] ?? '');
		if ($id !== '' && strpos($id, '-') !== false) pg_assert_true(strpos($id, 'tv-') === 0, "id $id is the template's");
	}
}

// Every literal has its Turkish; a tour reads "tur", a destination
// "destinasyon", a booking "rezervasyon" and a deposit "kapora".
function test_travel_tours_turkish_words()
{
	$map = pg_test_language_map('tr');
	$src = file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-travel/tours.php');
	preg_match_all("/lang\\('((?:[^'\\\\]|\\\\.)*)'\\)/", $src, $m);
	foreach (array_unique($m[1]) as $raw) {
		$key = stripslashes($raw);
		pg_assert_true(isset($map[$key]) && $map[$key] !== '', "Turkish for '$key'");
		if (!isset($map[$key])) continue;
		$tr = $map[$key];
		if (preg_match('/\b[Tt]ours?\b/', $key)) pg_assert_true(mb_stripos($tr, 'tur', 0, 'UTF-8') !== false, "'$key' says tur");
		if (preg_match('/\b[Dd]estinations?\b/', $key)) pg_assert_true(mb_stripos($tr, 'destinasyon', 0, 'UTF-8') !== false, "'$key' says destinasyon");
		if (preg_match('/\b[Bb]ooking\b/', $key)) pg_assert_true(mb_stripos($tr, 'rezervasyon', 0, 'UTF-8') !== false, "'$key' says rezervasyon");
		if (preg_match('/\b[Dd]eposits?\b/', $key)) pg_assert_true(mb_stripos($tr, 'kapora', 0, 'UTF-8') !== false, "'$key' says kapora");
	}
	// Destination reads Hedef: the template's own keys name a place.
	pg_assert_false(strpos($src, "lang('Destination')") !== false, "the part does not use lang('Destination')");
}
