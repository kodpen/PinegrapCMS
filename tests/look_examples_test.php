<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The Design examples screen (design_examples.php): every class it names is
 * a rule of the stylesheet and appears in its own live example, both panes
 * keep their field ids apart, the patterns draw the screens they come from
 * (the folded settings pane, the connect row, a list's empty state, the
 * parts of the e-mail settings drawing), the
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

function test_look_examples_form_drawer()
{
	$patterns = array_column(pg_look_examples_patterns('dark'), 'html', 'id');

	pg_assert_true(isset($patterns['pattern-form']), 'the form drawer');
	$html = $patterns['pattern-form'];

	pg_assert_same(1, substr_count($html, 'class="pg-card overflow-hidden d-flex flex-column" style="max-width:760px"'), 'one drawer in the standing frame at #api_drawer\'s width');
	pg_assert_same(1, substr_count($html, 'class="drawer-header p-5 pb-1 d-block"'), 'the head as the sheet measures it');
	pg_assert_same(1, substr_count($html, '<div class="pg-seg pg-list-seg" role="tablist">'), 'the tabs as the segmented control');
	pg_assert_same(5, substr_count($html, 'role="tab"'), 'Overview, Connect, Permissions, Events, Logs');
	pg_assert_same(1, substr_count($html, 'aria-selected="true"'), 'one tab chosen');
	pg_assert_contains('aria-labelledby="dark_look_form_tab"', $html, 'its pane named by the chosen tab');
	foreach (array('Details', 'Summary', 'Danger') as $label) {
		pg_assert_contains('>' . h(lang($label)) . '</div>', $html, 'the group ' . $label . ' under its label');
	}
	pg_assert_true(strpos($html, '>' . h(lang('Danger')) . '<') > strpos($html, '>' . h(lang('Summary')) . '<'), 'the danger group last');
	pg_assert_same(1, substr_count($html, 'class="pg-set-fold-item"'), 'Advanced folded, and closed');
	pg_assert_same(0, substr_count($html, 'pg-set-fold-item is-open'), 'nothing open');
	pg_assert_contains('id="pg-fold-dark-look-form-advanced"', $html, 'the pane\'s theme keeps the fold ids apart');
	pg_assert_same(1, substr_count($html, 'pg-button pg-button-fill'), 'Save is the one filled button');
	pg_assert_true(strpos($html, h(lang('Cancel')) . '</button><button type="button" class="pg-button pg-button-fill">' . h(lang('Save'))) !== false, 'Cancel quiet before Save in the foot');
	pg_assert_same(2, substr_count($html, 'pg-button pg-button-danger pg-button-sm'), 'Delete, and the confirm\'s Yes, delete');
	pg_assert_contains('>' . h(lang('Yes, delete')) . '</button>', $html, 'the confirm it opens');
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
	pg_assert_true((bool) preg_match('/class="drawer-header border-bottom[^"]*\bp-5"/', $html), 'the standing frame has the real drawer\'s 16 px');
	pg_assert_contains('pg-icon-tile pg-icon-tile-lg pg-tone-violet', $html, 'Claude\'s tile in the head, as the real drawer');
	pg_assert_contains('class="drawer-footer', $html, 'Close in the foot');
}

function test_look_examples_phone_pane()
{
	$pane = pg_look_examples_phone_pane();
	$css = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/css/backend.src.css');
	$screen = (string) file_get_contents(PG_FUNCTIONS_DIR . '/design_examples.php');

	preg_match_all('/<h3 class="pg-title-status" id="pg_look_phone_([^"]+)"/', $pane, $drawn);
	pg_assert_same(array('pattern-settings', 'pattern-form', 'pattern-doc-head', 'pattern-choice', 'pattern-stepline'), $drawn[1], 'the phone pane draws the marked patterns, in their order');
	preg_match_all('/\sid="([^"]+)"/', $pane, $ids);
	pg_assert_same(count($ids[1]), count(array_unique($ids[1])), 'no id twice in the phone pane');
	pg_assert_contains('<iframe class="pg-look-phone-frame" src="design_examples.php?phone=1" width="390"', pg_look_examples_phone_frame(), 'the frame is a phone\'s width');
	pg_assert_contains("width: 390px;\n  max-width: 100%;", $css, 'and stays inside a narrow window');
	pg_assert_contains("if (isset(\$_GET['phone'])) {", $screen, 'the screen draws the phone pane on its own page');
	pg_assert_true(strpos($screen, 'validate_area_access($user, \'designer\')') < strpos($screen, "isset(\$_GET['phone'])"), 'behind the same gate');
	pg_assert_contains('pg_look_examples_phone_frame()', $screen, 'and shows it under the two panes');

	$not_drawn = pg_look_examples_not_done()[1]['items'];
	pg_assert_true(!in_array(lang('Phone widths'), $not_drawn, true), 'phone widths left the not-drawn list');
	pg_assert_true(!in_array(lang('Dialogs and the settings modal'), $not_drawn, true), 'the settings pane is drawn; dialogs stay');
	pg_assert_true(in_array(lang('Dialogs'), $not_drawn, true), 'dialogs are not drawn yet');
}

