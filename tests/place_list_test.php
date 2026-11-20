<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the "Map of places" list (includes/fn/place_list.php,
 * places.php, assets/js/pg_map.js): which pages and records an answer may
 * carry, which fields (by the server's name rule, never the request's), the
 * cut at PG_PLACE_LIST_MAX and its note, the origin and rate checks, the
 * page's script, and the script's distance, order and search under Node;
 * "Near me" and the typed-address lookup (its rate, nothing typed kept).
 * No database is used.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/place_list.php');
require_once(PG_FUNCTIONS_DIR . '/waf.php');

function place_list_test_fields()
{
	return array(
		array('id' => 1, 'name' => 'Name', 'type' => 'text box', 'kind' => '', 'office' => 0),
		array('id' => 2, 'name' => 'Address', 'type' => 'text box', 'kind' => '', 'office' => 0),
		array('id' => 3, 'name' => 'Latitude', 'type' => 'text box', 'kind' => 'number', 'office' => 0),
		array('id' => 4, 'name' => 'Longitude', 'type' => 'text box', 'kind' => 'number', 'office' => 0),
		array('id' => 5, 'name' => 'Opening hours', 'type' => 'text area', 'kind' => '', 'office' => 0),
		array('id' => 6, 'name' => 'Phone', 'type' => 'text box', 'kind' => '', 'office' => 0),
		array('id' => 7, 'name' => 'Email', 'type' => 'email address', 'kind' => '', 'office' => 0),
		array('id' => 8, 'name' => 'Notes', 'type' => 'text area', 'kind' => '', 'office' => 1),
	);
}

// The fields come from the name rule: no e-mail, nothing office-use-only.
function test_place_list_fields_by_name_only()
{
	$roles = pg_place_list_field_roles(place_list_test_fields());

	pg_assert_same(array(2), $roles['address']);
	pg_assert_same(3, $roles['lat']);
	pg_assert_same(4, $roles['lng']);
	pg_assert_same(1, $roles['name']);
	pg_assert_same(5, $roles['hours']);
	pg_assert_same(6, $roles['phone']);

	foreach ($roles as $part => $value) {
		pg_assert_false(in_array(7, (array) $value, true) || in_array(8, (array) $value, true), $part . ' takes no e-mail and no office-use field');
	}

	$office = place_list_test_fields();
	$office[2]['office'] = 1;
	pg_assert_same(null, pg_place_list_field_roles($office), 'an office-use latitude is not a coordinate the answer carries');
	pg_assert_same(null, pg_place_list_field_roles(array_slice(place_list_test_fields(), 0, 2)), 'no coordinates: no places');
}

// A page closed to visitors, in the bin, missing, or without a public list gives nothing.
function test_place_list_page_gate()
{
	$list = array('cfg' => array());

	pg_assert_same('', pg_place_list_page_reason(true, 'public', false, $list, true));
	pg_assert_same('page', pg_place_list_page_reason(true, 'private', false, $list, true), 'a private folder');
	pg_assert_same('page', pg_place_list_page_reason(true, 'registration', false, $list, true));
	pg_assert_same('page', pg_place_list_page_reason(true, 'public', true, $list, true), 'in the bin');
	pg_assert_same('page', pg_place_list_page_reason(false, 'public', false, $list, true), 'no such page');
	pg_assert_same('list', pg_place_list_page_reason(true, 'public', false, null, true), 'no form list on the page');
	pg_assert_same('page', pg_place_list_page_reason(true, 'public', false, $list, false), 'a list for signed-in viewers only');
}

// Complete records only, the filters' clause applied, one more than the bound.
function test_place_list_records_query()
{
	$sql = pg_place_list_records_sql(42, array('join' => ' LEFT JOIN x ON 1', 'where' => " AND (x.v = 'a')"), array('sql' => 'forms.id DESC', 'join' => ''));

	pg_assert_contains("forms.page_id = '42'", $sql);
	pg_assert_contains('AND forms.complete = 1', $sql, 'an unfinished record is left out');
	pg_assert_contains(" AND (x.v = 'a')", $sql, 'the list\'s and its detail view\'s filters (a draft left out)');
	pg_assert_contains('LEFT JOIN x ON 1', $sql);
	pg_assert_contains('ORDER BY forms.id DESC', $sql);
	pg_assert_contains('LIMIT ' . (PG_PLACE_LIST_MAX + 1), $sql);
	pg_assert_same(500, PG_PLACE_LIST_MAX);
}

