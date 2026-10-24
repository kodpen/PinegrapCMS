<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the comments widget in the visual designer: the page comment
 * settings the editor reads and writes (pg_comment_editor_keys(), the save,
 * the templates), the Preview's page settings and audience, the canvas
 * states, sample comments and thread flag twin (under Node), the save
 * warnings about comments that are not drawn, and turning the fixed
 * comments block into the widget and back.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function comments_widget_designer_test_source($path)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $path);
}

// Whether the functions a test calls exist; a missing one fails the test
// once instead of stopping the run.
function comments_widget_designer_test_has($names)
{
	$ok = true;

	foreach ((array) $names as $name) {
		$ok = pg_assert_true(function_exists($name), $name . '() exists') && $ok;
	}

	return $ok;
}

// The source of a PHP function.
function comments_widget_designer_test_php_body($name)
{
	if (!function_exists($name)) {
		return '';
	}

	$fn = new ReflectionFunction($name);
	$lines = file($fn->getFileName());

	return implode('', array_slice($lines, $fn->getStartLine() - 1, $fn->getEndLine() - $fn->getStartLine() + 1));
}

// One function of style_designer.js, from its declaration to its closing
// brace.
function comments_widget_designer_test_js_function($js, $name)
{
	$start = strpos($js, "\n    function " . $name . '(');
	$end = ($start === false) ? false : strpos($js, "\n    }\n", $start);

	return (($start === false) || ($end === false)) ? '' : substr($js, $start, $end - $start + 6);
}

// Runs functions of style_designer.js and a script under Node. Returns the
// decoded JSON the script prints, or null when Node is missing (the test is
// then skipped) or a function is missing (counted as a failure).
function comments_widget_designer_test_node($names, $script)
{
	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);
	if ($code !== 0) {
		return null;
	}

	$js = comments_widget_designer_test_source('assets/js/style_designer.js');
	$code_js = '';
	$found = true;

	foreach ($names as $name) {
		$body = comments_widget_designer_test_js_function($js, $name);
		$found = pg_assert_true($body !== '', 'style_designer.js has ' . $name . '()') && $found;
		$code_js .= $body . "\n";
	}

	if (!$found) {
		return null;
	}

	$file = tempnam(sys_get_temp_dir(), 'pgcwd');
	file_put_contents($file, $code_js . $script);
	$out = array();
	exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	unlink($file);
	pg_assert_same(0, $code, 'the Node script runs: ' . implode("\n", array_slice($out, 0, 8)));
	$res = json_decode(implode("\n", $out), true);
	pg_assert_true(is_array($res), 'the script answers');

	return is_array($res) ? $res : null;
}

// The keys a JS array literal `var NAME = [...]` lists, in order.
function comments_widget_designer_test_js_list($js, $name)
{
	if (preg_match('/var ' . preg_quote($name, '/') . ' = \[(.*?)\];/s', $js, $m) !== 1) {
		return array();
	}

	preg_match_all("/'([a-z_]+)'/", $m[1], $keys);

	return $keys[1];
}

// The keys a JS object literal `var NAME = {...};` lists.
function comments_widget_designer_test_js_object_keys($js, $name)
{
	if (preg_match('/var ' . preg_quote($name, '/') . ' = \{(.*?)\};/s', $js, $m) !== 1) {
		return array();
	}

	preg_match_all('/\b([a-z_]+):/', $m[1], $keys);

	return $keys[1];
}

// -- the panel --------------------------------------------------------------------

