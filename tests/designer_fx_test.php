<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the interactions of visual editor nodes
 * (includes/fn/designer_fx.php): pg_fx_normalize() keeps the known fields
 * in range and drops what does not apply, pg_fx_attrs() writes the effect,
 * the trigger and the non-default values, the page writes them on content,
 * semantic and grid nodes, pg_fx_inject() adds the head style and the
 * script once. The editor's twins (_sdFxNormalize(), _sdFxAttrs()) and the
 * helpers of assets/js/pg_fx.src.js are run under Node; on a machine
 * without Node those halves are skipped.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The interactions both sides are compared on.
function _test_fx_cases()
{
	return array(
		'none' => null,
		'not an array' => 'fade',
		'unknown effect' => array('effect' => 'spin'),
		'bare fade' => array('effect' => 'fade'),
		'upper case' => array('effect' => 'FADE', 'trigger' => 'Load', 'easing' => 'LINEAR'),
		'slide with timing' => array('effect' => 'slide-up', 'trigger' => 'view', 'duration' => '900', 'delay' => 150, 'easing' => 'ease-in-out', 'stagger' => 80, 'distance' => 40, 'repeat' => true),
		'clamped' => array('effect' => 'slide-left', 'duration' => 1, 'delay' => 99999, 'stagger' => -5, 'distance' => 1000),
		'rounded' => array('effect' => 'scale', 'duration' => 650.5, 'delay' => '2.4'),
		'not numbers' => array('effect' => 'scale', 'duration' => 'fast', 'delay' => true, 'distance' => array(5), 'stagger' => ''),
		'unknown trigger and easing' => array('effect' => 'fade', 'trigger' => 'scroll', 'easing' => 'bounce'),
		'hover drops stagger and repeat' => array('effect' => 'slide-up', 'trigger' => 'hover', 'stagger' => 100, 'repeat' => true, 'offset' => 5),
		'click' => array('effect' => 'scale', 'trigger' => 'click', 'duration' => 300),
		'load drops repeat' => array('effect' => 'fade', 'trigger' => 'load', 'repeat' => 1, 'distance' => 50),
		'sticky' => array('effect' => 'sticky', 'offset' => 72, 'trigger' => 'view', 'duration' => 900),
		'sticky default' => array('effect' => 'sticky'),
		'parallax' => array('effect' => 'parallax', 'speed' => 35, 'trigger' => 'hover'),
		'parallax clamped' => array('effect' => 'parallax', 'speed' => 1),
		'scroll zoom' => array('effect' => 'scroll-zoom', 'from' => 60, 'trigger' => 'view', 'duration' => 900),
		'scroll zoom clamped' => array('effect' => 'scroll-zoom', 'from' => 1),
		'scroll zoom larger' => array('effect' => 'Scroll-Zoom', 'from' => '150'),
		'scroll fade' => array('effect' => 'scroll-fade'),
		'scroll fade rounded' => array('effect' => 'scroll-fade', 'from' => '30.5', 'speed' => 50),
		'scroll rise clamped' => array('effect' => 'scroll-rise', 'from' => 9999, 'distance' => 40),
		'scroll rise not a number' => array('effect' => 'scroll-rise', 'from' => 'far'),
		'from on an entrance' => array('effect' => 'fade', 'from' => 50),
		'count' => array('effect' => 'count'),
		'count with timing' => array('effect' => 'Count', 'duration' => '1500', 'delay' => 250, 'trigger' => 'hover', 'easing' => 'linear', 'from' => 5, 'repeat' => true, 'distance' => 9),
		'count clamped' => array('effect' => 'count', 'duration' => 99999, 'delay' => -1),
		'count at a transition\'s duration' => array('effect' => 'count', 'duration' => 600),
		'autoplay' => array('effect' => 'autoplay'),
		'autoplay drops every field' => array('effect' => 'AutoPlay', 'trigger' => 'view', 'duration' => 900, 'offset' => 10, 'from' => 50, 'repeat' => true),
		'chapter' => array('effect' => 'chapter'),
		'chapter with a duration' => array('effect' => 'Chapter', 'duration' => '1200', 'trigger' => 'view', 'delay' => 100, 'easing' => 'linear', 'from' => 5, 'repeat' => true),
		'chapter clamped' => array('effect' => 'chapter', 'duration' => 1),
		'scene' => array('effect' => 'scene'),
		'scene drops every field' => array('effect' => 'Scene', 'trigger' => 'view', 'duration' => 900, 'from' => 50, 'speed' => 30),
	);
}

function test_designer_fx_normalize()
{
	pg_assert_same(null, pg_fx_normalize(null), 'no interaction');
	pg_assert_same(null, pg_fx_normalize(array('effect' => 'spin')), 'an unknown effect');
	pg_assert_same(array('effect' => 'fade', 'trigger' => 'view', 'duration' => 600, 'delay' => 0, 'easing' => 'ease-out', 'stagger' => 0, 'repeat' => false), pg_fx_normalize(array('effect' => 'fade')), 'the defaults');
	pg_assert_same(array('effect' => 'slide-left', 'trigger' => 'view', 'duration' => 100, 'delay' => 5000, 'easing' => 'ease-out', 'distance' => 400, 'stagger' => 0, 'repeat' => false), pg_fx_normalize(array('effect' => 'slide-left', 'duration' => 1, 'delay' => 99999, 'stagger' => -5, 'distance' => 1000)), 'clamped');
	pg_assert_same(array('effect' => 'slide-up', 'trigger' => 'hover', 'duration' => 600, 'delay' => 0, 'easing' => 'ease-out', 'distance' => 0), pg_fx_normalize(array('effect' => 'slide-up', 'trigger' => 'hover', 'stagger' => 100, 'repeat' => true, 'offset' => 5)), 'hover keeps no stagger, repeat or offset');
	pg_assert_same(array('effect' => 'sticky', 'offset' => 72), pg_fx_normalize(array('effect' => 'sticky', 'offset' => 72, 'trigger' => 'view')), 'sticky has no trigger');
	pg_assert_same(array('effect' => 'parallax', 'speed' => 5), pg_fx_normalize(array('effect' => 'parallax', 'speed' => 1)), 'parallax speed clamped');
	pg_assert_same(651, pg_fx_normalize(array('effect' => 'scale', 'duration' => 650.5))['duration'], 'a half rounds up');
	pg_assert_same(array('effect' => 'autoplay'), pg_fx_normalize(array('effect' => 'autoplay', 'trigger' => 'load', 'duration' => 900)), 'autoplay has no field');
	pg_assert_same(' data-pg-fx="autoplay"', pg_fx_attrs(array('_fx' => array('effect' => 'autoplay'))), 'autoplay writes only its effect');
}

function test_designer_fx_attrs()
{
	pg_assert_same('', pg_fx_attrs(array()), 'no interaction');
	pg_assert_same(' data-pg-fx="fade" data-pg-fx-trigger="view"', pg_fx_attrs(array('_fx' => array('effect' => 'fade'))), 'the defaults are not written');
	pg_assert_same(' data-pg-fx="slide-up" data-pg-fx-trigger="view" data-pg-fx-duration="900" data-pg-fx-delay="150" data-pg-fx-easing="ease-in-out" data-pg-fx-stagger="80" data-pg-fx-distance="40" data-pg-fx-repeat',
		pg_fx_attrs(array('_fx' => _test_fx_cases()['slide with timing'])), 'every value in order');
	pg_assert_same(' data-pg-fx="sticky" data-pg-fx-offset="72"', pg_fx_attrs(array('_fx' => array('effect' => 'sticky', 'offset' => 72))), 'sticky');
	pg_assert_same(' data-pg-fx="parallax"', pg_fx_attrs(array('_fx' => array('effect' => 'parallax', 'speed' => 20))), 'parallax at its default speed');
	pg_assert_true((bool) preg_match(pg_fx_pattern(), pg_fx_attrs(array('_fx' => array('effect' => 'fade')))), 'the marker is found');
	pg_assert_false((bool) preg_match(pg_fx_pattern(), '<div data-pg-fx-trigger="view">'), 'a longer name is no marker');
	pg_assert_false((bool) preg_match(pg_fx_pattern(), '<p>data-pg-fx</p>'), 'the word as text');
}

// The scroll-linked effects keep only their start (`from`, in the effect's
// own range and unit) and write no trigger, so the head style never hides
// them: without the script, or under reduced motion, the node shows as it is.
function test_designer_fx_scroll_effects()
{
	pg_assert_same(array('effect' => 'scroll-zoom', 'from' => 80), pg_fx_normalize(array('effect' => 'scroll-zoom')), 'zoom starts at 80 %');
	pg_assert_same(array('effect' => 'scroll-fade', 'from' => 0), pg_fx_normalize(array('effect' => 'scroll-fade')), 'fade starts unseen');
	pg_assert_same(array('effect' => 'scroll-rise', 'from' => 80), pg_fx_normalize(array('effect' => 'scroll-rise')), 'rise starts 80 px lower');
	pg_assert_same(array('effect' => 'scroll-zoom', 'from' => 60), pg_fx_normalize(_test_fx_cases()['scroll zoom']), 'no trigger or timing');
	pg_assert_same(25, pg_fx_normalize(array('effect' => 'scroll-zoom', 'from' => 1))['from'], 'zoom from at least 25 %');
	pg_assert_same(200, pg_fx_normalize(array('effect' => 'scroll-zoom', 'from' => 900))['from'], 'zoom from at most 200 %');
	pg_assert_same(100, pg_fx_normalize(array('effect' => 'scroll-fade', 'from' => 101))['from'], 'fade from at most 100 %');
	pg_assert_same(31, pg_fx_normalize(array('effect' => 'scroll-fade', 'from' => '30.5'))['from'], 'a half rounds up');
	pg_assert_same(array('effect' => 'scroll-rise', 'from' => 400), pg_fx_normalize(array('effect' => 'scroll-rise', 'from' => 9999, 'distance' => 40)), 'rise from at most 400 px, no distance');
	pg_assert_false(array_key_exists('from', pg_fx_normalize(array('effect' => 'fade', 'from' => 50))), 'an entrance has no from');
	pg_assert_false(array_key_exists('from', pg_fx_normalize(array('effect' => 'parallax', 'from' => 50))), 'parallax has no from');

	pg_assert_same(' data-pg-fx="scroll-zoom"', pg_fx_attrs(array('_fx' => array('effect' => 'scroll-zoom', 'from' => 80))), 'the default start is not written');
	pg_assert_same(' data-pg-fx="scroll-zoom" data-pg-fx-from="60"', pg_fx_attrs(array('_fx' => _test_fx_cases()['scroll zoom'])));
	pg_assert_same(' data-pg-fx="scroll-fade" data-pg-fx-from="20"', pg_fx_attrs(array('_fx' => array('effect' => 'scroll-fade', 'from' => 20))));
	pg_assert_same(' data-pg-fx="scroll-rise" data-pg-fx-from="0"', pg_fx_attrs(array('_fx' => array('effect' => 'scroll-rise', 'from' => 0))), 'zero differs from the rise default');
	pg_assert_same(' data-pg-fx="fade" data-pg-fx-trigger="view"', pg_fx_attrs(array('_fx' => array('effect' => 'fade', 'from' => 50))), 'no from on an entrance');
	pg_assert_true(in_array('scroll-zoom', pg_fx_effects(), true) && in_array('scroll-fade', pg_fx_effects(), true) && in_array('scroll-rise', pg_fx_effects(), true), 'the effects are known');
	pg_assert_true(strpos(pg_fx_head_style(), 'scroll') === false, 'the head style hides no scroll effect');
}

