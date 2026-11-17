<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests that the external API's core handlers answer through api_send()
 * (api_ok(), api_fail() and the like) and never return their answer: the
 * router treats a handler that returns as a fault and answers 500
 * server_error, so a returned body never reaches the client. Read from the
 * source with the tokenizer, closures inside a handler left out.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The handler names the core schema routes to.
function api_handler_sends_test_handlers()
{
	preg_match_all("/'handler'\\s*=>\\s*'(\\w+)'/", (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/api/schema.php'), $match);

	return array_fill_keys($match[1], true);
}

// The lines of $source where a named function in $names returns a value at
// its own level, as "name:line". A closure's return belongs to the closure
// and is skipped; an arrow function has no return keyword. Pure.
function api_handler_sends_test_returns($source, array $names)
{
	$tokens = token_get_all($source);
	$count = count($tokens);
	$found = array();

	for ($i = 0; $i < $count; $i++) {
		if (!is_array($tokens[$i]) || ($tokens[$i][0] !== T_FUNCTION)) {
			continue;
		}

		$j = $i + 1;

		while (($j < $count) && is_array($tokens[$j]) && ($tokens[$j][0] === T_WHITESPACE)) {
			$j++;
		}

		if (($j >= $count) || !is_array($tokens[$j]) || ($tokens[$j][0] !== T_STRING) || !isset($names[$tokens[$j][1]])) {
			continue;
		}

		$name = $tokens[$j][1];

		while (($j < $count) && ($tokens[$j] !== '{')) {
			$j++;
		}

		$depth = 0;
		// Depths at which a closure's body opened; a return inside one is not the handler's.
		$closures = array();
		$closure_next = false;

		for ($k = $j; $k < $count; $k++) {
			$token = $tokens[$k];

			if (($token === '{') || (is_array($token) && in_array($token[0], array(T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES), true))) {
				$depth++;

				if ($closure_next && ($token === '{')) {
					$closures[] = $depth;
					$closure_next = false;
				}
			} elseif ($token === '}') {
				if ($closures && (end($closures) === $depth)) {
					array_pop($closures);
				}

				$depth--;

				if ($depth === 0) {
					break;
				}
			} elseif (is_array($token) && ($token[0] === T_FUNCTION)) {
				$closure_next = true;
			} elseif (is_array($token) && ($token[0] === T_RETURN) && !$closures) {
				$m = $k + 1;

				while (($m < $count) && is_array($tokens[$m]) && ($tokens[$m][0] === T_WHITESPACE)) {
					$m++;
				}

				if (($m < $count) && ($tokens[$m] !== ';')) {
					$found[] = $name . ':' . $token[2];
				}
			}
		}

		$i = $k;
	}

	return $found;
}

function test_api_handler_sends_test_finds_a_returned_answer()
{
	$source = "<?php\nfunction h(\$p) {\n\t\$ids = array_map(function (\$r) { return \$r['id']; }, \$p);\n\tif (!\$ids) {\n\t\treturn;\n\t}\n\treturn array('ids' => \$ids);\n}\nfunction other() {\n\treturn 1;\n}\n";

	pg_assert_same(array('h:7'), api_handler_sends_test_returns($source, array('h' => true)));
}

function test_api_core_handlers_never_return_their_answer()
{
	$handlers = api_handler_sends_test_handlers();

	pg_assert_true(count($handlers) > 100, 'the core schema names its handlers');

	$found = array();

	foreach (glob(PG_FUNCTIONS_DIR . '/includes/api/resources/*.php') as $file) {
		foreach (api_handler_sends_test_returns((string) file_get_contents($file), $handlers) as $hit) {
			$found[] = basename($file) . ' ' . $hit;
		}
	}

	pg_assert_same(array(), $found);
}
