<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the dialog shell of the Bootstrap 6 switch in
 * tools/bs6_switch_shell.php: modal markup to a <dialog> in PHP, HTML and
 * script strings, a form as the content box, roots made in a script,
 * offcanvas to a drawer, dropdown menus with their placement, the renamed
 * names and variables, and a second run leaving every result as it is.
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

function test_bs6_shell_dialog_markup()
{
	$in = "echo '\n<div class=\"modal fade pg-x\" id=\"m\" tabindex=\"-1\" aria-labelledby=\"m_t\" aria-hidden=\"true\">\n"
		. "    <div class=\"modal-dialog modal-lg modal-dialog-centered modal-fullscreen-md-down\">\n"
		. "        <div class=\"modal-content shadow\">\n"
		. "            <div class=\"modal-header\"><h5 class=\"modal-title\" id=\"m_t\">' . lang('T') . '</h5></div>\n"
		. "            <div class=\"modal-body\"><div><p>x</p></div></div>\n"
		. "        </div>\n    </div>\n</div>';\n";
	$result = bs6_switch_dialogs($in);
	$out = $result['source'];

	pg_assert_same('applied', $result['status']);
	pg_assert_contains("<dialog class=\"dialog pg-x dialog-lg md-down:dialog-fullscreen shadow\" id=\"m\" aria-labelledby=\"m_t\">\n            <div class=\"modal-header\">", $out);
	pg_assert_contains("<div class=\"modal-body\"><div><p>x</p></div></div>\n</dialog>';", $out);
	pg_assert_false(strpos($out, 'modal-dialog') !== false || strpos($out, 'modal-content') !== false, 'both boxes gone');
	pg_assert_same(array('source' => $out, 'status' => 'done', 'left' => 0), bs6_switch_dialogs($out), 'second run');
}

function test_bs6_shell_dialog_form_content()
{
	$in = "<div class=\"modal fade\" id=\"a\" tabindex=\"-1\">\n  <div class=\"modal-dialog\">\n    <form method=\"post\" class=\"modal-content disable_shortcut\">\n      <div class=\"modal-body\">x</div>\n    </form>\n  </div>\n</div>\n"
		. "<div class=\"modal fade\" id=\"b\" tabindex=\"-1\">\n  <div class=\"modal-dialog\">\n    <form method=\"post\">\n      <input type=\"hidden\" name=\"id\" value=\"1\">\n      <div class=\"modal-content\">\n        <div class=\"modal-body\">y</div>\n      </div>\n    </form>\n  </div>\n</div>\n";
	$out = bs6_switch_dialogs($in)['source'];

	pg_assert_same("<dialog class=\"dialog\" id=\"a\">\n    <form method=\"post\" class=\"d-contents disable_shortcut\">\n      <div class=\"modal-body\">x</div>\n    </form>\n</dialog>\n"
		. "<dialog class=\"dialog\" id=\"b\">\n    <form class=\"d-contents\" method=\"post\">\n      <input type=\"hidden\" name=\"id\" value=\"1\">\n        <div class=\"modal-body\">y</div>\n    </form>\n</dialog>\n", $out);
}

function test_bs6_shell_dialog_in_script()
{
	$in = "    node = el('div', 'modal fade ws-x');\n"
		. "    node.innerHTML = '<div class=\"modal-dialog modal-xl modal-dialog-scrollable\"><div class=\"modal-content\">'\n"
		. "        + '<div class=\"modal-body\"></div>'\n"
		. "        + '</div></div>';\n"
		. "    var b = document.createElement('div');\n    b.className = 'modal fade';\n"
		. "    b.innerHTML =\n        '<div class=\"modal-dialog modal-dialog-centered\">' +\n            '<div class=\"modal-content\">' +\n                '<div class=\"modal-body\"></div>' +\n            '</div>' +\n        '</div>';\n"
		. "    $('body').append('<div class=\"modal fade\" id=\"' + id + '\" tabindex=\"-1\"><div class=\"modal-dialog\">' +\n        '<div class=\"modal-content\"><div class=\"modal-body\"></div>' +\n        '</div></div></div>');\n";
	$out = bs6_switch_dialogs($in)['source'];

	pg_assert_contains("node = el('dialog', 'dialog ws-x dialog-xl dialog-scrollable');\n    node.innerHTML = '<div class=\"modal-body\"></div>';\n", $out);
	pg_assert_contains("var b = document.createElement('dialog');\n    b.className = 'dialog';\n    b.innerHTML =\n        '<div class=\"modal-body\"></div>';\n", $out);
	pg_assert_contains("$('body').append('<dialog class=\"dialog\" id=\"' + id + '\">' +\n        '<div class=\"modal-body\"></div>' +\n        '</dialog>');", $out);
}

// A root out of sight and a box with code among its classes in a script
// are left for a hand.
function test_bs6_shell_dialog_left_for_a_hand()
{
	$code = "n = el('div', 'modal fade');\nn.innerHTML = '<div class=\"modal-dialog ' + size + '\"><div class=\"modal-content\">' + '</div></div>';";

	foreach (array($code, "<section><div class=\"modal-dialog\"><div class=\"modal-content\">x</div></div></section>") as $in) {
		$result = bs6_switch_dialogs($in);
		pg_assert_same($in, $result['source']);
		pg_assert_same(1, $result['left']);
	}
}