// The count effect keeps its duration (a count runs longer than a
// transition: 2000 ms by default) and its delay, nothing else, and writes
// no trigger, so the head style never hides the number: without the script,
// or under reduced motion, the page shows the number its markup holds.
function test_designer_fx_count()
{
	pg_assert_same(array('effect' => 'count', 'duration' => 2000, 'delay' => 0), pg_fx_normalize(array('effect' => 'count')), 'the defaults');
	pg_assert_same(array('effect' => 'count', 'duration' => 1500, 'delay' => 250), pg_fx_normalize(array('effect' => 'Count', 'duration' => '1500', 'delay' => 250, 'trigger' => 'hover', 'easing' => 'linear', 'from' => 5, 'repeat' => true, 'distance' => 9)), 'no trigger, easing, start, repeat or distance');
	pg_assert_same(array('effect' => 'count', 'duration' => 5000, 'delay' => 0), pg_fx_normalize(array('effect' => 'count', 'duration' => 99999, 'delay' => -1)), 'clamped');
	pg_assert_same(' data-pg-fx="count"', pg_fx_attrs(array('_fx' => array('effect' => 'count', 'duration' => 2000))), 'the default duration is not written');
	pg_assert_same(' data-pg-fx="count" data-pg-fx-duration="600"', pg_fx_attrs(array('_fx' => array('effect' => 'count', 'duration' => 600))), 'a transition\'s default is a count\'s own value');
	pg_assert_same(' data-pg-fx="count" data-pg-fx-duration="1500" data-pg-fx-delay="250"', pg_fx_attrs(array('_fx' => array('effect' => 'count', 'duration' => 1500, 'delay' => 250, 'trigger' => 'hover'))));
	pg_assert_same(' data-pg-fx="fade" data-pg-fx-trigger="view"', pg_fx_attrs(array('_fx' => array('effect' => 'fade', 'duration' => 600))), 'an entrance keeps its own default');
	pg_assert_true(in_array('count', pg_fx_effects(), true), 'the effect is known');
	pg_assert_true(strpos(pg_fx_head_style(), 'count') === false, 'the head style hides no number');
}

// The chapter effect keeps its duration (the colour change's length) and
// nothing else, and writes no trigger, so the head style never hides the
// section: without the script the page keeps its own colours.
function test_designer_fx_chapter()
{
	pg_assert_same(array('effect' => 'chapter', 'duration' => 600), pg_fx_normalize(array('effect' => 'chapter')), 'the default');
	pg_assert_same(array('effect' => 'chapter', 'duration' => 1200), pg_fx_normalize(_test_fx_cases()['chapter with a duration']), 'no trigger, delay, easing, start or repeat');
	pg_assert_same(100, pg_fx_normalize(array('effect' => 'chapter', 'duration' => 1))['duration'], 'clamped');
	pg_assert_same(' data-pg-fx="chapter"', pg_fx_attrs(array('_fx' => array('effect' => 'chapter'))), 'the default duration is not written');
	pg_assert_same(' data-pg-fx="chapter" data-pg-fx-duration="1200"', pg_fx_attrs(array('_fx' => array('effect' => 'chapter', 'duration' => 1200, 'delay' => 300))));
	pg_assert_true(in_array('chapter', pg_fx_effects(), true), 'the effect is known');
	pg_assert_true(strpos(pg_fx_head_style(), 'chapter') === false, 'the head style hides no chapter');
}

// The scene effect has no field and writes no trigger: the head style never
// hides the section, and without the script its layers stand one under the
// other.
function test_designer_fx_scene()
{
	pg_assert_same(array('effect' => 'scene'), pg_fx_normalize(array('effect' => 'Scene', 'trigger' => 'view', 'duration' => 900, 'from' => 50)), 'no field');
	pg_assert_same(' data-pg-fx="scene"', pg_fx_attrs(array('_fx' => array('effect' => 'scene', 'speed' => 30))), 'the effect alone');
	pg_assert_true(in_array('scene', pg_fx_effects(), true), 'the effect is known');
	pg_assert_true(strpos(pg_fx_head_style(), 'scene') === false, 'the head style hides no scene');
}

