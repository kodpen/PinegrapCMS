<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Figma converter in includes/fn/figma_import.php: a frame of
 * a stored GET /v1/files/:key/nodes answer turned into a designer tree, with
 * auto layout as flex and grid, text as headings and paragraphs with their
 * style and runs, the pictures, instances, colours, text styles and bound
 * variables listed for the later import steps, and the report of what was
 * not carried.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function figma_test_answer($name)
{
	$path = dirname(__FILE__) . '/fixtures/figma/' . $name . '.json';
	return json_decode(file_get_contents($path), true);
}

function figma_test_convert($name, $id)
{
	$frame = pg_figma_answer_frame(figma_test_answer($name), $id);
	return pg_figma_convert_frame($frame['document'], $frame['styles'], $frame['components']);
}

// The node of the tree whose customName is $name, depth first; null if none.
function figma_test_find($node, $name)
{
	if (isset($node['props']['customName']) && $node['props']['customName'] === $name) {
		return $node;
	}
	foreach (isset($node['children']) ? $node['children'] : array() as $child) {
		$found = figma_test_find($child, $name);
		if ($found !== null) {
			return $found;
		}
	}
	return null;
}

function figma_test_style($node)
{
	return isset($node['props']['inlineStyle']) ? $node['props']['inlineStyle'] : '';
}

function test_figma_answer_frame_reads_both_id_forms()
{
	$answer = figma_test_answer('landing_nodes');
	$a = pg_figma_answer_frame($answer, '12:3');
	$b = pg_figma_answer_frame($answer, '12-3');
	pg_assert_same('Home / Desktop', $a['document']['name']);
	pg_assert_same($a, $b, 'the link form 12-3 is the API form 12:3');
	pg_assert_same('Brand/Primary', $a['styles']['S:brand01']['name']);
	pg_assert_same(null, pg_figma_answer_frame($answer, '99:1'), 'missing id');
	pg_assert_same(null, pg_figma_answer_frame(array('nodes' => array('1:2' => null)), '1:2'), 'null node');
	pg_assert_same(null, pg_figma_answer_frame('not an answer', '1:2'), 'not an array');
}

function test_figma_frame_becomes_root_with_section()
{
	$r = figma_test_convert('landing_nodes', '12:3');
	pg_assert_same('Home / Desktop', $r['name']);
	pg_assert_same('root', $r['tree']['type']);
	pg_assert_same(1, count($r['tree']['children']));
	$frame = $r['tree']['children'][0];
	pg_assert_same('semantic', $frame['type']);
	pg_assert_same('section', $frame['props']['tag']);
	pg_assert_same('Home / Desktop', $frame['props']['customName']);
	$style = figma_test_style($frame);
	pg_assert_contains('display:flex;flex-direction:column', $style);
	pg_assert_contains('gap:48px', $style);
	pg_assert_contains('padding:64px 80px 64px 80px', $style);
	pg_assert_contains('align-items:center', $style);
	pg_assert_contains('background-color:#ffffff', $style);
	pg_assert_contains('overflow:hidden', $style);
	pg_assert_contains('width:100%;max-width:1440px;margin:0 auto', $style, 'the frame is the page width, centred');
}

function test_figma_every_node_gets_an_id_once()
{
	$r = figma_test_convert('landing_nodes', '12:3');
	$ids = array();
	$walk = function ($n) use (&$walk, &$ids) {
		$ids[] = $n['_id'];
		foreach ($n['children'] as $c) {
			$walk($c);
		}
	};
	$walk($r['tree']);
	pg_assert_same('sd_1', $ids[0]);
	pg_assert_same(count($ids), count(array_unique($ids)));
}

function test_figma_heading_from_text_style_name()
{
	$r = figma_test_convert('landing_nodes', '12:3');
	$h = figma_test_find($r['tree'], 'Hero title');
	pg_assert_same('content', $h['type']);
	pg_assert_same('heading', $h['props']['contentType']);
	pg_assert_same('h1', $h['props']['tag']);
	pg_assert_same('Grow your shop &lt;faster&gt;<br>with Pinegrap', $h['props']['text'], 'escaped, line break kept');
	pg_assert_same('Inter', $h['props']['fontFamily']);
	$style = figma_test_style($h);
	pg_assert_contains('margin:0', $style);
	pg_assert_contains('font-size:56px', $style);
	pg_assert_contains('font-weight:700', $style);
	pg_assert_contains('line-height:64px', $style);
	pg_assert_contains('letter-spacing:-1.12px', $style);
	pg_assert_contains('text-align:center', $style);
	pg_assert_contains('color:#101828', $style);

	$h3 = figma_test_find($r['tree'], 'Card title');
	pg_assert_same('h3', $h3['props']['tag']);
	pg_assert_same('Playfair Display', $h3['props']['fontFamily']);
	pg_assert_contains('font-style:italic', figma_test_style($h3));
}

