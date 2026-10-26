<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the scripted hand fixes of the Bootstrap 6 switch in
 * tools/bs6_switch.php: each fix on a small source, a second run leaving
 * the result as it is, a changed source reported as missing, the layer
 * order against the dist, and every fix finding its place on today's tree.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(dirname(__DIR__) . '/tools/bs6_switch.php');

function bs6_switch_test_head()
{
	return "    </script>\n"
		. "    <link rel=\"stylesheet\" type=\"text/css\" href=\"' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/assets/lib/Jquery/ui/jquery-ui.min.css?v=' . @filemtime(PG_FUNCTIONS_DIR . '/assets/lib/Jquery/ui/jquery-ui.min.css') . '\"/>\n"
		. "    <link rel=\"stylesheet\" type=\"text/css\" href=\"' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/assets/lib/bootstrap-5.3.8/css/bootstrap.min.css?v=' . @filemtime(PG_FUNCTIONS_DIR . '/assets/lib/bootstrap-5.3.8/css/bootstrap.min.css') . '\"/>\n"
		. "    <link rel=\"stylesheet\" type=\"text/css\" href=\"' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/assets/lib/select2/select2.min.css\" />\n"
		. "    <script type=\"text/javascript\" src=\"' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/assets/lib/Jquery/jquery.min.js\"></script>\n"
		. "    <script type=\"text/javascript\" src=\"' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/assets/lib/bootstrap-5.3.8/js/bootstrap.bundle.min.js\"></script>\n"
		. "    <script type=\"text/javascript\" src=\"' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/assets/js/pg_ui.js\"></script>\n";
}

function test_bs6_switch_head()
{
	$result = bs6_switch_head(bs6_switch_test_head());
	$out = $result['source'];

	pg_assert_same('applied', $result['status']);
	pg_assert_false(strpos($out, 'bootstrap-5.3.8') !== false, 'no Bootstrap 5 left');
	pg_assert_contains('/assets/lib/bootstrap-6/css/bootstrap.min.css?v=', $out);
	pg_assert_contains('<script type="module" src="\' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . \'/assets/js/pg_bs6_bridge.js?v=', $out);
	pg_assert_contains('<style>@import url("\' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . \'/assets/lib/select2/select2.min.css") layer(vendor);</style>', $out);
	pg_assert_true(strpos($out, '<style>@layer ' . BS6_SWITCH_LAYER_ORDER . ';</style>') < strpos($out, 'jquery-ui.min.css'), 'layer order before the first sheet');
	pg_assert_true(strpos($out, 'pg_bs6_bridge.js') < strpos($out, 'pg_ui.js'), 'bridge before pgUI');

	pg_assert_same(array('source' => $out, 'status' => 'done'), bs6_switch_head($out), 'second run');
	pg_assert_same('missing', bs6_switch_head("<link href=\"x.css\">")['status']);
}

function test_bs6_switch_layer_order_matches_dist()
{
	$css = (string) file_get_contents(dirname(__DIR__) . '/pinegrap/assets/lib/bootstrap-6/css/bootstrap.css');
	preg_match('/@layer ([a-z, ]+);/', $css, $m);
	pg_assert_same(str_replace(' vendor,', '', BS6_SWITCH_LAYER_ORDER), $m[1], 'Bootstrap 6 order with vendor before custom');
}

function test_bs6_switch_sheet()
{
	$in = '<link rel="stylesheet" href="\' . $software . \'/assets/lib/bootstrap-5.3.8/css/bootstrap.min.css">';
	$result = bs6_switch_sheet($in);
	pg_assert_same('<link rel="stylesheet" href="\' . $software . \'/assets/lib/bootstrap-6/css/bootstrap.min.css">', $result['source']);
	pg_assert_same('done', bs6_switch_sheet($result['source'])['status']);
	pg_assert_same('missing', bs6_switch_sheet('<p>')['status']);
}

function test_bs6_switch_layer_sheet()
{
	$in = "@charset \"UTF-8\";\n\n/* note */\n.a { color: red; }\n";
	$result = bs6_switch_layer_sheet($in);
	pg_assert_same("@charset \"UTF-8\";\n\n" . BS6_SWITCH_LAYER_NOTE . "\n@layer custom {\n\n/* note */\n.a { color: red; }\n\n}\n", $result['source']);
	pg_assert_same('done', bs6_switch_layer_sheet($result['source'])['status']);

	$plain = bs6_switch_layer_sheet(".b { margin: 0; }")['source'];
	pg_assert_same(0, strpos($plain, BS6_SWITCH_LAYER_NOTE), 'no charset: the note first');

	$navbar = bs6_switch_navbar($result['source']);
	pg_assert_same('applied', $navbar['status']);
	pg_assert_true(strrpos($navbar['source'], '.software-navbar.fixed-top {') > strrpos($navbar['source'], "\n}\n\n"), 'after the layer block');
	pg_assert_same('done', bs6_switch_navbar($navbar['source'])['status']);
	pg_assert_same('missing', bs6_switch_navbar($in)['status'], 'needs the layered sheet');
}

function test_bs6_switch_style_blocks()
{
	$in = "echo '<style>.x { color: red; }</style>'; \$js = '<style type=\"text/css\">.y{}<\\/style>';"
		. " \$e = '<style></style>'; \$i = '<style>@import url(a.css);</style>'; \$c = '<style>' . CUSTOM_CSS . '</style>';";
	$result = bs6_switch_style_blocks($in);
	pg_assert_same('applied', $result['status']);
	pg_assert_contains("<style>@layer custom {.x { color: red; }}</style>", $result['source']);
	pg_assert_contains('<style type="text/css">@layer custom {.y{}}<\\/style>', $result['source'], 'escaped closing tag in JS');
	pg_assert_contains("'<style></style>'", $result['source'], 'empty block left');
	pg_assert_contains("<style>@import url(a.css);</style>", $result['source'], '@import left');
	pg_assert_contains("'<style>' . CUSTOM_CSS . '</style>'", $result['source'], 'site custom CSS left');
	pg_assert_same('done', bs6_switch_style_blocks($result['source'])['status']);
	pg_assert_same('missing', bs6_switch_style_blocks('<p>no style</p>')['status']);
}