function test_place_list_item_and_cut_note()
{
	$roles = pg_place_list_field_roles(place_list_test_fields());
	$item = pg_place_list_item(array('id' => 9), array(1 => 'Moda', 2 => 'Moda Caddesi 12', 3 => '40.98', 4 => '29.02', 6 => '+90 216 000 00 00', 7 => 'x@example.com'), $roles, '/branches/moda');

	pg_assert_same(array('name' => 'Moda', 'address' => 'Moda Caddesi 12', 'hours' => '', 'phone' => '+90 216 000 00 00', 'lat' => 40.98, 'lng' => 29.02, 'url' => '/branches/moda'), $item);

	$bad = pg_place_list_item(array('id' => 9), array(2 => 'x', 3 => '95', 4 => '29'), $roles, '');
	pg_assert_same(null, $bad['lat'], 'a latitude out of range gives no marker');
	pg_assert_same(null, $bad['lng']);

	pg_assert_contains('500', pg_place_list_reason_text('cut'));
	pg_assert_contains('Near me', pg_place_list_reason_text('cut'), 'the designer is told both: the cut and no "Near me"');
	pg_assert_same('', pg_place_list_reason_text(''));
}

// The list's request carries the page id only; the lookup's the typed text
// only, in the body. Same origin first, then the rate.
function test_place_list_endpoint_checks()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/places.php');
	$cross = strpos($source, 'pg_request_cross_site($_SERVER)');
	$find_rate = strpos($source, 'pg_place_list_find_throttled()');
	$find = strpos($source, "pg_place_list_find(\$_POST['find'] ?? '')");
	$rate = strpos($source, 'pg_place_list_throttled()');
	$read = strpos($source, "\$_GET['page']");

	pg_assert_true(($cross !== false) && ($rate !== false) && ($read !== false) && ($find_rate !== false) && ($find !== false), 'the checks are there');
	pg_assert_true($cross < $find_rate && $find_rate < $find, 'origin, then the lookup\'s rate, then the text');
	pg_assert_true($find < $rate && $rate < $read, 'origin, then rate, then the page id');
	pg_assert_same(2, preg_match_all('/\$_(GET|POST|REQUEST)\[/', $source), 'no other request value is read');
	pg_assert_same(0, preg_match('/\$_(GET|REQUEST)\[.find/', $source), 'the typed text is never read from the address');
	pg_assert_same(30, PG_PLACE_LIST_RATE_LIMIT);
}

// The lookup has its own, lower bound: ten a minute per address, the
// eleventh refused, another address not held back.
function test_place_list_find_rate_limit()
{
	$counts = array();
	$seen = array();
	$record = function ($subject, $scope, $window) use (&$counts, &$seen) {
		$seen = array($scope, $window);
		$key = $subject . '|' . $scope;
		$counts[$key] = isset($counts[$key]) ? $counts[$key] + 1 : 1;

		return $counts[$key];
	};

	pg_assert_same(10, PG_PLACE_LIST_FIND_LIMIT);
	pg_assert_true(PG_PLACE_LIST_FIND_LIMIT < PG_PLACE_LIST_RATE_LIMIT, 'each lookup asks Photon: a lower bound than the list');

	for ($i = 1; $i <= 10; $i++) {
		pg_assert_false(pg_place_list_find_throttled('8.8.4.4', $record), 'lookup ' . $i . ' passes');
	}

	pg_assert_true(pg_place_list_find_throttled('8.8.4.4', $record), 'the eleventh is refused');
	pg_assert_false(pg_place_list_find_throttled('1.1.1.1', $record), 'another address counts on its own');
	pg_assert_same(array('place_find', PG_PLACE_LIST_RATE_WINDOW), $seen, 'its own scope, a minute');
	pg_assert_false(pg_place_list_throttled('8.8.4.4', $record), 'the list keeps its own counter');
	pg_assert_same(1, $counts[waf_ip_subject('8.8.4.4') . '|place_list'], 'the list counts apart from the lookup');
}

