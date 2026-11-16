<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The browser side of the clicks and scroll count: the cookie notice rule
 * of the anonymous counts in frontend.src.js against the server's
 * (pg_consent_statistics_not_refused_by()) on the same examples, the
 * script's naming and fold of an element (assets/js/pg_clicks.src.js)
 * against the server's on the server test's fixtures, and who gets the
 * script. The browser parts run in Node; skipped where Node cannot run.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/consent.php');

// Runs `$code` in Node after `$prelude`; its `out` comes back decoded. Null
// when Node cannot run.
function page_clicks_script_test_node($prelude, $code)
{
	if (!function_exists('exec')) {
		return null;
	}

	$out = array();
	$status = 1;
	@exec('node --version 2>&1', $out, $status);

	if ($status !== 0) {
		return null;
	}

	$file = @tempnam(sys_get_temp_dir(), 'pgclicks');

	if ($file === false) {
		return null;
	}

	file_put_contents($file, $prelude . "\nvar out = null;\n" . $code . "\nconsole.log(JSON.stringify(out));");
	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $status);
	@unlink($file);

	pg_assert_same(0, $status, 'the Node script runs: ' . implode("\n", array_slice($out, 0, 8)));

	return ($status === 0) ? json_decode(implode("\n", $out), true) : null;
}

// The pure rule of frontend.src.js as a function of Node.
function page_clicks_script_test_consent_rule()
{
	$source = str_replace("\r\n", "\n", (string) file_get_contents(PG_FUNCTIONS_DIR . '/frontend.src.js'));
	$start = strpos($source, 'statistics_not_refused_by: function(enabled, raw) {');
	$end = ($start === false) ? false : strpos($source, "\n    },", $start);

	pg_assert_true(($start !== false) && ($end !== false), 'the browser rule is there');

	return 'var rule = function(enabled, raw) {' . substr($source, $start + strlen('statistics_not_refused_by: function(enabled, raw) {'), $end - $start - strlen('statistics_not_refused_by: function(enabled, raw) {')) . "\n};";
}

// The cookie notice examples both sides are held to: notice off, a1, a0,
// not answered, a value that cannot be read.
function page_clicks_script_test_consent_cases()
{
	return array(
		array(false, '', true, 'notice off'),
		array(false, '1.a0.m0', true, 'notice off, whatever the cookie says'),
		array(true, '1.a1.m0', true, 'a1'),
		array(true, '1.a0.m1', false, 'a0 refused'),
		array(true, '1.a0', false, 'a0 alone'),
		array(true, '', true, 'not answered'),
		array(true, '1.m1', true, 'statistics not named yet'),
		array(true, 'x.y', true, 'a broken value'),
		array(true, '2.a0', true, 'an unknown version'),
		array(true, '1.a1.a0', false, 'the last a part wins'),
		array(true, '1.aa.a0', false, 'an unknown part is skipped'),
	);
}

function test_page_clicks_consent_rule_is_one_on_both_sides()
{
	$cases = page_clicks_script_test_consent_cases();

	foreach ($cases as $case) {
		pg_assert_same($case[2], pg_consent_statistics_not_refused_by($case[0], pg_consent_parse($case[1])), 'server: ' . $case[3]);
	}

	$out = page_clicks_script_test_node(page_clicks_script_test_consent_rule(), 'out = ' . json_encode($cases) . '.map(function (c) { return rule(c[0], c[1]); });');

	if ($out === null) {
		return;
	}

	foreach ($cases as $index => $case) {
		pg_assert_same($case[2], $out[$index], 'browser: ' . $case[3]);
	}

	// The served twin, hand-patched in frontend.min.js, on the same examples.
	$min = (string) file_get_contents(PG_FUNCTIONS_DIR . '/frontend.min.js');
	$at = strpos($min, 'statistics_not_refused_by:function(e,t){');
	$end = ($at === false) ? false : strpos($min, 'return!o}', $at);
	pg_assert_true(($at !== false) && ($end !== false), 'the served rule is there');
	$served = page_clicks_script_test_node('var rule = function(e,t){' . substr($min, $at + 40, $end + 9 - $at - 40) . ';', 'out = ' . json_encode($cases) . '.map(function (c) { return rule(c[0], c[1]); });');

	foreach ($cases as $index => $case) {
		pg_assert_same($case[2], $served[$index], 'served: ' . $case[3]);
	}

	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/frontend.src.js');
	pg_assert_true(strpos($source, 'statistics_allowed') === false, 'no "allowed" check left: the anonymous counts ask "not refused"');
	pg_assert_true(strpos((string) file_get_contents(PG_FUNCTIONS_DIR . '/frontend.min.js'), 'statistics_allowed') === false, 'nor in the served script');
}

