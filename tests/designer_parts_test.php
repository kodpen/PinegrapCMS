<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the visual designer's parts on demand: a part not needed to open
 * a page is a bundle of its own (pg_designer_parts(), includes/fn/output.php),
 * kept out of style_designer.min.js and handed to the editor as
 * sdDesign.parts; the editor's loader (_sdPartLoad(), cut out of
 * assets/js/style_designer.src.js and run under Node) runs a part's files in
 * order, once. On a machine without Node the loader tests are skipped.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once __DIR__ . '/designer_blocks_test.php';

// Every part is a bundle of pg_js_bundles() named style_designer_<part>.min.js
// whose sources are not in the editor's own bundle: a part also bundled with
// the core would run twice.
function test_designer_parts_are_bundles_apart_from_the_core()
{
	$parts = pg_designer_parts();
	$bundles = pg_js_bundles();
	pg_assert_true(isset($parts['ai']), 'the assistant is a part');
	$core = array_flip($bundles['assets/js/style_designer.min.js']);

	foreach ($parts as $name => $bundle) {
		pg_assert_same('assets/js/style_designer_' . $name . '.min.js', $bundle, $name . ' is named after the part');
		pg_assert_true(isset($bundles[$bundle]), $bundle . ' is a bundle');

		foreach ($bundles[$bundle] as $source) {
			pg_assert_false(isset($core[$source]), $source . ' is not in style_designer.min.js');
		}
	}

	pg_assert_same(array('assets/js/designer_ai.js'), $bundles['assets/js/style_designer_ai.min.js'], 'the assistant part is its panel');
}

// The addresses handed to the editor: only the parts asked for, each the
// files pg_js_bundle_urls() gives for its bundle.
function test_designer_part_urls_list_the_parts_asked_for()
{
	pg_assert_same(array(), pg_designer_part_urls(array()), 'no part asked for');
	pg_assert_same(array(), pg_designer_part_urls(array('nope')), 'an unknown part is left out');
	$urls = pg_designer_part_urls(array('ai'));
	pg_assert_same(array('ai'), array_keys($urls), 'the assistant part');
	pg_assert_same(pg_js_bundle_urls('assets/js/style_designer_ai.min.js'), $urls['ai'], 'its bundle\'s addresses');
}

// The designer screen no longer prints the assistant's script: the editor
// loads it as a part.
function test_designer_screen_hands_parts_to_the_editor()
{
	$screen = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/designer_screen.php');
	pg_assert_false(strpos($screen, 'src="assets/js/designer_ai.js') !== false, 'no <script> tag for designer_ai.js');
	pg_assert_true(strpos($screen, "'parts'") !== false && strpos($screen, 'pg_designer_part_urls(') !== false, 'sdDesign.parts is filled');
}

// Runs $script after the loader and returns the JSON lines it prints; null
// without Node. `head` collects the script elements the loader appends;
// `finish(i, ok)` fires the load or error event of the i-th one.
function designer_parts_run($script)
{
	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);

	if ($code !== 0) {
		return null;
	}

	$source = _test_blocks_js();
	$from = strpos($source, '    // ── Parts on demand');
	$to = strpos($source, '    // ── end of parts on demand', (int) $from);
	pg_assert_true(($from !== false) && ($to !== false), 'the designer has the parts loader');

	$js = 'var appended = [];'
		. 'var document = { head: { appendChild: function (s) { s.parentNode = this; appended.push(s); }, removeChild: function (s) { s.parentNode = null; } },'
		. ' createElement: function (tag) { return { tag: tag, src: "", async: true, parentNode: null }; } };'
		. 'var _design = { parts: { ai: ["a.js", "b.js"], empty: [] } };'
		. 'function finish(i, ok) { var s = appended[i]; if (ok) s.onload(); else s.onerror(); }'
		. 'function flush() { return new Promise(function (r) { setTimeout(r, 0); }); }'
		. 'function out(v) { console.log(JSON.stringify(v)); }'
		. "\n" . substr($source, $from, $to - $from) . "\n" . $script;

	$file = tempnam(sys_get_temp_dir(), 'sdparts') . '.js';
	file_put_contents($file, $js);
	$lines = array();
	exec('node ' . escapeshellarg($file) . ' 2>&1', $lines, $code);
	@unlink($file);
	pg_assert_same(0, $code, 'the loader script runs: ' . implode("\n", $lines));

	return array_map(function ($line) { return json_decode($line, true); }, $lines);
}