function test_look_examples_mail_patterns_in_order()
{
	$ids = array_column(pg_look_examples_patterns('light'), 'id');
	$mail = array('pattern-tabs', 'pattern-values', 'pattern-choice', 'pattern-stepline', 'pattern-signin', 'pattern-result', 'pattern-confirm', 'pattern-confirm-go', 'pattern-switch');

	pg_assert_same($mail, array_slice($ids, -9), 'the e-mail drawing\'s seven parts, the confirm History takes back, then the switch rows, close the Patterns group');
	pg_assert_same(array('pattern-settings', 'pattern-form', 'pattern-doc-head', 'pattern-choice', 'pattern-stepline'), array_keys(array_filter(array_column(pg_look_examples_patterns('light'), 'phone', 'id'))), 'the phone pane takes the settings pane, the form drawer, the choice cards and the step line');
	pg_assert_contains('Look A: tabs with a state, value rows, choice cards, step line, provider', (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/css/backend.src.css'), 'the recipes in their own block');
}

function test_look_examples_tabs_with_a_state()
{
	$html = array_column(pg_look_examples_patterns('light'), 'html', 'id')['pattern-tabs'];

	pg_assert_same(2, substr_count($html, '<div class="pg-seg pg-seg-tall" role="tablist"'), 'the taller control, as tabs');
	pg_assert_same(4, substr_count($html, 'role="tab"'), 'two tabs a control');
	pg_assert_same(2, substr_count($html, 'aria-selected="true"'), 'one chosen a control');
	pg_assert_contains('<span class="pg-pill pg-tone-teal" title="' . h(lang('Ready')) . '"><span>' . h(lang('Ready')) . '</span></span>', $html, 'the state pill keeps its word as its title for the phone');
	pg_assert_contains('pg-tone-amber" title="' . h(lang('Sign-in needed')) . '"', $html, 'waiting on the person amber');
	pg_assert_contains('pg-tone-gray" title="' . h(lang('No box')) . '"', $html, 'nothing set up gray');

	$css = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/css/backend.src.css');
	pg_assert_true(preg_match('/\.pg-seg-tall \.pg-seg-item \{\s*height: 34px;/', $css) === 1, '34 px items');
}

function test_look_examples_value_rows()
{
	$html = array_column(pg_look_examples_patterns('light'), 'html', 'id')['pattern-values'];

	pg_assert_same(1, substr_count($html, 'class="pg-card-inner"'), 'one inner card');
	pg_assert_same(2, substr_count($html, 'class="pg-value-row"'), 'a row a kind of mail');
	pg_assert_same(2, substr_count($html, 'class="pg-button pg-button-quiet pg-button-sm"'), 'Change quiet small at each row\'s end');
	pg_assert_same(0, substr_count($html, 'pg-button-fill'), 'no filled button: the window\'s Save is the one');
	pg_assert_same(1, substr_count($html, 'class="pg-field-help"'), 'one meta line under the card');
}

function test_look_examples_choice_cards()
{
	$html = array_column(pg_look_examples_patterns('dark'), 'html', 'id')['pattern-choice'];

	pg_assert_same(2, substr_count($html, '<div class="pg-start pg-start-choice">'), 'the starting-point cards as a radio group');
	pg_assert_same(6, substr_count($html, 'form-field form-field-card'), 'four ways, then the two sign-in set-ups');
	pg_assert_same(6, substr_count($html, 'class="pg-start-mark"'), 'each card its mark at the head\'s end');
	pg_assert_same(2, substr_count($html, ' checked>'), 'one chosen a group');
	pg_assert_same(2, substr_count($html, 'class="d-block pg-start-note"'), 'a note at the card\'s end');
	pg_assert_same(1, substr_count($html, '<label class="pg-field-label"'), 'under the group only the chosen card\'s fields');
	pg_assert_contains('<i class="bi bi-plus-lg" aria-hidden="true"></i>' . h(lang('Connect another Google account')), $html, 'quiet with its plus');
	pg_assert_same(0, substr_count($html, 'pg-button-fill'), 'no filled button in the pattern');
	pg_assert_contains('name="dark_look_way"', $html, 'the pane keeps the radio groups apart');

	$css = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/css/backend.src.css');
	pg_assert_true(preg_match('/@media \(min-width: 576px\) \{\s*\.pg-start\.pg-start-choice \{\s*grid-template-columns: 1fr 1fr;/', $css) === 1, 'two a row, one under 576 px');
	pg_assert_contains('box-shadow: inset 0 0 0 2px var(--pg-accent), 0 0 0 4px', $css, 'the chosen card\'s ring');
}

function test_look_examples_step_line()
{
	$html = array_column(pg_look_examples_patterns('light'), 'html', 'id')['pattern-stepline'];

	pg_assert_same(3, substr_count($html, '<ol class="stepper pg-steps"'), 'the line at each of the three steps');
	pg_assert_same(9, substr_count($html, '<span class="pg-steps-name">'), 'a name a step, hidden on a phone but the current one');
	pg_assert_same(3, substr_count($html, 'aria-current="step"'), 'one current step a line');
	pg_assert_same(3, substr_count($html, 'stepper-item is-done'), 'step 2 has one finished step, step 3 two');
	pg_assert_true(preg_match('/<li[^>]*>\s*\d/', $html) === 0, 'the number is never written in the markup');

	$css = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/css/backend.src.css');
	pg_assert_contains('content: "\F26E";', $css, 'a finished step shows the check');
	pg_assert_contains('.pg-steps .stepper-item:not([aria-current="step"]) > .pg-steps-name {', $css, 'on a phone the other steps keep their numbers');
}

function test_look_examples_provider_sign_in()
{
	$html = array_column(pg_look_examples_patterns('light'), 'html', 'id')['pattern-signin'];

	pg_assert_same(2, substr_count($html, 'class="pg-card-inner pg-signin"'), 'the centred inner card');
	pg_assert_same(1, substr_count($html, '<button type="button" class="pg-signin-google"><svg width="18" height="18"'), 'Google\'s own button, its G at 18 px');
	pg_assert_same(1, substr_count($html, '<button type="button" class="pg-signin-microsoft"><svg width="21" height="21"'), 'Microsoft\'s own button, its squares at 21 px');
	pg_assert_same(0, substr_count($html, 'pg-button'), 'neither takes a look A button class');
	pg_assert_same(0, substr_count($html, 'pg-tone-'), 'nor a tone');

	$css = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/css/backend.src.css');
	pg_assert_contains("height: 40px;\n  border: 1px solid #747775;\n  border-radius: 4px;\n  color: #1f1f1f;", $css, 'Google\'s light button');
	pg_assert_contains("height: 41px;\n  border: 1px solid #8c8c8c;\n  border-radius: 0;\n  color: #5e5e5e;", $css, 'Microsoft\'s light button');
}

function test_look_examples_result_line()
{
	$html = array_column(pg_look_examples_patterns('light'), 'html', 'id')['pattern-result'];

	pg_assert_same(1, substr_count($html, '<div class="pg-result" role="status">'), 'the green line, read out');
	pg_assert_same(1, substr_count($html, 'class="pg-result-title"'), 'what happened');
	pg_assert_same(1, substr_count($html, 'class="pg-result-line"'), 'and one line more at most');
}

function test_look_examples_confirm_with_its_effects()
{
	$html = array_column(pg_look_examples_patterns('dark'), 'html', 'id')['pattern-confirm'];

	pg_assert_contains('role="dialog" aria-labelledby="dark_look_confirm_title"', $html, 'a dialog named by its title');
	pg_assert_contains('pg-icon-tile pg-icon-tile-lg pg-tone-rose', $html, 'the rose tile');
	pg_assert_same(1, substr_count($html, 'pg-effect pg-effect-keep'), 'what stays');
	pg_assert_same(2, substr_count($html, 'pg-effect pg-effect-go'), 'what goes');
	pg_assert_same(1, substr_count($html, 'pg-effect pg-effect-info'), 'what stays connected');
	pg_assert_true(strpos($html, 'pg-button-quiet') < strpos($html, 'pg-button-danger'), 'Cancel quiet, the danger button last');
	pg_assert_same(0, substr_count($html, 'pg-button-fill'), 'no filled button beside the danger one');
}

function test_look_examples_switch_rows()
{
	$html = array_column(pg_look_examples_patterns('dark'), 'html', 'id')['pattern-switch'];

	pg_assert_same(1, substr_count($html, '<div class="pg-set-fold">'), 'one card of rows, the folded rows\' frame');
	preg_match_all('/<div class="pg-set-switch-row[^"]*">(.*?)<\/div>(?=<div class="pg-set-switch-row|<\/div>)/', $html, $rows);
	pg_assert_same(8, count($rows[1]), 'a module a row');

	foreach ($rows[1] as $row) {
		$switches = substr_count($row, '<input type="checkbox" class="pg-switch"');
		$open = substr_count($row, 'class="pg-pill') + substr_count($row, h(lang('Open it')) . '<i class="bi bi-arrow-right"');
		pg_assert_true(($switches === 1 && $open === 0) || ($switches === 0 && $open === 2), 'one switch, or one state pill with Open');

		if ($switches === 1) {
			pg_assert_true(preg_match('/<label class="pg-set-fold-name" for="([^"]+)">.*id="\1"/', $row) === 1, 'the name is the switch\'s label');
			pg_assert_true(preg_match('/id="([^"]+)_line".*aria-describedby="\1_line"/', $row) === 1, 'the line is its description');
		}
	}

	pg_assert_same(0, preg_match('/<(a|button)[^>]*class="pg-set-switch-row/', $html), 'no row is a link or a button');
	pg_assert_same(2, substr_count($html, '>' . h(lang('Settings')) . '</button><input'), 'Settings quiet before the switch, on or off');
	pg_assert_same(1, substr_count($html, ' disabled>'), 'the row the viewer may not change');
	pg_assert_same(1, substr_count($html, 'pg-set-switch-row is-locked'), 'at half strength');
	pg_assert_same(1, substr_count($html, 'pg-set-switch-row is-off'), 'a category that is off greys its row');
	pg_assert_same(0, substr_count($html, 'pg-button-fill'), 'no filled button: the window\'s Save is the one');
	pg_assert_contains('class="pg-group-label-count"', $html, 'the count beside the group label');

	$css = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/css/backend.src.css');
	pg_assert_contains('.pg-set-switch-row:has(> .pg-switch:not(:checked)) .pg-icon-tile', $css, 'a switch turned off greys the tile at once');
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

function test_look_examples_flow_box()
{
	$html = array_column(pg_look_examples_patterns('dark'), 'html', 'id')['pattern-flow-box'];

	pg_assert_same(4, substr_count($html, 'class="pgc-node wsf-card '), 'four boxes as cards');
	pg_assert_same(4, substr_count($html, '<span class="pg-icon-tile wsf-node-tile" aria-hidden="true">'), 'each with its tile');
	pg_assert_same(4, substr_count($html, '<div class="wsf-node-text"><div class="wsf-node-head"><span class="wsf-node-kind">'), 'then the kind');
	pg_assert_same(1, substr_count($html, 'pgc-selected'), 'one chosen');
	pg_assert_same(1, substr_count($html, 'wsf-bad'), 'one with a problem');
	pg_assert_same(1, substr_count($html, 'wsf-node-warn'), 'which carries the mark');
	pg_assert_same(2, substr_count($html, '<rect class="pgc-edge-pill"'), 'both arrows of the Condition carry their outcome');
	$value = substr($html, (int) strpos($html, 'wsf-card wsf-group-values'));
	pg_assert_same(0, substr_count(substr($value, 0, (int) strpos($value, '<p ')), 'pgc-port'), 'a value box, the last, has no port');
	pg_assert_contains('id="dark-flow-arrow"', $html, 'the arrowhead is the pane\'s own');
}

// The confirm History takes back: blue tile, filled button, the History
// note, no danger button.
function test_look_examples_confirm_undone_by_history()
{
	$html = array_column(pg_look_examples_patterns('light'), 'html', 'id')['pattern-confirm-go'];

	pg_assert_contains('pg-icon-tile-lg pg-tone-blue', $html, 'a blue tile');
	pg_assert_contains('pg-button pg-button-fill', $html, 'a filled button');
	pg_assert_contains(h(lang('History keeps the page as it is now.')), $html, 'the History note');
	pg_assert_false(strpos($html, 'pg-button-danger') !== false, 'no danger button');
}
