<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for three places of the Visual Page Editor
 * (assets/js/style_designer.src.js) that must say what the site does: the
 * <body> the HTML panel and Preview show takes no class from the design's
 * name (the server writes none, generate_style_code_from_tree()); View Page
 * encodes each part of the page's name, so a "#" or "?" in it does not cut
 * the address; and the tab's rename box stops at the 100 characters the
 * page_name column holds. The helpers are cut out of the source and run
 * under Node; on a machine without Node those cases are skipped.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function designer_body_view_test_source()
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');
}

function designer_body_view_test_function($source, $name)
{
	$from = strpos($source, '    function ' . $name . '(');
	if ($from === false) {
		return '';
	}
	$to = strpos($source, "\n    }\n", $from);

	return ($to === false) ? '' : substr($source, $from, $to + 6 - $from);
}

// Runs $js after the named functions; null without Node.
function designer_body_view_test_run($names, $js)
{
	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);

	if ($code !== 0) {
		return null;
	}

	$source = designer_body_view_test_source();
	$code_js = "var window = { OUTPUT_PATH: '/' };\n"
		. "function esc(s) { return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/\"/g, '&quot;'); }\n";
	foreach ($names as $name) {
		$fn = designer_body_view_test_function($source, $name);
		pg_assert_true($fn !== '', $name . '() is found');
		if ($fn === '') {
			return null;
		}
		$code_js .= $fn . "\n";
	}

	$file = tempnam(sys_get_temp_dir(), 'pgbodyview');
	file_put_contents($file, $code_js . $js);
	$out = array();
	exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	unlink($file);

	pg_assert_same(0, $code, implode("\n", $out));

	return json_decode(implode("\n", $out), true);
}

// Every caller hands the body helper the tree and the design's additional
// body classes only, and the helper writes the classes the server writes.
function test_designer_body_class_not_from_design_name()
{
	$source = designer_body_view_test_source();
	preg_match_all('/_sdBodyAttrParts\(([^()]*(?:\([^()]*\)[^()]*)*)\)/', $source, $calls);
	pg_assert_true(count($calls[1]) >= 4, 'the helper and its three callers are found');

	foreach ($calls[1] as $args) {
		if (trim($args) === '') {
			continue;   // a comment naming the helper
		}
		pg_assert_same(1, substr_count($args, ','), 'two arguments: ' . $args);
		pg_assert_same(false, stripos($args, 'name'), 'no design name is passed: ' . $args);
	}

	$panel = designer_body_view_test_function($source, '_sdPageBodyAttrs');
	pg_assert_same(false, strpos($panel, '[name="name"]'), 'the HTML panel does not read the design name');

	$answer = designer_body_view_test_run(array('_sdBodyAttrParts'), <<<'JS'
console.log(JSON.stringify([
	_sdBodyAttrParts({ props: { cssClass: 'home', _attrs: [{ name: 'class', value: 'x' }, { name: 'data-a', value: '1' }] } }, 'extra one'),
	_sdBodyAttrParts({ props: {} }, '').cls
]));
JS
	);

	if ($answer === null) {
		return;
	}

	pg_assert_same(array('cls' => 'extra one home x', 'style' => '', 'attrs' => ' data-a="1"'), $answer[0], 'additional classes, the root class, the class attribute');
	pg_assert_same('', $answer[1], 'no class when nothing gives one');
}

// The link of View Page: each part of the name encoded, the slashes kept.
function test_designer_view_page_url_encodes_the_name()
{
	$answer = designer_body_view_test_run(array('_pgPageViewUrl'), <<<'JS'
console.log(JSON.stringify([
	_pgPageViewUrl({ page_id: 3584, savedName: 'pg-test a#b' }),
	_pgPageViewUrl({ page_id: 3584, savedName: 'klasör/alt sayfa' }),
	_pgPageViewUrl({ page_id: 3584, savedName: 'a?b' }),
	_pgPageViewUrl({ page_id: 3584, savedName: 'a&b' }),
	_pgPageViewUrl({ page_id: 0, savedName: 'x' })
]));
JS
	);

	if ($answer === null) {
		return;
	}

	pg_assert_same(array('/pg-test%20a%23b', '/klas%C3%B6r/alt%20sayfa', '/a%3Fb', '/a%26b', ''), $answer);
}

function test_designer_tab_rename_box_stops_at_the_column_length()
{
	$rename = designer_body_view_test_function(designer_body_view_test_source(), '_pgTabsRename');
	pg_assert_true($rename !== '', '_pgTabsRename() is found');
	pg_assert_contains('inp.maxLength = 100;', $rename, 'the rename box stops at 100 characters');

	$screen = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/designer_screen.php');
	pg_assert_contains('id="sd-page-name" class="form-control form-control-sm sd-page-field" data-page-key="page_name" maxlength="100"', $screen, 'the page name box of Page Settings too');
}