// The files run one after the other, in order and not async; the promise
// settles when the last has run; asked again, the same promise and no new
// script.
function test_designer_part_loader_runs_files_in_order_once()
{
	$r = designer_parts_run(
		'var settled = "";'
		. 'var p = _sdPartLoad("ai"); p.then(function () { settled = "ok"; });'
		. 'var again = _sdPartLoad("ai") === p;'
		. 'flush().then(function () { out([appended.length, again, _sdPartLoaded("ai")]); finish(0, true); return flush(); })'
		. '.then(function () { out([appended.map(function (s) { return s.src; }), appended[1].async, settled]); finish(1, true); return flush(); })'
		. '.then(function () { out([settled, _sdPartLoaded("ai"), appended.length]); _sdPartLoad("ai"); return flush(); })'
		. '.then(function () { out(appended.length); });'
	);

	if ($r === null) {
		return;
	}

	pg_assert_same(array(1, true, false), $r[0], 'one script at a time, the same promise, not loaded yet');
	pg_assert_same(array(array('a.js', 'b.js'), false, ''), $r[1], 'the second file after the first, in order');
	pg_assert_same(array('ok', true, 2), $r[2], 'settled once both ran');
	pg_assert_same(2, $r[3], 'a loaded part is not fetched again');
}

// A file that fails rejects the promise and takes its element away; the next
// ask tries again. A part the screen did not hand over rejects at once.
function test_designer_part_loader_retries_after_a_failure()
{
	$r = designer_parts_run(
		'_sdPartLoad("ai").then(function () { out("ok"); }, function () { out(["failed", appended[0].parentNode === null, _sdPartLoaded("ai")]); });'
		. 'flush().then(function () { finish(0, false); return flush(); })'
		. '.then(function () { _sdPartLoad("ai"); return flush(); })'
		. '.then(function () { out(appended.length); return Promise.all([_sdPartLoad("nope").catch(function () { return "unknown"; }), _sdPartLoad("empty").catch(function () { return "empty"; })]); })'
		. '.then(function (v) { out(v); });'
	);

	if ($r === null) {
		return;
	}

	pg_assert_same(array('failed', true, false), $r[0], 'rejected, the element taken away');
	pg_assert_same(2, $r[1], 'the next ask appends the file again');
	pg_assert_same(array('unknown', 'empty'), $r[2], 'a part not handed over is refused');
}

// The core's kit for the parts cut out of it: the keys of the object
// _sdPartKit() builds, functions and state alike.
function designer_parts_kit_keys()
{
	$source = _test_blocks_js();
	$from = strpos($source, '    function _sdPartKit() {');
	$to = strpos($source, "\n    }\n", (int) $from);
	pg_assert_true(($from !== false) && ($to !== false), 'the core builds a kit for its parts');
	$body = substr($source, $from, $to - $from);
	preg_match_all('/^\s{12}([A-Za-z_$][A-Za-z0-9_$]*):/m', $body, $m);
	return array_flip($m[1]);
}

// The core's top-level names (four-space declarations inside its closure).
function designer_parts_core_names()
{
	preg_match_all('/^    (?:function\s+|var\s+|let\s+|const\s+)([A-Za-z_$][A-Za-z0-9_$]*)/m', _test_blocks_js(), $m);
	return array_flip($m[1]);
}

