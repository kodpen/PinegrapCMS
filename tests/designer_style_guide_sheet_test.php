<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The designer's Style guide sheet (the part 'styleguide'): one row of the
 * Preview menu and of the command palette, no bar button, loaded on first
 * use for designers only, and the server's style_guide read kept out of the
 * content roles' list and in the page cache's clean list.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_style_guide_sheet_is_a_designer_part()
{
	$files = pg_designer_part_files();
	pg_assert_true(isset($files['styleguide']), 'the part is described');
	pg_assert_same('designer', $files['styleguide']['when'], 'handed to designers only');
	pg_assert_same(array('assets/js/style_designer_styleguide.js'), $files['styleguide']['sources'], 'one source');
	$part = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer_styleguide.js');
	pg_assert_true(strpos($part, "StyleDesigner.definePart('styleguide'") !== false, 'the source defines the part');
	pg_assert_true(strpos($part, 'return { open: open, close: close };') !== false, 'the part answers open and close');
}

function test_style_guide_sheet_is_the_last_row_of_the_preview_menu()
{
	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');
	$share = strpos($js, 'id="sd-vb-share"');
	$row = strpos($js, 'id="sd-vb-styleguide"');
	pg_assert_true($share !== false && $row !== false && $row > $share, 'the row comes after Share the prototype');
	$line = substr($js, $share, $row - $share);
	pg_assert_true(strpos($line, '_sdStyleGuideReady()') !== false, 'the row is drawn only when the sheet can open');
	pg_assert_true(strpos($line, 'class="sd-vb-sep" role="separator"') !== false, 'under a line');
	pg_assert_true(strpos($js, "{ id: 'act_style_guide', label: _sdT('Style guide')") !== false, 'a row of the command palette');
	pg_assert_same(1, substr_count($js, "_sdPartCall('styleguide', 'open'"), 'one way in, the stub');
	pg_assert_false((bool) preg_match('/id="sd-[a-z-]*style-?guide[a-z-]*-btn"/i', $js), 'no bar button');
}

function test_style_guide_sheet_read_is_for_designers_and_clean()
{
	$panel = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/panel/designer.php');
	$from = strpos($panel, '$content_ok = array(');
	$to = strpos($panel, 'pg_designer_access($user) === PG_DESIGNER_ACCESS_NONE', (int) $from);
	pg_assert_true($from !== false && $to !== false, 'the content roles\' list');
	pg_assert_false(strpos(substr($panel, $from, $to - $from), 'style_guide') !== false, 'content roles do not read it');
	pg_assert_true(strpos($panel, "case 'style_guide':") !== false, 'the designer endpoint answers it');
	$subs = pg_page_cache_panel_sub_actions();
	pg_assert_true(in_array('style_guide', $subs['designer']['clean'], true), 'it changes no stored page');
}

function test_style_guide_sheet_prints_the_sheet_alone()
{
	$part = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer_styleguide.js');
	pg_assert_true(strpos($part, "_sdT('Print or save as PDF')") !== false, 'the head carries Print or save as PDF');
	pg_assert_true(strpos($part, "_sdT('Getting it ready…')") !== false, 'the button says it is getting ready');
	pg_assert_true(strpos($part, 'var win = W.frame.contentWindow;') !== false && strpos($part, 'win.print()') !== false, 'the frame prints, not the editor');
	pg_assert_true(strpos($part, '@page{') !== false, 'every page carries its head and foot');
	pg_assert_true(strpos($part, 'counter(pages)') !== false, 'the foot counts the pages');
	pg_assert_true(strpos($part, 'print-color-adjust:exact') !== false, 'colours print as colours');
	pg_assert_true(strpos($part, 'break-inside:avoid') !== false, 'a row or card is never split');
}

function test_style_guide_names_open_their_variable_or_component()
{
	$part = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer_styleguide.js');
	pg_assert_true(strpos($part, "_sdT('Open in Variables')") !== false, 'a variable name says where it leads');
	pg_assert_true(strpos($part, "_sdT('Open the component')") !== false, 'a component name says where it leads');
	pg_assert_true(strpos($part, 'host.openVariable') !== false && strpos($part, 'host.openComponent') !== false, 'the host opens them');
	$vars = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer_variables.js');
	pg_assert_true(strpos($vars, 'reveal: reveal') !== false, 'the Variables part opens one variable');
	pg_assert_true(strpos($vars, 'data-var-row=') !== false, 'each row names its variable');
	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');
	pg_assert_true(strpos($js, "_sdPartCall('variables', 'reveal'") !== false, 'the core hands the key to the Variables part');
	pg_assert_true(strpos($js, "edit_system_style.php?component=") !== false, 'a component opens on its own');
}

// A function of the part, cut out of its source: from "function name(" to
// the brace closing it at the part's indent.
function style_guide_sheet_test_function($name)
{
	$part = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer_styleguide.js');
	$from = strpos($part, "\n    function " . $name . '(');
	if ($from === false) return '';
	$to = strpos($part, "\n    }\n", $from);
	return $to === false ? '' : substr($part, $from, $to + 6 - $from) . "\n";
}

function style_guide_sheet_test_node($js)
{
	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);
	if ($code !== 0) return null;
	$file = tempnam(sys_get_temp_dir(), 'sdsg') . '.js';
	file_put_contents($file, $js);
	$lines = array();
	exec('node ' . escapeshellarg($file) . ' 2>&1', $lines, $code);
	@unlink($file);
	pg_assert_same(0, $code, 'the cut runs under Node: ' . implode("\n", $lines));
	return json_decode((string) end($lines), true);
}

// "Light | Dark" redraws the spacing bars: what it replaces is the bars'
// own wrapper, never the Spacing block itself, whose heading would go.
function test_style_guide_mode_switch_redraws_the_spacing_bars_only()
{
	$fns = '';
	foreach (array('attr', 'cssVal', 'block', 'varName', 'colourOf', 'spacingHtml', 'setMode') as $f) {
		$src = style_guide_sheet_test_function($f);
		pg_assert_true($src !== '', 'the part has ' . $f . '()');
		$fns .= $src;
	}
	$js = 'var esc = function (s) { return String(s); }; var _sdT = function (s) { return s; };'
		. 'var W = { dark: false, guide: { has_variables: false, blocks: { colours: { rows: [] }, spacing: { title: "Spacing", rows: [{ key: "space_unit", name: "1 unit", px: 4 }] } } } };'
		. $fns
		. 'var html = block("spacing", spacingHtml(W.guide.blocks.spacing));'
		. 'var ids = (html.match(/ id="[^"]+"/g) || []);'
		. 'var asked = []; W.el = { querySelectorAll: function () { return []; } };'
		. 'W.doc = { documentElement: { setAttribute: function () {} }, getElementById: function (id) { asked.push(id); return null; } };'
		. 'setMode(true);'
		. 'var spacingId = asked.filter(function (id) { return id.indexOf("spacing") !== -1; })[0] || "";'
		. 'console.log(JSON.stringify([ids.length === new Set(ids).size, html.indexOf(" id=\"" + spacingId + "\"") > html.indexOf("<section"), html.indexOf("<section class=\"pgsg-block\" id=\"" + spacingId + "\"") === -1]));';
	$r = style_guide_sheet_test_node($js);
	if ($r === null) return;
	pg_assert_same(array(true, true, true), $r, 'ids unique; the switch redraws the wrapper inside the block, not the block');
}

