<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for a dynamic region placed with the visual designer: the region
 * node's attribute is printed as the classic `<dregion "x">name</dregion>`
 * tag, so get_page_content.php hands it to the code as $dregion_attribute,
 * the same on the page (_render_region_tag()) and in the editor
 * (_sdRegionTag() in assets/js/style_designer.src.js, run under Node when it is
 * there); and the designer's code tab reads and saves the region's PHP only
 * with the classic screen's right (administrators) and a form token.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/panel/actions.php');

// The attribute get_page_content.php would hand to the code for a tag, read
// with its own two expressions; null when the tag is not found.
function _test_dregion_attribute_seen($html)
{
	if (!preg_match_all('/<dregion(.*?)>(.*?)<\/dregion>/i', $html, $dregions, PREG_SET_ORDER)) {
		return null;
	}

	$attribute = '';

	if ($dregions[0][1] != '') {
		preg_match('/"(.*?)"/', $dregions[0][1], $attribute_value);
		$attribute = $attribute_value[1];
	}

	return array('attribute' => $attribute, 'name' => $dregions[0][2]);
}

// The region nodes both sides are compared on.
function _test_dregion_cases()
{
	return array(
		'no attribute' => array('regionType' => 'dynamic', 'regionName' => 'News'),
		'empty attribute' => array('regionType' => 'dynamic', 'regionName' => 'News', 'regionAttr' => ''),
		'attribute' => array('regionType' => 'dynamic', 'regionName' => 'News', 'regionAttr' => 'sports'),
		'attribute with spaces' => array('regionType' => 'dynamic', 'regionName' => 'News', 'regionAttr' => '  top 5 & more '),
		'attribute breaking the tag' => array('regionType' => 'dynamic', 'regionName' => 'News', 'regionAttr' => "a\"b<?php x(); ?>\nc>"),
		'attribute on another type' => array('regionType' => 'common', 'regionName' => 'Footer', 'regionAttr' => 'x'),
	);
}

function test_dregion_attribute_clean()
{
	pg_assert_same('', pg_dregion_attribute_clean(null));
	pg_assert_same('', pg_dregion_attribute_clean(array('x')), 'not a string');
	pg_assert_same('sports', pg_dregion_attribute_clean(' sports '));
	pg_assert_same('a & b', pg_dregion_attribute_clean('a & b'), 'kept as typed, as in a style tag');
	pg_assert_same('ab?php x(); ?c', pg_dregion_attribute_clean("a\"b<?php x(); ?>\nc>"), 'no quote, angle bracket or line break');
	pg_assert_same(255, strlen(pg_dregion_attribute_clean(str_repeat('x', 300))));
}

function test_dregion_tag_carries_the_attribute()
{
	$cases = _test_dregion_cases();

	pg_assert_same('<dregion>News</dregion>', _render_region_tag($cases['no attribute']));
	pg_assert_same('<dregion>News</dregion>', _render_region_tag($cases['empty attribute']));
	pg_assert_same('<dregion "sports">News</dregion>', _render_region_tag($cases['attribute']));
	pg_assert_same('<cregion>Footer</cregion>', _render_region_tag($cases['attribute on another type']), 'only a dynamic region has one');
}

// What the page prints is what the code gets.
function test_dregion_attribute_reaches_the_code()
{
	$cases = _test_dregion_cases();

	pg_assert_same(array('attribute' => '', 'name' => 'News'), _test_dregion_attribute_seen(_render_region_tag($cases['no attribute'])));
	pg_assert_same(array('attribute' => 'sports', 'name' => 'News'), _test_dregion_attribute_seen(_render_region_tag($cases['attribute'])));
	pg_assert_same(array('attribute' => 'top 5 & more', 'name' => 'News'), _test_dregion_attribute_seen(_render_region_tag($cases['attribute with spaces'])));
	pg_assert_same(array('attribute' => 'ab?php x(); ?c', 'name' => 'News'), _test_dregion_attribute_seen(_render_region_tag($cases['attribute breaking the tag'])));
}