// The comment settings section is shared by the fixed block and the widget,
// the widget's display options go through the generic handler, inner nodes
// get a shortcut to the settings, and a changed setting reaches the canvas
// and the live preview.
function test_comments_widget_designer_panel_wiring()
{
	$js = comments_widget_designer_test_source('assets/js/style_designer.js');

	$sect = comments_widget_designer_test_js_function($js, '_sdCommentSettingsSect');
	pg_assert_true($sect !== '', '_sdCommentSettingsSect is found');
	foreach (array("_cmtSwitch('pg_comments',", 'pg_comments_email_page', 'pg_comments_notify_email', 'sd-cmt-settings',
		"_sdT('Comment settings for {var}'", "_sdT('Saved with this page; other pages that show this widget keep their own.')") as $needle) {
		pg_assert_contains($needle, $sect, 'the settings section has ' . $needle);
	}

	$display = comments_widget_designer_test_js_function($js, '_sdCommentsDisplaySect');
	pg_assert_true($display !== '', '_sdCommentsDisplaySect is found');
	pg_assert_false(strpos($display, "_sdT('Order')") !== false, 'the order row is not the shop\'s "Order"');

	$region = comments_widget_designer_test_js_function($js, 'propsRegion');
	pg_assert_contains('_sdCommentSettingsSect(', $region, 'the region panel uses the shared section');
	pg_assert_false(strpos($region, 'data-cmt-prop') !== false, 'and keeps no copy of it');

	$ref = comments_widget_designer_test_js_function($js, 'propsSharedRef');
	pg_assert_contains('_sdCommentSettingsSect({ widget: true })', $ref, 'the widget panel uses the shared section');
	pg_assert_contains('_sdCommentsDisplaySect(', $ref, 'and the display options');

	$props = comments_widget_designer_test_js_function($js, 'renderProperties');
	pg_assert_contains('_sdCommentsSettingsShortcut(', $props, 'the shortcut is offered on inner nodes');
	pg_assert_contains("'sd-cmt-settings'", $props, 'and opens the settings section');
	pg_assert_contains("_sdOpenWidgetSection(sid, 'sd-sw-cf-source')", comments_widget_designer_test_js_function($js, '_cfOpenFormSettings'), 'the form shortcut shares the opener');

	pg_assert_contains('_sdLivePreviewSchedule()', comments_widget_designer_test_js_function($js, '_setCmtVal'), 'a changed setting refreshes the live preview');
	pg_assert_contains('_sdCommentSettingsChanged(', comments_widget_designer_test_js_function($js, 'bindPropEvents'), 'and redraws the canvas');
}

// The display options as the panel draws them: every option a select, the
// stored value selected (as pg_comments_widget_options() reads it), the
// reply depth only for threaded replies.
function test_comments_widget_designer_display_section()
{
	$script = <<<'JS'
function _sdT(s) { return s; }
function esc(s) { return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/"/g, '&quot;'); }
function sect(icon, title, body) { return '<section data-icon="' + icon + '" data-title="' + title + '">' + body + '</section>'; }
function row(label, ctrl) { return '<div class="row" data-label="' + label + '">' + ctrl + '</div>'; }
var out = {
    defaults: _sdCommentsDisplaySect(5, { regionType: 'comments' }),
    stored: _sdCommentsDisplaySect(5, { regionType: 'comments', order: 'newest', featured: 'filter', layout: 'threaded', max_depth: 7, per_page: '20' }),
    odd: _sdCommentsDisplaySect(5, { regionType: 'comments', order: 'x', featured: 3, layout: 'threaded', max_depth: '2', per_page: 15 })
};
process.stdout.write(JSON.stringify(out));
JS;

	$res = comments_widget_designer_test_node(array('_sdCommentsDisplaySect'), $script);
	if ($res === null) {
		return;
	}

	$selected = function ($html, $key) {
		$from = strpos($html, 'data-sw-cfg="' . $key . '"');
		if ($from === false) {
			return null;
		}
		$select = substr($html, $from, strpos($html, '</select>', $from) - $from);
		return (preg_match('/<option value="([^"]*)" selected>/', $select, $m) === 1) ? $m[1] : '';
	};

	foreach (array('order' => 'oldest,newest', 'featured' => 'first,filter,none', 'layout' => 'flat,threaded', 'per_page' => '0,10,20,50') as $key => $choices) {
		pg_assert_contains('data-sw-id="5" data-sw-cfg="' . $key . '" data-sw-cfg-kind="choice" data-sw-cfg-choices="' . $choices . '"', $res['defaults'], $key . ' is a choice');
	}
	pg_assert_contains('data-sw-cfg="max_depth" data-sw-cfg-kind="choice" data-sw-cfg-choices="1,2,3"', $res['stored'], 'the depth is a choice');
	pg_assert_contains('data-title="Display"', $res['defaults'], 'the section');
	pg_assert_contains('data-label="Comment order"', $res['defaults'], 'the order row is not the shop\'s "Order"');

	pg_assert_same(array('oldest', 'first', 'flat', null, '0'), array($selected($res['defaults'], 'order'), $selected($res['defaults'], 'featured'),
		$selected($res['defaults'], 'layout'), $selected($res['defaults'], 'max_depth'), $selected($res['defaults'], 'per_page')), 'the defaults; no depth for flat replies');
	pg_assert_same(array('newest', 'filter', 'threaded', '3', '20'), array($selected($res['stored'], 'order'), $selected($res['stored'], 'featured'),
		$selected($res['stored'], 'layout'), $selected($res['stored'], 'max_depth'), $selected($res['stored'], 'per_page')), 'stored values; the depth clamped');
	pg_assert_same(array('oldest', 'first', '2', '0'), array($selected($res['odd'], 'order'), $selected($res['odd'], 'featured'),
		$selected($res['odd'], 'max_depth'), $selected($res['odd'], 'per_page')), 'what the server reads as the default');
}
