<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the design variables' Export and Import
 * (includes/fn/design_variables.php): the Design Tokens file written from a
 * model, read back into it, read from another tool's file, and refused with
 * its reason when it is not a tokens file.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function _dvt_defaults()
{
	return pg_design_variables_defaults('', '');
}

function test_design_tokens_export_writes_every_variable_in_the_format()
{
	$d = _dvt_defaults();
	$m = $d;
	$m['values']['shadow'] = 'soft';
	$m['custom'] = array(array('key' => 'sand', 'name' => 'Sand', 'kind' => 'color', 'light' => '#e0c9a6', 'dark' => '#8a6d45'),
		array('key' => 'gutter', 'name' => 'Gutter', 'kind' => 'size', 'value' => '24px'));
	$m = pg_design_variables_normalize($m, $d);
	$e = pg_design_variables_tokens_export($m);
	$t = $e['tokens'];
	pg_assert_same(count(pg_design_variable_list()) + 2, $e['count'], 'every built-in and both own variables');
	pg_assert_same('color', $t['color']['brand']['$type'], 'a colour token');
	pg_assert_same($m['values']['brand']['light'], $t['color']['brand']['$value'], 'the light value is the value');
	pg_assert_same($m['values']['brand']['dark'], $t['color']['brand']['$extensions']['com.pinegrap']['dark'], 'the dark value rides in the extensions');
	pg_assert_same('dimension', $t['type']['size-base']['$type'], 'a length is a dimension');
	pg_assert_true(isset($t['type']['size-base']['$value']['unit']), 'a dimension is a value and a unit');
	pg_assert_same('fontWeight', $t['type']['weight-heading']['$type'], 'a weight');
	pg_assert_same('soft', $t['shape']['$extensions']['com.pinegrap']['shadow'], 'the shadow preset in the shape group');
	pg_assert_same('Sand', $t['own']['sand']['$description'], 'an own variable keeps its name');
	pg_assert_same(array('value' => 24, 'unit' => 'px'), $t['own']['gutter']['$value'], 'an own size');
	pg_assert_same('aurora-studio.tokens.json', pg_design_variables_tokens_file_name('Aurora Studio'), 'the file name');
	pg_assert_same('design.tokens.json', pg_design_variables_tokens_file_name(''), 'a design without a name');
}

function test_design_tokens_round_trip_gives_the_same_model()
{
	$d = _dvt_defaults();
	$m = $d;
	$m['values']['brand'] = array('light' => '#7c3aed', 'dark' => '#a78bfa');
	$m['values']['font_heading'] = '"Playfair Display", serif';
	$m['values']['size_base'] = '1.125rem';
	$m['values']['scale'] = '1.25';
	$m['values']['radius'] = '0';
	$m['values']['shadow'] = 'soft';
	$m['custom'] = array(array('key' => 'sand', 'name' => 'Sand', 'kind' => 'color', 'light' => '#e0c9a6', 'dark' => '#8a6d45'));
	$m = pg_design_variables_normalize($m, $d);
	$r = pg_design_variables_tokens_import(pg_design_variables_tokens_text($m), $d, $d);
	pg_assert_true($r['ok'], 'our own file is read');
	pg_assert_same($m['values'], $r['model']['values'], 'every value comes back');
	pg_assert_same($m['custom'], $r['model']['custom'], 'the own variables come back');
	pg_assert_same(0, $r['left'], 'nothing is left out');
	pg_assert_same(1, $r['own'], 'one own variable is new');
	pg_assert_same(1, $r['groups']['color'], 'one colour changes');
	pg_assert_same(2, $r['groups']['shape'], 'corners and shadow change');

	$again = pg_design_variables_tokens_import(pg_design_variables_tokens_text($m), $m, $d);
	pg_assert_true($again['ok'], 'the same file on the same design is read');
	pg_assert_same(0, $again['changed'], 'and changes nothing');
	pg_assert_same(0, $again['own'], 'and adds nothing');
}

function test_design_tokens_read_another_tools_file()
{
	$d = _dvt_defaults();
	$file = json_encode(array(
		'colors' => array(
			'$type' => 'color',
			'primary' => array('$value' => '#FF5500'),
			'surface' => array('$value' => array('colorSpace' => 'srgb', 'components' => array(1, 1, 1), 'hex' => '#fafafa')),
			'link' => array('$value' => '{colors.primary}'),
			'sky' => array('$value' => '#38bdf8cc'),
		),
		'size' => array(
			'radius' => array('$type' => 'dimension', '$value' => '12px'),
			'gutter' => array('$type' => 'dimension', '$value' => array('value' => 1.5, 'unit' => 'rem')),
		),
		'font' => array('font-body' => array('$type' => 'fontFamily', '$value' => array('Open Sans', 'sans-serif'))),
		'fx' => array(
			'fade' => array('$type' => 'duration', '$value' => '200ms'),
			'sunset' => array('$type' => 'gradient', '$value' => array()),
			'lost' => array('$type' => 'color', '$value' => '{nowhere.at.all}'),
		),
	));
	$r = pg_design_variables_tokens_import($file, $d, $d);
	pg_assert_true($r['ok'], 'a file of another tool is read');
	pg_assert_same('#ff5500', $r['model']['values']['brand']['light'], 'primary is the brand colour');
	pg_assert_same($d['values']['brand']['dark'], $r['model']['values']['brand']['dark'], 'no dark value keeps this design\'s');
	pg_assert_same('#fafafa', $r['model']['values']['surface']['light'], 'the 2025 colour object');
	pg_assert_same('12px', $r['model']['values']['radius'], 'radius is the corners');
	pg_assert_same('"Open Sans", sans-serif', $r['model']['values']['font_body'], 'a font list becomes a stack');
	$own = array();
	foreach ($r['model']['custom'] as $c) $own[$c['key']] = $c;
	pg_assert_same('#ff5500', $own['colors-link']['light'], 'a reference is followed');
	pg_assert_same('#38bdf8', $own['colors-sky']['light'], 'the alpha of a colour is dropped');
	pg_assert_same('1.5rem', $own['size-gutter']['value'], 'a 2025 dimension');
	pg_assert_same(3, $r['own'], 'three own variables');
	pg_assert_same(array('color' => 2, 'size' => 1), $r['own_kinds'], 'two colours and a size');
	pg_assert_same(array('duration' => 1, 'gradient' => 1, 'reference' => 1), $r['left_kinds'], 'what has no place is counted by kind');
	pg_assert_same(10, $r['tokens'], 'every token of the file');
}

