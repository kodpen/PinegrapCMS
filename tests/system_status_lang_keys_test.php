<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * get_system_status_checks() translates a check's title and message only
 * when it stores them (lang($title), lang($content)), so check_lang.php
 * cannot see the literals handed to $makeIcon(). These tests read the
 * literals from the tokens and require each one to be a Turkish key, and
 * refuse a message that glues a literal to another value before lang()
 * sees it (the joined sentence never matches a key).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(__DIR__ . '/helpers/language_map.php');

/**
 * The tokens of a product file, whitespace and comments dropped.
 *
 * @param string $path relative to pinegrap/
 * @return array
 */
function system_status_lang_tokens($path)
{
	$tokens = array();

	foreach (token_get_all((string) file_get_contents(dirname(__DIR__) . '/pinegrap/' . $path)) as $token) {
		if (is_array($token) && in_array($token[0], array(T_WHITESPACE, T_COMMENT, T_DOC_COMMENT), true)) {
			continue;
		}

		$tokens[] = $token;
	}

	return $tokens;
}

/**
 * The value of a single- or double-quoted literal without variables.
 *
 * @param array $token
 * @return string
 */
function system_status_lang_literal($token)
{
	return stripcslashes(substr($token[1], 1, -1));
}

/**
 * Each $makeIcon(...) call's arguments, each a list of tokens, with the
 * line of the call.
 *
 * @param array $tokens
 * @return array
 */
function system_status_lang_make_icon_calls($tokens)
{
	$calls = array();
	$count = count($tokens);

	for ($i = 0; $i < $count - 1; $i++) {
		if (!is_array($tokens[$i]) || $tokens[$i][0] !== T_VARIABLE || $tokens[$i][1] !== '$makeIcon' || $tokens[$i + 1] !== '(') {
			continue;
		}

		$args = array(array());
		$depth = 0;

		for ($j = $i + 2; $j < $count; $j++) {
			$token = $tokens[$j];

			if (in_array($token, array('(', '[', '{'), true) || (is_array($token) && in_array($token[0], array(T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES), true))) {
				$depth++;
			} elseif (in_array($token, array(')', ']', '}'), true)) {
				if ($depth === 0) {
					break;
				}

				$depth--;
			} elseif ($token === ',' && $depth === 0) {
				$args[] = array();
				continue;
			}

			$args[count($args) - 1][] = $token;
		}

		$calls[] = array('line' => $tokens[$i][2], 'args' => $args);
	}

	return $calls;
}

function test_system_status_lang_make_icon_literals_are_keys()
{
	$map = pg_test_language_map('tr');
	$calls = system_status_lang_make_icon_calls(system_status_lang_tokens('includes/fn/system_status.php'));

	pg_assert_true(count($calls) > 50, 'the $makeIcon() calls are found');

	foreach ($calls as $call) {
		// Argument 3 is the title, argument 4 the message.
		foreach (array(2, 3) as $index) {
			if (!isset($call['args'][$index][0])) {
				continue;
			}

			$arg = $call['args'][$index];

			if (!is_array($arg[0]) || $arg[0][0] !== T_CONSTANT_ENCAPSED_STRING) {
				continue;
			}

			$where = 'system_status.php:' . $call['line'];

			pg_assert_true(count($arg) === 1, $where . ': a literal joined to another value before lang() never matches a key');

			$key = system_status_lang_literal($arg[0]);
			pg_assert_true(isset($map[$key]), $where . ': no Turkish key for "' . $key . '"');
		}
	}
}

function test_system_status_lang_backup_messages_are_keys()
{
	$map = pg_test_language_map('tr');
	$tokens = system_status_lang_tokens('includes/fn/system_status.php');
	$found = 0;

	foreach ($tokens as $i => $token) {
		if (!is_array($token) || $token[0] !== T_VARIABLE || $token[1] !== '$backup_message' || ($tokens[$i + 1] ?? null) !== '=') {
			continue;
		}

		$value = $tokens[$i + 2];

		if (!is_array($value) || $value[0] !== T_CONSTANT_ENCAPSED_STRING || ($tokens[$i + 3] ?? null) !== ';') {
			continue;
		}

		$found++;
		$key = system_status_lang_literal($value);
		pg_assert_true(isset($map[$key]), 'system_status.php:' . $token[2] . ': no Turkish key for "' . $key . '"');
	}

	pg_assert_true($found >= 5, 'the literal backup messages are found');
}

function test_system_status_lang_server_rule_labels_are_keys()
{
	$map = pg_test_language_map('tr');
	$tokens = system_status_lang_tokens('includes/server_config.php');
	$server_names = array('IIS', 'nginx', 'Apache', 'LiteSpeed');
	$found = 0;

	foreach ($tokens as $i => $token) {
		if (!is_array($token) || $token[0] !== T_CONSTANT_ENCAPSED_STRING || system_status_lang_literal($token) !== 'label') {
			continue;
		}

		if (!is_array($tokens[$i + 1] ?? null) || $tokens[$i + 1][0] !== T_DOUBLE_ARROW) {
			continue;
		}

		$value = $tokens[$i + 2];

		if (!is_array($value) || $value[0] !== T_CONSTANT_ENCAPSED_STRING || !in_array($tokens[$i + 3] ?? null, array(',', ')', ']'), true)) {
			continue;
		}

		$key = system_status_lang_literal($value);

		if (in_array($key, $server_names, true)) {
			continue;
		}

		$found++;
		pg_assert_true(isset($map[$key]), 'server_config.php:' . $token[2] . ': no Turkish key for "' . $key . '"');
	}

	pg_assert_true($found >= 10, 'the server rule labels are found');
}
