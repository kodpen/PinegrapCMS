<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * A place's own values of a shared component drawn on the site: the place
 * marker in the page body, the per-call render of _expand_shared_refs()
 * (the cache rule: one page's values never reach another page's copy), the
 * checks a link, picture or text value passes, the texts the site
 * translation stores with the page, and the editor's wiring (canvas,
 * Preview, the part that loads the model).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once __DIR__ . '/component_overrides_test.php';

// A promo card as the editor stores it: a title, a picture, a button link,
// and a sale badge that a place may hide.
function component_overrides_render_tree()
{
	return array(
		'_id' => 'root', 'type' => 'root', 'props' => array(), 'children' => array(
			array('_id' => 'sd_t', 'type' => 'content', 'props' => array('contentType' => 'heading', 'tag' => 'h2', 'text' => 'Spring sale',
				'_open' => array(array('id' => 'of_title', 'kind' => 'text', 'label' => 'Title'))), 'children' => array()),
			array('_id' => 'sd_p', 'type' => 'content', 'props' => array('contentType' => 'image', 'src' => '/a.jpg', 'alt' => 'Shoes',
				'_open' => array(array('id' => 'of_pic', 'kind' => 'picture', 'label' => 'Picture'))), 'children' => array()),
			array('_id' => 'sd_b', 'type' => 'content', 'props' => array('contentType' => 'link', 'text' => 'Shop now', 'href' => '/spring',
				'_open' => array(array('id' => 'of_link', 'kind' => 'link', 'label' => 'Button link'))), 'children' => array()),
			array('_id' => 'sd_s', 'type' => 'content', 'props' => array('contentType' => 'paragraph', 'text' => 'Sale',
				'_open' => array(array('id' => 'of_sale', 'kind' => 'shown', 'label' => 'Sale badge'))), 'children' => array()),
		),
	);
}

function component_overrides_render_html($tree)
{
	return _render_tree_node($tree, 0);
}

// A placing node without values writes the body it wrote before; with
// values the place marker stands just before the reference (after a
// widget's placement marker).
function test_component_overrides_place_marker_in_the_body()
{
	$node = array('_id' => 'sd_r', 'type' => 'shared_ref', 'props' => array('sharedId' => 7, 'sharedName' => 'Promo card'), 'children' => array());
	$before = _render_tree_node($node, 0);
	pg_assert_same('<!--pg-shared-ref:7-->' . "\n", $before, 'no values: the body as before');

	foreach (array(array(), new stdClass(), 'x', array('Bad id' => 'x', 'of_n' => 3)) as $i => $none) {
		$node['props']['_place'] = $none;
		pg_assert_same($before, _render_tree_node($node, 0), 'nothing that travels writes no marker, case ' . $i);
	}

	$node['props']['_place'] = array('of_title' => 'Autumn sale', 'of_sale' => false, 'of_pic' => array('src' => '/b.jpg', 'alt' => 'Boots', 'x' => 1));
	$html = _render_tree_node($node, 0);
	pg_assert_true((bool) preg_match('/^<!--pg-place:([A-Za-z0-9_-]+)--><!--pg-shared-ref:7-->\n$/', $html, $m), 'the place marker before the reference: ' . $html);
	pg_assert_same(array('of_title' => 'Autumn sale', 'of_sale' => false, 'of_pic' => array('src' => '/b.jpg', 'alt' => 'Boots')),
		isset($m[1]) ? pg_co_place_decode($m[1]) : null, 'the values read back');

	$node['props']['placement'] = array('max_results' => 2);
	$html = _render_tree_node($node, 0);
	pg_assert_true(strpos($html, pg_sw_placement_marker(array('max_results' => 2)) . '<!--pg-place:') === 0, 'a placement marker stays first');

	pg_assert_same(array(), pg_co_place_decode('%%'), 'a payload that does not read is no values');
	pg_assert_same(array(), pg_co_place_decode(rtrim(strtr(base64_encode('"x"'), '+/', '-_'), '=')), 'nor one that is not an object');
}

