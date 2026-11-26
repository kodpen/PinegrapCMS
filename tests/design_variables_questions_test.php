<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the design variables' start questions
 * (includes/fn/design_variables.php): the questions' data, the answers read
 * from a request, the model they give, and the start of a new design made
 * from them.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_design_variables_questions_are_five_with_their_choices()
{
	$q = pg_design_variables_questions();
	pg_assert_same(array('brand', 'accent', 'look', 'type', 'room'), array_keys($q['steps']), 'the five questions in order');
	pg_assert_same(10, count($q['swatches']), 'ten brand swatches');
	pg_assert_same(count(pg_design_looks()), count($q['looks']), 'every built-in look is offered');
	pg_assert_same(6, $q['shown'], 'six looks before "Show all"');
	pg_assert_same('modern-soft', $q['looks'][0]['key'], 'the looks in the Theme pane order');
	pg_assert_same(array('clean', 'classic', 'friendly', 'technical'), array_keys($q['types']), 'the four type feels');
	pg_assert_same(array('roomy', 'compact'), array_keys($q['rooms']), 'roomy or compact');
	foreach ($q['swatches'] as $hex) {
		pg_assert_true((bool)preg_match('/^#[0-9a-f]{6}$/', $hex), 'swatch ' . $hex . ' is a hex colour');
	}
}

function test_design_variables_type_feels_use_valid_fonts()
{
	$list = pg_design_variable_list();
	foreach (pg_design_variables_questions()['types'] as $key => $t) {
		foreach ($t['values'] as $var => $v) {
			pg_assert_true(isset($list[$var]), $key . ' sets the variable ' . $var);
			pg_assert_same($v, pg_design_variable_value($list[$var], $v), $key . ' gives a valid ' . $var);
		}
	}
}

function test_design_variables_answers_are_read_safely()
{
	$a = pg_design_variables_answers(array('brand' => '#7C3AED', 'accent' => 'auto', 'look' => 'playful', 'type' => 'classic', 'room' => 'compact', 'x' => 1));
	pg_assert_same(array('brand' => '#7c3aed', 'accent' => 'auto', 'look' => 'playful', 'type' => 'classic', 'room' => 'compact'), $a, 'answers kept');

	$bad = pg_design_variables_answers(array('brand' => 'red;}', 'accent' => 'x', 'look' => 'nope', 'type' => 'odd', 'room' => 1));
	pg_assert_same(array('brand' => '', 'accent' => '', 'look' => '', 'type' => '', 'room' => ''), $bad, 'refused answers read as skipped');

	pg_assert_same($bad, pg_design_variables_answers('{"brand":'), 'broken JSON reads as all skipped');
	$json = pg_design_variables_answers('{"brand":"#2563eb","accent":"#0f766e"}');
	pg_assert_same('#0f766e', $json['accent'], 'a picked second colour is kept');
}

function test_design_variables_all_skipped_change_nothing()
{
	$d = pg_design_variables_defaults('', '');
	$m = pg_design_variables_from_answers(null, pg_design_variables_answers(array()), $d);
	pg_assert_same($d['values'], $m['values'], 'skipping all five keeps the defaults');
}

function test_design_variables_answers_set_their_groups()
{
	$d = pg_design_variables_defaults('', '');
	$a = pg_design_variables_answers(array('brand' => '#7c3aed', 'accent' => 'auto', 'type' => 'classic', 'room' => 'roomy'));
	$m = pg_design_variables_from_answers(null, $a, $d);
	pg_assert_same(array('light' => '#7c3aed', 'dark' => '#7c3aed'), $m['values']['brand'], 'brand colour');
	pg_assert_true($m['values']['accent']['light'] !== '#7c3aed' && $m['values']['accent']['light'] !== $d['values']['accent']['light'], 'a second colour is worked out');
	pg_assert_same(pg_design_variables_questions()['types']['classic']['values']['font_heading'], $m['values']['font_heading'], 'classic sets a serif heading');
	pg_assert_same('1.25rem', $m['values']['space_unit'], 'roomy base unit');
	pg_assert_same('6rem', $m['values']['space_section'], 'roomy section rhythm');
	pg_assert_same($d['values']['radius'], $m['values']['radius'], 'shape is left to the look');
}