// The editor prints the same tags.
function test_dregion_tag_editor_twin()
{
	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);

	if ($code !== 0) {
		return;
	}

	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');
	$script = 'function esc(s) { if (!s) return ""; return String(s).replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;"); }';

	foreach (array(array('function _sdDregionAttr(', "\n    }\n"), array('function _sdRegionTag(', "\n    }\n")) as $part) {
		$from = strpos($source, $part[0]);
		$to = ($from !== false) ? strpos($source, $part[1], $from) : false;
		pg_assert_true(($from !== false) && ($to !== false), 'the designer has ' . $part[0]);

		if (($from === false) || ($to === false)) {
			return;
		}

		$script .= substr($source, $from, $to - $from + strlen($part[1]));
	}

	$cases = _test_dregion_cases();
	$script .= 'var cases = ' . json_encode(array_values($cases)) . ';'
		. 'console.log(JSON.stringify(cases.map(_sdRegionTag)));';

	$file = tempnam(sys_get_temp_dir(), 'pgdreg');
	file_put_contents($file, $script);
	$out = array();
	exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	unlink($file);

	pg_assert_same(0, $code, implode("\n", $out));

	$tags = json_decode(implode("\n", $out), true);
	$names = array_keys($cases);

	foreach (array_values($cases) as $i => $props) {
		pg_assert_same(_render_region_tag($props), isset($tags[$i]) ? $tags[$i] : null, $names[$i]);
	}
}

// The code tab's endpoint: past the general gate (session, role 1 or
// better) only for its own role check, which admits administrators alone,
// the right of edit_dynamic_region.php.
function test_dregion_code_action_row()
{
	$actions = pg_panel_actions();

	pg_assert_true(isset($actions['dynamic_region_code']), 'the action is listed');

	$entry = $actions['dynamic_region_code'];
	pg_assert_same('designer.php', $entry['file']);
	pg_assert_same('pg_panel_dynamic_region_code', $entry['handler']);
	pg_assert_false($entry['exempt'], 'the general gate runs first');
	pg_assert_true($entry['token']);
	pg_assert_true($entry['write']);
	pg_assert_false(!empty($entry['any_role']), 'no role 2 or 3 request reaches it');
}

function test_dregion_code_handler_checks()
{
	require_once(PG_FUNCTIONS_DIR . '/includes/panel/designer.php');

	$function = new ReflectionFunction('pg_panel_dynamic_region_code');
	$lines = file($function->getFileName());
	$source = implode('', array_slice($lines, $function->getStartLine() - 1, $function->getEndLine() - $function->getStartLine() + 1));

	$token = strpos($source, 'validate_token();');
	$role = strpos($source, "if ((int) \$user['role'] !== 0)");
	$query = strpos($source, 'dregion');

	pg_assert_true($token !== false, 'the token is checked');
	pg_assert_true($role !== false, 'administrators only');
	pg_assert_true(($token < $role) && ($role < $query), 'both before the table is touched');

	$edit = (string) file_get_contents(PG_FUNCTIONS_DIR . '/edit_dynamic_region.php');
	pg_assert_contains("validate_area_access(\$user, 'administrator');", $edit, 'the classic screen keeps the same right');

	$screen = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/designer_screen.php');
	pg_assert_contains("'dynamicRegionCode' => ((int)\$user['role'] === 0)", $screen, 'the editor offers the tab to administrators only');
}

// A save may change what a visitor sees; a read does not.
function test_dregion_code_page_cache_sub_actions()
{
	$sub_actions = pg_page_cache_panel_sub_actions();

	pg_assert_true(isset($sub_actions['dynamic_region_code']));
	pg_assert_same(array('save'), $sub_actions['dynamic_region_code']['dirty']);
	pg_assert_same(array('read'), $sub_actions['dynamic_region_code']['clean']);
}