function test_bs6_shell_drawers()
{
	$in = "<div class=\"offcanvas offcanvas-end pg-x\" tabindex=\"-1\" id=\"d\">\n  <div class=\"offcanvas-header\"></div>\n  <div class=\"offcanvas-body\"><div></div></div>\n</div>\nvar n = el('div', 'offcanvas offcanvas-start');";
	$result = bs6_switch_drawers($in);

	pg_assert_same("<dialog class=\"drawer drawer-end pg-x\" id=\"d\">\n  <div class=\"offcanvas-header\"></div>\n  <div class=\"offcanvas-body\"><div></div></div>\n</dialog>\nvar n = el('dialog', 'drawer drawer-start');", $result['source']);
	pg_assert_same('done', bs6_switch_drawers($result['source'])['status'], 'second run');
}

function test_bs6_shell_menus()
{
	$in = "<div class=\"dropdown\"><button class=\"btn dropdown-toggle\" data-bs-toggle=\"dropdown\">A</button>\n<ul class=\"dropdown-menu dropdown-menu-end shadow\"><li><a class=\"dropdown-item\">x</a></li></ul></div>\n"
		. "<div class=\"btn-group dropup\"><button data-bs-toggle=\"dropdown\">B</button><div class=\"dropdown-menu\"></div></div>\n"
		. "<ul class=\"dropdown-menu dropdown-menu-end\"></ul>";
	$result = bs6_switch_menus($in);

	pg_assert_same("<div class=\"pg-menu-wrap\"><button class=\"btn dropdown-toggle\" data-bs-toggle=\"dropdown\" data-bs-placement=\"bottom-end\">A</button>\n<ul class=\"menu pg-menu-end shadow\"><li><a class=\"dropdown-item\">x</a></li></ul></div>\n"
		. "<div class=\"btn-group pg-menu-wrap\"><button data-bs-toggle=\"dropdown\" data-bs-placement=\"top-start\">B</button><div class=\"menu\"></div></div>\n"
		. "<ul class=\"menu pg-menu-end\"></ul>", $result['source']);
	pg_assert_same(1, $result['left'], 'a menu with no toggle before it');
	pg_assert_same('done', bs6_switch_menus($result['source'])['status'], 'second run');
}

function test_bs6_shell_component_names()
{
	$in = "<button data-bs-toggle=\"dropdown\" class=\"btn dropdown-toggle\"></button><a class=\"dropdown-item\"></a><hr class=\"dropdown-divider\">\n"
		. "<button data-bs-dismiss=\"modal\"></button><div class=\"modal-body\"></div><h5 class=\"offcanvas-title\"></h5>\n"
		. "x.setAttribute('data-bs-dismiss', 'offcanvas'); document.querySelector('.modal.show .modal-content, .offcanvas.show'); el.closest('.modal');\n"
		. "s = '--bs-dropdown-link-hover-bg: red; --bs-offcanvas-width: 1px; --bs-modal-bg: 0';\n"
		. "/* @pg-site-output */\nfunction site() {\n    return '<div class=\"modal-body\" data-bs-dismiss=\"modal\"></div>';\n}\n";
	$result = bs6_switch_component_names($in);

	pg_assert_same("<button data-bs-toggle=\"menu\" class=\"btn pg-menu-caret\"></button><a class=\"menu-item\"></a><hr class=\"menu-divider\">\n"
		. "<button data-bs-dismiss=\"dialog\"></button><div class=\"dialog-body\"></div><h5 class=\"drawer-title\"></h5>\n"
		. "x.setAttribute('data-bs-dismiss', 'drawer'); document.querySelector('.dialog[open], .drawer[open]'); el.closest('.dialog');\n"
		. "s = '--bs-menu-item-hover-bg: red; --bs-drawer-width: 1px; --bs-dialog-bg: 0';\n"
		. "/* @pg-site-output */\nfunction site() {\n    return '<div class=\"modal-body\" data-bs-dismiss=\"modal\"></div>';\n}\n", $result['source']);
	pg_assert_same('done', bs6_switch_component_names($result['source'])['status'], 'second run');
}

function test_bs6_shell_rules_and_selectors()
{
	$sheet = BS6_SWITCH_LAYER_NOTE . "\n@layer custom {\n\n.a { color: red; }\n\n}\n";
	$result = bs6_switch_shell_rules($sheet);

	pg_assert_same('applied', $result['status']);
	pg_assert_true(strpos($result['source'], '.pg-menu-caret::after') < strpos($result['source'], '.a { color: red; }'), 'at the top of the layer');
	pg_assert_same('done', bs6_switch_shell_rules($result['source'])['status'], 'second run');
	pg_assert_same('missing', bs6_switch_shell_rules('.a {}')['status'], 'needs the layered sheet');

	$css = ".x .dropdown .dropdown-menu.dropdown-menu-end, .y .offcanvas.offcanvas-end .modal-title { color: red; }";
	pg_assert_same(".x .pg-menu-wrap .menu.pg-menu-end, .y .drawer.drawer-end .dialog-title { color: red; }", bs6_switch_selectors($css)['source']);
}

// A modal printed open becomes a scrim holding an open, non-modal dialog.
function test_bs6_shell_open_sheet()
{
	$in = "<div class=\"modal fade show\" id=\"s\" tabindex=\"-1\" style=\"display:block; background:rgba(0,0,0,.6)\">\n"
		. "\t<div class=\"modal-dialog modal-lg modal-dialog-centered\">\n\t\t<div class=\"modal-content\">\n\t\t\t<div class=\"modal-body\">x</div>\n\t\t</div>\n\t</div>\n</div>";
	$result = bs6_switch_dialogs($in);

	pg_assert_same("<div class=\"pg-open-sheet\" id=\"s\" style=\"background:rgba(0,0,0,.6)\">\n"
		. "\t<dialog class=\"dialog dialog-lg\" open>\n\t\t\t<div class=\"modal-body\">x</div>\n\t</dialog>\n</div>", $result['source']);
	pg_assert_same('done', bs6_switch_dialogs($result['source'])['status'], 'second run');
}
