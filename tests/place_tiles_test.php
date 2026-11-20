<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the map of the "Map of places" block
 * (includes/fn/place_tiles.php, assets/js/pg_map.js): the tile address and
 * what is stored of the card, the credit line, the page's map settings, the
 * map's name in the cookie notice, the line the Firewall settings offer,
 * the security headers that do not change with the switch, and, under Node,
 * that no tile or library is asked for before marketing is allowed.
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

require_once(PG_FUNCTIONS_DIR . '/includes/fn/place_tiles.php');
require_once(PG_FUNCTIONS_DIR . '/waf.php');

function test_place_tiles_url_valid()
{
	pg_assert_true(pg_place_tiles_url_valid(PG_PLACE_TILES_DEFAULT_URL), 'OpenStreetMap\'s own');
	pg_assert_true(pg_place_tiles_url_valid('https://{s}.tile.example.org/{z}/{x}/{y}.png?key={key}'), 'a subdomain letter and a key');
	pg_assert_false(pg_place_tiles_url_valid('http://tile.example.org/{z}/{x}/{y}.png'), 'plain http');
	pg_assert_false(pg_place_tiles_url_valid('https://tile.example.org/{z}/{x}.png'), 'without {y}');
	pg_assert_false(pg_place_tiles_url_valid('https://tile.example.org/{z}/{x}/{y}.png"onerror="x'), 'a quote');
	pg_assert_false(pg_place_tiles_url_valid('https:///{z}/{x}/{y}.png'), 'no host');
	pg_assert_false(pg_place_tiles_url_valid('javascript:alert(1)//{z}{x}{y}'));
	pg_assert_false(pg_place_tiles_url_valid('https://a.org/' . str_repeat('x', 500) . '{z}{x}{y}'), 'too long');
}

function test_place_tiles_host()
{
	pg_assert_same('tile.openstreetmap.org', pg_place_tiles_host(PG_PLACE_TILES_DEFAULT_URL));
	pg_assert_same('*.tile.example.org', pg_place_tiles_host('https://{s}.tile.example.org/{z}/{x}/{y}.png'), '{s} is a wildcard');
	pg_assert_same('maps.example.com', pg_place_tiles_host('https://Maps.Example.com:8443/{z}/{x}/{y}.png'), 'lower case, no port');
	pg_assert_same('', pg_place_tiles_host('https://{z}/{x}/{y}'));
}

function test_place_tiles_to_store()
{
	$default = pg_place_tiles_to_store(PG_PLACE_TILES_DEFAULT_URL, PG_PLACE_TILES_DEFAULT_CREDIT, '', false);
	pg_assert_same(array('url' => '', 'credit' => '', 'key' => 'keep', 'error' => ''), $default, 'the defaults are stored as nothing; an empty key box keeps the key');

	$own = pg_place_tiles_to_store(' https://t.example.org/{z}/{x}/{y}.png?k={key} ', "  Example   Maps\n", ' abc ', false);
	pg_assert_same('https://t.example.org/{z}/{x}/{y}.png?k={key}', $own['url']);
	pg_assert_same('Example Maps', $own['credit'], 'one line');
	pg_assert_same('abc', $own['key']);

	pg_assert_same('remove', pg_place_tiles_to_store('', '', 'abc', true)['key'], 'the tick wins');
	pg_assert_same('url', pg_place_tiles_to_store('http://t.example.org/{z}/{x}/{y}', '', '', false)['error'], 'a bad address is refused');
}

function test_place_tiles_address_and_credit()
{
	pg_assert_same('https://t.example.org/1/2/3.png?k=a%2Bb', str_replace(array('{z}', '{x}', '{y}'), array(1, 2, 3), pg_place_tiles_address('https://t.example.org/{z}/{x}/{y}.png?k={key}', 'a+b')));
	pg_assert_contains('href="https://www.openstreetmap.org/copyright"', pg_place_tiles_credit_html(PG_PLACE_TILES_DEFAULT_CREDIT), 'OpenStreetMap\'s credit links its copyright page');
	pg_assert_same('&lt;img src=x onerror=1&gt; Maps', pg_place_tiles_credit_html('<img src=x onerror=1> Maps'), 'a typed credit is text');
}

