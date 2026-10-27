<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the token, stem and accordion fixes of the Bootstrap 6 switch
 * (tools/bs6_switch_tokens.php, tools/bs6_switch_stems.php,
 * bs6_switch_accordions() in tools/bs6_switch_shell.php) and the codemod's
 * reading of lone class strings printed into markup.
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

function test_bs6_switch_tokens_declarations_and_rules()
{
	$in = ":root,\n[data-bs-theme] {\n  --pg-bg-1: var(--bs-tertiary-bg);\n  --pg-radius-sm: var(--bs-border-radius-sm);\n  --pg-primary: var(--bs-primary);\n"
		. "  --pg-secondary: var(--bs-secondary);\n  --pg-primary-fg-emphasis: var(--bs-primary-text-emphasis);\n}\n"
		. ".a { color: var(--bs-secondary-color); border-radius: var(--bs-border-radius); background: var(--bs-body-bg-rgb); }\n"
		. ".b { outline-color: var(--bs-primary); background: var(--bs-dark); }\n";
	$result = bs6_switch_tokens($in);
	$out = $result['source'];

	pg_assert_same('applied', $result['status']);
	pg_assert_contains('--pg-bg-1: var(--bs-bg-1);', $out);
	pg_assert_contains('--pg-radius-sm: var(--bs-radius-3);', $out, 'the longer name is matched first');
	pg_assert_contains('--pg-primary: var(--bs-primary-base);', $out);
	pg_assert_contains('--pg-secondary: var(--bs-secondary-base);', $out, 'a value written for a declaration is not read again');
	pg_assert_contains('--pg-primary-fg-emphasis: var(--bs-primary-fg-emphasis);', $out);
	pg_assert_contains('.a { color: var(--pg-fg-2); border-radius: var(--pg-radius); background: var(--bs-body-bg-rgb); }', $out, 'a rule reads the token; a longer name is left');
	pg_assert_contains('.b { outline-color: var(--pg-primary); background: var(--bs-gray-900); }', $out, 'a bare colour reads the palette, a name with no token the v6 value');
	pg_assert_same(array('source' => $out, 'status' => 'done'), bs6_switch_tokens($out), 'second run');
}

function test_bs6_switch_rgb_variables()
{
	$in = ":root { --bs-body-bg-rgb: 255, 255, 255; --pg-x: rgb(var(--bs-navbar-bg-rgb)); }\n"
		. ".a { background: rgba(var(--bs-success-rgb),.3); box-shadow: 0 2px 8px rgba(var(--bs-primary-rgb), 0.18); }\n"
		. ".b { background-color: rgba(var(--bs-body-bg-rgb),60%); color: rgb(var(--bs-danger-rgb)); }\n";
	$result = bs6_switch_rgb_variables($in);

	pg_assert_same('applied', $result['status']);
	pg_assert_same(":root { --pg-body-bg-rgb: 255, 255, 255; --pg-x: rgb(var(--pg-navbar-bg-rgb)); }\n"
		. ".a { background: color-mix(in srgb, var(--pg-success) 30%, transparent); box-shadow: 0 2px 8px color-mix(in srgb, var(--pg-primary) 18%, transparent); }\n"
		. ".b { background-color: rgba(var(--pg-body-bg-rgb),60%); color: var(--pg-danger); }\n", $result['source']);
	pg_assert_same('done', bs6_switch_rgb_variables($result['source'])['status'], 'second run');
}

function test_bs6_switch_token_bridge()
{
	$sheet = BS6_SWITCH_LAYER_NOTE . "\n@layer custom {\n\n.x {}\n\n}\n";
	$result = bs6_switch_token_bridge($sheet);
	$out = $result['source'];

	pg_assert_same('applied', $result['status']);
	pg_assert_true(strpos($out, BS6_SWITCH_BRIDGE_NOTE) > strpos($out, "\n}\n"), 'after the layer, unlayered');
	pg_assert_contains('  --bs-primary-bg: var(--pg-primary);', $out);
	pg_assert_contains('  --bs-danger-contrast: var(--pg-on-accent);', $out);
	pg_assert_contains('  --bs-bg-body: rgb(var(--pg-body-bg-rgb));', $out);
	pg_assert_same(array('source' => $out, 'status' => 'done'), bs6_switch_token_bridge($out), 'second run');
	pg_assert_same('missing', bs6_switch_token_bridge('.x {}')['status']);
}

function test_bs6_switch_bridge_names_exist_in_dist()
{
	$css = (string) file_get_contents(dirname(__DIR__) . '/pinegrap/assets/lib/bootstrap-6/css/bootstrap.css');
	preg_match_all('/^  (--bs-[a-z0-9-]+):/m', bs6_switch_token_bridge_block(), $names);

	foreach ($names[1] as $name) {
		pg_assert_true(strpos($css, $name . ':') !== false, $name . ' is a Bootstrap 6 variable');
	}

	foreach (bs6_switch_token_values() as $v5 => $value) {
		if (preg_match_all('/var\((--bs-[a-z0-9-]+)\)/', $value, $used)) {
			foreach ($used[1] as $name) {
				pg_assert_true(strpos($css, $name . ':') !== false, $v5 . ' maps to ' . $name . ', a Bootstrap 6 variable');
			}
		}
	}
}

