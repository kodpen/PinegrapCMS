<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for where the checkout handlers (billing_information.php,
 * express_order.php, submit_order.php) record the e-mail marketing answer in
 * consent_log: in the branch that writes it to the contact, after that write,
 * so the log holds what the contact holds, also for a submit that is refused
 * or never paid afterwards.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The index of the next token from $i that is not whitespace or a comment,
// moving by $step (1 or -1); -1 when there is none.
function checkout_opt_in_consent_test_next(array $tokens, $i, $step)
{
	for ($i += $step; ($i >= 0) && ($i < count($tokens)); $i += $step) {
		if (!is_array($tokens[$i]) || !in_array($tokens[$i][0], array(T_WHITESPACE, T_COMMENT, T_DOC_COMMENT), true)) {
			return $i;
		}
	}

	return -1;
}

// The index of the bracket that closes (step 1) or opens (step -1) the one at
// $i; -1 when there is none. A brace opened inside a string ("{$x}", "${x}")
// is closed by a plain '}' token, so it counts as an opening brace.
function checkout_opt_in_consent_test_match(array $text, $i, $step)
{
	$open = array('(' => true, '{' => true, '${' => true);
	$close = array(')' => true, '}' => true);
	$depth = 0;

	for (; ($i >= 0) && ($i < count($text)); $i += $step) {
		$t = $text[$i];

		if (($step > 0) ? isset($open[$t]) : isset($close[$t])) {
			$depth++;
		} elseif (($step > 0) ? isset($close[$t]) : isset($open[$t])) {
			$depth--;

			if ($depth === 0) {
				return $i;
			}
		}
	}

	return -1;
}

// Every call of a consent log writer (pg_consent_log_*) in a file: its line,
// its arguments, the condition of the if block it sits in ('' when that block
// is no if) and the part of that block before the call. Read from the tokens,
// so braces in strings and comments do not count.
function checkout_opt_in_consent_test_calls($file)
{
	$tokens = token_get_all((string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file));
	$text = array();

	foreach ($tokens as $token) {
		$text[] = is_array($token) ? $token[1] : $token;
	}

	$calls = array();

	foreach ($tokens as $i => $token) {
		if (!is_array($token) || ($token[0] !== T_STRING) || (strpos($token[1], 'pg_consent_log_') !== 0)) {
			continue;
		}

		$paren = checkout_opt_in_consent_test_next($tokens, $i, 1);

		if (($paren < 0) || ($text[$paren] !== '(')) {
			continue;
		}

		$paren_end = checkout_opt_in_consent_test_match($text, $paren, 1);
		$arguments = ($paren_end > $paren) ? implode('', array_slice($text, $paren, $paren_end - $paren + 1)) : '';

		// The block the call is in: the nearest brace before it that is not
		// closed before it.
		$depth = 0;
		$block = -1;

		for ($j = $i - 1; $j >= 0; $j--) {
			if ($text[$j] === '}') {
				$depth++;
			} elseif (($text[$j] === '{') || ($text[$j] === '${')) {
				if ($depth === 0) {
					$block = $j;
					break;
				}

				$depth--;
			}
		}

		$condition = '';
		$before = '';

		if ($block >= 0) {
			$before = implode('', array_slice($text, $block, $i - $block));
			$close = checkout_opt_in_consent_test_next($tokens, $block, -1);

			if (($close >= 0) && ($text[$close] === ')')) {
				$open = checkout_opt_in_consent_test_match($text, $close, -1);
				$keyword = ($open >= 0) ? checkout_opt_in_consent_test_next($tokens, $open, -1) : -1;

				if (($keyword >= 0) && is_array($tokens[$keyword]) && in_array($tokens[$keyword][0], array(T_IF, T_ELSEIF), true)) {
					$condition = implode('', array_slice($text, $open, $close - $open + 1));
				}
			}
		}

		$calls[] = array(
			'line' => $token[2],
			'name' => $token[1],
			'arguments' => $arguments,
			'condition' => $condition,
			'before' => $before);
	}

	return $calls;
}

// Each handler that writes the box's answer to the contact records it there:
// inside the branch that runs only when the contact is updated, after the
// UPDATE that carries opt_in, with the value written and the ghost flag.
// Nothing records it anywhere else, such as from the orders row once the
// order is placed.
function test_checkout_opt_in_consent_is_recorded_where_the_contact_takes_it()
{
	foreach (array('billing_information.php', 'express_order.php', 'submit_order.php') as $file) {
		$calls = checkout_opt_in_consent_test_calls($file);

		pg_assert_true(count($calls) > 0, $file . ': records the answer');

		foreach ($calls as $call) {
			$label = $file . ':' . $call['line'] . ' ' . $call['name'];

			pg_assert_contains('update_contact', $call['condition'], $label . ': only when the contact is updated');
			pg_assert_same(1, preg_match("/UPDATE\\s+contacts\\b.*?\\bopt_in\\s*=\\s*'\\\$opt_in'/s", $call['before']), $label . ': after the contact takes the answer');
			pg_assert_same(1, preg_match('/\$opt_in\b/', $call['arguments']), $label . ': the value written');
			pg_assert_same(1, preg_match('/\$ghost\b/', $call['arguments']), $label . ': staff ghosting known');
		}
	}
}