function test_design_tokens_refused_with_the_reason()
{
	$d = _dvt_defaults();
	$not_json = pg_design_variables_tokens_import('body { color: red }', $d, $d);
	pg_assert_same(false, $not_json['ok'], 'a stylesheet is refused');
	pg_assert_same('It is not a JSON file.', $not_json['reason'], 'because it is not JSON');
	$plain = pg_design_variables_tokens_import('{"brand": "#ff0000", "name": "x"}', $d, $d);
	pg_assert_true(strpos($plain['reason'], '$value') !== false, 'JSON without a $value is not a tokens file');
	$none = pg_design_variables_tokens_import('{"motion":{"fast":{"$type":"duration","$value":"100ms"}}}', $d, $d);
	pg_assert_same('None of its tokens is a colour, a size, a font or a number.', $none['reason'], 'tokens without a place');
	$big = pg_design_variables_tokens_import(str_repeat(' ', PG_DESIGN_TOKENS_MAX + 1), $d, $d);
	pg_assert_same('It is larger than a tokens file can be.', $big['reason'], 'too large');
	$bad = pg_design_variables_tokens_import('{"color":{"brand":{"$type":"color","$value":"red;}"}}}', $d, $d);
	pg_assert_same(false, $bad['ok'], 'a value that fails the checks is never taken');
}

function test_design_tokens_own_variables_stop_at_the_limit()
{
	$d = _dvt_defaults();
	$own = array();
	for ($i = 1; $i <= 30; $i++) $own['c' . $i] = array('$type' => 'color', '$value' => sprintf('#%06x', $i));
	$r = pg_design_variables_tokens_import(json_encode(array('own' => $own)), $d, $d);
	pg_assert_same(24, count($r['model']['custom']), 'at most 24 own variables');
	pg_assert_same(array('limit' => 6), $r['left_kinds'], 'the rest are counted as over the limit');
}

// A file's token whose name is one of the design's own variables of another
// kind does not turn it into that kind: the places that read it would get a
// colour for a size. It is left out.
function test_design_tokens_own_variable_keeps_its_kind()
{
	$d = _dvt_defaults();
	$m = $d;
	$m['custom'] = array(array('key' => 'gap', 'name' => 'Gap', 'kind' => 'size', 'value' => '2rem'));
	$text = json_encode(array('own' => array('gap' => array('$type' => 'color', '$value' => '#ff0000'),
		'sand' => array('$type' => 'color', '$value' => '#e0c9a6'))));
	$r = pg_design_variables_tokens_import($text, $m, $d);
	pg_assert_true($r['ok']);
	pg_assert_same(array('key' => 'gap', 'name' => 'Gap', 'kind' => 'size', 'value' => '2rem'), $r['model']['custom'][0], 'the size stays a size');
	pg_assert_same(1, $r['own'], 'the new colour comes as own');
	pg_assert_same(1, $r['left'], 'the colour for the size is left out');
	pg_assert_same(0, $r['groups']['own'], 'no own variable reported as changed');
}

// The design's own variables are written under "own": read back they stay
// own variables even when their key reads as a built-in name (size-base,
// border-width, muted), so a round trip changes nothing.
function test_design_tokens_own_group_never_maps_to_a_built_in()
{
	$d = _dvt_defaults();
	$m = $d;
	$m['custom'] = array(
		array('key' => 'size-base', 'name' => 'Size base', 'kind' => 'size', 'value' => '3rem'),
		array('key' => 'muted', 'name' => 'Muted', 'kind' => 'color', 'light' => '#123456', 'dark' => '#654321'),
	);
	$r = pg_design_variables_tokens_import(pg_design_variables_tokens_text($m), $m, $d);
	pg_assert_true($r['ok']);
	pg_assert_same($m['values'], $r['model']['values'], 'no built-in value changes');
	pg_assert_same($m['custom'], $r['model']['custom'], 'the own variables stay');
	pg_assert_same(0, $r['changed'], 'nothing changes');
}
