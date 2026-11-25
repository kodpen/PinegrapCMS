<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the style guide sheet's model (includes/fn/design_style_guide.php):
 * a design's variables, its stylesheet's text styles and its shared components
 * in, the sheet's blocks out, with the text on surface contrast pairs.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The row of $rows whose $field is $value, or null.
function style_guide_test_row($rows, $field, $value)
{
	foreach ($rows as $row) {
		if (isset($row[$field]) && $row[$field] === $value) return $row;
	}
	return null;
}

function test_style_guide_blocks_come_in_the_sheet_order()
{
	$d = pg_design_variables_defaults('', '');
	$m = pg_style_guide_model($d, $d, '', array());
	pg_assert_same(array('colours', 'type', 'spacing', 'shape', 'controls', 'components'), array_keys($m['blocks']), 'block order');
	foreach ($m['blocks'] as $key => $block) {
		pg_assert_true(is_string($block['title']) && $block['title'] !== '', 'block ' . $key . ' has a title');
	}
	pg_assert_true($m['has_variables'], 'a model given is the design\'s variables');
	pg_assert_false($m['offer_start'], 'no start questions offered');
}

function test_style_guide_without_variables_shows_the_theme_values_and_offers_the_start()
{
	$d = pg_design_variables_defaults('modern-sharp', 'ocean');
	$m = pg_style_guide_model(null, $d, '', array());
	pg_assert_false($m['has_variables'], 'no variables file');
	pg_assert_true($m['offer_start'], 'the start questions are offered');
	$brand = style_guide_test_row($m['blocks']['colours']['rows'], 'key', 'brand');
	pg_assert_same('#0f766e', $brand['light'], 'brand from the palette');
	$radius = style_guide_test_row($m['blocks']['shape']['rows'], 'key', 'radius');
	$tokens = pg_design_look_tokens('modern-sharp');
	pg_assert_same($tokens['--pg-radius'], $radius['value'], 'corners from the look');
}

function test_style_guide_colours_carry_name_values_and_token()
{
	$d = pg_design_variables_defaults('', '');
	$v = $d;
	$v['custom'] = array(array('key' => 'Sand', 'name' => 'Sand', 'kind' => 'color', 'light' => '#e9dcc3', 'dark' => '#5a4a2e'));
	$v = pg_design_variables_normalize($v, $d);
	$rows = pg_style_guide_model($v, $d, '', array())['blocks']['colours']['rows'];
	pg_assert_same(array('brand', 'accent', 'neutral', 'surface', 'text', 'success', 'warning', 'danger', 'sand'), array_map(function ($r) { return $r['key']; }, $rows), 'built-in colours then the designer\'s own');
	$surface = style_guide_test_row($rows, 'key', 'surface');
	pg_assert_same(array('#ffffff', '#212529'), array($surface['light'], $surface['dark']), 'light and dark values');
	pg_assert_same('--pg-var-surface', $surface['token'], 'its token');
	pg_assert_false($surface['custom'], 'built-in');
	$sand = style_guide_test_row($rows, 'key', 'sand');
	pg_assert_same('Sand', $sand['name'], 'own colour keeps its name');
	pg_assert_same('--pg-var-c-sand', $sand['token'], 'own colour token');
	pg_assert_true($sand['custom'], 'marked as the designer\'s own');
}

function test_style_guide_contrast_pairs_measure_each_text_on_each_surface_in_both_modes()
{
	$d = pg_design_variables_defaults('', '');
	$pairs = pg_style_guide_contrast_pairs($d);
	$text = style_guide_test_row(array_values(array_filter($pairs, function ($p) { return $p['mode'] === 'light'; })), 'text', 'text');
	pg_assert_same('surface', $text['surface'], 'text measured on the surface');
	pg_assert_same(15.43, $text['ratio'], 'Bootstrap body text on white');
	pg_assert_true($text['ok'], 'passes 4.5:1');
	pg_assert_true($text['large_ok'], 'passes 3:1');

	$label = null;
	foreach ($pairs as $p) {
		if ($p['text'] === 'surface' && $p['surface'] === 'brand' && $p['mode'] === 'light') $label = $p;
	}
	pg_assert_true($label !== null, 'a label on the brand colour is measured');
	pg_assert_same(4.5, $label['ratio'], 'white on Bootstrap blue');

	$warning = null;
	foreach ($pairs as $p) {
		if ($p['text'] === 'warning' && $p['surface'] === 'surface' && $p['mode'] === 'light') $warning = $p;
	}
	pg_assert_false($warning['ok'], 'yellow text on white fails');
	pg_assert_false($warning['large_ok'], 'even as large text');

	$modes = array_unique(array_map(function ($p) { return $p['mode']; }, $pairs));
	sort($modes);
	pg_assert_same(array('dark', 'light'), $modes, 'both colour modes');
}

