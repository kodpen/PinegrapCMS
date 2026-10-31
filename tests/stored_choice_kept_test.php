<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests that an edit screen's select keeps the stored choice when its list
 * no longer offers it: Edit Product's private-access folder
 * (pg_pb_kept_option()) and the Workspace template editor's department and
 * task assignees (ws_template_kept_choices()). Without the kept option a
 * plain Save wrote folder 0, "No department" or "creator" over the record.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

if (!defined('PG_API_PANEL')) {
	define('PG_API_PANEL', true);
}

if (!defined('PG_INIT_LOADED')) {
	define('PG_INIT_LOADED', true);
}

require_once(PG_FUNCTIONS_DIR . '/product_builder.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/templates.php');

// A stored id the list already holds leaves the options as they are.
function test_stored_choice_kept_option_offered()
{
	$options = '<option value="4" selected class="private">Docs</option><option value="7" class="private">Club</option>';

	pg_assert_same($options, pg_pb_kept_option($options, 4, 'Docs', '(no longer offered)'));
	pg_assert_same($options, pg_pb_kept_option($options, '7', 'Club', '(no longer offered)'), 'a stored string id');
}

// Nothing stored adds nothing.
function test_stored_choice_kept_option_none()
{
	$options = '<option value="4">Docs</option>';

	pg_assert_same($options, pg_pb_kept_option($options, 0, '', '(no longer offered)'));
	pg_assert_same($options, pg_pb_kept_option($options, '', '', '(no longer offered)'), 'as a product row without one gives it');
	pg_assert_same($options, pg_pb_kept_option($options, null, '', '(no longer offered)'));
}

// A stored id the list left out comes back selected, its name escaped and
// marked, so Save posts it again.
function test_stored_choice_kept_option_missing()
{
	$options = '<option value="4">Docs</option>';

	pg_assert_same(
		$options . '<option value="12" selected>Old &amp; &lt;b&gt; (no longer offered)</option>',
		pg_pb_kept_option($options, '12', 'Old & <b>', '(no longer offered)')
	);

	// 1 is not 12: a prefix of another id does not count as offered.
	pg_assert_same(
		'<option value="12">A</option><option value="1" selected>B (x)</option>',
		pg_pb_kept_option('<option value="12">A</option>', 1, 'B', '(x)')
	);
}

// The template's own archived department and those its tasks name stay; an
// active department is not repeated and an unknown or archived-unnamed one
// is not offered.
function test_stored_choice_kept_template_departments()
{
	$departments = array(
		1 => array('id' => 1, 'name' => 'Sales', 'archived' => false),
		2 => array('id' => 2, 'name' => 'Old', 'archived' => true),
		3 => array('id' => 3, 'name' => 'Gone', 'archived' => true),
		4 => array('id' => 4, 'name' => 'Spare', 'archived' => true),
	);
	$template = array(
		'department_id' => 2,
		'body' => array('tasks' => array(
			array('assign' => 'department:3'),
			array('assign' => 'department:1'),
			array('assign' => 'department:99'),
			array('assign' => 'creator'),
		)),
	);

	$kept = ws_template_kept_choices($template, $departments, array());

	pg_assert_same(array(2, 3), $kept['department_ids']);
	pg_assert_same(array(), $kept['user_ids']);
}

// A person a task names who is not in the team list is kept once; the team
// members are not repeated.
function test_stored_choice_kept_template_people()
{
	$template = array(
		'department_id' => 0,
		'body' => array('tasks' => array(
			array('assign' => 'user:5'),
			array('assign' => 'user:8'),
			array('assign' => 'user:8'),
			array('assign' => 'department_lead'),
			array('title' => 'no rule'),
		)),
	);

	$kept = ws_template_kept_choices($template, array(), array(5, 6));

	pg_assert_same(array(), $kept['department_ids']);
	pg_assert_same(array(8), $kept['user_ids']);
}

// A template without tasks or with a broken body keeps only its department.
function test_stored_choice_kept_template_no_tasks()
{
	$departments = array(2 => array('id' => 2, 'name' => 'Old', 'archived' => true));

	$kept = ws_template_kept_choices(array('department_id' => 2, 'body' => 'x'), $departments, array());

	pg_assert_same(array(2), $kept['department_ids']);
	pg_assert_same(array(), $kept['user_ids']);
}