// A part cut out of the core registers with StyleDesigner.definePart(), takes
// every editor name it uses from the kit (K.<name>, a key the kit has) and
// uses no name of the core's closure directly: there it would be undefined.
function test_designer_parts_cut_from_the_core_use_only_the_kit()
{
	$kit = designer_parts_kit_keys();
	$core = designer_parts_core_names();
	$cut = 0;

	foreach (pg_designer_parts() as $name => $bundle) {
		foreach (pg_js_bundles()[$bundle] as $source) {
			if (!preg_match('#^assets/js/style_designer_[a-z0-9_]+\.js$#', $source)) {
				continue;
			}

			$cut++;
			$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $source);
			pg_assert_true(strpos($js, "StyleDesigner.definePart('" . $name . "', function (K) {") !== false, $source . ' registers part ' . $name);
			preg_match_all('/\bK\.([A-Za-z_$][A-Za-z0-9_$]*)/', $js, $used);

			foreach (array_unique($used[1]) as $key) {
				pg_assert_true(isset($kit[$key]), $source . ': K.' . $key . ' is in the kit');
			}

			// Names the part declares itself (its aliases and functions).
			preg_match_all('/(?:function\s+|var\s+|let\s+|const\s+)([A-Za-z_$][A-Za-z0-9_$]*)/', $js, $own);
			$own = array_flip($own[1]);
			$bare = preg_replace(array("~//[^\n]*~", '~/\*.*?\*/~s', "~'(?:[^'\\\\\n]|\\\\.)*'~", '~"(?:[^"\\\\\n]|\\\\.)*"~'), ' ', $js);
			preg_match_all('/(?<![.\w$])([A-Za-z_$][A-Za-z0-9_$]*)(?!\s*:(?!:))/', $bare, $ids);

			foreach (array_unique($ids[1]) as $id) {
				if (isset($core[$id]) && !isset($own[$id])) {
					pg_assert_true(false, $source . ' uses the core\'s ' . $id . ' outside the kit');
				}
			}
		}
	}

	pg_assert_true($cut >= 1, 'at least one part is cut out of the core');
}

// The import (HTML / ZIP and the paste-a-page reply) is a part: its code left
// the core, which keeps only the two entry points that run it.
function test_designer_import_is_a_part()
{
	$parts = pg_designer_parts();
	pg_assert_same('assets/js/style_designer_import.min.js', $parts['import'] ?? '', 'the import is a part');
	pg_assert_same(array('assets/js/style_designer_import.js'), pg_js_bundles()['assets/js/style_designer_import.min.js'] ?? array(), 'built from its file');
	$core = _test_blocks_js();
	pg_assert_false(strpos($core, 'function _pgImportFindShared(') !== false, 'the core no longer holds the import');
	pg_assert_true(strpos($core, "_sdPartCall('import', 'open'") !== false, 'the import dialog runs the part');
	pg_assert_true(strpos($core, "_sdPartCall('import', 'apply'") !== false, 'the paste-a-page reply runs the part');
	$screen = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/designer_screen.php');
	pg_assert_true(strpos($screen, "array('import')") !== false || strpos($screen, "'import'") !== false, 'the screen hands the import part over');
}

// The readers of the designer's _sdT() keys read the parts too.
function test_designer_readers_see_the_parts()
{
	$all = pg_designer_js_all_sources();
	pg_assert_same(pg_designer_js_sources(), array_slice($all, 0, count(pg_designer_js_sources())), 'the bundled sources first');
	pg_assert_true(in_array('assets/js/style_designer_import.js', $all, true), 'the import part is read');
	pg_assert_true(in_array('Make the repeated parts shared?', pg_designer_i18n_keys(), true), 'a key only the import part uses is in the editor\'s map');
}