function test_style_guide_contrast_pairs_take_the_designers_own_colours_as_text()
{
	$d = pg_design_variables_defaults('', '');
	$v = $d;
	$v['custom'] = array(array('key' => 'ink', 'kind' => 'color', 'light' => '#000000', 'dark' => '#ffffff'));
	$v = pg_design_variables_normalize($v, $d);
	$hit = null;
	foreach (pg_style_guide_contrast_pairs($v) as $p) {
		if ($p['text'] === 'ink' && $p['mode'] === 'light') $hit = $p;
	}
	pg_assert_same(21.0, (float)$hit['ratio'], 'black on white');
	pg_assert_true($hit['custom'], 'marked as the designer\'s own');
}

function test_style_guide_type_has_the_fonts_and_the_built_in_styles_at_their_sizes()
{
	$d = pg_design_variables_defaults('', '');
	$type = pg_style_guide_model($d, $d, '', array())['blocks']['type'];
	pg_assert_same(array('font_heading', 'font_body'), array_map(function ($r) { return $r['key']; }, $type['fonts']), 'the two fonts');
	$keys = array_map(function ($r) { return $r['key']; }, $type['styles']);
	pg_assert_same(array('display-1', 'display-2', 'display-3', 'display-4', 'display-5', 'display-6', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'lead', 'body', 'small'), $keys, 'built-in styles, largest first');
	$h1 = style_guide_test_row($type['styles'], 'key', 'h1');
	pg_assert_same('2.5rem', $h1['size'], 'h1 size');
	pg_assert_same(40, $h1['px'], 'h1 in pixels');
	pg_assert_same('font_heading', $h1['font'], 'heading font variable');
	pg_assert_same('weight_heading', $h1['weight_var'], 'heading weight variable');
	pg_assert_same('', $h1['source'], 'not from the stylesheet');
	$body = style_guide_test_row($type['styles'], 'key', 'body');
	pg_assert_same('font_body', $body['font'], 'text font variable');
	pg_assert_same('size_base', $body['size_var'], 'text size is the base size');
}

function test_style_guide_text_follows_the_base_size()
{
	$d = pg_design_variables_defaults('', '');
	$v = $d;
	$v['values']['size_base'] = '18px';
	$v = pg_design_variables_normalize($v, $d);
	$styles = pg_style_guide_text_styles('', $v);
	pg_assert_same(18, style_guide_test_row($styles, 'key', 'body')['px'], 'body at the base size');
	pg_assert_same('18px', style_guide_test_row($styles, 'key', 'body')['size'], 'written as given');
	pg_assert_same(15.75, style_guide_test_row($styles, 'key', 'small')['px'], 'small is 0.875 of the text');
	pg_assert_same(40, style_guide_test_row($styles, 'key', 'h1')['px'], 'headings stay on the root size');
}

