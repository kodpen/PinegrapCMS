<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the visual editor's site-output markers: the code marked
 * `/* @pg-site-output *\/` in style_designer.js writes markup Bootstrap
 * 5.3.8 draws (a page, or the canvas), so the Bootstrap 6 codemod and
 * check_bs6.php leave it alone. Covers the marker ranges, the codemod
 * skipping them, and the markers in style_designer.js: the members that
 * must carry one, and a guard for a new member that builds nodes.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(dirname(__DIR__) . '/tools/bs6_rules.php');

// The text of each range, for short assertions.
function bs6_so_test_texts($source)
{
	$texts = array();

	foreach (bs6_site_output_ranges($source) as $range) {
		$texts[] = substr($source, $range[0], $range[1] - $range[0]);
	}

	return $texts;
}

// style_designer.js's top-level members (four-space declarations inside
// the module) as array(name => array('marked' => bool, 'body' => string)).
function bs6_so_test_members()
{
	$lines = explode("\n", (string) file_get_contents(dirname(__DIR__) . '/pinegrap/assets/js/style_designer.js'));
	$starts = array();

	foreach ($lines as $i => $line) {
		if (preg_match('/^    (?:async\s+)?function\s+([\w$]+)|^    (?:var|const|let)\s+([\w$]+)\s*=/', $line, $m)) {
			$starts[] = array($i, $m[1] !== '' ? $m[1] : $m[2]);
		}
	}

	$members = array();
	$count = count($starts);

	for ($k = 0; $k < $count; $k++) {
		$from = $starts[$k][0];
		$to = $k + 1 < $count ? $starts[$k + 1][0] : count($lines);
		$members[$starts[$k][1]] = array(
			'marked' => $from > 0 && trim($lines[$from - 1]) === '/* @pg-site-output */',
			'body' => implode("\n", array_slice($lines, $from, $to - $from)),
		);
	}

	return $members;
}

function test_bs6_site_output_ranges()
{
	$source = "    function chrome() {\n        return '<p class=\"text-muted\">';\n    }\n"
		. "    /* @pg-site-output */\n    function site(p) {\n        if (p) {\n            return '<p class=\"text-muted\">';\n        }\n"
		. "        return [\n            1\n        ];\n    }\n\n    // Next member.\n    var other = 1;\n";
	$texts = bs6_so_test_texts($source);

	pg_assert_same(1, count($texts));
	pg_assert_contains('function site(p)', $texts[0]);
	pg_assert_true(substr($texts[0], -6) === "    }\n" || substr($texts[0], -5) === '    }', 'ends at the closing brace');
	pg_assert_false(strpos($texts[0], 'Next member') !== false, 'the next comment is not covered');
	pg_assert_false(strpos($texts[0], 'chrome') !== false, 'the member above is not covered');

	$one_line = "    /* @pg-site-output */\n    var CLS = { a: 'btn btn-primary' };\n    var next = '<b class=\"text-muted\">';\n";
	pg_assert_same(array("    /* @pg-site-output */\n    var CLS = { a: 'btn btn-primary' };"), bs6_so_test_texts($one_line), 'one-line declaration');

	$line = "    x.classList.toggle('carousel-dark', on); /* @pg-site-output */\n    y.classList.add('text-muted');\n";
	pg_assert_same(array("    x.classList.toggle('carousel-dark', on); /* @pg-site-output */"), bs6_so_test_texts($line), 'a marker at the end of a line covers the line');

	pg_assert_same(array(), bs6_site_output_ranges("var a = '<p class=\"text-muted\">';\n"));
}

function test_bs6_site_output_kept_by_codemod()
{
	$source = "    function chrome() {\n        return '<p class=\"text-muted mb-2\">';\n    }\n"
		. "    /* @pg-site-output */\n    function site() {\n        return '<p class=\"text-muted mb-2\">';\n    }\n";
	$r = bs6_convert_source($source);

	pg_assert_same(1, $r['kept']);
	pg_assert_contains("    function chrome() {\n        return '<p class=\"fg-secondary mb-3\">';", $r['source']);
	pg_assert_contains("    function site() {\n        return '<p class=\"text-muted mb-2\">';", $r['source']);
}

function test_bs6_site_output_codemod_scope()
{
	pg_assert_true(bs6_codemod_converts('assets/js/style_designer.js'), 'marked editor file');
	pg_assert_true(bs6_codemod_converts('view_orders.php'), 'panel file');
	pg_assert_false(bs6_codemod_converts('assets/css/style_designer.css'), 'unmarked editor file');
	pg_assert_false(bs6_codemod_converts('assets/js/pg_style_predict.js'), 'the designer vocabulary is data');
	pg_assert_true(bs6_codemod_converts('includes/fn/forms.php'), 'shared file with panel functions');
	pg_assert_false(bs6_codemod_converts('liveform.class.php'), 'shared file with no panel function');
	pg_assert_true(bs6_codemod_converts('includes/fn/output.php'), 'the panel head, menu and footer');
	pg_assert_true(bs6_codemod_converts('includes/designer_screen.php'), 'the editor screen');
	pg_assert_false(bs6_codemod_converts('get_page.php'), 'site file');
}