function test_figma_paragraph_with_bold_link_run()
{
	$r = figma_test_convert('landing_nodes', '12:3');
	$p = figma_test_find($r['tree'], 'Lead');
	pg_assert_same('paragraph', $p['props']['contentType']);
	pg_assert_same(
		'Pages, products and orders in <a href="https://example.com/place" style="font-weight:700">one place</a>.',
		$p['props']['text']
	);
	$style = figma_test_style($p);
	pg_assert_contains('line-height:1.4', $style, 'a percent of the font size is a unitless line height');
	pg_assert_contains('color:rgba(51,51,51,0.8)', $style);
	pg_assert_contains('width:640px', $style, 'fixed width in a column');
}

function test_figma_text_case_and_decoration()
{
	$r = figma_test_convert('landing_nodes', '12:3');
	$second = figma_test_find($r['tree'], 'Actions')['children'][1];
	$label = $second['children'][0];
	$style = figma_test_style($label);
	pg_assert_contains('text-transform:uppercase', $style);
	pg_assert_contains('text-decoration:underline', $style);
	pg_assert_same('See a demo', $label['props']['text']);
}

function test_figma_row_and_wrap()
{
	$r = figma_test_convert('landing_nodes', '12:3');
	$actions = figma_test_find($r['tree'], 'Actions');
	$style = figma_test_style($actions);
	pg_assert_contains('display:flex;flex-direction:row', $style);
	pg_assert_contains('justify-content:center', $style);
	pg_assert_contains('gap:16px', $style);
	pg_assert_same('div', $actions['props']['tag']);

	$cards = figma_test_find($r['tree'], 'Cards');
	$style = figma_test_style($cards);
	pg_assert_contains('flex-wrap:wrap', $style);
	pg_assert_contains('gap:32px 24px', $style, 'row gap from the counter axis spacing');
	pg_assert_contains('justify-content:space-between', $style);
	pg_assert_contains('align-self:stretch', $style, 'fills the column parent across');
	$card = figma_test_find($r['tree'], 'Card');
	$style = figma_test_style($card);
	pg_assert_contains('flex:1 1 0;min-width:0', $style, 'fills the row parent along');
	pg_assert_contains('border-radius:16px', $style);
	pg_assert_contains('box-shadow:0 4px 12px 0 rgba(0,0,0,0.1)', $style);
}

function test_figma_button_instance_and_border()
{
	$r = figma_test_convert('landing_nodes', '12:3');
	$actions = figma_test_find($r['tree'], 'Actions');
	$first = $actions['children'][0];
	$second = $actions['children'][1];
	pg_assert_contains('background-color:#2952f5', figma_test_style($first));
	pg_assert_contains('border-radius:8px', figma_test_style($first));
	pg_assert_contains('border:1px solid #2952f5', figma_test_style($second));
	pg_assert_contains('border-radius:8px', figma_test_style($second), 'four equal corner radii');

	pg_assert_same(2, count($r['instances']));
	pg_assert_same(array('node' => $first['_id'], 'figma' => '12:7', 'component_id' => '3:10', 'component_name' => 'Button'), $r['instances'][0]);
	pg_assert_same($second['_id'], $r['instances'][1]['node']);
}

function test_figma_hidden_layer_left_out_silently()
{
	$r = figma_test_convert('landing_nodes', '12:3');
	pg_assert_same(null, figma_test_find($r['tree'], 'Old card'));
	foreach ($r['report'] as $line) {
		pg_assert_true($line['figma'] !== '12:13', 'no report line for a hidden layer');
	}
}

function test_figma_image_fill_becomes_picture()
{
	$r = figma_test_convert('landing_nodes', '12:3');
	$photo = figma_test_find($r['tree'], 'Photo');
	pg_assert_same('image', $photo['props']['contentType']);
	pg_assert_same('', $photo['props']['src']);
	pg_assert_false(isset($photo['props']['alt']), 'no alt, so the audit asks for one');
	pg_assert_contains('height:200px', figma_test_style($photo));
	pg_assert_contains('align-self:stretch', figma_test_style($photo));
	pg_assert_contains('object-fit:cover', figma_test_style($photo));
	pg_assert_same(array(array('node' => $photo['_id'], 'figma' => '12:11', 'kind' => 'image', 'image_ref' => 'a1b2c3d4e5')), $r['pictures']);
}