function test_style_guide_text_styles_read_the_stylesheet()
{
	$d = pg_design_variables_defaults('', '');
	$css = "/* h1 { font-size: 9rem } */\n"
		. "h1, .h1 { font-size: 3rem; font-weight: bold; line-height: 1.1; }\n"
		. "h2 { font-size: calc(1.3rem + .6vw); font-family: 'Lora', serif; }\n"
		. "@media (max-width: 600px) { h3 { font-size: 1rem; } }\n"
		. ".intro-text { font-size: 1.125rem; font-weight: 300; }\n"
		. ".card-title { color: red; }\n"
		. "div.x .y { font-size: 2rem; }\n"
		. ".evil { font-size: 2rem; font-family: url(x);} body{display:none }\n";
	$styles = pg_style_guide_text_styles($css, $d);
	$h1 = style_guide_test_row($styles, 'key', 'h1');
	pg_assert_same('3rem', $h1['size'], 'stylesheet size wins');
	pg_assert_same(48, $h1['px'], 'in pixels');
	pg_assert_same('700', $h1['weight'], 'bold is 700');
	pg_assert_same('1.1', $h1['line_height'], 'line height read');
	pg_assert_same('stylesheet', $h1['source'], 'marked as the stylesheet\'s');
	$h2 = style_guide_test_row($styles, 'key', 'h2');
	pg_assert_same('2rem', $h2['size'], 'a calc() size keeps the default');
	pg_assert_same('\'Lora\', serif', $h2['family'], 'font family read');
	pg_assert_same('1.75rem', style_guide_test_row($styles, 'key', 'h3')['size'], 'a rule inside @media is left out');
	$intro = style_guide_test_row($styles, 'key', 'intro-text');
	pg_assert_true($intro !== null, 'a class with a font size is a text style');
	pg_assert_same('.intro-text', $intro['name'], 'named by its class');
	pg_assert_same(18, $intro['px'], 'its size');
	pg_assert_same('300', $intro['weight'], 'its weight');
	pg_assert_same(null, style_guide_test_row($styles, 'key', 'card-title'), 'a class without a size is not');
	pg_assert_same(null, style_guide_test_row($styles, 'key', 'y'), 'a compound selector is not');
	$evil = style_guide_test_row($styles, 'key', 'evil');
	pg_assert_same('', $evil['family'], 'a refused font family is left to the variable');
}

function test_style_guide_text_styles_keep_at_most_twelve_own_classes()
{
	$d = pg_design_variables_defaults('', '');
	$css = '';
	for ($i = 1; $i <= 20; $i++) $css .= '.t' . $i . ' { font-size: ' . $i . 'px; }';
	$own = array_filter(pg_style_guide_text_styles($css, $d), function ($r) { return $r['source'] === 'class'; });
	pg_assert_same(12, count($own), 'twelve own classes');
}

function test_style_guide_type_scale_steps_follow_base_and_scale()
{
	$d = pg_design_variables_defaults('', '');
	$v = $d;
	$v['values']['scale'] = '1.5';
	$v = pg_design_variables_normalize($v, $d);
	$scale = pg_style_guide_model($v, $d, '', array())['blocks']['type']['scale'];
	pg_assert_same(array('XS', 'S', 'Base', 'L', 'XL', '2XL', '3XL', '4XL', '5XL'), array_map(function ($r) { return $r['name']; }, $scale), 'the steps');
	pg_assert_same(16, style_guide_test_row($scale, 'name', 'Base')['px'], 'base');
	pg_assert_same(24, style_guide_test_row($scale, 'name', 'L')['px'], 'one step up');
	pg_assert_same(7, style_guide_test_row($scale, 'name', 'XS')['px'], 'two steps down, whole pixels');
}

function test_style_guide_spacing_is_the_units_and_the_section_rhythm()
{
	$d = pg_design_variables_defaults('', '');
	$v = $d;
	$v['values']['space_unit'] = '8px';
	$v['custom'] = array(array('key' => 'gutter', 'kind' => 'size', 'value' => '24px'));
	$v = pg_design_variables_normalize($v, $d);
	$rows = pg_style_guide_model($v, $d, '', array())['blocks']['spacing']['rows'];
	pg_assert_same(array(8, 16, 24, 32, 48, 64), array_map(function ($r) { return $r['px']; }, array_values(array_filter($rows, function ($r) { return $r['key'] === 'space_unit'; }))), 'the unit and its multiples');
	$section = style_guide_test_row($rows, 'key', 'space_section');
	pg_assert_same(64, $section['px'], 'section rhythm 4rem');
	$gutter = style_guide_test_row($rows, 'key', 'gutter');
	pg_assert_same(24, $gutter['px'], 'own size');
	pg_assert_true($gutter['custom'], 'own size marked');
}

function test_style_guide_shape_names_the_shadow()
{
	$d = pg_design_variables_defaults('', '');
	$rows = pg_style_guide_model($d, $d, '', array())['blocks']['shape']['rows'];
	pg_assert_same(array('radius', 'border_width', 'shadow'), array_map(function ($r) { return $r['key']; }, $rows), 'corners, line width, shadow');
	pg_assert_same(lang('As the look'), style_guide_test_row($rows, 'key', 'shadow')['label'], 'shadow shown by its label');
}