// The import part under Node, handed a kit like the core's: a one-page reply
// drops the blank placeholder tab through the kit's setter, opens the page as
// a new tab after the open one and counts the tab sequence on; a reply of two
// pages with the same header asks whether to share it.
function test_designer_import_part_runs_on_the_kit()
{
	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);

	if ($code !== 0) {
		return;
	}

	$js = 'process.on("unhandledRejection", function () {});'
		. 'var factory = null; var StyleDesigner = { definePart: function (n, f) { factory = f; } };'
		. 'var window = {}; var asked = 0;'
		. 'var document = { getElementById: function () { return null; }, createElement: function () { asked++; throw new Error("asked"); } };'
		. 'var pgUI = { dialog: { hide: function () {}, show: function () {} } };'
		. 'var state = { pages: [{ key: "p0", page_id: 0, page_name: "", tree: { children: [] } }, { key: "p1", page_id: 5, page_name: "Home", tree: { children: [{}] } }], active: "p1", seq: 0 };'
		. 'var inserted = [], switched = null, toasts = 0;'
		. 'var K = { _sdT: function (s) { return s; }, esc: function (s) { return String(s); }, sdToast: function () { toasts++; }, gid: function () { return "g"; },'
		. ' getNodeLabel: function () { return "x"; }, createDefaultTree: function () { return { children: [] }; }, reindex: function () { return 1; },'
		. ' _backfillIds: function () {}, _normalizeToggleableShowState: function () {}, _pgNewPageDraft: function () { return 1; },'
		. ' _pgTabsInsertAfter: function (p, after) { inserted.push([p.page_name, after]); }, _pgTabsPersistActive: function () {}, _pgTabsRender: function () {},'
		. ' _pgTabsSwitch: function (k) { switched = k; }, _pgTemplateTrackRows: function () {}, _pgToken: function () { return "t"; },'
		. ' _pgTreeOf: function (p) { return p.tree; }, _refreshSharedPalette: function () {}, _sdSmartActiveHost: function () { return false; }, _sdUsesBootstrap: function () { return true; } };'
		. 'Object.defineProperties(K, { pages: { get: function () { return state.pages; }, set: function (v) { state.pages = v; } }, activeKey: { get: function () { return state.active; } },'
		. ' newTabSeq: { get: function () { return state.seq; }, set: function (v) { state.seq = v; } }, sharedCache: { get: function () { return {}; } }, design: { get: function () { return null; } } });'
		. "\n" . (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer_import.js') . "\n"
		. 'var api = factory(K);'
		. 'api.apply({ pages: [{ page_name: "About", tree: { children: [] } }] }, "Site");'
		. 'console.log(JSON.stringify([typeof api.open, state.pages.map(function (p) { return p.key; }), inserted, state.seq, switched === null ? null : switched.indexOf("new") === 0, toasts]));'
		. 'var band = function () { return { type: "semantic", props: { tag: "header" }, children: [{ type: "content", props: {}, children: [] }, { type: "content", props: {}, children: [] }, { type: "content", props: {}, children: [] }] }; };'
		. 'try { api.apply({ pages: [{ tree: { children: [band()] } }, { tree: { children: [band()] } }] }, ""); } catch (e) {}'
		. 'console.log(JSON.stringify(asked));';

	$file = tempnam(sys_get_temp_dir(), 'sdimport') . '.js';
	file_put_contents($file, $js);
	$lines = array();
	exec('node ' . escapeshellarg($file) . ' 2>&1', $lines, $code);
	@unlink($file);
	pg_assert_same(0, $code, 'the import part runs: ' . implode("\n", $lines));
	pg_assert_same(array('function', array('p1'), array(array('About', 'p1')), 1, true, 1), json_decode($lines[0], true), 'the page opened as a tab after the open one, the blank tab dropped');
	pg_assert_same(1, json_decode($lines[1], true), 'a header on both pages is offered as shared');
}