function test_figma_colours_and_text_styles_listed_with_style_names()
{
	$r = figma_test_convert('landing_nodes', '12:3');
	$colours = array();
	foreach ($r['colors'] as $c) {
		$colours[$c['value']] = $c;
	}
	pg_assert_same('Brand/Primary', $colours['#2952f5']['style']);
	pg_assert_same(3, $colours['#2952f5']['count'], 'a styled fill, a stroke and a text colour');
	pg_assert_same('Text/Ink', $colours['#101828']['style']);
	pg_assert_same(2, $colours['#101828']['count']);
	pg_assert_same('Surface/Page', $colours['#ffffff']['style']);
	pg_assert_same('#2952f5', $r['colors'][0]['value'], 'most used first');

	$styles = array();
	foreach ($r['text_styles'] as $t) {
		$styles[$t['style']][] = $t;
	}
	pg_assert_same('Inter', $styles['Heading/H1'][0]['font_family']);
	pg_assert_same(56, $styles['Heading/H1'][0]['font_size']);
	pg_assert_same(700, $styles['Heading/H1'][0]['font_weight']);
	pg_assert_same('64px', $styles['Heading/H1'][0]['line_height']);
	pg_assert_same(1, count($styles['Button/Label']));
	pg_assert_same(2, count($styles['']), 'unnamed text styles kept apart by their values');
	pg_assert_same(array('Inter', 'Playfair Display'), $r['fonts']);
}

function test_figma_bound_variables_kept_for_later()
{
	$r = figma_test_convert('landing_nodes', '12:3');
	$first = figma_test_find($r['tree'], 'Actions')['children'][0];
	pg_assert_same(array(array('node' => $first['_id'], 'figma' => '12:7', 'field' => 'fills', 'variable_id' => 'VariableID:1:5')), $r['variables']);
}

function test_figma_report_names_what_is_not_carried()
{
	$r = figma_test_convert('landing_nodes', '12:3');
	pg_assert_same(array(array('figma' => '12:10', 'name' => 'Card', 'code' => 'effect')), $r['report']);

	$g = figma_test_convert('grid_absolute_nodes', '40:1');
	$codes = array();
	foreach ($g['report'] as $line) {
		$codes[$line['figma']] = $line['code'];
	}
	pg_assert_same(array('40:6' => 'rotation', '40:7' => 'type'), $codes);
}

function test_figma_grid_frame()
{
	$r = figma_test_convert('grid_absolute_nodes', '40:1');
	$frame = $r['tree']['children'][0];
	$style = figma_test_style($frame);
	pg_assert_contains('display:grid', $style);
	pg_assert_contains('grid-template-columns:repeat(3,minmax(0,1fr))', $style);
	pg_assert_contains('grid-template-rows:repeat(2,auto)', $style);
	pg_assert_contains('gap:30px 20px', $style);
	pg_assert_contains('background-image:linear-gradient(180deg,#ffffff 0%,#e6ebff 100%)', $style);
	pg_assert_contains('position:relative', $style, 'holds an absolute child');

	$tile = figma_test_find($r['tree'], 'Wide tile');
	pg_assert_contains('grid-column:span 2', figma_test_style($tile));
	pg_assert_contains('background-color:rgba(41,82,245,0.5)', figma_test_style($tile), 'paint opacity');

	$caption = figma_test_find($r['tree'], 'Caption');
	pg_assert_contains('text-align:right', figma_test_style($caption));
	pg_assert_false(strpos(figma_test_style($caption), 'line-height') !== false, 'intrinsic line height is left to the font');

	$floating = figma_test_find($r['tree'], 'Floating label');
	pg_assert_contains('position:absolute;left:1100px;top:760px', figma_test_style($floating));
}

function test_figma_free_frame_places_children_absolutely()
{
	$r = figma_test_convert('grid_absolute_nodes', '40:1');
	$tile = figma_test_find($r['tree'], 'Wide tile');
	pg_assert_contains('position:relative', figma_test_style($tile));
	pg_assert_contains('height:340px', figma_test_style($tile));
	$badge = figma_test_find($r['tree'], 'Badge');
	pg_assert_contains('position:absolute;left:660px;top:20px;width:40px;height:40px', figma_test_style($badge));
	pg_assert_contains('border-radius:50%', figma_test_style($badge));
}

function test_figma_icon_group_is_one_vector_picture()
{
	$r = figma_test_convert('grid_absolute_nodes', '40:1');
	$icon = figma_test_find($r['tree'], 'icon/star');
	pg_assert_same('image', $icon['props']['contentType']);
	pg_assert_same(array(), $icon['children']);
	pg_assert_contains('left:20px;top:20px;width:24px;height:24px', figma_test_style($icon));
	pg_assert_same(array(array('node' => $icon['_id'], 'figma' => '40:3', 'kind' => 'vector', 'image_ref' => '')), $r['pictures']);
}

