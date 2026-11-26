<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for a design's variables (includes/fn/design_variables.php): the
 * list, the defaults a look and a palette give, the stylesheet with its
 * pg-theme head and the reader that takes it back, and the contrast check
 * of text on the surface.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The declarations of the first rule whose selector starts with $selector.
function design_variables_test_block($css, $selector)
{
	$at = strpos($css, $selector . ' {');
	if ($at === false) return array();
	$end = strpos($css, '}', $at);
	$body = substr($css, $at, $end - $at);
	$out = array();
	if (preg_match_all('/^\s*(--[a-z0-9-]+):\s*(.*);$/m', $body, $m, PREG_SET_ORDER)) {
		foreach ($m as $hit) $out[$hit[1]] = $hit[2];
	}
	return $out;
}

function test_design_variables_list_has_the_four_groups()
{
	$groups = array();
	foreach (pg_design_variable_list() as $key => $def) {
		$groups[$def['group']][] = $key;
		pg_assert_true(isset($def['label'], $def['kind']), 'variable ' . $key . ' has a label and a kind');
	}
	pg_assert_same(array('color', 'type', 'spacing', 'shape'), array_keys(pg_design_variable_groups()), 'groups in order');
	pg_assert_same(array('brand', 'accent', 'neutral', 'surface', 'text', 'success', 'warning', 'danger'), $groups['color'], 'colour variables');
	pg_assert_same(array('font_heading', 'font_body', 'size_base', 'scale', 'weight_heading', 'weight_body', 'line_height'), $groups['type'], 'type variables');
	pg_assert_same(array('space_unit', 'space_section'), $groups['spacing'], 'spacing variables');
	pg_assert_same(array('radius', 'border_width', 'shadow'), $groups['shape'], 'shape variables');
}

function test_design_variables_defaults_follow_the_palette_and_the_look()
{
	$d = pg_design_variables_defaults('modern-sharp', 'ocean');
	pg_assert_same(array('light' => '#0f766e', 'dark' => '#0f766e'), $d['values']['brand'], 'brand is the palette primary');
	pg_assert_same(array('light' => '#1e3a8a', 'dark' => '#1e3a8a'), $d['values']['accent'], 'accent is the palette secondary');
	pg_assert_same(array('light' => '#ffffff', 'dark' => '#212529'), $d['values']['surface'], 'surface is Bootstrap body');
	$tokens = pg_design_look_tokens('modern-sharp');
	pg_assert_same($tokens['--pg-radius'], $d['values']['radius'], 'radius from the look');
	pg_assert_same($tokens['--pg-heading-weight'], $d['values']['weight_heading'], 'heading weight from the look');
	pg_assert_same('modern-sharp', $d['look'], 'look kept');
	pg_assert_same('ocean', $d['palette'], 'palette kept');
	pg_assert_same(array(), $d['custom'], 'no custom variables');
}

function test_design_variables_defaults_without_look_or_palette_are_bootstrap()
{
	$d = pg_design_variables_defaults('', '');
	pg_assert_same(array('light' => '#0d6efd', 'dark' => '#0d6efd'), $d['values']['brand'], 'Bootstrap primary');
	pg_assert_same('0.375rem', $d['values']['radius'], 'Bootstrap radius');
	pg_assert_same('1rem', $d['values']['size_base'], 'Bootstrap size');
	pg_assert_same('1.5', $d['values']['line_height'], 'Bootstrap line height');
	pg_assert_same('', $d['values']['shadow'], 'shadow follows the look');
	foreach (pg_design_variable_list() as $key => $def) {
		pg_assert_true(array_key_exists($key, $d['values']), 'default for ' . $key);
	}
}

function test_design_variables_normalize_refuses_bad_values()
{
	$d = pg_design_variables_defaults('', '');
	$in = $d;
	$in['values']['brand'] = array('light' => 'red; } body {', 'dark' => '#ABC');
	$in['values']['font_body'] = 'Arial; } * { display:none';
	$in['values']['size_base'] = '99rem';
	$in['values']['weight_heading'] = '650';
	$in['values']['line_height'] = '1.6';
	$in['values']['shadow'] = 'deep';
	$in['values']['radius'] = '12px';
	$in['values']['bogus'] = '1px';
	$m = pg_design_variables_normalize($in, $d);
	pg_assert_same(array('light' => '#0d6efd', 'dark' => '#aabbcc'), $m['values']['brand'], 'bad light refused, short hex widened');
	pg_assert_same($d['values']['font_body'], $m['values']['font_body'], 'font with a rule break refused');
	pg_assert_same('1rem', $m['values']['size_base'], 'size out of range refused');
	pg_assert_same($d['values']['weight_heading'], $m['values']['weight_heading'], 'weight off the hundreds refused');
	pg_assert_same('1.6', $m['values']['line_height'], 'line height kept');
	pg_assert_same('deep', $m['values']['shadow'], 'shadow choice kept');
	pg_assert_same('12px', $m['values']['radius'], 'radius in px kept');
	pg_assert_false(isset($m['values']['bogus']), 'unknown key dropped');
}

