<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the fields of an assistant's page operation
 * (includes/designer_ai.php): class names given as a list as well as one
 * string, any other field of the wrong shape refused by name, and an update
 * that carries lists changing the classes it names (never adding the word
 * "Array" with a PHP warning in front of the editor's answer).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/designer_ai.php');

// A page with one block, as the store template's campaign box.
function _designer_ai_op_fields_tree()
{
	return pg_design_ai_tree(array(
		'type'     => 'root',
		'_id'      => 'sd_1',
		'props'    => array(),
		'children' => array(
			array(
				'type'     => 'semantic',
				'_id'      => 'sd_2',
				'props'    => array('tag' => 'div', 'cssClass' => 'h-100 rounded-4 p-4 text-bg-primary d-flex'),
				'children' => array(
					array('type' => 'semantic', '_id' => 'sd_3', 'props' => array('tag' => 'h2', 'text' => 'Season'), 'children' => array()),
				),
			),
		),
	));
}

function test_designer_ai_op_fields_class_words_from_a_string_or_a_list()
{
	pg_assert_same(array('a', 'b'), pg_design_ai_class_words(' a  b '), 'one string');
	pg_assert_same(array('a', 'b', 'c'), pg_design_ai_class_words(array('a', 'b c')), 'a list');
	pg_assert_same(array(), pg_design_ai_class_words(null), 'left out');
	pg_assert_same(array(), pg_design_ai_class_words(array()), 'an empty list');
	pg_assert_same(null, pg_design_ai_class_words(array('a', array('b'))), 'a list inside the list');
	pg_assert_same(null, pg_design_ai_class_words(array('name' => array())), 'an object');
}

function test_designer_ai_op_fields_lists_of_classes_become_one_string()
{
	$read = pg_design_ai_op_fields(array('op' => 'update', 'node' => 'sd_2', 'add_classes' => array('pg-moda-hero', 'text-white'), 'remove_classes' => array('text-bg-primary'), 'classes' => array()));

	pg_assert_same('', $read['error'], 'no error');
	pg_assert_same('pg-moda-hero text-white', $read['op']['add_classes'], 'add_classes');
	pg_assert_same('text-bg-primary', $read['op']['remove_classes'], 'remove_classes');
	pg_assert_same('', $read['op']['classes'], 'an empty list is no class');

	$same = pg_design_ai_op_fields(array('op' => 'update', 'node' => 'sd_2', 'add_classes' => 'a  b'));
	pg_assert_same('a  b', $same['op']['add_classes'], 'a string stays as it came');
}

function test_designer_ai_op_fields_other_fields_must_be_words()
{
	foreach (array('op', 'node', 'parent', 'before', 'after', 'index', 'text', 'style', 'html', 'css', 'name') as $key) {
		$read = pg_design_ai_op_fields(array('op' => 'update', 'node' => 'sd_2', $key => array('x')));
		pg_assert_contains($key . ':', $read['error'], $key . ' as a list is refused by name');
	}

	$bad = pg_design_ai_op_fields(array('op' => 'update', 'node' => 'sd_2', 'add_classes' => array(array('x'))));
	pg_assert_contains('add_classes:', $bad['error'], 'a list of lists');

	$numbers = pg_design_ai_op_fields(array('op' => 'insert', 'parent' => 'sd_2', 'index' => 0, 'html' => '<p>a</p>'));
	pg_assert_same('', $numbers['error'], 'a number is a word');
}

// What Claude sent for the store template's campaign box on 2026-11-12: the
// classes to add and to remove as lists.
function test_designer_ai_op_fields_update_with_lists_changes_the_classes()
{
	$step = _pg_dai_op(_designer_ai_op_fields_tree(), array(
		'op'             => 'update',
		'node'           => 'sd_2',
		'add_classes'    => array('pg-moda-hero', 'text-white'),
		'remove_classes' => array('text-bg-primary'),
	));

	pg_assert_true($step['ok'], 'applied');

	$classes = preg_split('/\s+/', pg_design_ai_classes_of(pg_design_ai_node($step['tree'], 'sd_2')), -1, PREG_SPLIT_NO_EMPTY);

	pg_assert_true(in_array('pg-moda-hero', $classes, true), 'the class the page CSS styles is there');
	pg_assert_true(in_array('text-white', $classes, true), 'the second class is there');
	pg_assert_false(in_array('text-bg-primary', $classes, true), 'the class to remove is gone');
	pg_assert_false(in_array('Array', $classes, true), 'no class named Array');
}

function test_designer_ai_op_fields_update_with_an_object_for_text_is_refused()
{
	$step = _pg_dai_op(_designer_ai_op_fields_tree(), array('op' => 'update', 'node' => 'sd_3', 'text' => array('value' => 'New season')));

	pg_assert_false($step['ok'], 'refused');
	pg_assert_contains('text:', $step['error'], 'the field is named');
}