// The code view's runtime is a part: switching the view on, the document and
// the selection both ways, the file tabs and the search in all tabs run when
// the view is first used. The core keeps what a page needs at open (the
// switch, the tab strip, the completion sources the code dialog uses too,
// the bound props of the output) and stubs for the entry points.
function test_designer_code_view_runtime_is_a_part()
{
	$parts = pg_designer_parts();
	pg_assert_same('assets/js/style_designer_code.min.js', $parts['code'] ?? '', 'the code view is a part');
	pg_assert_same(array('assets/js/style_designer_code.js'), pg_js_bundles()['assets/js/style_designer_code.min.js'] ?? array(), 'built from its file');
	$core = _test_blocks_js();

	foreach (array('_sdCodeDocument', '_sdCodeOnSelection', '_sdCodeTabsFlush', '_sdCodeFindRun') as $moved) {
		pg_assert_false(strpos($core, 'function ' . $moved . '(') !== false, $moved . ' left the core');
	}

	foreach (array('_sdCodeInit', '_sdCodePhrases', '_sdCodeBoundProps', '_sdCodeClassCompletion', '_sdCodeTabsRender') as $kept) {
		pg_assert_true(strpos($core, 'function ' . $kept . '(') !== false, $kept . ' stays in the core');
	}

	foreach (array('toggle', 'tabOpen', 'layoutOpen', 'dregionOpen') as $entry) {
		pg_assert_true(strpos($core, "_sdPartCall('code', '" . $entry . "'") !== false, 'the core runs the part for ' . $entry);
	}

	$part = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer_code.js');
	pg_assert_true(strpos($part, 'function _sdCodeDocument(') !== false, 'the part holds the document');
	$screen = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/designer_screen.php');
	pg_assert_true(strpos($screen, "'code'") !== false, 'the screen hands the code part over');
}

// The code part's factory runs on a kit with every key the core's has and
// hands back the entry points the core's stubs call.
function test_designer_code_part_hands_back_its_entry_points()
{
	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);

	if ($code !== 0) {
		return;
	}

	$keys = array_keys(designer_parts_kit_keys());
	$js = 'var factory = null; var StyleDesigner = { definePart: function (n, f) { if (n === "code") factory = f; } };'
		. 'var K = {}; ' . json_encode($keys) . '.forEach(function (k) { K[k] = function () {}; });'
		. "\n" . (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer_code.js') . "\n"
		. 'var api = factory(K); console.log(JSON.stringify(Object.keys(api).filter(function (k) { return typeof api[k] === "function"; }).sort()));';
	$file = tempnam(sys_get_temp_dir(), 'sdcode') . '.js';
	file_put_contents($file, $js);
	$lines = array();
	exec('node ' . escapeshellarg($file) . ' 2>&1', $lines, $code);
	@unlink($file);
	pg_assert_same(0, $code, 'the code part runs: ' . implode("\n", $lines));
	pg_assert_same(array('bindingCompletion', 'dregionOpen', 'findShow', 'flush', 'layoutOpen', 'refresh', 'saveShortcut', 'tabActivate', 'tabClose', 'tabOpen', 'tabSave', 'toggle'), json_decode($lines[0], true), 'its entry points');
}

// The code view's typed-changes engine (what is typed read back into the
// tree, the {{token}} bindings, the locks of the document) is in the part:
// nothing is typed before the view ran. The core keeps the bound props of
// the output and two stubs: the flush before a save and the binding
// completion the editor asks for.
function test_designer_code_view_typed_changes_are_in_the_part()
{
	$core = _test_blocks_js();
	$part = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer_code.js');
	$moved = array('_sdCodeApply', '_sdCodeOnChange', '_sdCodeReconcileChildren', '_sdCodeSetAttr', '_sdCodeTextFromTyped',
		'_sdCodeApplyBoundValue', '_sdCodeLockOf', '_sdCodeOpenTag', '_sdCodeTextKind', '_sdCodeLockTitle', '_sdCodeOnLockHit');

	foreach ($moved as $name) {
		pg_assert_false(strpos($core, 'function ' . $name . '(') !== false, $name . ' left the core');
		pg_assert_true(strpos($part, 'function ' . $name . '(') !== false, 'the part holds ' . $name);
		pg_assert_false(strpos($part, 'K.' . $name . ';') !== false, 'the part no longer takes ' . $name . ' from the kit');
	}

	pg_assert_false(strpos($core, '_SD_CODE_APPLY_MS') !== false, 'the pause before an apply is the part\'s');

	foreach (array('_sdCodeBoundProps', '_sdCodeAttrsWith', '_sdCodeBindToken', '_sdCodeFlush', '_sdCodeBindingCompletion') as $kept) {
		pg_assert_true(strpos($core, 'function ' . $kept . '(') !== false, $kept . ' stays in the core');
	}

	foreach (array('_sdCodeLockOf', '_sdCodeOpenTag', '_sdCodeOnChange', '_sdCodeTextKind') as $gone) {
		pg_assert_false(isset(designer_parts_kit_keys()[$gone]), $gone . ' is no longer in the kit');
	}
}