// The literal fold examples of the server's own test (tests/page_clicks_test.php):
// list of array(kind, input, host, expected), kind url or label.
function page_clicks_script_test_fixtures()
{
	$source = (string) file_get_contents(dirname(__FILE__) . '/page_clicks_test.php');
	$string = '(\'(?:[^\'\\\\]|\\\\.)*\'|"(?:[^"\\\\]|\\\\.)*")';
	$fixtures = array();

	preg_match_all('/pg_assert_same\(' . $string . ', pg_page_clicks_fold_(url|label)\(' . $string . '(?:, ' . $string . ')?\)/', $source, $matches, PREG_SET_ORDER);

	$read = function ($literal) {
		$body = substr($literal, 1, -1);

		return ($literal[0] === '"') ? stripcslashes($body) : str_replace(array('\\\'', '\\\\'), array('\'', '\\'), $body);
	};

	foreach ($matches as $match) {
		$fixtures[] = array($match[2], $read($match[3]), isset($match[4]) && ($match[4] !== '') ? $read($match[4]) : '', $read($match[1]));
	}

	return $fixtures;
}

function test_page_clicks_script_folds_as_the_server()
{
	$fixtures = page_clicks_script_test_fixtures();

	pg_assert_true(count($fixtures) >= 15, 'the server test\'s examples were read: ' . count($fixtures));

	// More of the same kind the server test builds with str_repeat().
	$fixtures[] = array('url', '/' . str_repeat('a-', 200), '', pg_page_clicks_fold_url('/' . str_repeat('a-', 200)));
	$fixtures[] = array('label', str_repeat('Ü', 80), '', pg_page_clicks_fold_label(str_repeat('Ü', 80)));
	$fixtures[] = array('label', 'Order 12345678', '', '');
	$fixtures[] = array('label', 'Write to ayse@example.com', '', '');
	$fixtures[] = array('url', '//cdn.example.org:8080/a/b?c', 'www.example.com', '//cdn.example.org/a/b');

	foreach ($fixtures as $fixture) {
		$server = ($fixture[0] === 'url') ? pg_page_clicks_fold_url($fixture[1], $fixture[2]) : pg_page_clicks_fold_label($fixture[1]);
		pg_assert_same($fixture[3], $server, 'the server: ' . $fixture[1]);
	}

	$out = page_clicks_script_test_node('var window = {};' . "\n" . file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/pg_clicks.src.js'),
		'out = ' . json_encode($fixtures) . '.map(function (f) { return (f[0] === "url") ? window.pgClicks.foldUrl(f[1], f[2]) : window.pgClicks.foldLabel(f[1]); });');

	if ($out === null) {
		return;
	}

	foreach ($fixtures as $index => $fixture) {
		pg_assert_same($fixture[3], $out[$index], 'the script: ' . $fixture[0] . ' ' . $fixture[1]);
	}
}