function test_bs6_switch_drop_stacked_repair()
{
	$in = "            event.preventDefault();\n\n"
		. "            if (!target._pgStacked) {\n\n"
		. "                target._pgStacked = true;\n\n"
		. "                // comment\n"
		. "                pgUI.dialog.on(target, 'hidden', function () {\n"
		. "                    if (pgUI.dialog.isOpen(el)) {\n"
		. "                        document.body.classList.add('modal-open');\n"
		. "                    }\n"
		. "                });\n"
		. "            }\n\n"
		. "            pgUI.dialog.show(target);\n";
	$result = bs6_switch_drop_stacked_repair($in);
	pg_assert_same("            event.preventDefault();\n\n\n            pgUI.dialog.show(target);\n", $result['source']);
	pg_assert_same('done', bs6_switch_drop_stacked_repair($result['source'])['status']);
}

// Every fix finds its place on today's tree: a fix whose text moved on
// development fails here, not on switch day.
function test_bs6_switch_plan_finds_every_place()
{
	$root = dirname(__DIR__) . '/pinegrap';
	$missing = array();
	$sources = array();

	foreach (bs6_switch_plan($root) as $step) {
		list($path, $fix, $function) = $step;

		if (!isset($sources[$path])) {
			$sources[$path] = (string) @file_get_contents($root . '/' . $path);
		}

		$result = $function($sources[$path]);
		$sources[$path] = $result['source'];

		if ($result['status'] === 'missing') {
			$missing[] = "$fix pinegrap/$path";
		}
	}

	pg_assert_same(array(), $missing);
}

function test_bs6_switch_checks()
{
	$in = "<div class=\"form-check form-switch mb-3\">\n"
		. "  <input class=\"form-check-input\" type=\"checkbox\" role=\"switch\" id=\"a\"' . ((\$ttl > 0) ? ' checked' : '') . '>\n"
		. "  <label class=\"form-check-label\" for=\"a\">A</label>\n"
		. "</div>\n"
		. "<div class=\"form-check form-check-inline\"><input type=\"radio\" class=\"form-check-input\" id=\"b\"><label class=\"form-check-label small\" for=\"b\">B</label></div>\n"
		. "<div class=\"form-check\"><input class=\"form-check-input\" type=\"checkbox\" id=\"c\"></div>\n";
	$result = bs6_switch_checks($in);
	$out = $result['source'];

	pg_assert_same('applied', $result['status']);
	pg_assert_contains("<div class=\"form-field mb-3\">\n  <div class=\"switch\"><input class=\"\" type=\"checkbox\" role=\"switch\" id=\"a\"' . ((\$ttl > 0) ? ' checked' : '') . '></div>", $out, 'switch box past the PHP comparison');
	pg_assert_contains('<label class="" for="a">', $out);
	pg_assert_contains('<div class="form-field d-inline-grid me-5"><input type="radio" class="radio" id="b"><label class="small" for="b">', $out, 'inline radio');
	pg_assert_contains('<div class="form-field"><input class="check" type="checkbox" id="c"></div>', $out);
	pg_assert_same('done', bs6_switch_checks($out)['status'], 'second run');

	$field = "<div class=\"form-check form-switch\">\n    ' . \$liveform->output_field(array('type' => 'checkbox', 'name' => 'x', 'label' => lang('A (b)'), 'class' => 'form-check-input')) . '\n    <label class=\"form-check-label\" for=\"x\">X</label>\n</div>\n"
		. "<div class=\"form-check\">' . \$liveform->output_field(array('type' => 'radio', 'name' => 'y', 'class' => 'form-check-input')) . '</div>";
	$out = bs6_switch_checks($field)['source'];
	pg_assert_contains("<div class=\"form-field\">\n    <div class=\"switch\">' . \$liveform->output_field(array('type' => 'checkbox', 'name' => 'x', 'label' => lang('A (b)'), 'class' => '')) . '</div>\n", $out, 'liveform switch');
	pg_assert_contains("'type' => 'radio', 'name' => 'y', 'class' => 'radio'", $out, 'liveform radio by its type key');
}

function test_bs6_switch_selectors()
{
	$in = ".a .btn-primary:hover, .form-check-input:checked + .form-check-label, .col-md-6 > .p-3, .modal-content { color: red; }\n.x { background: url(a.text-white.png); }\n.form-select-sm, .text-white { margin: 0; }";
	$result = bs6_switch_selectors($in);

	pg_assert_same(".a .btn-solid.theme-primary:hover, :is(.check, .radio):checked + :is(.form-field > label), .md\\:col-6 > .p-3, .modal-content { color: red; }\n.x { background: url(a.text-white.png); }\n.form-control-sm, .fg-white { margin: 0; }", $result['source']);
	pg_assert_same('done', bs6_switch_selectors($result['source'])['status'], 'second run');
}

function test_bs6_switch_pins()
{
	$pairs = array(array("'bg-warning'", "'theme-warning'"));
	$result = bs6_switch_pins("a('bg-warning'); b('bg-warning');", $pairs);
	pg_assert_same(array('source' => "a('theme-warning'); b('theme-warning');", 'status' => 'applied'), $result);
	pg_assert_same('done', bs6_switch_pins($result['source'], $pairs)['status']);
	pg_assert_same('missing', bs6_switch_pins('changed', $pairs)['status']);
}