function test_design_variables_custom_variables_are_checked()
{
	$d = pg_design_variables_defaults('', '');
	$in = $d;
	$in['custom'] = array(
		array('key' => 'Sand Tone', 'name' => 'Sand', 'kind' => 'color', 'light' => '#e0c9a6', 'dark' => '#8a6d45'),
		array('key' => 'gap', 'name' => 'Gap */ x', 'kind' => 'size', 'value' => '2.5rem'),
		array('key' => 'gap', 'name' => 'Twice', 'kind' => 'size', 'value' => '1rem'),
		array('key' => 'bad', 'name' => 'Bad', 'kind' => 'size', 'value' => 'calc(1px)'),
		array('key' => 'x', 'name' => 'X', 'kind' => 'script', 'value' => '1px'),
	);
	$m = pg_design_variables_normalize($in, $d);
	pg_assert_same(2, count($m['custom']), 'two valid custom variables');
	pg_assert_same('sand-tone', $m['custom'][0]['key'], 'key made a slug');
	pg_assert_same('#8a6d45', $m['custom'][0]['dark'], 'dark kept');
	pg_assert_same('gap', $m['custom'][1]['key'], 'first gap kept, the second dropped');
	pg_assert_same('2.5rem', $m['custom'][1]['value'], 'size kept');
}

function test_design_variables_css_round_trips_through_the_reader()
{
	$d = pg_design_variables_defaults('minimal', 'sunset');
	$m = $d;
	$m['name'] = 'Shop */ variables';
	$m['values']['brand'] = array('light' => '#7c3aed', 'dark' => '#a78bfa');
	$m['values']['font_heading'] = '"Iowan Old Style", Georgia, serif';
	$m['custom'] = array(array('key' => 'sand', 'name' => 'Sand', 'kind' => 'color', 'light' => '#e0c9a6', 'dark' => '#8a6d45'));
	$css = pg_design_variables_css($m, $d);
	pg_assert_same(0, strpos($css, '/*! pg-theme {"kind":"variables"'), 'starts with the pg-theme head');
	pg_assert_same($css, pg_design_variables_css(pg_design_variables_read($css, $d), $d), 'read back writes the same file');
	$back = pg_design_variables_read($css, $d);
	pg_assert_same($m['values']['brand'], $back['values']['brand'], 'brand read back');
	pg_assert_same('Shop */ variables', $back['name'], 'name read back as typed');
	pg_assert_same('#8a6d45', $back['custom'][0]['dark'], 'custom read back');
	$outside = preg_replace('~/\*.*?\*/~s', '', $css);
	pg_assert_same(0, strpos(ltrim($outside), ':root'), 'no name closes a comment');
}

function test_design_variables_reader_refuses_other_files()
{
	$d = pg_design_variables_defaults('', '');
	pg_assert_same(null, pg_design_variables_read('body { color: red; }', $d), 'plain CSS');
	pg_assert_same(null, pg_design_variables_read(pg_design_palette_css('#2563eb', '#475569', 'P'), $d), 'a palette file');
	pg_assert_same(null, pg_design_variables_read(pg_design_theme_header(array('kind' => 'look')) . "\n", $d), 'a look head');
	pg_assert_same(null, pg_design_variables_read('/*! pg-theme {broken */', $d), 'broken json');
}

function test_design_variables_defaults_write_only_their_own_tokens()
{
	// A file of untouched defaults changes nothing the page already reads.
	$d = pg_design_variables_defaults('modern-soft', 'classic');
	$css = pg_design_variables_css($d, $d);
	$outside = preg_replace('~/\*.*?\*/~s', '', $css);
	preg_match_all('/(--[a-z0-9-]+)\s*:/', $outside, $m);
	foreach ($m[1] as $name) {
		pg_assert_same(0, strpos($name, '--pg-var-'), $name . ' is a variable token');
	}
	$all = design_variables_test_block($css, ":root,\n[data-bs-theme]");
	$light = design_variables_test_block($css, ":root,\n[data-bs-theme=light]");
	pg_assert_same('#2563eb', $light['--pg-var-brand'], 'brand token');
	pg_assert_same($d['values']['radius'], $all['--pg-var-radius'], 'radius token');
}