// Nothing typed is kept: the lookup writes no table, file, session, cookie
// or log, and its answer carries the point alone, not the text.
function test_place_list_find_keeps_nothing()
{
	$fn = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/place_list.php');
	$start = strpos($fn, 'function pg_place_list_find_text(');
	$body = substr($fn, $start, strpos($fn, "\n}\n", strpos($fn, 'function pg_place_list_find(')) - $start);

	pg_assert_true($start !== false && $body !== '', 'the lookup is there');
	pg_assert_same(0, preg_match('/\bdb\(|db_|mysqli_|file_put_contents|fopen|error_log|log_activity|waf_text_log|_SESSION|setcookie|pg_cache/', $body), 'no store, no log');

	$answer = pg_place_list_find_answer(array('state' => 'found', 'lat' => '41.0', 'lng' => '29.03', 'error' => 'Moda Caddesi'));
	pg_assert_same(array('ok' => true, 'state' => 'found', 'lat' => 41.0, 'lng' => 29.03), $answer, 'the point alone comes back');
	pg_assert_same(array('ok' => true, 'state' => 'not_found', 'lat' => null, 'lng' => null), pg_place_list_find_answer(array('state' => 'not_found')));
	pg_assert_same('error', pg_place_list_find_answer(array('state' => 'found', 'lat' => '', 'lng' => ''))['state'], 'found without a point is an error');
	pg_assert_same('error', pg_place_list_find_answer(array('state' => 'other'))['state']);
	pg_assert_same(array('ok' => true, 'state' => 'empty', 'lat' => null, 'lng' => null), pg_place_list_find('   '), 'nothing typed asks nothing');
	pg_assert_same(array('ok' => true, 'state' => 'empty', 'lat' => null, 'lng' => null), pg_place_list_find(array('x')), 'a list is no text');

	pg_assert_same('Moda Caddesi 12, Kadıköy', pg_place_list_find_text("  Moda  Caddesi 12,\n Kadıköy "));
	pg_assert_same(PG_PLACE_LIST_FIND_MAX, mb_strlen(pg_place_list_find_text(str_repeat('ş', 300)), 'UTF-8'));

	$endpoint = (string) file_get_contents(PG_FUNCTIONS_DIR . '/places.php');
	pg_assert_same(0, preg_match('/waf_text_log|error_log|log_activity/', $endpoint), 'the endpoint logs nothing itself');

	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/pg_map.js');
	pg_assert_same(0, preg_match('/localStorage|sessionStorage|document\.cookie\s*=/', $js), 'the page stores nothing typed or found');
	pg_assert_contains("method: 'POST'", $js, 'the text goes in the body, not the address');
	pg_assert_true(strpos($js, 'root.fetch(config.endpoint, {') > strpos($js, "form.addEventListener('submit'"), 'asked only on a press of Find');
}

// The box is drawn only while Photon can be asked; the page is told so.
function test_place_list_find_on()
{
	pg_assert_false(pg_place_list_find_on(false), 'without cURL no box');
	pg_assert_true(pg_place_list_find_on(true));

	$out = pg_place_list_inject('<body><div data-pg-map></div></body>', 3, 'x.js', array('on' => false));
	pg_assert_true(strpos($out, '"find":true') !== false || strpos($out, '"find":false') !== false, 'the page knows whether the box is drawn');
	pg_assert_contains('"Near me":', $out);
}

function test_place_list_inject()
{
	$page = '<html><body><div data-pg-map="">x</div></body></html>';
	$out = pg_place_list_inject($page, 17, '/pinegrap/assets/js/pg_map.js?v=1');

	pg_assert_same(1, substr_count($out, '/assets/js/pg_map.js'));
	pg_assert_contains('"page":17', $out);
	pg_assert_same($out, pg_place_list_inject($out, 17, '/pinegrap/assets/js/pg_map.js?v=1'), 'once');
	pg_assert_same('<p data-pg-map-page="1">x</p>', pg_place_list_inject('<p data-pg-map-page="1">x</p>', 1, 'x.js'), 'another attribute is no mark');
}

