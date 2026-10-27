<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for pg_db_bind() in includes/fn/core.php, the pure binder behind
 * db_q(): each ? becomes a quoted, escaped value or a bare number, and a
 * template whose placeholders do not match the values is refused. db_q()
 * itself needs a connection.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Stands in for mysqli_real_escape_string(): a backslash before \, ' and ".
function db_q_test_escape($value)
{
	return addcslashes($value, "\\'\"");
}

function db_q_test_bind($sql, $params)
{
	return pg_db_bind($sql, $params, 'db_q_test_escape');
}

function test_db_q_binds_strings_quoted_and_escaped()
{
	pg_assert_same("SELECT id FROM page WHERE name = 'O\\'Brien'", db_q_test_bind('SELECT id FROM page WHERE name = ?', array("O'Brien")));
	pg_assert_same("SELECT 'a\\\\b'", db_q_test_bind('SELECT ?', array('a\\b')));
	pg_assert_same("SELECT ''", db_q_test_bind('SELECT ?', array('')));
}

function test_db_q_question_mark_in_a_value_is_not_expanded()
{
	pg_assert_same("UPDATE page SET title = 'Why?', name = 'x?y' WHERE id = 3", db_q_test_bind('UPDATE page SET title = ?, name = ? WHERE id = ?', array('Why?', 'x?y', 3)));
}

function test_db_q_binds_null_as_null()
{
	pg_assert_same('UPDATE page SET folder = NULL WHERE id = 1', db_q_test_bind('UPDATE page SET folder = ? WHERE id = ?', array(null, 1)));
}

function test_db_q_binds_numbers_bare()
{
	pg_assert_same('SELECT 42, -7, 0', db_q_test_bind('SELECT ?, ?, ?', array(42, -7, 0)));
	pg_assert_same('SELECT 1.5, -0.25, 2.0', db_q_test_bind('SELECT ?, ?, ?', array(1.5, -0.25, 2.0)));
}

function test_db_q_numeric_strings_stay_quoted()
{
	pg_assert_same("SELECT '42'", db_q_test_bind('SELECT ?', array('42')));
}

function test_db_q_binds_booleans_as_one_and_zero()
{
	pg_assert_same('SELECT 1, 0', db_q_test_bind('SELECT ?, ?', array(true, false)));
}

function test_db_q_refuses_a_count_mismatch()
{
	pg_assert_same(null, db_q_test_bind('SELECT ?, ?', array(1)), 'too few values');
	pg_assert_same(null, db_q_test_bind('SELECT ?', array(1, 2)), 'too many values');
	pg_assert_same(null, db_q_test_bind('SELECT 1', array(1)), 'no placeholder');
}

function test_db_q_no_placeholders_and_no_values()
{
	pg_assert_same('SELECT 1', db_q_test_bind('SELECT 1', array()));
}

function test_db_q_refuses_values_it_cannot_write()
{
	pg_assert_same(null, db_q_test_bind('SELECT ?', array(array(1))), 'array');
	pg_assert_same(null, db_q_test_bind('SELECT ?', array(new stdClass())), 'object');
	pg_assert_same(null, db_q_test_bind('SELECT ?', array(INF)), 'infinity');
	pg_assert_same(null, db_q_test_bind('SELECT ?', array(NAN)), 'not a number');
}

function test_db_q_values_are_taken_in_order_whatever_their_keys()
{
	pg_assert_same("SELECT 'a', 'b'", db_q_test_bind('SELECT ?, ?', array('x' => 'a', 'y' => 'b')));
}
