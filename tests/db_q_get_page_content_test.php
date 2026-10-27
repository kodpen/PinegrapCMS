<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Static checks on get_page_content.php: its queries with values run through
 * db_q() placeholders, not escape()/e() concatenated into the SQL text. The
 * only raw queries still concatenating values are the two watcher queries
 * whose WHERE clause is completed by pg_comments_thread_sql().
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Each query statement of the file: from "$query =" to the call running it.
function db_q_get_page_content_test_blocks()
{
	$lines = file(PG_FUNCTIONS_DIR . '/get_page_content.php');
	$blocks = array();
	$start = null;

	foreach ($lines as $index => $line) {
		if (preg_match('/\$query\s*=/', $line)) {
			$start = $index;
		}

		if ($start !== null && preg_match('/(mysqli_query|db_q)\(/', $line)) {
			$blocks[] = array(
				'line' => $start + 1,
				'call' => strpos($line, 'db_q(') !== false ? 'db_q' : 'mysqli_query',
				'text' => implode('', array_slice($lines, $start, $index - $start + 1)));
			$start = null;
		}
	}

	return $blocks;
}

function test_db_q_get_page_content_has_no_escaped_values_in_query_text()
{
	foreach (db_q_get_page_content_test_blocks() as $block) {
		if (!preg_match('/\b(escape|e)\(/', $block['text'])) {
			continue;
		}

		pg_assert_true(
			strpos($block['text'], '$sql_where_watchers') !== false,
			'get_page_content.php line ' . $block['line'] . ' concatenates an escaped value into its SQL');
	}
}

function test_db_q_get_page_content_value_queries_use_db_q()
{
	$db_q = 0;

	foreach (db_q_get_page_content_test_blocks() as $block) {
		if ($block['call'] === 'db_q') {
			$db_q++;
			continue;
		}

		// A raw query keeps no quoted PHP variable either (WHERE x = '$v').
		pg_assert_false(
			(bool) preg_match('/\'"\s*\.\s*\$|\'\$[a-z_]/i', $block['text'])
				&& strpos($block['text'], '$sql_where_watchers') === false,
			'get_page_content.php line ' . $block['line'] . ' writes a value into a raw query');
	}

	pg_assert_true($db_q >= 24, 'get_page_content.php runs ' . $db_q . ' queries through db_q()');
}

function test_db_q_get_page_content_like_pattern_binds_as_before()
{
	$escape = function ($value) {
		return addcslashes($value, "\\'\"");
	};
	$email = "o'neil_x%@example.com";

	pg_assert_same(
		"SELECT 1 WHERE email_address LIKE '%" . $escape(escape_like($email)) . "%'",
		pg_db_bind('SELECT 1 WHERE email_address LIKE ?', array('%' . escape_like($email) . '%'), $escape));
}

function test_db_q_get_page_content_empty_string_stays_quoted()
{
	// escape(null) wrote '' where (string) now passes ''; null would be NULL.
	pg_assert_same("SELECT 1 WHERE id = ''", pg_db_bind('SELECT 1 WHERE id = ?', array((string) null), 'addslashes'));
}
