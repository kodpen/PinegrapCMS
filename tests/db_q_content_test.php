<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Static checks on includes/fn/content.php: its queries with values run
 * through db_q() placeholders, not escape()/e() concatenated into the SQL
 * text. The raw queries left are those without values and those completed
 * by a clause built elsewhere with escaped values ($where, $sql_where,
 * $sql_comparison), which may hold a '?'.
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
function db_q_content_test_blocks()
{
	$lines = file(PG_FUNCTIONS_DIR . '/includes/fn/content.php');
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

// A raw query completed by a clause holding escaped values stays raw.
function db_q_content_test_has_built_clause($text)
{
	return (bool) preg_match('/\$(where|sql_where|sql_comparison)\b/', $text);
}

function test_db_q_content_has_no_escaped_values_in_query_text()
{
	foreach (db_q_content_test_blocks() as $block) {
		pg_assert_false(
			(bool) preg_match('/\b(escape|e)\(/', $block['text']),
			'includes/fn/content.php line ' . $block['line'] . ' concatenates an escaped value into its SQL');
	}
}

function test_db_q_content_value_queries_use_db_q()
{
	$db_q = 0;

	foreach (db_q_content_test_blocks() as $block) {
		if ($block['call'] === 'db_q') {
			$db_q++;
			continue;
		}

		// A raw query keeps no quoted PHP variable either (WHERE x = '$v').
		pg_assert_false(
			(bool) preg_match('/\'"\s*\.\s*\$|\'\$[a-z_]/i', $block['text'])
				&& !db_q_content_test_has_built_clause($block['text']),
			'includes/fn/content.php line ' . $block['line'] . ' writes a value into a raw query');
	}

	pg_assert_true($db_q >= 23, 'includes/fn/content.php runs ' . $db_q . ' queries through db_q()');
}

function test_db_q_content_numbered_address_name_binds_as_before()
{
	$escape = function ($value) {
		return addcslashes($value, "\\'\"");
	};
	$address_name = "o'neil";

	pg_assert_same(
		"SELECT id FROM products where address_name = '" . $escape($address_name) . "[2]'",
		pg_db_bind('SELECT id FROM products where address_name = ?', array($address_name . '[' . 2 . ']'), $escape));
}