// A fake page for the script: a link in a nav, a button in main, a link
// in the cookie notice; listeners kept; beacons caught.
function page_clicks_script_test_page($consent, $vitals = false)
{
	return 'var listeners = {}, beacons = [];
function on(prefix) { return function (type, fn) { (listeners[prefix + type] = listeners[prefix + type] || []).push(fn); }; }
function el(tag, attrs, parent, text) { return { tagName: tag, parentElement: parent || null, innerText: text || "", href: attrs.href ? (attrs.href.charAt(0) === "/" ? "https://www.example.com" + attrs.href : attrs.href) : undefined,
	getAttribute: function (n) { return Object.prototype.hasOwnProperty.call(attrs, n) ? attrs[n] : null; }, querySelector: function () { return null; } }; }
var nav = el("NAV", {}, null), main = el("DIV", { role: "main" }, null), cc = el("DIV", { id: "pg-cc" }, null);
var link = el("A", { href: "/shop?colour=red" }, nav, "  Shop \n now ");
var span = el("SPAN", {}, link, "Shop now");
var button = el("BUTTON", { type: "button" }, main, "Call 05551234567");
var buttonNamed = el("BUTTON", { type: "submit", "aria-label": "Send" }, main, " ");
var field = el("INPUT", { type: "text", value: "typed secret" }, main, "");
var ccLink = el("A", { href: "/privacy" }, cc, "Privacy");
var doc = { currentScript: { getAttribute: function (n) { return { "data-u": "/pinegrap/page_view.php", "data-p": "7", "data-s": "sig" }[n] || null; } },
	documentElement: { scrollHeight: 4000, scrollTop: 0, clientHeight: 800 }, body: { scrollHeight: 4000 }, visibilityState: "visible", addEventListener: on("d") };
var window = { document: doc, location: { host: "www.example.com" }, innerWidth: 1400, innerHeight: 800, pageYOffset: 0,
	navigator: { sendBeacon: function (u, d) { beacons.push({ u: u, f: d.fields }); return true; } },
	FormData: function () { this.fields = {}; this.append = function (k, v) { this.fields[k] = v; }; },
	setTimeout: function (f) { f(); }, addEventListener: on("w"),
	software: { statistics_not_refused: function () { return ' . json_encode($consent) . '; } } };
' . ($vitals ? 'window.pgVitalsSends = true;' : '') . '
' . file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/pg_clicks.src.js') . '
function click(t) { listeners.dclick[0]({ target: t }); }
function scrollTo(y) { window.pageYOffset = y; listeners.wscroll[0](); }
function hide() { doc.visibilityState = "hidden"; listeners.dvisibilitychange[0](); listeners.wpagehide[0](); }';
}