// The script's distance, order and search, run under Node.
function test_place_list_script_under_node()
{
	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);

	if ($code !== 0) {
		pg_assert_true(true, 'Node is not installed: the run is skipped');
		return;
	}

	$script = 'var m = require(' . json_encode(PG_FUNCTIONS_DIR . '/assets/js/pg_map.js') . ');'
		. 'var places = [{name:"Ankara", address:"Atatürk Bulvarı", lat:39.92, lng:32.85}, {name:"No point", address:"x"}, {name:"Kadıköy", address:"Moda Caddesi", lat:40.98, lng:29.02}, {name:"Beşiktaş", address:"Barbaros", lat:41.04, lng:29.0}];'
		. 'var o = m.order(places, 41.0, 29.03);'
		. 'console.log(JSON.stringify({km: Math.round(m.distance(41.0, 29.0, 39.92, 32.85)), order: o.map(function (r) { return r.place.name; }), first: Math.round(o[0].km * 10) / 10,'
		. ' kadikoy: m.matches(places[2], "KADIKOY moda"), istanbul: m.matches({name:"İstanbul"}, "istanbul"), miss: m.matches(places[0], "izmir"),'
		. ' near: m.arrange(places, "", {lat: 41.0, lng: 29.03}).map(function (r) { return r.place.name + (r.km === null ? "" : "@" + m.formatKm(r.km, "en")); }),'
		. ' nearSearch: m.arrange(places, "a", {lat: 39.9, lng: 32.8}).map(function (r) { return r.place.name; }),'
		. ' plain: m.arrange(places, "moda", null).map(function (r) { return r.place.name + (r.km === null ? "" : "!"); }),'
		. ' offered: [m.nearOffered(places, false), m.nearOffered(places, true), m.nearOffered([{name:"x"}], false)],'
		. ' fmt: [m.formatKm(2.345, "en"), m.formatKm(12.6, "en"), m.formatKm(2.35, "tr")]}));';
	// A file rather than node -e: escapeshellarg() on Windows turns the
	// script's double quotes into spaces.
	$file = tempnam(sys_get_temp_dir(), 'pgmap');
	file_put_contents($file, $script);
	$lines = array();
	exec('node ' . escapeshellarg($file) . ' 2>&1', $lines, $code);
	unlink($file);
	$result = json_decode(implode("\n", $lines), true);

	pg_assert_true(is_array($result), 'the script runs under Node');
	pg_assert_true(abs($result['km'] - 347) <= 2, 'these points of Istanbul and Ankara are about 347 km apart (' . $result['km'] . ')');
	pg_assert_same(array('Kadıköy', 'Beşiktaş', 'Ankara', 'No point'), $result['order'], 'nearest first, a place without coordinates last');
	pg_assert_true($result['first'] < 3, 'the nearest is within 3 km');
	pg_assert_true($result['kadikoy'], 'typed without accents and in capitals, every word');
	pg_assert_true($result['istanbul'], 'dotted capital İ matches i');
	pg_assert_false($result['miss']);
	pg_assert_same(array('Kadıköy@2.4', 'Beşiktaş@5.1', 'Ankara@345', 'No point'), $result['near'], 'from a point: nearest first, each with its distance');
	pg_assert_same(array('Ankara', 'Kadıköy', 'Beşiktaş'), $result['nearSearch'], 'the search narrows the ordered list');
	pg_assert_same(array('Kadıköy'), $result['plain'], 'without a point: the list order, no distance');
	pg_assert_same(array(true, false, false), $result['offered'], 'near me only for the whole list with a point in it');
	pg_assert_same(array('2.3', '13', '2,4'), $result['fmt'], 'one decimal under 10 km, in the page\'s language');
}

// The block's own pickers, saved on the block in the page's tree.
function place_list_test_tree($choice)
{
	$attrs = array(array('name' => 'data-pg-map', 'value' => ''));

	if ($choice !== null) {
		$attrs[] = array('name' => 'data-pg-map-fields', 'value' => is_string($choice) ? $choice : json_encode($choice));
	}

	return array('type' => 'root', 'children' => array(
		array('type' => 'semantic', 'props' => array('tag' => 'section'), 'children' => array(
			array('type' => 'semantic', 'props' => array('tag' => 'div', '_attrs' => array(array('name' => 'data-pg-map-list', 'value' => '')))),
			array('type' => 'semantic', 'props' => array('tag' => 'div', '_attrs' => $attrs), 'children' => array()),
		)),
	));
}