function test_style_guide_controls_have_their_states()
{
	$rows = pg_style_guide_controls();
	pg_assert_same(array('button-primary', 'button-secondary', 'button-outline', 'link', 'input', 'select', 'check'), array_map(function ($r) { return $r['key']; }, $rows), 'the controls');
	foreach ($rows as $r) {
		pg_assert_same('rest', $r['states'][0], $r['key'] . ' starts at rest');
		pg_assert_true(in_array('disabled', $r['states'], true), $r['key'] . ' has an off state');
	}
	pg_assert_same(array('rest', 'hover', 'focus', 'disabled'), style_guide_test_row($rows, 'key', 'button-primary')['states'], 'button states');
}

function test_style_guide_components_by_name_without_system_widgets()
{
	$rows = array(
		array('id' => '7', 'name' => 'Footer', 'system_region_config' => ''),
		array('id' => 3, 'name' => 'header', 'system_region_config' => null),
		array('id' => 9, 'name' => 'Cart', 'system_region_config' => '{"type":"cart"}'),
		array('id' => 0, 'name' => 'Broken'),
		array('id' => 4, 'name' => 'Banner'),
		'junk',
	);
	$out = pg_style_guide_components($rows);
	pg_assert_same(array(array('id' => 4, 'name' => 'Banner'), array('id' => 7, 'name' => 'Footer'), array('id' => 3, 'name' => 'header')), $out, 'named components, sorted by name, ids as numbers');
	pg_assert_same($out, pg_style_guide_model(null, pg_design_variables_defaults('', ''), '', $rows)['blocks']['components']['rows'], 'the model carries them');
}

function test_style_guide_model_reads_nothing_it_is_not_given()
{
	$d = pg_design_variables_defaults('', '');
	$a = pg_style_guide_model($d, $d, '', array());
	$b = pg_style_guide_model($d, $d, '', array());
	pg_assert_same($a, $b, 'the same input gives the same sheet');
	$bad = pg_style_guide_model(array('values' => array('brand' => 'nope')), $d, null, null);
	pg_assert_same('#0d6efd', style_guide_test_row($bad['blocks']['colours']['rows'], 'key', 'brand')['light'], 'a refused value takes its default');
	pg_assert_same(array(), $bad['blocks']['components']['rows'], 'no components');
}

function test_style_guide_component_pages_count_each_page_once_and_skip_the_bin()
{
	$rows = array(array('id' => 3, 'name' => 'Footer'), array('id' => 7, 'name' => 'Header'), array('id' => 9, 'name' => 'Promo'));
	$usage = array(
		3 => array(
			array('page_id' => 10, 'style_id' => 1),
			array('page_id' => 10, 'style_id' => 1),
			array('page_id' => 11, 'style_id' => 1),
			array('page_id' => 12, 'style_id' => 2, 'binned' => true),
		),
		7 => array(array('page_id' => 0, 'style_id' => 4), array('page_id' => 0, 'style_id' => 5)),
	);
	$out = pg_style_guide_component_pages($rows, $usage);
	pg_assert_same(2, $out[0]['pages'], 'a page placing it twice counts once; a binned page not at all');
	pg_assert_same(2, $out[1]['pages'], 'an older database counts designs');
	pg_assert_same(0, $out[2]['pages'], 'not placed');
	pg_assert_same('Promo', $out[2]['name'], 'the row is kept');
}

function test_style_guide_names_carry_their_number()
{
	$d = pg_design_variables_defaults('', '');
	$m = pg_style_guide_model($d, $d, '', array());
	$h2 = style_guide_test_row($m['blocks']['type']['styles'], 'key', 'h2');
	$d3 = style_guide_test_row($m['blocks']['type']['styles'], 'key', 'display-3');
	pg_assert_same('Heading 2', $h2['name'], 'a heading\'s name has its number');
	pg_assert_same('Display 3', $d3['name'], 'a display style\'s name has its number');
	pg_assert_same('1 unit', $m['blocks']['spacing']['rows'][0]['name'], 'one unit');
	pg_assert_same('4 units', $m['blocks']['spacing']['rows'][3]['name'], 'four units');
}
