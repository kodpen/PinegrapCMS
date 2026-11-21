<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The Design examples screen (design_examples.php): every class it names is
 * a rule of the stylesheet and appears in its own live example, both panes
 * keep their field ids apart, the patterns draw the screens they come from
 * (the folded settings pane, the connect row, a list's empty state), the
 * screen is for the Designer role and above, and System tools links it under
 * the same gate.
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
	pg_assert_true(count(pg_look_examples_patterns('light')) >= 3, 'settings pane, connect row, empty list');

	foreach (array_merge($sections, pg_look_examples_patterns('light')) as $section) {
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
	pg_assert_contains('id="pg_look_light_patterns"', $light, 'the patterns group under the parts');
	pg_assert_true(strpos($light, 'id="pg_look_light_patterns"') > strpos($light, 'id="pg_look_light_rows"'), 'after the last part');
	pg_assert_true(count(pg_look_examples_not_done()) === 2, 'what is not a recipe yet, and what is not drawn');
}

function test_look_examples_settings_pane_pattern()
{
	$patterns = array_column(pg_look_examples_patterns('dark'), 'html', 'id');

	pg_assert_true(isset($patterns['pattern-settings']), 'the settings pane');
	$html = $patterns['pattern-settings'];

	pg_assert_contains('class="pg-sm-heading"', $html, 'the purpose line under the pane title');
	pg_assert_same(1, substr_count($html, 'data-pg-folds'), 'one list of folded rows, drawn by pg_settings_fold()');
	pg_assert_true(substr_count($html, 'class="pg-set-fold-item') >= 4, 'a row a part');
	pg_assert_same(1, substr_count($html, 'pg-set-fold-item is-open'), 'one row open');
	pg_assert_same(substr_count($html, 'class="pg-set-fold-item'), substr_count($html, 'pg-set-fold-chevron'), 'each row its chevron');
	pg_assert_contains('id="pg-fold-dark-', $html, 'the pane\'s theme keeps the row ids apart');
	pg_assert_true(preg_match('/pg-set-fold-item is-open"[^>]*data-pg-fold-attention/', $html) === 1, 'the row that needs attention is the open one');
	pg_assert_true(substr_count($html, 'class="ws-agset-row') >= 2, 'the open row lists its things as rows');
	pg_assert_contains('aria-label="' . h(lang('Remove')) . '"', $html, 'a thing that was added can be removed from its row');
	pg_assert_same(0, substr_count($html, 'pg-button-fill'), 'the settings window\'s Save is the one filled button');
	pg_assert_true(preg_match('/class="pg-button pg-button-quiet pg-button-sm"><i class="bi bi-plus-lg" aria-hidden="true"><\/i>' . preg_quote(h(lang('Add a server')), '/') . '</', $html) === 1, 'Add is quiet with its plus');
	pg_assert_contains('<span class="pg-pill ws-agset-state pg-tone-gray">' . h(lang('Not connected')) . '</span>', $html, 'not connected is gray');
	pg_assert_true(strpos($html, 'pg-tone-amber">' . h(lang('Not connected'))) === false, 'never amber');
}

function test_look_examples_connect_row_and_empty_list()
{
	$patterns = array_column(pg_look_examples_patterns('light'), 'html', 'id');

	pg_assert_true(isset($patterns['pattern-connect']), 'the connect row');
	$html = $patterns['pattern-connect'];

	pg_assert_same(3, substr_count($html, 'pg-card-inner-row connections-row'), 'connected, a sign-in missing, not connected');
	pg_assert_contains('<span class="pg-pill pg-tone-teal">' . h(lang('Connected')) . '</span>', $html, 'connected');
	pg_assert_contains('<span class="pg-pill pg-tone-amber">' . h(lang('Needs attention')) . '</span>', $html, 'a sign-in missing');
	pg_assert_contains('<span class="pg-pill pg-tone-gray">' . h(lang('Not connected')) . '</span>', $html, 'not connected');
	pg_assert_true(preg_match('/' . preg_quote(h(lang('Not connected')), '/') . '<\/span>\s*<span class="pg-button pg-button-sm pg-button-quiet">' . preg_quote(h(lang('Connect')), '/') . '</', $html) === 1, 'not connected is followed by the button that connects');

	pg_assert_true(isset($patterns['pattern-empty']), 'a list\'s empty state');
	pg_assert_contains('class="ws-empty', $patterns['pattern-empty'], 'the settings list\'s empty state');
}

function test_look_examples_starting_point_sheet()
{
	$patterns = array_column(pg_look_examples_patterns('dark'), 'html', 'id');

	pg_assert_true(isset($patterns['pattern-start']), 'the starting-point sheet');
	$html = $patterns['pattern-start'];

	pg_assert_same(3, substr_count($html, 'class="pg-card overflow-hidden d-flex flex-column" style="max-width:760px"'), 'three steps, each in the standing frame at the sheet\'s width');
	pg_assert_same(3, substr_count($html, 'stepper stepper-horizontal'), 'each with the step line');
	pg_assert_same(3, substr_count($html, 'aria-current="step"'), 'one current step a frame');
	pg_assert_same(3, substr_count($html, 'stepper-item active theme-success'), 'step 2 has one finished step, step 3 two');
	pg_assert_same(count(api_app_templates(array(), array(), array())), substr_count($html, 'form-field form-field-card'), 'a card a starting point of Application Access');
	pg_assert_same(1, substr_count($html, '<div class="pg-start">'), 'the cards in the recipe\'s grid');
	pg_assert_contains('<span class="pg-pill pg-tone-teal">' . h(lang('Set up already')) . '</span>', $html, 'a card that opens what is there says so');
	pg_assert_contains('<details class="pg-start-advanced">', $html, 'what few people change is folded');
	pg_assert_true(strpos($html, '<details class="pg-start-advanced" open') === false, 'and closed');
	pg_assert_same(3, substr_count($html, 'class="pg-start-copy"'), 'the done step: address, key, secret');
	pg_assert_same(3, substr_count($html, 'pg-button pg-button-fill'), 'one filled button a step');
	pg_assert_contains('id="dark_look_start_signin"', $html, 'the pane\'s theme keeps the ids apart');
	pg_assert_contains('name="dark_look_start"', $html, 'and the radio group');

	$css = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/css/backend.src.css');
	pg_assert_contains('Look A: starting-point cards.', $css, 'the recipe in its own block');
	pg_assert_same(3, substr_count($html, 'class="drawer-header p-5 pb-1 d-block"'), 'the head\'s padding as #api_new measures: 16 16 4 16');
	pg_assert_same(3, substr_count($html, 'class="drawer-body p-5"'), 'the body\'s 16 px');
}

function test_look_examples_numbered_steps_drawer()
{
	$patterns = array_column(pg_look_examples_patterns('light'), 'html', 'id');

	pg_assert_true(isset($patterns['pattern-steps']), 'the numbered steps drawer');
	$html = $patterns['pattern-steps'];

	pg_assert_same(1, substr_count($html, '<ol class="stepper pg-stepper-cards ws-claude-setup-steps">'), 'the steps as the stepper, numbered by it');
	pg_assert_true(substr_count($html, 'stepper-item active ws-claude-step') >= 3, 'Claude\'s own set-up steps');
	pg_assert_true(preg_match('/<li[^>]*>\s*<div class="pg-field-label">\d/', $html) === 0, 'the number is never written in the markup');
	pg_assert_same(0, substr_count($html, 'pg-button-fill'), 'no filled button: the window\'s Save is the one');
	pg_assert_contains('class="pg-button pg-button-quiet pg-button-sm"><i class="bi bi-box-arrow-up-right" aria-hidden="true"></i>' . h(lang('Set up on claude.ai')), $html, 'one quiet button opens it');

	$css = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/css/backend.src.css');
	pg_assert_contains('.pg-stepper-cards > .stepper-item > * { grid-column: 2; }', $css, 'a step with several children keeps them in the content column');
	pg_assert_contains('class="drawer-header border-bottom p-5"', $html, 'the standing frame has the real drawer\'s 16 px');
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
