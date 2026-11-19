<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the "Listen" script (assets/js/pg_listen.js, run under Node on
 * small DOM-shaped fixtures) and its server door (includes/fn/listen.php):
 * the pieces read from an article and their languages, the cut of a
 * machine translation's "-x-" tail, a part's own language, nothing read
 * outside article or main, the button shown only with a voice for the
 * page's language, its controls, and the script added to a page carrying
 * data-pg-listen with its labels.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/listen.php');

// Runs $script with the script's exports as L and the fixture helpers, and
// answers its JSON; null without Node.
function _listen_node($script)
{
	if (!function_exists('exec')) {
		return null;
	}
	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);
	if ($code !== 0) {
		return null;
	}
	$file = @tempnam(sys_get_temp_dir(), 'pglisten');
	if ($file === false) {
		return null;
	}
	file_put_contents($file, 'var L = require(' . json_encode(PG_FUNCTIONS_DIR . '/assets/js/pg_listen.js') . ');' . "\n"
		. (string) file_get_contents(dirname(__FILE__) . '/fixtures/listen/dom.js') . "\n"
		. $script);
	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);
	return ($code === 0) ? json_decode(implode("\n", $out), true) : array('error' => implode("\n", $out));
}

function _listen_ran($r)
{
	if ($r === null) {
		return false;
	}
	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	return !isset($r['error']);
}

// -- what is read (L4, L5, L6, L7) -------------------------------------------