function test_place_list_saved_choice_from_tree()
{
	$choice = pg_place_list_saved_choice(place_list_test_tree(array('name' => 'Branch', 'hours' => 'Open', 'lat' => 'Y', 'other' => 'x', 'phone' => array('x'))));

	pg_assert_same(array('name' => 'Branch', 'hours' => 'Open', 'lat' => 'Y'), $choice, 'the known parts with a name only');
	pg_assert_same(array(), pg_place_list_saved_choice(place_list_test_tree(null)), 'no choice: the name rule');
	pg_assert_same(array(), pg_place_list_saved_choice(place_list_test_tree('{not json')), 'a broken value is no choice');
	pg_assert_same(array(), pg_place_list_saved_choice('not a tree'));
	pg_assert_same(array(), pg_place_list_saved_choice(array('children' => array(array('props' => array('_attrs' => array(array('name' => 'data-pg-map-fields', 'value' => '{"name":"Branch"}'))))))), 'a choice off the block does not count');
}

function test_place_list_chosen_fields()
{
	$fields = place_list_test_fields();
	$fields[] = array('id' => 9, 'name' => 'Branch', 'type' => 'text box', 'kind' => '', 'office' => 0);
	$fields[] = array('id' => 10, 'name' => 'Y', 'type' => 'text box', 'kind' => 'number', 'office' => 0);
	$fields[] = array('id' => 11, 'name' => 'Street', 'type' => 'text box', 'kind' => '', 'office' => 0);

	$roles = pg_place_list_field_roles($fields, array('name' => 'Branch', 'lat' => 'Y', 'address' => 'Street'));
	pg_assert_same(9, $roles['name'], 'a chosen text field');
	pg_assert_same(10, $roles['lat'], 'a chosen number field');
	pg_assert_same(array(11), $roles['address'], 'a chosen address is the address alone');
	pg_assert_same(4, $roles['lng'], 'a part not chosen keeps the name rule');
	pg_assert_same(5, $roles['hours']);

	// Office-use-only, another kind, another collection's field: refused, the name rule instead.
	$refused = pg_place_list_field_roles($fields, array('hours' => 'Notes', 'phone' => 'Email', 'name' => 'Missing', 'lat' => 'Branch', 'lng' => 'Notes'));
	pg_assert_same(5, $refused['hours'], 'an office-use-only field is refused');
	pg_assert_same(6, $refused['phone'], 'an e-mail field is not text');
	pg_assert_same(1, $refused['name'], 'a field the collection lacks');
	pg_assert_same(3, $refused['lat'], 'a text field is no coordinate');
	pg_assert_same(4, $refused['lng']);

	foreach ($refused as $part => $value) {
		pg_assert_false(in_array(8, (array) $value, true), $part . ' takes no office-use field');
	}

	// The pickers can give what the names do not.
	$plain = array(
		array('id' => 1, 'name' => 'Where', 'type' => 'text area', 'kind' => '', 'office' => 0),
		array('id' => 2, 'name' => 'North', 'type' => 'text box', 'kind' => 'number', 'office' => 0),
		array('id' => 3, 'name' => 'East', 'type' => 'text box', 'kind' => 'number', 'office' => 0),
	);
	pg_assert_same(null, pg_place_list_field_roles($plain), 'by name: no place fields');
	$picked = pg_place_list_field_roles($plain, array('address' => 'Where', 'lat' => 'North', 'lng' => 'East'));
	pg_assert_same(array(1), $picked['address']);
	pg_assert_same(array(2, 3), array($picked['lat'], $picked['lng']));
	pg_assert_same(null, pg_place_list_field_roles($plain, array('address' => 'Where', 'lat' => 'North')), 'still two coordinates and an address');
}

// A request naming a field gets nothing more: the answer reads the page id
// and the saved page, never another request value.
function test_place_list_request_names_no_field()
{
	$fn = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/place_list.php');
	$endpoint = (string) file_get_contents(PG_FUNCTIONS_DIR . '/places.php');

	pg_assert_same(0, preg_match('/\$_(GET|POST|REQUEST|COOKIE)\b/', $fn), 'the module reads no request value');
	pg_assert_contains("pg_place_list_for_page((int) (\$_GET['page'] ?? 0))", $endpoint, 'the endpoint passes the page id alone');
	pg_assert_contains('$tree = (string) pg_page_tree_json((int) $page_id);', $fn, 'the choice comes from the saved page');
	pg_assert_contains('pg_place_list_saved_choice(json_decode($tree, true))', $fn);
}