// The first number of a text as its figures show it, and the text at a
// point of the count: zero at the start, eased (0.875 of the number half
// way), the text itself at the end, with the group and decimal separators,
// the decimals and the words around the number kept.
function test_designer_fx_count_numbers()
{
	$texts = array(
		'grouped with points' => '10.000+',
		'decimal comma, prefix' => '%99,9',
		'both separators' => '$1,234.56',
		'grouped, a suffix' => "1.250.000 \u{20BA}",
		'lakh groups' => '12,50,000',
		'decimal point, a suffix' => '4.9/5',
		'below one' => '0.125',
		'no-break space groups' => "1\u{00A0}250 km",
		'the first number' => '24/7',
		'words before' => 'Since 2010',
		'leading zeros' => '007',
		'one comma, two digits' => '1,25',
		'a version' => '1.2.3',
		'two decimal marks' => '1,234.5,6',
		'no number' => 'Many',
	);
	$script = 'var window = {};' . (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/pg_fx.src.js')
		. 'var fx = window.pgFx, texts = ' . json_encode($texts) . ', out = {};'
		. 'Object.keys(texts).forEach(function (k) { var n = fx.number(texts[k]);'
		. ' out[k] = n ? [fx.countText(n, 0), fx.countText(n, 0.5), fx.countText(n, 1), fx.countText(n, 2)] : null; });'
		. 'console.log(JSON.stringify(out));';
	$got = _test_fx_node($script);
	if ($got === null) {
		return;
	}

	pg_assert_same(array('0+', '8.750+', '10.000+', '10.000+'), $got['grouped with points']);
	pg_assert_same(array('%0,0', '%87,4', '%99,9', '%99,9'), $got['decimal comma, prefix']);
	pg_assert_same(array('$0.00', '$1,080.24', '$1,234.56', '$1,234.56'), $got['both separators']);
	pg_assert_same(array("0 \u{20BA}", "1.093.750 \u{20BA}", "1.250.000 \u{20BA}", "1.250.000 \u{20BA}"), $got['grouped, a suffix']);
	pg_assert_same(array('0', '10,93,750', '12,50,000', '12,50,000'), $got['lakh groups'], 'groups of two above the last three');
	pg_assert_same(array('0.0/5', '4.3/5', '4.9/5', '4.9/5'), $got['decimal point, a suffix']);
	pg_assert_same(array('0.000', '0.109', '0.125', '0.125'), $got['below one'], 'a zero before the point makes it a decimal');
	pg_assert_same(array('0 km', "1\u{00A0}094 km", "1\u{00A0}250 km", "1\u{00A0}250 km"), $got['no-break space groups']);
	pg_assert_same(array('0/7', '21/7', '24/7', '24/7'), $got['the first number']);
	pg_assert_same(array('Since 0', 'Since 1759', 'Since 2010', 'Since 2010'), $got['words before']);
	pg_assert_same(array('000', '006', '007', '007'), $got['leading zeros'], 'its width kept');
	pg_assert_same(array('0,00', '1,09', '1,25', '1,25'), $got['one comma, two digits'], 'not three digits after it: a decimal');
	pg_assert_same(null, $got['a version'], 'separators that make no number: no count');
	pg_assert_same(null, $got['two decimal marks']);
	pg_assert_same(null, $got['no number']);
}

// Content, semantic and grid nodes carry the interaction after the title;
// a copy of its names typed into the Attributes panel is left out.
function test_designer_fx_rendered_on_nodes()
{
	$fx = array('effect' => 'fade', 'trigger' => 'load');
	$panel = array(array('name' => 'data-pg-fx', 'value' => 'typed'), array('name' => 'data-k', 'value' => 'v'));
	$html = _render_tree_node(array('type' => 'content', 'props' => array('contentType' => 'heading', 'tag' => 'h2', 'text' => 'H', 'title' => 'T', '_fx' => $fx, '_attrs' => $panel), 'children' => array()), 0, 0);
	pg_assert_contains('<h2 title="T" data-pg-fx="fade" data-pg-fx-trigger="load" data-k="v"', $html);
	pg_assert_same(1, substr_count($html, 'data-pg-fx="'), 'written once');

	$html = _render_tree_node(array('type' => 'semantic', 'props' => array('tag' => 'section', '_fx' => $fx), 'children' => array()), 0, 0);
	pg_assert_contains('<section data-pg-fx="fade" data-pg-fx-trigger="load"', $html);

	$html = _render_tree_node(array('type' => 'content', 'props' => array('contentType' => 'paragraph', 'text' => 'P', '_attrs' => $panel), 'children' => array()), 0, 0);
	pg_assert_contains('data-pg-fx="typed"', $html, 'without an interaction the panel entry stays');

	pg_assert_contains('data-pg-fx="fade"', _pg_grid_open_tag('row', array('_fx' => $fx), array('class' => '', 'style' => '')), 'a grid node');
}

// A component carries its interaction on the element that takes its id,
// as the editor's HTML does (_sdFxIntoFirstTag()); a copy typed into the
// Attributes panel is left out; the language switcher's placeholder has none.
function test_designer_fx_rendered_on_components()
{
	$fx = array('effect' => 'slide-up', 'trigger' => 'view', 'duration' => 900);
	$attrs = pg_fx_attrs(array('_fx' => $fx));
	$panel = array(array('name' => 'data-pg-fx', 'value' => 'typed'), array('name' => 'data-k', 'value' => 'v'));
	$c = function ($type, $props = array()) use ($fx, $panel) {
		return _render_tree_node(array('type' => 'component', 'props' => array('componentType' => $type, 'id' => 'n1', '_fx' => $fx, '_attrs' => $panel) + $props, 'children' => array()), 0, 0);
	};

	foreach (array(
		'btn'       => '<a href="#" class="btn btn-primary" id="n1"' . $attrs . ' data-k="v">',
		'badge'     => '<span class="badge bg-primary" id="n1"' . $attrs . ' data-k="v">',
		'card'      => '<div class="card" id="n1"' . $attrs . ' data-k="v">',
		'alert_box' => $attrs,
		'hero'      => $attrs,
		'navbar'    => $attrs,
		'accordion' => $attrs,
		'tabs'      => $attrs,
		'carousel'  => $attrs,
	) as $type => $expected) {
		$html = $c($type);
		pg_assert_contains($expected, $html, $type);
		pg_assert_same(1, substr_count($html, 'data-pg-fx="'), $type . ': written once');
		pg_assert_false(strpos($html, 'typed') !== false, $type . ': the typed copy is left out');
	}
	pg_assert_contains('<button type="button" class="btn btn-primary" id="n1"' . $attrs, $c('btn', array('btnElement' => 'button')));
	pg_assert_contains('<input type="button" value="Button" class="btn btn-primary" id="n1"' . $attrs, $c('btn', array('btnElement' => 'input')));
	// The interaction is on the component's outer element: the first tag.
	pg_assert_true(strpos($c('card'), 'data-pg-fx') < strpos($c('card'), '</div>'), 'on the outer element');

	$html = _render_tree_node(array('type' => 'component', 'props' => array('componentType' => 'btn', '_attrs' => $panel), 'children' => array()), 0, 0);
	pg_assert_contains('data-pg-fx="typed"', $html, 'without an interaction the panel entry stays');
}

// Nodes inside a widget or a shared component are drawn by the same paths
// and keep their interaction through the widget's finish pass; the page
// looks for the attributes once widgets and shared components are in.
function test_designer_fx_inside_widgets_and_shared_components()
{
	$fx = array('effect' => 'fade', 'trigger' => 'view');
	$tree = array('type' => 'root', 'props' => array(), 'children' => array(
		array('type' => 'semantic', 'props' => array('tag' => 'div', '_fx' => $fx), 'children' => array(
			array('type' => 'component', 'props' => array('componentType' => 'btn', '_fx' => array('effect' => 'scale', 'trigger' => 'hover')), 'children' => array()),
		)),
	));
	$scope = pg_sw_finish_open(array('sid' => 94, 'kind' => 'search_results', 'email' => false));
	try {
		$html = pg_sw_finish_widget_html(_render_tree_node($tree, 0, 0), $scope);
	} finally {
		pg_sw_finish_close();
	}
	pg_assert_contains('<div data-pg-fx="fade" data-pg-fx-trigger="view">', $html);
	pg_assert_contains('data-pg-fx="scale" data-pg-fx-trigger="hover"', $html, 'a component inside a widget');

	$page = (string) file_get_contents(PG_FUNCTIONS_DIR . '/get_page_content.php');
	$inject = strpos($page, '$content = pg_fx_inject($content);');
	pg_assert_true($inject !== false, 'the page injects the script');
	pg_assert_true(strpos($page, '$content = _expand_shared_refs($content);') < $inject, 'after the shared components');
	pg_assert_true(strpos($page, '$content = _expand_system_widgets($content, $mode, $email);') < $inject, 'after the widgets');
}

function test_designer_fx_inject()
{
	$src = '/pinegrap/assets/js/pg_fx.min.js?v=1';
	$tag = '<script src="' . $src . '" defer></script>' . "\n";

	$plain = '<html><head></head><body><p>x</p></body></html>';
	pg_assert_same($plain, pg_fx_inject($plain, $src), 'a page without the marker is left as it is');

	$page = '<html><head><title>t</title></head><body><p data-pg-fx="fade">x</p></body></html>';
	$once = pg_fx_inject($page, $src);
	pg_assert_same('<html><head><title>t</title>' . pg_fx_head_style() . '</head><body><p data-pg-fx="fade">x</p>' . $tag . '</body></html>', $once);
	pg_assert_same($once, pg_fx_inject($once, $src), 'a second call adds nothing');

	pg_assert_same(pg_fx_head_style() . '<p data-pg-fx="fade">x</p>' . $tag, pg_fx_inject('<p data-pg-fx="fade">x</p>', $src), 'a fragment');
	pg_assert_contains('src="/x.js?a=1&amp;b=2"', pg_fx_inject('<p data-pg-fx="fade">', '/x.js?a=1&b=2'), 'the address is escaped');

	$style = pg_fx_head_style();
	pg_assert_contains('prefers-reduced-motion:no-preference', $style, 'hidden only where motion is allowed');
	pg_assert_contains('[data-pg-fx-trigger="view"]:not(.pg-fx-armed)', $style);
	pg_assert_contains('pg-fx-failsafe 0s 3s forwards', $style, 'a script that never comes lets them show');
	pg_assert_contains('pg_fx_inject($content)', (string) file_get_contents(PG_FUNCTIONS_DIR . '/get_page_content.php'), 'the published page asks for it');
}

function test_designer_fx_preview_pattern()
{
	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');
	$found = preg_match("~\\{ test: (/[^\\n]*?/[a-z]*), file: 'assets/js/pg_fx\\.min\\.js' \\}~", $js, $m);
	pg_assert_true((bool) $found, 'the preview has a row for pg_fx.min.js');
	pg_assert_same(pg_fx_pattern(), $found ? $m[1] : '', 'the preview tests the same expression as the server');
}

// Runs $script under Node and returns its JSON output, null without Node.
function _test_fx_node($script)
{
	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);
	if ($code !== 0) {
		return null;
	}

	$file = tempnam(sys_get_temp_dir(), 'pgfx') . '.js';
	file_put_contents($file, $script);
	$out = array();
	exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);
	$json = json_decode(implode("\n", $out), true);
	pg_assert_true($code === 0 && $json !== null, 'node ran: ' . implode("\n", $out));

	return $json;
}

// The editor's twins give the same interaction and the same attributes.
function test_designer_fx_editor_twins()
{
	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');
	$start = strpos($js, '    // The lists are first-is-default');
	$end = strpos($js, '    // The id, the title and tooltip, the interaction');
	pg_assert_true($start !== false && $end !== false, 'the twins are found');

	$cases = _test_fx_cases();
	$script = substr($js, $start, $end - $start)
		. 'var cases = ' . json_encode($cases) . ';'
		. 'var out = {}; Object.keys(cases).forEach(function (k) { out[k] = [_sdFxNormalize(cases[k]), _sdFxAttrs({ _fx: cases[k] })]; });'
		. 'console.log(JSON.stringify(out));';
	$got = _test_fx_node($script);
	if ($got === null) {
		return;
	}

	foreach ($cases as $name => $fx) {
		pg_assert_same(array(pg_fx_normalize($fx), pg_fx_attrs(array('_fx' => $fx))), $got[$name], $name);
	}
}