// The members that write a page's markup (starter and palette trees,
// components, the page's HTML) or draw the canvas.
function test_bs6_site_output_members_marked()
{
	$members = bs6_so_test_members();
	$expected = array(
		'colProps', '_cfPaletteBlock', '_cfFrame', '_cfStarterTree', '_cfBuildFromFields', 'makeCarouselSlide',
		'_serializeRowToClasses', '_serializeColToClasses', '_sdLangSwitcherMarkup', 'COMPONENTS', 'CONTENT',
		'IFRAME_CSS', 'addCompositeChild', 'wrapNode', 'splitCell', 'buildNodeEl', 'buildInnerElement',
		'getNodeLabel', 'createFromPalette', 'onAddChild', 'setQuickLayout', 'getNodeHtmlTag',
		'_sdCodeNewSemantic', '_sdCodeNewSpan', '_sdCodeNewHolder', '_sdIlLinkNode', '_sdIlSplit',
		'_sdMigrateStructuralBindings', '_sdSplitTextLink', '_sdWrapForParent', '_sdWriteChoices', '_swBlankTree',
		'_sdListStarter', '_sdOrderListStarter', '_sdCalendarListStarter', '_sdProductListStarter',
		'_buildExpressOrderStarterTree', '_swFormFieldNodes', '_swAcctField', '_swAcctSelect', '_swAcctSubmit',
		'_swAcctLinks', '_swAcctSignedOut', '_swAcctPage', '_sdWishlistHeart', '_buildStarterTree',
		'_ensureMessagesNode', '_sdEmitNode', 'generateHTML', '_sdThemeHeadHtml', '_sdPreviewDocHtml',
		'_sdCanvasOpenRestore', '_sdPalInfo', '_sdPartEl', '_sdPartOrderTimeline', 'SD_OV_STATUS_CLASS_DEFAULTS',
		'_SD_HTML_PREVIEW_TOKENS',
	);

	foreach ($expected as $name) {
		pg_assert_true(isset($members[$name]) && $members[$name]['marked'], "$name carries /* @pg-site-output */");
	}
}

// A member that builds nodes (createNode(), a cssClass literal, a toHTML
// property) writes site markup: it is marked, or it is one of the panel's
// handlers below, whose own class lists are editor controls.
function test_bs6_site_output_node_builders_marked()
{
	$chrome = array(
		'createNode',               // the node factory: no classes
		'bindCtxOptsEvents',        // context options panel
		'initLeftPanel',            // palette, layers and dialogs
		'_wireSystemWidgetProps',   // system widget settings
		'propsButtonLinkOptions',   // button and link settings
		'bindPropEvents',           // properties panel; its canvas line is marked
	);

	foreach (bs6_so_test_members() as $name => $member) {
		if ($member['marked'] || in_array($name, $chrome, true)) {
			continue;
		}

		pg_assert_false(
			(bool) preg_match('/\bcreateNode\s*\(|\btoHTML\s*:|cssClass\s*:\s*[\'"][^\'"]/', $member['body']),
			"$name builds nodes: mark it /* @pg-site-output */ (docs/memory/ui/bootstrap-6-codemod.md)"
		);
	}
}

// A function of a panel file that a site renderer calls (directly or through
// another such function) prints into site pages: when the codemod would
// change a class in it, it must be listed in bs6_site_functions(), or a site
// page would get Bootstrap 6 classes.
function test_bs6_site_called_functions_are_listed()
{
	$root = PG_FUNCTIONS_DIR;
	$called = array();
	$bodies = array();

	foreach (bs6_scope_files($root) as $path => $kind) {
		if (!preg_match('/\.(php|js)$/', $path)) {
			continue;
		}

		$source = (string) file_get_contents($root . '/' . $path);

		if ($kind === 'site') {
			preg_match_all('/\b([A-Za-z_][A-Za-z0-9_]*)\s*\(/', $source, $m);
			$called += array_fill_keys($m[1], true);
		} elseif ($kind === 'panel' && preg_match('#^includes/.*\.php$#', $path)) {
			preg_match_all('/^function ([A-Za-z_][A-Za-z0-9_]*)\s*\(/m', $source, $m);
			foreach ($m[1] as $name) {
				$range = bs6_function_ranges($source, array($name));
				if ($range) {
					$bodies[$name] = array($path, substr($source, $range[0][0], $range[0][1] - $range[0][0]));
				}
			}
		}
	}

	$used = array();
	do {
		$grew = false;
		foreach ($bodies as $name => $body) {
			if (!isset($used[$name]) && isset($called[$name])) {
				$used[$name] = true;
				preg_match_all('/\b([A-Za-z_][A-Za-z0-9_]*)\s*\(/', $body[1], $m);
				$called += array_fill_keys($m[1], true);
				$grew = true;
			}
		}
	} while ($grew);

	$listed = bs6_site_functions();
	$missing = array();

	// After the switch a class Bootstrap 6 defines is not a v5 one.
	if (bs6_panel_head_is_v6($root)) {
		bs6_converted_tree_classes(bs6_dist_classes((string) file_get_contents($root . '/assets/lib/bootstrap-6/css/bootstrap.css')));
	}

	foreach (array_keys($used) as $name) {
		list($path, $body) = $bodies[$name];

		if (strpos($path, 'includes/oauth/') === 0 || (isset($listed[$path]) && in_array($name, $listed[$path], true))) {
			continue;
		}

		$result = bs6_convert_source($body, null, true);

		if ($result['changed'] > 0) {
			$missing[] = $path . ' ' . $name;
		}
	}

	bs6_converted_tree_classes(false);
	pg_assert_same(array(), $missing, 'site-called functions with Bootstrap classes');
}