function test_page_clicks_script_names_and_sends_once()
{
	$out = page_clicks_script_test_node(page_clicks_script_test_page(true), '
		click(span); click(link); click(button); click(buttonNamed); click(field); click(ccLink);
		scrollTo(1600);
		hide();
		out = { beacons: beacons, named: window.pgClicks.name(span, "www.example.com"), none: window.pgClicks.name(field, "") };');

	if ($out === null) {
		return;
	}

	pg_assert_same(array('nav', 'link', '/shop', 'Shop now'), $out['named'], 'the link by its landmark, address folded, text folded');
	pg_assert_same(null, $out['none'], 'a text field is no link or button and its typed value is never read');
	pg_assert_same(1, count($out['beacons']), 'one request a page view, the hide and the pagehide together');

	$beacon = $out['beacons'][0];
	pg_assert_same('/pinegrap/page_view.php', $beacon['u']);
	pg_assert_same(array('7', 'sig'), array($beacon['f']['cp'], $beacon['f']['cs']), 'the page and its signature');

	$c = json_decode($beacon['f']['c'], true);
	pg_assert_same('desktop', $c['d']);
	pg_assert_same(6, $c['t'], 'the deepest tenth the window\'s bottom reached: (1600 + 800) / 4000');
	pg_assert_same(array(
		array('nav', 'link', '/shop', 'Shop now'),
		array('main', 'button', '', 'Send'),
	), $c['e'], 'once each; aria-label when the text is blank; a button whose label was only a long number has nothing left and is dropped; the cookie notice\'s link and the text field are left out');

	// The page parts by role: the server parse takes what the script sends.
	pg_assert_true(pg_page_clicks_parse($beacon['f']['c'], 'www.example.com') !== null, 'the server reads the beacon as sent');
}

function test_page_clicks_script_respects_a_refusal_and_counts_no_answer()
{
	// software.statistics_not_refused() answers for the cookie notice: a
	// refusal sends nothing; an answer of yes or none yet sends.
	$refused = page_clicks_script_test_node(page_clicks_script_test_page(false), 'click(link); hide(); out = beacons.length;');
	$unanswered = page_clicks_script_test_node(page_clicks_script_test_page(true), 'click(link); hide(); out = beacons.length;');
	$no_check = page_clicks_script_test_node(str_replace('software: {', 'nosoftware: {', page_clicks_script_test_page(true)), 'click(link); hide(); out = beacons.length;');
	$with_vitals = page_clicks_script_test_node(page_clicks_script_test_page(true, true), 'click(link); hide(); var taken = window.pgClicks.take(); out = { beacons: beacons.length, taken: taken };');

	if ($refused === null) {
		return;
	}

	pg_assert_same(0, $refused, 'nothing after a refusal of statistics');
	pg_assert_same(1, $unanswered, 'a visitor who has not answered is counted');
	pg_assert_same(0, $no_check, 'without the notice\'s check on the page nothing is sent');
	pg_assert_same(0, $with_vitals['beacons'], 'with the speed beacon on the page the script sends no request of its own');
	pg_assert_true(is_array($with_vitals['taken']) && ($with_vitals['taken']['cp'] === '7'), 'the speed beacon takes its fields');

	$vitals = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/pg_vitals.src.js');
	pg_assert_contains('window.pgVitalsSends = true;', $vitals, 'the speed beacon says it carries them');
	pg_assert_contains('window.pgClicks.take()', $vitals, 'and takes them into its one request');
}

function test_page_clicks_script_who_gets_it()
{
	require_once(PG_FUNCTIONS_DIR . '/includes/fn/page_clicks.php');

	$visitor = array('rendered_html_page' => true, 'public' => true, 'signed_in' => false, 'toolbar' => false, 'panel_preview' => false, 'background' => false, 'staff' => false);

	pg_assert_true(pg_page_clicks_beacon_wanted($visitor, true), 'a public page for a visitor, the switch on');
	pg_assert_false(pg_page_clicks_beacon_wanted($visitor, false), 'the switch off');

	foreach (array('public' => false, 'signed_in' => true, 'staff' => true, 'toolbar' => true, 'panel_preview' => true, 'background' => true, 'rendered_html_page' => false) as $key => $value) {
		pg_assert_false(pg_page_clicks_beacon_wanted(array_merge($visitor, array($key => $value)), true), $key . ' ' . var_export($value, true));
	}

	pg_assert_same('', pg_page_clicks_beacon_tag('', '/p', 7, 'sig'), 'no script address, no tag');
	pg_assert_contains('defer data-u="/pinegrap/page_view.php" data-p="7" data-s="s&lt;g"', pg_page_clicks_beacon_tag('/pinegrap/assets/js/pg_clicks.min.js', '/pinegrap/page_view.php', 7, 's<g'));

	$page = (string) file_get_contents(PG_FUNCTIONS_DIR . '/get_page.php');
	pg_assert_true(strpos($page, 'pg_clicks_beacon_inject(') > strpos($page, 'pg_vitals_beacon_inject('), 'beside the speed beacon');
	pg_assert_true(strpos($page, 'pg_clicks_beacon_inject(') < strpos($page, 'pg_experiment_click_script_for_served('), 'before the page cache');
	pg_assert_contains("'public' => (\$access_control_type == 'public'),", $page, 'a folder open to everyone');
	pg_assert_contains("'signed_in' => (bool) USER_LOGGED_IN,", $page, 'no signed-in member');

	pg_assert_contains("'assets/js/pg_clicks.min.js'               => array('assets/js/pg_clicks.src.js'),", (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/output.php'), 'the bundle');
}

function test_page_clicks_features_card_uses_the_one_reader_and_saver()
{
	$card = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/settings/features.php');
	$save = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/settings/features.save.php');

	pg_assert_contains('id="pgset-page-clicks"', $card, 'the card');
	pg_assert_contains("(pg_page_clicks_switch_on() ? ' checked=\"checked\"' : '')", $card, 'drawn from the one reader');
	pg_assert_contains('id="page_clicks_privacy"', $card, 'with the sentence for the privacy text');
	pg_assert_contains("pg_page_clicks_switch_save((bool) post_value('page_clicks_on'));", $save, 'saved through the one saver');
	pg_assert_contains("array_key_exists('page_clicks_card', \$_POST)", $save, 'only when its card was on the form');
	pg_assert_true(strpos($card . $save, "pg_config_text('page_clicks')") === false && strpos($card . $save, "pg_config_text_save('page_clicks'") === false, 'no second reader or saver');
}
