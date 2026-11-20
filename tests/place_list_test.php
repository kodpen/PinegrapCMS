<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the "Map of places" list (includes/fn/place_list.php,
 * places.php, assets/js/pg_map.js): which pages and records an answer may
 * carry, which fields (by the server's name rule, never the request's), the
 * cut at PG_PLACE_LIST_MAX and its note, the origin and rate checks, the
 * page's script, and the script's distance, order and search under Node.
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

// The request carries the page id only; same origin first, then the rate.
function test_place_list_endpoint_checks()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/places.php');
	$cross = strpos($source, 'pg_request_cross_site($_SERVER)');
	$rate = strpos($source, 'pg_place_list_throttled()');
	$read = strpos($source, "\$_GET['page']");

	pg_assert_true(($cross !== false) && ($rate !== false) && ($read !== false), 'the checks are there');
	pg_assert_true($cross < $rate && $rate < $read, 'origin, then rate, then the page id');
	pg_assert_same(1, preg_match_all('/\$_(GET|POST|REQUEST)\[/', $source), 'no other request value is read');
	pg_assert_same(30, PG_PLACE_LIST_RATE_LIMIT);
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
		. ' kadikoy: m.matches(places[2], "KADIKOY moda"), istanbul: m.matches({name:"İstanbul"}, "istanbul"), miss: m.matches(places[0], "izmir")}));';
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
	pg_assert_contains('pg_place_list_saved_choice(json_decode((string) pg_page_tree_json(', $fn, 'the choice comes from the saved page');
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