// A link or picture that would run a script is left out (the component's
// own is drawn); a text's render markers and PHP tags are made inert.
function test_component_overrides_safe_values()
{
	$tree = component_overrides_render_tree();
	$safe = pg_co_safe_values($tree, array(
		'of_title' => 'A <!--pg-custom-php:eA--> <?php echo 1; ?>',
		'of_link'  => " JaVa\tScript:alert(1)",
		'of_pic'   => array('src' => 'data:text/html;base64,PHN2Zz4=', 'alt' => 'x'),
		'of_sale'  => 'no',
		'of_gone'  => 'closed',
	));
	pg_assert_same(array('of_title' => 'A <!-- pg-custom-php:eA--> &lt;?php echo 1; ?>'), $safe, 'only the text, made inert');

	$safe = pg_co_safe_values($tree, array('of_link' => 'mailto:a@b.c', 'of_pic' => 'data:image/png;base64,AAAA'));
	pg_assert_same(array('of_pic' => array('src' => 'data:image/png;base64,AAAA', 'alt' => null), 'of_link' => 'mailto:a@b.c'), $safe, 'a mail link and a picture\'s data:image stay');
	pg_assert_true(pg_co_address_runs_script('&#106;avascript:x'), 'entities are decoded');
	pg_assert_true(pg_co_address_runs_script('data:image/png;base64,AAAA'), 'a link may not be data:');
	pg_assert_false(pg_co_address_runs_script('/a.jpg', true), 'a plain address');
	pg_assert_same(array(), pg_co_safe_values($tree, 'x'), 'no values');
}

// The site draws each place with its own values, the component's other
// places as the component has them; the drawing of a place lives only in
// the call that drew it, so another page drawn after it (another page's
// cached copy) never holds it.
function test_component_overrides_cache_rule()
{
	$tree = component_overrides_render_tree();
	$own = array(7 => component_overrides_render_html($tree), 9 => '<!--pg-system-widget:9-->');
	$trees = array(7 => $tree);
	$calls = array();
	$render = function ($sid, $t) use (&$calls) {
		$calls[] = $sid;
		return component_overrides_render_html($t);
	};

	$a = array('of_title' => 'Autumn sale', 'of_sale' => false);
	$b = array('of_link' => '/winter');
	$marker_a = pg_co_place_marker($a);
	$marker_b = pg_co_place_marker($b);
	$page_a = '<main>' . $marker_a . '<!--pg-shared-ref:7-->|' . $marker_b . '<!--pg-shared-ref:7-->|<!--pg-shared-ref:7-->|'
		. $marker_a . '<!--pg-shared-ref:7--></main>';
	$out_a = _pg_shared_refs_replace($page_a, $own, $trees, $render);
	$parts = explode('|', substr($out_a, 6, -7));

	pg_assert_same(4, count($parts), 'four places drawn');
	pg_assert_contains('Autumn sale', $parts[0], 'the first place has its title');
	pg_assert_false(strpos($parts[0], '>Sale<') !== false, 'and hides the badge');
	pg_assert_contains('href="/winter"', $parts[1], 'the second place has its link');
	pg_assert_contains('Spring sale', $parts[1], 'and the component\'s title');
	pg_assert_same($own[7], $parts[2], 'a place without values is the component byte for byte');
	pg_assert_same($parts[0], $parts[3], 'the same values draw the same');
	pg_assert_same(array(7, 7), $calls, 'drawn once per values in the call');

	$page_b = '<main><!--pg-shared-ref:7--></main>';
	$calls = array();
	pg_assert_same('<main>' . $own[7] . '</main>', _pg_shared_refs_replace($page_b, $own, $trees, $render), 'the next page draws the component\'s own');
	pg_assert_same(array(), $calls, 'without a place marker nothing is drawn again');
	$out_a2 = _pg_shared_refs_replace($page_a, $own, $trees, $render);
	pg_assert_same($out_a, $out_a2, 'the first page drawn again is the same');
	pg_assert_same(array(7, 7), $calls, 'and drawn anew: nothing kept between calls');

	$unsafe = pg_co_place_marker(array('of_link' => 'javascript:alert(1)', 'of_gone' => 'x'));
	$calls = array();
	pg_assert_same($own[7], _pg_shared_refs_replace($unsafe . '<!--pg-shared-ref:7-->', $own, $trees, $render), 'values that cannot be drawn: the component\'s own');
	pg_assert_same(array(), $calls, 'and not drawn again');

	$placement = pg_sw_placement_marker(array('max_results' => 2));
	pg_assert_same($placement . '<!--pg-system-widget:9-->',
		_pg_shared_refs_replace($placement . $marker_a . '<!--pg-shared-ref:9-->', $own, $trees, $render), 'a widget keeps its placement, not a place marker');
	pg_assert_same('', _pg_shared_refs_replace($marker_a . '<!--pg-shared-ref:5-->', $own, $trees, $render), 'an unknown id draws nothing');
}