function test_design_variables_answers_keep_own_variables()
{
	$d = pg_design_variables_defaults('', '');
	$model = $d;
	$model['custom'] = array(array('key' => 'sand', 'name' => 'Sand', 'kind' => 'color', 'light' => '#eeddcc', 'dark' => '#443322'));
	$model['values']['radius'] = '1rem';
	$m = pg_design_variables_from_answers($model, pg_design_variables_answers(array('room' => 'compact')), $d);
	pg_assert_same('sand', $m['custom'][0]['key'], 'own variables stay');
	pg_assert_same($d['values']['radius'], $m['values']['radius'], 'the four groups are replaced');
	pg_assert_same('0.75rem', $m['values']['space_unit'], 'compact base unit');
}

function test_design_variables_companion_differs_and_is_hex()
{
	$c = pg_design_variables_companion('#7c3aed');
	pg_assert_true((bool)preg_match('/^#[0-9a-f]{6}$/', $c), 'a hex colour');
	pg_assert_true($c !== '#7c3aed', 'not the brand colour');
	pg_assert_same($c, pg_design_variables_companion('#7C3AED'), 'case does not matter');
	pg_assert_true((bool)preg_match('/^#[0-9a-f]{6}$/', pg_design_variables_companion('#808080')), 'a grey still gives a colour');
}

function test_design_variables_start_from_answers()
{
	$none = pg_design_variables_start('', '', '');
	pg_assert_same(array('look' => '', 'model' => null, 'css' => ''), $none, 'no answers: the design as it opens today');

	$look = pg_design_variables_start('{"look":"elegant"}', '', '');
	pg_assert_same('elegant', $look['look'], 'the feel sets the look');
	pg_assert_same(null, $look['model'], 'only the feel answered: no variables file');

	$s = pg_design_variables_start('{"brand":"#e11d48","look":"playful"}', '', '');
	pg_assert_same('playful', $s['look'], 'look from the feel');
	pg_assert_same('playful', $s['model']['look'], 'model made on the chosen look');
	pg_assert_same('#e11d48', $s['model']['values']['brand']['light'], 'brand in the model');
	pg_assert_true(strpos($s['css'], '/*! pg-theme {"kind":"variables"') === 0, 'a variables stylesheet to try on');
}

function test_design_variables_start_reaches_the_editor()
{
	$start = pg_design_variables_start('{"brand":"#e11d48","accent":"","look":"minimal","type":"classic","room":"roomy"}', '', '');
	$editor = pg_design_variables_start_editor($start);
	pg_assert_same($start['model'], json_decode($editor['input'], true), 'the model rides in the hidden style_variables input');
	pg_assert_same($start['css'], $editor['try'], 'its stylesheet is tried on the canvas at once');
	pg_assert_true(strpos($editor['try'], '#e11d48') !== false, 'the brand answer is in the stylesheet');

	$none = array('input' => '', 'try' => '');
	pg_assert_same($none, pg_design_variables_start_editor(null), 'no start answers: nothing');
	pg_assert_same($none, pg_design_variables_start_editor(pg_design_variables_start('{"look":"minimal"}', '', '')), 'only a feel: no variables');
}

// The editor's core declares `const StyleDesigner` at the top level, which is
// a global binding but never a property of window: a part that looked for
// window.StyleDesigner never registered, and "Ask me again" failed with
// "This part of the editor could not be loaded".
function test_design_variables_questions_part_registers_with_the_editor()
{
	$src = (string)file_get_contents(dirname(__DIR__) . '/pinegrap/assets/js/designer_questions.js');
	pg_assert_true(strpos($src, 'window.StyleDesigner') === false, 'the part does not look for window.StyleDesigner');
	pg_assert_true(strpos($src, "typeof StyleDesigner !== 'undefined' && StyleDesigner.definePart") !== false, 'the part registers through the global binding');
	$core = (string)file_get_contents(dirname(__DIR__) . '/pinegrap/assets/js/style_designer.src.js');
	pg_assert_true((bool)preg_match('/^const StyleDesigner = /m', $core), 'the core still declares StyleDesigner as a top-level const');
}
