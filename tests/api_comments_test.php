<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for replies in the external API's comments
 * (includes/api/resources/comments.php): parent_id in the answer (null for a
 * comment that answers none and for a reply whose comment was removed), the
 * comments.list filter on it, their declarations, and the select that works
 * before the column exists (2026.11.0, 11.0.31).
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

require_once(PG_FUNCTIONS_DIR . '/includes/api/resources/comments.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/schema.php');

// The lines of a function, to read what it builds.
function api_comments_test_function_source($name)
{
	if (!function_exists($name)) {
		return '';
	}

	$function = new ReflectionFunction($name);
	$lines = file($function->getFileName());

	return implode('', array_slice($lines, $function->getStartLine() - 1, $function->getEndLine() - $function->getStartLine() + 1));
}

function test_api_comments_parent_value()
{
	pg_assert_same(null, api_comment_parent_value(array('parent_id' => 0)), 'a top-level comment');
	pg_assert_same(null, api_comment_parent_value(array('parent_id' => '0')), 'as the database answers');
	pg_assert_same(null, api_comment_parent_value(array()), 'no column');
	pg_assert_same(null, api_comment_parent_value(array('parent_id' => array(7))), 'not a value');
	pg_assert_same(7, api_comment_parent_value(array('parent_id' => 7)), 'a reply');
	pg_assert_same(7, api_comment_parent_value(array('parent_id' => '7')), 'a reply as the database answers');
}

function test_api_comments_parent_filter()
{
	pg_assert_same('(parent_comment.id IS NULL)', api_comment_parent_filter(0, true), 'comments that answer none, orphaned replies included');
	pg_assert_same("((comments.parent_id = '7') AND (parent_comment.id IS NOT NULL))", api_comment_parent_filter(7, true), 'the replies to comment 7');
	pg_assert_same(api_comment_parent_filter(7, true), api_comment_parent_filter('7', true), 'a string id');
	pg_assert_same('', api_comment_parent_filter(0, false), 'before the column every comment answers none');
	pg_assert_same('0', api_comment_parent_filter(7, false), 'and none is a reply');
}

function test_api_comments_parent_declared()
{
	$schema = api_comment_schema();

	pg_assert_same('integer?', isset($schema['parent_id']) ? $schema['parent_id'] : null, 'the field is declared');

	$param = null;

	foreach (api_schema() as $route) {
		if ($route['id'] !== 'comments.list') {
			continue;
		}

		foreach ($route['params'] as $candidate) {
			if ($candidate['name'] === 'parent_id') {
				$param = $candidate;
			}
		}

		pg_assert_contains('parent_id', $route['description'], 'the list says what parent_id is');
	}

	pg_assert_true(is_array($param), 'comments.list takes parent_id');

	if (is_array($param)) {
		pg_assert_same('int', $param['type'], 'a whole number');
		pg_assert_same(0, $param['min'], '0 is allowed');
		pg_assert_same('query', $param['in'], 'in the query');
	}

	pg_assert_true(strpos(api_comments_test_function_source('api_comment_present'), "'parent_id'       => api_comment_parent_value(\$row),") !== false, 'the answer carries it');
}

// Without the column (before 11.0.31, or without a connection) the select
// names no parent_id and joins nothing.
function test_api_comments_select_bridge()
{
	$select = api_comment_select();

	pg_assert_contains('0 AS parent_id', $select, 'a constant before the column exists');
	pg_assert_false(strpos($select, 'parent_comment') !== false, 'no join before the column exists');

	$source = api_comments_test_function_source('api_comment_select');

	pg_assert_contains('COALESCE(parent_comment.id, 0) AS parent_id', $source, 'a removed parent reads as none');
	pg_assert_contains('LEFT JOIN comments AS parent_comment ON parent_comment.id = comments.parent_id', $source, 'the parent is joined once the column exists');
	pg_assert_contains('pg_comment_replies_ready()', $source, 'asked through the probe');

	$list = api_comments_test_function_source('api_comments_list');

	pg_assert_true(strpos($list, "api_comment_parent_filter(\$params['parent_id'], pg_comment_replies_ready())") !== false, 'the list filters through the helper');
}