function test_place_tiles_page_config()
{
	pg_assert_same(array('on' => false), pg_place_tiles_page_config(false, true, array('url' => 'x', 'credit' => 'y'), '/lib'), 'off: nothing of the map reaches the page');

	$on = pg_place_tiles_page_config(true, true, array('url' => PG_PLACE_TILES_DEFAULT_URL, 'credit' => PG_PLACE_TILES_DEFAULT_CREDIT), '/p/assets/lib/leaflet');
	pg_assert_same(true, $on['consent'], 'with the notice on the map waits');
	pg_assert_same('/p/assets/lib/leaflet/leaflet.js', $on['script']);
	pg_assert_same(PG_PLACE_TILES_DEFAULT_URL, $on['tiles']);
	pg_assert_false(pg_place_tiles_page_config(true, false, array('url' => 'u', 'credit' => 'c'), '')['consent'], 'with the notice off it does not');
}

function test_place_tiles_consent_name()
{
	pg_assert_same('OpenStreetMap', pg_place_tiles_consent_name(true, true, PG_PLACE_TILES_DEFAULT_URL));
	pg_assert_same('t.example.org', pg_place_tiles_consent_name(true, true, 'https://t.example.org/{z}/{x}/{y}.png'));
	pg_assert_same('', pg_place_tiles_consent_name(true, false, PG_PLACE_TILES_DEFAULT_URL), 'switch off: no row');
	pg_assert_same('', pg_place_tiles_consent_name(false, true, PG_PLACE_TILES_DEFAULT_URL), 'no block on the page: no row');

	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/consent.php');
	pg_assert_contains("\$catalogue['marketing'][] = array(\$map_name, false,", $source, 'the map is a marketing service of the notice');
}

function test_place_tiles_csp_allows()
{
	$host = 'tile.openstreetmap.org';

	pg_assert_true(pg_place_tiles_csp_allows("default-src 'self'; img-src 'self' data: https:", $host), 'https: covers it');
	pg_assert_true(pg_place_tiles_csp_allows("img-src 'self' https://tile.openstreetmap.org", $host), 'the host itself');
	pg_assert_true(pg_place_tiles_csp_allows("img-src *.openstreetmap.org", $host), 'a wildcard over it');
	pg_assert_true(pg_place_tiles_csp_allows("img-src https://*.example.org", '*.example.org'), 'a {s} host under the same wildcard');
	pg_assert_true(pg_place_tiles_csp_allows("script-src 'self'", $host), 'neither img-src nor default-src: nothing refused');
	pg_assert_false(pg_place_tiles_csp_allows("default-src 'self'", $host), 'default-src stands for img-src');
	pg_assert_false(pg_place_tiles_csp_allows("default-src https:; img-src 'self' data:", $host), 'img-src wins over default-src');
	pg_assert_false(pg_place_tiles_csp_allows("img-src *.openstreetmap.org.evil.com", $host));
	pg_assert_same('img-src https://tile.openstreetmap.org', pg_place_tiles_csp_text($host));
	pg_assert_same('', pg_place_tiles_firewall_note(''), 'the built-in policy needs no line');
}

