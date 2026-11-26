<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Help window's "Tips" switch in the Visual Page Editor: the
 * core's _sdTipsOff() reads the person's tour key, and every part that keeps
 * its own first-use key (select similar, overrides, the Sketch reading and
 * building, an element's states, variables, Preview as) asks it before its
 * line is drawn. The reading runs in Node; without Node that case is skipped.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

function designer_tips_off_test_fn()
{
	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');

	if (!preg_match('/\n    function _sdTipsOff\(\) \{\n.*?\n    \}\n/s', $js, $m)) {
		return '';
	}

	return $m[0];
}

function test_tips_off_core_hands_the_reading_to_the_parts()
{
	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');

	pg_assert_true(designer_tips_off_test_fn() !== '', 'the core has _sdTipsOff()');
	pg_assert_true(strpos($js, "            _sdTipsOff: _sdTipsOff,\n") !== false, 'the part kit hands it');
}

function test_tips_off_every_older_first_use_line_asks_it()
{
	$parts = array(
		'similar'     => "    function tipSeen() {\n        if (K._sdTipsOff()) return true;",
		'overrides'   => "    function firstSeen() {\n        if (K._sdTipsOff()) return true;",
		'sketchbuild' => "    function seen() {\n        if (K._sdTipsOff()) return true;",
		'sketchread'  => "    function _srSeen() {\n        if (K._sdTipsOff()) return true;",
		'states'      => "    function hintSeen() {\n        if (K._sdTipsOff()) return true;",
		'variables'   => "    function tipShown() {\n        if (K._sdTipsOff()) return false;",
		'previewas'   => "            if (K._sdTipsOff()) seen = true;",
	);

	foreach ($parts as $part => $needle) {
		$src = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer_' . $part . '.js');
		pg_assert_true(strpos($src, $needle) !== false, 'the ' . $part . ' line is not drawn with "Tips" off');
	}

	// The bands and side by side lines go through the tour model's tipDue().
	foreach (array('bands', 'mirror') as $part) {
		$src = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer_' . $part . '.js');
		pg_assert_true(strpos($src, 'if (!T.tipDue(state, TIP)) return;') !== false, 'the ' . $part . ' line reads the switch through tipDue()');
	}
}

function test_tips_off_reads_the_persons_tour_key()
{
	if (!function_exists('exec')) {
		return;
	}

	$out = array();
	$status = 1;
	@exec('node --version 2>&1', $out, $status);

	if ($status !== 0) {
		return;
	}

	$script = 'var store = {}; var window = { localStorage: { getItem: function (k) { return Object.prototype.hasOwnProperty.call(store, k) ? store[k] : null; } } };' . "\n"
		. 'var _design = { userId: 7 };' . "\n"
		. designer_tips_off_test_fn() . "\n"
		. 'var r = {}; r.none = _sdTipsOff();'
		. 'store["pg.designer.7.tour"] = JSON.stringify({ tipsOff: true }); r.off = _sdTipsOff();'
		. 'store["pg.designer.7.tour"] = JSON.stringify({ tipsOff: false, tips: { history: true } }); r.on = _sdTipsOff();'
		. 'store["pg.designer.7.tour"] = "{bad"; r.bad = _sdTipsOff();'
		. 'store["pg.designer.8.tour"] = JSON.stringify({ tipsOff: true }); store["pg.designer.7.tour"] = null; r.other = _sdTipsOff();'
		. '_design = { userId: 0 }; r.nobody = _sdTipsOff();'
		. 'process.stdout.write(JSON.stringify(r));';

	$file = tempnam(sys_get_temp_dir(), 'pgtipsoff');
	file_put_contents($file, $script);
	$lines = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $lines, $status);
	@unlink($file);

	pg_assert_same(0, $status, 'the Node script runs: ' . implode("\n", $lines));
	$res = json_decode(implode("\n", $lines), true);

	pg_assert_same(array('none' => false, 'off' => true, 'on' => false, 'bad' => false, 'other' => false, 'nobody' => false), $res, 'on unless the person turned "Tips" off');
}
