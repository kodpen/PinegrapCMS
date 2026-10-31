<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the image editor's page side (includes/fn/image_editor.php): the
 * loader is printed once with its config, the scripts run the model first,
 * and every tool the model knows has a label.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// pg_image_editor_includes() and its config, drawn in a PHP process of its
// own: the URLs need OUTPUT_PATH, which the tests after this one must not
// find defined, and the loader prints once per process.
function image_editor_includes_run()
{
	if (!function_exists('exec') || !defined('PHP_BINARY') || (PHP_BINARY === '')) {
		return null;
	}

	$file = @tempnam(sys_get_temp_dir(), 'pgieincludes');

	if ($file === false) {
		return null;
	}

	file_put_contents($file, '<?php' . "\n"
		. "define('PG_TEST_RUNNER', true);\n"
		. "define('OUTPUT_PATH', '/');\n"
		. "define('OUTPUT_SOFTWARE_DIRECTORY', 'pinegrap');\n"
		. 'require ' . var_export(PG_FUNCTIONS_DIR . '/functions.php', true) . ";\n"
		. "echo json_encode(array('first' => pg_image_editor_includes(), 'second' => pg_image_editor_includes(), 'config' => pg_image_editor_config()));\n");

	$out = array();
	$code = 1;
	@exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);

	return array('code' => $code, 'out' => $out, 'res' => json_decode(implode("\n", $out), true));
}

// The loader comes once per page, as one script whose JSON cannot close the
// script element, and names the editor's script and stylesheet.
function test_image_editor_includes_print_the_loader_once()
{
	$run = image_editor_includes_run();

	if ($run === null) {
		return;
	}

	pg_assert_same(0, $run['code'], 'drawn: ' . implode("\n", array_slice($run['out'], 0, 8)));

	if (!is_array($run['res'])) {
		pg_assert_true(false, 'the process answers');
		return;
	}

	$first = $run['res']['first'];
	$config = $run['res']['config'];

	pg_assert_contains('window.pgImageEditorConfig = ', $first, 'the config is printed');
	pg_assert_contains('window.pgImageEditor = {', $first, 'the loader is printed');
	pg_assert_same(1, substr_count($first, '</'), 'only the closing tag starts with </');
	pg_assert_same('', $run['res']['second'], 'a second call prints nothing');
	pg_assert_true(count($config['scripts']) > 0, 'the bundle has scripts');

	foreach ($config['scripts'] as $url) {
		pg_assert_true((bool) preg_match('#^/pinegrap/assets/js/pg_image_editor[a-z_]*(\.min)?\.js\?v=\d+$#', $url), $url . ' is an editor script with its stamp');
	}

	pg_assert_true((bool) preg_match('#^/pinegrap/assets/css/pg_image_editor\.(min|src)\.css\?v=\d+$#', $config['style']), $config['style'] . ' is the editor stylesheet with its stamp');
	pg_assert_same(pg_image_editor_strings(), $config['strings'], 'the labels travel with the config');
}

// The model runs before the modal that reads it; the stylesheet is built
// from its source.
function test_image_editor_bundles_run_the_model_first()
{
	$scripts = pg_js_bundles();
	$styles = pg_css_bundles();

	pg_assert_same(array('assets/js/pg_image_editor_model.js', 'assets/js/pg_image_editor.js'), $scripts['assets/js/pg_image_editor.min.js'], 'the model, then the modal');
	pg_assert_same('assets/css/pg_image_editor.src.css', $styles['assets/css/pg_image_editor.min.css'], 'the stylesheet source');
}

// Every tool the model accepts has a label, and no label is empty.
function test_image_editor_strings_name_every_tool()
{
	$model = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/pg_image_editor_model.js');
	$strings = pg_image_editor_strings();

	pg_assert_true((bool) preg_match('#var TOOLS = \[([^\]]+)\]#', $model, $m), 'the model lists its tools');
	preg_match_all("#'([a-z]+)'#", isset($m[1]) ? $m[1] : '', $tools);
	pg_assert_true(count($tools[1]) > 10, 'the tool list was read');

	foreach ($tools[1] as $tool) {
		pg_assert_true(isset($strings['tool_' . $tool]), 'tool_' . $tool . ' has a label');
	}

	foreach ($strings as $key => $text) {
		pg_assert_true(is_string($text) && ($text !== ''), $key . ' is a text');
	}
}