// The block's panel keeps the choice on the block, under the server's name,
// and offers no office-use-only field.
function test_place_list_panel_section()
{
	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');

	pg_assert_contains("_sdSetAttrOn(hit.node, '" . pg_place_list_choice_attr() . "'", $js, 'the panel writes the attribute the server reads');
	pg_assert_contains("var _SD_PLACE_MAP_PARTS = ['" . implode("', '", pg_place_list_parts()) . "'];", $js, 'the same parts');
	pg_assert_contains("String(f.office_use_only) === '1') return false;", $js, 'no office-use-only field is offered');
	pg_assert_contains("cfg.regionType !== 'form_list_view'", $js, 'the list places.php reads');
}

// An import or an API write keeps a number field's text as sent: a
// coordinate with a decimal comma is still the point it names.
function test_place_list_item_reads_a_decimal_comma()
{
	$roles = pg_place_list_field_roles(place_list_test_fields());
	$item = pg_place_list_item(array('id' => 9), array(2 => 'Moda', 3 => ' 40,98 ', 4 => '29,0215'), $roles, '');

	pg_assert_same(40.98, $item['lat']);
	pg_assert_same(29.0215, $item['lng']);

	$bad = pg_place_list_item(array('id' => 9), array(2 => 'Moda', 3 => '40,98,1', 4 => '29'), $roles, '');
	pg_assert_same(null, $bad['lat'], 'two commas name no number');
	pg_assert_same(null, $bad['lng'], 'no point without both');
	pg_assert_same(null, pg_place_list_item(array('id' => 9), array(2 => 'x', 3 => '-90,5', 4 => '1'), $roles, '')['lat'], 'still in range');
}

// places.php answers only for a page that holds the block: another public
// page with a public form list gives no addresses and phones its list may
// not show. A block inside a shared component the page names, or one inside
// that, counts.
function test_place_list_page_must_hold_the_block()
{
	$asked = array();
	$shared = array(
		5 => json_encode(array('type' => 'root', 'children' => array(array('type' => 'shared_ref', 'props' => array('sharedId' => 6))))),
		6 => json_encode(place_list_test_tree(null)),
		7 => json_encode(array('type' => 'root', 'children' => array(array('type' => 'shared_ref', 'props' => array('sharedId' => 7))))),
	);
	$load = function ($ids) use ($shared, &$asked) {
		$asked[] = $ids;
		return array_intersect_key($shared, array_flip($ids));
	};
	$ref = function ($sid) {
		return json_encode(array('type' => 'root', 'children' => array(array('type' => 'semantic', 'props' => array('tag' => 'div')), array('type' => 'shared_ref', 'props' => array('sharedId' => $sid)))));
	};

	pg_assert_true(pg_place_list_tree_holds_block(json_encode(place_list_test_tree(array('name' => 'Branch'))), $load), 'the block on the page');
	pg_assert_same(array(), $asked, 'no shared component read for a block on the page');
	pg_assert_false(pg_place_list_tree_holds_block(json_encode(array('type' => 'root', 'children' => array(array('type' => 'semantic', 'props' => array('tag' => 'div'))))), $load), 'a page without the block');
	pg_assert_false(pg_place_list_tree_holds_block('', $load), 'no tree');
	pg_assert_true(pg_place_list_tree_holds_block($ref(6), $load), 'in a shared component');
	pg_assert_true(pg_place_list_tree_holds_block($ref(5), $load), 'in a shared component inside another');
	pg_assert_false(pg_place_list_tree_holds_block($ref(7), $load), 'a component naming itself ends');
	pg_assert_false(pg_place_list_tree_holds_block($ref(99), $load), 'a missing component');

	$fn = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/place_list.php');
	$start = strpos($fn, 'function pg_place_list_for_page(');
	$body = substr($fn, $start, strpos($fn, "\n}\n", $start) - $start);
	pg_assert_true(strpos($body, 'pg_place_list_tree_holds_block(') !== false && strpos($body, 'pg_place_list_tree_holds_block(') < strpos($body, 'db_items(pg_place_list_records_sql('), 'the answer asks before any record is read');
	pg_assert_contains('Map of places block', pg_place_list_reason_text('block'));
}