// The Themes tab (the Bootstrap variable rows, their cascade rules, the
// header and "save as a theme") is a part: drawn the first time the tab is
// shown. The core keeps the rule-block helpers the design's load runs
// (_themeUpgradeLightSelector() and the parsers under it) and one stub.
function test_designer_theme_panel_is_a_part()
{
	$parts = pg_designer_parts();
	pg_assert_same('assets/js/style_designer_theme.min.js', $parts['theme'] ?? '', 'the theme panel is a part');
	pg_assert_same(array('assets/js/style_designer_theme.js'), pg_js_bundles()['assets/js/style_designer_theme.min.js'] ?? array(), 'built from its file');
	$core = _test_blocks_js();
	$part = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer_theme.js');
	$moved = array('_themeCascadeRulesFor', '_writeCascadeBlock', '_writeThemeVar', '_findThemeVarMeta', '_renderThemeHead', '_sdSaveAsTheme', '_sdThemeDecorationLabels');

	foreach ($moved as $name) {
		pg_assert_false(strpos($core, 'function ' . $name . '(') !== false, $name . ' left the core');
		pg_assert_true(strpos($part, 'function ' . $name . '(') !== false, 'the part holds ' . $name);
	}

	pg_assert_false(strpos($core, 'THEME_VAR_GROUPS') !== false, 'the variable list left the core');

	foreach (array('_findRuleBlock', '_themeUpgradeLightSelector', '_themeSetDecl', '_themeModeDecls', '_sdThemeCssFromUserStyles', 'renderThemePanel') as $kept) {
		pg_assert_true(strpos($core, 'function ' . $kept . '(') !== false, $kept . ' stays in the core');
	}

	pg_assert_true(strpos($core, "_sdPartCall('theme', 'render'") !== false, 'the core runs the part to draw the tab');
	$screen = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/designer_screen.php');
	pg_assert_true(strpos($screen, "'theme'") !== false, 'the screen hands the theme part over');
}

// The theme part under Node, handed a kit with every key the core's has and
// a host like the core's: render() fills the variable rows of the mode the
// host names; a colour typed in a row is written through the host into the
// right block, with the cascade rules of a theme colour.
function test_designer_theme_part_draws_and_writes_through_the_host()
{
	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);

	if ($code !== 0) {
		return;
	}

	$keys = array_keys(designer_parts_kit_keys());
	$js = 'var factory = null; var StyleDesigner = { definePart: function (n, f) { if (n === "theme") factory = f; } };'
		. 'var window = {}; var localStorage = { getItem: function () { return null; }, setItem: function () {} };'
		. 'var listeners = []; var document = { addEventListener: function (t, f) { listeners.push(t); }, querySelector: function () { return null; } };'
		. 'function el() { var e = { innerHTML: "", style: {}, listeners: {}, addEventListener: function (t, f) { this.listeners[t] = f; }, querySelectorAll: function () { return []; }, querySelector: function () { return el(); } }; return e; }'
		. 'var head = el(), vars = el(); document.getElementById = function (id) { return id === "sd-theme-head" ? head : (id === "sd-theme-vars" ? vars : null); };'
		. 'var K = {}; ' . json_encode($keys) . '.forEach(function (k) { K[k] = function () {}; });'
		. 'K._sdT = function (s) { return s; }; K.esc = function (s) { return String(s); }; K._sdUsesBootstrap = function () { return true; };'
		. 'K._sdThemes = function () { return null; }; var saved = 0; K.saveState = function () { saved++; };'
		. 'var content = ""; var host = { wrap: el(), mode: function () { return "light"; }, userStyles: function () { return content; },'
		. ' setUserStyles: function (c) { content = c; }, activeTheme: function () { return null; }, openDesignSettings: function () {},'
		. ' themeCss: function () { return ""; }, blockFor: function (m, n) { return m === "dark" ? "dark" : "shared"; },'
		. ' setDecl: function (c, kind, n, v) { return c + kind + ":" + n + "=" + v + ";"; }, upgrade: function (c) { return c; },'
		. ' modeDecls: function () { return {}; }, hasDarkDefault: function () { return false; } };'
		. "\n" . (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer_theme.js') . "\n"
		. 'var api = factory(K); api.render(host);'
		. 'console.log(JSON.stringify([Object.keys(api).sort(), vars.innerHTML.indexOf("data-var=\"--bs-primary\"") !== -1, head.innerHTML.indexOf("No theme") !== -1]));'
		. 'api.write(host, "light", "--bs-primary", "#ff0000");'
		. 'console.log(JSON.stringify([saved, content.indexOf("shared:--bs-primary=#ff0000;") !== -1, content.indexOf("pg-theme-cascade:primary:light:start") !== -1]));';
	$file = tempnam(sys_get_temp_dir(), 'sdtheme') . '.js';
	file_put_contents($file, $js);
	$lines = array();
	exec('node ' . escapeshellarg($file) . ' 2>&1', $lines, $code);
	@unlink($file);
	pg_assert_same(0, $code, 'the theme part runs: ' . implode("\n", $lines));
	pg_assert_same(array(array('render', 'write'), true, true), json_decode($lines[0] ?? '', true), 'its entry points; the rows and the header drawn');
	pg_assert_same(array(1, true, true), json_decode($lines[1] ?? '', true), 'one undo step, the value and its cascade written');
}