function test_design_variables_changed_values_drive_the_theme_tokens()
{
	$d = pg_design_variables_defaults('', '');
	$m = $d;
	$m['values']['brand'] = array('light' => '#7c3aed', 'dark' => '#a78bfa');
	$m['values']['surface'] = array('light' => '#fafaf9', 'dark' => '#1c1917');
	$m['values']['font_body'] = 'Georgia, serif';
	$m['values']['radius'] = '0.75rem';
	$m['values']['border_width'] = '2px';
	$m['values']['shadow'] = 'crisp';
	$css = pg_design_variables_css($m, $d);
	$all   = design_variables_test_block($css, ":root,\n[data-bs-theme]");
	$light = design_variables_test_block($css, ":root,\n[data-bs-theme=light]");
	$dark  = design_variables_test_block($css, '[data-bs-theme=dark]');
	pg_assert_same('#7c3aed', $light['--bs-primary'], 'light primary');
	pg_assert_same('#a78bfa', $dark['--bs-primary'], 'dark primary');
	pg_assert_same('#a78bfa', $dark['--pg-var-brand'], 'dark brand token');
	pg_assert_same('#fafaf9', $light['--bs-body-bg'], 'light surface');
	pg_assert_same('28, 25, 23', $dark['--bs-body-bg-rgb'], 'dark surface rgb');
	pg_assert_same('Georgia, serif', $all['--pg-font-body'], 'body font');
	pg_assert_same('0.75rem', $all['--pg-radius'], 'radius');
	pg_assert_same('1rem', $all['--pg-radius-lg'], 'radius ladder follows');
	pg_assert_same('2px', $all['--pg-border-width'], 'line width');
	pg_assert_true(isset($all['--pg-shadow-lg']), 'shadow preset written');
	pg_assert_false(isset($light['--bs-secondary']), 'unchanged accent writes nothing');
	pg_assert_false(isset($all['--pg-line-height']), 'unchanged line height writes nothing');
}

function test_design_variables_contrast_of_text_on_surface()
{
	$d = pg_design_variables_defaults('', '');
	foreach (pg_design_variables_contrast($d) as $row) {
		pg_assert_true($row['ok'], $row['text'] . ' on surface reads in ' . $row['mode']);
	}
	$m = $d;
	$m['values']['text'] = array('light' => '#bbbbbb', 'dark' => '#dee2e6');
	$rows = pg_design_variables_contrast($m);
	pg_assert_same('text', $rows[0]['text'], 'first row is text');
	pg_assert_same('light', $rows[0]['mode'], 'in light mode');
	pg_assert_false($rows[0]['ok'], 'pale grey text on white fails');
	pg_assert_same(1.92, $rows[0]['ratio'], 'ratio to two places');
	pg_assert_true($rows[1]['ok'], 'dark mode still reads');
}

function test_design_variables_head_stays_short()
{
	// The editor reads only the start of a design CSS file for its head.
	$d = pg_design_variables_defaults('', '');
	$m = $d;
	$m['name'] = str_repeat('N', 200);
	for ($i = 0; $i < 40; $i++) {
		$m['custom'][] = array('key' => 'c' . $i . str_repeat('x', 40), 'name' => str_repeat('n', 80), 'kind' => 'color', 'light' => '#123456', 'dark' => '#654321');
	}
	$css = pg_design_variables_css($m, $d);
	$head = strtok($css, "\n");
	pg_assert_true(strlen($head) <= PG_DESIGN_VARIABLES_HEAD_MAX, 'head under the limit: ' . strlen($head));
}

function test_design_variables_rebase_follows_a_new_palette()
{
	$old = pg_design_variables_defaults('', 'ocean');
	$new = pg_design_variables_defaults('', '');
	$m = $old;
	$m['values']['accent'] = array('light' => '#ff0000', 'dark' => '#ff0000');
	$r = pg_design_variables_rebase($m, $old, $new);
	pg_assert_same($new['values']['brand'], $r['values']['brand'], 'an untouched brand follows the new palette');
	pg_assert_same(array('light' => '#ff0000', 'dark' => '#ff0000'), $r['values']['accent'], 'a changed accent stays');
	pg_assert_same('', $r['palette'], 'the model now names the new palette');
}

function test_design_variables_state_rebases_a_model_of_another_palette()
{
	$m = pg_design_variables_defaults('', 'ocean');
	$s = pg_design_variables_state($m, '', '');
	$d = pg_design_variables_defaults('', '');
	pg_assert_same($d['values']['brand'], $s['model']['values']['brand'], 'brand rebased');
	pg_assert_same($d, $s['defaults'], 'defaults of the current choice');
	pg_assert_same(pg_design_variables_css($s['model'], $d), $s['css'], 'stylesheet of the rebased model');
	pg_assert_true(strpos($s['css'], '--bs-primary') === false, 'untouched defaults drive no theme token');
	pg_assert_same(4, count($s['contrast']), 'four contrast rows');

	$none = pg_design_variables_state(null, '', 'ocean');
	pg_assert_same(pg_design_variables_defaults('', 'ocean'), $none['model'], 'no model: the defaults');
}

