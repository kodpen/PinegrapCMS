<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for a classic designer region turned into the tree of a shared
 * component (includes/designer_region_import.php): PHP blocks lifted into
 * custom_php nodes before parsing, the whole region kept as one block when
 * its PHP cannot be cut apart, raw blocks kept verbatim, and the classic
 * placeholders as region nodes or nested shared components.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/designer_region_import.php');

// Every node of a tree, depth first, the root left out.
function _test_dri_nodes($nodes)
{
	$out = array();
	foreach ($nodes as $n) {
		$out[] = $n;
		if (!empty($n['children'])) {
			$out = array_merge($out, _test_dri_nodes($n['children']));
		}
	}
	return $out;
}

// The props of every node of one content type.
function _test_dri_content($tree, $type)
{
	$out = array();
	foreach (_test_dri_nodes($tree['children']) as $n) {
		if (($n['type'] === 'content') && ($n['props']['contentType'] === $type)) {
			$out[] = $n['props'];
		}
	}
	return $out;
}

// The props of every node of a node type.
function _test_dri_typed($tree, $type)
{
	$out = array();
	foreach (_test_dri_nodes($tree['children']) as $n) {
		if ($n['type'] === $type) $out[] = $n['props'];
	}
	return $out;
}

function _test_dri_convert($content, $php = true, $lookup = null)
{
	return pg_di_designer_region_tree($content, $lookup, array('php' => $php));
}

function test_designer_region_plain_html()
{
	$r = _test_dri_convert('<div class="box"><h2>Hello</h2><p>World</p></div>');
	pg_assert_same('none', $r['php']);
	pg_assert_same(1, count($r['tree']['children']));
	pg_assert_same('box', $r['tree']['children'][0]['props']['cssClass']);
	pg_assert_same('Hello', _test_dri_content($r['tree'], 'heading')[0]['text']);

	$empty = _test_dri_convert("  \n");
	pg_assert_same(array(), $empty['tree']['children'], 'an empty region is an empty tree');
}

function test_designer_region_php_blocks_lifted()
{
	$r = _test_dri_convert('<div><?php echo date("Y") ?></div><p>Hi <?= $_GET["n"] ?></p>');
	pg_assert_same('blocks', $r['php']);
	$php = _test_dri_content($r['tree'], 'custom_php');
	pg_assert_same(2, count($php));
	pg_assert_same('echo date("Y");', $php[0]['php'], 'the closing tag ended the statement');
	pg_assert_same('echo $_GET["n"];', $php[1]['php'], 'short echo');
	pg_assert_same(array(), $r['warnings'], 'superglobals are no warning');

	// Each lifted block runs as eval() runs it.
	foreach ($php as $p) {
		pg_assert_true(pg_di_php_block_whole(token_get_all('<?php ' . $p['php'])), 'whole: ' . $p['php']);
	}

	// The paragraph holding a block is built from nodes, not rich text.
	pg_assert_same(array(), _test_dri_content($r['tree'], 'paragraph'));
	pg_assert_same(1, count(array_filter(_test_dri_typed($r['tree'], 'semantic'), function ($p) { return $p['tag'] === 'p'; })));
}

function test_designer_region_php_kept_whole()
{
	$cases = array(
		'loop across blocks'      => '<ul><?php foreach ($rows as $r) { ?><li>x</li><?php } ?></ul>',
		'alternative syntax'      => '<?php if ($_GET["a"]): ?><b>a</b><?php endif; ?>',
		'shared variable'         => '<?php $n = 3; ?><p>x</p><?php echo $n; ?>',
		'inside an attribute'     => '<a href="<?php echo "/x"; ?>">x</a>',
		'inside a script'         => '<script>var a = <?php echo 1; ?>;</script>',
	);
	foreach ($cases as $label => $content) {
		$r = _test_dri_convert($content);
		pg_assert_same('whole', $r['php'], $label);
		pg_assert_same(1, count($r['tree']['children']), $label . ': one node');
		pg_assert_same('?>' . $content, $r['tree']['children'][0]['props']['php'], $label . ': the content unchanged');
	}
}

function test_designer_region_php_not_run_stays_text()
{
	$content = '<p><?php echo 1; ?></p>';
	$r = _test_dri_convert($content, false);
	pg_assert_same('text', $r['php']);
	pg_assert_same(array(), _test_dri_content($r['tree'], 'custom_php'), 'no code runs that did not run before');
	pg_assert_same($content, _test_dri_content($r['tree'], 'custom_html')[0]['html']);
}

