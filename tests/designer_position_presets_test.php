<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Position presets and Z Index layers of the Options panel's
 * Size and display section (assets/js/style_designer.src.js): "Fill the
 * parent" and "Centre" as the four offsets and their classes, the preset
 * an element matches, Bootstrap's layers by name and which typed z-index
 * is written. The pure block is cut out of the source and run under Node;
 * without Node those cases are skipped.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Runs $code after the pure block; its `out` comes back decoded. Null without Node.
function designer_position_presets_test_run($code)
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');
	$from = strpos($source, '    // ── Position presets and layers: pure helpers');
	$to = ($from === false) ? false : strpos($source, '    // ── end of position presets and layers', $from);
	$block = ($to === false) ? '' : substr($source, $from, $to - $from);

	pg_assert_true($block !== '', 'the editor has the pure block of the position presets');
	if ($block === '' || !function_exists('exec')) {
		return null;
	}

	$out = array();
	$status = 1;
	@exec('node --version 2>&1', $out, $status);
	if ($status !== 0) {
		return null;
	}

	$file = tempnam(sys_get_temp_dir(), 'pgpos');
	file_put_contents($file, $block . "\nvar out = null;\n" . $code . "\nconsole.log(JSON.stringify(out));");
	$out = array();
	exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $status);
	unlink($file);

	pg_assert_same(0, $status, implode("\n", $out));

	return json_decode(implode("\n", $out), true);
}

// Fill sets every offset to 0 and drops a centring translate; Centre puts
// the corner at 50% / 50% with translate-middle; an element without a
// Position becomes Absolute, a positioned one keeps its own.
function test_designer_position_presets_write()
{
	$answer = designer_position_presets_test_run(<<<'JS'
out = {
	fillBare: _sdPosPreset('fill', ''),
	fillCentred: _sdPosPreset('fill', 'card translate-middle position-relative'),
	centreStatic: _sdPosPreset('centre', 'p-3 position-static'),
	centreFixed: _sdPosPreset('centre', 'position-fixed translate-middle'),
	unknown: _sdPosPreset('corner', 'position-absolute')
};
JS
	);
	if ($answer === null) {
		return;
	}

	pg_assert_same(array('top' => '0', 'right' => '0', 'bottom' => '0', 'left' => '0', 'cls' => 'position-absolute'), $answer['fillBare']);
	pg_assert_same(array('top' => '0', 'right' => '0', 'bottom' => '0', 'left' => '0', 'cls' => 'card position-relative'), $answer['fillCentred']);
	pg_assert_same(array('top' => '50%', 'right' => '', 'bottom' => '', 'left' => '50%', 'cls' => 'p-3 translate-middle position-absolute'), $answer['centreStatic']);
	pg_assert_same('position-fixed translate-middle', $answer['centreFixed']['cls'], 'translate-middle is held once');
	pg_assert_same(null, $answer['unknown']);
}

// The preset an element matches is read back from its offsets and classes.
function test_designer_position_presets_read_back()
{
	$answer = designer_position_presets_test_run(<<<'JS'
out = [
	_sdPosPresetOf({ top: '0', right: '0px', bottom: '0', left: 0 }, 'position-absolute'),
	_sdPosPresetOf({ top: '0', right: '0', bottom: '0', left: '0' }, 'position-absolute translate-middle'),
	_sdPosPresetOf({ top: '50%', left: '50%' }, 'position-absolute translate-middle'),
	_sdPosPresetOf({ top: '50%', left: '50%', bottom: '1rem' }, 'translate-middle'),
	_sdPosPresetOf({ top: '0', left: '0' }, ''),
	_sdPosPresetOf(null, '')
];
JS
	);
	if ($answer === null) {
		return;
	}

	pg_assert_same(array('fill', '', 'centre', '', '', ''), $answer);
}

// Bootstrap 5.3's layers by name, and the typed values that are written:
// a whole number or auto, as getNodeExtraStyle() writes them.
function test_designer_position_presets_layers()
{
	$answer = designer_position_presets_test_run(<<<'JS'
out = {
	names: _SD_Z_LAYERS.map(function (l) { return l[1] + ' ' + l[0]; }),
	layerOf: ['1020', ' 1055 ', '1050', '', '10'].map(_sdZLayerOf),
	valid: ['', '5', '-1', '1030', 'auto', 'AUTO', ' 7 ', '1.5', '10px', '9; color:red', 'none'].map(_sdZValid)
};
JS
	);
	if ($answer === null) {
		return;
	}

	pg_assert_same(array('Dropdown 1000', 'Sticky 1020', 'Fixed 1030', 'Modal 1055'), $answer['names']);
	pg_assert_same(array('1020', '1055', '', '', ''), $answer['layerOf']);
	pg_assert_same(array(true, true, true, true, true, true, true, false, false, false, false), $answer['valid']);
}