// A component drawn through _pg_shared_tree_html() with a place's values,
// as _expand_shared_refs() draws it.
function test_component_overrides_tree_html()
{
	$tree = component_overrides_render_tree();
	$html = _pg_shared_tree_html(7, pg_co_apply($tree, array('of_pic' => array('src' => '/b.jpg', 'alt' => 'Boots'))));
	pg_assert_contains('/b.jpg', $html, 'the place\'s picture');
	pg_assert_contains('Boots', $html, 'and its alt');
	pg_assert_false(strpos($html, '/a.jpg') !== false, 'not the component\'s');
}

// A place's texts enter the site translation as the page's own, under the
// placing node's id and the field's id.
function test_component_overrides_place_texts_for_translation()
{
	pg_tr_load();
	$tree = component_overrides_render_tree();
	$texts = pg_co_place_texts($tree, array('of_title' => 'Autumn sale', 'of_link' => '/x', 'of_sale' => false));
	pg_assert_same(1, count($texts), 'only the text field');
	pg_assert_same('Autumn sale', isset($texts[0]) ? $texts[0]['node']['props']['text'] : null, 'with the place\'s text');

	$position = 3;
	$segments = pg_tr_place_segments($tree, array('of_title' => 'Autumn sale'), 'sd_r', $position);
	pg_assert_same(1, count($segments), 'one segment');
	pg_assert_same('sd_r.of_title', isset($segments[0]) ? $segments[0]['node_id'] : null, 'under the place and the field');
	pg_assert_same('Autumn sale', isset($segments[0]) ? $segments[0]['text'] : null, 'the place\'s text');
	pg_assert_same(4, $position, 'the counter runs on');
	pg_assert_same(array(), pg_tr_place_segments(null, array('of_title' => 'x'), 'sd_r', $position), 'no tree, no segment');
	pg_assert_same(array(), pg_tr_place_segments($tree, array(), 'sd_r', $position), 'no values, no segment');
}

// The editor's node id is _id: the open-field list names it.
function test_component_overrides_node_id_is_the_editors()
{
	$fields = pg_co_open_fields(component_overrides_render_tree());
	pg_assert_same('sd_t', $fields[0]['nodeId'], 'the _id');
}

// The browser twin answers the same for the draw checks and the node id.
function test_component_overrides_render_twin()
{
	$tree = component_overrides_render_tree();
	$cases = array(
		array('of_title' => 'A <!--PG-x--> <?php', 'of_link' => 'vbscript:x', 'of_pic' => 'data:image/gif;base64,R0', 'of_sale' => false),
		array('of_link' => '/ok', 'of_pic' => array('src' => 'data:text/html,x')),
		array(),
	);
	$js = component_overrides_test_node('var tree = ' . json_encode($tree, JSON_UNESCAPED_UNICODE) . ', cases = ' . json_encode($cases) . ';'
		. ' out = {safe: cases.map(function (c) { return C.safeValues(tree, c); }), node: C.openFields(tree)[0].nodeId};');

	if ($js === null) {
		return;
	}

	foreach ($cases as $i => $case) {
		pg_assert_same(pg_co_safe_values($tree, $case), $js['safe'][$i], 'the same safe values, case ' . $i);
	}
	pg_assert_same('sd_t', $js['node'], 'the same node id');
}

// The canvas and the Preview draw a non-widget component through
// _sdPlaceDraw(); the model is the part 'overrides', handed to the editor
// and kept out of the core bundle.
function test_component_overrides_editor_wiring()
{
	$src = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');
	pg_assert_contains('_sdPlaceDraw(node, cached.tree, buildNodeEl)', $src, 'the canvas draws a place with its values');
	pg_assert_contains('_sdPlaceDraw(n, _prCached.tree, function (t) {', $src, 'the Preview and the code view too');
	pg_assert_contains("_sdPartLoad('overrides')", $src, 'the model loads on first use');

	$parts = pg_designer_parts();
	pg_assert_same('assets/js/style_designer_overrides.min.js', isset($parts['overrides']) ? $parts['overrides'] : null, 'the part');
	$bundles = pg_js_bundles();
	pg_assert_same('assets/js/pg_component_overrides.js', $bundles['assets/js/style_designer_overrides.min.js'][0], 'its first source is the model');
	pg_assert_false(in_array('assets/js/pg_component_overrides.js', $bundles['assets/js/style_designer.min.js'], true), 'not in the core');
	pg_assert_contains("'overrides', 'pages')", (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/designer_screen.php'), 'handed to the editor');
}
