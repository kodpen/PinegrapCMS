<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The designer's "Clicks" layer (assets/js/style_designer_clicks.js, the
 * part 'clicks'): its fold against the server's and the visitors' script's
 * on the server test's fixtures, the canvas' links and buttons named as the
 * script names a click and matched to the counted rows, the elements the
 * canvas does not hold kept in the side list, the tenth lines, the device
 * choice, the empty state's button only for whoever may change Features,
 * visitors' labels drawn as text, and the kit names only. The part runs in
 * Node; skipped where Node cannot run.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(dirname(__FILE__) . '/page_clicks_script_test.php');
require_once(dirname(__FILE__) . '/designer_blocks_test.php');
require_once(dirname(__FILE__) . '/designer_parts_test.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/page_clicks.php');

function designer_clicks_part_test_source()
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer_clicks.js');
}

// The part's entry points under Node, handed a kit like the core's: _sdT
// fills {var:1}, esc escapes as the core's does, the design's settings come
// from $clicks. `api` is the part; `el()` builds a fake element.
function designer_clicks_part_test_prelude($clicks = array())
{
	return 'var window = { location: { host: "www.example.com", origin: "https://www.example.com" }, OUTPUT_PATH: "/" };
var factory = null; var StyleDesigner = { definePart: function (n, f) { factory = f; } };
' . designer_clicks_part_test_source() . '
var K = { _sdT: function (s, v) { return (v === undefined) ? s : String(s).replace("{var:1}", String(v)); },
	esc: function (s) { return String(s || "").replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;"); },
	sdToast: function () {}, _pgToken: function () { return "t"; } };
Object.defineProperties(K, { design: { get: function () { return { clicks: ' . json_encode((object) $clicks) . ' }; } },
	pages: { get: function () { return []; } }, activeKey: { get: function () { return ""; } }, canvasDoc: { get: function () { return null; } } });
var api = factory(K);
function el(tag, attrs, parent, text) { return { tagName: tag, parentElement: parent || null, innerText: text || "",
	href: attrs.href ? (attrs.href.charAt(0) === "/" ? "https://www.example.com" + attrs.href : attrs.href) : undefined,
	getAttribute: function (n) { return Object.prototype.hasOwnProperty.call(attrs, n) ? attrs[n] : null; }, querySelector: function () { return null; } }; }';
}

function test_designer_clicks_part_folds_as_the_server_and_the_script()
{
	$fixtures = page_clicks_script_test_fixtures();

	pg_assert_true(count($fixtures) >= 15, 'the server test\'s examples were read: ' . count($fixtures));

	$fixtures[] = array('label', 'Order 12345678', '', '');
	$fixtures[] = array('label', 'Write to ayse@example.com', '', '');
	$fixtures[] = array('url', '//cdn.example.org:8080/a/b?c', 'www.example.com', '//cdn.example.org/a/b');
	$fixtures[] = array('url', 'tel:+905551234567', '', 'tel:');

	foreach ($fixtures as $fixture) {
		$server = ($fixture[0] === 'url') ? pg_page_clicks_fold_url($fixture[1], $fixture[2]) : pg_page_clicks_fold_label($fixture[1]);
		pg_assert_same($fixture[3], $server, 'the server: ' . $fixture[1]);
	}

	$out = page_clicks_script_test_node(designer_clicks_part_test_prelude(),
		'out = ' . json_encode($fixtures) . '.map(function (f) { return (f[0] === "url") ? api.foldUrl(f[1], f[2]) : api.foldLabel(f[1]); });');

	if ($out === null) {
		return;
	}

	foreach ($fixtures as $index => $fixture) {
		pg_assert_same($fixture[3], $out[$index], 'the layer: ' . $fixture[0] . ' ' . $fixture[1]);
	}
}

// A page of the canvas: a link in a nav, the same link in the footer, a
// button in main, a link in the cookie notice, a link to an e-mail address.
function designer_clicks_part_test_canvas()
{
	return '
var nav = el("NAV", {}, null), foot = el("FOOTER", {}, null), main = el("DIV", { role: "main" }, null), cc = el("DIV", { id: "pg-cc" }, null);
var shop = el("A", { href: "/shop?colour=red" }, nav, "  Shop \n now ");
var shopFoot = el("A", { href: "/shop" }, foot, "Shop now");
var send = el("BUTTON", { type: "submit", "aria-label": "Send" }, main, " ");
var privacy = el("A", { href: "/privacy" }, cc, "Privacy");
var mail = el("A", { href: "mailto:info@example.com" }, foot, "Write to us");
var nodes = [shop, shopFoot, send, privacy, mail];';
}

function test_designer_clicks_part_names_the_canvas_as_the_script_and_matches_rows()
{
	// The rows as the server keeps them: the four parts folded by the server.
	$row = function ($area, $role, $url, $label, $share) {
		return array('area' => $area, 'role' => $role, 'url' => ($url === '') ? '' : pg_page_clicks_fold_url($url, 'www.example.com'),
			'label' => pg_page_clicks_fold_label($label), 'clicks' => 5, 'share' => $share);
	};
	$rows = array(
		$row('nav', 'link', 'https://www.example.com/shop?colour=blue', "Shop\tnow", 0.42),
		$row('main', 'button', '', 'Send', 0.1),
		$row('footer', 'link', 'mailto:info@example.com', 'Write to us', 0.05),
		$row('aside', 'link', 'https://www.example.com/sale', 'Sale <b>today</b>', 0.031),
	);

	$out = page_clicks_script_test_node(designer_clicks_part_test_prelude() . designer_clicks_part_test_canvas() . '
var script = (function () { var window = {}; ' . file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/pg_clicks.src.js') . '; return window.pgClicks; })();',
		'var rows = ' . json_encode($rows) . ';
		var m = api.match(nodes, rows, "www.example.com", "https://www.example.com/home");
		out = {
			twins: nodes.map(function (n) { return [api.name(n, "www.example.com", "https://www.example.com/home"), script.name(n, "www.example.com")]; }),
			found: m.found.map(function (p) { return [nodes.indexOf(p.el), p.row.label]; }),
			missing: m.missing.map(function (r) { return r.label; }),
			list: api.list(m.missing, 3)
		};');

	if ($out === null) {
		return;
	}

	foreach ($out['twins'] as $index => $pair) {
		pg_assert_same($pair[1], $pair[0], 'canvas element ' . $index . ' is named as the script names its click');
	}

	pg_assert_same(array(array(0, 'Shop now'), array(2, 'Send'), array(4, 'Write to us')), $out['found'],
		'the nav link by its folded address and label, the button by aria-label, the mail link by its kind; the footer\'s own shop link is another element and the cookie notice is left out');
	pg_assert_same(array('Sale <b>today</b>'), $out['missing'], 'a counted element the canvas does not hold is kept for the side list');
	pg_assert_contains('Sale &lt;b&gt;today&lt;/b&gt;', $out['list'], 'a label holding markup is drawn as text');
	pg_assert_true(strpos($out['list'], '<b>') === false, 'no visitor markup reaches the panel');
	pg_assert_contains('Side column · Link · /sale', $out['list'], 'the part and kind and address as the quiet line');
	pg_assert_contains('3.1%', $out['list'], 'its share');
	pg_assert_contains('Other elements: 3 clicks', $out['list'], 'the clicks past the bound as one line');
}

function test_designer_clicks_part_lines_and_device_choice()
{
	$out = page_clicks_script_test_node(designer_clicks_part_test_prelude(), '
		var reach = [{ tenth: 0, share: 1 }, { tenth: 1, share: 0.9 }, { tenth: 5, share: 0.5 }, { tenth: 6, share: null }, { tenth: 10, share: 0.125 }];
		out = {
			lines: api.lines(reach, 4000).map(function (l) { return [l.tenth, l.top, l.text]; }),
			all: api.ask(7, ""),
			mobile: api.ask(7, "mobile"),
			bad: api.ask(7, "phone<x>"),
			unsaved: api.ask(0, "tablet")
		};');

	if ($out === null) {
		return;
	}

	pg_assert_same(array(array(1, 399, '90%'), array(5, 1999, '50%'), array(10, 3999, '12.5%')), $out['lines'],
		'a line a tenth with its share: none for tenth 0 or a tenth without a share');
	pg_assert_same(array('page_id' => 7), $out['all'], 'all devices: no device asked');
	pg_assert_same(array('page_id' => 7, 'device' => 'mobile'), $out['mobile'], 'the device choice filters the rows');
	pg_assert_same(array('page_id' => 7), $out['bad'], 'an unknown device is all devices');
	pg_assert_same(null, $out['unsaved'], 'a page not saved yet has no figures');
}

function test_designer_clicks_part_empty_state_and_who_may_open()
{
	$code = 'var panel = { innerHTML: "", addEventListener: function () {}, removeEventListener: function () {} };
		var opened = api.open({ panel: panel });
		out = { opened: opened, panel: panel.innerHTML, may: api.empty(true), mayNot: api.empty(false) };';

	$off = page_clicks_script_test_node(designer_clicks_part_test_prelude(array('visitors' => true, 'features' => true, 'on' => false)), $code);
	$user = page_clicks_script_test_node(designer_clicks_part_test_prelude(array('visitors' => true, 'features' => false, 'on' => false)), $code);
	$closed = page_clicks_script_test_node(designer_clicks_part_test_prelude(array('visitors' => false, 'features' => true, 'on' => false)), $code);

	if ($off === null) {
		return;
	}

	pg_assert_true($off['opened'], 'opened for whoever may open Visitors');
	pg_assert_contains('how far down they scroll', $off['panel'], 'the first line: what would be kept');
	pg_assert_contains('nothing about a visitor is kept', $off['panel'], 'the second line: what never is');
	pg_assert_contains('data-sd-clicks-on', $off['panel'], 'the button for whoever may change Features');
	pg_assert_true(strpos($user['panel'], 'data-sd-clicks-on') === false, 'no button for whoever may not change Features');
	pg_assert_contains('A manager can turn it on in Site Settings, Features.', $user['panel'], 'told who can');
	pg_assert_contains('nothing about a visitor is kept', $user['panel'], 'the two lines all the same');
	pg_assert_false($closed['opened'], 'refused to whoever may not open Visitors');
	pg_assert_same('', $closed['panel'], 'nothing drawn for them');

	$source = designer_clicks_part_test_source();
	pg_assert_contains("fetch(s.turnOn, { method: 'POST'", $source, 'the button posts to the Visitors section\'s switch, which saves through the one saver');
	pg_assert_contains("K._pgApiPost('page_clicks', asked)", $source, 'the figures through the designer\'s one read (includes/panel/designer.php)');
	$code = preg_replace(array('~/\*.*?\*/~s', "~//[^\n]*~"), ' ', $source);
	pg_assert_true(strpos($code, 'page_clicks_switch') === false && strpos($code, 'pg_config_text') === false && strpos($code, 'sub_action') === false, 'no reader or saver of its own');
	pg_assert_contains('Every variant of an experiment on this page is counted together.', $source, 'variants are not told apart, and the reader is told so');
}

// The checks tests/designer_parts_test.php runs on every listed part, on this
// one before it is listed: registered by name, kit names only.
function test_designer_clicks_part_uses_only_the_kit()
{
	$kit = designer_parts_kit_keys();
	$core = designer_parts_core_names();
	$js = designer_clicks_part_test_source();

	pg_assert_contains("StyleDesigner.definePart('clicks', function (K) {", $js, 'the part registers as clicks');
	preg_match_all('/\bK\.([A-Za-z_$][A-Za-z0-9_$]*)/', $js, $used);

	foreach (array_unique($used[1]) as $key) {
		pg_assert_true(isset($kit[$key]), 'K.' . $key . ' is in the kit');
	}

	preg_match_all('/(?:function\s+|var\s+|let\s+|const\s+)([A-Za-z_$][A-Za-z0-9_$]*)/', $js, $own);
	$own = array_flip($own[1]);
	$bare = preg_replace(array("~//[^\n]*~", '~/\*.*?\*/~s', "~'(?:[^'\\\\\n]|\\\\.)*'~", '~"(?:[^"\\\\\n]|\\\\.)*"~'), ' ', $js);
	preg_match_all('/(?<![.\w$])([A-Za-z_$][A-Za-z0-9_$]*)(?!\s*:(?!:))/', $bare, $ids);

	foreach (array_unique($ids[1]) as $id) {
		if (isset($core[$id]) && !isset($own[$id])) {
			pg_assert_true(false, 'the part uses the core\'s ' . $id . ' outside the kit');
		}
	}

	pg_assert_true(strpos(implode("\n", pg_js_bundles()['assets/js/style_designer.min.js']), 'style_designer_clicks.js') === false, 'kept out of the core bundle');
}

// The empty state's "Turn on" after a post the switch did not take: the
// session ran out (a redirect to the sign-in page) or the token was refused
// (an error page answered 200). Both answers look like the switch's own
// redirect, so the figures' read says whether it is on.
function test_designer_clicks_part_turn_on_trusts_only_the_switch()
{
	// One page on the canvas, and one settings object, as sdDesign is.
	$prelude = 'var DESIGN = null;' . "\n" . str_replace(
		array('pages: { get: function () { return []; } }, activeKey: { get: function () { return ""; } }', 'design: { get: function () { return { clicks: '),
		array('pages: { get: function () { return [{ key: "p7", page_id: 7, page_name: "home" }]; } }, activeKey: { get: function () { return "p7"; } }', 'design: { get: function () { return DESIGN = DESIGN || { clicks: '),
		designer_clicks_part_test_prelude(array('visitors' => true, 'features' => true, 'on' => false, 'ready' => true)));
	// The answer comes after the promises: the harness' own print of `out`
	// is held back and this one prints it.
	$code = 'var toasts = [], asked = 0, print = console.log; console.log = function () {};
		K._pgApiPost = function () { asked++; return Promise.resolve({ status: "success", on: SWITCH, page: { views: 0, reach: [], elements: [], other: 0 } }); };
		var FormData = function () { this.append = function () {}; };
		var fetch = function () { return Promise.resolve({ ok: false, type: "opaqueredirect" }); };
		K.sdToast = function (m) { toasts.push(m); };
		var heard = {};
		var panel = { innerHTML: "", addEventListener: function (t, f) { heard[t] = f; }, removeEventListener: function () {} };
		api.open({ panel: panel });
		var button = { disabled: false };
		setTimeout(function () { heard.click({ target: { closest: function () { return button; } } }); }, 5);
		setTimeout(function () { print(JSON.stringify({ asked: asked, toasts: toasts, disabled: button.disabled, button: panel.innerHTML.indexOf("data-sd-clicks-on") >= 0, on: K.design.clicks.on })); process.exit(0); }, 50);';

	$refused = page_clicks_script_test_node($prelude, str_replace('SWITCH', 'false', $code));
	$taken = page_clicks_script_test_node($prelude, str_replace('SWITCH', 'true', $code));

	if ($refused === null) {
		return;
	}

	pg_assert_same(1, $refused['asked'], 'the figures are read after the post');
	pg_assert_same(1, count($refused['toasts']), 'a post the switch did not take is told as not turned on');
	pg_assert_false($refused['disabled'], 'the button can be pressed again');
	pg_assert_true($refused['button'], 'the empty state with its button is drawn again, not an empty period');
	pg_assert_same(0, count($taken['toasts']), 'no error when the switch is on');
	pg_assert_false($taken['button'], 'the figures replace the empty state');

	$panel = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/panel/designer.php');
	pg_assert_contains("'on'     => pg_page_clicks_switch_on(),", $panel, 'the read says whether the switch is on');
}
