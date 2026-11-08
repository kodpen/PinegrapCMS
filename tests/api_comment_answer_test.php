<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for POST /comments/{id}/answer (includes/api/resources/comments.php):
 * what writing a text does to a question's staff answer, the route's
 * declaration, its answer object, and the screen's write path it calls.
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
require_once(PG_FUNCTIONS_DIR . '/includes/api/openapi.php');

// The lines of a function, to read what it calls.
function api_comment_answer_test_source($name)
{
	if (!function_exists($name)) {
		return '';
	}

	$function = new ReflectionFunction($name);
	$lines = file($function->getFileName());

	return implode('', array_slice($lines, $function->getStartLine() - 1, $function->getEndLine() - $function->getStartLine() + 1));
}

function test_api_comment_answer_outcome()
{
	pg_assert_same('added', api_comment_answer_outcome(null, 'Yes, it fits.'), 'a first answer');
	pg_assert_same('changed', api_comment_answer_outcome('Yes.', 'Yes, it fits.'), 'a different text');
	pg_assert_same('unchanged', api_comment_answer_outcome('Yes, it fits.', 'Yes, it fits.'), 'the same text');
	pg_assert_same('unchanged', api_comment_answer_outcome('Yes, it fits.', "  Yes, it fits.\n"), 'the same text once trimmed, as the save trims it');
	pg_assert_same('removed', api_comment_answer_outcome('Yes.', ''), 'an empty text removes the answer');
	pg_assert_same('removed', api_comment_answer_outcome('Yes.', '   '), 'blanks only remove it too');
	pg_assert_same('unchanged', api_comment_answer_outcome(null, ''), 'nothing to remove');
	pg_assert_same('unchanged', api_comment_answer_outcome(str_repeat('a', 5000), str_repeat('a', 5010)), 'cut at 5000 characters like the save');
}

function test_api_comment_answer_route_declared()
{
	$found = null;

	foreach (api_schema() as $route) {
		if ($route['id'] === 'comments.answer') {
			$found = $route;
		}
	}

	pg_assert_true(is_array($found), 'the route is declared');

	if (!is_array($found)) {
		return;
	}

	pg_assert_same('POST', $found['method'], 'a POST');
	pg_assert_same('/comments/{id}/answer', $found['path'], 'its path');
	pg_assert_same('comments:write', $found['scope'], 'the moderation scope');
	pg_assert_true(!empty($found['dry_run']), 'rehearsable');
	pg_assert_same('CommentAnswer', $found['returns'], 'its answer object');

	$text = null;

	foreach ($found['params'] as $param) {
		if ($param['name'] === 'text') {
			$text = $param;
		}
	}

	pg_assert_true(is_array($text), 'takes text');

	if (is_array($text)) {
		pg_assert_same('body', $text['in'], 'in the body');
		pg_assert_true(!empty($text['required']), 'required, so an empty string is a deliberate removal');
		pg_assert_same(5000, $text['max_length'], 'the length the save keeps');
	}
}

function test_api_comment_answer_object()
{
	$objects = api_openapi_objects();

	pg_assert_same('api_comment_answer_schema', isset($objects['CommentAnswer']) ? $objects['CommentAnswer'] : null, 'registered');
	pg_assert_same(array('question_id' => 'integer', 'result' => 'string', 'answer' => 'Comment?'), api_comment_answer_schema(), 'its fields');
}

// The route writes through the screen's function, with the key's owner as
// the member of staff, mails a first answer, and stops a rehearsal before.
function test_api_comment_answer_write_path()
{
	$source = api_comment_answer_test_source('api_comments_answer');

	pg_assert_contains("pg_product_question_answer_save(\$id, \$params['text'], (int)\$app['owner']['id'], (string)\$app['owner']['username'])", $source, 'the screen\'s save, as the owner');
	pg_assert_contains("'not_a_question'", $source, 'only a question');
	pg_assert_contains('pg_product_question_answer_mail($id)', $source, 'the asker is mailed');

	$stop = strpos($source, 'api_dry_run_stop(');
	$save = strpos($source, 'pg_product_question_answer_save(');
	$kind = strpos($source, "'not_a_question'");

	pg_assert_true(($stop !== false) && ($save !== false) && ($stop < $save), 'the rehearsal stops before the write');
	pg_assert_true(($kind !== false) && ($kind < $stop), 'and after the kind check');
}