// The Google Fonts browser (the modal, its searchable list and the detail
// with the weights) is a part: built the first time "Add a Google Font" is
// asked for. The core keeps the added-fonts list, its field and the Fonts
// section of the assets panel, and one stub that runs the part.
function test_designer_fonts_browser_is_a_part()
{
	$parts = pg_designer_parts();
	pg_assert_same('assets/js/style_designer_fonts.min.js', $parts['fonts'] ?? '', 'the fonts browser is a part');
	pg_assert_same(array('assets/js/style_designer_fonts.js'), pg_js_bundles()['assets/js/style_designer_fonts.min.js'] ?? array(), 'built from its file');
	$core = _test_blocks_js();
	$part = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer_fonts.js');

	foreach (array('renderGfBrowser', 'openGfPicker', 'closeGfModal') as $name) {
		pg_assert_false(strpos($core, 'function ' . $name . '(') !== false, $name . ' left the core');
		pg_assert_true(strpos($part, 'function ' . $name . '(') !== false, 'the part holds ' . $name);
	}

	pg_assert_false(strpos($core, "'sd-gf-modal'") !== false, 'the modal is no longer built at open');
	pg_assert_true(strpos($part, "'sd-gf-modal'") !== false, 'the part builds the modal');

	foreach (array('parseGfField', 'saveGfField', 'renderFontList', 'openGfModal') as $kept) {
		pg_assert_true(strpos($core, 'function ' . $kept . '(') !== false, $kept . ' stays in the core');
	}

	pg_assert_true(strpos($core, "_sdPartCall('fonts', 'open'") !== false, 'the core runs the part to open the browser');
	$screen = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/designer_screen.php');
	pg_assert_true(strpos($screen, "'fonts'") !== false, 'the screen hands the fonts part over');
}