function test_bs6_switch_stems()
{
	$in = "<?php\n"
		. "\$tone = \$ok ? 'success' : 'danger';\n"
		. "\$a = '<span class=\"badge rounded-pill text-bg-' . h(\$label[1]) . '\">x</span>';\n"
		. "\$b = '<i class=\"bi me-3 text-' . \$tone . '\"></i>';\n"
		. "\$c = '<div class=\"col-6 col-lg-' . (\$wide ? '3' : '4') . '\">';\n"
		. "\$d = '<div class=\"alert alert-' . \$level . ' mb-3\">';\n"
		. "\$e = '<b class=\"text-' . (\$side === 'right' ? 'end' : 'start') . '\">';\n"
		. "\$f = '<a class=\"btn btn-' . \$tone . ' me-3\">';\n"
		. "\$g = '<span class=\"bg-' . h(\$tone) . '-subtle text-' . h(\$tone) . '-emphasis\">';\n";
	$result = bs6_switch_stems($in);
	$out = $result['source'];

	pg_assert_same('applied', $result['status']);
	pg_assert_contains('class="badge rounded-pill theme-\' . h($label[1]) . \'"', $out, 'a badge takes the theme class');
	pg_assert_contains('class="bi me-3 fg-\' . $tone . \'"', $out, 'every assigned value is a colour');
	pg_assert_contains('class="col-6 lg:col-\' . ($wide ? \'3\' : \'4\') . \'"', $out);
	pg_assert_contains('class="alert theme-\' . $level . \' mb-3"', $out);
	pg_assert_contains('class="text-\' . ($side === \'right\' ? \'end\' : \'start\') . \'"', $out, 'values that are not colours are left');
	pg_assert_contains('class="btn btn-solid theme-\' . $tone . \' me-3"', $out);
	pg_assert_contains('class="bg-subtle-\' . h($tone) . \' fg-emphasis-\' . h($tone) . \'"', $out);
	pg_assert_same(array('source' => $out, 'status' => 'done'), bs6_switch_stems($out), 'second run');

	pg_assert_same(array('success', 'danger', 'secondary'), bs6_stem_literals("h((\$s == 'ok') ? 'success' : ((\$s == 'fail') ? 'danger' : 'secondary'))"));
	pg_assert_same(null, bs6_stem_literals('$x . "y"'));
}

function test_bs6_switch_accordions()
{
	$in = "\$o = '<div class=\"accordion\" id=\"acc\">\n"
		. "    <div class=\"accordion-item\">\n"
		. "        <h2 class=\"accordion-header\">\n"
		. "            <button class=\"accordion-button' . (\$open ? '' : ' collapsed') . '\" type=\"button\" data-bs-toggle=\"collapse\" data-bs-target=\"#one\">\n"
		. "                <i class=\"bi bi-x me-3\"></i>' . lang('One') . '\n"
		. "            </button>\n"
		. "        </h2>\n"
		. "        <div id=\"one\" class=\"accordion-collapse collapse' . (\$open ? ' show' : '') . '\" data-bs-parent=\"#acc\">\n"
		. "            <div class=\"accordion-body\"><div>x</div></div>\n"
		. "        </div>\n"
		. "    </div>\n"
		. "</div>';\n";
	$result = bs6_switch_accordions($in);
	$out = $result['source'];

	pg_assert_same('applied', $result['status']);
	pg_assert_contains("<details class=\"accordion-item\" name=\"acc\"' . (\$open ? ' open' : '') . '>\n        <summary class=\"accordion-header\"><i class=\"bi bi-x me-3\"></i>' . lang('One') . ' <svg class=\"accordion-icon\"", $out);
	pg_assert_contains("<div class=\"accordion-body\"><div>x</div></div>\n    </details>\n</div>';", $out);
	pg_assert_false(strpos($out, 'accordion-collapse') !== false, 'no collapse box left');
	pg_assert_same('done', bs6_switch_accordions($out)['status'], 'second run');
}

function test_bs6_codemod_lone_strings_printed_into_markup()
{
	bs6_current_path('x.php');
	$php = bs6_convert_source("<?php\n\$c = \$ok ? 'btn-success' : 'btn-danger';\n", null, true);
	bs6_current_path('x.js');
	$js = bs6_convert_source("b.className = 'btn btn-sm ' + (on ? 'btn-primary' : 'btn-outline-secondary');\nb.classList.toggle('btn-warning', x);\nvar k = 'btn-danger';\n", null, true);
	bs6_current_path(false);

	pg_assert_contains("'btn-solid theme-success' : 'btn-solid theme-danger'", $php['source'], 'PHP code has no class API');
	pg_assert_contains("(on ? 'btn-solid theme-primary' : 'btn-outline theme-secondary')", $js['source'], 'a choice joined to a class string');
	pg_assert_contains("b.classList.toggle('btn-warning', x);", $js['source'], 'a class API argument takes one class');
	pg_assert_contains("var k = 'btn-danger';", $js['source']);
}