function test_bs6_site_output_shared_file_outside_its_panel_functions()
{
	$source = "<?php\n\$top = '<p class=\"mb-2\">';\nfunction get_page_type_checkboxes_and_labels() {\n    return '<p class=\"mb-2\">';\n}\nfunction site_form() {\n    return '<p class=\"mb-2\">';\n}\n";
	bs6_current_path('includes/fn/forms.php');
	$ranges = bs6_site_output_ranges($source);
	$r = bs6_convert_source($source);
	bs6_current_path(false);

	pg_assert_same(2, count($ranges));
	pg_assert_contains("function get_page_type_checkboxes_and_labels() {\n    return '<p class=\"mb-3\">';", $r['source']);
	pg_assert_contains("\$top = '<p class=\"mb-2\">';", $r['source']);
	pg_assert_contains("function site_form() {\n    return '<p class=\"mb-2\">';", $r['source']);
}

function test_bs6_shared_panel_functions_exist()
{
	$root = dirname(__DIR__) . '/pinegrap/';
	foreach (bs6_shared_panel_functions() as $path => $names) {
		pg_assert_same('shared', bs6_scope_kind($path), $path);
		$source = (string) file_get_contents($root . $path);
		pg_assert_same(count($names), count(bs6_function_ranges($source, $names)), $path);
	}
}

function test_bs6_shared_panel_blocks_exist()
{
	$root = dirname(__DIR__) . '/pinegrap/';
	foreach (bs6_shared_panel_blocks() as $path => $blocks) {
		pg_assert_same('shared', bs6_scope_kind($path), $path);
		$source = (string) file_get_contents($root . $path);
		foreach ($blocks as $function => $anchor) {
			pg_assert_true(count(bs6_block_ranges($source, $function, $anchor)) > 0, "$path $function");
		}
	}
}

function test_bs6_site_output_shared_function_panel_block()
{
	$source = "<?php\nfunction get_calendar(\$user) {\n    if (\$user) {\n        \$a = '<a class=\"btn btn-outline-primary\">{';\n        if (\$x) { \$y = 1; }\n    } else {\n        \$a = '<a class=\"btn btn-outline-primary\">';\n    }\n    if (\$user) {\n        \$b = '<select class=\"form-select\">';\n    }\n}\nfunction other() {\n    if (\$user) {\n        return '<p class=\"mb-2\">';\n    }\n}\n";
	bs6_current_path('includes/fn/calendar.php');
	$r = bs6_convert_source($source);
	bs6_current_path(false);

	pg_assert_same(2, count(bs6_block_ranges($source, 'get_calendar', 'if ($user) {')));
	pg_assert_contains("if (\$user) {\n        \$a = '<a class=\"btn btn-outline theme-primary\">{';", $r['source']);
	pg_assert_contains("} else {\n        \$a = '<a class=\"btn btn-outline-primary\">';", $r['source']);
	pg_assert_contains("\$b = '<select class=\"form-control\">';", $r['source']);
	pg_assert_contains("return '<p class=\"mb-2\">';", $r['source']);
}

// System Info and get_login()'s default screen print inside
// output_header_secure() (Bootstrap 6); a login page's branches are site
// output.
function test_bs6_panel_screens_under_secure_header()
{
	pg_assert_same('panel', bs6_scope_kind('includes/templates/si.php'));
	pg_assert_true(bs6_codemod_converts('includes/templates/si.php'), 'System Info');

	$blocks = bs6_shared_panel_blocks();
	pg_assert_true(isset($blocks['get_login.php']['get_login']), 'get_login() default screen block');

	$source = "<?php\nfunction get_login(\$properties = array()) {\n    if ((int)\$page_id <= 0) {\n        \$a = '<p class=\"text-muted mb-2\">';\n    } elseif (\$layout_type == 'system') {\n        \$a = '<p class=\"text-muted mb-2\">';\n    }\n}\n";
	bs6_current_path('get_login.php');
	$r = bs6_convert_source($source);
	bs6_current_path(false);

	pg_assert_contains("if ((int)\$page_id <= 0) {\n        \$a = '<p class=\"fg-secondary mb-3\">';", $r['source']);
	pg_assert_contains("} elseif (\$layout_type == 'system') {\n        \$a = '<p class=\"text-muted mb-2\">';", $r['source']);
}