function test_designer_region_scope_warning()
{
	$r = _test_dri_convert('<p><?php echo $page_id; ?></p>');
	pg_assert_same(1, count($r['warnings']));
	pg_assert_true(strpos($r['warnings'][0], '$page_id') !== false);

	$r = _test_dri_convert('<?php global $x; $y = 1; foreach (array(1) as $k => $v) { echo $x . $y . $k . $v; } ?>');
	pg_assert_same(array(), $r['warnings'], 'set, global and foreach names');
}

function test_designer_region_raw_blocks()
{
	$content = '<style>.a{color:red}</style><!-- note --><div>x</div><script>if (a < b) go();</script><pre>  a
  b</pre>';
	$r = _test_dri_convert($content);
	$raw = array();
	foreach (_test_dri_content($r['tree'], 'custom_html') as $p) $raw[] = $p['html'];
	pg_assert_same(array('<style>.a{color:red}</style>', '<!-- note -->', '<script>if (a < b) go();</script>', "<pre>  a\n  b</pre>"), $raw);
}

function test_designer_region_placeholders()
{
	$content = '<div><pregion></pregion><system>Cart</system><dregion "top 5">News</dregion>'
		. '<menu>Main</menu><div style="clear: both"></div><tcloud>Tags</tcloud><cart></cart>'
		. '<!--pg-comments-block--><cregion>Footer</cregion><cregion>Banner</cregion><cregion>Self</cregion></div>';
	$lookup = function ($name) {
		if ($name === 'Banner') return array('sharedId' => 7, 'sharedName' => 'Banner');
		if ($name === 'Self') return array('regionType' => 'designer');
		return null;
	};
	$r = _test_dri_convert($content, true, $lookup);
	$regions = _test_dri_typed($r['tree'], 'region');
	$got = array();
	foreach ($regions as $p) $got[] = $p['regionType'] . ':' . $p['regionName'];
	pg_assert_same(array('page:', 'system:Cart', 'dynamic:News', 'menu:Main', 'tag_cloud:Tags', 'cart:', 'comments_block:', 'common:Footer', 'designer:Self'), $got);
	pg_assert_same('top 5', $regions[2]['regionAttr']);
	pg_assert_same(array(array('sharedId' => 7, 'sharedName' => 'Banner')), _test_dri_typed($r['tree'], 'shared_ref'));
	// The menu's clearing div belongs to the tag the region node prints.
	$divs = array_filter(_test_dri_typed($r['tree'], 'semantic'), function ($p) { return $p['tag'] === 'div'; });
	pg_assert_same(1, count($divs), 'only the wrapper div is left');

	// Each region node prints the tag it came from.
	foreach ($regions as $p) {
		pg_assert_true(strpos($content, _render_region_tag($p)) !== false || $p['regionType'] === 'menu', 'prints ' . _render_region_tag($p));
	}
}

function test_designer_region_tag_in_raw_block_stays()
{
	$r = _test_dri_convert('<!-- <cregion>Old</cregion> --><p>x</p>');
	pg_assert_same(array(), _test_dri_typed($r['tree'], 'region'), 'a commented-out tag is no region');
	pg_assert_same('<!-- <cregion>Old</cregion> -->', _test_dri_content($r['tree'], 'custom_html')[0]['html']);
}

function test_designer_region_php_beside_placeholders()
{
	$r = _test_dri_convert('<header><?php echo 1; ?><cregion>Menu bar</cregion></header>');
	pg_assert_same('blocks', $r['php']);
	pg_assert_same(1, count(_test_dri_content($r['tree'], 'custom_php')));
	pg_assert_same(1, count(_test_dri_typed($r['tree'], 'region')));
}

// Writing a component from a designer region stays with administrators and
// designers: the sub-action is not one of the reads open to every role, and
// the editor offers it to the same roles.
function test_designer_region_convert_rights()
{
	$panel = file_get_contents(PG_FUNCTIONS_DIR . '/includes/panel/shared_component.php');
	pg_assert_true(preg_match('/\$_sc_read_only = array\(([^;]*)\);/s', $panel, $m) === 1, 'the read list');
	pg_assert_true(strpos($m[1], 'from_designer_region') === false, 'not a read');
	pg_assert_true(strpos($panel, "case 'from_designer_region':") !== false);

	$screen = file_get_contents(PG_FUNCTIONS_DIR . '/includes/designer_screen.php');
	pg_assert_true(strpos($screen, "'designerRegionConvert' => ((int)\$user['role'] <= 1)") !== false, 'the screen flag');
}