// An article with a menu, a form, a hidden part, a button, the block itself
// and a span in another language.
function test_listen_pieces_of_an_article()
{
	$r = _listen_node('
var art = el("article", {}, [
  el("nav", {}, [el("a", {}, ["Home"])]),
  el("h1", {}, ["The old library"]),
  el("div", { "data-pg-listen": "" }, [el("button", {}, ["Listen"])]),
  el("p", {}, ["We opened the shelves. ", el("strong", {}, ["Then"]), " we read. Was it ", el("span", { lang: "fr" }, ["déjà vu"]), "?"]),
  el("form", {}, [el("label", {}, ["Name"]), el("input", {})]),
  el("p", { hidden: "" }, ["Secret."]),
  el("p", { "aria-hidden": "true" }, ["Decor."]),
  el("button", {}, ["Share"]),
  el("aside", {}, ["Related"]),
  el("script", {}, ["var x = 1;"]),
  el("footer", {}, ["Footer"])
]);
console.log(JSON.stringify(L.pieces(art, "en-x-mtfrom-tr")));');
	if (!_listen_ran($r)) {
		return;
	}
	pg_assert_same(array(
		array('text' => 'The old library', 'lang' => 'en'),
		array('text' => 'We opened the shelves.', 'lang' => 'en'),
		array('text' => 'Then we read.', 'lang' => 'en'),
		array('text' => 'Was it', 'lang' => 'en'),
		array('text' => 'déjà vu', 'lang' => 'fr'),
		array('text' => '?', 'lang' => 'en'),
	), $r);
}

// A long sentence is cut at a space within 200 characters; a number's dot
// is no sentence end.
function test_listen_pieces_are_short()
{
	$r = _listen_node('
var long = new Array(60).join("word ") + "end.";
var art = el("article", {}, [el("p", {}, [long + " It cost 3.5 million. Done!"])]);
console.log(JSON.stringify(L.pieces(art, "en").map(function (p) { return p.text; })));');
	if (!_listen_ran($r)) {
		return;
	}
	foreach ($r as $text) {
		pg_assert_true(mb_strlen($text, 'UTF-8') <= 200, 'at most 200 characters: ' . $text);
	}
	pg_assert_same('It cost 3.5 million.', $r[count($r) - 2]);
	pg_assert_same('Done!', $r[count($r) - 1]);
	pg_assert_same(str_repeat('word ', 59) . 'end.', implode(' ', array_slice($r, 0, count($r) - 2)), 'nothing lost between the pieces');
}

function test_listen_language_cut()
{
	$r = _listen_node('console.log(JSON.stringify([L.langOf("en-x-mtfrom-tr"), L.langOf(" TR "), L.langOf("pt-BR"), L.langOf("de-X-mt"), L.langOf(""), L.langOf(null), L.langOf("x-klingon")]));');
	if (!_listen_ran($r)) {
		return;
	}
	pg_assert_same(array('en', 'tr', 'pt-BR', 'de', '', '', ''), $r);
}

// The nearest article around the block, else main, else nothing.
function test_listen_scope()
{
	$r = _listen_node('
var blockA = el("div", { "data-pg-listen": "" }, []);
var art = el("article", {}, [el("div", {}, [blockA]), el("p", {}, ["In the article."])]);
var main = el("main", {}, [art, el("p", {}, ["Beside the article."])]);
var blockB = el("div", { "data-pg-listen": "" }, []);
var main2 = el("main", {}, [el("section", {}, [blockB, el("p", {}, ["In main."])])]);
var blockC = el("div", { "data-pg-listen": "" }, []);
el("body", {}, [el("div", {}, [blockC, el("p", {}, ["Nowhere."])])]);
console.log(JSON.stringify({
  a: L.scopeFor(blockA, main) === art,
  b: L.scopeFor(blockB, main2) === main2,
  c: L.scopeFor(blockC, null),
  read: L.pieces(L.scopeFor(blockB, main2), "tr").map(function (p) { return p.text; })
}));');
	if (!_listen_ran($r)) {
		return;
	}
	pg_assert_true($r['a'], 'the article around the block');
	pg_assert_true($r['b'], 'else the page\'s main');
	pg_assert_same(null, $r['c'], 'else nothing');
	pg_assert_same(array('In main.'), $r['read']);
}

// -- when the button shows (L8) ------------------------------------------------

function test_listen_voice_for_the_language()
{
	$r = _listen_node('
var voices = [{ lang: "en-US", name: "A" }, { lang: "tr_TR", name: "B" }, { lang: "pt-BR", name: "C" }];
console.log(JSON.stringify({
  tr: L.hasVoice(voices, "tr"), de: L.hasVoice(voices, "de"), none: L.hasVoice([], "en"), empty: L.hasVoice(voices, ""),
  pt: L.voiceFor(voices, "pt-PT").name, en: L.voiceFor(voices, "en").name, br: L.voiceFor(voices.concat([{ lang: "pt-PT", name: "D" }]), "pt-BR").name
}));');
	if (!_listen_ran($r)) {
		return;
	}
	pg_assert_true($r['tr'], 'tr_TR serves tr');
	pg_assert_false($r['de'], 'no German voice');
	pg_assert_false($r['none'], 'no voice yet');
	pg_assert_false($r['empty'], 'no language: no voice');
	pg_assert_same('C', $r['pt'], 'the same language, another region');
	pg_assert_same('A', $r['en']);
	pg_assert_same('C', $r['br'], 'the exact tag first');
}

// The root stays hidden without speech, without a voice, without anything
// to read; it shows when the voices arrive late.
function test_listen_start_shows_only_with_a_voice()
{
	$r = _listen_node('
function page(lang, withArticle) {
  var root = listenBlock();
  var body = withArticle ? el("body", {}, [el("article", {}, [root, el("p", {}, ["Text."])])]) : el("body", {}, [root]);
  var doc = fakeDoc(el("html", { lang: lang }, [body]));
  return { root: root, doc: doc };
}
var out = {};
var a = page("tr", true); L.start({ }, a.doc); out.noSpeech = a.root.hidden;
var b = page("tr", true); var sb = fakeSynth([{ lang: "en-US" }]); L.start(fakeWin(sb), b.doc); out.noVoice = b.root.hidden;
var c = page("tr", true); var sc = fakeSynth([]); L.start(fakeWin(sc), c.doc); out.before = c.root.hidden;
sc.voices = [{ lang: "tr-TR" }]; sc.fire("voiceschanged"); out.late = c.root.hidden;
var d = page("tr", false); L.start(fakeWin(fakeSynth([{ lang: "tr-TR" }])), d.doc); out.nothingToRead = d.root.hidden;
var e = page("en-x-mtfrom-tr", true); L.start(fakeWin(fakeSynth([{ lang: "en-GB" }])), e.doc); out.translated = e.root.hidden;
console.log(JSON.stringify(out));');
	if (!_listen_ran($r)) {
		return;
	}
	pg_assert_true($r['noSpeech'], 'no speechSynthesis: hidden');
	pg_assert_true($r['noVoice'], 'no voice for the page\'s language: hidden');
	pg_assert_true($r['before'], 'voices not there yet: hidden');
	pg_assert_false($r['late'], 'shown once the voices arrive');
	pg_assert_true($r['nothingToRead'], 'no article or main: hidden');
	pg_assert_false($r['translated'], 'a machine translation is read in its language');
}

// -- the controls (L9, L10, L11) -----------------------------------------------

function test_listen_controls()
{
	$r = _listen_node('
var root = listenBlock();
var root2 = listenBlock();
var doc = fakeDoc(el("html", { lang: "tr" }, [el("body", {}, [el("article", {}, [root, el("p", {}, ["Bir. İki."])]), el("article", {}, [root2, el("p", {}, ["Üç."])])])]));
var synth = fakeSynth([{ lang: "tr-TR", name: "T" }]);
var win = fakeWin(synth); win.pgListenLabels = { "Listen": "Dinle", "Pause": "Duraklat", "Resume": "Sürdür", "Stop": "Durdur" };
L.start(win, doc);
var q = function (r, n) { return r.querySelector("[data-pg-listen-" + n + "]"); };
var t = function (r, n) { return q(r, n).querySelector("[data-pg-listen-text]").textContent; };
var snap = function (r) { return { play: q(r, "play").hidden, pause: q(r, "pause").hidden, stop: q(r, "stop").hidden, pressed: q(r, "pause").getAttribute("aria-pressed"), pauseText: t(r, "pause") }; };
var out = { labels: [t(root, "play"), t(root, "pause"), t(root, "stop")], idle: snap(root) };
q(root, "play").click();
out.speaking = snap(root); out.spoken = synth.spoken.map(function (u) { return [u.text, u.lang, u.voice && u.voice.name]; });
out.focus = doc.activeElement === q(root, "pause");
q(root, "pause").click(); out.paused = snap(root); out.synthPaused = synth.paused;
q(root, "pause").click(); out.resumed = snap(root); out.synthPausedAfter = synth.paused;
synth.finish(); out.second = synth.spoken.length;
q(root2, "play").click(); out.other = snap(root); out.otherSpeaks = snap(root2);
synth.finish(); out.end = snap(root2); out.endFocus = doc.activeElement === q(root2, "play");
q(root, "play").click(); q(root, "stop").click(); out.stopped = snap(root); out.cancelled = synth.cancels > 0;
q(root, "play").click(); win.fire("pagehide"); out.left = snap(root);
q(root, "play").click(); doc.visibilityState = "hidden"; doc.fire("visibilitychange"); out.hiddenPage = snap(root);
console.log(JSON.stringify(out));');
	if (!_listen_ran($r)) {
		return;
	}
	pg_assert_same(array('Dinle', 'Duraklat', 'Durdur'), $r['labels'], 'the labels in the page\'s language');
	pg_assert_same(array('play' => false, 'pause' => true, 'stop' => true, 'pressed' => 'false', 'pauseText' => 'Duraklat'), $r['idle']);
	pg_assert_same(array('play' => true, 'pause' => false, 'stop' => false, 'pressed' => 'false', 'pauseText' => 'Duraklat'), $r['speaking'], 'pause and stop while it speaks');
	pg_assert_same(array(array('Bir.', 'tr', 'T')), $r['spoken'], 'one piece at a time, in its language and voice');
	pg_assert_true($r['focus'], 'the focus moves to Pause');
	pg_assert_same(array('play' => true, 'pause' => false, 'stop' => false, 'pressed' => 'true', 'pauseText' => 'Sürdür'), $r['paused'], 'Resume, told as pressed');
	pg_assert_true($r['synthPaused']);
	pg_assert_same('false', $r['resumed']['pressed']);
	pg_assert_false($r['synthPausedAfter']);
	pg_assert_same(2, $r['second'], 'the next piece follows');
	pg_assert_true($r['other']['play'] === false && $r['other']['pause'] === true, 'one block speaks at a time');
	pg_assert_true($r['otherSpeaks']['play'], 'the other speaks');
	pg_assert_same(false, $r['end']['play'], 'the end puts the button back');
	pg_assert_true($r['endFocus'], 'the focus goes back to Listen');
	pg_assert_same(false, $r['stopped']['play'], 'Stop');
	pg_assert_true($r['cancelled']);
	pg_assert_same(false, $r['left']['play'], 'leaving the page stops it');
	pg_assert_same(false, $r['hiddenPage']['play'], 'hiding the page stops it');
}

// -- the server door (L2, L3, L11) ---------------------------------------------

function test_listen_inject()
{
	$src = '/pinegrap/assets/js/pg_listen.js?v=1';
	$html = '<html><body><article><div data-pg-listen hidden><button data-pg-listen-play></button></div></article></body></html>';
	$out = pg_listen_inject($html, $src);
	pg_assert_same(1, substr_count($out, 'pg_listen.js'));
	pg_assert_contains('window.pgListenLabels = ', $out);
	pg_assert_contains('"Listen":', $out);
	pg_assert_contains('<script src="' . $src . '" defer></script>' . "\n" . '</body></html>', $out, 'before the last </body>');
	pg_assert_same($out, pg_listen_inject($out, $src), 'a second call adds nothing');

	foreach (array('<p data-pg-listen-play>x</p>', '<p>data-pg-listen</p>', '<p class="data-pg-listen">x</p>') as $none) {
		$page = '<html><body>' . $none . '</body></html>';
		pg_assert_same($page, pg_listen_inject($page, $src), $none);
	}
	pg_assert_true(pg_listen_on_page('<div data-pg-listen="">'));
	pg_assert_true(pg_listen_on_page('<div class="x" DATA-PG-LISTEN>'));
	pg_assert_same(array('Listen', 'Pause', 'Resume', 'Stop'), array_keys(pg_listen_labels()));
}

// -- the Preview's labels (L11, L13) ------------------------------------------

// The editor's _sdPreviewListenLabels(), from its declaration to its end.
function _listen_preview_labels_js()
{
	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');
	$start = strpos($js, "\n    function _sdPreviewListenLabels(");
	$end = $start === false ? false : strpos($js, "\n    }\n", $start);
	pg_assert_true($start !== false && $end !== false, 'the editor holds _sdPreviewListenLabels()');
	return ($start === false || $end === false) ? '' : substr($js, $start, $end + 6 - $start);
}

// The Preview gives pg_listen.js the labels the published page gets, before
// the script: pg_listen_labels() in the page's language, printed on the
// editor's screen.
function test_listen_preview_labels()
{
	$labels = array('Listen' => 'Dinle', 'Pause' => 'Duraklat', 'Resume' => 'Devam ettir', 'Stop' => 'Durdur </script>');
	$r = _listen_node(_listen_preview_labels_js() . '
var L2 = ' . json_encode($labels) . ';
console.log(JSON.stringify({
  listen: _sdPreviewListenLabels("https://x.test/pinegrap/assets/js/pg_listen.js?v=5", L2),
  other: _sdPreviewListenLabels("https://x.test/pinegrap/assets/js/pg_map.js?v=5", L2),
  none: _sdPreviewListenLabels("https://x.test/pinegrap/assets/js/pg_listen.js?v=5", undefined)
}));');
	if (!_listen_ran($r)) {
		return;
	}
	$m = array();
	pg_assert_true((bool) preg_match('~^    <script>window\.pgListenLabels = (\{.*\});</script>\n$~s', $r['listen'], $m), 'one script before pg_listen.js: ' . $r['listen']);
	pg_assert_same($labels, json_decode(isset($m[1]) ? $m[1] : '', true), 'the labels as given');
	pg_assert_same(1, substr_count($r['listen'], '</script>'), 'a label cannot end the script');
	pg_assert_same('', $r['other'], 'only for the Listen script');
	pg_assert_same('', $r['none'], 'nothing without labels');

	// The same object the published page's door writes.
	$page = pg_listen_inject('<body><div data-pg-listen hidden></div></body>', '/x/pg_listen.js');
	pg_assert_true((bool) preg_match('~<script>window\.pgListenLabels = (\{.*?\});</script>~', $page, $m));
	pg_assert_same(pg_listen_labels(), json_decode($m[1], true));

	$core = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');
	pg_assert_contains("_pvJsInject += _sdPreviewListenLabels(src, window.sdListenLabels) + '    <script src=\"' + esc(src) + '\" defer><\\/script>\\n';", $core, 'the Preview writes them before the script');
	$screen = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/designer_screen.php');
	pg_assert_contains("require_once(PG_FUNCTIONS_DIR . '/includes/fn/listen.php');", $screen);
	pg_assert_contains("window.sdListenLabels = ' . encode_json(pg_listen_labels()) . ';", $screen, 'the editor gets pg_listen_labels()');
}

function test_listen_wiring()
{
	$page = (string) file_get_contents(PG_FUNCTIONS_DIR . '/get_page_content.php');
	pg_assert_contains("if (!\$email && stripos(\$content, 'data-pg-listen') !== false) {", $page, 'not in an e-mail');
	pg_assert_contains("require_once(PG_FUNCTIONS_DIR . '/includes/fn/listen.php');", $page);
	pg_assert_contains('$content = pg_listen_inject($content);', $page);

	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/pg_listen.js');
	foreach (array('XMLHttpRequest', 'fetch(', 'cookie', 'localStorage', 'sessionStorage') as $never) {
		pg_assert_false(strpos($js, $never) !== false, 'no ' . $never);
	}
}