// The headers the software sends do not read the switch, and the built-in
// policy already lets OpenStreetMap's tiles through: the same headers with
// "Maps" on and off.
function test_place_tiles_headers_same_with_the_switch_on_and_off()
{
	$waf = (string) file_get_contents(PG_FUNCTIONS_DIR . '/waf.php');
	$init = (string) file_get_contents(PG_FUNCTIONS_DIR . '/init.php');

	foreach (array('place_tiles', 'PG_PLACE_TILES', 'place_map_on') as $word) {
		pg_assert_same(false, stripos($waf, $word), 'waf.php does not read ' . $word);
		pg_assert_same(false, stripos($init, $word), 'init.php does not read ' . $word);
	}

	pg_assert_same(0, preg_match('/\bheader\s*\(/', (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/place_tiles.php')), 'the module sends no header');
	pg_assert_true(pg_place_tiles_csp_allows(waf_csp_policy(), pg_place_tiles_host(PG_PLACE_TILES_DEFAULT_URL)), 'the built-in policy lets the default tiles through');
}

// No library and no tile before marketing is allowed: the gate and the one
// way the map is mounted, run under Node.
function test_place_tiles_no_tile_before_consent_under_node()
{
	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);

	if ($code !== 0) {
		pg_assert_true(true, 'Node is not installed: the run is skipped');
		return;
	}

	$script = 'var m = require(' . json_encode(PG_FUNCTIONS_DIR . '/assets/js/pg_map.js') . ');'
		. 'function doc() { var l = []; return { addEventListener: function (n, f) { if (n === "pg:consent") l.push(f); }, removeEventListener: function (n, f) { l = l.filter(function (g) { return g !== f; }); }, fire: function (d) { l.slice().forEach(function (f) { f({ detail: d }); }); }, count: function () { return l.length; } }; }'
		. 'var map = { on: true, consent: true, tiles: "https://tile.openstreetmap.org/{z}/{x}/{y}.png" };'
		. 'var r = {};'
		. 'var d = doc(), n = 0; r.wait = m.whenAllowed(d, map, "pg_consent=1.a1.m0", function () { n++; }); r.before = n;'
		. 'd.fire({ analytics: true, marketing: false }); r.refused = n;'
		. 'd.fire({ analytics: false, marketing: true }); r.allowed = n; d.fire({ marketing: true }); r.once = n; r.left = d.count();'
		. 'var d2 = doc(), n2 = 0; r.stored = m.whenAllowed(d2, map, "x=1; pg_consent=1.a0.m1", function () { n2++; }); r.storedMounted = n2;'
		. 'var n3 = 0; r.noticeOff = m.whenAllowed(doc(), { on: true, consent: false, tiles: "t" }, "", function () { n3++; }); r.noticeOffMounted = n3;'
		. 'var d4 = doc(), n4 = 0; r.off = m.whenAllowed(d4, { on: false }, "pg_consent=1.m1", function () { n4++; }); d4.fire({ marketing: true }); r.offMounted = n4;'
		. 'r.noAnswer = m.gate(map, ""); r.badCookie = m.marketingAllowed("pg_consent=2.m1"); r.other = m.marketingAllowed("my_pg_consent=1.m1");'
		. 'console.log(JSON.stringify(r));';
	$file = tempnam(sys_get_temp_dir(), 'pgtiles');
	file_put_contents($file, $script);
	$lines = array();
	exec('node ' . escapeshellarg($file) . ' 2>&1', $lines, $code);
	unlink($file);
	$r = json_decode(implode("\n", $lines), true);

	pg_assert_true(is_array($r), 'the script runs under Node: ' . implode("\n", $lines));
	pg_assert_same('wait', $r['wait'], 'marketing refused in the stored answer: the map waits');
	pg_assert_same(0, $r['before'], 'nothing is mounted before the answer');
	pg_assert_same(0, $r['refused'], 'nor on an answer that refuses marketing');
	pg_assert_same(1, $r['allowed'], 'mounted on the answer that allows it, without a reload');
	pg_assert_same(1, $r['once'], 'once');
	pg_assert_same(0, $r['left'], 'the listener leaves');
	pg_assert_same('load', $r['stored'], 'allowed earlier: at once');
	pg_assert_same(1, $r['storedMounted']);
	pg_assert_same('load', $r['noticeOff'], 'the notice off: at once');
	pg_assert_same(1, $r['noticeOffMounted']);
	pg_assert_same('off', $r['off'], 'the switch off: never');
	pg_assert_same(0, $r['offMounted']);
	pg_assert_same('wait', $r['noAnswer'], 'no answer yet: waits');
	pg_assert_false($r['badCookie']);
	pg_assert_false($r['other'], 'another cookie\'s name is not read');

	// The library and the tiles are asked for only inside mountMap(), which
	// only whenAllowed() calls.
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/pg_map.js');
	pg_assert_same(1, substr_count($source, 'loadLibrary(map, function'), 'the library is loaded in one place');
	pg_assert_same(1, substr_count($source, 'L.tileLayer('), 'the tiles are asked for in one place');
	pg_assert_same(2, substr_count($source, 'mountMap('), 'mountMap() is defined once and called once');
	pg_assert_contains("whenAllowed(root.document, map, root.document.cookie, function () {\n                mountMap(view, map);", $source, 'and that call waits for the gate');
}