// The fonts part under Node, handed a kit with every key the core's has and
// a host like the core's: open() builds the modal once and lists the
// catalogue, marking the fonts already added; picking a font and pressing
// Add writes the list back through the host with the weights ticked;
// Remove takes it out again.
function test_designer_fonts_part_lists_adds_and_removes_through_the_host()
{
	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);

	if ($code !== 0) {
		return;
	}

	$keys = array_keys(designer_parts_kit_keys());
	$js = 'var factory = null; var StyleDesigner = { definePart: function (n, f) { if (n === "fonts") factory = f; } };'
		. 'var byId = {}; var built = 0; var keydowns = 0;'
		. 'function el(tag) { var e = { tag: tag, id: "", value: "", dataset: {}, style: {}, classList: { add: function () {}, remove: function () {}, toggle: function () {} },'
		. ' listeners: {}, children: [], _html: "", isConnected: true, focus: function () {},'
		. ' addEventListener: function (t, f) { this.listeners[t] = f; }, appendChild: function (c) { this.children.push(c); if (c.id) byId[c.id] = c; return c; },'
		. ' querySelectorAll: function (s) { return (this.q && this.q[s]) || []; }, querySelector: function (s) { var l = this.querySelectorAll(s); return l.length ? l[0] : (byId[s.replace("#", "")] || null); } };'
		. ' Object.defineProperty(e, "innerHTML", { get: function () { return this._html; }, set: function (h) { this._html = h; if (this.id === "sd-gf-modal") { built++; ["sd-gf-modal-close", "sd-gf-search", "sd-gf-list", "sd-gf-detail"].forEach(function (id) { var c = el("div"); c.id = id; byId[id] = c; }); } } });'
		. ' return e; }'
		. 'var document = { body: el("body"), head: el("head"), createElement: function (t) { return el(t); }, getElementById: function (id) { return byId[id] || null; },'
		. ' querySelector: function () { return null; }, querySelectorAll: function () { return []; }, addEventListener: function (t) { if (t === "keydown") keydowns++; } };'
		. 'var setTimeout = function (f) { f(); };'
		. 'var K = {}; ' . json_encode($keys) . '.forEach(function (k) { K[k] = function () {}; });'
		. 'K._sdT = function (s) { return s; }; K.esc = function (s) { return String(s); };'
		. 'var added = [{ family: "Lato", weights: [400], enabled: true }]; var writes = 0;'
		. 'var host = { added: function () { return added; }, set: function (l) { added = l; writes++; }, catalogue: function () { return ["Inter", "Lato", "Roboto"]; } };'
		. "\n" . (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer_fonts.js') . "\n"
		. 'var api = factory(K); api.open(host); api.open(host);'
		. 'var list = byId["sd-gf-list"].innerHTML;'
		. 'console.log(JSON.stringify([Object.keys(api).sort(), built, keydowns, byId["sd-gf-modal"].style.display, (list.match(/data-gf-pick=/g) || []).length, /sd-gf-added-mark[^>]*data-gf-pick="Lato"/.test(list)]));'
		. 'var det = byId["sd-gf-detail"]; var w400 = { value: "400" }, w700 = { value: "700" }; var add = el("button"), rem = el("button");'
		. 'det.q = { "input:checked": [w400, w700], ".sd-gf-det-add": [add], ".sd-gf-det-remove": [rem], ".sd-gf-det-wt input": [] };'
		. 'api.pick(host, "Inter"); add.listeners.click();'
		. 'var inter = added.filter(function (f) { return f.family === "Inter"; })[0];'
		. 'console.log(JSON.stringify([writes, added.length, inter ? inter.weights : null, det.innerHTML.indexOf("Inter") !== -1]));'
		. 'api.pick(host, "Inter"); rem.listeners.click();'
		. 'console.log(JSON.stringify([writes, added.map(function (f) { return f.family; })]));';
	$file = tempnam(sys_get_temp_dir(), 'sdfonts') . '.js';
	file_put_contents($file, $js);
	$lines = array();
	exec('node ' . escapeshellarg($file) . ' 2>&1', $lines, $code);
	@unlink($file);
	pg_assert_same(0, $code, 'the fonts part runs: ' . implode("\n", $lines));
	pg_assert_same(array(array('open', 'pick'), 1, 1, 'flex', 3, true), json_decode($lines[0] ?? '', true), 'its entry points; the modal built once; the catalogue listed with Lato marked');
	pg_assert_same(array(1, 2, array(400, 700), true), json_decode($lines[1] ?? '', true), 'Add writes the font with its weights');
	pg_assert_same(array(2, array('Lato')), json_decode($lines[2] ?? '', true), 'Remove takes it out');
}