function test_figma_node_limit_stops_and_reports_once()
{
	$children = array();
	for ($i = 0; $i < 30; $i++) {
		$children[] = array('id' => '1:' . ($i + 10), 'name' => 'Box', 'type' => 'RECTANGLE',
			'absoluteBoundingBox' => array('x' => 0, 'y' => 0, 'width' => 10, 'height' => 10));
	}
	$frame = array('id' => '1:2', 'name' => 'Many', 'type' => 'FRAME', 'layoutMode' => 'VERTICAL',
		'absoluteBoundingBox' => array('x' => 0, 'y' => 0, 'width' => 100, 'height' => 100), 'children' => $children);
	$r = pg_figma_convert_frame($frame, array(), array(), array('max_nodes' => 10));
	pg_assert_same(9, count($r['tree']['children'][0]['children']), 'the frame counts as one node');
	pg_assert_same(array(array('figma' => '1:19', 'name' => 'Box', 'code' => 'limit')), $r['report']);
}

function test_figma_unsafe_link_dropped_and_family_cleaned()
{
	$frame = array('id' => '1:2', 'name' => 'F', 'type' => 'FRAME', 'layoutMode' => 'VERTICAL',
		'absoluteBoundingBox' => array('x' => 0, 'y' => 0, 'width' => 100, 'height' => 100),
		'children' => array(
			array('id' => '1:3', 'name' => 'Bad', 'type' => 'TEXT', 'characters' => 'ab',
				'style' => array('fontFamily' => 'Inter', 'fontSize' => 16, 'hyperlink' => array('type' => 'URL', 'url' => 'javascript:alert(1)'))),
			array('id' => '1:4', 'name' => 'Good', 'type' => 'TEXT', 'characters' => 'cd',
				'style' => array('fontFamily' => 'Inter', 'fontSize' => 16, 'hyperlink' => array('type' => 'URL', 'url' => 'https://example.com/?a=1&b=2'))),
			array('id' => '1:5', 'name' => 'Run', 'type' => 'TEXT', 'characters' => "x\u{1F600}y",
				'style' => array('fontFamily' => 'Inter', 'fontSize' => 16),
				'characterStyleOverrides' => array(0, 0, 0, 4),
				'styleOverrideTable' => array('4' => array('fontFamily' => 'Evil";}<x>'))),
		));
	$r = pg_figma_convert_frame($frame);
	$kids = $r['tree']['children'][0]['children'];
	pg_assert_same('paragraph', $kids[0]['props']['contentType']);
	pg_assert_same('ab', $kids[0]['props']['text']);
	pg_assert_same('link', $kids[1]['props']['contentType']);
	pg_assert_same('https://example.com/?a=1&b=2', $kids[1]['props']['href']);
	pg_assert_same('cd', $kids[1]['props']['text']);
	pg_assert_same("x\u{1F600}<span style=\"font-family:'Evilx',sans-serif\">y</span>", $kids[2]['props']['text'], 'UTF-16 positions');
}

function test_figma_image_fill_on_a_box_is_a_background_picture()
{
	$frame = array('id' => '1:2', 'name' => 'F', 'type' => 'FRAME', 'layoutMode' => 'VERTICAL',
		'absoluteBoundingBox' => array('x' => 0, 'y' => 0, 'width' => 100, 'height' => 100),
		'children' => array(
			array('id' => '1:3', 'name' => 'Banner', 'type' => 'FRAME', 'layoutMode' => 'VERTICAL',
				'fills' => array(
					array('type' => 'SOLID', 'color' => array('r' => 0, 'g' => 0, 'b' => 0, 'a' => 1)),
					array('type' => 'IMAGE', 'scaleMode' => 'FIT', 'imageRef' => 'ff00'),
				),
				'children' => array(array('id' => '1:4', 'name' => 'T', 'type' => 'TEXT', 'characters' => 'Hi', 'style' => array('fontSize' => 12)))),
			array('id' => '1:5', 'name' => 'Glow', 'type' => 'RECTANGLE',
				'absoluteBoundingBox' => array('x' => 0, 'y' => 0, 'width' => 10, 'height' => 10),
				'fills' => array(array('type' => 'GRADIENT_RADIAL', 'gradientStops' => array()))),
		));
	$r = pg_figma_convert_frame($frame);
	$banner = $r['tree']['children'][0]['children'][0];
	pg_assert_contains('background-size:contain;background-position:center', figma_test_style($banner));
	pg_assert_same(array(array('node' => $banner['_id'], 'figma' => '1:3', 'kind' => 'background', 'image_ref' => 'ff00')), $r['pictures']);
	pg_assert_same(array(
		array('figma' => '1:3', 'name' => 'Banner', 'code' => 'fill'),
		array('figma' => '1:5', 'name' => 'Glow', 'code' => 'fill'),
	), $r['report'], 'stacked fills and a radial gradient');
}