// What the page writes, pg_fx.src.js reads back as the same values; the
// helpers give the start states, transitions and parallax offsets.
function test_designer_fx_script_reads_the_page()
{
	$cases = array();
	foreach (_test_fx_cases() as $name => $fx) {
		$norm = pg_fx_normalize($fx);
		if ($norm === null) continue;
		$attrs = array();
		preg_match_all('~ (data-pg-fx[a-z-]*)(?:="([^"]*)")?~', pg_fx_attrs(array('_fx' => $fx)), $all, PREG_SET_ORDER);
		foreach ($all as $a) $attrs[$a[1]] = isset($a[2]) ? $a[2] : '';
		$cases[$name] = array('norm' => $norm, 'attrs' => $attrs);
	}

	$script = 'var window = {};' . (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/pg_fx.src.js')
		. 'var fx = window.pgFx, cases = ' . json_encode($cases) . ', out = {};'
		. 'Object.keys(cases).forEach(function (k) { var a = cases[k].attrs;'
		. ' out[k] = fx.read(function (n) { return Object.prototype.hasOwnProperty.call(a, n) ? a[n] : null; }); });'
		. 'out._shift = ["fade|view", "slide-up|view", "slide-left|load", "slide-up|hover", "slide-right|click", "scale|view", "scale|hover", "fade|hover"].map(function (s) {'
		. ' var p = s.split("|"); return fx.shift(fx.read(function (n) { return n === "data-pg-fx" ? p[0] : n === "data-pg-fx-trigger" ? p[1] : null; })); });'
		. 'out._transition = fx.transition({ duration: 600, easing: "ease-out" }, 150);'
		. 'out._parallax = [fx.parallax(400, 200, 1000, 20), fx.parallax(0, 100, 800, 50), fx.parallax(100, 100, 1000, 20)];'
		. 'out._unknown = fx.read(function (n) { return n === "data-pg-fx" ? "spin" : null; });'
		. 'out._progress = [fx.progress(800, 200, 800, -5000), fx.progress(550, 200, 800, -5000), fx.progress(300, 200, 800, -5000), fx.progress(900, 200, 800, -5000), fx.progress(700, 200, 800, 600), fx.progress(900, 100, 800, 850), fx.progress(0, 1600, 800, -5000)];'
		. 'out._chapterAt = [fx.chapterAt([[0, 300], [300, 900], [900, 1500]], 800, false), fx.chapterAt([[-500, 300], [500, 900]], 800, false), fx.chapterAt([[600, 900]], 800, false), fx.chapterAt([[-200, 300], [600, 800]], 800, true), fx.chapterAt([[-200, 300], [810, 900]], 800, true), fx.chapterAt([], 800, false), fx.chapterAt([[400, 900]], 800, false)];'
		. 'out._chapterColours = [fx.chapterColours("rgb(17, 17, 17)", "rgb(250, 250, 250)", "rgb(33, 37, 41)"), fx.chapterColours("rgba(0, 0, 0, 0)", "rgb(33, 37, 41)", "rgb(33, 37, 41)"), fx.chapterColours("transparent", "", "x"), fx.chapterColours("rgba(10, 20, 30, 0.5)", "red", "blue"), fx.chapterColours("rgba(0,0,0,0.0)", "red", "red")];'
		. 'out._sceneStep = [fx.sceneStep(0, 2400, 800, 3), fx.sceneStep(-500, 2400, 800, 3), fx.sceneStep(-540, 2400, 800, 3), fx.sceneStep(-1100, 2400, 800, 3), fx.sceneStep(-1600, 2400, 800, 3), fx.sceneStep(-3000, 2400, 800, 3), fx.sceneStep(300, 2400, 800, 3), fx.sceneStep(-100, 600, 800, 3), fx.sceneStep(-900, 2400, 800, 1)];'
		. 'out._scrollState = [["scroll-zoom", 80, 0], ["scroll-zoom", 80, 0.5], ["scroll-zoom", 150, 0.5], ["scroll-zoom", 60, 1], ["scroll-fade", 0, 0.25], ["scroll-rise", 80, 0.5], ["scroll-rise", 80, 0]].map(function (c) {'
		. ' return fx.scrollState({ effect: c[0], from: c[1] }, c[2]); });'
		. 'console.log(JSON.stringify(out));';
	$got = _test_fx_node($script);
	if ($got === null) {
		return;
	}

	foreach ($cases as $name => $c) {
		$norm = $c['norm'];
		$read = $got[$name];
		foreach ($norm as $field => $value) {
			if ($field === 'distance' && $value === 0) {
				pg_assert_same($norm['trigger'] === 'view' || $norm['trigger'] === 'load' ? 24 : 6, $read['distance'], $name . ': the script\'s own distance');
				continue;
			}
			pg_assert_same($value, $read[$field], $name . ': ' . $field);
		}
	}

	pg_assert_same(array(
		array('opacity' => '0', 'transform' => ''),
		array('opacity' => '0', 'transform' => 'translate3d(0px,24px,0)'),
		array('opacity' => '0', 'transform' => 'translate3d(24px,0px,0)'),
		array('opacity' => '', 'transform' => 'translate3d(0px,-6px,0)'),
		array('opacity' => '', 'transform' => 'translate3d(6px,0px,0)'),
		array('opacity' => '0', 'transform' => 'scale(0.9)'),
		array('opacity' => '', 'transform' => 'scale(1.05)'),
		array('opacity' => '0.6', 'transform' => ''),
	), $got['_shift'], 'start and hover states');
	pg_assert_same('opacity 600ms ease-out 150ms,transform 600ms ease-out 150ms', $got['_transition']);
	pg_assert_same(array(0, 175, 70), $got["_parallax"], "parallax offsets");
	pg_assert_same(null, $got['_unknown'], 'an unknown effect is not read');
	// 0 while the box is below the window, 1 once its middle reaches the
	// window's middle, or at the page's end for a box that cannot get there.
	pg_assert_same(array(0, 0.5, 1, 0, 0.5, 1, 0.667), $got['_progress'], 'scroll progress');
	// The box that holds the window's middle (400 of 800); at the page's
	// end one whose top is in the lower half too.
	pg_assert_same(array(1, -1, -1, 1, -1, -1, 0), $got['_chapterAt'], 'the chapter the page wears');
	// 2400 px for three layers in an 800 px window: 1600 px of scroll, a
	// third each; before the top and past the end it stays at the edges.
	pg_assert_same(array(1, 1, 2, 3, 3, 3, 1, 1, 1), $got['_sceneStep'], 'the step a scene has reached');
	pg_assert_same(array(
		array('background' => 'rgb(17, 17, 17)', 'color' => 'rgb(250, 250, 250)'),
		array('background' => '', 'color' => ''),
		array('background' => '', 'color' => ''),
		array('background' => 'rgba(10, 20, 30, 0.5)', 'color' => 'red'),
		array('background' => '', 'color' => ''),
	), $got['_chapterColours'], 'a chapter\'s own colours, none where it has the page\'s');
	pg_assert_same(array(
		array('opacity' => '', 'transform' => 'scale(0.8)'),
		array('opacity' => '', 'transform' => 'scale(0.9)'),
		array('opacity' => '', 'transform' => 'scale(1.25)'),
		array('opacity' => '', 'transform' => ''),
		array('opacity' => '0.25', 'transform' => ''),
		array('opacity' => '', 'transform' => 'translate3d(0,40px,0)'),
		array('opacity' => '', 'transform' => 'translate3d(0,80px,0)'),
	), $got['_scrollState'], 'scroll states, the node\'s own at the end');
}

// The Interactions section: offered where the page writes the attributes,
// its rows follow the effect and the trigger, a field change keeps the
// rest and drops what no longer applies, and the panel wires it up. The
// rows and their fields are the part 'interactions'
// (style_designer_interactions.js); the core draws the section around them.
function test_designer_fx_panel()
{
	// The code view's attribute writes are in its part (style_designer_code.js).
	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js')
		. "\n" . (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer_code.js');
	$part = (string) @file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer_interactions.js');
	$twins = strpos($js, '    // The lists are first-is-default');
	$twins_end = strpos($js, '    // The id, the title and tooltip, the interaction');
	$panel = strpos($js, '    var _SD_FX_CONTENT');
	$panel_end = strpos($js, '    function propsInteractions(');
	pg_assert_true($twins !== false && $twins_end !== false && $panel !== false && $panel_end !== false && $part !== '', 'the panel code is found');

	$script = 'function esc(s) { return String(s).replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;"); }'
		. 'function _sdT(s) { return s; }'
		. 'function row(l, c) { return "<row " + l + ">" + c + "</row>"; }'
		. 'function swAttr(a, on) { return "<sw " + a + (on ? " on" : "") + ">"; }'
		. substr($js, $twins, $twins_end - $twins) . substr($js, $panel, $panel_end - $panel)
		. 'var factory = null; var StyleDesigner = { definePart: function (n, f) { if (n === "interactions") factory = f; } };'
		. "\n" . $part . "\n"
		. 'var api = factory({ esc: esc, _sdT: _sdT, row: row, swAttr: swAttr, _sdFxSpec: _sdFxSpec, _sdFxNormalize: _sdFxNormalize, _sdFxTakes: _sdFxTakes });'
		. 'function propsInteractions(n) { return _sdFxTakes(n) ? api.rows(n) : ""; }'
		. 'var _sdFxSet = api.set;'
		. 'function rows(h) { return (h.match(/<row [^>]+>/g) || []).map(function (r) { return r.slice(5, -1); }); }'
		. 'var c = function (t, p) { return { type: t, props: p }; };'
		. 'var out = {'
		. ' takes: [c("content", { contentType: "heading" }), c("content", { contentType: "custom_html" }), c("semantic", {}), c("row", {}), c("component", { componentType: "btn" }), c("shared_ref", {}), c("component", { componentType: "language_switcher" })].map(_sdFxTakes),'
		. ' none: rows(propsInteractions(c("semantic", {}))),'
		. ' slide: rows(propsInteractions(c("semantic", { _fx: { effect: "slide-up", trigger: "view" } }))),'
		. ' hover: rows(propsInteractions(c("semantic", { _fx: { effect: "fade", trigger: "hover" } }))),'
		. ' sticky: rows(propsInteractions(c("semantic", { _fx: { effect: "sticky", offset: 8 } }))),'
		. ' parallax: rows(propsInteractions(c("semantic", { _fx: { effect: "parallax" } }))),'
		. ' other: propsInteractions(c("component", { componentType: "language_switcher", _fx: { effect: "fade" } })),'
		. ' component: rows(propsInteractions(c("component", { componentType: "card", _fx: { effect: "fade", trigger: "hover" } }))),'
		. ' into: ['
		. '  _sdFxIntoFirstTag("  <a href=\\"#\\" class=\\"btn\\">x</a>\\n", { _fx: { effect: "fade", trigger: "load" } }),'
		. '  _sdFxIntoFirstTag("<input type=\\"button\\" value=\\"a/b\\"/>", { _fx: { effect: "scale", trigger: "hover" } }),'
		. '  _sdFxIntoFirstTag("<span>x</span>", {}),'
		. '  _sdFxIntoFirstTag("text", { _fx: { effect: "fade" } })'
		. ' ],'
		. ' slideHtml: propsInteractions(c("semantic", { _fx: { effect: "slide-left", trigger: "hover" } })),'
		. ' zoom: rows(propsInteractions(c("semantic", { _fx: { effect: "scroll-zoom" } }))),'
		. ' fadeScroll: rows(propsInteractions(c("semantic", { _fx: { effect: "scroll-fade" } }))),'
		. ' rise: rows(propsInteractions(c("semantic", { _fx: { effect: "scroll-rise", from: 120 } }))),'
		. ' zoomHtml: propsInteractions(c("semantic", { _fx: { effect: "scroll-zoom", from: 150 } })),'
		. ' zoomInHtml: propsInteractions(c("semantic", { _fx: { effect: "scroll-zoom" } })),'
		. ' set: ['
		. '  _sdFxSet(null, "effect", "fade"),'
		. '  _sdFxSet({ effect: "fade", duration: 900 }, "effect", "slide-up"),'
		. '  _sdFxSet({ effect: "slide-up", stagger: 80, repeat: true }, "trigger", "hover"),'
		. '  _sdFxSet({ effect: "fade", delay: 100 }, "effect", "sticky"),'
		. '  _sdFxSet({ effect: "fade" }, "effect", ""),'
		. '  _sdFxSet({ effect: "fade" }, "duration", "99999"),'
		. '  _sdFxSet({ effect: "fade" }, "repeat", true),'
		. '  _sdFxSet({ effect: "scroll-zoom", from: 150 }, "effect", "scroll-fade"),'
		. '  _sdFxSet({ effect: "scroll-zoom" }, "from", "150"),'
		. '  _sdFxSet({ effect: "fade", duration: 900, delay: 100 }, "effect", "count"),'
		. '  _sdFxSet({ effect: "count", duration: 3000, delay: 100 }, "effect", "fade"),'
		. '  _sdFxSet({ effect: "count" }, "duration", "1200")'
		. ' ],'
		. ' count: rows(propsInteractions(c("content", { contentType: "heading", _fx: { effect: "count" } }))),'
		. ' countHtml: propsInteractions(c("content", { contentType: "paragraph", _fx: { effect: "count", duration: 1500 } })),'
		. ' chapter: rows(propsInteractions(c("semantic", { _fx: { effect: "chapter" } }))),'
		. ' scene: rows(propsInteractions(c("semantic", { _fx: { effect: "scene" } }))),'
		. ' sceneHtml: propsInteractions(c("semantic", { _fx: { effect: "scene" } })),'
		. ' sceneOffered: propsInteractions(c("semantic", {})).indexOf(\'<option value="scene"\') !== -1,'
		. ' chapterHtml: propsInteractions(c("container", { _fx: { effect: "chapter", duration: 900 } })),'
		. ' chapterSet: [_sdFxSet({ effect: "count", duration: 3000 }, "effect", "chapter"), _sdFxSet({ effect: "fade", duration: 900, delay: 100 }, "effect", "chapter"), _sdFxSet({ effect: "chapter" }, "duration", "1500")],'
		. ' chapterOffered: [c("semantic", {}), c("container", {}), c("row", {}), c("content", { contentType: "heading" }), c("content", { contentType: "image", _fx: { effect: "chapter" } })].map(function (n) {'
		. '  return propsInteractions(n).indexOf(\'<option value="chapter"\') !== -1; }),'
		. ' offered: [c("content", { contentType: "heading" }), c("content", { contentType: "span" }), c("content", { contentType: "link" }), c("content", { contentType: "image" }), c("semantic", {}), c("component", { componentType: "btn" }), c("semantic", { _fx: { effect: "count" } })].map(function (n) {'
		. '  return propsInteractions(n).indexOf(\'<option value="count"\') !== -1; })'
		. '};'
		. 'console.log(JSON.stringify(out));';
	$got = _test_fx_node($script);
	if ($got === null) {
		return;
	}

	pg_assert_same(array(true, false, true, true, true, false, false), $got['takes'], 'where the section is offered');
	pg_assert_same(array('Effect'), $got['none'], 'no interaction: the effect alone');
	pg_assert_same(array('Effect', 'Trigger', 'Duration', 'Delay', 'Easing', 'Distance', 'Stagger children', 'Every time'), $got['slide'], 'a slide that enters on view');
	pg_assert_same(array('Effect', 'Trigger', 'Duration', 'Delay', 'Easing'), $got['hover'], 'a hover fade');
	pg_assert_same(array('Effect', 'Top offset'), $got['sticky']);
	pg_assert_same(array('Effect', 'Speed'), $got['parallax']);
	pg_assert_same('', $got['other'], 'not on the language switcher');
	pg_assert_same(array('Effect', 'Trigger', 'Duration', 'Delay', 'Easing'), $got['component'], 'on a component');
	// The component's first tag takes the attributes the page writes.
	pg_assert_same('  <a href="#" class="btn"' . pg_fx_attrs(array('_fx' => array('effect' => 'fade', 'trigger' => 'load'))) . ">x</a>\n", $got['into'][0]);
	pg_assert_same('<input type="button" value="a/b"' . pg_fx_attrs(array('_fx' => array('effect' => 'scale', 'trigger' => 'hover'))) . '/>', $got['into'][1], 'before a void tag\'s slash');
	pg_assert_same('<span>x</span>', $got['into'][2], 'no interaction, unchanged');
	pg_assert_same('text', $got['into'][3], 'no tag, unchanged');
	pg_assert_contains("_sdFxIntoFirstTag(comp.toHTML(P, pad, _pcChildren), n.props)", $js, 'the editor\'s HTML writes it on components');
	pg_assert_contains('data-fx="distance" min="0" max="400" step="1" value="" placeholder="6"', $got['slideHtml'], 'the script\'s own distance as the placeholder');
	pg_assert_contains('<option value="slide-left" selected>Slide left</option>', $got['slideHtml']);

	pg_assert_same(pg_fx_normalize(array('effect' => 'fade')), $got['set'][0], 'a new effect with the defaults');
	pg_assert_same(900, $got['set'][1]['duration'], 'switching the effect keeps the timing');
	pg_assert_same(pg_fx_normalize(array('effect' => 'slide-up', 'trigger' => 'hover')), $got['set'][2], 'hover drops stagger and repeat');
	pg_assert_same(array('effect' => 'sticky', 'offset' => 0), $got['set'][3]);
	pg_assert_same(null, $got['set'][4], 'cleared');
	pg_assert_same(5000, $got['set'][5]['duration'], 'clamped');
	pg_assert_same(true, $got['set'][6]['repeat']);
	pg_assert_same(array('Effect', 'Start scale'), $got['zoom'], 'a scroll zoom: its start alone');
	pg_assert_same(array('Effect', 'Start opacity'), $got['fadeScroll']);
	pg_assert_same(array('Effect', 'Start offset'), $got['rise']);
	pg_assert_contains('data-fx="from" min="25" max="200" step="1" value="150"', $got['zoomHtml'], 'the start in the range of its effect');
	pg_assert_contains('<option value="scroll-zoom" selected>Zoom on scroll</option>', $got['zoomHtml']);
	pg_assert_false(strpos($got['zoomHtml'], 'data-fx-play') !== false, 'a scroll effect has nothing to play');
	pg_assert_contains('set Overflow to Hidden', $got['zoomHtml'], 'a zoom that starts larger asks for a clipping parent');
	pg_assert_false(strpos($got['zoomInHtml'], 'Overflow') !== false, 'one that starts smaller does not');
	pg_assert_same(array('effect' => 'scroll-fade', 'from' => 0), $got['set'][7], 'another scroll effect starts at its own default');
	pg_assert_same(array('effect' => 'scroll-zoom', 'from' => 150), $got['set'][8], 'a typed start');
	pg_assert_same(array('effect' => 'count', 'duration' => 2000, 'delay' => 100), $got['set'][9], 'a count counts for its own duration, the delay kept');
	pg_assert_same(pg_fx_normalize(array('effect' => 'fade', 'delay' => 100)), $got['set'][10], 'and back to a transition\'s duration');
	pg_assert_same(array('effect' => 'count', 'duration' => 1200, 'delay' => 0), $got['set'][11], 'a typed duration');
	pg_assert_same(array('Effect', 'Duration', 'Delay'), $got['count'], 'a count: its duration and delay');
	pg_assert_contains('data-fx="duration" min="100" max="5000" step="1" value="1500"', $got['countHtml']);
	pg_assert_contains('<option value="count" selected>Count up</option>', $got['countHtml']);
	pg_assert_contains('data-fx-play', $got['countHtml'], 'a count can be played on the canvas');
	pg_assert_contains('first number in the text', $got['countHtml'], 'a note on what it counts');
	pg_assert_same(array(true, true, true, false, false, false, true), $got['offered'], 'offered on text, kept where it is set');
	pg_assert_same(array('Effect', 'Duration'), $got['chapter'], 'a chapter: its duration');
	pg_assert_same(array('Effect'), $got['scene'], 'a scene: no field');
	pg_assert_contains('<option value="scene" selected>Pinned scene</option>', $got['sceneHtml'], 'kept in the list where it is set');
	pg_assert_contains('Set by the Pinned Story block', $got['sceneHtml']);
	pg_assert_contains('see the layers one under the other', $got['sceneHtml'], 'reduced motion: the layers stacked');
	pg_assert_false(strpos($got['sceneHtml'], 'data-fx-play') !== false, 'a scene has nothing to play');
	pg_assert_false($got['sceneOffered'], 'not offered on a section without it');
	pg_assert_contains('data-fx="duration" min="100" max="5000" step="1" value="900"', $got['chapterHtml']);
	pg_assert_contains('<option value="chapter" selected>Colour chapter</option>', $got['chapterHtml']);
	pg_assert_false(strpos($got['chapterHtml'], 'data-fx-play') !== false, 'a chapter has nothing to play');
	pg_assert_contains('see the colours change at once', $got['chapterHtml'], 'reduced motion: a cut');
	pg_assert_same(array(array('effect' => 'chapter', 'duration' => 600), array('effect' => 'chapter', 'duration' => 900), array('effect' => 'chapter', 'duration' => 1500)), $got['chapterSet'], 'from a count the default duration, from a transition its own');
	pg_assert_same(array(true, true, false, false, true), $got['chapterOffered'], 'offered on sections and containers, kept where it is set');

	pg_assert_contains("optHtml += propsInteractions(selectedNode);", $js, 'the panel shows the section');
	pg_assert_contains("_sdFxBind(selectedNode);", $js, 'and binds it');
	pg_assert_contains("_sdPartCall('interactions', 'fill'", $js, 'the part fills the section');
	pg_assert_contains("if (_sdCodeSetFxAttr(node, name, now)) return;", $js, 'the code view hands the attributes back');
	pg_assert_contains('data-fx-play', $got['slideHtml'], 'a moving effect has a Play button');
	pg_assert_contains("        _sdScrollers();\n        _sdFxCanvas();", $js, 'the canvas binds interactions after every render');
	pg_assert_contains("var _fxAttrs = _sdFxAttrs(node.props);", $js, 'canvas elements carry the attributes');
}

// A small fake document for pg_fx.src.js under Node: elements with
// attributes, classes, inline style and a box (`rect`, drawn with the
// shift and scale their transform holds, as a browser measures it), a
// window that scrolls (`winOn.scroll()` runs the scroll listener) and a
// motion setting the test switches (`reducedNow`, `mmChange()`). The nodes
// the script finds are those in `nodes`.
function _test_fx_fake_dom()
{
	return <<<'JS'
var timers = [], observed = [], unobserved = [], winOn = {}, reducedNow = false, mmChange = null;
function El(attrs) {
	var el = this;
	this.attrs = attrs; this.children = []; this.parentNode = null; this.offsetWidth = 200; this.offsetHeight = 100;
	this.listeners = {};
	this.rect = { top: 0, height: 100 };
	var cls = {};
	this.classList = {
		add: function (c) { cls[c] = 1; }, remove: function (c) { delete cls[c]; },
		contains: function (c) { return !!cls[c]; },
		toggle: function (c) { if (cls[c]) { delete cls[c]; return false; } cls[c] = 1; return true; }
	};
	var prio = {};
	this.style = {
		opacity: '', transform: '', transition: '', top: '', position: '',
		setProperty: function (k, v, p) { this[k] = v; prio[k] = p || ''; },
		removeProperty: function (k) { this[k] = ''; prio[k] = ''; },
		getPropertyPriority: function (k) { return prio[k] || ''; }
	};
	this.getAttribute = function (n) { return Object.prototype.hasOwnProperty.call(el.attrs, n) ? el.attrs[n] : null; };
	this.addEventListener = function (t, f) { (el.listeners[t] = el.listeners[t] || []).push(f); };
	this.getBoundingClientRect = function () {
		var t = el.style.transform, y = 0, k = 1, m;
		if ((m = /translate3d\(0,(-?[\d.]+)px,0\)/.exec(t))) y = Number(m[1]);
		if ((m = /scale\(([\d.]+)\)/.exec(t))) k = Number(m[1]);
		var top = el.rect.top + y + el.rect.height * (1 - k) / 2, h = el.rect.height * k;
		return { top: top, height: h, bottom: top + h };
	};
}
function box(attrs, top, height) {
	var e = new El(attrs);
	e.rect = { top: top, height: height };
	e.offsetHeight = height;
	return e;
}
var nodes = [];
var document = {
	readyState: 'complete',
	querySelectorAll: function () { return nodes.filter(function (n) { return n.attrs['data-pg-fx'] !== undefined; }); },
	documentElement: { contains: function (el) { return nodes.indexOf(el) !== -1; }, clientHeight: 800, scrollHeight: 10000 },
	addEventListener: function () {}
};
var mm = { addEventListener: function (t, f) { mmChange = f; } };
Object.defineProperty(mm, 'matches', { get: function () { return reducedNow; } });
var window = {
	pgFxEditing: true, innerHeight: 800, pageYOffset: 0,
	matchMedia: function () { return mm; },
	getComputedStyle: function (el) { return { display: 'block', position: el.style.position || 'static' }; },
	IntersectionObserver: function () { this.observe = function (e) { observed.push(e); }; this.unobserve = function (e) { unobserved.push(e); }; },
	requestAnimationFrame: function (f) { f(); },
	setTimeout: function (f) { timers.push(f); },
	addEventListener: function (t, f) { winOn[t] = f; }
};
function setTimeout(f) { timers.push(f); }
function clearTimeout() {}

JS;
}

// pg_fx.src.js on a small fake document: the editor's canvas (pgFxEditing)
// plays the entrances there when the file starts and shows a node drawn by
// a later render in place, a node taken out is forgotten, replay() plays
// an effect with important transitions and gives the node back, sticky
// gets its position where no head style gave it one.
function test_designer_fx_script_on_the_canvas()
{
	$fake = _test_fx_fake_dom() . <<<'JS'
var first = new El({ 'data-pg-fx': 'fade', 'data-pg-fx-trigger': 'load' });
var sticky = new El({ 'data-pg-fx': 'sticky', 'data-pg-fx-offset': '12' });
var viewed = new El({ 'data-pg-fx': 'slide-up', 'data-pg-fx-trigger': 'view' });
nodes.push(first, sticky, viewed);
JS;
	$steps = <<<'JS'
var out = {};
out.first = [first.style.opacity, first.style.transition.indexOf('opacity 600ms') === 0, first.classList.contains('pg-fx-armed')];
out.sticky = [sticky.style.position, sticky.style.top];
out.viewed = [viewed.style.opacity, observed.indexOf(viewed) !== -1];
nodes = [first];
var redrawn = new El({ 'data-pg-fx': 'fade', 'data-pg-fx-trigger': 'view' });
nodes.push(redrawn);
window.pgFxBind(document);
out.redrawn = [redrawn.style.opacity, redrawn.classList.contains('pg-fx-armed'), observed.indexOf(redrawn) === -1];
out.forgotten = unobserved.indexOf(viewed) !== -1;
var hover = new El({ 'data-pg-fx': 'scale', 'data-pg-fx-trigger': 'hover' });
hover.style.transform = 'rotate(2deg)';
nodes.push(hover);
window.pgFxBind(document);
timers = [];
out.replay = window.pgFx.replay(hover);
out.during = [hover.style.transform, hover.style.getPropertyPriority('transition')];
timers.shift()();
out.back = [hover.style.transform];
timers.shift()();
out.after = [hover.style.transform, hover.style.transition, hover.style.getPropertyPriority('transition')];
timers = [];
out.replayEntrance = window.pgFx.replay(redrawn);
out.entranceDuring = [redrawn.style.opacity, redrawn.style.getPropertyPriority('transition')];
out.stickyReplay = window.pgFx.replay(sticky);
console.log(JSON.stringify(out));
JS;
	$script = $fake . (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/pg_fx.src.js') . $steps;
	$got = _test_fx_node($script);
	if ($got === null) {
		return;
	}

	pg_assert_same(array('', true, true), $got['first'], 'the first bind plays the entrance');
	pg_assert_same(array('sticky', '12px'), $got['sticky'], 'sticky without a head style');
	pg_assert_same(array('0', true), $got['viewed'], 'a view entrance waits, armed');
	pg_assert_same(array('', true, true), $got['redrawn'], 'a node drawn later shows in place');
	pg_assert_true($got['forgotten'], 'a node taken out is no longer observed');
	pg_assert_true($got['replay'], 'a hover effect replays');
	pg_assert_same(array('rotate(2deg) scale(1.05)', 'important'), $got['during'], 'towards the effect, important, on top of its own transform');
	pg_assert_same(array('rotate(2deg)'), $got['back'], 'and back');
	pg_assert_same(array('rotate(2deg)', '', ''), $got['after'], 'its own state and transition again');
	pg_assert_true($got['replayEntrance']);
	pg_assert_same(array('', 'important'), $got['entranceDuring'], 'an entrance plays to its own state');
	pg_assert_false($got['stickyReplay'], 'sticky has nothing to replay');
}

// The scroll-linked effects on a fake page 800 px high: each frame puts
// the node at its point between the start and its own state, measured
// without the shift or scale it already has (no drift on a scroll that
// moves nothing); a node far below is left alone until it comes near; a
// node in the page's last strip ends in its own state at the page's end;
// under reduced motion a node shows as it is, and turning it on while the
// page is open puts every node back.
function test_designer_fx_script_scroll_effects()
{
	$nodes = <<<'JS'
var zoom = box({ 'data-pg-fx': 'scroll-zoom', 'data-pg-fx-from': '60' }, 600, 200);
zoom.style.transform = 'rotate(2deg)';
zoom.style.transition = 'box-shadow .3s';
var rise = box({ 'data-pg-fx': 'scroll-rise' }, 1000, 200);
var shown = box({ 'data-pg-fx': 'scroll-fade' }, 100, 200);
var far = box({ 'data-pg-fx': 'scroll-fade' }, 5000, 200);
var halfSeen = box({ 'data-pg-fx': 'scroll-fade', 'data-pg-fx-from': '20' }, 550, 200);
halfSeen.style.opacity = '0.5';
nodes.push(zoom, rise, shown, far, halfSeen);

JS;
	$steps = <<<'JS'
var out = {};
out.start = [zoom.style.transform, zoom.style.transition, rise.style.transform, shown.style.opacity, far.style.opacity, halfSeen.style.opacity];
winOn.scroll();
out.again = [zoom.style.transform, rise.style.transform];
[zoom, rise, shown, far, halfSeen].forEach(function (n) { n.rect.top -= 450; });
window.pageYOffset = 450;
winOn.scroll();
out.scrolled = [zoom.style.transform, rise.style.transform, halfSeen.style.opacity, zoom.style.transition];
var last = box({ 'data-pg-fx': 'scroll-fade', 'data-pg-fx-from': '20' }, 1100, 200);
nodes.push(last);
document.documentElement.scrollHeight = 1750;
window.pgFxBind(document);
out.lastBefore = last.style.opacity;
[zoom, rise, shown, far, halfSeen, last].forEach(function (n) { n.rect.top -= 500; });
window.pageYOffset = 950;
winOn.scroll();
out.lastEnd = last.style.opacity;
rise.rect.top = 550;
document.documentElement.scrollHeight = 10000;
winOn.scroll();
out.mid = rise.style.transform;
reducedNow = true;
var calm = box({ 'data-pg-fx': 'scroll-zoom' }, 700, 100);
nodes.push(calm);
window.pgFxBind(document);
out.calm = [calm.style.transform, calm.classList.contains('pg-fx-armed')];
rise.rect.top = 300;
winOn.scroll();
out.frozen = rise.style.transform;
mmChange();
out.back = [zoom.style.transform, rise.style.transform, rise.style.transition, halfSeen.style.opacity];
console.log(JSON.stringify(out));
JS;
	$got = _test_fx_node(_test_fx_fake_dom() . $nodes . (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/pg_fx.src.js') . $steps);
	if ($got === null) {
		return;
	}

	// zoom: top 600 of 800, its middle reaches the window's middle at top
	// 300, so 0.4 of the way: 60 + 40 * 0.4 = 76 %, after its own rotation.
	// halfSeen: half way, opacity 20 % + 80 % * 0.5 = 60 % of its own 0.5.
	pg_assert_same(array('rotate(2deg) scale(0.76)', 'none', 'translate3d(0,80px,0)', '', '', '0.3'), $got['start'], 'the start states at load');
	pg_assert_same(array('rotate(2deg) scale(0.76)', 'translate3d(0,80px,0)'), $got['again'], 'measured without its own scale and shift');
	pg_assert_same(array('rotate(2deg)', 'translate3d(0,40px,0)', '0.5', 'box-shadow .3s'), $got['scrolled'], 'past the middle the node\'s own state and transition');
	pg_assert_same('0.2', $got['lastBefore'], 'a node in the last strip starts below the window');
	pg_assert_same('', $got['lastEnd'], 'and is whole at the page\'s end');
	pg_assert_same('translate3d(0,40px,0)', $got['mid'], 'back half way');
	pg_assert_same(array('', true), $got['calm'], 'reduced motion: the node as it is');
	pg_assert_same('translate3d(0,40px,0)', $got['frozen'], 'reduced motion: no frame moves a node');
	pg_assert_same(array('rotate(2deg)', '', '', '0.5'), $got['back'], 'turned on while open: every node back to its own state');
}

// The count effect on a fake page: a number is set to zero when the script
// binds it and counts up once it is seen, after its delay, frame by frame
// to the text the markup holds; a node without a number is left alone; on
// the editor's canvas a node drawn by a later render shows its number, a
// press on a counting node shows it at once, and the Play button counts it
// again, a press or focus during Play stopping it too (one listener per
// node); reduced motion turned on puts every number back, and a node bound
// under it never counts but for Play.
function test_designer_fx_script_counts()
{
	$nodes = <<<'JS'
var clock = 1000, frames = [], seenCb = null;
Date.now = function () { return clock; };
window.requestAnimationFrame = function (f) { frames.push(f); };
window.IntersectionObserver = function (cb) {
	seenCb = cb;
	this.observe = function (e) { observed.push(e); };
	this.unobserve = function (e) { unobserved.push(e); };
};
function tick(ms) { clock += ms; var f = frames; frames = []; f.forEach(function (g) { g(); }); }
function text(s, next) { return { nodeType: 3, data: s, nextSibling: next || null }; }
function counting(attrs, first) { var e = new El(attrs); e.nodeType = 1; e.nodeName = 'DIV'; e.firstChild = first; return e; }
var big = counting({ 'data-pg-fx': 'count' }, text('10.000+'));
var late = counting({ 'data-pg-fx': 'count', 'data-pg-fx-duration': '1000', 'data-pg-fx-delay': '500' }, text('%99,9'));
var inner = text('$1,234.56');
var nested = counting({ 'data-pg-fx': 'count' }, text('Up to ', { nodeType: 1, nodeName: 'STRONG', firstChild: inner, nextSibling: text(' saved') }));
var words = counting({ 'data-pg-fx': 'count' }, text('Many'));
var waiting = counting({ 'data-pg-fx': 'count' }, text('250'));
nodes.push(big, late, nested, words, waiting);

JS;
	$steps = <<<'JS'
var out = {};
out.armed = [big.firstChild.data, late.firstChild.data, inner.data, words.firstChild.data, waiting.firstChild.data];
out.observed = [big, late, nested, words, waiting].map(function (e) { return observed.indexOf(e) !== -1; });
seenCb([{ target: big, isIntersecting: true }, { target: late, isIntersecting: true }, { target: nested, isIntersecting: true }]);
out.unobserved = [unobserved.indexOf(big) !== -1, unobserved.indexOf(late) !== -1];
tick(0);
out.first = [big.firstChild.data, late.firstChild.data, inner.data];
tick(500);
out.delayed = late.firstChild.data;
tick(500);
out.half = [big.firstChild.data, late.firstChild.data, inner.data];
tick(1000);
out.end = [big.firstChild.data, late.firstChild.data, inner.data, frames.length];
waiting.listeners.pointerdown[0]();
out.pressed = waiting.firstChild.data;
var redrawn = counting({ 'data-pg-fx': 'count' }, text('3.500'));
nodes.push(redrawn);
window.pgFxBind(document);
out.redrawn = [redrawn.firstChild.data, observed.indexOf(redrawn) === -1];
out.replay = window.pgFx.replay(redrawn);
tick(0);
out.replayStart = redrawn.firstChild.data;
tick(2000);
out.replayEnd = redrawn.firstChild.data;
window.pgFx.replay(redrawn);
window.pgFx.replay(redrawn);
tick(500);
out.replayListeners = (redrawn.listeners.pointerdown || []).length;
if (out.replayListeners) redrawn.listeners.pointerdown[0]();
tick(16);
out.replayPressed = [redrawn.firstChild.data, frames.length];
var other = counting({ 'data-pg-fx': 'count' }, text('1.250.000'));
nodes = [other];
window.pgFx.replay(other);
tick(0);
out.otherStart = other.firstChild.data;
reducedNow = true;
mmChange();
out.reduced = other.firstChild.data;
tick(16);
out.reducedAfter = [other.firstChild.data, frames.length];
var calm = counting({ 'data-pg-fx': 'count' }, text('42'));
nodes.push(calm);
window.pgFxBind(document);
out.calm = [calm.firstChild.data, observed.indexOf(calm) === -1];
window.pgFx.replay(calm);
tick(0);
out.calmPlay = calm.firstChild.data;
if ((calm.listeners.focusin || []).length) calm.listeners.focusin[0]();
tick(16);
out.calmPressed = [calm.firstChild.data, frames.length];
console.log(JSON.stringify(out));
JS;
	$got = _test_fx_node(_test_fx_fake_dom() . $nodes . (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/pg_fx.src.js') . $steps);
	if ($got === null) {
		return;
	}

	pg_assert_same(array('0+', '%0,0', '$0.00', 'Many', '0'), $got['armed'], 'zero until seen, the words around kept');
	pg_assert_same(array(true, true, true, false, true), $got['observed'], 'a node without a number is not watched');
	pg_assert_same(array(true, true), $got['unobserved'], 'counted once');
	pg_assert_same(array('0+', '%0,0', '$0.00'), $got['first'], 'from zero');
	pg_assert_same('%0,0', $got['delayed'], 'the delay waits');
	pg_assert_same(array('8.750+', '%87,4', '$1,080.24'), $got['half'], 'half way, eased');
	pg_assert_same(array('10.000+', '%99,9', '$1,234.56', 0), $got['end'], 'the number the markup holds, no frame left');
	pg_assert_same('250', $got['pressed'], 'a press on the canvas shows the number');
	pg_assert_same(array('3.500', true), $got['redrawn'], 'drawn by a later render: the number, not watched');
	pg_assert_true($got['replay'], 'the Play button counts it');
	pg_assert_same('0', $got['replayStart']);
	pg_assert_same('3.500', $got['replayEnd']);
	pg_assert_same(1, $got['replayListeners'], 'Play listens for a press once, however often it is pressed');
	pg_assert_same(array('3.500', 0), $got['replayPressed'], 'a press during Play shows the number and stops the count');
	pg_assert_same('0', $got['otherStart']);
	pg_assert_same('1.250.000', $got['reduced'], 'reduced motion turned on: the number back');
	pg_assert_same(array('1.250.000', 0), $got['reducedAfter'], 'and no frame writes it again');
	pg_assert_same(array('42', true), $got['calm'], 'bound under reduced motion: never counts');
	pg_assert_same('0', $got['calmPlay'], 'the Play button counts it still');
	pg_assert_same(array('42', 0), $got['calmPressed'], 'and a focus on it shows the number');
}


// Autoplay: pg_fx.src.js plays a <video> with the effect, muted, looping
// and inline; its pause button (data-pg-video-toggle, aria-controls) pauses
// and plays it and says so with aria-pressed; a refused play leaves it
// paused; under reduced motion it stays still, and turning reduced motion
// on while it plays pauses it. Another element with the effect is left alone.
function test_designer_fx_script_autoplay_video()
{
	$script = <<<'JS'
var reducedNow = false, mmChange = null, capture = null;
function El(tag, attrs) {
	var el = this;
	this.tagName = tag; this.attrs = attrs; this.parentNode = null; this.listeners = {};
	this.classList = { add: function () {}, remove: function () {}, contains: function () { return false; } };
	this.style = { setProperty: function () {}, removeProperty: function () {} };
	this.getAttribute = function (n) { return Object.prototype.hasOwnProperty.call(el.attrs, n) ? el.attrs[n] : null; };
	this.setAttribute = function (n, v) { el.attrs[n] = String(v); };
	this.addEventListener = function () {};
	this.closest = function (sel) { return sel === '[data-pg-video-toggle]' && el.attrs['data-pg-video-toggle'] !== undefined ? el : null; };
}
function Video(attrs, refuse) {
	El.call(this, 'VIDEO', attrs);
	var v = this;
	this.paused = true; this.plays = 0;
	this.play = function () { v.plays++; if (refuse) return { then: function (ok, bad) { bad(new Error('no')); } }; v.paused = false; return { then: function (ok) { if (ok) ok(); } }; };
	this.pause = function () { v.paused = true; };
}
var video = new Video({ 'data-pg-fx': 'autoplay', id: 'hero-film' });
var refused = new Video({ 'data-pg-fx': 'autoplay', id: 'blocked' }, true);
var div = new El('DIV', { 'data-pg-fx': 'autoplay' });
var btn = new El('BUTTON', { 'data-pg-video-toggle': '', 'aria-controls': 'hero-film', 'aria-pressed': 'false' });
var btn2 = new El('BUTTON', { 'data-pg-video-toggle': '', 'aria-controls': 'blocked', 'aria-pressed': 'false' });
var all = [video, refused, div, btn, btn2];
var document = {
	readyState: 'complete',
	querySelectorAll: function (sel) {
		return all.filter(function (n) { return sel === '[data-pg-fx]' ? n.attrs['data-pg-fx'] !== undefined : n.attrs['data-pg-video-toggle'] !== undefined; });
	},
	getElementById: function (id) { return all.filter(function (n) { return n.attrs.id === id; })[0] || null; },
	documentElement: { contains: function (el) { return all.indexOf(el) !== -1; }, clientHeight: 800, scrollHeight: 2000 },
	addEventListener: function (t, f, c) { if (t === 'click' && c) capture = f; }
};
var mm = { addEventListener: function (t, f) { mmChange = f; } };
Object.defineProperty(mm, 'matches', { get: function () { return reducedNow; } });
var window = {
	innerHeight: 800, pageYOffset: 0,
	matchMedia: function () { return mm; },
	getComputedStyle: function () { return { position: 'static' }; },
	requestAnimationFrame: function (f) { f(); },
	addEventListener: function () {}
};
JS;
	$script .= (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/pg_fx.src.js');
	$script .= <<<'JS'
var out = {};
out.started = [video.paused, video.muted, video.loop, video.playsInline, video.attrs.playsinline, btn.attrs['aria-pressed']];
out.refused = [refused.paused, btn2.attrs['aria-pressed']];
out.div = div.plays === undefined && div.muted === undefined;
var stopped = false, ev = function (t) { return { target: t, preventDefault: function () { stopped = true; } }; };
capture(ev(btn));
out.paused = [video.paused, btn.attrs['aria-pressed'], stopped];
capture(ev(btn));
out.again = [video.paused, btn.attrs['aria-pressed'], video.plays];
reducedNow = true; mmChange();
out.reduced = [video.paused, btn.attrs['aria-pressed']];
var other = new El('A', {}); stopped = false; capture(ev(other));
out.other = stopped;
var still = new Video({ 'data-pg-fx': 'autoplay', id: 'still' });
all.push(still); window.pgFxBind(document);
out.still = [still.paused, still.plays];
console.log(JSON.stringify(out));
JS;
	$got = _test_fx_node($script);
	if ($got === null) {
		return;
	}

	pg_assert_same(array(false, true, true, true, '', 'false'), $got['started'], 'the video plays muted, looping and inline; its button says it plays');
	pg_assert_same(array(true, 'true'), $got['refused'], 'a refused play leaves it paused, and its button says so');
	pg_assert_true($got['div'], 'an element that is not a video is left alone');
	pg_assert_same(array(true, 'true', true), $got['paused'], 'the button pauses it');
	pg_assert_same(array(false, 'false', 2), $got['again'], 'and plays it again');
	pg_assert_same(array(true, 'true'), $got['reduced'], 'reduced motion turned on pauses it');
	pg_assert_false($got['other'], 'another click is left alone');
	pg_assert_same(array(true, 0), $got['still'], 'under reduced motion a video does not start');
}

// Chapters on a fake page 800 px high: the page's body takes the colours
// of the chapter that holds the window's middle, over its duration, and
// its own back (inline values and transition) once none does; a chapter
// with no background of its own leaves the page's; under reduced motion
// the colours change at once; on the editor's canvas nothing is painted.
function test_designer_fx_script_chapters()
{
	$nodes = <<<'JS'
window.pgFxEditing = false;
document.body = { style: { backgroundColor: '', color: 'rgb(1, 2, 3)', transition: 'opacity 1s' }, scrollHeight: 0 };
var computed = function (el) {
	if (el === document.body) return { color: 'rgb(33, 37, 41)' };
	return { display: 'block', position: 'static', backgroundColor: el.bg || 'rgba(0, 0, 0, 0)', color: el.fg || 'rgb(33, 37, 41)' };
};
window.getComputedStyle = computed;
var dark = box({ 'data-pg-fx': 'chapter', 'data-pg-fx-duration': '900' }, 1000, 600);
dark.bg = 'rgb(17, 17, 17)'; dark.fg = 'rgb(250, 250, 250)';
var plain = box({ 'data-pg-fx': 'chapter' }, 1600, 600);
plain.fg = 'rgb(200, 0, 0)';
nodes.push(dark, plain);

JS;
	$steps = <<<'JS'
var out = {}, s = document.body.style;
function scrollTo(y) { [dark, plain].forEach(function (n) { n.rect.top += window.pageYOffset - y; }); window.pageYOffset = y; winOn.scroll(); }
out.start = [s.backgroundColor, s.color, s.transition];
scrollTo(700);
out.dark = [s.backgroundColor, s.color, s.transition];
scrollTo(1300);
out.plain = [s.backgroundColor, s.color, s.transition];
scrollTo(0);
out.own = [s.backgroundColor, s.color, s.transition];
reducedNow = true;
scrollTo(700);
out.reduced = [s.backgroundColor, s.transition];
nodes = [];
window.pgFxBind(document);
out.gone = [s.backgroundColor, s.color, s.transition];
reducedNow = false;
window.pgFxEditing = true;
var edited = box({ 'data-pg-fx': 'chapter' }, 100, 600);
edited.bg = 'rgb(0, 0, 0)';
nodes.push(edited);
window.pgFxBind(document);
winOn.scroll();
out.editing = [s.backgroundColor, s.color];
console.log(JSON.stringify(out));
JS;
	$got = _test_fx_node(_test_fx_fake_dom() . $nodes . (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/pg_fx.src.js') . $steps);
	if ($got === null) {
		return;
	}

	pg_assert_same(array('', 'rgb(1, 2, 3)', 'opacity 1s'), $got['start'], 'below the window: the page\'s own colours');
	pg_assert_same(array('rgb(17, 17, 17)', 'rgb(250, 250, 250)', 'background-color 900ms ease,color 900ms ease'), $got['dark'], 'the dark chapter holds the middle');
	pg_assert_same(array('', 'rgb(200, 0, 0)', 'background-color 600ms ease,color 600ms ease'), $got['plain'], 'no background of its own: the page\'s, with its text colour');
	pg_assert_same(array('', 'rgb(1, 2, 3)', 'background-color 600ms ease,color 600ms ease'), $got['own'], 'no chapter at the middle: the page\'s own, over the leaving one\'s duration');
	pg_assert_same(array('rgb(17, 17, 17)', 'none'), $got['reduced'], 'reduced motion: a cut');
	pg_assert_same(array('', 'rgb(1, 2, 3)', 'opacity 1s'), $got['gone'], 'every chapter gone: the body as it was');
	pg_assert_same(array('', 'rgb(1, 2, 3)'), $got['editing'], 'the editor\'s canvas keeps its own colours');
}

// A pinned scene on a fake page 800 px high: the script writes the number
// of layers, then the step its scroll has reached, and marks that step's
// layer alone; a scene with one layer is left as it is; under reduced
// motion turned on the layers stand one under the other again; the editor's
// canvas never plays a scene.
function test_designer_fx_script_scene()
{
	$nodes = <<<'JS'
window.pgFxEditing = false;
El.prototype.setAttribute = function (n, v) { this.attrs[n] = String(v); };
El.prototype.removeAttribute = function (n) { delete this.attrs[n]; };
var props = {};
function scene(top, count) {
	var s = box({ 'data-pg-fx': 'scene' }, top, 2400), layers = [];
	for (var i = 0; i < count; i++) layers.push(new El({ 'data-pg-scene-layer': '' }));
	s.layers = layers;
	s.querySelectorAll = function (sel) { return sel === '[data-pg-scene-layer]' ? layers : []; };
	s.style.setProperty = function (k, v) { s.style[k] = v; };
	return s;
}
var story = scene(1000, 3);
var single = scene(4000, 1);
nodes.push(story, single);

JS;
	$steps = <<<'JS'
var out = {};
function on(s) { return s.layers.map(function (l) { return l.attrs['data-pg-scene-on'] !== undefined; }); }
function scrollTo(y) { [story, single].forEach(function (n) { n.rect.top += window.pageYOffset - y; }); window.pageYOffset = y; winOn.scroll(); }
out.start = [story.style['--pg-scene-steps'], story.attrs['data-pg-scene-step'], on(story)];
out.single = [single.attrs['data-pg-scene-step'] === undefined, single.style['--pg-scene-steps'] === undefined, on(single)];
scrollTo(1600);
out.second = [story.attrs['data-pg-scene-step'], on(story)];
scrollTo(2300);
out.third = [story.attrs['data-pg-scene-step'], on(story)];
scrollTo(9000);
out.past = story.attrs['data-pg-scene-step'];
reducedNow = true;
mmChange();
out.reduced = [story.attrs['data-pg-scene-step'] === undefined, on(story)];
scrollTo(1600);
out.frozen = story.attrs['data-pg-scene-step'] === undefined;
reducedNow = false;
window.pgFxEditing = true;
var edited = scene(0, 3);
nodes.push(edited);
window.pgFxBind(document);
winOn.scroll();
out.editing = [edited.attrs['data-pg-scene-step'] === undefined, on(edited)];
console.log(JSON.stringify(out));
JS;
	$got = _test_fx_node(_test_fx_fake_dom() . $nodes . (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/pg_fx.src.js') . $steps);
	if ($got === null) {
		return;
	}

	pg_assert_same(array('3', '1', array(true, false, false)), $got['start'], 'three layers, the first step before the section is reached');
	pg_assert_same(array(true, true, array(false)), $got['single'], 'one layer: nothing to play');
	pg_assert_same(array('2', array(false, true, false)), $got['second'], 'a third of the way: the second layer alone');
	pg_assert_same(array('3', array(false, false, true)), $got['third']);
	pg_assert_same('3', $got['past'], 'past the scene: its last step');
	pg_assert_same(array(true, array(false, false, false)), $got['reduced'], 'reduced motion turned on: the layers one under the other');
	pg_assert_true($got['frozen'], 'and no frame plays it again');
	pg_assert_same(array(true, array(false, false, false)), $got['editing'], 'the editor\'s canvas keeps the layers to edit');
}
