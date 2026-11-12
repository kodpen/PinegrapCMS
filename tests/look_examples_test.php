<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The Design examples screen (design_examples.php): every class it names is
 * a rule of the stylesheet and appears in its own live example, both panes
 * keep their field ids apart, the screen is for the Designer role and above,
 * and System tools links it under the same gate.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/look_examples.php');

function test_look_examples_classes_are_rules_and_drawn()
{
	$css = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/css/backend.src.css');
	$sections = pg_look_examples('light');

	pg_assert_true(count($sections) >= 7, 'type, buttons, choice, tiles, fields, cards, rows');

	foreach ($sections as $section) {
		pg_assert_true(count($section['classes']) > 0, $section['id'] . ' names its classes');

		foreach ($section['classes'] as $name) {
			if ($name[0] === '.') {
				pg_assert_true(preg_match('/' . preg_quote($name, '/') . '(?![a-z0-9-])/', $css) === 1, $name . ' is a rule of backend.src.css');
				pg_assert_true(preg_match('/class="[^"]*\b' . preg_quote(substr($name, 1), '/') . '\b/', $section['html']) === 1, $name . ' is drawn in ' . $section['id']);
			} else {
				pg_assert_contains($name, $section['html'], $name . ' is used in ' . $section['id']);
			}
		}
	}
}

function test_look_examples_panes_keep_ids_apart()
{
	$light = pg_look_examples_pane('light');
	$dark = pg_look_examples_pane('dark');

	pg_assert_contains('data-bs-theme="light"', $light, 'the light pane');
	pg_assert_contains('data-bs-theme="dark"', $dark, 'the dark pane');
	preg_match_all('/\sid="([^"]+)"/', $light . $dark, $ids);
	pg_assert_same(count($ids[1]), count(array_unique($ids[1])), 'no id twice on the page');
	pg_assert_contains('data-bs-theme="light"', pg_look_examples_pane('nonsense'), 'anything else is light');
	pg_assert_true(count(pg_look_examples_not_done()) === 2, 'what is not a recipe yet, and what is not drawn');
}

function test_look_examples_screen_gate_and_link()
{
	$screen = (string) file_get_contents(PG_FUNCTIONS_DIR . '/design_examples.php');
	$registry = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/settings/registry.php');

	pg_assert_contains("validate_area_access(\$user, 'designer');", $screen, 'Designer and above');
	pg_assert_contains("pg_look_examples_pane('light')", $screen, 'the light pane');
	pg_assert_contains("pg_look_examples_pane('dark')", $screen, 'the dark pane');
	pg_assert_contains("if (\$role <= 1) {\n        \$tools[] = array('label' => lang('Design examples'), 'icon' => 'bi-palette', 'url' => 'design_examples.php');", $registry, 'System tools links it under the same gate');
	pg_assert_contains("'design_examples.php'       => array(", $registry, 'with search words');
}
