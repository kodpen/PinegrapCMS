<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the image editor's site colours: the row of the site's palette
 * and variable colours before the shared swatches. The list the action
 * answers (includes/fn/image_editor.php) and the model's check of that answer
 * (assets/js/pg_image_editor_model.js, run under Node).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Runs $script with the model as M and answers its JSON; null without Node.
function image_editor_colors_node($script)
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

	$file = @tempnam(sys_get_temp_dir(), 'pgiecolors');

	if ($file === false) {
		return null;
	}

	file_put_contents($file, 'var M = require(' . json_encode(PG_FUNCTIONS_DIR . '/assets/js/pg_image_editor_model.js') . ');' . "\n" . $script);

	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);

	return ($code === 0) ? json_decode(implode("\n", $out), true) : array('error' => implode("\n", $out));
}

// Palettes first, then the variables a designer set, then the untouched
// ones; one swatch a colour, short forms written out, at most twelve.
function test_image_editor_site_colors_list_order_and_limit()
{
	$designs = array(
		array(
			'palette'   => array(array('color' => '#0F766E', 'name' => 'Ocean: primary colour'), array('color' => '#1e3a8a', 'name' => 'Ocean: secondary colour')),
			'variables' => array(
				array('color' => '#0f766e', 'name' => 'Brand', 'own' => false),
				array('color' => '#212529', 'name' => 'Text', 'own' => false),
				array('color' => '#f0a', 'name' => 'Pink', 'own' => true),
			),
		),
		array(
			'palette'   => array(array('color' => '#e11d48', 'name' => 'Coral: primary colour'), array('color' => 'red', 'name' => 'Not a hex')),
			'variables' => array(),
		),
	);

	pg_assert_same(array(
		array('color' => '#0f766e', 'name' => 'Ocean: primary colour'),
		array('color' => '#1e3a8a', 'name' => 'Ocean: secondary colour'),
		array('color' => '#e11d48', 'name' => 'Coral: primary colour'),
		array('color' => '#ff00aa', 'name' => 'Pink'),
		array('color' => '#212529', 'name' => 'Text'),
	), pg_image_editor_site_colors_list($designs), 'order, one swatch a colour, invalid dropped');

	$many = array('palette' => array(), 'variables' => array());

	for ($i = 0; $i < 20; $i++) {
		$many['variables'][] = array('color' => sprintf('#0000%02x', $i), 'name' => 'C' . $i, 'own' => true);
	}

	$list = pg_image_editor_site_colors_list(array($many));
	pg_assert_same(12, count($list), 'at most twelve');
	pg_assert_same('C11', $list[11]['name'], 'the first twelve kept');
	pg_assert_same(array(), pg_image_editor_site_colors_list(array()), 'no design, no row');
}

// A design's colour variables: built-in ones named by their label and marked
// own when they differ from the design's default, its own colours always own.
function test_image_editor_site_variable_colors()
{
	$defaults = pg_design_variables_defaults('', 'ocean');
	$model = array(
		'values' => array('brand' => array('light' => '#ff6600', 'dark' => '#ff8800')),
		'custom' => array(
			array('key' => 'leaf', 'name' => 'Leaf', 'kind' => 'color', 'light' => '#22aa44', 'dark' => '#22aa44'),
			array('key' => 'gap', 'name' => 'Gap', 'kind' => 'size', 'value' => '2rem'),
		),
	);

	$rows = pg_image_editor_site_variable_colors($model, $defaults);
	$by = array();

	foreach ($rows as $row) {
		$by[$row['name']] = $row;
	}

	pg_assert_same(array('color' => '#ff6600', 'name' => lang('Brand'), 'own' => true), $by[lang('Brand')], 'a changed brand is own');
	pg_assert_same(array('color' => '#1e3a8a', 'name' => lang('Accent'), 'own' => false), $by[lang('Accent')], 'an untouched accent is not');
	pg_assert_same(array('color' => '#22aa44', 'name' => 'Leaf', 'own' => true), $by['Leaf'], 'an own colour');
	pg_assert_false(isset($by['Gap']), 'a size is no colour');
	pg_assert_same(9, count($rows), 'eight built-in colours and one own');
}

// The palette part names each colour after its palette.
function test_image_editor_site_palette_colors()
{
	pg_assert_same(array(
		array('color' => '#0f766e', 'name' => lang(array('string' => '{var:1}: primary colour', 'vars' => array('Ocean')))),
		array('color' => '#1e3a8a', 'name' => lang(array('string' => '{var:1}: secondary colour', 'vars' => array('Ocean')))),
	), pg_image_editor_site_palette_colors('Ocean', array('primary' => '#0F766E', 'secondary' => '#1e3a8a')));
}

// The model keeps only well-formed swatches of an answer, twelve at most.
function test_image_editor_site_colors_model_check()
{
	$r = image_editor_colors_node(
		'var list = []; for (var i = 0; i < 15; i++) { list.push({ color: "#0000" + (i < 16 ? "0" : "") + i.toString(16), name: "C" + i }); }'
		. 'console.log(JSON.stringify({'
		. ' good: M.siteColors({ status: "success", colors: [{ color: "#AABBCC", name: "Brand" }, { color: "url(x)", name: "Bad" }, { color: "#aabbcc", name: "Twice" }, { color: "#112233" }, null] }),'
		. ' many: M.siteColors({ status: "success", colors: list }).length,'
		. ' refused: M.siteColors({ status: "error" }), none: M.siteColors(null) }));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_same(array(array('color' => '#aabbcc', 'name' => 'Brand'), array('color' => '#112233', 'name' => '#112233')), $r['good'], 'kept, lower case, once, named');
	pg_assert_same(12, $r['many'], 'twelve at most');
	pg_assert_same(array(), $r['refused'], 'a refusal is no row');
	pg_assert_same(array(), $r['none'], 'no answer is no row');
}

// The row's label belongs to the colours part.
function test_image_editor_site_colors_label()
{
	pg_assert_true(in_array('colors', pg_image_editor_parts(), true), 'the colours part is listed');
	$strings = pg_image_editor_strings_colors();
	pg_assert_true(isset($strings['siteColors']), 'the row is labelled');
}