function test_design_variables_state_keeps_a_model_of_the_same_choice()
{
	$m = pg_design_variables_defaults('', 'ocean');
	$m['values']['brand'] = array('light' => '#112233', 'dark' => '#445566');
	$s = pg_design_variables_state($m, '', 'ocean');
	pg_assert_same(array('light' => '#112233', 'dark' => '#445566'), $s['model']['values']['brand'], 'brand kept');
	pg_assert_true(strpos($s['css'], '--bs-primary: #112233') !== false, 'a changed brand writes the primary');
}

function test_design_variables_client_list_matches_the_list()
{
	$c = pg_design_variables_client_list();
	pg_assert_same(array('color', 'type', 'spacing', 'shape'), array_column($c['groups'], 'key'), 'groups in order');
	pg_assert_same(array_keys(pg_design_variable_list()), array_column($c['rows'], 'key'), 'rows in order');
	foreach ($c['rows'] as $row) {
		if ($row['key'] === 'shadow') {
			pg_assert_same('', $row['options'][0]['value'], 'shadow starts with As the look');
		}
		if ($row['key'] === 'radius') {
			pg_assert_same(array(0, 3), $row['rem'], 'radius rem bounds');
		}
	}
}

function test_design_variables_file_name_and_head()
{
	pg_assert_same('design-variables-12.css', pg_design_variables_file_name(12), 'file name of a design');
	pg_assert_same('', pg_design_variables_file_name(0), 'no file for no design');
	$css = pg_design_variables_css(pg_design_variables_defaults('', ''), pg_design_variables_defaults('', ''));
	pg_assert_true(pg_design_variables_head_ok($css), 'a written file has the variables head');
	pg_assert_false(pg_design_variables_head_ok('/*! pg-theme {"kind":"look"} */'), 'a look file is not variables');
	pg_assert_false(pg_design_variables_head_ok('body { color: red; }'), 'a plain stylesheet is not variables');
}

function test_design_variables_load_after_the_palette()
{
	$ids = function ($assets) { return array_column($assets, 'id'); };
	pg_assert_same(array(), pg_design_theme_assets('', '', 'bootstrap5', ''), 'nothing without a choice or a file');
	pg_assert_same(array('pg-theme-bridge', 'pg-theme-base', 'pg-theme-variables'),
		$ids(pg_design_theme_assets('', '', 'bootstrap5', '/design-variables-3.css?v=1')), 'variables alone bring the bridge and the look engine');
	pg_assert_same(array('pg-theme-bridge', 'pg-theme-base', 'pg-theme-look', 'pg-theme-palette', 'pg-theme-variables'),
		$ids(pg_design_theme_assets('modern-sharp', 'ocean', 'bootstrap5', '/design-variables-3.css?v=1')), 'variables last');
	pg_assert_same(array('pg-theme-bridge', 'pg-theme-palette'),
		$ids(pg_design_theme_assets('', 'ocean', 'bootstrap5')), 'a palette alone as before');
}

// A bare 0 is a length only where the row's bounds start at 0: a base size
// of 0 sets --pg-font-size: 0 and hides every page's text.
function test_design_variables_zero_only_where_the_row_starts_at_zero()
{
	$d = pg_design_variables_defaults('', '');
	$in = $d;
	$in['values']['size_base'] = '0';
	$in['values']['space_unit'] = '0';
	$in['values']['space_section'] = '0';
	$in['values']['radius'] = '0';
	$in['values']['border_width'] = '0';
	$m = pg_design_variables_normalize($in, $d);
	pg_assert_same('1rem', $m['values']['size_base'], 'base size 0 refused');
	pg_assert_same('1rem', $m['values']['space_unit'], 'base unit 0 refused');
	pg_assert_same('4rem', $m['values']['space_section'], 'section rhythm 0 refused');
	pg_assert_same('0', $m['values']['radius'], 'square corners kept');
	pg_assert_same('0', $m['values']['border_width'], 'no lines kept');
}

// A font stack's names are bare or in one pair of quotes: an open quote
// runs on over the next declaration and the next variable is lost.
function test_design_variables_font_quotes_are_closed()
{
	$d = pg_design_variables_defaults('', '');
	$in = $d;
	$in['values']['font_heading'] = '"Inter';
	$in['values']['font_body'] = "Inter, 'Segoe UI\", Arial";
	$m = pg_design_variables_normalize($in, $d);
	pg_assert_same($d['values']['font_heading'], $m['values']['font_heading'], 'an open quote refused');
	pg_assert_same($d['values']['font_body'], $m['values']['font_body'], 'mixed quotes refused');
	$in['values']['font_body'] = "Inter, 'Segoe UI', \"Noto Sans\", sans-serif";
	$m = pg_design_variables_normalize($in, $d);
	pg_assert_same("Inter, 'Segoe UI', \"Noto Sans\", sans-serif", $m['values']['font_body'], 'closed quotes kept');
}
